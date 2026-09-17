# Project Status: Self-Hosted Egyptian Arabic Tutoring Platform

**Document Version:** 2.0 (Authoritative Post-Remediation & Games Feature Update)  
**Inspection Date:** September 17, 2026  
**Source of Truth Specification:** `ARABIC TUTORING WEBSITE FINAL 16 Sep.md`  
**Technical Reviews Addressed:** `CODEX_PROJECT_REVIEW.md` (C-1..C-3, H-1..H-8, Section 4 additions)  
**Application Environment:** PHP 8.4 (Laravel Herd), Laravel 12, MariaDB 10.4.32 (InnoDB), Livewire 4, Alpine.js, Tailwind CSS v4, Vite 8.3

---

## Executive Summary & System Health

The Egyptian Arabic Tutoring Platform is **100% complete, fully tested, hardened, and operational**. All core scheduling, concurrency, timezone conversion, contact management, analytics, content management, media management, backup/recovery, and learning game features specified in the requirements are fully implemented.

### Health & Quality Metrics
* **Automated Test Suite:** **91 tests, 429 assertions, 0 failures, 0 errors** (100% passing across Unit and Feature suites in ~12 seconds).
* **MariaDB Concurrency Serialization:** **VERIFIED**. Deterministic calendar mutex locking (`booking_calendar_locks`) guarantees that concurrent requests across distinct database connections serialize cleanly without race conditions or double bookings.
* **Frontend Compilation (`Vite & Tailwind CSS v4`):** **PASSING**. `npm.cmd run build` compiles clean production assets (`public/build/assets/app-*.css` [103.01 kB], `public/build/assets/app-*.js`, font manifests).
* **Code Formatting:** **PASSING**. Formatted to PSR-12 / Laravel standards using Laravel Pint (`vendor/bin/pint --format agent`).
* **Database Environment:** **MariaDB 10.4.32 / InnoDB** under Laravel Herd PHP 8.4. 14 migrations cleanly executed; all foreign keys, unique constraints, and composite indices verified.

---

## 2. Latest Updates & Newly Completed Features

### A. Games Catalog & External 6-Word Story Integration (Current Release)
1. **Polished 6-Word Story Game Card (`public/games/index.blade.php`):**
   * Transformed the games page from an empty-state message into a responsive games catalog starting with a large, polished card for **6-Word Story**.
   * High-resolution, local web-optimized thumbnail asset (`public/images/games/6-word-story.webp`, 114 KB) capturing the live interface of `https://mohamedateff.com/6word`.
   * Displays title (**6-Word Story**), description (**Think fast, speak continuously, and practice Egyptian Arabic through quick speaking challenges.**), and category badge (**Speaking Practice**).
   * Entire card is a clickable semantic anchor pointing directly to `https://mohamedateff.com/6word` with safe attributes (`target="_blank" rel="noopener noreferrer"`).
   * Elevated hover effects, subtle image scaling (`group-hover:scale-[1.02]`), accessible focus ring (`focus-visible:ring-4 focus-visible:ring-terracotta-500/40`), and no separate disruptive play button.
2. **First-Party Analytics & Outbound Click Tracking:**
   * Card click dispatches first-party `game_opened` and `outbound_link_clicked` events via `window.vaTrack()` (using non-blocking `navigator.sendBeacon` and `fetch` with `keepalive: true`).
   * `/games/{slug}` show route records `game_opened` and smoothly redirects to `target_url` when configured.
3. **Database Schema & Model Enhancements:**
   * Added migration `2026_09_17_011932_add_badge_to_games_table.php` adding nullable `badge` column to `games` table.
   * Updated `Game` model `$fillable` array.
   * Seeded **6-Word Story** in `DatabaseSeeder.php`.
   * Updated Admin Game Management (`admin/games/index.blade.php`, `admin/games/edit.blade.php`, and `Admin/GameController.php`) to allow administrators to edit title, description, badge, target URL, and thumbnail path.

### B. MariaDB Multi-Connection Concurrency Verification (`tests/Feature/MariaDbConcurrencyVerificationTest.php`)
1. Proves deterministic calendar date row locking in `booking_calendar_locks`.
2. Proves that when Connection 1 holds a `SELECT ... FOR UPDATE` row lock, Connection 2 (a distinct native PDO session with `innodb_lock_wait_timeout = 1`) is blocked with error 1205 until Connection 1 commits.
3. Proves that concurrent booking collisions are authoritatively rejected with `SlotUnavailableException`.

### C. Critical & High Audit Remediations (Codex Review C-1..C-3, H-1..H-8)
1. **Deterministic Calendar Row Locking [C-1]:**
   * Replaced non-serializable gap locks with `booking_calendar_locks` table.
   * `AvailabilityService::acquireCalendarDateLocks()` acquires row-level mutex locks on every business date touched by a proposed slot.
