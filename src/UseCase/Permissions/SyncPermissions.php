<?php
declare(strict_types=1);

namespace App\UseCase\Permissions;

use App\Rbac\ActionDiscovery;
use App\Rbac\PermissionAuditLog;
use App\Rbac\PermissionsSynchronizer;
use App\UseCase\Actor;

/**
 * Discovers the controllers' actions, syncs the catalogue (defaults for new actions, or for all
 * with `$reset`) and audits it. Used by the "Sync actions" button and `bin/cake permissions sync`.
 */
class SyncPermissions
{
    /**
     * @param \App\Rbac\ActionDiscovery $discovery Action discovery.
     * @param \App\Rbac\PermissionsSynchronizer $sync Synchronizer.
     * @param \App\Rbac\PermissionAuditLog $audit Audit log.
     */
    public function __construct(
        private ActionDiscovery $discovery,
        private PermissionsSynchronizer $sync,
        private PermissionAuditLog $audit,
    ) {
    }

    /**
     * @param \App\UseCase\Actor $actor Who.
     * @param bool $reset Drop all grants and re-apply the defaults.
     * @return array{found: int, added: int, orphaned: int, restored: int}
     */
    public function execute(Actor $actor, bool $reset = false): array
    {
        $actions = $this->discovery->actions();
        $result = $this->sync->sync($actions, $reset);
        $this->audit->record('sync', $actor, details: $result + ['reset' => $reset]);

        return ['found' => count($actions)] + $result;
    }
}
