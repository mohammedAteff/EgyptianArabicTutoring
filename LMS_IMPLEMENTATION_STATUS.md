# LMS V1 Implementation Status

Date: 2026-10-08 (Africa/Cairo).

## Stage 8 disposition — completed implementation

**COMPLETE — IMPLEMENTATION COMPLETE — PENDING INDEPENDENT STAGE 9 ACCEPTANCE.** Learning analytics/operations, deterministic attention, canonical review queues, visit-based notifications, versioned settings, current permissions, safe generic/specific learner preview, security audit visibility and functional/performance hardening are implemented. The independent Stage 9 acceptance has not been performed; this does not call the full application verified. No production deployment occurred. Live Bunny certification remains pending under the owner's explicit mock-verification choice.

Stage 8 starts from complete Stage 7 commit `02bc5071cb0bc638e5a6ee493bcb190e44c3bf90`; the accepted pre-LMS application baseline remains `4296b21368cbc9c564d0cb7a85e29b2ae408e0ee`. Plan section 18 records final authority, event ownership, reporting scope, settings precedence, preview and deliberate exclusions. No new dependency or stop-condition owner decision was required.

### Stage 8 evidence

| Gate | Exact final evidence |
|---|---|
| Focused integration and UI fixture render | PASS **116 tests / 982 assertions** across LearningAnalyticsOperationsTest, LearningPreviewTest, LmsOperationalPermissionsSettingsTest, TutoringLearningIntegrationTest and StudentLearningTest |
| Complete current-schema backend | PASS **1361 tests / 11073 assertions** against explicit bolt_landing_test |
| Dedicated MariaDB/concurrency | PASS **61 tests / 340 assertions**, 10 classes with phpunit.concurrency.xml; includes three new simultaneous settings/completion/notification races |
| JS | PASS **43 tests / 0 failures**, including secure UUID fallback and missing-randomness failure; no dependencies changed |
| Frontend | PASS Vite production build; final CSS **130.10 kB**, app **25.89 kB**, TUS **60.41 kB**, HLS **574.74 kB**. Existing HLS >500-kB warning remains non-blocking |
| Static analysis | PASS no regression: the **same 255 accepted diagnostics**, compared as a complete path/identifier/message multiset with duplicate multiplicity; no suppression/configuration weakening |
| Blade/routes | PASS **256 compiled views**, each PHP-linted; view cache/clear and route cache/clear. All **15 new operational/settings/preview routes** remain in the guarded web pipeline with canonical capabilities and mutation CSRF/throttles/session blocking |
| Formatting/diff | PASS required dirty-PHP Pint plus working/staged whitespace checks |
| Audits | PASS fresh Composer **0 advisories / 0 abandoned packages** and npm **0 vulnerabilities** |
| Performance | Hub **15 queries** with one or 20 courses; a course report **15 queries** with one or 20 Students. Controlled MariaDB EXPLAIN uses lms_video_operations with **range**, not ALL, access |
| Functional UI | PASS nine current feature-rendered screens at **1280×900 and 390×844**, accessible names/captions, Arabic/English direction, navigation, disabled assessment preview, visible actor/subject/exit, locked/empty states and recovered player failure; **19 screenshots** in the delivery evidence |
| Local additive DDL | PASS local bolt_landing / MariaDB **10.11.18**, **batch 25**, **131 tables / 81 Ran migrations**, one non-unique video queue index; **14 generated columns / four typed finance guards exactly unchanged** |
| Local data boundary | Existing **3 Students / 0 Courses** remain. Learning content/access/evidence/security tables stay empty except the existing four protection profiles. Synthetic fixtures are confined to test/render workflows |
| Git/deployment | Reviewed **55 task files (22 new / 33 modified)** are committed/pushed through the established non-force workflow; exact SHA and full inventory are in the delivery receipt. Three pre-existing untracked production documents remain untouched/uncommitted. No production migration, credential change, hot edit, deployment or Stage 9 run |

The normal suite excludes the historical intermediate-schema group and the 10 dedicated concurrency classes; the dedicated classes run separately. Database suites are serial. The full backend includes booking, availability, holds, reschedule, package/funding/payment/refund/ledger, Resources/private files, maintenance, notifications, analytics, authentication/roles, Student Portal, public pages and timezone regressions, alongside Stage 2–7 LMS integration. Earlier assertions were not weakened: the one Stage 4 zero-LMS-event assertion was replaced by the newly authorized **exactly one Course Started and no other semantic events** check, while all existing notification/read-state assertions remain.

### Stage 8 analytics validation and limitations

Existing AnalyticsService/AnalyticsEvent have the five server-only event recorders documented in plan 18.1; the browser and generic track cannot forge them. Canonical start/completion/submit retries and concurrent completion persist one semantic identity. Metadata is finite opaque learner identity/IDs/hashes with existing server-derived country/IP hashing, and excludes permanent raw IP, names/emails, private titles, answers, feedback, URLs/referrers and video proof/security telemetry. Staff, preview, bot, synthetic and internal requests produce no normal learning events.

Canonical current state, not event counts or security heartbeats, drives progress/completion/results. Controlled data proves two retained Students with three access grants remain two enrolled/two started/one completed, **50% completion**, **50% average progress**, one completed lesson and one latest-visit learner; optional lessons and sorted curriculum ordering do not change completion identity. Changed requirement generations and evidence after the period end cannot complete the selected cohort. Unverified/suspended/future/archived/foreign-private cohorts are excluded. A revoked source removes current accessibility without deleting retained history.

Three submitted quizzes (one passed, one failed, one manual pending) yield **3 submissions / 1 pass / 50 graded average**. Two assignment revisions with one Needs Revision decision yield **2 submissions / 1 reviewed / 1 awaiting final review**. Numerators are subsets; completion rates cannot exceed 100. Six current video placements at 20/25/50/75/95/100% yield **started 6, 25% 5, 50% 4, 75% 3, threshold 2, average coverage 60.8%, average covered seconds 61**. A changed media generation removes stale coverage and Processing media cannot enter trusted video reports. Reports/queues make no Bunny calls.

The report explicitly uses current retained enrolled cohorts/current published curriculum through the selected end. Open-access learners without retained enrollment are excluded from that cohort, with normal hub/profile learning unaffected. Latest-visit metrics describe each retained Student/course's latest visit, not a reconstruction of all historical activity. Video ranges are current coverage for placements started in the period, not historical watch snapshots. No total study/attention time, precise completion-time claim, heatmap, overdue assignment date or parallel progress model is invented.

### Stage 8 operational rules, notifications and precedence

Attention uses editable elapsed-day defaults: inactive 14; stalled completion 14; three current-definition failures without a current pass; union access ending within five days below 30%; private follow-up neither visited nor canonically started for seven days after actual availability. Recent Student submissions count as activity, staff grading does not. Completion, visits/starts, passes, permanent overlap, changed definitions and loss of access resolve flags from canonical facts; Students appear once across courses. There is no AI or second alert-state writer.

Authenticated visits remain the only Student notification synchronization trigger. Existing assignment/review semantic keys and read state remain; private assignment/video readiness, Needs Revision, union expiry and eligible scheduled unlocks are added with bounded owner-scoped facts. Ready video is deduplicated across assignments. Future, expired, revoked, locked and foreign targets produce no new notification. Existing read history is retained. There is no proactive queue/worker, email/SMS/Telegram redesign or new notification subsystem.

Private versioned `lms.options` uses the current Setting architecture. Super Admin saves validate finite fields and concurrency versions and audit non-secret changes. New content captures the global completion default; prior explicit rules remain stable. Protection is explicit lesson → explicit course → optional safe catalog global fallback → Member; private learning keeps the strong Private fallback. Profile limits and encrypted Bunny credentials remain in their existing authority, with no duplicated storage or Student override.

### Stage 8 final permission matrix

| Capability | Super Admin | Admin | Assistant |
|---|---|---|---|
| Learning analytics and Student progress | Allow | Allow | Deny |
| Course create/edit/publish/archive/duplicate | Allow | Allow | Deny |
| Assign learning/private creation/grant/revoke/extend | Allow | Allow | Deny |
| Quiz and assignment review | Allow | Allow | Deny |
| Generic/specific Student preview | Allow | Allow | Deny |
| Student browser/session administration (existing teaching delegation) | Allow | Allow | Deny |
| Global device/stream limits and protection profiles | Allow | Deny | Deny |
| Bunny configuration/global LMS settings/security audit | Allow | Deny | Deny |

Fresh capability queries, existing role/authentication/account-activity/MFA boundaries and Student ownership remain. Specific preview adds manageTeaching and private-owner checks. Assistant navigation and crafted requests are denied. The existing Admin browser delegation was preserved rather than redefined. Actual Student self-service remains owner/session scoped.

### Stage 8 preview validation

Generic and Lucy-specific previews retain the real web Administrator and a 15-minute actor/session context; there is no Student login, guard substitution or fake enrollment. Current entitlement/drip/prerequisite/progress projection, disabled quiz/assignment interactions, dedicated private material routes, persistent subject/actor/exit banner and initiation/valid-exit audits are verified. Regenerated/foreign-actor contexts remain unusable and can be safely discarded/restarted/exited in the caller's own session, without misattributing an old actor's exit. Demoted/suspended/forbidden staff, foreign lessons/media/materials, expired contexts and lost access fail closed.

Snapshots of **14 tables** prove no changes to enrollment, grants, learning assignments/visits, notes/bookmarks, progress/watch rows, attempts/submissions, authorized devices, Student leases, notifications or analytics. Dual real guards cannot perform Student-area actions while preview is active; actual Student session proof is unchanged. Personal notes, actual answers/body/feedback and tutor preparation are excluded.

Bunny preview uses separate authenticated Staff authorization, provider mock verification/reconciliation and narrow HLS signing. It does not reserve a Student device/stream/watch row, even with occupied allowances. Tokens last at most **120 seconds**, further clipped to real access end (the controlled 30-second source remains 30 seconds). Proof closure, changed policy/provider/media, source revocation and Processing media refuse renewal/play. Network/provider work remains outside the final locking/signing transaction. Exiting prevents fresh authorization; an issued CDN capability can remain usable until its already bounded expiration. Ordinary page loads make no provider calls. This is mock/local proof, not live Bunny certification.

