# Final Project Audit

Audit date: 2026-09-25  
Repository revision reviewed: `3c3d657` (`main`, matching `origin/main`)  
Runtime reviewed: PHP 8.4.25, Laravel 13.32.0, Livewire 4.4.5, Tailwind CSS 4.3.3, Vite 8.3.0, MariaDB 10.11.18/InnoDB

## 1. Executive Summary

The application is substantially implemented. The original public site, booking engine, resource gate, CMS, analytics, reports, administration, V1 localization/analytics amendments, V2 brand/geolocation/pricing amendments, and V3 student/forms/billing/articles/RBAC modules all exist and are connected end to end.

The audit did **not** find a critical booking, timezone, authorization, student-credit, or data-loss defect. The normal full suite passed with 472 tests and 2,979 assertions, the production frontend build passed, all 44 application migrations are applied, and the five expected scheduler entries are registered.

The project is **not yet fully complete**. Four verified HIGH issues remain:

1. production scheduler/queue/off-host-backup/alert/restore evidence is still incomplete;
2. the media library can delete an image still referenced by an article;
3. migration tests mutate shared MariaDB schema and make the suite order-dependent;
4. the XLSX export path exhausted the normal 128 MB PHP memory limit during a randomized full run.

There are no missing top-level modules. V1 is complete in application behavior, V2 is complete in application behavior, and V3 is partial because of the article/media integrity defect. Operations remain only partially verified on the actual host.

## 2. Critical Issues

No verified CRITICAL issue remains.

The booking collision path was inspected beyond test names: competing processes acquire pre-existing, buffer-expanded `booking_calendar_locks` rows before conflict checks and writes. Real multi-process MariaDB tests exist and passed in the normal suite.

## 3. High-Priority Issues

### FA-005 — Production automation and disaster recovery are not fully proven

- **Severity:** HIGH
- **Type:** PARTIAL / NOT VERIFIED
- **Evidence:** `routes/console.php`; `app/Domains/System/Services/BackupService.php:94`; `app/Http/Controllers/Admin/SystemHealthController.php:75`; `README.md`; `PROJECT_STATUS.md`; `Gemini.md`
- **Problem:** The code and schedule definitions exist, but the repository contains no current target-host evidence of recurring scheduler and queue-worker execution, a successful real off-host object, delivered failure alerts, or a complete restore drill from the off-host copy. This audit could not inspect Hostinger because SSH authentication was rejected.
- **Impact:** A production incident could reveal that cron, the queue worker, off-host replication, alert delivery, or restoration was never actually operating.
- **Minimum correction:** On the production host, capture fresh heartbeat/queue timestamps, verify a real remote backup object and checksum, force and receive a test failure alert, restore that remote archive into an isolated database/storage target, run integrity/smoke checks, and record date, operator, archive, result, and recovery time.

### FA-006 — Article featured images bypass media reference protection

- **Severity:** HIGH
- **Type:** INCORRECT / V3 REGRESSION RISK
- **Evidence:** `app/Domains/CMS/Models/Media.php:45` and `:107`; `app/Http/Controllers/Admin/MediaController.php:107`; `app/Http/Controllers/Admin/ArticleController.php:61`; `app/Domains/CMS/Models/Article.php`; `database/migrations/2026_09_23_164114_create_articles_tables.php:18`
- **Problem:** `ArticleController::validated()` stores `featured_image_path` from the media table, but `Media::getReferences()` and `Media::isPathReferenced()` check pages, resources, games, settings, and content revisions—not `Article::featured_image_path`. `MediaController::destroy()` relies exclusively on `getReferences()` before deleting the record and physical file.
- **Impact:** An authorized admin can delete an image used by a published or draft article, leaving the public article with a broken featured image and destroying the underlying asset.
- **Minimum correction:** Treat article featured-image paths and their public URLs as media references in both reference methods. Block deletion while any published, draft, or archived article points at the asset.

### FA-007 — Migration safety tests corrupt the shared test schema when order changes

