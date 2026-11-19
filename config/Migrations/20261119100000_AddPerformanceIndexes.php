<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Indexes for the lookups the app and CakeDC/Users run (PostgreSQL never indexes foreign keys on its own):
 *
 * - users: login (`username` OR `email`), remember-me cookie, `?api_key=` (Token authenticator, enabled by
 *   CakeDC on every URL), email validation / password reset tokens, dashboard by `created`. `username` and
 *   `email` are UNIQUE: CakeDC only checks it in PHP (`isUnique`), which two simultaneous sign-ups can beat.
 * - account_activities: the profile lists `WHERE user_id ORDER BY id DESC`, and deleting a user sets
 *   `actor_id` to NULL (without an index that is a full scan of the table per deleted user).
 * - social_accounts / failed_password_attempts (CakeDC): their FKs to users and their lookups.
 * - roles_permissions: FK `permission_id` (deleting orphan permissions).
 */
class AddPerformanceIndexes extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->table('users')
            ->addIndex(['username'], ['unique' => true, 'name' => 'users_username_unique'])
            ->addIndex(['email'], ['unique' => true, 'name' => 'users_email_unique'])
            ->addIndex(['api_token'], ['name' => 'users_api_token'])
            ->addIndex(['token'], ['name' => 'users_token'])
            ->addIndex(['created'], ['name' => 'users_created'])
            ->update();

        $this->table('account_activities')
            ->removeIndex(['user_id', 'created'])
            ->addIndex(['user_id', 'id'], ['name' => 'account_activities_user_id_id'])
            ->addIndex(['actor_id'], ['name' => 'account_activities_actor_id'])
            ->update();

        $this->table('social_accounts')
            ->addIndex(['user_id'], ['name' => 'social_accounts_user_id'])
            ->addIndex(['provider', 'reference'], ['name' => 'social_accounts_provider_reference'])
            ->update();

        $this->table('failed_password_attempts')
            ->addIndex(['user_id', 'created'], ['name' => 'failed_password_attempts_user_id_created'])
            ->update();

        $this->table('roles_permissions')
            ->addIndex(['permission_id'], ['name' => 'roles_permissions_permission_id'])
            ->update();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->table('roles_permissions')->removeIndexByName('roles_permissions_permission_id')->update();
        $this->table('failed_password_attempts')
            ->removeIndexByName('failed_password_attempts_user_id_created')
            ->update();
        $this->table('social_accounts')
            ->removeIndexByName('social_accounts_user_id')
            ->removeIndexByName('social_accounts_provider_reference')
            ->update();
        $this->table('account_activities')
            ->removeIndexByName('account_activities_user_id_id')
            ->removeIndexByName('account_activities_actor_id')
            ->addIndex(['user_id', 'created'])
            ->update();
        $this->table('users')
            ->removeIndexByName('users_username_unique')
            ->removeIndexByName('users_email_unique')
            ->removeIndexByName('users_api_token')
            ->removeIndexByName('users_token')
            ->removeIndexByName('users_created')
            ->update();
    }
}
