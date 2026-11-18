<?php
/**
 * Two-step verification of another user (user page, superadmins only).
 *
 * @var \App\View\AppView $this
 * @var string $userId
 * @var list<string> $methods
 * @var bool $canReset Not another superadmin
 */
$labels = ['totp' => __('Authenticator app'), 'webauthn' => __('Security key or passkey')];
$badges = $methods === []
    ? '<span class="text-sm text-muted-foreground">' . __('Not enabled') . '</span>'
    : implode(' ', array_map(fn(string $m): string => $this->Badge->render($labels[$m] ?? $m, ['variant' => 'outline']), $methods));
$body = '<div class="flex flex-wrap items-center justify-between gap-3"><div class="flex flex-wrap gap-2">' . $badges . '</div>';
if ($methods !== [] && $canReset) {
    $body .= $this->Form->postButton(
        __('Reset two-step verification'),
        ['plugin' => false, 'prefix' => false, 'controller' => 'TwoFactor', 'action' => 'reset', $userId],
        ['variant' => 'destructive', 'size' => 'sm', 'data-confirm' => __('Remove both methods? The user will be emailed and can enable them again.')],
    );
}
$body .= '</div>';
echo $this->Card->render($body, [
    'title' => __('Two-step verification'),
    'description' => $canReset
        ? __('If the user lost their device, reset it: they will sign in with their password only.')
        : __('Superadmins reset their own with bin/cake two_factor reset.'),
]);