- **Severity:** HIGH
- **Type:** BROKEN TEST ISOLATION
- **Evidence:** `tests/Feature/MigrationRollbackSafetyTest.php:126-190`; `tests/Feature/DailyMetricsMigrationTest.php`; `phpunit.xml`; MariaDB randomized audit run
- **Problem:** `test_pristine_seed_rollback_cleans_up_when_no_authored_content_exists()` directly calls historical migration `down()` methods and drops translation tables. MariaDB DDL auto-commits, so `RefreshDatabase` transactions cannot restore that schema. Other migration tests also alter columns with DDL. The migration ledger remains marked as fully run while physical tables/columns are missing or old.
- **Observed result:** The normal ordered suite passed, but `php artisan test --compact --order-by=random` with 512 MB completed with only 456/472 passing: 7 failures and 9 errors, including missing `administrators`, `settings`, and `marketing_touches` tables and stale `audit_logs`/`visitor_sessions` schemas.
- **Impact:** Green results depend on file/test order. The suite can conceal state leakage and cannot be treated as deterministic release proof.
- **Minimum correction:** Run destructive migration scenarios in a dedicated disposable database/connection or external process that creates and destroys its own database. Never execute DDL rollback tests against the shared suite database. Add a randomized-order CI gate.

### FA-008 — XLSX export can exceed the configured PHP memory limit

- **Severity:** HIGH
- **Type:** PRODUCTION RELIABILITY / TEST FAILURE
- **Evidence:** `app/Domains/Reporting/Services/ExportService.php:96-123`; `tests/Feature/AnalyticsAndReportsTest.php:307`; `php.ini` memory limit 128 MB; `maennchen/zipstream-php/src/File.php:334`
- **Problem:** The exporter materializes the workbook in memory, enables auto-sizing for every column, writes it through PhpSpreadsheet/ZipStream, and does not explicitly disconnect worksheets after saving. During a randomized full run at the normal 128 MB limit, `test_admin_can_export_xlsx_report` fatally exhausted memory while ZipStream requested another 16 MB. The same test passed alone, proving cumulative/order-sensitive memory pressure.
- **Impact:** A long-lived worker or a sufficiently large export can return HTTP 500 and fail to cleanly complete the report download.
- **Minimum correction:** Bound export size or stream/chunk the export through a memory-safe writer, avoid expensive full-column autosizing for large reports, dispose workbook resources in `finally`, and retain temporary-file cleanup on failure. Add a representative large-export test at 128 MB plus a full randomized suite gate.

## 4. Missing Implementations

No required top-level module is absent.

The following required operational outcomes remain uncompleted rather than missing in code:

- actual off-host backup and restore proof;
- verified recurring production scheduler and queue execution;
- verified alert delivery.

## 5. Incorrect / Partial Implementations

| Area | Status | Evidence | Defect |
| --- | --- | --- | --- |
| Article/media integration | INCORRECT | `Media::getReferences()`, `MediaController::destroy()`, `ArticleController::validated()` | Article featured images are not protected from media deletion. |
| Production recovery | PARTIAL | `BackupService`, `SystemHealthController`, README runbook | Implementation exists; target-host recurring/off-host/restore proof is absent. |
| Database test isolation | INCORRECT | `MigrationRollbackSafetyTest`, `DailyMetricsMigrationTest` | DDL mutates the shared MariaDB test schema and survives transactions. |
| XLSX export | PARTIAL | `ExportService::xlsx()` | Correct output for focused tests, but not reliable within the configured memory envelope. |

## 6. Risks

### R-01 — Offsite health status is not tied to the latest backup

- **Severity:** MEDIUM
- **Evidence:** `BackupService.php:94-108`; `SystemHealthController.php:76-101`
- **Realistic impact:** `last_offsite_backup_status = success` has no timestamp or archive identity. A later local backup can be healthy while the displayed offsite success refers to an older archive; a configured offsite disk with a null status is also not made unhealthy.
- **Required adjustment:** Persist offsite archive name, checksum, timestamp, and status for every run; require the offsite success record to match the latest local backup.

### R-02 — Two runtime dependency constraints are unbounded

- **Severity:** MEDIUM
- **Evidence:** `composer.json:11` and `:16`; `composer validate`
- **Realistic impact:** The lock file currently pins safe versions, but a fresh dependency resolution could select an incompatible future release of `giggsey/libphonenumber-for-php` or `mews/purifier`.
- **Required adjustment:** Replace `*` with compatible major/minor constraints after confirming installed APIs, then update and test the lock file.

### R-03 — Production revision was not independently verified

