<?php
declare(strict_types=1);

namespace App\UseCase\Users;

use App\Account\ActivityLog;
use App\Model\Table\UsersTable;
use App\Service\UserRoleManager;
use ArrayAccess;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\I18n\DateTime;
use Cake\Mailer\MailerAwareTrait;
use Cake\ORM\TableRegistry;
use Cake\Utility\Security;
use Cake\Validation\Validation;

/**
 * Invitations: an admin creates the account (email + role) and the person chooses their password from
 * an emailed link (`/invitations/accept/<token>`, App\Mailer\UsersMailer::invitation(), through the
 * queue). Until then the account is inactive, so it cannot sign in. The link uses CakeDC's
 * `token` / `token_expires`, is single use and expires after `App.invitations.expirationDays`.
 *
 * Only roles the actor may assign (UserRoleManager::assignableRoles()). Invitation and acceptance go to
 * the account activity (who invited).
 */
class InviteUser
{
    use MailerAwareTrait;

    public const MIN_PASSWORD_LENGTH = 8;

    private UsersTable $users;

    private ActivityLog $activity;

    private UserRoleManager $roles;

    /**
     * @param \App\Model\Table\UsersTable|null $users Users table.
     * @param \App\Account\ActivityLog|null $activity Activity log.
     * @param \App\Service\UserRoleManager|null $roles Role rules.
     */
    public function __construct(
        ?UsersTable $users = null,
        ?ActivityLog $activity = null,
        ?UserRoleManager $roles = null,
    ) {
        /** @var \App\Model\Table\UsersTable $table */
        $table = $users ?? TableRegistry::getTableLocator()->get((string)Configure::read('Users.table', 'Users'));
        $this->users = $table;
        $this->activity = $activity ?? new ActivityLog();
        $this->roles = $roles ?? UserRoleManager::fromConfig();
    }

    /**
     * @param \ArrayAccess<string, mixed>|array<string, mixed> $actor Signed-in user.
     * @param array<string, mixed> $data `email`, `role`, optional `first_name`, `last_name`.
     * @return string|null Error message, or null when the invitation was sent.
     */
    public function invite(ArrayAccess|array $actor, array $data): ?string
    {
        $email = mb_strtolower(trim((string)($data['email'] ?? '')));
        $role = (string)($data['role'] ?? '');
        if (!Validation::email($email)) {
            return __('Enter a valid email address.');
        }
        if (!array_key_exists($role, $this->roles->assignableRoles($actor))) {
            return __('You cannot assign that role.');
        }
        if ($this->users->exists(['OR' => ['email' => $email, 'username' => $email]])) {
            return __('There is already an account with that email.');
        }

        /** @var \CakeDC\Users\Model\Entity\User $user */
        $user = $this->users->newEmptyEntity();
        $user->patch([
            // Sign-in also works with the email; the username can be changed later
            'username' => $email,
            'email' => $email,
            'first_name' => $this->name($data['first_name'] ?? null),
            'last_name' => $this->name($data['last_name'] ?? null),
            'role' => $role,
            'active' => false,
            'invited_at' => DateTime::now(),
        ], ['guard' => false]);
        // Unusable password until the invitation is accepted (nobody knows it)
        $user->set('password', password_hash(Security::randomString(32), PASSWORD_DEFAULT), ['setter' => false]);
        $user->updateToken($this->expirationSeconds());
        if (!$this->users->save($user)) {
            return __('The invitation could not be sent. Try again.');
        }
        $this->activity->record((string)$user->get('id'), ActivityLog::INVITED, null, $this->actorId($actor));
        $this->send($user);

        return null;
    }

