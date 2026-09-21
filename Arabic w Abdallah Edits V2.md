**EXISTING WEBSITE — FINAL SPECIFICATION \& EXECUTION CONTRACT (VERSION 2.7 — FINAL PRODUCTION RELEASE)**

You are modifying an already-developed, production-quality Egyptian Arabic tutoring website and private administrative platform. The existing modular monolith is stable, self-hosted, and live.

**Core Invariants:**

**Do NOT rebuild the application from scratch.**

**Do NOT rewrite working systems for stylistic preference.**

**Do NOT introduce customer accounts, student dashboards, or automated payment gateways in this phase.**

**Do NOT hard-delete existing business, booking, contact, or session-type records to satisfy new constraints.**

**Do NOT replace the cumulative cohort funnel with country analytics; both systems must coexist as distinct, decoupled analytical dimensions.**

**0. LIVE-APPLICATION SAFETY \& CHANGE SEQUENCING**

Execute changes in the following strict phases on a development or staging environment before any production deployment:

**Phase A — Brand \& Isolated UI Corrections:** Centralize the English identity "Abdallah", resolve mobile admin drawer overlay and accessibility bugs, and configure legacy /book 301 redirects.

**Phase B — Portable Database Migrations:** Apply engine-portable schema updates for detected country codes, daily country aggregation, marketing\_touches, and session\_types safe deactivation without deleting records.

**Phase C — Analytics \& Geolocation Pipeline:** Implement trusted proxy header validation with GeoLite2 fallback, server-authoritative country ingestion semantics, session-country binding, server-side visitor identity verification, and touch deduplication rules.

**Phase D — Timezone \& Booking Engine:** Implement TimezoneDisplayService, reference-instant slot offset formatting, and local SVG flag asset integration.

**Phase E — Pricing \& Policy Localization:** Implement the dedicated PricingController and Blade template, seed reconciled Privacy and Terms CMS records (parent entities in pages, content in page\_translations), and verify draft fallback and stale translation rules.

**Phase F — Funnel Reconciliation \& Automated Verification:** Execute the automated test suite, verify historical analytics cutover boundaries, and generate the final implementation report.

**1. AUDIT \& CODE DISCOVERY DIRECTIVES**

Before modifying code or database schemas, inspect the codebase and document:

**Brand References:** Search config/, lang/[en.json](en.json), Blade layout templates, SEO metadata helpers, and database seeders for legacy tutor names ("Ahmad", "Ahmed").

**Session Types:** Query the session\_types table. Document all active and inactive records, durations, and prices.

**Analytics Pipeline:** Inspect analytics\_events, visitors, sessions, daily\_metrics, visitor\_funnel\_progressions, event listeners, post-commit dispatch hooks, and ingestion middleware.

**Country Geolocation:** Check if an existing country-detection mechanism exists. Inspect trusted proxy CIDR configurations in config/[trustedproxy.php](trustedproxy.php) or reverse-proxy middleware.

**Admin Layout:** Inspect resources/views/layouts/[admin.blade.php](admin.blade.php), [Alpine.js](Alpine.js) data/store bindings, and responsive breakpoints.

**Localization State:** Compare lang/[en.json](en.json), lang/[fr.json](fr.json), and lang/[de.json](de.json). Inspect pages, page\_translations, and entity\_translation\_revisions.

**2. BRAND \& TUTOR IDENTITY: EXCLUSIVELY ENGLISH "ABDALLAH"**

The canonical business and educator identity is **Abdallah**.

**2.1 Scope \& Boundaries**

**Exclusively Latin Script:** Use **"Abdallah"** across all public and administrative interfaces. Do **not** use the Arabic script "عبدالله" for public business branding, page titles, headers, footers, or Open Graph metadata. Arabic script is strictly reserved for Egyptian Arabic educational instructional content (vocabulary, grammar, dialogues).

**Obsolete Name Removal:** Eliminate all tutor branding instances of "Ahmad" and "Ahmed".

**Strict Replacement Boundaries (Zero Educational Corruption):**

**Permitted Targets:** config/\*.php, lang/\*.json, public layout Blade templates (header, footer, navigation, about, hero), Open Graph/meta tags, and admin user display names.

**Prohibited Targets:** Do NOT run an unconstrained global search-and-replace across migrations, learning content tables, or resource markdown fixtures. If an Arabic dialogue or grammar example uses "Ahmed" (أحمد) as a third-person conversational character (e.g., *"Ahmed went to the market"*), leave it untouched.

Do NOT modify unrelated test customer records or historical audit logs.

**2.2 Configuration Source of Truth**

Centralize the identity:

// config/[app.php](app.php) or config/[business.php](business.php) 'tutor\_name' =\> 'Abdallah', 'site\_name' =\> 'Egyptian Arabic with Abdallah', 

Reference this centralized configuration across all layouts and templates.

**3. AUDIENCE GEOLOCATION \& COUNTRY ANALYTICS ARCHITECTURE**

Implement audience country segmentation within the self-hosted first-party analytics system without third-party tracking SaaS and without storing raw IP addresses.

**3.1 Separation of Geographical Concepts**

The platform must strictly distinguish network geography from user-selected timezone geography:

**detected\_country\_code (Network Derived):** The approximate ISO-3166-1 alpha-2 country inferred from the HTTP request or local GeoIP database. Used exclusively for audience analytics and traffic segmentation.

**timezone\_country\_code (Timezone Derived):** The country associated with the user's selected IANA timezone (e.g., America/New\_York \\rightarrow US). Used exclusively for UI flag presentation and slot formatting.

**Actual Residence:** The platform acknowledges that network IP and selected timezone do not establish legal residence; no field may claim to represent verified customer residency.

**3.2 Network Geolocation Architecture \& Proxy Security**

**Trusted Proxy Rule:**

Ingesting country headers (e.g., CF-IPCountry, X-Country-Code) is permitted **only** when the request arrives through an explicitly configured, trusted reverse proxy/CDN CIDR block (e.g., Cloudflare IP ranges in config/[trustedproxy.php](trustedproxy.php)).

If the request does not originate from a trusted proxy, **strip and ignore all client-supplied country headers** to prevent header-spoofing attacks.

**Local GeoIP Primary / Fallback:**

Use the existing country-detection mechanism if one already exists and satisfies these requirements.

If no adequate mechanism exists, implement local GeoIP using the MaxMind GeoLite2-Country database (.mmdb) via the project's compatible PHP library (geoip2/geoip2).

**Storage Path:** Store the database at storage/geoip/[GeoLite2-Country.mmdb](GeoLite2-Country.mmdb).

**Application Resilience:** A missing or unreadable GeoIP database must **never** block application boot, deployments, migrations, or HTTP requests. If lookup fails or the database is unreadable, fall back gracefully to NULL (in relational tables) or 'ZZ' (in reporting aggregates).

**CLI Update Command:** Provide an artisan command (php artisan geoip:update) that downloads and verifies fresh MMDB releases using credentials appropriate to the configured MaxMind distribution mechanism. Never hard-code credentials. The update command must download to a temporary file, verify file integrity, and replace the active database atomically. A failure must leave the existing database untouched and exit with a non-zero CLI status code.

**Licensing \& Attribution:** Adhere to MaxMind terms by documenting license compliance, pruning obsolete database files upon update, and including the required attribution in documentation/credits: *"This product includes GeoLite2 data created by MaxMind, available from *[*https://www.maxmind.com*](https://www.maxmind.com?utm_source=gemini)*."*

**3.3 Visitor, Session, Event \& Booking Country Semantics**

**visitors.detected\_country\_code:** The visitor's acquisition country (the detected country from their first qualifying session). **Immutable** once assigned; does not change if the visitor returns later via VPN or while traveling. If the first qualifying session cannot be resolved, acquisition country remains NULL and must never be rewritten based on subsequent sessions.

**sessions.detected\_country\_code:** The detected country at the start of that specific 30-minute session.

[**analytics\_events.metadata**](analytics_events.metadata)**-\>detected\_country\_code:** Raw analytics events snapshot the normalized uppercase country code (ISO-3166-1 alpha-2 or 'ZZ') determined by server-side ingestion logic, preserving historical context even if sessions are later pruned.

**bookings.detected\_country\_code:** The detected country of the session during which the booking was created. This value is derived and snapshotted strictly by BookingService from the verified session state at booking creation time and is immutable. The booking service must ignore or reject any caller-supplied conflicting country value.

