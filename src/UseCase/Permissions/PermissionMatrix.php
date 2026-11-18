<?php
declare(strict_types=1);

namespace App\UseCase\Permissions;

use App\Model\Entity\Permission;
use App\Rbac\ActionDiscovery;
use App\Rbac\PermissionsSynchronizer;
use App\Service\UserRoleManager;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use CakeDC\Auth\Rbac\Permissions\ConfigProvider;

/**
 * Read model of the permissions screen: actions grouped per controller × editable roles.
 *
 * A row is locked (not editable) when a system rule of config/permissions.php already decides
 * it (`public` = bypassAuth, `all_roles` = role `*`) or it is an access-control screen
 * (`superadmin`, see PermissionsSynchronizer::isSuperadminOnly()).
 */
class PermissionMatrix
{
    public const LOCKED_PUBLIC = 'public';
    public const LOCKED_ALL_ROLES = 'all_roles';
    public const LOCKED_SUPERADMIN = 'superadmin';

    /**
     * @var array<array<string, mixed>>
     */
    private array $systemRules;

    private Table $permissions;

    /**
     * @param \App\Rbac\PermissionsSynchronizer $sync Grants.
     * @param \App\Service\UserRoleManager $roles Roles.
     * @param array<array<string, mixed>>|null $systemRules System rules (default config/permissions.php).
     */
    public function __construct(
        private PermissionsSynchronizer $sync,
        private UserRoleManager $roles,
        ?array $systemRules = null,
    ) {
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
     * @return array<string, list<array{key: string, action: string, locked: string|null}>>
     */
    public function groups(): array
    {
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
            $groups[$group][] = [
                'key' => $permission->key(),
                'action' => $permission->action,
                'locked' => $this->lockReason($permission),
            ];
        }

        return $groups;
    }

    /**
     * Keys of the locked rows (they have no checkbox, so the browser never submits them).
     *
     * @return array<string, true>
     */
    public function lockedKeys(): array
    {
        $locked = [];
        foreach ($this->groups() as $actions) {
            foreach ($actions as $action) {
                if ($action['locked'] !== null) {
                    $locked[$action['key']] = true;
                }
            }
        }

        return $locked;
    }

    /**
     * Current grants as lookup tables (role slug => [action key => index]).
     *
     * @return array<string, array<string, int>>
     */
    public function grants(): array
    {
        return array_map('array_flip', $this->sync->grants());
    }

    /**
     * @return int Actions no longer present in the code.
     */
    public function orphans(): int
    {
        return $this->permissions->find()->where(['orphan' => true])->count();
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
