<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Model\Table\UsersTable;
use App\Test\TestCase\PermissionsTestTrait;
use App\Test\TestCase\UserFactoryTrait;
use Cake\TestSuite\EmailTrait;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Cake\Utility\Hash;
use CakeDC\Users\Model\Entity\User;
use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

/**
 * Enabling / disabling 2FA from the own profile: current password first, TOTP confirmed with a
 * code, the key registered by the browser; an email on every change; methods are independent.
 */
class TwoFactorSetupTest extends TestCase
{
    use EmailTrait;
    use IntegrationTestTrait;
    use PermissionsTestTrait;
    use UserFactoryTrait;

    protected array $fixtures = ['app.Users', 'app.Roles', 'app.Permissions', 'app.RolesPermissions'];

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->enableRetainFlashMessages();
        $this->user = $this->createUser(['username' => 'ana', 'email' => 'ana@example.test']);
        $this->signIn($this->user);
    }

    /**
     * Value of the session after the last request. Read from `$_SESSION`: in CLI the test's
     * session object is not the one the app used and reading it would start (and wipe) it.
     */
    private function sessionValue(string $path): mixed
    {
        return Hash::get($_SESSION ?? [], $path);
    }

    private function users(): UsersTable
    {
        /** @var \App\Model\Table\UsersTable $users */
        $users = $this->fetchTable('Users');

        return $users;
    }

    /**
     * @return list<string>
     */
    private function methods(): array
    {
        return $this->users()->twoFactorMethods((string)$this->user->id);
    }

    private function startTotp(string $password = 'Secret1234!'): ?string
    {
        $this->post('/two-factor/start', ['method' => 'totp', 'current_password' => $password]);
        $setup = $this->sessionValue('TwoFactorSetup');
        $this->session(['TwoFactorSetup' => $setup]);

        return is_array($setup) ? ($setup['secret'] ?? null) : null;
    }

    private function code(string $secret): string
    {
        return (new TwoFactorAuth(new EndroidQrCodeProvider()))->getCode($secret);
    }

    public function testProfileShowsTheCardWithBothMethods(): void
    {
        $this->get('/profile');

        $this->assertResponseOk();
        $this->assertResponseContains('Two-step verification');
        $this->assertResponseContains('Authenticator app');
        $this->assertResponseContains('Security key or passkey');
    }

    public function testWrongPasswordDoesNotStart(): void
    {
        $this->assertNull($this->startTotp('wrong'));
        $this->assertRedirect('/profile');
        $this->assertFlashMessage('The password is not correct.', 'twoFactor');
    }

    public function testRightPasswordShowsTheQrWithoutEnablingYet(): void
    {
        $secret = $this->startTotp();

        $this->assertNotNull($secret);
        $this->assertSame([], $this->methods(), 'Nothing stored until the code is confirmed');
        $this->get('/profile');
        $this->assertResponseContains('data:image/png;base64,');
        $this->assertResponseContains($secret, 'Secret shown for manual entry');
    }

    public function testWrongCodeDoesNotEnableAndRightCodeEnablesAndEmails(): void
    {
        $secret = (string)$this->startTotp();

        $this->post('/two-factor/confirm', ['code' => $this->code($secret) === '000000' ? '111111' : '000000']);
        $this->assertSame([], $this->methods());
        $this->assertNoMailSent();

        $this->post('/two-factor/confirm', ['code' => $this->code($secret)]);
        $this->assertSame(['totp'], $this->methods());
        $this->assertMailSentTo('ana@example.test');
        $this->assertMailContains('enabled');
        $this->assertNull($this->sessionValue('TwoFactorSetup'), 'Setup step cleared');
    }

    public function testAnActiveMethodCannotBeStartedAgainButTheOtherCan(): void
    {
        $this->users()->enableTotp((string)$this->user->id, 'ABCDEFGHIJKLMNOP');

        $this->post('/two-factor/start', ['method' => 'totp', 'current_password' => 'Secret1234!']);
        $this->assertFlashMessage('That method is already active.', 'twoFactor');

        $this->post('/two-factor/start', ['method' => 'webauthn', 'current_password' => 'Secret1234!']);
        $this->assertSame('webauthn', $this->sessionValue('TwoFactorSetup.method'));
    }

    public function testDisableNeedsThePasswordAndKeepsTheOtherMethod(): void
    {
        $id = (string)$this->user->id;
        $this->users()->enableTotp($id, 'ABCDEFGHIJKLMNOP');
        $this->users()->updateQuery()->set(['additional_data' => ['webauthn_credentials' => ['k' => []], 'webauthn_passkey' => true]], ['additional_data' => 'json'])
            ->where(['id' => $id])->execute();

        $this->post('/two-factor/disable', ['method' => 'totp', 'current_password' => 'wrong']);
        $this->assertSame(['totp', 'webauthn'], $this->methods());

        $this->post('/two-factor/disable', ['method' => 'totp', 'current_password' => 'Secret1234!']);
        $this->assertSame(['webauthn'], $this->methods());
        $this->assertMailContains('disabled');
    }

    public function testKeyOptionsNeedThePasswordStep(): void
    {
        $this->post('/two-factor/webauthn-options');
        $this->assertResponseCode(403);

        $this->post('/two-factor/start', ['method' => 'webauthn', 'current_password' => 'Secret1234!']);
        $this->session(['TwoFactorSetup' => $this->sessionValue('TwoFactorSetup')]);
        $this->post('/two-factor/webauthn-options');

        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $options = json_decode((string)$this->_response->getBody(), true);
        $this->assertSame('required', $options['authenticatorSelection']['residentKey'], 'Registered as a passkey');
        $this->assertSame('required', $options['authenticatorSelection']['userVerification']);
        $this->assertNotEmpty($options['challenge']);
    }

    public function testInvalidKeyRegistrationIsRejected(): void
    {
        $this->post('/two-factor/start', ['method' => 'webauthn', 'current_password' => 'Secret1234!']);
        $this->session(['TwoFactorSetup' => $this->sessionValue('TwoFactorSetup')]);

        $this->post('/two-factor/webauthn-register', ['id' => 'bogus', 'rawId' => 'bogus', 'type' => 'public-key', 'response' => []]);

        $this->assertResponseCode(422);
        $this->assertSame([], $this->methods());
    }

    public function testCancelClearsTheStepAndActionsArePostOnly(): void
    {
        $this->startTotp();
        $this->post('/two-factor/cancel');
        $this->assertNull($this->sessionValue('TwoFactorSetup'));

        $this->get('/two-factor/start');
        $this->assertResponseCode(405);
    }

    public function testProfileFormsCannotChangeTwoFactorFields(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $this->signIn($admin);
        $this->enableSecurityToken();

        $this->post('/users/edit/' . $this->user->id, [
            'username' => 'ana', 'email' => 'ana@example.test',
            'secret' => 'ATTACKERSECRET12', 'secret_verified' => '1',
        ]);

        $this->assertSame([], $this->methods(), 'A TOTP secret cannot be planted from a form');
    }
}
