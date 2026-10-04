# Pass 2 — Filter-Aware Exports, Social Analytics & Privacy Consistency

## Local state and scope

Implemented in the existing working tree on `main`, based on unchanged HEAD `d15967c88912f34db289ef3621bd9ebef1d56c85`. Pass 1 changes, its report, and existing untracked artifacts were preserved. Nothing was committed, pushed, deployed, or changed on Hostinger. Production state was not certified. No real messages or financial transactions were sent.

The required prior reports and current routes, export controllers, shared writer, views, reporting services, and tests were reviewed before implementation. Discovery reconfirmed **10 tabular export endpoint families / 15 datasets**. Resource/media downloads, backup downloads, and ICS files are outside this inventory. Forms' index now links to **Responses & exports**, where the visible scope controls live, instead of offering a context-free CSV link.

Installed versions were checked: PHP 8.4, Laravel 13.32, Livewire 4.4.5, Tailwind 4, and PHPUnit 12.5.35. Relevant Laravel, testing, Tailwind, Livewire, computer-use, and spreadsheet inspection guidance was applied. `.ai/rules` is absent. Boost tools were not exposed in this session; installed package source, existing tests, Artisan, and Herd discovery supplied the available fallbacks. No dependencies or package locks were changed.

## Complete export matrix

All rows below support **CSV and XLSX** through `ExportService`, share validated scope between screen and export, and expose the shared **Filters / Apply Filters / Clear Filters** controls. CSV/XLSX links preserve active filters; pagination never limits an export. Clear returns to the default scope. Period reports default to the last 30 business calendar days; integrity reports are snapshots and intentionally have identity/category filters instead of arbitrary dates. Blank default selectors are labeled **Default**, rather than implying all-time/all-version scope.

`Admin` below means Super Admin or Admin. `B` means dates use the configured business timezone, converted to UTC query boundaries. `S` means a current snapshot with no report date predicate. Generic exports omit DOB, private staff notes, raw IP, authentication secrets, and credentials. Forms can export authorized answers to questions explicitly created for that purpose; Assistant-hidden answers stay excluded for Assistant.

