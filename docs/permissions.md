# Permissions (database-backed RBAC)

CakeDC/Auth's RBAC still makes the decisions, but the rules come from the database
(`App\Rbac\DatabasePermissionsProvider`, configured in `Auth.RbacPolicy`), with a cache that is invalidated on every change.
It works as an **allowlist**: anything not granted is denied.

## Where each rule comes from

| Type | Where | Editable |
| --- | --- | --- |
| System rules: public actions (login, registration, reset, validation, passkey), own profile and logout, dashboard, superadmin = everything, DebugKit | `config/permissions.php` | No (code) |
| Superadmin-only actions: `Roles::*`, `Permissions::*`, `TwoFactor::reset`, `Queue::*` | `PermissionsSynchronizer::SUPERADMIN_ONLY` | No: they are filtered out even if ticked, so nobody can grant themselves *Access control* |
| Everything else: action × role | `permissions` and `roles_permissions` tables | Yes, in the matrix |

## Action discovery

`bin/cake permissions sync` (or the matrix's *Sync actions* button) walks the controllers of the app and of the
plugins in `App.permissions.plugins` (CakeDC/Users) and stores every public action (same criteria as
`Controller::isAction()`, minus the methods in `App.permissions.ignoredActions`):

- **new** ones get the permissions from `App.permissions.defaults` (`Plugin.Prefix/Controller::action` patterns,
  `*`, and `!` to exclude);
- ones that **no longer exist** are marked as orphaned (they show up in the matrix so you can delete them);
- `--reset` reapplies the default permissions to everything (recorded in the history).

Run `sync` after creating controllers or actions (in production, after the deploy if there are new actions).

## The matrix

*Access control › Permissions* (superadmin only): controllers × actions × roles with checkboxes. Locked rows show
why: **Public** (no login), **All roles** (system rule) or **Superadmin only**. Saving keeps the locked rows as they
are and records the diff in the history.

## History

*Access control › History*: who changed what and when (append-only, `permission_audits` table):
`grants_changed` (+ granted / − revoked per role), `role_created`, `role_updated` (fields before → after),
`role_deleted`, `sync` (counts and whether `--reset` was used; actor `console` when run from the terminal) and `audit_purged`.

Retention: `App.permissions.auditRetentionDays` (365 days, minimum 30), applied by `bin/cake maintenance` (or
`bin/cake permissions purge [--days N] [--dry-run]`). The purge records itself only if it deletes something.
