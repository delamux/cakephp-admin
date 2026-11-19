<?php
declare(strict_types=1);

namespace App\UseCase\Account;

use App\Account\ActivityLog;
use App\Account\UserAgent;
use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Security;

/**
 * "Where you're signed in": the live sessions of an account (from App\Http\Session\AppDatabaseSession)
 * and signing out one of them or all but the current one.
 *
 * Pages only see `key` (SHA-256 of the session id), never the id itself: it is the session cookie.
 * Signing out the other sessions also rotates `users.session_key`, part of the "remember me" cookie
 * token (App\Model\Entity\User::_getRememberSecret()), so those devices cannot sign back in alone.
 */
class ActiveSessions
{
    private Table $sessions;

    private Table $users;

    private ActivityLog $activity;

    /**
     * @param \Cake\ORM\Table|null $sessions Sessions table.
     * @param \Cake\ORM\Table|null $users Users table.
     * @param \App\Account\ActivityLog|null $activity Activity log.
     */
    public function __construct(?Table $sessions = null, ?Table $users = null, ?ActivityLog $activity = null)
    {
        $locator = TableRegistry::getTableLocator();
        $this->sessions = $sessions ?? $locator->get('Sessions');
        $this->users = $users ?? $locator->get((string)Configure::read('Users.table', 'Users'));
        $this->activity = $activity ?? new ActivityLog();
    }

    /**
     * @param string $userId User.
     * @param string $currentId Session id of the request (marked `current`, listed first).
     * @return list<array{key: string, current: bool, ip: string|null, browser: string, lastActive: \Cake\I18n\DateTime}>
     */
    public function list(string $userId, string $currentId): array
    {
        $timeout = (int)Configure::read('Session.timeout', 240) * 60;
        $rows = $this->sessions->find()
            ->select(['id', 'ip', 'user_agent', 'expires'])
            ->where(['user_id' => $userId, 'expires >' => time()])
            ->orderBy(['expires' => 'DESC'])
            ->all();
        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'key' => hash('sha256', (string)$row->get('id')),
                'current' => $currentId !== '' && hash_equals((string)$row->get('id'), $currentId),
                'ip' => $row->get('ip'),
                'browser' => UserAgent::describe($row->get('user_agent')),
                // `expires` = last write + timeout
                'lastActive' => DateTime::createFromTimestamp((int)$row->get('expires') - $timeout),
            ];
        }
        usort($list, fn(array $a, array $b): int => $b['current'] <=> $a['current']);

        return $list;
    }

    /**
     * @param string $userId User.
     * @param string $key `key` of the session to sign out (not the current one).
     * @param string $currentId Session id of the request.
     * @return bool Whether a session was signed out.
     */
    public function signOut(string $userId, string $key, string $currentId): bool
    {
        foreach ($this->sessions->find()->select(['id'])->where(['user_id' => $userId])->all() as $row) {
            $id = (string)$row->get('id');
            if ($id !== $currentId && hash_equals(hash('sha256', $id), $key)) {
                return $this->sessions->deleteAll(['id' => $id, 'user_id' => $userId]) > 0;
            }
        }

        return false;
    }

    /**
     * Signs out every session of the user but the current one, and invalidates their "remember me"
     * cookies.
     *
     * @param string $userId User.
     * @param string $currentId Session id of the request.
     * @return int Sessions signed out.
     */
    public function signOutOthers(string $userId, string $currentId): int
    {
        $removed = $this->sessions->deleteAll(['user_id' => $userId, 'id !=' => $currentId]);
        $this->users->updateQuery()
            ->set(['session_key' => bin2hex(Security::randomBytes(32))])
            ->where(['id' => $userId])
            ->execute();
        $this->activity->record($userId, ActivityLog::SESSIONS_REVOKED);

        return $removed;
    }
}
