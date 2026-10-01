# Independent v9.4 requirement audit

Review date: 2026-10-01. Source: user attachment `49211e73-e0b9-4d18-ac0c-aa3f1cd4ad3c/Pasted text.txt` (all 205 lines read). Baseline HEAD: `e44493572f39f45e6e2ca7bcb20bff8229a73b1b`. The existing 52 dirty files were preserved as the implementation under review; no commit, reset, dependency change, or deployment was performed.

This report supersedes earlier completion claims for the v9.4 remediation. Historical V1–V3 audit documents retain their dated provenance. Passing tests alone were not accepted as evidence of complete implementation: controllers, routes, services, models, migrations, Blade templates, JavaScript, and test assertions were traced.

## Discovery and reconciled assumptions

- PHP: `C:/Users/e/.config/herd/bin/php84/php.exe`, 8.4.25. Node 24.19.0; npm 11.17.0. Laravel 13.32.0; Livewire 4.4.5; PHPUnit 12.5.35; Vite 8.3.0; Tailwind 4.3.3.
- MariaDB 10.11.18 was stopped at the beginning of this review. Its existing configuration at `C:/Users/e/AppData/Local/mariadb-data/bolt_landing/my.ini` was inspected and its existing server started, bound to 127.0.0.1:3307. Application database: `bolt_landing`; isolated test database: `bolt_landing_test`.
- All 55 application migrations were applied at discovery. Application queue/cache defaults and `.env.testing` are database. Standard PHPUnit deliberately uses array cache/session and synchronous mail/queue tests; the concurrency configuration uses database cache/session against `bolt_landing_test`.
- Separate-process shared-cache lock probe: `EXCLUSION_SUCCESS`. Runtime write PDO identity remained identical inside `DatabaseCapability::transaction`.
- The prompt's `app/Domains/Database/DatabaseCapability.php` is not the real path. The authoritative class is `App\Domains\Database\Services\DatabaseCapability`. No compatibility class was invented.
- The real `createPublicBooking` signature is `(array $data, array $intakeAnswers = [], ?int $formVersionId = null): Booking`. The original array argument was preserved. The illustrative positional prompt signature is not used.
- `acquireHold(string $visitorToken, ?string $sessionToken, SessionType $sessionType, CarbonInterface $startUtc, CarbonInterface $endUtc, ?int $durationMinutes = null, ?string $analyticsVisitorToken = null, ?string $analyticsSessionToken = null): BookingHold`.
- Identity APIs: `normalizeName(string): string`, `normalizeEmail(?string): ?string`, `normalizePhone(?string, ?string $countryCode = null): ?string`, `normalizedFullName(string, string): string`, `normalizeIdentity(?string, ?string, ?string, ?string, ?string $phoneCountry = null): string`.
- Grant API: public `ResourceController::issueDownloadGrant(Resource, Contact, string $visitorToken, string $sessionToken, ?int $requestId = null): string`. It stores a cache grant and a session copy. `LocalizedUrlService` has `getLocalizedUrl`, not the illustrative `route` method. Download URLs use the existing locale-specific named routes.
- Public identity mutex uses the default write connection (`useReadPdo: false`) before the domain transaction. Reconciled row sequence: buffer-expanded calendar date locks → contacts → students → holds/bookings and dependent writes. Named mutex release remains in `finally` after domain transaction completion. Existing merge writers use compatible calendar/contact/student/booking tiers.
- `forms.prompt_trigger` is already absent in the final schema. Migration A discovers legacy enum metadata including MariaDB's quoted default `'none'`; this differs from the prompt's unquoted illustrative comparison. `form_submissions.student_id` is unsigned bigint NOT NULL; contact/booking IDs are nullable unsigned bigint. Forms have separate active/published version pointers. Resource requests have a nullable varchar(64) replay hash and NOT NULL timestamp `created_at` without a default.
- Discovered foreign keys: booking contact → contacts RESTRICT; booking student → students SET NULL; submission student → students CASCADE; submission version → form_versions RESTRICT; submission contact/booking → their parents SET NULL; trigger form → forms CASCADE. Update rules are RESTRICT. Unique indexes include `contacts_email_unique`, `bookings_idempotency_key_unique`, `unique_form_trigger(form_id, trigger_name)`, and `resource_requests_consumed_challenge_hash_unique`; students' normalized identity indexes are nonunique.
- Application bookings/students/contacts were empty at discovery, so there were no stored booking statuses to enumerate. The varchar status column and actual writers/tests establish confirmed, cancelled, completed, no_show, pending, and held; the presenter also safely accepts the legacy no-show spelling and logs unknown statuses.
- No dead student-records/track-records navigation route anchor remains. Visitor middleware reads `_va_visitor`/`_va_session`, verifies session ownership, attaches server attributes, and issues cookies. Resource authorization reads cookies only, so request parameter spoofing has no authority.
- Resource request routes use `throttle:resource-request`. Static public directories are build, assets, images, storage; Vite builds into public/build. Maintenance bypass also retains vendor, favicon.ico, and robots.txt.
- Country rollup command: `analytics:aggregate-daily-country {--date=}`. It replaces the target day's aggregate transactionally rather than incrementing it; existing tests cover idempotency. Scheduled country rollup is 00:02 Cairo with explicit yesterday date; general aggregation/pruning is now 00:05 Cairo.
- Boost MCP tools are not exposed in this session. Direct source, installed package metadata, Artisan commands, and read-only MariaDB metadata were used. Official Laravel 13 and Livewire 4 validation documentation was checked for the validation changes.

