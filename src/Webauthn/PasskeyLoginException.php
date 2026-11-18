<?php
declare(strict_types=1);

namespace App\Webauthn;

use RuntimeException;

/**
 * Passwordless passkey login failure. The message is for the log: users only get a generic error
 * that never reveals whether an account exists.
 */
class PasskeyLoginException extends RuntimeException
{
}
