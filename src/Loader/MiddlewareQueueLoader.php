<?php
declare(strict_types=1);

namespace App\Loader;

use App\Middleware\ConfirmPasswordMiddleware;
use App\Middleware\RefreshIdentityMiddleware;
use Authentication\AuthenticationServiceProviderInterface;
use Authorization\AuthorizationServiceProviderInterface;
use Cake\Http\MiddlewareQueue;
use CakeDC\Users\Loader\MiddlewareQueueLoader as BaseMiddlewareQueueLoader;

/**
 * CakeDC/Users middleware queue + RefreshIdentityMiddleware right after authentication, so the
 * authorization (RBAC) middleware always sees the current role of the user (and ended sessions are
 * already anonymous), and ConfirmPasswordMiddleware after authorization ("sudo mode" for the actions
 * the user is allowed to open).
 *
 * Configured in config/users.php (`Users.middlewareQueueLoader`).
 */
class MiddlewareQueueLoader extends BaseMiddlewareQueueLoader
{
    /**
     * @inheritDoc
     */
    protected function loadAuthenticationMiddleware(
        MiddlewareQueue $middlewareQueue,
        AuthenticationServiceProviderInterface $authenticationServiceProvider,
    ) {
        parent::loadAuthenticationMiddleware($middlewareQueue, $authenticationServiceProvider);
        $middlewareQueue->add(new RefreshIdentityMiddleware());
    }

    /**
     * @inheritDoc
     */
    protected function loadAuthorizationMiddleware(
        MiddlewareQueue $middlewareQueue,
        AuthorizationServiceProviderInterface $authorizationServiceProvider,
    ) {
        parent::loadAuthorizationMiddleware($middlewareQueue, $authorizationServiceProvider);

        return $middlewareQueue->add(new ConfirmPasswordMiddleware());
    }
}
