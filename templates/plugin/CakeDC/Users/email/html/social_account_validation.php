<?php
/**
 * Social account validation email (CakeDC/Users override).
 *
 * @var \App\View\AppView $this
 * @var \CakeDC\Users\Model\Entity\User $user
 * @var \Cake\Datasource\EntityInterface $socialAccount
 * @var array $activationUrl
 */
$url = $this->Url->build($activationUrl, ['fullBase' => true]);
$this->assign('title', __('Activate your social login'));
$this->assign('preheader', __('Confirm the {0} account linked to your profile.', h((string)$socialAccount['provider'])));
?>
<h1><?= __('Activate your social login') ?></h1>
<p><?= __('Hi {0},', h($user['first_name'] ?? $user['username'] ?? '')) ?></p>
<p><?= __('Confirm that you want to sign in with your {0} account.', '<strong>' . h((string)$socialAccount['provider']) . '</strong>') ?></p>
<?= $this->element('email/button', ['url' => $url, 'text' => __('Activate social login')]) ?>
<?= $this->element('email/fallback_link', ['url' => $url]) ?>
