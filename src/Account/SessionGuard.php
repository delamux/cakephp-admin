<?php
declare(strict_types=1);

namespace App\Account;

use App\Rbac\ActionDiscovery;
use App\Rbac\PermissionsSynchronizer;
use Cake\Core\Configure;
use Cake\Http\Session;

/**
 * Session rules on top of CakePHP's idle timeout (`Session.timeout`), applied by
 * App\Middleware\RefreshIdentityMiddleware and App\Middleware\ConfirmPasswordMiddleware:
 *
 * - **Maximum lifetime**: a signed-in session ends N seconds after it started, however active it is
 *   (`App.session.maxLifetime`, 0 = no limit).
 * - **Sign-out key**: the session remembers `users.session_key` when it starts; when the account
 *   rotates it (password change or reset, "sign out of every other session") every other session ends.
 * - **Password confirmation** ("sudo mode"): sensitive actions (`App.session.confirmActions`, patterns
 *   on `Plugin.Prefix/Controller::action`, `!` excludes) require the password typed in the last
 *   N seconds (`App.session.confirmWindow`, 0 = switched off). A password or passkey login counts as
 *   a confirmation; a "remember me" cookie or an emailed sign-in link does not.
 *
 * The timestamps live in the session under `App.Session` and are dropped when nobody is signed in.
 */
final class SessionGuard
{
    public const SIGNED_IN_AT = 'App.Session.signedInAt';
    public const CONFIRMED_AT = 'App.Session.confirmedAt';
    public const KEY = 'App.Session.key';
    private const ROOT = 'App.Session';

    /**
     * Default sensitive actions: who can do what (permissions, roles, role changes, invitations with a
     * role), account takeover paths (editing email/username, deleting, resetting someone's 2FA).
     */
    public const CONFIRM_ACTIONS = [
        'Permissions::*',
        'Roles::*',
        'UserRoles::*',
        'Invitations::add',
        'Invitations::resend',
        'TwoFactor::reset',
        'CakeDC/Users.Users::add',
        'CakeDC/Users.Users::edit',
        'CakeDC/Users.Users::delete',
        'CakeDC/Users.Users::resetOneTimePasswordAuthenticator',
    ];

    /**
     * @param int $maxLifetime Seconds a session may live (0 = no limit).
     * @param int $confirmWindow Seconds a password confirmation lasts (0 = never asked).
     * @param list<string> $confirmActions Patterns of the actions that require it.
     */
    public function __construct(
        private int $maxLifetime,
        private int $confirmWindow,
        private array $confirmActions = self::CONFIRM_ACTIONS,
    ) {
    }

    /**
     * @return self
     */
    public static function fromConfig(): self
    {
        /** @var list<string> $actions */
        $actions = array_values((array)Configure::read('App.session.confirmActions', self::CONFIRM_ACTIONS));

        return new self(
            (int)Configure::read('App.session.maxLifetime', 12 * 3600),
            (int)Configure::read('App.session.confirmWindow', 15 * 60),
            $actions,
        );
    }

    /**
     * @param int|null $signedInAt When the session started (null = not stamped yet).
     * @param int $now Current time.
     * @return bool
     */
    public function lifetimeExceeded(?int $signedInAt, int $now): bool
    {
        return $this->maxLifetime > 0 && $signedInAt !== null && $now - $signedInAt >= $this->maxLifetime;
    }

    /**
     * @param string|null $stamped Key remembered by the session (null = not stamped yet).
     * @param string|null $current Current `users.session_key` (null = never rotated).
     * @return bool
     */
    public function keyChanged(?string $stamped, ?string $current): bool
    {
        return $stamped !== null && !hash_equals($stamped, (string)$current);
    }

    /**
     * @param array<string, mixed> $action `plugin`, `prefix`, `controller`, `action` of the request.
     * @return bool
     */
    public function requiresConfirmation(array $action): bool
    {
        if ($this->confirmWindow <= 0 || empty($action['controller']) || empty($action['action'])) {
            return false;
        }

        return PermissionsSynchronizer::matches(ActionDiscovery::key($action), $this->confirmActions);
    }

    /**
     * @param int|null $confirmedAt Last password confirmation (null = never).
     * @param int $now Current time.
     * @return bool
     */
    public function isConfirmed(?int $confirmedAt, int $now): bool
    {
        return $confirmedAt !== null && $now - $confirmedAt < $this->confirmWindow;
    }

    /**
     * A local path to go back to after confirming, or `/` (no other host, no `//`, no control characters).
     *
     * @param mixed $url Candidate.
     * @return string
     */
    public static function safeRedirect(mixed $url): string
    {
        if (
            !is_string($url)
            || !str_starts_with($url, '/')
            || str_starts_with($url, '//')
            || str_starts_with($url, '/\\')
            || preg_match('/[\x00-\x1F\x7F]/', $url)
        ) {
            return '/';
        }

        return $url;
    }

    /**
     * Interactive sign-in (password, 2FA, passkey): a new session that also counts as a confirmation.
     *
     * @param \Cake\Http\Session $session Session.
     * @return void
     */
    public static function signedIn(Session $session): void
    {
        $session->write(self::SIGNED_IN_AT, time());
        self::confirmed($session);
    }

    /**
     * @param \Cake\Http\Session $session Session.
     * @return void
     */
    public static function confirmed(Session $session): void
    {
        $session->write(self::CONFIRMED_AT, time());
    }

    /**
     * After this session rotated the key itself: it adopts the new one on the next request.
     *
     * @param \Cake\Http\Session $session Session.
     * @return void
     */
    public static function forgetKey(Session $session): void
    {
        $session->delete(self::KEY);
    }

    /**
     * Nobody signed in: the next sign-in starts from scratch.
     *
     * @param \Cake\Http\Session $session Session.
     * @return void
     */
    public static function clear(Session $session): void
    {
        if ($session->check(self::ROOT)) {
            $session->delete(self::ROOT);
        }
    }

    /**
     * @param \Cake\Http\Session $session Session.
     * @param string $key `SIGNED_IN_AT` or `CONFIRMED_AT`.
     * @return int|null
     */
    public static function time(Session $session, string $key): ?int
    {
        $value = $session->read($key);

        return is_int($value) ? $value : null;
    }
}
