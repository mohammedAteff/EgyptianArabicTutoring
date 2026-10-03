# Discovery

Feature expansion of the existing Laravel 13.32 / Livewire 4.4.5 application, dated 2026-10-03. The current repository, prior remediation reports, operational handoff, routes, migrations, services, tests and supplied screenshots were reviewed. Existing booking, ledger, identity, reporting, geolocation and authorization services remain authoritative. No dependencies were changed. Local Herd and MariaDB were used for synthetic QA; no real customer records were changed for QA.

# Baseline

Starting GitHub and production commit: `5aaa22ab85ace375f3effd81924d635f7f7bc214`. PHP 8.4.25 locally; production PHP 8.4.19. Static analysis baseline: 285 findings at the existing level 5 configuration. The separate `intermediate-schema` group assumes the historical `forms.prompt_trigger` column; two tests fail on both the untouched baseline and current schema. They were retained without weakening assertions.

# Student Identity Copy Controls

Eight compact controls beside identity labels copy the current form values, including unsaved edits: first name, last name, email, full phone, date of birth, identity status, preferred timezone and complete internal notes. Buttons do not submit. Dates are formatted for a human-readable clipboard block; empty fields are handled. Existing SVG styling, accessible labels and live success feedback are reused. Secure clipboard API and text-selection fallback are covered by JS tests. Browser QA captured nine successful native fallback operations, including unsaved first-name and copy-all, with no saved record mutation. The connected browser's virtual clipboard reader did not mirror the native clipboard; success and the selected text were inspected instead.

# Credit Expiration / Reconciliation

One shared credit-expiry component receives the existing StudentPackage and StudentLedgerService summary in Student Records and Cashier. It shows each package separately: acquisition date, allocated, courtesy, consumed, restored and remaining credits, effective expiry and status. Active zero-balance packages display Exhausted; expired dates use the business calendar. Courtesy credits inherit their package expiry. Existing extension workflow and audit history remain authoritative. Browser extended synthetic package #2 from 2026-12-15 to 2027-01-15 with a reason; both views immediately showed the same date and six credits. Read controllers explicitly use an eager-loaded ledger snapshot; write callers retain fresh authoritative queries.

# Cashier Filters / Export

CashierReportService validates and supplies one scope for the screen, CSV and XLSX: student, inclusive business date range, transaction type, package, payment method and existing package status. Blank filters intentionally mean all. Existing payments/refunds are immutable recorded events; no unsupported provider settlement statuses were fabricated. Named selection controls use available domain records. Screen previews the first 100 matching transactions; exports stream the full matching scope in bounded chunks.

Real downloaded filtered CSV contained one synthetic refund: numeric `-20`, currency USD, REF-3, original PAY-3 and business date `YYYY-MM-DD HH:mm`. XLSX endpoint tests load the produced workbook and inspect native numeric amounts/credit cells and explicit string cells. Browser XLSX download was blocked with ERR_BLOCKED_BY_CLIENT; this is a browser limitation, not a claimed successful browser download. Trusted numeric values are native numbers; user-controlled dangerous text retains formula-injection protection. Free-credit rows have blank amount/currency and a separate numeric Credit Change. No raw ISO/seconds/AM-PM dates; each row identifies the Business Timezone.

# Transaction References

Stable internal references derive from existing immutable record IDs: PAY-id, REF-id and CRD-id. Refunds include their original PAY-id. External/manual references remain separate and may be blank. No fake provider reference or financial backfill was introduced; historical amounts/history remain unchanged.

# Student Records

Navigation/page context is Student Records; existing route compatibility is retained. Total Students counts unmerged, non-deleted canonical students with a positive allocation or a confirmed/completed/no-show lesson. Resource-only leads and empty identities are excluded; historical actual students remain in the roster even when credits are exhausted. Suspended students remain real roster members and are explicitly filterable.

StudentRecordsQuery supplies list and exports with normalized primary identity/verified secondary email search, verification/suspension state, package, available-credit state, expiry range, timezone, lesson status and business-calendar join range. Unsettled standard preset packages with no expiry are not falsely classified as usable credits. Exports contain operational identity, package/credit/expiry and booking summary; passwords, tokens, DOB and internal notes are excluded. Browser verified Total Students 2, a one-row filtered roster, and its actual CSV.

