# Public Content and Blog Implementation Report

Date: 9 October 2026  
Status: **APPROVED LOCAL SCOPE COMPLETE — HELD ITEMS AND BASELINE CHECK LIMITS RECORDED**  
Application implementation: **COMPLETE ON THE DEDICATED LOCAL BRANCH; NO DEPLOYMENT**  
Discovery baseline: `main`, commit `73e482d2e156eb5242a07f127f0ce62909829e9b`

The owner approved D1–D5 and the public author name Abdallah on 8 October 2026, with the constraints recorded below. The discovery findings are retained as the historical baseline; their pending-decision wording does not supersede this approval. The owner subsequently approved the separate concrete financial timing plan before any financial logic changes. The workbook and unverified contact/social destinations remain held. No production action is authorized.

## Implementation progress and current evidence

### Approved scope and safety

- D1 approved: standalone Tiptap Blog editor, minimal schema additions and an HTML source/preservation path.
- D2: authoritative completion-based diagnostic window and full-receipt-based validity confirmed; local implementation authorized by the owner’s final approval of [the financial plan](C:/Users/e/Herd/ArabicwAbdallah/FINANCIAL_TIMING_CORRECTION_PLAN.md). Policy 1.0, the full-refund wording and online-rescheduling/direct-contact distinction are approved.
- D3 approved: narrow public visibility controls; hide identified QA choices/resource/banner without removing history or authorized access.
- D4 approved: English first, honest fallback/unavailable states; no unsupplied French/German translations.
- D5 approved: Blogs/Articles terminology, Games V3 wording, planned videos, required name/email resource access, FAQ grouping/search and deferral of additional resource/game discovery controls.
- D6: public author Abdallah approved; workbook and unverified destinations held.

Before editing an application file, created and verified `codex/public-copy-blog-v3` from `73e482d2e156eb5242a07f127f0ce62909829e9b`. `git branch --show-current` confirms the dedicated branch. It has no upstream; `git ls-remote --heads origin refs/heads/codex/public-copy-blog-v3` returns no branch. There is no `.github` deployment workflow directory, no active local Git hook and no configured `core.hooksPath`. `php artisan env --no-interaction` reports LOCAL; the configured application database is `bolt_landing` on `127.0.0.1`. Kept entirely local with no pushes or deployment commands, these changes cannot trigger a remote production release. Hosting-provider deployment settings were not inspected or altered.

No deployment, merge, push, production migration, production data/configuration change or release has occurred. The three pre-existing untracked reports remain untouched. `.ai/rules` remains absent. No new sub-agents or unrelated documentation have been created.

### Stage receipts

| Stage | Status | Actual evidence / remaining work |
| --- | --- | --- |
| 0 — safe baseline | Complete | Dedicated local branch, no upstream/remote counterpart, no active local deployment hook; local app environment confirmed |
| Financial final plan | Approved; implemented locally | Owner replied “Approve this financial plan”; 15 timing tests pass, including exact boundaries, historical rights, chronological receipts/refunds and ambiguous Cairo clocks |
| 1–7 — approved implementation | Complete within approved scope | Copy, visibility, Policy 1.0, FAQ administration, Blog editor/publishing/locales/SEO and approved financial clocks implemented locally; workbook and destinations remain held |
| 8 — regression/acceptance | Complete; existing full-suite limits disclosed | 241 scoped Laravel tests, 1,830 assertions, 78.850 s; archive/Blog follow-up: 55 tests, 331 assertions, 32.946 s; 18 JavaScript tests pass. Build/formatter/diff checks pass. Final full run: 1,342/1,468 pass, with the same 72 failures/54 errors as the original code and zero new unsuccessful cases. Static analysis: 250 existing issues versus 255 originally, zero new issues |

### Completed local implementation receipts

- Local visibility/FAQ migration completed in 137.76 ms; additive Blog schema migration completed in 283.64 ms. A local follow-up migration completed in 170.80 ms, giving each of the three Blog relationship tables its own row ID while retaining unique article/target pairs and existing foreign keys. This matches the resource ownership/archive graph’s single-column identity requirement. No production migration ran.
- Public visibility controls, approved page copy, FAQ grouping/search, English fallback notices and Policy 1.0 are implemented. Assigned student access survives public resource hiding; the booking service rejects hidden lesson types.
- The approved content importer is transaction guarded to local/testing, archives existing wording, keeps historical FAQ rows and preserves unrelated Terms/Privacy. Local dry run completed, followed by a successful import: changed=true, 34 FAQs, 2 hidden lesson types, 1 hidden resource. No Blog or financial records were changed.
- Financial schema migration ran locally in 50.98 ms, adding nullable start/expiry timestamps without a backfill. Before/after: 9 packages; protected date/price/discount/allocation hash `b7137899eca0b97b194fececa86cad3b0b34c854b3f8eb5d6163f6e3e047f1ef` unchanged; 6 typed allocations, 27 ledger rows and balance 44 unchanged. All 9 existing clocks remain null in the new fields.
- BlogPublishing: 5 tests, 31 assertions, 16.439 s. Latest BlogAuthoring + FinancialTiming: 28 tests, 177 assertions, 18.491 s; covers the full authenticated publishing lifecycle, source preservation, locale grouping, media authorization, exact activation/expiry and historical rights. Synthetic HTML review fixtures are generated by isolated tests; no QA article is published in the application database.
- Approved Tiptap dependencies installed at 3.31.4; npm reported zero vulnerabilities. Final frontend build passed in 4.69 s (82 modules; Blog editor loaded separately at 396.01 kB, 124.88 kB gzip; public app entry 26.47 kB, 9.36 kB gzip). Existing HLS chunk-size warning remains.
- Public tests exposed missing fallback/copy expectations, quoting in booking text and game-card presentation/tracking regressions. Those were corrected; PublicExperience now passes 13 tests/109 assertions. Public catalogue download gates now require name and email even if the historical is_gated flag is false, preserving unlisted legacy download handling and authorized student routes. Resource/access regression tests continue.
- `node --test tests/clipboard.test.mjs tests/resource-access.test.mjs tests/telemetry.test.mjs tests/announcement-banner.test.mjs tests/admin-sidebar.test.mjs`: 18 passed, zero failures/skips, 191 ms. `php vendor/bin/pint --dirty --format agent` completed successfully.
- Static analysis initially exhausted PHP’s 128 MB limit. A full comparison used a private export of the original committed source and the existing dependencies, with a process-only 1 GB limit. Baseline: 255 issues. Latest implementation: 250 issues, with zero new issues when comparing file/message/identifier multisets independently of shifted line numbers. Existing project-wide warnings remain; no production/runtime configuration changed. The first private baseline startup hit a Windows cache-file race; warming that private application cache resolved it.
- Browser review confirmed the homepage, real game preview/badge/outbound link, Blogs navigation and combined FAQ search/topic results. Desktop width 1440 and mobile width 390 show no horizontal overflow on the reviewed pages. The policy mobile screenshot is 375 × 844 CSS content pixels after scrollbar allowance. The standalone editor preserves table/comment/Arabic source while editing metadata, shows dirty state and the live search preview, and retains Arabic plus RTL direction through visual/source changes. Inline image controls remain available in source mode. The HTML review fixture was rendered through authenticated isolated tests; submissions are blocked in the browser copy. Server publishing/authentication workflows are verified by HTTP tests, without publishing QA articles in the local application database.
- The initial full-suite run completed 1,468 tests: 1,330 passed, 80 assertion failures and 58 errors, 11,047 assertions, 468.119 s. Full review corrected CMS preview notices, homepage analytics identities, existing approved About translations, pricing CTA metadata, outdated copy assertions, isolated financial test fixtures and Blog relationship row identities. The valid original-code comparison completed 1,428 tests: 1,302 passed, 72 assertion failures and 54 errors, 10,831 assertions, 420.366 s. The final implementation run completed 1,468 tests: 1,342 passed, 72 assertion failures and 54 errors, 11,096 assertions, 433.060 s. Every unsuccessful test case also fails on the original code; no new unsuccessful cases remain. Differences in 31 failure strings were reviewed: whitespace/line endings, the same missing Arabic label in rendered HTML, and the same duplicate Bunny-provider error with different generated values. The full suite is not green. The disposable comparison copy’s two testing-only memory assignments were raised from 512 to 1,536 MB to serialize failure output/JUnit; primary application configuration remains untouched. An earlier comparison lacked ignored build assets and had 331 manifest-related failures, so it was excluded from regression attribution. The valid comparison used the same built assets as the implementation PHP tests, which do not execute frontend JavaScript. No unrelated LMS implementation was changed.
- Final scoped regression receipt: 241 tests passed, 1,830 assertions, 78.850 s, zero assertion failures/errors. The run covers all new public/Blog/financial tests plus existing ledger/typed entitlements/Cashier/billing UI, public experience, CMS/media/translations/previews/localized SEO, resource replacement/downloads, student authentication/admin authorization and booking hold/language-state tests. The earlier 76-test run’s remaining issues were the audit test’s record-ID selection and two missing pricing CTA markers; both are corrected and pass in this final scope.
- A settings-only follow-up import restored the existing homepage editing controls to the approved Word values. A separate version marker prevents future owner edits being overwritten on reruns. Its receipt was changed=true, faqs=0, hidden_session_types=0, hidden_resources=0; final dry-run JSON reports changed=false with dry_run=true. Dry runs now execute the guarded import in a transaction and roll it back.
- The archive correction passes `DevelopmentDataArchiveTest`, `DevelopmentLaunchDataToolsTest` and `BlogAuthoringWorkflowTest`: 55 tests, 331 assertions, 32.946 s, zero failures/errors. Final static-analysis comparison again reports 250 existing issues versus 255 on the original code, with zero new file/message/identifier occurrences. Receipts are saved under `storage/logs/codex-blog-archive-regression.json` and `storage/logs/codex-static-comparison-final.json`.
- Browser review screenshots are saved in `C:/Users/e/.codex/visualizations/2026/10/08/01a11c59-4828-79c3-9bb8-c9a4ceeb8f42`. They cover the desktop homepage, source-preservation editor, mobile article/policy/pricing and filtered FAQ. Temporary browser tabs were closed and the viewport override reset. The temporary public editor fixture was removed.
- Cleanup limitation: automatic approval review blocked deletion of the ignored temporary comparison directory `storage/framework/cache/codex-static-baseline-20261008` and its source archive, reporting only “blocked by policy.” Both remain local; neither is part of the application diff or a production artifact. Test/static receipts remain separately available in `storage/logs`.
- Final read-only application data receipt: `bolt_landing`; 9 packages, zero populated new validity clocks, 6 typed allocations, 27 ledger rows, balance 44. All original dated entitlements remain on their legacy end-of-day rule. No local QA Blog article was published; the existing real article remains available with Abdallah attribution and correct article metadata.