| Dataset / endpoint family | Visible UI filters | CSV | XLSX | Shared query/read model | Authorization | Timezone | Sensitive fields | Verified result |
|---|---|---|---|---|---|---|---|---|
| Cashier financial ledger — billing/export | Student, from/through dates, transaction type, package, payment method, package status | Yes | Yes | Existing `CashierReportService` | Admin | B | Contact fields permitted; private notes/DOB/IP excluded | Filtered screen and both parsed files match; browser CSV inspected |
| Billing reconciliation — billing/reconcile/export | Search, student ID, problem category | Yes | Yes | `BillingReconciliationService` + `ReconciliationReport` | Admin | S | Whitelisted diagnostic details only | All six discrepancy categories share one projection; identity scope passes |
| Student Records roster — students/export | Search, identity status, package, available credits, expiry dates, timezone, session status, joined dates | Yes | Yes | Existing `StudentRecordsQuery` | Admin export; Assistant retains permitted roster view | B + current roster | Authorized name/email/phone; no DOB or notes | Search, clear, all-row export and role checks pass |
| Students & Contacts directory — contacts/export | Search, population, resource/category, booking status, activity dates | Yes | Yes | Existing `DirectoryQuery` | Admin export | B + current directory | Authorized contact fields; no private profile fields | Same population/search scope in both formats |
| Forms responses — forms/{form}/export | Owned form version, draft/submitted status, student search, creation dates | Yes | **Added** | `FormSubmissionQuery` | Admin and Assistant; Assistant-visible questions only | B, submission creation time | Own-version check; hidden labels and answers excluded for Assistant | Both formats parsed; foreign version 404; 400-row export remains chunked |
| Analytics overview — analytics/overview/export | Period or explicit from/through dates | Yes | Yes | Existing analytics services with `ReportPeriod` | Admin | B | Aggregate values and safe metric dimensions | Whole selected-day scope shared across screen/CSV/XLSX |
| Analytics countries — analytics/countries/export | Period/dates, country name/code search, sort column, direction | Yes | Yes | Shared `countryRows()` | Admin | B | Aggregate country values | Search/sort preserved; intended screen columns plus underlying counts exported |
| Analytics sections — analytics/sections/export | Period/dates, section ID contains | Yes | Yes | Shared `sectionRows()` | Admin | B | Section aggregate values | Same section/date scope in both formats |
| Operational Traffic — reports/export type=traffic | Period/dates, acquisition source | Yes | Yes | Existing `ReportService::getTrafficReport()` | Admin | B | Aggregate traffic values | Source narrows screen and both parsed exports |
| Operational Bookings — reports/export type=bookings | Period/dates, booking status, source, campaign | Yes | Yes | Existing `ReportService::getBookingsReport()` | Admin | B; student-local display retained | Authorized booking contact/attribution fields; no DOB/notes/IP | Status and attribution scope shared |
| Operational Resources — reports/export type=resources | Period/dates, resource, category | Yes | Yes | Existing `ReportService::getResourcesReport()` | Admin | B | Resource counts only | Real resource/category options; matching resource in both formats |
| Operational Social — reports/export type=social | Period/dates, platform, placement, exact page, country, source, medium, campaign, language, context | Yes | Yes | Existing `ReportService` delegates to additive `SocialAnalyticsRollup` | Admin | B, rollups partitioned by timezone | Dimension aggregates; no visitor identifiers/IP exported | Footer/floating filters and raw/durable scope pass; desktop/mobile inspected |
| Operational Events — reports/export type=events | Period/dates, event, exact page, source | Yes | Yes | Existing `ReportService::getEventsReport()` | Admin | B | Event counts and safe dimensions | Exact page filter avoids collision with pagination `page` |
| Operational Campaigns — reports/export type=campaigns | Period/dates, campaign, source, content | Yes | Yes | Existing `ReportService::getCampaignContentReport()` | Admin | B | Campaign aggregate values | Combined campaign/source/content scope preserved |
| Maintenance visitors — health/maintenance-visitors/export | Period/dates, country, page/path contains | Yes | Yes | Shared `maintenanceFilters()` / `maintenanceQuery()` | **Super Admin only** | B | Raw IP column removed; existing visitor identifier/path/country retained | Screen paginates 30; export reads every match |

The six operational datasets share one endpoint family. The other nine are separate endpoint families. Inventory was reconfirmed after implementation; no additional tabular export writer remained outside this matrix.

## Shared scope, safety, and bounded cleanup

`ReportPeriod` consolidates date validation and business calendar boundaries across analytics, operational reports, and maintenance reporting. Explicit custom dates must be supplied together, in order, within a five-year maximum window. Operational tab navigation retains a custom period. Country header sorting retains the other active filters. Existing roster/directory filter queries remain in use. Existing detail-back navigation was left intact where it already permits returning to the previous page.

Forms use one validated owned-version query for screen and both export formats. Authorized staff may still inspect drafts or submitted responses; this pre-existing privacy decision was retained and is explained on the page. Export questions are loaded once in their configured order; answers are loaded in 200-record batches. A generic contact export does not gain access to form answers.

Countries use one search/sort projection, eliminating the old export mismatch. Export columns are Country Code, Country, Unique Active Visitors, Sessions, Page Views, Bounced Sessions, Bounce Rate, Booking CTA Clicks, Completed Bookings, Conversion Rate, and Resource Requests.

The shared writer neutralizes formula prefixes in strings and headers, including leading whitespace. IDs, phone numbers, references, and answers stay strings. Trusted integer/float values stay numeric, including negative refund amounts. CSV is streamed with UTF-8 BOM. **XLSX remains an in-memory PhpSpreadsheet workbook**; chunked inputs do not make the workbook streaming. Limits are 10,000 data rows, 200,000 cells, 8,000,000 bytes of string content, and a guard at 65% of PHP's configured memory limit. Exceeding a limit gives a validation message to narrow scope or export CSV with the same filters. Partial files and workbook memory are released. A hard row-limit regression exercises the fallback.

