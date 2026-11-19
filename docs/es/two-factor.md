# Verificación en dos pasos y passkeys

[English](../en/two-factor.md) · **Español**

Opcional para todos los roles y activable desde el perfil (tarjeta *Two-step verification*). Interruptor general:
`TWO_FACTOR_ENABLED` (por defecto `true`). Requiere HTTPS y que `APP_FULL_BASE_URL` sea el dominio definitivo: las
llaves quedan atadas a ese dominio (WebAuthn).

## Métodos (independientes)

| Método | Cómo se usa |
| --- | --- |
| **App de autenticación (TOTP)** | QR generado en el servidor (Google Authenticator, 1Password, Bitwarden…). Al iniciar sesión se pide el código de 6 dígitos |
| **Llave de seguridad o passkey** (WebAuthn) | Touch ID / Windows Hello / llave física / gestor de contraseñas. Se pide como segundo paso **solo si es el único método** |
| **Passkey sin contraseña** | La misma llave sirve para «Sign in with a passkey» en el login (credencial descubrible, `userHandle` = id del usuario) |

Activar o desactivar un método pide la **contraseña actual**; cada cambio envía un **email** al usuario (nunca el
secreto) y queda en su [actividad](account-activity.md).

## Garantías (con tests)

- **Códigos TOTP de un solo uso:** cada ventana de 30 s se reclama de forma atómica (`users.totp_last_step`); un
  código ya usado (también el de la activación) no vale otra vez.
- **Passkeys:** reto de un solo uso que caduca a los 5 min, verificación de usuario obligatoria, contador de firmas
  creciente (detecta llaves clonadas), cuenta activa y no bloqueada, redirección solo interna.
- Los campos de 2FA (`secret`, `additional_data`…) **no se pueden asignar desde formularios**.
- Códigos o llaves incorrectos cuentan en el [límite por IP](security.md) y se registran como «Wrong two-step
  verification» en la actividad (señal de que alguien tiene la contraseña).

## Dispositivo perdido

No hay códigos de recuperación: lo resuelve una persona.

- **Un superadmin** quita el 2FA de otra cuenta desde su ficha (*Reset two-step verification*); el usuario recibe un
  email y la actividad guarda quién lo hizo. Nunca el de otro superadmin.
- **Un superadmin sin su dispositivo**, desde la terminal:
  ```bash
  bin/cake two_factor reset <usuario|email>
  ```

## Dónde está

`App\UseCase\TwoFactor\TwoFactorSettings` (reglas), `TwoFactorController` (pasos del perfil),
`WebauthnLoginController` / `PasskeyLoginController` (login), `src/Webauthn/`, `webroot/js/webauthn.js`
(sin build), `config/users.php` (`OneTimePasswordAuthenticator`, `Webauthn2fa`).
