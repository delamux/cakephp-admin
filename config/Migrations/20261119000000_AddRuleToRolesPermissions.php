<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Role permissions with rules (App\Rbac\RuleCatalog): `roles_permissions.rule` (NULL = allowed,
 * name = allowed when the rule says so). Moves to the database, with the behaviour they had:
 *
 * - The `role => '*'` system rules of config/permissions.php that are not profile/logout
 *   (dashboard, pages, own two-step settings) → allowed for every role; reset of the own
 *   authenticator → `owner`.
 * - Users::edit/delete: only admins could use them (UserRoleManager::canManage()) → revoked from the
 *   other roles. The superadmin/admin account protections stay in code (UserRoleManager).
 */
class AddRuleToRolesPermissions extends BaseMigration
{
    private const EVERY_ROLE = [
        ['', 'Dashboard', 'index', null],
        ['', 'Pages', 'display', null],
        ['', 'TwoFactor', 'start', null],
        ['', 'TwoFactor', 'confirm', null],
        ['', 'TwoFactor', 'cancel', null],
        ['', 'TwoFactor', 'disable', null],
        ['', 'TwoFactor', 'webauthnOptions', null],
        ['', 'TwoFactor', 'webauthnRegister', null],
        ['CakeDC/Users', 'Users', 'resetOneTimePasswordAuthenticator', 'owner'],
    ];

    private const ADMIN_ONLY_ACTIONS = ['edit', 'delete'];

    /**
     * @return void
     */
    public function up(): void
    {
        $this->table('roles_permissions')
            ->addColumn('rule', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->update();

        $now = date('Y-m-d H:i:s');
        $roles = $this->fetchAll("SELECT id, slug FROM roles WHERE slug <> 'superadmin'");
        foreach (self::EVERY_ROLE as [$plugin, $controller, $action, $rule]) {
            $permissionId = $this->permissionId($plugin, $controller, $action, $now);
            foreach ($roles as $role) {
                $this->execute('DELETE FROM roles_permissions WHERE role_id = ? AND permission_id = ?', [$role['id'], $permissionId]);
                $this->execute(
                    'INSERT INTO roles_permissions (role_id, permission_id, rule) VALUES (?, ?, ?)',
                    [$role['id'], $permissionId, $rule],
                );
            }
        }

        foreach (self::ADMIN_ONLY_ACTIONS as $action) {
            $permission = $this->row(
                "SELECT id FROM permissions WHERE plugin = 'CakeDC/Users' AND prefix = '' AND controller = 'Users' AND action = ?",
                [$action],
            );
            if (!$permission) {
                continue;
            }
            $this->execute(
                "DELETE FROM roles_permissions WHERE permission_id = ? AND role_id NOT IN (SELECT id FROM roles WHERE slug = 'admin')",
                [$permission['id']],
            );
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->table('roles_permissions')->removeColumn('rule')->update();
    }

    /**
     * @param string $sql Query.
     * @param array<mixed> $params Bound values.
     * @return array<string, mixed>|false
     */
    private function row(string $sql, array $params): array|false
    {
        return $this->query($sql, $params)->fetch('assoc');
    }

    /**
     * @param string $plugin Plugin ('' = app).
     * @param string $controller Controller.
     * @param string $action Action.
     * @param string $now Timestamp.
     * @return int
     */
    private function permissionId(string $plugin, string $controller, string $action, string $now): int
    {
        $where = 'plugin = ? AND prefix = ? AND controller = ? AND action = ?';
        $params = [$plugin, '', $controller, $action];
        $row = $this->row("SELECT id FROM permissions WHERE {$where}", $params);
        if (!$row) {
            $this->execute(
                'INSERT INTO permissions (plugin, prefix, controller, action, orphan, created, modified) VALUES (?, ?, ?, ?, false, ?, ?)',
                [...$params, $now, $now],
            );
            $row = $this->row("SELECT id FROM permissions WHERE {$where}", $params);
        }

        return (int)$row['id'];
    }
}