Reconciliation exports and screen now share a flattened six-category diagnostic projection, including previously omitted booking discrepancies. Refund sums and ownership/consumption checks use aggregate subqueries and batched eager loading instead of per-record queries. Social raw-day completeness is counted in one grouped query rather than one query per stored day. Controllers remain orchestration-focused. No universal filter object across unrelated domains, replacement event architecture, suppression, or unrelated refactor was introduced. Some existing report services still materialize aggregate collections; CSV output streaming is not a claim that every upstream query has constant memory.

## Social pipeline, attribution, and history

The existing telemetry and server ingestion pipeline remain authoritative. Marked social links are excluded from the generic outbound listener, so one physical click emits one semantic event:

| Link | Existing event | Placement |
|---|---|---|
| Footer WhatsApp | `whatsapp_clicked` | `footer_social` |
| Floating WhatsApp | `whatsapp_clicked` | `floating_cta` |
| Footer Telegram | `telegram_clicked` | `footer_social` |
| Footer YouTube, TikTok, Instagram, and other configured SocialLink platforms | `social_link_clicked` | `footer_social` |
| Unmarked outbound link | `outbound_link_clicked` | Its available metadata or unknown |

Platform, placement, page, business date, country, source, medium, campaign, language, and context are available in the Social report and both formats. Platform and country filters are normalized; remaining dimensions use the same exact comparisons for raw and durable rows. Existing exclusions and bot handling remain in force. WhatsApp totals can include both placements while detail rows retain attribution. **Social Clicks** excludes generic outbound clicks; **Generic Outbound Clicks** is separately labeled. No event was redefined to fit a label.

The additive `daily_social_metrics` table extends existing aggregation. Each complete business day stores counts and daily distinct visitors per dimension group, plus a completeness marker; the reporting timezone is part of the unique scope. It stores **no raw IP or visitor token**. The existing daily command persists social detail before both the 180-day whole-day pruning path and the 90-day chunk pruning path. Failure prevents that path from deleting raw data. Rebuilding an already-pruned day does not erase its durable snapshot. Reading a day uses raw events while they remain complete, then the matching timezone's durable snapshot after pruning.

Daily unique visitors are explicitly labeled **Daily Unique Visitors (dimension group)**. Period-distinct visitors are computed from retained raw visitor tokens only. When durable history is needed, the period-distinct total is unavailable and shown as such; daily group uniques are never summed and advertised as period-distinct. Rollups from another business timezone are not silently relabeled. Missing historical page/placement/language/context stay unknown; country uses unresolved `ZZ`. No historical analytics was fabricated or backfilled. Detail already lost before this fix cannot be recovered; the new history is reliable when the existing aggregation command runs before pruning.

Telemetry failure checks cover endpoint 503, beacon throwing, fetch rejection, and JavaScript UUID failure. Native anchor navigation remains untouched, listeners remain idempotent, and bounded retries reuse the event UUID.

## Raw-IP remediation and local data cleanup

Global producer discovery included request IP, REMOTE_ADDR, address columns, and binary address helpers. Unnecessary new persistence was stopped in `AuditLogService`, staff login/logout, password recovery auditing, and reschedule history. The append-only AuditLog creation guard nulls the legacy column and redacts address keys and validated literal/embedded IPv4/IPv6 in payloads. Audit actions, actors, targets, amounts, timestamps, and other permitted metadata remain. Database sessions use `PrivacyDatabaseSessionHandler`, which omits IP while retaining session payload, user agent, activity, and existing security behavior.

A final sweep found direct login/Livewire throttles also placed raw addresses in database cache keys. `TransientRateLimitKey` uses an app-key HMAC for these **demonstrably required short-lived security buckets**, while preserving the existing limits and 60/300-second expirations. It is not treated as anonymous analytics identity or permission to retain permanent IP fingerprints. Route middleware already hashes its keys in the installed Laravel version and retains its limits. GeoIP resolution uses IP transiently. Existing user-selected internal-connection exclusions retain their established restricted HMAC purpose. External web-server security logs were outside this application database change.