### Stage 8 performance, UI, invariants and release boundary

Fresh graph/enrollment/grant/evidence projections remove per-course/per-lesson access/progress rereads from the hub, curriculum modules, profile/For You, timeline and visit facts. Cohort reports stream 200-Student batches and attention aggregates attempt facts without loading answers. Direct delivery and writes keep fresh canonical authorization/locks; no cross-request entitlement cache exists. One additive provider/status/id index supports the new global media queue; existing status/block, enrollment and progress indexes remain. No table, rollup, backfill or broad application/database cleanup was added.

The UI pass corrected narrow mobile filters, verified accessible labels/captions and Arabic/English direction, and exercised preview loading/error/disabled/locked states. A local HTTP player UUID error was fixed using secure random bytes and a recoverable catch/finally path; uploads now clearly explain their secure HTTPS context requirement. Existing production TLS checks are unchanged. Screenshots use isolated synthetic test HTML with submissions, navigation to real endpoints and telemetry disabled. All temporary public review files were removed, the viewport/tabs reset, and the final build assets matched the reviewed files. The known HLS bundle-size warning and the exact accepted PHPStan baseline remain deliberate disclosed limitations.

All **27 PRE_LMS_INVARIANTS** retain their meaning. Directly touched integration concerns are **1–5, 7–8, 17 and 19–24**: identity/ownership/session/roles/security, time interpretation/display, append-only history, Resources/private materials/teaching, notification synchronization, analytics privacy and audit. **6, 9–16, 18 and 25–27** retain unchanged writers and regression proof for booking storage/availability/locking/holds/funding/reschedule/cancellation/money/installments/meeting rooms/recurrence/waitlist/destructive tools. AI, transcription, community, certificates, ecommerce, SCORM/xAPI, Student early drip overrides, a proactive notification architecture and a visual rebrand remain excluded.

Deployment state: local additive index applied only after local database/version/pending-SQL checks; source is tested/committed/pushed, with exact SHA in the delivery receipt. No production backup/deploy/migration/hot edit/credential configuration or production retest is claimed. **Stage 9 independent acceptance remains pending and was not started.**

## Stage 7 disposition — completed implementation

**COMPLETE — Stage 7 implementation and local verification.** The Student profile now offers Assign Learning for existing courses, lessons, Resources and targeted quizzes/assignments, plus a quick private item/video/quiz/assignment editor. Optional tutoring-session follow-up is independent of canonical session completion. Profile Learning, Student For You, current educational progress/review needs, eligible visit-synchronized notifications and a bounded pedagogical timeline reuse the existing Stage 2–6 authorities. No production deployment or Stage 8 work was performed. Live Bunny certification remains pending by the owner's explicit mock-verification choice.

The mandatory 15-row overlap matrix was recorded before implementation in plan section 17.1: Lesson Workspace, profile progress, existing notifications and the read timeline are **EXTENDED**; Resource/private-file and LMS engines are **REUSED**; Homework, milestones, pronunciation/errors, tags, tutor preparation and post-lesson feedback are **KEPT SEPARATE**; learning plans, Educational Notes and the unchanged Student next-action priority are **LINKED**. No tutoring-only record is imported into private learning.

### Stage 7 verification receipts

| Gate | Actual evidence |
|---|---|
| Initial authority compatibility | PASS **82 tests / 529 assertions**, including teaching, Homework, Resources/private files and Stage 2–6 integration |
| Final canonical evidence/security focus | PASS **72 tests / 548 assertions** including the 33 Stage 7 cases, current learning/watch/media guards, retakes and stable grade/review dates after merge |
| Full current-schema regression | PASS **1,317 tests / 10,661 assertions**, all passed, direct runner exit 0, **374.944 seconds**; includes auth/MFA/roles, Student Portal, booking/reschedule/cancellation, typed entitlement/provenance, finance/installments, Resources/private files, notifications, privacy/lifecycle, Stage 5 security and Stage 6 evidence |
| Full dedicated MariaDB concurrency/lifecycle | PASS **58 tests / 319 assertions**, all passed, direct runner exit 0, **61.296 seconds**; nine dedicated classes, including four new simultaneous assignment/private-create/reversed-bulk/merge races |
| Final UI/Bunny authoring regression | PASS **58 tests / 368 assertions**, direct runner exit 0; rerun after the final private-video wording change. Earlier isolated UI export also passed **1 test / 13 assertions** |
| JavaScript | PASS **41 tests**, zero failures/skips; explicit application test files, including protected video, learning progress/player, Resource access, editor, sidebar and telemetry regressions |
| Final frontend build | PASS after disposable review files were removed; CSS **115.97 kB**, app JS **25.35 kB**, TUS **60.41 kB**, HLS **574.74 kB**. Existing HLS chunk-size warning remains nonblocking; no dependency or limit change |
| Browser/responsive | PASS **1280px desktop / 390px mobile** on Assign Learning, private creation/editor, profile Learning and Student For You; Arabic/English readable, no horizontal overflow, Resource duration fields disabled, working private-quiz controls and timeline disclosure. Ten screenshots retained. Synthetic HTTP-rendered Blade/Alpine fixtures only; forms/navigation/network/telemetry disabled, no real login/provider/microphone use. Four HTML fixtures/runtime removed, temporary tab closed and viewport reset |
| PHPStan no-regression | PASS exact **255 → 255** path/identifier/message diagnostic multiset, including duplicate diagnostics; zero added/removed entries. No ignores, baseline/configuration changes or new suppressions. Analyzer exit remains nonzero for the accepted existing baseline |
| Blade/routes | PASS **247 compiled templates PHP-linted without errors**, view cache/clear and route cache/clear. All **12** new Admin/Super routes resolve within the existing guarded web pipeline, with canonical policies, CSRF and mutation throttles |
| Pint/whitespace | PASS required dirty-PHP Pint and working/staged whitespace checks; no changed dependencies |
| Dependency audits | PASS fresh Composer audit with cache disabled: **0 advisories / 0 abandoned packages**; npm **0 vulnerabilities**. A prior Packagist timeout was resolved by the fresh successful run |
| Local additive migration | PASS verified local **bolt_landing / MariaDB 10.11.18**, **batch 24**, **131 tables / 80 Ran migrations**, six nullable columns, two paired CHECK constraints and a restricting block foreign key; **14 generated columns / 4 finance guards unchanged** |
| Local data boundary | Existing **3 Student rows / 0 Course rows** unchanged; all learning content/grant/evidence tables remain empty and the four existing protection profiles remain. Synthetic fixtures exist only in the dedicated test workflow |
| Release scope | Reviewed main commit is pushed without force; exact SHA and inventory are in the delivery receipt. No production schema/credentials/backup/hot edit/deployment/retest. Stage 8 remains outside scope |

The ordinary suite excludes the historical intermediate-schema group and the nine dedicated concurrency classes. The dedicated classes run separately with phpunit.concurrency.xml. Every database suite runs serially against explicit bolt_landing_test; normal local DDL is applied only after bolt_landing identity/version and the pending SQL are verified. The accepted pre-LMS application baseline remains `4296b21368cbc9c564d0cb7a85e29b2ae408e0ee`; Stage 7 implementation starts from the complete Stage 6 commit `2809f9e29736b9ed8631b697b6fc650e76e0a102`.

### Stage 7 architecture and ownership

LmsTutoringAssignments authorizes fresh Admin/Super teaching and LMS capabilities, locks the verified Student, validates owned confirmed/completed Booking context and delegates to canonical writers. Original UUID/fingerprint retries preserve original terms; a different UUID for an existing active/scheduled assignment with the same scope/block/Booking returns that assignment. Expired/revoked rights require a new assignment or explicit versioned grant operation. Relative days are elapsed 24-hour periods, fixed wall times use Business Timezone with DST gap/fold rejection, UTC persists and Student display timezone remains authoritative for presentation.

Targeted quizzes/assignments store a current block identity and navigate directly to the Stage 6 assessment, using the established lesson-scoped grant. They do not create block-only entitlement semantics. Resources reuse their existing published-source, owner/session visibility and delivery rules: timed LMS windows are refused, withdrawal hides only the owned assignment and the source survives. Bulk existing catalog/Resource assignment is bounded to 25 Students, locks them in order, creates canonical individual rights and rolls back on an ineligible member. Private learning cannot be bulk-shared to another owner.

Quick private creation uses one automatically managed Course Studio section/lesson/release graph behind a single-item editor. Creation keys/fingerprints make simultaneous/replayed creation deterministic. Only explicit entered content/instructions are authored; preparation, Educational Notes, workspace private notes/material history and feedback are never imported. Bunny reuses direct resumable upload, Ready reconciliation and the strong Private profile; processing, failed and foreign media fail closed. Private defaults use Stage 6 manual completion, video 95%, quiz pass or assignment approval. No new dependency or media subsystem is introduced.

Canonical Booking completion and its transaction/controller are unchanged. The optional follow-up link is exposed after delivery and remains available later. Tests cover completion without follow-up, immediate/later Course/Resource assignment, provider/validation failure without changing the delivered snapshot, and repeated public confirmation preserving Delivered with exactly one completion event. LMS access/progress cannot debit typed tutoring rights, deliver a session or write finance; nine existing tutoring/financial tables must remain byte-identical through the mixed assignment/private/revoke/profile scenario.

Profile/For You projections use current Stage 6 completion and assessment evidence; a prior current-definition passing attempt keeps completion after a failed retake, while the latest result remains visible. Instructions/links require current canonical access. Scheduled and Unavailable states complement New/In Progress/Completed/Expired. Existing Homework/plan navigation and questionnaire → Homework → Resource → booking → teaching next-action priority remain unchanged.

