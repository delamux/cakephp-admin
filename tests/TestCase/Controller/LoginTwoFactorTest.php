<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Model\Table\UsersTable;
use App\Test\TestCase\PermissionsTestTrait;
use App\Test\TestCase\UserFactoryTrait;
use App\Test\TestCase\WebauthnTestTrait;
use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Cake\Utility\Hash;
use CakeDC\Users\Model\Entity\User;
use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

/**
 * Login with 2FA: the session only opens after the second step (TOTP code, or the key when it is
 * the only method). Users without 2FA sign in as before.
 */
class LoginTwoFactorTest extends TestCase
{
    use IntegrationTestTrait;
    use PermissionsTestTrait;
    use UserFactoryTrait;
    use WebauthnTestTrait;

    protected array $fixtures = ['app.Users', 'app.Roles', 'app.Permissions', 'app.RolesPermissions'];

    private const SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->onWebauthnHost();
    }

    private function users(): UsersTable
    {
        /** @var \App\Model\Table\UsersTable $users */
        $users = $this->fetchTable('Users');

        return $users;
    }

    /**
     * Session after the last request (see TwoFactorSetupTest::sessionValue()).
     */
    private function sessionValue(string $path): mixed
    {
        return Hash::get($_SESSION ?? [], $path);
    }

    /**
     * Carries the session of the last request to the next one, as a browser cookie would: the
     * whole session is replaced (a destroyed session must not keep old keys).
     */
    private function keepSession(): void
    {
        $this->_session = $_SESSION ?? [];
    }

    private function logIn(User $user): void
    {
        $this->post('/login', ['username' => $user->username, 'password' => 'Secret1234!']);
    }

    public function testWithoutTwoFactorTheLoginOpensTheSession(): void
    {
        $user = $this->createUser();

        $this->logIn($user);

        $this->assertRedirectContains('/');
        $this->assertSame($user->id, $this->sessionValue('Auth.id'));
    }

    public function testTotpUserGoesToVerifyAndOnlyTheRightCodeLogsIn(): void
    {
        $user = $this->createUser();
        $this->users()->enableTotp((string)$user->id, self::SECRET);

        $this->logIn($user);
        $this->assertRedirectContains('/verify');
        $this->assertNull($this->sessionValue('Auth'), 'No session before the second step');

        $this->keepSession();
        $this->get('/verify');
        $this->assertResponseOk();
        $this->assertResponseContains('name="code"');
        $this->assertResponseNotContains('data:image/png', 'No enrolment during login');

        $this->keepSession();
        $this->post('/verify', ['code' => (new TwoFactorAuth(new EndroidQrCodeProvider()))->getCode(self::SECRET)]);
        $this->assertRedirectContains('/login');
        $this->keepSession();
        $this->get('/login');
        $this->assertSame($user->id, $this->sessionValue('Auth.id'), 'Logged in after the right code');
    }

    public function testACodeCannotBeUsedTwice(): void
    {
        $user = $this->createUser();
        $this->users()->enableTotp((string)$user->id, self::SECRET);
        $code = (new TwoFactorAuth(new EndroidQrCodeProvider()))->getCode(self::SECRET);

        $this->logIn($user);
        $this->keepSession();
        $this->post('/verify', ['code' => $code]);
        $this->keepSession();
        $this->get('/login');
        $this->assertSame($user->id, $this->sessionValue('Auth.id'), 'First use logs in');

        // Someone who saw the code (shoulder surfing, phishing proxy) tries it within its 30 s window
        $this->_session = [];
        $this->logIn($user);
        $this->keepSession();
        $this->post('/verify', ['code' => $code]);
        $this->keepSession();
        $this->get('/login');
        $this->assertNull($this->sessionValue('Auth'), 'The same code is rejected');
    }

    public function testTheCodeUsedToEnableTotpCannotBeUsedToLogIn(): void
    {
        $user = $this->createUser();
        $this->session(['Auth' => $user]);
        $this->post('/two-factor/start', ['method' => 'totp', 'current_password' => 'Secret1234!']);
        $secret = (string)$this->sessionValue('TwoFactorSetup.secret');
        $this->keepSession();
        $code = (new TwoFactorAuth(new EndroidQrCodeProvider()))->getCode($secret);
        $this->post('/two-factor/confirm', ['code' => $code]);
        $this->assertSame(['totp'], $this->users()->twoFactorMethods((string)$user->id));

        $this->_session = [];
        $this->logIn($user);
        $this->keepSession();
        $this->post('/verify', ['code' => $code]);
        $this->keepSession();
        $this->get('/login');

        $this->assertNull($this->sessionValue('Auth'));
    }

    public function testWrongCodeDoesNotLogIn(): void
    {
        $user = $this->createUser();
        $this->users()->enableTotp((string)$user->id, self::SECRET);
        $this->logIn($user);
        $this->keepSession();

        $this->post('/verify', ['code' => '000000']);
        $this->keepSession();
        $this->get('/login');

        $this->assertNull($this->sessionValue('Auth'));
    }

    public function testVerifyWithoutThePasswordStepGoesBackToLogin(): void
    {
        $this->get('/verify');

        $this->assertRedirectContains('/login');
    }

    public function testKeyUserIsAskedForTheKeyOnTheAppAction(): void
    {
        $user = $this->createUser();
        $this->storeKey((string)$user->id);

        $this->logIn($user);
        $this->assertRedirectContains('/webauthn2fa');
        $this->assertNull($this->sessionValue('Auth'));

        $this->keepSession();
        $this->get('/users/webauthn2fa');
        $this->assertResponseOk();
        $this->assertResponseContains('data-webauthn-authenticate');
        $this->assertResponseContains('/webauthn-login/authenticate', 'Verified with the app adapter');
    }

    public function testKeyVerificationRejectsMissingStepAndInvalidCredentials(): void
    {
        $this->post('/webauthn-login/authenticate', ['id' => 'x']);
        $this->assertResponseCode(422);

        $user = $this->createUser();
        $this->storeKey((string)$user->id);
        $this->logIn($user);
        $this->keepSession();
        $this->post('/webauthn-login/authenticate', ['id' => 'bogus', 'rawId' => 'bogus', 'type' => 'public-key', 'response' => []]);

        $this->assertResponseCode(422);
        $this->assertNull($this->sessionValue('Auth'));
    }

    public function testAValidKeyFinishesTheLogin(): void
    {
        $user = $this->createUser();
        $this->storeKey((string)$user->id);
        $this->logIn($user);
        $this->keepSession();
        $this->get('/users/webauthn2fa-authenticate-options');
        $this->assertResponseOk();
        $challenge = json_decode((string)$this->_response->getBody(), true)['challenge'];

        $this->keepSession();
        $this->post('/webauthn-login/authenticate', $this->assertion(
            (string)base64_decode(strtr($challenge, '-_', '+/'), true),
            null,
        ));
        $this->assertResponseOk();
        $this->assertStringContainsString('/login', (string)json_decode((string)$this->_response->getBody(), true)['redirectUrl']);

        $this->keepSession();
        $this->get('/login');
        $this->assertSame($user->id, $this->sessionValue('Auth.id'), 'Logged in after the key');
    }

    public function testTheEmailedSignInLinkAlsoGoesThroughTwoFactor(): void
    {
        $this->get('/login');

        $this->assertFalse(Configure::read('Auth.Authenticators.OneTimeToken.skipTwoFactorVerify'));
    }
}