    /**
     * New link (the previous one stops working) for a pending invitation.
     *
     * @param \ArrayAccess<string, mixed>|array<string, mixed> $actor Signed-in user.
     * @param string $userId Invited user.
     * @return string|null Error message, or null when sent.
     */
    public function resend(ArrayAccess|array $actor, string $userId): ?string
    {
        $user = $this->users->find()->where(['id' => $userId])->first();
        if (!$user instanceof EntityInterface || !$this->isPending($user)) {
            return __('That user has no pending invitation.');
        }
        if (!$this->roles->canManage($actor, $user)) {
            return __('You cannot manage that user.');
        }
        /** @var \CakeDC\Users\Model\Entity\User $user */
        $user->updateToken($this->expirationSeconds());
        if (!$this->users->save($user, ['checkRules' => false])) {
            return __('The invitation could not be sent. Try again.');
        }
        $this->activity->record((string)$user->get('id'), ActivityLog::INVITED, null, $this->actorId($actor));
        $this->send($user);

        return null;
    }

    /**
     * @param string $token Token from the link.
     * @return \Cake\Datasource\EntityInterface|null The invited user, if the link is valid.
     */
    public function pending(string $token): ?EntityInterface
    {
        if (!preg_match('/^[0-9a-f]{32}$/', $token)) {
            return null;
        }
        $user = $this->users->find()
            ->where(['token' => $token, 'active' => false, 'invited_at IS NOT' => null])
            ->first();
        if (!$user instanceof EntityInterface) {
            return null;
        }
        $expires = $user->get('token_expires');

        return $expires instanceof DateTime && $expires->isFuture() ? $user : null;
    }

    /**
     * Chooses the password and activates the account.
     *
     * @param string $token Token from the link.
     * @param string $password New password.
     * @param string $confirm Confirmation.
     * @return string|null Error message, or null when the account is ready.
     */
    public function accept(string $token, string $password, string $confirm): ?string
    {
        $user = $this->pending($token);
        if ($user === null) {
            return __('This invitation link is not valid or has expired.');
        }
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return __('Use at least 8 characters.');
        }
        if (!hash_equals($password, $confirm)) {
            return __('The passwords do not match.');
        }
        $user->set('password', $password);
        $user->patch([
            'active' => true,
            'activation_date' => DateTime::now(),
            'token' => null,
            'token_expires' => null,
        ], ['guard' => false]);
        if (!$this->users->save($user, ['checkRules' => false])) {
            return __('Your account could not be activated. Try again.');
        }
        $this->activity->record((string)$user->get('id'), ActivityLog::INVITATION_ACCEPTED);

        return null;
    }

    /**
     * @param \ArrayAccess<string, mixed>|array<string, mixed> $actor Signed-in user.
     * @return array<string, string> Roles the actor may give (slug => name).
     */
    public function assignableRoles(ArrayAccess|array $actor): array
    {
        return $this->roles->assignableRoles($actor);
    }

    /**
     * @param \Cake\Datasource\EntityInterface $user User.
     * @return bool
     */
    public function isPending(EntityInterface $user): bool
    {
        return !$user->get('active') && $user->get('invited_at') !== null;
    }

    /**
     * @param \Cake\Datasource\EntityInterface $user Invited user (with a fresh token).
     * @return void
     */
    private function send(EntityInterface $user): void
    {
        $this->getMailer((string)(Configure::read('Users.Email.mailerClass') ?: 'CakeDC/Users.Users'))
            ->send('invitation', [$user, (int)Configure::read('App.invitations.expirationDays', 7)]);
    }

    /**
     * @return int
     */
    private function expirationSeconds(): int
    {
        return max(1, (int)Configure::read('App.invitations.expirationDays', 7)) * 86400;
    }

    /**
     * @param mixed $value Raw name.
     * @return string|null
     */
    private function name(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : mb_substr($value, 0, 50);
    }

    /**
     * @param \ArrayAccess<string, mixed>|array<string, mixed> $actor Signed-in user.
     * @return string|null
     */
    private function actorId(ArrayAccess|array $actor): ?string
    {
        $id = (string)($actor['id'] ?? '');

        return $id === '' ? null : $id;
    }
}
