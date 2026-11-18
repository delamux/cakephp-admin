<?php
declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use App\Http\AuthThrottle;
use App\Test\TestCase\PermissionsTestTrait;
use App\Test\TestCase\UserFactoryTrait;
use App\Test\TestCase\WebauthnTestTrait;
use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Security setup of book.cakephp.org/5.x/security on real responses: security headers, CSP with
 * nonces (report-only in dev), HTTPS + HSTS when enforced, hardened CSRF cookie, FormProtection on
 * the app's forms, encrypted "remember me" cookie and the client IP behind trusted proxies.
 */
class SecurityTest extends TestCase
{
    use IntegrationTestTrait;
    use PermissionsTestTrait;
    use UserFactoryTrait;
    use WebauthnTestTrait;

    protected array $fixtures = ['app.Users', 'app.Roles', 'app.Permissions', 'app.RolesPermissions', 'app.AuthThrottles'];

    /**
     * @var array<string, mixed>
     */
    private array $config = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
    }

    protected function tearDown(): void
    {
        foreach ($this->config as $key => $value) {
            Configure::write($key, $value);
        }
        parent::tearDown();
    }

    /**
     * Config for the next requests (config/app.php is loaded once per process: Configure sticks).
     */
    private function configure(string $key, mixed $value): void
    {
        if (!array_key_exists($key, $this->config)) {
            $this->config[$key] = Configure::read($key);
        }
        Configure::write($key, $value);
    }

    public function testSecurityHeadersOnEveryPage(): void
    {
        $this->get('/login');

        $this->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertHeader('X-Frame-Options', 'sameorigin');
        $this->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertHeader('X-Download-Options', 'noopen');
        $policy = $this->_response->getHeaderLine('Permissions-Policy');
        $this->assertStringContainsString('camera=()', $policy);
        $this->assertStringContainsString('geolocation=()', $policy);
        $this->assertStringNotContainsString('publickey-credentials', $policy, 'WebAuthn must stay allowed');
    }

    public function testCspIsReportOnlyInDevAndEnforcedInProduction(): void
    {
        $this->configure('Security.csp.reportOnly', true);
        $this->get('/login');
        $this->assertNotEmpty($this->_response->getHeaderLine('Content-Security-Policy-Report-Only'));
        $this->assertSame('', $this->_response->getHeaderLine('Content-Security-Policy'));

        $this->configure('Security.csp.reportOnly', false);
        $this->get('/login');
        $csp = $this->_response->getHeaderLine('Content-Security-Policy');
        foreach (["default-src 'self'", "object-src 'none'", "frame-ancestors 'self'", "base-uri 'self'", "form-action 'self'"] as $directive) {
            $this->assertStringContainsString($directive, $csp);
        }
        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
    }

    public function testEveryScriptOfThePageCarriesTheNonceAndThereIsNoInlineJs(): void
    {
        $this->configure('Security.csp.reportOnly', false);
        $this->get('/login');

        preg_match("/script-src[^;]*'nonce-([^']+)'/", $this->_response->getHeaderLine('Content-Security-Policy'), $m);
        $this->assertNotEmpty($m[1] ?? null, 'Nonce in script-src');
        $body = (string)$this->_response->getBody();
        preg_match_all('/<script\b[^>]*>/i', $body, $scripts);
        $this->assertNotEmpty($scripts[0]);
        foreach ($scripts[0] as $tag) {
            $this->assertStringContainsString('nonce="' . $m[1] . '"', $tag, $tag);
        }
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+="/i', $body, 'No inline event handlers');
    }

    public function testAppPagesHaveNoInlineEventHandlers(): void
    {
        $this->session(['Auth' => $this->createUser(['role' => 'superadmin', 'is_superuser' => true])]);
        $this->createUser(['username' => 'listed']);

        foreach (['/', '/users/index', '/roles', '/permissions', '/permissions/audit', '/profile'] as $url) {
            $this->get($url);
            $this->assertResponseOk($url);
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+="/i', (string)$this->_response->getBody(), $url);
        }
    }

    public function testHttpsIsEnforcedWithHstsWhenEnabled(): void
    {
        $this->configure('Security.https.enforce', true);

        $this->get('/login');
        $this->assertResponseCode(301);
        $this->assertHeaderContains('Location', 'https://');

        $this->configRequest(['environment' => ['HTTPS' => 'on']]);
        $this->get('/login');
        $this->assertResponseOk();
        $this->assertHeaderContains('Strict-Transport-Security', 'max-age=31536000');
    }

    public function testCsrfCookieIsHttpOnlySameSiteAndSecureOnHttps(): void
    {
        $this->configRequest(['environment' => ['HTTPS' => 'on']]);
        $this->get('/login');

        $cookie = $this->_response->getCookie('csrfToken');
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie['httponly']);
        $this->assertTrue($cookie['secure']);
        $this->assertSame('Lax', $cookie['samesite']);
    }

    public function testAppFormsAreProtectedButJsonEndpointsAreNot(): void
    {
        $this->enableCsrfToken();
        $this->session(['Auth' => $this->createUser(['role' => 'superadmin', 'is_superuser' => true])]);

        $this->post('/roles/add', ['slug' => 'tampered', 'name' => 'Tampered']);
        $this->assertResponseCode(400, 'No FormProtection token');
        $this->assertFalse($this->fetchTable('Roles')->exists(['slug' => 'tampered']));

        $this->enableSecurityToken();
        $this->post('/roles/add', ['slug' => 'support', 'name' => 'Support']);
        $this->assertTrue($this->fetchTable('Roles')->exists(['slug' => 'support']), 'With the token it works');

        $this->onWebauthnHost();
        $this->post('/passkey/options');
        $this->assertResponseOk('JSON endpoint (fetch, no form token)');
    }

    public function testRememberMeCookieIsEncrypted(): void
    {
        $this->enableCsrfToken();
        $this->onWebauthnHost();
        $user = $this->createUser(['username' => 'remembered']);
        $this->storeKey((string)$user->id);
        $this->post('/passkey/options');
        $challenge = json_decode((string)$this->_response->getBody(), true)['challenge'];
        $this->session($_SESSION ?? []);

        $this->post('/passkey/login', $this->assertion((string)base64_decode(strtr($challenge, '-_', '+/'), true), (string)$user->id) + ['remember_me' => '1']);

        $setCookie = $this->_response->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('CookieAuth=', $setCookie);
        $this->assertStringNotContainsString('remembered', urldecode($setCookie), 'Username not readable in the cookie');
        $this->assertStringContainsString('CookieAuth=Q2FrZQ', $setCookie, 'Encrypted by EncryptedCookieMiddleware');
    }

    public function testClientIpComesFromTrustedProxiesOnly(): void
    {
        $this->configure('Security.trustedProxies', ['10.0.0.5']);
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $this->configRequest(['environment' => ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '198.51.100.20']]);
        $this->post('/login', ['username' => 'nobody', 'password' => 'wrong']);
        // configRequest() merges recursively: start a clean request
        $this->_request = [];
        $this->configRequest(['environment' => ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '198.51.100.99']]);
        $this->post('/login', ['username' => 'nobody', 'password' => 'wrong']);

        $count = fn(string $ip): int => AuthThrottle::hits(AuthThrottle::LOGIN, $ip);
        $this->assertSame(1, $count('198.51.100.20'), 'Forwarded client behind the trusted proxy');
        $this->assertSame(1, $count('203.0.113.9'), 'Untrusted peer: its own address');
        $this->assertSame(0, $count('198.51.100.99'), 'Spoofed header ignored');
    }

    /**
     * Coolify/Traefik: the proxy (nginx container) address changes on every deploy, so a private
     * range is trusted. Its X-Forwarded-For and X-Forwarded-Proto are honoured; anyone else's are not.
     */
    public function testTrustedProxiesAcceptCidrRangesAndTheForwardedScheme(): void
    {
        $this->configure('Security.trustedProxies', ['10.0.0.0/8', 'fd00::/8']);
        $this->configure('Security.https.enforce', true);
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $behindProxy = ['REMOTE_ADDR' => '10.4.0.7', 'HTTP_X_FORWARDED_FOR' => '198.51.100.30', 'HTTP_X_FORWARDED_PROTO' => 'https'];
        $this->configRequest(['environment' => $behindProxy]);
        $this->get('/login');
        $this->assertResponseOk('TLS ended at the proxy: no redirect loop');
        $this->assertHeaderContains('Strict-Transport-Security', 'max-age=');

        $this->post('/login', ['username' => 'nobody', 'password' => 'wrong']);
        $this->_request = [];
        $this->configRequest(['environment' => ['REMOTE_ADDR' => 'fd12::5', 'HTTP_X_FORWARDED_FOR' => '198.51.100.31', 'HTTP_X_FORWARDED_PROTO' => 'https']]);
        $this->post('/login', ['username' => 'nobody', 'password' => 'wrong']);
        $this->_request = [];
        $this->configRequest(['environment' => ['REMOTE_ADDR' => '203.0.113.10', 'HTTP_X_FORWARDED_FOR' => '198.51.100.32', 'HTTP_X_FORWARDED_PROTO' => 'https']]);
        $this->get('/login');
        $this->assertResponseCode(301, 'Outside the range: the forwarded scheme is not believed');

        $count = fn(string $ip): int => AuthThrottle::hits(AuthThrottle::LOGIN, $ip);
        $this->assertSame(1, $count('198.51.100.30'), 'IPv4 range');
        $this->assertSame(1, $count('198.51.100.31'), 'IPv6 range');
    }
}
