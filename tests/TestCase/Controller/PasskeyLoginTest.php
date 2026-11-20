<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Account\ActivityLog;
use App\Test\TestCase\PermissionsTestTrait;
use App\Test\TestCase\UserFactoryTrait;
use App\Test\TestCase\WebauthnTestTrait;
use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Cake\Utility\Hash;
use CakeDC\Users\Model\Entity\User;

/**
 * Passwordless login with a passkey: the browser finds the credential (discoverable), the server
 * identifies the user by the `userHandle`, checks the signature against a single-use challenge and
 * opens the session. Errors are generic (they never reveal whether an account exists).
 */
class PasskeyLoginTest extends TestCase
{
    use IntegrationTestTrait;
    use PermissionsTestTrait;
    use UserFactoryTrait;
    use WebauthnTestTrait;

    protected array $fixtures = ['app.Users', 'app.Roles', 'app.Permissions', 'app.RolesPermissions', 'app.AccountActivities'];

    private const GENERIC = 'Could not sign in with the passkey. Try again or sign in with your password.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->enableCsrfToken();
        $this->onWebauthnHost();
    }

    private function sessionValue(string $path): mixed
    {
        return Hash::get($_SESSION ?? [], $path);
    }

    /**
     * Requests options and returns the binary challenge (session carried to the next request).
     */
    private function challenge(): string
    {
        $this->post('/passkey/options');
        $options = json_decode((string)$this->_response->getBody(), true);
        $this->session($_SESSION ?? []);

        return (string)base64_decode(strtr((string)$options['challenge'], '-_', '+/'), true);
    }

    private function userWithPasskey(array $data = [], bool $passkey = true): User
    {
        $user = $this->createUser($data);
        $this->storeKey((string)$user->id, $passkey);

        return $user;
    }

    public function testOptionsAreForAnyUserAndRequireVerification(): void
    {
        $this->post('/passkey/options');

        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $options = json_decode((string)$this->_response->getBody(), true);
        $this->assertNotEmpty($options['challenge']);
        $this->assertSame('required', $options['userVerification']);
        $this->assertSame(self::WEBAUTHN_HOST, $options['rpId']);
        $this->assertEmpty($options['allowCredentials'] ?? [], 'Discoverable: no user is known yet');
    }

    public function testAValidPasskeyLogsIn(): void
    {
        $user = $this->userWithPasskey();

        $this->post('/passkey/login', $this->assertion($this->challenge(), (string)$user->id));

        $this->assertResponseOk();
        $this->assertSame($user->id, $this->sessionValue('Auth.id'));
        $this->assertSame('/', json_decode((string)$this->_response->getBody(), true)['redirectUrl']);
        $this->assertNotNull($this->fetchTable('Users')->get($user->id)->get('last_login'), 'Last login recorded');
        $activity = $this->fetchTable('AccountActivities')->find()->where(['user_id' => $user->id])->all()->extract('event')->toList();
        $this->assertSame([ActivityLog::PASSKEY_LOGIN], $activity, 'Account activity');
    }

    public function testTheChallengeIsSingleUseAndExpires(): void
    {
        $user = $this->userWithPasskey();
        $assertion = $this->assertion($this->challenge(), (string)$user->id);
        $this->post('/passkey/login', $assertion);
        $this->assertResponseOk();

        $this->session(['Auth' => null]);
        $this->post('/passkey/login', $assertion);
        $this->assertResponseCode(422, 'Replay rejected');

        $assertion = $this->assertion($this->challenge(), (string)$user->id);
        $session = $_SESSION ?? [];
        $session['Passkey']['issued'] = time() - 301;
        $this->session($session);
        $this->post('/passkey/login', $assertion);
        $this->assertResponseCode(422, 'Expired challenge');
    }

    public function testWithoutChallengeTheErrorIsGeneric(): void
    {
        $user = $this->userWithPasskey();

        $this->post('/passkey/login', $this->assertion(random_bytes(32), (string)$user->id));

        $this->assertResponseCode(422);
        $this->assertSame(self::GENERIC, json_decode((string)$this->_response->getBody(), true)['message']);
        $this->assertNull($this->sessionValue('Auth'));
    }

    public function testLegacyKeysUnverifiedUsersAndInactiveAccountsAreRejected(): void
    {
        $legacy = $this->userWithPasskey([], false);
        $this->post('/passkey/login', $this->assertion($this->challenge(), (string)$legacy->id));
        $this->assertResponseCode(422, 'Key registered before passkeys');

        $user = $this->userWithPasskey();
        $this->post('/passkey/login', $this->assertion($this->challenge(), (string)$user->id, false));
        $this->assertResponseCode(422, 'User verification is required');

        $inactive = $this->userWithPasskey(['active' => false]);
        $this->post('/passkey/login', $this->assertion($this->challenge(), (string)$inactive->id));
        $this->assertResponseCode(422, 'Inactive account');
        $this->assertNull($this->sessionValue('Auth'));
    }

    public function testAccountsLockedByThePasswordLockoutAreRejected(): void
    {
        // CakeDC/Users 17 format: a single identifier (className + options), not a collection
        Configure::write('Auth.Authenticators.Form.identifier', [
            'className' => 'CakeDC/Users.PasswordLockout',
            'lockoutHandler' => ['className' => 'CakeDC/Users.LockoutHandler'],
        ]);
        $locked = $this->userWithPasskey(['lockout_time' => DateTime::now()]);

        $this->post('/passkey/login', $this->assertion($this->challenge(), (string)$locked->id));

        $this->assertResponseCode(422);
        $this->assertNull($this->sessionValue('Auth'));
    }

    public function testOnlyInternalRedirectsAreFollowed(): void
    {
        $user = $this->userWithPasskey();
        $this->post('/passkey/login?redirect=' . urlencode('/users/index'), $this->assertion($this->challenge(), (string)$user->id));
        $this->assertSame('/users/index', json_decode((string)$this->_response->getBody(), true)['redirectUrl']);

        $this->session(['Auth' => null]);
        $this->post('/passkey/login?redirect=' . urlencode('https://evil.example/'), $this->assertion($this->challenge(), (string)$user->id));
        $this->assertSame('/', json_decode((string)$this->_response->getBody(), true)['redirectUrl']);
    }

    public function testRememberMeIssuesTheCookie(): void
    {
        $user = $this->userWithPasskey();

        $this->post('/passkey/login', $this->assertion($this->challenge(), (string)$user->id) + ['remember_me' => '1']);

        $this->assertResponseOk();
        // CakeDC's cookie authenticator adds a raw Set-Cookie header (not the cookie collection)
        $this->assertHeaderContains('Set-Cookie', 'CookieAuth=');
        $this->assertHeaderContains('Set-Cookie', 'secure', 'HTTPS app: secure cookie');
        $this->assertHeaderContains('Set-Cookie', 'samesite=Lax');
    }

    public function testTheLoginPageOffersPasskeys(): void
    {
        $this->get('/login');

        $this->assertResponseContains('data-passkey-login');
        $this->assertResponseContains('autocomplete="username webauthn"', 'Conditional UI (autofill)');
    }
}
