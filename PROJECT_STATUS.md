# Project Status — Egyptian Arabic with Abdallah

**2026-10-01 v9.4 review:** The independent second pass found and repaired booking intake, form publication/autosave, timezone cache, status rendering, resource flow, billing provenance, and scheduler defects. See [the requirement matrix and current verification results](CODEX_V94_SECOND_PASS_AUDIT.md). The release/deployment statements below describe the historical 2026-09-25 V3 release; they do not establish deployment or live-browser verification of these new repairs.

**Review date:** 2026-09-25

**Specification priority:** `Arabic w Abdallah EDIT V3.md` > `Arabic w Abdallah Edits V2.md` > `ARABIC TUTORING WEBSITE FINAL 16 Sep.md`

**Status:** Independently checked on 2026-09-25 with Herd PHP 8.4.25: MariaDB 10.11.18 is connected, all 44 application migrations are Ran, the student FK/capability gate passes, and backfill dry run reports zero rows in every outcome category. The latest full suite passes at **472 tests / 2,977 assertions**; Pint, Blade compilation, production frontend build, and five scheduled tasks also pass. Commit `7123f15` is pushed to GitHub and deployed to Hostinger; production dependencies, migrations, caches, assets, live routes, and admin login were verified. One external HIGH gate remains: off-host backup/restore, recurring scheduler evidence, and alerting are not yet proven (FA-005).

The earlier V2-only status and its 404-test baseline are superseded by this report. Public booking remains the free intake flow; session credits are required only for authenticated student-portal bookings, as clarified by the owner.

## Phase 0 Reconciliation Report

- **Stack:** Composer requires PHP `^8.4.1`; the Herd runtime available here is PHP `8.4.25`. The lockfile pins Laravel `13.32.0` and Livewire `4.4.5`; the frontend lockfile pins Tailwind `4.3.3` and Vite `8.3.0`. The existing UI uses Blade, Alpine directives, and Livewire components.
- **Primary keys:** Existing and V3 migrations consistently use Laravel `$table->id()` / `foreignId()` big-integer keys.
- **Database:** BoltLanding's `.env` targets `bolt_landing` at `127.0.0.1:3307` as `root`; the password value was not read. Herd PHP 8.4.25 `artisan migrate:status` independently showed 44 Ran / 0 Pending. `db:verify-capability` detected MariaDB 10.11.18 and verified `bookings.student_id → students.id ON DELETE SET NULL`. The read-only student backfill dry run returned zero in every outcome category. `phpunit.xml` targets the separate `bolt_landing_test` database. Hostinger migrations and backfill dry run were also completed; a full independent production data audit remains out of scope.
- **Authorization:** Existing admin auth uses `administrators.role` (`super_admin` as owner, `admin`, `assistant`) and `EnsureAdminRole`; student access uses the dedicated `student` guard/session middleware. V3 student actions integrate through these existing boundaries.
- **Booking integration:** UTC booking snapshots and availability remain in `Booking`, `AvailabilityService`, `TimezoneService`, and the existing `booking_calendar_locks` date rows. Public booking uses a server-side authenticated hold; student booking/rescheduling uses encrypted, expiring, visitor-bound `SlotResolver` identities. `BookingService`, `StudentBookingService`, `RescheduleService`, and `CancellationService` share `DatabaseCapability` transactions and the calendar-date mutex.
- **Accessible context:** Recent Git history (`git log -20`) and project-root specifications/status files are accessible and were inspected. Only non-secret DB host/port/name/username values were read from the local `.env`; private keys and secrets were not copied into this report. Hostinger deployment state was inspected through the configured SSH/Browser workflow for the release smoke checks below.

This reconciliation is retrospective to the current implementation pass. Read-only checks independently verified the local database/migration gate after the owner confirmed the connection. No additional credentials or server-wide database changes were needed.

## Executive Summary

The current repository includes V3 student identity/authentication, student self-service and rescheduling, dynamic forms, append-only package/credit/payment/refund records, SEO articles with HTMLPurifier, admin RBAC/audit support, backfill tooling, and fail-closed database capability checks. The final full suite on Herd PHP 8.4.25 against isolated MariaDB passed: **472 tests and 2,977 assertions**. Production build, Pint, Blade compilation, all 44 application migrations, capability/FK check, read-only backfill dry run, and scheduler listing also passed.

