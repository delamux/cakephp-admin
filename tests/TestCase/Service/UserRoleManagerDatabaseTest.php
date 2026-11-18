<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\UserRoleManager;
use Cake\TestSuite\TestCase;

/**
 * The role whitelist comes from the roles table.
 */
class UserRoleManagerDatabaseTest extends TestCase
{
    protected array $fixtures = ['app.Roles', 'app.Users', 'app.Permissions', 'app.RolesPermissions'];

    public function testRolesComeFromTheDatabase(): void
    {
        $roles = $this->fetchTable('Roles');
        $roles->saveOrFail($roles->newEntity(['slug' => 'support', 'name' => 'Support']));

        $manager = UserRoleManager::fromConfig();

        $this->assertSame(
            ['superadmin' => 'Superadmin', 'admin' => 'Administrator', 'editor' => 'Editor', 'user' => 'User', 'support' => 'Support'],
            $manager->roles(),
        );
        $this->assertArrayHasKey('support', $manager->assignableRoles(['role' => 'admin']));
        $this->assertArrayNotHasKey('superadmin', $manager->assignableRoles(['role' => 'admin']));
    }

    public function testRenamedRoleLabelIsUsed(): void
    {
        $this->fetchTable('Roles')->updateAll(['name' => 'Content editor'], ['slug' => 'editor']);

        $this->assertSame('Content editor', UserRoleManager::fromConfig()->roles()['editor']);
    }
}
