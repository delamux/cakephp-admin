<?php
/**
 * Permissions per role (superadmin only): one tab per editable role, one select per action
 * (not allowed, allowed, or allowed under a rule). System actions are read only, behind a button.
 *
 * @var \App\View\AppView $this
 * @var array<string, string> $roles Editable roles (slug => name), superadmin excluded
 * @var string $active Slug of the open tab
 * @var array<string, list<array{key: string, action: string, rules: array<string, string>}>> $groups Editable actions per controller
 * @var array<string, list<array{key: string, action: string, locked: string}>> $systemGroups System actions per controller
 * @var array<string, array<string, string>> $values role slug => [action key => select value]
 * @var int $orphans Actions no longer present in the code
 */
use App\UseCase\Permissions\PermissionMatrix;

$this->assign('title', (string)$this->get('title'));
$this->start('header_actions');
echo $this->Button->render(__('System actions'), [
    'type' => 'button', 'variant' => 'outline', 'size' => 'sm', 'icon' => 'lock',
    'data-reveal' => 'system-actions', 'aria-controls' => 'system-actions', 'aria-expanded' => 'false',
]);
echo $this->Form->postButton(
    '<span class="sr-only sm:not-sr-only">' . __('Sync actions') . '</span>',
    ['action' => 'sync'],
    ['variant' => 'outline', 'size' => 'sm', 'escapeTitle' => false, 'title' => __('Discover controllers and actions again'), 'form' => ['class' => 'inline']],
);
$this->end();

$lockLabels = [
    PermissionMatrix::LOCKED_PUBLIC => __('Public (no sign-in)'),
    PermissionMatrix::LOCKED_ALL_ROLES => __('Every signed-in user'),
    PermissionMatrix::LOCKED_SUPERADMIN => __('Superadmin only'),
];

// A switch when the action can only be allowed or not; a select when some rule can decide it.
// An unchecked switch is not submitted: UpdateRolePermissions treats a missing action as DENY.
$select = function (string $slug, array $action) use ($values, $roles): string {
    $current = $values[$slug][$action['key']] ?? PermissionMatrix::DENY;
    $name = 'grants[' . h($action['key']) . ']';
    $label = ' aria-label="' . h(__('{0}: {1}', $roles[$slug], $action['key'])) . '"';
    if ($action['rules'] === []) {
        return '<input type="checkbox" role="switch" class="input" name="' . $name . '" value="' . PermissionMatrix::ALLOW . '"'
            . $label . ($current !== PermissionMatrix::DENY ? ' checked' : '') . '>';
    }
    $options = [PermissionMatrix::DENY => __('Not allowed'), PermissionMatrix::ALLOW => __('Allowed')] + $action['rules'];
    $html = '<select class="select w-full sm:w-56" name="' . $name . '"' . $label . '>';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . h((string)$value) . '"' . ((string)$value === $current ? ' selected' : '') . '>' . h($label) . '</option>';
    }

    return $html . '</select>';
};

$tabs = [];
foreach ($roles as $slug => $name) {
    $granted = count(array_filter($values[$slug] ?? [], fn(string $v): bool => $v !== PermissionMatrix::DENY));
    $cards = '';
    foreach ($groups as $controller => $actions) {
        $rows = [];
        foreach ($actions as $action) {
            $rows[] = [
                'action' => '<code class="text-sm">' . h($action['action']) . '</code>',
                'permission' => $select($slug, $action),
            ];
        }
        $cards .= $this->Card->render(
            '<div class="overflow-x-auto rounded-lg border">' . $this->Table->render([
                'action' => __('Action'),
                'permission' => ['label' => __('Permission'), 'class' => 'w-80'],
            ], $rows, ['escape' => false, 'headOptions' => ['class' => 'bg-muted']]) . '</div>',
            ['title' => $controller, 'headerOptions' => ['class' => 'font-mono']],
        );
    }
    $tabs[] = [
        'id' => $slug,
        'tab' => h($name) . ' ' . $this->Badge->render((string)$granted, ['variant' => 'secondary']),
        'escape' => false,
        'panel' => $this->Form->create(null, [
                'id' => 'permissions-form-' . $slug,
                'url' => ['action' => 'index'],
                'class' => 'mt-4 flex flex-col gap-4',
                // htmx: save without reloading; the screen is swapped and the flash comes as a toast
                'hx-post' => $this->Url->build(['action' => 'index']),
                'hx-target' => '#permissions-matrix',
                'hx-select' => '#permissions-matrix',
                'hx-swap' => 'outerHTML',
            ])
            . $this->Form->hidden('role', ['value' => $slug])
            . $cards
            . '<div class="sticky bottom-4 flex justify-end">'
            . $this->Form->button(__('Save permissions of {0}', $name), ['icon' => 'save'])
            . '</div>'
            . $this->Form->end(),
    ];
}

$system = '';
foreach ($systemGroups as $controller => $actions) {
    $rows = [];
    foreach ($actions as $action) {
        $rows[] = [
            'action' => '<code class="text-sm">' . h($action['action']) . '</code>',
            'rule' => $this->Badge->render($lockLabels[$action['locked']] ?? $action['locked'], ['variant' => 'outline']),
        ];
    }
    $system .= '<div class="grid gap-2"><p class="font-mono text-sm font-medium">' . h($controller) . '</p>'
        . '<div class="overflow-x-auto rounded-lg border">' . $this->Table->render([
            'action' => __('Action'),
            'rule' => ['label' => __('Decided by'), 'class' => 'w-56'],
        ], $rows, ['escape' => false, 'headOptions' => ['class' => 'bg-muted']]) . '</div></div>';
}
?>
<div class="flex flex-col gap-4 px-4 lg:px-6" id="permissions-matrix">
    <?= $this->Alert->render(
        __('Superadmin can always do everything'),
        __('Choose, for each role, whether an action is not allowed, allowed, or allowed under a rule (for example, only the own account). Public and system actions are fixed in config/permissions.php.'),
        ['icon' => 'info'],
    ) ?>
    <?php if ($orphans > 0) : ?>
        <?= $this->Alert->warning(__n('{0} action no longer exists in the code and is ignored.', '{0} actions no longer exist in the code and are ignored.', $orphans, $orphans)) ?>
    <?php endif; ?>

    <div id="system-actions" hidden>
        <?= $this->Card->render(
            '<div class="grid gap-4 md:grid-cols-2">' . $system . '</div>',
            [
                'title' => __('Public and system actions'),
                'description' => __('Decided by config/permissions.php or reserved to superadmin. Read only.'),
                'footer' => $this->Button->render(__('Hide'), ['type' => 'button', 'variant' => 'ghost', 'size' => 'sm', 'data-reveal-close' => 'system-actions']),
            ],
        ) ?>
    </div>

    <?= $this->Tabs->render($tabs, ['id' => 'permissions-roles', 'active' => $active, 'variant' => 'line']) ?>
</div>
