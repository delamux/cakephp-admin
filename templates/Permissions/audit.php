<?php
/**
 * Audit log of roles/permissions changes (superadmin only).
 *
 * @var \App\View\AppView $this
 * @var iterable<\Cake\Datasource\EntityInterface> $audits
 */
$this->assign('title', (string)$this->get('title'));
$this->start('header_actions');
echo $this->element('header_action', ['text' => __('Permissions'), 'url' => ['action' => 'index'], 'icon' => 'settings-2', 'variant' => 'outline']);
$this->end();

$labels = [
    'grants_changed' => __('Permissions changed'),
    'role_created' => __('Role created'),
    'role_updated' => __('Role updated'),
    'role_deleted' => __('Role deleted'),
    'sync' => __('Actions synced'),
    'audit_purged' => __('History purged'),
];
$keys = fn(?array $list, string $sign, string $variant): string => implode(' ', array_map(
    fn(string $key): string => $this->Badge->render($sign . ' ' . $key, ['variant' => $variant, 'class' => 'font-mono']),
    $list ?? [],
));

$rows = [];
foreach ($audits as $audit) {
    $details = (array)($audit->get('details') ?? []);
    $change = $keys($audit->get('added'), '+', 'secondary') . ' ' . $keys($audit->get('removed'), '−', 'destructive');
    if ($audit->get('event') === 'role_updated') {
        $change = implode(', ', array_map(fn($f, $v): string => h($f . ': ' . $v[0] . ' → ' . $v[1]), array_keys($details), $details));
    } elseif ($audit->get('event') === 'sync') {
        $change = h(__('{0} added, {1} orphaned, {2} restored', $details['added'] ?? 0, $details['orphaned'] ?? 0, $details['restored'] ?? 0));
        if (!empty($details['reset'])) {
            $change .= ' ' . $this->Badge->render(__('Default grants re-applied'), ['variant' => 'destructive']);
        }
    } elseif ($audit->get('event') === 'audit_purged') {
        $change = h(__('{0} entries older than {1} days deleted', $details['deleted'] ?? 0, $details['days'] ?? 0));
    } elseif (in_array($audit->get('event'), ['role_created', 'role_deleted'], true)) {
        $change = h((string)($details['name'] ?? ''));
    }
    $rows[] = [
        'when' => '<span class="whitespace-nowrap text-muted-foreground">' . h($audit->get('created')?->i18nFormat('d MMM yyyy, HH:mm')) . '</span>',
        'who' => '<span class="font-medium">' . h($audit->get('username')) . '</span>',
        'event' => $this->Badge->render($labels[$audit->get('event')] ?? (string)$audit->get('event'), ['variant' => 'outline']),
        'role' => $audit->get('role_slug') ? '<code class="text-sm">' . h($audit->get('role_slug')) . '</code>' : '<span class="text-muted-foreground">—</span>',
        'change' => '<div class="flex flex-wrap gap-1 text-sm">' . $change . '</div>',
    ];
}
?>
<div class="px-4 lg:px-6">
    <?= $this->Card->render(
        $rows === []
            ? $this->EmptyState->render(__('No changes yet'), ['icon' => 'file-text', 'description' => __('Changes to roles and permissions will be listed here.')])
            : '<div class="overflow-x-auto rounded-lg border">' . $this->Table->render([
                'when' => __('When'),
                'who' => __('Who'),
                'event' => __('Event'),
                'role' => __('Role'),
                'change' => __('Change (granted / revoked)'),
            ], $rows, ['escape' => false, 'headOptions' => ['class' => 'bg-muted']]) . '</div>',
        [
            'title' => __('Permissions history'),
            'description' => __('Who changed roles and permissions, and when. Granted actions are marked +, revoked ones −.'),
            'footer' => $this->Pagination->render(['counter' => true]),
            'footerOptions' => ['class' => 'justify-between'],
        ],
    ) ?>
</div>
