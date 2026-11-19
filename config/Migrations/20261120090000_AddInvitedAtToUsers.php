<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Invitations (App\UseCase\Users\InviteUser): when the account was invited by an admin. A pending
 * invitation is `invited_at` set + `active` false; the link uses CakeDC's `token` / `token_expires`.
 */
class AddInvitedAtToUsers extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('users')
            ->addColumn('invited_at', 'datetime', ['null' => true, 'default' => null])
            ->update();
    }
}
