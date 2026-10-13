# Production Typed Entitlement Mapping Proposal

Date: 2026-10-04. Evidence: direct Hostinger SELECT/SHOW inventory in an explicitly read-only, UTC, repeatable-read transaction. Production SHA: `d15967c88912f34db289ef3621bd9ebef1d56c85`. Target: `18ac92c27f96851bbf83d5df68831f76fc22dc76`.

**Planning only. No mapping is approved or applied. This document is not an executable mapping manifest.** No student names, addresses, contact identifiers, tokens, or staff notes are included.

## Classification result

| Classification | Purchases |
| --- | ---: |
| A. Deterministically mappable | 0 |
| B. Custom but clearly classifiable | 0 |
| C. Ambiguous; owner decision required | 6 |
| D. Intentionally legacy/unclassified | 0 approved |
| E. Inconsistent as primary package classification | 0 |
| Active purchases with positive, unexpired generic balances | 6 |

All six lack an explicit original offering key or entitlement type. Their ledger descriptions contain no explicit one-hour/two-hour terms or stable offering identifiers. Name, quantity, price, current presets and booking minutes support review, but do not establish the sold right. There are no package-linked bookings or consumption entries to provide additional provenance. All six are therefore C, including purchases whose commercial terms resemble current presets. Secondary exceptions are listed below without counting the same purchase twice.

Keeping these purchases unclassified preserves their history but makes their available **typed** units zero in the candidate. It would strand 28 currently remaining generic units. That is not an acceptable unattended cutover. No owner decision to retire any right has been supplied.

## Immutable purchase evidence

All amounts are USD. All six have `status=active`, zero recorded discount, and matching grant quantities. The dates are stored historical expiry dates; do not replace them with today's preset validity.

| Production purchase | Current name | Allocated / grant | Courtesy | Remaining | Original / final | Gross payments | Refunds | Net paid / due | Expiry | Created UTC |
| --- | --- | ---: | ---: | ---: | --- | ---: | ---: | --- | --- | --- |
| SP-1 | Diagnostic & Roadmap | 1 / 1 | 0 | 1 | 25.00 / 25.00 | 25.00 | 0.00 | 25.00 / 0.00 | 2026-10-15 | 2026-10-01 15:50:31 |
| SP-2 | Foundation Coaching Track | 8 / 8 | 0 | 8 | 280.00 / 280.00 | 505.00 | 225.00 | 280.00 / 0.00 | 2026-12-15 | 2026-10-01 15:51:18 |
| SP-3 | Diagnostic & Roadmap | 1 / 1 | 0 | 1 | 25.00 / 25.00 | 25.00 | 0.00 | 25.00 / 0.00 | 2026-10-15 | 2026-10-01 16:23:12 |
| SP-4 | Foundation Coaching Track | 8 / 8 | 0 | 8 | 280.00 / 280.00 | 255.00 | 0.00 | 255.00 / 25.00 | NULL; no recorded expiry | 2026-10-01 16:24:34 |
| SP-5 | Advanced Conversational | 1 / 1 | 0 | 1 | 28.00 / 28.00 | 250.00 | 222.00 | 28.00 / 0.00 | 2026-10-31 | 2026-10-01 16:25:16 |
| SP-6 | Foundation Coaching Track | 8 / 8 | +1 | 9 | 280.00 / 280.00 | 510.00 | 230.00 | 280.00 / 0.00 | 2026-12-15 | 2026-10-01 19:16:06 |

Ledger IDs 1–6 are the respective package grants. Ledger ID 7 is the +1 courtesy adjustment on SP-6. No units have been consumed, restored or forfeited. Refunds are cash events, not evidence that credits were forfeited. Preserve the separate payment/refund rows and the USD 25.00 outstanding on SP-4; do not net historical events into replacement rows.

## Proposed groups, subject to documentary owner approval

| Group / purchases | Candidate offering key | Candidate type | Confidence | Owner approval | Usable remaining units | Upcoming booking depends on these credits? | Scheduling consequence |
| --- | --- | --- | --- | --- | ---: | --- | --- |
| Diagnostic: SP-1, SP-3 | `diagnostic_roadmap`, only if confirmed | `one_hour`, only if confirmed | AMBIGUOUS | Required for each purchase or an explicit grouped approval covering both IDs | 2 | No recorded dependency | Would enable one-hour typed lessons; leaving unclassified disables typed use |
| Dated Foundation: SP-2, SP-6 | `foundation_track`, only if confirmed | `two_hour`, only if confirmed | AMBIGUOUS | Required; SP-6 courtesy must have the same approved type if using the current mapper | 17 | No recorded dependency | Requires an active two-hour package-funded SessionType, currently absent |
| Unlimited/undated Foundation: SP-4 | `foundation_track` or `custom`, only if confirmed | Owner-confirmed type | AMBIGUOUS | Required, including explicit treatment of NULL expiry and unpaid USD 25.00 | 8 | No recorded dependency | Typed use requires a matching lesson; do not silently introduce a 75-day expiry |
| Advanced Conversational: SP-5 | `advanced_conversational`, only if confirmed | `one_hour`, only if confirmed | AMBIGUOUS | Required | 1 | No recorded dependency | Requires an active matching one-hour advanced lesson, currently absent |

