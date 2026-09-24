# Gemini Remediation Ledger

**Updated:** 2026-09-24

**Specification priority:** `Arabic w Abdallah EDIT V3.md` > V2 > original specification

**Rule:** This file lists only verified remaining CRITICAL/HIGH items. Current code/test results are in `PROJECT_STATUS.md`.

## Remaining Issues

No verified CRITICAL/HIGH application-code fix is currently outstanding after this independent pass. Owner setup is still required to enter the real private lesson-room URL in Owner Settings; no meeting URL was assumed or read from the local database. Remote production deployment and the reported `b11` backup/isolation were not verified from this shell.

## Resolved During This V3 Pass

- **V3-R01 — Local database gate:** `.env` targets `bolt_landing` at `127.0.0.1:3307`; `db:verify-capability` detected MariaDB 10.11.18 and verified the booking/student FK; `migrate:status` showed 44 Ran / 0 Pending; the explicit backfill dry run reported zero rows in every outcome category. PHPUnit config targets the isolated `bolt_landing_test` database.
- **b11 project isolation preserved (owner-reported):** Owner reports sibling project `b11` and all 54 tables remain untouched, and a safety backup is at `C:\Users\Ateff\Herd\b11_backup_safe.sql`; this shell did not query the database or inspect the backup.

- **V3 test-contract mismatch:** The old lifecycle test expected a second cancellation to throw. Updated it to assert V3's idempotent repeat-cancel behavior, exactly one cancellation event, and continued denial of rescheduling a cancelled booking. Focused result: 11 tests / 29 assertions passed.
- **V3 refund-concurrency proof gap:** Added coordinated independent-process refund coverage for separate payments against a package's exact aggregate paid ceiling. The MariaDB test passes and asserts persisted totals.
- **V3 hot-path index proof gap:** Added an EXPLAIN regression test verifying the ledger balance lookup selects the package-leading index as an index seek/ref plan.
- **MariaDB migration ordering:** Corrected add/drop index order around the form-submission uniqueness change.
- **Student admin page SQL error:** Replaced eager-loading a computed `session_types.name` accessor with the real `title` column.
- **Backfill exact-identity linking:** The backfill previously created a duplicate legacy student even when all available canonical contact identifiers matched one existing active student. It now links exact normalized name/email/phone matches, retains partial matches as duplicate-review cases, keeps ambiguous matches unlinked, and reflects those outcomes in dry-run counts. `StudentBackfillAndIdentityTest::test_backfill_links_an_exact_canonical_student_match_without_creating_a_duplicate` is included in the independently passing full suite; the live dry run reported zero rows in every outcome category.
- **Timestamp preservation:** Explicitly preserve historical timestamps during ownership/anonymization updates affected by MariaDB TIMESTAMP auto-update behavior.
- **Backfill locking:** Backfill application now holds calendar rows before sorted student and booking rows, uses bounded transactions, and rejects booking-time drift that would require an unheld calendar lock.
- **Idempotent booking side effect:** Replayed student bookings no longer dispatch duplicate admin notifications; public intake remains free and only authenticated student bookings debit credits.
- **V3 admin-reschedule reconfirmation rule:** `RescheduleService::reschedule()` sets `admin_reconfirmation_needed = true` for every actor; the admin-route/history/flag/credit-neutrality regression is included in the independently passing full suite.
- **V3 confirmed-only cancellation rule:** `CancellationService::cancel()` permits only confirmed bookings and retains idempotent repeated cancellation; the pending-status/event/ledger regression is included in the independently passing full suite.
- **Cold-start feature-test setup:** The initial independent suite run failed because `ExampleTest` requested the DB-backed homepage without refreshing the isolated test schema. Added `RefreshDatabase` to `tests/Feature/ExampleTest.php`; the focused test passed after a fresh migration and the subsequent full suite passed.
- **Form publish boundary:** Kept saved draft structure private from students until publish; student submissions and default admin response/CSV views follow the currently published version. Verified with HTTP and persisted version assertions.
- **Privacy audit scope:** Removed the global audit IP/user-agent wipe; only student-related or matching-personal-data log metadata is cleared, and unrelated audit metadata is preserved by regression coverage.
- **Article image path validation:** Rejects multi-layer percent-encoded dot/backslash traversal while retaining valid same-origin storage images.
- **V3 admin preview authorization:** Public CMS preview routes previously relied on controller authentication checks and did not distinguish Assistant from Admin/Owner. Added `EnsureAdminPreviewAccess`, preserving 403 responses for guests and denying assistants; authorized Admin/Owner previews remain available. Role-matrix and CMS preview regressions pass.
- **V3 student lesson details:** Student upcoming-session cards omitted the required tutor name and meeting link, while confirmations and `.ics` files silently used the generic `https://meet.google.com` homepage as a fake room. Added owner-managed HTTPS URL validation and display to the student dashboard, booking confirmation, and calendar file; an unset/unsafe value now has no fake join URL. The actual room URL must be configured by the owner.
- **V3 lock-order proof:** Added MariaDB SQL-event assertions for the observed lock tier order on student booking and student merge. Existing independent-process booking-vs-merge and refund-race tests remain enabled.
- **V3 unchecked form metadata:** `FormController::validatedMetadata()` previously left `is_mandatory` and `can_edit_after_submission` unchanged when browser checkboxes were unchecked and omitted from the request. It now normalizes missing values to `false`; `FormsEngineTest::test_unchecked_form_metadata_checkboxes_are_saved_as_false` passes.
- **V3 canonical admin student search:** `StudentController::index()` previously lowercased the raw query rather than reusing `StudentIdentityService`, causing punctuation-normalized names and formatted E.164 phone searches to miss records. It now searches normalized name/email and validates explicit international phone input without guessing a country. `AdministratorAuthorizationTest::test_admin_student_search_uses_canonical_name_and_international_phone_normalization` passes.
- **V3 held upcoming bookings:** `DashboardController::index()` previously omitted future `held` bookings from the required Upcoming sessions card. It now includes `held` alongside `confirmed` and legacy `pending`; `StudentReschedulingTest::test_student_dashboard_lists_future_held_bookings_as_upcoming` passes.

