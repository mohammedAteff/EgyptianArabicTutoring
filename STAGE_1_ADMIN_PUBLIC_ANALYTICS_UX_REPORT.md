# Stage 1 — Admin Shell, Communication, Analytics UX & Public Polish

Date: 2026-10-05. Starting commit: `18ac92c27f96851bbf83d5df68831f76fc22dc76` on `main`.

## Result

The 14 Stage 1 presentation/communication features are implemented on the existing application foundations. Validation passed. The completion commit and normal GitHub push result are recorded in the final stage response. No Hostinger access, deployment or operations handoff was performed. Stages 2–5 remain unstarted.

The owner clarified that the desired name is **Abdallah**, not Abdullah, and supplied an owner email. That exact email has no account in the local database. The account card continues to show the actual signed-in administrator's profile name; no unrelated local account was renamed, and no global name override was introduced. Any correction to that absent owner's record must occur in its actual environment under separately authorized operations.

## Features completed and foundations reused

| Feature | Implementation / reused foundation |
|---|---|
| Practice-time formatting | HumanDurationFormatter starts with the largest nonzero unit and pluralizes correctly. Zero is `0 minutes`; 60 minutes is `1 hour`; 24h1m is `1 day, 1 minute`. Existing actual elapsed completed-lesson duration and authoritative practice dwell calculation are unchanged. |
| Site announcement | Existing settings infrastructure; enabled/audience/severity/dismissal; EN/FR/DE messages and optional HTTPS CTA; optional Business Timezone start/expiry converted through existing DST rejection logic. Visibility is evaluated at runtime without jobs. Public/student layouts share one component. |
| Reddit | Central SocialLink platform list drives validation and the add-channel form. Footer uses the same native external anchor, size, hover/focus behavior and semantic telemetry. Disabled or unconfigured links stay hidden. Existing Social report filters and CSV/XLSX paths support Reddit. |
| Role-aware branding | Administrator::roleLabel drives Super Admin/Admin/Assistant Operations Console and ordinary account/list role labels. Stored role values and authorization are unchanged. |
| Account name | Uses each authenticated administrator's actual profile; desired owner spelling recorded above. |
| Sidebar spacing/security | Staff Notes belongs in Overview's spacing group; consistent item rhythm; shield icon/active state for Super Admin-only Account Security; configuration items aligned with the existing navigation family. |
| Desktop collapse | Fully hidden sidebar, zero reserved content margin, explicit keyboard/ARIA expand/collapse button, browser preference restored before rendering. Existing mobile drawer remains independent. Duplicate native click/touch handlers and redundant state removed. Mobile top-bar controls fit the viewport. |
| Student copy placement | Existing overlapping-rectangles component placed at upper right as heading/label siblings. Nine controls retain non-submit semantics, current unsaved values, focus/ARIA and copied confirmation. |
| Recovery-code copy | Upper-right action reads only the intentionally rendered one-time panel, actual administrator name and ten codes. Plain-text context/line breaks assembled client-side through the existing clipboard helper. No new endpoint, logging, analytics, audit or session payload. |
| Live Pulse copy | Removed only the technical explanatory sentence; metric logic unchanged. |
| Historical warning | Removed only the yellow warning block; cutover flags, dates and report semantics remain. |
| Maintenance relocation | Canonical Insights & Telemetry → Maintenance Mode page. Existing health screen links to it; old export URL remains a permission-preserving compatibility alias to the same controller. |
| Maintenance visuals | One three-card summary; real bounced-hit rate; country names/flags/globe from the same helper as Country Analytics; all country groups; paginated traffic details in Business Timezone; original filter/export service retained. Unknown empty/XX/ZZ country values share the ZZ group/filter. |

## Validation

Installed versions checked before using APIs: PHP 8.4.25, Laravel 13.32.0, Livewire 4.4.5, Tailwind 4, Vite 8.3.0, PHPUnit 12.5.35, Larastan 3.12.2. Relevant Laravel/Livewire/Tailwind/testing skills and version-scoped Boost documentation were used. No dependency changes.

| Check | Result |
|---|---|
| Duration + announcement targeted suite | 27 passed, 83 assertions |
| Presentation/recovery/export final targeted suite | 67 passed, 731 assertions |
| Adjusted adjacent presentation/counter/mobile/date fixtures | 46 passed, 368 assertions |
| Final maintenance/unknown-country checks | 11 passed, 75 assertions |
| Full current-schema PHPUnit suite | 888 passed, 7,920 assertions; zero failures/errors/skips |
| JavaScript tests | 17 passed: clipboard formatting/current values, recovery payload, announcement dismissal/storage errors, desktop persistence/storage errors, social semantic deduplication/failure paths |
| Production frontend build | Passed |
| Blade cache | Passed |
| Pint dirty formatter | Passed |
| Git whitespace check | Passed |
| PHPStan comparison | Exact HEAD 257 → final 257. Full path/identifier/message multiset comparison: zero added diagnostics and zero removed diagnostics; line shifts ignored; duplicate counts retained. |

