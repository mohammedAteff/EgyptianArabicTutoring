# Egyptian Arabic Tutoring Platform & Private Admin Console

Production-grade, self-hosted web application for an independent Egyptian Arabic educator, providing a public learning and conversion portal paired with a private operations, scheduling, and analytics back office.

---

## 1. Project Purpose

The platform serves three primary visitor conversion flows:
1. **Book**: Schedule private 1-on-1 Egyptian Arabic tutoring sessions with dual-timezone rendering and hold reservation locks. Cancellation remains available through the secure booking link; rescheduling is handled directly by the tutor/admin team.
2. **Learn**: Browse a structured library of educational resources, download curriculum workbooks, and acquire gated leads.
3. **Play**: Engage with educational vocabulary games designed for street and conversational fluency.

The platform is designed as a **modular monolith** adhering to:
> **One application. One database. One source of truth.** No reliance on external booking SaaS (Calendly), analytics SaaS (Google Analytics), or form SaaS (Typeform).

---

## 2. Technology Stack

- **Runtime**: PHP 8.4
- **Framework**: Laravel 13.32
- **Database**: MariaDB/InnoDB; the local MariaDB-B11 greeting reports 10.11.18. Verify and pin the production vendor/version separately.
- **Frontend**: Blade, Livewire 4, Alpine.js, Tailwind CSS 4, Vite 8
- **Testing**: PHPUnit 12

---

## 3. Architecture & Domain Structure

The application adopts a Domain-Driven modular monolith structure under `app/Domains/`:

```
app/
├── Console/Commands/       # Artisan commands (backup:restore, analytics rollups, etc.)
├── Domains/
│   ├── Administration/     # Administrator authentication, notifications, and security
│   ├── Analytics/          # First-party anonymous session tracking and aggregation
│   ├── Audit/              # Non-repudiable audit logging
│   ├── Availability/       # Canonical slot generator, buffer locks, and rule resolution
│   ├── Booking/            # Core booking engine, holds, lifecycle, reschedule & cancel
│   ├── CMS/                # Structured homepage/about CMS, pages, FAQs, and media
│   ├── Contacts/           # Student identity resolution, deduplication, and CRM
│   ├── Games/              # Educational games catalog and event tracking
│   ├── Resources/          # Gated learning materials, categories, and download tokens
│   ├── System/             # System health, backups, restoration, and diagnostics
│   └── Timezone/           # Cairo wall-time resolution, DST gap/fold handling, ICS export
├── Http/
│   ├── Controllers/Admin/  # Back-office administration controllers
│   ├── Middleware/         # Rate limiting, role authorization, visitor tracking
│   └── Requests/           # Strict HTTP form request validations
└── Livewire/               # Reactive client interfaces (BookingWizard)
```

---

## 4. Local Setup (Laravel Herd on Windows)

1. **Clone and Enter Repository**:
   ```bash
   cd c:\Users\Ateff\Herd\BoltLanding
   ```

2. **Environment Configuration**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Install Dependencies**:
   ```bash
   composer install
   npm install
   ```

4. **Database Configuration**:
   Ensure the local MariaDB service is listening on `127.0.0.1:3307`. Create database `bolt_landing` and test database `bolt_landing_test`:
   ```sql
   CREATE DATABASE bolt_landing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE DATABASE bolt_landing_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

5. **Run Migrations & Seeders**:
   ```bash
   php artisan migrate --seed
   ```

6. **Build Frontend Assets**:
   ```bash
   npm run build
   ```

7. **Serve Application**:
   Served automatically by Laravel Herd at `http://boltlanding.test` (or `php artisan serve`).

---

## 5. Environment Variables & Production Defaults

