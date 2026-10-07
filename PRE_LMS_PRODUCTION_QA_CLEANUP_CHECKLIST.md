# Pre-LMS Production QA Cleanup Checklist

**NO CLEANUP EXECUTED.** Owner review and separate exact cleanup approval are required. Starting authority: PRODUCTION_QA_DATASET_MANIFEST.md, run `QA_ACCEPTANCE_2026_10_06_A`; original private final-journal.json has332 registered objects. The tables below reproduce those exact IDs, then append observed acceptance additions. Re-read current references and all descendants before execution; IDs are never guessed.

## Original manifest ownership scopes

| Table / scope | Exact original IDs | Manifest cleanup path |
|---|---|---|
| admin_notifications | 17,18,19,20,21,22,23,24,25,26,27,28 | Manual inbox retention; review Student reset overlap |
| administrators | 6,7,8,9 | Manual staff/factor/session cleanup |
| analytics_events | 1174,1175,1176,1177,1178,1179,1180,1181,1182,1183,1184,1185,1186 | Manual exact QA Analytics |
| audit_logs | 157,158,159,160,161,162,163,164,165,166,167,168,169,170,171,172,173,174,175,176,177,178,179,180,181,182,183,184,185,186,187,188,189,190,191,192,193,194,195,196,197,198,199,200,201,202,203,204,205,206,207,208,209,210,211,212,213,214,215,216,217,218,219,220,221,222,223,224,225,226,227,228,229,230,231,239,240,241,242,243,244,245,246,247,248,249 | Immutable audit: existing retention/privacy policy only; Immutable audit: existing retention/privacy policy only; not manual model delete |
| blog_revisions | 2 | Manual Blog/redirect/revision cleanup; immutable audit retains controlled retention |
| blog_slug_redirects | 2 | Manual Blog/redirect/revision cleanup; immutable audit retains controlled retention |
| blogs | 2 | Manual Blog/redirect/revision cleanup |
| booking_calendar_locks | 40,43,51,52 | Operational lock retention; do not delete during live booking activity |
| booking_events | 21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36 | Reset Students; Financial closure only for package-funded; immutable audit retains controlled retention |
| booking_holds | 21 | Reset Students adapter / manual hold lifecycle |
| booking_policy_decisions | 1,2 | Reset Students; Financial closure only for package-funded; immutable audit retains controlled retention |
| booking_waitlists | 1 | Reset Students |
| bookings | 13,14,15,16,17,18,19,20,21,22 | Reset Students; Financial closure only for package-funded |
| category_translations | 3 | Reset Resources optional Categories; immutable audit retains controlled retention |
| contacts | 11,12,13,14,15,16 | Manual Contact graph; Reset Students does not imply Contact removal |
| content_revisions | 5,6,7,8,9 | Reset Games/translations/revisions; shared Media separate; immutable audit retains controlled retention; Reset Pages; reset translations/revisions; shared media separate; immutable audit retains controlled retention; Reset Resources; private payload after reference review; immutable audit retains controlled retention |
| entity_translation_revisions | 11,12,13,18,19,20,21,22 | Manual staff/factor/session cleanup; immutable audit retains controlled retention |
| faq_translations | 6 | Reset FAQ/translations; immutable audit retains controlled retention |
| faqs | 6 | Reset FAQ/translations |
| form_answers | 1,2,3,4,5,6,7,8 | Manual Form graph; immutable audit retains controlled retention; Reset Students; Financial closure only for package-funded; immutable audit retains controlled retention; immutable audit retains controlled retention |
| form_questions | 10,11,12,13,14,15 | Manual Form graph |
| form_submission_revisions | 1,2 | Reset Students; immutable audit retains controlled retention |
| form_submissions | 1,2,3 | Reset Students; Reset Students; Financial closure only for package-funded; immutable audit retains controlled retention |
| form_triggers | 4,5 | Manual Form graph |
| form_versions | 3,4 | Manual Form graph |
| forms | 3 | Manual Form graph after Student submissions |
| game_translations | 4,5 | Reset Games/translations/revisions; shared Media separate; immutable audit retains controlled retention |
| games | 4,5 | Reset Games/translations/revisions; shared Media separate |
| homeworks | 1,2 | Reset Students |
| learning_milestones | 1,2 | Reset Students |
| learning_plans | 1,2 | Reset Students |
| lesson_feedback | 1 | Reset Students |
| lesson_materials | 2,3,4,5 | Reset Students owns private lesson file; Resource payload separate |
| marketing_touches | 13 | Manual exact QA Analytics; global Reset Analytics only in later authorized scope; immutable audit retains controlled retention |
| media | 2 | Reset Media; check all references first |
| meeting_providers | 4 | Manual after preferences/room/booking refs |
| meeting_rooms | 1,2 | Manual after booking references |
| package_installments | 1,2 | Reset Students / Financial |
| package_renewals | 1 | Reset Students / Financial |
| page_translations | 4,5,6 | Reset Pages/translations; Reset Pages; reset translations/revisions; shared media separate; immutable audit retains controlled retention |
| pages | 4 | Reset Pages; reset translations/revisions; shared media separate |
| payment_methods | 7 | Manual after payment references |
| payment_records | 13,14,15,16,17,18 | Reset Students / Financial |
| payment_refunds | 4,5,6 | Reset Students / Financial |
| promotions | 2,3,4 | Manual Promotion cleanup; shared Media separate |
| recurring_lesson_occurrences | 1,2 | Reset Students |
| recurring_lesson_plans | 1 | Reset Students |
| resource_assignments | 1,2 | Reset Students; Resource reset detaches reference |
| resource_categories | 3 | Reset Resources optional Categories |
| resource_downloads | 2,3 | Manual Contact graph; Reset Students does not imply Contact removal; immutable audit retains controlled retention |
| resource_requests | 4,5 | Manual Contact graph; Reset Students does not imply Contact removal; immutable audit retains controlled retention |
| resource_translations | 2,3,4,5 | Reset Resources/translations; Reset Resources; private payload after reference review; immutable audit retains controlled retention |
| resources | 2,3 | Reset Resources; private payload after reference review |
| session_ledger_entries | 19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38 | Reset Students / Financial |
| session_reschedules | 2 | Reset Students; Financial closure only for package-funded; immutable audit retains controlled retention |
| session_types | 4,5,6 | Manual after all lesson references; Manual after bookings/plans |
| social_links | 7 | Manual social definition cleanup |
| staff_bins | 2,3 | Manual staff note/preferences |
| staff_note_preferences | 1 | Manual staff preferences |
| staff_recent_views | 1,2,3 | Manual recent entries; Student reset removes Student ownership |
| staff_saved_views | 1,2,3 | Manual staff saved views |
| staff_tasks | 1,2 | Reset Students linked task; manual staff cleanup overlap |
| student_bins | 1,2,3 | Reset Students |
| student_error_logs | 1,2 | Reset Students |
| student_operational_alerts | 1,2 | Reset Students |
| student_package_entitlements | 10,11,12,13,14,5,6,7,8,9 | Reset Students / Financial |
| student_packages | 11,12,13,14,15,16,17,18,19,20 | Reset Students / Financial; Reset Students; overlapping Financial reset |
| student_unavailabilities | 1 | Reset Students |
| students | 10,11,12,7,8,9 | Reset Students (all owned graph); no reset now |
| teaching_tags | 1,2 | Reset Students |
| telegram_deliveries | 7,8 | Normal safe notification retention |
| tutor_preparations | 1 | Reset Students |
| visitor_funnel_progressions | 60 | Manual exact QA Analytics; immutable audit retains controlled retention |
| visitor_sessions | 189 | Manual exact QA Analytics; global Reset Analytics only in later authorized scope |
| visitors | 163 | Manual exact QA Analytics |

