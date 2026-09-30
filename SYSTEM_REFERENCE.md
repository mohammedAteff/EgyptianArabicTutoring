# Egyptian Arabic with Abdallah — System Reference

**Baseline:** v1.0 · **Repository commit:** `59ab16ac35332c2eb754d1db0c0f40f60de2e44c` (`git rev-parse HEAD`, 2026-09-27). This is a code-and-local-runtime reference, not a certification of the live host. Tags: **[VERIFIED: CODE]** inspected repository source; **[VERIFIED: RUNTIME]** command executed in this inspection; **[UNVERIFIED: ENVIRONMENT]** depends on deployment or credentials; **[NOT BUILT]** no working path located in the inspected code.

## 1. System Overview

The application is a self-hosted tutoring site: visitors read learning content, request/download resources, play linked learning games, and book lessons; verified students use a separate portal for their lessons, package credits, and versioned forms; staff operate the calendar, content, billing records, analytics, and maintenance tools. [VERIFIED: CODE] `routes/web.php`, `app/Livewire/BookingWizard.php`, `app/Http/Controllers/Student/*`, `app/Http/Controllers/Admin/*`.

| Layer | Ground truth |
|---|---|
| Runtime | PHP requirement `^8.4.1`, Laravel `^13.17`, Livewire `^4.4`, PHPUnit `^12.5.12`; local Herd CLI PHP 8.4.25 (`composer.json`; `php84/php.exe -v`). [VERIFIED: CODE][VERIFIED: RUNTIME] |
| Database | Local configured `mysql` connection at `127.0.0.1:3307/bolt_landing`; MariaDB 10.11.18, InnoDB application tables (`.env.example`, `artisan config:show database.default`, `artisan db:show --json`, `artisan db:table bookings --json`). [VERIFIED: RUNTIME] |
| UI | Public/admin/student Blade layouts, one Livewire `BookingWizard`, Alpine and first-party JS, Tailwind 4/Vite 8 (`resources/views/layouts/*`, `resources/views/livewire/booking-wizard.blade.php`, `resources/js/app.js`, `package.json`). [VERIFIED: CODE] |
| Node/npm | `package.json` defines Vite build/dev scripts and dependency ranges but does not pin a top-level Node/npm version; this sandbox could not execute its Node binary, so installed Node/npm versions are [UNVERIFIED: ENVIRONMENT]. [VERIFIED: CODE: `package.json`; command: `node --version`] |
| Localization | Path-selected `en`, `fr`, `de`; English fallback, database translations/revisions for pages, FAQs, games, categories, resources; locale JSON/PHP UI strings in `lang/`; no Arabic route locale found. `default_language` accepts `en,ar` in settings despite route locale being en/fr/de (`app/Http/Middleware/SetRequestLocale.php`, `app/Domains/CMS/Services/TranslationService.php`, `app/Http/Controllers/Admin/SettingController.php`). [VERIFIED: CODE] |
| Hosting | Repository documents Herd locally and Hostinger/LiteSpeed subdirectory deployment; actual web-server/process-manager configuration and off-host service health are external (`README.md`, `routes/web.php` `/_arabictutor-landing`). [VERIFIED: CODE][UNVERIFIED: ENVIRONMENT] |

## 2. Roles, Authorization & Capabilities

`administrators.role` has `super_admin`, `admin`, `assistant`; `EnsureAdminRole` checks an explicit role list and `auth:web` uses the `administrators` provider. Student uses a distinct session guard/provider plus `student_id` and a 180-minute expiry check. There is no general public account guard (`app/Domains/Administration/Models/Administrator.php`, `app/Http/Middleware/EnsureAdminRole.php`, `app/Http/Middleware/EnsureStudentAuthenticated.php`, `app/Http/Controllers/Student/AuthController.php`, `config/auth.php`). [VERIFIED: CODE]

| Actor | Allowed route/action examples | Boundary |
|---|---|---|
| Visitor | Public content and `/booking`; Livewire `BookingWizard::confirmBooking`; tokenized confirmation/cancel; gated resource request/download; analytics and game events (`routes/web.php`, `app/Livewire/BookingWizard.php`, `app/Http/Controllers/BookingController.php`, `app/Http/Controllers/ResourceController.php`). Public reschedule page only supplies contact instructions and its POST aborts 403. [VERIFIED: CODE] | No admin/student routes; public mutations use rate limits, hold/session or confirmation tokens as applicable (`routes/web.php`, `app/Providers/AppServiceProvider.php`, `BookingService::createPublicBooking`). [VERIFIED: CODE] |
| Verified student | `/student`, form show/save, credit booking, eligible reschedule, logout (`routes/web.php`, `Student/*Controller`, `StudentBookingService`). [VERIFIED: CODE] | `student.auth` checks verified identity, guard ID and expiry; booking/form actions check ownership (`EnsureStudentAuthenticated`, `StudentBookingService`, `Student/FormController`). [VERIFIED: CODE] |
| Assistant | Read-only `/admin/students*`, `/admin/forms`, submissions and export (`routes/web.php`, `Admin/FormController`). **Exception/gap:** POST `admin.bookings.reschedule` has only `auth:web` and invokes the mutation service, so assistant can reach it with valid input. [VERIFIED: CODE] | Most write routes require `role:super_admin,admin`; assistant cannot enter general dashboard. The reschedule exception contradicts the intended read-only test name; its test supplies empty input and proves validation, not denial (`routes/web.php`, `Admin/BookingController::reschedule`, `tests/Feature/AdministratorAuthorizationTest.php`). [VERIFIED: CODE] |
| Admin | Operational bookings, availability, contacts, CMS/resources/games/blog/forms, billing, promotions, analytics/reports, everyday settings (`routes/web.php` role groups, `Admin/SettingController`). [VERIFIED: CODE] | Cannot manage administrators, backups/health/audit log, student merge/anonymization, or maintenance toggle (`routes/web.php`, `SettingController::update`). [VERIFIED: CODE] |
| Super admin | All admin routes including `/admin/administrators*`, `/admin/backups*`, `/admin/health*`, student merge/anonymize, maintenance control (`routes/web.php`). [VERIFIED: CODE] | Still subject to controller validation and model/ownership constraints (`Admin/StudentBillingController`, `Admin/AdministratorController`). [VERIFIED: CODE] |

## 3. Core User Journeys

| Journey | Actual path and stopping point |
|---|---|
| First booking | GET `/booking` → `BookingController::index` → Livewire `BookingWizard` gets `AvailabilityService` slots and `SlotResolver` signed slot identity → `BookingHoldService::acquireHold` → details → `BookingService::createPublicBooking` creates canonical contact, booking/event, converts hold → confirmation token and `IcsGenerator` (`routes/web.php`, named classes). Public intake does **not** consume package credits. [VERIFIED: CODE] |
| Student reschedule/cancel | Student portal `/student/bookings/{booking}/reschedule` → `Student/RescheduleController::update` → `RescheduleService::reschedule`. Public token cancellation → `BookingController::cancel` → `CancellationService`; public `showReschedule` only redirects with contact instructions and `processReschedule` aborts 403. Student reschedule requires an issued slot; cancellation restores consumed credit where one exists (`routes/web.php`, `app/Http/Controllers/BookingController.php`, named services). [VERIFIED: CODE] |
| Student forms | Identity/DOB verification in `Student/AuthController::login` → guarded dashboard → `Student/FormController::show/save` → `FormAssignmentService`/`FormSubmissionService` → `form_submissions`, answers/revisions. This is the built forms workflow; a separate homework-grading module is [NOT BUILT] in `app/Domains/Forms`, `routes/web.php`. [VERIFIED: CODE] |
| Package/payment | Admin `/admin/students/{student}/packages` and `/payments` → `StudentBillingController` → `StudentLedgerService::createPackage/recordPayment`; student credit booking → `StudentBookingService::create` → `consumeForBooking` within same transaction. Payments are manual records, not a payment-gateway charge (`routes/web.php`, named classes). [VERIFIED: CODE] |
| Refund | Admin `/admin/students/{student}/payments/{payment}/refunds` → `StudentBillingController::storeRefund` → `StudentLedgerService::refund`, recording `payment_refunds` and optional credit forfeiture. External money transfer is [NOT BUILT] in this path (`routes/web.php`, named methods). [VERIFIED: CODE] |
| Promotion | Admin create/update/toggle/preview in `PromotionController`; `PromotionService::getActivePromotion` selects active scheduled promotions for Blade banners (`routes/web.php`, `app/Domains/Marketing/Services/PromotionService.php`, `resources/views/components/promotional-banner.blade.php`). [VERIFIED: CODE] |
| Reminder | `routes/console.php` schedules `booking:send-telegram-reminders` every five minutes; command invokes `TelegramNotificationService`. Bot token/chat IDs and scheduler execution remain [UNVERIFIED: ENVIRONMENT] (`app/Console/Commands/SendBookingRemindersCommand.php`, `app/Domains/Notifications/Services/TelegramNotificationService.php`). [VERIFIED: CODE] |
| Maintenance | Super admin submits `/admin/settings` toggle → `SettingController::update` → `settings`; `EnsureNotUnderMaintenance` returns 503 for non-admin traffic and tracks a maintenance visit; same page disables it. DB/cache failure is fail-open (`routes/web.php`, `SettingController`, `EnsureNotUnderMaintenance`). [VERIFIED: CODE] |
| Analytics/export | `/admin/analytics*` and `/admin/reports*` → dashboard/report controllers → `AnalyticsService`/`ReportService` → `ExportService` CSV/XLSX (`routes/web.php`, named services). [VERIFIED: CODE] |

## 4. Business Rules & Invariants

| Bucket | Implemented invariant and evidence |
|---|---|
| Financial/credits | Five presets: diagnostic 1×60m $25/14d; foundation 8×120m $280/75d; fluency 12×120m $390/100d; pay-as-you-go 1×120m $48/30d; advanced 1×60m $28/30d. Foundation/fluency may receive $25 diagnostic discount; code also declares discounted-price display values $255/$365. `StudentLedgerService::PRESETS/checkDiagnosticCreditEligibility/createPackage`. [VERIFIED: CODE] |
| Financial/credits | Credit balance is sum of `session_ledger_entries.credit_change`, not a stored balance; eligible packages are row-locked and selected by expiration/creation order; payment/refund amounts use integer cents/bcmath; refunds cannot exceed recorded payment and can forfeit unused credits (`StudentLedgerService::selectAndLockEligiblePackageForBooking/consumeForBooking/refund`). [VERIFIED: CODE] |
| Booking | Canonical slot duration+buffer grid, recurring rules/special-hours exceptions, min notice and horizon; default hold 10m, notice 12h, buffer 15m, horizon 60d. Holds require matching visitor and session and expire by timestamp even before cleanup (`AvailabilityService`, `BookingHoldService`, `BookingService`). [VERIFIED: CODE] |
| Booking concurrency | Transactions acquire existing-or-inserted `booking_calendar_locks` rows for all buffer-expanded Cairo dates, sorted, then validate collisions with bookings/holds; `DatabaseCapability::transaction` uses READ COMMITTED on MariaDB; booking idempotency and confirmation tokens are unique (`AvailabilityService::acquireCalendarDateLocksForIntervals`, `BookingService`, `StudentBookingService`, `RescheduleService`, `artisan db:table bookings --json`). [VERIFIED: CODE][VERIFIED: RUNTIME] |
| Time | `start_at_utc/end_at_utc` plus original Cairo/customer zone snapshots; IANA zone validation, DST gap rejection/fold resolution; ICS emits UTC instants (`TimezoneService`, `IcsGenerator`, `artisan db:table bookings --json`). [VERIFIED: CODE][VERIFIED: RUNTIME] |
| Identity | Admin `web` and student `student` guards are separate; student login matches at least two normalized identifiers plus DOB and expires after 180 minutes; admin password reset broker uses administrators (`config/auth.php`, `Student/AuthController`, `EnsureStudentAuthenticated`). [VERIFIED: CODE] |
| System state | Maintenance is a cached `settings` toggle; admin/auth/health exceptions, 503 public response, fail-open on DB/cache error (`EnsureNotUnderMaintenance`). [VERIFIED: CODE] |
| Integrity/security | `AuditLog::creating` HMAC-redacts keyed identity/secrets and strings containing email/phone; `ExportService::sanitizeCell` prefixes spreadsheet-leading `= + - @ TAB CR`; `GeoIpService` requires trusted proxies for country headers and falls back when database unavailable (`AuditLog`, `ExportService`, `GeoIpService`). [VERIFIED: CODE] |
| Content/security | `TranslationService` stores English source revisions, localized drafts and stale/published states; `RichTextSanitizer` runs HTMLPurifier with a limited allow-list and same-origin storage-image check (`TranslationService`, `RichTextSanitizer`). [VERIFIED: CODE] |
| CSRF/uploads | All app routes use Laravel's `web` middleware; no explicit CSRF exemption found in `bootstrap/app.php`. Media accepts JPEG/PNG/WebP/GIF/PDF ≤10MB; resource files PDF/ZIP/DOC/DOCX/MP3/WAV/M4A ≤50MB and covers ≤10MB; promotions JPG/PNG/WebP ≤10MB (`artisan route:list --json --except-vendor`, `Admin/MediaController::store`, `Admin/ResourceController::store/update`, `Admin/PromotionController::store/update`). [VERIFIED: CODE][VERIFIED: RUNTIME] |

## 5. Database Schema & Entity Relationship Map

