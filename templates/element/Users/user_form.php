<?php
/**
 * Shared admin user form (add / edit) for the CakeDC/Users templates.
 *
 * @var \App\View\AppView $this
 * @var \CakeDC\Users\Model\Entity\User $user
 * @var string $title
 * @var bool $withPassword
 */
ob_start();
echo $this->Form->create($user, ['class' => 'form grid gap-5']);
echo '<div class="grid gap-4 sm:grid-cols-2">';
echo $this->Form->control('first_name', ['label' => __('First name')]);
echo $this->Form->control('last_name', ['label' => __('Last name')]);
echo $this->Form->control('username', ['label' => __('Username')]);
echo $this->Form->control('email', ['label' => __('Email'), 'type' => 'email']);
echo '</div>';
if ($withPassword) {
    echo $this->Form->control('password', ['label' => __('Password'), 'autocomplete' => 'new-password']);
}
echo $this->Form->control('active', ['type' => 'checkbox', 'label' => __('Active (can sign in)')]);
echo '<div class="flex gap-2">'
    . $this->Form->button(__('Save'))
    . $this->Button->link(__('Cancel'), ['action' => 'index'], ['variant' => 'ghost'])
    . '</div>';
echo $this->Form->end();
$body = (string)ob_get_clean();

echo $this->Card->render($body, ['title' => $title, 'description' => __('Basic information of the account.')]);
