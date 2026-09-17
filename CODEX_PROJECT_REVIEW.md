# Codex Independent Project Review

Review date: 2026-09-17  
Specification reviewed: `ARABIC TUTORING WEBSITE FINAL 16 Sep.md`  
Previous audit reviewed: `PROJECT_STATUS.md`

## 1. Executive Summary

The project is a substantial Laravel implementation, but it is **not production-ready** and the previous audit materially overstates the safety and completeness of its most important subsystem: booking.

The application boots with Herd PHP 8.4, all 13 migrations are applied, the frontend builds, and the current automated suite passes (65 tests, 314 assertions). Those are useful health signals, but they do not prove the claims made in `PROJECT_STATUS.md`. In particular:

- Booking creation does not authoritatively revalidate the selected slot against recurring availability, exceptions, session duration, buffers, minimum notice, maximum horizon, or active session type. A tampered Livewire request or direct service call can create an off-hours, blocked-date, wrong-duration, or otherwise invalid booking.
- The collision code calls `lockForUpdate()` on range queries, but an empty slot has no booking or hold row to lock. The design has no canonical slot/calendar lock row and no database invariant preventing overlaps. Any protection for empty ranges depends on isolation-level and optimizer-specific gap-lock behavior. The tests called “concurrency” are sequential and do not verify this behavior.
- A final booking is not required to own a current hold. The service trusts a client-controlled visitor token to exclude holds and will also create a booking with no hold at all.
- Public resource downloads do not enforce the email gate or the generated token. A direct GET downloads the resource. Missing files are replaced with a synthetic PDF and are still recorded as successful downloads.
- All authenticated administrators have every admin capability. `super_admin` exists only as a model helper and is not enforced by routes, gates, policies, or controllers.
- Contact identity is not concurrency-safe because normalized email has a non-unique index and contact creation is a find-then-create race.
- Analytics does not receive the authoritative booking lifecycle events needed for its advertised booking funnel, attribution is not propagated correctly, and multiple report/dashboard definitions disagree.
- Backups, restore procedures, restore testing, operational health checks, hold cleanup, password reset, administrator management, page editing/revisions, and media management are absent.

The strongest parts are the UTC-oriented schema, immutable timezone snapshots, basic slot rendering, cancellation history, confirmation tokens, CSV/XLSX formula neutralization, and general Laravel structure. They provide a good base, but the critical booking path should be redesigned and tested against the actual production database before further feature work.

### Diagnostics performed

| Check | Result |
| --- | --- |
| Herd PHP | 8.4.25 |
| Laravel | 13.32.0 |
| Livewire | 4.4.5 |
| Database reached by the project | MariaDB 10.4.32, InnoDB, `REPEATABLE-READ` |
| Migrations | 13/13 applied |
| Test suite | PASS — 65 tests, 314 assertions |
| Frontend build | PASS — Vite 8.3.0; optional `fontaine` warning only |
| Composer advisory audit | No advisories reported |
| npm advisory audit | 0 vulnerabilities reported |
| Scheduled tasks | One task: `analytics:aggregate-daily --prune` at 00:05 |
| Public storage link | Not linked |
| Git history | No commits; all files are currently untracked |

The default `php` on PATH is XAMPP PHP 8.2.12 and cannot run this project; the Herd PHP 8.4 binary is required. Local `APP_DEBUG` is enabled. These are deployment/documentation concerns, not test failures.

## 2. Critical Issues

### Finding C-1 — Empty-slot locking is not a reliable concurrency invariant

**Specification requires:**  
Two simultaneous requests must never successfully reserve the same or overlapping slot. The final write must be serialized by a real database invariant or a lock that always exists.

**PROJECT_STATUS.md claims:**  
MySQL InnoDB pessimistic row locking handles races and automated tests simulate concurrent visitor submissions.

**Actual implementation:**  
`BookingService::createBooking()`, `BookingHoldService::acquireHold()`, and `RescheduleService::reschedule()` run collision queries with `lockForUpdate()`. When the interval is empty, there is no booking/hold row representing that interval to lock. There is no slot inventory/calendar mutex row, advisory lock, exclusion constraint, or unique key that prevents overlapping intervals.

In the currently observed MariaDB `REPEATABLE-READ` environment, next-key/gap locks may cause some competing inserts to block or deadlock depending on the selected index and query plan. That is not a durable application invariant: it changes under `READ COMMITTED`, can change with indexes/plans/database versions, and is not established by the tests. The application also claims MySQL while the inspected database is MariaDB 10.4.32.

The named concurrency tests are sequential. One request creates a booking or hold completely, then the second request runs and sees the committed row. They do not overlap transactions or processes.

**Evidence:**

- `app/Domains/Booking/Services/BookingService.php`, `createBooking()` (collision query around lines 90–112)
- `app/Domains/Booking/Services/BookingHoldService.php`, `acquireHold()` (around lines 43–88)
- `app/Domains/Booking/Services/RescheduleService.php`, `reschedule()` (around lines 43–65)
- `database/migrations/2026_09_16_204921_create_booking_tables.php` (only indexes on ranges; no overlap/slot invariant)
- `tests/Feature/CriticalBookingQAMatrixTest.php`, `test_scenario_e_two_users_select_same_slot_concurrency_race()` (first hold finishes before second starts)
- `tests/Feature/BookingEngineTest.php`, `test_concurrent_collision_throws_slot_unavailable_exception()` (first booking finishes before second starts)

**Severity:** Critical

**Required correction:**  
Introduce a canonical row that always exists and can be locked before collision validation—for example, a business-calendar lock row per tutor/date—or materialize bookable slot rows with a unique reservation constraint. Lock it inside the same transaction, then recheck bookings and holds before inserting. If arbitrary overlapping durations remain supported, serialize all mutations for the affected tutor/business date(s). Define and verify the required MySQL version and isolation level. Handle deadlock/duplicate-key retries by re-reading authoritative state. Add true parallel integration tests using separate database connections/processes against the same production-version MySQL server.

### Finding C-2 — The final booking write bypasses the availability rules

**Specification requires:**  
The server must authoritatively revalidate the slot at final submission, including recurring hours, exceptions, blocks, special hours, duration, buffers, minimum notice, horizon, holds, existing bookings, and session availability.

**PROJECT_STATUS.md claims:**  
The booking service provides authoritative booking creation; all availability rules, buffers, notice, horizon, and critical scenario I are operational and tested.

**Actual implementation:**  
`BookingService::createBooking()` validates only that the session type exists, the start is in the future, the timezone is valid, and no current booking/other hold overlaps. It does **not** call `AvailabilityService`, require an active session type, verify `end > start`, require the configured duration, verify the recurring rule/special hours, apply minimum notice/horizon, enforce buffer around existing reservations, or recheck blocked dates.

The Livewire component accepts `startUtc`, `endUtc`, and `slotData` as action parameters and stores them in public properties. `BookingHoldService` likewise accepts those values without schedule validation. A client can submit an arbitrary future interval, including an off-hours interval, an invalid/negative interval, an inactive session, a date just blocked by an administrator, or a time outside the booking horizon.

The scenario-I test is a false positive. It creates a hold with visitor token `vis-blocked-date`, then omits that visitor token from `createBooking()`. The service rejects the request because it sees the caller's own hold as another visitor's hold—not because it detects the new blocked date. The real wizard passes the visitor token, so the same blocked date can proceed.