## Verification Baseline

- Latest full PHPUnit/Laravel run: **INDEPENDENT PASS — 468 tests, 2,919 assertions, 0 failures/errors** on MariaDB using Herd PHP 8.4.25 and `bolt_landing_test`. The prior 465-test / 2,909-assertion baseline is superseded.
- Focused latest regressions for admin settings, meeting-link presentation/ICS, student rescheduling, booking locks, and merge locks: **41 tests, 322 assertions**. Preview/RBAC/CMS focused suite: **25 tests, 239 assertions**.
- Production frontend build: **INDEPENDENT PASS** (Vite 8.3.0, 2.76 seconds; only the optional Fontaine optimization warning).
- Laravel Pint: **PASS under Herd PHP 8.4.25**.
- Composer platform requirements: **PASS — all 24 checks** under Herd PHP 8.4.25.
- Blade compilation: **PASS**.
- Scheduler: **5 tasks listed** on the configured local application.
- Routes: Compiled preview route listing confirms all six public CMS preview routes use `EnsureAdminPreviewAccess`; the admin article preview retains the Admin/Owner role gate.
- Latest focused PHP syntax checks passed on the changed cancellation/reschedule services and their regression tests under PHP 8.4.25. `git diff --check` passes.
- Migrations/FK/capability/backfill: **INDEPENDENT PASS** — 44 Ran / 0 Pending; FK verified; MariaDB 10.11.18; dry run zero outcomes/records.
- Laravel Herd local site: owner reports `http://boltlanding.test` returned HTTP 200; not independently requested from this shell.
- Independent current checks: test DB target confirmed; full suite, Pint, frontend build, migration status, capability, backfill dry run, and scheduler listing passed using Herd PHP 8.4.25. Vite emitted only its optional Fontaine optimization warning.

## Latest Independent Verification

- Frontend production build: **INDEPENDENT PASS — Vite 8.3.0, 2.76 seconds**; optional Fontaine optimization warning only.
- Test database isolation: `phpunit.xml` points to `bolt_landing_test`; the project `.env` points to `bolt_landing` on port 3307.
- Migration status: **44 Ran / 0 Pending**; `db:verify-capability` detected MariaDB 10.11.18 and verified the student FK; backfill dry run made no changes and returned zero rows in every outcome category.
- Full suite: **468 passed / 2,919 assertions**; Pint and Blade compilation passed; scheduler lists five tasks. Herd PHP 8.4.25 was explicitly invoked for PHP checks.
- The owner-reported backup and sibling `b11` state remain unverified from this shell, as does any remote production deployment.
