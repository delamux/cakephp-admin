<?php
/**
 * @var \Cake\View\View $this
 * @var \CakeDC\Users\Model\Entity\User $user
 * @var \Cake\Datasource\EntityInterface $socialAccount
 * @var array $activationUrl
 */
?>
<?= __('Hi {0},', $user['first_name'] ?? $user['username'] ?? '') ?>


<?= __('Confirm that you want to sign in with your {0} account:', (string)$socialAccount['provider']) ?>


<?= $this->Url->build($activationUrl, ['fullBase' => true]);
