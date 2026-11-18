<?php
declare(strict_types=1);

namespace App\UseCase\Account;

use App\Account\ActivityLog;
use Cake\I18n\DateTime;
use InvalidArgumentException;

/**
 * Retention of the account activity log (personal data: IPs, browsers): deletes entries older than
 * N days, never less than MIN_RETENTION_DAYS.
 */
class PurgeAccountActivity
{
    /**
     * Never keep less activity than this (protects against `--days 0` wiping the log).
     */
    public const MIN_RETENTION_DAYS = 30;

    /**
     * @param \App\Account\ActivityLog $log Activity log.
     */
    public function __construct(private ActivityLog $log)
    {
    }

    /**
     * @param int $days Retention in days.
     * @return int Entries that would be deleted.
     */
    public function count(int $days): int
    {
        return $this->log->countBefore($this->cutoff($days));
    }

    /**
     * @param int $days Retention in days.
     * @return int Deleted entries.
     */
    public function execute(int $days): int
    {
        return $this->log->deleteBefore($this->cutoff($days));
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