**3.4 Data Schema, Priority Resolution \& Idempotent Aggregation**

**Relational Columns:**

Add detected\_country\_code CHAR(2) NULL to visitors, sessions, and bookings.

Never store raw IP addresses in visitors, sessions, contacts, or bookings (Document A §77).

**Handling Unresolved Countries:**

When geolocation cannot resolve an IP (private ranges, unmapped IPv6, lookup failures), store NULL in relational columns and normalize to 'ZZ' (Unknown / Unresolved) in aggregate reporting tables.

**Event Country Resolution \& Client-Supplied Rejection:**

Country code association is server-authoritative. Ingestion middleware derives the country code from trusted proxy headers or local GeoIP and writes it to server metadata.

Any client-supplied country\_code in public API payloads must be rejected or stripped.

When associating country with an event during aggregation, use this deterministic priority:

The event's server-resolved metadata-\>detected\_country\_code, if valid.

The associated session's detected\_country\_code.

Fallback to 'ZZ' (Unknown / Unresolved).

**Daily Aggregation Table (daily\_country\_metrics):**

This is an **activity-date report**, not an acquisition-cohort report.

**Idempotency Requirement:** Running analytics:aggregate-daily-country --date=YYYY-MM-DD repeatedly must produce the identical row set and values. Implement deterministic deduplication and an idempotent upsert keyed by (metric\_date, country\_code). It must never duplicate totals.

Metrics within a country row represent activity occurring on that calendar date (Africa/Cairo):

unique\_visitors: **Count of distinct non-bot visitors having eligible activity on that Cairo calendar date, grouped by the visitor's immutable acquisition country.**

sessions: Grouped by sessions.detected\_country\_code.

booking\_cta\_clicks: Grouped by event-resolved country (per Section 3.4.3).

bookings\_completed: Grouped by bookings.detected\_country\_code.

resource\_requests: Grouped by event-resolved country.

Portable Schema Structure:

metric\_date (DATE), country\_code (CHAR(2) containing ISO alpha-2 or 'ZZ')

unique\_visitors (INT), sessions (INT), booking\_cta\_clicks (INT), bookings\_completed (INT), resource\_requests (INT)

Unique constraint: UNIQUE(metric\_date, country\_code)

**Admin UI Audience Card ("Audience Activity by Detected Country"):**

Display an **Audience Activity by Detected Country** card in Admin Analytics with Country Flag + Name (with a fallback globe icon for 'ZZ' labeled "Unknown / Unresolved").

Clearly present the dimension of each column in help text or table headers:

Metric ColumnGeographical Dimension**Unique Active Visitors**Visitor acquisition country (visitors.detected\_country\_code)**Sessions**Session-start country (sessions.detected\_country\_code)**CTA Reached**Event/session detected country**Completed Bookings**Booking-creation country (bookings.detected\_country\_code) 

Do NOT display an ambiguous conversion percentage unless explicitly labeled as an activity ratio (Bookings on Date / Active Visitors on Date).

**4. TIMEZONE ENGINE \& SLOT REFERENCE-INSTANT CONTRACT**

Enhance the customer-facing booking engine on /booking (/fr/reservation, /de/buchen) to render available slots and timezone pickers clearly.

**4.1 Visual Format Contract**

All slot date headers, slot selections, and timezone pickers must display according to: 

\\mathbf{\[Flag\]\\;\[City\]\\;(\[Timezone\\;Label\\;/\\;Offset\])} 

*Example (Standard):* \[DE Flag\] Berlin (Europe/Berlin, UTC+2)

*Example (US Multi-Zone):* \[US Flag\] New York (EDT, UTC-4)

*Example (Neutral/UTC):* \[🌐 Globe\] UTC (Coordinated Universal Time)

**4.2 Critical DST \& Reference-Instant Offset Calculation**

**Reference-Instant Rule:** Do NOT calculate timezone offsets using "the current instant" for booking slots. Offset calculations must use the **scheduled start timestamp of the specific slot being rendered**.

*Example:* If a visitor in September (Berlin UTC+2) views slots for January, the slot must display as UTC+1 (CET), not UTC+2 (CEST).

**Standalone Timezone Selector:** When rendering the timezone picker before a specific slot has been selected, calculate the offset using the current instant (now()).

**Offset Formatting Rules:**

Zero offset: Display UTC (never UTC+0 or UTC-0).

Integer offsets: Display UTC+X or UTC-X (e.g., UTC+2, UTC-4).

Fractional offsets: Display UTC+X:YY or UTC-X:YY (e.g., UTC+5:30, UTC+5:45).

Do not use ambiguous, non-standard abbreviations as the sole timezone identifier.

**4.3 Timezone Display Service \& Assets**

Create a centralized TimezoneDisplayService:

**Mapping:** Map IANA timezone identifiers (e.g., Europe/Berlin, America/New\_York) to their canonical ISO-3166-1 alpha-2 country code (timezone\_country\_code) using DateTimeZone::getLocation() or a static lookup table derived from [zone.tab](zone.tab).

**City Extraction:** Use a curated display-name dictionary for common customer zones (e.g., America/New\_York \\rightarrow "New York", America/Indiana/Indianapolis \\rightarrow "Indianapolis", America/Argentina/Buenos\_Aires \\rightarrow "Buenos Aires"), with a deterministic IANA fallback (last path segment with underscores replaced by spaces) when not in the dictionary.

**Windows OS Compatibility:** Do NOT use Unicode emoji flags. Deliver flags as lightweight, local SVG files: /assets/flags/4x3/{timezone\_country\_code}.svg.

**Fallback Handling:** For UTC, GMT, or unmapped regions, return a local globe icon (/assets/flags/4x3/[globe.svg](globe.svg)), city = 'UTC', and country\_code = NULL.

**Accessibility Markup:**\<div class="inline-flex items-center gap-2"\> \<span class="inline-block w-5 h-3.5 shrink-0" aria-hidden="true"\> \<img src="/assets/flags/4x3/[de.svg](de.svg)" alt="" class="w-full h-full object-cover rounded-xs" /\> \</span\> \<span class="sr-only"\>Timezone Country: Germany. Timezone: \</span\> \<span class="font-medium text-slate-900"\>Berlin\</span\> \<span class="text-xs text-slate-500"\>(Europe/Berlin, UTC+1)\</span\> \</div\> 

**4.4 Booking Flow Continuity**

Store customer\_timezone (IANA string) and timezone\_country\_code in the session-backed booking state.

Manually changing the timezone in Step 2 updates all slot calculations and UI headers without altering the visitor's network detected\_country\_code.

Retain the selected timezone and timezone\_country\_code across language switches (/booking \\leftrightarrow /fr/reservation \\leftrightarrow /de/buchen).

**5. BOOKING ENGINE, SESSION TYPES \& OPERATIONAL RESCHEDULING**

**5.1 Single Active Session Type Invariant**

The database table session\_types must contain **exactly one active record**:

title: "Diagnostic \& Learning Roadmap"

duration\_minutes: 60

price: 25.00

currency: 'USD'

active: true

**5.2 Safe Session-Type Synchronization Architecture \& Concurrency Lock**

**Separation of Concerns:** Database structure belongs in migrations. Session-type business synchronization belongs in an idempotent, transactional seeder or command (SyncDiagnosticSessionType) executed post-migration.

**Prohibition on Deletion:** Do NOT execute DELETE statements on session\_types.

**Deterministic Canonical Matching:** Identify the canonical record using an existing stable configuration or business key (identifier = 'diagnostic\_roadmap') when one exists. If no such key exists, use the exact canonical title "Diagnostic \& Learning Roadmap" as the reconciliation criterion. If multiple matching records historically exist, update the earliest created record and deactivate duplicates without deleting them.

**Concurrency Guarantee:** The synchronization operation must serialize concurrent executions sufficiently to guarantee the exactly-one-active invariant. Use an appropriate transaction-level lock, table-level lock, or database mutex supported by the confirmed engine (e.g., DB::transaction() with table locking or advisory locks). The implementation must remain idempotent under concurrent invocation.

**Atomic Synchronization Logic:**

Acquire lock.

Inspect session\_types for the canonical Diagnostic session per the deterministic matching rule.

If absent, insert it (duration\_minutes = 60, price = 25.00, currency = 'USD', active = true).

If present, update its fields and ensure active = true.

Set active = false on all other existing session types.

