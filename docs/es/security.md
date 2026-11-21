# Seguridad

[English](../en/security.md) · **Español**

Aplicado según [book.cakephp.org/5.x/security](https://book.cakephp.org/5.x/security.html)
(`App\Http\SecurityMiddlewares`, `config/app.php` → `Security.*`). `SecurityTest` lo comprueba sobre respuestas reales.

| Medida | Cómo |
| --- | --- |
| **Content-Security-Policy** | `CspMiddleware` con nonces de script y estilo; nada en línea (sin `'unsafe-inline'`/`'unsafe-eval'`), `object-src 'none'`, `frame-ancestors`/`form-action`/`base-uri 'self'`. **Estricta en producción**; Report-Only en dev (el build de navegador de Tailwind inyecta estilos sin nonce). En producción no hay ninguna petición a CDNs |
| Cabeceras | `nosniff`, `X-Frame-Options: sameorigin`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-Download-Options`, `Permissions-Policy` (cámara, micro, geolocalización, pagos, USB desactivados; WebAuthn permitido) |
| HTTPS + HSTS | `HTTPS_ENFORCE=true` (imagen de producción): HTTP → HTTPS y `Strict-Transport-Security` de 1 año (`HSTS_MAX_AGE`, `HSTS_INCLUDE_SUBDOMAINS`) |
| CSRF | Cookie `httponly`, `SameSite=Lax` y `secure` en HTTPS; `fetch`/htmx envían `X-CSRF-Token` desde `<meta name="csrf-token">` |
| FormProtection | En todos los controladores: los formularios de `FormHelper` llevan un token de sus campos (solo se desbloquean los endpoints JSON de WebAuthn y las casillas de la matriz) |
| Cookie «recordarme» | Cifrada con AES (`EncryptedCookieMiddleware`, `COOKIE_KEY` o derivada de la sal), `secure`, `SameSite=Lax`. Su token incluye el hash de la contraseña y `users.session_key`: cambiar la contraseña o «Sign out of every other session» la invalida. Dura 1 mes |
| `?api_key=` | El autenticador Token de CakeDC (entrar con `users.api_token` en cualquier URL) está **desactivado** (`Auth.Authenticators.Token => false`): la app no tiene API |
| Sesión | En **PostgreSQL** (tabla `sessions`); `httponly`, `SameSite=Lax`, `use_strict_mode`, `secure` en HTTPS, caducidad por inactividad (`SESSION_TIMEOUT`, 60 min) y **vida máxima** (`SESSION_MAX_LIFETIME`, 12 h). Sobrevive a los redeploys y la comparten todas las réplicas. Cada fila guarda su usuario, IP y navegador (`AppDatabaseSession`) para *Dónde tienes la sesión abierta* |
| Cerrar sesión en todas partes | Cambiar o restablecer la contraseña cierra **las demás sesiones** de la cuenta (y sus cookies «recordarme»); quien la cambia desde su perfil sigue dentro. Ver [Sesiones](#sesiones) |
| Confirmar contraseña | Las acciones sensibles piden la contraseña otra vez si no se escribió en los últimos 15 min (`SESSION_CONFIRM_MINUTES`). Ver [Sesiones](#sesiones) |
| Límite de intentos por IP | En **PostgreSQL** (`auth_throttles`, IP con hash SHA-256, upsert atómico): 20 accesos / códigos 2FA / llaves de seguridad / passkeys / confirmaciones de contraseña fallidos cada 15 min; registro, reset, reenvío y enlace de acceso por email 5 por hora → 429 + `Retry-After` (`Security.throttle`) |
| Proxies de confianza | `TRUSTED_PROXIES`: IPs o **rangos CIDR** desde los que se aceptan `X-Forwarded-For` y `X-Forwarded-Proto` |
| Cuentas | 2FA y passkeys, códigos de un solo uso, campos de 2FA, tokens y fechas de acceso no asignables desde formularios (el CRUD tampoco acepta la contraseña al editar), perfiles privados (`/profile/<id>` solo muestra el propio), solo un superadmin gestiona superadmins, [actividad de la cuenta](account-activity.md), [histórico de permisos](permissions.md#histórico) |
| Archivos subidos | Privados fuera de `webroot`, servidos solo con sesión; validados por su contenido; las imágenes se reconvierten (sin EXIF ni contenido oculto); nunca el nombre del cliente ([Subida de archivos](uploads.md)) |
| Datos personales | La actividad y los históricos tienen retención y se purgan a diario; la cola no muestra datos de trabajos; los logs de fallos no llevan datos |

## Sesiones

`App\Account\SessionGuard` (con tests), aplicado por `RefreshIdentityMiddleware` y `ConfirmPasswordMiddleware`;
configuración en `App.session` (`config/users.php`):

- **Inactividad** (`SESSION_TIMEOUT`, 60 min): la sesión caduca si no hay peticiones.
- **Vida máxima** (`SESSION_MAX_LIFETIME`, 12 h): la sesión termina a las N horas del inicio de sesión aunque haya
  actividad, y se borra también la cookie «recordarme». Si la sesión caduca antes por inactividad, la cookie
  «recordarme» (1 mes) vuelve a abrir una sesión nueva.
- **Cerrar sesión en todas partes**: cada sesión guarda el `users.session_key` de la cuenta al empezar. Cambiar la
  contraseña desde el perfil, restablecerla por email o «Sign out of every other session» rotan esa clave, así que
  cualquier otra sesión (incluida una robada) termina en su siguiente petición con *Your session has ended*.
- **Confirmar contraseña («modo sudo»)**: las acciones sensibles redirigen a `/confirm-password` si la contraseña no
  se escribió en los últimos `SESSION_CONFIRM_MINUTES` (15). Iniciar sesión con contraseña (y 2FA) o con passkey cuenta
  como confirmación; volver por la cookie «recordarme» o por un enlace de acceso por email, no. Un GET vuelve a la
  misma página; un POST vuelve al formulario de origen (hay que reenviarlo). Los fallos cuentan en el límite por IP.
  Por defecto (`SessionGuard::CONFIRM_ACTIONS`, cambiable en `App.session.confirmActions`):
  permisos, roles, cambio de rol, invitaciones, reset del 2FA de otro y alta, edición y borrado de usuarios.
  Se aplica también a los superadmins.

## Registro público

`/register` está **activado** por defecto (rol `user`, con validación por email). Para un panel interno lo
recomendable es desactivarlo con `REGISTRATION_ENABLED=false`: `/register` responde 404, el login oculta el enlace
*Sign up* y las cuentas se crean solo por [invitación](accounts-and-roles.md#invitaciones) o desde el CRUD de usuarios.

## Proxies de confianza

Detrás de un balanceador o de Traefik (Coolify), la app solo ve la IP del proxy. Con `TRUSTED_PROXIES`:

- la IP del cliente (límite por IP, actividad, logs) sale de `X-Forwarded-For`;
- el esquema sale de `X-Forwarded-Proto`: con TLS terminado en el proxy la app sabe que es HTTPS (sin bucle de
  redirección, cookies `secure`, HSTS);
- solo para conexiones que llegan desde esas IPs o rangos: nadie más puede falsear las cabeceras.

En Coolify los contenedores cambian de IP en cada deploy, así que se confía en los rangos privados de Docker
(`10.0.0.0/8,172.16.0.0/12,192.168.0.0/16`, el valor por defecto de `compose.coolify.yaml`). Es seguro porque PHP-FPM
solo es accesible desde nginx y nginx solo desde Traefik (no se publica ningún puerto).

## Plantillas compatibles con la CSP

Nada de `onclick`/`onsubmit`, `<script>` sin `Html->script()`, `style="…"` ni `javascript:`. En su lugar:

- Scripts y estilos con `$this->Html->script()` / `->css()` / `->scriptBlock()`: llevan el nonce solos.
- Confirmación: `'data-confirm' => __('Are you sure?')` en el botón o el formulario (lo gestiona `webroot/js/app.js`).
- Enviar un formulario desde un enlace: `'data-submit-form' => 'id-del-form'`.
- Componentes del tema (sidebar, diálogos, alertas, barras de progreso): atributos `data-*` del propio tema.
- Comprueba las pantallas nuevas con el stack de producción (`compose.prod.yaml`): ahí la CSP bloquea de verdad.
