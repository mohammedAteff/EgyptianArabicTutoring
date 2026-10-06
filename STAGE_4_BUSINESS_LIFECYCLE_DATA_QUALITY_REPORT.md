# Stage 4 — Scheduling, Policies, Financial Lifecycle & Data Quality

Implementation date: 2026-10-06. Local brownfield Laravel application only; no deployment or production access. All fifteen requested features are implemented and required acceptance checks passed. Implementation/report commit `63d3b0b` was pushed normally to origin/main and its remote SHA was confirmed. This completion checkpoint records that evidence.

## Delivered features and reused foundations

| Requirement | Implemented behavior | Existing authority / principal files |
|---|---|---|
| 1. Recurring lessons | Staff-created weekly/fortnightly plans, start/end/count, lesson type, local time/timezone, DST fold choice and active/paused/completed status. Explicit bounded generation records each booked or blocked occurrence; retries reuse its identity. | RecurringLessonService, RecurringLessonPlan/Occurrence; AvailabilityService, SlotResolver and StudentBookingService create every actual booking through the existing transaction/typed debit path |
| 2. Cancellation / no-show policy | Configurable threshold and restore/retain outcomes, late cancellation deny option, immutable policy snapshot on new bookings and separate append-only consequence history. | BookingPolicyService/Decision, CancellationService, NoShowService; existing StudentLedgerService reversal and financial refund architecture |
| 3. Waitlist | Owned student interest in a date range and lesson type; staff open/contacted/closed/withdrawn management. Interest creates no booking or credit debit. | StudentSchedulingService, BookingWaitlist; existing Student identity/authentication |
| 4. Student holidays | Student-owned full local date periods stored as UTC boundaries; staff can manage them. Relevant linked-student booking/reschedule/recurrence writes recheck overlaps. | StudentUnavailability, StudentSchedulingService; existing TimezoneService and booking locks |
| 5. Calendar exceptions / conflict reasons | Existing exception management is paginated, date-unique and serialized with booking calendar locks. Saving/removing an exception warns about existing confirmed bookings without rewriting them. Recurrence displays safe blocked reasons. | AvailabilityService/Controller, existing exceptions/calendar locks; recurring occurrence reason codes |
| 6. Expected installments | Immutable full-price schedule of up to twelve dated installments, separate forecast projection and voided history. Actual receipts/refunds determine projected satisfaction. | InstallmentScheduleService, PackageInstallment; StudentLedgerService::summary |
| 7. Receivables | Default due view plus overdue installments, partial payments, overpayments and all history; owner/currency/state filters and matching CSV/XLSX exports. | ReceivablesReadModel/Controller; canonical ledger financial summary and ExportService |
| 8. Statements / receipts | Staff and owner-only package statements, actual payment receipts, print and CSV/XLSX. Payment-method snapshot and genuine stored reference retained; manual records remain manual. | FinancialStatementService, staff/student FinancialStatementController; append-only PaymentRecord/RefundRecord and canonical typed summary |
| 9. Renewals | A renewal creates a new purchase and grant through the existing purchase service, linked to its unchanged previous purchase, date, reason and optional notes. | PackageRenewalService, PackageRenewal, PackageLifecycleController; StudentLedgerService::createPackage |
| 10. Business reporting | Actual dated cash receipts/refunds, current balances/overpayments, typed usage, expiry and dated renewal history; explicitly observed renewal/repeat-lesson indicators. | BusinessLifecycleReport/Controller; Report filters, TimezoneService, canonical ledger summary and ExportService |
| 11. Data Quality Center | Read-only counts and review links for missing identity fields, incomplete/unclassified purchases, invalid configuration and ownership/unlinked-booking risks. | DataQualityReadModel/Controller; existing Student/package/booking/payment relationships |
| 12. Duplicate candidates | Conservative normalized name plus email or international phone candidates for Students and Contacts. Review only; no automatic merges. | StudentIdentityService normalization; StudentMergeService remains the authoritative merge path |
| 13. Shared reasons | Reusable reason catalog and form component for relevant cancellation, planning, renewal and operational-state actions, with optional context notes. Historical free text is retained. | OperationalReasonCatalog, operational-reason-select component |
| 14. Audit viewer | Actor/type/ID, action, entity, date and domain filters; allowlisted readable diffs; Super Admin only. Legacy secrets and raw IP are not rendered. Dashboard audit summary has the same role/privacy restriction. | Existing AuditLog/SystemHealthController; AuditLogPresentation and audit views |
| 15. Inactive/archive | Explicit active/inactive/archived operational state, reasons and history, separate from suspension/deletion. Default roster and daily non-booking queues omit inactive students; explicit history and financial views retain them. | StudentSchedulingService, StudentOperationalStatusController; StudentRecordsQuery, OperationsReadModel, StaffTaskQuery and StaffRecentViewService |