| Variable | Description | Recommended Production Value |
|---|---|---|
| `APP_ENV` | Application environment | `production` |
| `APP_DEBUG` | Stack trace visibility | `false` |
| `APP_URL` | Canonical root domain | `https://yourdomain.com` |
| `BUSINESS_TIMEZONE` | Business calendar reference | `Africa/Cairo` |
| `DB_CONNECTION` | Database driver | `mysql` |
| `SESSION_DRIVER` | Session storage engine | `database` |
| `CACHE_STORE` | Cache backend | `database` |
| `QUEUE_CONNECTION` | Queue backend | `database` |
| `MAIL_MAILER` | Mail provider driver | `smtp` |
| `BACKUP_OFFSITE_DISK` | Secondary off-host backup destination disk | `s3` (or configured off-host disk) |

---

## 6. Initial Administrator Creation

To provision administrators securely:
```bash
# Recommended: Provision safely via interactive artisan command
php artisan admin:create

# Or specify arguments directly:
php artisan admin:create admin@yourdomain.com --name="Lead Tutor" --role=super_admin

# In non-production environments only:
# DatabaseSeeder creates an initial super_admin (admin@boltlanding.test / Password123!) 
# only if no administrator accounts exist. Re-running the seeder never resets passwords.
php artisan db:seed
```

Roles supported:
- **`super_admin`**: Full access, including administrator management, system health, settings, and backups.
- **`admin`**: Operational access to bookings, availability, calendar, contacts, leads, resources, games, content, and reports.

---

## 7. Scheduled Tasks & Host Cron Setup

The platform relies on scheduled automation registered in `routes/console.php`.

Configure your host server to invoke the Laravel scheduler every minute:
```bash
# Linux crontab:
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1

# Windows Task Scheduler (or Herd):
# Trigger: Every 1 minute
# Action: php.exe artisan schedule:run
```

### Registered Schedules:
- `* * * * * scheduler-heartbeat`: Records minute-by-minute heartbeat to verify host cron health.
- `*/5 * * * * booking:cleanup-holds`: Atomically expires reservation holds older than 10 minutes.
- `05 00 * * * analytics:aggregate-daily --prune`: Rolls up previous day metrics and prunes old raw visitor sessions.
- `00 02 * * * backup:run --clean`: Creates a consistent point-in-time backup archive and prunes backups older than 30 days.
- `00 03 * * * session-cleanup`: Removes expired database sessions according to the configured retention policy.

---

## 8. Queue Workers

Run queue workers for asynchronous background processing:
```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

---

## 9. Booking Engine, Holds & Concurrency Architecture

### Hold Protocol (Two-Phase Commitment):
1. **Hold Acquisition (`BookingHoldService::acquireHold`)**:
   - Visitor selects a slot. System validates against canonical availability rules.
   - Generates an active 10-minute hold with a cryptographically secure 64-character token.
   - Hold is tied to the visitor token, Laravel session token, session type, start UTC, and end UTC.
2. **Buffer-Expanded Calendar Mutex (`AvailabilityService::acquireCalendarDateLocks`)**:
   - Every hold acquisition, booking confirmation, and rescheduling operation calculates `start - buffer` through `end + buffer`.
   - Acquires row-level `FOR UPDATE` locks on all affected dates in `booking_calendar_locks` in sorted order, preventing cross-midnight buffer collision races under real concurrent connections.
3. **Authorized Finalization (`BookingService::createPublicBooking`)**:
   - Finalization mandates both `hold_id` and `hold_token` matching the visitor and session identity.
   - Converts the hold to a confirmed booking atomically within the date-locked transaction.
   - Prevents booking theft, sequential hold guessing, and double-booking.
4. **Lifecycle & Policy Enforcement**:
    - State transition rules: only active `confirmed` or `pending` bookings can be cancelled or rescheduled by authorized workflows.
   - Completed, cancelled, and student no-show records are immutable via public tokens.
    - Enforces configurable cutoff windows for public cancellation and admin/direct-contact rescheduling.

---

## 10. Timezone Architecture & Deterministic DST Handling

- **Single Canonical Storage**: All appointment start and end instants are stored in UTC (`start_at_utc`, `end_at_utc`).
- **Immutable Snapshotting**: At the moment of booking, immutable date/time strings and UTC offsets for both student and Cairo business timezones are permanently captured on the `bookings` record.
- **DST Resolver (`TimezoneService::resolveLocalWallTime`)**:
   - Reusable wall-time parser detects spring-forward gaps (nonexistent local times) and rejects them cleanly with `DstGapException`.
   - Resolves autumn fall-back folds (duplicated local times) deterministically.
- **RFC 5545 ICS Exports (`IcsGenerator`)**:
   - Exports exact UTC `Z` instants with dual-timezone descriptions, compatible with Google Calendar, Apple Calendar, and Outlook.

---

## 11. Operations: Backup & Restore Runbooks

Managed by `App\Domains\System\Services\BackupService`.

### Creating Backups:
```bash
# Generate full archive (database SQL + private gated materials + public media + manifest.json)
php artisan backup:run --type=full --clean
```

- **Consistent Point-in-Time Snapshot**: Executes under `REPEATABLE READ` transaction isolation.
- **Private Gated Resources**: Archives `storage/app/private/resources`.
- **Public Assets**: Archives `storage/app/public`.
- **Cryptographic Integrity**: Generates `manifest.json` with SHA-256 checksums for `database.sql` and every individual file.
- **Offsite Replication**: Automatically replicates archive to configured `filesystems.backup_disk`.

### Restoring Backups:
```bash
# Full restore drill (prompts for confirmation)
php artisan backup:restore backup-full-YYYY-MM-DD-HHMMSS.zip

