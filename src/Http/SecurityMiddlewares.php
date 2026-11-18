<?php
declare(strict_types=1);

namespace App\Http;

use App\Middleware\TrustedProxiesMiddleware;
use Cake\Core\Configure;
use Cake\Http\Middleware\CspMiddleware;
use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\Http\Middleware\EncryptedCookieMiddleware;
use Cake\Http\Middleware\HttpsEnforcerMiddleware;
use Cake\Http\Middleware\SecurityHeadersMiddleware;
use Cake\Utility\Security;
use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The security middlewares of book.cakephp.org/5.x/security, built from `Security.*` config
 * (config/app.php, environment variables). Order in Application::middleware().
 */
final class SecurityMiddlewares
{
    /**
     * Client IP behind a load balancer: X-Forwarded-For only from `Security.trustedProxies`.
     *
     * @return \Psr\Http\Server\MiddlewareInterface
     */
    public static function trustedProxies(): MiddlewareInterface
    {
        /** @var list<string> $proxies */
        $proxies = array_values(array_map('strval', (array)Configure::read('Security.trustedProxies', [])));

        return new TrustedProxiesMiddleware($proxies);
    }

    /**
     * HTTP -> HTTPS redirect + HSTS (`Security.https.enforce`, production). Null when disabled.
     *
     * @return \Cake\Http\Middleware\HttpsEnforcerMiddleware|null
     */
    public static function httpsEnforcer(): ?HttpsEnforcerMiddleware
    {
        if (!Configure::read('Security.https.enforce')) {
            return null;
        }

        return new HttpsEnforcerMiddleware([
            // Enforcement is explicit (HTTPS_ENFORCE): also with debug on
            'disableOnDebug' => false,
            // Already applied by trustedProxies() (earlier in the queue, with CIDR support)
            'trustedProxies' => null,
            'hsts' => [
                'maxAge' => (int)Configure::read('Security.https.hstsMaxAge', 31536000),
                'includeSubDomains' => (bool)Configure::read('Security.https.hstsIncludeSubDomains', false),
                'preload' => false,
            ],
        ]);
    }

    /**
     * @return \Cake\Http\Middleware\SecurityHeadersMiddleware
     */
    public static function securityHeaders(): SecurityHeadersMiddleware
    {
        return (new SecurityHeadersMiddleware())
            ->noSniff()
            ->noOpen()
            ->setXFrameOptions(SecurityHeadersMiddleware::SAMEORIGIN)
            ->setReferrerPolicy(SecurityHeadersMiddleware::STRICT_ORIGIN_WHEN_CROSS_ORIGIN)
            ->setCrossDomainPolicy(SecurityHeadersMiddleware::NONE)
            // Features the admin never uses; WebAuthn (publickey-credentials-*) stays allowed
            ->setPermissionsPolicy('camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
    }

    /**
     * Content-Security-Policy with nonces for scripts and styles (HtmlHelper adds them). Nothing
     * inline is allowed; in dev (Tailwind browser build, CDN) the same policy is report-only.
     *
     * @return \Cake\Http\Middleware\CspMiddleware
     */
    public static function csp(): CspMiddleware
    {
        $cdn = !Configure::read('BasecoatTheme.compiledAssets');
        $allow = fn(array $hosts): array => $cdn ? $hosts : [];
        $policy = [
            'default-src' => ['self' => true],
            'script-src' => ['self' => true, 'allow' => $allow(['https://cdn.jsdelivr.net'])],
            'style-src' => [
                'self' => true,
                'allow' => $allow(['https://cdn.jsdelivr.net', 'https://fonts.googleapis.com']),
            ],
            'font-src' => ['self' => true, 'allow' => $allow(['https://fonts.gstatic.com'])],
            // data: for the TOTP QR code
            'img-src' => ['self' => true, 'data' => true],
            'connect-src' => ['self' => true, 'allow' => $allow(['https://cdn.jsdelivr.net'])],
            'object-src' => [],
            'base-uri' => ['self' => true],
            'form-action' => ['self' => true],
            'frame-ancestors' => ['self' => true],
            'report-only' => (bool)Configure::read('Security.csp.reportOnly'),
            'upgrade-insecure-requests' => (bool)Configure::read('Security.https.enforce'),
        ];

        return new CspMiddleware($policy, ['scriptNonce' => true, 'styleNonce' => true]);
    }

    /**
     * CSRF cookie: httponly (the token reaches JS through the page), SameSite=Lax, secure on HTTPS.
     * Built per request so http://localhost keeps working in dev.
     *
     * @return \Closure
     */
    public static function csrf(): Closure
    {
        return function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
            $csrf = new CsrfProtectionMiddleware([
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => $request->getUri()->getScheme() === 'https',
            ]);

            return $csrf->process($request, $handler);
        };
    }

    /**
     * "Remember me" cookie encrypted (AES) with `Security.cookieKey` (COOKIE_KEY, or derived from
     * the salt): its content (username, token hash) is not readable or forgeable by the client.
     *
     * @return \Cake\Http\Middleware\EncryptedCookieMiddleware
     */
    public static function encryptedCookies(): EncryptedCookieMiddleware
    {
        $key = (string)Configure::read('Security.cookieKey');
        if (strlen($key) < 32) {
            $key = hash('sha256', 'cookie-key:' . Security::getSalt());
        }

        return new EncryptedCookieMiddleware(['CookieAuth'], $key);
    }
}
