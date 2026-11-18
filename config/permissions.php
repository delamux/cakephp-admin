<?php
declare(strict_types=1);

use App\Service\UserRoleManager;
/**
 * SYSTEM RBAC rules (CakeDC/Auth), not editable from the UI. Evaluated top-down, first match wins.
 * Role permissions (allow-list per action) live in the database and are appended after these
 * rules by App\Rbac\DatabasePermissionsProvider.
 * Superusers (`is_superuser = true`) bypass these rules through the Superuser policy.
 *
 * Plugin defaults: vendor/cakedc/users/config/permissions.php
 */
use Cake\Http\ServerRequest;
use Cake\Utility\Hash;

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
        // The rest of the role permissions come from the database (Roles → Permissions screen,
        // App\Rbac\DatabasePermissionsProvider). Account-level limits (superadmins, own account)
        // are enforced by App\Service\UserRoleManager.
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
        [
            'role' => '*',
            'plugin' => 'CakeDC/Users',
            'controller' => 'Users',
            'action' => 'resetOneTimePasswordAuthenticator',
            'allowed' => function (array $user, $role, ServerRequest $request): bool {
                $userId = Hash::get($request->getAttribute('params'), 'pass.0');

                return !empty($userId) && !empty($user) && $userId === $user['id'];
            },
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
        [
            'role' => '*',
            'plugin' => null,
            'controller' => 'WebauthnLogin',
            'action' => 'authenticate',
            'bypassAuth' => true,
        ],
        // Any logged-in user: own two-factor settings (password re-checked in each step)
        [
            'role' => '*',
            'plugin' => null,
            'controller' => 'TwoFactor',
            'action' => ['start', 'confirm', 'cancel', 'disable', 'webauthnOptions', 'webauthnRegister'],
        ],
        // Any logged-in user: dashboard
        [
            'role' => '*',
            'plugin' => null,
            'controller' => 'Dashboard',
            'action' => 'index',
        ],
        [
            'role' => '*',
            'controller' => 'Pages',
            'action' => 'display',
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
