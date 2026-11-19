<?php
declare(strict_types=1);

namespace App\Test\TestCase\Queue;

use App\Queue\QueueFailureListener;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Log\Engine\ArrayLog;
use Cake\Log\Log;
use Cake\TestSuite\TestCase;
use Queue\Model\Entity\QueuedJob;

/**
 * Failed jobs reach the error log (stderr in production, collected by Coolify): a failed attempt
 * is a warning, a job that used up its retries is an error. Only the first line of the failure
 * (no stack trace, no job data: emails carry personal data).
 */
class QueueFailureLogTest extends TestCase
{
    private ArrayLog $log;

    protected function setUp(): void
    {
        parent::setUp();
        Log::setConfig('queue-capture', ['className' => 'Array', 'levels' => ['warning', 'error']]);
        $engine = Log::engine('queue-capture');
        assert($engine instanceof ArrayLog);
        $this->log = $engine;
        EventManager::instance()->on(new QueueFailureListener());
    }

    protected function tearDown(): void
    {
        Log::drop('queue-capture');
        parent::tearDown();
    }

    private function job(): QueuedJob
    {
        return new QueuedJob(['id' => 42, 'job_task' => 'Queue.Email', 'attempts' => 2, 'data' => ['to' => 'someone@example.test']]);
    }

    public function testAFailedAttemptIsAWarningAndExhaustedRetriesAnError(): void
    {
        $failure = "Cake\\Network\\Exception\\SocketException: Connection refused\n#0 /app/vendor/...";

        EventManager::instance()->dispatch(new Event('Queue.Job.failed', null, ['job' => $this->job(), 'failureMessage' => $failure]));
        EventManager::instance()->dispatch(new Event('Queue.Job.maxAttemptsExhausted', null, ['job' => $this->job(), 'failureMessage' => $failure]));

        $lines = $this->log->read();
        $this->assertCount(2, $lines);
        $this->assertStringStartsWith('warning: ', $lines[0]);
        $this->assertStringContainsString('Queue job 42 (Queue.Email) failed, attempt 2: Cake\\Network\\Exception\\SocketException: Connection refused', $lines[0]);
        $this->assertStringStartsWith('error: ', $lines[1]);
        $this->assertStringContainsString('Queue job 42 (Queue.Email) gave up after 2 attempts', $lines[1]);
        $all = implode("\n", $lines);
        $this->assertStringNotContainsString('#0 /app', $all, 'No stack trace');
        $this->assertStringNotContainsString('someone@example.test', $all, 'No job data');
    }
}