Independent verification found one test expectation left behind by the older V2 lifecycle: a repeated cancellation was expected to throw, although V3 requires idempotent success. The test now performs a first cancellation, repeats it, and proves it creates no duplicate cancellation event. Two important V3 proof gaps were also closed: refund contention now runs in separate PHP processes, and the MariaDB query plan test verifies the ledger package index is used. A cold-start suite run also exposed that `ExampleTest` lacked `RefreshDatabase`; the trait has been added so the DB-backed homepage test bootstraps its isolated test schema itself.

The current continuation also found that student backfill always created a new legacy profile when a contact had an exact canonical name/email/phone match to one existing active student. Backfill now links that exact match, keeps partial matches in duplicate-review triage, and reports the same distinction in dry-run counts. Its focused regression test is included in the independently passing 472-test suite; the live dry run also returned zero rows in every category.

This final V3 pass found two additional implementation gaps and one proof gap. Assistants could access public CMS draft-preview routes because those routes only checked web authentication; preview URLs now use a dedicated role middleware that returns 403 for guests and assistants while allowing Admin/Owner previews. The student upcoming-session card omitted the required tutor and meeting details, and booking confirmations/calendar files used a generic Google Meet homepage as if it were a room link; Owner Settings now accepts only HTTPS lesson URLs and student cards, confirmations, and `.ics` files use the configured URL or explicitly show that no link is configured. New MariaDB SQL-event tests assert the actual lock tier order for student booking and student merge. The real-process concurrency tests remain in the full suite.

The latest V3 recheck caught three further requirement mismatches: unchecked form metadata checkboxes were omitted from requests and therefore retained their old `true` values; the admin student search did not reuse canonical name/email/phone normalization; and future bookings with status `held` were excluded from the upcoming card. The update handler now persists omitted booleans as `false`, search uses `StudentIdentityService` (including explicitly international E.164 phone input without country guessing), and the dashboard includes `held` while preserving legacy `pending` display. Regression tests cover each behavior.

Read-only application-database checks were run after the owner confirmed the connection: `migrate:status` showed 44 Ran / 0 Pending, the capability command verified MariaDB 10.11.18 and the FK, and the explicit backfill dry run returned zero rows. No migration or backfill apply operation was run. A previous PHP/PDO probe reported `auth_gssapi_client` before the reported auth correction; it is stale and must not be presented as the current connection result. No password or server-wide auth change was made.

## Latest Continuation Delta

- Added HMAC-keyed student-authentication limit coverage across rotating IPs.
- Rescheduling now records admin/system actions in `session_reschedules` and serializes linked-student mutation in the canonical lock order; regression coverage includes admin history, ownership denial, ledger neutrality, and the fixed UTC cutoff across Cairo DST transitions.
- **V3 reschedule reconfirmation invariant:** `RescheduleService::reschedule()` sets `admin_reconfirmation_needed = true` for every actor. Admin HTTP/history/flag/ledger-neutrality regressions are included in the independent full-suite pass.
- **V3 confirmed-only cancellation:** `CancellationService::cancel()` accepts only `confirmed` (while preserving repeated-cancel idempotency). The pending-status/event/ledger regression is included in the independent full-suite pass.
- Added prompt-trigger form assignment checks to the student dashboard, GET, and transactional submission path, with coverage for booking, reschedule, next-session, and direct-access denial.
- Added a merge-session invalidation request test and SQL-projection assertions proving assistants do not select payment/financial columns.
- Read-only local checks now show 44/44 migrations, verified MariaDB 10.11.18 and student FK, and a zero-row backfill dry run. These results supersede the previous 39/5 migration snapshot and the stale historical PDO probe.
- V3 preview authorization now denies assistant access to home/about/page/resource/game/FAQ drafts with 403 while retaining Admin/Owner access; guest preview requests retain their established 403 behavior.
- Added an Owner-managed HTTPS lesson-room URL. Student upcoming-session cards now identify the tutor and link to the configured room; public confirmation and `.ics` output no longer invent a generic Google Meet homepage when unset. The actual private meeting URL still requires owner configuration.
- Added SQL-event assertions that verify booking and merge lock acquisition proceeds through each populated tier in calendar/resource → student → booking → package → payment/refund/ledger order. Existing independent-process booking-vs-merge and refund race checks continue to pass.

