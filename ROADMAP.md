# ROADMAP — CakePHP Admin Skeleton

Esqueleto de administración completo: login, gestión de usuarios y dashboard, listo para clonar y arrancar proyectos.

## Reglas del proyecto (no negociables)

- **PHP 8.5** (`"php": ">=8.5"` en composer.json).
- **Última versión de CakePHP** (5.4.x).
- **Context7 siempre** para consultar la API de CakePHP/plugins (`/cakephp/docs`, `/cakedc/users`, `/cakephp/authentication`, `/cakephp/authorization`, `/cakephp/migrations`, `/friendsofcake/cakepdf`).
- Catálogo de plugins de referencia: https://github.com/FriendsOfCake/awesome-cakephp
- **Antes de instalar cualquier plugin/paquete: preguntar al usuario y explicar para qué sirve.**
- **Todo en Docker local**, BD **PostgreSQL 18**.
- Correos con **Mailpit** en desarrollo.
- Correos HTML **bonitos y con máxima compatibilidad** (tablas, CSS inline, 600px, versión texto, dark mode).
- PDFs con **CakePdf** (Dompdf).
- Inspiración visual: https://shadcnuikit.com/dashboard/default
- El usuario es autor del tema basecoat y autoriza modificarlo si hace falta (confirmar antes de push/PR).

## Stack

| Pieza | Paquete | Estado |
| --- | --- | --- |
| Framework | `cakephp/cakephp` 5.4.* (skeleton `cakephp/app` 5.4.0) | ✅ |
| Usuarios/login/RBAC | `cakedc/users` ^16.0 (+ `cakedc/auth`, `cakephp/authentication`, `cakephp/authorization`) | ✅ (pedido) |
| Tema UI | `delamux/cakephp-basecoat-theme` dev-main (VCS GitHub, no está en Packagist; PHP >= 8.2 desde `b3dd6e5`) | ✅ (pedido) |
| PDFs | `friendsofcake/cakepdf` 5.3 + `dompdf/dompdf` 3.1 | ✅ (aprobado) |
| CSS inline emails | `pelago/emogrifier` 8.3 | ✅ (aprobado) |
| Análisis estático | `phpstan/phpstan` 2.3 (dev) | ✅ (aprobado) |
| Infra | Docker Compose: nginx + `php:8.5-fpm` (Dockerfile multi-stage dev/prod/web), `postgres:18-alpine`, `axllent/mailpit` | ✅ (FrankenPHP → PHP-FPM el 2026-11-18, decisión del usuario) |

Puertos: app http://localhost:8080 · Mailpit UI http://localhost:8026 (SMTP host 1026) · Postgres localhost:5433.
(8025 lo usa el Mailpit de otro proyecto local.)

## Fases

### Fase 0 — Base ✅
- [x] `composer create-project cakephp/app:~5.4`, PHP >=8.5
- [x] cakedc/users + basecoat theme
- [x] ROADMAP.md

### Fase 1 — Configuración base ✅
- [x] Docker: FrankenPHP + PG18 (+ BD de tests `cakephp_admin_test`) + Mailpit (sustituido por nginx + PHP-FPM en la Fase 12)
- [x] Driver Postgres / encoding utf8 en `config/app.php`; URLs por env
- [x] Plugins en `src/Application.php` (`CakeDC/Users`, `BasecoatTheme`, `CakePdf`)
- [x] `config/users.php` (loginRedirect → Dashboard, reCaptcha off, RememberMe, OneTimeLogin, mailer propio, mensaje de acceso denegado)
- [x] `config/permissions.php` (Dashboard para todos los logueados, admin todo, superuser bypass)
- [x] Migraciones CakeDC/Users + superusuario `admin`
- [x] Seed de demo `bin/cake seeds run DemoUsers`

### Fase 2 — Tema y layout ✅
- [x] `AppController::beforeRender`: tema BasecoatTheme, layout `auth` sin identidad, sidebar/usuario por request
- [x] `AppView`: `loadBasecoatHelpers()` + `CakeDC/Users.User` / `AuthLink`
- [x] `App\Navigation\SidebarMenu` (TDD): menú según rol, bloque de usuario con Perfil / Cambiar contraseña / Log out

### Fase 3 — Plantillas CakeDC/Users ✅
- [x] login, register, request_reset_password, request_login_link, resend_token_validation, change_password
- [x] profile, index (sort, paginación, menú de fila, columnas responsive), view, add, edit (+ danger zone)

### Fase 4 — Dashboard ✅
- [x] `DashboardStatsService` (TDD): totales, activos, pendientes, admins, nuevos 30d vs 30d previos, serie diaria, recientes, administradores
- [x] KPIs, gráfico de barras de altas, últimos usuarios, equipo; usuario normal ve tarjeta de bienvenida

### Fase 5 — Correo ✅
- [x] SMTP → Mailpit por `EMAIL_TRANSPORT_DEFAULT_URL`
- [x] `CssInliner` (TDD) + `InlineCssTrait` (inline antes del wrap del body) + `AppMailer` / `UsersMailer`
- [x] Layout HTML (tablas, 600px, preheader, botón bulletproof, MSO, dark mode, móvil) + layout texto
- [x] Emails CakeDC sobrescritos: validación, reset, social, login link; asuntos propios
- [x] Probado en Mailpit: html-check ~90–92 % soportado (resto: @media/<style> como mejora progresiva)

