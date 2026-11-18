<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Existing CakeDC superusers get the `superadmin` role (role ⇔ is_superuser are kept in sync
 * from now on by App\Model\Table\UsersTable::beforeSave()).
 */
class SuperadminRole extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->execute("UPDATE users SET role = 'superadmin' WHERE is_superuser = true");
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->execute("UPDATE users SET role = 'admin' WHERE role = 'superadmin'");
    }
}
