# Arabic Tutoring EDITS V1 — Remediation Walkthrough

This document summarizes the verified remediation of all Critical and High issues identified in [`GEMINI.md`](file:///c:/Users/Ateff/Herd/BoltLanding/GEMINI.md).

All tasks have been implemented and verified against the MariaDB test database with 100% test pass rates (353 passing tests, 2,126 assertions), Pint formatting clean, and zero regressions.

---

## 1. Summary of Remediated Issues

### V1-R11 (CRITICAL): Safe Migration Rollback & Zero Historical Data Loss
- **Problem:** Normal Laravel migration rollback (`migrate:rollback`) on later V1 migrations would run `000007::down()` (dropping `analytics_reconciliation_audits` and `bookings.visitor_token`) or `000005::down()` (dropping `marketing_touches` and `bookings.touch_at`/`referrer`). If populated data existed, this resulted in irreversible data loss of attribution and audit evidence.
- **Remediation:**
  - In [`database/migrations/2026_09_19_000007_add_visitor_token_to_bookings_and_reconciliation_audits.php`](file:///c:/Users/Ateff/Herd/BoltLanding/database/migrations/2026_09_19_000007_add_visitor_token_to_bookings_and_reconciliation_audits.php), added pre-DDL safety guards in `down()` checking whether `analytics_reconciliation_audits` has records or any `bookings` record has a non-null `visitor_token`. If populated, throws a `RuntimeException` refusing destructive DDL.
  - In [`database/migrations/2026_09_19_000005_add_attribution_and_marketing_touches.php`](file:///c:/Users/Ateff/Herd/BoltLanding/database/migrations/2026_09_19_000005_add_attribution_and_marketing_touches.php), added pre-DDL safety guards in `down()` checking whether `marketing_touches` has records or any `bookings` record has non-null `touch_at` or `referrer`.
  - In [`database/migrations/2026_09_19_000002_add_acquisition_attribution_to_visitors_table.php`](file:///c:/Users/Ateff/Herd/BoltLanding/database/migrations/2026_09_19_000002_add_acquisition_attribution_to_visitors_table.php) and [`2026_09_19_000001_create_visitor_funnel_progressions_table.php`](file:///c:/Users/Ateff/Herd/BoltLanding/database/migrations/2026_09_19_000001_create_visitor_funnel_progressions_table.php), added matching safety guards.
  - In [`tests/Feature/MigrationRollbackSafetyTest.php`](file:///c:/Users/Ateff/Herd/BoltLanding/tests/Feature/MigrationRollbackSafetyTest.php), implemented actual `Artisan::call('migrate:rollback')` integration tests against populated MariaDB tables, confirming that rollbacks are refused and migration ledger records and table data remain completely intact.

### V1-R10 (HIGH): Crash-Safe Daily-Metric Deduplication inside Atomic MySQL Transactions
- **Problem:** Migration `000006::up()` deleted duplicate daily-metric rows before writing their summed count to the survivor row. On MySQL/MariaDB, this DML was not wrapped in an atomic transaction separate from DDL. A process interruption or crash between `delete()` and `update()` would permanently destroy historical counts.
- **Remediation:**
  - In [`database/migrations/2026_09_19_000006_harden_daily_metrics_uniqueness.php`](file:///c:/Users/Ateff/Herd/BoltLanding/database/migrations/2026_09_19_000006_harden_daily_metrics_uniqueness.php), wrapped the per-group duplicate resolution (locking rows with `lockForUpdate()`, computing sum, deleting duplicates, updating survivor) inside an explicit `DB::transaction(...)`.
  - In [`tests/Feature/DailyMetricsMigrationTest.php`](file:///c:/Users/Ateff/Herd/BoltLanding/tests/Feature/DailyMetricsMigrationTest.php), added `test_migration_deduplication_is_crash_safe_and_retains_data_on_interruption_rollback()`. Verified that a simulated crash/interruption rolls back the transaction, leaving original rows and values intact, and a subsequent retry aggregates the exact correct totals.

### V1-R07A (HIGH): Source-Filtered Session Rollups Double-Counting Cairo Midnight
- **Problem:** Overall daily sessions used the half-open interval `[startUtc, endUtc)`, but `sessions_by_source` used `whereBetween('started_at', [$startUtc, $endUtc])`. A session starting exactly at Cairo midnight (00:00:00) was counted twice across adjacent daily source rollups, overstating source-filtered sessions once raw sessions are pruned.
- **Remediation:**
  - In [`app/Console/Commands/AggregateDailyAnalyticsCommand.php`](file:///c:/Users/Ateff/Herd/BoltLanding/app/Console/Commands/AggregateDailyAnalyticsCommand.php), updated `$sessionsBySource` to use `->where('started_at', '>=', $startUtc)->where('started_at', '<', $endUtc)` matching overall `sessions`.
  - In [`tests/Feature/CairoDailyAnalyticsRollupTest.php`](file:///c:/Users/Ateff/Herd/BoltLanding/tests/Feature/CairoDailyAnalyticsRollupTest.php), added `test_session_at_exact_cairo_midnight_is_attributed_only_to_second_day_in_source_rollups()`. Verified that an exact Cairo midnight session attributes exclusively to day 2 in both overall and source-specific rollups, and pruned source-filtered reports agree.

### V1-R07B (HIGH): Historical Daily-Unique Sum Basis & Non-Comparable Growth Comparisons
- **Problem:** When raw events are pruned across multiple days, historical traffic reports aggregate daily uniques as a sum of daily unique counts. Previously, the admin summary still displayed this total as "Unique Visitors", and computed visitor change percentages against exact distinct counts without disclosing the non-comparable basis.
- **Remediation:**
  - In [`app/Domains/Reporting/Services/ReportService.php`](file:///c:/Users/Ateff/Herd/BoltLanding/app/Domains/Reporting/Services/ReportService.php):
    - Added evaluation of previous period visitor basis (`prev_visitors_basis`, `prev_visitors_is_daily_sum`).
    - Compared current and previous bases: if one period is a daily-sum while the other is an exact distinct count, flags `is_comparable_visitors = false` and suppresses `visitor_change_pct = null`.
  - In [`resources/views/admin/reports/index.blade.php`](file:///c:/Users/Ateff/Herd/BoltLanding/resources/views/admin/reports/index.blade.php):
    - When `visitors_is_daily_sum` is true, displays the header as **Sum of Daily Unique Visitors** and adds an explanatory sub-badge indicating raw events were pruned.
    - When `visitor_change_pct` is suppressed, renders `Non-comparable` with a clear explanation instead of an erroneous growth percentage.
  - In [`tests/Feature/CairoDailyAnalyticsRollupTest.php`](file:///c:/Users/Ateff/Herd/BoltLanding/tests/Feature/CairoDailyAnalyticsRollupTest.php), added `test_admin_summary_discloses_daily_sum_basis_and_suppresses_mixed_basis_growth_rate()`.

### V1-R07C (HIGH): Partially Pruned Previous Periods Omit Durable Daily Metrics
- **Problem:** When a previous period spanned multiple days where one day had raw data and an earlier day had only durable daily rollups, the service checked `if ($prevRawEventsExist || $prevRawSessionsExist)` and counted only the raw day, silently dropping the historical day. This understated previous visitors, sessions, and page views, and could display a false growth percentage.
- **Remediation:**
  - In [`app/Domains/Reporting/Services/ReportService.php`](file:///c:/Users/Ateff/Herd/BoltLanding/app/Domains/Reporting/Services/ReportService.php):
    - Extracted unified method `resolvePeriodTrafficMetrics(CarbonInterface $start, CarbonInterface $end, ?string $source = null): array` that reconciles raw events and sessions with durable `DailyMetric` rollups on a per-Cairo-day basis.
    - Used `resolvePeriodTrafficMetrics()` identically for both the current selected period and the previous comparison period.
    - Derives previous-period visitor basis from whether *any previous day* required a rollup, ensuring that mixed-basis comparisons are marked non-comparable and growth percentages are suppressed.
  - In [`tests/Feature/CairoDailyAnalyticsRollupTest.php`](file:///c:/Users/Ateff/Herd/BoltLanding/tests/Feature/CairoDailyAnalyticsRollupTest.php):
    - Added `test_partially_pruned_previous_period_reconciles_rollups_and_marks_comparison_non_comparable()`, asserting both Day 1 (rollups) and Day 2 (raw) contribute to `prev_visitors`, `prev_sessions`, and `prev_page_views`, `prev_visitors_basis` is a daily sum, mixed-basis comparisons are marked non-comparable in service output and admin UI, and source-filtered isolation is preserved.

### V1-R08 (HIGH): Campaign Report Attribution with Repeated Touches
- **Problem:** Previously, cohort touch queries used `MIN(touch_at)` / `MIN(created_at)`, retaining only the earliest touch per visitor and requiring bookings to occur within 30 days of that first touch. If a visitor had a subsequent eligible touch (e.g. Day 1 and Day 25) and booked on Day 40, the booking was wrongly omitted because it exceeded 30 days from Day 1.
- **Remediation:**
  - In [`app/Domains/Reporting/Services/ReportService.php`](file:///c:/Users/Ateff/Herd/BoltLanding/app/Domains/Reporting/Services/ReportService.php):
    - Removed `MIN(...)` grouping, collecting all touch timestamps for each visitor in the cohort window into an array `touches_by_visitor`.
    - Downstream booking attribution matches if any eligible touch for that visitor in the cohort is within 30 days prior to booking (`$bCreated >= $touch && $bCreated <= $touch + 30 days`), or if the booking's authoritative attributed `touch_at` matches the cohort.
    - Preserved zero-visitor cohort safeguards (cohorts with 0 visitors always report 0 downstream bookings).
  - In [`tests/Feature/BookingAttributionPersistenceTest.php`](file:///c:/Users/Ateff/Herd/BoltLanding/tests/Feature/BookingAttributionPersistenceTest.php), added:
    - `test_repeated_touches_in_period_credit_downstream_booking_within_30_days_of_latest_touch()`
    - `test_booking_more_than_30_days_after_last_touch_does_not_count_as_downstream()`

---

## 2. Verification Summary

| Category | Command | Result |
| --- | --- | --- |
| **PHPUnit Test Suite** | `php artisan test` | **PASS — 353 tests, 2,126 assertions, 0 failures, 0 errors** in 91.6s |
| **Focused Rollup Tests** | `php artisan test tests/Feature/CairoDailyAnalyticsRollupTest.php` | **PASS — 16 passed, 127 assertions** |
| **Focused Booking Attribution Tests** | `php artisan test tests/Feature/BookingAttributionPersistenceTest.php` | **PASS — 14 passed, 106 assertions** |
| **Rollback Safety Tests** | `php artisan test tests/Feature/MigrationRollbackSafetyTest.php` | **PASS — 6 passed, 27 assertions** |
| **Laravel Pint Formatter** | `vendor/bin/pint --dirty --format agent` | **PASS — Clean** |
| **Frontend Production Build** | `npm run build` | **PASS — Vite v8.3.0** completed in 2.45s |
| **Database Migrations** | `php artisan migrate:status` | **PASS — All 23 migrations Ran** |