The timeline reads bounded canonical session, Resource, assignment, lesson/video completion, quiz pass, submission/review and course-completion facts. Semantic keys and canonical dates remain stable across merges; sorting uses time and a stable key tie-breaker. No audit/security/heartbeat log is imported, and no answers, submitted bodies, feedback bodies or tutor preparation are projected. Notifications retain existing authenticated-visit synchronization, owner/event keys, read state and mounted routes, with generic eligible learning/review messages. No proactive worker or aggregate analytics/attention architecture is added.

Migration `2026_10_08_035746_add_tutoring_learning_context_to_lms` adds nullable private creation metadata to Course, nullable block ID/kind to LearningAssignment, graded_at to QuizAttempt and reviewed_at to AssignmentSubmission. Pair constraints fail closed for incomplete/unsupported context. New canonical grade/review dates avoid merge-induced timestamp drift. Legacy rows with null dates use their previous updated_at; no false historical instant is backfilled. Down migration deliberately requires a reviewed forward fix to retain history. Current private lifecycle merge/anonymization tests retain block context, move ownership and withdraw/redact erased private learning.

### Stage 7 invariant dispositions

All 27 meanings remain. “Exercised” identifies a directly used Stage 7 integration; “Preserved” identifies unchanged authority covered by the full regression gates. Existing production PARTIAL dispositions remain unchanged.

| Invariant | Stage 7 disposition and evidence |
|---|---|
| I1 Student identity/normalization | **EXERCISED:** fresh verified canonical Student required; merged/unverified/suspended authority and authentication regressions retained |
| I2 Student ownership | **EXERCISED:** Lucy/Sarah isolation and Student/session/course/lesson/Resource/private item/Bunny/assessment/file tampering fail closed |
| I3 Authentication/session boundaries | **EXERCISED:** existing Student/web guards and session proof; no forged/extended session, native auth/Portal/playback tests pass |
| I4 Staff roles | **EXERCISED:** Admin/Super policies on every writer/read; Assistant and suspended staff denied, no hidden-button authority |
| I5 Super Admin security/TOTP | **PRESERVED:** existing guard/MFA pipeline and security workflow; full auth and dedicated concurrency tests pass |
| I6 Canonical UTC Booking storage | **EXERCISED:** owned session instant is read only; delivered Booking snapshot survives assignment/provider failure |
| I7 Business Timezone interpretation | **EXERCISED:** fixed LMS form instants use TimezoneService; both DST gap/fold cases rejected |
| I8 Student timezone display | **EXERCISED:** learning dates use existing Student display timezone without rewriting Booking snapshots |
| I9 Availability authority | **PRESERVED:** no slot/notice/buffer/rule writer; availability/booking regressions pass |
| I10 Booking locking/concurrency | **EXERCISED:** Student → owned Booking → Course/target order; sorted bulk and merge races pass |
| I11 BookingHold behavior | **PRESERVED:** no hold lifecycle edits; ordinary and dedicated booking races pass |
| I12 Typed one_hour/two_hour separation | **EXERCISED:** learning creates no tutoring minutes/rights; byte-identical mixed-workflow proof and typed regressions pass |
| I13 Exact funding provenance | **PRESERVED:** no ledger/provenance writer; 14 generated columns and four finance guards unchanged, funding regressions pass |
| I14 No reschedule second debit | **PRESERVED:** reschedule authority unchanged; full ordinary/dedicated regression passes |
| I15 Exact cancellation restoration | **PRESERVED:** cancellation/refund authority unchanged; completed public confirmation remains Delivered |
| I16 Purchase/payment/refund reconciliation | **EXERCISED:** mixed workflow requires financial tables unchanged; full finance regression passes |
| I17 Append-only history | **EXERCISED:** canonical access/audit facts and retained assessment evidence; expected-version retry/merge history preserved |
| I18 Installments versus payments | **EXERCISED:** no installment/payment side effects; byte-identical table proof and finance/lifecycle regressions pass |
| I19 Resource authority | **EXERCISED:** canonical published source assignment/private delivery; withdrawal never deletes source |
| I20 Private lesson/material files | **EXERCISED:** reused containment/hash/ownership and secure upload/delivery; foreign attachment and Student access denied |
| I21 Teaching ownership/sharing | **EXERCISED:** Homework/plans/preparation/feedback retain sharing and writers; private notes never imported |
| I22 Student notification synchronization | **EXERCISED:** authenticated visits only; owner keys, eligibility and retained read state tested |
| I23 Analytics/privacy | **EXERCISED:** no aggregates/raw log import; canonical merge/anonymization and private redaction pass |
| I24 Audit expectations | **EXERCISED:** existing writer audits retained; timeline excludes audits/security/heartbeats and uses semantic facts |
| I25 Manual meeting-room model | **PRESERVED:** no provider/room lifecycle changes; existing booking/workspace regressions pass |
| I26 Manual recurrence/waitlist | **PRESERVED:** no recurrence/waitlist writes from learning; existing planning/lifecycle suites pass |
| I27 Destructive-tools fail-closed boundary | **EXERCISED:** lifecycle privacy/merge and development-data safety/concurrency regressions pass; no reset/import/cleanup performed |

### Stage 7 file inventory

**43 task files:** 15 new and 28 modified, including only the two requested root documents. The three pre-existing untracked Hostinger/proposal documents are untouched and excluded from the commit.

| Paths | Result |
|---|---|
| app/Domains/Lms/Services/{LmsTutoringAssignments,LmsTutoringReadModel,LmsTeachingTimeline,LmsLearningNotifications}.php | New integration facade and bounded canonical projections |
| app/Domains/Lms/Services/{LmsAccessOperations,LmsAccessService,LmsProgressService,LmsQuizService,LmsAssignmentService}.php | Optional assessment target and stable current completion/grade/review dates |
| app/Domains/Lms/Models/{Course,LearningAssignment,QuizAttempt,AssignmentSubmission}.php | Opaque creation context, block relationship and canonical date casts/properties |
| app/Domains/Students/Services/{TeachingRecordService,StudentTeachingReadModel}.php; app/Domains/Students/Models/ResourceAssignment.php | Canonical Resource withdrawal/read reuse and eligible visit-notification extension |
| app/Http/Controllers/Admin/{AssignLearningController,PrivateLearningController,StudentController,StudentTeachingController}.php; app/Http/Requests/AssignLearningRequest.php; app/Http/Controllers/Student/LearningController.php | Guarded profile/private/session workflow, Business Timezone normalization and Student For You |
| database/migrations/2026_10_08_035746_add_tutoring_learning_context_to_lms.php; routes/web.php | Six nullable columns, context constraints/restricting FK and 12 guarded routes |
| resources/views/admin/students/{assign-learning,private-learning,_learning,_learning-availability,show,teaching}.blade.php | Quick assignment/private editor, profile Learning and teaching links |
| resources/views/admin/bookings/{show,lesson-workspace}.blade.php; resources/views/components/student-quick-actions.blade.php | Optional delivered-session follow-up and prominent authorized profile action |
| resources/views/admin/lms/courses/{_block-form,_videos}.blade.php | Reused assessment/content/Bunny controls with optional private-item routes/copy; normal Studio retained |
| resources/views/components/learning-timeline.blade.php; resources/views/student/learning/index.blade.php; resources/views/student/teaching.blade.php | Bounded timeline, For You and explicit Homework/plan navigation |
| tests/Feature/{TutoringLearningIntegrationTest,TutoringLearningConcurrencyTest}.php; tests/Feature/Concurrency/booking_worker.php | 33 integration cases and four independently gated MariaDB races, using canonical services |
| LMS_IMPLEMENTATION_PLAN.md; LMS_IMPLEMENTATION_STATUS.md | Pre-implementation overlap review, actual contracts, invariant mapping and final verification |

The new Stage 7 cases add **37 tests / 261 assertions** across the ordinary and dedicated release suites. A real course-completion date-selection issue found during focused verification was corrected by selecting progress rows using lesson identity rather than Eloquent progress primary keys. Quiz retake projection and merge-induced grade/review date drift were also corrected and explicitly tested; no prior authorization, private-file, tutoring, funding or financial assertion was removed or weakened. Final wording changes were followed by the focused 58-test UI/Bunny run and all 247 compiled-template lints.

## Stage 6 disposition — completed baseline

**COMPLETE — Stage 6 implementation and local verification.** Canonical lesson/module/course completion, separate protected-video watched ranges/resume, prerequisite/drip gates, all six quiz types and five private assignment submission types are implemented. Course Studio authors the bounded rules and assessments; Students see current progress, assessment/revision states and a real Completed section. Existing tutoring Homework/progress, typed one_hour/two_hour rights, booking delivery and finance retain their authority. No Stage 7 or production deployment was started. Live Bunny certification remains pending by the owner's explicit mock-verification choice.

The owner confirmed optional lessons excluded from completion, all configured requirements combined with AND, ordered questions with answers after grading, independent modules unless lesson prerequisites are authored, and no Student early-drip override. Plan section 16 records the actual schema, policies, trust/history/storage boundaries and Homework decision **KEPT SEPARATE**. This supersedes the Stage 1 optional Homework bridge proposal and earlier deferrals; historical Stage 1–5 evidence remains below.

### Stage 6 verification receipts

