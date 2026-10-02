# Brownfield remediation — discovery and verification

## Discovery

Read the current pasted request, V5 baseline, and all thirteen supplied screenshots. The current request supersedes the baseline's PIN/fail-open rules. No `.ai/rules` directory exists. Working tree was clean. Boost MCP is unavailable.

Runtime: Herd PHP 8.4.25; Laravel 13.32.0; Livewire 4.4.5; PHPUnit 12.5.35; Node 24.19.0; npm 11.17.0. Existing MariaDB 10.11.18 instance was stopped; restarted its existing configuration at 127.0.0.1:3307. Application `bolt_landing`, tests `bolt_landing_test`. All 55 migrations applied. Database cache/queue retained. Standard PHPUnit uses existing array cache/session and sync queue; concurrency uses database stores. No dependencies changed.

Routes: public localized booking/resource/game routes; student login/dashboard/forms/autosave/booking/reschedule routes; protected admin billing, forms, analytics and settings routes. Student auth is a separate guard, verified DOB plus two normalized identifiers with fingerprint-based throttling. StudentBookingService, SlotResolver, BookingHoldService, AvailabilityService and RescheduleService already handle slots, ownership, debits and history. Ledger terms are immutable; payments/refunds and credit entries are append-only and idempotent.

Middleware order: session → TrackVisitorSession → EnsureNotUnderMaintenance → SubstituteBindings. Role middleware protects mutations; assistant can view student/forms records. Visitor cookies `_va_visitor` and `_va_session` bind sessions to persistent visitors. Session boundaries use 30-minute inactivity. Analytics allow-lists and server-only outcomes already exist. Raw IP is hashed; geography uses transient IP and local MMDB/trusted proxy headers. Acquisition, session and event country are separate. Existing country/update commands and rollups will be reused.

Schema: forms has active/published version pointers and lock_version; form_triggers has unique(form_id, trigger_name); submissions student_id is NOT NULL, contact/booking nullable; answers reference version-specific questions. Package original/discount/final prices, payments, refunds and session ledger already exist. Administrators lack time preference. Visitors lack known-student linkage. No stored booking statuses exist locally; writers/presenter define confirmed, cancelled, completed, no_show/no-show, pending, held; unknown logs warning.

Booking lock order retained: bounded named identity mutex on write PDO → DatabaseCapability transaction → buffer-expanded calendar locks → contacts → students → hold/booking/dependent writes → commit → finally release. Same PDO inside/outside transaction verified. Public create signature `(array $data, array $intakeAnswers = [], ?int $formVersionId = null): Booking`. Form publication uses a bounded named lock and locked active version. Autosave already serializes form/student/draft writes. No compatibility classes or alternate booking implementation needed.

Scheduler: heartbeat every minute; holds every five minutes; country rollup 00:02 Cairo, general rollup/prune 00:05 Cairo, backups 02:00, auth cleanup 03:00, reminders every five minutes. Retention currently 180 days and requires durable daily/country rollup. No historical aggregation, pruning, backfill or cleanup will be run in the application database.

## Diagnosed causes

- Confirmation template treats every non-confirmed state as cancelled despite correct model presenter.
- Wizard tutor time is 24-hour; staff has no individual preference.
- Preset branch silently discards entered discounts and expiry without override notes; summary exposes signed debt as negative Remaining Due.
- Resource DNS rule fails open and skips checks in tests; PIN configuration can activate sending.
- Telemetry appends path/page_template to every event, violating per-event metadata allow-list; section observer cannot reach 50% for very tall sections; hidden dwell accounting/retries need repair.
- Resource/game funnels count events rather than unique visitor identities.
- Country/section/bounce dashboard reads daily aggregates without current-day events. Section percentages are literal demo constants.
- Public live counter caches for fifteen minutes and does not exclude bots consistently.
- Forms editor exposes JSON and one trigger; student form has no autosave JavaScript despite backend endpoint.
- Maintenance lookup supports legacy keys but model-save invalidation is incomplete; message hardcoded.
- Student portal routes/services exist but public navigation omits entry; login requires an extra phone-country field.
- WhatsApp floating CTA, internal exclusions and goal configuration are absent.

## PROPOSED_FILE_MODIFICATIONS

