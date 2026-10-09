# Financial timing correction plan — approved for local implementation

Prepared: 8 October 2026. Status: **APPROVED BY OWNER; IMPLEMENTED AND VERIFIED LOCALLY**.

The owner’s final reply was “Approve this financial plan.”

Implementation branch: `codex/public-copy-blog-v3`, local only, with no upstream or remote branch. This approval authorizes implementation and tests locally. It does not authorize deployment, a push, production migrations, production data changes or a release.

## 1. Diagnostic credit: 48 hours from completion

The current implementation requires a paid, completed diagnostic and checks a 48-hour window from payment settlement. Change only the window's anchor to the diagnostic's existing `bookings.completed_at` timestamp. Keep the existing paid-diagnostic, eligible-package and once-only credit checks.

- Eligibility begins at the recorded completion instant and ends exactly 48 hours later. At the deadline the window has expired. Future completion timestamps are invalid.
- Eligibility continues to be evaluated when an eligible package purchase is created. An already issued package keeps its recorded price and discount; a later payment does not revoke its awarded credit.
- Historical completed diagnostics may use an authentic completion audit/event timestamp when the completion field is missing. Do not infer completion from payment time or scheduled lesson end. If no reliable timestamp exists, leave automatic credit unavailable and present the case for staff review through the existing manual discount process.
- Do not recalculate or remove previously awarded discounts, reopen consumed credits, change completed bookings or alter ledger history.

No new booking column is needed. Tests will cover completion versus payment timing, the exact boundary, missing completion evidence, refunds, once-only use and preservation of issued discounts.

## 2. Package validity: actual full payment receipt

The existing payment record has `paid_at`, but staff entry currently always writes the entry time. Package expiry is currently a date interpreted through the end of that day. Add two nullable UTC timestamps to `student_packages`: `validity_started_at` and `expires_at`. Retain `expiration_date` for legacy records and compatibility.

- Add a payment receipt date/time input, displayed in the existing business timezone, defaulting to the current time. Store the actual receipt instant in `payments.paid_at`; reject future times. Existing payments are not rewritten. The existing reference, notes, audit identity and idempotency protections remain.
- For a package that has not yet started validity, determine the chronological instant at which received payments, less refunds, first fully cover its immutable final price. Partial payments do not start validity. Use receipt timestamps, not data-entry time or package creation time.
- Set the new start and expiry together under the existing locks/transaction. The 75/100 calendar-day term retains the same local wall-clock time in the established Cairo business timezone, then stores the result in UTC. This keeps the existing calendar-day convention across daylight-saving changes. Expiry is effective at the exact deadline.
- Once activated, validity is not reset by replaying a payment, entering another payment or issuing a refund. No automatic entitlement deletion or ledger forfeiture is introduced.
- Continue maintaining the compatibility expiry date for existing date-based displays, but make the timestamp authoritative wherever a new timestamp exists: booking eligibility, package selection, balances/status, operations/report filters and expiry reminders. Show the date/time and timezone where a date alone would be misleading.
- A staff date extension on a timestamp-based package can only move the deadline later. Interpret the selected extension date through its end in the business timezone. Preserve the current behavior for legacy date-based packages.

## 3. Existing records and student protection

The migration is additive and leaves all existing rows unchanged. There is no automatic expiry backfill, repricing, payment rewrite, entitlement remapping or ledger adjustment.

- A package with an existing expiry date keeps its current end-of-day entitlement. Null new timestamps mean the legacy rule remains authoritative. Do not convert it to an earlier exact deadline, even if payment history could establish one.
- Existing unlimited/custom packages retain their current treatment. Existing manually extended dates remain intact.
- An unpaid package whose validity has not started can use the corrected receipt-based timestamps when it subsequently reaches full payment. It has no previously active validity period to shorten.
- Paid records with missing or contradictory timing evidence require staff review; do not invent an earlier expiry or automatically remove access. For a record that currently has active rights, retain the current deadline until an explicit reviewed correction or extension.
- Existing student resource assignments, course/material access, typed allocations and historical bookings remain untouched.

The local discovery dataset contains nine packages: eight with expiry dates and one custom/unlimited package. These are local observations, not production counts. Production data has not been queried for this task.

## 4. Validation and rollout boundary

Before and after the local additive migration, compare existing package dates, price/discount terms, allocations and ledger balances. Run focused tests for the 48-hour boundary; partial/full and backdated receipt entry; chronological settlement; replay/refund behavior; exact expiry; Cairo daylight saving; legacy end-of-day access; extension protection; and existing booking/student access regressions.

Use the established local test database only. Update Policy 1.0, Pricing, Booking and FAQ wording consistently. The owner approved this plan before financial logic changes. All deployment and production execution are a separate task requiring explicit approval.

## Local implementation evidence

The approved timing corrections are implemented on the dedicated local branch. All 15 financial timing tests pass within the final scoped run of 241 tests/1,830 assertions. Coverage includes the exact 48-hour boundary, authentic missing-completion evidence, preserving an issued discount, partial/out-of-order receipts, refunds before full settlement, payment replay, exact expiry eligibility, Cairo clock changes, protected legacy dates/unlimited rights and already paid undated records. No existing entitlement was backfilled or shortened.

The local migration added nullable timestamps without a backfill. At final read-only verification the application still has 9 packages, zero populated new clocks, 27 ledger rows and ledger balance 44. The migration checkpoint’s protected date/price/discount/allocation hash matched before and after. No production action, push, merge or release is authorized or performed.

