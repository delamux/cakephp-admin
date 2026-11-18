<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Time window (30 s step) of the last accepted TOTP code: a code cannot be used twice
 * (App\Model\Table\UsersTable::claimTotpStep()).
 */
class AddTotpLastStepToUsers extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('users')
            ->addColumn('totp_last_step', 'biginteger', ['null' => true, 'default' => null])
            ->update();
    }
}
