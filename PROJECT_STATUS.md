# Project Status — Egyptian Arabic with Abdallah

**Authoritative review date:** 2026-09-21
**Source specifications:** `ARABIC TUTORING WEBSITE FINAL 16 Sep.md` and the newer `Arabic w Abdallah Edits V2.md`
**Current status:** V2 implementation and regression pass complete; no verified CRITICAL or HIGH application issue remains.

This file supersedes the September 17 status report. The old report’s 91-test/14-migration baseline and customer self-service rescheduling claim were stale.

## Executive Summary

The current repository contains the requested V2 architecture and the previously remediated booking, availability, analytics, CMS, resource, games, reporting, backup, and admin systems. The review inspected routes, migrations, models, services, controllers, Livewire, middleware, admin authorization, Blade views, commands, scheduler registration, seeders/factories, dependencies, environment templates, README, and the test suite.

Four material V2 discrepancies were found and fixed during this pass: cancellation incorrectly inherited the 24-hour rescheduling cutoff, several supported timezone country codes had no local SVG flag asset, the persisted canonical Privacy Policy omitted its required operational/legal-review disclosure, and a stale public reschedule view still contained the disabled self-service form. Cancellation is now 4 hours, direct-contact rescheduling remains 24 hours, every country code in the supported timezone mapping has a local flag asset, the privacy correction is stored as a new immutable source revision, and the public reschedule view is now direct-contact-only. Forward migrations update only known legacy defaults without deleting or silently replacing authored settings.

The local environment is intentionally `APP_ENV=local` with debug enabled and has no production GeoLite2 file/off-site disk configured. Those are deployment configuration tasks, not unresolved application logic defects.

## Verification Baseline

| Check | Result |
| --- | --- |
| Full PHPUnit/Laravel suite | **PASS — 404 tests, 2,441 assertions, 0 failures, 0 errors**; Herd PHP 8.4, MariaDB/InnoDB |
| Laravel Pint | **PASS — clean** (`vendor/bin/pint --dirty --format agent`) |
| Production frontend build | **PASS — Vite 8.3.0** (`npm.cmd run build`) |
| Blade compilation | **PASS** (`php artisan view:cache`) |
| Database migrations | **PASS — all 32 migrations Ran**, including the V2 policy, brand, and privacy-disclosure migrations |
| Scheduler registration | **PASS — 5 tasks**: heartbeat, hold cleanup, analytics/pruning, backup, session cleanup |
| Route compilation | **PASS — 158 routes**, including localized routes and legacy `/book` redirects |
| Git whitespace check | **PASS — `git diff --check`** (only normal CRLF normalization warnings) |

## V2 Requirement Verification

| V2 requirement group | Verdict | Evidence |
| --- | --- | --- |
| Phase A brand, admin drawer, `/book` 301s | VERIFIED | `config/business.php`, `layouts/public.blade.php`, `layouts/admin.blade.php`, `NormalizeTrailingSlash`, `PhaseAVerificationTest` |
| Phase B portable country schema and non-destructive session-type sync | VERIFIED | Migrations `2026_09_21_000001`–`000007`, `SyncDiagnosticSessionType`, `PhaseBVerificationTest`, rollback tests |
| Phase C trusted geolocation and immutable country semantics | VERIFIED | `GeoIpService`, `GeoIpUpdateCommand`, `AnalyticsService`, `TrackVisitorSession`, Phase C tests |
| Phase C daily Cairo country rollups | VERIFIED | `AggregateDailyCountryMetricsCommand`, `DailyCountryMetric`, bot and idempotency tests |
| Phase D timezone display and reference-instant offsets | VERIFIED | `TimezoneDisplayService`, Livewire `BookingWizard`, local SVG assets, Phase D tests |
| Phase D booking/holds/concurrency/idempotency/availability | VERIFIED | `BookingService`, `BookingHoldService`, `AvailabilityService`, `RescheduleService`, MariaDB concurrency suite |
| Phase E policy localization and pricing | VERIFIED | `PolicyPagesSeeder`, `ContentService`, `TranslationService`, `PricingController`, pricing translations/views |
| Phase F funnel reconciliation, attribution, retention, reports | VERIFIED | Analytics/reporting services, Cairo rollup/funnel/attribution suites, cutover setting |
| Admin/CMS/resources/media/games/exports/health/backups | VERIFIED | Admin route/controller matrix and CMS/media/resource/report/backup/health tests |

## Material Fixes Completed In This Pass

### V2 policy cutoff split — HIGH, resolved

**Root cause:** `CancellationService` and `RescheduleService` both read `booking_cancellation_cutoff_hours`, while V2 requires 4 hours for cancellation and 24 hours for direct-contact rescheduling.