Existing files in scope:
app/Domains/Booking/Models/Booking.php
app/Http/Controllers/BookingController.php
app/Domains/Timezone/Services/TimezoneDisplayService.php
app/Domains/Administration/Models/Administrator.php
app/Domains/Students/Services/StudentLedgerService.php
app/Http/Controllers/Admin/StudentBillingController.php
app/Http/Controllers/Admin/SettingController.php
app/Domains/CMS/Models/Setting.php
app/Http/Controllers/ResourceController.php
app/Domains/Analytics/Services/AnalyticsService.php
app/Domains/Analytics/Services/EngagementCounterService.php
app/Domains/Analytics/Services/GeoIpService.php
app/Http/Middleware/TrackVisitorSession.php
app/Http/Controllers/AnalyticsController.php
app/Http/Controllers/Admin/AnalyticsDashboardController.php
app/Console/Commands/GeoIpUpdateCommand.php
app/Console/Commands/AggregateDailyAnalyticsCommand.php
app/Domains/Forms/Services/FormBuilderService.php
app/Http/Controllers/Admin/FormController.php
app/Http/Controllers/Student/FormController.php
app/Http/Controllers/Student/AuthController.php
app/Domains/Students/Services/StudentIdentityService.php
app/Http/Controllers/Student/BookingController.php
app/Http/Controllers/Student/RescheduleController.php
app/Domains/Students/Services/StudentBookingService.php
app/Domains/Booking/Services/BookingService.php
app/Domains/Forms/Services/FormSubmissionService.php
app/Livewire/BookingWizard.php
resources/views/public/confirmation.blade.php
resources/views/livewire/booking-wizard.blade.php
resources/views/layouts/public.blade.php
resources/views/layouts/student.blade.php
resources/views/layouts/admin.blade.php
resources/views/admin/bookings/index.blade.php
resources/views/admin/bookings/show.blade.php
resources/views/admin/dashboard.blade.php
resources/views/admin/students/show.blade.php
resources/views/admin/billing/cashier.blade.php
resources/views/admin/forms/edit.blade.php
resources/views/admin/settings/index.blade.php
resources/views/admin/analytics/index.blade.php
resources/views/errors/503.blade.php
resources/views/student/login.blade.php
resources/views/student/forms/show.blade.php
resources/js/analytics-telemetry.js
resources/js/app.js
config/business.php
config/services.php
routes/web.php
routes/console.php

New files (scaffolded where appropriate):
app/Domains/Resources/Services/EmailQualityService.php
resources/views/components/whatsapp-cta.blade.php
resources/js/form-builder.js
database/migrations/2026_10_02_000001_add_remediation_identity_preferences.php
tests/Feature/OperationalRemediationTest.php
tests/telemetry.test.mjs
tests/form-builder.test.mjs
resources/data/disposable-email-domains.txt
REMEDIATION_REPORT.md

Existing relevant test files may be updated only when an intentionally superseded behavior requires assertion changes; record exact paths below before editing. Generated public/build outputs and ignored storage/geoip database installation are included.

## Verification

The final evidence below distinguishes implementation, automated tests, actual browser checks, and limitations.

Additional manifest before editing: resources/views/admin/analytics/sections.blade.php (honest unavailable rate rendering).

Additional manifest before editing: app/Console/Commands/InstallStudentFormsCommand.php (idempotent onboarding setup using existing versioned builder); app/Domains/Analytics/Models/Visitor.php (known student relationship fillable); resources/views/admin/forms/submissions.blade.php, resources/views/admin/promotions/index.blade.php, resources/views/admin/pages/edit.blade.php, resources/views/admin/system/backups.blade.php (centralized display preference).

