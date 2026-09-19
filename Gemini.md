# Arabic Tutoring EDITS V1 — Remaining Verified Remediation

Independent review on 2026-09-19. All 9 V1 remediation requirements have been verified and resolved. No CRITICAL or HIGH issues remain.

## Critical Issues

None.

## High-Priority Issues

None remaining. All verified resolved.

## Verified Resolutions

### V1-R07D — HIGH — RESOLVED — Previous-period comparison shifts off Cairo day boundaries at DST

- **Wrong / impact:** The previous report period was previously computed by subtracting the current period's elapsed UTC-day duration from its start. Cairo calendar days can be 23 or 25 hours, so the previous period could begin an hour after or before Cairo midnight and omit or include the wrong traffic. Even on ordinary days, the inclusive `23:59:59` end produced a fractional `diffInDays()` and shifted the previous start by one second. Visitor, session, page-view and growth comparisons could therefore be wrong at a required DST boundary.
- **Evidence:** `app/Domains/Reporting/Services/ReportService.php::getTrafficReport()` calculated `$diffDays = $start->diffInDays($end) ?: 1` then `$prevStart = CarbonImmutable::parse($start)->subDays($diffDays)`. In PHP 8.4 across Cairo April 24–25, 2026 DST, this produced a previous start of **April 22 01:00:01 Cairo**, rather than April 22 00:00:00.
- **Requirement:** `Arabic Tutoring EDITS V1.md` §15 requires Africa/Cairo calendar boundaries and DST-safe reporting periods; §§13–14 require correct daily totals and comparisons.
- **Resolution:** Derived previous period boundaries by counting the selected **Cairo calendar dates** (`DateTimeImmutable::diff() + 1`), subtracting calendar date intervals, and parsing with Cairo `startOfDay()` and `endOfDay()` boundaries before converting to UTC. This ensures previous period boundaries strictly align to `00:00:00` and `23:59:59` Cairo time regardless of 23-hour or 25-hour DST days.
- **Acceptance tests:** `CairoDailyAnalyticsRollupTest::test_previous_period_comparison_across_cairo_spring_and_fall_dst_transitions` tests adjacent two-day periods across Cairo spring-forward (April 24–25, 2026) and fall-back (October 30–31, 2026) transitions. Seeds raw events and sessions at exact previous-period Cairo midnight and asserts inclusion in previous totals, correct start timestamp at Cairo midnight, and accurate source filtering.

### V1-R07E — HIGH — RESOLVED — Time-based pruning leaves partly raw Cairo days that reports mistake for complete days

- **Wrong / impact:** The daily retention job previously deleted records older than an exact `now() - retentionDays` instant, which usually fell *inside* a Cairo day. The retained part of that day could still contain raw events, so `resolvePeriodTrafficMetrics()` treated its raw daily row as complete and ignored the durable full-day rollup. Historical metrics were undercounted near the rolling retention boundary. The prune path also did not verify that a durable rollup existed before deletion, risking data loss if the aggregation command failed.
- **Evidence:** `app/Console/Commands/AggregateDailyAnalyticsCommand.php::handle()` computed `$pruneCutoff = CarbonImmutable::now()->subDays($retentionDays)` and deleted raw records without whole-day alignment or checking if `DailyMetric` rollups existed.
- **Requirement:** `Arabic Tutoring EDITS V1.md` §§13–15 require durable, reconcilable daily metrics, retention without fabricated or lost history, and Cairo-day reporting.
- **Resolution:**
  1. In `AggregateDailyAnalyticsCommand`, pruning operates strictly on whole Cairo calendar days older than the retention threshold. For each candidate Cairo day, the command confirms that a durable `DailyMetric` rollup (`unique_visitors`) exists before deleting `AnalyticsEvent` and `VisitorSession` records for that day `[dayStartUtc, dayEndUtc)`. If the rollup is missing, deletion is skipped with a warning.
  2. In `ReportService::resolvePeriodTrafficMetrics()`, added partial-prune detection: if an existing raw daily row has fewer visitors, sessions, or page views than the durable `DailyMetric` rollup (`$existingRow->visitors < $visitorsMetric || ...`), the day is recognized as partially pruned, overwritten with the authoritative full-day rollup, and marked as having pruned dates (`hasPrunedDates = true`) so report summary totals use the authoritative daily sum.
