# Pass 1 — Admin UI Consistency & Student Billing Presentation

Completed locally on 2026-10-04 (Africa/Cairo). No commit, push, deployment, Hostinger connection or production business-data mutation occurred.

## Baseline and scope

- Branch: `main`.
- Baseline and final HEAD: `d15967c88912f34db289ef3621bd9ebef1d56c85`.
- Delivery: uncommitted working-tree changes; seven tracked files modified, three implementation/test files added, and this report added.
- The starting tree also contained the prior untracked `FEATURE_GAP_REUSE_AND_DEPENDENCY_PLAN.md` and `HOSTINGER_OPERATIONS_HANDOFF.md`. They were preserved. Three unrelated `Analysis output *.png` files appeared during the session and were left untouched.
- Read the supplied Pass 1 request, forensic reuse/dependency plan, feature expansion and remediation reports, relevant README/SYSTEM_REFERENCE material, AGENTS.md, and current source/tests. Current code remained authoritative. `.ai/rules` does not exist.
- Installed runtime checked: PHP 8.4.25, Laravel 13.32.0, Livewire 4.4.5, PHPUnit 12.5.35, Pint 1.32.1, Larastan 3.12.2, Tailwind 4 and Vite 8.3.0. No dependencies, migrations or schema were changed.
- Used the Laravel, Tailwind, testing and computer-use skills. Boost tools were unavailable in this session; CLI/source inspection, official package documentation and the local Herd browser were used as fallbacks.

## Files changed

| File | Change |
|---|---|
| `resources/views/components/report-filters.blade.php` | Shared Apply Filters / Clear Filters pair, secondary reset button, keyboard focus and fixed parent-scope preservation. |
| `resources/views/components/copy-button.blade.php` | Shared overlapping-rectangles SVG, centered inline alignment and minimum 44px target. |
| `resources/views/admin/students/show.blade.php` | Aligned copy labels, removed repeated per-package forms/history and duplicate summary fragments, reused selected-package partial, collapsed package creation after overview. |
| `resources/views/admin/students/package-management.blade.php` | Compact overview of independent purchases; one selected package with Overview, Payments, Credits, Expiration and History sections; Cashier deep link. |
| `app/Http/Controllers/Admin/StudentController.php` | Validates presentation parameters; supplies prepared financial data and meeting providers; preserves Assistant data restrictions. |
| `app/Domains/Students/Services/StudentPackagePresentation.php` | Read-only selected-package projection over current packages, payments, refunds, ledger entries and validity audit records. Delegates calculations to StudentLedgerService. |
| `app/Domains/Students/Models/StudentPackage.php` | Correct generic type declaration for the existing ledgerEntries relationship; no relationship behavior change. |
| `app/Domains/Students/Services/StudentLedgerService.php` | Optional resolved business-timezone argument for read-only summary/eligibility, avoiding repeated setting reads. Existing callers and calculations retain their defaults. |
| `app/Domains/Timezone/Services/TimezoneDisplayService.php` | Allows the already resolved timezone to be reused for administrator date/time formatting. |
| `tests/Feature/AdminUiBillingPresentationTest.php` | 26 focused cases covering reset scope, shared copy markup, selection, history isolation, existing mutation actions, ownership, replay rejection, roles and bounded query count. |
| `PASS_1_UI_AND_BILLING_CLEANUP_REPORT.md` | This requested report. |

## Shared filters and copy controls

The previous reset link said `Clear / all dates`, although it already cleared the screen's transient filters. It now says **Clear Filters** and looks like a secondary button beside **Apply Filters**. Cashier Hub, Student Records, Students & Contacts and Staff Notes all consume the same existing component. No domain-specific query builder was replaced.

Reset navigation returns to the existing route with no transient search, dates, statuses, package, owner, sort or pagination parameters. If a consumer supplies mandatory `fixed` scope, the component preserves it in the reset URL. The focused component test verifies that a parent scope survives while search/page state is removed. Existing route authorization and query validation remain in place. Reset uses normal GET links, so browser navigation/back semantics remain available.

The shared copy component now uses one application-native SVG depicting two overlapping rectangles. Student identity labels and their buttons use centered flex alignment and consistent spacing. Button type, aria labels, focus treatment, live status, individual-field/all-details/note hooks and current unsaved-value behavior remain intact.

`resources/js/clipboard.js` was not changed. SHA256: `77B450AB3D9C5F06F1980F0517AC206788E8C327E7F40B15F20A5A4FD85322E2`. Existing tests continue to cover unsaved field extraction and native/fallback clipboard paths.

