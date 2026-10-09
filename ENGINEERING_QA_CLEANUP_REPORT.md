# Engineering QA cleanup report

Completed 9 October 2026. Engineering runtime clutter was removed, including the Stage 9 LMS graph and owner-confirmed Sarah Adel. The accepted production release remains `094b2c45375d5b221d9a6a132af670b580dd4d05` with a clean tracked checkout. All 1343 selected rows and nine files are absent. Four synthetic staff accounts are retired. Tests, prior reports, manifests, acceptance evidence, Git history, native recovery and immutable audit were preserved.

## Freeze and protected recovery

Production schema is unchanged: 134 tables, 82 migration rows, the identical complete FK metadata and four identical typed booking/ledger triggers. Owner Administrator #1's entire persisted row hash is identical before/after, including profile, credentials, role and security/TOTP state. The original account did not require/confirm TOTP; this operation preserved that state and did not enable, disable, recreate or reset credentials. The owner later signed in normally and the actual desktop UI identified Abdallah / Super Admin.

Protected native recovery is `storage/app/backups/owner-review-20261009-094b2c4-before` (0700) and its matching archive (0600). Archive: **23,967,053 bytes**, SHA-256 `9cd74d2cc913c9f75a5b3aa398c1842cc37e7eed310a012fbab3048ccd9a47b1`. Native SQL and the source/vendor/environment/private/public files, mounted wrapper and build were captured and verified. The final live check reverified the archive hash. The mandatory Student-reset recovery is identity `846a04c7-8cd9-4837-8ee9-06df37b2ba27`, database SHA-256 `aceca12627014bab660f97c30e2d0787968d96e6a3d9cd707c0124b290757994` with one private file. Portable export identity `da01933e-b4f9-4e61-9818-3f98e6b63d5a`, SHA-256 `f956b2419568b8423d7283b4edb87c451b7658bb2af9a7c9267f491923b07d84`. These are recovery references, not public download links.

Local `main` had newer commits, including `b447de64039a700748fe38d42da3aa8974f06ec0`, with application/public-copy changes that are **not deployed**. Production operation adapters used the accepted deployed source, not those newer APIs. Documentation publication is based on published `73e482d2e156eb5242a07f127f0ce62909829e9b` so that the existing unpublished application change is not pushed by this task. The pre-existing production untracked `.env.backup.before-7123f15` was preserved. No production hot edits or documentation redeployment occurred.

## Before, removals and clean boundary

