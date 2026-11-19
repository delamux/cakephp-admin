<?php
declare(strict_types=1);

namespace App\Command;

use App\UseCase\Maintenance\DailyMaintenance;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Throwable;

/**
 * `bin/cake maintenance`: the daily cron (Coolify Scheduled Task, crontab). Runs every daily task
 * of App\UseCase\Maintenance\DailyMaintenance and prints what each one did; exits with an error
 * if any failed (the others still run).
 */
class MaintenanceCommand extends Command
{
    /**
     * @param \App\UseCase\Maintenance\DailyMaintenance $maintenance Use case.
     */
    public function __construct(private DailyMaintenance $maintenance)
    {
        parent::__construct();
    }

    /**
     * @return string
     */
    public static function defaultName(): string
    {
        return 'maintenance';
    }

    /**
     * @param \Cake\Console\ConsoleOptionParser $parser Parser.
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription(
            'Daily maintenance (run it once a day): retention of the account activity and the permissions '
            . 'history, finished queue jobs, expired sessions and throttling counters.',
        );
    }

    /**
     * @param \Cake\Console\Arguments $args Arguments.
     * @param \Cake\Console\ConsoleIo $io IO.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $failed = false;
        foreach ($this->maintenance->run() as $task => $result) {
            if ($result instanceof Throwable) {
                $failed = true;
                $io->error(sprintf('%s: failed (%s)', $task, $result->getMessage()));
                continue;
            }
            $io->out(sprintf('%s: %d deleted', $task, $result));
        }

        return $failed ? static::CODE_ERROR : static::CODE_SUCCESS;
    }
}
