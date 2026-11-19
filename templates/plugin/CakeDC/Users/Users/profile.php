<?php
/**
 * My profile (CakeDC/Users override).
 *
 * @var \App\View\AppView $this
 * @var \CakeDC\Users\Model\Entity\User $user
 * @var bool $isCurrentUser
 */
$name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: (string)$user->username;
$this->assign('title', __('Profile'));

$date = fn($value): string => $value ? h($value->i18nFormat('d MMM yyyy, HH:mm')) : '<span class="text-muted-foreground">—</span>';
$details = [
    __('Username') => h($user->username),
    __('Email') => h($user->email),
    __('Role') => $this->element('Users/role_badge', ['user' => $user]),
    __('Member since') => $date($user->created),
    __('Last login') => $date($user->last_login),
];
$list = '<dl class="grid gap-4 text-sm sm:grid-cols-2">';
foreach ($details as $label => $value) {
    $list .= '<div class="grid gap-1"><dt class="text-muted-foreground">' . h($label) . '</dt><dd class="font-medium">' . $value . '</dd></div>';
}
$list .= '</dl>';
$connect = $this->User->socialConnectLinkList($user->social_accounts ?? []);
?>
<div class="grid gap-4 px-4 lg:px-6 @3xl/main:grid-cols-3">
    <?= $this->Card->render(
        '<div class="flex flex-col items-center gap-3 text-center">'
        . $this->Avatar->render($name, array_filter(['src' => $this->UserPhoto->url($user), 'size' => 'lg', 'class' => 'size-20 text-xl']))
        . '<div><p class="text-lg font-semibold">' . h($name) . '</p><p class="text-sm text-muted-foreground">' . h($user->email) . '</p></div></div>',
        [
            'footer' => $isCurrentUser
                ? $this->Button->link(__('Change password'), ['action' => 'changePassword'], ['variant' => 'outline', 'class' => 'w-full', 'icon' => 'settings-2'])
                : null,
        ],
    ) ?>
    <?= $this->Card->render($list . ($connect !== '' ? '<div class="mt-6 flex flex-wrap gap-2">' . $connect . '</div>' : ''), [
        'class' => '@3xl/main:col-span-2',
        'title' => __('Personal information'),
        'description' => __('Details of your account.'),
    ]) ?>
    <?php if ($isCurrentUser) : ?>
        <div class="@3xl/main:col-span-3"><?= $this->element('Users/photo_form', ['user' => $user, 'own' => true]) ?></div>
        <div class="grid items-start gap-4 @3xl/main:col-span-3 @5xl/main:grid-cols-2">
            <?= $this->cell('TwoFactor') ?>
            <div class="min-w-0"><?= $this->cell('AccountActivity', [(string)$user->id]) ?></div>
        </div>
    <?php endif; ?>
</div>