| Domain/table | Fresh QA selection removed | Before total | After cleanup total |
|---|---|---|---|
| admin_notifications | 19 | 19 | 0 |
| analytics_events | 846 | 2069 | 1223 |
| blog_revisions | 1 | 1 | 0 |
| blog_slug_redirects | 1 | 1 | 0 |
| blogs | 1 | 1 | 0 |
| booking_events | 22 | 22 | 0 |
| booking_holds | 3 | 18 | 15 |
| booking_policy_decisions | 4 | 4 | 0 |
| booking_waitlists | 2 | 2 | 0 |
| bookings | 13 | 13 | 0 |
| category_translations | 1 | 3 | 2 |
| contacts | 7 | 9 | 2 |
| content_revisions | 18 | 18 | 0 |
| entity_translation_revisions | 8 | 18 | 10 |
| faq_translations | 1 | 5 | 4 |
| faqs | 1 | 5 | 4 |
| form_answers | 14 | 14 | 0 |
| form_questions | 6 | 15 | 9 |
| form_submission_revisions | 5 | 5 | 0 |
| form_submissions | 5 | 5 | 0 |
| form_triggers | 2 | 5 | 3 |
| form_versions | 2 | 4 | 2 |
| forms | 1 | 3 | 2 |
| game_translations | 2 | 3 | 1 |
| games | 2 | 3 | 1 |
| homeworks | 2 | 2 | 0 |
| learning_milestones | 2 | 2 | 0 |
| learning_plans | 2 | 2 | 0 |
| lesson_feedback | 1 | 1 | 0 |
| lesson_materials | 4 | 4 | 0 |
| lms_access_events | 7 | 7 | 0 |
| lms_access_grants | 4 | 4 | 0 |
| lms_access_rules | 3 | 3 | 0 |
| lms_assets | 3 | 3 | 0 |
| lms_assignment_submissions | 2 | 2 | 0 |
| lms_authorized_devices | 1 | 1 | 0 |
| lms_course_releases | 6 | 6 | 0 |
| lms_courses | 3 | 3 | 0 |
| lms_enrollments | 3 | 3 | 0 |
| lms_learning_assignments | 4 | 4 | 0 |
| lms_learning_visits | 3 | 3 | 0 |
| lms_lesson_blocks | 15 | 15 | 0 |
| lms_lesson_notes | 3 | 3 | 0 |
| lms_lesson_progress | 3 | 3 | 0 |
| lms_lessons | 3 | 3 | 0 |
| lms_quiz_attempts | 3 | 3 | 0 |
| lms_sections | 3 | 3 | 0 |
| maintenance_visits | 1 | 15 | 14 |
| marketing_touches | 5 | 17 | 12 |
| media | 1 | 1 | 0 |
| meeting_providers | 1 | 4 | 3 |
| meeting_rooms | 2 | 2 | 0 |
| package_installments | 2 | 2 | 0 |
| package_renewals | 1 | 1 | 0 |
| page_translations | 3 | 5 | 2 |
| pages | 1 | 3 | 2 |
| payment_methods | 1 | 7 | 6 |
| payment_records | 6 | 6 | 0 |
| payment_refunds | 3 | 3 | 0 |
| promotions | 3 | 3 | 0 |
| recurring_lesson_occurrences | 2 | 2 | 0 |
| recurring_lesson_plans | 1 | 1 | 0 |
| resource_assignments | 2 | 2 | 0 |
| resource_categories | 1 | 3 | 2 |
| resource_downloads | 4 | 5 | 1 |
| resource_requests | 4 | 7 | 3 |
| resource_translations | 4 | 5 | 1 |
| resources | 2 | 3 | 1 |
| session_ledger_entries | 22 | 22 | 0 |
| session_reschedules | 2 | 2 | 0 |
| session_types | 3 | 6 | 3 |
| social_links | 1 | 6 | 5 |
| staff_bins | 3 | 4 | 1 |
| staff_note_preferences | 2 | 2 | 0 |
| staff_recent_views | 11 | 11 | 0 |
| staff_saved_views | 4 | 4 | 0 |
| staff_tasks | 2 | 2 | 0 |
| student_auth_attempts | 17 | 17 | 0 |
| student_bins | 4 | 4 | 0 |
| student_error_logs | 2 | 2 | 0 |
| student_notifications | 52 | 52 | 0 |
| student_operational_alerts | 2 | 2 | 0 |
| student_package_entitlements | 10 | 10 | 0 |
| student_packages | 10 | 10 | 0 |
| student_unavailabilities | 2 | 2 | 0 |
| students | 7 | 7 | 0 |
| teaching_tags | 2 | 2 | 0 |
| tutor_preparations | 1 | 1 | 0 |
| visitor_funnel_progressions | 20 | 89 | 75 |
| visitor_sessions | 27 | 226 | 199 |
| visitors | 20 | 192 | 172 |

The fresh selected count is not a count of every table's preexisting population. All selections came from exact historical manifests cross-checked against current FK/polymorphic references, authenticated Student/visitor ownership, registered QA route/campaign references and current file hashes. No name/date/high-ID predicate was a deletion authority. Original frozen rows, classification, dependents, origins and file hashes are listed in `CURRENT_ENGINEERING_QA_CLEANUP_MANIFEST.md`.

The initial seven Students, 13 bookings, ten purchases, six payment records, three refund facts, 52 Student notices, 19 admin notices and three QA courses were removed. LMS rows included four grants, three enrollments, four assignments, three progress rows, three quiz attempts, two submissions, one browser/device and six releases. All removed finance/teaching/forms/recurrence/waitlist/access/assessment facts were synthetic. Known analytics removal was 846 facts, 20 owned Visitors, 27 sessions, 20 funnels and five marketing touches; 1223 unrelated/unclassified raw events remained protected. One billing discrepancy that belonged to the synthetic cohort disappeared with its authoritative closure.

