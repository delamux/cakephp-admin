<?php
declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use App\Http\AuthThrottle;
use App\Test\TestCase\PermissionsTestTrait;
use App\Test\TestCase\UserFactoryTrait;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

/**
 * Per-IP throttling of the sign-in endpoints: failed logins, wrong 2FA codes and failed passkeys
 * share one bucket; account actions (register, password reset, resend) have their own. Over the
 * limit: 429 with Retry-After.
 */
class AuthThrottleTest extends TestCase
{
    use IntegrationTestTrait;
    use PermissionsTestTrait;
    use UserFactoryTrait;

    protected array $fixtures = ['app.Users', 'app.Roles', 'app.Permissions', 'app.RolesPermissions', 'app.AuthThrottles'];

    private const SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
    private const IP = '203.0.113.7';

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest(['environment' => ['REMOTE_ADDR' => self::IP]]);
    }

    private function failLogins(int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->post('/login', ['username' => 'nobody', 'password' => 'wrong']);
        }
    }

    public function testFailedLoginsAreLimitedPerIp(): void
    {
        $limit = (int)Configure::read('Security.throttle.loginFailures');
        $this->failLogins($limit);
        $this->assertSame(0, AuthThrottle::retryAfter(AuthThrottle::LOGIN, '198.51.100.1'), 'Other IPs are not affected');

        $this->post('/login', ['username' => 'nobody', 'password' => 'wrong']);

        $this->assertResponseCode(429);
        $this->assertGreaterThan(0, (int)$this->_response->getHeaderLine('Retry-After'));
    }

    public function testWrongTwoFactorCodesCountButTheRightOneDoesNot(): void
    {
        $user = $this->createUser();
        /** @var \App\Model\Table\UsersTable $users */
        $users = $this->fetchTable('Users');
        $users->enableTotp((string)$user->id, self::SECRET);

        $login = function () use ($user): void {
            $this->post('/login', ['username' => $user->username, 'password' => 'Secret1234!']);
            $this->session($_SESSION ?? []);
        };
        $login();
        $this->post('/verify', ['code' => '000000']);
        $this->assertSame(1, $this->failures(), 'Wrong code = failed login');

        $login();
        $this->post('/verify', ['code' => (new TwoFactorAuth(new EndroidQrCodeProvider()))->getCode(self::SECRET)]);
        $this->assertSame(1, $this->failures(), 'Right code not counted');
    }

    public function testPasskeyOptionsAreNotCountedButRefusedWhenExhausted(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->post('/passkey/options');
        }
        $this->assertResponseOk();
        $this->assertSame(0, $this->failures());

        $this->post('/passkey/login', ['id' => 'bogus']);
        $this->assertSame(1, $this->failures(), 'Failed passkey = failed login');

        $this->failLogins((int)Configure::read('Security.throttle.loginFailures'));
        $this->post('/passkey/options');
        $this->assertResponseCode(429);
    }

    public function testAccountActionsHaveTheirOwnLimit(): void
    {
        $limit = (int)Configure::read('Security.throttle.accountActions');
        for ($i = 0; $i < $limit; $i++) {
            $this->post('/register', ['username' => 'x' . $i]);
            $this->assertNotSame(429, $this->_response->getStatusCode());
        }

        $this->post('/register', ['username' => 'again']);
        $this->assertResponseCode(429);
        $this->post('/login', ['username' => 'nobody', 'password' => 'wrong']);
        $this->assertNotSame(429, $this->_response->getStatusCode(), 'Separate bucket');
    }

    private function failures(): int
    {
        return AuthThrottle::hits(AuthThrottle::LOGIN, self::IP);
    }

    public function testTheWindowRestartsWhenItExpiresAndExpiredRowsAreCleaned(): void
    {
        AuthThrottle::recordLoginFailure('198.51.100.7');
        AuthThrottle::recordLoginFailure('198.51.100.7');
        $this->assertSame(2, AuthThrottle::hits(AuthThrottle::LOGIN, '198.51.100.7'));

        // The window ended: the next failure starts a new one
        $db = ConnectionManager::get('test');
        $db->execute('UPDATE auth_throttles SET window_ends = :t', ['t' => time() - 1], ['t' => 'integer']);
        $this->assertSame(0, AuthThrottle::hits(AuthThrottle::LOGIN, '198.51.100.7'), 'Expired window = no hits');
        AuthThrottle::recordLoginFailure('198.51.100.7');
        $this->assertSame(1, AuthThrottle::hits(AuthThrottle::LOGIN, '198.51.100.7'));

        AuthThrottle::recordAction('register', '198.51.100.8');
        $db->execute("UPDATE auth_throttles SET window_ends = :t WHERE bucket = 'register'", ['t' => time() - 1], ['t' => 'integer']);
        $this->assertSame(1, AuthThrottle::purgeExpired());
        $rows = $db->execute('SELECT bucket, ip_hash FROM auth_throttles')->fetchAll('assoc');
        $this->assertCount(1, $rows, 'Only the live window is kept');
        $this->assertSame(64, strlen((string)$rows[0]['ip_hash']), 'IP stored hashed');
        $this->assertNotSame('198.51.100.7', $rows[0]['ip_hash']);
    }
}
