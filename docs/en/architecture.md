# Architecture

**English** · [Español](../es/architecture.md)

**DDD-lite.** Thin controllers and commands; orchestration (transactions, auditing, caching, emails, activity) lives
in **use cases** (`src/UseCase`) injected with CakePHP's DI container; domain rules live in plain classes
(`UserRoleManager`, `PermissionsSynchronizer`, `ActionDiscovery`) and Tables act as repositories. It isn't full DDD
(no aggregates separate from the ORM): the admin is mostly CRUD. `ArchitectureTest` prevents a controller or command
from orchestrating on its own (auditing, permissions cache, config factories).

```
src/
├── UseCase/            # use cases: Account, Maintenance, Permissions, Queue, Roles, TwoFactor, Users (+ Actor)
├── Account/            # activity log: ActivityLog (repository), CakeDC listener, UserAgent
├── Queue/              # QueueFailureListener (queue failures to the log)
├── Storage/            # uploaded files: Uploads (convention, storage), ImageProcessor (GD)
├── Rbac/               # action discovery, synchronization, database RBAC provider, history
├── Service/            # role rules (UserRoleManager), dashboard statistics
├── Authentication/     # optional 2FA checkers
├── Webauthn/           # WebAuthn adapters (registration, second step, passkey)
├── Http/               # SecurityMiddlewares (CSP, headers, HTTPS, CSRF, cookies), AuthThrottle
├── Middleware/         # per-IP rate limiting, trusted proxies (CIDR), identity refresh
├── Command/            # permissions sync/purge, activity purge, two_factor reset, maintenance
├── Controller/         # thin; Htmx component and custom OneTimePasswordAuthenticator
├── Mailer/             # UsersMailer/AppMailer + inline CSS (Emogrifier)
├── Navigation/         # SidebarMenu (role-based menu)
└── Model/              # UsersTable (2FA, superadmin sync), Roles, Permissions, audits, activity
config/
├── app.php             # Security.*, session, emails (queue transport), Queue, logs
├── users.php           # CakeDC/Users, 2FA, roles, App.permissions, App.accountActivity
└── permissions.php     # RBAC system rules (the rest live in the database)
webroot/js/             # app.js (confirmations, htmx CSRF) and webauthn.js, no build step
docker/                 # multi-stage Dockerfile, nginx, php.ini
docs/                   # this documentation
```

## Cross-cutting pieces

| What | Where |
| --- | --- |
| Middlewares and services (DI) | `src/Application.php`, `src/Http/SecurityMiddlewares.php` |
| Global listeners (activity, queue failures) | `Application::bootstrap()`, registered once per `EventManager` |
| Theme, layout and menu | `src/Controller/AppController.php`, `src/View/AppView.php`, `src/Navigation/SidebarMenu.php` |
| Extra icons (besides the theme's) | `AppView::EXTRA_ICONS` (Lucide SVG paths) |
| Overridden CakeDC/Users screens | `templates/plugin/CakeDC/Users/Users/` |
| Reusable cards | `src/View/Cell/` (`TwoFactorCell`, `AccountActivityCell`) |

## How to add a new module (e.g. "Customers")

1. **Migration** in `config/Migrations/` (`bin/cake bake migration CreateCustomers`) and `bin/migrate`.
2. **Table + Entity** in `src/Model/` (sensitive fields kept out of `$_accessible`). Fallback classes are disabled:
   every table needs its own `Table` class.
3. **Use case** in `src/UseCase/Customers/` if there's more than CRUD (rules, transactions, emails, auditing);
   register it in `Application::services()` and inject it into the action: `public function add(CreateCustomer $create)`.
4. **Thin controller** in `src/Controller/` (it inherits FormProtection, htmx and the guards from `AppController`).
5. **Templates** using the theme helpers (`Card`, `Table`, `Button`, `Badge`, `EmptyState`…) with no inline JS or
   styles (see [Security](security.md#csp-compatible-templates)). For a CRUD, **bake** generates them already with the
   theme (`Bake.theme` = `BasecoatTheme` in `config/app.php`): `bin/cake bake all Customers` writes table, entity,
   controller, the four screens (list, detail, form, delete in a dialog) and the tests; then adapt them.
6. **Permissions:** `bin/cake permissions sync` discovers the new actions; grant access per role in
   *Access control › Permissions* (or in `App.permissions.defaults` in `config/users.php` for new installations).
   If only the superadmin should touch it, add it to `PermissionsSynchronizer::SUPERADMIN_ONLY`.
7. **Menu:** an entry in `SidebarMenu::items()` depending on the role.
8. **Files** (photos, documents): `Uploads::attachImage()` and the `<table>/<id>/<field>/<uuid>.<ext>` convention
   ([File uploads](uploads.md#adding-a-file-to-another-entity)).
9. **Heavy or slow work** (imports, large reports, external calls): a queue task
   ([Queue](queue.md#creating-your-own-task)).
10. **Daily tasks** (retention, cleanups): one more step in `App\UseCase\Maintenance\DailyMaintenance`; the cron
   doesn't change. Every new command goes into the table of
   [commands and when they run](operations.md#app-commands-srccommand-and-when-they-run).
11. **Tests** for your own rules (TDD) and strings in `resources/locales/es_ES/default.po`.
