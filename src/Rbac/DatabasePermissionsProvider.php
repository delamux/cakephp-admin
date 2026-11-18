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
 * action, extension => '*'` (empty plugin/prefix = none). Orphan actions grant nothing.
 */
class DatabasePermissionsProvider extends ConfigProvider
{
    /**
     * @return array<array<string, mixed>>
     */
    public function getPermissions(): array
    {
        $database = PermissionsCache::remember(fn(): array => $this->databaseRules());

        return array_merge(parent::getPermissions(), $database);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function databaseRules(): array
    {
        $rows = TableRegistry::getTableLocator()->get('RolesPermissions')->find()
            ->select([
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
            $key = ActionDiscovery::key($permission);
            $rules[$key] ??= [
                'role' => [],
                'plugin' => $permission['plugin'] !== '' ? $permission['plugin'] : null,
                'prefix' => $permission['prefix'] !== '' ? $permission['prefix'] : null,
                'controller' => $permission['controller'],
                'action' => $permission['action'],
                'extension' => '*',
            ];
            $rules[$key]['role'][] = $row['_matchingData']['Roles']['slug'];
        }
        ksort($rules, SORT_STRING);

        return array_values($rules);
    }
}
