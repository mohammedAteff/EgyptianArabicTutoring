# LMS V1 Implementation Status

Date: 2026-10-07 (Africa/Cairo).


## Stage 2 disposition — current implementation

**COMPLETE — backend foundation and all required local gates passed.** The separate owner Stage 2 instruction authorized the minimum backend foundation. Stage 1 remains completed and its historical verification below remains evidence for the pre-LMS application; it is not the Stage 2 release receipt. Stage 3 and production deployment have not begun.

The additive foundation includes Course → Section → Lesson → ordered LessonBlock, nine lms_* tables, publication/version enforcement, one effective-access service, manual grant/enroll/revoke/regrant/extend/start/expiry operations, owner-safe private learning assignments, current staff policies and backend JSON/file endpoints. Student merge/privacy adapters and the existing Student proof/Resource delivery authorities are reused. Tutoring credit types, ledger/payment/booking semantics, UI, dependencies and notification/analytics writers are unchanged.

Member currently means the authenticated verified nonsuspended canonical Student account, as stated while waiting for the optional membership clarification. A separate membership product has not been invented or activated. The owner explicitly confirmed relative access as elapsed 24-hour days from grant start. Fixed and future timestamps use an explicit ISO offset and normalize to UTC; start is inclusive and end exclusive.

### Stage 2 verification receipts

| Gate | Actual evidence |
|---|---|
| Focused LMS behavior | PASS 48 tests / 189 assertions in final focused run |
| New real MariaDB races | PASS 5 tests / 27 assertions in initial dedicated LMS run; independent worker processes wait at a shared start gate |
| Full concurrency regression | PASS 31 tests / 179 assertions across MariaDbConcurrencyVerificationTest, BusinessLifecycleConcurrencyTest, DevelopmentDataConcurrencyTest and LmsAccessConcurrencyTest; each class uses the dedicated MariaDB test database |
| Full current-schema regression checkpoint | PASS 1,090 tests / 9,188 assertions; 46 new LMS tests were present when that run discovered tests |
| Final complete current-schema regression | PASS 1,092 tests / 9,193 assertions, zero failures/errors/skips, approximately 288 seconds; includes all 48 LMS behavior tests on the final PHP source |
| PHPStan no-regression | PASS complete path/identifier/message diagnostic multiset 255 → 255; no additions/removals, ignore comments, baseline file or level/config weakening |
| Pint | PASS final dirty PHP check under required agent formatter; no subsequent PHP edits |
| Composer audit | PASS locked dependencies, 0 advisories/abandoned packages; first request timed out against Packagist, successful retry retained |
| npm audit | PASS 0 vulnerabilities |
| JS/front-end | No JS, CSS, Blade, package or asset change in Stage 2; Stage 1 JS20/build PASS remains the unchanged frontend evidence |
| Blade/routes | PASS all 10 LMS route entries, existing guards/roles/throttles confirmed; Blade/routes compiled successfully and generated caches cleared |
| Whitespace | PASS git diff --check and git diff --cached --check on the final application/documentation change |
| Local additive migration | PASS on verified local bolt_landing / MariaDB 10.11.18; only new foundation migration applied, batch 19 |
| Local schema after migration | 116 application tables, 75 Ran migration records/files, 12 generated columns (10 prior + 2 LMS), same 4 typed booking/ledger guards |
| Production | Not deployed or freshly inspected; recorded production baseline remains application 4296b21 / MariaDB 11.8.9 |

**Inventory correction:** Stage 1 incorrectly stated 75 local Ran migrations. The actual pre-foundation local/file count was 74; after this single additive migration it is 75. The source database migration table and current migrate:status/file inventory agree. The accepted production report's separate 75-migration count is unchanged. The local 107-table baseline and its 10 generated columns/4 typed guards were correct; Stage 2 adds exactly nine tables/two virtual columns and does not modify the old typed guards.

Initial new-test failures were corrected before the passing receipts: the new fixture clock was moved before publication timestamps; suspended staff assertions use the existing canonical redirect; the privacy revocation update now changes revoked_at/status atomically to honor its new CHECK; immutable merge date comparisons use persisted scalar facts. An initial PHPStan run exhausted the default 128 MB limit; the complete 1 GB rerun established no regression. An accidentally misnamed historical-suite exclusion was interrupted and replaced by the established MigrationACompatibilityTest exclusion; no interrupted run is counted as passing. No existing test or authority document was weakened.

Assertion counts in worker races can vary with the legitimate winner branch: the established booking/merge test checks extra persisted facts when the booking wins, and the new grant/merge test checks a grant owner only when a grant was actually created. The executed full concurrency receipt is 31/179; it is not inflated to the earlier checkpoint count.

### Stage 2 regression coverage and file inventory

