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
| "Remember me" cookie | AES-encrypted (`EncryptedCookieMiddleware`, `COOKIE_KEY` or derived from the salt), `secure`, `SameSite=Lax`. Its token includes the password hash and `users.session_key`: changing the password or "Sign out of every other session" invalidates it. Lasts 1 month |
| `?api_key=` | CakeDC's Token authenticator (sign-in with `users.api_token` on any URL) is **disabled** (`Auth.Authenticators.Token => false`): the app has no API |
| Session | Stored in **PostgreSQL** (`sessions` table); `httponly`, `SameSite=Lax`, `use_strict_mode`, `secure` over HTTPS, idle timeout (`SESSION_TIMEOUT`, 60 min) and **maximum lifetime** (`SESSION_MAX_LIFETIME`, 12 h). Survives redeploys and is shared by every replica. Each row stores its user, IP and browser (`AppDatabaseSession`) for *Where you are signed in* |
| Sign out everywhere | Changing or resetting the password signs out **every other session** of the account (and their "remember me" cookies); whoever changes it from their profile stays signed in. See [Sessions](#sessions) |
| Password confirmation | Sensitive actions ask for the password again unless it was typed in the last 15 min (`SESSION_CONFIRM_MINUTES`). See [Sessions](#sessions) |
| Per-IP rate limiting | In **PostgreSQL** (`auth_throttles`, SHA-256-hashed IP, atomic upsert): 20 failed sign-ins / 2FA codes / security keys / passkeys / password confirmations every 15 min; sign-up, reset, resend and emailed sign-in link 5 per hour → 429 + `Retry-After` (`Security.throttle`) |
| Trusted proxies | `TRUSTED_PROXIES`: IPs or **CIDR ranges** from which `X-Forwarded-For` and `X-Forwarded-Proto` are accepted |
| Accounts | 2FA and passkeys, single-use codes, 2FA fields, tokens and sign-in dates not mass-assignable from forms (the CRUD does not take the password when editing either), private profiles (`/profile/<id>` only shows your own), only a superadmin manages superadmins, [account activity](account-activity.md), [permissions history](permissions.md#history) |
| Uploaded files | Private, outside `webroot`, served only with a session; validated by their content; images are re-encoded (no EXIF or hidden content); never the client's file name ([File uploads](uploads.md)) |
| Personal data | Activity and histories have retention periods and are purged daily; the queue doesn't show job data; failure logs carry no data |

## Sessions

`App\Account\SessionGuard` (with tests), applied by `RefreshIdentityMiddleware` and `ConfirmPasswordMiddleware`;
configured in `App.session` (`config/users.php`):

- **Idle timeout** (`SESSION_TIMEOUT`, 60 min): the session expires without requests.
- **Maximum lifetime** (`SESSION_MAX_LIFETIME`, 12 h): the session ends N hours after signing in however active it
  is, and the "remember me" cookie is removed too. If the session expires earlier from inactivity, the "remember me"
  cookie (1 month) opens a new one.
- **Sign out everywhere**: every session remembers the account's `users.session_key` when it starts. Changing the
  password from the profile, resetting it by email or "Sign out of every other session" rotate that key, so any other
  session (a stolen one included) ends on its next request with *Your session has ended*.
- **Password confirmation ("sudo mode")**: sensitive actions redirect to `/confirm-password` unless the password was
  typed in the last `SESSION_CONFIRM_MINUTES` (15). Signing in with the password (and 2FA) or a passkey counts as a
  confirmation; coming back through the "remember me" cookie or an emailed sign-in link does not. A GET comes back to
  the same page; a POST back to the form it came from (submit it again). Failures count in the per-IP limit.
  Defaults (`SessionGuard::CONFIRM_ACTIONS`, change them in `App.session.confirmActions`): permissions, roles, role
  changes, invitations, resetting someone's 2FA, and adding, editing and deleting users. Superadmins are asked too.

## Public sign-up

`/register` is **on** by default (role `user`, with email verification). For an internal panel, switch it off with
`REGISTRATION_ENABLED=false`: `/register` answers 404, the login page hides the *Sign up* link and accounts are only
created by [invitation](accounts-and-roles.md#invitations) or from the users CRUD.

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
