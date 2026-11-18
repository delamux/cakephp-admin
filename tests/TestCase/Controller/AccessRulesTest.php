<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\PermissionsTestTrait;
use App\Test\TestCase\UserFactoryTrait;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App access rules (config/permissions.php) and the PDF users report.
 */
class AccessRulesTest extends TestCase
{
    use IntegrationTestTrait;
    use PermissionsTestTrait;
    use UserFactoryTrait;

    protected array $fixtures = ['app.Users', 'app.Roles', 'app.Permissions', 'app.RolesPermissions'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
    }

    private function loginAs(string $role, bool $superuser = false): void
    {
        $this->session(['Auth' => $this->createUser(['role' => $role, 'is_superuser' => $superuser])]);
    }

    public function testDashboardRequiresLogin(): void
    {
        $this->get('/');

        $this->assertRedirectContains('/login');
    }

    public function testAnyLoggedInUserCanSeeTheDashboard(): void
    {
        $this->loginAs('user');

        $this->get('/');

        $this->assertResponseOk();
    }

    public function testRegularUserCannotDownloadUsersReport(): void
    {
        $this->loginAs('user');

        $this->get('/reports/users.pdf');

        $this->assertRedirect();
        $this->assertHeaderNotContains('Content-Type', 'application/pdf');
    }

    public function testAdminDownloadsUsersReportAsPdf(): void
    {
        $this->loginAs('admin');

        $this->get('/reports/users.pdf');

        $this->assertResponseOk();
        $this->assertContentType('application/pdf');
        $this->assertStringStartsWith('%PDF', (string)$this->_response->getBody());
        $this->assertHeaderContains('Content-Disposition', 'users-report-');
    }

    public function testRegularUserCannotManageUsers(): void
    {
        $this->loginAs('user');

        $this->get('/users/index');

        $this->assertRedirect();
    }

    public function testExpiredSessionAsksToSignInInsteadOfAccessDenied(): void
    {
        $this->enableRetainFlashMessages();

        $this->get('/users/index');

        $this->assertRedirectContains('/login');
        $this->assertFlashMessage('Please sign in to continue.');
    }

    public function testLoggedInWithoutPermissionGetsAccessDenied(): void
    {
        $this->enableRetainFlashMessages();
        $this->loginAs('user');

        $this->get('/users/index');

        $this->assertFlashMessage('You don\'t have permission to open that page.');
    }

    public function testEditorSeesUsersAndReportReadOnly(): void
    {
        $this->loginAs('editor');
        $other = $this->createUser(['role' => 'user']);

        $this->get('/users/index');
        $this->assertResponseOk();
        $this->get('/users/view/' . $other->id);
        $this->assertResponseOk();
        $this->get('/reports/users.pdf');
        $this->assertContentType('application/pdf');

        $this->get('/users/edit/' . $other->id);
        $this->assertRedirect();
        $this->get('/users/add');
        $this->assertRedirect();
    }
}