| Files / area | Resulting behavior |
|---|---|
| app/Domains/Lms/Models/{LmsModel,Course,Section,Lesson,LessonBlock,AccessRule,AccessGrant,Enrollment,LearningAssignment,AccessEvent}.php | Guarded canonical structure, parent relationships, casts, archival/history protections |
| app/Domains/Lms/Services/LmsStructureService.php | Authorized course/section/lesson/block creation, safe content, current publication states with expected versions and course rule writer |
| app/Domains/Lms/Services/{LmsAccessService,LmsAccessWindow,LmsAccessOperations}.php | Shared current-access resolver, bounded read graph, UTC windows, idempotent independent sources and versioned access operations |
| app/Domains/Lms/Services/LmsStudentLifecycle.php | Merge preserves grants/source dates and supersedes enrollment collisions without restarting relative access; privacy clears private bodies/metadata and revokes/archives |
| app/Domains/Students/Services/{StudentSessionContext,StudentMergeService,StudentPrivacyService}.php | Existing proof reader and canonical lifecycle integration |
| app/Http/Middleware/EnsureStudentAuthenticated.php; app/Http/Controllers/ResourceController.php | Reuse the same current Student proof reader; existing timezone handling and public Resource gates retained |
| app/Policies/LmsPolicy.php; app/Providers/AppServiceProvider.php | Server-side existing staff boundaries and shared learner decisions |
| app/Http/Controllers/LmsFoundationController.php; app/Http/Controllers/Admin/LmsAccessController.php; routes/web.php | Explicit authorized metadata/content/file/assignment projections, nested ID checks and staff operations; no new screens |
| database/migrations/2026_10_07_152414_create_lms_foundation_tables.php | Nine additive tables, coherent composite FKs, source/live-owner uniqueness, finite publication/window/content/revocation checks, refusal of populated destructive rollback |
| database/factories/{LmsCourseFactory,LmsSectionFactory,LmsLessonFactory,LmsLessonBlockFactory}.php | Useful isolated structure states without seeded curriculum/private data |
| tests/Feature/LmsFoundationTest.php | Structure/order, ancestor states, public/member/all-Student proofs, staff boundaries, nested IDs, private A/B course/lesson/Resource isolation, safe/gated Resource delivery, sanitizer/link validation, MariaDB constraints, bounded outline reads and rollback refusal |
| tests/Feature/LmsAccessOperationsTest.php | Selected enrollment, permanent/future/fixed/elapsed relative windows and exact end, source union/deduplicated lists, replay/conflict/source uniqueness, grant edits/regrant/stale revoke, scope/owner constraints, optional Booking isolation, safe access events and no finance/notification writes, HTTP operations |
| tests/Feature/LmsLifecycleIntegrationTest.php | Canonical merge ownership/history/relative anchors, privacy body erasure and retained history, populated unknown LMS reset dependency refusal |
| tests/Feature/LmsAccessConcurrencyTest.php; tests/Feature/Concurrency/booking_worker.php | Actual simultaneous duplicates, same-source retries, conflicting revoke/extend, grant-vs-merge and cross-owner/course source/key collision rollback |
| LMS_IMPLEMENTATION_PLAN.md; LMS_IMPLEMENTATION_STATUS.md | Stage 1 discovery preserved; actual Stage 2 contracts, departures, receipts and later-stage boundaries recorded |

Directly touched PRE_LMS_INVARIANTS: I1 identity, I2 ownership, I3 sessions, I4 staff roles, I8 display-timezone middleware integration, I10 lifecycle locking, I17 retained history, I19 Resources, I20 private files, I21 teaching/session ownership, I23 privacy, I24 audit and I27 fail-closed destructive tools. Plan section 12.5 maps each interaction and preserved boundary. The other 14 invariants remain protected by existing writers and the full regression suite; Stage 2 introduces no tutoring finance/funding interpretation.

No accepted PUB/STU/SA capability ID or disposition is renumbered or upgraded. The 48 historical PARTIAL limits below remain recorded production limits, not newly certified outcomes. No Course Studio/Learning Hub/Bunny/progress/prerequisite/drip/quiz/submission/analytics/AI/transcription/ecommerce implementation is claimed.

### Stage 2 scope and unresolved decisions

Plan section 12 documents every table's purpose, domain owner, PK/FKs, uniqueness/indexes, null rationale, lifecycle/deletion/history, Student ownership and authorization. The Stage 1 proposed catalog is explicitly future-only for tables not introduced. Actual deviations are the minimal course-level audience rule, derived private-assignment owner/window through a required grant, globally stable source identities, retained superseded enrollments and the small access-operation event store. Enrollment is not an entitlement, and revoking one grant does not globally deny another source.

Optional product decision remaining: whether the owner later wants Member to mean a separate explicit membership. Current authenticated-Student semantics are documented/tested and no broad rules are automatically seeded. Later stages still own immutable releases/draft-live upgrades, assets/provider integration, fine-grained rules, completion/assessments, UI and reviewed export/reset/restore graph support. Production schema differences and a real production migration/retest remain deployment gates, not completed Stage 2 evidence.

