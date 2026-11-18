<?php
declare(strict_types=1);

namespace App\UseCase\Roles;

use App\Model\Table\RolesTable;
use App\Rbac\PermissionAuditLog;
use App\Rbac\PermissionsCache;
use App\UseCase\Actor;
use Cake\Datasource\EntityInterface;
use Cake\ORM\TableRegistry;

/**
 * Roles lifecycle: create / rename / delete. The rules (protected, in use, slug format) live in
 * RolesTable; this use case adds what surrounds a change: RBAC cache invalidation and audit.
 */
class ManageRoles
{
    private RolesTable $roles;

    /**
     * @param \App\Rbac\PermissionAuditLog $audit Audit log.
     * @param \App\Model\Table\RolesTable|null $roles Roles table.
     */
    public function __construct(private PermissionAuditLog $audit, ?RolesTable $roles = null)
    {
        /** @var \App\Model\Table\RolesTable $table */
        $table = $roles ?? TableRegistry::getTableLocator()->get('Roles');
        $this->roles = $table;
    }

    /**
     * @return \Cake\Datasource\EntityInterface
     */
    public function newRole(): EntityInterface
    {
        return $this->roles->newEmptyEntity();
    }

    /**
     * @param string $id Role id.
     * @return \Cake\Datasource\EntityInterface
     */
    public function get(string $id): EntityInterface
    {
        return $this->roles->get($id);
    }

    /**
     * The slug is stored on each user: it cannot change for protected or in-use roles.
     *
     * @param \Cake\Datasource\EntityInterface $role Role.
     * @return bool
     */
    public function isSlugLocked(EntityInterface $role): bool
    {
        return (bool)$role->get('protected') || $this->roles->isInUse((string)$role->getOriginal('slug'));
    }

    /**
     * @param array<string, mixed> $data Form data (only slug and name are used).
     * @param \App\UseCase\Actor $actor Who.
     * @return \App\UseCase\Roles\RoleResult
     */
    public function create(array $data, Actor $actor): RoleResult
    {
        $role = $this->roles->patchEntity($this->roles->newEmptyEntity(), $data, ['fields' => ['slug', 'name']]);
        if (!$this->roles->save($role)) {
            return RoleResult::of(false, $role);
        }
        PermissionsCache::clear();
        $this->audit->record('role_created', $actor, (string)$role->get('slug'), details: [
            'name' => (string)$role->get('name'),
        ]);

        return RoleResult::of(true, $role);
    }

    /**
     * @param string $id Role id.
     * @param array<string, mixed> $data Form data (only slug and name are used).
     * @param \App\UseCase\Actor $actor Who.
     * @return \App\UseCase\Roles\RoleResult
     */
    public function update(string $id, array $data, Actor $actor): RoleResult
    {
        $role = $this->roles->patchEntity($this->roles->get($id), $data, ['fields' => ['slug', 'name']]);
        $changes = [];
        foreach (['slug', 'name'] as $field) {
            if ($role->isDirty($field) && $role->getOriginal($field) !== $role->get($field)) {
                $changes[$field] = [$role->getOriginal($field), $role->get($field)];
            }
        }
        if (!$this->roles->save($role)) {
            return RoleResult::of(false, $role);
        }
        PermissionsCache::clear();
        if ($changes !== []) {
            $this->audit->record('role_updated', $actor, (string)$role->get('slug'), details: $changes);
        }

        return RoleResult::of(true, $role);
    }

    /**
     * @param string $id Role id.
     * @param \App\UseCase\Actor $actor Who.
     * @return \App\UseCase\Roles\RoleResult
     */
    public function delete(string $id, Actor $actor): RoleResult
    {
        $role = $this->roles->get($id);
        if (!$this->roles->delete($role)) {
            return RoleResult::of(false, $role);
        }
        PermissionsCache::clear();
        $this->audit->record('role_deleted', $actor, (string)$role->get('slug'), details: [
            'name' => (string)$role->get('name'),
        ]);

        return RoleResult::of(true, $role);
    }
}
