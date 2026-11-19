<?php
declare(strict_types=1);

namespace App\Rbac;

use App\Model\Table\PermissionsTable;
use App\Model\Table\RolesTable;
use App\Service\UserRoleManager;
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
    public const SUPERADMIN_ONLY = ['Roles::*', 'Permissions::*', 'TwoFactor::reset', 'Queue::*'];

    private PermissionsTable $Permissions;

    private RolesTable $Roles;

    private Table $RolesPermissions;

    private RuleCatalog $catalog;

    /**
     * @param array<string, array<int|string, string>> $defaults Default grants per role slug (`*` = every
     *   role): patterns (`!` excludes) and `pattern => rule` (granted with that rule).
     * @param list<string> $superadminOnly Patterns never grantable to a role (access-control screens).
     * @param \App\Rbac\RuleCatalog|null $catalog Rules (default App\Rbac\RuleCatalog::fromConfig()).
     */
    public function __construct(
        private array $defaults,
        private array $superadminOnly = self::SUPERADMIN_ONLY,
        ?RuleCatalog $catalog = null,
    ) {
        $this->catalog = $catalog ?? RuleCatalog::fromConfig();
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
        /** @var array<string, array<int|string, string>> $defaults */
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
     * Granted (non orphan) actions per role slug with their rule (null = allowed without a rule).
     *
     * @return array<string, array<string, string|null>> role slug => [action key => rule]
     */
    public function rules(): array
    {
        $rows = $this->RolesPermissions->find()
            ->contain(['Roles', 'Permissions'])
            ->where(['Permissions.orphan' => false])
            ->all();
        $rules = [];
        foreach ($rows as $row) {
            /** @var \App\Model\Entity\Permission $permission */
            $permission = $row->get('permission');
            $rule = $row->get('rule');
            $slug = (string)$row->get('role')->get('slug');
            $rules[$slug][$permission->key()] = is_string($rule) && $rule !== '' ? $rule : null;
        }
        foreach ($rules as &$keys) {
            ksort($keys, SORT_STRING);
        }

        return $rules;
    }

    /**
     * Replaces the grants of a role. Unknown or orphan keys, superadmin-only actions and rules that
     * cannot decide the action (App\Rbac\RuleCatalog::isValid()) grant nothing.
     *
     * @param string $roleSlug Role.
     * @param array<int|string, string|null> $grants `key => rule|null`, or a list of keys (no rule).
     * @return array{added: list<string>, removed: list<string>} What changed (`key` or `key [rule]`).
     */
    public function setGrants(string $roleSlug, array $grants): array
    {
        $wanted = [];
        foreach ($grants as $key => $rule) {
            if (is_int($key)) {
                $wanted[(string)$rule] = null;
            } else {
                $wanted[$key] = is_string($rule) && $rule !== '' ? $rule : null;
            }
        }

        $before = self::labels($this->rules()[$roleSlug] ?? []);
        $role = $this->Roles->find()->where(['slug' => $roleSlug])->firstOrFail();
        $this->RolesPermissions->deleteAll(['role_id' => $role->get('id')]);

        foreach ($this->Permissions->find()->where(['orphan' => false])->all() as $permission) {
            /** @var \App\Model\Entity\Permission $permission */
            $key = $permission->key();
            if (!array_key_exists($key, $wanted) || $this->isSuperadminOnly($key)) {
                continue;
            }
            $rule = $wanted[$key];
            if ($rule === null || $this->catalog->isValid($rule, $permission->toArray())) {
                $this->grant((int)$role->get('id'), $permission->id, $rule);
            }
        }
        PermissionsCache::clear();

        $after = self::labels($this->rules()[$roleSlug] ?? []);

        return [
            'added' => array_values(array_diff($after, $before)),
            'removed' => array_values(array_diff($before, $after)),
        ];
    }

    /**
     * Grants the defaults (`*` + its own patterns) of every current action to a role (new roles).
     *
     * @param string $roleSlug Role.
     * @return void
     */
    public function grantDefaultsTo(string $roleSlug): void
    {
        $permissions = $this->Permissions->find()->where(['orphan' => false])->all()->toList();
        $this->grantDefaults($permissions, $roleSlug);
        PermissionsCache::clear();
    }

    /**
     * @param array<string, string|null> $rules key => rule.
     * @return list<string> `key` or `key [rule]`.
     */
    private static function labels(array $rules): array
    {
        $labels = [];
        foreach ($rules as $key => $rule) {
            $labels[] = $rule === null ? $key : $key . ' [' . $rule . ']';
        }

        return $labels;
    }

    /**
     * @param array<\Cake\Datasource\EntityInterface> $permissions Permissions to grant defaults for.
     * @param string|null $onlyRole Only this role (default: every role with defaults, `*` = all but superadmin).
     * @return void
     */
    private function grantDefaults(array $permissions, ?string $onlyRole = null): void
    {
        if ($permissions === []) {
            return;
        }
        $query = $this->Roles->find()->where(['slug !=' => UserRoleManager::SUPERADMIN]);
        if ($onlyRole !== null) {
            $query->where(['slug' => $onlyRole]);
        } elseif (!isset($this->defaults['*'])) {
            $query->where(['slug IN' => array_keys($this->defaults) ?: ['']]);
        }
        foreach ($query->all() as $role) {
            $patterns = array_merge($this->defaults['*'] ?? [], $this->defaults[(string)$role->get('slug')] ?? []);
            foreach ($permissions as $permission) {
                $action = $permission->toArray();
                $key = ActionDiscovery::key($action);
                if ($this->isSuperadminOnly($key)) {
                    continue;
                }
                [$granted, $rule] = self::decide($key, $patterns);
                if ($granted && ($rule === null || $this->catalog->isValid($rule, $action))) {
                    $this->grant((int)$role->get('id'), (int)$permission->get('id'), $rule);
                }
            }
        }
    }

    /**
     * Default for one action: plain patterns grant without a rule; `pattern => rule` grants with it
     * (wins over a plain pattern, the last matching one wins); `!pattern` always excludes.
     *
     * @param string $key Action key.
     * @param array<int|string, string> $patterns Patterns.
     * @return array{bool, string|null} [granted, rule]
     */
    private static function decide(string $key, array $patterns): array
    {
        $plain = array_values(
            array_filter($patterns, fn($pattern, $index): bool => is_int($index), ARRAY_FILTER_USE_BOTH),
        );
        $rule = null;
        foreach ($patterns as $pattern => $name) {
            if (is_string($pattern) && fnmatch($pattern, $key, FNM_NOESCAPE)) {
                $rule = $name;
            }
        }
        $excluded = array_filter($plain, fn(string $pattern): bool => str_starts_with($pattern, '!'));
        if ($excluded !== [] && !self::matches($key, ['*', ...$excluded])) {
            return [false, null];
        }

        return [$rule !== null || self::matches($key, $plain), $rule];
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
     * @param string|null $rule Rule name (null = allowed).
     * @return void
     */
    private function grant(int $roleId, int $permissionId, ?string $rule = null): void
    {
        if (!$this->RolesPermissions->exists(['role_id' => $roleId, 'permission_id' => $permissionId])) {
            $this->RolesPermissions->saveOrFail(
                $this->RolesPermissions->newEntity(
                    ['role_id' => $roleId, 'permission_id' => $permissionId, 'rule' => $rule],
                    ['accessibleFields' => ['*' => true]],
                ),
            );
        }
    }
}