The real owner Student Records screen was observed with **zero Students** and the admin unread badge at zero before seeding. Three QA courses, visible engineering titles, owned CMS objects, staff tasks/notes/preferences and QA staff accounts were removed. Historical audit remains accessible to authorized audit staff by design; it is not rewritten or claimed erased.

## Mechanisms, files and retained boundaries

The existing Student reset initially failed closed on unreviewed LMS dependencies. Exact course archive and Student LMS erasure used canonical services; the reviewed residual complete LMS graph was then purged child first with FK and typed guards active. After a fresh re-query, normal HTTPS DevTools Student reset operation #8 used an actor/session-bound preview, exact counts, current password/MFA, mandatory protected snapshot and export. It owned Student finance/bookings/teaching/forms/notices/auth state. Independent cleanup re-queried the remaining CMS/form/Contact/definition/notice graph before removing it. Nullable version pointers were resolved only within their exact synthetic form graph. QA staff were normally soft-deleted with historical provenance retained.

Five LMS private files were removed by the existing after-commit file lifecycle, one tutoring attachment by Student reset, and one Resource PDF plus two Media payloads by exact last-reference/hash-guarded cleanup. Every removed path/hash is in the frozen manifest. Genuine Arabic Alphabet Resource #1's private PDF retained its original hash. Calendar serialization locks were preserved. Contacts #4/#7 were independently genuine/unknown and remain; Sarah Adel's Student and booking ownership did not authorize deleting Contact #7.

Audit row count: 336 before, 346 immediately after. All original audit IDs survive, with Student-associated data redacted by the current privacy/reset authority. QA administrator rows #6–#9 survive only as soft-deleted system provenance; they cannot sign in or appear in active staff lists. DevTools operation history, protected snapshots/export and engineering proof documents are retained. Date projections for 6–9 October were canonically rebuilt from retained facts; mixed social rollups were invalidated only for those complete-source recent days, with no retention pruning. Earlier projections remained intact.

Destructive-tools environment/cache files were restored byte-for-byte and by original modes. A fresh process confirmed false, and normal HTTPS returned 404 afterward. The stale `cached_flag=true` inside the same restoration process was an old opcode-cache observation; fresh final `cached_flag/effective_flag=false` is authoritative. Rule #1/#2 reminders were paused only to protect fictional upcoming review bookings; definitions/recipients/authentication stay unchanged.

## Final result and limitations

Final live reconciliation at 2026-10-09T15:28:10+00:00: **0 FK orphans, 0 financial discrepancies, BALANCED**, 13/13 valid typed debit references and UTC/timezone snapshots, no second debit on Omar's reschedule, 0 unexpected learning-file orphans, unchanged owner row, 36 preserved definition/content domains checked field-by-field (four live scheduler heartbeat values excluded from static comparison), protected native recovery verified, no new Telegram deliveries, 0 reminder states/queued jobs/failed jobs. Normal owner login works. Source/schema/security are unchanged and destructive tools are disabled.

Application source did not require a fix. Preparation adapters had rollback/retry corrections: an unassigned pre-booking-only form was replaced with Iris's genuinely assigned existing Learning Profile form; the old Resource PDF was rejected by passive-upload validation and preserved; a preparation-only nonexistent relationship was corrected. Both transient uploads were exactly reconciled and all failed DB phases rolled back. No guard was bypassed. Persistent IDs naturally contain rollback/gap history; no resequencing occurred.

The existing offsite-backup replication warning remains a genuine operational diagnostic outside this request's infrastructure scope; verified native recovery exists, but this report does not claim offsite replication was repaired. Later desktop navigation to Today timed out after the representative owner/Student screens had been inspected. The owner login, Student roster/course/profile states, My Learning/private revision state and Daniel's receivable were actually observed; wider creation/finance/file integrity has independent live evidence. This was a bounded owner-review check, not another Stage 9 acceptance suite.

The new six-person natural dataset remains intact. Its full registry and final-launch removal contract are documented separately. Final-launch cleanup, architecture review, UI redesign, public-copy replacement, live Bunny and unrelated infrastructure work were not performed.
