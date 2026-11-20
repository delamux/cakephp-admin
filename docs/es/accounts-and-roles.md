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
| Perfil | `/profile`: datos, **foto**, cambiar contraseña, verificación en dos pasos, **dónde tienes la sesión abierta** y **actividad reciente** |
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

## Invitaciones

*Administration › Users › Invite user*: escribes el email, el rol y (opcional) el nombre. La cuenta se crea
**inactiva** y la persona recibe un email (por la cola) con un enlace para elegir su contraseña; hasta entonces no
puede entrar. El listado la muestra como **Invited**, con **Resend invitation** en el menú de la fila (enlace nuevo,
el anterior deja de valer).

- Solo los roles que quien invita puede asignar (un admin nunca invita a un superadmin).
- El enlace es de un solo uso y caduca a los `App.invitations.expirationDays` (**7 días**, `config/users.php`).
- La página de aceptar (`/invitations/accept/<token>`) es pública y tiene límite por IP; la contraseña, 8 caracteres mínimo.
- La invitación (con **quién** invitó) y la aceptación quedan en la [actividad de la cuenta](account-activity.md).
- Quién puede invitar es un permiso (`Invitations::add` / `resend`, admins por defecto) de la [matriz](permissions.md).

## Dónde tienes la sesión abierta

El perfil lista las **sesiones vivas** de la cuenta (navegador, IP, última actividad; la actual marcada como *This
device*). El usuario puede cerrar una o **todas las demás**. Esto último invalida también las cookies «recordarme» de
esos dispositivos, para que no vuelvan a entrar solos (`users.session_key`, parte del token de la cookie). Queda en la
[actividad](account-activity.md).

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
bin/cake users password_email ana@example.com   # envía un enlace para restablecer la contraseña
bin/cake seeds run DemoUsers          # 40 usuarios de demo (contraseña Demo1234!), solo para desarrollo
```

`users password_email` está corregido en el fork de CakeDC (no enviaba el email).
