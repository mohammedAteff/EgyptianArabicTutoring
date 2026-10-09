# Owner Review Dataset report

The natural review dataset is ready and remains in production: **6 fictional Students, 13 bookings, 7 purchases/manual payments, 3 published courses, 8 access/enrollment/learning-assignment bundles, 2 graded quiz attempts and 2 reviewed submissions**. Created records and file ownership are registered in `OWNER_REVIEW_DATASET_MANIFEST.md`; raw verification details and session/authentication values remain in protected storage outside Git.

| Review ID | Student ID / natural name | Package IDs | Booking IDs | Learning |
|---|---|---|---|---|
| OR-01 | 14 · Sarah Black | 21 | 39,46 | 7 In Progress 50% |
| OR-02 | 15 · Iris Smith | 22 | 47 | 7 Not Started 0% |
| OR-03 | 16 · Daniel Foster | 23 | 43,48 | 7 In Progress 0% |
| OR-04 | 17 · Maya Collins | 24 | 40,41,42,49 | 7 In Progress 75%; 8 Completed 100%; 9 In Progress 0% |
| OR-05 | 18 · Omar Hassan | 25,26 | 44,50,51 | 8 In Progress 0% |
| OR-06 | 19 · Sophie Martin | 27 | 45 | 7 In Progress 25% |

## Coherent finance, scheduling and teaching

Seven manual USD payment facts total **750.00**, against purchase final prices totaling **870.00**. Daniel's purchase is 240.00 before a 40.00 discount, 200.00 final, 80.00 paid and 120.00 due. Its two 100.00 installment forecasts have 20.00 currently overdue and 100.00 later; they do not create extra payment/debt facts. His statement and receipt were checked through ordinary authenticated Student HTTPS and his 120.00 receivable / 20.00 overdue forecast were seen in the genuine owner's desktop UI. No live payment-provider transaction or real settlement is claimed.

Seven tutoring lessons were completed only after their historical end time; six bookings are currently confirmed in the future. Bounded historical console clocks restored themselves in `finally` and left production clock/configuration unchanged. Purchases precede their tutoring activity. Signed availability, notice, booking, completion, timezone and typed services remained authoritative. Each booking consumes exactly one appropriate typed unit from its own allocation. Omar has separate one-hour/two-hour rights; reschedule #5 for booking #51 preserves original debit #73, with no second debit.

Shared teaching includes three plans with six milestones, three homework items, three Resource assignments, three Student notes, Sarah's private handout/preparation, Maya's pronunciation pattern, two lesson-feedback responses and Iris's submitted existing Learning Profile questionnaire (six answers and one immutable response revision). Two owner tasks support Daniel/Sophie and one created owner note holds conversation ideas; the original owner note is preserved. Sophie has one one-hour unit remaining and near expiry, with canonical follow-up notices.

## LMS states and representative UI evidence

Foundations has four lessons, natural rich text, the unchanged original Alphabet Resource, a new passive PDF handout, an ordered three-question quiz and an introduction assignment. Sarah is 50%, Maya 75%, Sophie 25%, Daniel 0% in progress and Iris 0% not started. The short Conversation course is completed by Maya (100%) and in progress for Omar (0%). Maya's private course has an actual text submission marked **Needs Revision**, with specific tutor feedback. Her catalog file submission is **Approved**; her quiz and Sarah's normally submitted HTTPS quiz both scored 100% through canonical grading. Private notes and visits are owned by their Students. No pretend video/provider/device/playback evidence was created.

Actual desktop observations: Abdallah / Super Admin normal sign-in; Student Records empty after cleanup then the six natural people; Maya's owner profile showing 75%, 100% and private 0% states; Course Studio with exactly the three natural published courses; normal Maya Student sign-in; My Learning with Continue / My Courses / For You / Completed sections; private lesson player and **Needs Revision** submission history/feedback; Daniel's canonical receivable screen. Four visual screenshots are retained in the task output folder. The new PDF was rendered and visually checked. Later navigation to Today timed out; it is not claimed as visually verified. No full acceptance rerun was performed.

Normal HTTPS verification made 24 successful requests with three real Student verifications, Sarah's begin/submit/result quiz flow, Daniel's statement and receipt, and ordinary logout. Three authenticated Visitor roots generated 21 modest actual facts (including two server-owned learning events); synthetic campaign labels or countries were not fabricated. Console-seeded activity was not backfilled into analytics as pretend traffic. Learning cohort analytics derive authoritative current enrollment/progress/assessment facts regardless of whether preparation ran through HTTP.

## Notifications, preservation and live integrity

42 Student notices retain canonical assignment/homework/Resource/questionnaire/lesson/assessment/expiry ownership. Historical items were normally marked read: **20 unread** (3 each, 5 Maya). Admin has 13 created booking notices with **6 unread** upcoming items. This deliberate small review inbox is disposable later; launch contract requires zero synthetic notices/unread counters. Two existing Telegram countdown rules remain paused while fake future bookings exist; mail is `log`. No new Telegram deliveries, reminders, queued jobs or failures were observed. No external messages or payment transactions were sent.

Live final reconciliation: 0 FK orphans, 0 finance discrepancies, BALANCED; all 13 typed provenance/timezone snapshots match authority; reschedule debit preserved; assessment definitions/ownership and course access valid; all three created private-file hashes valid; no unreferenced files in lesson-materials/LMS-assets/LMS-submissions; all old engineering selection IDs/files absent; original Resource file hash preserved; identical owner persisted row/security, source/schema/migrations/triggers; tools disabled; native protected recovery hash verified. 36 retained system/content definition domains were checked, allowing only their naturally updating scheduler heartbeats.

Preparation-only failures were rolled back and their exact transient uploads removed. No application defect required a source fix or redeployment. No credentials appear in these reports or Git. Existing unrelated traffic, Contact #4/#7, owner/system definitions, real content, original owner notes and protected history remain. The existing offsite-replication warning is not repaired by this data task.

## Future launch and product observations

Do not remove this dataset during owner review. Apply `FINAL_LAUNCH_CLEAN_STATE_SPEC.md` only under later launch authorization with a fresh snapshot/ownership preview. The registry includes 410 exact created primary records (103 are retained audit provenance), eight private verification states, three logged-out normal HTTP sessions and three owned private files. Existing mixed owner/Student browser state must sign the fictional Student out normally before a future reset preserves owner authentication. Recalculate future descendants because manual review adds facts.

The existing UI exposes internal identifiers such as Student #17, Purchase #24 and Session #42. Rollbacks also leave legitimate ID gaps. This is recorded as a future product/UI issue: freshness must be communicated by zero real operational counts and natural content, not by altering physical sequences. No UI redesign was started. There are no engineering QA names in the current normal roster/course/payment/assignment/inbox objects; immutable technical audit action names remain intentionally available in the audit area.
