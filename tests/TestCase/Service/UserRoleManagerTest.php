<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\UserRoleManager;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;

/**
 * Business rules of roles (superadmin, admin, editor, user) and account management.
 */
class UserRoleManagerTest extends TestCase
{
    private const ROLES = [
        'superadmin' => 'Superadmin',
        'admin' => 'Administrator',
        'editor' => 'Editor',
        'user' => 'User',
    ];

    private UserRoleManager $manager;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $users = [
        'super' => ['id' => 's1', 'role' => 'superadmin', 'is_superuser' => true],
        'otherSuper' => ['id' => 's2', 'role' => 'superadmin', 'is_superuser' => true],
        'admin' => ['id' => 'a1', 'role' => 'admin', 'is_superuser' => false],
        'otherAdmin' => ['id' => 'a2', 'role' => 'admin', 'is_superuser' => false],
        'editor' => ['id' => 'e1', 'role' => 'editor', 'is_superuser' => false],
        'user' => ['id' => 'u1', 'role' => 'user', 'is_superuser' => false],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new UserRoleManager(self::ROLES);
    }

    public function testRolesAreTheConfiguredWhitelist(): void
    {
        $this->assertSame(self::ROLES, $this->manager->roles());
    }

    public function testAccessLevels(): void
    {
        $this->assertTrue(UserRoleManager::isSuperadmin($this->users['super']));
        $this->assertTrue(UserRoleManager::isSuperadmin(['role' => 'user', 'is_superuser' => true]), 'The CakeDC flag is enough');
        $this->assertFalse(UserRoleManager::isSuperadmin($this->users['admin']));

        $this->assertTrue(UserRoleManager::hasAdminAccess($this->users['super']));
        $this->assertTrue(UserRoleManager::hasAdminAccess($this->users['admin']));
        $this->assertFalse(UserRoleManager::hasAdminAccess($this->users['editor']));
        $this->assertFalse(UserRoleManager::hasAdminAccess(null));

        $this->assertTrue(UserRoleManager::canViewUsers($this->users['editor']), 'Editors see users read-only');
        $this->assertTrue(UserRoleManager::canViewUsers($this->users['admin']));
        $this->assertFalse(UserRoleManager::canViewUsers($this->users['user']));
        $this->assertFalse(UserRoleManager::canViewUsers(null));
    }

    public function testAdminChangesRolesOfOtherNonSuperadminUsers(): void
    {
        $this->assertNull($this->manager->roleChangeError($this->users['admin'], $this->users['user'], 'editor'));
        $this->assertNull($this->manager->roleChangeError($this->users['admin'], $this->users['otherAdmin'], 'user'));
        $this->assertTrue($this->manager->canChangeRole($this->users['super'], $this->users['admin'], 'user'));
    }

    public function testEditorsAndUsersCannotChangeRoles(): void
    {
        $this->assertNotNull($this->manager->roleChangeError($this->users['editor'], $this->users['user'], 'editor'));
        $this->assertNotNull($this->manager->roleChangeError($this->users['user'], $this->users['editor'], 'user'));
    }

    public function testUnknownRolesAreRejected(): void
    {
        $this->assertNotNull($this->manager->roleChangeError($this->users['admin'], $this->users['user'], 'root'));
        $this->assertNotNull($this->manager->roleChangeError($this->users['super'], $this->users['user'], ''));
    }

    public function testNobodyChangesTheirOwnRole(): void
    {
        // Guarantees there is always at least one admin left: the actor keeps their access.
        $this->assertNotNull($this->manager->roleChangeError($this->users['admin'], $this->users['admin'], 'user'));
        $this->assertNotNull($this->manager->roleChangeError($this->users['super'], $this->users['super'], 'admin'));
    }

    public function testOnlySuperadminsGrantOrRemoveSuperadmin(): void
    {
        $this->assertNotNull($this->manager->roleChangeError($this->users['admin'], $this->users['user'], 'superadmin'), 'Admin cannot escalate anyone');
        $this->assertNotNull($this->manager->roleChangeError($this->users['admin'], $this->users['super'], 'user'), 'Admin cannot demote a superadmin');
        $this->assertNull($this->manager->roleChangeError($this->users['super'], $this->users['user'], 'superadmin'));
        $this->assertNull($this->manager->roleChangeError($this->users['super'], $this->users['otherSuper'], 'admin'));
    }

