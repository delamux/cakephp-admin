<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Empty sessions table (App\Http\Session\AppDatabaseSession).
 */
class SessionsFixture extends TestFixture
{
    public string $table = 'sessions';

    public array $records = [];
}
