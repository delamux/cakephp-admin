<?php
declare(strict_types=1);

namespace App\Rbac;

use Cake\ORM\TableRegistry;
use CakeDC\Auth\Rbac\Permissions\ConfigProvider;

/**
 * RBAC rules = system rules (config/permissions.php: public actions, superadmin, profile, dashboard,
 * DebugKit) + allow-list from the database (roles_permissions), cached until the next change.
 *
 * Configured in config/users.php: `Auth.RbacPolicy.adapter.permissions_provider_class`.
 * Each granted action becomes one CakeDC rule: `role => [slugs], plugin, prefix, controller,
 * action, extension => '*'` (empty plugin/prefix = none) plus `allowed` = the rule of
 * roles_permissions.rule (App\Rbac\RuleCatalog). Orphan actions grant nothing.
 */
class DatabasePermissionsProvider extends ConfigProvider
{
    /**
     * @return array<array<string, mixed>>
     */
    public function getPermissions(): array
    {
        // The cache holds plain data (rule names); the rule objects are attached on every read
        $database = PermissionsCache::remember(fn(): array => $this->databaseRules());
        $catalog = RuleCatalog::fromConfig();
        foreach ($database as $index => $rule) {
            $name = $rule['rule'] ?? null;
            unset($rule['rule']);
            if ($name !== null) {
                // Unknown rule (removed from the catalogue): deny rather than allow without it
                $rule['allowed'] = $catalog->get((string)$name) ?? false;
            }
            $database[$index] = $rule;
        }

        return array_merge(parent::getPermissions(), $database);
    }

    /**
     * One CakeDC rule per action and rule name: roles sharing the same rule are grouped.
     *
     * @return list<array<string, mixed>> `rule` holds the rule name (null = allowed).
     */
    public function databaseRules(): array
    {
        $rows = TableRegistry::getTableLocator()->get('RolesPermissions')->find()
            ->select([
                'RolesPermissions.rule',
                'Roles.slug',
                'Permissions.plugin',
                'Permissions.prefix',
                'Permissions.controller',
                'Permissions.action',
            ])
            ->innerJoinWith('Roles')
            ->innerJoinWith('Permissions', fn($q) => $q->where(['Permissions.orphan' => false]))
            ->disableHydration()
            ->all();

        $rules = [];
        foreach ($rows as $row) {
            $permission = $row['_matchingData']['Permissions'];
            $ruleName = is_string($row['rule'] ?? null) && $row['rule'] !== '' ? $row['rule'] : null;
            $key = ActionDiscovery::key($permission) . '|' . ($ruleName ?? '');
            $rules[$key] ??= [
                'role' => [],
                'plugin' => $permission['plugin'] !== '' ? $permission['plugin'] : null,
                'prefix' => $permission['prefix'] !== '' ? $permission['prefix'] : null,
                'controller' => $permission['controller'],
                'action' => $permission['action'],
                'extension' => '*',
                'rule' => $ruleName,
            ];
            $rules[$key]['role'][] = $row['_matchingData']['Roles']['slug'];
        }
        ksort($rules, SORT_STRING);

        return array_values($rules);
    }
}
