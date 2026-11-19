<?php
declare(strict_types=1);

namespace App\UseCase\Queue;

use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Queue\Model\Table\QueuedJobsTable;
use Queue\Model\Table\QueueProcessesTable;

/**
 * Read model and actions of the Queue screen (QueueController), over the Queue plugin tables.
 * Failed = retries used up (`aborted`); only those can be deleted, a pending or running job never.
 * Job data is never exposed (emails carry personal data): task, dates, attempts and the error.
 */
class QueueMonitor
{
    private QueuedJobsTable $jobs;

    private QueueProcessesTable $processes;

    /**
     * @param \Queue\Model\Table\QueuedJobsTable|null $jobs Jobs table.
     * @param \Queue\Model\Table\QueueProcessesTable|null $processes Workers table.
     */
    public function __construct(?QueuedJobsTable $jobs = null, ?QueueProcessesTable $processes = null)
    {
        $locator = TableRegistry::getTableLocator();
        $jobs ??= $locator->get('Queue.QueuedJobs');
        $processes ??= $locator->get('Queue.QueueProcesses');
        assert($jobs instanceof QueuedJobsTable && $processes instanceof QueueProcessesTable);
        $this->jobs = $jobs;
        $this->processes = $processes;
    }

    /**
     * @return array{pending: int, running: int, scheduled: int, failed: int, done: int}
     */
    public function counters(): array
    {
        $running = $this->jobs->find()->where([
            'completed IS' => null,
            'fetched IS NOT' => null,
            'failure_message IS' => null,
            'OR' => ['status IS' => null, 'status !=' => QueuedJobsTable::STATUS_ABORTED],
        ])->count();

        return [
            'pending' => max(0, $this->jobs->getPendingCount() - $running),
            'running' => $running,
            'scheduled' => $this->jobs->getScheduledCount(),
            'failed' => $this->jobs->getAbortedCount(),
            'done' => $this->jobs->find()->where(['completed >=' => DateTime::now()->subDays(1)])->count(),
        ];
    }

    /**
     * @param int $limit Max rows.
     * @return list<\Queue\Model\Entity\QueuedJob> Newest first.
     */
    public function failedJobs(int $limit = 50): array
    {
        /** @var list<\Queue\Model\Entity\QueuedJob> $rows */
        $rows = $this->jobs->getAbortedStats()->limit($limit)->all()->toList();

        return $rows;
    }

    /**
     * @param int $limit Max rows.
     * @return list<\Queue\Model\Entity\QueuedJob> Newest first, without their data.
     */
    public function recentJobs(int $limit = 20): array
    {
        /** @var list<\Queue\Model\Entity\QueuedJob> $rows */
        $rows = $this->jobs->find()
            ->select([
                'id', 'job_task', 'created', 'notbefore', 'fetched', 'completed',
                'attempts', 'status', 'failure_message',
            ])
            ->orderByDesc('id')
            ->limit($limit)
            ->all()
            ->toList();

        return $rows;
    }

    /**
     * @return list<\Queue\Model\Entity\QueueProcess> Workers with a recent heartbeat.
     */
    public function workers(): array
    {
        /** @var list<\Queue\Model\Entity\QueueProcess> $rows */
        $rows = $this->processes->find('active')->orderByDesc('modified')->all()->toList();

        return $rows;
    }

    /**
     * @param int $id Job id.
     * @return bool False when the job is not a failed one.
     */
    public function retry(int $id): bool
    {
        return $this->jobs->reset($id) > 0;
    }

    /**
     * @return int Failed jobs queued again.
     */
    public function retryAll(): int
    {
        return $this->jobs->resetAborted();
    }

    /**
     * @param int $id Job id.
     * @return bool False when the job is not a failed one (pending or running jobs are kept).
     */
    public function delete(int $id): bool
    {
        return $this->jobs->deleteAll([
            'id' => $id,
            'completed IS' => null,
            'status' => QueuedJobsTable::STATUS_ABORTED,
        ]) > 0;
    }
}
