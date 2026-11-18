<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Empty catalogue: tests run `permissions sync` or insert what they need.
 */
class PermissionsFixture extends TestFixture
{
    public string $table = 'permissions';

    public array $records = [];
}
