<?php
declare(strict_types=1);

namespace App\View\Cell;

use App\UseCase\Account\ActiveSessions;
use Cake\View\Cell;

/**
 * "Where you're signed in" card of the own profile (App\UseCase\Account\ActiveSessions).
 */
class ActiveSessionsCell extends Cell
{
    /**
     * @param string $userId Signed-in user.
     * @param string $currentId Session id of the request.
     * @return void
     */
    public function display(string $userId, string $currentId): void
    {
        $this->set('sessions', (new ActiveSessions())->list($userId, $currentId));
    }
}
