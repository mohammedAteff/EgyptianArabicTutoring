# Executive Summary

This is a report-only handoff for the deployed EgyptianArabicTutoring application. Discovery used the current repository, installed framework, command signatures, deployed files, read-only database/configuration/process inspection, and Telegram's read-only `getWebhookInfo`. No cron, worker, application configuration, customer record, Telegram rule, deployment, or production message was changed during this pass. Commands below are instructions for the receiving developer; they have not been executed unless explicitly described as inspection.

Local main, deployed HEAD, and GitHub main were verified at **`5aaa22ab85ace375f3effd81924d635f7f7bc214`**. Code already implements scheduler callbacks, database queues, encrypted Telegram delivery records, polling commands, independent watchdog delivery, analytics rollups and retention, hold cleanup, backups, and GeoIP updates. Ordinary web requests, transactional event recording, and direct connectivity tests can work without a worker. Enqueuing an alert does not deliver it.

**Background automation is not production-ready.** At inspection on 2026-10-02, the scheduler heartbeat was stale, the worker heartbeat was absent, both analytics rollup tables were empty, inbound commands were disabled, and no watchdog or digest rules were configured. A previously successful direct Telegram test is not evidence of a running queue. Offsite backup replication was failing despite a successful local backup.

Install **four independent, once-per-minute Hostinger Custom cron entries**, using the private host wrapper in section 4:

| ID | Responsibility | Executes |
| --- | --- | --- |
| H1 | Laravel scheduler, including eight registered tasks | `schedule:run` |
| H2 | Independent scheduler/queue watchdog with direct health-alert fallback | `telegram:watchdog` |
| H3 | Telegram inbound command polling | `telegram:poll` |
| H4 | One bounded database queue worker | `queue:work database --queue=default` |

Inbound mode is **POLLING**, not webhook. H2 and H3 are not registered in Laravel's scheduler and must remain independent of H1. H4 is required for ordinary outbound alerts, reminders, deferred/multipart delivery, and queued resource-verification mail. PHP currently disables `proc_open`: H1 needs the process-scoped workaround below for its two subprocess tasks, `telegram:tick` and `geoip:update`. A moving scheduler heartbeat alone does not prove those tasks work.

## 2. Current Environment Facts

| Fact | Production | Local discovery |
| --- | --- | --- |
| Repository | `mohammedAteff/EgyptianArabicTutoring`, main | Same repository, main |
| SHA | `5aaa22ab85ace375f3effd81924d635f7f7bc214` | Same SHA; clean at start of this pass |
| Application root | `/home/u494520852/domains/mohamedateff.com/arabictutor_app` | `C:\Users\e\Herd\ArabicwAbdallah` |
| Public wrapper | `/home/u494520852/domains/mohamedateff.com/public_html/arabictutor` | Herd-served project |
| PHP CLI | `/opt/alt/php84/usr/bin/php`, PHP 8.4.19 | `C:/Users/e/.config/herd/bin/php84/php.exe`, PHP 8.4.25 |
| PHP alias | `/usr/bin/php` resolves to the production binary above | Use explicit Herd PHP path |
| Laravel | 13.32.0 | 13.32.0 |
| MariaDB | 11.8.9-MariaDB-log, port 3306 | 10.11.18, port 3307 |
| Environment | production | local |
| Application timezone | UTC (`config/app.php`) | UTC |
| Business timezone | `Setting::get('business_timezone')`, validated by `TimezoneService`; Africa/Cairo currently | Africa/Cairo currently |
| Queue | database, queue `default`, retry_after 90 seconds | database |
| Cache / sessions | database / database; session lifetime 120 minutes | database / database |
| User | `u494520852` | Windows user `e` |
| SSH endpoint | `46.202.158.92`, port 65002, account `u494520852` | Existing authorized SSH identity used for read-only discovery; no credential contents included |
| PHP extensions/limits | pcntl, posix, ZIP present; execution limit 0; memory limit 1536M | Separate local runtime; do not infer host limits from it |
| Available host controls | `/usr/bin/bash`, `/usr/bin/flock`, `/usr/bin/timeout`, `/usr/bin/sleep`, `/usr/bin/find`, `/usr/bin/date`, `/usr/bin/stat`, `/usr/bin/tail`, `/usr/bin/gzip` | No local Bash located during inspection |

Writable production paths include `storage`, `storage/logs`, `storage/framework`, `storage/app/backups`, and `bootstrap/cache` beneath the application root. Database tables `jobs`, `failed_jobs`, `job_batches`, `cache`, `cache_locks`, `sessions`, Telegram tables, and both rollup tables exist. Inspected migration status showed migrations applied; run `migrate:status` again immediately before implementation.

Secret-presence inspection only: APP_KEY present; existing bot token present; S3 key/secret/bucket configuration present; MaxMind license key and explicit GeoIP download URL missing. No token, password, bucket name, chat ID, or user ID is reproduced here. Production mail uses `log`, so running the queue does not establish external email delivery.

No account `crontab`, Supervisor, or systemd management executable was available. No Artisan background process was observed. SSH cannot establish the hPanel cron inventory, plan name, cron quota, or CPU/process quotas. The developer must inspect **the website's hPanel Cron Jobs list and plan/resource limits**, save an evidence screenshot, and replace duplicate equivalents with the four entries below rather than adding duplicates. This is an explicit discovery step, not an assumption that the hPanel list is empty.

The proposed `/home/u494520852/operations` directory did not exist during inspection. Production also contains the pre-existing untracked file `.env.backup.before-7123f15` (mode 640); it is not a new application release change and must never be staged/pushed or displayed. Keep that recovery copy private along with the live environment file.

Evidence baseline:

| State | Inspected value |
| --- | --- |
| `last_scheduler_run_at` | 2026-10-01T14:08:00+00:00 |
| `last_holds_cleanup_at` | 2026-09-19T19:00:36+00:00 |
| `last_analytics_aggregation_at`, `last_country_analytics_aggregation_at` | Missing |
| `queue_worker_heartbeat_at` | Missing |
| Pending/failed framework jobs | 0 / 0; neither proves worker availability |
| Daily/country rollups | 0 rows / 0 rows |
| `telegram.last_tick_at`, `telegram.last_watchdog_run_at`, `telegram.last_queue_seen` | Missing |
| Telegram delivery | One direct TEST, sent 2026-10-02 08:13:11 UTC, one attempt |
| Global automation / commands | Automation enabled; commands disabled |
| Bot 1 / destination 1 | Enabled; private personal-detail destination; commands disabled; zero allowed command users |
| Rules | Two enabled session-reminder rules: 1440 and 210 minutes; no other configured rule types |
| Last local backup | `backup-full-2026-10-02-132456.zip`, success at 2026-10-02T13:24:58+00:00 |
| Offsite backup | Failed, category `s3_replication_failed` |
| Maintenance | Off; `telegram.maintenance_since` empty |

## 3. Complete Scheduler Inventory

Source of truth: `routes/console.php`. There are **eight scheduler registrations**, not separate registrations for every Telegram alert type.

| Task | Artisan command / callback | Frequency | Timezone | Without overlap? | Queue required? | Business purpose | Failure impact |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Scheduler heartbeat | Callback: `Setting::set('last_scheduler_run_at', now('UTC')->toIso8601String(), 'system')` | Every minute | Timestamp UTC | No internal mutex | No | Proves scheduler entered callback | Health becomes stale |
| Hold cleanup | In-process `booking:cleanup-holds` callback | Every 5 minutes | Interval; UTC data | No internal mutex | Only resulting configured alerts | Expire/prune holds and abandoned-lead events | Stale statuses, storage growth, delayed alerts |
| Country rollup | In-process `analytics:aggregate-daily-country --date=...` callback | Daily 00:02 | Business schedule; date argument hardcoded to Cairo yesterday | No internal mutex | No | Country rollups before pruning | Missing country history; see section 16 timezone caveat |
| Daily rollup and retention | In-process `analytics:aggregate-daily --prune` callback | Daily 00:05 | Business timezone | No internal mutex | No | Daily rollups, synchronous country rollup, guarded retention | Missing history/storage growth or retention failure |
| Backup and cleanup | In-process `backup:run --clean` callback | Daily 02:00 | **Application UTC** | No internal mutex | Only configured backup notifications | SQL/storage snapshot and 30-day retention | Missing recovery snapshot/offsite failure |
| Session/auth cleanup | Callback: `auth:clear-resets`, then expired database-session deletion | Daily 03:00 | **Application UTC** | No internal mutex | No | Remove expired reset tokens and sessions | Expired rows accumulate |
| Telegram scanner | Subprocess `telegram:tick` | Every minute | UTC instants; business-local rule times | Yes, 10-minute mutex expiry | Yes, ordinary deliveries | Reminder/package/form/spike/digest/maintenance scans, pending dispatch | Alerts stop or backlog grows |
| GeoIP updater | Subprocess `geoip:update` | Day 2 monthly, 04:00 | Business timezone | Yes, 120-minute mutex expiry | No | Replace country dataset atomically | Dataset ages; unknown-country coverage can worsen |

H1 supplies a host-level lock for the entire scheduler because most callbacks have no internal overlap protection. No task is configured with `runInBackground()` or `onOneServer()`. Mutex expiry is in **minutes**, not execution timeout. Database cache uses `cache_locks` for atomic locks.

Hold/country/daily callbacks update success timestamps on successful scheduled execution; failure hooks write failure state and attempt internal admin notifications. Backup service maintains its own state. Scheduler process exit zero is insufficient: Laravel can report an individual task exception and continue. Callback Artisan output is buffered, and scheduled subprocess output is not explicitly redirected by the registrations. Check per-task timestamps, database results, and Laravel exceptions alongside H1 logs.

There is no scheduled standalone watchdog, poller, worker, Telegram-history cleanup, failed-job pruning, or historical backfill. Package, form, traffic, digest, and maintenance scans are inside `telegram:tick`, not additional host jobs. Application UI maintenance is distinct from Artisan's `down`; the latter suppresses ordinary scheduled tasks and workers.

## 4. Exact Laravel Scheduler Hostinger Setup

Use `schedule:run` once per minute, as `u494520852`. `schedule:work` requires a maintained persistent process and is inappropriate for the controls available on this account. Do not point cron at the public wrapper's index.php, a web URL, or a system-default PHP version.

### Prerequisite: process-scoped PHP subprocess support

Production's disabled-function list contains `proc_open`. Callback tasks work in-process, but Laravel's two `Schedule::command` registrations require Symfony Process. This was confirmed against the installed framework. A harmless PHP-version subprocess was successfully executed using a **CLI override removing only `proc_open` from the existing disabled list**. No host setting was changed.

The wrapper derives the current list each invocation, preserves every other disabled function, and permits subprocess creation only for that scheduler PHP process. It does not set `disable_functions` to an empty value. The receiving developer must repeat the following read-only preflight on the host:

```bash
cd /home/u494520852/domains/mohamedateff.com/arabictutor_app
PHP=/opt/alt/php84/usr/bin/php
DISABLED="$($PHP -r '$f=array_filter(array_map("trim",explode(",",ini_get("disable_functions"))),fn($v)=>$v!=="proc_open"); echo implode(",",$f);')"
"$PHP" -d "disable_functions=$DISABLED" -r 'require "vendor/autoload.php"; $p=new Symfony\Component\Process\Process([PHP_BINARY,"--version"]); $p->run(); echo "subprocess_exit=".$p->getExitCode().PHP_EOL; exit($p->getExitCode());'
```

PASS: `subprocess_exit=0`. If hosting policy starts rejecting this override, do not install an H1 that only updates heartbeat. Resolve CLI subprocess permission through hPanel's PHP configuration/support, repeat this preflight, and keep all other disabled functions intact. This verified CLI approach avoids separate duplicate host entries for tick and GeoIP.

### Developer installation: one private host wrapper, four roles

These are **future developer actions**, outside application source. Create `/home/u494520852/operations/run.sh`; never place it under public_html. Use LF line endings. The wrapper creates private logs, holds independent OS locks, bounds invocations, and rotates by daily filename with a 14-day retention policy confined to its own role logs.