## Final Audit Remediation Delta (2026-09-24)

- **FA-001 fixed:** Added `app/Domains/CMS/Services/RichTextSanitizer.php` and applied one allow-list/same-origin-image policy to generic page create/update, translation, draft/publish, restore, preview, and public rendering. `ArticleService` reuses the shared sanitizer; `FinalAuditRemediationTest` proves script, event-handler, and `javascript:` payloads are removed.
- **FA-002 fixed:** `AppServiceProvider::boot()` now keys authenticated student reschedule attempts by student session and booking, while token routes retain token identity and every request retains an IP limit. Cross-student isolation is tested.
- **FA-003 fixed:** `AnalyticsDashboardController` and `ReportController` now use exact inclusive Cairo 7/30/90-day starts (`subDays(6/29/89)`). Controller-boundary tests cover all three presets.
- **External gate remaining:** FA-005 (prove target-host recurring scheduler/queue health, off-host backup, alerting, and full restore drill). The former FA-004 deployment gate is resolved by the Hostinger verification below. Production has `BACKUP_OFFSITE_DISK=s3` and AWS setting names, but an actual off-host transfer was not run because it would export production data without explicit egress approval.

## Production Deployment Verification (2026-09-24/25)

- **Repository:** GitHub `origin/main` contains commit `7123f15` (`Deploy audited V3 release and fix production readiness`). The Hostinger checkout at `/home/u494520852/domains/mohamedateff.com/arabictutor_app` was reset to the same commit; the separate `public_html/arabictutor` wrapper was preserved.
- **Runtime:** Hostinger PHP 8.4.19; `APP_ENV=production`; `APP_DEBUG=false`; MariaDB 11.8.9 capability variables are present.
- **Database:** A backup was taken before migrations; both pending migrations completed; `migrate:students-backfill --dry-run` returned zero outcomes; caches were rebuilt.
- **Live smoke:** `/arabictutor/`, `/arabictutor/admin/login`, `/arabictutor/student/login`, and `/arabictutor/articles` returned expected responses. A real admin login reached `/arabictutor/admin`, confirming the `audit_logs.event_uuid` 500 is fixed.
- **Asset serving fix:** Hostinger's existing `public/build` and wrapper `build` directories were mode `700`, causing 404s for present Vite CSS/JS files. They are now `755` with files `644`; the exact CSS, JS, and manifest URLs return `200`, and browser verification shows styled admin/public pages with no console errors. The README runbook includes the permission check for future releases.
- **Operations:** HPanel shows once-per-minute `schedule:run` and bounded `queue:work` cron jobs. Manual scheduler and queue commands completed. A post-migration full backup was created and passed `unzip -t`; the archive manifest reports Laravel 13.32.0, PHP 8.4.19, and SHA-256 `29db51c911f3ab3a13c312f724ddb9f2513006a1f7ab26ac751820e4c472c4b8`. Actual S3 transfer and restore remain unverified.

## V3 Requirement Verification

