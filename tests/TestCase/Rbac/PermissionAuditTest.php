<?php
declare(strict_types=1);

namespace App\Test\TestCase\Rbac;

use App\Rbac\PermissionsSynchronizer;
use App\Test\TestCase\PermissionsTestTrait;
use App\Test\TestCase\UserFactoryTrait;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use CakeDC\Users\Model\Entity\User;

/**
 * Every change of roles/permissions is audited: who, when, what (added/removed).
 */
class PermissionAuditTest extends TestCase
{
    use ConsoleIntegrationTestTrait;
    use IntegrationTestTrait;
    use PermissionsTestTrait;
    use UserFactoryTrait;

    protected array $fixtures = ['app.Users', 'app.Roles', 'app.Permissions', 'app.RolesPermissions', 'app.PermissionAudits'];

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->fetchTable('PermissionAudits')->deleteAll([]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        // Matrix checkboxes are plain inputs, unlocked in PermissionsController (FormProtection)
        $this->setUnlockedFields(['grants']);
        $this->super = $this->createUser(['username' => 'root', 'role' => 'superadmin', 'is_superuser' => true]);
        $this->session(['Auth' => $this->super]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function audits(): array
    {
        return $this->fetchTable('PermissionAudits')->find()->orderBy(['id' => 'ASC'])->disableHydration()->all()->toList();
    }

    public function testSetGrantsReturnsWhatChanged(): void
    {
        $diff = PermissionsSynchronizer::fromConfig()->setGrants('editor', ['CakeDC/Users.Users::index', 'Dashboard::index']);

        $this->assertSame(['Dashboard::index'], $diff['added']);
        $this->assertSame(['CakeDC/Users.Users::view', 'Reports::users'], $diff['removed']);
    }

    public function testSavingTheMatrixAuditsOnlyTheRolesThatChanged(): void
    {
        $current = PermissionsSynchronizer::fromConfig()->grants();
        $this->post('/permissions', ['grants' => [
            'admin' => $current['admin'],
            'editor' => ['CakeDC/Users.Users::index', 'CakeDC/Users.Users::edit'],
        ]]);

        $audits = $this->audits();
        $this->assertCount(1, $audits, 'admin unchanged, user had nothing and still has nothing');
        $this->assertSame('grants_changed', $audits[0]['event']);
        $this->assertSame('editor', $audits[0]['role_slug']);
        $this->assertSame('root', $audits[0]['username']);
        $this->assertSame($this->super->id, $audits[0]['user_id']);
        $this->assertSame(['CakeDC/Users.Users::edit'], $audits[0]['added']);
        $this->assertSame(['CakeDC/Users.Users::view', 'Reports::users'], $audits[0]['removed']);
    }

    public function testLockedRowsAreNotRevokedWhenTheBrowserDoesNotSendThem(): void
    {
        $sync = PermissionsSynchronizer::fromConfig();
        $before = $sync->grants()['admin'];
        // The browser only sends the editable checkboxes (locked rows have none)
        $editable = array_values(array_filter($before, fn(string $k): bool => !str_starts_with($k, 'Dashboard::') && !str_contains($k, '::login')));

        $this->post('/permissions', ['grants' => ['admin' => $editable, 'editor' => $sync->grants()['editor']]]);

        $this->assertSame($before, $sync->grants()['admin']);
        $this->assertSame([], $this->audits(), 'Nothing really changed');
    }

    public function testRoleLifecycleIsAudited(): void
    {
        $this->post('/roles/add', ['slug' => 'support', 'name' => 'Support']);
        $id = $this->fetchTable('Roles')->findBySlug('support')->firstOrFail()->id;
        $this->post('/roles/edit/' . $id, ['slug' => 'support', 'name' => 'Customer support']);
        $this->post('/roles/delete/' . $id);

        $events = array_map(fn(array $a): string => $a['event'] . ':' . $a['role_slug'], $this->audits());
        $this->assertSame(['role_created:support', 'role_updated:support', 'role_deleted:support'], $events);
        $this->assertSame(['name' => ['Support', 'Customer support']], $this->audits()[1]['details']);
    }

    public function testFailedRoleDeletionIsNotAudited(): void
    {
        $id = $this->fetchTable('Roles')->findBySlug('superadmin')->firstOrFail()->id;

        $this->post('/roles/delete/' . $id);

        $this->assertSame([], $this->audits());
    }

    public function testSyncIsAuditedWithTheConsoleAsActor(): void
    {
        $this->fetchTable('Permissions')->deleteAll(['controller' => 'Reports']);

        $this->exec('permissions sync');

        $audit = $this->audits()[0];
        $this->assertSame('sync', $audit['event']);
        $this->assertSame('console', $audit['username']);
        $this->assertNull($audit['user_id']);
        $this->assertSame(1, $audit['details']['added']);
    }

    public function testAuditScreenIsSuperadminOnly(): void
    {
        PermissionsSynchronizer::fromConfig()->setGrants('editor', []);
        $this->post('/permissions', ['grants' => ['editor' => ['Dashboard::index']]]);

        $this->get('/permissions/audit');
        $this->assertResponseOk();
        $this->assertResponseContains('root');
        $this->assertResponseContains('Dashboard::index');

        $this->session(['Auth' => $this->createUser(['role' => 'admin'])]);
        $this->get('/permissions/audit');
        $this->assertRedirect();
    }
}
