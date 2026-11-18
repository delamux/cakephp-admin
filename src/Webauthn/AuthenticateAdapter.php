<?php
declare(strict_types=1);

namespace App\Webauthn;

use CakeDC\Users\Webauthn\AuthenticateAdapter as CakeDcAuthenticateAdapter;

/**
 * Security key verification (second login step) with the algorithms of StandardAlgorithmsTrait.
 */
class AuthenticateAdapter extends CakeDcAuthenticateAdapter
{
    use StandardAlgorithmsTrait;
}
