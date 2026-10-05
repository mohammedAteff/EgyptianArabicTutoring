# Stage 2 — Student Portal, Lesson Workspace & Teaching Experience

Date: 2026-10-05. Local Herd development and GitHub work only. Starting main/origin main: `c61e35a882dc333aab34a64d6a7937b6ae624a24`.

Implementation, required automated verification and desktop/mobile browser QA are complete. Normal Git push evidence is recorded at the end. No production access or deployment.

## Feature implementation and reuse

| Requirement | Implementation | Existing foundation reused |
|---|---|---|
| 1. Public/student reschedule parity | One timezone selector, flag/search/selected state, calendar and slot component; current session → timezone → date/slot → old/new/timezone review → confirmation | TimezoneService/TimezoneDisplayService, local flag assets, BookingWizard, AvailabilityService, SlotResolver and unchanged RescheduleService write path |
| 2. Homework | Staff assignments with optional lesson context, dates, assigned/in_progress/submitted/completed lifecycle, explicit sharing, student response, tutor feedback, HTTPS link and existing resource/material references | Student identity, Booking-owned Lesson Workspace, SafeLessonUrl and existing private resource/material access |
| 3. Learning plan | Goals, focus, level/notes, status, start/update dates and editable milestones with completion dates | Existing Student; narrow plan/milestone records |
| 4. Tutor preparation | Separate internal student/lesson preparation records, with no student visibility switch | Existing teaching-role boundary; Educational Notes kept in their existing area |
| 5. Resource assignment | Existing published resources referenced by ID; optional lesson, instructions and student review state | Existing Resource records/files and LessonMaterialService download/redirect safety |
| 6. Pronunciation/error log | Pronunciation/vocabulary/grammar patterns, correction/notes and practising/improved/resolved status | Existing student/lesson ownership and teaching authorization |
| 7. Teaching tags | Student-wide or lesson tags, normalized labels and duplicate create prevention | Discovery found no existing teaching tag system; one small shared teaching-tag table |
| 8. Progress summary | Completed lessons, completed/shared milestones and homework, shared Educational Notes and improved/resolved patterns | Authoritative stored bookings and teaching records; no inferred performance score |
| 9. Next action | Outstanding current questionnaire → open homework → unreviewed resource → compatible package booking when no upcoming confirmed lesson → learning progress | Existing FormAssignmentService/submissions and typed entitlement eligibility/projection |
| 10. Notification center | Persistent owner-scoped in-app events and read state for homework, resources, questionnaires, eligible published materials, lesson state/time changes and entitlement notices | Existing student authentication and business read models; no email/Telegram dependency |
| 11. Low balance/expiry | Type, package, purchase identifier, available units and expiry displayed per allocation | Existing EntitlementService; legacy/unclassified units do not become bookable typed credit |
| 12. Private lesson feedback | Optional rating/comment for an owned completed lesson; edits reuse one booking-unique row; staff view in teaching context | Existing completed Booking ownership; no public review publication |

Business choices are explicit: low balance means at most two available units on an eligible typed allocation; expiry notices cover active packages with remaining units whose expiry is within 14 business-calendar days, including an already expired date. Booking eligibility requires one compatible allocation meeting the SessionType's exact units; allocations are not pooled. No conversion between one-hour/two-hour rights. Questionnaires use their current published version, with mandatory forms first. Homework submission is a response/status change; tutors decide completion. No new file uploads or duplicate Resource files.

## Architecture and touched-code hygiene

- `BookingSlotPresenter` centralizes the existing availability-to-signed-slot presentation. Shared `timezone-selector`, `booking-calendar` and `booking-slot` Blade components serve both flows. Removed obsolete Livewire timezone modal/search state and the student's datalist picker; there is one selector per page.
- Rescheduling preserves canonical UTC, business/customer snapshots, signed slot/visitor validation, minimum notice, buffers, exception dates, locking, idempotency and existing typed debit provenance. The service that moves the booking was not replaced.
- `StudentPortalService` owns the existing portal queries; `StudentTeachingReadModel` batches teaching relationships and computes facts/actions. `EntitlementService::forPackages` reuses already loaded packages rather than fetching them twice.
- `TeachingRecordService` centralizes owner/lesson/reference checks, Student-first locking, transactional saves, milestone completion, resource-review reset and privacy redaction. Staff writes use a focused Form Request. Small student controllers handle homework, resources, notifications, teaching pages and feedback; the Student model did not grow.
- One teaching form component serves staff creation/editing. Teaching links extend Student Records and the existing Booking Lesson Workspace. Removed duplicate success presentation and an unused save-result variable.
- Private Resource access reuses `LessonMaterialService::open` through an unsaved reference; it does not create material/file duplicates or public-gate requests/download metrics. Fixed the existing binary download response's public-cache default by explicitly restoring private/no-store caching.
- Browser QA caught and fixed Alpine's inability to compile a bare `try` statement in a click expression. Timezone navigation now uses a component method. The common modal handles search/no-match, focus trapping, Escape and focus return without another dependency.

