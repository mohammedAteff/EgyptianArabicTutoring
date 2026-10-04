# Executive Summary

Audit date: 2026-10-04, Africa/Cairo. Scope: discovery and planning only. **79 candidates were audited: nine specific audit areas and all 70 retained numbered Ideas Vault items.** Overlaps are intentionally counted as requested candidates, not as 79 proposed modules. The Vault's eight closing thematic groups and its omitted numbers are not additional features.

The strongest path is to extend the existing Booking, Student, Resource, Forms, Ledger, Reporting and Notifications domains. Start with shared filter/copy presentation and selected-package billing presentation, then repair export parity and social reporting. Establish a lesson workspace for optional materials, recording links and preparation notes. Treat typed entitlements and optional Super Admin TOTP as separate, carefully tested domain/security releases.

Typed credits require schema changes and a reviewed historical mapping. Current package purchases do not persist their preset/product identity; ledger quantities have no entitlement type. Do not label generic balances as “1-hour” or “2-hour” without approved evidence. Optional TOTP fits the custom administrator authentication boundary, but no installed OTP/QR/recovery implementation currently supplies it.

The audit viewer, internal admin inbox, daily dashboard, availability exceptions, form drafts, educational notes, private resource delivery, meeting allocator and financial ledger already exist. Their presence supports reuse; it does not prove every expanded Vault requirement is complete. No complete retained request is classified A merely because a smaller foundation works.

Evidence levels used throughout:
- **Code:** inspected current classes, routes, schema/migrations, views and JavaScript.
- **Connected flow:** producer, authorization, persistence and consumer traced; static evidence, not a new browser acceptance run.
- **Tests:** named existing tests inspected as coverage evidence; tests were not executed in this audit.
- **Reported deployment:** earlier reports describe deployment and tests. This audit made no production connection and does not independently re-certify them.
- **Operational:** background delivery cannot be called operational from code or a historical connectivity test. Hostinger acceptance remains deferred.

A material contradiction needs a later repair: the requested no-persistent-raw-IP invariant is respected by analytics/maintenance visitor collection, but several audit/authentication paths explicitly write request IPs. This audit records the discrepancy without changing or deleting data.

# Repository / Baseline State

| Item | Observed state |
|---|---|
| Repository | C:/Users/e/Herd/ArabicwAbdallah |
| HEAD | d15967c88912f34db289ef3621bd9ebef1d56c85 |
| Branch | main |
| Starting working tree | No tracked modifications; untracked HOSTINGER_OPERATIONS_HANDOFF.md |
| Allowed output | FEATURE_GAP_REUSE_AND_DEPENDENCY_PLAN.md only |
| PHP | Local CLI 8.4.25 |
| Framework / UI | Laravel 13.32.0; Livewire 4.4.5; Tailwind declared ^4; Vite declared ^8 |
| Other relevant installed PHP packages | PhpSpreadsheet 5.9.0; PHPUnit 12.5.35; Boost 2.9.0; Pint 1.32.1; libphonenumber 9.0.39; geoip2 3.4.0 |
| Local database | MariaDB 10.11.18; read-only inspection only |
| Local migration state | 63 migration files recorded as Ran, through batch 8; no migration executed |
| Shared rules | AGENTS.md inspected; .ai/rules does not exist |
| Hostinger handoff hash | C551CD5E06E671DD31CA7DBA127253F55443CBB26471CAA5B8B8677D76D94286 |

Current HEAD is a documentation follow-up to feature commit f889c2d16756953dcacefc0857e1c5bfb30a744b, whose deployment is described in FEATURE_EXPANSION_IMPLEMENTATION_REPORT.md. The preserved operations handoff names older release 5aaa22ab85ace375f3effd81924d635f7f7bc214; current HEAD does not equal either literal SHA. Its older “same local/GitHub/production SHA” statement is historical, not today's verification. No remote fetch, push, production status query or deployment was performed.

Read-only inspection included dependency versions, routes, migration status, local schema/data aggregates, source search, relevant historical documents and tests. Local synthetic records demonstrate possible ambiguous package identities; they do not establish the state of production historical data. No student identity, bot secret, token, credential or recovery artifact is reproduced here.

Laravel Boost discovery exposed no callable schema/query/search-docs tools in this session. Existing files/migrations and read-only local inspection were the fallback; no tinker mutations or verification scripts were created. Official Laravel/Fortify sources were consulted only for the proposed, currently missing TOTP implementation.

# Documents Reviewed

| Source | Review scope / authority |
|---|---|
| Supplied forensic audit request, attachment 3d49e7a8-4da2-4c7f-8919-88b11b83a168/Pasted text.txt | Entire request; authoritative scope, nine areas, output structure and prohibition on implementation |
| Supplied Ideas Vault, attachment a46587f6-30c2-4c14-b71f-2c31d1f2054d/Pasted text.txt | Entire Vault; authoritative 70 retained IDs and feature purposes |
| AGENTS.md | Current repository conventions and safe inspection constraints |
| README.md, SYSTEM_REFERENCE.md, SYSTEM_CAPABILITIES_REPORT.md | Architecture and capability context; conclusions checked against source |
| ARABIC TUTORING WEBSITE FINAL 16 Sep.md; Arabic Tutoring EDITS V1.md; Arabic w Abdallah Edits V2.md; Arabic w Abdallah EDIT V3.md; Arabic_wi_Abdallah_Edits_V4.md; ArabicwithhAbdallah_EDIT V5.0.md | Relevant architecture/history sections and headings reviewed; specifications are historical intent, not new instructions to execute |
| CODEX_PROJECT_REVIEW.md; CODEX_CRITICAL_REVIEW.md; CODEX_FINAL_PROJECT_AUDIT.md; CODEX_V94_SECOND_PASS_AUDIT.md; Gemini.md | Relevant findings reviewed as historical hypotheses, not current truth |
| PROJECT_STATUS.md; walkthrough.md | Older migration/test/deployment/cron statements compared with current baseline |
| REMEDIATION_REPORT.md; FOLLOWUP_REMEDIATION_REPORT.md | Resource gate, cancellation, forms, financial and social remediation context |
| TELEGRAM_OPERATIONAL_REMEDIATION_REPORT.md; POST_DEPLOYMENT_BUSINESS_REMEDIATION_REPORT.md | Bot/delivery architecture, settings, analytics and operational limits |
| FEATURE_EXPANSION_IMPLEMENTATION_REPORT.md; SOCIAL_PROOF_COUNTERS_REPORT.md | Latest feature, test, export, notes, meeting and reported deployment evidence |
| HOSTINGER_OPERATIONS_HANDOFF.md | Dependency context only; explicitly deferred, not executed, unchanged |
| docs/GEOIP.md | Country trust model and raw-IP claim; compared with audit writers |
| Current source, migrations, route inventory, tests, composer metadata, package.json | Authoritative implementation evidence; relevant files below |

The large historical specifications/reports received targeted review, not a claim that every historic line is implemented. The two supplied current-request documents were read in full.

Contradictions and qualifications discovered:

| Historical/general claim | Current evidence / implication |
|---|---|
| Older status describes 44 migrations and an older test/deployment baseline | Current local migration state is 63; latest feature report describes newer release and coverage. Do not mix dates or local/production batch numbers. |
| Old review defects imply resource security, notes or meeting work is still absent | Current controllers, domain services and tests contain subsequent remediation. Re-audit the actual flow before rebuilding. |
| “Streaming exports” / bounded query chunks imply bounded XLSX memory | ExportService streams CSV and iterates input, but PhpSpreadsheet holds workbook cells in memory. Some reporting queries also materialize collections. |
| Social placements are preserved | True at ingestion and general social report; AnalyticsService::whatsappReport omits placement/page when grouping, so the overview merges them. |
| No raw IP is stored in the application database, including docs/GEOIP.md's blanket sentence | AuditLogService, Admin AuthController, PasswordResetController and RescheduleService contain raw IP writes. Analytics maintenance visits explicitly store null. |
| Form drafts would become newly visible through Vault #64 | FormController currently includes draft submissions/answers in staff views and export, subject to assistant question visibility. A future privacy policy must address the existing behavior. |
| Package presets represent persistent product identity | StudentLedgerService accepts a preset key but StudentPackage has no product/preset column. Matching name, quantity and USD is a heuristic, not provenance. |
| All credits are suitable for any session because a positive package balance exists | Current generic booking selector has no entitlement compatibility check. Duration overrides make inference from minutes especially unsafe. |
| Retention setting 180 days guarantees detailed social events for 180 days | AggregateDailyAnalyticsCommand also has a rollup-gated 90-day raw-event pruning path. General rollups do not preserve every social dimension. |
| Existing health check/inbox/audit/today screens meet every proposed expansion | Foundations exist; missing host validation/open-room action, notification types/recipient semantics, filters and operational cards remain. |
| Prior cron screenshot or one verified bot proves autonomous automation | Latest handoff explicitly lacks final operations acceptance. Connectivity is separate from scheduler/worker/watchdog operation. |
| Historical intermediate forms schema tests describe the current schema | Two retained intermediate-schema failures are reported against the unchanged baseline; do not revive removed forms.prompt_trigger to satisfy obsolete assumptions. |

# Current Architecture Map

Evidence references E01–E22 are reused in the feature matrix; each identifies actual files/classes. Proposed classes/tables later in this report are recommendations, not existing implementation.

## Authentication

**E01.** [Admin AuthController](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Controllers/Admin/AuthController.php), [Administrator](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Administration/Models/Administrator.php), [auth configuration](C:/Users/e/Herd/ArabicwAbdallah/config/auth.php), [PasswordResetController](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Controllers/Admin/PasswordResetController.php), [student AuthController](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Controllers/Student/AuthController.php).

Staff use the web guard and custom email/password controller, suspended-account condition, rate limiting, remember option and session regeneration. A successful password currently establishes full staff authentication immediately. Administrator has roles but no TOTP/recovery fields or challenge. Password reset uses the administrator broker; password_timeout configuration alone does not implement a password-confirmation flow.

Student access uses the existing identity/session flow with DOB and registered identifiers, verified/unsuspended account checks and verified secondary-email support. It is not the same login pipeline as staff. Extend either explicitly; do not replace student identity with administrator authentication.

## Roles / Authorization

**E02.** [routes/web.php](C:/Users/e/Herd/ArabicwAbdallah/routes/web.php), [EnsureAdminRole](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Middleware/EnsureAdminRole.php), [StudentBinPolicy](C:/Users/e/Herd/ArabicwAbdallah/app/Policies/StudentBinPolicy.php), [StaffBinPolicy](C:/Users/e/Herd/ArabicwAbdallah/app/Policies/StaffBinPolicy.php).

Roles are super_admin, admin and assistant, with route middleware, controller gates and parent ownership checks. Staff access to Student Records does not imply access to finance, identity edits or exports. Financial mutations/exports are Super Admin/Admin; destructive student merge/privacy operations and audit-log viewer are Super Admin. Form exports are available under staff routes but filter assistant-hidden questions. Notes have their own authorship/visibility rules. Preserve each boundary rather than copying a broad “staff” permission.

## Students / Contacts

**E03.** [StudentRecordsQuery](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Students/Services/StudentRecordsQuery.php), [DirectoryQuery](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Contacts/Services/DirectoryQuery.php), [ContactService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Contacts/Services/ContactService.php), [StudentIdentityService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Students/Services/StudentIdentityService.php), [StudentMergeService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Students/Services/StudentMergeService.php), [StudentPrivacyService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Students/Services/StudentPrivacyService.php).

Students are canonical tutoring identities; Contacts include leads/resource requesters. DirectoryQuery joins unambiguous canonical/verified-email ownership and combines roster students with remaining leads. It is not a second student registry. Student roster criteria are encoded in Student::tutoringRoster. Existing secondary email verification, merge and privacy services must include every new student-related relation.

ContactService::findSuspectedDuplicates currently detects repeated phone groups despite its broader email-oriented comment; it is not a complete canonical-student duplicate review tool. Never infer a merge from name alone.

## Booking / Availability

**E04.** [BookingService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Booking/Services/BookingService.php), [StudentBookingService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Students/Services/StudentBookingService.php), [AvailabilityService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Availability/Services/AvailabilityService.php), [CancellationService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Booking/Services/CancellationService.php), [RescheduleService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Booking/Services/RescheduleService.php), [BookingController](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Controllers/Admin/BookingController.php), [AvailabilityController](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Controllers/Admin/AvailabilityController.php).

UTC start/end storage, business/customer timezone snapshots, issued slot identities, hold authentication, authoritative availability and calendar/student row locks are established foundations. Availability rules support duration overrides, buffers, minimum notice, horizon and exceptions. Public/admin creation paths and returning-student package booking are distinct; only the latter calls StudentLedgerService::consumeForBooking in its transaction. Future paid-package bookings through another path need an explicit policy, not an assumed debit.

Cancellation enforces customer cutoff/past/status rules; staff can bypass the customer cutoff. Accepted cancellation restores the original booking consumption if present. Completion/no-show update lifecycle/event/audit records; neither consumes a second credit. Repeated completion currently errors rather than returning an idempotent success. Rescheduling keeps the consumed package; typed compatibility must be added without double consumption. No recurring-series or waitlist domain was found.

## Packages / Credits / Ledger

**E05.** [StudentLedgerService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Students/Services/StudentLedgerService.php), [StudentPackage](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Students/Models/StudentPackage.php), [SessionLedgerEntry](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Students/Models/SessionLedgerEntry.php), [billing migration](C:/Users/e/Herd/ArabicwAbdallah/database/migrations/2026_09_23_163158_create_student_billing_tables.php), [StudentBillingController](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Controllers/Admin/StudentBillingController.php).

The append-only ledger stores signed integer credit_change, student, package, optional booking, reason/description, creator and idempotency key. Entry types are package_grant, session_consumed, courtesy_adjustment, cancellation_restore and expiration_forfeit. Booking/type and idempotency uniqueness support replay protection. Money lives in package/payment/refund fields, not credit quantities.

Package definitions are StudentLedgerService::PRESETS, not a persisted catalog. Purchases retain package_name, total_sessions_allocated, money/currency, expiry/status, with historical term mutation guards. No stable product FK or typed allocation exists. Generic balances and earliest-expiring usable-package selection are the current semantics.

## Cashier / Financial Records

**E06.** [CashierReportService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Students/Services/CashierReportService.php), [BillingReconciliationService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Students/Services/BillingReconciliationService.php), StudentBillingController and E05.

Payments link to student package instances and snapshot payment method names, amount/currency, time and references. Refunds link to the original payment and package and cannot exceed remaining refundable amounts. Optional refund forfeiture appends a negative ledger row; it does not rewrite consumption. Manual payment/refund recording does not execute provider payments/refunds.

Cashier and Student Records share ledger/money services but expose different presentation/actions. Reconciliation derives negative balances, refund excess, credit mismatches and ownership inconsistencies. Existing installments means multiple payment records; it does not include due schedules or allocations.

## Student Portal

**E07.** [student DashboardController](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Controllers/Student/DashboardController.php), [student dashboard](C:/Users/e/Herd/ArabicwAbdallah/resources/views/student/dashboard.blade.php), E01/E04/E05/E12.

Portal displays package balances, upcoming/history bookings, forms, educational notes and eligible meeting links under student ownership. It lacks a lesson workspace, assigned resource history, homework, recording links, persistent notifications and next-action prioritization. Its package summary does not currently expose effective expiry to build the proposed student expiry alerts. Read-only preview must reuse the student projection, not render staff detail pages.

## Educational Notes

**E08.** [StudentBin](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Students/Models/StudentBin.php), [StaffBin](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Administration/Models/StaffBin.php), [StudentBinController](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Controllers/StudentBinController.php), [StaffBinController](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Controllers/Admin/StaffBinController.php), E02 policies.

Student educational notes already distinguish student_visible and author identity. Staff may read under policy; student reads only own visible notes; edit/delete rules preserve author and Super Admin restrictions. Shared Staff Notes allow staff read/create, author-only edit and Super Admin soft delete. There is no booking FK, assignment lifecycle or structured progress/pronunciation model. Extend these boundaries; do not expose internal staff notes as lesson content by convention.

