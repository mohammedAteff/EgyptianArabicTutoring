# Typed entitlement mapping review

Read-only baseline recorded before the expansion migration, 2026-10-04. No student identifiers are included.

## Local inventory

| Purchase ID | Purchased name | Allocated | Original / discount / final USD | Expiry | State | Ledger balance |
|---|---|---:|---|---|---|---:|
| 1 | Foundation Coaching Track | 8 | 280 / 25 / 255 | 2027-01-15 | active | 9 |
| 2 | Feature QA Package | 4 | 100 / 0 / 100 | 2027-01-15 | active | 6 |
| 3 | Pass 1 Synthetic Package | 2 | 40 / 0 / 40 | 2027-04-01 | active | 2 |

Three purchases, nine ledger entries, seven bookings. Grants +14, courtesy +3, consumption -2, restoration +2, forfeiture 0: net 17 historical units. Five bookings have no debit; the other two cancelled bookings have debits. All seven bookings have actual duration 60 minutes. SessionType 1 is inactive, SessionType 2 is the configured diagnostic, SessionType 3 is a synthetic lesson. There is no configured two-hour SessionType at baseline. The original three payments total USD 405; the original three refunds total USD 70.

Zero historical purchase mappings are approved; all three remain unclassified. Purchase 1 resembles the foundation preset, but its historical diagnostic debits conflict with treating the purchase as two-hour rights. Name, price, allocated quantity, and duration are evidence only. Purchases 2 and 3 are custom synthetic history, also requiring explicit review. There are no deterministic historical purchase mappings established by the available evidence.

The verified diagnostic configuration uses the existing diagnostic-session slug/action and explicitly requires one_hour, one unit. Other legacy SessionTypes require administrator configuration. The five current preset keys are diagnostic_roadmap and advanced_conversational (one_hour), and foundation_track, fluency_track, payg_maintenance (two_hour). No conversions are implemented.

## Review workflow

Run `php artisan entitlements:review` to obtain machine-readable package facts and fingerprints without student identifiers. The inventory hashes ledger descriptions instead of printing private notes. A reviewer supplies a JSON manifest with `package_id`, `fingerprint`, `offering_key`, `entitlement_code`, `reviewer`, and `evidence`. First preview with `php artisan entitlements:review path.json`; apply only a reviewed manifest using `--apply`. The command never guesses. Exact replay is recorded once. Changes to quantities, money, timestamps, ownership, or booking facts invalidate an unapplied manifest.

Grants must reconcile to the purchased allocation, net units must be nonnegative, ownership must agree, and each historical restoration must match its original debit. Mapping updates classification/provenance only and stores the original snapshot and review evidence. It preserves monetary records, ledger quantities, event timestamps, booking relationships, ownership, and idempotency keys. Mapping a canonical purchase with no expiry requires an explicit `validity_days` review: a positive integer means pending settlement terms; null means reviewed unlimited terms. It does not silently interpret an unset expiry as unlimited canonical availability. Mixed historical rights require a separately reviewed migration; this workflow does not split historical events or convert types.

## Final local reconciliation, 2026-10-04

- Historical mappings applied: **0**. Historical purchases still `legacy_unclassified`: **3**. Review records: **0**.
- Original historical grants +14, courtesy +3, consumption -2, restoration +2, forfeiture 0 remain unchanged: **17 units**, balances **9 / 6 / 2**.
- Original purchase terms, payment/refund amounts, ledger quantities/timestamps/keys and booking links are preserved. Financial and typed reconciliation reports **0 issues**.
- Browser QA added five canonical purchases and one explicitly typed custom purchase, with six allocations. These are new local synthetic records, not historical backfills. New purchase identity states are `mapped` (5 canonical purchases) and `custom` (1 purchase).
- New typed grants +25, courtesy +2, consumption -3, restoration +3: **27 rights**, comprising **5 one-hour** and **22 two-hour** rights. Three QA bookings were cancelled and restored to their original allocations. No QA booking remains active.
- The mixed student has **3 one-hour / 22 two-hour** rights. A separate student has **2 one-hour** rights and cannot book a two-hour lesson. Historical units remain visible as review required and are excluded from typed spending.

Reconciliation confirms preserved totals; it does not approve ambiguous classification or establish that production history is ready for cutover.

## Production prerequisites

Production has not been accessed during Pass 4. Inventory and review production history independently; do not deploy while unclassified credits would become unavailable. The three unresolved local purchases demonstrate that this is a real prerequisite, not a theoretical warning.

Stage expansion separately from application cutover, apply approved mapping manifests, reconcile money and signed units, configure every package-funded SessionType explicitly, then validate constraints on the exact production database. The integrity migration uses composite foreign keys and MariaDB triggers, requiring trigger privileges. MariaDB DDL can partially apply; inspect any failed migration before retrying, particularly already-created triggers/check constraints.

Review existing AvailabilityRule duration overrides: local weekly rules generated a 60-minute slot for the nominal 120-minute QA SessionType. Entitlement selection correctly remained `two_hour`; duration is a separate scheduling setting. Configure actual intended two-hour slots before production acceptance.

Prepare verified backups and a forward recovery plan before cutover. Typed mutations prevent destructive rollback. The private local pre-migration dump is at C:/Users/e/AppData/Local/Temp/arabic-pass4-baseline/local-before.sql; it must not be committed. No push, deployment, Hostinger operation, real payment, or Telegram message occurred during this pass.
