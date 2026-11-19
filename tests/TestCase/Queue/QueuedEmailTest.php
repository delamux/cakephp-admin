<?php
declare(strict_types=1);

namespace App\Test\TestCase\Queue;

use App\Mailer\UsersMailer;
use App\Test\TestCase\UserFactoryTrait;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Mailer\Mailer;
use Cake\Mailer\TransportFactory;
use Cake\TestSuite\TestCase;
use Cake\TestSuite\TestEmailTransport;

/**
 * Every email goes through the queue (`queue` transport in front of the real one): the request
 * only stores a job, and the worker (`bin/cake queue run`) delivers it later, already rendered
 * (layout and inlined CSS included).
 */
class QueuedEmailTest extends TestCase
{
    use ConsoleIntegrationTestTrait;
    use UserFactoryTrait;

    protected array $fixtures = ['app.Users', 'app.QueuedJobs', 'app.QueueProcesses'];

    /**
     * @var array<string, mixed>
     */
    private array $transports = [];

    public function testTheDefaultProfileUsesTheQueueTransportInFrontOfTheRealOne(): void
    {
        $this->assertSame('queue', Mailer::getConfig('default')['transport'] ?? null);
        $this->assertSame('default', $this->transports['queue']['transport'] ?? null);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Configure::write('Users.controller', 'CakeDC/Users.Users');
        $this->loadPlugins(['CakeDC/Users', 'Queue']);
        // config/app.php `queue` transport (EmailTrait in other tests replaces every transport for
        // good, and bootstrap consumed the config) in front of the real one, captured here
        $this->transports = ['queue' => TransportFactory::getConfig('queue'), 'default' => TransportFactory::getConfig('default')];
        TransportFactory::drop('queue');
        TransportFactory::setConfig('queue', ['className' => 'Queue.Queue', 'transport' => 'default']);
        TransportFactory::drop('default');
        TransportFactory::setConfig('default', ['className' => TestEmailTransport::class]);
        TestEmailTransport::clearMessages();
    }

    protected function tearDown(): void
    {
        foreach ($this->transports as $name => $config) {
            TransportFactory::drop($name);
            TransportFactory::setConfig($name, (array)$config);
        }
        parent::tearDown();
    }

    public function testEmailsAreQueuedAndTheWorkerDeliversThemRendered(): void
    {
        $user = $this->createUser(['token' => 'tok-queued-123']);

        (new UsersMailer())->send('resetPassword', [$user]);

        $this->assertSame([], TestEmailTransport::getMessages(), 'Nothing sent during the request');
        $jobs = $this->fetchTable('Queue.QueuedJobs')->find()->all()->toList();
        $this->assertCount(1, $jobs);
        $this->assertSame('Queue.Email', $jobs[0]->job_task);

        Configure::write('Queue.exitwhennothingtodo', true);
        // The command boots the whole Application, which loads the plugins itself
        $this->clearPlugins();
        $this->appPluginsToLoad = [];
        $this->exec('queue run --max-runtime 30');
        $this->assertExitSuccess();

        $messages = TestEmailTransport::getMessages();
        $this->assertCount(1, $messages, 'Delivered by the worker through the real transport');
        $this->assertSame([$user->email => $user->email], $messages[0]->getTo());
        $this->assertStringContainsString('tok-queued-123', $messages[0]->getBodyHtml());
        $this->assertMatchesRegularExpression('/class="button"[^>]*style=/', $messages[0]->getBodyHtml(), 'CSS inlined');
        $this->assertNotNull($this->fetchTable('Queue.QueuedJobs')->get($jobs[0]->id)->completed);
    }
}