**Evidence:**

- `app/Domains/Booking/Services/BookingService.php`, `createBooking()` (lines 52–178)
- `app/Domains/Booking/Services/BookingHoldService.php`, `acquireHold()`
- `app/Domains/Availability/Services/AvailabilityService.php`, `isSlotAvailable()` (not called and itself incomplete)
- `app/Livewire/BookingWizard.php`, public slot properties and `selectSlot()`/`confirmBooking()` (lines 21–35 and 160–228)
- `tests/Feature/CriticalBookingQAMatrixTest.php`, scenario I (around lines 333–366)

**Severity:** Critical

**Required correction:**  
Create one authoritative reservation validator used by hold, final booking, and rescheduling. It must derive the end time from the active session type, not trust it from the browser; reject non-positive/mismatched intervals; enforce recurring/special/blocked availability, notice, horizon, buffers, and session status; and execute the final validation while holding the canonical calendar lock described in C-1. Replace scenario I with a test that passes the real hold identity and proves rejection specifically because the exception changed.

### Finding C-3 — Hold ownership is not required and can be spoofed

**Specification requires:**  
Final booking should confirm that the same visitor/session owns an active, unexpired hold for the exact slot, then atomically convert that hold while creating the booking.

**PROJECT_STATUS.md claims:**  
Competing visitors cannot complete a booking during a hold and holds are converted during finalization.

**Actual implementation:**  
Booking creation accepts an optional `visitor_token`; no hold ID/secret is required. If no token is supplied, a booking can be created without a hold. If a token is supplied, the code merely excludes all holds bearing that token from the conflict query and later updates matching holds. It does not lock and verify a specific active, unexpired hold owned by the current Laravel session. `visitorToken` is a public Livewire property and is not marked locked; `session_token` is stored on holds but never checked during booking.

This lets a manipulated request bypass the intended hold protocol and makes a stolen/spoofed visitor token sufficient to ignore someone else's reservation.

**Evidence:**

- `app/Domains/Booking/Services/BookingService.php`, hold query and conversion (around lines 101–112 and 155–162)
- `app/Livewire/BookingWizard.php`, public `$visitorToken`, `$holdId`, and `confirmBooking()`
- `app/Domains/Booking/Services/BookingHoldService.php`, `hasValidHold()` exists but is not used by booking creation

**Severity:** Critical

**Required correction:**  
Require a server-bound hold identifier plus an unguessable hold secret or authenticated session binding. Lock that exact hold, confirm active status, expiry, visitor/session ownership, session type, start, and end, then create the booking and convert the hold in one transaction. Do not accept visitor identity from a mutable public component property.

## 3. High-Priority Issues

### Finding H-1 — Rescheduling repeats the booking safety defects

**Specification requires:**  
Rescheduling must be as concurrency-safe and availability-aware as initial booking, while preserving history and using the current business timezone for the new appointment.

**PROJECT_STATUS.md claims:**  
Rescheduling is strictly non-destructive and concurrency-safe.

**Actual implementation:**  
History preservation is present, but rescheduling only checks past time, booking overlaps, and holds. It does not enforce availability rules, exceptions, notice, horizon, configured duration, buffers, or active session type, and it relies on the same empty-range locking pattern. The controller parses the entered wall time using the current business timezone, while the service creates new snapshots using the booking's old `business_timezone`. If the business timezone setting changed, the selected wall time and stored business snapshot can disagree.

**Evidence:**

- `app/Domains/Booking/Services/RescheduleService.php`, `reschedule()`
- `app/Http/Controllers/Admin/BookingController.php`, `reschedule()`

**Severity:** High

**Required correction:**  
Route rescheduling through the same authoritative, locked reservation operation as new booking; use the current business timezone consistently for parsing and the new immutable snapshot; lock the booking row before transition; test simultaneous reschedules/bookings.

### Finding H-2 — The resource email gate is bypassable by a direct URL

**Specification requires:**  
Gated/private resources must be downloadable only after the request flow using a secure, expiring token, and request/download events must represent real successful actions.

**PROJECT_STATUS.md claims:**  
The public resource library has gated email capture, secure download token flow, and request/download analytics. It separately calls the synthetic PDF only technical debt.

**Actual implementation:**  
`requestAccess()` stores token-associated data in the session and flashes a random token, but `/resources/{slug}/download` never accepts or validates that token. It reads optional session data and serves the file to any direct GET. It records a download and analytics event before confirming that the file exists. If no file exists, it returns a generated “sample” PDF as a successful production download.

The admin UI exposes `is_gated`, but there is no such database column or model field and the controller ignores it. Thus the UI choice is fictitious.

**Evidence:**

- `routes/web.php`, public resource routes (around lines 35–40)
- `app/Http/Controllers/ResourceController.php`, `requestAccess()` and `download()` (around lines 58–180)
- `resources/views/admin/resources/create.blade.php` and `edit.blade.php`, `is_gated` inputs
- `database/migrations/2026_09_16_204922_create_resource_tables.php` (no `is_gated`)

**Severity:** High

**Required correction:**  
Make the gate explicit in schema. For gated resources, require a single-use or appropriately reusable expiring HMAC/signed token bound to resource/request/contact, validate it before recording a download, and return 404/410/503 when the backing file is absent. Remove the synthetic PDF outside explicit test fixtures.

### Finding H-3 — Role-based authorization is absent

**Specification requires:**  
`super_admin` and `admin` must have enforced capability boundaries; sensitive settings, administrators, backups, audit/security, and destructive operations must be restricted appropriately.

**PROJECT_STATUS.md claims:**  
It identifies a role gap, but otherwise presents the admin platform as operational.

**Actual implementation:**  
Every admin route is under only `auth:web`. There are no policies, gates, role middleware, or controller authorization checks. `Administrator::isSuperAdmin()` and `isAdmin()` are unused helpers. Any authenticated ordinary admin can change settings, availability, resources/content, contacts, reports, health, and audit logs.

**Evidence:**

- `routes/web.php`, admin group beginning around line 72
- `app/Domains/Administration/Models/Administrator.php`
- No policy/gate files or calls found under `app/`

**Severity:** High

**Required correction:**  
Define a capability matrix, enforce it with policies/gates or explicit middleware at routes and actions, and add ordinary-admin denial tests for every privileged function. Do not rely on hiding navigation links.

### Finding H-4 — Canonical contact identity is race-prone

**Specification requires:**  
Normalized email should provide a durable canonical identity under concurrent bookings and resource requests; merges must safely preserve related data.

**PROJECT_STATUS.md claims:**  
Email normalization, deduplication, contact resolution, attribution preservation, and non-destructive merge are implemented and tested.

**Actual implementation:**  
Email is normalized on model creation, but the database column has only a normal index. `resolveOrCreate()` does a select followed by create without a transaction or duplicate-key recovery. Concurrent requests for the same email can create two canonical contacts. Normalization does not run on update. A soft-deleted, non-merged contact is returned and updated without restoration, so new business data can be attached to a contact hidden from normal admin queries.

Merge uses a transaction but does not lock either contact. It reassigns bookings, requests, and downloads, but discards many potentially useful conflicting fields (name, notes, display email, attribution, timestamps), normalizes neither phone nor email groups, and can race with new related records.

**Evidence:**

