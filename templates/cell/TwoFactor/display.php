<?php
/**
 * Two-step verification card (own profile).
 *
 * @var \App\View\AppView $this
 * @var list<string> $methods Active methods
 * @var array<string, bool> $available Methods switched on in the config
 * @var array{method: string, expires: int, secret?: string}|null $setup Setup step in progress
 * @var string|null $qr TOTP QR (data URI)
 * @var string|null $ask Password form to reopen
 * @var bool $legacyKey Key registered before passkeys
 * @var bool $secure HTTPS request (WebAuthn needs it)
 * @var string $username Account name (hidden field for password managers)
 */
$labels = ['totp' => __('Authenticator app'), 'webauthn' => __('Security key or passkey')];
$hints = [
    'totp' => __('A 6-digit code from an app such as Google Authenticator, 1Password or Bitwarden.'),
    'webauthn' => __('A security key, fingerprint, face or your device PIN. It also lets you sign in without a password.'),
];
$url = fn(string $action): array => ['plugin' => false, 'prefix' => false, 'controller' => 'TwoFactor', 'action' => $action];
// htmx: every step swaps only this card (the controller redirects to the profile, htmx follows it)
$hx = fn(string $action): array => [
    'hx-post' => $this->Url->build($url($action)),
    'hx-target' => '#two-factor-card',
    'hx-select' => '#two-factor-card',
    'hx-swap' => 'outerHTML',
];
// Password confirmation, folded until its trigger is clicked ([data-reveal] in app.js). Reopened when the
// controller asks again after a wrong password ($ask).
$passwordForm = function (string $action, string $method, string $button, bool $open, ?string $variant = null) use ($url, $hx, $username): string {
    $id = 'two-factor-' . $action . '-' . $method;

    return $this->Form->create(null, [
            'url' => $url($action), 'id' => $id, 'hidden' => !$open,
            'class' => 'mt-3 grid gap-3 rounded-lg border bg-muted/40 p-4',
        ] + $hx($action))
        . $this->Form->hidden('method', ['value' => $method])
        // Password managers associate the password with the account (no name: never submitted)
        . '<input type="text" autocomplete="username" value="' . h($username) . '" hidden aria-hidden="true" tabindex="-1">'
        . $this->Form->control('current_password', [
            'type' => 'password', 'label' => __('Current password'), 'required' => true,
            'autocomplete' => 'current-password', 'id' => $id . '-password',
        ])
        . '<div class="flex flex-wrap justify-end gap-2">'
        . $this->Button->render(__('Cancel'), ['type' => 'button', 'variant' => 'ghost', 'size' => 'sm', 'data-reveal-close' => $id])
        . $this->Form->button($button, array_filter(['variant' => $variant, 'size' => 'sm']))
        . '</div>'
        . $this->Form->end();
};
$trigger = fn(string $action, string $method, string $label, bool $open, ?string $variant = null): string => $this->Button->render($label, array_filter([
    'type' => 'button', 'variant' => $variant, 'size' => 'sm', 'hidden' => $open,
    'data-reveal' => 'two-factor-' . $action . '-' . $method,
    'aria-controls' => 'two-factor-' . $action . '-' . $method,
    'aria-expanded' => $open ? 'true' : 'false',
]));