Preserve all historical primary keys, foreign-key relationships, and prior booking references.

**5.3 Step 1 Auto-Skip Invariant**

Because exactly one active session type exists, /booking must automatically skip the session-selection step and land visitors directly on Step 2 (Date \& Time Selection), satisfying Document A (§28). Do NOT add multi-session packages (Foundation, Fluency) or maintenance tiers to session\_types.

**5.4 Authoritative Operational Rescheduling Rule**

To eliminate policy and technical ambiguity:

**Direct-Contact Model:** Rescheduling requests must be submitted directly to the tutor via WhatsApp, Telegram, or email at least 24 hours prior to class time.

**Administrator Execution:** Rescheduling is executed by the administrator in the private admin platform, updating the appointment instant atomically and writing to booking\_events (Document A §31, §32).

**Token Confirmation Access:** Customers can view their updated appointment date/time by refreshing their secure token confirmation page (/booking/confirmation/{token}). There is no unauthenticated customer self-service rescheduling form.

**6. PRICING ARCHITECTURE, CREDIT ACCOUNTING \& LOCALIZED FUNNEL**

**6.1 Architectural Placement**

Do NOT implement /pricing as a generic markdown record in the pages table.

Implement /pricing via PricingController@index rendering a dedicated, componentized Blade template: resources/views/public/[pricing.blade.php](pricing.blade.php).

Register localized routes:

English: /pricing

French: /fr/tarifs

German: /de/preise

Store all translatable pricing copy, labels, tooltips, and savings numbers in lang/[en.json](en.json), lang/[fr.json](fr.json), and lang/[de.json](de.json) under the "pricing.\*" namespace.

**6.2 Primary Conversion Action \& Analytics**

The primary CTA ("Book Diagnostic Session") must link directly to the localized booking flow (/booking, /fr/reservation, /de/buchen).

The CTA button must dispatch the existing allow-listed analytics event: booking\_cta\_clicked. Do not create unapproved events like pricing\_cta\_clicked.

**Zero Payment Automation:** The page is strictly informational. There is no shopping cart, no Stripe checkout, and no automated PayPal gateway. All packages are billed manually by the tutor.

**6.3 Mathematical Pricing Specification \& Credit Accounting**

The package standard price represents the baseline commitment. When a student completes the $25 diagnostic and enrolls within 48 elapsed hours of completion, the $25 diagnostic fee is credited against the package price on the manual PayPal invoice. Total customer expenditure across diagnostic + package equals the standard package price:

Offer / TrackSpecs \& ValidityStandard PricePackage Invoice Amount After CreditTotal Customer ExpenditureCustomer Effective RatePackage Invoice Equivalent RateSavings Anchor**Diagnostic \& Roadmap** *(Mandatory Entry)*60 mins (Single)**$25**N/A**$25**$25.00/hrN/AIndependent educational value; comprehensive written roadmap**Foundation Coaching Track**8 Sessions (16 Hours) Valid: 75 calendar days**$280****$255** *(due on invoice)***$280** ($25 + $255)**$35.00/session** ($17.50/hr)**$31.87/session** ($15.93/hr)**Save $104** vs 8 × $48 single maintenance sessions ($384)**Fluency Immersion Track**12 Sessions (24 Hours) Valid: 100 calendar days**$390****$365** *(due on invoice)***$390** ($25 + $365)**$32.50/session** ($16.25/hr)**$30.41/session** ($15.20/hr)**Save $186** vs 12 × $48 single maintenance sessions ($576). Priority scheduling**Pay-As-You-Go Maintenance**2 Hours (Single)**$48** ($24/hr)N/A**$48**$24.00/hrN/A*Alumni only:* Hidden from main purchase flow**Advanced Conversational**1 Hour (Single)**$28**N/A**$28**$28.00/hrN/A*Unlisted exception:* Advanced C1+ debate practice only 

*Enforce:* Do NOT add a public 1-hour vs. 2-hour duration selector. 2-hour sessions are the standard educational format.

**6.4 Temporal Business Rules \& Window Precision**

**Analytics Maturity Window:** Exactly 30 \\times 86,400 elapsed seconds from first\_qualifying\_visit\_at.

**Diagnostic Credit Window:** Exactly 48 elapsed hours (48 \\times 3,600 seconds) from the diagnostic completion timestamp.

**Package Validity Expiration (Mathematical Definition):**

The date of package purchase is **Day 0** (evaluated at 00:00:00 Africa/Cairo).

Foundation access expires at 23:59:59 Africa/Cairo on \\text{purchase\\\_date} + 75\\text{ calendar days} (e.g., purchased September 20 \\rightarrow expires December 4 at 23:59:59 Africa/Cairo).

Fluency access expires at 23:59:59 Africa/Cairo on \\text{purchase\\\_date} + 100\\text{ calendar days} (e.g., purchased September 20 \\rightarrow expires December 29 at 23:59:59 Africa/Cairo).

**7. PUBLIC POLICIES: PRIVACY \& TERMS AND CONDITIONS**

**7.1 Preamble on Legal Disclosures**

Do not invent legal, payment, retention, or service practices. The supplied policy text represents the authoritative operational disclosure. If an underlying technical capability contradicts this text, report the discrepancy immediately.

**7.2 Entity vs. Translation Schema Architecture**

The pages table holds exactly **one** non-translatable parent row for Privacy (slug = 'privacy') and **one** parent row for Terms (slug = 'terms').

All translatable text (English, French, German) resides strictly in page\_translations referencing page\_id.

Seed English rows with locale = 'en', status = 'published', and source\_revision\_id = 1. Seed initial Revision 1 snapshots in entity\_translation\_revisions.

**7.3 Canonical English Policy Source Content**

**Privacy Policy (Canonical English Source)**

**Privacy Policy** *Last Updated: September 2026*

This Privacy Policy explains what personal information is collected, how it is handled, and how your privacy is protected.

**1. Information Collected**

Personal \& Contact Details: Your name, email address, and messaging handle or phone number (WhatsApp or Telegram).

Scheduling \& Learning Data: Diagnostic evaluations, learning roadmaps, and calendar booking records.

Technical \& Geolocation Data: To understand our audience, our first-party analytics system derives an approximate country from your network connection. The application does not retain raw IP addresses in its application database.

Communication Files: Audio voice notes, text queries, and study materials exchanged for instructional review.

**2. Payments \& Financial Data** Payments are billed manually via PayPal invoice. This website does not process, record, or store credit card numbers, debit card details, or banking credentials. All transactions are subject to PayPal’s independent privacy and security policies.

**3. Online Meetings \& Audio Privacy** Video coaching sessions conducted via Zoom, Google Meet, or Microsoft Teams are private 1-on-1 classes. They are not recorded unless you explicitly request a recording for your personal study. Pronunciation voice notes, custom PDFs, and feedback files are never shared publicly or used for marketing without your prior written permission.

**4. How Information Is Used** Your personal data is used to provide the Services, operate the website, manage bookings, maintain security, and produce aggregated first-party audience analytics:

Managing session bookings and schedule adjustments.

Generating aggregated, first-party audience analytics (e.g., total visitors by country) without cross-site tracking or profiling.

Issuing manual PayPal invoices and verifying payments.

Delivering personalized lesson materials and feedback via WhatsApp, Telegram, or email. Personal data is not sold to or shared with third-party advertisers or data brokers; limited data may be processed by the trusted third-party service providers listed in Section 5 strictly as necessary to provide the Services.

**5. Third-Party Service Providers** Data is shared only with tools necessary to deliver your coaching:

PayPal: For manual invoicing and payment processing.

Video Platforms (Zoom / Google Meet / Microsoft Teams): For hosting live lessons.

Messaging Platforms (WhatsApp / Telegram): For asynchronous audio feedback and direct communication.

**6. Your Rights \& Data Retention** Intake notes and learning logs are retained during your studies and for up to 12 months after your last class to allow smooth resumption if you return. Aggregated, pseudonymous analytics records are retained separately for long-term reporting. You may request an export of your materials or request the deletion of your personal contact records at any time by contacting the tutor directly. *(Note: This policy provides an operational disclosure of platform practices and should be reviewed by qualified legal counsel.)*

**Terms and Conditions (Canonical English Source)**

**Terms and Conditions** *Last Updated: September 2026*

These Terms and Conditions govern your booking, payment, and participation in 1-on-1 Egyptian Arabic coaching, diagnostic sessions, and asynchronous feedback ("Services"). By booking a session on this website or paying an invoice, you agree to these terms.

