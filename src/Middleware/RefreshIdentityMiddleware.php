<?php
declare(strict_types=1);

namespace App\Middleware;

use Authentication\AuthenticationService;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\ORM\TableRegistry;
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
 * - account gone or inactive: the identity is cleared (session + remember-me cookie) and the
 *   request is flagged with `sessionEnded` (App\Middleware\UnauthorizedHandler\AppRedirectHandler
 *   then says "Your session has ended"),
 * - role or superuser flag changed: the fresh user becomes the identity (and is persisted).
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
        if ($identity === null || !$service instanceof AuthenticationService) {
            return $handler->handle($request);
        }

        $stored = $identity->getOriginalData();
        /** @var \CakeDC\Users\Model\Entity\User|null $fresh */
        $fresh = TableRegistry::getTableLocator()->get((string)Configure::read('Users.table', 'CakeDC/Users.Users'))
            ->find()
            ->where(['id' => $stored['id'] ?? null])
            ->first();

        if ($fresh === null || !$fresh->active) {
            $result = $service->clearIdentity($request, new Response());
            $request = $result['request']
                ->withoutAttribute('identity')
                ->withAttribute(self::SESSION_ENDED, true);

            return $this->withCookiesOf($result['response'], $handler->handle($request));
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
