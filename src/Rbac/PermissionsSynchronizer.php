<?php
declare(strict_types=1);

namespace App\Rbac;

use App\Model\Table\PermissionsTable;
use App\Model\Table\RolesTable;
use Cake\Core\Configure;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;

/**
 * Keeps the permissions catalogue in sync with the code and manages the allow-list.
 *
 * - sync(): adds new actions, flags missing ones as orphans (and restores them when they come
 *   back), and grants the defaults (`App.permissions.defaults`) to NEW actions only, so changes
 *   made from the UI are never undone. `reset: true` re-applies the defaults to everything.
 * - Defaults: `role => [patterns]`, `*` wildcards on `Plugin.Prefix/Controller::action`, and
 *   `!pattern` exclusions. Superadmin needs no grants (it bypasses the RBAC rules).
 */
class PermissionsSynchronizer
{
    /**
     * Never grantable to a role from the matrix: access control and resetting other users' 2FA.
     */
    public const SUPERADMIN_ONLY = ['Roles::*', 'Permissions::*', 'TwoFactor::reset'];

    private PermissionsTable $Permissions;

    private RolesTable $Roles;

    private Table $RolesPermissions;

    /**
     * @param array<string, list<string>> $defaults Default grants per role slug.
     * @param list<string> $superadminOnly Patterns never grantable to a role (access-control screens).
     */
    public function __construct(
        private array $defaults,
        private array $superadminOnly = self::SUPERADMIN_ONLY,
    ) {
        $locator = TableRegistry::getTableLocator();
        /** @var \App\Model\Table\PermissionsTable $permissions */
        $permissions = $locator->get('Permissions');
        /** @var \App\Model\Table\RolesTable $roles */
        $roles = $locator->get('Roles');
        $this->Permissions = $permissions;
        $this->Roles = $roles;
        $this->RolesPermissions = $locator->get('RolesPermissions');
    }

    /**
     * @return self
     */
    public static function fromConfig(): self
    {
        /** @var array<string, list<string>> $defaults */
        $defaults = (array)Configure::read('App.permissions.defaults', []);
        /** @var list<string> $superadminOnly */
        $superadminOnly = (array)Configure::read('App.permissions.superadminOnly', self::SUPERADMIN_ONLY);

        return new self($defaults, $superadminOnly);
    }

    /**
     * @param list<array{plugin: string, prefix: string, controller: string, action: string}> $actions Discovered actions.
     * @param bool $reset Re-apply the default grants to every action (drops the current grants).
     * @return array{added: int, orphaned: int, restored: int}
     */
    public function sync(array $actions, bool $reset = false): array
    {
        $existing = [];
        foreach ($this->Permissions->find()->all() as $permission) {
            /** @var \App\Model\Entity\Permission $permission */
            $existing[$permission->key()] = $permission;
        }

        $result = ['added' => 0, 'orphaned' => 0, 'restored' => 0];
        $new = [];
        $seen = [];
        foreach ($actions as $action) {
            $key = ActionDiscovery::key($action);
            $seen[$key] = true;
            if (!isset($existing[$key])) {
                $permission = $this->Permissions->saveOrFail($this->Permissions->newEntity($action));
                $new[] = $permission;
                $result['added']++;
            } elseif ($existing[$key]->orphan) {
                $existing[$key]->orphan = false;
                $this->Permissions->saveOrFail($existing[$key]);
                $result['restored']++;
            }
        }
        foreach ($existing as $key => $permission) {
            if (!isset($seen[$key]) && !$permission->orphan) {
                $permission->orphan = true;
                $this->Permissions->saveOrFail($permission);
                $result['orphaned']++;
            }
        }

        if ($reset) {
            $this->RolesPermissions->deleteAll([]);
            $new = $this->Permissions->find()->where(['orphan' => false])->all()->toList();
        }
        $this->grantDefaults($new);
        PermissionsCache::clear();

        return $result;
    }