| Gate | Actual evidence |
|---|---|
| Focused progress/watch/author/student | PASS **86 tests / 701 assertions**, followed by **73 tests / 451 assertions** including the strengthened malformed-assessment guard and corrected historical expectations |
| Final current-media/playback regression | PASS **39 tests / 299 assertions** including processing/withdrawn assets, unsafe fallback and a withdrawn block; an earlier active lease cannot begin or update watch evidence and rejected requests leave prior evidence unchanged |
| Full current-schema regression | PASS **1,284 tests / 10,425 assertions**, all passed, direct runner exit 0, approximately **374 seconds**; includes the final four media-state regressions, Stage 5 provider/playback/security, canonical auth/Portal/private files, tutoring Homework/teaching, booking/typed funding/finance, lifecycle/privacy and existing Livewire regressions |
| New MariaDB learning evidence | PASS **6 independent simultaneous races / 29 assertions**, plus **2 after-commit private-file erasure tests / 9 assertions**; all eight included in the final dedicated suite |
| Full dedicated MariaDB concurrency/lifecycle | PASS **54 tests / 294 assertions**, all passed, direct runner exit 0, approximately **60 seconds**; eight concurrency classes with independently gated processes, plus the new real-commit erasure cases |
| Final Student UI/assessment regression | PASS **66 tests / 518 assertions** with isolated HTTP-rendered fixtures; final assessment fields, current-version action availability, Completed review link and own history tested |
| JavaScript | PASS **41 tests**, zero failures/skips; finite position/codec validation, actual player proof/batching/seek/stop hooks, heartbeat separation, explicit recording/track cleanup/size cap and form handoff, alongside existing regressions |
| Frontend build | PASS Vite after temporary review files were removed; CSS **115.91 kB**, app JS **25.35 kB**, TUS **60.41 kB**, dynamic HLS **574.74 kB**. Existing HLS chunk warning is nonblocking; no limit or policy weakened |
| Browser / responsive | PASS **1280px desktop / 390px mobile** on quiz, assignment/history/audio fallback, Hub/Completed, course, Studio and pending manual review; no horizontal overflow, readable Arabic/English, working add-question/type/rule controls, guarded recording fallback. Synthetic Blade/Alpine fixtures with current built assets; forms/navigation/telemetry disabled, no genuine session, provider or microphone use. Twelve fixture files/tab removed and viewport reset |
| PHPStan no-regression | PASS exact path/identifier/message diagnostic multiset **255 → 255**, zero added/removed entries including duplicates; no ignore/baseline/configuration changes. Analyzer exit remains nonzero for its unchanged accepted baseline |
| Blade / routes | PASS view cache/clear, **242 compiled templates PHP-linted without errors**, final HTTP-rendered view regressions and route cache/clear. All **8 Student evidence + 4 Admin/Super review routes** resolve with canonical guards, CSRF and write throttles |
| Pint / whitespace | PASS required dirty-PHP Pint and working/staged diff checks; formatting applied, no changed dependencies |
| Dependency audits | PASS Composer **0 advisories / 0 abandoned packages**, npm **0 vulnerabilities** |
| Local additive migration | PASS verified local `bolt_landing` / MariaDB **10.11.18**, **batch 23**, **131 application tables / 79 Ran migrations**; +4 evidence tables/+1 migration, **14 generated columns / 4 finance guards unchanged** |
| Local data boundary | Existing **3 Student rows / 0 Course rows** unchanged. All four new learning tables empty; no real Student, assessment, course, payment, package, provider, browser or playback record created for testing |
| Deployment/live scope | No production inspection, backup, credential configuration, hot edit, deployment or retest. Live Bunny checks remain pending under the Stage 5 accepted boundary. Stage 7 is not started |

The ordinary suite excludes historical intermediate-schema MigrationACompatibilityTest and the eight dedicated concurrency classes. All eight dedicated classes are separately run using phpunit.concurrency.xml; database suites run serially against explicit `bolt_landing_test`. The accepted pre-LMS application baseline remains `4296b21368cbc9c564d0cb7a85e29b2ae408e0ee`.

The first full run passed 1,278 of 1,280: two Stage 2/3 expectations still called quiz/assignment unsupported. The Studio test now positively checks the implemented controls while retaining retired Bunny-placeholder rejection. The foundation test retains database rejection of malformed `{}` quiz payloads through a stricter assessment envelope guard. A full rerun passed all 1,280; the final full run with four additional current-media cases passes all 1,284. Student Hub's previous “no 0%” presentation expectation was superseded by actual canonical Not Started progress, while its no-progress-write-on-listing assertion remains. No ownership, authorization, private-file, finance or concurrency assertion was removed or weakened.

Focused tests also caught and corrected a real watch-timestamp precision issue: second-only Eloquent serialization could turn jitter into credited coverage. Both the schema and model now preserve milliseconds, and synthetic fast samples earn zero coverage. Test setup issues involving absolute session deadlines, generated Course versions and synthetic finance table names were repaired without weakening server contracts. Upload transactions do not retry external file writes; after-commit privacy tests prove successful deletion and inaccessible recovery metadata on failure.

### Stage 6 canonical behavior and security

LmsProgressService is the completion authority. Current required lessons form the course/module denominator; optional lessons do not complete a course and scoped access cannot claim a whole course complete. Completion methods are deterministic AND, with every current block of each required type needing its evidence. Exact retries and first completion are idempotent. Visits, note/bookmark actions and video security renewal cannot mark completion. Expiry/revocation preserves evidence; renewed access restores unchanged progress. Changed rules/content/media create a new current requirement generation while retaining previous timestamps, attempt definitions/answers/marks and assignment/revision snapshots.

LmsLearningGate independently enforces same-course acyclic prerequisites and immediate/elapsed-day/fixed scheduling on direct lesson, JSON, file, playback, assessment and write paths. Relative days are 86,400-second periods from the retained canonical enrollment anchor. Author fixed wall times use TimezoneService/Business Timezone, reject DST gap/fold ambiguity and store UTC; Student dates use their existing display timezone. A due drip date never overrides expired entitlement. There is no Student early override.

Watch begin/sample require the canonical Student/session, current entitlement/gate, ready and safely usable current block/media, and owned current browser/lease proof. Processing/withdrawn assets, unsafe fallback and a withdrawn block fail closed even under an earlier active lease, without changing saved evidence. A separate random hashed watch proof and exact sequence bind updates. Resume position does not imply coverage. Ten-second batches credit only bounded continuous 1x movement against server elapsed time; skipped, stale, backward, paused and duplicate coverage cannot inflate percent. Jitter allowance earns no extra time. All current required protected videos must meet the unrounded threshold. Existing Stage 5 security renewal, immediate teardown, capability TTL, quotas and watermark remain intact. Browser telemetry is not proof of human attention; valid real-time imitation, screen capture and residual CDN capabilities retain the documented Stage 5/V1 limits.

Quiz reservation, submission and review choose Student/attempt number/score/pass server-side. All six types use bounded validation and retained definitions; written questions require a version-checked Admin/Super grade. Correct keys stay out of Student HTML/JSON until a graded snapshot's retained policy allows review, and never-review remains private. Arabic letter/diacritic distinctions remain, with explicit accepted forms and conservative whitespace/case normalization. Submitted answers and terminal grades cannot be overwritten through a changed payload or stale review.

Assignment submission/review is a separate canonical LMS domain. Five allowed types, state/version checks, semantic UUID retries, immutable useful revision history, finite MIME/extension/size checks and random private paths apply. Owner/reviewer downloads recheck identity, containment and SHA-256 and force attachment/nosniff/no-store. Student B cannot read or write A's attempts, evidence, submissions or files; client Student/score/pass/percent/approval/path/attempt-number fields have no authority. Optional recording is explicit, bounded and becomes a private normal upload; no automatic upload or unsafe media pipeline exists. Privacy erases instructional answers/bodies/feedback and active proofs, deletes submitted bytes after commit and retains inaccessible opaque cleanup metadata only if deletion fails.

No educational percentage/time creates tutoring minutes, Bookings marked delivered, typed package entitlement, package debit, payment, refund, installment or funding provenance. The explicit mixed-completion regression requires those tutoring/finance tables to remain identical. Existing tutoring progress summary, Homework, learning plans/milestones, pronunciation/errors, educational notes, private tutor preparation and post-lesson feedback retain their writers and sharing semantics. No Stage 8 aggregation or proactive notification delivery is added; bounded completion/assessment transitions use the existing audit authority.

### Stage 6 touched invariants and file inventory

All 27 pre-LMS meanings remain. Directly exercised integration surfaces are I1–3 identity/ownership/auth, I4–5 staff authority, I6–8 UTC/Business/Student timezone, I10 ordered locks, I12–18 funding/finance/history, I19–21 private files/Resource/teaching, I22 existing Portal notifications, I23–24 privacy/audit and I27 destructive-tools fail-closed boundaries. Availability, cancellation/refund, calendar/recurrence/meeting-room and accepted production PARTIAL dispositions are preserved by unchanged authority and regression evidence, not upgraded by local tests.

| Task paths | Result |
|---|---|
| app/Domains/Lms/Models/{LessonProgress,VideoProgress,QuizAttempt,AssignmentSubmission}.php; app/Domains/Lms/Models/Lesson.php | Guarded canonical evidence, hidden proofs/answer snapshots, typed timestamps and nullable lesson rules |
| app/Domains/Lms/Services/{LmsLearningDefinition,LmsLearningGate,LmsProgressService,LmsQuizService,LmsAssignmentService}.php | Bounded definitions, educational gate, derived progress, trusted scoring/review, revision and watch engines |
| app/Domains/Lms/Services/{CourseStudioService,LmsContentService,LmsAccessService,StudentLearningService,ProtectedPlaybackService,LmsStudentLifecycle}.php | Author/draft/publication/key mapping, secure current projections, direct-path locks, session-bound watch proof and canonical merge/privacy |
| app/Domains/Booking/Services/LessonMaterialService.php | Reused private-file containment/hash authority extended for finite private Student submissions |
| app/Http/Controllers/Student/{LearningEvidenceController,LearningController}.php; app/Http/Controllers/Admin/{LearningReviewController,CourseStudioController}.php; app/Http/Controllers/LmsFoundationController.php | Scoped evidence/assessment/review, canonical learning start, Business Timezone interpretation and safe JSON |
| database/migrations/2026_10_07_232837_create_lms_learning_evidence_tables.php; routes/web.php; bootstrap/app.php | Additive schema/checks/unique identities, 12 guarded routes and watch-token flash exclusion |
| resources/views/admin/lms/courses/{_learning,_assessment,_block-form,edit,preview}.blade.php; resources/views/admin/lms/reviews/index.blade.php | Minimal learning/quiz/assignment authoring, read-only preview and pending review queue |
| resources/views/student/learning/{assessment,course,curriculum,index,lesson,protected-video}.blade.php | Canonical progress/locks/next/Completed, own evidence/history, conditional submission fields and watch status |
| resources/js/{learning-progress,protected-video,app}.js; tests/learning-progress.test.mjs | Separate watch/recording hooks, safe stop handoff, batching, fallback/cap/cleanup tests |
| tests/Feature/{LmsLearningEvidenceTest,LmsWatchProgressTest,LearningEvidenceConcurrencyTest}.php; tests/Feature/Concurrency/booking_worker.php | New behavior/security/isolation/snapshot/timezone/finance/merge/privacy tests and genuine independent races |
| tests/Feature/{CourseStudioAssetSecurityTest,LmsFoundationTest,StudentLearningTest}.php | Superseded unavailable-assessment/empty-progress expectations replaced with current positive behavior and retained safety guards |
| LMS_IMPLEMENTATION_PLAN.md; LMS_IMPLEMENTATION_STATUS.md | Actual Stage 6 contracts, Homework decision, evidence, boundaries and release status |

