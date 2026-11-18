<?php
declare(strict_types=1);

namespace App\Command;

use App\UseCase\Account\PurgeAccountActivity;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use InvalidArgumentException;

/**
 * `bin/cake activity purge [--days N] [--dry-run]`: deletes account activity older than the
 * retention (`App.accountActivity.retentionDays`, default 180, minimum 30). Meant for a daily cron.
 */
class ActivityPurgeCommand extends Command
{
    /**
     * @param \App\UseCase\Account\PurgeAccountActivity $purge Use case.
     */
    public function __construct(private PurgeAccountActivity $purge)
    {
        parent::__construct();
    }

    /**
     * @return string
     */
    public static function defaultName(): string
    {
        return 'activity purge';
    }

    /**
     * @param \Cake\Console\ConsoleOptionParser $parser Parser.
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Delete account activity older than the retention period.')
            ->addOption('days', [
                'help' => 'Retention in days (default App.accountActivity.retentionDays, minimum '
                    . PurgeAccountActivity::MIN_RETENTION_DAYS . ').',
            ])
            ->addOption('dry-run', ['boolean' => true, 'help' => 'Only count what would be deleted.']);
    }

    /**
     * @param \Cake\Console\Arguments $args Arguments.
     * @param \Cake\Console\ConsoleIo $io IO.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $days = (int)($args->getOption('days') ?? Configure::read('App.accountActivity.retentionDays', 180));

        try {
            if ($args->getOption('dry-run')) {
                $io->out(sprintf(
                    '%d activity entries older than %d days would be deleted.',
                    $this->purge->count($days),
                    $days,
                ));

                return static::CODE_SUCCESS;
            }
            $deleted = $this->purge->execute($days);
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return static::CODE_ERROR;
        }
        $io->out(sprintf('%d activity entries older than %d days deleted.', $deleted, $days));

        return static::CODE_SUCCESS;
    }
}
