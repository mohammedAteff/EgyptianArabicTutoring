# Discovery

Third implementation pass, 2026-10-02 (Africa/Cairo). Baseline: `636b2511c7f32175d2c87f31213cf1796f93a8f5`, existing Laravel 13.32 / PHP 8.4 / Livewire 4.4 / Tailwind 4 application. Existing reports and V5 specification were read as background; the operational repair request controlled this pass. Existing domain services, database queue, admin authentication, public layouts, reporting and booking locks were retained. No dependencies or historical analytics/ledger records were changed. Laravel Boost tools and `.ai/rules` were unavailable in this session; existing source, tests and installed package versions were inspected directly.

# WhatsApp Root Cause

The main Save as Draft / Publish All Settings form ended before the WhatsApp controls. Those controls belonged to a second operational form with a different endpoint and button. Main Publish could report success while never submitting the edited WhatsApp values. This was reproduced in the actual administrator browser: reload showed an empty destination and a disabled public switch after Publish.

Sections now belong to one form and one validated, atomic settings transaction. Both main buttons persist operational controls. Draft still keeps homepage/About copy in preview, while operational settings apply immediately. A destination is required when either public or portal visibility is enabled. Separate booleans control the public layout and student layout. Footer Social Links remain independent. The fixed circular button uses the existing first-party click tracker and its own `floating_cta` placement.

# Maintenance Root Cause

Two separate save scopes made the bottom operational Save omit the maintenance selector above it. An authenticated administrator is deliberately exempt from public maintenance middleware; checking the website in that same browser session also appears Live even when maintenance is saved correctly. The middleware itself already returned anonymous 503 correctly.

The unified form now saves the selector and maintenance message together. Cache invalidation also runs after transaction commit, preventing another request from repopulating the cache with the previous flag before commit. Browser verification used fresh anonymous cookies, not an administrator session, and confirmed Maintenance -> 503 -> Live -> 200. Administrator access, admin sign-in and health stay available; the maintenance response hides the floating contact button.

# Social Analytics

All five configured footer platforms have authoritative `data-social-platform` metadata from the existing SocialLink records: YouTube, TikTok, Instagram, Telegram and WhatsApp. A single initialized listener tracks one intended event per physical click. Existing generic outbound tracking skips these links and the floating contact button to avoid a second conversion.

Social & Messaging reports and CSV/XLSX exports include platform, placement, event, originating page, Cairo date, clicks, unique visitors, language, country and source/medium/campaign. `footer_social` and `floating_cta` are separate. Historical generic outbound events remain generic; no old events were relabeled. Country unknown values retain the existing analytics definition.

Actual browser clicks produced one analytics request for each of the five footer links. The real admin report then showed all five footer platforms, each with one click and one unique visitor, plus the earlier floating WhatsApp click in a separate row.

# Existing Telegram Architecture

Previously one encrypted Settings token, a list of chat IDs and configurable countdown windows fed a synchronous reminder service and `booking:send-telegram-reminders`. Accepted reminders were deduplicated using booking-event idempotency keys. The public Telegram social link was separate.

A locked, transactional, idempotent import preserves the existing encrypted token, recipients, enabled state, countdown windows and accepted-reminder keys. Previously trusted reminder recipients retain personal detail to preserve existing behavior; new destinations default to limited detail. Existing Settings credentials stay encrypted and available for recovery, while the old active settings UI is replaced by the dedicated center. After import, the old command forwards to `telegram:tick` instead of sending a second copy. Recurring dispatch now needs the existing database queue worker.

# Telegram Automation Architecture

The admin `Telegram Bots` center has Overview, Bots, Destinations, Alert rules, Commands and Delivery history. Administrators and super administrators can manage it; assistant/viewer roles are denied. Tokens are encrypted and never returned to the page, audit payload or flashed validation input. Leaving a replacement token blank preserves it.

