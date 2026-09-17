**FINAL DEVELOPMENT SPECIFICATION**

**Self-Hosted Egyptian Arabic Tutoring Website + Private Admin Platform**

**0. BUILD DIRECTIVE**

Build a production-quality, self-hosted web application for an independent Egyptian Arabic tutor.

This is **not a simple landing page**.

It is a small modular monolith consisting of:

A polished public-facing tutoring website.

A native booking system.

A scalable learning-resource library.

A games section ready for separately developed games.

A first-party analytics system.

A private administrative/business operations platform.

A lightweight structured CMS.

Reporting and XLSX/CSV export.

Secure authentication, backups, audit logging, and operational tooling.

The public experience should be extremely simple.

The administrative experience should be substantially more sophisticated.

**Fundamental principle**

**One application. One database. One source of truth.**

Do not use Calendly, Typeform, Google Forms, Google Analytics, ConvertKit, or comparable SaaS products for core V1 functionality.

Third-party infrastructure may be used where genuinely appropriate for infrastructure or security, but core business workflows must belong to this application.

This application is for **one independent educator**.

Do not build:

multi-tenant SaaS architecture

marketplace architecture

microservices

Kubernetes

distributed services

message buses

enterprise infrastructure

Prefer a **modular monolith**.

Do not over-engineer the system.

Do not under-engineer critical business logic.

**1. PRODUCT OBJECTIVE**

Visitors primarily arrive from:

YouTube

TikTok

Instagram

Telegram

WhatsApp

Search engines

Direct links

The website exists primarily to support three visitor actions:

**1. Book**

**Book a private Egyptian Arabic tutoring session**

This is the primary business conversion.

**2. Learn**

**Download a workbook or other learning resource**

The system must support a growing library rather than one PDF.

**3. Play**

**Play Egyptian Arabic learning games**

The initial version may contain placeholders or links to separately developed games.

The public website should encourage useful exploration, but engagement must never be achieved through manipulative design.

Do not use:

fake scarcity

fake countdowns

fake booking notifications

fake activity

forced popups

autoplay audio/video

scroll hijacking

intentionally confusing navigation

infinite scrolling solely to increase session duration

deceptive buttons

unclosable overlays

Optimize for **meaningful engagement and completed business actions**, not raw time-on-site.

**2. VERSION PRIORITY**

Use three implementation priorities.

**V1 CORE — REQUIRED FOR INITIAL PRODUCTION**

Public website

Booking system

Automatic timezone detection

Manual timezone override

Availability engine

Booking holds

Booking confirmation

.ics calendar generation

Contacts

Resource library

Email-gated resource access

Games section

Social links

Admin authentication

Admin dashboard

Booking/calendar management

Availability management

Customer/contact management

Lead/resource-request management

First-party analytics

UTM attribution

Traffic/conversion reporting

XLSX/CSV export

Security

Accessibility

Performance

Backups

Audit logging

Testing

**V1 ENHANCED — IMPLEMENT AFTER CORE FUNCTIONALITY IS STABLE**

Global admin search

Content revision history

Media library

Advanced dashboard drill-downs

Period comparison

Internal notification center

Customer duplicate detection/merge

Advanced resource analytics

System-health dashboard

**FUTURE — ARCHITECT FOR, DO NOT IMPLEMENT**

Payment processing

Customer accounts

Student dashboard

Automated booking emails

Marketing automation

Courses

Memberships

Subscriptions

Lesson-package balances

Game accounts

Learning progress

Multiple tutors

Multi-tenancy

Public API

Referral system

Coupons

Waitlists

Generic report builder

Do not let future functionality complicate V1 unnecessarily.

**3. TECHNICAL STACK**

Preferred stack:

Backend: Laravel/PHP

Database: PostgreSQL or MySQL

Frontend: Blade + Livewire/[Alpine.js](Alpine.js) or equivalent Laravel-native approach

CSS: Tailwind CSS

Authentication: secure server-side sessions

Charts: lightweight client-side chart library

XLSX: PhpSpreadsheet or equivalent

File storage: Laravel Storage abstraction

Local filesystem storage initially

Storage architecture must permit future object storage without changing business logic

Do not introduce React/[Next.js](Next.js) or another separate frontend stack unless a concrete requirement makes it preferable.

Keep dependencies minimal and justified.

**4. DEVELOPMENT RULE**

Before writing large amounts of UI code, first produce a technical blueprint containing:

architecture

domain boundaries

database entities/relationships

booking state model

availability algorithm

timezone model

analytics architecture

routes

permissions

storage architecture

major services/components

Validate the blueprint against this specification.

Then implement in the defined roadmap order.

Do not build a polished frontend on top of an unverified booking/data architecture.

**5. USER TYPES**

**Visitor**

Anonymous website visitor.

May:

browse

request resources

play games

initiate booking

complete booking

No account required.

**Contact**

A canonical person record identified primarily through normalized email.

A contact may be:

a resource lead

a customer

both

neither yet, depending on available information

Do not maintain separate independent identities for leads and customers.

**Administrator**

Two roles:

super\_admin

admin

**6. IDENTITY MODEL**

Use a canonical:

**contacts**

entity.

A person who requests a workbook and later books a session should normally be represented by the same contact record.

Normalize email addresses using:

trim

lowercase

Do not silently merge records based on weak matching.

When a duplicate contact is suspected:

flag it

show the evidence

allow manual merge

preserve historical records

soft-archive the duplicate

Do not silently merge customers.

A contact does not need an account/password.

**7. PUBLIC WEBSITE**

Required pages:

/

/book

/resources

/resources/{slug}

/games

/games/{slug}

/about

/faq

/privacy

/terms

Optional:

/contact

Support additional CMS-created public pages.

**8. PUBLIC NAVIGATION**

Primary navigation should remain compact:

Home

Book

Resources

Games

About

The Book action should have the strongest visual hierarchy.

Do not create a large navigation system.

The visitor should immediately understand:

**What is this?**

**What can I do?**

**How do I book?**

**9. HOMEPAGE**

The homepage must prioritize:

clarity

trust

useful exploration

booking conversion

Every section should answer at least one useful visitor question.

