# Two-step verification and passkeys

**English** · [Español](../es/two-factor.md)

Optional for every role and enabled from the profile (*Two-step verification* card). Global switch:
`TWO_FACTOR_ENABLED` (default `true`). It requires HTTPS and `APP_FULL_BASE_URL` set to the final domain: keys are
bound to that domain (WebAuthn).

## Methods (independent)

| Method | How it's used |
| --- | --- |
| **Authenticator app (TOTP)** | Server-generated QR code (Google Authenticator, 1Password, Bitwarden…). The 6-digit code is requested at sign-in |
| **Security key or passkey** (WebAuthn) | Touch ID / Windows Hello / hardware key / password manager. Requested as the second step **only if it's the only method** |
| **Passwordless passkey** | The same key works for "Sign in with a passkey" on the login page (discoverable credential, `userHandle` = user id) |

Enabling or disabling a method asks for the **current password**; every change sends an **email** to the user
(never the secret) and is recorded in their [activity](account-activity.md).

## Guarantees (with tests)

- **Single-use TOTP codes:** each 30 s window is claimed atomically (`users.totp_last_step`); an already used code
  (including the activation one) doesn't work again.
- **Passkeys:** single-use challenge expiring after 5 min, mandatory user verification, increasing signature counter
  (detects cloned keys), active and non-locked account, internal redirects only.
- 2FA fields (`secret`, `additional_data`…) **can't be assigned from forms**.
- Wrong codes or keys count towards the [per-IP rate limit](security.md) and are recorded as "Wrong two-step
  verification" in the activity (a sign that someone has the password).

## Lost device

There are no recovery codes: a person resolves it.

- **A superadmin** removes 2FA from another account on its user page (*Reset two-step verification*); the user gets
  an email and the activity records who did it. Never another superadmin's.
- **A superadmin without their device**, from the terminal:
  ```bash
  bin/cake two_factor reset <username|email>
  ```

## Where it lives

`App\UseCase\TwoFactor\TwoFactorSettings` (rules), `TwoFactorController` (profile steps),
`WebauthnLoginController` / `PasskeyLoginController` (login), `src/Webauthn/`, `webroot/js/webauthn.js`
(no build step), `config/users.php` (`OneTimePasswordAuthenticator`, `Webauthn2fa`).