Test manifest before editing: tests/Concerns/HasPublishedShortForm.php; tests/Feature/AnalyticsAndReportsTest.php, tests/Feature/AnalyticsFunnelAndAttributionTest.php, tests/Feature/AnalyticsValidationAndIdentityTest.php, tests/Feature/BookingAttributionPersistenceTest.php, tests/Feature/BookingCreationNotificationTest.php, tests/Feature/BookingEngineTest.php, tests/Feature/BookingHoldAuthenticationTest.php, tests/Feature/BookingLanguageSwitchSyncTest.php, tests/Feature/CanonicalAvailabilityValidationTest.php, tests/Feature/CriticalBookingQAMatrixTest.php, tests/Feature/PhaseCVerificationTest.php, tests/Feature/PublicExperienceTest.php, tests/Feature/V3SlotIdentityTest.php, tests/Feature/V5RemediationModuleTest.php, tests/Feature/AdminFunctionsAndCalendarTest.php, tests/Feature/LocalizedPublicFlowAndSeoTest.php, tests/Feature/MariaDbConcurrencyVerificationTest.php, tests/Feature/Concurrency/booking_worker.php. Scheduling tests now explicitly provide a published intake fixture; legacy PIN/bot/copy expectations are superseded. DNS is mocked in resource boundary tests, not bypassed in application code.

Additional manifest: app/Domains/Booking/Services/RescheduleService.php (post-commit outcome tracking), resources/views/student/dashboard.blade.php (explicit overpayment display), app/Console/Commands/AggregateDailyCountryMetricsCommand.php (shared conversion bounce definition).

Additional manifest before edits: .gitignore (exclude installed geolocation dataset); app/Console/Commands/AggregateDailyAnalyticsCommand.php (prospective shared bounce conversion definition); resources/views/admin/contacts/show.blade.php, resources/views/admin/system/audit-logs.blade.php (personal time preference); lang/fr.json, lang/de.json (booking lifecycle translations).

Additional manifest before editing: app/Http/Middleware/EnsureNotUnderMaintenance.php (anonymous maintenance visitor cookie, no network/browser fingerprint).

Additional manifest before editing: app/Domains/Analytics/Models/VisitorSession.php, app/Domains/Forms/Models/FormSubmission.php (accurate relationship generics for new reporting/outcome code).


## Final implementation and acceptance evidence

The following section numbers refer to the current master remediation request. Existing locks, ledgers, schema versions, availability resolution, role gates, and passwordless authentication were retained.

