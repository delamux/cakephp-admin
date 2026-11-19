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

    public function testMatrixSplitsEditableActionsFromSystemRules(): void
    {
        $editable = array_merge(...array_values($this->matrix()->groups()));
        $system = [];
        foreach ($this->matrix()->systemGroups() as $actions) {
            foreach ($actions as $action) {
                $system[$action['key']] = $action['locked'];
            }
        }

        $this->assertContains('CakeDC/Users.Users::edit', array_column($editable, 'key'));
        $this->assertContains('Dashboard::index', array_column($editable, 'key'), 'Moved to the database: editable');
        $this->assertNotContains('CakeDC/Users.Users::login', array_column($editable, 'key'));
        $this->assertSame(PermissionMatrix::LOCKED_PUBLIC, $system['CakeDC/Users.Users::login']);
        $this->assertSame(PermissionMatrix::LOCKED_ALL_ROLES, $system['CakeDC/Users.Users::profile']);
        $this->assertSame(PermissionMatrix::LOCKED_SUPERADMIN, $system['Permissions::index']);
        $this->assertArrayNotHasKey('superadmin', $this->matrix()->editableRoles());
    }

    public function testMatrixOffersOnlyTheRulesThatCanDecideEachAction(): void
    {
        $byKey = array_column(array_merge(...array_values($this->matrix()->groups())), 'rules', 'key');

        $this->assertSame(['owner'], array_keys($byKey['CakeDC/Users.Users::edit']));
        $this->assertSame([], $byKey['Dashboard::index']);
    }

    public function testMatrixValuesPerRole(): void
    {
        $values = $this->matrix()->values('admin');

        $this->assertSame('owner', $values['CakeDC/Users.Users::resetOneTimePasswordAuthenticator']);
        $this->assertSame(PermissionMatrix::ALLOW, $values['CakeDC/Users.Users::edit']);
        $this->assertSame(PermissionMatrix::ALLOW, $values['Reports::users']);
        $this->assertSame(PermissionMatrix::DENY, $this->matrix()->values('user')['Reports::users']);
    }

    public function testUpdateSavesOneRoleWithRulesKeepsSystemRowsAndAudits(): void
    {
        $sync = $this->synchronizer();
        $before = $sync->rules();
        $update = new UpdateRolePermissions($this->matrix(), $sync, $this->auditLog());
        $values = $this->matrix()->values('editor');

        $changes = $update->execute('editor', [
            'CakeDC/Users.Users::edit' => 'owner',
            'CakeDC/Users.Users::view' => PermissionMatrix::DENY,
            'Permissions::index' => PermissionMatrix::ALLOW,
            'CakeDC/Users.Users::login' => PermissionMatrix::DENY,
        ] + $values, $this->root());

        $after = $sync->rules();
        $this->assertSame('owner', $after['editor']['CakeDC/Users.Users::edit']);
        $this->assertArrayNotHasKey('CakeDC/Users.Users::view', $after['editor']);
        $this->assertArrayNotHasKey('Permissions::index', $after['editor'], 'Superadmin-only key ignored');
        $this->assertSame($before['admin'], $after['admin'], 'Other roles untouched');
        $this->assertSame(['CakeDC/Users.Users::edit [owner]'], $changes['added']);
        $this->assertSame(['CakeDC/Users.Users::view'], $changes['removed']);
        $audits = $this->audits();
        $this->assertCount(1, $audits);
        $this->assertSame(['grants_changed', 'editor', 'root'], [$audits[0]['event'], $audits[0]['role_slug'], $audits[0]['username']]);
    }

    public function testUpdateKeepsSystemGrantsTheFormDoesNotSend(): void
    {
        $sync = $this->synchronizer();
        $before = $sync->rules()['admin'];
        $this->assertArrayHasKey('CakeDC/Users.Users::login', $before, 'Granted by the `*` default, decided by the system rule');

        $changes = (new UpdateRolePermissions($this->matrix(), $sync, $this->auditLog()))
            ->execute('admin', $this->matrix()->values('admin'), $this->root());

        $this->assertSame($before, $sync->rules()['admin']);
        $this->assertSame(['added' => [], 'removed' => []], $changes);
        $this->assertSame([], $this->audits());
    }

    public function testUpdateRejectsSuperadminAndUnknownRoles(): void
    {
        $update = new UpdateRolePermissions($this->matrix(), $this->synchronizer(), $this->auditLog());

        $this->expectException(InvalidArgumentException::class);
        $update->execute('superadmin', ['Dashboard::index' => PermissionMatrix::ALLOW], $this->root());
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