## Selected-package architecture and reuse

The financial section begins with a compact table of every independent purchase: package name/ID/currency, authoritative creation date, original/final amount, gross paid/refunded, due/overpayment, currently available credits, effective expiry and presentation status. No purchases are combined. The former separate credit-summary table was removed; its detailed allocated/courtesy/consumed/restored values are available for the selected package in Credits.

One server-rendered management panel follows the table. The newest purchase is the default. A GET selector or a Manage link chooses another purchase. Validated `billing_tab` controls which of five sections is rendered; the URL supports bookmarking and browser navigation. No new JavaScript, Livewire component or frontend framework was introduced. On mobile, package and history selectors use a full row. Wide overview tables scroll inside a focusable, labelled region instead of widening the page.

Payments shows one existing payment form, with selected-package payments and expandable refund controls. Credits shows one signed adjustment form. Expiration shows the existing extension form and recorded audit history. History projects existing payment/refund/credit/audit records into expandable rows and supports All, Payments, Refunds, Credit changes and Expiry changes. It neither writes a history table nor duplicates financial records. Unselected packages' detailed histories/forms are not permanently rendered.

The focused presentation class loads packages and their existing relationships in batches, uses the canonical ledger summary/refundable amount methods, and reads validity audit history only for the selection. It is not a payment-writing service. The query regression test verifies six queries for both one and six packages. Resolved timezone reuse removes a per-package setting lookup and duplicate formatting lookups; it does not change expiry boundaries or stored values.

### Endpoints retained

| Purpose | Existing route / parameter |
|---|---|
| Payment | `admin.students.payments.store` — current student and selected package |
| Refund | `admin.students.refunds.store` — current student and selected payment |
| Signed credit adjustment | `admin.students.credits.adjust` — current student and selected package |
| Validity extension | `admin.students.packages.validity` — current student and selected package |
| Package creation | `admin.students.packages.store` — existing collapsed form |
| Full Cashier | `admin.billing.cashier` with supported `student_id` |

Existing field names, CSRF tokens, idempotency keys, previous-expiry value, validation, reason/audit rules and backend ownership checks were reused. The mutation handlers, reconciliation, locking, financial calculations and ledger writers were not rewritten. Refunds still do not restore credits. Currency, discounts, net paid, due, overpayment, courtesy rules and expiry semantics remain unchanged.

## Authorization

Existing Super Admin/Admin finance access remains the boundary. Assistant student views receive no packages, financial records, copy controls, financial forms or Full Cashier link. Forging financial presentation parameters does not expose those records. The existing Cashier authorization still returns 403 for Assistant.

Focused endpoint tests verify that the rendered payment/refund/credit/expiry actions target only the selected purchase, reject foreign parent ownership, retain replay protection and reject Assistant mutations. Existing privacy/financial/concurrency tests also passed in the full current-schema run. No permission or middleware was broadened.

## Verification

| Check | Result |
|---|---|
| Focused PHPUnit: `php artisan test --compact tests/Feature/AdminUiBillingPresentationTest.php` | **26 passed, 229 assertions**; rerun after the final mobile selector adjustment. |
| Current-schema suite excluding the separately executed concurrency class: `php artisan test --compact --exclude-group=intermediate-schema --exclude-filter=MariaDbConcurrencyVerificationTest` | **698 passed, 6,644 assertions**. |
| `php artisan test --compact tests/Feature/MariaDbConcurrencyVerificationTest.php` | **13 passed, 57 assertions**; executed separately and sequentially. |
| Combined current-schema result | **711 passed, 6,701 assertions**. The focused cases are included in the 698; not added twice. |
| `node --test tests/clipboard.test.mjs tests/telemetry.test.mjs tests/form-builder.test.mjs` | **8 passed**. |
| `npm run build` | Passed, Vite 8.3.0; rerun after the mobile adjustment. |
| `php artisan view:cache --no-interaction` | Passed after final changes. |
| `php vendor/bin/pint --dirty --format agent` | Passed. |
| `git diff --check` | Passed; Git's existing CRLF-to-LF notice for the student view is non-failing. |
| PHPStan, unchanged level/config, `--memory-limit=1G --error-format=agent -v` | **261 before → 261 after; zero new/removed diagnostic entries**. |

The historical `intermediate-schema` group was excluded as requested for the full current-schema suite. It contains three historical compatibility tests and is documented in the prior feature report as 1 pass / 2 failures against both its baseline and the current schema. It was not changed or rerun in this pass.