### Final review receipts

| Check | Saved evidence |
| --- | --- |
| Scoped 241-test run | [Scoped Laravel result](C:/Users/e/Herd/ArabicwAbdallah/storage/logs/codex-final-targeted.json) |
| Archive and Blog 55-test run | [Archive/Blog result](C:/Users/e/Herd/ArabicwAbdallah/storage/logs/codex-blog-archive-regression.json) |
| Original full-suite comparison | [Original result](C:/Users/e/Herd/ArabicwAbdallah/storage/logs/codex-full-baseline.json) |
| Final full-suite run and comparison | [Final result](C:/Users/e/Herd/ArabicwAbdallah/storage/logs/codex-full-regression-final.json), [comparison](C:/Users/e/Herd/ArabicwAbdallah/storage/logs/codex-full-comparison-final.json) |
| Static-analysis comparison | [Static comparison](C:/Users/e/Herd/ArabicwAbdallah/storage/logs/codex-static-comparison-final.json) |
| Desktop public homepage | [Homepage screenshot](C:/Users/e/.codex/visualizations/2026/10/08/01a11c59-4828-79c3-9bb8-c9a4ceeb8f42/website-home-desktop.jpg) |
| Standalone source preservation | [Editor screenshot](C:/Users/e/.codex/visualizations/2026/10/08/01a11c59-4828-79c3-9bb8-c9a4ceeb8f42/blog-editor-source-preservation.jpg) |
| Mobile public review | [Policy](C:/Users/e/.codex/visualizations/2026/10/08/01a11c59-4828-79c3-9bb8-c9a4ceeb8f42/website-policy-mobile.jpg), [article](C:/Users/e/.codex/visualizations/2026/10/08/01a11c59-4828-79c3-9bb8-c9a4ceeb8f42/website-article-mobile.jpg), [pricing/fallback](C:/Users/e/.codex/visualizations/2026/10/08/01a11c59-4828-79c3-9bb8-c9a4ceeb8f42/website-pricing-mobile.jpg) |

Actual final commands include `php artisan test --compact` for the full implementation suite; the archive follow-up names `tests/Feature/DevelopmentDataArchiveTest.php tests/Feature/DevelopmentLaunchDataToolsTest.php tests/Feature/BlogAuthoringWorkflowTest.php`; `php -d memory_limit=1G vendor/bin/phpstan analyse --memory-limit=1G --no-progress --error-format=json`; `php vendor/bin/pint --dirty --format agent`; the five Node test files listed above; and `npm run build`. These are local receipts, not production verification or release approval.

The following discovery sections describe the unchanged baseline at the discovery checkpoint. Fresh implementation receipts above and below supersede historical approval/test statuses.

## A. Discovery findings

### Sources and their authority

- User request: “Website Copy”, with the Word document and pasted task brief supplied as context.
- Editorial source: [Website Copy.docx](<C:/Users/e/Desktop/ChatGPT Saved/Website Copy.docx>). The entire document was extracted and reviewed in order: 1,743 paragraph/table blocks, including both tables and the admin requirements. No tracked changes, comments, or additional header/footer/footnote text were found in the document package.
- Proposed scope and approval checkpoint: [Pasted task brief](<C:/Users/e/.codex/attachments/b7bbb25d-1064-46e3-9a9c-4ceb5c3a2031/Pasted text.txt>), especially §§1.1, 22 and 25. It requests discovery before implementation and expressly requires approval of the plan.
- Actual behavior: current code, routes, installed packages, read-only local database queries, and public browser inspection.
- Historical context: `SYSTEM_REFERENCE.md`, `SYSTEM_CAPABILITIES_REPORT.md`, `PROJECT_STATUS.md`, `CODEX_V94_SECOND_PASS_AUDIT.md`, `FOLLOWUP_REMEDIATION_REPORT.md`, `REMAINING_FEATURES_IMPLEMENTATION_TRACKER.md`, `PRE_LMS_INVARIANTS.md`, and `LMS_IMPLEMENTATION_STATUS.md`. Older specifications differ from the current implementation in several places. Their historical test/deployment receipts are not fresh verification by this task.
- Repository guidance: `AGENTS.md` and the applicable document-reading, Laravel, Livewire, Tailwind, testing, and computer-use skills. `.ai/rules` is absent at this baseline.

The Word document's public wording is editorial input. Its placeholders, suggested layouts, and imperative implementation notes are not independent permission to publish, install dependencies, modify financial behavior, or create educational assets. Approved copy, proposed blog functionality, existing behavior to preserve, optional presentation ideas, and future features are treated separately below.

### Source versions

| Reference | Word section | Explicit ending version |
| --- | --- | --- |
| H | Homepage — Complete Content | 2.0 |
| P | Pricing & Coaching Tracks — Final Refined Copy | 3.0 |
| L | Booking, Cancellation, Rescheduling & Refund Policy | 1.0 |
| A | About page | 2.0 |
| R | Resources page | 2.0 |
| G | Games page | 3.0 |
| K | Booking Experience | 2.0 |
| F | FAQ page | 2.0 |
| BL | Blogs listing page | 3.0 |
| BA | Individual Blog Article | 3.0 |
| BP | Blog Administration Requirements | 3.0 |

These are the latest explicitly labelled versions within the supplied document. The substantive internal inconsistencies are recorded under decisions D2 and D5; they will not be silently resolved by substituting older copy.

### Architecture and boundaries

| Area | Actual implementation |
| --- | --- |
| Runtime | PHP 8.4.25; Laravel 13.32.0; Livewire 4.4.5; PHPUnit 12.5.35. Versions checked through the CLI and installed Composer packages. |
| Frontend | Server-rendered Blade, Tailwind 4.3.3, Vite 8.3.0, Alpine and class-based Livewire for booking. Frontend versions checked against `package.json` and the lockfile. No rich-text editor package is currently installed. |
| Database | Local MariaDB 10.11.18, database `bolt_landing`, 131 tables. Schemas and relevant non-personal content were inspected through Laravel Boost's registered tools. |
| Organization | Domain modules for CMS, Resources, Games, Booking, Availability, Timezone, Students, Forms, Administration, Analytics, Notifications, LMS, and System. Existing domain/service/controller/view conventions should be extended. |
| CMS | Settings, Pages and translations, FAQs and translations, Resources, Games, Media, revision infrastructure, and a separate existing Blog model/service. No replacement CMS is necessary. |
| Public rendering | `resources/views/layouts/public.blade.php`; public page views under `resources/views/public`; blog views under `resources/views/blog`; JSON UI translations under `lang/en.json`, `fr.json`, `de.json`. |
| Booking | Four-step Livewire wizard, signed slot identifiers, visitor/session-bound 10-minute holds, database concurrency protection, idempotent final confirmation, UTC storage and date-sensitive timezone conversion. Submission already creates a confirmed booking. |
| Financial/student systems | Manual payment/refund records, append-only ledger behavior, typed entitlements, package settlement and expiry, identity verification and Student Portal. These are preservation boundaries, not copy-edit targets. |
| Localization | English, French and German UI/routes, `LocalizedUrlService`, entity translation grouping/revisions, and explicit English content fallback for several CMS entities. Blog integration with these public URL mechanisms is incomplete. |
| Media | Existing public-disk library, picker, upload endpoint, MIME/size validation, image metadata/alt text, and deletion/reference guards. Blog image authoring should reuse this infrastructure. |
| LMS | Current local status documentation records Stage 9 readiness with limitations on 8 October. This is historical context, not an independent production inspection. LMS files and behavior remain outside the proposed implementation scope. |
| Testing | PHPUnit feature/unit tests and built-in Node tests. `phpunit.xml` points to `bolt_landing_test` on local port 3307; `Tests/TestCase.php` checks reconciled MariaDB compatibility before database-refresh traits run. Tests were inspected, not executed during discovery. |

The existing terracotta/Nile palette, fonts, shared components, responsive navigation, and first-party analytics remain the design foundation. There is no proposal for a new branding system or page architecture.

Three pre-existing untracked reports were present and remain untouched: `HOSTINGER_FIVE_PASS_DEPLOYMENT_REPORT.md`, `HOSTINGER_FIVE_PASS_PRODUCTION_PREFLIGHT.md`, and `PRODUCTION_TYPED_ENTITLEMENT_MAPPING_PROPOSAL.md`. Recheck shared work before implementation and use a dedicated `codex/` branch or suitable isolated worktree.