### Git and release delivery

Pre-change HEAD was the documentation commit 91f795bbf6cedc750b6b14340612b83ec369c1d0, with accepted application baseline 4296b21368cbc9c564d0cb7a85e29b2ae408e0ee. All local tests/gates passed before staging the task's application and matching documentation for its application commit and non-force push. Three pre-existing untracked Hostinger/proposal documents remain untouched and unstaged. No production backup/deploy/retest is claimed because no production deployment was requested or performed. The final chat delivery receipt identifies the exact new application SHA; this document does not embed its own future commit hash.

## Stage 1 disposition — historical discovery receipt

**COMPLETE — architecture blueprint and final fresh baseline verification completed.**

[LMS_IMPLEMENTATION_PLAN.md](LMS_IMPLEMENTATION_PLAN.md) completes discovery, all required integration areas, all 27 invariants, additive schema boundaries, authorization/media/lifecycle decisions, the nine-stage dependency map, nine repaired-defect regressions, exclusions and post-LMS review handoff.

The **final fresh current-schema rerun passed all 1,044 tests / 9,004 assertions**, zero failures/errors/skips. The initial run executed the same 1,044 tests / 9,004 assertions with 1,043 passed and one failure: the existing scheduler-heartbeat freshness test failed its less-than-30-seconds assertion and took 62.876194 seconds. Its unchanged-source isolated rerun passed 1 test / 4 assertions, followed by the final full-suite PASS. The initial failed checkpoint remains retained as a timing-sensitive verification finding; it is not relabeled PASS, an LMS defect or a proven new production scheduler defect. No application or test change was needed.

There is **no architecture stop-condition blocker**: mandatory authorities are present, the accepted release is identified, canonical Student/Resource/private-file/auth ownership is determined, no invariant exception is required, and the roadmap remains intact. The open product/security policies below belong to later-stage decisions. At the Stage 1 exit, Stage 2 was **NOT STARTED**; the later separate owner instruction and current Stage 2 receipt above supersede that historical scope boundary.

## Baseline verified and evidence boundaries

| Item | Evidence / state |
|---|---|
| Accepted application release | `4296b21368cbc9c564d0cb7a85e29b2ae408e0ee`; local commit object and `pre-lms-stable` tag exist |
| Discovery documentation HEAD | `91f795bbf6cedc750b6b14340612b83ec369c1d0`, branch main |
| Runtime source parity | Complete release-to-HEAD changed-path inventory contains only Markdown; intervening 6f1d60d/91f795b are documentation commits |
| Working tree at entry | No tracked changes; three existing untracked Hostinger/historical proposal documents preserved |
| Production deployment | Accepted report establishes application 4296b21; **not freshly inspected/deployed in this task** |
| Permanent inventory | 26 Public + 28 Student + 99 Super Admin = 153; 105 PASS / 48 PARTIAL / 0 FAIL / 0 NOT TESTABLE retained |
| Accepted defects | All nine repaired/deployed/retested in accepted report; 0 Critical / 1 High / 8 Medium / 0 Low |
| Local stack/schema | PHP 8.4.25, Laravel 13.32.0, Livewire 4.4.5; MariaDB 10.11.18/InnoDB; 74 baseline Ran migrations (corrected by Stage 2 metadata), 107 local application tables; read-only metadata confirms 10 generated columns and 4 typed guards |
| Production schema evidence | Recorded 110 tables / 75 migrations / 10 generated columns / 4 typed guards, MariaDB 11.8.9; no fresh production/local schema equality claimed |
| Code/database changes | None to application source, tests, migrations, dependencies or runtime configuration; schema inspection read-only; PHPUnit uses isolated bolt_landing_test |
| Deliverables | New LMS_IMPLEMENTATION_PLAN.md and this status document; review copies in the current chat's outputs folder |
| Delivery state | Local documentation files only; no Git commit/push, production deployment, cleanup or outbound message performed |

Documentation SHAs remain separate from the application SHA. Do not deploy these Markdown files merely to align production and documentation history.

## Mandatory files and discovered authorities

Reviewed all six mandatory files: PRE_LMS_INVARIANTS.md, APPLICATION_CAPABILITY_MATRIX.md, PRODUCTION_APPLICATION_ACCEPTANCE_REPORT.md, PRE_LMS_DEFECT_AND_REMEDIATION_LOG.md, MISSING_FEATURES_BACKLOG.md and REMAINING_FEATURES_IMPLEMENTATION_TRACKER.md. The plan's authority section records additional local architecture/status/teaching/typed-entitlement/development-tool/deployment/cleanup material and source/schema/test discovery.

Confirmed authorities include:

