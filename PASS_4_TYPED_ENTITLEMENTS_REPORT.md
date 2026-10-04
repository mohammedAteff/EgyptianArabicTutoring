# Pass 4 — package offering identity and typed session entitlements

Implemented locally on 2026-10-04. HEAD remains `d15967c88912f34db289ef3621bd9ebef1d56c85` on `main`; changes are uncommitted. No push, deployment, production migration, Hostinger operation, real payment, or outbound Telegram message occurred. Earlier instructions to publish are superseded for this pass by its explicit implement/verify/report/stop scope.

## Baseline and preserved work

The previous model treated signed session ledger units as a generic package balance. Package names were purchase snapshots but lacked stable offering identity; a positive balance could not distinguish incompatible purchased rights. Booking consumption and cancellation already used an append-only ledger and transactional services. This pass extends those services rather than introducing a second booking engine.

The required prior-pass reports, booking/ledger/cancellation/reschedule domains, current offerings, SessionTypes, credit consumers, relevant migrations and tests were reviewed before editing. Installed versions were checked: PHP 8.4.25, Laravel 13.32, Livewire 4.4, PHPUnit 12.5, Pint 1.32, Larastan 3.12, Tailwind 4, Vite 8.3 and PhpSpreadsheet 5.9. Laravel, testing, UI and browser skills were applied. Boost tools were unavailable; repository source, Artisan and installed packages supplied the equivalent inspection. `.ai/rules` does not exist in this checkout.

Passes 1–3 remain in the existing working tree, including their lesson workspace, export/privacy and presentation changes. All **945 original inventoried files remain present**. `HOSTINGER_OPERATIONS_HANDOFF.md` is unchanged (SHA256 `C551CD5E06E671DD31CA7DBA127253F55443CBB26471CAA5B8B8677D76D94286`). No dependencies were changed. A private baseline dump, working-tree patch, file inventory and static-analysis baseline are outside the repository under `C:/Users/e/AppData/Local/Temp/arabic-pass4-baseline/`.

## Final domain model

### Stable purchase identity

New purchases retain a stable `offering_key`, explicit `identity_state`, expiry terms and purchase fingerprint. Names, quantities, prices, discount, currency and other purchase snapshots remain on the purchase; today's display name does not rewrite purchased terms. Canonical terms are selected on the server. Idempotent purchase replay must match the immutable payload.

| Stable key | Current offering | Entitlement | Grant | Validity days |
|---|---|---|---:|---:|
| `diagnostic_roadmap` | Diagnostic & Roadmap | `one_hour` | 1 | 14 |
| `foundation_track` | Foundation Coaching Track | `two_hour` | 8 | 75 |
| `fluency_track` | Fluency Immersion Track | `two_hour` | 12 | 100 |
| `payg_maintenance` | Pay-As-You-Go Maintenance | `two_hour` | 1 | 30 |
| `advanced_conversational` | Advanced Conversational | `one_hour` | 1 | 30 |

New custom packages require an explicit type and keep `custom` identity. Old unmatched purchases retain `legacy_unclassified`. There is no name/price/duration heuristic and no automatic conversion in either direction.

### Types, allocations and ledger

`EntitlementType` provides stable `one_hour` and `two_hour` codes, display labels, nominal minutes and active state. Minutes explain the type; they do not decide compatibility. `StudentPackageEntitlement` is a one-to-many purchase allocation with package owner, type and granted quantity. Package expiry remains shared across its allocations, with no duplicated money. Useful factories and seeders were added.

Every new signed mutation identifies its purchase, allocation and type alongside existing booking, reason, actor and idempotency facts. Grants, consumption, courtesy adjustments, cancellation restoration and refund/expiry forfeiture remain ledger events. Allocation balances derive from ledger sums. The read model separates allocated, courtesy, consumed, restored, forfeited, remaining and currently available units by type. Historical unclassified units are visible, review required and unavailable to the typed spend path.

Inactive, suspended, expired and unclassified purchases cannot fund new bookings. A canonical purchase awaiting settlement cannot become unlimited merely because its expiry is null; explicit unlimited custom terms remain supported.

### Explicit requirements and immutable booking provenance

Each SessionType has an explicit funding mode (`package`, `direct`, `free` or transitional `legacy`). Package funding requires a type and positive units. The verified diagnostic slug/action explicitly requires one `one_hour` unit; other legacy configuration requires review. Admin configuration provides type/unit controls. Direct public/manual booking is explicit and does not pretend to debit a package.

