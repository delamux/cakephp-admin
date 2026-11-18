<?php
declare(strict_types=1);

namespace App\Test\TestCase\Rbac;

use App\Rbac\ActionDiscovery;
use Cake\TestSuite\TestCase;

/**
 * What counts as a controller action for the permissions catalogue.
 */
class ActionDiscoveryTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $found;

    protected function setUp(): void
    {
        parent::setUp();
        $discovery = new ActionDiscovery(
            [
                ['plugin' => '', 'namespace' => 'App\\Controller', 'path' => APP . 'Controller' . DS],
                ['plugin' => 'CakeDC/Users', 'namespace' => 'CakeDC\\Users\\Controller', 'path' => ROOT . '/vendor/cakedc/users/src/Controller/'],
            ],
            ['getUsersTable', 'setUsersTable', 'validateReCaptcha'],
        );
        $this->found = array_map(fn(array $a): string => ActionDiscovery::key($a), $discovery->actions());
    }

    public function testAppActionsAreFound(): void
    {
        $this->assertContains('Dashboard::index', $this->found);
        $this->assertContains('Reports::users', $this->found);
        $this->assertContains('UserRoles::edit', $this->found);
    }

    public function testPluginActionsIncludingTraitMethodsAreFound(): void
    {
        $this->assertContains('CakeDC/Users.Users::index', $this->found);
        $this->assertContains('CakeDC/Users.Users::login', $this->found, 'LoginTrait method');
        $this->assertContains('CakeDC/Users.SocialAccounts::validateAccount', $this->found);
    }

    public function testFrameworkMethodsCallbacksAndHelpersAreNotActions(): void
    {
        foreach (['beforeFilter', 'initialize', 'beforeRender', 'getUsersTable', 'validateReCaptcha', 'redirect', 'set'] as $method) {
            $this->assertEmpty(preg_grep('/::' . $method . '$/', $this->found), $method . ' must not be an action');
        }
    }

    public function testBaseAndErrorControllersAreSkipped(): void
    {
        $this->assertEmpty(preg_grep('/^(CakeDC\/Users\.)?(App|Error)::/', $this->found));
    }

    public function testResultIsSortedAndUnique(): void
    {
        $sorted = $this->found;
        sort($sorted);

        $this->assertSame($sorted, $this->found);
        $this->assertSame(array_values(array_unique($this->found)), $this->found);
    }

    public function testKeyFormatIncludesPrefix(): void
    {
        $this->assertSame('Admin/Articles::index', ActionDiscovery::key(['plugin' => '', 'prefix' => 'Admin', 'controller' => 'Articles', 'action' => 'index']));
        $this->assertSame('Blog.Admin/Posts::add', ActionDiscovery::key(['plugin' => 'Blog', 'prefix' => 'Admin', 'controller' => 'Posts', 'action' => 'add']));
    }
}
