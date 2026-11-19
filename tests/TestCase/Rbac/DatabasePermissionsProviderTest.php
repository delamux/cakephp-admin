<?php
declare(strict_types=1);

namespace App\Test\TestCase\Rbac;

use App\Rbac\DatabasePermissionsProvider;
use App\Rbac\PermissionsCache;
use App\Rbac\PermissionsSynchronizer;
use App\Test\TestCase\PermissionsTestTrait;
use App\Test\TestCase\UserFactoryTrait;
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
    use UserFactoryTrait;

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
            // admin edit/delete need the account id (rules): testAdminAccountLimitsAreRulesNow
            'admin changes roles' => ['admin', '', 'UserRoles', 'edit', null, true],
            'admin report pdf' => ['admin', '', 'Reports', 'users', 'pdf', true],
            'admin no roles screen' => ['admin', '', 'Roles', 'index', null, false],
            'admin no permissions screen' => ['admin', '', 'Permissions', 'index', null, false],
            'editor lists users' => ['editor', 'CakeDC/Users', 'Users', 'index', null, true],
            'editor views users' => ['editor', 'CakeDC/Users', 'Users', 'view', null, true],
            'editor report pdf' => ['editor', '', 'Reports', 'users', 'pdf', true],
            'editor cannot edit' => ['editor', 'CakeDC/Users', 'Users', 'edit', null, false],
            'editor cannot change roles' => ['editor', '', 'UserRoles', 'edit', null, false],
            'user dashboard (every role)' => ['user', '', 'Dashboard', 'index', null, true],
            'user profile (system)' => ['user', 'CakeDC/Users', 'Users', 'profile', null, true],
            'user no users list' => ['user', 'CakeDC/Users', 'Users', 'index', null, false],
            'user no report' => ['user', '', 'Reports', 'users', 'pdf', false],
            'superadmin role: everything (system)' => ['superadmin', '', 'Permissions', 'index', null, true],
            'unknown role: nothing (grants live in the database)' => ['support', '', 'Dashboard', 'index', null, false],
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

    private function allowedOn(array $user, string $controller, string $action, string $id): bool
    {
        $request = (new ServerRequest())->withAttribute('params', [
            'plugin' => $controller === 'Users' ? 'CakeDC/Users' : null, 'prefix' => null,
            'controller' => $controller, 'action' => $action, '_ext' => null, 'pass' => [$id],
        ]);

        return (new Rbac(['permissions_provider_class' => DatabasePermissionsProvider::class, 'log' => false]))
            ->checkPermissions($user + ['is_superuser' => false], $request);
    }

    public function testAdminEditsAndDeletesAccountsByRolePermission(): void
    {
        // Who is off limits (superadmin/admin accounts, own account) is a fixed protection in code:
        // App\Service\UserRoleManager::isProtected(), see RoleManagementTest
        $regular = $this->createUser(['role' => 'user']);

        $this->assertTrue($this->allowedOn(['id' => 'a1', 'role' => 'admin'], 'Users', 'edit', $regular->id));
        $this->assertTrue($this->allowedOn(['id' => 'a1', 'role' => 'admin'], 'Users', 'delete', $regular->id));
    }

    public function testFormerAllRolesRulesAreDatabaseGrants(): void
    {
        $me = $this->createUser(['role' => 'editor']);
        $other = $this->createUser(['role' => 'editor']);
        $editor = ['id' => $me->id, 'role' => 'editor'];

        $this->assertTrue($this->allowed('editor', '', 'TwoFactor', 'start'));
        $this->assertTrue($this->allowedOn($editor, 'Users', 'resetOneTimePasswordAuthenticator', $me->id), 'owner');
        $this->assertFalse($this->allowedOn($editor, 'Users', 'resetOneTimePasswordAuthenticator', $other->id));

        PermissionsSynchronizer::fromConfig()->setGrants('editor', ['TwoFactor::cancel']);
        $this->rbac = new Rbac(['permissions_provider_class' => DatabasePermissionsProvider::class, 'log' => false]);
        $this->assertFalse($this->allowed('editor', '', 'Dashboard', 'index'), 'Now they can be revoked');
    }

    public function testEditorAllowedToEditOnlyTheOwnAccount(): void
    {
        $me = $this->createUser(['role' => 'editor']);
        $other = $this->createUser(['role' => 'user']);
        PermissionsSynchronizer::fromConfig()->setGrants('editor', ['CakeDC/Users.Users::edit' => 'owner']);

        $this->assertTrue($this->allowedOn(['id' => $me->id, 'role' => 'editor'], 'Users', 'edit', $me->id));
        $this->assertFalse($this->allowedOn(['id' => $me->id, 'role' => 'editor'], 'Users', 'edit', $other->id));
    }

    public function testSystemRulesComeFirst(): void
    {
        $rules = (new DatabasePermissionsProvider())->getPermissions();

        $this->assertTrue($rules[0]['bypassAuth'] ?? false, 'Public actions first');
        $this->assertNotEmpty(array_filter($rules, fn(array $r): bool => ($r['controller'] ?? null) === 'Reports' && in_array('editor', (array)($r['role'] ?? []), true)));
    }
}
