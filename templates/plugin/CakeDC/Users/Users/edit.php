<?php
/**
 * Edit user (CakeDC/Users override, admins only).
 *
 * @var \App\View\AppView $this
 * @var string $tableAlias
 * @var \App\Service\UserRoleManager $roleManager
 * @var \CakeDC\Users\Model\Entity\User $currentUser
 */

$user = ${$tableAlias};
$this->assign('title', __('Edit {0}', $user->username));
$this->start('breadcrumb');
echo $this->Breadcrumb->render([__('Users') => ['action' => 'index'], h($user->username) => ['action' => 'view', $user->id], __('Edit')]);
$this->end();

$isSelf = (string)$currentUser->id === (string)$user->id;
$canChangeRole = $roleManager->canManage($currentUser, $user) && !$isSelf;

// Role: own form posting to UserRolesController (role is not mass-assignable in the CRUD).
// Options = roles the logged-in user may assign (only superadmins see/grant `superadmin`).
if (!$canChangeRole) {
    $roleBody = '<p class="text-sm text-muted-foreground">' . __('You cannot change your own role. Ask another administrator.') . '</p>';
    $roleFooter = null;
} else {
    $roleBody = $this->Form->create(null, ['url' => ['_name' => 'user-roles:edit', 'id' => $user->id], 'id' => 'role-form', 'class' => 'form grid gap-4'])
        . $this->Form->control('role', [
            'type' => 'select',
            'options' => $roleManager->assignableRoles($currentUser),
            'value' => $user->role,
            'label' => __('Role'),
        ])
        . $this->Form->end();
    $roleFooter = $this->Form->button(__('Update role'), ['form' => 'role-form', 'variant' => 'outline']);
}
?>
<div class="grid gap-4 px-4 lg:px-6 @3xl/main:grid-cols-3">
    <div class="@3xl/main:col-span-2">
        <?= $this->element('Users/user_form', ['user' => $user, 'title' => __('Account details'), 'withPassword' => false]) ?>
    </div>
    <div class="flex flex-col gap-4">
        <?= $this->Card->render($roleBody, [
            'title' => __('Role'),
            'description' => __('Superadmin: everything · Administrator: users and roles · Editor: read-only users and reports · User: own account.'),
            'action' => $this->element('Users/role_badge', ['user' => $user]),
            'footer' => $roleFooter,
        ]) ?>
        <?php if ($roleManager->canChangePasswordOf($currentUser, $user)) : ?>
            <?= $this->Card->render(
                '<p class="text-sm text-muted-foreground">' . __('Set a new password for this user. They will need to use it on their next sign in.') . '</p>',
                [
                    'title' => __('Password'),
                    'footer' => $this->Button->link(__('Change password'), ['action' => 'changePassword', $user->id], ['variant' => 'outline', 'icon' => 'settings-2']),
                ],
            ) ?>
        <?php endif; ?>
        <?php if ($roleManager->canDelete($currentUser, $user)) : ?>
            <?= $this->Card->render(
                '<p class="text-sm text-muted-foreground">' . __('Permanently delete this account and all its data.') . '</p>',
                [
                    'title' => __('Danger zone'),
                    'class' => 'border-destructive/40',
                    'footer' => $this->Form->postButton(__('Delete user'), ['action' => 'delete', $user->id], [
                        'variant' => 'destructive',
                        'data-confirm' => __('Delete user {0}? This cannot be undone.', $user->username),
                    ]),
                ],
            ) ?>
        <?php endif; ?>
    </div>
</div>
