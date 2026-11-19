<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\PermissionsTestTrait;
use App\Test\TestCase\UserFactoryTrait;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * CakeDC enables a Token authenticator (`?api_key=<users.api_token>`) on every URL. The app has no
 * API, so it is switched off: a leaked or guessed token must never open a session.
 */
class TokenAuthenticationTest extends TestCase
{
    use IntegrationTestTrait;
    use PermissionsTestTrait;
    use UserFactoryTrait;

    protected array $fixtures = ['app.Users', 'app.Roles', 'app.Permissions', 'app.RolesPermissions'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
    }

    public function testApiKeyQueryParameterDoesNotAuthenticate(): void
    {
        $this->createUser(['api_token' => 'leaked-token-0123456789', 'role' => 'admin']);

        $this->get('/?api_key=leaked-token-0123456789');

        $this->assertRedirectContains('/login');
    }
}
