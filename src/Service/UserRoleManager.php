<?php
declare(strict_types=1);

namespace App\Service;

use ArrayAccess;
use Cake\Core\Configure;
use Cake\Database\Exception\DatabaseException;
use Cake\Datasource\EntityInterface;
use Cake\ORM\TableRegistry;
use PDOException;

/**
 * Roles and account-management rules.
 *
 * Roles (whitelist `App.roles`): `superadmin`, `admin`, `editor`, `user`.
 * - superadmin: everything. Kept in sync with CakeDC's `is_superuser` flag (syncSuperadmin()),
 * - admin: manages users and roles, except superadmins; cannot grant superadmin,
 * - editor: read-only access to users and reports,
 * - user: dashboard and own profile.
 *
 * Rules:
 * - only superadmins manage superadmins (an admin editing a superadmin's email could take the
 *   account over with a password reset) and only they grant/remove superadmin,
 * - nobody changes their own role (so there is always at least one admin left: the actor),
 * - nobody deletes their own account from the CRUD.
 */
class UserRoleManager
{
    public const SUPERADMIN = 'superadmin';
    public const ADMIN = 'admin';
    public const EDITOR = 'editor';
    public const USER = 'user';

    /**
     * @param array<string, string> $roles Allowed roles: `role => label`.
     * @param bool $superusersChangePasswords CakeDC `Users.Superuser.allowedToChangePasswords`.
     */
    public function __construct(private array $roles, private bool $superusersChangePasswords = false)
    {
    }

    /**
     * Manager with the roles configured in `App.roles`.
     *
     * @return self
     */
    public static function fromConfig(): self
    {
        return new self(
            self::configuredRoles(),
            (bool)Configure::read('Users.Superuser.allowedToChangePasswords', false),
        );
    }

    /**
     * Roles from the `roles` table (Phase 9); `App.roles` only while the table does not exist yet.
     *
     * @return array<string, string>
     */
    private static function configuredRoles(): array
    {
        try {
            /** @var \App\Model\Table\RolesTable $roles */
            $roles = TableRegistry::getTableLocator()->get('Roles');
            $list = $roles->slugList();
            if ($list !== []) {
                return $list;
            }
        } catch (DatabaseException | PDOException) {
            // Not migrated yet
        }

        /** @var array<string, string> */
        return (array)Configure::read('App.roles', [
            self::SUPERADMIN => __('Superadmin'),
            self::ADMIN => __('Administrator'),
            self::EDITOR => __('Editor'),
            self::USER => __('User'),
        ]);
    }

    /**
     * @param \ArrayAccess<string, mixed>|array<string, mixed>|null $user User or identity data.
     * @return bool
     */
    public static function isSuperadmin(ArrayAccess|array|null $user): bool
    {
        return $user !== null && (!empty($user['is_superuser']) || ($user['role'] ?? null) === self::SUPERADMIN);
    }

    /**
     * Superadmins and admins: manage users and roles.
     *
     * @param \ArrayAccess<string, mixed>|array<string, mixed>|null $user User or identity data.
     * @return bool
     */
    public static function hasAdminAccess(ArrayAccess|array|null $user): bool
    {
        return self::isSuperadmin($user) || ($user['role'] ?? null) === self::ADMIN;
    }

    /**
     * Staff (superadmins, admins, editors): see users and reports.
     *
     * @param \ArrayAccess<string, mixed>|array<string, mixed>|null $user User or identity data.
     * @return bool
     */
    public static function canViewUsers(ArrayAccess|array|null $user): bool
    {
        return self::hasAdminAccess($user) || ($user['role'] ?? null) === self::EDITOR;
    }

    /**
     * Keeps role `superadmin` and the CakeDC `is_superuser` flag consistent before saving.
     * When the flag changes it wins (CLI `users add_superuser`); otherwise the role drives the flag.
     *
     * @param \Cake\Datasource\EntityInterface $user User entity.
     * @return void
     */
    public static function syncSuperadmin(EntityInterface $user): void
    {
        if ($user->isDirty('is_superuser')) {
            if ($user->get('is_superuser')) {
                $user->set('role', self::SUPERADMIN);
            } elseif ($user->get('role') === self::SUPERADMIN) {
                $user->set('role', self::ADMIN);
            }

            return;
        }
        if ($user->isDirty('role')) {
            $user->set('is_superuser', $user->get('role') === self::SUPERADMIN);
        }
    }

