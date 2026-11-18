<?php
declare(strict_types=1);

namespace App\Webauthn;

use CakeDC\Users\Webauthn\RegisterAdapter as CakeDcRegisterAdapter;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;

/**
 * Key registration from the profile, as a passkey: discoverable (stored by the key, the device or
 * the password manager, so the browser can offer it on the login page without a username) and with
 * user verification required (fingerprint, face or PIN), so it can also sign in without a password.
 */
class RegisterAdapter extends CakeDcRegisterAdapter
{
    use StandardAlgorithmsTrait;

    /**
     * Stored in the session like CakeDC does (`Webauthn2fa.registerOptions` / `.userEntity`), which
     * is where `verifyResponse()` reads them.
     *
     * @return \Webauthn\PublicKeyCredentialCreationOptions
     */
    public function getOptions(): PublicKeyCredentialCreationOptions
    {
        $userEntity = $this->getUserEntity();
        $options = PublicKeyCredentialCreationOptions::create(
            rp: $this->rpEntity,
            user: $userEntity,
            challenge: random_bytes(32),
            pubKeyCredParams: $this->getPubKeyCredParams(),
            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            ),
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
        );
        $this->request->getSession()->write('Webauthn2fa.registerOptions', $options);
        $this->request->getSession()->write('Webauthn2fa.userEntity', $userEntity);

        return $options;
    }
}
