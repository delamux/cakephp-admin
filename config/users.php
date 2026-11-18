<?php
declare(strict_types=1);

use App\Authentication\OptionalOneTimePasswordChecker;
use App\Authentication\OptionalWebauthn2faChecker;
use App\Loader\MiddlewareQueueLoader;
use App\Mailer\UsersMailer;
use App\Middleware\UnauthorizedHandler\AppRedirectHandler;
use App\Rbac\DatabasePermissionsProvider;
use App\Rbac\PermissionsSynchronizer;
use CakeDC\Auth\Rbac\Rbac;
use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;

// Two-factor master switch (TWO_FACTOR_ENABLED, on by default): off hides the methods and asks nothing at login.
$twoFactorEnabled = filter_var(env('TWO_FACTOR_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN);
// WebAuthn domain: keys are bound to it (host of APP_FULL_BASE_URL; null = the request host)
$fullBaseUrl = (string)env('APP_FULL_BASE_URL', '');
$webauthnId = $fullBaseUrl !== '' ? parse_url($fullBaseUrl, PHP_URL_HOST) : null;

/**
 * CakeDC/Users overrides (loaded via Configure::write('Users.config', ['users']) in Application::bootstrap()).
 * Plugin defaults: vendor/cakedc/users/config/users.php
 */
return [
    'Users' => [
        // App table (superadmin ⇔ is_superuser sync). CakeDC maps its aliases `Users` and
        // `CakeDC/Users.Users` to this class in its bootstrap.
        'table' => 'Users',
        // CakeDC middlewares + App\Middleware\RefreshIdentityMiddleware before authorization
        'middlewareQueueLoader' => MiddlewareQueueLoader::class,
        'Email' => [
            'required' => true,
            'validate' => true,
            // Mailer with the app email layout + CSS inlining (Emogrifier)
            'mailerClass' => UsersMailer::class,
        ],
        'Registration' => [
            'active' => true,
            'reCaptcha' => false,
            'allowLoggedIn' => false,
            'defaultRole' => 'user',
        ],
        'reCaptcha' => [
            'registration' => false,
            'login' => false,
        ],
        'Social' => [
            'login' => false,
        ],
        'RememberMe' => [
            'active' => true,
            'checked' => false,
        ],
    ],
    'OneTimeLogin' => [
        'enabled' => true,
    ],
    // Opt-in 2FA: TOTP (authenticator app) and/or a security key / passkey, enabled from the profile
    // (App\Controller\TwoFactorController). The checkers ask the second step only to users who enabled it.
    'OneTimePasswordAuthenticator' => [
        'checker' => OptionalOneTimePasswordChecker::class,
        'login' => $twoFactorEnabled,
        'issuer' => env('APP_NAME', 'CakePHP Admin'),
        'digits' => 6,
        'period' => 30,
        // Must be set explicitly: overriding the plugin config with null breaks the login page
        'qrcodeprovider' => new EndroidQrCodeProvider(),
    ],
    // WebAuthn requires HTTPS (https://admindashboard.localhost in dev, README "HTTPS")
    'Webauthn2fa' => [
        'enabled' => $twoFactorEnabled,
        'appName' => env('APP_NAME', 'CakePHP Admin'),
        'id' => $webauthnId ?: null,
        'checker' => OptionalWebauthn2faChecker::class,
    ],
    // The emailed one-time sign-in link must also go through 2FA (CakeDC skips it by default)
    'Auth.Authenticators.OneTimeToken.skipTwoFactorVerify' => false,
    // "Remember me" cookie: only over HTTPS when the app runs on HTTPS, never sent cross-site
    'Auth.Authenticators.Cookie.cookie.secure' => str_starts_with($fullBaseUrl, 'https://'),
    'Auth.Authenticators.Cookie.cookie.samesite' => 'Lax',
    // Role whitelist (App\Service\UserRoleManager). `superadmin` is kept in sync with CakeDC's
    // `is_superuser` (App\Model\Table\UsersTable::beforeSave) and only superadmins grant it.
    'App.roles' => [
        'superadmin' => __('Superadmin'),
        'admin' => __('Administrator'),
        'editor' => __('Editor'),
        'user' => __('User'),
    ],
    // Permissions managed from the database (Phase 9). `bin/cake permissions sync` discovers the
    // actions of the app + these plugins and grants `defaults` to NEW actions (patterns on
    // `Plugin.Prefix/Controller::action`, `!` excludes). Superadmin bypasses RBAC: no grants needed.
    // Public actions, profile/logout and the dashboard are system rules (config/permissions.php).
    'App.permissions' => [
        'plugins' => ['CakeDC/Users'],
        'ignoredActions' => ['getUsersTable', 'setUsersTable', 'validateReCaptcha'],
        // Never grantable from the matrix (an admin could otherwise escalate itself)
        'superadminOnly' => PermissionsSynchronizer::SUPERADMIN_ONLY,
        // Permissions history kept for N days (`bin/cake permissions purge`, minimum 30)
        'auditRetentionDays' => 365,
        'defaults' => [
            'admin' => ['*', '!Roles::*', '!Permissions::*'],
            'editor' => ['CakeDC/Users.Users::index', 'CakeDC/Users.Users::view', 'Reports::users'],
        ],
    ],
    // Account activity log (App\Account\ActivityLog): kept for N days, then deleted by
    // `bin/cake activity purge` (daily cron, minimum 30). It holds IPs and browsers (personal data).
    'App.accountActivity' => [
        'retentionDays' => 180,
    ],
    // Not signed in → "Please sign in to continue."; signed in without permission → this flash
    // (the plugin default appends the raw URL in debug mode).
    'Auth.AuthorizationMiddleware.unauthorizedHandler' => [
        'className' => AppRedirectHandler::class,
        'flash' => [
            'message' => __('You don\'t have permission to open that page.'),
            'params' => ['title' => __('Access denied')],
        ],
    ],
    // RBAC rules = config/permissions.php (system) + database allow-list (Phase 9)
    'Auth.RbacPolicy' => [
        'adapter' => [
            'className' => Rbac::class,
            'permissions_provider_class' => DatabasePermissionsProvider::class,
        ],
    ],
    'Auth.AuthenticationComponent.loginRedirect' => [
        'prefix' => false,
        'plugin' => false,
        'controller' => 'Dashboard',
        'action' => 'index',
    ],
];
