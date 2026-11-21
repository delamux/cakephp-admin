<?php
declare(strict_types=1);

namespace App\Account;

use App\Http\AuthThrottle;
use App\UseCase\Account\ActiveSessions;
use ArrayAccess;
use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Cake\ORM\TableRegistry;
use CakeDC\Users\Plugin;

/**
 * CakeDC/Users events -> account activity log (App\Account\ActivityLog). A failed password login
 * also counts in the per-IP limit (App\Http\AuthThrottle) and is recorded only for an existing
 * account (what was typed is never stored). Passkey login and 2FA changes are recorded where
 * they happen (no CakeDC event): PasskeyLoginController, App\UseCase\TwoFactor\TwoFactorSettings.
 *
 * Session security (App\Account\SessionGuard): an interactive login starts the session clock and counts
 * as a password confirmation; a password change or reset signs out the other sessions of the account.
 */
class AccountActivityListener implements EventListenerInterface
{
    /**
     * @param \App\Account\ActivityLog|null $log Log (lazy: the table locator is reset in tests).
     * @param \App\UseCase\Account\ActiveSessions|null $sessions Sessions (lazy, same reason).
     */
    public function __construct(private ?ActivityLog $log = null, private ?ActiveSessions $sessions = null)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        return [
            Plugin::EVENT_AFTER_LOGIN => 'afterLogin',
            Plugin::EVENT_AFTER_LOGIN_FAILURE => 'afterLoginFailure',
            Plugin::EVENT_AFTER_LOGOUT => 'afterLogout',
            Plugin::EVENT_AFTER_CHANGE_PASSWORD => 'afterChangePassword',
        ];
    }

    /**
     * @param \Cake\Event\EventInterface<object> $event Event (data `user`).
     * @return void
     */
    public function afterLogin(EventInterface $event): void
    {
        $controller = $event->getSubject();
        if ($controller instanceof Controller) {
            SessionGuard::signedIn($controller->getRequest()->getSession());
        }
        $this->recordFor($event, $this->userId($event->getData('user')), ActivityLog::LOGIN);
    }

    /**
     * @param \Cake\Event\EventInterface<object> $event Event.
     * @return void
     */
    public function afterLoginFailure(EventInterface $event): void
    {
        $controller = $event->getSubject();
        if (!$controller instanceof Controller) {
            return;
        }
        $request = $controller->getRequest();
        AuthThrottle::recordLoginFailure((string)$request->clientIp());

        $typed = $request->getData('username');
        if (!is_string($typed) || trim($typed) === '') {
            return;
        }
        $user = TableRegistry::getTableLocator()->get((string)Configure::read('Users.table', 'Users'))
            ->find()
            ->select(['id'])
            ->where(['OR' => ['username' => trim($typed), 'email' => trim($typed)]])
            ->first();
        if ($user instanceof EntityInterface) {
            $this->log()->record((string)$user->get('id'), ActivityLog::LOGIN_FAILED, $request);
        }
    }

    /**
     * @param \Cake\Event\EventInterface<object> $event Event (data `user`: the identity).
     * @return void
     */
    public function afterLogout(EventInterface $event): void
    {
        $this->recordFor($event, $this->userId($event->getData('user')), ActivityLog::LOGOUT);
    }

    /**
     * Change (signed in: own profile, or a superadmin for someone else) or reset by email token
     * (not signed in). Every other session of the account is signed out; the user's own request
     * keeps its session (and adopts the new sign-out key).
     *
     * @param \Cake\Event\EventInterface<object> $event Event (data `user`).
     * @return void
     */
    public function afterChangePassword(EventInterface $event): void
    {
        $controller = $event->getSubject();
        $request = $controller instanceof Controller ? $controller->getRequest() : null;
        $actorId = $this->userId($request?->getAttribute('identity'));
        $userId = $this->userId($event->getData('user'));
        $this->recordFor(
            $event,
            $userId,
            $actorId === null ? ActivityLog::PASSWORD_RESET : ActivityLog::PASSWORD_CHANGED,
            $actorId,
        );
        if ($userId === null) {
            return;
        }
        $own = $request !== null && $actorId === $userId;
        $this->sessions()->passwordChanged($userId, $own ? $request->getSession()->id() : null);
        if ($own) {
            SessionGuard::forgetKey($request->getSession());
        }
    }

    /**
     * @param \Cake\Event\EventInterface<object> $event Event.
     * @param string|null $userId Account.
     * @param string $activity Activity.
     * @param string|null $actorId Who did it.
     * @return void
     */
    private function recordFor(EventInterface $event, ?string $userId, string $activity, ?string $actorId = null): void
    {
        if ($userId === null) {
            return;
        }
        $controller = $event->getSubject();
        $request = $controller instanceof Controller ? $controller->getRequest() : null;
        $this->log()->record($userId, $activity, $request, $actorId);
    }

    /**
     * @param mixed $user Identity, entity or array.
     * @return string|null
     */
    private function userId(mixed $user): ?string
    {
        if ($user instanceof EntityInterface) {
            $id = $user->get('id');
        } elseif (is_array($user) || $user instanceof ArrayAccess) {
            $id = $user['id'] ?? null;
        } else {
            $id = null;
        }

        return $id === null || $id === '' ? null : (string)$id;
    }

    /**
     * @return \App\Account\ActivityLog
     */
    private function log(): ActivityLog
    {
        return $this->log ?? new ActivityLog();
    }

    /**
     * @return \App\UseCase\Account\ActiveSessions
     */
    private function sessions(): ActiveSessions
    {
        return $this->sessions ?? new ActiveSessions();
    }
}