## Resources

**E09.** [Resource](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Resources/Models/Resource.php), [ResourceController](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Controllers/Admin/ResourceController.php), [public ResourceController](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Controllers/ResourceController.php), resources domain models/services and E03.

Library contains categories, files/external links and localized content, plus requests/download records. Files use local private storage; public gated access uses its existing visitor/contact grant flow. Public resource access is not proof of a private student's lesson ownership. Media cover-image storage is a public presentation feature, not a private lesson-PDF authorization layer. No explicit resource-to-student assignment or ordered collection/bookmark relation was found.

## Analytics

**E10.** [AnalyticsService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Analytics/Services/AnalyticsService.php), [AnalyticsController](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Controllers/AnalyticsController.php), [analytics telemetry](C:/Users/e/Herd/ArabicwAbdallah/resources/js/analytics-telemetry.js), [AggregateDailyAnalyticsCommand](C:/Users/e/Herd/ArabicwAbdallah/app/Console/Commands/AggregateDailyAnalyticsCommand.php), [EngagementCounterService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Analytics/Services/EngagementCounterService.php).

First-party visitor/session/event relationships, client UUID deduplication, server country snapshots, attribution and exclusions feed daily/timezone/country rollups and reports. No raw IP is required for this future social feature. Acquisition, session and event country are different dimensions; unknown ZZ is legitimate. Historical aggregates cannot reconstruct period-wide distinct visitors or excluded raw identities after pruning; report their basis. Learning hours currently use actual completed UTC booking duration plus study dwell; preserve this separation from credit quantity.

## Reports / Exports

**E11.** [ExportService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Reporting/Services/ExportService.php), [ReportService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Reporting/Services/ReportService.php), [ReportController](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Controllers/Admin/ReportController.php), [AnalyticsDashboardController](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Controllers/Admin/AnalyticsDashboardController.php), E03/E06/E12.

Route/controller/writer search found ten tabular export endpoint families, representing fifteen datasets because operational reports expose six types. Nine families use ExportService; form answers use direct CSV streaming. CSV safety is centralized for the shared writer; XLSX cell typing depends on producers returning actual PHP numbers. Full export/filter inventory appears below.

## Meeting Links

**E12.** [MeetingLinkService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Booking/Services/MeetingLinkService.php), [MeetingLinkController](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Controllers/Admin/MeetingLinkController.php), [meeting-links view](C:/Users/e/Herd/ArabicwAbdallah/resources/views/admin/bookings/meeting-links.blade.php), MeetingProvider/MeetingRoom and Booking snapshot fields.

Existing provider preference/default selection, room pool, rotating assignments, manual override, snapshot preservation and student reveal timing must stay authoritative. HTTPS validation and duplicate URL hash already exist. No provider hostname policy or manual Open Room button exists in the pool editor. Upcoming-assignment view has status/provider text, while general booking lists do not have the requested assignment badge. Reported production room pool is empty; local room tests do not establish usable production URLs.

## Notifications / Telegram

**E13.** [AdminNotificationService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Administration/Services/AdminNotificationService.php), [NotificationController](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Controllers/Admin/NotificationController.php), [TelegramReadService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Notifications/Services/TelegramReadService.php), [TelegramAutomationService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Notifications/Services/TelegramAutomationService.php), [TelegramDeliveryService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Notifications/Services/TelegramDeliveryService.php), [TelegramRuleCatalog](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Notifications/Services/TelegramRuleCatalog.php).

Admin inbox already persists notifications, supports unread/read/dismiss actions and receives booking/resource/system events. Its read state is shared per notification, not a per-administrator recipient/read relation. Student inbox is absent.

Telegram already has encrypted bots/payloads, destination/rule controls, outbox/dedupe/delivery attempts, read-only commands and configurable digests/thresholds. businessDigest supplies counts for today's/tomorrow's sessions, low/expiring packages, leads, system and analytics; it lacks all proposed task/installment/first-lesson detail. One accepted connectivity test is not worker acceptance. No messages were sent in this audit.

## Audit System

**E14.** [AuditLogService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Audit/Services/AuditLogService.php), [SystemHealthController](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Controllers/Admin/SystemHealthController.php), [audit logs view](C:/Users/e/Herd/ArabicwAbdallah/resources/views/admin/system/audit-logs.blade.php), E01/E04.

There is a Super Admin audit viewer with paginated records. New work is filtering, readable summaries, payload allowlists and correct actor projection, not another event store. Staff and student audit shapes differ; use their explicit actor/entity fields. Raw IP writes occur in audit service and direct auth/reset/reschedule logging; a future removal must cover every producer, historical retention, default session storage and non-database logging, rather than changing one analytics field.

## Forms

**E15.** [FormController](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Controllers/Admin/FormController.php), [FormSubmissionService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Forms/Services/FormSubmissionService.php), [FormAssignmentService](C:/Users/e/Herd/ArabicwAbdallah/app/Domains/Forms/Services/FormAssignmentService.php), Forms models, student form views and E07.

Versioned questions, normalized answers, assignment triggers, autosaved drafts, optimistic revisions and submission revisions exist. Staff view/export currently query all version submissions, including drafts. Assistant-hidden questions stay filtered. Operational completion/progress dashboards can derive from existing assignments and submissions; homework needs its own submission/review lifecycle rather than rebranding intake forms.

Additional matrix evidence:
- **E16:** [shared report-filters](C:/Users/e/Herd/ArabicwAbdallah/resources/views/components/report-filters.blade.php), [shared copy-button](C:/Users/e/Herd/ArabicwAbdallah/resources/views/components/copy-button.blade.php), [clipboard JavaScript](C:/Users/e/Herd/ArabicwAbdallah/resources/js/clipboard.js), [Student Records detail](C:/Users/e/Herd/ArabicwAbdallah/resources/views/admin/students/show.blade.php), [Staff Notes view](C:/Users/e/Herd/ArabicwAbdallah/resources/views/admin/staff-bins.blade.php).
- **E17:** [staff DashboardController](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Controllers/Admin/DashboardController.php), [SearchController](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Controllers/Admin/SearchController.php).
- **E18:** [public layout/footer](C:/Users/e/Herd/ArabicwAbdallah/resources/views/layouts/public.blade.php), [ContentController](C:/Users/e/Herd/ArabicwAbdallah/app/Http/Controllers/Admin/ContentController.php), SocialLink configuration and E10/E11.
- **E19:** [FeatureExpansionTest](C:/Users/e/Herd/ArabicwAbdallah/tests/Feature/FeatureExpansionTest.php), [BinAuthorizationTest](C:/Users/e/Herd/ArabicwAbdallah/tests/Feature/BinAuthorizationTest.php), [MeetingRotationTest](C:/Users/e/Herd/ArabicwAbdallah/tests/Feature/MeetingRotationTest.php), [EngagementCountersTest](C:/Users/e/Herd/ArabicwAbdallah/tests/Feature/EngagementCountersTest.php).
- **E20:** [StudentLedgerTest](C:/Users/e/Herd/ArabicwAbdallah/tests/Feature/StudentLedgerTest.php), [StudentCreditBookingTest](C:/Users/e/Herd/ArabicwAbdallah/tests/Feature/StudentCreditBookingTest.php), [CashierBillingReconciliationTest](C:/Users/e/Herd/ArabicwAbdallah/tests/Feature/CashierBillingReconciliationTest.php), [StudentReschedulingTest](C:/Users/e/Herd/ArabicwAbdallah/tests/Feature/StudentReschedulingTest.php).
- **E21:** [ClientTelemetryIngestionTest](C:/Users/e/Herd/ArabicwAbdallah/tests/Feature/ClientTelemetryIngestionTest.php), [FollowupRemediationTest](C:/Users/e/Herd/ArabicwAbdallah/tests/Feature/FollowupRemediationTest.php), [telemetry JavaScript tests](C:/Users/e/Herd/ArabicwAbdallah/tests/telemetry.test.mjs), [clipboard tests](C:/Users/e/Herd/ArabicwAbdallah/tests/clipboard.test.mjs).
- **E22:** [HOSTINGER_OPERATIONS_HANDOFF.md](C:/Users/e/Herd/ArabicwAbdallah/HOSTINGER_OPERATIONS_HANDOFF.md), [latest feature report](C:/Users/e/Herd/ArabicwAbdallah/FEATURE_EXPANSION_IMPLEMENTATION_REPORT.md), [social counters report](C:/Users/e/Herd/ArabicwAbdallah/SOCIAL_PROOF_COUNTERS_REPORT.md).

# Immediate UI / Remediation Audit

The primary classification applies to the full requested outcome. Risk is the future change's risk, not an assertion that current functionality is broken.

| Candidate | Status | Existing Source of Truth | Existing Files/Classes | Actual Gap | Schema Change? | Risk | Dependencies | Recommended Batch |
|---|---|---|---|---|---|---|---|---|
| R1 Clear Filters | C | Shared filter view, domain-specific queries | E16/E03/E06/E08 | Coherent Apply Filters / Clear Filters presentation | No | LOW | None | B1 |
| R2 Cashier offering filter | D | PRESETS vs StudentPackage purchases | E05/E06 | Stable offering identity not persisted | Yes for reliable provenance; heuristic-only stopgap needs none | MEDIUM | Reviewed historical mapping; feeds R7 | B5 |
| R3 Student billing presentation | C | Existing package, payment/refund and ledger actions | E05/E06/E16 | Selected-package panel/history tabs | No | LOW | Parent/role guards preserved | B1 |
| R4 Copy icon/alignment | C | Shared copy component/clipboard behavior | E16/E19 | Overlapping-rectangles SVG and parent label alignment | No | LOW | Accessibility/unsaved values preserved | B1 |
| R5 Global export consistency | B | Ten families / fifteen datasets, ExportService | E03/E06/E11/E14/E15 | Country filter mismatch; separate unsafe form CSV path; typing/scope contract | Usually no; capacity work may require infrastructure later | MEDIUM | Shared per-area filter/query projections | B2 |
| R6 Footer social analytics | D | SocialLink + deduped event/report pipeline | E10/E11/E18/E21 | Reporting filters, context/content, overview placement, historical coverage | Optional durable social dimensions require additive schema | MEDIUM | Export parity / retention definition | B2 |
| R7 Typed entitlements | D | Generic authoritative package ledger | E04/E05/E07/E13/E20 | Explicit compatible types, snapshots and reviewed backfill | Yes | HIGH | R2 provenance; business mapping | B5 |
| R8 Optional Super Admin TOTP | E | Custom staff guard/password/rate limits/encryption | E01/E02/E14 | Enrollment/challenge/recovery/reauth support absent | Yes; approved mature package also needed | HIGH | Recovery policy; independent of ledger | B6 |
| R9 Lesson materials / recordings | E | Booking + private Resources + Educational Notes | E04/E07/E08/E09/E12/E19 | One-to-many lesson attachments, visibility and student delivery | Yes | MEDIUM | Lesson foundation / ownership | B3 |

## Clear Filters

Cashier, Student Records, Students & Contacts and Staff Notes all use x-report-filters. Clearing already navigates to its bare action URL and removes query filters, sorting and pagination; it does not merely clear dates. The wording “Clear / all dates” is misleading. Shared presentation can change all four consumers, but the query services remain separate because finance/roster/directory/notes scopes differ.

Consumers: StudentBillingController + CashierReportService; StudentController + StudentRecordsQuery; ContactController + DirectoryQuery; StaffBinController's authorized note query. Routes are admin.billing.index, admin.students.index, admin.contacts.index and admin.staff-bins.index. Preserve fixed contextual route scope if later introduced; clearing transient filters must not lose a mandatory parent/permission scope. No new filter framework or schema is justified.

## Cashier Package Filter

CashierReportService validates package_id against student_packages and applies student_package_id to payments, refunds and ledger rows. The picker lists individual purchases, so “Name #ID” is expected from its present semantics.

Current offerings, from PRESETS, are Diagnostic & Roadmap (1 × 60 minutes), Foundation Coaching Track (8 × 120), Fluency Immersion Track (12 × 120), Pay-As-You-Go Maintenance (1 × 120), and Advanced Conversational (1 × 60). The final two are unlisted business presets. These are code definitions, not proof of today's public SessionType configuration.

Smallest reliable future change: retain preset/definition key on every new purchase, preserve immutable purchase terms, add a reviewed mapping for historical purchases, and filter by that key through CashierReportService for both screen and exports. A fully editable catalog needs versioned definitions; a stable key referencing current code presets can precede that catalog. Do not add an unrelated package-name taxonomy or use the example prompt names as products. Null/unmapped/custom purchases need a visible custom/unclassified option.

A no-schema name/quantity/currency filter could be a clearly labeled historical grouping, but cannot honestly identify canonical products; renamed/customized or coincidentally matching purchases are ambiguous. Prefer the schema-backed path with R7 rather than shipping a falsely authoritative picker.

## Student Records Billing UX

Student Records repeats package payment, method/reference/notes, credit adjustment, expiry, history/refund and ledger controls for every package. The existing endpoints already accept explicit student/package/payment parents. Render compact summaries, one selected package, one management panel with Overview/Payments/Credits/Expiration/History, and a link to Cashier with student_id set. Keep independent packages independent in storage.

Reuse POST admin.students.payments.store, admin.students.refunds.store, admin.students.credits.adjust and admin.students.packages.validity, with current parent ownership, idempotency and audit behavior. Cashier retains preset purchase creation, diagnostic reconciliation and manual refund-forfeiture/courtesy controls; Student Records exposes signed credit adjustments and expiry management. Preserve the union of capabilities without silently adding mutations to assistants. A selected-package view may use existing Blade/Alpine presentation; no new financial calculation or Livewire backend is necessary.

## Copy Icon

copy-button centralizes SVG, button type, data attributes and accessible feedback. clipboard.js copies current unsaved field values and formatted identity details, with a fallback. Changing the component SVG updates current uses. The Student Records heading and labels contain inline buttons but do not establish an explicit shared flex/baseline wrapper; icon replacement alone does not guarantee layout alignment. Update the parent presentation or introduce a reusable label/control wrapper as well. Preserve type=button, keyboard behavior, aria labels/live status, focus and mobile target size. Existing clipboard tests are regression evidence; no behavior rewrite is justified.

## Global Export Consistency

Complete controller/route/writer inventory: **10 tabular endpoint families / 15 datasets**. The six operational report variants are enumerated individually below. Format selection is not a separate dataset. No Staff Notes, resource catalog, generic audit-log or booking-calendar CSV endpoint was found beyond these. Backup archives, uploaded resource/media downloads and public booking ICS are downloads but not tabular report datasets; keep their own ownership/recovery controls.

All ExportService datasets use its UTF-8/BOM CSV sanitizer and explicit XLSX cell typing. Actual int/float values stay numeric; string numbers, percentages with “%”, composite money details and identifiers remain strings. Counts/money must be deliberately typed by producers, while phone/reference strings must stay strings. Do not convert every numeric-looking string into a number.