| Request sections | Root cause / repair | Evidence and result |
| --- | --- | --- |
| 5 | A catch-all confirmation branch called every non-confirmed booking canceled. The page now uses the existing lifecycle presenter, reschedule history, explicit status descriptions and status-dependent calendar, meeting and next-step content. FR/DE lifecycle translations added. | OperationalRemediationTest status matrix and LocalizedPublicFlowAndSeoTest passed. Browser confirmed and canceled pages showed the correct titles and guidance. Delivered/no-show/pending/held/unknown states verified by automated HTTP tests. |
| 6, 38 | Display formatting was scattered; tutor review used raw 24-hour slot strings. Central student formatting uses 12-hour AM/PM. Administrators have an individual 12/24 preference, default 24; human-facing staff views use it. Canonical UTC instants and timezone calculations remain unchanged. | Preference ownership and student-format tests passed. Browser switched the staff preference to 12-hour and restored 24. Booking review and confirmation both showed 4:15 PM; portal scheduling/rescheduling showed AM/PM. UTC diagnostic strings remain explicitly 24-hour. |
| 7 | Canonical presets replaced entered discounts; signed balances were presented as negative debt. Presets retain a valid discount, applying eligible diagnostic credit without adding it twice. Original, discount, invoice, net-paid and positive overpaid values are distinct; amount due is nonnegative. Refund action uses a named route and explicitly records manual bookkeeping. | StudentLedgerTest, CashierBillingReconciliationTest, V5RemediationModuleTest and new financial regression passed. Browser: original 280, discount 25, invoice 255; payment 285 -> overpaid 30; manual refund 30 -> paid net 255, due 0, credits unchanged. No processor/API or money transfer. |
| 8 | DNS validation failed open; configuration could activate PIN delivery. EmailQualityService enforces syntax/IDN normalization, disposable parent-domain checks, MX/address routing and null-MX rejection. DNS failures return a retryable validation error. Positive routing cached for one hour. Mode A grants immediate cookie/session-bound download access; PIN endpoint is inactive and sends nothing. | Boundary tests mock DNS responses and exercise grants, consumption, syntax, disposable domains, DNS failure, null MX, expired/replayed access and retired PIN behavior. Tests passed. Local public resource library has no resource content, so a browser download was not claimed. |
| 9–12, 19–36 | Universal client metadata violated per-event allow-lists. Tall sections never reached visibility thresholds. Hidden dwell, retry identities, cached rollups and event-vs-visitor denominators were inconsistent; section percentages were demo constants. Repaired pipeline/reporting definitions are detailed below. | AnalyticsAndReportsTest, AnalyticsFunnelAndAttributionTest, AnalyticsValidationAndIdentityTest, BookingAttributionPersistenceTest, PhaseCVerificationTest, OperationalRemediationTest and JS telemetry tests passed. Actual admin overview/country/section pages rendered real/empty values, including unresolved ZZ. No backfill, rewrite or prune command ran. |
| 13 | Live values were trapped in a long-lived counter payload; monthly audience and practice sources were inconsistent. Live calculation now runs separately, previous-month traffic uses actual session visitor identity, and practice combines completed lessons with educational dwell only. | EngagementCountersTest and analytics regressions passed, including completed-only lesson time and exclusion of inappropriate dwell. Existing configuration switches retained. |
| 14 | Backend versioned forms existed, but the editor exposed JSON and one trigger; portal lacked autosave. Added a visual question editor, multiple trigger checkboxes, conditional rules, stable options, order/delete and preview. Short Form is mandatory before first booking, including an existing student without booking history; missing published intake fails closed. Long Form autosaves and resumes its version. Explicit submit waits for in-flight autosave, then schedules native resubmission after the first event completes. | FormsEngineTest and OperationalRemediationTest passed; JS editor test preserves metadata through edits/reorder. Concurrent publication/autosave tests passed. Browser added/previewed questions, completed Short Form, edited Long Form, saw Saved, reloaded with the answer retained, submitted and saw final responses. The browser caught and verified the submit-event timing fix. |
| 15 | Model writes did not consistently invalidate the maintenance cache; message was hardcoded. Targeted invalidation applies to both recognized keys; custom text is escaped. Customer routes return 503, existing authorized admin/auth/health bypasses remain, and maintenance visits use an anonymous UUID cookie with ip_address NULL. | MaintenanceModeResilienceTest, BackupAndMaintenanceTest and new cache/message tests passed. Real Herd HTTP request: 503, configured message present, WhatsApp absent; one maintenance visit recorded and zero stored raw IPs. Admin dashboard remained accessible. In-app browser navigation aborts on the 503 response, so the visual maintenance page is not represented as a completed browser screenshot. |
| 16–17 | Public navigation omitted the portal; login demanded an extra country input. Added desktop/mobile portal entry and one Phone Number field. Safe national/international normalization is combined with DOB and a second registered identifier; candidate ambiguity/ownership gates retained. Existing credit scheduling and rescheduling services were reused. | StudentAuthenticationTest, StudentCreditBookingTest, StudentReschedulingTest and ownership tests passed. Browser logged in with DOB/email/national formatted phone, saw eight credits, scheduled a lesson (seven remaining), rescheduled it (still seven), and saw the new upcoming time/status. QA booking canceled afterward. |
| 18 | Floating WhatsApp CTA/settings were absent. Added a global validated HTTPS WhatsApp destination, EN/FR/DE labels/messages, independent public/portal switches, desktop text and mobile collapse, and click context/language attribution. | OperationalRemediationTest verifies visibility separation, validation, attribution, goals/export and internal exclusions. Browser enabled the public CTA, saw the configured destination on homepage/pricing, then disabled it and cleared the URL. No WhatsApp message was sent. |
| 37, 39–47 | Repairs must not replace authorization, transaction boundaries or database locking. No dependencies or paid services added. Existing role gates, ownership and lock order remain; new preference route is personal, settings mutations protected, server outcomes tracked after successful actions. | Full current-schema tests and real MariaDB concurrency suite passed. PHP lint, Pint, Blade compilation, frontend build and JS tests passed. Static-analysis limitation documented separately. |

## Analytics definitions and invariants

