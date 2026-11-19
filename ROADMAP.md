# ROADMAP — CakePHP Admin Skeleton

Complete admin skeleton: login, user management and dashboard, ready to clone and kick-start projects.

## Project rules (non-negotiable)

- **PHP 8.5** (`"php": ">=8.5"` in composer.json).
- **Latest CakePHP version** (5.4.x).
- **Always use Context7** to look up the CakePHP/plugin APIs (`/cakephp/docs`, `/cakedc/users`, `/cakephp/authentication`, `/cakephp/authorization`, `/cakephp/migrations`, `/friendsofcake/cakepdf`).
- Reference plugin catalogue: https://github.com/FriendsOfCake/awesome-cakephp
- **Before installing any plugin/package: ask the user and explain what it is for.**
- **Everything in local Docker**, database **PostgreSQL 18**.
- Emails via **Mailpit** in development.
- **Good-looking, maximally compatible** HTML emails (tables, inline CSS, 600px, plain-text version, dark mode).
- PDFs with **CakePdf** (Dompdf).
- Visual inspiration: https://shadcnuikit.com/dashboard/default
- The user is the author of the basecoat theme and allows modifying it if needed (confirm before push/PR).

## Stack

| Piece | Package | Status |
| --- | --- | --- |
| Framework | `cakephp/cakephp` 5.4.* (skeleton `cakephp/app` 5.4.0) | ✅ |
| Users/login/RBAC | `cakedc/users` ^16.0 (+ `cakedc/auth`, `cakephp/authentication`, `cakephp/authorization`) | ✅ (requested) |
| UI theme | `delamux/cakephp-basecoat-theme` dev-main (GitHub VCS, not on Packagist; PHP >= 8.2 since `b3dd6e5`) | ✅ (requested) |
| PDFs | `friendsofcake/cakepdf` 5.3 + `dompdf/dompdf` 3.1 | ✅ (approved) |
| Inline CSS for emails | `pelago/emogrifier` 8.3 | ✅ (approved) |
| Static analysis | `phpstan/phpstan` 2.3 (dev) | ✅ (approved) |
| Infra | Docker Compose: nginx + `php:8.5-fpm` (multi-stage dev/prod/web Dockerfile), `postgres:18-alpine`, `axllent/mailpit` | ✅ (FrankenPHP → PHP-FPM on 2026-11-18, user's decision) |

Ports: app http://localhost:8080 · Mailpit UI http://localhost:8026 (SMTP host 1026) · Postgres localhost:5433.
(8025 is used by another local project's Mailpit.)

## Phases

### Phase 0 — Base ✅
- [x] `composer create-project cakephp/app:~5.4`, PHP >=8.5
- [x] cakedc/users + basecoat theme
- [x] ROADMAP.md

### Phase 1 — Base configuration ✅
- [x] Docker: FrankenPHP + PG18 (+ test database `cakephp_admin_test`) + Mailpit (replaced by nginx + PHP-FPM in Phase 12)
- [x] Postgres driver / utf8 encoding in `config/app.php`; URLs from env
- [x] Plugins in `src/Application.php` (`CakeDC/Users`, `BasecoatTheme`, `CakePdf`)
- [x] `config/users.php` (loginRedirect → Dashboard, reCaptcha off, RememberMe, OneTimeLogin, custom mailer, access-denied message)
- [x] `config/permissions.php` (Dashboard for every logged-in user, admin everything, superuser bypass)
- [x] CakeDC/Users migrations + `admin` superuser
- [x] Demo seed `bin/cake seeds run DemoUsers`

### Phase 2 — Theme and layout ✅
- [x] `AppController::beforeRender`: BasecoatTheme theme, `auth` layout without identity, sidebar/user per request
- [x] `AppView`: `loadBasecoatHelpers()` + `CakeDC/Users.User` / `AuthLink`
- [x] `App\Navigation\SidebarMenu` (TDD): role-based menu, user block with Profile / Change password / Log out

### Phase 3 — CakeDC/Users templates ✅
- [x] login, register, request_reset_password, request_login_link, resend_token_validation, change_password
- [x] profile, index (sorting, pagination, row menu, responsive columns), view, add, edit (+ danger zone)

### Phase 4 — Dashboard ✅
- [x] `DashboardStatsService` (TDD): totals, active, pending, admins, new in the last 30d vs the previous 30d, daily series, recent, administrators
- [x] KPIs, sign-ups bar chart, latest users, team; regular users see a welcome card

### Phase 5 — Email ✅
- [x] SMTP → Mailpit via `EMAIL_TRANSPORT_DEFAULT_URL`
- [x] `CssInliner` (TDD) + `InlineCssTrait` (inlining before the body wrap) + `AppMailer` / `UsersMailer`
- [x] HTML layout (tables, 600px, preheader, bulletproof button, MSO, dark mode, mobile) + text layout
- [x] Overridden CakeDC emails: validation, reset, social, login link; custom subjects
- [x] Tested in Mailpit: html-check ~90–92 % supported (the rest: @media/<style> as progressive enhancement)

### Phase 6 — PDF ✅
- [x] `/reports/users.pdf` (admins): KPIs + table; `?download=1` forces the download `users-report-YYYYMMDD-HHMMSS.pdf`

### Phase 7 — Quality ✅
- [x] `composer check` = phpunit (26 tests) + phpcs (CakePHP standard) + phpstan level 8, all green
- [x] README

### Phase 8 — UI/UX QA ✅ (2026-11-18)
Walkthrough with Chrome DevTools (desktop 1280, mobile 390, light/dark, admin and regular user, emails in Mailpit, PDF).
Fixed during QA:
- [x] Plural "1 admins" → `__n`
- [x] "Latest users" table: long dates pushed the actions menu out → compact dates + `whitespace-nowrap` + scroll-x
- [x] Chart: a smoothed line exaggerated the daily data → bars
- [x] "Team" only showed admins among the latest 8 → `administrators()` (TDD)
- [x] Users list cut off → email under the name, secondary columns hidden with a container query
- [x] Header overflowing on mobile → icon-only actions (`element/header_action`, sr-only label) and date hidden on mobile
- [x] Status badge without an icon on the user page
- [x] "not authorized … Location = URL" message (debug) → custom message with a title
- [x] Date range visible to users without statistics → admins only
- [x] Profile/user page/edit stacked at 1280px → `@3xl` breakpoint
- [x] Error flash stuck to the first field in auth forms → inside the form grid
- [x] Login link email showed a 32-hex "code" with a 6-digit form (plugin inconsistency) → link only
- [x] Emails: removed `word-break` and poorly supported preheader hacks
- [x] PDF: "1 / 0" (Dompdf doesn't support `counter(pages)`) → "Page N"

### Phase 9 — Database-backed permissions (TDD) ✅ (2026-11-18)

**Goal:** stop defining each role's permissions in `config/permissions.php` and manage them from the database through
a UI: automatically discover controllers and their public actions and assign them to each role.

**Hook point (without touching CakeDC):** `CakeDC\Auth\Rbac` accepts `permissions_provider_class`
(currently `ConfigProvider`, which reads the file). We implement `App\Rbac\DatabasePermissionsProvider`
(extends `AbstractProvider`) and configure it in `Auth.RbacPolicy` → CakeDC's RBAC stays the same,
only the source of the rules changes.

**Design proposal (to be confirmed):**
- `App\Rbac\ActionDiscovery`: discovers controllers (app + selected plugins) and their public actions
  via reflection (`public` methods declared in the controller or its traits, excluding `initialize`,
  `beforeFilter`, callbacks and `Controller` methods), with prefix/plugin/controller/action.
- Tables: `permissions` (discovered catalogue: plugin, prefix, controller, action, `public` for actions
  without login) and `roles_permissions` (role ↔ permission, `allowed`).
- `bin/cake permissions sync` command (+ a button in the UI) that syncs the catalogue with the code:
  adds new actions, flags as orphaned those that no longer exist.
- Rules that do **not** go to the database (they stay in code for security): public login/sign-up/reset actions,
  `superadmin` = everything (and `is_superuser` still bypasses the RBAC), DebugKit in debug.
- UI: *controller/action × role* matrix with checkboxes, superadmin only; changes cached
  (`_cake_rbac_`) and invalidated on save.
- Security: don't allow removing your own access to the permissions screen; superadmin not editable;
  optional auditing (who changed what and when).

**Tests (TDD):** action discovery (what counts as a public action), catalogue sync,
provider → CakeDC rules equivalent to the current ones in `permissions.php` (non-regression test:
same allow/deny decisions for the 4 roles), UI matrix → rules, cache invalidation, guards
(nobody locks themselves out).

**Decisions (user, 2026-11-18):**
| Topic | Decision |
| --- | --- |
| Discovery scope | App controllers + CakeDC/Users (Users, SocialAccounts). DebugKit, Bake and technical plugins excluded |
| Roles | Move to the database with CRUD (`roles` table, seeded with superadmin/admin/editor/user). `superadmin` protected: cannot be deleted or renamed |
| Granularity | *Allow* whitelist per action (all extensions). Anything unchecked is denied |
| `config/permissions.php` | System rules only: public actions (login/sign-up/reset/validation), superadmin = everything, DebugKit, own profile/logout. The rest from the database, with a seed that replicates the current permissions |

**Implementation plan (each step: red test → green → refactor):**
1. Migrations: `roles` (unique slug, name, `protected`), `permissions` (plugin, prefix, controller, action, unique per combination, `orphan`), `roles_permissions`. `users.role` remains the slug.
2. `App\Rbac\ActionDiscovery`: list of public actions (reflection; excludes `Controller`/`AppController` methods, callbacks and non-public methods; includes traits such as CakeDC's).
3. `bin/cake permissions sync`: inserts new ones, flags orphans, idempotent.
4. Non-regression seed: per-role permissions equivalent to the current ones (admin = everything except system; editor = Users::index/view + Reports::users; user = Dashboard::index).
5. `App\Rbac\DatabasePermissionsProvider` (configured in `Auth.RbacPolicy`): system rules from the file + database rules, cached; non-regression test of the allow/deny decisions for the 4 roles.
6. `UserRoleManager` reads roles from the database (dynamic whitelist); `superadmin` stays in sync with `is_superuser`.
7. UI (superadmin only): roles CRUD + controller/action × role matrix; cache invalidation on save; guards (can't remove your own access to the permissions screen, `superadmin` not editable, can't delete a role with assigned users).
8. UI/UX QA + quick security roast.

**Done:** steps 1–8 complete, 120 green tests + phpcs + phpstan level 8.
- `roles`/`permissions`/`roles_permissions` tables (migration `20261118130000`), `RolesTable` with rules (protected, in use, slug).
- `App\Rbac\ActionDiscovery` (`Controller::isAction()` criterion + `App.permissions.ignoredActions`), `PermissionsSynchronizer`, `bin/cake permissions sync [--reset]`.
- `App\Rbac\DatabasePermissionsProvider` (extends CakeDC's `ConfigProvider`) in `Auth.RbacPolicy`; `PermissionsCache` invalidated on every change. Non-regression test: 18 allow/deny decisions identical to the previous file.
- `config/permissions.php` = system rules only (public, superadmin, profile/logout, dashboard, Pages, DebugKit).
- Superadmin UI: `/roles` (CRUD) and `/permissions` (matrix with locked rows: Public / All roles / Superadmin only) + "Sync actions" button; "Access control" in the sidebar.
- QA/roast: (1) system rows were shown as editable with no effect → locked; (2) **escalation**: an admin could be granted `Permissions::*`/`Roles::*` from the matrix and grant themselves everything → `App.permissions.superadminOnly`, filtered in `PermissionsSynchronizer` (test); (3) unknown actions via fallback routes are now denied by the RBAC (whitelist) instead of a 404.

### Phase 10 — Permissions auditing (TDD) ✅ (2026-11-18)
- `permission_audits` table (migration `20261118140000`): user_id, username, event, role_slug, added/removed/details (JSON), created. Append-only.
- `App\Rbac\PermissionAudit::record()`; events `grants_changed` (only roles with an actual diff), `role_created`, `role_updated` (fields `[before, after]`), `role_deleted` (only if actually deleted), `sync` (counts + `reset`; actor `console` from `bin/cake`).
- `PermissionsSynchronizer::setGrants()` returns `{added, removed}`.
- `/permissions/audit` screen (superadmin, paginated, + granted / − revoked); sidebar *Access control › History*.
- Bug found in QA: saving the matrix revoked the locked rows (the browser doesn't submit them) → they are now preserved (test).
- Purge ✅: `bin/cake permissions purge [--days N] [--dry-run]`, retention `App.permissions.auditRetentionDays` (365, minimum 30 → error), records `audit_purged` only if something is deleted (quiet daily cron). Logic in `PermissionAudit::purge()/countOlderThan()`.

### Phase 11 — DDD-lite refactor (TDD) ✅ (2026-11-18)
Goal: thin controllers and commands; orchestration in use cases injected by the CakePHP 5 DI container (no new packages). No full DDD (aggregates/model separate from the ORM): the admin is mostly CRUD.
- Layers: `src/UseCase/` (application: orchestration, transactions, auditing, caching) → existing pure domain rules (`UserRoleManager`, `PermissionsSynchronizer`, `ActionDiscovery`) → Tables as repositories (infrastructure). Namespace `App\UseCase` (not `App\Application`, which clashes with the `App\Application` class).
- Steps:
  1. Container: `Application::services()` with `ReflectionContainer` (autowiring) + `fromConfig()` factories for the configurable services. `Actor` value object (user or console).
  2. Permissions: `PermissionMatrix` (read model: groups + locked rows, moves `matchesSystemRule` out of the controller), `UpdateRolePermissions`, `SyncPermissions`.
  3. Roles: `ManageRoles` (create/edit/delete + cache + auditing).
  4. History: `PermissionAuditLog` (injectable instance instead of statics) + `PurgePermissionHistory`; commands with constructor injection.
  5. Architecture test (without StructArmed): controllers/commands don't call `PermissionAudit`, `PermissionsCache` or `::fromConfig()`.
  6. Full suite green with no behaviour change (the existing integration tests are the safety net).
- **Done:** 148 green tests + phpcs + phpstan 8.
  - `App\UseCase\Actor` (user/console VO), `Permissions\{PermissionMatrix, UpdateRolePermissions (transaction), SyncPermissions, PurgePermissionHistory}`, `Roles\{ManageRoles, RoleResult}`, `Users\ChangeUserRole`.
  - `App\Rbac\PermissionAuditLog` (instance, repository for `permission_audits`) replaces the static `PermissionAudit`.
  - `PermissionMatrix` returns lock codes (`public`/`all_roles`/`superadmin`); the template translates them.
  - Container: explicit registrations in `Application::services()` (no global `ReflectionContainer`: it would autowire every controller, CakeDC's included). Injection into actions (`index(PermissionMatrix $matrix, …)`) and via constructor in commands.
  - `AppController::actor()`; `ArchitectureTest` blocks in controllers/commands: `PermissionAudit*`, `PermissionsCache::`, `::fromConfig(` and direct writes to the audit/permissions tables. Documented exception: `AppController` (guards in `beforeFilter` for CakeDC's controllers, without injection).

### Phase 12 — Production ✅ (2026-11-18)
- User's decisions: Tailwind compiled in a **Docker Node stage** (npm `tailwindcss`, `@tailwindcss/cli`, `basecoat-css`, build only) and **PHP-FPM instead of FrankenPHP** (no worker mode: CakePHP has no official support and static state would leak between requests).
- Multi-stage `docker/app/Dockerfile`: `base` (php:8.5-fpm + extensions), `dev` (composer/git, bind mount), `vendor` (`--no-dev`, classmap-authoritative autoload), `assets` (node:22 → `webroot/css/app.css` + `webroot/js/basecoat`), `prod` (opcache `validate_timestamps=0`, `www-data`, `plugin assets copy`), `web` (nginx + static files).
- `docker/app/fpm-pool.conf` (`clear_env = no`: without it PHP-FPM doesn't see `DATABASE_URL`/`SECURITY_SALT`), `php-dev.ini` / `php-prod.ini` (errors to stderr, `secure`/`httponly`/`SameSite=Lax` cookies), `docker/nginx/default.conf`.
- `compose.yaml` (dev): `web` (nginx :8080) + `app` (FPM) services. `compose.prod.yaml`: fails if `SECURITY_SALT` / `APP_FULL_BASE_URL` are missing.
- Config: `Datasources.default.url = env('DATABASE_URL')` in `app.php`; `config/app_local.php` kept out of the image (`.dockerignore`); `DebugKit` optional (it doesn't exist with `--no-dev`); `BasecoatTheme.compiledAssets` (`THEME_COMPILED_ASSETS`) → `AppView::themeConfig()` (`css: app`, no Tailwind browser build, custom JS).
- Verified: 148 tests in the FPM container; prod stack on :8081 migrated, synced (41 actions), superadmin login, matrix and dashboard (chart) with no console errors; `app.css` 235 KB uncompressed (gzip in nginx).
- Self-hosted (2026-11-18, approved by the user): npm `chart.js@4.4.7` (same version as the CDN) → `webroot/js/vendor/chart.umd.js`; `@fontsource-variable/geist@5.3.0` (OFL) → `webroot/font/geist/` (latin + latin-ext subsets, `@font-face` in `resources/css/app.css`); `AppView::themeConfig()` with `font: false` and local `chartJs`. Production no longer makes any external request (verified: only its own origin).
- Cache busting: `Asset.timestamp = env('ASSET_TIMESTAMP', true)`, `force` in the prod image → `app.css?<mtime>`. Found in QA: with nginx's `Expires` the browser kept using the old `app.css` after a deploy. nginx caches static files for 7 days.
- Logs to stderr (2026-11-18): `LOG_TO_STDERR=true` in the prod image → the `debug`/`error`/`queries` loggers in `config/app.php` use `ConsoleLog` (`php://stderr`, no colours) instead of `FileLog`. Verified: a 404 shows up in `docker compose logs app` and `logs/` stays empty. Dev still uses files.
- TLS (2026-11-18, revised at the user's request): filae's **mkcert** flow — `bin/dev-https-cert` generates `docker/certs/admindashboard.localhost{,-key}.pem` (outside git), nginx serves `https://admindashboard.localhost` on 443 (no port in the URL) only if the certificate exists (`docker/nginx/https.conf` + `10-enable-https.sh`), in dev and in the prod stack. HSTS and security headers in nginx. Caddy removed. Coexistence with filae (user's decision: shared proxy): `~/code/local-https-proxy` (nginx stream, `ssl_preread`, SNI passthrough) on 443/80 → `admindashboard.localhost` :8445 (`HTTPS_PORT`), `filae.localhost` :8444 (`APP_HTTPS_PORT=8444` in filae's `.env`; without touching its code). Verified: both domains without a port at the same time, valid certificates, 80 → 301 HTTPS.
- Notes: in prod use `migrations migrate --no-lock` (the code is read-only for `www-data`).

### Phase 13 — 2FA (TOTP + security key) and passkeys (TDD) ✅ (2026-11-18)
Reference: filae's implementation (`../filae`, specs `openspec/specs/{two-factor-auth,passkey-login}`). Approved packages: fork `delamux/CakeDC-users` branch `issue/4-webauthn-lib-5` (alias 16.0.5; webauthn-lib 5), `robthree/twofactorauth` ^3, `web-auth/webauthn-lib` ^5.3.5, `endroid/qr-code` ^6.2.
- Rules: optional 2FA for every role; TOTP and/or security key methods (independent); with TOTP enabled the code is requested; the key only if it is the only method; passkey = passwordless login with the same key. Enabling/disabling requires the current password; email on every change (no secrets). No recovery codes: the superadmin resets other users (not other superadmins) and `bin/cake two_factor reset` covers the superadmin themselves. `TWO_FACTOR_ENABLED` (enabled by default). The email sign-in link also goes through 2FA.
- Differences from filae: no prefixes (CakeDC's profile screen), use cases in `src/UseCase/TwoFactor` (DDD-lite), JS without a build step (`webroot/js/webauthn.js`), HTTPS in dev with Caddy (`https://admindashboard.localhost:8443`; WebAuthn requires it).
- Steps: 1) optional checkers + methods in `UsersTable` · 2) enable/disable in the profile (TOTP with QR, key) + emails · 3) login with 2FA (verify, key) · 4) passkeys · 5) reset by superadmin + command · 6) per-IP rate limiting · 7) dev HTTPS + QA.
- **Done** (191 tests + phpcs + phpstan 8):
  - Data: no migrations (CakeDC already ships `secret`, `secret_verified`, `additional_data`). `App\Model\Entity\User` protects `secret`, `secret_verified` and `additional_data` from mass assignment (CakeDC's entity allowed it: a TOTP secret or a key could be planted from a form).
  - `UsersTable`: `twoFactorMethods`, `enableTotp`, `disableTotp`, `disableWebauthn`, `disableTwoFactor`, `markPasskey`, `isLegacyWebauthnKey`, `findPasskeyOwner`, `recordLogin`. Checkers `App\Authentication\Optional*Checker`.
  - Use case `App\UseCase\TwoFactor\TwoFactorSettings` (password, TOTP/QR, confirm, registered key, disable, reset, `twoFactorChanged` email). `TwoFactorController` (POST steps, 10 min in session, superadmin reset with defence in depth), `TwoFactor` cell (profile card and status on the user page).
  - Login: `verify` / `webauthn2fa` templates, `WebauthnLoginController` (custom adapter: CakeDC creates it inside its action). Passkeys: `App\Webauthn\PasskeyLoginAdapter` (single-use challenge, 5 min, no `allowCredentials`, `userHandle`), `PasskeyLoginController` (active, lockout if configured, remember me, internal redirects only), routes `/passkey/{options,login}`. `StandardAlgorithmsTrait` avoids the Ed256/Ed512 warning that broke the JSON.
  - Build-free JS `webroot/js/webauthn.js` (registration, second step, passkey with conditional autofill; CSRF via header, base64url).
  - `bin/cake two_factor reset <user|email>`. `TwoFactor::reset` in `PermissionsSynchronizer::SUPERADMIN_ONLY` (single source of truth).
  - Per-IP rate limiting (`App\Http\AuthThrottle`, `App\Middleware\AuthThrottleMiddleware`, `throttle` cache, `Security.throttle`): failed sign-ins + wrong codes + failed passkeys 20/15 min; sign-up/reset/resend 5/h; 429 + `Retry-After`. In tests, in-memory cache.
  - "Remember me" cookie `secure` (if the app runs over HTTPS) and `SameSite=Lax`. The email sign-in link also asks for 2FA.
  - Tests with real signatures (EC P-256, `WebauthnTestTrait`): end-to-end second step with a key and passkey, single-use/expired challenge, increasing counter (anti-cloning), legacy keys, no user verification, inactive accounts.
  - QA (https://admindashboard.localhost): enabling TOTP with QR, email, sign-in with a code, status and reset from a user's page, reset email; es_ES translations (57 new strings).
- TOTP code reuse closed (2026-11-18, TDD): `users.totp_last_step` column (migration `20261118160000`), `UsersTable::claimTotpStep()` (a single `UPDATE ... WHERE totp_last_step IS NULL OR < :step`: atomic, race-free), `TwoFactorSettings::verifyLoginCode()` and `App\Controller\Component\OneTimePasswordAuthenticatorComponent` (replaces CakeDC's in `AppController::beforeFilter()`). The activation code counts as used. Verified in the app: activation code rejected, new code accepted, the same code again rejected.
- Known limitations: actually registering/using keys and passkeys requires a device (Touch ID, security key, password manager): covered by tests with real signatures, not tested by hand. (Trusted proxies: resolved in Phase 15 with `TRUSTED_PROXIES`.)

### Phase 14 — CI with GitHub Actions ✅ (2026-11-18)
- `.github/workflows/ci.yml` (replaces the one from the CakePHP skeleton): on push to `main`, pull requests and manually. One job on `ubuntu-24.04` with PHP 8.5 (same extensions as the image) and PostgreSQL 18 as a service: `composer install` → `config/app_local.example.php` as local config → **PHPUnit**, **CodeSniffer** (PR annotations with `cs2pr`), **PHPStan** level 8 (`github` format) and **`composer audit`**. Each quality step runs even if the previous one fails, so you see everything at once.
- User's decisions: tests and analysis only (no Docker image builds) and no migration steps (the test schema is created by the suite's bootstrap).
- Verified by reproducing the job locally from scratch (clean copy without `vendor/` or `app_local.php`, empty PostgreSQL): 194 tests, phpcs, phpstan and audit all green.
- To make it run: create the git repository and push it to GitHub (the project isn't a repo yet). Dependabot already updates Composer and the actions weekly.

### Phase 15 — Security (book.cakephp.org/5.x/security) ✅ (2026-11-18)
User's decisions: strict CSP in production and Report-Only mode in dev; theme without inline JS (no config option) published straight to main; approved packages `paragonie/csp-builder` (CspMiddleware) and `htmx.org` (npm).
- Steps: 1) theme without inline `onclick`/`<script>` (event delegation + `Html->scriptBlock` with nonce) · 2) `SecurityHeadersMiddleware` · 3) `HttpsEnforcerMiddleware` + HSTS (production) · 4) `CspMiddleware` with nonces (report-only in debug) · 5) CSRF `secure` + `SameSite` · 6) `FormProtection` in the app's controllers · 7) `EncryptedCookieMiddleware` for "remember me" · 8) hardened session · 9) trusted proxies (real IP behind a load balancer).
- **Done** (`SecurityTest`, 208 tests + phpcs + phpstan 8):
  - Theme (straight to main, `c3842c0` + `fabdb9d`): sidebar, dialogs, alerts, toasts, progress bars and "Back" with `data-*` + delegation in `basecoat-theme.js`; nonces on the dark-mode script and Tailwind's `<style>`; re-initialisation after `htmx:load`.
  - App: `App\Http\SecurityMiddlewares` (factory) and `App\Middleware\TrustedProxiesMiddleware`; config `Security.{https,csp,cookieKey,trustedProxies}` and `Session.ini`; nginx no longer duplicates headers.
  - Templates without inline JS: `data-confirm` and `data-submit-form` (`webroot/js/app.js`); removed the skeleton's flash templates (the theme's take precedence) and CakeDC's old "Reset authenticator" card (it duplicated the Phase 13 reset).
  - QA on the production stack with the CSP enforced: no console errors on login, dashboard, users, permissions and profile (2FA). Found by QA: `ProgressHelper`'s `style="width"` and the error layout's `javascript:history.back()` (blocked by the CSP) → fixed in the theme.
  - Tests: `configRequest()` merges recursively and `app.php` is loaded once per process (tests use `Configure::write`); the failed sign-in listener was registered on every bootstrap → static closure.
- Sessions in PostgreSQL (2026-11-18, user's decision): migration `20261118170000_CreateSessions` (`id` varchar 128 PK, `data` bytea, indexed `expires` int, `created`/`modified`), `Session.defaults = database` in every environment (the hardened `Session.ini` and the `timeout` are kept). Verified: the session is stored in `sessions` and stays open after recreating the `app` container. PHP's GC deletes expired ones (`expires < now`).
- Rate-limit counters in PostgreSQL (2026-11-18, user's decision): migration `20261118180000_CreateAuthThrottles` (PK `bucket` + SHA-256 `ip_hash`, `hits`, indexed `window_ends`); `App\Http\AuthThrottle` performs a single atomic `INSERT … ON CONFLICT DO UPDATE` (resets an expired window; no lost counts with concurrent requests) and occasionally purges expired rows (`purgeExpired()`). The `throttle` cache is gone. `AuthThrottlesTable` exists for the ORM (fixtures; no fallback table classes). Tests: the bootstrap empties the table at start; the ones that count use the fixture.

### Phase 16 — htmx ✅ (2026-11-18)
- Custom integration inspired by `zunnu/cake-htmx` (without the plugin, which is unmaintained): `Htmx` component (`HX-Request` detection, partial rendering, `HX-Trigger`, `HX-Redirect`), htmx 2.0.11 self-hosted in production, CSRF via header, CSP-compatible (`allowEval: false`, no inline styles). Applied to: the 2FA card, the users list (search/sort/pagination) and the permissions matrix.
- **Done** (`HtmxTest`): `htmx` layout with flash messages as out-of-band toasts (`hx-swap-oob` into the toaster: visible even when `hx-select` picks another element), `Vary: HX-Request`, `hx-boost` requests with `hx-target` also partial, `HX-Redirect` when an htmx request needs login, `htmx-config` with the page's nonce, `webauthn.js` re-bound after `htmx:load`. Verified in production (strict CSP): sorting users without a reload (URL in the history), saving permissions with a toast, 2FA step swapping only the card.
- README rewritten with the full stack, security and htmx.

### Phase 17 — Account activity log (TDD) ✅ (2026-11-18)
- `account_activities` table (auto-increment id = insertion order, `user_id` FK with CASCADE, `actor_id` FK SET NULL, `event`, `ip`, `user_agent` ≤255, `created`). Decisions with the user: **full IP**, **failures for non-existent users are not stored** (the throttle covers them; what was typed is never stored), **profile + user page** (superadmin only), **180-day retention** (`App.accountActivity.retentionDays`, `bin/cake activity purge`, minimum 30).
- Events: `login`, `passkey_login`, `login_failed`, `two_factor_failed` (wrong code or key after the correct password), `logout`, `password_changed` / `password_reset` (via token), `totp_enabled` / `totp_disabled`, `key_added` / `key_removed`, `two_factor_reset` (with who did it).
- Where: `App\Account\AccountActivityListener` for CakeDC's events (replaces the static closure for failures: a single listener, registered once per EventManager with a `WeakMap`), `TwoFactorSettings` for 2FA, `PasskeyLoginController` and `WebauthnLoginController`. A write failure goes to the log and doesn't break the action.
- UI: `AccountActivity` cell (latest 20, failures in red, "Chrome on macOS" via `App\Account\UserAgent`, IP, "by X" if someone else acted). Translated into Spanish. Tested in the browser.
- Tests: `AccountActivityTest`, `UserAgentTest`, an assertion in `PasskeyLoginTest`. 226 green tests, phpcs and phpstan green.
- AuditStash (`lorenzo/audit-stash`) was ruled out for this (it audits ORM record changes, not sign-ins/failures/sign-outs); it remains an option for auditing business entities.

### Phase 18 — Queue (dereuromark/cakephp-queue) ✅ (2026-11-18)
- Decisions (2026-11-18): AuditStash **ruled out** for now (there are no business entities to audit; users and roles are already covered by account activity and `permission_audits`). Queue, yes:
  - **All emails** through the queue (`queue` transport in front of the real one; CakeDC, 2FA, notifications).
  - **Worker as its own Docker service** (`bin/cake queue run`, automatic restart), in dev, prod and Coolify.
  - **Custom screen** with Basecoat (the plugin's uses Bootstrap from a CDN, incompatible with the CSP): counters, failed jobs with their error, retry/delete, workers. Superadmin only.
  - Failures: screen + error log (stderr). No notification emails.
  - The users PDF stays synchronous.
  - **`bin/cake maintenance`**: one command for the daily cron (activity purge, permissions history purge, queue cleanup).
- Local ports unchanged (dev). No Mailpit in production.
- **Done** (TDD; 235 tests + phpcs + phpstan 8):
  - `dereuromark/cakephp-queue` 8.18 (`addPlugin('Queue', ['routes' => false])`), tables from its migrations; `bin/migrate` (CakeDC → Queue → app) and `bin/deploy` (migrate + `permissions sync`).
  - Emails: `queue` transport (`Queue.Queue` in front of `default`), `Email.default` → `queue` (`EMAIL_QUEUE=false` to disable). `QueuedEmailTest`: the email is queued and the worker delivers the already rendered message (inline CSS). Testing note: `EmailTrait` replaces every transport and never restores them.
  - `App\Queue\QueueFailureListener`: failed attempt → warning, retries exhausted → error; first line only, no trace or data.
  - `bin/cake maintenance` (`App\UseCase\Maintenance\DailyMaintenance`): activity, permissions history, queue (`cleanOldJobs`), expired sessions, rate-limit counters; a failing step doesn't stop the rest.
  - `/queue` screen (`QueueController` + `App\UseCase\Queue\QueueMonitor`, `Queue::*` in `SUPERADMIN_ONLY`, menu *System › Queue*): counters, failed jobs with their error (retry/delete/retry all; only failed jobs can be deleted), recent jobs without data, workers; warning if no worker is running. Verified with no inline styles/events and no scripts without a nonce.
  - `Queue` config in `app.php` (600 s lifetime + jitter, sleeptime 5, 3 retries, 1 worker per container, completed kept 7 days / failed 30). `pcntl` in the image (graceful shutdown).
  - Docker: `worker` service in dev and prod; one-shot `migrate` in prod; nginx with a `PHP_FPM_HOST` upstream (template `00-upstream.conf.template`) and `/healthz`.
  - Verified end to end in dev and on the production stack: queued email → worker → Mailpit; `migrate` exits with 0.
  - Found: CakeDC's `bin/cake users password_email` sends nothing (it doesn't pass `type => password` to `resetToken()`); documented.

### Phase 19 — Deployment on Coolify ✅ (2026-11-18)
- Custom compose for Coolify: PostgreSQL as a **Coolify database resource** (S3 backups; the app receives `DATABASE_URL`), **HTTP-only nginx** behind Traefik (Coolify's TLS, `TRUSTED_PROXIES`, HSTS from the app), image **built by Coolify from the repo**, cron via Coolify **Scheduled Tasks** (`bin/cake maintenance`).
- **Done:** `compose.coolify.yaml` (services `web`, `app`, `worker`, `migrate` with `restart: "no"` + `exclude_from_hc`; no ports; `SERVICE_URL_WEB_80` → `APP_FULL_BASE_URL`; `SERVICE_HEX_64_*` for the salt and the cookie key; `DATABASE_URL`/SMTP required; `PHP_FPM_HOST=${SERVICE_NAME_APP}` to avoid clashes on the shared network). Checked against the Coolify documentation (Context7).
  - `TRUSTED_PROXIES` accepts IPv4/IPv6 **CIDR ranges** (`TrustedProxiesMiddleware`, TDD): behind Traefik `X-Forwarded-For` and `X-Forwarded-Proto` are honoured (no redirect loop, HSTS, `secure` cookies); defaults to Docker's private ranges. `HttpsEnforcerMiddleware` no longer repeats `setTrustedProxies`.
  - Simulated locally with the production stack: HTTP request with `X-Forwarded-Proto: https` from another container → 200 with HSTS and CSP; without the header → 301; outside the range → the header isn't trusted.
  - Pending on the user's side: push the repo, create the database and the app in Coolify (steps in `docs/deploy-coolify.md`).

### Phase 20 — Documentation (`docs/`) ✅ (2026-11-18)
- A guide per area covering everything the app can do; README as summary and index; the crons to run always documented.
- **Done:** `docs/` with 13 guides (getting started, architecture and how to add a module, accounts and roles, permissions, 2FA, activity, queue and custom jobs, emails and PDF, security and CSP templates, htmx, configuration, Coolify, operations and crons); README reduced to a summary + index. Internal links checked.

### Phase 21 — File uploads: user photo (TDD) ✅ (2026-11-19)
- Approved package: `josegonzalez/cakephp-upload` 8.1.1 (FriendsOfCake) + `league/flysystem` 3. User's decisions: **`<table>/<id>/<field>/<uuid>.<ext>` convention** for every entity (not the original name nor the controller), **private Docker volume** (served by a controller with a session), everyone manages their own photo + admins can manage others'.
- `App\Storage\Uploads` (root `Uploads.path`/`UPLOADS_PATH`, `attachImage()` = `upload.file` type + behavior, `deleteRecord()`), `App\Storage\ImageProcessor` (GD: real type via `finfo`, 2 MB, 6000 px; 256×256 cropped WebP, EXIF orientation, no metadata), `App\UseCase\Users\ChangeUserPhoto` (permissions via `canManage`, deletes the previous one, `photo_changed`/`photo_removed` activity with actor), `UserPhotoController` (update/delete/show; system rule for every role), `UserPhotoHelper` (URL with `?v=`), card on profile and edit pages, photo on the user page, list, dashboard and sidebar. Migration `20261119090000_AddPhotoToUsers` (`photo`, `photo_dir`, not mass-assignable).
- Docker: `exif` extension; `storage/uploads` owned by `www-data` in the image; `uploads` volume on `app` and `worker` (prod and Coolify; a single `app` replica while files live on a local volume).
- Verified: `UserPhotoTest` (7 tests) and end to end in dev (upload from the form → `users/<id>/photo/<uuid>.webp`, WebP served with private caching, CSP `img-src 'self'`). Documented in `docs/uploads.md`.
- Note: in parallel another session reworked the permissions matrix (per-role rules, migration `20261119000000_AddRuleToRolesPermissions`); its files were not touched. Full suite green at the end: 258 tests.

### Phase 22 — Performance review of migrations and relations ✅ (2026-11-19)
- Reviewed the actual PG indexes against the app's and CakeDC's queries. PostgreSQL doesn't index FKs on its own and CakeDC/Users doesn't create indexes on `username`/`email`.
- Migration `20261119100000_AddPerformanceIndexes` (`up`/`down`, rollback verified):
  - `users`: UNIQUE `username` and UNIQUE `email` (login, cookie, and preventing duplicates from races: `isUnique` only checks in PHP), `api_token` (Token authenticator `?api_key=`), `token` (email validation / reset), `created` (dashboard).
  - `account_activities`: `(user_id, created)` → `(user_id, id)` (the list orders by `id`); `actor_id` (without it, deleting a user scanned the whole table for the `SET NULL`).
  - `social_accounts` `user_id` and `(provider, reference)`; `failed_password_attempts` `(user_id, created)`; `roles_permissions` `permission_id`.
- `EXPLAIN` confirms the indexes are used. Full suite green: 261 tests.

## Pending / next steps

- [x] **Generic basecoat theme improvements** (2026-11-18, approved). Clone in `../cakephp-basecoat-theme`,
  branch `feat/admin-improvements`, TDD (83 green tests):
  - TableHelper: sort indicator (`chevrons-up-down` / `arrow-up` / `arrow-down`) + `aria-sort` on the
    active column; `sortIcons` config (false = plain links) and `classes.sort*`.
  - ButtonHelper: `collapse` option (`sm|md|lg|xl|true`) → icon-only below the breakpoint, sr-only label + `title`.
  - Layout: truncatable `flex-1` title and `shrink-0` actions.
  - FormHelper: `nestedCheckboxAndRadio` = false by default (plain checkbox, no "choice card");
    `nestedInput => true` for the card. `checkboxWrapper`/`radioWrapper` with `{{input}}{{label}}`
    (fixes non-nested radios/multicheckbox).
  - Theme README updated.
  - [x] Published straight to the theme's `main` (commit `419a5a5`) and the app updated with
    `composer update delamux/cakephp-basecoat-theme` (lock at `419a5a5`).
- [x] **Role management from the CRUD** (2026-11-18, TDD + a quick `/grill:roast` beforehand):
  - `App\Service\UserRoleManager`: whitelisted roles (`App.roles` in `config/users.php`), admins/superusers only,
    nobody changes their own role (there's always ≥1 admin: the one acting), only superusers manage superusers,
    nobody deletes their own account, "change another user's password" same as CakeDC (`Users.Superuser.allowedToChangePasswords`).
  - `POST /user-roles/{id}` (`UserRolesController`, route `user-roles:edit`, uuid id → 404 otherwise); `is_superuser` never from the UI.
  - Guards in `AppController::beforeFilter` for CakeDC's CRUD `edit`/`delete` (they also apply to superusers,
    who bypass the RBAC through the `Rbac|Superuser` policy).
  - UI: "Role" card on the edit page; edit/delete/change password only if allowed (list, user page, edit).
  - Roast findings closed: (1) an admin could change a superuser's email → takeover via reset; (2) deleting
    yourself / deleting superusers; (3) broken "Change password" link for other users.
  - QA caught an uncovered bug: route with `setMethods` not found by reverse routing → URL test + 404 for invalid ids.
- [x] Session-expired message: `App\Middleware\UnauthorizedHandler\AppRedirectHandler` → without a session "Please sign in to continue.", with a session but no permission "Access denied".
- [x] **Default roles `superadmin`, `admin`, `editor`, `user`** (2026-11-18, TDD):
  | Role | Can |
  | --- | --- |
  | superadmin | Everything; the only one who manages superadmins and grants/revokes superadmin. Synced with CakeDC's `is_superuser` |
  | admin | Users and roles (not superadmins, can't grant superadmin), reports |
  | editor | Read-only: users list/page, PDF report, dashboard statistics |
  | user | Dashboard and their profile |
  - `App\Model\Table\UsersTable` (via `Users.table = 'Users'`, CakeDC maps its aliases) syncs role ⇄ flag in `beforeSave` (covers the CRUD, role changes and `bin/cake users add_superuser`).
  - Migration `20261118120000_SuperadminRole`: existing superusers → `superadmin` role.
  - RBAC (`config/permissions.php`): superadmin/admin everything; editor `Users::index/view` + `Reports::users`.
  - UI: role selector with `assignableRoles()` (admins don't see "Superadmin"), unified role badge (`element/Users/role_badge`), actions only if allowed.
- [x] **Quick roast of the roles**: the session stored the full entity → a demoted/deleted/deactivated admin kept access until signing out. Now `AppController::refreshIdentity()` re-reads the user on every request (1 query by PK): it refreshes the identity or ends the session ("Your session has ended"). Later replaced by `App\Middleware\RefreshIdentityMiddleware` (via `Users.middlewareQueueLoader` → `App\Loader\MiddlewareQueueLoader`), which runs between Authentication and Authorization: the RBAC sees the current role from the very first request.
- [x] Spanish i18n (2026-11-18): `LocaleSelectorMiddleware(['en_US','es_ES'])` picks based on `Accept-Language` (default `App.defaultLocale`). `resources/locales/default.pot` (227 strings, `bin/cake i18n extract --paths src,templates --output resources/locales`) + `es_ES/default.po` and `es_ES/cake.po`. CakeDC/Users and the theme ship their own `es`. When adding strings: re-extract and fill in the `.po` (then `bin/cake cache clear_all`).
- [x] Production (2026-11-18): see Phase 12.
- [x] `composer self-update` on the host (2026-11-18, done by the user): Composer 2.11.2, `composer diagnose` OK with PHP 8.5.12.

## Testing (TDD)

- **TDD only for the project's own functionality / business rules** (red → green → refactor).
- **Don't** test templates or what the framework/plugins already test.
- Covered: `DashboardStatsService`, `SidebarMenu`, `CssInliner`, `UsersMailer` (inline + multipart + layout), access rules + PDF (`AccessRulesTest`).
- `docker compose exec app vendor/bin/phpunit` (database `cakephp_admin_test` on PG18).
- The `UsersFixture` fixture uses `tableAlias = 'CakeDC/Users.Users'` because the app disables fallback Tables.

## Decisions / notes

- CakeDC/Users loads Authentication + Authorization by itself. `Rbac|Superuser` policy.
- CakeDC templates carry a plugin prefix → the email layout is resolved in the plugin first; `templates/plugin/CakeDC/Users/layout/email/*` include the app's layout (single source).
- CSS inlining happens in `render()` before `Message::setBody()` so line wrapping doesn't split attributes/URLs.
- The skeleton's `PagesControllerTest` and `templates/Pages/home.php` (CakePHP welcome page) were removed: `/` is the dashboard and everything requires login.

## Log

- 2026-11-18: Phases 0–8 completed. Tests 26/26, phpcs and phpstan level 8 green.
