<?php
declare(strict_types=1);

namespace App\Http\Session;

use ArrayAccess;
use Cake\Http\Session\DatabaseSession;
use Cake\Routing\Router;

/**
 * Database sessions (CakePHP DatabaseSession) that also store the signed-in user, the client IP
 * (trusted proxies applied) and the browser on every write, for "Where you're signed in"
 * (App\UseCase\Account\ActiveSessions). PHP writes the session on every request, so the IP, browser
 * and `expires` (last activity + timeout) stay current.
 */
class AppDatabaseSession extends DatabaseSession
{
    /**
     * @param string $id Session id.
     * @param string $data Serialized session data.
     * @return bool
     */
    public function write(string $id, string $data): bool
    {
        if (!$id) {
            return false;
        }
        $request = Router::getRequest();
        $userAgent = $request?->getHeaderLine('User-Agent');
        /** @var string $pkField */
        $pkField = $this->_table->getPrimaryKey();
        $session = $this->_table->newEntity([
            $pkField => $id,
            'data' => $data,
            'expires' => time() + $this->_timeout,
            'user_id' => $this->userId(),
            'ip' => $request?->clientIp() ?: null,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
        ], ['accessibleFields' => ['*' => true]]);

        return (bool)$this->_table->save($session);
    }

    /**
     * @return string|null Signed-in user of the session being written.
     */
    private function userId(): ?string
    {
        $auth = $_SESSION['Auth'] ?? null;
        $id = is_array($auth) || $auth instanceof ArrayAccess ? ($auth['id'] ?? null) : null;

        return is_string($id) && $id !== '' ? $id : null;
    }
}
