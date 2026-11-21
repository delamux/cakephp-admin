<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;

/**
 * `bin/cake permissions purge`: retention of the permissions history.
 *
 * @link \App\Command\PermissionsPurgeCommand
 */
class PermissionsPurgeCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    protected array $fixtures = ['app.PermissionAudits'];

    private function audit(int $daysAgo, string $event = 'grants_changed'): void
    {
        $audits = $this->fetchTable('PermissionAudits');
        $audits->saveOrFail($audits->newEntity([
            'username' => 'root',
            'event' => $event,
            'created' => DateTime::now()->subDays($daysAgo),
        ], ['accessibleFields' => ['*' => true]]));
    }

    /**
     * @return list<string>
     */
    private function events(): array
    {
        return $this->fetchTable('PermissionAudits')->find()->orderBy(['id' => 'ASC'])
            ->all()->map(fn($a): string => (string)$a->get('event'))->toList();
    }

    public function testDeletesEntriesOlderThanTheRetentionAndRecordsThePurge(): void
    {
        $this->audit(400, 'role_created');
        $this->audit(200, 'role_updated');
        $this->audit(5, 'role_deleted');

        $this->exec('permissions purge --days 180');

        $this->assertExitSuccess();
        $this->assertOutputContains('2 history entries older than 180 days deleted.');
        $this->assertSame(['role_deleted', 'audit_purged'], $this->events());
        $purge = $this->fetchTable('PermissionAudits')->find()->where(['event' => 'audit_purged'])->firstOrFail();
        $this->assertSame('console', $purge->get('username'));
        $this->assertSame(2, $purge->get('details')['deleted']);
        $this->assertSame(180, $purge->get('details')['days']);
    }

    public function testRetentionDefaultsToTheConfiguredDays(): void
    {
        // config/users.php: App.permissions.auditRetentionDays = 365 (loaded by the app bootstrap in exec())
        $this->audit(400);
        $this->audit(300);

        $this->exec('permissions purge');

        $this->assertOutputContains('1 history entries older than 365 days deleted.');
    }

    public function testDryRunOnlyCounts(): void
    {
        $this->audit(400);

        $this->exec('permissions purge --days 365 --dry-run');

        $this->assertExitSuccess();
        $this->assertOutputContains('1 history entries older than 365 days would be deleted.');
        $this->assertSame(['grants_changed'], $this->events());
    }

    public function testRetentionBelowTheMinimumIsRejected(): void
    {
        $this->audit(40);

        $this->exec('permissions purge --days 7');

        $this->assertExitError();
        $this->assertErrorContains('at least 30 days');
        $this->assertSame(['grants_changed'], $this->events());
    }

    public function testNothingToPurgeLeavesNoTrace(): void
    {
        $this->audit(10);

        $this->exec('permissions purge --days 365');

        $this->assertOutputContains('0 history entries');
        $this->assertSame(['grants_changed'], $this->events(), 'A daily cron must not flood the history');
    }
}
