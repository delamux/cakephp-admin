<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Model\Table\UsersTable;
use App\Test\TestCase\PermissionsTestTrait;
use App\Test\TestCase\UserFactoryTrait;
use Cake\TestSuite\EmailTrait;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use CakeDC\Users\Model\Entity\User;

/**
 * Lost device: a superadmin removes another user's 2FA (both methods), never another superadmin's.
 */
class TwoFactorResetTest extends TestCase
{
    use EmailTrait;
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
        $this->enableRetainFlashMessages();
    }

    private function users(): UsersTable
    {
        /** @var \App\Model\Table\UsersTable $users */
        $users = $this->fetchTable('Users');

        return $users;
    }

    private function withTwoFactor(array $data = []): User
    {
        $user = $this->createUser($data);
        $this->users()->enableTotp((string)$user->id, 'ABCDEFGHIJKLMNOP');
        $this->users()->updateQuery()->set(['additional_data' => ['webauthn_credentials' => ['k' => []], 'webauthn_passkey' => true]], ['additional_data' => 'json'])
            ->where(['id' => $user->id])->execute();

        return $user;
    }

    private function superadmin(): User
    {
        return $this->createUser(['role' => 'superadmin', 'is_superuser' => true]);
    }

    public function testSuperadminResetsAUserAndTheUserIsEmailed(): void
    {
        $target = $this->withTwoFactor(['email' => 'target@example.test']);
        $this->session(['Auth' => $this->superadmin()]);

        $this->post('/two-factor/reset/' . $target->id);

        $this->assertRedirectContains('/users/view/' . $target->id);
        $this->assertSame([], $this->users()->twoFactorMethods((string)$target->id));
        $this->assertMailSentTo('target@example.test');
        $this->assertMailContains('removed the two-step verification');
    }

    public function testAnotherSuperadminIsNeverReset(): void
    {
        $other = $this->withTwoFactor(['role' => 'superadmin', 'is_superuser' => true]);
        $this->session(['Auth' => $this->superadmin()]);

        $this->post('/two-factor/reset/' . $other->id);

        $this->assertSame(['totp', 'webauthn'], $this->users()->twoFactorMethods((string)$other->id));
        $this->assertFlashMessage('The two-step verification of another superadmin cannot be reset.');
    }

    public function testOtherRolesCannotResetAndItIsPostOnly(): void
    {
        $target = $this->withTwoFactor();
        $this->session(['Auth' => $this->createUser(['role' => 'admin'])]);
        $this->post('/two-factor/reset/' . $target->id);
        $this->assertSame(['totp', 'webauthn'], $this->users()->twoFactorMethods((string)$target->id));

        $this->session(['Auth' => $this->superadmin()]);
        $this->get('/two-factor/reset/' . $target->id);
        $this->assertResponseCode(405);
    }

    public function testUserPageShowsStatusAndButtonOnlyForNonSuperadmins(): void
    {
        $target = $this->withTwoFactor();
        $other = $this->withTwoFactor(['role' => 'superadmin', 'is_superuser' => true]);
        $this->session(['Auth' => $this->superadmin()]);

        $this->get('/users/view/' . $target->id);
        $this->assertResponseOk();
        $this->assertResponseContains('Authenticator app');
        $this->assertResponseContains('/two-factor/reset/' . $target->id);

        $this->get('/users/view/' . $other->id);
        $this->assertResponseNotContains('/two-factor/reset/' . $other->id);
    }
}
