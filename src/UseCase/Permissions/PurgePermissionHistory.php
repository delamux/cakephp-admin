<?php
declare(strict_types=1);

namespace App\UseCase\Permissions;

use App\Rbac\PermissionAuditLog;
use App\UseCase\Actor;
use Cake\I18n\DateTime;
use InvalidArgumentException;

/**
 * Retention of the permissions history: deletes entries older than N days (never less than
 * MIN_RETENTION_DAYS) and records the purge itself, only when something was deleted.
 */
class PurgePermissionHistory
{
    /**
     * Never keep less history than this (protects against `--days 0` wiping the log).
     */
    public const MIN_RETENTION_DAYS = 30;

    /**
     * @param \App\Rbac\PermissionAuditLog $audit Audit log.
     */
    public function __construct(private PermissionAuditLog $audit)
    {
    }

    /**
     * @param int $days Retention in days.
     * @return int Entries that would be deleted.
     */
    public function count(int $days): int
    {
        return $this->audit->countBefore($this->cutoff($days));
    }

    /**
     * @param int $days Retention in days.
     * @param \App\UseCase\Actor $actor Who.
     * @return int Deleted entries.
     */
    public function execute(int $days, Actor $actor): int
    {
        $cutoff = $this->cutoff($days);
        $deleted = $this->audit->deleteBefore($cutoff);
        if ($deleted > 0) {
            $this->audit->record('audit_purged', $actor, details: [
                'deleted' => $deleted,
                'days' => $days,
                'before' => $cutoff->format('Y-m-d H:i:s'),
            ]);
        }

        return $deleted;
    }

    /**
     * @param int $days Retention in days.
     * @return \Cake\I18n\DateTime
     * @throws \InvalidArgumentException Below the minimum retention.
     */
    private function cutoff(int $days): DateTime
    {
        if ($days < self::MIN_RETENTION_DAYS) {
            throw new InvalidArgumentException(
                sprintf('The retention must be at least %d days.', self::MIN_RETENTION_DAYS),
            );
        }

        return DateTime::now()->subDays($days);
    }
}
