# Production QA Dataset Report

**Status: PARTIAL — REQUIRED PREREQUISITES BLOCKED. Do not begin full acceptance as a fully ready dataset.** The created graph is intact; no cleanup, LMS work or deployment was performed.

## Release and specification

- Production Application SHA: `7cb9a236b60350cfa134c2d6c94e14df1eb20c6b`.
- Documentation HEAD SHA observed before creation and after origin refresh: `e47026645f38fb0b8a0b77e85b5110b6a3714fdd`. The delivery documentation commit is identified by Git and the final response; a document cannot contain the SHA of its own commit.
- Runtime/application diff at start: none. The four newer files were APPLICATION_CAPABILITY_MATRIX.md, PRE_LMS_PRODUCTION_DEPLOYMENT_REPORT.md, REMAINING_FEATURES_IMPLEMENTATION_TRACKER.md and STAGE_5_DEVELOPMENT_LAUNCH_DATA_TOOLS_REPORT.md. This task adds only its three requested Markdown reports.
- Matrix: 153 IDs, all 20 specification fields consumed. Governing SHA-256 `57ce8326521f95011d02eaf399ea50e9fec9e136f7eb59e352ea4155bb4a5dd7`.
- QA Run: **QA_ACCEPTANCE_2026_10_06_A**; human label **QA ACCEPTANCE**, distinguishing suffix **20261006 A**.
- Production verified healthy, tracked source clean, config cached, PHP 8.4.19 / Laravel 13.32.0 / MariaDB 11.8.9; 110 tables, 75 migrations, typed compiled guards retained. Maintenance and destructive flags off.

## Coverage result

| Measure | Count |
|---|---:|
| Total matrix capabilities | 153 |
| Capabilities needing synthetic data | 139 |
| Capabilities with planned QA scenarios | 139 |
| Capabilities covered by coherent starting dataset | 132 |
| Capabilities still uncovered | 7 |
| No synthetic data required | 14 |
| Planned scenarios | 15 |
| Scenarios with created rows / controlled activity | 12 |
| Additional reuse-only destructive graph scenario | 1 (Q14; archive prerequisites blocked) |
| Synthetic Students | 6 |
| Traceable database objects at integrity capture | 331 |
| Application file payloads | 4 |

No fixture mapping omission: all 139 synthetic IDs appear in both the plan and manifest. Blocked IDs are planned but not counted covered. Coverage is starting-state coverage, not test-case coverage; no claim that every recommended negative/temporal/configuration branch passed.

| Uncovered ID | Genuine missing prerequisite / scope constraint |
|---|---|
| SA-076 | No labeled maintenance visit. Production maintenance is off; no bounded window or isolated fixture is authorized/available. Existing 14 visits remain genuine. |
| SA-084 | No isolated/faked QA bot/destination security fixture. The existing verified owner configuration is retained; expanding integrations/recipients or enabling commands is prohibited. |
| SA-085 | Bookings/analytics and original rule definitions exist, but isolated fake-transport rule/quiet-window/cooldown/recipient fixtures are absent. Extra live rule sends are outside this task. |
| SA-095 | No compatible portable QA ZIP/variant fixtures. Canonical archive creation/inspection requires separately enabled guarded tools; no enable/export/inspection executed. |
| SA-096 | Live QA graph exists, but no compatible inspected archive/selected restore preview or approved isolated restore scope. Restore execution is expressly deferred. |
| SA-097 | QA owner/foreign actors exist, but no canonical completed/pending archive operation. Completion/history was not fabricated while the feature remains disabled. |
| SA-098 | No unclassified legacy purchase. New purchase workflows mandate explicit type; a narrowly reviewed fixture adapter awaits the earlier user clarification. No ledger fabrication or provenance stripping. |

The earlier legacy-fixture clarification remains unanswered. No time passage was treated as permission. Maintenance and Telegram isolation require separate scope; guarded archive completion cannot be manufactured while disabled. Related capabilities retain partial branch prerequisites: SA-083/STU-005/STU-009 lack the legacy branch, and archive/security/history dependencies remain conditional.

## Actual construction and minimum graph

Six personas keep concerns separate: A completed direct lesson/teaching; B finance/mixed typed rights; C expiry/incompatibility/no-pooling/diagnostic; D New York recurrence/holiday/waitlist; E empty identity; F small dependent identity graph. Four staff identities cover Super Admin, peer Super Admin, Admin and Assistant; only the QA Super Admin owns a newly enrolled factor. Genuine bolt@admin.com remains unchanged.

Ten typed purchases, ten authoritative allocations, six manual payments, three refunds, two forecast installments and one renewal support unpaid/partial/discounted/full-payment/full-refund/partial-refund/overpayment/multi-currency states. Ten bookings include five confirmed, three completed, one cancelled and one no-show; reschedule retained the original debit, cancellation added the exact restoration, and no-show retained consumption. Two recurring occurrences were manually generated via current availability. Extra completed diagnostic lesson was necessary because canonical FIFO first consumed C’s near-expiry grant; no provenance was patched. C has two separate positive one-unit allocations against an owned two-unit requirement to prepare no-pooling refusal.

