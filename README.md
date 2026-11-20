# CakePHP Admin Skeleton

**English** · [Español](README-es.md)

Admin panel skeleton for kick-starting CakePHP projects: full login, users, roles and permissions managed from the UI
itself, two-step verification and passkeys, account activity, a job queue, dashboard, emails and PDF, the security
guidance from [book.cakephp.org/5.x/security](https://book.cakephp.org/5.x/security.html) applied, htmx, i18n and
Docker for development, production and **Coolify**.

**Full documentation: [docs/en/](docs/en/README.md)** ([en español](docs/es/README.md)) · Plan, decisions and status of each phase: [ROADMAP.md](ROADMAP.md).

## What's included

- **Accounts** (CakeDC/Users): sign-in with username or email, sign-up with email verification, password reset,
  magic sign-in link, "remember me", profile, user CRUD, **email invitations** and **where you are signed in** (sign
  the other devices out). → [Accounts and roles](docs/en/accounts-and-roles.md)
- **Database-backed roles and permissions**: an action × role matrix with automatic controller discovery, optional
  per-permission rules (owner), locked superadmin-only actions and a change history. → [Permissions](docs/en/permissions.md)
- Optional **two-step verification** (TOTP app and/or security key) and **passkeys** for passwordless sign-in;
  single-use codes, email notifications, reset by a superadmin or from the console. → [2FA and passkeys](docs/en/two-factor.md)
- **Account activity**: sign-ins (including failed ones), sign-outs, password, 2FA and photo changes, with IP and
  browser, in the profile and on the user's page. → [Account activity](docs/en/account-activity.md)
- **Job queue** in PostgreSQL: **every email is sent in the background**, worker in Docker, a screen with failed jobs
  (retry/delete) and a base for your own tasks. → [Queue](docs/en/queue.md)
- **Private file uploads** with a single convention (`<table>/<id>/<field>/<uuid>.<ext>`): **user photo** validated by
  its content and re-encoded to WebP without EXIF. → [File uploads](docs/en/uploads.md)
- **Dashboard** with KPIs and a chart, a **PDF report** and highly compatible HTML **emails** (inline CSS, dark mode,
  plain-text version). → [Emails and PDF](docs/en/emails-and-pdf.md)
- **Security**: strict CSP with nonces, security headers, HTTPS + HSTS, CSRF and FormProtection, encrypted cookie,
  sessions and per-IP rate limiting in PostgreSQL, trusted proxies with CIDR ranges. → [Security](docs/en/security.md)
- CSP-compatible **htmx** (lists, matrix, 2FA without reloads). → [htmx](docs/en/htmx.md)
- **CRUD generator**: `bin/cake bake all <Model>` writes the screens with the theme components (list with
  **search, filters and CSV export**, detail, form, delete in a dialog, CSP-safe). → [Architecture](docs/en/architecture.md)
- **i18n** English and Spanish · **Quality**: TDD (≈260 tests), PHPStan level 8, CodeSniffer, `composer audit`, CI on
  GitHub Actions.

## Stack

| Layer | Technology | Version | Why |
| --- | --- | --- | --- |
| Language | PHP | 8.5 | Project requirement |
| Framework | CakePHP | 5.4 | Conventions, ORM, DI container, security middlewares |
| Users | CakeDC/Users (fork `delamux/CakeDC-users`, branch `issue/4-webauthn-lib-5-cake17`) | 17.0.0 + webauthn-lib 5 | Login, sign-up, 2FA; the fork makes it compatible with webauthn-lib 5 / PHP 8.5 |
| Authorization | CakeDC/Auth RBAC + custom database provider · cakephp/authentication | 13.0 · 4.2 | Per-role rules editable in the UI |
| 2FA / passkeys | robthree/twofactorauth · web-auth/webauthn-lib · endroid/qr-code | 3 · 5.3 · 6.2 | TOTP, WebAuthn, server-side QR |
| UI | [delamux/cakephp-basecoat-theme](https://github.com/delamux/cakephp-basecoat-theme) (Basecoat + Tailwind v4) | main | shadcn-style components, light/dark, no inline JS |
| Interaction | htmx | 2.0.11 | Fragments without reloads, no build step or JS framework |
| CSP | paragonie/csp-builder (CakePHP's CspMiddleware) | 3.1 | Content-Security-Policy with nonces |
| PDF | friendsofcake/cakepdf + Dompdf | — | Pure-PHP PDF, no binaries |
| Emails | pelago/emogrifier | — | Inline CSS for email clients |
| Database | PostgreSQL | 18 | |
| Server | nginx + PHP-FPM + worker | 1.29 · 8.5 | Same multi-stage Dockerfile for dev, production and Coolify |
| Files | josegonzalez/cakephp-upload (FriendsOfCake) + Flysystem | 8.1 · 3 | Private uploads with a naming convention |
| Queue | dereuromark/cakephp-queue | 8.18 | Emails and background jobs in PostgreSQL (no Redis) |
| Mail in dev | Mailpit | — | Local inbox at http://localhost:8026 |
| Local HTTPS | mkcert + `/etc/hosts` | — | `https://cakeadmin.local` without a port (WebAuthn requires HTTPS) |
| Production assets | Tailwind CLI + basecoat-css, Chart.js, htmx, Geist (Docker Node stage) | — | Everything self-hosted: no requests to CDNs |
| Quality | PHPUnit 13 · PHPStan 2 (level 8) · CakePHP CodeSniffer | — | `composer check`; CI on GitHub Actions |

## Getting started

Once per machine, add the domain to `/etc/hosts` (IPv4 and IPv6); HTTPS is published on port 443:

```
127.0.0.1   cakeadmin.local
::1         cakeadmin.local
```

```bash
brew install mkcert && mkcert -install && bin/dev-https-cert     # local HTTPS (WebAuthn requires it)
docker compose up -d --build                                       # web, app, worker, db, mailpit
docker compose exec app composer install
docker compose exec -u www-data app bin/deploy                     # migrations + permissions catalogue
docker compose exec -u www-data app bin/cake users add_superuser -u admin -p 'Admin1234!' -e admin@cakephp-admin.test
```

App at https://cakeadmin.local · emails at http://localhost:8026. Details in
[Getting started](docs/en/getting-started.md).

## Production

- **Coolify:** `compose.coolify.yaml` (PostgreSQL as a Coolify resource, TLS at Traefik, worker and automatic
  migrations on every deploy). → [Deploying to Coolify](docs/en/deploy-coolify.md)
- **Always running:** the `worker` service and **one daily cron: `bin/cake maintenance`**. Which `src/Command`
  commands must be scheduled and which must not → [Operations and crons](docs/en/operations.md#app-commands-srccommand-and-when-they-run)
- Environment variables → [Configuration](docs/en/configuration.md).

## Documentation

The guides in `docs/en/` (Spanish versions in [`docs/es/`](docs/es/README.md)):

| Guide | |
| --- | --- |
| [Getting started](docs/en/getting-started.md) | Local setup, day-to-day work, production stack locally |
| [Architecture](docs/en/architecture.md) | Layers, where things live, how to add a module |
| [Accounts, users and roles](docs/en/accounts-and-roles.md) · [Permissions](docs/en/permissions.md) | Users, roles, database RBAC |
| [2FA and passkeys](docs/en/two-factor.md) · [Account activity](docs/en/account-activity.md) | Account security |
| [Queue](docs/en/queue.md) · [Emails and PDF](docs/en/emails-and-pdf.md) · [File uploads](docs/en/uploads.md) | Background jobs, mail, reports, files |
| [Security](docs/en/security.md) · [htmx](docs/en/htmx.md) | Measures applied and rules for writing templates |
| [Configuration](docs/en/configuration.md) · [Coolify](docs/en/deploy-coolify.md) · [Operations](docs/en/operations.md) | Deploying and maintaining |