Package-funded bookings retain the type ID/code, required units, source purchase, allocation, original consumed entry and request fingerprint. Later configuration changes cannot reinterpret those snapshots. Typed provenance becomes immutable after consumption.

### One selection path

`EntitlementService` owns requirements, eligibility, per-type projections and compatible selection. Student portal, Cashier, Student Records, reports and Telegram use shared services/read models. Existing student/package/calendar locks, holds, AvailabilityService and transaction boundaries remain in place. Selection locks the student/purchases/allocations/ledger and chooses the earliest-expiring eligible compatible allocation, with deterministic creation/ID tie breaks.

One allocation must fund the entire required quantity. Separate small allocations are not pooled. UI eligibility uses `canFund` so two one-unit packages do not expose slots for a lesson requiring two units from one allocation. Different types are never substituted. Repeat submissions must match type and payload; an idempotency key cannot be reused to consume a different right.

### Lifecycle, adjustments and money

- Cancellation restores exactly the original purchase, allocation, type and quantity once. Legacy unclassified debits remain reversible. No new source package is chosen.
- Rescheduling keeps the original debit. A changed SessionType is allowed only when its required type and units match that immutable debit; incompatible changes fail before mutation. Student rescheduling cannot switch type. No second debit is created.
- Completion/no-show preserves existing lifecycle without another debit. Teaching/practice hours still use actual completed UTC start/end duration; a 75-minute completed booking contributes 1.25 hours regardless of entitlement type.
- Courtesy adjustments require a specific owned allocation, signed quantity and reason. Cross-student/package injection is rejected, and negative adjustments are bounded.
- Money and entitlement forfeiture remain separate. Monetary refund limits/history are preserved; optional forfeiture targets an explicit allocation and cannot exceed its available units. Replay cannot remove rights twice.
- Merge locks/cascades preserve allocation ownership and booking/debit provenance. Privacy erasure retains typed financial and ledger facts while applying existing personal-data policy.

## Schema and integrity

Two additive migrations are implemented and applied to local and test databases:

1. `2026_10_04_042111_expand_typed_session_entitlements.php`: creates `entitlement_types`, `student_package_entitlements`, `entitlement_mapping_reviews`; adds purchase identity/expiry/fingerprint, SessionType requirement/funding, nullable ledger type/allocation and Booking snapshot fields.
2. `2026_10_04_044431_constrain_typed_session_provenance.php`: adds full booking/debit composite provenance and MariaDB integrity guards.

Composite constraints tie allocation to package/type and student owner, ledger to allocation/owner, and booking to its exact debit and allocation. Unique package/type allocations and existing idempotency uniqueness remain. An allocation/entry index supports balance queries; offering keys support report filtering. Transitional fields remain nullable for unresolved history.

MariaDB rejects CHECK expressions on cascade-owned columns. BEFORE INSERT/UPDATE triggers therefore enforce complete ledger/booking provenance and valid signed typed event quantities while preserving ownership merge cascades. SessionType funding/requirement consistency uses a CHECK. Core local tables use InnoDB. Constraint tests reject forged allocation, owner and debit links.

No generic fields were dropped and no historical financial rows were rewritten. After typed allocations exist, destructive rollback of the expansion is rejected; recovery must be reviewed and forward-safe.

## UI, reporting and notifications

- Cashier filters by canonical offering identity across **all purchases**, with Custom / Unclassified included. Purchase IDs remain provenance, rather than offering choices. Display-name changes cannot change filter membership. Legacy direct `package_id` inputs remain compatible.
- Screen, CSV and XLSX share the same offering filter. Offering Key, Purchase ID, Entitlement Type and Allocation ID append to the original transaction columns; monetary values and existing export protections remain.
- Student Records and the selected-package panel show per-type availability, immutable purchase terms, expiry, typed history and review/settlement/expiry state. Available-credit filters and exported balances use the same eligibility/read model.
- Cashier purchase/courtesy/refund controls require explicit types/allocations. Reconciliation includes typed grants, nonnegative balances, debit snapshots and exact restoration links.
- Student portal and booking screens show separate one-hour/two-hour rights, purchase provenance and expiry. Incompatible requirements display no bookable slots. Legacy history remains visible as review required.
- Admin booking details show type, units, source purchase/allocation/debit; SessionType configuration makes funding explicit. Existing lesson workspace/materials are preserved.
- Telegram low-balance and expiry data/messages identify type and purchase/offering. Tests use mocked responses and block stray requests.