| Endpoint / dataset | Screen query and export query | Filters / full scope | Pagination / dates / timezone | Authorization and sensitive fields |
|---|---|---|---|---|
| admin.billing.export / financial ledger | CashierReportService::rows and filters, same preview/export | student_id, date_from/to, transaction_type, package_id, payment_method, package_status | Preview 100 rows; full lazy/chunked export. Inclusive business-date inputs converted to UTC boundaries; output business timezone | SA/Admin. Name/email and financial references intentional; credit quantity separate from money; excludes DOB/auth secrets/internal student notes |
| admin.billing.reconcile.export | BillingReconciliationService::reconcile shared | No meaningful current filter; full discrepancy diagnostic | Materialized diagnostic collections; no period filter because integrity is current ledger-wide | SA/Admin. Student names/IDs and financial discrepancy details; adding period filters could hide integrity problems |
| admin.students.export | StudentRecordsQuery::filters/query shared | q, status, package name, credits, expiry_from/to, timezone, session_status, joined_from/to | Screen 25; export lazy100 all matches. Joined business dates/UTC conversion; expiry business dates | SA/Admin export, broader staff read limited separately. Name/email/phone intentionally; no DOB/internal notes/verification secrets |
| admin.contacts.export | DirectoryQuery::filters/query shared | search, population, resource_id, category_id, booking_status, activity_from/to | Screen 20; export lazy250 all matches; business activity boundaries; business output | SA/Admin. Canonical person projection; no DOB/student internal notes; explicit contact PII remains |
| admin.forms.export | FormController version-scoped queries, duplicated screen/export answer projection | version_id belongs to form; assistant-visible question selection | Screen 50; export chunk200 all matches. Screen descending vs export ascending; UTC submitted timestamp; no period filter | Staff including Assistant with question restrictions. Both include drafts today; answers are potentially sensitive. CSV only; direct writer has no shared BOM/sanitized headers/XLSX |
| admin.analytics.overview.export | AnalyticsService reportingMetrics plus ReportService traffic, whatsappReport and goalReport | Preset range: today, 7d, 30d/default, month, 90d | Screen summary and export aggregates, no paging; current business timezone boundaries | SA/Admin. Non-PII metrics/dimension strings; mixed sections are an overview metrics dataset, not an export of every visible dashboard table |
| admin.analytics.countries.export | Screen reportingCountries → country-name/search/sort; export reportingCountries without those steps | Screen range, search, sort, dir; export only range/format | Both full aggregate collections/no page. Same preset business-date window, but export loses screen search/sort | SA/Admin. Non-PII. Export omits screen CTA/completed/resource columns. **Confirmed filter/scope mismatch** |
| admin.analytics.sections.export | AnalyticsService::sectionReport shared | Preset range only | All section rows; business window, unavailable rates retained rather than fabricated | SA/Admin. No student identity; no extra search filter needed for tiny fixed section list |
| admin.reports.export / traffic | ReportService::getTrafficReport shared | type=traffic, range/custom start/end, source | Full report collection and totals; business-date window converted UTC; no table pagination | SA/Admin. Counts/source attribution, no contact identity. Historic daily-unique sums explicitly labeled |
| admin.reports.export / bookings | ReportService::getBookingsReport shared | type=bookings, range/custom start/end, status | Full matching report; business date scope and local timestamps/UTC context | SA/Admin. Student/contact name/email and booking details are intentional staff PII; scope is report-specific, not current calendar-view filters |
| admin.reports.export / resources | ReportService::getResourcesReport shared | type=resources, range/custom start/end | Full resource report, no paging; business date scope | SA/Admin. Aggregate resource performance, no intake-answer export |
| admin.reports.export / social | ReportService::getSocialReport shared | type=social, range/custom start/end; no platform/placement/country/page/source/campaign filter API | Full grouped collection + totals; business date grouping; raw-event retention limits | SA/Admin. Hashed/distinct visitor metrics and safe dimensions; no names/raw IP; context/content absent |
| admin.reports.export / events | ReportService::getEventsReport shared | type=events, range/custom start/end, event_name | Full aggregates/no pagination; business date scope. “Raw Events Log” actually groups counts, not individual raw records | SA/Admin. Event/page/source aggregate; do not promise raw payload export |
| admin.reports.export / campaigns | ReportService::getCampaignContentReport shared | type=campaigns, range/custom start/end, campaign | Full grouped report; business date scope | SA/Admin. UTM/content attribution; no contact identity |
| admin.health.maintenance-visitors.export | SystemHealthController maintenanceVisitor cursor vs health summary/recent rows | No current filters; all retained visits | Cursor ordered by id; export raw UTC timestamps. Health summary/top-country display is a different summarized view | SA-only health group. Visitor ID/URL/referrer; legacy IP column exists but new collection writes null. Export must not expose any retained raw IP |

ReportController accepts contextual query parameters for source/status/event_name/campaign in code, but current operational UI mainly exposes type/period; do not call hidden query support a completed filter UI. Custom date parsing needs shared validation and clear range semantics. AnalyticsDashboardController only implements presets; adding custom dates requires a deliberate contract, not copying ReportController assumptions.

Confirmed repairs and justified improvements:
1. Use one country projection/filter/sort service for UI and export, preserve every scope parameter in links and include the same intended columns.
2. Move form exports to ExportService and shared version/visibility/status scope. Existing direct regex protects dangerous first characters in answers, but misses leading-whitespace formulas and does not sanitize question-label headers. Do not claim parity with the shared sanitizer.
3. Decide draft-answer visibility explicitly; maintain consistent read/export policy and safe assistant exclusions. Add useful version/status/student/date filters to submissions rather than an unrelated forms system.
4. Add social dimension filters and retain page/placement/context/content consistently. Preserve export scope.
5. Audit numbers per producer: Cashier uses numeric money/credit values; roster/directory cast counts; reconciliation IDs/composite details are strings; analytics percentages are strings. Native numeric rates need documented percent units if changed.
6. Document XLSX scale honestly. Chunked input is not bounded workbook memory; use limits/CSV fallback or a separately approved scalable writer for genuinely large workloads. Changing dependencies is a future approval item.
7. Maintenance traffic can benefit from date/country filters; reconciliation can benefit from category/student search while preserving an unfiltered integrity total. Do not add filters to tiny fixed section summaries merely for visual consistency.
8. Do not export DOB, credentials, TOTP/recovery data, private staff-only notes or sensitive draft questions as a side effect of unifying writers.

Existing FeatureExpansionTest and reporting tests cover several full-scope, role and numeric cases, not a universal parity guarantee. Future regression cases are listed later.

## Footer Social Analytics

The configured local/footer platforms are YouTube, TikTok, Instagram, Telegram and WhatsApp; previous deployment report also records five social links. Current code additionally allows Facebook, LinkedIn, X and custom links. This audit did not query live configuration.

| Pipeline step | Actual current state |
|---|---|
| Render/configuration | Enabled SocialLink entries render through public footer with data-social-platform; existing safe URL/configuration behavior retained |
| Physical click | analytics-telemetry.js chooses exactly one semantic event: WhatsApp → whatsapp_clicked; Telegram → telegram_clicked; other platforms → social_link_clicked |
| Metadata | platform, placement=footer_social, target URL, source page, context=public, language plus visitor attribution. Floating WhatsApp uses placement=floating_cta and public/portal context |
| Listener interaction | Initialization guard prevents repeat setup. Public layout's legacy outbound handler skips data-social-platform/data-whatsapp-cta elements; it does not add outbound_link_clicked for the same marked footer click |
| Delivery/navigation | Queued UUID events use beacon/fetch handling. Outbound navigation is not prevented; telemetry failure cannot block the link |
| Validation/persistence | AnalyticsController allowlists events/metadata and bounds payloads; AnalyticsService uses client event UUID dedupe and visitor/session relationships. Country snapshot is server-derived; existing internal/bot/synthetic exclusions apply |
| General social reporting | ReportService groups by platform, placement, event, page, business date, language, country, source, medium and campaign. Counts clicks and distinct visitors; separate period distinct total avoids adding row uniques |
| UI / export | Operational Social & Messaging report and CSV/XLSX use the same report. Platform/placement rows exist; corresponding dimension filters do not. Context and UTM content are omitted here |
| Overview WhatsApp consumer | AnalyticsService::whatsappReport groups date/country/source/medium/campaign/content/context/language but drops page/placement. Footer/floating events with matching dimensions are merged in this consumer |
| Metric naming | General total includes outbound_link_clicked as well as social events; “Total Messaging Clicks” is misleading for outbound game/content links |
| Durable history | Existing overall daily/country aggregates do not retain every platform/placement/page/context dimension. Raw-event pruning can remove detailed social history after 90 days when rollups exist; do not invent historical distinct totals from aggregate counts |

All five configured platforms have a traced event path. There is **no evidence of a current marked-footer double count**: JavaScript tests exercise the distinct semantic event path; ClientTelemetryIngestionTest covers one physical click counted once; FollowupRemediationTest covers distinct WhatsApp placements at ingestion. Those tests do not establish all downstream views distinguish placement or support filters. New tests must cover optional/custom platforms, overview grouping, disabled/reordered links, UI/export scope and pruned history.

Safest repair: extend the existing social read model and consumer filters; use platform + placement + context + page + attribution consistently; label general outbound totals separately; keep raw event identity dedupe. If long-term detailed social reporting is required, add durable dimensions before pruning, with an honest daily-unique versus period-unique definition. Do not emit a second generic social event just to populate another dashboard.

# Typed Credit / Entitlement Audit

## Current Credit Semantics

One generic ledger unit is consumed at a returning student's booking confirmation. Package grants and courtesy adjustments are signed integers; usable balance is derived from rows and package eligibility, with earliest-expiring eligible package selection. There is no conversion, typed bucket or session compatibility association. E04/E05/E20 are the authoritative paths.

Package-instance and money provenance already exists and must be retained. The diagnostic discount eligibility calculation is a separate monetary credit/discount concept, based on historical paid/refunded diagnostic transactions and completed lessons; it must not be confused with session entitlements.

## Current Credit Assumptions

| Current assumption / path | Evidence | Required typed behavior |
|---|---|---|
| Initial allocation is one quantity | StudentPackage.total_sessions_allocated; createPackage adds package_grant | Persist allocation by entitlement type; total must become an explicitly labeled display aggregate, not compatibility |
| Package choice needs student ID only | selectAndLockEligiblePackageForBooking(int studentId) | Accept authoritative required type/quantity, filter compatible buckets before earliest-expiry ordering |
| One booking consumes -1 | consumeForBooking / StudentBookingService | Snapshot required type and units; keep exactly-once atomic consumption with booking |
| Courtesy/signed adjustment is untyped | courtesy_adjustment rows / adjustCredits / Cashier grant | Require explicit type tied to a valid package allocation; never add a global generic adjustment |
| Cancellation restores quantity/package | restoreCancellation finds original session_consumed | Restore exact original entitlement bucket/type/units and provenance, including old-booking snapshot |
| Refund forfeiture compares aggregate remaining credits | refundPayment locks package ledger, adds expiration_forfeit | Explicit typed forfeiture allocation; money refund remains independent of entitlement policy |
| Expiry is package-specific | summary/isPackageEligible / expiry controller/history | Apply expiry to every package allocation; preserve business-date boundaries and append-only history |
| Reschedule leaves original debit | RescheduleService | Preserve snapshot; revalidate if session/type changes. Reject incompatible change unless an explicit atomic replacement policy exists |
| Complete/no-show updates status only | Admin BookingController | No second debit. Preserve consumed type; delivered hours use actual UTC duration |
| Public/admin BookingService does not automatically use package selector | Separate creation paths; no consumeForBooking call | Define which paths are paid-package bookings; compatibility checks on every consuming path; diagnostic/no-charge behavior explicit |
| Roster/portal/Cashier show one balance | StudentRecordsQuery/controller, DashboardController, summary | Display per-type balances and package provenance. Avoid one misleading usable-credit total |
| Reminder thresholds/templates use remaining_credits | TelegramReadService / rule templates | Threshold and label per type or explicit package allocation; retain old messages only with clear legacy labels |
| Exports/reconciliation count generic quantities | CashierReportService, BillingReconciliationService | Typed columns/buckets; reconcile by package/type, not summed quantity only |
| Preset identification uses name/quantity/USD | presetForPackage | Stable definition key/version on purchase; historical heuristic is evidence needing approval, not an automatic type assignment |
| Preset minutes imply compatibility | PRESETS plus SessionType duration/availability overrides | SessionType requires an explicit entitlement type; duration remains scheduling/delivery data |

Broad searches across app, views, migrations and tests for credit/allocated/available/remaining/consumed/restored/courtesy/balance were used, including notifications, reporting and portal consumers. Also inspect old App\Services/App\Models wrappers when implementing: BookingService's wrapper extends the domain service, so do not create competing methods in both layers.

## Required Domain Changes

Proposed domain concepts, **not existing tables**:

1. EntitlementType: immutable stable code and display label, active-for-new-sales state. Minutes may be explanatory metadata, never the compatibility algorithm.
2. Package definition identity: retain current preset keys first, or introduce versioned PackageDefinition if admins need editable offerings. Preserve purchased name, price/discount/currency and expiry policy snapshots.
3. Package allocations: one-to-many StudentPackageEntitlement rows with package_id, entitlement_type_id and granted quantity. This supports future mixed-type packages without another migration of meaning.
4. Ledger: attach each row to the authoritative allocation/type. Require consistent student/package ownership and snapshot type/quantity for booking debits/restores. Existing enum/unique rules need deliberate extension for later transfers, not ad-hoc misuse.
5. SessionType: explicit required entitlement reference/units for new package-funded bookings. Optional/null only for explicitly non-entitlement/diagnostic legacy behavior during staged migration.
6. Booking snapshot: required entitlement code/type/units and consumed allocation relation recorded at confirmation. Later edits to current SessionType must not change old booking rights.
7. Service selection: one compatibility resolver in the current booking/ledger transaction, retaining calendar/student/package locks and deterministic package ordering.
8. Money: PaymentRecord/PaymentRefund remain authoritative financial history. Avoid converting prices, refunds or diagnostic discounts into session quantities automatically.

Recommended invariants: one compatible allocation per debit unless a future mixed-source rule is expressly designed; no automatic 2×1-hour→1×2-hour conversion; cancellation exactly reverses the original type; package-specific expiry; same idempotency replay cannot consume another type; active current definitions cannot silently reinterpret historical rows; no negative balance in any bucket.

## Historical Mapping

Available evidence is incomplete by design:
- PRESETS has durations and quantities, but StudentPackage does not persist its key/duration or definition version.
- SessionType has duration_minutes, but definition edits and AvailabilityRule duration overrides mean scheduled minutes are not a historical entitlement contract.
- Booking UTC start/end is good delivered/scheduled-duration evidence, not proof of purchased credit rights.
- Consumed ledger rows retain booking and package provenance; grants/courtesy/unlinked adjustments lack a type.
- Current local custom package data demonstrates that not every purchase can be assigned by preset matching. Local active types include 60-minute diagnostic/QA definitions; this is not a production catalog inventory.

Future mapping workflow:
1. Inventory distinct purchases, original sales definitions, grant/adjustment rows and linked bookings using read-only reports. Capture immutable row counts/checksums and balances before migration.
2. Approve a versioned mapping manifest with exact package IDs/definition evidence, required session types and exception reasons. Exact name/quantity/currency matches may support a proposed map but cannot establish it alone.
3. Flag contradictory or missing evidence. Keep unresolved records explicitly legacy/unclassified and unavailable for new typed spending until reviewed; preserve historical visibility and rights, with a support route for affected students.
4. Preserve all monetary values, timestamps, ownership, idempotency keys, booking provenance and original signed quantities. Populate additive type references through a controlled, auditable backfill; do not rewrite financial events.
5. For ambiguous historical bookings, preserve recorded consumption as legacy rather than assigning a guessed type from minutes. Cancellation of legacy consumption must still be reversible through its original allocation.
6. Reconcile per-package and per-type grant/consumption/restore/adjustment totals. Old aggregate totals should match sums of explicitly mapped plus unresolved buckets.
7. Require zero unreviewed records in the new-spend path before switching consumers. New sales/booking requirements and all read/write surfaces move together.

No production mapping was attempted. A blanket automatic mapping would violate the request.

## Migration Risk

**HIGH.** Additive columns alone are insufficient: the semantic cutover must include selectors, portal balances, adjustments, refunds, expiry, restoration, public/student/admin paths, messages, reports and fixtures. A mixed state where one service still spends generic credits is unacceptable.

Use expand → reviewed backfill → reconcile → coordinated application cutover → constraints. Rehearse on a protected database copy, test interrupted/replayed backfill and rollback before any production plan. Do not enforce NOT NULL on unresolved history or drop generic fields first. The old application cannot safely be rolled back after new typed transactions unless its behavior is blocked or a compatibility layer prevents generic spending; retain backups and a forward-fix plan, not a promise of effortless rollback.

## Affected Screens