- `database/migrations/2026_09_16_204918_create_contacts_table.php`, email index at line 17
- `app/Domains/Contacts/Models/Contact.php`, creating hook
- `app/Domains/Contacts/Services/ContactService.php`, `resolveOrCreate()`, `merge()`, `findSuspectedDuplicates()`

**Severity:** High

**Required correction:**  
Make normalized email unique at the database level after cleaning duplicates. Use transactional `firstOrCreate`/insert-and-recover behavior around the unique key. Normalize on every assignment. Lock contacts during merge, define deterministic field-conflict rules, and protect concurrent relationship creation or reparent it after locking.

### Finding H-5 — Resource uploads do not validate type or lifecycle

**Specification requires:**  
Uploads must enforce allowed MIME/signature/extension/size, use private storage, avoid path ambiguity, and delete or retain replaced/deleted files by a documented policy.

**PROJECT_STATUS.md claims:**  
Private file storage and resource administration are implemented; upload rejection tests are merely pending.

**Actual implementation:**  
The controller validates only `file|max:51200`. It trusts the client extension for storage and independently trusts an admin-selected `file_type`. It does not verify MIME, signature, or extension agreement. Replacing a file leaves the old file orphaned; soft-deleting a resource leaves its file. Publishing is allowed without a file and the synthetic fallback masks the missing asset. The create/edit form names the description field `description`, while the controller expects `short_description`, so submitted descriptions are silently dropped.

**Evidence:**

- `app/Http/Controllers/Admin/ResourceController.php`, validation/storage around lines 44–76 and 105–143
- `resources/views/admin/resources/create.blade.php`, lines 69–72
- `resources/views/admin/resources/edit.blade.php`, lines 66–70

**Severity:** High

**Required correction:**  
Use explicit MIME and extension allow-lists plus server-side content inspection appropriate to PDF/audio/ZIP/document files. Generate server filenames, store disk/path metadata, require a valid file before publish, delete/retain superseded files explicitly, and align form/controller field names.

### Finding H-6 — No working backup or recovery system exists

**Specification requires:**  
Automated MySQL/database and uploaded-file backups, retention, monitoring, off-host copy, restore documentation, and periodic restore verification.

**PROJECT_STATUS.md claims:**  
It acknowledges the backup runner is missing but still rates “Backups & Cron” 40% complete.

**Actual implementation:**  
There is no backup command, schedule, file/database snapshot code, off-host destination, status record, alert, restore documentation, or restore test. A seeded `backup_retention_days` setting is unused. The health screen does not inspect backups. Therefore production data and uploaded resources have no application-provided recovery path.

**Evidence:**

- `routes/console.php` (only analytics aggregation)
- `app/Http/Controllers/Admin/SystemHealthController.php`
- `database/seeders/DatabaseSeeder.php` (unused backup setting)
- Default Laravel `README.md`

**Severity:** High

**Required correction:**  
Implement and operationally configure encrypted database and storage backups to an independent destination, retention, observable failures, restore runbook, and routine restore tests before production data is accepted.

### Finding H-7 — Analytics cannot support the advertised booking funnel or reliable attribution

**Specification requires:**  
Authoritative server-side conversion events, coherent visitor/session identity, UTM attribution, bot handling, validated events, rollups, retention, and consistent reporting.

**PROJECT_STATUS.md claims:**  
The five-stage booking funnel, resource/game funnels, UTM acquisition, active visitors, rollups, and server-only conversions are operational.

**Actual implementation:**  
No booking/hold/reschedule/cancellation service calls `AnalyticsService`; the server-only booking events are present only as constants and test fixtures. Consequently the advertised production booking funnel cannot receive its authoritative conversion stages. Middleware stores UTM values on `visitor_sessions`, but `AnalyticsService` reads UTM only from the current request query. Booking never passes request attribution to `BookingService`. Resource and game controllers read Laravel session keys that the analytics middleware never writes, so those events commonly have null visitor/session identity.

The browser endpoint blocks server-only names and has an allow-list, which is good, but accepts arbitrary nested metadata without limits and lets a browser fabricate all allowed engagement/game events. Only that endpoint has a 60/minute throttle. Resource requests/downloads, games, and the Livewire booking flow have no dedicated abuse limits.

**Evidence:**

- `app/Domains/Analytics/Services/AnalyticsService.php`
- `app/Http/Middleware/TrackVisitorSession.php`
- `app/Http/Controllers/AnalyticsController.php`
- `app/Http/Controllers/ResourceController.php`
- `app/Http/Controllers/GameController.php`
- `routes/web.php`
- Search results show no lifecycle analytics calls from booking services

**Severity:** High

**Required correction:**  
Emit server-only lifecycle events after successful transactional state changes (or through an outbox/after-commit mechanism). Establish one visitor/session identity source, propagate stored attribution, validate metadata schemas and size, bind sessions, and rate-limit all public mutation/telemetry endpoints independently.

### Finding H-8 — Seeded administrator credentials are unsafe

**Specification requires:**  
Initial administrator creation must be safe and must not introduce a known reusable production credential.

**PROJECT_STATUS.md claims:**  
It lists social placeholders but does not identify the administrator password risk.

**Actual implementation:**  
`DatabaseSeeder` creates/updates `admin@boltlanding.test` with the fixed password `Password123!`. If the seeder is run in a shared or production environment, the account is immediately predictable and reseeding resets the password.

**Evidence:**

- `database/seeders/DatabaseSeeder.php`, lines 22–31
- `resources/views/admin/auth/login.blade.php` displays the same admin address as a placeholder

**Severity:** High

**Required correction:**  
Remove fixed credentials. Require an environment-provided one-time credential, an interactive provisioning command, or a random secret emitted exactly once, and force password rotation. Prevent production seeding of demo content.

## 4. Missing Requirements

The following meaningful specification requirements are absent rather than merely defective:

- Administrator CRUD, activation/deactivation, role assignment, and enforced permission matrix.
- Password reset/recovery for administrators.
- Admin creation of bookings. Existing admin booking actions operate only on existing records.
- Customer self-service rescheduling. The confirmation page text offers cancellation/rescheduling, but only cancellation is implemented publicly.
- A real page CMS: create/edit, draft, preview, publish/unpublish, revision creation, revision comparison/restoration, and permission enforcement.
- Media library UI and lifecycle management for CMS/resources/game thumbnails.
- Configurable per-session/tutor video meeting URL and delivery workflow.
- Internal notification center for booking, cancellation, resource request, and operational failures.
- Real email notification/calendar invitation delivery. Mail is configured to `log` in `.env.example` and no jobs/mailables were found.
- Automated database and uploaded-file backups, off-host copies, retention, monitoring, restore documentation, and restore tests.
- Hold cleanup schedule. `cleanupExpiredHolds()` exists but is never scheduled.
- Dedicated session cleanup, failed-job monitoring, scheduler heartbeat, queue health, disk-capacity checks, and backup health.
- Maintenance-mode enforcement. The setting and UI exist, but no public middleware uses it.
- Dedicated rate limits for booking/holds, resource requests/downloads, game telemetry, and public cancellation.
- Full calendar views (day/week/month) and admin booking creation. The current booking ledger is a filtered list/month-oriented view, not the full operations calendar described by the specification.
- Resource category management, complete game CRUD, thumbnail upload, and target URL management.
- Sitemap/canonical/complete social metadata management and structured SEO editing.
- Project-specific setup/deployment/operations/restore documentation. `README.md` remains Laravel's default.
- A committed Git baseline/history. The repository has no commits and every file is untracked, preventing meaningful history, rollback, or review.

