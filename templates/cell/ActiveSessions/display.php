<?php
/**
 * "Where you're signed in" (App\View\Cell\ActiveSessionsCell).
 *
 * @var \App\View\AppView $this
 * @var list<array{key: string, current: bool, ip: string|null, browser: string, lastActive: \Cake\I18n\DateTime}> $sessions
 */
$items = '';
$others = 0;
foreach ($sessions as $session) {
    $action = '';
    if ($session['current']) {
        $action = $this->Badge->render(__('This device'), ['variant' => 'secondary']);
    } else {
        $others++;
        $action = $this->Form->postButton(__('Sign out'), ['controller' => 'AccountSessions', 'action' => 'signOut', $session['key']], [
            'class' => 'btn-sm-outline',
            'data-confirm' => __('Sign out this session?'),
        ]);
    }
    $items .= '<li class="flex items-center justify-between gap-3 py-3">'
        . '<div class="flex min-w-0 items-center gap-3">' . $this->Icon->render('monitor', ['class' => 'size-5 shrink-0 text-muted-foreground'])
        . '<div class="min-w-0"><p class="truncate text-sm font-medium">' . h($session['browser']) . '</p>'
        . '<p class="truncate text-xs text-muted-foreground"><span class="font-mono">' . h($session['ip'] ?? '—') . '</span> · '
        . h(__('Active {0}', $session['lastActive']->timeAgoInWords(['end' => '+1 week', 'format' => 'd MMM yyyy, HH:mm', 'accuracy' => 'minute'])))
        . '</p></div></div>'
        . '<div class="shrink-0">' . $action . '</div></li>';
}

$body = $items === ''
    ? $this->EmptyState->render(__('No active sessions'), ['icon' => 'monitor'])
    : '<ul class="divide-y">' . $items . '</ul>';

echo $this->Card->render($body, [
    'title' => __('Where you are signed in'),
    'description' => __('Devices with an open session. Something you do not recognise? Sign it out and change your password.'),
    'footer' => $others > 0
        ? $this->Form->postButton(__('Sign out of every other session'), ['controller' => 'AccountSessions', 'action' => 'signOutOthers'], [
            'class' => 'btn-outline w-full',
            'data-confirm' => __('Sign out of every other session? Those devices will have to sign in again.'),
        ])
        : null,
]);
