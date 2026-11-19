# Configuration

In production everything is set through **environment variables** (`config/app_local.php` isn't included in the
image). In dev, `compose.yaml` already defines them.

## Environment variables

| Variable | Default | Purpose |
| --- | --- | --- |
| `SECURITY_SALT` | — (**required** in production) | Security salt (`openssl rand -hex 32`). Don't change it later: it invalidates sessions and cookies |
| `APP_FULL_BASE_URL` | `https://cakeadmin.local` | Public URL: email links and the **WebAuthn domain** (keys are bound to it) |
| `APP_NAME` | `CakePHP Admin` | Name used as the TOTP issuer and in WebAuthn |
| `DATABASE_URL` | compose PostgreSQL | `postgres://user:password@host:5432/db` |
| `EMAIL_TRANSPORT_DEFAULT_URL` | compose Mailpit | Real SMTP: `smtp://user:password@smtp.example.com:587?tls=true` |
| `EMAIL_FROM`, `EMAIL_FROM_NAME` | `admin@cakephp-admin.test`, `CakePHP Admin` | Sender |
| `EMAIL_QUEUE` | `true` | `false`: emails are sent during the request (no worker) |
| `QUEUE_WORKER_LIFETIME` | `600` | Seconds before the worker is recycled |
| `QUEUE_SLEEPTIME` | `5` | Worker wait time when there's no work |
| `UPLOADS_PATH` | `storage/uploads/` | Private root for uploaded files (in production, the `uploads` volume) |
| `COOKIE_KEY` | derived from the salt | AES key for the "remember me" cookie (≥ 32 characters) |
| `HTTPS_ENFORCE` | `true` in the production image | HTTP → HTTPS + HSTS |
| `HSTS_MAX_AGE`, `HSTS_INCLUDE_SUBDOMAINS` | `31536000`, `false` | HSTS |
| `CSP_REPORT_ONLY` | `false` in production (`DEBUG` in dev) | Report-only CSP |
| `TRUSTED_PROXIES` | empty (Coolify: private ranges) | Proxy IPs or CIDR ranges, comma-separated |
| `SESSION_TIMEOUT` | `240` | Idle minutes |
| `TWO_FACTOR_ENABLED` | `true` | Global switch for 2FA and passkeys |
| `DEBUG` | `false` | Never `true` in production |
| `APP_DEFAULT_LOCALE`, `APP_DEFAULT_TIMEZONE` | `en_US`, `UTC` | Default language (the browser picks between `en_US` and `es_ES`) and time zone |
| `THEME_COMPILED_ASSETS`, `ASSET_TIMESTAMP`, `LOG_TO_STDERR` | set in the image | Self-hosted assets, cache busting, logs to the container output |
| `APP_PORT`, `HTTPS_PORT`, `DB_PORT`, `MAILPIT_UI_PORT`, `MAILPIT_SMTP_PORT` | 8080, 443, 5433, 8026, 1026 | Dev ports (`compose.prod.yaml` publishes HTTPS on 8445 by default) |

In local dev, set `HTTPS_PORT=443` in a `.env` file at the project root (ignored by git) so the app is at
`https://cakeadmin.local` without a port (443 is also the `compose.yaml` default; use another port in `.env` if 443 is
taken); see [Getting started](getting-started.md#1-local-https-and-domain-once-per-machine).

## App config keys

| Key | File | What |
| --- | --- | --- |
| `App.roles` | `config/users.php` | Labels for the initial roles |
| `App.permissions.{plugins,ignoredActions,defaults,superadminOnly,auditRetentionDays}` | `config/users.php` | [Permissions](permissions.md) |
| `App.accountActivity.retentionDays` | `config/users.php` | [Activity](account-activity.md) retention (180) |
| `Users.*` (sign-up, emails, "remember me", social) | `config/users.php` | CakeDC/Users |
| `OneTimePasswordAuthenticator`, `Webauthn2fa` | `config/users.php` | [2FA](two-factor.md) |
| `Security.{throttle,https,csp,cookieKey,trustedProxies}` | `config/app.php` | [Security](security.md) |
| `Queue.*` | `config/app.php` | [Queue](queue.md) |
| `EmailTransport.{default,queue}`, `Email.default` | `config/app.php` | [Emails](emails-and-pdf.md) |
| `Session` | `config/app.php` | Hardened database-backed session |
| `Uploads.path` | `config/app.php` | [File uploads](uploads.md) |