### Fase 6 — PDF ✅
- [x] `/reports/users.pdf` (admins): KPIs + tabla; `?download=1` fuerza descarga `users-report-YYYYMMDD-HHMMSS.pdf`

### Fase 7 — Calidad ✅
- [x] `composer check` = phpunit (26 tests) + phpcs (CakePHP standard) + phpstan nivel 8, todo verde
- [x] README

### Fase 8 — QA UI/UX ✅ (2026-11-18)
Recorrido con Chrome DevTools (desktop 1280, móvil 390, light/dark, admin y usuario normal, emails en Mailpit, PDF).
Corregido durante la QA:
- [x] Plural "1 admins" → `__n`
- [x] Tabla "Latest users": fechas largas empujaban el menú de acciones → fechas compactas + `whitespace-nowrap` + scroll-x
- [x] Gráfico: línea suavizada exageraba datos diarios → barras
- [x] "Team" solo mostraba admins entre los 8 últimos → `administrators()` (TDD)
- [x] Listado de usuarios cortado → email bajo el nombre, columnas secundarias ocultas por container query
- [x] Header en móvil desbordado → acciones icon-only (`element/header_action`, label sr-only) y fecha oculta en móvil
- [x] Badge de estado sin icono en ficha de usuario
- [x] Mensaje "not authorized … Location = URL" (debug) → mensaje propio con título
- [x] Rango de fechas visible a usuarios sin estadísticas → solo admins
- [x] Perfil/ficha/edición apilados a 1280px → breakpoint `@3xl`
- [x] Flash de error pegado al primer campo en formularios auth → dentro del grid del form
- [x] Email login link mostraba un "código" de 32 hex con form de 6 dígitos (inconsistencia del plugin) → solo enlace
- [x] Emails: quitado `word-break` y hacks de preheader con soporte bajo
- [x] PDF: "1 / 0" (Dompdf no soporta `counter(pages)`) → "Page N"

### Fase 9 — Permisos por base de datos (TDD) ✅ (2026-11-18)

**Objetivo:** dejar de definir los permisos de cada rol en `config/permissions.php` y gestionarlos desde la BD
con una UI: descubrir automáticamente los controladores y sus acciones públicas y asignarlas a cada rol.

**Punto de enganche (sin tocar CakeDC):** `CakeDC\Auth\Rbac` acepta `permissions_provider_class`
(hoy `ConfigProvider`, que lee el fichero). Se implementa `App\Rbac\DatabasePermissionsProvider`
(extiende `AbstractProvider`) y se configura en `Auth.RbacPolicy` → el RBAC de CakeDC sigue igual,
solo cambia de dónde salen las reglas.

**Propuesta de diseño (a confirmar):**
- `App\Rbac\ActionDiscovery`: descubre controladores (app + plugins elegidos) y sus acciones públicas
  vía reflexión (métodos `public` declarados en el controlador o sus traits, sin `initialize`,
  `beforeFilter`, callbacks ni métodos de `Controller`), con prefix/plugin/controller/action.
- Tablas: `permissions` (catálogo descubierto: plugin, prefix, controller, action, `public` para acciones
  sin login) y `roles_permissions` (rol ↔ permiso, `allowed`).
- Comando `bin/cake permissions sync` (+ botón en la UI) que sincroniza el catálogo con el código:
  añade acciones nuevas, marca huérfanas las que ya no existen.
- Reglas que **no** van a BD (siguen en código por seguridad): acciones públicas de login/registro/reset,
  `superadmin` = todo (y `is_superuser` sigue saltándose el RBAC), DebugKit en debug.
- UI: matriz *controlador/acción × rol* con checkboxes, solo para superadmin; cambios en caché
  (`_cake_rbac_`) invalidada al guardar.
- Seguridad: no permitir quitarse acceso a la propia pantalla de permisos; superadmin no editable;
  auditoría (quién cambió qué y cuándo) opcional.

**Tests (TDD):** descubrimiento de acciones (qué cuenta como acción pública), sync del catálogo,
provider → reglas CakeDC equivalentes a las actuales de `permissions.php` (test de no-regresión:
mismas decisiones allow/deny para los 4 roles), matriz UI → reglas, invalidación de caché, guards
(nadie se bloquea a sí mismo).

**Decisiones (usuario, 2026-11-18):**
| Tema | Decisión |
| --- | --- |
| Alcance del descubrimiento | Controladores de la app + CakeDC/Users (Users, SocialAccounts). Excluidos DebugKit, Bake y plugins técnicos |
| Roles | Pasan a BD con CRUD (tabla `roles`, seed con superadmin/admin/editor/user). `superadmin` protegido: no se borra ni se renombra |
| Granularidad | Lista blanca *allow* por acción (todas las extensiones). Lo no marcado se deniega |
| `config/permissions.php` | Solo reglas de sistema: acciones públicas (login/registro/reset/validación), superadmin = todo, DebugKit, perfil/logout propios. El resto desde la BD, con seed que replica los permisos actuales |