Eleven small controllers expose the new staff/student boundaries. Shared planning, recurrence, reasons and feedback components reuse the current layouts. No additional booking, payment, balance, notification or identity authority was introduced. Package terms, actual financial records, typed provenance and existing historical snapshots remain intact.

## Policy and scheduling semantics

New bookings snapshot policy version 1. Defaults use the existing cancellation cutoff setting (normally four hours), deny late customer cancellation, restore staff cancellation credit and retain no-show credit. The configuration accepts cutoff 0–168 hours; late cancellation can deny/restore/retain; staff cancellation and no-show can restore/retain. Early customer cancellation restores original credit; past customer cancellation is denied. Policy updates and their audit entry are atomic.

Existing bookings with a null snapshot keep documented version-0 behavior derived from the legacy cutoff setting; this stage does not backfill or silently replace old booking snapshots. Invalid stored new policy is flagged read-only in Data Quality and new bookings use documented defaults until staff correct it. Booking status and policy consequence are separate records.

Restore reverses the exact original typed allocation and quantity through the existing canonical restoration key. Retain preserves the original debit rather than charging again. No-show restoration is described as a no-show policy consequence. Neither action fabricates a money refund; actual cash refunds remain the existing separately recorded workflow. Cancellation/no-show races produce one terminal state and one consequence, without duplicate restoration.

Plans have at most 104 numbered occurrences; each explicit batch is 1–12 (default four). End date/count bounds are respected. Pausing/completing a plan does not cancel already booked lessons. Generation rechecks current availability, notice, conflicts, active lesson/student, compatible unexpired credit and student holidays. DST gaps and folds are resolved by the existing timezone service; skipped holiday midnights produce a friendly validation error. Blocked occurrences show safe categories without identifying another student. No unlimited future credit block is consumed and no scheduled generation was configured.

Default daily operational queues exclude inactive/archived students, including open/contacted waitlist interest. Existing booked lessons remain visible in Today/calendar so tutors can handle commitments. History, finance, audit and explicit all/archived views remain accessible. Operational archive does not suspend authentication, delete data or automatically cancel lessons. New recurring generation requires an active student.

## Financial semantics and reporting

StudentLedgerService::summary is the single financial/typed projection. All new readers eagerly load its existing payments, refunds, allocations and ledger entries; money uses exact decimal/integer arithmetic. Expected installment rows never create PaymentRecords. One immutable complete schedule must equal the purchase's final price; actual net payments satisfy due-date-ordered installments and refunds can reopen forecast balances. Voiding changes forecast status only and preserves history. Replacing a voided schedule is outside this first version.

Renewals preserve previous purchase terms, payments, refunds and allocation history. Catalog renewals use the existing purchase service's current preset and discount rules. A classified custom renewal repeats the previous immutable price/quantity/type with an optional new expiry; catalog validity remains governed by the existing service. Unclassified/multi-allocation custom renewal requires a classified preset instead of guessing funding. Idempotent renewal replay returns the original new purchase; changed details or another owner cannot reuse its key.

Statements/receipts use immutable recorded payment-method text, not the method's later renamed label. Internal payment IDs are identified as internal; an external/provider reference appears only if it already exists. Student routes recheck package/payment ownership and return 404 for another student's record. All new statement/export/receipt responses are private and no-store, including binary XLSX.

Report dates are business-calendar dates converted to half-open UTC bounds. Receipts and refunds are counted on their actual respective dates, so a refund-only period may have negative net cash. Currencies are never added together. Outstanding balance, typed usage and expiry are labeled current values rather than reconstructed historical balances. Observed renewal share means distinct source purchases renewed during the selected period divided by purchases created before period end; repeat-lesson share means students with at least two completed lessons divided by students with at least one during the period. These are descriptive indicators, not forecasts, accrual revenue or a claimed retention probability. Currency attendance filters use students with a purchase in that currency, including preserved history. Screen/export use the same filter/row generation.

## Schema, history, transaction and query hygiene

One additive migration, `2026_10_05_194113_add_business_lifecycle_and_data_quality.php`, was applied to the normal local database. It adds operational state to Students, nullable policy snapshots to Bookings and a unique calendar-exception date. Duplicate exception dates block migration for manual review rather than silently choosing a row.

