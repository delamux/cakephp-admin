<?php
declare(strict_types=1);

namespace App\Model\Entity;

use CakeDC\Users\Model\Entity\User as BaseUser;

/**
 * CakeDC/Users user with the two-factor columns protected from mass assignment: CakeDC's entity
 * accepts every field but `id`, `is_superuser` and `role`, so a form could plant a TOTP secret or a
 * WebAuthn key in another account. 2FA is only written by App\Model\Table\UsersTable methods; the
 * photo only by App\UseCase\Users\ChangeUserPhoto.
 */
class User extends BaseUser
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        '*' => true,
        'id' => false,
        'is_superuser' => false,
        'role' => false,
        'secret' => false,
        'secret_verified' => false,
        'additional_data' => false,
        'totp_last_step' => false,
        // Only App\UseCase\Users\ChangeUserPhoto (validated, re-encoded image)
        'photo' => false,
        'photo_dir' => false,
    ];
}