The architecture supports multiple bots, multiple private/group/channel destinations per bot and multiple rules per bot, with no hard-coded numerical product cap. Destination chat IDs are unique within a bot. Rules choose destinations only from their own bot. Each bot, destination, rule and the global subsystem has a switch. Command access is separately gated globally, per bot, per command and by both chat ID and Telegram user ID.

Rules choose trigger, instant/delayed or scheduled/on-demand mode where applicable, priority, timing/threshold/window, persistent entity cooldown, quiet hours, named student/lesson filters, country/source filters, digest sections and an editable template. Unmatched conditions prevent delivery. Normal/low priority wait until quiet hours end; critical bypasses quiet hours. Preview uses fictional sample data and sends nothing. Plain-text placeholders are allow-listed per trigger; no executable template expressions or Markdown parsing. Messages split at 3,500 Unicode characters and resume from the next accepted part.

Committed business events enqueue encrypted delivery payloads after commit. Business transactions survive Telegram failure. Jobs contain only the delivery ID. A unique delivery key, transactional rule lock and atomic pending-to-sending claim protect deduplication across processes. Bot/chat rate limits, bounded HTTP timeouts and at most four retry attempts on rejected 429/5xx responses apply. Failed and uncertain outcomes have safe codes. A network outcome that might have been accepted is marked uncertain and is not automatically resent. The monitor marks abandoned sending claims uncertain after five minutes. Reminder delivery rechecks current booking status/start time; completed required forms cancel stale missing-form notifications.

# Implemented Alert Types

20 configurable alert types:

| Domain | Types and authoritative source |
| --- | --- |
| Bookings | Created, rescheduled, cancelled: committed booking events; diagnostic/package context, ledger-derived credits and session position, tutor/student schedule and private lesson URL. |
| Students | Low credits (default <=1), unused active package approaching expiry (default 14 days; add a second 7-day rule): StudentLedgerService and package expiry. Expired/inactive packages are excluded. |
| Forms | Submitted (no answer bodies), required form missing before a confirmed lesson (default 720 minutes): existing assignment/published-version/submission services. |
| Reminders | Lesson countdown with arbitrary lead-minute rules; confirmed future lessons only; imported windows preserved. |
| Resources | Access requested, material downloaded: real resource request/download records, existing visitor country, source when recorded. Unknown geography remains unknown. |
| Abandonment | Expired owned booking hold only after valid contact details were collected; converted/released holds excluded. Lead snapshot encrypted and cleared on release/conversion. No early Contact record is invented. |
| Analytics | Traffic spike (default 50 eligible unique visitors in 60 minutes): existing active-visitor analytics service and persistent cooldown. |
| Health | Scheduler stale and queue stale (default 15 minutes), separate signals and independent watchdog command. |
| Backup | Local backup success/failure and offsite replication failure: actual private archive basename/byte size and this attempt's replication outcome; no public download URL. |
| Maintenance | Enabled transition and duration exceeded (default 120 minutes): saved setting transition and persisted start time. |
| Digests | Business and analytics digests, scheduled daily or on demand; daily delivery identity avoids duplicates. |

Business digest distinguishes bookings created today from sessions occurring today/tomorrow. Sections cover lifecycle counts, low/expiring credits, resource requests/downloads, backup/maintenance/heartbeat and analytics. Analytics uses existing reporting services, labels period uniques versus fallback sum-of-daily-uniques explicitly, includes retained top content, social platforms, acquisition sources and top five acquisition countries. Historical/cutover comparability limitations are stated. `/stats 7d` and `/stats 30d` use the same services and Cairo period boundaries as reports.

# Scheduler / Watchdog Architecture

`telegram:tick` is scheduled every minute through the existing Laravel scheduler with overlap protection. `telegram:watchdog` is deliberately NOT scheduled inside that scheduler: its hosting cron independently checks the persisted scheduler heartbeat and existing queue-worker heartbeat, persists the last observed queue signal and can deliver due health alerts directly even if the queue is stopped. `telegram:poll` is another independently invoked, locked command with persistent per-bot update offset. No public webhook was introduced.