| Table | History / uniqueness / lookup strategy |
|---|---|
| recurring_lesson_plans | Restrictive Student/SessionType FKs; nullable staff creator SET NULL; immutable terms/fingerprint; unique request key; student/status index |
| recurring_lesson_occurrences | Restrictive plan/booking references; unique plan/sequence and nullable unique booking; bounded per plan; booked linkage retained |
| booking_policy_decisions | Restrictive Booking FK; immutable snapshot/consequence; unique booking/action; explicit actor identity and creation time |
| booking_waitlists | Restrictive Student/SessionType FKs; immutable owner/interest/request identity; status/notes lifecycle; unique request key; student/status and status/date indexes |
| student_unavailabilities | Restrictive Student FK; immutable UTC interval/timezone/request identity; status/notes lifecycle; unique request key; student/status/start index |
| package_installments | Restrictive purchase FK; immutable amount/date/schedule identity; status lifecycle; unique purchase/sequence and schedule/sequence; status/due index |
| package_renewals | Restrictive Student and old/new purchase FKs; nullable creator SET NULL; immutable linkage; unique new purchase/request key; student/date index |

Seven models have useful factories; SessionType also now supports factories. Finite statuses are validated at service boundaries and immutable historical fields/deletion are guarded in models. Merge transfers the new directly student-owned records to the canonical Student through the existing controlled merge workflow. Privacy redacts new notes and deactivates planning records while retaining financial/booking history. No automatic historical data repair is performed. Empty rollback is defined; rollback refuses once any new table contains lifecycle history, requiring reviewed forward recovery instead of destroying records. No production migration or rollback was executed.

Existing write lock order is retained: calendar date → Contact where applicable → Student → Booking, then recurrence plan/occurrence for that booking. Renewal/installment/payment operations use Student → purchase locks. Status-only plan changes take the plan lock without waiting for Student, avoiding an inverted cycle. Calendar exception mutation uses the same calendar date lock as booking. Idempotency combines immutable owner/details fingerprints with database unique keys and transaction retries; real MariaDB race coverage verifies the consequences.

Receivable/report processing uses eager-loaded lazy batches of 75; screen results are limited to 50 matching rows, exports stream through existing capped/formula-safe ExportService. A one-to-twenty purchase regression holds receivables at nine queries. Business timezone is resolved once per batch reader instead of once per purchase. Audit/exceptions are paginated at 30/25. Data-quality detail samples are bounded at 30; duplicate review scans the first 1,000 eligible records per kind and returns at most 100 pairs. That review is conservative and bounded, not an exhaustive duplicate certification.

Controllers delegate transactions/calculation to domain services; Blade presents service outcomes and forms. Removed duplicated no-show/exception transaction logic, unused cancellation ledger injection, and blanket cache flushes from DB-backed availability settings. Audit forward scrubbing covers credentials/OTP/recovery/token/PII fields; existing history is not rewritten. Presentation uses an explicit safe-field allowlist, including for legacy records. Main-dashboard student-time labels now render in the stated customer timezone, with regression and browser evidence.

## Automated acceptance verification

| Check | Result |
|---|---|
| Full current-schema suite | Final rerun: 993 passed / 8,642 assertions; no failures, errors or skips |
| Final feature + accessibility focus | 42 passed / 295 assertions; Stage 4 feature file includes 36 tests / 202 assertions |
| Existing MariaDB concurrency suite | Final rerun: 16 passed / 76 assertions |
| New lifecycle MariaDB concurrency suite | Final rerun: 6 passed / 51 assertions |
| JavaScript | 18 passed across the six existing test files |
| Production frontend build | Passed; Vite 8.3 production assets generated locally |
| Blade cache / Pint / whitespace | Passed; final checkpoint verifies current source |
| PHPStan | 257 starting findings → 257; exact file/identifier/message multiset unchanged, zero added/removed; full verbose output compared |

The current-schema suite excludes MigrationACompatibilityTest, which concerns a historical intermediate schema, and both dedicated concurrency classes, which run separately with phpunit.concurrency.xml. The concurrency database is an explicitly guarded local test database, MariaDB 10.11.18; fixtures are committed and competing processes use real transactions, without an outer test transaction. No dependency, baseline, analysis level or ignore change was made. PHPStan's existing 257 diagnostics remain debt; the stage gate is no new diagnostics, not a claim of a clean global analysis.

Meaningful new coverage includes recurring replay/last credit/free funding, safe notice/conflict/blocked/inactive/expiry reasons, pause/archive, DST gap/fold/skipped-midnight validation, policy snapshots/legacy behavior/typed restore/retain, exact installment/refund/overpayment projection, unchanged renewal source, key drift and foreign-owner rejection, actual statement/receipt provenance, method rename stability, privacy headers, filtered CSV/XLSX contents, mixed currencies/refund dates, archive/history/saved views, conservative duplicate review, read-only invalid configuration, safe legacy audit/dashboard role restriction, bounded query growth, merge and privacy lifecycle. All four new XLSX endpoints are decoded with IOFactory to inspect workbook rows, filters and actual cash semantics.

