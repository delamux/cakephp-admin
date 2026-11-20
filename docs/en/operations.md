# Operations and crons

**English** · [Español](../es/operations.md)

## What must always be running

| What | How | If it's missing |
| --- | --- | --- |
| **Queue worker** | `worker` service (`bin/cake queue run`), restarted by Docker | **No emails go out** (they stay in the queue); *System › Queue* warns "Queue stopped" |
| **Daily cron: `bin/cake maintenance`** | Coolify: *Scheduled Task* `0 3 * * *` on the `app` container | Activity, history, queue, sessions and counters grow without limit; personal data retention isn't enforced |

There are no other crons. If another daily task is needed, it's added inside `maintenance`
(`App\UseCase\Maintenance\DailyMaintenance`) and the cron doesn't change.

### What `bin/cake maintenance` does

| Step | Deletes |
| --- | --- |
| Account activity | Account activity older than `App.accountActivity.retentionDays` (180 days) |
| Permissions history | Permissions history older than `App.permissions.auditRetentionDays` (365 days); records the purge |
| Queue | Completed jobs older than 7 days and failed ones older than 30 |
| Expired sessions | Expired sessions from the `sessions` table |
| Throttling counters | Expired per-IP rate-limit windows |

It shows how many rows each step deleted; if one fails, the others carry on and the command exits with an error
(leaving the reason in the log).

### Without Docker or Coolify (crontab)

```cron
# daily cron
0 3 * * * cd /path/to/the/app && bin/cake maintenance
# worker, if you have no supervisor (systemd/supervisord): starts one every 10 min, each lives 600 s
*/10 * * * * cd /path/to/the/app && bin/cake queue run -q
```

With systemd, a service with `ExecStart=/path/to/the/app/bin/cake queue run` and `Restart=always` is better. Run it
with the same user and the same PHP version as the web server.

## App commands (`src/Command`) and when they run

| File | Command | What it does | When | Schedule it? |
| --- | --- | --- | --- | --- |
| `MaintenanceCommand.php` | `bin/cake maintenance` | Every daily task: activity and permissions history purge, queue cleanup, expired sessions and per-IP rate-limit counters | **Once a day** | **Yes: it's the only cron** (Coolify: *Scheduled Task* `0 3 * * *`, container `app`) |
| `PermissionsSyncCommand.php` | `bin/cake permissions sync [--reset]` | Registers new controller actions with their default permissions and flags the ones that no longer exist | **On every deploy** | No: `bin/deploy` runs it (`migrate` service). By hand in dev, after creating controllers |
| `ActivityPurgeCommand.php` | `bin/cake activity purge [--days N] [--dry-run]` | Deletes account activity older than the retention period (180 days) | Already included in `maintenance` | **No** (it would run twice). Only by hand, e.g. with `--dry-run` to see how much it would delete |
| `PermissionsPurgeCommand.php` | `bin/cake permissions purge [--days N] [--dry-run]` | Deletes permissions history older than the retention period (365 days) | Already included in `maintenance` | **No** (it would run twice). Only by hand |
| `TwoFactorResetCommand.php` | `bin/cake two_factor reset <username\|email>` | Removes both 2FA methods from an account (a superadmin who lost their device) | When needed | **No**: it's a manual support action |

Also, outside `src/Command`: the **worker** (`bin/cake queue run`, from the Queue plugin) isn't a cron but a process
that is always running (`worker` service), and `bin/deploy` / `bin/migrate` are deploy scripts.

When you create a new command, add it to this table. If it must run daily, don't create another cron: add the step to
`App\UseCase\Maintenance\DailyMaintenance` (and its command, if you want to be able to run it by hand).

## Quick command reference

| Command | Purpose |
| --- | --- |
| `bin/deploy` | Migrations + permissions sync (run by `migrate` on every deploy) |
| `bin/migrate` | Migrations only (CakeDC/Users, Queue, app) |
| `bin/cake maintenance` | The daily cron |
| `bin/cake permissions sync [--reset]` | Discover new actions (and re-apply the default permissions with `--reset`) |
| `bin/cake permissions purge [--days N] [--dry-run]` | Manual purge of the permissions history |
| `bin/cake activity purge [--days N] [--dry-run]` | Manual purge of account activity |
| `bin/cake two_factor reset <username\|email>` | Remove 2FA from an account (superadmin without their device) |
| `bin/cake users add_superuser` | Create a superadmin |
| `bin/cake users password_email <username\|email>` | Email a password reset link |
| `bin/cake bake all <Model>` | CRUD with the theme templates ([Architecture](architecture.md#how-to-add-a-new-module-eg-customers)) |
| `bin/cake queue run` / `queue info [--format json]` / `queue job view <id>` / `queue worker` | Worker and queue status |
| `composer check` | Tests + CodeSniffer + PHPStan |

## Logs

Production: everything to stderr (`LOG_TO_STDERR`), in the container logs (Coolify: *Logs*; Docker:
`docker compose logs -f app worker`). Dev: `logs/*.log`.

| Message | What it means |
| --- | --- |
| `Queue job N (Queue.Email) gave up after 4 attempts: …` | An email couldn't be sent after the retries: check the SMTP and retry from *System › Queue* |
| `Queue job N (…) failed, attempt N: …` (warning) | Failed attempt; it will be retried automatically |
| `Account activity "…" of user … not recorded: …` | The activity couldn't be written (the user's action did go through) |
| `Maintenance task "…" failed: …` | A step of the daily cron failed |
| `Passkey login rejected: …` / `WebAuthn second step rejected: …` | Attempts with invalid keys (technical detail; the user sees a generic message) |

## Health and monitoring

- `GET /healthz` on `web` (nginx, 200 "ok") for the container health check.
- `bin/cake queue info --format json`: pending, failed, workers (for external alerts).
- *System › Queue*: live workers and failed jobs.

## Backups

Two things hold state; the containers hold nothing else:

- **PostgreSQL**: users, roles, permissions, sessions, queue, activity, histories → scheduled backups of the database
  resource (Coolify → S3).
- **`uploads` volume**: uploaded files (user photos) → back it up just as often (e.g. a *Scheduled Task* that syncs it
  to S3, or the server's backups).

Restoring = restore the database and the volume (from the same point in time) and redeploy.

## Updates

1. In dev: `composer update` (or the Dependabot PR), `composer check`, test with `compose.prod.yaml`.
2. Push: the deploy applies the new migrations (the plugins' too) and registers the new actions.
3. After updating the theme or CSS: nothing else, cache busting (`?<mtime>`) invalidates the assets.