Remove filler.

**Hero**

Must quickly communicate:

Egyptian Arabic

tutor identity

audience/context

primary value

next action

Primary CTA:

**Book a Session**

Secondary:

**Get a Resource**

**Play Games**

The primary CTA must clearly dominate.

**10. PUBLIC UX / ENGAGEMENT**

The experience should be compelling because it is useful and pleasant.

Possible interactions:

pronunciation examples

language examples

expandable explanations

resource previews

contextual recommendations

game previews

subtle hover/press states

learning-oriented micro-interactions

Avoid gratuitous animation.

All animation must:

be purposeful

be performant

respect prefers-reduced-motion

never delay interaction

never obscure content

A visitor who books quickly is a successful conversion.

Do not interpret longer sessions as inherently better.

**11. VISUAL DIRECTION**

Design for a contemporary, Egypt-aware identity.

Avoid stereotypical visual shorthand such as automatically using:

pyramids

hieroglyphics

desert imagery

tourist-shop aesthetics

Do not use generic SaaS templates.

Do not use generic educational stock imagery unless specifically supplied.

Do not invent a visual identity around fake cultural symbolism.

Use:

strong typography

controlled whitespace

restrained color

refined components

subtle cultural character

modern editorial composition

The public website should feel like a real language educator's product.

**12. TYPOGRAPHY AND LANGUAGE**

Support Arabic and English correctly.

Use a coherent limited type system.

Possible Arabic families:

Cairo

Tajawal

IBM Plex Sans Arabic

Noto Sans Arabic

Noto Naskh Arabic

Amiri

Select based on the actual visual design.

Support proper:

RTL

LTR

language metadata

Arabic shaping

mixed-language content

Use dir correctly.

Do not simulate RTL by reversing visual layout manually.

Centralize interface strings so future localization is possible.

**13. MOBILE-FIRST DESIGN**

Assume substantial traffic comes from mobile social platforms.

Design mobile intentionally.

Requirements:

thumb-friendly navigation

readable typography

short public forms

touch-friendly calendar

obvious timezone control

simple resource access

clear CTA hierarchy

appropriate sticky CTA where genuinely useful

no tiny controls

no desktop-layout shrinkage

Support:

portrait

landscape

common Android browsers

Safari/iOS

Chrome

Firefox

Edge

**14. BOOKING SYSTEM — HIGHEST PRIORITY**

The booking system is the most important functional part of the application.

V1 has one configurable session type, but model session types as a real database entity.

**session\_types**

Fields:

id

title

description

duration\_minutes

price

currency

active

created\_at

updated\_at

Only one active session type is required for launch.

The design must permit additional session types later.

**15. BUSINESS TIMEZONE**

The tutor has a configurable business timezone.

Example:

Africa/Cairo

The current business timezone applies to future scheduling.

When a booking is created:

**snapshot the business timezone onto the booking.**

Changing the business timezone later must never silently rewrite historical bookings.

**16. CUSTOMER TIMEZONE — CRITICAL**

The booking flow must behave similarly to modern booking platforms such as Calendly regarding timezone handling.

**Automatic detection**

When the booking page loads, automatically detect the visitor's browser timezone using a browser-native mechanism such as:

[Intl.DateTimeFormat](Intl.DateTimeFormat)().resolvedOptions().timeZone

Prefer the browser's IANA timezone over IP geolocation.

Do not guess the timezone from country alone.

Example:

Berlin:

Europe/Berlin

New York:

America/New\_York

Cairo:

Africa/Cairo

If browser timezone detection fails:

fall back to the configured business timezone

make this visible

allow manual change

**17. TIMEZONE DISPLAY**

Display prominently:

**Your time zone: Europe/Berlin**

Prefer a friendly label while retaining an accessible way to inspect the IANA identifier.

Provide:

**Change time zone**

The timezone selector must:

search IANA zones

be keyboard accessible

work on mobile

contain recognizable city/region names

avoid a raw offset-only dropdown

**18. TIMEZONE OVERRIDE**

The visitor can manually override the detected timezone.

Once changed:

all available slots update

calendar times update

review updates

confirmation updates

.ics generation uses the selected timezone

The selected timezone must persist across the complete booking flow.

It must never silently switch back.

**19. TIMEZONE DATA MODEL**

For every booking store:

start\_at\_utc end\_at\_utc business\_timezone customer\_timezone business\_local\_date\_at\_booking business\_local\_start\_time\_at\_booking business\_local\_end\_time\_at\_booking customer\_local\_date\_at\_booking customer\_local\_start\_time\_at\_booking customer\_local\_end\_time\_at\_booking business\_utc\_offset\_at\_booking customer\_utc\_offset\_at\_booking 

**Canonical truth**

start\_at\_utc and end\_at\_utc represent the actual appointment instant.

The IANA timezone identifiers are retained to explain/render the appointment in each relevant timezone.

The historical local-date/time and offset values preserve exactly what the user and business saw at booking time.

The offsets are **historical snapshots only**.

Never use an offset as the scheduling source of truth.

Never store:

+02:00

as the sole representation of timezone.

**20. TIMEZONE RULE-CHANGE PRINCIPLE**

An already-created booking represents a specific instant.

If timezone rules change after booking:

**do not silently move the appointment to a different instant.**

The stored UTC instant remains authoritative.

The stored IANA timezone and historical local representation preserve the original booking context.

For future availability generation, always use the current timezone database/rules.

This distinction must be explicitly documented in code.

**21. TIMEZONE CONVERSION ARCHITECTURE**

Create centralized timezone utilities/services.

Do not implement timezone conversion independently in:

frontend calendar

availability service

booking confirmation

admin calendar

.ics generator

All conversion logic must use the same underlying library and defined service layer.

Do not manually calculate offsets.

**22. DST REQUIREMENTS**

Test:

nonexistent spring-forward local times

duplicated fall-back local times

differing DST rules between customer and business

midnight boundary crossings

date changes between zones

future appointments around DST transitions

Never silently round a nonexistent time.

Use a deterministic and documented resolution strategy for ambiguous fall-back times.

If an available slot itself maps to a timezone ambiguity, do not display two visually identical ambiguous times without disambiguation.

