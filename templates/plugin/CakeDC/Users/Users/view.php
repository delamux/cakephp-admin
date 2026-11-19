<?php
/**
 * User detail (CakeDC/Users override, admins only).
 *
 * @var \App\View\AppView $this
 * @var string $tableAlias
 * @var \App\Service\UserRoleManager $roleManager
 * @var \CakeDC\Users\Model\Entity\User $currentUser
 */
$user = ${$tableAlias};
$name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: (string)$user->username;
$this->assign('title', $name);
$this->start('breadcrumb');
echo $this->Breadcrumb->render([__('Users') => ['action' => 'index'], h($user->username)]);
$this->end();
if (!$roleManager->isProtected($currentUser, $user) && $this->AuthLink->isAuthorized(['plugin' => 'CakeDC/Users', 'prefix' => false, 'controller' => 'Users', 'action' => 'edit', $user->id])) {
    $this->start('header_actions');
    echo $this->element('header_action', ['text' => __('Edit'), 'url' => ['action' => 'edit', $user->id], 'icon' => 'pencil', 'variant' => 'outline']);
    $this->end();
}

$date = fn($value): string => $value ? h($value->i18nFormat('d MMM yyyy, HH:mm')) : '<span class="text-muted-foreground">—</span>';
$details = [
    __('Username') => h($user->username),
    __('Email') => h($user->email),
    __('Role') => $this->element('Users/role_badge', ['user' => $user]),
    __('Status') => $user->active
        ? $this->Badge->render(__('Active'), ['variant' => 'outline', 'icon' => $this->Icon->render('circle-check', ['class' => 'size-3.5 fill-green-500 text-background dark:fill-green-400', 'replaceClass' => true])])
        : ($user->get('invited_at') !== null
            ? $this->Badge->render(__('Invited'), ['variant' => 'outline', 'icon' => 'mail'])
            : $this->Badge->render(__('Pending validation'), ['variant' => 'outline', 'icon' => 'loader'])),
    __('Last login') => $date($user->last_login),
    __('Activation date') => $date($user->activation_date),
    __('Terms accepted') => $date($user->tos_date),
    __('Created') => $date($user->created),
    __('Modified') => $date($user->modified),
];
$list = '<dl class="grid gap-4 text-sm sm:grid-cols-2">';
foreach ($details as $label => $value) {
    $list .= '<div class="grid gap-1"><dt class="text-muted-foreground">' . h($label) . '</dt><dd class="font-medium">' . $value . '</dd></div>';
}
$list .= '</dl>';

$social = '';
foreach ($user->social_accounts ?? [] as $account) {
    $social .= '<li class="flex items-center justify-between text-sm"><span class="font-medium">' . h($account->provider) . '</span>'
        . $this->Badge->render($account->active ? __('Active') : __('Inactive'), ['variant' => 'outline']) . '</li>';
}
?>
<div class="grid gap-4 px-4 lg:px-6 @3xl/main:grid-cols-3">
    <?= $this->Card->render(
        '<div class="flex flex-col items-center gap-3 text-center">' . $this->Avatar->render($name, array_filter(['src' => $this->UserPhoto->url($user), 'size' => 'lg', 'class' => 'size-20 text-xl']))
        . '<div><p class="text-lg font-semibold">' . h($name) . '</p><p class="text-sm text-muted-foreground">' . h($user->email) . '</p></div></div>',
        ['footer' => $roleManager->canChangePasswordOf($currentUser, $user)
            ? $this->Button->link(__('Change password'), ['action' => 'changePassword', $user->id], ['variant' => 'outline', 'class' => 'w-full'])
            : null],
    ) ?>
    <?= $this->Card->render($list, [
        'class' => '@3xl/main:col-span-2',
        'title' => __('Account'),
        'description' => __('Identity and lifecycle of the account.'),
    ]) ?>
    <?= $this->Card->render(
        $social === '' ? '<p class="text-sm text-muted-foreground">' . __('No social accounts linked.') . '</p>' : '<ul class="grid gap-3">' . $social . '</ul>',
        ['class' => '@3xl/main:col-span-3', 'title' => __('Social accounts')],
    ) ?>
    <?php if ($roleManager->isSuperadmin($currentUser) && $currentUser->id !== $user->id) : ?>
        <div class="@3xl/main:col-span-3"><?= $this->cell('TwoFactor::status', [(string)$user->id, (bool)$user->is_superuser]) ?></div>
    <?php endif; ?>
    <?php if ($roleManager->isSuperadmin($currentUser)) : ?>
        <div class="@3xl/main:col-span-3"><?= $this->cell('AccountActivity', [(string)$user->id, $currentUser->id === $user->id]) ?></div>
    <?php endif; ?>
</div>
