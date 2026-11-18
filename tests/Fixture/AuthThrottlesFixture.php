<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Empty per-IP throttling counters.
 */
class AuthThrottlesFixture extends TestFixture
{
    public string $table = 'auth_throttles';

    public array $records = [];
}
