<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Database\Expression\QueryExpression;
use Cake\I18n\DateTime;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use CakeDC\Users\Model\Table\UsersTable;

/**
 * Numbers shown on the dashboard.
 *
 * Rules:
 * - pending = not active (email not validated yet),
 * - admins = role `admin` OR superuser,
 * - new users = signups in the last 30 days, growth compared with the 30 days before
 *   (null when the previous period has no signups: nothing to compare with),
 * - signups per day: one point per day, days without signups are 0, oldest first, ending today.
 */
class DashboardStatsService
{
    public const PERIOD_DAYS = 30;

    /**
     * @param \Cake\ORM\Table $users Users table.
     */
    public function __construct(private Table $users)
    {
    }

    /**
     * @return array{total: int, active: int, pending: int, admins: int, newUsers: int, newUsersPrevious: int, newUsersGrowth: float|null}
     */
    public function summary(): array
    {
        $now = DateTime::now();
        $periodStart = $now->subDays(self::PERIOD_DAYS);
        $previousStart = $periodStart->subDays(self::PERIOD_DAYS);

        $total = $this->users->find()->count();
        $active = $this->users->find()->where(['active' => true])->count();
        $admins = $this->administratorsQuery()->count();
        $newUsers = $this->users->find()->where(['created >' => $periodStart])->count();
        $newUsersPrevious = $this->users->find()
            ->where(['created >' => $previousStart, 'created <=' => $periodStart])
            ->count();

        return [
            'total' => $total,
            'active' => $active,
            'pending' => $total - $active,
            'admins' => $admins,
            'newUsers' => $newUsers,
            'newUsersPrevious' => $newUsersPrevious,
            'newUsersGrowth' => $this->growth($newUsers, $newUsersPrevious),
        ];
    }

    /**
     * @return list<array{date: string, users: int}>
     */
    public function signupsPerDay(int $days = self::PERIOD_DAYS): array
    {
        $today = DateTime::now()->startOfDay();
        $from = $today->subDays($days - 1);

        $counts = [];
        $created = $this->users->find()
            ->select(['created'])
            ->where(['created >=' => $from])
            ->all();
        foreach ($created as $user) {
            $day = $user->get('created')->format('Y-m-d');
            $counts[$day] = ($counts[$day] ?? 0) + 1;
        }

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->addDays($i)->format('Y-m-d');
            $series[] = ['date' => $day, 'users' => $counts[$day] ?? 0];
        }

        return $series;
    }

    /**
     * @return list<\Cake\Datasource\EntityInterface>
     */
    public function recentUsers(int $limit = 5): array
    {
        return array_values($this->users->find()
            ->orderByDesc('created')
            ->limit($limit)
            ->all()
            ->toList());
    }

    /**
     * Administrators (role `admin` or superuser), newest first, whatever their signup date.
     *
     * @return list<\Cake\Datasource\EntityInterface>
     */
    public function administrators(int $limit = 5): array
    {
        /** @var list<\Cake\Datasource\EntityInterface> $admins */
        $admins = $this->administratorsQuery()
            ->orderByDesc('created')
            ->limit($limit)
            ->all()
            ->toList();

        return $admins;
    }

    /**
     * Users that are admins (role `admin`) or superusers.
     *
     * @return \Cake\ORM\Query\SelectQuery<\Cake\Datasource\EntityInterface>
     */
    private function administratorsQuery(): SelectQuery
    {
        return $this->users->find()
            ->where(fn(QueryExpression $exp) => $exp->or([
                'role' => UsersTable::ROLE_ADMIN,
                'is_superuser' => true,
            ]));
    }

    /**
     * Growth percentage, null when there is nothing to compare with.
     *
     * @param int $current Current period count.
     * @param int $previous Previous period count.
     * @return float|null
     */
    private function growth(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }
}