    public function testAssignableRolesDependOnTheActor(): void
    {
        $this->assertSame(self::ROLES, $this->manager->assignableRoles($this->users['super']));
        $this->assertSame(['admin' => 'Administrator', 'editor' => 'Editor', 'user' => 'User'], $this->manager->assignableRoles($this->users['admin']));
        $this->assertSame([], $this->manager->assignableRoles($this->users['editor']));
    }

    public function testOnlySuperadminsManageSuperadmins(): void
    {
        $this->assertFalse($this->manager->canManage($this->users['admin'], $this->users['super']), 'Admin cannot edit a superadmin (account takeover via email change)');
        $this->assertFalse($this->manager->canDelete($this->users['admin'], $this->users['super']));
        $this->assertTrue($this->manager->canManage($this->users['otherSuper'], $this->users['super']));
        $this->assertFalse($this->manager->canManage($this->users['editor'], $this->users['user']), 'Editors are read-only');
    }

    public function testNobodyDeletesTheirOwnAccountFromTheCrud(): void
    {
        $this->assertFalse($this->manager->canDelete($this->users['admin'], $this->users['admin']));
        $this->assertFalse($this->manager->canDelete($this->users['super'], $this->users['super']));
        $this->assertTrue($this->manager->canDelete($this->users['admin'], $this->users['user']));
    }

    public function testPasswordOfAnotherUserFollowsCakeDcSuperuserSetting(): void
    {
        $closed = $this->manager;
        $open = new UserRoleManager(self::ROLES, superusersChangePasswords: true);

        $this->assertTrue($closed->canChangePasswordOf($this->users['admin'], $this->users['admin']), 'Own password, always');
        $this->assertFalse($closed->canChangePasswordOf($this->users['super'], $this->users['user']), 'CakeDC default: not allowed');
        $this->assertTrue($open->canChangePasswordOf($this->users['super'], $this->users['user']));
        $this->assertFalse($open->canChangePasswordOf($this->users['admin'], $this->users['user']), 'Only superadmins, even when enabled');
    }

    public function testSuperuserFlagDrivesTheRoleWhenBothChange(): void
    {
        // e.g. `bin/cake users add_superuser` creates role `admin` + is_superuser
        $user = new Entity(['role' => 'admin', 'is_superuser' => true]);
        UserRoleManager::syncSuperadmin($user);
        $this->assertSame('superadmin', $user->get('role'));

        $revoked = new Entity(['role' => 'superadmin', 'is_superuser' => true], ['markClean' => true]);
        $revoked->set('is_superuser', false);
        UserRoleManager::syncSuperadmin($revoked);
        $this->assertSame('admin', $revoked->get('role'), 'Losing the flag drops to admin');
    }

    public function testRoleDrivesTheFlagWhenOnlyTheRoleChanges(): void
    {
        $promoted = new Entity(['role' => 'user', 'is_superuser' => false], ['markClean' => true]);
        $promoted->set('role', 'superadmin');
        UserRoleManager::syncSuperadmin($promoted);
        $this->assertTrue($promoted->get('is_superuser'));

        $demoted = new Entity(['role' => 'superadmin', 'is_superuser' => true], ['markClean' => true]);
        $demoted->set('role', 'editor');
        UserRoleManager::syncSuperadmin($demoted);
        $this->assertFalse($demoted->get('is_superuser'));
    }

    public function testUntouchedRegularUserStaysAsIs(): void
    {
        $user = new Entity(['role' => 'editor', 'is_superuser' => false], ['markClean' => true]);
        $user->set('first_name', 'Ana');
        UserRoleManager::syncSuperadmin($user);
        $this->assertSame('editor', $user->get('role'));
        $this->assertFalse($user->get('is_superuser'));
    }
}
