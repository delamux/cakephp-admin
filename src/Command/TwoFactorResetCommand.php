<?php
declare(strict_types=1);

namespace App\Command;

use App\UseCase\Actor;
use App\UseCase\TwoFactor\TwoFactorSettings;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;

/**
 * `bin/cake two_factor reset <username|email>`: removes both 2FA methods of an account, e.g. a
 * superadmin who lost their device (the UI never resets another superadmin). No email is sent.
 */
class TwoFactorResetCommand extends Command
{
    /**
     * @param \App\UseCase\TwoFactor\TwoFactorSettings $settings Use case.
     */
    public function __construct(private TwoFactorSettings $settings)
    {
        parent::__construct();
    }

    /**
     * @return string
     */
    public static function defaultName(): string
    {
        return 'two_factor reset';
    }

    /**
     * @param \Cake\Console\ConsoleOptionParser $parser Parser.
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Remove the two-step verification (authenticator app and keys) of an account.')
            ->addArgument('user', ['help' => 'Username or email.', 'required' => true]);
    }

    /**
     * @param \Cake\Console\Arguments $args Arguments.
     * @param \Cake\Console\ConsoleIo $io IO.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $login = (string)$args->getArgument('user');
        $user = $this->fetchTable((string)Configure::read('Users.table', 'Users'))->find()
            ->select(['id', 'username'])
            ->where(['OR' => ['username' => $login, 'email' => $login]])
            ->first();
        if ($user === null) {
            $io->error('No user with that username or email.');

            return static::CODE_ERROR;
        }
        $username = (string)$user->get('username');
        if (!$this->settings->reset((string)$user->get('id'), notify: false, actor: Actor::console())) {
            $io->out(sprintf('%s has no two-step verification.', $username));

            return static::CODE_SUCCESS;
        }
        $io->out(sprintf('Two-step verification removed for %s.', $username));

        return static::CODE_SUCCESS;
    }
}
