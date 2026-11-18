# CakePHP Admin Skeleton

Esqueleto de panel de administración para arrancar proyectos con CakePHP: login completo, usuarios, roles y permisos
gestionados desde la propia UI, verificación en dos pasos y passkeys, dashboard, emails y PDF, seguridad de
[book.cakephp.org/5.x/security](https://book.cakephp.org/5.x/security.html) aplicada, htmx, i18n y Docker para dev y
producción.

Plan, decisiones y estado de cada fase: [ROADMAP.md](ROADMAP.md).

## Qué incluye

- **Cuentas** (CakeDC/Users): login, registro con validación por email, reset de contraseña, enlace mágico de acceso,
  perfil, CRUD de usuarios, «recordarme».
- **Roles y permisos en base de datos**: roles `superadmin`, `admin`, `editor`, `user` (y los que crees), matriz
  acción × rol con descubrimiento automático de controladores, acciones solo-superadmin bloqueadas e **histórico de
  cambios** con purga por retención.
- **Actividad de la cuenta**: inicios de sesión (también fallidos), cierres, cambios de contraseña y de 2FA, con IP y
  navegador; cada usuario ve la suya en el perfil y el superadmin la de cualquiera. Purga a los 180 días.
- **Verificación en dos pasos** opcional: app de autenticación (TOTP + QR) y/o llave de seguridad; **passkeys** para
  entrar sin contraseña; avisos por email; restablecimiento por superadmin o por consola; códigos de un solo uso.
- **Dashboard** con KPIs, gráfico de altas, últimos usuarios y equipo; informe de usuarios en **PDF**.
- **Emails** HTML de alta compatibilidad (tablas, CSS inline con Emogrifier, dark mode, versión texto).
- **htmx**: listados, matriz de permisos y tarjeta de 2FA sin recargar, compatible con la CSP.
- **Seguridad**: CSP estricta con nonces, cabeceras de seguridad, HTTPS + HSTS, CSRF y FormProtection, cookie
  «recordarme» cifrada, sesión endurecida, límite de intentos por IP, proxies de confianza.
- **i18n**: inglés y español (según el navegador).
- **Calidad**: TDD (≈225 tests), PHPStan nivel 8, CakePHP CodeSniffer, `composer audit`, CI en GitHub Actions.

## Stack

| Capa | Tecnología | Versión | Por qué |
| --- | --- | --- | --- |
| Lenguaje | PHP | 8.5 | Requisito del proyecto |
| Framework | CakePHP | 5.4 | Convenciones, ORM, DI container, middlewares de seguridad |
| Usuarios | CakeDC/Users (fork `delamux/CakeDC-users`, rama `issue/4-webauthn-lib-5`) | 16.0.5 + webauthn-lib 5 | Login, registro, 2FA; el fork lo hace compatible con webauthn-lib 5 / PHP 8.5 |
| Autorización | CakeDC/Auth RBAC + proveedor propio en BD | 10.1 | Reglas por rol editables en la UI |
| 2FA / passkeys | robthree/twofactorauth · web-auth/webauthn-lib · endroid/qr-code | 3 · 5.3 · 6.2 | TOTP, WebAuthn, QR en servidor |
| UI | [delamux/cakephp-basecoat-theme](https://github.com/delamux/cakephp-basecoat-theme) (Basecoat + Tailwind v4) | main | Componentes estilo shadcn, light/dark, sin JS en línea |
| Interacción | htmx | 2.0.11 | Fragmentos sin recargar, sin build ni framework JS |
| CSP | paragonie/csp-builder (CspMiddleware de CakePHP) | 3.1 | Content-Security-Policy con nonces |
| PDF | friendsofcake/cakepdf + Dompdf | — | PDF en PHP puro, sin binarios |
| Emails | pelago/emogrifier | — | CSS inline para clientes de correo |
| Base de datos | PostgreSQL | 18 | |
| Servidor | nginx + PHP-FPM | 1.29 · 8.5 | Mismo Dockerfile multi-stage para dev y producción |
| Correo en dev | Mailpit | — | Bandeja local en http://localhost:8026 |
| HTTPS local | mkcert + proxy SNI compartido | — | `https://admindashboard.localhost` sin puerto (WebAuthn exige HTTPS) |
| Assets en producción | Tailwind CLI + basecoat-css, Chart.js, htmx, Geist (etapa Node de Docker) | — | Todo autoalojado: ninguna petición a CDNs |
| Calidad | PHPUnit 13 · PHPStan 2 (nivel 8) · CakePHP CodeSniffer | — | `composer check`; CI en GitHub Actions |

## Arquitectura

DDD-lite: controladores y comandos finos; la orquestación (auditoría, caché, transacciones, emails) vive en **casos de
uso** de `src/UseCase` inyectados con el contenedor DI de CakePHP (`Application::services()`), las reglas de dominio en
clases puras (`UserRoleManager`, `PermissionsSynchronizer`, `ActionDiscovery`) y las Tables hacen de repositorios.
`ArchitectureTest` impide que un controlador o comando orqueste por su cuenta.

```
src/
├── UseCase/            # casos de uso: Permissions, Roles, TwoFactor, Users (+ Actor)
├── Rbac/               # descubrimiento de acciones, sincronización, proveedor RBAC en BD, auditoría
├── Service/            # reglas de roles, estadísticas del dashboard
├── Authentication/     # checkers de 2FA opcional
├── Webauthn/           # adaptadores WebAuthn (registro, segundo paso, passkey)
├── Http/               # SecurityMiddlewares (CSP, cabeceras, HTTPS, CSRF, cookies), AuthThrottle
├── Middleware/         # límite por IP, proxies de confianza, refresco de identidad
├── Controller/         # finos; Component/Htmx y OneTimePasswordAuthenticator propio
└── Model/              # UsersTable (2FA, sincronía superadmin), Roles, Permissions, auditoría
webroot/js/             # app.js (confirmaciones, CSRF de htmx) y webauthn.js, sin build
```

## Arranque (desarrollo)

```bash
# 1. HTTPS local (una vez por equipo; WebAuthn/passkeys lo exigen)
brew install mkcert && mkcert -install
bin/dev-https-cert

# 2. Servicios y base de datos
docker compose up -d --build
docker compose exec app composer install
docker compose exec app bin/cake migrations migrate -p CakeDC/Users
docker compose exec app bin/cake migrations migrate
docker compose exec app bin/cake permissions sync
docker compose exec app bin/cake users add_superuser -u admin -p 'Admin1234!' -e admin@cakephp-admin.test
docker compose exec app bin/cake seeds run DemoUsers     # opcional: 40 usuarios de demo (contraseña Demo1234!)
```

| Servicio | URL |
| --- | --- |
| App | https://admindashboard.localhost (vía el proxy compartido) · https://admindashboard.localhost:8445 · http://localhost:8080 |
| Mailpit | http://localhost:8026 |
| PostgreSQL | `localhost:5433` (cakephp / secret; BD de tests `cakephp_admin_test`) |

- En dev el código va montado en el contenedor y Tailwind se carga con su build de navegador: no hay que compilar nada.
- **HTTPS sin puerto junto a otros proyectos:** el proxy `~/code/local-https-proxy` (puertos 443/80, fuera del
  proyecto) reparte por dominio sin descifrar (SNI): `admindashboard.localhost` → 8445, `filae.localhost` → 8444.
  Arráncalo una vez con `docker compose up -d` en esa carpeta. Los `*.localhost` no necesitan `/etc/hosts`.
- El certificado dura ~2 años; se renueva con `bin/dev-https-cert`.
- Puertos configurables: `APP_PORT`, `HTTPS_PORT`, `MAILPIT_UI_PORT`, `MAILPIT_SMTP_PORT`, `DB_PORT`.

## Desarrollo diario

```bash
docker compose exec app composer check                    # PHPUnit + CodeSniffer + PHPStan nivel 8
docker compose exec app bin/cake permissions sync         # tras añadir controladores o acciones
docker compose exec app bin/cake permissions purge        # histórico de permisos (retención 365 días)
docker compose exec app bin/cake activity purge           # actividad de las cuentas (retención 180 días)
docker compose exec app bin/cake two_factor reset admin   # quitar el 2FA de una cuenta (p. ej. superadmin sin móvil)
```

- **TDD** para las reglas de negocio propias (no para plantillas ni para lo que ya prueba el framework).
- **CI** (`.github/workflows/ci.yml`): PHPUnit contra PostgreSQL 18, CodeSniffer (anotado en el PR), PHPStan y
  `composer audit` en cada push a `main` y en los pull requests. Dependabot actualiza Composer y las actions.
- **Traducciones:** al añadir textos, `bin/cake i18n extract --paths src,templates --output resources/locales --merge no
  --overwrite --extract-core no`, completar `resources/locales/es_ES/default.po` y `bin/cake cache clear_all`.

## Seguridad

Aplicado según [book.cakephp.org/5.x/security](https://book.cakephp.org/5.x/security.html) (`App\Http\SecurityMiddlewares`,
`config/app.php` → `Security.*`). `SecurityTest` lo comprueba sobre respuestas reales.

| Medida | Cómo |
| --- | --- |
| **Content-Security-Policy** | `CspMiddleware` con nonces de script y estilo; nada en línea (`'unsafe-inline'`/`'unsafe-eval'` fuera), `object-src 'none'`, `frame-ancestors`/`form-action`/`base-uri 'self'`. Estricta en producción; **Report-Only en dev** (el build de navegador de Tailwind inyecta estilos sin nonce). |
| Cabeceras | `SecurityHeadersMiddleware`: `nosniff`, `X-Frame-Options: sameorigin`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-Download-Options`, `Permissions-Policy` (cámara, micro, geolocalización… desactivados; WebAuthn permitido). |
| HTTPS + HSTS | `HttpsEnforcerMiddleware` con `HTTPS_ENFORCE=true` (imagen de producción): HTTP → HTTPS y `Strict-Transport-Security` de 1 año. |
| CSRF | `CsrfProtectionMiddleware`: cookie `httponly`, `SameSite=Lax` y `secure` en HTTPS; los `fetch`/htmx envían `X-CSRF-Token` desde `<meta name="csrf-token">`. |
| FormProtection | En todos los controladores: los formularios de `FormHelper` llevan un token de sus campos. Solo se desbloquean los endpoints JSON de WebAuthn y los checkboxes de la matriz. |
| Cookie «recordarme» | `EncryptedCookieMiddleware` (AES) con `COOKIE_KEY` (o derivada de la sal): ni legible ni falsificable; `secure` y `SameSite=Lax`. |
| Sesión | En **PostgreSQL** (tabla `sessions`); `httponly`, `SameSite=Lax`, `use_strict_mode`, `secure` en HTTPS, caducidad por inactividad (`SESSION_TIMEOUT`). |
| Límite de intentos | Por IP, en **PostgreSQL** (`auth_throttles`, IP con hash, upsert atómico): 20 accesos/códigos 2FA/passkeys fallidos cada 15 min; registro, reset y reenvío 5 por hora (429 + `Retry-After`). |
| Proxies de confianza | `TRUSTED_PROXIES`: solo desde ellos se acepta `X-Forwarded-For` (IP real para el límite y los logs). |
| Actividad de la cuenta | `account_activities`: accesos, fallos (solo de cuentas existentes, nunca lo tecleado), cierres, contraseña y 2FA, con IP y navegador. Visible en el perfil y, para el superadmin, en la ficha del usuario. Sin `TRUSTED_PROXIES` detrás de un proxy se ve la IP del proxy. |
| Cuentas | 2FA/passkeys, códigos TOTP de un solo uso, campos de 2FA no asignables desde formularios, solo un superadmin gestiona superadmins, histórico de cambios de permisos. |

**Cómo escribir plantillas compatibles con la CSP:** nada de `onclick`/`onsubmit`, `<script>` sin `Html->script()`,
`style="…"` ni `javascript:`. En su lugar:

- Scripts y estilos con `$this->Html->script()` / `->css()` / `->scriptBlock()`: llevan el nonce solos.
- Confirmación: `'data-confirm' => __('¿Seguro?')` en el botón o el formulario (lo gestiona `webroot/js/app.js`).
- Enviar un formulario desde un enlace: `'data-submit-form' => 'id-del-form'`.
- Componentes del tema (sidebar, diálogos, alertas, barras de progreso): atributos `data-*` del propio tema.

## htmx

htmx 2 (autoalojado en producción, CDN fijado en dev) con una integración propia inspirada en `zunnu/cake-htmx`
(`App\Controller\Component\HtmxComponent`, cargado en `AppController`):

- **Peticiones htmx** (`HX-Request`) se renderizan con el layout `htmx`: solo la plantilla, y los mensajes flash
  llegan como **toasts** fuera de banda (`hx-swap-oob` al toaster), aunque `hx-select` tome otro elemento. La
  navegación con `hx-boost` sin `hx-target` recibe la página completa. Siempre `Vary: HX-Request`.
- **CSRF** por cabecera (`app.js`), **FormProtection** intacto (los formularios siguen siendo de `FormHelper`) y
  **CSP**: `htmx-config` con `allowEval: false`, sin estilos inyectados y con el nonce de la página.
- Si una petición htmx necesita login, `AppRedirectHandler` responde con `HX-Redirect` (navegación completa).
- Helpers en el controlador: `$this->Htmx->isRequest()`, `->trigger('evento')`, `->redirect($url)`, `->refresh()`.

Patrón habitual: el formulario hace `hx-post`, el controlador redirige como siempre y htmx sigue la redirección y
cambia solo el fragmento:

```php
<?= $this->Form->create($entity, [
    'hx-post' => $this->Url->build(['action' => 'edit', $entity->id]),
    'hx-target' => '#mi-bloque',
    'hx-select' => '#mi-bloque',
    'hx-swap' => 'outerHTML',
]) ?>
```

En uso: listado de usuarios (orden, paginación y borrado con `hx-boost`), matriz de permisos (guardar con toast) y
tarjeta de verificación en dos pasos (cada paso cambia solo la tarjeta).

## Producción

Imágenes autocontenidas (`docker/app/Dockerfile`): `prod` (PHP-FPM, `composer --no-dev`, opcache sin revalidar,
usuario `www-data`) y `web` (nginx con los estáticos). El CSS se compila en una etapa Node (Tailwind v4 + basecoat-css)
y se autoalojan Basecoat JS, Chart.js, htmx y la fuente Geist; las URLs llevan `?<mtime>` para invalidar la caché en
cada deploy. `config/app_local.php` no entra en la imagen: todo va por variables de entorno.

```bash
bin/dev-https-cert                                  # local: certificado de mkcert (en real, el del dominio)
export SECURITY_SALT=$(openssl rand -hex 32) COOKIE_KEY=$(openssl rand -hex 32)
docker compose -f compose.prod.yaml up -d --build   # https://admindashboard.localhost (dev y prod, uno cada vez)
docker compose -f compose.prod.yaml exec app bin/cake migrations migrate -p CakeDC/Users --no-lock
docker compose -f compose.prod.yaml exec app bin/cake migrations migrate --no-lock
docker compose -f compose.prod.yaml exec app bin/cake permissions sync
docker compose -f compose.prod.yaml exec app bin/cake users add_superuser
```

| Variable | Por defecto | Uso |
| --- | --- | --- |
| `SECURITY_SALT` | — (obligatoria) | Sal de seguridad |
| `APP_FULL_BASE_URL` | `https://admindashboard.localhost` | URL pública (enlaces de emails, dominio de WebAuthn: las llaves quedan atadas a él) |
| `DATABASE_URL` | PostgreSQL del compose | `postgres://user:pass@host:5432/db` |
| `EMAIL_TRANSPORT_DEFAULT_URL`, `EMAIL_FROM` | Mailpit del compose | SMTP de salida |
| `COOKIE_KEY` | derivada de la sal | Clave AES de la cookie «recordarme» (≥ 32 caracteres) |
| `HTTPS_ENFORCE` | `true` en la imagen | HTTP → HTTPS + HSTS (`HSTS_MAX_AGE`, `HSTS_INCLUDE_SUBDOMAINS`) |
| `CSP_REPORT_ONLY` | `false` en la imagen (`DEBUG` en dev) | CSP en modo solo informe |
| `TRUSTED_PROXIES` | vacío | IPs del balanceador cuyo `X-Forwarded-For` se acepta (separadas por comas) |
| `SESSION_TIMEOUT` | `240` | Minutos de inactividad |
| `TWO_FACTOR_ENABLED` | `true` | Interruptor general del 2FA y las passkeys |
| `DEBUG` | `false` | Nunca `true` en producción |
| `THEME_COMPILED_ASSETS`, `ASSET_TIMESTAMP`, `LOG_TO_STDERR` | fijadas en la imagen | Assets propios, cache busting, logs a la salida del contenedor |
| `HTTPS_PORT` | `8445` | Puerto HTTPS que publica nginx |

En un despliegue real:
- Certificado del dominio en `docker/certs/` (o TLS en el balanceador + `TRUSTED_PROXIES`) y `APP_FULL_BASE_URL` fijo.
- **Sesiones y límite de intentos en PostgreSQL** (tablas `sessions` y `auth_throttles`): sobreviven a los redeploys
  y los comparten todas las réplicas; no hace falta Redis.
- Purga diaria en cron: `bin/cake permissions purge` y `bin/cake activity purge`.

## Dónde está cada cosa

| Qué | Dónde |
| --- | --- |
| Middlewares y servicios (DI) | `src/Application.php`, `src/Http/SecurityMiddlewares.php` |
| Config de CakeDC/Users, 2FA, roles | `config/users.php` |
| Reglas de sistema del RBAC | `config/permissions.php` (el resto, en BD: *Access control › Permissions*) |
| Seguridad (`Security.*`), sesión, caché | `config/app.php` |
| Casos de uso | `src/UseCase/` |
| Actividad de la cuenta | `src/Account/`, `src/View/Cell/AccountActivityCell.php`, `templates/cell/AccountActivity/` |
| Tema, layout y menú | `src/Controller/AppController.php`, `src/View/AppView.php`, `src/Navigation/SidebarMenu.php` |
| htmx | `src/Controller/Component/HtmxComponent.php`, `templates/layout/htmx.php`, `webroot/js/app.js` |
| WebAuthn / passkeys | `src/Webauthn/`, `src/Controller/{TwoFactor,WebauthnLogin,PasskeyLogin}Controller.php`, `webroot/js/webauthn.js` |
| Pantallas de usuarios (override) | `templates/plugin/CakeDC/Users/Users/` |
| Emails | `src/Mailer/`, `templates/layout/email/`, `templates/email/`, `templates/plugin/CakeDC/Users/email/` |
| PDF | `src/Controller/ReportsController.php`, `templates/Reports/pdf/` |
| Docker, nginx, certificados | `compose.yaml`, `compose.prod.yaml`, `docker/`, `bin/dev-https-cert` |
| Assets de producción | `package.json`, `resources/css/app.css` |
| Traducciones | `resources/locales/` |
