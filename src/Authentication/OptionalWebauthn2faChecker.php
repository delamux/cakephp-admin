<?php
declare(strict_types=1);

namespace App\Authentication;

use CakeDC\Auth\Authentication\DefaultWebauthn2FAuthenticationChecker;

/**
 * Security key (WebAuthn) as second step only for users who registered one, and only when it is
 * their only method: with TOTP enabled the code is asked instead (OptionalOneTimePasswordChecker).
 */
class OptionalWebauthn2faChecker extends DefaultWebauthn2FAuthenticationChecker
{
    use TwoFactorMethodTrait;

    /**
     * @param array<string, mixed>|null $user User who just passed the password step.
     * @return bool
     */
    public function isRequired(?array $user = null): bool
    {
        return $this->isEnabled() && $this->twoFactorMethodsOf($user) === ['webauthn'];
    }
}