# Students & Contacts Export

DirectoryQuery unifies canonical roster students and contact/lead activity. Primary normalized emails and verified secondary emails map contacts only when the canonical owner is unambiguous. Ambiguous matches fail closed. Merged identities are excluded and activity ownership follows existing canonical links. Population modes: all, students, resource contacts, non-student leads and students with resource activity. Search, resource/category, booking state and business-calendar activity filters share the list/export query. No country filter was invented where an authoritative identity-country column is absent. Browser verified the all-population directory and a resource-only scope with one row in the downloaded CSV.

# Resources Consolidation

One Resources sidebar item reuses the book icon. Internal Resources/Categories tabs appear on index/create/edit pages. Existing resource/category routes, records, slugs, public URLs, gates, tracking and permissions remain intact. Browser navigated both management areas.

# Automatic Meeting Room Assignment

Existing MeetingLinkService retains provider resolution and URL security. Provider mutexes are acquired in stable ID order before booking/room locks. Eligible rooms are active, valid, available without overlap, and exclude both chronological neighboring lesson rooms. Rotation favors unused then least-recently-used eligible rooms with a stable ID tie-breaker. Existing manual assignment and stored URL snapshot are retained where valid; rescheduling revalidates and assigns only when needed. A controlled admin action reconciles future confirmed unassigned lessons. No production reconciliation was executed as QA.

Browser reconciled three synthetic consecutive lessons to Room 1 / Room 2 / Room 3, manually changed the first to Room 3, then reconciled again and observed 3 / 2 / 3. Tests cover previous/next neighbors, disabled/invalid rooms, overlap, provider restrictions, rescheduling and manual snapshot preservation. Separate MariaDB worker-process tests exercise simultaneous overlapping assignments.

# Country-Based Initial Localization

Existing trusted GeoIpService selects initial FR → French and DE/AT → German. Existing localized deep links take precedence. An explicit choice persists in session plus a one-year HttpOnly SameSite=Lax cookie, and overrides later country changes. Admin, student, API, preview, download and crawler routes do not receive country redirects. BookingWizard persists manual changes without discarding its existing state. No raw IP persistence was added.

HTTP tests mock the country service and assert all three route outcomes, manual persistence, private-route exclusions and rejection of untrusted country headers. Browser also verified French/German/Austrian first visits using a temporary local-only country-service fixture; the front controller was restored byte-for-byte and has no final diff. This proves browser route/render behavior, not physical access from those countries or production MMDB provisioning. Browser manually chose German, then an unlocalized resource URL retained German.

# Internal Staff Bins

Staff Notes provides plain-text title/body creation, search, copy and owner editing, with created/updated/author metadata. Bodies are escaped, bounded and excluded from audit payloads. Shared staff visibility is separate from Student Educational Notes. Super-admin deletion is recoverable soft deletion with an explicit inline checkbox confirmation; audit metadata preserves history. Browser created, copied, edited, searched and removed a synthetic staff note, including escaped script-like text.

# Student Educational Bins

Educational Notes in Student Records and the student portal are keyed to the canonical student. Staff can create visible or hidden educational notes; students create/edit their own writing and cannot edit tutor notes. Hidden/cross-student requests return 404. Client ownership/type/visibility injection is ignored; student creation is always owned by the authenticated student and visible. There is no student DELETE route. Super administrators can moderate all student educational notes with soft deletion and audit. Merges move note ownership while retaining creator history; privacy erasure redacts active and deleted note bodies. Browser verified tutor creation, student login/create/edit and a foreign-student 404.

# Authorization Matrix

| Operation | Super admin | Admin / Assistant | Student |
|---|---|---|---|
| Read shared staff notes | Yes | Yes | No |
| Create staff note | Own | Own | No |
| Edit staff note | Own only | Own only | No |
| Soft-delete staff note | Any, confirmation | No | No |
| Read student notes | Authorized record | Authorized record | Own visible only |
| Create student note | Authorized record | Authorized record | Own only |
| Edit student note | Any (moderation) | Own staff-authored only | Own student-authored only |
| Soft-delete student note | Any, confirmation | No | No route |

Suspended/unverified portal access is denied. Nested student/note routes verify parent ownership before policy evaluation. Cross-student or hidden requests do not leak the note's existence.