**23. AVAILABILITY ENGINE**

Availability is generated server-side.

Calculate available slots from:

Weekly recurring availability

Date-specific exceptions

Existing bookings

Active holds

Session duration

Buffer time

Minimum booking notice

Maximum booking horizon

Date-specific rules override recurring rules for that date.

The final server-side booking transaction is authoritative.

The browser's displayed availability is advisory.

**24. RECURRING AVAILABILITY**

availability\_rules must support:

weekday

enabled

start time

end time

optional session-duration override

optional buffer override

optional minimum-notice override

optional maximum-horizon override

Support multiple intervals per weekday.

Example:

Monday:

09:00–13:00

15:00–19:00

Avoid designing the V1 UI as an overly complicated calendar grid.

A clear repeatable row-based schedule editor is sufficient.

**25. AVAILABILITY EXCEPTIONS**

Support:

**Blocked date**

Entire date unavailable.

**Special hours**

Date has custom availability.

For example:

September 20:

12:00–16:00

Admin must be able to easily understand which dates are:

normal

modified

blocked

**26. BOOKING HOLDS**

Implement temporary slot holds.

Create:

**booking\_holds**

Fields:

id

visitor/session identifier

slot start UTC

slot end UTC

created\_at

expires\_at

released\_at

status

The hold duration should be configurable within a safe range, with a sensible default such as 5–10 minutes.

A hold:

is not a booking

cannot become visible as confirmed

expires automatically

prevents another visitor from selecting the same slot during the hold

is revalidated server-side during final booking

Implement cleanup through scheduled jobs plus expiry checks during availability queries.

**27. BOOKING RACE CONDITION**

Two users must never be able to successfully book the same slot.

The final booking operation must:

validate current availability

validate active hold where applicable

run inside a database transaction

enforce the conflict constraint/locking mechanism at the database level

Do not rely only on frontend state.

Do not rely only on availability caching.

**28. BOOKING FLOW**

**Step 1 — Session**

If only one active session type exists, skip the visible selection step.

Otherwise allow selection.

**Step 2 — Date**

Show the calendar in the customer's selected timezone.

Only dates with at least one valid slot should be selectable.

**Step 3 — Time**

Show only valid available slots.

Immediately create a short-lived hold when the visitor selects a slot.

**Step 4 — Details**

Collect:

name

email

WhatsApp/phone

optional note

Do not collect unnecessary personal data.

**Step 5 — Review**

Show:

session

duration

price

date

time

customer timezone

business timezone

customer details

Allow going backward without losing entered data.

**Step 6 — Confirmation**

Show:

booking reference

customer-local date/time

business date/time

both timezones

next steps

.ics download

**29. IDEMPOTENCY**

Booking submission must contain an idempotency key.

If the same submission is repeated:

do not create another booking

return/reference the existing booking

Handle:

double click

browser retry

network retry

refresh during confirmation

**30. BOOKING STATES**

V1 booking statuses:

Confirmed

Cancelled

Completed

No-show

Reserve:

Pending

for future payment/manual-confirmation workflows.

Do not model “Rescheduled” as a permanent booking status.

Rescheduling is an action that changes the appointment time and creates historical booking events.

**31. BOOKING HISTORY**

Create:

**booking\_events**

Fields:

id

booking\_id

event\_type

performed\_by

previous\_data JSON

new\_data JSON

created\_at

Events may include:

created

rescheduled

cancelled

confirmed

completed

marked\_no\_show

restored/reopened where applicable

Never lose historical booking information when a booking is rescheduled.

**32. RESCHEDULING**

When an administrator reschedules:

Validate the new slot.

Validate current availability.

Acquire/reserve the new slot atomically.

Preserve original booking history.

Update the current booking instant.

Record a booking\_events entry.

Release the old slot.

Preserve the customer.

Record analytics/audit information.

Do not overwrite the original state without history.

The customer's timezone should remain the timezone they originally selected unless explicitly changed as part of a future customer-management workflow.

**33. CANCELLATION**

Cancellation:

changes status

sets cancelled\_at

preserves history

releases the slot

creates booking event

creates audit event

appears in reporting

Never physically delete a cancelled booking.

**34. BOOKING CONFIRMATION ACCESS**

Because customers have no accounts, create a secure non-guessable booking confirmation token.

Example concept:

/book/confirmation/{secure-token}

Requirements:

token must be cryptographically unpredictable

never expose sequential database IDs

do not expose unrelated customer records

confirmation page must reveal only necessary booking information

Support refreshing/reopening the confirmation page.

**35. .ICS CALENDAR FILE**

Generate a valid calendar event containing:

title

description

start

end

booking reference

appropriate timezone semantics

Use UTC Z timestamps or a correctly constructed VTIMEZONE.

Do not emit ambiguous timezone-less local times.

The file must correspond to the exact booked UTC instant.

**36. BOOKING POLICIES**

Create editable booking policy content:

cancellation policy

rescheduling policy

minimum notice explanation

booking instructions

what happens after booking

Policy enforcement and policy wording are separate.

The system must not claim to enforce a policy that has not actually been implemented.

**37. RESOURCE LIBRARY**

Build a scalable resource library.

Resources are not hardcoded PDFs.

Examples:

Egyptian Arabic survival workbook

Numbers cheat sheet

food vocabulary

pronunciation guide

phrases

travel vocabulary

culture guides

future workbooks

**38. RESOURCE CATEGORIES**

Use:

**resource\_categories**

Fields:

id

name

slug

sort\_order

active

Do not use free-text categories.

**39. RESOURCE ENTITY**

resources fields:

id

category\_id

title

slug

short\_description

full\_description

file reference

file type

file size

cover image reference

status

featured

sort\_order

published\_at

created\_at

updated\_at

deleted\_at

Status:

draft

published

archived

Do not expose unpublished content publicly.

**40. RESOURCE REQUESTS**

Create a dedicated:

**resource\_requests**

entity.

A request is:

successful submission of the resource email gate

Fields:

id

contact\_id

resource\_id

visitor/session attribution

source

medium

campaign

content

term

landing\_page

created\_at

One contact can request multiple resources.

