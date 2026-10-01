# V3 Implementation Walkthrough

**2026-10-01 v9.4 follow-up:** An independent review found additional defects and repaired them. Current findings, changed paths, verification results, and remaining limits are in [CODEX_V94_SECOND_PASS_AUDIT.md](CODEX_V94_SECOND_PASS_AUDIT.md). The V3 checks and deployment statements below remain dated historical evidence.

**Updated:** 2026-09-25

**Specification:** `Arabic w Abdallah EDIT V3.md` (newest priority)

**Current status:** Local V3 release checks were independently run on 2026-09-25 with Herd PHP 8.4.25. The full suite passes at 472 tests / 2,977 assertions against the isolated `bolt_landing_test` database; the production build and Pint pass; the application database has 44/44 migrations, the MariaDB 10.11.18 capability/FK check passes, and the read-only student backfill dry run reports zero rows. Five scheduled tasks are registered. Commit `7123f15` is deployed to Hostinger, live route/admin-login smoke checks pass, and one operational gate remains for off-host recovery/recurring evidence.

## Implemented and Verified

1. Timezone country detection now uses PHP's IANA timezone location metadata with narrow exception overrides. Public/student booking uses server-authorized slot identity and retains UTC as the appointment source of truth.
2. Student identity uses Unicode NFKC/name normalization, lowercase email, explicit-region E.164 phone normalization, DOB plus two matching identifiers, verified-only records, generic failure messages, HMAC identity limits, consecutive-failure cooldowns, and no remember-me login.
3. Student sessions regenerate on login, expire absolutely after 180 minutes, verify live student status on each protected request, and clear only student-scoped session data on logout/expiry.
4. Student portal booking consumes an eligible package credit atomically using FIFO rules; public intake remains free. Student rescheduling is ownership-checked, slot-resolved server-side, fixed-UTC 24-hour gated, and ledger-neutral.
5. Merge and anonymization preserve financial/event facts, reassign ownership under deterministic locks, flatten merge pointers, invalidate auth status cache, scrub student identifiers from audit payloads, clear request metadata only on related/redacted audit rows, and use semantically valid anonymized values.
6. Dynamic form versioning separates the active draft from the published pointer; students and default admin response/CSV views use the published version. Base-version conflicts, frozen versions, server-side conditional logic, canonical answers, immutable submission snapshots, role-based answer visibility, and streamed CSV export are implemented.
7. Billing uses append-only package/payment/refund/credit records, derived balances, business-timezone expiration, idempotency, and package-level refund locking/ceilings.
8. Article administration uses HTMLPurifier allow-lists, strict same-origin storage-image path checks including nested-encoding traversal rejection, revisions, optimistic version checks, unique translation grouping, and permanent redirects for changed slugs.
9. Owner/admin/assistant/student authorization and projections, append-style audit support, social-link validation/icons, and DB capability checks are implemented.
10. Backfill is bounded and defaults to a read-only dry-run. Independent dry-run verification returned zero rows in every outcome category and confirmed no records changed.

## Verification and Corrections During This Pass

### Latest continuation — independent release verification

- Added a dedicated role gate to all six public CMS draft-preview routes. Guest requests retain the established 403 contract, assistants are denied, and Admin/Owner previews remain available.
- Student upcoming-session cards now show the configured tutor identity and a private HTTPS lesson-room link. Owner Settings validates that URL; confirmation pages and `.ics` files no longer substitute the generic Google Meet homepage when no room is configured.
- Added SQL-event assertions for the canonical lock-tier order in student booking and merge. Existing independent-process MariaDB concurrency tests remain in place.

