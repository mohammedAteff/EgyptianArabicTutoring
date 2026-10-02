# Discovery

Pass date: 2026-10-02. Baseline: `c913d7e16ee9f5bb1d3ec4baa0a94b5f565f81eb` on main, GitHub and Hostinger before this pass. Earlier remediation reports are preserved. The specification, preceding requests/reports, current screenshots, routes, schema, controllers, services and tests were inspected before implementation.

The existing UTC booking engine, holds, database locks, append-only billing ledger, passwordless student identity, forms, analytics middleware, settings transaction and Telegram automation were extended. No meeting API, paid service or dependency change was introduced. Local browser acceptance used the existing synthetic Remediation Browser QA student and synthetic resource. No local QA database or fake bot is deployed.

# Root Causes

## Settings sections 7–10

A universal endpoint publishing failure could not be reproduced in the current baseline. The operational sections already belong to the single settings form, are validated and saved atomically, and operational values apply on both Draft and Publish. Draft/published copy semantics differ: homepage/About copy remains in preview until Publish, whereas operational controls apply immediately. The screenshots show unchecked WhatsApp/goal controls and an empty destination; those values cannot activate the relevant consumers.

Two practical defects were repaired: validation errors were not clearly surfaced globally, allowing re-rendered old input to look saved, and business-timezone cache invalidation needed an after-commit invalidation to prevent consumers retaining an earlier value across an atomic save. Maintenance already had after-commit invalidation. A form-wide error banner and explicit save-semantics caption now explain the result. The existing all-or-nothing validation and operational transaction were preserved. An invalid enabled WhatsApp destination does not partially publish other settings.

Actual browser Draft → Publish → reload → consumer checks proved maintenance text, WhatsApp public/portal switches, configured goals and connection exclusion. No database mutation substituted for these settings acceptance checks.

## Analytics disagreement

Overview/country/section pages relied on durable rollups plus a current-day fragment; operational traffic could also read retained historical raw activity. Missing historical rollups therefore produced contradictory totals. Visitor identities from sessions without events were omitted in some paths. Aggregate visitor counts could be summed across days while another view counted distinct identities across the period. Source attribution used another retained-data scope. Bounce evaluation at a period boundary could ignore a conversion just after midnight.

ReportService owns the eligible public event/session/booking scopes and canonical traffic summary. AnalyticsService reuses those scopes for retained history, country activity, metrics, goals, attention and acquisition. Public counters and Telegram summaries use those services. Whole-day matching-timezone rollups preserve pruned history; raw and aggregate rows reconcile without additive duplication. Session bounce evaluation uses all retained events belonging to selected sessions, while period conversion totals remain bounded by their event timestamps.

## Hardcoded timezone assumptions

Operational date boundaries, formatter calls, digest windows, counter caches and rollup keys were tied to Cairo. These now derive the configured business timezone. One browser-found booking-list defect converted an already student-local instant back into business time; the student column now explicitly preserves its student timezone. UTC storage remains unchanged.

# Maintenance Page

The Arabic block was removed completely. English heading and configurable message remain. Browser publication of “QA maintenance message — returning shortly.” produced the expected independent public maintenance page on desktop and 390×844 mobile. Local Live mode and original message were restored afterward. The production mode found immediately before deployment was Live (`maintenance_mode=0`); deployment preserves that value.

# Canonical Analytics Definitions

- Visitor: a distinct eligible visitor token with event activity or an eligible session starting in the selected period. Session-only visitors are included. When raw identities have been pruned, a multi-day rollup result is a sum of daily unique visitors, explicitly flagged rather than represented as exact period distinct visitors.
- Session: an eligible visitor_sessions row whose started_at falls within the period. Multiple sessions from one visitor remain multiple sessions.
- Pageview: an eligible `page_view` event whose UTC timestamp falls within the period.
- Conversion: a configured allowed outcome event; event totals and distinct converting visitors are separate. Visitor conversion divides converting visitors by canonical eligible audience. Confirmed booking funnel counts have their own completion/cohort semantics and are labeled separately from the operational booking ledger.
- Active visitor: an eligible distinct visitor associated with a session active within the configured rolling window. The public live counter uses 60 seconds; the admin Live Pulse uses the configured window (default five minutes). These different windows are intentional.
- Bounce: exactly one retained pageview, no conversion event and less than ten seconds of session activity. No fabricated attention percentages are displayed for sections without observations.
- Country: visitor acquisition country, session-start country and event/booking server snapshots are distinct dimensions. Unresolved remains ZZ. Comparing different country dimensions as interchangeable is incorrect.
- Source attribution: retained activity grouped by source/medium/campaign/content. One visitor can appear in multiple source groups; source-row visitor counts are not additive across groups. Attribution and pruned-history scope limits are stated in the UI.
- Internal/admin/bot/synthetic exclusions are enforced by the existing ingestion boundary and shared defensive bot scopes. Connection exclusions prevent future collection from that connection; they do not rewrite previously stored history.

