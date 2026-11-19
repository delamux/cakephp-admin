# Arquitectura

**DDD-lite.** Controladores y comandos finos; la orquestación (transacciones, auditoría, caché, emails, actividad)
vive en **casos de uso** (`src/UseCase`) inyectados con el contenedor DI de CakePHP; las reglas de dominio están en
clases puras (`UserRoleManager`, `PermissionsSynchronizer`, `ActionDiscovery`) y las Tables hacen de repositorios.
No es DDD completo (sin agregados separados del ORM): el admin es mayormente CRUD. `ArchitectureTest` impide que un
controlador o un comando orqueste por su cuenta (auditoría, caché de permisos, factorías de config).

```
src/
├── UseCase/            # casos de uso: Account, Maintenance, Permissions, Queue, Roles, TwoFactor, Users (+ Actor)
├── Account/            # registro de actividad: ActivityLog (repositorio), listener de CakeDC, UserAgent
├── Queue/              # QueueFailureListener (fallos de la cola al log)
├── Storage/            # archivos subidos: Uploads (convención, almacenamiento), ImageProcessor (GD)
├── Rbac/               # descubrimiento de acciones, sincronización, proveedor RBAC en BD, histórico
├── Service/            # reglas de roles (UserRoleManager), estadísticas del dashboard
├── Authentication/     # checkers de 2FA opcional
├── Webauthn/           # adaptadores WebAuthn (registro, segundo paso, passkey)
├── Http/               # SecurityMiddlewares (CSP, cabeceras, HTTPS, CSRF, cookies), AuthThrottle
├── Middleware/         # límite por IP, proxies de confianza (CIDR), refresco de identidad
├── Command/            # permissions sync/purge, activity purge, two_factor reset, maintenance
├── Controller/         # finos; Component/Htmx y OneTimePasswordAuthenticator propio
├── Mailer/             # UsersMailer/AppMailer + CSS en línea (Emogrifier)
├── Navigation/         # SidebarMenu (menú según rol)
└── Model/              # UsersTable (2FA, sincronía superadmin), Roles, Permissions, auditorías, actividad
config/
├── app.php             # Security.*, sesión, emails (transporte queue), Queue, logs
├── users.php           # CakeDC/Users, 2FA, roles, App.permissions, App.accountActivity
└── permissions.php     # reglas de sistema del RBAC (el resto, en BD)
webroot/js/             # app.js (confirmaciones, CSRF de htmx) y webauthn.js, sin build
docker/                 # Dockerfile multi-stage, nginx, php.ini
docs/                   # esta documentación
```

## Piezas transversales

| Qué | Dónde |
| --- | --- |
| Middlewares y servicios (DI) | `src/Application.php`, `src/Http/SecurityMiddlewares.php` |
| Listeners globales (actividad, fallos de la cola) | `Application::bootstrap()`, registrados una vez por `EventManager` |
| Tema, layout y menú | `src/Controller/AppController.php`, `src/View/AppView.php`, `src/Navigation/SidebarMenu.php` |
| Iconos extra (además de los del tema) | `AppView::EXTRA_ICONS` (rutas SVG de Lucide) |
| Pantallas de CakeDC/Users sobrescritas | `templates/plugin/CakeDC/Users/Users/` |
| Tarjetas reutilizables | `src/View/Cell/` (`TwoFactorCell`, `AccountActivityCell`) |

## Cómo añadir un módulo nuevo (p. ej. «Clientes»)

1. **Migración** en `config/Migrations/` (`bin/cake bake migration CreateCustomers`) y `bin/migrate`.
2. **Table + Entity** en `src/Model/` (los campos sensibles, fuera de `$_accessible`). Las clases fallback están
   desactivadas: toda tabla necesita su clase `Table`.
3. **Caso de uso** en `src/UseCase/Customers/` si hay algo más que CRUD (reglas, transacción, emails, auditoría);
   regístralo en `Application::services()` e inyéctalo en la acción: `public function add(CreateCustomer $create)`.
4. **Controlador fino** en `src/Controller/` (hereda FormProtection, htmx y los guards de `AppController`).
5. **Plantillas** con los helpers del tema (`Card`, `Table`, `Button`, `Badge`, `EmptyState`…) y sin JS ni estilos en
   línea (ver [Seguridad](security.md#plantillas-compatibles-con-la-csp)).
6. **Permisos:** `bin/cake permissions sync` descubre las acciones nuevas; dales acceso por rol en
   *Access control › Permissions* (o en `App.permissions.defaults` de `config/users.php` para instalaciones nuevas).
   Si solo debe tocarlo el superadmin, añádelo a `PermissionsSynchronizer::SUPERADMIN_ONLY`.
7. **Menú:** una entrada en `SidebarMenu::items()` según el rol.
8. **Archivos** (fotos, documentos): `Uploads::attachImage()` y la convención `<tabla>/<id>/<campo>/<uuid>.<ext>`
   ([Subida de archivos](uploads.md#añadir-un-archivo-a-otra-entidad)).
9. **Trabajo pesado o lento** (importaciones, informes grandes, llamadas externas): una tarea de la cola
   ([Cola](queue.md#crear-una-tarea-propia)).
10. **Tareas diarias** (retenciones, limpiezas): un paso más en `App\UseCase\Maintenance\DailyMaintenance`; el cron
   no cambia. Todo comando nuevo va a la tabla de
   [comandos y cuándo se ejecutan](operations.md#comandos-de-la-app-srccommand-y-cuándo-se-ejecutan).
11. **Tests** de las reglas propias (TDD) y textos en `resources/locales/es_ES/default.po`.
