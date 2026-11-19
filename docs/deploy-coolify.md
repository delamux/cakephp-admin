# Despliegue en Coolify

`compose.coolify.yaml` (build pack **Docker Compose**): Coolify construye las imágenes desde el repositorio en cada
deploy y su proxy (Traefik + Let's Encrypt) pone el HTTPS delante de nginx. No se publica ningún puerto.

```
Internet ──HTTPS──> Traefik (Coolify) ──HTTP──> web (nginx) ──FastCGI──> app (PHP-FPM) ──> PostgreSQL (recurso de Coolify)
                                                                        worker (cola) ──┘     └── SMTP real
migrate (una vez por deploy: bin/deploy = migraciones + sync de permisos)
```

| Servicio | Imagen (target del Dockerfile) | Qué hace |
| --- | --- | --- |
| `web` | `web` | nginx con los estáticos; `/healthz` para el health check |
| `app` | `prod` | PHP-FPM |
| `worker` | `prod` | `bin/cake queue run` (emails y trabajos) |
| `migrate` | `prod` | `bin/deploy` y termina (`restart: "no"`, fuera del health check); `app` y `worker` esperan a que acabe bien |

## Paso a paso

1. **Repositorio** en GitHub (o GitLab/Gitea) accesible para Coolify.
2. **Base de datos:** *New Resource → Database → PostgreSQL 18* en el mismo servidor. Activa las **copias de
   seguridad programadas** (S3) y copia su **Internal URL** (`postgres://usuario:clave@<contenedor>:5432/<bd>`).
3. **Aplicación:** *New Resource → Application* desde el repositorio, build pack **Docker Compose**, archivo
   `/compose.coolify.yaml`.
4. **Red:** *Advanced → Docker compose → Connect to predefined network*, para que los contenedores lleguen a la base
   de datos.
5. **Dominio:** en el servicio `web`, el dominio definitivo (`https://admin.example.com`). Coolify lo pone en
   `SERVICE_URL_WEB` → `APP_FULL_BASE_URL`. **Elígelo antes de que nadie registre llaves o passkeys**: quedan atadas
   a él.
6. **Variables** (*Environment Variables*):
   - `DATABASE_URL`: la Internal URL del paso 2.
   - `EMAIL_TRANSPORT_DEFAULT_URL`: el SMTP real (`smtp://usuario:clave@smtp.example.com:587?tls=true`).
   - `EMAIL_FROM` (y opcionalmente `EMAIL_FROM_NAME`).
   - `SECURITY_SALT` y `COOKIE_KEY` los genera Coolify la primera vez (`SERVICE_HEX_64_*`) y los conserva:
     **no los cambies** (cerraría todas las sesiones e invalidaría las cookies «recordarme»).
   - Opcionales: `TWO_FACTOR_ENABLED`, `SESSION_TIMEOUT`, `TRUSTED_PROXIES` (por defecto, los rangos privados de
     Docker: ver [Seguridad](security.md#proxies-de-confianza)).
7. **Deploy.** `migrate` crea o actualiza el esquema y registra las acciones; después arrancan `app`, `worker` y `web`.
8. **Primer superadmin** (solo la primera vez), desde *Terminal* del contenedor `app`:
   ```bash
   bin/cake users add_superuser -u admin -e admin@example.com    # sin -p genera la contraseña y la muestra
   ```
9. **Tarea diaria:** *Scheduled Tasks → Add*: nombre `maintenance`, comando `bin/cake maintenance`, frecuencia
   `0 3 * * *`, contenedor `app`. Es el único cron ([Operación](operations.md)).
10. **Comprueba:** entra con el superadmin, mira *System › Queue* (debe haber un worker vivo) y pide un reset de
    contraseña para ver que el email llega.

## Cada deploy

Push → Coolify construye → `migrate` (migraciones + acciones nuevas en el catálogo de permisos) → se sustituyen
`app`, `worker` y `web`. Las sesiones y la cola están en PostgreSQL: nadie pierde la sesión y no se pierden
trabajos (el worker termina el que tiene en curso antes de parar). Si `migrate` falla, `app` y `worker` no arrancan
con el código nuevo y el deploy queda fallido: revisa el log de `migrate` en Coolify.

## Notas

- **Archivos subidos** (fotos de usuario): volumen `uploads`, que Coolify conserva entre deploys. Inclúyelo en tus
  copias de seguridad ([Subida de archivos](uploads.md#almacenamiento)).
- Escalar: réplicas de `worker` sin problema (el estado vive en PostgreSQL); de `app`, **una sola** mientras los
  archivos estén en el volumen local (para más, almacenamiento S3).
- Logs: todo va a stderr (*Logs* de cada servicio en Coolify). Qué vigilar: [Operación](operations.md#logs).
- No hay Mailpit en producción: hace falta un SMTP real (Postmark, SES, Mailgun, el de tu dominio…).
- Probar en local algo parecido: `compose.prod.yaml` (mismas imágenes, migraciones automáticas y worker).
