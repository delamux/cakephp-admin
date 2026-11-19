<?php
declare(strict_types=1);

namespace App\UseCase\Permissions;

use App\Model\Entity\Permission;
use App\Rbac\ActionDiscovery;
use App\Rbac\PermissionsSynchronizer;
use App\Rbac\RuleCatalog;
use App\Service\UserRoleManager;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use CakeDC\Auth\Rbac\Permissions\ConfigProvider;

/**
 * Read model of the permissions screen: one tab per editable role, actions grouped per controller,
 * each with a select (DENY, ALLOW or a rule of App\Rbac\RuleCatalog that can decide it).
 *
 * An action is a system one (read only, behind "Show system actions") when a rule of
 * config/permissions.php already decides it (`public` = bypassAuth, `all_roles` = role `*`) or it
 * is an access-control screen (`superadmin`, see PermissionsSynchronizer::isSuperadminOnly()).
 */
class PermissionMatrix
{
    public const LOCKED_PUBLIC = 'public';
    public const LOCKED_ALL_ROLES = 'all_roles';
    public const LOCKED_SUPERADMIN = 'superadmin';

    /** Select value: not allowed (no grant). */
    public const DENY = 'deny';
    /** Select value: allowed without a rule. */
    public const ALLOW = 'allow';

    /**
     * @var array<array<string, mixed>>
     */
    private array $systemRules;

    private Table $permissions;

    private RuleCatalog $catalog;

    /**
     * @var array<string, list<array{\App\Model\Entity\Permission, string|null}>>|null
     */
    private ?array $actions = null;

    /**
     * @param \App\Rbac\PermissionsSynchronizer $sync Grants.
     * @param \App\Service\UserRoleManager $roles Roles.
     * @param array<array<string, mixed>>|null $systemRules System rules (default config/permissions.php).
     * @param \App\Rbac\RuleCatalog|null $catalog Rules (default App\Rbac\RuleCatalog::fromConfig()).
     */
    public function __construct(
        private PermissionsSynchronizer $sync,
        private UserRoleManager $roles,
        ?array $systemRules = null,
        ?RuleCatalog $catalog = null,
    ) {
        $this->catalog = $catalog ?? RuleCatalog::fromConfig();
        $this->systemRules = $systemRules ?? (new ConfigProvider())->getPermissions();
        $this->permissions = TableRegistry::getTableLocator()->get('Permissions');
    }

    /**
     * Roles shown as columns (slug => name): all but superadmin, who can always do everything.
     *
     * @return array<string, string>
     */
    public function editableRoles(): array
    {
        return array_diff_key($this->roles->roles(), [UserRoleManager::SUPERADMIN => true]);
    }

    /**
     * Editable actions per controller, each with the rules that can decide it (name => label).
     *
     * @return array<string, list<array{key: string, action: string, rules: array<string, string>}>>
     */
    public function groups(): array
    {
        $groups = [];
        foreach ($this->actions() as $group => $actions) {
            foreach ($actions as [$permission, $locked]) {
                if ($locked === null) {
                    $groups[$group][] = [
                        'key' => $permission->key(),
                        'action' => $permission->action,
                        'rules' => $this->catalog->applicable($permission->toArray()),
                    ];
                }
            }
        }

        return $groups;
    }

    /**
     * Actions decided by config/permissions.php or reserved to superadmin (read only), per controller.
     *
     * @return array<string, list<array{key: string, action: string, locked: string}>>
     */
    public function systemGroups(): array
    {
        $groups = [];
        foreach ($this->actions() as $group => $actions) {
            foreach ($actions as [$permission, $locked]) {
                if ($locked !== null) {
                    $groups[$group][] = ['key' => $permission->key(), 'action' => $permission->action, 'locked' => $locked];
                }
            }
        }

        return $groups;
    }

    /**
     * Keys of the locked rows (not in the form, so their grants are kept as they are).
     *
     * @return array<string, true>
     */
    public function lockedKeys(): array
    {
        $locked = [];
        foreach ($this->systemGroups() as $actions) {
            foreach ($actions as $action) {
                $locked[$action['key']] = true;
            }
        }

        return $locked;
    }

    /**
     * Select value of every editable action for a role: DENY, ALLOW or a rule name.
     *
     * @param string $roleSlug Role.
     * @return array<string, string>
     */
    public function values(string $roleSlug): array
    {
        $granted = $this->sync->rules()[$roleSlug] ?? [];
        $values = [];
        foreach ($this->groups() as $actions) {
            foreach ($actions as $action) {
                $key = $action['key'];
                $values[$key] = array_key_exists($key, $granted) ? ($granted[$key] ?? self::ALLOW) : self::DENY;
            }
        }

        return $values;
    }

    /**
     * @return int Actions no longer present in the code.
     */
    public function orphans(): int
    {
        return $this->permissions->find()->where(['orphan' => true])->count();
    }

    /**
     * Non orphan actions per controller with their lock reason (null = editable).
     *
     * @return array<string, list<array{\App\Model\Entity\Permission, string|null}>>
     */
    private function actions(): array
    {
        if ($this->actions !== null) {
            return $this->actions;
        }
        $permissions = $this->permissions->find()
            ->where(['orphan' => false])
            ->orderBy(['plugin' => 'ASC', 'prefix' => 'ASC', 'controller' => 'ASC', 'action' => 'ASC'])
            ->all();
        $groups = [];
        foreach ($permissions as $permission) {
            /** @var \App\Model\Entity\Permission $permission */
            $group = rtrim(ActionDiscovery::key([
                'plugin' => $permission->plugin,
                'prefix' => $permission->prefix,
                'controller' => $permission->controller,
                'action' => '',
            ]), ':');
            $groups[$group][] = [$permission, $this->lockReason($permission)];
        }

        return $this->actions = $groups;
    }

    /**
     * @param \App\Model\Entity\Permission $permission Action.
     * @return string|null
     */
    private function lockReason(Permission $permission): ?string
    {
        if ($this->matchesSystemRule($permission, fn(array $r): bool => ($r['bypassAuth'] ?? false) === true)) {
            return self::LOCKED_PUBLIC;
        }
        if ($this->matchesSystemRule($permission, fn(array $r): bool => ($r['role'] ?? null) === '*')) {
            return self::LOCKED_ALL_ROLES;
        }

        return $this->sync->isSuperadminOnly($permission->key()) ? self::LOCKED_SUPERADMIN : null;
    }

    /**
     * @param \App\Model\Entity\Permission $permission Action.
     * @param callable(array<string, mixed>): bool $filter Rule filter.
     * @return bool
     */
    private function matchesSystemRule(Permission $permission, callable $filter): bool
    {
        $matches = fn(mixed $rule, string $value): bool => $rule === '*' || in_array($value, (array)$rule, true);
        foreach ($this->systemRules as $rule) {
            // Missing `plugin` key = any plugin; `plugin => null` = the app ('')
            $plugin = array_key_exists('plugin', $rule) ? ($rule['plugin'] ?? '') : '*';
            if (
                $filter($rule)
                && $matches($plugin, (string)$permission->plugin)
                && $matches($rule['controller'] ?? '*', (string)$permission->controller)
                && $matches($rule['action'] ?? '*', (string)$permission->action)
            ) {
                return true;
            }
        }

        return false;
    }
}