Established delivery is test → commit → non-force push on main. The exact committed/pushed SHA and complete path inventory are in the output delivery receipt and final chat; this file cannot contain its own future commit hash. Only Stage 6 files and the two requested root records are staged. The three pre-existing untracked Hostinger/proposal documents remain untouched. Production deployment needs a separate authorized release with protected backup, exact reviewed application/assets, additive DDL/guard/storage preflight and production access/private-file/Portal/Bunny regression. Documentation parity alone does not authorize deployment.

---
## Stage 5 disposition — historical receipt, superseded for Stage 6

**COMPLETE — Stage 5 implementation and local/mock verification. Live Bunny certification remains pending by the owner's explicit choice.** Course Studio now manages generic video assets and direct resumable Bunny uploads, honest processing and reference-safe media lifecycle. Protected playback remains Laravel-authorized with short CDN capabilities, fresh renewal, browser controls, serialized stream limits and server-selected moving watermark policy. Premium DRM is **NOT CONFIGURED** and a required-DRM policy fails closed. No production deployment or Stage 6 work was started.

The owner approved provider mocks rather than supplying a non-production library and separately approved the two application dependencies, hls.js and tus-js-client. Plan section 15 records actual domain/schema, current official provider mechanisms, lifetimes, locking, privacy, operational limits and the pending live checklist. Historical Stage 1–4 receipts and accepted pre-LMS production limitations retain their original evidence boundary.

### Stage 5 verification receipts

| Gate | Actual evidence |
|---|---|
| Focused provider/Studio/playback/authoring | PASS **89 tests / 439 assertions**, including 77 new ordinary tests plus existing CourseStudioTest |
| Full current-schema application regression | PASS **1,245 tests / 10,112 assertions**, zero failures/errors/skips; approximately 350 seconds, including canonical auth/Portal, private material/file/Resource, lifecycle/privacy, booking/finance and existing Livewire regressions |
| New independent MariaDB video races | PASS **6 tests / 29 assertions**: last stream, same-request retry, last browser, revoke versus issue, source withdrawal versus renewal and overlapping old/new provider polls |
| Full dedicated concurrency regression | PASS **46 tests / 256 assertions**, zero failures/errors/skips; approximately 51 seconds, all seven concurrency classes, direct runner exit 0 |
| JavaScript | PASS **37 tests**, zero failures/skips; scoped manifest/variant/segment/key rewriting, hostile host/scope/traversal rejection and expiry/renewal bounds alongside existing regressions |
| Production frontend build | PASS Vite; CSS about **129.83 kB**, app JS **21.33 kB**, TUS chunk **60.41 kB**, dynamic HLS chunk **574.74 kB**; HLS chunk-size warning is nonblocking, no limit weakened |
| Browser / responsive review | PASS desktop 1280px / mobile 390px: player, Studio media, settings and own-browser page, no horizontal overflow, 44px controls, Arabic input and blank credential fields; no browser warnings/errors. Synthetic HTTP-rendered Blade fixtures with built assets; submissions/navigation/external calls disabled, fixture files/tab removed and viewport reset; no live playback certification |
| PHPStan no-regression | PASS complete path/identifier/message diagnostic multiset **255 → 255**, exactly equal with duplicates; no ignores, new baseline or weakened configuration. Existing analyzer exit remains nonzero for its unchanged baseline. |
| Blade / routes | PASS view cache/clear and route cache/clear; all **19** new named video routes resolve with the existing guards/roles and only the authenticated provider callback excluded from CSRF |
| Pint / whitespace | PASS required dirty-PHP Pint and working/staged diff checks; no subsequent PHP changes |
| Dependency/security audits | PASS Composer **0 advisories / 0 abandoned packages**, npm **0 vulnerabilities** |
| Local migration/schema | PASS additive migration on verified local bolt_landing / MariaDB 10.11.18, **batch 22**, **127 application tables / 78 Ran migrations**, +6 tables/+1 migration; **14 generated columns / 4 finance guards unchanged**; only four default profiles seeded |
| Local data boundary | Three existing Student rows and zero Course rows unchanged; zero provider connections/media/browser/lease rows added to normal local data; no genuine account login, credential configuration or business-data mutation |
| Provider/live/production | Official API/security documentation verified; exact provider HTTP behavior mocked. No live library configured, uploaded, played or certified; no production inspection, backup, deployment or retest |

Normal and concurrency database suites run serially against explicit bolt_landing_test / MariaDB 10.11.18. The current-schema suite excludes historical MigrationACompatibilityTest and seven dedicated concurrency classes; all seven are run under phpunit.concurrency.xml with independent gated processes. No existing test/assertion was weakened. The new signing tests use fixed independently checked vectors rather than reimplementing the signer as the expected value.

Tests caught and repaired a missing publication-check import and a real merge-quota edge case: soft-deleted secondary Students still have unexpired issued capabilities and must remain in the surviving Student's quota. The status-poll race drove asset-locked bounded reconciliation. Synthetic fixture/cookie/session/schema setup and HTTP expectation mistakes were corrected without weakening production contracts. Full regression evidence includes the resulting behavior.

### Stage 5 security evidence and actual behavior

Canonical StudentSessionContext, absolute login expiry and Stage 2 entitlement/publication/source scope precede every protected issue/renewal. Nested Course/lesson/block/media and current private owner are checked again under canonical locks. Student B cannot obtain A's media capability, identity or browser/lease data, even with copied IDs/proofs. Foreign nested targets, injected private media references, future/expired/revoked sources, unpublished ancestors, processing/replaced media, changed policies/config, missing credentials, timeout/malformed provider state, session regeneration, logout and revoked browsers fail closed. Initial learner HTML/public JSON contain no provider GUID or signed URL. No new Livewire public property or client-supplied Student identity chooses access.

The real adapter checks the account API library and linked CDN zone on every authorization: embed and CDN token controls, signing-key match, non-IP-bound authorization, forced HTTPS, exact approved domains/empty-referrer blocking, HLS CORS, no unsupported edge rules/scripts, and disabled originals/MP4 fallback/direct/early playback/DRM. Current CDN signing uses HS256-prefixed Base64URL HMAC-SHA256 for the video directory; TUS uses the separately documented SHA-256 scoped upload signature. Invalid remote configuration returns a safe 503 without secret-bearing HTTP exceptions. The protected lesson uses strict-origin referrers; private-file responses retain their existing no-referrer boundary.

Course Studio reserves one local request before remote creation. Upload bytes go directly from staff browser to Bunny with renewable 15-minute scoped TUS tickets. Ready comes from fresh GET status 4 plus usable duration/no fallback, never upload completion or callback hints. Raw callback HMAC uses the library Read-Only key; size/shape/known library/GUID are bounded; exact digests are idempotent. Distinct old callbacks and overlapping polls use serialized fresh GET rather than stale callback state. Delete is explicit and blocked by live, draft or retained historical references. Unknown creation GUIDs cannot be reported deleted; ambiguous delete records failure. Local orphan candidates exist; global remote inventory/unknown post-timeout orphan recovery remains manual.

Profile inheritance is lesson → Course → Private/Member default. Super Admin alone changes provider secrets, policy fields and explicit draft overrides; Admin can author content with existing profiles, Assistant is denied. All Portal protected playback requires current Student authentication, including the Public profile. Private/Premium defaults are two browsers, one stream and required moving watermark; Member four/two; Public no configured quotas. Defaults are 120-second capability, 30-second renewal and 150-second lease. Authorization never exceeds absolute login or effective lesson access end. Profile settings cap TTL at 600 seconds and lease at 900 seconds.

Browser identity is a high-entropy encrypted HttpOnly/Lax cookie with mounted path/domain and Secure in production. The database stores only its hash, label, state and last use, with no raw IP/UA/fingerprint. Current Student row locking serializes enrollment, count/insert, renewal and revocation. Quota continues to count unexpired capabilities after close/revoke and after canonical merge, including soft-deleted secondary identities. Independent process evidence proves limits; abandoned leases expire without requiring a worker. Students manage only their own browser registrations; staff use existing manageTeaching authority. No private notes are exposed.

The custom native/HLS player uses memory-only lease proof, checked loader scopes, periodic access renewal, hard local expiry and stop/clear behavior. Close/hidden page/fatal failures release best-effort; a paused player does not renew indefinitely. Moving pseudonymous Learner reference plus session code is selected server-side. Container fullscreen retains the overlay; native fullscreen/PiP/casting/download controls are not offered. No global right-click suppression exists. The browser overlay remains removable and cannot prevent screen capture or deliberate bearer-token sharing.

Revocation blocks new/renewed capabilities; it cannot instantly recall issued CDN tokens or delivered bytes. Default maximum residual token lifetime is 120 seconds, shorter at access/login expiry, up to 600 seconds only if Super changes policy. DRM is **NOT CONFIGURED**, optional extension only; explicitly required DRM returns unavailable rather than downgrading. Ordinary V1 does not require paid DRM. External video remains the external provider's weaker protection class.

Canonical merge revokes both browser/lease proof sets instead of transferring them. Erasure deletes security proofs and withdraws/redacts source-private video metadata/content references while preserving shared catalog media. It performs no remote DELETE and makes no claim about provider copies/backups. Application backup includes metadata, not remote video bytes. Retained historical revisions conservatively pin used assets; independently reviewed history/provider retention and source recovery are needed before production use.