Canonical booking/material/teaching/forms/staff/content controllers and services created the graph with constraints enabled. Student identity bootstrap uses a narrowly scoped adapter applying controller validation and actual identity/timezone normalization because there is no dedicated staff Student-create endpoint. Past expiry was supplied to the canonical purchase service as a labeled fixture, with no historical date/grant edit. Student POST teaching/feedback paths prepare data without portal visits. Local protected orchestration is outside application source/Git/web root. No direct SQL business insertion, fake provider transactions, fake booking debit, forced locks or disabled guards were used.

One published/draft Resource pair shares a QA category/private PDF; lesson Resource references use that same library authority. One passive media record is reused by a Page with separate pending draft, published Blog revision/old-slug redirect, two Game cards and three inactive Promotion variants. French published Page/German draft plus genuinely stale French Resource/outdated German draft support locale/fallback/reconciliation. FAQ, Reddit definition and promotions remain inactive. QA rooms/provider were active only inside the uncommitted construction transaction and then committed inactive, leaving booking snapshots available without exposing QA rooms to genuine bookings. Existing availability/defaults/intake Form/private Resource remained unchanged.

One follow-up Form has two published-version generations, six questions, a prior-version submitted response, a current-version draft and the genuine intake response generated by A’s public booking. Shared/private homework, plans/milestones, assignments, error logs, tags and notes, staff-only preparation, private/shared materials and feedback are owned by the actual Student/Booking IDs. Staff data include shared/personal notes, personal pins/favorite, tasks, alerts, saved views, recents and canonical unread admin inbox notices.

| Domain | Exact traceable rows |
|---|---:|
| Staff operations and status | 26 |
| Staff identity and audit | 90 |
| Analytics | 17 |
| CMS and files | 31 |
| Booking, scheduling and meetings | 45 |
| Resources and Contacts | 18 |
| Questionnaires | 24 |
| Teaching | 21 |
| Finance and entitlements | 53 |
| Student identity | 6 |

## Financial and entitlement reconciliation

| Purchase | Student | Currency | Net price | Gross paid | Refunds | Net paid | Due | Overpaid | Current entitlement state |
|---|---|---|---:|---:|---:|---:|---:|---:|---|
| 11 | 8 | USD | 100.00 | 30.00 | 0.00 | 30.00 | 70.00 | 0.00 | Active; one_hour: remaining 2, available 2 |
| 12 | 8 | USD | 80.00 | 90.00 | 5.00 | 85.00 | 0.00 | 5.00 | Active; two_hour: remaining 1, available 1 |
| 13 | 8 | USD | 20.00 | 20.00 | 20.00 | 0.00 | 20.00 | 0.00 | Active; one_hour: remaining 1, available 1 |
| 14 | 8 | EUR | 30.00 | 30.00 | 5.00 | 25.00 | 5.00 | 0.00 | Active; one_hour: remaining 2, available 2 |
| 15 | 9 | USD | 40.00 | 0.00 | 0.00 | 0.00 | 40.00 | 0.00 | Expired; two_hour: remaining 1, available 0 |
| 16 | 9 | USD | 25.00 | 0.00 | 0.00 | 0.00 | 25.00 | 0.00 | Active; one_hour: remaining 1, available 1 |
| 17 | 12 | USD | 10.00 | 0.00 | 0.00 | 0.00 | 10.00 | 0.00 | Unavailable; one_hour: remaining 0, available 0 |
| 18 | 8 | USD | 20.00 | 0.00 | 0.00 | 0.00 | 20.00 | 0.00 | Active; one_hour: remaining 1, available 1 |
| 19 | 9 | USD | 25.00 | 25.00 | 0.00 | 25.00 | 0.00 | 0.00 | Unavailable; one_hour: remaining 0, available 0 |
| 20 | 9 | USD | 0.00 | 0.00 | 0.00 | 0.00 | 0.00 | 0.00 | Active; one_hour: remaining 1, available 1 |

All low-level money/ownership/grant/debit/restoration checks are zero. Canonical BillingReconciliationService truthfully reports **DISCREPANCIES_DETECTED, 1**, solely its intentional “negative derived money due / overpaid” warning for purchase #12 (USD 5.00). This is the required overpayment fixture, not an unreconciled transaction; the report is not mislabeled BALANCED. All other financial categories and typed grant/ownership/provenance checks are zero. Three refunds are bounded by their actual payments; only the explicit USD 5.00 refund forfeits one exact two_hour allocation. Forecast installments are not cash records.

## Production preservation and integrity

