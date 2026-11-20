<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Account\ActivityLog;
use App\Test\TestCase\PermissionsTestTrait;
use App\Test\TestCase\UserFactoryTrait;
use Cake\Datasource\EntityInterface;
use Cake\I18n\DateTime;
use Cake\TestSuite\EmailTrait;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Invitations: an admin enters an email and a role; the person gets an email (through the queue) with a
 * link to choose their password. Until then the account exists but cannot sign in. Links are single
 * use and expire (`App.invitations.expirationDays`). Admins only assign the roles they may assign.
 */
class InvitationsTest extends TestCase
{
    use EmailTrait;
    use IntegrationTestTrait;
    use PermissionsTestTrait;
    use UserFactoryTrait;

    protected array $fixtures = [
        'app.Users', 'app.Roles', 'app.Permissions', 'app.RolesPermissions', 'app.AccountActivities',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->enableRetainFlashMessages();
    }

    private function invited(string $email): ?EntityInterface
    {
        return $this->fetchTable('Users')->find()->where(['email' => $email])->first();
    }

    private function inviteAsAdmin(string $email = 'new@example.test', string $role = 'editor'): void
    {
        $this->session(['Auth' => $this->createUser(['role' => 'admin'])]);
        $this->post('/invitations/add', ['email' => $email, 'role' => $role, 'first_name' => 'Nuria']);
    }

    public function testAnAdminInvitesSomeoneWhoGetsAnEmailWithTheLink(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $this->session(['Auth' => $admin]);

        $this->post('/invitations/add', ['email' => 'new@example.test', 'role' => 'editor', 'first_name' => 'Nuria']);

        $this->assertRedirectContains('/users');
        $this->assertFlashMessage('Invitation sent to new@example.test.');
        $user = $this->invited('new@example.test');
        $this->assertNotNull($user);
        $this->assertSame('editor', $user->get('role'));
        $this->assertFalse($user->get('active'), 'Cannot sign in until the invitation is accepted');
        $this->assertNotNull($user->get('invited_at'));
        $this->assertMailCount(1);
        $this->assertMailSentTo('new@example.test');
        $this->assertMailContains('/invitations/accept/' . $user->get('token'));
        $activity = $this->fetchTable('AccountActivities')->find()->where(['user_id' => $user->get('id')])->firstOrFail();
        $this->assertSame(ActivityLog::INVITED, $activity->get('event'));
        $this->assertSame((string)$admin->id, $activity->get('actor_id'));
    }

    public function testOnlyAssignableRolesAndNewEmails(): void
    {
        $this->createUser(['email' => 'taken@example.test']);

        $this->inviteAsAdmin('boss@example.test', 'superadmin');
        $this->assertResponseOk();
        $this->assertResponseContains('You cannot assign that role.');
        $this->assertNull($this->invited('boss@example.test'));

        $this->post('/invitations/add', ['email' => 'taken@example.test', 'role' => 'user']);
        $this->assertResponseContains('There is already an account with that email.');

        $this->post('/invitations/add', ['email' => 'not-an-email', 'role' => 'user']);
        $this->assertResponseContains('Enter a valid email address.');
        $this->assertNoMailSent();
    }

    public function testUsersWithoutPermissionCannotInvite(): void
    {
        $this->session(['Auth' => $this->createUser(['role' => 'editor'])]);

        $this->post('/invitations/add', ['email' => 'new@example.test', 'role' => 'user']);

        $this->assertNull($this->invited('new@example.test'));
        $this->assertNoMailSent();
    }

    public function testAcceptingChoosesThePasswordActivatesTheAccountAndTheLinkIsSingleUse(): void
    {
        $this->inviteAsAdmin();
        $token = (string)$this->invited('new@example.test')?->get('token');
        $this->_session = [];

        $this->get('/invitations/accept/' . $token);
        $this->assertResponseOk();
        $this->assertResponseContains('new@example.test');

        $this->post('/invitations/accept/' . $token, ['password' => 'short', 'password_confirm' => 'short']);
        $this->assertResponseContains('Use at least 8 characters.');
        $this->post('/invitations/accept/' . $token, ['password' => 'Welcome2026!', 'password_confirm' => 'Other2026!']);
        $this->assertFalse($this->invited('new@example.test')?->get('active'));

        $this->post('/invitations/accept/' . $token, ['password' => 'Welcome2026!', 'password_confirm' => 'Welcome2026!']);
        $this->assertRedirectContains('/login');
        $this->assertFlashMessage('Your account is ready. Sign in with your new password.');
        $user = $this->invited('new@example.test');
        $this->assertTrue($user?->get('active'));
        $this->assertNull($user->get('token'));
        $this->assertTrue(password_verify('Welcome2026!', (string)$user->get('password')));
        $events = $this->fetchTable('AccountActivities')->find()->where(['user_id' => $user->get('id')])
            ->orderBy(['id' => 'ASC'])->all()->extract('event')->toList();
        $this->assertSame([ActivityLog::INVITED, ActivityLog::INVITATION_ACCEPTED], $events);

        $this->get('/invitations/accept/' . $token);
        $this->assertResponseCode(404);
    }

    public function testExpiredOrUnknownLinksAreRejected(): void
    {
        $this->inviteAsAdmin();
        $user = $this->invited('new@example.test');
        $this->fetchTable('Users')->updateQuery()->set(['token_expires' => DateTime::now()->subMinutes(1)])
            ->where(['id' => $user?->get('id')])->execute();
        $this->_session = [];

        $this->get('/invitations/accept/' . $user?->get('token'));
        $this->assertResponseCode(404);
        $this->post('/invitations/accept/' . $user?->get('token'), ['password' => 'Welcome2026!', 'password_confirm' => 'Welcome2026!']);
        $this->assertResponseCode(404);
        $this->assertFalse($this->invited('new@example.test')?->get('active'));

        $this->get('/invitations/accept/0123456789abcdef0123456789abcdef');
        $this->assertResponseCode(404);
    }

    public function testResendingIssuesANewLinkAndInvalidatesTheOldOne(): void
    {
        $this->inviteAsAdmin();
        $user = $this->invited('new@example.test');
        $old = (string)$user?->get('token');

        $this->post('/invitations/resend/' . $user?->get('id'));

        $this->assertRedirectContains('/users');
        $this->assertFlashMessage('Invitation sent to new@example.test.');
        $new = (string)$this->invited('new@example.test')?->get('token');
        $this->assertNotSame($old, $new);
        $this->assertMailCount(2);
        $this->_session = [];
        $this->get('/invitations/accept/' . $old);
        $this->assertResponseCode(404);
        $this->get('/invitations/accept/' . $new);
        $this->assertResponseOk();
    }

    public function testOnlyPendingInvitationsCanBeResent(): void
    {
        $this->session(['Auth' => $this->createUser(['role' => 'admin'])]);
        $member = $this->createUser();

        $this->post('/invitations/resend/' . $member->id);

        $this->assertFlashMessage('That user has no pending invitation.');
        $this->assertNoMailSent();
    }
}