## Review edit manifest

Only the following paths are authorized for additional changes in this review. Build output under public/build is generated. The two Migration B paths are included for the intermediate-schema verification lifecycle.

```text
app/Domains/Booking/Models/Booking.php
app/Domains/Booking/Services/BookingService.php
app/Domains/CMS/Models/Setting.php
app/Domains/Forms/Services/FormBuilderService.php
app/Domains/Forms/Services/FormValidationService.php
app/Domains/Students/Services/BillingReconciliationService.php
app/Domains/Students/Services/StudentIdentityService.php
app/Http/Controllers/ResourceController.php
app/Http/Controllers/Student/DashboardController.php
app/Http/Controllers/Student/FormController.php
app/Livewire/BookingWizard.php
resources/views/livewire/booking-wizard.blade.php
resources/views/public/resources/show.blade.php
resources/views/student/dashboard.blade.php
routes/console.php
tests/Feature/CashierBillingReconciliationTest.php
tests/Feature/AnalyticsFunnelAndAttributionTest.php
tests/Feature/PublicExperienceTest.php
tests/Feature/FormsEngineTest.php
tests/Feature/V5RemediationModuleTest.php
tests/Feature/BookingEngineTest.php
database/migrations/2026_10_01_000000_drop_prompt_trigger_from_forms_table.php
database/migrations_pending/2026_10_01_000000_drop_prompt_trigger_from_forms_table.php
CODEX_V94_SECOND_PASS_AUDIT.md
PROJECT_STATUS.md
walkthrough.md
public/build/**
```

## Requirement-by-requirement findings

