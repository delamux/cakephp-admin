<?php
/**
 * Permissions matrix: actions × roles (superadmin only).
 *
 * @var \App\View\AppView $this
 * @var array<string, string> $roles Editable roles (slug => name), superadmin excluded
 * @var array<string, list<array{key: string, action: string, locked: string|null}>> $groups Actions per controller
 * @var array<string, array<string, int>> $grants role slug => [action key => index]
 * @var int $orphans Actions no longer present in the code
 */
$this->assign('title', (string)$this->get('title'));
$this->start('header_actions');
echo $this->Form->postButton(
    '<span class="sr-only sm:not-sr-only">' . __('Sync actions') . '</span>',
    ['action' => 'sync'],
    ['variant' => 'outline', 'size' => 'sm', 'escapeTitle' => false, 'title' => __('Discover controllers and actions again'), 'form' => ['class' => 'inline']],
);
echo $this->Form->button(__('Save permissions'), ['form' => 'permissions-form', 'size' => 'sm']);
$this->end();

$roleSlugs = array_keys($roles);
$lockLabels = [
    \App\UseCase\Permissions\PermissionMatrix::LOCKED_PUBLIC => __('Public'),
    \App\UseCase\Permissions\PermissionMatrix::LOCKED_ALL_ROLES => __('All roles'),
    \App\UseCase\Permissions\PermissionMatrix::LOCKED_SUPERADMIN => __('Superadmin only'),
];
?>
<div class="flex flex-col gap-4 px-4 lg:px-6" id="permissions-matrix">
    <?= $this->Alert->render(
        __('Superadmin can always do everything'),
        __('Tick what each role can do. Locked rows are system rules: public actions (sign in, register…), actions for all roles (dashboard, profile) and the access-control screens (superadmin only).'),
        ['icon' => 'info'],
    ) ?>
    <?php if ($orphans > 0) : ?>
        <?= $this->Alert->warning(__n('{0} action no longer exists in the code and is ignored.', '{0} actions no longer exist in the code and are ignored.', $orphans, $orphans)) ?>
    <?php endif; ?>

    <?= $this->Form->create(null, [
        'id' => 'permissions-form',
        'url' => ['action' => 'index'],
        // htmx: save without reloading; the matrix is swapped and the flash comes as a toast
        'hx-post' => $this->Url->build(['action' => 'index']),
        'hx-target' => '#permissions-matrix',
        'hx-select' => '#permissions-matrix',
        'hx-swap' => 'outerHTML',
    ]) ?>
    <div class="flex flex-col gap-4">
        <?php foreach ($groups as $controller => $actions) :
            $rows = [];
            foreach ($actions as $action) {
                $row = ['action' => '<code class="text-sm">' . h($action['action']) . '</code>'
                    . ($action['locked'] !== null ? ' ' . $this->Badge->render($lockLabels[$action['locked']] ?? $action['locked'], ['variant' => 'outline']) : '')];
                foreach ($roleSlugs as $slug) {
                    $row[$slug] = $action['locked'] !== null
                        ? '<span class="text-muted-foreground" title="' . h(__('Always available')) . '">—</span>'
                        : '<input type="checkbox" class="input" name="grants[' . h($slug) . '][]" value="' . h($action['key']) . '"'
                            . (isset($grants[$slug][$action['key']]) ? ' checked' : '')
                            . ' aria-label="' . h(__('{0}: {1}', $roles[$slug], $action['key'])) . '">';
                }
                $rows[] = $row;
            }
            $columns = ['action' => __('Action')];
            foreach ($roles as $slug => $name) {
                $columns[$slug] = ['label' => $name, 'class' => 'w-28 text-center'];
            }
            ?>
            <?= $this->Card->render(
                '<div class="overflow-x-auto rounded-lg border">' . $this->Table->render($columns, $rows, ['escape' => false, 'headOptions' => ['class' => 'bg-muted']]) . '</div>',
                ['title' => $controller, 'headerOptions' => ['class' => 'font-mono']],
            ) ?>
        <?php endforeach; ?>
    </div>
    <div class="flex justify-end"><?= $this->Form->button(__('Save permissions')) ?></div>
    <?= $this->Form->end() ?>
</div>