- **Severity:** MEDIUM
- **Evidence:** local/origin revision `3c3d657`; SSH returned `Permission denied (publickey,password)`
- **Realistic impact:** Repository state is known; live Hostinger code/assets/config cannot be certified from this audit.
- **Required adjustment:** Restore authorized read-only deployment access and compare production `git rev-parse HEAD`, Vite manifest/assets, Laravel caches, migrations, and health output with the release revision.

## 7. V1 / V2 / V3 Verification

### V1

| Requested edit group | Status | Evidence |
| --- | --- | --- |
| Cumulative cohort funnel, provenance, maturity, reconciliation | VERIFIED COMPLETE | `FunnelProgressionService`; `FunnelCohortReconciliationAndMaturityTest` |
| Cairo half-open reporting windows and durable rollups | VERIFIED COMPLETE | `ReportService`; `AggregateDailyAnalyticsCommand`; `CairoDailyAnalyticsRollupTest` |
| Visitor/session identity, inactivity, bots, event integrity | VERIFIED COMPLETE | analytics middleware/services; `AnalyticsValidationAndIdentityTest`; `SessionInactivityAndCookieRefreshTest` |
| First/last non-direct attribution and campaign content reporting | VERIFIED COMPLETE | `AnalyticsService`; `ReportService`; attribution tests |
| EN/FR/DE CMS translations, immutable source revisions, stale reconciliation | VERIFIED COMPLETE | translation models/services/admin views; `CmsTranslationAndRevisionsTest`; `AdminTranslationWorkflowTest` |
| Localized routing, fallback banners, canonical/hreflang precision | VERIFIED COMPLETE | localized routes/controllers/views; `LocalizedPublicFlowAndSeoTest` |
| Booking-flow locale switch without PII/token tampering | VERIFIED COMPLETE | booking wizard state/token logic; `BookingLanguageSwitchSyncTest` |
| Embedded Arabic bidi isolation, social footer, mobile admin accessibility | VERIFIED COMPLETE | public/admin layouts; `AdminDrawerAndSocialAccessibilityTest` |
| Migration/data-preservation behavior | VERIFIED COMPLETE in application migrations | rollback guards and migration tests; test harness isolation remains FA-007 |

### V2

| Requested edit group | Status | Evidence |
| --- | --- | --- |
| English “Abdallah” brand source | VERIFIED COMPLETE | setting migration/config/public and admin views |
| Server-derived country detection and trusted proxy behavior | VERIFIED COMPLETE | geolocation services/middleware; country analytics tests |
| Country metrics remain separate from cohort funnel | VERIFIED COMPLETE | `daily_country_metrics`; reporting services/tests |
| Reference-instant timezone offsets and localized display | VERIFIED COMPLETE | `TimezoneService`; timezone tests |
| One active session type, synchronization lock, booking step auto-skip | VERIFIED COMPLETE | session-type services/admin; booking wizard tests |
| Fixed UTC +24h reschedule cutoff | VERIFIED COMPLETE | `RescheduleService`; lifecycle tests |
| Pricing/packages, localized conversion flow, policies | VERIFIED COMPLETE | pricing views/settings/policy pages/tests |
| Canonical CMS entity/translation/revision rules | VERIFIED COMPLETE | CMS translation workflow tests |
| Funnel/marketing touch/retention/cutover amendments | VERIFIED COMPLETE | analytics migrations/services and reconciliation tests |
| Mobile admin/modal behavior and canonical redirects | VERIFIED COMPLETE | layouts/routes and accessibility/routing tests |

### V3

| Requested module/edit | Status | Evidence |
| --- | --- | --- |
| Country flag mapping and authoritative timezone override | VERIFIED COMPLETE | timezone/country services; `V3SlotIdentityTest` |
| Signed, owner-bound, policy-bound server slot identity | VERIFIED COMPLETE | `SlotResolver`; booking/reschedule services and tests |
| Passwordless student identity/auth/session security | VERIFIED COMPLETE | student services/controllers/middleware; `StudentAuthenticationTest` |
| Student dashboard and self-service rescheduling | VERIFIED COMPLETE | student routes/controllers/views; `StudentReschedulingTest` |
| Unified booking-credit transaction and lock hierarchy | VERIFIED COMPLETE | booking/ledger services; real-process MariaDB tests |
| Dynamic versioned forms and conditional answers | VERIFIED COMPLETE | forms domain/admin/student UI; `FormsEngineTest` |
| Streaming forms CSV and formula-injection protection | VERIFIED COMPLETE | forms export path/tests |
| Billing, FIFO credits, append-only ledger, concurrent refunds | VERIFIED COMPLETE | billing services/models; ledger/concurrency tests |
| SEO articles, sanitization, optimistic locking, slug redirects | PARTIAL | article service/controllers/tests work; article media references are omitted (FA-006) |
| RBAC projections, audit log, privacy erasure, social links | VERIFIED COMPLETE | route middleware/controllers/services/tests |
| Historical student backfill and capability gate | VERIFIED COMPLETE | migrations/commands; capability and backfill checks |

