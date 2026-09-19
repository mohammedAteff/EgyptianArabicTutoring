**EXISTING WEBSITE — FINAL AUDIT, CORRECTION \& ENHANCEMENT SPECIFICATION**

You are modifying an already-developed, production-quality Egyptian Arabic tutoring website and private admin platform.

The existing application was built according to a previous development specification. The current implementation is generally strong and **must NOT be rebuilt from scratch**.

Your task is to:

Audit the existing implementation against the requirements below.

Identify the actual causes of any bugs or divergences.

Correct the underlying implementation rather than adding superficial workarounds.

Preserve existing working functionality, routes, business records, relationships, and stable architecture.

Add the requested enhancements safely.

Safely migrate existing data where schema changes are required.

Thoroughly test all affected subsystems across desktop and mobile.

Document any limitation that cannot be resolved without infrastructure or external services.

Do not rewrite stable systems merely for stylistic preference.

**0. LIVE-APPLICATION SAFETY AND CHANGE SEQUENCING**

This is an existing application containing live business data, including bookings, customer records, tutor availability, analytics history, and public content.

Do **not** perform schema migrations, destructive data transformations, localization migrations, analytics backfills, or irreversible modifications directly against live production data.

For changes involving database structure or existing records:

Work on a separate development branch.

Use a staging environment containing a representative copy of production data.

Back up production data before executing migrations.

Test forward migrations.

Verify rollback or recovery safety before deployment.

Ensure migrations are backward-compatible where practical.

Document every irreversible transformation.

Never truncate, overwrite, or delete historical business records merely to simplify migration.

Execute modifications in this sequence unless technical dependencies require a different order:

**Phase A — Low-Risk Isolated Fixes**

Remove the public Admin Panel / Tutor Panel link.

Correct mobile admin navigation and layout overflow.

Redesign the social-media footer links.

**Phase B — Analytics Pipeline Audit \& Storage Decoupling**

Implement the dedicated visitor\_funnel\_progressions cohort storage.

Audit raw event generation, validation, post-commit dispatch, and deduplication.

Correct cumulative funnel calculations, deterministic stage imputation, and dashboard terminology.

Ensure idempotent daily activity rollups in daily\_metrics.

Reconcile historical analytics where valid raw source data exists.

**Phase C — Public Localization \& Content Management**

Implement normalized translation child tables with database-enforced live/draft uniqueness.

Implement immutable English source-revision snapshots.

Safely migrate existing English content into \*\_translations tables while preserving entity identities and relationships.

Deprecate legacy translatable columns only after migration verification.

Implement the human-in-the-loop translation workflow with explicit publish actions.

Do not use external translation APIs or local machine-learning translation packages.

Configure public localized routing, canonical entity slugs, and taxonomy parameters.

Implement missing-translation fallback behavior and SEO/hreflang safeguards.

Implement session-bound, PII-safe booking state persistence across language switches.

**Phase D — Verification \& Production Cutover**

Execute the automated acceptance suite.

Perform manual QA on real mobile devices and representative responsive viewports.

Verify migration integrity on staging production-data copies.

Validate deployment readiness.

Produce the final implementation report.

**1. CORE ENGINEERING PRINCIPLES**

Before modifying implementation, inspect:

Existing database schema, foreign keys, indexes, constraints, and migrations.

Analytics event dispatchers, listeners, services, queue jobs, aggregation jobs, and dashboard queries.

Booking flow state machine, slot holds, expiration handling, transactions, and concurrency locks.

Content-delivery layer, CMS models, translation logic, and Blade/frontend components.

Admin layout, responsive breakpoints, navigation/drawer scripts, tables, and forms.

Public header, footer, language switcher, templates, and route declarations.

Where the current implementation already satisfies a requirement, preserve it.

Where it does not, identify and correct the root cause.

Do not create duplicate tables, shadow routes, parallel analytics architectures, or redundant CMS systems merely to avoid understanding the existing implementation.

Use the existing framework, architecture, packages, conventions, and deployment model wherever practical.

**2. ANALYTICS PIPELINE \& TRACKING AUDIT**

Tracking data is a core business asset. The administrator relies on analytics for traffic sources, resource engagement, booking-stage progression, campaign performance, and return on marketing activity.

Audit the full pipeline end-to-end:

Visitor / Session ID → Event Generation → Validation \& Deduplication → Bot Filtering → Attribution Attachment → Cohort Progression Storage → Daily Activity Rollups → Dashboard \& Reports

Verify that displayed dashboard numbers, charts, tables, CSV exports, and XLSX exports are internally consistent.

Do not fix an incorrect dashboard number by changing only the presentation layer. Trace the number to its source and correct the underlying data flow.

Document the root cause of every material historical discrepancy discovered.

**3. CUMULATIVE BOOKING FUNNEL DEFINITION**

The primary booking funnel is strictly cumulative:

Visitors → Booking CTA Reached / Qualified → Booking Started → Slot Held → Booking Completed

A visitor qualifying for a downstream stage must also be counted in every preceding stage.

Example:

Visitors: 1,000

Booking CTA Reached / Qualified: 300

Booking Started: 120

Slot Held: 80

Booking Completed: 50

Therefore:

Visitors = 1,000

Booking CTA Reached / Qualified = 300

Booking Started = 120

Slot Held = 80

Booking Completed = 50

Required invariants:

Visitors who reach Booking Started must not be subtracted from the CTA stage.

Every visitor in Slot Held must also exist in Booking Started, Booking CTA Reached / Qualified, and Visitors.

Every visitor in Booking Completed must also exist in every preceding stage.

The cumulative invariant must hold across:

Dashboard views

Date-range filters

Cohort reports

Exported CSV/XLSX data

Automated tests

Any API responses serving analytics data

**4. FUNNEL COUNTING UNIT, PROVENANCE \& UI HONESTY**

The cumulative funnel counts **unique qualifying visitors**, not:

raw event hits

raw sessions

button-click totals

total booking rows

A single visitor repeating an action within the applicable cohort window counts once per funnel stage.

**Stage Qualification**

A visitor qualifies for a funnel stage through either:

**A. Observed Event**

The system records the actual action through a client-side or server-side event.

Example:

A visitor clicks the booking CTA and booking\_cta\_clicked is successfully recorded.

**B. Deterministic Imputation**

A verified downstream action proves the visitor logically progressed through an earlier stage even though the earlier client-side event was not captured.

Examples:

Visitor directly enters /booking.

Visitor reaches booking through an external referral or bookmark.

Browser analytics are blocked, but the backend successfully creates a slot hold or completed booking.

Imputation must be deterministic and based on authoritative evidence.

Do **not** fabricate missing client events inside the raw analytics event log.

**Dashboard Terminology**

The second funnel stage must always be labeled:

