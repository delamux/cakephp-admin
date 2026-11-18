<?php
/**
 * Roles list (superadmin only).
 *
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Role> $roles
 * @var array<string, int> $counts Users per role slug
 */
$this->assign('title', (string)$this->get('title'));
$this->start('header_actions');
echo $this->element('header_action', ['text' => __('Permissions'), 'url' => ['controller' => 'Permissions', 'action' => 'index'], 'icon' => 'settings-2', 'variant' => 'outline']);
echo $this->element('header_action', ['text' => __('New role'), 'url' => ['action' => 'add'], 'icon' => 'plus']);
$this->end();

$rows = [];
foreach ($roles as $role) {
    $users = (int)($counts[$role->slug] ?? 0);
    $deletable = !$role->protected && $users === 0;
    $rows[] = [
        'name' => '<div class="grid leading-tight"><span class="font-medium">' . h($role->name) . '</span>'
            . '<code class="text-xs text-muted-foreground">' . h($role->slug) . '</code></div>',
        'users' => $this->Badge->render(__n('{0} user', '{0} users', $users, $users), ['variant' => 'outline']),
        'type' => $role->protected
            ? $this->Badge->render(__('Protected'), ['variant' => 'secondary', 'icon' => 'circle-alert'])
            : '<span class="text-muted-foreground">' . __('Custom') . '</span>',
        'actions' => '<div class="flex justify-end gap-1">'
            . ($role->protected ? '' : $this->Button->link(__('Edit'), ['action' => 'edit', $role->id], ['variant' => 'ghost', 'size' => 'sm', 'icon' => 'pencil']))
            . ($deletable
                ? $this->Form->postButton(__('Delete'), ['action' => 'delete', $role->id], [
                    'variant' => 'ghost',
                    'size' => 'sm',
                    'class' => 'text-destructive',
                    'data-confirm' => __('Delete role {0}?', $role->name),
                ])
                : '')
            . '</div>',
    ];
}
?>
<div class="px-4 lg:px-6">
    <?= $this->Card->render(
        '<div class="overflow-x-auto rounded-lg border">' . $this->Table->render([
            'name' => __('Role'),
            'users' => __('Users'),
            'type' => ['label' => __('Type'), 'class' => 'hidden @xl/main:table-cell'],
            'actions' => ['label' => '', 'class' => 'text-right'],
        ], $rows, ['escape' => false, 'headOptions' => ['class' => 'bg-muted']]) . '</div>',
        [
            'title' => __('Roles'),
            'description' => __('Roles assigned to users. Their permissions are managed in the permissions screen. A role with users cannot be deleted.'),
        ],
    ) ?>
</div>