## 8. Full Specification Compliance Matrix

| Requirement | Source File | Current Implementation | Status | Evidence | Problem / Risk |
| --- | --- | --- | --- | --- | --- |
| Public Book/Learn/Play site | Original §§7-13, 118-123 | Public routes, Blade pages, responsive navigation and conversion sections | VERIFIED COMPLETE | `routes/web.php`; `resources/views` | None found. |
| UTC canonical booking storage | Original §§14-22 | UTC timestamps plus IANA and snapshot metadata | VERIFIED COMPLETE | booking migrations/models/services; timezone tests | None found. |
| Recurring availability, exceptions, buffers, notice, horizon | Original §§23-25, 36 | Canonical availability engine | VERIFIED COMPLETE | `AvailabilityService`; availability tests | None found. |
| Holds, ownership, expiry and cleanup | Original §§26-28 | Authenticated hold token/visitor binding and scheduled cleanup | VERIFIED COMPLETE | `BookingHoldService`; hold tests; scheduler | None found. |
| Real simultaneous booking protection | Original §27 | Buffer-expanded pre-existing calendar lock rows plus InnoDB transaction | VERIFIED COMPLETE | `BookingService`; `BufferExpandedConcurrencyLockTest`; worker script | Real multi-process test passes. |
| Idempotency, states and history | Original §§29-31 | Unique key, locked retries, append-only events | VERIFIED COMPLETE | booking schema/services/tests | None found. |
| Reschedule/cancel lifecycle | Original §§32-33; V2 §5.4; V3 Module 3 | Server-resolved slots, fixed +24h cutoff, idempotent credit restore | VERIFIED COMPLETE | lifecycle/student tests | None found. |
| Confirmation and ICS | Original §§34-35 | Signed/token access and UTC calendar output | VERIFIED COMPLETE | confirmation controller/tests | None found. |
| Resources, gates and private downloads | Original §§37-43 | Private disk, single-use scoped tokens, safe filenames, request/download events | VERIFIED COMPLETE | resource services/controllers/tests | None found. |
| Media reference-safe deletion | Original §44; V3 Module 6 integration | Reference checks exist for most CMS entities | INCORRECT | `Media.php:45`; `ArticleController.php:68` | Articles are omitted; FA-006. |
| Games and analytics | Original §§45-46 | Admin CRUD, public listing/external launch, events | VERIFIED COMPLETE | game controllers/views/tests | None found. |
| CMS drafts, previews, revisions | Original §§47-49; V1 §§20-27 | Draft/publish/discard/preview and normalized translations | VERIFIED COMPLETE | CMS services/controllers/tests | None found. |
| Admin auth and roles | Original §§52-53; V3 Module 7 | super_admin/admin/assistant middleware and scoped projections | VERIFIED COMPLETE | `EnsureAdminRole`; routes; authorization tests | None found. |
| Admin dashboard/search/bookings/availability | Original §§54-58 | Connected screens, searches and mutations | VERIFIED COMPLETE | admin controllers/views/tests | None found. |
| Contacts, dedupe, leads and merges | Original §§59-61; V3 Module 2 | Normalization, DB uniqueness, 1062 recovery, locked merge/reparenting | VERIFIED COMPLETE | contact/student services/tests | None found. |
| Settings and maintenance | Original §§62-63 | Owner settings and maintenance middleware | VERIFIED COMPLETE | settings controller/middleware/tests | None found. |
| Analytics ingestion and privacy | Original §§64-78; V1/V2 | Allow-list/schema/size/rate/bot/privacy/attribution controls | VERIFIED COMPLETE | analytics services/middleware/tests | No arbitrary browser event ingestion found. |
| Reports and comparisons | Original §§79-80; V1 | Cairo daily reconciliation and comparable-basis disclosure | VERIFIED COMPLETE | `ReportService`; Cairo rollup tests | None found in calculations. |
| CSV/XLSX exports | Original §§81-82 | CSV/XLSX with formula neutralization and authorization | PARTIAL | `ExportService`; report tests | XLSX memory reliability failure; FA-008. |
| Notifications and append-only audit | Original §§83-84; V3 Module 7 | Admin notifications and immutable audit model | VERIFIED COMPLETE | services/models/tests | None found. |
| Public endpoint/security controls | Original §§85-88 | CSRF, rate limiting, validation, upload rules, no SVG | VERIFIED COMPLETE | middleware/controllers/tests | None found. |
| SEO/accessibility/error states | Original §§89, 96-99; V1/V2 | metadata, canonical/hreflang, focus/drawer/fallback/error views | VERIFIED COMPLETE | layouts/views/tests | None found. |
| Database entities/indexes/FKs | Original §§91-95; V3 schemas | 44 migrations, FK and unique indexes | VERIFIED COMPLETE | migration status; capability check | Test harness can desynchronize its own schema (FA-007). |
| Backups and restore | Original §§105-107 | DB+private/public archive, hashes, retention, offsite adapter, restore preflight | PARTIAL | `BackupService`; backup tests; README | Live offsite/restore evidence absent; FA-005. |
| Scheduler and health | Original §§108-110 | Five schedules and health probes | PARTIAL | `routes/console.php`; `SystemHealthController`; schedule list | Recurring host execution/alerts not independently proven. |
| Automated critical QA | Original §§113-115; V1/V2/V3 test matrices | 472-test suite plus real worker concurrency | PARTIAL | normal pass; randomized failure | Order dependence and memory failure, FA-007/FA-008. |
| V1 analytics/localization amendments | V1 | Implemented end to end | VERIFIED COMPLETE | V1-focused services/tests | None beyond test isolation. |
| V2 brand/country/pricing amendments | V2 | Implemented end to end | VERIFIED COMPLETE | V2 migrations/services/tests | None found. |
| V3 students/forms/billing/articles/RBAC | V3 | All modules present and connected | PARTIAL | V3 domain code/tests | Article/media integration defect, FA-006. |

