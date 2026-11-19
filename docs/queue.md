# Job queue

[dereuromark/cakephp-queue](https://github.com/dereuromark/cakephp-queue): background jobs stored in **PostgreSQL**
(`queued_jobs` and `queue_processes` tables, with `SKIP LOCKED`). No Redis or external services.

## Emails: all through the queue

The `Email.default` profile uses the `queue` transport (`config/app.php`), which stores the **already rendered**
message (layout and inline CSS included) as a `Queue.Email` job; the worker delivers it through the real transport
(`default`, `EMAIL_TRANSPORT_DEFAULT_URL`). The request doesn't wait for SMTP and, if SMTP is down, it's retried.

- It applies to everything: sign-up, reset, magic link, 2FA notifications… without touching any mailer.
- `EMAIL_QUEUE=false` goes back to sending during the request (for example, on an installation without a worker).
- Nothing changes in tests: `EmailTrait` replaces the transports.

## The worker

`bin/cake queue run`: processes jobs and exits after `Queue.workerLifetime` (600 s + a random margin); Docker starts
it again (`restart: unless-stopped`). It's the **`worker`** service in `compose.yaml` (dev), `compose.prod.yaml` and
`compose.coolify.yaml`, using the same image as the app.

- With `pcntl` (installed in the image), when it's stopped (`docker stop`, redeploy) it finishes the current job
  before exiting (`stop_grace_period: 60s`).
- Several worker replicas share the work without stepping on each other.
- In dev, after changing code the worker uses: `docker compose restart worker`.

| Config (`Queue` in `config/app.php`) | Value | Purpose |
| --- | --- | --- |
| `workerLifetime` | `QUEUE_WORKER_LIFETIME` (600) | Seconds before the process is recycled |
| `sleeptime` | `QUEUE_SLEEPTIME` (5) | Wait time when there's no work (how long an email takes to go out) |
| `defaultJobRetries` | 3 | Retries after the first attempt |
| `maxworkers` | 1 | Workers per server (one per container) |
| `cleanuptimeout` / `cleanupAbortedTimeout` | 7 / 30 days | How long completed and failed jobs are kept |

## The *System › Queue* screen (superadmin)

`/queue`: counters (waiting, in progress, scheduled, failed, done in the last 24 h), **failed jobs with their error**
(retry one, retry all, delete), the latest 20 jobs and the live workers. If there's no worker, it warns you. It never
shows job data (emails carry personal data).

The plugin's own screen (`/admin/queue`) has no routes: it loads Bootstrap from a CDN, which is incompatible with the
CSP.

## Failures

- A failed attempt → `warning` in the log; a job that runs out of retries → `error` in the log (in production,
  stderr: collected by Docker/Coolify) and it stays on the screen until it's retried or deleted. Only the first line
  of the error: no trace or data.
- Old completed and failed jobs are deleted by `bin/cake maintenance`.

```bash
bin/cake queue info                 # status and statistics (--format json for monitoring)
bin/cake queue job view <id>        # a single job
bin/cake queue worker               # registered workers
```

## Creating your own task

```php
// src/Queue/Task/ImportCustomersTask.php
namespace App\Queue\Task;

use Queue\Queue\Task;

class ImportCustomersTask extends Task
{
    public ?int $timeout = 300;   // seconds (≤ Queue.defaultRequeueTimeout)
    public ?int $retries = 2;

    public function run(array $data, int $jobId): void
    {
        $file = $this->fetchTable('Imports')->get($data['import_id']);
        // ... work; an exception = failed attempt (it will be retried)
        $this->QueuedJobs->updateProgress($jobId, 0.5);
    }
}
```

```php
// Enqueue (from a use case): pass IDs, not entities; the worker reads the current data
$this->fetchTable('Queue.QueuedJobs')->createJob('ImportCustomers', ['import_id' => $import->id], [
    'reference' => 'import-' . $import->id,   // with 'unique' => true, avoids duplicate pending jobs
]);
```

**Idempotency:** a job can run twice (worker died halfway, claim expired). Design tasks so that repeating them does no
harm: check the state before acting, use unique constraints or the external service's idempotency support. For
emails, the rare case is a duplicate message.

To send your own email you don't need a task: every `Mailer` already goes through the queue.