**Plan de implementación (cada paso: test rojo → verde → refactor):**
1. Migraciones: `roles` (slug único, nombre, `protected`), `permissions` (plugin, prefix, controller, action, único por combinación, `orphan`), `roles_permissions`. `users.role` sigue siendo el slug.
2. `App\Rbac\ActionDiscovery`: lista de acciones públicas (reflexión; excluye métodos de `Controller`/`AppController`, callbacks y no públicos; incluye traits como los de CakeDC).
3. `bin/cake permissions sync`: inserta nuevas, marca huérfanas, idempotente.
4. Seed de no-regresión: permisos por rol equivalentes a los actuales (admin = todo salvo sistema; editor = Users::index/view + Reports::users; user = Dashboard::index).
5. `App\Rbac\DatabasePermissionsProvider` (configurado en `Auth.RbacPolicy`): reglas de sistema del fichero + reglas de BD, en caché; test de no-regresión de las decisiones allow/deny de los 4 roles.
6. `UserRoleManager` lee los roles de BD (lista blanca dinámica); `superadmin` sigue sincronizado con `is_superuser`.
7. UI (solo superadmin): CRUD de roles + matriz controlador/acción × rol; invalidación de caché al guardar; guards (no quitarse acceso a la pantalla de permisos, `superadmin` no editable, no borrar un rol con usuarios asignados).
8. QA UI/UX + roast rápido de seguridad.

**Hecho:** pasos 1–8 completos, 120 tests verdes + phpcs + phpstan nivel 8.
- Tablas `roles`/`permissions`/`roles_permissions` (migración `20261118130000`), `RolesTable` con reglas (protegido, en uso, slug).
- `App\Rbac\ActionDiscovery` (criterio de `Controller::isAction()` + `App.permissions.ignoredActions`), `PermissionsSynchronizer`, `bin/cake permissions sync [--reset]`.
- `App\Rbac\DatabasePermissionsProvider` (extiende el `ConfigProvider` de CakeDC) en `Auth.RbacPolicy`; caché `PermissionsCache` invalidada en cada cambio. Test de no regresión: 18 decisiones allow/deny idénticas al fichero anterior.
- `config/permissions.php` = solo reglas de sistema (públicas, superadmin, perfil/logout, dashboard, Pages, DebugKit).
- UI superadmin: `/roles` (CRUD) y `/permissions` (matriz con filas bloqueadas: Public / All roles / Superadmin only) + botón "Sync actions"; sidebar "Access control".
- QA/roast: (1) filas de sistema se mostraban editables sin efecto → bloqueadas; (2) **escalada**: un admin podía recibir `Permissions::*`/`Roles::*` desde la matriz y auto-concederse todo → `App.permissions.superadminOnly`, filtrado en `PermissionsSynchronizer` (test); (3) acción desconocida vía rutas fallback ahora se deniega por RBAC (lista blanca) en vez de 404.

### Fase 10 — Auditoría de permisos (TDD) ✅ (2026-11-18)
- Tabla `permission_audits` (migración `20261118140000`): user_id, username, event, role_slug, added/removed/details (JSON), created. Append-only.
- `App\Rbac\PermissionAudit::record()`; eventos `grants_changed` (solo roles con diff real), `role_created`, `role_updated` (campos `[antes, después]`), `role_deleted` (solo si se borra), `sync` (conteos + `reset`; actor `console` desde `bin/cake`).
- `PermissionsSynchronizer::setGrants()` devuelve `{added, removed}`.
- Pantalla `/permissions/audit` (superadmin, paginada, + concedido / − revocado); sidebar *Access control › History*.
- Bug encontrado en QA: guardar la matriz revocaba las filas bloqueadas (el navegador no las envía) → ahora se conservan (test).
- Purga ✅: `bin/cake permissions purge [--days N] [--dry-run]`, retención `App.permissions.auditRetentionDays` (365, mínimo 30 → error), registra `audit_purged` solo si borra algo (cron diario sin ruido). Lógica en `PermissionAudit::purge()/countOlderThan()`.

### Fase 11 — Refactor DDD-lite (TDD) ✅ (2026-11-18)
Objetivo: controladores y comandos finos; la orquestación en casos de uso inyectados por el contenedor DI de CakePHP 5 (sin paquetes nuevos). Sin DDD completo (agregados/modelo separado del ORM): el admin es mayormente CRUD.
- Capas: `src/UseCase/` (aplicación: orquesta, transacción, auditoría, caché) → reglas de dominio puras ya existentes (`UserRoleManager`, `PermissionsSynchronizer`, `ActionDiscovery`) → Tables como repositorios (infraestructura). Namespace `App\UseCase` (no `App\Application`, choca con la clase `App\Application`).
- Pasos:
  1. Contenedor: `Application::services()` con `ReflectionContainer` (autowiring) + factorías `fromConfig()` de los servicios configurables. Value object `Actor` (usuario o consola).
  2. Permisos: `PermissionMatrix` (read model: grupos + filas bloqueadas, saca `matchesSystemRule` del controlador), `UpdateRolePermissions`, `SyncPermissions`.
  3. Roles: `ManageRoles` (crear/editar/borrar + caché + auditoría).
  4. Histórico: `PermissionAuditLog` (instancia inyectable en vez de estáticos) + `PurgePermissionHistory`; comandos con inyección por constructor.
  5. Test de arquitectura (sin StructArmed): controladores/comandos no llaman a `PermissionAudit`, `PermissionsCache` ni `::fromConfig()`.
  6. Suite completa verde sin cambiar comportamiento (los tests de integración existentes son la red de seguridad).
