<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * "Where you're signed in" (App\UseCase\Account\ActiveSessions): each session row knows its user, IP and
 * browser (written by App\Http\Session\AppDatabaseSession); deleting a user deletes their sessions.
 * `users.session_key` is part of the "remember me" cookie token: rotating it signs the other devices out
 * for good.
 */
class AddDevicesToSessions extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('sessions')
            ->addColumn('user_id', 'uuid', ['null' => true, 'default' => null])
            ->addColumn('ip', 'string', ['limit' => 45, 'null' => true, 'default' => null])
            ->addColumn('user_agent', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addIndex(['user_id'], ['name' => 'sessions_user_id'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->update();
        $this->table('users')
            ->addColumn('session_key', 'string', ['limit' => 64, 'null' => true, 'default' => null])
            ->update();
    }
}
