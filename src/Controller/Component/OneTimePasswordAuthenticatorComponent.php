<?php
declare(strict_types=1);

namespace App\Controller\Component;

use App\UseCase\TwoFactor\TwoFactorSettings;
use CakeDC\Auth\Authentication\AuthenticationService;
use CakeDC\Auth\Controller\Component\OneTimePasswordAuthenticatorComponent as BaseComponent;

/**
 * CakeDC's TOTP component (login `/verify`) with replay protection: a code is accepted only once
 * (its 30 s window is claimed atomically, App\UseCase\TwoFactor\TwoFactorSettings::verifyLoginCode()).
 * Swapped in for CakeDC's by App\Controller\AppController::beforeFilter().
 */
class OneTimePasswordAuthenticatorComponent extends BaseComponent
{
    /**
     * @param string $secret TOTP secret of the user being verified.
     * @param string $code Code typed by the user.
     * @return bool
     */
    public function verifyCode(string $secret, string $code): bool
    {
        $user = $this->getController()->getRequest()->getSession()
            ->read(AuthenticationService::TWO_FACTOR_VERIFY_SESSION_KEY);
        $userId = (string)($user['id'] ?? '');
        if ($userId === '') {
            return false;
        }

        return (new TwoFactorSettings())->verifyLoginCode($userId, $secret, $code);
    }
}
