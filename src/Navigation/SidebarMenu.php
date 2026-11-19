<?php
declare(strict_types=1);

namespace App\Navigation;

use App\Service\UserRoleManager;
use ArrayAccess;

/**
 * Builds the sidebar navigation (BasecoatTheme SidebarHelper format) for the logged-in user.
 *
 * Rules:
 * - no identity: no menu,
 * - everybody: Dashboard + own account (Profile),
 * - admins (role `admin` or superuser): Administration section (Users, Users report PDF).
 */
class SidebarMenu
{
    /**
     * @param \ArrayAccess<string, mixed>|array<string, mixed>|null $user Identity data.
     * @return list<array<string, mixed>|string>
     */
    public function items(ArrayAccess|array|null $user): array
    {
        if ($user === null) {
            return [];
        }

        $items = [
            $this->item(__('Dashboard'), $this->appUrl('Dashboard', 'index'), 'layout-dashboard'),
        ];

        // Staff (superadmin, admin, editor): users + report (editors read-only)
        if (UserRoleManager::canViewUsers($user)) {
            $items[] = ['type' => 'group', 'label' => __('Administration'), 'items' => [
                $this->item(__('Users'), $this->usersUrl('index'), 'users') + ['currentMatch' => 'prefix'],
                $this->item(__('Users report'), $this->appUrl('Reports', 'users') + ['_ext' => 'pdf'], 'file-text')
                    + ['target' => '_blank'],
            ]];
        }
        // Access control (roles + permissions matrix): superadmin only
        if (UserRoleManager::isSuperadmin($user)) {
            $items[] = ['type' => 'group', 'label' => __('Access control'), 'items' => [
                $this->item(__('Roles'), $this->appUrl('Roles', 'index'), 'circle-user') + ['currentMatch' => 'prefix'],
                $this->item(__('Permissions'), $this->appUrl('Permissions', 'index'), 'settings-2'),
                $this->item(__('History'), $this->appUrl('Permissions', 'audit'), 'file-text'),
            ]];
            // Operations: background jobs (emails...)
            $items[] = ['type' => 'group', 'label' => __('System'), 'items' => [
                $this->item(__('Queue'), $this->appUrl('Queue', 'index'), 'inbox'),
            ]];
        }

        $items[] = [
            'type' => 'group',
            'label' => __('Account'),
            'attributes' => ['class' => 'mt-auto'],
            'items' => [$this->item(__('Profile'), $this->usersUrl('profile'), 'circle-user')],
        ];

        return $items;
    }

    /**
     * Data for the sidebar footer user dropdown.
     *
     * @param \ArrayAccess<string, mixed>|array<string, mixed> $user Identity data.
     * @return array{name: string, email: string, menu: list<array<string, mixed>|string>}
     */
    public function user(ArrayAccess|array $user): array
    {
        $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

        return [
            'name' => $fullName !== '' ? $fullName : (string)($user['username'] ?? ''),
            'email' => (string)($user['email'] ?? ''),
            'menu' => [
                $this->item(__('Profile'), $this->usersUrl('profile'), 'circle-user'),
                $this->item(__('Change password'), $this->usersUrl('changePassword'), 'settings-2'),
                '-',
                $this->item(__('Log out'), $this->usersUrl('logout'), 'log-out') + ['variant' => 'destructive'],
            ],
        ];
    }

    /**
     * Admins: role `admin` or superuser.
     *
     * @param \ArrayAccess<string, mixed>|array<string, mixed> $user Identity data.
     * @return bool
     */
    public function isAdmin(ArrayAccess|array $user): bool
    {
        return UserRoleManager::hasAdminAccess($user);
    }

    /**
     * @param string $label Label.
     * @param array<string, mixed> $url URL.
     * @param string $icon Icon name.
     * @return array<string, mixed>
     */
    private function item(string $label, array $url, string $icon): array
    {
        return ['label' => $label, 'url' => $url, 'icon' => $icon];
    }

    /**
     * @param string $controller Controller.
     * @param string $action Action.
     * @return array<string, mixed>
     */
    private function appUrl(string $controller, string $action): array
    {
        return ['plugin' => false, 'prefix' => false, 'controller' => $controller, 'action' => $action];
    }

    /**
     * @param string $action CakeDC/Users UsersController action.
     * @return array<string, mixed>
     */
    private function usersUrl(string $action): array
    {
        return ['plugin' => 'CakeDC/Users', 'prefix' => false, 'controller' => 'Users', 'action' => $action];
    }
}