New races cover two generators of one occurrence, competing plans for the last credit, cancellation versus no-show, replayed renewal, replayed actual payment satisfying a forecast, and payment versus renewal preserving money on the original purchase. Existing sixteen booking/reschedule/cancellation/financial races remain covered. An older dashboard accessibility assertion was updated to inspect the restricted audit table as Super Admin; ordinary Admin audit privacy is separately tested.

## Browser QA and evidence

Boost resolved the Herd local URL. Desktop QA used 1280×720; mobile emulation used 390×844. All created/changed records were clearly synthetic QA records. Temporary tabs were closed and viewport/device overrides reset after QA; fresh mobile report logs contained no warnings/errors and its document width equaled 390.

| Journey | Observed result |
|---|---|
| Student planning | Owned passwordless QA login, saved holiday and waitlist interest, visible dates/status and mobile form; holiday subsequently cancelled and interest closed |
| Recurrence | Staff created a one-occurrence plan outside availability; generation showed zero bookings and one safe blocked occurrence; replay retained it; plan subsequently completed |
| Policy/calendar | Desktop/mobile policy defaults and snapshot explanation rendered; existing exception/weekly calendar UI and warning rendered without changing owner settings |
| Finance | Receivables default Due and student/currency filters, canonical refund-adjusted balance, lifecycle/renewal history and mobile Add another installment row rendered; no actual money/renewal/schedule UI writes |
| Statement/receipt ownership | Staff/student owned actual payment/refund statement and manual receipt rendered; foreign package returned 404 |
| Exports | Actual browser CSV download captured and inspected; XLSX browser completion could not be confirmed because the browser download tooling timed out. Four XLSX endpoints independently passed real workbook-content tests; no browser XLSX success is claimed |
| Archive | Admin archived QA Student 2: default roster omitted it, archived filter found it, finance/history remained available; student was restored active afterward |
| Audit/data quality | Super Admin saw safe filtered actor/status diff and review links; ordinary Admin dashboard omitted audit summary; Assistant direct Data Quality access returned 403 and privileged links were absent |
| Time/responsiveness | Dashboard showed 09:00 Cairo / 02:00 New York correctly; recurrence, installments, student statements/planning, policy and reporting fit 390-pixel document width with table scrolling inside wrappers |

Synthetic cancelled holiday, closed interest and completed blocked plan remain locally as reviewable history. No active QA planning item or archive state remains; no new real booking/payment/renewal or production data was created. Recent persisted Boost errors were older Stage 2 reschedule entries; fresh Stage 4 page logs were clean. Existing legacy manual Contact-only bookings cannot infer a Student holiday relationship; linked-student public/student/recurring/reschedule paths enforce it.

Evidence outside Git: `C:/Users/e/.codex/visualizations/2026/10/05/01a10cc2-a93f-75f0-a06b-762317ec539a/`: stage4-report-desktop.png, stage4-report-mobile.png, stage4-audit-filter.png, stage4-recurring-desktop.png, stage4-recurring-mobile.png, stage4-installments-mobile.png, stage4-student-statement-mobile.png, stage4-student-planning-mobile.png, stage4-policy-mobile.png, stage4-archive-roster.png, stage4-data-quality-desktop.png and stage4-package-statement.csv.

## Deferred work and final completion

Stage 5 development/launch data-management work remains outside this stage. Existing static debt remains. Recurring generation/waitlist follow-up are explicit manual operations; no scheduler, worker or automatic outbound notification was configured. No Hostinger handoff/deployment, owner security enrollment, production access or environment change was performed. The owner spelling remains Abdallah; the supplied owner email is absent locally and no unrelated account was renamed.

**Stage 4 complete. Implementation/report commit `63d3b0b8b4de275ba91f4eb2485e1c218676b05b` was pushed normally to `origin/main` after all acceptance gates passed, and the remote SHA was confirmed.** This documentation checkpoint records the implementation push; the final response records the checkpoint's own pushed HEAD/remote confirmation. Ready for Stage 5. Unrelated user documents, local evidence/build output/temp scripts/test logs and credentials are excluded. No deployment was performed.

Telegram completion: existing outbound TelegramDeliveryService::direct was discovered and will be reused for exactly one final owner-authorized status resolution after the last push. Read-only preflight found zero enabled verified destinations (`verified_destination_unavailable`). No disabled QA bot/destination is enabled or repurposed; no credentials, destination identifier or bot configuration are stored here.

The final safe delivery receipt is referenced outside Git at `C:/Users/e/.codex/visualizations/2026/10/05/01a10cc2-a93f-75f0-a06b-762317ec539a/stage4-telegram-receipt.json`. It records notification/transport attempted, success/failure, safe error category and returned message IDs only. This final-step receipt preserves the required report → push → one notification ordering. If a verified destination remains unavailable, transport is not attempted, configuration is untouched and the final response reports TELEGRAM NOTIFICATION FAILED. Notification failure does not undo a successful stage result.
