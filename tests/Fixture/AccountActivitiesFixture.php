<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Empty account activity log.
 */
class AccountActivitiesFixture extends TestFixture
{
    public string $table = 'account_activities';

    public array $records = [];
}
