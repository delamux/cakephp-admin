<?php
/**
 * Accept an invitation: choose the password (public, auth layout).
 *
 * @var \App\View\AppView $this
 * @var string $email
 * @var string|null $error
 */
$this->assign('title', __('Accept invitation'));

ob_start();
echo $this->Form->create(null, ['class' => 'form grid gap-5']);
echo $this->Flash->render();
if ($error !== null) {
    echo $this->Alert->error($error);
}
echo '<p class="text-sm text-muted-foreground">' . __('Account: {0}', '<span class="font-medium text-foreground">' . h($email) . '</span>') . '</p>';
echo $this->Form->control('password', ['type' => 'password', 'required' => true, 'label' => __('Password'), 'autocomplete' => 'new-password']);
echo $this->Form->control('password_confirm', ['type' => 'password', 'required' => true, 'label' => __('Confirm password'), 'autocomplete' => 'new-password']);
echo $this->Form->button(__('Activate account'), ['class' => 'w-full']);
echo $this->Form->end();
$body = (string)ob_get_clean();

echo $this->Card->render($body, [
    'title' => __('Choose your password'),
    'description' => __('Use at least 8 characters. A long passphrase is best.'),
    'headerOptions' => ['class' => 'text-center'],
]);
