<?php
declare(strict_types=1);

namespace App\Test\TestCase\Account;

use App\Account\ActivityLog;
use App\Http\Session\AppDatabaseSession;
use App\Test\TestCase\PermissionsTestTrait;
use App\Test\TestCase\UserFactoryTrait;
use App\UseCase\Account\ActiveSessions;
use Cake\Http\Cookie\Cookie;
use Cake\Http\ServerRequest;
use Cake\Routing\Router;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * "Where you're signed in": every session row knows its user, IP and browser
 * (App\Http\Session\AppDatabaseSession). The user sees their live sessions in the profile and signs
 * out one of them or all the others; that also invalidates the "remember me" cookies of the other
 * devices (rotated `users.session_key`, part of the cookie token), or they would sign back in alone.
 */
class ActiveSessionsTest extends TestCase
{
    use IntegrationTestTrait;
    use PermissionsTestTrait;
    use UserFactoryTrait;

    protected array $fixtures = [
        'app.Users', 'app.Roles', 'app.Permissions', 'app.RolesPermissions', 'app.AccountActivities',
        'app.Sessions',
    ];

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

    private function addSession(string $id, ?string $userId, int $expires, string $ip = '203.0.113.7'): void
    {
        $this->fetchTable('Sessions')->getConnection()->insertQuery('sessions', [
            'id' => $id,
            'data' => '',
            'expires' => $expires,
            'user_id' => $userId,
            'ip' => $ip,
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
        ], ['expires' => 'integer'])->execute();
    }

    /**
     * @return list<string>
     */
    private function sessionIds(): array
    {
        return $this->fetchTable('Sessions')->find()->orderBy(['id' => 'ASC'])->all()->extract('id')->toList();
    }

    public function testTheSessionHandlerStoresTheUserIpAndBrowser(): void
    {
        $user = $this->createUser();
        Router::setRequest(new ServerRequest([
            'environment' => ['REMOTE_ADDR' => '198.51.100.4', 'HTTP_USER_AGENT' => 'Firefox/131.0'],
        ]));
        $handler = new AppDatabaseSession();

        $_SESSION = ['Auth' => $user];
        $this->assertTrue($handler->write('signed-in', 'data'));
        $_SESSION = [];
        $this->assertTrue($handler->write('anonymous', 'data'));

        $rows = $this->fetchTable('Sessions')->find()->all()->indexBy('id')->toArray();
        $this->assertSame((string)$user->id, $rows['signed-in']->get('user_id'));
        $this->assertSame('198.51.100.4', $rows['signed-in']->get('ip'));
        $this->assertSame('Firefox/131.0', $rows['signed-in']->get('user_agent'));
        $this->assertNull($rows['anonymous']->get('user_id'));
    }

    public function testListsOnlyTheLiveSessionsOfTheUserMarkingTheCurrentOne(): void
    {
        $user = $this->createUser();
        $other = $this->createUser();
        $this->addSession('current', (string)$user->id, time() + 3600);
        $this->addSession('laptop', (string)$user->id, time() + 1800);
        $this->addSession('expired', (string)$user->id, time() - 60);
        $this->addSession('someone-else', (string)$other->id, time() + 3600);

        $sessions = (new ActiveSessions())->list((string)$user->id, 'current');

        $this->assertCount(2, $sessions);
        $this->assertTrue($sessions[0]['current'], 'Current first');
        $this->assertFalse($sessions[1]['current']);
        $this->assertSame('203.0.113.7', $sessions[1]['ip']);
        $this->assertSame('Chrome on macOS', $sessions[1]['browser']);
        $this->assertSame(hash('sha256', 'laptop'), $sessions[1]['key'], 'The raw session id never reaches the page');
    }

    public function testSigningOutTheOtherSessionsKeepsThisOneAndInvalidatesRememberMe(): void
    {
        $user = $this->createUser();
        $other = $this->createUser();
        $this->addSession('current', (string)$user->id, time() + 3600);
        $this->addSession('laptop', (string)$user->id, time() + 3600);
        $this->addSession('phone', (string)$user->id, time() + 3600);
        $this->addSession('someone-else', (string)$other->id, time() + 3600);
        $secret = $this->fetchTable('Users')->get($user->id)->get('remember_secret');

        $removed = (new ActiveSessions())->signOutOthers((string)$user->id, 'current');

        $this->assertSame(2, $removed);
        $this->assertSame(['current', 'someone-else'], $this->sessionIds());
        $this->assertNotSame($secret, $this->fetchTable('Users')->get($user->id)->get('remember_secret'));
        $this->assertSame(
            [ActivityLog::SESSIONS_REVOKED],
            $this->fetchTable('AccountActivities')->find()->where(['user_id' => $user->id])->all()->extract('event')->toList(),
        );
    }

    public function testSigningOutOneSessionOnlyWorksForOwnSessions(): void
    {
        $user = $this->createUser();
        $other = $this->createUser();
        $this->addSession('laptop', (string)$user->id, time() + 3600);
        $this->addSession('someone-else', (string)$other->id, time() + 3600);
        $sessions = new ActiveSessions();

        $this->assertFalse($sessions->signOut((string)$user->id, hash('sha256', 'someone-else'), 'current'));
        $this->assertFalse($sessions->signOut((string)$user->id, hash('sha256', 'current'), 'current'), 'Not this one: use Sign out');
        $this->assertTrue($sessions->signOut((string)$user->id, hash('sha256', 'laptop'), 'current'));

        $this->assertSame(['someone-else'], $this->sessionIds());
    }

    public function testFromTheProfile(): void
    {
        $user = $this->createUser();
        $this->addSession('laptop', (string)$user->id, time() + 3600);
        $this->session(['Auth' => $user]);

        $this->get('/profile');
        $this->assertResponseContains('Where you are signed in');
        $this->assertResponseContains('203.0.113.7');

        $this->post('/account-sessions/sign-out/' . hash('sha256', 'laptop'));
        $this->assertRedirectContains('/profile');
        $this->assertFlashMessage('Session signed out.');
        $this->assertSame([], $this->sessionIds());

        $this->addSession('phone', (string)$user->id, time() + 3600);
        $this->post('/account-sessions/sign-out-others');
        $this->assertRedirectContains('/profile');
        $this->assertFlashMessage('Signed out of every other session.');
        $this->assertSame([], $this->sessionIds());
    }

    public function testRememberMeSignsBackInUntilTheOtherSessionsAreSignedOut(): void
    {
        $user = $this->createUser(['username' => 'remembered']);
        $this->post('/login', ['username' => 'remembered', 'password' => 'Secret1234!', 'remember_me' => '1']);
        // Set by EncryptedCookieMiddleware as a header (already encrypted)
        $cookie = null;
        foreach ($this->_response->getHeader('Set-Cookie') as $header) {
            if (str_starts_with($header, 'CookieAuth=')) {
                $cookie = Cookie::createFromHeaderString($header)->getValue();
            }
        }
        $this->assertIsString($cookie, 'Remember-me cookie issued');

        // Another device: no session, only the cookie
        $this->_session = [];
        $this->cookie('CookieAuth', $cookie);
        $this->get('/profile');
        $this->assertResponseOk();

        (new ActiveSessions())->signOutOthers((string)$user->id, 'this-device');

        $this->_session = [];
        $this->cookie('CookieAuth', $cookie);
        $this->get('/profile');
        $this->assertRedirectContains('/login');
    }
}
