# Production Application Acceptance Report

**PRE-LMS STABLE BASELINE**. All 153 permanent Capability IDs have one final disposition. The result separates production observations from isolated functional/security mutation tests; a rendered page alone is not certification of its write actions.

Production Application SHA: `4296b21368cbc9c564d0cb7a85e29b2ae408e0ee`. Prior application releases: `40be0b5f2cfc663cbe25afdd712bf41b85500409` / `addda6f227f05f206db24a6f1adc1c1eb70ee1f9`. Documentation baseline at entry: `e1e506fa6408856fcdef65d5fb1558027688b2b8`. Final Documentation HEAD SHA is recorded in the Codex delivery receipt after publication; a Git commit cannot embed its own content hash. Documentation commits are not redeployed.

Runtime-relevant differences from the accepted application to subsequent documentation publication: NONE; only requested Markdown reports/tracker/matrix annotations. Existing unrelated untracked Hostinger/historical proposal documents are excluded. No LMS, cleanup, redesign, Hostinger operations handoff, inbound Telegram or scheduler/worker configuration was performed.

QA run: `QA_ACCEPTANCE_2026_10_06_A`. Acceptance run: `PRE_LMS_ACCEPTANCE_2026_10_07_A`. Original master matrix SHA256: `57ce8326521f95011d02eaf399ea50e9fec9e136f7eb59e352ea4155bb4a5dd7`. All capability IDs and meanings remain stable.

## Capability reconciliation

| Audience | Total | PASS | PARTIAL | FAIL | NOT TESTABLE |
|---|---:|---:|---:|---:|---:|
| PUBLIC | 26 | 21 | 5 | 0 | 0 |
| STUDENT | 28 | 26 | 2 | 0 | 0 |
| SUPER ADMIN | 99 | 58 | 41 | 0 | 0 |
| **TOTAL** | **153** | **105** | **48** | **0** | **0** |

PARTIAL means a named production branch is constrained while current-source isolated functional coverage passes. It is not an invented missing feature or a concealed confirmed application failure. No untested credential change, irreversible reset, merge, expiry manipulation or extra Telegram delivery was performed merely to manufacture PASS.

## Engineering release gate

| Gate | Result / evidence |
|---|---|
| Full current-schema PHP | PASS 1,044 tests /9,004 assertions; full-accepted-release.log/xml |
| Dedicated MariaDB concurrency | PASS 26 tests /152 assertions; concurrency-accepted-release.log/xml; real independent workers, dedicated local MariaDB10.11.18 |
| Stage1–5 and new PRELMS regressions | Included in the final1044 cases; exact executed class/method map retained in acceptance-case-map.json. Historical intermediate-schema MigrationACompatibilityTest is deliberately excluded, not counted as passed. |
| JavaScript / production build | PASS 20 tests; javascript-final-all.log; build-final-009.log |
| Blade/routes/Pint/whitespace | Local and production Blade compiled; final local route cache compiled then cleared; Pint dirty agent passes; git diff --check passes. |
| Composer/npm security audits | Both current lockfile audits have zero advisories of every severity; composer-audit-final.json/npm-audit-final.json. Lockfiles unchanged after the approved patch. |
| PHPStan | Established 257 → 255 findings; zero added/two resolved; complete file/identifier/message multiset comparison, no baseline/ignore/level reduction. This is no-regression, not a claim of zero historical debt. |

## Production state and independent domain proof

The last sealed native/domain checkpoints report110 tables,75 current migrations,10 generated columns,4 typed guards/triggers, zero unexpected FK orphans and zero native ownership/grant/refund/negative-unit/restoration issues. Every pre-QA genuine row digest and the owner protected digest match. Genuine Alphabet and QA private PDF hashes match; all referenced lesson files exist. All booking UTC/customer/business snapshots and policy snapshots reconcile.

New QA Booking23: one_hour allocation5/purchase11/debit39, created Oct23 09Cairo then normally rescheduled to Oct30 09Cairo (07:00UTC after DST), with one consumption row. Staff cancellation appends restoration40 to the same allocation/type once. Payments/refunds remain unchanged; purchase11 stays100net/30paid/70due. Booking24: real public free intake for existing Student11, Oct21 13:15–14:15UTC, no allocation/debit/payment; token cancellation retains history. Actual904-byte ICS contains matching canonical instants.

Actual notice-only valid-grid rejection: Oct7 09:00Cairo, inside the existing12-hour notice horizon; service rejects the notice branch, no booking/configuration/clock change. Purchase15 is expired/ineligible;16 remains eligible on its actual Oct7 expiry day;19 diagnostic payment remains within its real48-hour window. Separate one_hour allocations cannot pool to fund type6. Current selector lists active types, then disables the unavailable selection; it does not hide every incompatible type.

Independent money projections retain the intentional USD5 overpayment on purchase12: original90−discount10=80net; gross payments90−refund5=85net paid; overpayment5. This one expected canonical warning is not an unexpected integrity defect. Purchase11 due70;13 net-paid0/due20 after full refund;14 EUR net-paid25/due5. Installments remain forecast rather than payments, and currencies are separate.

Real browser notifications were absent before portal visits, then synchronized and persisted for the correct owner. A read remains read after revisits; dedup/history are checked. A’s homework1 is tutor-completed with preserved student response/feedback; resource assignment1 reviewed; authored note4 persists; feedback1 now5; current questionnaire submission3 retains prior submitted version. D’s added Nov12 holiday and Nov13 waitlist are cancelled/withdrawn with history, while original Nov10 records remain active/open. Staff note4, saved view4 and personal preference3 are retained for owner review.

## Browser, files and analytics

Actual browser Resource request7 produces a visible unconsumed link; explicit download saves733 bytes, SHA256 `32758789a19d65a855ab7b44a994f7b5bdc06c3d286d41230fe11e5ccc17a500`. Replay returns the gate and creates no second successful download. The earlier controller-only proof could not establish this client behavior; PRELMS-007 fixes the reproduced defect.

Actual CSV/XLSX downloads are parsed for semantic numeric parity/private-no-store delivery: selected Student B Cashier8 monetary rows, receivables5 purchases, statement4 data rows, filtered roster1 Student, and uniquely filtered maintenance1 visit. Generated balance-summary timestamps differ only within the real request window. Formula escaping and omitted private/Assistant-hidden answers are exercised in FilterAwareExportsTest; no malicious formula was inserted into genuine production data. Country exports compare actual all-country/date scope; unsupported country=EG is not treated as a country filter.

Fresh real campaign event snapshot has415 first-party events including booking_cancelled, booking_completed, booking_cta_clicked, booking_slot_held, game_completed, game_opened, game_started, outbound_link_clicked, page_view, resource_downloaded, resource_gate_viewed, resource_requested, section_dwell, section_view, session_started, short_form_completed, social_link_clicked, whatsapp_clicked. No duplicate non-null client UUID appears. Raw-IP checks on sessions/audit/reschedules/maintenance/cache and invalid JSON cells are zero. Existing simulated setup telemetry is excluded from proof of real browser instrumentation. Native TikTok and WhatsApp destinations opened; no login/message sent. Separate /6word curated round starts/finishes independently; current external completion is not assumed to be a tutoring-app receipt.

Maintenance window03:48:11–03:48:26UTC lasted15 seconds. Anonymous real visit15 appears with Egypt/EG and requested QA path; CSV/XLSX have one matching row. Exact original maintenance/system/message rows including timestamps were restored and the public homepage reopened. Automatic existing runtime heartbeat/backup/Telegram maintenance-state changes are distinguished from configuration changes.

## Development tools and recovery

Original .env has no destructive-tools key; default/cached effective state false. Approved temporary enablement supported safe QA-only export/inspection/no-op import, then exact original environment/cache bytes and modes were restored. Fresh independent inspection and browser direct404/navigation-hidden proof confirm false. No reset executed. Export operation2 ZIP5089 bytes, SHA256 `f7f939b91b1fd95efc6527d1d2a1f195a01c4c066436b48708c40240fb7ff3e3`; selected Student7/material closure includes1Student/1Booking/4materials/1Resource/1category/0verified emails. Entry hashes/size/compatibility verify; actual server secret scan finds neither APP_KEY nor actual Telegram token. Optional manifest application_sha is truthfully null on proc_open-disabled Hostinger; deployment SHA is established independently.

Operation4 selected profile import restores0 rows/files, skips1 identical profile, reports0orphans; source fixtures unchanged. Version999 and secret-field archives are rejected; operation6 conflict preview refuses overwrite. Peer Super cannot inspect/download another operator’s export404; Admin/Assistant403 and anonymous302. Missing-row restore/all-modules/financial/reset/recovery-failure branches are isolated tests and remain PARTIAL where production execution was deliberately omitted.

Protected recovery: pre-lms-deploy-20261006-7cb9a23.tar.gz SHA256 `0454ac9211c980c175e5145b5d4d16b4310e2e512f11de1dda4366102e8b6ea3`; fresh native pre-lms-acceptance-20261007-40be0b5.tar.gz23660877bytes/mode600 SHA256 `f719b24e23020e7e10a90d2b7fd72a2aad0b279939b3bdc2ca316c9530e96220`. Fresh SQL/source/environment/vendor/private/public/wrapper snapshots and allSHA256SUMS were verified; temporary dump credentials removed. These protected backups are not disposable managed archives. The later one-line view repair needs no DB migration; prior source addda6f is the rollback reference. No BackupService call generated extra Telegram notifications.

## Defects, limits and release decision

Nine confirmed application defects: Critical0/High1/Medium8/Low0; all nine repaired, pushed, deployed and their established failures retested. No remaining confirmed Critical/High application defect. See PRE_LMS_DEFECT_AND_REMEDIATION_LOG.md for source/production evidence, exact fix SHAs and meaningful red/green cases.

GO criteria satisfied for owner review: no unresolved Critical/High foundational defect, engineering and final production integrity pass, every153ID reconciled, and the named production safety/timing/inbox/legacy limits do not invalidate the tested LMS foundations. No QA cleanup or LMS work begins automatically.

Operations debt: pre-existing s3_replication_failed is offsite replication debt; protected local native recovery remains valid. Production mail is intentionally log, so actual email inbox delivery is not certified. Existing scheduler/worker heartbeats were observed; no absence was invented and no operations handoff/configuration executed. Retain these for owner operations review.

Tooling exclusions: Windows php.bat regex interception, first test-only missing meeting-provider seed, an initial nonreproduced cancellation lookup, wrong fixture-name/type/text assumptions, transient browser popup notifications, and unsupported country query were diagnosed separately. Final PHP/concurrency gates replace failed intermediate runs. Three final HTTP assertions expected page text that the accepted current view does not contain; corrected own-email/QA-content assertions and foreign denials pass. Their failed attempts remain in the private log and are not application failures.

## Owner Manual Review Recommended

Review public booking/mobile flow and wording; Student next action/learning/forms/notifications/timezone; Cashier/receivables/receipt/overpayment; lesson workspace and staff operations; reports/export labels; account/security surfaces; game/card availability and scheduled promotions. Existing footer and floating WhatsApp numbers differ; inspect the configured owner destinations. FAQ/public contact-reschedule wording may feel different from authenticated self-service rescheduling. Wrong export confirmation requires a fresh preview. Device/browser timezone selection takes precedence over account fallback. These are owner product/UX judgments, not permission to redesign or change business rules here.

## Evidence retention and notification

Private evidence folder: `C:\Users\e\AppData\Local\Temp\pre-lms-acceptance-20261007-A`. JSON/JUnit/logs/screenshots/actual export/PDF/ZIP/ICS artifacts are retained outside Git; cookie/QA credential and protected backup secrets are never copied into this report. START notification attempted/succeeded: delivery9, message13,2026-10-06T22:00:31Z through the existing verified pathway. FINAL: not sent yet; send exactly once after reporting/publication, then append only safe receipt metadata. No progress deliveries.

## Complete153-ID ledger

### PUB-001 — Home and configurable landing sections

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Public content |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Public content. Home and configurable landing sections. Published content and configured section switches determine rendering; empty libraries remain valid. |
| Business rules | Published content and configured section switches determine rendering; empty libraries remain valid. Marketing copy is not a financial ledger. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [PUB-002](#pub-002), [PUB-004](#pub-004), [PUB-023](#pub-023) |
| QA scenarios | Existing surface/current configuration |
| Actual fixture/reference | Current configuration/private runtime checkpoints; seven setup gaps resolved/classified above |
| Positive test | Executed isolated mutation/projection assertions: PublicExperienceTest::test_homepage_loads_successfully_with_hero_and_content; CmsDraftAndPreviewMatrixTest::test_resource_draft_update_preserves_live_version_and_saves_content_revision |
| Negative/edge test | Executed isolated edge/security assertions: CmsDraftAndPreviewMatrixTest::test_preview_routes_reject_unauthenticated_guests_even_with_fake_parameters; PublicExperienceTest::test_gated_resource_cannot_be_downloaded_without_token_or_email_submission |
| Browser/UI evidence | public-real-browser-home (2026-10-06T23:09:29.975Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/HomeController.php](app/Http/Controllers/HomeController.php)<br>[resources/views/public/home.blade.php](resources/views/public/home.blade.php) |

### PUB-002 — Public navigation, footer and responsive menu

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Navigation |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Navigation. Public navigation, footer and responsive menu. Navigation keeps real public destinations, language links, Student entry and booking CTA; mobile menu does not alter authorization.. |
| Business rules | Navigation keeps real public destinations, language links, Student entry and booking CTA; mobile menu does not alter authorization. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [PUB-003](#pub-003), [PUB-019](#pub-019), [PUB-025](#pub-025) |
| QA scenarios | Existing surface/current configuration |
| Actual fixture/reference | Current configuration/private runtime checkpoints; seven setup gaps resolved/classified above |
| Positive test | Executed isolated mutation/projection assertions: AdminDrawerAndSocialAccessibilityTest::test_public_footer_social_links_have_icon_and_visible_label; AdminDrawerAndSocialAccessibilityTest::test_public_navigation_contains_language_switcher |
| Negative/edge test | Executed isolated edge/security assertions: LocalizedPublicFlowAndSeoTest::test_stale_translation_remains_visible_and_participates_in_hreflang; PublicExperienceTest::test_gated_resource_cannot_be_downloaded_without_token_or_email_submission |
| Browser/UI evidence | public-real-browser-home (2026-10-06T23:09:29.975Z); public-mobile-navigation (2026-10-07T03:46:56.874Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [resources/views/layouts/public.blade.php](resources/views/layouts/public.blade.php)<br>[app/Http/Controllers/HomeController.php](app/Http/Controllers/HomeController.php) |

### PUB-003 — English, French and German route/translation presentation

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Localization/SEO |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Localization/SEO. English, French and German route/translation presentation. Explicit locale choice takes precedence; first-visit FR or DE/AT country hints select language, not scheduling timezone. |
| Business rules | Explicit locale choice takes precedence; first-visit FR or DE/AT country hints select language, not scheduling timezone. English fallback/stale translation handling preserves unpublished drafts; canonical/hreflang/trailing-slash rules apply. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [PUB-002](#pub-002), [SA-066](#sa-066) |
| QA scenarios | Existing surface/current configuration |
| Actual fixture/reference | media:2, pages:4, page_translations:4, content_revisions:5, content_revisions:6 |
| Positive test | Executed isolated mutation/projection assertions: LocalizedPublicFlowAndSeoTest::test_policy_pages_render_fallback_banner_and_english_canonical_with_zero_hreflang_when_requested_in_french_or_german; CountryInitialLocaleTest::test_france_germany_and_austria_choose_existing_localized_routes |
| Negative/edge test | Executed isolated edge/security assertions: LocalizedPublicFlowAndSeoTest::test_stale_translation_remains_visible_and_participates_in_hreflang; BookingLanguageSwitchSyncTest::test_switch_language_with_expired_hold_resets_to_step_two_and_retains_contact_data |
| Browser/UI evidence | public-explicit-french (2026-10-07T03:44:37.702Z); public-explicit-german (2026-10-07T03:46:38.569Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Middleware/SetRequestLocale.php](app/Http/Middleware/SetRequestLocale.php)<br>[app/Domains/CMS/Services/LocalizedUrlService.php](app/Domains/CMS/Services/LocalizedUrlService.php)<br>[app/Http/Controllers/HomeController.php](app/Http/Controllers/HomeController.php)<br>[app/Http/Controllers/ResourceController.php](app/Http/Controllers/ResourceController.php)<br>[app/Http/Controllers/BookingController.php](app/Http/Controllers/BookingController.php) |

### PUB-004 — About, FAQ, legal and published custom pages

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Public content |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Public content. About, FAQ, legal and published custom pages. Published current content is served; drafts/archived pages are excluded. |
| Business rules | Published current content is served; drafts/archived pages are excluded. FAQ expansion is local UI interaction. A separate free-form Contact submission endpoint is not present. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [PUB-003](#pub-003), [SA-064](#sa-064), [SA-065](#sa-065) |
| QA scenarios | Q08 |
| Actual fixture/reference | media:2, pages:4, faqs:6, social_links:7, blogs:2, games:4, games:5, promotions:2, promotions:3, promotions:4, page_translations:5, page_translations:6, resource_translations:4, resource_translations:5, page_translations:4, blog_slug_redirects:2, blog_revisions:2, faq_translations:6, game_translations:4, game_translations:5, content_revisions:5, content_revisions:6, content_revisions:7, content_revisions:8 |
| Positive test | Executed isolated mutation/projection assertions: CmsAndMediaWorkflowTest::test_home_and_about_cms_edits_render_on_public_pages; CmsAndMediaWorkflowTest::test_published_version_stays_public_during_draft_edits_and_explicit_publish_switches_versions |
| Negative/edge test | Executed isolated edge/security assertions: CmsAndMediaWorkflowTest::test_draft_page_never_leaks_publicly_to_unauthenticated_visitors; CmsAndMediaWorkflowTest::test_valid_signed_preview_url_is_denied_to_unauthenticated_guests |
| Browser/UI evidence | public-qa-published-page (2026-10-07T03:44:28.317Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/PageController.php](app/Http/Controllers/PageController.php) |

### PUB-005 — Coaching-track and lesson pricing presentation

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Pricing |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Pricing. Coaching-track and lesson pricing presentation. Shows Diagnostic, Foundation, Fluency and conditional/unlisted offers with diagnostic-credit explanation. |
| Business rules | Shows Diagnostic, Foundation, Fluency and conditional/unlisted offers with diagnostic-credit explanation. Booking SessionType prices and staff purchase presets have separate authorities; browsing does not buy/grant/charge anything. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [PUB-006](#pub-006), [SA-027](#sa-027), [SA-028](#sa-028) |
| QA scenarios | Existing surface/current configuration |
| Actual fixture/reference | faqs:6, faq_translations:6 |
| Positive test | Executed isolated mutation/projection assertions: CashierBillingReconciliationTest::test_eligible_track_enrollment_applies_diagnostic_credit_on_the_server; PublicExperienceTest::test_games_catalog_and_interactive_event_tracking |
| Negative/edge test | Executed isolated edge/security assertions: TypedEntitlementsTest::test_two_one_hour_rights_cannot_pay_for_a_two_hour_lesson; TypedEntitlementsTest::test_reschedule_keeps_original_debit_for_same_or_compatible_lesson_and_rejects_incompatible_type |
| Browser/UI evidence | public-booking-format-selection (2026-10-07T03:39:36.168Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/PricingController.php](app/Http/Controllers/PricingController.php)<br>[resources/views/public/pricing.blade.php](resources/views/public/pricing.blade.php) |

### PUB-006 — Choose an active public lesson format

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Booking |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Booking. Choose an active public lesson format. Selecting an inactive/nonexistent format is rejected. |
| Business rules | Selecting an inactive/nonexistent format is rejected. Switching format clears selected slot; the public finalizer creates a direct booking, not a typed package purchase or ledger debit. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [PUB-005](#pub-005), [PUB-007](#pub-007), [PUB-008](#pub-008), [PUB-011](#pub-011) |
| QA scenarios | Existing surface/current configuration |
| Actual fixture/reference | session_types:4, session_types:5, social_links:7 |
| Positive test | Executed isolated mutation/projection assertions: BookingEngineTest::test_creates_booking_with_complete_immutable_timezone_snapshots_and_booking_event; BookingEngineTest::test_concurrent_collision_throws_slot_unavailable_exception |
| Negative/edge test | Executed isolated edge/security assertions: TypedEntitlementsTest::test_two_one_hour_rights_cannot_pay_for_a_two_hour_lesson; TypedEntitlementsTest::test_reschedule_keeps_original_debit_for_same_or_compatible_lesson_and_rejects_incompatible_type |
| Browser/UI evidence | public-booking-format-selection (2026-10-07T03:39:36.168Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Livewire/BookingWizard.php](app/Livewire/BookingWizard.php)<br>[resources/views/livewire/booking-wizard.blade.php](resources/views/livewire/booking-wizard.blade.php)<br>[app/Http/Controllers/BookingController.php](app/Http/Controllers/BookingController.php) |

### PUB-007 — Searchable timezone selection, flags and local slot labels

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Timezones |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Timezones. Searchable timezone selection, flags and local slot labels. Uses valid IANA zones and date-specific UTC offsets; detection cannot replace an explicit manual selection/owned hold. |
| Business rules | Uses valid IANA zones and date-specific UTC offsets; detection cannot replace an explicit manual selection/owned hold. No-match, selected result, Escape/focus return and countryless zones are handled. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [PUB-008](#pub-008), [STU-008](#stu-008) |
| QA scenarios | Existing surface/current configuration |
| Actual fixture/reference | social_links:7 |
| Positive test | Executed isolated mutation/projection assertions: BookingLanguageSwitchSyncTest::test_timezone_switches_refresh_customer_slot_display_and_preserve_the_owned_hold; ReschedulePresentationTest::test_public_and_student_use_one_timezone_picker_calendar_and_slot_presentation with data set "Cairo" |
| Negative/edge test | Executed isolated edge/security assertions: ReschedulePresentationTest::test_invalid_month_or_timezone_input_falls_back_safely; BookingLanguageSwitchSyncTest::test_switch_language_with_expired_hold_resets_to_step_two_and_retains_contact_data |
| Browser/UI evidence | student-d-explicit-timezone-persisted (2026-10-07T03:38:53.504Z); public-booking-format-selection (2026-10-07T03:39:36.168Z); confirmation-timezone-repaired (2026-10-07T03:46:37.112Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | PRELMS-008 |
| Fix commit SHA | 70b60d3edd02a23cf46a7457a50109e8a5aabf1d |
| Deployed fix SHA | 70b60d3edd02a23cf46a7457a50109e8a5aabf1d |
| Retest | Actual9:15AM New York now labels America/New_York UTC-04:00. Summer/winter regression8/103 and unchanged persisted snapshot pass. |
| Authoritative current source | [resources/views/components/timezone-selector.blade.php](resources/views/components/timezone-selector.blade.php)<br>[app/Domains/Timezone/Services/TimezoneService.php](app/Domains/Timezone/Services/TimezoneService.php)<br>[app/Http/Controllers/BookingController.php](app/Http/Controllers/BookingController.php) |

### PUB-008 — Customer-calendar dates and authoritative available slots

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Availability |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Availability. Customer-calendar dates and authoritative available slots. Weekly business-time windows, date exceptions, notice, horizon, buffers and existing holds/conflicts constrain slots. |
| Business rules | Weekly business-time windows, date exceptions, notice, horizon, buffers and existing holds/conflicts constrain slots. Availability duration overrides are independent of typed entitlement units; arbitrary client timestamps are not authoritative. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [PUB-006](#pub-006), [PUB-007](#pub-007), [SA-045](#sa-045), [SA-046](#sa-046) |
| QA scenarios | Q04 |
| Actual fixture/reference | session_types:4, session_types:5, booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: AvailabilityTest::test_recurring_weekly_rules_generate_slots_in_customer_timezone; AvailabilityTest::test_buffer_time_between_slots_is_respected |
| Negative/edge test | Executed isolated edge/security assertions: CanonicalAvailabilityValidationTest::test_no_rules_configured_rejects_arbitrary_future_slots; CanonicalAvailabilityValidationTest::test_disabled_only_rules_reject_slots |
| Browser/UI evidence | student-b-authoritative-slots (2026-10-07T03:27:47.111Z); public-booking-format-selection (2026-10-07T03:39:36.168Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Availability/Services/AvailabilityService.php](app/Domains/Availability/Services/AvailabilityService.php)<br>[app/Domains/Availability/Services/SlotResolver.php](app/Domains/Availability/Services/SlotResolver.php)<br>[app/Http/Controllers/BookingController.php](app/Http/Controllers/BookingController.php) |

### PUB-009 — Authenticated temporary reservation holds

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Booking |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Booking. Authenticated temporary reservation holds. Slot selection acquires an authenticated visitor/session-owned hold under calendar locks. |
| Business rules | Slot selection acquires an authenticated visitor/session-owned hold under calendar locks. Forged, expired, conflicting or mismatched holds refuse finalization; throttling limits reservation attempts. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [PUB-008](#pub-008), [PUB-011](#pub-011) |
| QA scenarios | Q04 |
| Actual fixture/reference | booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: BookingEngineTest::test_creates_booking_with_complete_immutable_timezone_snapshots_and_booking_event; BookingEngineTest::test_concurrent_collision_throws_slot_unavailable_exception |
| Negative/edge test | Executed isolated edge/security assertions: BookingHoldAuthenticationTest::test_client_timestamps_cannot_override_the_authenticated_hold_interval; BookingEngineTest::test_booking_idempotency_returns_existing_booking_without_duplicate |
| Browser/UI evidence | public-booking-held-review (2026-10-07T03:40:39.646Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Booking/Services/BookingHoldService.php](app/Domains/Booking/Services/BookingHoldService.php)<br>[app/Livewire/BookingWizard.php](app/Livewire/BookingWizard.php)<br>[app/Http/Controllers/BookingController.php](app/Http/Controllers/BookingController.php) |

### PUB-010 — Identity details and current pre-booking questionnaire

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Booking/intake |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Booking/intake. Identity details and current pre-booking questionnaire. Required first/last name, email, normalized phone/country and DOB support canonical Student provisioning. |
| Business rules | Required first/last name, email, normalized phone/country and DOB support canonical Student provisioning. Conditional/required intake validation follows current published version; version drift/identity conflicts refuse. Language switches preserve pending inputs and owned hold state. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [PUB-009](#pub-009), [PUB-011](#pub-011), [SA-070](#sa-070) |
| QA scenarios | Q04, Q09 |
| Actual fixture/reference | forms:3, form_versions:3, form_questions:10, form_questions:11, form_questions:12, form_triggers:4, form_triggers:5, booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, form_submissions:2, form_submissions:3, form_versions:4, form_questions:13, form_questions:14, form_questions:15, form_submission_revisions:1, form_submission_revisions:2, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:4, form_answers:5, form_answers:6, form_answers:7, form_answers:8, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: BookingLanguageSwitchSyncTest::test_switch_language_to_german_at_confirmation_step_preserves_hold_and_details; ContactIdentityTest::test_contact_merge_locks_calendar_contacts_students_then_bookings |
| Negative/edge test | Executed isolated edge/security assertions: BookingLanguageSwitchSyncTest::test_switch_language_with_expired_hold_resets_to_step_two_and_retains_contact_data; FormsEngineTest::test_drafts_freeze_old_versions_and_stale_builder_posts_conflict |
| Browser/UI evidence | public-booking-held-review (2026-10-07T03:40:39.646Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Livewire/BookingWizard.php](app/Livewire/BookingWizard.php)<br>[app/Domains/Booking/Services/BookingService.php](app/Domains/Booking/Services/BookingService.php)<br>[app/Http/Controllers/BookingController.php](app/Http/Controllers/BookingController.php) |

### PUB-011 — Review and atomically confirm a direct booking

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Booking |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Booking. Review and atomically confirm a direct booking. Server-held UTC times are authoritative. |
| Business rules | Server-held UTC times are authoritative. Calendar/identity locks and unique idempotency prevent overlap/double creation. Direct public bookings create no package payment or typed debit; email/Telegram notification failure does not undo a valid booking. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [PUB-009](#pub-009), [PUB-010](#pub-010), [PUB-012](#pub-012), [SA-051](#sa-051) |
| QA scenarios | Q04 |
| Actual fixture/reference | booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: BookingAttributionPersistenceTest::test_full_booking_path_preserves_first_touch_and_records_last_non_direct_conversion_touch; BookingAttributionPersistenceTest::test_booking_attribution_older_than_30_days_falls_back_to_direct |
| Negative/edge test | Executed isolated edge/security assertions: BookingAttributionPersistenceTest::test_stale_utm_greater_than_30_days_yields_direct_none_without_fabricated_touch; BookingCreationNotificationTest::test_idempotent_booking_replay_emits_no_duplicate_notification |
| Browser/UI evidence | public-booking-held-review (2026-10-07T03:40:39.646Z); public-booking-confirmed (2026-10-07T03:40:56.856Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Booking/Services/BookingService.php](app/Domains/Booking/Services/BookingService.php)<br>[app/Livewire/BookingWizard.php](app/Livewire/BookingWizard.php)<br>[app/Http/Controllers/BookingController.php](app/Http/Controllers/BookingController.php) |

### PUB-012 — Token-protected confirmation, calendar download and contact-to-reschedule instruction

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Booking confirmation |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Booking confirmation. Token-protected confirmation, calendar download and contact-to-reschedule instruction. Opaque confirmation token grants access to its booking page. |
| Business rules | Opaque confirmation token grants access to its booking page. ICS uses canonical booking times. Public reschedule GET redirects to confirmation/contact instructions; POST self-service rescheduling always returns 403 and is not a capability. Meeting links remain time/status/identity gated. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [PUB-011](#pub-011), [PUB-013](#pub-013), [STU-028](#stu-028) |
| QA scenarios | Q04 |
| Actual fixture/reference | booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: BookingEngineTest::test_ics_calendar_generator_produces_rfc5545_compliant_vcalendar; StudentReschedulingTest::test_student_reschedule_uses_server_slot_preserves_status_and_is_idempotent |
| Negative/edge test | Executed isolated edge/security assertions: BookingEngineTest::test_booking_creation_rejects_mismatched_hold_token; StudentReschedulingTest::test_student_cannot_reschedule_another_students_booking_or_one_within_24_hours |
| Browser/UI evidence | public-booking-confirmed (2026-10-07T03:40:56.856Z); public-token-cancellation (2026-10-07T03:42:12.551Z); confirmation-timezone-repaired (2026-10-07T03:46:37.112Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | PRELMS-008 |
| Fix commit SHA | 70b60d3edd02a23cf46a7457a50109e8a5aabf1d |
| Deployed fix SHA | 70b60d3edd02a23cf46a7457a50109e8a5aabf1d |
| Retest | Actual9:15AM New York now labels America/New_York UTC-04:00. Summer/winter regression8/103 and unchanged persisted snapshot pass. |
| Authoritative current source | [app/Http/Controllers/BookingController.php](app/Http/Controllers/BookingController.php)<br>[app/Domains/Booking/Services/IcsGenerator.php](app/Domains/Booking/Services/IcsGenerator.php) |

### PUB-013 — Customer cancellation through a confirmation token

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Booking policy |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Booking policy. Customer cancellation through a confirmation token. Past customer cancellation is refused; cutoff and late restore/retain/deny come from booking snapshot (legacy null snapshot uses version-0 settings). |
| Business rules | Past customer cancellation is refused; cutoff and late restore/retain/deny come from booking snapshot (legacy null snapshot uses version-0 settings). Restoring credit reverses original quantity/allocation exactly once; cancellation creates no money refund. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [PUB-012](#pub-012), [SA-043](#sa-043), [SA-047](#sa-047) |
| QA scenarios | Q04 |
| Actual fixture/reference | booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: BookingLifecycleAndPolicyCutoffTest::test_customer_can_cancel_with_at_least_four_hours_notice; BookingLifecycleAndPolicyCutoffTest::test_admin_can_override_cancellation_and_reschedule_cutoffs |
| Negative/edge test | Executed isolated edge/security assertions: BookingLifecycleAndPolicyCutoffTest::test_customer_cancel_rejected_inside_cutoff; BookingLifecycleAndPolicyCutoffTest::test_customer_reschedule_rejected_inside_cutoff |
| Browser/UI evidence | public-token-cancellation (2026-10-07T03:42:12.551Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Booking/Services/CancellationService.php](app/Domains/Booking/Services/CancellationService.php)<br>[app/Domains/Booking/Services/BookingPolicyService.php](app/Domains/Booking/Services/BookingPolicyService.php)<br>[app/Http/Controllers/BookingController.php](app/Http/Controllers/BookingController.php) |

### PUB-014 — Published Resource Library and translated detail pages

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Resources |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Resources. Published Resource Library and translated detail pages. Future/draft/withdrawn resources are excluded; metadata does not expose private storage paths. |
| Business rules | Future/draft/withdrawn resources are excluded; metadata does not expose private storage paths. Library supports category selection and 12-item pagination; detail can show English fallback/stale translation status. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [PUB-003](#pub-003), [PUB-015](#pub-015), [SA-061](#sa-061), [SA-062](#sa-062) |
| QA scenarios | Q07 |
| Actual fixture/reference | resource_categories:3, resources:2, resources:3, resource_translations:4, resource_translations:5, contacts:11, contacts:12, contacts:14, contacts:15, contacts:13, contacts:16, resource_translations:2, resource_translations:3, category_translations:3, resource_downloads:2, resource_downloads:3, resource_requests:4, resource_requests:5, content_revisions:9 |
| Positive test | Executed isolated mutation/projection assertions: ExternalResourceLinkingTest::test_admin_can_create_resource_with_valid_external_url; ExternalResourceLinkingTest::test_downloading_resource_with_external_url_redirects_away_and_records_download |
| Negative/edge test | Executed isolated edge/security assertions: PublicExperienceTest::test_gated_resource_cannot_be_downloaded_without_token_or_email_submission; CmsTranslationAndRevisionsTest::test_stale_translation_remains_publicly_visible |
| Browser/UI evidence | public-resource-library (2026-10-06T23:12:50.739Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/ResourceController.php](app/Http/Controllers/ResourceController.php) |

### PUB-015 — Immediate gated Resource request and single-use access grant

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Resource acquisition |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Resource acquisition. Immediate gated Resource request and single-use access grant. Valid name/email and analytics visitor cookies required; untrusted secondary email does not redefine a Student identity. |
| Business rules | Valid name/email and analytics visitor cookies required; untrusted secondary email does not redefine a Student identity. Current gate needs no email PIN; verify-pin remains inactive/404 and has no capability ID. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [PUB-014](#pub-014), [PUB-016](#pub-016), [STU-003](#stu-003) |
| QA scenarios | Q07 |
| Actual fixture/reference | resource_categories:3, resources:2, resources:3, contacts:11, contacts:12, contacts:14, contacts:15, contacts:13, contacts:16, resource_translations:2, resource_translations:3, category_translations:3, resource_downloads:2, resource_downloads:3, resource_requests:4, resource_requests:5, content_revisions:9 |
| Positive test | Executed isolated mutation/projection assertions: StudentSecondaryEmailTest::test_five_resource_requests_reuse_one_student_and_contact_without_trusting_an_unverified_email; ExternalResourceLinkingTest::test_admin_can_create_resource_with_valid_external_url |
| Negative/edge test | Executed isolated edge/security assertions: ExternalResourceLinkingTest::test_external_url_scheme_validation_rejects_unapproved_schemes; StudentSecondaryEmailTest::test_expired_and_conflicting_secondary_emails_are_rejected |
| Browser/UI evidence | resource-real-browser-grant (2026-10-06T23:15:19.472Z); resource-repaired-ready (2026-10-07T02:53:02.702Z); resource-repaired-ready-settled (2026-10-07T02:53:09.067Z); resource-browser-file-saved (2026-10-07T02:53:58.437Z); resource-grant-replay-denied (2026-10-07T02:53:59.116Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | PRELMS-007 |
| Fix commit SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Deployed fix SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Retest | Actual active inline method red/green; real browser733-byte PDF hash matches fixture; explicit click records one download, replay returns gate. |
| Authoritative current source | [app/Http/Controllers/ResourceController.php](app/Http/Controllers/ResourceController.php) |

### PUB-016 — Authorized private Resource file download or configured external redirect

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Resource delivery |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Resource delivery. Authorized private Resource file download or configured external redirect. Gated bearer and session copies are single-use and bound to matching session/visitor. |
| Business rules | Gated bearer and session copies are single-use and bound to matching session/visitor. Missing file denies; external URL records delivery before redirect. Public Resource download is distinct from private teaching-assignment access and its no-store policy. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [PUB-015](#pub-015), [SA-061](#sa-061) |
| QA scenarios | Q07 |
| Actual fixture/reference | resource_categories:3, resources:2, resources:3, contacts:11, contacts:12, contacts:14, contacts:15, contacts:13, contacts:16, resource_translations:2, resource_translations:3, category_translations:3, resource_downloads:2, resource_downloads:3, resource_requests:4, resource_requests:5, content_revisions:9 |
| Positive test | Executed isolated mutation/projection assertions: ExternalResourceLinkingTest::test_downloading_resource_with_external_url_redirects_away_and_records_download; ExternalResourceLinkingTest::test_admin_can_create_resource_with_valid_external_url |
| Negative/edge test | Executed isolated edge/security assertions: ExternalResourceLinkingTest::test_external_url_scheme_validation_rejects_unapproved_schemes |
| Browser/UI evidence | resource-real-browser-grant (2026-10-06T23:15:19.472Z); resource-repaired-ready (2026-10-07T02:53:02.702Z); resource-repaired-ready-settled (2026-10-07T02:53:09.067Z); resource-browser-file-saved (2026-10-07T02:53:58.437Z); resource-grant-replay-denied (2026-10-07T02:53:59.116Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | PRELMS-007 |
| Fix commit SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Deployed fix SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Retest | Actual active inline method red/green; real browser733-byte PDF hash matches fixture; explicit click records one download, replay returns gate. |
| Authoritative current source | [app/Http/Controllers/ResourceController.php](app/Http/Controllers/ResourceController.php) |

### PUB-017 — Published articles, sanitized reading and old-slug redirects

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Blog |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Blog. Published articles, sanitized reading and old-slug redirects. Public detail only resolves published Blogs or a published current target via 301 old-slug redirect. |
| Business rules | Public detail only resolves published Blogs or a published current target via 301 old-slug redirect. Drafts/previews require staff access. Legacy articles tables do not imply a second public article system. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [SA-067](#sa-067), [PUB-024](#pub-024) |
| QA scenarios | Q08 |
| Actual fixture/reference | media:2, pages:4, faqs:6, social_links:7, blogs:2, games:4, games:5, promotions:2, promotions:3, promotions:4, page_translations:5, page_translations:6, resource_translations:4, resource_translations:5, page_translations:4, blog_slug_redirects:2, blog_revisions:2, faq_translations:6, game_translations:4, game_translations:5, content_revisions:5, content_revisions:6, content_revisions:7, content_revisions:8 |
| Positive test | Executed isolated mutation/projection assertions: BlogPublishingTest::test_published_updates_snapshot_complete_content_and_old_slug_returns_301; BlogPublishingTest::test_blog_html_is_purified_and_external_or_data_images_are_removed |
| Negative/edge test | Executed isolated edge/security assertions: BlogPublishingTest::test_stale_blog_editor_version_is_rejected_with_conflict; PublicExperienceTest::test_gated_resource_cannot_be_downloaded_without_token_or_email_submission |
| Browser/UI evidence | public-qa-blog-reading (2026-10-07T03:44:36.961Z); public-qa-blog-settled (2026-10-07T03:44:51.149Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/BlogController.php](app/Http/Controllers/BlogController.php)<br>[app/Domains/CMS/Services/BlogService.php](app/Domains/CMS/Services/BlogService.php) |

### PUB-018 — Available/coming-soon learning game catalog and playable/linked content

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Games |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Games. Available/coming-soon learning game catalog and playable/linked content. Only available games accept gameplay tracking. |
| Business rules | Only available games accept gameplay tracking. Configured target URLs redirect externally; embedded/current game presentation is not a course/LMS progression engine. Server bounds allowlisted action/score/duration metadata. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [PUB-003](#pub-003), [PUB-024](#pub-024), [SA-068](#sa-068) |
| QA scenarios | Q08 |
| Actual fixture/reference | media:2, pages:4, faqs:6, social_links:7, blogs:2, games:4, games:5, promotions:2, promotions:3, promotions:4, page_translations:5, page_translations:6, resource_translations:4, resource_translations:5, page_translations:4, blog_slug_redirects:2, blog_revisions:2, faq_translations:6, game_translations:4, game_translations:5, content_revisions:5, content_revisions:6, content_revisions:7, content_revisions:8 |
| Positive test | Executed isolated mutation/projection assertions: PublicExperienceTest::test_games_catalog_and_interactive_event_tracking; EngagementCountersTest::test_collective_learning_activity_aggregates_completed_lessons_and_study_dwell |
| Negative/edge test | Executed isolated edge/security assertions: ClientTelemetryIngestionTest::test_disallowed_metadata_keys_are_rejected_with_422; EngagementCountersTest::test_counter_draft_does_not_publish_and_publish_invalidates_public_cache |
| Browser/UI evidence | public-game-catalog (2026-10-06T23:09:42.202Z); game-status-repaired (2026-10-07T02:55:02.727Z); public-game-status-final (2026-10-07T03:43:52.392Z); external-linked-game-started (2026-10-07T03:54:13.820Z); native-game-catalog-click (2026-10-07T04:08:33.579Z); qa-game-embedded-test-publication (2026-10-07T04:10:18.198Z); real-embedded-game-start (2026-10-07T04:10:26.605Z); real-embedded-game-complete (2026-10-07T04:11:08.791Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Actual built-in quiz5/5/start/complete receipts are verified. After PRELMS-009, live public markup/server API proves one server opening, retained external handlers and invalid/nested/Coming Soon denials; browser control stalled, so a fresh postfix GUI click is not claimed. |
| Defect IDs | PRELMS-006, PRELMS-009 |
| Fix commit SHA | 4296b21368cbc9c564d0cb7a85e29b2ae408e0ee; addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Deployed fix SHA | 4296b21368cbc9c564d0cb7a85e29b2ae408e0ee; addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Retest | Coming Soon/Disabled/available regressions pass; actual QA Game5 publish preserves Coming Soon and catalog has no play link.; Meaningful rendered-anchor red case; final internal/external regressions2/20 pass. Current production internal markup has no client handler, external retains both events, destination returns200, invalid/nested/Coming Soon422/422/404. Unique server-API campaign has exactly one game_opened; QA target restored. Browser postfix click constrained by tool outage. |
| Authoritative current source | [app/Http/Controllers/GameController.php](app/Http/Controllers/GameController.php)<br>[resources/views/public/games/show.blade.php](resources/views/public/games/show.blade.php) |

### PUB-019 — Configured native social-channel links including Reddit

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Communication |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Communication. Configured native social-channel links including Reddit. Reddit is a supported platform alongside existing channels; disabled/unconfigured rows do not render. |
| Business rules | Reddit is a supported platform alongside existing channels; disabled/unconfigured rows do not render. Native keyboard-accessible links retain real hrefs. Current production has no Reddit row; generic existing channel roots are rendering evidence, not account ownership certification. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [SA-065](#sa-065), [PUB-024](#pub-024) |
| QA scenarios | Existing surface/current configuration |
| Actual fixture/reference | Current configuration/private runtime checkpoints; seven setup gaps resolved/classified above |
| Positive test | Executed isolated mutation/projection assertions: AdminDrawerAndSocialAccessibilityTest::test_public_footer_social_links_have_icon_and_visible_label; StageOnePresentationTest::test_reddit_configuration_footer_and_filter_aware_social_exports_reuse_existing_paths |
| Negative/edge test | Executed isolated edge/security assertions: StageOnePresentationTest::test_reddit_configuration_rejects_unsafe_urls_and_assistant_writes; StageOnePresentationTest::test_assistant_shell_omits_destinations_denied_by_existing_role_middleware |
| Browser/UI evidence | public-mobile-navigation (2026-10-07T03:46:56.874Z); public-native-social-click (2026-10-07T04:00:49.237Z); social-browser-popup-limitation (2026-10-07T04:01:00.031Z); public-native-social-and-whatsapp-settled (2026-10-07T04:01:21.727Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Configured native social links opened. The QA Reddit channel remains intentionally disabled; enabling it was not required or performed. Enabled Reddit rendering/tracking is isolated-test coverage. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/CMS/Models/SocialLink.php](app/Domains/CMS/Models/SocialLink.php)<br>[resources/views/layouts/public.blade.php](resources/views/layouts/public.blade.php)<br>[app/Http/Controllers/HomeController.php](app/Http/Controllers/HomeController.php) |

### PUB-020 — Floating and footer WhatsApp entry

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Communication |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Communication. Floating and footer WhatsApp entry. Only configured enabled placement renders; international number and encoded message form a native WhatsApp link. |
| Business rules | Only configured enabled placement renders; international number and encoded message form a native WhatsApp link. Floating and footer destinations are separate current configuration, not automatically synchronized. No chat message is sent by this inventory. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [PUB-019](#pub-019), [PUB-024](#pub-024), [SA-077](#sa-077) |
| QA scenarios | Existing surface/current configuration |
| Actual fixture/reference | Current configuration/private runtime checkpoints; seven setup gaps resolved/classified above |
| Positive test | Executed isolated mutation/projection assertions: StageOnePresentationTest::test_reddit_configuration_footer_and_filter_aware_social_exports_reuse_existing_paths; PublicExperienceTest::test_homepage_loads_successfully_with_hero_and_content |
| Negative/edge test | Executed isolated edge/security assertions: PublicExperienceTest::test_gated_resource_cannot_be_downloaded_without_token_or_email_submission; StageOnePresentationTest::test_assistant_shell_omits_destinations_denied_by_existing_role_middleware |
| Browser/UI evidence | public-mobile-navigation (2026-10-07T03:46:56.874Z); public-native-whatsapp-click (2026-10-07T04:00:49.828Z); public-native-social-and-whatsapp-settled (2026-10-07T04:01:21.727Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [resources/views/layouts/public.blade.php](resources/views/layouts/public.blade.php)<br>[resources/views/layouts/student.blade.php](resources/views/layouts/student.blade.php)<br>[app/Http/Controllers/HomeController.php](app/Http/Controllers/HomeController.php) |

### PUB-021 — Emergency announcement rendering and dismissal

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Communication |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Communication. Emergency announcement rendering and dismissal. EN/FR/DE message fallback, safe HTTPS CTA, information/warning/urgent styles and optional dismissal. |
| Business rules | EN/FR/DE message fallback, safe HTTPS CTA, information/warning/urgent styles and optional dismissal. Audience/window/message checks determine visibility; a changed version can reappear. Currently disabled and correctly absent. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [SA-078](#sa-078), [PUB-003](#pub-003) |
| QA scenarios | Existing surface/current configuration |
| Actual fixture/reference | Current configuration/private runtime checkpoints; seven setup gaps resolved/classified above |
| Positive test | Executed isolated mutation/projection assertions: AnnouncementBannerTest::test_end_only_and_disabled_fixed_announcements_are_supported; AnnouncementBannerTest::test_configuration_roles_publish_an_escaped_localized_banner_without_restricting_site_access with data set #0 |
| Negative/edge test | Executed isolated edge/security assertions: AnnouncementBannerTest::test_assistants_and_guests_cannot_publish_announcements; AnnouncementBannerTest::test_invalid_configuration_is_rejected_atomically with data set #0 |
| Browser/UI evidence | No separate browser mutation certified. Current source/controller behavior is exercised by the named isolated cases; production fixture facts are inspected where available. |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Announcement is disabled in actual production. Audience/window/DST/dismissal behavior is executed in isolated tests, without publishing a new live emergency notice. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/CMS/Services/AnnouncementService.php](app/Domains/CMS/Services/AnnouncementService.php)<br>[resources/js/announcement-banner.js](resources/js/announcement-banner.js)<br>[app/Http/Controllers/HomeController.php](app/Http/Controllers/HomeController.php) |

### PUB-022 — Application Maintenance Mode public response

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Maintenance |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Maintenance. Application Maintenance Mode public response. Returns application 503 for guests during maintenance, while staff/admin paths and health remain accessible. |
| Business rules | Returns application 503 for guests during maintenance, while staff/admin paths and health remain accessible. Separate Laravel deployment down mode is not a customer-controlled public feature. Currently off; no toggle used here. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [SA-079](#sa-079), [SA-076](#sa-076) |
| QA scenarios | Existing surface/current configuration |
| Actual fixture/reference | Current configuration/private runtime checkpoints; seven setup gaps resolved/classified above |
| Positive test | Executed isolated mutation/projection assertions: BackupAndMaintenanceTest::test_maintenance_mode_blocks_public_visitors_with_503; MaintenanceModeResilienceTest::test_maintenance_mode_intercepts_public_traffic_and_records_visits |
| Negative/edge test | Executed isolated edge/security assertions: MaintenanceModeResilienceTest::test_unauthenticated_admin_auth_endpoints_bypass_maintenance; BackupAndMaintenanceTest::test_cleanup_expired_holds_command_transitions_expired_holds |
| Browser/UI evidence | public-controlled-maintenance (2026-10-07T03:48:20.656Z); public-maintenance-restored (2026-10-07T03:48:36.695Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Middleware/CheckMaintenanceMode.php](app/Http/Middleware/CheckMaintenanceMode.php)<br>[resources/views/errors/503.blade.php](resources/views/errors/503.blade.php)<br>[app/Http/Controllers/HomeController.php](app/Http/Controllers/HomeController.php) |

### PUB-023 — Live visitors, prior-month traffic and collective practice counters

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Social proof |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Social proof. Live visitors, prior-month traffic and collective practice counters. Live distinct users use last 60 seconds. |
| Business rules | Live distinct users use last 60 seconds. Practice total combines completed lesson minutes and tracked study dwell, with human days/hours/minutes; no fake popularity values. Counter enable/source/window/templates are separate. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [PUB-024](#pub-024), [SA-072](#sa-072), [SA-077](#sa-077) |
| QA scenarios | Q11 |
| Actual fixture/reference | visitor_sessions:189, visitors:163, analytics_events:1174, analytics_events:1175, analytics_events:1176, analytics_events:1177, analytics_events:1178, analytics_events:1179, analytics_events:1180, analytics_events:1181, analytics_events:1182, analytics_events:1183, analytics_events:1184, analytics_events:1185, analytics_events:1186, marketing_touches:13, visitor_funnel_progressions:60 |
| Positive test | Executed isolated mutation/projection assertions: EngagementCountersTest::test_previous_month_traffic_uses_cairo_month_boundaries_converted_to_utc; EngagementCountersTest::test_all_public_counters_render_known_fixture_values_with_the_selected_traffic_source |
| Negative/edge test | Executed isolated edge/security assertions: CairoDailyAnalyticsRollupTest::test_pruned_source_filtered_traffic_report_prevents_unfiltered_rollup_contamination; CairoDailyAnalyticsRollupTest::test_concurrent_or_repeated_aggregation_produces_no_duplicate_rows_with_empty_dimensions |
| Browser/UI evidence | public-real-browser-home (2026-10-06T23:09:29.975Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Analytics/Services/EngagementCounterService.php](app/Domains/Analytics/Services/EngagementCounterService.php)<br>[app/Domains/Analytics/Services/HumanDurationFormatter.php](app/Domains/Analytics/Services/HumanDurationFormatter.php)<br>[app/Http/Controllers/HomeController.php](app/Http/Controllers/HomeController.php) |

### PUB-024 — First-party visitor, attention, attribution and semantic interaction tracking

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Analytics/privacy |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Analytics/privacy. First-party visitor, attention, attribution and semantic interaction tracking. 32 KiB payload/20-event batch/2 KiB metadata limits and per-event allowlists; clients cannot spoof server-only booking/resource/form completion. |
| Business rules | 32 KiB payload/20-event batch/2 KiB metadata limits and per-event allowlists; clients cannot spoof server-only booking/resource/form completion. UUID/click dedupe and presence ping avoid count inflation. Internal authenticated staff, preview/bot/excluded traffic are filtered; raw IP is not a permanent reporting identity. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [SA-072](#sa-072), [SA-073](#sa-073), [SA-074](#sa-074), [SA-075](#sa-075), [SA-077](#sa-077) |
| QA scenarios | Q11 |
| Actual fixture/reference | visitor_sessions:189, visitors:163, analytics_events:1174, analytics_events:1175, analytics_events:1176, analytics_events:1177, analytics_events:1178, analytics_events:1179, analytics_events:1180, analytics_events:1181, analytics_events:1182, analytics_events:1183, analytics_events:1184, analytics_events:1185, analytics_events:1186, marketing_touches:13, visitor_funnel_progressions:60 |
| Positive test | Executed isolated mutation/projection assertions: AnalyticsFunnelAndAttributionTest::test_first_non_direct_acquisition_attribution; AnalyticsFunnelAndAttributionTest::test_funnel_cumulative_progression_counts_each_visitor_once |
| Negative/edge test | Executed isolated edge/security assertions: AnalyticsValidationAndIdentityTest::test_generic_endpoint_rejects_client_claimed_resource_downloaded_event; AnalyticsValidationAndIdentityTest::test_game_track_rejects_disabled_game |
| Browser/UI evidence | public-real-browser-home (2026-10-06T23:09:29.975Z); resource-real-browser-grant (2026-10-06T23:15:19.472Z); public-booking-confirmed (2026-10-07T03:40:56.856Z); public-native-social-click (2026-10-07T04:00:49.237Z); public-native-whatsapp-click (2026-10-07T04:00:49.828Z); social-browser-popup-limitation (2026-10-07T04:01:00.031Z); public-native-social-and-whatsapp-settled (2026-10-07T04:01:21.727Z); native-game-catalog-click (2026-10-07T04:08:33.579Z); real-embedded-game-start (2026-10-07T04:10:26.605Z); real-embedded-game-complete (2026-10-07T04:11:08.791Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Real browser page/attention/Resource/booking/social/WhatsApp/game ingestion is verified with no duplicate client UUID. Disabled Reddit is exercised only in isolated tests; no new social configuration was enabled. |
| Defect IDs | PRELMS-009 |
| Fix commit SHA | 4296b21368cbc9c564d0cb7a85e29b2ae408e0ee |
| Deployed fix SHA | 4296b21368cbc9c564d0cb7a85e29b2ae408e0ee |
| Retest | Meaningful rendered-anchor red case; final internal/external regressions2/20 pass. Current production internal markup has no client handler, external retains both events, destination returns200, invalid/nested/Coming Soon422/422/404. Unique server-API campaign has exactly one game_opened; QA target restored. Browser postfix click constrained by tool outage. |
| Authoritative current source | [app/Http/Controllers/AnalyticsController.php](app/Http/Controllers/AnalyticsController.php)<br>[app/Domains/Analytics/Services/AnalyticsService.php](app/Domains/Analytics/Services/AnalyticsService.php)<br>[resources/js/analytics-telemetry.js](resources/js/analytics-telemetry.js) |

### PUB-025 — Public Student and staff authentication entry pages

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Authentication entry |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Authentication entry. Public Student and staff authentication entry pages. No public standalone self-registration or public staff account creation. |
| Business rules | No public standalone self-registration or public staff account creation. Portal identity verification and staff password/recovery are distinct flows; staff pages are noindex. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [STU-001](#stu-001), [SA-013](#sa-013), [SA-014](#sa-014) |
| QA scenarios | Existing surface/current configuration |
| Actual fixture/reference | blogs:2, blog_slug_redirects:2, blog_revisions:2 |
| Positive test | Executed isolated mutation/projection assertions: StudentAuthenticationTest::test_student_logout_does_not_destroy_admin_authentication; AdministratorTwoFactorTest::test_enrollment_requires_current_password_and_stays_pending_until_confirmation |
| Negative/edge test | Executed isolated edge/security assertions: AdministratorTwoFactorTest::test_revoking_stale_staff_authentication_preserves_an_unrelated_student_session with data set #0; AdministratorTwoFactorTest::test_revoking_stale_staff_authentication_preserves_an_unrelated_student_session with data set #1 |
| Browser/UI evidence | No separate browser mutation certified. Current source/controller behavior is exercised by the named isolated cases; production fixture facts are inspected where available. |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Student/AuthController.php](app/Http/Controllers/Student/AuthController.php)<br>[app/Http/Controllers/Admin/AuthController.php](app/Http/Controllers/Admin/AuthController.php)<br>[app/Http/Controllers/Admin/PasswordResetController.php](app/Http/Controllers/Admin/PasswordResetController.php) |

### PUB-026 — Scheduled promotional bars, modal and inline card

| Field | Evidence / disposition |
|---|---|
| Audience/domain | PUBLIC / Promotions |
| Expected behavior | **Audience:** PUBLIC-FACING. **Domain:** Promotions. Scheduled promotional bars, modal and inline card. Latest eligible promotion per placement uses bounded transition-aware cache, with optional countdown. |
| Business rules | Latest eligible promotion per placement uses bounded transition-aware cache, with optional countdown. Supported display types are top_bar/floating_modal/inline_card. Currently no promotions; this is configurable deployed behavior, not missing functionality. |
| Prerequisites/dependencies | Available public site and enabled/published definitions where applicable. [SA-069](#sa-069), [PUB-003](#pub-003) |
| QA scenarios | Q08 |
| Actual fixture/reference | media:2, pages:4, faqs:6, social_links:7, blogs:2, games:4, games:5, promotions:2, promotions:3, promotions:4, page_translations:5, page_translations:6, resource_translations:4, resource_translations:5, page_translations:4, blog_slug_redirects:2, blog_revisions:2, faq_translations:6, game_translations:4, game_translations:5, content_revisions:5, content_revisions:6, content_revisions:7, content_revisions:8 |
| Positive test | Executed isolated mutation/projection assertions: PublicExperienceTest::test_internal_game_card_leaves_open_tracking_to_the_destination_controller; PublicExperienceTest::test_external_game_card_renders_with_preview_image_badge_and_clickable_link |
| Negative/edge test | Executed isolated edge/security assertions: PromotionsTest::test_expired_or_future_promotions_are_not_active; PromotionsTest::test_promotion_rejects_executable_links_and_stores_uploaded_image_with_a_safe_extension |
| Browser/UI evidence | No separate browser mutation certified. Current source/controller behavior is exercised by the named isolated cases; production fixture facts are inspected where available. |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Prepared promotion bars/modal/cards remain disabled with original Oct16–17 dates. Publication/window/countdown branches are isolated-test coverage; production clock/schedule were not changed. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Marketing/Services/PromotionService.php](app/Domains/Marketing/Services/PromotionService.php)<br>[resources/views/layouts/public.blade.php](resources/views/layouts/public.blade.php)<br>[app/Http/Controllers/HomeController.php](app/Http/Controllers/HomeController.php) |

### STU-001 — Passwordless identity verification, session expiry and sign-out

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Authentication |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Authentication. Passwordless identity verification, session expiry and sign-out. DOB plus at least two matching name/email/phone signals must identify exactly one verified unsuspended Student. |
| Business rules | DOB plus at least two matching name/email/phone signals must identify exactly one verified unsuspended Student. National phone requires normalization context; ambiguity fails generically. Every authenticated request rechecks guard/identity/expiry; sign-out removes Student keys while staff guard may coexist. |
| Prerequisites/dependencies | An existing verified unsuspended Student with recorded DOB and at least two matching identity attributes; no public signup. [PUB-025](#pub-025), [SA-020](#sa-020), [SA-018](#sa-018) |
| QA scenarios | Q01 |
| Actual fixture/reference | students:7, students:8, students:9, students:10, students:11, students:12, student_bins:3, audit_logs:196 |
| Positive test | Executed isolated mutation/projection assertions: SessionInactivityAndCookieRefreshTest::test_active_visitor_session_within_timeout_is_continued_and_slides_cookie; SessionInactivityAndCookieRefreshTest::test_custom_session_timeout_setting_is_respected; production HTTP assertions 6. |
| Negative/edge test | Executed isolated edge/security assertions: SessionInactivityAndCookieRefreshTest::test_inactive_session_past_timeout_is_expired_and_starts_new_session; StudentAuthenticationTest::test_unverified_or_ambiguous_students_cannot_authenticate |
| Browser/UI evidence | UI-STUDENT-A-FIRST-VISIT (2026-10-06T22:03:45.986Z); student-tutor-feedback-return (2026-10-06T23:03:28.386Z); student-normal-signout (2026-10-06T23:04:34.814Z); student-c-compatibility-dashboard (2026-10-07T03:37:31.202Z); student-d-new-york (2026-10-07T03:38:09.402Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Student/AuthController.php](app/Http/Controllers/Student/AuthController.php)<br>[app/Http/Middleware/EnsureStudentAuthenticated.php](app/Http/Middleware/EnsureStudentAuthenticated.php) |

### STU-002 — Read recorded profile and verified emails

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Identity |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Identity. Read recorded profile and verified emails. Profile exposes recorded identity and verified addresses; no general Student self-edit route for name/DOB/phone is available. |
| Business rules | Profile exposes recorded identity and verified addresses; no general Student self-edit route for name/DOB/phone is available. Changes to primary identity remain staff-managed. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [STU-001](#stu-001), [SA-020](#sa-020) |
| QA scenarios | Q01 |
| Actual fixture/reference | students:7, students:8, students:9, students:10, students:11, students:12, student_bins:3, audit_logs:196 |
| Positive test | Executed isolated mutation/projection assertions: StudentAuthenticationTest::test_verified_student_can_sign_in_with_dob_and_two_normalized_identifiers; StudentSecondaryEmailTest::test_five_resource_requests_reuse_one_student_and_contact_without_trusting_an_unverified_email; production HTTP assertions 3. |
| Negative/edge test | Executed isolated edge/security assertions: StudentAuthenticationTest::test_unverified_or_ambiguous_students_cannot_authenticate; StudentSecondaryEmailTest::test_expired_and_conflicting_secondary_emails_are_rejected |
| Browser/UI evidence | UI-STUDENT-A-PROFILE (2026-10-06T22:36:09.491Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Student/ProfileController.php](app/Http/Controllers/Student/ProfileController.php)<br>[resources/views/student/profile.blade.php](resources/views/student/profile.blade.php) |

### STU-003 — Add a verified secondary authentication email

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Identity/email |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Identity/email. Add a verified secondary authentication email. Six requests/hour and route throttles; 15-minute hashed token belongs to the authenticated Student and is single-use. |
| Business rules | Six requests/hour and route throttles; 15-minute hashed token belongs to the authenticated Student and is single-use. Primary/secondary/soft-deleted ownership conflicts reject; requesting an address does not make it trusted. Current production mailer is Log, so real external inbox delivery is not certified. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [STU-002](#stu-002), [STU-001](#stu-001), [PUB-015](#pub-015) |
| QA scenarios | Q01 |
| Actual fixture/reference | students:7, students:8, students:9, students:10, students:11, students:12, student_bins:3, audit_logs:196 |
| Positive test | Executed isolated mutation/projection assertions: StudentSecondaryEmailTest::test_five_resource_requests_reuse_one_student_and_contact_without_trusting_an_unverified_email; StudentSecondaryEmailTest::test_verification_is_owned_single_use_and_enables_existing_two_identifier_login |
| Negative/edge test | Executed isolated edge/security assertions: StudentSecondaryEmailTest::test_expired_and_conflicting_secondary_emails_are_rejected |
| Browser/UI evidence | UI-STUDENT-A-PROFILE (2026-10-06T22:36:09.491Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Normal profile/secondary-email boundary is inspected and feature tests execute request, signed verification, expiry, reuse and identity conflicts. Example-domain QA addresses and production log mail preclude actual inbox delivery. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/StudentEmailService.php](app/Domains/Students/Services/StudentEmailService.php)<br>[app/Http/Controllers/Student/ProfileController.php](app/Http/Controllers/Student/ProfileController.php) |

### STU-004 — Prioritized next action and empty-state dashboard

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Dashboard |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Dashboard. Prioritized next action and empty-state dashboard. Priority is current mandatory-first outstanding questionnaire → open homework → unreviewed Resource → compatible booking if no upcoming lesson → learning progress. |
| Business rules | Priority is current mandatory-first outstanding questionnaire → open homework → unreviewed Resource → compatible booking if no upcoming lesson → learning progress. Typed allocations are not pooled to manufacture booking eligibility. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [STU-005](#stu-005), [STU-009](#stu-009), [STU-014](#stu-014), [STU-015](#stu-015), [STU-020](#stu-020), [STU-022](#stu-022) |
| QA scenarios | Q01 |
| Actual fixture/reference | students:7, students:8, students:9, students:10, students:11, students:12, student_bins:3, audit_logs:196 |
| Positive test | Executed isolated mutation/projection assertions: StudentTeachingExperienceTest::test_resource_review_drives_next_action_and_reset_on_reassignment; StudentTeachingExperienceTest::test_next_action_prioritizes_questionnaire_then_homework; production HTTP assertions 8. |
| Negative/edge test | Executed isolated edge/security assertions: StudentTeachingExperienceTest::test_invalid_homework_never_writes with data set "empty title"; StudentTeachingExperienceTest::test_assistant_cannot_read_or_write_teaching_but_retains_existing_educational_notes_permission |
| Browser/UI evidence | student-c-compatibility-dashboard (2026-10-07T03:37:31.202Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/StudentTeachingReadModel.php](app/Domains/Students/Services/StudentTeachingReadModel.php)<br>[app/Http/Controllers/Student/DashboardController.php](app/Http/Controllers/Student/DashboardController.php) |

### STU-005 — Owned packages and separate typed credit balances

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Entitlements |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Entitlements. Owned packages and separate typed credit balances. one_hour and two_hour rights are separate; SessionType exact type/units, compatible single allocation and business-date eligibility govern spend. |
| Business rules | one_hour and two_hour rights are separate; SessionType exact type/units, compatible single allocation and business-date eligibility govern spend. Expired/pending-settlement/inactive/legacy_unclassified units are not available; historical units remain visible as review-required. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [STU-001](#stu-001), [SA-027](#sa-027), [SA-031](#sa-031), [STU-009](#stu-009) |
| QA scenarios | Q03, Q15 |
| Actual fixture/reference | payment_methods:7, student_packages:11, student_package_entitlements:5, session_ledger_entries:19, student_packages:12, student_package_entitlements:6, session_ledger_entries:20, student_packages:13, student_package_entitlements:7, session_ledger_entries:21, student_packages:14, student_package_entitlements:8, session_ledger_entries:22, student_packages:15, student_package_entitlements:9, session_ledger_entries:23, student_packages:16, student_package_entitlements:10, session_ledger_entries:24, student_packages:17, student_package_entitlements:11, session_ledger_entries:25, payment_records:13, payment_records:14, payment_records:15, payment_records:16, payment_refunds:4, payment_records:17, payment_refunds:5, package_installments:1, package_installments:2, package_renewals:1, student_packages:18, student_package_entitlements:12, session_ledger_entries:26, student_packages:19, student_package_entitlements:13, session_ledger_entries:33, payment_records:18, session_ledger_entries:36, payment_refunds:6, session_ledger_entries:37, student_packages:20, student_package_entitlements:14, session_ledger_entries:38 |
| Positive test | Executed isolated mutation/projection assertions: TypedEntitlementsTest::test_portal_preserves_separate_balances_and_each_purchase_expiry; StudentCreditBookingTest::test_confirmed_student_booking_consumes_one_fifo_credit_and_replays_idempotently; production HTTP assertions 2. |
| Negative/edge test | Executed isolated edge/security assertions: StudentCreditBookingTest::test_tampered_encrypted_slot_identity_is_rejected_before_booking_or_credit_mutation; StudentCreditBookingTest::test_booking_key_cannot_be_reused_for_another_session_type_or_slot |
| Browser/UI evidence | UI-STUDENT-A-FIRST-VISIT (2026-10-06T22:03:45.986Z); student-b-typed-balances (2026-10-07T03:26:42.603Z); student-b-booking-created (2026-10-07T03:28:16.079Z); student-reschedule-confirmed (2026-10-07T03:30:05.376Z); student-c-compatibility-dashboard (2026-10-07T03:37:31.202Z); student-c-unpoolable-types-excluded (2026-10-07T03:37:37.831Z); student-c-nonpooling-blocked (2026-10-07T03:37:57.050Z); student-c-expired-2h-blocked (2026-10-07T03:38:07.084Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/EntitlementService.php](app/Domains/Students/Services/EntitlementService.php)<br>[app/Domains/Students/Services/StudentPortalService.php](app/Domains/Students/Services/StudentPortalService.php)<br>[app/Http/Controllers/Student/DashboardController.php](app/Http/Controllers/Student/DashboardController.php) |

### STU-006 — Allocation-specific low-credit and expiry notices

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Entitlements/notices |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Entitlements/notices. Allocation-specific low-credit and expiry notices. Low means eligible allocation available units ≤2. |
| Business rules | Low means eligible allocation available units ≤2. Active purchases with remaining units and expiry within 14 business-calendar days include already expired dates. Copy includes type, package, purchase ID, available quantity and expiry; legacy units are not bookable. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [STU-005](#stu-005), [STU-022](#stu-022) |
| QA scenarios | Q03 |
| Actual fixture/reference | payment_methods:7, student_packages:11, student_package_entitlements:5, session_ledger_entries:19, student_packages:12, student_package_entitlements:6, session_ledger_entries:20, student_packages:13, student_package_entitlements:7, session_ledger_entries:21, student_packages:14, student_package_entitlements:8, session_ledger_entries:22, student_packages:15, student_package_entitlements:9, session_ledger_entries:23, student_packages:16, student_package_entitlements:10, session_ledger_entries:24, student_packages:17, student_package_entitlements:11, session_ledger_entries:25, payment_records:13, payment_records:14, payment_records:15, payment_records:16, payment_refunds:4, payment_records:17, payment_refunds:5, package_installments:1, package_installments:2, package_renewals:1, student_packages:18, student_package_entitlements:12, session_ledger_entries:26, student_packages:19, student_package_entitlements:13, session_ledger_entries:33, payment_records:18, session_ledger_entries:36, payment_refunds:6, session_ledger_entries:37, student_packages:20, student_package_entitlements:14, session_ledger_entries:38 |
| Positive test | Executed isolated mutation/projection assertions: StudentTeachingExperienceTest::test_typed_notices_identify_purchase_type_and_expiry_without_generic_totals; StudentTeachingExperienceTest::test_booking_next_action_requires_one_compatible_allocation_not_pooled_or_legacy_units |
| Negative/edge test | Executed isolated edge/security assertions: StudentTeachingExperienceTest::test_assistant_cannot_read_or_write_teaching_but_retains_existing_educational_notes_permission; StudentTeachingExperienceTest::test_suspended_staff_and_merged_students_cannot_use_teaching_workspace |
| Browser/UI evidence | student-b-typed-balances (2026-10-07T03:26:42.603Z); student-c-compatibility-dashboard (2026-10-07T03:37:31.202Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/StudentTeachingReadModel.php](app/Domains/Students/Services/StudentTeachingReadModel.php)<br>[app/Http/Controllers/Student/DashboardController.php](app/Http/Controllers/Student/DashboardController.php)<br>[app/Http/Controllers/Student/NotificationController.php](app/Http/Controllers/Student/NotificationController.php) |

### STU-007 — Owned upcoming sessions and preserved lesson history

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Lessons |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Lessons. Owned upcoming sessions and preserved lesson history. Confirmed upcoming lessons and completed/cancelled/no-show/history remain distinct; reschedule-needed status does not invent completion. |
| Business rules | Confirmed upcoming lessons and completed/cancelled/no-show/history remain distinct; reschedule-needed status does not invent completion. Lesson workspace visibility has its own end/status boundary. Archive state does not erase booked commitments/history. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [STU-001](#stu-001), [STU-010](#stu-010), [STU-011](#stu-011), [STU-028](#stu-028) |
| QA scenarios | Q04 |
| Actual fixture/reference | booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: LessonWorkspaceTest::test_history_shows_multiple_ordered_visible_items_and_omits_private_recording; LessonWorkspaceTest::test_history_adds_one_material_projection_query_without_payload_loading_or_n_plus_one; production HTTP assertions 1. |
| Negative/edge test | Executed isolated edge/security assertions: LessonWorkspaceTest::test_students_cannot_access_ineligible_lessons with data set "future"; LessonWorkspaceTest::test_students_cannot_access_ineligible_lessons with data set "current" |
| Browser/UI evidence | UI-STUDENT-A-FOREIGN-LESSON (2026-10-06T22:36:19.056Z); student-b-typed-balances (2026-10-07T03:26:42.603Z); student-b-booking-created (2026-10-07T03:28:16.079Z); student-reschedule-confirmed (2026-10-07T03:30:05.376Z); student-d-new-york (2026-10-07T03:38:09.402Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/StudentPortalService.php](app/Domains/Students/Services/StudentPortalService.php)<br>[resources/views/student/dashboard.blade.php](resources/views/student/dashboard.blade.php)<br>[app/Http/Controllers/Student/DashboardController.php](app/Http/Controllers/Student/DashboardController.php) |

### STU-008 — Persistent Student timezone preference

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Timezones |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Timezones. Persistent Student timezone preference. Uses shared searchable flag/offset UI. |
| Business rules | Uses shared searchable flag/offset UI. Valid query/input zone can persist preference; invalid input falls back/rejects at its boundary. Locale choice does not change booking timestamps. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [PUB-007](#pub-007), [STU-007](#stu-007) |
| QA scenarios | Q01 |
| Actual fixture/reference | students:7, students:8, students:9, students:10, students:11, students:12, student_bins:3, audit_logs:196 |
| Positive test | Executed isolated mutation/projection assertions: ReschedulePresentationTest::test_public_and_student_use_one_timezone_picker_calendar_and_slot_presentation with data set "Cairo"; ReschedulePresentationTest::test_public_and_student_use_one_timezone_picker_calendar_and_slot_presentation with data set "New York"; production HTTP assertions 1. |
| Negative/edge test | Executed isolated edge/security assertions: ReschedulePresentationTest::test_invalid_month_or_timezone_input_falls_back_safely; StudentReschedulingTest::test_student_cannot_reschedule_another_students_booking_or_one_within_24_hours |
| Browser/UI evidence | UI-STUDENT-A-FIRST-VISIT (2026-10-06T22:03:45.986Z); student-d-new-york (2026-10-07T03:38:09.402Z); student-d-explicit-timezone-persisted (2026-10-07T03:38:53.504Z); confirmation-timezone-repaired (2026-10-07T03:46:37.112Z); student-browser-timezone-restored (2026-10-07T04:00:38.478Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | PRELMS-008 |
| Fix commit SHA | 70b60d3edd02a23cf46a7457a50109e8a5aabf1d |
| Deployed fix SHA | 70b60d3edd02a23cf46a7457a50109e8a5aabf1d |
| Retest | Actual9:15AM New York now labels America/New_York UTC-04:00. Summer/winter regression8/103 and unchanged persisted snapshot pass. |
| Authoritative current source | [app/Http/Middleware/EnsureStudentAuthenticated.php](app/Http/Middleware/EnsureStudentAuthenticated.php)<br>[resources/views/components/timezone-selector.blade.php](resources/views/components/timezone-selector.blade.php)<br>[app/Http/Controllers/Student/DashboardController.php](app/Http/Controllers/Student/DashboardController.php)<br>[app/Http/Controllers/Student/BookingController.php](app/Http/Controllers/Student/BookingController.php)<br>[app/Http/Controllers/Student/RescheduleController.php](app/Http/Controllers/Student/RescheduleController.php) |

### STU-009 — Book an eligible package-funded lesson

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Booking |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Booking. Book an eligible package-funded lesson. One allocation must cover exact required units; same-type balances across purchases cannot be pooled for one booking. |
| Business rules | One allocation must cover exact required units; same-type balances across purchases cannot be pooled for one booking. No one_hour/two_hour conversion, negative balance or double debit; transaction rechecks notice/holiday/conflict/student/type/expiry and idempotency. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [STU-005](#stu-005), [STU-008](#stu-008), [SA-044](#sa-044), [SA-045](#sa-045), [STU-026](#stu-026) |
| QA scenarios | Q04, Q15 |
| Actual fixture/reference | student_packages:11, student_package_entitlements:5, student_packages:12, student_package_entitlements:6, student_packages:13, student_package_entitlements:7, student_packages:14, student_package_entitlements:8, student_packages:15, student_package_entitlements:9, student_packages:16, student_package_entitlements:10, student_packages:17, student_package_entitlements:11, booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, session_types:6, student_packages:20, student_package_entitlements:14, session_ledger_entries:38, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: StudentCreditBookingTest::test_confirmed_student_booking_consumes_one_fifo_credit_and_replays_idempotently; StudentCreditBookingTest::test_student_booking_acquires_lock_tiers_in_canonical_order; production HTTP assertions 2. |
| Negative/edge test | Executed isolated edge/security assertions: StudentCreditBookingTest::test_tampered_encrypted_slot_identity_is_rejected_before_booking_or_credit_mutation; StudentCreditBookingTest::test_booking_key_cannot_be_reused_for_another_session_type_or_slot |
| Browser/UI evidence | student-b-authoritative-slots (2026-10-07T03:27:47.111Z); student-b-booking-created (2026-10-07T03:28:16.079Z); booking-23-cancelled (2026-10-07T03:36:17.509Z); student-c-unpoolable-types-excluded (2026-10-07T03:37:37.831Z); student-c-nonpooling-blocked (2026-10-07T03:37:57.050Z); student-c-expired-2h-blocked (2026-10-07T03:38:07.084Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Student/BookingController.php](app/Http/Controllers/Student/BookingController.php)<br>[app/Domains/Students/Services/StudentBookingService.php](app/Domains/Students/Services/StudentBookingService.php) |

### STU-010 — Self-service owned Student rescheduling and review

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Rescheduling |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Rescheduling. Self-service owned Student rescheduling and review. Portal GET permits confirmed lessons at least 24 hours ahead; nearer/other-status lessons direct to tutor. |
| Business rules | Portal GET permits confirmed lessons at least 24 hours ahead; nearer/other-status lessons direct to tutor. Signed visitor-bound slots, holiday/availability/notice/conflict locks and idempotency recheck writes. Original allocation/type/quantity remain unchanged. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [STU-007](#stu-007), [STU-008](#stu-008), [PUB-008](#pub-008), [STU-026](#stu-026), [SA-041](#sa-041) |
| QA scenarios | Q04 |
| Actual fixture/reference | booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: TypedEntitlementsTest::test_reviewed_mapping_is_replayable_and_preserves_facts_and_signed_balance; TypedEntitlementsTest::test_reviewed_consumption_and_restoration_preserve_original_event_facts; production HTTP assertions 1. |
| Negative/edge test | Executed isolated edge/security assertions: ReschedulePresentationTest::test_invalid_month_or_timezone_input_falls_back_safely; StudentReschedulingTest::test_student_cannot_reschedule_another_students_booking_or_one_within_24_hours |
| Browser/UI evidence | student-reschedule-reviewed (2026-10-07T03:29:54.264Z); student-reschedule-confirmed (2026-10-07T03:30:05.376Z); booking-23-cancelled (2026-10-07T03:36:17.509Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Student/RescheduleController.php](app/Http/Controllers/Student/RescheduleController.php)<br>[app/Domains/Booking/Services/RescheduleService.php](app/Domains/Booking/Services/RescheduleService.php) |

### STU-011 — Owned completed or ended-confirmed lesson workspace

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Lesson workspace |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Lesson workspace. Owned completed or ended-confirmed lesson workspace. A completed lesson or confirmed lesson whose end is ≤now is eligible. |
| Business rules | A completed lesson or confirmed lesson whose end is ≤now is eligible. Upcoming lesson workspace, foreign booking and deleted/withdrawn content deny. Internal tutor preparation is structurally excluded. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [STU-007](#stu-007), [STU-012](#stu-012), [SA-052](#sa-052) |
| QA scenarios | Q04, Q06 |
| Actual fixture/reference | booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, lesson_materials:2, lesson_materials:3, lesson_materials:4, lesson_materials:5, homeworks:1, learning_plans:1, learning_milestones:1, resource_assignments:1, student_error_logs:1, teaching_tags:1, student_bins:1, homeworks:2, learning_plans:2, learning_milestones:2, resource_assignments:2, student_error_logs:2, teaching_tags:2, student_bins:2, tutor_preparations:1, lesson_feedback:1, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: LessonWorkspaceTest::test_policy_matrix_for_staff_workspaces_and_materials with data set "tutor"; LessonWorkspaceTest::test_policy_matrix_for_staff_workspaces_and_materials with data set "admin"; production HTTP assertions 2. |
| Negative/edge test | Executed isolated edge/security assertions: LessonWorkspaceTest::test_guests_and_suspended_staff_cannot_manage_materials; LessonWorkspaceTest::test_students_cannot_access_ineligible_lessons with data set "future" |
| Browser/UI evidence | UI-STUDENT-A-FOREIGN-LESSON (2026-10-06T22:36:19.056Z); UI-STUDENT-A-OWN-LESSON (2026-10-06T22:36:44.062Z); PRELMS-001-RETEST-CLICK (2026-10-06T22:45:30.487Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Policies/LessonWorkspacePolicy.php](app/Policies/LessonWorkspacePolicy.php)<br>[app/Http/Controllers/Student/LessonWorkspaceController.php](app/Http/Controllers/Student/LessonWorkspaceController.php) |

### STU-012 — Open shared private PDF, Resource, recording or safe link

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Private learning files |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Private learning files. Open shared private PDF, Resource, recording or safe link. Parent lesson and material ownership, nonwithdrawn/student_visible and Resource publication are rechecked. |
| Business rules | Parent lesson and material ownership, nonwithdrawn/student_visible and Resource publication are rechecked. Strict private root/realpath/symlink/path checks protect PDFs; external links must satisfy SafeLessonUrl; no-store/private/no-referrer responses. Private PDF maximum is 10 MiB; complete %PDF-/EOF bytes and PassiveLessonPdf reject scripts, automatic actions, launches and embedded files. Material kinds private_file/resource/external_link/recording have database payload-shape checks; title ≤200, description ≤5000, order 0–10000; sharing defaults off. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [STU-011](#stu-011), [SA-052](#sa-052), [SA-061](#sa-061) |
| QA scenarios | Q04, Q06 |
| Actual fixture/reference | booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, lesson_materials:2, lesson_materials:3, lesson_materials:4, lesson_materials:5, homeworks:1, learning_plans:1, learning_milestones:1, resource_assignments:1, student_error_logs:1, teaching_tags:1, student_bins:1, homeworks:2, learning_plans:2, learning_milestones:2, resource_assignments:2, student_error_logs:2, teaching_tags:2, student_bins:2, tutor_preparations:1, lesson_feedback:1, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: LessonWorkspaceTest::test_visible_recording_is_separate_and_safe_external_link_has_no_referrer; LessonWorkspaceTest::test_link_validation_wiring_and_safe_token_url_audit; production HTTP assertions 2. |
| Negative/edge test | Executed isolated edge/security assertions: LessonWorkspaceTest::test_https_rule_rejects_unsafe_links with data set #0; LessonWorkspaceTest::test_https_rule_rejects_unsafe_links with data set #1 |
| Browser/UI evidence | UI-STUDENT-A-PRIVATE-DOWNLOAD (2026-10-06T22:16:56.722Z); UI-STUDENT-A-OWN-LESSON (2026-10-06T22:36:44.062Z); UI-STUDENT-A-UNSHARED-MATERIAL (2026-10-06T22:42:55.558Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Booking/Services/LessonMaterialService.php](app/Domains/Booking/Services/LessonMaterialService.php)<br>[app/Policies/LessonMaterialPolicy.php](app/Policies/LessonMaterialPolicy.php)<br>[app/Rules/PassiveLessonPdf.php](app/Rules/PassiveLessonPdf.php)<br>[app/Http/Controllers/Student/LessonWorkspaceController.php](app/Http/Controllers/Student/LessonWorkspaceController.php) |

### STU-013 — Shared learning plans and tutor-managed milestones

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Learning plan |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Learning plan. Shared learning plans and tutor-managed milestones. Students read tutor-shared plan/milestone state; they cannot create plans or self-mark milestones complete. |
| Business rules | Students read tutor-shared plan/milestone state; they cannot create plans or self-mark milestones complete. Hidden plans/preparation and foreign milestones are excluded. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [SA-054](#sa-054), [STU-018](#stu-018) |
| QA scenarios | Q06 |
| Actual fixture/reference | lesson_materials:2, lesson_materials:3, lesson_materials:4, lesson_materials:5, homeworks:1, learning_plans:1, learning_milestones:1, resource_assignments:1, student_error_logs:1, teaching_tags:1, student_bins:1, homeworks:2, learning_plans:2, learning_milestones:2, resource_assignments:2, student_error_logs:2, teaching_tags:2, student_bins:2, tutor_preparations:1, lesson_feedback:1 |
| Positive test | Executed isolated mutation/projection assertions: StudentTeachingExperienceTest::test_teaching_permission_matches_lesson_workspace_roles with data set "super admin"; StudentTeachingExperienceTest::test_teaching_permission_matches_lesson_workspace_roles with data set "admin"; production HTTP assertions 1. |
| Negative/edge test | Executed isolated edge/security assertions: StudentTeachingExperienceTest::test_learning_milestone_completion_is_editable_and_foreign_plans_are_rejected; StudentTeachingExperienceTest::test_guest_and_suspended_student_cannot_access_learning_or_submit_feedback |
| Browser/UI evidence | UI-STUDENT-A-TEACHING-VISIBILITY (2026-10-06T22:08:06.597Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/StudentTeachingReadModel.php](app/Domains/Students/Services/StudentTeachingReadModel.php)<br>[resources/views/student/teaching.blade.php](resources/views/student/teaching.blade.php)<br>[app/Http/Controllers/Student/TeachingController.php](app/Http/Controllers/Student/TeachingController.php) |

### STU-014 — Shared homework, progress/response submission and tutor feedback

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Homework |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Homework. Shared homework, progress/response submission and tutor feedback. Only in_progress or submitted student writes; completed homework returns 409 and only tutor determines completion. |
| Business rules | Only in_progress or submitted student writes; completed homework returns 409 and only tutor determines completion. Shared reference ownership/publication and safe-link checks repeat at open. No Student file-upload/submission attachment feature is exposed. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [SA-053](#sa-053), [STU-012](#stu-012), [STU-018](#stu-018) |
| QA scenarios | Q06 |
| Actual fixture/reference | lesson_materials:2, lesson_materials:3, lesson_materials:4, lesson_materials:5, homeworks:1, learning_plans:1, learning_milestones:1, resource_assignments:1, student_error_logs:1, teaching_tags:1, student_bins:1, homeworks:2, learning_plans:2, learning_milestones:2, resource_assignments:2, student_error_logs:2, teaching_tags:2, student_bins:2, tutor_preparations:1, lesson_feedback:1 |
| Positive test | Executed isolated mutation/projection assertions: StudentTeachingExperienceTest::test_staff_save_teaching_records_with_student_and_optional_lesson with data set "homework"; StudentTeachingExperienceTest::test_student_sees_only_shared_owned_teaching_and_authoritative_progress; production HTTP assertions 2. |
| Negative/edge test | Executed isolated edge/security assertions: StudentTeachingExperienceTest::test_homework_cannot_reference_a_material_from_another_lesson; StudentTeachingExperienceTest::test_invalid_homework_never_writes with data set "empty title" |
| Browser/UI evidence | UI-STUDENT-A-TEACHING-VISIBILITY (2026-10-06T22:08:06.597Z); UI-STUDENT-A-HOMEWORK-DRAFT-PERSISTENCE (2026-10-06T22:08:11.563Z); UI-STUDENT-A-HOMEWORK-SUBMIT (2026-10-06T22:08:16.154Z); tutor-homework-completed (2026-10-06T22:51:10.009Z); student-tutor-feedback-return (2026-10-06T23:03:28.386Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Student/HomeworkController.php](app/Http/Controllers/Student/HomeworkController.php)<br>[app/Http/Controllers/Student/TeachingController.php](app/Http/Controllers/Student/TeachingController.php) |

### STU-015 — Open assigned published Resources and persist review state

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Resource assignment |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Resource assignment. Open assigned published Resources and persist review state. References the existing Resource/file; creates no duplicate file or public lead/download metric. |
| Business rules | References the existing Resource/file; creates no duplicate file or public lead/download metric. Changed staff Resource assignment resets review. Hidden/withdrawn/unpublished/foreign assignment denies; private delivery reuses LessonMaterialService. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [SA-056](#sa-056), [SA-061](#sa-061), [STU-012](#stu-012) |
| QA scenarios | Q06 |
| Actual fixture/reference | resources:2, resources:3, lesson_materials:2, lesson_materials:3, lesson_materials:4, lesson_materials:5, homeworks:1, learning_plans:1, learning_milestones:1, resource_assignments:1, student_error_logs:1, teaching_tags:1, student_bins:1, homeworks:2, learning_plans:2, learning_milestones:2, resource_assignments:2, student_error_logs:2, teaching_tags:2, student_bins:2, tutor_preparations:1, lesson_feedback:1, resource_translations:2, resource_translations:3, content_revisions:9 |
| Positive test | Executed isolated mutation/projection assertions: StudentTeachingExperienceTest::test_resource_review_drives_next_action_and_reset_on_reassignment; StudentTeachingExperienceTest::test_notifications_are_owner_scoped_persistent_and_do_not_repeat_after_read; production HTTP assertions 1. |
| Negative/edge test | Executed isolated edge/security assertions: StudentTeachingExperienceTest::test_assistant_cannot_read_or_write_teaching_but_retains_existing_educational_notes_permission; StudentTeachingExperienceTest::test_suspended_staff_and_merged_students_cannot_use_teaching_workspace |
| Browser/UI evidence | UI-STUDENT-A-TEACHING-VISIBILITY (2026-10-06T22:08:06.597Z); UI-STUDENT-A-RESOURCE-REVIEW (2026-10-06T22:10:46.977Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Student/ResourceAssignmentController.php](app/Http/Controllers/Student/ResourceAssignmentController.php)<br>[app/Http/Controllers/Student/TeachingController.php](app/Http/Controllers/Student/TeachingController.php) |

### STU-016 — Shared pronunciation, vocabulary and grammar correction patterns

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Learning patterns |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Learning patterns. Shared pronunciation, vocabulary and grammar correction patterns. Read tutor-visible practising/improved/resolved facts; no automated speech score or model-generated proficiency prediction. |
| Business rules | Read tutor-visible practising/improved/resolved facts; no automated speech score or model-generated proficiency prediction. Hidden/private/foreign correction data is excluded. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [SA-057](#sa-057), [STU-018](#stu-018) |
| QA scenarios | Q06 |
| Actual fixture/reference | lesson_materials:2, lesson_materials:3, lesson_materials:4, lesson_materials:5, homeworks:1, learning_plans:1, learning_milestones:1, resource_assignments:1, student_error_logs:1, teaching_tags:1, student_bins:1, homeworks:2, learning_plans:2, learning_milestones:2, resource_assignments:2, student_error_logs:2, teaching_tags:2, student_bins:2, tutor_preparations:1, lesson_feedback:1 |
| Positive test | Executed isolated mutation/projection assertions: StudentTeachingExperienceTest::test_teaching_permission_matches_lesson_workspace_roles with data set "super admin"; StudentTeachingExperienceTest::test_teaching_permission_matches_lesson_workspace_roles with data set "admin"; production HTTP assertions 1. |
| Negative/edge test | Executed isolated edge/security assertions: StudentTeachingExperienceTest::test_assistant_cannot_read_or_write_teaching_but_retains_existing_educational_notes_permission; StudentTeachingExperienceTest::test_suspended_staff_and_merged_students_cannot_use_teaching_workspace |
| Browser/UI evidence | UI-STUDENT-A-TEACHING-VISIBILITY (2026-10-06T22:08:06.597Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/StudentTeachingReadModel.php](app/Domains/Students/Services/StudentTeachingReadModel.php)<br>[app/Http/Controllers/Student/TeachingController.php](app/Http/Controllers/Student/TeachingController.php) |

### STU-017 — Shared normalized teaching tags

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Teaching context |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Teaching context. Shared normalized teaching tags. Shared normalized label tags are teaching context, not public taxonomy. |
| Business rules | Shared normalized label tags are teaching context, not public taxonomy. Students do not author/update tags or see private lesson/student tags. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [SA-058](#sa-058) |
| QA scenarios | Q06 |
| Actual fixture/reference | lesson_materials:2, lesson_materials:3, lesson_materials:4, lesson_materials:5, homeworks:1, learning_plans:1, learning_milestones:1, resource_assignments:1, student_error_logs:1, teaching_tags:1, student_bins:1, homeworks:2, learning_plans:2, learning_milestones:2, resource_assignments:2, student_error_logs:2, teaching_tags:2, student_bins:2, tutor_preparations:1, lesson_feedback:1 |
| Positive test | Executed isolated mutation/projection assertions: StudentTeachingExperienceTest::test_teaching_permission_matches_lesson_workspace_roles with data set "super admin"; StudentTeachingExperienceTest::test_teaching_permission_matches_lesson_workspace_roles with data set "admin"; production HTTP assertions 1. |
| Negative/edge test | Executed isolated edge/security assertions: StudentTeachingExperienceTest::test_assistant_cannot_read_or_write_teaching_but_retains_existing_educational_notes_permission; StudentTeachingExperienceTest::test_suspended_staff_and_merged_students_cannot_use_teaching_workspace |
| Browser/UI evidence | UI-STUDENT-A-TEACHING-VISIBILITY (2026-10-06T22:08:06.597Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/StudentTeachingReadModel.php](app/Domains/Students/Services/StudentTeachingReadModel.php)<br>[app/Http/Controllers/Student/TeachingController.php](app/Http/Controllers/Student/TeachingController.php) |

### STU-018 — Fact-based lesson and learning progress summary

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Progress |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Progress. Fact-based lesson and learning progress summary. Counts reflect stored records and sharing: completed lessons, completed/total milestones and homework, shared notes, improved/resolved patterns. |
| Business rules | Counts reflect stored records and sharing: completed lessons, completed/total milestones and homework, shared notes, improved/resolved patterns. No inferred performance/retention score or LMS completion curriculum is claimed. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [STU-007](#stu-007), [STU-013](#stu-013), [STU-014](#stu-014), [STU-016](#stu-016), [STU-019](#stu-019) |
| QA scenarios | Q06 |
| Actual fixture/reference | lesson_materials:2, lesson_materials:3, lesson_materials:4, lesson_materials:5, homeworks:1, learning_plans:1, learning_milestones:1, resource_assignments:1, student_error_logs:1, teaching_tags:1, student_bins:1, homeworks:2, learning_plans:2, learning_milestones:2, resource_assignments:2, student_error_logs:2, teaching_tags:2, student_bins:2, tutor_preparations:1, lesson_feedback:1 |
| Positive test | Executed isolated mutation/projection assertions: StudentTeachingExperienceTest::test_teaching_permission_matches_lesson_workspace_roles with data set "super admin"; StudentTeachingExperienceTest::test_teaching_permission_matches_lesson_workspace_roles with data set "admin"; production HTTP assertions 7. |
| Negative/edge test | Executed isolated edge/security assertions: StudentTeachingExperienceTest::test_staff_cannot_attach_a_foreign_lesson_or_edit_a_foreign_record; StudentTeachingExperienceTest::test_homework_cannot_reference_a_material_from_another_lesson |
| Browser/UI evidence | UI-STUDENT-A-FIRST-VISIT (2026-10-06T22:03:45.986Z); UI-STUDENT-A-TEACHING-VISIBILITY (2026-10-06T22:08:06.597Z); tutor-homework-completed (2026-10-06T22:51:10.009Z); student-tutor-feedback-return (2026-10-06T23:03:28.386Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/StudentTeachingReadModel.php](app/Domains/Students/Services/StudentTeachingReadModel.php)<br>[app/Http/Controllers/Student/DashboardController.php](app/Http/Controllers/Student/DashboardController.php)<br>[app/Http/Controllers/Student/TeachingController.php](app/Http/Controllers/Student/TeachingController.php) |

### STU-019 — Read shared notes and create/edit Student-authored notes

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Educational notes |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Educational notes. Read shared notes and create/edit Student-authored notes. Students can read shared staff notes but cannot edit them; their authored visible notes can be edited. |
| Business rules | Students can read shared staff notes but cannot edit them; their authored visible notes can be edited. No Student delete route; only Super Admin deletion authority. Text is escaped, and q search escapes wildcard characters. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [SA-060](#sa-060), [STU-001](#stu-001) |
| QA scenarios | Q06 |
| Actual fixture/reference | lesson_materials:2, lesson_materials:3, lesson_materials:4, lesson_materials:5, homeworks:1, learning_plans:1, learning_milestones:1, resource_assignments:1, student_error_logs:1, teaching_tags:1, student_bins:1, homeworks:2, learning_plans:2, learning_milestones:2, resource_assignments:2, student_error_logs:2, teaching_tags:2, student_bins:2, tutor_preparations:1, lesson_feedback:1 |
| Positive test | Executed isolated mutation/projection assertions: BinAuthorizationTest::test_staff_roles_can_share_and_edit_only_their_own_notes_and_only_super_admin_can_delete; BinAuthorizationTest::test_staff_student_notes_follow_parent_binding_and_moderation_policy; production HTTP assertions 2. |
| Negative/edge test | Executed isolated edge/security assertions: BinAuthorizationTest::test_student_notes_are_isolated_hidden_notes_are_404_and_payload_cannot_change_ownership; StudentTeachingExperienceTest::test_assistant_cannot_read_or_write_teaching_but_retains_existing_educational_notes_permission |
| Browser/UI evidence | UI-STUDENT-A-NOTES-VISIBILITY (2026-10-06T22:17:17.301Z); UI-STUDENT-A-NOTE-CREATED (2026-10-06T22:17:22.394Z); UI-STUDENT-A-NOTE-EDIT (2026-10-06T22:21:14.461Z); UI-STUDENT-A-FOREIGN-NOTE (2026-10-06T22:36:14.605Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/StudentBinController.php](app/Http/Controllers/StudentBinController.php)<br>[app/Policies/StudentBinPolicy.php](app/Policies/StudentBinPolicy.php) |

### STU-020 — Assigned published questionnaires, drafts/submission and version history

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Questionnaires |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Questionnaires. Assigned published questionnaires, drafts/submission and version history. Assignment/current-published/owner checks apply; conditional required/type validation and can_edit_after_submission lock control writes. |
| Business rules | Assignment/current-published/owner checks apply; conditional required/type validation and can_edit_after_submission lock control writes. Historical answers stay on their original version; explicit new_version starts current form rather than rewriting old history. Current assignments are derived, not a fabricated manual-assignment table: a published form without pre_booking/after_booking/after_reschedule/next_session_check triggers is generally assigned; after_booking requires owned nondeleted booking history, after_reschedule an owned reschedule, next_session_check an upcoming confirmed lesson. Own prior submission keeps the form available; pre_booking alone does not generally assign it in the portal. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [SA-070](#sa-070), [SA-071](#sa-071), [STU-004](#stu-004), [STU-021](#stu-021) |
| QA scenarios | Q09 |
| Actual fixture/reference | forms:3, form_versions:3, form_questions:10, form_questions:11, form_questions:12, form_triggers:4, form_triggers:5, form_submissions:2, form_submissions:3, form_versions:4, form_questions:13, form_questions:14, form_questions:15, form_submission_revisions:1, form_submission_revisions:2, form_answers:4, form_answers:5, form_answers:6, form_answers:7, form_answers:8 |
| Positive test | Executed isolated mutation/projection assertions: FormsEngineTest::test_submission_revisions_are_append_only_and_assistant_exports_hide_private_answers_and_formulae; FormsEngineTest::test_autosave_validates_answers_and_preserves_final_submission |
| Negative/edge test | Executed isolated edge/security assertions: FormsEngineTest::test_drafts_freeze_old_versions_and_stale_builder_posts_conflict; FormsEngineTest::test_conditional_logic_rejects_cycles_and_depth_greater_than_three |
| Browser/UI evidence | student-form-autosave-reload (2026-10-06T23:03:58.486Z); student-form-submitted-v2 (2026-10-06T23:04:12.459Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Student/FormController.php](app/Http/Controllers/Student/FormController.php)<br>[app/Domains/Forms/Services/FormSubmissionService.php](app/Domains/Forms/Services/FormSubmissionService.php) |

### STU-021 — Form draft autosave with current draft/version identity

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Questionnaire productivity |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Questionnaire productivity. Form draft autosave with current draft/version identity. Autosave is an on-demand browser request, not background completion or file upload. |
| Business rules | Autosave is an on-demand browser request, not background completion or file upload. Same submission service preserves owner/version/editing rules; up to 300 answer keys and student-form-save throttle apply. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [STU-020](#stu-020) |
| QA scenarios | Q09 |
| Actual fixture/reference | forms:3, form_versions:3, form_questions:10, form_questions:11, form_questions:12, form_triggers:4, form_triggers:5, form_submissions:2, form_submissions:3, form_versions:4, form_questions:13, form_questions:14, form_questions:15, form_submission_revisions:1, form_submission_revisions:2, form_answers:4, form_answers:5, form_answers:6, form_answers:7, form_answers:8 |
| Positive test | Executed isolated mutation/projection assertions: FormsEngineTest::test_unchecked_form_metadata_checkboxes_are_saved_as_false; FormsEngineTest::test_submission_revisions_are_append_only_and_assistant_exports_hide_private_answers_and_formulae |
| Negative/edge test | Executed isolated edge/security assertions: FormsEngineTest::test_drafts_freeze_old_versions_and_stale_builder_posts_conflict; FormsEngineTest::test_autosave_cannot_assign_an_untriggered_form_to_a_student |
| Browser/UI evidence | student-form-autosave-reload (2026-10-06T23:03:58.486Z); student-form-submitted-v2 (2026-10-06T23:04:12.459Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Student/FormController.php](app/Http/Controllers/Student/FormController.php)<br>[resources/views/student/forms/show.blade.php](resources/views/student/forms/show.blade.php) |

### STU-022 — Persistent owner Notification Center and read state

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / In-app communication |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** In-app communication. Persistent owner Notification Center and read state. Synchronizes currently eligible events on authenticated visits; does not reconstruct every unseen intermediate change or require a worker. |
| Business rules | Synchronizes currently eligible events on authenticated visits; does not reconstruct every unseen intermediate change or require a worker. Unique hashed keys prevent repeats. Generic teaching copy/authorized links avoid duplicating sensitive assignment content; foreign mark-read returns 404. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [STU-004](#stu-004), [STU-006](#stu-006), [STU-014](#stu-014), [STU-015](#stu-015), [STU-020](#stu-020) |
| QA scenarios | Q09 |
| Actual fixture/reference | students:7, students:8, students:9, students:10, students:11, students:12, forms:3, form_versions:3, form_questions:10, form_questions:11, form_questions:12, form_triggers:4, form_triggers:5, homeworks:1, resource_assignments:1, homeworks:2, resource_assignments:2, form_submissions:2, form_submissions:3, form_versions:4, form_questions:13, form_questions:14, form_questions:15, form_submission_revisions:1, form_submission_revisions:2, form_answers:4, form_answers:5, form_answers:6, form_answers:7, form_answers:8 |
| Positive test | Executed isolated mutation/projection assertions: StudentTeachingExperienceTest::test_notifications_are_owner_scoped_persistent_and_do_not_repeat_after_read; StudentTeachingExperienceTest::test_resource_access_rechecks_owner_visibility_publication_and_safe_url; production HTTP assertions 1. |
| Negative/edge test | Executed isolated edge/security assertions: StudentTeachingExperienceTest::test_assistant_cannot_read_or_write_teaching_but_retains_existing_educational_notes_permission; StudentTeachingExperienceTest::test_suspended_staff_and_merged_students_cannot_use_teaching_workspace |
| Browser/UI evidence | UI-STUDENT-A-FIRST-VISIT (2026-10-06T22:03:45.986Z); UI-STUDENT-A-NOTIFICATION-READ (2026-10-06T22:04:10.306Z); PRELMS-001-REPRO (2026-10-06T22:07:38.059Z); UI-STUDENT-A-READ-PERSISTENCE (2026-10-06T22:07:44.134Z); PRELMS-001-RETEST-LINKS (2026-10-06T22:43:01.270Z); PRELMS-001-RETEST-CLICK (2026-10-06T22:45:30.487Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | PRELMS-001 |
| Fix commit SHA | 40be0b5f2cfc663cbe25afdd712bf41b85500409 |
| Deployed fix SHA | 40be0b5f2cfc663cbe25afdd712bf41b85500409 |
| Retest | Existing nine notifications rendered with the correct prefix and opened the owned lesson; root/subdirectory regression passes. |
| Authoritative current source | [app/Domains/Students/Services/StudentNotificationService.php](app/Domains/Students/Services/StudentNotificationService.php)<br>[app/Http/Controllers/Student/NotificationController.php](app/Http/Controllers/Student/NotificationController.php) |

### STU-023 — Optional rating/comment for own completed lesson

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Private feedback |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Private feedback. Optional rating/comment for own completed lesson. Only completed owned lessons permit submission; no public testimonial publishing. |
| Business rules | Only completed owned lessons permit submission; no public testimonial publishing. Student/Booking locks, one-row uniqueness, CSRF and 30/minute throttle prevent duplicates/foreign updates. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [STU-007](#stu-007), [SA-059](#sa-059) |
| QA scenarios | Q04, Q06 |
| Actual fixture/reference | booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, lesson_materials:2, lesson_materials:3, lesson_materials:4, lesson_materials:5, homeworks:1, learning_plans:1, learning_milestones:1, resource_assignments:1, student_error_logs:1, teaching_tags:1, student_bins:1, homeworks:2, learning_plans:2, learning_milestones:2, resource_assignments:2, student_error_logs:2, teaching_tags:2, student_bins:2, tutor_preparations:1, lesson_feedback:1, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: StudentTeachingExperienceTest::test_staff_save_teaching_records_with_student_and_optional_lesson with data set "homework"; StudentTeachingExperienceTest::test_staff_save_teaching_records_with_student_and_optional_lesson with data set "plan"; production HTTP assertions 1. |
| Negative/edge test | Executed isolated edge/security assertions: StudentTeachingExperienceTest::test_staff_cannot_attach_a_foreign_lesson_or_edit_a_foreign_record; StudentTeachingExperienceTest::test_homework_cannot_reference_a_material_from_another_lesson |
| Browser/UI evidence | student-feedback-updated (2026-10-06T23:04:34.036Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Student/LessonFeedbackController.php](app/Http/Controllers/Student/LessonFeedbackController.php) |

### STU-024 — Owned purchase statements and CSV/XLSX

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Finance |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Finance. Owned purchase statements and CSV/XLSX. Statement separates immutable price/discount/final price, actual receipts/refunds, net paid, current due/overpaid and typed available/history. |
| Business rules | Statement separates immutable price/discount/final price, actual receipts/refunds, net paid, current due/overpaid and typed available/history. No fabricated receipt or payment provider reference. Another Student purchase returns 404; responses private/no-store. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [SA-035](#sa-035), [STU-005](#stu-005), [STU-025](#stu-025) |
| QA scenarios | Q03 |
| Actual fixture/reference | payment_methods:7, student_packages:11, student_package_entitlements:5, session_ledger_entries:19, student_packages:12, student_package_entitlements:6, session_ledger_entries:20, student_packages:13, student_package_entitlements:7, session_ledger_entries:21, student_packages:14, student_package_entitlements:8, session_ledger_entries:22, student_packages:15, student_package_entitlements:9, session_ledger_entries:23, student_packages:16, student_package_entitlements:10, session_ledger_entries:24, student_packages:17, student_package_entitlements:11, session_ledger_entries:25, payment_records:13, payment_records:14, payment_records:15, payment_records:16, payment_refunds:4, payment_records:17, payment_refunds:5, package_installments:1, package_installments:2, package_renewals:1, student_packages:18, student_package_entitlements:12, session_ledger_entries:26, student_packages:19, student_package_entitlements:13, session_ledger_entries:33, payment_records:18, session_ledger_entries:36, payment_refunds:6, session_ledger_entries:37, student_packages:20, student_package_entitlements:14, session_ledger_entries:38 |
| Positive test | Executed isolated mutation/projection assertions: TypedEntitlementsTest::test_offering_screen_csv_xlsx_have_identical_purchase_and_type_provenance; BusinessLifecycleDataQualityTest::test_renewal_creates_one_linked_purchase_and_preserves_previous_terms; production HTTP assertions 11. |
| Negative/edge test | Executed isolated edge/security assertions: BusinessLifecycleDataQualityTest::test_incompatible_and_expired_purchases_never_fund_recurring_lessons; TypedEntitlementsTest::test_purchase_replay_rejects_changed_payload_or_type |
| Browser/UI evidence | student-b-statement-browser (2026-10-07T03:58:58.188Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | PRELMS-004 |
| Fix commit SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Deployed fix SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Retest | Explicit completed-response setPrivate plus no-store; FilterAwareExports16/368 and actual CSV/XLSX private/no-store semantic parity pass. |
| Authoritative current source | [app/Http/Controllers/Student/FinancialStatementController.php](app/Http/Controllers/Student/FinancialStatementController.php)<br>[app/Domains/Students/Services/FinancialStatementService.php](app/Domains/Students/Services/FinancialStatementService.php) |

### STU-025 — Read/print an actual owned payment receipt

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Finance receipts |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Finance receipts. Read/print an actual owned payment receipt. Receipt reflects an actual PaymentRecord; internal payment ID is labeled internal, and external/provider reference is shown only when already stored. |
| Business rules | Receipt reflects an actual PaymentRecord; internal payment ID is labeled internal, and external/provider reference is shown only when already stored. Method renaming does not rewrite recorded method; foreign/mismatched payment denies. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [STU-024](#stu-024), [SA-029](#sa-029), [SA-035](#sa-035) |
| QA scenarios | Q03 |
| Actual fixture/reference | payment_methods:7, student_packages:11, student_package_entitlements:5, session_ledger_entries:19, student_packages:12, student_package_entitlements:6, session_ledger_entries:20, student_packages:13, student_package_entitlements:7, session_ledger_entries:21, student_packages:14, student_package_entitlements:8, session_ledger_entries:22, student_packages:15, student_package_entitlements:9, session_ledger_entries:23, student_packages:16, student_package_entitlements:10, session_ledger_entries:24, student_packages:17, student_package_entitlements:11, session_ledger_entries:25, payment_records:13, payment_records:14, payment_records:15, payment_records:16, payment_refunds:4, payment_records:17, payment_refunds:5, package_installments:1, package_installments:2, package_renewals:1, student_packages:18, student_package_entitlements:12, session_ledger_entries:26, student_packages:19, student_package_entitlements:13, session_ledger_entries:33, payment_records:18, session_ledger_entries:36, payment_refunds:6, session_ledger_entries:37, student_packages:20, student_package_entitlements:14, session_ledger_entries:38 |
| Positive test | Executed isolated mutation/projection assertions: BusinessLifecycleDataQualityTest::test_financial_screen_export_and_receipt_are_actual_and_owner_scoped; BusinessLifecycleDataQualityTest::test_new_xlsx_exports_keep_financial_filters_and_actual_payment_rows; production HTTP assertions 4. |
| Negative/edge test | Executed isolated edge/security assertions: BusinessLifecycleDataQualityTest::test_installment_forecasts_reconcile_partial_payment_refunds_and_overpayment; BusinessLifecycleDataQualityTest::test_paused_plans_and_archived_students_cannot_generate_new_recurring_bookings |
| Browser/UI evidence | student-b-actual-payment-receipt (2026-10-07T03:58:57.789Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Student/FinancialStatementController.php](app/Http/Controllers/Student/FinancialStatementController.php) |

### STU-026 — Own holidays/unavailability with cancellation history

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Planning |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Planning. Own holidays/unavailability with cancellation history. At most roughly one year (367-day validation bound); nonexistent DST midnight gives a friendly refusal. |
| Business rules | At most roughly one year (367-day validation bound); nonexistent DST midnight gives a friendly refusal. Linked Student booking/rescheduling/recurrence checks overlap; existing commitments are not auto-cancelled. Contact-only legacy bookings cannot infer Student holidays. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [STU-009](#stu-009), [STU-010](#stu-010), [SA-050](#sa-050), [SA-048](#sa-048) |
| QA scenarios | Q05 |
| Actual fixture/reference | session_types:4, session_types:5, recurring_lesson_plans:1, recurring_lesson_occurrences:1, recurring_lesson_occurrences:2, student_unavailabilities:1, booking_waitlists:1, session_types:6 |
| Positive test | Executed isolated mutation/projection assertions: BusinessLifecycleDataQualityTest::test_student_holidays_and_tutor_blocked_dates_have_separate_safe_reasons; BusinessLifecycleDataQualityTest::test_cancellation_restores_once_and_records_separate_typed_policy_decision; production HTTP assertions 2. |
| Negative/edge test | Executed isolated edge/security assertions: BusinessLifecycleDataQualityTest::test_installment_totals_and_history_cannot_be_rewritten; BusinessLifecycleDataQualityTest::test_paused_plans_and_archived_students_cannot_generate_new_recurring_bookings |
| Browser/UI evidence | student-d-planning-created (2026-10-07T03:39:02.697Z); student-d-planning-cancelled (2026-10-07T03:39:10.740Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/StudentSchedulingService.php](app/Domains/Students/Services/StudentSchedulingService.php)<br>[app/Http/Controllers/Student/SchedulingController.php](app/Http/Controllers/Student/SchedulingController.php) |

### STU-027 — Own lesson waitlist interest and withdrawal

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Planning |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Planning. Own lesson waitlist interest and withdrawal. Maximum date-range difference 90 days; interest grants no credits and creates no Booking/hold/payment. |
| Business rules | Maximum date-range difference 90 days; interest grants no credits and creates no Booking/hold/payment. Staff follow-up and status are manual; no automatic slot assignment or outreach is promised. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [SA-049](#sa-049), [STU-026](#stu-026) |
| QA scenarios | Q05 |
| Actual fixture/reference | session_types:4, session_types:5, recurring_lesson_plans:1, recurring_lesson_occurrences:1, recurring_lesson_occurrences:2, student_unavailabilities:1, booking_waitlists:1, session_types:6 |
| Positive test | Executed isolated mutation/projection assertions: BusinessLifecycleDataQualityTest::test_archived_waitlist_interest_leaves_daily_queue_but_remains_in_history; BusinessLifecycleDataQualityTest::test_free_recurring_lessons_use_no_package_ledger; production HTTP assertions 2. |
| Negative/edge test | Executed isolated edge/security assertions: BusinessLifecycleDataQualityTest::test_student_cannot_withdraw_another_students_interest_or_holiday; BusinessLifecycleDataQualityTest::test_recurring_safe_conflicts_distinguish_notice_collision_and_inactive_lesson |
| Browser/UI evidence | student-d-waitlist-created (2026-10-07T03:39:11.449Z); student-d-waitlist-withdrawn (2026-10-07T03:39:17.533Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/StudentSchedulingService.php](app/Domains/Students/Services/StudentSchedulingService.php)<br>[app/Http/Controllers/Student/SchedulingController.php](app/Http/Controllers/Student/SchedulingController.php) |

### STU-028 — Time-gated meeting link reveal

| Field | Evidence / disposition |
|---|---|
| Audience/domain | STUDENT / Lesson access |
| Expected behavior | **Audience:** STUDENT PORTAL. **Domain:** Lesson access. Time-gated meeting link reveal. Link only reveals for confirmed not-ended lessons within configured lead minutes (default 15, range 0–1440), with assigned safe HTTPS snapshot and valid unsuspended Student where linked. |
| Business rules | Link only reveals for confirmed not-ended lessons within configured lead minutes (default 15, range 0–1440), with assigned safe HTTPS snapshot and valid unsuspended Student where linked. No early URL or completed/cancelled/no-show link; room edits do not overwrite snapshots automatically. |
| Prerequisites/dependencies | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. [STU-007](#stu-007), [SA-051](#sa-051), [PUB-012](#pub-012) |
| QA scenarios | Q04 |
| Actual fixture/reference | booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: MeetingRotationTest::test_chronological_rotation_uses_each_room_and_never_reuses_consecutive_rooms; MeetingRotationTest::test_inserting_a_booking_checks_both_neighbors_and_single_room_fails_closed |
| Negative/edge test | Executed isolated edge/security assertions: MeetingRotationTest::test_preferred_provider_wins_and_overlap_manual_override_is_rejected; StudentAuthenticationTest::test_unverified_or_ambiguous_students_cannot_authenticate |
| Browser/UI evidence | No separate browser mutation certified. Current source/controller behavior is exercised by the named isolated cases; production fixture facts are inspected where available. |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Actual future QA lessons hide the meeting link and show the15-minute reveal rule. Eligible reveal/expiry and wrong-owner cases are isolated tests; no production clock or original lesson was moved. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Booking/Services/MeetingLinkService.php](app/Domains/Booking/Services/MeetingLinkService.php)<br>[app/Http/Controllers/Student/DashboardController.php](app/Http/Controllers/Student/DashboardController.php)<br>[app/Http/Controllers/BookingController.php](app/Http/Controllers/BookingController.php) |

### SA-001 — Role-aware navigation, desktop collapse, mobile drawer and time-format preference

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Admin shell |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Admin shell. Role-aware navigation, desktop collapse, mobile drawer and time-format preference. Actual owner profile supplies name and human role label; desktop collapse and mobile drawer stay separate. |
| Business rules | Actual owner profile supplies name and human role label; desktop collapse and mobile drawer stay separate. Existing role-gated links and active/MFA authorization remain authoritative; 12/24-hour preference does not alter timestamps. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-013](#sa-013), [SA-015](#sa-015) |
| QA scenarios | Q02 |
| Actual fixture/reference | administrators:6, administrators:7, administrators:8, administrators:9, entity_translation_revisions:11, entity_translation_revisions:12, entity_translation_revisions:13, entity_translation_revisions:18, entity_translation_revisions:19, entity_translation_revisions:20, entity_translation_revisions:21, entity_translation_revisions:22, audit_logs:161, audit_logs:162, audit_logs:163, audit_logs:164, audit_logs:165, audit_logs:166, audit_logs:167, audit_logs:168, audit_logs:169, audit_logs:170, audit_logs:171, audit_logs:172, audit_logs:173, audit_logs:174, audit_logs:175, audit_logs:176, audit_logs:177, audit_logs:178, audit_logs:179, audit_logs:180, audit_logs:181, audit_logs:182, audit_logs:183, audit_logs:184, audit_logs:185, audit_logs:186, audit_logs:187, audit_logs:188, audit_logs:189, audit_logs:190, audit_logs:191, audit_logs:192, audit_logs:193, audit_logs:194, audit_logs:195, audit_logs:197, audit_logs:198, audit_logs:199, audit_logs:200, audit_logs:201, audit_logs:202, audit_logs:203, audit_logs:204, audit_logs:205, audit_logs:206, audit_logs:207, audit_logs:208, audit_logs:209, audit_logs:210, audit_logs:211, audit_logs:212, audit_logs:213, audit_logs:214, audit_logs:215, audit_logs:216, audit_logs:217, audit_logs:218, audit_logs:219, audit_logs:220, audit_logs:221, audit_logs:222, audit_logs:223, audit_logs:224, audit_logs:225, audit_logs:226, audit_logs:227, audit_logs:228, audit_logs:229, audit_logs:230, audit_logs:231, audit_logs:239, audit_logs:240, audit_logs:241, audit_logs:242, audit_logs:243, audit_logs:244, audit_logs:245, audit_logs:246, audit_logs:247, audit_logs:248, audit_logs:249, audit_logs:157, audit_logs:158, audit_logs:159, audit_logs:160 |
| Positive test | Executed isolated mutation/projection assertions: AdminDrawerAndSocialAccessibilityTest::test_admin_mobile_drawer_attributes_and_accessibility_markup; AdminDrawerAndSocialAccessibilityTest::test_public_navigation_contains_language_switcher; production HTTP assertions 3. |
| Negative/edge test | Executed isolated edge/security assertions: StageOnePresentationTest::test_assistant_shell_omits_destinations_denied_by_existing_role_middleware; StaffOperationsProductivityTest::test_saved_views_apply_allowlisted_filters_and_cannot_be_accessed_across_users with data set #0 |
| Browser/UI evidence | staff-mfa-signin (2026-10-06T22:49:15.081Z); staff-time-format-12h (2026-10-06T22:55:49.675Z); staff-sidebar-collapsed (2026-10-06T22:55:51.195Z); assistant-entry (2026-10-06T23:06:51.502Z); staff-mobile-drawer-open (2026-10-06T23:07:23.779Z); staff-mobile-drawer-escape (2026-10-06T23:07:24.288Z); assistant-forbidden-sidebar-link (2026-10-06T23:08:39.765Z); assistant-repaired-navigation (2026-10-07T03:37:06.637Z); assistant-direct-settings-denied (2026-10-07T03:37:23.310Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | PRELMS-005 |
| Fix commit SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Deployed fix SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Retest | Actual postdeploy Assistant has retained eight destinations, no forbidden settings/CMS/finance links; direct Settings still403; StageOne12/116 passes. |
| Authoritative current source | [resources/views/layouts/admin.blade.php](resources/views/layouts/admin.blade.php)<br>[resources/js/app.js](resources/js/app.js)<br>[app/Http/Controllers/Admin/DashboardController.php](app/Http/Controllers/Admin/DashboardController.php)<br>[app/Http/Controllers/Admin/SettingController.php](app/Http/Controllers/Admin/SettingController.php) |

### SA-002 — Quick launcher, Student finder and existing cross-domain search

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Productivity/search |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Productivity/search. Quick launcher, Student finder and existing cross-domain search. Launcher filters existing permitted destinations and submits to the current Student Records finder; global search uses its own Contact/Booking/Resource query (minimum 2 characters, up to 10 each). |
| Business rules | Launcher filters existing permitted destinations and submits to the current Student Records finder; global search uses its own Contact/Booking/Resource query (minimum 2 characters, up to 10 each). It is not a new indexing engine or permission bypass. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-019](#sa-019), [SA-001](#sa-001) |
| QA scenarios | Q07 |
| Actual fixture/reference | resource_categories:3, resources:2, resources:3, staff_recent_views:1, staff_recent_views:2, staff_recent_views:3, contacts:11, contacts:12, contacts:14, contacts:15, contacts:13, contacts:16, resource_translations:2, resource_translations:3, category_translations:3, resource_downloads:2, resource_downloads:3, resource_requests:4, resource_requests:5, content_revisions:9 |
| Positive test | Executed isolated mutation/projection assertions: StaffOperationsProductivityTest::test_assistant_login_has_an_authorized_landing_and_launcher_hides_privileged_destinations; AdminFunctionsAndCalendarTest::test_notification_creation_read_state_and_deduplication |
| Negative/edge test | Executed isolated edge/security assertions: StaffOperationsProductivityTest::test_saved_views_apply_allowlisted_filters_and_cannot_be_accessed_across_users with data set #0; StaffOperationsProductivityTest::test_saved_views_apply_allowlisted_filters_and_cannot_be_accessed_across_users with data set #1 |
| Browser/UI evidence | staff-mfa-signin (2026-10-06T22:49:15.081Z); staff-finder (2026-10-06T22:56:06.070Z); assistant-repaired-navigation (2026-10-07T03:37:06.637Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | PRELMS-005 |
| Fix commit SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Deployed fix SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Retest | Actual postdeploy Assistant has retained eight destinations, no forbidden settings/CMS/finance links; direct Settings still403; StageOne12/116 passes. |
| Authoritative current source | [resources/views/components/staff-quick-actions.blade.php](resources/views/components/staff-quick-actions.blade.php)<br>[app/Http/Controllers/Admin/SearchController.php](app/Http/Controllers/Admin/SearchController.php)<br>[app/Http/Controllers/Admin/StudentController.php](app/Http/Controllers/Admin/StudentController.php) |

### SA-003 — Tutor dashboard and next-lesson business/customer clocks

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Operations |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Operations. Tutor dashboard and next-lesson business/customer clocks. Next lesson shows business and actual customer timezone; current schedule/status and distinct business counts come from records. |
| Business rules | Next lesson shows business and actual customer timezone; current schedule/status and distinct business counts come from records. Only Super Admin sees audit summary; Assistant lands on Today, not this privileged dashboard. Empty upcoming schedule is a supported state. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-039](#sa-039), [SA-004](#sa-004), [SA-082](#sa-082) |
| QA scenarios | Q04 |
| Actual fixture/reference | booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, audit_logs:196, audit_logs:161, audit_logs:162, audit_logs:163, audit_logs:164, audit_logs:165, audit_logs:166, audit_logs:167, audit_logs:168, audit_logs:169, audit_logs:170, audit_logs:171, audit_logs:172, audit_logs:175, audit_logs:176, audit_logs:177, audit_logs:178, audit_logs:179, audit_logs:180, audit_logs:181, audit_logs:182, audit_logs:183, audit_logs:184, audit_logs:185, audit_logs:186, audit_logs:187, audit_logs:188, audit_logs:189, audit_logs:190, audit_logs:191, audit_logs:192, audit_logs:193, audit_logs:194, audit_logs:195, audit_logs:197, audit_logs:198, audit_logs:199, audit_logs:200, audit_logs:201, audit_logs:202, audit_logs:203, audit_logs:204, audit_logs:205, audit_logs:206, audit_logs:207, audit_logs:208, audit_logs:209, audit_logs:210, audit_logs:211, audit_logs:212, audit_logs:213, audit_logs:214, audit_logs:215, audit_logs:216, audit_logs:217, audit_logs:218, audit_logs:219, audit_logs:220, audit_logs:221, audit_logs:222, audit_logs:223, audit_logs:224, audit_logs:225, audit_logs:226, audit_logs:227, audit_logs:228, audit_logs:229, audit_logs:230, audit_logs:231, audit_logs:239, audit_logs:240, audit_logs:241, audit_logs:242, audit_logs:243, audit_logs:244, audit_logs:245, audit_logs:246, audit_logs:247, audit_logs:248, audit_logs:249, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: BusinessLifecycleDataQualityTest::test_dashboard_student_time_uses_booking_timezone_for_next_and_today_lessons; BusinessLifecycleDataQualityTest::test_free_recurring_lessons_use_no_package_ledger |
| Negative/edge test | Executed isolated edge/security assertions: BusinessLifecycleDataQualityTest::test_recurring_safe_conflicts_distinguish_notice_collision_and_inactive_lesson; BusinessLifecycleDataQualityTest::test_incompatible_and_expired_purchases_never_fund_recurring_lessons |
| Browser/UI evidence | staff-time-format-12h (2026-10-06T22:55:49.675Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/DashboardController.php](app/Http/Controllers/Admin/DashboardController.php)<br>[resources/views/admin/dashboard.blade.php](resources/views/admin/dashboard.blade.php) |

### SA-004 — Today, follow-up queues and canonical business summaries

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Operations |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Operations. Today, follow-up queues and canonical business summaries. Each section displays at most 12 while counts cover all matches. |
| Business rules | Each section displays at most 12 while counts cover all matches. Low credit aggregates only eligible same-type units per Student (0–2); expiry window is 7 business days. Inactive daily queues omit archived/inactive students but existing booked commitments remain visible. Assistant financial/form sections and task scope are restricted. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-008](#sa-008), [SA-011](#sa-011), [SA-007](#sa-007), [SA-034](#sa-034), [SA-025](#sa-025), [SA-010](#sa-010) |
| QA scenarios | Q04, Q10 |
| Actual fixture/reference | booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, staff_note_preferences:1, staff_bins:2, staff_bins:3, staff_tasks:1, student_operational_alerts:1, staff_tasks:2, student_operational_alerts:2, staff_saved_views:1, staff_saved_views:2, staff_saved_views:3, staff_recent_views:1, staff_recent_views:2, staff_recent_views:3, admin_notifications:17, admin_notifications:18, admin_notifications:19, admin_notifications:20, admin_notifications:21, admin_notifications:22, admin_notifications:23, admin_notifications:24, admin_notifications:25, admin_notifications:26, admin_notifications:27, admin_notifications:28, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: StaffOperationsProductivityTest::test_today_uses_business_day_boundaries_exact_counts_and_assistant_assignment_scope; BusinessLifecycleDataQualityTest::test_receivables_and_business_report_reconcile_same_canonical_balance_and_dates; production HTTP assertions 2. |
| Negative/edge test | Executed isolated edge/security assertions: StaffOperationsProductivityTest::test_today_financial_projection_uses_typed_balances_and_net_payments_excludes_expired_and_pools_only_same_type; BusinessLifecycleDataQualityTest::test_paused_plans_and_archived_students_cannot_generate_new_recurring_bookings |
| Browser/UI evidence | staff-page-today-operations (2026-10-06T22:49:29.391Z); assistant-entry (2026-10-06T23:06:51.502Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Administration/Services/OperationsReadModel.php](app/Domains/Administration/Services/OperationsReadModel.php)<br>[app/Http/Controllers/Admin/OperationsController.php](app/Http/Controllers/Admin/OperationsController.php) |

### SA-005 — Create, search, edit and soft-delete Staff Notes

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Staff notes |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Staff notes. Create, search, edit and soft-delete Staff Notes. Notes are staff-only and escaped; q/owner/order/collection filters apply. |
| Business rules | Notes are staff-only and escaped; q/owner/order/collection filters apply. Existing author protections remain; Super Admin can manage/delete others, DELETE confirmation required, history retained. Student Educational Notes are a separate ownership domain. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-006](#sa-006), [SA-007](#sa-007), [SA-009](#sa-009) |
| QA scenarios | Q10 |
| Actual fixture/reference | staff_note_preferences:1, staff_bins:2, staff_bins:3, staff_tasks:1, student_operational_alerts:1, staff_tasks:2, student_operational_alerts:2, staff_saved_views:1, staff_saved_views:2, staff_saved_views:3, staff_recent_views:1, staff_recent_views:2, staff_recent_views:3, admin_notifications:17, admin_notifications:18, admin_notifications:19, admin_notifications:20, admin_notifications:21, admin_notifications:22, admin_notifications:23, admin_notifications:24, admin_notifications:25, admin_notifications:26, admin_notifications:27, admin_notifications:28 |
| Positive test | Executed isolated mutation/projection assertions: BinAuthorizationTest::test_staff_roles_can_share_and_edit_only_their_own_notes_and_only_super_admin_can_delete; BinAuthorizationTest::test_privacy_erasure_redacts_active_and_soft_deleted_student_notes_without_touching_other_students |
| Negative/edge test | Executed isolated edge/security assertions: BinAuthorizationTest::test_rollback_refuses_to_drop_populated_note_tables_even_when_the_only_note_is_soft_deleted; BinAuthorizationTest::test_student_notes_are_isolated_hidden_notes_are_404_and_payload_cannot_change_ownership |
| Browser/UI evidence | staff-page-staff-notes (2026-10-06T22:49:31.117Z); staff-note-created (2026-10-07T03:51:40.240Z); staff-note-edited-persisted (2026-10-07T03:51:57.100Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/StaffBinController.php](app/Http/Controllers/Admin/StaffBinController.php) |

### SA-006 — Shared operational pins with actor/time

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Staff notes |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Staff notes. Shared operational pins with actor/time. Only Super Admin changes shared pin; current authorized staff can read it. |
| Business rules | Only Super Admin changes shared pin; current authorized staff can read it. Unpin clears shared actor/time, without changing personal preference flags. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-005](#sa-005), [SA-004](#sa-004) |
| QA scenarios | Q10 |
| Actual fixture/reference | staff_note_preferences:1, staff_bins:2, staff_bins:3, staff_tasks:1, student_operational_alerts:1, staff_tasks:2, student_operational_alerts:2, staff_saved_views:1, staff_saved_views:2, staff_saved_views:3, staff_recent_views:1, staff_recent_views:2, staff_recent_views:3, admin_notifications:17, admin_notifications:18, admin_notifications:19, admin_notifications:20, admin_notifications:21, admin_notifications:22, admin_notifications:23, admin_notifications:24, admin_notifications:25, admin_notifications:26, admin_notifications:27, admin_notifications:28 |
| Positive test | Executed isolated mutation/projection assertions: StaffOperationsProductivityTest::test_shared_pins_follow_super_admin_authority_and_are_visible_to_all_staff with data set #0; StaffOperationsProductivityTest::test_shared_pins_follow_super_admin_authority_and_are_visible_to_all_staff with data set #1 |
| Negative/edge test | Executed isolated edge/security assertions: StaffOperationsProductivityTest::test_saved_views_apply_allowlisted_filters_and_cannot_be_accessed_across_users with data set #0; StaffOperationsProductivityTest::test_saved_views_apply_allowlisted_filters_and_cannot_be_accessed_across_users with data set #1 |
| Browser/UI evidence | staff-page-staff-notes (2026-10-06T22:49:31.117Z); staff-personal-pin-favorite (2026-10-06T22:53:30.304Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/StaffBinController.php](app/Http/Controllers/Admin/StaffBinController.php)<br>[app/Policies/StaffBinPolicy.php](app/Policies/StaffBinPolicy.php)<br>[app/Http/Controllers/Admin/OperationsController.php](app/Http/Controllers/Admin/OperationsController.php) |

### SA-007 — Personal pins, favorites and collections

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Staff notes |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Staff notes. Personal pins, favorites and collections. Personal flags do not change global pin or another staff member. |
| Business rules | Personal flags do not change global pin or another staff member. Updating one flag leaves the other intact; deleted notes are excluded. Collection/sort queries use authenticated ID. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-005](#sa-005), [SA-006](#sa-006) |
| QA scenarios | Q10 |
| Actual fixture/reference | staff_note_preferences:1, staff_bins:2, staff_bins:3, staff_tasks:1, student_operational_alerts:1, staff_tasks:2, student_operational_alerts:2, staff_saved_views:1, staff_saved_views:2, staff_saved_views:3, staff_recent_views:1, staff_recent_views:2, staff_recent_views:3, admin_notifications:17, admin_notifications:18, admin_notifications:19, admin_notifications:20, admin_notifications:21, admin_notifications:22, admin_notifications:23, admin_notifications:24, admin_notifications:25, admin_notifications:26, admin_notifications:27, admin_notifications:28 |
| Positive test | Executed isolated mutation/projection assertions: StaffOperationsProductivityTest::test_personal_pins_and_favorites_are_independent_unique_and_owner_scoped with data set #0; StaffOperationsProductivityTest::test_personal_pins_and_favorites_are_independent_unique_and_owner_scoped with data set #1 |
| Negative/edge test | Executed isolated edge/security assertions: StaffOperationsProductivityTest::test_saved_views_apply_allowlisted_filters_and_cannot_be_accessed_across_users with data set #0; StaffOperationsProductivityTest::test_saved_views_apply_allowlisted_filters_and_cannot_be_accessed_across_users with data set #1 |
| Browser/UI evidence | staff-mfa-signin (2026-10-06T22:49:15.081Z); staff-page-staff-notes (2026-10-06T22:49:31.117Z); staff-personal-pin-favorite (2026-10-06T22:53:30.304Z); assistant-repaired-navigation (2026-10-07T03:37:06.637Z); staff-personal-view-replaced-applied (2026-10-07T03:52:16.808Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/StaffBinController.php](app/Http/Controllers/Admin/StaffBinController.php) |

### SA-008 — Lightweight assigned tasks and status lifecycle

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Staff productivity |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Staff productivity. Lightweight assigned tasks and status lifecycle. Open/in_progress/completed/cancelled lifecycle; completing sets original completion instant, reopening clears it. |
| Business rules | Open/in_progress/completed/cancelled lifecycle; completing sets original completion instant, reopening clears it. Super Admin/Admin manage assignment/text; Assistant creates for self and changes assigned task status only. Invalid merged/foreign parent or assignee refuses. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-004](#sa-004), [SA-009](#sa-009), [SA-025](#sa-025) |
| QA scenarios | Q10 |
| Actual fixture/reference | staff_note_preferences:1, staff_bins:2, staff_bins:3, staff_tasks:1, student_operational_alerts:1, staff_tasks:2, student_operational_alerts:2, staff_saved_views:1, staff_saved_views:2, staff_saved_views:3, staff_recent_views:1, staff_recent_views:2, staff_recent_views:3, admin_notifications:17, admin_notifications:18, admin_notifications:19, admin_notifications:20, admin_notifications:21, admin_notifications:22, admin_notifications:23, admin_notifications:24, admin_notifications:25, admin_notifications:26, admin_notifications:27, admin_notifications:28 |
| Positive test | Executed isolated mutation/projection assertions: StaffOperationsProductivityTest::test_admin_creates_assigns_and_edits_tasks_with_validated_references_and_completion_lifecycle; StaffOperationsProductivityTest::test_assistant_tasks_are_assignment_scoped_and_only_status_can_change |
| Negative/edge test | Executed isolated edge/security assertions: StaffOperationsProductivityTest::test_saved_views_apply_allowlisted_filters_and_cannot_be_accessed_across_users with data set #0; StaffOperationsProductivityTest::test_saved_views_apply_allowlisted_filters_and_cannot_be_accessed_across_users with data set #1 |
| Browser/UI evidence | staff-page-staff-tasks (2026-10-06T22:49:30.294Z); assistant-task-progress (2026-10-06T23:07:05.227Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/StaffTaskController.php](app/Http/Controllers/Admin/StaffTaskController.php)<br>[app/Domains/Administration/Services/StaffTaskQuery.php](app/Domains/Administration/Services/StaffTaskQuery.php) |

### SA-009 — Save, apply, replace-by-name and remove personal filter views

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Personal productivity |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Personal productivity. Save, apply, replace-by-name and remove personal filter views. Only Students, Staff Notes and Tasks are supported. |
| Business rules | Only Students, Staff Notes and Tasks are supported. Section-specific scalar allowlists reuse current validation; nested/unknown filters reject, same name updates, max 20 per actor/section and foreign IDs 404. Views are personal, not shared. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-019](#sa-019), [SA-005](#sa-005), [SA-008](#sa-008) |
| QA scenarios | Q10 |
| Actual fixture/reference | staff_note_preferences:1, staff_bins:2, staff_bins:3, staff_tasks:1, student_operational_alerts:1, staff_tasks:2, student_operational_alerts:2, staff_saved_views:1, staff_saved_views:2, staff_saved_views:3, staff_recent_views:1, staff_recent_views:2, staff_recent_views:3, admin_notifications:17, admin_notifications:18, admin_notifications:19, admin_notifications:20, admin_notifications:21, admin_notifications:22, admin_notifications:23, admin_notifications:24, admin_notifications:25, admin_notifications:26, admin_notifications:27, admin_notifications:28 |
| Positive test | Executed isolated mutation/projection assertions: StaffOperationsProductivityTest::test_note_filters_compose_and_pinned_notes_sort_prominently_without_leaking_other_personal_flags; StaffOperationsProductivityTest::test_recent_views_record_only_ids_after_authorized_reads_and_resolve_current_names_per_user |
| Negative/edge test | Executed isolated edge/security assertions: StaffOperationsProductivityTest::test_saved_views_apply_allowlisted_filters_and_cannot_be_accessed_across_users with data set #0; StaffOperationsProductivityTest::test_saved_views_apply_allowlisted_filters_and_cannot_be_accessed_across_users with data set #1 |
| Browser/UI evidence | staff-page-staff-notes (2026-10-06T22:49:31.117Z); staff-personal-view-saved (2026-10-07T03:51:58.397Z); staff-personal-view-replaced-applied (2026-10-07T03:52:16.808Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/StaffSavedViewController.php](app/Http/Controllers/Admin/StaffSavedViewController.php)<br>[app/Domains/Administration/Services/StaffSavedViewService.php](app/Domains/Administration/Services/StaffSavedViewService.php) |

### SA-010 — Recently viewed Student, Booking/lesson and Contact shortcuts

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Personal productivity |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Personal productivity. Recently viewed Student, Booking/lesson and Contact shortcuts. Stores no page snapshots or copied PII/text. |
| Business rules | Stores no page snapshots or copied PII/text. Guest/suspended/forbidden reads do not record; merged/deleted/inactive Student links are omitted; history is per staff member. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-004](#sa-004), [SA-019](#sa-019), [SA-021](#sa-021), [SA-039](#sa-039) |
| QA scenarios | Q10 |
| Actual fixture/reference | staff_note_preferences:1, staff_bins:2, staff_bins:3, staff_tasks:1, student_operational_alerts:1, staff_tasks:2, student_operational_alerts:2, staff_saved_views:1, staff_saved_views:2, staff_saved_views:3, staff_recent_views:1, staff_recent_views:2, staff_recent_views:3, admin_notifications:17, admin_notifications:18, admin_notifications:19, admin_notifications:20, admin_notifications:21, admin_notifications:22, admin_notifications:23, admin_notifications:24, admin_notifications:25, admin_notifications:26, admin_notifications:27, admin_notifications:28 |
| Positive test | Executed isolated mutation/projection assertions: StaffOperationsProductivityTest::test_contextual_booking_prefills_the_existing_manual_flow_and_preserves_permissions; StaffOperationsProductivityTest::test_operations_queries_stay_bounded_as_lesson_and_payment_rows_grow |
| Negative/edge test | Executed isolated edge/security assertions: StaffOperationsProductivityTest::test_saved_views_apply_allowlisted_filters_and_cannot_be_accessed_across_users with data set #0; StaffOperationsProductivityTest::test_saved_views_apply_allowlisted_filters_and_cannot_be_accessed_across_users with data set #1 |
| Browser/UI evidence | staff-page-today-operations (2026-10-06T22:49:29.391Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Middleware/TrackStaffRecentView.php](app/Http/Middleware/TrackStaffRecentView.php)<br>[app/Domains/Administration/Services/StaffRecentViewService.php](app/Domains/Administration/Services/StaffRecentViewService.php)<br>[app/Http/Controllers/Admin/OperationsController.php](app/Http/Controllers/Admin/OperationsController.php)<br>[app/Http/Controllers/Admin/StudentController.php](app/Http/Controllers/Admin/StudentController.php)<br>[app/Http/Controllers/Admin/BookingController.php](app/Http/Controllers/Admin/BookingController.php)<br>[app/Http/Controllers/Admin/ContactController.php](app/Http/Controllers/Admin/ContactController.php) |

### SA-011 — Prominent operational alerts and resolved/archived history

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Student operations |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Student operations. Prominent operational alerts and resolved/archived history. Active/resolved/archived alerts remain separate from teaching notes and finance. |
| Business rules | Active/resolved/archived alerts remain separate from teaching notes and finance. Student-first lock rechecks parent; Assistant reads without management controls; content escaped and not sent into generic analytics/exports. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-019](#sa-019), [SA-004](#sa-004), [SA-025](#sa-025) |
| QA scenarios | Q10 |
| Actual fixture/reference | staff_note_preferences:1, staff_bins:2, staff_bins:3, staff_tasks:1, student_operational_alerts:1, staff_tasks:2, student_operational_alerts:2, staff_saved_views:1, staff_saved_views:2, staff_saved_views:3, staff_recent_views:1, staff_recent_views:2, staff_recent_views:3, admin_notifications:17, admin_notifications:18, admin_notifications:19, admin_notifications:20, admin_notifications:21, admin_notifications:22, admin_notifications:23, admin_notifications:24, admin_notifications:25, admin_notifications:26, admin_notifications:27, admin_notifications:28 |
| Positive test | Executed isolated mutation/projection assertions: StaffOperationsProductivityTest::test_merge_preserves_operations_and_privacy_removes_student_details_from_tasks_alerts_and_history; StaffOperationsProductivityTest::test_note_filters_compose_and_pinned_notes_sort_prominently_without_leaking_other_personal_flags |
| Negative/edge test | Executed isolated edge/security assertions: StaffOperationsProductivityTest::test_saved_views_apply_allowlisted_filters_and_cannot_be_accessed_across_users with data set #0; StaffOperationsProductivityTest::test_saved_views_apply_allowlisted_filters_and_cannot_be_accessed_across_users with data set #1 |
| Browser/UI evidence | staff-page-today-operations (2026-10-06T22:49:29.391Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Active QA alerts and resolved-history projections are inspected; resolution/archive transitions are isolated tests, preserving the prepared operational alert. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/StudentOperationalAlertController.php](app/Http/Controllers/Admin/StudentOperationalAlertController.php) |

### SA-012 — Staff notification inbox and read/delete state

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Staff notifications |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Staff notifications. Staff notification inbox and read/delete state. Administrative operational/business/system notifications differ from Student in-app events. |
| Business rules | Administrative operational/business/system notifications differ from Student in-app events. Delivery producers may be background or request-driven; inbox actions are explicit and role/auth scoped. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-013](#sa-013), [SA-080](#sa-080), [STU-022](#stu-022) |
| QA scenarios | Q10 |
| Actual fixture/reference | staff_note_preferences:1, staff_bins:2, staff_bins:3, staff_tasks:1, student_operational_alerts:1, staff_tasks:2, student_operational_alerts:2, staff_saved_views:1, staff_saved_views:2, staff_saved_views:3, staff_recent_views:1, staff_recent_views:2, staff_recent_views:3, admin_notifications:17, admin_notifications:18, admin_notifications:19, admin_notifications:20, admin_notifications:21, admin_notifications:22, admin_notifications:23, admin_notifications:24, admin_notifications:25, admin_notifications:26, admin_notifications:27, admin_notifications:28 |
| Positive test | Executed isolated mutation/projection assertions: BookingCreationNotificationTest::test_livewire_booking_confirmation_emits_exactly_one_notification; BookingCreationNotificationTest::test_http_admin_creation_emits_exactly_one_notification |
| Negative/edge test | Executed isolated edge/security assertions: BookingCreationNotificationTest::test_idempotent_booking_replay_emits_no_duplicate_notification; AdminOperationsTest::test_unauthenticated_user_redirected_to_admin_login |
| Browser/UI evidence | staff-page-notifications (2026-10-06T22:50:05.878Z); staff-page-notifications (2026-10-06T22:50:47.626Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Inbox and newly generated QA booking notices are inspected. Read/delete security and persistence are isolated tests; no production inbox deletion was performed. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/NotificationController.php](app/Http/Controllers/Admin/NotificationController.php)<br>[app/Domains/Administration/Services/AdminNotificationService.php](app/Domains/Administration/Services/AdminNotificationService.php) |

### SA-013 — Password sign-in, staged MFA proof and staff logout

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Staff authentication |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Staff authentication. Password sign-in, staged MFA proof and staff logout. Enabled Super Admin password stage alone grants no guard access; fresh TOTP/unused recovery completes it. |
| Business rules | Enabled Super Admin password stage alone grants no guard access; fresh TOTP/unused recovery completes it. Current security/password/role/suspension invalidates stale proof; MFA accounts cannot bypass with remembered cookies. Assistant authorized landing is Today; Student guard is independent. |
| Prerequisites/dependencies | Existing active staff account and human-entered existing password; valid factor when enabled. [SA-015](#sa-015), [SA-018](#sa-018), [SA-001](#sa-001) |
| QA scenarios | Q02 |
| Actual fixture/reference | administrators:6, administrators:7, administrators:8, administrators:9, entity_translation_revisions:11, entity_translation_revisions:12, entity_translation_revisions:13, entity_translation_revisions:18, entity_translation_revisions:19, entity_translation_revisions:20, entity_translation_revisions:21, entity_translation_revisions:22, audit_logs:161, audit_logs:162, audit_logs:163, audit_logs:164, audit_logs:165, audit_logs:166, audit_logs:167, audit_logs:168, audit_logs:169, audit_logs:170, audit_logs:171, audit_logs:172, audit_logs:173, audit_logs:174, audit_logs:175, audit_logs:176, audit_logs:177, audit_logs:178, audit_logs:179, audit_logs:180, audit_logs:181, audit_logs:182, audit_logs:183, audit_logs:184, audit_logs:185, audit_logs:186, audit_logs:187, audit_logs:188, audit_logs:189, audit_logs:190, audit_logs:191, audit_logs:192, audit_logs:193, audit_logs:194, audit_logs:195, audit_logs:197, audit_logs:198, audit_logs:199, audit_logs:200, audit_logs:201, audit_logs:202, audit_logs:203, audit_logs:204, audit_logs:205, audit_logs:206, audit_logs:207, audit_logs:208, audit_logs:209, audit_logs:210, audit_logs:211, audit_logs:212, audit_logs:213, audit_logs:214, audit_logs:215, audit_logs:216, audit_logs:217, audit_logs:218, audit_logs:219, audit_logs:220, audit_logs:221, audit_logs:222, audit_logs:223, audit_logs:224, audit_logs:225, audit_logs:226, audit_logs:227, audit_logs:228, audit_logs:229, audit_logs:230, audit_logs:231, audit_logs:239, audit_logs:240, audit_logs:241, audit_logs:242, audit_logs:243, audit_logs:244, audit_logs:245, audit_logs:246, audit_logs:247, audit_logs:248, audit_logs:249, audit_logs:157, audit_logs:158, audit_logs:159, audit_logs:160 |
| Positive test | Executed isolated mutation/projection assertions: AdministratorAuthorizationTest::test_assistants_can_access_assigned_read_only_pages_but_not_privileged_actions_or_draft_previews; AdministratorTwoFactorTest::test_enrollment_requires_current_password_and_stays_pending_until_confirmation; production HTTP assertions 2. |
| Negative/edge test | Executed isolated edge/security assertions: AdministratorTwoFactorTest::test_expired_pending_login_and_changed_password_require_password_stage_again; AdministratorTwoFactorTest::test_regeneration_requires_password_and_factor_and_invalidates_all_old_codes |
| Browser/UI evidence | assistant-entry (2026-10-06T23:06:51.502Z); assistant-normal-signout (2026-10-06T23:09:27.506Z); qa-staff-renewed-session (2026-10-07T02:54:42.788Z); final-qa-super-mfa (2026-10-07T03:47:40.062Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/AuthController.php](app/Http/Controllers/Admin/AuthController.php)<br>[app/Domains/Administration/Services/AdministratorLoginService.php](app/Domains/Administration/Services/AdministratorLoginService.php)<br>[app/Http/Controllers/Admin/TwoFactorChallengeController.php](app/Http/Controllers/Admin/TwoFactorChallengeController.php) |

### SA-014 — Staff reset-link request and password reset without removing MFA

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Password recovery |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Password recovery. Staff reset-link request and password reset without removing MFA. Request/attempt throttles and token expiry apply; successful reset retains active MFA and requires factor on next sign-in. |
| Business rules | Request/attempt throttles and token expiry apply; successful reset retains active MFA and requires factor on next sign-in. Current Log mailer does not prove external inbox delivery. No public owner emergency bypass link. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-013](#sa-013), [SA-015](#sa-015) |
| QA scenarios | Q02 |
| Actual fixture/reference | administrators:6, administrators:7, administrators:8, administrators:9, entity_translation_revisions:11, entity_translation_revisions:12, entity_translation_revisions:13, entity_translation_revisions:18, entity_translation_revisions:19, entity_translation_revisions:20, entity_translation_revisions:21, entity_translation_revisions:22, audit_logs:161, audit_logs:162, audit_logs:163, audit_logs:164, audit_logs:165, audit_logs:166, audit_logs:167, audit_logs:168, audit_logs:169, audit_logs:170, audit_logs:171, audit_logs:172, audit_logs:173, audit_logs:174, audit_logs:175, audit_logs:176, audit_logs:177, audit_logs:178, audit_logs:179, audit_logs:180, audit_logs:181, audit_logs:182, audit_logs:183, audit_logs:184, audit_logs:185, audit_logs:186, audit_logs:187, audit_logs:188, audit_logs:189, audit_logs:190, audit_logs:191, audit_logs:192, audit_logs:193, audit_logs:194, audit_logs:195, audit_logs:197, audit_logs:198, audit_logs:199, audit_logs:200, audit_logs:201, audit_logs:202, audit_logs:203, audit_logs:204, audit_logs:205, audit_logs:206, audit_logs:207, audit_logs:208, audit_logs:209, audit_logs:210, audit_logs:211, audit_logs:212, audit_logs:213, audit_logs:214, audit_logs:215, audit_logs:216, audit_logs:217, audit_logs:218, audit_logs:219, audit_logs:220, audit_logs:221, audit_logs:222, audit_logs:223, audit_logs:224, audit_logs:225, audit_logs:226, audit_logs:227, audit_logs:228, audit_logs:229, audit_logs:230, audit_logs:231, audit_logs:239, audit_logs:240, audit_logs:241, audit_logs:242, audit_logs:243, audit_logs:244, audit_logs:245, audit_logs:246, audit_logs:247, audit_logs:248, audit_logs:249, audit_logs:157, audit_logs:158, audit_logs:159, audit_logs:160 |
| Positive test | Executed isolated mutation/projection assertions: AdministratorTwoFactorTest::test_password_reset_keeps_two_factor_and_requires_challenge_after_reset; PasswordRecoveryTest::test_valid_password_reset_succeeds_and_updates_password |
| Negative/edge test | Executed isolated edge/security assertions: AdministratorTwoFactorTest::test_expired_pending_login_and_changed_password_require_password_stage_again; AdministratorTwoFactorTest::test_regeneration_requires_password_and_factor_and_invalidates_all_old_codes |
| Browser/UI evidence | No separate browser mutation certified. Current source/controller behavior is exercised by the named isolated cases; production fixture facts are inspected where available. |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Reset-link/credential completion is isolated-test coverage. Production mail remains log and existing credentials/MFA are preserved. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/PasswordResetController.php](app/Http/Controllers/Admin/PasswordResetController.php) |

### SA-015 — Optional Super Admin TOTP enrollment and confirmation

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Account Security |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Account Security. Optional Super Admin TOTP enrollment and confirmation. Only Super Admin manages own MFA. |
| Business rules | Only Super Admin manages own MFA. Current password required; pending enrollment expires 10 minutes and QR/manual key appears only on initial response. Valid six-digit 30-second TOTP with ±1 step activates; one-time recovery plaintext is not stored/redisplayed. Partial/corrupt security state fails closed. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-013](#sa-013), [SA-016](#sa-016) |
| QA scenarios | Q02 |
| Actual fixture/reference | administrators:6, administrators:7, administrators:8, administrators:9, entity_translation_revisions:11, entity_translation_revisions:12, entity_translation_revisions:13, entity_translation_revisions:18, entity_translation_revisions:19, entity_translation_revisions:20, entity_translation_revisions:21, entity_translation_revisions:22, audit_logs:161, audit_logs:162, audit_logs:163, audit_logs:164, audit_logs:165, audit_logs:166, audit_logs:167, audit_logs:168, audit_logs:169, audit_logs:170, audit_logs:171, audit_logs:172, audit_logs:173, audit_logs:174, audit_logs:175, audit_logs:176, audit_logs:177, audit_logs:178, audit_logs:179, audit_logs:180, audit_logs:181, audit_logs:182, audit_logs:183, audit_logs:184, audit_logs:185, audit_logs:186, audit_logs:187, audit_logs:188, audit_logs:189, audit_logs:190, audit_logs:191, audit_logs:192, audit_logs:193, audit_logs:194, audit_logs:195, audit_logs:197, audit_logs:198, audit_logs:199, audit_logs:200, audit_logs:201, audit_logs:202, audit_logs:203, audit_logs:204, audit_logs:205, audit_logs:206, audit_logs:207, audit_logs:208, audit_logs:209, audit_logs:210, audit_logs:211, audit_logs:212, audit_logs:213, audit_logs:214, audit_logs:215, audit_logs:216, audit_logs:217, audit_logs:218, audit_logs:219, audit_logs:220, audit_logs:221, audit_logs:222, audit_logs:223, audit_logs:224, audit_logs:225, audit_logs:226, audit_logs:227, audit_logs:228, audit_logs:229, audit_logs:230, audit_logs:231, audit_logs:239, audit_logs:240, audit_logs:241, audit_logs:242, audit_logs:243, audit_logs:244, audit_logs:245, audit_logs:246, audit_logs:247, audit_logs:248, audit_logs:249, audit_logs:157, audit_logs:158, audit_logs:159, audit_logs:160 |
| Positive test | Executed isolated mutation/projection assertions: AdministratorTwoFactorTest::test_enrollment_requires_current_password_and_stays_pending_until_confirmation; AdministratorTwoFactorTest::test_enrollment_confirmation_and_security_changes_are_temporarily_throttled; production HTTP assertions 2. |
| Negative/edge test | Executed isolated edge/security assertions: AdministratorTwoFactorTest::test_password_change_invalidates_pending_enrollment_confirmation; AdministratorTwoFactorTest::test_wrong_outside_window_and_replayed_totp_do_not_authenticate |
| Browser/UI evidence | qa-staff-renewed-session (2026-10-07T02:54:42.788Z); final-qa-super-mfa (2026-10-07T03:47:40.062Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Actual enrolled QA Super password+fresh TOTP challenges pass. New enrollment is isolated-test coverage; no live authenticator credential was changed. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Administration/Services/AdministratorTwoFactorService.php](app/Domains/Administration/Services/AdministratorTwoFactorService.php)<br>[app/Http/Controllers/Admin/TwoFactorSecurityController.php](app/Http/Controllers/Admin/TwoFactorSecurityController.php) |

### SA-016 — Strong recovery-code regeneration, authenticator replacement and disable

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Account Security |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Account Security. Strong recovery-code regeneration, authenticator replacement and disable. Password plus fresh TOTP or unused recovery authorizes enabled-factor actions; replayed step/code fails under lock. |
| Business rules | Password plus fresh TOTP or unused recovery authorizes enabled-factor actions; replayed step/code fails under lock. Replacement keeps old authenticator until confirmation; regeneration invalidates old codes. Emergency administrator:recover-two-factor is interactive trusted-server workflow, not HTTP bypass or automatic support action. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-015](#sa-015), [SA-013](#sa-013) |
| QA scenarios | Q02 |
| Actual fixture/reference | administrators:6, administrators:7, administrators:8, administrators:9, entity_translation_revisions:11, entity_translation_revisions:12, entity_translation_revisions:13, entity_translation_revisions:18, entity_translation_revisions:19, entity_translation_revisions:20, entity_translation_revisions:21, entity_translation_revisions:22, audit_logs:161, audit_logs:162, audit_logs:163, audit_logs:164, audit_logs:165, audit_logs:166, audit_logs:167, audit_logs:168, audit_logs:169, audit_logs:170, audit_logs:171, audit_logs:172, audit_logs:173, audit_logs:174, audit_logs:175, audit_logs:176, audit_logs:177, audit_logs:178, audit_logs:179, audit_logs:180, audit_logs:181, audit_logs:182, audit_logs:183, audit_logs:184, audit_logs:185, audit_logs:186, audit_logs:187, audit_logs:188, audit_logs:189, audit_logs:190, audit_logs:191, audit_logs:192, audit_logs:193, audit_logs:194, audit_logs:195, audit_logs:197, audit_logs:198, audit_logs:199, audit_logs:200, audit_logs:201, audit_logs:202, audit_logs:203, audit_logs:204, audit_logs:205, audit_logs:206, audit_logs:207, audit_logs:208, audit_logs:209, audit_logs:210, audit_logs:211, audit_logs:212, audit_logs:213, audit_logs:214, audit_logs:215, audit_logs:216, audit_logs:217, audit_logs:218, audit_logs:219, audit_logs:220, audit_logs:221, audit_logs:222, audit_logs:223, audit_logs:224, audit_logs:225, audit_logs:226, audit_logs:227, audit_logs:228, audit_logs:229, audit_logs:230, audit_logs:231, audit_logs:239, audit_logs:240, audit_logs:241, audit_logs:242, audit_logs:243, audit_logs:244, audit_logs:245, audit_logs:246, audit_logs:247, audit_logs:248, audit_logs:249, audit_logs:157, audit_logs:158, audit_logs:159, audit_logs:160 |
| Positive test | Executed isolated mutation/projection assertions: MariaDbConcurrencyVerificationTest::test_two_parallel_uses_of_one_recovery_code_have_one_winner; AdministratorTwoFactorTest::test_replacement_keeps_original_factor_active_until_new_setup_is_confirmed; production HTTP assertions 2. |
| Negative/edge test | Executed isolated edge/security assertions: AdministratorTwoFactorTest::test_recovery_code_is_consumed_once_and_replay_cannot_authenticate; AdministratorTwoFactorTest::test_regeneration_requires_password_and_factor_and_invalidates_all_old_codes |
| Browser/UI evidence | No separate browser mutation certified. Current source/controller behavior is exercised by the named isolated cases; production fixture facts are inspected where available. |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Regeneration/replacement/disable and one-use recovery races pass isolated tests. Existing owner/QA authenticators and recovery material are preserved. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Administration/Services/AdministratorTwoFactorService.php](app/Domains/Administration/Services/AdministratorTwoFactorService.php)<br>[app/Http/Controllers/Admin/TwoFactorSecurityController.php](app/Http/Controllers/Admin/TwoFactorSecurityController.php) |

### SA-017 — Create/update/soft-delete staff accounts and roles

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Staff management |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Staff management. Create/update/soft-delete staff accounts and roles. Role keys super_admin/admin/assistant retain authorization. |
| Business rules | Role keys super_admin/admin/assistant retain authorization. Self-delete and deleting/demoting the last available Super Admin refuse; actor/security access is rechecked. Staff secrets are excluded from ordinary serialization/export; no bulk owner renaming. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-013](#sa-013), [SA-018](#sa-018), [SA-015](#sa-015) |
| QA scenarios | Q02 |
| Actual fixture/reference | administrators:6, administrators:7, administrators:8, administrators:9, entity_translation_revisions:11, entity_translation_revisions:12, entity_translation_revisions:13, entity_translation_revisions:18, entity_translation_revisions:19, entity_translation_revisions:20, entity_translation_revisions:21, entity_translation_revisions:22, audit_logs:161, audit_logs:162, audit_logs:163, audit_logs:164, audit_logs:165, audit_logs:166, audit_logs:167, audit_logs:168, audit_logs:169, audit_logs:170, audit_logs:171, audit_logs:172, audit_logs:173, audit_logs:174, audit_logs:175, audit_logs:176, audit_logs:177, audit_logs:178, audit_logs:179, audit_logs:180, audit_logs:181, audit_logs:182, audit_logs:183, audit_logs:184, audit_logs:185, audit_logs:186, audit_logs:187, audit_logs:188, audit_logs:189, audit_logs:190, audit_logs:191, audit_logs:192, audit_logs:193, audit_logs:194, audit_logs:195, audit_logs:197, audit_logs:198, audit_logs:199, audit_logs:200, audit_logs:201, audit_logs:202, audit_logs:203, audit_logs:204, audit_logs:205, audit_logs:206, audit_logs:207, audit_logs:208, audit_logs:209, audit_logs:210, audit_logs:211, audit_logs:212, audit_logs:213, audit_logs:214, audit_logs:215, audit_logs:216, audit_logs:217, audit_logs:218, audit_logs:219, audit_logs:220, audit_logs:221, audit_logs:222, audit_logs:223, audit_logs:224, audit_logs:225, audit_logs:226, audit_logs:227, audit_logs:228, audit_logs:229, audit_logs:230, audit_logs:231, audit_logs:239, audit_logs:240, audit_logs:241, audit_logs:242, audit_logs:243, audit_logs:244, audit_logs:245, audit_logs:246, audit_logs:247, audit_logs:248, audit_logs:249, audit_logs:157, audit_logs:158, audit_logs:159, audit_logs:160 |
| Positive test | Executed isolated mutation/projection assertions: AdminManagementAndCmsTest::test_admin_create_artisan_command; AdminManagementAndCmsTest::test_administrator_crud_lifecycle_and_role_protection; production HTTP assertions 2. |
| Negative/edge test | Executed isolated edge/security assertions: AdministratorTwoFactorTest::test_other_staff_roles_cannot_access_any_security_operation with data set #0; AdministratorTwoFactorTest::test_other_staff_roles_cannot_access_any_security_operation with data set #1 |
| Browser/UI evidence | staff-page-staff-administrators (2026-10-06T22:50:51.618Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Actual role-separated accounts and denied management routes are checked; create/update/delete authorization is isolated-test coverage. No additional privileged account or credential was created. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/AdministratorController.php](app/Http/Controllers/Admin/AdministratorController.php) |

### SA-018 — Suspend and restore Student or staff access with reason

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Access management |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Access management. Suspend and restore Student or staff access with reason. Only current active Super Admin; cannot suspend self or last available Super Admin. |
| Business rules | Only current active Super Admin; cannot suspend self or last available Super Admin. Suspension is distinct from operational inactive/archive and anonymization, and both login/current guard recheck it. Restore removes current suspension fields without erasing history. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-017](#sa-017), [SA-020](#sa-020), [STU-001](#stu-001), [SA-025](#sa-025) |
| QA scenarios | Q02 |
| Actual fixture/reference | administrators:6, administrators:7, administrators:8, administrators:9, entity_translation_revisions:11, entity_translation_revisions:12, entity_translation_revisions:13, entity_translation_revisions:18, entity_translation_revisions:19, entity_translation_revisions:20, entity_translation_revisions:21, entity_translation_revisions:22, audit_logs:196, audit_logs:161, audit_logs:162, audit_logs:163, audit_logs:164, audit_logs:165, audit_logs:166, audit_logs:167, audit_logs:168, audit_logs:169, audit_logs:170, audit_logs:171, audit_logs:172, audit_logs:173, audit_logs:174, audit_logs:175, audit_logs:176, audit_logs:177, audit_logs:178, audit_logs:179, audit_logs:180, audit_logs:181, audit_logs:182, audit_logs:183, audit_logs:184, audit_logs:185, audit_logs:186, audit_logs:187, audit_logs:188, audit_logs:189, audit_logs:190, audit_logs:191, audit_logs:192, audit_logs:193, audit_logs:194, audit_logs:195, audit_logs:197, audit_logs:198, audit_logs:199, audit_logs:200, audit_logs:201, audit_logs:202, audit_logs:203, audit_logs:204, audit_logs:205, audit_logs:206, audit_logs:207, audit_logs:208, audit_logs:209, audit_logs:210, audit_logs:211, audit_logs:212, audit_logs:213, audit_logs:214, audit_logs:215, audit_logs:216, audit_logs:217, audit_logs:218, audit_logs:219, audit_logs:220, audit_logs:221, audit_logs:222, audit_logs:223, audit_logs:224, audit_logs:225, audit_logs:226, audit_logs:227, audit_logs:228, audit_logs:229, audit_logs:230, audit_logs:231, audit_logs:239, audit_logs:240, audit_logs:241, audit_logs:242, audit_logs:243, audit_logs:244, audit_logs:245, audit_logs:246, audit_logs:247, audit_logs:248, audit_logs:249, audit_logs:157, audit_logs:158, audit_logs:159, audit_logs:160 |
| Positive test | Executed isolated mutation/projection assertions: AdministratorAuthorizationTest::test_assistants_can_access_assigned_read_only_pages_but_not_privileged_actions_or_draft_previews; AdministratorAuthorizationTest::test_admins_can_manage_business_content_but_not_owner_only_system_access |
| Negative/edge test | Executed isolated edge/security assertions: AdministratorTwoFactorTest::test_other_staff_roles_cannot_access_any_security_operation with data set #0; AdministratorTwoFactorTest::test_other_staff_roles_cannot_access_any_security_operation with data set #1 |
| Browser/UI evidence | staff-page-staff-administrators (2026-10-06T22:50:51.618Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Suspension/restoration/security branches are isolated tests; genuine and prepared QA access states are retained. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/AccountSuspensionController.php](app/Http/Controllers/Admin/AccountSuspensionController.php) |

### SA-019 — Canonical roster, filtered overview, detail and CSV/XLSX

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Student Records |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Student Records. Canonical roster, filtered overview, detail and CSV/XLSX. Default operational active scope; explicit inactive/archived/all retain history. |
| Business rules | Default operational active scope; explicit inactive/archived/all retain history. Normalized name/email/international-phone plus verified-secondary email search. Exports reuse exact screen filters and typed available/history concepts, not an undifferentiated credit total. No standalone Student-create UI route is exposed; public booking provisions identities. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-020](#sa-020), [SA-025](#sa-025), [SA-027](#sa-027), [SA-009](#sa-009), [SA-010](#sa-010) |
| QA scenarios | Q01 |
| Actual fixture/reference | students:7, students:8, students:9, students:10, students:11, students:12, student_packages:11, student_packages:12, student_packages:13, student_packages:14, student_packages:15, student_packages:16, student_packages:17, student_bins:3, staff_saved_views:1, staff_saved_views:2, staff_saved_views:3, audit_logs:196 |
| Positive test | Executed isolated mutation/projection assertions: FilterAwareExportsTest::test_every_dataset_has_one_filter_scope_for_screen_csv_xlsx_and_clear with data set #0; FilterAwareExportsTest::test_every_dataset_has_one_filter_scope_for_screen_csv_xlsx_and_clear with data set #1; production HTTP assertions 1. |
| Negative/edge test | Executed isolated edge/security assertions: StaffOperationsProductivityTest::test_saved_views_apply_allowlisted_filters_and_cannot_be_accessed_across_users with data set #0; StaffOperationsProductivityTest::test_saved_views_apply_allowlisted_filters_and_cannot_be_accessed_across_users with data set #1 |
| Browser/UI evidence | staff-page-student-records (2026-10-06T22:49:32.015Z); roster-filter-finance (2026-10-06T22:51:46.783Z); staff-finder (2026-10-06T22:56:06.070Z); booking-23-cancelled (2026-10-07T03:36:17.509Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | PRELMS-004 |
| Fix commit SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Deployed fix SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Retest | Explicit completed-response setPrivate plus no-store; FilterAwareExports16/368 and actual CSV/XLSX private/no-store semantic parity pass. |
| Authoritative current source | [app/Domains/Students/Services/StudentRecordsQuery.php](app/Domains/Students/Services/StudentRecordsQuery.php)<br>[app/Http/Controllers/Admin/StudentController.php](app/Http/Controllers/Admin/StudentController.php) |

### SA-020 — Update/verify recorded identity and contextual contact actions

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Student identity |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Student identity. Update/verify recorded identity and contextual contact actions. Canonical normalization and ownership conflicts protect existing Student identity; incomplete/legacy-unverified profiles require staff review. |
| Business rules | Canonical normalization and ownership conflicts protect existing Student identity; incomplete/legacy-unverified profiles require staff review. Contextual copy/book/Cashier/teaching/notes links follow current roles. Fields do not globally hardcode Abdallah or make unverified secondary addresses trusted. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-019](#sa-019), [STU-001](#stu-001), [SA-083](#sa-083), [SA-040](#sa-040) |
| QA scenarios | Q01 |
| Actual fixture/reference | students:7, students:8, students:9, students:10, students:11, students:12, student_bins:3, audit_logs:196 |
| Positive test | Executed isolated mutation/projection assertions: StaffOperationsProductivityTest::test_assistant_reads_alerts_without_management_or_billing_actions; StaffOperationsProductivityTest::test_contextual_booking_prefills_the_existing_manual_flow_and_preserves_permissions |
| Negative/edge test | Executed isolated edge/security assertions: StaffOperationsProductivityTest::test_saved_views_apply_allowlisted_filters_and_cannot_be_accessed_across_users with data set #0; StaffOperationsProductivityTest::test_saved_views_apply_allowlisted_filters_and_cannot_be_accessed_across_users with data set #1 |
| Browser/UI evidence | staff-page-student-records (2026-10-06T22:49:32.015Z); booking-23-cancelled (2026-10-07T03:36:17.509Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/StudentController.php](app/Http/Controllers/Admin/StudentController.php)<br>[resources/views/components/student-quick-actions.blade.php](resources/views/components/student-quick-actions.blade.php) |

### SA-021 — Contact directory, acquisition history, lead inquiry view and staff notes

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Contacts/leads |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Contacts/leads. Contact directory, acquisition history, lead inquiry view and staff notes. Contacts remain distinct from authenticated Students. |
| Business rules | Contacts remain distinct from authenticated Students. Independent public leads survive Student-specific privacy/reset boundaries; current detail resolves canonical relationships. Search/export do not expose administrator credentials or raw IP reporting identity. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [PUB-015](#pub-015), [PUB-011](#pub-011), [SA-022](#sa-022), [SA-010](#sa-010) |
| QA scenarios | Q01, Q07 |
| Actual fixture/reference | students:7, students:8, students:9, students:10, students:11, students:12, resource_categories:3, resources:2, resources:3, student_bins:3, contacts:11, contacts:12, contacts:14, contacts:15, contacts:13, contacts:16, resource_translations:2, resource_translations:3, category_translations:3, resource_downloads:2, resource_downloads:3, audit_logs:196, resource_requests:4, resource_requests:5, content_revisions:9 |
| Positive test | Executed isolated mutation/projection assertions: AdminOperationsTest::test_admin_booking_detail_and_notes_update; ContactIdentityTest::test_contact_email_is_normalized_to_lowercase_and_trimmed |
| Negative/edge test | Executed isolated edge/security assertions: AdminOperationsTest::test_admin_contact_duplicate_detection_and_merge; AdminOperationsTest::test_unauthenticated_user_redirected_to_admin_login |
| Browser/UI evidence | staff-page-students-contacts (2026-10-06T22:49:47.531Z); staff-page-leads-resource-inquiries- (2026-10-06T22:49:48.319Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/ContactController.php](app/Http/Controllers/Admin/ContactController.php) |

### SA-022 — Review and explicitly merge suspected duplicate Contacts

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Contact identity |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Contact identity. Review and explicitly merge suspected duplicate Contacts. Explicit two different existing IDs required; review candidates are not automatic identity proof. |
| Business rules | Explicit two different existing IDs required; review candidates are not automatic identity proof. ContactService reassigns references under locks and preserves history; does not convert public leads into authenticated Students by guesswork. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-021](#sa-021), [SA-083](#sa-083) |
| QA scenarios | Q01, Q07 |
| Actual fixture/reference | students:7, students:8, students:9, students:10, students:11, students:12, resource_categories:3, resources:2, resources:3, student_bins:3, contacts:11, contacts:12, contacts:14, contacts:15, contacts:13, contacts:16, resource_translations:2, resource_translations:3, category_translations:3, resource_downloads:2, resource_downloads:3, audit_logs:196, resource_requests:4, resource_requests:5, content_revisions:9 |
| Positive test | Executed isolated mutation/projection assertions: ContactIdentityTest::test_contact_merge_locks_calendar_contacts_students_then_bookings; ContactIdentityTest::test_contact_merge_follows_pointer_to_canonical_record |
| Negative/edge test | Executed isolated edge/security assertions: MariaDbConcurrencyVerificationTest::test_concurrent_booking_collision_is_rejected_authoritatively; MariaDbConcurrencyVerificationTest::test_concurrent_meeting_assignment_cannot_share_an_overlapping_room |
| Browser/UI evidence | staff-page-students-contacts (2026-10-06T22:49:47.531Z); booking-23-cancelled (2026-10-07T03:36:17.509Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Duplicate Contact review and merge races are isolated tests; directory/provenance is inspected. No production Contact merge was executed. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Contacts/Services/ContactService.php](app/Domains/Contacts/Services/ContactService.php)<br>[app/Http/Controllers/Admin/ContactController.php](app/Http/Controllers/Admin/ContactController.php) |

### SA-023 — Explicit canonical Student merge preserving facts/provenance

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Student identity |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Student identity. Explicit canonical Student merge preserving facts/provenance. Two distinct valid canonical IDs; calendar/Student/Booking locks detect changed graph. |
| Business rules | Two distinct valid canonical IDs; calendar/Student/Booking locks detect changed graph. Stable booking/purchase/material IDs, amounts, ledger quantities/timestamps and history remain; typed provenance is preserved. Recent entries to merged records are omitted, not copied with private pages. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-019](#sa-019), [SA-020](#sa-020), [SA-022](#sa-022), [SA-038](#sa-038) |
| QA scenarios | Q01 |
| Actual fixture/reference | students:7, students:8, students:9, students:10, students:11, students:12, student_bins:3, staff_tasks:1, student_operational_alerts:1, staff_tasks:2, student_operational_alerts:2, audit_logs:196 |
| Positive test | Executed isolated mutation/projection assertions: TypedEntitlementsTest::test_merge_preserves_typed_ownership_and_booking_provenance; StaffOperationsProductivityTest::test_merge_preserves_operations_and_privacy_removes_student_details_from_tasks_alerts_and_history |
| Negative/edge test | Executed isolated edge/security assertions: StudentMergeAndPrivacyTest::test_admin_cannot_merge_or_anonymize_students; StudentMergeAndPrivacyTest::test_merge_invalidates_the_secondary_students_active_portal_session |
| Browser/UI evidence | No separate browser mutation certified. Current source/controller behavior is exercised by the named isolated cases; production fixture facts are inspected where available. |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Canonical Student merge/typed provenance/teaching ownership regressions pass isolated tests. Six QA identities remain separate for owner review. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/StudentMergeService.php](app/Domains/Students/Services/StudentMergeService.php)<br>[app/Http/Controllers/Admin/StudentController.php](app/Http/Controllers/Admin/StudentController.php) |

### SA-024 — Student privacy anonymization and owned-content redaction

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Privacy |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Privacy. Student privacy anonymization and owned-content redaction. Retains structural Booking/financial history rather than reset/restart. |
| Business rules | Retains structural Booking/financial history rather than reset/restart. Shared independent Contacts are protected; current graph changes refuse. Teaching responses/preparation/corrections/feedback/notes/forms are redacted, sharing revoked; Student notifications removed. This is not reversible identity editing. Structural Booking history/status is retained with sensitive notes redacted; anonymization itself neither cancels bookings nor restores credits nor changes cash amounts. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-023](#sa-023), [SA-038](#sa-038), [SA-052](#sa-052), [SA-083](#sa-083) |
| QA scenarios | Q01 |
| Actual fixture/reference | students:7, students:8, students:9, students:10, students:11, students:12, student_bins:3, staff_tasks:1, student_operational_alerts:1, staff_tasks:2, student_operational_alerts:2, audit_logs:196, audit_logs:161, audit_logs:162, audit_logs:163, audit_logs:164, audit_logs:165, audit_logs:166, audit_logs:167, audit_logs:168, audit_logs:169, audit_logs:170, audit_logs:171, audit_logs:172, audit_logs:175, audit_logs:176, audit_logs:177, audit_logs:178, audit_logs:179, audit_logs:180, audit_logs:181, audit_logs:182, audit_logs:183, audit_logs:184, audit_logs:185, audit_logs:186, audit_logs:187, audit_logs:188, audit_logs:189, audit_logs:190, audit_logs:191, audit_logs:192, audit_logs:193, audit_logs:194, audit_logs:195, audit_logs:197, audit_logs:198, audit_logs:199, audit_logs:200, audit_logs:201, audit_logs:202, audit_logs:203, audit_logs:204, audit_logs:205, audit_logs:206, audit_logs:207, audit_logs:208, audit_logs:209, audit_logs:210, audit_logs:211, audit_logs:212, audit_logs:213, audit_logs:214, audit_logs:215, audit_logs:216, audit_logs:217, audit_logs:218, audit_logs:219, audit_logs:220, audit_logs:221, audit_logs:222, audit_logs:223, audit_logs:224, audit_logs:225, audit_logs:226, audit_logs:227, audit_logs:228, audit_logs:229, audit_logs:230, audit_logs:231, audit_logs:239, audit_logs:240, audit_logs:241, audit_logs:242, audit_logs:243, audit_logs:244, audit_logs:245, audit_logs:246, audit_logs:247, audit_logs:248, audit_logs:249 |
| Positive test | Executed isolated mutation/projection assertions: StudentTeachingExperienceTest::test_merge_preserves_and_reassigns_all_teaching_history_and_privacy_redacts_content; LessonWorkspaceTest::test_privacy_erases_material_payload_and_private_file_but_preserves_booking_and_resource |
| Negative/edge test | Executed isolated edge/security assertions: LessonWorkspaceTest::test_rejects_unsafe_pdf_content_and_extension with data set "html"; LessonWorkspaceTest::test_rejects_unsafe_pdf_content_and_extension with data set "script suffix" |
| Browser/UI evidence | No separate browser mutation certified. Current source/controller behavior is exercised by the named isolated cases; production fixture facts are inspected where available. |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Privacy redaction/anonymization branches pass isolated tests; no preserved production or QA identity was anonymized. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/StudentPrivacyService.php](app/Domains/Students/Services/StudentPrivacyService.php)<br>[app/Http/Controllers/Admin/StudentController.php](app/Http/Controllers/Admin/StudentController.php) |

### SA-025 — Active/inactive/archive lifecycle with explicit history

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Student operations |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Student operations. Active/inactive/archive lifecycle with explicit history. Archive is not suspension, deletion, automatic lesson cancellation or financial erasure. |
| Business rules | Archive is not suspension, deletion, automatic lesson cancellation or financial erasure. Default daily non-booking queues omit inactive records; booked commitments and explicit history/finance remain. New recurrence generation requires active status. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-019](#sa-019), [SA-004](#sa-004), [SA-018](#sa-018), [SA-048](#sa-048) |
| QA scenarios | Q01 |
| Actual fixture/reference | students:7, students:8, students:9, students:10, students:11, students:12, student_bins:3, audit_logs:196 |
| Positive test | Executed isolated mutation/projection assertions: BusinessLifecycleDataQualityTest::test_archived_waitlist_interest_leaves_daily_queue_but_remains_in_history; BusinessLifecycleDataQualityTest::test_archive_excludes_default_roster_and_followups_but_preserves_booked_lessons_and_finance |
| Negative/edge test | Executed isolated edge/security assertions: BusinessLifecycleDataQualityTest::test_recurring_safe_conflicts_distinguish_notice_collision_and_inactive_lesson; BusinessLifecycleDataQualityTest::test_paused_plans_and_archived_students_cannot_generate_new_recurring_bookings |
| Browser/UI evidence | staff-page-student-records (2026-10-06T22:49:32.015Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/StudentOperationalStatusController.php](app/Http/Controllers/Admin/StudentOperationalStatusController.php)<br>[app/Domains/Students/Services/StudentSchedulingService.php](app/Domains/Students/Services/StudentSchedulingService.php) |

### SA-026 — Student selection, filtered transactions and financial exports

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Cashier |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Cashier. Student selection, filtered transactions and financial exports. Student selector scopes purchase management; displayed transaction totals keep currencies distinct and derive net cash from receipts/refunds. |
| Business rules | Student selector scopes purchase management; displayed transaction totals keep currencies distinct and derive net cash from receipts/refunds. Screen and export use the same filter/query authority, not stored editable balances. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-027](#sa-027), [SA-029](#sa-029), [SA-030](#sa-030), [SA-034](#sa-034) |
| QA scenarios | Q03 |
| Actual fixture/reference | payment_methods:7, student_packages:11, student_package_entitlements:5, session_ledger_entries:19, student_packages:12, student_package_entitlements:6, session_ledger_entries:20, student_packages:13, student_package_entitlements:7, session_ledger_entries:21, student_packages:14, student_package_entitlements:8, session_ledger_entries:22, student_packages:15, student_package_entitlements:9, session_ledger_entries:23, student_packages:16, student_package_entitlements:10, session_ledger_entries:24, student_packages:17, student_package_entitlements:11, session_ledger_entries:25, payment_records:13, payment_records:14, payment_records:15, payment_records:16, payment_refunds:4, payment_records:17, payment_refunds:5, package_installments:1, package_installments:2, package_renewals:1, student_packages:18, student_package_entitlements:12, session_ledger_entries:26, student_packages:19, student_package_entitlements:13, session_ledger_entries:33, payment_records:18, session_ledger_entries:36, payment_refunds:6, session_ledger_entries:37, student_packages:20, student_package_entitlements:14, session_ledger_entries:38 |
| Positive test | Executed isolated mutation/projection assertions: AdminUiBillingPresentationTest::test_history_is_selected_and_filtered with data set #0; AdminUiBillingPresentationTest::test_history_is_selected_and_filtered with data set #1; production HTTP assertions 9. |
| Negative/edge test | Executed isolated edge/security assertions: AdminUiBillingPresentationTest::test_empty_single_default_and_foreign_package_selection; AdminUiBillingPresentationTest::test_foreign_financial_actions_are_rejected with data set #0 |
| Browser/UI evidence | staff-page-cashier-hub (2026-10-06T22:49:33.165Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | PRELMS-004 |
| Fix commit SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Deployed fix SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Retest | Explicit completed-response setPrivate plus no-store; FilterAwareExports16/368 and actual CSV/XLSX private/no-store semantic parity pass. |
| Authoritative current source | [app/Http/Controllers/Admin/StudentBillingController.php](app/Http/Controllers/Admin/StudentBillingController.php)<br>[app/Domains/Students/Services/CashierReportService.php](app/Domains/Students/Services/CashierReportService.php) |

### SA-027 — Create canonical preset or classified custom purchases and grants

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Purchases/typed rights |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Purchases/typed rights. Create canonical preset or classified custom purchases and grants. Original price minus discount equals final/net contract price; nonnegative decimal amounts and discount ≤price. |
| Business rules | Original price minus discount equals final/net contract price; nonnegative decimal amounts and discount ≤price. Presets: Diagnostic one_hour×1 $25/14 days; Foundation two_hour×8 $280/75; Fluency two_hour×12 $390/100; maintenance two_hour×1 $48/30; advanced one_hour×1 $28/30. Custom requires explicit active type; preset override notes preserve explicit staff override. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-020](#sa-020), [SA-028](#sa-028), [SA-029](#sa-029), [STU-005](#stu-005) |
| QA scenarios | Q03 |
| Actual fixture/reference | payment_methods:7, student_packages:11, student_package_entitlements:5, session_ledger_entries:19, student_packages:12, student_package_entitlements:6, session_ledger_entries:20, student_packages:13, student_package_entitlements:7, session_ledger_entries:21, student_packages:14, student_package_entitlements:8, session_ledger_entries:22, student_packages:15, student_package_entitlements:9, session_ledger_entries:23, student_packages:16, student_package_entitlements:10, session_ledger_entries:24, student_packages:17, student_package_entitlements:11, session_ledger_entries:25, payment_records:13, payment_records:14, payment_records:15, payment_records:16, payment_refunds:4, payment_records:17, payment_refunds:5, package_installments:1, package_installments:2, package_renewals:1, student_packages:18, student_package_entitlements:12, session_ledger_entries:26, student_packages:19, student_package_entitlements:13, session_ledger_entries:33, payment_records:18, session_ledger_entries:36, payment_refunds:6, session_ledger_entries:37, student_packages:20, student_package_entitlements:14, session_ledger_entries:38 |
| Positive test | Executed isolated mutation/projection assertions: TypedEntitlementsTest::test_offering_filter_matches_all_purchases_and_custom_history; CashierBillingReconciliationTest::test_canonical_product_presets_match_exact_specifications |
| Negative/edge test | Executed isolated edge/security assertions: TypedEntitlementsTest::test_selection_skips_incompatible_expired_cancelled_and_unclassified_history; TypedEntitlementsTest::test_mapping_rejects_unreconciled_grants_without_changing_history |
| Browser/UI evidence | staff-page-cashier-hub (2026-10-06T22:49:33.165Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Prepared classified purchases/grants and live credit consumption/restoration are reconciled. New purchase/grant form mutations are isolated-test coverage; no extra purchase was fabricated. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/StudentLedgerService.php](app/Domains/Students/Services/StudentLedgerService.php)<br>[app/Domains/Students/Models/StudentPackage.php](app/Domains/Students/Models/StudentPackage.php)<br>[app/Http/Controllers/Admin/StudentBillingController.php](app/Http/Controllers/Admin/StudentBillingController.php) |

### SA-028 — Reviewed discount and 48-hour diagnostic-credit eligibility

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Discounts |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Discounts. Reviewed discount and 48-hour diagnostic-credit eligibility. Eligible diagnostic must have completed consumed lesson, genuine USD settlement/net paid ≥$25 and qualifying settlement within 48 hours. |
| Business rules | Eligible diagnostic must have completed consumed lesson, genuine USD settlement/net paid ≥$25 and qualifying settlement within 48 hours. Prior Foundation/Fluency $25 claim blocks reuse; recent refunds can invalidate/reopen settlement evaluation. Eligible credit/explicit discount is bounded by price. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-027](#sa-027), [SA-029](#sa-029), [SA-030](#sa-030), [SA-042](#sa-042) |
| QA scenarios | Q03 |
| Actual fixture/reference | payment_methods:7, student_packages:11, student_package_entitlements:5, session_ledger_entries:19, student_packages:12, student_package_entitlements:6, session_ledger_entries:20, student_packages:13, student_package_entitlements:7, session_ledger_entries:21, student_packages:14, student_package_entitlements:8, session_ledger_entries:22, student_packages:15, student_package_entitlements:9, session_ledger_entries:23, student_packages:16, student_package_entitlements:10, session_ledger_entries:24, student_packages:17, student_package_entitlements:11, session_ledger_entries:25, payment_records:13, payment_records:14, payment_records:15, payment_records:16, payment_refunds:4, payment_records:17, payment_refunds:5, package_installments:1, package_installments:2, package_renewals:1, student_packages:18, student_package_entitlements:12, session_ledger_entries:26, student_packages:19, student_package_entitlements:13, session_ledger_entries:33, payment_records:18, session_ledger_entries:36, payment_refunds:6, session_ledger_entries:37, student_packages:20, student_package_entitlements:14, session_ledger_entries:38 |
| Positive test | Executed isolated mutation/projection assertions: CashierBillingReconciliationTest::test_48_hour_diagnostic_credit_automation_logic; CashierBillingReconciliationTest::test_diagnostic_credit_requires_a_completed_and_fully_settled_diagnostic |
| Negative/edge test | Executed isolated edge/security assertions: CashierBillingReconciliationTest::test_refund_cannot_forfeit_more_credits_than_remain; TypedEntitlementsTest::test_two_one_hour_rights_cannot_pay_for_a_two_hour_lesson |
| Browser/UI evidence | staff-page-cashier-hub (2026-10-06T22:49:33.165Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Independent discount calculations and current diagnostic purchase/payment window are checked; redemption/48-hour boundaries are isolated tests. Historical prices/provenance are unchanged. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/StudentLedgerService.php](app/Domains/Students/Services/StudentLedgerService.php)<br>[app/Http/Controllers/Admin/StudentBillingController.php](app/Http/Controllers/Admin/StudentBillingController.php) |

### SA-029 — Record positive manual payments and settlement

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Actual cash |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Actual cash. Record positive manual payments and settlement. Actual manually received money is separate from expected installments/purchase price. |
| Business rules | Actual manually received money is separate from expected installments/purchase price. Payment can overpay; due/overpaid are projected separately. Student/purchase locks/idempotency avoid duplicate receipt; controller replay returns 409 while underlying same-key service reuses existing same-purchase payment. No automatic online charge is made. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-027](#sa-027), [SA-035](#sa-035), [SA-033](#sa-033), [SA-099](#sa-099) |
| QA scenarios | Q03 |
| Actual fixture/reference | payment_methods:7, student_packages:11, student_package_entitlements:5, session_ledger_entries:19, student_packages:12, student_package_entitlements:6, session_ledger_entries:20, student_packages:13, student_package_entitlements:7, session_ledger_entries:21, student_packages:14, student_package_entitlements:8, session_ledger_entries:22, student_packages:15, student_package_entitlements:9, session_ledger_entries:23, student_packages:16, student_package_entitlements:10, session_ledger_entries:24, student_packages:17, student_package_entitlements:11, session_ledger_entries:25, payment_records:13, payment_records:14, payment_records:15, payment_records:16, payment_refunds:4, payment_records:17, payment_refunds:5, package_installments:1, package_installments:2, package_renewals:1, student_packages:18, student_package_entitlements:12, session_ledger_entries:26, student_packages:19, student_package_entitlements:13, session_ledger_entries:33, payment_records:18, session_ledger_entries:36, payment_refunds:6, session_ledger_entries:37, student_packages:20, student_package_entitlements:14, session_ledger_entries:38 |
| Positive test | Executed isolated mutation/projection assertions: CashierBillingReconciliationTest::test_preset_package_terms_and_expiry_are_server_authoritative_after_settlement; BusinessLifecycleConcurrencyTest::test_replayed_payment_race_records_one_actual_payment_and_satisfies_forecast_once |
| Negative/edge test | Executed isolated edge/security assertions: CashierBillingReconciliationTest::test_refund_cannot_forfeit_more_credits_than_remain; CashierBillingReconciliationTest::test_cashier_rejects_duplicate_client_uuids_with_http_409_without_duplicate_ledger_rows |
| Browser/UI evidence | staff-page-cashier-hub (2026-10-06T22:49:33.165Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Actual persisted payments and rendered receipts reconcile. Payment posting/settlement/idempotency races are isolated tests; no real funds or additional manual payment record was created. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/StudentLedgerService.php](app/Domains/Students/Services/StudentLedgerService.php)<br>[app/Http/Controllers/Admin/StudentBillingController.php](app/Http/Controllers/Admin/StudentBillingController.php) |

### SA-030 — Record bounded refunds with optional explicit typed forfeiture

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Actual cash refunds |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Actual cash refunds. Record bounded refunds with optional explicit typed forfeiture. Positive refund cannot exceed original unrefunded payment or package refundable net paid. |
| Business rules | Positive refund cannot exceed original unrefunded payment or package refundable net paid. Refund alone changes money, not rights; optional forfeiture must name allocation and cannot exceed remaining quantity. Replay payload/amount/reason/type mismatch rejects; historic receipt/grant rows stay unchanged. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-029](#sa-029), [SA-031](#sa-031), [SA-034](#sa-034), [SA-035](#sa-035) |
| QA scenarios | Q03 |
| Actual fixture/reference | payment_methods:7, student_packages:11, student_package_entitlements:5, session_ledger_entries:19, student_packages:12, student_package_entitlements:6, session_ledger_entries:20, student_packages:13, student_package_entitlements:7, session_ledger_entries:21, student_packages:14, student_package_entitlements:8, session_ledger_entries:22, student_packages:15, student_package_entitlements:9, session_ledger_entries:23, student_packages:16, student_package_entitlements:10, session_ledger_entries:24, student_packages:17, student_package_entitlements:11, session_ledger_entries:25, payment_records:13, payment_records:14, payment_records:15, payment_records:16, payment_refunds:4, payment_records:17, payment_refunds:5, package_installments:1, package_installments:2, package_renewals:1, student_packages:18, student_package_entitlements:12, session_ledger_entries:26, student_packages:19, student_package_entitlements:13, session_ledger_entries:33, payment_records:18, session_ledger_entries:36, payment_refunds:6, session_ledger_entries:37, student_packages:20, student_package_entitlements:14, session_ledger_entries:38 |
| Positive test | Executed isolated mutation/projection assertions: TypedEntitlementsTest::test_refund_forfeiture_is_typed_and_money_only_refunds_leave_rights_unchanged; CashierBillingReconciliationTest::test_refund_lock_and_credit_forfeiture |
| Negative/edge test | Executed isolated edge/security assertions: CashierBillingReconciliationTest::test_refund_cannot_forfeit_more_credits_than_remain; CashierBillingReconciliationTest::test_cashier_rejects_duplicate_client_uuids_with_http_409_without_duplicate_ledger_rows |
| Browser/UI evidence | staff-page-cashier-hub (2026-10-06T22:49:33.165Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Persisted bounded refunds/explicit forfeiture are independently reconciled. Refund posting/excess/stale/concurrency branches are isolated tests. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/StudentLedgerService.php](app/Domains/Students/Services/StudentLedgerService.php)<br>[app/Http/Controllers/Admin/StudentBillingController.php](app/Http/Controllers/Admin/StudentBillingController.php) |

### SA-031 — Explicit typed courtesy adjustments and historical balance projection

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Typed rights |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Typed rights. Explicit typed courtesy adjustments and historical balance projection. Type and allocation are explicit; change cannot make remaining rights negative. |
| Business rules | Type and allocation are explicit; change cannot make remaining rights negative. No one_hour↔two_hour conversion or minutes-based inference; idempotent details/ownership/reason/type must match. Consumed/restored/forfeited and unavailable history remain separately projected. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-027](#sa-027), [SA-030](#sa-030), [STU-005](#stu-005) |
| QA scenarios | Q03 |
| Actual fixture/reference | payment_methods:7, student_packages:11, student_package_entitlements:5, session_ledger_entries:19, student_packages:12, student_package_entitlements:6, session_ledger_entries:20, student_packages:13, student_package_entitlements:7, session_ledger_entries:21, student_packages:14, student_package_entitlements:8, session_ledger_entries:22, student_packages:15, student_package_entitlements:9, session_ledger_entries:23, student_packages:16, student_package_entitlements:10, session_ledger_entries:24, student_packages:17, student_package_entitlements:11, session_ledger_entries:25, payment_records:13, payment_records:14, payment_records:15, payment_records:16, payment_refunds:4, payment_records:17, payment_refunds:5, package_installments:1, package_installments:2, package_renewals:1, student_packages:18, student_package_entitlements:12, session_ledger_entries:26, student_packages:19, student_package_entitlements:13, session_ledger_entries:33, payment_records:18, session_ledger_entries:36, payment_refunds:6, session_ledger_entries:37, student_packages:20, student_package_entitlements:14, session_ledger_entries:38 |
| Positive test | Executed isolated mutation/projection assertions: TypedEntitlementsTest::test_courtesy_adjustments_are_explicit_bounded_and_not_cross_package; StudentLedgerTest::test_package_terms_and_credit_balance_are_derived_from_append_only_ledger |
| Negative/edge test | Executed isolated edge/security assertions: TypedEntitlementsTest::test_negative_courtesy_cannot_spend_another_type; TypedEntitlementsTest::test_two_one_hour_rights_cannot_pay_for_a_two_hour_lesson |
| Browser/UI evidence | staff-page-cashier-hub (2026-10-06T22:49:33.165Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Prepared courtesy and allocation balances remain separate and reconcile. Adjustment mutation/reason/negative-unit protection is isolated-test coverage. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/StudentLedgerService.php](app/Domains/Students/Services/StudentLedgerService.php)<br>[app/Domains/Students/Services/EntitlementService.php](app/Domains/Students/Services/EntitlementService.php)<br>[app/Http/Controllers/Admin/StudentBillingController.php](app/Http/Controllers/Admin/StudentBillingController.php) |

### SA-032 — Extend an existing expiry with reason and stale-date protection

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Purchase validity |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Purchase validity. Extend an existing expiry with reason and stale-date protection. Requires existing expiry, matching previous date, strictly later new date and nonempty reason. |
| Business rules | Requires existing expiry, matching previous date, strictly later new date and nonempty reason. Cannot silently change unlimited/pending-settlement null expiry or shorten validity through this action; concurrent/stale form rejects. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-027](#sa-027), [STU-005](#stu-005) |
| QA scenarios | Q03 |
| Actual fixture/reference | payment_methods:7, student_packages:11, student_package_entitlements:5, session_ledger_entries:19, student_packages:12, student_package_entitlements:6, session_ledger_entries:20, student_packages:13, student_package_entitlements:7, session_ledger_entries:21, student_packages:14, student_package_entitlements:8, session_ledger_entries:22, student_packages:15, student_package_entitlements:9, session_ledger_entries:23, student_packages:16, student_package_entitlements:10, session_ledger_entries:24, student_packages:17, student_package_entitlements:11, session_ledger_entries:25, payment_records:13, payment_records:14, payment_records:15, payment_records:16, payment_refunds:4, payment_records:17, payment_refunds:5, package_installments:1, package_installments:2, package_renewals:1, student_packages:18, student_package_entitlements:12, session_ledger_entries:26, student_packages:19, student_package_entitlements:13, session_ledger_entries:33, payment_records:18, session_ledger_entries:36, payment_refunds:6, session_ledger_entries:37, student_packages:20, student_package_entitlements:14, session_ledger_entries:38 |
| Positive test | Executed isolated mutation/projection assertions: TypedEntitlementsTest::test_portal_preserves_separate_balances_and_each_purchase_expiry; TypedEntitlementsTest::test_historical_null_expiry_requires_reviewed_terms_before_canonical_mapping |
| Negative/edge test | Executed isolated edge/security assertions: TypedEntitlementsTest::test_ambiguous_history_is_never_guessed_and_stale_mapping_is_rejected; BusinessOperationsControlsTest::test_only_super_admin_can_suspend_and_self_suspension_is_rejected |
| Browser/UI evidence | staff-page-cashier-hub (2026-10-06T22:49:33.165Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Existing real expiry eligibility is re-evaluated at current time. Extension/stale-date protection is isolated-test coverage; no expiry was extended for acceptance. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/StudentBillingController.php](app/Http/Controllers/Admin/StudentBillingController.php) |

### SA-033 — Immutable full-price installment schedule, FIFO projection and voided history

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Expected payments |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Expected payments. Immutable full-price installment schedule, FIFO projection and voided history. Schedule total must equal final price. |
| Business rules | Schedule total must equal final price. Due-date/sequence projection allocates current net actual payments FIFO; refunds reopen due forecast, overpayment remains money fact. One immutable schedule only, including voided history; no replacement schedule or fabricated PaymentRecord. Key-owner/fingerprint drift refuses. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-027](#sa-027), [SA-029](#sa-029), [SA-030](#sa-030), [SA-034](#sa-034) |
| QA scenarios | Q03 |
| Actual fixture/reference | payment_methods:7, student_packages:11, student_package_entitlements:5, session_ledger_entries:19, student_packages:12, student_package_entitlements:6, session_ledger_entries:20, student_packages:13, student_package_entitlements:7, session_ledger_entries:21, student_packages:14, student_package_entitlements:8, session_ledger_entries:22, student_packages:15, student_package_entitlements:9, session_ledger_entries:23, student_packages:16, student_package_entitlements:10, session_ledger_entries:24, student_packages:17, student_package_entitlements:11, session_ledger_entries:25, payment_records:13, payment_records:14, payment_records:15, payment_records:16, payment_refunds:4, payment_records:17, payment_refunds:5, package_installments:1, package_installments:2, package_renewals:1, student_packages:18, student_package_entitlements:12, session_ledger_entries:26, student_packages:19, student_package_entitlements:13, session_ledger_entries:33, payment_records:18, session_ledger_entries:36, payment_refunds:6, session_ledger_entries:37, student_packages:20, student_package_entitlements:14, session_ledger_entries:38 |
| Positive test | Executed isolated mutation/projection assertions: BusinessLifecycleDataQualityTest::test_archive_filter_is_saved_and_receivables_default_is_truthfully_due; BusinessLifecycleDataQualityTest::test_archived_waitlist_interest_leaves_daily_queue_but_remains_in_history |
| Negative/edge test | Executed isolated edge/security assertions: BusinessLifecycleDataQualityTest::test_installment_totals_and_history_cannot_be_rewritten; BusinessLifecycleDataQualityTest::test_installment_forecasts_reconcile_partial_payment_refunds_and_overpayment |
| Browser/UI evidence | staff-page-receivables-statements (2026-10-06T22:49:34.703Z); staff-purchase-statement (2026-10-06T22:57:27.055Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Actual installment forecast, payment/refund projection and statement are inspected; schedule creation/voiding/concurrency branches are isolated tests. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/InstallmentScheduleService.php](app/Domains/Students/Services/InstallmentScheduleService.php)<br>[app/Http/Controllers/Admin/PackageLifecycleController.php](app/Http/Controllers/Admin/PackageLifecycleController.php) |

### SA-034 — Current balances, overdue installments, partial payments and overpayments

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Receivables |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Receivables. Current balances, overdue installments, partial payments and overpayments. Default Due plus overdue/partial/overpaid/all scopes; due=max(final−net_paid,0), overpaid=max(net_paid−final,0). |
| Business rules | Default Due plus overdue/partial/overpaid/all scopes; due=max(final−net_paid,0), overpaid=max(net_paid−final,0). Never pool currencies or infer a receipt from expected schedule. Preserved inactive/history purchases remain explicitly reviewable; bounded 50-row screen and lazy export readers. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-033](#sa-033), [SA-029](#sa-029), [SA-030](#sa-030), [SA-035](#sa-035) |
| QA scenarios | Q03 |
| Actual fixture/reference | payment_methods:7, student_packages:11, student_package_entitlements:5, session_ledger_entries:19, student_packages:12, student_package_entitlements:6, session_ledger_entries:20, student_packages:13, student_package_entitlements:7, session_ledger_entries:21, student_packages:14, student_package_entitlements:8, session_ledger_entries:22, student_packages:15, student_package_entitlements:9, session_ledger_entries:23, student_packages:16, student_package_entitlements:10, session_ledger_entries:24, student_packages:17, student_package_entitlements:11, session_ledger_entries:25, payment_records:13, payment_records:14, payment_records:15, payment_records:16, payment_refunds:4, payment_records:17, payment_refunds:5, package_installments:1, package_installments:2, package_renewals:1, student_packages:18, student_package_entitlements:12, session_ledger_entries:26, student_packages:19, student_package_entitlements:13, session_ledger_entries:33, payment_records:18, session_ledger_entries:36, payment_refunds:6, session_ledger_entries:37, student_packages:20, student_package_entitlements:14, session_ledger_entries:38 |
| Positive test | Executed isolated mutation/projection assertions: BusinessLifecycleDataQualityTest::test_recurring_generation_is_bounded_per_booking_typed_and_replay_safe; BusinessLifecycleDataQualityTest::test_recurring_never_overdraws_the_last_compatible_credit; production HTTP assertions 10. |
| Negative/edge test | Executed isolated edge/security assertions: BusinessLifecycleDataQualityTest::test_installment_forecasts_reconcile_partial_payment_refunds_and_overpayment; BusinessLifecycleDataQualityTest::test_paused_plans_and_archived_students_cannot_generate_new_recurring_bookings |
| Browser/UI evidence | staff-page-receivables-statements (2026-10-06T22:49:34.703Z); staff-purchase-statement (2026-10-06T22:57:27.055Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/ReceivablesReadModel.php](app/Domains/Students/Services/ReceivablesReadModel.php)<br>[app/Http/Controllers/Admin/ReceivablesController.php](app/Http/Controllers/Admin/ReceivablesController.php) |

### SA-035 — Staff package statements, actual receipts, print and exports

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Financial documents |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Financial documents. Staff package statements, actual receipts, print and exports. Contract price/discount/final amount, net paid/due/overpaid, actual payment/refund and expected installments remain distinct. |
| Business rules | Contract price/discount/final amount, net paid/due/overpaid, actual payment/refund and expected installments remain distinct. Internal IDs are not invented provider references; later method rename does not alter old receipt. Binary exports and views are private/no-store. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-029](#sa-029), [SA-030](#sa-030), [SA-033](#sa-033), [STU-024](#stu-024), [STU-025](#stu-025), [SA-099](#sa-099) |
| QA scenarios | Q03 |
| Actual fixture/reference | payment_methods:7, student_packages:11, student_package_entitlements:5, session_ledger_entries:19, student_packages:12, student_package_entitlements:6, session_ledger_entries:20, student_packages:13, student_package_entitlements:7, session_ledger_entries:21, student_packages:14, student_package_entitlements:8, session_ledger_entries:22, student_packages:15, student_package_entitlements:9, session_ledger_entries:23, student_packages:16, student_package_entitlements:10, session_ledger_entries:24, student_packages:17, student_package_entitlements:11, session_ledger_entries:25, payment_records:13, payment_records:14, payment_records:15, payment_records:16, payment_refunds:4, payment_records:17, payment_refunds:5, package_installments:1, package_installments:2, package_renewals:1, student_packages:18, student_package_entitlements:12, session_ledger_entries:26, student_packages:19, student_package_entitlements:13, session_ledger_entries:33, payment_records:18, session_ledger_entries:36, payment_refunds:6, session_ledger_entries:37, student_packages:20, student_package_entitlements:14, session_ledger_entries:38 |
| Positive test | Executed isolated mutation/projection assertions: BusinessLifecycleDataQualityTest::test_free_recurring_lessons_use_no_package_ledger; BusinessLifecycleDataQualityTest::test_new_xlsx_exports_keep_financial_filters_and_actual_payment_rows; production HTTP assertions 14. |
| Negative/edge test | Executed isolated edge/security assertions: BusinessLifecycleDataQualityTest::test_paused_plans_and_archived_students_cannot_generate_new_recurring_bookings; BusinessLifecycleDataQualityTest::test_plan_idempotency_rejects_changed_terms |
| Browser/UI evidence | staff-page-receivables-statements (2026-10-06T22:49:34.703Z); staff-purchase-statement (2026-10-06T22:57:27.055Z); staff-payment-receipt (2026-10-06T22:57:37.556Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | PRELMS-004 |
| Fix commit SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Deployed fix SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Retest | Explicit completed-response setPrivate plus no-store; FilterAwareExports16/368 and actual CSV/XLSX private/no-store semantic parity pass. |
| Authoritative current source | [app/Http/Controllers/Admin/FinancialStatementController.php](app/Http/Controllers/Admin/FinancialStatementController.php)<br>[app/Domains/Students/Services/FinancialStatementService.php](app/Domains/Students/Services/FinancialStatementService.php) |

### SA-036 — Create a new linked purchase/grant without rewriting previous history

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Renewals |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Renewals. Create a new linked purchase/grant without rewriting previous history. Catalog renewals use current purchase-service rules; classified single-allocation custom repeats old terms/type with optional expiry. |
| Business rules | Catalog renewals use current purchase-service rules; classified single-allocation custom repeats old terms/type with optional expiry. Legacy/unclassified or multi-allocation custom requires explicit classified preset. Old price/ledger/payments/expiry remain unchanged; changed/foreign key replay refuses. No money receipt just for renewal. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-027](#sa-027), [SA-028](#sa-028), [SA-038](#sa-038), [SA-033](#sa-033) |
| QA scenarios | Q03 |
| Actual fixture/reference | payment_methods:7, student_packages:11, student_package_entitlements:5, session_ledger_entries:19, student_packages:12, student_package_entitlements:6, session_ledger_entries:20, student_packages:13, student_package_entitlements:7, session_ledger_entries:21, student_packages:14, student_package_entitlements:8, session_ledger_entries:22, student_packages:15, student_package_entitlements:9, session_ledger_entries:23, student_packages:16, student_package_entitlements:10, session_ledger_entries:24, student_packages:17, student_package_entitlements:11, session_ledger_entries:25, payment_records:13, payment_records:14, payment_records:15, payment_records:16, payment_refunds:4, payment_records:17, payment_refunds:5, package_installments:1, package_installments:2, package_renewals:1, student_packages:18, student_package_entitlements:12, session_ledger_entries:26, student_packages:19, student_package_entitlements:13, session_ledger_entries:33, payment_records:18, session_ledger_entries:36, payment_refunds:6, session_ledger_entries:37, student_packages:20, student_package_entitlements:14, session_ledger_entries:38 |
| Positive test | Executed isolated mutation/projection assertions: BusinessLifecycleDataQualityTest::test_renewal_creates_one_linked_purchase_and_preserves_previous_terms; BusinessLifecycleDataQualityTest::test_receivables_queries_stay_bounded_with_more_purchases_and_forecasts |
| Negative/edge test | Executed isolated edge/security assertions: BusinessLifecycleDataQualityTest::test_installment_totals_and_history_cannot_be_rewritten; BusinessLifecycleDataQualityTest::test_incompatible_and_expired_purchases_never_fund_recurring_lessons |
| Browser/UI evidence | staff-page-receivables-statements (2026-10-06T22:49:34.703Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Prepared renewal points to a new purchase/grant without changing old facts. Renewal creation/idempotency is isolated-test coverage. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/PackageRenewalService.php](app/Domains/Students/Services/PackageRenewalService.php)<br>[app/Http/Controllers/Admin/PackageLifecycleController.php](app/Http/Controllers/Admin/PackageLifecycleController.php) |

### SA-037 — Descriptive cash, dues, typed usage, expiry and renewal/repeat indicators

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Business reporting |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Business reporting. Descriptive cash, dues, typed usage, expiry and renewal/repeat indicators. Receipts/refunds count on actual separate dates; refund-only period can have negative net cash. |
| Business rules | Receipts/refunds count on actual separate dates; refund-only period can have negative net cash. Currencies remain separate. Balances/usage/expiry are labeled current values; renewal share and repeat-lesson share are observed ratios, not forecasts, retention probability or accrual revenue. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-029](#sa-029), [SA-030](#sa-030), [SA-036](#sa-036), [SA-042](#sa-042) |
| QA scenarios | Q03 |
| Actual fixture/reference | payment_methods:7, student_packages:11, student_package_entitlements:5, session_ledger_entries:19, student_packages:12, student_package_entitlements:6, session_ledger_entries:20, student_packages:13, student_package_entitlements:7, session_ledger_entries:21, student_packages:14, student_package_entitlements:8, session_ledger_entries:22, student_packages:15, student_package_entitlements:9, session_ledger_entries:23, student_packages:16, student_package_entitlements:10, session_ledger_entries:24, student_packages:17, student_package_entitlements:11, session_ledger_entries:25, payment_records:13, payment_records:14, payment_records:15, payment_records:16, payment_refunds:4, payment_records:17, payment_refunds:5, package_installments:1, package_installments:2, package_renewals:1, student_packages:18, student_package_entitlements:12, session_ledger_entries:26, student_packages:19, student_package_entitlements:13, session_ledger_entries:33, payment_records:18, session_ledger_entries:36, payment_refunds:6, session_ledger_entries:37, student_packages:20, student_package_entitlements:14, session_ledger_entries:38 |
| Positive test | Executed isolated mutation/projection assertions: BusinessLifecycleDataQualityTest::test_recurring_generation_is_bounded_per_booking_typed_and_replay_safe; BusinessLifecycleDataQualityTest::test_cancellation_restores_once_and_records_separate_typed_policy_decision |
| Negative/edge test | Executed isolated edge/security assertions: BusinessLifecycleDataQualityTest::test_cross_owner_interest_key_and_changed_renewal_terms_are_rejected; BusinessLifecycleDataQualityTest::test_paused_plans_and_archived_students_cannot_generate_new_recurring_bookings |
| Browser/UI evidence | staff-page-business-lifecycle (2026-10-06T22:49:37.694Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Reporting/Services/BusinessLifecycleReport.php](app/Domains/Reporting/Services/BusinessLifecycleReport.php)<br>[app/Http/Controllers/Admin/BusinessLifecycleReportController.php](app/Http/Controllers/Admin/BusinessLifecycleReportController.php) |

### SA-038 — Read-only billing integrity and typed provenance diagnostics

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Reconciliation |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Reconciliation. Read-only billing integrity and typed provenance diagnostics. Reports grant/negative/ownership/payment/refund/provenance inconsistencies against authoritative summary, retaining underlying facts. |
| Business rules | Reports grant/negative/ownership/payment/refund/provenance inconsistencies against authoritative summary, retaining underlying facts. Empty production is honestly No Transactions/UNINITIALIZED rather than proof of populated balanced finances. Classification/repair requires separately reviewed action; no guessed type conversion. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-027](#sa-027), [SA-029](#sa-029), [SA-030](#sa-030), [SA-031](#sa-031), [SA-098](#sa-098) |
| QA scenarios | Q03 |
| Actual fixture/reference | payment_methods:7, student_packages:11, student_package_entitlements:5, session_ledger_entries:19, student_packages:12, student_package_entitlements:6, session_ledger_entries:20, student_packages:13, student_package_entitlements:7, session_ledger_entries:21, student_packages:14, student_package_entitlements:8, session_ledger_entries:22, student_packages:15, student_package_entitlements:9, session_ledger_entries:23, student_packages:16, student_package_entitlements:10, session_ledger_entries:24, student_packages:17, student_package_entitlements:11, session_ledger_entries:25, payment_records:13, payment_records:14, payment_records:15, payment_records:16, payment_refunds:4, payment_records:17, payment_refunds:5, package_installments:1, package_installments:2, package_renewals:1, student_packages:18, student_package_entitlements:12, session_ledger_entries:26, session_ledger_entries:27, session_ledger_entries:28, session_ledger_entries:29, session_ledger_entries:30, session_ledger_entries:31, session_ledger_entries:32, session_ledger_entries:34, student_packages:19, student_package_entitlements:13, session_ledger_entries:33, payment_records:18, recurring_lesson_plans:1, session_ledger_entries:35, session_ledger_entries:36, payment_refunds:6, session_ledger_entries:37, session_types:6, student_packages:20, student_package_entitlements:14, session_ledger_entries:38 |
| Positive test | Executed isolated mutation/projection assertions: TypedEntitlementsTest::test_refund_forfeiture_is_typed_and_money_only_refunds_leave_rights_unchanged; TypedEntitlementsTest::test_merge_preserves_typed_ownership_and_booking_provenance |
| Negative/edge test | Executed isolated edge/security assertions: CashierBillingReconciliationTest::test_refund_cannot_forfeit_more_credits_than_remain; CashierBillingReconciliationTest::test_cashier_rejects_duplicate_client_uuids_with_http_409_without_duplicate_ledger_rows |
| Browser/UI evidence | staff-page-billing-reconcile (2026-10-06T22:49:33.902Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/BillingReconciliationService.php](app/Domains/Students/Services/BillingReconciliationService.php)<br>[app/Http/Controllers/Admin/StudentBillingController.php](app/Http/Controllers/Admin/StudentBillingController.php) |

### SA-039 — Business-time booking list, day/week/month calendar, detail and notes

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Bookings/calendar |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Bookings/calendar. Business-time booking list, day/week/month calendar, detail and notes. Confirmed/completed/cancelled/no-show stay distinct; list and calendar partitions have deliberate status scopes, and inactive Students do not erase commitments. |
| Business rules | Confirmed/completed/cancelled/no-show stay distinct; list and calendar partitions have deliberate status scopes, and inactive Students do not erase commitments. Detail exposes original history and current authorized actions; no hidden payment gateway or repeated credit debit. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-040](#sa-040), [SA-041](#sa-041), [SA-042](#sa-042), [SA-043](#sa-043), [SA-051](#sa-051) |
| QA scenarios | Q04 |
| Actual fixture/reference | booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, recurring_lesson_occurrences:1, bookings:20, recurring_lesson_occurrences:2, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, admin_notifications:17, admin_notifications:18, admin_notifications:19, admin_notifications:20, admin_notifications:21, admin_notifications:22, admin_notifications:23, admin_notifications:24, admin_notifications:25, admin_notifications:26, admin_notifications:27, admin_notifications:28, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: AdminFunctionsAndCalendarTest::test_booking_calendar_week_view_boundary_and_cairo_timezone_rendering; AdminFunctionsAndCalendarTest::test_booking_calendar_day_view_boundary_and_cairo_timezone_rendering |
| Negative/edge test | Executed isolated edge/security assertions: AdminOperationsTest::test_unauthenticated_user_redirected_to_admin_login; AdminOperationsTest::test_admin_contact_duplicate_detection_and_merge |
| Browser/UI evidence | staff-page-bookings-calendar (2026-10-06T22:49:45.648Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/BookingController.php](app/Http/Controllers/Admin/BookingController.php) |

### SA-040 — Manual direct booking with optional canonical Student preselection

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Booking creation |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Booking creation. Manual direct booking with optional canonical Student preselection. Admin manual path bypasses public hold requirement only, not slot availability/notice/buffer/date/calendar locks. |
| Business rules | Admin manual path bypasses public hold requirement only, not slot availability/notice/buffer/date/calendar locks. Optional Student ID is rechecked; selected Student prefills existing contact/timezone. Direct booking does not spend/create typed rights or invent cash payment. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-039](#sa-039), [SA-044](#sa-044), [SA-045](#sa-045), [SA-020](#sa-020), [SA-051](#sa-051) |
| QA scenarios | Q04 |
| Actual fixture/reference | session_types:4, session_types:5, booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: StaffOperationsProductivityTest::test_contextual_booking_prefills_the_existing_manual_flow_and_preserves_permissions; BookingEngineTest::test_creates_booking_with_complete_immutable_timezone_snapshots_and_booking_event |
| Negative/edge test | Executed isolated edge/security assertions: BookingEngineTest::test_booking_idempotency_returns_existing_booking_without_duplicate; BookingEngineTest::test_booking_creation_rejects_tampered_duration |
| Browser/UI evidence | staff-page-bookings-calendar (2026-10-06T22:49:45.648Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Public and Student normal booking paths execute against production; staff preselection/manual creation controller branches pass isolated tests. No extra staff-created production booking was added. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/BookingController.php](app/Http/Controllers/Admin/BookingController.php)<br>[app/Domains/Booking/Services/BookingService.php](app/Domains/Booking/Services/BookingService.php) |

### SA-041 — Staff reschedule with canonical slot and immutable typed funding

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Rescheduling |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Rescheduling. Staff reschedule with canonical slot and immutable typed funding. Same availability/calendar/Student/Booking locks, notice/DST/holiday boundaries and idempotency govern moves. |
| Business rules | Same availability/calendar/Student/Booking locks, notice/DST/holiday boundaries and idempotency govern moves. A type/required-unit change incompatible with existing debit is refused rather than silently converting rights. Historical old/new times retained. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-039](#sa-039), [SA-044](#sa-044), [SA-045](#sa-045), [SA-050](#sa-050), [STU-010](#stu-010) |
| QA scenarios | Q04 |
| Actual fixture/reference | booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: StudentReschedulingTest::test_student_reschedule_uses_server_slot_preserves_status_and_is_idempotent; StudentReschedulingTest::test_student_reschedule_uses_fixed_utc_cutoff_across_cairo_dst_transitions |
| Negative/edge test | Executed isolated edge/security assertions: StudentReschedulingTest::test_student_cannot_reschedule_another_students_booking_or_one_within_24_hours; TypedEntitlementsTest::test_reschedule_keeps_original_debit_for_same_or_compatible_lesson_and_rejects_incompatible_type |
| Browser/UI evidence | staff-page-bookings-calendar (2026-10-06T22:49:45.648Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Production Student reschedule retains exact debit39/allocation5 across DST; staff reschedule variants and conflicting races pass isolated tests. Original fixture bookings were not moved. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/BookingController.php](app/Http/Controllers/Admin/BookingController.php)<br>[app/Domains/Booking/Services/RescheduleService.php](app/Domains/Booking/Services/RescheduleService.php) |

### SA-042 — Mark completed or no-show without fabricating payment

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Lesson outcome |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Lesson outcome. Mark completed or no-show without fabricating payment. Cannot complete cancelled/completed/no-show records through completion action. |
| Business rules | Cannot complete cancelled/completed/no-show records through completion action. No-show is a terminal policy transition under locks; restore/retain follows snapshot, retains original debit when applicable and does not create money refund. Competing cancel/no-show produce one consequence. Completion has status guards but no elapsed-start/end requirement; a future confirmed lesson can currently be marked complete. No-show requires confirmed status but also has no elapsed-time guard. This is current behavior, not a certified attendance timestamp. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-039](#sa-039), [SA-043](#sa-043), [SA-047](#sa-047), [SA-038](#sa-038) |
| QA scenarios | Q04 |
| Actual fixture/reference | booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: BusinessLifecycleDataQualityTest::test_configured_no_show_restoration_is_append_only_without_a_second_debit; BusinessLifecycleDataQualityTest::test_new_xlsx_exports_keep_financial_filters_and_actual_payment_rows |
| Negative/edge test | Executed isolated edge/security assertions: BookingLifecycleAndPolicyCutoffTest::test_completed_booking_cannot_be_cancelled_or_rescheduled; BookingLifecycleAndPolicyCutoffTest::test_completed_booking_cannot_be_rescheduled |
| Browser/UI evidence | staff-page-bookings-calendar (2026-10-06T22:49:45.648Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Prepared delivered/no-show facts and financial independence are checked. New complete/no-show transitions and races are isolated tests; no further future lesson was marked delivered. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/BookingController.php](app/Http/Controllers/Admin/BookingController.php)<br>[app/Domains/Booking/Services/NoShowService.php](app/Domains/Booking/Services/NoShowService.php) |

### SA-043 — Staff cancellation and separate immutable credit consequence

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Cancellation policy |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Cancellation policy. Staff cancellation and separate immutable credit consequence. Staff restore/retain differs from customer cutoff; retain is not another charge. |
| Business rules | Staff restore/retain differs from customer cutoff; retain is not another charge. Restores original allocation/quantity once, including typed provenance; no automatic cash refund. Shared reason code plus optional notes preserve historical text. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-047](#sa-047), [SA-031](#sa-031), [SA-042](#sa-042), [PUB-013](#pub-013) |
| QA scenarios | Q04 |
| Actual fixture/reference | booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: BusinessLifecycleDataQualityTest::test_cancellation_restores_once_and_records_separate_typed_policy_decision; BusinessLifecycleDataQualityTest::test_retaining_cancellation_keeps_only_the_original_credit_consumption |
| Negative/edge test | Executed isolated edge/security assertions: BookingLifecycleAndPolicyCutoffTest::test_pending_booking_cannot_be_cancelled_or_restore_a_student_credit; BookingLifecycleAndPolicyCutoffTest::test_cancelled_booking_cancellation_is_idempotent_but_cannot_be_rescheduled_again |
| Browser/UI evidence | staff-page-bookings-calendar (2026-10-06T22:49:45.648Z); booking-23-cancelled (2026-10-07T03:36:17.509Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Booking/Services/CancellationService.php](app/Domains/Booking/Services/CancellationService.php)<br>[app/Domains/Booking/Services/BookingPolicyService.php](app/Domains/Booking/Services/BookingPolicyService.php)<br>[app/Http/Controllers/Admin/BookingController.php](app/Http/Controllers/Admin/BookingController.php) |

### SA-044 — SessionType duration/price/funding and exact entitlement requirement

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Lesson configuration |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Lesson configuration. SessionType duration/price/funding and exact entitlement requirement. Funding modes package/direct/free/legacy are finite; package requires active type+units and nonpackage clears them. |
| Business rules | Funding modes package/direct/free/legacy are finite; package requires active type+units and nonpackage clears them. Existing Booking entitlement snapshots are unchanged. Availability override duration can differ from nominal duration; rights are not inferred from minutes or price. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-027](#sa-027), [STU-009](#stu-009), [SA-045](#sa-045), [SA-041](#sa-041) |
| QA scenarios | Q05 |
| Actual fixture/reference | session_types:4, session_types:5, recurring_lesson_plans:1, recurring_lesson_occurrences:1, recurring_lesson_occurrences:2, student_unavailabilities:1, booking_waitlists:1, session_types:6, student_packages:20, student_package_entitlements:14, session_ledger_entries:38 |
| Positive test | Executed isolated mutation/projection assertions: DurationOverrideBookingTest::test_customer_http_reschedule_into_override_duration_rule_is_disabled; DurationOverrideBookingTest::test_admin_http_reschedule_and_manual_create_into_override_duration_rule |
| Negative/edge test | Executed isolated edge/security assertions: BusinessOperationsControlsTest::test_only_super_admin_can_suspend_and_self_suspension_is_rejected; BusinessOperationsControlsTest::test_suspended_administrator_cannot_reuse_an_existing_session_or_log_in |
| Browser/UI evidence | staff-page-tutor-availability (2026-10-06T22:49:46.545Z); student-c-unpoolable-types-excluded (2026-10-07T03:37:37.831Z); student-c-nonpooling-blocked (2026-10-07T03:37:57.050Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/SessionTypeController.php](app/Http/Controllers/Admin/SessionTypeController.php) |

### SA-045 — Weekly tutor availability, duration/buffer/notice/horizon overrides

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Availability |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Availability. Weekly tutor availability, duration/buffer/notice/horizon overrides. Weekday start/end and rule duration/buffer/notice/horizon affect authoritative future slot generation. |
| Business rules | Weekday start/end and rule duration/buffer/notice/horizon affect authoritative future slot generation. Validation rejects invalid windows; existing booked history is retained. Changes do not blanket-flush all application cache. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [PUB-008](#pub-008), [SA-044](#sa-044), [SA-046](#sa-046) |
| QA scenarios | Q05 |
| Actual fixture/reference | session_types:4, session_types:5, recurring_lesson_plans:1, recurring_lesson_occurrences:1, recurring_lesson_occurrences:2, student_unavailabilities:1, booking_waitlists:1, session_types:6 |
| Positive test | Executed isolated mutation/projection assertions: AvailabilityTest::test_special_hours_exception_overrides_weekly_rules; CanonicalAvailabilityValidationTest::test_rule_specific_notice_and_horizon_are_enforced |
| Negative/edge test | Executed isolated edge/security assertions: CanonicalAvailabilityValidationTest::test_rule_duration_override_enforces_rule_duration_and_rejects_client_mismatch; AvailabilityTest::test_blocked_date_exception_prevents_slot_generation |
| Browser/UI evidence | staff-page-tutor-availability (2026-10-06T22:49:46.545Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/AvailabilityController.php](app/Http/Controllers/Admin/AvailabilityController.php)<br>[app/Domains/Availability/Services/AvailabilityService.php](app/Domains/Availability/Services/AvailabilityService.php) |

### SA-046 — Date-specific tutor exceptions/blocked days with booked-history warning

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Availability exceptions |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Availability exceptions. Date-specific tutor exceptions/blocked days with booked-history warning. One exception per date, validated blocked/custom window and existing-calendar lock protect scheduling. |
| Business rules | One exception per date, validated blocked/custom window and existing-calendar lock protect scheduling. Current confirmed commitments trigger warning rather than silent cancellation/rewrite. Conflicts/DST are checked through existing availability path. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-045](#sa-045), [SA-039](#sa-039), [SA-048](#sa-048) |
| QA scenarios | Q05 |
| Actual fixture/reference | session_types:4, session_types:5, recurring_lesson_plans:1, recurring_lesson_occurrences:1, recurring_lesson_occurrences:2, student_unavailabilities:1, booking_waitlists:1, session_types:6 |
| Positive test | Executed isolated mutation/projection assertions: BusinessLifecycleDataQualityTest::test_student_holidays_and_tutor_blocked_dates_have_separate_safe_reasons; BusinessLifecycleDataQualityTest::test_receivables_and_business_report_reconcile_same_canonical_balance_and_dates |
| Negative/edge test | Executed isolated edge/security assertions: AvailabilityTest::test_blocked_date_exception_prevents_slot_generation; BusinessLifecycleDataQualityTest::test_installment_totals_and_history_cannot_be_rewritten |
| Browser/UI evidence | staff-page-tutor-availability (2026-10-06T22:49:46.545Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/AvailabilityController.php](app/Http/Controllers/Admin/AvailabilityController.php)<br>[app/Domains/Availability/Services/AvailabilityService.php](app/Domains/Availability/Services/AvailabilityService.php) |

### SA-047 — Cancellation/no-show policy and immutable new-booking snapshots

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Policy configuration |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Policy configuration. Cancellation/no-show policy and immutable new-booking snapshots. Cutoff 0–168 hours; late customer deny/restore/retain, staff/no-show restore/retain. |
| Business rules | Cutoff 0–168 hours; late customer deny/restore/retain, staff/no-show restore/retain. Existing snapshot does not change with settings; null historical snapshot remains version-0 legacy semantics. Invalid stored policy is flagged by Data Quality and new bookings use documented defaults until corrected. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-043](#sa-043), [SA-042](#sa-042), [SA-083](#sa-083) |
| QA scenarios | Q04, Q05 |
| Actual fixture/reference | session_types:4, session_types:5, booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, recurring_lesson_plans:1, recurring_lesson_occurrences:1, bookings:20, recurring_lesson_occurrences:2, bookings:21, student_unavailabilities:1, booking_waitlists:1, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, session_types:6, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: BusinessLifecycleDataQualityTest::test_cancellation_restores_once_and_records_separate_typed_policy_decision; BookingPolicyMigrationTest::test_v2_policy_cutoffs_are_split_by_migration |
| Negative/edge test | Executed isolated edge/security assertions: BusinessLifecycleDataQualityTest::test_new_policy_cannot_rewrite_booking_snapshot_and_legacy_rows_keep_legacy_rules; BusinessLifecycleDataQualityTest::test_invalid_stored_policy_is_reported_read_only_and_uses_safe_new_booking_defaults |
| Browser/UI evidence | staff-page-booking-policy (2026-10-06T22:49:36.775Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Booking/Services/BookingPolicyService.php](app/Domains/Booking/Services/BookingPolicyService.php)<br>[app/Http/Controllers/Admin/BookingPolicyController.php](app/Http/Controllers/Admin/BookingPolicyController.php) |

### SA-048 — Explicit recurring plans, bounded manual generation and safe blocked reasons

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Recurring lessons |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Recurring lessons. Explicit recurring plans, bounded manual generation and safe blocked reasons. At most 104 occurrences; generation explicitly requests 1–12 (default 4). |
| Business rules | At most 104 occurrences; generation explicitly requests 1–12 (default 4). Already booked sequence reuses Booking; blocked sequences can be reconsidered under current checks. Pause/complete does not cancel existing lessons. Safe reason categories hide other Students; no automatic scheduled generator. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [STU-009](#stu-009), [SA-044](#sa-044), [SA-045](#sa-045), [SA-046](#sa-046), [SA-050](#sa-050), [SA-025](#sa-025) |
| QA scenarios | Q05 |
| Actual fixture/reference | session_types:4, session_types:5, recurring_lesson_plans:1, recurring_lesson_occurrences:1, recurring_lesson_occurrences:2, student_unavailabilities:1, booking_waitlists:1, session_types:6 |
| Positive test | Executed isolated mutation/projection assertions: BusinessLifecycleDataQualityTest::test_recurring_generation_is_bounded_per_booking_typed_and_replay_safe; BusinessLifecycleDataQualityTest::test_student_holidays_and_tutor_blocked_dates_have_separate_safe_reasons |
| Negative/edge test | Executed isolated edge/security assertions: BusinessLifecycleDataQualityTest::test_paused_plans_and_archived_students_cannot_generate_new_recurring_bookings; BusinessLifecycleDataQualityTest::test_recurring_safe_conflicts_distinguish_notice_collision_and_inactive_lesson |
| Browser/UI evidence | staff-page-recurring-lessons (2026-10-06T22:49:35.418Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Original bounded manually generated recurrence plan/occurrences are reconciled. New generation/holiday/conflict/last-unit races pass isolated tests; no automatic worker or extra original occurrence was configured. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Booking/Services/RecurringLessonService.php](app/Domains/Booking/Services/RecurringLessonService.php)<br>[app/Http/Controllers/Admin/RecurringLessonController.php](app/Http/Controllers/Admin/RecurringLessonController.php) |

### SA-049 — Review/contact/close/withdraw lesson interest manually

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Waitlist operations |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Waitlist operations. Review/contact/close/withdraw lesson interest manually. Default daily queues exclude inactive/archived Students; explicit history remains. |
| Business rules | Default daily queues exclude inactive/archived Students; explicit history remains. Follow-up is manual unless separately using existing communication tools; changing status alone sends no promise of automatic matching or outreach. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [STU-027](#stu-027), [SA-025](#sa-025), [SA-048](#sa-048) |
| QA scenarios | Q05 |
| Actual fixture/reference | session_types:4, session_types:5, recurring_lesson_plans:1, recurring_lesson_occurrences:1, recurring_lesson_occurrences:2, student_unavailabilities:1, booking_waitlists:1, session_types:6 |
| Positive test | Executed isolated mutation/projection assertions: BusinessLifecycleDataQualityTest::test_free_recurring_lessons_use_no_package_ledger; BusinessLifecycleDataQualityTest::test_archive_excludes_default_roster_and_followups_but_preserves_booked_lessons_and_finance |
| Negative/edge test | Executed isolated edge/security assertions: BusinessLifecycleDataQualityTest::test_student_cannot_withdraw_another_students_interest_or_holiday; BusinessLifecycleDataQualityTest::test_recurring_safe_conflicts_distinguish_notice_collision_and_inactive_lesson |
| Browser/UI evidence | staff-page-lesson-waitlist (2026-10-06T22:49:36.100Z); student-d-waitlist-created (2026-10-07T03:39:11.449Z); student-d-waitlist-withdrawn (2026-10-07T03:39:17.533Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/StudentSchedulingController.php](app/Http/Controllers/Admin/StudentSchedulingController.php)<br>[app/Domains/Students/Services/StudentSchedulingService.php](app/Domains/Students/Services/StudentSchedulingService.php) |

### SA-050 — Staff manage Student unavailability separately from tutor calendar

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Student planning |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Student planning. Staff manage Student unavailability separately from tutor calendar. Staff shares the same Student-first validation/overlap rules as portal. |
| Business rules | Staff shares the same Student-first validation/overlap rules as portal. Holidays block linked future booking/reschedule/recurrence writes, not existing commitment cancellation; tutor weekly exceptions remain independent. Contact-only legacy bookings cannot infer a Student link. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [STU-026](#stu-026), [SA-048](#sa-048), [SA-041](#sa-041), [SA-046](#sa-046) |
| QA scenarios | Q05 |
| Actual fixture/reference | session_types:4, session_types:5, recurring_lesson_plans:1, recurring_lesson_occurrences:1, recurring_lesson_occurrences:2, student_unavailabilities:1, booking_waitlists:1, session_types:6 |
| Positive test | Executed isolated mutation/projection assertions: BusinessLifecycleDataQualityTest::test_student_holidays_and_tutor_blocked_dates_have_separate_safe_reasons; BusinessLifecycleDataQualityTest::test_calendar_exception_changes_preserve_bookings_and_unrelated_cache |
| Negative/edge test | Executed isolated edge/security assertions: BusinessLifecycleDataQualityTest::test_paused_plans_and_archived_students_cannot_generate_new_recurring_bookings; BusinessLifecycleDataQualityTest::test_plan_idempotency_rejects_changed_terms |
| Browser/UI evidence | student-d-planning-created (2026-10-07T03:39:02.697Z); student-d-planning-cancelled (2026-10-07T03:39:10.740Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/StudentSchedulingController.php](app/Http/Controllers/Admin/StudentSchedulingController.php) |

### SA-051 — Provider/room pools, preference, explicit assignment and reconciliation

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Meeting access |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Meeting access. Provider/room pools, preference, explicit assignment and reconciliation. Keep enabled default provider; unique HTTPS room URL/hash. |
| Business rules | Keep enabled default provider; unique HTTPS room URL/hash. Assignment excludes disabled/overlapping/consecutive-room reuse and rotates by prior use; unavailable pool marks need rather than leaking early link. Existing assignment URL snapshot survives room editing; reschedule revalidates/reassigns safely. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-039](#sa-039), [SA-041](#sa-041), [STU-028](#stu-028) |
| QA scenarios | Q04 |
| Actual fixture/reference | booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: MeetingRotationTest::test_manual_assignment_retains_valid_snapshot_and_revalidation_reassigns_disabled_room; MeetingRotationTest::test_chronological_rotation_uses_each_room_and_never_reuses_consecutive_rooms |
| Negative/edge test | Executed isolated edge/security assertions: MariaDbConcurrencyVerificationTest::test_concurrent_meeting_assignment_cannot_share_an_overlapping_room; MeetingRotationTest::test_preferred_provider_wins_and_overlap_manual_override_is_rejected |
| Browser/UI evidence | staff-page-meeting-links (2026-10-06T22:50:39.887Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Original harmless room/provider snapshots and pre-reveal state are inspected. Assignment/overlap/reconciliation races pass isolated tests; no genuine meeting URL was exposed or changed. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/MeetingLinkController.php](app/Http/Controllers/Admin/MeetingLinkController.php)<br>[app/Domains/Booking/Services/MeetingLinkService.php](app/Domains/Booking/Services/MeetingLinkService.php) |

### SA-052 — Staff manage private PDF, Resource, recording/link materials and sharing

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Lesson workspace |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Lesson workspace. Staff manage private PDF, Resource, recording/link materials and sharing. Admin/Super Admin policy permits teaching; Assistant is denied. |
| Business rules | Admin/Super Admin policy permits teaching; Assistant is denied. Private files stay outside public root; Resource references reuse files, recordings are validated links, not a recording capture service. Withdrawal clears access/references, retains row/history and reports cleanup pending if bytes could not be removed. Student access has separate completed/ended-confirmed/share checks. Private PDF maximum is 10 MiB; complete %PDF-/EOF bytes and PassiveLessonPdf reject scripts, automatic actions, launches and embedded files. Material kinds private_file/resource/external_link/recording have database payload-shape checks; title ≤200, description ≤5000, order 0–10000; sharing defaults off. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-039](#sa-039), [SA-061](#sa-061), [STU-011](#stu-011), [STU-012](#stu-012) |
| QA scenarios | Q06 |
| Actual fixture/reference | lesson_materials:2, lesson_materials:3, lesson_materials:4, lesson_materials:5, homeworks:1, learning_plans:1, learning_milestones:1, resource_assignments:1, student_error_logs:1, teaching_tags:1, student_bins:1, homeworks:2, learning_plans:2, learning_milestones:2, resource_assignments:2, student_error_logs:2, teaching_tags:2, student_bins:2, tutor_preparations:1, lesson_feedback:1 |
| Positive test | Executed isolated mutation/projection assertions: LessonWorkspaceTest::test_visible_recording_is_separate_and_safe_external_link_has_no_referrer; LessonWorkspaceTest::test_policy_matrix_for_staff_workspaces_and_materials with data set "tutor" |
| Negative/edge test | Executed isolated edge/security assertions: LessonWorkspaceTest::test_guests_and_suspended_staff_cannot_manage_materials; LessonWorkspaceTest::test_https_rule_rejects_unsafe_links with data set #0 |
| Browser/UI evidence | No separate browser mutation certified. Current source/controller behavior is exercised by the named isolated cases; production fixture facts are inspected where available. |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/LessonWorkspaceController.php](app/Http/Controllers/Admin/LessonWorkspaceController.php)<br>[app/Domains/Booking/Services/LessonMaterialService.php](app/Domains/Booking/Services/LessonMaterialService.php)<br>[app/Rules/PassiveLessonPdf.php](app/Rules/PassiveLessonPdf.php) |

### SA-053 — Author shared/private homework and tutor completion/feedback

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Teaching/homework |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Teaching/homework. Author shared/private homework and tutor completion/feedback. Assigned/in_progress/submitted/completed lifecycle; only tutor completes. |
| Business rules | Assigned/in_progress/submitted/completed lifecycle; only tutor completes. Owner/lesson/reference checks repeat under Student lock; due≥assigned date and 5000-character text bounds. Staff changes do not publish internal preparation or answer text into generic analytics/Telegram audit. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [STU-014](#stu-014), [SA-052](#sa-052), [SA-056](#sa-056) |
| QA scenarios | Q06 |
| Actual fixture/reference | lesson_materials:2, lesson_materials:3, lesson_materials:4, lesson_materials:5, homeworks:1, learning_plans:1, learning_milestones:1, resource_assignments:1, student_error_logs:1, teaching_tags:1, student_bins:1, homeworks:2, learning_plans:2, learning_milestones:2, resource_assignments:2, student_error_logs:2, teaching_tags:2, student_bins:2, tutor_preparations:1, lesson_feedback:1 |
| Positive test | Executed isolated mutation/projection assertions: StudentTeachingExperienceTest::test_staff_save_teaching_records_with_student_and_optional_lesson with data set "homework"; StudentTeachingExperienceTest::test_student_sees_only_shared_owned_teaching_and_authoritative_progress |
| Negative/edge test | Executed isolated edge/security assertions: StudentTeachingExperienceTest::test_homework_cannot_reference_a_material_from_another_lesson; StudentTeachingExperienceTest::test_invalid_homework_never_writes with data set "empty title" |
| Browser/UI evidence | tutor-homework-completed (2026-10-06T22:51:10.009Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/TeachingRecordService.php](app/Domains/Students/Services/TeachingRecordService.php)<br>[app/Http/Requests/SaveTeachingRecordRequest.php](app/Http/Requests/SaveTeachingRecordRequest.php)<br>[app/Http/Controllers/Admin/StudentTeachingController.php](app/Http/Controllers/Admin/StudentTeachingController.php) |

### SA-054 — Learning plans and editable milestones with completion facts

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Teaching plans |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Teaching plans. Learning plans and editable milestones with completion facts. Milestone parent ownership is checked; completing retains original completed_at, reopening clears it and touches plan. |
| Business rules | Milestone parent ownership is checked; completing retains original completed_at, reopening clears it and touches plan. Student reads shared state only; no automatic course sequencing, assessment or Student milestone authoring. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [STU-013](#stu-013), [STU-018](#stu-018), [SA-053](#sa-053) |
| QA scenarios | Q06 |
| Actual fixture/reference | lesson_materials:2, lesson_materials:3, lesson_materials:4, lesson_materials:5, homeworks:1, learning_plans:1, learning_milestones:1, resource_assignments:1, student_error_logs:1, teaching_tags:1, student_bins:1, homeworks:2, learning_plans:2, learning_milestones:2, resource_assignments:2, student_error_logs:2, teaching_tags:2, student_bins:2, tutor_preparations:1, lesson_feedback:1 |
| Positive test | Executed isolated mutation/projection assertions: StudentTeachingExperienceTest::test_teaching_permission_matches_lesson_workspace_roles with data set "super admin"; StudentTeachingExperienceTest::test_teaching_permission_matches_lesson_workspace_roles with data set "admin" |
| Negative/edge test | Executed isolated edge/security assertions: StudentTeachingExperienceTest::test_learning_milestone_completion_is_editable_and_foreign_plans_are_rejected; StudentTeachingExperienceTest::test_guest_and_suspended_student_cannot_access_learning_or_submit_feedback |
| Browser/UI evidence | No separate browser mutation certified. Current source/controller behavior is exercised by the named isolated cases; production fixture facts are inspected where available. |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/TeachingRecordService.php](app/Domains/Students/Services/TeachingRecordService.php)<br>[app/Http/Controllers/Admin/StudentTeachingController.php](app/Http/Controllers/Admin/StudentTeachingController.php) |

### SA-055 — Staff-only Student/lesson preparation

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Private tutor preparation |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Private tutor preparation. Staff-only Student/lesson preparation. Preparation is a separate structurally staff-only record with no student_visible switch. |
| Business rules | Preparation is a separate structurally staff-only record with no student_visible switch. Assistant lacks teaching management; Student/public/notifications/exports never receive preparation text. Audit stores identifiers/kind, not private body. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-052](#sa-052), [SA-054](#sa-054) |
| QA scenarios | Q06 |
| Actual fixture/reference | lesson_materials:2, lesson_materials:3, lesson_materials:4, lesson_materials:5, homeworks:1, learning_plans:1, learning_milestones:1, resource_assignments:1, student_error_logs:1, teaching_tags:1, student_bins:1, homeworks:2, learning_plans:2, learning_milestones:2, resource_assignments:2, student_error_logs:2, teaching_tags:2, student_bins:2, tutor_preparations:1, lesson_feedback:1 |
| Positive test | Executed isolated mutation/projection assertions: StudentTeachingExperienceTest::test_staff_save_teaching_records_with_student_and_optional_lesson with data set "preparation"; StudentTeachingExperienceTest::test_notifications_include_only_shared_materials_and_publish_new_lesson_events_once |
| Negative/edge test | Executed isolated edge/security assertions: StudentTeachingExperienceTest::test_staff_cannot_attach_a_foreign_lesson_or_edit_a_foreign_record; StudentTeachingExperienceTest::test_homework_cannot_reference_a_material_from_another_lesson |
| Browser/UI evidence | No separate browser mutation certified. Current source/controller behavior is exercised by the named isolated cases; production fixture facts are inspected where available. |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Models/TutorPreparation.php](app/Domains/Students/Models/TutorPreparation.php)<br>[app/Http/Requests/SaveTeachingRecordRequest.php](app/Http/Requests/SaveTeachingRecordRequest.php)<br>[app/Http/Controllers/Admin/StudentTeachingController.php](app/Http/Controllers/Admin/StudentTeachingController.php) |

### SA-056 — Reference existing Resources with instructions, sharing and review reset

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Resource assignment |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Resource assignment. Reference existing Resources with instructions, sharing and review reset. No duplicated Resource/file/material/gate request. |
| Business rules | No duplicated Resource/file/material/gate request. Publication and Student/lesson ownership checked on save/open; changed assignment resets prior review, optional withdraw hides it. No automatic file copying or unreviewed re-sharing after Resource reset. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-061](#sa-061), [STU-015](#stu-015), [SA-052](#sa-052) |
| QA scenarios | Q06 |
| Actual fixture/reference | resources:2, resources:3, lesson_materials:2, lesson_materials:3, lesson_materials:4, lesson_materials:5, homeworks:1, learning_plans:1, learning_milestones:1, resource_assignments:1, student_error_logs:1, teaching_tags:1, student_bins:1, homeworks:2, learning_plans:2, learning_milestones:2, resource_assignments:2, student_error_logs:2, teaching_tags:2, student_bins:2, tutor_preparations:1, lesson_feedback:1, resource_translations:2, resource_translations:3, content_revisions:9 |
| Positive test | Executed isolated mutation/projection assertions: StudentTeachingExperienceTest::test_resource_review_drives_next_action_and_reset_on_reassignment; StudentTeachingExperienceTest::test_homework_links_and_material_references_keep_their_existing_access_boundaries |
| Negative/edge test | Executed isolated edge/security assertions: StudentTeachingExperienceTest::test_homework_cannot_reference_a_material_from_another_lesson; StudentTeachingExperienceTest::test_assistant_cannot_read_or_write_teaching_but_retains_existing_educational_notes_permission |
| Browser/UI evidence | No separate browser mutation certified. Current source/controller behavior is exercised by the named isolated cases; production fixture facts are inspected where available. |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/TeachingRecordService.php](app/Domains/Students/Services/TeachingRecordService.php)<br>[app/Http/Controllers/Admin/StudentTeachingController.php](app/Http/Controllers/Admin/StudentTeachingController.php) |

### SA-057 — Maintain pronunciation/vocabulary/grammar error log

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Correction patterns |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Correction patterns. Maintain pronunciation/vocabulary/grammar error log. Finite categories/statuses and text bounds; progress uses stored improved/resolved facts. |
| Business rules | Finite categories/statuses and text bounds; progress uses stored improved/resolved facts. No inferred pronunciation scoring, speech-recognition evaluation or public proficiency export; privacy redaction later removes private text. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [STU-016](#stu-016), [STU-018](#stu-018), [SA-024](#sa-024) |
| QA scenarios | Q06 |
| Actual fixture/reference | lesson_materials:2, lesson_materials:3, lesson_materials:4, lesson_materials:5, homeworks:1, learning_plans:1, learning_milestones:1, resource_assignments:1, student_error_logs:1, teaching_tags:1, student_bins:1, homeworks:2, learning_plans:2, learning_milestones:2, resource_assignments:2, student_error_logs:2, teaching_tags:2, student_bins:2, tutor_preparations:1, lesson_feedback:1 |
| Positive test | Executed isolated mutation/projection assertions: StudentTeachingExperienceTest::test_staff_save_teaching_records_with_student_and_optional_lesson with data set "error"; StudentTeachingExperienceTest::test_teaching_permission_matches_lesson_workspace_roles with data set "super admin" |
| Negative/edge test | Executed isolated edge/security assertions: StudentTeachingExperienceTest::test_assistant_cannot_read_or_write_teaching_but_retains_existing_educational_notes_permission; StudentTeachingExperienceTest::test_suspended_staff_and_merged_students_cannot_use_teaching_workspace |
| Browser/UI evidence | No separate browser mutation certified. Current source/controller behavior is exercised by the named isolated cases; production fixture facts are inspected where available. |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/TeachingRecordService.php](app/Domains/Students/Services/TeachingRecordService.php)<br>[app/Http/Controllers/Admin/StudentTeachingController.php](app/Http/Controllers/Admin/StudentTeachingController.php) |

### SA-058 — Normalize and deduplicate Student/lesson tags

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Teaching tags |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Teaching tags. Normalize and deduplicate Student/lesson tags. Serialized Student lock prevents duplicate create; no DB uniqueness that would destroy merged historical rows. |
| Business rules | Serialized Student lock prevents duplicate create; no DB uniqueness that would destroy merged historical rows. Letters/numbers/spaces/hyphens validate; Student-visible tags remain private teaching context. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [STU-017](#stu-017), [SA-023](#sa-023) |
| QA scenarios | Q06 |
| Actual fixture/reference | lesson_materials:2, lesson_materials:3, lesson_materials:4, lesson_materials:5, homeworks:1, learning_plans:1, learning_milestones:1, resource_assignments:1, student_error_logs:1, teaching_tags:1, student_bins:1, homeworks:2, learning_plans:2, learning_milestones:2, resource_assignments:2, student_error_logs:2, teaching_tags:2, student_bins:2, tutor_preparations:1, lesson_feedback:1 |
| Positive test | Executed isolated mutation/projection assertions: StudentTeachingExperienceTest::test_teaching_permission_matches_lesson_workspace_roles with data set "super admin"; StudentTeachingExperienceTest::test_teaching_permission_matches_lesson_workspace_roles with data set "admin" |
| Negative/edge test | Executed isolated edge/security assertions: StudentTeachingExperienceTest::test_tags_normalize_and_deduplicate_with_separate_student_and_lesson_contexts; StudentTeachingExperienceTest::test_staff_cannot_attach_a_foreign_lesson_or_edit_a_foreign_record |
| Browser/UI evidence | No separate browser mutation certified. Current source/controller behavior is exercised by the named isolated cases; production fixture facts are inspected where available. |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/TeachingRecordService.php](app/Domains/Students/Services/TeachingRecordService.php)<br>[app/Http/Controllers/Admin/StudentTeachingController.php](app/Http/Controllers/Admin/StudentTeachingController.php) |

### SA-059 — Read private Student lesson ratings/comments

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Private feedback review |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Private feedback review. Read private Student lesson ratings/comments. Private completed-lesson feedback is visible to teaching staff, not a public testimonial or editable marketing score. |
| Business rules | Private completed-lesson feedback is visible to teaching staff, not a public testimonial or editable marketing score. Student submission reuses one booking-unique row; teaching audit/notifications do not duplicate private comments. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [STU-023](#stu-023), [SA-052](#sa-052) |
| QA scenarios | Q04, Q06 |
| Actual fixture/reference | booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, lesson_materials:2, lesson_materials:3, lesson_materials:4, lesson_materials:5, homeworks:1, learning_plans:1, learning_milestones:1, resource_assignments:1, student_error_logs:1, teaching_tags:1, student_bins:1, homeworks:2, learning_plans:2, learning_milestones:2, resource_assignments:2, student_error_logs:2, teaching_tags:2, student_bins:2, tutor_preparations:1, lesson_feedback:1, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52 |
| Positive test | Executed isolated mutation/projection assertions: StudentTeachingExperienceTest::test_teaching_permission_matches_lesson_workspace_roles with data set "super admin"; StudentTeachingExperienceTest::test_teaching_permission_matches_lesson_workspace_roles with data set "admin" |
| Negative/edge test | Executed isolated edge/security assertions: StudentTeachingExperienceTest::test_staff_cannot_attach_a_foreign_lesson_or_edit_a_foreign_record; StudentTeachingExperienceTest::test_homework_cannot_reference_a_material_from_another_lesson |
| Browser/UI evidence | student-feedback-updated (2026-10-06T23:04:34.036Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/StudentTeachingController.php](app/Http/Controllers/Admin/StudentTeachingController.php) |

### SA-060 — Staff author/share and Super Admin manage Student Educational Notes

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Educational notes |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Educational notes. Staff author/share and Super Admin manage Student Educational Notes. Authorized Assistant/Admin can read/create under existing policy, but edits remain creator-aware; Super Admin can edit/delete any. |
| Business rules | Authorized Assistant/Admin can read/create under existing policy, but edits remain creator-aware; Super Admin can edit/delete any. Student edits only Student-authored visible notes; hidden staff notes remain private. Student note deletion has no portal route. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [STU-019](#stu-019), [SA-024](#sa-024), [SA-019](#sa-019) |
| QA scenarios | Q06 |
| Actual fixture/reference | lesson_materials:2, lesson_materials:3, lesson_materials:4, lesson_materials:5, homeworks:1, learning_plans:1, learning_milestones:1, resource_assignments:1, student_error_logs:1, teaching_tags:1, student_bins:1, homeworks:2, learning_plans:2, learning_milestones:2, resource_assignments:2, student_error_logs:2, teaching_tags:2, student_bins:2, tutor_preparations:1, lesson_feedback:1 |
| Positive test | Executed isolated mutation/projection assertions: BinAuthorizationTest::test_staff_roles_can_share_and_edit_only_their_own_notes_and_only_super_admin_can_delete; StudentTeachingExperienceTest::test_student_sees_only_shared_owned_teaching_and_authoritative_progress |
| Negative/edge test | Executed isolated edge/security assertions: StudentTeachingExperienceTest::test_assistant_cannot_read_or_write_teaching_but_retains_existing_educational_notes_permission; BinAuthorizationTest::test_student_notes_are_isolated_hidden_notes_are_404_and_payload_cannot_change_ownership |
| Browser/UI evidence | No separate browser mutation certified. Current source/controller behavior is exercised by the named isolated cases; production fixture facts are inspected where available. |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/StudentBinController.php](app/Http/Controllers/StudentBinController.php)<br>[app/Policies/StudentBinPolicy.php](app/Policies/StudentBinPolicy.php) |

### SA-061 — Library authoring, private payload/cover replacement, drafts and publication

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Resource management |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Resource management. Library authoring, private payload/cover replacement, drafts and publication. Uploads allow pdf/zip/doc/docx/mp3/wav/m4a up to 50 MiB; cover up to 10 MiB. |
| Business rules | Uploads allow pdf/zip/doc/docx/mp3/wav/m4a up to 50 MiB; cover up to 10 MiB. Draft/publish workflow preserves live values until explicit publishing. Replacement retires old unreferenced bytes after commit. Ordinary per-Resource delete is distinct from Stage 5 reviewed graph reset and does not use that shared-file quarantine/recovery contract. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-062](#sa-062), [SA-063](#sa-063), [SA-066](#sa-066), [PUB-014](#pub-014), [STU-015](#stu-015), [SA-092](#sa-092) |
| QA scenarios | Q07 |
| Actual fixture/reference | resource_categories:3, resources:2, resources:3, contacts:11, contacts:12, contacts:14, contacts:15, contacts:13, contacts:16, admin_notifications:17, admin_notifications:18, admin_notifications:19, admin_notifications:20, admin_notifications:21, admin_notifications:22, admin_notifications:23, admin_notifications:24, admin_notifications:25, admin_notifications:26, admin_notifications:27, admin_notifications:28, resource_translations:2, resource_translations:3, category_translations:3, resource_downloads:2, resource_downloads:3, resource_requests:4, resource_requests:5, content_revisions:9 |
| Positive test | Executed isolated mutation/projection assertions: CmsDraftAndPreviewMatrixTest::test_failed_draft_publication_does_not_delete_pre_existing_draft_files; ResourceCoverSharedMediaTest::test_replacing_resource_cover_preserves_media_referenced_by_another_resource |
| Negative/edge test | Executed isolated edge/security assertions: CmsDraftAndPreviewMatrixTest::test_preview_routes_reject_unauthenticated_guests_even_with_fake_parameters; ExternalResourceLinkingTest::test_external_url_scheme_validation_rejects_unapproved_schemes |
| Browser/UI evidence | staff-page-resources (2026-10-06T22:49:49.082Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/ResourceController.php](app/Http/Controllers/Admin/ResourceController.php)<br>[app/Domains/Resources/Models/Resource.php](app/Domains/Resources/Models/Resource.php)<br>[app/Http/Controllers/ResourceController.php](app/Http/Controllers/ResourceController.php) |

### SA-062 — Manage category labels, active/order and localization

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Resource taxonomy |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Resource taxonomy. Manage category labels, active/order and localization. Categories feed public active category navigation. |
| Business rules | Categories feed public active category navigation. Finite ownership/FK rules protect referencing Resources; ordinary CRUD/category actions are separate from configurable Stage 5 Resources/Categories reset scope. Locale publication uses current source revision. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-061](#sa-061), [SA-066](#sa-066), [PUB-014](#pub-014) |
| QA scenarios | Q07 |
| Actual fixture/reference | resource_categories:3, resources:2, resources:3, contacts:11, contacts:12, contacts:14, contacts:15, contacts:13, contacts:16, resource_translations:2, resource_translations:3, category_translations:3, resource_downloads:2, resource_downloads:3, resource_requests:4, resource_requests:5, content_revisions:9 |
| Positive test | Executed isolated mutation/projection assertions: AdminManagementAndCmsTest::test_admin_create_artisan_command; AdminManagementAndCmsTest::test_administrator_crud_lifecycle_and_role_protection |
| Negative/edge test | Executed isolated edge/security assertions: CmsTranslationAndRevisionsTest::test_stale_translation_remains_publicly_visible |
| Browser/UI evidence | staff-page-resources (2026-10-06T22:49:49.082Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/ResourceCategoryController.php](app/Http/Controllers/Admin/ResourceCategoryController.php) |

### SA-063 — Upload/pick public Media with reference-protected removal

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Public Media |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Public Media. Upload/pick public Media with reference-protected removal. Image JPEG/PNG/WebP/GIF or PDF ≤10 MiB; SVG is excluded from uploaded public files. |
| Business rules | Image JPEG/PNG/WebP/GIF or PDF ≤10 MiB; SVG is excluded from uploaded public files. Picker reuses approved existing assets; referenced Media removal refuses rather than breaking known content. Public Media is not private lesson-file storage or automatic portable Media import. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-061](#sa-061), [SA-064](#sa-064), [SA-069](#sa-069) |
| QA scenarios | Q08 |
| Actual fixture/reference | media:2, pages:4, faqs:6, social_links:7, blogs:2, games:4, games:5, promotions:2, promotions:3, promotions:4, page_translations:5, page_translations:6, resource_translations:4, resource_translations:5, page_translations:4, blog_slug_redirects:2, blog_revisions:2, faq_translations:6, game_translations:4, game_translations:5, content_revisions:5, content_revisions:6, content_revisions:7, content_revisions:8 |
| Positive test | Executed isolated mutation/projection assertions: CmsAndMediaWorkflowTest::test_admin_forms_contain_media_picker_trigger; CmsAndMediaWorkflowTest::test_media_upload_never_uses_a_client_supplied_executable_extension_for_storage |
| Negative/edge test | Executed isolated edge/security assertions: CmsAndMediaWorkflowTest::test_public_media_upload_rejects_active_svg_content; CmsAndMediaWorkflowTest::test_referenced_media_cannot_be_silently_deleted |
| Browser/UI evidence | staff-page-media-library (2026-10-06T22:49:54.917Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Existing passive QA media/reference protection is inspected and exercised in isolated upload/delete tests. No production media deletion was performed. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/MediaController.php](app/Http/Controllers/Admin/MediaController.php)<br>[app/Domains/CMS/Models/Media.php](app/Domains/CMS/Models/Media.php) |

### SA-064 — Draft/live Pages, sanitized rich text, revisions and explicit restoration

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Page CMS |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Page CMS. Draft/live Pages, sanitized rich text, revisions and explicit restoration. Draft save and explicit publish/restore preserve history rather than overwrite arbitrary old revision. |
| Business rules | Draft save and explicit publish/restore preserve history rather than overwrite arbitrary old revision. Published public visibility follows status/time; authenticated previews do not leak drafts. Dangerous rich text/active content is sanitized; source edits can make translations stale. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [PUB-004](#pub-004), [SA-063](#sa-063), [SA-066](#sa-066) |
| QA scenarios | Q08 |
| Actual fixture/reference | media:2, pages:4, faqs:6, social_links:7, blogs:2, games:4, games:5, promotions:2, promotions:3, promotions:4, page_translations:5, page_translations:6, resource_translations:4, resource_translations:5, page_translations:4, blog_slug_redirects:2, blog_revisions:2, faq_translations:6, game_translations:4, game_translations:5, content_revisions:5, content_revisions:6, content_revisions:7, content_revisions:8 |
| Positive test | Executed isolated mutation/projection assertions: CmsDraftAndPreviewMatrixTest::test_resource_draft_update_preserves_live_version_and_saves_content_revision; CmsDraftAndPreviewMatrixTest::test_game_draft_update_preserves_live_version_and_authenticated_preview |
| Negative/edge test | Executed isolated edge/security assertions: CmsAndMediaWorkflowTest::test_draft_page_never_leaks_publicly_to_unauthenticated_visitors; CmsAndMediaWorkflowTest::test_valid_signed_preview_url_is_denied_to_unauthenticated_guests |
| Browser/UI evidence | staff-page-pages-cms (2026-10-06T22:49:51.591Z); public-qa-published-page (2026-10-07T03:44:28.317Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/PageController.php](app/Http/Controllers/Admin/PageController.php)<br>[app/Domains/CMS/Services/RichTextSanitizer.php](app/Domains/CMS/Services/RichTextSanitizer.php)<br>[app/Http/Controllers/PageController.php](app/Http/Controllers/PageController.php) |

### SA-065 — FAQ drafts and native social/Reddit/WhatsApp channel configuration

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Content/communication |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Content/communication. FAQ drafts and native social/Reddit/WhatsApp channel configuration. New SocialLink starts disabled; supported platform includes Reddit. |
| Business rules | New SocialLink starts disabled; supported platform includes Reddit. HTTP(S) targets prohibit unsafe credentialed/invalid schemes; Telegram handle and international WhatsApp numbers use dedicated validation. FAQ live/draft semantics and public escaping stay intact; no external message is sent by configuration alone. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [PUB-004](#pub-004), [PUB-019](#pub-019), [PUB-020](#pub-020), [SA-066](#sa-066) |
| QA scenarios | Q08 |
| Actual fixture/reference | media:2, pages:4, faqs:6, social_links:7, blogs:2, games:4, games:5, promotions:2, promotions:3, promotions:4, page_translations:5, page_translations:6, resource_translations:4, resource_translations:5, page_translations:4, blog_slug_redirects:2, blog_revisions:2, faq_translations:6, game_translations:4, game_translations:5, content_revisions:5, content_revisions:6, content_revisions:7, content_revisions:8 |
| Positive test | Executed isolated mutation/projection assertions: StageOnePresentationTest::test_reddit_configuration_footer_and_filter_aware_social_exports_reuse_existing_paths; SocialHistoryAndIpPrivacyTest::test_social_dimensions_and_daily_uniques_survive_pruning_without_false_period_uniques |
| Negative/edge test | Executed isolated edge/security assertions: StageOnePresentationTest::test_reddit_configuration_rejects_unsafe_urls_and_assistant_writes; CmsDraftAndPreviewMatrixTest::test_preview_routes_reject_unauthenticated_guests_even_with_fake_parameters |
| Browser/UI evidence | staff-page-content-faqs (2026-10-06T22:49:50.818Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — FAQ/social configuration and disabled QA Reddit are inspected. Publication/configuration/unsafe URL cases are isolated tests; genuine social destinations remain unchanged. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/ContentController.php](app/Http/Controllers/Admin/ContentController.php)<br>[app/Http/Controllers/PageController.php](app/Http/Controllers/PageController.php) |

### SA-066 — French/German draft publication and source-revision reconciliation

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Localization CMS |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Localization CMS. French/German draft publication and source-revision reconciliation. Only supported entity/FR/DE locale; unknown kind/locale rejects. |
| Business rules | Only supported entity/FR/DE locale; unknown kind/locale rejects. Publication explicitly archives prior live version and validates current source; English edits can leave translation stale pending human reconciliation. Draft diff GET is read-only, not an auto-translation service. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [PUB-003](#pub-003), [SA-061](#sa-061), [SA-064](#sa-064), [SA-065](#sa-065), [SA-068](#sa-068) |
| QA scenarios | Q08 |
| Actual fixture/reference | media:2, pages:4, faqs:6, social_links:7, blogs:2, games:4, games:5, promotions:2, promotions:3, promotions:4, page_translations:5, page_translations:6, resource_translations:4, resource_translations:5, page_translations:4, blog_slug_redirects:2, blog_revisions:2, faq_translations:6, game_translations:4, game_translations:5, content_revisions:5, content_revisions:6, content_revisions:7, content_revisions:8 |
| Positive test | Executed isolated mutation/projection assertions: CmsTranslationAndRevisionsTest::test_draft_reconciliation_flagged_on_new_source_revision; AdminTranslationWorkflowTest::test_draft_save_reconciliation_and_atomic_publish_workflow |
| Negative/edge test | Executed isolated edge/security assertions: AdminTranslationWorkflowTest::test_resource_english_edit_creates_new_revision_and_stales_french_translation; CmsTranslationAndRevisionsTest::test_stale_translation_remains_publicly_visible |
| Browser/UI evidence | public-explicit-french (2026-10-07T03:44:37.702Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/TranslationController.php](app/Http/Controllers/Admin/TranslationController.php)<br>[app/Domains/CMS/Services/TranslationService.php](app/Domains/CMS/Services/TranslationService.php) |

### SA-067 — Blog draft/publication, sanitized Markdown, optimistic edits and old-slug history

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Blog CMS |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Blog CMS. Blog draft/publication, sanitized Markdown, optimistic edits and old-slug history. Current public authority is Blogs, not abandoned articles tables. |
| Business rules | Current public authority is Blogs, not abandoned articles tables. Optimistic version rejects stale edit; draft preview staff/noindex only. Renaming a published slug retains redirect to published target; raw HTML/unsafe Markdown content cannot become active script. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [PUB-017](#pub-017), [SA-063](#sa-063) |
| QA scenarios | Q08 |
| Actual fixture/reference | media:2, pages:4, faqs:6, social_links:7, blogs:2, games:4, games:5, promotions:2, promotions:3, promotions:4, page_translations:5, page_translations:6, resource_translations:4, resource_translations:5, page_translations:4, blog_slug_redirects:2, blog_revisions:2, faq_translations:6, game_translations:4, game_translations:5, content_revisions:5, content_revisions:6, content_revisions:7, content_revisions:8 |
| Positive test | Executed isolated mutation/projection assertions: BlogPublishingTest::test_drafts_never_leak_through_public_blog_route; CmsAndMediaWorkflowTest::test_published_version_stays_public_during_draft_edits_and_explicit_publish_switches_versions |
| Negative/edge test | Executed isolated edge/security assertions: BlogPublishingTest::test_stale_blog_editor_version_is_rejected_with_conflict; CmsAndMediaWorkflowTest::test_draft_page_never_leaks_publicly_to_unauthenticated_visitors |
| Browser/UI evidence | staff-page-blog (2026-10-06T22:49:52.275Z); public-qa-blog-reading (2026-10-07T03:44:36.961Z); public-qa-blog-settled (2026-10-07T03:44:51.149Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/BlogController.php](app/Http/Controllers/Admin/BlogController.php)<br>[app/Domains/CMS/Services/BlogService.php](app/Domains/CMS/Services/BlogService.php) |

### SA-068 — Game catalog status, safe target, draft/publish and localized presentation

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Games/content |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Games/content. Game catalog status, safe target, draft/publish and localized presentation. Available/coming_soon public display is distinct from playable tracking; current game presentation/local/external target is not a programmable LMS lesson engine. |
| Business rules | Available/coming_soon public display is distinct from playable tracking; current game presentation/local/external target is not a programmable LMS lesson engine. Draft saves/previews avoid leaking live changes; active target schemes/path validation and publication checks apply. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [PUB-018](#pub-018), [SA-063](#sa-063), [SA-066](#sa-066) |
| QA scenarios | Q08 |
| Actual fixture/reference | media:2, pages:4, faqs:6, social_links:7, blogs:2, games:4, games:5, promotions:2, promotions:3, promotions:4, page_translations:5, page_translations:6, resource_translations:4, resource_translations:5, page_translations:4, blog_slug_redirects:2, blog_revisions:2, faq_translations:6, game_translations:4, game_translations:5, content_revisions:5, content_revisions:6, content_revisions:7, content_revisions:8 |
| Positive test | Executed isolated mutation/projection assertions: CmsDraftAndPreviewMatrixTest::test_publishing_game_edits_preserves_the_selected_catalog_availability with data set #0; CmsDraftAndPreviewMatrixTest::test_publishing_game_edits_preserves_the_selected_catalog_availability with data set #1 |
| Negative/edge test | Executed isolated edge/security assertions: AdminTranslationWorkflowTest::test_resource_english_edit_creates_new_revision_and_stales_french_translation; CmsDraftAndPreviewMatrixTest::test_preview_routes_reject_unauthenticated_guests_even_with_fake_parameters |
| Browser/UI evidence | staff-page-learning-games (2026-10-06T22:49:49.812Z); qa-game-destination-correction (2026-10-06T22:54:07.918Z); qa-coming-game-destination (2026-10-06T22:54:47.485Z); game-status-repaired (2026-10-07T02:55:02.727Z); public-game-status-final (2026-10-07T03:43:52.392Z); qa-game-embedded-test-publication (2026-10-07T04:10:18.198Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | PRELMS-006 |
| Fix commit SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Deployed fix SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Retest | Coming Soon/Disabled/available regressions pass; actual QA Game5 publish preserves Coming Soon and catalog has no play link. |
| Authoritative current source | [app/Http/Controllers/Admin/GameController.php](app/Http/Controllers/Admin/GameController.php)<br>[app/Http/Controllers/GameController.php](app/Http/Controllers/GameController.php) |

### SA-069 — Manage scheduled promotion bars, modal/cards and countdown

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Promotions |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Promotions. Manage scheduled promotion bars, modal/cards and countdown. Display types top_bar/floating_modal/inline_card; end≥start, safe CTA/banner validation and 10 MiB uploaded banner limit. |
| Business rules | Display types top_bar/floating_modal/inline_card; end≥start, safe CTA/banner validation and 10 MiB uploaded banner limit. Latest eligible record per placement uses transition-aware cache capped at 300 seconds; no scheduler needed to flip publication window. Separate from emergency announcement. Authenticated Promotion preview renders the selected promotion separately from active public display; normal admin preview permission applies. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [PUB-026](#pub-026), [SA-063](#sa-063), [SA-078](#sa-078) |
| QA scenarios | Q08 |
| Actual fixture/reference | media:2, pages:4, faqs:6, social_links:7, blogs:2, games:4, games:5, promotions:2, promotions:3, promotions:4, page_translations:5, page_translations:6, resource_translations:4, resource_translations:5, page_translations:4, blog_slug_redirects:2, blog_revisions:2, faq_translations:6, game_translations:4, game_translations:5, content_revisions:5, content_revisions:6, content_revisions:7, content_revisions:8 |
| Positive test | Executed isolated mutation/projection assertions: PromotionsTest::test_active_promotion_retrieval_and_time_bounded_caching; PromotionsTest::test_admin_can_crud_promotions |
| Negative/edge test | Executed isolated edge/security assertions: PromotionsTest::test_expired_or_future_promotions_are_not_active; PromotionsTest::test_promotion_rejects_executable_links_and_stores_uploaded_image_with_a_safe_extension |
| Browser/UI evidence | staff-page-promotions-portal (2026-10-06T22:49:53.097Z); qa-promotion-destinations (2026-10-06T22:55:07.764Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/PromotionController.php](app/Http/Controllers/Admin/PromotionController.php)<br>[app/Domains/Marketing/Services/PromotionService.php](app/Domains/Marketing/Services/PromotionService.php) |

### SA-070 — Versioned questionnaire authoring, conditions, assignment triggers and publication

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Forms builder |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Forms builder. Versioned questionnaire authoring, conditions, assignment triggers and publication. Typed questions/conditional required/validation/info blocks, mandatory/edit-after-submission and assignment triggers define runtime forms. |
| Business rules | Typed questions/conditional required/validation/info blocks, mandatory/edit-after-submission and assignment triggers define runtime forms. base_version_id and lock_version refuse stale edits/publication; historical answers remain tied to original version. Assistant can read permitted published definitions, not manage builder. No Student arbitrary form authoring/file upload. Current assignments are derived, not a fabricated manual-assignment table: a published form without pre_booking/after_booking/after_reschedule/next_session_check triggers is generally assigned; after_booking requires owned nondeleted booking history, after_reschedule an owned reschedule, next_session_check an upcoming confirmed lesson. Own prior submission keeps the form available; pre_booking alone does not generally assign it in the portal. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [PUB-010](#pub-010), [STU-020](#stu-020), [SA-071](#sa-071) |
| QA scenarios | Q09 |
| Actual fixture/reference | forms:3, form_versions:3, form_questions:10, form_questions:11, form_questions:12, form_triggers:4, form_triggers:5, form_submissions:2, form_submissions:3, form_versions:4, form_questions:13, form_questions:14, form_questions:15, form_submission_revisions:1, form_submission_revisions:2, form_answers:4, form_answers:5, form_answers:6, form_answers:7, form_answers:8 |
| Positive test | Executed isolated mutation/projection assertions: FormsEngineTest::test_form_triggers_assign_forms_and_block_untriggered_direct_access; FilterAwareExportsTest::test_every_dataset_has_one_filter_scope_for_screen_csv_xlsx_and_clear with data set #0 |
| Negative/edge test | Executed isolated edge/security assertions: FormsEngineTest::test_drafts_freeze_old_versions_and_stale_builder_posts_conflict; FormsEngineTest::test_conditional_logic_rejects_cycles_and_depth_greater_than_three |
| Browser/UI evidence | staff-page-student-forms (2026-10-06T22:49:54.151Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/FormController.php](app/Http/Controllers/Admin/FormController.php)<br>[app/Domains/Forms/Services/FormBuilderService.php](app/Domains/Forms/Services/FormBuilderService.php)<br>[app/Domains/Forms/Services/FormAssignmentService.php](app/Domains/Forms/Services/FormAssignmentService.php) |

### SA-071 — Versioned submissions, permission-filtered answers and tabular export

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Form responses |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Form responses. Versioned submissions, permission-filtered answers and tabular export. Answers stay on their submitted version; screen/export use same FormSubmissionQuery. |
| Business rules | Answers stay on their submitted version; screen/export use same FormSubmissionQuery. Assistant omits non-assistant-visible answers/questions and info blocks are not answer columns. Actual submitted timestamp and status retained; no synthetic completion from draft autosave. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-070](#sa-070), [STU-020](#stu-020), [STU-021](#stu-021) |
| QA scenarios | Q09 |
| Actual fixture/reference | forms:3, form_versions:3, form_questions:10, form_questions:11, form_questions:12, form_triggers:4, form_triggers:5, form_submissions:2, form_submissions:3, form_versions:4, form_questions:13, form_questions:14, form_questions:15, form_submission_revisions:1, form_submission_revisions:2, form_answers:4, form_answers:5, form_answers:6, form_answers:7, form_answers:8 |
| Positive test | Executed isolated mutation/projection assertions: FormsEngineTest::test_submission_revisions_are_append_only_and_assistant_exports_hide_private_answers_and_formulae; FormsEngineTest::test_csv_export_reads_questions_once_and_answers_in_200_row_chunks; production HTTP assertions 1. |
| Negative/edge test | Executed isolated edge/security assertions: FormsEngineTest::test_drafts_freeze_old_versions_and_stale_builder_posts_conflict; FormsEngineTest::test_conditional_logic_rejects_cycles_and_depth_greater_than_three |
| Browser/UI evidence | staff-page-student-forms (2026-10-06T22:49:54.151Z); student-form-submitted-v2 (2026-10-06T23:04:12.459Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | PRELMS-004 |
| Fix commit SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Deployed fix SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Retest | Explicit completed-response setPrivate plus no-store; FilterAwareExports16/368 and actual CSV/XLSX private/no-store semantic parity pass. |
| Authoritative current source | [app/Http/Controllers/Admin/FormController.php](app/Http/Controllers/Admin/FormController.php)<br>[app/Domains/Forms/Services/FormSubmissionQuery.php](app/Domains/Forms/Services/FormSubmissionQuery.php) |

### SA-072 — Live Pulse, primary/resource/game funnels, goals and acquisition reports

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Analytics/funnels |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Analytics/funnels. Live Pulse, primary/resource/game funnels, goals and acquisition reports. Funnels use coherent session/cohort identity and maturation/reconciliation semantics rather than adding unrelated event counts. |
| Business rules | Funnels use coherent session/cohort identity and maturation/reconciliation semantics rather than adding unrelated event counts. Live visitors, bounce trend, goal conversion and acquisition preserve internal/preview/bot exclusion and historical authority boundaries. No financial retention prediction or fake conversion repair. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [PUB-024](#pub-024), [SA-075](#sa-075), [SA-077](#sa-077) |
| QA scenarios | Q11 |
| Actual fixture/reference | visitor_sessions:189, visitors:163, analytics_events:1174, analytics_events:1175, analytics_events:1176, analytics_events:1177, analytics_events:1178, analytics_events:1179, analytics_events:1180, analytics_events:1181, analytics_events:1182, analytics_events:1183, analytics_events:1184, analytics_events:1185, analytics_events:1186, marketing_touches:13, visitor_funnel_progressions:60 |
| Positive test | Executed isolated mutation/projection assertions: AnalyticsAndReportsTest::test_admin_can_view_analytics_dashboard_with_funnels; AnalyticsAndReportsTest::test_admin_can_view_the_five_fixed_reports; production HTTP assertions 1. |
| Negative/edge test | Executed isolated edge/security assertions: AnalyticsAndReportsTest::test_client_analytics_event_endpoint_rejects_server_only_events; AnalyticsAndReportsTest::test_event_deduplication_prevents_rapid_fire_booking_cta_retries |
| Browser/UI evidence | staff-page-analytics (2026-10-06T22:50:29.585Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | PRELMS-009 |
| Fix commit SHA | 4296b21368cbc9c564d0cb7a85e29b2ae408e0ee |
| Deployed fix SHA | 4296b21368cbc9c564d0cb7a85e29b2ae408e0ee |
| Retest | Meaningful rendered-anchor red case; final internal/external regressions2/20 pass. Current production internal markup has no client handler, external retains both events, destination returns200, invalid/nested/Coming Soon422/422/404. Unique server-API campaign has exactly one game_opened; QA target restored. Browser postfix click constrained by tool outage. |
| Authoritative current source | [app/Http/Controllers/Admin/AnalyticsDashboardController.php](app/Http/Controllers/Admin/AnalyticsDashboardController.php)<br>[app/Domains/Analytics/Services/AnalyticsService.php](app/Domains/Analytics/Services/AnalyticsService.php) |

### SA-073 — Country traffic, conversion, flags and sorting/export

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Country analytics |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Country analytics. Country traffic, conversion, flags and sorting/export. Detected country is distinct from customer timezone and language. |
| Business rules | Detected country is distinct from customer timezone and language. Friendly country labels/flags plus Unknown/world fallback; distinct visitor/session and numerator/denominator conversions stay canonical. Client-supplied country does not redefine trusted attribution or infer manual-booking country. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [PUB-024](#pub-024), [SA-072](#sa-072), [SA-077](#sa-077) |
| QA scenarios | Q11 |
| Actual fixture/reference | visitor_sessions:189, visitors:163, analytics_events:1174, analytics_events:1175, analytics_events:1176, analytics_events:1177, analytics_events:1178, analytics_events:1179, analytics_events:1180, analytics_events:1181, analytics_events:1182, analytics_events:1183, analytics_events:1184, analytics_events:1185, analytics_events:1186, marketing_touches:13, visitor_funnel_progressions:60 |
| Positive test | Executed isolated mutation/projection assertions: CairoDailyAnalyticsRollupTest::test_daily_metrics_and_traffic_report_and_export_totals_identical; CairoDailyAnalyticsRollupTest::test_report_service_traffic_report_groups_strictly_by_cairo_calendar_date; production HTTP assertions 6. |
| Negative/edge test | Executed isolated edge/security assertions: CairoDailyAnalyticsRollupTest::test_pruned_source_filtered_traffic_report_prevents_unfiltered_rollup_contamination; CountryInitialLocaleTest::test_untrusted_country_header_cannot_choose_language |
| Browser/UI evidence | staff-page-country-analytics (2026-10-06T22:50:31.145Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Actual country CSV/XLSX files are parsed and equivalent for the offered all-country/date scope. An unsupported country=EG query does not filter this report and is not claimed as such. Exact later screen/file reconciliation is constrained by connectivity. |
| Defect IDs | PRELMS-004 |
| Fix commit SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Deployed fix SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Retest | Explicit completed-response setPrivate plus no-store; FilterAwareExports16/368 and actual CSV/XLSX private/no-store semantic parity pass. |
| Authoritative current source | [app/Http/Controllers/Admin/AnalyticsDashboardController.php](app/Http/Controllers/Admin/AnalyticsDashboardController.php)<br>[app/Domains/Timezone/Services/TimezoneDisplayService.php](app/Domains/Timezone/Services/TimezoneDisplayService.php) |

### SA-074 — Section views, dwell, bounce/drop-off and filtering/export

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Attention analytics |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Attention analytics. Section views, dwell, bounce/drop-off and filtering/export. Section attention is observed engagement, not fabricated learning mastery. |
| Business rules | Section attention is observed engagement, not fabricated learning mastery. Repeated clicks/presence pings do not inflate views; validated section IDs and bounded dwell values preserve signal. Business-date historical rollups and current raw data retain exclusion rules. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [PUB-024](#pub-024), [PUB-023](#pub-023), [SA-072](#sa-072) |
| QA scenarios | Q11 |
| Actual fixture/reference | visitor_sessions:189, visitors:163, analytics_events:1174, analytics_events:1175, analytics_events:1176, analytics_events:1177, analytics_events:1178, analytics_events:1179, analytics_events:1180, analytics_events:1181, analytics_events:1182, analytics_events:1183, analytics_events:1184, analytics_events:1185, analytics_events:1186, marketing_touches:13, visitor_funnel_progressions:60 |
| Positive test | Executed isolated mutation/projection assertions: AnalyticsAndReportsTest::test_admin_can_export_csv_with_formula_injection_defense; AnalyticsAndReportsTest::test_admin_can_export_xlsx_report |
| Negative/edge test | Executed isolated edge/security assertions: AnalyticsAndReportsTest::test_client_analytics_event_endpoint_rejects_server_only_events; AnalyticsAndReportsTest::test_event_deduplication_prevents_rapid_fire_booking_cta_retries |
| Browser/UI evidence | staff-page-section-attention (2026-10-06T22:50:32.516Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | PRELMS-004 |
| Fix commit SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Deployed fix SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Retest | Explicit completed-response setPrivate plus no-store; FilterAwareExports16/368 and actual CSV/XLSX private/no-store semantic parity pass. |
| Authoritative current source | [app/Http/Controllers/Admin/AnalyticsDashboardController.php](app/Http/Controllers/Admin/AnalyticsDashboardController.php)<br>[resources/js/analytics-telemetry.js](resources/js/analytics-telemetry.js) |

### SA-075 — Traffic, booking, Resource, social, event and campaign reports/exports

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Operational reports |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Operational reports. Traffic, booking, Resource, social, event and campaign reports/exports. Screen/export share the same filters/readers. |
| Business rules | Screen/export share the same filters/readers. Social history preserves platform/placement/page/country/source/medium/campaign/language/context after raw pruning; semantic dedupe avoids double click counts. Data meanings/cutover and distinct visitors are retained even when explanatory warning copy is absent. No raw IP or staff credentials in ordinary exports. Durable daily_social_metrics retains business-day/timezone/dimension-group counts and daily group-distinct visitors. After raw pruning, period-distinct visitors are unavailable: daily uniques must not be summed and labeled period-distinct. Historical rollups are not relabeled into another timezone, unknown dimensions remain Unknown and no historical facts are fabricated. Aggregation must finish before raw pruning; rebuilding an already pruned day preserves its durable snapshot. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-072](#sa-072), [SA-073](#sa-073), [SA-074](#sa-074), [PUB-024](#pub-024), [SA-081](#sa-081) |
| QA scenarios | Q11 |
| Actual fixture/reference | contacts:11, contacts:12, contacts:14, contacts:15, contacts:13, contacts:16, visitor_sessions:189, visitors:163, analytics_events:1174, analytics_events:1175, analytics_events:1176, analytics_events:1177, analytics_events:1178, analytics_events:1179, analytics_events:1180, analytics_events:1181, analytics_events:1182, analytics_events:1183, analytics_events:1184, analytics_events:1185, analytics_events:1186, marketing_touches:13, resource_downloads:2, resource_downloads:3, visitor_funnel_progressions:60, resource_requests:4, resource_requests:5 |
| Positive test | Executed isolated mutation/projection assertions: AnalyticsAndReportsTest::test_bot_exclusion_across_events_and_reports; CairoDailyAnalyticsRollupTest::test_historical_traffic_report_survives_raw_event_pruning_and_serves_from_daily_metrics |
| Negative/edge test | Executed isolated edge/security assertions: AnalyticsAndReportsTest::test_event_deduplication_prevents_rapid_fire_booking_cta_retries; CairoDailyAnalyticsRollupTest::test_pruned_source_filtered_traffic_report_prevents_unfiltered_rollup_contamination |
| Browser/UI evidence | staff-page-operational-reports-exports (2026-10-06T22:50:38.978Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | PRELMS-004 |
| Fix commit SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Deployed fix SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Retest | Explicit completed-response setPrivate plus no-store; FilterAwareExports16/368 and actual CSV/XLSX private/no-store semantic parity pass. |
| Authoritative current source | [app/Http/Controllers/Admin/ReportController.php](app/Http/Controllers/Admin/ReportController.php)<br>[app/Domains/Reporting/Services/ExportService.php](app/Domains/Reporting/Services/ExportService.php)<br>[app/Domains/Reporting/Services/ReportPeriod.php](app/Domains/Reporting/Services/ReportPeriod.php) |

### SA-076 — Friendly maintenance visitor/session/country details and export

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Maintenance analytics |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Maintenance analytics. Friendly maintenance visitor/session/country details and export. Super Admin-only maintained one summary with friendly countries/flags and correct Business Timezone labels. |
| Business rules | Super Admin-only maintained one summary with friendly countries/flags and correct Business Timezone labels. Screen/export filter parity; compatibility health export alias is same capability, not abandoned separate business report. Maintenance traffic does not imply a completed booking. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [PUB-022](#pub-022), [SA-079](#sa-079), [SA-080](#sa-080) |
| QA scenarios | Q12 |
| Actual fixture/reference | Current configuration/private runtime checkpoints; seven setup gaps resolved/classified above |
| Positive test | Executed isolated mutation/projection assertions: StageOnePresentationTest::test_maintenance_analytics_has_one_summary_with_country_flags_and_actual_filtered_bounce_rate; MaintenanceModeResilienceTest::test_maintenance_mode_intercepts_public_traffic_and_records_visits; production HTTP assertions 3. |
| Negative/edge test | Executed isolated edge/security assertions: MaintenanceModeResilienceTest::test_unauthenticated_admin_auth_endpoints_bypass_maintenance; StageOnePresentationTest::test_assistant_shell_omits_destinations_denied_by_existing_role_middleware |
| Browser/UI evidence | staff-page-maintenance-mode (2026-10-06T22:50:33.425Z); public-controlled-maintenance (2026-10-07T03:48:20.656Z); maintenance-actual-visitor-report (2026-10-07T03:48:47.962Z); maintenance-filtered-browser-exports (2026-10-07T03:49:04.806Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | PRELMS-004 |
| Fix commit SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Deployed fix SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Retest | Explicit completed-response setPrivate plus no-store; FilterAwareExports16/368 and actual CSV/XLSX private/no-store semantic parity pass. |
| Authoritative current source | [app/Http/Controllers/Admin/MaintenanceAnalyticsController.php](app/Http/Controllers/Admin/MaintenanceAnalyticsController.php) |

### SA-077 — Branding, public copy, business timezone, booking defaults, counters and privacy goals

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / System/business configuration |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** System/business configuration. Branding, public copy, business timezone, booking defaults, counters and privacy goals. Public copy/sections and bilingual operations keep existing authorities. |
| Business rules | Public copy/sections and bilingual operations keep existing authorities. Valid IANA Business Timezone controls future date boundaries without rewriting old booking snapshots; individual SessionType/rule overrides remain separate. Counter source/window/templates and goal allowlist are finite; connection exclusion stores HMAC, not raw IP. Authenticated preview/home and about/preview use draft settings where present without publishing them; normal preview role/noindex boundaries apply. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [PUB-001](#pub-001), [PUB-020](#pub-020), [PUB-023](#pub-023), [PUB-024](#pub-024), [SA-045](#sa-045), [SA-047](#sa-047) |
| QA scenarios | Q08 |
| Actual fixture/reference | media:2, pages:4, faqs:6, social_links:7, blogs:2, games:4, games:5, promotions:2, promotions:3, promotions:4, page_translations:5, page_translations:6, resource_translations:4, resource_translations:5, page_translations:4, blog_slug_redirects:2, blog_revisions:2, faq_translations:6, game_translations:4, game_translations:5, content_revisions:5, content_revisions:6, content_revisions:7, content_revisions:8 |
| Positive test | Executed isolated mutation/projection assertions: EngagementCountersTest::test_public_counters_payload_uses_the_business_timezone_day_cache; EngagementCountersTest::test_admin_settings_updates_social_proof_counters; production HTTP assertions 1. |
| Negative/edge test | Executed isolated edge/security assertions: EngagementCountersTest::test_counter_draft_does_not_publish_and_publish_invalidates_public_cache; OperationalSettingsWorkflowTest::test_invalid_operational_values_do_not_partially_publish_other_settings |
| Browser/UI evidence | staff-page-settings-policies (2026-10-06T22:50:48.961Z); assistant-direct-settings-denied (2026-10-07T03:37:23.310Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Actual public/business/privacy settings and clocks are inspected. Settings writes/validation are isolated tests; genuine settings remain unchanged except approved, exactly restored maintenance state. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/SettingController.php](app/Http/Controllers/Admin/SettingController.php)<br>[app/Http/Controllers/HomeController.php](app/Http/Controllers/HomeController.php)<br>[app/Http/Controllers/PageController.php](app/Http/Controllers/PageController.php) |

### SA-078 — Configure emergency announcement audience, localized text, window, severity and CTA

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Emergency communication |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Emergency communication. Configure emergency announcement audience, localized text, window, severity and CTA. Separate save avoids accidental other setting writes. |
| Business rules | Separate save avoids accidental other setting writes. Supports Public or Public+Student audience, EN/FR/DE fallback and information/warning/urgent; SafeLessonUrl HTTPS CTA and unambiguous existent start/end local time. Disabled/empty/out-of-window is intentionally hidden. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [PUB-021](#pub-021), [SA-077](#sa-077), [PUB-003](#pub-003) |
| QA scenarios | Existing surface/current configuration |
| Actual fixture/reference | Current configuration/private runtime checkpoints; seven setup gaps resolved/classified above |
| Positive test | Executed isolated mutation/projection assertions: AnnouncementBannerTest::test_portal_respects_the_configured_audience with data set #0; AnnouncementBannerTest::test_portal_respects_the_configured_audience with data set #1 |
| Negative/edge test | Executed isolated edge/security assertions: AnnouncementBannerTest::test_assistants_and_guests_cannot_publish_announcements; AnnouncementBannerTest::test_invalid_configuration_is_rejected_atomically with data set #0 |
| Browser/UI evidence | staff-page-settings-policies (2026-10-06T22:50:48.961Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Disabled announcement configuration is inspected. Audience/CTA/window/localization/security mutations are isolated tests; no new emergency message was published. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/SettingController.php](app/Http/Controllers/Admin/SettingController.php)<br>[app/Domains/CMS/Services/AnnouncementService.php](app/Domains/CMS/Services/AnnouncementService.php) |

### SA-079 — Application Maintenance Mode switch and message

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Maintenance control |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Maintenance control. Application Maintenance Mode switch and message. Guest/public 503 while staff/admin/health stay accessible; application switch is distinct from Laravel deployment down mode. |
| Business rules | Guest/public 503 while staff/admin/health stay accessible; application switch is distinct from Laravel deployment down mode. Current flag is off; no maintenance configuration changed for inventory. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [PUB-022](#pub-022), [SA-076](#sa-076), [SA-077](#sa-077) |
| QA scenarios | Existing surface/current configuration |
| Actual fixture/reference | social_links:7 |
| Positive test | Executed isolated mutation/projection assertions: BackupAndMaintenanceTest::test_maintenance_mode_blocks_public_visitors_with_503; MaintenanceModeResilienceTest::test_maintenance_mode_intercepts_public_traffic_and_records_visits |
| Negative/edge test | Executed isolated edge/security assertions: MaintenanceModeResilienceTest::test_unauthenticated_admin_auth_endpoints_bypass_maintenance; BackupAndMaintenanceTest::test_cleanup_expired_holds_command_transitions_expired_holds |
| Browser/UI evidence | staff-page-settings-policies (2026-10-06T22:50:48.961Z); public-controlled-maintenance (2026-10-07T03:48:20.656Z); public-maintenance-restored (2026-10-07T03:48:36.695Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/SettingController.php](app/Http/Controllers/Admin/SettingController.php)<br>[app/Http/Middleware/CheckMaintenanceMode.php](app/Http/Middleware/CheckMaintenanceMode.php)<br>[app/Http/Controllers/Admin/SystemHealthController.php](app/Http/Controllers/Admin/SystemHealthController.php) |

### SA-080 — Read environment, clocks, storage and operational heartbeat health

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / System diagnostics |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** System diagnostics. Read environment, clocks, storage and operational heartbeat health. Diagnostics report actual healthy/stale/failure/unknown state rather than configuring services. |
| Business rules | Diagnostics report actual healthy/stale/failure/unknown state rather than configuring services. Existing production Log mail and s3_replication_failed warning remain; zero queued jobs is not proof of sustained worker health. No secret/key/token material is a capability-report value. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-081](#sa-081), [SA-076](#sa-076), [SA-085](#sa-085), [SA-077](#sa-077) |
| QA scenarios | Existing surface/current configuration |
| Actual fixture/reference | social_links:7 |
| Positive test | Executed isolated mutation/projection assertions: ProductionCookieSecurityTest::test_production_environment_sets_secure_cookies_with_lax_and_valid_lifetimes; ProductionCookieSecurityTest::test_https_request_in_any_environment_sets_secure_cookies |
| Negative/edge test | Executed isolated edge/security assertions: OperationalRemediationTest::test_dns_failure_is_retryable_and_disposable_domains_are_rejected; OperationalRemediationTest::test_long_form_autosave_rejects_cross_version_writes_and_resumes |
| Browser/UI evidence | staff-page-system-health (2026-10-06T22:50:49.814Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | PRELMS-002 |
| Fix commit SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Deployed fix SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Retest | Owner-authorized targeted override1.11.0 changes one lock entry; actual installed CLI,20JS tests,build and zero-advisory audits pass. |
| Authoritative current source | [app/Http/Controllers/Admin/SystemHealthController.php](app/Http/Controllers/Admin/SystemHealthController.php)<br>[routes/console.php](routes/console.php) |

### SA-081 — Create/list/download/delete positively managed private backups

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Backup management |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Backup management. Create/list/download/delete positively managed private backups. Only positively identified managed archives are listed/deleted/pruned; protected/manual/deployment/unknown artifacts survive. |
| Business rules | Only positively identified managed archives are listed/deleted/pruned; protected/manual/deployment/unknown artifacts survive. Raw full backups may contain credentials/private data and are not sanitized portable exports. Creation/retention share Stage 5 mutex. No ordinary web full-system restore route; legacy console restore/generated-trigger recovery needs separate reviewed drill, not portable round-trip certification. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-080](#sa-080), [SA-093](#sa-093), [SA-094](#sa-094), [SA-088](#sa-088) |
| QA scenarios | Q14 |
| Actual fixture/reference | visitor_sessions:189, visitors:163, analytics_events:1174, analytics_events:1175, analytics_events:1176, analytics_events:1177, analytics_events:1178, analytics_events:1179, analytics_events:1180, analytics_events:1181, analytics_events:1182, analytics_events:1183, analytics_events:1184, analytics_events:1185, analytics_events:1186, marketing_touches:13, visitor_funnel_progressions:60 |
| Positive test | Executed isolated mutation/projection assertions: BackupAndMaintenanceTest::test_super_admin_can_access_backups_dashboard_and_create_backup; BackupAndMaintenanceTest::test_backup_download_and_deletion; production HTTP assertions 2. |
| Negative/edge test | Executed isolated edge/security assertions: BackupAndMaintenanceTest::test_regular_admin_denied_access_to_backups; BackupAndMaintenanceTest::test_retention_policy_prunes_expired_backups |
| Browser/UI evidence | staff-page-backups-recovery (2026-10-06T22:50:50.811Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Protected native recovery archives are hash verified and managed-backup UI inspected. Managed create/delete and restore drill are isolated tests; no managed/genuine archive was deleted or production restore performed. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/BackupController.php](app/Http/Controllers/Admin/BackupController.php)<br>[app/Domains/System/Services/BackupService.php](app/Domains/System/Services/BackupService.php)<br>[app/Domains/System/Services/ManagedBackupCatalog.php](app/Domains/System/Services/ManagedBackupCatalog.php) |

### SA-082 — Safe role-restricted audit viewer and readable allowlisted diffs

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Audit/privacy |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Audit/privacy. Safe role-restricted audit viewer and readable allowlisted diffs. Super Admin only; raw IP, password/token/TOTP/recovery/free sensitive text do not render. |
| Business rules | Super Admin only; raw IP, password/token/TOTP/recovery/free sensitive text do not render. Actor kind/IDs and safe business/status changes remain readable; display redaction is separate from existing audit history and controlled privacy erasure. No arbitrary raw audit payload export. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-003](#sa-003), [SA-018](#sa-018), [SA-024](#sa-024) |
| QA scenarios | Q02 |
| Actual fixture/reference | administrators:6, administrators:7, administrators:8, administrators:9, entity_translation_revisions:11, entity_translation_revisions:12, entity_translation_revisions:13, entity_translation_revisions:18, entity_translation_revisions:19, entity_translation_revisions:20, entity_translation_revisions:21, entity_translation_revisions:22, audit_logs:196, audit_logs:161, audit_logs:162, audit_logs:163, audit_logs:164, audit_logs:165, audit_logs:166, audit_logs:167, audit_logs:168, audit_logs:169, audit_logs:170, audit_logs:171, audit_logs:172, audit_logs:173, audit_logs:174, audit_logs:175, audit_logs:176, audit_logs:177, audit_logs:178, audit_logs:179, audit_logs:180, audit_logs:181, audit_logs:182, audit_logs:183, audit_logs:184, audit_logs:185, audit_logs:186, audit_logs:187, audit_logs:188, audit_logs:189, audit_logs:190, audit_logs:191, audit_logs:192, audit_logs:193, audit_logs:194, audit_logs:195, audit_logs:197, audit_logs:198, audit_logs:199, audit_logs:200, audit_logs:201, audit_logs:202, audit_logs:203, audit_logs:204, audit_logs:205, audit_logs:206, audit_logs:207, audit_logs:208, audit_logs:209, audit_logs:210, audit_logs:211, audit_logs:212, audit_logs:213, audit_logs:214, audit_logs:215, audit_logs:216, audit_logs:217, audit_logs:218, audit_logs:219, audit_logs:220, audit_logs:221, audit_logs:222, audit_logs:223, audit_logs:224, audit_logs:225, audit_logs:226, audit_logs:227, audit_logs:228, audit_logs:229, audit_logs:230, audit_logs:231, audit_logs:239, audit_logs:240, audit_logs:241, audit_logs:242, audit_logs:243, audit_logs:244, audit_logs:245, audit_logs:246, audit_logs:247, audit_logs:248, audit_logs:249, audit_logs:157, audit_logs:158, audit_logs:159, audit_logs:160 |
| Positive test | Executed isolated mutation/projection assertions: BusinessLifecycleDataQualityTest::test_audit_viewer_never_renders_legacy_secrets_or_raw_ip_and_filters_operational_changes; AdministratorTwoFactorTest::test_safe_intended_staff_destination_is_preserved |
| Negative/edge test | Executed isolated edge/security assertions: AdministratorTwoFactorTest::test_other_staff_roles_cannot_access_any_security_operation with data set #0; AdministratorTwoFactorTest::test_other_staff_roles_cannot_access_any_security_operation with data set #1 |
| Browser/UI evidence | staff-page-security-audit-log (2026-10-06T22:50:52.703Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/SystemHealthController.php](app/Http/Controllers/Admin/SystemHealthController.php)<br>[app/Domains/Audit/Services/AuditLogPresentation.php](app/Domains/Audit/Services/AuditLogPresentation.php) |

### SA-083 — Read-only identity/purchase/configuration/relationship risks and bounded duplicate candidates

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Data quality |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Data quality. Read-only identity/purchase/configuration/relationship risks and bounded duplicate candidates. Samples bounded at 30; duplicate scan first 1000 eligible rows/kind and at most 100 pairs. |
| Business rules | Samples bounded at 30; duplicate scan first 1000 eligible rows/kind and at most 100 pairs. Same normalized name plus email or international phone is conservative signal; ambiguous national phone/anonymized identities excluded. Not exhaustive duplicate certification. Invalid policy/timezone/type flags do not silently overwrite settings; review links lead to authoritative actions. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-020](#sa-020), [SA-023](#sa-023), [SA-038](#sa-038), [SA-044](#sa-044), [SA-047](#sa-047), [SA-098](#sa-098) |
| QA scenarios | Q01, Q15 |
| Actual fixture/reference | students:7, students:8, students:9, students:10, students:11, students:12, student_packages:11, student_packages:12, student_packages:13, student_packages:14, student_packages:15, student_packages:16, student_packages:17, student_bins:3, audit_logs:196 |
| Positive test | Executed isolated mutation/projection assertions: BusinessLifecycleDataQualityTest::test_receivables_queries_stay_bounded_with_more_purchases_and_forecasts; BusinessLifecycleDataQualityTest::test_recurring_generation_is_bounded_per_booking_typed_and_replay_safe |
| Negative/edge test | Executed isolated edge/security assertions: BusinessLifecycleDataQualityTest::test_normalized_duplicate_candidates_are_conservative_and_read_only; BusinessLifecycleDataQualityTest::test_assistants_cannot_access_financial_configuration_or_data_quality_routes |
| Browser/UI evidence | staff-page-data-quality-center (2026-10-06T22:49:38.495Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Students/Services/DataQualityReadModel.php](app/Domains/Students/Services/DataQualityReadModel.php)<br>[app/Http/Controllers/Admin/DataQualityController.php](app/Http/Controllers/Admin/DataQualityController.php) |

### SA-084 — Existing bot/destination/global switches and controlled read-command settings

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Telegram configuration |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Telegram configuration. Existing bot/destination/global switches and controlled read-command settings. Token encrypted/hidden and blank edit preserves it; destination cannot move bots, same-bot chat identity unique. |
| Business rules | Token encrypted/hidden and blank edit preserves it; destination cannot move bots, same-bot chat identity unique. Optional today/tomorrow/student/stats commands require global+bot enable, bot allowlist and enabled trusted destination sender IDs; personal Student lookup only personal detail. Current bot commands disabled and global missing flag defaults false; inventory never polls/enables/modifies them. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-085](#sa-085), [SA-086](#sa-086), [SA-080](#sa-080) |
| QA scenarios | Q13 |
| Actual fixture/reference | telegram_deliveries:7, telegram_deliveries:8 |
| Positive test | Executed isolated mutation/projection assertions: TelegramAutomationTest::test_multiple_destinations_dedupe_and_summary_redaction; TelegramAutomationTest::test_interactive_commands_require_both_chat_and_user_allowlisting with data set #0; production HTTP assertions 2. |
| Negative/edge test | Executed isolated edge/security assertions: TelegramAutomationTest::test_cross_bot_destination_is_rejected_and_template_placeholder_is_validated; TelegramAutomationTest::test_unprivileged_roles_cannot_manage_telegram with data set #0 |
| Browser/UI evidence | staff-page-telegram-bots (2026-10-06T22:50:41.502Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Existing verified bot/destination and disabled inbound commands are inspected. Switch/command mutations pass isolated tests; no polling, commands, destination or bot credential change. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/TelegramController.php](app/Http/Controllers/Admin/TelegramController.php)<br>[app/Domains/Notifications/Services/TelegramCommandService.php](app/Domains/Notifications/Services/TelegramCommandService.php) |

### SA-085 — Rule templates, booking reminders, event alerts and on-demand digest requests

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Outbound Telegram |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Outbound Telegram. Rule templates, booking reminders, event alerts and on-demand digest requests. Catalog limits trigger modes/template variables/sections/conditions and destination ownership; priorities/cooldowns/quiet hours bound delivery. |
| Business rules | Catalog limits trigger modes/template variables/sections/conditions and destination ownership; priorities/cooldowns/quiet hours bound delivery. Only on_demand business_digest/analytics_digest can be manually run. Existing reminders read canonical UTC milestones/business clock and reveal-safe meeting URLs. No progress spam/new integration or recipient change is authorized here. Existing POST Preview with sample data validates trigger/template and renders catalog sample values, not private live records; 30/minute throttle. Preview is an active UI control. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-084](#sa-084), [SA-086](#sa-086), [SA-039](#sa-039), [SA-051](#sa-051), [SA-075](#sa-075) |
| QA scenarios | Q04, Q13 |
| Actual fixture/reference | booking_holds:21, bookings:13, bookings:14, session_ledger_entries:27, bookings:15, session_ledger_entries:28, bookings:16, session_ledger_entries:29, session_ledger_entries:30, bookings:17, session_ledger_entries:31, bookings:18, session_ledger_entries:32, bookings:19, session_ledger_entries:34, bookings:20, bookings:21, meeting_rooms:1, meeting_rooms:2, meeting_providers:4, bookings:22, session_ledger_entries:35, form_submissions:1, session_reschedules:2, booking_events:21, booking_events:22, booking_events:23, booking_events:24, booking_events:25, booking_events:26, booking_events:27, booking_events:28, booking_events:29, booking_events:30, booking_events:31, booking_events:32, booking_events:33, booking_events:34, booking_events:35, booking_events:36, form_answers:1, form_answers:2, form_answers:3, booking_policy_decisions:1, booking_policy_decisions:2, telegram_deliveries:7, booking_calendar_locks:40, booking_calendar_locks:43, booking_calendar_locks:51, booking_calendar_locks:52, telegram_deliveries:8 |
| Positive test | Executed isolated mutation/projection assertions: TelegramAutomationTest::test_business_digest_distinguishes_created_bookings_from_sessions_today; TelegramAutomationTest::test_booking_events_emit_only_after_commit_and_rolled_back_events_do_not_alert with data set #0; production HTTP assertions 2. |
| Negative/edge test | Executed isolated edge/security assertions: TelegramAutomationTest::test_each_switch_prevents_delivery with data set #0; TelegramAutomationTest::test_each_switch_prevents_delivery with data set #1 |
| Browser/UI evidence | staff-page-telegram-bots (2026-10-06T22:50:41.502Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Existing reminder rules/configuration and template/security branches pass isolated tests. No additional live reminder/digest/event message, scheduler or worker configuration is performed. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Notifications/Services/TelegramAutomationService.php](app/Domains/Notifications/Services/TelegramAutomationService.php)<br>[app/Domains/Notifications/Services/TelegramReadService.php](app/Domains/Notifications/Services/TelegramReadService.php)<br>[app/Console/Commands/TelegramTickCommand.php](app/Console/Commands/TelegramTickCommand.php)<br>[app/Http/Controllers/Admin/TelegramController.php](app/Http/Controllers/Admin/TelegramController.php)<br>[resources/views/admin/telegram/index.blade.php](resources/views/admin/telegram/index.blade.php) |

### SA-086 — Safe delivery history, enabled-destination test and one-off verified status pathway

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Telegram delivery |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Telegram delivery. Safe delivery history, enabled-destination test and one-off verified status pathway. Direct path reuses verified destination and deduplicated identity; never guess token/destination. |
| Business rules | Direct path reuses verified destination and deduplicated identity; never guess token/destination. History identifies actual sent/failure/uncertain, not inferred delivery. Tests require enabled bot+destination; uncertain interrupted delivery is not aggressively retried. Legacy Settings test is distinct existing compatibility path, not a reason to redesign integration. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-084](#sa-084), [SA-085](#sa-085) |
| QA scenarios | Q13 |
| Actual fixture/reference | telegram_deliveries:7, telegram_deliveries:8 |
| Positive test | Executed isolated mutation/projection assertions: OperationalRemediationTest::test_status_presenter_distinguishes_every_lifecycle_state; TelegramAutomationTest::test_multiple_destinations_dedupe_and_summary_redaction; production HTTP assertions 2. |
| Negative/edge test | Executed isolated edge/security assertions: TelegramAutomationTest::test_each_switch_prevents_delivery with data set #0; TelegramAutomationTest::test_each_switch_prevents_delivery with data set #1 |
| Browser/UI evidence | staff-page-telegram-bots (2026-10-06T22:50:41.502Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — The verified outbound pathway delivers the single START; FINAL delivery will be recorded once. Other live destination-test buttons are not used because exactly two stage messages are authorized. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/Notifications/Services/TelegramDeliveryService.php](app/Domains/Notifications/Services/TelegramDeliveryService.php)<br>[app/Http/Controllers/Admin/TelegramController.php](app/Http/Controllers/Admin/TelegramController.php)<br>[app/Http/Controllers/Admin/SettingController.php](app/Http/Controllers/Admin/SettingController.php) |

### SA-087 — Permanent feature-off boundary and production compatibility

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Development & Launch access |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Development & Launch access. Permanent feature-off boundary and production compatibility. Disabled-by-default backend/service flag returns 404 for current Super Admin and hides navigation; role/auth independently deny other actors. |
| Business rules | Disabled-by-default backend/service flag returns 404 for current Super Admin and hides navigation; role/auth independently deny other actors. Deployment verified schema, private recovery, JSON database sessions, mutex and managed-backup exclusions with tools still off. Merely reading this matrix/runbook does not authorize enable/reset. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-088](#sa-088), [SA-081](#sa-081), [SA-080](#sa-080) |
| QA scenarios | Q02, Q14 |
| Actual fixture/reference | administrators:6, administrators:7, administrators:8, administrators:9, entity_translation_revisions:11, entity_translation_revisions:12, entity_translation_revisions:13, entity_translation_revisions:18, entity_translation_revisions:19, entity_translation_revisions:20, entity_translation_revisions:21, entity_translation_revisions:22, audit_logs:161, audit_logs:162, audit_logs:163, audit_logs:164, audit_logs:165, audit_logs:166, audit_logs:167, audit_logs:168, audit_logs:169, audit_logs:170, audit_logs:171, audit_logs:172, audit_logs:173, audit_logs:174, audit_logs:175, audit_logs:176, audit_logs:177, audit_logs:178, audit_logs:179, audit_logs:180, audit_logs:181, audit_logs:182, audit_logs:183, audit_logs:184, audit_logs:185, audit_logs:186, audit_logs:187, audit_logs:188, audit_logs:189, audit_logs:190, audit_logs:191, audit_logs:192, audit_logs:193, audit_logs:194, audit_logs:195, audit_logs:197, audit_logs:198, audit_logs:199, audit_logs:200, audit_logs:201, audit_logs:202, audit_logs:203, audit_logs:204, audit_logs:205, audit_logs:206, audit_logs:207, audit_logs:208, audit_logs:209, audit_logs:210, audit_logs:211, audit_logs:212, audit_logs:213, audit_logs:214, audit_logs:215, audit_logs:216, audit_logs:217, audit_logs:218, audit_logs:219, audit_logs:220, audit_logs:221, audit_logs:222, audit_logs:223, audit_logs:224, audit_logs:225, audit_logs:226, audit_logs:227, audit_logs:228, audit_logs:229, audit_logs:230, audit_logs:231, audit_logs:239, audit_logs:240, audit_logs:241, audit_logs:242, audit_logs:243, audit_logs:244, audit_logs:245, audit_logs:246, audit_logs:247, audit_logs:248, audit_logs:249, audit_logs:157, audit_logs:158, audit_logs:159, audit_logs:160 |
| Positive test | Executed isolated mutation/projection assertions: DevelopmentLaunchDataToolsTest::test_enabled_super_admin_gets_preview_without_deletion; DevelopmentLaunchDataToolsTest::test_analytics_reset_preserves_configuration_and_idempotent_replay; production HTTP assertions 8. |
| Negative/edge test | Executed isolated edge/security assertions: DevelopmentLaunchDataToolsTest::test_disabled_tools_are_hidden_and_backend_denied; DevelopmentLaunchDataToolsTest::test_other_staff_roles_are_denied with data set #0 |
| Browser/UI evidence | development-default-off (2026-10-07T02:57:16.025Z); development-controlled-enable (2026-10-07T02:58:40.187Z); development-enabled-confirmed (2026-10-07T03:01:03.588Z); development-restored-off (2026-10-07T03:09:07.745Z); development-navigation-hidden-again (2026-10-07T03:09:08.382Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/System/Services/DevelopmentToolsAccess.php](app/Domains/System/Services/DevelopmentToolsAccess.php)<br>[config/development_tools.php](config/development_tools.php)<br>[app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php) |

### SA-088 — Immutable previews, password/factor/token confirmation, locks and safe recovery

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Development operation security |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Development operation security. Immutable previews, password/factor/token confirmation, locks and safe recovery. Five-minute preview, exact phrase and current password; fresh TOTP/unused recovery only when actor MFA enabled. |
| Business rules | Five-minute preview, exact phrase and current password; fresh TOTP/unused recovery only when actor MFA enabled. Owner/session/security/preview change invalidates; shared 600-second mutex excludes backup/reset competitors. Unknown dependent graph/cycle, unsupported scope/session/path or mandatory snapshot failure refuses. Confirmation rechecks locked records; completed replay returns result once with constraints/triggers enabled. |
| Prerequisites/dependencies | Separate server operator enablement and explicit destructive QA authorization; active Super Admin, matching current database/private paths, fresh preview and recovery. [SA-087](#sa-087), [SA-015](#sa-015), [SA-016](#sa-016), [SA-081](#sa-081), [SA-097](#sa-097) |
| QA scenarios | Q02, Q14 |
| Actual fixture/reference | administrators:6, administrators:7, administrators:8, administrators:9, entity_translation_revisions:11, entity_translation_revisions:12, entity_translation_revisions:13, entity_translation_revisions:18, entity_translation_revisions:19, entity_translation_revisions:20, entity_translation_revisions:21, entity_translation_revisions:22, audit_logs:161, audit_logs:162, audit_logs:163, audit_logs:164, audit_logs:165, audit_logs:166, audit_logs:167, audit_logs:168, audit_logs:169, audit_logs:170, audit_logs:171, audit_logs:172, audit_logs:173, audit_logs:174, audit_logs:175, audit_logs:176, audit_logs:177, audit_logs:178, audit_logs:179, audit_logs:180, audit_logs:181, audit_logs:182, audit_logs:183, audit_logs:184, audit_logs:185, audit_logs:186, audit_logs:187, audit_logs:188, audit_logs:189, audit_logs:190, audit_logs:191, audit_logs:192, audit_logs:193, audit_logs:194, audit_logs:195, audit_logs:197, audit_logs:198, audit_logs:199, audit_logs:200, audit_logs:201, audit_logs:202, audit_logs:203, audit_logs:204, audit_logs:205, audit_logs:206, audit_logs:207, audit_logs:208, audit_logs:209, audit_logs:210, audit_logs:211, audit_logs:212, audit_logs:213, audit_logs:214, audit_logs:215, audit_logs:216, audit_logs:217, audit_logs:218, audit_logs:219, audit_logs:220, audit_logs:221, audit_logs:222, audit_logs:223, audit_logs:224, audit_logs:225, audit_logs:226, audit_logs:227, audit_logs:228, audit_logs:229, audit_logs:230, audit_logs:231, audit_logs:239, audit_logs:240, audit_logs:241, audit_logs:242, audit_logs:243, audit_logs:244, audit_logs:245, audit_logs:246, audit_logs:247, audit_logs:248, audit_logs:249, audit_logs:157, audit_logs:158, audit_logs:159, audit_logs:160 |
| Positive test | Executed isolated mutation/projection assertions: DevelopmentLaunchDataToolsTest::test_unused_recovery_factor_is_consumed_without_export_or_audit_contents; DevelopmentLaunchDataToolsTest::test_enabled_super_admin_gets_preview_without_deletion |
| Negative/edge test | Executed isolated edge/security assertions: DevelopmentLaunchDataToolsTest::test_confirmation_rejects_missing_csrf_token_in_web_environment; DevelopmentLaunchDataToolsTest::test_wrong_confirmation_never_mutates_facts with data set #0 |
| Browser/UI evidence | development-qa-export-preview (2026-10-07T03:01:55.599Z); development-wrong-phrase-refused (2026-10-07T03:02:18.856Z); development-export-confirmed (2026-10-07T03:03:09.367Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Real safe export rejects wrong phrase and requires current password/fresh existing MFA. Reset locks/recovery/stale-token/snapshot-failure branches are isolated tests; no production reset. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/System/Services/DevelopmentToolsService.php](app/Domains/System/Services/DevelopmentToolsService.php)<br>[app/Domains/System/Services/DevelopmentToolsAccess.php](app/Domains/System/Services/DevelopmentToolsAccess.php)<br>[app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php) |

### SA-089 — Reviewed analytics-fact reset with retained business boundaries

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Analytics reset |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Analytics reset. Reviewed analytics-fact reset with retained business boundaries. RESET ALL ANALYTICS requires SA-088. |
| Business rules | RESET ALL ANALYTICS requires SA-088. Preserves content/settings/goals/exclusions/SocialLinks and business bookings; archived boundary prevents retained historical bookings refilling country/acquisition facts. Operational business reports retain history. No arbitrary source/config reset. |
| Prerequisites/dependencies | SA-088 separately enabled/authorized disposable scope and protected recovery. [SA-088](#sa-088), [SA-094](#sa-094), [SA-072](#sa-072), [SA-075](#sa-075) |
| QA scenarios | Q11, Q14 |
| Actual fixture/reference | visitor_sessions:189, visitors:163, analytics_events:1174, analytics_events:1175, analytics_events:1176, analytics_events:1177, analytics_events:1178, analytics_events:1179, analytics_events:1180, analytics_events:1181, analytics_events:1182, analytics_events:1183, analytics_events:1184, analytics_events:1185, analytics_events:1186, marketing_touches:13, visitor_funnel_progressions:60 |
| Positive test | Executed isolated mutation/projection assertions: DevelopmentLaunchDataToolsTest::test_analytics_reset_preserves_configuration_and_idempotent_replay; DevelopmentLaunchDataToolsTest::test_financial_reset_removes_complete_typed_cycle_and_keeps_students_and_free_lesson |
| Negative/edge test | Executed isolated edge/security assertions: DevelopmentLaunchDataToolsTest::test_analytics_reset_does_not_recollect_retained_bookings_or_leave_click_dedup_stale; DevelopmentLaunchDataToolsTest::test_wrong_confirmation_never_mutates_facts with data set #0 |
| Browser/UI evidence | No separate browser mutation certified. Current source/controller behavior is exercised by the named isolated cases; production fixture facts are inspected where available. |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Actual feature-off denial and isolated analytics reset boundaries pass. Production analytics are retained for review; no reset executed. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/System/Services/DevelopmentDataResetService.php](app/Domains/System/Services/DevelopmentDataResetService.php)<br>[app/Domains/System/Services/DevelopmentDataCatalog.php](app/Domains/System/Services/DevelopmentDataCatalog.php)<br>[app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php) |

### SA-090 — Reviewed all-Student ownership graph reset and auth cleanup

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Student reset |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Student reset. Reviewed all-Student ownership graph reset and auth cleanup. DELETE ALL STUDENTS preserves administrators/staff login, independent Contacts/leads, shared library/business definitions/configuration. |
| Business rules | DELETE ALL STUDENTS preserves administrators/staff login, independent Contacts/leads, shared library/business definitions/configuration. Unknown ownership refuses; linked audit/visitor state detached rather than shared system history deleted. Exact FK/typed cycles handled with enforcement enabled; IDs are not restarted. |
| Prerequisites/dependencies | SA-088; explicitly disposable complete scope, compatible database-backed JSON sessions/local private files and recovery. [SA-088](#sa-088), [SA-094](#sa-094), [SA-038](#sa-038), [SA-021](#sa-021), [SA-024](#sa-024) |
| QA scenarios | Q14 |
| Actual fixture/reference | Current configuration/private runtime checkpoints; seven setup gaps resolved/classified above |
| Positive test | Executed isolated mutation/projection assertions: DevelopmentDataConcurrencyTest::test_financial_reset_and_new_payment_leave_a_coherent_graph; DevelopmentLaunchDataToolsTest::test_analytics_reset_preserves_configuration_and_idempotent_replay |
| Negative/edge test | Executed isolated edge/security assertions: DevelopmentLaunchDataToolsTest::test_reset_services_refuse_unconfirmed_direct_invocation; DevelopmentLaunchDataToolsTest::test_required_snapshot_failure_refuses_reset_even_when_option_unchecked |
| Browser/UI evidence | No separate browser mutation certified. Current source/controller behavior is exercised by the named isolated cases; production fixture facts are inspected where available. |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — All-Student graph/auth cleanup is isolated-test coverage. Six QA Students and all genuine protected rows remain for review; no reset executed. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/System/Services/DevelopmentDataResetService.php](app/Domains/System/Services/DevelopmentDataResetService.php)<br>[app/Domains/System/Services/StudentSessionCleanupService.php](app/Domains/System/Services/StudentSessionCleanupService.php)<br>[app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php) |

### SA-091 — Coherent financial test-history reset with required booking dependents

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Financial reset |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Financial reset. Coherent financial test-history reset with required booking dependents. RESET FINANCIAL TEST HISTORY preserves Students and unrelated direct/free lessons. |
| Business rules | RESET FINANCIAL TEST HISTORY preserves Students and unrelated direct/free lessons. Funding snapshot/debit cycles detach atomically with FKs/triggers enabled, not partial null provenance. No fake PaymentRecords, remapped rights or balance patch; empty result UNINITIALIZED_DATASET honestly. |
| Prerequisites/dependencies | SA-088 and separately approved fully disposable financial/booking closure. [SA-088](#sa-088), [SA-094](#sa-094), [SA-096](#sa-096), [SA-038](#sa-038) |
| QA scenarios | Q14 |
| Actual fixture/reference | Current configuration/private runtime checkpoints; seven setup gaps resolved/classified above |
| Positive test | Executed isolated mutation/projection assertions: DevelopmentDataConcurrencyTest::test_financial_reset_and_new_payment_leave_a_coherent_graph; DevelopmentLaunchDataToolsTest::test_financial_reset_removes_complete_typed_cycle_and_keeps_students_and_free_lesson |
| Negative/edge test | Executed isolated edge/security assertions: DevelopmentLaunchDataToolsTest::test_required_snapshot_failure_refuses_reset_even_when_option_unchecked; DevelopmentLaunchDataToolsTest::test_analytics_reset_does_not_recollect_retained_bookings_or_leave_click_dedup_stale |
| Browser/UI evidence | No separate browser mutation certified. Current source/controller behavior is exercised by the named isolated cases; production fixture facts are inspected where available. |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Finance reset/dependent-booking history boundaries are isolated-test coverage. Production purchases/payments/refunds/ledger are retained; no reset executed. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/System/Services/DevelopmentDataResetService.php](app/Domains/System/Services/DevelopmentDataResetService.php)<br>[app/Domains/System/Services/DevelopmentDataCatalog.php](app/Domains/System/Services/DevelopmentDataCatalog.php)<br>[app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php) |

### SA-092 — Immutable selected Resources/Categories/history/files reset with safe detachment

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Resource reset |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Resource reset. Immutable selected Resources/Categories/history/files reset with safe detachment. RESET RESOURCE TEST DATA re-shows immutable scope; Categories default preserved. |
| Business rules | RESET RESOURCE TEST DATA re-shows immutable scope; Categories default preserved. Cannot delete referenced definitions while omitting required history/categories. Shared/unrelated private/public bytes stay; Files unchecked retains bytes, no unowned-file sweep. Restoring Resource does not automatically republish withdrawn lesson materials. |
| Prerequisites/dependencies | SA-088 and separately approved disposable Resource/reference/owned-byte scope. [SA-088](#sa-088), [SA-061](#sa-061), [SA-062](#sa-062), [SA-052](#sa-052), [SA-094](#sa-094), [SA-096](#sa-096) |
| QA scenarios | Q07, Q14 |
| Actual fixture/reference | resource_categories:3, resources:2, resources:3, lesson_materials:2, lesson_materials:3, lesson_materials:4, lesson_materials:5, resource_assignments:1, resource_assignments:2, resource_translations:4, contacts:11, contacts:12, contacts:14, contacts:15, contacts:13, contacts:16, resource_translations:2, resource_translations:3, category_translations:3, resource_downloads:2, resource_downloads:3, resource_requests:4, resource_requests:5, content_revisions:9 |
| Positive test | Executed isolated mutation/projection assertions: DevelopmentLaunchDataToolsTest::test_student_reset_removes_owned_files_but_preserves_lead_resource_and_staff_session; DevelopmentLaunchDataToolsTest::test_resource_reset_withdraws_shared_lesson_reference_preserves_categories_and_removes_private_payload |
| Negative/edge test | Executed isolated edge/security assertions: DevelopmentLaunchDataToolsTest::test_categories_cannot_reset_while_referencing_resources_are_preserved; DevelopmentLaunchDataToolsTest::test_reset_services_refuse_unconfirmed_direct_invocation |
| Browser/UI evidence | No separate browser mutation certified. Current source/controller behavior is exercised by the named isolated cases; production fixture facts are inspected where available. |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Resource/category/file/history reset boundaries are isolated-test coverage. Genuine alphabet and QA private payloads remain unchanged; no reset executed. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/System/Services/DevelopmentDataResetService.php](app/Domains/System/Services/DevelopmentDataResetService.php)<br>[app/Domains/System/Services/DevelopmentDataFiles.php](app/Domains/System/Services/DevelopmentDataFiles.php)<br>[app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php) |

### SA-093 — Reset only proven managed local snapshots and last-result lifecycle

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Managed backup reset |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Managed backup reset. Reset only proven managed local snapshots and last-result lifecycle. RESET MANAGED BACKUPS preserves retention/storage settings, manual/deployment/emergency/protected/unknown/unverifiable and offsite copies. |
| Business rules | RESET MANAGED BACKUPS preserves retention/storage settings, manual/deployment/emergency/protected/unknown/unverifiable and offsite copies. Ownership is proven, not filename guessed. Protected recovery precedes removal; next ordinary backup starts new lifecycle; no remote backup cleanup promise. |
| Prerequisites/dependencies | SA-088 and positively identified disposable managed archives/private local storage. [SA-088](#sa-088), [SA-081](#sa-081), [SA-094](#sa-094) |
| QA scenarios | Q14 |
| Actual fixture/reference | media:2, blogs:2, promotions:2, promotions:3, promotions:4, blog_slug_redirects:2, blog_revisions:2 |
| Positive test | Executed isolated mutation/projection assertions: DevelopmentLaunchDataToolsTest::test_backup_reset_removes_only_managed_and_protects_external_and_predeployment_archives; DevelopmentLaunchDataToolsTest::test_protected_snapshot_is_created_privately_outside_managed_reset_set |
| Negative/edge test | Executed isolated edge/security assertions: DevelopmentLaunchDataToolsTest::test_reset_services_refuse_unconfirmed_direct_invocation; DevelopmentLaunchDataToolsTest::test_required_snapshot_failure_refuses_reset_even_when_option_unchecked |
| Browser/UI evidence | No separate browser mutation certified. Current source/controller behavior is exercised by the named isolated cases; production fixture facts are inspected where available. |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Managed-only backup/reset/last-result lifecycle is isolated-test coverage. Protected native backups and all retained files are excluded from cleanup; no reset executed. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Domains/System/Services/ManagedBackupCatalog.php](app/Domains/System/Services/ManagedBackupCatalog.php)<br>[app/Domains/System/Services/DevelopmentDataResetService.php](app/Domains/System/Services/DevelopmentDataResetService.php)<br>[app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php) |

### SA-094 — Export Everything/Custom bounded private archive with module/filter closure

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Portable domain export |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Portable domain export. Export Everything/Custom bounded private archive with module/filter closure. EXPORT DEVELOPMENT DATA still requires secure confirmation/feature flag. |
| Business rules | EXPORT DEVELOPMENT DATA still requires secure confirmation/feature flag. Includes required parent closure, stable IDs, business timezone/UTC creation and payload hashes. Excludes staff/users/settings/APP_KEY/factor/session/challenge/Telegram credentials; contains selected private business data. Managed native snapshots convert to sanitized domain payload without executing SQL. Limits 50k rows/100 MiB ZIP/256 MiB expanded/10k entries. No arbitrary public Media import/export promise. |
| Prerequisites/dependencies | SA-088 separately enabled/authorized export scope and compatible private storage; export confirmation is nondeleting but sensitive. [SA-088](#sa-088), [SA-097](#sa-097), [SA-081](#sa-081), [SA-096](#sa-096) |
| QA scenarios | Q14 |
| Actual fixture/reference | resources:2, resources:3, lesson_materials:2, lesson_materials:3, lesson_materials:4, lesson_materials:5, pages:4, games:4, games:5, page_translations:5, page_translations:4, resource_translations:2, resource_translations:3, game_translations:4, game_translations:5, content_revisions:5, content_revisions:6, content_revisions:7, content_revisions:8, content_revisions:9 |
| Positive test | Executed isolated mutation/projection assertions: DevelopmentDataArchiveTest::test_student_filtered_export_only_includes_matching_profiles; DevelopmentDataArchiveTest::test_full_export_manifest_hashes_and_credentials_exclusion |
| Negative/edge test | Executed isolated edge/security assertions: DevelopmentDataArchiveTest::test_export_remains_readable_when_optional_git_metadata_cannot_be_collected; DevelopmentDataArchiveTest::test_selective_restore_refuses_missing_parent_module |
| Browser/UI evidence | development-controlled-enable (2026-10-07T02:58:40.187Z); development-enabled-confirmed (2026-10-07T03:01:03.588Z); development-qa-export-preview (2026-10-07T03:01:55.599Z); development-wrong-phrase-refused (2026-10-07T03:02:18.856Z); development-export-confirmed (2026-10-07T03:03:09.367Z); development-real-zip-download (2026-10-07T03:03:24.198Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Real selected QA-only portable export/download/closure/hash/secret exclusion succeeds on Hostinger with proc_open disabled. Export Everything/limits/all-module selection is isolated-test coverage. |
| Defect IDs | PRELMS-003 |
| Fix commit SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Deployed fix SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Retest | Actual disable_functions red/green regression; real5089-byte production ZIP and no-op import succeed with truthful null application_sha. |
| Authoritative current source | [app/Domains/System/Services/DevelopmentDataArchiveService.php](app/Domains/System/Services/DevelopmentDataArchiveService.php)<br>[app/Domains/System/Services/DevelopmentDataCatalog.php](app/Domains/System/Services/DevelopmentDataCatalog.php)<br>[app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php) |

### SA-095 — Upload inspection-only compatible portable archive and conflict preview

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Archive inspection |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Archive inspection. Upload inspection-only compatible portable archive and conflict preview. Upload token alone cannot authorize restore. |
| Business rules | Upload token alone cannot authorize restore. Traversal/symlink/duplicate/unmanifested entries/unknown columns/credential fields/unsupported version/size/hash and collation identity conflicts refuse; safe selected-module preview is a separate step. Archive paths are never extracted as destinations. |
| Prerequisites/dependencies | SA-087 separate enablement/authorized compatible test ZIP and current active Super Admin. [SA-094](#sa-094), [SA-096](#sa-096), [SA-088](#sa-088) |
| QA scenarios | Q14 |
| Actual fixture/reference | Current configuration/private runtime checkpoints; seven setup gaps resolved/classified above |
| Positive test | Executed isolated mutation/projection assertions: DevelopmentDataArchiveTest::test_import_upload_is_inspection_only_and_requires_selected_preview; DevelopmentDataArchiveTest::test_student_filtered_export_only_includes_matching_profiles |
| Negative/edge test | Executed isolated edge/security assertions: DevelopmentDataArchiveTest::test_conflicting_profile_is_reported_and_never_overwritten; DevelopmentDataArchiveTest::test_corrupt_payload_and_incompatible_manifest_are_rejected |
| Browser/UI evidence | development-import-inspection (2026-10-07T03:04:10.410Z); development-selected-import-preview (2026-10-07T03:04:38.443Z); development-negative-incompatible (2026-10-07T03:06:44.222Z); development-negative-secret-field (2026-10-07T03:06:46.261Z); development-difference-preview (2026-10-07T03:07:25.651Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | PRELMS-003 |
| Fix commit SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Deployed fix SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Retest | Actual disable_functions red/green regression; real5089-byte production ZIP and no-op import succeed with truthful null application_sha. |
| Authoritative current source | [app/Domains/System/Services/DevelopmentToolsService.php](app/Domains/System/Services/DevelopmentToolsService.php)<br>[app/Domains/System/Services/DevelopmentDataArchiveService.php](app/Domains/System/Services/DevelopmentDataArchiveService.php)<br>[app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php) |

### SA-096 — Restore missing/skip identical/refuse differences with stable provenance

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Selective restore |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Selective restore. Restore missing/skip identical/refuse differences with stable provenance. RESTORE MISSING DEVELOPMENT DATA requires separate selected preview and SA-088. |
| Business rules | RESTORE MISSING DEVELOPMENT DATA requires separate selected preview and SA-088. Identical rows skip; changed rows/unique identities/config dependencies/missing parent modules refuse, no arbitrary overwrite or ID remap. Typed cyclic Booking staging/original debit/full snapshot restored with constraints enabled; file writes compensate on rollback. Resource restore does not auto-re-share withdrawn materials; unsupported raw SQL conversion refuses. |
| Prerequisites/dependencies | SA-088 matching identities/schema/configuration/private roots and explicit compatible selected preview; separate restore authorization. [SA-095](#sa-095), [SA-094](#sa-094), [SA-088](#sa-088), [SA-038](#sa-038), [SA-052](#sa-052) |
| QA scenarios | Q14 |
| Actual fixture/reference | Current configuration/private runtime checkpoints; seven setup gaps resolved/classified above |
| Positive test | Executed isolated mutation/projection assertions: DevelopmentDataArchiveTest::test_restore_missing_is_selective_and_repeated_archive_skips_identical_rows; DevelopmentDataArchiveTest::test_private_lesson_file_restores_to_generated_private_path_with_identical_hash |
| Negative/edge test | Executed isolated edge/security assertions: DevelopmentDataArchiveTest::test_selective_restore_refuses_missing_parent_module; DevelopmentDataArchiveTest::test_native_snapshot_parser_handles_comments_and_refuses_unsupported_insert_format |
| Browser/UI evidence | development-import-inspection (2026-10-07T03:04:10.410Z); development-selected-import-preview (2026-10-07T03:04:38.443Z); development-identical-import-completed (2026-10-07T03:04:50.577Z); development-negative-incompatible (2026-10-07T03:06:44.222Z); development-negative-secret-field (2026-10-07T03:06:46.261Z); development-difference-preview (2026-10-07T03:07:25.651Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Real compatible import skips one identical profile with zero restored rows/files; conflicting overwrite and incompatible/secret archives are refused. Restore-missing/financial provenance is isolated-test coverage; no missing production row was fabricated. |
| Defect IDs | PRELMS-003 |
| Fix commit SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Deployed fix SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Retest | Actual disable_functions red/green regression; real5089-byte production ZIP and no-op import succeed with truthful null application_sha. |
| Authoritative current source | [app/Domains/System/Services/DevelopmentDataImportService.php](app/Domains/System/Services/DevelopmentDataImportService.php)<br>[app/Domains/System/Services/DevelopmentToolsService.php](app/Domains/System/Services/DevelopmentToolsService.php)<br>[app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php) |

### SA-097 — Owner operation summary, private hash-verified download and manual retention

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Operation history/recovery |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Operation history/recovery. Owner operation summary, private hash-verified download and manual retention. Owner-only operation 404 for foreign ID; download requires completed private export path/payload hash and current feature flag. |
| Business rules | Owner-only operation 404 for foreign ID; download requires completed private export path/payload hash and current feature flag. Protected full raw recovery has no portable download route and may contain credential state. At most 20 unexpired previews per actor; no automatic artifact cleanup scheduler or reset of IDs. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-088](#sa-088), [SA-094](#sa-094), [SA-095](#sa-095), [SA-096](#sa-096) |
| QA scenarios | Q02, Q14 |
| Actual fixture/reference | administrators:6, administrators:7, administrators:8, administrators:9, entity_translation_revisions:11, entity_translation_revisions:12, entity_translation_revisions:13, entity_translation_revisions:18, entity_translation_revisions:19, entity_translation_revisions:20, entity_translation_revisions:21, entity_translation_revisions:22, audit_logs:161, audit_logs:162, audit_logs:163, audit_logs:164, audit_logs:165, audit_logs:166, audit_logs:167, audit_logs:168, audit_logs:169, audit_logs:170, audit_logs:171, audit_logs:172, audit_logs:173, audit_logs:174, audit_logs:175, audit_logs:176, audit_logs:177, audit_logs:178, audit_logs:179, audit_logs:180, audit_logs:181, audit_logs:182, audit_logs:183, audit_logs:184, audit_logs:185, audit_logs:186, audit_logs:187, audit_logs:188, audit_logs:189, audit_logs:190, audit_logs:191, audit_logs:192, audit_logs:193, audit_logs:194, audit_logs:195, audit_logs:197, audit_logs:198, audit_logs:199, audit_logs:200, audit_logs:201, audit_logs:202, audit_logs:203, audit_logs:204, audit_logs:205, audit_logs:206, audit_logs:207, audit_logs:208, audit_logs:209, audit_logs:210, audit_logs:211, audit_logs:212, audit_logs:213, audit_logs:214, audit_logs:215, audit_logs:216, audit_logs:217, audit_logs:218, audit_logs:219, audit_logs:220, audit_logs:221, audit_logs:222, audit_logs:223, audit_logs:224, audit_logs:225, audit_logs:226, audit_logs:227, audit_logs:228, audit_logs:229, audit_logs:230, audit_logs:231, audit_logs:239, audit_logs:240, audit_logs:241, audit_logs:242, audit_logs:243, audit_logs:244, audit_logs:245, audit_logs:246, audit_logs:247, audit_logs:248, audit_logs:249, audit_logs:157, audit_logs:158, audit_logs:159, audit_logs:160 |
| Positive test | Executed isolated mutation/projection assertions: DevelopmentDataArchiveTest::test_download_is_owner_only_private_and_hash_verified; DevelopmentDataArchiveTest::test_full_export_manifest_hashes_and_credentials_exclusion; production HTTP assertions 6. |
| Negative/edge test | Executed isolated edge/security assertions: DevelopmentDataArchiveTest::test_export_remains_readable_when_optional_git_metadata_cannot_be_collected; DevelopmentDataArchiveTest::test_conflicting_profile_is_reported_and_never_overwritten |
| Browser/UI evidence | development-export-confirmed (2026-10-07T03:03:09.367Z); development-real-zip-download (2026-10-07T03:03:24.198Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | PRELMS-003 |
| Fix commit SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Deployed fix SHA | addda6f227f05f206db24a6f1adc1c1eb70ee1f9 |
| Retest | Actual disable_functions red/green regression; real5089-byte production ZIP and no-op import succeed with truthful null application_sha. |
| Authoritative current source | [app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php)<br>[app/Domains/System/Models/DevelopmentDataOperation.php](app/Domains/System/Models/DevelopmentDataOperation.php) |

### SA-098 — Trusted-console explicit legacy entitlement inventory/classification

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Historical typed review |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Historical typed review. Trusted-console explicit legacy entitlement inventory/classification. Never infer rights from name/price/minutes. |
| Business rules | Never infer rights from name/price/minutes. Manifest requires purchase/fingerprint/offering/type/reviewer/evidence, coherent grants/nonnegative balance/exact restorations and explicit null-expiry validity terms. Changed/different/replayed review and owner mismatch refuse; mixed allocation remapping is not arbitrary import. It is a trusted-console capability, not Super Admin web access or automatic Data Quality repair. |
| Prerequisites/dependencies | Separately authorized trusted server console and reviewed legacy manifest; ordinary web session does not authorize --apply. [SA-038](#sa-038), [SA-083](#sa-083), [SA-027](#sa-027) |
| QA scenarios | Q15 |
| Actual fixture/reference | Current configuration/private runtime checkpoints; seven setup gaps resolved/classified above |
| Positive test | Executed isolated mutation/projection assertions: TypedEntitlementsTest::test_legacy_debits_remain_reversible_without_classification; TypedEntitlementsTest::test_minutes_are_explanatory_and_do_not_select_entitlements |
| Negative/edge test | Executed isolated edge/security assertions: TypedEntitlementsTest::test_two_one_hour_rights_cannot_pay_for_a_two_hour_lesson; TypedEntitlementsTest::test_selection_skips_incompatible_expired_cancelled_and_unclassified_history |
| Browser/UI evidence | No separate browser mutation certified. Current source/controller behavior is exercised by the named isolated cases; production fixture facts are inspected where available. |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PARTIAL |
| Classification/limit | TEST / TOOLING LIMITATION — Actual trusted console inventories zero legacy-unclassified purchases and rejects --apply without a reviewed manifest. Explicit classification/legacy protection is isolated typed-entitlement coverage; historical intermediate MigrationA tests are not part of the current-schema gate. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Console/Commands/ReviewEntitlementMapping.php](app/Console/Commands/ReviewEntitlementMapping.php)<br>[app/Domains/Students/Services/EntitlementMappingService.php](app/Domains/Students/Services/EntitlementMappingService.php) |

### SA-099 — Enabled/default payment methods and immutable receipt labels

| Field | Evidence / disposition |
|---|---|
| Audience/domain | SUPER ADMIN / Payment method configuration |
| Expected behavior | **Audience:** SUPER ADMIN. **Domain:** Payment method configuration. Enabled/default payment methods and immutable receipt labels. Keep an enabled default/at least one active method when changing defaults; disabled method cannot receive new payment. |
| Business rules | Keep an enabled default/at least one active method when changing defaults; disabled method cannot receive new payment. Historical payment method text and genuine reference remain immutable after rename/disable. No gateway credentials or automatic transaction execution configured by this screen. |
| Prerequisites/dependencies | Active signed-in Super Admin; current MFA session proof when enabled. [SA-029](#sa-029), [SA-035](#sa-035), [STU-025](#stu-025) |
| QA scenarios | Q03 |
| Actual fixture/reference | payment_methods:7, student_packages:11, student_package_entitlements:5, session_ledger_entries:19, student_packages:12, student_package_entitlements:6, session_ledger_entries:20, student_packages:13, student_package_entitlements:7, session_ledger_entries:21, student_packages:14, student_package_entitlements:8, session_ledger_entries:22, student_packages:15, student_package_entitlements:9, session_ledger_entries:23, student_packages:16, student_package_entitlements:10, session_ledger_entries:24, student_packages:17, student_package_entitlements:11, session_ledger_entries:25, payment_records:13, payment_records:14, payment_records:15, payment_records:16, payment_refunds:4, payment_records:17, payment_refunds:5, package_installments:1, package_installments:2, package_renewals:1, student_packages:18, student_package_entitlements:12, session_ledger_entries:26, student_packages:19, student_package_entitlements:13, session_ledger_entries:33, payment_records:18, session_ledger_entries:36, payment_refunds:6, session_ledger_entries:37, student_packages:20, student_package_entitlements:14, session_ledger_entries:38 |
| Positive test | Executed isolated mutation/projection assertions: BusinessLifecycleDataQualityTest::test_financial_screen_export_and_receipt_are_actual_and_owner_scoped; BusinessLifecycleDataQualityTest::test_archive_excludes_default_roster_and_followups_but_preserves_booked_lessons_and_finance |
| Negative/edge test | Executed isolated edge/security assertions: BusinessOperationsControlsTest::test_assistants_and_students_cannot_manage_payment_methods_meeting_pools_or_validity; BusinessLifecycleDataQualityTest::test_installment_forecasts_reconcile_partial_payment_refunds_and_overpayment |
| Browser/UI evidence | staff-page-payment-methods (2026-10-06T22:50:40.706Z) |
| Persisted-state evidence | Original fixture references below remain the starting authority; fresh scoped read 2026-10-07T05:25:00+00:00 and domain/native integrity checkpoints 2026-10-07T05:24:52+00:00 / 2026-10-07T05:24:54+00:00. Final checkpoint verifies original genuine rows, policy/UTC and typed funding integrity. |
| Security/authorization | Named isolated cases execute current authorization/validation branches; actual A/B/E/F foreign lesson/material/note/homework/assignment/notification/statement/receipt and role denials are logged in production-http-evidence.json. This shared boundary proof is not a claim that every destructive branch ran on production. |
| Final disposition | PASS |
| Classification/limit | EXPECTED BUSINESS RULE — Current documented behavior accepted using the listed production observations/facts and executed functional tests; isolated branches are identified explicitly. |
| Defect IDs | None |
| Fix commit SHA | None |
| Deployed fix SHA | None |
| Retest | No application defect established; current functional cases passed. Production coverage limits are explicit above. |
| Authoritative current source | [app/Http/Controllers/Admin/PaymentMethodController.php](app/Http/Controllers/Admin/PaymentMethodController.php) |


## Closeout access limitation

Production connectivity recovered; QA Game4 original target and Available status are restored through the normal authenticated controller. Its real browser game_started1822/game_completed1830 receipts show5/5. The earlier duplicate opening pair1819/1821 is preserved as historical QA evidence, not deleted. Postfix campaign PRE_LMS_GAME_009_20261007 records one server opening and no accepted negative start. Browser control remains unavailable after the connection stall; this is explicit tooling limitation. No original cookie expiry was extended; QA session closeout is recorded separately before delivery. A fresh GUI click after PRELMS-009 and exact original browser-cookie restoration are not falsely claimed.

QA browser closeout: normal server logout succeeded at2026-10-07T05:21:50.162438+00:00. Browser control did not recover, so exact original browser-cookie restoration is not claimed and no expiry was extended. The genuine owner credentials,remember token and protected account state remain unchanged; owner review can use normal sign-in. Agent-created temporary tabs remain subject to normal tool/app cleanup.

## Final exploratory closeout

After all 153 IDs were reconciled, a final production server walkthrough revisited About/FAQ/Privacy/Terms/pricing, Student A dashboard/completed tutor feedback, and QA Peer Super Today/health/data-quality/default-off tools. All normal responses and expected 404 passed; the peer session was signed out. The stalled browser prevented a fresh final GUI replay, so this is explicitly server/API evidence and does not replace the earlier real browser walkthrough, screenshots and action receipts. A pricing assertion initially expected a literal USD label, while the accepted page presents dollar pricing/coaching text; the corrected semantic check passed. All three incorrect final page-text expectations remain tooling records, not application failures.
