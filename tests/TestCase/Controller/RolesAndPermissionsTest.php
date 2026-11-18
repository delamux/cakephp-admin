<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Rbac\PermissionsSynchronizer;
use App\Test\TestCase\PermissionsTestTrait;
use App\Test\TestCase\UserFactoryTrait;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Roles CRUD and permissions matrix (superadmin only).
 */
class RolesAndPermissionsTest extends TestCase
{
    use IntegrationTestTrait;
    use PermissionsTestTrait;
    use UserFactoryTrait;

    protected array $fixtures = ['app.Users', 'app.Roles', 'app.Permissions', 'app.RolesPermissions'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        // Matrix checkboxes are plain inputs, unlocked in PermissionsController (FormProtection)
        $this->setUnlockedFields(['grants']);
        $this->enableRetainFlashMessages();
    }

    private function loginAsSuperadmin(): void
    {
        $this->session(['Auth' => $this->createUser(['role' => 'superadmin', 'is_superuser' => true])]);
    }

    private function roleExists(string $slug): bool
    {
        return $this->fetchTable('Roles')->exists(['slug' => $slug]);
    }

    public function testAdminCannotOpenRolesOrPermissions(): void
    {
        $this->session(['Auth' => $this->createUser(['role' => 'admin'])]);

        $this->get('/roles');
        $this->assertRedirect();
        $this->get('/permissions');
        $this->assertRedirect();
    }

    public function testSuperadminListsAndCreatesRoles(): void
    {
        $this->loginAsSuperadmin();

        $this->get('/roles');
        $this->assertResponseOk();
        $this->assertResponseContains('Editor');

        $this->post('/roles/add', ['slug' => 'support', 'name' => 'Support']);
        $this->assertRedirect(['controller' => 'Roles', 'action' => 'index']);
        $this->assertTrue($this->roleExists('support'));
    }

    public function testProtectedRoleIsNeverDeleted(): void
    {
        $this->loginAsSuperadmin();
        $id = $this->fetchTable('Roles')->findBySlug('superadmin')->firstOrFail()->id;

        $this->post('/roles/delete/' . $id);

        $this->assertTrue($this->roleExists('superadmin'));
        $this->assertFlashMessage('This role is protected and cannot be deleted.');
    }

    public function testRoleInUseIsNotDeletedButAnUnusedOneIs(): void
    {
        $this->loginAsSuperadmin();
        $this->createUser(['role' => 'editor']);
        $roles = $this->fetchTable('Roles');

        $this->post('/roles/delete/' . $roles->findBySlug('editor')->firstOrFail()->id);
        $this->assertTrue($this->roleExists('editor'));

        $this->post('/roles/delete/' . $roles->findBySlug('user')->firstOrFail()->id);
        $this->assertFalse($this->roleExists('user'));
    }

    public function testMatrixListsActionsPerRoleWithoutSuperadminColumn(): void
    {
        $this->loginAsSuperadmin();

        $this->get('/permissions');

        $this->assertResponseOk();
        $this->assertResponseContains('name="grants[editor][]"');
        $this->assertResponseNotContains('name="grants[superadmin][]"');
        $this->assertResponseContains('value="CakeDC/Users.Users::edit"');
        $this->assertResponseContains('Public', 'Public (system) actions are flagged');
    }

    public function testSavingTheMatrixReplacesGrantsAndTakesEffect(): void
    {
        $this->loginAsSuperadmin();

        $this->post('/permissions', ['grants' => [
            'editor' => ['CakeDC/Users.Users::index', 'CakeDC/Users.Users::edit'],
            'superadmin' => ['Dashboard::index'],
        ]]);

        $this->assertRedirect(['controller' => 'Permissions', 'action' => 'index']);
        $grants = PermissionsSynchronizer::fromConfig()->grants();
        $this->assertSame(['CakeDC/Users.Users::edit', 'CakeDC/Users.Users::index'], $grants['editor']);
        $this->assertArrayNotHasKey('superadmin', $grants, 'Superadmin is not editable');
        $this->assertNotContains('CakeDC/Users.Users::edit', $grants['admin'] ?? [], 'Roles missing from the form lose their editable grants');
        $this->assertContains('Dashboard::index', $grants['admin'] ?? [], 'Locked (system) rows are kept');

        // The editor lost `view` (RBAC reads the new allow-list)
        $editor = $this->createUser(['role' => 'editor']);
        $target = $this->createUser(['role' => 'user']);
        $this->session(['Auth' => $editor]);
        $this->get('/users/view/' . $target->id);
        $this->assertResponseCode(302, 'view was removed from the editor');
    }

    public function testAccessControlScreensCannotBeGrantedFromTheMatrix(): void
    {
        $this->loginAsSuperadmin();

        $this->post('/permissions', ['grants' => ['admin' => ['Permissions::index', 'Roles::edit', 'Dashboard::index']]]);

        $admin = PermissionsSynchronizer::fromConfig()->grants()['admin'];
        $this->assertEmpty(preg_grep('/^(Roles|Permissions)::/', $admin), 'No self-escalation path');
        $this->assertNotContains('CakeDC/Users.Users::edit', $admin, 'Unticked editable rows are revoked');
    }

    public function testSystemRowsAreShownAsLockedNotAsCheckboxes(): void
    {
        $this->loginAsSuperadmin();

        $this->get('/permissions');

        $this->assertResponseNotContains('value="Dashboard::index"', 'Dashboard is a system rule for every role');
        $this->assertResponseNotContains('value="Permissions::index"', 'Superadmin only');
        $this->assertResponseContains('All roles');
        $this->assertResponseContains('Superadmin only');
    }

    public function testSyncButtonRefreshesTheCatalogue(): void
    {
        $this->loginAsSuperadmin();
        $this->fetchTable('Permissions')->deleteAll(['controller' => 'Reports']);

        $this->post('/permissions/sync');

        $this->assertRedirect(['controller' => 'Permissions', 'action' => 'index']);
        $this->assertTrue($this->fetchTable('Permissions')->exists(['controller' => 'Reports', 'action' => 'users']));
    }
}