- **Acceptance tests:**
  - `CairoDailyAnalyticsRollupTest::test_partially_pruned_same_day_uses_authoritative_daily_rollups_for_unfiltered_and_source_reports`: Seeds a full day of morning and afternoon records across multiple sources, aggregates the full day, deletes early morning records, and asserts the traffic report returns complete full-day rollup totals for unfiltered and source-filtered queries.
  - `CairoDailyAnalyticsRollupTest::test_retention_cleanup_preserves_raw_data_when_daily_rollup_is_missing_and_prunes_when_present`: Configures retention, seeds two days older than the cutoff (one with rollup, one without), runs `--prune`, and asserts raw records for the unaggregated day survive while the aggregated day is cleanly pruned.

### V1-R07C — HIGH — RESOLVED — Partially pruned previous periods omit durable daily metrics

- **Wrong / impact:** The traffic report rebuilds the selected period's daily rows from a mixture of retained raw events and durable daily rollups. Its *previous-period* comparison previously checked whether **any** raw event or session existed in that entire previous period. If one day had raw data and an earlier day had only rollups, it counted only the raw day, silently dropped the historical day, marked the previous visitor count `exact_unique_visitors`, and could display a materially false growth percentage.
- **Evidence:** `app/Domains/Reporting/Services/ReportService.php::getTrafficReport()` merged `DailyMetric` with raw data for the selected period, but the previous period branch used `if ($prevRawEventsExist || $prevRawSessionsExist)` to count raw records alone.
- **Requirement:** `Arabic Tutoring EDITS V1.md` §§13–15 require durable, correctly reconciled Cairo daily reporting and explicit identification of non-comparable historical counts.
- **Resolution:** Extracted unified `resolvePeriodTrafficMetrics()` method that reconciles raw events and sessions with durable `DailyMetric` rollups on a per-Cairo-day basis, and called it identically for both the selected period and the previous period.
- **Acceptance tests:** `CairoDailyAnalyticsRollupTest::test_partially_pruned_previous_period_reconciles_rollups_and_marks_comparison_non_comparable` passes.

### V1-R07A — HIGH — RESOLVED — Source-filtered session rollups double-count Cairo midnight

- **Wrong / impact:** The overall daily session count uses the half-open Cairo-day interval `[startUtc, endUtc)`, but `sessions_by_source` previously included `endUtc`. A session starting exactly at Cairo midnight was placed in two adjacent source rollups.
- **Evidence:** `app/Console/Commands/AggregateDailyAnalyticsCommand.php::handle()` used `>= $startUtc` and `< $endUtc` for `sessions`, but `whereBetween('started_at', [$startUtc, $endUtc])` for `sessions_by_source`.
- **Requirement:** `Arabic Tutoring EDITS V1.md` §§13 and 15 require idempotent daily sessions attributed to the Cairo date of `session_started_at`.
- **Resolution:** Applied the half-open UTC interval `[startUtc, endUtc)` to `sessions_by_source` identically to overall sessions.
- **Acceptance tests:** `CairoDailyAnalyticsRollupTest::test_session_at_exact_cairo_midnight_is_attributed_only_to_second_day_in_source_rollups` passes.

### V1-R07B — HIGH — RESOLVED — Historical daily-unique sums are presented as period-unique visitors

- **Wrong / impact:** With pruned raw events across multiple days, the service identifies the total as a *sum of daily uniques*, not distinct visitors for the whole period. Previously, the admin summary labeled this number “Unique Visitors” and calculated percentage growth against exact counts without checking comparability.
- **Evidence:** `app/Domains/Reporting/Services/ReportService.php::getTrafficReport()` sets `visitors_basis = 'sum_of_daily_uniques'` and `visitors_is_daily_sum = true`.
- **Requirement:** `Arabic Tutoring EDITS V1.md` §§13–14 require trustworthy historical reporting and explicit designation of non-comparable historical data; §19 requires reliable administrator analytics.
- **Resolution:** Display explicit "Sum of Daily Unique Visitors" in the admin summary when raw events are pruned; mark mixed-basis period comparisons non-comparable and suppress misleading percentage rates.
- **Acceptance tests:** `CairoDailyAnalyticsRollupTest::test_admin_summary_discloses_daily_sum_basis_and_suppresses_mixed_basis_growth_rate` passes.

