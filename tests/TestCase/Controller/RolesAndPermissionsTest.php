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
        $this->signIn($this->createUser(['role' => 'superadmin', 'is_superuser' => true]));
    }

    private function roleExists(string $slug): bool
    {
        return $this->fetchTable('Roles')->exists(['slug' => $slug]);
    }

    public function testAdminCannotOpenRolesOrPermissions(): void
    {
        $this->signIn($this->createUser(['role' => 'admin']));

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

    public function testOneTabPerRoleWithASelectPerActionAndNoSuperadmin(): void
    {
        $this->loginAsSuperadmin();

        $this->get('/permissions');

        $this->assertResponseOk();
        $this->assertResponseContains('id="permissions-form-editor"');
        $this->assertResponseNotContains('id="permissions-form-superadmin"');
        $this->assertResponseContains('name="grants[CakeDC/Users.Users::edit]"');
        $this->assertResponseContains('value="owner"', 'Account actions offer the owner rule');
        $this->assertResponseContains('Owner only');
    }

    public function testSavingATabReplacesThatRoleAndTakesEffect(): void
    {
        $this->loginAsSuperadmin();
        $admin = PermissionsSynchronizer::fromConfig()->rules()['admin'];

        $this->post('/permissions', ['role' => 'editor', 'grants' => [
            'CakeDC/Users.Users::index' => 'allow',
            'CakeDC/Users.Users::edit' => 'owner',
            'Dashboard::index' => 'allow',
        ]]);

        $this->assertRedirect(['controller' => 'Permissions', 'action' => 'index', '?' => ['role' => 'editor']]);
        $rules = PermissionsSynchronizer::fromConfig()->rules();
        $this->assertSame('owner', $rules['editor']['CakeDC/Users.Users::edit']);
        $this->assertArrayNotHasKey('CakeDC/Users.Users::view', $rules['editor'], 'Missing from the form = not allowed');
        $this->assertSame($admin, $rules['admin'], 'Only the submitted role changes');

        // RBAC reads the new grants and their rules
        $editor = $this->createUser(['role' => 'editor']);
        $target = $this->createUser(['role' => 'user']);
        $this->signIn($editor);
        $this->get('/users/view/' . $target->id);
        $this->assertResponseCode(302, 'view was removed from the editor');
        $this->get('/users/edit/' . $target->id);
        $this->assertResponseCode(302, 'owner: not another account');
        $this->get('/users/edit/' . $editor->id);
        $this->assertResponseOk();
    }

    public function testAccessControlScreensCannotBeGrantedFromTheMatrix(): void
    {
        $this->loginAsSuperadmin();

        $this->post('/permissions', ['role' => 'admin', 'grants' => ['Permissions::index' => 'allow', 'Roles::edit' => 'allow', 'Dashboard::index' => 'allow']]);

        $admin = PermissionsSynchronizer::fromConfig()->grants()['admin'];
        $this->assertEmpty(preg_grep('/^(Roles|Permissions)::/', $admin), 'No self-escalation path');
        $this->assertNotContains('CakeDC/Users.Users::edit', $admin, 'Editable rows missing from the form are revoked');
    }

    public function testSystemActionsAreReadOnlyBehindAButton(): void
    {
        $this->loginAsSuperadmin();

        $this->get('/permissions');

        $this->assertResponseNotContains('name="grants[Permissions::index]"', 'Superadmin only');
        $this->assertResponseNotContains('name="grants[CakeDC/Users.Users::login]"', 'Public: config/permissions.php');
        $this->assertResponseContains('name="grants[Dashboard::index]"', 'Moved to the database: editable');
        $this->assertMatchesRegularExpression('/<input type="checkbox" role="switch"[^>]+name="grants\\[Dashboard::index\\]"/', $this->_response->getBody()->__toString(), 'No rule can decide it: a switch');
        $this->assertResponseContains('id="system-actions" hidden');
        $this->assertResponseContains('data-reveal="system-actions"');
        $this->assertResponseContains('Public (no sign-in)');
        $this->assertResponseContains('Every signed-in user');
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
