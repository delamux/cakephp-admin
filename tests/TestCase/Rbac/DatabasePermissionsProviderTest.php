<?php
declare(strict_types=1);

namespace App\Test\TestCase\Rbac;

use App\Rbac\DatabasePermissionsProvider;
use App\Rbac\PermissionsCache;
use App\Rbac\PermissionsSynchronizer;
use App\Test\TestCase\PermissionsTestTrait;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use CakeDC\Auth\Rbac\Rbac;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * RBAC rules from the database (system rules from config/permissions.php + allow-list).
 */
class DatabasePermissionsProviderTest extends TestCase
{
    use PermissionsTestTrait;

    protected array $fixtures = ['app.Roles', 'app.Permissions', 'app.RolesPermissions', 'app.Users'];

    private Rbac $rbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->rbac = new Rbac(['permissions_provider_class' => DatabasePermissionsProvider::class, 'log' => false]);
    }

    private function allowed(string $role, string $plugin, string $controller, string $action, ?string $ext = null): bool
    {
        $request = (new ServerRequest())->withAttribute('params', [
            'plugin' => $plugin === '' ? null : $plugin,
            'prefix' => null,
            'controller' => $controller,
            'action' => $action,
            '_ext' => $ext,
            'pass' => [],
        ]);

        return $this->rbac->checkPermissions(['id' => 'u1', 'role' => $role, 'is_superuser' => false], $request);
    }

    /**
     * Same decisions as the former config/permissions.php for the default roles.
     *
     * @return array<string, array{string, string, string, string, string|null, bool}>
     */
    public static function decisions(): array
    {
        return [
            'admin edits users' => ['admin', 'CakeDC/Users', 'Users', 'edit', null, true],
            'admin deletes users' => ['admin', 'CakeDC/Users', 'Users', 'delete', null, true],
            'admin changes roles' => ['admin', '', 'UserRoles', 'edit', null, true],
            'admin report pdf' => ['admin', '', 'Reports', 'users', 'pdf', true],
            'admin no roles screen' => ['admin', '', 'Roles', 'index', null, false],
            'admin no permissions screen' => ['admin', '', 'Permissions', 'index', null, false],
            'editor lists users' => ['editor', 'CakeDC/Users', 'Users', 'index', null, true],
            'editor views users' => ['editor', 'CakeDC/Users', 'Users', 'view', null, true],
            'editor report pdf' => ['editor', '', 'Reports', 'users', 'pdf', true],
            'editor cannot edit' => ['editor', 'CakeDC/Users', 'Users', 'edit', null, false],
            'editor cannot change roles' => ['editor', '', 'UserRoles', 'edit', null, false],
            'user dashboard (system)' => ['user', '', 'Dashboard', 'index', null, true],
            'user profile (system)' => ['user', 'CakeDC/Users', 'Users', 'profile', null, true],
            'user no users list' => ['user', 'CakeDC/Users', 'Users', 'index', null, false],
            'user no report' => ['user', '', 'Reports', 'users', 'pdf', false],
            'superadmin role: everything (system)' => ['superadmin', '', 'Permissions', 'index', null, true],
            'unknown role: dashboard only' => ['support', '', 'Dashboard', 'index', null, true],
            'unknown role: no users' => ['support', 'CakeDC/Users', 'Users', 'index', null, false],
        ];
    }

    #[DataProvider('decisions')]
    public function testDecisionsMatchTheFormerConfigFile(string $role, string $plugin, string $controller, string $action, ?string $ext, bool $expected): void
    {
        $this->assertSame($expected, $this->allowed($role, $plugin, $controller, $action, $ext));
    }

    public function testGrantingFromTheDatabaseTakesEffectImmediately(): void
    {
        $this->assertFalse($this->allowed('user', 'CakeDC/Users', 'Users', 'index'));

        PermissionsSynchronizer::fromConfig()->setGrants('user', ['CakeDC/Users.Users::index']);
        $rbac = new Rbac(['permissions_provider_class' => DatabasePermissionsProvider::class, 'log' => false]);
        $request = (new ServerRequest())->withAttribute('params', ['plugin' => 'CakeDC/Users', 'prefix' => null, 'controller' => 'Users', 'action' => 'index', '_ext' => null, 'pass' => []]);

        $this->assertTrue($rbac->checkPermissions(['id' => 'u1', 'role' => 'user'], $request), 'Cache was invalidated');
    }

    public function testOrphanActionsGrantNothing(): void
    {
        $this->fetchTable('Permissions')->updateAll(['orphan' => true], ['controller' => 'Reports']);
        PermissionsCache::clear();
        $rbac = new Rbac(['permissions_provider_class' => DatabasePermissionsProvider::class, 'log' => false]);
        $request = (new ServerRequest())->withAttribute('params', ['plugin' => null, 'prefix' => null, 'controller' => 'Reports', 'action' => 'users', '_ext' => 'pdf', 'pass' => []]);

        $this->assertFalse($rbac->checkPermissions(['id' => 'u1', 'role' => 'editor'], $request));
    }

    public function testSystemRulesComeFirst(): void
    {
        $rules = (new DatabasePermissionsProvider())->getPermissions();

        $this->assertTrue($rules[0]['bypassAuth'] ?? false, 'Public actions first');
        $this->assertNotEmpty(array_filter($rules, fn(array $r): bool => ($r['controller'] ?? null) === 'Reports' && in_array('editor', (array)($r['role'] ?? []), true)));
    }
}