## Schema and historical handling

One additive migration: `database/migrations/2026_10_05_173212_create_student_teaching_tables.php`. Nine tables: `homeworks`, `learning_plans`, `learning_milestones`, `tutor_preparations`, `resource_assignments`, `student_error_logs`, `teaching_tags`, `lesson_feedback`, `student_notifications`.

All teaching ownership uses existing Student/Booking IDs with foreign keys and restrictive deletion. Optional lesson/reference FKs are nullable; admin creator references use SET NULL. Sharing defaults false. Tutor preparation is structurally staff-only. Lifecycle values use finite enums, core fields are relational/scalar, and indexes follow student/shared/status/due/read/context queries. A short explicit resource-assignment index avoids MariaDB's identifier limit. Feedback has a true booking-unique constraint; notifications have a true hash-deduplication unique constraint. Tag create deduplication is serialized under the Student lock; a DB uniqueness constraint is deliberately omitted so merges can preserve historical rows. Milestones remain owned through their plans.

Student merge transfers all eight directly student-owned tables, preserving IDs/timestamps and milestone ownership. Privacy redacts homework/responses/links, plans/milestones, preparation, correction notes, tags and feedback, withdraws sharing, and removes notification messages while preserving structural teaching history and existing financial/booking history. No old production migration was changed. Useful factories support isolated synthetic tests; no curriculum/demo data is automatically seeded.

The migration was applied to the local database only. An initial long-index-name failure was repaired before final verification; recovery was restricted to the newly created empty Stage 2 tables. Existing student/financial data was not reset. Browser QA subsequently created clearly named synthetic teaching records through staff forms and rescheduled an existing synthetic QA booking.

## Authorization, validation and privacy

`StudentTeachingPolicy` permits active Super Admin/Admin teaching access and denies Assistant; it does not alter Assistant Educational Notes rights. Verified active student authentication and owner/share queries protect every portal action. Foreign records/bookings/material references are rejected, resource publication and safe URLs are rechecked when opened, and completed homework cannot be rewritten by students. Feedback accepts ratings 1–5, only for owned completed lessons, with transactional Student/Booking locks and one row per lesson. All writes use normal web CSRF protection; feedback is rate limited.

Teaching text/feedback is escaped. Notifications use generic teaching copy rather than duplicating private assignment content, and internal links still require fresh authorization. Staff audit metadata contains record/kind/booking identifiers, not preparation, homework responses, feedback or credentials. New teaching responses are private/no-store. No private content was added to generic exports, analytics, Telegram or logs.

## Query/performance

Teaching collections use eager-loaded plan milestones, resource metadata and eligible material metadata. The regression fixture expands to eight homework/resources/milestones and verifies two resource reads and one milestone read, rather than per-row fetches. Package entitlements/ledger/payment/refund data is loaded once by the existing portal path. Notifications batch insert with a unique key and paginate at 20. Notification generation occurs when the authenticated student visits the portal; it records the current eligible state, not every unseen intermediate event. There are no new background jobs, schedulers or workers.

## Automated verification

| Check | Result |
|---|---|
| Stage 2 feature/presentation tests | 44 passed / 273 assertions |
| Existing StudentRescheduling focused tests | 6 passed / 48 assertions |
| Full current-schema suite | 932 passed / 8,198 assertions; zero failures/errors/skips |
| Dedicated MariaDB concurrency suite | 16 passed / 76 assertions; database-backed cache/session configuration |
| JavaScript suite | 17 passed |
| Production frontend build | Passed on final source |
| Blade cache | Passed on final source |
| Pint | Passed |
| Git whitespace check | Passed |
| PHPStan | 257 starting findings → 257; final complete path/identifier/message multiset unchanged, including duplicates; zero added/removed findings |

The current-schema suite excludes `MigrationACompatibilityTest` (historical intermediate-schema expectations) and `MariaDbConcurrencyVerificationTest` (run separately with its database-backed cache/session configuration). No historical test is changed to fake a current-schema pass. Global PHPStan still exits with existing debt; the acceptance gate is zero new diagnostics, with no baseline, ignore or level change.

New endpoint tests cover staff roles, owner/foreign/private boundaries, validation, milestones, preparation privacy, private resource bytes/headers/publication/URLs, review reset, links/material access, tags, deterministic actions, non-pooled typed booking eligibility, persistent notification read state and event deduplication, typed notices, private feedback, escaping, merge/privacy and batched queries. Presentation tests cover Cairo/New York/Berlin/UTC shared controls, flags, 12-hour slots, signed canonical UTC, exact original typed allocation/debit and replay idempotency, plus invalid date/timezone input. Calendar selection clearing was verified in the actual browser instead of asserting implementation text.

## Browser QA

Local Herd URL resolved through Boost. Desktop views at 1280/1440 widths and mobile at 390×844. Only clearly synthetic staff/student data was used.

