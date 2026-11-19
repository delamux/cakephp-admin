# Documentation

**English** · [Español](../es/README.md)

Everything the app does, how to use it, how to run it and how to extend it. The [README](../../README.md) is the
summary; the decisions and the status of each phase are in the [ROADMAP](../../ROADMAP.md).

| Guide | What it covers |
| --- | --- |
| [Getting started](getting-started.md) | Running locally: HTTPS with mkcert, Docker, migrations, superadmin, demo data |
| [Architecture](architecture.md) | Layers (DDD-lite), where everything lives and **how to add a new module** |
| [Accounts, users and roles](accounts-and-roles.md) | Login, sign-up, reset, magic link, "remember me", profile, user CRUD, roles |
| [Permissions](permissions.md) | Database-backed RBAC: action discovery, matrix, system rules, history |
| [Two-step verification and passkeys](two-factor.md) | TOTP, security keys, passkeys, reset, single-use codes |
| [Account activity](account-activity.md) | What is recorded for each account, who can see it and how long it is kept |
| [Job queue](queue.md) | Background emails, the worker, the *System › Queue* screen and **how to create your own tasks** |
| [File uploads](uploads.md) | `<table>/<id>/<field>/<uuid>.<ext>` convention, private storage, user photo, how to add files to another entity |
| [Emails and PDF](emails-and-pdf.md) | Compatible email layout, inline CSS, PDF report |
| [Security](security.md) | CSP, headers, HTTPS, CSRF, sessions, per-IP rate limits, proxies; **rules for writing templates** |
| [htmx](htmx.md) | CSP-compatible fragments without page reloads |
| [Configuration](configuration.md) | Every environment variable and config key |
| [Deploying on Coolify](deploy-coolify.md) | Step by step: database, domain, SMTP, worker, cron |
| [Operations and crons](operations.md) | **The crons you must always run**, commands, logs, backups, updates |