Three migrations were created and applied **locally only**:

1. `2026_10_03_232534_create_daily_social_metrics_table.php` — additive dimension history.
2. `2026_10_03_232535_redact_persisted_request_addresses.php` — clears legacy address columns and scans audit payloads in 200-row batches, changing only address values.
3. `2026_10_04_002050_redact_transient_rate_limit_addresses.php` — deletes expired legacy address-bearing throttle keys and rekeys active counters/timers while preserving expiration. Existing active opaque counters are merged conservatively; unrelated cache entries and other application prefixes remain intact. Unexpected counter values stop cleanup instead of discarding security state.

| Local cleanup | Rows affected |
|---|---:|
| audit_logs address column | 81 |
| session_reschedules address column | 2 |
| maintenance_visits address column | 0 |
| framework sessions address column | 1 |
| Audit payload rows needing address changes | 0 |
| Expired raw-address cache keys removed | 6 |
| Active cache rows needing rekeying locally | 0 |

Post-cleanup read-only checks found zero non-null address columns in those four tables and zero raw IPv4 cache keys in the local application. IPv4/IPv6 cleanup, active counter merging, timer preservation, idempotence, auth auditing, and booking/security limits are additionally covered in isolated tests. These counts describe this local database, not production.

Protected pre-cleanup dumps are outside the repository/webroot:

- `C:/Users/e/AppData/Local/Temp/pass2-pre-privacy-local.sql` — audit, reschedule, maintenance, and session tables.
- `C:/Users/e/AppData/Local/Temp/pass2-pre-cache-privacy-local.sql` — cache table before its cleanup.

Windows ACLs were checked: local user, administrators, SYSTEM, and configured Codex sandbox identities; no public/everyone grant. Dumps can contain legacy private data and are not published or included in this report. The privacy migrations intentionally do not reconstruct erased addresses on rollback. Recovery would require the protected pre-migration backup; restore must be planned to avoid reverting newer operational data. A future production run requires its own contemporaneous protected backup and must record its own counts.

## Verification

| Check | Final result |
|---|---|
| Focused export matrix: `php artisan test --compact tests/Feature/FilterAwareExportsTest.php` | **16 passed, 308 assertions**, rerun after the final selector wording change; actual CSV and XLSX decoded for each of the 15 datasets. |
| Focused privacy/cache/booking throttle regressions | **23 passed, 226 assertions**. Includes active counter/timer migration, unchanged security limits, auth audits, IPv6 cleanup, and Livewire language-switch cleanup keys. |
| Current-schema suite: `php artisan test --compact --exclude-group=intermediate-schema --exclude-filter=MariaDbConcurrencyVerificationTest` | **724 passed, 7,030 assertions**. |
| Separate sequential run: `php artisan test --compact tests/Feature/MariaDbConcurrencyVerificationTest.php` | **13 passed, 57 assertions**. |
| Combined current-schema result | **737 passed, 7,087 assertions**; focused tests are included, not added twice. |
| `node --test tests/telemetry.test.mjs tests/clipboard.test.mjs tests/form-builder.test.mjs` | **12 passed**, including all four failure-path cases and semantic footer platform tracking. |
| `npm run build` | Passed, Vite 8.3.0. |
| `php artisan view:cache --no-interaction` | Passed after final changes. |
| `php vendor/bin/pint --dirty --format agent` | Passed after final changes. |
| `git diff --check` | Passed; non-failing CRLF normalization notices remain. |
| Full PHPStan comparison, unchanged level 5/config, 2GB memory allowance | **261 before → 259 after: zero new entries, two removed entries**. |

PHPStan comparison uses the full verbose diagnostics normalized by path, identifier, and message, preserving duplicates while permitting moved lines. Both removed entries were unnecessary nullsafe access in `AuditLogService`, eliminated with the raw-IP producers. No baseline, suppression, dependency, or rule-level change was added. PHPStan's global command still returns a nonzero result for the 259 existing findings; the comparison establishes zero new debt.