| Requirement | Independent finding and final implementation |
| --- | --- |
| Preserve domain architecture, native services, locks, hold conversion, UTC snapshots and audit events | Existing domain services retained. No replacement layers, paid APIs or dependencies introduced. Booking hold/calendar and transaction behavior traced in BookingService, AvailabilityService, StudentBookingService, RescheduleService and the shared transaction helper. |
| Five required public identity fields; no heuristic name splitting | Split identity fields already existed. Backend now checks date/email shape and normalizes phone through StudentIdentityService before locks; the wizard applies the same phone/date requirements. |
| Validation before transactions/calendar locks | Existing service checked presence and question membership only. Repaired to call the existing complete form validator before the identity mutex, supporting types, choices, ranges, conditional questions, false/zero values and hidden-answer removal. |
| Identity provisioning/reuse/conflict/merged chains | Verified and legacy records are reused; guests become legacy_unverified; competing canonical candidates throw the domain conflict. Broken/cyclic merge chains previously returned a still-merged record; now they throw the same explicit conflict. |
| Named mutex bounds, write PDO continuity, release after transaction | Existing bounded identity lock and finally release retained. Same default connection/PDO verified in the domain transaction. Contact uniqueness is handled only for the verified exact index and SQLSTATE/vendor code. |
| Wizard 429/conflict handling and field errors | Existing domain catches retained. Previously swallowed ValidationException as a generic failure; now individual field/intake errors reach the error bag and return to the details step. |
| Accurate portal confirmation/passwordless access | Confirmation explains verification, DOB and any two identifiers. Login resolves normalized identity against verified students with DOB and two matches. No student password registration was added. |
| Cashier includes unverified/NULL and excludes merged; normalized search | Null-safe status predicate and normalized name/email/phone search already exist. Controllers do not introduce alternate identity algorithms for booking input. |
| Reconciliation empty dataset, grant mismatch and negative package-linked balance | Existing UNINITIALIZED_DATASET, grant totals and package-linked negative-credit checks traced. Missing debit detection previously trusted marketing `source`; now requires the student-created booking audit event emitted atomically by StudentBookingService. |
| SessionReschedule timestamps/write boundary | Existing model defaults created_at and uses no automatic timestamps. Actual reschedule writes use the model; remaining query-builder references read/reassign records. |
| Status presenter and N+1 protection | Presenter existed but no UI used it. Dashboard now uses studentStatusLabel and withExists('reschedules'); held has an explicit label. |
| Dynamic tutor presentation and customer isolation | Accessors and admin/Telegram rendering already existed. Central cache invalidation was missing in model setters and the old test masked it with Cache::forget. Saved/deleted timezone settings now invalidate; the regression tests the actual setter without manual invalidation. UTC and customer snapshots stay unchanged. |
| Dead navigation, assistant reschedule denial | Valid students/contacts/billing links retained; no dead anchor found. Route resolves EnsureAdminRole:super_admin,admin; authorization tests exercise assistant 403. |
| Arabic UI configuration purge with educational content retained | Dedicated setting migration, seeder English default, en/fr/de validation and dropdown already correct. Arabic vocabulary and bdi learning fragments retained. |
| Migration A read-only preflight before mutation | Parent key topology, trigger table/index/FK/allowlist/conflicts, submission student nullability and orphan references all checked before DDL/data writes. Legacy default uses actual MariaDB metadata. |
| Migration B staged cleanup and runtime purge | Final canonical B exists and is guarded against unmigrated triggers; runtime code uses form_triggers exclusively. Intermediate-schema tests use the real discovered service signatures. |
| Trigger model allowlist and form relation | Existing FormTrigger lifecycle rejects unapproved trigger names and Form::triggers is registered. |
| Immutable authors and stable question keys | Author preserved during edits/version creation; semantic keys preserved and structure validated. Published versions without responses were incorrectly modified in place; edits now derive a separate draft version even before the first response. |
| All publication writers serialize and use locked active version | Previously selected mutex path using an unlocked trigger snapshot; updates could add competing live triggers. Create/update/publication now share the bounded write-connection mutex; publication resolves trigger/version under transaction locks. Removal targets competing published forms and preserves pending draft trigger selections. |
| Wizard mount snapshot, refreshed live form and cross-version rejection | Locked IDs were already declared. Previously checked only the old form's version and missed replacement by another form, and render switched to new questions silently. Snapshot rendering stays on the initial version; final submission resolves the current triggered form, rejects changes and offers the new questions for review. Language restoration also discards stale-version answers. |
| All supported public form controls and conditional state | Old UI checked nonexistent textarea/select type names and wrong option fields. Long text, real choices, multi-choice arrays, boolean, rating, email, phone, number, date and info blocks now use installed form types; server visibility logic is shared. Optional questions are not made required merely because the form is mandatory. |
| Atomic public intake and rollback | Contact/student/booking/submission/answers/hold conversion remain within one outer domain transaction. Persistence errors rollback all records. Existing rollback test now attaches the form to pre_booking so it actually reaches the protected persistence path. |
| Autosave auth, server-side version, one draft, JSON values | Existing student lock/draft-only lookup serialized concurrent saves, but assignment and typed validation were missing. Autosave now locks/rechecks form and verified student, enforces assignment, rejects foreign questions, uses normal validation with requiredness disabled for drafts, purges hidden/deleted answers and cannot create a fresh draft to bypass a final submission. |
| Resource request/replay schema and model timestamps | Nullable unique replay hash, guarded migration, timestamps=false, created_at hook and attribution fillable fields already correct. |
| DNS valid-only cache/fail-open and shared-cache guard | Native MX then A checks cache only VALID; inconclusive results log and allow the request. Both PIN entrypoints reject nonshared stores. Testing intentionally bypasses external DNS according to the contract. |
| Cookie authority, Mode A/Mode B, queued PIN mail | Cookie-only issuance/verification and queued ShouldQueue mailable already present. Real issuance → captured queued PIN → verification → localized grant → download now exercised rather than only hand-constructing challenges. UI now correctly marks required name. |
| Replay/race and immediate fifth failure | Locked replay precheck and exact SQLSTATE 23000/vendor 1062/hash-index catch traced and tested separately. Fifth failure purges challenge and returns 429. Unrelated DB errors rethrow. |
| Resource state machine/security/localization | Existing AJAX states/status handling and retry controls retained. Download URLs now retain French/German routes; verification enforces the same publication time boundary as issuance/download. Old values use JavaScript-safe encoding in Alpine state. |
| Null-safe analytics durations and event parity | Null-safe session duration/human labels and event allowlist already correct. |
| Resource telemetry actual dispatch/ingestion/admin exclusion | Existing marker/admin-preview exclusions present. Previous test posted session_activity while authenticated as admin and asserted only 200. It now proves resource_gate_viewed persistence as a guest; an isolated JavaScript DOM test executes the real telemetry module and proves one mount dispatch. |
| Country schedule, historical/current public counters, date cache | Rollups before current Cairo date plus current-day UTC raw events and date-scoped 900-second cache already correct. Fixed general aggregation/pruning timezone to Cairo so 00:02/00:05 ordering is real rather than split across UTC and Cairo. |
| Maintenance priority, 503/bypasses/assets/fail-open | Visitor middleware and maintenance are explicitly prioritized before SubstituteBindings and after session startup. Guest/admin/login/health/assets behavior and fail-open catches traced. Existing maintenance setter invalidation retained. |
| Database queue, independent scheduler/worker health, test cache split/harness reuse | Configuration preserved; health evaluates scheduler heartbeat separately from worker heartbeat/jobs. Existing independent-process worker harness reused by identity/publication/autosave race tests. |
| Formatting/build/routes/schema/placeholder/scope checks | Results recorded below after final verification. No tests were removed or weakened to obtain green results. |