The bounded hourly prune command expires stale leases, removes old security leases after seven days, retains revoked-cookie tombstones 366 days and trims callback digests after 30 days. Production scheduler operation is not presumed. Security start/close/config/device audit uses safe IDs/status only; renewal is not a per-heartbeat audit or visitor event. Existing Portal notifications and private materials remain under their current authority. Bunny timestamps reuse private idempotent Stage 4 bookmarks with duration bounds; no watched percentage/time, completion, resume/progress, prerequisite, assessment or Stage 8 analytics is created.

### Stage 5 touched invariants and exact file inventory

I1–3 identity/ownership/auth, I4–5 staff/security, I10 ordered locks, I17 retained history, I19–20 Resource/private files, I21 teaching scope, I22 notification synchronization, I23–24 privacy/audit and I27 destructive-tools boundaries are preserved. Destructive tools remain fail closed for new unknown dependents; no expanded reset/export/restore permission exists. Finance, typed one_hour/two_hour provenance, booking/cancellation/refund, calendar/recurrence/room assignment and unrelated authentication writers are unchanged. Accepted pre-LMS PARTIAL production dispositions are not upgraded by local tests.

| Task files | Result |
|---|---|
| app/Domains/Lms/Models/{VideoProviderConnection,VideoAsset,ProtectionProfile,AuthorizedDevice,PlaybackLease,VideoWebhookReceipt}.php; matching six database/factories files | Bounded media/config/security identities, hidden encrypted keys/proofs and isolated fixtures |
| app/Domains/Lms/Models/{Course,Lesson,LessonBlock}.php | Generic video/profile relationships and typed model contracts |
| app/Domains/Lms/Services/{VideoDeliveryProvider,BunnyStreamProvider,LmsVideoService,LmsVideoSettings,LmsVideoProfiles,VideoDeviceService,ProtectedPlaybackService}.php | Real provider adapter, media lifecycle, Super-only config, reusable policy and canonical secure playback engine |
| app/Exceptions/VideoProviderUnavailable.php; app/Providers/AppServiceProvider.php | Sanitized fail-closed provider exception and bounded adapter binding |
| app/Domains/Lms/Services/{CourseStudioService,LmsContentService,LmsAccessService,StudentLearningService,StudentLearningStateService,LmsStudentLifecycle}.php | Generic authoring/ready-content/owner projection, private bookmarks and merge/erasure integration |
| app/Http/Controllers/Admin/{CourseStudioController,CourseVideoController,VideoSettingsController,VideoDeviceController}.php | Scoped Course Studio media, profiles/secrets and staff browser management |
| app/Http/Controllers/Student/{ProtectedPlaybackController,VideoDeviceController,AuthController,LearningController}.php; app/Http/Controllers/{BunnyStreamWebhookController,LmsFoundationController}.php | Canonical Student playback/device/logout, secure projections/referrer policy and authenticated bounded callbacks |
| app/Console/Commands/PruneLmsVideoSecurity.php; routes/{web,console}.php; bootstrap/app.php | Security routes, throttles, CSRF-scoped callback exclusion, scheduled retention and secret flash exclusion |
| app/Domains/Analytics/Services/AnalyticsService.php | Security/webhook traffic excluded from visitor telemetry |
| database/migrations/2026_10_07_194042_create_lms_video_security_tables.php | Six additive tables, policy seeds, generic block/profile FKs and coherent guards; populated rollback refused |
| resources/views/admin/lms/courses/{_videos,_protection,_block-form,edit,preview}.blade.php | Direct/resumed upload, status/reference lifecycle and profile/generic video draft controls |
| resources/views/admin/lms/video/{settings,devices}.blade.php; resources/views/admin/students/teaching.blade.php | Masked Super settings, policy truth and discoverable authorized staff browser controls |
| resources/views/student/learning/{protected-video,devices,lesson,index}.blade.php | Integrated custom secure player and discoverable own browser management |
| resources/js/{protected-video,bunny-upload,app}.js; package.json; package-lock.json | Directory-scoped HLS delivery, renewal/expiry, moving watermark and direct resumable uploads; only two approved new dependencies |
| tests/Feature/{BunnyStreamProviderTest,LmsVideoStudioTest,ProtectedPlaybackTest,ProtectedPlaybackConcurrencyTest}.php; tests/Feature/Concurrency/booking_worker.php; tests/protected-video.test.mjs | Provider vectors/mock integration, authorization/privacy/session/lifecycle contracts and real process races plus JS boundaries |
| LMS_IMPLEMENTATION_PLAN.md; LMS_IMPLEMENTATION_STATUS.md | Actual Stage 5 contracts, evidence, limitations and pending provider/release checklist |

### Pending live certification and release boundary

No non-production library was available for this task; the owner explicitly accepted provider mocks and live verification pending. Before production certification: configure credentials outside chat in protected Super settings, verify real library/zone fields and permissions, test direct/resumed upload and processing/failure/callback/delete, unsigned/expired/all HLS and alternate direct paths, allowed/disallowed/empty referrers, CORS/forced HTTPS, Chrome/Safari/mobile long renewal/expiry/revocation and watermark in permitted fullscreen. After the full suite, final responsive/field-ID adjustments were additionally checked by **131 tests / 808 assertions** across playback/Studio/Student Learning/teaching, then **25 Studio tests / 128 assertions**, plus final build/Blade/route checks. Native HLS resource URL propagation particularly requires live proof. Any unsupported remote override remains fail closed; no permissive workaround is approved.

Established delivery workflow remains **test → commit → non-force push on main**. The exact committed/pushed application SHA is in the final receipt and chat; this document cannot contain its own future hash. Only Stage 5 task files and these two requested records are staged. Three pre-existing untracked Hostinger/proposal documents remain untouched. No production hot edits, production backup/deploy/retest, Markdown-only parity deployment or Stage 6 continuation occurred.

---
## Stage 4 disposition — historical receipt, superseded for Stage 5

**COMPLETE — The Student LMS implementation and all required local gates pass.** Students can discover and consume authorized courses inside the existing Portal, navigate ordered lessons, use mixed content, keep private notes and bookmark meaningful direct-video timestamps. Completed remains explicitly empty until real completion exists. No Stage 5 or production deployment was started.

Stage 4 was separately authorized after Stage 3 application commit 59d992f1290b35e50fa8dbb3e510b049225fb4ad. Plan section 14 records actual Student Hub, personal-state, content/file, access-state and lifecycle contracts. The Stage 1 proposal and historical Stage 3 “Stage 4 deferred” statements are superseded only for this scope.

### Stage 4 verification receipts

| Gate | Actual final evidence |
|---|---|
| New student behavior/security | PASS **39 tests / 324 assertions** in the full current-schema run |
| Focused Student/access/auth/Portal/file regressions | PASS **141 tests / 835 assertions**; StudentLearningTest, LmsFoundationTest, LmsAccessOperationsTest, LmsLifecycleIntegrationTest, StudentAuthenticationTest, StudentSecondaryEmailTest, SessionInactivityAndCookieRefreshTest, StudentTeachingExperienceTest and matching Resource/material tests |
| New real concurrent personal-state races | PASS **5 tests / 24 assertions**: simultaneous visits, same timestamp saves, same-version note edits, privacy erasure versus save and access withdrawal versus save |
| Full current-schema application regression | PASS **1,168 tests / 9,769 assertions**, zero failures/errors/skips, approximately 357 seconds; the 1,129-test Stage 3 application plus all 39 new ordinary tests |
| Full dedicated concurrency regression | PASS **40 tests / 227 assertions**, zero failures/errors/skips, approximately 43 seconds; MariaDbConcurrencyVerificationTest, BusinessLifecycleConcurrencyTest, DevelopmentDataConcurrencyTest, LmsAccessConcurrencyTest, CourseStudioConcurrencyTest and StudentLearningConcurrencyTest |
| JavaScript | PASS **34 tests**, zero failures/skips; 27 existing plus 7 player tests covering finite timestamps, seek boundaries, metadata/error state, matching form/video and matching bookmark/video |
| Frontend build | PASS final Vite build, **114.65 kB CSS / 10.42 kB application JS**, approximately 1.76 seconds; dependencies unchanged |
| Browser / responsive UI | PASS default 1280px desktop and 390px mobile; no horizontal overflow, collapsed mobile/open desktop curriculum, working disclosure/input, mixed Arabic/English direction and 44px learning/nav/sign-out touch targets; no warning/error logs |
| Browser evidence boundary | Real Blade HTML from isolated HTTP fixtures, actual built CSS/player JS and existing Herd; no business login or session bypass. Submission/navigation/analytics disabled, tokens/external media source removed; temporary public fixtures and browser tab removed and viewport reset. Provider playback itself is not claimed from this visual review. |
| PHPStan no-regression | PASS complete path/identifier/message diagnostic multiset **255 → 255**, exactly equal; no added/removed diagnostics, ignores, baselines or weaker configuration. The analyzer retains its pre-existing nonzero baseline result. |
| Blade / routes | PASS view cache/clear and route cache/clear; all **11** new named routes resolve under existing Student middleware; old routes preserved |
| Pint / whitespace | PASS required dirty-PHP formatter and working/staged diff checks; no later PHP edits |
| Dependency/security audits | PASS Composer **0 advisories / 0 abandoned packages**, npm **0 vulnerabilities** |
| Local migration/schema | PASS only the additive personal-state migration on verified local bolt_landing / MariaDB 10.11.18, **batch 21**, **121 application tables / 77 Ran migrations**; +3 tables/+1 migration, no generated-column or typed-finance guard changes; no business-data seeding |
| Production | Not freshly inspected, backed up, deployed or retested; accepted historical production evidence remains its recorded 4296b21 / MariaDB 11.8.9 baseline |

The current-schema suite excludes historical MigrationACompatibilityTest and the six dedicated concurrency classes. Database suites run serially against the explicit bolt_landing_test database. Independent workers use that same isolated database with the dedicated concurrency configuration. No existing test was weakened. UI-only review files were transient, not application routes.

