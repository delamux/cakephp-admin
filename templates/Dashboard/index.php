<?php
/**
 * Dashboard (inspired by shadcn "dashboard-01" / shadcnuikit default dashboard).
 *
 * @var \App\View\AppView $this
 * @var bool $isAdmin
 * @var bool $canViewUsers
 * @var \App\Service\UserRoleManager $roleManager
 * @var \CakeDC\Users\Model\Entity\User $currentUser
 * @var array<string, mixed>|null $summary
 * @var list<array{date: string, users: int}> $signups
 * @var list<\CakeDC\Users\Model\Entity\User> $recentUsers
 * @var list<\CakeDC\Users\Model\Entity\User> $administrators
 * @var int $periodDays
 */
use Cake\I18n\Date;
use Cake\I18n\DateTime;

$displayName = fn($user): string => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: (string)$user->username;
$today = DateTime::now();

if ($canViewUsers) {
    $this->start('breadcrumb');
    echo '<p class="hidden truncate text-xs text-muted-foreground sm:block">' . h($today->subDays($periodDays - 1)->i18nFormat('d MMM yyyy')) . ' – ' . h($today->i18nFormat('d MMM yyyy')) . '</p>';
    $this->end();
}

if (!$canViewUsers) : ?>
    <div class="px-4 lg:px-6">
        <?= $this->Card->render(
            '<p class="text-sm text-muted-foreground">' . __('You are signed in as {0}. Keep your account details and password up to date.', '<strong class="text-foreground">' . h($currentUser->username) . '</strong>') . '</p>',
            [
                'title' => __('Welcome, {0}', $displayName($currentUser)),
                'description' => __('Your workspace'),
                'footer' => $this->Button->link(__('My profile'), ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'profile'], ['icon' => 'circle-user'])
                    . ' ' . $this->Button->link(__('Change password'), ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'changePassword'], ['variant' => 'outline']),
                'footerOptions' => ['class' => 'flex gap-2'],
            ],
        ) ?>
    </div>
    <?php return; ?>
<?php endif;

$this->start('header_actions');
echo $this->element('header_action', ['text' => __('Export'), 'url' => ['controller' => 'Reports', 'action' => 'users', '_ext' => 'pdf', '?' => ['download' => 1]], 'icon' => 'arrow-down', 'variant' => 'outline']);
if ($isAdmin) {
    echo $this->element('header_action', ['text' => __('New user'), 'url' => ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'add'], 'icon' => 'plus']);
}
$this->end();

$growth = $summary['newUsersGrowth'];
$growthBadge = $growth === null
    ? $this->Badge->render(__('new'), ['variant' => 'outline'])
    : $this->Badge->render(($growth >= 0 ? '+' : '') . $this->Number->format($growth, ['precision' => 1]) . '%', ['variant' => 'outline', 'icon' => $growth >= 0 ? 'trending-up' : 'trending-down']);
$activeRate = $summary['total'] > 0 ? (int)round($summary['active'] / $summary['total'] * 100) : 0;

$stats = [
    [
        'label' => __('Total users'),
        'value' => $this->Number->format($summary['total']),
        'badge' => $this->Badge->render(__n('{0} admin', '{0} admins', $summary['admins'], $this->Number->format($summary['admins'])), ['variant' => 'outline', 'icon' => 'users']),
        'trend' => __('All registered accounts'),
        'note' => __('Including administrators'),
        'icon' => 'users',
    ],
    [
        'label' => __('New users'),
        'value' => $this->Number->format($summary['newUsers']),
        'badge' => $growthBadge,
        'trend' => $growth === null ? __('No signups in the previous period') : ($growth >= 0 ? __('Up vs previous {0} days', $periodDays) : __('Down vs previous {0} days', $periodDays)),
        'note' => __('Last {0} days', $periodDays),
        'icon' => $growth !== null && $growth < 0 ? 'trending-down' : 'trending-up',
    ],
    [
        'label' => __('Active accounts'),
        'value' => $this->Number->format($summary['active']),
        'badge' => $this->Badge->render($activeRate . '%', ['variant' => 'outline', 'icon' => 'circle-check']),
        'trend' => __('Email validated'),
        'note' => __('Can sign in'),
        'icon' => 'circle-check',
    ],
    [
        'label' => __('Pending validation'),
        'value' => $this->Number->format($summary['pending']),
        'badge' => $this->Badge->render($summary['pending'] > 0 ? __('action needed') : __('all clear'), ['variant' => $summary['pending'] > 0 ? 'secondary' : 'outline', 'icon' => $summary['pending'] > 0 ? 'circle-alert' : 'check']),
        'trend' => __('Waiting for email confirmation'),
        'note' => __('Accounts that cannot sign in yet'),
        'icon' => 'mail',
    ],
];

