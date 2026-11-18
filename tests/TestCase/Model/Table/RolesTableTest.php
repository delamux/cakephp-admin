<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\RolesTable;
use App\Test\TestCase\UserFactoryTrait;
use Cake\TestSuite\TestCase;

/**
 * Business rules of roles stored in the database.
 */
class RolesTableTest extends TestCase
{
    use UserFactoryTrait;

    protected array $fixtures = ['app.Roles', 'app.Users', 'app.Permissions', 'app.RolesPermissions'];

    private RolesTable $Roles;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var \App\Model\Table\RolesTable $roles */
        $roles = $this->fetchTable('Roles');
        $this->Roles = $roles;
    }

    public function testNewRoleIsCreatedWithAValidSlug(): void
    {
        $role = $this->Roles->newEntity(['slug' => 'support', 'name' => 'Support']);

        $this->assertNotFalse($this->Roles->save($role));
        $this->assertFalse($role->get('protected'), 'New roles are never protected');
    }

    public function testSlugMustBeLowercaseAndUnique(): void
    {
        $this->assertNotEmpty($this->Roles->newEntity(['slug' => 'Support Team', 'name' => 'X'])->getError('slug'));
        $this->assertFalse($this->Roles->save($this->Roles->newEntity(['slug' => 'editor', 'name' => 'Another editor'])));
    }

    public function testProtectedFlagCannotBeAssigned(): void
    {
        $role = $this->Roles->newEntity(['slug' => 'root', 'name' => 'Root', 'protected' => true]);

        $this->assertFalse($role->get('protected') ?? false);
    }

    public function testProtectedRoleCannotBeRenamedOrDeleted(): void
    {
        $superadmin = $this->Roles->findBySlug('superadmin')->firstOrFail();

        $this->assertFalse($this->Roles->delete($superadmin));

        $superadmin = $this->Roles->patchEntity($superadmin, ['slug' => 'boss', 'name' => 'Boss']);
        $this->assertFalse($this->Roles->save($superadmin));
    }

    public function testRoleWithUsersCannotBeDeleted(): void
    {
        $this->createUser(['role' => 'editor']);
        $editor = $this->Roles->findBySlug('editor')->firstOrFail();

        $this->assertFalse($this->Roles->delete($editor));
        $this->assertNotEmpty($editor->getError('slug'));
    }

    public function testRoleWithoutUsersCanBeDeleted(): void
    {
        $editor = $this->Roles->findBySlug('editor')->firstOrFail();

        $this->assertTrue($this->Roles->delete($editor));
    }

    public function testSlugOfARoleWithUsersCannotChange(): void
    {
        $this->createUser(['role' => 'editor']);
        $editor = $this->Roles->findBySlug('editor')->firstOrFail();

        $editor = $this->Roles->patchEntity($editor, ['slug' => 'writer']);
        $this->assertFalse($this->Roles->save($editor), 'Users keep the slug in users.role');
    }

    public function testListIsSlugToNameOrderedByAccess(): void
    {
        $this->assertSame(
            ['superadmin' => 'Superadmin', 'admin' => 'Administrator', 'editor' => 'Editor', 'user' => 'User'],
            $this->Roles->slugList(),
        );
    }
}