    /**
     * Allowed roles (`role => label`).
     *
     * @return array<string, string>
     */
    public function roles(): array
    {
        return $this->roles;
    }

    /**
     * Roles `$actor` may assign to other users.
     *
     * @param \ArrayAccess<string, mixed>|array<string, mixed>|null $actor Logged-in user.
     * @return array<string, string>
     */
    public function assignableRoles(ArrayAccess|array|null $actor): array
    {
        if (!self::hasAdminAccess($actor)) {
            return [];
        }
        if (self::isSuperadmin($actor)) {
            return $this->roles;
        }

        return array_diff_key($this->roles, [self::SUPERADMIN => true]);
    }

    /**
     * Can `$actor` edit the account of `$target`?
     *
     * @param \ArrayAccess<string, mixed>|array<string, mixed>|null $actor Logged-in user.
     * @param \ArrayAccess<string, mixed>|array<string, mixed> $target Managed user.
     * @return bool
     */
    public function canManage(ArrayAccess|array|null $actor, ArrayAccess|array $target): bool
    {
        if (!self::hasAdminAccess($actor)) {
            return false;
        }

        return !self::isSuperadmin($target) || self::isSuperadmin($actor);
    }

    /**
     * Can `$actor` delete the account of `$target`?
     *
     * @param \ArrayAccess<string, mixed>|array<string, mixed>|null $actor Logged-in user.
     * @param \ArrayAccess<string, mixed>|array<string, mixed> $target Managed user.
     * @return bool
     */
    public function canDelete(ArrayAccess|array|null $actor, ArrayAccess|array $target): bool
    {
        return $this->canManage($actor, $target) && !$this->isSameUser($actor, $target);
    }

    /**
     * Can `$actor` change the password of `$target`? Mirrors CakeDC: own password always,
     * another user's only for superadmins when `Users.Superuser.allowedToChangePasswords` is on.
     *
     * @param \ArrayAccess<string, mixed>|array<string, mixed>|null $actor Logged-in user.
     * @param \ArrayAccess<string, mixed>|array<string, mixed> $target Managed user.
     * @return bool
     */
    public function canChangePasswordOf(ArrayAccess|array|null $actor, ArrayAccess|array $target): bool
    {
        if ($this->isSameUser($actor, $target)) {
            return true;
        }

        return $this->superusersChangePasswords && !empty($actor['is_superuser']);
    }

    /**
     * @param \ArrayAccess<string, mixed>|array<string, mixed>|null $actor Logged-in user.
     * @param \ArrayAccess<string, mixed>|array<string, mixed> $target Managed user.
     * @param string $role New role.
     * @return bool
     */
    public function canChangeRole(ArrayAccess|array|null $actor, ArrayAccess|array $target, string $role): bool
    {
        return $this->roleChangeError($actor, $target, $role) === null;
    }

    /**
     * Why the role change is not allowed, or null when it is.
     *
     * @param \ArrayAccess<string, mixed>|array<string, mixed>|null $actor Logged-in user.
     * @param \ArrayAccess<string, mixed>|array<string, mixed> $target Managed user.
     * @param string $role New role.
     * @return string|null
     */
    public function roleChangeError(ArrayAccess|array|null $actor, ArrayAccess|array $target, string $role): ?string
    {
        if (!$this->canManage($actor, $target)) {
            return __('You are not allowed to change the role of this user.');
        }
        if ($this->isSameUser($actor, $target)) {
            return __('You cannot change your own role.');
        }
        if (!array_key_exists($role, $this->roles)) {
            return __('Unknown role "{0}".', $role);
        }
        if (!array_key_exists($role, $this->assignableRoles($actor))) {
            return __('Only superadmins can grant the {0} role.', $this->roles[$role]);
        }

        return null;
    }

    /**
     * @param \ArrayAccess<string, mixed>|array<string, mixed>|null $actor Logged-in user.
     * @param \ArrayAccess<string, mixed>|array<string, mixed> $target Managed user.
     * @return bool
     */
    private function isSameUser(ArrayAccess|array|null $actor, ArrayAccess|array $target): bool
    {
        return $actor !== null && isset($actor['id'], $target['id']) && (string)$actor['id'] === (string)$target['id'];
    }
}
