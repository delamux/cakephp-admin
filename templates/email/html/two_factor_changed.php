<?php
/**
 * Two-factor change notice (App\Mailer\UsersMailer::twoFactorChanged()).
 *
 * @var \App\View\AppView $this
 * @var string $name
 * @var string $event enabled | disabled | reset
 * @var string|null $method totp | webauthn
 */
$methodLabel = $method === 'webauthn' ? __('security key or passkey') : __('authenticator app');
$title = match ($event) {
    'enabled' => __('Two-step verification turned on'),
    'disabled' => __('Two-step verification turned off'),
    default => __('Two-step verification reset'),
};
$this->assign('title', $title);
$this->assign('preheader', $title);
?>
<h1><?= h($title) ?></h1>
<p><?= __('Hi {0},', h($name)) ?></p>
<?php if ($event === 'enabled') : ?>
    <p><?= __('You have enabled the {0} as a second step to sign in to your account.', h($methodLabel)) ?></p>
<?php elseif ($event === 'disabled') : ?>
    <p><?= __('You have disabled the {0} as a second step to sign in to your account.', h($methodLabel)) ?></p>
<?php else : ?>
    <p><?= __('An administrator has removed the two-step verification of your account. You can enable it again from your profile.') ?></p>
<?php endif; ?>
<p class="muted"><?= __('If it was not you, change your password and contact an administrator as soon as possible.') ?></p>