**Booking CTA Reached / Qualified**

Never label the cumulative stage simply "CTA Clicks", because some qualified visitors may be imputed.

Provide an optional breakdown or tooltip showing:

**Observed CTA Clicks:** visitors for whom an actual CTA click was recorded.

**Imputed Qualification:** visitors whose CTA-stage qualification was inferred from verified downstream evidence.

Raw event reporting must contain only observed events.

Cohort funnel reporting must contain the complete qualified population.

The database must explicitly preserve stage provenance:

observed

imputed

none

**5. COHORT DEFINITION \& EXACT DATE INTERVALS**

The primary booking funnel is cohort-based.

**Canonical Reporting Timezone**

All:

calendar boundaries

cohort dates

midnight calculations

daily rollups

date filters

reporting periods

must be calculated using:

Africa/Cairo

**Mathematical Reporting Interval**

All calendar reporting periods use half-open intervals:

\[T\_start, T\_end)

where:

T\_start = 00:00:00 at the beginning of the first requested day.

T\_end = 00:00:00 at the beginning of the calendar day immediately after the final requested day.

**Cohort Qualification**

A visitor belongs to a requested cohort \[T\_start, T\_end) if and only if:

t\_first\_seen \>= T\_start

and

t\_first\_seen \< T\_end

where t\_first\_seen is the visitor's first qualifying visit overall.

**Returning Visitors**

Example:

Visitor A first visits on September 2 and completes a booking on September 14.

For the September 10–16 new-visitor cohort:

\[2026-09-10, 2026-09-17)

Visitor A must:

be excluded from the cohort funnel;

remain included in September 14 booking-date activity reports;

never be reclassified as a new cohort entrant.

Returning visitors must never become new cohort visitors simply because they performed an action during another reporting period.

**6. COHORT MATURITY \& AUDITED RECONCILIATION**

The default funnel maturity window is exactly 30 days.

**Exact Window**

The window length is:

30 × 86,400 seconds

from the visitor's first qualifying visit timestamp.

A downstream funnel action qualifies for the visitor's cohort if and only if:

t\_event \>= t\_first\_seen

and

t\_event \< t\_first\_seen + 30 days

The upper boundary is exclusive.

Therefore an event occurring exactly at:

t\_first\_seen + 30 days

is outside the 30-day cohort window.

**Authoritative Timestamp**

Maturity is governed by the **authoritative event timestamp**, not the timestamp when the server happened to receive the event.

Late ingestion must never extend the cohort window.

**Late-Arriving Events**

A delayed event whose authoritative event timestamp falls inside the 30-day window may be incorporated by an explicit reconciliation/backfill process.

If:

t\_event \< t\_first\_seen + 30 days

the event remains analytically eligible even if received later.

If:

t\_event \>= t\_first\_seen + 30 days

the event:

remains available in raw logs if retained;

may appear in event-date or booking-date activity reporting;

must **not** alter the visitor's 30-day cohort conversion state.

Post-maturity reconciliation must:

run through an explicit administrative/CLI reconciliation process;

be auditable;

record when reconciliation occurred;

not execute implicitly during routine dashboard reads.

**Immature Cohorts**

If any visitor in the queried cohort period has:

t\_current \< t\_first\_seen + 30 days

the cohort must clearly be labeled:

**Immature / In-Progress**

Fully matured cohorts must expose:

**Mature cohort — last reconciled: \[timestamp\]**

Maturity status must not be inferred merely from the calendar date of the cohort.

**7. COHORT FUNNEL VS. EVENT-DATE ACTIVITY REPORTING**

These are separate analytical concepts and must never be silently conflated.

DimensionCohort Funnel AnalysisDaily Activity ReportingGrouping KeyVisitor first qualifying visitEvent occurrence timestampPrimary QuestionWhat percentage of visitors arriving in this period eventually progressed/booked?What activity occurred on this calendar day?Storage Sourcevisitor\_funnel\_progressionsdaily\_metrics + retained raw eventsHistorical BehaviorUpdates during normal maturity window; later corrections only via auditable reconciliationImmutable after aggregation unless explicitly rebuilt/backfilled 

Examples of daily activity:

page views

sessions

CTA clicks

resource downloads

social clicks

bookings created on a particular calendar date

Do not calculate cohort funnels by summing daily activity metrics.

**8. FUNNEL PROGRESSION VS. ABANDONMENT ANALYSIS**

The platform may show terminal abandonment alongside the cumulative funnel.

**Cumulative Funnel**

Shows everyone who reached or exceeded each stage:

1,000 → 300 → 120 → 80 → 50

**Terminal Abandonment**

Assign each cohort visitor exactly one mutually exclusive terminal bucket based on the highest qualified stage achieved.

Buckets:

Dropped off before CTA

Qualified for CTA, did not start booking

Started booking, did not hold a slot

Held a slot, did not complete

Completed booking

The total of all terminal buckets must equal the number of qualifying cohort visitors exactly.

Direct visitors who enter /booking and abandon before holding a slot are classified as:

**Started booking, did not hold a slot**

The terminal bucket must use the visitor's highest qualified stage, including deterministic imputation.

**9. FUNNEL STORAGE ARCHITECTURE \& LATE-ARRIVING EVENTS**

Do not compute cohort funnels by summing daily\_metrics.

Maintain dedicated visitor-level progression storage:

visitor\_funnel\_progressions

Minimum fields:

visitor\_id — primary key / relation to master visitor

cohort\_date — first-visit date in Africa/Cairo

visitor\_at — first qualifying visit timestamp

booking\_cta\_observed\_at

booking\_cta\_qualified\_at

booking\_cta\_provenance

booking\_started\_observed\_at

booking\_started\_qualified\_at

booking\_started\_provenance

slot\_held\_observed\_at

slot\_held\_qualified\_at

slot\_held\_provenance

booking\_completed\_observed\_at

booking\_completed\_qualified\_at

booking\_completed\_provenance

updated\_at

Where appropriate, use an enum/check constraint for provenance:

observed

imputed

none

**Stage Representation**

For an observed event:

\*\_observed\_at = authoritative event timestamp

\*\_qualified\_at = same qualifying timestamp

provenance = observed

For deterministic imputation:

\*\_observed\_at = NULL

\*\_qualified\_at = authoritative downstream evidence timestamp selected by the qualification engine

provenance = imputed

**Late Event Handling**

If a delayed event arrives for an upstream stage currently marked imputed:

**Event inside the maturity window**

If:

t\_event \< t\_first\_seen + 30 days

the reconciliation process may:

populate \*\_observed\_at;

replace the provenance with observed;

preserve the correct qualified timestamp according to the progression rules;

record the reconciliation in an auditable log.

**Event outside the maturity window**

If:

t\_event \>= t\_first\_seen + 30 days

do not retroactively modify the 30-day cohort progression merely to make the event appear observed.

Retain the event in raw logs where retention policy permits.

**Retention**

Raw analytics events may be pruned according to an explicit retention policy.

However:

visitors

visitor\_funnel\_progressions

immutable source attribution required for long-term reporting

must remain available for the application's intended multi-year reporting horizon.

**10. FUNNEL ORDERING INVARIANTS**

For any cohort date range:

Completed Booking Visitors \<= Slot-Held Visitors \<= Booking-Started Visitors \<= Booking-CTA Visitors \<= Total Visitors

Automated tests must prove these inequalities.

The qualification engine must satisfy them through deterministic imputation when upstream events are absent but downstream evidence proves progression.

**11. ANALYTICS EVENT AUDIT \& TRUSTWORTHINESS**

Audit all existing tracking events, including:

page\_view

session\_started

booking\_cta\_clicked

booking\_started

booking\_slot\_held

booking\_completed

booking\_cancelled

booking\_rescheduled

resource\_gate\_viewed

resource\_requested

resource\_downloaded

game\_opened

game\_started

game\_completed

social\_link\_clicked

whatsapp\_clicked

telegram\_clicked

outbound\_link\_clicked

faq\_opened

navigation\_click

Document for each event:

source

payload

timestamp semantics

visitor/session association

validation

deduplication scope

bot filtering

aggregation destination

**Transactional Event Dispatch**

Critical business milestones must be server-authoritative.

**booking\_slot\_held**

Generate this event server-side only after the booking-hold transaction successfully creates and persists the active hold record.

If the framework supports transaction lifecycle hooks, dispatch after commit using the framework's supported afterCommit mechanism.

A database lock acquisition alone is **not** sufficient evidence.

A transaction that acquires a lock and then rolls back must produce zero hold events.

**booking\_completed**

Generate this event server-side only after the finalized booking record has successfully committed.

Never trust a client-side JavaScript event as proof that a booking was actually completed.

**12. EVENT DEDUPLICATION \& SCOPING**

Repeated interactions must not artificially inflate metrics.

Document and enforce an explicit scoping model.

ScopeRuleExamplesVisitor-scopedCount once per visitor within cohort evaluationFunnel qualification milestonesSession-scopedCount once per 30-minute session where applicablesession\_started, resource\_gate\_viewedRequest-scopedDeduplicate by idempotency key or rapid duplicate guardbooking\_cta\_clicked, resource\_requestedRepeatableLegitimate repeated events remain repeatablepage\_view, game\_completed, social clicks 

A language switch must not duplicate:

sessions

funnel stages

active booking holds

visitor identity

Where event semantics make a particular scope inappropriate, preserve the existing business meaning and document the reason.

**13. DAILY ACTIVITY ROLLUPS (daily\_metrics)**

Maintain daily\_metrics for high-performance operational reporting.

At minimum support:

daily page views

daily sessions

daily CTA activity

resource downloads

social link clicks

bookings created on calendar date D

Use idempotent aggregation.

Enforce:

UNIQUE(metric\_date, metric\_name)

Re-running the aggregation for the same date must produce the same final values rather than duplicating totals.

Never query daily\_metrics to construct visitor-level cohort funnels.

**14. ROLLUP REBUILDING \& HISTORICAL RECONCILIATION**

Provide an administrator/CLI mechanism to rebuild analytics deterministically.

It must support:

rebuilding any retained day's daily\_metrics;

rebuilding visitor\_funnel\_progressions;

rebuilding from retained raw events and authoritative server booking records;

correct Africa/Cairo timestamp evaluation.

Where historical data lacks essential information such as:

visitor IDs

reliable stage timestamps

sufficient event identity

do not fabricate values.

Instead:

Determine the last trustworthy date.

Designate earlier affected periods as:

**Non-Comparable Historical Data**

Record an explicit authoritative analytics cutover date.

Explain the limitation in the implementation report.

Historical reconciliation must not modify business records merely to make analytics appear complete.

**15. TIMEZONE \& MIDNIGHT BOUNDARIES**

Canonical analytics timezone:

Africa/Cairo

A session crossing midnight remains one session.

The session is attributed to the calendar date associated with session\_started\_at.

If reporting timezone configuration is ever changed in the future:

historical rollups must retain their original compilation timezone metadata; or

an explicit, documented backfill must be performed.

Do not silently reinterpret historical data under a new timezone.

**16. VISITOR IDENTITY \& SESSION TIMEOUTS**

**Visitor Identifier**

Use a cryptographically secure, pseudonymous first-party UUIDv4.

The identifier must contain no PII.

**Cookie**

The visitor cookie:

contains only the anonymous measurement identifier;

contains no name, email, phone, notes, or other PII;

may be readable by first-party JavaScript when necessary to associate client events with the visitor;

must use Secure in production;

must use SameSite=Lax;

must have a 365-day expiration.

If the implementation uses an HttpOnly cookie instead, server-side request middleware must automatically associate the visitor ID with internal events so visitor continuity is preserved.

Do not compromise tracking continuity merely because of cookie-security configuration.

**Master Visitor Record**

Maintain:

visitors(visitor\_id, first\_seen\_at, last\_seen\_at)

**Session**

A session expires after:

**30 minutes of inactivity**

**Estimated Active Visitors**

Define:

**Estimated Active Visitors**

as visitors having recorded activity during the rolling five-minute interval.

Use that exact label in the dashboard.

**Known Identity Limitations**

Document that:

clearing browser cookies can create a new anonymous visitor;

Safari/other browser storage restrictions may fragment identity;

multiple devices cannot be assumed to belong to the same anonymous visitor.

Do not implement browser fingerprinting to compensate.

**17. BOT FILTERING**

Filter known non-human traffic before aggregation.

Maintain appropriate bot detection for:

search crawlers

monitoring agents

link-preview bots

WhatsApp previews

Telegram previews

Slack previews

Discord previews

Twitter/X previews

Facebook previews

Bot requests should:

be identified before visitor/funnel aggregation;

be excluded from unique visitor counts;

be excluded from session totals;

be excluded from funnel calculations;

be excluded from daily business metrics.

Where raw request/event logging is retained, preserve:

is\_bot = true

Do not classify legitimate mobile browsers merely because they use WebViews, compression, or mobile user-agent characteristics.

**18. UTM ATTRIBUTION ARCHITECTURE**

Keep two attribution models separate.

**A. Visitor Acquisition Attribution — First Non-Direct Touch**

Answers:

**“What initial marketing channel introduced this visitor to the site?”**

**Window**

Evaluate across the visitor's lifetime.

**Behavior**

If Day 1 is direct:

acquisition remains unassigned.