- **Hecho:** 148 tests verdes + phpcs + phpstan 8.
  - `App\UseCase\Actor` (VO usuario/consola), `Permissions\{PermissionMatrix, UpdateRolePermissions (transacción), SyncPermissions, PurgePermissionHistory}`, `Roles\{ManageRoles, RoleResult}`, `Users\ChangeUserRole`.
  - `App\Rbac\PermissionAuditLog` (instancia, repositorio de `permission_audits`) sustituye al estático `PermissionAudit`.
  - `PermissionMatrix` devuelve códigos de bloqueo (`public`/`all_roles`/`superadmin`); la plantilla los traduce.
  - Contenedor: registros explícitos en `Application::services()` (sin `ReflectionContainer` global: autowirearía todos los controladores, también los de CakeDC). Inyección en acciones (`index(PermissionMatrix $matrix, …)`) y por constructor en comandos.
  - `AppController::actor()`; `ArchitectureTest` bloquea en controladores/comandos: `PermissionAudit*`, `PermissionsCache::`, `::fromConfig(` y escrituras directas a tablas de auditoría/permisos. Excepción documentada: `AppController` (guards en `beforeFilter` de los controladores de CakeDC, sin inyección).

### Fase 12 — Producción ✅ (2026-11-18)
- Decisiones del usuario: Tailwind compilado en etapa **Node de Docker** (npm `tailwindcss`, `@tailwindcss/cli`, `basecoat-css`, solo build) y **PHP-FPM en vez de FrankenPHP** (sin worker mode: CakePHP no tiene soporte oficial y el estado estático se filtraría entre peticiones).
- `docker/app/Dockerfile` multi-stage: `base` (php:8.5-fpm + extensiones), `dev` (composer/git, bind mount), `vendor` (`--no-dev`, autoload classmap-authoritative), `assets` (node:22 → `webroot/css/app.css` + `webroot/js/basecoat`), `prod` (opcache `validate_timestamps=0`, `www-data`, `plugin assets copy`), `web` (nginx + estáticos).
- `docker/app/fpm-pool.conf` (`clear_env = no`: sin esto PHP-FPM no ve `DATABASE_URL`/`SECURITY_SALT`), `php-dev.ini` / `php-prod.ini` (errores a stderr, cookies `secure`/`httponly`/`SameSite=Lax`), `docker/nginx/default.conf`.
- `compose.yaml` (dev): servicios `web` (nginx :8080) + `app` (FPM). `compose.prod.yaml`: falla si faltan `SECURITY_SALT` / `APP_FULL_BASE_URL`.
- Config: `Datasources.default.url = env('DATABASE_URL')` en `app.php`; `config/app_local.php` fuera de la imagen (`.dockerignore`); `DebugKit` opcional (no existe con `--no-dev`); `BasecoatTheme.compiledAssets` (`THEME_COMPILED_ASSETS`) → `AppView::themeConfig()` (`css: app`, sin Tailwind browser, JS propia).
- Verificado: 148 tests en el contenedor FPM; stack prod en :8081 migrado, sync (41 acciones), login superadmin, matriz y dashboard (gráfica) sin errores de consola; `app.css` 235 KB sin comprimir (gzip en nginx).
- Autoalojado (2026-11-18, aprobado por el usuario): npm `chart.js@4.4.7` (misma versión que el CDN) → `webroot/js/vendor/chart.umd.js`; `@fontsource-variable/geist@5.3.0` (OFL) → `webroot/font/geist/` (subconjuntos latin + latin-ext, `@font-face` en `resources/css/app.css`); `AppView::themeConfig()` con `font: false` y `chartJs` local. Producción ya no hace ninguna petición externa (verificado: solo el propio origen).
- Cache busting: `Asset.timestamp = env('ASSET_TIMESTAMP', true)`, `force` en la imagen prod → `app.css?<mtime>`. Encontrado en QA: con `Expires` de nginx el navegador seguía usando el `app.css` viejo tras un deploy. nginx cachea los estáticos 7 días.
- Logs a stderr (2026-11-18): `LOG_TO_STDERR=true` en la imagen prod → los loggers `debug`/`error`/`queries` de `config/app.php` usan `ConsoleLog` (`php://stderr`, sin colores) en vez de `FileLog`. Verificado: un 404 aparece en `docker compose logs app` y `logs/` queda vacío. Dev sigue con ficheros.
- TLS (2026-11-18, revisado a petición del usuario): flujo de filae con **mkcert** — `bin/dev-https-cert` genera `docker/certs/admindashboard.localhost{,-key}.pem` (fuera de git), nginx sirve `https://admindashboard.localhost` en 443 (sin puerto en la URL) solo si existe el certificado (`docker/nginx/https.conf` + `10-enable-https.sh`), en dev y en el stack prod. HSTS y cabeceras de seguridad en nginx. Caddy eliminado. Convivencia con filae (decisión del usuario: proxy compartido): `~/code/local-https-proxy` (nginx stream, `ssl_preread`, passthrough por SNI) en 443/80 → `admindashboard.localhost` :8445 (`HTTPS_PORT`), `filae.localhost` :8444 (`APP_HTTPS_PORT=8444` en el `.env` de filae; sin tocar su código). Verificado: los dos dominios sin puerto a la vez, certificados válidos, 80 → 301 HTTPS.
- Notas: en prod usar `migrations migrate --no-lock` (el código es de solo lectura para `www-data`).

