<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\PermissionsTestTrait;
use App\Test\TestCase\UserFactoryTrait;
use Cake\I18n\DateTime;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Queue\Model\Entity\QueuedJob;
use Queue\Model\Table\QueuedJobsTable;

/**
 * Queue screen (superadmin): how many jobs are waiting, running and failed, the failed ones with
 * their error, retry or delete them, and the workers that are alive.
 */
class QueueScreenTest extends TestCase
{
    use IntegrationTestTrait;
    use PermissionsTestTrait;
    use UserFactoryTrait;

    protected array $fixtures = [
        'app.Users', 'app.Roles', 'app.Permissions', 'app.RolesPermissions', 'app.QueuedJobs', 'app.QueueProcesses',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->enableRetainFlashMessages();
    }

    private function jobs(): QueuedJobsTable
    {
        $jobs = $this->fetchTable('Queue.QueuedJobs');
        assert($jobs instanceof QueuedJobsTable);

        return $jobs;
    }

    private function failedJob(): QueuedJob
    {
        $job = $this->jobs()->createJob('Queue.Email', ['to' => 'someone@example.test']);
        $this->jobs()->updateAll([
            'attempts' => 2,
            'fetched' => DateTime::now()->subMinutes(5),
            'status' => QueuedJobsTable::STATUS_ABORTED,
            'failure_message' => "Cake\\Network\\Exception\\SocketException: Connection refused\n#0 /app/vendor/x.php(1)",
        ], ['id' => $job->id]);

        return $this->jobs()->get($job->id);
    }

    private function asSuperadmin(): void
    {
        $this->signIn($this->createUser(['role' => 'superadmin', 'is_superuser' => true]));
    }

    public function testSuperadminSeesCountersFailedJobsAndWorkers(): void
    {
        $this->jobs()->createJob('Queue.Email', ['to' => 'a@example.test']);
        $done = $this->jobs()->createJob('Queue.Email', ['to' => 'b@example.test']);
        $this->jobs()->updateAll(['completed' => DateTime::now()], ['id' => $done->id]);
        $failed = $this->failedJob();
        $processes = $this->fetchTable('Queue.QueueProcesses');
        $processes->saveOrFail($processes->newEntity(['pid' => '4321', 'workerkey' => 'k-alive', 'server' => 'worker-1'], ['accessibleFields' => ['*' => true]]));
        $this->asSuperadmin();

        $this->get('/queue');

        $this->assertResponseOk();
        $this->assertResponseContains('data-queue-count="pending">1<');
        $this->assertResponseContains('data-queue-count="failed">1<');
        $this->assertResponseContains('data-queue-count="done">1<');
        $this->assertResponseContains('SocketException: Connection refused');
        $this->assertResponseContains('/queue/retry/' . $failed->id);
        $this->assertResponseContains('worker-1');
        $this->assertResponseNotContains('someone@example.test', 'Job data (personal data) is not shown');
    }

    public function testAFailedJobCanBeRetriedOrDeletedAndAllCanBeRetried(): void
    {
        $this->asSuperadmin();
        $failed = $this->failedJob();

        $this->post('/queue/retry/' . $failed->id);
        $this->assertRedirect('/queue');
        $retried = $this->jobs()->get($failed->id);
        $this->assertSame(0, $retried->attempts);
        $this->assertNull($retried->status);
        $this->assertNull($retried->failure_message);
        $this->assertSame(1, $this->jobs()->getPendingCount(), 'Waiting again for the worker');

        $other = $this->failedJob();
        $this->post('/queue/delete/' . $other->id);
        $this->assertFalse($this->jobs()->exists(['id' => $other->id]));

        $this->failedJob();
        $this->failedJob();
        $this->post('/queue/retry-all');
        $this->assertSame(0, $this->jobs()->getAbortedCount());
        $this->assertFlashMessage('2 failed jobs queued again.');
    }

    public function testPendingJobsCannotBeDeletedAndActionsArePostOnly(): void
    {
        $this->asSuperadmin();
        $pending = $this->jobs()->createJob('Queue.Email', ['to' => 'a@example.test']);

        $this->post('/queue/delete/' . $pending->id);
        $this->assertTrue($this->jobs()->exists(['id' => $pending->id]), 'Only failed jobs are deleted');

        $this->get('/queue/retry/' . $pending->id);
        $this->assertResponseCode(405);
    }

    public function testOnlySuperadminsSeeTheQueue(): void
    {
        $this->signIn($this->createUser(['role' => 'admin']));
        $this->get('/queue');
        $this->assertResponseNotContains('data-queue-count');

        $this->asSuperadmin();
        $this->get('/');
        $this->assertResponseContains('href="/queue"');
    }
}