The full current-schema run excludes MigrationACompatibilityTest and MariaDbConcurrencyVerificationTest by class name. Intermediate-schema expectations are intentionally not rewritten to fit the current schema. No new transactional race path requires a dedicated concurrency rehearsal. An initial overbroad run also executed those classes; historical compatibility failures were separated from the current-schema acceptance result. Global PHPStan still exits unsuccessfully because of unchanged existing debt; this is a **no-new-debt comparison**, not a globally clean PHPStan claim. No baseline/ignore/strictness changes.

Tests with expectations tied to the old copy-label nesting, old sidebar state name or old duration text were updated to the new public contract. Two existing StudentReschedulingTest scenarios relied on the real clock preceding their fixed October 5 booking; they now freeze time before the fixture's booking, keeping results deterministic without changing application behavior.

## Browser QA

Performed on the existing Herd local site through the in-app browser, at 1440×1000 and 390×844, using existing explicitly synthetic staff/student records. Temporary viewport override reset afterward.

| Surface | Evidence/result |
|---|---|
| Public counter | Existing known three-hour fixture renders `3 hours`, retaining its computed total. Desktop/mobile inspected. |
| Announcement | Enabled synthetic message leaves public and student pages usable; French message and translated dismissal label; CTA native HTTPS link; mobile wrap; fixed warning; dismissal persists through reload; revised settings reappear. Disabled banner absent publicly and in the student portal. Schedule/DST/expiry boundaries covered in feature tests. |
| Student portal | Signed into the synthetic learner's existing record; announcement present in the authenticated dashboard and absent after disabling. |
| Reddit | Added through the existing form, enabled through the existing toggle, matching 16px platform/12px external-link icons and noopener/noreferrer. One physical guest footer click changed local Reddit semantic-event count from 0 to exactly 1, placement footer_social. QA channel returned to disabled. |
| Staff shell | Actual names and role-aware titles checked for Super Admin, Admin and Assistant, including mobile drawer. Account Security is present for Super Admin and absent for other roles. |
| Collapse/mobile | Desktop sidebar/content widths changed from 256px offset to 0, persisted on navigation and expanded with Enter. Mobile drawer opens; Escape closes and returns focus to the menu button. Mobile top bar visually fits. |
| Student copying | Relevant controls are upper-right siblings; synthetic first-name/current unsaved details exercised. Clipboard formatting/current-value correctness covered by JS tests. |
| Recovery panel | Synthetic Super Admin only: enrollment produced ten one-time codes and one Copy action; `Copied ✓` confirmation; mobile panel fits without document overflow; leaving the panel removes codes/copy action. No secret-bearing screenshot or report taken. Synthetic account restored to its pre-QA disabled MFA state using the existing audited service. |
| Live Pulse/reports | Removed sentences/warning absent in rendered screens. |
| Maintenance | Desktop/mobile inspected; single summary, loaded globe images, friendly country/details; ZZ + pricing filter returns two matching rows; CSV/XLSX links preserve all selected filters. CSV download captured. |
| Browser errors | No current-stage uncaught JavaScript/Alpine errors. Historical October 1 Boost browser logs were ignored. |

Browser tooling limitations: its clipboard reader did not expose the application copy buffer, despite the browser's `Copied ✓` confirmation; exact recovery text and clean line breaks are verified by JS tests, and one-time/private response protections by PHP tests. XLSX's browser download event was not captured within the bounded wait; its link/filter parameters were verified, and generated workbook contents/CSV parity passed feature tests.

Saved non-sensitive desktop evidence: the local `stage-1-maintenance.jpg` artifact linked in the final response. Screenshots/downloads/temp outputs are excluded from Git.

## Touched-Code Hygiene

- Extracted repeated duration presentation; removed stale unused counter date variables.
- Centralized ordinary role labels and social platform definitions.
- Removed replaced health analytics queries/Blade and stale controller imports; kept one maintenance query/filter/export implementation.
- Shared country/flag display between Country Analytics and Maintenance Analytics; normalized unresolved groups and filters consistently.
- Removed redundant sidebar state, inline native handler wrappers, duplicate DOM/touch listeners and empty initialization; retained existing mobile focus/scroll behavior.
- Moved copy actions out of labels; reused the existing component/delegated clipboard handler.
- Used one announcement component/settings service and narrowly scoped translations, with escaped messages and versioned dismissal.
- Reviewed only touched/adjacent code; no unrelated application redesign/refactor.

## Touched-Schema Hygiene

**No migrations or new tables.** Existing settings ownership/unique key/indexed group support simple announcement scalar values. Date nullability is represented by blank optional values; UTC instants come from the existing Business Timezone resolver. No needless JSON document or duplicated maintenance concept. Announcement save is atomic through the existing Setting::set path. Existing maintenance_visits and social_links schema reused. No new FKs or indexes required by these access patterns.

## Query/performance notes

