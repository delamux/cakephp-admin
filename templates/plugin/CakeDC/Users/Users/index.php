<?php
/**
 * Users list (CakeDC/Users override, admins only).
 *
 * @var \App\View\AppView $this
 * @var string $tableAlias
 * @var \App\Service\UserRoleManager $roleManager
 * @var bool $isAdmin
 * @var \CakeDC\Users\Model\Entity\User $currentUser
 */
$users = ${$tableAlias};
$this->assign('title', __('Users'));

$this->start('header_actions');
echo $this->element('header_action', ['text' => __('Export'), 'url' => ['plugin' => false, 'controller' => 'Reports', 'action' => 'users', '_ext' => 'pdf', '?' => ['download' => 1]], 'icon' => 'arrow-down', 'variant' => 'outline']);
if ($isAdmin) {
    echo $this->element('header_action', ['text' => __('New user'), 'url' => ['action' => 'add'], 'icon' => 'plus']);
}
$this->end();

$displayName = fn($user): string => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: (string)$user->username;

// Row actions: what the RBAC rules allow (role permissions, rule `owner`) and the account protections
// allow (App\Service\UserRoleManager::isProtected(): superadmin/admin accounts, own account)
$canDelete = fn($user): bool => $user->id !== $currentUser->id && !$roleManager->isProtected($currentUser, $user) && $this->AuthLink->isAuthorized(['plugin' => 'CakeDC/Users', 'prefix' => false, 'controller' => 'Users', 'action' => 'delete', $user->id]);
$rowMenu = function ($user, int $i) use ($roleManager, $currentUser, $canDelete): array {
    // Links leave the list: normal navigation (the list container is htmx-boosted)
    $nav = ['attributes' => ['hx-boost' => 'false']];
    $items = [['label' => __('View'), 'url' => ['action' => 'view', $user->id], 'icon' => 'user'] + $nav];
    if (!$roleManager->isProtected($currentUser, $user) && $this->AuthLink->isAuthorized(['plugin' => 'CakeDC/Users', 'prefix' => false, 'controller' => 'Users', 'action' => 'edit', $user->id])) {
        $items[] = ['label' => __('Edit'), 'url' => ['action' => 'edit', $user->id], 'icon' => 'pencil'] + $nav;
    }
    if ($roleManager->canChangePasswordOf($currentUser, $user)) {
        $items[] = ['label' => __('Change password'), 'url' => ['action' => 'changePassword', $user->id], 'icon' => 'settings-2'] + $nav;
    }
    if ($canDelete($user)) {
        $items[] = '-';
        $items[] = ['label' => __('Delete'), 'url' => '#', 'icon' => 'trash', 'variant' => 'destructive', 'attributes' => [
            // webroot/js/app.js submits the form (no inline JS: CSP)
            'data-submit-form' => 'delete-user-' . $i,
        ]];
    }

    return $items;
};

$rows = [];
foreach ($users as $i => $user) {
    $rows[] = [
        'username' => '<div class="flex items-center gap-3">' . $this->Avatar->render($displayName($user), array_filter(['src' => $this->UserPhoto->url($user), 'size' => 'sm']))
            . '<div class="grid leading-tight">' . $this->Html->link($displayName($user), ['action' => 'view', $user->id], ['class' => 'font-medium hover:underline'])
            . '<span class="text-xs text-muted-foreground">' . h($user->email) . '</span></div></div>',
        'last_login' => '<span class="whitespace-nowrap text-muted-foreground">' . ($user->last_login ? h($user->last_login->i18nFormat('d MMM yyyy')) : __('Never')) . '</span>',
        'role' => $this->element('Users/role_badge', ['user' => $user]),
        'active' => $user->active
            ? $this->Badge->render(__('Active'), ['variant' => 'outline', 'class' => 'text-muted-foreground', 'icon' => $this->Icon->render('circle-check', ['class' => 'size-3.5 fill-green-500 text-background dark:fill-green-400', 'replaceClass' => true])])
            : $this->Badge->render(__('Pending'), ['variant' => 'outline', 'class' => 'text-muted-foreground', 'icon' => 'loader']),
        'created' => '<span class="whitespace-nowrap text-muted-foreground">' . h($user->created?->i18nFormat('d MMM yyyy')) . '</span>',
        'actions' => $this->DropdownMenu->render('', $rowMenu($user, $i), [
            'id' => 'user-menu-' . $i,
            'align' => 'end',
            'trigger' => ['variant' => 'ghost', 'size' => 'icon-sm', 'icon' => 'ellipsis-vertical', 'class' => 'btn text-muted-foreground', 'aria-label' => __('Actions')],
            'popoverOptions' => ['class' => 'min-w-40'],
        ]) . ($canDelete($user) ? $this->Form->create(null, [
            'url' => ['action' => 'delete', $user->id],
            'id' => 'delete-user-' . $i,
            'class' => 'hidden',
            'data-confirm' => __('Delete user {0}? This cannot be undone.', $user->username),
        ]) . $this->Form->end() : ''),
    ];
}
?>
<?php // htmx: sorting, pagination and delete swap only the list (hx-select), with the URL in the history ?>
<div class="px-4 lg:px-6" id="users-list" hx-boost="true" hx-target="#users-list" hx-select="#users-list" hx-swap="outerHTML">
    <?= $this->Card->render(
        $rows === []
            ? $this->EmptyState->render(__('No users yet'), ['icon' => 'users', 'description' => __('Create the first account to get started.'), 'actions' => $isAdmin ? $this->Button->link(__('New user'), ['action' => 'add'], ['icon' => 'plus']) : null])
            : '<div class="overflow-x-auto rounded-lg border">' . $this->Table->render([
                'username' => ['label' => __('User'), 'sort' => true],
                'role' => ['label' => __('Role'), 'sort' => true, 'class' => 'hidden @xl/main:table-cell'],
                'active' => ['label' => __('Status'), 'sort' => true],
                'last_login' => ['label' => __('Last login'), 'sort' => true, 'class' => 'hidden @4xl/main:table-cell'],
                'created' => ['label' => __('Created'), 'sort' => true, 'class' => 'hidden @3xl/main:table-cell'],
                'actions' => ['label' => '', 'class' => 'w-8'],
            ], $rows, ['escape' => false, 'headOptions' => ['class' => 'bg-muted']]) . '</div>',
        [
            'title' => __('All users'),
            'description' => $this->Paginator->counter(__('{{count}} accounts')),
            'footer' => $this->Pagination->render(['counter' => true]),
            'footerOptions' => ['class' => 'justify-between'],
        ],
    ) ?>
</div>
