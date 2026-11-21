<?php
declare(strict_types=1);

namespace App\UseCase\Account;

use App\Http\AuthThrottle;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\Core\Configure;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;

/**
 * Password confirmation before a sensitive action (App\Account\SessionGuard, "sudo mode"): the
 * signed-in user types their password again. A wrong password counts as a failed login of the client
 * IP (App\Http\AuthThrottle), so a stolen session cannot be used to guess it.
 */
class ConfirmPassword
{
    private Table $users;

    /**
     * @param \Cake\ORM\Table|null $users Users table.
     */
    public function __construct(?Table $users = null)
    {
        $this->users = $users ?? TableRegistry::getTableLocator()->get((string)Configure::read('Users.table', 'Users'));
    }

    /**
     * @param string $userId Signed-in user.
     * @param string $password Typed password.
     * @param string $ip Client IP.
     * @return bool The password is right.
     */
    public function execute(string $userId, string $password, string $ip): bool
    {
        $user = $userId !== '' ? $this->users->find()->select(['password'])->where(['id' => $userId])->first() : null;
        $hash = (string)$user?->get('password');
        if ($password !== '' && $hash !== '' && (new DefaultPasswordHasher())->check($password, $hash)) {
            return true;
        }
        AuthThrottle::recordLoginFailure($ip);

        return false;
    }
}