The historical `intermediate-schema` group contains three compatibility tests previously documented as one pass and two baseline failures. It was excluded from the requested current-schema run, left unchanged, and not rerun. The concurrency class ran only after the full suite completed; no second database test process was launched during the accepted final full run. The final copy-only selector wording adjustment was followed by the complete 15-dataset endpoint regression and final view/build checks.

The new tests cover meaningful scope changes, clear-default behavior, both formats, pagination-independent exports, guest/Assistant permissions, hidden Forms questions, foreign-version ownership, spreadsheet formula safety and numeric/string cell types, whole business days including Berlin DST, raw/durable social scope, and both pruning paths. Existing roster/directory/date-boundary/authorization, financial, Forms, and booking regressions also passed in the full suite.

Final local evidence logs live in `C:/Users/e/AppData/Local/Temp/`: `pass2-full-final.log`, `pass2-concurrency-final.log`, `pass2-filters-final.log`, `pass2-cache-privacy.log`, `pass2-js-final.log`, `pass2-build-final.log`, `pass2-views-final.log`, `pass2-pint-final.log`, `pass2-diff-final.log`, `pass2-phpstan-final.json`, and `pass2-phpstan-delta.json`.

## Browser and file inspection

Used the existing Herd-served local application, confirmed through Herd site discovery; no separate web server was started. All 15 dataset screens were navigated in the connected browser. Each visible panel, Apply/Clear behavior, format links, and retained scope was inspected. Countries search and header sorting preserved the range/search in both format URLs. Social platform/placement filters with custom dates preserved every dimension in export URLs and operational tab navigation.

Social was visually inspected at 1440×900 and 390×844. Mobile page width remained 390px; wide result tables scroll inside their containers. Shared action controls measured at least 44px high. No warning/error browser-log entries were returned in the QA tab. The temporary viewport override was reset.

One browser-generated financial CSV was saved and read: `C:/Users/e/Downloads/financial_ledger_2026-10-04.csv`. Its 13 columns and three matching synthetic payment/refund/courtesy rows agreed with the selected scope; the negative refund remained a number and no raw IP/DOB/private notes were present. Regression tests also opened actual generated CSV and XLSX contents for **all 15 datasets**, rather than relying on HTTP status. `IOFactory` read real workbook sheets; formula/string/numeric types were checked separately. The 400-submission Forms case verifies export beyond the visible page.

**Browser limitation:** the connected in-app browser blocked XLSX download with `ERR_BLOCKED_BY_CLIENT` / inspector blocking and its download wait timed out. This was not bypassed. XLSX file generation, parsing, and cell inspection passed in automated endpoint tests, but a native browser XLSX save/open is not certified. Browser filter/link QA and actual file-content verification are distinct evidence.

Evidence retained locally:

- `C:/Users/e/AppData/Local/Temp/pass2-browser-qa.json` — 15-screen scope/link observations.
- `C:/Users/e/AppData/Local/Temp/pass2-social-desktop.png`.
- `C:/Users/e/AppData/Local/Temp/pass2-social-mobile.png`.
- `C:/Users/e/AppData/Local/Temp/pass2-social-mobile-controls.png`.

QA used existing local synthetic identities and read-only report interactions. Automated mutations ran in the separate `bolt_landing_test` database; the historical local purges above ran against the backed-up `bolt_landing` database. No production credentials, customer data, or secret values are included in this report.

## Deferred operations and remaining limits

`HOSTINGER_OPERATIONS_HANDOFF.md` remains unchanged, SHA256 `C551CD5E06E671DD31CA7DBA127253F55443CBB26471CAA5B8B8677D76D94286`. Scheduler, queue, watchdog, and other deferred hosting work were not altered. No typed credits, lesson workspace, TOTP, replacement analytics system, or production backfill was implemented.

Durable detail depends on the existing aggregation being scheduled; this local pass does not activate Hostinger scheduling. Exact period distinctness cannot be reconstructed after raw visitor identities are pruned. Large XLSX requests must use CSV or narrower filters. Existing repository-wide PHPStan findings remain; zero new debt is the acceptance result, not a claim that the entire application is static-analysis clean.
