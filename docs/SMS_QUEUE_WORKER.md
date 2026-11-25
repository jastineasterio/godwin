# SMS Queue Worker — God-Win Daycare

## Why a worker is required

Admission SMS notifications (submission and acceptance) are dispatched as
queue jobs on the `sms` queue using the database queue driver
(`QUEUE_CONNECTION=database`). A job row is inserted into the `jobs` table
with `attempts = 0` and stays there forever until a worker pops and runs it.

During diagnosis, **no queue worker was running**, so jobs 10–14 (apps 1/5/5/6/7)
sat pending indefinitely. **There is no "immediate delivery" without a running
worker.** The dispatch path itself is correct:
`ApplicationController::store` and `AdmissionApplicationController::accept`
both dispatch `SendAdmissionNotificationJob` with `->afterCommit()` on the
`sms` queue.

## Development: run the worker

```bash
php artisan queue:work --queue=sms --tries=3 --timeout=60 --sleep=3
```

Run this in a dedicated terminal while developing. It processes only the
`sms` queue, so it will not interfere with other queue work.

To run it detached in the background (PowerShell):

```powershell
Start-Process -FilePath php -ArgumentList "artisan queue:work --queue=sms --tries=3 --timeout=60 --sleep=3" -WorkingDirectory C:\Users\HP\Desktop\godwin -RedirectToError storage/logs/sms_worker.log -RedirectToOutput storage/logs/sms_worker.log
```

On Linux/macOS:

```bash
nohup php artisan queue:work --queue=sms --tries=3 --timeout=60 --sleep=3 >> storage/logs/sms_worker.log 2>&1 &
```

## Production: Supervisor (persistent worker)

Install the Supervisor configuration:

```bash
sudo cp deploy/sms-queue-worker.conf /etc/supervisor/conf.d/godwin_sms_worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start godwin_sms_worker
```

Edit the `directory` and `command` paths in the conf to match your deployment
path before installing.

Key Supervisor settings:

- `autostart=true` / `autorestart=true` — worker starts on boot and restarts
  automatically after a failure or crash.
- `startretries=3` — Supervisor retries starting the worker 3 times.
- `stopasgroup=true` / `killasgroup=true` — clean shutdown of the worker process.
- Exactly **one** `queue:work` process is configured for the `sms` queue.

## Worker tuning values

| Setting | Value | Where |
|---|---|---|
| Queue | `sms` | `--queue=sms` |
| Max tries | 3 | `--tries=3` (job also sets `$tries = 3`) |
| Backoff between retries | 10s | `SendAdmissionNotificationJob::$backoff` |
| Job timeout | 60s | `--timeout=60` |
| Empty-queue sleep | 3s | `--sleep=3` |
| DB `retry_after` | 90s | `config/queue.php` database connection |

Transient provider failures (connection errors, 5xx, rate limits) are rethrown
by the job and retried with the 10s fixed backoff up to 3 attempts, then
recorded in `failed_jobs`. Permanent failures (no provider, disabled SMS,
invalid phone, provider rejection, message too long) are logged and finished
without retrying.

## Verify the worker is running

```bash
supervisorctl status godwin_sms_worker
# Expected: godwin_sms_worker:RUNNING
```

```bash
php artisan queue:work --queue=sms --timeout=60 --sleep=3
# In development, expect log lines like:
# [timestamp] Processing: App\Jobs\Sms\SendAdmissionNotificationJob
# [timestamp] Processed:  App\Jobs\Sms\SendAdmissionNotificationJob
```

Confirm the queue is being drained:

```sql
SELECT COUNT(*) AS pending FROM jobs WHERE queue = 'sms';
-- Expected after processing: 0 (or a small number of in-flight jobs)
```

Check for failures:

```sql
SELECT * FROM failed_jobs WHERE queue = 'sms' ORDER BY failed_at DESC;
```

Inspect worker logs:

```bash
tail -n 50 storage/logs/sms_worker.log
```

## Do not

- Do not run more than one worker on the `sms` queue.
- Do not delete, truncate, or automatically resend pending jobs — let the worker
  process them; idempotency keys prevent duplicate SMS.
- Do not blindly resend `submission_unknown` records — use the admin recovery
  flow (reconcile via provider status lookup, or authorized manual resend).
- Do not expose the DARSMS API key in logs or configuration diffs.