| Area | Code verdict | Evidence / qualification |
| --- | --- | --- |
| Timezone country resolution and authoritative public slot identity | VERIFIED | `TimezoneDisplayService::resolveCountryCode()` uses `DateTimeZone::getLocation()` plus narrow overrides; signed slot resolution and manual timezone tests pass. |
| Student identity normalization and passwordless verification | VERIFIED IN FULL SUITE | NFKC/Unicode name normalization, E.164 phone normalization with country context, DOB plus two identifiers, verified-only matching, generic failure, HMAC limits, consecutive cooldown, and canonical admin search are implemented; the 472-test suite independently passes. |
| Student session/ownership boundary | VERIFIED | No remember-me login; 180-minute absolute expiry, session regeneration, per-request live-status cache check, own-record query scoping, and student-only session key clearing are implemented. |
| Student portal, credits and rescheduling | VERIFIED IN FULL SUITE | Public intake stays credit-free; student bookings debit FIFO credits atomically; student rescheduling requires a signed slot, ownership check and fixed 24-hour UTC cutoff without ledger mutation. Upcoming-session cards include `confirmed`, `held`, and legacy `pending` future bookings, tutor identity, and a configured HTTPS meeting link; unset links are disclosed instead of faked. Admin-history, ownership, ledger-neutrality, DST-boundary, and lock-order regressions are included in the independent 472-test pass. |
| Merge and privacy erasure | VERIFIED IN FULL SUITE | Merge reassigns ownership under deterministic calendar/student/booking/package/payment locks, preserves historical facts, flattens merge pointers, invalidates auth cache, and records a redacted audit event; anonymization preserves unrelated audit metadata. The independent full suite passes. |
| Dynamic forms | VERIFIED IN FULL SUITE | Published and active versions are separate; drafts stay private until publish. Tests cover version freeze, base-version conflicts, server validation, canonical answers, immutable snapshots, assistant visibility, trigger assignment, streamed CSV, and persistence of unchecked metadata flags as `false`; the independent full suite passes. |
| Billing, refunds and ledger | VERIFIED IN TEST DB | Balances derive from append-only entries; FIFO expiration uses Cairo business dates; refund service locks student/package/payment and enforces per-payment/package ceilings. Sequential, actual cross-process, and EXPLAIN/index tests pass. |
| Articles CMS | VERIFIED IN FULL SUITE | HTMLPurifier allowlist, same-origin storage-image validation, optimistic locking, revisions and 301 slug redirects are covered by implementation and tests; the independent full suite passes. |
| RBAC, audit and social links | VERIFIED IN FULL SUITE | Owner/admin/assistant/student access boundaries, explicit assistant projections, assistant denial from all draft previews, semantic PII scrub, and local SVG social icons are covered by tests in the independent full-suite pass. |
| Backfill and local database gate | VERIFIED LOCALLY; HOSTING MIGRATION PASS | Local `migrate:students-backfill --dry-run` returned zero rows in all outcomes; all 44 migrations, the student FK, and MariaDB 10.11.18 capability gate independently pass. Hostinger migrations and dry-run backfill also completed successfully. |

## Fixes and Verification Added During This Pass

- Fixed MariaDB migration ordering where dropping a referenced unique index before adding its replacement index failed with error 1553; the supporting index is now added first and removed last.
- Corrected the student admin eager-load projection to select the actual session-type `title` column rather than the `name` accessor, which is not a physical column.
- Preserved historical creation timestamps during merge/privacy operations despite MariaDB `TIMESTAMP` auto-update behavior.
- Reworked student backfill application into bounded transactions with canonical calendar-date locks, sorted student/booking locks, safe national-phone triage, and drift detection.
- Corrected student backfill identity reconciliation: an exact match across all available normalized contact identifiers links the booking to the existing active student; partial single matches remain new legacy profiles with review pointers, and ambiguous matches remain unlinked for triage. Dry-run counts now account for exact links and duplicate candidates.
- Prevented idempotent student-booking replay from sending a duplicate admin notification; replaced an unsafe legacy test-only booking helper path with a real hold-backed booking test.
- Added independently coordinated multi-process coverage for adjacent bookings, booking-vs-merge, and separate-payment refunds; added an EXPLAIN assertion for the ledger package index.
- Updated the cancellation lifecycle test to match V3 idempotency while retaining the cancelled-reschedule denial assertion.
- Added the V3 booking/student FK migration and capability verifier. They never disable foreign-key checks; unsupported/unsafe online DDL stops with a logged manual deployment strategy.
- Fixed form publication isolation: active draft versions no longer replace the student-visible version before publish; student writes use the published pointer, and default admin response/CSV views target the published version. Added persisted-version and admin-view regression assertions.
- Scoped privacy-erasure request-metadata removal to audit rows tied to the erased student or containing that student's identifiers; unrelated audit IP/user-agent evidence is retained and tested.
- Hardened article image URL validation against multiply encoded dot-segment/backslash traversal before allowing same-origin `/storage/` URLs.