PHPStan comparison used full verbose baseline/current results normalized by path, identifier and message, preserving duplicate entries while allowing line movement. For the complete baseline run, the four changed tracked application PHP files were temporarily restored to HEAD and the new presentation class temporarily omitted; all exact working source bytes were restored in `finally`. A preliminary attempt hit PHP's default 128MB limit and was discarded. Both accepted comparison runs used 1GB and produced all 261 details. No suppressions, baseline entries or lower rule level were added. The repository still has 261 pre-existing diagnostics, including the existing collection-sum finding in StudentLedgerService and DateTimeInterface check in TimezoneDisplayService; it is not globally PHPStan-clean.

## Local browser QA

Used the existing Herd app at `http://ArabicwAbdallah.test`, verified through Herd site discovery. Desktop was checked at normal desktop size and 1440×900; mobile at 390×844. The temporary viewport override was reset afterward.

| Flow | Desktop and mobile result |
|---|---|
| Cashier, Student Records, Students & Contacts, Staff Notes filters | Applied searches/selections, then reset to each clean default route. Shared 44px Apply/Clear controls, keyboard activation and mobile stacking verified. |
| Copy controls | All eight fields plus Copy Student Details displayed `Copied ✓`; Staff Notes copy also succeeded. Unsaved first-name value was used without saving identity changes. Targets measured at least 44px high/wide. |
| One package | Existing synthetic student #1 rendered one overview row and one selected panel. |
| Multiple packages | Synthetic student #2 rendered two independent overview rows and one selected panel; selection and active-section navigation verified. |
| Payments/refunds | Correct selected-package payment endpoint and selected-payment refund endpoint; expandable refund fields were usable on mobile. Browser review was read-only for payment/refund/credit/expiry actions; actual writes/replay tests ran in the isolated test database. |
| Credits and Expiration | Correct selected package, canonical balances, current expiry, existing audit history and correctly targeted forms. |
| History | Refund/expiry filters showed only that activity; switching purchases changed the history to the other purchase's own grant. |
| Full Cashier | Both viewports opened Cashier with `student_id=2`. |
| Allowed/denied roles | Existing local Super Admin could view controls. Existing local Assistant saw no finance/copy controls and received the expected Cashier 403 on both viewports. Admin rendering/mutations were additionally covered by focused endpoint tests. |
| Mobile layout | Page width stayed within the 390px viewport. Wide tables scroll internally; selected panel sections and selectors wrap without page overflow. |
| Browser logs | No warning/error entries returned in the final QA tab. |

Local QA used only pre-existing synthetic accounts/students. One clearly named `Pass 1 Synthetic Package` was created through the existing UI for synthetic student #2 to verify multiple-package rendering (two credits, USD 40, expiry 2027-04-01). It remains local. No real payment was made, no message was sent and no production data was changed. The unsaved identity edit was discarded by navigation. Automated tests used the separate `bolt_landing_test` database.

Native clipboard contents could not be independently read through the connected browser's virtual clipboard API. The browser verifies the success feedback and current unsaved input; existing JavaScript tests verify the copied payload and fallback behavior. No stronger OS-clipboard claim is made.

Screenshots retained locally:

- `C:/Users/e/AppData/Local/Temp/pass1-package-desktop.jpg`
- `C:/Users/e/AppData/Local/Temp/pass1-filters-mobile.jpg`
- `C:/Users/e/AppData/Local/Temp/pass1-payment-mobile.jpg`
- `C:/Users/e/AppData/Local/Temp/pass1-package-mobile.jpg`

## Limits and completion

Package histories still load the existing selected purchase's records without pagination; this pass avoids rendering every purchase's expanded detail but does not introduce a new export/history architecture. Unselected purchase relationships are batch-loaded for canonical summaries; query count is bounded, while record volume still scales with the student's existing history. Wide multi-column overview tables require horizontal scrolling on small screens.

No typed credits, offering identity, social analytics, exports, lesson workspace, TOTP, pins/favorites or Hostinger operations were implemented. No financial rules were changed.

`HOSTINGER_OPERATIONS_HANDOFF.md` remains unchanged with SHA256 `C551CD5E06E671DD31CA7DBA127253F55443CBB26471CAA5B8B8677D76D94286`. GitHub and production were not contacted to certify their state. The authorized local implementation, verification and report are complete; changes remain ready for review in the working tree.
