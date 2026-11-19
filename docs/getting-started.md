# Getting started (development)

Requirements: Docker (Docker Desktop, OrbStack or Colima) and [mkcert](https://github.com/FiloSottile/mkcert). You
don't need PHP, Composer or Node on your machine: everything runs in containers.

## 1. Local HTTPS (once per machine)

WebAuthn (security keys and passkeys) only works over HTTPS on a domain, so locally the app uses
`https://admindashboard.localhost` with an mkcert certificate:

```bash
brew install mkcert && mkcert -install
bin/dev-https-cert          # creates docker/certs/admindashboard.localhost{,-key}.pem (outside git), lasts ~2 years
```

To use it **without a port** alongside other projects, the shared proxy `~/code/local-https-proxy` (outside this
repository) listens on 443/80 and routes by domain without decrypting (SNI): `admindashboard.localhost` → 8445,
`filae.localhost` → 8444. Start it once with `docker compose up -d` in its folder. `*.localhost` domains don't need
`/etc/hosts`. Without the proxy, the app is still at `https://admindashboard.localhost:8445`.

## 2. Services, schema and first user

```bash
docker compose up -d --build            # web (nginx), app (PHP-FPM), worker (queue), db (PostgreSQL 18), mailpit
docker compose exec app composer install
docker compose exec app bin/migrate     # CakeDC/Users + Queue + app migrations, in that order
docker compose exec app bin/cake permissions sync
docker compose exec app bin/cake users add_superuser -u admin -p 'Admin1234!' -e admin@cakephp-admin.test
docker compose exec app bin/cake seeds run DemoUsers     # optional: 40 demo users (password Demo1234!)
```

| Service | Where |
| --- | --- |
| App | https://admindashboard.localhost (proxy) · https://admindashboard.localhost:8445 · http://localhost:8080 |
| Mailpit (every email in dev) | http://localhost:8026 |
| PostgreSQL | `localhost:5433`, user `cakephp` / `secret`; test database `cakephp_admin_test` |
| Worker logs | `docker compose logs -f worker` |

Ports are configurable with `APP_PORT`, `HTTPS_PORT`, `MAILPIT_UI_PORT`, `MAILPIT_SMTP_PORT` and `DB_PORT`.

## 3. Day to day

```bash
docker compose exec app composer check      # PHPUnit + CodeSniffer + PHPStan level 8 (same as CI)
docker compose exec app bin/cake permissions sync   # after creating controllers or actions
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
It uses the same port as dev: run one at a time (or set `HTTPS_PORT=8446`).
