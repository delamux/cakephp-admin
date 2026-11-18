<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Roles and permissions managed from the database (Phase 9).
 *
 * - roles: slug (= users.role), name, protected (superadmin: cannot be deleted/renamed).
 * - permissions: catalogue of discovered controller actions (`bin/cake permissions sync`).
 *   plugin/prefix are '' (not NULL) so the unique index works on PostgreSQL.
 * - roles_permissions: allow-list (role ↔ action).
 */
class CreateRolesAndPermissions extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->table('roles')
            ->addColumn('slug', 'string', ['limit' => 50, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('protected', 'boolean', ['default' => false, 'null' => false])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['slug'], ['unique' => true])
            ->create();

        $this->table('permissions')
            ->addColumn('plugin', 'string', ['limit' => 100, 'default' => '', 'null' => false])
            ->addColumn('prefix', 'string', ['limit' => 100, 'default' => '', 'null' => false])
            ->addColumn('controller', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('action', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('orphan', 'boolean', ['default' => false, 'null' => false])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['plugin', 'prefix', 'controller', 'action'], ['unique' => true])
            ->create();

        $this->table('roles_permissions')
            ->addColumn('role_id', 'integer', ['null' => false])
            ->addColumn('permission_id', 'integer', ['null' => false])
            ->addIndex(['role_id', 'permission_id'], ['unique' => true])
            ->addForeignKey('role_id', 'roles', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('permission_id', 'permissions', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $now = date('Y-m-d H:i:s');
        $this->table('roles')->insert([
            ['slug' => 'superadmin', 'name' => 'Superadmin', 'protected' => true, 'created' => $now, 'modified' => $now],
            ['slug' => 'admin', 'name' => 'Administrator', 'protected' => false, 'created' => $now, 'modified' => $now],
            ['slug' => 'editor', 'name' => 'Editor', 'protected' => false, 'created' => $now, 'modified' => $now],
            ['slug' => 'user', 'name' => 'User', 'protected' => false, 'created' => $now, 'modified' => $now],
        ])->saveData();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->table('roles_permissions')->drop()->save();
        $this->table('permissions')->drop()->save();
        $this->table('roles')->drop()->save();
    }
}