- StudentIdentityService/StudentEmailService and canonical Students; separate student/web guards, absolute Student proof, staff roles/inactivity/TOTP.
- AvailabilityService/SlotResolver and existing Booking/Hold/reschedule/cancel/no-show lock/transaction authorities.
- EntitlementService/StudentLedgerService/BillingReconciliationService, immutable typed allocation/funding provenance and actual payment/refund/installment semantics.
- Resource model/public gate/admin publication, current Resource references, LessonMaterialService private delivery and strict file/path/payload guards.
- TeachingRecordService/StudentTeachingReadModel, existing Homework/plans/milestones/preparation/error/tags/feedback; StudentBin Educational Notes with distinct creator/moderation policy.
- StudentPortalService next action; StudentNotificationService persistent visit synchronization, dedupe/read state and mounted links.
- Existing AnalyticsService/ingestion/rollups/ReportPeriod/ExportService, safe AuditLogService/Presentation, Setting/localization/revisions/sanitizer.
- Canonical merge/privacy services and default-off DevelopmentDataCatalog/Graph/Files/archive/reset/import boundaries; BackupService captures the local private root.

No existing Course/Module/LMS Lesson/enrollment/access membership/Bunny/protected-playback domain was found. Existing Games and intake Forms do not supply the requested LMS grading/progress engine.

## Recorded acceptance engineering baseline

| Gate at accepted application release | Recorded result |
|---|---|
| Current-schema PHP | PASS 1,044 / 9,004 assertions |
| Dedicated MariaDB concurrency | PASS 26 / 152 assertions |
| JavaScript | PASS 20 tests |
| Frontend production build | PASS |
| Blade/routes/Pint/whitespace | PASS |
| Composer/npm audits | Zero advisories |
| PHPStan | 257 → 255 findings, zero added/two resolved; no suppressions/level reduction |

Historical evidence is not presented as a fresh test execution. Focused suites are subsets and must not be added again to full-suite totals.

## Fresh Stage 1 local verification

| Check actually executed | Result and limitation |
|---|---|
| Final full current-schema PHPUnit | PASS 1,044 tests / 9,004 assertions, zero failures/errors/skips; unchanged source; approximately 449 seconds |
| Initial full-suite checkpoint | NON-GREEN: 1,044 tests, 1,043 passed / 9,004 assertions, 1 failure / 0 errors / 0 skips; existing heartbeat freshness assertion at tests/Feature/ProductionHealthAndSchedulerTest.php:85 |
| Unchanged heartbeat isolated rerun | PASS 1 test / 4 assertions before final full PASS; initial failure remains retained, no assertion weakened or code changed |
| Dedicated three-class MariaDB suite | PASS 26 tests / **157 assertions**, real separate workers; no concurrent database refresh suite |
| Concurrency count reconciliation | Accepted 152 versus fresh 157: only test_parallel_student_booking_and_merge_follow_calendar_student_booking_lock_order changed assertion count (8 → 13) through its existing race-outcome branches; identical tests/source, not five added tests |
| JavaScript | PASS 20 / 20, zero failure/skip |
| Frontend build | PASS Vite 8.3.0 production build; generated ignored local assets only |
| Student routes | Registered route inventory inspected; 64 matching student paths including administrative Student paths |
| Blade compilation | PASS, temporary compiled views cleared |
| Route cache compilation | PASS, temporary route cache cleared |
| Pint | PASS dirty agent command; no PHP edits, so no whole-source reformat/certification implied |
| Whitespace | PASS git diff --check; new Markdown also checked for trailing whitespace/conflict markers |
| Composer audit | Zero advisories / abandoned-package findings |
| npm audit | Zero advisories of all severities |
| PHPStan no-regression | PASS complete file/identifier/message multiset: **255 → 255, zero added/removed**, duplicate counts retained; raw analyzer exit 1 for unchanged historical debt |
| Local schema | Read-only db:show, db:table Students/lesson_materials/resource_assignments/Bookings, migrate:status and schema metadata; 74 Ran/0 Pending (Stage 2 metadata correction), 10 generated columns/4 typed guards, zero existing lms_* tables; no migration executed |
| Production/browser/Bunny | Not executed; accepted production observations are reused as recorded baseline evidence; provider docs checked, no live library security certification |

Commands followed the repository's normal gates using the installed native Herd PHP executable to avoid the documented Windows php.bat regex interception. Reproduction:

```text
php vendor/bin/phpunit --filter '^(?!.*(MigrationACompatibilityTest|MariaDbConcurrencyVerificationTest|BusinessLifecycleConcurrencyTest|DevelopmentDataConcurrencyTest)).*$'
php vendor/bin/phpunit tests/Feature/ProductionHealthAndSchedulerTest.php --filter test_scheduler_heartbeat_execution_updates_setting
php vendor/bin/phpunit -c phpunit.concurrency.xml --filter '(MariaDbConcurrencyVerificationTest|BusinessLifecycleConcurrencyTest|DevelopmentDataConcurrencyTest)'
node --test tests/announcement-banner.test.mjs tests/admin-sidebar.test.mjs tests/clipboard.test.mjs tests/staff-quick-actions.test.mjs tests/resource-access.test.mjs tests/form-builder.test.mjs tests/telemetry.test.mjs
npm run build
php artisan route:list --path=student --except-vendor --no-interaction
php artisan view:cache --no-interaction
php artisan view:clear --no-interaction
php artisan route:cache --no-interaction
php artisan route:clear --no-interaction
php vendor/bin/pint --dirty --format agent
git diff --check
composer audit --format=json --no-interaction
npm audit --json
php vendor/bin/phpstan analyse --no-progress --error-format=json --memory-limit=1G -v
```