## Verification results

- Final standard suite: `artisan test --compact --exclude-group=intermediate-schema --env=testing` — **553 passed, 5,599 assertions**, exit 0.
- Final targeted forms/public/resource/cohort checks — **42 passed, 280 assertions**. Additional targeted billing, identity, telemetry and PIN checks passed during repair.
- Migration A intermediate graph: safe test-only `migrate:fresh`, then intermediate-schema suite — **3 passed, 15 assertions**. Migration B was restored to its canonical path in `finally`; final `migrate:fresh` applied all **55 migrations** and a live schema assertion confirmed `forms.prompt_trigger` absent. No application database tables were reset.
- Write-PDO continuity and independent-process shared-cache lock exclusion — passed.
- `npm run build` — passed (Vite 8.3.0); `vendor/bin/pint --dirty --format agent` — passed after formatting; Blade `view:cache` — passed, then compiled views cleared.
- Route registration — **221 nonvendor routes**; scheduler registration — seven tasks, country rollup at 00:02 Cairo followed by aggregation/pruning at 00:05 Cairo.
- Runtime `prompt_trigger` scan and discovery-placeholder scan — zero matches. Git scope check against the 52 pre-existing dirty paths plus this review manifest — passed. `git diff --check` — passed.
- The first full run exposed four failures. Two cohort fixtures used fixed September dates without fixing the clock and had crossed the documented 30-day maturity boundary; their clock fixtures now test the intended windows. The public booking fixture used an invalid phone number and now uses a valid reserved number. The new PIN end-to-end fixture omitted the session cookie required for the download grant and now carries it. Assertions and application security requirements were preserved.
- Shared-cache concurrency configuration: **553 passed, 5,599 assertions**. Artisan reported passed tests but exit 1; direct `vendor/bin/phpunit --configuration=phpunit.concurrency.xml --exclude-group=intermediate-schema --no-progress` independently passed with exit **0**. Inspection found Collision's Artisan command prepends `--configuration=phpunit.xml` while forwarding the requested configuration. A minimal unit-test reproduction with both configuration options passed its assertion but returned 1; the same test with a single option returned 0. This is a runner argument issue, not a failed application test. Vendor dependencies were not edited.