# Database Changes

Forward migration `2026_10_03_141923_create_staff_and_student_bins.php` adds only staff_bins and student_bins, with restrictive parent foreign keys, indexes, timestamps, soft deletes and MEDIUMTEXT bodies sized for valid multibyte notes. A 20,000-emoji portal round trip is covered by a feature test. No financial/customer backfill. Rollback explicitly refuses to drop populated tables, including soft-deleted notes; retain the data and use a reviewed forward migration. Factories support test ownership states. Existing merge/privacy services include student note ownership/redaction.

# Files Changed

- `app/Domains/Analytics/Services/EngagementCounterService.php`
- `app/Domains/Booking/Services/MeetingLinkService.php`
- `app/Domains/Booking/Services/RescheduleService.php`
- `app/Domains/CMS/Models/Setting.php`
- `app/Domains/Reporting/Services/ExportService.php`
- `app/Domains/Students/Models/PaymentRecord.php`
- `app/Domains/Students/Models/PaymentRefund.php`
- `app/Domains/Students/Models/SessionLedgerEntry.php`
- `app/Domains/Students/Models/Student.php`
- `app/Domains/Students/Services/StudentLedgerService.php`
- `app/Domains/Students/Services/StudentMergeService.php`
- `app/Domains/Students/Services/StudentPrivacyService.php`
- `app/Http/Controllers/Admin/ContactController.php`
- `app/Http/Controllers/Admin/DashboardController.php`
- `app/Http/Controllers/Admin/MeetingLinkController.php`
- `app/Http/Controllers/Admin/SettingController.php`
- `app/Http/Controllers/Admin/StudentBillingController.php`
- `app/Http/Controllers/Admin/StudentController.php`
- `app/Http/Middleware/SetRequestLocale.php`
- `app/Livewire/BookingWizard.php`
- `app/Providers/AppServiceProvider.php`
- `resources/js/app.js`
- `resources/views/admin/billing/cashier.blade.php`
- `resources/views/admin/bookings/meeting-links.blade.php`
- `resources/views/admin/contacts/index.blade.php`
- `resources/views/admin/dashboard.blade.php`
- `resources/views/admin/resource-categories/create.blade.php`
- `resources/views/admin/resource-categories/edit.blade.php`
- `resources/views/admin/resource-categories/index.blade.php`
- `resources/views/admin/resources/create.blade.php`
- `resources/views/admin/resources/edit.blade.php`
- `resources/views/admin/resources/index.blade.php`
- `resources/views/admin/settings/index.blade.php`
- `resources/views/admin/students/index.blade.php`
- `resources/views/admin/students/show.blade.php`
- `resources/views/layouts/admin.blade.php`
- `resources/views/layouts/public.blade.php`
- `resources/views/layouts/student.blade.php`
- `routes/web.php`
- `tests/Feature/AdministratorAuthorizationTest.php`
- `tests/Feature/CashierBillingReconciliationTest.php`
- `tests/Feature/EngagementCountersTest.php`
- `tests/Feature/FollowupRemediationTest.php`
- `app/Domains/Administration/Models/StaffBin.php`
- `app/Domains/Contacts/Services/DirectoryQuery.php`
- `app/Domains/Students/Models/StudentBin.php`
- `app/Domains/Students/Services/CashierReportService.php`
- `app/Domains/Students/Services/StudentRecordsQuery.php`
- `app/Http/Controllers/Admin/StaffBinController.php`
- `app/Http/Controllers/StudentBinController.php`
- `app/Policies/StaffBinPolicy.php`
- `app/Policies/StudentBinPolicy.php`
- `database/factories/StaffBinFactory.php`
- `database/factories/StudentBinFactory.php`
- `database/migrations/2026_10_03_141923_create_staff_and_student_bins.php`
- `resources/js/clipboard.js`
- `resources/views/admin/staff-bins.blade.php`
- `resources/views/components/copy-button.blade.php`
- `resources/views/components/credit-expiry-table.blade.php`
- `resources/views/components/report-filters.blade.php`
- `resources/views/components/resources-tabs.blade.php`
- `resources/views/student/educational-bins.blade.php`
- `tests/Feature/BinAuthorizationTest.php`
- `tests/Feature/CountryInitialLocaleTest.php`
- `tests/Feature/FeatureExpansionTest.php`
- `tests/Feature/MeetingRotationTest.php`
- `tests/clipboard.test.mjs`

