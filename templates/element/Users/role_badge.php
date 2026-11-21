<?php
/**
 * Role badge: label from the role whitelist (App.roles), emphasis by access level.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 */
use App\Service\UserRoleManager;

$role = UserRoleManager::isSuperadmin($user) ? UserRoleManager::SUPERADMIN : (string)$user->role;
$label = UserRoleManager::fromConfig()->roles()[$role] ?? $role;
$variant = match ($role) {
    UserRoleManager::SUPERADMIN => 'primary',
    UserRoleManager::ADMIN => 'secondary',
    default => 'outline',
};

echo $this->Badge->render($label, ['variant' => $variant]);