### Observed public content and records

Public browser inspection covered Home, Pricing, About, Resources, Games, Booking, FAQ, the Blog listing, and the existing article. Admin behavior was inspected through code, routes, schema, and existing tests; no administrator session or publishing workflow was exercised.

- One local published English blog: “Mastering Egyptian Arabic: Practical Tips for Beginners”, slug `mastering-egyptian-arabic-practical-tips-for-beginners`, published 30 September 2026. It contains a short paragraph. Preserve it and its URL; its editorial provenance has not been independently established. No new article bodies are supplied for publication.
- One published local resource: “Synthetic Follow-up Access Guide”, explicitly described as a local browser verification fixture. It is visible on Home and Resources and has one student resource assignment; archiving it would affect that access. No Alphabet Workbook record or matching asset was found in the inspected local resource records/files. This does not establish whether the genuine workbook exists elsewhere.
- One available game: 6-Word Story, with the existing destination `https://mohamedateff.com/6word`. Its actual browser interface confirms spoken practice, Connected Story and Wild Mix, four difficulty levels, and multiple languages. The destination now also offers other exercises; the Word document does not authorize adding those as new site catalogue entries.
- Three active session types are exposed by public booking: Diagnostic & Learning Roadmap ($25/60 minutes), Feature QA Lesson ($25/60 minutes), and Pass4 Two-Hour QA Lesson ($48/120 minutes). An older $40 lesson exists but is inactive and was not visible in booking. Public code currently equates `active` with selectable; there is no distinct public-booking visibility flag.
- QA-labelled engagement figures appear in the public banner. About currently displays unsupported figures including 1,500+ lessons, 35+ countries, and 70% speaking time. None are supported by the supplied copy.
- Four active FAQs exist. The supplied FAQ has **34 questions in eight topic groups**, plus introduction, contact and interface states.
- The published short intake form has the stable keys `goals`, `experience`, and `context`; all three are required. Its current labels emphasize Egyptian Arabic. The supplied questions broaden them to Arabic and add preparation helpers/placeholders. Use a new form version, preserving historical submissions and keys.
- The only published Terms translation is English and matches the base Terms content. The current policy conflicts with the proposed copy; see D2.
- Existing footer social destinations include generic platform homepages and contact placeholders. They cannot be called verified profiles without owner confirmation.

### Important defects and compatibility risks

1. **Blog canonical and language URLs are wrong.** `LocalizedUrlService` does not resolve blog routes. Browser inspection confirms that `/blog` and the article default to the homepage canonical and Open Graph URL; alternate-language links point to translated homepages. Intentional custom canonical fields must be preserved while fixing defaults and related metadata.
2. **Blog locale is ignored in public discovery.** The model stores locale/grouping, but the listing retrieves all published records without locale selection. Manual locale/UUID fields do not provide a translation workflow.
3. **Authoring is a raw HTML textarea.** The existing LMS rich-text component is coupled to `LmsContentService` and has a narrower feature set. It is not a drop-in blog editor and should not be modified as part of this task.
4. **RTL formatting is stripped.** The shared sanitizer preserves Arabic characters but does not allow direction/language attributes or directional spans. Its current default is also used by LMS. A blog-specific safe profile is needed, with the existing default unchanged and security regression coverage.
5. **Images are incompletely protected.** Blog featured paths have an existence check but no adequate image-only selection workflow. Templates render empty alt text and lack a broken-image fallback. Media reference guards account for featured images and revision featured images, but not inline images in blog bodies or body snapshots; an inline image could therefore be removed from the library.
6. **Canonical length validation differs from storage.** HTTP validation permits 2,048 characters; the existing column is a 255-character string. This can fail at storage time rather than provide clear validation feedback.
7. **Publication timestamps need deliberate semantics.** Unpublishing clears `published_at`; republishing then assigns a new date. `updated_at` also changes for non-editorial updates. Preserve the original publication date on unpublish/republish and add a nullable meaningful editorial update date, without inventing historical dates.
8. **Home resource visibility is inconsistent.** Home checks published status/non-null date but does not apply the resource model's future-publication cutoff. Use the existing published scope so scheduled resources cannot leak onto Home or related-content sections.
9. **Public claims outpace or conflict with behavior.** Credit timing, package expiry, refunds, immediate confirmation wording and rescheduling need owner decisions. These are not safe global find-and-replace edits.
10. **Stored HTML compatibility requires evidence.** The local article is too small to represent long educational posts. Round-trip tests need it plus representative supported legacy HTML, Arabic/transliteration, lists, quotes, images, and links. Unknown safe markup must trigger an explicit preservation/source-editing path, not silent deletion.

## B. Public content-to-code mapping and implementation tracker

Paths below are relative to `C:/Users/e/Herd/ArabicwAbdallah`. H/P/etc. refer to the source-version table above. “Missing presentation” means a modest section can be added to the existing view; “missing function” needs explicit scope approval. **Every row is NOT STARTED; no changes have been applied.** Verification below is the planned acceptance check, not a passing result.

Localization key: **UI** = `lang/en.json`, `fr.json`, `de.json` and shared labels; **CMS** = English source plus existing entity translation/revision/fallback mechanisms; **Form** = versioned short-form labels, helpers and existing locale handling; **Blog** = each article's real `locale`/translation group, with no invented translations. D4 settles rollout policy.

### Shared public foundation

| Page/section | Source | Existing location | Current status | Required action | Functional dependency | Localization | Verification |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Brand, header, mobile menu, primary CTA | Global copy repeated across H/P/A/R/G/K/F/BL | `layouts/public.blade.php`, CMS Settings, `config/business.php` | Outdated Egyptian-only brand/strapline; singular Blog label | Apply Arabic with Abdallah wording and approved CTA labels; preserve navigation mechanics/order; settle Blogs label under D5 | Named/localized routes, portal entry, analytics | UI + Settings | Desktop/mobile links, keyboard menu, student login entry, no unsupported claims |
| Footer, explore/student access/information links | Global footer | `layouts/public.blade.php`, site footer/contact settings | Outdated; missing Blogs/student/policy grouping; placeholder contacts | Apply supplied text; add working existing destinations and approved policy link; use only verified contacts | Privacy/Terms pages, contact settings, social analytics | UI + Settings/CMS | Every visible destination valid; no placeholder or fabricated social profiles |
| Language selectors and public SEO defaults | Global navigation; supplied page metadata | `LocalizedUrlService.php`, public layout, controllers/views | Existing core routes; blog mapping defective | Preserve en/fr/de routes; correct blog integration; update source titles/descriptions where supplied | Published translations, intentional canonical overrides | UI/CMS/Blog | Correct canonical/hreflang and honest fallback; no links pretending a translation exists |
| Shared notices, errors, buttons and validation | Relevant K/R/G/F/BL/BA/BP states | Public views, Livewire wizard, resource-access JS, CMS settings/components; booking exceptions | Mixed old/generic wording | Update supplied messages at the actual state boundary; retain meaningful server errors; report any needed unsupplied prose | Existing validators, rate limits, error handling, announcement controls | UI | Real success/error/empty states and accessibility announcements; no dead retry controls |

### Homepage — H 2.0

| Page/section | Source | Existing location | Current status | Required action | Functional dependency | Localization | Verification |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Hero/introduction/CTAs | H §1 | `public/home.blade.php`, `HomeController.php`, hero Settings | Outdated copy and Egyptian-only positioning | Replace with supplied Egyptian/MSA/blended offer, audiences and diagnostic invitation | Booking and pricing links | UI + Settings | Correct copy/CTA destinations; no fluency guarantees |
| Everyday expressions | H §2 | Existing expression feature in Home view | Interactive presentation exists; expressions differ | Replace with ماشي / Māshi, يلا بينا / Yalla bīna, بجد؟ / Begad? and approved glosses | Existing interaction | UI, Arabic spans | Arabic/transliteration accuracy, mixed direction, keyboard behavior |
| Personalized learning and support | H §3 | Home approach Settings + three existing cards | Outdated | Replace all three explanations and helper text | Actual lesson/material/guidance capabilities | UI + Settings | No unsupported unlimited support, response-time or video promises |
| Diagnostic and written roadmap | H §4 | Home session panel and active SessionType description | Partial presentation; dynamic price/duration exist | Add approved consultation/assessment/roadmap sequence; distinguish delivery afterward | Actual $25/60-minute diagnostic; manual follow-up | UI + selected record | Correct duration, price, no obligation and next-step language |
| Free learning resources | H §5 | Home resource Settings and dynamic featured resources | Outdated; synthetic entry exposed; future-date predicate incomplete | Apply copy, preserve real cards, use published scope; visibility/workbook under D3/D6 | Resource access/catalogue eligibility | UI/CMS | Eligible resources only; scheduled/QA exclusion approved; valid access links |
| Language games | H §6 | Home game Settings and dynamic cards | Copy conflicts with later game specification | Apply owner-approved later spoken/multilingual description under D5; preserve actual destination | Existing game availability, external link/tracking | UI/CMS | 6-Word Story behavior accurately described; no invented mechanics |
| Meet Abdallah | H §7 | No equivalent biography section in Home | Missing presentation | Add supplied brief introduction and About link using existing design components | Existing About page | UI/Settings | First-person intent and legitimate biography; no fabricated credentials |
| Homepage FAQ | H §8 | Home active FAQ selection | Four existing FAQs; supplied six differ | Populate approved six and consistent answers via CMS; avoid simply selecting arbitrary first six | Main FAQ dataset/revisions | CMS | Six intended questions; consistent pricing/booking/policy answers |
| Final invitation | H §9 | Home final CTA Settings/view | Outdated | Replace invitation and diagnostic CTA | Booking route | UI + Settings | Correct invitation, link and responsive layout |