### Fase 13 — 2FA (TOTP + llave) y passkeys (TDD) ✅ (2026-11-18)
Referencia: implementación de filae (`../filae`, specs `openspec/specs/{two-factor-auth,passkey-login}`). Paquetes aprobados: fork `delamux/CakeDC-users` rama `issue/4-webauthn-lib-5` (alias 16.0.5; webauthn-lib 5), `robthree/twofactorauth` ^3, `web-auth/webauthn-lib` ^5.3.5, `endroid/qr-code` ^6.2.
- Reglas: 2FA opcional para todos los roles; métodos TOTP y/o llave (independientes); con TOTP activo se pide el código; la llave solo si es el único método; passkey = login sin contraseña con la misma llave. Activar/desactivar con la contraseña actual; email en cada cambio (sin secretos). Sin códigos de recuperación: el superadmin resetea a otros (no a otros superadmins) y `bin/cake two_factor reset` para el propio superadmin. `TWO_FACTOR_ENABLED` (por defecto activo). El enlace de acceso por email también pasa por 2FA.
- Diferencias con filae: sin prefijos (pantalla de perfil de CakeDC), casos de uso en `src/UseCase/TwoFactor` (DDD-lite), JS sin build (`webroot/js/webauthn.js`), HTTPS en dev con Caddy (`https://admindashboard.localhost:8443`; WebAuthn lo exige).
- Pasos: 1) checkers opcionales + métodos en `UsersTable` · 2) activar/desactivar en el perfil (TOTP con QR, llave) + emails · 3) login con 2FA (verify, llave) · 4) passkeys · 5) reset por superadmin + comando · 6) limitación por IP · 7) HTTPS dev + QA.
- **Hecho** (191 tests + phpcs + phpstan 8):
  - Datos: sin migraciones (CakeDC ya trae `secret`, `secret_verified`, `additional_data`). `App\Model\Entity\User` protege de asignación masiva `secret`, `secret_verified` y `additional_data` (la entidad de CakeDC lo permitía: se podía plantar un secreto TOTP o una llave desde un formulario).
  - `UsersTable`: `twoFactorMethods`, `enableTotp`, `disableTotp`, `disableWebauthn`, `disableTwoFactor`, `markPasskey`, `isLegacyWebauthnKey`, `findPasskeyOwner`, `recordLogin`. Checkers `App\Authentication\Optional*Checker`.
  - Caso de uso `App\UseCase\TwoFactor\TwoFactorSettings` (contraseña, TOTP/QR, confirmar, llave registrada, desactivar, reset, email `twoFactorChanged`). `TwoFactorController` (pasos POST, 10 min en sesión, reset superadmin con defensa en profundidad), celda `TwoFactor` (tarjeta del perfil y estado en la ficha de usuario).
  - Login: plantillas `verify` / `webauthn2fa`, `WebauthnLoginController` (adaptador propio: CakeDC lo crea dentro de su acción). Passkeys: `App\Webauthn\PasskeyLoginAdapter` (reto de un solo uso, 5 min, sin `allowCredentials`, `userHandle`), `PasskeyLoginController` (activa, bloqueo si está configurado, recordarme, solo redirecciones internas), rutas `/passkey/{options,login}`. `StandardAlgorithmsTrait` evita el aviso Ed256/Ed512 que rompía el JSON.
  - JS sin build `webroot/js/webauthn.js` (registro, segundo paso, passkey con autorrelleno condicional; CSRF por cabecera, base64url).
  - `bin/cake two_factor reset <usuario|email>`. `TwoFactor::reset` en `PermissionsSynchronizer::SUPERADMIN_ONLY` (fuente única).
  - Límite por IP (`App\Http\AuthThrottle`, `App\Middleware\AuthThrottleMiddleware`, caché `throttle`, `Security.throttle`): accesos fallidos + códigos erróneos + passkeys fallidas 20/15 min; registro/reset/reenvío 5/h; 429 + `Retry-After`. En tests, caché en memoria.
  - Cookie «recordarme» `secure` (si la app es HTTPS) y `SameSite=Lax`. El enlace de acceso por email también pide 2FA.
  - Tests con firmas reales (EC P-256, `WebauthnTestTrait`): segundo paso con llave y passkey de extremo a extremo, reto de un solo uso/caducado, contador creciente (anti-clonado), llaves antiguas, sin verificación de usuario, cuentas inactivas.
  - QA (https://admindashboard.localhost): activar TOTP con QR, email, acceso con código, estado y reset desde la ficha de un usuario, email de reset; traducciones es_ES (57 cadenas nuevas).
- Reutilización de códigos TOTP cerrada (2026-11-18, TDD): columna `users.totp_last_step` (migración `20261118160000`), `UsersTable::claimTotpStep()` (un único `UPDATE ... WHERE totp_last_step IS NULL OR < :step`: atómico, sin carreras), `TwoFactorSettings::verifyLoginCode()` y `App\Controller\Component\OneTimePasswordAuthenticatorComponent` (sustituye al de CakeDC en `AppController::beforeFilter()`). El código de activación cuenta como usado. Verificado en la app: código de activación rechazado, código nuevo entra, el mismo otra vez rechazado.
- Limitaciones conocidas: el registro/uso real de llaves y passkeys necesita un dispositivo (Touch ID, llave, gestor de contraseñas): cubierto por tests con firmas reales, no probado a mano. (Proxies de confianza: resuelto en la Fase 15 con `TRUSTED_PROXIES`.)

### Fase 14 — CI con GitHub Actions ✅ (2026-11-18)
- `.github/workflows/ci.yml` (sustituye al del esqueleto de CakePHP): en push a `main`, pull requests y a mano. Un job en `ubuntu-24.04` con PHP 8.5 (mismas extensiones que la imagen) y PostgreSQL 18 como servicio: `composer install` → `config/app_local.example.php` como config local → **PHPUnit**, **CodeSniffer** (anotaciones en el PR con `cs2pr`), **PHPStan** nivel 8 (formato `github`) y **`composer audit`**. Cada paso de calidad corre aunque falle el anterior, para verlo todo de una vez.
- Decisiones del usuario: solo tests y análisis (sin construir imágenes Docker) y sin pasos de migraciones (el esquema de tests lo crea el bootstrap de la suite).
- Verificado reproduciendo el job en local desde cero (copia limpia sin `vendor/` ni `app_local.php`, PostgreSQL vacío): 194 tests, phpcs, phpstan y audit en verde.
- Para que corra: crear el repositorio git y subirlo a GitHub (el proyecto aún no es un repo). Dependabot ya actualiza Composer y las actions cada semana.

### Fase 15 — Seguridad (book.cakephp.org/5.x/security) ✅ (2026-11-18)
Decisiones del usuario: CSP estricta en producción y en modo Report-Only en dev; tema sin JS en línea (sin opción de config) publicado directo a main; paquetes aprobados `paragonie/csp-builder` (CspMiddleware) y `htmx.org` (npm).
- Pasos: 1) tema sin `onclick`/`<script>` en línea (delegación de eventos + `Html->scriptBlock` con nonce) · 2) `SecurityHeadersMiddleware` · 3) `HttpsEnforcerMiddleware` + HSTS (producción) · 4) `CspMiddleware` con nonces (report-only con debug) · 5) CSRF `secure` + `SameSite` · 6) `FormProtection` en los controladores de la app · 7) `EncryptedCookieMiddleware` para «recordarme» · 8) sesión endurecida · 9) proxies de confianza (IP real tras balanceador).
- **Hecho** (`SecurityTest`, 208 tests + phpcs + phpstan 8):
  - Tema (directo a main, `c3842c0` + `fabdb9d`): sidebar, diálogos, alertas, toasts, barras de progreso y «Volver» con `data-*` + delegación en `basecoat-theme.js`; nonces en el script de modo oscuro y el `<style>` de Tailwind; reinicio tras `htmx:load`.
  - App: `App\Http\SecurityMiddlewares` (fábrica) y `App\Middleware\TrustedProxiesMiddleware`; config `Security.{https,csp,cookieKey,trustedProxies}` y `Session.ini`; nginx ya no duplica cabeceras.
  - Plantillas sin JS en línea: `data-confirm` y `data-submit-form` (`webroot/js/app.js`); fuera las plantillas flash del esqueleto (las del tema mandan) y la tarjeta antigua «Reset authenticator» de CakeDC (duplicaba el reset de la Fase 13).
  - QA en el stack de producción con la CSP aplicada: sin errores de consola en login, dashboard, usuarios, permisos y perfil (2FA). Encontrado por QA: `style="width"` de `ProgressHelper` y `javascript:history.back()` del layout de error (bloqueados por la CSP) → corregidos en el tema.
  - Tests: `configRequest()` mezcla recursivamente y `app.php` se carga una vez por proceso (en tests se usa `Configure::write`); el listener de accesos fallidos se registraba en cada bootstrap → cierre estático.
