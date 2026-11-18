<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Rbac\PermissionsSynchronizer;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * `bin/cake permissions sync`: real discovery + configured defaults.
 */
class PermissionsSyncCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    protected array $fixtures = ['app.Roles', 'app.Permissions', 'app.RolesPermissions', 'app.Users'];

    public function testSyncDiscoversActionsAndGrantsTheDefaults(): void
    {
        $this->exec('permissions sync');

        $this->assertExitSuccess();
        $this->assertOutputRegExp('/\d+ added, 0 orphaned, 0 restored/');
        $grants = PermissionsSynchronizer::fromConfig()->grants();
        $this->assertContains('CakeDC/Users.Users::index', $grants['editor']);
        $this->assertContains('Reports::users', $grants['editor']);
        $this->assertNotContains('CakeDC/Users.Users::edit', $grants['editor']);
        $this->assertContains('CakeDC/Users.Users::delete', $grants['admin']);
        $this->assertEmpty(preg_grep('/^(Roles|Permissions)::/', $grants['admin']), 'Roles/permissions screens: superadmin only');
    }

    public function testSecondRunIsIdempotent(): void
    {
        $this->exec('permissions sync');
        $this->exec('permissions sync');

        $this->assertOutputContains('0 added, 0 orphaned, 0 restored');
    }

    public function testResetOptionIsAvailable(): void
    {
        $this->exec('permissions sync --reset');

        $this->assertExitSuccess();
        $this->assertOutputContains('Default grants re-applied');
    }
}
