<?php
/**
 * One-time login link email (CakeDC/Users override, HTML only in the plugin mailer).
 *
 * Note: the plugin token is a 32-char hex string (not a short typeable code), so only the link is offered.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 * @var array|string $loginLink
 * @var string $token
 */
$url = $this->Url->build($loginLink, ['fullBase' => true]);
$this->assign('title', __('Your sign-in link'));
$this->assign('preheader', __('Sign in with one click, no password needed.'));
?>
<h1><?= __('Your sign-in link') ?></h1>
<p><?= __('Hi {0},', h($user->first_name ?? $user->username ?? '')) ?></p>
<p><?= __('Use the button below to sign in without a password.') ?></p>
<?= $this->element('email/button', ['url' => $url, 'text' => __('Sign in')]) ?>
<p class="muted"><?= __('The link can only be used once and expires in a few minutes.') ?></p>
<?= $this->element('email/fallback_link', ['url' => $url]) ?>
