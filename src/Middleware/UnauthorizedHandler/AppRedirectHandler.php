<?php
declare(strict_types=1);

namespace App\Middleware\UnauthorizedHandler;

use App\Middleware\RefreshIdentityMiddleware;
use CakeDC\Users\Middleware\UnauthorizedHandler\DefaultRedirectHandler;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * CakeDC redirect handler with a message that matches the situation:
 * - not signed in (or the session expired): "Please sign in to continue." (info),
 * - signed in without permission: the configured `flash` ("Access denied").
 *
 * Configured in config/users.php (`Auth.AuthorizationMiddleware.unauthorizedHandler`).
 */
class AppRedirectHandler extends DefaultRedirectHandler
{
    /**
     * @inheritDoc
     */
    public function handle(
        Exception $exception,
        ServerRequestInterface $request,
        array $options = [],
    ): ResponseInterface {
        if ($request->getAttribute(RefreshIdentityMiddleware::SESSION_ENDED)) {
            // The account was deleted/deactivated while signed in (RefreshIdentityMiddleware)
            $options['flash'] = [
                'message' => __('Your session has ended. Please sign in again.'),
                'element' => 'flash/error',
                'params' => [],
            ];
        } elseif ($request->getAttribute('identity') === null) {
            $options['flash'] = [
                'message' => __('Please sign in to continue.'),
                'element' => 'flash/info',
                'params' => [],
            ];
        }

        $response = parent::handle($exception, $request, $options);
        // htmx would follow the redirect and swap the login page into a fragment: navigate instead
        if ($request->getHeaderLine('HX-Request') === 'true' && $response->hasHeader('Location')) {
            return $response->withStatus(200)->withHeader('HX-Redirect', $response->getHeaderLine('Location'));
        }

        return $response;
    }
}
