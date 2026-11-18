<?php
declare(strict_types=1);

namespace App\View\Cell;

use App\Controller\TwoFactorController;
use App\UseCase\TwoFactor\TwoFactorSettings;
use Cake\View\Cell;

/**
 * "Two-step verification" card of the own profile (CakeDC's profile page, which has no hook for
 * extra view vars). State: active methods, the setup step in progress (QR / key registration),
 * the password form to reopen after an error.
 */
class TwoFactorCell extends Cell
{
    /**
     * @return void
     */
    public function display(): void
    {
        $identity = $this->request->getAttribute('identity');
        $userId = (string)($identity?->getIdentifier() ?? '');
        $session = $this->request->getSession();
        $settings = new TwoFactorSettings();

        $setup = $session->read(TwoFactorController::SETUP_KEY);
        if (!is_array($setup) || (int)($setup['expires'] ?? 0) < time()) {
            $setup = null;
        }
        $qr = null;
        if (($setup['method'] ?? null) === TwoFactorSettings::TOTP && !empty($setup['secret'])) {
            $label = (string)($identity?->get('email') ?: $identity?->get('username'));
            $qr = $settings->totpQr((string)$setup['secret'], $label);
        }
        $ask = $session->consume('TwoFactorAsk');

        $this->set([
            'methods' => $userId !== '' ? $settings->methods($userId) : [],
            'available' => [
                TwoFactorSettings::TOTP => $settings->available(TwoFactorSettings::TOTP),
                TwoFactorSettings::WEBAUTHN => $settings->available(TwoFactorSettings::WEBAUTHN),
            ],
            'setup' => $setup,
            'qr' => $qr,
            'ask' => is_string($ask) ? $ask : null,
            'legacyKey' => $userId !== '' && $settings->hasLegacyKey($userId),
            'secure' => $this->request->is('https'),
            'username' => (string)($identity?->get('username') ?? ''),
        ]);
    }

    /**
     * 2FA status of another user (user page, superadmins): active methods and the reset button,
     * never for another superadmin.
     *
     * @param string $userId Target user id.
     * @param bool $targetIsSuperuser Target is a superadmin.
     * @return void
     */
    public function status(string $userId, bool $targetIsSuperuser): void
    {
        $this->set([
            'userId' => $userId,
            'methods' => (new TwoFactorSettings())->methods($userId),
            'canReset' => !$targetIsSuperuser,
        ]);
    }
}
