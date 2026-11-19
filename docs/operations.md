# Operación y crons

## Lo que tiene que estar siempre en marcha

| Qué | Cómo | Si falta |
| --- | --- | --- |
| **Worker de la cola** | Servicio `worker` (`bin/cake queue run`), lo reinicia Docker | **No sale ningún email** (se quedan en la cola); *System › Queue* avisa «Queue stopped» |
| **Cron diario: `bin/cake maintenance`** | Coolify: *Scheduled Task* `0 3 * * *` en el contenedor `app` | Crecen sin límite la actividad, el histórico, la cola, las sesiones y los contadores; no se cumple la retención de datos personales |

No hay más crons. Si hace falta otra tarea diaria, se añade dentro de `maintenance`
(`App\UseCase\Maintenance\DailyMaintenance`) y el cron no cambia.

### Qué hace `bin/cake maintenance`

| Paso | Borra |
| --- | --- |
| Account activity | Actividad de las cuentas más antigua que `App.accountActivity.retentionDays` (180 días) |
| Permissions history | Histórico de permisos más antiguo que `App.permissions.auditRetentionDays` (365 días); registra la purga |
| Queue | Trabajos terminados de más de 7 días y fallidos de más de 30 |
| Expired sessions | Sesiones caducadas de la tabla `sessions` |
| Throttling counters | Ventanas caducadas del límite por IP |

Muestra cuántas filas borró cada paso; si uno falla, los demás siguen y el comando sale con error (y deja el motivo
en el log).

### Sin Docker ni Coolify (crontab)

```cron
# cron diario
0 3 * * * cd /ruta/a/la/app && bin/cake maintenance
# worker, si no tienes un supervisor (systemd/supervisord): arranca uno cada 10 min, cada uno vive 600 s
*/10 * * * * cd /ruta/a/la/app && bin/cake queue run -q
```

Con systemd, mejor un servicio con `ExecStart=/ruta/a/la/app/bin/cake queue run` y `Restart=always`. Ejecútalo con el
mismo usuario y la misma versión de PHP que la web.

## Comandos de la app (`src/Command`) y cuándo se ejecutan

| Archivo | Comando | Qué hace | Cuándo | ¿Programarlo? |
| --- | --- | --- | --- | --- |
| `MaintenanceCommand.php` | `bin/cake maintenance` | Todas las tareas diarias: purga de actividad y de histórico de permisos, limpieza de la cola, sesiones caducadas y contadores del límite por IP | **Una vez al día** | **Sí: es el único cron** (Coolify: *Scheduled Task* `0 3 * * *`, contenedor `app`) |
| `PermissionsSyncCommand.php` | `bin/cake permissions sync [--reset]` | Registra las acciones nuevas de los controladores con sus permisos por defecto y marca las que ya no existen | **En cada deploy** | No: lo ejecuta `bin/deploy` (servicio `migrate`). A mano en dev, tras crear controladores |
| `ActivityPurgeCommand.php` | `bin/cake activity purge [--days N] [--dry-run]` | Borra la actividad de las cuentas más antigua que la retención (180 días) | Ya incluido en `maintenance` | **No** (se duplicaría). Solo a mano, p. ej. con `--dry-run` para ver cuánto borraría |
| `PermissionsPurgeCommand.php` | `bin/cake permissions purge [--days N] [--dry-run]` | Borra el histórico de permisos más antiguo que la retención (365 días) | Ya incluido en `maintenance` | **No** (se duplicaría). Solo a mano |
| `TwoFactorResetCommand.php` | `bin/cake two_factor reset <usuario\|email>` | Quita los dos métodos de 2FA de una cuenta (superadmin que perdió su dispositivo) | Cuando haga falta | **No**: es una acción manual de soporte |

Además, fuera de `src/Command`: el **worker** (`bin/cake queue run`, del plugin Queue) no es un cron sino un proceso que
está siempre en marcha (servicio `worker`), y `bin/deploy` / `bin/migrate` son scripts del deploy.

Al crear un comando nuevo, añádelo a esta tabla. Si debe ejecutarse a diario, no crees otro cron: añade el paso a
`App\UseCase\Maintenance\DailyMaintenance` (y su comando, si quieres poder lanzarlo a mano).

## Referencia rápida de comandos

| Comando | Para qué |
| --- | --- |
| `bin/deploy` | Migraciones + sync de permisos (lo ejecuta `migrate` en cada deploy) |
| `bin/migrate` | Solo las migraciones (CakeDC/Users, Queue, app) |
| `bin/cake maintenance` | El cron diario |
| `bin/cake permissions sync [--reset]` | Descubrir acciones nuevas (y reaplicar los permisos por defecto con `--reset`) |
| `bin/cake permissions purge [--days N] [--dry-run]` | Purga manual del histórico de permisos |
| `bin/cake activity purge [--days N] [--dry-run]` | Purga manual de la actividad de las cuentas |
| `bin/cake two_factor reset <usuario\|email>` | Quitar el 2FA de una cuenta (superadmin sin dispositivo) |
| `bin/cake users add_superuser` | Crear un superadmin |
| `bin/cake queue run` / `queue info [--format json]` / `queue job view <id>` / `queue worker` | Worker y estado de la cola |
| `composer check` | Tests + CodeSniffer + PHPStan |

## Logs

Producción: todo a stderr (`LOG_TO_STDERR`), en los logs del contenedor (Coolify: *Logs*; Docker:
`docker compose logs -f app worker`). Dev: `logs/*.log`.

| Mensaje | Qué significa |
| --- | --- |
| `Queue job N (Queue.Email) gave up after 4 attempts: …` | Un email no se pudo enviar tras los reintentos: revisa el SMTP y reintenta desde *System › Queue* |
| `Queue job N (…) failed, attempt N: …` (warning) | Intento fallido; se reintentará solo |
| `Account activity "…" of user … not recorded: …` | No se pudo escribir la actividad (la acción del usuario sí se hizo) |
| `Maintenance task "…" failed: …` | Un paso del cron diario falló |
| `Passkey login rejected: …` / `WebAuthn second step rejected: …` | Intentos con llaves no válidas (detalle técnico; el usuario ve un mensaje genérico) |

## Salud y monitorización

- `GET /healthz` en `web` (nginx, 200 «ok») para el health check del contenedor.
- `bin/cake queue info --format json`: pendientes, fallidos, workers (para alertas externas).
- *System › Queue*: workers vivos y fallidos.

## Copias de seguridad

Dos cosas guardan estado; los contenedores, nada más:

- **PostgreSQL**: usuarios, roles, permisos, sesiones, cola, actividad, históricos → copias programadas del recurso
  de base de datos (Coolify → S3).
- **Volumen `uploads`**: los archivos subidos (fotos de usuario) → cópialo con la misma frecuencia (p. ej. un
  *Scheduled Task* que lo sincronice con S3, o las copias del servidor).

Restaurar = restaurar la base de datos y el volumen (del mismo momento) y volver a desplegar.

## Actualizaciones

1. En dev: `composer update` (o el PR de Dependabot), `composer check`, prueba con `compose.prod.yaml`.
2. Push: el deploy aplica las migraciones nuevas (también las de los plugins) y registra las acciones nuevas.
3. Tras actualizar el tema o CSS: nada más, el cache busting (`?<mtime>`) invalida los assets.
