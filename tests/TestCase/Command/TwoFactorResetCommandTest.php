<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Model\Table\UsersTable;
use App\Test\TestCase\UserFactoryTrait;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\EmailTrait;
use Cake\TestSuite\TestCase;

/**
 * `bin/cake two_factor reset <username|email>`: the way out for a superadmin who lost their device.
 *
 * @link \App\Command\TwoFactorResetCommand
 */
class TwoFactorResetCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;
    use EmailTrait;
    use UserFactoryTrait;

    protected array $fixtures = ['app.Users'];

    private function users(): UsersTable
    {
        /** @var \App\Model\Table\UsersTable $users */
        $users = $this->fetchTable('Users');

        return $users;
    }

    public function testResetsByUsernameOrEmailWithoutEmail(): void
    {
        $byName = $this->createUser(['username' => 'root', 'role' => 'superadmin', 'is_superuser' => true]);
        $byEmail = $this->createUser(['email' => 'ops@example.test']);
        $this->users()->enableTotp((string)$byName->id, 'ABCDEFGHIJKLMNOP');
        $this->users()->enableTotp((string)$byEmail->id, 'ABCDEFGHIJKLMNOP');

        $this->exec('two_factor reset root');
        $this->assertExitSuccess();
        $this->assertOutputContains('Two-step verification removed for root.');
        $this->exec('two_factor reset ops@example.test');
        $this->assertExitSuccess();

        $this->assertSame([], $this->users()->twoFactorMethods((string)$byName->id));
        $this->assertSame([], $this->users()->twoFactorMethods((string)$byEmail->id));
        $this->assertNoMailSent();
    }

    public function testUnknownUserFailsAndNoTwoFactorIsANoOp(): void
    {
        $this->createUser(['username' => 'plain']);

        $this->exec('two_factor reset nobody');
        $this->assertExitError();
        $this->assertErrorContains('No user with that username or email.');

        $this->exec('two_factor reset plain');
        $this->assertExitSuccess();
        $this->assertOutputContains('plain has no two-step verification.');
    }
}
