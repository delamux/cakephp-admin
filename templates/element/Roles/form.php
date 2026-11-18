<?php
/**
 * Role form (add / edit).
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Role $role
 * @var bool $slugLocked The slug cannot change (protected role or role with users)
 */
ob_start();
echo $this->Form->create($role, ['class' => 'form grid gap-5']);
echo $this->Form->control('name', ['label' => __('Name'), 'required' => true]);
echo $this->Form->control('slug', ['label' => __('Identifier'), 'required' => true, 'disabled' => $slugLocked]);
echo '<p class="-mt-3 text-sm text-muted-foreground">'
    . ($slugLocked
        ? __('The identifier cannot change because users have this role.')
        : __('Lowercase letters, numbers, "-" or "_". It is stored on each user.'))
    . '</p>';
echo '<div class="flex gap-2">' . $this->Form->button(__('Save')) . $this->Button->link(__('Cancel'), ['action' => 'index'], ['variant' => 'ghost']) . '</div>';
echo $this->Form->end();
$body = (string)ob_get_clean();

echo $this->Card->render($body, ['title' => __('Role'), 'description' => __('After creating it, choose what it can do in the permissions screen.')]);
