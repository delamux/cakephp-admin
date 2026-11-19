<?php
declare(strict_types=1);

namespace App\Controller;

use App\UseCase\Users\InviteUser;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;

/**
 * Invitations (App\UseCase\Users\InviteUser): `add` and `resend` for whoever has the permission
 * (admins by default; the role rules decide which roles may be given), `accept` is public: the
 * emailed link to choose a password (system rule in config/permissions.php, rate limited per IP).
 */
class InvitationsController extends AppController
{
    /**
     * @param \App\UseCase\Users\InviteUser $invitations Use case.
     * @return \Cake\Http\Response|null
     */
    public function add(InviteUser $invitations): ?Response
    {
        $actor = $this->identity();
        $error = null;
        if ($this->request->is('post')) {
            $data = (array)$this->request->getData();
            $error = $invitations->invite($actor, $data);
            if ($error === null) {
                $this->Flash->success(__('Invitation sent to {0}.', mb_strtolower(trim((string)$data['email']))));

                return $this->redirect($this->usersIndex());
            }
        }
        $this->set([
            'error' => $error,
            'roles' => $invitations->assignableRoles($actor),
        ]);

        return null;
    }

    /**
     * @param \App\UseCase\Users\InviteUser $invitations Use case.
     * @param string $id Invited user.
     * @return \Cake\Http\Response|null
     */
    public function resend(InviteUser $invitations, string $id): ?Response
    {
        $this->request->allowMethod(['post']);
        $error = $invitations->resend($this->identity(), $id);
        if ($error !== null) {
            $this->Flash->error($error);
        } else {
            $email = $this->fetchTable('Users')->get($id)->get('email');
            $this->Flash->success(__('Invitation sent to {0}.', (string)$email));
        }

        return $this->redirect($this->usersIndex());
    }

    /**
     * @param \App\UseCase\Users\InviteUser $invitations Use case.
     * @param string $token Token from the emailed link.
     * @return \Cake\Http\Response|null
     */
    public function accept(InviteUser $invitations, string $token = ''): ?Response
    {
        $user = $invitations->pending($token);
        if ($user === null) {
            throw new NotFoundException(__('This invitation link is not valid or has expired.'));
        }
        $error = null;
        if ($this->request->is('post')) {
            $error = $invitations->accept(
                $token,
                (string)$this->request->getData('password'),
                (string)$this->request->getData('password_confirm'),
            );
            if ($error === null) {
                $this->Flash->success(__('Your account is ready. Sign in with your new password.'));

                return $this->redirect(['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'login']);
            }
        }
        $this->set(['email' => (string)$user->get('email'), 'error' => $error]);

        return null;
    }

    /**
     * @return \ArrayAccess<string, mixed>|array<string, mixed>
     */
    private function identity(): mixed
    {
        return $this->request->getAttribute('identity')?->getOriginalData() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function usersIndex(): array
    {
        return ['plugin' => 'CakeDC/Users', 'prefix' => false, 'controller' => 'Users', 'action' => 'index'];
    }
}