**Changes:**

- `CancellationService` now defaults to `booking_cancellation_cutoff_hours = 4`.
- `RescheduleService` now reads `booking_reschedule_cutoff_hours = 24`.
- `Admin\SettingController` validates and persists both settings.
- Admin settings, booking wizard, confirmation copy, and seed defaults explain the distinct rules.
- `2026_09_21_000007_split_booking_policy_cutoffs.php` safely migrates the previous known 24-hour default and adds the new setting; rollback is intentionally non-destructive.
- `BookingLifecycleAndPolicyCutoffTest` proves the 4-hour cancellation and 24-hour rescheduling boundaries; `BookingPolicyMigrationTest` proves migration defaults.

### Legacy persisted brand setting — HIGH, resolved

The existing database still contained the old generic `site_name` value even though the application configuration had moved to Abdallah.

- `2026_09_21_000008_reconcile_legacy_brand_setting.php` updates only the exact old default `Egyptian Arabic Tutoring`, inserts the V2 default when absent, and preserves custom administrator branding on rollback.
- `BrandSettingMigrationTest` and `PhaseAVerificationTest` prove the migrated value and rendered identity.

### Misleading resource fallback copy — HIGH, resolved

`resources/views/admin/resources/create.blade.php` no longer claims a synthetic sample PDF is generated. Missing protected files remain a 404, as required by the safe resource-download behavior.

### Incomplete mapped timezone flags — HIGH, resolved

`TimezoneDisplayService` supported country mappings beyond the originally bundled flag files, so valid mapped zones could display a generic globe. Local SVG assets were added for every mapped country code and Phase D now checks the complete set plus Tokyo/Japan rendering.

### Missing canonical privacy disclosure — HIGH, resolved

The seeded and persisted English Privacy Policy omitted the V2-required operational disclosure and legal-review note. The seeder now includes it, and `2026_09_21_000009_reconcile_privacy_policy_disclosure.php` reconciles only the known legacy canonical text through a new immutable revision while leaving authored variants untouched.

### Stale public reschedule form — HIGH, resolved

The old public reschedule Blade template still exposed slot-selection controls even though customer rescheduling is prohibited. It now renders only direct-contact instructions, and the feature test proves that no form remains.

### Stale test assumptions — HIGH, resolved

`PhaseBVerificationTest` now supplies the schema-required legacy session-type slug, and `CanonicalAvailabilityValidationTest` derives its availability rule weekday from the actual future slot instead of assuming a same-day clock window. These changes correct test fixtures; application behavior was not weakened.

## Important End-to-End Areas Rechecked

- Booking finalization locks buffer-expanded calendar rows, authenticates hold ID/token/session/visitor identity, validates canonical availability, and preserves idempotency.
- Rescheduling is administrator-executed; public token rescheduling returns a direct-contact notice/403 and cannot mutate a booking.
- Expired holds are rejected/cleaned by command; active holds are buffer-aware.
- UTC appointment instants and Cairo/customer snapshots use IANA zones and DST gap/fold handling; ICS exports remain UTC-canonical.
- Admin role middleware and controller-level operational authorization prevent ordinary admins from sensitive system/backup/notification actions.
- Contacts use normalized unique identity and locked merges preserving relationships.
- Resource gates use bound, one-use, expiring grants; path/MIME checks and missing-file 404s are enforced.
- Analytics client events are allow-listed, rate-limited/deduplicated, server-only completions are rejected from public ingestion, and country rollups exclude bots.
- Reports reconcile raw events with durable Cairo daily rollups and disclose non-comparable daily-unique sums; CSV/XLSX output sanitizes formula injection.
- CMS pages, games, resources, FAQs, policies, translations, drafts, previews, stale states, and revisions have end-to-end admin/public coverage.
- Backups include database/private/public assets with manifest verification, retention, off-site replication hooks, restore-drill coverage, and health/scheduler checks.

## Deployment Preconditions (Not Code Defects)

Before production release, configure and verify:

- `APP_ENV=production`, `APP_DEBUG=false`, a production `APP_KEY`, HTTPS `APP_URL`, database/mail credentials, and `php artisan config:cache`.
- A real GeoLite2 database at `storage/geoip/GeoLite2-Country.mmdb` if local country lookup is desired; leave `GEOIP_TRUSTED_PROXIES` empty unless the actual proxy CIDR is known and configured.
- Off-site backup disk and retention policy; confirm a restore drill on a separate target.
- Queue worker supervision and a once-per-minute `schedule:run` trigger.
- `php artisan storage:link`, built assets, writable cache/session/storage paths, and a monitored `/admin/health` endpoint.

## Remaining Critical / High Issues

None verified in the current repository after the final test/build/migration/scheduler pass.