## 9. Bugs Found

### FA-006 — Deletable article image

- **Severity:** HIGH
- **Affected files:** `app/Domains/CMS/Models/Media.php`; `app/Http/Controllers/Admin/MediaController.php`; `app/Http/Controllers/Admin/ArticleController.php`
- **Root cause:** the media reference registry was not extended when V3 articles were added.
- **Expected:** media referenced by any article cannot be deleted.
- **Actual:** article references are invisible to the deletion guard.
- **Minimum fix:** add article path/URL reference queries to both media reference APIs.
- **Regression test:** create media plus published/draft article, assert delete returns 422/error and file/row survive; clear the reference and assert deletion succeeds.

### FA-007 — Order-dependent database suite

- **Severity:** HIGH
- **Affected files:** `tests/Feature/MigrationRollbackSafetyTest.php`; `tests/Feature/DailyMetricsMigrationTest.php`; test database setup
- **Root cause:** auto-committing MariaDB DDL executes inside the suite's shared database while `RefreshDatabase` assumes transaction rollback can isolate it.
- **Expected:** all tests pass in any order and leave schema consistent with the migration ledger.
- **Actual:** randomized order produces missing tables/columns and 16 failed/error tests.
- **Minimum fix:** isolate DDL migration tests in disposable databases/processes and validate/tear them down explicitly.
- **Regression test:** mandatory `--order-by=random` CI run, followed by schema/ledger parity assertion.

### FA-008 — XLSX memory exhaustion

- **Severity:** HIGH
- **Affected files:** `app/Domains/Reporting/Services/ExportService.php`; `tests/Feature/AnalyticsAndReportsTest.php`
- **Root cause:** in-memory spreadsheet generation and cumulative resource retention exceed the configured limit.
- **Expected:** authorized report export succeeds within the documented PHP memory limit and cleans temporary resources.
- **Actual:** ZipStream fatally exhausted 128 MB in a full randomized run; focused export passed alone.
- **Minimum fix:** use bounded/streamed generation and explicit disposal/cleanup.
- **Regression test:** realistic large export at 128 MB, repeated exports in one process, failure cleanup, and randomized full suite.