## 5. Incorrect or Partial Implementations

### Availability generation

- Recurring rules and one exception per queried date are used, but `max_horizon_days` on rules is never used; 60 days is hard-coded in both `AvailabilityService` and `BookingWizard`.
- `min_notice_hours` is applied while generating displayed slots, but not at hold, booking, or reschedule time.
- `buffer_minutes` is used only as the step between generated candidates. Existing bookings and holds are compared to the raw session interval; their surrounding buffers are not reserved. The schema has one buffer, not distinct before/after values.
- `session_duration_minutes` on a rule may override the selected session type's configured duration, which can make a session type display one duration but book another.
- No independent slot granularity exists despite the prior audit's “slot interval configuration” wording.
- Multiple recurring rows can overlap and emit duplicate slots. There is no database constraint or deduplication.
- Exceptions are loaded with `keyBy(date)`, silently choosing one when duplicate dates exist; the database does not make exception date unique.
- Special hours support one interval and inherit the first recurring rule's duration/buffer/notice, or fall back inconsistently.
- `isSlotAvailable()` checks only past time, raw booking/hold overlap, and a blocked exception. It does not establish that the slot came from an enabled rule or special interval and does not enforce duration, notice, horizon, or buffer.

Evidence: `app/Domains/Availability/Services/AvailabilityService.php`; `database/migrations/2026_09_16_204920_create_availability_tables.php`; `app/Livewire/BookingWizard.php`.

### Holds and idempotency

- Expired holds are excluded from availability/collision queries, so they stop blocking logically. Their rows accumulate indefinitely because cleanup is not scheduled, causing unbounded table/index growth.
- A visitor's earlier holds are marked released when a new hold is acquired, but navigation/session abandonment depends entirely on expiry.
- The idempotency key has a unique database index and sequential retries return the prior row. Under simultaneous first submissions, both can pass the pre-check; the losing insert may surface a database exception unless the collision/gap-lock path happens to serialize it. There is no catch-and-reload for the unique key.
- The same idempotency key is not bound to a payload fingerprint, so reusing it with different customer/slot data returns the first booking silently.

Evidence: `BookingService`, `BookingHoldService`, booking migration, and sequential idempotency tests.

### Timezone and calendar handling

- UTC storage and IANA validation are present, and booking snapshots preserve historical local projections.
- Local wall times are parsed with `CarbonImmutable::parse()` without explicitly rejecting nonexistent spring-forward times or disambiguating repeated fall-back times. The tests compare offsets/UTC instants but do not exercise a nonexistent or duplicated wall-clock input through the booking path.
- Changing timezone in the wizard does not clear/rebuild an already selected slot or hold. The stored `selectedSlot` display payload can remain in the old timezone while final booking snapshots use the new timezone.
- Manual timezone choice is component state only; it is not persisted across a reload/new flow.
- `.ics` uses UTC `Z` timestamps correctly, but description newline escapes are constructed as `\n` and then all backslashes are escaped again, likely producing literal doubled backslashes. Long and Unicode content is not RFC 5545 line-folded. Tests check marker strings, not parsing with an independent calendar parser.

Evidence: `TimezoneService`, `BookingWizard`, `AvailabilityService`, `IcsGenerator`, and timezone/critical tests.

### Resource publishing and administration

- Resource index uses `published_at <= now()`, but show/request/download check only `status = published`. Future-dated resources are directly accessible by slug.
- Description form/controller names do not match.
- `is_gated` is a non-functional UI-only field.
- Cover images are claimed but not implemented.
- Public storage is currently not linked.
- File replacement/deletion leaves orphaned files.

### CMS and public content

- Page, content revision, and media tables/models exist, but revisions/media have no working service or UI.
- Admin content handles FAQs and social links; it only lists pages and has no page actions.
- About, terms, and privacy are hard-coded Blade templates, not CMS-managed pages.
- `/p/{slug}` filters status but not `published_at <= now()`, so a future-scheduled published page leaks early.
- Dynamic page HTML is rendered with `{!! $page->content !!}` and no sanitization contract. A future editor/import path could create stored XSS.
- Social URLs/phones are updated without validation; dangerous URL schemes can be stored and rendered.

### Games

- Public index/show do not filter status. A direct slug opens disabled/archived games.
- Migration/model status vocabulary says `archived`, while admin validation/UI writes `disabled`.
- Admin can edit title/description/status/order but cannot create/delete games or manage thumbnail/target URL as required.
- Game event metadata merges arbitrary client input without schema/size validation.

### Reports and exports

- CSV and XLSX outputs neutralize leading formula characters (including after whitespace). XLSX files are stored privately and marked for deletion after send. These portions are substantially implemented.
- Traffic report groups by both date and source but labels each row's source as “Top Source”; it can emit several rows for a date and does not compute the top source.
- Previous-period calculation uses `diffInDays()` against inclusive boundaries and can compare unequal-length ranges.
- Booking reports filter by appointment start time, while dashboard booking metrics filter by record creation time. “Confirmed” excludes records later completed/cancelled; “completed” filters `created_at`, not `completed_at`.
- “Yesterday” dashboard queries use a lower bound without an end-of-yesterday bound.
- Report dates are interpreted in app UTC, not the business timezone.
- The booking export labels columns “Cairo” while using the current configurable business timezone, and it does not use each booking's immutable business snapshot. Historical report display can shift after a setting change.
- Reports load the full result set into memory before CSV streaming/XLSX generation; large exports can exhaust memory.
- Custom date input and `format` are not properly constrained. Unknown formats receive CSV content with an arbitrary extension.

Evidence: `app/Domains/Reporting/Services/ReportService.php`, `ExportService.php`, `Admin/ReportController.php`, and `Admin/DashboardController.php`.

### Analytics rollups and identity

- Tracking cookies are explicitly created with `secure=false` and `httpOnly=false`, including on HTTPS.
- A supplied `_va_session` token is accepted without verifying that it belongs to the current visitor token.
- Bot classification is a small user-agent check, not the multi-signal handling described by the specification.
- Session timeout and active visitor settings are seeded but ignored; code hard-codes 30 and 5 minutes.
- The dedup operation is `Cache::has()` followed by `Cache::put()`, not atomic.
- The event controller accepts `page` up to 500 characters while the database `page` column is the default 255; long input can be dropped via the service's catch path under strict SQL modes.
- Daily dashboards/reports query raw events rather than primarily using rollups.
- Aggregation does not remove stale metrics when a dimension/event disappears on rerun. The unique key includes nullable dimensions; MySQL/MariaDB unique indexes permit multiple rows containing NULL, so concurrent/racing aggregation can duplicate overall metrics.
- Raw events are pruned after 180 days, but there is no separate shorter handling for pseudonymous IP hashes and no documented privacy/consent implementation.

## 6. Verification of PROJECT_STATUS.md