[VERIFIED: RUNTIME] Local `bolt_landing` MariaDB 10.11.18/InnoDB; metadata below comes from `php artisan db:table <table> --json` during this inspection. Framework infrastructure tables present: `users`, `migrations`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens` (`artisan db:show --json`). The local schema is not proof of production schema parity. [UNVERIFIED: ENVIRONMENT]

### `admin_notifications`

[VERIFIED: RUNTIME] `php artisan db:table admin_notifications --json`; model: `app/Domains/Administration/Models/AdminNotification.php`; traits: HasFactory.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `type` | `varchar(64)` | no | — | — |
| `level` | `varchar(32)` | no | `'info'` | — |
| `title` | `varchar(255)` | no | — | — |
| `message` | `text` | no | — | — |
| `link` | `varchar(500)` | yes | `NULL` | — |
| `data` | `longtext` | yes | `NULL` | — |
| `read_at` | `timestamp` | yes | `NULL` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `admin_notifications_read_at_created_at_index(read_at,created_at)`; `admin_notifications_read_at_index(read_at)`; `admin_notifications_type_index(type)`; `primary(id)`. Foreign keys: none.

### `administrators`

[VERIFIED: RUNTIME] `php artisan db:table administrators --json`; model: `app/Domains/Administration/Models/Administrator.php`; traits: HasFactory; relationships: blogs:HasMany.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `name` | `varchar(255)` | no | — | — |
| `email` | `varchar(255)` | no | — | unique |
| `password` | `varchar(255)` | no | — | — |
| `role` | `varchar(32)` | no | `'admin'` | — |
| `remember_token` | `varchar(100)` | yes | `NULL` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |
| `deleted_at` | `timestamp` | yes | `NULL` | — |

Indexes: `administrators_email_unique(email)` unique; `primary(id)`. Foreign keys: none.

### `analytics_events`

[VERIFIED: RUNTIME] `php artisan db:table analytics_events --json`; model: `app/Domains/Analytics/Models/AnalyticsEvent.php`; traits: HasFactory.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `event_uuid` | `char(36)` | yes | `NULL` | unique |
| `event_name` | `varchar(64)` | no | — | — |
| `visitor_token` | `varchar(64)` | yes | `NULL` | — |
| `visitor_id` | `varchar(64)` | yes | `NULL` | — |
| `session_token` | `varchar(64)` | yes | `NULL` | — |
| `page` | `varchar(255)` | yes | `NULL` | — |
| `referrer` | `text` | yes | `NULL` | — |
| `utm_source` | `varchar(255)` | yes | `NULL` | — |
| `utm_medium` | `varchar(255)` | yes | `NULL` | — |
| `utm_campaign` | `varchar(255)` | yes | `NULL` | — |
| `utm_content` | `varchar(255)` | yes | `NULL` | — |
| `utm_term` | `varchar(255)` | yes | `NULL` | — |
| `metadata` | `longtext` | yes | `NULL` | — |
| `ip_hash` | `varchar(64)` | yes | `NULL` | — |
| `is_bot` | `tinyint(1)` | no | `0` | — |
| `created_at` | `datetime` | no | — | — |

Indexes: `analytics_events_created_at_index(created_at)`; `analytics_events_event_name_index(event_name)`; `analytics_events_event_uuid_unique(event_uuid)` unique; `analytics_events_is_bot_index(is_bot)`; `analytics_events_session_token_index(session_token)`; `analytics_events_visitor_id_index(visitor_id)`; `analytics_events_visitor_token_index(visitor_token)`; `primary(id)`. Foreign keys: none.

### `analytics_reconciliation_audits`

[VERIFIED: RUNTIME] `php artisan db:table analytics_reconciliation_audits --json`; model: `app/Domains/Analytics/Models/AnalyticsReconciliationAudit.php`; traits: HasFactory.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `audit_type` | `varchar(50)` | no | `'rebuild'` | — |
| `cutover_at` | `timestamp` | yes | `NULL` | — |
| `rebuilt_visitors_count` | `int(11)` | no | `0` | — |
| `preserved_historical_count` | `int(11)` | no | `0` | — |
| `authoritative_bookings_count` | `int(11)` | no | `0` | — |
| `non_comparable_before` | `timestamp` | yes | `NULL` | — |
| `notes` | `text` | yes | `NULL` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `primary(id)`. Foreign keys: none.

### `audit_logs`

[VERIFIED: RUNTIME] `php artisan db:table audit_logs --json`; model: `app/Domains/Audit/Models/AuditLog.php`; traits: HasFactory; relationships: administrator:BelongsTo, actorUser:BelongsTo, actorStudent:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `administrator_id` | `bigint(20) unsigned` | yes | `NULL` | FK→administrators.id |
| `action` | `varchar(64)` | no | — | — |
| `entity_type` | `varchar(64)` | no | — | — |
| `entity_id` | `bigint(20) unsigned` | yes | `NULL` | — |
| `previous_data` | `longtext` | yes | `NULL` | — |
| `new_data` | `longtext` | yes | `NULL` | — |
| `ip_address` | `varchar(45)` | yes | `NULL` | — |
| `user_agent` | `text` | yes | `NULL` | — |
| `created_at` | `timestamp` | no | — | — |
| `event_uuid` | `char(36)` | no | — | unique |
| `actor_type` | `varchar(16)` | no | `'admin'` | — |
| `actor_user_id` | `bigint(20) unsigned` | yes | `NULL` | FK→administrators.id |
| `actor_student_id` | `bigint(20) unsigned` | yes | `NULL` | FK→students.id |
| `target_type` | `varchar(120)` | yes | `NULL` | — |
| `target_id` | `varchar(64)` | yes | `NULL` | — |
| `old_values` | `longtext` | yes | `NULL` | — |
| `new_values` | `longtext` | yes | `NULL` | — |

Indexes: `audit_event_uuid_unique(event_uuid)` unique; `audit_logs_action_index(action)`; `audit_logs_actor_student_id_foreign(actor_student_id)`; `audit_logs_actor_user_id_foreign(actor_user_id)`; `audit_logs_administrator_id_foreign(administrator_id)`; `audit_logs_created_at_index(created_at)`; `audit_logs_entity_type_index(entity_type)`; `primary(id)`. Foreign keys: `actor_student_id`→`students.id` ON DELETE SET NULL ON UPDATE RESTRICT; `actor_user_id`→`administrators.id` ON DELETE SET NULL ON UPDATE RESTRICT; `administrator_id`→`administrators.id` ON DELETE SET NULL ON UPDATE RESTRICT.

### `availability_exceptions`

[VERIFIED: RUNTIME] `php artisan db:table availability_exceptions --json`; model: `app/Domains/Availability/Models/AvailabilityException.php`; traits: HasFactory.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `date` | `date` | no | — | — |
| `type` | `varchar(32)` | no | — | — |
| `start_time` | `time` | yes | `NULL` | — |
| `end_time` | `time` | yes | `NULL` | — |
| `notes` | `varchar(255)` | yes | `NULL` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `availability_exceptions_date_index(date)`; `primary(id)`. Foreign keys: none.

### `availability_rules`

[VERIFIED: RUNTIME] `php artisan db:table availability_rules --json`; model: `app/Domains/Availability/Models/AvailabilityRule.php`; traits: HasFactory.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `weekday` | `tinyint(3) unsigned` | no | — | — |
| `start_time` | `time` | no | — | — |
| `end_time` | `time` | no | — | — |
| `session_duration_minutes` | `int(10) unsigned` | yes | `NULL` | — |
| `buffer_minutes` | `int(10) unsigned` | yes | `NULL` | — |
| `min_notice_hours` | `int(10) unsigned` | yes | `NULL` | — |
| `max_horizon_days` | `int(10) unsigned` | yes | `NULL` | — |
| `enabled` | `tinyint(1)` | no | `1` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `availability_rules_weekday_enabled_index(weekday,enabled)`; `primary(id)`. Foreign keys: none.

### `blog_revisions`

[VERIFIED: RUNTIME] `php artisan db:table blog_revisions --json`; model: `app/Domains/CMS/Models/BlogRevision.php`; relationships: blog:BelongsTo, revisedBy:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `blog_id` | `bigint(20) unsigned` | no | — | FK→blogs.id |
| `snapshot` | `longtext` | no | — | — |
| `revised_by` | `bigint(20) unsigned` | yes | `NULL` | FK→administrators.id |
| `created_at` | `timestamp` | no | — | — |

Indexes: `blog_revisions_blog_id_foreign(blog_id)`; `blog_revisions_revised_by_foreign(revised_by)`; `primary(id)`. Foreign keys: `blog_id`→`blogs.id` ON DELETE RESTRICT ON UPDATE RESTRICT; `revised_by`→`administrators.id` ON DELETE SET NULL ON UPDATE RESTRICT.

### `blog_slug_redirects`

[VERIFIED: RUNTIME] `php artisan db:table blog_slug_redirects --json`; model: `app/Domains/CMS/Models/BlogSlugRedirect.php`; relationships: blog:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `blog_id` | `bigint(20) unsigned` | no | — | FK→blogs.id |
| `old_slug` | `varchar(255)` | no | — | unique |
| `created_at` | `timestamp` | no | — | — |

Indexes: `blog_slug_redirects_blog_id_foreign(blog_id)`; `blog_slug_redirects_old_slug_unique(old_slug)` unique; `primary(id)`. Foreign keys: `blog_id`→`blogs.id` ON DELETE RESTRICT ON UPDATE RESTRICT.

### `blogs`

[VERIFIED: RUNTIME] `php artisan db:table blogs --json`; model: `app/Domains/CMS/Models/Blog.php`; traits: HasFactory; relationships: author:BelongsTo, revisions:HasMany, redirects:HasMany.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `title` | `varchar(255)` | no | — | — |
| `slug` | `varchar(255)` | no | — | unique |
| `excerpt` | `text` | no | — | — |
| `body` | `longtext` | no | — | — |
| `featured_image_path` | `varchar(255)` | yes | `NULL` | — |
| `seo_title` | `varchar(255)` | yes | `NULL` | — |
| `seo_description` | `varchar(255)` | yes | `NULL` | — |
| `canonical_url` | `varchar(255)` | yes | `NULL` | — |
| `status` | `enum('draft','published','archived')` | no | `'draft'` | — |
| `published_at` | `datetime` | yes | `NULL` | — |
| `author_id` | `bigint(20) unsigned` | no | — | FK→administrators.id |
| `locale` | `varchar(8)` | no | `'en'` | unique |
| `translation_group_id` | `char(36)` | yes | `NULL` | unique |
| `lock_version` | `int(10) unsigned` | no | `1` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `blogs_author_id_foreign(author_id)`; `blogs_locale_index(locale)`; `blogs_slug_unique(slug)` unique; `blogs_status_index(status)`; `blogs_translation_group_id_index(translation_group_id)`; `blogs_translation_group_id_locale_unique(translation_group_id,locale)` unique; `primary(id)`. Foreign keys: `author_id`→`administrators.id` ON DELETE RESTRICT ON UPDATE RESTRICT.

### `booking_calendar_locks`

[VERIFIED: RUNTIME] `php artisan db:table booking_calendar_locks --json`; model: none (query-builder table).

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `lock_date` | `date` | no | — | unique |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `booking_calendar_locks_lock_date_unique(lock_date)` unique; `primary(id)`. Foreign keys: none.

### `booking_events`

[VERIFIED: RUNTIME] `php artisan db:table booking_events --json`; model: `app/Domains/Booking/Models/BookingEvent.php`; traits: HasFactory; relationships: booking:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `booking_id` | `bigint(20) unsigned` | no | — | FK→bookings.id |
| `event_type` | `varchar(32)` | no | — | — |
| `idempotency_key` | `varchar(120)` | yes | `NULL` | unique |
| `performed_by` | `varchar(32)` | no | — | — |
| `performed_by_id` | `bigint(20) unsigned` | yes | `NULL` | — |
| `previous_data` | `longtext` | yes | `NULL` | — |
| `new_data` | `longtext` | yes | `NULL` | — |
| `created_at` | `timestamp` | no | — | — |

Indexes: `booking_events_booking_id_foreign(booking_id)`; `booking_events_created_at_index(created_at)`; `booking_events_event_type_index(event_type)`; `booking_events_idempotency_key_unique(idempotency_key)` unique; `primary(id)`. Foreign keys: `booking_id`→`bookings.id` ON DELETE CASCADE ON UPDATE RESTRICT.

### `booking_holds`

[VERIFIED: RUNTIME] `php artisan db:table booking_holds --json`; model: `app/Domains/Booking/Models/BookingHold.php`; traits: HasFactory; relationships: sessionType:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `visitor_token` | `varchar(64)` | no | — | — |
| `session_token` | `varchar(64)` | yes | `NULL` | — |
| `hold_token` | `varchar(64)` | yes | `NULL` | — |
| `session_type_id` | `bigint(20) unsigned` | no | — | FK→session_types.id |
| `slot_start_utc` | `datetime` | no | — | — |
| `slot_end_utc` | `datetime` | no | — | — |
| `expires_at` | `datetime` | no | — | — |
| `released_at` | `datetime` | yes | `NULL` | — |
| `status` | `varchar(32)` | no | `'active'` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `booking_holds_expires_at_index(expires_at)`; `booking_holds_hold_token_index(hold_token)`; `booking_holds_session_token_index(session_token)`; `booking_holds_session_type_id_foreign(session_type_id)`; `booking_holds_slot_end_utc_index(slot_end_utc)`; `booking_holds_slot_lookup_index(slot_start_utc,slot_end_utc,status,expires_at)`; `booking_holds_slot_start_utc_index(slot_start_utc)`; `booking_holds_status_index(status)`; `booking_holds_visitor_token_index(visitor_token)`; `primary(id)`. Foreign keys: `session_type_id`→`session_types.id` ON DELETE RESTRICT ON UPDATE RESTRICT.

### `bookings`

[VERIFIED: RUNTIME] `php artisan db:table bookings --json`; model: `app/Domains/Booking/Models/Booking.php`; traits: HasFactory; relationships: contact:BelongsTo, student:BelongsTo, sessionType:BelongsTo, events:HasMany.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `contact_id` | `bigint(20) unsigned` | no | — | FK→contacts.id |
| `student_id` | `bigint(20) unsigned` | yes | `NULL` | FK→students.id |
| `visitor_token` | `varchar(64)` | yes | `NULL` | — |
| `session_type_id` | `bigint(20) unsigned` | no | — | FK→session_types.id |
| `start_at_utc` | `datetime` | no | — | — |
| `end_at_utc` | `datetime` | no | — | — |
| `business_timezone` | `varchar(64)` | no | — | — |
| `customer_timezone` | `varchar(64)` | no | — | — |
| `business_local_date_at_booking` | `date` | no | — | — |
| `business_local_start_time_at_booking` | `time` | no | — | — |
| `business_local_end_time_at_booking` | `time` | no | — | — |
| `customer_local_date_at_booking` | `date` | no | — | — |
| `customer_local_start_time_at_booking` | `time` | no | — | — |
| `customer_local_end_time_at_booking` | `time` | no | — | — |
| `business_utc_offset_at_booking` | `varchar(10)` | no | — | — |
| `customer_utc_offset_at_booking` | `varchar(10)` | no | — | — |
| `status` | `varchar(32)` | no | `'confirmed'` | — |
| `idempotency_key` | `varchar(100)` | no | — | unique |
| `confirmation_token` | `varchar(64)` | no | — | unique |
| `notes` | `text` | yes | `NULL` | — |
| `source` | `varchar(255)` | yes | `NULL` | — |
| `medium` | `varchar(255)` | yes | `NULL` | — |
| `campaign` | `varchar(255)` | yes | `NULL` | — |
| `content` | `varchar(255)` | yes | `NULL` | — |
| `term` | `varchar(255)` | yes | `NULL` | — |
| `referrer` | `varchar(500)` | yes | `NULL` | — |
| `touch_at` | `datetime` | yes | `NULL` | — |
| `cancelled_at` | `datetime` | yes | `NULL` | — |
| `cancellation_reason` | `text` | yes | `NULL` | — |
| `completed_at` | `datetime` | yes | `NULL` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |
| `deleted_at` | `timestamp` | yes | `NULL` | — |
| `detected_country_code` | `char(2)` | yes | `NULL` | — |
| `admin_reconfirmation_needed` | `tinyint(1)` | no | `0` | — |

Indexes: `bookings_availability_check_index(status,cancelled_at,start_at_utc,end_at_utc)`; `bookings_confirmation_token_unique(confirmation_token)` unique; `bookings_contact_id_foreign(contact_id)`; `bookings_detected_country_code_index(detected_country_code)`; `bookings_end_at_utc_index(end_at_utc)`; `bookings_idempotency_key_unique(idempotency_key)` unique; `bookings_session_type_id_foreign(session_type_id)`; `bookings_start_at_utc_index(start_at_utc)`; `bookings_status_index(status)`; `bookings_student_id_index(student_id)`; `bookings_visitor_token_index(visitor_token)`; `primary(id)`. Foreign keys: `contact_id`→`contacts.id` ON DELETE RESTRICT ON UPDATE RESTRICT; `session_type_id`→`session_types.id` ON DELETE RESTRICT ON UPDATE RESTRICT; `student_id`→`students.id` ON DELETE SET NULL ON UPDATE RESTRICT.

### `category_translations`

[VERIFIED: RUNTIME] `php artisan db:table category_translations --json`; model: `app/Domains/Resources/Models/CategoryTranslation.php`; traits: HasFactory; relationships: category:BelongsTo, sourceRevision:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `category_id` | `bigint(20) unsigned` | no | — | unique; FK→resource_categories.id |
| `locale` | `varchar(5)` | no | — | — |
| `source_revision_id` | `bigint(20) unsigned` | yes | `NULL` | — |
| `status` | `varchar(20)` | no | `'draft'` | — |
| `name` | `varchar(255)` | no | — | — |
| `description` | `text` | yes | `NULL` | — |
| `published_at` | `datetime` | yes | `NULL` | — |
| `live_locale` | `varchar(5)` | yes | `NULL` | unique |
| `draft_locale` | `varchar(5)` | yes | `NULL` | unique |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `category_translations_status_index(status)`; `primary(id)`; `uq_cat_trans_draft(category_id,draft_locale)` unique; `uq_cat_trans_live(category_id,live_locale)` unique. Foreign keys: `category_id`→`resource_categories.id` ON DELETE CASCADE ON UPDATE RESTRICT.

### `contacts`

[VERIFIED: RUNTIME] `php artisan db:table contacts --json`; model: `app/Domains/Contacts/Models/Contact.php`; traits: HasFactory; relationships: bookings:HasMany, resourceRequests:HasMany, resourceDownloads:HasMany, mergedInto:BelongsTo, mergedContacts:HasMany.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `name` | `varchar(255)` | yes | `NULL` | — |
| `email` | `varchar(255)` | no | — | unique |
| `display_email` | `varchar(255)` | no | — | — |
| `phone` | `varchar(255)` | yes | `NULL` | — |
| `notes` | `text` | yes | `NULL` | — |
| `first_seen_at` | `timestamp` | yes | `NULL` | — |
| `last_seen_at` | `timestamp` | yes | `NULL` | — |
| `utm_source` | `varchar(255)` | yes | `NULL` | — |
| `utm_medium` | `varchar(255)` | yes | `NULL` | — |
| `utm_campaign` | `varchar(255)` | yes | `NULL` | — |
| `utm_content` | `varchar(255)` | yes | `NULL` | — |
| `utm_term` | `varchar(255)` | yes | `NULL` | — |
| `merged_into_contact_id` | `bigint(20) unsigned` | yes | `NULL` | FK→contacts.id |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |
| `deleted_at` | `timestamp` | yes | `NULL` | — |

Indexes: `contacts_email_unique(email)` unique; `contacts_merged_into_contact_id_foreign(merged_into_contact_id)`; `primary(id)`. Foreign keys: `merged_into_contact_id`→`contacts.id` ON DELETE SET NULL ON UPDATE RESTRICT.

### `content_revisions`

[VERIFIED: RUNTIME] `php artisan db:table content_revisions --json`; model: `app/Domains/CMS/Models/ContentRevision.php`; traits: HasFactory; relationships: revisable:MorphTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `revisable_type` | `varchar(255)` | no | — | — |
| `revisable_id` | `bigint(20) unsigned` | no | — | — |
| `revision_number` | `int(10) unsigned` | no | `1` | — |
| `title` | `varchar(255)` | yes | `NULL` | — |
| `content` | `longtext` | no | — | — |
| `created_by_id` | `bigint(20) unsigned` | yes | `NULL` | — |
| `status` | `varchar(32)` | no | `'draft'` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `content_revisions_revisable_type_revisable_id_index(revisable_type,revisable_id)`; `primary(id)`. Foreign keys: none.

### `daily_country_metrics`

[VERIFIED: RUNTIME] `php artisan db:table daily_country_metrics --json`; model: `app/Domains/Analytics/Models/DailyCountryMetric.php`; traits: HasFactory.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `metric_date` | `date` | no | — | unique |
| `country_code` | `char(2)` | no | — | unique |
| `unique_visitors` | `int(10) unsigned` | no | `0` | — |
| `booking_cta_clicks` | `int(10) unsigned` | no | `0` | — |
| `bookings_completed` | `int(10) unsigned` | no | `0` | — |
| `resource_requests` | `int(10) unsigned` | no | `0` | — |
| `visitors` | `int(10) unsigned` | no | `0` | — |
| `sessions` | `int(10) unsigned` | no | `0` | — |
| `page_views` | `int(10) unsigned` | no | `0` | — |
| `bounced_sessions_count` | `int(10) unsigned` | no | `0` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `idx_country_metric_date(country_code,metric_date)`; `idx_country_metric_date_only(metric_date)`; `primary(id)`; `uq_daily_country_metrics(metric_date,country_code)` unique. Foreign keys: none.

### `daily_metrics`

[VERIFIED: RUNTIME] `php artisan db:table daily_metrics --json`; model: `app/Domains/Analytics/Models/DailyMetric.php`; traits: HasFactory.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `metric_date` | `date` | no | — | unique |
| `metric_name` | `varchar(64)` | no | — | unique |
| `dimension_key` | `varchar(64)` | no | `''` | unique |
| `dimension_value` | `varchar(128)` | no | `''` | unique |
| `count` | `bigint(20) unsigned` | no | `0` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `daily_metrics_metric_date_index(metric_date)`; `daily_metrics_unique(metric_date,metric_name,dimension_key,dimension_value)` unique; `primary(id)`. Foreign keys: none.

### `entity_translation_revisions`

[VERIFIED: RUNTIME] `php artisan db:table entity_translation_revisions --json`; model: `app/Domains/CMS/Models/EntityTranslationRevision.php`; traits: HasFactory; relationships: author:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `entity_type` | `varchar(50)` | no | — | unique |
| `entity_id` | `bigint(20) unsigned` | no | — | unique |
| `revision_number` | `int(10) unsigned` | no | — | unique |
| `locale` | `varchar(5)` | no | `'en'` | — |
| `title` | `varchar(255)` | yes | `NULL` | — |
| `description` | `text` | yes | `NULL` | — |
| `content` | `longtext` | yes | `NULL` | — |
| `created_by` | `bigint(20) unsigned` | yes | `NULL` | FK→administrators.id |
| `created_at` | `datetime` | no | — | — |

Indexes: `entity_translation_revisions_created_at_index(created_at)`; `entity_translation_revisions_created_by_foreign(created_by)`; `entity_translation_revisions_entity_id_index(entity_id)`; `entity_translation_revisions_entity_type_index(entity_type)`; `primary(id)`; `uq_entity_revision(entity_type,entity_id,revision_number)` unique. Foreign keys: `created_by`→`administrators.id` ON DELETE SET NULL ON UPDATE RESTRICT.

### `faq_translations`

[VERIFIED: RUNTIME] `php artisan db:table faq_translations --json`; model: `app/Domains/CMS/Models/FaqTranslation.php`; traits: HasFactory; relationships: faq:BelongsTo, sourceRevision:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `faq_id` | `bigint(20) unsigned` | no | — | unique; FK→faqs.id |
| `locale` | `varchar(5)` | no | — | — |
| `source_revision_id` | `bigint(20) unsigned` | yes | `NULL` | — |
| `status` | `varchar(20)` | no | `'draft'` | — |
| `question` | `text` | no | — | — |
| `answer` | `text` | no | — | — |
| `published_at` | `datetime` | yes | `NULL` | — |
| `live_locale` | `varchar(5)` | yes | `NULL` | unique |
| `draft_locale` | `varchar(5)` | yes | `NULL` | unique |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `faq_translations_status_index(status)`; `primary(id)`; `uq_faq_trans_draft(faq_id,draft_locale)` unique; `uq_faq_trans_live(faq_id,live_locale)` unique. Foreign keys: `faq_id`→`faqs.id` ON DELETE CASCADE ON UPDATE RESTRICT.

### `faqs`

[VERIFIED: RUNTIME] `php artisan db:table faqs --json`; model: `app/Domains/CMS/Models/Faq.php`; traits: HasFactory; relationships: revisions:MorphMany.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `question` | `text` | no | — | — |
| `answer` | `text` | no | — | — |
| `sort_order` | `int(11)` | no | `0` | — |
| `active` | `tinyint(1)` | no | `1` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `primary(id)`. Foreign keys: none.

### `form_answers`

[VERIFIED: RUNTIME] `php artisan db:table form_answers --json`; model: `app/Domains/Forms/Models/FormAnswer.php`; relationships: submission:BelongsTo, question:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `form_submission_id` | `bigint(20) unsigned` | no | — | unique; FK→form_submissions.id |
| `form_question_id` | `bigint(20) unsigned` | no | — | unique; FK→form_questions.id |
| `value_text` | `longtext` | yes | `NULL` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `form_answers_form_question_id_foreign(form_question_id)`; `form_answers_form_submission_id_form_question_id_unique(form_submission_id,form_question_id)` unique; `primary(id)`. Foreign keys: `form_question_id`→`form_questions.id` ON DELETE RESTRICT ON UPDATE RESTRICT; `form_submission_id`→`form_submissions.id` ON DELETE CASCADE ON UPDATE RESTRICT.

### `form_question_options`

[VERIFIED: RUNTIME] `php artisan db:table form_question_options --json`; model: `app/Domains/Forms/Models/FormQuestionOption.php`; relationships: question:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `form_question_id` | `bigint(20) unsigned` | no | — | unique; FK→form_questions.id |
| `label` | `varchar(255)` | no | — | — |
| `value` | `varchar(255)` | no | — | unique |
| `sort_order` | `int(10) unsigned` | no | `0` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `form_question_options_form_question_id_value_unique(form_question_id,value)` unique; `primary(id)`. Foreign keys: `form_question_id`→`form_questions.id` ON DELETE CASCADE ON UPDATE RESTRICT.

### `form_questions`

[VERIFIED: RUNTIME] `php artisan db:table form_questions --json`; model: `app/Domains/Forms/Models/FormQuestion.php`; relationships: version:BelongsTo, options:HasMany.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `form_version_id` | `bigint(20) unsigned` | no | — | unique; FK→form_versions.id |
| `question_key` | `varchar(255)` | no | — | unique |
| `label` | `varchar(255)` | no | — | — |
| `description` | `text` | yes | `NULL` | — |
| `question_type` | `varchar(32)` | no | — | — |
| `is_required` | `tinyint(1)` | no | `0` | — |
| `assistant_visible` | `tinyint(1)` | no | `1` | — |
| `sort_order` | `int(10) unsigned` | no | `0` | — |
| `validation_rules` | `longtext` | yes | `NULL` | — |
| `presentation_config` | `longtext` | yes | `NULL` | — |
| `conditional_logic` | `longtext` | yes | `NULL` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `form_questions_form_version_id_question_key_unique(form_version_id,question_key)` unique; `form_questions_form_version_id_sort_order_index(form_version_id,sort_order)`; `primary(id)`. Foreign keys: `form_version_id`→`form_versions.id` ON DELETE CASCADE ON UPDATE RESTRICT.

### `form_submission_revisions`

[VERIFIED: RUNTIME] `php artisan db:table form_submission_revisions --json`; model: `app/Domains/Forms/Models/FormSubmissionRevision.php`; relationships: submission:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `form_submission_id` | `bigint(20) unsigned` | no | — | unique; FK→form_submissions.id |
| `revision_number` | `int(10) unsigned` | no | — | unique |
| `snapshot_answers` | `longtext` | no | — | — |
| `submitted_at` | `datetime` | yes | `NULL` | — |
| `created_at` | `timestamp` | no | — | — |

Indexes: `form_sub_rev_version_unique(form_submission_id,revision_number)` unique; `primary(id)`. Foreign keys: `form_submission_id`→`form_submissions.id` ON DELETE CASCADE ON UPDATE RESTRICT.

### `form_submissions`

[VERIFIED: RUNTIME] `php artisan db:table form_submissions --json`; model: `app/Domains/Forms/Models/FormSubmission.php`; relationships: version:BelongsTo, student:BelongsTo, answers:HasMany, revisions:HasMany.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `form_version_id` | `bigint(20) unsigned` | no | — | FK→form_versions.id |
| `student_id` | `bigint(20) unsigned` | no | — | FK→students.id |
| `status` | `enum('draft','submitted')` | no | `'draft'` | — |
| `submitted_at` | `datetime` | yes | `NULL` | — |
| `submission_revision` | `int(10) unsigned` | no | `1` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `form_submissions_status_index(status)`; `form_submissions_student_id_status_index(student_id,status)`; `form_submissions_version_student_index(form_version_id,student_id)`; `primary(id)`. Foreign keys: `form_version_id`→`form_versions.id` ON DELETE RESTRICT ON UPDATE RESTRICT; `student_id`→`students.id` ON DELETE CASCADE ON UPDATE RESTRICT.

### `form_versions`

[VERIFIED: RUNTIME] `php artisan db:table form_versions --json`; model: `app/Domains/Forms/Models/FormVersion.php`; relationships: form:BelongsTo, questions:HasMany, submissions:HasMany.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `form_id` | `bigint(20) unsigned` | no | — | unique; FK→forms.id |
| `version_number` | `int(10) unsigned` | no | — | unique |
| `changelog` | `text` | yes | `NULL` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `form_versions_form_id_version_number_unique(form_id,version_number)` unique; `primary(id)`. Foreign keys: `form_id`→`forms.id` ON DELETE CASCADE ON UPDATE RESTRICT.

### `forms`

[VERIFIED: RUNTIME] `php artisan db:table forms --json`; model: `app/Domains/Forms/Models/Form.php`; traits: HasFactory; relationships: author:BelongsTo, activeVersion:BelongsTo, publishedVersion:BelongsTo, versions:HasMany.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `title` | `varchar(255)` | no | — | — |
| `slug` | `varchar(255)` | no | — | unique |
| `description` | `text` | yes | `NULL` | — |
| `status` | `enum('draft','published','archived')` | no | `'draft'` | — |
| `prompt_trigger` | `enum('none','after_booking','after_reschedule','next_session_check')` | no | `'none'` | — |
| `is_mandatory` | `tinyint(1)` | no | `0` | — |
| `can_edit_after_submission` | `tinyint(1)` | no | `0` | — |
| `lock_version` | `int(10) unsigned` | no | `1` | — |
| `created_by` | `bigint(20) unsigned` | no | — | FK→administrators.id |
| `active_version_id` | `bigint(20) unsigned` | yes | `NULL` | FK→form_versions.id |
| `published_version_id` | `bigint(20) unsigned` | yes | `NULL` | FK→form_versions.id |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `forms_active_version_id_foreign(active_version_id)`; `forms_created_by_foreign(created_by)`; `forms_published_version_id_foreign(published_version_id)`; `forms_slug_unique(slug)` unique; `forms_status_index(status)`; `primary(id)`. Foreign keys: `active_version_id`→`form_versions.id` ON DELETE SET NULL ON UPDATE RESTRICT; `created_by`→`administrators.id` ON DELETE RESTRICT ON UPDATE RESTRICT; `published_version_id`→`form_versions.id` ON DELETE SET NULL ON UPDATE RESTRICT.

### `game_translations`

[VERIFIED: RUNTIME] `php artisan db:table game_translations --json`; model: `app/Domains/Games/Models/GameTranslation.php`; traits: HasFactory; relationships: game:BelongsTo, sourceRevision:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `game_id` | `bigint(20) unsigned` | no | — | unique; FK→games.id |
| `locale` | `varchar(5)` | no | — | — |
| `source_revision_id` | `bigint(20) unsigned` | yes | `NULL` | — |
| `status` | `varchar(20)` | no | `'draft'` | — |
| `title` | `varchar(255)` | no | — | — |
| `description` | `text` | yes | `NULL` | — |
| `published_at` | `datetime` | yes | `NULL` | — |
| `live_locale` | `varchar(5)` | yes | `NULL` | unique |
| `draft_locale` | `varchar(5)` | yes | `NULL` | unique |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `game_translations_status_index(status)`; `primary(id)`; `uq_game_trans_draft(game_id,draft_locale)` unique; `uq_game_trans_live(game_id,live_locale)` unique. Foreign keys: `game_id`→`games.id` ON DELETE CASCADE ON UPDATE RESTRICT.

### `games`

[VERIFIED: RUNTIME] `php artisan db:table games --json`; model: `app/Domains/Games/Models/Game.php`; traits: HasFactory; relationships: revisions:MorphMany.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `title` | `varchar(255)` | no | — | — |
| `slug` | `varchar(255)` | no | — | unique |
| `description` | `text` | yes | `NULL` | — |
| `badge` | `varchar(64)` | yes | `NULL` | — |
| `thumbnail_path` | `varchar(255)` | yes | `NULL` | — |
| `target_url` | `varchar(255)` | yes | `NULL` | — |
| `status` | `varchar(32)` | no | `'coming_soon'` | — |
| `featured` | `tinyint(1)` | no | `0` | — |
| `sort_order` | `int(11)` | no | `0` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `games_slug_unique(slug)` unique; `games_status_index(status)`; `primary(id)`. Foreign keys: none.

### `maintenance_visits`

[VERIFIED: RUNTIME] `php artisan db:table maintenance_visits --json`; model: none (query-builder table).

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `visitor_id` | `varchar(100)` | yes | `NULL` | — |
| `ip_address` | `varchar(45)` | yes | `NULL` | — |
| `user_agent` | `text` | yes | `NULL` | — |
| `country_code` | `char(2)` | no | `'XX'` | — |
| `url` | `varchar(500)` | no | — | — |
| `referrer` | `varchar(500)` | yes | `NULL` | — |
| `is_bounced` | `tinyint(1)` | no | `1` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `maintenance_visits_country_code_index(country_code)`; `maintenance_visits_created_at_index(created_at)`; `maintenance_visits_visitor_id_index(visitor_id)`; `primary(id)`. Foreign keys: none.

### `marketing_touches`

[VERIFIED: RUNTIME] `php artisan db:table marketing_touches --json`; model: `app/Domains/Analytics/Models/MarketingTouch.php`; traits: HasFactory; relationships: visitor:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `visitor_id` | `bigint(20) unsigned` | no | — | FK→visitors.id |
| `visitor_token` | `varchar(64)` | no | — | — |
| `session_token` | `varchar(64)` | yes | `NULL` | — |
| `utm_source` | `varchar(100)` | yes | `NULL` | — |
| `utm_medium` | `varchar(100)` | yes | `NULL` | — |
| `utm_campaign` | `varchar(100)` | yes | `NULL` | — |
| `utm_content` | `varchar(100)` | yes | `NULL` | — |
| `utm_term` | `varchar(100)` | yes | `NULL` | — |
| `referrer` | `varchar(500)` | yes | `NULL` | — |
| `is_direct` | `tinyint(1)` | no | `0` | — |
| `dedupe_hash` | `varchar(64)` | yes | `NULL` | unique |
| `touch_at` | `datetime` | no | — | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `marketing_touches_dedupe_hash_unique(dedupe_hash)` unique; `marketing_touches_session_token_index(session_token)`; `marketing_touches_touch_at_index(touch_at)`; `marketing_touches_visitor_id_foreign(visitor_id)`; `marketing_touches_visitor_token_index(visitor_token)`; `marketing_touches_visitor_token_touch_at_index(visitor_token,touch_at)`; `primary(id)`. Foreign keys: `visitor_id`→`visitors.id` ON DELETE CASCADE ON UPDATE RESTRICT.

### `media`

[VERIFIED: RUNTIME] `php artisan db:table media --json`; model: `app/Domains/CMS/Models/Media.php`; traits: HasFactory.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `filename` | `varchar(255)` | no | — | — |
| `disk` | `varchar(32)` | no | `'public'` | — |
| `path` | `varchar(255)` | no | — | — |
| `mime_type` | `varchar(100)` | no | — | — |
| `file_size` | `bigint(20) unsigned` | no | `0` | — |
| `dimensions` | `longtext` | yes | `NULL` | — |
| `alt_text` | `varchar(255)` | yes | `NULL` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `primary(id)`. Foreign keys: none.

### `migration_booking_exceptions`

[VERIFIED: RUNTIME] `php artisan db:table migration_booking_exceptions --json`; model: none (query-builder table).

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `booking_id` | `bigint(20) unsigned` | no | — | unique; FK→bookings.id |
| `reason` | `varchar(64)` | no | — | — |
| `context` | `longtext` | yes | `NULL` | — |
| `resolved_at` | `timestamp` | yes | `NULL` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `migration_booking_exceptions_booking_id_unique(booking_id)` unique; `primary(id)`. Foreign keys: `booking_id`→`bookings.id` ON DELETE CASCADE ON UPDATE RESTRICT.

### `migration_booking_timezone_exceptions`

[VERIFIED: RUNTIME] `php artisan db:table migration_booking_timezone_exceptions --json`; model: none (query-builder table).

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `booking_id` | `bigint(20) unsigned` | no | — | unique; FK→bookings.id |
| `reason` | `varchar(64)` | no | — | — |
| `resolved_at` | `timestamp` | yes | `NULL` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `migration_booking_timezone_exceptions_booking_id_unique(booking_id)` unique; `primary(id)`. Foreign keys: `booking_id`→`bookings.id` ON DELETE CASCADE ON UPDATE RESTRICT.

### `page_translations`

[VERIFIED: RUNTIME] `php artisan db:table page_translations --json`; model: `app/Domains/CMS/Models/PageTranslation.php`; traits: HasFactory; relationships: page:BelongsTo, sourceRevision:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `page_id` | `bigint(20) unsigned` | no | — | unique; FK→pages.id |
| `locale` | `varchar(5)` | no | — | — |
| `source_revision_id` | `bigint(20) unsigned` | yes | `NULL` | — |
| `status` | `varchar(20)` | no | `'draft'` | — |
| `title` | `varchar(255)` | no | — | — |
| `content` | `longtext` | yes | `NULL` | — |
| `excerpt` | `text` | yes | `NULL` | — |
| `seo_title` | `varchar(255)` | yes | `NULL` | — |
| `seo_description` | `text` | yes | `NULL` | — |
| `published_at` | `datetime` | yes | `NULL` | — |
| `live_locale` | `varchar(5)` | yes | `NULL` | unique |
| `draft_locale` | `varchar(5)` | yes | `NULL` | unique |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `page_translations_status_index(status)`; `primary(id)`; `uq_page_trans_draft(page_id,draft_locale)` unique; `uq_page_trans_live(page_id,live_locale)` unique. Foreign keys: `page_id`→`pages.id` ON DELETE CASCADE ON UPDATE RESTRICT.

### `pages`

[VERIFIED: RUNTIME] `php artisan db:table pages --json`; model: `app/Domains/CMS/Models/Page.php`; traits: HasFactory; relationships: revisions:MorphMany.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `title` | `varchar(255)` | no | — | — |
| `slug` | `varchar(255)` | no | — | unique |
| `content` | `longtext` | yes | `NULL` | — |
| `excerpt` | `text` | yes | `NULL` | — |
| `status` | `varchar(32)` | no | `'draft'` | — |
| `seo_title` | `varchar(255)` | yes | `NULL` | — |
| `seo_description` | `text` | yes | `NULL` | — |
| `og_image_path` | `varchar(255)` | yes | `NULL` | — |
| `published_at` | `datetime` | yes | `NULL` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `pages_slug_unique(slug)` unique; `pages_status_index(status)`; `primary(id)`. Foreign keys: none.

### `payment_records`

[VERIFIED: RUNTIME] `php artisan db:table payment_records --json`; model: `app/Domains/Students/Models/PaymentRecord.php`; traits: HasFactory; relationships: package:BelongsTo, student:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `student_package_id` | `bigint(20) unsigned` | no | — | FK→student_packages.id |
| `student_id` | `bigint(20) unsigned` | no | — | FK→students.id |
| `idempotency_key` | `varchar(80)` | no | — | unique |
| `amount_paid` | `decimal(10,2)` | no | — | — |
| `currency` | `varchar(3)` | no | — | — |
| `payment_method` | `varchar(255)` | no | `'PayPal - Manual'` | — |
| `transaction_reference` | `varchar(255)` | yes | `NULL` | — |
| `paid_at` | `datetime` | no | — | — |
| `recorded_by` | `bigint(20) unsigned` | yes | `NULL` | FK→administrators.id |
| `notes` | `text` | yes | `NULL` | — |
| `created_at` | `timestamp` | no | — | — |

Indexes: `payment_records_idempotency_key_unique(idempotency_key)` unique; `payment_records_recorded_by_foreign(recorded_by)`; `payment_records_student_id_foreign(student_id)`; `payment_records_student_package_id_student_id_index(student_package_id,student_id)`; `primary(id)`. Foreign keys: `recorded_by`→`administrators.id` ON DELETE SET NULL ON UPDATE RESTRICT; `student_id`→`students.id` ON DELETE RESTRICT ON UPDATE RESTRICT; `student_package_id`→`student_packages.id` ON DELETE RESTRICT ON UPDATE RESTRICT.

### `payment_refunds`

[VERIFIED: RUNTIME] `php artisan db:table payment_refunds --json`; model: `app/Domains/Students/Models/PaymentRefund.php`; traits: HasFactory; relationships: payment:BelongsTo, package:BelongsTo, student:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `payment_record_id` | `bigint(20) unsigned` | no | — | FK→payment_records.id |
| `student_package_id` | `bigint(20) unsigned` | no | — | FK→student_packages.id |
| `student_id` | `bigint(20) unsigned` | no | — | FK→students.id |
| `idempotency_key` | `varchar(80)` | no | — | unique |
| `amount_refunded` | `decimal(10,2)` | no | — | — |
| `currency` | `varchar(3)` | no | — | — |
| `reason` | `text` | yes | `NULL` | — |
| `refunded_at` | `datetime` | no | — | — |
| `recorded_by` | `bigint(20) unsigned` | yes | `NULL` | FK→administrators.id |
| `created_at` | `timestamp` | no | — | — |

Indexes: `payment_refunds_idempotency_key_unique(idempotency_key)` unique; `payment_refunds_payment_record_id_foreign(payment_record_id)`; `payment_refunds_recorded_by_foreign(recorded_by)`; `payment_refunds_student_id_foreign(student_id)`; `payment_refunds_student_package_id_student_id_index(student_package_id,student_id)`; `primary(id)`. Foreign keys: `payment_record_id`→`payment_records.id` ON DELETE RESTRICT ON UPDATE RESTRICT; `recorded_by`→`administrators.id` ON DELETE SET NULL ON UPDATE RESTRICT; `student_id`→`students.id` ON DELETE RESTRICT ON UPDATE RESTRICT; `student_package_id`→`student_packages.id` ON DELETE RESTRICT ON UPDATE RESTRICT.

### `promotions`

[VERIFIED: RUNTIME] `php artisan db:table promotions --json`; model: `app/Domains/Marketing/Models/Promotion.php`; traits: HasFactory.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `title` | `varchar(255)` | no | — | — |
| `headline` | `varchar(255)` | no | — | — |
| `subheadline` | `text` | yes | `NULL` | — |
| `cta_text` | `varchar(100)` | no | — | — |
| `cta_url` | `varchar(255)` | no | — | — |
| `banner_image_path` | `varchar(255)` | yes | `NULL` | — |
| `is_active` | `tinyint(1)` | no | `0` | — |
| `display_type` | `enum('top_bar','floating_modal','inline_card')` | no | — | — |
| `starts_at` | `datetime` | yes | `NULL` | — |
| `ends_at` | `datetime` | yes | `NULL` | — |
| `has_countdown` | `tinyint(1)` | no | `0` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `primary(id)`. Foreign keys: none.

### `resource_categories`

[VERIFIED: RUNTIME] `php artisan db:table resource_categories --json`; model: `app/Domains/Resources/Models/ResourceCategory.php`; traits: HasFactory; relationships: resources:HasMany.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `name` | `varchar(255)` | no | — | — |
| `slug` | `varchar(255)` | no | — | unique |
| `sort_order` | `int(11)` | no | `0` | — |
| `active` | `tinyint(1)` | no | `1` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `primary(id)`; `resource_categories_slug_unique(slug)` unique. Foreign keys: none.

### `resource_downloads`

[VERIFIED: RUNTIME] `php artisan db:table resource_downloads --json`; model: `app/Domains/Resources/Models/ResourceDownload.php`; traits: HasFactory; relationships: resource:BelongsTo, contact:BelongsTo, request:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `resource_id` | `bigint(20) unsigned` | no | — | FK→resources.id |
| `contact_id` | `bigint(20) unsigned` | yes | `NULL` | FK→contacts.id |
| `request_id` | `bigint(20) unsigned` | yes | `NULL` | FK→resource_requests.id |
| `visitor_token` | `varchar(64)` | yes | `NULL` | — |
| `session_token` | `varchar(64)` | yes | `NULL` | — |
| `created_at` | `timestamp` | no | — | — |

Indexes: `primary(id)`; `resource_downloads_contact_id_foreign(contact_id)`; `resource_downloads_created_at_index(created_at)`; `resource_downloads_request_id_foreign(request_id)`; `resource_downloads_resource_id_foreign(resource_id)`; `resource_downloads_session_token_index(session_token)`; `resource_downloads_visitor_token_index(visitor_token)`. Foreign keys: `contact_id`→`contacts.id` ON DELETE RESTRICT ON UPDATE RESTRICT; `request_id`→`resource_requests.id` ON DELETE RESTRICT ON UPDATE RESTRICT; `resource_id`→`resources.id` ON DELETE RESTRICT ON UPDATE RESTRICT.

### `resource_requests`

[VERIFIED: RUNTIME] `php artisan db:table resource_requests --json`; model: `app/Domains/Resources/Models/ResourceRequest.php`; traits: HasFactory; relationships: contact:BelongsTo, resource:BelongsTo, downloads:HasMany.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `contact_id` | `bigint(20) unsigned` | no | — | FK→contacts.id |
| `resource_id` | `bigint(20) unsigned` | no | — | FK→resources.id |
| `visitor_token` | `varchar(64)` | yes | `NULL` | — |
| `session_token` | `varchar(64)` | yes | `NULL` | — |
| `source` | `varchar(255)` | yes | `NULL` | — |
| `medium` | `varchar(255)` | yes | `NULL` | — |
| `campaign` | `varchar(255)` | yes | `NULL` | — |
| `content` | `varchar(255)` | yes | `NULL` | — |
| `term` | `varchar(255)` | yes | `NULL` | — |
| `landing_page` | `varchar(255)` | yes | `NULL` | — |
| `created_at` | `timestamp` | no | — | — |

Indexes: `primary(id)`; `resource_requests_contact_id_foreign(contact_id)`; `resource_requests_created_at_index(created_at)`; `resource_requests_resource_id_foreign(resource_id)`; `resource_requests_session_token_index(session_token)`; `resource_requests_visitor_token_index(visitor_token)`. Foreign keys: `contact_id`→`contacts.id` ON DELETE RESTRICT ON UPDATE RESTRICT; `resource_id`→`resources.id` ON DELETE RESTRICT ON UPDATE RESTRICT.

### `resource_translations`

[VERIFIED: RUNTIME] `php artisan db:table resource_translations --json`; model: `app/Domains/Resources/Models/ResourceTranslation.php`; traits: HasFactory; relationships: resource:BelongsTo, sourceRevision:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `resource_id` | `bigint(20) unsigned` | no | — | unique; FK→resources.id |
| `locale` | `varchar(5)` | no | — | — |
| `source_revision_id` | `bigint(20) unsigned` | yes | `NULL` | — |
| `status` | `varchar(20)` | no | `'draft'` | — |
| `title` | `varchar(255)` | no | — | — |
| `short_description` | `text` | yes | `NULL` | — |
| `full_description` | `text` | yes | `NULL` | — |
| `published_at` | `datetime` | yes | `NULL` | — |
| `live_locale` | `varchar(5)` | yes | `NULL` | unique |
| `draft_locale` | `varchar(5)` | yes | `NULL` | unique |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `primary(id)`; `resource_translations_status_index(status)`; `uq_resource_trans_draft(resource_id,draft_locale)` unique; `uq_resource_trans_live(resource_id,live_locale)` unique. Foreign keys: `resource_id`→`resources.id` ON DELETE CASCADE ON UPDATE RESTRICT.

### `resources`

[VERIFIED: RUNTIME] `php artisan db:table resources --json`; model: `app/Domains/Resources/Models/Resource.php`; traits: HasFactory; relationships: category:BelongsTo, requests:HasMany, downloads:HasMany, revisions:MorphMany.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `category_id` | `bigint(20) unsigned` | no | — | FK→resource_categories.id |
| `title` | `varchar(255)` | no | — | — |
| `slug` | `varchar(255)` | no | — | unique |
| `short_description` | `text` | yes | `NULL` | — |
| `full_description` | `text` | yes | `NULL` | — |
| `file_path` | `varchar(255)` | yes | `NULL` | — |
| `external_url` | `varchar(500)` | yes | `NULL` | — |
| `file_type` | `varchar(20)` | yes | `NULL` | — |
| `file_size` | `bigint(20) unsigned` | no | `0` | — |
| `cover_image_path` | `varchar(255)` | yes | `NULL` | — |
| `is_gated` | `tinyint(1)` | no | `1` | — |
| `status` | `varchar(32)` | no | `'draft'` | — |
| `featured` | `tinyint(1)` | no | `0` | — |
| `sort_order` | `int(11)` | no | `0` | — |
| `published_at` | `datetime` | yes | `NULL` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |
| `deleted_at` | `timestamp` | yes | `NULL` | — |

Indexes: `primary(id)`; `resources_category_id_foreign(category_id)`; `resources_slug_unique(slug)` unique; `resources_status_index(status)`. Foreign keys: `category_id`→`resource_categories.id` ON DELETE RESTRICT ON UPDATE RESTRICT.

### `session_ledger_entries`

[VERIFIED: RUNTIME] `php artisan db:table session_ledger_entries --json`; model: `app/Domains/Students/Models/SessionLedgerEntry.php`; traits: HasFactory; relationships: package:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `student_id` | `bigint(20) unsigned` | no | — | FK→students.id |
| `student_package_id` | `bigint(20) unsigned` | yes | `NULL` | FK→student_packages.id |
| `booking_id` | `bigint(20) unsigned` | yes | `NULL` | unique; FK→bookings.id |
| `idempotency_key` | `varchar(80)` | no | — | unique |
| `entry_type` | `enum('package_grant','session_consumed','courtesy_adjustment','cancellation_restore','expiration_forfeit')` | no | — | unique |
| `credit_change` | `int(11)` | no | — | — |
| `description` | `varchar(255)` | no | — | — |
| `created_by` | `bigint(20) unsigned` | yes | `NULL` | FK→administrators.id |
| `created_at` | `timestamp` | no | — | — |

Indexes: `primary(id)`; `session_ledger_entries_booking_id_entry_type_unique(booking_id,entry_type)` unique; `session_ledger_entries_created_by_foreign(created_by)`; `session_ledger_entries_idempotency_key_unique(idempotency_key)` unique; `session_ledger_entries_student_id_foreign(student_id)`; `session_ledger_entries_student_package_id_student_id_index(student_package_id,student_id)`. Foreign keys: `booking_id`→`bookings.id` ON DELETE RESTRICT ON UPDATE RESTRICT; `created_by`→`administrators.id` ON DELETE SET NULL ON UPDATE RESTRICT; `student_id`→`students.id` ON DELETE RESTRICT ON UPDATE RESTRICT; `student_package_id`→`student_packages.id` ON DELETE RESTRICT ON UPDATE RESTRICT.

### `session_reschedules`

[VERIFIED: RUNTIME] `php artisan db:table session_reschedules --json`; model: none (query-builder table).

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `booking_id` | `bigint(20) unsigned` | no | — | FK→bookings.id |
| `actor_type` | `enum('student','admin','system')` | no | — | — |
| `actor_id` | `bigint(20) unsigned` | yes | `NULL` | — |
| `old_start_at_utc` | `datetime` | no | — | — |
| `new_start_at_utc` | `datetime` | no | — | — |
| `old_timezone` | `varchar(64)` | no | — | — |
| `new_timezone` | `varchar(64)` | no | — | — |
| `idempotency_key` | `varchar(80)` | no | — | unique |
| `ip_address` | `varchar(45)` | yes | `NULL` | — |
| `created_at` | `timestamp` | no | — | — |

Indexes: `primary(id)`; `session_reschedules_booking_id_foreign(booking_id)`; `session_reschedules_idempotency_key_unique(idempotency_key)` unique. Foreign keys: `booking_id`→`bookings.id` ON DELETE RESTRICT ON UPDATE RESTRICT.

### `session_types`

[VERIFIED: RUNTIME] `php artisan db:table session_types --json`; model: `app/Domains/Booking/Models/SessionType.php`; traits: HasFactory; relationships: bookings:HasMany, holds:HasMany.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `title` | `varchar(255)` | no | — | — |
| `slug` | `varchar(255)` | no | — | unique |
| `description` | `text` | yes | `NULL` | — |
| `duration_minutes` | `int(10) unsigned` | no | `60` | — |
| `price` | `decimal(8,2)` | no | `0.00` | — |
| `currency` | `varchar(3)` | no | `'USD'` | — |
| `active` | `tinyint(1)` | no | `1` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `primary(id)`; `session_types_slug_unique(slug)` unique. Foreign keys: none.

### `settings`

[VERIFIED: RUNTIME] `php artisan db:table settings --json`; model: `app/Domains/CMS/Models/Setting.php`; traits: HasFactory.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `key` | `varchar(100)` | no | — | unique |
| `value` | `longtext` | yes | `NULL` | — |
| `group` | `varchar(50)` | no | `'general'` | — |
| `is_public` | `tinyint(1)` | no | `0` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `primary(id)`; `settings_group_index(group)`; `settings_key_unique(key)` unique. Foreign keys: none.

### `social_links`

[VERIFIED: RUNTIME] `php artisan db:table social_links --json`; model: `app/Domains/CMS/Models/SocialLink.php`; traits: HasFactory.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `platform` | `varchar(50)` | no | — | — |
| `url_or_phone` | `varchar(255)` | no | — | — |
| `label` | `varchar(255)` | no | — | — |
| `default_message` | `text` | yes | `NULL` | — |
| `enabled` | `tinyint(1)` | no | `1` | — |
| `sort_order` | `int(11)` | no | `0` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `primary(id)`. Foreign keys: none.

### `student_auth_attempts`

[VERIFIED: RUNTIME] `php artisan db:table student_auth_attempts --json`; model: none (query-builder table).

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `fingerprint` | `varchar(80)` | no | — | PK |
| `consecutive_failures` | `int(10) unsigned` | no | `0` | — |
| `cooldown_until` | `datetime` | yes | `NULL` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `primary(fingerprint)`; `student_auth_attempts_cooldown_until_index(cooldown_until)`. Foreign keys: none.

### `student_packages`

[VERIFIED: RUNTIME] `php artisan db:table student_packages --json`; model: `app/Domains/Students/Models/StudentPackage.php`; traits: HasFactory; relationships: student:BelongsTo, ledgerEntries:HasMany, payments:HasMany, refunds:HasMany.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `student_id` | `bigint(20) unsigned` | no | — | FK→students.id |
| `package_name` | `varchar(255)` | no | — | — |
| `original_price` | `decimal(10,2)` | no | — | — |
| `discount_amount` | `decimal(10,2)` | no | `0.00` | — |
| `final_price` | `decimal(10,2)` | no | — | — |
| `currency` | `varchar(3)` | no | `'USD'` | — |
| `total_sessions_allocated` | `int(10) unsigned` | no | — | — |
| `expiration_date` | `date` | yes | `NULL` | — |
| `status` | `enum('active','completed','expired','cancelled')` | no | `'active'` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `primary(id)`; `student_packages_student_id_status_expiration_date_index(student_id,status,expiration_date)`. Foreign keys: `student_id`→`students.id` ON DELETE RESTRICT ON UPDATE RESTRICT.

### `students`

[VERIFIED: RUNTIME] `php artisan db:table students --json`; model: `app/Domains/Students/Models/Student.php`; traits: HasFactory; relationships: packages:HasMany, bookings:HasMany, possibleDuplicateOf:BelongsTo, mergedInto:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `first_name` | `varchar(255)` | no | — | — |
| `last_name` | `varchar(255)` | no | — | — |
| `name_normalized` | `varchar(255)` | no | — | — |
| `email` | `varchar(255)` | yes | `NULL` | — |
| `email_normalized` | `varchar(255)` | yes | `NULL` | — |
| `phone` | `varchar(255)` | yes | `NULL` | — |
| `phone_normalized` | `varchar(255)` | yes | `NULL` | — |
| `date_of_birth` | `date` | yes | `NULL` | — |
| `preferred_timezone` | `varchar(64)` | yes | `NULL` | — |
| `identity_status` | `enum('verified','legacy_unverified','merged')` | no | `'legacy_unverified'` | — |
| `possible_duplicate_of_student_id` | `bigint(20) unsigned` | yes | `NULL` | FK→students.id |
| `merged_into_student_id` | `bigint(20) unsigned` | yes | `NULL` | FK→students.id |
| `internal_notes` | `text` | yes | `NULL` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |
| `deleted_at` | `timestamp` | yes | `NULL` | — |

Indexes: `primary(id)`; `students_email_index(email)`; `students_email_normalized_index(email_normalized)`; `students_identity_status_index(identity_status)`; `students_merged_into_student_id_foreign(merged_into_student_id)`; `students_name_normalized_index(name_normalized)`; `students_phone_index(phone)`; `students_phone_normalized_index(phone_normalized)`; `students_possible_duplicate_of_student_id_foreign(possible_duplicate_of_student_id)`. Foreign keys: `merged_into_student_id`→`students.id` ON DELETE SET NULL ON UPDATE RESTRICT; `possible_duplicate_of_student_id`→`students.id` ON DELETE SET NULL ON UPDATE RESTRICT.

### `visitor_funnel_progressions`

[VERIFIED: RUNTIME] `php artisan db:table visitor_funnel_progressions --json`; model: `app/Domains/Analytics/Models/VisitorFunnelProgression.php`; traits: HasFactory; relationships: visitor:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `visitor_id` | `bigint(20) unsigned` | no | — | unique; FK→visitors.id |
| `cohort_date` | `date` | no | — | — |
| `visitor_at` | `datetime` | no | — | — |
| `booking_cta_observed_at` | `datetime` | yes | `NULL` | — |
| `booking_cta_qualified_at` | `datetime` | yes | `NULL` | — |
| `booking_cta_provenance` | `varchar(20)` | no | `'none'` | — |
| `booking_started_observed_at` | `datetime` | yes | `NULL` | — |
| `booking_started_qualified_at` | `datetime` | yes | `NULL` | — |
| `booking_started_provenance` | `varchar(20)` | no | `'none'` | — |
| `slot_held_observed_at` | `datetime` | yes | `NULL` | — |
| `slot_held_qualified_at` | `datetime` | yes | `NULL` | — |
| `slot_held_provenance` | `varchar(20)` | no | `'none'` | — |
| `booking_completed_observed_at` | `datetime` | yes | `NULL` | — |
| `booking_completed_qualified_at` | `datetime` | yes | `NULL` | — |
| `booking_completed_provenance` | `varchar(20)` | no | `'none'` | — |
| `reconciled_at` | `datetime` | yes | `NULL` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |

Indexes: `primary(id)`; `visitor_funnel_progressions_cohort_date_index(cohort_date)`; `visitor_funnel_progressions_visitor_at_index(visitor_at)`; `visitor_funnel_progressions_visitor_id_unique(visitor_id)` unique. Foreign keys: `visitor_id`→`visitors.id` ON DELETE CASCADE ON UPDATE RESTRICT.

### `visitor_sessions`

[VERIFIED: RUNTIME] `php artisan db:table visitor_sessions --json`; model: `app/Domains/Analytics/Models/VisitorSession.php`; traits: HasFactory; relationships: visitor:BelongsTo.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `session_token` | `varchar(64)` | no | — | unique |
| `session_id` | `varchar(64)` | yes | `NULL` | — |
| `visitor_id` | `bigint(20) unsigned` | no | — | FK→visitors.id |
| `started_at` | `datetime` | no | — | — |
| `last_activity_at` | `datetime` | no | — | — |
| `utm_source` | `varchar(255)` | yes | `NULL` | — |
| `utm_medium` | `varchar(255)` | yes | `NULL` | — |
| `utm_campaign` | `varchar(255)` | yes | `NULL` | — |
| `utm_content` | `varchar(255)` | yes | `NULL` | — |
| `utm_term` | `varchar(255)` | yes | `NULL` | — |
| `referrer` | `text` | yes | `NULL` | — |
| `landing_page` | `varchar(255)` | yes | `NULL` | — |
| `is_bot` | `tinyint(1)` | no | `0` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |
| `detected_country_code` | `char(2)` | yes | `NULL` | — |

Indexes: `primary(id)`; `visitor_sessions_detected_country_code_index(detected_country_code)`; `visitor_sessions_is_bot_index(is_bot)`; `visitor_sessions_last_activity_at_index(last_activity_at)`; `visitor_sessions_session_id_index(session_id)`; `visitor_sessions_session_token_unique(session_token)` unique; `visitor_sessions_started_at_index(started_at)`; `visitor_sessions_visitor_id_foreign(visitor_id)`. Foreign keys: `visitor_id`→`visitors.id` ON DELETE CASCADE ON UPDATE RESTRICT.

### `visitors`

[VERIFIED: RUNTIME] `php artisan db:table visitors --json`; model: `app/Domains/Analytics/Models/Visitor.php`; traits: HasFactory; relationships: sessions:HasMany, funnelProgression:HasOne.

| Column | Type | Nullable | Default | Constraints / FK |
|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | no | — | PK |
| `visitor_token` | `varchar(64)` | no | — | unique |
| `visitor_id` | `varchar(64)` | yes | `NULL` | — |
| `first_seen_at` | `datetime` | no | — | — |
| `last_seen_at` | `datetime` | no | — | — |
| `device_type` | `varchar(30)` | yes | `NULL` | — |
| `user_agent` | `text` | yes | `NULL` | — |
| `is_bot` | `tinyint(1)` | no | `0` | — |
| `acquisition_source` | `varchar(100)` | yes | `NULL` | — |
| `acquisition_medium` | `varchar(100)` | yes | `NULL` | — |
| `acquisition_campaign` | `varchar(100)` | yes | `NULL` | — |
| `acquisition_content` | `varchar(100)` | yes | `NULL` | — |
| `acquisition_term` | `varchar(100)` | yes | `NULL` | — |
| `acquisition_touch_at` | `datetime` | yes | `NULL` | — |
| `created_at` | `timestamp` | yes | `NULL` | — |
| `updated_at` | `timestamp` | yes | `NULL` | — |
| `detected_country_code` | `char(2)` | yes | `NULL` | — |

Indexes: `primary(id)`; `visitors_detected_country_code_index(detected_country_code)`; `visitors_is_bot_index(is_bot)`; `visitors_last_seen_at_index(last_seen_at)`; `visitors_visitor_id_index(visitor_id)`; `visitors_visitor_token_unique(visitor_token)` unique. Foreign keys: none.

## 6. Domain & File Map [VERIFIED: CODE]

| Location | Responsibility / entry points |
| --- | --- |
| `app/Domains/Administration` | Administrator and notification models, reset notification, `AdminNotificationService`; HTTP controllers under `app/Http/Controllers/Admin` implement console operations. |
| `app/Domains/Analytics` | Visitor/session/event/attribution and daily-metric models; `AnalyticsService`, `FunnelProgressionService`, `GeoIpService`, `EngagementCounterService`. |
| `app/Domains/Audit` | Append-only `AuditLog` and sensitive-field redaction in `AuditLogService`. |
| `app/Domains/Availability` | Recurring rules, date exceptions, slot resolution, capacity/conflict checks, calendar-date row locks in `AvailabilityService`. |
| `app/Domains/Booking` | Booking/hold/event/session-type models; booking, hold, cancellation, rescheduling, ICS, meeting-link services; diagnostic synchronization action. |
| `app/Domains/CMS` | Page/FAQ/blog/media/social/settings models, translations and immutable revisions; `ContentService`, `TranslationService`, `BlogService`, `RichTextSanitizer`, `LocalizedUrlService`. |
| `app/Domains/Contacts` | Normalized contact identity and merge logic via `ContactService`; admin contact controllers live under `app/Http/Controllers/Admin`. |
| `app/Domains/Database` | MariaDB vendor/capability guard and transaction-isolation helper in `DatabaseCapability`. |
| `app/Domains/Forms` | Versioned forms/questions/options/assignments/submissions; builder, assignment, submission and validation services. |
| `app/Domains/Games` | Game and localized game translation models; admin/public game controllers render and manage content. |
| `app/Domains/Marketing` | Promotion model and `PromotionService` for publication/schedule evaluation. |
| `app/Domains/Notifications` | `TelegramNotificationService`; scheduled reminder entry point is `SendBookingRemindersCommand`. |
| `app/Domains/Reporting` | `ReportService` calculations and `ExportService` CSV/XLSX output. |
| `app/Domains/Resources` | Categories, resources, translations, gated requests and download records. Public and admin controllers enforce access. |
| `app/Domains/Students` | Student identity/auth attempts, packages, payment/refund records, ledger entries; booking, billing reconciliation, merge/privacy services. |
| `app/Domains/System` | `BackupService` creates/verifies local/offsite archives; backup and restore Artisan commands drive it. |
| `app/Domains/Timezone` | IANA-zone display/conversion services and DST gap/fold exceptions. |
| `app/Http/Middleware` | Locale, maintenance, admin-role, student-session, preview and visitor-session middleware registered by `bootstrap/app.php`. |
| `app/Http/Controllers` | Public page, booking, resource, blog, analytics, pricing and game endpoints; `Admin/` and `Student/` contain their corresponding surfaces. |
| `app/Livewire/BookingWizard.php` + `resources/views/livewire/booking-wizard.blade.php` | Public multi-step reactive booking; holds and timezone changes remain server-authoritative. |
| `resources/views/layouts` | Blade shells for public, admin and student surfaces; `resources/views/admin`, `student`, `public`, `blog` hold screens. |
| `routes/web.php`, `routes/console.php` | HTTP route groups and scheduled command definitions. `bootstrap/app.php` wires middleware and exception JSON behavior. |
| `database/migrations`, `database/seeders`, `database/factories` | Schema history, non-destructive local bootstrap and test data builders. |
| `config/business.php`, `config/auth.php`, `config/filesystems.php`, `config/queue.php` | Domain knobs, dual guards, storage disks and async transport. |
| `tests/Feature`, `tests/Unit` | HTTP/integration and unit verification, including MariaDB-specific real-concurrency tests. |

The map is based on the file inventory command `rg --files app/Domains app/Http/Controllers app/Livewire app/Console config resources/views` and the cited classes; it is a location map, not a claim that every screen has been manually exercised. [VERIFIED: CODE]

## 7. Entity Execution Matrix [VERIFIED: CODE]

| Entity | Table / migration family | Model | Primary service | Validation boundary | Controller / component | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| Booking | `bookings`; booking/student migrations | `Booking` | `BookingService`, `StudentBookingService`, `RescheduleService`, `CancellationService` | Service-side authoritative `AvailabilityService::validateSlot` and controller/Livewire input checks | `BookingWizard`, public `BookingController`, admin `BookingController`, student `BookingController`/`RescheduleController` | **High-Sensitivity**: times, contact, credits and token ownership; `BookingService::createPublicBooking` locks calendar dates. |
| Booking hold | `booking_holds` | `BookingHold` | `BookingHoldService` | Slot/session/visitor token checks in service and wizard | `BookingWizard`, `BookingController` | **High-Sensitivity**: 10-minute TTL, active-hold conflict, release/cleanup. |
| Availability | `availability_rules`, `availability_exceptions` | `AvailabilityRule`, `AvailabilityException` | `AvailabilityService`, `SlotResolver` | Admin `AvailabilityController` input validation; canonical slot revalidation on commit | Admin `AvailabilityController`, wizard | **High-Sensitivity**: business calendar and buffer overlap. |
| Contact | `contacts` | `Contact` | `ContactService` | Normalization and unique contact identity in service/schema | Admin `ContactController`, public resource/booking flows | **High-Sensitivity**: canonical email and merge relationships. |
| Student | `students` | `Student` | `StudentIdentityService`, `StudentMergeService`, `StudentPrivacyService` | `Student/AuthController` verifies DOB + two identifiers; `EnsureStudentAuthenticated` checks session | Admin `StudentController`, student `AuthController`/`DashboardController` | **High-Sensitivity**: separate student guard and ownership. |
| Package / ledger | `student_packages`, `session_ledger_entries` | `StudentPackage`, `SessionLedgerEntry` | `StudentLedgerService` | Package/balance validation inside locked transaction | Admin `StudentBillingController`, student booking controllers | **High-Sensitivity**: money/credits; balance derived from ledger. |
| Payment / refund | `payment_records`, `payment_refunds` | `PaymentRecord`, `PaymentRefund` | `StudentLedgerService`, `BillingReconciliationService` | Refund amount/source validation, locked ledger state | Admin `StudentBillingController` | **High-Sensitivity**: manual cashier, no gateway payment webhook in `routes/web.php`. |
| Form / version / submission | `forms`, `form_versions`, `form_questions`, `form_question_options`, `form_submissions`, `form_answers`, `form_submission_revisions` | Corresponding `app/Domains/Forms/Models` classes | `FormBuilderService`, `FormAssignmentService`, `FormSubmissionService`, `FormValidationService` | Published version and question answer validation; student assignment ownership | Admin `FormController`, student `FormController` | **High-Sensitivity**: private student work and immutable version references. |
| Blog | `blogs`, `blog_revisions`, `blog_slug_redirects` | `Blog`, `BlogRevision`, `BlogSlugRedirect` | `BlogService`, `RichTextSanitizer` | Admin `BlogController` validation/sanitization | Admin `BlogController`, public `BlogController` | Draft/publish, revision history and redirects. |
| Promotion | `promotions` | `Promotion` | `PromotionService` | Admin `PromotionController` validates media, CTA and Cairo schedule | Admin `PromotionController`, public Blade component | Publication and timed display. |
| Resource / request / download | `resources`, `resource_categories`, `resource_requests`, `resource_downloads` (+ translations) | `Resource`, `ResourceCategory`, `ResourceRequest`, `ResourceDownload` | Public `ResourceController` grant/download logic | Token, visitor/session ownership and file-path checks; admin upload validation | Public `ResourceController`, admin `ResourceController`/`ResourceCategoryController` | **High-Sensitivity**: private gated files; single-use temporary grant. |
| Page / FAQ / translation / revision | `pages`, `faqs`, `*_translations`, `content_revisions`, `entity_translation_revisions` | `Page`, `Faq`, translation/revision models | `ContentService`, `TranslationService`, `RichTextSanitizer` | Admin content/page/translation controllers; preview middleware | Admin `PageController`, `ContentController`, `TranslationController`; public `PageController` | English source, draft vs published, stale translation reconciliation. |
| Game | `games`, `game_translations` | `Game`, `GameTranslation` | Admin/public game controller actions | Admin validation and translation workflow | Admin `GameController`, public `GameController` | Game catalog, localized public render and draft revisions. |
| Visitor / session / event / touch / metric | `visitors`, `visitor_sessions`, `analytics_events`, `marketing_touches`, `daily_metrics`, `daily_country_metrics`, `visitor_funnel_progressions` | Corresponding analytics models | `AnalyticsService`, `FunnelProgressionService`, `ReportService` | Event allow-list/metadata/session binding; bot skip | Public `AnalyticsController`, admin analytics/report controllers; `TrackVisitorSession` middleware | First-party telemetry, Cairo-day aggregation, retention. |
| Setting | `settings`, `social_links` | `Setting`, `SocialLink` | `SettingController` | Admin role; maintenance toggle additionally super-admin | Admin `SettingController`, `ContentController` | **High-Sensitivity** for maintenance and encrypted Telegram token. |
| Backup archive | No domain table; storage ZIP/manifest | — | `BackupService` | CLI/admin super-admin authorization and restore confirmation | `RunBackupCommand`, `RestoreBackupCommand`, admin `BackupController` | **High-Sensitivity**: destructive restore and secrets in backup. |

Model/table mapping comes from the runtime schema in Section 5 plus `rg --files app/Domains`; execution paths are in the cited service/controller files and `routes/web.php`. [VERIFIED: CODE / RUNTIME]

## 8. Route & Authorization Inventory [VERIFIED: RUNTIME]

Generated from `php artisan route:list --json --except-vendor` (217 application routes). Framework/Livewire/vendor endpoints are excluded. Middleware abbreviations: `web` includes Laravel's web session/CSRF stack; `THROTTLE:name` is a named limiter defined in `AppServiceProvider`; `auth:web` authenticates administrators; `EnsureAdminRole` lists accepted roles; `EnsureStudentAuthenticated` validates the dedicated student session. “None (middleware only)” means no additional policy/gate was identified for that route, not that it is public. The final column names verified controller/service checks where applicable; consult the handler before changing authorization. [VERIFIED: RUNTIME / CODE]

### Public (67 routes) [VERIFIED: RUNTIME]

| Method | URI | Route Name | Action / Handler | Middleware | Enforcing Policy/Gate |
| --- | --- | --- | --- | --- | --- |
| GET\|HEAD | `/` | `home` | `HomeController@index` | `web` | None (middleware only) |
| GET\|HEAD | `_arabictutor-landing` | `home.internal` | `HomeController@index` | `web` | None (middleware only) |
| GET\|HEAD | `about` | `about` | `PageController@about` | `web` | None (middleware only) |
| GET\|HEAD | `about/preview` | `about.preview` | `PageController@previewAbout` | `web, ApplyAdminNoindexHeaders, EnsureAdminPreviewAccess` | None (middleware only) |
| POST | `analytics/event` | `analytics.track` | `AnalyticsController@track` | `web, THROTTLE:60,1` | Event allow-list + session binding (`AnalyticsService`) |
| POST | `api/analytics/events` | `analytics.events` | `AnalyticsController@track` | `web, THROTTLE:60,1` | Event allow-list + session binding (`AnalyticsService`) |
| GET\|HEAD | `blog` | `blog.index` | `BlogController@index` | `web` | None (middleware only) |
| GET\|HEAD | `blog/{slug}` | `blog.show` | `BlogController@show` | `web` | None (middleware only) |
| GET\|HEAD | `book` | `—` | `Route closure (see `routes/web.php`)` | `web` | None (middleware only) |
| GET\|HEAD | `book/confirmation/{token}` | `—` | `Route closure (see `routes/web.php`)` | `web` | None (middleware only) |
| GET\|HEAD | `book/confirmation/{token}/ics` | `—` | `Route closure (see `routes/web.php`)` | `web` | None (middleware only) |
| POST | `book/{token}/cancel` | `—` | `BookingController@cancel` | `web, THROTTLE:booking-cancel` | Confirmation token / hold ownership (`BookingController`, `BookingService`) |
| GET\|HEAD | `book/{token}/reschedule` | `—` | `Route closure (see `routes/web.php`)` | `web` | None (middleware only) |
| POST | `book/{token}/reschedule` | `—` | `BookingController@processReschedule` | `web, THROTTLE:booking-reschedule` | Always aborts 403; self-service disabled (`BookingController`) |
| GET\|HEAD | `booking` | `booking.index` | `BookingController@index` | `web` | Confirmation token / hold ownership (`BookingController`, `BookingService`) |
| GET\|HEAD | `booking/confirmation/{token}` | `booking.confirmation` | `BookingController@confirmation` | `web` | Confirmation token / hold ownership (`BookingController`, `BookingService`) |
| GET\|HEAD | `booking/confirmation/{token}/ics` | `booking.ics` | `BookingController@ics` | `web` | Confirmation token / hold ownership (`BookingController`, `BookingService`) |
| POST | `booking/{token}/cancel` | `booking.cancel` | `BookingController@cancel` | `web, THROTTLE:booking-cancel` | Confirmation token / hold ownership (`BookingController`, `BookingService`) |
| GET\|HEAD | `booking/{token}/reschedule` | `booking.reschedule` | `BookingController@showReschedule` | `web` | Confirmation token lookup; redirects to contact instructions (`BookingController`) |
| POST | `booking/{token}/reschedule` | `booking.reschedule.submit` | `BookingController@processReschedule` | `web, THROTTLE:booking-reschedule` | Always aborts 403; self-service disabled (`BookingController`) |
| GET\|HEAD | `de` | `home.de` | `HomeController@index` | `web` | None (middleware only) |
| GET\|HEAD | `de/agb` | `terms.de` | `PageController@terms` | `web` | None (middleware only) |
| GET\|HEAD | `de/buchen` | `booking.de` | `BookingController@index` | `web` | Confirmation token / hold ownership (`BookingController`, `BookingService`) |
| GET\|HEAD | `de/buchen/bestaetigung/{token}` | `booking.confirmation.de` | `BookingController@confirmation` | `web` | Confirmation token / hold ownership (`BookingController`, `BookingService`) |
| GET\|HEAD | `de/datenschutz` | `privacy.de` | `PageController@privacy` | `web` | None (middleware only) |
| GET\|HEAD | `de/faq` | `faq.de` | `PageController@faq` | `web` | None (middleware only) |
| GET\|HEAD | `de/p/{slug}` | `page.show.de` | `PageController@show` | `web` | None (middleware only) |
| GET\|HEAD | `de/preise` | `pricing.de` | `PricingController@index` | `web` | None (middleware only) |
| GET\|HEAD | `de/ressourcen` | `resources.de` | `ResourceController@index` | `web` | Grant token + session/visitor (`ResourceController`) |
| GET\|HEAD | `de/ressourcen/{slug}` | `resources.show.de` | `ResourceController@show` | `web` | Grant token + session/visitor (`ResourceController`) |
| GET\|HEAD | `de/ressourcen/{slug}/download` | `resources.download.de` | `ResourceController@download` | `web` | Grant token + session/visitor (`ResourceController`) |
| POST | `de/ressourcen/{slug}/request` | `resources.request.de` | `ResourceController@requestAccess` | `web, THROTTLE:resource-request` | Grant token + session/visitor (`ResourceController`) |
| GET\|HEAD | `de/spiele` | `games.de` | `GameController@index` | `web` | None (middleware only) |
| GET\|HEAD | `de/spiele/{slug}` | `games.show.de` | `GameController@show` | `web` | None (middleware only) |
| GET\|HEAD | `de/ueber-uns` | `about.de` | `PageController@about` | `web` | None (middleware only) |
| GET\|HEAD | `faq` | `faq` | `PageController@faq` | `web` | None (middleware only) |
| GET\|HEAD | `faq/preview` | `faq.preview` | `PageController@previewFaq` | `web, ApplyAdminNoindexHeaders, EnsureAdminPreviewAccess` | None (middleware only) |
| GET\|HEAD | `fr` | `home.fr` | `HomeController@index` | `web` | None (middleware only) |
| GET\|HEAD | `fr/a-propos` | `about.fr` | `PageController@about` | `web` | None (middleware only) |
| GET\|HEAD | `fr/conditions` | `terms.fr` | `PageController@terms` | `web` | None (middleware only) |
| GET\|HEAD | `fr/confidentialite` | `privacy.fr` | `PageController@privacy` | `web` | None (middleware only) |
| GET\|HEAD | `fr/faq` | `faq.fr` | `PageController@faq` | `web` | None (middleware only) |
| GET\|HEAD | `fr/jeux` | `games.fr` | `GameController@index` | `web` | None (middleware only) |
| GET\|HEAD | `fr/jeux/{slug}` | `games.show.fr` | `GameController@show` | `web` | None (middleware only) |
| GET\|HEAD | `fr/p/{slug}` | `page.show.fr` | `PageController@show` | `web` | None (middleware only) |
| GET\|HEAD | `fr/reservation` | `booking.fr` | `BookingController@index` | `web` | Confirmation token / hold ownership (`BookingController`, `BookingService`) |
| GET\|HEAD | `fr/reservation/confirmation/{token}` | `booking.confirmation.fr` | `BookingController@confirmation` | `web` | Confirmation token / hold ownership (`BookingController`, `BookingService`) |
| GET\|HEAD | `fr/ressources` | `resources.fr` | `ResourceController@index` | `web` | Grant token + session/visitor (`ResourceController`) |
| GET\|HEAD | `fr/ressources/{slug}` | `resources.show.fr` | `ResourceController@show` | `web` | Grant token + session/visitor (`ResourceController`) |
| GET\|HEAD | `fr/ressources/{slug}/download` | `resources.download.fr` | `ResourceController@download` | `web` | Grant token + session/visitor (`ResourceController`) |
| POST | `fr/ressources/{slug}/request` | `resources.request.fr` | `ResourceController@requestAccess` | `web, THROTTLE:resource-request` | Grant token + session/visitor (`ResourceController`) |
| GET\|HEAD | `fr/tarifs` | `pricing.fr` | `PricingController@index` | `web` | None (middleware only) |
| GET\|HEAD | `games` | `games.index` | `GameController@index` | `web` | None (middleware only) |
| GET\|HEAD | `games/{slug}` | `games.show` | `GameController@show` | `web` | None (middleware only) |
| GET\|HEAD | `games/{slug}/preview` | `games.preview` | `GameController@preview` | `web, ApplyAdminNoindexHeaders, EnsureAdminPreviewAccess` | None (middleware only) |
| POST | `games/{slug}/track` | `games.track` | `GameController@track` | `web, THROTTLE:game-track` | None (middleware only) |
| GET\|HEAD | `p/{slug}` | `page.show` | `PageController@show` | `web` | None (middleware only) |
| GET\|HEAD | `p/{slug}/preview` | `pages.preview` | `PageController@preview` | `web, ApplyAdminNoindexHeaders, EnsureAdminPreviewAccess` | None (middleware only) |
| GET\|HEAD | `preview/home` | `home.preview` | `HomeController@preview` | `web, ApplyAdminNoindexHeaders, EnsureAdminPreviewAccess` | None (middleware only) |
| GET\|HEAD | `pricing` | `pricing` | `PricingController@index` | `web` | None (middleware only) |
| GET\|HEAD | `privacy` | `privacy` | `PageController@privacy` | `web` | None (middleware only) |
| GET\|HEAD | `resources` | `resources.index` | `ResourceController@index` | `web` | Grant token + session/visitor (`ResourceController`) |
| GET\|HEAD | `resources/{slug}` | `resources.show` | `ResourceController@show` | `web` | Grant token + session/visitor (`ResourceController`) |
| GET\|HEAD | `resources/{slug}/download` | `resources.download` | `ResourceController@download` | `web` | Grant token + session/visitor (`ResourceController`) |
| GET\|HEAD | `resources/{slug}/preview` | `resources.preview` | `ResourceController@preview` | `web, ApplyAdminNoindexHeaders, EnsureAdminPreviewAccess` | Grant token + session/visitor (`ResourceController`) |
| POST | `resources/{slug}/request` | `resources.request` | `ResourceController@requestAccess` | `web, THROTTLE:resource-request` | Grant token + session/visitor (`ResourceController`) |
| GET\|HEAD | `terms` | `terms` | `PageController@terms` | `web` | None (middleware only) |

### Student (10 routes) [VERIFIED: RUNTIME]

| Method | URI | Route Name | Action / Handler | Middleware | Enforcing Policy/Gate |
| --- | --- | --- | --- | --- | --- |
| GET\|HEAD | `student` | `student.dashboard` | `Student\DashboardController@index` | `web, ApplyAdminNoindexHeaders, EnsureStudentAuthenticated` | None (middleware only) |
| POST | `student/bookings` | `student.bookings.store` | `Student\BookingController@store` | `web, ApplyAdminNoindexHeaders, EnsureStudentAuthenticated, THROTTLE:student-booking-finalize` | Student ownership + signed slot (`StudentBookingService` / `RescheduleService`) |
| GET\|HEAD | `student/bookings/create` | `student.bookings.create` | `Student\BookingController@create` | `web, ApplyAdminNoindexHeaders, EnsureStudentAuthenticated` | Student ownership + signed slot (`StudentBookingService` / `RescheduleService`) |
| GET\|HEAD | `student/bookings/{booking}/reschedule` | `student.bookings.reschedule` | `Student\RescheduleController@show` | `web, ApplyAdminNoindexHeaders, EnsureStudentAuthenticated` | student_id ownership + status/cutoff (`Student/RescheduleController::show`) |
| POST | `student/bookings/{booking}/reschedule` | `student.bookings.reschedule.submit` | `Student\RescheduleController@update` | `web, ApplyAdminNoindexHeaders, EnsureStudentAuthenticated, THROTTLE:booking-reschedule` | student_id ownership + signed slot (`Student/RescheduleController::update`) |
| GET\|HEAD | `student/forms/{slug}` | `student.forms.show` | `Student\FormController@show` | `web, ApplyAdminNoindexHeaders, EnsureStudentAuthenticated` | Student assignment/ownership (`Student/FormController`) |
| POST | `student/forms/{slug}` | `student.forms.save` | `Student\FormController@save` | `web, ApplyAdminNoindexHeaders, EnsureStudentAuthenticated, THROTTLE:student-form-save` | Student assignment/ownership (`Student/FormController`) |
| GET\|HEAD | `student/login` | `student.login` | `Student\AuthController@showLogin` | `web, ApplyAdminNoindexHeaders` | DOB + two identifiers / session (`Student/AuthController`) |
| POST | `student/login` | `student.login.submit` | `Student\AuthController@login` | `web, ApplyAdminNoindexHeaders, THROTTLE:student-verification` | DOB + two identifiers / session (`Student/AuthController`) |
| POST | `student/logout` | `student.logout` | `Student\AuthController@logout` | `web, ApplyAdminNoindexHeaders, EnsureStudentAuthenticated` | DOB + two identifiers / session (`Student/AuthController`) |

### Admin (140 routes) [VERIFIED: RUNTIME]

| Method | URI | Route Name | Action / Handler | Middleware | Enforcing Policy/Gate |
| --- | --- | --- | --- | --- | --- |
| GET\|HEAD | `admin` | `admin.dashboard` | `Admin\DashboardController@index` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/administrators` | `admin.administrators.index` | `Admin\AdministratorController@index` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin` | Role restriction via middleware; controller self-safety |
| POST | `admin/administrators` | `admin.administrators.store` | `Admin\AdministratorController@store` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin` | Role restriction via middleware; controller self-safety |
| GET\|HEAD | `admin/administrators/create` | `admin.administrators.create` | `Admin\AdministratorController@create` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin` | Role restriction via middleware; controller self-safety |
| PUT\|PATCH | `admin/administrators/{administrator}` | `admin.administrators.update` | `Admin\AdministratorController@update` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin` | Role restriction via middleware; controller self-safety |
| DELETE | `admin/administrators/{administrator}` | `admin.administrators.destroy` | `Admin\AdministratorController@destroy` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin` | Role restriction via middleware; controller self-safety |
| GET\|HEAD | `admin/administrators/{administrator}/edit` | `admin.administrators.edit` | `Admin\AdministratorController@edit` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin` | Role restriction via middleware; controller self-safety |
| GET\|HEAD | `admin/analytics` | `admin.analytics` | `Admin\AnalyticsDashboardController@index` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/analytics/countries` | `admin.analytics.countries` | `Admin\AnalyticsDashboardController@countries` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/analytics/countries/export` | `admin.analytics.countries.export` | `Admin\AnalyticsDashboardController@exportCountries` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/analytics/overview/export` | `admin.analytics.overview.export` | `Admin\AnalyticsDashboardController@exportOverview` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/analytics/sections` | `admin.analytics.sections` | `Admin\AnalyticsDashboardController@sections` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/analytics/sections/export` | `admin.analytics.sections.export` | `Admin\AnalyticsDashboardController@exportSections` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/audit-logs` | `admin.audit-logs` | `Admin\SystemHealthController@auditLogs` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin` | None (middleware only) |
| GET\|HEAD | `admin/availability` | `admin.availability.index` | `Admin\AvailabilityController@index` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/availability/exceptions` | `admin.availability.exceptions.store` | `Admin\AvailabilityController@storeException` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| DELETE | `admin/availability/exceptions/{exception}` | `admin.availability.exception.destroy` | `Admin\AvailabilityController@destroyException` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/availability/rules` | `admin.availability.rules.store` | `Admin\AvailabilityController@storeRule` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| DELETE | `admin/availability/rules/{rule}` | `admin.availability.destroy` | `Admin\AvailabilityController@destroyRule` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/availability/rules/{rule}/toggle` | `admin.availability.toggle` | `Admin\AvailabilityController@toggleRule` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/backups` | `admin.backups.index` | `Admin\BackupController@index` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin` | None (middleware only) |
| POST | `admin/backups` | `admin.backups.create` | `Admin\BackupController@create` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin` | None (middleware only) |
| DELETE | `admin/backups/{filename}` | `admin.backups.destroy` | `Admin\BackupController@destroy` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin` | None (middleware only) |
| GET\|HEAD | `admin/backups/{filename}/download` | `admin.backups.download` | `Admin\BackupController@download` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin` | None (middleware only) |
| GET\|HEAD | `admin/billing/cashier` | `admin.billing.cashier` | `Admin\StudentBillingController@cashier` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | Student/package/payment validation (`StudentBillingController`, `StudentLedgerService`) |
| GET\|HEAD | `admin/billing/check-diagnostic-credit/{student}` | `admin.billing.check_diagnostic` | `Admin\StudentBillingController@checkDiagnosticCredit` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | Student/package/payment validation (`StudentBillingController`, `StudentLedgerService`) |
| GET\|HEAD | `admin/billing/export` | `admin.billing.export` | `Admin\StudentBillingController@exportFinancials` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | Student/package/payment validation (`StudentBillingController`, `StudentLedgerService`) |
| GET\|HEAD | `admin/billing/reconcile` | `admin.billing.reconcile` | `Admin\StudentBillingController@reconcile` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | Student/package/payment validation (`StudentBillingController`, `StudentLedgerService`) |
| GET\|HEAD | `admin/billing/reconcile/export` | `admin.billing.reconcile.export` | `Admin\StudentBillingController@exportReconciliation` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | Student/package/payment validation (`StudentBillingController`, `StudentLedgerService`) |
| GET\|HEAD | `admin/blog` | `admin.blog.index` | `Admin\BlogController@index` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/blog` | `admin.blog.store` | `Admin\BlogController@store` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/blog/create` | `admin.blog.create` | `Admin\BlogController@create` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| PUT | `admin/blog/{blog}` | `admin.blog.update` | `Admin\BlogController@update` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| DELETE | `admin/blog/{blog}` | `admin.blog.destroy` | `Admin\BlogController@destroy` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/blog/{blog}/edit` | `admin.blog.edit` | `Admin\BlogController@edit` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/blog/{blog}/preview` | `admin.blog.preview` | `Admin\BlogController@preview` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/bookings` | `admin.bookings.index` | `Admin\BookingController@index` | `web, ApplyAdminNoindexHeaders, auth:web` | None (middleware only) |
| POST | `admin/bookings` | `admin.bookings.store` | `Admin\BookingController@store` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/bookings/create` | `admin.bookings.create` | `Admin\BookingController@create` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/bookings/{booking}` | `admin.bookings.show` | `Admin\BookingController@show` | `web, ApplyAdminNoindexHeaders, auth:web` | None (middleware only) |
| POST | `admin/bookings/{booking}/cancel` | `admin.bookings.cancel` | `Admin\BookingController@cancel` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/bookings/{booking}/complete` | `admin.bookings.complete` | `Admin\BookingController@complete` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/bookings/{booking}/no-show` | `admin.bookings.no-show` | `Admin\BookingController@markNoShow` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| PATCH | `admin/bookings/{booking}/notes` | `admin.bookings.notes` | `Admin\BookingController@updateNotes` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/bookings/{booking}/reschedule` | `admin.bookings.reschedule` | `Admin\BookingController@reschedule` | `web, ApplyAdminNoindexHeaders, auth:web` | No role guard beyond auth:web; assistant can reach mutation |
| GET\|HEAD | `admin/contacts` | `admin.contacts.index` | `Admin\ContactController@index` | `web, ApplyAdminNoindexHeaders, auth:web` | None (middleware only) |
| GET\|HEAD | `admin/contacts/duplicates` | `admin.contacts.duplicates` | `Admin\ContactController@duplicates` | `web, ApplyAdminNoindexHeaders, auth:web` | None (middleware only) |
| POST | `admin/contacts/merge` | `admin.contacts.merge` | `Admin\ContactController@merge` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/contacts/{contact}` | `admin.contacts.show` | `Admin\ContactController@show` | `web, ApplyAdminNoindexHeaders, auth:web` | None (middleware only) |
| PATCH | `admin/contacts/{contact}/notes` | `admin.contacts.notes` | `Admin\ContactController@updateNotes` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/content` | `admin.content.index` | `Admin\ContentController@index` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/content/faqs` | `admin.content.faq.store` | `Admin\ContentController@storeFaq` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| PUT | `admin/content/faqs/{faq}` | `admin.content.faq.update` | `Admin\ContentController@updateFaq` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| DELETE | `admin/content/faqs/{faq}` | `admin.content.faq.destroy` | `Admin\ContentController@destroyFaq` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| DELETE | `admin/content/faqs/{faq}/draft` | `admin.content.faq.draft.destroy` | `Admin\ContentController@discardFaqDraft` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/content/social` | `admin.content.social.update` | `Admin\ContentController@updateSocial` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/content/social/add` | `admin.content.social.store` | `Admin\ContentController@storeSocial` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/content/social/{socialLink}/toggle` | `admin.content.social.toggle` | `Admin\ContentController@toggleSocial` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/dashboard` | `admin.` | `Admin\DashboardController@index` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/forgot-password` | `admin.password.request` | `Admin\PasswordResetController@showForgotForm` | `web, ApplyAdminNoindexHeaders` | None (middleware only) |
| POST | `admin/forgot-password` | `admin.password.email` | `Admin\PasswordResetController@sendResetLink` | `web, ApplyAdminNoindexHeaders, THROTTLE:password-reset-request` | None (middleware only) |
| POST | `admin/forms` | `admin.forms.store` | `Admin\FormController@store` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/forms` | `admin.forms.index` | `Admin\FormController@index` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin,assistant` | None (middleware only) |
| GET\|HEAD | `admin/forms/create` | `admin.forms.create` | `Admin\FormController@create` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| PUT | `admin/forms/{form}` | `admin.forms.update` | `Admin\FormController@update` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/forms/{form}/archive` | `admin.forms.archive` | `Admin\FormController@archive` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/forms/{form}/edit` | `admin.forms.edit` | `Admin\FormController@edit` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/forms/{form}/export` | `admin.forms.export` | `Admin\FormController@export` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin,assistant` | None (middleware only) |
| PUT | `admin/forms/{form}/publish` | `admin.forms.publish` | `Admin\FormController@publish` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/forms/{form}/submissions` | `admin.forms.submissions` | `Admin\FormController@submissions` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin,assistant` | None (middleware only) |
| GET\|HEAD | `admin/games` | `admin.games.index` | `Admin\GameController@index` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/games` | `admin.games.store` | `Admin\GameController@store` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/games/create` | `admin.games.create` | `Admin\GameController@create` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| PUT | `admin/games/{game}` | `admin.games.update` | `Admin\GameController@update` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| DELETE | `admin/games/{game}` | `admin.games.destroy` | `Admin\GameController@destroy` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| DELETE | `admin/games/{game}/draft` | `admin.games.draft.destroy` | `Admin\GameController@discardDraft` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/games/{game}/edit` | `admin.games.edit` | `Admin\GameController@edit` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/health` | `admin.health` | `Admin\SystemHealthController@index` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin` | None (middleware only) |
| GET\|HEAD | `admin/health/maintenance-visitors/export` | `admin.health.maintenance-visitors.export` | `Admin\SystemHealthController@exportMaintenanceTraffic` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin` | None (middleware only) |
| GET\|HEAD | `admin/leads` | `admin.leads` | `Admin\ContactController@leads` | `web, ApplyAdminNoindexHeaders, auth:web` | None (middleware only) |
| GET\|HEAD | `admin/login` | `admin.login` | `Admin\AuthController@showLogin` | `web, ApplyAdminNoindexHeaders` | None (middleware only) |
| POST | `admin/login` | `admin.login.submit` | `Admin\AuthController@login` | `web, ApplyAdminNoindexHeaders` | None (middleware only) |
| POST | `admin/logout` | `admin.logout` | `Admin\AuthController@logout` | `web, ApplyAdminNoindexHeaders` | None (middleware only) |
| GET\|HEAD | `admin/media` | `admin.media.index` | `Admin\MediaController@index` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/media` | `admin.media.store` | `Admin\MediaController@store` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/media/picker` | `admin.media.picker` | `Admin\MediaController@picker` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| DELETE | `admin/media/{media}` | `admin.media.destroy` | `Admin\MediaController@destroy` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/notifications` | `admin.notifications.index` | `Admin\NotificationController@index` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/notifications/read-all` | `admin.notifications.read-all` | `Admin\NotificationController@markAllAsRead` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| DELETE | `admin/notifications/{notification}` | `admin.notifications.destroy` | `Admin\NotificationController@destroy` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/notifications/{notification}/read` | `admin.notifications.read` | `Admin\NotificationController@markAsRead` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/pages` | `admin.pages.index` | `Admin\PageController@index` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/pages` | `admin.pages.store` | `Admin\PageController@store` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/pages/create` | `admin.pages.create` | `Admin\PageController@create` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| PUT\|PATCH | `admin/pages/{page}` | `admin.pages.update` | `Admin\PageController@update` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| DELETE | `admin/pages/{page}` | `admin.pages.destroy` | `Admin\PageController@destroy` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| DELETE | `admin/pages/{page}/draft` | `admin.pages.draft.destroy` | `Admin\PageController@discardDraft` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/pages/{page}/edit` | `admin.pages.edit` | `Admin\PageController@edit` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/pages/{page}/revisions/{revision}/restore` | `admin.pages.revisions.restore` | `Admin\PageController@restoreRevision` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/promotions` | `admin.promotions.index` | `Admin\PromotionController@index` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/promotions` | `admin.promotions.store` | `Admin\PromotionController@store` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/promotions/create` | `admin.promotions.create` | `Admin\PromotionController@create` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| PUT | `admin/promotions/{promotion}` | `admin.promotions.update` | `Admin\PromotionController@update` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| DELETE | `admin/promotions/{promotion}` | `admin.promotions.destroy` | `Admin\PromotionController@destroy` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/promotions/{promotion}/edit` | `admin.promotions.edit` | `Admin\PromotionController@edit` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/promotions/{promotion}/preview` | `admin.promotions.preview` | `Admin\PromotionController@preview` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin, EnsureAdminPreviewAccess` | None (middleware only) |
| POST | `admin/promotions/{promotion}/toggle` | `admin.promotions.toggle` | `Admin\PromotionController@toggle` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/reports` | `admin.reports.index` | `Admin\ReportController@index` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/reports/export` | `admin.reports.export` | `Admin\ReportController@export` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/reset-password` | `admin.password.update` | `Admin\PasswordResetController@resetPassword` | `web, ApplyAdminNoindexHeaders, THROTTLE:password-reset-attempt` | None (middleware only) |
| GET\|HEAD | `admin/reset-password/{token}` | `admin.password.reset` | `Admin\PasswordResetController@showResetForm` | `web, ApplyAdminNoindexHeaders` | None (middleware only) |
| GET\|HEAD | `admin/resource-categories` | `admin.resource-categories.index` | `Admin\ResourceCategoryController@index` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/resource-categories` | `admin.resource-categories.store` | `Admin\ResourceCategoryController@store` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/resource-categories/create` | `admin.resource-categories.create` | `Admin\ResourceCategoryController@create` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| PUT\|PATCH | `admin/resource-categories/{resource_category}` | `admin.resource-categories.update` | `Admin\ResourceCategoryController@update` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| DELETE | `admin/resource-categories/{resource_category}` | `admin.resource-categories.destroy` | `Admin\ResourceCategoryController@destroy` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/resource-categories/{resource_category}/edit` | `admin.resource-categories.edit` | `Admin\ResourceCategoryController@edit` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/resources` | `admin.resources.index` | `Admin\ResourceController@index` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/resources` | `admin.resources.store` | `Admin\ResourceController@store` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/resources/create` | `admin.resources.create` | `Admin\ResourceController@create` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| PUT | `admin/resources/{resource}` | `admin.resources.update` | `Admin\ResourceController@update` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| DELETE | `admin/resources/{resource}` | `admin.resources.destroy` | `Admin\ResourceController@destroy` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| DELETE | `admin/resources/{resource}/draft` | `admin.resources.draft.destroy` | `Admin\ResourceController@discardDraft` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/resources/{resource}/edit` | `admin.resources.edit` | `Admin\ResourceController@edit` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/search` | `admin.search` | `Admin\SearchController@search` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/settings` | `admin.settings.index` | `Admin\SettingController@index` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| POST | `admin/settings` | `admin.settings.update` | `Admin\SettingController@update` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | Maintenance field requires super_admin (`SettingController::update`) |
| POST | `admin/settings/telegram/test` | `admin.settings.telegram.test` | `Admin\SettingController@testTelegram` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | Admin setting validation (`SettingController`) |
| GET\|HEAD | `admin/students` | `admin.students.index` | `Admin\StudentController@index` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin,assistant` | None (middleware only) |
| PATCH | `admin/students/{student}` | `admin.students.update` | `Admin\StudentController@update` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | None (middleware only) |
| GET\|HEAD | `admin/students/{student}` | `admin.students.show` | `Admin\StudentController@show` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin,assistant` | None (middleware only) |
| POST | `admin/students/{student}/anonymize` | `admin.students.anonymize` | `Admin\StudentController@anonymize` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin` | None (middleware only) |
| POST | `admin/students/{student}/merge` | `admin.students.merge` | `Admin\StudentController@merge` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin` | None (middleware only) |
| POST | `admin/students/{student}/packages` | `admin.students.packages.store` | `Admin\StudentBillingController@storePackage` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | Student/package/payment validation (`StudentBillingController`, `StudentLedgerService`) |
| POST | `admin/students/{student}/packages/{package}/credits` | `admin.students.credits.adjust` | `Admin\StudentBillingController@adjustCredits` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | Student/package/payment validation (`StudentBillingController`, `StudentLedgerService`) |
| POST | `admin/students/{student}/packages/{package}/payments` | `admin.students.payments.store` | `Admin\StudentBillingController@storePayment` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | Student/package/payment validation (`StudentBillingController`, `StudentLedgerService`) |
| POST | `admin/students/{student}/payments/{payment}/refunds` | `admin.students.refunds.store` | `Admin\StudentBillingController@storeRefund` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | Student/package/payment validation (`StudentBillingController`, `StudentLedgerService`) |
| POST | `admin/translations/{entityType}/{id}/{locale}/draft` | `admin.translations.save-draft` | `Admin\TranslationController@saveDraft` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | Admin preview/editor validation (`TranslationController`) |
| POST | `admin/translations/{entityType}/{id}/{locale}/publish` | `admin.translations.publish` | `Admin\TranslationController@publish` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | Admin preview/editor validation (`TranslationController`) |
| GET\|HEAD | `admin/translations/{entityType}/{id}/{locale}/reconcile` | `admin.translations.reconcile` | `Admin\TranslationController@reconcile` | `web, ApplyAdminNoindexHeaders, auth:web, EnsureAdminRole:super_admin,admin` | Admin preview/editor validation (`TranslationController`) |

## 9. Settings & Configuration Keys [VERIFIED: CODE]

### Admin-facing business knobs

Keys are stored in settings.key. SettingController::update validates writes; DatabaseSeeder supplies bootstrap defaults. Homepage/about drafts use the draft: prefix, while published content reads the live key. Maintenance is writable only by super_admin. [app/Http/Controllers/Admin/SettingController.php; database/seeders/DatabaseSeeder.php]

| Key or family | Purpose, accepted values, default/fallback |
| --- | --- |
| site_name, default_language, site_footer_text | Branding/footer. Default language form accepts en/ar, seeded en. **Contradiction:** SetRequestLocale and translated public routes use en/fr/de, not ar; this setting is not evidence of an Arabic public route. [SettingController::update; app/Http/Middleware/SetRequestLocale.php; routes/web.php] |
| business_timezone | Validated timezone input, seeded Africa/Cairo; booking UTC storage is separate. [SettingController::update; DatabaseSeeder] |
| maintenance_mode | Boolean seeded off; only super-admin may change; middleware caches it and bypasses admin/auth/health. [SettingController::update; EnsureNotUnderMaintenance] |
| cancellation_policy, rescheduling_policy, booking_instructions, video_meeting_url | Public policy/instructions and optional HTTPS meeting URL. [SettingController::update; DatabaseSeeder] |
| booking_cancellation_cutoff_hours, booking_reschedule_cutoff_hours | Integer 0–168; seeded 4 and 24 respectively; lifecycle services enforce. [SettingController::update; CancellationService; RescheduleService] |
| hero_title, hero_subtitle, home_*, about_* | Draft/published homepage/about copy; seeded hero text, most other values nullable. [SettingController::update; DatabaseSeeder] |
| active_visitor_window, session_timeout_minutes, analytics_retention_days, analytics_authoritative_cutover_date | Seeded 5 minutes, 30 minutes, 180 days, and current Cairo date at seed time. [DatabaseSeeder; TrackVisitorSession; AggregateDailyAnalyticsCommand] |
| backup_retention_days | Seeded 30 days; backup retention. [DatabaseSeeder; BackupService] |
| counters.learning_hours.*, counters.monthly_traffic.*, counters.live_users.* | Social-proof toggles and wording; hours window 1–90 days, traffic source unique_visitors or sessions; all seeded disabled. [SettingController::update; DatabaseSeeder; EngagementCounterService] |
| telegram.reminders_enabled, telegram.bot_token, telegram.reminder_windows, telegram.notification_chat_ids | Reminder switch, encrypted bot token, unique minute offsets/chat IDs; seeded windows [1440,60], no recipients. [SettingController::update; TelegramNotificationService] |
| last_scheduler_run_at, last_holds_cleanup_at, last_analytics_aggregation_at and status keys | Operational heartbeat/status written by scheduled callbacks, not normal settings form. [routes/console.php; SystemHealthController] |

### Developer/environment knobs

These are configuration templates/bindings, **not** evidence that production credentials or services work. [UNVERIFIED: ENVIRONMENT]

| Key | Purpose / default / unset behavior |
| --- | --- |
| BUSINESS_TIMEZONE | Example Africa/Cairo in .env.example; the database setting is the runtime business knob. [.env.example; SettingController] |
| DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD | MariaDB connection; example 127.0.0.1:3307/bolt_landing. Local connection is verified in Section 5. Do not use example root/blank password in production. [.env.example; config/database.php] |
| DB_RECONCILED_VENDOR, DB_RECONCILED_VERSION | Deployment capability assertion; empty in example, requiring deployment reconciliation. [.env.example; DatabaseCapability] |
| STUDENT_AUTH_HMAC_KEY | Optional dedicated student-identity HMAC material. [config/services.php; StudentIdentityService] |
| GEOIP_DATABASE_PATH, GEOIP_DOWNLOAD_URL, MAXMIND_LICENSE_KEY, GEOIP_TRUSTED_PROXIES | GeoLite local database/update and trusted proxy country headers; missing lookup falls back without blocking a request. [config/services.php; GeoIpService; GeoIpUpdateCommand] |
| BACKUP_OFFSITE_DISK, AWS_* | Optional offsite archive replication. Unset/failed replication does not prevent a local archive. [config/filesystems.php; BackupService] |
| BACKUP_RETENTION_DAYS, RATE_LIMIT_ENABLED, ANALYTICS_AUTHORITATIVE_CUTOVER_DATE | Documented example knobs for retention, limiter switch and analytics provenance; inspect consumers before changing. [.env.example; AppServiceProvider; AggregateDailyAnalyticsCommand] |
| MAIL_*, QUEUE_CONNECTION, SESSION_DRIVER, CACHE_STORE | Infrastructure transports; example SMTP and database queue/session/cache. Actual local queue default is database; delivery remains environment-dependent. [.env.example; config/mail.php; config/queue.php] |

## 10. Scheduled Jobs & Background Processes [VERIFIED: CODE / RUNTIME]

php artisan schedule:list returned six entries matching routes/console.php. Host cron/process liveness is [UNVERIFIED: ENVIRONMENT]; registration is not execution proof.

| Schedule | Action/dependency | Silent-stop effect / visibility |
| --- | --- | --- |
| Every minute | scheduler-heartbeat writes last_scheduler_run_at. | Health page detects stale timestamp. [routes/console.php; SystemHealthController] |
| Every 5 minutes | booking:cleanup-holds expires/removes old holds; status/warning callbacks. | Expired rows accumulate; availability ignores expired timestamps. [CleanupExpiredHoldsCommand; routes/console.php] |
| Daily 00:05 | analytics:aggregate-daily --prune creates rollups and prunes eligible whole Cairo days. | Rollups/retention become stale; status and warning callback. [AggregateDailyAnalyticsCommand; routes/console.php] |
| Daily 02:00 | backup:run --clean archives SQL and storage, rotates old files. | Recovery point ages; backup health view and failure notification. [RunBackupCommand; BackupService; routes/console.php] |
| Daily 03:00 | auth:clear-resets and expired database-session deletion, conditional on session driver. | Expired token/session rows accumulate. [routes/console.php] |
| Every 5 minutes, overlap guard 10m | booking:send-telegram-reminders checks due confirmed lessons at configured windows. | Reminders are missed, not queued for catch-up. [SendBookingRemindersCommand; TelegramNotificationService; routes/console.php] |

Manual, unscheduled commands include geoip:update, db:verify-capability, migrate:students-backfill, backup:restore, analytics country aggregation/backfill, diagnostic session sync and admin:create. [app/Console/Commands; routes/console.php]

## 11. Asynchronous Processing & Events [VERIFIED: CODE / RUNTIME]

php artisan config:show queue.default returned database; .env.example also specifies QUEUE_CONNECTION=database. Queue tables exist (Section 5). The application file inventory and searches for ShouldQueue/dispatch found no domain job classes or named application queues; vendor behavior is not included in that claim. [config/queue.php; app/Providers/AppServiceProvider.php; rg --files app; rg -n 'ShouldQueue|dispatch\(' app]

| Trigger | Inline/deferred behavior |
| --- | --- |
| Booking/hold | Calendar locks, booking/hold writes, events and ledger operations run synchronously. DB::afterCommit analytics runs after commit in the request process, not a queue. [BookingService::createPublicBooking; BookingHoldService::acquireHold; StudentBookingService::create] |
| Admin payment/refund | Cashier/reconciliation services mutate payment, package and ledger state in HTTP request transactions; no gateway webhook is registered. [StudentBillingController; StudentLedgerService; routes/web.php] |
| Telegram reminder | Scheduled command invokes TelegramNotificationService inline; outbound HTTP timeout is five seconds. [SendBookingRemindersCommand; TelegramNotificationService] |
| Password reset | Administrator invokes a Laravel mail notification; no verified ShouldQueue implementation in AdminResetPasswordNotification. [Administrator::sendPasswordResetNotification; AdminResetPasswordNotification] |
| Queue worker lifecycle | Queue::looping/before update heartbeat cache; Queue::failing creates admin warning. These are framework queue events, not domain-listener registration. [AppServiceProvider::boot] |

No application app/Jobs directory or dedicated domain event/listener map appeared in the inspected file inventory. Booking history uses a booking_events table, not Laravel's event dispatcher. [rg --files app; app/Domains/Booking/Models/BookingEvent.php]

## 12. Third-Party Integrations & External I/O [VERIFIED: CODE]

| Integration | Configuration / call site | Failure or fallback |
| --- | --- | --- |
| MariaDB/InnoDB | config/database.php, DatabaseCapability; local vendor 10.11.18 on mysql driver. Production vendor/version [UNVERIFIED: ENVIRONMENT]. | Unsupported vendor throws UnsupportedDatabaseVendorException; booking isolation/locks require InnoDB. |
| SMTP/Laravel mail | config/mail.php and AdminResetPasswordNotification. MAIL_* example values are placeholders [UNVERIFIED: ENVIRONMENT]. No application Mail::/Notification:: booking-confirmation sender appeared in the inspected app inventory. | Delivery failure is transport-dependent; code inspection cannot prove receipt. |
| Telegram Bot API | Encrypted settings token; TelegramNotificationService sends via HTTP with five-second timeout. Live token/recipient acceptance [UNVERIFIED: ENVIRONMENT]. | Service returns failure/logs; scheduler does not queue retry. |
| MaxMind GeoLite2 | geoip2 package, MMDB path/update/license and trusted proxies in config/services.php; GeoIpService. External download/license [UNVERIFIED: ENVIRONMENT]. | Missing/unreadable lookup yields no detected country, not a request failure. |
| S3-compatible offsite backups | config/filesystems.php BACKUP_OFFSITE_DISK/AWS_*; BackupService::createBackup. Remote bucket [UNVERIFIED: ENVIRONMENT]. | Local archive may succeed when offsite upload fails; inspect offsite status. |
| External resource/game/social links | ResourceController and game/social target URLs redirect to configured external destinations. | Destination availability/content [UNVERIFIED: ENVIRONMENT]. |
| HTMLPurifier/libphonenumber | Local Composer libraries in RichTextSanitizer and phone normalization. [composer.json] | In-process sanitization/validation; no network dependency. |

No inbound payment webhook route or signature verifier appears in routes/web.php or the 217-route runtime inventory. Payments are manually recorded in StudentBillingController; external gateway reconciliation is [NOT BUILT]. [VERIFIED: CODE / RUNTIME]

## 13. Error Handling & Exception Boundaries [VERIFIED: CODE]

Custom exception inventory from app/Domains/*/Exceptions:

| Exception | State / source | Caught and user-facing result |
| --- | --- | --- |
| SlotUnavailableException | Authoritative slot conflict or hold failure in booking/availability services. | BookingWizard catches it into errorMessage; Student/BookingController returns a slot_id validation error; Student/RescheduleController returns with form error. Admin/BookingController catches broader Exception in several paths. [BookingWizard::selectSlot/confirmBooking; Student/BookingController::store; Student/RescheduleController::update; Admin/BookingController] |
| DstGapException, DstFoldAmbiguityException | Invalid/non-unique local wall time; both extend SlotUnavailableException. | Follow the slot-error boundary where booking services surface them; not globally assigned a unique HTTP response. [TimezoneService; app/Domains/Timezone/Exceptions] |
| InvalidTimezoneException | Invalid IANA identifier; extends InvalidArgumentException. | Controllers validate or reject timezone input; student display path falls back to preferred/business/Cairo. [TimezoneService; Student/BookingController::displayTimezone] |
| BookingPolicyViolationException | Lifecycle cutoff or policy breach; DomainException. | Student/RescheduleController catches to form error; other admin/public paths may handle broadly or bubble to framework handler. [RescheduleService; Student/RescheduleController] |
| InvalidBookingStatusTransitionException | Disallowed booking state change; DomainException. | Student/RescheduleController catches to form error; other controllers differ. [RescheduleService; CancellationService; Student/RescheduleController] |
| UnsupportedDatabaseVendorException | Database capability mismatch. | Not presented as a recoverable booking-form error; uncaught path reaches Laravel error handler. [DatabaseCapability; bootstrap/app.php] |

There is no single global mapper for these domain exceptions in bootstrap/app.php. Standard ValidationException renders form errors/redirects for HTML; abort(403/404/422) uses Laravel's HTTP exception renderer. Requests matching api/* or expecting JSON receive JSON; otherwise unexpected uncaught exceptions render Laravel's HTML error page (debug detail depends on APP_DEBUG). A custom maintenance 503 view exists under resources/views/errors/maintenance.blade.php and resources/views/errors/503.blade.php. [bootstrap/app.php; resources/views/errors; Admin/AuthController; StudentBillingController]

## 14. Testing Conventions & Developer Tooling [VERIFIED: CODE]

PHPUnit suites are defined in phpunit.xml: tests/Unit and tests/Feature. Tests/TestCase.php enforces the required MariaDB capability before domain tests. phpunit.xml points tests at the separate bolt_landing_test MariaDB database on port 3307, with array session/cache and sync queue; this is a test configuration, not proof a command has been run in this documentation task. [phpunit.xml; tests/TestCase.php]

Application-specific feature tests cover booking, DST, holds, credits, auth, CMS, media, forms, analytics, reports, billing, backups, notifications and localization (file inventory: rg --files tests/Feature). MariaDbConcurrencyVerificationTest and BufferExpandedConcurrencyLockTest spawn independent processes/PDO connections for genuine races. They intentionally do not rely solely on a test-managed transaction; their tearDown kills workers, purges secondary connections and manually removes scoped fixtures, including foreign-key-check manipulation in BufferExpandedConcurrencyLockTest. **Do not run these tests against the application database.** [tests/Feature/MariaDbConcurrencyVerificationTest.php; tests/Feature/BufferExpandedConcurrencyLockTest.php; tests/Feature/Concurrency/booking_worker.php]

No PHPUnit test run is claimed here: the attached documentation request restricted database writes, and running the suite would mutate the isolated test database. Verification in this document is code inspection plus read-only Artisan/database metadata commands. [phpunit.xml; Sys Ref V2.md]

Seeder inventory: DatabaseSeeder creates defaults and calls PolicyPagesSeeder and BlogSeeder, while SyncDiagnosticSessionTypeSeeder exists for diagnostic synchronization. For a fresh local development database the documented command is php artisan migrate --seed; rerunning db:seed calls DatabaseSeeder, which uses updateOrCreate for many defaults and creates an initial development administrator only when none exists (read its environment guard before use). The safer explicit administrator command is php artisan admin:create. Do not seed an existing production database casually because updateOrCreate changes seeded content. [database/seeders/DatabaseSeeder.php; database/seeders/PolicyPagesSeeder.php; database/seeders/BlogSeeder.php; database/seeders/SyncDiagnosticSessionTypeSeeder.php; README.md §§4–6]

Developer checks named in the repository include php artisan test, php artisan migrate:status, php artisan schedule:list, npm run build and vendor/bin/pint --dirty --format agent. These are commands, not a claim that this document ran the write-producing checks. [README.md; AGENTS.md]

## 15. Local Development Bootstrap [VERIFIED: CODE]

The documented local server is Laravel Herd on Windows, with a project directory under a Herd-linked path; no Docker/Sail compose configuration appeared in the inspected root/file inventory. AGENTS.md says Herd serves the site automatically. Runtime PHP 8.4.25 was confirmed via the Herd php84 executable; system PATH php points to incompatible XAMPP PHP 8.2.12, so use Herd PHP 8.4 or align PATH before Composer/Artisan. The installed package constraints are PHP ^8.4.1 and Laravel ^13.17. [README.md §4; AGENTS.md; composer.json; command: C:/Users/Ateff/.config/herd/bin/php84/php.exe --version; command: php --version]

Fresh-clone procedure documented by README.md §4 (these are **setup instructions, not commands executed here**): clone into Herd location; copy .env.example to .env; set an environment-specific APP_URL and MariaDB credentials; composer install; npm install; php artisan key:generate; create local bolt_landing and bolt_landing_test schemas; php artisan migrate --seed; npm run build; provision an administrator with php artisan admin:create and log in through the admin login route. The example MariaDB endpoint is 127.0.0.1:3307 and the local runtime schema was read successfully at that endpoint. [README.md §§4–6; .env.example; config/database.php; Section 5 runtime inspection]

Herd handles HTTP serving, so do not launch php artisan serve merely to use the project locally. Scheduler execution and a queue worker are separate processes in production; README.md §§7–8 describes cron every minute and php artisan queue:work. Local mail delivery requires a configured mail transport; .env.example SMTP values are placeholders. Public storage URL requires the Laravel public-storage link documented by README/deployment guidance. Hostinger production web-root/subpath, cron, workers, TLS, SMTP and offsite S3 credentials remain [UNVERIFIED: ENVIRONMENT]; a local file cannot prove them. [AGENTS.md; README.md; .env.example; config/filesystems.php]

## 16. Analytics & Reporting Capabilities [VERIFIED: CODE]

This is first-party server-side analytics, not client snippets alone. TrackVisitorSession creates/refreshes visitor and session identities and stores acquisition fields; AnalyticsController accepts only AnalyticsService's event allow-list and validates metadata/session binding; resources/js/analytics-telemetry.js emits client interactions. Raw entities are visitors, visitor_sessions, analytics_events, marketing_touches, visitor_funnel_progressions and reconciliation audits; durable aggregates are daily_metrics and daily_country_metrics. [app/Http/Middleware/TrackVisitorSession.php; app/Http/Controllers/AnalyticsController.php; app/Domains/Analytics/Services/AnalyticsService.php; resources/js/analytics-telemetry.js; Section 5]

| Metric | Exact implemented definition / boundary |
| --- | --- |
| Daily unique visitors | COUNT(DISTINCT non-bot analytics_events.visitor_token) inside half-open Cairo day converted to UTC. [AggregateDailyAnalyticsCommand::handle] |
| Daily sessions | Count non-bot VisitorSession rows whose started_at is in that same half-open day and whose visitor is non-bot. [AggregateDailyAnalyticsCommand::handle] |
| Daily bookings created | Count bookings by created_at in the Cairo day, regardless of later cancellation, excluding linked bot visitors. [AggregateDailyAnalyticsCommand::handle] |
| Bounced sessions | Session duration less than 10 seconds, exactly one page_view and no booking conversion action, attributed by session start day. [AggregateDailyAnalyticsCommand::handle] |
| Resource funnel | request_rate = 100 × resource_requested / resource_gate_viewed; download_rate = 100 × resource_downloaded / resource_requested; zero denominator yields 0. [AnalyticsService::getResourceFunnel] |
| Game completion | completion_rate = 100 × game_completed / game_started; zero denominator yields 0. [AnalyticsService::getGameFunnel] |
| Booking funnel | Cohort visitors first seen in [Cairo start, next Cairo day). CTA rate = 100 × CTA-qualified / visitors; start rate = 100 × started / CTA-qualified; hold rate = 100 × held / started; complete rate = 100 × completed / held; overall conversion = 100 × completed / visitors. Qualification includes observed/imputed milestones, capped for monotonicity and 30-day maturity. [FunnelProgressionService::getCohortFunnel] |
| Traffic report | Per-day event distinct visitors, started sessions and page views; raw data reconciled against durable rollups for missing/partly pruned dates. With full raw data, period visitors = exact distinct token count; with pruned multi-day history, visitors = sum of daily uniques. Growth percentage is suppressed when current/previous counting bases differ; otherwise 100 × (current−previous)/previous, with 0 when previous is 0. [ReportService::getTrafficReport; ReportService::resolvePeriodTrafficMetrics] |
| Resource report | Per-resource request/download counts in selected date range; conversion = 100 × downloads / requests, 0 if none. [ReportService::getResourcesReport] |
| Campaign downstream rate | Qualifying source/campaign/content visitor cohort; confirmed downstream bookings within touch attribution window ÷ visitor count × 100; no visitors means 0. Unlinked and period-created bookings are separate fields, not silently included as cohort conversions. [ReportService::getCampaignContentReport] |
| Acquisition performance | Groups non-bot raw events by COALESCE source/medium/campaign/content; distinct visitor_token visitors, page_view count, booking_completed event count and resource_requested lead count. [AnalyticsService::getAcquisitionPerformance] |
| Country metrics | DailyCountryMetric tracks per-country uniques, sessions, CTA clicks, completed bookings, resource requests and page views; missing country normalizes to fallback country code. [AggregateDailyCountryMetricsCommand] |

Admin routes for analytics dashboards, country/section reporting and reports/exports are in Section 8. ExportService produces CSV/XLSX and neutralizes spreadsheet formulas before output; XLSX temporary output uses delete-after-send. ReportController bounds custom ranges; a build of the public dashboard is not a proof of live telemetry ingestion. [app/Http/Controllers/Admin/AnalyticsDashboardController.php; app/Http/Controllers/Admin/ReportController.php; app/Domains/Reporting/Services/ExportService.php]

## 17. Known Gaps, Limitations & Unverified Items

| Classification | Evidence and consequence |
| --- | --- |
| [VERIFIED: CODE] Contradictory locale controls | SettingController::update accepts default_language=en/ar, but SetRequestLocale and routes/web.php recognize en/fr/de, public layout fixes dir=ltr, and lang/ contains only en.json/fr.json/de.json. Arabic text fragments use local bdi dir=rtl. A future edit must not assume complete Arabic UI/RTL merely from the setting. [SettingController; SetRequestLocale; resources/views/layouts/public.blade.php; rg --files lang] |
| [VERIFIED: CODE] Manual payments, not checkout | StudentBillingController and StudentLedgerService record cash/manual payments/refunds; route inventory contains no payment webhook/gateway endpoint. Automatic online charging and webhook reconciliation are [NOT BUILT]. [routes/web.php; StudentBillingController] |
| [VERIFIED: CODE] Assistant booking write gap | POST admin.bookings.reschedule is inside auth:web but omits role:super_admin,admin, and Admin/BookingController::reschedule has no actor-role check. An authenticated assistant may submit a valid date/time to change an appointment. AdministratorAuthorizationTest supplies invalid input and expects validation errors, so it does not prove denial. [routes/web.php:213; Admin/BookingController::reschedule; tests/Feature/AdministratorAuthorizationTest.php] |
| [UNVERIFIED: ENVIRONMENT] Production operations | Local registration does not prove Hostinger scheduler, queue worker, mail delivery, Telegram token, GeoIP refresh, offsite backup bucket/restore drill, TLS, permissions or asset availability. Verify directly on host. [routes/console.php; .env.example; BackupService; README.md] |
| [UNVERIFIED: ENVIRONMENT] Frontend package runtime | package.json/lockfile establish intended Node/Vite/Tailwind dependencies, but Node executable was inaccessible in this sandboxed documentation session. No build result is claimed. [package.json; command: node --version] |
| [VERIFIED: CODE] README drift | README.md's schedule list names five jobs, while routes/console.php and php artisan schedule:list show six including Telegram reminders. Treat executable registration as authoritative. [README.md §7; routes/console.php] |
| [VERIFIED: CODE] Documentation-scope limit | Runtime schema and route registration were checked locally; this reference did not execute write-producing PHPUnit/build/migrations or observe full browser journeys. It should guide—not replace—release verification. [Section 5 commands; Section 14] |

## 18. Glossary [VERIFIED: CODE]

| Term | Project-specific meaning / source |
| --- | --- |
| Business timezone | Tutor schedule reference, seeded Africa/Cairo; not the storage timezone. [DatabaseSeeder; TimezoneService] |
| UTC booking interval | Canonical start_at_utc/end_at_utc timestamps persisted on Booking. [Booking model; BookingService] |
| Calendar-date lock | Existing/inserted date row locked FOR UPDATE to serialize overlaps, including otherwise empty slots. [AvailabilityService::acquireCalendarDateLocksForIntervals] |
| Hold | Short-lived reservation of an available slot tied to a visitor/session/hold token, default ten minutes. [BookingHoldService] |
| Slot ID | Signed identity of offered interval and owner used by student booking/rescheduling; backend revalidates availability. [SlotResolver; StudentBookingService] |
| Contact | Canonical lead/person identity, distinct from a verified Student account. [ContactService; StudentIdentityService] |
| Student package | Dated purchase/credit allocation. [StudentPackage; StudentLedgerService] |
| Ledger entry | Signed credit movement; current balance is derived by summation rather than trusted as a mutable counter. [SessionLedgerEntry; StudentLedgerService] |
| Visitor/session token | First-party analytics identity from cookies/session; not equivalent to an admin or student authentication credential. [TrackVisitorSession] |
| Cohort | Non-bot visitor set qualified by first-visit time inside Cairo day boundaries. [FunnelProgressionService::getCohortFunnel] |
| Observed/imputed milestone | Funnel stage actually seen in raw telemetry or reconstructed from authoritative progression/booking data. [FunnelProgressionService] |
| Daily-unique sum | Historical multi-day visitor estimate when raw cross-day token identity was pruned; not exact period distinct visitors. [ReportService::resolvePeriodTrafficMetrics] |
| Draft revision | Unpublished CMS/translation version; public readers use published source/translation. [ContentService; TranslationService; ContentRevision] |
| Gated resource | Resource requiring request/grant and private token-bound download. [ResourceController; ResourceRequest; ResourceDownload] |
| Maintenance visit | Traffic recorded while public site returns maintenance response. [EnsureNotUnderMaintenance; AnalyticsService::trackMaintenanceVisit] |
| Offsite backup | Optional second copy of a local SQL/storage ZIP on configured backup disk; local success does not certify remote copy. [BackupService] |

## 19. Change Log Since Last Baseline [VERIFIED: RUNTIME]

**Baseline:** v1.0 initial system reference. No prior SYSTEM_REFERENCE.md or ARCHITECTURE_AUDIT.md existed at the repository root before this document was created (initial root inventory). **Git HEAD:** 59ab16ac35332c2eb754d1db0c0f40f60de2e44c; current branch main. [commands: git status --short before creation; git branch --show-current; git rev-parse HEAD]

Because this is the base branch with no prior architecture baseline, the comparison source is git log -n 15 --stat, not a fabricated before/after diff:

| Recent commit(s) | Components changed |
| --- | --- |
| 59ab16a, 739508e | Admin system-health grid/clock and sidebar/topbar layout. [git log -n 15 --stat] |
| 2cbb34c | V4 batches: promotions, social-proof counters, cashier reconciliation, Telegram reminders, maintenance capture, analytics rollups, resource external links, Blog terminology/model migration, relevant schema/test additions. This records what Git says changed, not a claim that every production deployment succeeded. [git log -n 15 --stat; cited domain files in Sections 6–16] |
| 8a5463b, 958fcb1, 3c3d657 | Timezone flag SVG/license, booking display sync, admin content/settings and sidebar width. [git log -n 15 --stat] |
| 5bf40af, d943deb, 1b8b869 | Documentation/status updates for production assets, offsite backup gate and Hostinger operations. These are documentation claims, not runtime evidence. [git log -n 15 --stat] |
| 7123f15, 4c019ed, 344f082 | V3 student portal/forms/billing, backfill, MariaDB capability/foreign-key work and production-readiness changes. [git log -n 15 --stat] |
| 8f8dd2a, e607d25, f6a9ea3 | Admin mobile drawer behavior. [git log -n 15 --stat] |

Future revisions should record their starting and ending Git commits and compare directly against this v1.0 file. [VERIFIED: CODE]