Historical intermediate-schema MigrationACompatibilityTest is excluded exactly as in the accepted current-schema gate; dedicated concurrency classes execute separately with their database-backed configuration. No test is changed/deleted or counted as passed through exclusion. PHPStan's initial compact formatter output truncated diagnostics; the verbose rerun supplies the complete comparison.

Full/isolated/concurrency execution outputs and discovery/static metadata are outside the repository under `C:/Users/e/Documents/Codex/2026-10-07/files-pasted-by-the-user-lms/work/`. Retained files include initial phpunit-stage1.log/xml, final phpunit-stage1-final.log/xml, concurrency-stage1.log/xml, phpstan-stage1-full.json, phpstan-comparison.json and schema-overview.log. The isolated rerun result is also retained in the chat execution record. Final and initial checkpoints stay distinct; isolated/focused/concurrency assertion totals are not added to the full-suite total.

## Architecture decisions

| Classification | Decision |
|---|---|
| REUSE | Canonical Students/auth, current tutoring/finance/time/availability, teaching authorities and private Resource delivery patterns |
| EXTEND | Explicit action policies/navigation, Resource/reference/private delivery, merge/privacy, visit notifications, read models/audit/settings/localization; workspace/Assign Learning links |
| NEW BOUNDED LMS DOMAIN | Course hierarchy/releases/access/grants/enrollment, progress/assessments/submission/review, private learning assignments, media adapter/device/playback leases, private annotations/bookmarks |
| SEPARATE BY DESIGN | LMS progress/access from typed tutoring rights/cash/delivery/hours; tutor-only preparation; private post-lesson feedback; public Resource gate from private references; course assessment from intake Forms/Games; private player notes from shared Educational Notes |

The plan enumerates every proposed new table's purpose/owner/PK/FKs/unique/index/null rationale/lifecycle/archive/history/authorization and existing-authority relationship. It reuses shared ContentRevision, AuditLog, Setting and StudentNotification stores instead of duplicating them. Published release anchors retain learner evidence; no migrations are created. Namespace LMS-* is proposed without final numbered capability IDs; all existing PUB/STU/SA IDs remain stable.

All **27** invariant contracts are mapped by number and touched stage. All **nine** PRELMS repaired defects are mapped to future failure mechanisms and regression tests. High-risk integration points include the occupied /student/learning route, tutoring-only roster selection, public-image sanitizer assumptions, Resource current-publication authority, strict Booking material ownership, preview read-only behavior, canonical merge/composite-owner collisions and fail-closed reset/export graph handling.

## Pre-existing constrained PARTIAL branches

The accepted **48 PARTIAL** dispositions retain their individual meanings in the final 153-ID ledger. They are constrained production branches with current-source isolated coverage, not 48 defects. Stage 1 does not upgrade/downgrade those dispositions, fabricate production mutations, or add the local timing failure to that acceptance ledger.

Examples requiring preservation:

| Existing limitation | Permanent IDs / implication |
|---|---|
| Fresh postfix Game GUI replay constrained by browser outage; live markup/server/event evidence retained | PUB-018; do not call duplicate-opening repair untested or re-add dual recorder |
| Disabled Reddit/emergency/promotion production branches kept disabled | PUB-019/021/024/026, SA-065/078; isolated coverage does not authorize publishing |
| Production log mail/example-domain addresses prevent inbox certification | STU-003, SA-014; no transport/credential change |
| Future meeting links remain hidden until real 15-minute window | STU-028, SA-051; no moved clock/lesson or room credential exposure |
| Credential/account/suspension changes deliberately limited to isolated tests | SA-015–018; existing factors/accounts preserved |
| Contact/Student merge and anonymization not executed on retained production cohort | SA-022–024; no cleanup/merge/privacy mutation authorized here |
| Purchase/payment/refund/adjustment/expiry/installment/renewal posting branches mainly isolated | SA-027–033/036; exact current persisted cash/rights still reconcile, not settlement integration |
| Staff manual booking/reschedule/delivery/no-show and further recurrence generation constrained | SA-040–042/048; no fabricated extra booking/attendance/automatic worker |
| Public Media/backup deletion, provider/configuration and new outbound delivery branches constrained | SA-063/077/081/084–086; no new communication or settings authorization |
| Country export supports all-country/date scope; unsupported country=EG not a certified filter | SA-073; preserve actual filter semantics |
| Reset/recovery/all-module export/missing-row restore tests remain isolated while tools default off | SA-088–094/096; safe selected export and identical no-op restore are distinguished from destructive production tests |
| Trusted-console classification has zero legacy production purchases; reviewed apply not performed | SA-098; no minute/price/name-based entitlement inference |

