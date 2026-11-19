# Actividad de la cuenta

[English](../en/account-activity.md) · **Español**

Registro de los eventos de seguridad de cada cuenta, con **fecha, IP y navegador** (tabla `account_activities`).

## Qué se registra

| Evento | Cuándo |
| --- | --- |
| Signed in | Inicio de sesión completado (también tras el segundo paso) |
| Signed in with a passkey | Login sin contraseña |
| Failed sign-in | Contraseña incorrecta para una cuenta **que existe** |
| Wrong two-step verification | Contraseña correcta pero código o llave incorrectos |
| Signed out | Cierre de sesión |
| Password changed / Password reset by email | Cambio desde el perfil / restablecimiento con el enlace |
| Authenticator app enabled / removed | TOTP |
| Security key or passkey added / removed | WebAuthn |
| Two-step verification reset | Por un superadmin (se guarda **quién**) o por consola |
| Photo changed / removed | Foto de usuario (si la cambió un administrador, se guarda **quién**) |
| Invited / Invitation accepted | [Invitaciones](accounts-and-roles.md#invitaciones) (se guarda **quién** invitó) |
| Other sessions signed out | «Sign out of every other session» desde el perfil |

**Nunca** se guarda lo que se escribió (contraseñas, códigos ni el usuario de un intento con una cuenta inexistente:
esos solo cuentan en el límite por IP). Si falla la escritura del registro, va al log de errores y la acción sigue:
la auditoría no impide entrar.

## Quién lo ve

- Cada usuario, la suya: tarjeta **Recent activity** de su perfil (últimos 20 eventos; los fallos, en rojo).
- El superadmin, la de cualquiera: en la ficha del usuario.

Detrás de un proxy, la IP solo es la real si `TRUSTED_PROXIES` está configurado ([Seguridad](security.md#proxies-de-confianza)).

## Retención

Son datos personales (IP, navegador): se guardan `App.accountActivity.retentionDays` (**180 días**, mínimo 30) y los
borra `bin/cake maintenance` (o `bin/cake activity purge [--days N] [--dry-run]`). Al borrar un usuario se borra su
actividad.

## Dónde está

`App\Account\ActivityLog` (repositorio y lista de eventos), `App\Account\AccountActivityListener` (eventos de
CakeDC: login, fallo, logout, contraseña), `TwoFactorSettings` y los controladores de passkey/llave (lo que CakeDC no
anuncia), `App\Account\UserAgent` («Chrome on macOS»), `AccountActivityCell`.

Para registrar un evento nuevo: una constante en `ActivityLog`, su etiqueta e icono en
`templates/cell/AccountActivity/display.php` y `(new ActivityLog())->record($userId, ActivityLog::MI_EVENTO)`.
