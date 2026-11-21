<?php
/**
 * @var \App\View\AppView $this
 * @var string $name
 * @var string $event enabled | disabled | reset
 * @var string|null $method totp | webauthn
 */
$methodLabel = $method === 'webauthn' ? __('security key or passkey') : __('authenticator app');
?>
<?= __('Hi {0},', $name) ?>


<?php if ($event === 'enabled') : ?>
<?= __('You have enabled the {0} as a second step to sign in to your account.', $methodLabel) ?>
<?php elseif ($event === 'disabled') : ?>
<?= __('You have disabled the {0} as a second step to sign in to your account.', $methodLabel) ?>
<?php else : ?>
<?= __('An administrator has removed the two-step verification of your account. You can enable it again from your profile.') ?>
<?php endif; ?>


<?= __('If it was not you, change your password and contact an administrator as soon as possible.') ?>