**1. Delivery \& Platform Logistics**

Live Video Sessions: Coaching sessions take place remotely via agreed video conferencing tools (Zoom, Google Meet, or Microsoft Teams). Meeting links are provided prior to each scheduled lesson.

Direct Scheduling: Lesson times and initial bookings are arranged directly through this website's scheduling interface.

Asynchronous Communication: Between-class micro-lessons, pronunciation reviews, and study queries are delivered via WhatsApp or Telegram based on your preference.

Package Validity:

Foundation Coaching Track (8 Sessions / 16 Hours): Valid for 75 calendar days from the date of purchase.

Fluency Immersion Track (12 Sessions / 24 Hours): Valid for 100 calendar days from the date of purchase. Unused sessions expire automatically after the validity window closes.

**2. Invoicing, Payments \& Credits**

Manual Invoicing via PayPal: Pricing published on the website is displayed for reference. All payments are billed manually via PayPal invoice. Lesson slots and package enrollments are confirmed once payment verification is received.

48-Hour Diagnostic Credit: The $25 diagnostic fee is 100% credited toward an 8- or 12-session package if you confirm enrollment and pay the package invoice within 48 hours of completing your diagnostic session. Standard package pricing applies after this 48-hour window.

**3. Rescheduling, Cancellations \& No-Show Policy**

Rescheduling (24+ Hours Notice): You may request to reschedule a session by contacting the tutor directly via WhatsApp, Telegram, or email at least 24 hours prior to the scheduled start time. Where calendar availability permits, adjustments will be accommodated without penalty.

Cancellations (4+ Hours Notice): Cancellations submitted with at least 4 hours' advance notice do not forfeit the session credit. The credit remains redeemable within your package validity period. Cancellations submitted with less than 4 hours' notice are forfeited and deducted from your package total.

15-Minute No-Show Rule: The tutor waits in the meeting room for 15 minutes. If you do not join within the first 15 minutes, the session is forfeited. If you join within the 15-minute window, instruction begins immediately but concludes at the originally scheduled end time.

**4. Refund Policy**

Diagnostic Sessions ($25): Non-refundable once the session has taken place. Cancellations made with at least 4 hours' notice can be rebooked or refunded, minus any non-recoverable PayPal processing fees.

Package Purchases: Packages are non-refundable once the first instructional session has been delivered. If a package is purchased and cancelled prior to attending the first lesson, a refund will be issued minus applicable non-recoverable PayPal fees.

Unused \& Expired Lessons: No partial, pro-rated, or retroactive refunds are provided for lessons left unscheduled after the package expiration date (75 or 100 days).

**5. Materials \& Usage** Custom study roadmaps, curated documents, and video/audio feedback created for you are for your personal educational use only. You may not distribute, sell, or publicly share these proprietary training materials without prior written consent.

**8. CMS \& STATIC LOCALIZATION WORKFLOW**

**8.1 Single Canonical Entity Representation**

Do NOT create separate parent entities per locale (e.g., privacy-en, privacy-fr, privacy-de).

The parent table pages contains exactly **one** record for Privacy (slug = 'privacy') and **one** record for Terms (slug = 'terms').

Translations reside strictly in page\_translations referencing page\_id.

**8.2 Immutable Source Revisions, Draft Retention \& Stale Rules**

**Source Revisions:** Translatable English edits create immutable snapshots in entity\_translation\_revisions (revision\_number starting at 1).

**Stale Translation Trigger:** Published French/German translations become status = 'stale' **only** when a new English source revision is committed/published (advancing from R to R+1), never upon saving an uncommitted English draft.

**Stale Translations Remain Visible:** Stale translations must remain publicly visible, render their existing translated text, self-canonicalize, and display NO fallback banner.

**Draft Retention on Source Advancement:** When English source content advances from R to R+1, an existing French or German draft retains its original source\_revision\_id = R. It is **never silently relabeled** to R+1. The draft is flagged as requiring reconciliation and remains the sole active draft for that locale until explicitly reconciled or replaced.

**Outdated Draft Publish Guard:** A draft whose source\_revision\_id is older than the current published English source revision (R\_{\\text{draft}} \< R\_{\\text{current}}) cannot be published directly. It must be explicitly reconciled against R\_{\\text{current}} first.

**Publishing Concurrency \& Atomicity:** Publishing a translation must occur inside a database transaction with pessimistic row locking (lockForUpdate on parent and child translation records). Atomically:

Previous live translation becomes status = 'archived'.

Reconciled draft becomes status = 'published', source\_revision\_id = R\_{\\text{current}}, and published\_at = NOW().

Exactly one live translation row exists for that (entity\_id, locale) pair.

**8.3 Missing Translation Fallback \& Hreflang Precision**

When a localized route is requested for an entity whose translation is missing, draft-only, or unpublished:

Return **HTTP 200 OK**.

Render the requested locale's interface shell (French/German navigation, header, footer).

Render English source content inside \<div lang="en" dir="ltr"\>.

Render the localized fallback alert banner:

*FR:* *"Ce contenu n'est pas encore disponible en français. La version anglaise est affichée."*

*DE:* *"Dieser Inhalt ist noch nicht auf Deutsch verfügbar. Die englische Version wird angezeigt."*

Point \<link rel="canonical"\> to the canonical English URL (/privacy or /terms).

**Hreflang Precision Rule:** Emit zero hreflang alternate tags for the unavailable/fallback locale. The fallback locale is completely omitted from hreflang clusters. However, genuinely published locales (e.g., English and German if both published) must continue to emit valid alternates for each other.

**9. CUMULATIVE FUNNEL PIPELINE, IMPUTATION \& MATURITY**

**9.1 Cumulative Funnel Invariants**

The primary booking funnel is strictly cumulative and visitor-counted: 

\\mathbf{Visitors \\longrightarrow Booking\\;CTA\\;Reached\\;/\\;Qualified \\longrightarrow Booking\\;Started \\longrightarrow Slot\\;Held \\longrightarrow Booking\\;Completed} 

Invariant: \\text{Completed} \\le \\text{Slot Held} \\le \\text{Booking Started} \\le \\text{CTA Qualified} \\le \\text{Total Visitors}.

Labeling: Stage 2 must be labeled **Booking CTA Reached / Qualified** (never "CTA Clicks").

**9.2 Event Deduplication Rules**

To prevent business metric distortion:

**Request-Scoped (Rapid Duplicate Guard, 5s):** booking\_cta\_clicked, resource\_requested.

**Session-Scoped (Once per 30-min session):** session\_started, resource\_gate\_viewed.

**Repeatable:** page\_view, game\_completed, external/social clicks.

**Visitor-Scoped Funnel Milestones:** Funnel qualification events count once per visitor within the cohort evaluation.

**Language Switching:** Switching interface languages must never duplicate sessions, active holds, visitor identity, or funnel milestones.

**9.3 Deterministic Stage Imputation \& Provenance**

Downstream milestones prove upstream qualification. Direct entry to /booking or completed bookings with blocked client analytics impute upstream stages.

Imputed stages receive \*\_observed\_at = NULL, \*\_qualified\_at = \[downstream\_timestamp\], and provenance = 'imputed'.

Never fabricate fake client events inside raw analytics\_events. Provenance lives strictly in progression records.

**9.4 Funnel Storage Specification (visitor\_funnel\_progressions)**

Do not compute cohort funnels by summing daily\_metrics. Maintain dedicated visitor-level progression storage containing explicit stage triplets:

visitor\_id (Primary Key / relation to visitors)

cohort\_date (Cairo calendar DATE)

first\_qualifying\_visit\_at (UTC TIMESTAMP)

booking\_cta\_observed\_at, booking\_cta\_qualified\_at, booking\_cta\_provenance

booking\_started\_observed\_at, booking\_started\_qualified\_at, booking\_started\_provenance

slot\_held\_observed\_at, slot\_held\_qualified\_at, slot\_held\_provenance

booking\_completed\_observed\_at, booking\_completed\_qualified\_at, booking\_completed\_provenance

updated\_at (TIMESTAMP)

Where \*\_observed\_at records the actual event timestamp, \*\_qualified\_at records when the stage became provably satisfied, and provenance is an enum/check constraint (observed, imputed, none).

**9.5 Cohort Date vs. Cohort Timestamp Precision**