See the appended exact PARTIAL inventory for each accepted classification/limit. Existing manual rooms/recurrence/waitlist, visit-synchronized notifications, USD5 deliberate overpayment, per-currency money, nonconvertible single-allocation rights and missing-only restore are intentional business behavior, not unfinished LMS features.

## Known risks and future owner decisions

- The initial heartbeat freshness assertion was timing-sensitive; isolated and final full reruns pass unchanged. Retain that evidence and diagnose further if it repeats, without weakening the test or inventing a production scheduler defect.
- Define member audience meaning and fixed/relative/permanent window anchors before Stage 2 implements those policies; suggested membership is explicit manual LMS access, independent of tutoring finance.
- Decide release-upgrade/completion reopening and initial assessment policies before Stage 3/6 accepts them.
- Choose device/stream quotas, lease/token lifetimes, watermark identity and fullscreen/PiP/casting policy before Stage 5. Bunny iframe token protection, direct CDN protection, Student ownership and measured residual playback are distinct controls.
- Anonymous public content cannot be made individually private through an authenticated denial; protected/private learning must use an appropriate audience. External/YouTube security cannot be certified as Bunny protection.
- Local schema count is 107; recorded production count is 110. Inventory production-only/legacy tables and actual MariaDB constraints before Stage 2 deployment DDL; do not infer a schema defect from count alone.
- New Student-owned rows must join merge/privacy lifecycle adapters at introduction; unreviewed reset/export dependencies stay fail closed and destructive tools off.
- Log mail, s3_replication_failed and 255 accepted PHPStan findings remain recorded operations/static limits; no broad repair is authorized.
- Owner may choose a bounded global next-action interleave/attention thresholds later; current priorities and facts stay authoritative.

No invariant relaxation or material roadmap change is proposed. If any future decision requires one, stop at that stage and identify the exact contract/collision for owner resolution.

## Future stage checklist

- [x] Stage 1 discovery/integration/schema/security/regression blueprint written.
- [x] Accepted baseline and documentation-only differences established.
- [x] All 27 invariants, existing capability namespace and all nine repaired defects mapped.
- [x] Actual available local gates executed and their results/limits documented.
- [x] Stage 1 final fresh full current-schema gate all-green; initial heartbeat timing checkpoint retained separately.
- [x] Stage 2 — foundation/schema/LMS access; current member assumption and confirmed elapsed windows documented; MariaDB/lifecycle/concurrency/full gates passed.
- [ ] Stage 3 — Course Studio/mixed content/drafts/releases/preview.
- [ ] Stage 4 — My Learning/For You/Continue/player/private notes and bookmarks.
- [ ] Stage 5 — Bunny security/profiles/devices/leases/dynamic watermark, measured residual limits.
- [ ] Stage 6 — progress/completion/prerequisites/drip/quizzes/submissions/reviews.
- [ ] Stage 7 — tutoring/private Assign Learning/follow-up/timeline integration.
- [ ] Stage 8 — analytics/attention/visit notifications/permissions/settings/localization/export integration.
- [ ] Stage 9 — adversarial whole-system audit/remediation/acceptance, all 27 invariants and new permanent capabilities.
- [ ] Separate post-Stage-9 complete tutoring + LMS architecture/database review.

## Deliberate exclusions

No AI tutor/assistant, automatic transcription/subtitles, community/social platform, native LMS ecommerce/payment gateway, marketplace, SCORM/xAPI, complex gamification, webinars or revenue sharing. DRM remains future optional selection. No unapproved /6word progress bridge, proactive Student delivery, named-cohort cleanup or online settlement.

Stage 1 creates no LMS runtime, migrations, dependency changes, production hot edits/deployment, cleanup, owner/security changes or outbound messages. Existing authorities and accepted capability documents remain unchanged.


## Exact accepted PARTIAL inventory (48 IDs)

Derived from the final dispositions in PRODUCTION_APPLICATION_ACCEPTANCE_REPORT.md. These remain existing production coverage limits; no LMS defect status is inferred. SA-086 retains its ledger wording written before the final notification receipt; the report's later receipt confirms that final delivery 10/message 14 succeeded. Stage 1 authorizes no new message.