## Acceptance additions and changed facts

| Scope | Observed IDs / state | Later cleanup boundary |
|---|---|---|
| booking_waitlists | 2 | Student-owned graph; inspect descendants/current state after any Reset Students. |
| bookings | 23,24 | Student-owned graph; inspect descendants/current state after any Reset Students. |
| form_submissions | 4 | Student-owned graph; inspect descendants/current state after any Reset Students. |
| session_ledger_entries | 39,40 | Student-owned graph; inspect descendants/current state after any Reset Students. |
| student_bins | 4 | Student-owned graph; inspect descendants/current state after any Reset Students. |
| student_notifications | 1,2,3,4,5,6,7,8,9,127,128,129,130,131,132,133,134,135,136,137,138,139,140,141,142,143,144,145,146,147,148,149,181,182,183,184,196,197,208,274 | Student-owned graph; inspect descendants/current state after any Reset Students. |
| student_unavailabilities | 2 | Student-owned graph; inspect descendants/current state after any Reset Students. |
| bookings |23B cancelled,24E cancelled | Keep until owner review; include events/reschedule/policy/ledger/material references. |
| session_ledger_entries |39 debit and40 restoration, both booking23/allocation5 | Append-only during ordinary use; future authorized reset must take coherent parent closure. |
| contacts |17 newly created for public booking24 | Contact history may survive Student reset; inspect booking/form/request/acquisition references before exact removal. |
| staff_bins |4QA authored note | Staff domain; original2/3 unchanged; do not delete genuine/other-author notes. |
| staff_saved_views |4operator6 personal replacement-by-name | Remove only the QA owner’s saved view after approval. |
| staff_note_preferences |3operator6 pin/favorite for note2 | Independent personal preference; shared operational pin has its own actor/time. |
| maintenance_visits |15unique QA maintenance path | Analytics domain; original visits1–14 are genuine and excluded from exact QA cleanup. |
| content_revisions / English translation revisions / audits | QA Game4/5 observed content revisions 7,8,10,11,12,13,14,15,16; including temporary embedded test and exact link restoration | Identify by owner entity4/5 plus manifest/recent actor6 audit closure; never broad-remove genuine revisions. |