# Automated Tests

Final current-schema result: 685/685 passed, 6472 assertions. The separately executed MariaDB concurrency verification result: 13/13 passed, 57 assertions (separate sequential run). JavaScript: 8/8 pass (clipboard, telemetry and form builder). Vite build, Blade compilation, PHP lint, Pint and git diff whitespace checks pass. Full-schema security/booking/ledger/identity/Telegram tests remain in scope.

The retained historical intermediate-schema group: 1 pass / 2 fail, reproduced on the untouched baseline. A preliminary overlapping execution of two suites caused a test-database deadlock; it was discarded and final suites were rerun sequentially. This is not presented as a clean run. Static analysis has existing findings detailed below.

# Browser Verification

Synthetic local workflows verified:

| Area | Actual evidence |
|---|---|
| Identity | Eight individual controls plus copy-all; unsaved edit copied, form not saved |
| Expiry | Existing extension form; new date visible in both views |
| Cashier | Student/refund filter, one-row preview, actual downloaded CSV inspected |
| XLSX | Browser download blocked; produced workbook inspected in HTTP feature tests |
| Roster | Renamed navigation, real roster count, normalized search, actual filtered CSV |
| Directory | Canonical all view and resource-only filter, actual downloaded CSV |
| Resources | Shared navigation and both tabs |
| Meeting | Reconcile 1/2/3; manual 3/2/3 persisted through second reconcile |
| Localization | FR/DE/AT browser routes with temporary country fixtures; manual German persists |
| Counters | All controls, draft invisibility, publish; known 3 hours / 3 prior-month sessions / 1 live visitor |
| Staff notes | Create/edit/search/copy/escape/confirmed soft-delete; assistant shared read and no delete |
| Student notes | Visible tutor note, hidden note withheld, student create/edit, foreign 404 |
| Mobile | Narrow layout, scrolling tables and identity copy-all |

More than 30 actual local workflow checks completed. Some later visible UI submissions used CDP DOM click assistance after the browser pointer backend stopped dispatching clicks. No authentication or permission checks were bypassed. Screenshots saved locally: `C:/Users/e/AppData/Local/Temp/awa-feature-student-notes.png` and `C:/Users/e/AppData/Local/Temp/awa-feature-public-counters.png`.

# PHPStan Delta

Same level 5/configuration: **285 → 261 findings; 24 removed; zero new**. Full verbose JSON compared by relative path, message and identifier, accounting for line movement. No broad ignore, lowered rule level or baseline suppression was added. The 261 remaining pre-existing findings mean the whole repository is not PHPStan-clean.

# Deployment

Prepared for authorized SSH deployment to the existing Hostinger application. A private recovery point, exact release SHA, forward-only migration, matching built assets and before/after preservation fingerprints are required. Production receipt will be appended after deployment.

# Deferred Hostinger Operations

HOSTINGER_OPERATIONS_HANDOFF.md remains unchanged; SHA256 `C551CD5E06E671DD31CA7DBA127253F55443CBB26471CAA5B8B8677D76D94286`. No H1/H2/H3/H4 cron jobs, scheduler, queue-worker cron, watchdog, Telegram poller, external-mail or offsite backup repair were configured. No final operations acceptance drill was performed. The application code paths are implemented/tested, but scheduled production execution remains dependent on the deferred HOSTINGER_OPERATIONS_HANDOFF.md.

# Remaining Limitations

Browser XLSX download is blocked by the connected browser; file-level endpoint tests verify workbook content. Country browser checks use local country fixtures; real national-network detection still depends on production trusted proxy/MMDB setup. Dropdowns are bounded to 500 student/package/resource options; exports themselves stream the full matched query. Heavy public counters have up to 15-minute freshness lag; live visitors are refreshed on each payload request, with no autonomous live-page refresh guarantee. Historical aggregate-only analytics cannot retroactively reconstruct individual exclusions after raw data is pruned. Two baseline historical-schema tests and 261 baseline static-analysis findings remain. Scheduled host automation is explicitly deferred.
