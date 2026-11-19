<?php
declare(strict_types=1);

namespace App\Controller;

use App\UseCase\Account\ActiveSessions;
use Cake\Http\Response;

/**
 * Own sessions from the profile ("Where you're signed in"): sign out one of them or every other one.
 * Always the signed-in user's sessions (system rule for every role in config/permissions.php).
 */
class AccountSessionsController extends AppController
{
    /**
     * @param \App\UseCase\Account\ActiveSessions $sessions Use case.
     * @param string $key Session key (SHA-256 of its id) from the list.
     * @return \Cake\Http\Response|null
     */
    public function signOut(ActiveSessions $sessions, string $key = ''): ?Response
    {
        $this->request->allowMethod(['post']);
        if ($sessions->signOut($this->userId(), $key, $this->request->getSession()->id())) {
            $this->Flash->success(__('Session signed out.'));
        } else {
            $this->Flash->error(__('That session no longer exists.'));
        }

        return $this->redirect($this->profile());
    }

    /**
     * @param \App\UseCase\Account\ActiveSessions $sessions Use case.
     * @return \Cake\Http\Response|null
     */
    public function signOutOthers(ActiveSessions $sessions): ?Response
    {
        $this->request->allowMethod(['post']);
        $sessions->signOutOthers($this->userId(), $this->request->getSession()->id());
        $this->Flash->success(__('Signed out of every other session.'));

        return $this->redirect($this->profile());
    }

    /**
     * @return string
     */
    private function userId(): string
    {
        return (string)($this->request->getAttribute('identity')?->getIdentifier() ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(): array
    {
        return ['plugin' => 'CakeDC/Users', 'prefix' => false, 'controller' => 'Users', 'action' => 'profile'];
    }
}
