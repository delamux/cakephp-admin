<?php
/**
 * Sign up (CakeDC/Users override).
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 */
use Cake\Core\Configure;

$this->assign('title', __('Create an account'));

ob_start();
echo $this->Form->create($user, ['class' => 'form grid gap-5']);
echo '<div class="grid grid-cols-2 gap-4">';
echo $this->Form->control('first_name', ['label' => __('First name'), 'autocomplete' => 'given-name']);
echo $this->Form->control('last_name', ['label' => __('Last name'), 'autocomplete' => 'family-name']);
echo '</div>';
echo $this->Form->control('username', ['label' => __('Username'), 'autocomplete' => 'username']);
echo $this->Form->control('email', ['label' => __('Email'), 'autocomplete' => 'email']);
echo $this->Form->control('password', ['label' => __('Password'), 'id' => 'new-password', 'autocomplete' => 'new-password']);
if (Configure::read('Users.passwordMeter.enabled')) {
    echo $this->User->addPasswordMeter();
}
echo $this->Form->control('password_confirm', ['type' => 'password', 'required' => true, 'label' => __('Confirm password'), 'autocomplete' => 'new-password']);
if (Configure::read('Users.Tos.required')) {
    echo $this->Form->control('tos', ['type' => 'checkbox', 'label' => __('I accept the terms of service'), 'required' => true]);
}
if (Configure::read('Users.reCaptcha.registration')) {
    echo $this->User->addReCaptcha();
}
echo $this->Form->button(__('Create account'), ['id' => 'btn-submit', 'class' => 'w-full']);
echo $this->Form->end();
$body = (string)ob_get_clean();

echo $this->Card->render($body, [
    'title' => __('Create an account'),
    'description' => __('Enter your details below to get started'),
    'headerOptions' => ['class' => 'text-center'],
    'footer' => '<p class="w-full text-center text-sm text-muted-foreground">' . __('Already have an account?') . ' '
        . $this->Html->link(__('Sign in'), ['action' => 'login'], ['class' => 'font-medium text-foreground underline underline-offset-4']) . '</p>',
]);