- Sesiones en PostgreSQL (2026-11-18, decisión del usuario): migración `20261118170000_CreateSessions` (`id` varchar 128 PK, `data` bytea, `expires` int con índice, `created`/`modified`), `Session.defaults = database` en todos los entornos (la endurecida `Session.ini` y el `timeout` se mantienen). Verificado: la sesión queda en `sessions` y sigue abierta tras recrear el contenedor `app`. El GC de PHP borra las caducadas (`expires < now`).
- Contadores del límite en PostgreSQL (2026-11-18, decisión del usuario): migración `20261118180000_CreateAuthThrottles` (PK `bucket` + `ip_hash` SHA-256, `hits`, `window_ends` con índice); `App\Http\AuthThrottle` hace un único `INSERT … ON CONFLICT DO UPDATE` atómico (reinicia la ventana caducada; sin conteos perdidos con peticiones simultáneas) y purga las filas caducadas de vez en cuando (`purgeExpired()`). Fuera la caché `throttle`. `AuthThrottlesTable` existe para el ORM (fixtures; sin clases de tabla de reserva). Tests: el bootstrap vacía la tabla al empezar; los que cuentan usan el fixture.

### Fase 16 — htmx ✅ (2026-11-18)
- Integración propia inspirada en `zunnu/cake-htmx` (sin el plugin, no mantenido): componente `Htmx` (detección `HX-Request`, render parcial, `HX-Trigger`, `HX-Redirect`), htmx 2.0.11 autoalojado en producción, CSRF por cabecera, compatible con la CSP (`allowEval: false`, sin estilos en línea). Aplicado a: tarjeta de 2FA, listado de usuarios (búsqueda/orden/paginación) y matriz de permisos.
- **Hecho** (`HtmxTest`): layout `htmx` con flash como toasts fuera de banda (`hx-swap-oob` al toaster: se ven aunque `hx-select` tome otro elemento), `Vary: HX-Request`, peticiones `hx-boost` con `hx-target` también parciales, `HX-Redirect` cuando una petición htmx necesita login, `htmx-config` con el nonce de la página, `webauthn.js` reenlazado tras `htmx:load`. Verificado en producción (CSP estricta): ordenar usuarios sin recargar (URL en el historial), guardar permisos con toast, paso de 2FA cambiando solo la tarjeta.
- README reescrito con todo el stack, seguridad y htmx.