Do not collapse all requests from the same email into one record.

**41. RESOURCE DOWNLOADS**

Create:

**resource\_downloads**

A download means:

The application successfully began serving the requested resource file.

Fields:

id

resource\_id

contact\_id nullable

request\_id nullable

visitor/session identifier where appropriate

created\_at

Do not claim to know whether the user's device physically completed the download.

**42. RESOURCE GATE**

Flow:

**Get Resource**

→ email form

→ optional name

→ validation

→ create/update contact

→ create resource request

→ record analytics event

→ immediately provide resource

No automated marketing email.

Do not tell the user to check their inbox.

Resource access does not equal marketing consent.

A future marketing opt-in must be a separate, clearly worded mechanism.

**43. RESOURCE DOWNLOAD SECURITY**

Store files in non-public storage.

Serve through application-controlled routes.

Validate:

authorization/publication state

allowed file type

file size

MIME type

safe filename

storage location

Never allow uploaded files to execute as application code.

Never expose filesystem paths.

**44. MEDIA LIBRARY**

Create a lightweight media-management system.

Supports:

upload

replace

delete/archive

filename

type

dimensions

size

storage reference

Used for:

logo

favicon

resource covers

game thumbnails

About image

Open Graph images

other CMS media

Do not create a complex WordPress-style media product.

**45. GAMES**

Create:

**games**

Fields:

id

title

slug

description

thumbnail

target URL/route

status

featured

sort\_order

created\_at

updated\_at

Statuses:

coming\_soon

available

archived

V1 may display placeholders.

Architect for future internally integrated games.

**46. GAME ANALYTICS**

Track:

game\_opened

game\_started

game\_completed

Additional game-specific events can be added later.

Do not require accounts for games in V1.

**47. STRUCTURED CMS**

Build a small structured CMS.

Do not build an arbitrary visual page builder.

Admin-editable:

**Homepage**

hero

teaching approach

resource introduction

games introduction

final CTA

footer text

**About**

biography

teaching philosophy

image

**FAQ**

question

answer

order

active

**Pages**

title

slug

content

excerpt

status

SEO metadata

OG image

published\_at

**48. CONTENT REVISIONS**

Published content must not be modified directly in a way that unexpectedly changes the live page.

Use a revision/draft model.

A change to live content should create/update a draft revision.

Workflow:

**Published → Edit Draft → Preview → Publish**

Publishing makes the draft live.

Archived content is not publicly visible.

**49. CONTENT PREVIEW**

Provide authenticated preview of unpublished content.

Preview:

resources

games

pages

homepage content

FAQ

Preview URLs must not leak unpublished content to unauthenticated visitors.

**50. SOCIAL LINKS**

Admin-editable:

Instagram

TikTok

YouTube

Telegram

WhatsApp

Fields:

platform

URL or phone configuration

label

enabled

sort order

Do not hardcode URLs.

**51. WHATSAPP-SPECIFIC SUPPORT**

WhatsApp should optionally support:

phone number

country code

default message

The application may generate a properly formed WhatsApp link.

Example conceptual message:

“Hi, I came from your Egyptian Arabic content and would like to ask about a session.”

The message must be editable.

Do not expose or invent a number.

**52. ADMIN AUTHENTICATION**

Use secure server-side authentication.

Requirements:

password hashing using framework-recommended method

secure session cookies

logout

login rate limiting

brute-force mitigation

password reset

secure reset tokens

reset-token expiration

no password logging

V1 password reset may use a transactional security-email channel.

This is separate from marketing/booking automation.

**53. ADMIN ROLES**

**Super Admin**

Everything, including:

administrator management

security/system configuration

backups

**Admin**

Can operate:

bookings

calendar

contacts

leads

resources

games

content

analytics

reports

But cannot perform sensitive administrator/system operations unless explicitly authorized.

Implement authorization policies server-side.

**54. ADMIN DASHBOARD**

The dashboard is an operations console.

Default order:

**1. Schedule**

today's bookings

next upcoming booking

this week's schedule

**2. Needs attention**

new bookings

cancellations

new resource requests

system warnings

backup failures

**3. KPI snapshot**

Selectable:

today

yesterday

7 days

30 days

custom

Metrics:

unique visitors

sessions

page views

active visitors

bookings

completed bookings

cancellations

resource requests

downloads

games

social clicks

**4. Conversion overview**

Visitors → booking CTA → booking started → booking completed

**5. Traffic overview**

Source/channel performance

**6. Resource performance**

Top resources

**7. Recent activity**

Recent operational events

**55. ADMIN INFORMATION ARCHITECTURE**

Use:

**Overview**

Dashboard

**Business**

Bookings

Calendar

Contacts

Leads

**Content**

Resources

Games

Pages

FAQ

Media

**Analytics**

Overview

Traffic

Conversions

Resources

Events

Reports

**Website**

Social

SEO

Settings

**System**

Administrators

Audit Log

Backups

System Health

Keep the navigation understandable.

**56. ADMIN GLOBAL SEARCH**

Implement a simple database-backed global search.

Search by:

contact name

email

phone

booking reference

resource title

Results should link to the relevant record.

Do not introduce Elasticsearch or an external search platform for V1.

**57. ADMIN BOOKING INTERFACE**

Provide:

day view

week view

calendar view

list/upcoming view

Search/filter:

customer

date

status

source

Operations:

create

confirm

cancel

reschedule

complete

no-show

notes

view contact

view history

Default calendar/filter timezone:

**business timezone**

Show both:

**Business time**

and

**Student time**

on every booking detail.

**58. ADMIN AVAILABILITY INTERFACE**

Support:

recurring weekly schedule

exceptions

special hours

blocked dates

duration

buffers

minimum notice

booking horizon

business timezone

Changes must invalidate appropriate availability caches.

**59. CONTACT MANAGEMENT**

Contact profile:

name

normalized email

display email

phone/WhatsApp

first seen

last seen

lead/resource activity

bookings

booking counts by status

notes

attribution

Do not store more personal information than necessary.

**60. DUPLICATE CONTACTS**

When potential duplicates exist:

Display:

Possible duplicate

Give admin:

**Review**

→ compare

→ **Merge**