| Permanent ID | Accepted classification / limit |
|---|---|
| PUB-018 | TEST / TOOLING LIMITATION — Actual built-in quiz5/5/start/complete receipts are verified. After PRELMS-009, live public markup/server API proves one server opening, retained external handlers and invalid/nested/Coming Soon denials; browser control stalled, so a fresh postfix GUI click is not claimed. |
| PUB-019 | TEST / TOOLING LIMITATION — Configured native social links opened. The QA Reddit channel remains intentionally disabled; enabling it was not required or performed. Enabled Reddit rendering/tracking is isolated-test coverage. |
| PUB-021 | TEST / TOOLING LIMITATION — Announcement is disabled in actual production. Audience/window/DST/dismissal behavior is executed in isolated tests, without publishing a new live emergency notice. |
| PUB-024 | TEST / TOOLING LIMITATION — Real browser page/attention/Resource/booking/social/WhatsApp/game ingestion is verified with no duplicate client UUID. Disabled Reddit is exercised only in isolated tests; no new social configuration was enabled. |
| PUB-026 | TEST / TOOLING LIMITATION — Prepared promotion bars/modal/cards remain disabled with original Oct16–17 dates. Publication/window/countdown branches are isolated-test coverage; production clock/schedule were not changed. |
| STU-003 | TEST / TOOLING LIMITATION — Normal profile/secondary-email boundary is inspected and feature tests execute request, signed verification, expiry, reuse and identity conflicts. Example-domain QA addresses and production log mail preclude actual inbox delivery. |
| STU-028 | TEST / TOOLING LIMITATION — Actual future QA lessons hide the meeting link and show the15-minute reveal rule. Eligible reveal/expiry and wrong-owner cases are isolated tests; no production clock or original lesson was moved. |
| SA-011 | TEST / TOOLING LIMITATION — Active QA alerts and resolved-history projections are inspected; resolution/archive transitions are isolated tests, preserving the prepared operational alert. |
| SA-012 | TEST / TOOLING LIMITATION — Inbox and newly generated QA booking notices are inspected. Read/delete security and persistence are isolated tests; no production inbox deletion was performed. |
| SA-014 | TEST / TOOLING LIMITATION — Reset-link/credential completion is isolated-test coverage. Production mail remains log and existing credentials/MFA are preserved. |
| SA-015 | TEST / TOOLING LIMITATION — Actual enrolled QA Super password+fresh TOTP challenges pass. New enrollment is isolated-test coverage; no live authenticator credential was changed. |
| SA-016 | TEST / TOOLING LIMITATION — Regeneration/replacement/disable and one-use recovery races pass isolated tests. Existing owner/QA authenticators and recovery material are preserved. |
| SA-017 | TEST / TOOLING LIMITATION — Actual role-separated accounts and denied management routes are checked; create/update/delete authorization is isolated-test coverage. No additional privileged account or credential was created. |
| SA-018 | TEST / TOOLING LIMITATION — Suspension/restoration/security branches are isolated tests; genuine and prepared QA access states are retained. |
| SA-022 | TEST / TOOLING LIMITATION — Duplicate Contact review and merge races are isolated tests; directory/provenance is inspected. No production Contact merge was executed. |
| SA-023 | TEST / TOOLING LIMITATION — Canonical Student merge/typed provenance/teaching ownership regressions pass isolated tests. Six QA identities remain separate for owner review. |
| SA-024 | TEST / TOOLING LIMITATION — Privacy redaction/anonymization branches pass isolated tests; no preserved production or QA identity was anonymized. |
| SA-027 | TEST / TOOLING LIMITATION — Prepared classified purchases/grants and live credit consumption/restoration are reconciled. New purchase/grant form mutations are isolated-test coverage; no extra purchase was fabricated. |
| SA-028 | TEST / TOOLING LIMITATION — Independent discount calculations and current diagnostic purchase/payment window are checked; redemption/48-hour boundaries are isolated tests. Historical prices/provenance are unchanged. |
| SA-029 | TEST / TOOLING LIMITATION — Actual persisted payments and rendered receipts reconcile. Payment posting/settlement/idempotency races are isolated tests; no real funds or additional manual payment record was created. |
| SA-030 | TEST / TOOLING LIMITATION — Persisted bounded refunds/explicit forfeiture are independently reconciled. Refund posting/excess/stale/concurrency branches are isolated tests. |
| SA-031 | TEST / TOOLING LIMITATION — Prepared courtesy and allocation balances remain separate and reconcile. Adjustment mutation/reason/negative-unit protection is isolated-test coverage. |
| SA-032 | TEST / TOOLING LIMITATION — Existing real expiry eligibility is re-evaluated at current time. Extension/stale-date protection is isolated-test coverage; no expiry was extended for acceptance. |
| SA-033 | TEST / TOOLING LIMITATION — Actual installment forecast, payment/refund projection and statement are inspected; schedule creation/voiding/concurrency branches are isolated tests. |
| SA-036 | TEST / TOOLING LIMITATION — Prepared renewal points to a new purchase/grant without changing old facts. Renewal creation/idempotency is isolated-test coverage. |
| SA-040 | TEST / TOOLING LIMITATION — Public and Student normal booking paths execute against production; staff preselection/manual creation controller branches pass isolated tests. No extra staff-created production booking was added. |
| SA-041 | TEST / TOOLING LIMITATION — Production Student reschedule retains exact debit39/allocation5 across DST; staff reschedule variants and conflicting races pass isolated tests. Original fixture bookings were not moved. |
| SA-042 | TEST / TOOLING LIMITATION — Prepared delivered/no-show facts and financial independence are checked. New complete/no-show transitions and races are isolated tests; no further future lesson was marked delivered. |
| SA-048 | TEST / TOOLING LIMITATION — Original bounded manually generated recurrence plan/occurrences are reconciled. New generation/holiday/conflict/last-unit races pass isolated tests; no automatic worker or extra original occurrence was configured. |
| SA-051 | TEST / TOOLING LIMITATION — Original harmless room/provider snapshots and pre-reveal state are inspected. Assignment/overlap/reconciliation races pass isolated tests; no genuine meeting URL was exposed or changed. |
| SA-063 | TEST / TOOLING LIMITATION — Existing passive QA media/reference protection is inspected and exercised in isolated upload/delete tests. No production media deletion was performed. |
| SA-065 | TEST / TOOLING LIMITATION — FAQ/social configuration and disabled QA Reddit are inspected. Publication/configuration/unsafe URL cases are isolated tests; genuine social destinations remain unchanged. |
| SA-073 | TEST / TOOLING LIMITATION — Actual country CSV/XLSX files are parsed and equivalent for the offered all-country/date scope. An unsupported country=EG query does not filter this report and is not claimed as such. Exact later screen/file reconciliation is constrained by connectivity. |
| SA-077 | TEST / TOOLING LIMITATION — Actual public/business/privacy settings and clocks are inspected. Settings writes/validation are isolated tests; genuine settings remain unchanged except approved, exactly restored maintenance state. |
| SA-078 | TEST / TOOLING LIMITATION — Disabled announcement configuration is inspected. Audience/CTA/window/localization/security mutations are isolated tests; no new emergency message was published. |
| SA-081 | TEST / TOOLING LIMITATION — Protected native recovery archives are hash verified and managed-backup UI inspected. Managed create/delete and restore drill are isolated tests; no managed/genuine archive was deleted or production restore performed. |
| SA-084 | TEST / TOOLING LIMITATION — Existing verified bot/destination and disabled inbound commands are inspected. Switch/command mutations pass isolated tests; no polling, commands, destination or bot credential change. |
| SA-085 | TEST / TOOLING LIMITATION — Existing reminder rules/configuration and template/security branches pass isolated tests. No additional live reminder/digest/event message, scheduler or worker configuration is performed. |
| SA-086 | TEST / TOOLING LIMITATION — The verified outbound pathway delivers the single START; FINAL delivery will be recorded once. Other live destination-test buttons are not used because exactly two stage messages are authorized. |
| SA-088 | TEST / TOOLING LIMITATION — Real safe export rejects wrong phrase and requires current password/fresh existing MFA. Reset locks/recovery/stale-token/snapshot-failure branches are isolated tests; no production reset. |
| SA-089 | TEST / TOOLING LIMITATION — Actual feature-off denial and isolated analytics reset boundaries pass. Production analytics are retained for review; no reset executed. |
| SA-090 | TEST / TOOLING LIMITATION — All-Student graph/auth cleanup is isolated-test coverage. Six QA Students and all genuine protected rows remain for review; no reset executed. |
| SA-091 | TEST / TOOLING LIMITATION — Finance reset/dependent-booking history boundaries are isolated-test coverage. Production purchases/payments/refunds/ledger are retained; no reset executed. |
| SA-092 | TEST / TOOLING LIMITATION — Resource/category/file/history reset boundaries are isolated-test coverage. Genuine alphabet and QA private payloads remain unchanged; no reset executed. |
| SA-093 | TEST / TOOLING LIMITATION — Managed-only backup/reset/last-result lifecycle is isolated-test coverage. Protected native backups and all retained files are excluded from cleanup; no reset executed. |
| SA-094 | TEST / TOOLING LIMITATION — Real selected QA-only portable export/download/closure/hash/secret exclusion succeeds on Hostinger with proc_open disabled. Export Everything/limits/all-module selection is isolated-test coverage. |
| SA-096 | TEST / TOOLING LIMITATION — Real compatible import skips one identical profile with zero restored rows/files; conflicting overwrite and incompatible/secret archives are refused. Restore-missing/financial provenance is isolated-test coverage; no missing production row was fabricated. |
| SA-098 | TEST / TOOLING LIMITATION — Actual trusted console inventories zero legacy-unclassified purchases and rejects --apply without a reviewed manifest. Explicit classification/legacy protection is isolated typed-entitlement coverage; historical intermediate MigrationA tests are not part of the current-schema gate. |
