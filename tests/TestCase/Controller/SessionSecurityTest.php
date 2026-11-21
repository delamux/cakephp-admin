<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Account\SessionGuard;
use App\Rbac\PermissionsCache;
use App\Test\TestCase\PermissionsTestTrait;
use App\Test\TestCase\UserFactoryTrait;
use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use CakeDC\Users\Model\Entity\User;

/**
 * Sessions and accounts: a new password signs out the other sessions, sessions have a maximum
 * lifetime, sensitive actions ask for the password again ("sudo mode"), profiles are private, the
 * fixed account protections cover every action on someone else's account and the users CRUD never
 * takes a password or a token from the request.
 */
class SessionSecurityTest extends TestCase
{
    use IntegrationTestTrait;
    use PermissionsTestTrait;
    use UserFactoryTrait;

    protected array $fixtures = [
        'app.Users', 'app.Roles', 'app.Permissions', 'app.RolesPermissions', 'app.AccountActivities',
        'app.Sessions', 'app.AuthThrottles', 'app.PermissionAudits',
    ];

    private const SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->enableRetainFlashMessages();
    }

    protected function tearDown(): void
    {
        unset($_SESSION);
        parent::tearDown();
    }

    private function addSession(string $id, string $userId): void
    {
        $this->fetchTable('Sessions')->getConnection()->insertQuery('sessions', [
            'id' => $id, 'data' => '', 'expires' => time() + 3600, 'user_id' => $userId,
        ], ['expires' => 'integer'])->execute();
    }

    /**
     * @return list<string>
     */
    private function sessionsOf(string $userId): array
    {
        return $this->fetchTable('Sessions')->find()->where(['user_id' => $userId])->all()->extract('id')->toList();
    }

    private function fresh(User $user): User
    {
        /** @var \CakeDC\Users\Model\Entity\User */
        return $this->fetchTable('Users')->get($user->id);
    }

    private function superadmin(): User
    {
        return $this->createUser(['role' => 'superadmin', 'is_superuser' => true]);
    }

    // 1. A new password signs out the other sessions

    public function testChangingTheOwnPasswordSignsOutTheOtherDevicesButNotThisOne(): void
    {
        $user = $this->createUser();
        $this->addSession('other-device', (string)$user->id);
        $this->signIn($user);

        $this->post('/users/changePassword', [
            'current_password' => 'Secret1234!',
            'password' => 'NewSecret1234!',
            'password_confirm' => 'NewSecret1234!',
        ]);

        $this->assertFlashMessage('Password has been changed successfully');
        $this->assertNotContains('other-device', $this->sessionsOf((string)$user->id));
        $key = $this->fresh($user)->get('session_key');
        $this->assertIsString($key, 'Sign-out key rotated');

        // This device adopts the new key and stays signed in
        $this->session($_SESSION);
        $this->get('/profile');
        $this->assertResponseOk();
        $this->assertSame($key, $this->_requestSession->read(SessionGuard::KEY));
    }

    public function testASessionThatStillHoldsAnOldSignOutKeyIsEnded(): void
    {
        $user = $this->createUser();
        $this->fetchTable('Users')->updateQuery()->set(['session_key' => 'rotated'])->where(['id' => $user->id])->execute();
        $this->signIn($this->fresh($user), ['App' => ['Session' => ['key' => 'before-rotation']]]);

        $this->get('/profile');

        $this->assertRedirectContains('/login');
        $this->assertFlashMessage('Your session has ended. Please sign in again.', 'flash');
        $this->assertNull($this->_requestSession->read('Auth'));
    }

    public function testAPasswordResetByEmailSignsOutEverySession(): void
    {
        $user = $this->createUser();
        $this->addSession('laptop', (string)$user->id);
        $this->addSession('phone', (string)$user->id);
        // CakeDC `Users.Key.Session.resetPasswordUserId` (config is only loaded when the app boots)
        $this->session(['Users' => ['resetPasswordUserId' => $user->id]]);

        $this->post('/users/changePassword', ['password' => 'NewSecret1234!', 'password_confirm' => 'NewSecret1234!']);

        $this->assertFlashMessage('Password has been changed successfully');
        $this->assertSame([], $this->sessionsOf((string)$user->id));
        $this->assertNotNull($this->fresh($user)->get('session_key'));
    }

    // 2. Private profiles

    public function testAUserCannotOpenSomeoneElsesProfile(): void
    {
        $other = $this->createUser(['email' => 'someone.else@example.test']);
        $this->signIn($this->createUser(['email' => 'me@example.test']));

        $this->get('/profile/' . $other->id);

        $this->assertResponseOk();
        $this->assertResponseContains('me@example.test');
        $this->assertResponseNotContains('someone.else@example.test');
    }

    // 5. Fixed account protections cover every action on someone else's account

    public function testAnAdminCannotResetASuperadminsTwoFactorEvenIfTheMatrixAllowsIt(): void
    {
        $super = $this->superadmin();
        /** @var \App\Model\Table\UsersTable $users */
        $users = $this->fetchTable('Users');
        $users->enableTotp((string)$super->id, self::SECRET);
        // A superadmin removed the `owner` rule of admins in the matrix
        $this->fetchTable('RolesPermissions')->updateAll(['rule' => null], ['rule' => 'owner']);
        PermissionsCache::clear();
        $this->signIn($this->createUser(['role' => 'admin']));

        $this->post('/users/resetOneTimePasswordAuthenticator/' . $super->id);

        $this->assertRedirectContains('/users/index');
        $this->assertFlashMessage('You don\'t have permission to manage this account.');
        $this->assertSame(self::SECRET, $this->fresh($super)->get('secret'));
    }

    // 6. Maximum lifetime and password confirmation

    public function testASessionEndsAfterItsMaximumLifetimeHoweverActive(): void
    {
        // Default SESSION_MAX_LIFETIME: 12 hours
        $this->signIn($this->createUser(), ['App' => ['Session' => ['signedInAt' => time() - 12 * 3600]]]);

        $this->get('/profile');

        $this->assertRedirectContains('/login');
        $this->assertFlashMessage('Your session has ended. Please sign in again.', 'flash');
    }

    public function testSensitiveScreensAskForThePasswordAgainAndComeBack(): void
    {
        // Default SESSION_CONFIRM_MINUTES: 15
        $this->signIn($this->superadmin(), ['App' => ['Session' => ['confirmedAt' => time() - 15 * 60]]]);

        $this->get('/permissions?role=admin');
        $this->assertRedirect('/confirm-password?redirect=%2Fpermissions%3Frole%3Dadmin');
        $this->assertFlashMessage('Confirm your password to continue.', 'flash');

        $this->get('/profile');
        $this->assertResponseOk('Ordinary pages are not asked');

        $this->post('/confirm-password?redirect=%2Fpermissions%3Frole%3Dadmin', ['password' => 'wrong']);
        $this->assertResponseOk();
        $this->assertFlashMessage('The password is not correct.', 'flash');

        $this->post('/confirm-password?redirect=%2Fpermissions%3Frole%3Dadmin', ['password' => 'Secret1234!']);
        $this->assertRedirect('/permissions?role=admin');

        $this->session($_SESSION);
        $this->get('/permissions?role=admin');
        $this->assertResponseOk('Confirmed for the next minutes');
    }

    public function testAnUnconfirmedPostGoesBackToTheFormItCameFromAndHtmxNavigates(): void
    {
        $this->session(['Auth' => $this->superadmin()]);
        $this->configRequest(['headers' => ['Referer' => Configure::read('App.fullBaseUrl') . '/roles']]);

        $this->post('/roles/add', ['name' => 'Auditor', 'slug' => 'auditor']);
        $this->assertRedirect('/confirm-password?redirect=%2Froles');
        $this->assertFalse($this->fetchTable('Roles')->exists(['slug' => 'auditor']), 'Nothing was saved');

        $this->configRequest(['headers' => ['HX-Request' => 'true']]);
        $this->post('/roles/add', ['name' => 'Auditor', 'slug' => 'auditor']);
        $this->assertHeaderContains('HX-Redirect', '/confirm-password');
    }

    public function testAPasswordLoginCountsAsAConfirmationAndOnlyLocalRedirectsAreFollowed(): void
    {
        $super = $this->superadmin();
        $this->post('/login', ['username' => $super->username, 'password' => 'Secret1234!']);
        $this->session($_SESSION);

        $this->get('/permissions');
        $this->assertResponseOk();

        $this->post('/confirm-password?redirect=' . urlencode('https://evil.example/'), ['password' => 'Secret1234!']);
        $this->assertRedirect('/');
    }

    // 7. The users CRUD never takes a password or a token from the request

    public function testEditingAUserIgnoresPasswordAndTokensEvenInAForgedForm(): void
    {
        $user = $this->createUser(['first_name' => 'Old']);
        $hash = $this->fresh($user)->get('password');
        $this->signIn($this->createUser(['role' => 'admin']));

        $this->post('/users/edit/' . $user->id, [
            'first_name' => 'New',
            'password' => 'Hacked1234!',
            'token' => 'planted-reset-token',
            'api_token' => 'planted-api-token',
            'login_token' => 'planted-login-token',
        ]);

        $saved = $this->fresh($user);
        $this->assertSame('New', $saved->get('first_name'), 'The legitimate change is saved');
        $this->assertSame($hash, $saved->get('password'));
        $this->assertNull($saved->get('token'));
        $this->assertNull($saved->get('api_token'));
        $this->assertNull($saved->get('login_token'));
    }

    public function testSignUpCannotPlantTokens(): void
    {
        $this->post('/register', [
            'username' => 'newcomer',
            'email' => 'newcomer@example.test',
            'password' => 'Secret1234!',
            'password_confirm' => 'Secret1234!',
            'tos' => 1,
            'api_token' => 'planted-api-token',
            'login_token' => 'planted-login-token',
        ]);

        /** @var \CakeDC\Users\Model\Entity\User $user */
        $user = $this->fetchTable('Users')->find()->where(['username' => 'newcomer'])->firstOrFail();
        $this->assertNull($user->get('api_token'));
        $this->assertNull($user->get('login_token'));
    }
}
