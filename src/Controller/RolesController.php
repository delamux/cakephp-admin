<?php
declare(strict_types=1);

namespace App\Controller;

use App\UseCase\Roles\ManageRoles;
use Cake\Core\Configure;
use Cake\Http\Response;

/**
 * Roles CRUD (superadmin only). Thin controller: the lifecycle (cache, audit) is in
 * App\UseCase\Roles\ManageRoles and the rules in App\Model\Table\RolesTable.
 *
 * @property \App\Model\Table\RolesTable $Roles
 */
class RolesController extends AppController
{
    /**
     * @return void
     */
    public function index(): void
    {
        $roles = $this->fetchTable('Roles')->find()->orderBy(['protected' => 'DESC', 'id' => 'ASC'])->all();
        $usersTable = $this->fetchTable((string)Configure::read('Users.table', 'Users'));
        $counts = $usersTable->find()
            ->select(['role', 'total' => $usersTable->find()->func()->count('*')])
            ->groupBy('role')
            ->disableHydration()
            ->all()
            ->combine('role', 'total')
            ->toArray();

        $this->set(['title' => __('Roles'), 'roles' => $roles, 'counts' => $counts]);
    }

    /**
     * @param \App\UseCase\Roles\ManageRoles $roles Use case.
     * @return \Cake\Http\Response|null
     */
    public function add(ManageRoles $roles): ?Response
    {
        $role = $roles->newRole();
        if ($this->request->is('post')) {
            $result = $roles->create((array)$this->request->getData(), $this->actor());
            if ($result->ok) {
                $name = $result->role->get('name');
                $this->Flash->success(__('Role {0} created. Assign its permissions in the permissions screen.', $name));

                return $this->redirect(['action' => 'index']);
            }
            $role = $result->role;
            $this->Flash->error($result->error ?? __('The role could not be saved.'));
        }
        $this->set(['title' => __('New role'), 'role' => $role, 'slugLocked' => false]);

        return null;
    }

    /**
     * @param \App\UseCase\Roles\ManageRoles $roles Use case.
     * @param string $id Role id.
     * @return \Cake\Http\Response|null
     */
    public function edit(ManageRoles $roles, string $id): ?Response
    {
        $role = $roles->get($id);
        if ($this->request->is(['post', 'put', 'patch'])) {
            $result = $roles->update($id, (array)$this->request->getData(), $this->actor());
            if ($result->ok) {
                $this->Flash->success(__('Role {0} saved.', $result->role->get('name')));

                return $this->redirect(['action' => 'index']);
            }
            $role = $result->role;
            $this->Flash->error($result->error ?? __('The role could not be saved.'));
        }
        $this->set([
            'title' => __('Edit {0}', $role->get('name')),
            'role' => $role,
            'slugLocked' => $roles->isSlugLocked($role),
        ]);

        return null;
    }

    /**
     * @param \App\UseCase\Roles\ManageRoles $roles Use case.
     * @param string $id Role id.
     * @return \Cake\Http\Response|null
     */
    public function delete(ManageRoles $roles, string $id): ?Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $result = $roles->delete($id, $this->actor());
        if ($result->ok) {
            $this->Flash->success(__('Role {0} deleted.', $result->role->get('name')));
        } else {
            $this->Flash->error($result->error ?? __('The role could not be deleted.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