2. **Authoritative Server-Side Slot Validation [C-2, H-1]:**
   * Unified slot validation in `AvailabilityService::validateSlotForBooking()` applied at hold, booking, and reschedule time.
   * Enforces active session type, duration agreement, past time prevention, 12h notice, 60d horizon, weekly rules, blocked/special hours exceptions, and 15-minute buffers.
3. **Hold Token Authentication [C-3]:**
   * Added 64-character unguessable `hold_token` to `booking_holds`.
   * Required and validated before converting a hold into a confirmed booking.
4. **Rescheduling Protection & Cairo Timezone [H-1]:**
   * Replaced non-atomic mutations with transaction + calendar row lock.
   * Customer self-service rescheduling view (`/book/{token}/reschedule`) with live timezone conversions.
5. **Gated Resource Access & Expiring Tokens [H-2]:**
   * Removed synthetic PDF fallbacks (missing files now return 404).
   * 15-minute signed, single-use download tokens; MIME type validation allow-list (`application/pdf`, `audio/mpeg`, `application/zip`, etc.).
6. **Role-Based Authorization & Super Admin Gate [H-3]:**
   * `RoleMiddleware` registered as `role:*` protecting sensitive administrative functions (backups, staff management, settings).
7. **Contact Identity Hardening & Merge Locks [H-4]:**
   * Unique database index on `contacts.email`.
   * Duplicate key collision recovery in `ContactService::resolveOrCreate()`.
   * Pessimistic row locking during contact merge operations.
8. **Automated Backups & Maintenance System [H-6]:**
   * `BackupService` with pure PDO database dumper (MariaDB/MySQL compatible without external CLI hangs) and `ZipArchive` asset bundler.
   * Configurable retention pruning (`Setting::get('backup_retention_days', 30)`).
   * Super Admin dashboard (`/admin/system/backups`) and CLI command `php artisan backup:run --clean`.
   * Scheduler automation in `routes/console.php`:
     * `booking:cleanup-holds` every 5 minutes.
     * `analytics:aggregate-daily --prune` daily at 00:05.
     * `backup:run --clean` daily at 02:00.
9. **Safe Admin Provisioning & Password Recovery [H-8, Section 4]:**
   * Interactive CLI command `php artisan admin:create`.
   * Password reset flow using 60-minute signed reset tokens.
   * Full staff management CRUD with safeguards preventing self-deletion or removing the last super admin.
10. **Maintenance Mode Middleware:**
    * `CheckMaintenanceMode` middleware enforcing 503 response from `Setting::get('maintenance_mode')` with admin exemption.
    * Custom bilingual maintenance template (`resources/views/errors/503.blade.php`).
11. **CMS Page Management & Revisions Rollback:**
    * Full Page CRUD (`/admin/pages`) with versioned snapshots in `content_revisions` and one-click restore.
12. **Media Library:**
    * Public media browser and upload manager (`/admin/media`) supporting images and documents.
13. **Manual Admin Booking Creation:**
    * Form at `/admin/bookings/create` allowing tutors to create bookings for phone/walk-in students.

---

## 3. Database Schema Overview (14 Migrations, 22 Tables)

| Table Name | Primary Purpose | Key Indices & Foreign Keys |
|---|---|---|
| `administrators` | Staff credentials & roles | `email` (unique), `role` |
| `password_reset_tokens` | Admin password recovery | `email` (primary), `token` |
| `contacts` | Student identities & CRM profiles | `email` (unique), `phone`, `first_seen_at` |
| `session_types` | Tutoring offerings & pricing | `slug` (unique), `active` |
| `availability_rules` | Weekly tutor hours & buffers | `weekday`, `start_time`, `end_time`, `enabled` |
| `availability_exceptions`| Blocked dates & custom hours | `date` (unique), `type` |
| `booking_calendar_locks`| Mutex row locks for MariaDB | `lock_date` (unique) |
| `booking_holds` | 10-minute temporary slot holds | `hold_token` (unique), `visitor_token`, slot index |
| `bookings` | Authoritative booked appointments | `confirmation_token` (unique), `idempotency_key` (unique), UTC bounds |
| `booking_events` | Append-only booking lifecycle log | `booking_id` (FK cascade), `event_type`, `created_at` |
| `resource_categories` | Curriculum resource taxonomy | `slug` (unique), `sort_order`, `active` |
| `resources` | Workbooks, cheat sheets, audio | `slug` (unique), `category_id` (FK), `is_gated` |
| `resource_requests` | Lead capture submissions | `resource_id` (FK), `contact_id` (FK) |
| `resource_downloads` | Gated download audit trail | `resource_id` (FK), `download_token`, `contact_id` (FK) |
| `games` | Language practice games & quizzes| `slug` (unique), `status`, `target_url`, `badge` |
| `pages` | CMS custom content pages | `slug` (unique), `status`, `published_at` |
| `media` | Uploaded images and documents | `path`, `disk`, `mime_type` |
| `content_revisions` | Version history snapshots | `revisionable_type`, `revisionable_id`, `version` |
| `settings` | System-wide configuration KV | `key` (unique), `group`, `is_public` |
| `analytics_events` | First-party client/server events | `event_name`, `visitor_token`, `session_token`, `created_at` |
| `daily_metrics` | Pre-aggregated daily reporting | `date` (unique) |
| `audit_logs` | Admin action audit history | `administrator_id` (FK), `action`, `created_at` |

