# Security

**English** · [Español](../es/security.md)

Applied following [book.cakephp.org/5.x/security](https://book.cakephp.org/5.x/security.html)
(`App\Http\SecurityMiddlewares`, `config/app.php` → `Security.*`). `SecurityTest` checks it against real responses.

| Measure | How |
| --- | --- |
| **Content-Security-Policy** | `CspMiddleware` with script and style nonces; nothing inline (no `'unsafe-inline'`/`'unsafe-eval'`), `object-src 'none'`, `frame-ancestors`/`form-action`/`base-uri 'self'`. **Strict in production**; Report-Only in dev (Tailwind's browser build injects styles without a nonce). Production makes no requests to CDNs |
| Headers | `nosniff`, `X-Frame-Options: sameorigin`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-Download-Options`, `Permissions-Policy` (camera, microphone, geolocation, payments, USB disabled; WebAuthn allowed) |
| HTTPS + HSTS | `HTTPS_ENFORCE=true` (production image): HTTP → HTTPS and a 1-year `Strict-Transport-Security` (`HSTS_MAX_AGE`, `HSTS_INCLUDE_SUBDOMAINS`) |
| CSRF | `httponly` cookie, `SameSite=Lax` and `secure` over HTTPS; `fetch`/htmx send `X-CSRF-Token` from `<meta name="csrf-token">` |
| FormProtection | In every controller: `FormHelper` forms carry a token for their fields (only the WebAuthn JSON endpoints and the matrix checkboxes are unlocked) |
| "Remember me" cookie | AES-encrypted (`EncryptedCookieMiddleware`, `COOKIE_KEY` or derived from the salt), `secure`, `SameSite=Lax` |
| Session | Stored in **PostgreSQL** (`sessions` table); `httponly`, `SameSite=Lax`, `use_strict_mode`, `secure` over HTTPS, idle timeout (`SESSION_TIMEOUT`, 240 min). Survives redeploys and is shared by every replica |
| Per-IP rate limiting | In **PostgreSQL** (`auth_throttles`, SHA-256-hashed IP, atomic upsert): 20 failed sign-ins / 2FA codes / passkeys every 15 min; sign-up, reset and resend 5 per hour → 429 + `Retry-After` (`Security.throttle`) |
| Trusted proxies | `TRUSTED_PROXIES`: IPs or **CIDR ranges** from which `X-Forwarded-For` and `X-Forwarded-Proto` are accepted |
| Accounts | 2FA and passkeys, single-use codes, 2FA fields not mass-assignable, only a superadmin manages superadmins, [account activity](account-activity.md), [permissions history](permissions.md#history) |
| Uploaded files | Private, outside `webroot`, served only with a session; validated by their content; images are re-encoded (no EXIF or hidden content); never the client's file name ([File uploads](uploads.md)) |
| Personal data | Activity and histories have retention periods and are purged daily; the queue doesn't show job data; failure logs carry no data |

## Trusted proxies

Behind a load balancer or Traefik (Coolify), the app only sees the proxy's IP. With `TRUSTED_PROXIES`:

- the client IP (rate limiting, activity, logs) comes from `X-Forwarded-For`;
- the scheme comes from `X-Forwarded-Proto`: with TLS terminated at the proxy the app knows it's HTTPS (no redirect
  loop, `secure` cookies, HSTS);
- only for connections coming from those IPs or ranges: nobody else can spoof the headers.

On Coolify containers change IP on every deploy, so the app trusts Docker's private ranges
(`10.0.0.0/8,172.16.0.0/12,192.168.0.0/16`, the default in `compose.coolify.yaml`). This is safe because PHP-FPM is
only reachable from nginx and nginx only from Traefik (no port is published).

## CSP-compatible templates

No `onclick`/`onsubmit`, no `<script>` outside `Html->script()`, no `style="…"` and no `javascript:`. Instead:

- Scripts and styles via `$this->Html->script()` / `->css()` / `->scriptBlock()`: they get the nonce automatically.
- Confirmation: `'data-confirm' => __('Are you sure?')` on the button or form (handled by `webroot/js/app.js`).
- Submitting a form from a link: `'data-submit-form' => 'form-id'`.
- Theme components (sidebar, dialogs, alerts, progress bars): the theme's own `data-*` attributes.
- Check new screens on the production stack (`compose.prod.yaml`): that's where the CSP actually blocks.
