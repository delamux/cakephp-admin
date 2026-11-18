<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Empty Queue plugin table (schema from its migrations, through the plugin's Table class).
 */
class QueueProcessesFixture extends TestFixture
{
    public string $tableAlias = 'Queue.QueueProcesses';

    public array $records = [];
}