| Claim | Verdict | Evidence | Notes |
| ----- | ------- | -------- | ----- |
| PHP 8.4 / Laravel 13 / Livewire 4 stack | VERIFIED | Herd diagnostics; `composer.lock` | Herd PHP is 8.4.25, Laravel 13.32.0, Livewire 4.4.5. The stated Livewire 4.1.3 later in the file is stale. Default PATH PHP 8.2 cannot run the project. |
| MySQL InnoDB environment | MISLEADING | Database diagnostics; table engine query | Laravel uses the MySQL driver and tables are InnoDB, but the inspected server is MariaDB 10.4.32, not MySQL. Production MySQL behavior was not tested. |
| Frontend build passes | VERIFIED | `npm.cmd run build` | Passes; optional `fontaine` warning only. |
| All 13 migrations are applied | VERIFIED | `artisan migrate:status` | Verified in the currently configured database. This does not prove fresh migration on a production MySQL version. |
| 65 tests / 314 assertions all pass | VERIFIED | Full `artisan test --compact` | Count and pass status verified. The suite does not prove several safety claims. |
| Canonical UTC booking storage | VERIFIED | Booking migration, `TimezoneService`, `BookingService` | UTC columns and immutable projections are present. Arbitrary invalid intervals can still be stored. |
| Africa/Cairo business timezone | PARTIAL | `TimezoneService::getBusinessTimezone()`, seeder | Correct default and configurable IANA zone. Some reports hard-label Cairo; reschedule snapshot can use the old zone. |
| Recurring availability | PARTIAL | `AvailabilityService` | Slot display uses recurring rules, but final booking/reschedule does not enforce them. |
| Availability exceptions | PARTIAL | `AvailabilityService`; scenario-I test | Display generation honors blocked/special days. Final booking does not; scenario I fails for the wrong reason. |
| Buffers are enforced | INCORRECT | `AvailabilityService`, booking collision queries | Buffer is candidate spacing only; it is not enforced around existing bookings/holds or at final write. |
| Minimum notice is enforced | PARTIAL | `AvailabilityService` | Enforced while listing slots only, not at hold/final booking/reschedule. |
| Maximum booking horizon is enforced | PARTIAL | `AvailabilityService`, `BookingWizard` | Hard-coded 60-day UI/list window; rule-level horizon is unused and final writes bypass it. |
| MySQL pessimistic locking prevents races | INCORRECT | Booking/hold/reschedule services; migration | No always-existing lock target or overlap constraint. Safety depends on gap-lock side effects; tests are not concurrent. |
| Booking holds prevent competing completion | PARTIAL | `BookingHoldService`, `BookingService` | Basic sequential conflict filtering works. Ownership is optional/spoofable; concurrency is not proven; cleanup is unscheduled. |
| Holds auto-expire cleanly without cleanup | MISLEADING | Hold queries and `cleanupExpiredHolds()` | Expired rows stop blocking queries, but remain `active` and accumulate until an unscheduled method is called. |
| Idempotency returns the existing booking without errors | PARTIAL | Unique index; `BookingService`; tests | Works sequentially. Simultaneous first requests and payload mismatch are not handled as a robust idempotency contract. |
| All critical booking QA cases are verified | MISLEADING | `CriticalBookingQAMatrixTest` | E/G are sequential; I rejects due to omitted visitor token; DST and manual-timezone tests do not exercise the real edge behavior. |
| Livewire booking wizard is operational | PARTIAL | `BookingWizard` and Blade view | Four steps render and happy path is tested, but public slot inputs/identity can be tampered with and final authoritative validation is missing. |
| `.ics` support is complete | PARTIAL | `IcsGenerator` | UTC instants are correct; escaping/folding and independent calendar interoperability are untested and likely defective. |
| Contacts/leads provide canonical identity | PARTIAL | Contact migration/service/controller | Sequential normalization works; no unique email invariant, merge locking, normalized phone, or consistent lead definition. |
| Resource gating/private downloads work | INCORRECT | `ResourceController::download()` | Direct download bypasses the token/gate; `is_gated` UI is not backed by schema. |
| Cover images and resource file management work | INCORRECT | Resource schema/controller/views | No cover upload implementation; weak file validation and orphan lifecycle. |
| First-party analytics is operational | PARTIAL | Analytics service/middleware/controllers | Basic ingestion exists, but authoritative booking events/identity/attribution/abuse controls and rollup use are incomplete. |
| Five-stage booking funnel is live | INCORRECT | Event constants vs booking services | Booking services do not emit the server-only stages; tests insert events directly. |
| Reports are complete and consistent | PARTIAL | Report/dashboard services | Reports render/export, but calculations, time boundaries, labels, and metric definitions are inconsistent. |
| CSV/XLSX formula injection protection | VERIFIED | `ExportService::sanitizeCell()` and tests | Leading `= + - @`, including after whitespace, are prefixed with an apostrophe. Scalability and format validation remain issues. |
| CMS pages/revisions/media are partly implemented | VERIFIED | CMS migrations/models/admin routes | Schema/models exist; only FAQ/social editing is functional. No page/revision/media workflow. |
| System health verifies MySQL and operations | PARTIAL | `SystemHealthController` | DB/storage/cache probes exist, but it hard-codes a MySQL success message and omits backups, jobs, scheduler, disk capacity, queue, and failed tasks. |
| Scheduled analytics aggregation/retention | VERIFIED | `routes/console.php`, command, `schedule:list` | The single task is registered. Production scheduler execution itself cannot be verified from code. |
| Backups are partially complete | INCORRECT | Search of commands/schedule/docs | A setting alone is not backup capability; no backup or restore implementation exists. |
| Application is advanced and V1 core is completely operational | INCORRECT | Critical findings C-1 through C-3 | The happy path is advanced, but the core booking correctness/security contract is incomplete. |

## 7. Booking / MySQL Review

### Exact creation path

1. `BookingWizard::selectSlot()` receives browser-provided start/end/slot metadata.
2. `BookingHoldService::acquireHold()` converts them to UTC, rejects past time, then transactionally checks booking/hold overlap and creates a hold.
3. Customer details are validated in the Livewire component.
4. `BookingWizard::confirmBooking()` forwards the same public start/end values, customer data, idempotency key, and visitor token.
5. `BookingService::createBooking()` performs an idempotency lookup, loads any session type, validates only future start/timezone, then enters a transaction.
6. Inside the transaction it rechecks the key, queries overlapping bookings with `FOR UPDATE`, queries other active holds with `FOR UPDATE`, resolves/creates a contact, creates the booking, converts matching holds, and records a booking event.

The dangerous gap is between “the UI displayed this candidate” and “the database may insert it”: the latter never proves the former.

### Transactions and locks

Transactions encompass collision checking and insertion, which is directionally correct. The problem is the lock target. A range query against no matching rows does not lock a durable application-owned slot row. InnoDB/MariaDB next-key locking may lock scanned index gaps under `REPEATABLE-READ`, and concurrent inserts may deadlock rather than double-insert. However:

- gap locks are isolation- and plan-dependent;
- they are not equivalent to a unique/exclusion invariant;
- there is no declared supported server/version/isolation contract;
- production is intended as MySQL but testing used MariaDB 10.4;
- the tests do not overlap transactions to observe blocking, deadlock, retry, or commits;
- the collision predicate spans intervals, so a simple unique start time would not be sufficient either.

The safe design is to lock a row whose identity is independent of whether the slot is empty, then recheck the interval under that lock.

### Holds

Positive behaviors:

- active, unexpired holds are excluded from displayed slots;
- expired holds are ignored;
- a visitor's prior active holds are released before a new one is created;
- successful booking marks exact-start holds for the visitor converted.

Defects:

- hold acquisition does not validate schedule/duration/horizon/notice;
- hold identity is not required at finalization;
- hold session token is never verified;
- hold conversion matches visitor/start only and does not require expiry, end time, session type, or hold ID;
- empty-slot concurrency is not proven;
- cleanup is not scheduled, so expired `active` rows grow indefinitely;
- public navigation away/back can leave unnecessary holds until expiry;
- no dedicated rate limit prevents automated hold exhaustion.

### Idempotency

The unique `idempotency_key` is valuable, and sequential duplicate calls return the same record. It is incomplete as an API contract because concurrent first use is not caught/re-read, and keys are not tied to a request hash. A robust implementation should store request fingerprint/result, return the original result only for the same fingerprint, reject key reuse for different data, and convert duplicate-key/deadlock outcomes into a deterministic response.

### Cancellation and status transitions

Cancellation is soft/non-destructive and creates booking/audit history. It reopens availability because collision queries exclude cancelled bookings. However public cancellation has no booking-policy cutoff enforcement or rate limit, and the reason is not explicitly bounded by validation in the public controller. Admin completion/no-show/cancellation transitions do not consistently lock the booking row, leaving lifecycle races possible. The seeded/public wording promises self-service rescheduling and a 24-hour policy that the public implementation does not provide/enforce.

### Rescheduling

Rescheduling preserves previous UTC/local snapshots in an event, but it repeats the unsafe range locking and does not call authoritative availability validation. It also has a current-vs-old business timezone mismatch. It should use the same calendar lock and reservation validator as initial booking.

### Required booking design tests

The redesigned path needs multi-process tests for exact collision, partial overlap on both sides, simultaneous holds, hold-vs-booking, booking-vs-reschedule, two reschedules, same idempotency key, different idempotency keys, deadlock retry, and contact creation. Each process must use an independent database connection and coordinate barriers so both transactions inspect an initially empty interval before either inserts.

## 8. Timezone / DST Review

### Verified behavior

- Laravel application timezone is UTC (`config/app.php`).
- Booking and hold instants are stored in UTC-named columns.
- IANA identifiers are validated using the platform timezone list.
- The business timezone defaults to `Africa/Cairo` and is configurable.
- Availability is generated from business-local recurring times and projected to customer timezone.
- Booking snapshots store business/customer local date/time and UTC offsets.
- `.ics` DTSTART/DTEND use canonical UTC `Z` values.

### Unverified or incorrect behavior

- Nonexistent local times during a spring-forward transition are not rejected explicitly. Parser normalization could silently move a requested time.
- Repeated local times during fall-back have no documented first/second occurrence policy and no UI disambiguation.
- The test called DST preservation starts from explicit UTC instants; it does not prove local slot generation/selection through a gap or fold.
- The “manual timezone switch” QA test compares Carbon conversions rather than driving the Livewire flow.
- Changing timezone after a slot selection does not clear/reproject the selected display payload/hold.
- Reports use current business timezone instead of immutable historical snapshots and label it “Cairo” even if configured otherwise.
- Admin reschedule can parse with current timezone and snapshot with the booking's old timezone.
- Future availability must use the current business timezone, but historical bookings must display their stored snapshot. The code only consistently follows the second half.

### Required correction

Define explicit wall-time parsing rules: reject nonexistent times; for ambiguous times require a UTC instant selected from generated slots or store the offset/fold choice. Never reconstruct a chosen instant from an ambiguous free-form local string. Clear/reload wizard selection when timezone changes. Use immutable snapshot fields in historical reports, and add Cairo plus DST-observing business/customer zone tests covering spring gaps, fall folds, midnight/date-line crossing, setting changes, and ICS parser round trips.

## 9. Security / Authorization Review

### Confirmed controls

- Web routes receive Laravel CSRF protection.
- Admin login regenerates the session, logout invalidates/regenerates the CSRF token, and login attempts are rate-limited.
- Booking confirmation tokens use 64 random characters and are unique.
- Model fillable lists are generally explicit; no broad `guarded = []` pattern was found.
- Resource files use the private `local` disk rather than a public URL.
- Analytics has an event allow-list and refuses server-only names at the browser endpoint.
- Blade output is escaped in most user-data locations.
- Dependency audits reported no known Composer/npm advisories at review time.

### Security gaps

1. **Authorization:** all authenticated admins have all capabilities; no policies/gates.
2. **Known seeded credential:** fixed super-admin email/password.
3. **Resource authorization:** direct download bypasses the gate/token.
4. **Upload validation:** any uploaded file type/extension is accepted from an authenticated admin, then served back as a download.
5. **Booking tampering:** Livewire public properties/action parameters control slot times and visitor identity without final derivation/validation.
6. **Abuse controls:** booking, holds, resource forms/downloads, game tracking, and cancellation lack dedicated throttles. An attacker can exhaust holds, create contacts/bookings, inflate analytics, and consume storage/database capacity.
7. **Analytics cookies:** `_va_visitor` and `_va_session` are explicitly non-Secure and non-HttpOnly. Session token ownership is not bound to visitor identity.
8. **Analytics payload:** arbitrary nested metadata has no schema/depth/value/size limits; allowed client events can be fabricated.
9. **Stored XSS boundary:** dynamic page content is rendered raw without an HTML sanitizer contract. Social link URLs are unvalidated and may accept dangerous schemes.
10. **Future publication leakage:** direct page/resource routes do not consistently enforce `published_at <= now()`.
11. **Public disabled games:** status is not enforced by public controllers.
12. **Security headers:** no application middleware for CSP, HSTS, frame protection, referrer policy, or permissions policy was found. These may be added at a reverse proxy, but that is undocumented and therefore not verified.
13. **Debug/deployment defaults:** `.env.example` uses `APP_DEBUG=true`, `APP_URL=http://localhost:8000`, SQLite, generic app/mail identity, and omits secure-cookie guidance despite the intended Herd/MySQL stack.

No raw SQL interpolation vulnerability was identified in the reviewed queries. Report filters use query builder bindings. No public arbitrary path parameter is passed directly to storage. These positives do not mitigate the authorization/gating defects above.

## 10. Data Integrity Review

### Schema invariants missing

- `contacts.email` is not unique.
- No constraint ensures booking/hold `end > start`.
- No database invariant prevents overlapping active bookings or holds.
- No constraint enforces availability exception uniqueness by date; service drops duplicates via `keyBy`.
- No constraint enforces non-overlapping/valid availability rule intervals.
- Status/role strings are comments/application validation only; the database accepts arbitrary values.
- Social platform uniqueness is not enforced.
- Daily metric uniqueness contains nullable columns, so MySQL/MariaDB can store multiple “unique” overall rows with NULL dimensions.
- Revision numbering/ownership constraints are weak and unused.

### Contact and relationship integrity

Merge moves bookings, resource requests, and downloads, which is good. It does not lock source/target, resolve all fields, normalize phones, handle already-merged/deleted targets robustly, or protect concurrent relationship writes. Duplicate detection only groups exact phone strings despite its comment claiming email detection. The leads page excludes anyone with any booking, while `Contact::isLead()` uses a different active-status concept, so admin counts/lists can disagree.

### Resource and event integrity

- A resource request stores the full `Referer` in a 255-character column without truncation, which can fail under strict SQL mode.
- Analytics controller permits 500-character `page` while the column is 255. The service catches database errors and returns success, silently losing the event.
- Resource downloads are recorded before file existence/success is known.
- Resource replacement/delete does not reconcile physical files.
- Direct resource/game analytics bypass the central service, so validation, bot flag, attribution, IP hashing, and deduplication differ by event source.

