# Application Capability Matrix

Authoritative pre-LMS inventory — 2026-10-06. Permanent IDs: PUB-001–PUB-026, STU-001–STU-028 and SA-001–SA-099. Retain IDs when descriptions change; add future IDs instead of renumbering. This describes current application behavior and designs later QA; it is not an execution authorization for that QA.

**Authority and baseline.** Production application SHA is **`7cb9a236b60350cfa134c2d6c94e14df1eb20c6b`**. At discovery, freshly fetched local HEAD and origin/main were **`64c7ccd6dc8744e7172591c539b00c790a070508`**. The owner explicitly approved documentation-only later commits on 2026-10-06. Their only differences from the deployed SHA were PRE_LMS_PRODUCTION_DEPLOYMENT_REPORT.md, REMAINING_FEATURES_IMPLEMENTATION_TRACKER.md and STAGE_5_DEVELOPMENT_LAUNCH_DATA_TOOLS_REPORT.md. Application code, migrations, locks/dependencies, tracked assets and runtime configuration matched the pinned candidate. Production tracked checkout was clean. This matrix/tracker delivery adds documentation commits without changing or deploying the application.

Production base: [Egyptian Arabic Tutoring](https://mohamedateff.com/arabictutor/). Route paths below are relative to that base; named routes are taken from the actual production route inventory, not invented URLs. Installed source reviewed: Laravel 13.32.0, Livewire 4.4.5, PHP 8.4; production MariaDB 11.8.9/InnoDB, 110 tables and 75 migration rows. Four typed-entitlement guards and ten generated columns remain present.

**Discovery and evidence limits.** Source inspection covered active routes/middleware, policies/controllers/services/models, migration/schema constraints, Blade/Livewire/JavaScript, configuration, forms, exports, navigation and existing tests. Current production Public and signed-in Super Admin navigation/configuration/empty-state pages were cross-checked without submitting forms or toggling settings. Development & Launch navigation is hidden and its direct GET returns 404. Current production has zero Students, bookings, purchases and homework records; there is one Resource, one Game, two Forms, one staff Note and one Administrator. No synthetic account or business dataset was created by this inventory. The earlier same-SHA, separately authorized temporary-Student smoke and safe cleanup remain historical evidence, not a fresh populated Student acceptance pass.

The actual production Student guard redirects to sign-in. Source/tests establish the populated workflows below; their acceptance scenarios remain pending. Current page existence does not prove a successful state-changing workflow. Telegram index has an existing idempotent legacy importer; current settings/counts are checked for configuration preservation. Ordinary database sessions/heartbeats and the one authorized final outbound status receipt are expected runtime writes, not QA dataset creation. Read-only browser activity uses the existing staff-exclusion behavior; no public form, hold, payment, teaching response, reset, archive upload or restore was submitted.

**Verification basis.** Existing tests listed per capability are inspected evidence, not a claim that each suggested scenario was newly executed or completely covered. The unchanged pinned application previously passed 1,034 current-schema tests / 8,855 assertions, 190 focused tests / 1,134 assertions, 26 dedicated MariaDB checks / 152 assertions and 18 JavaScript tests, with production build/cache compilation and audits passed. Those recorded gates are reused for this documentation-only task; tests/builds are not redundantly rerun. Existing static-analysis debt remains 257 findings, zero new at release. Current inventory checks validate all 153 IDs, required fields, route names, file/test references, related IDs, QA classes, summary counts and documentation whitespace.

**Shared interpretation.** Canonical scheduled instants are UTC. Business Timezone governs weekly availability, operational dates and report boundaries; customer/student zones govern displayed lesson times and explicitly chosen planning dates. Locale (en/fr/de) and 12/24-hour preference do not change canonical instants. One-hour and two-hour rights are separate allocation types with explicit required units: price, duration or offer names never authorize conversion. Cancellation/restoration follows original debit provenance and immutable policy snapshots. Money received is an actual PaymentRecord, refunds are separate cash outflows, installment schedules are expectations, and package price/discount/net/balance/overpayment are separate facts. Operational indicators describe recorded facts; they are not predictive retention scoring.

Role details appear only inside the Super Admin capabilities. Current route allowlists are included where useful; policies, ownership, MFA state, suspension and CSRF remain additional boundaries. Student foreign objects/private or unshared teaching records are denied; tutor preparation is staff-only. Ordinary Super Admin access does not authorize trusted-console remediation, disclosure of raw backups or disabled destructive tools. No LMS course/module/enrollment system or predictive learning engine is included.

**Excluded/compatibility surfaces.** Internal landing and /book redirects are aliases, not extra capabilities. Localized aliases are grouped with their owning capability. The legacy public verify-pin endpoints always return 404; the current immediate Resource gate is PUB-015. Public reschedule GET directs the user to confirmation/contact instructions and POST returns 403; Student rescheduling is STU-010. Legacy Articles are not a second public Blog system. Framework health/storage/Livewire transport routes and generated route-cache names are not business capabilities. Local/production route-list totals (343/349) differ only through these known framework/generated transport registrations; active application routes/source match. Authenticated draft previews and Telegram sample preview are active and included.

**How to use the QA fields.** Synthetic = Yes means a complete acceptance scenario needs disposable synthetic entities/facts; it does not mean creation is authorized now. READ-ONLY can cover the existing page, configuration or safe output even when a capability also has write actions. Partial/read-only checks do not certify mutations. DESTRUCTIVE-PREVIEW and DESTRUCTIVE-EXECUTION are explicit special gates; no preview or execution happened here. Related IDs identify prerequisite authorities and related UI/flows; reciprocal links are not a strict execution DAG.

| Summary (unique IDs; categories overlap) | Count |
|---|---:|
| Public | 26 |
| Student Portal | 28 |
| Super Admin | 99 |
| Total | 153 |
| Requiring synthetic data | 139 |
| Requiring financial scenarios | 24 |
| Requiring booking scenarios | 40 |
| Requiring teaching or Resource scenarios (union) | 48 |
| Requiring analytics scenarios | 17 |
| Destructive or security/permission-sensitive (union) | 133 |
| DESTRUCTIVE-EXECUTION subset | 11 |

The security union includes ordinary sign-in/ownership/permission checks; it is not a count of destructive buttons. Destructive execution is the separate 11-ID subset. A capability may require several scenario types, so category totals must not be added together.

**Final preservation check before notification.** A second production read-only snapshot matched every protected business-table digest, all table counts, schema/migrations, owner protected fields, private Resource hashes and environment/cache/lock/manifest/wrapper continuity hashes. Telegram bot/destination/rule state and delivery totals were unchanged at that checkpoint. Only four existing runtime settings advanced: last_holds_cleanup_at, last_scheduler_run_at, telegram.last_tick_at and telegram.last_watchdog_run_at. Both destructive flags and maintenance remained false. Fresh production Git still reported the exact pinned SHA and no tracked changes; the historical untracked environment backup was preserved. Fresh Public/Admin browser warning/error logs were empty. The temporary inventory tab was closed and the owner's dashboard left open.

## PUBLIC-FACING

[PUB-001](#pub-001) Home and configurable landing sections · [PUB-002](#pub-002) Public navigation, footer and responsive menu · [PUB-003](#pub-003) English, French and German route/translation presentation · [PUB-004](#pub-004) About, FAQ, legal and published custom pages · [PUB-005](#pub-005) Coaching-track and lesson pricing presentation · [PUB-006](#pub-006) Choose an active public lesson format · [PUB-007](#pub-007) Searchable timezone selection, flags and local slot labels · [PUB-008](#pub-008) Customer-calendar dates and authoritative available slots · [PUB-009](#pub-009) Authenticated temporary reservation holds · [PUB-010](#pub-010) Identity details and current pre-booking questionnaire · [PUB-011](#pub-011) Review and atomically confirm a direct booking · [PUB-012](#pub-012) Token-protected confirmation, calendar download and contact-to-reschedule instruction · [PUB-013](#pub-013) Customer cancellation through a confirmation token · [PUB-014](#pub-014) Published Resource Library and translated detail pages · [PUB-015](#pub-015) Immediate gated Resource request and single-use access grant · [PUB-016](#pub-016) Authorized private Resource file download or configured external redirect · [PUB-017](#pub-017) Published articles, sanitized reading and old-slug redirects · [PUB-018](#pub-018) Available/coming-soon learning game catalog and playable/linked content · [PUB-019](#pub-019) Configured native social-channel links including Reddit · [PUB-020](#pub-020) Floating and footer WhatsApp entry · [PUB-021](#pub-021) Emergency announcement rendering and dismissal · [PUB-022](#pub-022) Application Maintenance Mode public response · [PUB-023](#pub-023) Live visitors, prior-month traffic and collective practice counters · [PUB-024](#pub-024) First-party visitor, attention, attribution and semantic interaction tracking · [PUB-025](#pub-025) Public Student and staff authentication entry pages · [PUB-026](#pub-026) Scheduled promotional bars, modal and inline card

### PUB-001

**Home and configurable landing sections**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Public content. Home and configurable landing sections. Published content and configured section switches determine rendering; empty libraries remain valid. |
| UI / routes | Home; hero, method, lesson offer, Resource/Game teasers and FAQ<br>`GET\|HEAD /` — `home`<br>`GET\|HEAD /fr` — `home.fr`<br>`GET\|HEAD /de` — `home.de` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Settings, published Resources/Games, active FAQs and counter payload. |
| Data created / changed | None. |
| Business rules | Published content and configured section switches determine rendering; empty libraries remain valid. Marketing copy is not a financial ledger. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Cairo/business clock and dated counter payload; otherwise content has no scheduling semantics. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Published content and configured section switches determine rendering; empty libraries remain valid. Marketing copy is not a financial ledger. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/PublicExperienceTest.php](tests/Feature/PublicExperienceTest.php)<br>[tests/Feature/StageOnePresentationTest.php](tests/Feature/StageOnePresentationTest.php)<br>[tests/Feature/CmsDraftAndPreviewMatrixTest.php](tests/Feature/CmsDraftAndPreviewMatrixTest.php) |
| Read-only / synthetic QA | **Read-only:** Yes, current output can be inspected without domain writes.<br>**Synthetic data required for complete scenario:** No; existing surface/browser state is sufficient, with configuration or security authorization still required where stated. |
| Recommended acceptance scenario | Open desktop/mobile home; follow visible CTAs; confirm configured/empty sections and absence of draft content. |
| Dependent / related capability IDs | [PUB-002](#pub-002), [PUB-004](#pub-004), [PUB-023](#pub-023) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/HomeController.php](app/Http/Controllers/HomeController.php)<br>[resources/views/public/home.blade.php](resources/views/public/home.blade.php) |
| QA needs | READ-ONLY, PUBLIC-INTERACTION |

### PUB-002

**Public navigation, footer and responsive menu**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Navigation. Public navigation, footer and responsive menu. Navigation keeps real public destinations, language links, Student entry and booking CTA; mobile menu does not alter authorization.. |
| UI / routes | Public header/footer; mobile menu<br>`GET\|HEAD /` — `home`<br>`GET\|HEAD /fr` — `home.fr`<br>`GET\|HEAD /de` — `home.de` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Localized URLs, branding and enabled SocialLinks. |
| Data created / changed | Browser menu state only. |
| Business rules | Navigation keeps real public destinations, language links, Student entry and booking CTA; mobile menu does not alter authorization. |
| Configuration | Public brand/homepage settings and enabled social rows. |
| Timezone | Not applicable to navigation. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Navigation keeps real public destinations, language links, Student entry and booking CTA; mobile menu does not alter authorization. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/AdminDrawerAndSocialAccessibilityTest.php](tests/Feature/AdminDrawerAndSocialAccessibilityTest.php)<br>[tests/Feature/PublicExperienceTest.php](tests/Feature/PublicExperienceTest.php)<br>[tests/Feature/LocalizedPublicFlowAndSeoTest.php](tests/Feature/LocalizedPublicFlowAndSeoTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** No; existing surface/browser state is sufficient, with configuration or security authorization still required where stated. |
| Recommended acceptance scenario | Open/close mobile menu; navigate each enabled item; verify keyboard focus and footer destinations. |
| Dependent / related capability IDs | [PUB-003](#pub-003), [PUB-019](#pub-019), [PUB-025](#pub-025) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [resources/views/layouts/public.blade.php](resources/views/layouts/public.blade.php)<br>[app/Http/Controllers/HomeController.php](app/Http/Controllers/HomeController.php) |
| QA needs | READ-ONLY, PUBLIC-INTERACTION |

### PUB-003

**English, French and German route/translation presentation**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Localization/SEO. English, French and German route/translation presentation. Explicit locale choice takes precedence; first-visit FR or DE/AT country hints select language, not scheduling timezone. |
| UI / routes | Language selector, localized public paths and SEO head<br>`GET\|HEAD /` — `home`<br>`GET\|HEAD /fr` — `home.fr`<br>`GET\|HEAD /de` — `home.de`<br>`GET\|HEAD /fr/ressources` — `resources.fr`<br>`GET\|HEAD /de/ressourcen` — `resources.de`<br>`GET\|HEAD /fr/reservation` — `booking.fr`<br>`GET\|HEAD /de/buchen` — `booking.de` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Published translations/source revisions, localized route map, locale cookie/session and IP country hint. |
| Data created / changed | Public locale browser cookie/session preference. |
| Business rules | Explicit locale choice takes precedence; first-visit FR or DE/AT country hints select language, not scheduling timezone. English fallback/stale translation handling preserves unpublished drafts; canonical/hreflang/trailing-slash rules apply. |
| Configuration | en/fr/de translations, source revision relation, public locale preference. |
| Timezone | Locale and customer/business timezone are independent. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Explicit locale choice takes precedence; first-visit FR or DE/AT country hints select language, not scheduling timezone. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/LocalizedPublicFlowAndSeoTest.php](tests/Feature/LocalizedPublicFlowAndSeoTest.php)<br>[tests/Feature/LocalizedRoutingAndBookingStateTest.php](tests/Feature/LocalizedRoutingAndBookingStateTest.php)<br>[tests/Feature/CountryInitialLocaleTest.php](tests/Feature/CountryInitialLocaleTest.php)<br>[tests/Feature/BookingLanguageSwitchSyncTest.php](tests/Feature/BookingLanguageSwitchSyncTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** No; existing surface/browser state is sufficient, with configuration or security authorization still required where stated. |
| Recommended acceptance scenario | Inspect all three languages, available translation/fallback and query preservation; restore browser language afterward. |
| Dependent / related capability IDs | [PUB-002](#pub-002), [SA-066](#sa-066) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Middleware/SetRequestLocale.php](app/Http/Middleware/SetRequestLocale.php)<br>[app/Domains/CMS/Services/LocalizedUrlService.php](app/Domains/CMS/Services/LocalizedUrlService.php)<br>[app/Http/Controllers/HomeController.php](app/Http/Controllers/HomeController.php)<br>[app/Http/Controllers/ResourceController.php](app/Http/Controllers/ResourceController.php)<br>[app/Http/Controllers/BookingController.php](app/Http/Controllers/BookingController.php) |
| QA needs | READ-ONLY, PUBLIC-INTERACTION |

### PUB-004

**About, FAQ, legal and published custom pages**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Public content. About, FAQ, legal and published custom pages. Published current content is served; drafts/archived pages are excluded. |
| UI / routes | About/FAQ/Terms/Privacy and /p/{slug}<br>`GET\|HEAD /about` — `about`<br>`GET\|HEAD /faq` — `faq`<br>`GET\|HEAD /terms` — `terms`<br>`GET\|HEAD /privacy` — `privacy`<br>`GET\|HEAD /p/{slug}` — `page.show`<br>`GET\|HEAD /fr/a-propos` — `about.fr`<br>`GET\|HEAD /de/ueber-uns` — `about.de`<br>`GET\|HEAD /fr/faq` — `faq.fr`<br>`GET\|HEAD /de/faq` — `faq.de`<br>`GET\|HEAD /fr/conditions` — `terms.fr`<br>`GET\|HEAD /de/agb` — `terms.de`<br>`GET\|HEAD /fr/confidentialite` — `privacy.fr`<br>`GET\|HEAD /de/datenschutz` — `privacy.de`<br>`GET\|HEAD /fr/p/{slug}` — `page.show.fr`<br>`GET\|HEAD /de/p/{slug}` — `page.show.de` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Business copy, active FAQs, published Pages/translations and sanitized content. |
| Data created / changed | None. |
| Business rules | Published current content is served; drafts/archived pages are excluded. FAQ expansion is local UI interaction. A separate free-form Contact submission endpoint is not present. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Scheduled instants stored UTC; customer timezone shown for booking; other dated aggregates use Business Timezone. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | FAQ expansion; custom page selected by slug; no public universal content search. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | A separate free-form Contact submission endpoint is not present. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/CmsAndMediaWorkflowTest.php](tests/Feature/CmsAndMediaWorkflowTest.php)<br>[tests/Feature/CmsDraftAndPreviewMatrixTest.php](tests/Feature/CmsDraftAndPreviewMatrixTest.php)<br>[tests/Feature/PublicExperienceTest.php](tests/Feature/PublicExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Yes, current output can be inspected without domain writes.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Open each observed legal/content page; inspect FAQ expansion and draft/unknown slug denial using later disposable content. |
| Dependent / related capability IDs | [PUB-003](#pub-003), [SA-064](#sa-064), [SA-065](#sa-065) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/PageController.php](app/Http/Controllers/PageController.php) |
| QA needs | READ-ONLY, PUBLIC-INTERACTION |

### PUB-005

**Coaching-track and lesson pricing presentation**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Pricing. Coaching-track and lesson pricing presentation. Shows Diagnostic, Foundation, Fluency and conditional/unlisted offers with diagnostic-credit explanation. |
| UI / routes | Pricing navigation /pricing<br>`GET\|HEAD /pricing` — `pricing`<br>`GET\|HEAD /fr/tarifs` — `pricing.fr`<br>`GET\|HEAD /de/preise` — `pricing.de` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Pricing view/copy and locale URLs. |
| Data created / changed | None. |
| Business rules | Shows Diagnostic, Foundation, Fluency and conditional/unlisted offers with diagnostic-credit explanation. Booking SessionType prices and staff purchase presets have separate authorities; browsing does not buy/grant/charge anything. |
| Configuration | Pricing view and canonical purchase presets in StudentLedgerService; active SessionTypes govern booking offers. |
| Timezone | Scheduled instants stored UTC; customer timezone shown for booking; other dated aggregates use Business Timezone. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Booking SessionType prices and staff purchase presets have separate authorities; browsing does not buy/grant/charge anything. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/PublicExperienceTest.php](tests/Feature/PublicExperienceTest.php)<br>[tests/Feature/TypedEntitlementsTest.php](tests/Feature/TypedEntitlementsTest.php)<br>[tests/Feature/CashierBillingReconciliationTest.php](tests/Feature/CashierBillingReconciliationTest.php) |
| Read-only / synthetic QA | **Read-only:** Yes, current output can be inspected without domain writes.<br>**Synthetic data required for complete scenario:** No; existing surface/browser state is sufficient, with configuration or security authorization still required where stated. |
| Recommended acceptance scenario | Compare displayed offer labels/terms to authoritative preset rules; confirm no fabricated checkout/payment. |
| Dependent / related capability IDs | [PUB-006](#pub-006), [SA-027](#sa-027), [SA-028](#sa-028) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/PricingController.php](app/Http/Controllers/PricingController.php)<br>[resources/views/public/pricing.blade.php](resources/views/public/pricing.blade.php) |
| QA needs | READ-ONLY, PUBLIC-INTERACTION |

### PUB-006

**Choose an active public lesson format**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Booking. Choose an active public lesson format. Selecting an inactive/nonexistent format is rejected. |
| UI / routes | Book a Lesson; Livewire wizard Session step<br>`GET\|HEAD /booking` — `booking.index`<br>`GET\|HEAD /fr/reservation` — `booking.fr`<br>`GET\|HEAD /de/buchen` — `booking.de` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Active SessionTypes and booking-flow session state. |
| Data created / changed | Wizard selection/browser session state. |
| Business rules | Selecting an inactive/nonexistent format is rejected. Switching format clears selected slot; the public finalizer creates a direct booking, not a typed package purchase or ledger debit. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Scheduled instants stored UTC; customer timezone shown for booking; other dated aggregates use Business Timezone. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Selecting an inactive/nonexistent format is rejected. Switching format clears selected slot; the public finalizer creates a direct booking, not a typed package purchase or ledger debit. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BookingEngineTest.php](tests/Feature/BookingEngineTest.php)<br>[tests/Feature/BookingLanguageSwitchSyncTest.php](tests/Feature/BookingLanguageSwitchSyncTest.php)<br>[tests/Feature/TypedEntitlementsTest.php](tests/Feature/TypedEntitlementsTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** No; existing surface/browser state is sufficient, with configuration or security authorization still required where stated. |
| Recommended acceptance scenario | Inspect active formats/prices and step gating without selecting a reservation slot. |
| Dependent / related capability IDs | [PUB-005](#pub-005), [PUB-007](#pub-007), [PUB-008](#pub-008), [PUB-011](#pub-011) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Livewire/BookingWizard.php](app/Livewire/BookingWizard.php)<br>[resources/views/livewire/booking-wizard.blade.php](resources/views/livewire/booking-wizard.blade.php)<br>[app/Http/Controllers/BookingController.php](app/Http/Controllers/BookingController.php) |
| QA needs | READ-ONLY, PUBLIC-INTERACTION |

### PUB-007

**Searchable timezone selection, flags and local slot labels**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Timezones. Searchable timezone selection, flags and local slot labels. Uses valid IANA zones and date-specific UTC offsets; detection cannot replace an explicit manual selection/owned hold. |
| UI / routes | Booking and shared reschedule timezone selector<br>`GET\|HEAD /booking` — `booking.index` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Timezone catalog/territory flags, customer selection and Business Timezone. |
| Data created / changed | Wizard/display browser state; an owned hold timezone can be updated after validation. |
| Business rules | Uses valid IANA zones and date-specific UTC offsets; detection cannot replace an explicit manual selection/owned hold. No-match, selected result, Escape/focus return and countryless zones are handled. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | UTC canonical slot instants; customer date/time and business equivalents; DST-aware labels. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | City/zone search; selected timezone. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Uses valid IANA zones and date-specific UTC offsets; detection cannot replace an explicit manual selection/owned hold. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/TimezoneTest.php](tests/Feature/TimezoneTest.php)<br>[tests/Feature/ReschedulePresentationTest.php](tests/Feature/ReschedulePresentationTest.php)<br>[tests/Feature/BookingLanguageSwitchSyncTest.php](tests/Feature/BookingLanguageSwitchSyncTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** No; existing surface/browser state is sufficient, with configuration or security authorization still required where stated. |
| Recommended acceptance scenario | Search Cairo/New York/Berlin/UTC; inspect flags and no-match; avoid holding a slot in this inventory. |
| Dependent / related capability IDs | [PUB-008](#pub-008), [STU-008](#stu-008) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [resources/views/components/timezone-selector.blade.php](resources/views/components/timezone-selector.blade.php)<br>[app/Domains/Timezone/Services/TimezoneService.php](app/Domains/Timezone/Services/TimezoneService.php)<br>[app/Http/Controllers/BookingController.php](app/Http/Controllers/BookingController.php) |
| QA needs | READ-ONLY, PUBLIC-INTERACTION |

### PUB-008

**Customer-calendar dates and authoritative available slots**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Availability. Customer-calendar dates and authoritative available slots. Weekly business-time windows, date exceptions, notice, horizon, buffers and existing holds/conflicts constrain slots. |
| UI / routes | Wizard Date & Time; month/calendar/slot controls<br>`GET\|HEAD /booking` — `booking.index` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | AvailabilityRules/Exceptions, active bookings/holds, SessionType and signed slot presenter. |
| Data created / changed | Selection state only before a slot hold is acquired. |
| Business rules | Weekly business-time windows, date exceptions, notice, horizon, buffers and existing holds/conflicts constrain slots. Availability duration overrides are independent of typed entitlement units; arbitrary client timestamps are not authoritative. |
| Configuration | AvailabilityRule duration/buffer/notice/horizon overrides and booking fallback settings. |
| Timezone | Generate in business zone, store/sign UTC, group by customer calendar date; resolve DST gaps/folds explicitly. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Availability duration overrides are independent of typed entitlement units; arbitrary client timestamps are not authoritative. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/AvailabilityTest.php](tests/Feature/AvailabilityTest.php)<br>[tests/Feature/CanonicalAvailabilityValidationTest.php](tests/Feature/CanonicalAvailabilityValidationTest.php)<br>[tests/Feature/DstGapAndFoldHandlingTest.php](tests/Feature/DstGapAndFoldHandlingTest.php)<br>[tests/Feature/V3SlotIdentityTest.php](tests/Feature/V3SlotIdentityTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Inspect dates/slots in two zones; later QA supplies conflict, exception, notice and DST boundary fixtures. |
| Dependent / related capability IDs | [PUB-006](#pub-006), [PUB-007](#pub-007), [SA-045](#sa-045), [SA-046](#sa-046) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Availability/Services/AvailabilityService.php](app/Domains/Availability/Services/AvailabilityService.php)<br>[app/Domains/Availability/Services/SlotResolver.php](app/Domains/Availability/Services/SlotResolver.php)<br>[app/Http/Controllers/BookingController.php](app/Http/Controllers/BookingController.php) |
| QA needs | READ-ONLY, PUBLIC-INTERACTION |

### PUB-009

**Authenticated temporary reservation holds**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Booking. Authenticated temporary reservation holds. Slot selection acquires an authenticated visitor/session-owned hold under calendar locks. |
| UI / routes | Wizard slot selection and hold countdown<br>`GET\|HEAD /booking` — `booking.index` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Canonical signed slot, visitor/session identity and current availability. |
| Data created / changed | booking_holds with opaque hold token, expiry, owned slot and possible lead details. |
| Business rules | Slot selection acquires an authenticated visitor/session-owned hold under calendar locks. Forged, expired, conflicting or mismatched holds refuse finalization; throttling limits reservation attempts. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Scheduled instants stored UTC; customer timezone shown for booking; other dated aggregates use Business Timezone. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Forged, expired, conflicting or mismatched holds refuse finalization; throttling limits reservation attempts. |
| Background / manual behavior | **Background:** Existing booking:cleanup-holds scheduled every five minutes; expired holds also fail live validation.<br>**Manual/on-demand:** User selects slot; cleanup is background declaration, not setup performed here. |
| Existing automated tests | [tests/Feature/BookingHoldAuthenticationTest.php](tests/Feature/BookingHoldAuthenticationTest.php)<br>[tests/Feature/BookingEngineTest.php](tests/Feature/BookingEngineTest.php)<br>[tests/Feature/BufferExpandedConcurrencyLockTest.php](tests/Feature/BufferExpandedConcurrencyLockTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | With an approved disposable booking slot, verify countdown, replay, expiration, foreign token and competing reservation refusal. |
| Dependent / related capability IDs | [PUB-008](#pub-008), [PUB-011](#pub-011) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Booking/Services/BookingHoldService.php](app/Domains/Booking/Services/BookingHoldService.php)<br>[app/Livewire/BookingWizard.php](app/Livewire/BookingWizard.php)<br>[app/Http/Controllers/BookingController.php](app/Http/Controllers/BookingController.php) |
| QA needs | PUBLIC-INTERACTION, BOOKING-DATA, SECURITY/PERMISSION |

### PUB-010

**Identity details and current pre-booking questionnaire**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Booking/intake. Identity details and current pre-booking questionnaire. Required first/last name, email, normalized phone/country and DOB support canonical Student provisioning. |
| UI / routes | Wizard Your Details; name/email/phone/DOB/intake fields<br>`GET\|HEAD /booking` — `booking.index` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Current published pre_booking Form version, questions, held slot and normalized identity rules. |
| Data created / changed | Owned hold lead_details, wizard state; final intake rows are written atomically on confirmation. |
| Business rules | Required first/last name, email, normalized phone/country and DOB support canonical Student provisioning. Conditional/required intake validation follows current published version; version drift/identity conflicts refuse. Language switches preserve pending inputs and owned hold state. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Scheduled instants stored UTC; customer timezone shown for booking; other dated aggregates use Business Timezone. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Conditional/required intake validation follows current published version; version drift/identity conflicts refuse. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/FormsEngineTest.php](tests/Feature/FormsEngineTest.php)<br>[tests/Feature/StudentAuthenticationTest.php](tests/Feature/StudentAuthenticationTest.php)<br>[tests/Feature/BookingLanguageSwitchSyncTest.php](tests/Feature/BookingLanguageSwitchSyncTest.php)<br>[tests/Feature/ContactIdentityTest.php](tests/Feature/ContactIdentityTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later QA supplies valid normalized identities and conditional answers; reject missing fields, ambiguous identity and stale published version. |
| Dependent / related capability IDs | [PUB-009](#pub-009), [PUB-011](#pub-011), [SA-070](#sa-070) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Livewire/BookingWizard.php](app/Livewire/BookingWizard.php)<br>[app/Domains/Booking/Services/BookingService.php](app/Domains/Booking/Services/BookingService.php)<br>[app/Http/Controllers/BookingController.php](app/Http/Controllers/BookingController.php) |
| QA needs | PUBLIC-INTERACTION, STUDENT-DATA, BOOKING-DATA |

### PUB-011

**Review and atomically confirm a direct booking**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Booking. Review and atomically confirm a direct booking. Server-held UTC times are authoritative. |
| UI / routes | Wizard Review & Confirm<br>`GET\|HEAD /booking` — `booking.index` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Owned active hold, immutable slot/configuration, identity/intake version and attribution. |
| Data created / changed | Canonical Contact/Student when needed, confirmed direct Booking/snapshots, consumed hold, BookingEvent, intake submission/answers, meeting assignment and analytics/notifications. |
| Business rules | Server-held UTC times are authoritative. Calendar/identity locks and unique idempotency prevent overlap/double creation. Direct public bookings create no package payment or typed debit; email/Telegram notification failure does not undo a valid booking. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Scheduled instants stored UTC; customer timezone shown for booking; other dated aggregates use Business Timezone. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Direct public bookings create no package payment or typed debit; email/Telegram notification failure does not undo a valid booking. |
| Background / manual behavior | **Background:** Existing notification paths may dispatch configured jobs; booking/identity transaction itself is synchronous.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BookingEngineTest.php](tests/Feature/BookingEngineTest.php)<br>[tests/Feature/CriticalBookingQAMatrixTest.php](tests/Feature/CriticalBookingQAMatrixTest.php)<br>[tests/Feature/BookingCreationNotificationTest.php](tests/Feature/BookingCreationNotificationTest.php)<br>[tests/Feature/BookingAttributionPersistenceTest.php](tests/Feature/BookingAttributionPersistenceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Confirm one approved direct QA booking; replay exact request; check one Booking, canonical identity and original snapshots, with no fabricated PaymentRecord. |
| Dependent / related capability IDs | [PUB-009](#pub-009), [PUB-010](#pub-010), [PUB-012](#pub-012), [SA-051](#sa-051) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Booking/Services/BookingService.php](app/Domains/Booking/Services/BookingService.php)<br>[app/Livewire/BookingWizard.php](app/Livewire/BookingWizard.php)<br>[app/Http/Controllers/BookingController.php](app/Http/Controllers/BookingController.php) |
| QA needs | PUBLIC-INTERACTION, STUDENT-DATA, BOOKING-DATA, SECURITY/PERMISSION |

### PUB-012

**Token-protected confirmation, calendar download and contact-to-reschedule instruction**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Booking confirmation. Token-protected confirmation, calendar download and contact-to-reschedule instruction. Opaque confirmation token grants access to its booking page. |
| UI / routes | Confirmation URL /booking/confirmation/{token}<br>`GET\|HEAD /booking/confirmation/{token}` — `booking.confirmation`<br>`GET\|HEAD /booking/confirmation/{token}/ics` — `booking.ics`<br>`GET\|HEAD /booking/{token}/reschedule` — `booking.reschedule`<br>`GET\|HEAD /fr/reservation/confirmation/{token}` — `booking.confirmation.fr`<br>`GET\|HEAD /de/buchen/bestaetigung/{token}` — `booking.confirmation.de` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Booking status, lesson/contact snapshots, reschedule existence and eligible meeting snapshot. |
| Data created / changed | None for page/ICS; normal session/page telemetry may update. |
| Business rules | Opaque confirmation token grants access to its booking page. ICS uses canonical booking times. Public reschedule GET redirects to confirmation/contact instructions; POST self-service rescheduling always returns 403 and is not a capability. Meeting links remain time/status/identity gated. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Display query timezone if valid, otherwise customer snapshot; ICS canonical instants. |
| Authorization / privacy | Possession of a valid opaque confirmation token; treat the link as private booking access, not a public listing. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | ICS only; no CSV/XLSX. |
| Edges / negative cases | Public reschedule GET redirects to confirmation/contact instructions; POST self-service rescheduling always returns 403 and is not a capability. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BookingEngineTest.php](tests/Feature/BookingEngineTest.php)<br>[tests/Feature/StudentReschedulingTest.php](tests/Feature/StudentReschedulingTest.php)<br>[tests/Feature/MeetingRotationTest.php](tests/Feature/MeetingRotationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Inspect an approved QA confirmation/ICS and invalid token; verify hidden early meeting URL and contact-to-reschedule redirect. |
| Dependent / related capability IDs | [PUB-011](#pub-011), [PUB-013](#pub-013), [STU-028](#stu-028) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/BookingController.php](app/Http/Controllers/BookingController.php)<br>[app/Domains/Booking/Services/IcsGenerator.php](app/Domains/Booking/Services/IcsGenerator.php) |
| QA needs | READ-ONLY, BOOKING-DATA, SECURITY/PERMISSION |

### PUB-013

**Customer cancellation through a confirmation token**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Booking policy. Customer cancellation through a confirmation token. Past customer cancellation is refused; cutoff and late restore/retain/deny come from booking snapshot (legacy null snapshot uses version-0 settings). |
| UI / routes | Confirmation cancel action<br>`POST /booking/{token}/cancel` — `booking.cancel` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Confirmed Booking and its immutable policy snapshot, original debit if present. |
| Data created / changed | Cancelled status/event/time/reason and policy consequence; original typed restoration only when applicable. |
| Business rules | Past customer cancellation is refused; cutoff and late restore/retain/deny come from booking snapshot (legacy null snapshot uses version-0 settings). Restoring credit reverses original quantity/allocation exactly once; cancellation creates no money refund. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Scheduled instants stored UTC; customer timezone shown for booking; other dated aggregates use Business Timezone. |
| Authorization / privacy | Valid private confirmation token, CSRF and cancellation throttle; status/policy rechecked under locks. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Past customer cancellation is refused; cutoff and late restore/retain/deny come from booking snapshot (legacy null snapshot uses version-0 settings). Restoring credit reverses original quantity/allocation exactly once; cancellation creates no money refund. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BookingLifecycleAndPolicyCutoffTest.php](tests/Feature/BookingLifecycleAndPolicyCutoffTest.php)<br>[tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php)<br>[tests/Feature/StudentLedgerTest.php](tests/Feature/StudentLedgerTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Using disposable QA bookings, compare early/late/past cancellation and repeat submission; inspect consequence and unchanged payments. |
| Dependent / related capability IDs | [PUB-012](#pub-012), [SA-043](#sa-043), [SA-047](#sa-047) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Booking/Services/CancellationService.php](app/Domains/Booking/Services/CancellationService.php)<br>[app/Domains/Booking/Services/BookingPolicyService.php](app/Domains/Booking/Services/BookingPolicyService.php)<br>[app/Http/Controllers/BookingController.php](app/Http/Controllers/BookingController.php) |
| QA needs | PUBLIC-INTERACTION, BOOKING-DATA, ENTITLEMENT-DATA, SECURITY/PERMISSION |

### PUB-014

**Published Resource Library and translated detail pages**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Resources. Published Resource Library and translated detail pages. Future/draft/withdrawn resources are excluded; metadata does not expose private storage paths. |
| UI / routes | Resources navigation; category tabs and Resource detail<br>`GET\|HEAD /resources` — `resources.index`<br>`GET\|HEAD /resources/{slug}` — `resources.show`<br>`GET\|HEAD /fr/ressources/{slug}` — `resources.show.fr`<br>`GET\|HEAD /de/ressourcen/{slug}` — `resources.show.de` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Published/time-eligible Resources, active categories and live translations. |
| Data created / changed | None. |
| Business rules | Future/draft/withdrawn resources are excluded; metadata does not expose private storage paths. Library supports category selection and 12-item pagination; detail can show English fallback/stale translation status. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Scheduled instants stored UTC; customer timezone shown for booking; other dated aggregates use Business Timezone. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | Category slug and pagination; no general public Resource text-search endpoint. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Future/draft/withdrawn resources are excluded; metadata does not expose private storage paths. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/PublicExperienceTest.php](tests/Feature/PublicExperienceTest.php)<br>[tests/Feature/ExternalResourceLinkingTest.php](tests/Feature/ExternalResourceLinkingTest.php)<br>[tests/Feature/CmsTranslationAndRevisionsTest.php](tests/Feature/CmsTranslationAndRevisionsTest.php) |
| Read-only / synthetic QA | **Read-only:** Yes, current output can be inspected without domain writes.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Inspect current Arabic Alphabet card/detail and category filters; later publication fixtures cover draft/future/missing cases. |
| Dependent / related capability IDs | [PUB-003](#pub-003), [PUB-015](#pub-015), [SA-061](#sa-061), [SA-062](#sa-062) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/ResourceController.php](app/Http/Controllers/ResourceController.php) |
| QA needs | READ-ONLY, PUBLIC-INTERACTION |

### PUB-015

**Immediate gated Resource request and single-use access grant**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Resource acquisition. Immediate gated Resource request and single-use access grant. Valid name/email and analytics visitor cookies required; untrusted secondary email does not redefine a Student identity. |
| UI / routes | Resource detail Request Free Access form<br>`POST /resources/{slug}/request` — `resources.request`<br>`POST /fr/ressources/{slug}/request` — `resources.request.fr`<br>`POST /de/ressourcen/{slug}/request` — `resources.request.de` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Published Resource, email quality, visitor/session cookies and optional authenticated Student/trusted email. |
| Data created / changed | Canonical Contact, ResourceRequest with submitted-email/Student association, attribution event, notification and 60-minute session/visitor-bound download grant. |
| Business rules | Valid name/email and analytics visitor cookies required; untrusted secondary email does not redefine a Student identity. Current gate needs no email PIN; verify-pin remains inactive/404 and has no capability ID. |
| Configuration | Resource.is_gated, email-quality checks, private storage and resource-request throttle. |
| Timezone | Scheduled instants stored UTC; customer timezone shown for booking; other dated aggregates use Business Timezone. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Valid name/email and analytics visitor cookies required; untrusted secondary email does not redefine a Student identity. Current gate needs no email PIN; verify-pin remains inactive/404 and has no capability ID. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/ExternalResourceLinkingTest.php](tests/Feature/ExternalResourceLinkingTest.php)<br>[tests/Feature/StudentSecondaryEmailTest.php](tests/Feature/StudentSecondaryEmailTest.php)<br>[tests/Feature/FeatureExpansionTest.php](tests/Feature/FeatureExpansionTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later approved QA submits a safe test lead, checks canonical association, missing cookies, invalid email and one-use session-bound grant; no PIN wait expected. |
| Dependent / related capability IDs | [PUB-014](#pub-014), [PUB-016](#pub-016), [STU-003](#stu-003) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/ResourceController.php](app/Http/Controllers/ResourceController.php) |
| QA needs | PUBLIC-INTERACTION, RESOURCE-DATA, STUDENT-DATA, ANALYTICS-DATA, SECURITY/PERMISSION |

### PUB-016

**Authorized private Resource file download or configured external redirect**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Resource delivery. Authorized private Resource file download or configured external redirect. Gated bearer and session copies are single-use and bound to matching session/visitor. |
| UI / routes | Resource detail download action<br>`GET\|HEAD /resources/{slug}/download` — `resources.download`<br>`GET\|HEAD /fr/ressources/{slug}/download` — `resources.download.fr`<br>`GET\|HEAD /de/ressourcen/{slug}/download` — `resources.download.de` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Published Resource and matching grant/session ownership; existing private file or external_url. |
| Data created / changed | ResourceDownload and resource_downloaded analytics; consumes gated cache/session grant. |
| Business rules | Gated bearer and session copies are single-use and bound to matching session/visitor. Missing file denies; external URL records delivery before redirect. Public Resource download is distinct from private teaching-assignment access and its no-store policy. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Scheduled instants stored UTC; customer timezone shown for booking; other dated aggregates use Business Timezone. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | Resource payload; no tabular export. |
| Edges / negative cases | Missing file denies; external URL records delivery before redirect. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/ExternalResourceLinkingTest.php](tests/Feature/ExternalResourceLinkingTest.php)<br>[tests/Feature/ResourceSafeReplacementTest.php](tests/Feature/ResourceSafeReplacementTest.php)<br>[tests/Feature/ResourceCoverSharedMediaTest.php](tests/Feature/ResourceCoverSharedMediaTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later approved QA captures bytes/hash or intended external redirect, then foreign/replayed grant denial; do not export private owner history for inventory. |
| Dependent / related capability IDs | [PUB-015](#pub-015), [SA-061](#sa-061) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/ResourceController.php](app/Http/Controllers/ResourceController.php) |
| QA needs | PUBLIC-INTERACTION, RESOURCE-DATA, ANALYTICS-DATA, SECURITY/PERMISSION |

### PUB-017

**Published articles, sanitized reading and old-slug redirects**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Blog. Published articles, sanitized reading and old-slug redirects. Public detail only resolves published Blogs or a published current target via 301 old-slug redirect. |
| UI / routes | Blog navigation and /blog/{slug}<br>`GET\|HEAD /blog` — `blog.index`<br>`GET\|HEAD /blog/{slug}` — `blog.show` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Published Blogs, body/SEO metadata and blog_slug_redirects. |
| Data created / changed | None. |
| Business rules | Public detail only resolves published Blogs or a published current target via 301 old-slug redirect. Drafts/previews require staff access. Legacy articles tables do not imply a second public article system. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Scheduled instants stored UTC; customer timezone shown for booking; other dated aggregates use Business Timezone. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | Published-date order and 12-item pagination; no public article search endpoint. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Legacy articles tables do not imply a second public article system. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BlogPublishingTest.php](tests/Feature/BlogPublishingTest.php)<br>[tests/Feature/PublicExperienceTest.php](tests/Feature/PublicExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Yes, current output can be inspected without domain writes.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Create later disposable published/draft/renamed Blog; check published reading, draft denial and old slug 301. |
| Dependent / related capability IDs | [SA-067](#sa-067), [PUB-024](#pub-024) |
| Production cross-check | Current Blog index cross-checked empty; populated detail/redirect requires later content fixture. |
| Current source evidence | [app/Http/Controllers/BlogController.php](app/Http/Controllers/BlogController.php)<br>[app/Domains/CMS/Services/BlogService.php](app/Domains/CMS/Services/BlogService.php) |
| QA needs | READ-ONLY, PUBLIC-INTERACTION, RESOURCE-DATA |

### PUB-018

**Available/coming-soon learning game catalog and playable/linked content**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Games. Available/coming-soon learning game catalog and playable/linked content. Only available games accept gameplay tracking. |
| UI / routes | Games and /games/{slug}; current 6-Word Story<br>`GET\|HEAD /games` — `games.index`<br>`GET\|HEAD /games/{slug}` — `games.show`<br>`POST /games/{slug}/track` — `games.track`<br>`GET\|HEAD /fr/jeux` — `games.fr`<br>`GET\|HEAD /de/spiele` — `games.de`<br>`GET\|HEAD /fr/jeux/{slug}` — `games.show.fr`<br>`GET\|HEAD /de/spiele/{slug}` — `games.show.de` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Available/coming_soon Games/translations and local game presentation. |
| Data created / changed | Game-open/start/complete first-party telemetry; browser game state. |
| Business rules | Only available games accept gameplay tracking. Configured target URLs redirect externally; embedded/current game presentation is not a course/LMS progression engine. Server bounds allowlisted action/score/duration metadata. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Scheduled instants stored UTC; customer timezone shown for booking; other dated aggregates use Business Timezone. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Configured target URLs redirect externally; embedded/current game presentation is not a course/LMS progression engine. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/PublicExperienceTest.php](tests/Feature/PublicExperienceTest.php)<br>[tests/Feature/ClientTelemetryIngestionTest.php](tests/Feature/ClientTelemetryIngestionTest.php)<br>[tests/Feature/EngagementCountersTest.php](tests/Feature/EngagementCountersTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Inspect/play the approved current game later; verify start/complete event dedupe and invalid action/nested metadata/coming-soon behavior. |
| Dependent / related capability IDs | [PUB-003](#pub-003), [PUB-024](#pub-024), [SA-068](#sa-068) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/GameController.php](app/Http/Controllers/GameController.php)<br>[resources/views/public/games/show.blade.php](resources/views/public/games/show.blade.php) |
| QA needs | PUBLIC-INTERACTION, ANALYTICS-DATA |

### PUB-019

**Configured native social-channel links including Reddit**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Communication. Configured native social-channel links including Reddit. Reddit is a supported platform alongside existing channels; disabled/unconfigured rows do not render. |
| UI / routes | Footer Connect Directly and relevant content placements<br>`GET\|HEAD /` — `home`<br>`GET\|HEAD /fr` — `home.fr`<br>`GET\|HEAD /de` — `home.de` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Enabled SocialLinks, validated URLs/labels/sort and placement metadata. |
| Data created / changed | Browser outbound navigation and semantic first-party click event. |
| Business rules | Reddit is a supported platform alongside existing channels; disabled/unconfigured rows do not render. Native keyboard-accessible links retain real hrefs. Current production has no Reddit row; generic existing channel roots are rendering evidence, not account ownership certification. |
| Configuration | SocialLinks enabled/platform/url_or_phone/label/sort; controlled by Content & FAQs. |
| Timezone | Scheduled instants stored UTC; customer timezone shown for booking; other dated aggregates use Business Timezone. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Reddit is a supported platform alongside existing channels; disabled/unconfigured rows do not render. Current production has no Reddit row; generic existing channel roots are rendering evidence, not account ownership certification. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StageOnePresentationTest.php](tests/Feature/StageOnePresentationTest.php)<br>[tests/Feature/SocialHistoryAndIpPrivacyTest.php](tests/Feature/SocialHistoryAndIpPrivacyTest.php)<br>[tests/Feature/AdminDrawerAndSocialAccessibilityTest.php](tests/Feature/AdminDrawerAndSocialAccessibilityTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** No; existing surface/browser state is sufficient, with configuration or security authorization still required where stated. |
| Recommended acceptance scenario | Inspect enabled native links without sending external messages; later approved configuration fixture tests Reddit and disabled links. |
| Dependent / related capability IDs | [SA-065](#sa-065), [PUB-024](#pub-024) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/CMS/Models/SocialLink.php](app/Domains/CMS/Models/SocialLink.php)<br>[resources/views/layouts/public.blade.php](resources/views/layouts/public.blade.php)<br>[app/Http/Controllers/HomeController.php](app/Http/Controllers/HomeController.php) |
| QA needs | READ-ONLY, PUBLIC-INTERACTION |

### PUB-020

**Floating and footer WhatsApp entry**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Communication. Floating and footer WhatsApp entry. Only configured enabled placement renders; international number and encoded message form a native WhatsApp link. |
| UI / routes | Floating WhatsApp and configured footer channel<br>`GET\|HEAD /` — `home`<br>`GET\|HEAD /fr` — `home.fr`<br>`GET\|HEAD /de` — `home.de` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Independent floating WhatsApp settings and optional SocialLink phone/message. |
| Data created / changed | Outbound browser navigation and WhatsApp click telemetry. |
| Business rules | Only configured enabled placement renders; international number and encoded message form a native WhatsApp link. Floating and footer destinations are separate current configuration, not automatically synchronized. No chat message is sent by this inventory. |
| Configuration | Floating visibility/number/default text and separate SocialLink. |
| Timezone | Scheduled instants stored UTC; customer timezone shown for booking; other dated aggregates use Business Timezone. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Floating and footer destinations are separate current configuration, not automatically synchronized. No chat message is sent by this inventory. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/PublicExperienceTest.php](tests/Feature/PublicExperienceTest.php)<br>[tests/Feature/SocialHistoryAndIpPrivacyTest.php](tests/Feature/SocialHistoryAndIpPrivacyTest.php)<br>[tests/Feature/StageOnePresentationTest.php](tests/Feature/StageOnePresentationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** No; existing surface/browser state is sufficient, with configuration or security authorization still required where stated. |
| Recommended acceptance scenario | Inspect href/placement/keyboard access; later authorized sandbox interaction verifies semantic telemetry without owner messaging. |
| Dependent / related capability IDs | [PUB-019](#pub-019), [PUB-024](#pub-024), [SA-077](#sa-077) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [resources/views/layouts/public.blade.php](resources/views/layouts/public.blade.php)<br>[resources/views/layouts/student.blade.php](resources/views/layouts/student.blade.php)<br>[app/Http/Controllers/HomeController.php](app/Http/Controllers/HomeController.php) |
| QA needs | READ-ONLY, PUBLIC-INTERACTION |

### PUB-021

**Emergency announcement rendering and dismissal**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Communication. Emergency announcement rendering and dismissal. EN/FR/DE message fallback, safe HTTPS CTA, information/warning/urgent styles and optional dismissal. |
| UI / routes | Announcement banner above public/optional Student content<br>`GET\|HEAD /` — `home`<br>`GET\|HEAD /fr` — `home.fr`<br>`GET\|HEAD /de` — `home.de` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | announcement.* enabled/audience/severity/message/CTA/window/version. |
| Data created / changed | Version-bound local browser dismissal only. |
| Business rules | EN/FR/DE message fallback, safe HTTPS CTA, information/warning/urgent styles and optional dismissal. Audience/window/message checks determine visibility; a changed version can reappear. Currently disabled and correctly absent. |
| Configuration | announcement.* settings; audience public or public_student. |
| Timezone | Business-zone scheduling; explicit DST validation when saved; UTC-compatible date comparison. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Currently disabled and correctly absent. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/AnnouncementBannerTest.php](tests/Feature/AnnouncementBannerTest.php)<br>[tests/Feature/StageOnePresentationTest.php](tests/Feature/StageOnePresentationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** No; existing surface/browser state is sufficient, with configuration or security authorization still required where stated. |
| Recommended acceptance scenario | Later authorized toggle in a controlled window tests audience, expiry, no message, stale dismissal and unsafe CTA; restore settings. |
| Dependent / related capability IDs | [SA-078](#sa-078), [PUB-003](#pub-003) |
| Production cross-check | Absence verified under disabled configuration; no setting changed to display it. |
| Current source evidence | [app/Domains/CMS/Services/AnnouncementService.php](app/Domains/CMS/Services/AnnouncementService.php)<br>[resources/js/announcement-banner.js](resources/js/announcement-banner.js)<br>[app/Http/Controllers/HomeController.php](app/Http/Controllers/HomeController.php) |
| QA needs | PUBLIC-INTERACTION, CONFIGURATION-TOGGLE |

### PUB-022

**Application Maintenance Mode public response**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Maintenance. Application Maintenance Mode public response. Returns application 503 for guests during maintenance, while staff/admin paths and health remain accessible. |
| UI / routes | Public 503 page when application setting is enabled<br>`GET\|HEAD /` — `home`<br>`GET\|HEAD /fr` — `home.fr`<br>`GET\|HEAD /de` — `home.de` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | maintenance_mode and configured maintenance message; current staff identity. |
| Data created / changed | Ordinary maintenance-visit telemetry, not business records. |
| Business rules | Returns application 503 for guests during maintenance, while staff/admin paths and health remain accessible. Separate Laravel deployment down mode is not a customer-controlled public feature. Currently off; no toggle used here. |
| Configuration | maintenance_mode and maintenance_message settings. |
| Timezone | Scheduled instants stored UTC; customer timezone shown for booking; other dated aggregates use Business Timezone. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Separate Laravel deployment down mode is not a customer-controlled public feature. Currently off; no toggle used here. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/MaintenanceModeResilienceTest.php](tests/Feature/MaintenanceModeResilienceTest.php)<br>[tests/Feature/BackupAndMaintenanceTest.php](tests/Feature/BackupAndMaintenanceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** No; existing surface/browser state is sufficient, with configuration or security authorization still required where stated. |
| Recommended acceptance scenario | Later bounded authorized toggle checks 503/no business submissions, staff bypass and maintenance analytics; restore off. |
| Dependent / related capability IDs | [SA-079](#sa-079), [SA-076](#sa-076) |
| Production cross-check | Normal public pages verified with maintenance off; enabled-state acceptance requires later explicit toggle authorization. |
| Current source evidence | [app/Http/Middleware/CheckMaintenanceMode.php](app/Http/Middleware/CheckMaintenanceMode.php)<br>[resources/views/errors/503.blade.php](resources/views/errors/503.blade.php)<br>[app/Http/Controllers/HomeController.php](app/Http/Controllers/HomeController.php) |
| QA needs | READ-ONLY, CONFIGURATION-TOGGLE, ANALYTICS-DATA |

### PUB-023

**Live visitors, prior-month traffic and collective practice counters**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Social proof. Live visitors, prior-month traffic and collective practice counters. Live distinct users use last 60 seconds. |
| UI / routes | Public top counter strips/home<br>`GET\|HEAD /` — `home`<br>`GET\|HEAD /fr` — `home.fr`<br>`GET\|HEAD /de` — `home.de` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Non-bot reporting sessions, prior-month daily/raw metrics, completed Booking durations and selected learning-section dwell. |
| Data created / changed | None beyond ordinary reporting cache. |
| Business rules | Live distinct users use last 60 seconds. Practice total combines completed lesson minutes and tracked study dwell, with human days/hours/minutes; no fake popularity values. Counter enable/source/window/templates are separate. |
| Configuration | counters.live_users.*, counters.monthly_traffic.*, counters.learning_hours.*. |
| Timezone | Previous business calendar month and bounded rolling business-day window converted to UTC; practice labels are durations, not UTC times. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Practice total combines completed lesson minutes and tracked study dwell, with human days/hours/minutes; no fake popularity values. |
| Background / manual behavior | **Background:** Existing analytics rollups support old completed days; current raw data/cache queried on-demand.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/EngagementCountersTest.php](tests/Feature/EngagementCountersTest.php)<br>[tests/Feature/CairoDailyAnalyticsRollupTest.php](tests/Feature/CairoDailyAnalyticsRollupTest.php)<br>[tests/Unit/HumanDurationFormatterTest.php](tests/Unit/HumanDurationFormatterTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later bounded analytics/completed-lesson fixtures verify distinct counts, period boundaries and duration formatting; no fake counter setting. |
| Dependent / related capability IDs | [PUB-024](#pub-024), [SA-072](#sa-072), [SA-077](#sa-077) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Analytics/Services/EngagementCounterService.php](app/Domains/Analytics/Services/EngagementCounterService.php)<br>[app/Domains/Analytics/Services/HumanDurationFormatter.php](app/Domains/Analytics/Services/HumanDurationFormatter.php)<br>[app/Http/Controllers/HomeController.php](app/Http/Controllers/HomeController.php) |
| QA needs | READ-ONLY, ANALYTICS-DATA |

### PUB-024

**First-party visitor, attention, attribution and semantic interaction tracking**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Analytics/privacy. First-party visitor, attention, attribution and semantic interaction tracking. 32 KiB payload/20-event batch/2 KiB metadata limits and per-event allowlists; clients cannot spoof server-only booking/resource/form completion. |
| UI / routes | Public page interactions; /analytics/event and compatible ingestion alias<br>`POST /analytics/event` — `analytics.track`<br>`POST /api/analytics/events` — `analytics.events` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | First-party visitor/session identity, request-country resolution, approved UTM/referrer context and exclusions. |
| Data created / changed | Visitors/sessions, validated raw events, funnel/marketing touches; transient dedupe/presence state. |
| Business rules | 32 KiB payload/20-event batch/2 KiB metadata limits and per-event allowlists; clients cannot spoof server-only booking/resource/form completion. UUID/click dedupe and presence ping avoid count inflation. Internal authenticated staff, preview/bot/excluded traffic are filtered; raw IP is not a permanent reporting identity. |
| Configuration | Analytics goals/exclusion hashes, authoritative cutover/boundaries, geo-IP settings and retention; no third-party ad tracker required. |
| Timezone | Scheduled instants stored UTC; customer timezone shown for booking; other dated aggregates use Business Timezone. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | 32 KiB payload/20-event batch/2 KiB metadata limits and per-event allowlists; clients cannot spoof server-only booking/resource/form completion. Internal authenticated staff, preview/bot/excluded traffic are filtered; raw IP is not a permanent reporting identity. |
| Background / manual behavior | **Background:** Existing daily metric/country/social aggregation and raw pruning; geo-IP update declaration. Runtime execution remains operationally dependent.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/ClientTelemetryIngestionTest.php](tests/Feature/ClientTelemetryIngestionTest.php)<br>[tests/Feature/AnalyticsValidationAndIdentityTest.php](tests/Feature/AnalyticsValidationAndIdentityTest.php)<br>[tests/Feature/AnalyticsFunnelAndAttributionTest.php](tests/Feature/AnalyticsFunnelAndAttributionTest.php)<br>[tests/Feature/SocialHistoryAndIpPrivacyTest.php](tests/Feature/SocialHistoryAndIpPrivacyTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later synthetic tagged session checks country/UTM/dwell/funnel cohorts, duplicate clicks, spoofed completion rejection and privacy-safe export. |
| Dependent / related capability IDs | [SA-072](#sa-072), [SA-073](#sa-073), [SA-074](#sa-074), [SA-075](#sa-075), [SA-077](#sa-077) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/AnalyticsController.php](app/Http/Controllers/AnalyticsController.php)<br>[app/Domains/Analytics/Services/AnalyticsService.php](app/Domains/Analytics/Services/AnalyticsService.php)<br>[resources/js/analytics-telemetry.js](resources/js/analytics-telemetry.js) |
| QA needs | PUBLIC-INTERACTION, ANALYTICS-DATA, SECURITY/PERMISSION |

### PUB-025

**Public Student and staff authentication entry pages**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Authentication entry. Public Student and staff authentication entry pages. No public standalone self-registration or public staff account creation. |
| UI / routes | Student Portal link /student/login; known /admin/login<br>`GET\|HEAD /student/login` — `student.login`<br>`GET\|HEAD /admin/login` — `admin.login`<br>`GET\|HEAD /admin/forgot-password` — `admin.password.request` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Authentication form labels and current session status. |
| Data created / changed | None without submission. |
| Business rules | No public standalone self-registration or public staff account creation. Portal identity verification and staff password/recovery are distinct flows; staff pages are noindex. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Not applicable to entry rendering; expiration/security clocks use UTC/Unix time. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | No public standalone self-registration or public staff account creation. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentAuthenticationTest.php](tests/Feature/StudentAuthenticationTest.php)<br>[tests/Feature/PasswordRecoveryTest.php](tests/Feature/PasswordRecoveryTest.php)<br>[tests/Feature/AdministratorTwoFactorTest.php](tests/Feature/AdministratorTwoFactorTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** No; existing surface/browser state is sufficient, with configuration or security authorization still required where stated. |
| Recommended acceptance scenario | Open entry pages without submitting credentials; verify labels and noindex; later use dedicated QA accounts. |
| Dependent / related capability IDs | [STU-001](#stu-001), [SA-013](#sa-013), [SA-014](#sa-014) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Student/AuthController.php](app/Http/Controllers/Student/AuthController.php)<br>[app/Http/Controllers/Admin/AuthController.php](app/Http/Controllers/Admin/AuthController.php)<br>[app/Http/Controllers/Admin/PasswordResetController.php](app/Http/Controllers/Admin/PasswordResetController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION |

### PUB-026

**Scheduled promotional bars, modal and inline card**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** PUBLIC-FACING. **Domain:** Promotions. Scheduled promotional bars, modal and inline card. Latest eligible promotion per placement uses bounded transition-aware cache, with optional countdown. |
| UI / routes | Configured public promotion placements<br>`GET\|HEAD /` — `home`<br>`GET\|HEAD /fr` — `home.fr`<br>`GET\|HEAD /de` — `home.de` |
| Prerequisites | Available public site and enabled/published definitions where applicable. |
| Data read | Active Promotions with display type, window, image and safe CTA. |
| Data created / changed | Local browser modal dismissal/interaction only. |
| Business rules | Latest eligible promotion per placement uses bounded transition-aware cache, with optional countdown. Supported display types are top_bar/floating_modal/inline_card. Currently no promotions; this is configurable deployed behavior, not missing functionality. |
| Configuration | Promotions is_active, display_type, starts_at/ends_at, countdown and banner. |
| Timezone | Business-zone form input converted to UTC; public eligibility compares current UTC instants. |
| Authorization / privacy | Guest access to published public information; protected tokens/ownership still apply to protected actions. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Currently no promotions; this is configurable deployed behavior, not missing functionality. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/PromotionsTest.php](tests/Feature/PromotionsTest.php)<br>[tests/Feature/PublicExperienceTest.php](tests/Feature/PublicExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later disposable future/live/expired promotions test scheduling, cache transition, safe CTA and dismissal. |
| Dependent / related capability IDs | [SA-069](#sa-069), [PUB-003](#pub-003) |
| Production cross-check | Promotions index observed empty; no promotion published during inventory. |
| Current source evidence | [app/Domains/Marketing/Services/PromotionService.php](app/Domains/Marketing/Services/PromotionService.php)<br>[resources/views/layouts/public.blade.php](resources/views/layouts/public.blade.php)<br>[app/Http/Controllers/HomeController.php](app/Http/Controllers/HomeController.php) |
| QA needs | PUBLIC-INTERACTION, RESOURCE-DATA, CONFIGURATION-TOGGLE |

## STUDENT PORTAL

[STU-001](#stu-001) Passwordless identity verification, session expiry and sign-out · [STU-002](#stu-002) Read recorded profile and verified emails · [STU-003](#stu-003) Add a verified secondary authentication email · [STU-004](#stu-004) Prioritized next action and empty-state dashboard · [STU-005](#stu-005) Owned packages and separate typed credit balances · [STU-006](#stu-006) Allocation-specific low-credit and expiry notices · [STU-007](#stu-007) Owned upcoming sessions and preserved lesson history · [STU-008](#stu-008) Persistent Student timezone preference · [STU-009](#stu-009) Book an eligible package-funded lesson · [STU-010](#stu-010) Self-service owned Student rescheduling and review · [STU-011](#stu-011) Owned completed or ended-confirmed lesson workspace · [STU-012](#stu-012) Open shared private PDF, Resource, recording or safe link · [STU-013](#stu-013) Shared learning plans and tutor-managed milestones · [STU-014](#stu-014) Shared homework, progress/response submission and tutor feedback · [STU-015](#stu-015) Open assigned published Resources and persist review state · [STU-016](#stu-016) Shared pronunciation, vocabulary and grammar correction patterns · [STU-017](#stu-017) Shared normalized teaching tags · [STU-018](#stu-018) Fact-based lesson and learning progress summary · [STU-019](#stu-019) Read shared notes and create/edit Student-authored notes · [STU-020](#stu-020) Assigned published questionnaires, drafts/submission and version history · [STU-021](#stu-021) Form draft autosave with current draft/version identity · [STU-022](#stu-022) Persistent owner Notification Center and read state · [STU-023](#stu-023) Optional rating/comment for own completed lesson · [STU-024](#stu-024) Owned purchase statements and CSV/XLSX · [STU-025](#stu-025) Read/print an actual owned payment receipt · [STU-026](#stu-026) Own holidays/unavailability with cancellation history · [STU-027](#stu-027) Own lesson waitlist interest and withdrawal · [STU-028](#stu-028) Time-gated meeting link reveal

### STU-001

**Passwordless identity verification, session expiry and sign-out**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Authentication. Passwordless identity verification, session expiry and sign-out. DOB plus at least two matching name/email/phone signals must identify exactly one verified unsuspended Student. |
| UI / routes | Student sign in; Student navigation Sign out<br>`GET\|HEAD /student/login` — `student.login`<br>`POST /student/login` — `student.login.submit`<br>`POST /student/logout` — `student.logout` |
| Prerequisites | An existing verified unsuspended Student with recorded DOB and at least two matching identity attributes; no public signup. |
| Data read | Verified Student normalized name, DOB, primary/verified secondary email, phone and auth-attempt state. |
| Data created / changed | HMAC auth-attempt/cooldown rows and regenerated three-hour student guard/session; optional visitor-to-Student association. |
| Business rules | DOB plus at least two matching name/email/phone signals must identify exactly one verified unsuspended Student. National phone requires normalization context; ambiguity fails generically. Every authenticated request rechecks guard/identity/expiry; sign-out removes Student keys while staff guard may coexist. |
| Configuration | Student verification throttles/HMAC secret fallback and database session backend. |
| Timezone | Session proof expires 180 minutes after sign-in in UTC; DOB is a date, not converted as an appointment. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Follow the publication/eligibility and current-state rules above; validate the rejected or unavailable case in the acceptance scenario. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentAuthenticationTest.php](tests/Feature/StudentAuthenticationTest.php)<br>[tests/Feature/StudentSecondaryEmailTest.php](tests/Feature/StudentSecondaryEmailTest.php)<br>[tests/Feature/SessionInactivityAndCookieRefreshTest.php](tests/Feature/SessionInactivityAndCookieRefreshTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Use two approved QA identities; check correct login, ambiguous/invalid/suspended/merged/expired login and Student-only logout. |
| Dependent / related capability IDs | [PUB-025](#pub-025), [SA-020](#sa-020), [SA-018](#sa-018) |
| Production cross-check | Current sign-in page verified; current Student count is zero. Same-release authenticated empty-state smoke was verified before inventory with the separately authorized account that was removed. |
| Current source evidence | [app/Http/Controllers/Student/AuthController.php](app/Http/Controllers/Student/AuthController.php)<br>[app/Http/Middleware/EnsureStudentAuthenticated.php](app/Http/Middleware/EnsureStudentAuthenticated.php) |
| QA needs | STUDENT-DATA, SECURITY/PERMISSION |

### STU-002

**Read recorded profile and verified emails**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Identity. Read recorded profile and verified emails. Profile exposes recorded identity and verified addresses; no general Student self-edit route for name/DOB/phone is available. |
| UI / routes | Sessions → Your profile and verified emails<br>`GET\|HEAD /student/profile` — `student.profile` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Current Student profile and student_emails. |
| Data created / changed | None. |
| Business rules | Profile exposes recorded identity and verified addresses; no general Student self-edit route for name/DOB/phone is available. Changes to primary identity remain staff-managed. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Preferred IANA zone displayed; DOB remains a plain date. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Profile exposes recorded identity and verified addresses; no general Student self-edit route for name/DOB/phone is available. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentSecondaryEmailTest.php](tests/Feature/StudentSecondaryEmailTest.php)<br>[tests/Feature/StudentAuthenticationTest.php](tests/Feature/StudentAuthenticationTest.php) |
| Read-only / synthetic QA | **Read-only:** Yes, current output can be inspected without domain writes.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Inspect only own profile; prove another identity cannot be selected by query/route and no staff fields are exposed. |
| Dependent / related capability IDs | [STU-001](#stu-001), [SA-020](#sa-020) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Http/Controllers/Student/ProfileController.php](app/Http/Controllers/Student/ProfileController.php)<br>[resources/views/student/profile.blade.php](resources/views/student/profile.blade.php) |
| QA needs | STUDENT-DATA, SECURITY/PERMISSION |

### STU-003

**Add a verified secondary authentication email**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Identity/email. Add a verified secondary authentication email. Six requests/hour and route throttles; 15-minute hashed token belongs to the authenticated Student and is single-use. |
| UI / routes | Profile → secondary-email request and verification link<br>`POST /student/profile/emails` — `student.profile.email.request`<br>`GET\|HEAD /student/profile/emails/verify/{token}` — `student.profile.email.verify` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Primary/secondary ownership, email quality and hashed pending challenge. |
| Data created / changed | student_email_verifications, synchronous configured Mail send, trusted student_emails and safe audit on successful verification. |
| Business rules | Six requests/hour and route throttles; 15-minute hashed token belongs to the authenticated Student and is single-use. Primary/secondary/soft-deleted ownership conflicts reject; requesting an address does not make it trusted. Current production mailer is Log, so real external inbox delivery is not certified. |
| Configuration | services.student_auth HMAC, configured mailer and email-quality checks; current Log mail preserved. |
| Timezone | UTC token expiry and verification timestamp; no DOB/Business Timezone conversion. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Primary/secondary/soft-deleted ownership conflicts reject; requesting an address does not make it trusted. Current production mailer is Log, so real external inbox delivery is not certified. |
| Background / manual behavior | **Background:** Verification Mail send is synchronous in this service; no new worker required.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentSecondaryEmailTest.php](tests/Feature/StudentSecondaryEmailTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later safe QA mail capture tests request, trusted login after verify, foreign/expired/replayed token and conflicting email; no owner mail configuration change. |
| Dependent / related capability IDs | [STU-002](#stu-002), [STU-001](#stu-001), [PUB-015](#pub-015) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Domains/Students/Services/StudentEmailService.php](app/Domains/Students/Services/StudentEmailService.php)<br>[app/Http/Controllers/Student/ProfileController.php](app/Http/Controllers/Student/ProfileController.php) |
| QA needs | STUDENT-DATA, SECURITY/PERMISSION |

### STU-004

**Prioritized next action and empty-state dashboard**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Dashboard. Prioritized next action and empty-state dashboard. Priority is current mandatory-first outstanding questionnaire → open homework → unreviewed Resource → compatible booking if no upcoming lesson → learning progress. |
| UI / routes | Sessions → Your next step<br>`GET\|HEAD /student` — `student.dashboard` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Current published assigned questionnaires, submissions, shared homework/resources, confirmed future lessons and compatible allocations. |
| Data created / changed | Current eligible notification synchronization only; next-action selection itself creates no lesson/payment. |
| Business rules | Priority is current mandatory-first outstanding questionnaire → open homework → unreviewed Resource → compatible booking if no upcoming lesson → learning progress. Typed allocations are not pooled to manufacture booking eligibility. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants are UTC; lesson display uses student/customer timezone; calendar dates use their explicitly stated business/student zone. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Priority is current mandatory-first outstanding questionnaire → open homework → unreviewed Resource → compatible booking if no upcoming lesson → learning progress. Typed allocations are not pooled to manufacture booking eligibility. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later QA varies one prerequisite at a time to check exact priority; empty profile falls back to learning progress. |
| Dependent / related capability IDs | [STU-005](#stu-005), [STU-009](#stu-009), [STU-014](#stu-014), [STU-015](#stu-015), [STU-020](#stu-020), [STU-022](#stu-022) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Domains/Students/Services/StudentTeachingReadModel.php](app/Domains/Students/Services/StudentTeachingReadModel.php)<br>[app/Http/Controllers/Student/DashboardController.php](app/Http/Controllers/Student/DashboardController.php) |
| QA needs | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION |

### STU-005

**Owned packages and separate typed credit balances**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Entitlements. Owned packages and separate typed credit balances. one_hour and two_hour rights are separate; SessionType exact type/units, compatible single allocation and business-date eligibility govern spend. |
| UI / routes | Sessions → Sessions and credits / available entitlements<br>`GET\|HEAD /student` — `student.dashboard` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Owned purchases, typed allocations, append-only grant/use/restore/adjust/forfeit ledger and expiry. |
| Data created / changed | None except visit-driven notification synchronization. |
| Business rules | one_hour and two_hour rights are separate; SessionType exact type/units, compatible single allocation and business-date eligibility govern spend. Expired/pending-settlement/inactive/legacy_unclassified units are not available; historical units remain visible as review-required. |
| Configuration | SessionType funding/required type+units; preset validity_days; active entitlement definitions. |
| Timezone | Expiry includes end of Business Timezone expiration date; no duration-based credit conversion. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Expired/pending-settlement/inactive/legacy_unclassified units are not available; historical units remain visible as review-required. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/TypedEntitlementsTest.php](tests/Feature/TypedEntitlementsTest.php)<br>[tests/Feature/StudentCreditBookingTest.php](tests/Feature/StudentCreditBookingTest.php)<br>[tests/Feature/StudentLedgerTest.php](tests/Feature/StudentLedgerTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later mixed-rights, pending, expired/unlimited and legacy QA purchases prove separate available/history balances without pooling. |
| Dependent / related capability IDs | [STU-001](#stu-001), [SA-027](#sa-027), [SA-031](#sa-031), [STU-009](#stu-009) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Domains/Students/Services/EntitlementService.php](app/Domains/Students/Services/EntitlementService.php)<br>[app/Domains/Students/Services/StudentPortalService.php](app/Domains/Students/Services/StudentPortalService.php)<br>[app/Http/Controllers/Student/DashboardController.php](app/Http/Controllers/Student/DashboardController.php) |
| QA needs | STUDENT-DATA, ENTITLEMENT-DATA, SECURITY/PERMISSION |

### STU-006

**Allocation-specific low-credit and expiry notices**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Entitlements/notices. Allocation-specific low-credit and expiry notices. Low means eligible allocation available units ≤2. |
| UI / routes | Sessions credits notice; Notification Center<br>`GET\|HEAD /student` — `student.dashboard`<br>`GET\|HEAD /student/notifications` — `student.notifications.index` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Eligible allocation available units, active purchase remaining units and expiration. |
| Data created / changed | Deduplicated persistent entitlement notification when current state is synchronized. |
| Business rules | Low means eligible allocation available units ≤2. Active purchases with remaining units and expiry within 14 business-calendar days include already expired dates. Copy includes type, package, purchase ID, available quantity and expiry; legacy units are not bookable. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants are UTC; lesson display uses student/customer timezone; calendar dates use their explicitly stated business/student zone. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Active purchases with remaining units and expiry within 14 business-calendar days include already expired dates. Copy includes type, package, purchase ID, available quantity and expiry; legacy units are not bookable. |
| Background / manual behavior | **Background:** Synchronized on authenticated portal reads, not instant scheduled push.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later type-separated 0/1/2/3-unit and 14-day/expired fixtures prove notice boundaries and dedupe. |
| Dependent / related capability IDs | [STU-005](#stu-005), [STU-022](#stu-022) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Domains/Students/Services/StudentTeachingReadModel.php](app/Domains/Students/Services/StudentTeachingReadModel.php)<br>[app/Http/Controllers/Student/DashboardController.php](app/Http/Controllers/Student/DashboardController.php)<br>[app/Http/Controllers/Student/NotificationController.php](app/Http/Controllers/Student/NotificationController.php) |
| QA needs | STUDENT-DATA, ENTITLEMENT-DATA |

### STU-007

**Owned upcoming sessions and preserved lesson history**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Lessons. Owned upcoming sessions and preserved lesson history. Confirmed upcoming lessons and completed/cancelled/no-show/history remain distinct; reschedule-needed status does not invent completion. |
| UI / routes | Sessions → Upcoming sessions / Session history<br>`GET\|HEAD /student` — `student.dashboard` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Owned Bookings/SessionTypes, status/time snapshots and completed count. |
| Data created / changed | None except current notification synchronization. |
| Business rules | Confirmed upcoming lessons and completed/cancelled/no-show/history remain distinct; reschedule-needed status does not invent completion. Lesson workspace visibility has its own end/status boundary. Archive state does not erase booked commitments/history. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants are UTC; lesson display uses student/customer timezone; calendar dates use their explicitly stated business/student zone. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | Displayed owned upcoming/history partitions; no universal portal booking export or admin calendar. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Confirmed upcoming lessons and completed/cancelled/no-show/history remain distinct; reschedule-needed status does not invent completion. Archive state does not erase booked commitments/history. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentAuthenticationTest.php](tests/Feature/StudentAuthenticationTest.php)<br>[tests/Feature/StudentReschedulingTest.php](tests/Feature/StudentReschedulingTest.php)<br>[tests/Feature/LessonWorkspaceTest.php](tests/Feature/LessonWorkspaceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later QA supplies confirmed future, ended-confirmed, completed, cancelled and no-show lessons; inspect correct status/time and available actions. |
| Dependent / related capability IDs | [STU-001](#stu-001), [STU-010](#stu-010), [STU-011](#stu-011), [STU-028](#stu-028) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Domains/Students/Services/StudentPortalService.php](app/Domains/Students/Services/StudentPortalService.php)<br>[resources/views/student/dashboard.blade.php](resources/views/student/dashboard.blade.php)<br>[app/Http/Controllers/Student/DashboardController.php](app/Http/Controllers/Student/DashboardController.php) |
| QA needs | STUDENT-DATA, BOOKING-DATA, SECURITY/PERMISSION |

### STU-008

**Persistent Student timezone preference**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Timezones. Persistent Student timezone preference. Uses shared searchable flag/offset UI. |
| UI / routes | Sessions/booking/reschedule → Change Timezone<br>`GET\|HEAD /student` — `student.dashboard`<br>`GET\|HEAD /student/bookings/create` — `student.bookings.create`<br>`GET\|HEAD /student/bookings/{booking}/reschedule` — `student.bookings.reschedule` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Valid requested IANA zone, preferred_timezone and customer/business fallback. |
| Data created / changed | student_display_timezone in session and Student.preferred_timezone when an authenticated request supplies a different valid zone. |
| Business rules | Uses shared searchable flag/offset UI. Valid query/input zone can persist preference; invalid input falls back/rejects at its boundary. Locale choice does not change booking timestamps. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Customer display zone, canonical UTC times and date-specific DST offsets. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Valid query/input zone can persist preference; invalid input falls back/rejects at its boundary. Locale choice does not change booking timestamps. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/TimezoneTest.php](tests/Feature/TimezoneTest.php)<br>[tests/Feature/ReschedulePresentationTest.php](tests/Feature/ReschedulePresentationTest.php)<br>[tests/Feature/StudentReschedulingTest.php](tests/Feature/StudentReschedulingTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later own Student changes Cairo/New York/UTC, reloads and signs in again; verify preferred zone and unchanged canonical booking instants. |
| Dependent / related capability IDs | [PUB-007](#pub-007), [STU-007](#stu-007) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Http/Middleware/EnsureStudentAuthenticated.php](app/Http/Middleware/EnsureStudentAuthenticated.php)<br>[resources/views/components/timezone-selector.blade.php](resources/views/components/timezone-selector.blade.php)<br>[app/Http/Controllers/Student/DashboardController.php](app/Http/Controllers/Student/DashboardController.php)<br>[app/Http/Controllers/Student/BookingController.php](app/Http/Controllers/Student/BookingController.php)<br>[app/Http/Controllers/Student/RescheduleController.php](app/Http/Controllers/Student/RescheduleController.php) |
| QA needs | STUDENT-DATA, PUBLIC-INTERACTION |

### STU-009

**Book an eligible package-funded lesson**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Booking. Book an eligible package-funded lesson. One allocation must cover exact required units; same-type balances across purchases cannot be pooled for one booking. |
| UI / routes | Sessions → Book your next session / /student/bookings/create<br>`GET\|HEAD /student/bookings/create` — `student.bookings.create`<br>`POST /student/bookings` — `student.bookings.store` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Active package SessionTypes, one compatible allocation, current availability/signed slot and own Student. |
| Data created / changed | Confirmed Booking, exact typed debit/provenance snapshot, policy snapshot, event/meeting linkage and package-booking analytics. |
| Business rules | One allocation must cover exact required units; same-type balances across purchases cannot be pooled for one booking. No one_hour/two_hour conversion, negative balance or double debit; transaction rechecks notice/holiday/conflict/student/type/expiry and idempotency. |
| Configuration | Active funding_mode=package SessionType, active typed rights and availability. |
| Timezone | Canonical instants are UTC; lesson display uses student/customer timezone; calendar dates use their explicitly stated business/student zone. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | One allocation must cover exact required units; same-type balances across purchases cannot be pooled for one booking. No one_hour/two_hour conversion, negative balance or double debit; transaction rechecks notice/holiday/conflict/student/type/expiry and idempotency. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentCreditBookingTest.php](tests/Feature/StudentCreditBookingTest.php)<br>[tests/Feature/TypedEntitlementsTest.php](tests/Feature/TypedEntitlementsTest.php)<br>[tests/Feature/MariaDbConcurrencyVerificationTest.php](tests/Feature/MariaDbConcurrencyVerificationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later mixed-rights QA books exactly once; replay/last-credit race and incompatible/expired/foreign slot fail without extra debit. |
| Dependent / related capability IDs | [STU-005](#stu-005), [STU-008](#stu-008), [SA-044](#sa-044), [SA-045](#sa-045), [STU-026](#stu-026) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Http/Controllers/Student/BookingController.php](app/Http/Controllers/Student/BookingController.php)<br>[app/Domains/Students/Services/StudentBookingService.php](app/Domains/Students/Services/StudentBookingService.php) |
| QA needs | STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA, SECURITY/PERMISSION |

### STU-010

**Self-service owned Student rescheduling and review**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Rescheduling. Self-service owned Student rescheduling and review. Portal GET permits confirmed lessons at least 24 hours ahead; nearer/other-status lessons direct to tutor. |
| UI / routes | Eligible upcoming lesson → Reschedule<br>`GET\|HEAD /student/bookings/{booking}/reschedule` — `student.bookings.reschedule`<br>`POST /student/bookings/{booking}/reschedule` — `student.bookings.reschedule.submit` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Owned confirmed lesson, current authoritative slots, original funding snapshot and request identity. |
| Data created / changed | New canonical times/customer snapshot, SessionReschedule/BookingEvent, reconfirmation state and safe meeting reassignment; no new typed debit. |
| Business rules | Portal GET permits confirmed lessons at least 24 hours ahead; nearer/other-status lessons direct to tutor. Signed visitor-bound slots, holiday/availability/notice/conflict locks and idempotency recheck writes. Original allocation/type/quantity remain unchanged. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants are UTC; lesson display uses student/customer timezone; calendar dates use their explicitly stated business/student zone. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | Timezone, selected customer date/month and available slots. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Follow the publication/eligibility and current-state rules above; validate the rejected or unavailable case in the acceptance scenario. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentReschedulingTest.php](tests/Feature/StudentReschedulingTest.php)<br>[tests/Feature/ReschedulePresentationTest.php](tests/Feature/ReschedulePresentationTest.php)<br>[tests/Feature/TypedEntitlementsTest.php](tests/Feature/TypedEntitlementsTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later owned >24-hour QA lesson shows old/new time review, date-clearing selection and one move; foreign/past/near-start/changed-type/expired slot refuse. |
| Dependent / related capability IDs | [STU-007](#stu-007), [STU-008](#stu-008), [PUB-008](#pub-008), [STU-026](#stu-026), [SA-041](#sa-041) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Http/Controllers/Student/RescheduleController.php](app/Http/Controllers/Student/RescheduleController.php)<br>[app/Domains/Booking/Services/RescheduleService.php](app/Domains/Booking/Services/RescheduleService.php) |
| QA needs | STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA, SECURITY/PERMISSION |

### STU-011

**Owned completed or ended-confirmed lesson workspace**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Lesson workspace. Owned completed or ended-confirmed lesson workspace. A completed lesson or confirmed lesson whose end is ≤now is eligible. |
| UI / routes | Session history → Lesson workspace<br>`GET\|HEAD /student/lessons/{booking}` — `student.lessons.show` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Owned nondeleted Booking, lesson summary and nonwithdrawn student-visible materials. |
| Data created / changed | None. |
| Business rules | A completed lesson or confirmed lesson whose end is ≤now is eligible. Upcoming lesson workspace, foreign booking and deleted/withdrawn content deny. Internal tutor preparation is structurally excluded. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants are UTC; lesson display uses student/customer timezone; calendar dates use their explicitly stated business/student zone. |
| Authorization / privacy | LessonWorkspacePolicy ownership/status/end boundary; response private/no-store. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Upcoming lesson workspace, foreign booking and deleted/withdrawn content deny. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/LessonWorkspaceTest.php](tests/Feature/LessonWorkspaceTest.php) |
| Read-only / synthetic QA | **Read-only:** Yes, current output can be inspected without domain writes.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later inspect owned completed and ended-confirmed workspace, then upcoming/foreign/deleted denial without private material disclosure. |
| Dependent / related capability IDs | [STU-007](#stu-007), [STU-012](#stu-012), [SA-052](#sa-052) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Policies/LessonWorkspacePolicy.php](app/Policies/LessonWorkspacePolicy.php)<br>[app/Http/Controllers/Student/LessonWorkspaceController.php](app/Http/Controllers/Student/LessonWorkspaceController.php) |
| QA needs | STUDENT-DATA, BOOKING-DATA, TEACHING-DATA, SECURITY/PERMISSION |

### STU-012

**Open shared private PDF, Resource, recording or safe link**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Private learning files. Open shared private PDF, Resource, recording or safe link. Parent lesson and material ownership, nonwithdrawn/student_visible and Resource publication are rechecked. |
| UI / routes | Lesson workspace material action<br>`GET\|HEAD /student/lessons/{booking}/materials/{material}` — `student.lessons.materials.open` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Owned workspace, LessonMaterial visibility/kind, published referenced Resource and approved private bytes. |
| Data created / changed | None; download/read does not create a public Resource gate request/history. |
| Business rules | Parent lesson and material ownership, nonwithdrawn/student_visible and Resource publication are rechecked. Strict private root/realpath/symlink/path checks protect PDFs; external links must satisfy SafeLessonUrl; no-store/private/no-referrer responses. Private PDF maximum is 10 MiB; complete %PDF-/EOF bytes and PassiveLessonPdf reject scripts, automatic actions, launches and embedded files. Material kinds private_file/resource/external_link/recording have database payload-shape checks; title ≤200, description ≤5000, order 0–10000; sharing defaults off. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants are UTC; lesson display uses student/customer timezone; calendar dates use their explicitly stated business/student zone. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | Authorized private payload/download or safe HTTPS redirect; no generic CSV/XLSX. |
| Edges / negative cases | Private PDF maximum is 10 MiB; complete %PDF-/EOF bytes and PassiveLessonPdf reject scripts, automatic actions, launches and embedded files. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/LessonWorkspaceTest.php](tests/Feature/LessonWorkspaceTest.php)<br>[tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later capture owned payload hash/headers and safe link; reject foreign, withdrawn, unpublished, traversal/symlink/missing file cases. |
| Dependent / related capability IDs | [STU-011](#stu-011), [SA-052](#sa-052), [SA-061](#sa-061) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Domains/Booking/Services/LessonMaterialService.php](app/Domains/Booking/Services/LessonMaterialService.php)<br>[app/Policies/LessonMaterialPolicy.php](app/Policies/LessonMaterialPolicy.php)<br>[app/Rules/PassiveLessonPdf.php](app/Rules/PassiveLessonPdf.php)<br>[app/Http/Controllers/Student/LessonWorkspaceController.php](app/Http/Controllers/Student/LessonWorkspaceController.php) |
| QA needs | STUDENT-DATA, BOOKING-DATA, TEACHING-DATA, RESOURCE-DATA, SECURITY/PERMISSION |

### STU-013

**Shared learning plans and tutor-managed milestones**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Learning plan. Shared learning plans and tutor-managed milestones. Students read tutor-shared plan/milestone state; they cannot create plans or self-mark milestones complete. |
| UI / routes | My learning → Plans/milestones<br>`GET\|HEAD /student/learning` — `student.teaching.index` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Student-visible LearningPlans and owned milestones, status/goals/focus/start/completion facts. |
| Data created / changed | None. |
| Business rules | Students read tutor-shared plan/milestone state; they cannot create plans or self-mark milestones complete. Hidden plans/preparation and foreign milestones are excluded. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants are UTC; lesson display uses student/customer timezone; calendar dates use their explicitly stated business/student zone. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Students read tutor-shared plan/milestone state; they cannot create plans or self-mark milestones complete. Hidden plans/preparation and foreign milestones are excluded. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Yes, current output can be inspected without domain writes.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later active/paused/completed/shared/hidden plans verify ordered milestones and staff-controlled completion dates. |
| Dependent / related capability IDs | [SA-054](#sa-054), [STU-018](#stu-018) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Domains/Students/Services/StudentTeachingReadModel.php](app/Domains/Students/Services/StudentTeachingReadModel.php)<br>[resources/views/student/teaching.blade.php](resources/views/student/teaching.blade.php)<br>[app/Http/Controllers/Student/TeachingController.php](app/Http/Controllers/Student/TeachingController.php) |
| QA needs | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION |

### STU-014

**Shared homework, progress/response submission and tutor feedback**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Homework. Shared homework, progress/response submission and tutor feedback. Only in_progress or submitted student writes; completed homework returns 409 and only tutor determines completion. |
| UI / routes | My learning → Homework<br>`GET\|HEAD /student/learning` — `student.teaching.index`<br>`PATCH /student/homework/{homework}` — `student.homework.update`<br>`GET\|HEAD /student/homework/{homework}/resource` — `student.homework.resource`<br>`GET\|HEAD /student/homework/{homework}/link` — `student.homework.link` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Shared owned homework, optional owned lesson/material/published Resource/safe link and tutor feedback. |
| Data created / changed | Own student_response and in_progress/submitted status under Student lock. |
| Business rules | Only in_progress or submitted student writes; completed homework returns 409 and only tutor determines completion. Shared reference ownership/publication and safe-link checks repeat at open. No Student file-upload/submission attachment feature is exposed. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants are UTC; lesson display uses student/customer timezone; calendar dates use their explicitly stated business/student zone. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | No Student file-upload/submission attachment feature is exposed. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later progress → text response → submit → tutor complete/feedback; completed rewrite/foreign item/material/link deny. |
| Dependent / related capability IDs | [SA-053](#sa-053), [STU-012](#stu-012), [STU-018](#stu-018) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Http/Controllers/Student/HomeworkController.php](app/Http/Controllers/Student/HomeworkController.php)<br>[app/Http/Controllers/Student/TeachingController.php](app/Http/Controllers/Student/TeachingController.php) |
| QA needs | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION |

### STU-015

**Open assigned published Resources and persist review state**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Resource assignment. Open assigned published Resources and persist review state. References the existing Resource/file; creates no duplicate file or public lead/download metric. |
| UI / routes | My learning → Assigned resources<br>`GET\|HEAD /student/learning` — `student.teaching.index`<br>`GET\|HEAD /student/assigned-resources/{assignment}` — `student.resources.open`<br>`PATCH /student/assigned-resources/{assignment}` — `student.resources.update` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Shared ResourceAssignment with optional owned lesson and published Resource metadata/bytes. |
| Data created / changed | reviewed_at set once for own eligible assignment. |
| Business rules | References the existing Resource/file; creates no duplicate file or public lead/download metric. Changed staff Resource assignment resets review. Hidden/withdrawn/unpublished/foreign assignment denies; private delivery reuses LessonMaterialService. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants are UTC; lesson display uses student/customer timezone; calendar dates use their explicitly stated business/student zone. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | Authorized referenced payload or safe link only. |
| Edges / negative cases | References the existing Resource/file; creates no duplicate file or public lead/download metric. Hidden/withdrawn/unpublished/foreign assignment denies; private delivery reuses LessonMaterialService. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later open and mark reviewed twice, reload, then change assigned Resource from staff and confirm review reset/hidden denial. |
| Dependent / related capability IDs | [SA-056](#sa-056), [SA-061](#sa-061), [STU-012](#stu-012) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Http/Controllers/Student/ResourceAssignmentController.php](app/Http/Controllers/Student/ResourceAssignmentController.php)<br>[app/Http/Controllers/Student/TeachingController.php](app/Http/Controllers/Student/TeachingController.php) |
| QA needs | STUDENT-DATA, TEACHING-DATA, RESOURCE-DATA, SECURITY/PERMISSION |

### STU-016

**Shared pronunciation, vocabulary and grammar correction patterns**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Learning patterns. Shared pronunciation, vocabulary and grammar correction patterns. Read tutor-visible practising/improved/resolved facts; no automated speech score or model-generated proficiency prediction. |
| UI / routes | My learning → Patterns/error log<br>`GET\|HEAD /student/learning` — `student.teaching.index` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Shared StudentErrorLogs, category/mistake/correction/notes/status and optional own lesson context. |
| Data created / changed | None. |
| Business rules | Read tutor-visible practising/improved/resolved facts; no automated speech score or model-generated proficiency prediction. Hidden/private/foreign correction data is excluded. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants are UTC; lesson display uses student/customer timezone; calendar dates use their explicitly stated business/student zone. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Read tutor-visible practising/improved/resolved facts; no automated speech score or model-generated proficiency prediction. Hidden/private/foreign correction data is excluded. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Yes, current output can be inspected without domain writes.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later shared/hidden pronunciation/vocabulary/grammar records and improved/resolved statuses verify visibility and progress count. |
| Dependent / related capability IDs | [SA-057](#sa-057), [STU-018](#stu-018) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Domains/Students/Services/StudentTeachingReadModel.php](app/Domains/Students/Services/StudentTeachingReadModel.php)<br>[app/Http/Controllers/Student/TeachingController.php](app/Http/Controllers/Student/TeachingController.php) |
| QA needs | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION |

### STU-017

**Shared normalized teaching tags**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Teaching context. Shared normalized teaching tags. Shared normalized label tags are teaching context, not public taxonomy. |
| UI / routes | My learning → Teaching tags<br>`GET\|HEAD /student/learning` — `student.teaching.index` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Student/owned-lesson student_visible TeachingTags. |
| Data created / changed | None. |
| Business rules | Shared normalized label tags are teaching context, not public taxonomy. Students do not author/update tags or see private lesson/student tags. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants are UTC; lesson display uses student/customer timezone; calendar dates use their explicitly stated business/student zone. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Shared normalized label tags are teaching context, not public taxonomy. Students do not author/update tags or see private lesson/student tags. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Yes, current output can be inspected without domain writes.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later student-wide/owned-lesson tags verify shared-only display and hidden/foreign exclusion. |
| Dependent / related capability IDs | [SA-058](#sa-058) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Domains/Students/Services/StudentTeachingReadModel.php](app/Domains/Students/Services/StudentTeachingReadModel.php)<br>[app/Http/Controllers/Student/TeachingController.php](app/Http/Controllers/Student/TeachingController.php) |
| QA needs | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION |

### STU-018

**Fact-based lesson and learning progress summary**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Progress. Fact-based lesson and learning progress summary. Counts reflect stored records and sharing: completed lessons, completed/total milestones and homework, shared notes, improved/resolved patterns. |
| UI / routes | Sessions → Your learning progress; My learning<br>`GET\|HEAD /student` — `student.dashboard`<br>`GET\|HEAD /student/learning` — `student.teaching.index` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Owned completed lessons, shared plans/milestones/homework/notes and improved/resolved patterns. |
| Data created / changed | None except visit-driven current notifications. |
| Business rules | Counts reflect stored records and sharing: completed lessons, completed/total milestones and homework, shared notes, improved/resolved patterns. No inferred performance/retention score or LMS completion curriculum is claimed. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants are UTC; lesson display uses student/customer timezone; calendar dates use their explicitly stated business/student zone. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | No inferred performance/retention score or LMS completion curriculum is claimed. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later modify one shared fact through staff-approved QA to verify exact count/dominator and exclusion of private preparation. |
| Dependent / related capability IDs | [STU-007](#stu-007), [STU-013](#stu-013), [STU-014](#stu-014), [STU-016](#stu-016), [STU-019](#stu-019) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Domains/Students/Services/StudentTeachingReadModel.php](app/Domains/Students/Services/StudentTeachingReadModel.php)<br>[app/Http/Controllers/Student/DashboardController.php](app/Http/Controllers/Student/DashboardController.php)<br>[app/Http/Controllers/Student/TeachingController.php](app/Http/Controllers/Student/TeachingController.php) |
| QA needs | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION |

### STU-019

**Read shared notes and create/edit Student-authored notes**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Educational notes. Read shared notes and create/edit Student-authored notes. Students can read shared staff notes but cannot edit them; their authored visible notes can be edited. |
| UI / routes | Educational Notes navigation<br>`GET\|HEAD /student/notes` — `student.bins.index`<br>`POST /student/notes` — `student.bins.store`<br>`GET\|HEAD /student/notes/{bin}/edit` — `student.bins.edit`<br>`PUT /student/notes/{bin}` — `student.bins.update` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Owned student_visible StudentBins, title/body/creator and timestamps. |
| Data created / changed | New automatically student-visible student-authored note; edits to own Student-authored note; audit metadata. |
| Business rules | Students can read shared staff notes but cannot edit them; their authored visible notes can be edited. No Student delete route; only Super Admin deletion authority. Text is escaped, and q search escapes wildcard characters. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants are UTC; lesson display uses student/customer timezone; calendar dates use their explicitly stated business/student zone. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | Title/body q search; updated order and 20-item pagination. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Students can read shared staff notes but cannot edit them; their authored visible notes can be edited. No Student delete route; only Super Admin deletion authority. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BinAuthorizationTest.php](tests/Feature/BinAuthorizationTest.php)<br>[tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later Student creates/edits own note, searches shared note and receives staff-note edit/foreign-note denial; no delete button. |
| Dependent / related capability IDs | [SA-060](#sa-060), [STU-001](#stu-001) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Http/Controllers/StudentBinController.php](app/Http/Controllers/StudentBinController.php)<br>[app/Policies/StudentBinPolicy.php](app/Policies/StudentBinPolicy.php) |
| QA needs | STUDENT-DATA, SECURITY/PERMISSION |

### STU-020

**Assigned published questionnaires, drafts/submission and version history**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Questionnaires. Assigned published questionnaires, drafts/submission and version history. Assignment/current-published/owner checks apply; conditional required/type validation and can_edit_after_submission lock control writes. |
| UI / routes | Sessions → Questionnaires / /student/forms/{slug}<br>`GET\|HEAD /student/forms/{slug}` — `student.forms.show`<br>`POST /student/forms/{slug}` — `student.forms.save` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Assigned published Form/version/questions/options, own current/prior submissions and answers. |
| Data created / changed | Draft/submitted FormSubmission/answers/revisions; authoritative form completion analytics on submit. |
| Business rules | Assignment/current-published/owner checks apply; conditional required/type validation and can_edit_after_submission lock control writes. Historical answers stay on their original version; explicit new_version starts current form rather than rewriting old history. Current assignments are derived, not a fabricated manual-assignment table: a published form without pre_booking/after_booking/after_reschedule/next_session_check triggers is generally assigned; after_booking requires owned nondeleted booking history, after_reschedule an owned reschedule, next_session_check an upcoming confirmed lesson. Own prior submission keeps the form available; pre_booking alone does not generally assign it in the portal. |
| Configuration | Form published_version_id, triggers, conditional logic, validation and can_edit_after_submission. |
| Timezone | Canonical instants are UTC; lesson display uses student/customer timezone; calendar dates use their explicitly stated business/student zone. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Current assignments are derived, not a fabricated manual-assignment table: a published form without pre_booking/after_booking/after_reschedule/next_session_check triggers is generally assigned; after_booking requires owned nondeleted booking history, after_reschedule an owned reschedule, next_session_check an upcoming confirmed lesson. Own prior submission keeps the form available; pre_booking alone does not generally assign it in the portal. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/FormsEngineTest.php](tests/Feature/FormsEngineTest.php)<br>[tests/Feature/FilterAwareExportsTest.php](tests/Feature/FilterAwareExportsTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later assigned/unassigned QA checks draft/submit/locked answer, conditional required fields and changed version/history; no foreign answers. |
| Dependent / related capability IDs | [SA-070](#sa-070), [SA-071](#sa-071), [STU-004](#stu-004), [STU-021](#stu-021) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Http/Controllers/Student/FormController.php](app/Http/Controllers/Student/FormController.php)<br>[app/Domains/Forms/Services/FormSubmissionService.php](app/Domains/Forms/Services/FormSubmissionService.php) |
| QA needs | STUDENT-DATA, SECURITY/PERMISSION |

### STU-021

**Form draft autosave with current draft/version identity**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Questionnaire productivity. Form draft autosave with current draft/version identity. Autosave is an on-demand browser request, not background completion or file upload. |
| UI / routes | Questionnaire autosave UI<br>`POST /student/forms/{slug}/autosave` — `student.forms.autosave` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Current assigned Form/version, own draft and current answers. |
| Data created / changed | Validated draft/submission answers; returns draft_id/form_version_id. |
| Business rules | Autosave is an on-demand browser request, not background completion or file upload. Same submission service preserves owner/version/editing rules; up to 300 answer keys and student-form-save throttle apply. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants are UTC; lesson display uses student/customer timezone; calendar dates use their explicitly stated business/student zone. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Autosave is an on-demand browser request, not background completion or file upload. |
| Background / manual behavior | **Background:** Browser-triggered autosave requests; no queue/scheduler.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/FormsEngineTest.php](tests/Feature/FormsEngineTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later edit QA form, inspect saved draft after reload, reject stale/foreign submission/version and locked submitted form. |
| Dependent / related capability IDs | [STU-020](#stu-020) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Http/Controllers/Student/FormController.php](app/Http/Controllers/Student/FormController.php)<br>[resources/views/student/forms/show.blade.php](resources/views/student/forms/show.blade.php) |
| QA needs | STUDENT-DATA, SECURITY/PERMISSION |

### STU-022

**Persistent owner Notification Center and read state**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** In-app communication. Persistent owner Notification Center and read state. Synchronizes currently eligible events on authenticated visits; does not reconstruct every unseen intermediate change or require a worker. |
| UI / routes | Notifications navigation; dashboard unread count<br>`GET\|HEAD /student/notifications` — `student.notifications.index`<br>`PATCH /student/notifications/{notification}` — `student.notifications.update` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Own notifications plus current shared homework/Resources/forms/materials, lesson states and typed notices. |
| Data created / changed | Deduplicated StudentNotification rows on eligible portal visits; owner read_at update. |
| Business rules | Synchronizes currently eligible events on authenticated visits; does not reconstruct every unseen intermediate change or require a worker. Unique hashed keys prevent repeats. Generic teaching copy/authorized links avoid duplicating sensitive assignment content; foreign mark-read returns 404. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants are UTC; lesson display uses student/customer timezone; calendar dates use their explicitly stated business/student zone. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | 20-item paginated owner notification list; read state persists. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Synchronizes currently eligible events on authenticated visits; does not reconstruct every unseen intermediate change or require a worker. Generic teaching copy/authorized links avoid duplicating sensitive assignment content; foreign mark-read returns 404. |
| Background / manual behavior | **Background:** No background notification synchronization; authenticated portal reads trigger it.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later fixtures create all eligible types; visit twice then mark read/reload/sign-in; prove dedupe, count and foreign denial. |
| Dependent / related capability IDs | [STU-004](#stu-004), [STU-006](#stu-006), [STU-014](#stu-014), [STU-015](#stu-015), [STU-020](#stu-020) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Domains/Students/Services/StudentNotificationService.php](app/Domains/Students/Services/StudentNotificationService.php)<br>[app/Http/Controllers/Student/NotificationController.php](app/Http/Controllers/Student/NotificationController.php) |
| QA needs | STUDENT-DATA, SECURITY/PERMISSION |

### STU-023

**Optional rating/comment for own completed lesson**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Private feedback. Optional rating/comment for own completed lesson. Only completed owned lessons permit submission; no public testimonial publishing. |
| UI / routes | My learning/lesson feedback context<br>`POST /student/lessons/{booking}/feedback` — `student.lessons.feedback` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Owned completed Booking and its one feedback record. |
| Data created / changed | LessonFeedback rating 1–5 and optional comment; same booking row reused for edits. |
| Business rules | Only completed owned lessons permit submission; no public testimonial publishing. Student/Booking locks, one-row uniqueness, CSRF and 30/minute throttle prevent duplicates/foreign updates. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants are UTC; lesson display uses student/customer timezone; calendar dates use their explicitly stated business/student zone. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Only completed owned lessons permit submission; no public testimonial publishing. Student/Booking locks, one-row uniqueness, CSRF and 30/minute throttle prevent duplicates/foreign updates. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later submit/edit 4/5 on owned completed QA lesson; reject future/foreign/invalid rating and verify one private row. |
| Dependent / related capability IDs | [STU-007](#stu-007), [SA-059](#sa-059) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Http/Controllers/Student/LessonFeedbackController.php](app/Http/Controllers/Student/LessonFeedbackController.php) |
| QA needs | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION |

### STU-024

**Owned purchase statements and CSV/XLSX**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Finance. Owned purchase statements and CSV/XLSX. Statement separates immutable price/discount/final price, actual receipts/refunds, net paid, current due/overpaid and typed available/history. |
| UI / routes | Statements navigation → package statement<br>`GET\|HEAD /student/statements` — `student.statements.index`<br>`GET\|HEAD /student/packages/{package}/statement` — `student.statements.show`<br>`GET\|HEAD /student/packages/{package}/statement/export` — `student.statements.export` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Own purchases, actual payments/refunds, typed ledger and installment forecast via canonical summary. |
| Data created / changed | None apart from generated export temporary file. |
| Business rules | Statement separates immutable price/discount/final price, actual receipts/refunds, net paid, current due/overpaid and typed available/history. No fabricated receipt or payment provider reference. Another Student purchase returns 404; responses private/no-store. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants are UTC; lesson display uses student/customer timezone; calendar dates use their explicitly stated business/student zone. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | Own package index paginated 20; package detail; export format csv/xlsx. |
| CSV / XLSX / other export | CSV/XLSX matching actual statement rows; print view; shared formula safety and XLSX capacity limits. |
| Edges / negative cases | No fabricated receipt or payment provider reference. Another Student purchase returns 404; responses private/no-store. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php)<br>[tests/Feature/TypedEntitlementsTest.php](tests/Feature/TypedEntitlementsTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later partial/refund/overpaid QA purchase checks screen/CSV/XLSX parity and foreign/private header denial. |
| Dependent / related capability IDs | [SA-035](#sa-035), [STU-005](#stu-005), [STU-025](#stu-025) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Http/Controllers/Student/FinancialStatementController.php](app/Http/Controllers/Student/FinancialStatementController.php)<br>[app/Domains/Students/Services/FinancialStatementService.php](app/Domains/Students/Services/FinancialStatementService.php) |
| QA needs | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION |

### STU-025

**Read/print an actual owned payment receipt**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Finance receipts. Read/print an actual owned payment receipt. Receipt reflects an actual PaymentRecord; internal payment ID is labeled internal, and external/provider reference is shown only when already stored. |
| UI / routes | Package statement → payment receipt<br>`GET\|HEAD /student/packages/{package}/receipts/{payment}` — `student.receipts.show` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Payment belonging to own package, immutable recorded method/reference and original payment instant. |
| Data created / changed | None. |
| Business rules | Receipt reflects an actual PaymentRecord; internal payment ID is labeled internal, and external/provider reference is shown only when already stored. Method renaming does not rewrite recorded method; foreign/mismatched payment denies. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants are UTC; lesson display uses student/customer timezone; calendar dates use their explicitly stated business/student zone. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | Print-oriented receipt view; statement tabular export is STU-024. |
| Edges / negative cases | Method renaming does not rewrite recorded method; foreign/mismatched payment denies. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php)<br>[tests/Feature/StudentLedgerTest.php](tests/Feature/StudentLedgerTest.php) |
| Read-only / synthetic QA | **Read-only:** Yes, current output can be inspected without domain writes.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later inspect manually recorded QA receipt before/after payment-method rename and foreign/mismatched purchase denial. |
| Dependent / related capability IDs | [STU-024](#stu-024), [SA-029](#sa-029), [SA-035](#sa-035) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Http/Controllers/Student/FinancialStatementController.php](app/Http/Controllers/Student/FinancialStatementController.php) |
| QA needs | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION |

### STU-026

**Own holidays/unavailability with cancellation history**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Planning. Own holidays/unavailability with cancellation history. At most roughly one year (367-day validation bound); nonexistent DST midnight gives a friendly refusal. |
| UI / routes | Planning → holiday dates/timezone/reason<br>`GET\|HEAD /student/planning` — `student.scheduling.index`<br>`POST /student/planning/{kind}` — `student.scheduling.store`<br>`POST /student/holidays/{holiday}/cancel` — `student.holidays.cancel` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Own active/cancelled StudentUnavailability and selected timezone/reasons. |
| Data created / changed | Inclusive local-day range converted to half-open UTC interval, immutable request/fingerprint; cancelled status on closure. |
| Business rules | At most roughly one year (367-day validation bound); nonexistent DST midnight gives a friendly refusal. Linked Student booking/rescheduling/recurrence checks overlap; existing commitments are not auto-cancelled. Contact-only legacy bookings cannot infer Student holidays. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | date_from local midnight to day-after-date_to local midnight resolved in chosen Student zone; stored UTC. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | At most roughly one year (367-day validation bound); nonexistent DST midnight gives a friendly refusal. Linked Student booking/rescheduling/recurrence checks overlap; existing commitments are not auto-cancelled. Contact-only legacy bookings cannot infer Student holidays. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later create/cancel a QA holiday, check overlap at boundaries/DST and unchanged existing lessons; foreign closure/key drift rejects. |
| Dependent / related capability IDs | [STU-009](#stu-009), [STU-010](#stu-010), [SA-050](#sa-050), [SA-048](#sa-048) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Domains/Students/Services/StudentSchedulingService.php](app/Domains/Students/Services/StudentSchedulingService.php)<br>[app/Http/Controllers/Student/SchedulingController.php](app/Http/Controllers/Student/SchedulingController.php) |
| QA needs | STUDENT-DATA, BOOKING-DATA, SECURITY/PERMISSION |

### STU-027

**Own lesson waitlist interest and withdrawal**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Planning. Own lesson waitlist interest and withdrawal. Maximum date-range difference 90 days; interest grants no credits and creates no Booking/hold/payment. |
| UI / routes | Planning → waitlist range/type/timezone<br>`GET\|HEAD /student/planning` — `student.scheduling.index`<br>`POST /student/planning/{kind}` — `student.scheduling.store`<br>`POST /student/waitlist/{interest}/withdraw` — `student.waitlist.withdraw` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Own BookingWaitlists and selected existing SessionType. |
| Data created / changed | Open interest with immutable owner/range/request fingerprint; withdrawn status/history. |
| Business rules | Maximum date-range difference 90 days; interest grants no credits and creates no Booking/hold/payment. Staff follow-up and status are manual; no automatic slot assignment or outreach is promised. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants are UTC; lesson display uses student/customer timezone; calendar dates use their explicitly stated business/student zone. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Maximum date-range difference 90 days; interest grants no credits and creates no Booking/hold/payment. Staff follow-up and status are manual; no automatic slot assignment or outreach is promised. |
| Background / manual behavior | **Background:** No automatic waitlist worker or booking generation.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later create/replay/withdraw one QA interest; compare staff contacted/closed history and unchanged entitlements/bookings. |
| Dependent / related capability IDs | [SA-049](#sa-049), [STU-026](#stu-026) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Domains/Students/Services/StudentSchedulingService.php](app/Domains/Students/Services/StudentSchedulingService.php)<br>[app/Http/Controllers/Student/SchedulingController.php](app/Http/Controllers/Student/SchedulingController.php) |
| QA needs | STUDENT-DATA, BOOKING-DATA, SECURITY/PERMISSION |

### STU-028

**Time-gated meeting link reveal**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** STUDENT PORTAL. **Domain:** Lesson access. Time-gated meeting link reveal. Link only reveals for confirmed not-ended lessons within configured lead minutes (default 15, range 0–1440), with assigned safe HTTPS snapshot and valid unsuspended Student where linked. |
| UI / routes | Eligible confirmed session Join action and private confirmation<br>`GET\|HEAD /student` — `student.dashboard`<br>`GET\|HEAD /booking/confirmation/{token}` — `booking.confirmation`<br>`GET\|HEAD /fr/reservation/confirmation/{token}` — `booking.confirmation.fr`<br>`GET\|HEAD /de/buchen/bestaetigung/{token}` — `booking.confirmation.de` |
| Prerequisites | Existing verified, unsuspended, unmerged Student; valid three-hour student guard/session. |
| Data read | Assigned meeting URL/provider snapshot, confirmed status, Student verification and start/end instants. |
| Data created / changed | None. |
| Business rules | Link only reveals for confirmed not-ended lessons within configured lead minutes (default 15, range 0–1440), with assigned safe HTTPS snapshot and valid unsuspended Student where linked. No early URL or completed/cancelled/no-show link; room edits do not overwrite snapshots automatically. |
| Configuration | meeting.student_reveal_minutes; enabled room/provider and assignment. |
| Timezone | Reveal uses absolute UTC start/end comparisons; displayed lesson stays customer-zone aware. |
| Authorization / privacy | Student guard and owner/visibility queries; foreign/private objects return 404; staff-only preparation is never shared. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | No early URL or completed/cancelled/no-show link; room edits do not overwrite snapshots automatically. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/MeetingRotationTest.php](tests/Feature/MeetingRotationTest.php)<br>[tests/Feature/StudentAuthenticationTest.php](tests/Feature/StudentAuthenticationTest.php) |
| Read-only / synthetic QA | **Read-only:** Yes, current output can be inspected without domain writes.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later safe QA room/lesson verifies just-before/within/after window and suspended/cancelled denial without publishing owner room URL. |
| Dependent / related capability IDs | [STU-007](#stu-007), [SA-051](#sa-051), [PUB-012](#pub-012) |
| Production cross-check | Current production has zero Student accounts. Student sign-in/guard surface and deployed routes/source were checked; prior same-SHA authorized temporary-Student smoke covered empty portal areas and cleanup. This inventory created no account and did not verify populated authenticated workflows. |
| Current source evidence | [app/Domains/Booking/Services/MeetingLinkService.php](app/Domains/Booking/Services/MeetingLinkService.php)<br>[app/Http/Controllers/Student/DashboardController.php](app/Http/Controllers/Student/DashboardController.php)<br>[app/Http/Controllers/BookingController.php](app/Http/Controllers/BookingController.php) |
| QA needs | STUDENT-DATA, BOOKING-DATA, SECURITY/PERMISSION |

## SUPER ADMIN

[SA-001](#sa-001) Role-aware navigation, desktop collapse, mobile drawer and time-format preference · [SA-002](#sa-002) Quick launcher, Student finder and existing cross-domain search · [SA-003](#sa-003) Tutor dashboard and next-lesson business/customer clocks · [SA-004](#sa-004) Today, follow-up queues and canonical business summaries · [SA-005](#sa-005) Create, search, edit and soft-delete Staff Notes · [SA-006](#sa-006) Shared operational pins with actor/time · [SA-007](#sa-007) Personal pins, favorites and collections · [SA-008](#sa-008) Lightweight assigned tasks and status lifecycle · [SA-009](#sa-009) Save, apply, replace-by-name and remove personal filter views · [SA-010](#sa-010) Recently viewed Student, Booking/lesson and Contact shortcuts · [SA-011](#sa-011) Prominent operational alerts and resolved/archived history · [SA-012](#sa-012) Staff notification inbox and read/delete state · [SA-013](#sa-013) Password sign-in, staged MFA proof and staff logout · [SA-014](#sa-014) Staff reset-link request and password reset without removing MFA · [SA-015](#sa-015) Optional Super Admin TOTP enrollment and confirmation · [SA-016](#sa-016) Strong recovery-code regeneration, authenticator replacement and disable · [SA-017](#sa-017) Create/update/soft-delete staff accounts and roles · [SA-018](#sa-018) Suspend and restore Student or staff access with reason · [SA-019](#sa-019) Canonical roster, filtered overview, detail and CSV/XLSX · [SA-020](#sa-020) Update/verify recorded identity and contextual contact actions · [SA-021](#sa-021) Contact directory, acquisition history, lead inquiry view and staff notes · [SA-022](#sa-022) Review and explicitly merge suspected duplicate Contacts · [SA-023](#sa-023) Explicit canonical Student merge preserving facts/provenance · [SA-024](#sa-024) Student privacy anonymization and owned-content redaction · [SA-025](#sa-025) Active/inactive/archive lifecycle with explicit history · [SA-026](#sa-026) Student selection, filtered transactions and financial exports · [SA-027](#sa-027) Create canonical preset or classified custom purchases and grants · [SA-028](#sa-028) Reviewed discount and 48-hour diagnostic-credit eligibility · [SA-029](#sa-029) Record positive manual payments and settlement · [SA-030](#sa-030) Record bounded refunds with optional explicit typed forfeiture · [SA-031](#sa-031) Explicit typed courtesy adjustments and historical balance projection · [SA-032](#sa-032) Extend an existing expiry with reason and stale-date protection · [SA-033](#sa-033) Immutable full-price installment schedule, FIFO projection and voided history · [SA-034](#sa-034) Current balances, overdue installments, partial payments and overpayments · [SA-035](#sa-035) Staff package statements, actual receipts, print and exports · [SA-036](#sa-036) Create a new linked purchase/grant without rewriting previous history · [SA-037](#sa-037) Descriptive cash, dues, typed usage, expiry and renewal/repeat indicators · [SA-038](#sa-038) Read-only billing integrity and typed provenance diagnostics · [SA-039](#sa-039) Business-time booking list, day/week/month calendar, detail and notes · [SA-040](#sa-040) Manual direct booking with optional canonical Student preselection · [SA-041](#sa-041) Staff reschedule with canonical slot and immutable typed funding · [SA-042](#sa-042) Mark completed or no-show without fabricating payment · [SA-043](#sa-043) Staff cancellation and separate immutable credit consequence · [SA-044](#sa-044) SessionType duration/price/funding and exact entitlement requirement · [SA-045](#sa-045) Weekly tutor availability, duration/buffer/notice/horizon overrides · [SA-046](#sa-046) Date-specific tutor exceptions/blocked days with booked-history warning · [SA-047](#sa-047) Cancellation/no-show policy and immutable new-booking snapshots · [SA-048](#sa-048) Explicit recurring plans, bounded manual generation and safe blocked reasons · [SA-049](#sa-049) Review/contact/close/withdraw lesson interest manually · [SA-050](#sa-050) Staff manage Student unavailability separately from tutor calendar · [SA-051](#sa-051) Provider/room pools, preference, explicit assignment and reconciliation · [SA-052](#sa-052) Staff manage private PDF, Resource, recording/link materials and sharing · [SA-053](#sa-053) Author shared/private homework and tutor completion/feedback · [SA-054](#sa-054) Learning plans and editable milestones with completion facts · [SA-055](#sa-055) Staff-only Student/lesson preparation · [SA-056](#sa-056) Reference existing Resources with instructions, sharing and review reset · [SA-057](#sa-057) Maintain pronunciation/vocabulary/grammar error log · [SA-058](#sa-058) Normalize and deduplicate Student/lesson tags · [SA-059](#sa-059) Read private Student lesson ratings/comments · [SA-060](#sa-060) Staff author/share and Super Admin manage Student Educational Notes · [SA-061](#sa-061) Library authoring, private payload/cover replacement, drafts and publication · [SA-062](#sa-062) Manage category labels, active/order and localization · [SA-063](#sa-063) Upload/pick public Media with reference-protected removal · [SA-064](#sa-064) Draft/live Pages, sanitized rich text, revisions and explicit restoration · [SA-065](#sa-065) FAQ drafts and native social/Reddit/WhatsApp channel configuration · [SA-066](#sa-066) French/German draft publication and source-revision reconciliation · [SA-067](#sa-067) Blog draft/publication, sanitized Markdown, optimistic edits and old-slug history · [SA-068](#sa-068) Game catalog status, safe target, draft/publish and localized presentation · [SA-069](#sa-069) Manage scheduled promotion bars, modal/cards and countdown · [SA-070](#sa-070) Versioned questionnaire authoring, conditions, assignment triggers and publication · [SA-071](#sa-071) Versioned submissions, permission-filtered answers and tabular export · [SA-072](#sa-072) Live Pulse, primary/resource/game funnels, goals and acquisition reports · [SA-073](#sa-073) Country traffic, conversion, flags and sorting/export · [SA-074](#sa-074) Section views, dwell, bounce/drop-off and filtering/export · [SA-075](#sa-075) Traffic, booking, Resource, social, event and campaign reports/exports · [SA-076](#sa-076) Friendly maintenance visitor/session/country details and export · [SA-077](#sa-077) Branding, public copy, business timezone, booking defaults, counters and privacy goals · [SA-078](#sa-078) Configure emergency announcement audience, localized text, window, severity and CTA · [SA-079](#sa-079) Application Maintenance Mode switch and message · [SA-080](#sa-080) Read environment, clocks, storage and operational heartbeat health · [SA-081](#sa-081) Create/list/download/delete positively managed private backups · [SA-082](#sa-082) Safe role-restricted audit viewer and readable allowlisted diffs · [SA-083](#sa-083) Read-only identity/purchase/configuration/relationship risks and bounded duplicate candidates · [SA-084](#sa-084) Existing bot/destination/global switches and controlled read-command settings · [SA-085](#sa-085) Rule templates, booking reminders, event alerts and on-demand digest requests · [SA-086](#sa-086) Safe delivery history, enabled-destination test and one-off verified status pathway · [SA-087](#sa-087) Permanent feature-off boundary and production compatibility · [SA-088](#sa-088) Immutable previews, password/factor/token confirmation, locks and safe recovery · [SA-089](#sa-089) Reviewed analytics-fact reset with retained business boundaries · [SA-090](#sa-090) Reviewed all-Student ownership graph reset and auth cleanup · [SA-091](#sa-091) Coherent financial test-history reset with required booking dependents · [SA-092](#sa-092) Immutable selected Resources/Categories/history/files reset with safe detachment · [SA-093](#sa-093) Reset only proven managed local snapshots and last-result lifecycle · [SA-094](#sa-094) Export Everything/Custom bounded private archive with module/filter closure · [SA-095](#sa-095) Upload inspection-only compatible portable archive and conflict preview · [SA-096](#sa-096) Restore missing/skip identical/refuse differences with stable provenance · [SA-097](#sa-097) Owner operation summary, private hash-verified download and manual retention · [SA-098](#sa-098) Trusted-console explicit legacy entitlement inventory/classification · [SA-099](#sa-099) Enabled/default payment methods and immutable receipt labels

### SA-001

**Role-aware navigation, desktop collapse, mobile drawer and time-format preference**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Admin shell. Role-aware navigation, desktop collapse, mobile drawer and time-format preference. Actual owner profile supplies name and human role label; desktop collapse and mobile drawer stay separate. |
| UI / routes | Super Admin Operations Console/account card; sidebar; My time format<br>`GET\|HEAD /admin` — `admin.dashboard`<br>`POST /admin/preferences/time` — `admin.preferences.time` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Authenticated Administrator profile/role, permitted routes and time_format. |
| Data created / changed | Browser-local collapse preference; own time_format after Save. |
| Business rules | Actual owner profile supplies name and human role label; desktop collapse and mobile drawer stay separate. Existing role-gated links and active/MFA authorization remain authoritative; 12/24-hour preference does not alter timestamps. |
| Configuration | Authenticated Administrator.time_format; browser sidebar state. |
| Timezone | Personal display format only; business/customer zones remain explicit. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`; `super_admin,admin,assistant`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Existing role-gated links and active/MFA authorization remain authoritative; 12/24-hour preference does not alter timestamps. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/AdminDrawerAndSocialAccessibilityTest.php](tests/Feature/AdminDrawerAndSocialAccessibilityTest.php)<br>[tests/Feature/StageOnePresentationTest.php](tests/Feature/StageOnePresentationTest.php)<br>[tests/Feature/StaffOperationsProductivityTest.php](tests/Feature/StaffOperationsProductivityTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Inspect Abdallah/Super Admin branding; later QA role accounts compare permitted menus and collapse/mobile/time-format behavior. |
| Dependent / related capability IDs | [SA-013](#sa-013), [SA-015](#sa-015) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [resources/views/layouts/admin.blade.php](resources/views/layouts/admin.blade.php)<br>[resources/js/app.js](resources/js/app.js)<br>[app/Http/Controllers/Admin/DashboardController.php](app/Http/Controllers/Admin/DashboardController.php)<br>[app/Http/Controllers/Admin/SettingController.php](app/Http/Controllers/Admin/SettingController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION |

### SA-002

**Quick launcher, Student finder and existing cross-domain search**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Productivity/search. Quick launcher, Student finder and existing cross-domain search. Launcher filters existing permitted destinations and submits to the current Student Records finder; global search uses its own Contact/Booking/Resource query (minimum 2 characters, up to 10 each). |
| UI / routes | Quick actions / Ctrl or Meta K; desktop Search<br>`GET\|HEAD /admin/students` — `admin.students.index`<br>`GET\|HEAD /admin/search` — `admin.search` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Role-aware destination labels; normalized roster search; bounded Contact/Booking/Resource search. |
| Data created / changed | Browser dialog/query state only. |
| Business rules | Launcher filters existing permitted destinations and submits to the current Student Records finder; global search uses its own Contact/Booking/Resource query (minimum 2 characters, up to 10 each). It is not a new indexing engine or permission bypass. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin,assistant`; `super_admin,admin`. |
| Filters / search | Launcher destination label; Student q; global q. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | It is not a new indexing engine or permission bypass. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StaffOperationsProductivityTest.php](tests/Feature/StaffOperationsProductivityTest.php)<br>[tests/Feature/AdminFunctionsAndCalendarTest.php](tests/Feature/AdminFunctionsAndCalendarTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Inspect/filter destinations and empty search; later search own disposable records and verify role privacy/focus/Escape. |
| Dependent / related capability IDs | [SA-019](#sa-019), [SA-001](#sa-001) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [resources/views/components/staff-quick-actions.blade.php](resources/views/components/staff-quick-actions.blade.php)<br>[app/Http/Controllers/Admin/SearchController.php](app/Http/Controllers/Admin/SearchController.php)<br>[app/Http/Controllers/Admin/StudentController.php](app/Http/Controllers/Admin/StudentController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, RESOURCE-DATA |

### SA-003

**Tutor dashboard and next-lesson business/customer clocks**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Operations. Tutor dashboard and next-lesson business/customer clocks. Next lesson shows business and actual customer timezone; current schedule/status and distinct business counts come from records. |
| UI / routes | Dashboard<br>`GET\|HEAD /admin` — `admin.dashboard` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Current bookings/schedules, 30-day business counts, Student/resource metrics and safe audit summary. |
| Data created / changed | None. |
| Business rules | Next lesson shows business and actual customer timezone; current schedule/status and distinct business counts come from records. Only Super Admin sees audit summary; Assistant lands on Today, not this privileged dashboard. Empty upcoming schedule is a supported state. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Only Super Admin sees audit summary; Assistant lands on Today, not this privileged dashboard. Empty upcoming schedule is a supported state. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/AdminUiBillingPresentationTest.php](tests/Feature/AdminUiBillingPresentationTest.php)<br>[tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php)<br>[tests/Feature/StaffOperationsProductivityTest.php](tests/Feature/StaffOperationsProductivityTest.php) |
| Read-only / synthetic QA | **Read-only:** Yes, current output can be inspected without domain writes.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later populated QA lessons verify next/upcoming and distinct counts, customer zone and lower-role audit exclusion. |
| Dependent / related capability IDs | [SA-039](#sa-039), [SA-004](#sa-004), [SA-082](#sa-082) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/DashboardController.php](app/Http/Controllers/Admin/DashboardController.php)<br>[resources/views/admin/dashboard.blade.php](resources/views/admin/dashboard.blade.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION |

### SA-004

**Today, follow-up queues and canonical business summaries**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Operations. Today, follow-up queues and canonical business summaries. Each section displays at most 12 while counts cover all matches. |
| UI / routes | Today & Operations<br>`GET\|HEAD /admin/today` — `admin.operations.index` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Business-day lessons, upcoming 7-day lessons, tasks/alerts/pins, typed expiry/low credit, net payment follow-ups, submitted forms and personal recents. |
| Data created / changed | None. |
| Business rules | Each section displays at most 12 while counts cover all matches. Low credit aggregates only eligible same-type units per Student (0–2); expiry window is 7 business days. Inactive daily queues omit archived/inactive students but existing booked commitments remain visible. Assistant financial/form sections and task scope are restricted. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin,assistant`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Follow the publication/eligibility and current-state rules above; validate the rejected or unavailable case in the acceptance scenario. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StaffOperationsProductivityTest.php](tests/Feature/StaffOperationsProductivityTest.php)<br>[tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php) |
| Read-only / synthetic QA | **Read-only:** Yes, current output can be inspected without domain writes.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later small cohort checks exact today/next-7/overdue/low-rights/net-due/form counts, archived commitment retention and scoped Assistant view. |
| Dependent / related capability IDs | [SA-008](#sa-008), [SA-011](#sa-011), [SA-007](#sa-007), [SA-034](#sa-034), [SA-025](#sa-025), [SA-010](#sa-010) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Administration/Services/OperationsReadModel.php](app/Domains/Administration/Services/OperationsReadModel.php)<br>[app/Http/Controllers/Admin/OperationsController.php](app/Http/Controllers/Admin/OperationsController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA |

### SA-005

**Create, search, edit and soft-delete Staff Notes**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Staff notes. Create, search, edit and soft-delete Staff Notes. Notes are staff-only and escaped; q/owner/order/collection filters apply. |
| UI / routes | Staff Notes<br>`GET\|HEAD /admin/staff-notes` — `admin.staff-bins.index`<br>`POST /admin/staff-notes` — `admin.staff-bins.store`<br>`GET\|HEAD /admin/staff-notes/{bin}/edit` — `admin.staff-bins.edit`<br>`PUT /admin/staff-notes/{bin}` — `admin.staff-bins.update`<br>`DELETE /admin/staff-notes/{bin}` — `admin.staff-bins.destroy` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | StaffBins/author and shared/personal preferences. |
| Data created / changed | StaffBins text/author and soft-deletion/audit history. |
| Business rules | Notes are staff-only and escaped; q/owner/order/collection filters apply. Existing author protections remain; Super Admin can manage/delete others, DELETE confirmation required, history retained. Student Educational Notes are a separate ownership domain. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin,assistant`. |
| Filters / search | q, sort newest/oldest/title, owner mine/all, collection all/shared/mine/favorites; 20 per page. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Follow the publication/eligibility and current-state rules above; validate the rejected or unavailable case in the acceptance scenario. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BinAuthorizationTest.php](tests/Feature/BinAuthorizationTest.php)<br>[tests/Feature/StaffOperationsProductivityTest.php](tests/Feature/StaffOperationsProductivityTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later disposable Staff Note create/edit/search/delete with exact confirmation; verify staff-only exposure and author permission limits. |
| Dependent / related capability IDs | [SA-006](#sa-006), [SA-007](#sa-007), [SA-009](#sa-009) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/StaffBinController.php](app/Http/Controllers/Admin/StaffBinController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION |

### SA-006

**Shared operational pins with actor/time**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Staff notes. Shared operational pins with actor/time. Only Super Admin changes shared pin; current authorized staff can read it. |
| UI / routes | Staff Notes → Shared pin; Today shared pins<br>`PATCH /admin/staff-bins/{bin}/shared-pin` — `admin.staff-bins.shared-pin`<br>`GET\|HEAD /admin/today` — `admin.operations.index` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Current StaffBin and Administrator. |
| Data created / changed | Global pinned flag, pinned_by and UTC pinned_at; safe audit. |
| Business rules | Only Super Admin changes shared pin; current authorized staff can read it. Unpin clears shared actor/time, without changing personal preference flags. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin,assistant`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Follow the publication/eligibility and current-state rules above; validate the rejected or unavailable case in the acceptance scenario. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StaffOperationsProductivityTest.php](tests/Feature/StaffOperationsProductivityTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later pin/unpin a disposable note, inspect actor/time on Today and Admin/Assistant shared-write denial. |
| Dependent / related capability IDs | [SA-005](#sa-005), [SA-004](#sa-004) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/StaffBinController.php](app/Http/Controllers/Admin/StaffBinController.php)<br>[app/Policies/StaffBinPolicy.php](app/Policies/StaffBinPolicy.php)<br>[app/Http/Controllers/Admin/OperationsController.php](app/Http/Controllers/Admin/OperationsController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION |

### SA-007

**Personal pins, favorites and collections**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Staff notes. Personal pins, favorites and collections. Personal flags do not change global pin or another staff member. |
| UI / routes | Staff Notes → My Pin/My Favorite and collections<br>`PATCH /admin/staff-bins/{bin}/personal` — `admin.staff-bins.personal`<br>`GET\|HEAD /admin/staff-notes` — `admin.staff-bins.index` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Current note and current-Administrator StaffNotePreference. |
| Data created / changed | Only requested current-user pinned/favorite flag; unique per Administrator/note identity. |
| Business rules | Personal flags do not change global pin or another staff member. Updating one flag leaves the other intact; deleted notes are excluded. Collection/sort queries use authenticated ID. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin,assistant`. |
| Filters / search | All, Shared Pins, My Pins, My Favorites; owner/search/order. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Personal flags do not change global pin or another staff member. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StaffOperationsProductivityTest.php](tests/Feature/StaffOperationsProductivityTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later two QA staff accounts apply independent pin/favorite flags; reload isolated collections and verify no global/other-user mutation. |
| Dependent / related capability IDs | [SA-005](#sa-005), [SA-006](#sa-006) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/StaffBinController.php](app/Http/Controllers/Admin/StaffBinController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION |

### SA-008

**Lightweight assigned tasks and status lifecycle**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Staff productivity. Lightweight assigned tasks and status lifecycle. Open/in_progress/completed/cancelled lifecycle; completing sets original completion instant, reopening clears it. |
| UI / routes | Staff Tasks; Today task sections<br>`GET\|HEAD /admin/tasks` — `admin.tasks.index`<br>`POST /admin/tasks` — `admin.tasks.store`<br>`PATCH /admin/tasks/{task}` — `admin.tasks.update` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | StaffTasks, active staff assignees and optional canonical Student. |
| Data created / changed | Task title/text/student/assignee/due/status/priority, completed_at and safe lifecycle audit. |
| Business rules | Open/in_progress/completed/cancelled lifecycle; completing sets original completion instant, reopening clears it. Super Admin/Admin manage assignment/text; Assistant creates for self and changes assigned task status only. Invalid merged/foreign parent or assignee refuses. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin,assistant`. |
| Filters / search | q, status, priority, owner, due, student_id; 20 per page. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Invalid merged/foreign parent or assignee refuses. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StaffOperationsProductivityTest.php](tests/Feature/StaffOperationsProductivityTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later overdue/high-priority Student task assigned to QA Assistant; prove status-only own action, foreign/forged reassignment denial and Today count change. |
| Dependent / related capability IDs | [SA-004](#sa-004), [SA-009](#sa-009), [SA-025](#sa-025) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/StaffTaskController.php](app/Http/Controllers/Admin/StaffTaskController.php)<br>[app/Domains/Administration/Services/StaffTaskQuery.php](app/Domains/Administration/Services/StaffTaskQuery.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA |

### SA-009

**Save, apply, replace-by-name and remove personal filter views**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Personal productivity. Save, apply, replace-by-name and remove personal filter views. Only Students, Staff Notes and Tasks are supported. |
| UI / routes | Student Records/Staff Notes/Tasks Saved views<br>`POST /admin/saved-views` — `admin.saved-views.store`<br>`GET\|HEAD /admin/saved-views/{savedView}` — `admin.saved-views.apply`<br>`DELETE /admin/saved-views/{savedView}` — `admin.saved-views.destroy` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Current actor/section and validated current filters. |
| Data created / changed | StaffSavedViews current-user section/name/configuration. |
| Business rules | Only Students, Staff Notes and Tasks are supported. Section-specific scalar allowlists reuse current validation; nested/unknown filters reject, same name updates, max 20 per actor/section and foreign IDs 404. Views are personal, not shared. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin,assistant`. |
| Filters / search | Stored allowlisted filters for students/staff_notes/tasks only. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Section-specific scalar allowlists reuse current validation; nested/unknown filters reject, same name updates, max 20 per actor/section and foreign IDs 404. Views are personal, not shared. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StaffOperationsProductivityTest.php](tests/Feature/StaffOperationsProductivityTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later save/apply/update/delete one view per supported section; compare two staff identities and invalid/21st view rejection. |
| Dependent / related capability IDs | [SA-019](#sa-019), [SA-005](#sa-005), [SA-008](#sa-008) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/StaffSavedViewController.php](app/Http/Controllers/Admin/StaffSavedViewController.php)<br>[app/Domains/Administration/Services/StaffSavedViewService.php](app/Domains/Administration/Services/StaffSavedViewService.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION |

### SA-010

**Recently viewed Student, Booking/lesson and Contact shortcuts**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Personal productivity. Recently viewed Student, Booking/lesson and Contact shortcuts. Stores no page snapshots or copied PII/text. |
| UI / routes | Today → Recently viewed by me<br>`GET\|HEAD /admin/today` — `admin.operations.index`<br>`GET\|HEAD /admin/students/{student}` — `admin.students.show`<br>`GET\|HEAD /admin/bookings/{booking}` — `admin.bookings.show`<br>`GET\|HEAD /admin/contacts/{contact}` — `admin.contacts.show` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Own StaffRecentViews plus currently authorized/live entity labels. |
| Data created / changed | Successful authorized detail GET records entity type/ID/UTC time; retains newest 30, shows up to 12. |
| Business rules | Stores no page snapshots or copied PII/text. Guest/suspended/forbidden reads do not record; merged/deleted/inactive Student links are omitted; history is per staff member. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin,assistant`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Stores no page snapshots or copied PII/text. Guest/suspended/forbidden reads do not record; merged/deleted/inactive Student links are omitted; history is per staff member. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StaffOperationsProductivityTest.php](tests/Feature/StaffOperationsProductivityTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: reading entity detail can update personal recent history; Today list itself is read-only.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later view three disposable entities, revisit/order and exceed bound; verify another staff sees independent history and forbidden views add nothing. |
| Dependent / related capability IDs | [SA-004](#sa-004), [SA-019](#sa-019), [SA-021](#sa-021), [SA-039](#sa-039) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Middleware/TrackStaffRecentView.php](app/Http/Middleware/TrackStaffRecentView.php)<br>[app/Domains/Administration/Services/StaffRecentViewService.php](app/Domains/Administration/Services/StaffRecentViewService.php)<br>[app/Http/Controllers/Admin/OperationsController.php](app/Http/Controllers/Admin/OperationsController.php)<br>[app/Http/Controllers/Admin/StudentController.php](app/Http/Controllers/Admin/StudentController.php)<br>[app/Http/Controllers/Admin/BookingController.php](app/Http/Controllers/Admin/BookingController.php)<br>[app/Http/Controllers/Admin/ContactController.php](app/Http/Controllers/Admin/ContactController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA |

### SA-011

**Prominent operational alerts and resolved/archived history**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Student operations. Prominent operational alerts and resolved/archived history. Active/resolved/archived alerts remain separate from teaching notes and finance. |
| UI / routes | Student Records detail → Operational alerts; Today<br>`POST /admin/students/{student}/alerts` — `admin.student-alerts.store`<br>`PATCH /admin/students/{student}/alerts/{alert}` — `admin.student-alerts.update` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Canonical Student and current StudentOperationalAlerts. |
| Data created / changed | Alert title/body/status, creator/updater, resolved_at and identifier/status audit. |
| Business rules | Active/resolved/archived alerts remain separate from teaching notes and finance. Student-first lock rechecks parent; Assistant reads without management controls; content escaped and not sent into generic analytics/exports. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin,assistant`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Student-first lock rechecks parent; Assistant reads without management controls; content escaped and not sent into generic analytics/exports. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StaffOperationsProductivityTest.php](tests/Feature/StaffOperationsProductivityTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later create/resolve/reactivate/archive one QA alert and verify parent mismatch, Assistant write denial and Today prominence. |
| Dependent / related capability IDs | [SA-019](#sa-019), [SA-004](#sa-004), [SA-025](#sa-025) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/StudentOperationalAlertController.php](app/Http/Controllers/Admin/StudentOperationalAlertController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA |

### SA-012

**Staff notification inbox and read/delete state**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Staff notifications. Staff notification inbox and read/delete state. Administrative operational/business/system notifications differ from Student in-app events. |
| UI / routes | Notifications<br>`GET\|HEAD /admin/notifications` — `admin.notifications.index`<br>`POST /admin/notifications/{notification}/read` — `admin.notifications.read`<br>`POST /admin/notifications/read-all` — `admin.notifications.read-all`<br>`DELETE /admin/notifications/{notification}` — `admin.notifications.destroy` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Existing Administrator notification records and current actor scope. |
| Data created / changed | Read marks, read-all and authorized notification deletion. |
| Business rules | Administrative operational/business/system notifications differ from Student in-app events. Delivery producers may be background or request-driven; inbox actions are explicit and role/auth scoped. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | Current inbox tabs/read-state and pagination defined by NotificationController. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Follow the publication/eligibility and current-state rules above; validate the rejected or unavailable case in the acceptance scenario. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/AdminOperationsTest.php](tests/Feature/AdminOperationsTest.php)<br>[tests/Feature/BookingCreationNotificationTest.php](tests/Feature/BookingCreationNotificationTest.php)<br>[tests/Feature/OperationalRemediationTest.php](tests/Feature/OperationalRemediationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later disposable actor-specific operational notification checks unread/read-all/delete and foreign/role access; do not manufacture owner alerts here. |
| Dependent / related capability IDs | [SA-013](#sa-013), [SA-080](#sa-080), [STU-022](#stu-022) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/NotificationController.php](app/Http/Controllers/Admin/NotificationController.php)<br>[app/Domains/Administration/Services/AdminNotificationService.php](app/Domains/Administration/Services/AdminNotificationService.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION |

### SA-013

**Password sign-in, staged MFA proof and staff logout**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Staff authentication. Password sign-in, staged MFA proof and staff logout. Enabled Super Admin password stage alone grants no guard access; fresh TOTP/unused recovery completes it. |
| UI / routes | /admin/login; optional challenge; Sign Out<br>`GET\|HEAD /admin/login` — `admin.login`<br>`POST /admin/login` — `admin.login.submit`<br>`GET\|HEAD /admin/two-factor-challenge` — `admin.two-factor.challenge`<br>`POST /admin/two-factor-challenge` — `admin.two-factor.verify`<br>`POST /admin/logout` — `admin.logout` |
| Prerequisites | Existing active staff account and human-entered existing password; valid factor when enabled. |
| Data read | Administrator provider/password/role/suspension, pending challenge and current security fingerprint. |
| Data created / changed | Regenerated staff session/current MFA proof, login/logout audit; no new staff account. |
| Business rules | Enabled Super Admin password stage alone grants no guard access; fresh TOTP/unused recovery completes it. Current security/password/role/suspension invalidates stale proof; MFA accounts cannot bypass with remembered cookies. Assistant authorized landing is Today; Student guard is independent. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Unix/UTC authentication expiry and factor clock; Business Timezone does not affect MFA. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Enabled Super Admin password stage alone grants no guard access; fresh TOTP/unused recovery completes it. Current security/password/role/suspension invalidates stale proof; MFA accounts cannot bypass with remembered cookies. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/AdministratorTwoFactorTest.php](tests/Feature/AdministratorTwoFactorTest.php)<br>[tests/Feature/AdministratorAuthorizationTest.php](tests/Feature/AdministratorAuthorizationTest.php)<br>[tests/Feature/SessionInactivityAndCookieRefreshTest.php](tests/Feature/SessionInactivityAndCookieRefreshTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later dedicated QA accounts check disabled/enabled MFA, invalid proof, remember behavior, suspension and safe intended redirect; keep owner credentials out of logs. |
| Dependent / related capability IDs | [SA-015](#sa-015), [SA-018](#sa-018), [SA-001](#sa-001) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/AuthController.php](app/Http/Controllers/Admin/AuthController.php)<br>[app/Domains/Administration/Services/AdministratorLoginService.php](app/Domains/Administration/Services/AdministratorLoginService.php)<br>[app/Http/Controllers/Admin/TwoFactorChallengeController.php](app/Http/Controllers/Admin/TwoFactorChallengeController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION |

### SA-014

**Staff reset-link request and password reset without removing MFA**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Password recovery. Staff reset-link request and password reset without removing MFA. Request/attempt throttles and token expiry apply; successful reset retains active MFA and requires factor on next sign-in. |
| UI / routes | Forgot password / emailed reset URL<br>`GET\|HEAD /admin/forgot-password` — `admin.password.request`<br>`POST /admin/forgot-password` — `admin.password.email`<br>`GET\|HEAD /admin/reset-password/{token}` — `admin.password.reset`<br>`POST /admin/reset-password` — `admin.password.update` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Existing staff identity, broker reset token and suspension/security state. |
| Data created / changed | Broker token, configured recovery Mail and changed hashed password on valid reset; stale pending/proof invalidated. |
| Business rules | Request/attempt throttles and token expiry apply; successful reset retains active MFA and requires factor on next sign-in. Current Log mailer does not prove external inbox delivery. No public owner emergency bypass link. |
| Configuration | Existing password broker and configured mailer (currently Log). |
| Timezone | Broker token/security expiration uses UTC/Unix clocks. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Current Log mailer does not prove external inbox delivery. No public owner emergency bypass link. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/PasswordRecoveryTest.php](tests/Feature/PasswordRecoveryTest.php)<br>[tests/Feature/AdministratorTwoFactorTest.php](tests/Feature/AdministratorTwoFactorTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later disposable QA staff Mail capture checks valid/expired/reused token, password change with MFA retained and nonenumerating failures; no owner reset. |
| Dependent / related capability IDs | [SA-013](#sa-013), [SA-015](#sa-015) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/PasswordResetController.php](app/Http/Controllers/Admin/PasswordResetController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION |

### SA-015

**Optional Super Admin TOTP enrollment and confirmation**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Account Security. Optional Super Admin TOTP enrollment and confirmation. Only Super Admin manages own MFA. |
| UI / routes | Account Security → enrollment/confirmation<br>`GET\|HEAD /admin/security` — `admin.security.show`<br>`POST /admin/security/enrollment` — `admin.security.enroll`<br>`POST /admin/security/confirmation` — `admin.security.confirm` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Current owner-specific password/security state and session-bound encrypted pending enrollment. |
| Data created / changed | Encrypted pending/active TOTP secret, replay step/security version, hashed encrypted recovery set and proof/audit. |
| Business rules | Only Super Admin manages own MFA. Current password required; pending enrollment expires 10 minutes and QR/manual key appears only on initial response. Valid six-digit 30-second TOTP with ±1 step activates; one-time recovery plaintext is not stored/redisplayed. Partial/corrupt security state fails closed. |
| Configuration | Installed Google2FA/Bacon QR; APP_KEY continuity and synchronized host clock; owner currently disabled/pending enrollment preserved. |
| Timezone | Unix TOTP step, independent of business timezone/DST. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Valid six-digit 30-second TOTP with ±1 step activates; one-time recovery plaintext is not stored/redisplayed. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/AdministratorTwoFactorTest.php](tests/Feature/AdministratorTwoFactorTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later dedicated QA Super Admin enrollment with local QR and fresh code; verify wrong/foreign/expired setup and no redisclosure; preserve real owner state. |
| Dependent / related capability IDs | [SA-013](#sa-013), [SA-016](#sa-016) |
| Production cross-check | Account Security heading/surface verified; no secret/QR/password/factor read or enrollment changed. |
| Current source evidence | [app/Domains/Administration/Services/AdministratorTwoFactorService.php](app/Domains/Administration/Services/AdministratorTwoFactorService.php)<br>[app/Http/Controllers/Admin/TwoFactorSecurityController.php](app/Http/Controllers/Admin/TwoFactorSecurityController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION |

### SA-016

**Strong recovery-code regeneration, authenticator replacement and disable**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Account Security. Strong recovery-code regeneration, authenticator replacement and disable. Password plus fresh TOTP or unused recovery authorizes enabled-factor actions; replayed step/code fails under lock. |
| UI / routes | Account Security strong actions; separately reviewed trusted-console emergency recovery<br>`POST /admin/security/recovery-codes` — `admin.security.regenerate`<br>`POST /admin/security/authenticator` — `admin.security.reset`<br>`DELETE /admin/security/two-factor` — `admin.security.disable` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Own current encrypted factor/recovery/security state and strong confirmation. |
| Data created / changed | Atomic one-use recovery removal or new factor/recovery set/cleared state; security version/remember token/session changes and safe audit. |
| Business rules | Password plus fresh TOTP or unused recovery authorizes enabled-factor actions; replayed step/code fails under lock. Replacement keeps old authenticator until confirmation; regeneration invalidates old codes. Emergency administrator:recover-two-factor is interactive trusted-server workflow, not HTTP bypass or automatic support action. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Unix 30-second factor steps; sensitive session proofs/expiry independent of Business Timezone. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Replacement keeps old authenticator until confirmation; regeneration invalidates old codes. Emergency administrator:recover-two-factor is interactive trusted-server workflow, not HTTP bypass or automatic support action. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/AdministratorTwoFactorTest.php](tests/Feature/AdministratorTwoFactorTest.php)<br>[tests/Feature/MariaDbConcurrencyVerificationTest.php](tests/Feature/MariaDbConcurrencyVerificationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later dedicated QA owner uses fresh/unused factors for replace/regenerate/disable and tests replay/race; any production emergency action needs separate explicit review. |
| Dependent / related capability IDs | [SA-015](#sa-015), [SA-013](#sa-013) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Administration/Services/AdministratorTwoFactorService.php](app/Domains/Administration/Services/AdministratorTwoFactorService.php)<br>[app/Http/Controllers/Admin/TwoFactorSecurityController.php](app/Http/Controllers/Admin/TwoFactorSecurityController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION |

### SA-017

**Create/update/soft-delete staff accounts and roles**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Staff management. Create/update/soft-delete staff accounts and roles. Role keys super_admin/admin/assistant retain authorization. |
| UI / routes | Staff Administrators<br>`GET\|HEAD /admin/administrators` — `admin.administrators.index`<br>`GET\|HEAD /admin/administrators/create` — `admin.administrators.create`<br>`POST /admin/administrators` — `admin.administrators.store`<br>`GET\|HEAD /admin/administrators/{administrator}/edit` — `admin.administrators.edit`<br>`PUT\|PATCH /admin/administrators/{administrator}` — `admin.administrators.update`<br>`DELETE /admin/administrators/{administrator}` — `admin.administrators.destroy` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Administrators and available Super Admin count. |
| Data created / changed | Name/normalized email/hashed password/role, optional password replacement, soft deletion and safe audit. |
| Business rules | Role keys super_admin/admin/assistant retain authorization. Self-delete and deleting/demoting the last available Super Admin refuse; actor/security access is rechecked. Staff secrets are excluded from ordinary serialization/export; no bulk owner renaming. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin`. |
| Filters / search | Name-sorted 20-item list; edit specific account. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Self-delete and deleting/demoting the last available Super Admin refuse; actor/security access is rechecked. Staff secrets are excluded from ordinary serialization/export; no bulk owner renaming. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/AdministratorAuthorizationTest.php](tests/Feature/AdministratorAuthorizationTest.php)<br>[tests/Feature/AdminManagementAndCmsTest.php](tests/Feature/AdminManagementAndCmsTest.php)<br>[tests/Feature/AdministratorTwoFactorTest.php](tests/Feature/AdministratorTwoFactorTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later disposable staff accounts compare role changes and protected last-Super-Admin/self-delete boundaries; never use real owner for negative destructive tests. |
| Dependent / related capability IDs | [SA-013](#sa-013), [SA-018](#sa-018), [SA-015](#sa-015) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/AdministratorController.php](app/Http/Controllers/Admin/AdministratorController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION |

### SA-018

**Suspend and restore Student or staff access with reason**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Access management. Suspend and restore Student or staff access with reason. Only current active Super Admin; cannot suspend self or last available Super Admin. |
| UI / routes | Student Records or Staff Administrators suspension controls<br>`POST /admin/accounts/{type}/{account}/suspension` — `admin.accounts.suspension` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Current Super Admin, target identity, suspension and available owner count. |
| Data created / changed | suspended_at/by/reason, safe account access audit and targeted auth-cache invalidation. |
| Business rules | Only current active Super Admin; cannot suspend self or last available Super Admin. Suspension is distinct from operational inactive/archive and anonymization, and both login/current guard recheck it. Restore removes current suspension fields without erasing history. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Only current active Super Admin; cannot suspend self or last available Super Admin. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/AdministratorAuthorizationTest.php](tests/Feature/AdministratorAuthorizationTest.php)<br>[tests/Feature/StudentAuthenticationTest.php](tests/Feature/StudentAuthenticationTest.php)<br>[tests/Feature/AdministratorTwoFactorTest.php](tests/Feature/AdministratorTwoFactorTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later disposable Student/Admin suspended after login loses access; restore returns normal guard; protect self/last owner and spoofed actor. |
| Dependent / related capability IDs | [SA-017](#sa-017), [SA-020](#sa-020), [STU-001](#stu-001), [SA-025](#sa-025) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/AccountSuspensionController.php](app/Http/Controllers/Admin/AccountSuspensionController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA |

### SA-019

**Canonical roster, filtered overview, detail and CSV/XLSX**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Student Records. Canonical roster, filtered overview, detail and CSV/XLSX. Default operational active scope; explicit inactive/archived/all retain history. |
| UI / routes | Student Records<br>`GET\|HEAD /admin/students` — `admin.students.index`<br>`GET\|HEAD /admin/students/{student}` — `admin.students.show`<br>`GET\|HEAD /admin/students/export` — `admin.students.export` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Canonical nonmerged/nondeleted roster, typed ledger/packages/payment/refund summary, bookings and verified emails. |
| Data created / changed | None for listing/export; authorized detail may record personal recent view. |
| Business rules | Default operational active scope; explicit inactive/archived/all retain history. Normalized name/email/international-phone plus verified-secondary email search. Exports reuse exact screen filters and typed available/history concepts, not an undifferentiated credit total. No standalone Student-create UI route is exposed; public booking provisions identities. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin,assistant`; `super_admin,admin`. |
| Filters / search | q, identity/suspension status, operational_status, package, available/none credits, expiry range, preferred timezone, session_status and joined business-date range. |
| CSV / XLSX / other export | Matching CSV/XLSX with business timezone and typed balance text; formula-safe/capped XLSX. |
| Edges / negative cases | Exports reuse exact screen filters and typed available/history concepts, not an undifferentiated credit total. No standalone Student-create UI route is exposed; public booking provisions identities. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/FilterAwareExportsTest.php](tests/Feature/FilterAwareExportsTest.php)<br>[tests/Feature/TypedEntitlementsTest.php](tests/Feature/TypedEntitlementsTest.php)<br>[tests/Feature/StaffOperationsProductivityTest.php](tests/Feature/StaffOperationsProductivityTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later mixed/expired/archived/secondary-email cohort verifies exact screen/export parity and retained explicit history. |
| Dependent / related capability IDs | [SA-020](#sa-020), [SA-025](#sa-025), [SA-027](#sa-027), [SA-009](#sa-009), [SA-010](#sa-010) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Students/Services/StudentRecordsQuery.php](app/Domains/Students/Services/StudentRecordsQuery.php)<br>[app/Http/Controllers/Admin/StudentController.php](app/Http/Controllers/Admin/StudentController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, ENTITLEMENT-DATA |

### SA-020

**Update/verify recorded identity and contextual contact actions**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Student identity. Update/verify recorded identity and contextual contact actions. Canonical normalization and ownership conflicts protect existing Student identity; incomplete/legacy-unverified profiles require staff review. |
| UI / routes | Student Records → profile/context actions<br>`PATCH /admin/students/{student}` — `admin.students.update`<br>`GET\|HEAD /admin/students/{student}` — `admin.students.show` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Student normalized identity/DOB/contact/preferred timezone, duplicate state and existing relationships. |
| Data created / changed | Validated primary identity/normalizations/verification/preference and safe audit; no new unrelated profile. |
| Business rules | Canonical normalization and ownership conflicts protect existing Student identity; incomplete/legacy-unverified profiles require staff review. Contextual copy/book/Cashier/teaching/notes links follow current roles. Fields do not globally hardcode Abdallah or make unverified secondary addresses trusted. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`; `super_admin,admin,assistant`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Fields do not globally hardcode Abdallah or make unverified secondary addresses trusted. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentBackfillAndIdentityTest.php](tests/Feature/StudentBackfillAndIdentityTest.php)<br>[tests/Feature/StudentMergeAndPrivacyTest.php](tests/Feature/StudentMergeAndPrivacyTest.php)<br>[tests/Feature/StaffOperationsProductivityTest.php](tests/Feature/StaffOperationsProductivityTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later QA legacy profile supplies missing DOB/normalized phone/email; check conflict rejection, verification and role-aware copy/context actions without changing owner. |
| Dependent / related capability IDs | [SA-019](#sa-019), [STU-001](#stu-001), [SA-083](#sa-083), [SA-040](#sa-040) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/StudentController.php](app/Http/Controllers/Admin/StudentController.php)<br>[resources/views/components/student-quick-actions.blade.php](resources/views/components/student-quick-actions.blade.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA |

### SA-021

**Contact directory, acquisition history, lead inquiry view and staff notes**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Contacts/leads. Contact directory, acquisition history, lead inquiry view and staff notes. Contacts remain distinct from authenticated Students. |
| UI / routes | Students & Contacts; Leads (Resource Inquiries)<br>`GET\|HEAD /admin/contacts` — `admin.contacts.index`<br>`GET\|HEAD /admin/contacts/{contact}` — `admin.contacts.show`<br>`PATCH /admin/contacts/{contact}/notes` — `admin.contacts.notes`<br>`GET\|HEAD /admin/contacts/export` — `admin.contacts.export`<br>`GET\|HEAD /admin/leads` — `admin.leads` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Contacts, canonical attribution, bookings/resource request/download relationships and notes. |
| Data created / changed | Authorized contact notes/audit only; upstream booking/resource workflows provision Contacts. |
| Business rules | Contacts remain distinct from authenticated Students. Independent public leads survive Student-specific privacy/reset boundaries; current detail resolves canonical relationships. Search/export do not expose administrator credentials or raw IP reporting identity. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | Directory search; population students/resources/leads/students_resources/all; resource_id, category_id, booking_status, activity_from/activity_to, format. Leads have their own search; no invented segment editor. |
| CSV / XLSX / other export | Directory CSV/XLSX matching supported filters; lead detail itself has no arbitrary archive export. |
| Edges / negative cases | Search/export do not expose administrator credentials or raw IP reporting identity. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/ContactIdentityTest.php](tests/Feature/ContactIdentityTest.php)<br>[tests/Feature/FilterAwareExportsTest.php](tests/Feature/FilterAwareExportsTest.php)<br>[tests/Feature/AdminOperationsTest.php](tests/Feature/AdminOperationsTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later approved synthetic Resource lead and booking Contact verify distinct segments, chronology, notes and filtered directory export. |
| Dependent / related capability IDs | [PUB-015](#pub-015), [PUB-011](#pub-011), [SA-022](#sa-022), [SA-010](#sa-010) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/ContactController.php](app/Http/Controllers/Admin/ContactController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, RESOURCE-DATA, ANALYTICS-DATA |

### SA-022

**Review and explicitly merge suspected duplicate Contacts**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Contact identity. Review and explicitly merge suspected duplicate Contacts. Explicit two different existing IDs required; review candidates are not automatic identity proof. |
| UI / routes | Contacts → duplicate review<br>`GET\|HEAD /admin/contacts/duplicates` — `admin.contacts.duplicates`<br>`POST /admin/contacts/merge` — `admin.contacts.merge` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Suspected Contact groups and canonical/duplicate identity relationships. |
| Data created / changed | Controlled canonical ownership/merged Contact state and audit while retaining booking/resource history. |
| Business rules | Explicit two different existing IDs required; review candidates are not automatic identity proof. ContactService reassigns references under locks and preserves history; does not convert public leads into authenticated Students by guesswork. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Explicit two different existing IDs required; review candidates are not automatic identity proof. ContactService reassigns references under locks and preserves history; does not convert public leads into authenticated Students by guesswork. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/ContactIdentityTest.php](tests/Feature/ContactIdentityTest.php)<br>[tests/Feature/MariaDbConcurrencyVerificationTest.php](tests/Feature/MariaDbConcurrencyVerificationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later disposable duplicate Contact pair merges once; confirm all referenced history and canonical resolution, including concurrency/invalid identical IDs. |
| Dependent / related capability IDs | [SA-021](#sa-021), [SA-083](#sa-083) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Contacts/Services/ContactService.php](app/Domains/Contacts/Services/ContactService.php)<br>[app/Http/Controllers/Admin/ContactController.php](app/Http/Controllers/Admin/ContactController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, RESOURCE-DATA |

### SA-023

**Explicit canonical Student merge preserving facts/provenance**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Student identity. Explicit canonical Student merge preserving facts/provenance. Two distinct valid canonical IDs; calendar/Student/Booking locks detect changed graph. |
| UI / routes | Student Records → Super Admin merge<br>`POST /admin/students/{student}/merge` — `admin.students.merge` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Both Student identities and current booking/calendar/finance/forms/teaching/planning/alert/task ownership graph. |
| Data created / changed | Only reviewed ownership references transfer to primary; secondary becomes merged/soft-deleted; verification challenges consumed, caches/audit updated. |
| Business rules | Two distinct valid canonical IDs; calendar/Student/Booking locks detect changed graph. Stable booking/purchase/material IDs, amounts, ledger quantities/timestamps and history remain; typed provenance is preserved. Recent entries to merged records are omitted, not copied with private pages. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Recent entries to merged records are omitted, not copied with private pages. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentMergeAndPrivacyTest.php](tests/Feature/StudentMergeAndPrivacyTest.php)<br>[tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php)<br>[tests/Feature/StaffOperationsProductivityTest.php](tests/Feature/StaffOperationsProductivityTest.php)<br>[tests/Feature/TypedEntitlementsTest.php](tests/Feature/TypedEntitlementsTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later purpose-built QA duplicate pair with small owned history merges; check canonical owner graph, old login denial and unchanged financial/provenance facts. |
| Dependent / related capability IDs | [SA-019](#sa-019), [SA-020](#sa-020), [SA-022](#sa-022), [SA-038](#sa-038) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Students/Services/StudentMergeService.php](app/Domains/Students/Services/StudentMergeService.php)<br>[app/Http/Controllers/Admin/StudentController.php](app/Http/Controllers/Admin/StudentController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, TEACHING-DATA |

### SA-024

**Student privacy anonymization and owned-content redaction**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Privacy. Student privacy anonymization and owned-content redaction. Retains structural Booking/financial history rather than reset/restart. |
| UI / routes | Student Records → Super Admin anonymize<br>`POST /admin/students/{student}/anonymize` — `admin.students.anonymize` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Current identity, shared/owned Contacts, bookings, forms/revisions, private materials, finance and teaching/planning/operational state. |
| Data created / changed | Anonymized soft-deleted identity, controlled owned PII/text redaction, shared-Contact isolation, withdrawn private materials and cleanup, paused/withdrawn/cancelled planning and safe audit. |
| Business rules | Retains structural Booking/financial history rather than reset/restart. Shared independent Contacts are protected; current graph changes refuse. Teaching responses/preparation/corrections/feedback/notes/forms are redacted, sharing revoked; Student notifications removed. This is not reversible identity editing. Structural Booking history/status is retained with sensitive notes redacted; anonymization itself neither cancels bookings nor restores credits nor changes cash amounts. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Shared independent Contacts are protected; current graph changes refuse. This is not reversible identity editing. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentMergeAndPrivacyTest.php](tests/Feature/StudentMergeAndPrivacyTest.php)<br>[tests/Feature/LessonWorkspaceTest.php](tests/Feature/LessonWorkspaceTest.php)<br>[tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** No for execution; only inspect the control and ownership design without submitting.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later explicitly disposable QA privacy subject and shared independent lead verify no PII/bytes remain reachable while money/history/other person survive; separate approval is required for destructive production execution. |
| Dependent / related capability IDs | [SA-023](#sa-023), [SA-038](#sa-038), [SA-052](#sa-052), [SA-083](#sa-083) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Students/Services/StudentPrivacyService.php](app/Domains/Students/Services/StudentPrivacyService.php)<br>[app/Http/Controllers/Admin/StudentController.php](app/Http/Controllers/Admin/StudentController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA, TEACHING-DATA, RESOURCE-DATA, FINANCIAL-DATA, DESTRUCTIVE-EXECUTION |

### SA-025

**Active/inactive/archive lifecycle with explicit history**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Student operations. Active/inactive/archive lifecycle with explicit history. Archive is not suspension, deletion, automatic lesson cancellation or financial erasure. |
| UI / routes | Student Records operational status; default roster/Today/task queues<br>`PATCH /admin/students/{student}/operational-status` — `admin.students.operational-status` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Canonical Student and current operational status/reason. |
| Data created / changed | active/inactive/archived state, changed-at UTC and reason/status audit. |
| Business rules | Archive is not suspension, deletion, automatic lesson cancellation or financial erasure. Default daily non-booking queues omit inactive records; booked commitments and explicit history/finance remain. New recurrence generation requires active status. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Archive is not suspension, deletion, automatic lesson cancellation or financial erasure. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php)<br>[tests/Feature/StaffOperationsProductivityTest.php](tests/Feature/StaffOperationsProductivityTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later archive QA Student with a confirmed commitment and purchase; default queues omit while calendar/explicit history/statement persist; restore active. |
| Dependent / related capability IDs | [SA-019](#sa-019), [SA-004](#sa-004), [SA-018](#sa-018), [SA-048](#sa-048) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/StudentOperationalStatusController.php](app/Http/Controllers/Admin/StudentOperationalStatusController.php)<br>[app/Domains/Students/Services/StudentSchedulingService.php](app/Domains/Students/Services/StudentSchedulingService.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA |

### SA-026

**Student selection, filtered transactions and financial exports**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Cashier. Student selection, filtered transactions and financial exports. Student selector scopes purchase management; displayed transaction totals keep currencies distinct and derive net cash from receipts/refunds. |
| UI / routes | Cashier Hub<br>`GET\|HEAD /admin/billing/cashier` — `admin.billing.cashier`<br>`GET\|HEAD /admin/billing/export` — `admin.billing.export` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Canonical Student/purchase/payment/refund/typed allocation summaries and offering presets. |
| Data created / changed | None for overview/export; context selection only. |
| Business rules | Student selector scopes purchase management; displayed transaction totals keep currencies distinct and derive net cash from receipts/refunds. Screen and export use the same filter/query authority, not stored editable balances. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | Student ID; date_from/date_to; transaction_type payment/refund/credit; offering_key presets or custom_unclassified; package_id; payment_method; package_status active/expired/completed/cancelled; format csv/xlsx. Courtesy-credit transaction rows select courtesy_adjustment, not every grant or consumption. |
| CSV / XLSX / other export | CSV/XLSX matching financial scope with formula safety/capacity limits. |
| Edges / negative cases | Screen and export use the same filter/query authority, not stored editable balances. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/CashierBillingReconciliationTest.php](tests/Feature/CashierBillingReconciliationTest.php)<br>[tests/Feature/FilterAwareExportsTest.php](tests/Feature/FilterAwareExportsTest.php)<br>[tests/Feature/AdminUiBillingPresentationTest.php](tests/Feature/AdminUiBillingPresentationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later small multi-purchase/multi-currency QA verifies filtered screen/export parity and untouched unrelated Student history. |
| Dependent / related capability IDs | [SA-027](#sa-027), [SA-029](#sa-029), [SA-030](#sa-030), [SA-034](#sa-034) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/StudentBillingController.php](app/Http/Controllers/Admin/StudentBillingController.php)<br>[app/Domains/Students/Services/CashierReportService.php](app/Domains/Students/Services/CashierReportService.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, FINANCIAL-DATA, ENTITLEMENT-DATA |

### SA-027

**Create canonical preset or classified custom purchases and grants**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Purchases/typed rights. Create canonical preset or classified custom purchases and grants. Original price minus discount equals final/net contract price; nonnegative decimal amounts and discount ≤price. |
| UI / routes | Cashier selected Student → Add package<br>`POST /admin/students/{student}/packages` — `admin.students.packages.store` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Canonical Student, active EntitlementType, preset catalog and eligible diagnostic credit. |
| Data created / changed | Immutable StudentPackage terms/fingerprint and typed allocation/grant ledger; no PaymentRecord just for creating purchase. |
| Business rules | Original price minus discount equals final/net contract price; nonnegative decimal amounts and discount ≤price. Presets: Diagnostic one_hour×1 $25/14 days; Foundation two_hour×8 $280/75; Fluency two_hour×12 $390/100; maintenance two_hour×1 $48/30; advanced one_hour×1 $28/30. Custom requires explicit active type; preset override notes preserve explicit staff override. |
| Configuration | StudentLedgerService::PRESETS and active entitlement definitions; preset validity initially waits for settlement unless explicitly overridden. |
| Timezone | Preset expiry begins from qualifying full net settlement business-date midnight plus validity_days; custom explicit expiry/unlimited has separate terms. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Follow the publication/eligibility and current-state rules above; validate the rejected or unavailable case in the acceptance scenario. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentLedgerTest.php](tests/Feature/StudentLedgerTest.php)<br>[tests/Feature/TypedEntitlementsTest.php](tests/Feature/TypedEntitlementsTest.php)<br>[tests/Feature/CashierBillingReconciliationTest.php](tests/Feature/CashierBillingReconciliationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later create preset/custom QA purchases, inspect one typed grant and immutable original/discount/final terms, pending-settlement availability and replay rejection. |
| Dependent / related capability IDs | [SA-020](#sa-020), [SA-028](#sa-028), [SA-029](#sa-029), [STU-005](#stu-005) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Students/Services/StudentLedgerService.php](app/Domains/Students/Services/StudentLedgerService.php)<br>[app/Domains/Students/Models/StudentPackage.php](app/Domains/Students/Models/StudentPackage.php)<br>[app/Http/Controllers/Admin/StudentBillingController.php](app/Http/Controllers/Admin/StudentBillingController.php) |
| QA needs | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION |

### SA-028

**Reviewed discount and 48-hour diagnostic-credit eligibility**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Discounts. Reviewed discount and 48-hour diagnostic-credit eligibility. Eligible diagnostic must have completed consumed lesson, genuine USD settlement/net paid ≥$25 and qualifying settlement within 48 hours. |
| UI / routes | Cashier diagnostic-credit check and package discount<br>`GET\|HEAD /admin/billing/check-diagnostic-credit/{student}` — `admin.billing.check_diagnostic`<br>`POST /admin/students/{student}/packages` — `admin.students.packages.store` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Diagnostic offering, completed consumption, actual net settlement/refunds and later core purchases. |
| Data created / changed | Discount recorded in new purchase; does not fabricate payment/refund or cash voucher. |
| Business rules | Eligible diagnostic must have completed consumed lesson, genuine USD settlement/net paid ≥$25 and qualifying settlement within 48 hours. Prior Foundation/Fluency $25 claim blocks reuse; recent refunds can invalidate/reopen settlement evaluation. Eligible credit/explicit discount is bounded by price. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | 48-hour comparison uses absolute UTC settlement instant; expiry still business-date based. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Prior Foundation/Fluency $25 claim blocks reuse; recent refunds can invalidate/reopen settlement evaluation. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/CashierBillingReconciliationTest.php](tests/Feature/CashierBillingReconciliationTest.php)<br>[tests/Feature/TypedEntitlementsTest.php](tests/Feature/TypedEntitlementsTest.php)<br>[tests/Feature/StudentLedgerTest.php](tests/Feature/StudentLedgerTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later completed/uncompleted, partially paid/refunded, within/outside 48h and already-claimed diagnostics prove exact single-use $25 behavior. |
| Dependent / related capability IDs | [SA-027](#sa-027), [SA-029](#sa-029), [SA-030](#sa-030), [SA-042](#sa-042) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Students/Services/StudentLedgerService.php](app/Domains/Students/Services/StudentLedgerService.php)<br>[app/Http/Controllers/Admin/StudentBillingController.php](app/Http/Controllers/Admin/StudentBillingController.php) |
| QA needs | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION |

### SA-029

**Record positive manual payments and settlement**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Actual cash. Record positive manual payments and settlement. Actual manually received money is separate from expected installments/purchase price. |
| UI / routes | Selected purchase → Record payment<br>`POST /admin/students/{student}/packages/{package}/payments` — `admin.students.payments.store` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Current Student/purchase, active configured PaymentMethod and immutable prior payments/refunds. |
| Data created / changed | Append-only positive PaymentRecord with purchase currency, method snapshot/ID, optional genuine reference/notes/actor and UTC paid_at; first settlement may set expiry. |
| Business rules | Actual manually received money is separate from expected installments/purchase price. Payment can overpay; due/overpaid are projected separately. Student/purchase locks/idempotency avoid duplicate receipt; controller replay returns 409 while underlying same-key service reuses existing same-purchase payment. No automatic online charge is made. |
| Configuration | Enabled PaymentMethods; exact cents/BCMath; immutable purchase currency. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | No automatic online charge is made. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentLedgerTest.php](tests/Feature/StudentLedgerTest.php)<br>[tests/Feature/CashierBillingReconciliationTest.php](tests/Feature/CashierBillingReconciliationTest.php)<br>[tests/Feature/BusinessLifecycleConcurrencyTest.php](tests/Feature/BusinessLifecycleConcurrencyTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later approved test receipt partial→settled→overpaid and retry/race; verify one receipt per key and expiry only on qualifying net settlement. |
| Dependent / related capability IDs | [SA-027](#sa-027), [SA-035](#sa-035), [SA-033](#sa-033), [SA-099](#sa-099) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Students/Services/StudentLedgerService.php](app/Domains/Students/Services/StudentLedgerService.php)<br>[app/Http/Controllers/Admin/StudentBillingController.php](app/Http/Controllers/Admin/StudentBillingController.php) |
| QA needs | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION |

### SA-030

**Record bounded refunds with optional explicit typed forfeiture**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Actual cash refunds. Record bounded refunds with optional explicit typed forfeiture. Positive refund cannot exceed original unrefunded payment or package refundable net paid. |
| UI / routes | Selected payment → Refund<br>`POST /admin/students/{student}/payments/{payment}/refunds` — `admin.students.refunds.store` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Original payment, package net-paid/refund history and chosen typed allocation if forfeiting. |
| Data created / changed | Append-only PaymentRefund; optional negative expiration_forfeit ledger on explicitly owned allocation. |
| Business rules | Positive refund cannot exceed original unrefunded payment or package refundable net paid. Refund alone changes money, not rights; optional forfeiture must name allocation and cannot exceed remaining quantity. Replay payload/amount/reason/type mismatch rejects; historic receipt/grant rows stay unchanged. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Positive refund cannot exceed original unrefunded payment or package refundable net paid. Refund alone changes money, not rights; optional forfeiture must name allocation and cannot exceed remaining quantity. Replay payload/amount/reason/type mismatch rejects; historic receipt/grant rows stay unchanged. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentLedgerTest.php](tests/Feature/StudentLedgerTest.php)<br>[tests/Feature/CashierBillingReconciliationTest.php](tests/Feature/CashierBillingReconciliationTest.php)<br>[tests/Feature/TypedEntitlementsTest.php](tests/Feature/TypedEntitlementsTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later partial/repeated/beyond-cap refund with and without forfeiture verifies net due/overpaid, original history and no over-forfeit/foreign allocation. |
| Dependent / related capability IDs | [SA-029](#sa-029), [SA-031](#sa-031), [SA-034](#sa-034), [SA-035](#sa-035) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Students/Services/StudentLedgerService.php](app/Domains/Students/Services/StudentLedgerService.php)<br>[app/Http/Controllers/Admin/StudentBillingController.php](app/Http/Controllers/Admin/StudentBillingController.php) |
| QA needs | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION |

### SA-031

**Explicit typed courtesy adjustments and historical balance projection**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Typed rights. Explicit typed courtesy adjustments and historical balance projection. Type and allocation are explicit; change cannot make remaining rights negative. |
| UI / routes | Student purchase → credit adjustment<br>`POST /admin/students/{student}/packages/{package}/credits` — `admin.students.credits.adjust` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Explicit package/Student-owned allocation and immutable ledger balance. |
| Data created / changed | Append-only courtesy_adjustment with nonzero signed units, reason/key/actor; no price/payment edits. |
| Business rules | Type and allocation are explicit; change cannot make remaining rights negative. No one_hour↔two_hour conversion or minutes-based inference; idempotent details/ownership/reason/type must match. Consumed/restored/forfeited and unavailable history remain separately projected. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Type and allocation are explicit; change cannot make remaining rights negative. No one_hour↔two_hour conversion or minutes-based inference; idempotent details/ownership/reason/type must match. Consumed/restored/forfeited and unavailable history remain separately projected. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/TypedEntitlementsTest.php](tests/Feature/TypedEntitlementsTest.php)<br>[tests/Feature/StudentLedgerTest.php](tests/Feature/StudentLedgerTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later add/subtract a named allocation, then reject zero/missing reason, below-zero, wrong allocation and key-payload drift; preserve money. |
| Dependent / related capability IDs | [SA-027](#sa-027), [SA-030](#sa-030), [STU-005](#stu-005) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Students/Services/StudentLedgerService.php](app/Domains/Students/Services/StudentLedgerService.php)<br>[app/Domains/Students/Services/EntitlementService.php](app/Domains/Students/Services/EntitlementService.php)<br>[app/Http/Controllers/Admin/StudentBillingController.php](app/Http/Controllers/Admin/StudentBillingController.php) |
| QA needs | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION |

### SA-032

**Extend an existing expiry with reason and stale-date protection**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Purchase validity. Extend an existing expiry with reason and stale-date protection. Requires existing expiry, matching previous date, strictly later new date and nonempty reason. |
| UI / routes | Student purchase → Extend validity<br>`POST /admin/students/{student}/packages/{package}/validity` — `admin.students.packages.validity` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Owned purchase current expiration_date and supplied previous date. |
| Data created / changed | Later expiration_date and safe before/after/reason audit; original grant/debit facts preserved. |
| Business rules | Requires existing expiry, matching previous date, strictly later new date and nonempty reason. Cannot silently change unlimited/pending-settlement null expiry or shorten validity through this action; concurrent/stale form rejects. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Expiration is a Business Timezone calendar date, inclusive through its end; audit instant UTC. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Requires existing expiry, matching previous date, strictly later new date and nonempty reason. Cannot silently change unlimited/pending-settlement null expiry or shorten validity through this action; concurrent/stale form rejects. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BusinessOperationsControlsTest.php](tests/Feature/BusinessOperationsControlsTest.php)<br>[tests/Feature/TypedEntitlementsTest.php](tests/Feature/TypedEntitlementsTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later expired QA purchase extends validly; reject stale/shorter/null-expiry request and verify ledger/money untouched. |
| Dependent / related capability IDs | [SA-027](#sa-027), [STU-005](#stu-005) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/StudentBillingController.php](app/Http/Controllers/Admin/StudentBillingController.php) |
| QA needs | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION |

### SA-033

**Immutable full-price installment schedule, FIFO projection and voided history**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Expected payments. Immutable full-price installment schedule, FIFO projection and voided history. Schedule total must equal final price. |
| UI / routes | Purchase lifecycle → installments<br>`GET\|HEAD /admin/packages/{package}/lifecycle` — `admin.packages.lifecycle`<br>`POST /admin/packages/{package}/installments` — `admin.packages.installments`<br>`POST /admin/packages/{package}/installments/void` — `admin.packages.installments.void` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Purchase final price, existing immutable schedule and canonical actual net-paid summary. |
| Data created / changed | 1–12 positive expected amount/due-date rows; status voided on cancellation, not actual payments. |
| Business rules | Schedule total must equal final price. Due-date/sequence projection allocates current net actual payments FIFO; refunds reopen due forecast, overpayment remains money fact. One immutable schedule only, including voided history; no replacement schedule or fabricated PaymentRecord. Key-owner/fingerprint drift refuses. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Due dates are business calendar dates; overdue before today, no timezone conversion to artificial payment time. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | One immutable schedule only, including voided history; no replacement schedule or fabricated PaymentRecord. Key-owner/fingerprint drift refuses. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php)<br>[tests/Feature/BusinessLifecycleConcurrencyTest.php](tests/Feature/BusinessLifecycleConcurrencyTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later three-installment QA schedule totals final price; actual partial/full/refund values project correctly; void retains rows and rejects replacement. |
| Dependent / related capability IDs | [SA-027](#sa-027), [SA-029](#sa-029), [SA-030](#sa-030), [SA-034](#sa-034) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Students/Services/InstallmentScheduleService.php](app/Domains/Students/Services/InstallmentScheduleService.php)<br>[app/Http/Controllers/Admin/PackageLifecycleController.php](app/Http/Controllers/Admin/PackageLifecycleController.php) |
| QA needs | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION |

### SA-034

**Current balances, overdue installments, partial payments and overpayments**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Receivables. Current balances, overdue installments, partial payments and overpayments. Default Due plus overdue/partial/overpaid/all scopes; due=max(final−net_paid,0), overpaid=max(net_paid−final,0). |
| UI / routes | Receivables & Statements<br>`GET\|HEAD /admin/billing/receivables` — `admin.receivables.index`<br>`GET\|HEAD /admin/billing/receivables/export` — `admin.receivables.export` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Canonical price/payments/refunds/typed ledger and installment projection. |
| Data created / changed | None apart from generated tabular export. |
| Business rules | Default Due plus overdue/partial/overpaid/all scopes; due=max(final−net_paid,0), overpaid=max(net_paid−final,0). Never pool currencies or infer a receipt from expected schedule. Preserved inactive/history purchases remain explicitly reviewable; bounded 50-row screen and lazy export readers. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | student_id, currency, state all/due/overdue/partial/overpaid, after_id and CSV/XLSX format. No free-text search filter. |
| CSV / XLSX / other export | CSV/XLSX same canonical rows/filters; exact decimals and formula safety. |
| Edges / negative cases | Never pool currencies or infer a receipt from expected schedule. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php)<br>[tests/Feature/FilterAwareExportsTest.php](tests/Feature/FilterAwareExportsTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later partial/refunded/overpaid/multi-currency QA compares default Due and each filter against actual statement and workbook rows. |
| Dependent / related capability IDs | [SA-033](#sa-033), [SA-029](#sa-029), [SA-030](#sa-030), [SA-035](#sa-035) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Students/Services/ReceivablesReadModel.php](app/Domains/Students/Services/ReceivablesReadModel.php)<br>[app/Http/Controllers/Admin/ReceivablesController.php](app/Http/Controllers/Admin/ReceivablesController.php) |
| QA needs | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION |

### SA-035

**Staff package statements, actual receipts, print and exports**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Financial documents. Staff package statements, actual receipts, print and exports. Contract price/discount/final amount, net paid/due/overpaid, actual payment/refund and expected installments remain distinct. |
| UI / routes | Purchase statement/receipt links from Student/Cashier/receivables<br>`GET\|HEAD /admin/packages/{package}/statement` — `admin.statements.show`<br>`GET\|HEAD /admin/packages/{package}/statement/export` — `admin.statements.export`<br>`GET\|HEAD /admin/packages/{package}/receipts/{payment}` — `admin.receipts.show` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Purchase contract, actual receipts/refunds, recorded method/reference snapshots, installment forecast and typed ledger. |
| Data created / changed | None except generated private export. |
| Business rules | Contract price/discount/final amount, net paid/due/overpaid, actual payment/refund and expected installments remain distinct. Internal IDs are not invented provider references; later method rename does not alter old receipt. Binary exports and views are private/no-store. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | Print and CSV/XLSX actual statement rows; shared XLSX/formula safety. |
| Edges / negative cases | Internal IDs are not invented provider references; later method rename does not alter old receipt. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php)<br>[tests/Feature/FilterAwareExportsTest.php](tests/Feature/FilterAwareExportsTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later staff/Student own statement and actual manual receipt agree; rename method, compare immutable receipt, then foreign/mismatched Student receipt denies. |
| Dependent / related capability IDs | [SA-029](#sa-029), [SA-030](#sa-030), [SA-033](#sa-033), [STU-024](#stu-024), [STU-025](#stu-025), [SA-099](#sa-099) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/FinancialStatementController.php](app/Http/Controllers/Admin/FinancialStatementController.php)<br>[app/Domains/Students/Services/FinancialStatementService.php](app/Domains/Students/Services/FinancialStatementService.php) |
| QA needs | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION |

### SA-036

**Create a new linked purchase/grant without rewriting previous history**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Renewals. Create a new linked purchase/grant without rewriting previous history. Catalog renewals use current purchase-service rules; classified single-allocation custom repeats old terms/type with optional expiry. |
| UI / routes | Purchase lifecycle → Renew<br>`GET\|HEAD /admin/packages/{package}/lifecycle` — `admin.packages.lifecycle`<br>`POST /admin/packages/{package}/renew` — `admin.packages.renew` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Existing classified source purchase/typed allocation and current preset/explicit renewal terms. |
| Data created / changed | New purchase/grant and immutable PackageRenewal old/new/Student/date/reason/key/fingerprint link. |
| Business rules | Catalog renewals use current purchase-service rules; classified single-allocation custom repeats old terms/type with optional expiry. Legacy/unclassified or multi-allocation custom requires explicit classified preset. Old price/ledger/payments/expiry remain unchanged; changed/foreign key replay refuses. No money receipt just for renewal. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Old price/ledger/payments/expiry remain unchanged; changed/foreign key replay refuses. No money receipt just for renewal. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php)<br>[tests/Feature/BusinessLifecycleConcurrencyTest.php](tests/Feature/BusinessLifecycleConcurrencyTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later renew catalog and classified custom purchases; verify unchanged source and one new grant/link on replay, with no phantom payment. |
| Dependent / related capability IDs | [SA-027](#sa-027), [SA-028](#sa-028), [SA-038](#sa-038), [SA-033](#sa-033) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Students/Services/PackageRenewalService.php](app/Domains/Students/Services/PackageRenewalService.php)<br>[app/Http/Controllers/Admin/PackageLifecycleController.php](app/Http/Controllers/Admin/PackageLifecycleController.php) |
| QA needs | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION |

### SA-037

**Descriptive cash, dues, typed usage, expiry and renewal/repeat indicators**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Business reporting. Descriptive cash, dues, typed usage, expiry and renewal/repeat indicators. Receipts/refunds count on actual separate dates; refund-only period can have negative net cash. |
| UI / routes | Business Lifecycle report<br>`GET\|HEAD /admin/reports/business-lifecycle` — `admin.reports.lifecycle`<br>`GET\|HEAD /admin/reports/business-lifecycle/export` — `admin.reports.lifecycle.export` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Actual dated receipts/refunds, current purchase/typed summaries, completed attendance and immutable renewal facts. |
| Data created / changed | None apart from generated export. |
| Business rules | Receipts/refunds count on actual separate dates; refund-only period can have negative net cash. Currencies remain separate. Balances/usage/expiry are labeled current values; renewal share and repeat-lesson share are observed ratios, not forecasts, retention probability or accrual revenue. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Business-calendar dates converted to half-open UTC start/end; no reconstructed historical balance claim. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | Business date range and currency/Student scope supported by BusinessLifecycleReport; matching screen/export. |
| CSV / XLSX / other export | CSV/XLSX with descriptive indicators and genuine cash facts. |
| Edges / negative cases | Balances/usage/expiry are labeled current values; renewal share and repeat-lesson share are observed ratios, not forecasts, retention probability or accrual revenue. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php)<br>[tests/Feature/BusinessReportingReconciliationTest.php](tests/Feature/BusinessReportingReconciliationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later refund-only and multi-currency periods, one repeat learner and one renewal validate denominators/current-value labels and workbook parity. |
| Dependent / related capability IDs | [SA-029](#sa-029), [SA-030](#sa-030), [SA-036](#sa-036), [SA-042](#sa-042) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Reporting/Services/BusinessLifecycleReport.php](app/Domains/Reporting/Services/BusinessLifecycleReport.php)<br>[app/Http/Controllers/Admin/BusinessLifecycleReportController.php](app/Http/Controllers/Admin/BusinessLifecycleReportController.php) |
| QA needs | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION, BOOKING-DATA |

### SA-038

**Read-only billing integrity and typed provenance diagnostics**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Reconciliation. Read-only billing integrity and typed provenance diagnostics. Reports grant/negative/ownership/payment/refund/provenance inconsistencies against authoritative summary, retaining underlying facts. |
| UI / routes | Billing Reconcile<br>`GET\|HEAD /admin/billing/reconcile` — `admin.billing.reconcile`<br>`GET\|HEAD /admin/billing/reconcile/export` — `admin.billing.reconcile.export` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Purchase prices/payments/refunds, Student ownership, ledger/allocations and Booking funding/debit/restoration snapshots. |
| Data created / changed | None apart from generated export; no automatic repair. |
| Business rules | Reports grant/negative/ownership/payment/refund/provenance inconsistencies against authoritative summary, retaining underlying facts. Empty production is honestly No Transactions/UNINITIALIZED rather than proof of populated balanced finances. Classification/repair requires separately reviewed action; no guessed type conversion. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | q, Student and discrepancy category; same filter-aware CSV/XLSX. |
| CSV / XLSX / other export | Filtered CSV/XLSX discrepancy report. |
| Edges / negative cases | Empty production is honestly No Transactions/UNINITIALIZED rather than proof of populated balanced finances. Classification/repair requires separately reviewed action; no guessed type conversion. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/CashierBillingReconciliationTest.php](tests/Feature/CashierBillingReconciliationTest.php)<br>[tests/Feature/TypedEntitlementsTest.php](tests/Feature/TypedEntitlementsTest.php)<br>[tests/Feature/BusinessReportingReconciliationTest.php](tests/Feature/BusinessReportingReconciliationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later valid QA finance yields zero issues; isolated deliberately inconsistent legacy cases are detected read-only without weakening production FKs/triggers. |
| Dependent / related capability IDs | [SA-027](#sa-027), [SA-029](#sa-029), [SA-030](#sa-030), [SA-031](#sa-031), [SA-098](#sa-098) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Students/Services/BillingReconciliationService.php](app/Domains/Students/Services/BillingReconciliationService.php)<br>[app/Http/Controllers/Admin/StudentBillingController.php](app/Http/Controllers/Admin/StudentBillingController.php) |
| QA needs | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION, BOOKING-DATA |

### SA-039

**Business-time booking list, day/week/month calendar, detail and notes**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Bookings/calendar. Business-time booking list, day/week/month calendar, detail and notes. Confirmed/completed/cancelled/no-show stay distinct; list and calendar partitions have deliberate status scopes, and inactive Students do not erase commitments. |
| UI / routes | Bookings & Calendar; Booking detail<br>`GET\|HEAD /admin/bookings` — `admin.bookings.index`<br>`GET\|HEAD /admin/bookings/{booking}` — `admin.bookings.show`<br>`PATCH /admin/bookings/{booking}/notes` — `admin.bookings.notes` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Bookings/Contacts/SessionTypes/events and original business/customer/funding snapshots. |
| Data created / changed | Authorized booking notes/audit only; listing/calendar are read-only. |
| Business rules | Confirmed/completed/cancelled/no-show stay distinct; list and calendar partitions have deliberate status scopes, and inactive Students do not erase commitments. Detail exposes original history and current authorized actions; no hidden payment gateway or repeated credit debit. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | view=list/day/week/calendar, status, date scope, search, selected day/month; actual calendar groups business dates. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Confirmed/completed/cancelled/no-show stay distinct; list and calendar partitions have deliberate status scopes, and inactive Students do not erase commitments. Detail exposes original history and current authorized actions; no hidden payment gateway or repeated credit debit. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/AdminFunctionsAndCalendarTest.php](tests/Feature/AdminFunctionsAndCalendarTest.php)<br>[tests/Feature/AdminOperationsTest.php](tests/Feature/AdminOperationsTest.php)<br>[tests/Feature/AdminUiBillingPresentationTest.php](tests/Feature/AdminUiBillingPresentationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later QA date-spanning status mix verifies list/day/week/month and preserved snapshots/notes; reject foreign lower-role writes. |
| Dependent / related capability IDs | [SA-040](#sa-040), [SA-041](#sa-041), [SA-042](#sa-042), [SA-043](#sa-043), [SA-051](#sa-051) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/BookingController.php](app/Http/Controllers/Admin/BookingController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, BOOKING-DATA |

### SA-040

**Manual direct booking with optional canonical Student preselection**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Booking creation. Manual direct booking with optional canonical Student preselection. Admin manual path bypasses public hold requirement only, not slot availability/notice/buffer/date/calendar locks. |
| UI / routes | Bookings → Create; Student contextual Book session<br>`GET\|HEAD /admin/bookings/create` — `admin.bookings.create`<br>`POST /admin/bookings` — `admin.bookings.store` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Active SessionType, authoritative signed slot, existing optional Student/contact/identity and availability. |
| Data created / changed | Confirmed direct Booking, immutable time/policy/contact snapshots, event/meeting assignment and notification. |
| Business rules | Admin manual path bypasses public hold requirement only, not slot availability/notice/buffer/date/calendar locks. Optional Student ID is rechecked; selected Student prefills existing contact/timezone. Direct booking does not spend/create typed rights or invent cash payment. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Admin manual path bypasses public hold requirement only, not slot availability/notice/buffer/date/calendar locks. Direct booking does not spend/create typed rights or invent cash payment. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BookingEngineTest.php](tests/Feature/BookingEngineTest.php)<br>[tests/Feature/StaffOperationsProductivityTest.php](tests/Feature/StaffOperationsProductivityTest.php)<br>[tests/Feature/CanonicalAvailabilityValidationTest.php](tests/Feature/CanonicalAvailabilityValidationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later approved direct/free QA slot with canonical Student prefill confirms once; stale/arbitrary/conflicting slot and foreign identity drift refuse. |
| Dependent / related capability IDs | [SA-039](#sa-039), [SA-044](#sa-044), [SA-045](#sa-045), [SA-020](#sa-020), [SA-051](#sa-051) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/BookingController.php](app/Http/Controllers/Admin/BookingController.php)<br>[app/Domains/Booking/Services/BookingService.php](app/Domains/Booking/Services/BookingService.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA |

### SA-041

**Staff reschedule with canonical slot and immutable typed funding**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Rescheduling. Staff reschedule with canonical slot and immutable typed funding. Same availability/calendar/Student/Booking locks, notice/DST/holiday boundaries and idempotency govern moves. |
| UI / routes | Booking detail → Reschedule<br>`POST /admin/bookings/{booking}/reschedule` — `admin.bookings.reschedule` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Current Booking/status/availability, selected SessionType and original typed provenance. |
| Data created / changed | New UTC times/customer snapshot and SessionReschedule/BookingEvent/reconfirmation/meeting revalidation; no fresh debit. |
| Business rules | Same availability/calendar/Student/Booking locks, notice/DST/holiday boundaries and idempotency govern moves. A type/required-unit change incompatible with existing debit is refused rather than silently converting rights. Historical old/new times retained. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | A type/required-unit change incompatible with existing debit is refused rather than silently converting rights. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentReschedulingTest.php](tests/Feature/StudentReschedulingTest.php)<br>[tests/Feature/TypedEntitlementsTest.php](tests/Feature/TypedEntitlementsTest.php)<br>[tests/Feature/MariaDbConcurrencyVerificationTest.php](tests/Feature/MariaDbConcurrencyVerificationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later move direct and package-funded QA lessons; compare original consumed entry/allocation/units, replay and incompatible type/conflicting slot refusals. |
| Dependent / related capability IDs | [SA-039](#sa-039), [SA-044](#sa-044), [SA-045](#sa-045), [SA-050](#sa-050), [STU-010](#stu-010) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/BookingController.php](app/Http/Controllers/Admin/BookingController.php)<br>[app/Domains/Booking/Services/RescheduleService.php](app/Domains/Booking/Services/RescheduleService.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, BOOKING-DATA, ENTITLEMENT-DATA |

### SA-042

**Mark completed or no-show without fabricating payment**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Lesson outcome. Mark completed or no-show without fabricating payment. Cannot complete cancelled/completed/no-show records through completion action. |
| UI / routes | Booking detail status actions<br>`POST /admin/bookings/{booking}/complete` — `admin.bookings.complete`<br>`POST /admin/bookings/{booking}/no-show` — `admin.bookings.no-show` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Current Booking status and immutable policy/debit if no-show consequence applies. |
| Data created / changed | Completed/no_show status/time, BookingEvent/audit and no-show policy decision/exact restore if configured. |
| Business rules | Cannot complete cancelled/completed/no-show records through completion action. No-show is a terminal policy transition under locks; restore/retain follows snapshot, retains original debit when applicable and does not create money refund. Competing cancel/no-show produce one consequence. Completion has status guards but no elapsed-start/end requirement; a future confirmed lesson can currently be marked complete. No-show requires confirmed status but also has no elapsed-time guard. This is current behavior, not a certified attendance timestamp. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Cannot complete cancelled/completed/no-show records through completion action. No-show is a terminal policy transition under locks; restore/retain follows snapshot, retains original debit when applicable and does not create money refund. Completion has status guards but no elapsed-start/end requirement; a future confirmed lesson can currently be marked complete. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BookingLifecycleAndPolicyCutoffTest.php](tests/Feature/BookingLifecycleAndPolicyCutoffTest.php)<br>[tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php)<br>[tests/Feature/BusinessLifecycleConcurrencyTest.php](tests/Feature/BusinessLifecycleConcurrencyTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later disposable future/ended/completed/cancelled QA statuses verify allowed transitions and policy restore/retain; cancellation/no-show race asserts one terminal outcome. |
| Dependent / related capability IDs | [SA-039](#sa-039), [SA-043](#sa-043), [SA-047](#sa-047), [SA-038](#sa-038) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/BookingController.php](app/Http/Controllers/Admin/BookingController.php)<br>[app/Domains/Booking/Services/NoShowService.php](app/Domains/Booking/Services/NoShowService.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, BOOKING-DATA, ENTITLEMENT-DATA |

### SA-043

**Staff cancellation and separate immutable credit consequence**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Cancellation policy. Staff cancellation and separate immutable credit consequence. Staff restore/retain differs from customer cutoff; retain is not another charge. |
| UI / routes | Booking detail Cancel; policy decision history<br>`POST /admin/bookings/{booking}/cancel` — `admin.bookings.cancel` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Current confirmed Booking, snapshot/legacy policy, original typed debit and allowed reason catalog. |
| Data created / changed | Cancellation status/events/reason and BookingPolicyDecision; exact typed restoration if policy restore. |
| Business rules | Staff restore/retain differs from customer cutoff; retain is not another charge. Restores original allocation/quantity once, including typed provenance; no automatic cash refund. Shared reason code plus optional notes preserve historical text. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Staff restore/retain differs from customer cutoff; retain is not another charge. Restores original allocation/quantity once, including typed provenance; no automatic cash refund. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BookingLifecycleAndPolicyCutoffTest.php](tests/Feature/BookingLifecycleAndPolicyCutoffTest.php)<br>[tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php)<br>[tests/Feature/TypedEntitlementsTest.php](tests/Feature/TypedEntitlementsTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later cancel direct/package QA under snapshot restore/retain, then replay and competing no-show; money remains unchanged. |
| Dependent / related capability IDs | [SA-047](#sa-047), [SA-031](#sa-031), [SA-042](#sa-042), [PUB-013](#pub-013) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Booking/Services/CancellationService.php](app/Domains/Booking/Services/CancellationService.php)<br>[app/Domains/Booking/Services/BookingPolicyService.php](app/Domains/Booking/Services/BookingPolicyService.php)<br>[app/Http/Controllers/Admin/BookingController.php](app/Http/Controllers/Admin/BookingController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, BOOKING-DATA, ENTITLEMENT-DATA |

### SA-044

**SessionType duration/price/funding and exact entitlement requirement**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Lesson configuration. SessionType duration/price/funding and exact entitlement requirement. Funding modes package/direct/free/legacy are finite; package requires active type+units and nonpackage clears them. |
| UI / routes | Bookings → Session Types<br>`GET\|HEAD /admin/session-types` — `admin.session-types.index`<br>`POST /admin/session-types/{sessionType?}` — `admin.session-types.save` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Existing SessionTypes and active EntitlementTypes. |
| Data created / changed | Validated SessionType title/slug/15–240-minute duration/decimal price/currency/active/funding and explicit package type/1–100 units. |
| Business rules | Funding modes package/direct/free/legacy are finite; package requires active type+units and nonpackage clears them. Existing Booking entitlement snapshots are unchanged. Availability override duration can differ from nominal duration; rights are not inferred from minutes or price. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Availability override duration can differ from nominal duration; rights are not inferred from minutes or price. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/TypedEntitlementsTest.php](tests/Feature/TypedEntitlementsTest.php)<br>[tests/Feature/DurationOverrideBookingTest.php](tests/Feature/DurationOverrideBookingTest.php)<br>[tests/Feature/BusinessOperationsControlsTest.php](tests/Feature/BusinessOperationsControlsTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later dedicated QA SessionType changes preserve existing booked snapshot; invalid package requirement or inactive type denies new spend. |
| Dependent / related capability IDs | [SA-027](#sa-027), [STU-009](#stu-009), [SA-045](#sa-045), [SA-041](#sa-041) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/SessionTypeController.php](app/Http/Controllers/Admin/SessionTypeController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, CONFIGURATION-TOGGLE, ENTITLEMENT-DATA |

### SA-045

**Weekly tutor availability, duration/buffer/notice/horizon overrides**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Availability. Weekly tutor availability, duration/buffer/notice/horizon overrides. Weekday start/end and rule duration/buffer/notice/horizon affect authoritative future slot generation. |
| UI / routes | Tutor Availability → weekly rules<br>`GET\|HEAD /admin/availability` — `admin.availability.index`<br>`POST /admin/availability/rules` — `admin.availability.rules.store`<br>`POST /admin/availability/rules/{rule}/toggle` — `admin.availability.toggle`<br>`DELETE /admin/availability/rules/{rule}` — `admin.availability.destroy` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Enabled AvailabilityRules, Business Timezone, SessionType and current calendar. |
| Data created / changed | Weekly rule configuration, active toggles/deletion and audit where defined; no existing Booking rewrite. |
| Business rules | Weekday start/end and rule duration/buffer/notice/horizon affect authoritative future slot generation. Validation rejects invalid windows; existing booked history is retained. Changes do not blanket-flush all application cache. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Weekly wall-clock rules are business-zone dates/times; generated canonical slots UTC with DST resolution. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Validation rejects invalid windows; existing booked history is retained. Changes do not blanket-flush all application cache. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/AvailabilityTest.php](tests/Feature/AvailabilityTest.php)<br>[tests/Feature/CanonicalAvailabilityValidationTest.php](tests/Feature/CanonicalAvailabilityValidationTest.php)<br>[tests/Feature/DurationOverrideBookingTest.php](tests/Feature/DurationOverrideBookingTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later separately approved narrow QA window checks same slots across customer zones, override duration and notice/horizon/buffer; preserve owner availability settings. |
| Dependent / related capability IDs | [PUB-008](#pub-008), [SA-044](#sa-044), [SA-046](#sa-046) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/AvailabilityController.php](app/Http/Controllers/Admin/AvailabilityController.php)<br>[app/Domains/Availability/Services/AvailabilityService.php](app/Domains/Availability/Services/AvailabilityService.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, CONFIGURATION-TOGGLE, BOOKING-DATA |

### SA-046

**Date-specific tutor exceptions/blocked days with booked-history warning**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Availability exceptions. Date-specific tutor exceptions/blocked days with booked-history warning. One exception per date, validated blocked/custom window and existing-calendar lock protect scheduling. |
| UI / routes | Tutor Availability → exceptions<br>`POST /admin/availability/exceptions` — `admin.availability.exceptions.store`<br>`DELETE /admin/availability/exceptions/{exception}` — `admin.availability.exception.destroy` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Unique business-date exception and existing confirmed affected bookings. |
| Data created / changed | AvailabilityException and date lock-protected mutation/removal. |
| Business rules | One exception per date, validated blocked/custom window and existing-calendar lock protect scheduling. Current confirmed commitments trigger warning rather than silent cancellation/rewrite. Conflicts/DST are checked through existing availability path. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Exception date/window is business-local, resolved to canonical UTC slots. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | 25-item exception pagination; selected business date. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Follow the publication/eligibility and current-state rules above; validate the rejected or unavailable case in the acceptance scenario. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php)<br>[tests/Feature/AvailabilityTest.php](tests/Feature/AvailabilityTest.php)<br>[tests/Feature/BookingPolicyMigrationTest.php](tests/Feature/BookingPolicyMigrationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later narrow approved QA date tests duplicate refusal, existing commitment warning and blocked/custom window without changing booked history. |
| Dependent / related capability IDs | [SA-045](#sa-045), [SA-039](#sa-039), [SA-048](#sa-048) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/AvailabilityController.php](app/Http/Controllers/Admin/AvailabilityController.php)<br>[app/Domains/Availability/Services/AvailabilityService.php](app/Domains/Availability/Services/AvailabilityService.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, CONFIGURATION-TOGGLE, BOOKING-DATA |

### SA-047

**Cancellation/no-show policy and immutable new-booking snapshots**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Policy configuration. Cancellation/no-show policy and immutable new-booking snapshots. Cutoff 0–168 hours; late customer deny/restore/retain, staff/no-show restore/retain. |
| UI / routes | Booking Policy<br>`GET\|HEAD /admin/booking-policy` — `admin.booking-policy.index`<br>`POST /admin/booking-policy` — `admin.booking-policy.store` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | booking_lifecycle_policy and legacy cancellation cutoff. |
| Data created / changed | Validated policy version 1 settings and safe atomic audit; new bookings snapshot it. |
| Business rules | Cutoff 0–168 hours; late customer deny/restore/retain, staff/no-show restore/retain. Existing snapshot does not change with settings; null historical snapshot remains version-0 legacy semantics. Invalid stored policy is flagged by Data Quality and new bookings use documented defaults until corrected. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Cutoff is elapsed hours versus UTC start, not a local date/DST guess. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Existing snapshot does not change with settings; null historical snapshot remains version-0 legacy semantics. Invalid stored policy is flagged by Data Quality and new bookings use documented defaults until corrected. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php)<br>[tests/Feature/BookingPolicyMigrationTest.php](tests/Feature/BookingPolicyMigrationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later separately approved configuration window creates two disposable bookings under different snapshots; old policy persists; invalid setting and legacy behavior verified. |
| Dependent / related capability IDs | [SA-043](#sa-043), [SA-042](#sa-042), [SA-083](#sa-083) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Booking/Services/BookingPolicyService.php](app/Domains/Booking/Services/BookingPolicyService.php)<br>[app/Http/Controllers/Admin/BookingPolicyController.php](app/Http/Controllers/Admin/BookingPolicyController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, CONFIGURATION-TOGGLE, BOOKING-DATA, ENTITLEMENT-DATA |

### SA-048

**Explicit recurring plans, bounded manual generation and safe blocked reasons**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Recurring lessons. Explicit recurring plans, bounded manual generation and safe blocked reasons. At most 104 occurrences; generation explicitly requests 1–12 (default 4). |
| UI / routes | Recurring Lessons; Student scheduling create-plan<br>`GET\|HEAD /admin/recurring-lessons` — `admin.recurring.index`<br>`POST /admin/students/{student}/recurring-lessons` — `admin.recurring.store`<br>`POST /admin/recurring-lessons/{plan}/generate` — `admin.recurring.generate`<br>`PATCH /admin/recurring-lessons/{plan}` — `admin.recurring.update` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Verified active Student, active SessionType/current availability/holiday/compatible rights and immutable plan/occurrence identities. |
| Data created / changed | Weekly/fortnightly plan, active/paused/completed state and numbered booked/blocked occurrences; actual bookings use StudentBookingService. |
| Business rules | At most 104 occurrences; generation explicitly requests 1–12 (default 4). Already booked sequence reuses Booking; blocked sequences can be reconsidered under current checks. Pause/complete does not cancel existing lessons. Safe reason categories hide other Students; no automatic scheduled generator. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Plan local date/time/IANA timezone and fold first/second/reject; canonical UTC per occurrence, DST gaps/folds rechecked. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | Recurring index: 20-row pagination, no search/status filter. Student scheduling context: plans_page (10), latest 100 occurrences, holidays_page (15). Manual generate takes from_sequence 1–104 and count 1–12; update takes active/paused/completed. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Pause/complete does not cancel existing lessons. Safe reason categories hide other Students; no automatic scheduled generator. |
| Background / manual behavior | **Background:** No recurring-generation scheduler/worker in deployed routes/console.php.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php)<br>[tests/Feature/BusinessLifecycleConcurrencyTest.php](tests/Feature/BusinessLifecycleConcurrencyTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later one active two-occurrence QA plan covers successful/replayed/blocked last-credit/holiday/DST generation; preserve old lesson when paused/completed. |
| Dependent / related capability IDs | [STU-009](#stu-009), [SA-044](#sa-044), [SA-045](#sa-045), [SA-046](#sa-046), [SA-050](#sa-050), [SA-025](#sa-025) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Booking/Services/RecurringLessonService.php](app/Domains/Booking/Services/RecurringLessonService.php)<br>[app/Http/Controllers/Admin/RecurringLessonController.php](app/Http/Controllers/Admin/RecurringLessonController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA |

### SA-049

**Review/contact/close/withdraw lesson interest manually**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Waitlist operations. Review/contact/close/withdraw lesson interest manually. Default daily queues exclude inactive/archived Students; explicit history remains. |
| UI / routes | Lesson Waitlist<br>`GET\|HEAD /admin/waitlist` — `admin.waitlist.index`<br>`PATCH /admin/waitlist/{interest}` — `admin.waitlist.update` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Canonical Student/SessionType/range/timezone interest and current status. |
| Data created / changed | Operational open/contacted/closed/withdrawn status/history; no Booking/payment/credit grant. |
| Business rules | Default daily queues exclude inactive/archived Students; explicit history remains. Follow-up is manual unless separately using existing communication tools; changing status alone sends no promise of automatic matching or outreach. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | Status all/open/contacted/closed/withdrawn only, default open; 25-row page ordered by date_from then ID. Open/contacted restrict operational_status active. No Student/type/date filter is exposed on this index. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Follow-up is manual unless separately using existing communication tools; changing status alone sends no promise of automatic matching or outreach. |
| Background / manual behavior | **Background:** No automatic waitlist-matching/background outreach feature.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later Student QA interest moves open→contacted→closed/withdrawn; compare own planning history and unchanged entitlements/booking count. |
| Dependent / related capability IDs | [STU-027](#stu-027), [SA-025](#sa-025), [SA-048](#sa-048) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/StudentSchedulingController.php](app/Http/Controllers/Admin/StudentSchedulingController.php)<br>[app/Domains/Students/Services/StudentSchedulingService.php](app/Domains/Students/Services/StudentSchedulingService.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA |

### SA-050

**Staff manage Student unavailability separately from tutor calendar**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Student planning. Staff manage Student unavailability separately from tutor calendar. Staff shares the same Student-first validation/overlap rules as portal. |
| UI / routes | Student Records → Scheduling/holidays<br>`GET\|HEAD /admin/students/{student}/scheduling` — `admin.students.scheduling`<br>`POST /admin/students/{student}/holidays` — `admin.students.holidays.store`<br>`POST /admin/students/{student}/holidays/{holiday}/cancel` — `admin.students.holidays.cancel` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Canonical Student holiday intervals/status and scheduling reasons. |
| Data created / changed | StudentUnavailability local-range/UTC interval/request fingerprint or cancelled history. |
| Business rules | Staff shares the same Student-first validation/overlap rules as portal. Holidays block linked future booking/reschedule/recurrence writes, not existing commitment cancellation; tutor weekly exceptions remain independent. Contact-only legacy bookings cannot infer a Student link. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Inclusive chosen-zone dates → exclusive next-day UTC end; skipped midnight and length invalidation. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Holidays block linked future booking/reschedule/recurrence writes, not existing commitment cancellation; tutor weekly exceptions remain independent. Contact-only legacy bookings cannot infer a Student link. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later record/cancel narrow QA holiday; verify exact overlaps and unaffected other Student/tutor availability and existing commitments. |
| Dependent / related capability IDs | [STU-026](#stu-026), [SA-048](#sa-048), [SA-041](#sa-041), [SA-046](#sa-046) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/StudentSchedulingController.php](app/Http/Controllers/Admin/StudentSchedulingController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA |

### SA-051

**Provider/room pools, preference, explicit assignment and reconciliation**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Meeting access. Provider/room pools, preference, explicit assignment and reconciliation. Keep enabled default provider; unique HTTPS room URL/hash. |
| UI / routes | Meeting Links; Booking meeting-room; Student provider preference<br>`GET\|HEAD /admin/meeting-links` — `admin.meeting-links.index`<br>`POST /admin/meeting-links/providers` — `admin.meeting-links.providers`<br>`POST /admin/meeting-links/rooms` — `admin.meeting-links.rooms`<br>`POST /admin/meeting-links/settings` — `admin.meeting-links.settings`<br>`POST /admin/bookings/{booking}/meeting-room` — `admin.meeting-links.assign`<br>`POST /admin/meeting-links/reconcile` — `admin.meeting-links.reconcile`<br>`POST /admin/students/{student}/meeting-preference` — `admin.students.meeting-preference` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Enabled MeetingProviders/Rooms, Student preferred provider and eligible upcoming bookings/history. |
| Data created / changed | Provider/room configuration, future preference, safe immutable Booking URL/provider snapshots and explicit reassign/reconcile audit. |
| Business rules | Keep enabled default provider; unique HTTPS room URL/hash. Assignment excludes disabled/overlapping/consecutive-room reuse and rotates by prior use; unavailable pool marks need rather than leaking early link. Existing assignment URL snapshot survives room editing; reschedule revalidates/reassigns safely. |
| Configuration | meeting.student_reveal_minutes 0–1440 (default 15), enabled rooms/default provider and Student preference. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | Upcoming confirmed not-ended assignments (bounded 100 in UI); room/provider pool selection. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Assignment excludes disabled/overlapping/consecutive-room reuse and rotates by prior use; unavailable pool marks need rather than leaking early link. |
| Background / manual behavior | **Background:** Assignment called during normal booking/reschedule and manual Reconcile; no new assignment scheduler.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/MeetingRotationTest.php](tests/Feature/MeetingRotationTest.php)<br>[tests/Feature/MariaDbConcurrencyVerificationTest.php](tests/Feature/MariaDbConcurrencyVerificationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later dummy safe HTTPS rooms/adjacent QA lessons test rotation/collision/preference/reveal/snapshot immutability; no owner meeting URL change. |
| Dependent / related capability IDs | [SA-039](#sa-039), [SA-041](#sa-041), [STU-028](#stu-028) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/MeetingLinkController.php](app/Http/Controllers/Admin/MeetingLinkController.php)<br>[app/Domains/Booking/Services/MeetingLinkService.php](app/Domains/Booking/Services/MeetingLinkService.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, BOOKING-DATA, CONFIGURATION-TOGGLE |

### SA-052

**Staff manage private PDF, Resource, recording/link materials and sharing**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Lesson workspace. Staff manage private PDF, Resource, recording/link materials and sharing. Admin/Super Admin policy permits teaching; Assistant is denied. |
| UI / routes | Booking → Lesson Workspace<br>`GET\|HEAD /admin/bookings/{booking}/lesson` — `admin.lessons.show`<br>`POST /admin/bookings/{booking}/lesson/materials` — `admin.lessons.materials.store`<br>`PATCH /admin/bookings/{booking}/lesson/materials/{material}` — `admin.lessons.materials.update`<br>`DELETE /admin/bookings/{booking}/lesson/materials/{material}` — `admin.lessons.materials.destroy`<br>`GET\|HEAD /admin/bookings/{booking}/lesson/materials/{material}` — `admin.lessons.materials.open` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Booking/Student/context, original credit-source ledger and current materials/published Resources. |
| Data created / changed | LessonMaterial references/title/description/order/visibility, private UUID PDF bytes or safe link; withdrawn state and owned-file cleanup/audit. |
| Business rules | Admin/Super Admin policy permits teaching; Assistant is denied. Private files stay outside public root; Resource references reuse files, recordings are validated links, not a recording capture service. Withdrawal clears access/references, retains row/history and reports cleanup pending if bytes could not be removed. Student access has separate completed/ended-confirmed/share checks. Private PDF maximum is 10 MiB; complete %PDF-/EOF bytes and PassiveLessonPdf reject scripts, automatic actions, launches and embedded files. Material kinds private_file/resource/external_link/recording have database payload-shape checks; title ≤200, description ≤5000, order 0–10000; sharing defaults off. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | Authorized individual private payload only; no generic teaching-text export. |
| Edges / negative cases | Admin/Super Admin policy permits teaching; Assistant is denied. Private files stay outside public root; Resource references reuse files, recordings are validated links, not a recording capture service. Withdrawal clears access/references, retains row/history and reports cleanup pending if bytes could not be removed. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/LessonWorkspaceTest.php](tests/Feature/LessonWorkspaceTest.php)<br>[tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later approved QA lesson attaches one PDF/reference/link; publish/reorder/withdraw and verify exact private hashes/headers, Assistant/foreign/upcoming-Student denial and cleanup semantics. |
| Dependent / related capability IDs | [SA-039](#sa-039), [SA-061](#sa-061), [STU-011](#stu-011), [STU-012](#stu-012) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/LessonWorkspaceController.php](app/Http/Controllers/Admin/LessonWorkspaceController.php)<br>[app/Domains/Booking/Services/LessonMaterialService.php](app/Domains/Booking/Services/LessonMaterialService.php)<br>[app/Rules/PassiveLessonPdf.php](app/Rules/PassiveLessonPdf.php) |
| QA needs | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION, BOOKING-DATA, RESOURCE-DATA |

### SA-053

**Author shared/private homework and tutor completion/feedback**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Teaching/homework. Author shared/private homework and tutor completion/feedback. Assigned/in_progress/submitted/completed lifecycle; only tutor completes. |
| UI / routes | Student Records → Teaching; optional Booking context<br>`GET\|HEAD /admin/students/{student}/teaching` — `admin.students.teaching`<br>`POST /admin/students/{student}/teaching/{kind}` — `admin.students.teaching.store` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Canonical Student, owned optional Booking/material/published Resource and current homework. |
| Data created / changed | Homework title/instructions/assigned/due dates/status/share, tutor feedback/safe link/references; no duplicate uploaded Resource. |
| Business rules | Assigned/in_progress/submitted/completed lifecycle; only tutor completes. Owner/lesson/reference checks repeat under Student lock; due≥assigned date and 5000-character text bounds. Staff changes do not publish internal preparation or answer text into generic analytics/Telegram audit. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. |
| Filters / search | Teaching page with optional owned booking query; no universal homework CSV/XLSX. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Staff changes do not publish internal preparation or answer text into generic analytics/Telegram audit. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later shared/private QA homework runs Student response→staff completion/feedback; foreign lesson/material, unsafe URL and invalid dates reject. |
| Dependent / related capability IDs | [STU-014](#stu-014), [SA-052](#sa-052), [SA-056](#sa-056) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Students/Services/TeachingRecordService.php](app/Domains/Students/Services/TeachingRecordService.php)<br>[app/Http/Requests/SaveTeachingRecordRequest.php](app/Http/Requests/SaveTeachingRecordRequest.php)<br>[app/Http/Controllers/Admin/StudentTeachingController.php](app/Http/Controllers/Admin/StudentTeachingController.php) |
| QA needs | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION, RESOURCE-DATA |

### SA-054

**Learning plans and editable milestones with completion facts**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Teaching plans. Learning plans and editable milestones with completion facts. Milestone parent ownership is checked; completing retains original completed_at, reopening clears it and touches plan. |
| UI / routes | Student Records → Teaching Plans/milestones<br>`GET\|HEAD /admin/students/{student}/teaching` — `admin.students.teaching`<br>`POST /admin/students/{student}/teaching/{kind}` — `admin.students.teaching.store` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Student plan/goals/focus/level and plan-owned milestones. |
| Data created / changed | Plan active/paused/completed status, share/start dates/notes and milestone pending/in_progress/completed state/time. |
| Business rules | Milestone parent ownership is checked; completing retains original completed_at, reopening clears it and touches plan. Student reads shared state only; no automatic course sequencing, assessment or Student milestone authoring. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Student reads shared state only; no automatic course sequencing, assessment or Student milestone authoring. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later create/edit shared/private QA plan and milestone, complete/reopen and inspect exact Student progress/foreign plan denial. |
| Dependent / related capability IDs | [STU-013](#stu-013), [STU-018](#stu-018), [SA-053](#sa-053) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Students/Services/TeachingRecordService.php](app/Domains/Students/Services/TeachingRecordService.php)<br>[app/Http/Controllers/Admin/StudentTeachingController.php](app/Http/Controllers/Admin/StudentTeachingController.php) |
| QA needs | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION |

### SA-055

**Staff-only Student/lesson preparation**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Private tutor preparation. Staff-only Student/lesson preparation. Preparation is a separate structurally staff-only record with no student_visible switch. |
| UI / routes | Student Records → Teaching preparation<br>`GET\|HEAD /admin/students/{student}/teaching` — `admin.students.teaching`<br>`POST /admin/students/{student}/teaching/{kind}` — `admin.students.teaching.store` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Owned Student and optional lesson context; current TutorPreparations. |
| Data created / changed | Bounded escaped private preparation body and creator/context. |
| Business rules | Preparation is a separate structurally staff-only record with no student_visible switch. Assistant lacks teaching management; Student/public/notifications/exports never receive preparation text. Audit stores identifiers/kind, not private body. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Preparation is a separate structurally staff-only record with no student_visible switch. Assistant lacks teaching management; Student/public/notifications/exports never receive preparation text. Audit stores identifiers/kind, not private body. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later write a distinctive QA preparation marker and prove absence from Student learning/workspace/notifications and generic exports. |
| Dependent / related capability IDs | [SA-052](#sa-052), [SA-054](#sa-054) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Students/Models/TutorPreparation.php](app/Domains/Students/Models/TutorPreparation.php)<br>[app/Http/Requests/SaveTeachingRecordRequest.php](app/Http/Requests/SaveTeachingRecordRequest.php)<br>[app/Http/Controllers/Admin/StudentTeachingController.php](app/Http/Controllers/Admin/StudentTeachingController.php) |
| QA needs | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION |

### SA-056

**Reference existing Resources with instructions, sharing and review reset**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Resource assignment. Reference existing Resources with instructions, sharing and review reset. No duplicated Resource/file/material/gate request. |
| UI / routes | Student Records → Teaching Assign resource<br>`GET\|HEAD /admin/students/{student}/teaching` — `admin.students.teaching`<br>`POST /admin/students/{student}/teaching/{kind}` — `admin.students.teaching.store` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Published existing Resource, canonical Student and optional owned lesson. |
| Data created / changed | ResourceAssignment reference/instructions/share; reviewed_at cleared when Resource changes; withdraw sets sharing false. |
| Business rules | No duplicated Resource/file/material/gate request. Publication and Student/lesson ownership checked on save/open; changed assignment resets prior review, optional withdraw hides it. No automatic file copying or unreviewed re-sharing after Resource reset. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | No duplicated Resource/file/material/gate request. No automatic file copying or unreviewed re-sharing after Resource reset. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later shared QA assignment is reviewed by Student, switched to another published Resource, then withdrawn/unpublished; verify review reset/private denial and unchanged library bytes. |
| Dependent / related capability IDs | [SA-061](#sa-061), [STU-015](#stu-015), [SA-052](#sa-052) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Students/Services/TeachingRecordService.php](app/Domains/Students/Services/TeachingRecordService.php)<br>[app/Http/Controllers/Admin/StudentTeachingController.php](app/Http/Controllers/Admin/StudentTeachingController.php) |
| QA needs | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION, RESOURCE-DATA |

### SA-057

**Maintain pronunciation/vocabulary/grammar error log**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Correction patterns. Maintain pronunciation/vocabulary/grammar error log. Finite categories/statuses and text bounds; progress uses stored improved/resolved facts. |
| UI / routes | Student Records → Teaching patterns<br>`GET\|HEAD /admin/students/{student}/teaching` — `admin.students.teaching`<br>`POST /admin/students/{student}/teaching/{kind}` — `admin.students.teaching.store` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Canonical Student/optional owned lesson and current error records. |
| Data created / changed | Mistake/correction/notes/category/practising-improved-resolved status and explicit share. |
| Business rules | Finite categories/statuses and text bounds; progress uses stored improved/resolved facts. No inferred pronunciation scoring, speech-recognition evaluation or public proficiency export; privacy redaction later removes private text. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | No inferred pronunciation scoring, speech-recognition evaluation or public proficiency export; privacy redaction later removes private text. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later pronunciation/vocabulary/grammar QA cases update status/share; Student sees only shared corrections and exact progress counts. |
| Dependent / related capability IDs | [STU-016](#stu-016), [STU-018](#stu-018), [SA-024](#sa-024) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Students/Services/TeachingRecordService.php](app/Domains/Students/Services/TeachingRecordService.php)<br>[app/Http/Controllers/Admin/StudentTeachingController.php](app/Http/Controllers/Admin/StudentTeachingController.php) |
| QA needs | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION |

### SA-058

**Normalize and deduplicate Student/lesson tags**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Teaching tags. Normalize and deduplicate Student/lesson tags. Serialized Student lock prevents duplicate create; no DB uniqueness that would destroy merged historical rows. |
| UI / routes | Student Records → Teaching tags<br>`GET\|HEAD /admin/students/{student}/teaching` — `admin.students.teaching`<br>`POST /admin/students/{student}/teaching/{kind}` — `admin.students.teaching.store` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Canonical Student, optional owned lesson and normalized existing label. |
| Data created / changed | TeachingTag/share with lowercased trimmed bounded label; repeated same Student/lesson/label create reuses current record. |
| Business rules | Serialized Student lock prevents duplicate create; no DB uniqueness that would destroy merged historical rows. Letters/numbers/spaces/hyphens validate; Student-visible tags remain private teaching context. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Serialized Student lock prevents duplicate create; no DB uniqueness that would destroy merged historical rows. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later create same tag twice with case/whitespace variants and owned/foreign lesson contexts; verify one shared normalized tag and preserved merge history. |
| Dependent / related capability IDs | [STU-017](#stu-017), [SA-023](#sa-023) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Students/Services/TeachingRecordService.php](app/Domains/Students/Services/TeachingRecordService.php)<br>[app/Http/Controllers/Admin/StudentTeachingController.php](app/Http/Controllers/Admin/StudentTeachingController.php) |
| QA needs | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION |

### SA-059

**Read private Student lesson ratings/comments**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Private feedback review. Read private Student lesson ratings/comments. Private completed-lesson feedback is visible to teaching staff, not a public testimonial or editable marketing score. |
| UI / routes | Student Records → Teaching feedback<br>`GET\|HEAD /admin/students/{student}/teaching` — `admin.students.teaching` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Student LessonFeedback and owned Booking context. |
| Data created / changed | None. |
| Business rules | Private completed-lesson feedback is visible to teaching staff, not a public testimonial or editable marketing score. Student submission reuses one booking-unique row; teaching audit/notifications do not duplicate private comments. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Private completed-lesson feedback is visible to teaching staff, not a public testimonial or editable marketing score. Student submission reuses one booking-unique row; teaching audit/notifications do not duplicate private comments. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Yes, current output can be inspected without domain writes.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later Student QA edits one completed-lesson rating; staff sees latest same-row value while Public/Assistant/private exports do not publish comment. |
| Dependent / related capability IDs | [STU-023](#stu-023), [SA-052](#sa-052) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/StudentTeachingController.php](app/Http/Controllers/Admin/StudentTeachingController.php) |
| QA needs | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION |

### SA-060

**Staff author/share and Super Admin manage Student Educational Notes**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Educational notes. Staff author/share and Super Admin manage Student Educational Notes. Authorized Assistant/Admin can read/create under existing policy, but edits remain creator-aware; Super Admin can edit/delete any. |
| UI / routes | Student Records → Educational Notes<br>`GET\|HEAD /admin/students/{student}/notes` — `admin.student-bins.index`<br>`POST /admin/students/{student}/notes` — `admin.student-bins.store`<br>`GET\|HEAD /admin/students/{student}/notes/{bin}/edit` — `admin.student-bins.edit`<br>`PUT /admin/students/{student}/notes/{bin}` — `admin.student-bins.update`<br>`DELETE /admin/students/{student}/notes/{bin}` — `admin.student-bins.destroy` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | StudentBins with Student/share/creator ownership. |
| Data created / changed | Staff-created note text/share, authorized edits, DELETE-confirmed soft deletion/history and identifier-only audit. |
| Business rules | Authorized Assistant/Admin can read/create under existing policy, but edits remain creator-aware; Super Admin can edit/delete any. Student edits only Student-authored visible notes; hidden staff notes remain private. Student note deletion has no portal route. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin,assistant`. |
| Filters / search | Escaped title/body q and 20-item updated-order pagination. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Student note deletion has no portal route. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BinAuthorizationTest.php](tests/Feature/BinAuthorizationTest.php)<br>[tests/Feature/StudentTeachingExperienceTest.php](tests/Feature/StudentTeachingExperienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later staff-/Student-authored shared/private QA notes verify exact actor edit rights, Super Admin delete confirmation and retained audit/history. |
| Dependent / related capability IDs | [STU-019](#stu-019), [SA-024](#sa-024), [SA-019](#sa-019) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/StudentBinController.php](app/Http/Controllers/StudentBinController.php)<br>[app/Policies/StudentBinPolicy.php](app/Policies/StudentBinPolicy.php) |
| QA needs | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION |

### SA-061

**Library authoring, private payload/cover replacement, drafts and publication**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Resource management. Library authoring, private payload/cover replacement, drafts and publication. Uploads allow pdf/zip/doc/docx/mp3/wav/m4a up to 50 MiB; cover up to 10 MiB. |
| UI / routes | Resources; Create/Edit/Preview/discard draft<br>`GET\|HEAD /admin/resources` — `admin.resources.index`<br>`GET\|HEAD /admin/resources/create` — `admin.resources.create`<br>`POST /admin/resources` — `admin.resources.store`<br>`GET\|HEAD /admin/resources/{resource}/edit` — `admin.resources.edit`<br>`PUT /admin/resources/{resource}` — `admin.resources.update`<br>`DELETE /admin/resources/{resource}` — `admin.resources.destroy`<br>`DELETE /admin/resources/{resource}/draft` — `admin.resources.draft.destroy`<br>`GET\|HEAD /resources/{slug}/preview` — `resources.preview` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Resources/Categories/translations/revisions, current private payload and shared public cover references. |
| Data created / changed | Resource metadata/status/gating/order, private resources payload or external URL, public cover Media, draft/source revision and audit; ordinary delete removes its payload then soft-deletes metadata. |
| Business rules | Uploads allow pdf/zip/doc/docx/mp3/wav/m4a up to 50 MiB; cover up to 10 MiB. Draft/publish workflow preserves live values until explicit publishing. Replacement retires old unreferenced bytes after commit. Ordinary per-Resource delete is distinct from Stage 5 reviewed graph reset and does not use that shared-file quarantine/recovery contract. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | Public gated payload and private assignment access are separate; no ordinary library-wide portable export. |
| Edges / negative cases | Ordinary per-Resource delete is distinct from Stage 5 reviewed graph reset and does not use that shared-file quarantine/recovery contract. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/ResourceSafeReplacementTest.php](tests/Feature/ResourceSafeReplacementTest.php)<br>[tests/Feature/ResourceCoverSharedMediaTest.php](tests/Feature/ResourceCoverSharedMediaTest.php)<br>[tests/Feature/ExternalResourceLinkingTest.php](tests/Feature/ExternalResourceLinkingTest.php)<br>[tests/Feature/CmsDraftAndPreviewMatrixTest.php](tests/Feature/CmsDraftAndPreviewMatrixTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later disposable file/link/cover resources verify draft/live replacement, unreferenced cleanup and shared covers; exercise ordinary deletion only on positively disposable isolated bytes, and compare Stage 5 reset separately. |
| Dependent / related capability IDs | [SA-062](#sa-062), [SA-063](#sa-063), [SA-066](#sa-066), [PUB-014](#pub-014), [STU-015](#stu-015), [SA-092](#sa-092) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/ResourceController.php](app/Http/Controllers/Admin/ResourceController.php)<br>[app/Domains/Resources/Models/Resource.php](app/Domains/Resources/Models/Resource.php)<br>[app/Http/Controllers/ResourceController.php](app/Http/Controllers/ResourceController.php) |
| QA needs | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION, DESTRUCTIVE-EXECUTION |

### SA-062

**Manage category labels, active/order and localization**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Resource taxonomy. Manage category labels, active/order and localization. Categories feed public active category navigation. |
| UI / routes | Resources → Resource Categories<br>`GET\|HEAD /admin/resource-categories` — `admin.resource-categories.index`<br>`GET\|HEAD /admin/resource-categories/create` — `admin.resource-categories.create`<br>`POST /admin/resource-categories` — `admin.resource-categories.store`<br>`GET\|HEAD /admin/resource-categories/{resource_category}/edit` — `admin.resource-categories.edit`<br>`PUT\|PATCH /admin/resource-categories/{resource_category}` — `admin.resource-categories.update`<br>`DELETE /admin/resource-categories/{resource_category}` — `admin.resource-categories.destroy` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Categories and referencing Resource count/translations. |
| Data created / changed | Category title/slug/active/order/source translations and authorized deletion/audit. |
| Business rules | Categories feed public active category navigation. Finite ownership/FK rules protect referencing Resources; ordinary CRUD/category actions are separate from configurable Stage 5 Resources/Categories reset scope. Locale publication uses current source revision. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Follow the publication/eligibility and current-state rules above; validate the rejected or unavailable case in the acceptance scenario. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/AdminManagementAndCmsTest.php](tests/Feature/AdminManagementAndCmsTest.php)<br>[tests/Feature/CmsTranslationAndRevisionsTest.php](tests/Feature/CmsTranslationAndRevisionsTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later disposable category supports translated tab/filter and inactive hiding; inspect referencing-category refusal and unchanged retained Resource ownership. |
| Dependent / related capability IDs | [SA-061](#sa-061), [SA-066](#sa-066), [PUB-014](#pub-014) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/ResourceCategoryController.php](app/Http/Controllers/Admin/ResourceCategoryController.php) |
| QA needs | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION |

### SA-063

**Upload/pick public Media with reference-protected removal**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Public Media. Upload/pick public Media with reference-protected removal. Image JPEG/PNG/WebP/GIF or PDF ≤10 MiB; SVG is excluded from uploaded public files. |
| UI / routes | Media Library and shared asset picker<br>`GET\|HEAD /admin/media` — `admin.media.index`<br>`POST /admin/media` — `admin.media.store`<br>`GET\|HEAD /admin/media/picker` — `admin.media.picker`<br>`DELETE /admin/media/{media}` — `admin.media.destroy` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Media metadata/public file size/dimensions/alt text and current content/cover references. |
| Data created / changed | Public Media rows/bytes and metadata/audit; unreferenced file+row deletion. |
| Business rules | Image JPEG/PNG/WebP/GIF or PDF ≤10 MiB; SVG is excluded from uploaded public files. Picker reuses approved existing assets; referenced Media removal refuses rather than breaking known content. Public Media is not private lesson-file storage or automatic portable Media import. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | Picker metadata/native public payloads; no bulk portable Media import. |
| Edges / negative cases | Picker reuses approved existing assets; referenced Media removal refuses rather than breaking known content. Public Media is not private lesson-file storage or automatic portable Media import. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/CmsAndMediaWorkflowTest.php](tests/Feature/CmsAndMediaWorkflowTest.php)<br>[tests/Feature/ResourceCoverSharedMediaTest.php](tests/Feature/ResourceCoverSharedMediaTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later disposable allowed image/PDF upload/picker/reference removal and SVG/oversize rejection; no real shared owner asset deletion. |
| Dependent / related capability IDs | [SA-061](#sa-061), [SA-064](#sa-064), [SA-069](#sa-069) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/MediaController.php](app/Http/Controllers/Admin/MediaController.php)<br>[app/Domains/CMS/Models/Media.php](app/Domains/CMS/Models/Media.php) |
| QA needs | RESOURCE-DATA, SECURITY/PERMISSION, DESTRUCTIVE-EXECUTION |

### SA-064

**Draft/live Pages, sanitized rich text, revisions and explicit restoration**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Page CMS. Draft/live Pages, sanitized rich text, revisions and explicit restoration. Draft save and explicit publish/restore preserve history rather than overwrite arbitrary old revision. |
| UI / routes | Pages CMS; edit/preview/history<br>`GET\|HEAD /admin/pages` — `admin.pages.index`<br>`GET\|HEAD /admin/pages/create` — `admin.pages.create`<br>`POST /admin/pages` — `admin.pages.store`<br>`GET\|HEAD /admin/pages/{page}/edit` — `admin.pages.edit`<br>`PUT\|PATCH /admin/pages/{page}` — `admin.pages.update`<br>`DELETE /admin/pages/{page}` — `admin.pages.destroy`<br>`DELETE /admin/pages/{page}/draft` — `admin.pages.draft.destroy`<br>`POST /admin/pages/{page}/revisions/{revision}/restore` — `admin.pages.revisions.restore`<br>`GET\|HEAD /p/{slug}/preview` — `pages.preview` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Pages/live fields, ContentRevisions, source translation revisions and SEO metadata. |
| Data created / changed | Sanitized page/draft/live publication state, new retained revisions or restored revision, audit and authorized removal. |
| Business rules | Draft save and explicit publish/restore preserve history rather than overwrite arbitrary old revision. Published public visibility follows status/time; authenticated previews do not leak drafts. Dangerous rich text/active content is sanitized; source edits can make translations stale. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | Title-sorted 20-item CMS page list; specific slug/revision. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Published public visibility follows status/time; authenticated previews do not leak drafts. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/CmsDraftAndPreviewMatrixTest.php](tests/Feature/CmsDraftAndPreviewMatrixTest.php)<br>[tests/Feature/CmsTranslationAndRevisionsTest.php](tests/Feature/CmsTranslationAndRevisionsTest.php)<br>[tests/Feature/CmsAndMediaWorkflowTest.php](tests/Feature/CmsAndMediaWorkflowTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later disposable page draft→preview→publish→new revision→restore tests live/draft separation, sanitized content, foreign preview and stale translation. |
| Dependent / related capability IDs | [PUB-004](#pub-004), [SA-063](#sa-063), [SA-066](#sa-066) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/PageController.php](app/Http/Controllers/Admin/PageController.php)<br>[app/Domains/CMS/Services/RichTextSanitizer.php](app/Domains/CMS/Services/RichTextSanitizer.php)<br>[app/Http/Controllers/PageController.php](app/Http/Controllers/PageController.php) |
| QA needs | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION |

### SA-065

**FAQ drafts and native social/Reddit/WhatsApp channel configuration**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Content/communication. FAQ drafts and native social/Reddit/WhatsApp channel configuration. New SocialLink starts disabled; supported platform includes Reddit. |
| UI / routes | Content & FAQs; FAQ translation and social rows<br>`GET\|HEAD /admin/content` — `admin.content.index`<br>`POST /admin/content/faqs` — `admin.content.faq.store`<br>`PUT /admin/content/faqs/{faq}` — `admin.content.faq.update`<br>`DELETE /admin/content/faqs/{faq}` — `admin.content.faq.destroy`<br>`DELETE /admin/content/faqs/{faq}/draft` — `admin.content.faq.draft.destroy`<br>`POST /admin/content/social` — `admin.content.social.update`<br>`POST /admin/content/social/add` — `admin.content.social.store`<br>`POST /admin/content/social/{socialLink}/toggle` — `admin.content.social.toggle`<br>`GET\|HEAD /faq/preview` — `faq.preview` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | FAQs/source/draft translations and SocialLink validated targets. |
| Data created / changed | FAQ text/active/order/revisions; social rows/label/target/message/order/enabled and safe audit. |
| Business rules | New SocialLink starts disabled; supported platform includes Reddit. HTTP(S) targets prohibit unsafe credentialed/invalid schemes; Telegram handle and international WhatsApp numbers use dedicated validation. FAQ live/draft semantics and public escaping stay intact; no external message is sent by configuration alone. |
| Configuration | SocialLinks platform/enabled/url_or_phone/label/default_message/sort and FAQ status/order. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | New SocialLink starts disabled; supported platform includes Reddit. HTTP(S) targets prohibit unsafe credentialed/invalid schemes; Telegram handle and international WhatsApp numbers use dedicated validation. FAQ live/draft semantics and public escaping stay intact; no external message is sent by configuration alone. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/StageOnePresentationTest.php](tests/Feature/StageOnePresentationTest.php)<br>[tests/Feature/CmsDraftAndPreviewMatrixTest.php](tests/Feature/CmsDraftAndPreviewMatrixTest.php)<br>[tests/Feature/SocialHistoryAndIpPrivacyTest.php](tests/Feature/SocialHistoryAndIpPrivacyTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later disposable FAQ and safe Reddit channel test draft/publish, native href, disabled hiding, unsafe URL refusal and correct click placement metadata; restore permanent config. |
| Dependent / related capability IDs | [PUB-004](#pub-004), [PUB-019](#pub-019), [PUB-020](#pub-020), [SA-066](#sa-066) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/ContentController.php](app/Http/Controllers/Admin/ContentController.php)<br>[app/Http/Controllers/PageController.php](app/Http/Controllers/PageController.php) |
| QA needs | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION |

### SA-066

**French/German draft publication and source-revision reconciliation**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Localization CMS. French/German draft publication and source-revision reconciliation. Only supported entity/FR/DE locale; unknown kind/locale rejects. |
| UI / routes | Inline translation panels; reconcile diff<br>`POST /admin/translations/{entityType}/{id}/{locale}/draft` — `admin.translations.save-draft`<br>`POST /admin/translations/{entityType}/{id}/{locale}/publish` — `admin.translations.publish`<br>`GET\|HEAD /admin/translations/{entityType}/{id}/{locale}/reconcile` — `admin.translations.reconcile` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Current English source revision and FR/DE draft/live translation for Resource/Game/Page/FAQ/category. |
| Data created / changed | Localized draft or explicit published translation/archive and source-revision acknowledgement. |
| Business rules | Only supported entity/FR/DE locale; unknown kind/locale rejects. Publication explicitly archives prior live version and validates current source; English edits can leave translation stale pending human reconciliation. Draft diff GET is read-only, not an auto-translation service. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Only supported entity/FR/DE locale; unknown kind/locale rejects. Draft diff GET is read-only, not an auto-translation service. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/AdminTranslationWorkflowTest.php](tests/Feature/AdminTranslationWorkflowTest.php)<br>[tests/Feature/CmsTranslationAndRevisionsTest.php](tests/Feature/CmsTranslationAndRevisionsTest.php)<br>[tests/Feature/DevelopmentDataArchiveTest.php](tests/Feature/DevelopmentDataArchiveTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later disposable English source edit shows stale FR/DE; save/reconcile/publish exact approved translation and verify public fallback without draft leakage. |
| Dependent / related capability IDs | [PUB-003](#pub-003), [SA-061](#sa-061), [SA-064](#sa-064), [SA-065](#sa-065), [SA-068](#sa-068) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/TranslationController.php](app/Http/Controllers/Admin/TranslationController.php)<br>[app/Domains/CMS/Services/TranslationService.php](app/Domains/CMS/Services/TranslationService.php) |
| QA needs | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION |

### SA-067

**Blog draft/publication, sanitized Markdown, optimistic edits and old-slug history**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Blog CMS. Blog draft/publication, sanitized Markdown, optimistic edits and old-slug history. Current public authority is Blogs, not abandoned articles tables. |
| UI / routes | Blog → Create/Edit/Preview<br>`GET\|HEAD /admin/blog` — `admin.blog.index`<br>`GET\|HEAD /admin/blog/create` — `admin.blog.create`<br>`POST /admin/blog` — `admin.blog.store`<br>`GET\|HEAD /admin/blog/{blog}/edit` — `admin.blog.edit`<br>`PUT /admin/blog/{blog}` — `admin.blog.update`<br>`DELETE /admin/blog/{blog}` — `admin.blog.destroy`<br>`GET\|HEAD /admin/blog/{blog}/preview` — `admin.blog.preview` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Blogs/BlogRevisions/slug redirects and lock_version. |
| Data created / changed | Validated sanitized blog metadata/body/status/publication/revision/slug history and authorized removal. |
| Business rules | Current public authority is Blogs, not abandoned articles tables. Optimistic version rejects stale edit; draft preview staff/noindex only. Renaming a published slug retains redirect to published target; raw HTML/unsafe Markdown content cannot become active script. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Current public authority is Blogs, not abandoned articles tables. Optimistic version rejects stale edit; draft preview staff/noindex only. Renaming a published slug retains redirect to published target; raw HTML/unsafe Markdown content cannot become active script. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BlogPublishingTest.php](tests/Feature/BlogPublishingTest.php)<br>[tests/Feature/CmsAndMediaWorkflowTest.php](tests/Feature/CmsAndMediaWorkflowTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later disposable blog draft→preview→publish→rename and stale-edit conflict; verify current public old-slug 301 and draft denial. |
| Dependent / related capability IDs | [PUB-017](#pub-017), [SA-063](#sa-063) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/BlogController.php](app/Http/Controllers/Admin/BlogController.php)<br>[app/Domains/CMS/Services/BlogService.php](app/Domains/CMS/Services/BlogService.php) |
| QA needs | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION |

### SA-068

**Game catalog status, safe target, draft/publish and localized presentation**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Games/content. Game catalog status, safe target, draft/publish and localized presentation. Available/coming_soon public display is distinct from playable tracking; current game presentation/local/external target is not a programmable LMS lesson engine. |
| UI / routes | Learning Games → Create/Edit/Preview<br>`GET\|HEAD /admin/games` — `admin.games.index`<br>`GET\|HEAD /admin/games/create` — `admin.games.create`<br>`POST /admin/games` — `admin.games.store`<br>`GET\|HEAD /admin/games/{game}/edit` — `admin.games.edit`<br>`PUT /admin/games/{game}` — `admin.games.update`<br>`DELETE /admin/games/{game}` — `admin.games.destroy`<br>`DELETE /admin/games/{game}/draft` — `admin.games.draft.destroy`<br>`GET\|HEAD /games/{slug}/preview` — `games.preview` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Games/live/draft revisions, translations and safe thumbnail/target references. |
| Data created / changed | Game title/description/badge/target/thumbnail/order/status/draft/source revision and audit. |
| Business rules | Available/coming_soon public display is distinct from playable tracking; current game presentation/local/external target is not a programmable LMS lesson engine. Draft saves/previews avoid leaking live changes; active target schemes/path validation and publication checks apply. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Available/coming_soon public display is distinct from playable tracking; current game presentation/local/external target is not a programmable LMS lesson engine. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/CmsDraftAndPreviewMatrixTest.php](tests/Feature/CmsDraftAndPreviewMatrixTest.php)<br>[tests/Feature/PublicExperienceTest.php](tests/Feature/PublicExperienceTest.php)<br>[tests/Feature/AdminTranslationWorkflowTest.php](tests/Feature/AdminTranslationWorkflowTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later disposable available/coming-soon/hidden/unsafe-target game verifies catalog, draft preview/live separation and translated published content. |
| Dependent / related capability IDs | [PUB-018](#pub-018), [SA-063](#sa-063), [SA-066](#sa-066) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/GameController.php](app/Http/Controllers/Admin/GameController.php)<br>[app/Http/Controllers/GameController.php](app/Http/Controllers/GameController.php) |
| QA needs | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION |

### SA-069

**Manage scheduled promotion bars, modal/cards and countdown**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Promotions. Manage scheduled promotion bars, modal/cards and countdown. Display types top_bar/floating_modal/inline_card; end≥start, safe CTA/banner validation and 10 MiB uploaded banner limit. |
| UI / routes | Promotions Portal → Create/Edit/Toggle<br>`GET\|HEAD /admin/promotions` — `admin.promotions.index`<br>`GET\|HEAD /admin/promotions/create` — `admin.promotions.create`<br>`POST /admin/promotions` — `admin.promotions.store`<br>`GET\|HEAD /admin/promotions/{promotion}/edit` — `admin.promotions.edit`<br>`PUT /admin/promotions/{promotion}` — `admin.promotions.update`<br>`POST /admin/promotions/{promotion}/toggle` — `admin.promotions.toggle`<br>`DELETE /admin/promotions/{promotion}` — `admin.promotions.destroy`<br>`GET\|HEAD /admin/promotions/{promotion}/preview` — `admin.promotions.preview` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Promotions and approved image/CTA references. |
| Data created / changed | Promotion active/display type/headline/image/CTA/windows/countdown and audit; cache invalidation. |
| Business rules | Display types top_bar/floating_modal/inline_card; end≥start, safe CTA/banner validation and 10 MiB uploaded banner limit. Latest eligible record per placement uses transition-aware cache capped at 300 seconds; no scheduler needed to flip publication window. Separate from emergency announcement. Authenticated Promotion preview renders the selected promotion separately from active public display; normal admin preview permission applies. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Business-zone date/time input converted to UTC eligibility; countdown displays remaining elapsed time. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Latest eligible record per placement uses transition-aware cache capped at 300 seconds; no scheduler needed to flip publication window. |
| Background / manual behavior | **Background:** On-demand eligibility/cache; no promotion publication job.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/PromotionsTest.php](tests/Feature/PromotionsTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later controlled future/live/expired dummy promotion verifies eligibility transition/dismissal/countdown and bad CTA/banner rejection; restore owner config. |
| Dependent / related capability IDs | [PUB-026](#pub-026), [SA-063](#sa-063), [SA-078](#sa-078) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/PromotionController.php](app/Http/Controllers/Admin/PromotionController.php)<br>[app/Domains/Marketing/Services/PromotionService.php](app/Domains/Marketing/Services/PromotionService.php) |
| QA needs | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION |

### SA-070

**Versioned questionnaire authoring, conditions, assignment triggers and publication**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Forms builder. Versioned questionnaire authoring, conditions, assignment triggers and publication. Typed questions/conditional required/validation/info blocks, mandatory/edit-after-submission and assignment triggers define runtime forms. |
| UI / routes | Student Forms → Create/Edit/Publish/Archive<br>`GET\|HEAD /admin/forms` — `admin.forms.index`<br>`GET\|HEAD /admin/forms/create` — `admin.forms.create`<br>`POST /admin/forms` — `admin.forms.store`<br>`GET\|HEAD /admin/forms/{form}/edit` — `admin.forms.edit`<br>`PUT /admin/forms/{form}` — `admin.forms.update`<br>`PUT /admin/forms/{form}/publish` — `admin.forms.publish`<br>`POST /admin/forms/{form}/archive` — `admin.forms.archive` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Forms/current draft/published version/questions/options/triggers/lock version. |
| Data created / changed | New retained FormVersion/question/options, triggers/metadata, explicit publish/archive and safe audit. |
| Business rules | Typed questions/conditional required/validation/info blocks, mandatory/edit-after-submission and assignment triggers define runtime forms. base_version_id and lock_version refuse stale edits/publication; historical answers remain tied to original version. Assistant can read permitted published definitions, not manage builder. No Student arbitrary form authoring/file upload. Current assignments are derived, not a fabricated manual-assignment table: a published form without pre_booking/after_booking/after_reschedule/next_session_check triggers is generally assigned; after_booking requires owned nondeleted booking history, after_reschedule an owned reschedule, next_session_check an upcoming confirmed lesson. Own prior submission keeps the form available; pre_booking alone does not generally assign it in the portal. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin,assistant`; `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | base_version_id and lock_version refuse stale edits/publication; historical answers remain tied to original version. Assistant can read permitted published definitions, not manage builder. No Student arbitrary form authoring/file upload. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/FormsEngineTest.php](tests/Feature/FormsEngineTest.php)<br>[tests/Feature/FilterAwareExportsTest.php](tests/Feature/FilterAwareExportsTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later one small pre-booking and one follow-up versioned QA form cover conditional/info block/assignment, stale lock, publish/archive and Student new-version choice. |
| Dependent / related capability IDs | [PUB-010](#pub-010), [STU-020](#stu-020), [SA-071](#sa-071) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/FormController.php](app/Http/Controllers/Admin/FormController.php)<br>[app/Domains/Forms/Services/FormBuilderService.php](app/Domains/Forms/Services/FormBuilderService.php)<br>[app/Domains/Forms/Services/FormAssignmentService.php](app/Domains/Forms/Services/FormAssignmentService.php) |
| QA needs | STUDENT-DATA, RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION |

### SA-071

**Versioned submissions, permission-filtered answers and tabular export**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Form responses. Versioned submissions, permission-filtered answers and tabular export. Answers stay on their submitted version; screen/export use same FormSubmissionQuery. |
| UI / routes | Student Forms → Submissions/export; Student record form history<br>`GET\|HEAD /admin/forms/{form}/submissions` — `admin.forms.submissions`<br>`GET\|HEAD /admin/forms/{form}/export` — `admin.forms.export` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | FormVersions/current/prior FormSubmissions/Answers/revisions and current staff role. |
| Data created / changed | None apart from generated export. |
| Business rules | Answers stay on their submitted version; screen/export use same FormSubmissionQuery. Assistant omits non-assistant-visible answers/questions and info blocks are not answer columns. Actual submitted timestamp and status retained; no synthetic completion from draft autosave. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin,assistant`. |
| Filters / search | version_id, status draft/submitted, Student name/email q, business created date_from/date_to; 50-item page. |
| CSV / XLSX / other export | CSV/XLSX same version/filter/role columns, formula safety and workbook limits. |
| Edges / negative cases | Assistant omits non-assistant-visible answers/questions and info blocks are not answer columns. Actual submitted timestamp and status retained; no synthetic completion from draft autosave. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/FormsEngineTest.php](tests/Feature/FormsEngineTest.php)<br>[tests/Feature/FilterAwareExportsTest.php](tests/Feature/FilterAwareExportsTest.php)<br>[tests/Feature/PhaseEVerificationTest.php](tests/Feature/PhaseEVerificationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later QA draft/submitted old/new versions compare full Super Admin and restricted Assistant screen/workbook, conditional answers and foreign Student privacy. |
| Dependent / related capability IDs | [SA-070](#sa-070), [STU-020](#stu-020), [STU-021](#stu-021) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/FormController.php](app/Http/Controllers/Admin/FormController.php)<br>[app/Domains/Forms/Services/FormSubmissionQuery.php](app/Domains/Forms/Services/FormSubmissionQuery.php) |
| QA needs | READ-ONLY, STUDENT-DATA, SECURITY/PERMISSION |

### SA-072

**Live Pulse, primary/resource/game funnels, goals and acquisition reports**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Analytics/funnels. Live Pulse, primary/resource/game funnels, goals and acquisition reports. Funnels use coherent session/cohort identity and maturation/reconciliation semantics rather than adding unrelated event counts. |
| UI / routes | Analytics & Funnels<br>`GET\|HEAD /admin/analytics` — `admin.analytics`<br>`GET\|HEAD /admin/analytics/overview/export` — `admin.analytics.overview.export` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Non-bot visitor/session/events, canonical server completion facts, cohort/marketing progress and daily/current-day raw reporting. |
| Data created / changed | None apart from generated export; read reporting may use existing cache. |
| Business rules | Funnels use coherent session/cohort identity and maturation/reconciliation semantics rather than adding unrelated event counts. Live visitors, bounce trend, goal conversion and acquisition preserve internal/preview/bot exclusion and historical authority boundaries. No financial retention prediction or fake conversion repair. |
| Configuration | Existing analytics goals, bot/internal exclusions, identity/funnel definitions and configured Business Timezone. Current reporting uses retained raw/daily facts, not invented conversions. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | ReportPeriod range and paired start_date/end_date; csv/xlsx format. Shared controller validates search ≤255, section ≤64, sort country_code/country_name/unique_visitors/sessions/bounce_rate/conversion_rate/booking_cta_clicks/bookings_completed and dir asc/desc; country/section screens apply their relevant fields. No arbitrary campaign/country filter on the overview. |
| CSV / XLSX / other export | Overview CSV/XLSX with same reporting scope and defined rows. |
| Edges / negative cases | No financial retention prediction or fake conversion repair. |
| Background / manual behavior | **Background:** Existing daily analytics/country/social rollups and raw retention; current-day live calculation on request.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/AnalyticsFunnelAndAttributionTest.php](tests/Feature/AnalyticsFunnelAndAttributionTest.php)<br>[tests/Feature/FunnelCohortReconciliationAndMaturityTest.php](tests/Feature/FunnelCohortReconciliationAndMaturityTest.php)<br>[tests/Feature/AnalyticsAndReportsTest.php](tests/Feature/AnalyticsAndReportsTest.php)<br>[tests/Feature/CairoDailyAnalyticsRollupTest.php](tests/Feature/CairoDailyAnalyticsRollupTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later bounded labeled sessions cover completed/abandoned/maturing funnel, duplicate UUID and bot/internal cases; reconcile screen/workbook without changing historical cutover. |
| Dependent / related capability IDs | [PUB-024](#pub-024), [SA-075](#sa-075), [SA-077](#sa-077) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/AnalyticsDashboardController.php](app/Http/Controllers/Admin/AnalyticsDashboardController.php)<br>[app/Domains/Analytics/Services/AnalyticsService.php](app/Domains/Analytics/Services/AnalyticsService.php) |
| QA needs | READ-ONLY, ANALYTICS-DATA, SECURITY/PERMISSION |

### SA-073

**Country traffic, conversion, flags and sorting/export**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Country analytics. Country traffic, conversion, flags and sorting/export. Detected country is distinct from customer timezone and language. |
| UI / routes | Country Analytics<br>`GET\|HEAD /admin/analytics/countries` — `admin.analytics.countries`<br>`GET\|HEAD /admin/analytics/countries/export` — `admin.analytics.countries.export` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Server-resolved country codes, canonical non-bot sessions/visitors/bookings and daily country rollups. |
| Data created / changed | None apart from generated export. |
| Business rules | Detected country is distinct from customer timezone and language. Friendly country labels/flags plus Unknown/world fallback; distinct visitor/session and numerator/denominator conversions stay canonical. Client-supplied country does not redefine trusted attribution or infer manual-booking country. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | ReportPeriod, country/name search, whitelisted metric/country sort and asc/desc. |
| CSV / XLSX / other export | Filtered/sorted CSV/XLSX country rows with consistent date bounds. |
| Edges / negative cases | Client-supplied country does not redefine trusted attribution or infer manual-booking country. |
| Background / manual behavior | **Background:** Existing daily country aggregation declaration; current day raw reporting.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/SocialHistoryAndIpPrivacyTest.php](tests/Feature/SocialHistoryAndIpPrivacyTest.php)<br>[tests/Feature/CountryInitialLocaleTest.php](tests/Feature/CountryInitialLocaleTest.php)<br>[tests/Feature/CairoDailyAnalyticsRollupTest.php](tests/Feature/CairoDailyAnalyticsRollupTest.php)<br>[tests/Feature/FilterAwareExportsTest.php](tests/Feature/FilterAwareExportsTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later two trusted-country/unknown/bot-tagged QA sessions verify distinct counts, flags, sorted export and period/day boundary parity. |
| Dependent / related capability IDs | [PUB-024](#pub-024), [SA-072](#sa-072), [SA-077](#sa-077) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/AnalyticsDashboardController.php](app/Http/Controllers/Admin/AnalyticsDashboardController.php)<br>[app/Domains/Timezone/Services/TimezoneDisplayService.php](app/Domains/Timezone/Services/TimezoneDisplayService.php) |
| QA needs | READ-ONLY, ANALYTICS-DATA, SECURITY/PERMISSION |

### SA-074

**Section views, dwell, bounce/drop-off and filtering/export**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Attention analytics. Section views, dwell, bounce/drop-off and filtering/export. Section attention is observed engagement, not fabricated learning mastery. |
| UI / routes | Section Attention<br>`GET\|HEAD /admin/analytics/sections` — `admin.analytics.sections`<br>`GET\|HEAD /admin/analytics/sections/export` — `admin.analytics.sections.export` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Allowlisted section_view/dwell events and reporting metric rollups/exposure/session context. |
| Data created / changed | None apart from generated export. |
| Business rules | Section attention is observed engagement, not fabricated learning mastery. Repeated clicks/presence pings do not inflate views; validated section IDs and bounded dwell values preserve signal. Business-date historical rollups and current raw data retain exclusion rules. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | ReportPeriod and section selection; metrics match same scoped source. |
| CSV / XLSX / other export | CSV/XLSX section rows and current reporting semantics. |
| Edges / negative cases | Section attention is observed engagement, not fabricated learning mastery. Repeated clicks/presence pings do not inflate views; validated section IDs and bounded dwell values preserve signal. |
| Background / manual behavior | **Background:** Existing analytics aggregation/pruning; no per-section dedicated worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/ClientTelemetryIngestionTest.php](tests/Feature/ClientTelemetryIngestionTest.php)<br>[tests/Feature/AnalyticsAndReportsTest.php](tests/Feature/AnalyticsAndReportsTest.php)<br>[tests/Feature/FilterAwareExportsTest.php](tests/Feature/FilterAwareExportsTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later controlled section exposure/dwell sessions test hidden/time bounds, repeated events, bounce/drop-off and screen/workbook parity. |
| Dependent / related capability IDs | [PUB-024](#pub-024), [PUB-023](#pub-023), [SA-072](#sa-072) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/AnalyticsDashboardController.php](app/Http/Controllers/Admin/AnalyticsDashboardController.php)<br>[resources/js/analytics-telemetry.js](resources/js/analytics-telemetry.js) |
| QA needs | READ-ONLY, ANALYTICS-DATA, SECURITY/PERMISSION |

### SA-075

**Traffic, booking, Resource, social, event and campaign reports/exports**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Operational reports. Traffic, booking, Resource, social, event and campaign reports/exports. Screen/export share the same filters/readers. |
| UI / routes | Operational Reports & Exports → report type<br>`GET\|HEAD /admin/reports` — `admin.reports.index`<br>`GET\|HEAD /admin/reports/export` — `admin.reports.export` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Canonical reporting visitor/session/bookings/resource requests/downloads/social events/marketing context and historical rollups. |
| Data created / changed | None apart from generated CSV/XLSX. |
| Business rules | Screen/export share the same filters/readers. Social history preserves platform/placement/page/country/source/medium/campaign/language/context after raw pruning; semantic dedupe avoids double click counts. Data meanings/cutover and distinct visitors are retained even when explanatory warning copy is absent. No raw IP or staff credentials in ordinary exports. Durable daily_social_metrics retains business-day/timezone/dimension-group counts and daily group-distinct visitors. After raw pruning, period-distinct visitors are unavailable: daily uniques must not be summed and labeled period-distinct. Historical rollups are not relabeled into another timezone, unknown dimensions remain Unknown and no historical facts are fabricated. Aggregation must finish before raw pruning; rebuilding an already pruned day preserves its durable snapshot. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | ReportPeriod ranges/paired custom dates/format; type traffic/bookings/resources/social/events/campaigns. Traffic: source. Bookings: status/source/campaign. Resources: resource_id/category_id. Social: platform/placement/page_url/country/source/medium/campaign/language/context. Events: event_name/page_url/source. Campaigns: campaign/source/content. Selection options/string/foreign-ID validation is type-specific; no global universal filter. |
| CSV / XLSX / other export | Six matching CSV/XLSX types; UTF-8 BOM streamed CSV, formula-safe strings; XLSX ≤10,000 rows/200,000 cells/8 MB text plus memory capacity guard. XLSX uses an in-memory workbook with a 65% PHP memory guard in addition to row/cell/text limits; it is not streaming. Streaming CSV does not make every upstream aggregation constant-memory. |
| Edges / negative cases | No raw IP or staff credentials in ordinary exports. After raw pruning, period-distinct visitors are unavailable: daily uniques must not be summed and labeled period-distinct. Historical rollups are not relabeled into another timezone, unknown dimensions remain Unknown and no historical facts are fabricated. |
| Background / manual behavior | **Background:** Existing daily raw-to-rollup retention supports history; export itself synchronous/on-demand.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/FilterAwareExportsTest.php](tests/Feature/FilterAwareExportsTest.php)<br>[tests/Feature/SocialHistoryAndIpPrivacyTest.php](tests/Feature/SocialHistoryAndIpPrivacyTest.php)<br>[tests/Feature/AnalyticsAndReportsTest.php](tests/Feature/AnalyticsAndReportsTest.php)<br>[tests/Feature/CairoDailyAnalyticsRollupTest.php](tests/Feature/CairoDailyAnalyticsRollupTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later tiny tagged campaign/social/resource/booking fixtures compare filters and parsed CSV/XLSX, unknown dimensions, repeated click and pre/post pruning parity. |
| Dependent / related capability IDs | [SA-072](#sa-072), [SA-073](#sa-073), [SA-074](#sa-074), [PUB-024](#pub-024), [SA-081](#sa-081) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/ReportController.php](app/Http/Controllers/Admin/ReportController.php)<br>[app/Domains/Reporting/Services/ExportService.php](app/Domains/Reporting/Services/ExportService.php)<br>[app/Domains/Reporting/Services/ReportPeriod.php](app/Domains/Reporting/Services/ReportPeriod.php) |
| QA needs | READ-ONLY, ANALYTICS-DATA, BOOKING-DATA, RESOURCE-DATA, SECURITY/PERMISSION |

### SA-076

**Friendly maintenance visitor/session/country details and export**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Maintenance analytics. Friendly maintenance visitor/session/country details and export. Super Admin-only maintained one summary with friendly countries/flags and correct Business Timezone labels. |
| UI / routes | Maintenance Mode under Insights; legacy health export alias<br>`GET\|HEAD /admin/analytics/maintenance` — `admin.analytics.maintenance`<br>`GET\|HEAD /admin/analytics/maintenance/export` — `admin.analytics.maintenance.export`<br>`GET\|HEAD /admin/health/maintenance-visitors/export` — `admin.health.maintenance-visitors.export` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | MaintenanceVisits and canonical aggregated country/time/referrer context. |
| Data created / changed | None apart from generated export. |
| Business rules | Super Admin-only maintained one summary with friendly countries/flags and correct Business Timezone labels. Screen/export filter parity; compatibility health export alias is same capability, not abandoned separate business report. Maintenance traffic does not imply a completed booking. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Date filters and screen labels use Business Timezone, converted to UTC bounds. Export explicitly labels Timestamp (UTC) and emits stored created_at UTC; do not reinterpret export timestamps as business-local. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin`. |
| Filters / search | ReportPeriod ranges today/7d/30d/90d/month/this_month/last_month/custom, paired start_date/end_date, country (ZZ includes null/empty/XX/ZZ), escaped path substring and csv/xlsx; maximum five-year span; 30-row page. |
| CSV / XLSX / other export | CSV/XLSX from same scope; no raw-IP reporting identity. |
| Edges / negative cases | Screen/export filter parity; compatibility health export alias is same capability, not abandoned separate business report. Maintenance traffic does not imply a completed booking. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/MaintenanceModeResilienceTest.php](tests/Feature/MaintenanceModeResilienceTest.php)<br>[tests/Feature/StageOnePresentationTest.php](tests/Feature/StageOnePresentationTest.php)<br>[tests/Feature/FilterAwareExportsTest.php](tests/Feature/FilterAwareExportsTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later explicitly authorized brief maintenance window or isolated fixtures produce labeled visits; compare country/time summaries and workbook filters while staff remains available. |
| Dependent / related capability IDs | [PUB-022](#pub-022), [SA-079](#sa-079), [SA-080](#sa-080) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/MaintenanceAnalyticsController.php](app/Http/Controllers/Admin/MaintenanceAnalyticsController.php) |
| QA needs | READ-ONLY, ANALYTICS-DATA, SECURITY/PERMISSION |

### SA-077

**Branding, public copy, business timezone, booking defaults, counters and privacy goals**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** System/business configuration. Branding, public copy, business timezone, booking defaults, counters and privacy goals. Public copy/sections and bilingual operations keep existing authorities. |
| UI / routes | Settings & Policies; separate operational save<br>`GET\|HEAD /admin/settings` — `admin.settings.index`<br>`POST /admin/settings` — `admin.settings.update`<br>`POST /admin/settings/operations` — `admin.settings.operations`<br>`GET\|HEAD /preview/home` — `home.preview`<br>`GET\|HEAD /about/preview` — `about.preview` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Current validated Setting groups, homepage/About/maintenance/WhatsApp/counters/analytics goal/exclusion definitions. |
| Data created / changed | Validated settings and cache-specific refresh/audit; no change performed by this inventory. |
| Business rules | Public copy/sections and bilingual operations keep existing authorities. Valid IANA Business Timezone controls future date boundaries without rewriting old booking snapshots; individual SessionType/rule overrides remain separate. Counter source/window/templates and goal allowlist are finite; connection exclusion stores HMAC, not raw IP. Authenticated preview/home and about/preview use draft settings where present without publishing them; normal preview role/noindex boundaries apply. |
| Configuration | business_timezone, public_brand/homepage/About content, booking fallback instructions/limits, counters.*, WhatsApp audience/URL/locale text, analytics.goals/internal_hashes/retention and operational settings. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Counter source/window/templates and goal allowlist are finite; connection exclusion stores HMAC, not raw IP. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/OperationalSettingsWorkflowTest.php](tests/Feature/OperationalSettingsWorkflowTest.php)<br>[tests/Feature/TimezoneTest.php](tests/Feature/TimezoneTest.php)<br>[tests/Feature/EngagementCountersTest.php](tests/Feature/EngagementCountersTest.php)<br>[tests/Feature/StageOnePresentationTest.php](tests/Feature/StageOnePresentationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later explicit scoped configuration approval compares defaults/overrides and safe invalid values; snapshot/restore permanent settings and verify no historical timestamp rewrite. |
| Dependent / related capability IDs | [PUB-001](#pub-001), [PUB-020](#pub-020), [PUB-023](#pub-023), [PUB-024](#pub-024), [SA-045](#sa-045), [SA-047](#sa-047) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/SettingController.php](app/Http/Controllers/Admin/SettingController.php)<br>[app/Http/Controllers/HomeController.php](app/Http/Controllers/HomeController.php)<br>[app/Http/Controllers/PageController.php](app/Http/Controllers/PageController.php) |
| QA needs | READ-ONLY, CONFIGURATION-TOGGLE, SECURITY/PERMISSION |

### SA-078

**Configure emergency announcement audience, localized text, window, severity and CTA**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Emergency communication. Configure emergency announcement audience, localized text, window, severity and CTA. Separate save avoids accidental other setting writes. |
| UI / routes | Settings & Policies → Site Announcement separate form<br>`POST /admin/settings/announcement` — `admin.settings.announcement` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Current announcement settings and business-time/DST resolver. |
| Data created / changed | announcement.* validated enabled/audience/severity/message/CTA/dismissible/window values. |
| Business rules | Separate save avoids accidental other setting writes. Supports Public or Public+Student audience, EN/FR/DE fallback and information/warning/urgent; SafeLessonUrl HTTPS CTA and unambiguous existent start/end local time. Disabled/empty/out-of-window is intentionally hidden. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Business local scheduling saved as absolute ISO instants; DST gap/fold input rejects. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Disabled/empty/out-of-window is intentionally hidden. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/AnnouncementBannerTest.php](tests/Feature/AnnouncementBannerTest.php)<br>[tests/Feature/StageOnePresentationTest.php](tests/Feature/StageOnePresentationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** No; existing surface/browser state is sufficient, with configuration or security authorization still required where stated. |
| Recommended acceptance scenario | Later authorized announcement-only toggle tests all audience/window/dismissal/version/unsafe URL cases, then restore original state. |
| Dependent / related capability IDs | [PUB-021](#pub-021), [SA-077](#sa-077), [PUB-003](#pub-003) |
| Production cross-check | Configuration form exists; effective announcement disabled, no toggle made. |
| Current source evidence | [app/Http/Controllers/Admin/SettingController.php](app/Http/Controllers/Admin/SettingController.php)<br>[app/Domains/CMS/Services/AnnouncementService.php](app/Domains/CMS/Services/AnnouncementService.php) |
| QA needs | CONFIGURATION-TOGGLE, SECURITY/PERMISSION |

### SA-079

**Application Maintenance Mode switch and message**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Maintenance control. Application Maintenance Mode switch and message. Guest/public 503 while staff/admin/health stay accessible; application switch is distinct from Laravel deployment down mode. |
| UI / routes | System Health or Settings maintenance controls<br>`POST /admin/settings` — `admin.settings.update`<br>`GET\|HEAD /admin/health` — `admin.health` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | maintenance_mode/message and current authenticated staff state. |
| Data created / changed | Explicit application maintenance Setting/cache/audit through existing action. |
| Business rules | Guest/public 503 while staff/admin/health stay accessible; application switch is distinct from Laravel deployment down mode. Current flag is off; no maintenance configuration changed for inventory. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`; `super_admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Current flag is off; no maintenance configuration changed for inventory. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BackupAndMaintenanceTest.php](tests/Feature/BackupAndMaintenanceTest.php)<br>[tests/Feature/MaintenanceModeResilienceTest.php](tests/Feature/MaintenanceModeResilienceTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** No; existing surface/browser state is sufficient, with configuration or security authorization still required where stated. |
| Recommended acceptance scenario | Later explicit bounded configuration window tests guest 503/allowed staff/health and maintenance visit reporting; restore off. |
| Dependent / related capability IDs | [PUB-022](#pub-022), [SA-076](#sa-076), [SA-077](#sa-077) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/SettingController.php](app/Http/Controllers/Admin/SettingController.php)<br>[app/Http/Middleware/CheckMaintenanceMode.php](app/Http/Middleware/CheckMaintenanceMode.php)<br>[app/Http/Controllers/Admin/SystemHealthController.php](app/Http/Controllers/Admin/SystemHealthController.php) |
| QA needs | CONFIGURATION-TOGGLE, SECURITY/PERMISSION, ANALYTICS-DATA |

### SA-080

**Read environment, clocks, storage and operational heartbeat health**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** System diagnostics. Read environment, clocks, storage and operational heartbeat health. Diagnostics report actual healthy/stale/failure/unknown state rather than configuring services. |
| UI / routes | System Health<br>`GET\|HEAD /admin/health` — `admin.health` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Safe environment/runtime/database/cookie/path/queue/backup/analytics/Telegram heartbeat state and clocks. |
| Data created / changed | None. |
| Business rules | Diagnostics report actual healthy/stale/failure/unknown state rather than configuring services. Existing production Log mail and s3_replication_failed warning remain; zero queued jobs is not proof of sustained worker health. No secret/key/token material is a capability-report value. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | PHP/application/UTC/Business Timezone clocks shown explicitly; scheduler declarations use their recorded zone. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Existing production Log mail and s3_replication_failed warning remain; zero queued jobs is not proof of sustained worker health. No secret/key/token material is a capability-report value. |
| Background / manual behavior | **Background:** Existing scheduler heartbeat each minute, hold cleanup every five minutes, daily analytics/backup/session cleanup, minute Telegram tick and monthly geo-IP declaration; inventory neither runs nor configures them.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/ProductionHealthAndSchedulerTest.php](tests/Feature/ProductionHealthAndSchedulerTest.php)<br>[tests/Feature/ProductionCookieSecurityTest.php](tests/Feature/ProductionCookieSecurityTest.php)<br>[tests/Feature/DatabaseTestPreflightTest.php](tests/Feature/DatabaseTestPreflightTest.php)<br>[tests/Feature/OperationalRemediationTest.php](tests/Feature/OperationalRemediationTest.php) |
| Read-only / synthetic QA | **Read-only:** Yes, current output can be inspected without domain writes.<br>**Synthetic data required for complete scenario:** No; existing surface/browser state is sufficient, with configuration or security authorization still required where stated. |
| Recommended acceptance scenario | Read safe current diagnostics/last run categories; later controlled test/fake probes verify stale/failure labels without changing production cron/worker/mail/S3. |
| Dependent / related capability IDs | [SA-081](#sa-081), [SA-076](#sa-076), [SA-085](#sa-085), [SA-077](#sa-077) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/SystemHealthController.php](app/Http/Controllers/Admin/SystemHealthController.php)<br>[routes/console.php](routes/console.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION |

### SA-081

**Create/list/download/delete positively managed private backups**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Backup management. Create/list/download/delete positively managed private backups. Only positively identified managed archives are listed/deleted/pruned; protected/manual/deployment/unknown artifacts survive. |
| UI / routes | Backups & Recovery<br>`GET\|HEAD /admin/backups` — `admin.backups.index`<br>`POST /admin/backups` — `admin.backups.create`<br>`GET\|HEAD /admin/backups/{filename}/download` — `admin.backups.download`<br>`DELETE /admin/backups/{filename}` — `admin.backups.destroy` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Managed private snapshot catalog/manifest/proof and retention/storage/last-result settings. |
| Data created / changed | On-demand full backup archive/audit/status; allowed managed archive deletion and retention; configured offsite replication attempt. |
| Business rules | Only positively identified managed archives are listed/deleted/pruned; protected/manual/deployment/unknown artifacts survive. Raw full backups may contain credentials/private data and are not sanitized portable exports. Creation/retention share Stage 5 mutex. No ordinary web full-system restore route; legacy console restore/generated-trigger recovery needs separate reviewed drill, not portable round-trip certification. |
| Configuration | Private local managed disk/root, backup_retention_days, backup types and existing offsite disk; current S3 warning preserved. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | Authorized managed ZIP download; contains sensitive full-system recovery data, unlike SA-094 domain export. |
| Edges / negative cases | Raw full backups may contain credentials/private data and are not sanitized portable exports. No ordinary web full-system restore route; legacy console restore/generated-trigger recovery needs separate reviewed drill, not portable round-trip certification. |
| Background / manual behavior | **Background:** Existing daily backup:run --clean declaration at 02:00 application scheduler time; manual Run Backup Now also exists. Execution/storage success is not inferred from declaration.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BackupAndMaintenanceTest.php](tests/Feature/BackupAndMaintenanceTest.php)<br>[tests/Feature/DisasterRecoveryRestoreDrillTest.php](tests/Feature/DisasterRecoveryRestoreDrillTest.php)<br>[tests/Feature/DevelopmentLaunchDataToolsTest.php](tests/Feature/DevelopmentLaunchDataToolsTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later separately approved disposable managed snapshot with known manifest is created/downloaded/hash-checked and removed; preserve manual/protected archive and compare next backup lifecycle. |
| Dependent / related capability IDs | [SA-080](#sa-080), [SA-093](#sa-093), [SA-094](#sa-094), [SA-088](#sa-088) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/BackupController.php](app/Http/Controllers/Admin/BackupController.php)<br>[app/Domains/System/Services/BackupService.php](app/Domains/System/Services/BackupService.php)<br>[app/Domains/System/Services/ManagedBackupCatalog.php](app/Domains/System/Services/ManagedBackupCatalog.php) |
| QA needs | RESOURCE-DATA, SECURITY/PERMISSION, DESTRUCTIVE-EXECUTION |

### SA-082

**Safe role-restricted audit viewer and readable allowlisted diffs**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Audit/privacy. Safe role-restricted audit viewer and readable allowlisted diffs. Super Admin only; raw IP, password/token/TOTP/recovery/free sensitive text do not render. |
| UI / routes | Security Audit Log; restricted dashboard summary<br>`GET\|HEAD /admin/audit-logs` — `admin.audit-logs` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Immutable audit actor/action/entity/time and safe allowlisted old/new fields, including scrubbed legacy payloads. |
| Data created / changed | None. |
| Business rules | Super Admin only; raw IP, password/token/TOTP/recovery/free sensitive text do not render. Actor kind/IDs and safe business/status changes remain readable; display redaction is separate from existing audit history and controlled privacy erasure. No arbitrary raw audit payload export. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin`. |
| Filters / search | Actor type/ID, action, entity, business date_from/date_to and domain; 30-item pagination. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Super Admin only; raw IP, password/token/TOTP/recovery/free sensitive text do not render. No arbitrary raw audit payload export. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php)<br>[tests/Feature/AdministratorTwoFactorTest.php](tests/Feature/AdministratorTwoFactorTest.php)<br>[tests/Feature/SocialHistoryAndIpPrivacyTest.php](tests/Feature/SocialHistoryAndIpPrivacyTest.php) |
| Read-only / synthetic QA | **Read-only:** Yes, current output can be inspected without domain writes.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later QA actor/status audits plus deliberately sensitive isolated legacy fixture verify safe diffs/filtering and ordinary Admin/Assistant denial; no secret generation in owner session. |
| Dependent / related capability IDs | [SA-003](#sa-003), [SA-018](#sa-018), [SA-024](#sa-024) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/SystemHealthController.php](app/Http/Controllers/Admin/SystemHealthController.php)<br>[app/Domains/Audit/Services/AuditLogPresentation.php](app/Domains/Audit/Services/AuditLogPresentation.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION |

### SA-083

**Read-only identity/purchase/configuration/relationship risks and bounded duplicate candidates**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Data quality. Read-only identity/purchase/configuration/relationship risks and bounded duplicate candidates. Samples bounded at 30; duplicate scan first 1000 eligible rows/kind and at most 100 pairs. |
| UI / routes | Data Quality Center<br>`GET\|HEAD /admin/data-quality` — `admin.data-quality.index` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Missing Student fields, unclassified/missing-grant purchases, invalid policy/timezone/type and owner relationships; normalized Student/Contact candidates. |
| Data created / changed | None; no automatic merge/repair/classification. |
| Business rules | Samples bounded at 30; duplicate scan first 1000 eligible rows/kind and at most 100 pairs. Same normalized name plus email or international phone is conservative signal; ambiguous national phone/anonymized identities excluded. Not exhaustive duplicate certification. Invalid policy/timezone/type flags do not silently overwrite settings; review links lead to authoritative actions. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Not exhaustive duplicate certification. Invalid policy/timezone/type flags do not silently overwrite settings; review links lead to authoritative actions. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php)<br>[tests/Feature/StudentMergeAndPrivacyTest.php](tests/Feature/StudentMergeAndPrivacyTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later valid legacy/missing-identity/duplicate candidate QA cases verify bounded read-only counts, safe links, ambiguous phone exclusion and unchanged rows/config. |
| Dependent / related capability IDs | [SA-020](#sa-020), [SA-023](#sa-023), [SA-038](#sa-038), [SA-044](#sa-044), [SA-047](#sa-047), [SA-098](#sa-098) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Students/Services/DataQualityReadModel.php](app/Domains/Students/Services/DataQualityReadModel.php)<br>[app/Http/Controllers/Admin/DataQualityController.php](app/Http/Controllers/Admin/DataQualityController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, ENTITLEMENT-DATA |

### SA-084

**Existing bot/destination/global switches and controlled read-command settings**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Telegram configuration. Existing bot/destination/global switches and controlled read-command settings. Token encrypted/hidden and blank edit preserves it; destination cannot move bots, same-bot chat identity unique. |
| UI / routes | Telegram Bots → Overview/Bots/Destinations/Commands<br>`GET\|HEAD /admin/telegram` — `admin.telegram.index`<br>`POST /admin/telegram/bots/{bot?}` — `admin.telegram.bot`<br>`POST /admin/telegram/destinations/{destination?}` — `admin.telegram.destination`<br>`POST /admin/telegram/global` — `admin.telegram.global` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Configured encrypted Bots, enabled destinations/detail level/allowed sender IDs and global flags; existing legacy import marker. |
| Data created / changed | Validated bot/destination/global configuration only on explicit POST; index/tick invokes idempotent existing legacy importer when needed. |
| Business rules | Token encrypted/hidden and blank edit preserves it; destination cannot move bots, same-bot chat identity unique. Optional today/tomorrow/student/stats commands require global+bot enable, bot allowlist and enabled trusted destination sender IDs; personal Student lookup only personal detail. Current bot commands disabled and global missing flag defaults false; inventory never polls/enables/modifies them. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Token encrypted/hidden and blank edit preserves it; destination cannot move bots, same-bot chat identity unique. Current bot commands disabled and global missing flag defaults false; inventory never polls/enables/modifies them. |
| Background / manual behavior | **Background:** Existing poll code is conditional in Telegram read/tick path; disabled commands are not activated by this inventory.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/TelegramAutomationTest.php](tests/Feature/TelegramAutomationTest.php)<br>[tests/Feature/TelegramMultiRecipientReminderTest.php](tests/Feature/TelegramMultiRecipientReminderTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect labels/non-secret current switches; legacy importer is idempotent. No configuration POST or poll performed.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later isolated/faked configured QA bot/destination checks encrypted blank-token edit, wrong-bot/unauthorized sender and disabled command refusal; real recipient/security expansion needs separate approval. |
| Dependent / related capability IDs | [SA-085](#sa-085), [SA-086](#sa-086), [SA-080](#sa-080) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/TelegramController.php](app/Http/Controllers/Admin/TelegramController.php)<br>[app/Domains/Notifications/Services/TelegramCommandService.php](app/Domains/Notifications/Services/TelegramCommandService.php) |
| QA needs | CONFIGURATION-TOGGLE, SECURITY/PERMISSION |

### SA-085

**Rule templates, booking reminders, event alerts and on-demand digest requests**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Outbound Telegram. Rule templates, booking reminders, event alerts and on-demand digest requests. Catalog limits trigger modes/template variables/sections/conditions and destination ownership; priorities/cooldowns/quiet hours bound delivery. |
| UI / routes | Telegram Bots → Rules and Run eligible digest; Preview with sample data<br>`POST /admin/telegram/rules/{rule?}` — `admin.telegram.rule`<br>`POST /admin/telegram/run/{rule}` — `admin.telegram.run`<br>`POST /admin/telegram/preview` — `admin.telegram.preview` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Trigger catalog, same-bot destinations/conditions, canonical bookings/business/analytics facts, template fields and cooldown/quiet windows. |
| Data created / changed | Rule configuration on save; deduplicated scheduled/on-demand TelegramDelivery and safe run audit. Sample preview only writes response/session flash, using catalog Sample fields; it sends no Telegram message and saves no rule. |
| Business rules | Catalog limits trigger modes/template variables/sections/conditions and destination ownership; priorities/cooldowns/quiet hours bound delivery. Only on_demand business_digest/analytics_digest can be manually run. Existing reminders read canonical UTC milestones/business clock and reveal-safe meeting URLs. No progress spam/new integration or recipient change is authorized here. Existing POST Preview with sample data validates trigger/template and renders catalog sample values, not private live records; 30/minute throttle. Preview is an active UI control. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | No progress spam/new integration or recipient change is authorized here. Existing POST Preview with sample data validates trigger/template and renders catalog sample values, not private live records; 30/minute throttle. |
| Background / manual behavior | **Background:** Existing minute telegram:tick scans due state and dispatches DeliverTelegramMessage jobs; existing queue/watchdog delivery semantics remain. Runtime worker health must be separately observed.<br>**Manual/on-demand:** Explicit rule edits and eligible on-demand digest request; notification producers can emit existing rules. |
| Existing automated tests | [tests/Feature/TelegramAutomationTest.php](tests/Feature/TelegramAutomationTest.php)<br>[tests/Feature/TelegramMultiRecipientReminderTest.php](tests/Feature/TelegramMultiRecipientReminderTest.php)<br>[tests/Feature/MeetingRotationTest.php](tests/Feature/MeetingRotationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later isolated/fake transports cover eligible/replayed rules, milestones/quiet-window/cooldown/recipient/template refusal; owner-authorized final inventory status is the only live send here. |
| Dependent / related capability IDs | [SA-084](#sa-084), [SA-086](#sa-086), [SA-039](#sa-039), [SA-051](#sa-051), [SA-075](#sa-075) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Notifications/Services/TelegramAutomationService.php](app/Domains/Notifications/Services/TelegramAutomationService.php)<br>[app/Domains/Notifications/Services/TelegramReadService.php](app/Domains/Notifications/Services/TelegramReadService.php)<br>[app/Console/Commands/TelegramTickCommand.php](app/Console/Commands/TelegramTickCommand.php)<br>[app/Http/Controllers/Admin/TelegramController.php](app/Http/Controllers/Admin/TelegramController.php)<br>[resources/views/admin/telegram/index.blade.php](resources/views/admin/telegram/index.blade.php) |
| QA needs | BOOKING-DATA, ANALYTICS-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION |

### SA-086

**Safe delivery history, enabled-destination test and one-off verified status pathway**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Telegram delivery. Safe delivery history, enabled-destination test and one-off verified status pathway. Direct path reuses verified destination and deduplicated identity; never guess token/destination. |
| UI / routes | Telegram Bots → History/Test; existing authorized completion service<br>`GET\|HEAD /admin/telegram` — `admin.telegram.index`<br>`POST /admin/telegram/test/{destination}` — `admin.telegram.test`<br>`POST /admin/settings/telegram/test` — `admin.settings.telegram.test` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Existing enabled Bot/destination, prior successful delivery proof and pending/sent/failed/uncertain history. |
| Data created / changed | Explicit test or authorized direct status TelegramDelivery/message IDs; safe delivery outcome, dedupe and failure categories. |
| Business rules | Direct path reuses verified destination and deduplicated identity; never guess token/destination. History identifies actual sent/failure/uncertain, not inferred delivery. Tests require enabled bot+destination; uncertain interrupted delivery is not aggressively retried. Legacy Settings test is distinct existing compatibility path, not a reason to redesign integration. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | History tab; validated bot/destination/rule/status/business date filters and 30-item pagination. |
| CSV / XLSX / other export | No credential/history dump export; safe delivery IDs/status metadata only in this matrix receipt. |
| Edges / negative cases | Direct path reuses verified destination and deduplicated identity; never guess token/destination. History identifies actual sent/failure/uncertain, not inferred delivery. Tests require enabled bot+destination; uncertain interrupted delivery is not aggressively retried. |
| Background / manual behavior | **Background:** Queued delivery retries/watchdog exist; one-off final helper does not schedule new work and cancels only its own pending send to avoid later repeats.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/TelegramAutomationTest.php](tests/Feature/TelegramAutomationTest.php)<br>[tests/Feature/TelegramMultiRecipientReminderTest.php](tests/Feature/TelegramMultiRecipientReminderTest.php)<br>[tests/Feature/OperationalRemediationTest.php](tests/Feature/OperationalRemediationTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Read actual safe history now; later faked delivery tests and separately approved sandbox Test check status/dedupe. At inventory completion send exactly the owner-approved concise counts message. |
| Dependent / related capability IDs | [SA-084](#sa-084), [SA-085](#sa-085) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Domains/Notifications/Services/TelegramDeliveryService.php](app/Domains/Notifications/Services/TelegramDeliveryService.php)<br>[app/Http/Controllers/Admin/TelegramController.php](app/Http/Controllers/Admin/TelegramController.php)<br>[app/Http/Controllers/Admin/SettingController.php](app/Http/Controllers/Admin/SettingController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION |

### SA-087

**Permanent feature-off boundary and production compatibility**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Development & Launch access. Permanent feature-off boundary and production compatibility. Disabled-by-default backend/service flag returns 404 for current Super Admin and hides navigation; role/auth independently deny other actors. |
| UI / routes | Development & Launch Tools when server-enabled; current link absent<br>`GET\|HEAD /admin/development-tools` — `admin.development-tools.index` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Effective server flag, fresh Administrator, private paths/session/cache/backup/schema compatibility and latest owner operations. |
| Data created / changed | None through disabled index; no flag change performed. |
| Business rules | Disabled-by-default backend/service flag returns 404 for current Super Admin and hides navigation; role/auth independently deny other actors. Deployment verified schema, private recovery, JSON database sessions, mutex and managed-backup exclusions with tools still off. Merely reading this matrix/runbook does not authorize enable/reset. |
| Configuration | DESTRUCTIVE_ADMIN_TOOLS_ENABLED=false; development_tools.enabled=false in cached production; require_snapshot default true. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Disabled-by-default backend/service flag returns 404 for current Super Admin and hides navigation; role/auth independently deny other actors. Merely reading this matrix/runbook does not authorize enable/reset. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/DevelopmentLaunchDataToolsTest.php](tests/Feature/DevelopmentLaunchDataToolsTest.php)<br>[tests/Feature/DevelopmentDataConcurrencyTest.php](tests/Feature/DevelopmentDataConcurrencyTest.php) |
| Read-only / synthetic QA | **Read-only:** Yes for disabled navigation/404/effective compatibility facts; enabled page requires separately authorized configuration.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Confirm off UI/direct backend now; later isolated enabled QA checks role gates and unsupported storage/session refusal without changing owner environment. |
| Dependent / related capability IDs | [SA-088](#sa-088), [SA-081](#sa-081), [SA-080](#sa-080) |
| Production cross-check | Current disabled route/hidden navigation cross-checked; operation table has zero rows. Compatibility proof is the completed same-release deployment evidence, not a destructive exercise. |
| Current source evidence | [app/Domains/System/Services/DevelopmentToolsAccess.php](app/Domains/System/Services/DevelopmentToolsAccess.php)<br>[config/development_tools.php](config/development_tools.php)<br>[app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php) |
| QA needs | READ-ONLY, SECURITY/PERMISSION, CONFIGURATION-TOGGLE |

### SA-088

**Immutable previews, password/factor/token confirmation, locks and safe recovery**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Development operation security. Immutable previews, password/factor/token confirmation, locks and safe recovery. Five-minute preview, exact phrase and current password; fresh TOTP/unused recovery only when actor MFA enabled. |
| UI / routes | Development & Launch preview/confirmation<br>`POST /admin/development-tools/preview` — `admin.development-tools.preview`<br>`POST /admin/development-tools/confirm` — `admin.development-tools.confirm` |
| Prerequisites | Separate server operator enablement and explicit destructive QA authorization; active Super Admin, matching current database/private paths, fresh preview and recovery. |
| Data read | Runtime FK/columns/generated/index inventory, reviewed ownership graph/current counts/file hashes, current owner/session/security state. |
| Data created / changed | Hashed operation row/token binding and private protected snapshot/export/quarantine during separately confirmed operation. |
| Business rules | Five-minute preview, exact phrase and current password; fresh TOTP/unused recovery only when actor MFA enabled. Owner/session/security/preview change invalidates; shared 600-second mutex excludes backup/reset competitors. Unknown dependent graph/cycle, unsupported scope/session/path or mandatory snapshot failure refuses. Confirmation rechecks locked records; completed replay returns result once with constraints/triggers enabled. |
| Configuration | require_snapshot=true; token_minutes=5; local development-data disk; global operation/backup mutex. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Owner/session/security/preview change invalidates; shared 600-second mutex excludes backup/reset competitors. Unknown dependent graph/cycle, unsupported scope/session/path or mandatory snapshot failure refuses. |
| Background / manual behavior | **Background:** No operation cleanup scheduler/worker; confirmed operations synchronous with manual private artifact retention.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/DevelopmentLaunchDataToolsTest.php](tests/Feature/DevelopmentLaunchDataToolsTest.php)<br>[tests/Feature/DevelopmentDataConcurrencyTest.php](tests/Feature/DevelopmentDataConcurrencyTest.php) |
| Read-only / synthetic QA | **Read-only:** No for creating/confirming preview state; only source/disabled boundary can be inspected now.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later isolated/disposable QA checks wrong phrase/password/factor, foreign/expired/stale token, competing operation, snapshot failure and replay without touching real owner/domain data. |
| Dependent / related capability IDs | [SA-087](#sa-087), [SA-015](#sa-015), [SA-016](#sa-016), [SA-081](#sa-081), [SA-097](#sa-097) |
| Production cross-check | Deployed but disabled; no preview or confirmation submitted. |
| Current source evidence | [app/Domains/System/Services/DevelopmentToolsService.php](app/Domains/System/Services/DevelopmentToolsService.php)<br>[app/Domains/System/Services/DevelopmentToolsAccess.php](app/Domains/System/Services/DevelopmentToolsAccess.php)<br>[app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php) |
| QA needs | SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW, DESTRUCTIVE-EXECUTION |

### SA-089

**Reviewed analytics-fact reset with retained business boundaries**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Analytics reset. Reviewed analytics-fact reset with retained business boundaries. RESET ALL ANALYTICS requires SA-088. |
| UI / routes | Development & Launch → Reset analytics<br>`POST /admin/development-tools/preview` — `admin.development-tools.preview`<br>`POST /admin/development-tools/confirm` — `admin.development-tools.confirm` |
| Prerequisites | SA-088 separately enabled/authorized disposable scope and protected recovery. |
| Data read | Reviewed visitors/sessions/events/acquisition/funnels/daily country/social/maintenance/reconciliation facts and retained business Booking boundary. |
| Data created / changed | Confirmed deletion of selected all-analytics facts, new lifecycle/dedupe generation and safe counts/recovery. |
| Business rules | RESET ALL ANALYTICS requires SA-088. Preserves content/settings/goals/exclusions/SocialLinks and business bookings; archived boundary prevents retained historical bookings refilling country/acquisition facts. Operational business reports retain history. No arbitrary source/config reset. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | No arbitrary source/config reset. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/DevelopmentLaunchDataToolsTest.php](tests/Feature/DevelopmentLaunchDataToolsTest.php)<br>[tests/Feature/DevelopmentDataArchiveTest.php](tests/Feature/DevelopmentDataArchiveTest.php) |
| Read-only / synthetic QA | **Read-only:** No; current inventory verifies disabled boundary/source only.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later isolated complete analytics QA cohort is exported/recovered then reset; facts zero, definitions/business history remain and fresh event generation dedupes independently. |
| Dependent / related capability IDs | [SA-088](#sa-088), [SA-094](#sa-094), [SA-072](#sa-072), [SA-075](#sa-075) |
| Production cross-check | Disabled; never executed in production inventory. |
| Current source evidence | [app/Domains/System/Services/DevelopmentDataResetService.php](app/Domains/System/Services/DevelopmentDataResetService.php)<br>[app/Domains/System/Services/DevelopmentDataCatalog.php](app/Domains/System/Services/DevelopmentDataCatalog.php)<br>[app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php) |
| QA needs | ANALYTICS-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW, DESTRUCTIVE-EXECUTION |

### SA-090

**Reviewed all-Student ownership graph reset and auth cleanup**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Student reset. Reviewed all-Student ownership graph reset and auth cleanup. DELETE ALL STUDENTS preserves administrators/staff login, independent Contacts/leads, shared library/business definitions/configuration. |
| UI / routes | Development & Launch → Reset students<br>`POST /admin/development-tools/preview` — `admin.development-tools.preview`<br>`POST /admin/development-tools/confirm` — `admin.development-tools.confirm` |
| Prerequisites | SA-088; explicitly disposable complete scope, compatible database-backed JSON sessions/local private files and recovery. |
| Data read | All Students including merged/archived/deleted, reviewed booking/finance/forms/teaching/planning/task/alert/private-file/auth/hold/queued dependencies. |
| Data created / changed | Confirmed owned-graph deletion, Student session/auth/recent/queued adapters, safe audit redaction and protected recovery/export. |
| Business rules | DELETE ALL STUDENTS preserves administrators/staff login, independent Contacts/leads, shared library/business definitions/configuration. Unknown ownership refuses; linked audit/visitor state detached rather than shared system history deleted. Exact FK/typed cycles handled with enforcement enabled; IDs are not restarted. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Unknown ownership refuses; linked audit/visitor state detached rather than shared system history deleted. Exact FK/typed cycles handled with enforcement enabled; IDs are not restarted. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/DevelopmentLaunchDataToolsTest.php](tests/Feature/DevelopmentLaunchDataToolsTest.php)<br>[tests/Feature/DevelopmentDataConcurrencyTest.php](tests/Feature/DevelopmentDataConcurrencyTest.php) |
| Read-only / synthetic QA | **Read-only:** No; current off boundary/source only.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later separately isolated QA graph reset verifies Student/business/auth zero, independent lead/shared Resource/staff retained and zero orphans/reconciliation; no production real-Student reset from this matrix. |
| Dependent / related capability IDs | [SA-088](#sa-088), [SA-094](#sa-094), [SA-038](#sa-038), [SA-021](#sa-021), [SA-024](#sa-024) |
| Production cross-check | Disabled; never executed in production inventory. |
| Current source evidence | [app/Domains/System/Services/DevelopmentDataResetService.php](app/Domains/System/Services/DevelopmentDataResetService.php)<br>[app/Domains/System/Services/StudentSessionCleanupService.php](app/Domains/System/Services/StudentSessionCleanupService.php)<br>[app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php) |
| QA needs | STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, TEACHING-DATA, RESOURCE-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW, DESTRUCTIVE-EXECUTION |

### SA-091

**Coherent financial test-history reset with required booking dependents**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Financial reset. Coherent financial test-history reset with required booking dependents. RESET FINANCIAL TEST HISTORY preserves Students and unrelated direct/free lessons. |
| UI / routes | Development & Launch → Reset financial test history<br>`POST /admin/development-tools/preview` — `admin.development-tools.preview`<br>`POST /admin/development-tools/confirm` — `admin.development-tools.confirm` |
| Prerequisites | SA-088 and separately approved fully disposable financial/booking closure. |
| Data read | Purchases/allocations/payments/refunds/ledger/installments/renewals/mapping reviews and package-funded Booking/teaching/file dependents. |
| Data created / changed | Confirmed complete financial graph removal plus package-funded dependents, safe recovery/export and reconciliation. |
| Business rules | RESET FINANCIAL TEST HISTORY preserves Students and unrelated direct/free lessons. Funding snapshot/debit cycles detach atomically with FKs/triggers enabled, not partial null provenance. No fake PaymentRecords, remapped rights or balance patch; empty result UNINITIALIZED_DATASET honestly. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Funding snapshot/debit cycles detach atomically with FKs/triggers enabled, not partial null provenance. No fake PaymentRecords, remapped rights or balance patch; empty result UNINITIALIZED_DATASET honestly. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/DevelopmentLaunchDataToolsTest.php](tests/Feature/DevelopmentLaunchDataToolsTest.php)<br>[tests/Feature/DevelopmentDataConcurrencyTest.php](tests/Feature/DevelopmentDataConcurrencyTest.php) |
| Read-only / synthetic QA | **Read-only:** No; off boundary/source only.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later isolated mixed direct/package graph proves financial/package-dependent zero and retained Student/direct lesson/configuration, then restore approved archive without duplicate debit/payment. |
| Dependent / related capability IDs | [SA-088](#sa-088), [SA-094](#sa-094), [SA-096](#sa-096), [SA-038](#sa-038) |
| Production cross-check | Disabled; never executed in production inventory. |
| Current source evidence | [app/Domains/System/Services/DevelopmentDataResetService.php](app/Domains/System/Services/DevelopmentDataResetService.php)<br>[app/Domains/System/Services/DevelopmentDataCatalog.php](app/Domains/System/Services/DevelopmentDataCatalog.php)<br>[app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php) |
| QA needs | FINANCIAL-DATA, ENTITLEMENT-DATA, BOOKING-DATA, TEACHING-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW, DESTRUCTIVE-EXECUTION |

### SA-092

**Immutable selected Resources/Categories/history/files reset with safe detachment**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Resource reset. Immutable selected Resources/Categories/history/files reset with safe detachment. RESET RESOURCE TEST DATA re-shows immutable scope; Categories default preserved. |
| UI / routes | Development & Launch → Reset resources options/preview<br>`POST /admin/development-tools/preview` — `admin.development-tools.preview`<br>`POST /admin/development-tools/confirm` — `admin.development-tools.confirm` |
| Prerequisites | SA-088 and separately approved disposable Resource/reference/owned-byte scope. |
| Data read | Reviewed Resource/category/translation/revision/request/download/file graph and shared material/homework references. |
| Data created / changed | Only confirmed selected resource graph; retained material rows lose Resource/share, homework pointers detach, owned unshared file quarantine/removal and safe recovery. |
| Business rules | RESET RESOURCE TEST DATA re-shows immutable scope; Categories default preserved. Cannot delete referenced definitions while omitting required history/categories. Shared/unrelated private/public bytes stay; Files unchecked retains bytes, no unowned-file sweep. Restoring Resource does not automatically republish withdrawn lesson materials. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Cannot delete referenced definitions while omitting required history/categories. Shared/unrelated private/public bytes stay; Files unchecked retains bytes, no unowned-file sweep. Restoring Resource does not automatically republish withdrawn lesson materials. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/DevelopmentLaunchDataToolsTest.php](tests/Feature/DevelopmentLaunchDataToolsTest.php)<br>[tests/Feature/DevelopmentDataArchiveTest.php](tests/Feature/DevelopmentDataArchiveTest.php) |
| Read-only / synthetic QA | **Read-only:** No; off boundary/source only.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later isolated shared Resource/material/homework/category fixture previews each option; execute only approved reset, verify safe withdrawal/retained shared bytes and refusal of unsafe omitted scope. |
| Dependent / related capability IDs | [SA-088](#sa-088), [SA-061](#sa-061), [SA-062](#sa-062), [SA-052](#sa-052), [SA-094](#sa-094), [SA-096](#sa-096) |
| Production cross-check | Disabled; never executed in production inventory. |
| Current source evidence | [app/Domains/System/Services/DevelopmentDataResetService.php](app/Domains/System/Services/DevelopmentDataResetService.php)<br>[app/Domains/System/Services/DevelopmentDataFiles.php](app/Domains/System/Services/DevelopmentDataFiles.php)<br>[app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php) |
| QA needs | RESOURCE-DATA, TEACHING-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW, DESTRUCTIVE-EXECUTION |

### SA-093

**Reset only proven managed local snapshots and last-result lifecycle**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Managed backup reset. Reset only proven managed local snapshots and last-result lifecycle. RESET MANAGED BACKUPS preserves retention/storage settings, manual/deployment/emergency/protected/unknown/unverifiable and offsite copies. |
| UI / routes | Development & Launch → Reset managed local backups<br>`POST /admin/development-tools/preview` — `admin.development-tools.preview`<br>`POST /admin/development-tools/confirm` — `admin.development-tools.confirm` |
| Prerequisites | SA-088 and positively identified disposable managed archives/private local storage. |
| Data read | Trusted managed creation audit, validated native/portable manifests/hashes and existing runtime backup result settings. |
| Data created / changed | Confirmed managed archive deletion/quarantine, last-managed/offsite runtime result cleanup and protected copies outside reset set. |
| Business rules | RESET MANAGED BACKUPS preserves retention/storage settings, manual/deployment/emergency/protected/unknown/unverifiable and offsite copies. Ownership is proven, not filename guessed. Protected recovery precedes removal; next ordinary backup starts new lifecycle; no remote backup cleanup promise. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Ownership is proven, not filename guessed. Protected recovery precedes removal; next ordinary backup starts new lifecycle; no remote backup cleanup promise. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/DevelopmentLaunchDataToolsTest.php](tests/Feature/DevelopmentLaunchDataToolsTest.php)<br>[tests/Feature/DevelopmentDataArchiveTest.php](tests/Feature/DevelopmentDataArchiveTest.php) |
| Read-only / synthetic QA | **Read-only:** No; disabled scope/source only.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later isolated one managed archive and one unmanaged/protected marker verifies preview/recovery/preservation, Never Run dashboard and next normal backup success. |
| Dependent / related capability IDs | [SA-088](#sa-088), [SA-081](#sa-081), [SA-094](#sa-094) |
| Production cross-check | Disabled; never executed in production inventory. |
| Current source evidence | [app/Domains/System/Services/ManagedBackupCatalog.php](app/Domains/System/Services/ManagedBackupCatalog.php)<br>[app/Domains/System/Services/DevelopmentDataResetService.php](app/Domains/System/Services/DevelopmentDataResetService.php)<br>[app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php) |
| QA needs | RESOURCE-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW, DESTRUCTIVE-EXECUTION |

### SA-094

**Export Everything/Custom bounded private archive with module/filter closure**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Portable domain export. Export Everything/Custom bounded private archive with module/filter closure. EXPORT DEVELOPMENT DATA still requires secure confirmation/feature flag. |
| UI / routes | Development & Launch module checkboxes and export preview<br>`POST /admin/development-tools/preview` — `admin.development-tools.preview`<br>`POST /admin/development-tools/confirm` — `admin.development-tools.confirm`<br>`GET\|HEAD /admin/development-tools/operations/{operation}/download` — `admin.development-tools.download` |
| Prerequisites | SA-088 separately enabled/authorized export scope and compatible private storage; export confirmation is nondeleting but sensitive. |
| Data read | Selected Student/bookings/forms/teaching/materials/notifications/finance/analytics/Resources/revisions/managed-snapshot modules and required parents/files. |
| Data created / changed | Versioned private ZIP/manifest/hashes/counts/schema/external dependency fingerprints and operation summary; no domain deletion. |
| Business rules | EXPORT DEVELOPMENT DATA still requires secure confirmation/feature flag. Includes required parent closure, stable IDs, business timezone/UTC creation and payload hashes. Excludes staff/users/settings/APP_KEY/factor/session/challenge/Telegram credentials; contains selected private business data. Managed native snapshots convert to sanitized domain payload without executing SQL. Limits 50k rows/100 MiB ZIP/256 MiB expanded/10k entries. No arbitrary public Media import/export promise. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin`. |
| Filters / search | Modules, Student ID, financial currency, business date range for analytics and positively proven managed snapshot selection; required parents may broaden counts. |
| CSV / XLSX / other export | Private portable ZIP only; not raw system backup or generic CSV/XLSX. |
| Edges / negative cases | No arbitrary public Media import/export promise. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/DevelopmentDataArchiveTest.php](tests/Feature/DevelopmentDataArchiveTest.php)<br>[tests/Feature/DevelopmentLaunchDataToolsTest.php](tests/Feature/DevelopmentLaunchDataToolsTest.php) |
| Read-only / synthetic QA | **Read-only:** No for archive/operation creation; existing completed owner download can read approved payload only when enabled.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later tiny approved QA graph export parses manifest/hashes/dependencies/private bytes and credential absence; actual safe browser ZIP completion remains a later acceptance gate. |
| Dependent / related capability IDs | [SA-088](#sa-088), [SA-097](#sa-097), [SA-081](#sa-081), [SA-096](#sa-096) |
| Production cross-check | Disabled; no archive export or ZIP download performed in this inventory. |
| Current source evidence | [app/Domains/System/Services/DevelopmentDataArchiveService.php](app/Domains/System/Services/DevelopmentDataArchiveService.php)<br>[app/Domains/System/Services/DevelopmentDataCatalog.php](app/Domains/System/Services/DevelopmentDataCatalog.php)<br>[app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php) |
| QA needs | STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, TEACHING-DATA, RESOURCE-DATA, ANALYTICS-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW |

### SA-095

**Upload inspection-only compatible portable archive and conflict preview**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Archive inspection. Upload inspection-only compatible portable archive and conflict preview. Upload token alone cannot authorize restore. |
| UI / routes | Development & Launch → Import archive<br>`POST /admin/development-tools/import` — `admin.development-tools.upload` |
| Prerequisites | SA-087 separate enablement/authorized compatible test ZIP and current active Super Admin. |
| Data read | Uploaded bounded ZIP/manifest/version/schema/hashes/entries/external ownership and current target identities. |
| Data created / changed | Private inspection copy and pending inspection operation only; zero live business rows restored. |
| Business rules | Upload token alone cannot authorize restore. Traversal/symlink/duplicate/unmanifested entries/unknown columns/credential fields/unsupported version/size/hash and collation identity conflicts refuse; safe selected-module preview is a separate step. Archive paths are never extracted as destinations. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Upload token alone cannot authorize restore. Traversal/symlink/duplicate/unmanifested entries/unknown columns/credential fields/unsupported version/size/hash and collation identity conflicts refuse; safe selected-module preview is a separate step. Archive paths are never extracted as destinations. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/DevelopmentDataArchiveTest.php](tests/Feature/DevelopmentDataArchiveTest.php) |
| Read-only / synthetic QA | **Read-only:** No: uploads private inspection artifact/operation but changes no business rows.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later approved tiny portable QA ZIP and corrupt/traversal/wrong-version/credential/oversize variants inspect or reject safely; assert zero live business writes before selection/confirmation. |
| Dependent / related capability IDs | [SA-094](#sa-094), [SA-096](#sa-096), [SA-088](#sa-088) |
| Production cross-check | Disabled; no upload/inspection performed. |
| Current source evidence | [app/Domains/System/Services/DevelopmentToolsService.php](app/Domains/System/Services/DevelopmentToolsService.php)<br>[app/Domains/System/Services/DevelopmentDataArchiveService.php](app/Domains/System/Services/DevelopmentDataArchiveService.php)<br>[app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php) |
| QA needs | RESOURCE-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW |

### SA-096

**Restore missing/skip identical/refuse differences with stable provenance**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Selective restore. Restore missing/skip identical/refuse differences with stable provenance. RESTORE MISSING DEVELOPMENT DATA requires separate selected preview and SA-088. |
| UI / routes | Development & Launch → Preview chosen restore → secure confirmation<br>`POST /admin/development-tools/import/selection` — `admin.development-tools.select-import`<br>`POST /admin/development-tools/confirm` — `admin.development-tools.confirm` |
| Prerequisites | SA-088 matching identities/schema/configuration/private roots and explicit compatible selected preview; separate restore authorization. |
| Data read | Inspected archive, explicitly selected module closure, current schema/IDs/unique/external-parent fingerprints and file hashes. |
| Data created / changed | Only missing selected rows and generated private owned payload paths; stable payment/debit IDs, operation/reconciliation/audit; managed Backups module adds idempotent portable snapshot files, not live DB rows. |
| Business rules | RESTORE MISSING DEVELOPMENT DATA requires separate selected preview and SA-088. Identical rows skip; changed rows/unique identities/config dependencies/missing parent modules refuse, no arbitrary overwrite or ID remap. Typed cyclic Booking staging/original debit/full snapshot restored with constraints enabled; file writes compensate on rollback. Resource restore does not auto-re-share withdrawn materials; unsupported raw SQL conversion refuses. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Identical rows skip; changed rows/unique identities/config dependencies/missing parent modules refuse, no arbitrary overwrite or ID remap. Resource restore does not auto-re-share withdrawn materials; unsupported raw SQL conversion refuses. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/DevelopmentDataArchiveTest.php](tests/Feature/DevelopmentDataArchiveTest.php)<br>[tests/Feature/DevelopmentDataConcurrencyTest.php](tests/Feature/DevelopmentDataConcurrencyTest.php) |
| Read-only / synthetic QA | **Read-only:** No for selection/confirmation; inspected conflict summary only can be viewed without business mutation.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later isolated approved archive restores missing exact QA graph/private hashes; repeat skips without duplicate payment/debit, then changed/unique/parent/dependency conflicts deny with complete rollback. |
| Dependent / related capability IDs | [SA-095](#sa-095), [SA-094](#sa-094), [SA-088](#sa-088), [SA-038](#sa-038), [SA-052](#sa-052) |
| Production cross-check | Disabled; no portable restore performed. |
| Current source evidence | [app/Domains/System/Services/DevelopmentDataImportService.php](app/Domains/System/Services/DevelopmentDataImportService.php)<br>[app/Domains/System/Services/DevelopmentToolsService.php](app/Domains/System/Services/DevelopmentToolsService.php)<br>[app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php) |
| QA needs | STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, TEACHING-DATA, RESOURCE-DATA, ANALYTICS-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW, DESTRUCTIVE-EXECUTION |

### SA-097

**Owner operation summary, private hash-verified download and manual retention**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Operation history/recovery. Owner operation summary, private hash-verified download and manual retention. Owner-only operation 404 for foreign ID; download requires completed private export path/payload hash and current feature flag. |
| UI / routes | Development & Launch latest operations/completion<br>`GET\|HEAD /admin/development-tools` — `admin.development-tools.index`<br>`GET\|HEAD /admin/development-tools/operations/{operation}` — `admin.development-tools.show`<br>`GET\|HEAD /admin/development-tools/operations/{operation}/download` — `admin.development-tools.download` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Latest 20 owner operation records and completed archive identity/hash/status/counts/reconciliation. |
| Data created / changed | None for reading existing completion/download; operation/inspection/recovery artifacts deliberately retained until reviewed manual cleanup. |
| Business rules | Owner-only operation 404 for foreign ID; download requires completed private export path/payload hash and current feature flag. Protected full raw recovery has no portable download route and may contain credential state. At most 20 unexpired previews per actor; no automatic artifact cleanup scheduler or reset of IDs. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | Completed owner-only private ZIP/no-store/no-referrer/nosniff; raw protected recovery excluded. |
| Edges / negative cases | Owner-only operation 404 for foreign ID; download requires completed private export path/payload hash and current feature flag. Protected full raw recovery has no portable download route and may contain credential state. At most 20 unexpired previews per actor; no automatic artifact cleanup scheduler or reset of IDs. |
| Background / manual behavior | **Background:** Manual retention review; no new scheduled cleanup.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/DevelopmentDataArchiveTest.php](tests/Feature/DevelopmentDataArchiveTest.php)<br>[tests/Feature/DevelopmentLaunchDataToolsTest.php](tests/Feature/DevelopmentLaunchDataToolsTest.php) |
| Read-only / synthetic QA | **Read-only:** Yes only for existing authorized completion/download under separately enabled flag; current disabled route denies.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later QA owner/foreign actor/completed/pending/wrong-hash/wrong-path operations verify private binary download and safe summary; preserve recovery and record actual browser completion. |
| Dependent / related capability IDs | [SA-088](#sa-088), [SA-094](#sa-094), [SA-095](#sa-095), [SA-096](#sa-096) |
| Production cross-check | Disabled; no operation history or archive produced; completed ZIP browser QA remains deferred. |
| Current source evidence | [app/Http/Controllers/Admin/DevelopmentToolsController.php](app/Http/Controllers/Admin/DevelopmentToolsController.php)<br>[app/Domains/System/Models/DevelopmentDataOperation.php](app/Domains/System/Models/DevelopmentDataOperation.php) |
| QA needs | READ-ONLY, RESOURCE-DATA, SECURITY/PERMISSION |

### SA-098

**Trusted-console explicit legacy entitlement inventory/classification**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Historical typed review. Trusted-console explicit legacy entitlement inventory/classification. Never infer rights from name/price/minutes. |
| UI / routes | No web button; existing entitlements:review command under trusted server access<br>`php artisan entitlements:review` (trusted console; no web route) |
| Prerequisites | Separately authorized trusted server console and reviewed legacy manifest; ordinary web session does not authorize --apply. |
| Data read | Legacy purchase/current ledger/linked Booking fact fingerprint, active reviewed type/catalog and prior mapping review. |
| Data created / changed | Dry-run none; explicitly approved --apply creates allocation/review and changes provenance fields only, preserving amounts/quantities/timestamps/IDs. |
| Business rules | Never infer rights from name/price/minutes. Manifest requires purchase/fingerprint/offering/type/reviewer/evidence, coherent grants/nonnegative balance/exact restorations and explicit null-expiry validity terms. Changed/different/replayed review and owner mismatch refuse; mixed allocation remapping is not arbitrary import. It is a trusted-console capability, not Super Admin web access or automatic Data Quality repair. |
| Configuration | Original legacy facts and active EntitlementType; preserve actual compiled MariaDB typed guards/constraints. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Trusted server/operator access; no HTTP route or automatic owner-role bypass; --apply requires explicit reviewed manifest. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Never infer rights from name/price/minutes. Changed/different/replayed review and owner mismatch refuse; mixed allocation remapping is not arbitrary import. It is a trusted-console capability, not Super Admin web access or automatic Data Quality repair. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/TypedEntitlementsTest.php](tests/Feature/TypedEntitlementsTest.php)<br>[tests/Feature/MigrationACompatibilityTest.php](tests/Feature/MigrationACompatibilityTest.php) |
| Read-only / synthetic QA | **Read-only:** Yes for inventory/preview; --apply is a separate state-changing reviewed action.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later isolated valid legacy QA purchase preview/apply/replay preserves all historical facts and canonical type; reject stale/ambiguous/conflicting manifest without disabled constraints. Historical MigrationA test is separate intermediate-schema evidence, not final-schema acceptance. |
| Dependent / related capability IDs | [SA-038](#sa-038), [SA-083](#sa-083), [SA-027](#sa-027) |
| Production cross-check | Current legacy purchases zero; command not run/applied during inventory. |
| Current source evidence | [app/Console/Commands/ReviewEntitlementMapping.php](app/Console/Commands/ReviewEntitlementMapping.php)<br>[app/Domains/Students/Services/EntitlementMappingService.php](app/Domains/Students/Services/EntitlementMappingService.php) |
| QA needs | READ-ONLY, ENTITLEMENT-DATA, FINANCIAL-DATA, BOOKING-DATA, SECURITY/PERMISSION |

### SA-099

**Enabled/default payment methods and immutable receipt labels**

| Field | Current capability |
|---|---|
| Identity / purpose | **Audience:** SUPER ADMIN. **Domain:** Payment method configuration. Enabled/default payment methods and immutable receipt labels. Keep an enabled default/at least one active method when changing defaults; disabled method cannot receive new payment. |
| UI / routes | Payment Methods<br>`GET\|HEAD /admin/payment-methods` — `admin.payment-methods.index`<br>`POST /admin/payment-methods` — `admin.payment-methods.store`<br>`PUT /admin/payment-methods/{paymentMethod}` — `admin.payment-methods.update` |
| Prerequisites | Active signed-in Super Admin; current MFA session proof when enabled. |
| Data read | Configured PaymentMethods and active/default order; historical immutable payment method snapshots. |
| Data created / changed | Validated unique name/sort/active/default configuration and safe audit; no old PaymentRecord rewrite. |
| Business rules | Keep an enabled default/at least one active method when changing defaults; disabled method cannot receive new payment. Historical payment method text and genuine reference remain immutable after rename/disable. No gateway credentials or automatic transaction execution configured by this screen. |
| Configuration | Existing business/content settings; no new configuration applied by this inventory. |
| Timezone | Canonical instants UTC; operational/report date filters use Business Timezone; personal 12/24-hour presentation does not change stored instants. |
| Authorization / privacy | Web guard, active account and route/policy authorization; current Super Admin can act. Mutations require normal CSRF; privileged records are not public. Route role allowlists (policies may narrow individual actions): `super_admin,admin`. |
| Filters / search | None beyond the UI selection described. |
| CSV / XLSX / other export | None. |
| Edges / negative cases | Keep an enabled default/at least one active method when changing defaults; disabled method cannot receive new payment. No gateway credentials or automatic transaction execution configured by this screen. |
| Background / manual behavior | **Background:** No feature-specific background worker.<br>**Manual/on-demand:** User request / on-demand. |
| Existing automated tests | [tests/Feature/BusinessOperationsControlsTest.php](tests/Feature/BusinessOperationsControlsTest.php)<br>[tests/Feature/StudentLedgerTest.php](tests/Feature/StudentLedgerTest.php)<br>[tests/Feature/BusinessLifecycleDataQualityTest.php](tests/Feature/BusinessLifecycleDataQualityTest.php) |
| Read-only / synthetic QA | **Read-only:** Partial: inspect page/configuration only; mutation scenario needs separate authorization/QA.<br>**Synthetic data required for complete scenario:** Yes |
| Recommended acceptance scenario | Later dedicated QA method create/rename/disable/default validation checks new receipts use current method while prior printed/CSV/XLSX snapshot retains original text; restore real defaults. |
| Dependent / related capability IDs | [SA-029](#sa-029), [SA-035](#sa-035), [STU-025](#stu-025) |
| Production cross-check | Current production page/index cross-checked where navigable; entity-dependent mutations are source/test supported and not executed. |
| Current source evidence | [app/Http/Controllers/Admin/PaymentMethodController.php](app/Http/Controllers/Admin/PaymentMethodController.php) |
| QA needs | CONFIGURATION-TOGGLE, FINANCIAL-DATA, SECURITY/PERMISSION |

## Recommended Synthetic Production QA Cohort

**Design only — no cohort created.** The next dataset prompt should approve an exact manifest, identity markers, state transitions, safe mail/Telegram behavior and cleanup/recovery before writes. Six purpose-built Students are a small practical starting cohort; reuse each coherent history across related IDs rather than create a Student per screen. Some incompatible cash/booking lifecycle states are tested in ordered phases, not falsely combined in one current balance.

| Proposed Student | Coherent state / coverage | Required separation |
|---|---|---|
| QA-A: new teaching learner | Verified login/profile, one ended-confirmed or completed direct lesson, one private passive PDF plus existing Resource reference, shared and unshared homework, a small plan/milestone, visible/error/tag and staff-only preparation, Educational Notes, eligible follow-up Form and completed-lesson feedback. STU-001–004, STU-011–023; SA-052–060, SA-070–071. | Start empty to prove empty/next-action state before adding teaching; preserve private/unshared items and no fabricated financial purchase. |
| QA-B: funded financial learner | Explicit one_hour and two_hour purchases/allocations; small real-format synthetic payment records and reference snapshots; due/partial/paid/overpayment/refund installment states in documented phases; upcoming and ended package lessons with original debit provenance. STU-005–006, STU-009–010, STU-024–025; SA-026–038, SA-039–043, SA-048, SA-099. | Each allocation and cash record has a named purpose. Expected installments never create payments. Use a separate small purchase for a second currency; never aggregate currencies as one balance. A final-unit competition is a bounded later isolated race scenario. |
| QA-C: compatibility and historical learner | One compatible allocation and an incompatible/expired second type, plus a valid pending/unclassified legacy purchase if permitted by the reviewed fixture path. Operational inactive/archive/restored history. STU-005–006/009; SA-025/027/038/083/098. | No conversion from price/minutes. Unique/FK/typed guards stay enabled. Console mapping apply and invalid-state fixtures belong to separately approved isolated checks, not arbitrary production row tampering. |
| QA-D: planning and foreign-owner peer | Direct/free lesson, narrow holiday, open waitlist interest, a two-occurrence manual recurring plan, own/foreign workspace and form access denials; a clearly identified DST gap/fold case under a dedicated safe scheduling window. STU-026–028; SA-048–051 and ownership checks. | Narrow future dates; no owner calendar/meeting-room changes without a separate reversible configuration window. Another Student's objects stay inaccessible. A plan state change does not silently cancel existing lessons. |
| QA-E and QA-F: identity/privacy pair | Minimal disposable valid identities with a small explicitly synthetic overlap/duplicate signal and one safe dependent history to test Contact/Student merge, operational cleanup and anonymization. SA-020–024/083 plus ledger/workspace owner integrity. | These are terminal test identities, used after the reusable cohort is verified. If normal identity resolution prevents a duplicate, use valid Contact signals or isolated legacy fixtures; never disable identity uniqueness/constraints to force a production duplicate. |

Supporting objects should be few and tagged: two synthetic independent Contacts/Resource leads; one visible and one hidden Resource in at most two QA categories; one owned public Media file only if needed; one passive private PDF reused through authorized references; one small draft/published Blog with a renamed slug; and one versioned pre-booking Form plus one follow-up Form with conditional answers/autosave. Reuse the existing Game for read-only play where sufficient; create a tagged available/coming-soon QA Game only if the later scope needs those state transitions. CMS publication, translation/revision and Resource gates must not turn synthetic material into unmarked public content.

Permission QA needs dedicated accounts: a QA Super Admin with its own MFA/recovery state, a second QA Super Admin for owner-isolation checks where necessary, one Admin and one Assistant. Student cross-owner checks reuse QA-A/QA-D. Never replace the real owner's password, TOTP, recovery codes, email, name or role for testing. Authentication recovery/enrollment and temporary access require the later prompt's precise approval.

Analytics needs a separately approved bounded tagged observation set: two known countries plus Unknown, completed/abandoned booking steps, semantic social links with repeated event UUIDs, section attention, campaign dimensions, bot/internal exclusion and one complete business-day durable rollup. Use actual approved events or an isolated test fixture path, never invented historical production visits. Keep rollup timezone and raw-retention boundaries explicit; period-distinct visitors after pruning remain unavailable. Maintenance/announcement, goal exclusions and scheduler/worker assertions are separate reversible configuration or operational windows, not implicit dataset writes.

Meeting/payment-method/configuration checks use dedicated harmless HTTPS room references and QA methods only after their configuration scope is approved; snapshots must survive later renames and preference changes. No real payment, outbound student mail, WhatsApp, Telegram test, reminder, command, poll or digest is triggered merely to build this cohort. The current Log mail and existing outbound Telegram configuration stay authoritative until the owner explicitly approves a later test destination/transport scope.

**Execution order for the later prompts:** verify empty/read-only baseline; create exact approved QA identities and small shared definitions; exercise teaching/forms; then grant typed rights and add clearly synthetic cash/installment histories; run bounded booking/planning/lifecycle cases; validate reports/exports and ownership; perform merge/privacy on QA-E/F last. Verify every phase with its named canonical rows and unchanged unrelated data. Preserve a manifest of IDs, source ledger/payment provenance, expected amounts/statuses and owned file hashes; label all records so cleanup does not guess ownership. A second QA account/booking is reused for negative ownership cases.

**Destructive isolation:** SA-088–096 and any whole-scope reset/restore scenario require a separately approved disposable complete clone or explicitly disposable full scope, compatible private paths/sessions and a verified protected recovery snapshot. A small synthetic cohort mixed into real production is not sufficient authorization for All Students, financial, analytics, Resource or managed-backup resets. Preview does not authorize execution; archive upload does not authorize restore. Check restore-missing/skip-identical/refuse-differences and browser ZIP completion in that later isolated scope. No resets, archive creation/upload, native restore, portable restore or tool enablement were performed here.

## QA classification by permanent ID

Each row classifies the whole capability; the individual entry states which portion can currently be read-only and which later acceptance case needs data or a configuration gate.

| ID | Audience | Feature | QA needs | Synthetic |
|---|---|---|---|---|
| [PUB-001](#pub-001) | PUBLIC-FACING | Home and configurable landing sections | READ-ONLY, PUBLIC-INTERACTION | No |
| [PUB-002](#pub-002) | PUBLIC-FACING | Public navigation, footer and responsive menu | READ-ONLY, PUBLIC-INTERACTION | No |
| [PUB-003](#pub-003) | PUBLIC-FACING | English, French and German route/translation presentation | READ-ONLY, PUBLIC-INTERACTION | No |
| [PUB-004](#pub-004) | PUBLIC-FACING | About, FAQ, legal and published custom pages | READ-ONLY, PUBLIC-INTERACTION | Yes |
| [PUB-005](#pub-005) | PUBLIC-FACING | Coaching-track and lesson pricing presentation | READ-ONLY, PUBLIC-INTERACTION | No |
| [PUB-006](#pub-006) | PUBLIC-FACING | Choose an active public lesson format | READ-ONLY, PUBLIC-INTERACTION | No |
| [PUB-007](#pub-007) | PUBLIC-FACING | Searchable timezone selection, flags and local slot labels | READ-ONLY, PUBLIC-INTERACTION | No |
| [PUB-008](#pub-008) | PUBLIC-FACING | Customer-calendar dates and authoritative available slots | READ-ONLY, PUBLIC-INTERACTION | Yes |
| [PUB-009](#pub-009) | PUBLIC-FACING | Authenticated temporary reservation holds | PUBLIC-INTERACTION, BOOKING-DATA, SECURITY/PERMISSION | Yes |
| [PUB-010](#pub-010) | PUBLIC-FACING | Identity details and current pre-booking questionnaire | PUBLIC-INTERACTION, STUDENT-DATA, BOOKING-DATA | Yes |
| [PUB-011](#pub-011) | PUBLIC-FACING | Review and atomically confirm a direct booking | PUBLIC-INTERACTION, STUDENT-DATA, BOOKING-DATA, SECURITY/PERMISSION | Yes |
| [PUB-012](#pub-012) | PUBLIC-FACING | Token-protected confirmation, calendar download and contact-to-reschedule instruction | READ-ONLY, BOOKING-DATA, SECURITY/PERMISSION | Yes |
| [PUB-013](#pub-013) | PUBLIC-FACING | Customer cancellation through a confirmation token | PUBLIC-INTERACTION, BOOKING-DATA, ENTITLEMENT-DATA, SECURITY/PERMISSION | Yes |
| [PUB-014](#pub-014) | PUBLIC-FACING | Published Resource Library and translated detail pages | READ-ONLY, PUBLIC-INTERACTION | Yes |
| [PUB-015](#pub-015) | PUBLIC-FACING | Immediate gated Resource request and single-use access grant | PUBLIC-INTERACTION, RESOURCE-DATA, STUDENT-DATA, ANALYTICS-DATA, SECURITY/PERMISSION | Yes |
| [PUB-016](#pub-016) | PUBLIC-FACING | Authorized private Resource file download or configured external redirect | PUBLIC-INTERACTION, RESOURCE-DATA, ANALYTICS-DATA, SECURITY/PERMISSION | Yes |
| [PUB-017](#pub-017) | PUBLIC-FACING | Published articles, sanitized reading and old-slug redirects | READ-ONLY, PUBLIC-INTERACTION, RESOURCE-DATA | Yes |
| [PUB-018](#pub-018) | PUBLIC-FACING | Available/coming-soon learning game catalog and playable/linked content | PUBLIC-INTERACTION, ANALYTICS-DATA | Yes |
| [PUB-019](#pub-019) | PUBLIC-FACING | Configured native social-channel links including Reddit | READ-ONLY, PUBLIC-INTERACTION | No |
| [PUB-020](#pub-020) | PUBLIC-FACING | Floating and footer WhatsApp entry | READ-ONLY, PUBLIC-INTERACTION | No |
| [PUB-021](#pub-021) | PUBLIC-FACING | Emergency announcement rendering and dismissal | PUBLIC-INTERACTION, CONFIGURATION-TOGGLE | No |
| [PUB-022](#pub-022) | PUBLIC-FACING | Application Maintenance Mode public response | READ-ONLY, CONFIGURATION-TOGGLE, ANALYTICS-DATA | No |
| [PUB-023](#pub-023) | PUBLIC-FACING | Live visitors, prior-month traffic and collective practice counters | READ-ONLY, ANALYTICS-DATA | Yes |
| [PUB-024](#pub-024) | PUBLIC-FACING | First-party visitor, attention, attribution and semantic interaction tracking | PUBLIC-INTERACTION, ANALYTICS-DATA, SECURITY/PERMISSION | Yes |
| [PUB-025](#pub-025) | PUBLIC-FACING | Public Student and staff authentication entry pages | READ-ONLY, SECURITY/PERMISSION | No |
| [PUB-026](#pub-026) | PUBLIC-FACING | Scheduled promotional bars, modal and inline card | PUBLIC-INTERACTION, RESOURCE-DATA, CONFIGURATION-TOGGLE | Yes |
| [STU-001](#stu-001) | STUDENT PORTAL | Passwordless identity verification, session expiry and sign-out | STUDENT-DATA, SECURITY/PERMISSION | Yes |
| [STU-002](#stu-002) | STUDENT PORTAL | Read recorded profile and verified emails | STUDENT-DATA, SECURITY/PERMISSION | Yes |
| [STU-003](#stu-003) | STUDENT PORTAL | Add a verified secondary authentication email | STUDENT-DATA, SECURITY/PERMISSION | Yes |
| [STU-004](#stu-004) | STUDENT PORTAL | Prioritized next action and empty-state dashboard | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes |
| [STU-005](#stu-005) | STUDENT PORTAL | Owned packages and separate typed credit balances | STUDENT-DATA, ENTITLEMENT-DATA, SECURITY/PERMISSION | Yes |
| [STU-006](#stu-006) | STUDENT PORTAL | Allocation-specific low-credit and expiry notices | STUDENT-DATA, ENTITLEMENT-DATA | Yes |
| [STU-007](#stu-007) | STUDENT PORTAL | Owned upcoming sessions and preserved lesson history | STUDENT-DATA, BOOKING-DATA, SECURITY/PERMISSION | Yes |
| [STU-008](#stu-008) | STUDENT PORTAL | Persistent Student timezone preference | STUDENT-DATA, PUBLIC-INTERACTION | Yes |
| [STU-009](#stu-009) | STUDENT PORTAL | Book an eligible package-funded lesson | STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA, SECURITY/PERMISSION | Yes |
| [STU-010](#stu-010) | STUDENT PORTAL | Self-service owned Student rescheduling and review | STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA, SECURITY/PERMISSION | Yes |
| [STU-011](#stu-011) | STUDENT PORTAL | Owned completed or ended-confirmed lesson workspace | STUDENT-DATA, BOOKING-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes |
| [STU-012](#stu-012) | STUDENT PORTAL | Open shared private PDF, Resource, recording or safe link | STUDENT-DATA, BOOKING-DATA, TEACHING-DATA, RESOURCE-DATA, SECURITY/PERMISSION | Yes |
| [STU-013](#stu-013) | STUDENT PORTAL | Shared learning plans and tutor-managed milestones | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes |
| [STU-014](#stu-014) | STUDENT PORTAL | Shared homework, progress/response submission and tutor feedback | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes |
| [STU-015](#stu-015) | STUDENT PORTAL | Open assigned published Resources and persist review state | STUDENT-DATA, TEACHING-DATA, RESOURCE-DATA, SECURITY/PERMISSION | Yes |
| [STU-016](#stu-016) | STUDENT PORTAL | Shared pronunciation, vocabulary and grammar correction patterns | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes |
| [STU-017](#stu-017) | STUDENT PORTAL | Shared normalized teaching tags | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes |
| [STU-018](#stu-018) | STUDENT PORTAL | Fact-based lesson and learning progress summary | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes |
| [STU-019](#stu-019) | STUDENT PORTAL | Read shared notes and create/edit Student-authored notes | STUDENT-DATA, SECURITY/PERMISSION | Yes |
| [STU-020](#stu-020) | STUDENT PORTAL | Assigned published questionnaires, drafts/submission and version history | STUDENT-DATA, SECURITY/PERMISSION | Yes |
| [STU-021](#stu-021) | STUDENT PORTAL | Form draft autosave with current draft/version identity | STUDENT-DATA, SECURITY/PERMISSION | Yes |
| [STU-022](#stu-022) | STUDENT PORTAL | Persistent owner Notification Center and read state | STUDENT-DATA, SECURITY/PERMISSION | Yes |
| [STU-023](#stu-023) | STUDENT PORTAL | Optional rating/comment for own completed lesson | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes |
| [STU-024](#stu-024) | STUDENT PORTAL | Owned purchase statements and CSV/XLSX | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes |
| [STU-025](#stu-025) | STUDENT PORTAL | Read/print an actual owned payment receipt | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes |
| [STU-026](#stu-026) | STUDENT PORTAL | Own holidays/unavailability with cancellation history | STUDENT-DATA, BOOKING-DATA, SECURITY/PERMISSION | Yes |
| [STU-027](#stu-027) | STUDENT PORTAL | Own lesson waitlist interest and withdrawal | STUDENT-DATA, BOOKING-DATA, SECURITY/PERMISSION | Yes |
| [STU-028](#stu-028) | STUDENT PORTAL | Time-gated meeting link reveal | STUDENT-DATA, BOOKING-DATA, SECURITY/PERMISSION | Yes |
| [SA-001](#sa-001) | SUPER ADMIN | Role-aware navigation, desktop collapse, mobile drawer and time-format preference | READ-ONLY, SECURITY/PERMISSION | Yes |
| [SA-002](#sa-002) | SUPER ADMIN | Quick launcher, Student finder and existing cross-domain search | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, RESOURCE-DATA | Yes |
| [SA-003](#sa-003) | SUPER ADMIN | Tutor dashboard and next-lesson business/customer clocks | READ-ONLY, SECURITY/PERMISSION | Yes |
| [SA-004](#sa-004) | SUPER ADMIN | Today, follow-up queues and canonical business summaries | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA | Yes |
| [SA-005](#sa-005) | SUPER ADMIN | Create, search, edit and soft-delete Staff Notes | READ-ONLY, SECURITY/PERMISSION | Yes |
| [SA-006](#sa-006) | SUPER ADMIN | Shared operational pins with actor/time | READ-ONLY, SECURITY/PERMISSION | Yes |
| [SA-007](#sa-007) | SUPER ADMIN | Personal pins, favorites and collections | READ-ONLY, SECURITY/PERMISSION | Yes |
| [SA-008](#sa-008) | SUPER ADMIN | Lightweight assigned tasks and status lifecycle | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA | Yes |
| [SA-009](#sa-009) | SUPER ADMIN | Save, apply, replace-by-name and remove personal filter views | READ-ONLY, SECURITY/PERMISSION | Yes |
| [SA-010](#sa-010) | SUPER ADMIN | Recently viewed Student, Booking/lesson and Contact shortcuts | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA | Yes |
| [SA-011](#sa-011) | SUPER ADMIN | Prominent operational alerts and resolved/archived history | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA | Yes |
| [SA-012](#sa-012) | SUPER ADMIN | Staff notification inbox and read/delete state | READ-ONLY, SECURITY/PERMISSION | Yes |
| [SA-013](#sa-013) | SUPER ADMIN | Password sign-in, staged MFA proof and staff logout | READ-ONLY, SECURITY/PERMISSION | Yes |
| [SA-014](#sa-014) | SUPER ADMIN | Staff reset-link request and password reset without removing MFA | READ-ONLY, SECURITY/PERMISSION | Yes |
| [SA-015](#sa-015) | SUPER ADMIN | Optional Super Admin TOTP enrollment and confirmation | READ-ONLY, SECURITY/PERMISSION | Yes |
| [SA-016](#sa-016) | SUPER ADMIN | Strong recovery-code regeneration, authenticator replacement and disable | READ-ONLY, SECURITY/PERMISSION | Yes |
| [SA-017](#sa-017) | SUPER ADMIN | Create/update/soft-delete staff accounts and roles | READ-ONLY, SECURITY/PERMISSION | Yes |
| [SA-018](#sa-018) | SUPER ADMIN | Suspend and restore Student or staff access with reason | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA | Yes |
| [SA-019](#sa-019) | SUPER ADMIN | Canonical roster, filtered overview, detail and CSV/XLSX | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, ENTITLEMENT-DATA | Yes |
| [SA-020](#sa-020) | SUPER ADMIN | Update/verify recorded identity and contextual contact actions | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA | Yes |
| [SA-021](#sa-021) | SUPER ADMIN | Contact directory, acquisition history, lead inquiry view and staff notes | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, RESOURCE-DATA, ANALYTICS-DATA | Yes |
| [SA-022](#sa-022) | SUPER ADMIN | Review and explicitly merge suspected duplicate Contacts | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, RESOURCE-DATA | Yes |
| [SA-023](#sa-023) | SUPER ADMIN | Explicit canonical Student merge preserving facts/provenance | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, TEACHING-DATA | Yes |
| [SA-024](#sa-024) | SUPER ADMIN | Student privacy anonymization and owned-content redaction | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA, TEACHING-DATA, RESOURCE-DATA, FINANCIAL-DATA, DESTRUCTIVE-EXECUTION | Yes |
| [SA-025](#sa-025) | SUPER ADMIN | Active/inactive/archive lifecycle with explicit history | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA | Yes |
| [SA-026](#sa-026) | SUPER ADMIN | Student selection, filtered transactions and financial exports | READ-ONLY, SECURITY/PERMISSION, FINANCIAL-DATA, ENTITLEMENT-DATA | Yes |
| [SA-027](#sa-027) | SUPER ADMIN | Create canonical preset or classified custom purchases and grants | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes |
| [SA-028](#sa-028) | SUPER ADMIN | Reviewed discount and 48-hour diagnostic-credit eligibility | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes |
| [SA-029](#sa-029) | SUPER ADMIN | Record positive manual payments and settlement | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes |
| [SA-030](#sa-030) | SUPER ADMIN | Record bounded refunds with optional explicit typed forfeiture | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes |
| [SA-031](#sa-031) | SUPER ADMIN | Explicit typed courtesy adjustments and historical balance projection | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes |
| [SA-032](#sa-032) | SUPER ADMIN | Extend an existing expiry with reason and stale-date protection | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes |
| [SA-033](#sa-033) | SUPER ADMIN | Immutable full-price installment schedule, FIFO projection and voided history | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes |
| [SA-034](#sa-034) | SUPER ADMIN | Current balances, overdue installments, partial payments and overpayments | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes |
| [SA-035](#sa-035) | SUPER ADMIN | Staff package statements, actual receipts, print and exports | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes |
| [SA-036](#sa-036) | SUPER ADMIN | Create a new linked purchase/grant without rewriting previous history | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes |
| [SA-037](#sa-037) | SUPER ADMIN | Descriptive cash, dues, typed usage, expiry and renewal/repeat indicators | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION, BOOKING-DATA | Yes |
| [SA-038](#sa-038) | SUPER ADMIN | Read-only billing integrity and typed provenance diagnostics | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION, BOOKING-DATA | Yes |
| [SA-039](#sa-039) | SUPER ADMIN | Business-time booking list, day/week/month calendar, detail and notes | READ-ONLY, SECURITY/PERMISSION, BOOKING-DATA | Yes |
| [SA-040](#sa-040) | SUPER ADMIN | Manual direct booking with optional canonical Student preselection | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA | Yes |
| [SA-041](#sa-041) | SUPER ADMIN | Staff reschedule with canonical slot and immutable typed funding | READ-ONLY, SECURITY/PERMISSION, BOOKING-DATA, ENTITLEMENT-DATA | Yes |
| [SA-042](#sa-042) | SUPER ADMIN | Mark completed or no-show without fabricating payment | READ-ONLY, SECURITY/PERMISSION, BOOKING-DATA, ENTITLEMENT-DATA | Yes |
| [SA-043](#sa-043) | SUPER ADMIN | Staff cancellation and separate immutable credit consequence | READ-ONLY, SECURITY/PERMISSION, BOOKING-DATA, ENTITLEMENT-DATA | Yes |
| [SA-044](#sa-044) | SUPER ADMIN | SessionType duration/price/funding and exact entitlement requirement | READ-ONLY, SECURITY/PERMISSION, CONFIGURATION-TOGGLE, ENTITLEMENT-DATA | Yes |
| [SA-045](#sa-045) | SUPER ADMIN | Weekly tutor availability, duration/buffer/notice/horizon overrides | READ-ONLY, SECURITY/PERMISSION, CONFIGURATION-TOGGLE, BOOKING-DATA | Yes |
| [SA-046](#sa-046) | SUPER ADMIN | Date-specific tutor exceptions/blocked days with booked-history warning | READ-ONLY, SECURITY/PERMISSION, CONFIGURATION-TOGGLE, BOOKING-DATA | Yes |
| [SA-047](#sa-047) | SUPER ADMIN | Cancellation/no-show policy and immutable new-booking snapshots | READ-ONLY, SECURITY/PERMISSION, CONFIGURATION-TOGGLE, BOOKING-DATA, ENTITLEMENT-DATA | Yes |
| [SA-048](#sa-048) | SUPER ADMIN | Explicit recurring plans, bounded manual generation and safe blocked reasons | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA | Yes |
| [SA-049](#sa-049) | SUPER ADMIN | Review/contact/close/withdraw lesson interest manually | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA | Yes |
| [SA-050](#sa-050) | SUPER ADMIN | Staff manage Student unavailability separately from tutor calendar | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA | Yes |
| [SA-051](#sa-051) | SUPER ADMIN | Provider/room pools, preference, explicit assignment and reconciliation | READ-ONLY, SECURITY/PERMISSION, BOOKING-DATA, CONFIGURATION-TOGGLE | Yes |
| [SA-052](#sa-052) | SUPER ADMIN | Staff manage private PDF, Resource, recording/link materials and sharing | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION, BOOKING-DATA, RESOURCE-DATA | Yes |
| [SA-053](#sa-053) | SUPER ADMIN | Author shared/private homework and tutor completion/feedback | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION, RESOURCE-DATA | Yes |
| [SA-054](#sa-054) | SUPER ADMIN | Learning plans and editable milestones with completion facts | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes |
| [SA-055](#sa-055) | SUPER ADMIN | Staff-only Student/lesson preparation | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes |
| [SA-056](#sa-056) | SUPER ADMIN | Reference existing Resources with instructions, sharing and review reset | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION, RESOURCE-DATA | Yes |
| [SA-057](#sa-057) | SUPER ADMIN | Maintain pronunciation/vocabulary/grammar error log | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes |
| [SA-058](#sa-058) | SUPER ADMIN | Normalize and deduplicate Student/lesson tags | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes |
| [SA-059](#sa-059) | SUPER ADMIN | Read private Student lesson ratings/comments | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes |
| [SA-060](#sa-060) | SUPER ADMIN | Staff author/share and Super Admin manage Student Educational Notes | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes |
| [SA-061](#sa-061) | SUPER ADMIN | Library authoring, private payload/cover replacement, drafts and publication | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION, DESTRUCTIVE-EXECUTION | Yes |
| [SA-062](#sa-062) | SUPER ADMIN | Manage category labels, active/order and localization | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes |
| [SA-063](#sa-063) | SUPER ADMIN | Upload/pick public Media with reference-protected removal | RESOURCE-DATA, SECURITY/PERMISSION, DESTRUCTIVE-EXECUTION | Yes |
| [SA-064](#sa-064) | SUPER ADMIN | Draft/live Pages, sanitized rich text, revisions and explicit restoration | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes |
| [SA-065](#sa-065) | SUPER ADMIN | FAQ drafts and native social/Reddit/WhatsApp channel configuration | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes |
| [SA-066](#sa-066) | SUPER ADMIN | French/German draft publication and source-revision reconciliation | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes |
| [SA-067](#sa-067) | SUPER ADMIN | Blog draft/publication, sanitized Markdown, optimistic edits and old-slug history | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes |
| [SA-068](#sa-068) | SUPER ADMIN | Game catalog status, safe target, draft/publish and localized presentation | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes |
| [SA-069](#sa-069) | SUPER ADMIN | Manage scheduled promotion bars, modal/cards and countdown | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes |
| [SA-070](#sa-070) | SUPER ADMIN | Versioned questionnaire authoring, conditions, assignment triggers and publication | STUDENT-DATA, RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes |
| [SA-071](#sa-071) | SUPER ADMIN | Versioned submissions, permission-filtered answers and tabular export | READ-ONLY, STUDENT-DATA, SECURITY/PERMISSION | Yes |
| [SA-072](#sa-072) | SUPER ADMIN | Live Pulse, primary/resource/game funnels, goals and acquisition reports | READ-ONLY, ANALYTICS-DATA, SECURITY/PERMISSION | Yes |
| [SA-073](#sa-073) | SUPER ADMIN | Country traffic, conversion, flags and sorting/export | READ-ONLY, ANALYTICS-DATA, SECURITY/PERMISSION | Yes |
| [SA-074](#sa-074) | SUPER ADMIN | Section views, dwell, bounce/drop-off and filtering/export | READ-ONLY, ANALYTICS-DATA, SECURITY/PERMISSION | Yes |
| [SA-075](#sa-075) | SUPER ADMIN | Traffic, booking, Resource, social, event and campaign reports/exports | READ-ONLY, ANALYTICS-DATA, BOOKING-DATA, RESOURCE-DATA, SECURITY/PERMISSION | Yes |
| [SA-076](#sa-076) | SUPER ADMIN | Friendly maintenance visitor/session/country details and export | READ-ONLY, ANALYTICS-DATA, SECURITY/PERMISSION | Yes |
| [SA-077](#sa-077) | SUPER ADMIN | Branding, public copy, business timezone, booking defaults, counters and privacy goals | READ-ONLY, CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes |
| [SA-078](#sa-078) | SUPER ADMIN | Configure emergency announcement audience, localized text, window, severity and CTA | CONFIGURATION-TOGGLE, SECURITY/PERMISSION | No |
| [SA-079](#sa-079) | SUPER ADMIN | Application Maintenance Mode switch and message | CONFIGURATION-TOGGLE, SECURITY/PERMISSION, ANALYTICS-DATA | No |
| [SA-080](#sa-080) | SUPER ADMIN | Read environment, clocks, storage and operational heartbeat health | READ-ONLY, SECURITY/PERMISSION | No |
| [SA-081](#sa-081) | SUPER ADMIN | Create/list/download/delete positively managed private backups | RESOURCE-DATA, SECURITY/PERMISSION, DESTRUCTIVE-EXECUTION | Yes |
| [SA-082](#sa-082) | SUPER ADMIN | Safe role-restricted audit viewer and readable allowlisted diffs | READ-ONLY, SECURITY/PERMISSION | Yes |
| [SA-083](#sa-083) | SUPER ADMIN | Read-only identity/purchase/configuration/relationship risks and bounded duplicate candidates | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, ENTITLEMENT-DATA | Yes |
| [SA-084](#sa-084) | SUPER ADMIN | Existing bot/destination/global switches and controlled read-command settings | CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes |
| [SA-085](#sa-085) | SUPER ADMIN | Rule templates, booking reminders, event alerts and on-demand digest requests | BOOKING-DATA, ANALYTICS-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes |
| [SA-086](#sa-086) | SUPER ADMIN | Safe delivery history, enabled-destination test and one-off verified status pathway | READ-ONLY, SECURITY/PERMISSION | Yes |
| [SA-087](#sa-087) | SUPER ADMIN | Permanent feature-off boundary and production compatibility | READ-ONLY, SECURITY/PERMISSION, CONFIGURATION-TOGGLE | Yes |
| [SA-088](#sa-088) | SUPER ADMIN | Immutable previews, password/factor/token confirmation, locks and safe recovery | SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW, DESTRUCTIVE-EXECUTION | Yes |
| [SA-089](#sa-089) | SUPER ADMIN | Reviewed analytics-fact reset with retained business boundaries | ANALYTICS-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW, DESTRUCTIVE-EXECUTION | Yes |
| [SA-090](#sa-090) | SUPER ADMIN | Reviewed all-Student ownership graph reset and auth cleanup | STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, TEACHING-DATA, RESOURCE-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW, DESTRUCTIVE-EXECUTION | Yes |
| [SA-091](#sa-091) | SUPER ADMIN | Coherent financial test-history reset with required booking dependents | FINANCIAL-DATA, ENTITLEMENT-DATA, BOOKING-DATA, TEACHING-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW, DESTRUCTIVE-EXECUTION | Yes |
| [SA-092](#sa-092) | SUPER ADMIN | Immutable selected Resources/Categories/history/files reset with safe detachment | RESOURCE-DATA, TEACHING-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW, DESTRUCTIVE-EXECUTION | Yes |
| [SA-093](#sa-093) | SUPER ADMIN | Reset only proven managed local snapshots and last-result lifecycle | RESOURCE-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW, DESTRUCTIVE-EXECUTION | Yes |
| [SA-094](#sa-094) | SUPER ADMIN | Export Everything/Custom bounded private archive with module/filter closure | STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, TEACHING-DATA, RESOURCE-DATA, ANALYTICS-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW | Yes |
| [SA-095](#sa-095) | SUPER ADMIN | Upload inspection-only compatible portable archive and conflict preview | RESOURCE-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW | Yes |
| [SA-096](#sa-096) | SUPER ADMIN | Restore missing/skip identical/refuse differences with stable provenance | STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, TEACHING-DATA, RESOURCE-DATA, ANALYTICS-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW, DESTRUCTIVE-EXECUTION | Yes |
| [SA-097](#sa-097) | SUPER ADMIN | Owner operation summary, private hash-verified download and manual retention | READ-ONLY, RESOURCE-DATA, SECURITY/PERMISSION | Yes |
| [SA-098](#sa-098) | SUPER ADMIN | Trusted-console explicit legacy entitlement inventory/classification | READ-ONLY, ENTITLEMENT-DATA, FINANCIAL-DATA, BOOKING-DATA, SECURITY/PERMISSION | Yes |
| [SA-099](#sa-099) | SUPER ADMIN | Enabled/default payment methods and immutable receipt labels | CONFIGURATION-TOGGLE, FINANCIAL-DATA, SECURITY/PERMISSION | Yes |

## Delivery and safe notification record

This matrix and its tracker checkpoint are documentation only. Normal Git commit/push and remote confirmation follow the consistency/whitespace checks. No application deployment is required for the newer documentation HEAD. Exactly one final owner-authorized notification uses the existing verified production bot/destination and TelegramDeliveryService with a distinct inventory identity. No new integration, credential, recipient, rule, command, polling, schedule or worker configuration is introduced.

Notification: not yet attempted at the initial documentation checkpoint; safe actual outcome is appended after the one final invocation. No token, chat credential or private message payload is stored here.

Historical context (not substitutes for current source): [PRE_LMS_PRODUCTION_DEPLOYMENT_REPORT.md](PRE_LMS_PRODUCTION_DEPLOYMENT_REPORT.md), [REMAINING_FEATURES_IMPLEMENTATION_TRACKER.md](REMAINING_FEATURES_IMPLEMENTATION_TRACKER.md), [STAGE_1_ADMIN_PUBLIC_ANALYTICS_UX_REPORT.md](STAGE_1_ADMIN_PUBLIC_ANALYTICS_UX_REPORT.md), [STAGE_2_STUDENT_TEACHING_EXPERIENCE_REPORT.md](STAGE_2_STUDENT_TEACHING_EXPERIENCE_REPORT.md), [STAGE_3_STAFF_OPERATIONS_PRODUCTIVITY_REPORT.md](STAGE_3_STAFF_OPERATIONS_PRODUCTIVITY_REPORT.md), [STAGE_4_BUSINESS_LIFECYCLE_DATA_QUALITY_REPORT.md](STAGE_4_BUSINESS_LIFECYCLE_DATA_QUALITY_REPORT.md), [STAGE_5_DEVELOPMENT_LAUNCH_DATA_TOOLS_REPORT.md](STAGE_5_DEVELOPMENT_LAUNCH_DATA_TOOLS_REPORT.md), [DEVELOPMENT_DATA_RESET_AND_RESTORE_RUNBOOK.md](DEVELOPMENT_DATA_RESET_AND_RESTORE_RUNBOOK.md). Protected local route/schema/UI evidence and the final safe delivery receipt are kept outside Git; credentials, backups and raw business exports are excluded from this document.