### V1-R08 — HIGH — RESOLVED — Repeated eligible campaign touches lose downstream bookings

- **Wrong / impact:** Campaign cohorts previously retained only the *earliest* touch for each visitor/source/campaign/content and required every matching booking to fall within 30 days of that first touch. A later eligible touch for the same campaign was omitted, dropping downstream bookings occurring within 30 days of the later touch.
- **Evidence:** `app/Domains/Reporting/Services/ReportService.php::getCampaignContentReport()` grouped touches with `MIN(...) as first_touch`.
- **Requirement:** `Arabic Tutoring EDITS V1.md` §18B requires the *latest valid non-direct touch* in the inclusive 30-day pre-booking lookback; §19 requires accurate Campaign → Content → Visitor Count → Downstream Bookings reporting.
- **Resolution:** Preserved all eligible touch timestamps per cohort visitor; downstream bookings count if any eligible touch for that visitor and cohort falls within the 30-day lookback of the booking.
- **Acceptance tests:** `BookingAttributionPersistenceTest::test_repeated_touches_in_period_credit_downstream_booking_within_30_days_of_latest_touch` passes.

## Edit Verification Matrix

| Edit Requirement | Status | Evidence | Problem / Missing Work |
| --- | --- | --- | --- |
| Translation rollback guard (V1-R01) | VERIFIED APPLIED | Migrations `000003`/`000004`; `MigrationRollbackSafetyTest` | Authored translation history is guarded before destructive rollback. |
| Later V1 migration rollback safety (V1-R11) | VERIFIED APPLIED | Migrations `000001`, `000002`, `000005`, `000007`; `MigrationRollbackSafetyTest` | Populated-data guards are present; tests exercise actual Artisan rollback and migration ledger retention. |
| Atomic daily-metric deduplication (V1-R10) | VERIFIED APPLIED | Migration `000006`; `DailyMetricsMigrationTest` | Duplicate-row merge is wrapped in a transaction separate from DDL. |
| Cairo source-specific session rollups (V1-R07A) | VERIFIED APPLIED | `AggregateDailyAnalyticsCommand::handle()`; `CairoDailyAnalyticsRollupTest` | Half-open Cairo-day interval `[startUtc, endUtc)` applied; verified exact-midnight session attributes only to day 2. |
| Historical traffic visitor meaning (V1-R07B) | VERIFIED APPLIED | `ReportService::getTrafficReport()`; `admin/reports/index.blade.php`; `CairoDailyAnalyticsRollupTest` | Admin summary explicitly discloses "Sum of Daily Unique Visitors" when pruned; mixed-basis comparisons marked "Non-comparable". |
| Campaign downstream attribution (V1-R08) | VERIFIED APPLIED | `ReportService::getCampaignContentReport()`; `BookingAttributionPersistenceTest` | All eligible touch timestamps preserved per visitor; downstream bookings match within 30-day lookback of any cohort touch. |
| Partially pruned previous-period comparisons (V1-R07C) | VERIFIED APPLIED | `ReportService::getTrafficReport()`; `CairoDailyAnalyticsRollupTest` | Reconciled per-Cairo-day raw/rollup logic reused for previous period; covered by dedicated automated tests. |
| Cairo previous-period DST boundaries (V1-R07D) | VERIFIED APPLIED | `ReportService::getTrafficReport()`; `CairoDailyAnalyticsRollupTest` | Cairo calendar date diff used; previous period starts at exact Cairo midnight across spring and fall DST transitions. |
| Partial-day retention and report completeness (V1-R07E) | VERIFIED APPLIED | `AggregateDailyAnalyticsCommand::handle()`; `ReportService::resolvePeriodTrafficMetrics()`; `CairoDailyAnalyticsRollupTest` | Whole-Cairo-day retention verifies daily rollups exist before pruning; partially pruned days safely restore full-day rollups. |

## Verification Baseline

- Full PHPUnit suite independently rerun: **PASS — 356 tests, 2,161 assertions, 0 failures, 0 errors** using Herd PHP 8.4 on MariaDB.
- Frontend production build: **PASS — Vite 8.3.0**.
- Migration status: **23 migrations Ran**.
- Laravel Pint: **PASS — 0 issues**.
- All 9 remediation requirements verified with dedicated automated tests.
