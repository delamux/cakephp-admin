<?php
declare(strict_types=1);

namespace App\Controller;

use App\UseCase\Permissions\PermissionMatrix;
use App\UseCase\Permissions\SyncPermissions;
use App\UseCase\Permissions\UpdateRolePermissions;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Response;

/**
 * Permissions per role: discovered actions with a select (deny, allow, rule). Superadmin only.
 * Thin controller: the work is done by the use cases in App\UseCase\Permissions.
 *
 * @property \App\Model\Table\PermissionsTable $Permissions
 */
class PermissionsController extends AppController
{
    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        // The selects are plain inputs (grants[action key]): their set varies with the catalogue
        $this->FormProtection->unlockFields(['grants']);
    }

    /**
     * GET: one tab per editable role (`?role=` opens that tab). POST: saves the permissions of the
     * submitted role (select value per action: deny, allow or a rule).
     *
     * @param \App\UseCase\Permissions\PermissionMatrix $matrix Read model.
     * @param \App\UseCase\Permissions\UpdateRolePermissions $update Use case.
     * @return \Cake\Http\Response|null
     */
    public function index(PermissionMatrix $matrix, UpdateRolePermissions $update): ?Response
    {
        $roles = $matrix->editableRoles();
        if ($this->request->is(['post', 'put'])) {
            $role = (string)$this->request->getData('role');
            if (!array_key_exists($role, $roles)) {
                throw new BadRequestException(__('Unknown role.'));
            }
            $update->execute($role, (array)$this->request->getData('grants'), $this->actor());
            $this->Flash->success(__('Permissions of {0} saved.', $roles[$role]));

            return $this->redirect(['action' => 'index', '?' => ['role' => $role]]);
        }

        $values = [];
        foreach (array_keys($roles) as $slug) {
            $values[$slug] = $matrix->values($slug);
        }
        $active = (string)$this->request->getQuery('role');

        $this->set([
            'title' => __('Permissions'),
            'roles' => $roles,
            'active' => array_key_exists($active, $roles) ? $active : (string)array_key_first($roles),
            'groups' => $matrix->groups(),
            'systemGroups' => $matrix->systemGroups(),
            'values' => $values,
            'orphans' => $matrix->orphans(),
        ]);

        return null;
    }

    /**
     * POST: discover the actions again (same as `bin/cake permissions sync`).
     *
     * @param \App\UseCase\Permissions\SyncPermissions $sync Use case.
     * @return \Cake\Http\Response|null
     */
    public function sync(SyncPermissions $sync): ?Response
    {
        $this->request->allowMethod(['post']);
        $result = $sync->execute($this->actor());
        $this->Flash->success(__(
            'Actions synced: {0} added, {1} orphaned, {2} restored.',
            $result['added'],
            $result['orphaned'],
            $result['restored'],
        ));

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Audit log of roles/permissions changes (latest first).
     *
     * @return void
     */
    public function audit(): void
    {
        $audits = $this->paginate(
            $this->fetchTable('PermissionAudits')->find()->orderBy(['created' => 'DESC', 'id' => 'DESC']),
            ['limit' => 50],
        );
        $this->set(['title' => __('Permissions history'), 'audits' => $audits]);
    }
}
