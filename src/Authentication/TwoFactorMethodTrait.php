<?php
declare(strict_types=1);

namespace App\Authentication;

use App\Model\Table\UsersTable;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;

/**
 * Active two-factor methods of a user for the checkers. The table is queried by id because the
 * data they receive (`toArray()` of the CakeDC entity) hides `secret` and `additional_data`.
 */
trait TwoFactorMethodTrait
{
    /**
     * @param array<string, mixed>|null $user User data.
     * @return list<string> Active methods (`totp`, `webauthn`).
     */
    protected function twoFactorMethodsOf(?array $user): array
    {
        $id = $user['id'] ?? null;
        if (!is_string($id) || $id === '') {
            return [];
        }
        $users = TableRegistry::getTableLocator()->get((string)Configure::read('Users.table', 'Users'));

        return $users instanceof UsersTable ? $users->twoFactorMethods($id) : [];
    }
}