## 10. Missing Modules / Functions

Missing required module count: **0**.

Every required functional area has an implementation: public site, booking, resources, games, CMS, articles, analytics, reports, contacts/students, dynamic forms, billing/ledger, admin/RBAC, backups, scheduler, health, authentication, and password reset.

## 11. Security / Authorization Review

- Admin routes use authentication plus explicit role middleware. Owner-only functions include administrator management, settings, health/backups, and destructive student merge/anonymization.
- Assistant student responses use explicit non-financial projections; financial and owner operations are not exposed.
- Student access requires date of birth plus two independent normalized identifiers, uses neutral errors, persistent consecutive-failure tracking, IP/identity throttles, and an absolute session lifetime. Persistent “remember me” is absent.
- Password resets use hashed, expiring, one-use tokens and generic responses.
- Public writes are CSRF-protected and throttled. Analytics accepts only allow-listed events and per-event metadata schemas; server-only conversions cannot be posted by browsers.
- Resource download tokens are random, scoped, single-use, and checked against private storage.
- Upload rules reject SVG and constrain MIME/size; article HTML is purified and image sources are restricted.
- No verified IDOR, mass-assignment bypass, unsafe redirect, raw-IP analytics storage, sensitive token logging, or user-controlled SQL was found.

Security verdict: **no CRITICAL/HIGH security defect found**.

## 12. Booking / Concurrency / Timezone Review

- Public finalization derives times from the authenticated hold, not browser timestamps.
- Calendar mutex rows exist before booking attempts and cover every business date crossed by the session and buffers. Locks are acquired in stable order before hold/booking conflict checks.
- Active holds and bookings are rechecked inside the transaction; hold conversion is atomic; idempotency has database enforcement.
- Multi-process worker tests use separate PHP processes/connections and a start gate. They are not sequential tests labeled “concurrency.”
- Student booking locks calendar, student, package, and ledger state in the documented hierarchy; credit debit and booking commit together.
- Rescheduling uses signed server slot identity, re-locks the calendar, preserves credit, records history, and enforces the fixed UTC cutoff. Cancellation restores credit only once.
- Availability includes recurring hours, special/blocked exceptions, duration overrides, buffers, notice, horizon, bookings, and active holds.
- UTC is canonical. IANA zones, Cairo business dates, customer/manual zones, DST gap/fold behavior, reference-instant offsets, cross-midnight days, and UTC ICS output are covered.

Verdict: **VERIFIED COMPLETE** for application logic; no known double-booking path remains.

## 13. Data Integrity Review

- Contact emails are canonicalized and protected by a unique index; duplicate-key races are recovered.
- Contact/student merge operations use deterministic locks, preserve/reparent bookings, resource activity, forms, packages, payments, refunds, ledger entries, and audit history.
- Student duplicate identifiers are intentionally permitted so ambiguous legacy identities can be classified and merged; login fails closed on ambiguity.
- Booking-to-student FK is present with `ON DELETE SET NULL`; capability check passed.
- Payments, refunds, ledger entries, and audit records are append-only at the model boundary. Concurrent refund/credit ceilings have real-process MariaDB tests.
- Privacy erasure scrubs PII while retaining non-identifying audit/accounting shells.

The application-data findings are sound. FA-006 is the one verified content-file integrity exception. FA-007 affects only the test database/harness, not the application database.

## 14. Analytics / Reporting Review

- Visitor and session IDs are server-bound and rotated after inactivity; active-now semantics are separate from daily totals.
- First non-direct acquisition and last non-direct booking attribution use append-only marketing touches and explicit windows.
- Bot/link-preview data is flagged/excluded; client country values are rejected in favor of server resolution.
- Funnel progression is cumulative, records provenance, uses booking records as authoritative completion, preserves pruned-history milestones, and exposes cohort maturity.
- Cairo half-open UTC intervals, DST boundaries, partial pruning, source isolation, durable rollups, and non-comparable visitor bases are handled and tested.
- CSV/XLSX values are formula-neutralized. Calculation correctness passed, but XLSX operational memory reliability remains FA-008.

## 15. CMS / Media / Games Review

