<?php
declare(strict_types=1);

namespace App\Webauthn;

use App\Model\Table\UsersTable;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Http\ServerRequest;
use Cake\ORM\TableRegistry;
use CakeDC\Users\Webauthn\PublicKeyCredentialLoader;
use CakeDC\Users\Webauthn\Repository\UserCredentialSourceRepository;
use Cose\Algorithm\Manager;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * Passwordless login with a passkey. Unlike CakeDC's adapters (which need the user in the session),
 * the user is unknown until the browser answers: the options have no `allowCredentials` and the
 * user is found by the `userHandle` (their id) of a flagged passkey. The challenge is single-use and
 * expires after CHALLENGE_LIFETIME seconds. Reuses CakeDC's credential format and repository.
 */
class PasskeyLoginAdapter
{
    use StandardAlgorithmsTrait;

    public const CHALLENGE_LIFETIME = 300;
    private const SESSION_OPTIONS = 'Passkey.options';
    private const SESSION_ISSUED = 'Passkey.issued';

    protected ?Manager $algorithmManager = null;

    private ?SerializerInterface $serializer = null;

    /**
     * Options for `navigator.credentials.get()`, stored (JSON) in the session.
     *
     * @param \Cake\Http\ServerRequest $request Request.
     * @return \Webauthn\PublicKeyCredentialRequestOptions
     */
    public function options(ServerRequest $request): PublicKeyCredentialRequestOptions
    {
        $options = PublicKeyCredentialRequestOptions::create(
            challenge: random_bytes(32),
            rpId: $this->relyingPartyId($request),
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: self::CHALLENGE_LIFETIME * 1000,
        );
        $request->getSession()->write(self::SESSION_OPTIONS, $this->toJson($options));
        $request->getSession()->write(self::SESSION_ISSUED, time());

        return $options;
    }

    /**
     * Checks the browser's assertion and returns the user.
     *
     * @param \Cake\Http\ServerRequest $request Request with the serialized credential.
     * @return \Cake\Datasource\EntityInterface
     * @throws \App\Webauthn\PasskeyLoginException Any failure (logged, never shown in detail).
     */
    public function verify(ServerRequest $request): EntityInterface
    {
        $session = $request->getSession();
        // Single use: consumed before anything is checked
        $json = $session->consume(self::SESSION_OPTIONS);
        $issued = (int)$session->consume(self::SESSION_ISSUED);
        if (!is_string($json) || $json === '') {
            throw new PasskeyLoginException('No passkey challenge in the session.');
        }
        if ($issued < time() - self::CHALLENGE_LIFETIME) {
            throw new PasskeyLoginException('The passkey challenge has expired.');
        }

        try {
            $options = $this->serializer()->deserialize($json, PublicKeyCredentialRequestOptions::class, 'json');
            $credential = (new PublicKeyCredentialLoader($this->serializer()))->loadArray((array)$request->getData());
            $response = $credential->response;
            if (!$response instanceof AuthenticatorAssertionResponse) {
                throw new PasskeyLoginException('The response is not an assertion.');
            }
            $userHandle = $response->userHandle;
            if ($userHandle === null || $userHandle === '') {
                throw new PasskeyLoginException('The passkey does not identify the user.');
            }
            $users = $this->users();
            $user = $users->find('passkeyOwner', userHandle: $userHandle, credentialId: $credential->rawId)->first();
            if (!$user instanceof EntityInterface) {
                throw new PasskeyLoginException('No user has that passkey.');
            }
            $repository = new UserCredentialSourceRepository($user, $users, $this->serializer());
            $record = $repository->findOneByCredentialId($credential->rawId);
            if ($record === null) {
                throw new PasskeyLoginException('No user has that passkey.');
            }
            $ceremonies = new CeremonyStepManagerFactory();
            $ceremonies->setAlgorithmManager($this->getAlgorithmManager());
            $ceremonies->setExtensionOutputCheckerHandler(ExtensionOutputCheckerHandler::create());
            $record = AuthenticatorAssertionResponseValidator::create($ceremonies->requestCeremony())->check(
                $record,
                $response,
                $options,
                $request->getUri()->getHost(),
                $userHandle,
            );
            // webauthn-lib 5 no longer persists the counter itself
            $repository->saveCredentialSource($record);
        } catch (PasskeyLoginException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new PasskeyLoginException('Invalid passkey: ' . $e->getMessage(), 0, $e);
        }

        return $users->get($user->get('id'));
    }

    /**
     * webauthn-lib 5 options are not JsonSerializable: Symfony serializer.
     *
     * @param object $value Options.
     * @return string
     */
    public function toJson(object $value): string
    {
        return $this->serializer()->serialize($value, 'json', [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            JsonEncode::OPTIONS => JSON_THROW_ON_ERROR,
        ]);
    }

    /**
     * @param \Cake\Http\ServerRequest $request Request.
     * @return string WebAuthn domain (`Webauthn2fa.id`, or the request host).
     */
    private function relyingPartyId(ServerRequest $request): string
    {
        $id = Configure::read('Webauthn2fa.id');

        return is_string($id) && $id !== '' ? $id : $request->getUri()->getHost();
    }

    /**
     * @return \Symfony\Component\Serializer\SerializerInterface
     */
    private function serializer(): SerializerInterface
    {
        if ($this->serializer === null) {
            $support = new AttestationStatementSupportManager();
            $support->add(new NoneAttestationStatementSupport());
            $this->serializer = (new WebauthnSerializerFactory($support))->create();
        }

        return $this->serializer;
    }

    /**
     * @return \App\Model\Table\UsersTable
     */
    private function users(): UsersTable
    {
        $users = TableRegistry::getTableLocator()->get((string)Configure::read('Users.table', 'Users'));
        if (!$users instanceof UsersTable) {
            throw new PasskeyLoginException('The users table is not App\Model\Table\UsersTable.');
        }

        return $users;
    }
}