### Pricing and About

| Page/section | Source | Existing location | Current status | Required action | Functional dependency | Localization | Verification |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Pricing introduction | P §1 | `public/pricing.blade.php`, `PricingController.php`, language JSON | Outdated emphasis | Apply Arabic/MSA/blended offer and diagnostic-first explanation | Diagnostic eligibility/presets | UI | Audience and offer agree with Home/About |
| Diagnostic and 48-hour credit example | P §2 | Pricing diagnostic/credit blocks; `StudentLedgerService.php` eligibility | Prices agree; timing conflicts | Apply approved consultation explanation; withhold incompatible credit statement pending D2 | Completed diagnostic, paid fee, one-time eligible package credit | UI + authoritative values | $25/60 minutes; $280−$25=$255; approved timing language |
| Foundation/Fluency cards, comparison/inclusions | P §3 | Pricing package cards and student ledger presets | Base amounts correct but presented crossed out; unsupported claims | Present standard totals prominently, eligible remaining payments separately; apply supplied comparison/inclusions | Package rates, durations, expiry/priority policy | UI + authoritative values | Foundation 8×2h/16h/$280/$35/$17.50h/75 days; Fluency 12×2h/24h/$390/$32.50/$16.25h/100 days; $255/$365 conditional |
| Guidance beyond sessions | P §4 | Existing benefit sections | Outdated or overpromising | Replace with approved bounded guidance/materials wording | Tutor-operated guidance; existing materials | UI | No unlimited clinics, daily response guarantees or unpublished content claims |
| Standalone options | P §5 | Existing supplementary pricing content/presets | Partial/outdated prominence | Add accurate secondary options with eligibility/by-arrangement wording | Returning 2-hour $48; eligible advanced 1-hour $28 | UI + values | Main packages remain primary; no unsupported public booking options |
| Payments, booking/cancellation and refunds | P §§6–7; L 1.0 | Pricing view, Terms CMS page; booking/ledger/refund services | Contradictions; missing full summary | Implement only after D2; clear confirmed appointment/manual payment and approved policy links | Actual state and manual policy operation | UI/CMS | No unpaid-as-paid claims; policy consistent across Terms/Pricing/FAQ/Booking |
| Pricing final invitation | P §8 | Pricing final CTA | Outdated | Replace with supplied invitation | Booking route | UI | Correct diagnostic CTA |
| About narrative, philosophy, methods, audiences, invitation | A §§1–6 | `public/about.blade.php`, `PageController.php`, About Settings; optional CMS Page path | Short biography and unsupported numerical claims; six-part narrative absent | Implement all six supplied sections; remove unsupported figures; use existing CMS/settings draft/revision conventions | Existing biography/image and material capabilities | UI/Settings/CMS | Full source coverage, accurate first-person voice, no invented qualifications; valid diagnostic CTA |

### Resources and Games

| Page/section | Source | Existing location | Current status | Required action | Functional dependency | Localization | Verification |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Resource introduction/discovery labels/cards | R §§1–2 + interface states | `public/resources/index.blade.php`, `ResourceController.php`, categories/resources/translations | Catalogue/category filters/pagination exist; search and several proposed filters absent | Apply labels and actual card metadata; optional search/filter scope under D5 | Published dates, categories, cover images, genuine assets | UI/CMS | Real filters, accurate format/level only where stored; no decorative nonfunctional search |
| Featured Alphabet Workbook and detail | R §3; F resources answers | Resources records + `public/resources/show.blade.php` | Genuine item described by source; local record/file absent | Locate owner-provided asset under D6 before linking/importing; apply supplied metadata only to verified asset | Actual workbook, cover, download authorization | CMS | File available and correct; promised 34-page/8-lesson/28-letter metadata verified against asset |
| How to use / further learning | R §§4,6 | Resource index/detail views | Missing presentation | Add supplied independent/video-companion practice guidance and game links; qualify videos as planned under D5 | Actual published companion content only | UI | No implication the planned series is released |
| Email access, loading, errors, unavailable states | R §5 + interface; K/F where relevant | Resource detail, request/download controllers, existing access JS and verification email template | Protected flow exists; source omits required name | Preserve name+email lead capture, token/expiry/verification controls; add accurate name label alongside supplied access copy | Current PIN requirement false; existing security/rate limits intact | UI/CMS | Existing access tests; valid/invalid/expired/unavailable cases; no false delivery claims |
| Games introduction, available game, descriptions | G §§1–2 | `public/games/index.blade.php`, `show.blade.php`, `GameController.php`, game translations | Real game present; Egyptian-only wording too narrow | Apply approved multilingual spoken-story description, actual modes/difficulties, and launch labels | Existing external destination, availability and tracking | UI/CMS | No replacement game/invented writing mechanics; accurate external launch behavior |
| Why games / keep exploring | G §§3–4 | Games views | Missing presentation | Add supplied creativity, active practice and resource invitation sections | Existing resources route | UI | Clear no-enrolment requirement, legitimate resource links |
| Game discovery and system states | G interface/search/category/empty/unavailable copy | Games list/detail; availability statuses | Empty states partial; no search/category model | Implement applicable states; proposed discovery controls under D5 | Actual game inventory/status, no fabricated categories | UI/CMS | Empty/unavailable cases and working controls; hide irrelevant controls for one item |

### Booking and FAQ

| Page/section | Source | Existing location | Current status | Required action | Functional dependency | Localization | Verification |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Booking introduction / session selection | K §§1–2 | `public/booking.blade.php`, `livewire/booking-wizard.blade.php`, `BookingWizard.php`, SessionType | Four-step flow exists; QA types exposed | Apply supplied diagnostic wording to the diagnostic branch; preserve other legitimate session behavior; visibility under D3 | Active/public eligibility and existing step auto-skip | UI + SessionType record | Genuine visible options only; server rejects hidden options; no forced diagnostic labels on returning-session confirmations |
| Date/time and timezone help | K §3 | Wizard view/class, timezone selector and display service | Functionality implemented; copy incomplete | Replace instructions and empty-slot help; render local/Cairo dates and date-specific offsets | 24-hour advance rule, signed slots, DST, actual selected timezone | UI | DST/gap/fold cases; timezone change preserves state; no hardcoded Cairo offset |
| Personal details and intake | K §4 | Wizard inputs; published short-form version/questions | Required fields exist; question labels/helpers differ | Apply labels/helpers/placeholders; create approved short-form revision preserving `goals/experience/context` and existing requiredness | Validation, DOB-based portal identity, historical submissions | UI + Form | Invalid/valid form behavior; source questions and preparation helpers; no long-form/LMS questionnaire changes |
| Hold notice and review/policy acknowledgement | K §§4–5,8 | Wizard review panel, existing hold/server exceptions | Holds/review exist; manual-payment explanation incomplete | Apply exact hold/review/acknowledgement wording and approved policy link; policies under D2 | Ten-minute visitor-bound hold, local/Cairo schedule, final confirmation | UI/CMS | Hold expiry/conflict, double submission, genuine successful confirmation, no automatic charge promise |
| Confirmation / calendar / what happens next | K §6 | `public/confirmation.blade.php`, `BookingController.php`, ICS generator | Implemented confirmation/status display; copy partial | Apply state-specific copy, manual payment instructions and roadmap next steps; preserve secure management/calendar links | Actual confirmed/cancelled/completed/no-show status; payment records distinct | UI | Confirmed unpaid versus recorded paid; replay does not create duplicate booking; calendar times correct |
| Booking, cancellation, rescheduling and failure messages | K §§7–8; F/L | Wizard errors, `public/reschedule.blade.php`, public booking controller and domain exceptions | Existing logic/state boundaries; supplied policy not fully aligned | Map wording to actual states; explicit contact-based changes where required; D2 before incompatible promises | Cancellation/credit policy, reschedule cutoff, no-show handling; portal behavior preserved | UI | State-specific errors/statuses; retain HTTP/auth boundaries; no label implying pending tutor approval after successful submission |
| FAQ introduction and all topic groups | F §§1–9 | `public/faq.blade.php`, `PageController.php`, Faq/FaqTranslation and revision services | Four records; 34 supplied questions/eight groups absent | Import/update supplied questions through existing CMS history workflow; plan group presentation under D5 | Prices, diagnostic, booking, support, package/refund policy, portal, resources/games, technical help | UI/CMS | 34-question coverage; consistent answers; portal capabilities accurately described |
| FAQ contact and interface states | F §10 + interface | FAQ view/accordion | Accordion exists; search/group controls absent; contacts unverified | Add supplied contact/help prose; search/category scope under D5; verified destinations under D6 | Existing accordion and real contact links | UI/CMS | Keyboard focus/expanded state; actual search/no-result/reset behavior only if approved |
| Full booking/cancellation/refund policy | L 1.0 | Existing Page CMS, generic `/p/{slug}` page route; `/terms` page | Dedicated supplied policy absent; published Terms conflicts | D2 approval: publish supplied policy as a versioned CMS page and reconcile conflicting Terms paragraphs; preserve unrelated legal text/Privacy | Manual policy administration and actual booking/ledger semantics | CMS | Cross-page agreement; English translation/base synchronization; no invented effective date |

### Blogs — public copy and admin labels