# Cross-Report Reconciliation

The deterministic MariaDB fixture contains 10 visitors, 17 sessions, 42 pageviews, three resource leads and two confirmed bookings over the same Berlin business period. It deliberately includes session-only visitors and retained historical days with no daily rollups.

Automated assertions cover canonical traffic, Overview response data, country totals where dimensions match, metrics, acquisition, operational resource/booking records where scope matches, Telegram summary, CSV and actual XLSX cell values. All comparable totals reconcile to 10 / 17 / 42 / 3 / 2. Configured booking visitor conversion is 20%. Previous-month counter assertions prove Total Sessions returns 17 and Unique Visitors returns 10; this is a query change, not merely a label change.

Operational bookings use lesson dates and selected statuses. Operational resource records include actual requests. Public conversion telemetry uses eligible events in their event period. These scopes are labeled, and intentionally different scopes are not forced to equal.

# Business Timezone Architecture

UTC remains authoritative storage and collision arithmetic. Configured business timezone controls administrator clocks, booking presentation, ordinary cashier/refund/form/audit/backup timestamps, Telegram dates and digest windows, reporting periods and public counter calendar windows. Administrator 12/24-hour preference remains supported.

Daily metrics and country metrics now carry `reporting_timezone`, with timezone included in unique keys. Existing records are tagged Africa/Cairo because that is their historical partition; they are not rebucketed or destroyed. Aggregation writes/rebuilds only the current partition. Matching complete-day aggregates supplement retained raw data. Partial-day scopes cannot borrow a whole-day aggregate. Counter cache keys include timezone and day; changes invalidate cached timezone after commit.

Changing timezone does not reconstruct raw events that retention already removed. Foreign-timezone partitions remain intact. Older periods lacking raw data or matching aggregates may have incomplete coverage; dashboard/report notices and this report make that limit explicit. The historical analytics cutover label remains a historical Cairo reference, not the current business timezone. Technical server UTC diagnostics and timezone examples/font names also remain legitimate.

Real browser changes showed the same QA lesson UTC `2026-10-06 12:00:00`: Cairo 15:00, Berlin 14:00, Dubai 16:00, student London 13:00. Original local business timezone was restored to Cairo. Automated travel coverage also proves Berlin 11:00, New York 5:00 AM, London 10:00 AM and Dubai 14:00 for a separate single UTC instant.

# Student Timezone Architecture

The portal detects the current device timezone and supports an explicit manual override, including “Use device timezone” to clear the override. The chosen timezone is used by scheduling, rescheduling, upcoming/history views and reminders. The stored original timezone is not blindly forced during travel.

Public Livewire and student scheduling/rescheduling share the booking-calendar Blade component, availability service and authoritative UTC engine. Student slots use 12-hour AM/PM display. Actual browser scheduling used New York, then London manual travel and rescheduling; one initial credit was consumed, rescheduling did not consume another, and final QA cancellation restored it. Mobile 390×844 calendar/day selection and New York AM slots were visually checked.

# Meeting Links Architecture

New Meeting Links administration supports custom providers, labels/icons, order, active state/default and manually pasted HTTPS room URLs with friendly names and notes. Zoom, Google Meet and Teams are initial data, not a closed enum. No external meeting-generation API exists.

A verified legacy HTTPS meeting setting is imported into a room and its detected provider becomes the default, preserving the working provider for future assignment. Nonoverlapping future confirmed lessons receive immutable URL/provider snapshots. Overlapping legacy lessons are deliberately left unassigned for staff review rather than given a colliding reusable room.

