<?php
declare(strict_types=1);

namespace App\Test\TestCase\UseCase\Roles;

use App\Test\TestCase\UseCase\UseCaseTestTrait;
use App\Test\TestCase\UserFactoryTrait;
use App\UseCase\Roles\ManageRoles;
use Cake\TestSuite\TestCase;

/**
 * Roles use case: create/update/delete + cache + audit; the rules stay in RolesTable.
 */
class ManageRolesTest extends TestCase
{
    use UseCaseTestTrait;
    use UserFactoryTrait;

    protected array $fixtures = ['app.Users', 'app.Roles', 'app.Permissions', 'app.RolesPermissions', 'app.PermissionAudits'];

    private function roles(): ManageRoles
    {
        return new ManageRoles($this->auditLog());
    }

    public function testCreateUpdateDeleteAreAudited(): void
    {
        $created = $this->roles()->create(['slug' => 'support', 'name' => 'Support', 'protected' => true], $this->root());
        $this->assertTrue($created->ok);
        $this->assertFalse($created->role->get('protected'), 'Mass assignment of protected is ignored');

        $id = (string)$created->role->get('id');
        $this->assertTrue($this->roles()->update($id, ['name' => 'Customer support'], $this->root())->ok);
        $this->assertTrue($this->roles()->update($id, ['name' => 'Customer support'], $this->root())->ok, 'No-op save');
        $this->assertTrue($this->roles()->delete($id, $this->root())->ok);

        $this->assertSame(
            ['role_created', 'role_updated', 'role_deleted'],
            array_column($this->audits(), 'event'),
            'The no-op update is not audited',
        );
        $this->assertSame(['name' => ['Support', 'Customer support']], $this->audits()[1]['details']);
    }

    public function testFailuresReturnTheFirstErrorAndAreNotAudited(): void
    {
        $superadmin = (string)$this->fetchTable('Roles')->findBySlug('superadmin')->firstOrFail()->get('id');

        $deleted = $this->roles()->delete($superadmin, $this->root());
        $duplicate = $this->roles()->create(['slug' => 'editor', 'name' => 'Again'], $this->root());

        $this->assertFalse($deleted->ok);
        $this->assertSame('This role is protected and cannot be deleted.', $deleted->error);
        $this->assertFalse($duplicate->ok);
        $this->assertSame('This role already exists.', $duplicate->error);
        $this->assertSame([], $this->audits());
    }

    public function testSlugIsLockedForProtectedOrInUseRoles(): void
    {
        $this->createUser(['role' => 'editor']);
        $roles = $this->fetchTable('Roles');

        $this->assertTrue($this->roles()->isSlugLocked($roles->findBySlug('superadmin')->firstOrFail()));
        $this->assertTrue($this->roles()->isSlugLocked($roles->findBySlug('editor')->firstOrFail()));
        $this->assertFalse($this->roles()->isSlugLocked($roles->findBySlug('user')->firstOrFail()));
    }
}
