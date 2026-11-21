<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Account\SessionGuard;
use Authentication\AuthenticationService;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Cake\ORM\TableRegistry;
use CakeDC\Users\Model\Entity\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Keeps the session identity in sync with the database, BEFORE authorization runs.
 *
 * The session stores the whole user entity, so without this a demoted, deleted or deactivated
 * account would keep its old access (RBAC included) until it signs out. Runs right after the
 * AuthenticationMiddleware (App\Loader\MiddlewareQueueLoader) and costs 1 query by primary key:
 * - session ended: the identity is cleared (session + remember-me cookie) and the request is flagged
 *   with `sessionEnded` (App\Middleware\UnauthorizedHandler\AppRedirectHandler then says "Your
 *   session has ended") when the account is gone or inactive, the session is older than its maximum
 *   lifetime, or the account rotated its sign-out key since the session started (password change or
 *   reset, "sign out of every other session"): see App\Account\SessionGuard,
 * - role or superuser flag changed: the fresh user becomes the identity (and is persisted).
 *
 * It also stamps the session the first time it sees it signed in (start time, sign-out key) and drops
 * those stamps when nobody is signed in.
 */
class RefreshIdentityMiddleware implements MiddlewareInterface
{
    public const SESSION_ENDED = 'sessionEnded';

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request Request.
     * @param \Psr\Http\Server\RequestHandlerInterface $handler Next handler.
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $identity = $request->getAttribute('identity');
        $service = $request->getAttribute('authentication');
        $session = $request instanceof ServerRequest ? $request->getSession() : null;
        if ($identity === null || !$service instanceof AuthenticationService) {
            if ($session !== null) {
                SessionGuard::clear($session);
            }

            return $handler->handle($request);
        }

        $stored = $identity->getOriginalData();
        /** @var \CakeDC\Users\Model\Entity\User|null $fresh */
        $fresh = TableRegistry::getTableLocator()->get((string)Configure::read('Users.table', 'CakeDC/Users.Users'))
            ->find()
            ->where(['id' => $stored['id'] ?? null])
            ->first();

        if ($fresh === null || !$fresh->active || ($session !== null && $this->sessionEnded($session, $fresh))) {
            if ($session !== null) {
                SessionGuard::clear($session);
            }
            $result = $service->clearIdentity($request, new Response());
            $request = $result['request']
                ->withoutAttribute('identity')
                ->withAttribute(self::SESSION_ENDED, true);

            return $this->withCookiesOf($result['response'], $handler->handle($request));
        }

        if ($session !== null) {
            $this->stamp($session, $fresh);
        }

        $roleChanged = $fresh->role !== ($stored['role'] ?? null);
        $flagChanged = (bool)$fresh->is_superuser !== (bool)($stored['is_superuser'] ?? false);
        if ($roleChanged || $flagChanged) {
            $result = $service->persistIdentity($request, new Response(), $fresh);
            $request = $result['request']->withAttribute('identity', $service->buildIdentity($fresh));

            return $this->withCookiesOf($result['response'], $handler->handle($request));
        }

        return $handler->handle($request);
    }

    /**
     * Too old, or signed out from elsewhere (rotated `users.session_key`).
     *
     * @param \Cake\Http\Session $session Session.
     * @param \CakeDC\Users\Model\Entity\User $fresh Current account.
     * @return bool
     */
    private function sessionEnded(Session $session, User $fresh): bool
    {
        $guard = SessionGuard::fromConfig();
        $stampedKey = $session->read(SessionGuard::KEY);

        return $guard->lifetimeExceeded(SessionGuard::time($session, SessionGuard::SIGNED_IN_AT), time())
            || $guard->keyChanged(is_string($stampedKey) ? $stampedKey : null, $this->sessionKey($fresh));
    }

    /**
     * First signed-in request of the session (or after this session rotated the key itself).
     *
     * @param \Cake\Http\Session $session Session.
     * @param \CakeDC\Users\Model\Entity\User $fresh Current account.
     * @return void
     */
    private function stamp(Session $session, User $fresh): void
    {
        if (!$session->check(SessionGuard::SIGNED_IN_AT)) {
            $session->write(SessionGuard::SIGNED_IN_AT, time());
        }
        if (!$session->check(SessionGuard::KEY)) {
            $session->write(SessionGuard::KEY, (string)$this->sessionKey($fresh));
        }
    }

    /**
     * @param \CakeDC\Users\Model\Entity\User $user Account.
     * @return string|null
     */
    private function sessionKey(User $user): ?string
    {
        $key = $user->get('session_key');

        return is_string($key) ? $key : null;
    }

    /**
     * Copies the cookies set by the authenticators (e.g. expired remember-me) to the real response.
     *
     * @param \Psr\Http\Message\ResponseInterface $from Response returned by the authentication service.
     * @param \Psr\Http\Message\ResponseInterface $to Response of the application.
     * @return \Psr\Http\Message\ResponseInterface
     */
    private function withCookiesOf(ResponseInterface $from, ResponseInterface $to): ResponseInterface
    {
        if (!$from instanceof Response || !$to instanceof Response) {
            return $to;
        }
        foreach ($from->getCookieCollection() as $cookie) {
            $to = $to->withCookie($cookie);
        }

        return $to;
    }
}