Student Records list/detail; selected-package billing panel; Cashier purchase/payment/refund/adjustment/expiry; roster/directory package displays; student dashboard/history/booking/slot selection; public/admin booking where package funding is permitted; session and future package configuration; reconciliation; financial/roster exports; operations dashboard; expiry/low-credit cards; Telegram rule filters/templates/history previews. Every remaining-credit label must name its unit/type or explicitly indicate an aggregate.

## Affected Services

StudentLedgerService; StudentBookingService; BookingService (public/admin policy integration); CancellationService; RescheduleService; StudentBillingController; StudentController/StudentRecordsQuery; student DashboardController; CashierReportService; BillingReconciliationService; TelegramReadService/TelegramBusinessEvents; session-sync actions/services; StudentMergeService; StudentPrivacyService; teaching/read-model projections.

EngagementCounterService already calculates delivered hours from completed booking UTC endpoints. Preserve that behavior; update labels/fixtures if necessary without replacing it with “credits × hours”.

## Required Tests

Extend E20 and booking concurrency tests rather than writing a mirror implementation:
- Compatible and incompatible bookings; simultaneous last-credit attempts in each type; deterministic earliest compatible expiry; expired/missing-expiry policies.
- Exact-type cancellation restoration, repeat/reordered callbacks, legacy restore and no extra completion/no-show debit.
- Reschedule same type, definition changed since booking, incompatible replacement, rollback on failed availability/type validation.
- Typed positive/negative adjustments; bounded typed refund forfeiture; monetary refunds unchanged; no cross-package/student bucket injection.
- Mixed-type grants, zero/negative guards, snapshot immutability, idempotency collision with different payload/type.
- Historical manifest ambiguity, no guessing, replay/resume/reconcile and preservation of money/history.
- Student ownership/assistant finance denial; per-type portal/list/export/message parity.
- Merge/privacy handling of new allocations without losing consumed-booking provenance.
- Actual delivered UTC hours, DST/override duration, canceled/no-show exclusion remain independent of type.
- Real MariaDB row-lock races, not only SQLite or sequential test calls.

# Super Admin TOTP 2FA Audit

Classification R8: **E; HIGH risk**. Reuse the existing web guard, Administrator model, suspended-account check, rate limiting and audit boundary. No installed Fortify, OTP, QR or recovery-code package was found in composer metadata/source, and Laravel's encryption support alone is not a full 2FA implementation.

