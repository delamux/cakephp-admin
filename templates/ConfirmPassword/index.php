<?php
/**
 * Confirm the password before a sensitive action (App\Middleware\ConfirmPasswordMiddleware).
 *
 * @var \App\View\AppView $this
 * @var string $redirect Local path to go back to.
 */
$this->assign('title', (string)$this->get('title'));

ob_start();
echo $this->Form->create(null, ['url' => ['action' => 'index', '?' => ['redirect' => $redirect]], 'class' => 'form grid gap-5']);
echo $this->Form->control('password', [
    'type' => 'password',
    'required' => true,
    'label' => __('Password'),
    'autocomplete' => 'current-password',
    'autofocus' => true,
]);
echo '<div class="flex gap-2">'
    . $this->Form->button(__('Confirm'))
    . $this->Button->link(__('Cancel'), '/', ['variant' => 'outline'])
    . '</div>';
echo $this->Form->end();
$body = (string)ob_get_clean();
?>
<div class="px-4 lg:px-6">
    <div class="max-w-xl"><?= $this->Card->render($body, [
        'title' => __('Confirm your password'),
        'description' => __('You are about to change something sensitive. Type your password again to continue; you will not be asked again for a few minutes.'),
    ]) ?></div>
</div>