The candidate presets specify diagnostic 1×60 minutes, Foundation 8×120, Fluency 12×120, maintenance 1×120, and advanced 1×60. These are **future offering definitions**, not retrospective proof. No Fluency or maintenance purchase exists in the inventory.

### Decisions for each purchase

1. SP-1 and SP-3: confirm the purchased one-hour diagnostic entitlement, referencing original agreement or an explicit owner attestation tied to the production IDs. Their USD 25.00 price and names alone do not satisfy that requirement.
2. SP-2: confirm whether all eight units mean two-hour Foundation sessions and preserve the recorded 2026-12-15 expiry. The USD 505.00 payment and USD 225.00 refund reconcile to USD 280.00; they do not imply a second allocation.
3. SP-4: confirm the type and whether NULL expiry intentionally means unlimited validity. The mapper requires `validity_days` explicitly positive or NULL when mapping a preset with NULL expiry. A positive value is metadata, not permission to rewrite the historical expiry. Confirm the treatment of its USD 25.00 due separately from credit classification.
4. SP-5: confirm that the single unit is a one-hour advanced session. USD 250.00 paid and USD 222.00 refunded reconcile to USD 28.00, without changing the one remaining unit.
5. SP-6: confirm eight purchased units and the additional courtesy unit. The current mapper assigns **all** entries in one purchase to one type. If the courtesy has a different duration or the purchase contains mixed rights, do not use the homogeneous mapper; commission a separately reviewed allocation/conversion design first. Preserve the nine-unit balance and 2026-12-15 expiry.

If the owner confirms non-preset homogeneous rights, use `custom` with the explicit approved type and unchanged terms. If evidence remains insufficient, keep history unclassified and block cutover until an approved rights-preservation solution exists. No retirement, conversion ratio, new expiry, new charge or new debit is proposed by this report.

## Upcoming booking evidence

| Booking | UTC schedule | Recorded minutes | Status | SessionType | Original package debit |
| --- | --- | ---: | --- | --- | --- |
| B-6 | 2026-10-06 06:00–07:00 | 60 | confirmed | 2; diagnostic | None |
| B-5 | 2026-10-09 12:30–14:30 | 120 | confirmed | 2; diagnostic | None |

There are two actionable upcoming bookings. Both have zero ledger links; neither demonstrably depends on an ambiguous purchase. Do not invent a link based on shared student identity, creation time, name or duration. Preserve existing bookings as legacy history unless original funding provenance is separately established. Cancellation must not mint a package restoration without an original debit. Rescheduling must preserve the legacy treatment and agreed historical duration or stop for explicit review.

B-5 exposes a real product/rule conflict: configured diagnostic 60 minutes versus Friday availability override 120 minutes. B-4 is a completed historical diagnostic with the same 120-minute duration. B-2 is marked completed although its stored start is 2026-10-05, after this inspection. These are separate booking/configuration exceptions; none establishes a purchase's entitlement.

## Future manifest process; not executed

After maintenance and expansion, run the candidate's `entitlements:review` inventory command, capture its fresh per-purchase fingerprint privately, and attach owner-approved evidence. Current pre-expansion table digests are not interchangeable with the command's post-expansion fingerprint.

Each individual JSON manifest requires `package_id`, `fingerprint`, `offering_key`, `entitlement_code`, `reviewer`, and `evidence`. For SP-4 preset mapping, also include an explicitly reviewed `validity_days` value, including NULL if agreed. Do not use this Markdown proposal as a manifest, leave placeholders in an executable file, or pass an array of purchases to the single-purchase command.

Preview one approved manifest without `--apply`; inspect its balance and row count. Only a subsequent authorized deployment may run the same manifest with `--apply`. Mapping locks facts and rejects changed fingerprints, wrong ownership, inconsistent grants/restorations or conflicting prior reviews. Repeat execution of the identical approved manifest is idempotent. After every purchase, verify historical grant/courtesy quantities, money, dates, owner, event timestamps and idempotency keys are unchanged, with only approved identity/provenance added.

Acceptance requires 28 total remaining units represented without guessing, an approved matching active lesson for each usable type, unchanged expiry and money, and no unintended entitlement attached to an existing direct/legacy booking. No mapping was applied during this preflight.