## Files changed by this review

The manifest above includes temporary migration staging and an allowed test path that needed no additional edit. Actual additional edits are:

- Booking/identity/billing: `app/Domains/Booking/Models/Booking.php`, `app/Domains/Booking/Services/BookingService.php`, `app/Domains/Students/Services/StudentIdentityService.php`, `app/Domains/Students/Services/BillingReconciliationService.php`.
- Forms: `app/Domains/Forms/Services/FormBuilderService.php`, `app/Domains/Forms/Services/FormValidationService.php`, `app/Http/Controllers/Student/FormController.php`, `app/Livewire/BookingWizard.php`, `resources/views/livewire/booking-wizard.blade.php`.
- Presentation/resources/scheduling: `app/Domains/CMS/Models/Setting.php`, `app/Http/Controllers/Student/DashboardController.php`, `resources/views/student/dashboard.blade.php`, `app/Http/Controllers/ResourceController.php`, `resources/views/public/resources/show.blade.php`, `routes/console.php`.
- Regression coverage: `tests/Feature/CashierBillingReconciliationTest.php`, `tests/Feature/FormsEngineTest.php`, `tests/Feature/V5RemediationModuleTest.php`, `tests/Feature/AnalyticsFunnelAndAttributionTest.php`, `tests/Feature/PublicExperienceTest.php`.
- Audit/status: `CODEX_V94_SECOND_PASS_AUDIT.md`, `PROJECT_STATUS.md`, `walkthrough.md`.

Generated assets were rebuilt under `public/build`. No migration content or dependency file was changed in this second pass.

## Limits of verification

- Live Herd rendering and visual/mobile browser inspection remain unverified. The earlier browser access attempt was explicitly rejected by a saved permission setting for this local site; no alternate browser or HTTP workaround was attempted.
- PHPStan was not installed during the original audit. The authorized follow-up below installs and runs it; its findings remain unresolved.
- External DNS/mail delivery and recurring production scheduler/worker execution are not proven by local feature tests. Mail is captured with Mail::fake and telemetry JavaScript uses an isolated DOM stub, not a real browser.
- This review cannot reconstruct whether the previous agent historically honored the original clean-tree/manifest gate or deployed Migration A and B in separate releases. Current migration preflights and both test schema phases are inspected/verified; deployment history is a separate evidence requirement.