Automatic assignment uses the student preference or provider default and available eligible rooms. Explicit reassignment supports an alternate provider. Ordered row locks, overlap checks and transaction retries protect reusable room assignment; overlapping same-room assignments are rejected, different rooms and adjacent intervals work. Duplicate room URLs are protected by a unique hash and validation handles a concurrent duplicate. Provider/room archival preserves historical assignments and labels/URLs.

Student reveal is independently configurable (default 15 minutes). Telegram countdown lead windows are independent (a ten-minute rule can be configured). Rendered delivery payloads re-read the booking at delivery time. Canceled, ended, unassigned and ineligible/suspended student sessions expose no link. Student portal ownership and existing secret confirmation-token access boundaries are preserved. Countdown presentation uses UTC instants; the browser does not receive the room URL before reveal. Calendar export includes a link only when eligible at generation time.

Actual browser: synthetic Zoom and Google Meet rooms were created; a QA lesson automatically used Zoom and was reassigned to Google Meet. Both QA rooms were archived after cancellation. Automated tests cover reveal threshold, cancellation, snapshots, overlap, adjacent intervals and real simultaneous MariaDB assignment workers. No real customer reminder was sent in this pass.

# Payment Methods

Cashier/Billing now has configurable payment methods with stable IDs, add/rename, enabled/archive, sort order and default. New payment entries select active methods. Historical payment method text remains its original snapshot; archival/renaming cannot rewrite ledger facts. Referenced methods are protected by restrictive foreign keys and no destructive delete action is offered.

Browser QA added and renamed Revolut QA transfer, moved it to the top/default, disabled PayPal for new entry while historical PayPal payments remained labeled, then restored PayPal/default and archived the synthetic Revolut method.

# Courtesy Credits / Ledger

Courtesy/free/recovery adjustments reuse StudentLedgerService, an idempotency key, required reason and administrator actor. No fake monetary payment is needed. Cashier and student package details show purchased, granted/courtesy, consumed, restored, forfeited and remaining credits using the existing append-only entries. Actual QA added +1 courtesy credit with a reason; nine credits remain after scheduling/rescheduling/cancellation verification. That synthetic audit/ledger history is retained, not deleted.

# Refund Synchronization

Cashier and Student admin package details derive balances from the same payment/refund ledger. Student details now show original payment, immutable method, reference, refunded and refundable amounts, full/partial state, refund reason/date and actor-related history. Fully refunded payments offer no further refund control. Existing synthetic payment/refund records were viewed through both UI surfaces; totals remained gross USD305, refunds USD50, net USD255 and due USD0. No real money transfer occurred.

# Package Validity Extension

The Student admin package control permits a later expiration only, checks the submitted previous value, requires a reason and records actor, old/new dates and timestamp. It does not change credit/payment facts. Audit scrubbing previously misidentified a strict ISO expiration date as a phone number; only the exact business expiration_date field with strict YYYY-MM-DD is now preserved. Identity scrubbing remains intact.

Browser QA extended the synthetic package, then confirmed readable 2027-01-14 → 2027-01-15 audit dates after the scrub fix. The first QA audit created before that fix remains hashed; history was not rewritten to conceal that discovery.

# Account Suspension

Super Admin can suspend/restore student, administrator or assistant with a reason and audit event. Existing sessions are denied on the next authenticated request and login is denied. Financial/package history and bookings are preserved, with no automatic cancellation. Self-suspension and suspension of the last available Super Admin are rejected. Ordered administrator locks plus fresh actor authorization prevent two Super Admins from concurrently suspending each other and leaving both inactive.

Actual browser student suspension denied refresh and login; restoration allowed login with original credentials and preserved the scheduled lesson/balance. Staff denial/restoration, protected-role behavior and role boundaries are automated test coverage; no real staff account was suspended for browser QA.

# Resource Email Identity / Secondary Emails

Logged-in resource gates prefill editable name/email. Primary and verified secondary emails normalize/reuse the canonical student/contact. An arbitrary edited email is retained as encrypted request activity but is not promoted to trusted identity. Mode A still grants access without PIN or mandatory email delivery. Existing email quality/security-context rules remain enforced.

A separate student Profile verification flow uses 64-character random, stored SHA-256 tokens, 15-minute expiry, single-use consumption, owner checks, six requests/hour and per-route verification throttling. Verified aliases are globally unique; conflicting primary/secondary claims are rejected, never silently merged. Ownership claims serialize against primary edits. Privacy/merge services preserve appropriate ownership/history while removing private submitted addresses/challenges when required.