Merge should:

retain one canonical contact

move related references

preserve resource requests

preserve bookings

preserve history

soft-archive duplicate

record audit event

Never silently merge.

**61. LEADS VIEW**

“Leads” should be a behavior-based admin view over contacts who have requested resources but may not have booked.

Provide:

email

name

resources requested

request dates

source

campaign

booking status

Do not create two unrelated identity systems for “lead” and “customer.”

**62. SETTINGS**

Centralize business-configurable settings.

**General**

site name

default language

maintenance mode

default timezone

**Branding**

logo

favicon

default OG image

**Contact**

email

WhatsApp

**Booking**

active session type

business timezone

session settings

booking policies

**Analytics**

active visitor window

session timeout

retention period

consent behavior

**SEO**

default title

description

social image

robots configuration

**63. MAINTENANCE MODE**

Admin-configurable toggle.

When enabled:

Public visitors see a simple maintenance page.

Admins remain able to log in and operate the system.

Do not expose sensitive system information on the maintenance page.

**64. FIRST-PARTY ANALYTICS**

Do not rely on third-party analytics.

Build a lightweight first-party analytics pipeline.

Core concepts:

anonymous visitor

session

event

attribution

daily rollups

**65. VISITOR IDENTITY**

Use a first-party pseudonymous cookie as the primary anonymous visitor identifier.

Use localStorage only for supplemental client-side state where helpful, such as:

temporary booking state

manually selected timezone

interface preferences

Do not depend on localStorage for server-side visitor identity.

Do not fingerprint users.

Do not attempt invasive cross-device identification.

**66. SESSION MODEL**

Default session timeout:

**30 minutes inactivity**

Configurable.

Session fields:

visitor ID

started\_at

last\_activity\_at

A new session begins after the inactivity threshold.

**67. ACTIVE NOW**

Default:

**5-minute recent activity window**

Admin-configurable:

**1–30 minutes**

A visitor is “active” when an eligible event/activity was recorded within that window.

Always label it:

**Estimated active visitors**

Never present it as an exact number of humans physically viewing the website.

Admin can view:

current page

device

source

time active

Do not reveal personally identifying information.

**68. ANALYTICS STORAGE**

Use two tiers.

**Raw events**

analytics\_events

Fields:

event\_name

created\_at

visitor\_id/session\_id

page

source

medium

campaign

content

term

referrer

metadata JSON

Default retention:

**180 days**

Configurable.

**Daily rollups**

daily\_metrics

Store pre-aggregated daily figures.

Dashboard summaries and date-range charts should primarily use rollups.

Raw events should be used for detailed event-level exploration.

**69. ANALYTICS EVENTS**

Implement a central event service.

Core events:

page\_view session\_started booking\_cta\_clicked booking\_started booking\_slot\_held booking\_completed booking\_cancelled booking\_rescheduled resource\_gate\_viewed resource\_requested resource\_downloaded game\_opened game\_started game\_completed social\_link\_clicked whatsapp\_clicked telegram\_clicked outbound\_link\_clicked faq\_opened navigation\_click 

All server-side event names must be validated against an allow-list.

Do not accept arbitrary event names from public clients.

**70. EVENT DEDUPLICATION**

For events where duplicate user actions are especially likely:

client-side debounce

server-side short-window duplicate protection

Examples:

resource requests

downloads

booking submissions

Do not allow a double click to inflate business metrics.

Do not globally deduplicate events that are legitimately repeated.

**71. BOT FILTERING**

Use lightweight multi-signal filtering:

maintained known-bot/user-agent patterns

known crawler signatures

suspicious request behavior

rate characteristics

known link-preview behavior where identifiable

Particularly consider:

WhatsApp previews

Telegram previews

Slack previews

Discord previews

search crawlers

Do not rely solely on User-Agent.

Where useful, classify traffic as:

human/included

known bot/excluded

suspicious/uncertain

Do not pretend bot detection is perfect.

**72. UTM ATTRIBUTION**

Capture:

utm\_source

utm\_medium

utm\_campaign

utm\_content

utm\_term

referrer

Persist attribution through the session.

Associate attribution with:

resource requests

bookings

game interactions

This must answer:

**Where did this booking come from?**

**73. CONTENT ATTRIBUTION**

UTM content should support granular content attribution.

Example:

utm\_source=youtube utm\_medium=description utm\_campaign=survival-arabic utm\_content=video-17 

Admin should eventually be able to see:

Video 17 → visitors → resource requests → bookings

Do not build a separate campaign-management platform.

**74. ANALYTICS METRIC DEFINITIONS**

Document and implement:

MetricDefinitionPage ViewSuccessful public page render/event excluding filtered bot trafficSessionVisitor activity grouped by configured inactivity timeoutUnique VisitorAnonymous visitor identifier counted once per selected periodActive VisitorVisitor with eligible recent activity within active windowBooking StartedVisitor reaches the date-selection stageBooking CompletedConfirmed booking successfully createdResource RequestSuccessful resource email-gate submissionResource DownloadApplication successfully begins serving the resource file 

Do not redefine these differently elsewhere.

**75. PRIMARY BUSINESS FUNNEL**

Track:

Visitor ↓ Booking CTA ↓ Booking Started ↓ Slot Held ↓ Booking Completed 

Secondary funnel:

Visitor ↓ Resource Viewed ↓ Gate Viewed ↓ Resource Requested ↓ Resource Downloaded 

Game funnel:

Visitor ↓ Game Opened ↓ Game Started ↓ Game Completed 

Do not force all visitors into one funnel.

**76. CONVERSION METRICS**

Primary:

**Completed bookings**

Secondary:

booking CTA rate

booking-start rate

booking-completion rate

resource-request rate

resource-download rate

game engagement

social click rate

Do not treat:

time-on-site

page count

animation interactions

as business success metrics by themselves.

**77. PRIVACY**

First-party analytics only by default.

Store the minimum useful data.

IP address:

may be used transiently for rate limiting/abuse prevention

short retention, e.g. 30 days

never long-term attached to customer/lead records

If country is desired:

derive country where genuinely useful

store country code instead of long-term raw IP

Do not fingerprint users.

