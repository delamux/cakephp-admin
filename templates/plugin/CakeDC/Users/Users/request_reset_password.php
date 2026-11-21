<?php
/**
 * Forgot password (CakeDC/Users override).
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 */
$this->assign('title', __('Forgot password'));

ob_start();
echo $this->Form->create($user, ['class' => 'form grid gap-5']);
echo $this->Flash->render('auth');
echo $this->Form->control('reference', ['label' => __('Email or username'), 'required' => true, 'autofocus' => true]);
echo $this->Form->button(__('Send reset link'), ['class' => 'w-full', 'icon' => 'mail']);
echo $this->Form->end();
$body = (string)ob_get_clean();

echo $this->Card->render($body, [
    'title' => __('Forgot your password?'),
    'description' => __('We will email you a link to choose a new one.'),
    'headerOptions' => ['class' => 'text-center'],
    'footer' => '<p class="w-full text-center text-sm">' . $this->Html->link('← ' . __('Back to sign in'), ['action' => 'login'], ['class' => 'text-muted-foreground underline-offset-4 hover:underline', 'escape' => false]) . '</p>',
]);
