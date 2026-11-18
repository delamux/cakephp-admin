<?php
declare(strict_types=1);

namespace App\Controller;

use App\UseCase\Users\ChangeUserRole;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\Validation\Validation;

/**
 * Role change from the users CRUD. Access: admins (RBAC) + the rules of ChangeUserRole.
 */
class UserRolesController extends AppController
{
    /**
     * POST /user-roles/{id} with `role`.
     *
     * @param \App\UseCase\Users\ChangeUserRole $changeRole Use case.
     * @param string $id User id.
     * @return \Cake\Http\Response|null
     */
    public function edit(ChangeUserRole $changeRole, string $id): ?Response
    {
        $this->request->allowMethod(['post', 'put', 'patch']);
        if (!Validation::uuid($id)) {
            throw new NotFoundException();
        }
        $result = $changeRole->execute(
            $this->request->getAttribute('identity')?->getOriginalData(),
            $id,
            (string)$this->request->getData('role'),
        );
        if ($result['error'] !== null) {
            $this->Flash->error($result['error']);
        } else {
            $this->Flash->success(__('{0} is now {1}.', $result['user']->get('username'), $result['roleName']));
        }

        return $this->redirect(['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'view', $id]);
    }
}