```bash
umask 077
mkdir -p /home/u494520852/operations/logs /home/u494520852/operations/locks
chmod 700 /home/u494520852/operations /home/u494520852/operations/logs /home/u494520852/operations/locks
cat > /home/u494520852/operations/run.sh <<'BASH'
#!/usr/bin/bash
set -u
umask 077
export PATH=/usr/bin:/bin
APP=/home/u494520852/domains/mohamedateff.com/arabictutor_app
OPS=/home/u494520852/operations
PHP=/opt/alt/php84/usr/bin/php
ROLE=${1:-}
case "$ROLE" in scheduler|watchdog|poll|queue) ;; *) exit 64 ;; esac
LOG="$OPS/logs/$ROLE-$(/usr/bin/date -u +%F).log"
exec >>"$LOG" 2>&1
exec 9>"$OPS/locks/$ROLE.lock"
if ! /usr/bin/flock -n 9; then
    printf '%s role=%s skipped=lock_busy\n' "$(/usr/bin/date -u +%FT%TZ)" "$ROLE"
    exit 0
fi
/usr/bin/find "$OPS/logs" -maxdepth 1 -type f -name "$ROLE-????-??-??.log" -mtime +14 -delete
(
    /usr/bin/flock -n 8 || exit 0
    APPLOG="$APP/storage/logs/laravel.log"
    TODAY=$(/usr/bin/date -u +%F)
    if [ -f "$APPLOG" ]; then
        SIZE=$(/usr/bin/stat -c %s "$APPLOG")
        MODIFIED=$(/usr/bin/stat -c %Y "$APPLOG")
        LOGDAY=$(/usr/bin/date -u -d "@$MODIFIED" +%F)
        if [ "$SIZE" -gt 0 ] && { [ "$LOGDAY" != "$TODAY" ] || [ "$SIZE" -ge 10485760 ]; }; then
            ARCHIVE="$OPS/logs/laravel-$(/usr/bin/date -u +%FT%H%M%S)-$$.log"
            /usr/bin/mv "$APPLOG" "$ARCHIVE" || exit 74
            : > "$APPLOG" || exit 74
            /usr/bin/chmod 600 "$APPLOG" "$ARCHIVE" || exit 74
            /usr/bin/touch "$ARCHIVE" || exit 74
        fi
    fi
    /usr/bin/find "$OPS/logs" -maxdepth 1 -type f -name 'laravel-????-??-??T??????-*.log' -mtime +14 -delete
) 8>"$OPS/locks/log-rotation.lock"
ROTATION_RC=$?
if [ "$ROTATION_RC" -ne 0 ]; then
    printf '%s log_rotation_failed=%s\n' "$(/usr/bin/date -u +%FT%TZ)" "$ROTATION_RC"
    exit "$ROTATION_RC"
fi
cd "$APP" || exit 72
printf '%s role=%s start\n' "$(/usr/bin/date -u +%FT%TZ)" "$ROLE"
case "$ROLE" in
    scheduler)
        DISABLED="$($PHP -r '$f=array_filter(array_map("trim",explode(",",ini_get("disable_functions"))),fn($v)=>$v!=="proc_open"); echo implode(",",$f);')" || exit 70
        /usr/bin/timeout --signal=TERM --kill-after=30s 900s "$PHP" -d "disable_functions=$DISABLED" artisan schedule:run --no-interaction
        ;;
    watchdog)
        /usr/bin/timeout --signal=TERM --kill-after=5s 120s "$PHP" artisan telegram:watchdog --no-interaction
        ;;
    poll)
        /usr/bin/timeout --signal=TERM --kill-after=5s 55s "$PHP" artisan telegram:poll --no-interaction
        ;;
    queue)
        /usr/bin/timeout --signal=TERM --kill-after=5s 55s "$PHP" artisan queue:work database --queue=default --stop-when-empty --max-time=20 --timeout=30 --tries=1 --sleep=1 --memory=256 --no-interaction
        ;;
esac
RC=$?
printf '%s role=%s finish exit=%s\n' "$(/usr/bin/date -u +%FT%TZ)" "$ROLE" "$RC"
exit "$RC"
BASH
chmod 700 /home/u494520852/operations/run.sh
/usr/bin/bash -n /home/u494520852/operations/run.sh
```

The scheduler's 900-second outer ceiling is an initial operational bound, **not a measured runtime requirement**. Avoid a 55-second scheduler cutoff that can truncate backups/downloads. A long H1 run skips later minute invocations; watch elapsed times and missed tasks, and measure before lowering its bound. A run reaching 900 seconds is a failure requiring inspection of partial archives/rollups and host quotas. Do not automatically rerun destructive retention or assume daily tasks catch up. Watchdog 120 seconds accommodates its batch of up to 20 due health deliveries; each Telegram request is already limited to five seconds. Poller 55 seconds is sufficient for the currently single bot's one bounded request; no long-poll loop exists. Worker has the tighter envelope explained in section 8.

In hPanel, open this website's **Cron Jobs**, select **Custom**, enter the following command, and set minute/hour/day/month/weekday to `*`. The equivalent full crontab line is shown for clarity; **do not paste the five schedule fields into hPanel's command field**:

```cron
* * * * * /usr/bin/bash /home/u494520852/operations/run.sh scheduler
```

