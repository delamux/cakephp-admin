# CakePHP Admin Skeleton

[English](README.md) · **Español**

Esqueleto de panel de administración para arrancar proyectos con CakePHP: login completo, usuarios, roles y permisos
gestionados desde la propia UI, verificación en dos pasos y passkeys, actividad de la cuenta, cola de trabajos,
dashboard, emails y PDF, la seguridad de [book.cakephp.org/5.x/security](https://book.cakephp.org/5.x/security.html)
aplicada, htmx, i18n y Docker para desarrollo, producción y **Coolify**.

**Documentación completa: [docs/es/](docs/es/README.md)** ([in English](docs/en/README.md)) · Plan, decisiones y
estado de cada fase: [ROADMAP.md](ROADMAP.md) (en inglés).

## Qué incluye

- **Cuentas** (CakeDC/Users): acceso con usuario o email, registro con validación por email, reset de contraseña,
  enlace mágico de acceso, «recordarme», perfil, CRUD de usuarios, **invitaciones por email** y **dónde tienes la sesión
  abierta** (cerrar los demás dispositivos). → [Cuentas y roles](docs/es/accounts-and-roles.md)
- **Roles y permisos en base de datos**: matriz acción × rol con descubrimiento automático de controladores, reglas
  opcionales por permiso (propietario), acciones solo-superadmin bloqueadas e histórico de cambios. → [Permisos](docs/es/permissions.md)
- **Verificación en dos pasos** opcional (app TOTP y/o llave de seguridad) y **passkeys** para entrar sin contraseña;
  códigos de un solo uso, avisos por email, reset por superadmin o por consola. → [2FA y passkeys](docs/es/two-factor.md)
- **Actividad de la cuenta**: accesos (también fallidos), cierres, cambios de contraseña, 2FA y foto, con IP y
  navegador, en el perfil y en la ficha del usuario. → [Actividad de la cuenta](docs/es/account-activity.md)
- **Cola de trabajos** en PostgreSQL: **todos los emails salen en segundo plano**, worker en Docker, pantalla con los
  fallidos (reintentar/borrar) y base para tus propias tareas. → [Cola](docs/es/queue.md)
- **Subida de archivos privada** con una única convención (`<tabla>/<id>/<campo>/<uuid>.<ext>`): **foto de usuario**
  validada por su contenido y reconvertida a WebP sin EXIF. → [Subida de archivos](docs/es/uploads.md)
- **Dashboard** con KPIs y gráfico, **informe en PDF** y **emails** HTML de alta compatibilidad (CSS en línea, modo
  oscuro, versión de texto). → [Emails y PDF](docs/es/emails-and-pdf.md)
- **Seguridad**: CSP estricta con nonces, cabeceras de seguridad, HTTPS + HSTS, CSRF y FormProtection, cookie cifrada,
  sesiones y límite por IP en PostgreSQL, proxies de confianza con rangos CIDR. → [Seguridad](docs/es/security.md)
- **htmx** compatible con la CSP (listados, matriz, 2FA sin recargar). → [htmx](docs/es/htmx.md)
- **Generador de CRUD**: `bin/cake bake all <Modelo>` escribe las pantallas con los componentes del tema (listado,
  ficha, formulario, borrado con diálogo, compatible con la CSP). → [Arquitectura](docs/es/architecture.md)
- **i18n** inglés y español · **Calidad**: TDD (≈260 tests), PHPStan nivel 8, CodeSniffer, `composer audit`, CI en
  GitHub Actions.

## Stack

| Capa | Tecnología | Versión | Por qué |
| --- | --- | --- | --- |
| Lenguaje | PHP | 8.5 | Requisito del proyecto |
| Framework | CakePHP | 5.4 | Convenciones, ORM, contenedor DI, middlewares de seguridad |
| Usuarios | CakeDC/Users (fork `delamux/CakeDC-users`, rama `issue/4-webauthn-lib-5`) | 16.0.5 + webauthn-lib 5 | Login, registro, 2FA; el fork lo hace compatible con webauthn-lib 5 / PHP 8.5 |
| Autorización | CakeDC/Auth RBAC + proveedor propio en BD | 10.1 | Reglas por rol editables en la UI |
| 2FA / passkeys | robthree/twofactorauth · web-auth/webauthn-lib · endroid/qr-code | 3 · 5.3 · 6.2 | TOTP, WebAuthn, QR en servidor |
| UI | [delamux/cakephp-basecoat-theme](https://github.com/delamux/cakephp-basecoat-theme) (Basecoat + Tailwind v4) | main | Componentes estilo shadcn, light/dark, sin JS en línea |
| Interacción | htmx | 2.0.11 | Fragmentos sin recargar, sin build ni framework JS |
| CSP | paragonie/csp-builder (CspMiddleware de CakePHP) | 3.1 | Content-Security-Policy con nonces |
| PDF | friendsofcake/cakepdf + Dompdf | — | PDF en PHP puro, sin binarios |
| Emails | pelago/emogrifier | — | CSS en línea para clientes de correo |
| Base de datos | PostgreSQL | 18 | |
| Servidor | nginx + PHP-FPM + worker | 1.29 · 8.5 | Mismo Dockerfile multi-stage para dev, producción y Coolify |
| Archivos | josegonzalez/cakephp-upload (FriendsOfCake) + Flysystem | 8.1 · 3 | Subidas privadas con convención de nombres |
| Cola | dereuromark/cakephp-queue | 8.18 | Emails y trabajos en segundo plano en PostgreSQL (sin Redis) |
| Correo en dev | Mailpit | — | Bandeja local en http://localhost:8026 |
| HTTPS local | mkcert + `/etc/hosts` | — | `https://cakeadmin.local` sin puerto (WebAuthn exige HTTPS) |
| Assets de producción | Tailwind CLI + basecoat-css, Chart.js, htmx, Geist (etapa Node de Docker) | — | Todo autoalojado: ninguna petición a CDNs |
| Calidad | PHPUnit 13 · PHPStan 2 (nivel 8) · CakePHP CodeSniffer | — | `composer check`; CI en GitHub Actions |

## Arranque

Una vez por equipo, añade el dominio a `/etc/hosts` (IPv4 e IPv6); HTTPS se publica en el puerto 443:

```
127.0.0.1   cakeadmin.local
::1         cakeadmin.local
```

```bash
brew install mkcert && mkcert -install && bin/dev-https-cert     # HTTPS local (WebAuthn lo exige)
docker compose up -d --build                                       # web, app, worker, db, mailpit
docker compose exec app composer install
docker compose exec -u www-data app bin/deploy                     # migraciones + catálogo de permisos
docker compose exec -u www-data app bin/cake users add_superuser -u admin -p 'Admin1234!' -e admin@cakephp-admin.test
```

App en https://cakeadmin.local · emails en http://localhost:8026. Detalles en
[Primeros pasos](docs/es/getting-started.md).

## Producción

- **Coolify:** `compose.coolify.yaml` (PostgreSQL como recurso de Coolify, TLS en Traefik, worker y migraciones
  automáticas en cada deploy). → [Despliegue en Coolify](docs/es/deploy-coolify.md)
- **Siempre en marcha:** el servicio `worker` y **un único cron diario: `bin/cake maintenance`**. Qué comandos de
  `src/Command` hay que programar y cuáles no → [Operación y crons](docs/es/operations.md#comandos-de-la-app-srccommand-y-cuándo-se-ejecutan)
- Variables de entorno → [Configuración](docs/es/configuration.md).

## Documentación

Las guías de `docs/es/` (versión en inglés en [`docs/en/`](docs/en/README.md)):

| Guía | |
| --- | --- |
| [Primeros pasos](docs/es/getting-started.md) | Arranque en local, día a día, stack de producción en local |
| [Arquitectura](docs/es/architecture.md) | Capas, dónde está cada cosa, cómo añadir un módulo |
| [Cuentas, usuarios y roles](docs/es/accounts-and-roles.md) · [Permisos](docs/es/permissions.md) | Usuarios, roles, RBAC en BD |
| [2FA y passkeys](docs/es/two-factor.md) · [Actividad de la cuenta](docs/es/account-activity.md) | Seguridad de las cuentas |
| [Cola](docs/es/queue.md) · [Emails y PDF](docs/es/emails-and-pdf.md) · [Subida de archivos](docs/es/uploads.md) | Trabajos en segundo plano, correo, informes, archivos |
| [Seguridad](docs/es/security.md) · [htmx](docs/es/htmx.md) | Medidas aplicadas y reglas para escribir plantillas |
| [Configuración](docs/es/configuration.md) · [Coolify](docs/es/deploy-coolify.md) · [Operación](docs/es/operations.md) | Desplegar y mantener |