Concurrency verification also exposed a Telegram sender race: a worker failing the bot lock could postpone the winning worker's delivery by updating `due_at`. The losing worker now schedules its retry without changing the winning worker's due timestamp. A deterministic regression and the real concurrency suite verify one send.

## Historical inventory and reconciliation

Detailed review is in `TYPED_ENTITLEMENT_MAPPING_REVIEW.md`. Before expansion, local data contained three purchases, nine ledger events and seven bookings. Five bookings had no debit; the other two were cancelled with consumption/restoration. All seven actual durations were 60 minutes. No verified two-hour SessionType existed at baseline.

| Historical purchase | Original allocation | Signed ledger balance | Final classification |
|---|---:|---:|---|
| Foundation Coaching Track, purchase 1 | 8 | 9 | Legacy / unclassified |
| Feature QA Package, purchase 2 | 4 | 6 | Legacy / unclassified |
| Pass 1 Synthetic Package, purchase 3 | 2 | 2 | Legacy / unclassified |

**0 historical purchases mapped; 3 remain unclassified; 0 review records.** Original grants +14, courtesy +3, consumption -2, restoration +2, forfeiture 0 reconcile to **17 unchanged historical units**. Original three payments remain **USD 405**, original three refunds **USD 70**. Purchase terms, timestamps, quantities, keys, ownership and booking relationships are preserved.

The review command inventories facts/fingerprints without printing student identities and hashes private ledger descriptions. Explicit JSON manifests require reviewer/evidence, current fingerprint, offering and type. Preview is nonmutating; apply stores the reviewed original snapshot. Changed facts, ownership disagreement, unreconciled grants, negative totals and orphan/mismatched restoration are rejected. Canonical null-expiry history requires reviewed validity terms. Exact replay succeeds once; a different review cannot reclassify an approved purchase. This workflow does not split mixed historical events or infer classification.

Browser QA created five new canonical purchases plus one new custom purchase, six allocations, **27 typed rights**: one-hour 5 / two-hour 22. New canonical `mapped` states are not historical backfills. Grants +25 and courtesy +2 minus consumption 3 plus restoration 3 reconcile to 27. The mixed student's balances are one-hour 3 / two-hour 22; a second student has only two one-hour rights. Historical units remain separate. Final browser reconciliation reported **All Accounts Balanced, 0 issues**. Balanced totals do not resolve classification ambiguity.

## Verification

All final checks are complete, including the full rerun after the multi-unit eligibility regression: **808 current-schema tests plus 15 dedicated concurrency tests, 823 PHP tests and 7,484 assertions in total**. The eight JavaScript tests are additional. No database test suites ran concurrently against the test database.

| Check | Result |
|---|---|
| Full current-schema PHPUnit suite | **808 passed, 7,411 assertions** |
| Dedicated MariaDB concurrency configuration | **15 passed, 73 assertions**, MariaDB 10.11.18 / InnoDB |
| Last UI eligibility regression | **1 passed, 5 assertions** |
| JavaScript telemetry tests | **8 passed** |
| Vite production build | Passed |
| Blade view cache | Passed |
| Pint dirty / agent format | Passed |
| Diff whitespace check | Passed; Git emitted existing CRLF-to-LF notices only |
| PHPStan comparison | **259 baseline errors → 257; 0 new, 2 removed** |

Current-schema suite command:

```text
php vendor/bin/phpunit --filter '^(?!.*(?:MariaDbConcurrencyVerificationTest|MigrationACompatibilityTest)).*$'
```

The dedicated race class is run separately under `phpunit.concurrency.xml`; `MigrationACompatibilityTest` intentionally targets an intermediate schema and is not a current-schema test. No assertion counts are hidden behind a claim that the intermediate suite ran against current schema.

```text
php vendor/bin/phpunit -c phpunit.concurrency.xml tests/Feature/MariaDbConcurrencyVerificationTest.php
node --test tests/telemetry.test.mjs
npm run build
php artisan view:cache --no-interaction
php vendor/bin/pint --dirty --format agent
git diff --check
php vendor/bin/phpstan analyse --no-progress --error-format=json -v
```

On Windows these PHP commands were invoked through the absolute PHP 8.4 executable to preserve the test-filter regular expression. Full verbose PHPStan JSON was compared by path, identifier and message, allowing line moves and retaining duplicate error counts. Its remaining 257 errors are pre-existing debt, not a clean static-analysis pass. No suppressions or dependency changes were introduced.

