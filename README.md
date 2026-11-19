# CakePHP Admin Skeleton

Admin panel skeleton for kick-starting CakePHP projects: full login, users, roles and permissions managed from the UI
itself, two-step verification and passkeys, account activity, a job queue, dashboard, emails and PDF, the security
guidance from [book.cakephp.org/5.x/security](https://book.cakephp.org/5.x/security.html) applied, htmx, i18n and
Docker for development, production and **Coolify**.

**Full documentation: [docs/](docs/README.md)** · Plan, decisions and status of each phase: [ROADMAP.md](ROADMAP.md).

## What's included

- **Accounts** (CakeDC/Users): sign-in with username or email, sign-up with email verification, password reset,
  magic sign-in link, "remember me", profile, user CRUD. → [Accounts and roles](docs/accounts-and-roles.md)
- **Database-backed roles and permissions**: an action × role matrix with automatic controller discovery, optional
  per-permission rules (owner), locked superadmin-only actions and a change history. → [Permissions](docs/permissions.md)
- Optional **two-step verification** (TOTP app and/or security key) and **passkeys** for passwordless sign-in;
  single-use codes, email notifications, reset by a superadmin or from the console. → [2FA and passkeys](docs/two-factor.md)
- **Account activity**: sign-ins (including failed ones), sign-outs, password, 2FA and photo changes, with IP and
  browser, in the profile and on the user's page. → [Account activity](docs/account-activity.md)
- **Job queue** in PostgreSQL: **every email is sent in the background**, worker in Docker, a screen with failed jobs
  (retry/delete) and a base for your own tasks. → [Queue](docs/queue.md)
- **Private file uploads** with a single convention (`<table>/<id>/<field>/<uuid>.<ext>`): **user photo** validated by
  its content and re-encoded to WebP without EXIF. → [File uploads](docs/uploads.md)
- **Dashboard** with KPIs and a chart, a **PDF report** and highly compatible HTML **emails** (inline CSS, dark mode,
  plain-text version). → [Emails and PDF](docs/emails-and-pdf.md)
- **Security**: strict CSP with nonces, security headers, HTTPS + HSTS, CSRF and FormProtection, encrypted cookie,
  sessions and per-IP rate limiting in PostgreSQL, trusted proxies with CIDR ranges. → [Security](docs/security.md)
- CSP-compatible **htmx** (lists, matrix, 2FA without reloads). → [htmx](docs/htmx.md)
- **i18n** English and Spanish · **Quality**: TDD (≈260 tests), PHPStan level 8, CodeSniffer, `composer audit`, CI on
  GitHub Actions.

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
| Server | nginx + PHP-FPM + worker | 1.29 · 8.5 | Same multi-stage Dockerfile for dev, production and Coolify |
| Files | josegonzalez/cakephp-upload (FriendsOfCake) + Flysystem | 8.1 · 3 | Private uploads with a naming convention |
| Queue | dereuromark/cakephp-queue | 8.18 | Emails and background jobs in PostgreSQL (no Redis) |
| Mail in dev | Mailpit | — | Local inbox at http://localhost:8026 |
| Local HTTPS | mkcert + shared SNI proxy | — | `https://admindashboard.localhost` without a port (WebAuthn requires HTTPS) |
| Production assets | Tailwind CLI + basecoat-css, Chart.js, htmx, Geist (Docker Node stage) | — | Everything self-hosted: no requests to CDNs |
| Quality | PHPUnit 13 · PHPStan 2 (level 8) · CakePHP CodeSniffer | — | `composer check`; CI on GitHub Actions |

## Getting started

```bash
brew install mkcert && mkcert -install && bin/dev-https-cert     # local HTTPS (WebAuthn requires it)
docker compose up -d --build                                       # web, app, worker, db, mailpit
docker compose exec app composer install
docker compose exec app bin/deploy                                 # migrations + permissions catalogue
docker compose exec app bin/cake users add_superuser -u admin -p 'Admin1234!' -e admin@cakephp-admin.test
```

App at https://admindashboard.localhost · emails at http://localhost:8026. Details in
[Getting started](docs/getting-started.md).

## Production

- **Coolify:** `compose.coolify.yaml` (PostgreSQL as a Coolify resource, TLS at Traefik, worker and automatic
  migrations on every deploy). → [Deploying to Coolify](docs/deploy-coolify.md)
- **Always running:** the `worker` service and **one daily cron: `bin/cake maintenance`**. Which `src/Command`
  commands must be scheduled and which must not → [Operations and crons](docs/operations.md#comandos-de-la-app-srccommand-y-cuándo-se-ejecutan)
- Environment variables → [Configuration](docs/configuration.md).

## Documentation

The guides in `docs/` are written in Spanish.

| Guide | |
| --- | --- |
| [Getting started](docs/getting-started.md) | Local setup, day-to-day work, production stack locally |
| [Architecture](docs/architecture.md) | Layers, where things live, how to add a module |
| [Accounts, users and roles](docs/accounts-and-roles.md) · [Permissions](docs/permissions.md) | Users, roles, database RBAC |
| [2FA and passkeys](docs/two-factor.md) · [Account activity](docs/account-activity.md) | Account security |
| [Queue](docs/queue.md) · [Emails and PDF](docs/emails-and-pdf.md) · [File uploads](docs/uploads.md) | Background jobs, mail, reports, files |
| [Security](docs/security.md) · [htmx](docs/htmx.md) | Measures applied and rules for writing templates |
| [Configuration](docs/configuration.md) · [Coolify](docs/deploy-coolify.md) · [Operations](docs/operations.md) | Deploying and maintaining |
