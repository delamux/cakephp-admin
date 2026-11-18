<?php
declare(strict_types=1);

namespace App\Controller;

use App\Account\ActivityLog;
use App\Model\Table\UsersTable;
use App\Webauthn\PasskeyLoginAdapter;
use App\Webauthn\PasskeyLoginException;
use Authentication\AuthenticationService;
use Authentication\Authenticator\CookieAuthenticator;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Http\Response;
use Cake\Log\Log;
use Cake\Routing\Router;
use CakeDC\Users\Identifier\PasswordLockout\LockoutHandler;
use Laminas\Diactoros\Uri;

/**
 * Passwordless login with a passkey (login page, webroot/js/webauthn.js). Public (bypassAuth).
 * The same account rules as the password login apply: active and, when configured, not locked.
 *
 * @property \Authentication\Controller\Component\AuthenticationComponent $Authentication
 */
class PasskeyLoginController extends AppController
{
    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        // JSON endpoints called by webroot/js/webauthn.js (fetch, no form token; CSRF header still checked)
        $this->FormProtection->unlockActions(['options', 'login']);
        // Same config CakeDC/Users loads in its controllers, for setIdentity()
        $config = (array)Configure::read('Auth.AuthenticationComponent');
        unset($config['config'], $config['load']);
        $this->loadComponent('Authentication.Authentication', $config);
    }

    /**
     * @param \App\Webauthn\PasskeyLoginAdapter $adapter Adapter.
     * @return \Cake\Http\Response
     */
    public function options(PasskeyLoginAdapter $adapter): Response
    {
        $this->request->allowMethod(['post']);

        return $this->response->withType('application/json')
            ->withStringBody($adapter->toJson($adapter->options($this->request)));
    }

    /**
     * @param \App\Webauthn\PasskeyLoginAdapter $adapter Adapter.
     * @return \Cake\Http\Response
     */
    public function login(PasskeyLoginAdapter $adapter): Response
    {
        $this->request->allowMethod(['post']);
        try {
            $user = $adapter->verify($this->request);
            $this->assertCanLogIn($user);
        } catch (PasskeyLoginException $e) {
            Log::warning('Passkey login rejected: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'message' => __('Could not sign in with the passkey. Try again or sign in with your password.'),
            ], 422);
        }

        $this->Authentication->setIdentity($user);
        $this->rememberMe($user);
        /** @var \App\Model\Table\UsersTable $users */
        $users = $this->fetchTable((string)Configure::read('Users.table', 'Users'));
        if ($users instanceof UsersTable) {
            $users->recordLogin((string)$user->get('id'));
        }
        (new ActivityLog())->record((string)$user->get('id'), ActivityLog::PASSKEY_LOGIN, $this->request);

        return $this->json(['success' => true, 'redirectUrl' => $this->redirectUrl()]);
    }

    /**
     * @param \Cake\Datasource\EntityInterface $user User.
     * @return void
     * @throws \App\Webauthn\PasskeyLoginException Inactive or locked account.
     */
    private function assertCanLogIn(EntityInterface $user): void
    {
        if (!$user->get('active')) {
            throw new PasskeyLoginException('Account ' . $user->get('id') . ' is not active.');
        }
        $identifiers = (array)Configure::read('Auth.Authenticators.Form.identifier');
        $lockout = $identifiers['Authentication.Password']['lockoutHandler'] ?? null;
        if (!is_array($lockout)) {
            return;
        }
        unset($lockout['className']);
        if (!(new LockoutHandler($lockout))->isUnlocked($user->toArray() + ['id' => $user->get('id')])) {
            throw new PasskeyLoginException('Account ' . $user->get('id') . ' is locked.');
        }
    }

    /**
     * "Remember me": CakeDC's cookie authenticator only issues the cookie on its login URL, so a
     * copy with this action as login URL (route array: CakeDC's CakeRouter checker) is used.
     *
     * @param \Cake\Datasource\EntityInterface $user User.
     * @return void
     */
    private function rememberMe(EntityInterface $user): void
    {
        $service = $this->request->getAttribute('authentication');
        if (!$service instanceof AuthenticationService || !$service->authenticators()->has('Cookie')) {
            return;
        }
        $cookie = $service->authenticators()->get('Cookie');
        if (!$cookie instanceof CookieAuthenticator) {
            return;
        }
        $here = new CookieAuthenticator(
            $cookie->getIdentifier(),
            ['loginUrl' => ['plugin' => false, 'prefix' => false, 'controller' => 'PasskeyLogin', 'action' => 'login']]
                + $cookie->getConfig(),
        );
        $response = $here->persistIdentity($this->request, $this->response, $user)['response'];
        if ($response instanceof Response) {
            $this->setResponse($response);
        }
    }

    /**
     * @return string `?redirect=` if internal (or an allowed host), the login redirect otherwise.
     */
    private function redirectUrl(): string
    {
        $default = Router::url(Configure::read('Auth.AuthenticationComponent.loginRedirect') ?? '/');
        $redirect = $this->request->getQuery('redirect');
        if (!is_string($redirect) || $redirect === '') {
            return $default;
        }
        $host = (new Uri($redirect))->getHost();
        if ($host !== '' && !in_array($host, (array)Configure::read('Users.AllowedRedirectHosts'), true)) {
            return $default;
        }

        return $redirect;
    }

    /**
     * @param array<string, mixed> $data Body.
     * @param int $status Status.
     * @return \Cake\Http\Response
     */
    private function json(array $data, int $status = 200): Response
    {
        return $this->response->withStatus($status)->withType('application/json')
            ->withStringBody((string)json_encode($data));
    }
}