- Added cross-IP HMAC student-authentication limit coverage; admin reschedule history/lock-order coverage; Cairo DST cutoff tests; form-trigger assignment and direct-access tests; merged-session invalidation coverage; a pending-cancellation/credit invariant test; and assistant financial-column SQL projection assertions.
- Updated rescheduling to record admin/system actors and lock linked students before booking mutation. Added a form assignment service enforcing published prompt triggers on dashboard, GET, and transactional POST.
- Reconciled the reschedule flag with V3: every actor sets `admin_reconfirmation_needed = true`; admin history, flag, ownership, and credit-neutrality regressions are included in the independently passing full suite.
- Tightened cancellation to confirmed bookings only; the pending-status/event/ledger invariant regression is included in the independent full-suite pass.
- Added `RefreshDatabase` to `tests/Feature/ExampleTest.php` after a cold-start run exposed that the DB-backed homepage test relied on a previously migrated schema. The focused test passed after its own fresh test migration, then the full suite passed.
- Final V3 audit fixed three additional requirement gaps: unchecked form metadata checkboxes now persist as `false`; admin student search reuses canonical identity normalization including explicit E.164 phone input; and future `held` bookings now appear in the student Upcoming sessions card. Regression tests cover all three.
- Final focused tests passed: 16 tests / 155 assertions across forms, admin authorization/search, and student rescheduling/dashboard.
- Full suite: **472 tests, 2,977 assertions, zero failures/errors** under Herd PHP 8.4.25 using `bolt_landing_test`.
- `vendor/bin/pint --dirty --format agent` passed; Vite 8.3.0 production build passed in 2.76 seconds (optional Fontaine warning); Blade view cache compilation and `git diff --check` passed.
- Application DB gates: `migrate:status` showed 44 Ran / 0 Pending; `db:verify-capability` detected MariaDB 10.11.18 and verified the student FK; student backfill dry run returned zero rows and made no changes.
- `schedule:list` showed five configured jobs. Hostinger HPanel lists the scheduler and queue cron entries; manual scheduler and bounded queue commands completed. Fresh recurring log evidence remains open.

- Corrected MariaDB index migration order and a student-admin query that selected a computed accessor as if it were a SQL column.
- Preserved creation timestamps in privacy/merge paths despite MariaDB TIMESTAMP auto-update behavior.
- Added sorted calendar/student/booking locks and booking-time drift detection to the backfill apply path.
- Fixed canonical identity reconciliation in the student backfill: exact matches across available normalized name/email/phone fields now link to the existing active student instead of creating a duplicate; partial matches stay flagged for review, ambiguous matches remain unlinked, and dry-run totals reflect these outcomes. Added a focused feature regression test.
- Updated an obsolete cancellation test to assert V3 idempotent repeat behavior without duplicate events.
- Added true independent-process refund concurrency coverage and a MariaDB EXPLAIN assertion for the ledger package index.
- Fixed form draft leakage into the student flow and made response/export defaults follow the published version; added end-to-end assertions for publication and saved response versioning.
- Fixed privacy erasure clearing IP/user-agent metadata from unrelated audit rows; added a test proving unrelated audit evidence remains intact.
- Hardened article image URL validation against nested percent-encoded path traversal and added sanitizer regression coverage.
- Latest full result: **INDEPENDENT PASS — 472 tests, 2,977 assertions, zero failures/errors**. Previous 468-, 465-, 455- and 448-test results are superseded. Focused meeting-link/settings/student/lock-order tests passed (**41 tests, 322 assertions**); preview/RBAC/CMS tests passed (**25 tests, 239 assertions**); final checkbox/search/held-status regressions passed (**16 tests, 155 assertions**); final audit remediations passed (**3 tests, 54 assertions**).

## Release Verification Notes

The previous **39 Ran / five Pending** snapshot is superseded by the independently observed 44 Ran / 0 Pending on port 3307. The capability command verified MariaDB 10.11.18 and `bookings.student_id → students.id ON DELETE SET NULL`; the backfill dry run returned zero rows. `phpunit.xml` targets the separate `bolt_landing_test` database. Hostinger was updated to commit `7123f15`, production migrations/backfill completed, and a post-migration backup passed archive integrity. Off-host storage, recurring execution, alerting, and a full restore drill remain open.

A Hostinger asset-serving regression was corrected after the release: existing `build` directories were mode `700`, causing 404s for present Vite CSS/JS files. The deployed asset directories are now `755` with files `644`; CSS, JS, and manifest requests return 200, and the README runbook documents the permission check.

See `PROJECT_STATUS.md` for the verification provenance and `Gemini.md` for the remediation ledger. The DB-backed checks, full suite, and Hostinger deployment/smoke checks listed above were independently verified; FA-005 is the remaining operational release gate.