- Pages, resources, games, FAQs, policies, and translations support draft/preview/publish/discard/revision flows. Public routes do not leak drafts.
- Human translation workflow, source revisions, stale draft reconciliation, fallback banners, canonical URLs, and hreflang suppression are implemented.
- Resources preserve private files, safe replacement, cover images, gates, and analytics.
- Games support admin management, published/available states, public rendering, outbound play, and analytics.
- Articles support drafts, sanitization, SEO fields, optimistic locks, immutable revisions, and slug redirects.
- **Exception:** media deletion does not recognize article featured-image references (FA-006).

## 16. Frontend / Responsive / RTL Review

- Production assets compile and all local smoke pages returned HTTP 200 with the Vite manifest present.
- Public navigation, responsive layouts, mobile booking flow, localized shell, and Arabic `bdi`/direction isolation are implemented.
- Admin desktop layout reserves sidebar width; mobile navigation uses a drawer/backdrop, focus management, Escape handling, body scroll lock, and responsive tables/cards.
- The prior unstyled-asset and admin-topbar-overlap defects are fixed at the reviewed revision.
- No meaningful missing V1/V2/V3 layout section was found. Live Hostinger rendering was not independently rechecked because authenticated host access was unavailable.

## 17. Operations / Backup / Scheduler / Health Review

- `schedule:list` registers: scheduler heartbeat every minute, hold cleanup every five minutes, analytics aggregation/pruning daily, backup/retention daily, and session cleanup daily.
- Backup archives database plus private/public application storage, writes a checksum manifest, applies retention, supports an offsite Flysystem disk, and includes path/checksum preflight for restore.
- System health checks database, storage, cache, backup freshness, scheduler, queue heartbeat, mail configuration, and failed jobs.
- README documents deployment, cron/worker expectations, backup, and isolated restore steps.
- Production evidence remains insufficient under FA-005. Health's offsite status should also be tied to the latest archive (R-01).

## 18. Test Coverage Gaps

1. No test proves media deletion is blocked by an article `featured_image_path` reference.
2. Destructive migration tests are not isolated and the suite lacks a passing randomized-order gate.
3. XLSX tests do not prove a realistic export succeeds within the production 128 MB memory envelope or after repeated exports in one worker.
4. Repository tests cannot prove real production cron/worker recurrence, real remote-object persistence, alert delivery, or an operator restore drill; those require operational evidence.

Critical booking concurrency, idempotency, hold expiry, DST, contact race, resource gate, authorization, analytics validation, refunds, and student booking credit behavior do have meaningful tests, including separate-process MariaDB tests where concurrency matters.

## 19. Incorrect Claims in Existing Analysis Files

- `PROJECT_STATUS.md`, `Gemini.md`, and the prior audit state or imply that only FA-005 remains. That is materially stale: FA-006, FA-007, and FA-008 are verified by current code/runtime evidence.
- Existing status files cite **472 tests / 2,977 assertions**. The fresh normal run produced **472 tests / 2,979 assertions**.
- Existing status files cite deployed revision `7123f15`. The reviewed local and `origin/main` revision is `3c3d657`. Production could not be independently compared because SSH authentication failed.
- Claims that the full suite is unconditionally green are misleading: it is green in its default order but fails when randomized because migration DDL leaks across tests.
- Claims that V3 is fully complete are inaccurate until article media references are protected.

## 20. Required Adjustments

### 1. FA-006 — Complete article/media reference integrity

- **Severity:** HIGH
- **Affected files:** `Media.php`, `MediaController.php`, article/media tests
- **Required change:** add article featured-image reference checks for normalized paths and public URLs.
- **Tests:** published/draft/archived article reference blocking; reference removal; successful final deletion.
- **Acceptance:** no referenced article image record or physical file can be deleted.

### 2. FA-007 — Isolate all schema-mutating tests

- **Severity:** HIGH
- **Affected files:** migration test classes and test DB bootstrap/CI
- **Required change:** give destructive migration tests their own disposable database/process lifecycle; assert schema/ledger parity after them.
- **Tests:** full suite in default and randomized order, both clean; second consecutive run clean.
- **Acceptance:** 100% pass in arbitrary order and no missing/stale table after the run.

### 3. FA-008 — Make XLSX export memory-bounded

- **Severity:** HIGH
- **Affected files:** `ExportService.php`, report export tests
- **Required change:** bound/stream workbook generation, remove unnecessary auto-sizing at scale, dispose resources in `finally`, clean partial files.
- **Tests:** large and repeated exports under 128 MB; download headers/formula safety/temp cleanup.
- **Acceptance:** no memory fatal and no orphan temporary files.

