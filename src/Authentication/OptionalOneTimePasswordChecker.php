<?php
declare(strict_types=1);

namespace App\Authentication;

use CakeDC\Auth\Authentication\DefaultOneTimePasswordAuthenticationChecker;

/**
 * TOTP code only for users who enabled it. CakeDC's checker requires it from everybody as soon as
 * `OneTimePasswordAuthenticator.login` is on (and enrols during login); here enrolment is done from
 * the profile and the login only asks users with a verified secret.
 */
class OptionalOneTimePasswordChecker extends DefaultOneTimePasswordAuthenticationChecker
{
    use TwoFactorMethodTrait;

    /**
     * @param array<string, mixed>|null $user User who just passed the password step.
     * @return bool
     */
    public function isRequired(?array $user = null): bool
    {
        return $this->isEnabled() && in_array('totp', $this->twoFactorMethodsOf($user), true);
    }
}
