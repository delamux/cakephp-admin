# Getting started (development)

**English** · [Español](../es/getting-started.md)

Requirements: Docker (Docker Desktop, OrbStack or Colima) and [mkcert](https://github.com/FiloSottile/mkcert). You
don't need PHP, Composer or Node on your machine: everything runs in containers.

## 1. Local HTTPS and domain (once per machine)

WebAuthn (security keys and passkeys) only works over HTTPS on a domain, so locally the app runs at
`https://cakeadmin.local` with an mkcert certificate. Do these three steps once.

**a) Point the domain at your machine** in `/etc/hosts` (`sudo nano /etc/hosts`). Add both lines, IPv4 and IPv6:

```
127.0.0.1   cakeadmin.local
::1         cakeadmin.local
```

Unlike `*.localhost`, a `.local` domain doesn't resolve on its own: without these lines the browser can't find the
app. Giving each project its own domain (`cakeadmin.local`, `otherapp.local`…) instead of plain `localhost` keeps
their cookies, sessions and WebAuthn keys apart. The `::1` line matters: on macOS, `.local` names are also looked up
over mDNS (Bonjour), and with only the IPv4 line every request can stall for several seconds waiting for that lookup.

**b) Port 443.** `compose.yaml` publishes HTTPS on 443 by default, so the URL has no port. Only one container can
hold 443 at a time: if another project is already using it, stop that one or give this one another port in a `.env`
file in the project root (Docker Compose reads it automatically; it's ignored by git):

```bash
echo 'HTTPS_PORT=8445' > .env      # → https://cakeadmin.local:8445
```

**c) Create the certificate:**

```bash
brew install mkcert && mkcert -install
bin/dev-https-cert          # creates docker/certs/cakeadmin.local{,-key}.pem (outside git), lasts ~2 years
```

> Coming from the old `admindashboard.localhost` domain? Run `bin/dev-https-cert` again (the certificate file names
> changed), add the `/etc/hosts` lines and sign in again: cookies and security keys were tied to the old domain, so
> re-register any keys or passkeys.

## 2. Services, schema and first user

```bash
docker compose up -d --build            # web (nginx), app (PHP-FPM), worker (queue), db (PostgreSQL 18), mailpit
docker compose exec app composer install
docker compose exec -u www-data app bin/deploy     # migrations (CakeDC/Users, Queue, app) + permissions sync
docker compose exec -u www-data app bin/cake users add_superuser -u admin -p 'Admin1234!' -e admin@cakephp-admin.test
docker compose exec -u www-data app bin/cake seeds run DemoUsers     # optional: 40 demo users (password Demo1234!)
```

Run the `bin/` commands as **`www-data`** (`-u www-data`), the same user as PHP-FPM. As root they create cache files
in `tmp/` and logs in `logs/` that the app can't overwrite later, and the app fails with permission errors.

| Service | Where |
| --- | --- |
| App | https://cakeadmin.local · http://localhost:8080 |
| Mailpit (every email in dev) | http://localhost:8026 |
| PostgreSQL | `localhost:5433`, user `cakephp` / `secret`; test database `cakephp_admin_test` |
| Worker logs | `docker compose logs -f worker` |

Ports are configurable with `APP_PORT`, `HTTPS_PORT`, `MAILPIT_UI_PORT`, `MAILPIT_SMTP_PORT` and `DB_PORT`.

## 3. Day to day

```bash
docker compose exec app composer check      # PHPUnit + CodeSniffer + PHPStan level 8 (same as CI)
docker compose exec -u www-data app bin/cake permissions sync   # after creating controllers or actions
docker compose restart worker               # after changing code the worker uses (or wait for it to recycle)
```

- The code is mounted into the `app` and `worker` containers, and Tailwind is loaded with its browser build: nothing
  needs compiling in dev.
- Emails go out through the **queue**: if they don't reach Mailpit, check that the `worker` service is running
  (*System › Queue* warns you).
- **TDD** for the project's own business rules (not for templates or for what the framework already tests).
- **Translations:** when adding strings, run `bin/cake i18n extract --paths src,templates --output resources/locales
  --merge no --overwrite --extract-core no`, fill in `resources/locales/es_ES/default.po` and run
  `bin/cake cache clear_all`.
- **CI** (`.github/workflows/ci.yml`): PHPUnit against PostgreSQL 18, CodeSniffer, PHPStan and `composer audit` on
  every push to `main` and on pull requests. Dependabot updates Composer packages and actions.

## Trying the production stack locally

```bash
SECURITY_SALT=$(openssl rand -hex 32) docker compose -f compose.prod.yaml up -d --build
```

Real images (no mounted code, strict CSP, compiled assets), automatic migrations (`migrate` service) and the worker.
It publishes HTTPS on 8445 by default (`https://cakeadmin.local:8445`), so it can run next to the dev stack; a
`HTTPS_PORT` in `.env` applies to both stacks.