# File restore into isolated staging directory without touching live database
php artisan backup:restore backup-full-YYYY-MM-DD-HHMMSS.zip --no-db --target-dir=storage/app/staging

# Database restore only with automated bypass
php artisan backup:restore backup-full-YYYY-MM-DD-HHMMSS.zip --no-files --force
```

- Restore process validates ZIP integrity and matches SHA-256 hashes against `manifest.json` before execution.
- Records `AuditLog` entry upon completion.

---

## 12. Testing & Verification

Run the entire test suite against MariaDB:
```bash
php artisan test
```

### Key Verification Suites:
- `tests/Feature/BookingHoldAuthenticationTest.php`: 14 tests for hold authentication, bypass prevention, and visitor matching.
- `tests/Feature/BufferExpandedConcurrencyLockTest.php`: Real two-process parallel OS races against MariaDB verifying buffer mutex.
- `tests/Feature/CanonicalAvailabilityValidationTest.php`: Slot resolution, grid alignment, duration, and notice validation.
- `tests/Feature/DstGapAndFoldHandlingTest.php`: Cairo and New York DST transitions, gaps, and folds.
- `tests/Feature/BookingLifecycleAndPolicyCutoffTest.php`: Status transition matrix and cancellation cutoffs.
- `tests/Feature/RateLimitingTest.php`: Independent throttling on every public write path.
- `tests/Feature/PasswordRecoveryTest.php`: Secure tokenized email password recovery.
- `tests/Feature/BackupAndMaintenanceTest.php`: Archive creation, manifest SHA-256 verification, and restore drills.
- `tests/Feature/AnalyticsAndReportsTest.php`: First-party analytics, privacy, and report definitions.
- `tests/Feature/CmsAndMediaWorkflowTest.php`: Signed draft preview, CMS settings, and reference-safe media deletion.
- `tests/Feature/AdminFunctionsAndCalendarTest.php`: Notifications, resource categories, games lifecycle, and day/week calendar.
- `tests/Feature/ProductionHealthAndSchedulerTest.php`: Scheduler heartbeat freshness, queue monitoring, and diagnostics.

---

## 13. Deployment Runbook

1. Pull code to production server.
2. Ensure PHP 8.4, Composer, Node.js, and MariaDB 10.4+ are installed.
3. Configure production `.env` with `APP_ENV=production`, `APP_DEBUG=false`, and real database/mail credentials.
4. Run deployment steps:
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   php artisan migrate --force
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan storage:link
   ```
5. Ensure Supervisor or systemd manages queue worker (`php artisan queue:work`).
6. Ensure crontab invokes `php artisan schedule:run` every minute.
7. Verify health dashboard at `/admin/health`.
