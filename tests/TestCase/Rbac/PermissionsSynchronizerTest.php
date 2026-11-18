<?php
declare(strict_types=1);

namespace App\Test\TestCase\Rbac;

use App\Rbac\PermissionsSynchronizer;
use Cake\TestSuite\TestCase;

/**
 * Catalogue synchronisation and default grants.
 */
class PermissionsSynchronizerTest extends TestCase
{
    protected array $fixtures = ['app.Roles', 'app.Permissions', 'app.RolesPermissions', 'app.Users'];

    private const DEFAULTS = [
        'admin' => ['*', '!Roles::*'],
        'editor' => ['Reports::users', 'CakeDC/Users.Users::index'],
        'user' => ['Dashboard::index'],
    ];

    /**
     * @param list<string> $keys `Plugin.Controller::action`
     * @return list<array{plugin: string, prefix: string, controller: string, action: string}>
     */
    private function actions(array $keys): array
    {
        return array_map(function (string $key): array {
            preg_match('/^(?:(.+)\.)?(?:(.+)\/)?([^\/.]+)::(.+)$/', $key, $m);

            return ['plugin' => $m[1], 'prefix' => $m[2], 'controller' => $m[3], 'action' => $m[4]];
        }, $keys);
    }

    /**
     * @return array<string, list<string>> role => granted keys
     */
    private function grants(PermissionsSynchronizer $sync): array
    {
        return $sync->grants();
    }

    public function testNewActionsAreAddedOnlyOnce(): void
    {
        $sync = new PermissionsSynchronizer(self::DEFAULTS);

        $first = $sync->sync($this->actions(['Dashboard::index', 'Reports::users']));
        $second = $sync->sync($this->actions(['Dashboard::index', 'Reports::users']));

        $this->assertSame(2, $first['added']);
        $this->assertSame(0, $second['added']);
        $this->assertSame(2, $this->fetchTable('Permissions')->find()->count());
    }

    public function testMissingActionsBecomeOrphansAndComeBack(): void
    {
        $sync = new PermissionsSynchronizer(self::DEFAULTS);
        $sync->sync($this->actions(['Dashboard::index', 'Reports::users']));

        $gone = $sync->sync($this->actions(['Dashboard::index']));
        $this->assertSame(1, $gone['orphaned']);
        $this->assertTrue($this->fetchTable('Permissions')->find()->where(['controller' => 'Reports'])->firstOrFail()->get('orphan'));

        $back = $sync->sync($this->actions(['Dashboard::index', 'Reports::users']));
        $this->assertSame(1, $back['restored']);
        $this->assertFalse($this->fetchTable('Permissions')->find()->where(['controller' => 'Reports'])->firstOrFail()->get('orphan'));
    }

    public function testDefaultsWithPatternsAndExclusionsAreGrantedToNewActions(): void
    {
        $sync = new PermissionsSynchronizer(self::DEFAULTS);

        $sync->sync($this->actions(['Dashboard::index', 'Reports::users', 'Roles::index', 'CakeDC/Users.Users::index']));
        $grants = $this->grants($sync);

        $this->assertSame(['CakeDC/Users.Users::index', 'Dashboard::index', 'Reports::users'], $grants['admin'], '`*` minus `!Roles::*`');
        $this->assertSame(['CakeDC/Users.Users::index', 'Reports::users'], $grants['editor']);
        $this->assertSame(['Dashboard::index'], $grants['user']);
        $this->assertArrayNotHasKey('superadmin', $grants, 'Superadmin needs no grants: it bypasses RBAC');
    }

    public function testExistingGrantsAreNotTouchedByALaterSync(): void
    {
        $sync = new PermissionsSynchronizer(self::DEFAULTS);
        $sync->sync($this->actions(['Dashboard::index']));
        // An admin removed it from the UI
        $sync->setGrants('user', []);

        $sync->sync($this->actions(['Dashboard::index', 'Reports::users']));

        $this->assertSame([], $this->grants($sync)['user'] ?? []);
    }

    public function testResetReappliesTheDefaultsToEverything(): void
    {
        $sync = new PermissionsSynchronizer(self::DEFAULTS);
        $sync->sync($this->actions(['Dashboard::index']));
        $sync->setGrants('user', []);
        $sync->setGrants('editor', ['Dashboard::index']);

        $sync->sync($this->actions(['Dashboard::index']), reset: true);

        $this->assertSame(['Dashboard::index'], $this->grants($sync)['user']);
        $this->assertArrayNotHasKey('editor', $this->grants($sync));
    }

    public function testSetGrantsIgnoresUnknownActionsAndOrphans(): void
    {
        $sync = new PermissionsSynchronizer(self::DEFAULTS);
        $sync->sync($this->actions(['Dashboard::index', 'Reports::users']));
        $sync->sync($this->actions(['Dashboard::index']));

        $sync->setGrants('editor', ['Dashboard::index', 'Reports::users', 'Nope::nothing']);

        $this->assertSame(['Dashboard::index'], $this->grants($sync)['editor']);
    }
}
