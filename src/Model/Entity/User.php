<?php
declare(strict_types=1);

namespace App\Model\Entity;

use CakeDC\Users\Model\Entity\User as BaseUser;

/**
 * CakeDC/Users user with the sensitive columns protected from mass assignment: CakeDC's entity
 * accepts every field but `id`, `is_superuser` and `role`, so a form (users CRUD, public sign-up) could
 * plant a TOTP secret, a WebAuthn key, a reset/sign-in token or fake login bookkeeping. 2FA is only
 * written by App\Model\Table\UsersTable methods; the photo only by App\UseCase\Users\ChangeUserPhoto;
 * tokens and dates by CakeDC's behaviors (`set()`/`updateAll()`, not affected) and InviteUser.
 *
 * `password` stays assignable: sign-up, the users CRUD `add`, change password and reset password set it
 * from their forms (and validate it). The CRUD `edit` drops it from the request (App\Controller\AppController).
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
        'invited_at' => false,
        // Only App\UseCase\Account\ActiveSessions (rotated to sign the other devices out)
        'session_key' => false,
        // Validation / reset / sign-in link tokens and login bookkeeping: CakeDC sets them itself
        'token' => false,
        'token_expires' => false,
        'api_token' => false,
        'activation_date' => false,
        'login_token' => false,
        'login_token_date' => false,
        'token_send_requested' => false,
        'last_login' => false,
        'lockout_time' => false,
    ];

    /**
     * Secret of the "remember me" cookie token (`Auth.Authenticators.Cookie.fields.password`): the
     * password hash plus `session_key`. Changing the password or rotating the key invalidates every
     * remember-me cookie of the account.
     *
     * @return string
     */
    protected function _getRememberSecret(): string
    {
        return (string)$this->get('password') . (string)$this->get('session_key');
    }
}