If Day 20 arrives with:

utm\_source=youtube

then YouTube becomes the visitor's permanent First Non-Direct Touch.

Once assigned, acquisition attribution is immutable.

Subsequent:

direct visits

different campaign visits

later referrers

must not overwrite the original acquisition source.

**B. Booking Conversion Attribution — Last Non-Direct Touch**

Answers:

**“What recent marketing touch is associated with this specific booking?”**

**Exact Window**

Evaluate:

t\_booking - 30 days \<= t\_touch \<= t\_booking

The 30-day booking lookback window is inclusive at both endpoints.

**Selection**

Select the latest valid non-direct marketing touch in the window containing:

valid campaign parameters; or

recognized non-direct external referrer.

**Direct Fallback**

If all eligible touches are direct:

Direct / None

Touches older than 30 days are ineligible for this booking's conversion attribution.

Persist each touch's:

utm\_source

utm\_medium

utm\_campaign

utm\_content

utm\_term

referrer

touch\_at

**19. GRANULAR CONTENT ATTRIBUTION**

Support reporting down to utm\_content.

Example:

utm\_source=youtube\&utm\_medium=description\&utm\_campaign=survival-arabic\&utm\_content=video-17

Administrator reporting must be able to drill through:

**Campaign → Content → Visitor Count → Downstream Bookings**

Do not collapse separate utm\_content values merely because they share a campaign.

**20. MULTILINGUAL ARCHITECTURE — EN / FR / DE**

Implement public-facing localization for:

English (en) — canonical source language

French (fr) — localized

German (de) — localized

The private admin interface remains English-only.

**Arabic Language Scope**

Arabic is **not** an interface locale.

Arabic exists exclusively as embedded Egyptian Arabic learning content, including:

vocabulary

example phrases

transcripts

grammar examples

transliterations

The public interface remains LTR in English, French, and German.

Embedded Arabic content must use correct RTL isolation.

**21. STATIC STRINGS VS. ADMIN-AUTHORED CMS CONTENT**

Maintain strict separation between static interface strings and CMS-authored content.

**A. Static Interface Strings**

Examples:

navigation labels

buttons

placeholders

validation errors

form labels

UI status messages

date/time labels

Store through centralized localization resources:

lang/[en.json](en.json) lang/[fr.json](fr.json) lang/[de.json](de.json) 

**Missing Static Key Fallback**

When a French or German key is missing:

return the English source string;

never expose the raw key;

never trigger a runtime error.

For example, never render:

[ui.booking](ui.booking).submit\_button

to the end user.

**B. Admin-Authored Public Content**

Examples:

resource titles

resource descriptions

games

FAQs

About page

policies

editable categories

These are managed through normalized translation tables and the admin review workflow.

**22. DATABASE SCHEMA \& DUAL-STATE STORAGE CONSTRAINTS**

Implement localization using framework migrations appropriate to the application's actual production database engine.

Do not impose a database engine that the existing application does not use.

**Canonical Operational Tables**

Canonical parent entities such as:

resources

games

pages

faqs

categories

must retain only non-translatable operational fields, such as:

entity identity

canonical slug

asset references

sorting order

activation flags

relationships

operational timestamps

All translatable public content must live in normalized child translation tables.

**Translation Tables**

Examples:

resource\_translations

game\_translations

page\_translations

faq\_translations

category\_translations

Each translation row must support at minimum:

parent entity identifier

locale: en, fr, or de

source\_revision\_id

status

translatable fields

published\_at

created\_at

updated\_at

Supported translation statuses:

draft

published

stale

archived

The English row is the canonical source translation.

**Database Integrity Invariant**

For each (entity\_id, locale) pair:

There may be at most one live translation, where status is published or stale.

There may be at most one active draft translation, where status is draft.

These constraints must be enforced at the database level, not merely through application checks.

**PostgreSQL**

When PostgreSQL is the production engine, use supported partial unique indexes or an equivalent database-native mechanism.

Example:

CREATE UNIQUE INDEX uq\_entity\_live ON entity\_translations (entity\_id, locale) WHERE status IN ('published', 'stale'); CREATE UNIQUE INDEX uq\_entity\_draft ON entity\_translations (entity\_id, locale) WHERE status = 'draft'; 

**MySQL 8+**

When MySQL 8+ is the production engine, use a database-supported equivalent such as generated columns participating in unique indexes.

Example pattern:

live\_locale = locale when status is published/stale, otherwise NULL draft\_locale = locale when status is draft, otherwise NULL 

Then enforce:

UNIQUE(entity\_id, live\_locale) UNIQUE(entity\_id, draft\_locale) 

Implement all column types, generated-column syntax, foreign keys, and indexes using the actual production engine and framework migration capabilities.

Do not assume SQLite support unless SQLite is actually part of the application's supported deployment architecture.

**23. NO EXTERNAL TRANSLATION API \& EXPLICIT PUBLISH ACTION**

Do not integrate:

OpenAI

Google Translate

DeepL

Microsoft Translator

AWS Translate

hosted LibreTranslate

or another recurring external translation API

No recurring external translation API key, subscription, or service is permitted in V1.

Do not introduce heavy local machine-learning translation infrastructure, including:

Python runtimes solely for translation

PyTorch

HuggingFace transformers

MarianMT

Argos Translate

memory-intensive translation workers

**Human-in-the-Loop Workflow**

When an administrator edits English source content:

Detect whether a translatable English field actually changed.

Increment the English source revision when appropriate.

Mark published French/German translations as stale.

Preserve stale translations publicly.

If no active translation draft exists, create a draft record as appropriate.

A draft may optionally be initialized as an exact English copy only when explicitly treated as an **unreviewed translation template**.

Administrator manually enters or edits the French/German translation.

Ordinary draft saves update the draft only.

Saving a draft must **never automatically publish it**.

The administrator must explicitly invoke a separate **Publish** action to publish a translation.

Publishing must be transactional.

When a draft is published:

prior live translation becomes archived;

draft becomes published;

revision reference is updated to the reconciled current English revision;

published\_at is set.

**24. IMMUTABLE SOURCE REVISION HISTORY \& DRAFT RECONCILIATION**

Translation reconciliation requires historical snapshots of English source content.

Maintain an immutable revision-history table conceptually equivalent to:

entity\_translation\_revisions id entity\_type entity\_id revision\_number locale title description content created\_at created\_by 

**Data Model Rules**

**entity\_type**

Identifies the canonical model, for example:

resource

game

page

faq

category

**entity\_id**

Identifies the parent entity.

Because entity\_type + entity\_id is a polymorphic reference across multiple parent tables, entity\_id must **not** be described as a conventional relational foreign key to one specific parent table.

Referential validity for the polymorphic pair must be enforced through application model rules, migration validation, and automated integrity tests.