$chartRows = array_map(fn(array $row): array => [
    'label' => (new Date($row['date']))->i18nFormat('d MMM'),
    'users' => $row['users'],
], $signups);

$statusBadge = fn($user): string => $user->active
    ? $this->Badge->render(__('Active'), ['variant' => 'outline', 'class' => 'text-muted-foreground', 'icon' => $this->Icon->render('circle-check', ['class' => 'size-3.5 fill-green-500 text-background dark:fill-green-400', 'replaceClass' => true])])
    : $this->Badge->render(__('Pending'), ['variant' => 'outline', 'class' => 'text-muted-foreground', 'icon' => $this->Icon->render('loader', ['class' => 'size-3.5', 'replaceClass' => true])]);
$roleBadge = fn($user): string => $this->element('Users/role_badge', ['user' => $user]);

$rows = [];
foreach ($recentUsers as $i => $user) {
    $rows[] = [
        'user' => '<div class="flex items-center gap-3">' . $this->Avatar->render($displayName($user), ['size' => 'sm'])
            . '<div class="grid leading-tight"><span class="font-medium">' . h($displayName($user)) . '</span><span class="text-xs text-muted-foreground">' . h($user->email) . '</span></div></div>',
        'role' => $roleBadge($user),
        'status' => $statusBadge($user),
        'created' => '<span class="whitespace-nowrap text-muted-foreground" title="' . h($user->created?->i18nFormat('d MMM yyyy, HH:mm')) . '">'
            . h($user->created?->timeAgoInWords(['end' => '+1 month', 'format' => 'd MMM yyyy', 'accuracy' => ['minute' => 'minute', 'hour' => 'hour', 'day' => 'day', 'week' => 'day', 'month' => 'day']])) . '</span>',
        'actions' => $this->DropdownMenu->render('', [
            ['label' => __('View'), 'url' => ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'view', $user->id], 'icon' => 'user'],
            ...($roleManager->canManage($currentUser, $user)
                ? [['label' => __('Edit'), 'url' => ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'edit', $user->id], 'icon' => 'pencil']]
                : []),
        ], [
            'id' => 'recent-user-' . $i,
            'align' => 'end',
            'trigger' => ['variant' => 'ghost', 'size' => 'icon-sm', 'icon' => 'ellipsis-vertical', 'class' => 'btn text-muted-foreground', 'aria-label' => __('Actions')],
            'popoverOptions' => ['class' => 'min-w-32'],
        ]),
    ];
}

