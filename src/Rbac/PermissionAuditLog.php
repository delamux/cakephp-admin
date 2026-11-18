<?php
declare(strict_types=1);

namespace App\Rbac;

use App\UseCase\Actor;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;

/**
 * Append-only audit log of roles/permissions changes (repository over `permission_audits`).
 *
 * Events: `grants_changed` (added/removed action keys of a role), `role_created`, `role_updated`
 * (details: changed fields `[old, new]`), `role_deleted`, `sync` (details: counts + reset),
 * `audit_purged` (details: deleted, days, before).
 */
class PermissionAuditLog
{
    private Table $audits;

    /**
     * @param \Cake\ORM\Table|null $audits PermissionAudits table.
     */
    public function __construct(?Table $audits = null)
    {
        $this->audits = $audits ?? TableRegistry::getTableLocator()->get('PermissionAudits');
    }

    /**
     * @param string $event Event name.
     * @param \App\UseCase\Actor $actor Who.
     * @param string|null $roleSlug Affected role.
     * @param list<string> $added Granted action keys.
     * @param list<string> $removed Revoked action keys.
     * @param array<string, mixed> $details Extra data.
     * @return void
     */
    public function record(
        string $event,
        Actor $actor,
        ?string $roleSlug = null,
        array $added = [],
        array $removed = [],
        array $details = [],
    ): void {
        $this->audits->saveOrFail($this->audits->newEntity([
            'user_id' => $actor->id,
            'username' => $actor->username,
            'event' => $event,
            'role_slug' => $roleSlug,
            'added' => $added ?: null,
            'removed' => $removed ?: null,
            'details' => $details ?: null,
        ], ['accessibleFields' => ['*' => true]]));
    }

    /**
     * @param \Cake\I18n\DateTime $before Cutoff.
     * @return int
     */
    public function countBefore(DateTime $before): int
    {
        return $this->audits->find()->where(['created <' => $before])->count();
    }

    /**
     * @param \Cake\I18n\DateTime $before Cutoff.
     * @return int Deleted entries.
     */
    public function deleteBefore(DateTime $before): int
    {
        return $this->audits->deleteAll(['created <' => $before]);
    }
}