- Visitor: persistent anonymous `_va_visitor` UUID. Session: `_va_session` tied authoritatively to that visitor, with the existing 30-minute inactivity boundary. Page views, visitors, sessions and conversion events remain separate measures.
- Known-student stitching occurs only after successful verified passwordless login with an owned visitor/session pair. A link is filled only when null; email matching alone does not link or overwrite a visitor. Questionnaire answers, DOB and login identifiers are not analytics event metadata.
- Browser payload metadata is explicitly allow-listed by event. Event UUID survives retries; queue batches cap at 20 and retry bounded transient failures. Initialization is guarded. Public HTTP development uses a UUID fallback where crypto.randomUUID is unavailable; these UUIDs are telemetry identities, not access credentials.
- Dwell: section view when at least half of the visible section/viewport area qualifies, including a section taller than the viewport. Only the dominant visible section accrues foreground dwell. Hidden/unfocused intervals do not accrue. Dwell flushes every 10 seconds and on lifecycle changes, presence every 45 seconds; each dwell chunk capped at 30 seconds.
- Reporting uses completed prior Cairo-day rollups plus current Cairo-day raw data, with no current-day rollup overlap. Country visitor uniqueness uses retained raw identities over the requested range; acquisition country, session-start country and event country retain separate semantics. Unresolved country remains explicit.
- Bounce: session duration under 10 seconds, at most one page view, and no conversion event. Both prospective aggregators use the shared conversion list. Existing historical rows remain untouched.
- Resource and game funnel stages count distinct visitor tokens, not repeated event totals. Booking funnel retains observed versus imputed-stage presentation. Successful booking, resource access, form submission, package scheduling and rescheduling remain authoritative outcomes, not client button clicks.
- Configurable goals support these outcomes: booking_completed, resource_requested, resource_downloaded, whatsapp_clicked, form_submitted, short_form_completed, long_form_completed, package_session_scheduled, booking_rescheduled, game_completed. Reports expose event count, distinct converting visitors and visitor conversion rate.
- WhatsApp dimensions: Cairo date, country, source, medium, campaign, content, public/portal context and language. A click means outbound interest, not a confirmed conversation. Overview CSV/XLSX include these rows and goal metrics.
- Section average attention = dwell seconds / section views. Entry-bounce denominator = distinct sessions exposed to the section; numerator = exposed sessions meeting the shared bounce rule. Drop-off = exposed sessions whose last observed section is that section / exposed sessions. Active sessions are explicitly provisional; no exposure denominator renders unavailable rather than a fabricated percentage.
- Public practice minutes count delivered/completed lesson duration plus curriculum/blog/resource/game section dwell. Hero/pricing browsing does not count as study. Historical/current-day dwell is not counted twice. Public monthly audience uses the prior Cairo calendar month; live online window is 60 seconds and admin live pulse is five minutes.
- Authenticated staff, preview/admin/asset/health routes, detected bots, synthetic-header requests and configured internal connections are excluded. Connection exclusions store only an app-keyed HMAC. Raw IP is used transiently for geolocation and is not stored in analytics or new maintenance rows. Maintenance identity no longer fingerprints IP plus user agent.
- Existing raw retention remains 180 days with durable-rollup prerequisites. No retrospective aggregation, retention cleanup or historical event/visitor correction was executed.

## Database changes and setup

One new forward migration: `database/migrations/2026_10_02_000001_add_remediation_identity_preferences.php`.

1. `administrators.time_format`: string(2), default `24`.
2. `visitors.student_id`: nullable FK to students, null on deletion.

Both additions are guarded for existing columns. No ledger, booking, form answer or historical analytics table was recreated. Rollback intentionally requires a reviewed forward migration to preserve preference/link data. Migration applied to the local application; current test schema refreshed independently. Existing historical Migration A compatibility tests were run on their intended intermediate test schema, then the test database returned to current schema through the normal suite.

Deployment/setup commands, using existing infrastructure:

```text
php artisan migrate --no-interaction
php artisan forms:install-onboarding --administrator=<existing-administrator-id> --no-interaction
php artisan geoip:update --no-interaction
npm run build
```

`forms:install-onboarding` creates/publishes Short Form and Long Form only when missing, through the existing versioned builder. It preserves existing forms and intake ownership. Short Form contains three required learning-goal/experience/context prompts. Long Form contains six deeper optional prompts with after-booking and next-session triggers. Both were installed locally for actual booking/portal verification. Review business copy through the visual editor before publishing any desired replacement version.

