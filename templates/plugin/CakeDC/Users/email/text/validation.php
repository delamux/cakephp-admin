<?php
/**
 * @var \Cake\View\View $this
 * @var string $activationUrl
 * @var string|null $first_name
 * @var string|null $username
 */
?>
<?= __('Hi {0},', $first_name ?? $username ?? '') ?>


<?= __('Thanks for signing up! Please confirm your email address to activate your account:') ?>


<?= $this->Url->build($activationUrl, ['fullBase' => true]);
