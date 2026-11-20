<?php
/**
 * Invitation (App\Mailer\UsersMailer::invitation()).
 *
 * @var \Cake\View\View $this
 * @var string $name
 * @var string $appName
 * @var array<string|int, mixed> $url
 * @var int $days
 */
$link = $this->Url->build($url, ['fullBase' => true]);
$this->assign('title', __('You have been invited to {0}', $appName));
$this->assign('preheader', __('Choose a password to activate your account.'));
?>
<h1><?= __('You have been invited to {0}', h($appName)) ?></h1>
<p><?= __('Hi {0},', h($name)) ?></p>
<p><?= __('An account has been created for you. Click the button below to choose your password and activate it.') ?></p>
<?= $this->element('email/button', ['url' => $link, 'text' => __('Accept invitation')]) ?>
<p class="muted"><?= __n('This link expires in {0} day and can be used only once.', 'This link expires in {0} days and can be used only once.', $days, $days) ?></p>
<?= $this->element('email/fallback_link', ['url' => $link]) ?>