Automated tests prove primary/verified-secondary reuse, arbitrary-email isolation, verification ownership/expiry/replay/rate/conflicts and five different resources producing one canonical student/contact and five activities. Browser QA covered the one existing synthetic Mode A resource: normalized primary, uppercase primary and edited alternate address all reached “Your File Is Ready!” without a PIN. Profile showed only the primary, proving the edited alternate was not verified. The synthetic primary briefly used a routable QA address because example.org fails existing email-quality DNS checks; its original address, identity status and device timezone were restored via the UI. No verification email was sent to a real third-party address as browser QA.

# UI Cleanup

- English maintenance on desktop/mobile.
- Lower goals/WhatsApp tables reuse the upper card/header/row/scroll treatment; desktop and mobile horizontal table scrolling visually checked.
- Recent active visitor cards use friendly page/source and human last-seen text; internal token/hash labels removed.
- Redundant Telegram settings notice removed; Telegram sidebar entry has the existing SVG icon style and working mobile navigation.
- Responsive Meeting Links controls, payment manager, student credit/refund/validity/suspension controls and shared mobile calendar checked.
- Settings form displays validation failures and save semantics clearly.

# Database Changes

Four forward migrations add timezone-isolated rollup keys; account suspension metadata; six seed payment methods and nullable payment-record references; configurable providers/rooms and booking assignment snapshots; student provider preference; unique verified secondary emails, expiring challenges, and encrypted submitted resource email/student reference.

Existing historical records, UTC instants, form versions, ledgers and primary identity rules are preserved. Foreign keys and indexes are MariaDB compatible. Rollbacks deliberately refuse to drop history-bearing new structures; rollback requires reviewed forward repair or private pre-deployment restoration, not a casual migrate:rollback.

# Tests

Final current-schema release run: **660 tests passed, 6,279 assertions** (343.889 seconds). Separate real MariaDB concurrency run: **13 tests passed, 57 assertions** (14.145 seconds). Earlier focused pass: 20 tests / 203 assertions; latest operations/reporting corrections: 13 tests / 110 assertions. Previous full run: 659 / 6275, all passed. Intermediate-schema historical tests remain excluded from the current-schema run, as in the baseline; they require their own earlier schema.

JS: five tests passed. Vite production build passed. Blade compilation passed. Changed PHP lint: 115 files, zero failures at the recorded check. UTF-8 check passed. Pint fixes applied. Final git diff whitespace check and final changed-file lint are recorded at release.

# Browser Verification

All settings and operational mutations above used real visible UI controls on the running Herd application. Synthetic QA booking #3 was scheduled, rescheduled, room-reassigned, then canceled to restore its credit. Synthetic room/method records are archived, and ledger/audit activity remains append-only. Original local maintenance mode/message, WhatsApp switches/destination, goals, connection hashes, business/student timezone and student identity/email state were restored. Earlier real production bot connectivity acceptance is in the preceding report; this pass does not send another real message.

Screenshots saved locally:

- C:/Users/e/AppData/Local/Temp/awa-business-maintenance-desktop.png
- C:/Users/e/AppData/Local/Temp/awa-business-maintenance-mobile.png
- C:/Users/e/AppData/Local/Temp/awa-business-analytics-tables.png
- C:/Users/e/AppData/Local/Temp/awa-business-analytics-mobile.png
- C:/Users/e/AppData/Local/Temp/awa-business-student-calendar-mobile.png

# PHPStan Delta

The complete baseline source was extracted privately at c913d7e and analyzed with the same installed PHP/dependencies/configuration. Diagnostic inventories were generated only in temporary files to compare all messages (the normal agent output truncates at 30 findings). They are not included in configuration, committed, or used to suppress findings.

Baseline: 307. Current: 285. Removed: 22. New: zero, comparing diagnostic message/path/count while ignoring shifted line numbers. No analysis level reduction, ignores, suppression baseline or dependency change. PHPStan remains failing on inherited debt; it is not claimed clean.

# Deployment Requirements

GitHub main and Hostinger deployment are explicitly authorized in this conversation. Verify all release checks before pushing. Preserve production .env/APP_KEY, uploads, database, existing untracked .env backup, bot configuration and current business settings. Ship production-built assets to both app/public/build and the existing /arabictutor public wrapper; apply four forward migrations and refresh config/routes/views. queue:restart is a signal, not a running worker.