$body = $this->Flash->render('twoFactor') ?? '';
if ($setup !== null && $setup['method'] === 'totp' && $qr !== null) {
    $body .= '<div class="grid gap-4 sm:grid-cols-[auto_1fr]">'
        . '<img src="' . h($qr) . '" alt="' . h(__('QR code for your authenticator app')) . '" class="size-44 rounded-md border bg-white p-2">'
        . '<div class="grid content-start gap-2 text-sm">'
        . '<p>' . __('Scan the code with your authenticator app, then type the 6-digit code it shows.') . '</p>'
        . '<p class="text-muted-foreground">' . __('Or enter this key manually:') . ' <code class="break-all">' . h($setup['secret'] ?? '') . '</code></p>'
        . $this->Form->create(null, ['url' => $url('confirm'), 'class' => 'grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end'] + $hx('confirm'))
        . $this->Form->control('code', ['label' => __('Code'), 'inputmode' => 'numeric', 'autocomplete' => 'one-time-code', 'pattern' => '[0-9 ]*', 'maxlength' => 8, 'required' => true])
        . $this->Form->button(__('Turn on'))
        . $this->Form->end()
        . $this->Form->postButton(__('Cancel'), $url('cancel'), ['variant' => 'ghost', 'size' => 'sm', 'form' => $hx('cancel')])
        . '</div></div>';
} elseif ($setup !== null && $setup['method'] === 'webauthn') {
    $body .= '<div class="grid gap-3 text-sm" data-webauthn-register'
        . ' data-options-url="' . h($this->Url->build($url('webauthnOptions'))) . '"'
        . ' data-register-url="' . h($this->Url->build($url('webauthnRegister'))) . '"'
        . ' data-csrf="' . h((string)$this->getRequest()->getAttribute('csrfToken')) . '"'
        . ' data-error="' . h(__('The key could not be registered. Try again.')) . '"'
        . ' data-unsupported="' . h(__('This browser does not support security keys or passkeys.')) . '">'
        . '<p>' . __('Your browser will ask you to touch your security key or confirm with your fingerprint, face or PIN.') . '</p>'
        . ($secure ? '' : $this->Alert->warning(__('Security keys need HTTPS: open https://admindashboard.localhost.')))
        . '<p class="hidden text-destructive" data-webauthn-message role="alert"></p>'
        . '<div class="flex flex-wrap gap-2">'
        . $this->Button->render(__('Register key'), ['type' => 'button', 'data-webauthn-start' => true, 'icon' => 'shield-check'])
        . $this->Form->postButton(__('Cancel'), $url('cancel'), ['variant' => 'ghost', 'form' => $hx('cancel')])
        . '</div></div>'
        // A cell has its own view: blocks do not reach the layout, so the (deferred) script goes here
        . $this->Html->script('webauthn', ['defer' => true]);
} else {
    $rows = '';
    foreach ($labels as $method => $label) {
        if (!($available[$method] ?? false)) {
            continue;
        }
        $active = in_array($method, $methods, true);
        $action = $active ? 'disable' : 'start';
        $open = $ask === ($active ? 'disable-' . $method : $method);
        $rows .= '<div class="grid gap-1 py-4 first:pt-0 last:pb-0">'
            . '<div class="flex items-start justify-between gap-3">'
            . '<div class="grid gap-1"><p class="flex flex-wrap items-center gap-2 font-medium">' . h($label)
            . $this->Badge->render($active ? __('On') : __('Off'), ['variant' => $active ? 'default' : 'outline']) . '</p>'
            . '<p class="text-sm text-muted-foreground">' . h($hints[$method]) . '</p></div>'
            . '<div class="shrink-0">' . ($active
                ? $trigger($action, $method, __('Turn off'), $open, 'outline')
                : $trigger($action, $method, __('Turn on'), $open)) . '</div>'
            . '</div>'
            . ($method === 'webauthn' && $active && $legacyKey ? '<p class="text-sm text-muted-foreground">' . __('This key works as a second step; register it again to also sign in without a password.') . '</p>' : '')
            . ($active
                ? $passwordForm($action, $method, __('Turn off'), $open, 'destructive')
                : $passwordForm($action, $method, __('Continue'), $open))
            . '</div>';
    }
    $body .= $rows !== ''
        ? '<div class="divide-y">' . $rows . '</div>'
        : '<p class="text-sm text-muted-foreground">' . __('Two-step verification is disabled in this installation.') . '</p>';
}
?>
<div id="two-factor-card">
<?= $this->Card->render($body, [
    'title' => __('Two-step verification'),
    'description' => __('After your password, ask for a second step when you sign in. Optional.'),
]) ?>
</div>
