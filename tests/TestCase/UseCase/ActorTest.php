<?php
declare(strict_types=1);

namespace App\Test\TestCase\UseCase;

use App\UseCase\Actor;
use ArrayObject;
use Cake\TestSuite\TestCase;

/**
 * Who performs a use case: a logged-in user or the console.
 */
class ActorTest extends TestCase
{
    public function testFromUserTakesIdAndUsername(): void
    {
        $actor = Actor::fromUser(new ArrayObject(['id' => 'u-1', 'username' => 'root', 'email' => 'x@y.z']));

        $this->assertSame('u-1', $actor->id);
        $this->assertSame('root', $actor->username);
        $this->assertFalse($actor->isConsole());
    }

    public function testUserWithoutUsernameFallsBackToItsId(): void
    {
        $this->assertSame('u-2', Actor::fromUser(['id' => 'u-2'])->username);
    }

    public function testConsoleHasNoUserId(): void
    {
        $actor = Actor::console();

        $this->assertNull($actor->id);
        $this->assertSame('console', $actor->username);
        $this->assertTrue($actor->isConsole());
    }
}