Announcement layout lookup uses one group query. Maintenance uses four bounded reads (aggregate, grouped countries, pagination count, 30 detail rows), proven unchanged as visits increase. No per-row relationship/database lookup; country flag lookup uses existing local assets. Exports retain the existing cursor/size safeguards. System Health no longer redundantly fetches the maintenance dataset.

## Security/privacy notes

Assistant rights, stored role keys and Super Admin security/health boundaries are unchanged. Announcement routes are CSRF-protected and restricted to Super Admin/Admin; HTML is escaped, CTA uses the existing HTTPS URL validator, invalid/nonexistent/ambiguous wall times fail validation without partial saves. Recovery codes remain solely in the authorized one-time response and client clipboard operation; subsequent GET has no codes. Existing no-store/no-referrer, hashing, factor replay/consumption and audit redaction behavior passed the security suite. No secrets printed or recorded in this report.

## Debt Deferred to Final Cleanup

- Existing 257 PHPStan diagnostics; no new findings.
- Assistant login's existing redirect reaches a dashboard route that denies Assistant access. Authorized Student Records navigation works and was used for shell QA; changing landing/role permissions is outside Stage 1.
- The supplied owner's account does not exist in the local database; no production profile change was attempted.
- Existing unrelated untracked Hostinger deployment/preflight and typed-entitlement proposal documents were preserved and excluded from the stage commit.
- No future stage, scheduler/worker, inbound Telegram, Hostinger deployment or operations work performed.

## Telegram completion notification

Existing outbound implementation discovered: TelegramDeliveryService::direct and its bot/destination/deduplication delivery path; legacy TelegramNotificationService inspected as fallback. Local preflight found only a disabled synthetic QA bot/destination, no successful verified destination, no legacy token and no legacy recipients. Destination identifiers and credentials are omitted.

Preflight delivery status: **unavailable — verified_destination_unavailable**. The final one-off notification resolution is reserved until after validation, report/tracker updates and normal push. There is no authorized verified target to send to locally; the disabled QA target must not be enabled or repurposed. No transport request, token/configuration change, inbound polling, commands, scheduler/worker configuration or aggressive retry is authorized by this stage. Final attempt/result is reported in the final response; Telegram failure does not invalidate the successful implementation/checks/push. No Telegram message ID exists when transport is unavailable.

## Files changed

- `app/Domains/Administration/Models/Administrator.php`
- `app/Domains/Analytics/Services/EngagementCounterService.php`
- `app/Domains/Analytics/Services/HumanDurationFormatter.php`
- `app/Domains/CMS/Models/SocialLink.php`
- `app/Domains/CMS/Services/AnnouncementService.php`
- `app/Domains/Timezone/Services/TimezoneDisplayService.php`
- `app/Http/Controllers/Admin/AnalyticsDashboardController.php`
- `app/Http/Controllers/Admin/ContentController.php`
- `app/Http/Controllers/Admin/MaintenanceAnalyticsController.php`
- `app/Http/Controllers/Admin/SettingController.php`
- `app/Http/Controllers/Admin/SystemHealthController.php`
- `app/Providers/AppServiceProvider.php`
- `lang/de.json`
- `lang/fr.json`
- `REMAINING_FEATURES_IMPLEMENTATION_TRACKER.md`
- `resources/js/announcement-banner.js`
- `resources/js/app.js`
- `resources/js/clipboard.js`
- `resources/views/admin/administrators/index.blade.php`
- `resources/views/admin/analytics/index.blade.php`
- `resources/views/admin/analytics/maintenance.blade.php`
- `resources/views/admin/auth/security.blade.php`
- `resources/views/admin/content/index.blade.php`
- `resources/views/admin/reports/index.blade.php`
- `resources/views/admin/settings/announcement.blade.php`
- `resources/views/admin/settings/index.blade.php`
- `resources/views/admin/students/show.blade.php`
- `resources/views/admin/system/health.blade.php`
- `resources/views/components/announcement-banner.blade.php`
- `resources/views/components/copy-button.blade.php`
- `resources/views/layouts/admin.blade.php`
- `resources/views/layouts/public.blade.php`
- `resources/views/layouts/student.blade.php`
- `routes/web.php`
- `STAGE_1_ADMIN_PUBLIC_ANALYTICS_UX_REPORT.md`
- `tests/admin-sidebar.test.mjs`
- `tests/announcement-banner.test.mjs`
- `tests/clipboard.test.mjs`
- `tests/Feature/AdministratorTwoFactorTest.php`
- `tests/Feature/AdminUiBillingPresentationTest.php`
- `tests/Feature/AnnouncementBannerTest.php`
- `tests/Feature/CairoDailyAnalyticsRollupTest.php`
- `tests/Feature/EngagementCountersTest.php`
- `tests/Feature/FilterAwareExportsTest.php`
- `tests/Feature/MaintenanceModeResilienceTest.php`
- `tests/Feature/PhaseAVerificationTest.php`
- `tests/Feature/StageOnePresentationTest.php`
- `tests/Feature/StudentReschedulingTest.php`
- `tests/telemetry.test.mjs`
- `tests/Unit/HumanDurationFormatterTest.php`
