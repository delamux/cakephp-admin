<?php
declare(strict_types=1);

namespace App\UseCase\TwoFactor;

use App\Account\ActivityLog;
use App\Model\Table\UsersTable;
use App\UseCase\Actor;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\Mailer\MailerAwareTrait;
use Cake\ORM\TableRegistry;
use RobThree\Auth\Algorithm;
use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;
use Throwable;

/**
 * Two-factor settings of an account (own profile, superadmin reset, console). Methods: `totp`
 * (authenticator app) and `webauthn` (security key / passkey), independent from each other.
 * Every change emails the user (never the secret; a mail failure does not undo the change) and
 * goes to the account activity log, as does a wrong code at login.
 */
class TwoFactorSettings
{
    use MailerAwareTrait;

    public const TOTP = 'totp';
    public const WEBAUTHN = 'webauthn';

    private UsersTable $users;

    private ActivityLog $activity;

    /**
     * @param \App\Model\Table\UsersTable|null $users Users table.
     * @param \App\Account\ActivityLog|null $activity Account activity log.
     */
    public function __construct(?UsersTable $users = null, ?ActivityLog $activity = null)
    {
        /** @var \App\Model\Table\UsersTable $table */
        $table = $users ?? TableRegistry::getTableLocator()->get((string)Configure::read('Users.table', 'Users'));
        $this->users = $table;
        $this->activity = $activity ?? new ActivityLog();
    }

    /**
     * @param string $userId User id.
     * @return list<string>
     */
    public function methods(string $userId): array
    {
        return $this->users->twoFactorMethods($userId);
    }

    /**
     * Method switched on in the config (TWO_FACTOR_ENABLED).
     *
     * @param string $method `totp` or `webauthn`.
     * @return bool
     */
    public function available(string $method): bool
    {
        return match ($method) {
            self::TOTP => (bool)Configure::read('OneTimePasswordAuthenticator.login'),
            self::WEBAUTHN => (bool)Configure::read('Webauthn2fa.enabled'),
            default => false,
        };
    }

    /**
     * @param string $userId User id.
     * @param string $password Current password typed by the user.
     * @return bool
     */
    public function passwordMatches(string $userId, string $password): bool
    {
        $hash = (string)$this->users->get($userId)->get('password');

        return $password !== '' && $hash !== '' && (new DefaultPasswordHasher())->check($password, $hash);
    }

    /**
     * @return string New TOTP secret (kept in the session until confirmed).
     */
    public function newTotpSecret(): string
    {
        return $this->totp()->createSecret();
    }

    /**
     * @param string $secret TOTP secret.
     * @param string $label Account label shown in the app (username/email).
     * @return string QR code as a data URI.
     */
    public function totpQr(string $secret, string $label): string
    {
        return $this->totp()->getQRCodeImageAsDataUri($label, $secret);
    }

    /**
     * Enables TOTP if `$code` matches the secret shown in the QR.
     *
     * @param string $userId User id.
     * @param string $secret Secret of the setup step.
     * @param string $code Code typed by the user.
     * @return bool
     */
    public function confirmTotp(string $userId, string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        $step = 0;
        if ($code === '' || !$this->totp()->verifyCode($secret, $code, timeslice: $step)) {
            return false;
        }
        // The confirmation code counts as used: it cannot sign in afterwards
        $this->users->enableTotp($userId, $secret, $step);
        $this->activity->record($userId, ActivityLog::TOTP_ENABLED);
        $this->notify($userId, 'enabled', self::TOTP);

        return true;
    }

    /**
     * Login second step: the code must match the secret and its time window must not have been
     * used before (replay protection; App\Controller\Component\OneTimePasswordAuthenticatorComponent).
     *
     * @param string $userId User id.
     * @param string $secret User's TOTP secret.
     * @param string $code Code typed by the user.
     * @return bool
     */
    public function verifyLoginCode(string $userId, string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        $step = 0;
        $valid = $code !== '' && $this->totp()->verifyCode($secret, $code, timeslice: $step)
            && $this->users->claimTotpStep($userId, (int)$step);
        if (!$valid) {
            $this->activity->record($userId, ActivityLog::TWO_FACTOR_FAILED);
        }

        return $valid;
    }

    /**
     * After the browser registered a key (App\Webauthn\RegisterAdapter): flag it as passkey.
     *
     * @param string $userId User id.
     * @return void
     */
    public function keyRegistered(string $userId): void
    {
        $this->users->markPasskey($userId);
        $this->activity->record($userId, ActivityLog::KEY_ADDED);
        $this->notify($userId, 'enabled', self::WEBAUTHN);
    }

    /**
     * @param string $userId User id.
     * @param string $method Method to remove (the other one stays).
     * @return bool False when the method was not active.
     */
    public function disable(string $userId, string $method): bool
    {
        if (!in_array($method, $this->methods($userId), true)) {
            return false;
        }
        $method === self::TOTP ? $this->users->disableTotp($userId) : $this->users->disableWebauthn($userId);
        $this->activity->record(
            $userId,
            $method === self::TOTP ? ActivityLog::TOTP_DISABLED : ActivityLog::KEY_REMOVED,
        );
        $this->notify($userId, 'disabled', $method);

        return true;
    }

    /**
     * Removes both methods (lost device). `$notify` false from the console.
     *
     * @param string $userId User id.
     * @param bool $notify Email the user.
     * @param \App\UseCase\Actor|null $actor Who reset it (recorded in the user's activity).
     * @return bool False when the user had no 2FA.
     */
    public function reset(string $userId, bool $notify = true, ?Actor $actor = null): bool
    {
        if ($this->methods($userId) === []) {
            return false;
        }
        $this->users->disableTwoFactor($userId);
        $this->activity->record($userId, ActivityLog::TWO_FACTOR_RESET, actorId: $actor?->id);
        if ($notify) {
            $this->notify($userId, 'reset');
        }

        return true;
    }

    /**
     * Key registered before passkeys (second factor only, not passwordless login).
     *
     * @param string $userId User id.
     * @return bool
     */
    public function hasLegacyKey(string $userId): bool
    {
        return $this->users->isLegacyWebauthnKey($userId);
    }

    /**
     * @return \RobThree\Auth\TwoFactorAuth
     */
    private function totp(): TwoFactorAuth
    {
        $qr = Configure::read('OneTimePasswordAuthenticator.qrcodeprovider');

        return new TwoFactorAuth(
            qrcodeprovider: $qr instanceof EndroidQrCodeProvider ? $qr : new EndroidQrCodeProvider(),
            issuer: (string)Configure::read('OneTimePasswordAuthenticator.issuer', 'CakePHP Admin'),
            digits: (int)Configure::read('OneTimePasswordAuthenticator.digits', 6),
            period: (int)Configure::read('OneTimePasswordAuthenticator.period', 30),
            algorithm: Algorithm::Sha1,
        );
    }

    /**
     * @param string $userId User id.
     * @param string $event `enabled`, `disabled` or `reset`.
     * @param string|null $method Method.
     * @return void
     */
    private function notify(string $userId, string $event, ?string $method = null): void
    {
        try {
            $this->getMailer((string)(Configure::read('Users.Email.mailerClass') ?: 'CakeDC/Users.Users'))
                ->send('twoFactorChanged', [$this->users->get($userId), $event, $method]);
        } catch (Throwable $e) {
            Log::error(sprintf('2FA notification for user %s not sent: %s', $userId, $e->getMessage()));
        }
    }
}