    /**
     * Granted (non orphan) action keys per role slug, roles without grants omitted.
     *
     * @return array<string, list<string>>
     */
    public function grants(): array
    {
        $rows = $this->RolesPermissions->find()
            ->contain(['Roles', 'Permissions'])
            ->where(['Permissions.orphan' => false])
            ->all();
        $grants = [];
        foreach ($rows as $row) {
            /** @var \App\Model\Entity\Permission $permission */
            $permission = $row->get('permission');
            $grants[(string)$row->get('role')->get('slug')][] = $permission->key();
        }
        foreach ($grants as &$keys) {
            sort($keys, SORT_STRING);
        }

        return $grants;
    }

    /**
     * Replaces the grants of a role (unknown or orphan keys are ignored).
     *
     * @param string $roleSlug Role.
     * @param list<string> $keys Action keys.
     * @return array{added: list<string>, removed: list<string>} What changed.
     */
    public function setGrants(string $roleSlug, array $keys): array
    {
        $before = $this->grants()[$roleSlug] ?? [];
        $role = $this->Roles->find()->where(['slug' => $roleSlug])->firstOrFail();
        $this->RolesPermissions->deleteAll(['role_id' => $role->get('id')]);

        $wanted = array_flip($keys);
        foreach ($this->Permissions->find()->where(['orphan' => false])->all() as $permission) {
            /** @var \App\Model\Entity\Permission $permission */
            if (isset($wanted[$permission->key()]) && !$this->isSuperadminOnly($permission->key())) {
                $this->grant((int)$role->get('id'), $permission->id);
            }
        }
        PermissionsCache::clear();

        $after = $this->grants()[$roleSlug] ?? [];

        return [
            'added' => array_values(array_diff($after, $before)),
            'removed' => array_values(array_diff($before, $after)),
        ];
    }

    /**
     * @param array<\Cake\Datasource\EntityInterface> $permissions Permissions to grant defaults for.
     * @return void
     */
    private function grantDefaults(array $permissions): void
    {
        if ($permissions === []) {
            return;
        }
        $roles = $this->Roles->find()->where(['slug IN' => array_keys($this->defaults) ?: ['']])->all();
        foreach ($roles as $role) {
            foreach ($permissions as $permission) {
                $key = ActionDiscovery::key($permission->toArray());
                $patterns = $this->defaults[(string)$role->get('slug')];
                if (!$this->isSuperadminOnly($key) && self::matches($key, $patterns)) {
                    $this->grant((int)$role->get('id'), (int)$permission->get('id'));
                }
            }
        }
    }

    /**
     * Access-control screens are never granted to a role (no self-escalation): superadmin only.
     *
     * @param string $key Action key.
     * @return bool
     */
    public function isSuperadminOnly(string $key): bool
    {
        return self::matches($key, $this->superadminOnly);
    }

    /**
     * Does `$key` match the patterns (`*` wildcards, `!` exclusions)?
     *
     * @param string $key Action key.
     * @param list<string> $patterns Patterns.
     * @return bool
     */
    public static function matches(string $key, array $patterns): bool
    {
        $included = false;
        foreach ($patterns as $pattern) {
            if (str_starts_with($pattern, '!')) {
                if (fnmatch(substr($pattern, 1), $key, FNM_NOESCAPE)) {
                    return false;
                }
            } elseif (fnmatch($pattern, $key, FNM_NOESCAPE)) {
                $included = true;
            }
        }

        return $included;
    }

    /**
     * @param int $roleId Role.
     * @param int $permissionId Permission.
     * @return void
     */
    private function grant(int $roleId, int $permissionId): void
    {
        if (!$this->RolesPermissions->exists(['role_id' => $roleId, 'permission_id' => $permissionId])) {
            $this->RolesPermissions->saveOrFail(
                $this->RolesPermissions->newEntity(['role_id' => $roleId, 'permission_id' => $permissionId]),
            );
        }
    }
}
