# Cuentas, usuarios y roles

[English](../en/accounts-and-roles.md) · **Español**

Las cuentas las gestiona [CakeDC/Users](https://github.com/CakeDC/users) (fork `delamux/CakeDC-users` para
webauthn-lib 5) con sus pantallas sobrescritas en `templates/plugin/CakeDC/Users/Users/` y su config en
`config/users.php`.

## Lo que puede hacer una persona

| Función | Detalle |
| --- | --- |
| Registrarse | `/register`, con validación por email (`Users.Email.validate`); rol por defecto `user`. Se desactiva con `Users.Registration.active` |
| Iniciar sesión | Usuario **o** email + contraseña; «recordarme» con cookie cifrada; segundo paso si tiene 2FA |
| Passkey | «Sign in with a passkey» en el login, sin contraseña ([2FA y passkeys](two-factor.md)) |
| Enlace mágico | «Email me a sign-in link» (`OneTimeLogin`): enlace de un solo uso por email; también pide el 2FA |
| Recuperar contraseña | «Forgot your password?»: enlace con token por email |
| Perfil | `/profile`: datos, **foto**, cambiar contraseña, verificación en dos pasos y **actividad reciente** |
| Cambiar contraseña | Pide la actual; queda en su [actividad](account-activity.md) |

Todos los emails salen por la [cola](queue.md) y usan el [layout de la app](emails-and-pdf.md). Los formularios
públicos tienen [límite por IP](security.md).

## Usuarios (CRUD)

*Administration › Users*: listado con búsqueda, orden y paginación sin recargar (htmx), ficha, alta, edición y borrado.
El superadmin ve además, en la ficha, el estado del 2FA (con botón de reset) y la actividad de la cuenta.

Reglas (`App\Service\UserRoleManager`, con tests):
- Solo un **superadmin** gestiona a otros superadmins y concede o quita ese rol (un admin que cambiara el email de un
  superadmin podría quedarse con su cuenta con un reset).
- **Nadie cambia su propio rol** (siempre queda al menos un administrador: quien actúa) ni borra su propia cuenta.
- La contraseña de otro solo la cambia un superadmin, y solo si `Users.Superuser.allowedToChangePasswords` está activo.
- El rol `superadmin` se mantiene sincronizado con el flag `is_superuser` de CakeDC.

## Roles

*Access control › Roles* (solo superadmin): CRUD de roles en la tabla `roles`.

| Rol | Por defecto puede |
| --- | --- |
| `superadmin` | Todo (se salta el RBAC). Protegido: no se borra ni se renombra |
| `admin` | Gestionar usuarios (salvo superadmins) y todo lo demás menos *Access control* y la cola |
| `editor` | Ver usuarios y el informe en PDF |
| `user` | Dashboard y su perfil |

Un rol con usuarios asignados no se puede borrar. Lo que puede hacer cada rol se cambia en la
[matriz de permisos](permissions.md); los cambios de roles quedan en el histórico de permisos.

## Dashboard e informe

- **Dashboard** (`/`): KPIs (totales, activos, pendientes, admins, altas de 30 días frente a los 30 anteriores),
  gráfico de altas diarias, últimos usuarios y equipo. Un usuario normal ve una tarjeta de bienvenida.
- **Informe de usuarios en PDF** (`/reports/users.pdf`, `?download=1` para descargar): ver [Emails y PDF](emails-and-pdf.md).

## Comandos

```bash
bin/cake users add_superuser -u admin -p 'Secreto1234!' -e admin@example.com
bin/cake seeds run DemoUsers          # 40 usuarios de demo (contraseña Demo1234!), solo para desarrollo
```

> `bin/cake users password_email` de CakeDC **no envía** el email (no le pasa el tipo a `resetToken()`): para un reset
> usa «Forgot your password?».
