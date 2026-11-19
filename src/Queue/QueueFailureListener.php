<?php
declare(strict_types=1);

namespace App\Queue;

use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Cake\Log\Log;
use Queue\Model\Entity\QueuedJob;

/**
 * Queue failures -> error log (stderr in production): a failed attempt is a warning (it will be
 * retried), a job that used up its retries an error (it stays on the Queue screen until it is
 * retried or deleted). Only the first line of the failure: no stack trace and no job data
 * (emails carry personal data).
 */
class QueueFailureListener implements EventListenerInterface
{
    /**
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        return [
            'Queue.Job.failed' => 'failed',
            'Queue.Job.maxAttemptsExhausted' => 'exhausted',
        ];
    }

    /**
     * @param \Cake\Event\EventInterface<object> $event Event (`job`, `failureMessage`).
     * @return void
     */
    public function failed(EventInterface $event): void
    {
        $job = $event->getData('job');
        if (!$job instanceof QueuedJob) {
            return;
        }
        Log::warning(sprintf(
            'Queue job %d (%s) failed, attempt %d: %s',
            $job->id,
            $job->job_task,
            $job->attempts,
            self::firstLine($event->getData('failureMessage')),
        ));
    }

    /**
     * @param \Cake\Event\EventInterface<object> $event Event (`job`, `failureMessage`).
     * @return void
     */
    public function exhausted(EventInterface $event): void
    {
        $job = $event->getData('job');
        if (!$job instanceof QueuedJob) {
            return;
        }
        Log::error(sprintf(
            'Queue job %d (%s) gave up after %d attempts: %s',
            $job->id,
            $job->job_task,
            $job->attempts,
            self::firstLine($event->getData('failureMessage')),
        ));
    }

    /**
     * @param mixed $message Failure message (exception class, message and trace).
     * @return string
     */
    private static function firstLine(mixed $message): string
    {
        return mb_substr(strtok(is_string($message) ? $message : '', "\n") ?: 'unknown error', 0, 500);
    }
}