During development, meaningful tests caught hidden-payload access through default serialization and an expiry-label Blade directive; these were corrected in the implementation. Synthetic fixture defects (Resource publication field, forbidden Bunny payload and timezone assertion) were corrected without weakening schema or behavior. The final focused/full receipts above include the corrected cases. Existing Bunny schema enforcement is preserved rather than bypassed to manufacture a test URL.

### Stage 4 exact file inventory

| Files changed | Resulting behavior |
|---|---|
| app/Domains/Lms/Models/LearningVisit.php; LessonNote.php; LessonBookmark.php | Guarded canonical-owner personal data, precise relationships/casts/factories, hidden private bodies/labels |
| app/Domains/Lms/Services/StudentLearningService.php; StudentLearningStateService.php | Authoritative learner read model and serialized current-access personal writes |
| app/Domains/Lms/Services/LmsAccessService.php; LmsAccessWindow.php | Presentation expiry/denial using the same source decisions, scope and UTC bounds; existing resolution contract preserved |
| app/Domains/Lms/Services/LmsStudentLifecycle.php | Canonical merge transfers private data/latest visits; erasure removes personal text/state |
| app/Http/Controllers/Student/LearningController.php; routes/web.php | Eleven existing-guard HTML/read/write/file routes with server ownership, scope and private response headers |
| database/migrations/2026_10_07_183616_create_lms_student_learning_state_tables.php | Three additive tables, coherent parent FKs/indexes, bounded bookmark time and populated rollback refusal |
| database/factories/LearningVisitFactory.php; LessonNoteFactory.php; LessonBookmarkFactory.php | Useful isolated canonical Student/lesson/block/visit fixtures; no genuine-data seeder |
| resources/views/layouts/student.blade.php | Natural My Learning entry, preserved tutoring entry and mobile navigation touch targets |
| resources/views/student/learning/index.blade.php; course.blade.php; lesson.blade.php; curriculum.blade.php; notes.blade.php; feedback.blade.php; unavailable.blade.php | Hub, published course/lesson experience, filtered mobile/desktop curriculum, private notes/bookmarks and unavailable states |
| resources/js/learning-player.js; resources/js/app.js | Native direct-video timestamp capture and matching bookmark seek; no player progress or analytics writer |
| tests/Feature/StudentLearningTest.php; StudentLearningConcurrencyTest.php; tests/Feature/Concurrency/booking_worker.php | A/B, publication/access/auth/file/private-ID/validation/lifecycle/notification tests and independent write races |
| tests/learning-player.test.mjs | Seven meaningful player boundary/control tests alongside existing JS regressions |
| LMS_IMPLEMENTATION_PLAN.md; LMS_IMPLEMENTATION_STATUS.md | Actual Stage 4 architecture, invariant mapping, final gates and later-stage insertion points |

### Canonical contracts and preserved invariants

Existing StudentSessionContext and student.auth remain the sole identity/session proof. The hub starts with Stage 2 activeForStudent; outlines, files, private assignments and personal writes repeat current access. Multiple grants form an OR union without duplicate cards. Unavailable states show no inaccessible title/instructions/foreign identity. Current access expiry is the latest effective finite end or no scheduled end; lesson display respects that exact lesson’s scoped sources. All dates use canonical Portal display timezone and elapsed-day/UTC authority.

Last-viewed activity is only Student/Course/last lesson/time. Overview visits retain the last lesson, inaccessible continuations fall back to the authorized overview, and denied reads create no visit. There is no watched fraction, percentage, course completion, resume writer or analytics inference.

Notes are private plain text with create/edit/delete and optimistic conflict handling. Their owner-only paginated list supports read/delete after expiry without exposing old instructional metadata. Current lesson access is required to create/edit. Bookmarks are private Student/lesson/block/millisecond navigation hints with optional labels. Direct HTML5 video provides meaningful currentTime only after finite duration is available; metadata/error controls and bounded matching seek are implemented. YouTube/Vimeo iframe timestamps are not fabricated. This does not certify how much a Student watched.

All real Stage 3 content types render through the existing sanitizer/provider rules. Images/Resource/PDF/files use nested entitlement plus LessonMaterialService’s current private file/Resource delivery. Unsafe or missing content fails clearly. Bunny remains a null-payload protected-video placeholder; no insecure permanent playback link is exposed.

There are no new Livewire components/public learner properties. Native allowlisted form payloads, ignored untrusted Student/target fields, server nested-ID checks and owned-ID queries provide the tampering boundary tested here. Existing Livewire functionality remains in the full regression suite.

Touched invariants are I1–3 (identity/ownership/auth), I4 (staff roles), I8 (Student timezone), I10 (locking), I17 (history versus explicitly deletable personal content), I19–20 (Resource/private files), I21 (teaching sharing), I22 (notification synchronization), I23–24 (privacy/analytics/private payloads) and I27 (destructive tools). Canonical merge/erasure handle all personal bodies and visit collisions. No automatic admin/tutor note exposure or private-text audit payload exists. Existing Portal notification sync/read-state and first-party pageview/activity remain; no duplicate semantic learning/game event was added.

Booking, typed one_hour/two_hour entitlements, funding, calendars, cancellations, refunds, finance, recurrence, meeting rooms, staff security and the nine pre-LMS repairs have no new writer. Their existing full/current-concurrency regressions remain authoritative; historical production PARTIAL dispositions are not upgraded by local LMS tests.

### Scope, insertion points and release boundary

Stage 5 may add a protected-provider adapter at safe block rendering/player capability and reauthorize playback requests. It can reuse Student/lesson/block timestamp bookmarks only after reliable provider-time capability exists. Stage 6 must create separate educational progress/completion/resume facts; opening a lesson, a private bookmark or a pageview is not completion. Learner release/version-upgrade semantics remain an explicit later decision.

Current Course Studio has no stored cover/description fields; cards use a neutral current-style cover. The three minimal personal tables deliberately do not require enrollment/release pins because broad Public/Member access may have no enrollment. Personal notes remain Student-only. Existing tutoring Assign Learning, prerequisites, drip, quizzes, graded assignments, protected Bunny playback/devices/watermarking, aggregate LMS analytics, AI/transcription and whole-site redesign remain deferred. No Stage 5 is started automatically.

Established workflow remains **test → commit → non-force push on main**. Only the Stage 4 application/tests and the two requested records are staged. The three pre-existing untracked Hostinger/proposal documents remain untouched. Review copies/final chat carry the exact delivery SHA after commit/push; the committed document cannot contain its own future hash. No production deployment or documentation-only deployment is performed.

---
## Stage 3 disposition — historical receipt, superseded for Stage 4

**COMPLETE — Course Studio is implemented and all required local gates passed.** Authorized Admin/Super Admin staff can list/filter, create, edit drafts, organize sections/lessons/content, reorder and move lessons, attach/reuse private files and Resources, securely preview, publish/unpublish, duplicate and archive/restore courses. Publication preserves selected child states and keeps pending edits separate from the current live Course. No Stage 4 or production deployment was started.

The owner separately authorized Stage 3 after Stage 2 application commit `42c849796d9a540d0fa116fc3e985e18d678a5f7`. Plan section 13 records the actual authoring/schema/security contracts and supersedes matching Stage 1 proposals and Stage 2 deferrals. The existing CMS ContentRevision store holds draft and published bodies; two additive tables hold release anchors and private attachment metadata. Stage 2 remains the canonical structure/access engine. Course Studio reuses the existing admin layout, roles/policies, sanitizer, private-file service, canonical Student lifecycle and audit service. No dependency change, second Resource/file authority, tutoring finance change or whole-site redesign was made.

### Stage 3 verification receipts

| Gate | Actual final evidence |
|---|---|
| Focused authoring/security/access/lifecycle | PASS 59 tests / 357 assertions; CourseStudioTest, CourseStudioPublicationTest, CourseStudioAssetSecurityTest, LmsAccessOperationsTest and LmsLifecycleIntegrationTest |
| New Course Studio behavior | PASS 37 tests / 252 assertions in final full run: authoring12/96, publication13/75, assets/security12/81 |
| New simultaneous authoring races | PASS 4 tests / 24 assertions: same slug creation, stale same-version draft write, same-version publication and duplicate submission; actual independent MariaDB workers |
| Full current-schema regression | PASS **1,129 tests / 9,445 assertions**, zero failures/errors/skips, approximately 377 seconds; includes all 37 new regular Course Studio tests and the 1,092-test Stage 2 application |
| Full dedicated concurrency regression | PASS **35 tests / 203 assertions** across MariaDbConcurrencyVerificationTest, BusinessLifecycleConcurrencyTest, DevelopmentDataConcurrencyTest, LmsAccessConcurrencyTest and CourseStudioConcurrencyTest |
| JavaScript | PASS **27 tests**, zero failures/skips; 20 existing plus 7 editor tests for submit sync, Unicode/plain paste, allowed formatting, safe URLs, selection isolation/current-selection precedence and link text retention |
| Vite/frontend | PASS final production build; CSS 113.90 kB, application JS 8.37 kB; no new dependency |
| Browser/rendered UI | PASS default 1280px desktop and 390px mobile layout review; page width equals viewport, visible Course Studio buttons meet 44px height, hierarchy/Arabic-English/attachment preview render; real editor replacement paste/formatting/safe plain-text paste/form synchronization checked; no warning/error logs |
| Browser evidence boundary | Authenticated workflow actions/ownership are proven by isolated Laravel HTTP/domain tests. Browser review used their synthetic rendered Blade fixtures, actual built CSS/Livewire-Alpine scripts and local Herd; no business-account login or Student session impersonation. Submission/navigation was blocked in fixtures, which were removed afterward. Screenshots are review artifacts, not production evidence. |
| PHPStan no-regression | PASS complete path/identifier/message diagnostic multiset **255 → 255**, exactly equal; zero new/removal diagnostics, no ignores/baseline/level/config weakening. PHPStan itself retains its existing nonzero baseline exit status. |
| Pint/whitespace | PASS final required dirty-PHP agent formatter and both working/staged git diff --check; no later PHP edits |
| Blade/routes | PASS compiled Blade and route caches, cleared afterward; 12 new staff Course Studio routes plus the existing access-list route under the prefix; existing CSRF/roles/throttles retained |
| Dependency audits | PASS Composer 0 advisories/abandoned packages and npm 0 vulnerabilities |
| Local migration | PASS only the new authoring migration on verified local bolt_landing / MariaDB 10.11.18, batch 20; no business-data seeding |
| Local schema after migration | **118 application tables / 76 Ran migrations / 14 generated columns / same 4 typed guards**; Stage 3 delta +2 tables/+1 migration/+2 generated CMS keys |
| Production | Not freshly inspected, backed up, deployed or retested in this stage; recorded accepted production application remains 4296b21 / MariaDB 11.8.9 |

