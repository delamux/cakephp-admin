<?php
declare(strict_types=1);

namespace App\UseCase\Maintenance;

use App\Http\AuthThrottle;
use App\UseCase\Account\PurgeAccountActivity;
use App\UseCase\Actor;
use App\UseCase\Permissions\PurgePermissionHistory;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Closure;
use Queue\Model\Table\QueuedJobsTable;
use Throwable;

/**
 * Everything that has to run once a day (`bin/cake maintenance`, the one cron / Coolify Scheduled
 * Task): retention of the account activity and the permissions history, finished queue jobs,
 * expired sessions and throttling counters. One failing task does not stop the others.
 * New daily work is added here, so the cron never changes.
 */
class DailyMaintenance
{
    /**
     * @param \App\UseCase\Account\PurgeAccountActivity $activity Account activity retention.
     * @param \App\UseCase\Permissions\PurgePermissionHistory $permissions Permissions history retention.
     */
    public function __construct(
        private PurgeAccountActivity $activity,
        private PurgePermissionHistory $permissions,
    ) {
    }

    /**
     * @return array<string, \Closure(): int> Task name => deleted rows.
     */
    public function tasks(): array
    {
        return [
            'Account activity' => fn(): int => $this->activity->execute(
                (int)Configure::read('App.accountActivity.retentionDays', 180),
            ),
            'Permissions history' => fn(): int => $this->permissions->execute(
                (int)Configure::read('App.permissions.auditRetentionDays', 365),
                Actor::console(),
            ),
            // Completed jobs after Queue.cleanuptimeout, aborted ones after Queue.cleanupAbortedTimeout
            'Queue' => function (): int {
                $jobs = TableRegistry::getTableLocator()->get('Queue.QueuedJobs');
                assert($jobs instanceof QueuedJobsTable);

                return $jobs->cleanOldJobs();
            },
            // PHP's own session GC is probabilistic; this makes it certain
            'Expired sessions' => function (): int {
                $db = ConnectionManager::get('default');
                assert($db instanceof Connection);

                return $db->execute(
                    'DELETE FROM sessions WHERE expires < :now',
                    ['now' => time()],
                    ['now' => 'integer'],
                )->rowCount();
            },
            'Throttling counters' => fn(): int => AuthThrottle::purgeExpired(),
        ];
    }

    /**
     * @return array<string, int|\Throwable> Task name => deleted rows, or the error it raised.
     */
    public function run(): array
    {
        $results = [];
        foreach ($this->tasks() as $name => $task) {
            $results[$name] = $this->attempt($name, $task);
        }

        return $results;
    }

    /**
     * @param string $name Task name.
     * @param \Closure(): int $task Task.
     * @return \Throwable|int
     */
    private function attempt(string $name, Closure $task): int|Throwable
    {
        try {
            return $task();
        } catch (Throwable $e) {
            Log::error(sprintf('Maintenance task "%s" failed: %s', $name, $e->getMessage()));

            return $e;
        }
    }
}