Use database foreign keys where the framework/schema can actually represent them safely, such as created\_by referencing the admin-user table.

**revision\_number**

Integer beginning at 1.

Enforce:

UNIQUE(entity\_type, entity\_id, revision\_number)

**locale**

For source revisions this must be:

en

Revision snapshots are immutable. Once written, a snapshot must never be edited in place.

Use database-engine-appropriate types for text, timestamps, and integer identifiers.

**Revision Semantics**

Each translatable entity has an immutable English source-revision history.

The current English translation points to the current source revision.

French/German translations record the English source revision against which their current text was created or reconciled.

**Revision Creation**

When English translation content is saved:

Compare new translatable values against the current authoritative English source revision.

If no translatable value changed, do not increment the revision.

If a translatable value changed: 

create the next revision number;

write an immutable snapshot of the resulting English content;

update the English translation's source\_revision\_id;

mark existing published French/German translations as stale.

Revision creation must be safe under concurrent writes. Do not permit two simultaneous English updates to create conflicting revision numbers.

Internal updates such as:

sorting

admin notes

asset-path changes

internal tags

operational flags

must not increment source content revision unless they genuinely alter public translatable content.

**Initial Revision**

The migration creates Revision 1 from the existing English content.

**Stale Detection**

When English advances from Revision R to R+1:

published French translations become stale;

published German translations become stale;

stale translations remain publicly visible.

Stale does not mean unpublished.

**Active Draft Reconciliation**

If a French/German draft is based on Revision R when English advances to R+1:

preserve the existing draft content;

do not silently change its source\_revision\_id;

mark the draft as based on an outdated revision;

flag it as requiring reconciliation.

The admin reconciliation UI should expose:

outdated source snapshot R;

current English source snapshot R+1;

existing localized draft;

the differences between the English revisions.

The system should allow the administrator to reconcile the draft manually.

**Publishing Reconciled Drafts**

Only after reconciliation and explicit Publish action:

archive the previous live translation;

mark the reconciled draft published;

set source\_revision\_id = R+1;

set published\_at = NOW().

**25. EXISTING ENGLISH CONTENT MIGRATION**

The existing production website must be migrated without changing entity identity.

**Step 1 — Preserve Master Identity**

Retain:

existing entity IDs

foreign-key relationships

canonical slugs

timestamps

asset references

ordering

publication flags

existing business relationships

**Step 2 — Backfill English Translation Rows**

Move/map legacy translatable fields into the relevant \*\_translations tables using:

locale = en

source\_revision\_id = 1

status = published

For published\_at:

use the existing historical publication timestamp when available;

otherwise use the migration execution timestamp.

**Step 3 — Seed Initial Source Snapshot**

Create immutable English Revision 1 in entity\_translation\_revisions using the migrated English source content.

The Revision 1 snapshot must exactly correspond to the English translation content after migration.

**Step 4 — Verify Before Legacy Deprecation**

Before changing public reads:

every published English entity must have exactly one published English translation;

all required relationships must remain valid;

all active content must resolve correctly;

rendered English content must match the pre-migration content.

Only after successful verification may legacy translatable columns be:

removed through a later migration; or

deprecated and permanently excluded as sources of truth.

There must never be two competing authoritative English content sources.

**Step 5 — Public Verification**

The public English website must render without unintended content, routing, or layout changes.

**26. CANONICAL PUBLIC ROUTING TABLE**

Localized URLs use localized path segments for major sections.

Individual resource/game entity slugs remain canonical across all languages in V1.

Required route matrix:

Route PurposeEnglishFrenchGermanHomepage//fr/deBooking Flow/booking/fr/reservation/de/buchenResources Index/resources/fr/ressources/de/ressourcenResource Detail/resources/{slug}/fr/ressources/{slug}/de/ressourcen/{slug}Games Index/games/fr/jeux/de/spieleGame Detail/games/{slug}/fr/jeux/{slug}/de/spiele/{slug}About/about/fr/a-propos/de/ueber-unsFAQ/faq/fr/faq/de/faqPrivacy/privacy/fr/confidentialite/de/datenschutzTerms/terms/fr/conditions/de/agb 

**Trailing Slash**

Standardize all routes without trailing slashes except /.

Redirect trailing-slash variants such as:

/fr/ → /fr

/fr/reservation/ → /fr/reservation

using HTTP 301 where appropriate.

Do not create duplicate route handlers for both forms.

**Entity Slugs**

Keep resource/game entity slugs identical across locales in V1.

Example:

/resources/survival-guide

/fr/ressources/survival-guide

/de/ressourcen/survival-guide

Do not translate entity slugs in V1.

**Taxonomy Parameters**

Category query parameters use canonical English category slugs across locales.

Example:

/fr/ressources?category=grammar

Only the visible category label is localized.

The parameter contract must be documented and applied consistently to filtering, canonical URLs, and route handling.

**27. MISSING TRANSLATION FALLBACK CONTRACT \& SEO INTEGRITY**

When a localized route is requested for an entity whose translation is:

missing

draft-only

otherwise not published

but the English source entity is published:

**1. HTTP Status**

Return:

200 OK

Do not return 404 merely because the translation is unavailable.

**2. Interface Shell**

Render the requested locale's interface shell.

For German:

German navigation

German static UI

German footer

German controls

**3. Content**

Render the English source content inside the primary content container.

**4. Fallback Banner**

Display an accessible warning in the requested interface language.

German example:

Dieser Inhalt ist noch nicht auf Deutsch verfügbar. Die englische Version wird angezeigt.

French example:

Ce contenu n'est pas encore disponible en français. La version anglaise est affichée.

**5. Language Tagging**

The English fallback content container must be explicitly identified:

\<div lang="en" dir="ltr"\> 

The overall document language should remain the requested interface locale.

**6. Canonical**

The fallback page must canonicalize to the application-generated English URL.

Example:

\<link rel="canonical" href="{{ canonical\_url }}" /\> 

For the fixture:

[https://example.com/resources/survival-guide](https://example.com/resources/survival-guide)

Do not place Markdown syntax inside the HTML attribute.

**7. Hreflang**

A fallback page must emit **zero hreflang alternate tags**.

The canonical English page and genuine localized pages must omit any locale whose translation is not genuinely published.

Only translations with:

status = published

may participate in hreflang clusters.

This prevents conflicting localization/indexing signals.

**8. Stale Translation Exception**

status = stale is **not** a fallback state.

A stale translation:

remains publicly visible;

renders its existing translated content;

displays no fallback banner;

self-canonicalizes;

participates in hreflang only if the implementation's localization policy considers stale public content an eligible published translation; the implementation must use one explicit, consistent rule and document it.

For this V1 specification, because stale translations remain public and are a vetted translation state, treat stale translations as genuine localized content for ordinary page rendering.

**28. IN-PROGRESS BOOKING FLOW STATE PRESERVATION**

When a visitor switches interface language during an active booking flow, preserve the entire meaningful booking state.

**State That Must Survive**

active booking step

selected date

selected time

active slot hold

hold expiration state

selected customer timezone

unsubmitted name

unsubmitted email

unsubmitted phone/WhatsApp

unsubmitted notes

**Preferred Architecture**

Prefer **server-side session state** for continuity between localized routes.

Do not require state to be transmitted through the URL unless technically necessary.

**Frontend Synchronization**

The existing booking component, whether Livewire, Alpine, Vue, React, or another framework, must synchronize in-flight form data into server-side session state before language navigation.

Acceptable mechanisms include:

debounced server synchronization; or

intercepting the language-switch action and synchronously persisting current state before redirect.

Choose the mechanism that best fits the existing implementation.

**Language Switch**

The logical transition is:

Current Booking View → Persist State to Session → Redirect to Localized Route

Example:

/booking → session persistence → /fr/reservation

or:

/fr/reservation → session persistence → /de/buchen

**Hold Token**

Prefer session-bound server-side state without a URL hold token.

If an opaque hold token must exist in the URL because of the current architecture:

it must contain no PII;

it must be short-lived where practical;

it must be bound to the current session;

the controller must verify that the hold belongs to the current session.

Security condition:

hold.session\_id === [current\_session.id](current_session.id)

If the token is invalid, expired, missing, or associated with another session:

reject access to the foreign hold;

do not populate private customer information;

render a clean Step 1 state;

log a security event appropriate to the existing security architecture.

**PII Prohibition**

Never place these values in URLs:

name

email

phone

notes

any other customer PII

**Hold Preservation**

Language switching must:

reuse the existing hold;

never create a second hold;

never release the existing hold;

never extend the expiration timer merely because language changed.

If the hold naturally expires during the transition, use the existing expired-hold logic.

**29. BIDIRECTIONAL TEXT \& EMBEDDED ARABIC (RTL)**

Arabic learning content exists inside an otherwise LTR interface.

Use bidirectional isolation.

Preferred markup:

\<bdi dir="rtl" lang="ar"\>...\</bdi\> 

or an equivalent CSS isolation strategy such as:

unicode-bidi: isolate; direction: rtl; 

Ensure:

Arabic text remains visually and semantically isolated;

punctuation does not unexpectedly reorder;

Arabic examples do not distort surrounding layout;

Arabic-compatible fonts have appropriate fallback ordering;

line-height does not clip Arabic glyphs.

Suitable Arabic-capable fonts may include existing project fonts such as Cairo or Amiri where appropriate.

Mixed examples containing:

Arabic

English

transliteration

numbers

EGP prices

punctuation

must render correctly.

Never apply dir="rtl" to the entire document merely because Arabic learning content exists.

**30. TRANSACTIONAL EMAIL SCOPE BOUNDARY**

Transactional email localization is **outside V1 scope**.

Do not:

build multilingual email templates;

introduce email localization infrastructure;

add new external email services;

introduce automated booking confirmation emails that did not exist in the baseline application;

add admin email notifications that were not part of the baseline.

Existing system/security email behavior remains governed by its existing implementation and is outside this localization project.

Public on-screen confirmation states and existing public resource-delivery experiences must use the selected interface language where those workflows are currently rendered by the application.

**31. SOCIAL MEDIA FOOTER REDESIGN**

Redesign public social links.

**Presentation**

Every enabled platform should use:

**Icon + Visible Text Label**

Supported examples:

Instagram

TikTok

YouTube

Telegram

WhatsApp

Do not make the icon the only visible indication of the platform.

**Assets**

Use:

existing SVG assets;

inline SVGs;

the existing installed icon package.

Do not add large icon libraries or external CDNs merely for these links.

**Accessibility**

Decorative SVGs:

aria-hidden="true" 

The anchor itself must have an accessible name.

Example:

aria-label="Visit our Instagram page" 

Ensure keyboard users can reach and understand the links.

**Non-Blocking Analytics**

Social-click tracking must never delay navigation.

Use:

[navigator.sendBeacon](navigator.sendBeacon)(), or

fetch(..., { keepalive: true })

Do not:

[event.preventDefault](event.preventDefault)() 

merely to await tracking.

If analytics is:

blocked;

unavailable;

slow;

failed;

the external social link must still navigate immediately.

**32. PRIVATE ADMIN DASHBOARD MOBILE UX \& ACCESSIBILITY**

Correct private admin navigation on small viewports at approximately:

\<= 768px

**Drawer Behavior**

The navigation sidebar must:

default closed on mobile;

open only through an explicit menu toggle;

not occupy the permanent desktop sidebar footprint.

**Backdrop**

When open:

show a backdrop;

tapping the backdrop closes the drawer;

explicit close control closes it;

Escape closes it.

**Accessibility**

The open drawer must behave as an accessible modal navigation container.

Use:

role="dialog" aria-modal="true" 

Implement:

keyboard focus trapping;

focus movement into the drawer;

focus restoration to the hamburger toggle after closing;

keyboard Escape support.

**Scroll Lock**

While open:

[document.body.style.overflow](document.body.style.overflow) = 'hidden' 

or an equivalent robust scroll-lock mechanism.

The drawer itself must remain vertically scrollable.

When closed, restore the prior body-scroll state rather than blindly assuming it was always auto.

**Viewport**

Verify the admin layout contains:

\<meta name="viewport" content="width=device-width, initial-scale=1"\> 

**Layout Overflow**

Remove or correct hardcoded desktop constraints such as inappropriate:

min-w-\[...\]

when they force horizontal scrolling.

Do not simply hide overflow if doing so conceals unusable content.

**Touch Targets**

Ensure interactive controls have at least:

24 × 24px

of target area where WCAG requires it, with approximately:

44 × 44px

preferred for comfortable mobile operation.

**Data Tables**

Complex mobile data views such as:

bookings

contacts

analytics logs

must either:

transform into responsive card/list layouts; or

remain horizontally scrollable inside a correctly constrained container.

Do not let them cause the entire page to overflow horizontally.

**33. PUBLIC ADMIN LINK REMOVAL \& SEARCH DE-INDEXING**

Remove all public-facing links to administrative functionality.

Remove:

Admin Panel links

Tutor Panel links

admin login links

from:

public header

public footer

public navigation

mobile navigation sheets

public account/menu areas

Direct administrative URLs remain protected and functional.

Example:

/admin/login

**Search Engine De-Indexing**

Removing links does not remove URLs already known to search engines.

All admin, tutor, and authentication routes must emit:

X-Robots-Tag: noindex, nofollow, noarchive 

[**robots.txt**](robots.txt)** Sequence**

Do **not** immediately add:

Disallow: /admin

or:

Disallow: /login

to [robots.txt](robots.txt)

while active de-indexing depends on crawlers reaching the URLs and reading the noindex header.

After administrative URLs have been removed from relevant search indexes, reassess whether robots restrictions are appropriate.

Follow the application's actual search-engine management process.

**Security**

Removing links is not a security mechanism.

Preserve all existing:

authentication guards

authorization/role checks

password hashing

session regeneration

CSRF protection

rate limiting

admin access restrictions

Do not weaken security to perform de-indexing.

**34. AUTOMATED ACCEPTANCE TEST SUITE**

The implementation must pass the following automated tests before deployment.

**34.1 Cumulative Funnel \& Imputation**

**test\_funnel\_cumulative\_progression\_counts\_each\_visitor\_once**

Simulate:

Visitor → CTA → Started → Held → Completed

Verify each of the five cumulative stages increments by exactly 1.

Repeat an event and confirm that unique visitor counts do not inflate.

**test\_direct\_booking\_imputes\_upstream\_stages\_with\_correct\_provenance**

Simulate a direct visitor entering:

/booking

and completing a reservation.

Verify:

Visitors = 1

Booking CTA Reached / Qualified = 1

Booking Started = 1

Slot Held = 1

Booking Completed = 1

Verify:

booking\_cta\_provenance = imputed

and:

booking\_cta\_observed\_at = NULL

**test\_blocked\_client\_tracking\_preserves\_funnel\_integrity**

Simulate a completed booking while browser analytics endpoints fail or are blocked.

Verify:

booking is still successfully persisted;

server-authoritative stages are recorded;

cumulative funnel remains valid;

no fabricated client event is inserted into raw analytics.

**34.2 Cohort Boundaries, Maturity \& Late Events**

**test\_returning\_visitor\_excluded\_from\_new\_cohort**

Seed:

first\_seen\_at = 2026-08-15 10:00:00

Create booking:

2026-09-12

Query cohort:

\[2026-09-10, 2026-09-17)

Verify:

Visitor is excluded from that new-visitor cohort.

Booking appears in September 12 booking-date activity.

**test\_cohort\_maturity\_window\_cutoff**

Seed:

first\_seen\_at = 2026-09-01 10:00:00

Booking A:

2026-09-25

This is inside the 30-day window and must count.

Booking B:

2026-10-01 10:00:00

This is exactly 30 days later and must not count because the upper boundary is exclusive.

Also test a later booking such as:

2026-10-15

and confirm it does not alter the 30-day cohort conversion.

**test\_late\_arriving\_event\_respects\_maturity**

Seed:

first\_seen\_at = 2026-09-01 10:00:00

with an imputed CTA.

Ingest an observed CTA event whose authoritative event timestamp is:

2026-10-15

Verify:

raw event may be retained;

progression is not changed to observed;

30-day cohort conversion does not change.

**test\_late\_event\_inside\_window\_can\_be\_reconciled**

Seed a visitor whose authoritative event timestamp falls inside the 30-day window but whose event is ingested later.

Run explicit reconciliation.

Verify:

event is incorporated;

provenance changes appropriately;

reconciliation timestamp/audit record exists;

routine dashboard reads do not perform hidden reconciliation.

**test\_immature\_cohort\_flagging**

Query a cohort period containing visitors whose 30-day windows have not elapsed.

Verify:

is\_mature = false

and visible UI label:

**Immature / In-Progress**

**34.3 Analytics Rollups, Dispatch \& Attribution**

**test\_booking\_slot\_held\_fires\_only\_post\_commit**

Begin a hold transaction.

Acquire the relevant reservation lock.

Throw an exception before commit.

Verify:

transaction rolls back;

no active hold remains;

zero booking\_slot\_held events are dispatched.

**test\_booking\_completed\_fires\_only\_post\_commit**

Attempt a booking transaction that fails or rolls back.

Verify:

no successful booking remains;

no booking\_completed event is emitted.

**test\_daily\_activity\_rollup\_idempotency**

Aggregate:

2026-09-14

three times.

Verify:

row count is unchanged;

metric values are unchanged;

no duplicate totals appear.

**test\_first\_non\_direct\_acquisition\_attribution**

Visitor:

Day 1: Direct

Day 10: utm\_source=youtube\&utm\_campaign=intro

Day 15: Direct + booking

Verify acquisition is locked to:

youtube / intro

**test\_last\_non\_direct\_touch\_booking\_attribution**

Visitor:

Day 1: YouTube

Day 20: Telegram

Day 22: Direct + booking

Verify:

Acquisition = YouTube

Booking conversion attribution = Telegram

**34.4 Localization, Source Revisions, Stale Content \& Fallbacks**

**test\_missing\_translation\_fallback\_and\_seo\_headers**

Request:

/fr/ressources/test-guide

with no published French translation.

Verify:

HTTP status = 200

English body content rendered

localized fallback alert present

English fallback wrapper has lang="en" dir="ltr"

canonical href equals the application-generated English URL:

[https://example.com/resources/test-guide](https://example.com/resources/test-guide)

zero hreflang alternate tags are emitted

**test\_stale\_translation\_remains\_publicly\_visible**

Seed:

English Rev 1

French Rev 1 published

Update English to Rev 2.

Verify:

French becomes stale;

public French route remains HTTP 200;

French translated text remains visible;

fallback banner does not appear.

**test\_draft\_reconciliation\_flagged\_on\_new\_source\_revision**

Create French draft based on English Rev 1.

Update English to Rev 2.

Verify:

draft content is unchanged;

draft source\_revision\_id remains 1 until reconciliation;

admin UI marks draft as requiring reconciliation;

Revision 1 snapshot still exists;

Revision 2 snapshot exists;

admin can compare the outdated and current English source.

**test\_draft\_save\_does\_not\_publish**

Create and save a French draft.

Verify:

status remains draft;

public route does not render it as published;

stale published translation remains live if one exists;

otherwise English fallback is used.

**test\_explicit\_publish\_transitions\_correctly**

Create an approved French draft.

Invoke explicit Publish action.

Verify atomically:

prior live translation becomes archived;

draft becomes published;

published\_at is populated;

source\_revision\_id equals current English revision;

no duplicate live translation exists.

**test\_source\_revision\_only\_changes\_for\_translatable\_content**

Change an operational field such as sorting order.

Verify English source revision does not increment.

Change English title/content.

Verify:

revision increments;

immutable snapshot is created;

published French/German become stale.

**test\_initial\_migration\_creates\_revision\_one**

Run migration on representative legacy content.

Verify:

entity ID unchanged;

English translation created;

Revision 1 snapshot created;

source content matches;

publication state preserved.

**test\_missing\_static\_translation\_falls\_back\_to\_english**

Add:

"[cta.start](cta.start)": "Start Now" 

to [en.json](en.json).

Omit it from [de.json](de.json).

Render:

/de/buchen

Verify visible text is:

Start Now

and never:

[cta.start](cta.start)

**34.5 Booking State Preservation**

**test\_booking\_language\_switch\_preserves\_state**

Seed an active booking session with:

Date: 2026-10-05

Time: 14:00

Timezone: Europe/Paris

Name: Jean Dupont

active database slot hold

Switch to:

/de/buchen

Verify:

German page loads;

selected date remains 2026-10-05;

time remains 14:00;

timezone remains Europe/Paris;

customer input remains populated;

active hold remains the same;

hold timer remains correct;

no second hold row is created;

hold expiration is not extended by the language switch.

**test\_hold\_token\_cross\_session\_tampering\_rejected**

Initiate a hold in Session A.

Attempt to access it using Session B.

Verify:

Session B cannot retrieve Session A's hold;

Session B cannot retrieve Session A's name/email/phone;

localized page renders clean Step 1 state;

an appropriate security event is logged.

**test\_language\_switch\_contains\_no\_pii\_in\_url**

Switch languages with populated booking form fields.

Verify the resulting URL contains none of:

name

email

phone

notes

**34.6 Admin Drawer Accessibility**

**test\_admin\_mobile\_drawer\_keyboard\_trap\_and\_escape**

Load:

/admin/dashboard

at:

375px

Open mobile drawer.

Verify:

body scrolling is disabled while open;

focus is inside drawer;

repeated Tab navigation remains within drawer;

Escape closes drawer;

body scrolling is restored;

focus returns to menu toggle.

**35. FINAL ACCEPTANCE CRITERIA**

The project is complete only when all of the following are true:

**1. Funnel Precision**

The primary booking funnel is:

cumulative;

cohort-based;

anchored to Africa/Cairo;

evaluated using an exact 30-day half-open timestamp window;

visitor-counted;

observed/imputed aware;

labeled **Booking CTA Reached / Qualified**;

invariant across all reports.

**2. Decoupled Analytics**

visitor\_funnel\_progressions powers cohort funnels.

daily\_metrics powers operational daily metrics.

Daily aggregation is idempotent.

Late events outside the 30-day window never alter matured cohort conversion.

Post-maturity corrections occur only through auditable reconciliation.

**3. Event Integrity**

booking\_slot\_held and booking\_completed are server-authoritative and emitted only after successful transaction commit.

**4. Attribution Integrity**

Acquisition = First Non-Direct Touch over visitor lifetime.

Booking conversion = Last Non-Direct Touch over exact 30-day pre-booking lookback.

**5. CMS Localization**

Resources, games, pages, FAQs, and categories support:

English

French

German

through normalized translation child tables.

Database-level constraints prevent multiple live or active draft rows for the same entity and locale.

**6. Immutable Revision History**

Every translatable entity has immutable English source snapshots.

Drafts retain their source revision.

Outdated drafts can be reconciled against:

their original English revision;

the current English revision.

**7. Localization Workflow Safety**

English changes increment source revisions only when translatable content changes.

Published French/German translations become stale without being unpublished.

Stale translations remain public.

Draft saves never publish.

Publishing requires explicit administrator action.

Published translation transitions are atomic.

Missing translations fall back to English under the specified SEO contract.

**8. Zero External Translation Dependencies**

No third-party translation APIs.

No local heavy translation ML.

**9. Booking Flow Persistence**

Language switching preserves:

booking step;

slot;

hold;

timezone;

customer inputs

through server-side state without placing PII in URLs.

**10. RTL \& Typography**

Embedded Arabic learning content is isolated correctly and does not cause:

layout distortion;

punctuation inversion;

typography clipping;

document-wide RTL direction changes.

**11. Responsive Admin UX**

The admin drawer:

opens correctly;

traps focus;

closes via Escape/backdrop/close control;

restores focus;

preserves body-scroll integrity;

handles mobile data layouts;

avoids page-level horizontal overflow.

**12. Admin Link \& Indexing**

Public admin links are eliminated.

Protected admin functionality remains protected.

Administrative/authentication routes emit the required X-Robots-Tag.

No indexing strategy change compromises authentication/security.

**13. Scope Discipline**

Transactional email localization is outside V1.

No new unrequested email system is introduced.

**14. Zero Data Loss**

All existing:

English entities;

entity IDs;

relationships;

canonical slugs;

bookings;

customer records;

availability records;

business history

remain intact.

**36. REQUIRED IMPLEMENTATION REPORT**

After implementation, provide a concise but concrete implementation report.

**Analytics**

Report:

root causes of historical analytics discrepancies;

event-generation corrections;

post-commit dispatch changes;

visitor\_funnel\_progressions migration;

cohort calculation implementation;

observed/imputed logic;

maturity implementation;

reconciliation process;

daily rollup reconciliation results;

attribution implementation;

any Non-Comparable Historical Data ranges;

authoritative analytics cutover date.

**Localization**

Report:

translation table architecture;

database-engine-specific uniqueness implementation;

English source-revision architecture;

entity\_translation\_revisions implementation;

immutable snapshot behavior;

revision creation logic;

draft reconciliation behavior;

explicit Publish implementation;

fallback implementation;

canonical/hreflang behavior;

confirmation that no external translation API was integrated;

confirmation that no heavy local ML translation stack was introduced.

**Booking State**

Report:

session persistence mechanism;

language-switch implementation;

hold preservation behavior;

session-binding validation;

PII URL protection;

zero-duplicate-hold verification.

**Mobile Admin**

Report:

root cause of mobile navigation interference;

responsive layout corrections;

drawer/focus management;

scroll locking;

data-table/card behavior;

viewport verification.

**SEO \& Routing**

Report:

route registration;

trailing-slash redirects;

canonical URL implementation;

localized route behavior;

missing-translation fallback behavior;

hreflang generation/suppression;

X-Robots-Tag deployment status.

**Migration \& Data Integrity**

Report:

staging migration execution;

production-data copy verification;

entity-count comparison before/after;

translation-count comparison;

revision snapshot counts;

foreign-key/integrity verification;

confirmation that legacy data remained intact;

any deprecated legacy columns and whether/when they were removed.

**Testing \& Verification**

Report:

automated test execution results;

failed tests and fixes;

migration tests;

desktop viewport verification;

mobile viewport verification;

real-device QA where available;

final deployment readiness status;

any unresolved infrastructure-dependent limitations.

Do not claim a test, migration, reconciliation, or production deployment was completed unless it was actually executed and verified.