Same-host watchdog cannot detect the host's total outage; an external hosting monitor is needed for that. A manual command run is not evidence that recurring hosting cron works.

Production SSH is available, but the shared account has no `crontab` executable. No continuously running queue worker was found during inspection. The user explicitly deferred hPanel/cron setup until contacting the developer. Existing recurring-host configuration therefore remains unverified, and recurring reminders, digests, polling and watchdog execution are NOT claimed operational.

# Security

- Bot credentials, delivery text/source snapshots and hold lead details are encrypted using existing Laravel APP_KEY. Tokens/PII are hidden from model serialization, admin history and validation flash. No credentials are committed or exposed in this report.
- Telegram URLs use the fixed official API host; supplied templates cannot inject an API host or executable expression. API errors store only safe codes.
- Limited-detail destinations redact student names/contact details/student local time/meeting links. Personal-detail access is explicit; legacy trusted settings preserve their prior detail.
- CSRF and existing admin middleware protect mutations. Cross-bot destination assignment is rejected. Both chat and user allow-list must match for commands; an empty user list denies commands. `/student` requires a personal-detail destination and refuses ambiguous names. Unknown/disabled commands reveal no business data.
- Poll updates use a persisted offset and lock; duplicate update delivery identities cannot resend. API responses in automated tests are mocked and stray requests are forbidden.
- No synthetic student/customer data was sent to Telegram. Only the approved labeled connectivity TEST is permitted for real production verification. No WhatsApp message was sent.

# Database Changes

Two additive migrations: Telegram bots, destinations, rules, destination-rule pivot, rule states and encrypted delivery history; encrypted nullable lead-details column on booking holds. Foreign keys and scoped uniqueness protect destination and delivery identities. No existing database, ledger, answers or analytics history was rebuilt. Local migration and test-schema coverage completed; both production migrations completed successfully; deployment evidence appears below.

# Files Changed