Private pre-deployment snapshot: `/home/u494520852/deployment-backups/20261002-business-c913d7e`. It includes source, wrapper, prior built assets, private environment, before-state hashes and `backup-full-2026-10-02-132456.zip` (SHA-256 5723bd0a3cac74a35a12d3d811345cedb8da5d774e2c5598ba0a4166d04695bc). Production was Live immediately before this deployment. Release verification must compare the settings/environment hashes and existing financial/booking record counts.

Hostinger scheduler/queue/watchdog/poll jobs remain deferred at the user's explicit request pending their developer. This pass must not claim recurring reminders or alerts are operational merely because code and tests pass.

# Remaining Limitations

- Host-level recurring execution and whole-host external monitoring remain deferred/unverified.
- Historical pruned raw data cannot be recreated in another business timezone; preserved old partitions and current-timezone coverage notices make the gap explicit.
- Multi-day unique visitor totals cannot be exactly deduplicated once identities are pruned; source/country scopes and count bases are documented rather than falsely equalized.
- PHPStan has 285 inherited findings.
- Only one synthetic resource's three email variants were browser-tested; five-resource identity reuse is automated coverage.
- Real staff suspension, room contention and reveal-boundary timing are automated tests rather than time-traveling a production browser.
- No external meeting API and no real customer messages/financial transfers were used as QA.

# Files Changed

Implementation file inventory (including new models, factories, migrations and tests; previous reports unchanged):

