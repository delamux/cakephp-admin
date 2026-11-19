<?php
declare(strict_types=1);

use App\Service\UserRoleManager;

/**
 * SYSTEM RBAC rules (CakeDC/Auth), not editable from the UI: public actions (no identity), the
 * superadmin role and what every signed-in user always keeps (profile, logout, social links).
 * Evaluated top-down, first match wins. Everything else is a role permission in the database
 * (Permissions screen, optionally with a rule of App\Rbac\RuleCatalog) appended after these rules
 * by App\Rbac\DatabasePermissionsProvider.
 * Superusers (`is_superuser = true`) bypass these rules through the Superuser policy.
 *
 * Plugin defaults: vendor/cakedc/users/config/permissions.php
 */

return [
    'CakeDC/Auth.permissions' => [
        // Public (no identity required) actions of the Users plugin
        [
            'prefix' => false,
            'plugin' => 'CakeDC/Users',
            'controller' => 'Users',
            'action' => [
                'socialLogin', 'login', 'logout', 'socialEmail', 'verify',
                'register', 'validateEmail',
                'changePassword', 'resetPassword', 'requestResetPassword',
                'resendTokenValidation', 'linkSocial',
                'webauthn2fa', 'webauthn2faRegister', 'webauthn2faRegisterOptions',
                'webauthn2faAuthenticate', 'webauthn2faAuthenticateOptions',
                'requestLoginLink', 'sendLoginLink', 'singleTokenLogin',
            ],
            'bypassAuth' => true,
        ],
        [
            'prefix' => false,
            'plugin' => 'CakeDC/Users',
            'controller' => 'SocialAccounts',
            'action' => ['validateAccount', 'resendValidation'],
            'bypassAuth' => true,
        ],
        // Superadmin role: everything (superadmins also have `is_superuser`, which bypasses RBAC).
        // Account limits of the other roles (own account, not superadmins) are rules of the
        // database permissions (App\Rbac\RuleCatalog).
        [
            'role' => UserRoleManager::SUPERADMIN,
            'prefix' => '*',
            'extension' => '*',
            'plugin' => '*',
            'controller' => '*',
            'action' => '*',
        ],
        // Any logged-in user: own profile
        [
            'role' => '*',
            'plugin' => 'CakeDC/Users',
            'controller' => 'Users',
            'action' => ['profile', 'logout', 'linkSocial', 'callbackLinkSocial'],
        ],
        // User photos: any signed-in role; whose photo may be changed is decided by
        // App\UseCase\Users\ChangeUserPhoto (own always; others' with admin access)
        [
            'role' => '*',
            'plugin' => null,
            'controller' => 'UserPhoto',
            'action' => ['update', 'delete', 'show'],
        ],
        // Passwordless login with a passkey (App\Controller\PasskeyLoginController)
        [
            'role' => '*',
            'plugin' => null,
            'controller' => 'PasskeyLogin',
            'action' => ['options', 'login'],
            'bypassAuth' => true,
        ],
        // Login second step with the security key (not logged in yet; App\Controller\WebauthnLoginController)
        // Invitation link (App\Controller\InvitationsController::accept()): choose the password
        [
            'role' => '*',
            'plugin' => null,
            'controller' => 'Invitations',
            'action' => 'accept',
            'bypassAuth' => true,
        ],
        [
            'role' => '*',
            'plugin' => null,
            'controller' => 'WebauthnLogin',
            'action' => 'authenticate',
            'bypassAuth' => true,
        ],
        [
            'role' => '*',
            'plugin' => 'DebugKit',
            'controller' => '*',
            'action' => '*',
            'bypassAuth' => true,
        ],
    ],
];