- `app/Console/Commands/ImportTelegramLegacyCommand.php`
- `app/Console/Commands/TelegramPollCommand.php`
- `app/Console/Commands/TelegramTestCommand.php`
- `app/Console/Commands/TelegramTickCommand.php`
- `app/Console/Commands/TelegramWatchdogCommand.php`
- `app/Domains/Notifications/Models/TelegramBot.php`
- `app/Domains/Notifications/Models/TelegramDelivery.php`
- `app/Domains/Notifications/Models/TelegramDestination.php`
- `app/Domains/Notifications/Models/TelegramRule.php`
- `app/Domains/Notifications/Models/TelegramRuleState.php`
- `app/Domains/Notifications/Services/TelegramAutomationService.php`
- `app/Domains/Notifications/Services/TelegramBusinessEvents.php`
- `app/Domains/Notifications/Services/TelegramCommandService.php`
- `app/Domains/Notifications/Services/TelegramDeliveryService.php`
- `app/Domains/Notifications/Services/TelegramLegacyImporter.php`
- `app/Domains/Notifications/Services/TelegramReadService.php`
- `app/Domains/Notifications/Services/TelegramRuleCatalog.php`
- `app/Domains/Notifications/Services/TelegramTemplateService.php`
- `app/Http/Controllers/Admin/TelegramController.php`
- `app/Jobs/DeliverTelegramMessage.php`
- `database/factories/TelegramBotFactory.php`
- `database/factories/TelegramDestinationFactory.php`
- `database/factories/TelegramRuleFactory.php`
- `database/migrations/2026_10_02_040450_create_telegram_automation_tables.php`
- `database/migrations/2026_10_02_040451_add_encrypted_lead_details_to_booking_holds.php`
- `resources/views/admin/telegram/destination-fields.blade.php`
- `resources/views/admin/telegram/index.blade.php`
- `tests/Feature/OperationalSettingsWorkflowTest.php`
- `tests/Feature/TelegramAutomationTest.php`
- `app/Console/Commands/SendBookingRemindersCommand.php`
- `app/Domains/Analytics/Services/AnalyticsService.php`
- `app/Domains/Booking/Models/Booking.php`
- `app/Domains/Booking/Models/BookingEvent.php`
- `app/Domains/Booking/Models/BookingHold.php`
- `app/Domains/Booking/Services/BookingHoldService.php`
- `app/Domains/CMS/Models/Setting.php`
- `app/Domains/Forms/Models/FormSubmission.php`
- `app/Domains/Forms/Models/FormVersion.php`
- `app/Domains/Notifications/Services/TelegramNotificationService.php`
- `app/Domains/Reporting/Services/ReportService.php`
- `app/Domains/Resources/Models/ResourceDownload.php`
- `app/Domains/Resources/Models/ResourceRequest.php`
- `app/Domains/Students/Models/SessionLedgerEntry.php`
- `app/Domains/Students/Models/StudentPackage.php`
- `app/Domains/System/Services/BackupService.php`
- `app/Http/Controllers/Admin/ReportController.php`
- `app/Http/Controllers/Admin/SettingController.php`
- `app/Livewire/BookingWizard.php`
- `app/Providers/AppServiceProvider.php`
- `bootstrap/app.php`
- `resources/js/analytics-telemetry.js`
- `resources/views/admin/reports/index.blade.php`
- `resources/views/admin/settings/index.blade.php`
- `resources/views/layouts/admin.blade.php`
- `resources/views/layouts/public.blade.php`
- `routes/console.php`
- `routes/web.php`
- `tests/Feature/Concurrency/booking_worker.php`
- `tests/Feature/MariaDbConcurrencyVerificationTest.php`
- `tests/Feature/TelegramMultiRecipientReminderTest.php`
- `tests/telemetry.test.mjs`

# Automated Tests

| Check | Result |
| --- | --- |
| Full current-schema suite, `php artisan test --compact --exclude-group=intermediate-schema` | 641 passed; 6,090 assertions; 193.367 s. |
| Dedicated real MariaDB/InnoDB concurrency suite using `phpunit.concurrency.xml` | 11 passed; 53 assertions; 8.049 s. New two-process Telegram emission and delivery race produced one row, one attempt and one accepted mocked message. |
| Focused operational settings / Telegram automation / legacy reminders | 60 passed; 249 assertions, plus 3 backup-outcome cases passed with 23 assertions. |
| JS telemetry and existing form builder | 5 passed, 0 failed. |
| Vite production build and Blade cache | Passed. |
| Pint dirty PHP format, diff whitespace, UTF-8 checks, PHP lint | Passed; 53 changed/new PHP files linted, 61 changed/new files UTF-8 checked. |
| PHPStan level 5, explicit 1 GB memory | 307 diagnostics versus 358 baseline; no new file/message diagnostics; no suppressions added. Existing analysis debt remains. |

The historical `intermediate-schema` group is a separate stage-only suite which expects the removed forms.prompt_trigger column. A broad initial invocation included it against the current schema and got its two expected schema assertions; the established current-schema command excludes that group, as in both previous reports. No assertions were weakened or removed. New migrations do not change the historical forms migration stage.

Coverage includes encrypted/masked credentials, roles, legacy import/dedupe, cross-bot validation, templates, sample preview, toggles, source conditions, privacy, post-commit/rollback lifecycle events, canonical ledgers, quiet hours/cooldowns, cancelled/rescheduled queued reminders, forms, expired-versus-converted holds, resources/unknown country, traffic thresholds, independent watchdog, daily/on-demand digests, strict command allow-lists/ambiguity/offset, Unicode splitting/resume, actual private backup archive sizes/current offsite success-failure-unconfigured outcomes, rejected/uncertain API responses and bounded retries. All automated Telegram calls use Http fakes.