Coverage includes both types, incompatible credit/no conversion, FIFO/expiry/status, no cross-allocation pooling, idempotency/type mismatch, exact and legacy cancellation, compatible/incompatible reschedules, typed signed courtesy, owned-allocation validation, bounded refund forfeiture, portal/type/expiry presentation, offering screen/CSV/XLSX parity, Telegram labels, reviewed mapping/replay/preservation/rejection, actual UTC teaching hours, merge/privacy and database provenance constraints.

Real concurrent workers competed for the last one-hour and last two-hour right. Each race yielded one success and one expected policy failure (process outcomes 0 and 2), one booking/debit and zero remaining units. Existing booking, hold, cancellation and Telegram races also passed. Worker Telegram calls use fakes, with stray requests prevented.

## Herd browser QA

Actual local admin/student forms were exercised with synthetic fixtures at desktop 1440×1000 and mobile 390×844:

1. Created each canonical offering, verified stable keys/terms and recorded clearly marked synthetic manual settlements; no payment processor was called.
2. Added courtesy rights for each type; confirmed Cashier Foundation filter and Student Records purchase/history/expiry presentation.
3. Created a one-hour booking from purchase 4/allocation 1 and a two-hour booking from earliest-expiring purchase 7/allocation 4. Provenance matched the compatible FIFO source.
4. Rescheduled the two-hour booking with unchanged debit. Rejected an admin one-hour → two-hour type change with a visible incompatibility error and no second debit.
5. Cancelled all three QA bookings, restoring each original allocation exactly. Final mixed balances returned to one-hour 3 / two-hour 22.
6. Signed in the one-hour-only student and confirmed two-hour booking has no compatible rights/slots; no conversion occurred.
7. Verified separate portal balances, review-required historical units, expiry and restored provenance. Mobile portal had no document overflow; the wide admin overview table retains intentional regional horizontal scrolling. Recent browser console warnings/errors were empty.
8. Ran read-only billing reconciliation, resulting in zero issues.

Screenshots are `PASS_4_ADMIN_DESKTOP.png`, `PASS_4_ADMIN_MOBILE.png`, `PASS_4_STUDENT_DESKTOP.png`, `PASS_4_STUDENT_MOBILE.png`, and `PASS_4_INCOMPATIBLE_MOBILE.png` in the project root. Admin overview screenshots show typed table presentation; selected-package/history details were additionally verified in the browser DOM.

**Browser export limitation:** the native XLSX download wait timed out after 10 seconds, so no browser-saved workbook is certified. Native CSV saving was not attempted. Automated endpoint tests generated and parsed CSV/XLSX, confirming identical purchase/type/filter results; native file-save acceptance remains manual.

**Scheduling configuration observation:** existing weekly AvailabilityRule overrides generated a 60-minute slot for the nominal 120-minute QA SessionType. The booking correctly consumed its explicitly required `two_hour` right. Duration and entitlement are independent; the existing scheduling engine was preserved. Actual intended two-hour slot configuration needs review before production acceptance.

## Production deployment prerequisites

Safe production migration remains **blocked pending historical classification and production configuration review**. Local unresolved purchases are visible and reversible but cannot fund typed bookings. Production history was not inventoried or mutated in this pass.

The intended sequence is **expand → reviewed backfill → reconcile → application cutover → constraints**. Stage the two migration phases separately: a routine deploy running all migrations and the new application together is not an approved cutover procedure.

Before any separately authorized production work:

1. Inventory production purchases, ledger events, booking provenance and existing configuration independently. Resolve each ambiguous/custom purchase with evidence; do not guess by name, money or minutes. Mixed historical purchases need a separately reviewed allocation/event mapping.
2. Verify backups and a forward recovery plan. Preserve original money, ledger quantities/timestamps/keys, ownership and booking/material relationships during reviewed classification.
3. Reconcile old monetary/signed totals and new per-type grants/balances/debit/restoration links before exposing typed spending. All existing usable rights must have an approved disposition.
4. Configure every package-funded SessionType's type/units and intended AvailabilityRule duration. Review canonical pending settlement/unlimited expiry terms.
5. Stage and verify on the exact production MariaDB version, InnoDB, ownership cascades and trigger privileges. DDL can partially apply; inspect failed constraints/triggers before retrying. The guard migration only conditionally recreates indexes/FKs, not already-created triggers/checks.
6. Cut over only after review/reconciliation/configuration acceptance, then validate constraints and smoke-test both types, cancellations and exports. Use forward recovery after typed writes; do not promise destructive rollback.

No production access, scheduling/queue changes, push or deployment was performed. Existing Hostinger operational follow-up remains outside this pass.
