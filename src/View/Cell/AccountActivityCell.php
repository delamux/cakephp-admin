<?php
declare(strict_types=1);

namespace App\View\Cell;

use App\Account\ActivityLog;
use Cake\Core\Configure;
use Cake\View\Cell;

/**
 * "Recent activity" card: last events of an account (own profile, or a user's page for
 * superadmins; the templates decide who sees it). Shows who acted when it was not the owner.
 */
class AccountActivityCell extends Cell
{
    /**
     * @param string $userId Account.
     * @param bool $own Own profile (changes the wording).
     * @return void
     */
    public function display(string $userId, bool $own = true): void
    {
        $entries = (new ActivityLog())->recentFor($userId);
        $actorIds = array_values(array_unique(array_filter(array_map(
            fn($entry): ?string => $entry->get('actor_id'),
            $entries,
        ))));
        $actors = $actorIds === [] ? [] : $this->fetchTable((string)Configure::read('Users.table', 'Users'))
            ->find('list', keyField: 'id', valueField: 'username')
            ->where(['id IN' => $actorIds])
            ->toArray();

        $this->set([
            'entries' => $entries,
            'actors' => $actors,
            'own' => $own,
            'retentionDays' => (int)Configure::read('App.accountActivity.retentionDays', 180),
        ]);
    }
}
