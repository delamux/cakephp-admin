# Primeros pasos (desarrollo)

Requisitos: Docker (Docker Desktop, OrbStack o Colima) y [mkcert](https://github.com/FiloSottile/mkcert). PHP, Composer
y Node no hacen falta en el equipo: todo corre en contenedores.

## 1. HTTPS local (una vez por equipo)

WebAuthn (llaves y passkeys) solo funciona con HTTPS y un dominio, así que en local se usa
`https://admindashboard.localhost` con un certificado de mkcert:

```bash
brew install mkcert && mkcert -install
bin/dev-https-cert          # crea docker/certs/admindashboard.localhost{,-key}.pem (fuera de git), dura ~2 años
```

Para usarlo **sin puerto** junto a otros proyectos, el proxy compartido `~/code/local-https-proxy` (fuera de este
repositorio) ocupa 443/80 y reparte por dominio sin descifrar (SNI): `admindashboard.localhost` → 8445,
`filae.localhost` → 8444. Arráncalo una vez con `docker compose up -d` en su carpeta. Los `*.localhost` no necesitan
`/etc/hosts`. Sin el proxy, la app sigue en `https://admindashboard.localhost:8445`.

## 2. Servicios, esquema y primer usuario

```bash
docker compose up -d --build            # web (nginx), app (PHP-FPM), worker (cola), db (PostgreSQL 18), mailpit
docker compose exec app composer install
docker compose exec app bin/migrate     # CakeDC/Users + Queue + migraciones de la app, en ese orden
docker compose exec app bin/cake permissions sync
docker compose exec app bin/cake users add_superuser -u admin -p 'Admin1234!' -e admin@cakephp-admin.test
docker compose exec app bin/cake seeds run DemoUsers     # opcional: 40 usuarios de demo (contraseña Demo1234!)
```

| Servicio | Dónde |
| --- | --- |
| App | https://admindashboard.localhost (proxy) · https://admindashboard.localhost:8445 · http://localhost:8080 |
| Mailpit (todos los emails de dev) | http://localhost:8026 |
| PostgreSQL | `localhost:5433`, usuario `cakephp` / `secret`; BD de tests `cakephp_admin_test` |
| Logs del worker | `docker compose logs -f worker` |

Puertos configurables con `APP_PORT`, `HTTPS_PORT`, `MAILPIT_UI_PORT`, `MAILPIT_SMTP_PORT` y `DB_PORT`.

## 3. Día a día

```bash
docker compose exec app composer check      # PHPUnit + CodeSniffer + PHPStan nivel 8 (lo mismo que el CI)
docker compose exec app bin/cake permissions sync   # tras crear controladores o acciones
docker compose restart worker               # tras cambiar código que usa el worker (o espera a que se recicle)
```

- El código va montado en los contenedores `app` y `worker`; Tailwind se carga con su build de navegador: no hay que
  compilar nada en dev.
- Los emails salen por la **cola**: si no llegan a Mailpit, mira que el servicio `worker` esté levantado
  (*System › Queue* lo avisa).
- **TDD** para las reglas de negocio propias (no para plantillas ni para lo que ya prueba el framework).
- **Traducciones:** al añadir textos, `bin/cake i18n extract --paths src,templates --output resources/locales --merge no
  --overwrite --extract-core no`, completar `resources/locales/es_ES/default.po` y `bin/cake cache clear_all`.
- **CI** (`.github/workflows/ci.yml`): PHPUnit contra PostgreSQL 18, CodeSniffer, PHPStan y `composer audit` en cada
  push a `main` y en los pull requests. Dependabot actualiza Composer y las actions.

## Probar el stack de producción en local

```bash
SECURITY_SALT=$(openssl rand -hex 32) docker compose -f compose.prod.yaml up -d --build
```

Imágenes reales (sin código montado, CSP estricta, assets compilados), migraciones automáticas (servicio `migrate`) y
worker. Usa el mismo puerto que el dev: levanta uno cada vez (o `HTTPS_PORT=8446`).
