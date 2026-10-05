# Remaining Features Implementation Tracker

## Stage

Stage 1 — Admin Shell, Communication, Analytics UX & Public Polish is complete and pushed (`c61e35a`). Stage 2 — Student Portal, Lesson Workspace & Teaching Experience is complete and its implementation is pushed (`b5d09a4`); required verification and browser QA passed. Ready for Stage 3. Stages 3 Staff Operations, 4 Scheduling/Financial Lifecycle/Data Quality and 5 Development/Launch Data Management remain unstarted. The original Stage 1 evidence is retained below, followed by Stage 2.

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

## Stage 2 — Student Portal, Lesson Workspace & Teaching Experience

Detailed evidence: [STAGE_2_STUDENT_TEACHING_EXPERIENCE_REPORT.md](STAGE_2_STUDENT_TEACHING_EXPERIENCE_REPORT.md).

| Requirement | Status / existing foundation | Important files/classes | Schema / authorization | Tests / browser QA |
|---|---|---|---|---|
| 1. Public/student reschedule parity | Implemented; one shared timezone/calendar/slot UX and authoritative availability/signing | BookingSlotPresenter; BookingWizard; RescheduleController; timezone-selector/booking-calendar/booking-slot; student reschedule | No booking schema change; original write service/ownership/notice/locks/idempotency/debit retained | Cairo/NY/Berlin/UTC presentation tests; exact typed debit/UTC/replay test; identical six visible public/student slots, date-clear/review/confirmation desktop/mobile |
| 2. Homework | Implemented; optional Booking-owned teaching context, response/status and tutor feedback; existing resource/material access | Homework; TeachingRecordService; SaveTeachingRecordRequest; HomeworkController; teaching forms/pages | homeworks; share defaults false; active teaching staff / owned shared student | Owner/private/foreign/validation/material/link/header tests; staff assignment → student progress/submission → staff completion/feedback → student progress |
| 3. Learning plan/progress | Implemented; existing Student identity, structured goals/milestones | LearningPlan/LearningMilestone; shared service/read model/forms | learning_plans/learning_milestones; staff manage, student sees shared plan | Milestone completion/edit/foreign-plan tests; actual staff-authored goals and completed milestone displayed |
| 4. Tutor preparation | Implemented; separate from Educational Notes and aligned with Lesson Workspace teaching rights | TutorPreparation; StudentTeachingPolicy; teaching page/form | tutor_preparations; structurally staff-only; Assistant denied | Role/privacy/merge/redaction tests; staff preparation saved and absent from student page |
| 5. Assigned resources | Implemented; references existing published Resource without file/public-gate duplication | ResourceAssignment; ResourceAssignmentController; LessonMaterialService::openResource | resource_assignments; owned/shared/published recheck | Download/ownership/publication/safe-URL/review-reset tests; review persists in UI; browser download completion limited by tooling |
| 6. Error log | Implemented; pronunciation/vocabulary/grammar plus correction/state | StudentErrorLog; teaching service/read model/forms | student_error_logs; explicit sharing; staff writes | Kind-save/validation/escaping/privacy tests; improved pronunciation entry appears and summary counts it |
| 7. Student/lesson tags | Implemented; no existing teaching tags found; one normalized system | TeachingTag; TeachingRecordService | teaching_tags; Student-locked create dedup; merge preserves rows | Normalization/dedup/context tests; actual lesson tag visible |
| 8. Fact-based progress | Implemented; stored completed bookings/milestones/homework/error states and shared Educational Notes | StudentTeachingReadModel; student-progress component | Reuses above/existing notes; owner/shared scope | Visibility/read-model tests; student saw 1 completed lesson, milestone 1/1, homework 1/1 and improved pattern 1/1 |
| 9. Next action | Implemented; current forms → homework → resource → compatible single allocation/no upcoming lesson → progress | StudentPortalService; StudentTeachingReadModel; EntitlementService::forPackages | No new eligibility/funding schema; legacy units not bookable | Questionnaire priority/review reset/non-pooled allocation tests; actual outstanding questionnaire surfaced |
| 10. Notification center | Implemented; persistent in-app owner events/read state; portal visit synchronization | StudentNotification/Service; NotificationController; notification page/nav | student_notifications; unique hash key; student-owned writes; paginate 20 | Owner/read/dedup/material-publication/form/resource/lesson-state tests; actual read persists across reload/new sign-in |
| 11. Typed low balance/expiry | Implemented; existing typed projection/eligibility, package/purchase/type/expiry | StudentTeachingReadModel; EntitlementService; dashboard | Existing allocations/ledger untouched | Typed notice/expiry tests; typed package-specific labels visible; thresholds documented in report |
| 12. Post-lesson feedback | Implemented; private optional completed-lesson feedback | LessonFeedback; LessonFeedbackController; dashboard/teaching page | lesson_feedback; booking unique; completed owned Booking under locks; staff-only reading | Owner/status/rating/upsert/privacy tests; actual 4/5 feedback saved and viewed by staff |

### Stage 2 hygiene and history

One additive migration creates nine narrow tables with Student/Booking/reference FKs, restrictive history deletion, nullable admin creator SET NULL, explicit sharing, finite status values and query-based indexes. No existing production migration, financial snapshot or typed provenance changed. Merge transfers eight directly student-owned tables and keeps plan-owned milestones; privacy redacts teaching text, references/links and feedback, withdraws visibility and removes notification messages. Existing bookings/resources/financial history remains.

Removed obsolete second timezone picker/search state and duplicated slot assembly. One portal query service/read model and shared staff form replace parallel reads/presentation. Resource access delegates to existing private infrastructure; binary responses now explicitly restore private caching. Student model stayed small; focused controllers, policy and Form Request enforce server boundaries. Eager-loading regression covers eight related records with two resource reads/one milestone read. Notification batch deduplication/read pagination is bounded; teaching history is retained.

### Stage 2 verification / debt / future dependencies

Focused Stage 2 tests: 44 passed / 273 assertions; existing reschedule focus: 6 passed / 48 assertions; full current-schema: 932 passed / 8,198 assertions; dedicated MariaDB concurrency: 16 passed / 76 assertions; JS: 17 passed. Final production build, Blade cache, Pint and whitespace checks passed. Final PHPStan comparison: 257→257 with the exact diagnostic multiset unchanged; zero added/removed findings and no ignores/baseline/level changes. Browser desktop/mobile journeys and Super Admin/Admin/Assistant/Student/public matrix are documented in the stage report; resource-download completion could not be confirmed by browser tooling and is not claimed as passed there.

Existing static debt, Assistant dashboard landing mismatch and the separate admin dashboard student-time line are deferred to the appropriate later audit. Notifications synchronize current eligible events when a student visits; no instant/background notification infrastructure added. No automatic seed data, dependency changes, public feedback publication, hosting, scheduler/workers or Telegram inbound work. Stages 3–5 remain future dependencies, not implemented work.

Implementation/report commit `b5d09a4` was pushed normally to main after all gates; this checkpoint records its push evidence, and the final response records the final pushed HEAD/remote confirmation. Existing unrelated untracked user documents are excluded. One owner-authorized final outbound Telegram status resolution follows the final push through the existing service, with a safe receipt outside Git referenced by the stage report. Local preflight: zero enabled verified destinations, category `verified_destination_unavailable`; no tokens/destination identifiers/configuration changes are stored here. Stage 2 is complete; no deployment or future-stage work performed.
