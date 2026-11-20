<?php
declare(strict_types=1);

namespace App\Account;

use Cake\Http\ServerRequest;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Throwable;

/**
 * Account activity log (repository over `account_activities`): security events of an account with
 * the IP and browser of the request. Only the event is stored, never what was typed (passwords,
 * codes, usernames of failed attempts). Writing it never breaks the action being logged: a
 * failure goes to the error log.
 */
class ActivityLog
{
    public const LOGIN = 'login';
    public const PASSKEY_LOGIN = 'passkey_login';
    public const LOGIN_FAILED = 'login_failed';
    public const TWO_FACTOR_FAILED = 'two_factor_failed';
    public const LOGOUT = 'logout';
    public const PASSWORD_CHANGED = 'password_changed';
    public const PASSWORD_RESET = 'password_reset';
    public const TOTP_ENABLED = 'totp_enabled';
    public const TOTP_DISABLED = 'totp_disabled';
    public const KEY_ADDED = 'key_added';
    public const KEY_REMOVED = 'key_removed';
    public const TWO_FACTOR_RESET = 'two_factor_reset';
    public const PHOTO_CHANGED = 'photo_changed';
    public const PHOTO_REMOVED = 'photo_removed';
    public const INVITED = 'invited';
    public const INVITATION_ACCEPTED = 'invitation_accepted';
    public const SESSIONS_REVOKED = 'sessions_revoked';

    /**
     * Events that may mean someone else is trying to get in (highlighted in the UI).
     */
    public const WARNINGS = [self::LOGIN_FAILED, self::TWO_FACTOR_FAILED, self::TWO_FACTOR_RESET];

    private Table $activities;

    /**
     * @param \Cake\ORM\Table|null $activities AccountActivities table.
     */
    public function __construct(?Table $activities = null)
    {
        $this->activities = $activities ?? TableRegistry::getTableLocator()->get('AccountActivities');
    }

    /**
     * @param string $userId Account the event belongs to.
     * @param string $event One of the constants.
     * @param \Cake\Http\ServerRequest|null $request Request (IP, browser); the current one if null,
     *   none from the console.
     * @param string|null $actorId Who did it when it was not the account owner.
     * @return void
     */
    public function record(
        string $userId,
        string $event,
        ?ServerRequest $request = null,
        ?string $actorId = null,
    ): void {
        $request ??= Router::getRequest();
        $userAgent = $request?->getHeaderLine('User-Agent');
        try {
            $this->activities->saveOrFail($this->activities->newEntity([
                'user_id' => $userId,
                'event' => $event,
                'actor_id' => $actorId !== $userId ? $actorId : null,
                'ip' => $request?->clientIp() ?: null,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
            ], ['accessibleFields' => ['*' => true]]));
        } catch (Throwable $e) {
            Log::error(sprintf(
                'Account activity "%s" of user %s not recorded: %s',
                $event,
                $userId,
                $e->getMessage(),
            ));
        }
    }

    /**
     * @param string $userId User id.
     * @param int $limit Max entries.
     * @return list<\Cake\Datasource\EntityInterface> Newest first.
     */
    public function recentFor(string $userId, int $limit = 20): array
    {
        /** @var list<\Cake\Datasource\EntityInterface> $rows */
        $rows = $this->activities->find()
            ->where(['user_id' => $userId])
            ->orderBy(['id' => 'DESC'])
            ->limit($limit)
            ->all()
            ->toList();

        return $rows;
    }

    /**
     * @param \Cake\I18n\DateTime $before Cutoff.
     * @return int
     */
    public function countBefore(DateTime $before): int
    {
        return $this->activities->find()->where(['created <' => $before])->count();
    }

    /**
     * @param \Cake\I18n\DateTime $before Cutoff.
     * @return int Deleted entries.
     */
    public function deleteBefore(DateTime $before): int
    {
        return $this->activities->deleteAll(['created <' => $before]);
    }
}