Do not use cross-site advertising identifiers.

Consent behavior must be configurable according to deployment/legal requirements.

This specification is not legal advice.

**78. DATA MANAGEMENT**

Provide an operational approach for:

contact anonymization/deletion

personal-data review

retention

resource request history

booking history

When personal data is removed, retain non-personal aggregate analytics where appropriate.

Do not silently destroy accounting/business history merely because a personal field was anonymized.

**79. REPORTING**

Provide five fixed reports.

**Traffic**

visitors

sessions

page views

source

date

**Bookings**

customer

date

time

status

source

campaign

**Resources**

resource

requests

downloads

conversion

**Social**

platform

clicks

page

date

**Events**

event

count

page

source

date

Do not build a generic report builder in V1.

**80. PERIOD COMPARISON**

Support:

this week vs previous week

this month vs previous month

custom period vs equivalent previous period

Present comparisons descriptively.

Do not imply causal relationships merely because a metric moved.

**81. EXPORT**

All important reports and filtered datasets support:

CSV

XLSX

Exports respect active filters/date ranges.

Use readable headers.

Example:

Customer Name

not:

customer\_id

**82. EXPORT SECURITY**

Protect against spreadsheet formula injection.

Any untrusted cell beginning with:

\= + - @ 

must be escaped/neutralized before being written to CSV/XLSX.

Protect export files through:

admin authorization

temporary storage

sensible expiration/deletion

no public URLs

**83. ADMIN NOTIFICATIONS**

Internal-only V1 notification center.

Events:

new booking

cancellation

new resource request

backup failure

system warning

No email/SMS notifications from these in V1.

Avoid notification spam.

**84. AUDIT LOG**

Track meaningful administrative actions.

Fields:

administrator

action

entity

entity ID

previous data where necessary

new data where necessary

timestamp

relevant metadata

Audit:

booking cancellation

rescheduling

availability changes

resource deletion

content publication

settings changes

administrator changes

contact merge

security-sensitive actions

**85. SECURITY**

Implement:

secure sessions

secure cookies

password hashing

CSRF

XSS protection

ORM/parameterized DB access

server-side validation

authorization policies

rate limiting

login throttling

secure password reset

secure file uploads

path-traversal protection

access control

safe resource downloads

HTTP security headers

production-safe error handling

secrets via environment variables

Never rely on hidden fields for authorization.

Never trust browser-computed business logic.

**86. PUBLIC WRITE-ENDPOINT PROTECTION**

Rate-limit independently:

booking

availability/slot-hold creation

resource requests

analytics ingestion

admin login

Use:

rate limiting

honeypot fields

request validation

Introduce CAPTCHA only if abuse demonstrates the need.

**87. ANALYTICS INGESTION SECURITY**

Analytics endpoint must:

validate allowed events

validate metadata structure/size

rate-limit

reject unreasonable payloads

avoid storing arbitrary sensitive values

avoid allowing clients to fake critical server-side business metrics

Critical events such as actual booking completion should be generated server-side.

**88. RESOURCE UPLOAD SECURITY**

Restrict:

allowed extensions

MIME types

file sizes

filenames

Store uploaded files outside executable public paths.

Do not trust MIME type alone.

Where appropriate, inspect file signatures.

**89. ACCESSIBILITY**

Target WCAG 2.2 AA.

Must support:

keyboard navigation

visible focus

semantic headings

labels

accessible errors

semantic links/buttons

accessible modal/dialog behavior

accessible calendar

accessible timezone picker

no keyboard traps

adequate contrast

reduced-motion preferences

pointer targets appropriate to WCAG 2.2

Do not communicate important state through color alone.

**90. PERFORMANCE**

Treat performance as a core product requirement.

Optimize:

server rendering

caching

asset sizes

images

fonts

JavaScript

database queries

analytics queries

Monitor:

LCP

INP

CLS

Do not add heavy client-side frameworks unnecessarily.

**91. DATABASE PERFORMANCE**

Use indexes based on actual query patterns.

Likely indexes include:

booking timestamps

booking status

normalized email

resource IDs

event names

visitor/session IDs

timestamps

attribution fields where justified

Use pagination on all unbounded datasets.

Do not blindly index every column.

**92. DATABASE ENTITIES**

At minimum:

administrators contacts session\_types bookings booking\_events booking\_holds availability\_rules availability\_exceptions resource\_categories resources resource\_requests resource\_downloads games pages faqs content\_revisions media social\_links settings visitors sessions analytics\_events daily\_metrics audit\_logs 

Use framework-managed migration files.

**93. BOOKING DATABASE RULES**

bookings must include:

id contact\_id session\_type\_id start\_at\_utc end\_at\_utc business\_timezone customer\_timezone business\_local\_date\_at\_booking business\_local\_start\_time\_at\_booking business\_local\_end\_time\_at\_booking customer\_local\_date\_at\_booking customer\_local\_start\_time\_at\_booking customer\_local\_end\_time\_at\_booking business\_utc\_offset\_at\_booking customer\_utc\_offset\_at\_booking status idempotency\_key confirmation\_token notes source medium campaign content term cancelled\_at completed\_at created\_at updated\_at deleted\_at 

Add payment fields only if/when payment is implemented.

**94. SOFT DELETION**

Use soft deletion where appropriate for:

contacts

bookings

resource records

other business entities where historical preservation matters

Never hard-delete by default.

Administrative deletion must be explicit and audited.

Do not use soft deletion as an excuse to retain sensitive personal data forever; privacy/data-retention rules still apply.

**95. CMS / MEDIA STORAGE**

Store media through the storage abstraction.

Do not scatter hardcoded filesystem paths through templates.

Database references should identify logical media objects.

**96. SEO**

Implement:

semantic HTML

title tags

meta descriptions

canonical URLs

Open Graph

social card metadata

sitemap

[robots.txt](robots.txt)

clean URLs

appropriate structured data

Do not build a giant SEO management platform.

Do not invent structured data.

**97. ERROR STATES**

Every important interaction must have:

loading

success

validation failure

empty

server failure

Booking must handle:

no availability

slot disappearing

expired hold

duplicate submission

network failure

server failure

Resources:

