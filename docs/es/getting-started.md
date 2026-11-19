# Primeros pasos (desarrollo)

[English](../en/getting-started.md) · **Español**

Requisitos: Docker (Docker Desktop, OrbStack o Colima) y [mkcert](https://github.com/FiloSottile/mkcert). PHP, Composer
y Node no hacen falta en el equipo: todo corre en contenedores.

## 1. HTTPS local y dominio (una vez por equipo)

WebAuthn (llaves de seguridad y passkeys) solo funciona con HTTPS y un dominio, así que en local la app corre en
`https://cakeadmin.local` con un certificado de mkcert. Estos tres pasos se hacen una sola vez.

**a) Apunta el dominio a tu equipo** en `/etc/hosts` (`sudo nano /etc/hosts`). Añade las dos líneas, IPv4 e IPv6:

```
127.0.0.1   cakeadmin.local
::1         cakeadmin.local
```

A diferencia de `*.localhost`, un dominio `.local` no se resuelve solo: sin estas líneas el navegador no encuentra la
app. Dar a cada proyecto su propio dominio (`cakeadmin.local`, `otraapp.local`…) en vez de `localhost` a secas
mantiene separadas sus cookies, sesiones y llaves WebAuthn. La línea `::1` importa: en macOS los nombres `.local`
también se buscan por mDNS (Bonjour) y, con solo la línea IPv4, cada petición puede quedarse varios segundos esperando
esa búsqueda.

**b) Puerto 443.** `compose.yaml` publica HTTPS en el 443 por defecto, así que la URL no lleva puerto. Solo un
contenedor puede ocupar el 443 a la vez: si otro proyecto ya lo usa, para ese o dale a este otro puerto en un archivo
`.env` en la raíz del proyecto (Docker Compose lo lee solo; git lo ignora):

```bash
echo 'HTTPS_PORT=8445' > .env      # → https://cakeadmin.local:8445
```

**c) Crea el certificado:**

```bash
brew install mkcert && mkcert -install
bin/dev-https-cert          # crea docker/certs/cakeadmin.local{,-key}.pem (fuera de git), dura ~2 años
```

> ¿Vienes del antiguo dominio `admindashboard.localhost`? Vuelve a ejecutar `bin/dev-https-cert` (cambiaron los
> nombres de los certificados), añade las líneas de `/etc/hosts` e inicia sesión de nuevo: las cookies y las llaves
> de seguridad estaban atadas al dominio anterior, así que vuelve a registrar las llaves o passkeys.

## 2. Servicios, esquema y primer usuario

```bash
docker compose up -d --build            # web (nginx), app (PHP-FPM), worker (cola), db (PostgreSQL 18), mailpit
docker compose exec app composer install
docker compose exec -u www-data app bin/deploy     # migraciones (CakeDC/Users, Queue, app) + sync de permisos
docker compose exec -u www-data app bin/cake users add_superuser -u admin -p 'Admin1234!' -e admin@cakephp-admin.test
docker compose exec -u www-data app bin/cake seeds run DemoUsers     # opcional: 40 usuarios de demo (contraseña Demo1234!)
```

Ejecuta los comandos de `bin/` como **`www-data`** (`-u www-data`), el mismo usuario que PHP-FPM. Como root crean
archivos de caché en `tmp/` y logs en `logs/` que luego la app no puede sobrescribir, y la app falla con errores de
permisos.

| Servicio | Dónde |
| --- | --- |
| App | https://cakeadmin.local · http://localhost:8080 |
| Mailpit (todos los emails de dev) | http://localhost:8026 |
| PostgreSQL | `localhost:5433`, usuario `cakephp` / `secret`; BD de tests `cakephp_admin_test` |
| Logs del worker | `docker compose logs -f worker` |

Puertos configurables con `APP_PORT`, `HTTPS_PORT`, `MAILPIT_UI_PORT`, `MAILPIT_SMTP_PORT` y `DB_PORT`.

## 3. Día a día

```bash
docker compose exec app composer check      # PHPUnit + CodeSniffer + PHPStan nivel 8 (lo mismo que el CI)
docker compose exec -u www-data app bin/cake permissions sync   # tras crear controladores o acciones
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
worker. Publica HTTPS en el 8445 por defecto (`https://cakeadmin.local:8445`), así que puede correr a la vez que el
stack de dev; un `HTTPS_PORT` en `.env` se aplica a los dos stacks.