$admins = $administrators;
?>
<div class="grid grid-cols-1 gap-4 px-4 lg:px-6 @xl/main:grid-cols-2 @5xl/main:grid-cols-4">
    <?php foreach ($stats as $stat) : ?>
        <?= $this->Card->render(null, [
            'class' => '@container/card bg-linear-to-t from-primary/5 to-card shadow-xs',
            'header' => '<p>' . h($stat['label']) . '</p><h2 class="!text-2xl !font-semibold tabular-nums @[250px]/card:!text-3xl">' . h($stat['value']) . '</h2>'
                . '<div class="absolute right-6 top-0">' . $stat['badge'] . '</div>',
            'headerOptions' => ['class' => 'relative'],
            'footer' => '<div class="flex items-center gap-2 font-medium">' . h($stat['trend']) . ' ' . $this->Icon->render($stat['icon']) . '</div>'
                . '<div class="text-muted-foreground">' . h($stat['note']) . '</div>',
            'footerOptions' => ['class' => 'flex flex-col items-start gap-1.5 text-sm'],
        ]) ?>
    <?php endforeach; ?>
</div>

<div class="px-4 lg:px-6">
    <?= $this->Card->render(
        $this->Chart->render($chartRows, [
            'users' => ['label' => __('Signups'), 'color' => 'var(--chart-1)', 'dataset' => ['borderRadius' => 4, 'maxBarThickness' => 18]],
        ], [
            'type' => 'bar',
            'labelKey' => 'label',
            'class' => 'h-[250px] w-full',
            'canvasOptions' => ['aria-label' => __('Signups per day')],
            'chartOptions' => ['scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0, 'color' => 'var(--muted-foreground)']],
                'x' => ['grid' => ['display' => false], 'border' => ['display' => false], 'ticks' => ['autoSkip' => true, 'maxTicksLimit' => 10, 'maxRotation' => 0, 'color' => 'var(--muted-foreground)']],
            ]],
        ]),
        [
            'title' => __('Signups'),
            'description' => __('New accounts per day, last {0} days', $periodDays),
            'action' => $this->Badge->render(__('{0} total', $this->Number->format(array_sum(array_column($signups, 'users')))), ['variant' => 'outline']),
            'bodyOptions' => ['class' => 'px-2 pt-4 sm:px-6 sm:pt-6'],
        ],
    ) ?>
</div>

<div class="grid grid-cols-1 gap-4 px-4 lg:px-6 @5xl/main:grid-cols-3">
    <?= $this->Card->render(
        $rows === []
            ? $this->EmptyState->render(__('No users yet'), ['icon' => 'users', 'description' => __('New accounts will appear here.')])
            : '<div class="overflow-x-auto rounded-lg border">' . $this->Table->render([
                'user' => __('User'),
                'role' => __('Role'),
                'status' => __('Status'),
                'created' => __('Joined'),
                'actions' => ['label' => '', 'class' => 'w-8'],
            ], $rows, ['escape' => false, 'headOptions' => ['class' => 'bg-muted']]) . '</div>',
        [
            'class' => '@5xl/main:col-span-2',
            'title' => __('Latest users'),
            'description' => __('The most recent accounts created in the application.'),
            'action' => $this->Button->link(__('View all'), ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'index'], ['variant' => 'outline', 'size' => 'sm']),
        ],
    ) ?>

    <?php
    $team = '';
    foreach ($admins as $admin) {
        $team .= '<li class="flex items-center gap-3">' . $this->Avatar->render($displayName($admin))
            . '<div class="grid flex-1 leading-tight"><span class="text-sm font-medium">' . h($displayName($admin)) . '</span><span class="text-xs text-muted-foreground">' . h($admin->email) . '</span></div>'
            . $roleBadge($admin) . '</li>';
    }
    echo $this->Card->render(
        $team === ''
            ? '<p class="text-sm text-muted-foreground">' . __('No administrators yet.') . '</p>'
            : '<ul class="flex flex-col gap-4">' . $team . '</ul>',
        [
            'title' => __('Team'),
            'description' => __('Administrators with full access.'),
            'footer' => $this->Progress->render($activeRate, ['aria-label' => __('Active accounts')]) . '<p class="text-xs text-muted-foreground">' . __('{0}% of accounts are active', $activeRate) . '</p>',
            'footerOptions' => ['class' => 'flex w-full flex-col items-start gap-2'],
        ],
    );
    ?>
</div>
