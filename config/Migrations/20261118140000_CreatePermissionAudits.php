<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Audit log of roles/permissions changes (who, when, what).
 */
class CreatePermissionAudits extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->table('permission_audits')
            ->addColumn('user_id', 'uuid', ['null' => true])
            ->addColumn('username', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('event', 'string', ['limit' => 50, 'null' => false])
            ->addColumn('role_slug', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('added', 'json', ['null' => true])
            ->addColumn('removed', 'json', ['null' => true])
            ->addColumn('details', 'json', ['null' => true])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addIndex(['created'])
            ->addIndex(['role_slug'])
            ->create();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->table('permission_audits')->drop()->save();
    }
}
