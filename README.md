# CakePHP Admin Skeleton

Admin panel skeleton for kick-starting CakePHP projects: full login, users, roles and permissions managed from the UI
itself, two-step verification and passkeys, dashboard, emails and PDF, the security guidance from
[book.cakephp.org/5.x/security](https://book.cakephp.org/5.x/security.html) applied, htmx, i18n and Docker for
development and production.

Plan, decisions and status of each phase: [ROADMAP.md](ROADMAP.md).

## What's included

- **Accounts** (CakeDC/Users): login, sign-up with email verification, password reset, magic sign-in link, profile,
  user CRUD, "remember me".
- **Database-backed roles and permissions**: `superadmin`, `admin`, `editor` and `user` roles (plus any you create),
  an action × role matrix with automatic controller discovery, locked superadmin-only actions and a **change history**
  with retention-based purging.
- **Account activity**: sign-ins (including failed ones), sign-outs, password and 2FA changes, with IP and browser;
  every user sees their own in their profile and the superadmin can see anyone's. Purged after 180 days.
- Optional **two-step verification**: authenticator app (TOTP + QR) and/or security key; **passkeys** for passwordless
  sign-in; email notifications; reset by a superadmin or from the console; single-use codes.
- **Dashboard** with KPIs, a sign-ups chart, latest users and the team; users report as a **PDF**.
- Highly compatible HTML **emails** (tables, inline CSS via Emogrifier, dark mode, plain-text version).
- **htmx**: lists, the permissions matrix and the 2FA card update without a page reload, CSP-compatible.
- **Security**: strict CSP with nonces, security headers, HTTPS + HSTS, CSRF and FormProtection, encrypted
  "remember me" cookie, hardened session, per-IP rate limiting, trusted proxies.
- **i18n**: English and Spanish (based on the browser).
- **Quality**: TDD (≈225 tests), PHPStan level 8, CakePHP CodeSniffer, `composer audit`, CI on GitHub Actions.

## Stack

| Layer | Technology | Version | Why |
| --- | --- | --- | --- |
| Language | PHP | 8.5 | Project requirement |
| Framework | CakePHP | 5.4 | Conventions, ORM, DI container, security middlewares |
| Users | CakeDC/Users (fork `delamux/CakeDC-users`, branch `issue/4-webauthn-lib-5`) | 16.0.5 + webauthn-lib 5 | Login, sign-up, 2FA; the fork makes it compatible with webauthn-lib 5 / PHP 8.5 |
| Authorization | CakeDC/Auth RBAC + custom database provider | 10.1 | Per-role rules editable in the UI |
| 2FA / passkeys | robthree/twofactorauth · web-auth/webauthn-lib · endroid/qr-code | 3 · 5.3 · 6.2 | TOTP, WebAuthn, server-side QR |
| UI | [delamux/cakephp-basecoat-theme](https://github.com/delamux/cakephp-basecoat-theme) (Basecoat + Tailwind v4) | main | shadcn-style components, light/dark, no inline JS |
| Interaction | htmx | 2.0.11 | Fragments without reloads, no build step or JS framework |
| CSP | paragonie/csp-builder (CakePHP's CspMiddleware) | 3.1 | Content-Security-Policy with nonces |
| PDF | friendsofcake/cakepdf + Dompdf | — | Pure-PHP PDF, no binaries |
| Emails | pelago/emogrifier | — | Inline CSS for email clients |
| Database | PostgreSQL | 18 | |
| Server | nginx + PHP-FPM | 1.29 · 8.5 | Same multi-stage Dockerfile for dev and production |
| Mail in dev | Mailpit | — | Local inbox at http://localhost:8026 |
| Local HTTPS | mkcert + shared SNI proxy | — | `https://admindashboard.localhost` without a port (WebAuthn requires HTTPS) |
| Production assets | Tailwind CLI + basecoat-css, Chart.js, htmx, Geist (Docker Node stage) | — | Everything self-hosted: no requests to CDNs |
| Quality | PHPUnit 13 · PHPStan 2 (level 8) · CakePHP CodeSniffer | — | `composer check`; CI on GitHub Actions |

## Architecture

DDD-lite: thin controllers and commands; orchestration (auditing, caching, transactions, emails) lives in **use cases**
under `src/UseCase`, injected with CakePHP's DI container (`Application::services()`); domain rules live in plain
classes (`UserRoleManager`, `PermissionsSynchronizer`, `ActionDiscovery`) and Tables act as repositories.
`ArchitectureTest` prevents controllers and commands from orchestrating on their own.

```
src/
├── UseCase/            # use cases: Permissions, Roles, TwoFactor, Users (+ Actor)
├── Rbac/               # action discovery, synchronization, database RBAC provider, auditing
├── Service/            # role rules, dashboard statistics
├── Authentication/     # optional 2FA checkers
├── Webauthn/           # WebAuthn adapters (registration, second step, passkey)
├── Http/               # SecurityMiddlewares (CSP, headers, HTTPS, CSRF, cookies), AuthThrottle
├── Middleware/         # per-IP rate limiting, trusted proxies, identity refresh
├── Controller/         # thin; Htmx component and custom OneTimePasswordAuthenticator
└── Model/              # UsersTable (2FA, superadmin sync), Roles, Permissions, auditing
webroot/js/             # app.js (confirmations, htmx CSRF) and webauthn.js, no build step
```

## Getting started (development)

```bash
# 1. Local HTTPS (once per machine; WebAuthn/passkeys require it)
brew install mkcert && mkcert -install
bin/dev-https-cert

# 2. Services and database
docker compose up -d --build
docker compose exec app composer install
docker compose exec app bin/cake migrations migrate -p CakeDC/Users
docker compose exec app bin/cake migrations migrate
docker compose exec app bin/cake permissions sync
docker compose exec app bin/cake users add_superuser -u admin -p 'Admin1234!' -e admin@cakephp-admin.test
docker compose exec app bin/cake seeds run DemoUsers     # optional: 40 demo users (password Demo1234!)
```

| Service | URL |
| --- | --- |
| App | https://admindashboard.localhost (via the shared proxy) · https://admindashboard.localhost:8445 · http://localhost:8080 |
| Mailpit | http://localhost:8026 |
| PostgreSQL | `localhost:5433` (cakephp / secret; test database `cakephp_admin_test`) |

- In dev the code is mounted into the container and Tailwind is loaded with its browser build: nothing needs compiling.
- **Port-less HTTPS alongside other projects:** the `~/code/local-https-proxy` proxy (ports 443/80, outside this
  project) routes by domain without decrypting (SNI): `admindashboard.localhost` → 8445, `filae.localhost` → 8444.
  Start it once with `docker compose up -d` in that folder. `*.localhost` domains don't need `/etc/hosts`.
- The certificate lasts ~2 years; renew it with `bin/dev-https-cert`.
- Configurable ports: `APP_PORT`, `HTTPS_PORT`, `MAILPIT_UI_PORT`, `MAILPIT_SMTP_PORT`, `DB_PORT`.

## Day-to-day development

```bash
docker compose exec app composer check                    # PHPUnit + CodeSniffer + PHPStan level 8
docker compose exec app bin/cake permissions sync         # after adding controllers or actions
docker compose exec app bin/cake permissions purge        # permissions history (365-day retention)
docker compose exec app bin/cake activity purge           # account activity (180-day retention)
docker compose exec app bin/cake two_factor reset admin   # remove 2FA from an account (e.g. a superadmin without their phone)
```

- **TDD** for the project's own business rules (not for templates or for what the framework already tests).
- **CI** (`.github/workflows/ci.yml`): PHPUnit against PostgreSQL 18, CodeSniffer (annotated on the PR), PHPStan and
  `composer audit` on every push to `main` and on pull requests. Dependabot updates Composer packages and actions.
- **Translations:** when adding strings, run `bin/cake i18n extract --paths src,templates --output resources/locales
  --merge no --overwrite --extract-core no`, fill in `resources/locales/es_ES/default.po` and run
  `bin/cake cache clear_all`.

## Security

Applied following [book.cakephp.org/5.x/security](https://book.cakephp.org/5.x/security.html)
(`App\Http\SecurityMiddlewares`, `config/app.php` → `Security.*`). `SecurityTest` checks it against real responses.

| Measure | How |
| --- | --- |
| **Content-Security-Policy** | `CspMiddleware` with script and style nonces; nothing inline (no `'unsafe-inline'`/`'unsafe-eval'`), `object-src 'none'`, `frame-ancestors`/`form-action`/`base-uri 'self'`. Strict in production; **Report-Only in dev** (Tailwind's browser build injects styles without a nonce). |
| Headers | `SecurityHeadersMiddleware`: `nosniff`, `X-Frame-Options: sameorigin`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-Download-Options`, `Permissions-Policy` (camera, microphone, geolocation… disabled; WebAuthn allowed). |
| HTTPS + HSTS | `HttpsEnforcerMiddleware` with `HTTPS_ENFORCE=true` (production image): HTTP → HTTPS and a 1-year `Strict-Transport-Security`. |
| CSRF | `CsrfProtectionMiddleware`: `httponly` cookie, `SameSite=Lax` and `secure` over HTTPS; `fetch`/htmx requests send `X-CSRF-Token` from `<meta name="csrf-token">`. |
| FormProtection | In every controller: `FormHelper` forms carry a token for their fields. Only the WebAuthn JSON endpoints and the matrix checkboxes are unlocked. |
| "Remember me" cookie | `EncryptedCookieMiddleware` (AES) with `COOKIE_KEY` (or derived from the salt): neither readable nor forgeable; `secure` and `SameSite=Lax`. |
| Session | Stored in **PostgreSQL** (`sessions` table); `httponly`, `SameSite=Lax`, `use_strict_mode`, `secure` over HTTPS, idle timeout (`SESSION_TIMEOUT`). |
| Rate limiting | Per IP, in **PostgreSQL** (`auth_throttles`, hashed IP, atomic upsert): 20 failed sign-ins/2FA codes/passkeys every 15 min; sign-up, reset and resend 5 per hour (429 + `Retry-After`). |
| Trusted proxies | `TRUSTED_PROXIES`: `X-Forwarded-For` is only accepted from them (real IP for rate limiting and logs). |
| Account activity | `account_activities`: sign-ins, failures (only for existing accounts, never what was typed), sign-outs, password and 2FA, with IP and browser. Visible in the profile and, for the superadmin, on the user's page. Behind a proxy without `TRUSTED_PROXIES` you see the proxy's IP. |
| Accounts | 2FA/passkeys, single-use TOTP codes, 2FA fields not mass-assignable from forms, only a superadmin can manage superadmins, permission change history. |

**How to write CSP-compatible templates:** no `onclick`/`onsubmit`, no `<script>` outside `Html->script()`, no
`style="…"` and no `javascript:`. Instead:

- Scripts and styles via `$this->Html->script()` / `->css()` / `->scriptBlock()`: they get the nonce automatically.
- Confirmation: `'data-confirm' => __('Are you sure?')` on the button or form (handled by `webroot/js/app.js`).
- Submitting a form from a link: `'data-submit-form' => 'form-id'`.
- Theme components (sidebar, dialogs, alerts, progress bars): the theme's own `data-*` attributes.

## htmx

htmx 2 (self-hosted in production, pinned CDN in dev) with a custom integration inspired by `zunnu/cake-htmx`
(`App\Controller\Component\HtmxComponent`, loaded in `AppController`):

- **htmx requests** (`HX-Request`) are rendered with the `htmx` layout: just the template, and flash messages arrive
  as out-of-band **toasts** (`hx-swap-oob` into the toaster), even when `hx-select` picks another element. Navigation
  with `hx-boost` and no `hx-target` receives the full page. Always `Vary: HX-Request`.
- **CSRF** via header (`app.js`), **FormProtection** untouched (forms are still `FormHelper` forms) and **CSP**:
  `htmx-config` with `allowEval: false`, no injected styles and the page's nonce.
- If an htmx request requires login, `AppRedirectHandler` responds with `HX-Redirect` (full navigation).
- Controller helpers: `$this->Htmx->isRequest()`, `->trigger('event')`, `->redirect($url)`, `->refresh()`.

Common pattern: the form does `hx-post`, the controller redirects as usual and htmx follows the redirect and swaps
only the fragment:

```php
<?= $this->Form->create($entity, [
    'hx-post' => $this->Url->build(['action' => 'edit', $entity->id]),
    'hx-target' => '#my-block',
    'hx-select' => '#my-block',
    'hx-swap' => 'outerHTML',
]) ?>
```

In use: the users list (sorting, pagination and deletion with `hx-boost`), the permissions matrix (save with a toast)
and the two-step verification card (each step swaps only the card).

## Production

Self-contained images (`docker/app/Dockerfile`): `prod` (PHP-FPM, `composer --no-dev`, opcache without revalidation,
`www-data` user) and `web` (nginx with the static files). CSS is compiled in a Node stage (Tailwind v4 + basecoat-css)
and Basecoat JS, Chart.js, htmx and the Geist font are self-hosted; URLs carry `?<mtime>` to bust the cache on every
deploy. `config/app_local.php` is not included in the image: everything is configured through environment variables.

```bash
bin/dev-https-cert                                  # local: mkcert certificate (in real deployments, the domain's)
export SECURITY_SALT=$(openssl rand -hex 32) COOKIE_KEY=$(openssl rand -hex 32)
docker compose -f compose.prod.yaml up -d --build   # https://admindashboard.localhost (dev and prod, one at a time)
docker compose -f compose.prod.yaml exec app bin/cake migrations migrate -p CakeDC/Users --no-lock
docker compose -f compose.prod.yaml exec app bin/cake migrations migrate --no-lock
docker compose -f compose.prod.yaml exec app bin/cake permissions sync
docker compose -f compose.prod.yaml exec app bin/cake users add_superuser
```

| Variable | Default | Purpose |
| --- | --- | --- |
| `SECURITY_SALT` | — (required) | Security salt |
| `APP_FULL_BASE_URL` | `https://admindashboard.localhost` | Public URL (email links, WebAuthn domain: keys are bound to it) |
| `DATABASE_URL` | compose PostgreSQL | `postgres://user:pass@host:5432/db` |
| `EMAIL_TRANSPORT_DEFAULT_URL`, `EMAIL_FROM` | compose Mailpit | Outgoing SMTP |
| `COOKIE_KEY` | derived from the salt | AES key for the "remember me" cookie (≥ 32 characters) |
| `HTTPS_ENFORCE` | `true` in the image | HTTP → HTTPS + HSTS (`HSTS_MAX_AGE`, `HSTS_INCLUDE_SUBDOMAINS`) |
| `CSP_REPORT_ONLY` | `false` in the image (`DEBUG` in dev) | CSP in report-only mode |
| `TRUSTED_PROXIES` | empty | Load balancer IPs whose `X-Forwarded-For` is accepted (comma-separated) |
| `SESSION_TIMEOUT` | `240` | Idle minutes |
| `TWO_FACTOR_ENABLED` | `true` | Global switch for 2FA and passkeys |
| `DEBUG` | `false` | Never `true` in production |
| `THEME_COMPILED_ASSETS`, `ASSET_TIMESTAMP`, `LOG_TO_STDERR` | set in the image | Self-hosted assets, cache busting, logs to the container output |
| `HTTPS_PORT` | `8445` | HTTPS port published by nginx |

In a real deployment:
- Domain certificate in `docker/certs/` (or TLS at the load balancer + `TRUSTED_PROXIES`) and a fixed
  `APP_FULL_BASE_URL`.
- **Sessions and rate limiting in PostgreSQL** (`sessions` and `auth_throttles` tables): they survive redeploys and
  are shared by all replicas; no Redis needed.
- Daily purge via cron: `bin/cake permissions purge` and `bin/cake activity purge`.

## Where things live

| What | Where |
| --- | --- |
| Middlewares and services (DI) | `src/Application.php`, `src/Http/SecurityMiddlewares.php` |
| CakeDC/Users, 2FA and roles config | `config/users.php` |
| RBAC system rules | `config/permissions.php` (the rest in the database: *Access control › Permissions*) |
| Security (`Security.*`), session, cache | `config/app.php` |
| Use cases | `src/UseCase/` |
| Account activity | `src/Account/`, `src/View/Cell/AccountActivityCell.php`, `templates/cell/AccountActivity/` |
| Theme, layout and menu | `src/Controller/AppController.php`, `src/View/AppView.php`, `src/Navigation/SidebarMenu.php` |
| htmx | `src/Controller/Component/HtmxComponent.php`, `templates/layout/htmx.php`, `webroot/js/app.js` |
| WebAuthn / passkeys | `src/Webauthn/`, `src/Controller/{TwoFactor,WebauthnLogin,PasskeyLogin}Controller.php`, `webroot/js/webauthn.js` |
| User screens (overrides) | `templates/plugin/CakeDC/Users/Users/` |
| Emails | `src/Mailer/`, `templates/layout/email/`, `templates/email/`, `templates/plugin/CakeDC/Users/email/` |
| PDF | `src/Controller/ReportsController.php`, `templates/Reports/pdf/` |
| Docker, nginx, certificates | `compose.yaml`, `compose.prod.yaml`, `docker/`, `bin/dev-https-cert` |
| Production assets | `package.json`, `resources/css/app.css` |
| Translations | `resources/locales/` |
