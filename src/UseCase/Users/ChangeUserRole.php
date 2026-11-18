<?php
declare(strict_types=1);

namespace App\UseCase\Users;

use App\Service\UserRoleManager;
use ArrayAccess;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;

/**
 * Changes the role of a user from the users CRUD, enforcing UserRoleManager's rules
 * (whitelist, not own role, superadmin only by superadmins). Returns the error, or null.
 */
class ChangeUserRole
{
    /**
     * @param \App\Service\UserRoleManager $roles Role rules.
     */
    public function __construct(private UserRoleManager $roles)
    {
    }

    /**
     * @param \ArrayAccess<string, mixed>|array<string, mixed>|null $actor Logged-in user.
     * @param string $userId Target user id.
     * @param string $role New role slug.
     * @return array{user: \Cake\Datasource\EntityInterface, error: string|null, roleName: string|null}
     */
    public function execute(ArrayAccess|array|null $actor, string $userId, string $role): array
    {
        $users = TableRegistry::getTableLocator()->get((string)Configure::read('Users.table', 'Users'));
        /** @var \Cake\Datasource\EntityInterface $user */
        $user = $users->get($userId);
        $error = $this->roles->roleChangeError($actor, $user, $role);
        if ($error === null) {
            // `role` is not mass-assignable on the CakeDC entity: set it explicitly, nothing else.
            $user->set('role', $role);
            $error = $users->save($user) ? null : __('The role could not be saved.');
        }

        return ['user' => $user, 'error' => $error, 'roleName' => $error === null ? $this->roleName($role) : null];
    }

    /**
     * @param string $role Role slug.
     * @return string
     */
    private function roleName(string $role): string
    {
        return $this->roles->roles()[$role] ?? $role;
    }
}