**first\_qualifying\_visit\_at:** Canonical UTC timestamp of the earliest non-bot, qualifying visitor activity that establishes cohort membership.

**cohort\_date:** The calendar date in **Africa/Cairo** containing first\_qualifying\_visit\_at.

**Cohort Membership:** Evaluated over half-open calendar boundaries \[00:00:00, \\text{next } 00:00:00) in Africa/Cairo.

**Maturity Window:** Exactly 30 \\times 86,400 elapsed seconds: \[first\\\_qualifying\\\_visit\\\_at, first\\\_qualifying\\\_visit\\\_at + 30\\text{ days}).

**Late-Arriving Events:** An event arriving late qualifies if and only if t\_{\\text{first\\\_qualifying\\\_visit\\\_at}} \\le t\_{\\text{event}} \< t\_{\\text{first\\\_qualifying\\\_visit\\\_at}} + 30\\text{ days}, governed by the authoritative event timestamp (t\_{\\text{event}}), not server receive time. Late events outside 30 days must never alter matured cohort conversion. Cohorts containing unelapsed windows must be labeled **Immature / In-Progress**.

**9.6 Transactional Event Dispatch**

booking\_slot\_held: Generate server-side **only after** the booking-hold transaction successfully commits (using the framework's afterCommit hook). Database lock acquisition alone does not constitute an event.

booking\_completed: Generate server-side **only after** the finalized booking transaction commits. Never trust client events.

**10. DEDICATED MARKETING TOUCHES \& DUAL-TOUCH ATTRIBUTION**

**10.1 Dedicated Append-Only Table (marketing\_touches)**

Persist all marketing interactions in a dedicated append-only table. Do NOT store the lifetime touch history solely as mutable JSON on visitors:

Schema::create('marketing\_touches', function (Blueprint $table) { $table-\>id(); $table-\>uuid('visitor\_id')-\>index(); $table-\>uuid('session\_id')-\>nullable()-\>index(); $table-\>timestamp('touch\_at'); $table-\>string('utm\_source', 100)-\>nullable(); $table-\>string('utm\_medium', 100)-\>nullable(); $table-\>string('utm\_campaign', 100)-\>nullable(); $table-\>string('utm\_content', 100)-\>nullable(); $table-\>string('utm\_term', 100)-\>nullable(); $table-\>text('referrer')-\>nullable(); $table-\>boolean('is\_direct')-\>default(false); $table-\>timestamps(); $table-\>index(\['visitor\_id', 'touch\_at'\]); }); 

**Deduplication:** Identical rapid duplicate touches caused by client retries must be deduplicated using a deterministic event hash or bounded 5-second duplicate guard. Legitimate repeated visits and distinct campaign touches must remain representable.

Do NOT derive first-touch or booking attribution by querying daily\_metrics.

**10.2 Attribution Models \& Late-Arrival Immutability**

**Visitor Acquisition Attribution (First Non-Direct Touch):** Evaluated over the visitor's lifetime from marketing\_touches. Once assigned a non-direct touch, it is **immutable**. A late-arriving historical touch does not silently rewrite an established acquisition source; historical adjustments occur strictly via auditable reconciliation.

**Booking Conversion Attribution (Last Non-Direct Touch):** Evaluated over the exact 30-day pre-booking lookback window: \[t\_{\\text{booking}} - 30\\text{ days}, t\_{\\text{booking}}\]. Snapshot the resulting attribution onto the bookings record at creation.

**Direct / None Fallback:** If all touches in the 30-day window are direct, OR if zero touches exist in the window, Booking Conversion Attribution is **Direct / None**.

Drill-downs must support granular reporting down to utm\_content.

**11. VISITOR IDENTITY, SESSIONS, RETENTION \& BOT FILTERING**

**11.1 Pseudonymous Visitor Cookie \& Server-Side Identity Validation**

Use a cryptographically secure, pseudonymous first-party UUIDv4 identifier.

Cookie characteristics: contains no PII, 365-day expiration, SameSite=Lax, Secure in production. Readable by first-party JavaScript to attach client events to the visitor ID.

**Server-Side Validation Guard:** The server must derive the authoritative visitor identity from verified first-party session state. Any client-supplied visitor ID must be checked against that verified identity and must never be trusted on its own. Critical server-generated events and stage qualifications must derive visitor identity strictly from verified server-side session state. Untrusted client payloads attempting to assert arbitrary visitor UUIDs must be rejected to prevent funnel poisoning.

Do NOT implement browser fingerprinting.

**11.2 Sessions \& Estimated Active Visitors**

**Session Inactivity Timeout:** 30 minutes.

**Estimated Active Visitors:** Unique visitors with eligible activity within a rolling 5-minute window. Must be labeled **Estimated Active Visitors** in the dashboard.

**11.3 Multi-Tier Data Retention**

**Raw analytics\_events:** Default 180-day retention, prunable by scheduled job.

**Aggregated Rollups (daily\_metrics, daily\_country\_metrics):** Retained permanently for multi-year business reporting.

**Progression Records (visitors, visitor\_funnel\_progressions):** Retained permanently for multi-year cohort continuity. Pruning raw events must NEVER destroy or recalculate persisted funnel progression records.

**11.4 Bot \& Link-Preview Filtering**

Filter crawlers, monitoring bots, and link-preview generators (WhatsApp, Telegram, Slack, Discord, Twitter/X, Facebook, search crawlers).

Flag raw records with is\_bot = true. Exclude bot requests from unique visitors, sessions, funnels, and daily rollups.

**12. MOBILE ADMIN REMEDIATION \& MODAL ACCESSIBILITY**

Eliminate the bug where signing into the private admin dashboard on a mobile viewport leaves navigation overlays blocking the screen.

**12.1 Root-Cause Diagnostics \& Layout Rules**

Inspect resources/views/layouts/[admin.blade.php](admin.blade.php):

**Initial State Integrity:** Inspect whether stale local storage, incorrect default state, backdrop pointer-events, or drawer CSS transforms cause the bug. If an Alpine $persist directive is present and found to rehydrate a desktop "open" state on mobile viewports, remove $persist from the drawer state. The drawer **must** default closed (sidebarOpen: false) on mobile viewport load regardless of desktop state.

**Backdrop Pointer Events Guard:** The backdrop element must be completely unclickable and invisible when closed:\<div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false" class="fixed inset-0 bg-slate-900/50 z-40 md:hidden" x-transition:enter="transition-opacity ease-linear duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"\> \</div\> 

**Off-Canvas Translation:** Ensure the mobile drawer container utilizes off-canvas translation classes (e.g., -translate-x-full md:translate-x-0) rather than static display toggles.

**Body Scroll Lock Cleanup:** If body scroll-locking is attached to sidebarOpen, ensure it executes via an Alpine watcher ($watch('sidebarOpen', value =\> ...)). When sidebarOpen is false, [document.body.style.overflow](document.body.style.overflow) must be restored to '' (empty string), never left as hidden.

**Desktop Margin Integrity:** The main content wrapper \<main\> must use ml-0 md:ml-64. It must not have a static ml-64 on mobile viewports that shoves content offscreen.

**12.2 Modal Navigation Accessibility (WCAG 2.2 AA)**

When open, the drawer behaves as a modal navigation surface:

The container must include role="dialog" and aria-modal="true".

Trap keyboard focus inside the drawer while open.

Pressing Escape must close the drawer and restore focus to the hamburger toggle button.

All interactive touch targets must meet WCAG 2.2 AA target size criteria (\\ge 24\\text{px} \\times 24\\text{px}), with 44\\text{px} \\times 44\\text{px} used as the design target for primary navigation controls.

**13. UNIFIED ROUTING, CANONICAL MAPPING \& REDIRECTS**

**13.1 Standardized Route Matrix**

Standardize strictly on /booking. Remove all internal references to /book.

PurposeEnglish RouteFrench RouteGerman RouteTarget**Homepage**//fr/deHomeController**Booking Flow**/booking/fr/reservation/de/buchenBookingController**Pricing**/pricing/fr/tarifs/de/preisePricingController@index**Resources Index**/resources/fr/ressources/de/ressourcenResourceController@index**Resource Detail**/resources/{slug}/fr/ressources/{slug}/de/ressourcen/{slug}ResourceController@show**Games Index**/games/fr/jeux/de/spieleGameController@index**Game Detail**/games/{slug}/fr/jeux/{slug}/de/spiele/{slug}GameController@show**About**/about/fr/a-propos/de/ueber-unsAboutController**FAQ**/faq/fr/faq/de/faqFaqController**Privacy Policy**/privacy/fr/confidentialite/de/datenschutzPageController@show('privacy')**Terms \& Conditions**/terms/fr/conditions/de/agbPageController@show('terms') 

**Legacy /book 301 Redirect:** Existing /book requests must issue an HTTP 301 redirect to /booking (preserving query strings) rather than returning 404.

**Trailing Slash Normalization:** Standardize without trailing slashes. Redirect trailing-slash variants (e.g., /pricing/ \\rightarrow /pricing) via HTTP 301.

**Navigation Placement:** Add Pricing to the primary header navigation. Add Privacy Policy and Terms \& Conditions to the public footer.

**Security Guard:** Never expose administrative or login routes in public navigation or footers.

**14. PORTABLE DATABASE MIGRATIONS \& CONSTRAINTS**

All database changes MUST use framework migrations and the database engine's native portable abstractions. Do not execute raw engine-specific DDL scripts. The schema requirements below describe semantic invariants that must be implemented using portable framework migration abstractions appropriate to the configured database engine (PostgreSQL or MySQL 8+).

**14.1 Translation Uniqueness Invariant**

Do NOT copy literal engine DDL into migrations. Implement the following database constraints using the confirmed production engine's supported schema grammar:

**Live Record Uniqueness:** For any parent entity and locale, there can be at most one translation with status IN ('published', 'stale').

**Draft Record Uniqueness:** For any parent entity and locale, there can be at most one translation with status = 'draft'.

**Technical Enforcement:** Implement via partial unique indexes in PostgreSQL, generated virtual columns participating in unique indexes in MySQL 8+, or framework-managed transactional validation where direct engine expressions are unavailable.

**Immutable Snapshot Uniqueness (entity\_translation\_revisions):** \\text{UNIQUE}(entity\\\_type, entity\\\_id, revision\\\_number) 

**14.2 Portable Framework Migration Blueprint**

Schema::table('visitors', function (Blueprint $table) { $table-\>char('detected\_country\_code', 2)-\>nullable()-\>index(); }); Schema::table('sessions', function (Blueprint $table) { $table-\>char('detected\_country\_code', 2)-\>nullable()-\>index(); }); Schema::table('bookings', function (Blueprint $table) { $table-\>char('detected\_country\_code', 2)-\>nullable()-\>index(); }); Schema::create('daily\_country\_metrics', function (Blueprint $table) { $table-\>id(); $table-\>date('metric\_date'); $table-\>char('country\_code', 2); // ISO Alpha-2 or 'ZZ' $table-\>unsignedInteger('unique\_visitors')-\>default(0); $table-\>unsignedInteger('sessions')-\>default(0); $table-\>unsignedInteger('booking\_cta\_clicks')-\>default(0); $table-\>unsignedInteger('bookings\_completed')-\>default(0); $table-\>unsignedInteger('resource\_requests')-\>default(0); $table-\>timestamps(); $table-\>unique(\['metric\_date', 'country\_code'\]); $table-\>index('metric\_date'); }); 

**15. HISTORICAL ANALYTICS RECONCILIATION \& CUTOVER**

Before modifying production reporting:

**Audit Historical Source Data:** Inspect existing analytics\_events, visitors, sessions, daily\_metrics, and visitor\_funnel\_progressions to evaluate whether historical records contain valid visitor IDs and reliable timestamps.

**Reconstruction \& Idempotent Backfill:**

Where valid historical data exists, execute an explicit, auditable CLI command to backfill visitor\_funnel\_progressions and daily\_country\_metrics.

Never manufacture historical funnel stages, attribution, countries, or cohort memberships where raw evidence is absent.

**Authoritative Cutover Date:**

If historical records lack sufficient visitor or country context, establish an explicit Authoritative Analytics Cutover Date.

Label preceding periods in administrative reporting as **Non-Comparable Historical Data**.

**16. AUTOMATED ACCEPTANCE TEST SUITE**

The following automated tests must pass before the modification is accepted. Tests must be written using the application's standard testing and ORM abstractions without engine-specific SQL assumptions.

**16.1 Brand \& Identity Verification**

/\*\* @test \*/ public function test\_brand\_containers\_use\_english\_abdallah\_exclusively() { $this-\>assertEquals('Abdallah', config('app.tutor\_name')); foreach (\['/', '/pricing', '/about', '/booking'\] as $uri) { $response = $this-\>get($uri); $response-\>assertOk(); $content = $response-\>getContent(); $crawler = new \\Symfony\\Component\\DomCrawler\\Crawler($content); // Header brand area $headerText = $crawler-\>filter('header')-\>text(); $this-\>assertStringContainsString('Abdallah', $headerText); $this-\>assertStringNotContainsString('Ahmad', $headerText); $this-\>assertStringNotContainsString('Ahmed', $headerText); $this-\>assertStringNotContainsString('عبدالله', $headerText); } $admin = \\App\\Models\\Administrator::factory()-\>create(); $this-\>actingAs($admin)-\>get('/admin/dashboard') -\>assertOk() -\>assertSee('Abdallah'); } 

**16.2 Trusted Proxy Geolocation \& Analytics Semantics**

/\*\* @test \*/ public function test\_analytics\_ingests\_country\_from\_trusted\_proxy\_and\_normalizes\_case() { // Create valid visitor session identity $session = app(\\App\\Services\\AnalyticsService::class)-\>startSession(); $visitorId = $session-\>visitor\_id; $this-\>withSession(\['visitor\_id' =\> $visitorId\]) -\>withServerVariables(\[ 'REMOTE\_ADDR' =\> '[173.245.48.50](173.245.48.50)', // Configured trusted proxy IP 'HTTP\_CF\_IPCOUNTRY' =\> 'de', // Lowercase from header \]) -\>postJson('/api/analytics/events', \[ 'event\_name' =\> 'page\_view', 'visitor\_id' =\> $visitorId, 'page' =\> '/pricing', \]) -\>assertOk(); $event = \\App\\Models\\AnalyticsEvent::where('visitor\_id', $visitorId)-\>first(); $this-\>assertNotNull($event); $this-\>assertEquals('DE', $event-\>metadata\['detected\_country\_code'\]); $this-\>assertArrayNotHasKey('ip', $event-\>metadata); } /\*\* @test \*/ public function test\_untrusted\_client\_country\_header\_is\_ignored() { $session = app(\\App\\Services\\AnalyticsService::class)-\>startSession(); $visitorId = $session-\>visitor\_id; $this-\>withSession(\['visitor\_id' =\> $visitorId\]) -\>withServerVariables(\[ 'REMOTE\_ADDR' =\> '[203.0.113.195](203.0.113.195)', // Untrusted client 'HTTP\_X\_COUNTRY\_CODE' =\> 'FR', \]) -\>postJson('/api/analytics/events', \[ 'event\_name' =\> 'page\_view', 'visitor\_id' =\> $visitorId, 'page' =\> '/pricing', \]) -\>assertOk(); $event = \\App\\Models\\AnalyticsEvent::where('visitor\_id', $visitorId)-\>first(); $this-\>assertNotNull($event); $this-\>assertNotEquals('FR', $event-\>metadata\['detected\_country\_code'\] ?? null); } /\*\* @test \*/ public function test\_untrusted\_client\_cannot\_poison\_arbitrary\_visitor\_identity() { $legitimateSession = app(\\App\\Services\\AnalyticsService::class)-\>startSession(); $spoofedVisitorId = (string) \\Illuminate\\Support\\Str::uuid(); // Client attempts to POST an event claiming to be a different visitor UUID $this-\>withSession(\['visitor\_id' =\> $legitimateSession-\>visitor\_id\]) -\>postJson('/api/analytics/events', \[ 'event\_name' =\> 'page\_view', 'visitor\_id' =\> $spoofedVisitorId, 'page' =\> '/pricing', \]); // System must reject or bind to verified session visitor\_id, never to spoofed ID $this-\>assertDatabaseMissing('analytics\_events', \[ 'visitor\_id' =\> $spoofedVisitorId, \]); } /\*\* @test \*/ public function test\_unresolvable\_country\_normalizes\_to\_zz\_in\_daily\_metrics() { $visitorId = (string) \\Illuminate\\Support\\Str::uuid(); \\App\\Models\\Visitor::create(\[ 'visitor\_id' =\> $visitorId, 'detected\_country\_code' =\> null, 'first\_qualifying\_visit\_at' =\> now(), 'first\_seen\_at' =\> now(), 'last\_seen\_at' =\> now(), \]); \\App\\Models\\Session::create(\[ 'session\_id' =\> (string) \\Illuminate\\Support\\Str::uuid(), 'visitor\_id' =\> $visitorId, 'detected\_country\_code' =\> null, 'started\_at' =\> now(), 'last\_activity\_at' =\> now(), \]); \\Artisan::call('analytics:aggregate-daily-country', \['--date' =\> now()-\>toDateString()\]); $this-\>assertDatabaseHas('daily\_country\_metrics', \[ 'metric\_date' =\> now()-\>toDateString(), 'country\_code' =\> 'ZZ', \]); } /\*\* @test \*/ public function test\_visitor\_acquisition\_country\_remains\_immutable() { $visitorId = (string) \\Illuminate\\Support\\Str::uuid(); $service = app(\\App\\Services\\AnalyticsService::class); $service-\>recordSession($visitorId, 'DE'); $this-\>assertEquals('DE', \\App\\Models\\Visitor::where('visitor\_id', $visitorId)-\>value('detected\_country\_code')); // Second session weeks later from EG $service-\>recordSession($visitorId, 'EG'); // Acquisition country must remain DE $this-\>assertEquals('DE', \\App\\Models\\Visitor::where('visitor\_id', $visitorId)-\>value('detected\_country\_code')); } /\*\* @test \*/ public function test\_booking\_country\_is\_snapshotted\_from\_session\_and\_immutable() { $visitorId = (string) \\Illuminate\\Support\\Str::uuid(); $service = app(\\App\\Services\\AnalyticsService::class); // Acquired in DE, Session 2 in EG $service-\>recordSession($visitorId, 'DE'); $session = $service-\>recordSession($visitorId, 'EG'); // Booking created during EG session $booking = app(\\App\\Services\\BookingService::class)-\>createBookingFromSession($session, \[ 'customer\_timezone' =\> 'Africa/Cairo', 'start\_at\_utc' =\> now()-\>addDays(2), // Caller attempts to pass conflicting country parameter 'detected\_country\_code' =\> 'US', \]); // Service derives EG from session, ignoring caller-supplied 'US' $this-\>assertEquals('EG', $booking-\>detected\_country\_code); // Later session in DE does not alter historical booking snapshot $service-\>recordSession($visitorId, 'DE'); $booking-\>refresh(); $this-\>assertEquals('EG', $booking-\>detected\_country\_code); } 

**16.3 Timezone Display \& Reference-Instant DST Offset**

/\*\* @test \*/ public function test\_slot\_utc\_offset\_calculates\_against\_slot\_scheduled\_instant() { $service = app(\\App\\Services\\TimezoneDisplayService::class); // Europe/Berlin: UTC+2 in summer (CEST), UTC+1 in winter (CET) $summerSlotInstant = \\Carbon\\Carbon::parse('2026-07-15 14:00:00', 'UTC'); $winterSlotInstant = \\Carbon\\Carbon::parse('2027-01-15 14:00:00', 'UTC'); $summerDisplay = $service-\>formatSlotForDisplay('Europe/Berlin', $summerSlotInstant); $winterDisplay = $service-\>formatSlotForDisplay('Europe/Berlin', $winterSlotInstant); $this-\>assertEquals('UTC+2', $summerDisplay\['utc\_offset'\]); $this-\>assertEquals('UTC+1', $winterDisplay\['utc\_offset'\]); $this-\>assertEquals('DE', $summerDisplay\['timezone\_country\_code'\]); $this-\>assertStringContainsString('flags/4x3/[de.svg](de.svg)', $summerDisplay\['flag\_asset'\]); } 

**16.4 Pricing Structural \& Value Assertions**

/\*\* @test \*/ public function test\_pricing\_page\_structure\_and\_canonical\_values() { $response = $this-\>get('/pricing'); $response-\>assertOk(); // Verify view and action $response-\>assertViewIs('[public.pricing](public.pricing)'); // Numbers check $response-\>assertSee('25'); // Diagnostic $response-\>assertSee('280'); // Foundation Total $response-\>assertSee('255'); // Foundation Invoice $response-\>assertSee('390'); // Fluency Total $response-\>assertSee('365'); // Fluency Invoice // Confirm primary CTA points to booking and does not invoke payment gateways $content = $response-\>getContent(); $crawler = new \\Symfony\\Component\\DomCrawler\\Crawler($content); $ctaHref = $crawler-\>filter('a\[data-cta="primary-diagnostic"\]')-\>attr('href'); $this-\>assertStringContainsString('/booking', $ctaHref); // Assert absence of checkout forms or card inputs $this-\>assertCount(0, $crawler-\>filter('form\[action\*="checkout"\]')); $this-\>assertCount(0, $crawler-\>filter('input\[type="credit-card"\]')); } 

**16.5 Session Types Transactional Synchronization**

/\*\* @test \*/ public function test\_session\_types\_synchronization\_is\_atomic\_idempotent\_and\_preserves\_records() { // Seed initial state with an existing custom session type $legacyId = \\DB::table('session\_types')-\>insertGetId(\[ 'title' =\> 'Legacy 90-min Session', 'duration\_minutes' =\> 90, 'price' =\> 45.00, 'currency' =\> 'USD', 'active' =\> true, 'created\_at' =\> now(), 'updated\_at' =\> now(), \]); // Execute synchronization service/command app(\\App\\Services\\SessionTypeSyncService::class)-\>sync(); $activeTypes = \\App\\Models\\SessionType::where('active', true)-\>get(); $this-\>assertCount(1, $activeTypes); $this-\>assertEquals(60, $activeTypes-\>first()-\>duration\_minutes); $this-\>assertEquals(25.00, $activeTypes-\>first()-\>price); // Legacy record is deactivated, NOT deleted $legacy = \\App\\Models\\SessionType::find($legacyId); $this-\>assertNotNull($legacy); $this-\>assertFalse((bool)$legacy-\>active); // Rerunning synchronization does not duplicate the Diagnostic record app(\\App\\Services\\SessionTypeSyncService::class)-\>sync(); $this-\>assertEquals(1, \\App\\Models\\SessionType::where('active', true)-\>count()); } 

**16.6 Stale Translation \& Fallback State Transition Verification**

/\*\* @test \*/ public function test\_stale\_about\_translation\_remains\_publicly\_visible\_and\_updates\_database\_states() { // 1. Create canonical About page with French Rev 1 $page = app(\\App\\Services\\ContentService::class)-\>createPage(\[ 'slug' =\> 'about', 'en' =\> \['title' =\> 'About Me', 'content' =\> 'Original English'\], 'fr' =\> \['title' =\> 'À Propos', 'content' =\> 'Texte français original'\], \]); // Verify initial database states $this-\>assertEquals('published', $page-\>translations()-\>where('locale', 'fr')-\>value('status')); $this-\>assertEquals(1, $page-\>translations()-\>where('locale', 'fr')-\>value('source\_revision\_id')); $this-\>assertEquals(1, $page-\>current\_source\_revision); // 2. Publish English Rev 2 -\> French Rev 1 transitions to stale in database app(\\App\\Services\\ContentService::class)-\>updateEnglishSource($page, \[ 'title' =\> 'About Abdallah', 'content' =\> 'Updated English Content', \]); $page-\>refresh(); // Verify exact database state transition $this-\>assertEquals('stale', $page-\>translations()-\>where('locale', 'fr')-\>value('status')); $this-\>assertEquals(1, $page-\>translations()-\>where('locale', 'fr')-\>value('source\_revision\_id')); $this-\>assertEquals(2, $page-\>current\_source\_revision); // 3. Verify public rendering $response = $this-\>get('/fr/a-propos'); $response-\>assertOk(); // Must render French content, NOT English fallback $response-\>assertSee('Texte français original'); $response-\>assertDontSee('Updated English Content'); $response-\>assertDontSee('Ce contenu n\\'est pas encore disponible en français', false); // Canonical link points to itself (French URL) $content = $response-\>getContent(); $crawler = new \\Symfony\\Component\\DomCrawler\\Crawler($content); $canonicalHref = $crawler-\>filter('link\[rel="canonical"\]')-\>attr('href'); $this-\>assertEquals(url('/fr/a-propos'), $canonicalHref); } /\*\* @test \*/ public function test\_untranslated\_privacy\_policy\_falls\_back\_to\_english\_with\_alert() { // Seed Privacy page with English translation only $page = app(\\App\\Services\\ContentService::class)-\>createPage(\[ 'slug' =\> 'privacy', 'en' =\> \['title' =\> 'Privacy Policy', 'content' =\> 'Authoritative English Privacy Text'\], \]); // Verify English Revision 1 snapshot exists in immutable revision history $this-\>assertDatabaseHas('entity\_translation\_revisions', \[ 'entity\_type' =\> 'page', 'entity\_id' =\> $page-\>id, 'revision\_number' =\> 1, 'locale' =\> 'en', \]); $response = $this-\>get('/fr/confidentialite'); $response-\>assertOk(); // Fallback banner rendered $response-\>assertSee('Ce contenu n\\'est pas encore disponible en français', false); $response-\>assertSee('Authoritative English Privacy Text'); // Canonical points to canonical English route $content = $response-\>getContent(); $crawler = new \\Symfony\\Component\\DomCrawler\\Crawler($content); $canonicalHref = $crawler-\>filter('link\[rel="canonical"\]')-\>attr('href'); $this-\>assertEquals(url('/privacy'), $canonicalHref); // Hreflang suppressed for fallback locale $hreflangs = $crawler-\>filter('link\[rel="alternate"\]\[hreflang="fr"\]'); $this-\>assertCount(0, $hreflangs); } 

**16.7 Legacy /book 301 Redirect**

/\*\* @test \*/ public function test\_legacy\_book\_route\_redirects\_permanently\_to\_booking() { $response = $this-\>get('/book?ref=newsletter'); $response-\>assertStatus(301); $response-\>assertRedirect('/booking?ref=newsletter'); } 

**16.8 Mobile Admin Navigation Layout \& Behavioral Verification**

To ensure testing honesty, the mobile admin navigation must be verified across two complementary tiers:

**Structural PHPUnit Test:** Verifies server-rendered markup, initial state default, accessibility roles, and off-canvas CSS classes.

**Behavioral Protocol (Browser/Manual QA):** Verifies interactive [Alpine.js](Alpine.js) execution (Escape handling, focus trapping, focus restoration, backdrop pointer-event blocking, and body scroll-lock restoration). If browser testing tools (e.g., Laravel Dusk, Playwright, Cypress) are already integrated into the project, implement automated tests for these interactions. Otherwise, document their successful execution in the required manual QA verification report.

/\*\* @test \*/ public function test\_admin\_mobile\_navigation\_markup\_and\_modal\_attributes() { $view = $this-\>view('[layouts.admin](layouts.admin)', \[ 'title' =\> 'Dashboard', 'slot' =\> '\<div\>Dashboard Content\</div\>', \]); // Verify initial drawer state is closed $view-\>assertSee('sidebarOpen: false', false); // Verify modal accessibility and layout constraints $view-\>assertSee('role="dialog"', false); $view-\>assertSee('aria-modal="true"', false); $view-\>assertSee('x-cloak', false); $view-\>assertSee('md:hidden', false); // Verify off-canvas translation markup $view-\>assertSee('-translate-x-full', false); $view-\>assertSee('md:translate-x-0', false); } 

**17. REQUIRED IMPLEMENTATION REPORT**

Upon completion, provide an executive implementation report covering:

**Brand Replacement Audit:** Files updated to establish **Abdallah** as the tutor/business name. Confirm that learning content dialogues were not modified.

**Geolocation Architecture:** Implementation status of trusted proxy validation, GeoLite2 fallback, MMDB path, update command atomicity, and MaxMind license attribution.

**Country Analytics:** Schema status of detected\_country\_code across visitors, sessions, bookings, and daily\_country\_metrics (including 'ZZ' unknown handling). Explicitly confirm that IP addresses are not stored in the application database, distinguishing this from infrastructure/reverse-proxy logs.

**Timezone Display Service:** Documentation of slot reference-instant offset calculations, SVG flag asset delivery, and UTC/globe fallback behavior.

**Pricing \& Session Types:** Confirmation of PricingController@index localized views, pricing numerical consistency, safe deactivation (without deletion) of conflicting session types, and verification of concurrent synchronization serialization.

**Policies \& CMS Fallback Verification:** Seeding status of English Privacy and Terms records, snapshot verification in entity\_translation\_revisions, and verification of localized fallback banners, canonical tags, and selective hreflang suppression.

**Mobile Admin Bug Resolution \& Behavioral Verification:** Identification of the root cause in the mobile layout (Alpine persistence, backdrop pointer events, static margins) and verification of its fix across both structural assertions and behavioral checks (Escape closing, focus trap, focus restoration, body scroll-lock restoration).

**Funnel \& Attribution Continuity:** Confirmation that visitor\_funnel\_progressions, marketing\_touches, cumulative stage triplets, deduplication rules, and 30-day cohort maturity rules remain functional.

**Historical Data Reconciliation:** Cutover date established and status of any backfill executed.

**Automated Test Results:** Summary table of test command(s), total tests, passed, failed, and skipped. Do NOT claim "all tests pass" if pre-existing failures remain unresolved.

You are executing the attached Specification Version 2.7 against a live, production-grade web application.
Treat this specification as an inviolable contract. To ensure live data safety, zero architectural regressions, and absolute integrity of the existing modular monolith, you are strictly forbidden from attempting a single-turn, end-to-end implementation. You must execute this work through a phased, human-gated workflow.
Copy, review, and strictly adhere to the following Operational Execution Protocol:
OPERATIONAL RULES OF ENGAGEMENT
Gate 0: Inspection First (Current Turn)
Do NOT write, modify, delete, or refactor any code in this turn.
Do NOT run migrations, seeders, or database mutations in this turn.
Your sole deliverable for this response is the complete, evidence-based Section 1 Audit & Code Discovery Report detailed below.
Strict Phased Progression (Stop and Wait)
Work will proceed strictly through the sequence defined in Section 0 (Phases A through F).
At the end of each phase, you must stop, present your completed changes and test results, and wait for explicit human approval before moving to the next phase.
Skipping phases or bundling multiple phases into a single turn without authorization will result in an immediate rollback.
Verifiable Proof Over Assertions
Never state that "tests passed" or "features were verified" without providing verifiable evidence.
For every executed test, you must show the exact artisan/CLI command used (e.g., php artisan test --filter=...) and the actual terminal output. Simulated or assumed test passes are considered failed implementations.
Zero Data Loss & Safe Concurrency
Never execute DELETE or TRUNCATE operations on business entities (session_types, bookings, contacts, visitors).
Adhere strictly to the portable migration rules and atomic transactional constraints specified in Sections 5, 8, 14, and 15.
YOUR INITIAL DELIVERABLE (TURN 1)
Inspect the codebase and produce the Section 1: Code Discovery & Pre-Implementation Audit Report. Structure your response using the following headings:
1. Brand & Identity Baseline
Locations and file paths where "Ahmad" or "Ahmed" appear across config/, lang/en.json, Blade templates, and seeders.
Confirmation of any learning content dialogues or fixture files containing "Ahmed" (أحمد) that must be protected and left untouched.
2. Session Types Inventory
Complete database row dump or model listing of all records currently in session_types (ID, title, duration, price, active status).
Identification of the canonical Diagnostic record or whether it must be inserted.
3. Analytics & Geolocation Architecture
State of the current analytics ingestion pipeline: inspect analytics_events, visitors, sessions, daily_metrics, and visitor_funnel_progressions.
Presence of existing reverse-proxy CIDR configurations (config/trustedproxy.php or equivalent) and any existing GeoIP libraries or database files.
4. Admin Layout & Mobile Drawer Diagnostics
Inspection of resources/views/layouts/admin.blade.php: identify the Alpine.js state initialization (presence of $persist, initial values), backdrop overlay markup, drawer translation classes, and <main> margin rules.
Exact hypothesis for the mobile overlay blocking bug based on the codebase's actual structure.
5. CMS, Localization & Route Architecture
Inspection of pages, page_translations, and entity_translation_revisions schemas and database constraints.
State of lang/en.json, lang/fr.json, and lang/de.json.
Verification of existing route declarations for /booking, /pricing, /privacy, and /terms, including any legacy /book references.
Do not modify any files. Produce the audit report above and await explicit instructions to begin Phase A.