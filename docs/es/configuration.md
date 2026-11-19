# Configuración

[English](../en/configuration.md) · **Español**

En producción todo va por **variables de entorno** (`config/app_local.php` no entra en la imagen). En dev,
`compose.yaml` ya las define.

## Variables de entorno

| Variable | Por defecto | Uso |
| --- | --- | --- |
| `SECURITY_SALT` | — (**obligatoria** en producción) | Sal de seguridad (`openssl rand -hex 32`). No la cambies después: invalida sesiones y cookies |
| `APP_FULL_BASE_URL` | `https://cakeadmin.local` | URL pública: enlaces de los emails y **dominio de WebAuthn** (las llaves quedan atadas a él) |
| `APP_NAME` | `CakePHP Admin` | Nombre en el emisor TOTP y en WebAuthn |
| `DATABASE_URL` | PostgreSQL del compose | `postgres://usuario:clave@host:5432/bd` |
| `EMAIL_TRANSPORT_DEFAULT_URL` | Mailpit del compose | SMTP real: `smtp://usuario:clave@smtp.example.com:587?tls=true` |
| `EMAIL_FROM`, `EMAIL_FROM_NAME` | `admin@cakephp-admin.test`, `CakePHP Admin` | Remitente |
| `EMAIL_QUEUE` | `true` | `false`: los emails salen durante la petición (sin worker) |
| `QUEUE_WORKER_LIFETIME` | `600` | Segundos antes de reciclar el worker |
| `QUEUE_SLEEPTIME` | `5` | Espera del worker cuando no hay trabajo |
| `UPLOADS_PATH` | `storage/uploads/` | Raíz privada de los archivos subidos (en producción, el volumen `uploads`) |
| `COOKIE_KEY` | derivada de la sal | Clave AES de la cookie «recordarme» (≥ 32 caracteres) |
| `HTTPS_ENFORCE` | `true` en la imagen de producción | HTTP → HTTPS + HSTS |
| `HSTS_MAX_AGE`, `HSTS_INCLUDE_SUBDOMAINS` | `31536000`, `false` | HSTS |
| `CSP_REPORT_ONLY` | `false` en producción (`DEBUG` en dev) | CSP solo informativa |
| `TRUSTED_PROXIES` | vacío (Coolify: rangos privados) | IPs o rangos CIDR del proxy, separados por comas |
| `SESSION_TIMEOUT` | `240` | Minutos de inactividad |
| `TWO_FACTOR_ENABLED` | `true` | Interruptor general del 2FA y las passkeys |
| `DEBUG` | `false` | Nunca `true` en producción |
| `APP_DEFAULT_LOCALE`, `APP_DEFAULT_TIMEZONE` | `en_US`, `UTC` | Idioma por defecto (el navegador elige entre `en_US` y `es_ES`) y zona horaria |
| `THEME_COMPILED_ASSETS`, `ASSET_TIMESTAMP`, `LOG_TO_STDERR` | fijadas en la imagen | Assets propios, cache busting, logs a la salida del contenedor |
| `APP_PORT`, `HTTPS_PORT`, `DB_PORT`, `MAILPIT_UI_PORT`, `MAILPIT_SMTP_PORT` | 8080, 443, 5433, 8026, 1026 | Puertos de dev (`compose.prod.yaml` publica HTTPS en el 8445 por defecto) |

En dev local la app está en `https://cakeadmin.local` en el puerto 443 (el valor por defecto de `compose.yaml`). Si el
443 está ocupado, pon otro `HTTPS_PORT` en un archivo `.env` en la raíz del proyecto (ignorado por git); ver
[Primeros pasos](getting-started.md#1-https-local-y-dominio-una-vez-por-equipo).

## Claves de configuración de la app

| Clave | Archivo | Qué |
| --- | --- | --- |
| `App.roles` | `config/users.php` | Etiquetas de los roles iniciales |
| `App.permissions.{plugins,ignoredActions,defaults,superadminOnly,auditRetentionDays}` | `config/users.php` | [Permisos](permissions.md) |
| `App.accountActivity.retentionDays` | `config/users.php` | Retención de la [actividad](account-activity.md) (180) |
| `Users.*` (registro, emails, «recordarme», social) | `config/users.php` | CakeDC/Users |
| `OneTimePasswordAuthenticator`, `Webauthn2fa` | `config/users.php` | [2FA](two-factor.md) |
| `Security.{throttle,https,csp,cookieKey,trustedProxies}` | `config/app.php` | [Seguridad](security.md) |
| `Queue.*` | `config/app.php` | [Cola](queue.md) |
| `EmailTransport.{default,queue}`, `Email.default` | `config/app.php` | [Emails](emails-and-pdf.md) |
| `Session` | `config/app.php` | Sesión en base de datos endurecida |
| `Uploads.path` | `config/app.php` | [Subida de archivos](uploads.md) |
