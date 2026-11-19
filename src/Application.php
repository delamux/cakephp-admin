<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     3.3.0
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App;

use App\Account\AccountActivityListener;
use App\Account\ActivityLog;
use App\Command\ActivityPurgeCommand;
use App\Command\MaintenanceCommand;
use App\Command\PermissionsPurgeCommand;
use App\Command\PermissionsSyncCommand;
use App\Command\TwoFactorResetCommand;
use App\Http\SecurityMiddlewares;
use App\Middleware\AuthThrottleMiddleware;
use App\Middleware\HostHeaderMiddleware;
use App\Queue\QueueFailureListener;
use App\Rbac\ActionDiscovery;
use App\Rbac\PermissionAuditLog;
use App\Rbac\PermissionsSynchronizer;
use App\Service\UserRoleManager;
use App\UseCase\Account\PurgeAccountActivity;
use App\UseCase\Maintenance\DailyMaintenance;
use App\UseCase\Permissions\PermissionMatrix;
use App\UseCase\Permissions\PurgePermissionHistory;
use App\UseCase\Permissions\SyncPermissions;
use App\UseCase\Permissions\UpdateRolePermissions;
use App\UseCase\Queue\QueueMonitor;
use App\UseCase\Roles\ManageRoles;
use App\UseCase\TwoFactor\TwoFactorSettings;
use App\UseCase\Users\ChangeUserPhoto;
use App\UseCase\Users\ChangeUserRole;
use App\Webauthn\PasskeyLoginAdapter;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Datasource\FactoryLocator;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Event\EventManager;
use Cake\Event\EventManagerInterface;
use Cake\Http\BaseApplication;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\I18n\Middleware\LocaleSelectorMiddleware;
use Cake\ORM\Locator\TableLocator;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use CakeDC\Users\Plugin;
use WeakMap;

/**
 * Application setup class.
 *
 * This defines the bootstrapping logic and middleware layers you
 * want to use in your application.
 */
class Application extends BaseApplication
{
    /**
     * Event managers that already have the account activity listener (the global manager is
     * shared by every bootstrap of the process, e.g. each request of an integration test).
     *
     * @var \WeakMap<\Cake\Event\EventManagerInterface, true>|null
     */
    private static ?WeakMap $activityListenerOn = null;

    /**
     * Load all the application configuration and bootstrap logic.
     *
     * @return void
     */
    public function bootstrap(): void
    {
        // Call parent to load bootstrap from files.
        parent::bootstrap();

        // CakeDC/Users loads Authentication + Authorization (Rbac|Superuser policy) itself.
        // config/users.php overrides the plugin defaults.
        Configure::write('Users.config', ['users']);
        // Account activity log (sign-ins, failures, sign-outs, passwords); failed password logins
        // also count in the per-IP limit (App\Http\AuthThrottle). Registered once per event manager.
        $events = EventManager::instance();
        self::$activityListenerOn ??= new WeakMap();
        if (!isset(self::$activityListenerOn[$events])) {
            $events->on(new AccountActivityListener());
            // Failed queue jobs to the error log (App\Queue\QueueFailureListener)
            $events->on(new QueueFailureListener());
            self::$activityListenerOn[$events] = true;
        }
        $this->addPlugin(Plugin::class);

        // UI theme (layouts, sidebar, Basecoat helpers)
        $this->addPlugin('BasecoatTheme');

        // PDF rendering (engine configured in config/app.php -> CakePdf)
        $this->addPlugin('CakePdf');

        // Background jobs (queued_jobs in PostgreSQL; worker: `bin/cake queue run`, the `worker` service).
        // Its own admin pages are not routed (Bootstrap from a CDN, against the CSP): see QueueController.
        $this->addPlugin('Queue', ['routes' => false]);

        // By default, does not allow fallback classes.
        FactoryLocator::add(
            'Table',
            (new TableLocator())->allowFallbackClass(false),
        );
    }

