<?php
declare(strict_types=1);

namespace App\UseCase\Permissions;

use App\Rbac\PermissionAuditLog;
use App\Rbac\PermissionsSynchronizer;
use App\UseCase\Actor;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use InvalidArgumentException;

/**
 * Saves the permissions of one role (one tab of the screen): every editable action gets the
 * submitted select value (PermissionMatrix::DENY, ::ALLOW or a rule name). System rows are not in
 * the form and keep their grants; superadmin-only keys and rules that cannot decide an action are
 * dropped by the synchronizer. Audited only when something changed.
 */
class UpdateRolePermissions
{
    /**
     * @param \App\UseCase\Permissions\PermissionMatrix $matrix Read model (roles, locked rows).
     * @param \App\Rbac\PermissionsSynchronizer $sync Grants.
     * @param \App\Rbac\PermissionAuditLog $audit Audit log.
     */
    public function __construct(
        private PermissionMatrix $matrix,
        private PermissionsSynchronizer $sync,
        private PermissionAuditLog $audit,
    ) {
    }

    /**
     * @param string $roleSlug Role (superadmin and unknown roles are rejected).
     * @param array<mixed> $submitted action key => DENY | ALLOW | rule name.
     * @param \App\UseCase\Actor $actor Who.
     * @return array{added: list<string>, removed: list<string>} What changed (`key` or `key [rule]`).
     * @throws \InvalidArgumentException When the role is not editable.
     */
    public function execute(string $roleSlug, array $submitted, Actor $actor): array
    {
        if (!array_key_exists($roleSlug, $this->matrix->editableRoles())) {
            throw new InvalidArgumentException(sprintf('Role `%s` is not editable.', $roleSlug));
        }
        $locked = $this->matrix->lockedKeys();
        $current = $this->sync->rules()[$roleSlug] ?? [];

        // System rows keep what they have; editable rows take the submitted value (missing = DENY)
        $grants = array_intersect_key($current, $locked);
        foreach (array_keys($this->matrix->values($roleSlug)) as $key) {
            $value = $submitted[$key] ?? PermissionMatrix::DENY;
            if (!is_string($value) || $value === PermissionMatrix::DENY) {
                continue;
            }
            $grants[$key] = $value === PermissionMatrix::ALLOW ? null : $value;
        }

        $connection = ConnectionManager::get('default');
        assert($connection instanceof Connection);

        /** @var array{added: list<string>, removed: list<string>} */
        return $connection->transactional(function () use ($roleSlug, $grants, $actor): array {
            $diff = $this->sync->setGrants($roleSlug, $grants);
            if ($diff['added'] !== [] || $diff['removed'] !== []) {
                $this->audit->record('grants_changed', $actor, $roleSlug, $diff['added'], $diff['removed']);
            }

            return $diff;
        });
    }
}