invalid email

unavailable resource

upload/storage failure

Admin:

save failure

authorization failure

destructive-action confirmation

Never leave the user uncertain whether an action succeeded.

**98. CUSTOMER-FACING BOOKING FAILURE**

If a chosen slot disappears before submission:

Tell the visitor clearly:

This time was just taken. Please choose another available time.

Return them to time selection without losing unnecessary form data.

Do not present a generic:

Something went wrong.

when the actual issue is understandable.

**99. CONTENT LOADING / ERROR HANDLING**

Public CMS content must gracefully handle:

missing images

unavailable resource

unpublished resource

missing optional sections

Never expose developer stack traces.

**100. ADMIN UX PRINCIPLES**

Admin interface should optimize for:

**clarity \> decoration**

Use:

sidebar

clear headings

contextual actions

filters

search

pagination

useful tables

responsive cards

empty states

confirmation dialogs

Public and admin design may differ visually but should share design tokens.

**101. DESIGN SYSTEM**

Create shared tokens for:

typography

spacing

radius

shadows

colors

component states

Reusable components:

buttons

inputs

selects

cards

tables

badges

alerts

modals

dropdowns

tabs

pagination

date controls

charts

empty states

loading states

Do not create two unrelated design systems for public/admin.

**102. ADMIN TABLES**

Tables should support:

sorting where useful

search

filters

pagination

responsive layout

Mobile may convert complex rows into cards.

Avoid forcing desktop tables into tiny mobile screens.

**103. ADMIN EMPTY STATES**

Examples:

No bookings yet. Your upcoming sessions will appear here.

No resources have been published yet.

Not enough data to display this report.

Provide an appropriate next action.

**104. WEBSITE CONTENT**

Do not invent:

tutor biography

qualifications

testimonials

student counts

prices

social URLs

reviews

Use editable placeholders until real content is provided.

Never seed production with fake performance data.

**105. MAINTENANCE / DEPLOYMENT**

Provide:

.[env.example](env.example)

migrations

installation instructions

storage setup

admin creation

cron/scheduled-task setup

production deployment

backup configuration

restore process

dev/staging/production distinction

No production debug mode.

**106. BACKUPS**

Automated backup:

database

uploaded files

Default database retention:

**30 days**

Configurable.

Admin System area should show:

last backup

result

failure

backup age

Backups must not live only on the same physical storage system as the production database without another recovery copy.

The implementation should make off-host backup storage possible.

**107. RESTORE TEST**

Document an actual restore procedure.

Periodically test restoration.

A backup is not considered reliable simply because a dump file exists.

**108. CRON / SCHEDULED TASKS**

Use scheduled jobs where required for:

backup

hold cleanup

analytics rollups

raw event retention

expired export cleanup

session cleanup

temporary-file cleanup

Do not build scheduled jobs for functionality that doesn't need them.

**109. SYSTEM HEALTH**

Admin System section should show basic:

application status

database connectivity

storage status

disk usage if available

latest backup

failed jobs

application version

scheduled-task health

Do not expose sensitive diagnostics publicly.

**110. LOGGING**

Use structured application logs.

Never log:

passwords

tokens

API secrets

unnecessary personal data

Make important errors diagnosable.

**111. ADMIN DATA RETENTION**

Retention must be configurable.

At minimum:

raw analytics events: default 180 days

transient IP/rate-limit records: short retention

daily aggregate metrics: long-term

business records: retained according to business/privacy policy

Document what is deleted, anonymized, or retained.

**112. GLOBAL CONFIGURATION**

Never hardcode business configuration into multiple code paths.

Centralize:

timezone

booking settings

business information

social links

analytics settings

retention

file limits

SEO defaults

Secrets belong in environment configuration.

Business-editable settings belong in the database/settings layer.

**113. TESTING — AUTOMATED**

At minimum test:

**Booking**

availability

unavailable slot

race condition

holds

hold expiration

cancellation

rescheduling

minimum notice

maximum horizon

exception rules

idempotency

**Timezone**

auto detection

manual override

UTC conversion

DST gap

DST duplicate hour

date boundary crossing

.ics

business/customer display

**Contacts**

email normalization

duplicate detection

merge

**Resources**

gate

request

download

download deduplication

unpublished resource protection

malicious upload rejection

**Analytics**

page view

sessions

visitors

active visitor

attribution

bot filtering

event validation

daily rollups

**Security**

authentication

authorization

CSRF

uploads

rate limits

access boundaries

**114. MANUAL QA**

Test:

desktop

mobile

tablet

slow network

keyboard-only

screen reader basics

Chrome

Safari

Firefox

Edge

Test the complete user journeys from start to finish.

**115. CRITICAL BOOKING QA MATRIX**

At minimum test:

**Scenario A**

Tutor in Cairo.

Student in Berlin.

Booking displayed in Berlin time and admin displayed in Cairo time.

**Scenario B**

Student manually switches Berlin → New York.

All subsequent times update.

**Scenario C**

Booking crosses midnight between zones.

Correct dates appear independently for student and admin.

**Scenario D**

DST transition.

Correct slot behavior.

**Scenario E**

Two users select same slot.

Only one can finally book it.

**Scenario F**

Visitor refreshes confirmation page.

Same booking appears.

**Scenario G**

Duplicate booking submission.

One booking only.

**Scenario H**

Admin reschedules.

Original time preserved in history.

**Scenario I**

Admin blocks a date while visitor has an open booking flow.

Final booking is rejected correctly.

**Scenario J**

Hold expires.

Another visitor can book the slot.

**116. BUSINESS DATA CONSISTENCY**

The system must use one canonical source for:

booking state

contact identity

resource identity

analytics definitions

Do not implement competing versions of the same metric/business rule in different modules.

**117. ADMIN DASHBOARD VS ANALYTICS VS REPORTS**

Maintain a deliberate distinction:

**Dashboard**

**What requires attention now?**

**Analytics**

**What are visitors doing?**

**Reports**

**What exact data do I need to inspect/export?**

Do not duplicate the same interface three times.

**118. PUBLIC CONVERSION DESIGN**

Primary CTA:

**Book a Session**

Secondary:

**Explore Resources**

