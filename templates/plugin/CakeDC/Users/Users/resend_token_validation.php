<?php
/**
 * Resend account validation email (CakeDC/Users override).
 *
 * @var \App\View\AppView $this
 * @var \CakeDC\Users\Model\Entity\User $user
 */
$this->assign('title', __('Resend validation email'));

ob_start();
echo $this->Form->create($user, ['class' => 'form grid gap-5']);
echo $this->Form->control('reference', ['label' => __('Email or username'), 'required' => true, 'autofocus' => true]);
echo $this->Form->button(__('Resend email'), ['class' => 'w-full', 'icon' => 'mail']);
echo $this->Form->end();
$body = (string)ob_get_clean();

echo $this->Card->render($body, [
    'title' => __('Didn\'t get the email?'),
    'description' => __('We will send you a new validation link.'),
    'headerOptions' => ['class' => 'text-center'],
    'footer' => '<p class="w-full text-center text-sm">' . $this->Html->link('← ' . __('Back to sign in'), ['action' => 'login'], ['class' => 'text-muted-foreground underline-offset-4 hover:underline', 'escape' => false]) . '</p>',
]);
