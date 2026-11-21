<?php
/**
 * Account validation email (CakeDC/Users override).
 *
 * @var \App\View\AppView $this
 * @var string $activationUrl
 * @var string|null $first_name
 * @var string|null $username
 */
$url = $this->Url->build($activationUrl, ['fullBase' => true]);
$this->assign('title', __('Confirm your email address'));
$this->assign('preheader', __('One click to activate your account.'));
?>
<h1><?= __('Confirm your email address') ?></h1>
<p><?= __('Hi {0},', h($first_name ?? $username ?? '')) ?></p>
<p><?= __('Thanks for signing up! Please confirm that this is your email address to activate your account.') ?></p>
<?= $this->element('email/button', ['url' => $url, 'text' => __('Activate my account')]) ?>
<?= $this->element('email/fallback_link', ['url' => $url]) ?>
