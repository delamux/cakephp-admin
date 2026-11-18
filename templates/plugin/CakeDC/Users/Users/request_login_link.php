<?php
/**
 * Passwordless sign-in request (CakeDC/Users override).
 *
 * @var \App\View\AppView $this
 */
use Cake\Core\Configure;

$this->assign('title', __('Sign-in link'));

ob_start();
echo $this->Form->create(null, ['class' => 'form grid gap-5']);
echo $this->Flash->render('auth');
echo $this->Form->control('email', ['type' => 'email', 'label' => __('Email'), 'required' => true, 'autofocus' => true, 'autocomplete' => 'email']);
if (Configure::read('Users.reCaptcha.login')) {
    echo $this->User->addReCaptcha();
}
echo $this->Form->button(__('Email me a link'), ['class' => 'w-full', 'icon' => 'mail']);
echo $this->Form->end();
$body = (string)ob_get_clean();

echo $this->Card->render($body, [
    'title' => __('Sign in without a password'),
    'description' => __('We will send a one-time link and code to your inbox.'),
    'headerOptions' => ['class' => 'text-center'],
    'footer' => '<p class="w-full text-center text-sm">' . $this->Html->link('← ' . __('Back to sign in'), ['action' => 'login'], ['class' => 'text-muted-foreground underline-offset-4 hover:underline', 'escape' => false]) . '</p>',
]);