    /**
     * Setup the middleware queue your application will use.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to setup.
     * @return \Cake\Http\MiddlewareQueue The updated middleware queue.
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue
            // Catch any exceptions in the lower layers,
            // and make an error page/response
            ->add(new ErrorHandlerMiddleware(Configure::read('Error'), $this))

            // Real client IP behind a load balancer (TRUSTED_PROXIES)
            ->add(SecurityMiddlewares::trustedProxies())
            // HTTP -> HTTPS + HSTS (HTTPS_ENFORCE, production)
            ->add(SecurityMiddlewares::httpsEnforcer() ?? fn($request, $handler) => $handler->handle($request))
            // Security headers and Content-Security-Policy with nonces (report-only in dev)
            ->add(SecurityMiddlewares::securityHeaders())
            ->add(SecurityMiddlewares::csp())

            // Validate Host header to prevent Host Header Injection attacks.
            // In production, ensures App.fullBaseUrl is configured and validates
            // the incoming Host header against it.
            ->add(new HostHeaderMiddleware())

            // Handle plugin/theme assets like CakePHP normally does.
            ->add(new AssetMiddleware([
                'cacheTime' => Configure::read('Asset.cacheTime'),
            ]))

            // Add routing middleware.
            // If you have a large number of routes connected, turning on routes
            // caching in production could improve performance.
            // See https://github.com/CakeDC/cakephp-cached-routing
            // Language from the browser (Accept-Language); falls back to App.defaultLocale
            ->add(new LocaleSelectorMiddleware(['en_US', 'es_ES']))

            ->add(new RoutingMiddleware($this))

            // Parse various types of encoded request bodies so that they are
            // available as array through $request->getData()
            // https://book.cakephp.org/5/en/controllers/middleware.html#body-parser-middleware
            ->add(new BodyParserMiddleware())

            // Per-IP throttling of sign-in / account endpoints (needs the routed action; before CSRF)
            ->add(new AuthThrottleMiddleware())

            // CSRF: httponly + SameSite=Lax cookie, secure on HTTPS
            ->add(SecurityMiddlewares::csrf())

            // "Remember me" cookie encrypted (runs before CakeDC's authentication middlewares)
            ->add(SecurityMiddlewares::encryptedCookies());

        return $middlewareQueue;
    }

    /**
     * Register application container services.
     *
     * @param \Cake\Core\ContainerInterface $container The Container to update.
     * @return void
     * @link https://book.cakephp.org/5/en/development/dependency-injection.html#dependency-injection
     */
    public function services(ContainerInterface $container): void
    {
        // DDD-lite: explicit registrations (no global ReflectionContainer, which would also autowire
        // every controller, CakeDC's included). Configurable services come from their factories.
        $container->add(UserRoleManager::class, fn(): UserRoleManager => UserRoleManager::fromConfig());
        $container->add(
            PermissionsSynchronizer::class,
            fn(): PermissionsSynchronizer => PermissionsSynchronizer::fromConfig(),
        );
        $container->add(ActionDiscovery::class, fn(): ActionDiscovery => ActionDiscovery::fromConfig());
        $container->add(PermissionAuditLog::class);
        $container->add(ActivityLog::class);

        // Use cases (src/UseCase)
        $container->add(PermissionMatrix::class)
            ->addArguments([PermissionsSynchronizer::class, UserRoleManager::class]);
        $container->add(UpdateRolePermissions::class)
            ->addArguments([PermissionMatrix::class, PermissionsSynchronizer::class, PermissionAuditLog::class]);
        $container->add(SyncPermissions::class)
            ->addArguments([ActionDiscovery::class, PermissionsSynchronizer::class, PermissionAuditLog::class]);
        $container->add(PurgePermissionHistory::class)->addArgument(PermissionAuditLog::class);
        $container->add(ManageRoles::class)->addArgument(PermissionAuditLog::class);
        $container->add(ChangeUserRole::class)->addArgument(UserRoleManager::class);
        $container->add(ChangeUserPhoto::class);
        $container->add(TwoFactorSettings::class);
        $container->add(PurgeAccountActivity::class)->addArgument(ActivityLog::class);
        $container->add(QueueMonitor::class);
        $container->add(DailyMaintenance::class)
            ->addArguments([PurgeAccountActivity::class, PurgePermissionHistory::class]);
        // Passkey login adapter (mockable in integration tests with mockService())
        $container->add(PasskeyLoginAdapter::class);

        // Commands (constructor injection)
        $container->add(PermissionsSyncCommand::class)->addArgument(SyncPermissions::class);
        $container->add(PermissionsPurgeCommand::class)->addArgument(PurgePermissionHistory::class);
        $container->add(TwoFactorResetCommand::class)->addArgument(TwoFactorSettings::class);
        $container->add(ActivityPurgeCommand::class)->addArgument(PurgeAccountActivity::class);
        $container->add(MaintenanceCommand::class)->addArgument(DailyMaintenance::class);
    }

    /**
     * Register custom event listeners here
     *
     * @param \Cake\Event\EventManagerInterface $eventManager
     * @return \Cake\Event\EventManagerInterface
     * @link https://book.cakephp.org/5/en/core-libraries/events.html#registering-listeners
     */
    public function events(EventManagerInterface $eventManager): EventManagerInterface
    {
        // $eventManager->on(new SomeCustomListenerClass());

        return $eventManager;
    }
}
