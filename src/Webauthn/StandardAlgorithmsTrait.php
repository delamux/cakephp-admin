<?php
declare(strict_types=1);

namespace App\Webauthn;

use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\ECDSA\ES256K;
use Cose\Algorithm\Signature\ECDSA\ES384;
use Cose\Algorithm\Signature\ECDSA\ES512;
use Cose\Algorithm\Signature\EdDSA\Ed256;
use Cose\Algorithm\Signature\EdDSA\Ed512;
use Cose\Algorithm\Signature\EdDSA\EdDSA;
use Cose\Algorithm\Signature\RSA\PS256;
use Cose\Algorithm\Signature\RSA\PS384;
use Cose\Algorithm\Signature\RSA\PS512;
use Cose\Algorithm\Signature\RSA\RS256;
use Cose\Algorithm\Signature\RSA\RS384;
use Cose\Algorithm\Signature\RSA\RS512;
use ReflectionClass;

/**
 * COSE algorithms for the CakeDC WebAuthn adapters without the non-standard-algorithm warning.
 *
 * CakeDC/Users 16 builds Ed256/Ed512 without acknowledging they are non-standard; cose-lib >= 4.8
 * then raises E_USER_WARNING, which (in debug) leaks into the JSON responses and breaks the
 * registration/verification. EdDSA (-8) and the ES/RS/PS families are the standard ones; Ed256/Ed512
 * stay only for keys registered with them, acknowledged when cose-lib supports the flag.
 */
trait StandardAlgorithmsTrait
{
    /**
     * @return \Cose\Algorithm\Manager
     */
    protected function getAlgorithmManager(): Manager
    {
        if ($this->algorithmManager === null) {
            $this->algorithmManager = Manager::create()->add(
                ES256::create(),
                ES256K::create(),
                ES384::create(),
                ES512::create(),
                RS256::create(),
                RS384::create(),
                RS512::create(),
                PS256::create(),
                PS384::create(),
                PS512::create(),
                new EdDSA(),
                $this->acknowledgesNonStandard(Ed256::class)
                    ? new Ed256(acknowledgeNonStandardAlgorithm: true)
                    : new Ed256(),
                $this->acknowledgesNonStandard(Ed512::class)
                    ? new Ed512(acknowledgeNonStandardAlgorithm: true)
                    : new Ed512(),
            );
        }

        return $this->algorithmManager;
    }

    /**
     * cose-lib >= 4.8 asks to acknowledge non-standard algorithms; older versions lack the flag.
     *
     * @param class-string $class Algorithm class.
     * @return bool
     */
    private function acknowledgesNonStandard(string $class): bool
    {
        foreach ((new ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $parameter) {
            if ($parameter->getName() === 'acknowledgeNonStandardAlgorithm') {
                return true;
            }
        }

        return false;
    }
}
