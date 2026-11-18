<?php
/**
 * Reset password email (CakeDC/Users override).
 *
 * @var \Cake\View\View $this
 * @var string $activationUrl
 * @var string|null $first_name
 * @var string|null $username
 */
$url = $this->Url->build($activationUrl, ['fullBase' => true]);
$this->assign('title', __('Reset your password'));
$this->assign('preheader', __('Use this link to choose a new password.'));
?>
<h1><?= __('Reset your password') ?></h1>
<p><?= __('Hi {0},', h($first_name ?? $username ?? '')) ?></p>
<p><?= __('We received a request to reset the password of your account. Click the button below to choose a new one.') ?></p>
<?= $this->element('email/button', ['url' => $url, 'text' => __('Choose a new password')]) ?>
<p class="muted"><?= __('This link expires soon. If you did not request a password reset, no action is needed: your password will stay the same.') ?></p>
<?= $this->element('email/fallback_link', ['url' => $url]) ?>
