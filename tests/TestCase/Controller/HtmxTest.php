<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\PermissionsTestTrait;
use App\Test\TestCase\UserFactoryTrait;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * htmx integration (App\Controller\Component\HtmxComponent): htmx loaded with the CSP nonce and a
 * CSP-friendly config, CSRF token for its requests, partial rendering for htmx requests (flash
 * messages as toasts), full pages for boosted navigation, and the screens that use it.
 */
class HtmxTest extends TestCase
{
    use IntegrationTestTrait;
    use PermissionsTestTrait;
    use UserFactoryTrait;

    protected array $fixtures = ['app.Users', 'app.Roles', 'app.Permissions', 'app.RolesPermissions'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->signIn($this->createUser(['role' => 'superadmin', 'is_superuser' => true]));
    }

    private function body(): string
    {
        return (string)$this->_response->getBody();
    }

    public function testPagesLoadHtmxWithACspFriendlyConfig(): void
    {
        $this->get('/roles');

        $this->assertMatchesRegularExpression('#<script[^>]+htmx[^>]+nonce="[^"]+"#', $this->body());
        $this->assertMatchesRegularExpression('/<meta name="htmx-config" content="[^"]*allowEval&quot;:false/', $this->body());
        $this->assertStringContainsString('includeIndicatorStyles&quot;:false', $this->body(), 'No injected <style>');
        $this->assertMatchesRegularExpression('/<meta name="csrf-token" content="[^"]+"/', $this->body());
    }

    public function testHtmxRequestsGetThePageWithoutTheLayout(): void
    {
        $this->configRequest(['headers' => ['HX-Request' => 'true']]);
        $this->get('/roles');

        $this->assertResponseOk();
        $this->assertStringNotContainsString('<html', $this->body());
        $this->assertStringContainsString('Roles assigned to users', $this->body());
        $this->assertHeaderContains('Vary', 'HX-Request');
    }

    public function testBoostedNavigationGetsTheFullPage(): void
    {
        $this->configRequest(['headers' => ['HX-Request' => 'true', 'HX-Boosted' => 'true']]);
        $this->get('/roles');

        $this->assertStringContainsString('<html', $this->body());
    }

    public function testFlashMessagesOfHtmxResponsesAreToasts(): void
    {
        $this->session(['Flash' => ['flash' => [['message' => 'Permissions saved.', 'key' => 'flash', 'element' => 'flash/success', 'params' => []]]]]);
        $this->configRequest(['headers' => ['HX-Request' => 'true']]);
        $this->get('/roles');

        // Out-of-band: htmx inserts them into the page toaster even when hx-select takes another element
        $this->assertMatchesRegularExpression('/<div hx-swap-oob="beforeend:#toaster">\s*<div[^>]+class="toast"/', $this->body());
        $this->assertStringContainsString('data-category="success"', $this->body());
        $this->assertStringContainsString('Permissions saved.', $this->body());
    }

    public function testScreensUseHtmx(): void
    {
        $this->get('/profile');
        $this->assertMatchesRegularExpression('/<form[^>]+hx-post="[^"]*\/two-factor\/start"[^>]+hx-select="#two-factor-card"/', $this->body());

        $this->createUser(['username' => 'listed']);
        $this->get('/users/index');
        $this->assertMatchesRegularExpression('/id="users-list"[^>]*hx-boost="true"|hx-boost="true"[^>]*id="users-list"/', $this->body());

        $this->get('/permissions');
        $this->assertMatchesRegularExpression('/<form[^>]+id="permissions-form-editor"[^>]+hx-post=/', $this->body());
    }
}