- Every original business table row prefix (before-QA max IDs/count/full-row hash) is unchanged across 92 tables; genuine owner protected digest and credentials/MFA presence match baseline. No genuine Customer/Contact/Resource/staff content altered.
- All database foreign keys: zero orphans. Reconciliation: zero ledger/purchase/booking/payment owner mismatches, negative unit balances, grant quantity mismatches, excess refunds, missing consumption parents or unmatched restorations.
- Canonical instants and business/customer local date/time snapshots agree; zero timezone issues and zero missing policy snapshots. Policy remains immutable. Rescheduled/funded bookings have one original debit; cancellation restoration retains exact type/allocation.
- Teaching ownership checks and Resource relationships pass. Owned PDF bytes exist and match SHA-256; original genuine alphabet PDF remains byte-identical. One orphan-free database graph is distinct from one **identified unreferenced passive PNG payload** left by the rolled-back content phase: its path/hash/manual cleanup responsibility are in the manifest, and it was left intact as requested.
- All six Students have zero notification rows and no portal visit/sync. Future first-visit acceptance is preserved. C’s near-expiry date is 2026-10-07 and diagnostic eligibility uses the actual payment’s 48-hour window; re-evaluate these temporal states at acceptance time. Do not substitute a clock or edit provenance.
- Same-day resolver attempt was rejected by current schedule/grid before the notice branch. Default notice remains 12 hours; no same-day booking or rule weakening. Existing valid availability was used for every persisted booking.
- .env, cached config, dependency locks, built manifest, deployed wrappers and public artifact hashes are unchanged. No source/migration/schema/trigger/generated-column/dependency/runtime configuration drift. Maintenance/destructive flags remain off; jobs/failed jobs are zero.
- Settings drift is limited to existing automatic heartbeat/hold-housekeeping keys: last_holds_cleanup_at, last_scheduler_run_at, telegram.last_tick_at, telegram.last_watchdog_run_at. These were not configuration edits. Existing scheduler/queue/worker settings were neither configured nor executed by this task. Telegram bot last-success metadata advances only through authorized status delivery.

## Public activity and external effects

Real production UI gate submission created a synthetic Contact/request and displayed “Your File Is Ready”. Browser download completion waiting timed out; its canonical download row exists, but full browser-byte acceptance is deferred. A separate anonymous HTTP workflow independently validated the 733-byte PDF hash, real GET page views, request/download, campaign acquisition and genuine server country result. Six bounded client payloads are recorded as **simulated HTTP telemetry**, not observed human social clicks/dwell/game completion. One campaign visitor/session and 13 events are prepared; staff exclusion was respected without clearing owner auth. No forged raw IP, country header or historical timestamp.

Reserved example.com/example.edu mail domains correctly failed the null-MX prerequisite. The gate uses a clearly synthetic QA address on the application owner’s existing mohamedateff.com domain, with actual DNS validation. Immediate access sends no email/PIN; Log mail remains unchanged. No genuine Student email/destination, gateway, recording provider or unrelated webhook was contacted. No external social messaging was opened. Existing reminder rules are unchanged and no QA lesson was due during setup; ordinary future reminder eligibility must be reviewed before the intact dataset’s future slots.

## Backup and cleanup ownership

The protected native predeployment archive remains available, mode 0600, 23,228,252 bytes, SHA-256 `0454ac9211c980c175e5145b5d4d16b4310e2e512f11de1dda4366102e8b6ea3`. It is the prior-source recovery snapshot (18ac92c), not a fabricated current-release QA backup. Prior isolated recovery evidence is retained; no full restore was repeated. Existing managed backup manifest/hash checks pass. Existing `s3_replication_failed` warning remains unresolved/unchanged; no infrastructure work was authorized. No additional backup was required for this nondestructive creation stage.

Reset Students owns Student finance/typed/planning/teaching/form answers with private payload review; Financial reset overlaps only package-funded booking closure and retains direct lesson/identity content. Contacts, staff identities/factors/notes/preferences/views/inbox, Forms/definitions, CMS/Media/Game/Page/Blog/Promotion/locale objects need their documented manual/domain owners. Resources own shared library bytes and teaching references must be checked first. Analytics whole-scope reset would include genuine history and is not authorized; exact QA IDs and shared rollups require review. Protected recovery is excluded from ordinary managed-backup cleanup. No cleanup ran; complete exact references are in the manifest.

## Validation, Git and Telegram

No application code changed. Existing release tests/build/static evidence is reused; no full acceptance, production test runner, reset or build/deploy operation was performed. New data-specific integrity and documentation reconciliation checks pass subject to the explicit missing fixture prerequisites and expected overpayment warning. Setup failures rolled back transaction rows; auto-increment gaps were not reset. All business/history deltas reconcile to registered QA objects, with runtime housekeeping excluded.

Documentation delivery: three requested files only; unrelated pre-existing untracked deployment/proposal documents excluded. Commit/push and final runtime diff are recorded at delivery.

START notification: attempted once, successful through existing verified service, delivery #7/message #11 at 2026-10-06T17:27:22Z. Bot/destination/token/rules/polling/commands remain unchanged. FINAL will be attempted exactly once **after documentation push**, using the blocked format (7 uncovered); safe outcome goes to the protected delivery receipt and final response. No completed/ready message is justified.
