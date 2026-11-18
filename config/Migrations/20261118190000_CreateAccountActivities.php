<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Account activity log (App\Account\ActivityLog): security events of each account with IP and
 * browser. Integer id = insertion order. Deleted with the account; `actor_id` is set when someone else acted (2FA reset).
 */
class CreateAccountActivities extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->table('account_activities')
            ->addColumn('user_id', 'uuid', ['null' => false])
            ->addColumn('event', 'string', ['limit' => 40, 'null' => false])
            ->addColumn('actor_id', 'uuid', ['null' => true])
            ->addColumn('ip', 'string', ['limit' => 45, 'null' => true])
            ->addColumn('user_agent', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addIndex(['user_id', 'created'])
            ->addIndex(['created'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('actor_id', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->create();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->table('account_activities')->drop()->save();
    }
}
