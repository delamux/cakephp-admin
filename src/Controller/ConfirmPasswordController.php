<?php
declare(strict_types=1);

namespace App\Controller;

use App\Account\SessionGuard;
use App\UseCase\Account\ConfirmPassword;
use Cake\Http\Response;

/**
 * "Confirm your password" before a sensitive action (App\Middleware\ConfirmPasswordMiddleware sends the
 * user here with `?redirect=<local path>`). Any signed-in user (system rule in config/permissions.php);
 * wrong passwords count in the per-IP login limit.
 */
class ConfirmPasswordController extends AppController
{
    /**
     * @param \App\UseCase\Account\ConfirmPassword $confirm Use case.
     * @return \Cake\Http\Response|null
     */
    public function index(ConfirmPassword $confirm): ?Response
    {
        $redirect = SessionGuard::safeRedirect($this->request->getQuery('redirect'));
        if ($this->request->is('post')) {
            $password = $this->request->getData('password');
            $ip = (string)$this->request->clientIp();
            if ($confirm->execute((string)$this->actor()->id, is_string($password) ? $password : '', $ip)) {
                SessionGuard::confirmed($this->request->getSession());

                return $this->redirect($redirect);
            }
            $this->Flash->error(__('The password is not correct.'));
        }
        $this->set(['title' => __('Confirm your password'), 'redirect' => $redirect]);

        return null;
    }
}