A mature implementation is preferable to hand-written TOTP cryptography. Laravel Fortify documents TOTP, enrollment confirmation, QR provisioning, password confirmation, recovery codes and challenges; its current 1.x manifest permits Illuminate 13. These are **future dependency options**, not installed capabilities or a package installation request. [Official Fortify documentation](https://laravel.com/framework/docs/13.x/fortify), [official package manifest](https://github.com/laravel/fortify/blob/1.x/composer.json).

Integration recommendation: approve a pinned compatible dependency set, then use Fortify's maintained actions/provider and a deliberately scoped staff authentication pipeline. Resolve route ownership conflicts with the current AuthController; do not publish a second generic login/registration system. Keep Admin/Assistant and student login behavior unchanged initially. Review transitive packages before approval; “enable TOTP” should not turn on unrelated authentication features.

Suggested additive Administrator security fields: encrypted two_factor_secret, encrypted recovery-code state, two_factor_confirmed_at; a separate one-to-one security record is also valid if existing conventions favor isolation. Existing encrypted Telegram token/payload casts demonstrate encryption infrastructure, not OTP support. Use mature package storage/verification behavior; prove recovery codes are consumed atomically once. Add minimal audit events for enrollment/confirmation/disable/regeneration/recovery use, never codes/QR/manual secret/payload.

Flow:
1. Only the authenticated Super Admin can open own security settings; require recent password reauthentication.
2. Generate a pending secret and show QR/manual key in an uncached authenticated response. Secret remains pending until a valid code is confirmed.
3. Enable only after confirmation; show recovery codes once and allow strongly verified regeneration.
4. After password success for an enrolled Super Admin, keep a short-lived pending identity; no privileged guard/session before challenge passes. Preserve rate/suspension checks across both stages.
5. Limit failed challenges, expire pending sessions, rotate session ID after success and validate safe intended destinations. Remember-me cannot bypass enabled 2FA.
6. Disabling/regenerating requires password plus valid second factor/recovery under the approved recovery policy. Password reset alone must not silently disable 2FA.
7. Existing accounts deploy disabled/null; no bulk enrollment or forced lockout. Define an offline, verified Super Admin recovery procedure with recorded actor/reason; no weak emailed “disable 2FA” shortcut.

TOTP uses wall-clock time, not the business calendar timezone. Test accepted clock window/replay behavior against the selected library, concurrent recovery use, wrong/expired codes, challenge bypass through all staff routes, pending session expiry, logout/reset/suspension, disabled SA, unaffected other roles and enrollment rollback. No Hostinger cron/queue/mail prerequisite exists for offline TOTP; correct server clock and encryption-key continuity do.

# Lesson Materials / Recording Links Audit

Classification R9: **E; MEDIUM risk**; private delivery/visibility is a sensitive boundary. E04/E07/E08/E09 contain reusable booking, student notes and library/private file flows, but no equivalent lesson material relation or recording association exists.

Best fit: the future Lesson Workspace (#1), presented within the existing Booking/Student portal. Add a one-to-many LessonMaterial relation with booking_id, creator, kind (private file / existing resource / external link / recording), title/description, resource_id OR private disk/path OR validated URL, sort order and explicit student_visible. Enforce mutually valid type fields. A recording subtype in the same relation avoids a competing attachment system; optional future expires_at can be added only with a real rule.

Start on completed/past lessons, Super Admin/tutor writes, explicit publishing, and own-student reads. Admin/Assistant access should follow established student educational policies; do not grant publishing/deletion merely because they can view a booking. New policies must resolve booking → canonical student and verify parent ownership on list/detail/download/update/delete. Deleted/merged/suspended identities follow current privacy/session services.

PDF delivery: private local disk with opaque file names, validated size/type/content, safe download headers and no user-controlled paths. Resolve paths from authorized material records; never accept path/query ownership or expose predictable public storage links. Resource Library references reuse the file rather than duplicate it, but private lesson authorization must run before delivery. Its public email/visitor gate is insufficient for Student A versus Student B isolation.

External materials/recordings remain manually supplied HTTPS links, with safe schemes/no credential-bearing URL and explicit per-lesson association. Do not fetch, join, scrape or sync private meeting recordings. Host-provider sharing permissions remain external; clearly document that a pasted provider URL may itself be shareable, so the application can control disclosure but cannot revoke someone else's provider access.

Student completed-history cards render only visible associated materials. Show recording only when a published recording link exists; no universal “Recording unavailable” block. Staff-only preparation remains separate from student-visible content. Do not use public footer social telemetry for private lesson content. If opened/downloaded status is needed, use authorized assignment activity with a privacy/retention policy.

Tests: own/foreign student IDs, invisible/deleted materials, suspended/merged accounts, assistant access, unauthorized staff mutations, traversal/file MIME/HTML hazards, resource replacement behavior, URL escaping, absent recording UI, multiple materials/ordering, audit payload minimization and privacy export/redaction. No scheduler, meeting provider API or automatic recording dependency is required for the manual first version.

# Ideas Vault Feature Matrix

Every retained number is represented exactly once below. Status applies to the full retained proposal; a working subset is documented in “Existing Source of Truth” and “Actual Gap.” E references resolve to the cited files/classes in Current Architecture Map. These tables describe future work; no addition was implemented.

| Code | Primary classification | Count across all 79 candidates |
|---|---|---:|
| A | ALREADY COMPLETE | 0 |
| B | EXISTS BUT NEEDS REPAIR | 1 |
| C | EXISTS BUT NEEDS UX ENHANCEMENT | 9 |
| D | PARTIALLY IMPLEMENTED — EXTEND EXISTING ARCHITECTURE | 16 |
| E | REUSABLE FOUNDATION EXISTS — FEATURE ITSELF MISSING | 34 |
| F | GENUINELY MISSING | 5 |
| G | SHOULD MERGE WITH ANOTHER REQUEST | 8 |
| H | DEFER UNTIL PREREQUISITE | 6 |
| I | NOT RECOMMENDED / REDUNDANT | 0 |
| Total | Nine audit areas + 70 Vault items | 79 |

A and I are zero: every retained proposal asks for some expanded behavior, and no whole retained proposal is rejected. This does not mean working foundations are absent. G means the outcome belongs to another feature, not that its business purpose is invalid. H identifies substantive dependencies; it does not prohibit independent prerequisite discovery.

Batch codes: B1 shared presentation; B2 reporting/security consistency; B3 lesson materials/workspace; B4 operational read models; B5 offering identity/typed entitlements; B6 optional TOTP; B7 financial lifecycle; B8 scheduling/policies; B9 structured teaching; B10 preferences/delegation/safe bulk. H-OPS is the unchanged deferred Hostinger handoff, not an implementation batch executed here.

| Feature / business purpose | Status | Existing Source of Truth | Existing Files/Classes | Actual Gap / reuse path | Schema Change? | Risk | Dependencies | Recommended Batch |
|---|---|---|---|---|---|---|---|---|
| #1 Per-session Lesson Workspace — organize preparation, delivery and follow-up | E | Booking / StudentBin / Resources | E04/E07/E08/E09 | No structured lesson workspace; add booking-owned preparation and materials, reuse notes | Optional workspace + material relations | MEDIUM | R9, visibility contract | B3 |
| #2 Homework / Assignments — assign, submit and review work | F | No assignment/submission/review domain; Forms only overlap | E08/E09/E15 | Add learning assignment lifecycle; intake forms are not homework | Assignments, submissions, reviews/revisions | MEDIUM | #1, #35, explicit visibility | B9 |
| #3 Learning Plan / Progress — goals and tutor-reviewed milestones | F | No plan/milestone model; notes/forms are inputs | E08/E15 | Add structured goals, review state and evidence; no fabricated scoring | Plans, goals/milestones and review evidence | MEDIUM | #1, #2, progress definitions | B9 |
| #4 Unified Student Timeline — review one student's history | E | Canonical Student plus booking, finance, forms and audit events | E03/E04/E05/E14/E15 | Build authorized read projection; no duplicate event store | No initially; indexes if measurements justify | MEDIUM | Shared actor/event projection | B4 |
| #5 Recurring Lesson Series — schedule repeated lessons safely | H | Authoritative availability/booking locks; no series | E04/E05/E12/E20 | Series intent, occurrence idempotency and conflict handling absent | Series and occurrence relation | HIGH | R7, #6, #46, partial-success policy | B8 |
| #6 Cancellation / No-show Policy Engine — formalize outcomes and exceptions | D | Current cutoff/status/restore services and BookingEvents | E04/E05/E20 | Current rules exist; versioned actor/timing/credit outcome policy absent | Policy versions/outcome snapshots if rules expand | HIGH | R7, #21, approved restoration policy | B8 |
| #7 Waitlist / Slot Interest — record demand and offer released slots | F | No waitlist model; slots/holds provide infrastructure | E04/E13 | Add preference/consent/offer lifecycle; offers must not promise unavailable slots | Waitlist entries and offer history | MEDIUM | #6, authoritative holds; operations for auto alerts | B8 |
| #9 Installment Schedule — distinguish due dates from partial payments | E | StudentPackage + append-only PaymentRecord/Refund | E05/E06 | Partial payments exist; due schedule/payment allocation absent | Due items and payment/refund allocations | HIGH | R2/R7 identity; allocation/overpayment policy | B7 |
| #10 Receipts / Statements — explain payments and balances | E | Cashier canonical transactions/export writer | E05/E06/E11 | Add authorized statement projection and immutable receipt references; no provider charge | No for read statement; receipt snapshot/number relation if issued | MEDIUM | #9 optional, currency/date contract | B7 |
| #12 View as Student — safe read-only portal preview | E | Student portal projection and staff authorization | E01/E02/E07 | Add dedicated staff preview, deny all actions and avoid replacing session identity | No initially | HIGH | Shared visible projection, exclusion/audit policy | B3 |
| #13 Parent / Guardian — managed delegated student access | F | No relationship or delegated portal identity | E01/E02/E03 | Consent, scopes, guardian identity/access and revocation absent | Guardian identity/relationship/consent | HIGH | Student/child privacy and access policy | B10 |
| #15 Student Notification Center — own actionable notices | E | Admin inbox/Telegram events, not student recipients | E07/E13 | Add student-recipient read state and visibility-safe payloads | Student notifications/recipient state | MEDIUM | #24/#1 events; external automation deferred | B4 |
| #16 Staff Tasks — track follow-up ownership and due work | F | Staff notes/inbox are not task lifecycle | E08/E13/E17 | Add assignee, due date, status and parent refs | Tasks and minimal lifecycle history | MEDIUM | Staff ownership; #31 read projection | B4 |
| #17 Pinned Student Alerts — surface staff operational context | E | Student internal/educational notes, no pin/expiry behavior | E03/E08 | Add staff-only pin/urgency/expiry metadata and clear authorship | Optional note pin metadata or focused alert relation | MEDIUM | #31, staff/student visibility boundary | B4 |
| #18 Command Palette / Global Quick Actions — navigate and act quickly | G | Existing restricted search and contextual route actions | E16/E17 | Merge with #38 search and #22 action registry; avoid a new permission engine | Reuse #38/#22; preferences optional | MEDIUM | #38, #22 | B10 |
| #19 Saved Filters / Views — remember staff query scopes | E | Shared filter UI and validated domain query params | E03/E06/E16 | Add per-admin named whitelisted filters; reauthorize on use | Scoped saved-view preferences | LOW | R1/R5 stable filter contracts | B10 |
| #20 Business Data Quality Center — review repair candidates safely | D | Billing reconciliation, contact duplicates, identity and meeting checks | E03/E06/E12/E17 | Unify read-only diagnostic cards/drilldowns; never auto-repair | No initially | MEDIUM | R7 mapping, #43, policy-safe repair links | B4 |
| #21 Standard Reason Codes — consistent operational reasons | E | Free-text ledger/refund/cancel/suspension audit reasons | E04/E05/E14 | Shared taxonomy plus explanation; preserve historical text | Nullable reason code + version/definition if configurable | MEDIUM | Domain-specific allowed outcomes; not generic mutation API | B2 |
| #22 Contextual One-click Student Actions — reduce navigation | E | Named booking/Cashier/forms/notes endpoints | E02/E04/E05/E15/E16 | Shared authorized links/actions; confirmation for mutations | No | LOW | R3, no assistant financial expansion | B1 |
| #23 Admin Micro-UX — improve clarity and feedback | C | Shared form/filter/copy components and current workflows | E16/E19 | Consistent resets, alignment, loading/feedback and compact detail | No | LOW | R1/R3/R4 | B1 |
| #24 Student Next Best Action — prioritize useful next steps | D | Portal already shows bookings, packages/forms | E07/E15 | Add deterministic priority projection and accurate link/eligibility | No initially | MEDIUM | #63/#69/#1; R7 when typed | B4 |
| #25 Post-lesson Feedback — capture private lesson response | E | Versioned Forms plus Booking ownership | E04/E15 | Add completed-lesson assignment/link; keep feedback separate from public reviews | Optional booking-linked form assignment/submission context | MEDIUM | #1, form privacy/trigger policy | B9 |
| #26 Renewal Workflow — create new package with history | E | Existing package purchase, expiry and ledger | E05/E06 | Add explicit renewal lineage and suggestion flow; no overwrite or auto-charge | Previous/renewal package link plus lifecycle evidence | HIGH | R2/R7, #9 if scheduled payments | B7 |
| #27 Progress Summary / Learning Report — share supported learning evidence | H | Educational notes, completed lessons, resource/form inputs | E04/E08/E09/E15 | No structured progress evidence yet; printable report after #3 | Reuse #3; optional published report snapshots | MEDIUM | #1/#3/#35, tutor approval | B9 |
| #29 Audit Log Viewer — filter and explain existing history | C | Super Admin audit page and AuditLogService | E14/E02 | Viewer exists; actor/entity/action/date filters and safe summaries absent | No initially | MEDIUM | Raw-IP/payload repair; actor normalization | B2 |
| #31 Today / Operations Dashboard — prioritize today's urgent work | D | Existing staff dashboard next/today bookings, cancellations and warnings | E17/E03/E04/E13 | Add canonical missing-form/credit/room/due-task drilldowns | No; consumes other feature relations | MEDIUM | #63/#69/#16/#9 progressively | B4 |
| #34 Pronunciation / Error Log — review recurring learning issues | E | Educational notes, no structured longitudinal issue state | E08 | Add category, observations and active/resolved review evidence | Learning issues and dated observations | MEDIUM | #1/#3 educational foundation | B9 |
| #35 Resource Assignment — recommend content to a student/lesson | E | Existing Resource, request/download and Student/Booking | E09/E03/E04 | Explicit assignment/visibility/activity relation absent; reuse file | Student resource assignments + optional booking ref | MEDIUM | #1/R9 private access projection | B3 |
| #36 Collections / Learning Paths — order existing learning content | E | Resource categories/library exist, no sequences | E09 | Add curated ordered collection items; assign via #35 | Collections, ordered items, assignment reference | MEDIUM | #35; no full LMS engine | B9 |
| #37 Student Bookmarks — personal references to content | E | Owned portal content exists, no favorites relation | E07/E08/E09 | Student-scoped reference list with current authorization on retrieval | Bookmarks with target/type and unique ownership | LOW | #35/#2 for those content targets | B10 |
| #38 Search Everything — search across permitted domains | D | SearchController searches limited contacts/bookings/resources | E17/E02/E03 | Expand authorized student/payment-reference/notes projections; assistants remain restricted | No initially; measured indexes optional | MEDIUM | Per-domain authorization, #22 | B4 |
| #39 Recently Viewed — staff navigation history | E | Administrator identity and record routes, no personal history | E01/E17 | Store minimal record refs/time, reauthorize and bound retention | Per-admin recent-reference relation | LOW | #38 optional; privacy retention | B10 |
| #40 Favorites / Pinned Students — personal roster shortcuts | E | Canonical roster and admin identity | E01/E03 | Per-admin preference; does not change student status | Admin/student preference relation | LOW | #19/#39 shared preference infrastructure | B10 |
| #42 Student Account Activity / Security — troubleshoot access | D | Verification, aliases, suspension/auth-attempt state exist | E01/E03/E14 | Safe successful-login/device/time preference projection and retention missing | Optional last-success event/metadata if absent | MEDIUM | Raw-IP repair; no invasive tracking | B4 |
| #43 Merge Candidate Detection — review identity evidence | D | Phone contact groups, canonical normalization and SA merge | E03/E17 | Canonical-student email/verified alias/phone evidence review absent | No initially; stored review state optional | HIGH | #20, parent/ownership checks | B4 |
| #44 Administrative Credit Transfer — correct allocation with provenance | H | Append-only ledger, no paired typed transfer type | E05/E20 | Atomic transfer pair/reference and conservation checks absent | Transfer record + supported ledger entry types | HIGH | R7, SA-only explicit reason and mapping | B7 after B5 |
| #45 Package Freeze / Pause — pause rights under explicit policy | H | Expiry extension/suspension exist but are distinct | E03/E05 | Pause intervals, booking rule and calculated expiry policy absent | Pause intervals and audited outcome | HIGH | R7/#6, overlap/extension business decisions | B7 after B5 |
| #46 Student Holidays / Unavailability — avoid unsuitable personal dates | E | Student timezone + authoritative tutor availability | E03/E04 | Student-specific intervals absent; no tutor calendar mutation | Student unavailability intervals | MEDIUM | #5, self/staff approval rules | B8 |
| #47 Tutor Calendar Exceptions — improve current exception editing | C | AvailabilityController and AvailabilityService exceptions | E04 | Calendar UI/named block presentation; core exception computation exists | No for calendar UI; optional reason label | LOW | Existing DST/exception invariants | B1 |
| #49 Booking Conflict Explanation — explain authoritative denials | D | Availability/status/hold/identity/ledger checks produce failures | E04/E05 | Safe staff reason projection/public redaction, not parallel availability | No | MEDIUM | R7, actual resolver outcomes | B8 |
| #50 Receivables / Payment Reminders — show due/overdue expectations | H | Canonical net money balances; no installment due dates | E05/E06/E13 | “Due” classification requires #9, not sticker-price debt inference | Reuse #9; reminder receipt state if automated | HIGH | #9/#26; H-OPS for recurring delivery | B7 |
| #51 Revenue / Cash Collection Reports — aggregate money correctly | E | Canonical payments/refunds + Cashier report | E05/E06/E11 | Month/method/offering totals and currency-safe labels | No initially | MEDIUM | R2/R5; distinguish cash collection from recognized revenue | B4 then B7 |
| #52 Retention Metrics — define cohorts and active/returning students | E | Canonical bookings/purchases/timestamps and reporting | E03/E04/E05/E11 | Documented cohorts and denominators, not existing visitor funnel | No initially; cached aggregate only if needed | MEDIUM | #26 lineage; #80 inclusion policy | B4 then B7 |
| #54 Attention / Churn List — deterministic follow-up flags | G | Credits/booking/expiry read data | E03/E04/E05/E17 | Merge with #52/#31; no separate predictive scoring module | Reuse shared read model | MEDIUM | R7/#52/#31 | B4 |
| #55 Renewal History — display package lineage | G | Existing independent package history | E05 | Merge with #26; chronological purchases are not proven renewals | Reuse #26 lineage | MEDIUM | #26 | B7 |
| #56 Lifetime Student Summary — accurate operational totals | E | Canonical student/bookings/payments/refunds/packages | E03/E04/E05/E06 | Compose summary, per-currency money/per-type credits/actual hours | No | MEDIUM | R7/R5/#52 shared definitions | B4 |
| #57 Notes Templates — insert editable educational/staff text | E | Notes editors and staff ownership policies | E08/E16 | Reusable templates, scope and ownership metadata absent | Template relation or staff-note kind, with distinct permission contract | LOW | #1/#2 contexts optional | B10 |
| #58 Text Snippets — copy reusable messages | G | Shared Staff Notes/copy foundations | E08/E16 | Merge into #57; no separate snippet subsystem | Reuse #57 kind/visibility metadata | LOW | #57, #22 | B10 |
| #59 Formatted Copy Blocks — copy booking/billing/package summaries | C | Copy-all identity and shared clipboard already work | E16/E19/E05/E12 | Add allowlisted projections beyond identity; no automatic disclosure | No | LOW | R4, permission-filtered summary functions | B1 |
| #60 Quick WhatsApp — open student's normalized destination | E | Normalized phone, public WhatsApp URL conventions | E03/E18 | Staff action absent; construct wa.me and optional escaped template | No | LOW | #22/#57 optional; no send/API | B1 |
| #61 Email Quick Action — open an email client | E | Student email and named record view | E03/E16 | Staff mailto link absent; no internal composer needed | No | LOW | #22; mailto needs no production SMTP | B1 |
| #62 Profile Completeness — show missing explicit requirements | E | Identity/verified emails/forms/packages | E03/E15/E05 | Read projection with named missing items, no vague percentage | No | LOW | #63, fixed criteria | B4 |
| #63 Form Completion Dashboard — act on required/in-progress forms | E | FormAssignmentService/submissions/versions | E15/E07 | Cross-student completion/next-lesson read model missing | No | MEDIUM | Draft policy/#64, current published versions | B4 |
| #64 Autosaved Draft Visibility — show progress and freshness safely | D | Drafts/revisions already stored and currently staff-visible | E15 | Progress denominator/last-save summary and explicit answer visibility policy | No initially | MEDIUM | #63, privacy decision before changing exposure | B4 |
| #65 Private Lesson Preparation — staff-only session scratchpad | G | Educational/Staff Notes with no booking relation | E08/E04 | Merge with #1; optional booking-scoped private note, never default publish | Reuse #1/#8 note relation | MEDIUM | #1/R9 policies | B3 |
| #66 Lesson Tags — categorize teaching work | E | Booking/notes no tag taxonomy | E04/E08 | Controlled tags/pivot and authorized search | Lesson tag definitions and booking pivot | LOW | #1, tag scope/security | B10 |
| #67 Student Tags — group operational students | E | Student roster has no tag taxonomy | E03 | Controlled staff taxonomy/pivot distinct from lesson tags | Student tag definitions and pivot | LOW | #31/#38 optional | B10 |
| #68 Business Notes on Packages — explain contract context | E | Package terms, financial reasons and audit exist | E05/E14 | Dedicated package-context staff note not represented by transaction reason | Nullable notes or package-note relation | MEDIUM | R3/#8 permission boundary | B4 |
| #69 Portal Credit Expiry Alerts — show actionable rights expiry | E | Package expiry and summary service exist in staff views | E05/E07 | Portal projection lacks expiry; add own usable-balance deadline card | No | MEDIUM | R7 labels; no autonomous reminder needed | B4 |
| #70 Staff Expiry Actions — make expiring rights actionable | G | Roster expiry filters, ledger, Telegram thresholds | E03/E05/E13 | Merge with #31/#26 cards and selected-package links | Reuse existing expiry and #26 lineage | MEDIUM | #31/#26; H-OPS for scheduled alert | B4/B7 |
| #71 Cancellation Statistics — distinguish causes/timing by period | D | Booking statuses, cancellation reasons and events | E04/E11 | Period projection exists in pieces; actor/late-outcome taxonomy insufficient | No initial status totals; #6/#21 snapshot for full dimensions | MEDIUM | #6/#21 definitions | B4 then B8 |
| #72 Room Usage View — see next/current/historical assignments | D | Meeting room pool + upcoming assignments/snapshots | E12/E04 | Per-room next-use/month counts absent; no new allocator | No | LOW | Snapshot vs current-room reporting policy | B4 |
| #73 Room Health Check — safe validation and manual opening | C | HTTPS validation/unique URL hash already enforced | E12 | Optional known-host warning and manual Open Room UI missing | No; optional provider host metadata | LOW | Custom-provider policy; no fetch/scrape/API | B1 |
| #74 Booking Room Indicator — see assigned/missing/provider in lists | C | Snapshots exist and special meeting page shows assignments | E12/E04 | General booking day/week/list cards lack badge | No | LOW | Eager-load projection; reveal policy preserved | B1 |
| #75 Daily Tutor Brief — useful daily operational summary | D | Telegram businessDigest/rule catalog + staff dashboard | E13/E17 | Counts exist; first lesson/task/installment detail missing, scheduled operation unverified | No beyond dependent features | MEDIUM | #31/#16/#9; H-OPS only for auto send | B4; delivery later |
| #76 End-of-day Summary — report delivered work and follow-up | G | Digest architecture, canonical events/payments | E13/E04/E05 | Merge with #75; add reporting window/template, no new sender | Reuse #75 | MEDIUM | #75/#6 definitions; H-OPS auto send | B4; delivery later |
| #77 Assistant Activity Summary — explain authorized changes | G | Audit actor history/current roles | E14/E02 | Merge with #29 reporting; do not imply assistants can enter payments | No; actor projection/filters | MEDIUM | #29, actual permission matrix | B4 |
| #78 Admin Notification Inbox — actionable local notices | D | Persisted admin inbox/read-dismiss flows already exist | E13 | Missing form/room/task/due events and per-recipient read semantics | Recipient/read relation if personal inbox wanted | MEDIUM | #16/#9/#31; no worker for local read | B4 |
| #79 Bulk Safe Actions — bounded selection workflows | H | Individual authorized actions/export already exist | E02/E03/E09/E11 | Need target relations and all-record per-item authorization/selection contract | Optional bulk operation/result record | HIGH | #35/#16/#67 + R5; no bulk finances/destruction | B10 |
| #80 Archive Inactive Students — retain history while hiding from active work | E | Canonical Student, suspension/soft deletion/merge distinct | E03/E07 | Dedicated archive state/unarchive/read inclusion policy absent | Nullable archived_at/by/reason | MEDIUM | #52, active booking/credit safeguards | B10 |

Each row below completes the authorization, booking/ledger/payment, portal, reporting and consolidation analysis for the same feature.

Authorization shorthand:
- SA = Super Admin; FIN = current SA/Admin financial permissions, never Assistant by default.
- READ = existing staff student-access projection, with current identity/financial field exclusions.
- LEARN = existing StudentBin/resource educational boundaries; proposed new actions need explicit policies. It is not an automatic permission grant to all staff.
- “Own student” means canonical ownership plus current verified/unsuspended student-session checks.

| ID | Authorization impact | Booking / ledger / payment impact | Student portal impact | Analytics / reporting impact | Module / consolidation decision |
|---|---|---|---|---|---|
| #1 | LEARN; explicit tutor publish; own visible student reads | No new booking/status/credit engine | Completed lesson workspace/material display | Private educational activity only by approved purpose | Booking/learning extension, not standalone appointment module |
| #2 | LEARN staff assign/review; own student submit | No automatic credit consequences | Assignments, drafts, submission/review | Learning completion read models, separate from visitor conversions | Learning subdomain sharing #1 |
| #3 | LEARN tutor review; own visible plan | No debit or financial score | Approved goals/progress | Tutor evidence, no invented scores | Learning foundation, not CRM notes blob |
| #4 | READ; FIN projection hidden from Assistant; student projection separate | Read-only canonical events | Optional own visible timeline later | Operational history, not new analytics event source | Student record projection |
| #5 | SA/Admin schedule; own student series only if expressly allowed | Each occurrence uses availability/compatible ledger transaction | Series preview/conflicts without guaranteed slots | Occurrence/cancel reports | Booking subdomain |
| #6 | SA/Admin policy; staff override reason; student safe outcomes | Versioned restoration/forfeit/no-show rules, exactly-once | Clear policy/outcome display | Actor/timing cancellation dimensions | Current booking lifecycle extension |
| #7 | Staff manage; own student consent/view | No credit spend until real booking confirmation | Interest/offer/expiry page | Demand and offer outcomes with honest denominators | Booking subdomain |
| #9 | FIN; Assistant no schedule mutation/export | Expected due schedule distinct from actual cash/credits | Own scheduled balance only if enabled | Receivables by currency, allocation audit | Cashier extension |
| #10 | FIN issue/view; own student scoped statement | Historical cash snapshots, no payment execution | Optional own receipt/statement | Money reports from canonical records | Cashier projection/document |
| #12 | SA initially; strong reauth/audit; preview-specific middleware | Read-only; no bookings/payments/notes writes | Same own-student visibility projection, synthetic context | Excluded from telemetry/conversions | Portal preview route, not impersonation module |
| #13 | SA manages delegation; guardian narrowly scoped after consent | Guardian cannot change credits/refunds by default | New delegated identity and scopes | Separate consent/usage, minimal metadata | Justified identity relationship, not duplicate Student |
| #15 | Student owns inbox; staff event producers restricted | No financial mutation from notification itself | Own recipient list/read state | Delivery/read counts without copying private payloads | Notifications domain |
| #16 | Staff assignee/creator scopes; SA destructive controls | No task completion triggers debit/refund | None initially | Due/completed task read models | Student/staff operations relation |
| #17 | READ staff; author/SA mutation per chosen note policy | No entitlement consequence | Hidden from student unless separate visible educational note | Optional expiry/card projection | Notes metadata / staff alert |
| #18 | Reauthorize every search/action; no new Assistant privileges | Mutations retain original endpoint guards | None | Personal navigation only, no public conversions | Merge #38/#22 |
| #19 | Per-admin ownership; validate current route permissions | None | None | No sensitive filter values in public telemetry | Preference layer |
| #20 | READ diagnostics; FIN sensitive cards; SA repair | No silent repair, no rewritten history | None | Diagnostic drilldowns, not scores | Composite operations read model |
| #21 | Existing domain roles, SA configuration if editable | Reason never grants forbidden outcome | Safe human explanation only | Consistent reason facets while preserving old text | Shared taxonomy |
| #22 | READ links; FIN hidden; parent guards on every action | Existing mutations unchanged | None | Staff activity via audit, exclude public funnel | Shared action registry |
| #23 | Same current screen permissions | Presentation only | Only where shared components are used | None | Shared UI |
| #24 | Own verified/unsuspended Student | Eligibility derived, no speculative credit total | Deterministic next-action links | Optional feature usage, no covert scoring | Portal projection |
| #25 | Own completed booking; LEARN review, hidden questions respected | Feedback never changes attendance/debits automatically | Optional private feedback form | Aggregate only with appropriate consent | Forms/lesson context |
| #26 | FIN purchase/renew; SA exceptions | New package/grant; lineage does not move balances | Own renewal recommendation, no auto charge | Renewal history/cohorts | Cashier/student lifecycle |
| #27 | LEARN tutor publish; own student access | Actual delivered duration, no price/credit score | Visible approved report | Evidence-backed progress only | Learning report projection |
| #29 | SA viewer remains; no assistant expansion | Read-only financial event summaries | None | Audit filters; redact secrets/IP | Existing audit viewer |
| #31 | Role-scoped cards; FIN cards absent for Assistant | Read-only authoritative eligibility/balances | None | Operational cards, not visitor metrics | Existing staff dashboard |
| #34 | LEARN author/reviewer rules; own visible observations | No debit | Optional approved learning issues | Educational longitudinal summaries | Learning subdomain |
| #35 | LEARN assign; own student authorized open/download | No booking required; optional completed-lesson association | Recommended Resources and safe access | Authorized assignment opened/downloaded, not duplicate public gate event | Resources/lesson relation |
| #36 | Staff curate under resource policy; own assignments | None | Ordered content references | Collection progress derived from actual items | Resource extension |
| #37 | Own student only; recheck target visibility each read | None | Personal bookmarks | No public interest inference from private targets | Preference relation |
| #38 | SA/Admin per current route; Assistant access needs explicit future approval | Payments searchable only to FIN | None | Staff search excluded from public conversions | Existing search expansion |
| #39 | Own admin; target authorization on re-open | None | None | Short retained history only | Preference layer |
| #40 | Own admin; permitted student references | None | None | None | Preference layer |
| #42 | SA/Admin security projection; Assistant only allowed troubleshooting state | No finances | Own account activity optional later | No raw IP/fingerprint profiling | Student record security panel |
| #43 | SA merge only; authorized review evidence | Reuse locked merge; conserve financial provenance | Merged identity access continuity | Read diagnostic outcomes, no fuzzy score proof | Existing identity/data quality |
| #44 | SA only with explicit reason | Atomic typed out/in pair; preserve consumption/refunds | Own balances display after operation | Transfer reconciliation/audit | Ledger extension |
| #45 | FIN or SA policy-controlled; student request separate | No guessed pause/refund; explicit effective expiry outcome | Pause status and usable rights | Audited intervals/outcomes | Package lifecycle |
| #46 | Own student request; staff manage with explicit policy | Does not modify tutor rules or cancel booked lessons silently | Own interval editor/view | Scheduling conflict context | Student scheduling relation |
| #47 | Existing availability roles only | Exception editor uses same authoritative resolver | Public slots reflect current rules | Audit exception edits | Existing calendar UI |
| #49 | Staff safe details; student redacted code | No alternative eligibility computation | Useful safe failure messages | Aggregate reason counts optional | Resolver read outcome |
| #50 | FIN receivables; student own due schedule | Due expectations + net collection, separate from credits | Own reminders/balance only if enabled | Due/overdue counts by correct dates/currency | Cashier/dashboard/notifications |
| #51 | FIN reports/exports | Net cash = payments minus refunds per currency; courtesy excluded | None | Canonical financial aggregation, not browser events | Existing reporting |
| #52 | Authorized student read; FIN only for renewal money | No mutations; define active/renewed precisely | None | Cohorts/retention based on actual records | Existing business reporting |
| #54 | Same #31/#52 role-scoped flags | No punitive or automated financial consequence | None | Deterministic attention list | Merge #31/#52 |
| #55 | FIN lineage; own student history optional | No old-package overwrite | Optional owned lineage | Renewal cohorts | Merge #26 |
| #56 | READ operational summary; FIN money hidden from Assistant | Per-type credits/per-currency cash; delivered UTC hours | Optional own summary later | Lifetime canonical totals | Student record projection |
| #57 | Staff owned/shared template policy; no broad SA editing exception inferred | Text insertion only | Student sees only saved/published note | None | Notes template extension |
| #58 | Same #57 authorship/visibility | Copy text only; no send | None | None | Merge #57 |
| #59 | Permission-filtered copies; FIN blocks Assistant money | Read-only copy of canonical summaries | None | None | Shared copy UI |
| #60 | Allowed staff contact access; normalized phone | No automatic message/send; user initiates external client | None | Do not count staff action as public conversion | Contextual action |
| #61 | Allowed staff email access | No automatic mail/send | None | Do not count staff action as public conversion | Contextual action |
| #62 | READ state projection, identity sensitivity filtered | No verification/package mutation | Optional explicit own missing requirements | Transparent named criteria | Student operations projection |
| #63 | Existing form roles/question boundaries | No eligibility bypass; uses mandatory assignment state | Current form statuses/links | Version-aware completion denominators | Forms/dashboard projection |
| #64 | Approved draft privacy before exposing answers; assistant_hidden retained | No ledger effect | Own autosave/progress status | Conditional-question denominator and last-save time | Forms/dashboard projection |
| #65 | LEARN staff-only author scope; never default publish | No booking lifecycle change | Not visible as preparation | No public tracking | Merge #1 |
| #66 | Learning staff taxonomy and booking parent checks | Tags cannot change type/credit eligibility | Only intentionally visible tags | Learning report filters | Shared tagging infrastructure, distinct target scope |
| #67 | Staff student-access scopes | Tags cannot define identity or credit policy | Hidden by default | Operational filters | Shared tagging infrastructure, distinct target scope |
| #68 | FIN package-context notes; Assistant not auto-expanded | No edited purchase terms | Hidden staff business note | Minimal audit, not visitor analytics | Package/notes extension |
| #69 | Own verified/unsuspended student | Derived usable typed balance + business-date expiry | Expiry card, no guessed future availability | No new public conversion necessary | Portal read projection |
| #70 | FIN/READ role-safe card; mutations original permissions | Existing expiry/renewal actions | See #69/#26 | Expiry follow-up counts | Merge #31/#26 |
| #71 | Staff permitted operational aggregates; no student ranking | Historical actor/timing evidence, not inferred penalties | None | Cancellation/no-show rates with real denominators | Booking report |
| #72 | Current meeting-management roles | No assignment/rotation changes | No early meeting-link disclosure | Snapshot-based room usage, no private URL in export | Meeting report |
| #73 | Current meeting-management roles | No allocation mutation from open test | No change to link reveal | No automated private-room probe | Existing meeting editor |
| #74 | Current booking-read roles; safe provider/status only | No allocation changes | Own reveal timing remains separate | Missing-room dashboard counts | Existing booking lists |
| #75 | Configured destination/rule authorization; private content minimized | Read only canonical balances/occurrences | None initially | Same Today/read model definitions | Existing dashboard/digest |
| #76 | Same #75 rule/destination control | No lifecycle or cash changes | None | Actual day outcomes, not assumed deliveries | Merge #75 |
| #77 | SA audit oversight; no surveillance score | Report only actually permitted actions, no implied Assistant payments | None | Audit-derived activity summaries | Merge #29 |
| #78 | Current staff inbox; recipient policy explicit | Read notices don't execute mutations | None; #15 separate recipients | Safe notification/delivery events | Existing AdminNotification |
| #79 | Every target authorized; FIN/suspend/delete/credit actions excluded initially | No bulk finance or implicit ledger changes | Assignment effects only when authorized | Per-item results/audit; selection not current page alone | Shared safe bulk orchestration |
| #80 | SA initially; staff visibility policy explicit | Archive cannot erase/forfeit balances or cancel future lessons silently | Decide access separately; distinct from suspension | Retention cohorts explicitly include/exclude archives | Student lifecycle state |

# Overlapping Features / Consolidation Opportunities

| Shared foundation | Features converging | Reuse and boundary |
|---|---|---|
| Lesson Workspace / educational content | R9, #1/#2/#3/#25/#27/#34/#35/#36/#65/#66 | Booking is the lesson identity; StudentBin handles appropriate notes; Resource is file/content identity. Homework and progress need distinct lifecycle relations but share the lesson screen, authorization and materials. Do not create another scheduling module. |
| Student operations / canonical identity | #4/#16/#17/#20/#22/#24/#31/#38/#42/#43/#52/#54/#56/#62/#63/#64/#67/#69/#70/#80 | Compose canonical student/domain projections. Timeline is read-only composition; task lifecycle and archive state are genuine additions, not a new CRM duplicate registry. |
| Cashier / ledger | R2/R3/R7, #9/#10/#26/#44/#45/#50/#51/#55/#68 | Payment/refund history remains money truth; typed ledger remains rights truth. Due schedules/renewal/transfer each have explicit relation semantics. No second balance column maintained by UI. |
| Booking / availability | #5/#6/#7/#46/#47/#49/#71 | Reuse issued slots, holds, UTC/timezones and locks. Recurrence stores intention/occurrences, not a parallel availability cache; waitlist does not reserve a slot until the existing hold flow succeeds. |
| Reporting / audit | R5/R6, #20/#29/#31/#51/#52/#54/#56/#71/#72/#77 | Same authoritative filtered queries feed UI/exports. Operational finance/learning data is not browser telemetry. Event labels and historical bases must be accurate. |
| Staff productivity preferences | R1/R4, #18/#19/#22/#23/#39/#40/#57/#58/#59/#60/#61/#79 | Shared UI/action/copy helpers, per-admin scoped preferences and audited bulk orchestration. Each action still calls its original authorized endpoint. |
| Notifications / digests | #15/#24/#31/#50/#69/#70/#75/#76/#78 | Extend existing admin inbox/outbox/rule delivery. Student inbox requires own-recipient semantics. Automatic scheduling/delivery is separately gated by H-OPS. |
| Security | R8, #12/#13/#42/#43/#79/#80 | Use existing identity/roles, purpose-built new policies and audit minimization; do not hide broad permission changes inside UX work. |

Eight primary G classifications consolidate whole requests: #18→#38/#22, #54→#52/#31, #55→#26, #58→#57, #65→#1, #70→#31/#26, #76→#75, #77→#29. Other rows still belong to an existing domain without being primary G; a genuine new relation does not imply a new standalone module.

Vocabulary is mentioned as an overlap example/theme, but no retained numbered standalone vocabulary item exists. Do not add it to the 70-item count. Likewise the closing thematic references to calendar subscriptions and granular permissions are not retained numbered feature requests for this audit.

# Proposed Shared Foundations

1. **One student-visible lesson projection.** Build from canonical Booking, approved StudentBin notes, materials and assignments. Reuse it in portal/history and SA read-only preview. Staff preparation/internal notes never enter it automatically.
2. **Versioned offering/entitlement contract.** Stable purchase provenance, explicit session requirement and typed append-only quantities, selected by current locked ledger service. Definition changes affect future contracts, not old purchases.
3. **Per-domain filter/query contract.** Validated filter DTO/array and authoritative query/projection reused by list/count/export. A shared button component is presentation; it must not homogenize unrelated finance/intake/analytics data.
4. **Authorized action registry.** Named route targets and policy checks for open booking/Cashier/forms/notes/copy/contact. Palette and contextual actions reuse it without a new general mutation endpoint.
5. **Operational read models.** Student timeline, lifetime summary, Today cards, receivables, completion/expiry/attention lists and digests share query definitions/denominators. Start on demand; cache only after correctness and measurements.
6. **Reason/actor normalization.** Consistent safe action summaries with domain reason codes and historical text preserved. Existing audit and booking events remain canonical; no duplicate all-events table.
7. **Notification recipient semantics.** Extend existing notifications rather than another delivery stack. Decide shared-admin versus personal read state explicitly; student recipient views have separate visibility.
8. **Preferences and templates.** Small whitelisted per-admin view/navigation settings are appropriate JSON/configuration; tasks, homework, rights, recordings and installment histories are not.
9. **Privacy lifecycle hooks.** Every new relation declares merge/reparent, export, redaction/delete, retention and author visibility. These are implementation gates rather than an afterthought.

# Database Reuse / Schema Recommendations

All additions below are conditional future recommendations. No migration, model or package was generated.

| Future data concept | Reuse / likely schema | Justification / features |
|---|---|---|
| Shared reset/copy/selected-package presentation | Existing Blade/JS, no schema | R1/R3/R4, #22/#23/#47/#59/#60/#61/#73/#74 are largely presentation/projection |
| Filter/export projections | Existing domain queries; no schema for parity | R5, #29/#31/#51/#56/#62/#63; optional indexes only with query plans |
| Offering identity | Stable key/version on StudentPackage; optional versioned definition table | R2: purchase instances currently lack provenance. An editable product table is justified only if configuration needs exceed code presets |
| Entitlement definitions / allocations / snapshots | EntitlementType + StudentPackageEntitlement; additive SessionType/Booking/Ledger refs | R7 requires compatibility and mixed allocations; cannot be a giant JSON balance or name convention |
| Lesson workspace | Optional booking-unique structured workspace and/or booking-linked StudentBin notes | #1/#65: one lesson identity, private preparation separate from published content |
| Lesson materials including recordings | Booking-owned one-to-many material records; existing Resource FK or private path or safe URL | R9: multiple materials/types and per-item visibility; not one booking.material_pdf field |
| Homework | Assignment, student submission/revision, review status relations | #2 needs due/state/review rules different from intake forms |
| Plans / milestones / learning issues | Student plan/goals, dated tutor evidence/observations | #3/#34; notes remain text, cannot safely query lifecycle from prose |
| Resource assignments / collections | StudentResourceAssignment optional Booking FK; ordered collection/items | #35/#36; reuse resource identity/file, preserve ordering and assignment history |
| Bookmarks | Student-owned target refs, uniqueness + target authorization | #37 references content, does not copy or own content |
| Post-lesson feedback | Optional booking context on existing form assignment/submission | #25: versioned Forms handles questions/answers; distinguish intake vs feedback assignment |
| Timeline / lifetime / operations / data quality | Queries/read projections first | #4/#20/#31/#42/#43/#52/#54/#56/#62/#63/#64/#70/#71/#72/#75/#76/#77 need no duplicate history table |
| Successful account activity | Minimal timestamp/event only if current auth history insufficient | #42: no raw IP/device fingerprint; do not store credential answers |
| Tasks / pinned alerts | Task lifecycle table; optional note pin/expiry metadata or alert relation | #16/#17 have state/assignee/time semantics, not generic StaffBin body parsing |
| Notification recipients | Existing AdminNotification extended with per-recipient read rows if required; student notices isolated | #15/#78: shared current read flag is not personal delivery/read state |
| Saved views / recents / pinned students | Small per-admin scoped preference records or whitelisted preferences JSON | #19/#39/#40; refs/filters revalidated, bounded history; not student domain status |
| Reason taxonomy | Nullable reason code on relevant events with preserved historical explanation; definitions if editable | #21/#6/#71; reasons cannot silently change old policy outcomes |
| Templates / snippets | Reuse StaffBin with explicit template/snippet kind or a focused template relation | #57/#58 share authorship/insert/copy semantics; do not make ordinary notes executable |
| Tags | Shared infrastructure with separate student/booking scopes and pivots | #66/#67; relational filtering, controlled definitions, no authorization inferred from tag |
| Package context notes | Nullable staff notes for simple single note, relation if history/multiple authors needed | #68; distinct from immutable contract terms and transaction reasons |
| Installment due items / allocations | Package-owned due schedule + allocation rows from immutable payment/refund IDs | #9/#50: multiple payments do not establish due dates; preserve total expected/currency |
| Statement / receipt | Derived statement initially; immutable issuance/number snapshot only if issuing receipts | #10: no duplicate payment balance; legal receipt requirements unresolved |
| Renewal lineage | Optional previous package link with cycle/identity/currency checks | #26/#55; chronology alone is not renewal proof, and old purchases remain |
| Credit transfers | Paired atomic typed ledger entries plus common transfer record/reference | #44: conservation/provenance cannot be free-text courtesy adjustments |
| Package pause | Approved non-overlapping interval records + outcome/expiry audit | #45: separate from suspension and manual expiry extension |
| Student unavailability | Student-owned intervals/timezone semantics | #46: cannot share tutor exception rows because it must not close tutor slots globally |
| Recurrence / waitlist | Series-occurrence identity; waitlist interest/offer lifecycle | #5/#7 need idempotency/consent and canonical booking links |
| Cancellation policy history | Versioned rules and booking/event outcome snapshot if rules expand | #6: mutable settings alone cannot explain historical penalty/restoration |
| Guardian access | Guardian identity, explicit relationship/consent and narrow scopes | #13 is genuinely missing; do not merge two human identities into a Student |
| Archive | Nullable archived_at/by/reason with active-work inclusion scopes | #80 distinct from soft delete, merge/privacy and suspension |
| TOTP | Administrator security columns/one-to-one encrypted state | R8 enrollment, confirmation/recovery lifecycle; never settings shared by all users |
| Social durable detail | Optional versioned social dimension rollup keyed by timezone/date/platform/placement/context/attribution | R6 if detailed historical reporting required; general DailyMetric cannot recover pruned page/placement uniqueness |
| Safe bulk job | Optional actor-owned selection/result record only for resumable multi-step work | #79: per-item outcomes/idempotency; do not bulk mutate money initially |

Foreign keys must preserve booking/package/student parent consistency and restrictive financial history. New semantic ledger entry types require actual schema review of the current enum/constraints. No destructive cleanup or speculative new base folders is recommended.

# Authorization / Security Impact

Existing staff access is not a blanket capability. Start each new endpoint with the current route group and a purpose-specific policy; enforce the policy again on the parent record being mutated/downloaded. A hidden button is not enforcement.

| Area | Future authorization contract |
|---|---|
| Money/entitlements/receivables/receipts | Current SA/Admin finance scope; Assistant denied. Explicit parent student/package/payment/allocation checks. Transfers SA-only |
| Lesson materials/recordings | Staff educational viewing only as currently allowed; tutor/SA publication explicitly gated; own visible student download through canonical booking owner |
| Private preparation/Staff Notes | Current author edit, SA delete and student_visible rules preserved; no accidental disclosure in portal preview/search/export |
| Audit/data quality | SA audit viewer stays restricted; diagnostic reads and repair mutations separated; no automatic merge/repair |
| Optional TOTP | SA self-enrollment/confirmation/recovery with reauthentication; pending login never has normal staff authority |
| Read-only preview | SA-only initial route, immutable preview context, deny all POST/PATCH/DELETE and exclude telemetry; no guard substitution |
| Guardian | Consent-bound child relationship and scoped delegated identity; no inherited finance mutation |
| Bulk actions | Resolve IDs from authorized scope, reauthorize every item, cap/preview selection and report failures; no initial bulk credit/refund/suspend/delete |
| Archive | SA initial action with current bookings/rights safeguards; distinct access policy rather than suspension shortcut |
| Search/copy/contact actions | Filter existence and fields before rendering/copying; do not expose inaccessible notes/payment references through snippets/palette |
| Student preferences/content | Own-student session checks on every target; recheck content visibility when reopening a bookmark |

Trusted external links use safe scheme/encoding; private files resolve server-controlled paths. Never log URLs carrying credentials, TOTP secrets, recovery codes or answer payloads. Existing API/route status codes and non-sensitive student conflict messages should stay consistent.

# Privacy Impact

The “no persistent raw IP” requirement is an actual mismatch with current security/audit writers, not a hypothetical risk. A future scoped privacy repair must inventory DB fields, Auth/PasswordReset/Reschedule/AuditLogService producers, database session IP behavior, logs and exports. Stop new raw-IP persistence, approve historical retention/redaction scope, and validate no loss of necessary actor traceability. Do not delete audit history or replace it with an unsalted reusable identifier. This audit performs no cleanup.

Public telemetry remains first-party, excludes internal/bot/synthetic activity, uses bounded metadata and does not need student names, private recording URLs or raw IP. Staff WhatsApp/mailto/copy/search actions should not inflate public conversion metrics. Social target URL storage should avoid credential/query secrets and remain within the existing safe metadata policy.

Educational material and recording access needs explicit student visibility, ownership and lifecycle retention. A private provider URL may reveal a recording once disclosed; the app controls disclosure, while external provider permissions need their own owner configuration. Merge/privacy service extensions must cover files, assignments, reviews, plans, tasks, notifications, bookmarks and guardian consents.

Form drafts are currently visible in staff queries/CSV. Code cannot determine whether that is the intended business consent policy. Prefer minimal progress/last-save visibility and submitted-answer disclosure until policy is settled; any change to existing draft exposure is a scoped future security decision and must be reflected in both screen and export.

Financial exports intentionally contain student name/email/phone in appropriate datasets. Shared writer reuse does not authorize adding DOB, internal notes, hidden form questions or security data. Audit summaries and activity dashboards need allowlists, not raw JSON dumps. Templates/recent history/saved filters should store minimal text or record refs, not copies of sensitive records.

# Migration Risk Register

| Change | Risk | Failure mode | Required mitigation before implementation |
|---|---|---|---|
| Typed entitlements / historical mapping | HIGH | Generic path spends incompatible rights; guessed history corrupts provenance | Reviewed manifest, additive schema, reconciliation, coordinated cutover, real concurrency tests |
| Offering identity/versioning | MEDIUM/HIGH when backfilling | Name heuristic misclassifies purchases; current edit changes old terms | Persist key for new sales, explicit unknown history, immutable purchase snapshots |
| TOTP enrollment/challenge/recovery | HIGH | Staff bypass or SA lockout; reset/remember path weakens second factor | Mature approved package, staged null default, challenge/reauth/recovery regression matrix |
| Installments / allocation | HIGH | Refunds/overpayments assigned wrongly; receivables invent debt | Allocation and currency rules, conservation checks, append-only money |
| Credit transfer / package pause | HIGH | Lost rights, double extension, hidden conversion or expiry bypass | Typed provenance first, atomic pairs, approved intervals/outcome rules |
| Recurrence / cancellation engine | HIGH | Conflicting appointments, duplicate debit, retroactive policy penalties | Occurrence idempotency/locks, versioned outcomes, partial failure policy |
| Guardian / portal preview | HIGH | Cross-student disclosure or impersonated mutations | Scoped identity/consent, read projection, no session substitution, negative authorization tests |
| Bulk operations / merge / archive | HIGH | Mistake radius, orphan relations, erased history or unusable credits | Per-target policies/preview, bounded selection, conservation/merge/privacy tests |
| Private material relations | MEDIUM with high disclosure consequence | Predictable file links, IDOR, hidden preparation published | Private storage/parent checks, explicit visibility and merge/redaction handling |
| Reason/actor schemas | MEDIUM | Historical audit changed or assistant capabilities implied | Nullable additions, old text preserved, safe read normalization |
| Social durable rollups | MEDIUM | Incorrect distinct totals or incomplete historic attribution labeled exact | Versioned dimensional basis, coverage labels, no reconstructing lost raw identities |
| Export/form/privacy consistency | MEDIUM; HIGH if deleting history | Formula execution, draft disclosure, raw IP exported, mismatched filters | Shared sanitizer/query projection, role/field tests, approved retention policy |
| Pure UI presentation | LOW | Reset loses mandatory scope; copy submits form; panel targets wrong package | Reuse current endpoints, contextual guards, focused UI regression verification |

A future implementation should separate schema introduction from historical mapping and from the semantic activation release. Additive migrations must be tested for forward/rollback behavior against the actual MariaDB/version and preserve existing restrictive financial FKs. Deployment authorization for this audit is explicitly absent.

# Existing Test Coverage

Tests were inspected as evidence, **not run in this audit**. The latest feature report records 685 passing current-schema tests / 6472 assertions, separate MariaDB concurrency 13 / 57, JS 8, and 261 pre-existing PHPStan findings at level 5. It also records two retained historical intermediate-schema failures. These are reported results at that release, not a fresh PASS stamp.

| Existing test family | Relevant established coverage | Limits for this plan |
|---|---|---|
| FeatureExpansionTest | Filtered Cashier/roster/directory exports and numeric workbook output; expanded operations views | Not every export family or future typed/product identity |
| BinAuthorizationTest; JS clipboard tests | Note ownership/visibility/merge/privacy and current unsaved-copy behavior | No booking-scoped material/recording/plan/homework lifecycle |
| StudentLedgerTest; StudentCreditBookingTest; CashierBillingReconciliationTest | Generic ledger, money/refunds/reconciliation, returning booking credits | No typed allocation compatibility, transfers, due schedule or freeze |
| StudentReschedulingTest; BookingLifecycleAndPolicyCutoffTest | Existing reschedule/cutoff/lifecycle behavior | No recurring series, policy versions or historical type snapshots |
| CanonicalAvailabilityValidationTest; CriticalBookingQAMatrixTest; BookingHoldAuthenticationTest; V3SlotIdentityTest | Authoritative slots, holds/idempotency and booking acceptance | New recurrence/typed rejection paths still need coverage |
| BufferExpandedConcurrencyLockTest; DurationOverrideBookingTest; DstGapAndFoldHandlingTest; TimezoneTest; MariaDbConcurrencyVerificationTest | Buffers, actual duration, DST/UTC boundaries and real races | Not evidence that a future new allocation/series locking order is safe |
| StudentAuthenticationTest; StudentSecondaryEmailTest; StudentMergeAndPrivacyTest; StudentBackfillAndIdentityTest | Student identity/session, aliases, merge/redaction boundaries | No guardian access/archive/new-child-relations/TOTP |
| AdministratorAuthorizationTest; PasswordRecoveryTest; RateLimitingTest; ProductionCookieSecurityTest | Existing role/password/reset/rate/session restrictions | No pending challenge/enrollment/recovery code race |
| FormsEngineTest; V5RemediationModuleTest; form-builder JS tests | Versioned forms, normalized answers, drafts/revisions/conditional UI | No universal writer parity, submitted-only policy or homework review |
| ClientTelemetryIngestionTest; FollowupRemediationTest; AnalyticsValidationAndIdentityTest; JS telemetry tests | UUID dedupe, distinct physical footer events, ingestion placement and identity validation | Overview placement grouping/filter UI/history completeness not guaranteed |
| BusinessReportingReconciliationTest; CairoDailyAnalyticsRollupTest; funnel cohort/analytics tests | Rollup/raw reconciliation, timezone/country/funnel definitions | No future social dimensional rollup/retention or student renewal cohort definition |
| MeetingRotationTest; BusinessOperationsControlsTest | Rotation, override, provider snapshots/reveal and operational settings | Production room URLs and operational host processes are separate |
| EngagementCountersTest | Actual delivered duration/study dwell and date/settings behavior | No entitlement conversion proof; hours must remain independent |
| TelegramAutomationTest; TelegramMultiRecipientReminderTest; ProductionHealthAndSchedulerTest | Bot/rule/outbox/delivery/recipient/health code behavior with mocked boundaries | Scheduler/worker/watchdog/mail/offsite operation remains unaccepted |
| MigrationRollbackSafetyTest; MigrationACompatibilityTest; DailyMetricsMigrationTest | Existing schema safety/regression boundaries | No future entitlement/material/TOTP backfill or semantic rollback coverage |

Tests in tests/Feature, tests/Feature/Concurrency, tests/Support and JS test files should be reused with meaningful fixtures/negative assertions. Existing factories and issued-slot support are preferred to tinker-created models. No new tests or scripts were written.

# Missing Future Test Coverage

This matrix maps **all retained IDs** to future behavior coverage. It complements R1–R9 tests above; groups are implementation boundaries, not assertions that missing code is tested already.

| Candidate IDs | Missing meaningful tests |
|---|---|
| R1/R4, #23 | Reset every filter/sort/page while preserving parent scope; visual icon/baseline/mobile/focus; copy unsaved values without submitting |
| R3, #22/#59/#60/#61/#68 | Selected package isolation and endpoint parity; permission-filtered copied summary; normalized WhatsApp/escaped mailto; no automatic sending or staff conversion event |
| R2/R7, #44/#45 | Definition provenance/ambiguous mapping; all typed grant/debit/restore/refund/expiry flows; atomic transfers; pause overlaps/outcomes; every read surface and MariaDB race |
| R5 | Country search/sort/UI/export parity; all fifteen datasets' supported filters/full scope; leading whitespace/header formulas; numbers versus phone/ref strings; hidden questions/fields; large XLSX capacity |
| R6 | All configured/optional platforms, one event per marked click, overview/general placement parity, context/content filters, export scope, missing/pruned data basis, navigation during failed telemetry |
| R8, #42 | SA enrollment/password confirmation/challenge/remember/reset/logout/suspension/recovery race; absent secrets in logs/export; safe own-account activity |
| R9, #1/#12/#35/#65 | Booking-owned materials/private paths/recording omission; resource reference replacement/visibility; foreign-student/assistant denial; read-only preview cannot invoke any mutation; hidden preparation |
| #2/#3/#25/#27/#34/#36/#37 | Homework revision/review/assignment ownership; tutor evidence/milestone publication; feedback booking/version ownership; collection ordering; bookmark target reauthorization |
| #4/#20/#31/#54/#56/#62/#69/#70 | Role-safe timeline/cards/diagnostics; defined lifetime actual hours/per-currency money/per-type rights; expiry at business midnight/DST; named missing criteria and truthful attention flags |
| #5/#6/#7/#46/#49/#71 | Series occurrence retry/conflict/partial rollback; policy snapshots; cancellation actor/timing/no-show outcomes; waitlist offer race/consent; student intervals vs tutor rules; safe denial reasons/denominators |
| #9/#10/#26/#50/#51/#52/#55 | Due-item allocations/refunds/overpayment/currency; immutable receipt/reference; renewal lineage/cycle prevention; no auto-charge; cohorts/inactive definitions and correct cash collection |
| #13 | Guardian consent/scopes/revocation/foreign child/unauthorized finances, privacy export/delete and narrow login |
| #15/#16/#17/#75/#76/#78 | Per-recipient notice/read state; task assignee/overdue/completion; expired alert visibility; digest window/detail/privacy/idempotency; no assumed host-delivery acceptance |
| #18/#19/#38/#39/#40/#57/#58/#66/#67 | Scoped search/action existence filtering; saved-filter allowlist; recent/favorite target reauthorization; template authorship/insertion; tag scopes and no implicit policy |
| #21/#29/#43/#77 | Reason versions/old text; normalized staff/student audit actors and redacted payload; duplicate evidence not automatic merge; activity summarizes only actually allowed mutations |
| #47/#72/#73/#74 | Existing exception semantics through new UI; snapshot-based room usage; safe HTTPS/known-host/custom-provider warning; badge/provider without early link disclosure or allocator changes |
| #63/#64 | Published-version assignments, conditional questions/progress denominator, last-save freshness, submitted/draft exposure policy, Assistant hidden-answer exclusions in both UI/export |
| #79/#80 | Authorized selection beyond page, per-item failure/replay/bounds; excluded destructive finance actions; archive/unarchive with active appointments/rights, no deletion/suspension equivalence; cohort scopes |
| H-OPS dependent branches only | Scheduler heartbeat, actual queue canary, independent watchdog, bot polling, mail/offsite acceptance in the deferred handoff; no such drill performed here |

# Dependency Graph

The graph represents future dependencies, not executed steps. TOTP can proceed independently once its recovery/package decisions are approved; it does not depend on typed credits.

~~~mermaid
flowchart TD
    A["Current canonical domains and permissions"] --> B1["B1 Shared presentation"]
    A --> B2["B2 Query/export/social/audit consistency"]
    A --> B3["B3 Lesson workspace, materials and assignments"]
    B2 --> B4["B4 Today, timeline and operations read models"]
    B3 --> B4
    A --> B5["B5 Offering identity and typed entitlements"]
    M["Reviewed historical mapping and compatibility rules"] --> B5
    A --> B6["B6 Optional Super Admin TOTP"]
    S["Approved maintained dependency and recovery policy"] --> B6
    B5 --> B7["B7 Installments, renewal and exceptional credit lifecycle"]
    B5 --> B8["B8 Policy engine, student intervals and recurrence"]
    B7 --> R["Receivables and renewal cohort enrichment"]
    B8 --> W["Waitlist offers and cancellation outcome reports"]
    B3 --> B9["B9 Homework, learning plans and progress"]
    B9 --> P["Published learning summary"]
    B2 --> B10["B10 Preferences, tags, guardian and safe bulk"]
    B3 --> B10
    B4 --> D["Canonical daily and end-of-day digest"]
    R --> D
    H["H-OPS deferred Hostinger acceptance"] --> X["Autonomous external delivery"]
    D --> X
~~~

Class H dependencies are explicit: #5 follows typed/policy/student intervals; #27 follows structured progress evidence; #44 follows typed rights; #45 follows approved pause rules/types; #50 follows due schedules; #79 follows target relations/safe selection. Automatic variants of other rows also depend on H-OPS without blocking their local/on-demand first version.

# Recommended Implementation Batches

These are recommendations for a later authorized implementation. Finish and verify each coherent boundary before adding unrelated workflows.

| Batch | Scope | Schema / risk | Completion gate |
|---|---|---|---|
| B1 — first recommended batch | R1/R3/R4, #22/#23/#59/#60/#61/#47/#73/#74. Prioritize three shared UI requests; remaining tiny actions may follow within the same presentation boundary | Mostly no schema / LOW | Reset all scoped filters; one selected-package panel retains every authorized action; unchanged copy behavior; mobile/accessibility; existing endpoint guards and ledger tests remain intact |
| B2 — consistency repair | R5/R6, #21/#29; scoped raw-IP and form-draft/export policy repair | Query/UI no schema initially; optional social retention/taxonomy additions / MEDIUM, historical deletion HIGH | All export scopes/field policies correct; single social click counted once and placements stay distinct in every consumer; privacy decisions documented; no raw secrets/IP exposure |
| B3 — lesson foundation | R9, #1/#35/#65 and tightly scoped #12 preview | Additive educational relations / MEDIUM; preview HIGH | Own-student delivery, staff visibility, absent recording omission, no duplicated resources; privacy/merge hooks and completed-lesson projection tested |
| B4 — operations read models | #4/#15/#16/#17/#20/#24/#31/#38/#42/#43/#51/#52/#54/#56/#62/#63/#64/#68/#69/#70/#71/#72/#75/#76/#77/#78 | Mostly query composition; tasks/recipient semantics additive / MEDIUM | Canonical totals and role-safe drilldowns; due/renewal/cancel dimensions labeled unavailable until prerequisites; on-demand digests only where host unverified |
| B5 — offering and typed rights | R2/R7, all connected package/booking/display/message/export surfaces | Reviewed additive + semantic migration / HIGH | Mapping exceptions approved; per-type conservation; every consuming path checked; no generic/typed mixed state; real concurrency and migration rehearsal |
| B6 — optional SA security | R8; existing staff authentication integration only | Approved maintained package + encrypted state / HIGH | No bypass/lockout, recovery one-use, null/default rollout, unaffected other roles; independent of B5 |
| B7 — financial lifecycle | #9/#10/#26/#44/#45/#50/#55; enrich #51/#52/#70 | Due schedule/allocation/lineage/pause/transfer additions / HIGH | Typed foundation where rights move; money/currency allocation rules; no automatic charging; append-only history preserved |
| B8 — scheduling policy | #6/#46/#49, then #5/#7; enrich #71 | Versioned outcomes/interval/series/waitlist / HIGH | Approved policy, compatible rights, hold/calendar locks, conflict/partial success semantics; no autonomous notification assumption |
| B9 — structured teaching | #2/#3/#25/#34/#36, then #27 | Learning lifecycle relations / MEDIUM | Structured real evidence, tutor publication and student isolation; no fabricated scores or competing form engine |
| B10 — selective later conveniences | #18/#19/#37/#39/#40/#57/#58/#66/#67, then #79/#80; #13 separately gated | Small preferences + selected lifecycle/security additions / LOW to HIGH | Actual usage justifies storage; per-target authorization; guardian consent separate review; no initial bulk finance/destruction |
| H-OPS — deferred external operations | Existing Hostinger handoff only | Infrastructure/operational | Must be explicitly resumed and accepted separately; unchanged and not executed by this audit |

Do not turn B4 into a giant prerequisite-heavy release: start with existing data (timeline, forms, expiry, meeting badges); due installments/tasks/renewal cohorts are added as those domains become authoritative. Do not make B1 wait for R2 product identity or R7 semantics. Do not make manual lesson materials wait for provider APIs or background automation.

# Features That Should NOT Be Separate Modules

- Clear/reset/copy/compact financial UI and contact actions: shared components and existing routes.
- Student timeline/lifetime/Today/completeness/attention lists: authorized read models over canonical domains, not duplicate CRM/student tables.
- Audit viewer/activity summaries: extend the current viewer, not another event sink.
- Lesson preparation/materials/manual recordings/lesson tags: Booking's workspace; keep homework/progress lifecycle relations beneath a shared teaching experience.
- Resource assignments/collections/bookmarks: references to existing resources/owned content, not copies of library files.
- Renewal lineage/receivables/receipts/revenue: existing Cashier/Reporting; no second accounting balance or courtesy-money conversion.
- Daily/end-of-day digests/admin inbox: existing notification/rule/delivery architecture; no second Telegram bot subsystem.
- Room usage/status/URL opening: current meeting editor/report; no alternative allocator or private-room scraper.
- Command palette/snippets/saved views: shared actions/search/templates/preferences; no independent authorization engine.
- Archive: explicit Student lifecycle state, not soft deletion or suspension renamed.

# Features Already Implemented Enough To Avoid Rebuilding

“Enough to avoid rebuilding” means a usable, traced foundation with named tests where available; it does not classify an expanded Vault proposal A.

| Working foundation | Reuse evidence | Expanded work remaining |
|---|---|---|
| Shared four-screen filter/reset UI | E16, E03/E06, FeatureExpansionTest | Wording/visual reset; per-area query contracts stay separate |
| Unsaved-value identity copy/all | E16/E19/E21 clipboard | Icon/parent alignment and extra safe summary projections |
| Append-only money/refund/generic ledger and expiry | E05/E06/E20 | Typed compatibility/product identity and later due schedules, not financial history rewrite |
| Canonical student/contact identity, aliases, merge/privacy | E03; identity/privacy tests | Candidate review/archive/guardian/new relation hooks |
| Authoritative slots/holds/UTC/timezone/locks | E04; booking/DST/concurrency tests | Series/intention/policy outcomes; no competing availability |
| Student portal, educational and shared staff notes | E07/E08/E19 | Lesson relation/materials/homework/progress; visibility reused |
| Resource Library/private files/public grant | E09; resource security tests | Private assigned-student entitlement separate from public gate |
| Meeting providers/pool/rotation/override/reveal | E12/E19 | Pool configuration/usage/badges/manual-open UX; reported production URLs still absent |
| Form versions/answers/assignments/drafts/revisions | E15; FormsEngineTest | Completion/progress/read policy/export consistency; no replacement intake engine |
| Social semantic events/dedupe/general grouped report | E10/E11/E18/E21 | Consumer placement consistency, filters, content/context and historical coverage |
| Admin audit viewer and notifications inbox | E13/E14 | Safe filters/summaries/more event types/per-recipient semantics |
| Today dashboard and Telegram digests | E13/E17 | Actionable read models/new-domain detail; scheduled operation remains deferred |
| Shared export writer and several authoritative query services | E03/E06/E11/E19 | Country/form parity, number/capacity contracts and new scopes |

# Deferred Hostinger Dependencies

HOSTINGER_OPERATIONS_HANDOFF.md is **deferred and unchanged**. Preserved SHA256: C551CD5E06E671DD31CA7DBA127253F55443CBB26471CAA5B8B8677D76D94286. No scheduler cron, queue cron, watchdog cron, Telegram polling cron, production mail changes, offsite repair or final operational acceptance was run.

Dependencies requiring later host acceptance:
- Scheduled Telegram session/missing-form/expiry/payment/task/daily/end-of-day notices: scheduler + real queue delivery + correct destinations and dedupe.
- Telegram commands requiring poller: polling job; inbound command authorization remains essential.
- Reliable stale scheduler/worker alerting: independent watchdog rather than the stalled scheduler alerting itself.
- Scheduled analytics aggregation/retention and GeoIP refresh: scheduler; raw/on-demand reports do not prove rollups stay current.
- Background backup/offsite replication: existing recovery/retention plus infrastructure credentials and accepted replication drill.
- Internal email composer/system-generated external mail if ever added: production mail acceptance; a staff mailto link needs none.

Code paths, local synchronous projections and historical bot connectivity are not proof of autonomous delivery. Manual resource/recording links, copy/actions/filter UI, on-demand dashboards and offline TOTP do not depend on recurring host jobs. Do not build a Codex scheduled automation as a workaround for production server operations.

# Open Decisions That Truly Require Business Input

No questions were asked during this audit. These decisions are recorded for a later implementation. Unknown means code cannot supply the business contract.

| Unknown decision | Why code cannot answer | Safe default recommendation | Consequences of options |
|---|---|---|---|
| Exact entitlement compatibility and historical mapping | Current purchases/ledger have no persisted type/product identity | Explicit approved definitions and reviewed manifest; unclassified history preserved, no guessed spending | Name/duration inference is faster but unsafe; review protects rights and can delay ambiguous new spending |
| Mixed-type package sales / multi-allocation spending | PRESETS currently grant one generic quantity | Design allocations to support multiple types; initially one compatible bucket per debit | Mixed-source spending adds locking/cancellation complexity; rejecting it simplifies provenance |
| Which public/admin paths consume package rights | Existing public/admin and returning-student paths differ | Keep diagnostic/no-charge policy explicit; consuming bookings always use compatible ledger transaction | Automatic debit would change business semantics; explicit selection gives traceability |
| Catalog editability and purchase definition versioning | Code presets exist, no admin-owned product catalog | Persist stable key/version now; editable catalog only when required | Code-only keys simpler; editable catalog adds version/publishing and historical governance |
| Cancellation/no-show/late/staff override outcomes | Current cutoff and staff bypass do not define all future penalties | Keep current outcomes until versioned policy approved; no retroactive penalties | More automatic forfeiture needs historical snapshot and student communication |
| Recurring-series all-or-none versus partial success | No series workflow exists | Preview all occurrences, require explicit conflict handling; never silently substitute | All-or-none easier reconciliation but less flexible; partial success needs per-occurrence results/retries |
| Refund allocation / due schedules / overpayments | Payment history lacks due-item allocation intent | Explicit schedule/allocations and currency conservation; don't infer “overdue” from sticker price | Automatic oldest-due allocation simpler but must handle reversals; manual allocation more work but explicit |
| Freeze intervals, expiry effects and booking access | Existing extension/suspension do not define pause policy | Defer pause; continue explicit audited extension | Pause booking block/expiry extension can benefit students but needs overlap/backdate limits |
| Draft answers private until submission? | Current staff queries expose drafts, but code proves behavior, not consent | Prefer progress/freshness only until explicit policy; scope screen/export together | Staff answer visibility aids preparation but increases disclosure; submitted-only minimizes unfinished-data exposure |
| Material publishing/retention and external recording consent | Notes visibility exists; per-lesson recording policy absent | Tutor/SA explicit publication, student-owned delivery, no element when absent | Staff-wide publish broadens access; expiring links need clear deletion/retention handling |
| Private resource assignment versus public library access | Public gate is visitor/contact based, not student lesson rights | Private ownership for lesson-specific files; reference public resources separately | Treating public gate as private would expose student material; private delivery adds policy checks |
| Guardian scopes and minor/student consent | No guardian relationship/identity model | Defer until explicit consent/scopes; no shared student credentials | Read-only learning scope simpler; booking/finance access requires separate grants |
| Optional SA TOTP recovery and dependency selection | No OTP/recovery implementation or recovery business process exists | Maintained approved package, null-default enrollment, strong offline recovery | Minimal action integration has less route disruption; full Fortify adoption needs route/pipeline reconciliation; weak recovery defeats 2FA |
| Shared versus personal admin notifications | Current read state is shared | Retain shared inbox until personal recipients needed | Personal read state requires recipient relation/backfill; shared dismissal can hide notices from colleagues |
| Retention/active/renewal definitions and archive portal behavior | Domain timestamps exist but cohort/business inclusion rules do not | Named deterministic criteria; archive distinct from suspension; no automatic forfeiture | Broader “active” changes rates; archive access must honor ongoing bookings/rights |
| Social history granularity and distinct metric promise | General rollups cannot recover detailed pruned visitors/placements | Preserve detailed dimensions only if needed; label daily-unique sums/coverage honestly | Exact long-period distinct needs retained identity basis; counts-only rollups cheaper but cannot promise exact distinct |
| Raw-IP removal and historical audit retention | Current writers contradict invariant; deletion policy is not encoded | Stop new persistence in a scoped repair; approve history/session/log retention separately | Immediate blanket deletion risks audit/recovery evidence; retaining raw IP indefinitely violates the requirement |
| Receipt legal numbering/tax content | Current Cashier is manual operational record, not a statutory invoice system | Start informational payment statements; do not call them tax invoices | Formal receipts may need immutable sequence/legal fields and retention |
| Bulk action/error radius and archive rules | Individual guards do not define multi-select/active-rights policy | Initially export/assign/tag/task only, preview and per-item results | Atomic bulk may fail all on one error; partial outcomes require clear tracking, never silent success |
| Provider hostname restrictions | Custom providers are allowed and HTTPS is enforced | Optional warning for known providers; preserve explicit custom HTTPS URLs | Hard allowlist can reject legitimate custom rooms; generic open URL cannot prove meeting health |

# Final Recommendation

Complete the audit first, then implement **B1 as the first release**: coherent Apply Filters/Clear Filters across the shared component, two-rectangle copy icon with parent alignment, and one selected-package management panel preserving all endpoints/history. Include the smallest useful contact/room badges only if they remain within that boundary. No financial redesign, typed-credit guessing or host operations belongs in that first batch.

Top ten highest-value future outcomes, ordered by practical tutoring use:
1. #1 + R9 Lesson Workspace with optional secure materials/recordings.
2. #31 Today operations dashboard using existing canonical data.
3. #63 Form completion dashboard with privacy-safe #64 draft progress.
4. #35 Resource assignment to students/lessons.
5. #4 Unified authorized student timeline.
6. #69 Student expiry visibility + #70 staff actions.
7. #2 Homework assignment/submission/review.
8. #3 Learning plan and tutor-reviewed progress.
9. #9 + #50 Installment due schedules and accurate receivables.
10. #26 + #55 Renewal flow/history with preserved packages.

Top ten highest-risk changes, ordered by potential harm:
1. R7 typed-credit semantic cutover/historical backfill.
2. R8 staff authentication/TOTP challenge and recovery.
3. #44 credit transfers/conservation.
4. #5 recurring lesson generation/concurrency.
5. #6 cancellation/no-show outcome changes.
6. #9/#50 installment allocation and debt interpretation.
7. #45 pause/expiry policy.
8. #13 guardian delegated identity.
9. #12 staff read-only portal preview and isolation.
10. #79 bulk actions / #80 archive with active rights.

Secure material delivery, ambiguous identity merge and raw-IP/history repair also require explicit safety gates even though they are not a competing top-ten list.

The conclusions are specific: typed entitlements need migration and reviewed mapping; optional SA TOTP fits with maintained package/schema work; lesson materials should be one-to-many Booking-owned references integrated into the lesson workspace; footer events are already semantically deduped but reporting/filters/history remain partial; filter-aware exports work in several shared-query areas but are not universal, especially countries/forms.

Only this planning report is the authorized output. No application code, dependency/configuration, test, migration, production data, message, commit, push or deployment was changed or performed. The Hostinger handoff remains deferred and unchanged.
