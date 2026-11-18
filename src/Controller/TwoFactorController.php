<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\UserRoleManager;
use App\UseCase\TwoFactor\TwoFactorSettings;
use App\Webauthn\RegisterAdapter;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Response;
use Cake\Log\Log;
use Cake\Routing\Router;
use CakeDC\Auth\Authentication\AuthenticationService;
use Throwable;

/**
 * Own two-factor settings, from the profile card (App\View\Cell\TwoFactorCell). Every step is a
 * POST: 1) current password + method -> setup step in the session (10 min); 2) TOTP code, or the
 * key registered by the browser (JSON). Rules in App\UseCase\TwoFactor\TwoFactorSettings.
 */
class TwoFactorController extends AppController
{
    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        // JSON endpoints called by webroot/js/webauthn.js (fetch, no form token; CSRF header still checked)
        $this->FormProtection->unlockActions(['webauthnOptions', 'webauthnRegister']);
    }

    public const SETUP_KEY = 'TwoFactorSetup';
    private const SETUP_TTL = 600;

    /**
     * @param \App\UseCase\TwoFactor\TwoFactorSettings $settings Use case.
     * @return \Cake\Http\Response|null
     */
    public function start(TwoFactorSettings $settings): ?Response
    {
        $this->request->allowMethod(['post']);
        $userId = $this->userId();
        $method = (string)$this->request->getData('method');
        if (!$settings->available($method)) {
            return $this->back(__('Choose a verification method.'), false);
        }
        if (in_array($method, $settings->methods($userId), true)) {
            return $this->back(__('That method is already active.'), false);
        }
        if (!$settings->passwordMatches($userId, (string)$this->request->getData('current_password'))) {
            $this->request->getSession()->write('TwoFactorAsk', $method);

            return $this->back(__('The password is not correct.'), false);
        }
        $setup = ['method' => $method, 'expires' => time() + self::SETUP_TTL];
        if ($method === TwoFactorSettings::TOTP) {
            $setup['secret'] = $settings->newTotpSecret();
        }
        $this->request->getSession()->write(self::SETUP_KEY, $setup);

        return $this->redirect($this->profileUrl());
    }

    /**
     * @param \App\UseCase\TwoFactor\TwoFactorSettings $settings Use case.
     * @return \Cake\Http\Response|null
     */
    public function confirm(TwoFactorSettings $settings): ?Response
    {
        $this->request->allowMethod(['post']);
        $setup = $this->setup(TwoFactorSettings::TOTP);
        if ($setup === null || empty($setup['secret'])) {
            return $this->back(__('The step has expired. Start again.'), false);
        }
        $code = (string)$this->request->getData('code');
        if (!$settings->confirmTotp($this->userId(), (string)$setup['secret'], $code)) {
            $message = __('The code is not valid. Check the time on your phone and type the current code.');

            return $this->back($message, false);
        }
        $this->request->getSession()->delete(self::SETUP_KEY);

        return $this->back(__('Two-step verification is on.'), true);
    }

    /**
     * @return \Cake\Http\Response|null
     */
    public function cancel(): ?Response
    {
        $this->request->allowMethod(['post']);
        $this->request->getSession()->delete(self::SETUP_KEY);

        return $this->redirect($this->profileUrl());
    }

    /**
     * @param \App\UseCase\TwoFactor\TwoFactorSettings $settings Use case.
     * @return \Cake\Http\Response|null
     */
    public function disable(TwoFactorSettings $settings): ?Response
    {
        $this->request->allowMethod(['post']);
        $userId = $this->userId();
        $method = (string)$this->request->getData('method');
        if (!$settings->passwordMatches($userId, (string)$this->request->getData('current_password'))) {
            $this->request->getSession()->write('TwoFactorAsk', 'disable-' . $method);

            return $this->back(__('The password is not correct.'), false);
        }
        if (!$settings->disable($userId, $method)) {
            return $this->redirect($this->profileUrl());
        }

        return $this->back($method === TwoFactorSettings::TOTP
            ? __('The authenticator app has been removed.')
            : __('The security key has been removed.'), true);
    }

    /**
     * Lost device: a superadmin removes another user's 2FA (both methods) and the user is emailed.
     * Superadmin only (no RBAC rule grants it; `App.permissions.superadminOnly`); another
     * superadmin's 2FA is never reset from the UI (the console command is the way out).
     *
     * @param \App\UseCase\TwoFactor\TwoFactorSettings $settings Use case.
     * @param string $id Target user id.
     * @return \Cake\Http\Response|null
     */
    public function reset(TwoFactorSettings $settings, string $id): ?Response
    {
        $this->request->allowMethod(['post']);
        // Defence in depth: superadmin only even if the matrix were misconfigured
        if (!UserRoleManager::isSuperadmin($this->request->getAttribute('identity'))) {
            throw new ForbiddenException();
        }
        $target = $this->fetchTable('Users')->get($id);
        $back = ['plugin' => 'CakeDC/Users', 'prefix' => false, 'controller' => 'Users', 'action' => 'view', $id];
        if ($target->get('is_superuser')) {
            $this->Flash->error(__('The two-step verification of another superadmin cannot be reset.'));

            return $this->redirect($back);
        }
        if ($settings->reset($id, actor: $this->actor())) {
            $this->Flash->success(__('Two-step verification removed for {0}.', $target->get('username')));
        }

        return $this->redirect($back);
    }

    /**
     * Key step 2: registration options for `navigator.credentials.create()` (JSON).
     *
     * @return \Cake\Http\Response
     */
    public function webauthnOptions(): Response
    {
        $this->request->allowMethod(['post']);
        $adapter = $this->registerAdapter();
        try {
            $options = $adapter->getOptions();
        } finally {
            $this->request->getSession()->delete(AuthenticationService::WEBAUTHN_2FA_SESSION_KEY);
        }

        return $this->response->withType('application/json')->withStringBody($adapter->toJson($options));
    }

    /**
     * Key step 3: the browser's response. Stored only if the server validates it.
     *
     * @param \App\UseCase\TwoFactor\TwoFactorSettings $settings Use case.
     * @return \Cake\Http\Response
     */
    public function webauthnRegister(TwoFactorSettings $settings): Response
    {
        $this->request->allowMethod(['post']);
        $userId = $this->userId();
        $adapter = $this->registerAdapter();
        $session = $this->request->getSession();
        try {
            $adapter->verifyResponse();
        } catch (Throwable $e) {
            Log::warning(sprintf('WebAuthn registration rejected for user %s: %s', $userId, $e->getMessage()));

            $message = __('The key could not be registered. Try again.');

            return $this->json(['success' => false, 'message' => $message], 422);
        } finally {
            $session->delete(AuthenticationService::WEBAUTHN_2FA_SESSION_KEY);
            $session->delete('Webauthn2fa.registerOptions');
            $session->delete('Webauthn2fa.userEntity');
        }
        $session->delete(self::SETUP_KEY);
        $settings->keyRegistered($userId);
        $this->Flash->success(__('Two-step verification is on.'), ['key' => 'twoFactor']);

        return $this->json(['success' => true, 'redirectUrl' => Router::url($this->profileUrl())]);
    }

    /**
     * CakeDC's register adapter for the logged-in user; requires the password step for a key.
     *
     * @return \App\Webauthn\RegisterAdapter
     * @throws \Cake\Http\Exception\ForbiddenException Without the password step.
     */
    private function registerAdapter(): RegisterAdapter
    {
        if ($this->setup(TwoFactorSettings::WEBAUTHN) === null) {
            throw new ForbiddenException(__('Confirm your password first.'));
        }
        $users = $this->fetchTable('Users');
        $this->request->getSession()
            ->write(AuthenticationService::WEBAUTHN_2FA_SESSION_KEY, $users->get($this->userId()));

        /** @var \CakeDC\Users\Model\Table\UsersTable $users */
        return new RegisterAdapter($this->request, $users);
    }

    /**
     * @param string $method Expected method.
     * @return array{method: string, expires: int, secret?: string}|null Setup step, if still valid.
     */
    private function setup(string $method): ?array
    {
        $setup = $this->request->getSession()->read(self::SETUP_KEY);
        if (!is_array($setup) || ($setup['method'] ?? null) !== $method || (int)($setup['expires'] ?? 0) < time()) {
            return null;
        }

        /** @var array{method: string, expires: int, secret?: string} */
        return $setup;
    }

    /**
     * @return string
     */
    private function userId(): string
    {
        return (string)$this->actor()->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function profileUrl(): array
    {
        return ['plugin' => 'CakeDC/Users', 'prefix' => false, 'controller' => 'Users', 'action' => 'profile'];
    }

    /**
     * @param string $message Message for the profile card.
     * @param bool $ok Success or error.
     * @return \Cake\Http\Response|null
     */
    private function back(string $message, bool $ok): ?Response
    {
        if ($ok) {
            $this->Flash->success($message, ['key' => 'twoFactor']);
        } else {
            $this->Flash->error($message, ['key' => 'twoFactor']);
        }

        return $this->redirect($this->profileUrl());
    }

    /**
     * @param array<string, mixed> $data Body.
     * @param int $status Status code.
     * @return \Cake\Http\Response
     */
    private function json(array $data, int $status = 200): Response
    {
        return $this->response->withStatus($status)->withType('application/json')
            ->withStringBody((string)json_encode($data));
    }
}