Documentation consulted: [Laravel 13 validation](https://laravel.com/framework/docs/13.x/validation), [Livewire 4 validation](https://livewire.laravel.com/docs/4.x/validation).

## Authorized verification follow-up (2026-10-01)

Continued triage scope also includes `app/Domains/Students/Models/SessionLedgerEntry.php`, `app/Http/Controllers/Admin/StudentBillingController.php` and the existing cashier regression test. A missing student relation caused courtesy-credit CSV exports to identify real students as Unknown; a failing endpoint regression reproduced this before repair. Exports now use chunked lazy iteration so their existing eager loads actually run. Larastan's supported `parseModelCastsMethod` setting is enabled to read the project's explicit `casts(): array` methods without rewriting model behavior or suppressing diagnostics.

Continued results: corrected analyzer configuration and the ledger repair produce **378 level-5 findings** (down from the initial 502), exit 1. Static analysis is still unresolved. The cashier suite passes **15 tests, 98 assertions**, including the new real CSV-content regression and existing formula-injection-safe cell prefix. Pint and whitespace checks pass. The initial local test attempt failed because MariaDB had stopped; the existing local 127.0.0.1:3307 server was started hidden using its unchanged configuration, then the functional regression reproduced Unknown and was repaired.

Full standard suite after the export repair: **554 tests passed, 5,604 assertions**, exit 0. Additional targeted scope includes `tests/Feature/ProductionHealthAndSchedulerTest.php` to verify healthy scheduler/inactive worker, stale scheduler/healthy worker, and healthy scheduler/stale worker independently using the database queue configuration. This is local health behavior verification, not evidence that production cron or workers recur.

The final focused production-health and cashier suites, including the new independence regression, pass **25 tests, 183 assertions**, exit 0. Pint and `git diff --check` pass after these changes. Production access is now the next required external step; the user requested notification at that point.

The user authorized all remaining checks except actual email delivery. Additional file scope: `composer.json`, `composer.lock`, `phpstan.neon`, this report, and ignored generated PHPStan cache/results. Existing dependency versions were preserved. Development dependencies added: Larastan 3.12.2, PHPStan 2.2.16 and Larastan's SQL parser dependency 0.7.0. Installed package metadata explicitly supports Laravel 13.

- Static analysis now runs across `app`, `routes`, `database` and `config` at level 5 with Laravel support. **502 findings, exit 1**; no baseline, ignores, exclusions, or rule lowering was used to obtain a pass. Full machine-readable output is `storage/logs/phpstan-review.json`. Findings include Eloquent relation/result typing, casted dates/JSON inferred as database strings, redundant conditions, and argument mismatches. These are analyzer findings requiring individual triage, not 502 demonstrated runtime failures. Static analysis cannot be certified clean. This verification did not undertake an unrelated application-wide typing rewrite.
- Composer configuration validates; existing wildcard constraints generate warnings. The tooling installation made no updates/removals to existing locked packages.
- Post-installation focused forms/remediation suite: **31 tests passed, 211 assertions**, exit 0. `git diff --check` passed.
- Browser access was attempted again following user authorization. It was rejected by the saved preference blocking `http://arabicwabdallah.test/`; no alternative surface or HTTP bypass was attempted. The user was directed to Settings > Browser to remove the site block.
- No SSH configuration or keys were available in the standard local SSH location, and the browser inventory contained no signed-in hosting tabs. Production checks await access details from the user; no production deployment, queue execution or mail delivery was performed.
- Actual Git history shows Migration A and B were both introduced in commit `e444935` (2026-09-30). This proves source introduction together, not deployment together or apart. No deployment log demonstrating the required separate-release sequence was available. The existing historical status files do not provide such evidence.

Setup references: [PHPStan configuration](https://phpstan.org/config-reference), [Larastan](https://github.com/larastan/larastan). Browser controls: [official settings documentation](https://learn.chatgpt.com/docs/reference/settings).