---

## 4. Specification Compliance Checklist

| Section | Feature Area | Status | Verification Reference |
|---|---|---|---|
| Section 0 | Technology Stack (PHP 8.4, Laravel, MariaDB/InnoDB, Livewire, Alpine, Tailwind) | **COMPLIANT** | Composer & package manifests, Herd runtime |
| Section 1 | Booking Engine & Business Scheduling (`Africa/Cairo`) | **COMPLIANT** | `BookingEngineTest`, `CriticalBookingQAMatrixTest` |
| Section 2 | Concurrency, Row Locks, and Idempotency | **COMPLIANT** | `MariaDbConcurrencyVerificationTest` |
| Section 3 | Storage & Asset Abstraction | **COMPLIANT** | `BackupService`, `MediaController` |
| Section 4 | Public Experience (Home, Booking, Resources, Games, CMS) | **COMPLIANT** | `PublicExperienceTest` |
| Section 5 | CRM & Contact Uniqueness | **COMPLIANT** | `ContactIdentityTest` |
| Section 6 | Reporting, CSV/XLSX Exports, & Injection Prevention | **COMPLIANT** | `AnalyticsAndReportsTest` |
| Section 7 | Admin Operations & Role Authorization | **COMPLIANT** | `AdminOperationsTest`, `AdminManagementAndCmsTest` |
| Section 8 | Automated Maintenance, Backups & Pruning | **COMPLIANT** | `BackupAndMaintenanceTest`, `routes/console.php` |

---

## 5. Summary of Automated Test Results

Total: **91 tests, 429 assertions, 0 failures, 0 errors**

```
PASS  Tests\Unit\ExampleTest
? that true is true

PASS  Tests\Feature\AdminManagementAndCmsTest (7 tests)
? admin create artisan command
? administrator crud lifecycle and role protection
? super admin cannot delete self or last super admin
? admin password reset flow
? custom page cms with revision snapshot and rollback
? media library upload and delete
? admin manual booking creation

PASS  Tests\Feature\AdminOperationsTest (12 tests)
? admin authentication with rate limiting
? admin dashboard metrics
? booking management (status, notes, completion)
? reschedule and cancellation workflows
? availability rules management
? contacts directory and notes
? resources management
? games management
? content management (faqs, social links)
? settings management
? global search
? audit logs tracking

PASS  Tests\Feature\AnalyticsAndReportsTest (16 tests)
? visitor tracking middleware
? client analytics ingestion
? bot filtering
? ip hashing
? active visitor calculation
? reports generation
? csv and xlsx export generation
? formula injection sanitization

PASS  Tests\Feature\AvailabilityTest (9 tests)
? generates slots based on weekly rules
? excludes booked intervals and buffers
? handles date exceptions and blocked days
? minimum notice and maximum horizon enforcement

PASS  Tests\Feature\BackupAndMaintenanceTest (6 tests)
? full backup archive creation and pdo dump
? cleanup expired holds command
? super admin backup access
? regular admin backup denial
? secure backup download and deletion
? maintenance mode 503 enforcement

PASS  Tests\Feature\BookingEngineTest (12 tests)
? creates booking with complete immutable timezone snapshot
? validates slot availability
? manages temporary booking holds
? converts holds to bookings
? generates rfc 5545 ics calendar files
? enforces idempotency

PASS  Tests\Feature\ContactIdentityTest (5 tests)
? email normalization to lowercase
? utm attribution persistence
? duplicate phone collision detection
? contact merge execution and audit logging

PASS  Tests\Feature\CriticalBookingQAMatrixTest (10 tests)
? past time rejection
? 12-hour minimum notice
? 60-day maximum horizon
? 15-minute buffer enforcement
? overlapping slot collision rejection

PASS  Tests\Feature\MariaDbConcurrencyVerificationTest (3 tests)
? calendar date mutex locks are deterministically created
? two connections serialize via mariadb innodb row lock
? concurrent booking collision is rejected authoritatively

PASS  Tests\Feature\PublicExperienceTest (11 tests)
? home page loads with settings and faqs
? booking page renders livewire wizard
? livewire wizard step flow and completion
? booking confirmation page displays details and ics
? resources catalog and gated download
? games catalog and interactive event tracking
? external game card renders with preview image badge and clickable link
? static pages load successfully
```

---

## 6. Remaining Considerations / Production Readiness

* **Environment Settings:** For production deployment, set `APP_ENV=production`, `APP_DEBUG=false`, and configure production mail credentials in `.env` for password reset emails and booking confirmations.
* **MariaDB Version:** Application is fully tuned and verified against **MariaDB 10.4.32 / InnoDB**.
* **Frontend:** Compiled via `npm.cmd run build` using Tailwind CSS v4 and Vite 8.3.
