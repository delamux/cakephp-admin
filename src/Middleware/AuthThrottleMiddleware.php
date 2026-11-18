<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Http\AuthThrottle;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use CakeDC\Auth\Authenticator\TwoFactorAuthenticator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Per-IP throttling of sign-in and account endpoints (POST only), 429 + Retry-After over the limit.
 * Runs after routing (needs the action) and before CSRF (to cut early).
 *
 * - `login`, `verify` (2FA code), passkey login: one shared bucket of failures. Password failures
 *   are counted by the CakeDC `afterLoginFailure` listener (Application::bootstrap()); a 2FA code is
 *   a failure if the second step did not pass; a passkey login if the response is not 200.
 * - Passkey options are refused when the bucket is exhausted but never counted: the login page
 *   requests them on every load (browser autofill).
 * - `register`, `requestResetPassword`, `resendTokenValidation`: every submission, own bucket.
 *
 * The client IP is REMOTE_ADDR as seen by nginx; behind a load balancer configure trusted proxies.
 */
class AuthThrottleMiddleware implements MiddlewareInterface
{
    private const LOGIN_ACTIONS = ['login', 'verify', 'passkeyLogin', 'passkeyOptions'];
    private const ACCOUNT_ACTIONS = ['register', 'requestResetPassword', 'resendTokenValidation'];

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request Request.
     * @param \Psr\Http\Server\RequestHandlerInterface $handler Handler.
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $action = $this->throttledAction($request);
        if ($action === null || !$request instanceof ServerRequest) {
            return $handler->handle($request);
        }
        $ip = (string)$request->clientIp();
        $bucket = in_array($action, self::LOGIN_ACTIONS, true) ? AuthThrottle::LOGIN : $action;
        $retryAfter = AuthThrottle::retryAfter($bucket, $ip);
        if ($retryAfter > 0) {
            return (new Response())
                ->withStatus(429)
                ->withHeader('Retry-After', (string)$retryAfter)
                ->withType('text/plain')
                ->withStringBody(__('Too many attempts. Wait a few minutes before trying again.'));
        }
        if ($bucket !== AuthThrottle::LOGIN) {
            AuthThrottle::recordAction($bucket, $ip);
        }

        $response = $handler->handle($request);
        if ($action === 'verify' && !$request->getSession()->check(TwoFactorAuthenticator::USER_SESSION_KEY)) {
            AuthThrottle::recordLoginFailure($ip);
        }
        if ($action === 'passkeyLogin' && $response->getStatusCode() !== 200) {
            AuthThrottle::recordLoginFailure($ip);
        }

        return $response;
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request Request.
     * @return string|null Throttled action, or null.
     */
    private function throttledAction(ServerRequestInterface $request): ?string
    {
        if (strtoupper($request->getMethod()) !== 'POST') {
            return null;
        }
        $params = (array)$request->getAttribute('params', []);
        if (empty($params['plugin']) && ($params['controller'] ?? null) === 'PasskeyLogin') {
            return match ($params['action'] ?? null) {
                'login' => 'passkeyLogin',
                'options' => 'passkeyOptions',
                default => null,
            };
        }
        if (($params['plugin'] ?? null) !== 'CakeDC/Users' || ($params['controller'] ?? null) !== 'Users') {
            return null;
        }
        $action = (string)($params['action'] ?? '');

        return in_array($action, ['login', 'verify', ...self::ACCOUNT_ACTIONS], true) ? $action : null;
    }
}