### Fase 17 — Registro de actividad de la cuenta (TDD) ✅ (2026-11-18)
- Tabla `account_activities` (id autoincremental = orden de inserción, `user_id` FK con CASCADE, `actor_id` FK SET NULL, `event`, `ip`, `user_agent` ≤255, `created`). Decisiones con el usuario: **IP completa**, **los fallos con usuario inexistente no se guardan** (los cubre el throttle; nunca se guarda lo tecleado), **perfil + ficha de usuario** (solo superadmin), **retención 180 días** (`App.accountActivity.retentionDays`, `bin/cake activity purge`, mínimo 30).
- Eventos: `login`, `passkey_login`, `login_failed`, `two_factor_failed` (código o llave incorrectos tras la contraseña correcta), `logout`, `password_changed` / `password_reset` (por token), `totp_enabled` / `totp_disabled`, `key_added` / `key_removed`, `two_factor_reset` (con quién lo hizo).
- Dónde: `App\Account\AccountActivityListener` para los eventos de CakeDC (sustituye al closure estático de los fallos: un solo listener, registrado una vez por EventManager con un `WeakMap`), `TwoFactorSettings` para el 2FA, `PasskeyLoginController` y `WebauthnLoginController`. Un fallo al escribir va al log y no rompe la acción.
- UI: cell `AccountActivity` (últimos 20, fallos en rojo, «Chrome en macOS» con `App\Account\UserAgent`, IP, «por X» si actuó otro). Traducido al español. Probado en el navegador.
- Tests: `AccountActivityTest`, `UserAgentTest`, aserción en `PasskeyLoginTest`. 226 tests verdes, phpcs y phpstan en verde.
- AuditStash (`lorenzo/audit-stash`) se descartó para esto (audita cambios de registros del ORM, no inicios/fallos/cierres de sesión); queda como opción para auditar entidades de negocio.

### Fase 18 — Cola (dereuromark/cakephp-queue) ⏳
- Decisiones (2026-11-18): AuditStash **descartado** por ahora (no hay entidades de negocio que auditar; usuarios y roles ya los cubren la actividad de la cuenta y `permission_audits`). Queue sí:
  - **Todos los emails** por la cola (transporte `queue` delante del real; CakeDC, 2FA, avisos).
  - **Worker como servicio Docker** propio (`bin/cake queue run`, reinicio automático), en dev, prod y Coolify.
  - **Pantalla propia** con Basecoat (la del plugin usa Bootstrap por CDN, incompatible con la CSP): contadores, fallidos con su error, reintentar/borrar, workers. Solo superadmin.
  - Fallos: pantalla + log de errores (stderr). Sin emails de aviso.
  - PDF de usuarios sigue síncrono.
  - **`bin/cake maintenance`**: un comando para el cron diario (purga de actividad, de histórico de permisos, limpieza de la cola).
- Puertos locales sin cambios (dev). En producción no hay Mailpit.

### Fase 19 — Despliegue en Coolify ⏳
- Compose propio para Coolify: PostgreSQL como **recurso de BD de Coolify** (copias S3; la app recibe `DATABASE_URL`), **nginx solo HTTP** detrás de Traefik (TLS de Coolify, `TRUSTED_PROXIES`, HSTS desde la app), imagen **construida por Coolify desde el repo**, cron con **Scheduled Tasks** de Coolify (`bin/cake maintenance`).

### Fase 20 — Documentación (`docs/`) ⏳
- Guía por área con todo el potencial de la app; README como resumen e índice; crons a ejecutar siempre documentados.

## Pendiente / siguientes pasos

- [x] **Mejoras genéricas del tema basecoat** (2026-11-18, aprobado). Clon en `../cakephp-basecoat-theme`,
  rama `feat/admin-improvements`, TDD (83 tests verdes):
  - TableHelper: indicador de orden (`chevrons-up-down` / `arrow-up` / `arrow-down`) + `aria-sort` en la
    columna activa; config `sortIcons` (false = enlaces planos) y `classes.sort*`.
  - ButtonHelper: opción `collapse` (`sm|md|lg|xl|true`) → icon-only bajo el breakpoint, label sr-only + `title`.
  - Layout: título `flex-1` truncable y acciones `shrink-0`.
  - FormHelper: `nestedCheckboxAndRadio` = false por defecto (checkbox plano, sin "choice card");
    `nestedInput => true` para la tarjeta. `checkboxWrapper`/`radioWrapper` con `{{input}}{{label}}`
    (arregla radios/multicheckbox sin anidar).
  - README del tema actualizado.
  - [x] Publicado directo a `main` del tema (commit `419a5a5`) y la app actualizada con
    `composer update delamux/cakephp-basecoat-theme` (lock en `419a5a5`).