### History

Booking events and audit logs provide useful append-style history. Hard deleting a booking would cascade-delete its events, although normal booking deletion is soft and no hard-delete UI was found. Audit logs are not database-immutable and coverage is uneven, but the main admin mutations generally create records.

## 11. Admin / CMS / Resources / Analytics Review

### Admin route/page inventory

The application exposes 63 non-vendor routes. The authenticated admin platform includes dashboard, booking list/detail/status actions, availability, contacts/leads/merge, resources, games, settings, FAQ/social content, search, analytics, reports, health, and audit logs.

Functional but limited:

- booking ledger and detail views;
- cancellation/completion/no-show/reschedule actions;
- recurring rule create/toggle/delete and exception create/delete;
- contact browsing, notes, basic merge;
- FAQ CRUD and social link editing;
- resource CRUD shell;
- game edit shell;
- global search;
- reports/export shell;
- database/storage/cache health probes and audit list.

Visually present but misleading/incomplete:

- resource `is_gated` control has no persistence/effect;
- resource description field silently fails to save;
- “authenticated fallback PDF” is not authenticated;
- maintenance-mode setting has no effect;
- analytics booking funnel lacks booking lifecycle emissions;
- “disabled” games remain directly public and status vocabulary conflicts with `archived`;
- content page list has no edit/publish/revision actions;
- health says “MySQL connection active” even when connected to MariaDB and does not inspect critical operations;
- calendar wording overstates a filtered ledger;
- no administrators, backups, media, notification, or category pages exist.

### CMS

CMS completion is below the prior audit's practical implication. The database scaffolding exists, but only FAQ/social editing works. Pages are hard-coded or read-only database records; no draft/preview/publish/revision workflow exists. Draft status filtering prevents ordinary draft leakage, but future-published records leak through direct routes because `published_at` is not checked there.

### Resources

Email/contact capture works on the form happy path, but it is optional from an attacker's perspective because download is direct. Request attribution uses Laravel session UTM keys never populated by analytics middleware. Request and download metrics therefore cannot reliably connect to acquisition identity. The synthetic PDF should be removed from production behavior; test fixtures can create real sample files instead.

### Analytics

The event table and dashboard queries are extensive, but data provenance is not trustworthy enough for business decisions. Browser-created events, inconsistent identity paths, missing server conversions, hard-coded activity windows, weak bot checks, and raw-vs-rollup inconsistency mean the displayed funnels should be treated as development telemetry, not authoritative analytics.

## 12. Infrastructure and Operations Review

### Scheduling

`artisan schedule:list` shows only:

```text
5 0 * * * php artisan analytics:aggregate-daily --prune
```

The command aggregates yesterday's raw events, prunes events older than the configured retention, and removes leftover export files older than 24 hours. It is not protected with `withoutOverlapping()` or `onOneServer()`. There is no code evidence that a production scheduler is actually invoking Laravel every minute.

Missing schedules include hold cleanup, backups, backup verification, session/data cleanup, failed-job monitoring, scheduler heartbeat, and dedicated temporary-file cleanup.

### Backups

No backup capability exists. Database, uploaded files, encryption, off-host replication, retention, monitoring, restore docs, and restore drills are all missing. This is a release blocker for real customer data.

### Health monitoring

The health controller checks a trivial DB query, a local storage write/delete, and cache put/get, plus versions. It does not monitor free disk space, queues, failed jobs, scheduler freshness, backups, mail, file assets, migration drift, database engine/version/isolation, or external dependencies. Exception messages are displayed to any authenticated admin because role separation is absent.

### Deployment readiness

- No production deployment runbook.
- Default README only.
- `.env.example` contradicts the intended stack and does not document secure production settings.
- Local config is debug-enabled and not cached.
- Public storage link is missing.
- Default PATH PHP is incompatible; Herd PHP must be selected explicitly.
- No Git commit/history exists.
- No proof of a production MySQL test matrix; current tests ran on MariaDB 10.4.
- No queue-worker/scheduler/service configuration or monitoring documentation.

### README

`README.md` is the stock Laravel document. It does not explain Herd setup, required PHP selection, MySQL/MariaDB requirements, environment variables, database creation, seeding hazards, build commands, scheduler/queue configuration, storage links, backups/restores, booking concurrency design, timezones, tests, deployment, or operational troubleshooting.

## 13. Test Coverage Gaps

The passing suite is useful for rendering, happy paths, and sequential service behavior. It does not cover these high-risk behaviors:

### Booking and database concurrency

- True simultaneous booking attempts using independent processes/connections.
- True simultaneous hold acquisition on an empty slot.
- Hold vs booking and reschedule vs booking races.
- Overlapping-but-not-identical intervals in parallel.
- Deadlock handling/retries and final committed-row assertions.
- MySQL production-version tests at the declared isolation level, rather than only MariaDB 10.4.
- Concurrent same-key idempotency and key reuse with a different payload.
- Final booking without a hold, with a spoofed visitor token, expired hold, wrong session, wrong end, or another session's hold.
- Invalid `end <= start`, wrong session duration, inactive session type, off-hours slot, notice violation, horizon violation, and buffer violation at final write.
- Admin blocking/special-hours change after hold with the real visitor token. Current scenario I is a false positive.
- Simultaneous completion/cancellation/rescheduling state transitions.

### Timezone/DST

- Spring-forward nonexistent business/customer local time.
- Fall-back duplicated local time with deterministic occurrence selection.
- Cairo rule changes and a DST-observing business timezone.
- Wizard manual override and reload persistence.
- Timezone change after slot/hold selection.
- Business timezone setting change before reschedule and historical reporting.
- Independent RFC 5545 parser validation, newline escaping, Unicode, long-line folding, and multiple calendar clients.

### Contacts

- Concurrent contact creation for case/whitespace variants of one email.
- Database unique-key recovery.
- Concurrent relationship creation during merge.
- Merge preservation of resource requests/downloads, notes, timestamps, and attribution conflicts.
- Phone normalization and email duplicate detection.
- Reuse/restoration of soft-deleted contacts.

### Authorization and security

- Ordinary admin denial for super-admin-only features.
- Direct unauthenticated resource download before request/token.
- Expired, wrong-resource, replayed, and tampered download token.
- MIME/signature/extension mismatch and active-file upload rejection.
- Future publication date access for pages/resources.
- Disabled/archived game access.
- Stored XSS/dangerous social URL rejection.
- Secure/HttpOnly analytics cookie attributes and visitor/session binding.
- Flood tests for booking/holds, resources, games, cancellation, and analytics.
- Analytics nested metadata size/depth/type validation.

### Analytics/reports/operations

- Proof that real booking lifecycle services emit server-only analytics after commit.
- UTM persistence across landing, booking, resource request, and download.
- Bot behavior across direct resource/game events.
- Repeated/concurrent daily aggregation and NULL-dimension uniqueness.
- Stale rollup removal on rerun.
- Dashboard/report metric-definition parity and timezone boundaries.
- Yesterday/custom-range boundary correctness.
- Large export memory behavior and invalid formats/dates.
- Open generated XLSX files and inspect formula cells with an independent reader.
- Expired hold cleanup schedule and large-table performance.
- Backup creation, retention, failure alert, restore, and file/database consistency.
- Scheduler/queue/failed-job health.

### Misleading existing test names/claims

