<?php
declare(strict_types=1);

namespace App\Test\TestCase\Navigation;

use App\Navigation\SidebarMenu;
use Cake\TestSuite\TestCase;

/**
 * Navigation rules: what each kind of user can see in the sidebar.
 */
class SidebarMenuTest extends TestCase
{
    private SidebarMenu $menu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->menu = new SidebarMenu();
    }

    /**
     * @param list<mixed> $items
     * @return list<string>
     */
    private function labels(array $items): array
    {
        $labels = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (isset($item['label'])) {
                $labels[] = $item['label'];
            }
            if (isset($item['items'])) {
                $labels = array_merge($labels, $this->labels($item['items']));
            }
        }

        return $labels;
    }

    public function testRegularUserOnlySeesDashboardAndOwnAccount(): void
    {
        $labels = $this->labels($this->menu->items(['role' => 'user', 'is_superuser' => false]));

        $this->assertContains('Dashboard', $labels);
        $this->assertContains('Profile', $labels);
        $this->assertNotContains('Users', $labels);
        $this->assertNotContains('Users report', $labels);
    }

    public function testAdminRoleSeesAdministrationSection(): void
    {
        $labels = $this->labels($this->menu->items(['role' => 'admin', 'is_superuser' => false]));

        $this->assertContains('Administration', $labels);
        $this->assertContains('Users', $labels);
        $this->assertContains('Users report', $labels);
    }

    public function testSuperuserSeesAdministrationEvenWithoutAdminRole(): void
    {
        $labels = $this->labels($this->menu->items(['role' => 'user', 'is_superuser' => true]));

        $this->assertContains('Users', $labels);
    }

    public function testEditorSeesUsersAndReportReadOnly(): void
    {
        $labels = $this->labels($this->menu->items(['role' => 'editor', 'is_superuser' => false]));

        $this->assertContains('Users', $labels);
        $this->assertContains('Users report', $labels);
    }

    public function testOnlySuperadminSeesRolesAndPermissions(): void
    {
        $super = $this->labels($this->menu->items(['role' => 'superadmin', 'is_superuser' => true]));
        $admin = $this->labels($this->menu->items(['role' => 'admin', 'is_superuser' => false]));

        $this->assertContains('Roles', $super);
        $this->assertContains('Permissions', $super);
        $this->assertNotContains('Roles', $admin);
        $this->assertNotContains('Permissions', $admin);
    }

    public function testNoIdentityGivesNoMenu(): void
    {
        $this->assertSame([], $this->menu->items(null));
    }

    public function testUserBlockUsesFullNameAndFallsBackToUsername(): void
    {
        $withName = $this->menu->user(['username' => 'jdoe', 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'j@x.test']);
        $withoutName = $this->menu->user(['username' => 'jdoe', 'first_name' => null, 'last_name' => '', 'email' => 'j@x.test']);

        $this->assertSame('Jane Doe', $withName['name']);
        $this->assertSame('jdoe', $withoutName['name']);
        $this->assertSame('j@x.test', $withName['email']);
    }

    public function testUserBlockMenuEndsWithLogout(): void
    {
        $menu = $this->menu->user(['username' => 'jdoe', 'email' => 'j@x.test'])['menu'];
        $last = end($menu);

        $this->assertSame('Log out', $last['label']);
        $this->assertSame('destructive', $last['variant']);
        $this->assertContains('Change password', array_column(array_filter($menu, 'is_array'), 'label'));
    }
}
