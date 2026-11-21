<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Account\SessionGuard;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * "Sudo mode": a sensitive action (App\Account\SessionGuard, `App.session.confirmActions`) needs the
 * password confirmed in the last minutes; otherwise the user is sent to /confirm-password and back.
 * A GET comes back to the same page; a POST (whose data cannot be replayed) to the page it was sent
 * from. htmx requests navigate (HX-Redirect) instead of swapping the form into a fragment.
 *
 * Runs after the authorization middlewares (App\Loader\MiddlewareQueueLoader): only a user allowed
 * to open the action is asked, superadmins included.
 */
class ConfirmPasswordMiddleware implements MiddlewareInterface
{
    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request Request.
     * @param \Psr\Http\Server\RequestHandlerInterface $handler Next handler.
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getAttribute('identity') === null || !$request instanceof ServerRequest) {
            return $handler->handle($request);
        }
        $params = (array)$request->getAttribute('params', []);
        $action = [
            'plugin' => (string)($params['plugin'] ?? ''),
            'prefix' => (string)($params['prefix'] ?? ''),
            'controller' => (string)($params['controller'] ?? ''),
            'action' => (string)($params['action'] ?? ''),
        ];
        $guard = SessionGuard::fromConfig();
        $session = $request->getSession();
        if (
            !$guard->requiresConfirmation($action)
            || $guard->isConfirmed(SessionGuard::time($session, SessionGuard::CONFIRMED_AT), time())
        ) {
            return $handler->handle($request);
        }

        $back = $request->is('get') ? $request->getRequestTarget() : $request->referer();
        $url = Router::url([
            'plugin' => null,
            'prefix' => false,
            'controller' => 'ConfirmPassword',
            'action' => 'index',
            '?' => ['redirect' => SessionGuard::safeRedirect($back)],
        ]);
        $request->getFlash()->info(__('Confirm your password to continue.'));

        $response = new Response();
        if ($request->getHeaderLine('HX-Request') === 'true') {
            return $response->withHeader('HX-Redirect', $url);
        }

        return $response->withStatus(302)->withLocation($url);
    }
}