# Browser Verification

Actual local Herd browser, existing QA admin and synthetic local student; temporary operational changes restored afterward. Local QA bot/destination/rule remain disabled and their token is a non-working placeholder. They are database fixtures, never transferred to production.

- Main Publish reproduced the old lost WhatsApp settings; unified Publish then persisted URL/public visibility/English label/message after reload.
- Fresh anonymous desktop and 390x844 mobile homepage displayed the circular fixed button; scrolling kept its position. The button linked to the saved WhatsApp destination/text, with one analytics request. Footer destinations were unchanged.
- Save as Draft saved portal-only visibility. Fresh public homepage had no floating button; student sign-in and authenticated synthetic portal did. Both switches off removed it from public and portal pages.
- Main administrator maintenance selection and custom message persisted. Fresh anonymous home, pricing, resources, booking and student login returned 503; admin login and `/up` returned 200. Maintenance page had no floating WhatsApp. Restored Live homepage returned HTTP 200.
- Each of five footer links was physically clicked once: one analytics request each. Admin Social & Messaging report showed all five footer rows plus separate floating WhatsApp, counts, placement, language/country and source fields.
- Dedicated Telegram UI added/reloaded a disabled bot, destination and countdown rule; sample preview rendered fictional data without a send. Reload preserved token masking, destination details, rule values and disabled state. Preview also preserves an unchecked rule switch.
- Synthetic student verification was temporarily enabled for sign-in and restored to legacy_unverified. Synthetic portal was signed out and administrator cookies restored. Maintenance/message/WhatsApp QA values were restored.

Evidence images: `C:/Users/e/AppData/Local/Temp/awa-telegram-whatsapp-mobile.png`, `awa-telegram-maintenance.png`, `awa-telegram-social-browser.png`. Telegram center evidence: `C:/Users/e/AppData/Local/Temp/awa-telegram-center-browser.png`. Production maintenance evidence: `C:/Users/e/AppData/Local/Temp/awa-telegram-production-maintenance.png`.

# Deployment Requirements

The user authorized GitHub push and Hostinger website update. Hostinger application: `/home/u494520852/domains/mohamedateff.com/arabictutor_app`, public wrapper: `/home/u494520852/domains/mohamedateff.com/public_html/arabictutor`. Existing environment, private assets, database queue and public footer configuration must be retained. Do not copy the local QA database to production.

Before source replacement a private source/environment/wrapper snapshot and database/storage ZIP were created: `/home/u494520852/deployment-backups/20261002-telegram-636b251`; archive `backup-full-2026-10-02-080133.zip`. Preserve these for rollback; the new migration tables contain encrypted configuration/history and must not be dropped casually.

Deploy source, production Vite build to app/public/build and wrapper/build, run `php artisan migrate --force`, `telegram:import-legacy`, refresh config/routes/views and `queue:restart`. Preserve APP_KEY; changing it makes encrypted bot tokens and delivery history unreadable.

Developer must configure hosting-level jobs using the account's confirmed PHP CLI and a reliable process lock (flock is present). Examples for hPanel Custom commands:

```sh
# Every minute: existing scheduler
cd /home/u494520852/domains/mohamedateff.com/arabictutor_app && /usr/bin/flock -n storage/framework/scheduler-cron.lock /usr/bin/php artisan schedule:run >> storage/logs/scheduler-cron.log 2>&1

# Every minute: bounded shared-host queue worker; use managed continuous worker if the plan supports it
cd /home/u494520852/domains/mohamedateff.com/arabictutor_app && /usr/bin/flock -n storage/framework/queue-cron.lock /usr/bin/php artisan queue:work database --stop-when-empty --max-time=50 --timeout=30 --tries=1 >> storage/logs/queue-cron.log 2>&1

# Every five minutes: independent watchdog, outside schedule:run
cd /home/u494520852/domains/mohamedateff.com/arabictutor_app && /usr/bin/flock -n storage/framework/telegram-watchdog.lock /usr/bin/php artisan telegram:watchdog >> storage/logs/telegram-watchdog.log 2>&1

# Every minute, only after commands are enabled and explicit user/chat allow-lists are configured
cd /home/u494520852/domains/mohamedateff.com/arabictutor_app && /usr/bin/flock -n storage/framework/telegram-poll.lock /usr/bin/php artisan telegram:poll >> storage/logs/telegram-poll.log 2>&1
```