Original fixture changes are intentional test history, not new deletion permission: A homework1 completed; assignment1 reviewed; authored Student note4; feedback1 updated5; current Form submission3 plus historical2; Assistant task1 in_progress; QA Games4available/5coming_soon with valid /p/ targets; Promotions2–4 valid /p/ targets but original disabled Oct16–17 dates. D’s new holiday/waitlist history is cancelled/withdrawn; original Nov10 active/open preserved. Browser/staff time preferences are returned to their recorded starting display where tested.

## Files and overlapping references

| Owner | Exact disk/path | SHA256 | Later action |
|---|---|---|---|
| lesson_materials:2 | `local:lesson-materials/aaea89d5-b5c5-4906-876f-f64f55ab870a.pdf` | `32758789a19d65a855ab7b44a994f7b5bdc06c3d286d41230fe11e5ccc17a500` | Reset Students with payload/reference review |
| resources:2 | `local:resources/qa-acceptance-20261006-published-workbook-dJ6Ef2oNwg.pdf` | `32758789a19d65a855ab7b44a994f7b5bdc06c3d286d41230fe11e5ccc17a500` | Reset Resources only after teaching-reference review |
| media:2 | `public:media/5vPe26grvXTGalIfxO2AO37FVCqZzXMmNgXpQziC.png` | `76d9878851952ef2d158f52cf1dfe59bd698d10dd8aab89da54dbac36f028f3b` | Reset Media after Page/Blog/Game/Promotion reference review |
| Rolled-back content setup upload; no database owner | `public:media/fRivv6aCvoZueU0gpRdbkD82CfxN8pIRf7hli2XK.png` | `76d9878851952ef2d158f52cf1dfe59bd698d10dd8aab89da54dbac36f028f3b` | Manual exact unreferenced setup payload removal after review |

