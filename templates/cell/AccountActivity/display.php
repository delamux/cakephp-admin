<?php
/**
 * Recent activity of an account (App\View\Cell\AccountActivityCell).
 *
 * @var \App\View\AppView $this
 * @var list<\Cake\Datasource\EntityInterface> $entries Newest first.
 * @var array<string, string> $actors Actor id => username.
 * @var bool $own Own profile.
 * @var int $retentionDays
 */
use App\Account\ActivityLog;
use App\Account\UserAgent;

$events = [
    ActivityLog::LOGIN => [__('Signed in'), 'log-in'],
    ActivityLog::PASSKEY_LOGIN => [__('Signed in with a passkey'), 'key-round'],
    ActivityLog::LOGIN_FAILED => [__('Failed sign-in'), 'triangle-alert'],
    ActivityLog::TWO_FACTOR_FAILED => [__('Wrong two-step verification'), 'triangle-alert'],
    ActivityLog::LOGOUT => [__('Signed out'), 'log-out'],
    ActivityLog::PASSWORD_CHANGED => [__('Password changed'), 'lock'],
    ActivityLog::PASSWORD_RESET => [__('Password reset by email'), 'lock'],
    ActivityLog::TOTP_ENABLED => [__('Authenticator app enabled'), 'shield-check'],
    ActivityLog::TOTP_DISABLED => [__('Authenticator app removed'), 'shield'],
    ActivityLog::KEY_ADDED => [__('Security key or passkey added'), 'key-round'],
    ActivityLog::KEY_REMOVED => [__('Security key or passkey removed'), 'key-round'],
    ActivityLog::TWO_FACTOR_RESET => [__('Two-step verification reset'), 'shield'],
    ActivityLog::PHOTO_CHANGED => [__('Photo changed'), 'circle-user'],
    ActivityLog::PHOTO_REMOVED => [__('Photo removed'), 'circle-user'],
    ActivityLog::INVITED => [__('Invited'), 'mail'],
    ActivityLog::INVITATION_ACCEPTED => [__('Invitation accepted'), 'log-in'],
];

$rows = [];
foreach ($entries as $entry) {
    $event = (string)$entry->get('event');
    [$label, $icon] = $events[$event] ?? [$event, 'circle'];
    $warning = in_array($event, ActivityLog::WARNINGS, true);
    $actorId = $entry->get('actor_id');
    $by = $actorId ? ' <span class="text-muted-foreground">' . h(__('by {0}', $actors[$actorId] ?? __('another user'))) . '</span>' : '';
    $rows[] = [
        'event' => '<span class="flex items-center gap-2 font-medium' . ($warning ? ' text-destructive' : '') . '">'
            . $this->Icon->render($icon, ['class' => 'size-4 shrink-0']) . h($label) . $by . '</span>',
        'when' => '<span class="whitespace-nowrap text-muted-foreground" title="' . h($entry->get('created')?->i18nFormat('d MMM yyyy, HH:mm:ss')) . '">'
            . h($entry->get('created')?->timeAgoInWords(['end' => '+1 week', 'format' => 'd MMM yyyy, HH:mm', 'accuracy' => 'minute'])) . '</span>',
        'device' => $entry->get('user_agent') || $entry->get('ip')
            ? h(UserAgent::describe($entry->get('user_agent'))) . '<span class="block font-mono text-xs text-muted-foreground">' . h($entry->get('ip') ?? '—') . '</span>'
            : '<span class="text-muted-foreground">' . __('Console') . '</span>',
    ];
}

echo $this->Card->render(
    $rows === []
        ? $this->EmptyState->render(__('No activity yet'), ['icon' => 'history', 'description' => __('Sign-ins and security changes will be listed here.')])
        : '<div class="overflow-x-auto rounded-lg border">' . $this->Table->render([
            'event' => __('Event'),
            'when' => __('When'),
            'device' => __('Device'),
        ], $rows, ['escape' => false, 'headOptions' => ['class' => 'bg-muted']]) . '</div>',
    [
        'title' => __('Recent activity'),
        'description' => $own
            ? __('Your last sign-ins and security changes (kept {0} days). Something you do not recognise? Change your password and enable two-step verification.', $retentionDays)
            : __('Last sign-ins and security changes of this account (kept {0} days).', $retentionDays),
    ],
);
