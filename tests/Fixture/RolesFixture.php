<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * The 4 default roles (same as the CreateRolesAndPermissions migration).
 */
class RolesFixture extends TestFixture
{
    public string $table = 'roles';

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        $now = '2026-11-18 00:00:00';
        $this->records = [
            ['slug' => 'superadmin', 'name' => 'Superadmin', 'protected' => true, 'created' => $now, 'modified' => $now],
            ['slug' => 'admin', 'name' => 'Administrator', 'protected' => false, 'created' => $now, 'modified' => $now],
            ['slug' => 'editor', 'name' => 'Editor', 'protected' => false, 'created' => $now, 'modified' => $now],
            ['slug' => 'user', 'name' => 'User', 'protected' => false, 'created' => $now, 'modified' => $now],
        ];
        parent::init();
    }
}
