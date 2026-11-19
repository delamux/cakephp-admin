# Permisos (RBAC en base de datos)

[English](../en/permissions.md) · **Español**

El RBAC de CakeDC/Auth sigue decidiendo, pero las reglas salen de la base de datos
(`App\Rbac\DatabasePermissionsProvider`, configurado en `Auth.RbacPolicy`), con caché que se invalida en cada cambio.
Funciona como **lista blanca**: lo que no está concedido se deniega.

## De dónde sale cada regla

| Tipo | Dónde | Editable |
| --- | --- | --- |
| Reglas de sistema: acciones públicas (login, registro, reset, validación, passkey), perfil y logout propios, dashboard, superadmin = todo, DebugKit | `config/permissions.php` | No (código) |
| Acciones solo para superadmin: `Roles::*`, `Permissions::*`, `TwoFactor::reset`, `Queue::*` | `PermissionsSynchronizer::SUPERADMIN_ONLY` | No: se filtran aunque se marquen, para que nadie se conceda a sí mismo *Access control* |
| El resto: acción × rol | Tablas `permissions` y `roles_permissions` | Sí, en la matriz |

## Descubrimiento de acciones

`bin/cake permissions sync` (o el botón *Sync actions* de la matriz) recorre los controladores de la app y de los
plugins de `App.permissions.plugins` (CakeDC/Users) y guarda cada acción pública (mismo criterio que
`Controller::isAction()`, sin los métodos de `App.permissions.ignoredActions`):

- las **nuevas** reciben los permisos de `App.permissions.defaults` (patrones `Plugin.Prefix/Controller::action`,
  `*` y `!` para excluir);
- las que ya **no existen** se marcan huérfanas (se ven en la matriz para borrarlas);
- `--reset` vuelve a aplicar los permisos por defecto a todo (queda en el histórico).

Ejecuta `sync` después de crear controladores o acciones (en producción, tras el deploy si hay acciones nuevas).

## La matriz

*Access control › Permissions* (solo superadmin): controladores × acciones × roles con casillas. Las filas
bloqueadas indican por qué: **Public** (sin login), **All roles** (regla de sistema) o **Superadmin only**. Guardar
conserva las filas bloqueadas y registra la diferencia en el histórico.

## Histórico

*Access control › History*: quién cambió qué y cuándo (append-only, tabla `permission_audits`):
`grants_changed` (+ concedido / − revocado por rol), `role_created`, `role_updated` (campos antes → después),
`role_deleted`, `sync` (conteos y si hubo `--reset`; actor `console` desde la terminal) y `audit_purged`.

Retención: `App.permissions.auditRetentionDays` (365 días, mínimo 30) aplicada por `bin/cake maintenance` (o
`bin/cake permissions purge [--days N] [--dry-run]`). La purga se registra a sí misma solo si borra algo.
