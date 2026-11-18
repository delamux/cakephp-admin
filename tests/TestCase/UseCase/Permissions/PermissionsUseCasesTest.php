<?php
declare(strict_types=1);

namespace App\Test\TestCase\UseCase\Permissions;

use App\Rbac\ActionDiscovery;
use App\Test\TestCase\PermissionsTestTrait;
use App\Test\TestCase\UseCase\UseCaseTestTrait;
use App\UseCase\Actor;
use App\UseCase\Permissions\PermissionMatrix;
use App\UseCase\Permissions\PurgePermissionHistory;
use App\UseCase\Permissions\SyncPermissions;
use App\UseCase\Permissions\UpdateRolePermissions;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;

/**
 * Permissions use cases: matrix (read model), saving it, syncing and purging the history.
 */
class PermissionsUseCasesTest extends TestCase
{
    use PermissionsTestTrait;
    use UseCaseTestTrait;

    protected array $fixtures = ['app.Users', 'app.Roles', 'app.Permissions', 'app.RolesPermissions', 'app.PermissionAudits'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
    }

    public function testMatrixFlagsLockedRowsWithAReasonCode(): void
    {
        $locked = [];
        foreach ($this->matrix()->groups() as $actions) {
            foreach ($actions as $action) {
                $locked[$action['key']] = $action['locked'];
            }
        }

        $this->assertSame(PermissionMatrix::LOCKED_PUBLIC, $locked['CakeDC/Users.Users::login']);
        $this->assertSame(PermissionMatrix::LOCKED_ALL_ROLES, $locked['Dashboard::index']);
        $this->assertSame(PermissionMatrix::LOCKED_SUPERADMIN, $locked['Permissions::index']);
        $this->assertNull($locked['CakeDC/Users.Users::edit'], 'Editable');
        $this->assertArrayNotHasKey('superadmin', $this->matrix()->editableRoles());
    }

    public function testMatrixGroupsActionsPerController(): void
    {
        $groups = $this->matrix()->groups();

        $this->assertArrayHasKey('CakeDC/Users.Users', $groups);
        $this->assertContains('index', array_column($groups['Roles'], 'action'));
    }

    public function testUpdateKeepsLockedGrantsIgnoresSuperadminAndAuditsOnlyRealChanges(): void
    {
        $sync = $this->synchronizer();
        $before = $sync->grants();
        $update = new UpdateRolePermissions($this->matrix(), $sync, $this->auditLog());

        $changes = $update->execute([
            'admin' => array_values(array_filter($before['admin'], fn(string $k): bool => $k !== 'Dashboard::index')),
            'editor' => ['CakeDC/Users.Users::index', 'Permissions::index'],
            'superadmin' => ['Dashboard::index'],
        ], $this->root());

        $after = $sync->grants();
        $this->assertSame($before['admin'], $after['admin'], 'Locked Dashboard::index kept, nothing else changed');
        $this->assertSame(['CakeDC/Users.Users::index'], $after['editor'], 'Superadmin-only key ignored');
        $this->assertArrayNotHasKey('superadmin', $after);
        $this->assertSame(['editor'], array_keys($changes));
        $this->assertSame(['CakeDC/Users.Users::view', 'Reports::users'], $changes['editor']['removed']);
        $audits = $this->audits();
        $this->assertCount(1, $audits);
        $this->assertSame(['grants_changed', 'editor', 'root'], [$audits[0]['event'], $audits[0]['role_slug'], $audits[0]['username']]);
    }

    public function testSyncReturnsCountsAndAuditsTheActor(): void
    {
        $this->fetchTable('Permissions')->deleteAll(['controller' => 'Reports']);
        $discovery = new ActionDiscovery([
            ['plugin' => '', 'namespace' => 'App\\Controller', 'path' => APP . 'Controller' . DS],
        ]);

        $result = (new SyncPermissions($discovery, $this->synchronizer(), $this->auditLog()))->execute(Actor::console());

        $this->assertSame(1, $result['added']);
        $this->assertGreaterThan(1, $result['found']);
        $audit = $this->audits()[0];
        $this->assertSame(['sync', 'console', false], [$audit['event'], $audit['username'], $audit['details']['reset']]);
    }

    public function testPurgeEnforcesTheMinimumRetentionAndAuditsOnlyWhenSomethingIsDeleted(): void
    {
        $audits = $this->fetchTable('PermissionAudits');
        $audits->saveOrFail($audits->newEntity(
            ['username' => 'root', 'event' => 'role_created', 'created' => DateTime::now()->subDays(100)],
            ['accessibleFields' => ['*' => true]],
        ));
        $purge = new PurgePermissionHistory($this->auditLog());

        $this->assertSame(1, $purge->count(60));
        $this->assertSame(0, $purge->execute(365, Actor::console()));
        $this->assertSame(['role_created'], array_column($this->audits(), 'event'), 'Nothing deleted, no trace');
        $this->assertSame(1, $purge->execute(60, Actor::console()));
        $this->assertSame(['audit_purged'], array_column($this->audits(), 'event'));

        $this->expectException(InvalidArgumentException::class);
        $purge->execute(PurgePermissionHistory::MIN_RETENTION_DAYS - 1, Actor::console());
    }
}
