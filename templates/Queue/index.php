<?php
/**
 * Background jobs (superadmin): counters, failed jobs, recent jobs and workers.
 *
 * @var \App\View\AppView $this
 * @var array{pending: int, running: int, scheduled: int, failed: int, done: int} $counters
 * @var list<\Queue\Model\Entity\QueuedJob> $failed
 * @var list<\Queue\Model\Entity\QueuedJob> $recent
 * @var list<\Queue\Model\Entity\QueueProcess> $workers
 */
$this->assign('title', (string)$this->get('title'));
if ($counters['failed'] > 0) {
    $this->start('header_actions');
    echo $this->Form->postButton(
        __('Retry all failed'),
        ['action' => 'retryAll'],
        ['variant' => 'outline', 'data-confirm' => __('Queue the {0} failed jobs again?', $counters['failed'])],
    );
    $this->end();
}

$when = fn($date): string => $date
    ? '<span class="whitespace-nowrap text-muted-foreground" title="' . h($date->i18nFormat('d MMM yyyy, HH:mm:ss')) . '">'
        . h($date->timeAgoInWords(['end' => '+1 week', 'format' => 'd MMM yyyy, HH:mm', 'accuracy' => 'minute'])) . '</span>'
    : '<span class="text-muted-foreground">—</span>';
$task = fn(string $name): string => '<code class="text-sm">' . h(preg_replace('/^Queue\./', '', $name)) . '</code>';
$firstLine = fn(?string $message): string => (string)strtok((string)$message, "\n");
$status = function ($job): string {
    if ($job->completed) {
        return $this->Badge->render(__('Done'), ['variant' => 'secondary']);
    }
    if ($job->status === 'aborted') {
        return $this->Badge->render(__('Failed'), ['variant' => 'destructive']);
    }
    if ($job->failure_message) {
        return $this->Badge->render(__('Retrying'), ['variant' => 'outline', 'class' => 'text-destructive']);
    }
    if ($job->fetched) {
        return $this->Badge->render(__('Running'), ['variant' => 'outline']);
    }
    if ($job->notbefore && $job->notbefore->isFuture()) {
        return $this->Badge->render(__('Scheduled'), ['variant' => 'outline']);
    }

    return $this->Badge->render(__('Waiting'), ['variant' => 'outline', 'class' => 'text-muted-foreground']);
};

$tiles = [
    'pending' => [__('Waiting'), __('Ready for a worker')],
    'running' => [__('Running'), __('Being processed now')],
    'scheduled' => [__('Scheduled'), __('Waiting for their time')],
    'failed' => [__('Failed'), __('Retries used up')],
    'done' => [__('Done'), __('Last 24 hours')],
];
?>
<div class="grid gap-4 px-4 lg:px-6">
    <?php if ($workers === []) : ?>
        <?= $this->Alert->render(__('No worker is running: jobs (emails included) wait until one starts. In Docker it is the `worker` service (`bin/cake queue run`).'), ['variant' => 'destructive', 'title' => __('Queue stopped')]) ?>
    <?php endif; ?>

    <div class="grid grid-cols-2 gap-4 @xl/main:grid-cols-3 @5xl/main:grid-cols-5">
        <?php foreach ($tiles as $key => [$label, $hint]) : ?>
            <?= $this->Card->render(
                '<p class="text-3xl font-semibold tabular-nums' . ($key === 'failed' && $counters[$key] > 0 ? ' text-destructive' : '') . '" data-queue-count="' . $key . '">' . $counters[$key] . '</p>'
                . '<p class="text-sm text-muted-foreground">' . h($hint) . '</p>',
                ['title' => $label],
            ) ?>
        <?php endforeach; ?>
    </div>

    <?php
    $failedRows = [];
    foreach ($failed as $job) {
        $failedRows[] = [
            'job' => '<span class="font-mono text-sm">#' . $job->id . '</span> ' . $task($job->job_task),
            'error' => '<details class="max-w-xl text-sm"><summary class="cursor-pointer text-destructive">' . h($firstLine($job->failure_message)) . '</summary>'
                . '<pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap rounded-md bg-muted p-3 text-xs">' . h((string)$job->failure_message) . '</pre></details>',
            'attempts' => '<span class="tabular-nums">' . (int)$job->attempts . '</span>',
            'when' => $when($job->fetched ?? $job->created),
            'actions' => '<div class="flex justify-end gap-2">'
                . $this->Form->postButton(__('Retry'), ['action' => 'retry', $job->id], ['variant' => 'outline', 'size' => 'sm'])
                . $this->Form->postButton(__('Delete'), ['action' => 'delete', $job->id], ['variant' => 'ghost', 'size' => 'sm', 'data-confirm' => __('Delete job {0}? It will not run again.', $job->id)])
                . '</div>',
        ];
    }
    echo $this->Card->render(
        $failedRows === []
            ? $this->EmptyState->render(__('No failed jobs'), ['icon' => 'circle-check', 'description' => __('Jobs that use up their retries appear here with their error.')])
            : '<div class="overflow-x-auto rounded-lg border">' . $this->Table->render([
                'job' => __('Job'),
                'error' => __('Error'),
                'attempts' => __('Attempts'),
                'when' => __('Last attempt'),
                'actions' => '',
            ], $failedRows, ['escape' => false, 'headOptions' => ['class' => 'bg-muted']]) . '</div>',
        [
            'title' => __('Failed jobs'),
            'description' => __('Retry once the cause is fixed (e.g. the mail server is back), or delete them.'),
        ],
    );
    ?>

    <div class="grid gap-4 @5xl/main:grid-cols-3">
        <?php
        $recentRows = array_map(fn($job): array => [
            'job' => '<span class="font-mono text-sm">#' . $job->id . '</span> ' . $task($job->job_task),
            'status' => $status($job),
            'created' => $when($job->created),
            'completed' => $when($job->completed),
        ], $recent);
        echo $this->Card->render(
            $recentRows === []
                ? $this->EmptyState->render(__('No jobs yet'), ['icon' => 'inbox', 'description' => __('Emails and other background work will be listed here.')])
                : '<div class="overflow-x-auto rounded-lg border">' . $this->Table->render([
                    'job' => __('Job'),
                    'status' => __('Status'),
                    'created' => __('Queued'),
                    'completed' => __('Finished'),
                ], $recentRows, ['escape' => false, 'headOptions' => ['class' => 'bg-muted']]) . '</div>',
            ['class' => '@5xl/main:col-span-2', 'title' => __('Recent jobs'), 'description' => __('The last 20, without their data.')],
        );

        $workerRows = array_map(fn($worker): array => [
            'server' => '<span class="font-medium">' . h($worker->server ?? '—') . '</span><span class="block font-mono text-xs text-muted-foreground">PID ' . h($worker->pid) . '</span>',
            'heartbeat' => $when($worker->modified),
        ], $workers);
        echo $this->Card->render(
            $workerRows === []
                ? '<p class="text-sm text-muted-foreground">' . __('No active workers.') . '</p>'
                : '<div class="overflow-x-auto rounded-lg border">' . $this->Table->render([
                    'server' => __('Worker'),
                    'heartbeat' => __('Last heartbeat'),
                ], $workerRows, ['escape' => false, 'headOptions' => ['class' => 'bg-muted']]) . '</div>',
            ['title' => __('Workers'), 'description' => __('Processes running `bin/cake queue run`.')],
        );
        ?>
    </div>
</div>