Watch fresh scheduler, tick, queue and watchdog heartbeats over repeated intervals; then prove a configured reminder and deliberately stale heartbeat recovery using approved QA data/destinations. Do not treat a one-off scheduler invocation as cron proof. Configure only wanted alert rules; import enables only the existing countdown rules. Commands remain off by default. Configure critical health rules for the independent watchdog. Never rely on duplicate Telegram message delivery as a retry signal after an uncertain network outcome.

References: [Laravel queue documentation](https://laravel.com/docs/13.x/queues), [Telegram Bot API](https://core.telegram.org/bots/api), [Hostinger cron setup](https://www.hostinger.com/support/1583465-how-to-set-up-a-cron-job-at-hostinger/).

Deployment completed on 2026-10-02. Implementation commit `ac07debe2e86fe9e272a43743675f1bd2a107228` was pushed to GitHub main and fast-forwarded on Hostinger. The built asset archive checksum matched before extraction; app/public/build and the public wrapper build were synchronized. Both migrations, locked legacy import, config/routes/views caching and queue restart signal completed successfully. No dependencies, local QA database or local fake bot were copied to production.

Production verification:

- `.env` SHA-256 matched the private pre-deployment snapshot. The combined existing operational settings, footer SocialLink records and encrypted legacy Telegram configuration fingerprint also matched exactly before and after deployment (`a71c404acf5db1849039a68125d2579e96835f686b899c10f372ae9df4f2011b`). Existing `.env.backup.before-7123f15` was preserved.
- Imported one enabled Existing Reminder Bot and one enabled private destination, with enabled countdown rules at 1,440 and 210 minutes. Global command access and per-bot commands remain disabled. New alert types are configurable, not silently enabled for customer data.
- The approved existing bot/destination received only one clearly labeled TEST with no student or customer data. `telegram:test 1` returned `sent`. Delivery #1 stored `trigger=test`, `attempts=1`, Telegram message ID `5`, encrypted payload and a successful bot health timestamp of 2026-10-02 11:13:11 Africa/Cairo. This proves Telegram API acceptance, not that a human read the message.
- The existing production setting is `maintenance_mode=1`; it was preserved. Fresh anonymous homepage, pricing, resources and booking returned 503 with the saved maintenance message. Admin login, `/up` and the new built JS asset returned 200. The actual production browser showed the configured maintenance page. To open the public website, the owner can select Live and Publish All Settings in the repaired dashboard; the local real browser already proved Live -> HTTP 200.
- The recorded production scheduler heartbeat remains 2026-10-01 17:08 Africa/Cairo. Recurring scheduler/queue/watchdog/polling operation remains unverified and deferred at the user's request. `queue:restart` sends a restart signal; it does not start a worker.

# Remaining Limitations

- Hosting cron/worker setup and recurring execution are deferred by the user; watchdog/polling/alerts need the documented jobs and configured rules. Entire-host outage detection requires an external monitor.
- PHPStan is improved but not clean (307 inherited diagnostics).
- Uncertain external API outcomes intentionally require operator review; no automatic resend can safely promise exactly-once delivery after a network timeout.
- Historical generic social events have no reliable platform/placement identity, and retained content/source reporting is limited by existing analytics retention and cutover comparability.
- No real student reminders or customer-data notifications were sent as QA; business alert paths are verified with mocked Telegram transport. A safe connectivity TEST is handled separately.