### 4. R-01 — Bind offsite status to a specific backup

- **Severity:** MEDIUM
- **Affected files:** `BackupService.php`, `SystemHealthController.php`, backup tests
- **Required change:** persist offsite timestamp/archive/checksum per run and make missing/stale/mismatched status unhealthy.
- **Tests:** success, failure, null status, stale status, and different-archive cases.
- **Acceptance:** health cannot report offsite success for an older or unidentified archive.

### 5. FA-005 — Prove the production operational chain

- **Severity:** HIGH
- **Affected systems:** Hostinger cron, queue process, remote storage, alerts, restore target, operations record
- **Required change:** execute and document the production verification described in FA-005 after deploying items 1-4.
- **Tests:** timestamp progression, queue job completion, offsite checksum, delivered test alert, isolated restore and smoke/integrity checks.
- **Acceptance:** dated evidence identifies the deployed commit and proves recoverability end to end.

### 6. R-02/R-03 — Pin dependencies and reconcile deployment

- **Severity:** MEDIUM
- **Affected files/systems:** `composer.json`, `composer.lock`, Hostinger release
- **Required change:** constrain the two wildcard packages, rerun security/build/tests, and verify production commit/assets/caches/migrations.
- **Acceptance:** `composer validate` has no unbound-constraint warning and production matches the approved revision.

## 21. Final Verification Results

| Check | Result |
| --- | --- |
| Full PHPUnit/Laravel suite, default order | **PASS — 472 passed, 2,979 assertions, 0 failures, 0 errors, 0 skips reported** |
| Full suite, randomized order, normal 128 MB | **FAIL — fatal memory exhaustion in XLSX/ZipStream before completion** |
| Focused XLSX test at 128 MB | **PASS — 1 test, 3 assertions** |
| Full suite, randomized order, 512 MB | **FAIL — 456 passed, 7 failed, 9 errors; shared test schema corrupted by DDL tests** |
| Production frontend build | **PASS — Vite 8.3.0, 4.00 s** (optional Fontaine package warning only) |
| Application migration status | **PASS — 44 ran, 0 pending** |
| Database capability/FK gate | **PASS — MariaDB 10.11.18/InnoDB and booking→student FK verified** |
| Student backfill dry run | **PASS — 0 exceptions/orphans; no writes** |
| Scheduler listing | **PASS — 5 expected tasks registered** |
| Blade compilation | **PASS** |
| Local HTTP smoke | **PASS — `/`, `/booking`, `/admin/login`, `/student/login` returned 200** |
| Composer security audit | **PASS — no advisories** |
| npm production audit | **PASS — 0 vulnerabilities** |
| Composer validation | **VALID with warnings — two `*` runtime constraints** |
| Formatting | **Not run** — no PHP application code was changed, and running the configured Pint command would modify files contrary to this review's no-code-change rule |
| Recent application log check | **PASS — no recent ERROR/CRITICAL entry found** |
| Production host verification | **NOT VERIFIED — SSH authentication rejected** |

- Remaining CRITICAL count: **0**
- Remaining HIGH count: **4**
- Missing required module count: **0**
- Original specification: **PARTIAL** (operations/export reliability)
- V1: **VERIFIED COMPLETE in application behavior**
- V2: **VERIFIED COMPLETE in application behavior**
- V3: **PARTIAL** (article/media reference integrity)

## 22. Final Verdict

- **Is everything from the original specification implemented?** No. All major modules exist, but production recovery proof and XLSX reliability are incomplete.
- **Is everything from V1 implemented?** Yes in application behavior. Its migration tests must still be isolated so the verification is trustworthy in arbitrary order.
- **Is everything from V2 implemented?** Yes in application behavior.
- **Is everything from V3 implemented?** No. The article module is present, but featured-image media deletion protection is incomplete.
- **Are there any CRITICAL issues?** No verified CRITICAL issues.
- **Are there any HIGH issues?** Yes—four.
- **Are there missing required modules?** No.
- **Must anything be corrected before calling the project complete?** Yes. Fix FA-006, FA-007, and FA-008, then complete and record FA-005 on the production host.

**Final approval: NOT APPROVED AS COMPLETE.** The core application is strong and the highest-risk booking/security logic is verified, but the four HIGH items above must be closed before final production completion can be claimed.
