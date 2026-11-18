<?php
/**
 * @var \Cake\View\View $this
 * @var string $activationUrl
 * @var string|null $first_name
 * @var string|null $username
 */
?>
<?= __('Hi {0},', $first_name ?? $username ?? '') ?>


<?= __('We received a request to reset the password of your account. Open this link to choose a new one:') ?>


<?= $this->Url->build($activationUrl, ['fullBase' => true]) ?>


<?= __('If you did not request a password reset, no action is needed.');