The known unreferenced passive PNG is setup debris from rollback, not an application defect. Remove only the exact listed unreferenced path after a fresh Media/cover/Page/Blog/Game/Promotion reference check. The lesson PDF and Resource PDF have identical bytes but separate owned paths; do not confuse content equality with ownership/reference equality. Genuine Alphabet Resource1 and its private PDF are protected.

## Archives and temporary evidence

| Operation | Owner / type / current status | Exact private archive path |
|---|---|---|
| 1 | 6 / export / pending | No archive |
| 2 | 6 / export / completed | `development-data/exports/fb3bcae3-5b1f-4e4e-a1d3-fefa05178bb9.zip` |
| 3 | 6 / import / superseded | `development-data/imports/87c18e91-0eaf-40c5-82d2-44282645d594.zip` |
| 4 | 6 / import / completed | `development-data/imports/87c18e91-0eaf-40c5-82d2-44282645d594.zip` |
| 5 | 6 / import / superseded | `development-data/imports/fd786070-aed8-4f6e-9beb-26b345285a9b.zip` |
| 6 | 6 / import / pending | `development-data/imports/fd786070-aed8-4f6e-9beb-26b345285a9b.zip` |

Operations3/4 share one import archive;5/6 share another. Deduplicate physical paths before any approved artifact removal. Operation2 export remains hash verified and bounded QA-only; operation4 imports0rows/files and skips1identical profile. Pending previews may be naturally expired; do not extend expiry. Recovery archives, failed/negative inspection artifacts and this private local/remote evidence folder need explicit manual-retention review, not blind folder deletion.

Protected exclusions: storage/app/backups/pre-lms-deploy-20261006-7cb9a23.tar.gz and its recovery folder; storage/app/backups/pre-lms-acceptance-20261007-40be0b5.tar.gz and its SQL/source/environment/vendor/wrapper/private-file recovery folder. Do not classify them as managed/disposable backups. Preserve the pre-existing untracked .env.backup.before-7123f15. Actual local downloaded PDF/ZIP/CSV/XLSX/ICS/screenshot evidence remains outside Git and is disposable only after owner review/approval.

## Order and overlap safeguards

1. Owner reviews populated production and approves a concrete ID/file plan; make and verify protected native recovery first.
2. Capture current genuine/QA ownership, typed-money/booking provenance, file hashes, role/session and enabled-flag state; reject mixed/genuine scope.
3. Student reset can already remove owned finance, allocations, bookings, teaching, notes, forms, notifications, planning and active auth. Inspect what remains before any separate Finance/Booking action; never double-delete assumed descendants.
4. Contacts, acquisition/analytics, shared Resources/Categories, staff records/preferences/views, CMS/revisions/media and Telegram receipts can survive Student closure. Recompute references; handle only exact proven QA residues.
5. Resource/Media resets require teaching, Page/Blog/Game/Promotion/cover/shared-payload reference review. Delete a payload only after its last permitted owner/reference is removed.
6. Preserve genuine analytics and original maintenance visits if using exact QA cleanup; broad Analytics Reset is a different destructive scope and needs its own approval. Use original registered IDs plus the acceptance campaign, game-patch campaign, authenticated QA ownership and stored event/action/time receipts to derive additional IDs; do not delete by approximate date/name alone.
7. Keep Telegram status receipts under normal retention; only one START and one FINAL are authorized for this stage. No bot/destination/rule/polling/command cleanup.
8. Remove temporary QA staff only after their task/note/preference/audit/operation references and sessions are reviewed; protect owner1bolt@admin.com/Abdallah and its security fields.
9. Confirm maintenance original rows exactly restored, destructive tools false/navigation-hidden/direct-denied, current FK/typed/financial/policy/UTC/private-file/genuine-owner integrity, then record actual result. No cleanup has been performed by this checklist.

Final temporary settings: maintenance/system/message exact rows restored after15seconds; destructive flag original absence/defaultfalse/cachefalse restored; no scheduler/worker/mail/bot/rule/commands change. Temporary game4 target restored to its original /p/ page after testing. Source and receipt metadata, audit/history and credentials have distinct retention boundaries: never publish or copy credentials into cleanup reports.
