<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Empty audit log.
 */
class PermissionAuditsFixture extends TestFixture
{
    public string $table = 'permission_audits';

    public array $records = [];
}
