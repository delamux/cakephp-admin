<?php
declare(strict_types=1);

namespace App\Test\TestCase\Account;

use App\Account\ActivityLog;
use App\Model\Table\UsersTable;
use App\Test\TestCase\PermissionsTestTrait;
use App\Test\TestCase\UserFactoryTrait;
use App\UseCase\Actor;
use App\UseCase\TwoFactor\TwoFactorSettings;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;
use RuntimeException;

/**
 * Account activity log: security events of each account (sign-ins, failed attempts, sign-outs,
 * password and 2FA changes) with IP and browser. Never stores what was typed. Writing it never
 * breaks the action that is being logged.
 */
class AccountActivityTest extends TestCase
{
    use ConsoleIntegrationTestTrait;
    use IntegrationTestTrait;
    use PermissionsTestTrait;
    use UserFactoryTrait;

    protected array $fixtures = [
        'app.Users', 'app.Roles', 'app.Permissions', 'app.RolesPermissions', 'app.AccountActivities',
    ];

    private const SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
    private const IP = '198.51.100.23';
    private const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/537.36 (KHTML, like Gecko) '
        . 'Chrome/131.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest(['environment' => ['REMOTE_ADDR' => self::IP, 'HTTP_USER_AGENT' => self::UA]]);
    }

    private function users(): UsersTable
    {
        /** @var \App\Model\Table\UsersTable $users */
        $users = $this->fetchTable('Users');

        return $users;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activity(?string $userId = null): array
    {
        $query = $this->fetchTable('AccountActivities')->find()
            ->orderBy(['id' => 'ASC'])
            ->disableHydration();
        if ($userId !== null) {
            $query->where(['user_id' => $userId]);
        }

        return $query->all()->toList();
    }

    /**
     * @return list<string>
     */
    private function events(string $userId): array
    {
        return array_column($this->activity($userId), 'event');
    }

    public function testASuccessfulLoginIsRecordedWithIpAndBrowser(): void
    {
        $user = $this->createUser();

        $this->post('/login', ['username' => $user->username, 'password' => 'Secret1234!']);

        $rows = $this->activity((string)$user->id);
        $this->assertCount(1, $rows);
        $this->assertSame(ActivityLog::LOGIN, $rows[0]['event']);
        $this->assertSame(self::IP, $rows[0]['ip']);
        $this->assertSame(self::UA, $rows[0]['user_agent']);
        $this->assertNull($rows[0]['actor_id'], 'Done by the user');
    }

    public function testWrongPasswordsAreRecordedForKnownAccountsOnlyAndNothingTypedIsStored(): void
    {
        $user = $this->createUser(['email' => 'known@example.test']);

        $this->post('/login', ['username' => $user->username, 'password' => 'typed-secret-1']);
        $this->post('/login', ['username' => 'known@example.test', 'password' => 'typed-secret-2']);
        $this->post('/login', ['username' => 'nobody-here', 'password' => 'typed-secret-3']);

        $this->assertSame(
            [ActivityLog::LOGIN_FAILED, ActivityLog::LOGIN_FAILED],
            $this->events((string)$user->id),
        );
        $this->assertCount(2, $this->activity(), 'Unknown usernames are not recorded');
        $stored = (string)json_encode($this->activity());
        $this->assertStringNotContainsString('typed-secret', $stored);
        $this->assertStringNotContainsString('nobody-here', $stored);
    }

    public function testLogoutIsRecorded(): void
    {
        $user = $this->createUser();
        $this->session(['Auth' => $user]);

        $this->get('/logout');

        $this->assertSame([ActivityLog::LOGOUT], $this->events((string)$user->id));
    }

    public function testTheSecondStepIsRecordedAndOnlyTheFinishedLoginCountsAsLogin(): void
    {
        $user = $this->createUser();
        $this->users()->enableTotp((string)$user->id, self::SECRET);
        $login = function () use ($user): void {
            $this->post('/login', ['username' => $user->username, 'password' => 'Secret1234!']);
            $this->session($_SESSION ?? []);
        };

        $login();
        $this->post('/verify', ['code' => '000000']);
        $this->assertSame(
            [ActivityLog::TWO_FACTOR_FAILED],
            $this->events((string)$user->id),
            'Password was right, code was not',
        );

        $login();
        $this->post('/verify', ['code' => (new TwoFactorAuth(new EndroidQrCodeProvider()))->getCode(self::SECRET)]);
        $this->session($_SESSION ?? []);
        $this->get('/login');

        $this->assertSame(
            [ActivityLog::TWO_FACTOR_FAILED, ActivityLog::LOGIN],
            $this->events((string)$user->id),
        );
    }

    public function testPasswordChangesAreRecorded(): void
    {
        $user = $this->createUser();
        $this->session(['Auth' => $user]);

        $this->post('/users/change-password', [
            'current_password' => 'Secret1234!',
            'password' => 'NewSecret5678!',
            'password_confirm' => 'NewSecret5678!',
        ]);

        $this->assertSame([ActivityLog::PASSWORD_CHANGED], $this->events((string)$user->id));
    }

    public function testAPasswordResetByTokenIsRecorded(): void
    {
        $user = $this->createUser();
        $key = (string)Configure::read('Users.Key.Session.resetPasswordUserId', 'Users.resetPasswordUserId');
        $this->session([$key => $user->id]);

        $this->post('/users/change-password', ['password' => 'NewSecret5678!', 'password_confirm' => 'NewSecret5678!']);

        $this->assertSame([ActivityLog::PASSWORD_RESET], $this->events((string)$user->id));
    }

    public function testTwoFactorChangesAreRecordedAndAResetNamesWhoDidIt(): void
    {
        $user = $this->createUser();
        $admin = $this->createUser(['role' => 'superadmin', 'is_superuser' => true]);
        $settings = new TwoFactorSettings($this->users());
        $totp = new TwoFactorAuth(new EndroidQrCodeProvider());

        $this->assertTrue($settings->confirmTotp((string)$user->id, self::SECRET, $totp->getCode(self::SECRET)));
        // The browser stores the credential (App\Webauthn\RegisterAdapter), then the use case is told
        $this->users()->updateQuery()
            ->set(['additional_data' => ['webauthn_credentials' => ['k' => []]]], ['additional_data' => 'json'])
            ->where(['id' => $user->id])->execute();
        $settings->keyRegistered((string)$user->id);
        $settings->disable((string)$user->id, TwoFactorSettings::WEBAUTHN);
        $settings->disable((string)$user->id, TwoFactorSettings::TOTP);
        $settings->confirmTotp((string)$user->id, self::SECRET, $totp->getCode(self::SECRET));
        $settings->reset((string)$user->id, actor: Actor::fromUser($admin->toArray()));

        $this->assertSame([
            ActivityLog::TOTP_ENABLED,
            ActivityLog::KEY_ADDED,
            ActivityLog::KEY_REMOVED,
            ActivityLog::TOTP_DISABLED,
            ActivityLog::TOTP_ENABLED,
            ActivityLog::TWO_FACTOR_RESET,
        ], $this->events((string)$user->id));
        $rows = $this->activity((string)$user->id);
        $this->assertSame((string)$admin->id, $rows[5]['actor_id']);
        $this->assertNull($rows[0]['actor_id']);
    }

    public function testAFailureWritingTheLogNeverBreaksTheAction(): void
    {
        $table = $this->getMockBuilder(Table::class)
            ->setConstructorArgs([['alias' => 'AccountActivities', 'table' => 'account_activities']])
            ->onlyMethods(['save'])
            ->getMock();
        $table->expects($this->once())->method('save')->willThrowException(new RuntimeException('database is down'));

        (new ActivityLog($table))->record('00000000-0000-4000-8000-000000000000', ActivityLog::LOGIN);

        $this->assertSame([], $this->activity());
    }

    public function testUsersSeeTheirOwnActivityAndOnlySuperadminsSeeOthers(): void
    {
        $user = $this->createUser();
        $request = new ServerRequest(['environment' => ['REMOTE_ADDR' => self::IP, 'HTTP_USER_AGENT' => self::UA]]);
        (new ActivityLog())->record((string)$user->id, ActivityLog::LOGIN_FAILED, $request);

        $this->session(['Auth' => $user]);
        $this->get('/profile');
        $this->assertResponseOk();
        $this->assertResponseContains('Recent activity');
        $this->assertResponseContains('Failed sign-in');
        $this->assertResponseContains('Chrome on macOS');

        $this->session(['Auth' => $this->createUser(['role' => 'superadmin', 'is_superuser' => true])]);
        $this->get('/users/view/' . $user->id);
        $this->assertResponseContains('Failed sign-in');

        $this->session(['Auth' => $this->createUser(['role' => 'admin'])]);
        $this->get('/users/view/' . $user->id);
        $this->assertResponseOk();
        $this->assertResponseNotContains('Recent activity');
    }

    public function testOldActivityIsPurgedButNeverBelowTheMinimum(): void
    {
        $user = $this->createUser();
        $log = new ActivityLog();
        $log->record((string)$user->id, ActivityLog::LOGIN);
        $log->record((string)$user->id, ActivityLog::LOGOUT);
        $this->fetchTable('AccountActivities')->updateAll(
            ['created' => DateTime::now()->subDays(200)],
            ['event' => ActivityLog::LOGIN],
        );

        $this->exec('activity purge --dry-run');
        $this->assertOutputContains('1 activity entries older than 180 days would be deleted.');
        $this->assertSame([ActivityLog::LOGIN, ActivityLog::LOGOUT], $this->events((string)$user->id));

        $this->exec('activity purge --days 10');
        $this->assertExitError();
        $this->assertErrorContains('at least 30 days');

        $this->exec('activity purge');
        $this->assertExitSuccess();
        $this->assertSame([ActivityLog::LOGOUT], $this->events((string)$user->id));
    }
}
