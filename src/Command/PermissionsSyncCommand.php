<?php
declare(strict_types=1);

namespace App\Command;

use App\UseCase\Actor;
use App\UseCase\Permissions\SyncPermissions;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * `bin/cake permissions sync [--reset]`: syncs the permissions catalogue with the controllers and
 * grants the defaults (`App.permissions.defaults`) to newly discovered actions.
 */
class PermissionsSyncCommand extends Command
{
    /**
     * @param \App\UseCase\Permissions\SyncPermissions $sync Use case.
     */
    public function __construct(private SyncPermissions $sync)
    {
        parent::__construct();
    }

    /**
     * @return string
     */
    public static function defaultName(): string
    {
        return 'permissions sync';
    }

    /**
     * @param \Cake\Console\ConsoleOptionParser $parser Parser.
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Sync the permissions catalogue with the controllers (defaults for new actions).')
            ->addOption('reset', [
                'boolean' => true,
                'help' => 'Drop all grants and re-apply the default grants (App.permissions.defaults).',
            ]);
    }

    /**
     * @param \Cake\Console\Arguments $args Arguments.
     * @param \Cake\Console\ConsoleIo $io IO.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $reset = (bool)$args->getOption('reset');
        $result = $this->sync->execute(Actor::console(), $reset);

        $io->out(sprintf(
            'Permissions: %d actions found, %d added, %d orphaned, %d restored.',
            $result['found'],
            $result['added'],
            $result['orphaned'],
            $result['restored'],
        ));
        if ($reset) {
            $io->out('Default grants re-applied.');
        }

        return static::CODE_SUCCESS;
    }
}
