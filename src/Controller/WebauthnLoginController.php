<?php
declare(strict_types=1);

namespace App\Controller;

use App\Account\ActivityLog;
use App\Webauthn\AuthenticateAdapter;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Log\Log;
use Cake\Routing\Router;
use CakeDC\Auth\Authenticator\TwoFactorAuthenticator;
use Throwable;

/**
 * Second login step with the security key. Replaces CakeDC's `webauthn2faAuthenticate`, which builds
 * its own adapter inside the action (so the app's algorithms could not be used). On success the user
 * is left in `TwoFactorAuthenticator.User`, and CakeDC's `TwoFactor` authenticator opens the session
 * when the browser goes back to the login action. Public (bypassAuth): the user is not logged in yet.
 */
class WebauthnLoginController extends AppController
{
    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        // JSON endpoints called by webroot/js/webauthn.js (fetch, no form token; CSRF header still checked)
        $this->FormProtection->unlockActions(['authenticate']);
    }

    /**
     * @return \Cake\Http\Response
     */
    public function authenticate(): Response
    {
        $this->request->allowMethod(['post']);
        try {
            $adapter = new AuthenticateAdapter($this->request);
            $adapter->verifyResponse();
        } catch (Throwable $e) {
            Log::warning('WebAuthn second step rejected: ' . $e->getMessage());
            // Password was right, key was not: worth seeing in the account activity
            $pendingId = $this->request->getSession()->read('Webauthn2fa.User.id');
            if (is_scalar($pendingId) && (string)$pendingId !== '') {
                (new ActivityLog())->record((string)$pendingId, ActivityLog::TWO_FACTOR_FAILED, $this->request);
            }

            return $this->response->withStatus(422)->withType('application/json')->withStringBody((string)json_encode([
                'success' => false,
                'message' => __('The key could not be verified. Try again.'),
            ]));
        }
        $session = $this->request->getSession();
        $session->delete('Webauthn2fa');
        $session->write(TwoFactorAuthenticator::USER_SESSION_KEY, $adapter->getUser());
        $loginAction = (array)Configure::read('Auth.AuthenticationComponent.loginAction')
            + ['?' => $this->request->getQueryParams()];

        return $this->response->withType('application/json')->withStringBody((string)json_encode([
            'success' => true,
            'redirectUrl' => Router::url($loginAction),
        ]));
    }
}
