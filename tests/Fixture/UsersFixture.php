<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Empty users table: each test creates exactly the users its business rule needs.
 */
class UsersFixture extends TestFixture
{
    // Use the real plugin Table class: the app disables fallback Table classes (Application::bootstrap()).
    public string $tableAlias = 'CakeDC/Users.Users';

    public array $records = [];
}