**Play Games**

CTA placement should be contextual:

hero

teaching explanation

relevant resource/game area

end of page

Do not make every scroll section a giant sales CTA.

**119. RESOURCE DISCOVERY**

Resource cards should communicate:

title

useful context

category

short description

clear CTA

Support:

featured resources

categories

ordering

published state

A growing resource library should remain usable without rebuilding the template.

**120. CONTENT RELATIONSHIPS**

Support contextual recommendations.

Resource → related games → booking.

Game → related resources → booking.

Informational page → relevant resources → booking.

These relationships should be stored/configurable where useful rather than hardcoded.

**121. SOCIAL CLICK TRACKING**

Track:

platform

page

timestamp

UTM attribution

For WhatsApp/Telegram, track through the same central event system before navigation where technically appropriate.

Do not prevent legitimate navigation merely because analytics recording failed.

**122. OUTBOUND LINKS**

External links should be tracked where useful.

Analytics failure must never break the user's action.

**123. ACCESSIBLE INTERACTION PRINCIPLE**

Interactive UI must remain usable without:

mouse hover

animation

high visual acuity

precise touch gestures

Drag-and-drop must never be the only way to reorder content.

**124. NO FAKE DATA**

Demo/test data must be clearly identifiable.

Before production:

remove demo bookings

remove demo visitors

remove demo leads

remove demo analytics

remove demo customers

Production dashboard must start from a truthful state.

**125. VERSIONING / DEPLOYMENT SAFETY**

Application updates should use:

migrations

versioned code

environment-specific configuration

Do not modify production database structure manually as a normal deployment process.

**126. ROLLBACK THINKING**

Where migrations can be destructive:

warn explicitly

back up first

document rollback implications

Do not assume every migration can safely be reversed.

**127. README REQUIREMENTS**

README must explain:

project purpose

architecture

technology stack

local setup

environment variables

database setup

storage

initial admin creation

scheduled tasks

testing

deployment

backups

restore

analytics metrics

booking architecture

timezone architecture

permission system

key service locations

A competent developer should understand the system without reading the entire codebase.

**128. IMPLEMENTATION ROADMAP**

**Phase 1 — Foundation**

Build:

Laravel foundation

database

auth

roles

storage

core entities

migrations

domain services

analytics event architecture

Do not build the full visual UI yet.

**Phase 2 — Booking Engine**

Build:

availability

timezone detection

timezone picker

timezone conversion

booking holds

race protection

idempotency

booking lifecycle

booking events

confirmation tokens

.ics

Test thoroughly before proceeding.

**Phase 3 — Public Website**

Build:

homepage

book page

resources

games

about

FAQ

privacy/terms

social integration

CMS rendering

**Phase 4 — Admin Core**

Build:

dashboard

bookings

calendar

availability

contacts

leads

resource management

games

pages

FAQ

settings

**Phase 5 — Analytics \& Reports**

Build:

event pipeline

visitor/session system

attribution

bot filtering

deduplication

rollups

analytics dashboards

reports

CSV/XLSX exports

**Phase 6 — Enhanced Operations**

Build:

global search

media library

revision workflow

advanced admin drill-downs

internal notifications

system health

**Phase 7 — Hardening**

Perform:

security review

accessibility review

performance optimization

rate-limit review

backup configuration

restore drill

logging/observability review

**Phase 8 — QA**

Run:

automated tests

timezone tests

concurrency tests

manual cross-browser testing

mobile testing

accessibility testing

production deployment rehearsal

**129. FINAL ACCEPTANCE CRITERIA**

The application is not complete merely because the pages render.

A release is acceptable only when all of the following work:

**Booking**

Visitor timezone automatically detected.

Visitor can manually change timezone.

Selected timezone persists throughout flow.

Available times display correctly in selected timezone.

Business sees booking correctly in business timezone.

Customer sees booking correctly in customer timezone.

Exact booked UTC instant is preserved.

DST edge cases behave deterministically.

Two simultaneous submissions cannot both book the slot.

Duplicate submissions do not create duplicates.

Holds expire correctly.

Cancellation releases slot.

Rescheduling preserves history.

Confirmation can be refreshed.

.ics represents the correct instant.

**Resources**

Multiple resources can exist.

Categories work.

Draft/published/archive states work.

Email gate works.

Resource request and download are separate records.

Download is securely served.

Resource statistics are accurate.

**Games**

Multiple games can exist.

Placeholder/available states work.

Analytics events fire.

**Contacts**

Leads and customers share canonical contact identity.

Email normalization works.

Duplicate detection works.

Manual merge preserves history.

**Admin**

Secure login.

Role-based authorization.

Dashboard works.

Calendar works.

Availability editor works.

Customers/contacts work.

Resources work.

Games work.

CMS works.

Analytics work.

Reports work.

CSV/XLSX exports work.

Audit log works.

Settings work.

**Analytics**

Visitors counted consistently.

Sessions calculated consistently.

Active visitors operate on the configured window.

Bot traffic is filtered appropriately.

UTM attribution persists.

Booking/resource/game events are recorded.

Dashboard uses correct aggregation.

Metrics match documented definitions.

**Security**

Authentication protected.

Authorization enforced.

Public endpoints rate-limited.

Uploads protected.

Private resources protected.

Secrets excluded from source control.

Production errors do not expose internals.

**Operations**

Backups run.

Backup status visible.

Files included in backup strategy.

Restore process documented and tested.

Scheduled tasks documented.

Production deployment documented.

**130. FINAL ENGINEERING PRINCIPLE**

Build a system that is:

**simple for visitors, powerful for the owner, and boringly reliable underneath.**

The public website should feel lightweight.

The booking engine must be rigorous.

The admin dashboard should provide real operational control.

The analytics should measure meaningful business behavior.

The resource system should support a growing library.

The architecture should remain a maintainable modular monolith.

Do not build complexity merely because it is possible.

Do not remove necessary complexity merely because the business is small.

The final product should be capable of evolving from:

**content → traffic → leads → bookings → customers → repeat customers → additional products**

without requiring a complete architectural rewrite.

Above all:

**The visitor should never have to understand the complexity of the system behind the website.**