| Page/section | Source | Existing location | Current status | Required action | Functional dependency | Localization | Verification |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Listing intro, discovery and article library | BL §§1–3 | `blog/index.blade.php`, public BlogController, Blog model | Basic listing exists; discovery/category/featured selection incomplete | Apply V3 introduction, genuine cards, search/category/sort labels and selected featured article treatment | Real published articles, approved new category/search implementation | UI/Blog | Preserve existing body/URL; functional filters; actual dates/excerpts |
| No articles, continue exploring and library messages | BL §§4–6 | Blog index view | Minimal empty state; supplied full states missing | Add supplied no-published/no-match/category-empty states and resource/game invitations | Distinct collection/search states; real published destinations | UI/Blog | Clear-search/reset links work; no empty featured placeholders or invented article cards |
| Article header/body/related/navigation/states | BA §§1–5 + article states | `blog/show.blade.php`, BlogController, public layout | Title/body/date/image exist; metadata/readability/related/navigation partial or missing | Apply interface copy around preserved author text; related sections only when eligible content exists; optional Copy Link using existing clipboard utility | Publication visibility, image/locale/SEO/relationships | UI/Blog | Arabic/Latin layout, responsive reading width, real author/date, safe body and correct 404/unavailable responses |
| Blog admin field helpers and workflow messages | BP §§1–10 | `admin/blog/index.blade.php`, `form.blade.php`, admin BlogController/BlogService | Raw fields and generic action/flash messages | Apply V3 labels/helpers and state-specific draft/preview/publish/update/error/dirty messages with real behavior | Editor, image picker, server validation/auth/version lock | UI/Blog | Draft/publish distinctions, practical SEO preview, validation and unsaved-change behavior; no false success |

Content imports must be deliberate, idempotent and limited to identified records/settings. Preserve existing revisions, links, submissions and educational bodies. Do not run broad seeders or replace the entire database. The tracker will record exact mutations and verification after approval.

## C. Blog capability matrix and feature tracker

Classification refers to **discovery evidence**, not implementation acceptance. “IMPLEMENTED AND VERIFIED” below means the described limited capability was confirmed by code/schema and, where indicated, read-only browser/data inspection. Existing automated tests were read but not run. **All work proposed in the final column is NOT STARTED, with no new tests or modified modules yet.** No feature is marked VERIFIED COMPLETE for this task.

| Requirement | Classification | Evidence / practical limit | Required work and planned verification |
| --- | --- | --- | --- |
| Article storage and existing content | IMPLEMENTED AND VERIFIED | Blog, BlogRevision and BlogSlugRedirect models; existing tables and one public article | Preserve IDs, bodies, URLs and history; migration/content-integrity assertions |
| Authorized admin listing/create/edit | IMPLEMENTED AND VERIFIED | Routes use web authentication, active-account and super_admin/admin role checks; listing paginates 20 | Preserve architecture; exercise allowed/denied HTTP roles and validations after approval |
| Visual long-form editor | MISSING | Raw HTML textarea; LMS editor is separate, limited and coupled | D1 editor choice; paragraphs, headings, emphasis, lists, links, images, quotes, Arabic/RTL; real browser authoring |
| Existing HTML compatibility | PARTIALLY IMPLEMENTED | Stored HTML is editable as source; no visual round-trip coverage | Match supported legacy schema, retain safe unsupported content via explicit source editing; representative round-trip tests |
| Server HTML/XSS sanitation | PARTIALLY IMPLEMENTED | Shared HTMLPurifier profile blocks scripts/events, unsafe URLs and external/data/traversal images; existing sanitizer tests | Preserve protections; blog-only direction/language profile; CMS and unchanged LMS sanitizer regression |
| Arabic/RTL/mixed script | DEFECTIVE | Characters survive; `dir`/`lang` formatting removed; article styling is incomplete | Safe directional attributes/nodes and matching CSS; mixed-script editor/save/preview/public checks |
| Suggested and manual slugs | PARTIALLY IMPLEMENTED | Manual normalized slug plus uniqueness/reserved redirect checks; no title suggestion | Suggest new slugs without changing an established URL on title edits; validation/race-friendly feedback |
| Stable URLs and old-slug redirects | IMPLEMENTED AND VERIFIED | Service records old slugs; public controller redirects to current published slug with 301; tests cover example | Retain global slug uniqueness and route conventions; test multiple renames, reserved collisions and no loops |
| Slug reversion and concurrent collisions | PARTIALLY IMPLEMENTED | Old slugs reserved even for their owning record; concurrent conflicts may surface as DB errors | Handle deliberate return to own old slug safely; friendly duplicate errors; retain cross-article reservations |
| Editable plain-text excerpt | PARTIALLY IMPLEMENTED | Stored excerpt, escaped card rendering; markup can still appear literally | Enforce/normalize plain-text excerpts and use them consistently; malicious/plain/HTML excerpt assertions |
| Existing image library/upload | IMPLEMENTED AND VERIFIED | CMS picker/upload and Media metadata exist; permitted images plus PDFs, 10 MB cap, no SVG; local library empty | Reuse authorized existing upload/library workflow; filter blog picker to supported public images |
| Blog featured image selection/preview/remove/replace | MISSING | Manual path field; no integrated picker/preview | Integrate picker and upload return/refresh, preview/remove/replace actions; valid/invalid MIME/path/storage cases |
| Image alternative text | MISSING | Media has alt metadata but blog templates output empty alt; no blog field | Article image alt field + inline editor alt; informative/decorative cases |
| Missing-image fallback/responsiveness | PARTIALLY IMPLEMENTED | No-image cards omit image; unavailable referenced images can break | Server existence check and presentation fallback for later load failure; mobile/image-missing cases |
| Inline media lifecycle references | DEFECTIVE | Guards protect featured image paths/snapshots, not body image references | Extend CMS media reference checks for live blog HTML and body revisions; prevent removal of referenced assets |
| Save draft / publish / update actions | PARTIALLY IMPLEMENTED | Status workflow exists but title/slug/excerpt/body required even for an unfinished draft; one generic save action | Distinct V3 actions/messages; allow titled incomplete drafts with generated/validated slug; full publication validation |
| Published visibility and unpublish access | IMPLEMENTED AND VERIFIED | Published status + non-future publication time scope; public show also applies it | Regress listing/direct URL/redirect visibility after draft/unpublish/archive changes |
| Publication-date stability | DEFECTIVE | Unpublish clears published date, republish resets it | Preserve original publication instant; no invented backfill; publish/unpublish/republish regression |
| Secure draft preview | PARTIALLY IMPLEMENTED | Authorized saved-record preview uses article layout; admin noindex header exists | Set correct preview flag; explicit private/no-store response; saved-draft layout, guest/student/staff denial checks |
| Unsaved changes | MISSING | No dirty warning for article form | Warn on navigation/unload; handle successful saves, intentional actions and browser/editor changes |
| SEO fields | PARTIALLY IMPLEMENTED | Title/description/canonical optional fields exist | Clear helper text/fallbacks and validation; preserve intentionally configured values |
| Search-result preview | MISSING | No illustrative preview | Show actual title/URL/description with supplied caveat; test fallback and live edits |
| Default canonical and Open Graph URL | DEFECTIVE | Browser-confirmed homepage fallback on listing/article | Blog-aware localized routes; internally consistent canonical/OG URLs; preserve explicit overrides |
| Canonical URL validation/storage length | DEFECTIVE | Validation 2,048; DB string 255 | Approved additive migration/validation alignment; long valid URLs and invalid schemes |
| Open Graph/social metadata | PARTIALLY IMPLEMENTED | Global website type/title/description; no article image/type/dates or complete social cards | Article-specific metadata with safe real-image defaults; no unpublished metadata leaks |
| Verified author/publication/update/language | PARTIALLY IMPLEMENTED | Internal admin author FK and publication datetime; no public author/update semantics | Use verified public author display; meaningful update timestamp; actual locale/date labels; no internal account details exposed |
| Article structured data | MISSING | No article JSON-LD implementation found | Minimal valid Article/BlogPosting data from verified visible content; omit unavailable properties |
| Sitemap / indexing integration | MISSING | No sitemap route/file/generator found; robots permits public crawling | Minimal published-only sitemap endpoint, canonical-aware eligible URLs and robots reference; drafts/admin/preview/redirect aliases excluded |
| Admin SEO checks | MISSING | Basic validation only | Helpful missing/invalid fields, headings, alt text, public internal-link, locale/indexing checks; separate blockers from advice; no fake scores |
| Categories and management | MISSING | No blog category storage/assignment | D1 minimal CMS taxonomy/one primary category; CRUD/assignment/filter validation and published-only counts |
| Public search and combined filters | MISSING | No query/search support | Bounded DB search over title/plain excerpt, combined locale/category predicates; no new search service; empty/no-match tests |
| Newest/oldest sorting | PARTIALLY IMPLEMENTED | Newest-only actual published_at ordering | Whitelisted two-way sort with stable tie-breaker; no draft/future leakage |
| Pagination | IMPLEMENTED AND VERIFIED | Public 12/admin 20 paginate calls; public listing renders actual record | Preserve bounded loading and query parameters after filters; multi-page test fixtures |
| Featured article selection | REQUIRES DECISION | Featured image does not select a featured article; source presentation is conditional | D1 minimal per-locale selected published article using existing Settings; no selection by default and no fake featured content |
| Locale/group storage | IMPLEMENTED AND VERIFIED | Existing locale and nullable UUID; unique group+locale | Retain relationships/IDs; constrain supported languages and provide user-friendly duplicate-group validation |
| Translation management UI | MISSING | Raw locale/UUID fields only | Language select, related versions/publication status, Add/Edit Translation backed by existing grouping; no generated article text |
| Public locale/canonical/hreflang/switching | DEFECTIVE | Locale ignored; no blog mapping; language switches go to homepages | Actual locale routes/real published translations only; explicit unavailable behavior under D4; preserve English article URLs |
| Related articles/resources/games | MISSING | No blog relationships found | D1 three small editorial link tables to existing IDs; eligible real content only, no automatic relevance invention |
| Breadcrumb/category/author/return navigation | PARTIALLY IMPLEMENTED | Title/excerpt/date/body exist; required framing absent | V3 interface around actual body; meaningful metadata, Back to Articles and eligible relationships |
| Reading-time estimate | REQUIRES DECISION | No estimate; source makes it conditional | D1 includes modest text-derived estimate labelled approximate; omit when unsuitable; no arbitrary word-count targets |
| Responsive typography/heading hierarchy | PARTIALLY IMPLEMENTED | Existing max-width/heading; `prose` classes without installed typography plugin | Scoped article CSS within current design, meaningful heading checks and keyboard/mobile RTL review; no new font/theme dependency |
| Copy Link/simple sharing | REQUIRES DECISION | Optional in source; existing clipboard utility available elsewhere | D1 includes Copy Link using that utility; success/failure/accessibility tests; no new sharing platform |
| Missing/unpublished article responses | PARTIALLY IMPLEMENTED | Public controller returns 404; no article-specific useful navigation/state layout | Keep correct privacy/status codes and add supplied public state with Blog navigation; genuine temporary failure must retain proper status |
| V3 admin labels, helpers and messages | PARTIALLY IMPLEMENTED | Older Blog naming/generic successes and validation | Apply V3 wording and actual draft/publish/update/image/error messages; terminology supersession D5 |
| QA/public-content exclusion | REQUIRES DECISION | No explicit QA blog found locally; QA resource/session/banner exposure verified | D3 approved visibility controls for actual records; no title heuristic, deleted data, or invented classification |
| Regression coverage | PARTIALLY IMPLEMENTED | Existing BlogPublishingTest covers sanitation, snapshots/redirects, optimistic conflict, draft privacy and old singular terminology | Add missing meaningful admin/public/editor/metadata/media/translation/search cases; update superseded copy expectations only after D5 approval |