Output goes to `/home/u494520852/operations/logs/scheduler-YYYY-MM-DD.log`, dated UTC. A lock-busy skip is intentional, but repeated skips require runtime investigation. This host-script approach follows [Hostinger's special-character cron instructions](https://www.hostinger.com/support/5646919-how-to-set-up-a-cron-job-with-special-characters-at-hostinger/); the shell script holds redirection and quoting outside hPanel's command field. See also [Hostinger cron setup](https://support.hostinger.com/en/articles/1583465-how-to-set-up-a-cron-job-at-hostinger).

## 5. Scheduler Heartbeat

Storage is the `settings` table, key `last_scheduler_run_at`, written as an ISO UTC instant every scheduler minute by the named `scheduler-heartbeat` callback. `SystemHealthController` marks missing heartbeat warning and age **greater than 10 minutes** unhealthy. Telegram health rules use their own `minutes` threshold; catalog default is **15 minutes**, not the dashboard's 10.

Run this read-only command twice on SSH, separated by two ordinary cron cycles:

```bash
cd /home/u494520852/domains/mohamedateff.com/arabictutor_app
/opt/alt/php84/usr/bin/php artisan tinker --execute 'dump(["observed_utc"=>now("UTC")->toIso8601String(),"scheduler"=>App\Domains\CMS\Models\Setting::get("last_scheduler_run_at"),"tick"=>App\Domains\CMS\Models\Setting::get("telegram.last_tick_at"),"watchdog"=>App\Domains\CMS\Models\Setting::get("telegram.last_watchdog_run_at"),"worker"=>Illuminate\Support\Facades\Cache::get("queue_worker_heartbeat_at")]);' --no-interaction
/usr/bin/tail -n 40 /home/u494520852/operations/logs/scheduler-$(/usr/bin/date -u +%F).log
```

PASS: scheduler timestamp advances without a manual `schedule:run`, age remains near one minute, H1 has completed invocations, **and tick timestamp advances separately**. Visit the authenticated Super Admin System Health panel only during implementation; its storage/cache probes perform small writes, so it was not invoked in this report-only inspection.

## 6. Independent Watchdog

`telegram:watchdog` calls `TelegramReadService::watchdog()` and then directly delivers up to 20 due pending `scheduler_stale` / `queue_stale` records. It records `telegram.last_watchdog_run_at` in settings. It reads scheduler heartbeat from settings and queue heartbeat from cache, falling back to persisted `telegram.last_queue_seen`; fresh queue observations update that persisted value.

Detection is missing or older-than-`rule.minutes`. Cooldown is per rule and hashed health entity in `telegram_rule_states`; delivery dedupe is per rule/destination/event identity. There are **no configured health rules currently**, so installing H2 alone only records watchdog execution. Configure two new operational rules on bot 1/destination 1, without changing the existing reminder rules:

| Trigger | Name | Mode | Priority | Minutes | Cooldown | Quiet hours | Template |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `scheduler_stale` | Production scheduler health | instant | critical | 15 | 60 minutes | None | `Scheduler stale. Last: {last_heartbeat}; threshold: {threshold_minutes} minutes.` |
| `queue_stale` | Production queue health | instant | critical | 15 | 60 minutes | None | `Queue worker stale. Last: {last_heartbeat}; threshold: {threshold_minutes} minutes.` |

Use threshold 1/window 60/send time 08:00 for these required form fields; those fields do not control heartbeat detection. Conditions empty, enabled, destination 1. Critical bypasses quiet hours. Finish establishing fresh H1/H4 before enabling the rules to avoid an expected first-run stale alert.

```cron
* * * * * /usr/bin/bash /home/u494520852/operations/run.sh watchdog
```

H2 has its own lock/process/log, outside H1. For scheduler dead **and** worker dead, the service may enqueue its health record, but the watchdog command additionally calls delivery directly: a running queue is **not** required for the alert. Due deferred health records can be retried directly by later watchdog runs. Database, database cache, PHP, Telegram network access, enabled automation/bot/destination/rule, and a running H2 still are required. An entire host/database outage cannot be diagnosed or notified by PHP on that host; there is no external uptime monitor implemented here.

Exit zero establishes command completion, not delivery success: inspect `status`, `attempts`, `message_ids`, `failure_code` in Delivery History and the watchdog timestamp. Logs: `operations/logs/watchdog-YYYY-MM-DD.log`. Do not run watchdog solely from Laravel's scheduler.

## 7. Queue Architecture

Production `QUEUE_CONNECTION=database`; the database connection is the default MySQL/MariaDB connection, table `jobs`, queue `default`, `retry_after=90`. Framework failures use `failed_jobs` with UUIDs (`database-uuids`); `job_batches` exists but no application batch producer was found. Connection `after_commit=false` is not a statement that Telegram sends uncommitted events: business listeners and alert enqueueing explicitly use `DB::afterCommit`.

`jobs` stores queue/payload/attempts/reserved_at/available_at/created_at; `failed_jobs` stores connection/queue/payload/exception/failed_at. Delays are `available_at` timestamps. Main asynchronous workloads:

| Work | Queue / tries / timeout | Reliability boundary |
| --- | --- | --- |
| `App\Jobs\DeliverTelegramMessage` | default; job tries 1; timeout 30 seconds | Serializes only delivery ID; encrypted text/source stay in `telegram_deliveries`. Atomic pending-to-sending claim, dedupe key, provider receipts, and part index enforce delivery idempotency |
| `ResourceVerificationPinMail` (`ShouldQueue`) | default; inherits worker limits unless separately configured | PIN is present in serialized mail data; protect queue DB/backups. Current mail driver logs instead of sending SMTP |

Framework retry count and Telegram transport retry are separate. The job does not implement `ShouldBeUnique` or automatic framework backoff; duplicates can be dispatched for due pending deliveries by tick. The database delivery claim prevents simultaneous accepted sends. Delivery service handles rejected transient API responses with due-time delays; do not raise worker tries to compensate.

Queue heartbeat key `queue_worker_heartbeat_at` is written by `Queue::looping` and `Queue::before` hooks in `AppServiceProvider`, TTL 300 seconds. A worker starting against an empty queue still leaves evidence. Neither `jobs=0` nor a fresh scheduler heartbeat proves H4 ran.

## 8. Hostinger Queue Worker Strategy

Use **one locked, bounded worker started every minute**, not Supervisor/Horizon, an unmaintained `nohup` daemon, `queue:listen`, or `QUEUE_CONNECTION=sync`. No persistent process-management interface was available in the inspected SSH account; its plan name is unknown, so this report does not invent plan-specific guarantees.

Actual H4 command inside the wrapper:

```bash
/usr/bin/timeout --signal=TERM --kill-after=5s 55s /opt/alt/php84/usr/bin/php artisan queue:work database --queue=default --stop-when-empty --max-time=20 --timeout=30 --tries=1 --sleep=1 --memory=256 --no-interaction
```

Run from the exact application root. `--stop-when-empty` exits idle workers; `--max-time=20` is checked between jobs, not a hard interrupt. A final 30-second job can extend execution toward 50 seconds. External TERM at 55 seconds and kill-after 5 seconds bound a stuck invocation; the envelope remains below reservation expiry 90 seconds. PHP pcntl support was verified. `--memory=256` is an initial worker recycle threshold, not a measured peak or PHP's allocation ceiling. Record peak usage under real workload before changing it. Sleep 1 applies while checking available work; there is no idle daemon holding memory all day.

An independent nonblocking host lock prevents overlapping H4 invocations. Exit 124 denotes external timeout; nonzero failures require queue/history review. Lock files may remain on disk after exit; OS locks automatically release, so do not delete lock files to force parallel workers. A bounded worker may leave backlog; monitor oldest available job and throughput instead of multiplying workers without capacity/rate-limit evidence.

After deployment, run `queue:restart`: an existing worker notices the cache restart marker between jobs, and next cron invocation boots the new code/configuration. Do not use `--force` to ignore maintenance deliberately imposed for a deployment. Restoring normal application operation precedes worker acceptance.

## 9. Deterministic Queued Delivery Test

`telegram:test 1` and the admin Test button use **direct delivery**. They cannot establish that a queued message was consumed. The following developer-only QA operation creates one labeled message with no student data, places the existing job on a temporary **`operations-qa`** queue, and consumes only that queue. It does not alter queue configuration or process customer jobs. This temporary test queue is not a fifth recurring entry.

First verify bot 1/destination 1 still exist and are enabled. Use the already verified destination; do not create a new token or publish its chat ID. In a quiet minute, hold H1's lock for the enqueue/consume sequence so tick cannot dispatch a competing default job. H4 only consumes default and cannot steal this test.

```bash
/usr/bin/flock -n /home/u494520852/operations/locks/scheduler.lock /usr/bin/bash <<'QA'
set -eu
cd /home/u494520852/domains/mohamedateff.com/arabictutor_app
PHP=/opt/alt/php84/usr/bin/php
"$PHP" artisan tinker --execute '$d=App\Domains\Notifications\Models\TelegramDestination::findOrFail(1); if (!$d->enabled || !$d->bot->enabled) { throw new RuntimeException("QA destination disabled"); } $identity=(string) Illuminate\Support\Str::uuid(); $m=App\Domains\Notifications\Models\TelegramDelivery::create(["telegram_bot_id"=>$d->telegram_bot_id,"telegram_destination_id"=>$d->id,"dedupe_key"=>hash("sha256","operations-qa:".$identity),"trigger"=>"test","payload"=>["parts"=>["TEST — queued operations QA. No student or customer data. Reference ".$identity]],"status"=>"pending","due_at"=>now("UTC")]); Illuminate\Support\Facades\Bus::dispatch((new App\Jobs\DeliverTelegramMessage($m->id))->onConnection("database")->onQueue("operations-qa")); dump(["qa_delivery_id"=>$m->id,"queued_qa_jobs"=>Illuminate\Support\Facades\DB::table("jobs")->where("queue","operations-qa")->count()]);' --no-interaction
/usr/bin/timeout --signal=TERM --kill-after=5s 55s "$PHP" artisan queue:work database --queue=operations-qa --stop-when-empty --max-jobs=1 --max-time=20 --timeout=30 --tries=1 --sleep=1 --memory=256 --no-interaction
"$PHP" artisan tinker --execute 'dump(["qa_queue_remaining"=>Illuminate\Support\Facades\DB::table("jobs")->where("queue","operations-qa")->count(),"worker"=>Illuminate\Support\Facades\Cache::get("queue_worker_heartbeat_at"),"recent_tests"=>App\Domains\Notifications\Models\TelegramDelivery::where("trigger","test")->latest("id")->limit(3)->get(["id","status","attempts","message_ids","failure_code","created_at"])->toArray()]);' --no-interaction
QA
```

PASS: captured delivery ID was queued, worker processed it, QA queue drained, heartbeat refreshed, same ID is `sent` with provider receipt and one labeled message arrived. Retain that ID as evidence; never dump payloads. A rate limit may defer this QA delivery and dispatch its retry to default; let H1/H4 resolve it and inspect its exact history, rather than creating more QA messages. Network-uncertain records are not automatically resent. For that outcome, prove worker consumption separately and mark provider acceptance inconclusive until the destination/history are reviewed. Do not declare sent based on process exit alone.

Cleanup: preserve delivery/audit history, ensure `operations-qa` has no residual job, and let the OS lock release naturally. Do not clear customer queues. This proves the job path manually; section 31B separately proves the recurring H4 path. `queue:restart` plus two later fresh H4 invocations verifies deployment restart behavior.

## 10. Telegram Outbound Flow

Business event → after-commit listener → enabled matching rule and conditions → enabled destinations → encrypted `telegram_deliveries` payload with unique dedupe key → after-commit dispatch of delivery-ID job → H4 → current rule/source eligibility checks → Telegram `sendMessage` → receipt/status history.

`telegram_bots` owns encrypted token, enabled/command gates, selected commands, update_offset and last success/failure. `telegram_destinations` owns bot relation, chat_id, type, detail_level, enabled and allowed_user_ids. `telegram_rules` owns trigger/mode/priority/timing/cooldown/conditions/template; `telegram_destination_rule` links recipients. `telegram_rule_states` persists cooldown entity hashes; `telegram_deliveries` owns encrypted payload, due/attempt timestamps, status, attempts, next_part, message_ids and failure_code. Tokens/recipient IDs belong in the authenticated center, not environment/cron arguments copied between operators.

Events originate from booking-created/rescheduled/cancelled ledger events, session-ledger package scans, submitted forms, resource requests/downloads, expired holds, maintenance transitions, backup outcomes, or tick scans. An alert failure is caught so it cannot roll back the main booking/payment/form transaction. Quiet hours defer normal/low alerts to the business-local end of the quiet interval; critical bypasses quiet hours. Delayed mode adds `minutes`; it is separate from reminder lead-time scanning.

Plain-text parts are split at 3500 Unicode characters. API connect timeout is 2 seconds and total request timeout 5. Per-bot send lock is 30 seconds; rate limits are one per second per chat, 20 per minute per bot, and 20 per minute for group chats. Multipart progress (`next_part`, `message_ids`) is persisted; next part defers three seconds. Lock/rate contention defers five seconds.

Rejected HTTP/API 429 or >=500 may retry while delivery attempts <4; delay is bounded exponential with Telegram retry-after respected (normally 60, 120, 240 seconds for the first three failed attempts; capped at 3600). Other rejection codes fail. Network exceptions become **uncertain**, with no automatic resend. Tick marks a delivery left `sending` for over five minutes uncertain (`worker_interrupted`). Telegram may already have accepted it: do not reset status or retry blindly. History has UTC timestamps, status, attempt count, part receipts, and safe failure category; payload is encrypted and omitted from ordinary history.

## 11. Telegram Inbound Mode

**POLLING conclusively:** `TelegramCommandService::poll()` calls `getUpdates` with persisted bot offset, limit 50, timeout 0, messages only. No application webhook route or webhook-secret implementation exists. Read-only API inspection returned HTTP 200, `ok=true`, empty webhook URL, zero pending updates. Do not configure webhook URL, public callback route, or deleteWebhook as part of this handoff.

H3 independently executes `telegram:poll` once per minute. Each enabled bot with commands enabled takes a `telegram-poll:{botId}` cache lock (45 seconds), handles updates, and advances `telegram_bots.update_offset`. It is one bounded poll, not an infinite loop. A replay is deduped by update ID/destination; unauthorized updates are ignored but their offset advances.

Current global commands flag is disabled; bot 1 commands are disabled with no selected list. In Telegram Bots administration enable global commands, enable commands on existing bot 1, select `today`, `tomorrow`, `student`, `stats`, and explicitly authorize the trusted sender on destination 1. For this existing **private chat**, its numeric private-chat recipient identifies the private user's ID; use the value already stored in the destination internally, without copying it into this report or logs. If the destination is later converted to a group, enumerate permitted sender IDs explicitly; group chat ID is not a user ID. Keep the existing token unchanged. H3 before these gates are enabled intentionally does no inbound work.

## 12. Interactive Commands

| Command | Actual behavior | Access / data scope |
| --- | --- | --- |
| `/today` | Confirmed sessions in current business calendar day, up to 50 | Matching enabled destination, explicit allowed sender, command allowlist; summary destination uses lesson IDs, personal destination includes names |
| `/tomorrow` | Same for next business day | Same; business-local boundaries converted to UTC |
| `/student Exact Full Name` | Normalized exact full-name match; ambiguity returns no unique match; active packages/credits/expiry and private admin link | Personal-detail destination only; no fuzzy mass lookup or form answers/DOB |
| `/stats 7d` | Current and preceding six business days, canonical traffic/report metrics | Authorized sender; retained-history caveats included |
| `/stats 30d` | Current and preceding 29 business days | Same |
| `/stats` | Usage reply requesting 7d or 30d | Not a default full report |

Supported syntax is **`/stats 7d`**, not `/stats7d`. Bot-name suffix is allowed. Sender/chat/bot limit is 20 commands per minute; argument length is bounded to 120 characters. Replies are delivered directly and recorded, although deferred/multipart continuation can require H4. `/stats` derives reports from `AnalyticsService`, `ReportService`, social events, acquisition sources, country rollups, and retained page-view events; daily uniques summed across older history are labeled accordingly and cannot reconstruct exact distinct range visitors. Before analytics cutover, periods may be incomparable. Enabling polling does not repair missing rollups or historical source coverage.

## 13. Complete Telegram Rule Inventory

Catalog supports **20 types**. Production has only the two reminder rules described in section 2. Catalog defaults are not configured production rules. Common UI defaults: normal priority, window 60 minutes, cooldown 1440 minutes, send time 08:00, no quiet interval; ordinary types support instant/delayed, digests support scheduled/on_demand. Except noted, catalog minutes=60 and threshold=1. Each type additionally requires global automation, enabled bot/rule/destination, and conditions matching its payload.

| Rule | Trigger | Instant/Scheduled/Digest | Scheduler required? | Queue required? | Default timing | Dedupe/cooldown | Data source |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Booking created | booking_created | Event instant/delayed | No for initial event | Yes | Post-commit; delayed minutes 60 | Booking event identity | Committed BookingEvent |
| Booking rescheduled | booking_rescheduled | Event instant/delayed | No for initial event | Yes | Post-commit; delayed 60 | Booking event identity | Committed BookingEvent |
| Booking cancelled | booking_cancelled | Event instant/delayed | No for initial event | Yes | Post-commit; delayed 60 | Booking event identity | Committed BookingEvent |
| Low session credits | low_credits | Event + threshold scan | Yes for ongoing scan | Yes | Every tick; threshold <=1 | Package cooldown entity | Active package/append-only ledger summary |
| Unused package nearing expiry | package_expiring | Event + calendar scan | Yes for aging | Yes | Every tick; within 14 business days | Package cooldown entity | Package expiry + positive ledger balance |
| Form submitted | form_submitted | Event instant/delayed | No for initial event | Yes | Post-commit; delayed 60 | Submission/revision identity | Submitted form metadata, no answers |
| Required form missing | missing_form | Scheduled scan | Yes | Yes | Every tick; 720-minute lead | Booking/UTC start + form version | Mandatory assignments + submitted versions |
| Lesson countdown | session_reminder | Scheduled scan | Yes | Yes | Every tick; catalog lead 60; configured 1440/210 | Booking/UTC start per rule/destination | Confirmed upcoming bookings and meeting gates |
| Resource access requested | resource_lead | Event instant/delayed | No for initial event | Yes | Post-commit; delayed 60 | Request identity | ResourceRequest |
| Resource downloaded | resource_downloaded | Event instant/delayed | No for initial event | Yes | Post-commit; delayed 60 | Download identity | ResourceDownload |
| Contact left expired hold | hold_abandoned | Cleanup event | Yes | Yes | Five-minute cleanup; optional delayed 60 | Hold identity | Newly expired hold with valid collected contact |
| Traffic spike | traffic_spike | Threshold scan | Yes | Yes | Every tick; threshold 50, window 60 | Traffic cooldown entity | Eligible unique active visitors/source summary |
| Scheduler stale | scheduler_stale | Independent health scan | **No; H2 required** | **No for direct H2 fallback** | Every H2; stale minutes 15 | Health-type cooldown entity | Settings scheduler UTC heartbeat |
| Queue stale | queue_stale | Independent health scan | **No; H2 required** | **No for direct H2 fallback** | Every H2; stale minutes 15 | Health-type cooldown entity | Cache worker heartbeat / persisted last_queue_seen |
| Backup completed | backup_success | Outcome event | Yes for scheduled backup | Yes | After 02:00 UTC backup; optional delayed 60 | Backup filename identity | Local/offsite backup metadata |
| Backup failed | backup_failure | Outcome event | Yes for scheduled backup | Yes; not queue-independent | At failure; optional delayed 60 | Failure/backup identity | Local error/offsite category |
| Maintenance enabled | maintenance_enabled | Transition event | No for initial event | Yes | Post-commit; optional delayed 60 | Incident identity | Maintenance setting transition |
| Maintenance too long | maintenance_duration | Duration scan | Yes | Yes | Every tick; 120-minute threshold | Incident-start cooldown entity | Maintenance flag + UTC incident start |
| Business digest | business_digest | Scheduled/on-demand digest | Yes for scheduled mode | Yes | Business-local send_time 08:00 | Local day; on-demand UUID | Bookings/students/resources/system/analytics selections |
| Analytics digest | analytics_digest | Scheduled/on-demand digest | Yes for scheduled mode | Yes | Business-local send_time 08:00 | Local day; on-demand UUID | Canonical stats(1), reports and retained events |

Delivery-level dedupe is the unique rule/destination/identity hash; cooldown state uses hashed entity and last emission. Different enabled rules intentionally produce separate alerts. Existing imported reminder rules additionally honor legacy booking-event idempotency; never add the legacy `booking:send-telegram-reminders` cron beside tick.

Digest sections are bookings, students, resources, system, analytics. Bookings-created and sessions-today are different counts. Student digest low/expiry counts use fixed 1-credit/14-day summary thresholds, not arbitrary configured per-rule thresholds. Templates accept catalog-approved placeholders; use preview before saving. General rule condition keys are `_student_id`, `_session_type_id`, `country`, `source`; unsupported filters cannot be invented.

## 14. Session Reminders

H1 runs tick every minute. Tick scans confirmed future bookings starting within each rule's `minutes`, builds business/student timezone times, and dedupes booking ID + current UTC start per rule/destination. H4 handles delivery; it rechecks confirmed/future/same-start eligibility, cancelling stale queued messages after cancellation/reschedule. Missing forms additionally recheck submitted state. Reminder lead time, destination count, quiet hours and delayed mode affect when a message can actually be delivered.

Current milestones are **24 hours and 3 hours 30 minutes**, not an assumed ten-minute reminder. No additional student-account Telegram destinations were found. Destination 1 is the existing administrator's private destination. Do not enable a catalog default or change 1440/210 to meet a demonstration. A rescheduled start produces a new identity; the old queued reminder is cancelled. Suspended students are excluded from meeting URL disclosure, but reminder delivery is not globally cancelled merely for suspension; do not infer that policy.

Delivery history proves pending→sending→sent, receipt and attempts. Cancelled with `source_no_longer_eligible` is expected for changed bookings. Stopping H4 can queue a reminder that later becomes ineligible; restoring H4 does not guarantee a missed lesson reminder is sent retroactively.

## 15. Meeting Links and Telegram Timing

`MeetingLinkService` uses the booking's assigned room/provider snapshot and current access gates. Verified active eligible student access, confirmed active booking, room assignment, cancellation/completion, and meeting reveal timing remain authoritative. Public/student reveal is **15 minutes** before start via `meeting.student_reveal_minutes`; Telegram reminder inclusion is a separate service path using reminder lead time. A 1440-minute admin reminder and a 15-minute student reveal are independent operations.

Rooms are reusable configured HTTPS links with overlap-safe assignment; there is **no meeting-provider API** creating conferences or requiring a separate meeting daemon/cron. A contact-only administrator booking has no student verification gate, whereas a booking linked to a student requires that student to be verified and unsuspended for disclosure.

Tick initially prepares URL/provider; delivery re-resolves those fields before sending, so a removed/reassigned room or lost access cannot rely on an old queued URL. Other pre-rendered fields are not universally recomputed; changing timezone/template after enqueue does not rewrite all queued text. Missing room/access yields Unavailable, not a replacement meeting architecture. Host operations must preserve existing room assignment, verification, suspension, and cancellation rules.

## 16. Analytics Scheduled Operations

At current Cairo business timezone, sequence is 00:02 country rollup, 00:05 daily rollup, then that daily command **synchronously calls country rollup again for the same business date before pruning**. Default date is yesterday, a completed business day. Daily writes are transactional and replace only date + reporting_timezone; country writes are timezone-scoped. Raw current-day reporting and timezone/date cache namespaces are separate from completed rollups. Empty/zero activity is valid; success is not an arbitrary positive count.

Actual stored rows use `daily_metrics(metric_date, metric_name, dimension_key, dimension_value, count, reporting_timezone)` and `daily_country_metrics(metric_date, country_code, unique_visitors, sessions, page_views, bounced_sessions_count, booking_cta_clicks, bookings_completed, resource_requests, reporting_timezone, ...)`. Acquisition visitor country, session-start country and event/booking snapshot country are distinct dimensions; unresolved ZZ must remain unresolved.

**Known code caveat:** the 00:02 registration schedules in configured Business Timezone but explicitly calculates its argument as yesterday in Africa/Cairo. Current configuration is Cairo, so it aligns today; a non-Cairo setting can select a different day. The 00:05 parent command's own business date and synchronous country call are the stronger order guarantee. This report does not modify this real defect or change timezone to hide it.

There is no separate scheduled historical reconciliation, data-quality repair, backfill, or current-day rollup command. Do not fabricate one. Verify yesterday's matching-timezone rows and timestamps after the next real 00:05 run. Counts must correspond to retained eligible raw records and the same canonical admin reports, not legacy funnel imputation. Routine H1 setup must not use `--rebuild-funnel` or `analytics:backfill-daily-country`.

## 17. Pruning Safety and Retention

Normal scheduled daily command includes `--prune`; **QA/manual smoke tests must supply an explicit `--date=YYYY-MM-DD` AND omit `--prune`**. The actual guard is `option('prune') || ! $dateInput`: omitting `--prune` alone does not disable retention. A bare default invocation prunes too. Section 31J derives yesterday in the configured business timezone and passes it explicitly. Country command failure returns failure before retention. Retention has more than one path:

| Actual cleanup | Boundary / guard |
| --- | --- |
| Eligible analytics events and sessions | Setting `analytics_retention_days`, currently 180; completed business dates; daily visitor + country rollup existence guard |
| Second analytics-event cleanup | Fixed 90-day event retention; requires daily visitor and country rows for matching reporting timezone; chunks of 1000 |
| Marketing touches | Fixed 90-day cutoff |
| Student auth attempts | Expired cooldown older than 7 days or record older than 30 days |
| Generated exports | Files older than 24 hours |
| Historical visits/funnel | Not automatically rewritten by normal daily command |

**Known retention caveat:** the first configurable-retention guard checks existence of rollups without filtering `reporting_timezone`; the later 90-day event guard does filter it. Thus the code is not universally safe across timezone changes. It also means successful event retention can be effectively 90 days even when the setting reads 180; session retention remains the configurable path. Do not claim uniform 180-day raw retention. Guarded days without rollups are skipped rather than backfilled, which preserves raw data but increases storage.

H1 will invoke the already registered production retention at its normal time. Before activation document current timezone and retention settings, verify a fresh backup, and acknowledge the two code caveats above. There is no instruction here to run pruning manually, delete rows for verification, or change timezone. If cross-timezone legacy rollups are present, pause activation of the destructive retention component through a separately reviewed code repair; an unreviewed cron bypass cannot correct the guard. Current rollup tables were empty at inspection, so no foreign-timezone rows were observed. Compare actual rollup coverage to raw dates; a command exit zero with skipped-day warnings is not proof all history was aggregated.

## 18. GeoIP Updater

`geoip:update` is registered monthly on day 2 at 04:00 Business Timezone with a 120-minute cache mutex. It requires H1's scoped subprocess support. There is no automatic catch-up for a missed monthly minute. Production path is `/home/u494520852/domains/mohamedateff.com/arabictutor_app/storage/geoip/GeoLite2-Country.mmdb`; inspected metadata identifies **DBIP-Country-Lite**, build epoch 1790818572. The filename does not imply a MaxMind license/source.

Explicit `--url` overrides configured URL; configured MaxMind credentials choose MaxMind when present; current credentials/URL are missing, so updater uses the DB-IP free current-month `.mmdb.gz` source. HTTP download timeout is 120 seconds. It supports MMDB/gzip/tar archive handling, validates size and country database metadata, stages beside the destination, atomically renames the valid result, and retains the old file on failure. Temporary downloads are cleaned. Do not run arbitrary untrusted `--url` or replace a known-good file with an empty download.

Developer activation may run the existing command once because October's scheduled slot was already missed; this updates only the dataset:

```bash
cd /home/u494520852/domains/mohamedateff.com/arabictutor_app
/opt/alt/php84/usr/bin/php artisan geoip:update --no-interaction
/opt/alt/php84/usr/bin/php -r 'require "vendor/autoload.php"; $r=new GeoIp2\Database\Reader("storage/geoip/GeoLite2-Country.mmdb"); $m=$r->metadata(); echo json_encode(["type"=>$m->databaseType,"build_epoch"=>$m->buildEpoch,"build_utc"=>gmdate("c",$m->buildEpoch)]).PHP_EOL; $r->close();'
```

Record metadata, permissions and command outcome; later prove the actual monthly schedule rather than inferring it from manual success. Resolver trusts forwarded country headers only with configured trusted-proxy CIDRs, otherwise performs lookup with transient IP; it must not treat arbitrary client country headers as authoritative. DB-IP attribution remains in public footer. Unknown country is a coverage state, not permission to backfill or guess old visitors.

## 19. Booking Hold Cleanup

`booking:cleanup-holds` delegates to `BookingHoldService`. Current default TTL is **10 minutes**. Every five minutes, H1's callback locks eligible active expired holds, changes them to expired, and emits an abandoned-lead event after commit only when valid collected contact data exists. Converted/released holds do not generate abandoned-lead alerts. It prunes expired/released holds older than 30 days, up to 1000 per run.

Authoritative availability already excludes holds whose `expires_at` is past; a stopped cleanup task does not extend a ten-minute hold indefinitely or require a booking-lock rewrite. Cleanup delays persisted statuses, lead alerts, and old-row removal. Successful scheduled invocation advances `last_holds_cleanup_at`. A manual invocation does not replace scheduled success-hook evidence.

No separate host hold cron is needed. A production acceptance hold must use a future unoccupied slot and synthetic contact only, remain unconfirmed, and expire naturally; never expire/convert a customer's hold to demonstrate cleanup.

## 20. Package, Form and Retention Automation

Low credits and unused package expiry read `StudentLedgerService`'s append-only ledger summary and active packages; they do not calculate credits from invoice balance or mutate billing. Expiry compares business calendar dates/end-of-day, ignores already expired packages, and requires remaining credits for the expiry warning. Low-credit threshold and expiry-day threshold are configured per rule; repeated conditions obey package cooldown. Ledger changes can enqueue an immediate eligible scan, while H1 tick catches date-driven changes later.

Missing forms use mandatory assignments and published form versions for confirmed upcoming bookings; no submitted answer bodies enter alert payloads. Required-form lead is separate from meeting reveal and session reminders. H4 is required to deliver any of these ordinary alerts. Currently none of these rule types is configured, so installing cron enables the machinery but does not create business rules.

Do not create fake paid packages, change credits, complete actual lessons, or shorten customer expiry for QA. Verify package/form edge cases using the existing isolated tests and synthetic fixtures outside the production database. Retention is described in section 17; no separate Telegram-delivery retention task is registered, and delivery history may grow.

## 21. Traffic Spike

Tick evaluates enabled `traffic_spike` rules using `AnalyticsService::getActiveVisitorsCount(rule.window_minutes)` and the active-visitor source summary, not lifetime page views. Defaults: threshold 50 unique active visitors, window 60 minutes, cooldown 1440 minutes in the rule form. The rule's minutes field is not the traffic lookback; `window_minutes` is. Threshold comparison is >=. Ordinary analytics exclusions still apply; the public 60-second banner and admin five-minute live pulse are different windows.

Requires collected eligible analytics data, an enabled configured rule, H1 and H4. None is currently configured. Do not lower a business threshold or generate artificial production visitor traffic to manufacture a pass. Use isolated test data for threshold/cooldown behavior and production read-only counts for collection evidence. There is no additional traffic cron.

## 22. Maintenance Reminders

Maintenance transition records `telegram.maintenance_since` in UTC and emits `maintenance_enabled` after commit. Re-saving an already true flag does not create a new incident. Tick emits `maintenance_duration` after the rule's minutes threshold, with incident-based cooldown; default threshold 120 minutes. Requires H1/H4, enabled rule/bot/destination/automation. Current public maintenance is off; no maintenance rule is configured.

Do not take production offline to test this. Section 31N defines an isolated automated proof of duration/cooldown plus a labeled production transport equivalent with no maintenance-state change. Application setting maintenance still permits scheduler execution; Artisan `down` is a different deployment control and normally suppresses scheduled tasks/worker execution. Host operations must not use either to hide missing automation.

## 23. Backup Architecture and Offsite Truth

`backup:run` calls `BackupService`: SQL dump, private/public application storage plus legacy resource/media directories if present, ZIP manifest with UTC creation time, runtime versions, database SHA-256, and file hashes/sizes. Its type option labels the archive; it does not establish separate database-only versus files-only scheduling. Source/vendor/build/.env/GeoIP are not a complete part of this backup; deployment snapshots remain separate and must be retained for code rollback.

Local directory: `/home/u494520852/domains/mohamedateff.com/arabictutor_app/storage/app/backups`. Default database dump path uses PDO; optional mysqldump configuration follows a different executable path. Normal callback runs **02:00 UTC**, which is 05:00 in Cairo at the inspected +03 offset, not 02:00 Cairo. `--clean` removes local and configured offsite ZIPs older than the current 30-day retention by modification time. Cleanup is not an acceptance test.

S3 replication is implemented using the configured disk and key `backups/{archive filename}`. S3 credentials/bucket were present but replication was **failed** (`s3_replication_failed`). A successful local command can still return zero when offsite upload fails; backup success/failure alerts include distinct local/offsite outcomes. A zero exit code or recent ZIP is insufficient for backup readiness.

Developer action: inspect the private configured S3 disk's endpoint/region/bucket/access policy in the provider and host configuration without copying credentials into tickets. Verify object write/read permissions and endpoint compatibility using the configured disk; after correcting the actual provider/configuration cause under normal deployment control, create one new backup **without `--clean`** and verify that exact object exists/readably matches the local archive. Do not assume missing credentials merely because upload failed, rotate credentials speculatively, use public object ACLs, or suppress the warning.

Read-only metadata checks (no secrets):

```bash
cd /home/u494520852/domains/mohamedateff.com/arabictutor_app
/opt/alt/php84/usr/bin/php artisan tinker --execute 'dump(["backup_at"=>App\Domains\CMS\Models\Setting::get("last_backup_at"),"local_status"=>App\Domains\CMS\Models\Setting::get("last_backup_status"),"file"=>App\Domains\CMS\Models\Setting::get("last_backup_file"),"offsite_status"=>App\Domains\CMS\Models\Setting::get("last_offsite_backup_status"),"offsite_category"=>App\Domains\CMS\Models\Setting::get("last_offsite_backup_category")]);' --no-interaction
```

Developer acceptance creates a backup, then verifies ZIP integrity without extraction/restoration:

```bash
cd /home/u494520852/domains/mohamedateff.com/arabictutor_app
/opt/alt/php84/usr/bin/php artisan backup:run --type=full --no-interaction
/opt/alt/php84/usr/bin/php artisan tinker --execute '$f=basename((string) App\Domains\CMS\Models\Setting::get("last_backup_file")); $z=new ZipArchive; if ($z->open(storage_path("app/backups/".$f))!==true) { throw new RuntimeException("Cannot open backup"); } $m=json_decode($z->getFromName("manifest.json"),true,512,JSON_THROW_ON_ERROR); $s=$z->getStream("database.sql"); if (!$s) { throw new RuntimeException("Missing database SQL"); } $h=hash_init("sha256"); hash_update_stream($h,$s); fclose($s); if (!hash_equals($m["database_sha256"],hash_final($h))) { throw new RuntimeException("Database checksum failed"); } foreach ($m["files"] as $name=>$meta) { $s=$z->getStream($name); if (!$s) { throw new RuntimeException("Missing archive file"); } $h=hash_init("sha256"); $size=hash_update_stream($h,$s); fclose($s); if (!hash_equals($meta["sha256"],hash_final($h)) || $size!==$meta["size_bytes"]) { throw new RuntimeException("File checksum/size failed"); } } $z->close(); dump(["archive"=>$f,"manifest_checks"=>"PASS"]);' --no-interaction
/opt/alt/php84/usr/bin/php artisan tinker --execute '$disk=config("filesystems.backup_disk") ?: App\Domains\CMS\Models\Setting::get("backup_offsite_disk"); $f=basename((string) App\Domains\CMS\Models\Setting::get("last_backup_file")); $s=Illuminate\Support\Facades\Storage::disk($disk)->readStream("backups/".$f); if (!is_resource($s)) { throw new RuntimeException("Offsite archive not readable"); } $h=hash_init("sha256"); hash_update_stream($h,$s); fclose($s); dump(["offsite_hash_matches"=>hash_equals(hash_file("sha256",storage_path("app/backups/".$f)),hash_final($h))]);' --no-interaction
```

Run in a quiet window outside the daily backup; capture filename to distinguish another concurrent backup. PASS includes manifest checks, offsite state success and full stream hash matching, plus later actual scheduled backup evidence. These are future implementation checks; none ran during this report-only pass. Do not run `backup:restore`, even with `--no-db --no-files`, as a read-only validator: restore can create/extract files. Restore drills belong in a separate isolated environment with protected data.

## 24. Queue Failure Handling and Safe Retry

```bash
cd /home/u494520852/domains/mohamedateff.com/arabictutor_app
/opt/alt/php84/usr/bin/php artisan queue:failed --no-interaction
/opt/alt/php84/usr/bin/php artisan tinker --execute 'dump(["pending"=>Illuminate\Support\Facades\DB::table("jobs")->count(),"failed"=>Illuminate\Support\Facades\DB::table("failed_jobs")->count(),"oldest_available_unreserved"=>Illuminate\Support\Facades\DB::table("jobs")->whereNull("reserved_at")->where("available_at","<=",time())->min("created_at"),"queues"=>Illuminate\Support\Facades\DB::table("jobs")->select("queue")->selectRaw("COUNT(*) AS jobs")->groupBy("queue")->get()->toArray()]);' --no-interaction
```

The standard failed-job listing reveals IDs/connection/queue/job class without requiring payload dumps. Exceptions/payloads require private inspection and may contain sensitive mail/URL data. Failure observer attempts admin notification; a failed queue cannot be relied upon to deliver ordinary queued failure alerts. H2 provides the independent stale-worker route.

For **one reviewed failed job**, take its UUID from the listing and use SSH interactive input so it is not an invented identifier:

```bash
read -r -p 'Reviewed failed-job UUID: ' FAILED_UUID
/opt/alt/php84/usr/bin/php artisan queue:retry "$FAILED_UUID" --no-interaction
```

Retry only after determining the domain state and safe cause. A Telegram delivery already `failed`/`uncertain`/`sent` may not be re-sent merely by replaying its ID job; delivery eligibility/status governs. For an uncertain accepted outcome, review destination receipts and do not reset its status manually. Resource PIN retries must consider expiry and SMTP configuration. Prefer one exact reviewed UUID, never `queue:retry all` to clear an incident.

`queue:forget`, `queue:flush`, `queue:clear`, and failed-job pruning exist but delete operational evidence or pending work; do not use them as recovery smoke tests or add retention cron without a separate retention decision. `queue:restart` does not consume queued jobs or fix Telegram credentials.

Only after retaining evidence and deciding that one reviewed failed job is intentionally discarded, use its captured UUID:

```bash
read -r -p 'Reviewed UUID approved for forgetting: ' FAILED_UUID
/opt/alt/php84/usr/bin/php artisan queue:forget "$FAILED_UUID" --no-interaction
```

This deletes that failed-job record, not its Telegram delivery history or domain state; it is not routine acceptance cleanup.

## 25. System Health Expectations

Authenticated Super Admin route is `/arabictutor/admin/health` on the existing production website. It tests DB access, storage writes/disk space, cache roundtrip, backup state, scheduler age, database worker heartbeat/pending/failed jobs, and mail driver. Storage/cache probes write temporary state; do not mistake this endpoint for a pure read-only forensic query.

| Component | Expected evidence / unhealthy condition |
| --- | --- |
| DB/cache/storage | Reachable, usable writes; disk usage >90% warns |
| Scheduler | Timestamp moves; >10 minutes stale is unhealthy |
| Worker | Heartbeat refreshed within 5 minutes; pending work + absent/stale heartbeat unhealthy; empty queue + absent heartbeat warning; failed jobs warn |
| Backup | Fresh within 36 hours, local success and offsite success; failed offsite unhealthy even with recent local ZIP |
| Mail | Production `log` driver warns; external delivery needs actual transport verification |
| Telegram | Review separate center history, tick/watchdog keys, inbound offset and rule configuration; System Health has no comprehensive Telegram-green test |
| Analytics | Review separate rollup tables and success keys; System Health does not prove aggregation freshness |

An HTTP 200 homepage or framework `/up` does not prove background execution. An overall all-green claim is false until the known offsite failure and mail-log warning are resolved or explicitly retained as documented exceptions. Host cron acceptance can succeed while those unrelated transport exceptions remain; state them separately in handoff evidence.

## 26. Required Hostinger Jobs

All commands below are **hPanel Custom command-field values**, with all five schedule selectors `*`. They use the one wrapper from section 4.

| ID | Purpose | Cadence | Exact command | Independent from Laravel scheduler? | Queue needed? | Expected success evidence |
| --- | --- | --- | --- | --- | --- | --- |
| H1 | Scheduler | Every minute | `/usr/bin/bash /home/u494520852/operations/run.sh scheduler` | Host entry supplies scheduler | For resulting Telegram/mail only | Scheduler + tick advance; hold/daily/country/backup/GeoIP execute when due |
| H2 | Watchdog | Every minute | `/usr/bin/bash /home/u494520852/operations/run.sh watchdog` | **Yes** | **No for direct health delivery** | Watchdog timestamp moves; bounded dual-lock drill sends labeled health alerts |
| H3 | Inbound Telegram | Every minute | `/usr/bin/bash /home/u494520852/operations/run.sh poll` | **Yes** | Direct initial reply; deferred parts may need queue | Authorized update advances offset; one correct reply, no duplicate |
| H4 | Database queue | Every minute | `/usr/bin/bash /home/u494520852/operations/run.sh queue` | **Yes** | This supplies the worker | Worker heartbeat moves; due jobs processed; receipts/history verified |

Four entries are necessary. Do not add one per alert type, daily aggregation, backup, GeoIP, or hold task. The existing scheduler already owns those cadences. Local report-only documentation does not mean these entries have been installed.

## 27. Copy/Paste Hostinger Commands

### H1 — Custom command field

```bash
/usr/bin/bash /home/u494520852/operations/run.sh scheduler
```

Enter in website hPanel Cron Jobs, Custom, every minute (`* * * * *` selectors). Logs scheduler daily UTC file, expected start/finish plus due task statuses. Hard ceiling 900 seconds + 30-second kill grace; inspect long runs and per-task outcomes. H1 is not a persistent `schedule:work` service.

### H2 — separate Custom entry

```bash
/usr/bin/bash /home/u494520852/operations/run.sh watchdog
```

Same hPanel panel and minute cadence. Expected `Independent heartbeat checks completed.`, moving watchdog key, and actual history receipts when configured rules become stale. Log watchdog daily UTC file; ceiling 120 seconds + 5-second kill grace. Independent lock/process from H1/H4.

### H3 — separate Custom entry

```bash
/usr/bin/bash /home/u494520852/operations/run.sh poll
```

Every minute in hPanel. Expected poll completion and offset advance only when updates exist and commands are enabled. Logs poll daily UTC file; ceiling 55 seconds + 5-second kill grace. No webhook setting or token argument.

### H4 — separate Custom entry

```bash
/usr/bin/bash /home/u494520852/operations/run.sh queue
```

Every minute in hPanel. Expected heartbeat even on empty queue; queued jobs show processing outcomes when present. Logs queue daily UTC file; worker normal max-time 20 seconds checked between jobs, job timeout 30 seconds, external ceiling 55 seconds + 5-second kill grace. `lock_busy` means this role was already running; investigate repeated skips.

### SSH preflight before enabling entries

```bash
cd /home/u494520852/domains/mohamedateff.com/arabictutor_app
/opt/alt/php84/usr/bin/php --version
/opt/alt/php84/usr/bin/php artisan migrate:status --no-interaction
/opt/alt/php84/usr/bin/php artisan schedule:list --timezone=Africa/Cairo --no-interaction
/usr/bin/bash -n /home/u494520852/operations/run.sh
```

Enter over SSH as deployment user, once before installation and after a changed release. All are introspection: versions match, applied migrations, eight tasks, script syntax exit zero. Repeat the scoped subprocess preflight in section 4. Do not run `schedule:run` as an innocent read-only check: at a due time it executes pruning/backup/alerts. After gates/rules are deliberately configured, actual cron should provide the runtime acceptance.

## 28. Logging, Rotation and Permissions

Actual application log is `/home/u494520852/domains/mohamedateff.com/arabictutor_app/storage/logs/laravel.log` (`stack`→`single`); no automatic application-log rotation was discovered. Existing `scheduler.log` and `queue.log` also exist there, but their existence does not establish active cron. New wrapper output goes to four private daily UTC role files under `/home/u494520852/operations/logs`. It removes **only its own role-pattern files older than 14 days** after obtaining the role lock. Preserve incident extracts privately before their retention window closes.

The supplied wrapper also rotates Laravel's single log under a separate nonblocking rotation lock when nonempty and last modified on a prior UTC day, or at >=10 MiB. It moves the old file into the private operations log directory, creates a new owner-only log, and keeps rotated files for 14 days measured from rotation (archive modification time is refreshed). Both paths were verified on the same filesystem during discovery. An already-open PHP logger can append to the moved archive until its request/task exits; archives remain plain/uncompressed so late writes are preserved. Do not compress/delete today's moved archive while an older PHP process still holds it. No persistent application worker exists in the prescribed strategy, so ordinary writers close within their bounded lifetime. The 10 MiB threshold is a rotation policy, not a strict maximum: bytes can grow between cron checks or during host failure. Each role also has a daily file rather than an infinitely growing combined log. Verify concurrent web/cron writes reach either the retained old file or the new file after rotation; do not use copy-then-truncate, which can drop intervening writes. This is part of the same wrapper, **not a fifth cron**. hPanel execution/output evidence supplements, rather than replaces, application keys/history.

Inspected `.env` was mode **640**, owner `u494520852`; application log/older cron logs were **644**; storage/framework/cache directories mostly **755**. Some old backup archives were **644**, newer inspected backup **600**. Therefore logs/old backups are **not presently proven private** at the filesystem mode level. They sit outside the public wrapper, which reduces web exposure but does not fix other-user readability.

Developer correction after verifying web PHP runs as this same account: make private log/backup directories 700 and their files 600, including `.env` 600; keep public storage public and retain framework directory access the web process actually needs. For files beneath private log/backup roots, inspect ownership then restrict only those resolved paths. Do not recursively chmod the whole app or use 777. Use umask 077 in wrapper (already supplied). Existing group ACL requirements must be inspected with the host's supported permission viewer before restriction; none were assumed from mode bits alone. Tokens must not be in cron UI, shell arguments, public URL, or pasted support logs.

After that same-user/ACL verification, the exact restricted-path commands are:

```bash
/usr/bin/chmod 600 /home/u494520852/domains/mohamedateff.com/arabictutor_app/.env /home/u494520852/domains/mohamedateff.com/arabictutor_app/.env.backup.before-7123f15
/usr/bin/chmod 700 /home/u494520852/domains/mohamedateff.com/arabictutor_app/storage/logs /home/u494520852/domains/mohamedateff.com/arabictutor_app/storage/app/backups
/usr/bin/find /home/u494520852/domains/mohamedateff.com/arabictutor_app/storage/logs -maxdepth 1 -type f -exec /usr/bin/chmod 600 '{}' +
/usr/bin/find /home/u494520852/domains/mohamedateff.com/arabictutor_app/storage/app/backups -maxdepth 1 -type f -name '*.zip' -exec /usr/bin/chmod 600 '{}' +
```

These are future security corrections, not performed here. Keep the untracked environment recovery copy out of Git; do not use broad staging on the host. If that old file has already been archived elsewhere during a future deployment, omit only its chmod argument after confirming it is absent; never recreate it from this report.

## 29. Future Deployment Behavior

This pass does not deploy. Future authorized deployment checklist:

1. Record release SHA and current backup/rollback artifacts; preserve production `.env`, private storage, bot token ciphertext, APP_KEY, DB and host wrapper. Verify compatibility before changing dependencies.
2. Install the release's locked production Composer dependencies and frontend build artifact (`public/build/manifest.json` and assets) using the existing release process. The CLI worker does not build browser assets. Composer/npm development `dev` processes are not production cron services.
3. After release files and database compatibility are ready, apply pending additive migrations with the command below; do not refresh/fresh/rollback the production DB for acceptance.
4. Rebuild caches against the production configuration, then notify existing workers to restart.
5. Verify H1/H2/H3/H4 use the unchanged root and current PHP version; fresh bounded invocations boot the new release automatically. No persistent scheduler/poller/watchdog restart is required.
6. Check heartbeat/tick/offset/worker evidence and actual scheduled task results, not just homepage rendering. If an in-progress old scheduler command needs coordinated interruption, `schedule:interrupt` exists, but with no sub-minute schedule here routine restart is unnecessary; never interrupt an active backup casually.

```bash
cd /home/u494520852/domains/mohamedateff.com/arabictutor_app
/opt/alt/php84/usr/bin/php artisan migrate --force --no-interaction
/opt/alt/php84/usr/bin/php artisan config:cache --no-interaction
/opt/alt/php84/usr/bin/php artisan route:cache --no-interaction
/opt/alt/php84/usr/bin/php artisan view:cache --no-interaction
/opt/alt/php84/usr/bin/php artisan queue:restart --no-interaction
```

These are mutating deployment commands, not report diagnostics. `queue:restart` is cooperative and uses cache. Avoid broad cache clearing that erases heartbeat/locks or allows overlapping delivery. If a deployment intentionally uses Artisan down, restore normal operation through that deployment's existing procedure before expecting jobs; do not overwrite the application's business maintenance setting. On configuration changes, rebuild caches rather than assuming editing `.env` reaches an already cached application.

## 30. Failure Scenarios

| Symptom | Likely cause | How to verify | Safe recovery |
| --- | --- | --- | --- |
| Scheduler stopped | H1 missing, bad path/PHP, host denial, lock held | H1 start/finish/skip logs, old heartbeat, process list, hPanel execution | Correct exact H1 wrapper/path; inspect live lock owner; next minute must advance heartbeat/tick; do not fake timestamp |
| Queue stopped | H4 absent, startup exception, DB/cache inaccessible | Worker key, H4 logs, jobs oldest age, failed_jobs | Repair H4 cause; restore bounded worker; examine stale deliveries before retry |
| Scheduler fresh, queue stale | Independent H4 failed | Scheduler/tick advance but worker absent and deliveries pending | Fix H4 only; do not switch sync or claim H1 proves worker |
| Queue fresh, scheduler stale | H1 failed | Worker key advances, tick/hold keys do not | Fix H1 scoped subprocess/runtime and cron; H2 remains active |
| Telegram unavailable | Network/API rejection/rate limit | Safe bot failure_code/history, pending due times, API timeout category | Rejected transients follow service delay; uncertain outcomes require receipt review; do not regenerate jobs blindly |
| Poller stopped/no reply | H3 missing or command gates/users disabled | H3 logs, bot offset, allowed command/user gates, provider webhook-presence check | Restore H3 and explicit authorization; preserve offset, no second poller |
| Webhook rejected / getUpdates conflict | An external webhook was configured despite polling design | Read-only getWebhookInfo URL-present/error category | Restore the intended polling-only provider configuration through its authenticated owner tooling after identifying unexpected webhook; do not create an app webhook or reset offset |
| Backup failed | DB/ZIP/storage capacity or S3 failure | Local and offsite state separately, ZIP manifest, free space, protected log | Repair actual failed component; create new non-cleaning backup and hash-verify exact offsite object |
| Analytics rollup failed | Missed minute, DB exception, invalid date/timezone | Success keys, yesterday/date+timezone rows, retained-data comparison, logs | Rerun completed-date aggregation without prune after fixing cause; no backfill or funnel rebuild |
| GeoIP update failed | Source unavailable, invalid archive, size/metadata/permissions | Old metadata intact, update error, monthly schedule entry | Retain old valid file; rerun default updater after actual cause fixed; no invented country mappings |
| Database unavailable | MariaDB/service/network/resource pressure | Connection errors in all role logs, host database resource status | Restore DB/service access; OS processes resume next cron; inspect pending/uncertain state before retry |
| Host cron never executes | Entry not saved/enabled, quota, wrong website/command | No role log start at all, hPanel job inventory/execution output, harmless preflight | Install four Custom entries on correct website/account; fix host quota/runtime permission, save execution evidence |
| Worker exited after deployment | Expected queue:restart or bounded cycle | H4 next invocation boots, fresh heartbeat, new release SHA | Accept normal exit if next minute runs; repair only persistent gaps |
| Duplicate worker/poller | Duplicate hPanel/legacy cron or manual daemon | Job inventory/process list; repeated role lock skips; bot cache lock | Remove duplicate scheduling after inventory; use one role lock; do not delete active lock file/offset |

If heartbeat moves but tick does not, first investigate `proc_open` preflight and task exceptions: the heartbeat callback can succeed while a subprocess fails. If a schedule mutex remains after a killed process, confirm **no matching task is running** and inspect TTL before using the existing `schedule:clear-cache`; that command affects schedule mutexes, not an OS worker lock. Never use cache clearing as routine cron recovery.

## 31. Acceptance Test Plan — A through N

All following actions are **for implementation**, not executed in this report-only pass. Capture UTC observation times, relevant business timezone/date, exact release SHA, log excerpts and IDs. Synthetic fixtures must be labeled TEST/Operations QA and contain no real name/contact/payment/form data. Use the already approved bot/destination. Do not include real customer lists in screenshots handed back. Existing normal reminder rules may naturally process real bookings when automation is enabled; do not manufacture or manually replay customer reminders for QA.

### A. Scheduler heartbeat

Setup: wrapper and H1 installed with subprocess preflight passing. Action: run the read-only section 5 query, let two real cron minutes pass, run again. Expect scheduler **and tick** advance, H1 complete. Cleanup: none. PASS: freshness advances without manual `schedule:run`. This proves minute execution, not every daily task.

### B. Recurring queue worker

Setup: H4 installed; record pending/failed counts with section 24 and heartbeat. Action: let two real minutes pass; inspect today's queue log and section 5 query. Expect H4 start/finish and fresh worker key even if jobs empty. Then execute section 9 once to prove enqueue/consume/receipt path. Cleanup: drain only the temporary QA queue, retain history. PASS: actual recurring H4 executions **plus** deterministic queued-test evidence. For deployment restart, run existing `queue:restart` once and verify fresh H4 starts within the next two cron minutes; no pending job is cleared.

### C. Telegram direct connectivity

Setup: existing enabled bot 1/destination 1. Action, once, outside rate contention:

```bash
cd /home/u494520852/domains/mohamedateff.com/arabictutor_app
/opt/alt/php84/usr/bin/php artisan telegram:test 1 --no-interaction
```

Expected labeled connectivity TEST, history sent, receipt, bot last_success advance. Cleanup: none; retain evidence. PASS: one provider-accepted labeled message. A deferred result must reach sent in history; do not rerun until its outcome is determined. C is explicitly **direct**, and B/section 9 establish the separate queued proof.

### D. Scheduled synthetic reminder

Setup: use admin **Bookings → Create** (`admin/bookings/create`) to create only a new synthetic contact booking: name `Operations QA`, email `operations-qa@example.invalid`, phone blank, notes `TEST operations reminder; cancel after acceptance`. Choose an unoccupied future slot at least 30 minutes away using an existing active diagnostic session type and its duration. Do not override calendar conflicts or create a paid package. Before creation confirm this QA email has no existing contact booking; the uniqueness check below aborts if ambiguous.

Mark only the new confirmed synthetic booking's source so a QA rule can filter it:

```bash
cd /home/u494520852/domains/mohamedateff.com/arabictutor_app
/opt/alt/php84/usr/bin/php artisan tinker --execute '$matches=App\Domains\Booking\Models\Booking::where("status","confirmed")->whereHas("contact",fn($q)=>$q->where("email","operations-qa@example.invalid")->where("name","Operations QA"))->get(); if ($matches->count()!==1) { throw new RuntimeException("Expected exactly one synthetic QA booking; stop without changing records"); } $b=$matches->first(); if (!$b->start_at_utc->isFuture() || $b->student_id!==null) { throw new RuntimeException("Unexpected QA fixture; stop"); } $b->update(["source"=>"operations-qa"]); dump(["qa_booking_id"=>$b->id,"start_utc"=>$b->start_at_utc->toIso8601String()]);' --no-interaction
```

In Telegram Bots → Alert rules create new `TEST operations reminder`, trigger session_reminder, enabled, bot 1/destination 1, instant, critical, minutes **60**, threshold 1/window 60/cooldown 60/send time 08:00/no quiet hours, condition **source = operations-qa**, template **`TEST scheduled reminder. Synthetic booking {booking_id}; tutor {tutor_time}.`** No customer wildcard filter or personal-data placeholders. The synthetic booking is inside the 60-minute lead window, so next real H1/H4 should enqueue and send it. Do not manually run tick.

Expected: delivery for captured QA booking/rule ID, one receipt, no repeat on a second tick. Current 1440/210 production rules may also alert for this new synthetic booking; their texts contain only the synthetic identity. Cleanup: disable the **new QA rule only**, cancel the QA booking through its admin cancellation action, retain history. No credits/payment exist to refund. PASS: scheduled QA rule specifically sent once through actual H1/H4, followed by safe cancellation. If no free near-future slot exists, create it in a later available hour and wait for its lead window; do not borrow a customer's slot or alter availability.

### E. Inbound `/today`

Setup: H3 plus global/bot command gates and authorized private sender as section 11; protect the destination's personal output. Action: authorized administrator sends `/today` to the existing bot once. Expect one reply consistent with confirmed business-day sessions and stored update_offset advancement. Capture only a redacted count/date, not customer names. Cleanup: none. PASS: authorized reply arrives via actual minute poll; an unauthorized sender receives none (test unauthorized identity only in isolated mocked tests).

### F. `/stats 7d`

Setup: same authorization. Action: send `/stats 7d`, then `/stats 30d` once if validating both ranges. Expect business-local range, canonical report totals, coverage caveats, one recorded reply per update. Compare identical date scope in admin reports; differing count definitions must be labeled, not forced equal. Cleanup: none. PASS: range and metric definitions agree with canonical reports; no unsupported `/stats7d` alias assumed. Digests/report counts may include synthetic operational fixture metadata; label that known QA contribution, do not delete historical analytics to hide it.

### G. One scheduled digest

Setup: create **new QA** business_digest rule, enabled, scheduled, critical, bot 1/destination 1, send_time **current Business Timezone HH:mm plus two minutes**, sections **system only**, no conditions/quiet hours, template `TEST scheduled operational digest {date}\n{digest}`. This reads operational state, not students. Use common required numeric fields as in section 13. Schedule earlier in the same day; do not set a past time unintentionally because tick catches up within today's date.

Action: wait through send_time and two real H1/H4 cycles. Expected one sent delivery for QA rule/business-day dedupe identity, no second delivery on another tick. Cleanup: disable new QA rule after receipt; retain history. PASS: actual scheduler emission and queued delivery, not admin on-demand Run. If later enabling permanent business/analytics digests, that is a separate explicitly selected business rule, not part of silently creating all 20 types.

### H. Independent scheduler watchdog

Setup/action: execute section 32's bounded lock drill using QA health rules. Expected: scheduler key stops briefly, watchdog key advances, TEST scheduler alert sent despite no H1. Cleanup: locks auto-release; disable QA rules only. PASS: H2's direct delivery receipt and later H1 recovery. Do not change/falsify heartbeat timestamps.

### I. Queue health alert

Setup/action: same drill holds H4's lock too. Expected worker cache heartbeat becomes older than the QA one-minute rule, persisted last_queue_seen is available from pre-drill H2 observations, TEST queue alert sent by H2 while H4 cannot run. No customer job is deleted/retried. Cleanup: automatic OS release and worker heartbeat recovery. PASS: history shows H2 sent with both locks held, proving queue-independent fallback; normal permanent 15-minute rules remain unchanged.

### J. Daily analytics

Setup: preserve backup and retention values; capture business yesterday using the code below. Action: first do one **non-pruning, explicitly dated** manual smoke test, then wait for real 00:05 Business Timezone H1 to establish scheduled evidence:

```bash
cd /home/u494520852/domains/mohamedateff.com/arabictutor_app
ROLLUP_DATE=$(/opt/alt/php84/usr/bin/php artisan tinker --execute 'echo now(app(App\Domains\Timezone\Services\TimezoneService::class)->getBusinessTimezone())->subDay()->toDateString();' --no-interaction)
case "$ROLLUP_DATE" in ????-??-??) ;; *) printf 'Unexpected date output; stop without aggregation.\n'; exit 64 ;; esac
/opt/alt/php84/usr/bin/php artisan analytics:aggregate-daily --date="$ROLLUP_DATE" --no-interaction
/opt/alt/php84/usr/bin/php artisan tinker --execute '$tz=app(App\Domains\Timezone\Services\TimezoneService::class)->getBusinessTimezone(); $date=now($tz)->subDay()->toDateString(); dump(["date"=>$date,"timezone"=>$tz,"rows"=>Illuminate\Support\Facades\DB::table("daily_metrics")->where("metric_date",$date)->where("reporting_timezone",$tz)->count(),"scheduled_at"=>App\Domains\CMS\Models\Setting::get("last_analytics_aggregation_at")]);' --no-interaction
```

Expected transaction replaces yesterday/current-timezone rows and country step completes; scheduled success key advances only after the real callback. Cleanup: none, do not delete valid rollups. PASS: manual smoke success **plus later scheduled timestamp and correct completed-date rows**. Do not run `--prune`, a bare date-less daily command, or backfill for QA; note scheduled production retention separately.

### K. Country rollup

Setup/action: follow J and inspect matching country rows after actual 00:02/00:05 sequence:

```bash
cd /home/u494520852/domains/mohamedateff.com/arabictutor_app
/opt/alt/php84/usr/bin/php artisan tinker --execute '$tz=app(App\Domains\Timezone\Services\TimezoneService::class)->getBusinessTimezone(); $date=now($tz)->subDay()->toDateString(); dump(["date"=>$date,"timezone"=>$tz,"countries"=>Illuminate\Support\Facades\DB::table("daily_country_metrics")->where("metric_date",$date)->where("reporting_timezone",$tz)->get(["country_code","unique_visitors","sessions","page_views","bookings_completed"])->toArray(),"scheduled_at"=>App\Domains\CMS\Models\Setting::get("last_country_analytics_aggregation_at")]);' --no-interaction
```

Expected correct separate country dimensions and unresolved bucket, with scheduled timestamp. Zero rows can be valid for a truly empty eligible day: verify retained eligible input count before labeling failure. Cleanup: none. PASS: canonical comparison with same date/timezone and no made-up country mapping. Manual equivalent `analytics:aggregate-daily-country` exists, but manual success alone does not prove the cron registration.

### L. Backup

Setup: offsite configuration failure resolved from actual provider cause; private permissions applied. Action: section 23's **non-cleaning** backup/hash sequence, then verify the next scheduled 02:00 UTC archive and state. Expected manifest/database/file hashes, offsite stream matches, new archive filename/time. Cleanup: retain the QA archive until normal retention; never restore production. PASS: local **and offsite** verified and scheduled execution evidenced; document unresolved offsite failure as FAIL, not green.

### M. Hold cleanup

Setup: in a browser with synthetic QA details, select an unoccupied future slot via normal booking wizard; collect no real contact and do not confirm. Capture only hold ID and expiry internally. Action: let 10-minute TTL expire, then one scheduled five-minute cleanup cycle. Inspect:

```bash
cd /home/u494520852/domains/mohamedateff.com/arabictutor_app
/opt/alt/php84/usr/bin/php artisan tinker --execute 'dump(["cleanup_at"=>App\Domains\CMS\Models\Setting::get("last_holds_cleanup_at"),"active_expired_count"=>Illuminate\Support\Facades\DB::table("booking_holds")->where("status","active")->where("expires_at","<=",now("UTC"))->count()]);' --no-interaction
```

Expected captured QA hold expired, slot naturally selectable, cleanup key advances. Never dump all lead_details or mutate real holds. Cleanup: normal expiry only. PASS: observed QA hold transition and true scheduled success key; a manual cleanup alone is insufficient. Abandoned-lead messaging additionally needs an enabled rule and valid collected synthetic email; it is not implied by a hold with no contact.

### N. Maintenance reminder, safe equivalent

Setup: production stays live. Prove actual threshold/transition/cooldown using existing test `test_maintenance_transition_and_duration_alert_use_one_incident_and_cooldown` in `tests/Feature/TelegramAutomationTest.php` on an **isolated test database with HTTP fakes**; it advances time 121 minutes and validates one incident and no duplicate. The inspected local `phpunit.xml` selects MariaDB `127.0.0.1:3307`, database `bolt_landing_test`, array cache/mail/session, testing environment; verify no process environment or cached configuration overrides this before running. Never run RefreshDatabase tests against production credentials. On the existing local Windows checkout only, run:

```powershell
Set-Location -LiteralPath 'C:\Users\e\Herd\ArabicwAbdallah'
& 'C:/Users/e/.config/herd/bin/php84/php.exe' artisan test --compact --filter=test_maintenance_transition_and_duration_alert_use_one_incident_and_cooldown
```

This command is for later developer verification; no test was run during this report-only pass. The test's own HTTP fakes prevent actual Telegram delivery, and its database fixtures are synthetic. Test-only sync does not change production's database queue.

For production transport equivalent create a new enabled QA maintenance_duration rule, instant/critical, destination 1, no conditions, template **`TEST maintenance-reminder transport equivalent. Production maintenance was not changed.`** Record its assigned rule ID privately, then invoke only that rule with synthetic payload using interactive ID input:

```bash
cd /home/u494520852/domains/mohamedateff.com/arabictutor_app
read -r -p 'New QA maintenance-duration rule ID: ' QA_RULE_ID
case "$QA_RULE_ID" in ''|*[!0-9]*) exit 64 ;; esac
export QA_RULE_ID
/opt/alt/php84/usr/bin/php artisan tinker --execute '$r=App\Domains\Notifications\Models\TelegramRule::findOrFail((int)getenv("QA_RULE_ID")); if ($r->trigger!=="maintenance_duration" || !str_starts_with($r->name,"TEST ")) { throw new RuntimeException("Not a QA maintenance rule"); } app(App\Domains\Notifications\Services\TelegramAutomationService::class)->emit("maintenance_duration","operations-qa:".Illuminate\Support\Str::uuid(),["enabled_at"=>"TEST only","duration_minutes"=>120],null,$r->id);' --no-interaction
```

Action: let H4 deliver. Expected labeled message/receipt and maintenance setting still false. Cleanup: disable only new QA rule and unset shell QA_RULE_ID. PASS: isolated duration/cooldown test passes, production queued transport works, production maintenance stays off. This equivalent does **not** claim a live production maintenance-duration scan was exercised. Exact future QA rule IDs are runtime-created values, not missing discoverable production facts; capture them rather than hard-code an invented number.

## 32. Safe Watchdog Failure Drill

Do this only after A/B show fresh heartbeats, H2 has persisted last_queue_seen, and pending default jobs are drained. Check admin calendar privately: no customer lesson/reminder deadline within the next 30 minutes and no daily/monthly scheduled task due during the drill. If either exists, select a later quiet window from the calendar; do not stop customer flows or rewrite the schedule.

Create two **new QA** health rules, names `TEST scheduler independence` and `TEST queue independence`, triggers scheduler_stale and queue_stale respectively, instant/critical, bot 1/destination 1, minutes **1**, cooldown **60**, no quiet hours/conditions, templates containing only `TEST`, last_heartbeat and threshold_minutes. Do not alter permanent health rules or their 15-minute threshold. Ensure scheduler/worker timestamps are fresh before enabling QA rules; otherwise an immediate alert would not establish drill causality.

Hold only the H1/H4 advisory lock files for **150 seconds**, bounded by an outer timeout. H2/H3 remain independent, the website stays online, no cron entry or heartbeat is edited, and both locks release automatically on process exit. Run:

```bash
/usr/bin/timeout --signal=TERM --kill-after=5s 155s /usr/bin/flock -n /home/u494520852/operations/locks/scheduler.lock /usr/bin/flock -n /home/u494520852/operations/locks/queue.lock /usr/bin/sleep 150
```

If lock acquisition fails because a real task is running, stop the drill and choose the next quiet window; never kill a backup/worker to acquire it. While the locks are held, a second SSH session observes section 5 keys and Telegram Delivery History. H1/H4 logs should record `lock_busy`; H2 starts and timestamp advances. By later minute cycles, QA rules detect age >1 minute and H2 directly sends both alerts even though H4 is blocked. Capture UTC timestamps and receipt IDs; do not use payload screenshots.

After automatic release, observe H1/tick/H4 recover within two ordinary cron cycles, then disable only the two QA rules. Preserve history/cooldown evidence. If no alert appears, inspect rule gates/due times/history/network and H2 logs; do not prolong the lock interval or change normal thresholds. This drill intentionally delays background processing by under three minutes in a quiet window; it does not interrupt PHP web requests or change booking availability/maintenance. Complete H and I evidence together, with production steady-state rules unchanged.

## 33. Operational Security

- Bot token is encrypted at rest in Telegram bot storage and decrypted internally for API access; no cron argument needs it. Telegram's API necessarily uses its token in the HTTPS path internally, but no public application URL or copied command should expose it. Never enable HTTP wire logging for acceptance.
- Encrypted delivery payloads are hidden in history; ID-only Telegram jobs avoid serialized bot credentials. Queued resource PIN mail does serialize its PIN; protect jobs/failed_jobs, logs, database exports, and backups accordingly. APP_KEY is needed to decrypt production ciphertext; never regenerate it.
- `.env` 640, existing logs/old ZIPs 644 require the permission corrections in section 28; do not assert current filesystem privacy based solely on nonpublic location. New operations directories/files use 700/600 via owner/umask.
- Authorized chat and sender IDs are separate checks; current allowed-user list is empty. Enabling global commands is insufficient without bot command allowlist and sender authorization. Limit personal-detail destinations to the existing trusted private chat. Group destinations should use summary detail unless explicitly approved.
- No webhook secret exists because inbound is polling. Do not exempt new routes from CSRF or invent a webhook endpoint to address polling problems.
- Provider/network exception text, backup errors and mail-log bodies can expose URLs or personal content despite safe Telegram failure categories. Review logs privately, redact before sharing, and do not claim every third-party exception is automatically sanitized.
- hPanel sees only the private wrapper path/role; it must not store tokens/passwords in commands. Backup objects must be private; verify provider ACL and hash without publishing the bucket/object URL.
- Keep operational wrapper/locks/logs outside public_html. Do not upload this report with private infrastructure details to the public site's resource library.

## 34. Resource Requirements

Required host entries: **4**; intended concurrently executing normal queue workers: **1**. Scheduler, watchdog and poller are independent PHP processes with independent locks; they can run concurrently with H4 and website requests. Current queue was empty and only one bot/two reminder rules were present, but no sustained workload/throughput measurement exists. Do not claim a numeric daily message volume or capacity guarantee.

H1 can be longest-running because rollups scan raw data and backup creates SQL/ZIP plus offsite upload. Worker starts each minute and exits when empty; recycle threshold 256 MB, 30-second job timeout, 55-second outer bound, reservation 90 seconds. Current PHP memory ceiling 1536M is not a promise that the hosting plan permits four simultaneous processes at that usage. hPanel Resource Usage and plan limits must supply real CPU, memory, process/cron quotas; record peak duration/RSS, pending oldest age, lock skips and rate delays during acceptance. Increase capacity only against measured pressure, not by changing queue to sync or removing locks.

Database queue/cache locking adds contention on jobs/cache/cache_locks and rule/delivery rows. Tick may dispatch duplicate ID jobs for due pending deliveries; service claim guards provider sends, but excess backlog still consumes database/worker capacity. Monitor sustained oldest due job growth. Long scheduler tasks can suppress later minute ticks under the outer lock and should be investigated, not silently allowed to run alongside another H1. Full-host outage requires monitoring outside this host; no such monitor was discovered or configured in this report.

## 35. What Must Not Be Changed

Host-level setup must preserve database queue, reservation/timeout ordering, booking calendar/row locks, idempotency, ledger append-only behavior, existing Business Timezone rules, analytics exclusions/country snapshots, current 1440/210 reminder milestones, enabled business rules, bot tokens, meeting-link gates/room architecture, and customer/student/payment/refund data.

Do not switch queue to sync; start uncontrolled parallel workers; backfill/rebuild historical funnels; run pruning to prove tests; delete queue/history/locks to make a dashboard look green; fake heartbeats; place watchdog only inside the scheduler; create a webhook for a polling bot; replace meeting access links; or rewrite scheduling without an evidenced defect. The two actual timezone/retention caveats in sections 16–17 are reported for separately reviewed correction, not silently fixed in this pass. New operational health rules and clearly isolated temporary TEST rules are explicit setup/acceptance artifacts, not changes to existing business alert policy.

## 36. Developer Execution Checklist

- [ ] Production and GitHub SHA verified against the reported baseline; record any later release.
- [ ] Correct deployment account/application/public-wrapper/PHP paths verified.
- [ ] Applied migration status and database jobs/failed_jobs/cache/cache_locks confirmed.
- [ ] hPanel inventory/resource quotas recorded; duplicate/legacy recurring equivalents identified.
- [ ] Scoped scheduler subprocess preflight passes; other disabled PHP functions preserved.
- [ ] Private wrapper created outside public_html with syntax check, role locks and logging/rotation.
- [ ] H1 installed; scheduler **and tick** timestamps advance through real cron cycles.
- [ ] H4 installed; idle heartbeat and deterministic queued-test receipts verified.
- [ ] H2 independently installed; watchdog timestamp and configured permanent health rules verified.
- [ ] Safe bounded scheduler+queue lock drill passes; both heartbeats recover; QA health rules disabled.
- [ ] H3 installed; POLLING mode retained; existing global/bot/command/sender gates configured.
- [ ] `/today` and `/stats 7d` reply/offset verified; private customer output redacted.
- [ ] Synthetic scheduled reminder sent once; QA booking cancelled and temporary rule disabled.
- [ ] Scheduled system-only QA digest sent once; temporary rule disabled.
- [ ] Daily/country completed-date rows and actual overnight scheduled timestamps verified.
- [ ] Retention boundaries/caveats acknowledged; no QA prune/backfill/funnel rebuild performed.
- [ ] Hold naturally expires and scheduled cleanup timestamp advances.
- [ ] Safe maintenance equivalent/isolated duration test passes; production stays live.
- [ ] GeoIP metadata/default updater verified and monthly schedule recorded.
- [ ] Local archive manifest verified; actual offsite upload/read hash succeeds; scheduled backup observed.
- [ ] Failed jobs reviewed individually; no queue flush/clear or uncertain automatic resend.
- [ ] Existing log/backup/.env permission gaps corrected without breaking web/runtime access.
- [ ] Private application and operations log rotation verified.
- [ ] System Health scheduler/queue fresh; offsite failure and mail-log warning resolved or explicitly reported as remaining failures.
- [ ] Evidence distinguishes manual smoke checks from actual host-scheduled execution.
- [ ] Handoff report and redacted proof returned; application business rules/customer/billing data preserved.

# Copy/Paste Message for Developer

The application is deployed at SHA `5aaa22ab85ace375f3effd81924d635f7f7bc214` in `/home/u494520852/domains/mohamedateff.com/arabictutor_app`, as `u494520852`, PHP `/opt/alt/php84/usr/bin/php`. Background automation is not verified operational. Install the private `/home/u494520852/operations/run.sh` exactly from section 4, including the scoped `proc_open` workaround, independent flock locks, timeout bounds, and private rotated role logs. Pass the harmless subprocess preflight first. Inspect hPanel's existing jobs and remove duplicate equivalents before adding these **four separate Custom entries**, each with all five cadence selectors `*`:

```text
/usr/bin/bash /home/u494520852/operations/run.sh scheduler
/usr/bin/bash /home/u494520852/operations/run.sh watchdog
/usr/bin/bash /home/u494520852/operations/run.sh poll
/usr/bin/bash /home/u494520852/operations/run.sh queue
```

Keep `QUEUE_CONNECTION=database`, queue default, retry_after 90; bounded worker flags are in the wrapper. H2 must stay independent and uses direct health delivery even when H1/H4 are stopped. Configure the two operational health rules from section 6 on existing bot/destination 1. Inbound is **POLLING**: enable existing global/bot command gates, select today/tomorrow/student/stats and explicitly authorize the trusted existing private sender; preserve token and offset, do not add a webhook. Existing 1440/210 reminder rules must remain unchanged. No extra host cron is needed for analytics, backup, hold cleanup, GeoIP or individual Telegram business rules.

Execute acceptance A–N and the bounded lock drill exactly as documented. Send back hPanel four-entry evidence, release/PHP versions, moving scheduler/tick/watchdog/worker timestamps across real cycles, labeled queued-test delivery ID/receipt, scheduled synthetic reminder/digest receipts, authorized command offset proof, independent watchdog receipts while both locks are held plus recovery, completed-date timezone-scoped daily/country rows and actual overnight timestamps, scheduled hold cleanup, GeoIP metadata, and local/offsite backup hash proof. Resolve the current S3 replication failure from actual provider configuration; the present production mail-log driver remains an external-email warning until real mail transport is verified. Correct private log/backup permissions and rotation, record measured host quotas/durations, and report remaining failures honestly rather than claiming all-green from a homepage or empty queue.

Do not change booking locks, timezone rules, ledgers, payments/refunds, customer records, tokens, meeting-link architecture, existing business alert rules, or maintenance state. Do not backfill, prune for QA, clear queues/history, reset uncertain deliveries, or use sync. Temporary labeled QA artifacts must use synthetic data, be disabled/cancelled after proof, and retain audit history. This handoff pass is report-only; it has not installed these jobs or deployed further code.
