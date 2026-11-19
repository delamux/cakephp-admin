<?php
/**
 * Invite a user (App\Controller\InvitationsController::add()).
 *
 * @var \App\View\AppView $this
 * @var string|null $error
 * @var array<string, string> $roles Roles the signed-in user may assign.
 */
$this->assign('title', __('Invite user'));
$this->start('breadcrumb');
echo $this->Breadcrumb->render([
    __('Users') => ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'index'],
    __('Invite user'),
]);
$this->end();

ob_start();
echo $this->Form->create(null, ['class' => 'form grid gap-5']);
if ($error !== null) {
    echo $this->Alert->error($error);
}
echo $this->Form->control('email', ['type' => 'email', 'required' => true, 'label' => __('Email'), 'autocomplete' => 'off']);
echo '<div class="grid gap-4 sm:grid-cols-2">';
echo $this->Form->control('first_name', ['label' => __('First name'), 'required' => false]);
echo $this->Form->control('last_name', ['label' => __('Last name'), 'required' => false]);
echo '</div>';
echo $this->Form->control('role', ['type' => 'select', 'options' => $roles, 'default' => 'user', 'label' => __('Role')]);
echo '<div class="flex gap-2">'
    . $this->Form->button(__('Send invitation'))
    . $this->Button->link(__('Cancel'), ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'index'], ['variant' => 'ghost'])
    . '</div>';
echo $this->Form->end();
$body = (string)ob_get_clean();
?>
<div class="px-4 lg:px-6">
    <div class="max-w-2xl">
        <?= $this->Card->render($body, [
            'title' => __('Invite user'),
            'description' => __('They will get an email with a link to choose their password. Until then they cannot sign in.'),
        ]) ?>
    </div>
</div>
