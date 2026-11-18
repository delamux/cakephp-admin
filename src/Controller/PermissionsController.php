<?php
declare(strict_types=1);

namespace App\Controller;

use App\UseCase\Permissions\PermissionMatrix;
use App\UseCase\Permissions\SyncPermissions;
use App\UseCase\Permissions\UpdateRolePermissions;
use Cake\Http\Response;

/**
 * Permissions matrix: discovered actions × roles (allow-list). Superadmin only.
 * Thin controller: the work is done by the use cases in App\UseCase\Permissions.
 */
class PermissionsController extends AppController
{
    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        // The matrix checkboxes are plain inputs (grants[role][]): their set varies with the catalogue
        $this->FormProtection->unlockFields(['grants']);
    }

    /**
     * GET: matrix. POST: replaces the grants of every editable role with the submitted ones.
     *
     * @param \App\UseCase\Permissions\PermissionMatrix $matrix Read model.
     * @param \App\UseCase\Permissions\UpdateRolePermissions $update Use case.
     * @return \Cake\Http\Response|null
     */
    public function index(PermissionMatrix $matrix, UpdateRolePermissions $update): ?Response
    {
        if ($this->request->is(['post', 'put'])) {
            $update->execute((array)$this->request->getData('grants'), $this->actor());
            $this->Flash->success(__('Permissions saved.'));

            return $this->redirect(['action' => 'index']);
        }

        $this->set([
            'title' => __('Permissions'),
            'roles' => $matrix->editableRoles(),
            'groups' => $matrix->groups(),
            'grants' => $matrix->grants(),
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
