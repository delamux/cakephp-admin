<?php
declare(strict_types=1);

namespace App\Mailer;

use Cake\Datasource\EntityInterface;
use CakeDC\Users\Mailer\UsersMailer as BaseUsersMailer;

/**
 * CakeDC/Users account emails (validation, reset password, social validation, login link)
 * rendered with the app email layout and inlined CSS, with clearer subjects.
 *
 * Configured in config/users.php (`Users.Email.mailerClass`). Templates are overridden in
 * templates/plugin/CakeDC/Users/email/{html,text}/.
 */
class UsersMailer extends BaseUsersMailer
{
    use InlineCssTrait;

    /**
     * @param \Cake\Datasource\EntityInterface $user User.
     * @param array<string, mixed> $options Options (`linkGenerator`).
     * @return void
     */
    protected function validation(EntityInterface $user, array $options = []): void
    {
        parent::validation($user, $options);
        $this->setSubject(__('Confirm your email address'));
    }

    /**
     * @param \Cake\Datasource\EntityInterface $user User.
     * @param array<string, mixed> $options Options (`linkGenerator`).
     * @return void
     */
    protected function resetPassword(EntityInterface $user, array $options = []): void
    {
        parent::resetPassword($user, $options);
        $this->setSubject(__('Reset your password'));
    }

    /**
     * @inheritDoc
     */
    protected function socialAccountValidation(EntityInterface $user, EntityInterface $socialAccount): void
    {
        parent::socialAccountValidation($user, $socialAccount);
        $this->setSubject(__('Activate your social login'));
    }

    /**
     * @inheritDoc
     */
    public function sendToken(EntityInterface $user, string $token): void
    {
        parent::sendToken($user, $token);
        $this->setSubject(__('Your sign-in link'));
    }

    /**
     * Two-factor change notice: enabled / disabled by the user, or reset by a superadmin. Never
     * includes the secret or key data.
     *
     * @param \Cake\Datasource\EntityInterface $user User.
     * @param string $event `enabled`, `disabled` or `reset`.
     * @param string|null $method `totp` or `webauthn`.
     * @return void
     */
    public function twoFactorChanged(EntityInterface $user, string $event, ?string $method = null): void
    {
        $this
            ->setTo((string)$user->get('email'))
            ->setSubject(match ($event) {
                'enabled' => __('Two-step verification turned on'),
                'disabled' => __('Two-step verification turned off'),
                default => __('Two-step verification reset'),
            })
            ->setEmailFormat('both')
            ->setViewVars([
                'name' => (string)($user->get('first_name') ?: $user->get('username')),
                'event' => $event,
                'method' => $method,
            ]);
        $this->viewBuilder()->setTemplate('two_factor_changed');
    }

    /**
     * Invitation (App\UseCase\Users\InviteUser): link to choose a password and activate the account.
     *
     * @param \Cake\Datasource\EntityInterface $user Invited user, with a fresh `token`.
     * @param int $days Days the link stays valid.
     * @return void
     */
    public function invitation(EntityInterface $user, int $days): void
    {
        $this
            ->setTo((string)$user->get('email'))
            ->setSubject(__('You have been invited to {0}', (string)env('APP_NAME', 'CakePHP Admin')))
            ->setEmailFormat('both')
            ->setViewVars([
                'name' => (string)($user->get('first_name') ?: $user->get('email')),
                'appName' => (string)env('APP_NAME', 'CakePHP Admin'),
                'url' => ['plugin' => false, 'prefix' => false, 'controller' => 'Invitations', 'action' => 'accept',
                    (string)$user->get('token')],
                'days' => $days,
            ]);
        $this->viewBuilder()->setTemplate('invitation');
    }
}
