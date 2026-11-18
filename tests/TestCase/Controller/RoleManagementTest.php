<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\PermissionsTestTrait;
use App\Test\TestCase\UserFactoryTrait;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use CakeDC\Users\Model\Entity\User;

/**
 * Role management from the users CRUD and the account-management guards.
 */
class RoleManagementTest extends TestCase
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
        // CakeDC's UsersController loads FormProtectionComponent
        $this->enableSecurityToken();
        $this->enableRetainFlashMessages();
    }

    private function loginAs(User $user): void
    {
        $this->session(['Auth' => $user]);
    }

    private function reload(User $user): User
    {
        /** @var \CakeDC\Users\Model\Entity\User */
        return TableRegistry::getTableLocator()->get('CakeDC/Users.Users')->get($user->id);
    }

    private function exists(User $user): bool
    {
        return TableRegistry::getTableLocator()->get('CakeDC/Users.Users')->exists(['id' => $user->id]);
    }

    public function testRoleFormUrlIsGeneratedForTheUser(): void
    {
        $this->loadRoutes();
        $this->assertSame('/user-roles/abc-123', Router::url(['_name' => 'user-roles:edit', 'id' => 'abc-123']));
    }

    public function testInvalidUserIdIsNotFoundNotADatabaseError(): void
    {
        // Superadmin bypasses RBAC (an admin is denied earlier: unknown action, not in the allow-list)
        $this->loginAs($this->createUser(['role' => 'superadmin', 'is_superuser' => true]));

        $this->post('/user-roles/not-a-uuid', ['role' => 'admin']);

        $this->assertResponseCode(404);
    }

    public function testAdminPromotesAUserToAdmin(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $user = $this->createUser(['role' => 'user']);
        $this->loginAs($admin);

        $this->post('/user-roles/' . $user->id, ['role' => 'admin']);

        $this->assertRedirectContains('/users/view/' . $user->id);
        $this->assertSame('admin', $this->reload($user)->role);
        $this->assertFlashElement('flash/success');
    }

    public function testAdminCannotChangeOwnRole(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $this->loginAs($admin);

        $this->post('/user-roles/' . $admin->id, ['role' => 'user']);

        $this->assertSame('admin', $this->reload($admin)->role);
        $this->assertFlashMessage('You cannot change your own role.');
    }

    public function testUnknownRoleIsRejected(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $user = $this->createUser(['role' => 'user']);
        $this->loginAs($admin);

        $this->post('/user-roles/' . $user->id, ['role' => 'root']);

        $this->assertSame('user', $this->reload($user)->role);
    }

    public function testRegularUserCannotChangeRoles(): void
    {
        $user = $this->createUser(['role' => 'user']);
        $other = $this->createUser(['role' => 'user']);
        $this->loginAs($user);

        $this->post('/user-roles/' . $other->id, ['role' => 'admin']);

        $this->assertRedirect();
        $this->assertSame('user', $this->reload($other)->role);
    }

    public function testRoleChangeNeverTouchesSuperuserFlag(): void
    {
        $super = $this->createUser(['role' => 'user', 'is_superuser' => true]);
        $user = $this->createUser(['role' => 'user']);
        $this->loginAs($super);

        $this->post('/user-roles/' . $user->id, ['role' => 'admin', 'is_superuser' => true]);

        $this->assertSame('admin', $this->reload($user)->role);
        $this->assertFalse($this->reload($user)->is_superuser);
    }

    public function testSuperuserFlagAndSuperadminRoleStayInSyncWhenSaving(): void
    {
        // Same as `bin/cake users add_superuser`: role admin + is_superuser
        $created = $this->createUser(['role' => 'admin', 'is_superuser' => true]);

        $this->assertSame('superadmin', $this->reload($created)->role);
    }

    public function testAdminCannotGrantSuperadmin(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $user = $this->createUser(['role' => 'user']);
        $this->loginAs($admin);

        $this->post('/user-roles/' . $user->id, ['role' => 'superadmin']);

        $this->assertSame('user', $this->reload($user)->role);
        $this->assertFalse($this->reload($user)->is_superuser);
    }

    public function testSuperadminGrantsAndRevokesSuperadmin(): void
    {
        $super = $this->createUser(['role' => 'superadmin', 'is_superuser' => true]);
        $user = $this->createUser(['role' => 'user']);
        $this->loginAs($super);

        $this->post('/user-roles/' . $user->id, ['role' => 'superadmin']);
        $this->assertTrue($this->reload($user)->is_superuser, 'Granting superadmin sets the CakeDC flag');

        $this->post('/user-roles/' . $user->id, ['role' => 'editor']);
        $this->assertSame('editor', $this->reload($user)->role);
        $this->assertFalse($this->reload($user)->is_superuser, 'Leaving superadmin clears the flag');
    }

    public function testEditorCannotChangeRolesOrDelete(): void
    {
        $editor = $this->createUser(['role' => 'editor']);
        $user = $this->createUser(['role' => 'user']);
        $this->loginAs($editor);

        $this->post('/user-roles/' . $user->id, ['role' => 'admin']);
        $this->assertSame('user', $this->reload($user)->role);

        $this->post('/users/delete/' . $user->id);
        $this->assertTrue($this->exists($user));
    }

    public function testDemotedAdminLosesAccessDespiteAStaleSession(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $user = $this->createUser(['role' => 'user']);
        $this->loginAs($admin);
        // Another superadmin demotes them while their session still says "admin"
        TableRegistry::getTableLocator()->get('CakeDC/Users.Users')->updateAll(['role' => 'user'], ['id' => $admin->id]);

        $this->post('/user-roles/' . $user->id, ['role' => 'admin']);
        $this->assertSame('user', $this->reload($user)->role);

        $this->post('/users/delete/' . $user->id);
        $this->assertTrue($this->exists($user));
    }

    public function testDemotedAdminIsDeniedByRbacOnTheVeryFirstRequest(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $this->loginAs($admin);
        TableRegistry::getTableLocator()->get('CakeDC/Users.Users')->updateAll(['role' => 'user'], ['id' => $admin->id]);

        // Users::index is only protected by the RBAC rules (no controller guard)
        $this->get('/users/index');

        $this->assertRedirect();
        $this->assertFlashMessage('You don\'t have permission to open that page.');
    }

    public function testPromotedUserGetsAccessOnTheVeryFirstRequest(): void
    {
        $user = $this->createUser(['role' => 'user']);
        $this->loginAs($user);
        TableRegistry::getTableLocator()->get('CakeDC/Users.Users')->updateAll(['role' => 'editor'], ['id' => $user->id]);

        $this->get('/users/index');

        $this->assertResponseOk();
    }

    public function testDeletedAccountWithAnOpenSessionIsSignedOut(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $this->loginAs($admin);
        TableRegistry::getTableLocator()->get('CakeDC/Users.Users')->deleteAll(['id' => $admin->id]);

        $this->get('/');

        $this->assertRedirectContains('/login');
        $this->assertFlashMessage('Your session has ended. Please sign in again.');
        $this->assertSessionNotHasKey('Auth');
    }

    public function testDeactivatedAccountWithAnOpenSessionIsSignedOut(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $this->loginAs($admin);
        TableRegistry::getTableLocator()->get('CakeDC/Users.Users')->updateAll(['active' => false], ['id' => $admin->id]);

        $this->get('/users/index');

        $this->assertRedirectContains('/login');
        $this->assertFlashMessage('Your session has ended. Please sign in again.');
    }

    public function testAdminCannotEditASuperuser(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $super = $this->createUser(['role' => 'user', 'is_superuser' => true, 'email' => 'root@example.test']);
        $this->loginAs($admin);

        $this->post('/users/edit/' . $super->id, ['email' => 'attacker@example.test']);

        $this->assertRedirect();
        $this->assertSame('root@example.test', $this->reload($super)->email, 'Email change would allow an account takeover');
    }

    public function testAdminCannotDeleteASuperuser(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $super = $this->createUser(['role' => 'user', 'is_superuser' => true]);
        $this->loginAs($admin);

        $this->post('/users/delete/' . $super->id);

        $this->assertTrue($this->exists($super));
    }

    public function testNobodyDeletesTheirOwnAccountFromTheCrud(): void
    {
        $super = $this->createUser(['role' => 'user', 'is_superuser' => true]);
        $this->loginAs($super);

        $this->post('/users/delete/' . $super->id);

        $this->assertTrue($this->exists($super));
        $this->assertFlashMessage('You cannot delete your own account.');
    }

    public function testAdminStillDeletesRegularUsers(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $user = $this->createUser(['role' => 'user']);
        $this->loginAs($admin);

        $this->post('/users/delete/' . $user->id);

        $this->assertFalse($this->exists($user));
    }
}
