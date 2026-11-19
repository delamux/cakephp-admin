<?php
/**
 * @var \Cake\View\View $this
 * @var string $name
 * @var string $appName
 * @var array<string|int, mixed> $url
 * @var int $days
 */
?>
<?= __('Hi {0},', $name) ?>


<?= __('An account has been created for you in {0}. Open this link to choose your password and activate it:', $appName) ?>


<?= $this->Url->build($url, ['fullBase' => true]) ?>


<?= __n('This link expires in {0} day and can be used only once.', 'This link expires in {0} days and can be used only once.', $days, $days);
