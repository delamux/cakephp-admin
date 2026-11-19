<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use App\Rbac\ActionDiscovery;
use App\Rbac\PermissionsSynchronizer;
use Cake\Core\Configure;

/**
 * Fills the permissions tables like `bin/cake permissions sync` (needs fixtures
 * app.Roles, app.Permissions, app.RolesPermissions).
 */
trait PermissionsTestTrait
{
    /**
     * @return void
     */
    protected function syncPermissions(): void
    {
        /** @var list<string> $ignored */
        $ignored = (array)Configure::read('App.permissions.ignoredActions', ['getUsersTable', 'setUsersTable', 'validateReCaptcha']);
        $discovery = new ActionDiscovery([
            ['plugin' => '', 'namespace' => 'App\\Controller', 'path' => APP . 'Controller' . DS],
            ['plugin' => 'CakeDC/Users', 'namespace' => 'CakeDC\\Users\\Controller', 'path' => ROOT . '/vendor/cakedc/users/src/Controller/'],
        ], $ignored);
        // Same defaults as the app (config/users.php is only loaded when the application boots)
        /** @var array<string, array<int|string, string>> $defaults */
        $defaults = (array)Configure::read('App.permissions.defaults', (require CONFIG . 'users.php')['App.permissions']['defaults']);
        (new PermissionsSynchronizer($defaults))->sync($discovery->actions());
    }
}
