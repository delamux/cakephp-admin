<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Test\TestCase\UserFactoryTrait;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\EmailTrait;
use Cake\TestSuite\TestCase;

/**
 * `bin/cake users password_email <username|email>`: CakeDC's command generates the token but never
 * sends the email (it does not tell `resetToken()` the email type). The app command fixes it.
 */
class UsersPasswordEmailCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;
    use EmailTrait;
    use UserFactoryTrait;

    protected array $fixtures = ['app.Users'];

    public function testSendsTheResetPasswordEmailWithAFreshToken(): void
    {
        $user = $this->createUser(['username' => 'ana', 'email' => 'ana@example.test']);

        $this->exec('users password_email ana');

        $this->assertExitSuccess();
        $this->assertMailCount(1);
        $this->assertMailSentTo('ana@example.test');
        $token = (string)$this->fetchTable('Users')->get($user->id)->get('token');
        $this->assertNotSame('', $token);
        $this->assertMailContains($token);
    }

    public function testUnknownUserFails(): void
    {
        $this->exec('users password_email nobody');

        $this->assertExitError();
        $this->assertNoMailSent();
    }
}
