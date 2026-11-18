<?php
declare(strict_types=1);

namespace App\UseCase\Permissions;

use App\Rbac\PermissionAuditLog;
use App\Rbac\PermissionsSynchronizer;
use App\UseCase\Actor;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;

/**
 * Saves the permissions matrix: replaces the grants of every editable role with the submitted
 * ones, keeping the locked rows (never submitted) and auditing only the roles that changed.
 * Superadmin is never editable; superadmin-only keys are filtered by the synchronizer.
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
     * @param array<mixed> $submitted role slug => list of action keys.
     * @param \App\UseCase\Actor $actor Who.
     * @return array<string, array{added: list<string>, removed: list<string>}> Changes per role.
     */
    public function execute(array $submitted, Actor $actor): array
    {
        $locked = $this->matrix->lockedKeys();
        $current = $this->sync->grants();

        $connection = ConnectionManager::get('default');
        assert($connection instanceof Connection);

        /** @var array<string, array{added: list<string>, removed: list<string>}> */
        return $connection->transactional(function () use ($submitted, $actor, $locked, $current) {
            $changes = [];
            foreach (array_keys($this->matrix->editableRoles()) as $slug) {
                $keep = array_filter($current[$slug] ?? [], fn(string $key): bool => isset($locked[$key]));
                $keys = array_values(array_unique(array_merge(
                    array_map('strval', (array)($submitted[$slug] ?? [])),
                    $keep,
                )));
                $diff = $this->sync->setGrants($slug, $keys);
                if ($diff['added'] !== [] || $diff['removed'] !== []) {
                    $this->audit->record('grants_changed', $actor, $slug, $diff['added'], $diff['removed']);
                    $changes[$slug] = $diff;
                }
            }

            return $changes;
        });
    }
}
