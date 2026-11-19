<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use Cake\ORM\TableRegistry;
use CakeDC\Users\Webauthn\Base64Utility;
use OpenSSLAsymmetricKey;
use RuntimeException;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * Real WebAuthn credentials for tests: an EC P-256 key pair, the credential stored like CakeDC/Users
 * does (`additional_data.webauthn_credentials`) and browser assertions signed with that key.
 * Requests go to https://cakeadmin.local (the WebAuthn domain of APP_FULL_BASE_URL).
 */
trait WebauthnTestTrait
{
    protected const WEBAUTHN_HOST = 'cakeadmin.local';
    protected const CREDENTIAL_ID = 'credential-id';

    private ?OpenSSLAsymmetricKey $webauthnKey = null;

    private int $signCount = 0;

    /**
     * HTTPS requests on the WebAuthn domain (IntegrationTestTrait).
     */
    protected function onWebauthnHost(): void
    {
        $this->configRequest(['environment' => ['HTTPS' => 'on', 'HTTP_HOST' => self::WEBAUTHN_HOST]]);
    }

    protected function b64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function webauthnKey(): OpenSSLAsymmetricKey
    {
        return $this->webauthnKey ??= openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC])
            ?: throw new RuntimeException('openssl_pkey_new failed');
    }

    /**
     * Stores the test key as the user's credential.
     *
     * @param string $userId User id (also the credential's userHandle).
     * @param bool $passkey With the passkey flag (false: key registered before passkeys).
     */
    protected function storeKey(string $userId, bool $passkey = true): void
    {
        $details = (array)openssl_pkey_get_details($this->webauthnKey());
        // COSE EC2 P-256 public key: {1: 2, 3: -7, -1: 1, -2: x, -3: y}; coordinates are 32 bytes
        $x = str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT);
        $y = str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
        $cose = "\xa5\x01\x02\x03\x26\x20\x01\x21\x58\x20" . $x . "\x22\x58\x20" . $y;
        $stored = [
            'publicKeyCredentialId' => $this->b64url(self::CREDENTIAL_ID),
            'type' => 'public-key',
            'transports' => [],
            'attestationType' => 'none',
            'trustPath' => ['type' => EmptyTrustPath::class],
            'aaguid' => '00000000-0000-0000-0000-000000000000',
            'credentialPublicKey' => $this->b64url($cose),
            'userHandle' => $this->b64url($userId),
            'counter' => 0,
        ];
        $data = ['webauthn_credentials' => [Base64Utility::basicEncode(self::CREDENTIAL_ID) => $stored]];
        if ($passkey) {
            $data['webauthn_passkey'] = true;
        }
        TableRegistry::getTableLocator()->get('Users')->updateQuery()
            ->set(['additional_data' => $data], ['additional_data' => 'json'])
            ->where(['id' => $userId])
            ->execute();
    }

    /**
     * Browser response to `navigator.credentials.get()` signed with the test key (as webauthn.js sends it).
     *
     * @param string $challenge Challenge (binary).
     * @param string|null $userHandle User handle (passkeys return the user id).
     * @param bool $userVerified User verification flag.
     * @return array<string, mixed>
     */
    protected function assertion(string $challenge, ?string $userHandle, bool $userVerified = true): array
    {
        $clientData = (string)json_encode([
            'type' => 'webauthn.get',
            'challenge' => $this->b64url($challenge),
            'origin' => 'https://' . self::WEBAUTHN_HOST,
            'crossOrigin' => false,
        ], JSON_UNESCAPED_SLASHES);
        // rpIdHash + flags (UP 0x01, UV 0x04) + counter (must grow: a repeated one means a cloned key)
        $authData = hash('sha256', self::WEBAUTHN_HOST, true) . chr($userVerified ? 0x05 : 0x01) . pack('N', ++$this->signCount);
        openssl_sign($authData . hash('sha256', $clientData, true), $signature, $this->webauthnKey(), OPENSSL_ALGO_SHA256);

        $response = [
            'clientDataJSON' => $this->b64url($clientData),
            'authenticatorData' => $this->b64url($authData),
            'signature' => $this->b64url((string)$signature),
        ];
        if ($userHandle !== null) {
            $response['userHandle'] = $this->b64url($userHandle);
        }

        return [
            'id' => $this->b64url(self::CREDENTIAL_ID),
            'rawId' => $this->b64url(self::CREDENTIAL_ID),
            'type' => 'public-key',
            'response' => $response,
        ];
    }
}
