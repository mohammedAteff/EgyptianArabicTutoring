# Remaining Features Implementation Tracker

## Stage

Stage 1 — Admin Shell, Communication, Analytics UX & Public Polish. Implementation and validation complete. Later stages remain unstarted: Stage 2 Student Portal/Lesson Workspace; Stage 3 Staff Operations; Stage 4 Scheduling/Financial Lifecycle/Data Quality; Stage 5 Development/Launch Data Management.

## Feature / Existing foundation reused / Files and classes touched

| Feature | Existing foundation reused | Files/classes touched | Final status |
|---|---|---|---|
| 1. Practice-time formatting | EngagementCounterService's authoritative elapsed lesson and practice calculation | HumanDurationFormatter; EngagementCounterService | Implemented; boundary tests passed |
| 2. Site announcement | Setting keys, Business Timezone/DST resolver, public/student layouts, Alpine and SafeLessonUrl | AnnouncementService; SettingController; AppServiceProvider; announcement component/settings/JS; routes; fr/de translations | Implemented; audience, expiry, DST and security tests passed |
| 3. Reddit | SocialLink, native footer anchors, existing semantic telemetry and SocialAnalyticsRollup/report/export paths | SocialLink; ContentController; content/public views; telemetry tests | Implemented; config/footer/export and JS deduplication tests passed |
| 4. Role-aware sidebar branding | Actual authenticated Administrator and existing sidebar | Administrator::roleLabel; admin layout | Implemented for all three roles |
| 5. Human role labels | Stored role keys/authorization retained | Administrator::roleLabel; administrator index/account card | Implemented; role tests passed |
| 6. Owner account name | Actual profile name retained | Existing admin account card | Correct spelling is Abdallah. Owner email supplied by owner is absent locally; no unrelated account renamed |
| 7. Sidebar spacing/security | Existing navigation/icon family and security permission | admin layout | Implemented; Super Admin-only Account Security retained |
| 8. Desktop collapse | Existing mobile drawer, browser storage | admin layout; admin-sidebar JS tests | Implemented; mobile state remains separate |
| 9. Student copy placement | copy-button, clipboard.js, current unsaved form values | student show; copy-button | Implemented; nine upper-right sibling controls tested |
| 10. Recovery-code copy | Existing authorized one-time response and clipboard helper | security view; clipboard.js; TwoFactor/clipboard tests | Implemented; client-side only, no redisplay/flash/telemetry |
| 11. Live Pulse copy | Existing metrics unchanged | analytics view | Implemented; explanatory sentence removed |
| 12. Historical warning copy | Existing cutover flags/date semantics retained | reports view; rollup test | Implemented; warning removed, semantics tested |
| 13. Maintenance navigation | Existing maintenance_visits dataset/filter/export | MaintenanceAnalyticsController; SystemHealthController; routes; admin/health views | Implemented; canonical Insights route and old export alias |
| 14. Friendly maintenance analytics | Country Analytics display helper; ReportPeriod/ExportService | TimezoneDisplayService; AnalyticsDashboardController; maintenance view/controller; export/resilience tests | Implemented; one summary, flags/globe, bounded queries and filter parity |

## Tables/migrations touched

No migrations or new tables. Announcement configuration uses the existing settings table's unique key and indexed group. Maintenance reads the existing maintenance_visits table; social configuration uses social_links. No production database access or changes.

## Tests added

HumanDurationFormatterTest; AnnouncementBannerTest; StageOnePresentationTest; announcement-banner.test.mjs; admin-sidebar.test.mjs. Existing AdministratorTwoFactorTest, FilterAwareExportsTest, MaintenanceModeResilienceTest, CairoDailyAnalyticsRollupTest, clipboard and telemetry tests extended.

## Browser verification

Completed on the Herd local site at desktop/mobile sizes using synthetic staff/student records. Counter, announcement public/translated/authenticated portal/disabled/dismissal states, Reddit icons and exactly one physical-click event, all staff roles, collapse/expand/persistence, mobile drawer Escape/focus, Student Records controls, synthetic one-time recovery panel, Live Pulse/reports and maintenance/filter/export links verified. CSV download captured; XLSX download event and exact browser clipboard read were limited by the browser tooling, with export contents and clipboard payload independently passing focused tests. Full matrix/evidence: STAGE_1_ADMIN_PUBLIC_ANALYTICS_UX_REPORT.md.

## Query/performance notes

Announcement settings use one group query per layout. Maintenance uses four bounded queries independent of visit count, paginates details at 30 and streams exports through the existing service. No N+1 relationship queries introduced. Strict MariaDB grouping verified.

## Security/privacy notes

Announcement text is escaped; CTA uses existing HTTPS safety validation; Assistant cannot configure it. Role keys and security authorization unchanged. Recovery text is assembled exclusively from rendered one-time codes without logging, analytics, audit, session flash or another endpoint. Existing no-store/no-referrer behavior preserved. No Telegram configuration changes or inbound operations.

## Known debt intentionally deferred

The exact starting commit has 257 PHPStan diagnostics. Full diagnostic multiset comparison after implementation has 257 with zero new/removed issues; no baseline/ignores added. Owner-name data correction remains outside this local stage because the supplied email is absent locally. Existing Assistant login/dashboard landing mismatch deferred; its authorized records page works. No Hostinger operations authorized.

## Final status

Stage 1 implementation complete: full current-schema suite 888 passed / 7,920 assertions; final targeted 67 passed / 731 assertions; JS 17 passed; production build, Blade cache, Pint and whitespace checks passed; PHPStan 257→257 with zero new diagnostics. Normal main push is authorized; completion commit/push evidence is recorded in the final stage response. No migrations and no Hostinger deployment. One final owner-authorized Telegram status resolution follows the push; local preflight category is verified_destination_unavailable. No credentials or configuration changes.