- `app/Console/Commands/AggregateDailyAnalyticsCommand.php`
- `app/Console/Commands/AggregateDailyCountryMetricsCommand.php`
- `app/Console/Commands/BackfillDailyCountryMetricsCommand.php`
- `app/Domains/Administration/Models/Administrator.php`
- `app/Domains/Analytics/Models/DailyCountryMetric.php`
- `app/Domains/Analytics/Models/DailyMetric.php`
- `app/Domains/Analytics/Services/AnalyticsService.php`
- `app/Domains/Analytics/Services/EngagementCounterService.php`
- `app/Domains/Audit/Models/AuditLog.php`
- `app/Domains/Booking/Models/Booking.php`
- `app/Domains/Booking/Models/MeetingProvider.php`
- `app/Domains/Booking/Models/MeetingRoom.php`
- `app/Domains/Booking/Services/BookingService.php`
- `app/Domains/Booking/Services/IcsGenerator.php`
- `app/Domains/Booking/Services/MeetingLinkService.php`
- `app/Domains/Booking/Services/RescheduleService.php`
- `app/Domains/CMS/Models/Setting.php`
- `app/Domains/Marketing/Services/PromotionService.php`
- `app/Domains/Notifications/Services/TelegramAutomationService.php`
- `app/Domains/Notifications/Services/TelegramDeliveryService.php`
- `app/Domains/Notifications/Services/TelegramNotificationService.php`
- `app/Domains/Notifications/Services/TelegramReadService.php`
- `app/Domains/Notifications/Services/TelegramRuleCatalog.php`
- `app/Domains/Reporting/Services/ReportService.php`
- `app/Domains/Resources/Models/ResourceRequest.php`
- `app/Domains/Students/Models/PaymentMethod.php`
- `app/Domains/Students/Models/PaymentRecord.php`
- `app/Domains/Students/Models/Student.php`
- `app/Domains/Students/Models/StudentEmail.php`
- `app/Domains/Students/Models/StudentEmailVerification.php`
- `app/Domains/Students/Services/StudentBookingService.php`
- `app/Domains/Students/Services/StudentEmailService.php`
- `app/Domains/Students/Services/StudentLedgerService.php`
- `app/Domains/Students/Services/StudentMergeService.php`
- `app/Domains/Students/Services/StudentPrivacyService.php`
- `app/Domains/Timezone/Services/TimezoneDisplayService.php`
- `app/Http/Controllers/Admin/AccountSuspensionController.php`
- `app/Http/Controllers/Admin/AdministratorController.php`
- `app/Http/Controllers/Admin/AnalyticsDashboardController.php`
- `app/Http/Controllers/Admin/AuthController.php`
- `app/Http/Controllers/Admin/MeetingLinkController.php`
- `app/Http/Controllers/Admin/PaymentMethodController.php`
- `app/Http/Controllers/Admin/PromotionController.php`
- `app/Http/Controllers/Admin/ReportController.php`
- `app/Http/Controllers/Admin/SettingController.php`
- `app/Http/Controllers/Admin/StudentBillingController.php`
- `app/Http/Controllers/Admin/StudentController.php`
- `app/Http/Controllers/Admin/SystemHealthController.php`
- `app/Http/Controllers/Admin/TelegramController.php`
- `app/Http/Controllers/BookingController.php`
- `app/Http/Controllers/ResourceController.php`
- `app/Http/Controllers/Student/AuthController.php`
- `app/Http/Controllers/Student/BookingController.php`
- `app/Http/Controllers/Student/DashboardController.php`
- `app/Http/Controllers/Student/ProfileController.php`
- `app/Http/Controllers/Student/RescheduleController.php`
- `app/Http/Middleware/EnsureAccountActive.php`
- `app/Http/Middleware/EnsureAdminPreviewAccess.php`
- `app/Http/Middleware/EnsureStudentAuthenticated.php`
- `app/Mail/StudentSecondaryEmailVerification.php`
- `bootstrap/app.php`
- `database/factories/MeetingProviderFactory.php`
- `database/factories/MeetingRoomFactory.php`
- `database/factories/PaymentMethodFactory.php`
- `database/factories/StudentEmailFactory.php`
- `database/factories/StudentEmailVerificationFactory.php`
- `database/migrations/2026_10_02_100308_add_reporting_timezone_to_analytics_rollups.php`
- `database/migrations/2026_10_02_101622_add_business_operations_controls.php`
- `database/migrations/2026_10_02_102325_add_meeting_room_assignments.php`
- `database/migrations/2026_10_02_103517_add_verified_student_secondary_emails.php`
- `resources/views/admin/administrators/edit.blade.php`
- `resources/views/admin/analytics/countries.blade.php`
- `resources/views/admin/analytics/index.blade.php`
- `resources/views/admin/availability/index.blade.php`
- `resources/views/admin/billing/cashier.blade.php`
- `resources/views/admin/billing/payment-methods.blade.php`
- `resources/views/admin/bookings/index.blade.php`
- `resources/views/admin/bookings/meeting-links.blade.php`
- `resources/views/admin/bookings/show.blade.php`
- `resources/views/admin/contacts/show.blade.php`
- `resources/views/admin/dashboard.blade.php`
- `resources/views/admin/promotions/create.blade.php`
- `resources/views/admin/promotions/edit.blade.php`
- `resources/views/admin/promotions/index.blade.php`
- `resources/views/admin/reports/index.blade.php`
- `resources/views/admin/settings/index.blade.php`
- `resources/views/admin/students/show.blade.php`
- `resources/views/admin/system/backups.blade.php`
- `resources/views/admin/system/health.blade.php`
- `resources/views/admin/telegram/index.blade.php`
- `resources/views/components/booking-calendar.blade.php`
- `resources/views/components/student-timezone.blade.php`
- `resources/views/emails/student-secondary-verification.blade.php`
- `resources/views/errors/503.blade.php`
- `resources/views/layouts/admin.blade.php`
- `resources/views/livewire/booking-wizard.blade.php`
- `resources/views/public/confirmation.blade.php`
- `resources/views/public/resources/show.blade.php`
- `resources/views/student/bookings/create.blade.php`
- `resources/views/student/dashboard.blade.php`
- `resources/views/student/profile.blade.php`
- `resources/views/student/reschedule.blade.php`
- `routes/console.php`
- `routes/web.php`
- `tests/Feature/BusinessOperationsControlsTest.php`
- `tests/Feature/BusinessReportingReconciliationTest.php`
- `tests/Feature/Concurrency/booking_worker.php`
- `tests/Feature/EngagementCountersTest.php`
- `tests/Feature/MariaDbConcurrencyVerificationTest.php`
- `tests/Feature/OperationalSettingsWorkflowTest.php`
- `tests/Feature/PublicExperienceTest.php`
- `tests/Feature/StudentMergeAndPrivacyTest.php`
- `tests/Feature/StudentReschedulingTest.php`
- `tests/Feature/StudentSecondaryEmailTest.php`
- `tests/Feature/TelegramMultiRecipientReminderTest.php`
