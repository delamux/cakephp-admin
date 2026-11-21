<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Account\ActivityLog;
use App\Test\TestCase\UserFactoryTrait;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;

/**
 * `bin/cake maintenance`: the one daily cron. Applies every retention (account activity,
 * permissions history, finished queue jobs) and removes expired sessions and throttling
 * counters; recent data is kept.
 *
 * @link \App\Command\MaintenanceCommand
 */
class MaintenanceCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;
    use UserFactoryTrait;

    protected array $fixtures = [
        'app.Users', 'app.AccountActivities', 'app.PermissionAudits', 'app.QueuedJobs', 'app.AuthThrottles',
    ];

    public function testDailyMaintenanceCleansEverythingPastItsRetention(): void
    {
        $user = $this->createUser();
        $log = new ActivityLog();
        $log->record((string)$user->id, ActivityLog::LOGIN);
        $log->record((string)$user->id, ActivityLog::LOGOUT);
        $this->fetchTable('AccountActivities')->updateAll(['created' => DateTime::now()->subDays(200)], ['event' => ActivityLog::LOGIN]);

        $audits = $this->fetchTable('PermissionAudits');
        foreach ([400, 5] as $days) {
            $audits->saveOrFail($audits->newEntity(['username' => 'root', 'event' => 'grants_changed', 'created' => DateTime::now()->subDays($days)], ['accessibleFields' => ['*' => true]]));
        }

        $jobs = $this->fetchTable('Queue.QueuedJobs');
        $old = $jobs->createJob('Queue.Email', ['x' => 1]);
        $recent = $jobs->createJob('Queue.Email', ['x' => 2]);
        $pending = $jobs->createJob('Queue.Email', ['x' => 3]);
        $jobs->updateAll(['completed' => DateTime::now()->subDays(40)], ['id' => $old->id]);
        $jobs->updateAll(['completed' => DateTime::now()->subDays(1)], ['id' => $recent->id]);

        $db = ConnectionManager::get('test');
        $db->execute('DELETE FROM sessions');
        $db->execute("INSERT INTO sessions (id, data, expires) VALUES ('expired', '', :old), ('live', '', :new)", ['old' => time() - 60, 'new' => time() + 3600], ['old' => 'integer', 'new' => 'integer']);
        $db->execute("INSERT INTO auth_throttles (bucket, ip_hash, hits, window_ends) VALUES ('login', :a, 3, :old), ('login', :b, 1, :new)", ['a' => str_repeat('a', 64), 'b' => str_repeat('b', 64), 'old' => time() - 60, 'new' => time() + 600], ['old' => 'integer', 'new' => 'integer']);

        $this->exec('maintenance');

        $this->assertExitSuccess();
        $this->assertOutputContains('Account activity: 1 deleted');
        $this->assertOutputContains('Permissions history: 1 deleted');
        $this->assertOutputContains('Queue: 1 deleted');
        $this->assertOutputContains('Expired sessions: 1 deleted');
        $this->assertOutputContains('Throttling counters: 1 deleted');

        $this->assertSame([ActivityLog::LOGOUT], $this->fetchTable('AccountActivities')->find()->all()->extract('event')->toList());
        $this->assertSame(['grants_changed', 'audit_purged'], $audits->find()->orderBy(['id' => 'ASC'])->all()->extract('event')->toList(), 'The purge records itself');
        $this->assertSame([$recent->id, $pending->id], $jobs->find()->orderBy(['id' => 'ASC'])->all()->extract('id')->toList(), 'Pending and recent jobs kept');
        $this->assertSame(['live'], array_column($db->execute('SELECT id FROM sessions')->fetchAll('assoc'), 'id'));
        $hashes = array_column($db->execute('SELECT ip_hash FROM auth_throttles')->fetchAll('assoc'), 'ip_hash');
        $this->assertNotContains(str_repeat('a', 64), $hashes, 'Expired window deleted');
        $this->assertContains(str_repeat('b', 64), $hashes, 'Live window kept');
        $db->execute('DELETE FROM sessions');
    }
}