- [x] **Gestión de roles desde el CRUD** (2026-11-18, TDD + `/grill:roast` rápido previo):
  - `App\Service\UserRoleManager`: roles en lista blanca (`App.roles` en `config/users.php`), solo admins/superusers,
    nadie cambia su propio rol (siempre queda ≥1 admin: el que actúa), solo superusers gestionan superusers,
    nadie borra su propia cuenta, "cambiar contraseña de otro" igual que CakeDC (`Users.Superuser.allowedToChangePasswords`).
  - `POST /user-roles/{id}` (`UserRolesController`, ruta `user-roles:edit`, id uuid → 404 si no lo es); `is_superuser` nunca desde la UI.
  - Guards en `AppController::beforeFilter` para `edit`/`delete` del CRUD de CakeDC (aplican también a superusers,
    que se saltan el RBAC por la política `Rbac|Superuser`).
  - UI: tarjeta "Role" en edición; editar/borrar/cambiar contraseña solo si está permitido (listado, ficha, edición).
  - Hallazgos del roast cerrados: (1) admin podía cambiar el email de un superuser → takeover vía reset; (2) borrarse a sí
    mismo / borrar superusers; (3) enlace "Change password" de otros roto.
  - QA cazó un bug no cubierto: ruta con `setMethods` no encontrada en reverse routing → test de URL + 404 para ids inválidos.
- [x] Mensaje de sesión caducada: `App\Middleware\UnauthorizedHandler\AppRedirectHandler` → sin sesión "Please sign in to continue.", con sesión y sin permiso "Access denied".
- [x] **Roles por defecto `superadmin`, `admin`, `editor`, `user`** (2026-11-18, TDD):
  | Rol | Puede |
  | --- | --- |
  | superadmin | Todo; único que gestiona superadmins y concede/quita superadmin. Sincronizado con `is_superuser` de CakeDC |
  | admin | Usuarios y roles (no superadmins, no concede superadmin), informes |
  | editor | Solo lectura: listado/ficha de usuarios, informe PDF, estadísticas del dashboard |
  | user | Dashboard y su perfil |
  - `App\Model\Table\UsersTable` (vía `Users.table = 'Users'`, CakeDC mapea sus alias) sincroniza rol ⇄ flag en `beforeSave` (cubre CRUD, cambio de rol y `bin/cake users add_superuser`).
  - Migración `20261118120000_SuperadminRole`: superusers existentes → rol `superadmin`.
  - RBAC (`config/permissions.php`): superadmin/admin todo; editor `Users::index/view` + `Reports::users`.
  - UI: selector de rol con `assignableRoles()` (admin no ve "Superadmin"), badge de rol unificado (`element/Users/role_badge`), acciones solo si están permitidas.
- [x] **Roast rápido sobre los roles**: la sesión guardaba la entidad completa → un admin degradado/borrado/desactivado conservaba acceso hasta cerrar sesión. Ahora `AppController::refreshIdentity()` relee el usuario en cada request (1 query por PK): refresca la identidad o cierra la sesión ("Your session has ended"). Cerrado después con `App\Middleware\RefreshIdentityMiddleware` (vía `Users.middlewareQueueLoader` → `App\Loader\MiddlewareQueueLoader`), que corre entre Authentication y Authorization: el RBAC ya ve el rol actual desde la primera request.
- [x] i18n español (2026-11-18): `LocaleSelectorMiddleware(['en_US','es_ES'])` elige por `Accept-Language` (por defecto `App.defaultLocale`). `resources/locales/default.pot` (227 cadenas, `bin/cake i18n extract --paths src,templates --output resources/locales`) + `es_ES/default.po` y `es_ES/cake.po`. CakeDC/Users y el tema traen su propio `es`. Al añadir cadenas: re-extraer y completar el `.po` (luego `bin/cake cache clear_all`).
- [x] Producción (2026-11-18): ver Fase 12.
- [x] `composer self-update` en host (2026-11-18, hecho por el usuario): Composer 2.11.2, `composer diagnose` OK con PHP 8.5.12.

## Testing (TDD)

- **TDD solo para funcionalidad propia / reglas de negocio** (rojo → verde → refactor).
- **No** testear templates ni lo ya testeado por framework/plugins.
- Cubierto: `DashboardStatsService`, `SidebarMenu`, `CssInliner`, `UsersMailer` (inline + multipart + layout), reglas de acceso + PDF (`AccessRulesTest`).
- `docker compose exec app vendor/bin/phpunit` (BD `cakephp_admin_test` en PG18).
- Fixture `UsersFixture` usa `tableAlias = 'CakeDC/Users.Users'` porque la app desactiva las Table fallback.

## Decisiones / notas

- CakeDC/Users carga Authentication + Authorization por sí mismo. Política `Rbac|Superuser`.
- Las plantillas CakeDC llevan prefijo de plugin → el layout de email se resuelve en el plugin primero; `templates/plugin/CakeDC/Users/layout/email/*` incluyen el layout de la app (una sola fuente).
- El inline de CSS se hace en `render()` antes de `Message::setBody()` para que el wrap de líneas no parta atributos/URLs.
- El skeleton `PagesControllerTest` y `templates/Pages/home.php` (bienvenida de CakePHP) se eliminaron: `/` es el dashboard y todo requiere login.

## Registro

- 2026-11-18: Fases 0–8 completadas. Tests 26/26, phpcs y phpstan nivel 8 en verde.