## D. Consequential decisions awaiting approval

**Approved decisions: none yet.** Recommendations are proposals. An unanswered item remains pending; silence is not approval. Approval can cover independent stages while expressly holding dependent areas. Any approved deferral will be recorded, and the overall assignment will not be described as complete while required work remains unresolved.

### D1 — Blog architecture, editor dependency and small schema additions

**Recommendation:** extend the existing Blog CMS with a standalone CMS editor using compatible Tiptap 3 packages: `@tiptap/core`, `@tiptap/pm`, `@tiptap/starter-kit`, and `@tiptap/extension-image`. Pin compatible versions after approval; do not assume a patch version or install anything now. Use the existing Blade/Vite/Alpine architecture and media endpoints. Do not reuse or modify the coupled LMS editor.

The official [plain JavaScript integration](https://tiptap.dev/docs/editor/getting-started/install/vanilla-javascript) fits the current frontend. The [Image extension](https://tiptap.dev/docs/editor/extensions/nodes/image) supplies image rendering rather than server upload; the existing authorized CMS upload flow remains responsible for files. Tiptap's [HTML content parsing](https://tiptap.dev/docs/editor/api/commands/content/set-content) accepts only its configured schema, so compatibility fixtures and an explicit preservation/source-editing path are essential.

Alternative: an independent custom CMS `contenteditable` editor without new packages. It avoids dependency additions but requires more custom work for long-form editing, reliable list/quote/RTL behavior, content preservation, undo and browser consistency. A raw textarea alone does not meet V3. A full replacement CMS is unnecessary.

Approve the following minimal additions together with the editor choice:

- One manageable **primary category per article**; a small CMS category table seeded only with the five supplied taxonomy labels, optional approved translated labels, and a nullable blog category reference. Existing articles remain uncategorized until intentionally assigned. Preserve the globally unique slug rule; no permalink redesign.
- Nullable featured-image alt text and meaningful editorial-update timestamp. Do not pretend ordinary metadata saves are substantive updates, and do not fabricate historical timestamps.
- Align canonical storage with the existing 2,048-character validation, preserving current values.
- Three explicit editorial relation tables connecting a Blog to existing Blogs, Resources and Games; validate references, reject self-links/duplicates, and filter visibility at render time.
- Reuse Settings for an optional selected featured article per locale. A selected ID must resolve to an eligible published article. No article is selected merely to fill space.
- Simple optional Copy Link using the existing clipboard utility; a modest approximate reading-time estimate when it can be derived from actual article text. No sharing service, SEO subscription, AI writing, comments, newsletter, or search infrastructure.

Keep existing records and UUID groups intact. Start new nullable fields/relationships empty, rather than rewriting content or inferring relevance. Include original publication-date preservation and safer duplicate/translation validation in the current BlogService workflow.

**Owner choice:** approve this minimal Tiptap-based extension, or choose the custom editor/identify requested changes to the minimal model. Dependency and migration approval is necessary before Stage 7 implementation.

### D2 — Business wording, behavior and binding policy

| Issue | Supplied source | Current verified behavior/text | Implication and recommendation |
| --- | --- | --- | --- |
| Diagnostic credit clock | Eligible package purchase within 48 hours after diagnostic completion | Eligibility requires a completed diagnostic, but the 48-hour cutoff uses the diagnostic payment's full net settlement timestamp | The window can expire before the diagnostic happens. A copy change cannot fix eligibility. Keep ledger code untouched in this copy/blog scope; hold conflicting timing copy until the owner authorizes a separate financial change or approves different truthful wording. Recommend a separately reviewed change that ultimately aligns with the supplied intended rule. |
| Package validity clock | Begins at the actual date and time payment is received | Expiration is a Cairo calendar **date**, calculated from the settlement day's midnight; activation occurs after full net settlement | Exact timestamp expiry and treatment of partial payments are consequential entitlement changes. Recommend a separate scoped decision/change; do not change existing students' expiry or rights through a copy update. Hold incompatible timing wording until settled. |
| Immediate confirmation | Successfully submitted appointment confirmed; payment arranged manually afterward | BookingService already creates confirmed appointments; Terms says lesson slots are confirmed after payment verification | Approve surgical replacement of that conflicting Terms statement, preserving separate package/payment semantics. Do not change the working booking confirmation logic. |
| Package refund | Standard 14-calendar-day request window, actual package payment less consumed sessions at the package rate, clamped to zero; handled manually | Terms prohibits refunds after first lesson and restricts unused/pro-rated refunds; ledger can record bounded manual refunds but does not implement this policy as an automatic calculator | Approve replacing conflicting refund paragraphs with supplied Policy 1.0. Tutor must operate the approved manual policy; no automatic gateway or silent accounting changes. |
| Diagnostic/individual cancellation refund | Eligible cancellation at least four hours ahead can receive full refund or rebooking | Terms deducts non-recoverable PayPal fees from eligible diagnostic refunds | Decide whether the supplied full-refund rule supersedes that deduction. Recommend using supplied Policy 1.0 consistently once expressly approved. |
| Rescheduling | Source allows eligible cancellation/rebooking at four hours; new appointments need 24 hours' notice | Existing direct policy/portal rescheduling cutoff is 24 hours; public changes direct the student to contact the tutor | Keep existing online reschedule protection. Approve explicit wording that changes within 24 hours are handled by contacting Abdallah, with eligible cancellation/rebooking subject to policy/new-slot availability. Do not imply a four-hour self-service rescheduling guarantee. |

**Owner choices:**

1. Should the diagnostic-credit and exact-validity discrepancies be handled in a separately approved business-rule change, with those copy lines held meanwhile (**recommended**), or should supplied wording be expressly revised to match current behavior? Including financial/entitlement changes in this assignment needs explicit scope approval and an additional reviewed plan before those changes.
2. May the supplied Policy 1.0 replace the conflicting Terms payment/refund paragraphs, with a dedicated versioned booking/cancellation/refund page using the existing CMS route? **Recommended: yes**, while preserving unrelated Terms and the Privacy Policy. Confirm any policy effective date or revised text; do not invent it.
3. May rescheduling microcopy distinguish the existing 24-hour online cutoff from four-hour cancellation eligibility and direct-contact requests? **Recommended: yes.**

This is a consistency comparison of supplied wording and the application, not an independent legal rewrite or interpretation of consumer law. No policy has been changed or published.

### D3 — QA visibility without breaking assigned resources or entitlements

The synthetic resource has **zero local LMS lesson-block references and one existing student resource assignment**. The student assignment opener requires the resource to remain published. Archiving that record would therefore change an existing student's access, even though it removes the public card.

**Recommendation:** approve explicit visibility controls with defaults that preserve existing legitimate behavior:

- Add a separate public-booking visibility flag to SessionType; retain `active`, typed funding/entitlement configuration and all historical records. Keep the diagnostic publicly selectable and hide the two identified QA choices. Apply the same public eligibility checks to listing, selection and server-side public booking creation, so a hidden ID cannot be posted directly.
- Add a separate **public catalogue visibility** flag to Resource, leaving its publication status and authorized assignment/download behavior intact. Remove the identified synthetic record from Home, the public resource catalogue and blog related-content selectors. Keep the existing student assignment route and LMS publication semantics unchanged. This is catalogue control, not a replacement for authorization or a claim that an unlisted URL is private.
- Disable the QA-configured public engagement banner display using its existing configuration, preserving actual analytics data and services. Remove unsupported About statistics as part of applying the source narrative. Do not invent replacement statistics.
- Do not classify the existing published blog as QA merely because it is short. No record deletions, broad status changes, title-based exclusion rules or production edits.

Alternative: relocate QA data into a dedicated test dataset and deliberately retire its live-development associations, but that requires separate record/access decisions. Simply deactivating session types or archiving the assigned resource is not the recommended shortcut.

**Owner choice:** approve the narrowly identified local catalogue/booking/banner visibility changes and these small flags, or nominate records that must remain publicly exposed. Production visibility changes remain a separate approval.

### D4 — English/French/German rollout and unavailable translations

The source document supplies English public prose and Arabic examples, not approved French/German versions. Current JSON UI translations and entity fallbacks exist; the inspected article/resources/FAQs have no corresponding French/German content translations. Reusing stale translated policy/pricing prose can preserve contradictory claims.

**Recommendation:** implement the supplied English source first, preserve the existing language infrastructure, and use explicit English fallback for changed website content without an approved translation. Do not treat old conflicting translations as current approved content. Existing CMS revision/stale mechanisms should be used rather than overwritten.

For articles, keep the existing English URLs stable; add routes through the established locale structure. Listings should discover articles in the selected language; when no such publications exist, show the approved empty/unavailable state and a clear option to browse English. Corresponding article language links and `hreflang` must represent only actual published translations. Switching to an unavailable translation must not send the reader to an unrelated homepage or mislabel English text as French/German.

Alternative: provide approved French/German website wording before rollout, or authorize drafting those **website-copy** translations for review before publication. Neither option authorizes inventing or publishing translated article bodies.

**Owner choice:** approve English-first with honest fallback/unavailable states, or require approved French/German public copy before completion. Language-specific content deferred by this choice must be recorded explicitly.

### D5 — Editorial conflicts, public terminology and optional controls

**Recommendations requiring editorial/scope confirmation:**

- Use Games 3.0/FAQ's accurate spoken, multilingual storytelling description on Home, replacing the earlier ambiguous few-words wording. Preserve the actual game and its verified modes/difficulties.
- Describe the Alphabet Workbook companion video series as **planned**, as the brief and FAQ specify. Do not imply a published video series, manufacture links, or add the external game's newer exercises to the catalogue.
- Let the latest Word document supersede the old public singular-Blog terminology requirement: public navigation “Blogs” and editorial labels “Articles” where supplied. Keep internal `Blog` names, existing English routes and stored URLs. The older singular-label test must be updated only after this supersession is approved.
- Keep the current navigation order where repeated source versions differ only in FAQ/Blogs ordering; use the approved brand and labels. This does not need a new navigation architecture.
- Preserve the resource access form's required **name and email** and add the existing simple Name label. The document's email-only illustration is not permission to remove lead-capture validation.
- Approve a minimal FAQ topic grouping/search extension to make the 34-question source and supplied search/no-result controls usable. This requires a nullable topic/group identifier for FAQ records and an admin assignment field, while preserving records, revisions and the existing accordion. Do not infer source group from question title at runtime.
- For Resources/Games, apply supplied discovery labels where controls already work. **Defer new resource/game search and additional filter models** unless separately requested; keep the small real inventory usable and omit controls with no practical function. Blog search/categories/sort remain part of the proposed approved workstream.

Alternative: request resource/game search now as an explicit modest scope addition, or keep FAQ as grouped/ungrouped copy with search controls expressly deferred. Either choice must be recorded so missing functionality is not presented as implemented.

**Owner choice:** approve these corrections, terminology and FAQ extension, with additional resource/game controls deferred, or specify the alternatives required.

### D6 — Genuine workbook, contacts and public author identity

**Needed owner information:**

- The actual Alphabet Workbook file/location and any real existing cover/detail URL. It is described as genuine in the brief, but it is unavailable in this local catalogue. Do not create a placeholder downloadable workbook or broken CTA.
- Verified contact email, WhatsApp/Telegram destinations and social profile URLs to display. Current generic/placeholder settings are insufficient; only owner-confirmed channels should be promoted.
- Confirmation that the supplied “Written by: Abdallah” attribution applies to the existing article, or the correct public author display. The internal administrator account is an audit identity, not automatically a publishable author biography.

Recommend omitting unverified links/author detail or holding the affected block until supplied, while continuing independent approved work. The video series remains planned. Owner information is not inferred from unrelated accounts or invented.

## E. Recommended implementation plan

The brief's eight stages are preserved. Decisions are prerequisites to dependent stages. Functional blog controls can be prepared in Stage 6 and wired in Stage 7; the intermediate result is not marked complete or shipped with decorative controls.

| Stage | Concrete work | Approval dependencies | Verification and exit condition |
| --- | --- | --- | --- |
| 0 — approved working baseline | Record owner responses; recheck current Git/LMS work; reuse suitable checkout/worktree; create dedicated `codex/` branch when appropriate; confirm safe test connection and capture only scoped content snapshots | Overall plan | No overwritten shared files or unintended environment changes; tracked scope agreed |
| 1 — public content foundation | Approved brand, header/footer/shared labels/SEO defaults; content key inventory; translation fallback policy; targeted settings/content mutation plan | D4, D5; verified contacts D6 | Existing nav/portal links, mobile keyboard access, locale behavior; no stale contradictory claims |
| 2 — core pages | H nine sections, P eight sections, A six sections; modest layout support; standard/credited pricing separation; reuse authoritative existing business values | D2 for timing/legal assertions; D5 game correction | Complete section checklist, price calculations, bounded support claims, responsive rendering; incompatible policy lines held explicitly |
| 3 — learning discovery | R/G introductions, descriptions/details and actual states; workbook only when real asset provided; approved QA catalogue controls; Home publication-scope correction | D3, D5, D6 | Genuine game destination; safe existing resource access and assigned-resource access; QA/future entries not displayed in discovery |
| 4 — booking content | All four steps, intake version/helpers, hold/review/confirmation/payment/status/error/policy copy; public-booking visibility checks if approved | D2, D3; approved short-form content version | Existing slots/holds/idempotency/UTC/DST/24-hour rule remain intact; successful unpaid appointment labelled confirmed; no auto-payment/account promise |
| 5 — FAQ and policy consistency | Import 34 approved answers/groups using CMS revisions; six appropriate Home FAQ entries; approved minimal FAQ search/group UI; approved policy/Terms reconciliation | D2, D5; D4 translation policy | Questions all covered; FAQ/Pricing/Booking/Terms agree; real search/reset/accordion; existing history preserved |
| 6 — public Blogs V3 | Listing/article/empty/unavailable/related/navigation copy around preserved records; article reading styles; real image fallback; translated-route/SEO integration prepared | D1, D4, D5; D6 public author | No body replacement, invented article, irrelevant featured area or draft leakage; layout and routes checked |
| 7 — publishing completion | Approved migrations; categories and relationships; standalone editor and safe profile; media picker/upload/alt/reference guards; draft/preview/publish/dirty actions; search/sort/page; translation management; canonical/social/schema/sitemap and practical SEO checks | D1; D3 QA eligibility; D4 translation policy | Meaningful admin/public/editor/security/data-preservation tests plus browser workflow; no LMS editor/service/controller/model/view changes |
| 8 — regression and acceptance | Cross-page editorial review, selected booking/student/resource/game/localization/analytics regressions; build/formatter/static analysis; final mobile/desktop/RTL review; report commands/results and unresolved items | All required decisions/assets resolved, or expressly documented approved deferrals | No unsupported completeness claim; remaining risks disclosed; owner can review scoped diff and actual results |

Do not deploy, merge into shared work, change production data, publish new educational articles, or send messages as part of this approval. Production rollout would be a separate concrete review/authorization step.

### Planned implementation boundaries

- **Primary modules:** existing CMS Blog models/services, public/admin Blog controllers and views; public page controllers/views; language JSON; shared public layout; existing CMS settings/content revision workflow; current media picker/guards.
- **Additive Blog schema:** small category table/reference, image alt/editorial update fields, aligned canonical storage, and three relationship tables. Preserve existing Blog table/name, author IDs, group UUIDs, revisions and old-slug reservations.
- **Separately approved public support schema:** public-booking SessionType visibility, Resource catalogue visibility, and FAQ topic assignment under D3/D5. Default legitimate records to current visibility; target only owner-approved records. These flags must not redefine existing `published()`/active/entitlement semantics globally.
- **Public URL changes:** add blog support to the existing localized route/URL service; retain `/blog` and `/blog/{slug}` English URLs. Scope shared layout changes to explicit blog metadata inputs and preserve existing page/resource/game fallback semantics.
- **Publication/SEO:** publication eligibility remains server-authoritative; original first publication time remains stable; only meaningful editorial updates receive the added update date. Never infer author credentials or image metadata. Retain intentional canonical overrides; sitemap contains eligible canonical site URLs, not previews, drafts, alternate redirect aliases, or external URLs.
- **Security/media:** server sanitization and image checks apply regardless of editor client validation. Article-specific directional markup is narrowly allowlisted; existing LMS sanitizer calls retain their default behavior. Inline image deletion guards include revision snapshots. Do not broaden storage/disks/MIME types, allow SVG/data images, or create a public upload bypass.
- **Search/performance:** bounded title/excerpt search, whitelisted sort values, category/locale/publication predicates, eager-loaded card relationships and ordinary pagination with retained filters. Avoid searching raw HTML markup or loading the whole catalogue. No Scout/external search dependency is proposed.
- **No unrelated edits:** do not change ledger/refund/expiry/typed entitlements, student identity, Student Portal/LMS routes or material authorization, availability/holding internals, analytics architecture, or educational article bodies. D2 financial behavior changes require their own explicitly approved plan.

## F. Testing evidence and planned acceptance checks

### Discovery evidence actually obtained

Material read-only commands included:

```text
git status --short
git rev-parse HEAD
git branch --show-current
php --version
composer show --direct
php artisan env --no-interaction
php artisan route:list --except-vendor --no-interaction
php artisan route:list --path=blog --json --no-interaction
```

Results: local environment, expected stack/branch, existing public/admin blog routes, and the three pre-existing untracked reports. File searches/reads covered the mapped controllers, views, model/service/schema/translation/media paths, relevant tests and local documentation. Some broad discovery output was truncated; focused follow-up reads were used for the findings reported here.

Laravel Boost's tools are installed but were not directly exposed as tool handles in this session. The registered `boost:execute-tool` Artisan interface was used instead. For example, this exact read-only invocation verified the live Terms translation:

```powershell
$toolArguments = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes('{"query":"SELECT p.slug, pt.locale, pt.status, (SHA2(pt.content, 256) = SHA2(p.content, 256)) AS matches_base_content FROM pages p JOIN page_translations pt ON pt.page_id = p.id WHERE p.slug = \"terms\""}'))
php artisan boost:execute-tool 'Laravel\Boost\Mcp\Tools\DatabaseQuery' $toolArguments --no-interaction
```

Result: published English translation, matching base Terms content. Other read-only query payloads inspected public Blogs/Resources/Games/SessionTypes, public settings, FAQ/form metadata and reference **counts**, not student identities or answers. The resource reference count returned zero LMS blocks and one student assignment. DatabaseQuery runs SELECT inspection within the tool's read-only transaction. DatabaseSchema inspected relevant storage; GetAbsoluteUrl resolved the local blog URL; SearchDocs returned Laravel 13 guidance for file validation, relationships and pagination/filter query strings.

Browser evidence: actual public page text and links were inspected without form submission, slot selection, booking creation, publishing or uploads. Blog DOM metadata confirmed the wrong homepage canonical/OG/hreflang defaults. Booking showed the two QA options. Home/Resources showed the synthetic fixture. The external game's language/practice/mode/difficulty screens were inspected; no round was started and no provider key was entered. The web text-fetch tool could not retrieve that game; the browser supplied the live interface evidence instead.

**Automated tests, build, formatter, static analysis and migrations: NOT RUN in discovery.** The approval checkpoint prohibits implementation/database changes; RefreshDatabase tests would refresh the separate test schema. No existing historical “all tests pass” receipt is being represented as a current result. There are no failed or skipped test results from this task because no test run has occurred. Administrator interactive behavior and responsive/RTL acceptance are not yet verified end to end.

### Planned meaningful test coverage

| Area | Coverage after approval | Existing relevant test files / additions |
| --- | --- | --- |
| Public content/contracts | Source section checklist, critical diagnostic/price/state/policy consistency and genuine destinations; avoid brittle cosmetic snapshots | `PublicExperienceTest.php`, selected new content/FAQ contracts where valuable |
| Booking safety | Public hidden-session rejection; signed hold ownership, expiration/conflict, duplicate confirmation, advance rule, local/Cairo/DST display and state/payment distinctions | `BookingEngineTest.php`, `BookingHoldAuthenticationTest.php`, `BookingLanguageSwitchSyncTest.php`, `BookingLifecycleAndPolicyCutoffTest.php`, `TimezoneTest.php`, `DstGapAndFoldHandlingTest.php`, `BookingCreationNotificationTest.php`, `StudentReschedulingTest.php` |
| Authoring/security | Draft with incomplete body, publish required sanitized content, update/version conflict, admin role denial, private preview, unpublish/direct URL privacy, unsafe HTML/URL/image rejection | Extend `BlogPublishingTest.php`; add focused Blog admin/preview HTTP tests |
| Editor compatibility | Current stored HTML and representative supported legacy long-form fixtures; mixed Arabic/transliteration, lists, headings, quotes, links/images/alt; unsupported-safe source fallback; load/edit/save/reload/preview | New targeted editor tests plus browser round-trip workflow; preserve semantic content rather than identical serialization |
| Media | Image-only picker/upload under existing limits; missing/replace/remove/alt; body and body-revision asset references; shared images remain protected | `CmsAndMediaWorkflowTest.php`, `ResourceCoverSharedMediaTest.php`, new Blog inline media assertions |
| Public Blog discovery | Published/non-future/locale filters; combined search/category/sort; multi-page filters; empty states and 404; selected featured eligibility; related articles/resources/games eligibility including QA | Extend/add focused Blog public tests with real factories and explicit timestamps |
| SEO/localization | Correct title/description/canonical/OG/social/schema; preserve custom canonical; sitemap eligibility; stable publication and meaningful update; real translation groups/routes/hreflang; absent translation honesty | Blog metadata/translation/sitemap tests; `LocalizedRoutingAndBookingStateTest.php`, `CmsTranslationAndRevisionsTest.php`, `AdminTranslationWorkflowTest.php` |
| Cross-system preservation | Existing required resource lead capture/tokens, assigned synthetic resource still accessible to its authorized student after catalogue hiding, game destination/tracking, Student Portal login/navigation, unchanged default shared sanitizer | `PublicExperienceTest.php`, `ResourceSafeReplacementTest.php`, `ExternalResourceLinkingTest.php`, `StudentAuthenticationTest.php`, `AdministratorAuthorizationTest.php`, relevant existing LMS sanitizer/lesson-content tests **without modifying LMS implementation** |
| Frontend/build | Editor dirty state and clipboard success/failure, resource-access and analytics helpers; mobile/desktop keyboard/RTL/readability; production assets | Existing `tests/clipboard.test.mjs`, `tests/resource-access.test.mjs` and affected JS tests; new editor coverage where meaningful |

Run narrow files/filters as their stages change, rerun each altered test after fixing it, and broaden at final acceptance because several shared public/CMS surfaces are affected. Confirm that cached configuration cannot point tests at the live-development or production database before any test or migration. Never run broad content seeders or test refreshes against application data.

Representative planned commands (not executed yet):

```text
php artisan test --compact tests/Feature/BlogPublishingTest.php
php artisan test --compact tests/Feature/PublicExperienceTest.php
php artisan test --compact tests/Feature/BookingHoldAuthenticationTest.php
php artisan test --compact tests/Feature/BookingLanguageSwitchSyncTest.php
php artisan test --compact tests/Feature/CmsAndMediaWorkflowTest.php
php artisan test --compact tests/Feature/StudentAuthenticationTest.php
node --test tests/clipboard.test.mjs tests/resource-access.test.mjs
npm run build
php vendor/bin/pint --dirty --format agent
php vendor/bin/phpstan analyse --no-progress
```

Run additional named existing/new tests from the coverage table as needed; record the actual exact commands, test/assertion counts, failures/skips and environmental limitations here. Compare static analysis to the repository's documented existing baseline without disabling checks or changing unrelated LMS code. A full PHPUnit regression is reserved for the final shared-surface acceptance stage after targeted checks pass.

### Final editorial and browser acceptance

- Tick every source section and relevant microcopy entry against the map; compare policy statements across all pages and real states, not just screenshots.
- Review desktop, tablet and mobile layouts, menu/accordion/filter/editor keyboard access, visible focus and error/status announcements.
- Exercise the authorized draft → save → preview → publish → edit → unpublish workflow on the safe test/development dataset, with existing content integrity captured before changes.
- Check actual stored/reloaded HTML for Arabic and mixed direction; check public/private responses and image fallback behavior.
- Check URL language switching and eligible translations; no false translated bodies or homepage canonical fallbacks.
- Recheck scoped Git diff, data mutation records, unchanged LMS paths and outstanding approvals. Do not mark any item VERIFIED COMPLETE without relevant evidence.

## G. Current acceptance status and held items

1. The owner approved D1–D5, public author Abdallah and the final financial timing plan. Approved application changes are implemented locally on `codex/public-copy-blog-v3`; the earlier discovery checkpoint is superseded.
2. The Alphabet Workbook remains held until the actual file is supplied or confirmed. No workbook file or download destination has been invented.
3. Public contact/social destinations remain held until confirmed. Historical destination records are retained, with unverified links disabled for public display.
4. No new French/German translations were generated or published in the application dataset. Existing approved CMS translations retain their established publication behavior and language tagging; unavailable Blog translations show the explicit unavailable page.
5. Scoped verification passes: 241 Laravel tests, 1,830 assertions; 18 JavaScript tests; build, formatter and diff checks. Static analysis has 250 existing issues versus 255 on the starting code, with zero new issues.
6. Project-wide tests remain non-green: the final run has 72 assertion failures and 54 errors, all the same cases found on the valid original-code comparison. No new unsuccessful cases remain. The corrected public/Blog/financial and archive scopes pass. The comparison copy alone had its testing-memory cap increased to permit failure-report serialization.
7. No LMS editor/service/controller/model/view implementation was changed. The new Blog editor and safety profile are isolated; resource/student access and existing authorization checks pass in the scoped suite.
8. No deployment, push, merge, production migration, production data/configuration change or release has occurred. Deployment requires a separate explicit approval.

The task brief’s instruction to wait for approval was fulfilled. The approvals and subsequent implementation evidence above govern the current task; historical baseline findings are retained for traceability.