The existing geo updater now validates and atomically installs either tar.gz or mmdb.gz country datasets. With no MaxMind license it uses the genuinely free current-month DB-IP Country Lite download. A validated roughly 8 MB database was installed at ignored `storage/geoip/GeoLite2-Country.mmdb`; database type DBIP-Country-Lite. Existing GeoIP reader/trusted-proxy rules reused. Monthly update scheduled day 2, 04:00 Africa/Cairo, without overlap. Run the normal Laravel scheduler in deployed environments; no new scheduler service was installed. Public footer contains DB-IP CC BY 4.0 attribution.

Disposable-domain dataset: `resources/data/disposable-email-domains.txt`, upstream snapshot obtained during this remediation from [disposable-email-domains](https://github.com/disposable-email-domains/disposable-email-domains), [CC0 license](https://github.com/disposable-email-domains/disposable-email-domains/blob/main/LICENSE.txt). Review and refresh this local snapshot from upstream as part of routine maintenance/deployment; there is no paid verifier or runtime mailbox API. DNS routing cannot prove that an individual mailbox exists, and transient resolver failures require retry.

## Automated verification results

| Check | Result |
| --- | --- |
| Full current-schema suite: `php artisan test --compact --exclude-group=intermediate-schema` | **567 passed; 5,690 assertions; 164.823 s** |
| Historical Migration A compatibility group on isolated intermediate schema | **3 passed; 15 assertions** |
| Real concurrency: `php vendor/bin/phpunit --configuration=phpunit.concurrency.xml --display-all-issues --no-progress tests/Feature/MariaDbConcurrencyVerificationTest.php` | **10 passed; 45 assertions; 6.393 s; exit 0** |
| Final focused rerun after browser form fix: FormsEngineTest, OperationalRemediationTest, PublicExperienceTest | **35 passed; 262 assertions** |
| `node --test tests/telemetry.test.mjs tests/form-builder.test.mjs` | **3 passed, 0 failed** |
| `npm run build` | **Passed**, Vite 8.3.0 |
| `php artisan view:cache` | **Passed** |
| `php vendor/bin/pint --dirty --format agent` | **Passed** after formatting |
| PHP syntax lint, changed/new non-Blade PHP files | **53 passed**, no failures |
| PHPStan installed level 5, `--memory-limit=1G --no-progress --error-format=json -v` | **Failed: 369 existing diagnostics**, versus **578** on untouched HEAD. No new file/message diagnostic compared with HEAD. No baseline/ignore suppressions introduced. |
| `git diff --check` | **Passed**, no whitespace errors |

PHPStan initially exhausted its configured 128 MB limit, then completed with an explicit 1 GB command-line limit. Remaining diagnostics are existing model/dynamic-property and type-analysis debt; this is not a clean static-analysis claim. Baseline compared in an untouched `git archive HEAD` checkout using the same installed vendor runtime and project rules.

Important new cases in OperationalRemediationTest: invoice/discount/refund consistency; personal staff time preference; safe phone variants; disposable/DNS/null-MX rejection; current-day read-only reporting and unique funnels; idempotent onboarding and required Short Form; version-safe Long Form autosave; maintenance cache invalidation/escaped message; lifecycle matrix; owned known-student stitching without overwrite; WhatsApp/goal/export/exclusion behavior. JS cases verify metadata rejection avoidance, retry/initialization identity, tall-section foreground-only dwell, and stable builder metadata/reordering.

Real concurrency uses separate processes/connections with MariaDB/InnoDB database cache/locks, covering calendar mutex creation, real row-lock blocking, overlapping booking collision, adjacent slots without deadlock, parallel refunds, booking versus merge lock order, contested package credits, publication conflict, and concurrent autosaves preserving one draft. Standard and concurrency database suites ran serially; an early overlapping test invocation produced invalid schema-interference output and was discarded, then rerun serially. No test assertions were weakened to accommodate that interference.

Relevant diagnostics also executed: installed direct Composer dependency inspection, package.json inspection, Artisan route/list/help/config/migration checks, Herd service/restart diagnostics, schema/index/lock review, geo dataset validation, and final diff/file inspection. No application historical aggregate or prune command, dependency installation, external refund or WhatsApp API command ran.

## Actual browser and running-application verification

Browser used the local Herd application, synthetic student `Remediation Browser QA` (`remediation-browser@example.org`), existing seed administrator and log mail transport. No real customer identity, payment, WhatsApp message or live deployment involved.

- Homepage/pricing and mobile portal entry inspected.
- Existing public game entry inspected: 6-Word Story redirects to the configured external `mohamedateff.com/6word/` game. Its external play/completion telemetry cannot be asserted as first-party application events; local game telemetry is covered by automated HTTP tests.
- Local resource library inspected and is empty. Automated resource tests provide the real grant/download boundary coverage; no browser download of nonexistent content claimed.
- Short Form completed inside real booking; review and confirmation showed the same 12-hour tutor time. Confirmed booking then canceled; false confirmed/calendar/meeting instructions absent.
- Staff time format switched to 12-hour, visibly applied, restored 24-hour.
- Visual forms editor added/previewed a question/options; unsaved exploration discarded.
- Student signed in with DOB + email + formatted national phone. Long Form autosaved, resumed after reload and submitted successfully; admin response view showed submission revision 2.
- Canonical package browser sequence verified original 280, discount 25, invoice 255, overpayment 30, manual refund 30 and zero due.
- Portal package visible with eight credits. Actual credit booking reduced balance to seven, rescheduling preserved seven and updated the upcoming time/status. Test appointment canceled afterward to release the slot.
- Public WhatsApp switch/destination rendered on homepage/pricing; restored disabled and blank destination. No outbound message sent.
- Settings maintenance enabled temporarily, authenticated admin retained access. In-app browser 503 navigation aborted before rendering; a direct request to the running Herd app returned 503, configured message and no WhatsApp, and created one maintenance row with raw IP null. Maintenance restored Live and message restored.
- Actual analytics dashboard showed real current-day values and explicit unknown-country row; no placeholder percentages or invented visitors were injected.

Evidence screenshots (local attachments):

- `C:/Users/e/.codex/attachments/remediation-form-preview.png`
- `C:/Users/e/.codex/attachments/remediation-time-preference.png`
- `C:/Users/e/.codex/attachments/remediation-booking-confirmed.png`
- `C:/Users/e/.codex/attachments/remediation-booking-canceled.png`
- `C:/Users/e/.codex/attachments/remediation-long-form-submitted.png`
- `C:/Users/e/.codex/attachments/remediation-cashier-net-price.png`
- `C:/Users/e/.codex/attachments/remediation-student-rescheduled.png`

Cleanup/restoration: synthetic appointments canceled; student identity reset to legacy_unverified; student/staff sessions signed out; staff preference 24-hour; temporary connection exclusions cleared; maintenance Live; WhatsApp disabled/URL blank. Synthetic student, questionnaire responses, package, manual payment/refund, audit facts and one new maintenance QA visit remain explicitly identified rather than destructively purged. No historical analytics rows deleted or rewritten.

## Remaining limitations

- PHPStan retains the 369 pre-existing diagnostics listed above. Runtime/regression/concurrency/build checks pass, but static analysis is not clean.
- Missing local resource content prevents a real browser download demonstration; external 6-Word Story completion is outside the first-party application's event authority. Their relevant internal endpoint behavior is tested without asserting external completion.
- Browser frontend telemetry is best-effort under blockers, offline shutdown and abrupt process termination. Bounded retries/idempotency and lifecycle flushing improve delivery but cannot guarantee every browser event. Server outcomes remain authoritative.
- Historical analytics were intentionally left untouched. Accuracy starts with repaired instrumentation. Legacy rollups without retained raw identities cannot retrospectively yield exact period-wide distinct visitors; missing section exposure denominators remain unavailable. Last-observed-section drop-off for active sessions is provisional.
- Email checks establish domain plausibility/routing, not mailbox ownership. PIN/mailbox verification is deliberately inactive. Disposable lists and free geolocation data require maintenance; unresolved/private addresses remain unknown.
- No production deployment was requested or performed. Deployments need the migration, onboarding setup when missing, frontend build and geolocation/scheduler setup above. Existing customer/business configuration must be reviewed in the target environment.

## Final changed-file inventory

All changed files are within the proposed scope and its recorded additions. Some proposed paths were inspected but did not need changes. Generated build artifacts and the installed geo dataset are ignored. Exact tracked/untracked source inventory follows:

```text
.gitignore
REMEDIATION_REPORT.md
app/Console/Commands/AggregateDailyAnalyticsCommand.php
app/Console/Commands/AggregateDailyCountryMetricsCommand.php
app/Console/Commands/GeoIpUpdateCommand.php
app/Console/Commands/InstallStudentFormsCommand.php
app/Domains/Administration/Models/Administrator.php
app/Domains/Analytics/Models/Visitor.php
app/Domains/Analytics/Models/VisitorSession.php
app/Domains/Analytics/Services/AnalyticsService.php
app/Domains/Analytics/Services/EngagementCounterService.php
app/Domains/Booking/Services/BookingService.php
app/Domains/Booking/Services/RescheduleService.php
app/Domains/CMS/Models/Setting.php
app/Domains/Forms/Models/FormSubmission.php
app/Domains/Forms/Services/FormBuilderService.php
app/Domains/Forms/Services/FormSubmissionService.php
app/Domains/Resources/Services/EmailQualityService.php
app/Domains/Students/Services/StudentIdentityService.php
app/Domains/Students/Services/StudentLedgerService.php
app/Domains/Timezone/Services/TimezoneDisplayService.php
app/Http/Controllers/Admin/AnalyticsDashboardController.php
app/Http/Controllers/Admin/FormController.php
app/Http/Controllers/Admin/SettingController.php
app/Http/Controllers/Admin/StudentBillingController.php
app/Http/Controllers/BookingController.php
app/Http/Controllers/ResourceController.php
app/Http/Controllers/Student/AuthController.php
app/Http/Controllers/Student/BookingController.php
app/Http/Controllers/Student/FormController.php
app/Http/Middleware/EnsureNotUnderMaintenance.php
app/Http/Middleware/TrackVisitorSession.php
database/migrations/2026_10_02_000001_add_remediation_identity_preferences.php
lang/de.json
lang/fr.json
resources/data/disposable-email-domains.txt
resources/js/analytics-telemetry.js
resources/js/app.js
resources/js/form-builder.js
resources/views/admin/analytics/index.blade.php
resources/views/admin/analytics/sections.blade.php
resources/views/admin/billing/cashier.blade.php
resources/views/admin/bookings/index.blade.php
resources/views/admin/bookings/show.blade.php
resources/views/admin/contacts/show.blade.php
resources/views/admin/dashboard.blade.php
resources/views/admin/forms/edit.blade.php
resources/views/admin/settings/index.blade.php
resources/views/admin/students/show.blade.php
resources/views/admin/system/audit-logs.blade.php
resources/views/components/whatsapp-cta.blade.php
resources/views/errors/503.blade.php
resources/views/layouts/admin.blade.php
resources/views/layouts/public.blade.php
resources/views/layouts/student.blade.php
resources/views/livewire/booking-wizard.blade.php
resources/views/public/confirmation.blade.php
resources/views/student/dashboard.blade.php
resources/views/student/forms/show.blade.php
resources/views/student/login.blade.php
routes/console.php
routes/web.php
tests/Concerns/HasPublishedShortForm.php
tests/Feature/AdminFunctionsAndCalendarTest.php
tests/Feature/AnalyticsAndReportsTest.php
tests/Feature/AnalyticsFunnelAndAttributionTest.php
tests/Feature/AnalyticsValidationAndIdentityTest.php
tests/Feature/BookingAttributionPersistenceTest.php
tests/Feature/BookingCreationNotificationTest.php
tests/Feature/BookingEngineTest.php
tests/Feature/BookingHoldAuthenticationTest.php
tests/Feature/BookingLanguageSwitchSyncTest.php
tests/Feature/CanonicalAvailabilityValidationTest.php
tests/Feature/Concurrency/booking_worker.php
tests/Feature/CriticalBookingQAMatrixTest.php
tests/Feature/LocalizedPublicFlowAndSeoTest.php
tests/Feature/MariaDbConcurrencyVerificationTest.php
tests/Feature/OperationalRemediationTest.php
tests/Feature/PhaseCVerificationTest.php
tests/Feature/PublicExperienceTest.php
tests/Feature/V3SlotIdentityTest.php
tests/Feature/V5RemediationModuleTest.php
tests/form-builder.test.mjs
tests/telemetry.test.mjs
```