- `test_scenario_e_two_users_select_same_slot_concurrency_race()` is sequential.
- `test_concurrent_collision_throws_slot_unavailable_exception()` is sequential.
- Scenario I proves an omitted visitor token conflicts with a hold, not blocked-date revalidation.
- Scenario D starts with explicit UTC instants and does not exercise DST wall-time gaps/folds.
- Scenario B compares conversions rather than interacting with the manual timezone UI.
- Analytics tests insert `booking_completed` directly instead of proving that booking emits it.
- The resource gate test follows the intended form path but does not try the direct download bypass.

## 14. Top Issues To Fix First

The order below is dependency-aware: establish the reservation invariant and authoritative validator before expanding admin/CMS features.

### 1. Establish a real booking serialization primitive

- **Severity:** Critical
- **Affected files:** booking/hold/reschedule services; booking migrations; database configuration; concurrency tests
- **Problem:** Empty intervals have no guaranteed lock row and no overlap invariant.
- **Recommended correction:** Add a canonical tutor/business-date calendar lock row (or materialized slot inventory), lock it before every hold/booking/reschedule mutation, recheck overlaps, and define supported MySQL version/isolation.
- **Tests needed afterward:** Barrier-synchronized parallel booking/hold/reschedule tests on production-version MySQL, including overlaps and deadlock retry.

### 2. Build one authoritative reservation validator

- **Severity:** Critical
- **Affected files:** `BookingService`, `BookingHoldService`, `RescheduleService`, `AvailabilityService`, `BookingWizard`
- **Problem:** Final writes bypass schedule, exception, duration, notice, horizon, buffer, and active-session rules.
- **Recommended correction:** Derive duration server-side and validate every rule inside the serialized transaction. Do not trust browser end time or slot metadata.
- **Tests needed afterward:** Off-hours, blocked/special hours, rule changes after hold, invalid duration/range, inactive session, notice/horizon, and buffer tests at service and Livewire boundaries.

### 3. Make holds server-bound and mandatory for public completion

- **Severity:** Critical
- **Affected files:** hold schema/model/service, booking service, Livewire component
- **Problem:** Optional/spoofable visitor token substitutes for hold ownership.
- **Recommended correction:** Require exact hold ID plus secret/session binding; lock and validate it, then convert atomically.
- **Tests needed afterward:** Missing/expired/wrong visitor/wrong session/wrong slot/replayed hold and tampered Livewire property tests.

### 4. Fix resource authorization before serving files

- **Severity:** High
- **Affected files:** public resource controller/routes, resource schema/model/admin forms, tests
- **Problem:** Direct download bypasses gate; fake PDF masks missing files and corrupts metrics.
- **Recommended correction:** Persist gating, validate an expiring bound token, verify file first, record only successful downloads, remove fallback.
- **Tests needed afterward:** Direct denial, expiry, replay policy, wrong resource/contact, missing file, scheduled publication, and successful request/download linkage.

### 5. Enforce administrator capabilities

- **Severity:** High
- **Affected files:** admin routes/controllers, authorization provider/policies, administrator model/tests
- **Problem:** `admin` and `super_admin` are equivalent.
- **Recommended correction:** Define and enforce gates/policies/middleware for settings, security/audit, backups, administrators, content, resources, and operations.
- **Tests needed afterward:** Full role matrix with allow/deny assertions and direct-route tests.

### 6. Make contact identity a database invariant

- **Severity:** High
- **Affected files:** contact migration/model/service/merge/tests
- **Problem:** Concurrent normalized email creation can duplicate contacts; merge can race/lose fields.
- **Recommended correction:** Clean data, add unique normalized email, catch duplicate inserts, normalize updates, lock merges, define field-conflict policy.
- **Tests needed afterward:** Parallel creation, case/whitespace variants, soft-delete handling, concurrent merge relationships, and all relationship preservation.

### 7. Repair analytics provenance before relying on dashboards

- **Severity:** High
- **Affected files:** booking/resource/game services/controllers, analytics middleware/service/command, reports
- **Problem:** Missing authoritative conversions, broken identity/UTM propagation, spoofable client data, inconsistent rollups.
- **Recommended correction:** Emit after-commit server events, unify identity, persist attribution, constrain metadata, secure cookies, add endpoint-specific throttles, make rollups idempotent and use them consistently.
- **Tests needed afterward:** End-to-end booking/resource attribution, bot filtering, rate limits, tampering, rollup reruns/concurrency, and dashboard/report parity.

### 8. Correct timezone/DST and reschedule semantics

- **Severity:** High
- **Affected files:** timezone/availability/reschedule services, wizard, ICS, reports
- **Problem:** Gap/fold handling is undefined; timezone changes can leave stale selection; reschedule/report snapshots are inconsistent.
- **Recommended correction:** Reject/resolve wall-time ambiguity explicitly, use selected UTC instants, clear selection on timezone change, use current zone for future scheduling and snapshots for history, fix ICS escaping/folding.
- **Tests needed afterward:** Gap/fold, setting changes, manual override, stale hold, historical report, and parser-based ICS tests.

### 9. Implement real backups and operational monitoring

- **Severity:** High
- **Affected files:** new operational command/config/schedule/health UI and documentation
- **Problem:** Production data is unrecoverable through any implemented process.
- **Recommended correction:** Encrypted database/storage backup, off-host copy, retention, alerts, scheduler/queue/failed-job/disk/backup health, documented and tested restores.
- **Tests needed afterward:** Backup failure/success, retention, integrity, point-in-time restore rehearsal, and health alert tests.

### 10. Secure upload and file lifecycle

- **Severity:** High
- **Affected files:** admin resource controller/forms, storage service/schema, tests
- **Problem:** MIME/extension/signature mismatch accepted; descriptions/gating broken; orphan files.
- **Recommended correction:** Strict validation/content inspection, generated filenames, publish preconditions, transactional replacement, cleanup policy, fix field names.
- **Tests needed afterward:** Each allowed type, disguised executable/polyglot policy, size, mismatch, replacement, delete, and missing-file publication.

### 11. Complete or remove misleading admin/CMS controls

- **Severity:** Medium
- **Affected files:** CMS/resources/games/settings/admin views and controllers
- **Problem:** Controls claim behavior that does not exist (`is_gated`, maintenance mode, page editing, disabled-game hiding).
- **Recommended correction:** Implement each control end-to-end or remove it until ready; add page/revision/media workflow with sanitization and authorization.
- **Tests needed afterward:** Draft/preview/publish/revision restore, maintenance bypass rules, game visibility, and every admin form's persisted effect.

### 12. Establish a production baseline and runbook

- **Severity:** Medium
- **Affected files:** `README.md`, `.env.example`, deployment configuration, Git repository
- **Problem:** Environment defaults contradict the intended stack; no deploy/operate/restore instructions or history exist.
- **Recommended correction:** Commit a reviewed baseline, document Herd PHP selection and exact MySQL support, secure environment settings, scheduler/queue/storage setup, deployment, monitoring, backup/restore, and rollback.
- **Tests needed afterward:** Clean-machine installation, fresh MySQL migration/seed without known credentials, production-config smoke test, build, scheduler, queue, storage, and restore rehearsal.

---

Overall verdict: **the application is a promising development-stage implementation with a passing happy-path suite, but the core booking safety, resource authorization, identity integrity, analytics provenance, and production recovery guarantees are not yet sufficient for real customer bookings or data.**