The established current-schema suite excludes historical MigrationACompatibilityTest and the five dedicated concurrency classes; the latter are run separately on the explicit MariaDB test database, without parallel database suites. Existing tests were not weakened. Normal-suite assertions remained the Stage 2 total plus all 252 new authoring assertions. Existing race assertion counts can depend on the legitimate winner branch; the executed combined receipt is 35/203.

Initial new-fixture issues were corrected before final receipts: an invalid YouTube fixture ID, HTML source-encoding assertion, missing optional Resource revision inventory and nonrefreshed default version. Byte-level upload tests strengthened MIME verification rather than trusting fake/client-reported type. Browser review found a remembered-selection paste bug; the editor now prefers the current owned selection, with a permanent JS regression. Failed validation retains scoped state/access input. All corrected code is covered by the final full run/JS gate. No prior defect or test was hidden.

### Stage 3 files and regression coverage

| Files / area | Resulting behavior and coverage |
|---|---|
| app/Domains/Lms/Services/{CourseStudioService,LmsContentService}.php | Authorized bounded draft tree, scoped CRUD/order/move, optimistic conflicts/live fingerprint, canonical publication, safe provider/text/reference validation, eligible Draft duplication and retained lifecycle |
| app/Domains/Lms/Models/{Course,LessonBlock,CourseRelease,LmsAsset}.php | Existing CMS revision/current-release relations, asset references/ownership, immutable release anchors and hidden private metadata |
| database/migrations/2026_10_07_164547_add_course_studio_foundation.php; database/factories/LmsAssetFactory.php | Two additive tables, scoped CMS revision uniqueness, owned release pointer, supported-content CHECK and private metadata factory; prior migrations untouched |
| app/Http/Controllers/Admin/CourseStudioController.php; routes/web.php | Current-staff list/filter/create/edit/actions/preview/download authorization, request allowlists, UTC normalization, private/no-store responses, conflict messages and twelve new routes |
| resources/views/layouts/admin.blade.php; resources/views/admin/lms/courses/{index,create,edit,preview,_order,_block-form}.blade.php | Authorized navigation, complete responsive admin authoring/list/preview, server-order forms, preserved validation input, selected publication states and no unfinished later-stage controls |
| app/View/Components/Lms/RichTextEditor.php; resources/views/components/lms/{input,state,button,rich-text-editor}.blade.php | Existing style conventions, labeled reusable form controls and sanitized mixed-language editor with local owned selection/plain-text paste |
| app/Domains/Booking/Services/LessonMaterialService.php | Existing private-file authority extended for Course UUID assets, actual MIME/hash/size/path checks, private preview and postprivacy cleanup; Resource/lesson material behavior retained |
| app/Domains/Lms/Services/{LmsAccessService,LmsStructureService,LmsStudentLifecycle}.php | Asset readiness/owner checks, guarded legacy structural writes, canonical merge-derived asset identity and private snapshot/filename/body/byte erasure |
| app/Domains/System/Services/DevelopmentDataArchiveService.php | Resource archives cannot inject LMS Course revisions into a second restore authority; existing scoped export and unknown-LMS reset refusal remain |
| tests/Feature/CourseStudioTest.php | Create/edit/form validation, role-aware nav/Student denial, complete hierarchy/order/move/archive, section/lesson/content foreign-key tampering, filters, stale conflict, UTC rules, retained failed state and read-only sanitized preview |
| tests/Feature/CourseStudioPublicationTest.php | Selected states, draft/live isolation, retained owned release/hash, empty/broken/reserved publication denial, unpublish/archive/restore/discard, safe runtime-free copies/new IDs/Resource reuse, legacy bypass/live change conflicts, schema guards, URL swap identity and archive boundary |
| tests/Feature/CourseStudioAssetSecurityTest.php | Actual byte-type checks, unsafe SVG/HTML/disguised uploads, private attachment/foreign/path denial, missing/modified bytes, Resource and duplicated-asset detachment reuse, private merge/privacy and controlled provider/sanitizer/XSS cases |
| tests/Feature/CourseStudioConcurrencyTest.php; tests/Feature/Concurrency/booking_worker.php | Actual simultaneous create/write/publish/duplicate controlled losers, one durable draft/release/copy and no runtime duplication; existing worker actions retained |
| tests/course-studio-editor.test.mjs | Actual component factory security and selection/form behavior; existing 20 JS tests retained |
| LMS_IMPLEMENTATION_PLAN.md; LMS_IMPLEMENTATION_STATUS.md | Actual Stage 3 contracts, invariants, gates, deployment limits and deferred-stage handoff; historical Stage 1/2 receipts retained |

Directly touched PRE_LMS_INVARIANTS: **I1–4, I10, I17, I19–21, I23–24 and I27**. Plan section 13.6 maps the contracts. Staff roles, Resource grants/reference reuse, private ownership/files, retained history, privacy/audit and destructive-tool boundaries have focused regressions. Tutoring type/credit/ledger/payment/booking semantics, availability/timezone and teaching sharing remain governed by their existing writers/full tests. All nine repaired defects were reviewed; this change specifically preserves Assistant navigation boundaries and selected publication states. No accepted capability ID or historical production PARTIAL branch is reclassified.

### Stage 3 boundaries, limitations and delivery

Authoring supports rich text, private images, PDF/file attachments or existing Resource files, published Resource references, safe external links, controlled YouTube and Vimeo/direct HTTPS video metadata. Provider availability is external; the stage does not download/proxy media or promise secure playback. Course duplicates reuse source file IDs/bytes only through compatible explicit references. Course kind/private owner remain fixed at creation. Uploads/detachments retain files until an explicit lifecycle cleanup; there is no unrequested destructive asset purge UI.

Draft changes do not mutate live rows/access until publication. Course status closes access immediately on unpublish/archive; child states are preserved rather than forced Published. Released bodies are retained by normal authoring, with the existing private privacy-erasure exception. No learner release pin/upgrade or progress-reset policy is implemented. A private author preview is not learner impersonation and creates no learning facts. Member retains the documented authenticated verified Student assumption; owner-confirmed relative access is elapsed 24-hour days. There is no separate membership decision, grant engine or tutoring credit bridge in the editor.

Reviewing/resetting/exporting/restoring LMS graphs is deferred; Resource-only exports exclude Course revisions and read rejects injected LMS bodies. CMS schema hashes change because of the two additive virtual fields, so pre-Stage-3 Resource revision archives require reviewed compatibility. Populated migration rollback refuses destructive history loss. Fresh production schema inventory, required recoverable backup, exact-SHA deployment and production retest remain separate future release work.

Deferred: Stage 4 My Learning/student player/private notes/bookmarks; Stage 5 Bunny API/secure playback/devices/watermarking; Stage 6 progress/completion/prerequisites/drip/quizzes/graded assignments; Stage 7 tutor Assign Learning UI; Stage 8 learning analytics/localization/settings/export integration; AI/transcription/ecommerce and whole-site branding. No dead or misleading author controls for those stages are exposed. Stage 4 must not begin automatically.

Local verified application code follows **test → commit → non-force push** on main. Only the task's code/tests and these two requested records are staged; the three pre-existing untracked Hostinger/proposal documents remain untouched. The final chat/review-copy receipt carries the exact new application SHA; this source record does not embed its own future hash. No production hot edit or documentation-only deployment is performed.

## Stage 2 disposition — historical foundation receipt

**COMPLETE — backend foundation and all required local gates passed.** The separate owner Stage 2 instruction authorized the minimum backend foundation. Stage 1 remains completed and its historical verification below remains evidence for the pre-LMS application; it is not the Stage 2 release receipt. At this checkpoint Stage 3 and production deployment had not begun; the separate Stage 3 instruction and current receipt above supersede that historical boundary.

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

## Implementation and acceptance checklist (current)

- [x] Stage 1 discovery/integration/schema/security/regression blueprint written.
- [x] Accepted baseline and documentation-only differences established.
- [x] All 27 invariants, existing capability namespace and all nine repaired defects mapped.
- [x] Actual available local gates executed and their results/limits documented.
- [x] Stage 1 final fresh full current-schema gate all-green; initial heartbeat timing checkpoint retained separately.
- [x] Stage 2 — foundation/schema/LMS access; current member assumption and confirmed elapsed windows documented; MariaDB/lifecycle/concurrency/full gates passed.
- [x] Stage 3 — Course Studio/mixed content/drafts/releases/preview; current authoring/access/file/lifecycle/concurrency/UI and full local gates passed.
- [x] Stage 4 — My Learning/For You/Continue/player/private notes/bookmarks; access/auth/file/privacy/UI/concurrency and full local gates passed.
- [x] Stage 5 — Bunny security/profiles/devices/leases/dynamic watermark and bounded residual limits implemented; owner-approved mocks pass, live certification remains pending.
- [x] Stage 6 — progress/completion/prerequisites/drip/quizzes/submissions/reviews implemented and locally verified.
- [x] Stage 7 — tutoring/private Assign Learning/follow-up/timeline integration implemented and locally verified.
- [x] Stage 8 — analytics/attention/visit notifications/permissions/settings/preview/functional UI integration implemented; independent acceptance remains pending.
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