| Journey | Observed result |
|---|---|
| Super Admin teaching authoring | Created shared homework/plan/completed milestone/resource/error/tag and private preparation through the UI; all saves verified |
| Student teaching | Shared records present; preparation absent; goals, milestone, improved pattern and lesson tag visible |
| Homework lifecycle | Saved progress → submitted response → staff saw response, marked completed and added feedback → student saw feedback and progress 1/1 |
| Resource review | Marked reviewed; date persisted |
| Private feedback | Student submitted 4/5 and synthetic comment for completed lesson; staff saw it in teaching context |
| Notification center | All requested event categories rendered; mark-read persisted after reload and fresh sign-in; dashboard unread count updated |
| Typed notices / next action | Package/type/purchase/expiry labels visible; outstanding questionnaire correctly had priority |
| Public/student parity | Separate views of the same QA lesson/date/New York timezone showed exactly the same six visible slot labels and tutor equivalents before rescheduling |
| Timezone interaction | City search, flags, no-match text, selected display, Escape/focus return and actual student timezone navigation passed |
| Review and confirmation | Old/new time and selected timezone rendered; changing date cleared the selected slot and disabled review; confirmed synthetic reschedule succeeded |
| Canonical booking result | QA booking #6 moved to 2026-10-08 06:00–07:00 UTC / 2:00–3:00 AM New York / 9:00–10:00 AM Cairo; customer snapshot New York, business Cairo, reconfirmation flagged; debit count remained zero. Separate automated typed scenario retained its original debit/type/units exactly once |
| Admin / Assistant | Ordinary Admin could open teaching; Assistant got 403, teaching link was hidden, existing Student Records/Educational Notes access remained |
| Responsive layouts | Public timezone/calendar/slots, student teaching/review, and staff teaching form fit the mobile viewport without horizontal overflow |
| Recent browser errors | Fresh final student/public mobile/staff mobile tabs had no warnings/errors |

Browser resource download clicks were exercised, but this browser tooling did not expose a completed download event or response capture. File delivery is therefore **not claimed browser-confirmed**; focused endpoint tests passed download bytes, ownership, safe redirects and private/no-store headers. Screenshot evidence is local-only, outside Git, under `C:/Users/e/.codex/visualizations/2026/10/05/01a10cc2-a93f-75f0-a06b-762317ec539a/`: `stage2-learning-desktop.png`, `stage2-learning-mobile.png`, `stage2-public-booking-desktop.png`, `stage2-public-booking-mobile.png`, `stage2-reschedule-desktop.png`, `stage2-reschedule-review-mobile.png`, `stage2-timezone-mobile.png`, `stage2-admin-teaching-desktop.png`, `stage2-admin-teaching-mobile.png`, `stage2-assistant-denied.png`. Temporary responsive overrides were reset.

## Deferred items / future dependencies

- Existing 257 static-analysis diagnostics remain deferred to the dedicated cleanup phase.
- Existing Assistant login landing points to a dashboard that denies Assistant; authorized Student Records still works. No role broadening was introduced.
- Existing admin dashboard next-lesson student-time line was observed displaying business time beside the customer timezone. Canonical storage and Stage 2 student presentation are correct; the separate admin dashboard line is flagged for Stage 4/time-display audit.
- Portal notifications are synchronized on authenticated visits; instant/background delivery and richer teaching history filtering/pagination can be assessed later if needed.
- Stage 3 Staff Operations, Stage 4 scheduling/financial/data-quality work and Stage 5 launch/danger-zone tools are unstarted.
- No dependency changes, hosting operations, inbound Telegram, worker/scheduler setup or public feedback publishing.

## Git and Telegram completion status

**Stage 2 complete. Implementation/report commit `b5d09a4` was pushed normally to `origin/main` after all acceptance gates passed.** This documentation checkpoint records the successful implementation push; the final response records its own pushed HEAD and remote confirmation. Existing unrelated untracked Hostinger/proposal documents are preserved and excluded; screenshots/build output/temp files/credentials are excluded. Ready for Stage 3. Production was not accessed or deployed.

Existing outbound path discovered: `TelegramDeliveryService::direct`, with the current destination/bot and delivery deduplication; legacy `TelegramNotificationService` also inspected. Safe local preflight: one configured synthetic destination, zero enabled destinations, zero enabled destinations with a successful verification delivery; legacy credential/recipient configuration absent. **Preflight category: `verified_destination_unavailable`.** No identifier/token is recorded. The single final completion/status resolution is reserved until after implementation, tests, build/QA, report/tracker and normal push. No disabled synthetic destination will be enabled or repurposed. Final attempt/result is recorded in the final response; transport failure preserves the successful stage. No aggressive retry or configuration change is authorized.

The final safe receipt is saved after the last push, outside Git alongside the browser evidence as `stage2-telegram-receipt.json`. It records only notification/transport attempted, success/failure, safe error category and message IDs if returned. This preserves the required final-step ordering without storing credentials or leaving implementation changes uncommitted.