## Verification Baseline

| Check | Result |
| --- | --- |
| Latest full Laravel/PHPUnit suite | **INDEPENDENT PASS — 472 tests, 2,977 assertions, 0 failures/errors** on MariaDB via Herd PHP 8.4.25 |
| Prior full Laravel/PHPUnit suite | **PASS — 448 tests, 2,764 assertions, 0 failures/errors**; superseded by the latest independent result |
| Focused latest feature regressions | **PASS — 41 tests, 322 assertions** across settings, meeting-link UI/ICS, booking/reschedule and lock ordering; preview/RBAC/CMS focused suite: **25 tests, 239 assertions** |
| Focused cancellation regression | **PASS — 11 tests, 29 assertions** |
| Parallel refund regression | **PASS — separate worker processes**; aggregate remains within recorded payments |
| Ledger hot-path query plan | **PASS — MariaDB EXPLAIN selects the package-leading index using a seek/ref plan** |
| Laravel Pint | **PASS — clean under Herd PHP 8.4.25** (`vendor/bin/pint --dirty --format agent`) |
| Production frontend build | **INDEPENDENT PASS — Vite 8.3.0, 2.76 seconds**; non-blocking optional `fontaine` optimization warning |
| Blade compilation | **PASS** (`artisan view:cache`) |
| Composer metadata/dependencies | **Valid**; Laravel 13.32.0, Livewire 4.4.5, `mews/purifier` 3.4.4, libphonenumber 9.0.39 |
| Migration status | **INDEPENDENT PASS — 44 Ran / 0 Pending** on the configured `bolt_landing` database |
| Composer platform | **PASS — all 24 platform requirements** using Herd PHP 8.4.25 |
| Changed PHP syntax/style | **INDEPENDENT PASS** — changed PHP files passed Laravel Pint under Herd PHP 8.4.25; the full suite also passed on that runtime |
| Latest backfill dry-run | **INDEPENDENT PASS — zero rows in every outcome category; no records changed** |
| Latest Pint check | **INDEPENDENT PASS** under Herd PHP 8.4.25 |
| Scheduler | **5 tasks listed locally; Hostinger cron entry present and manual `schedule:run` completed**. Fresh recurring log evidence remains open under FA-005. |
| Routes | **PASS — preview route listing confirms six public CMS preview endpoints use `EnsureAdminPreviewAccess`; admin article preview retains Admin/Owner role gate** |
| Student backfill | **INDEPENDENT PASS — dry run found zero rows in all outcome categories** |
| Database capability / student FK | **INDEPENDENT PASS** — MariaDB 10.11.18 and `bookings.student_id → students.id ON DELETE SET NULL` |
| Whitespace | **PASS** (`git diff --check`) |

| Independent verification in this shell | **PASS FOR LOCAL GATES** — full suite, build, Pint, Blade compilation, migration status, DB capability/FK, backfill dry run, and scheduler listing were independently run using Herd PHP 8.4.25. Hostinger release and live login/route smoke are verified separately; off-host recovery remains open |

## Remaining High-Priority Release Gates

No unresolved local application-code CRITICAL/HIGH issue remains after the final audit remediation. The three prior local HIGH defects (generic page rich-text sanitization, student reschedule limiter isolation, and exact Cairo report preset boundaries) are fixed and covered by the 472-test suite. The reviewed release is deployed and live route/admin-login smoke passed. One external HIGH release gate remains: **FA-005**, target-host recurring scheduler/queue evidence, off-host backup, alerting, and full restore drill. The owner must also configure the actual private lesson-room URL before students see a join link.

Two V3 business-rule mismatches found earlier were corrected: all reschedules set the admin-reconfirmation flag, and only confirmed bookings can be cancelled. Their regression tests are included in the independently passing suite.

See `Gemini.md` for the current remediation ledger and `walkthrough.md` for the implementation summary. The older `CODEX_CRITICAL_REVIEW.md` and `CODEX_PROJECT_REVIEW.md` remain historical audit records; their findings predate V2/V3 and are not current verdicts.
