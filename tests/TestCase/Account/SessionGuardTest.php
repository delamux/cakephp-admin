<?php
declare(strict_types=1);

namespace App\Test\TestCase\Account;

use App\Account\SessionGuard;
use Cake\TestSuite\TestCase;

/**
 * Session rules: absolute lifetime (whatever the activity), the sign-out key of the account
 * (rotated by "sign out of every other session" and by a password change) and the password
 * confirmation that sensitive actions require.
 */
class SessionGuardTest extends TestCase
{
    private const NOW = 1_800_000_000;

    private function guard(int $maxLifetime = 3600, int $confirmWindow = 900): SessionGuard
    {
        return new SessionGuard($maxLifetime, $confirmWindow, ['Permissions::*', 'CakeDC/Users.Users::delete']);
    }

    public function testASessionEndsWhenItIsOlderThanTheMaximumLifetime(): void
    {
        $guard = $this->guard(maxLifetime: 3600);

        $this->assertFalse($guard->lifetimeExceeded(self::NOW - 3599, self::NOW));
        $this->assertTrue($guard->lifetimeExceeded(self::NOW - 3600, self::NOW));
        $this->assertFalse($guard->lifetimeExceeded(null, self::NOW), 'Not stamped yet: it starts now');
        $this->assertFalse($this->guard(maxLifetime: 0)->lifetimeExceeded(0, self::NOW), '0 = no limit');
    }

    public function testTheSessionEndsWhenTheAccountKeyChangedSinceItWasStamped(): void
    {
        $guard = $this->guard();

        $this->assertFalse($guard->keyChanged(null, 'abc'), 'Not stamped yet');
        $this->assertFalse($guard->keyChanged('abc', 'abc'));
        $this->assertFalse($guard->keyChanged('', null), 'Account never rotated');
        $this->assertTrue($guard->keyChanged('abc', 'def'));
        $this->assertTrue($guard->keyChanged('', 'def'));
    }

    public function testOnlyTheConfiguredActionsRequireARecentPasswordConfirmation(): void
    {
        $guard = $this->guard();
        $permissions = ['plugin' => '', 'prefix' => '', 'controller' => 'Permissions', 'action' => 'index'];
        $delete = ['plugin' => 'CakeDC/Users', 'prefix' => '', 'controller' => 'Users', 'action' => 'delete'];
        $profile = ['plugin' => 'CakeDC/Users', 'prefix' => '', 'controller' => 'Users', 'action' => 'profile'];

        $this->assertTrue($guard->requiresConfirmation($permissions));
        $this->assertTrue($guard->requiresConfirmation($delete));
        $this->assertFalse($guard->requiresConfirmation($profile));
        $this->assertFalse($this->guard(confirmWindow: 0)->requiresConfirmation($permissions), '0 = switched off');
    }

    public function testAConfirmationLastsTheConfiguredWindow(): void
    {
        $guard = $this->guard(confirmWindow: 900);

        $this->assertTrue($guard->isConfirmed(self::NOW - 899, self::NOW));
        $this->assertFalse($guard->isConfirmed(self::NOW - 900, self::NOW));
        $this->assertFalse($guard->isConfirmed(null, self::NOW), 'Never confirmed');
    }

    public function testOnlyLocalPathsAreAcceptedAsTheRedirectAfterConfirming(): void
    {
        $this->assertSame('/permissions?role=admin', SessionGuard::safeRedirect('/permissions?role=admin'));
        $this->assertSame('/', SessionGuard::safeRedirect('https://evil.example/'));
        $this->assertSame('/', SessionGuard::safeRedirect('//evil.example/'));
        $this->assertSame('/', SessionGuard::safeRedirect('/\\evil.example/'));
        $this->assertSame('/', SessionGuard::safeRedirect("/\tevil"));
        $this->assertSame('/', SessionGuard::safeRedirect(null));
        $this->assertSame('/', SessionGuard::safeRedirect(['/x']));
    }
}
