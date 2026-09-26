**System Implementation, Discovery \& Reliability Master Directive (Definitive Production-Hardened Edition — Ground-Truth Architecture Parity)**

You are the senior lead software engineer and reliability architect for "Egyptian Arabic with Abdallah" (Laravel 13.32.0, PHP ^8.4.1, MariaDB 10.11.18, Livewire 4.4.5, [Alpine.js](Alpine.js), Tailwind CSS 4, Vite 8.3.0).

**Directive Revision Preservation Rule**

The project owner has audited, verified, and hardened this directive based on multi-round architectural audits, schema inspections, concurrency analyses, and Hostinger runtime constraints.

These specifications are intentional and mandatory. Do not revert, simplify, reinterpret, or replace them with alternative conventions or generic framework shortcuts. Before modifying or questioning any requirement in this directive, inspect the live codebase and prove that the requirement is incompatible with the existing implementation.

**Strict Prohibitions:**

Do not revert previously added concurrency, ledger, or teardown safeguards.

Do not remove an explicit constraint because it requires additional boilerplate.

Do not substitute third-party services (e.g., Stripe, Calendly, Redis) for existing first-party implementations.

Do not replace existing domain services with parallel abstractions or repository patterns.

Do not broaden the scope beyond the explicit instructions in this document.

Do not alter business semantics, currency calculations, or relational locking orders.

**Public UI Label Rule:** Never use the plural string "Blogs" in public-facing templates, navigation menus, headers, buttons, or breadcrumbs. Use strictly the singular label: **"Blog"**.

**Zero-Article Codebase Invariant:** Because the site is in an unlaunched testing phase with no external traffic or production data to protect, eliminate all existence of the terms article and articles across all code, filenames, database schemas, base migrations, seeders, factories, tests, variables, and routes.

**Semantic HTML Exemption:** Standard HTML5 \<article\> elements used for document markup are valid semantic tags and are exempt from the entity ban.

**No Hardcoded Notification Intervals:** Never hardcode reminder windows in commands, jobs, or notification services. All reminder lead times must be retrieved dynamically from application settings.

**Canonical Pricing \& Product Invariants:** Strictly encode the five canonical product tiers, their durations (60m vs 120m), validity windows (75 / 100 days), and the 48-hour diagnostic credit offset ($25.00). Never introduce arbitrary tiers (e.g., 5, 20 sessions) or generic e-commerce checkout flows.

If an apparent contradiction arises between this directive and the existing repository, halt and present the concrete file paths, schema definitions, and evidence before altering the architecture. Extend and harden the established system while preserving its relational models, domain boundaries, concurrency controls, and business logic.

**Strict Agent Reporting \& Verification Protocol**

If any required verification cannot be performed in the current environment (e.g., lack of access to the live Hostinger production host or remote S3 buckets), do not simulate or assume success.

Categorize all audit checks explicitly:

**VERIFIED:** Confirmed directly via local inspection, command execution, or schema query.

**UNVERIFIED:** Cannot be proven in the current local environment. State exactly why, identify the environmental prerequisite, and proceed only with tasks safe to execute without that check.

**NOT EXECUTED:** Action deferred pending cutover or live credentials.

**Secret Redaction \& Security Invariant:** Inspect the presence and configuration state of secrets, but never print, echo, serialize, or commit secret values to command output, reports, logs, screenshots, exceptions, or generated files. Report only SET, MISSING, or appropriately redacted fingerprints/status. This applies to APP\_KEY, database credentials, SMTP credentials, S3 credentials, Telegram bot tokens, API keys, and private URLs containing credentials or tokens. Verify that APP\_DEBUG=false in any production environment configuration.

**Preflight Environment \& Capability Audit Gate (Phase 0 Preflight)**

Before generating code, running migrations, or modifying configurations, execute a read-only audit of the codebase and environment. Output an **Environmental \& Preflight Verification Report** covering:

**1. Hostinger Capability \& Runtime Audit:**

Detect and report: Local PHP version, active PHP extensions (bcmath, pdo\_mysql, intl, mbstring, and specifically zip and xml / simplexml required for XLSX generation), detected MariaDB version, and session transaction isolation variables.

Inspect [composer.json](composer.json) for installed export libraries (e.g., openspout/openspout, phpoffice/phpspreadsheet, maatwebsite/excel). If an existing spreadsheet library is already installed, reuse it; if none is present, use a lightweight, memory-efficient streaming library (preferring openspout/openspout to prevent RAM exhaustion on Hostinger).

Confirm active driver assignments: CACHE\_STORE, SESSION\_DRIVER, and QUEUE\_CONNECTION.

**Queue Runtime Contract:** Determine whether production has a persistent queue worker provisioned. If no supported worker process is available, configure queue-dependent tasks explicitly for sync. Do not implement request-time worker autodetection or silently switch queue drivers based on inferred worker health.

Verify filesystem writability for storage/ and bootstrap/cache/, document root mapping to /public, and public storage symlink status.

Inspect .env configuration for APP\_KEY, APP\_ENV, APP\_DEBUG, APP\_URL, trusted proxies, GeoIP database path, SMTP credentials, private lesson room URL, offsite backup S3 credentials, and Telegram configuration.

Document Hostinger resource limits (database size, max concurrent user connections, PHP workers, CPU/RAM, and I/O caps) as deployment constraints rather than assuming hardcoded application limits.

**2. Codebase Discovery \& Pricing Invariant Audit:**

**Specified Canonical Tiers (Confirm Against **[**PricingController.php**](PricingController.php)** \& **[**pricing.blade.php**](pricing.blade.php)**):**

**Diagnostic \& Roadmap (diagnostic\_roadmap):** 1 session, 60 minutes, $25.00. Mandatory entry point.

**Foundation Coaching Track (foundation\_track):** 8 sessions (16 hours total, 120 mins/session), $280.00 standard ($255.00 after $25.00 diagnostic credit). Valid for 75 calendar days from invoice settlement evaluated at 00:00:00 Africa/Cairo.

**Fluency Immersion Track (fluency\_track):** 12 sessions (24 hours total, 120 mins/session), $390.00 standard ($365.00 after $25.00 diagnostic credit). Valid for 100 calendar days from invoice settlement evaluated at 00:00:00 Africa/Cairo. Priority calendar scheduling.

**Pay-As-You-Go Maintenance (payg\_maintenance):** 1 session, 120 minutes, $48.00 ($24.00/hr). Alumni only (unlisted from public primary tracks). Valid 30 calendar days.

**Advanced Conversational (advanced\_conversational):** 1 session, 60 minutes, $28.00 ($28.00/hr). Unlisted exception (C1+ debate only). Valid 30 calendar days.

Confirm that Cashier presets source these default values directly from the discovered configuration.

**3. Analytics Event Taxonomy, Route \& Middleware Alias Audit:**

**Event Taxonomy Confirmation:** Inspect App\\Domains\\Analytics\\Services\\[AnalyticsService.php](AnalyticsService.php) and verify the literal event names in its allow-list/constants (e.g., verifying whether pageviews are tracked as 'pageview' or 'page\_view', and confirming conversion names such as 'booking\_completed' and 'booking\_cta\_click'). Use the confirmed literal strings throughout the bounce rate aggregation queries.

**Route \& Column Invariants:**

Note that /admin/analytics exists, but /admin/analytics/countries is **unregistered**. Register this route cleanly in routes/[web.php](web.php).

Note schema column parity: visitor\_sessions and visitors record country as detected\_country\_code (char(2)), whereas daily\_country\_metrics uses country\_code (char(2)). Map visitor\_sessions.detected\_country\_code to daily\_country\_metrics.country\_code in all rollups.

**Middleware Alias Verification:**

Inspect bootstrap/[app.php](app.php) (or kernel middleware aliases) to verify the exact alias string mapped to App\\Http\\Middleware\\EnsureAdminRole (confirmed as '[admin.role](admin.role)'). The agent must resolve and use this literal string across route definitions; do not leave variable placeholders in route files.

**4. Deliberate Discovery Mandate (Complete Entity Inventory):**

**No Rushed Changes:** Take the necessary time to systematically discover, inventory, and cross-reference every file in the repository before writing code or renaming files.

**Verified CMS Paths:**

Models: App\\Domains\\CMS\\Models\\[Article.php](Article.php), [ArticleRevision.php](ArticleRevision.php), [ArticleSlugRedirect.php](ArticleSlugRedirect.php).

Domain Service: App\\Domains\\CMS\\Services\\[ArticleService.php](ArticleService.php).

Controllers: App\\Http\\Controllers\\[ArticleController.php](ArticleController.php), App\\Http\\Controllers\\Admin\\[ArticleController.php](ArticleController.php).

Foreign Keys: articles.author\_id and article\_revisions.revised\_by reference administrators(id).

Catalog all Blade templates, loop variables, route helpers, and test files referencing article or articles.

**Architectural Invariants \& Non-Negotiable Guardrails**

**Universal Dual-Format Streaming Export (CSV \& XLSX):**

**Memory Budget Protection:** Every export endpoint (all analytics sub-pages, maintenance logs, and financial cashier views) must stream rows directly to the client using chunked queries or database cursors (cursor() / chunk(500)). Never load whole tables or large collections into memory before generating CSV or XLSX files, preventing 503/Out-Of-Memory errors on Hostinger plans with constrained PHP memory limits.

**Spreadsheet Formula-Injection Neutralization:** For both CSV and XLSX outputs, every cell starting with formula trigger characters (=, +, -, @, \\t, \\r) must be sanitized (e.g., prepended with a single quote ') to prevent remote code execution in spreadsheet software.

**Dual-Format Endpoints:** All exportable views must provide UI options to download data in either CSV (.csv) or Excel (.xlsx) format using a uniform query parameter (e.g., ?format=csv and ?format=xlsx).

**Zero Untagged Cache Exceptions:** The host uses file or database cache drivers. Calling Cache::tags() throws an immediate, fatal BadMethodCallException. Strictly forbid Cache::tags() anywhere in the codebase. Use flat, prefixed cache keys (e.g., presence:summary, counters:social\_proof).

**Build vs. Runtime Separation:** Never require [Node.js](Node.js) or npm on the production PHP runtime. Frontend assets must compile reproducibly in the local or CI build environment (npm run build) and deploy as static artifacts in public/build/. Hostinger production deployment may invoke host-side Node tooling only if the specific purchased plan explicitly supports it and Node/npm availability is verified.

**Scheduler Runtime Contract:** Hostinger cron must invoke Laravel's scheduler once every minute in UTC: \* \* \* \* \* /usr/bin/php /path/to/artisan schedule:run \>\> /dev/null 2\>\&1 Laravel's internal scheduler definitions (e.g., everyFiveMinutes()) determine task dispatching. Never configure the host cron itself at five-minute intervals for five-minute Laravel tasks.

**Fail-Open Maintenance Mode with Admin Authentication Exception:**

Read maintenance state through a short-TTL cached lookup (Cache::remember('system.maintenance\_mode', 30, ...)) wrapped in a try/catch block defaulting to "site live" (fail-open) if the database or cache store is unreachable. When CACHE\_STORE=database, a total database crash causes the check to fail open; do not represent this middleware as providing database-independent availability during a total DB outage.

**Admin Login Access:** Maintenance mode must not block verified administrator authentication endpoints:

GET /admin/login ([admin.login](admin.login))

POST /admin/login ([admin.login.submit](admin.login.submit))

GET /admin/forgot-password ([admin.password.request](admin.password.request))

POST /admin/forgot-password ([admin.password.email](admin.password.email))

GET /admin/reset-password/{token} ([admin.password.reset](admin.password.reset))

POST /admin/reset-password ([admin.password.update](admin.password.update)) Protected admin routes remain bypassable after successful authentication via auth:web.

**Middleware Registration:** Apply maintenance interception through the existing global web middleware path, verifying that the Livewire AJAX update endpoint (/livewire-ac3f421a/update) is covered. Add a route-specific middleware assignment only if inspection proves it is not already covered by the global path, avoiding duplicate middleware execution.

**Exact Decimal Arithmetic (No Floating-Point Math):** Never cast monetary DECIMAL(10,2) values to PHP (float). Binary floating-point arithmetic produces precision errors in refund ceiling and financial calculations. Use integer cents (bcmul($val, '100', 0)) or bcsub() / bccomp() / bcadd() with 2 decimal places for all financial calculations.

**Append-Only Ledger \& Immutability:** Once a payment, refund, or ledger entry is posted, it is immutable. Never execute UPDATE or DELETE on posted financial records to correct errors. Corrections must take the form of compensating ledger entries (courtesy\_adjustment, expiration\_forfeit). Derived remaining balance is calculated strictly on read: \\text{Remaining Balance} = \\text{student\\\_[packages.final](packages.final)\\\_price} - \\sum(\\text{payment\\\_[records.amount](records.amount)\\\_paid}) + \\sum(\\text{payment\\\_[refunds.amount](refunds.amount)\\\_refunded}) 

**Server-Enforced Financial Idempotency:** Every cashier mutation must have a server-enforced database uniqueness constraint on its operation-specific idempotency key (payment\_records.idempotency\_key, payment\_refunds.idempotency\_key, session\_ledger\_entries.idempotency\_key).

**Applicable-Level Lock Ordering:**

booking\_calendar\_locks date mutex acquisition is a separate scheduling-resource lock protocol governed by its existing deterministic date-ordering rules.

For each financial workflow, acquire all existing/applicable row locks in the established sequence: \\text{Student} \\longrightarrow \\text{StudentPackage} \\longrightarrow \\text{PaymentRecord} \\longrightarrow \\text{SessionLedgerEntry} A level that has no existing row is skipped; creation/insertion must not introduce a lock acquisition that violates the relative ordering of the applicable levels.

**At-Least-Once Telegram API Acceptance Semantics:** Recipient-specific deduplication prevents duplicate success records in the database, but cannot make an external Telegram API call atomic with database state. Concurrent callers must therefore be treated as at-least-once delivery and may theoretically produce duplicate API dispatches during a race. The scheduled command must use -\>withoutOverlapping(10) to mitigate concurrency, and deduplication keys must be compound and milestone-specific (reminder\_{$bookingId}\_{$windowMinutes}m\_{$chatId}) so a failure for one recipient does not block retries or cause duplicate dispatches to recipients who already succeeded.

**Phase 0: Production Readiness Hardening \& Test Harness Remediation**

Execute these fixes before altering feature code to ensure a reliable test and runtime baseline.

**1. Multi-Process Worker Termination \& Safe Fixture Teardown**

**Target Files:** tests/Feature/[BufferExpandedConcurrencyLockTest.php](BufferExpandedConcurrencyLockTest.php), tests/Feature/[MariaDbConcurrencyVerificationTest.php](MariaDbConcurrencyVerificationTest.php).

**Root Cause Remedy (Line 67 Fix):** In [BufferExpandedConcurrencyLockTest.php:67](BufferExpandedConcurrencyLockTest.php:67), deleting parent bookings rows prior to child session\_reschedules triggers MariaDB Error 1451 (RESTRICT foreign key constraint session\_reschedules\_booking\_id\_foreign).

**Implementation:**

Capture all background worker process handles or PIDs during test execution. In tearDown(), explicitly wait, terminate, or kill any active worker processes (proc\_terminate(), $process-\>stop(1)) and verify they have exited before executing database cleanup. No SQL cleanup or transaction reset may occur while any child worker process remains alive.

Terminate and purge auxiliary PDO database connections (DB::disconnect(), DB::purge()).

Enforce strict child-first deletion order, scoped strictly to these concurrency test classes, with foreign key check suppression as an isolated test-harness reset mechanism:

protected function tearDown(): void { // 1. Terminate all child worker processes before touching the database if (!empty($this-\>workerProcesses)) { foreach ($this-\>workerProcesses as $process) { if (is\_resource($process)) { proc\_terminate($process); proc\_close($process); } elseif ($process instanceof \\Symfony\\Component\\Process\\Process \&\& $process-\>isRunning()) { $process-\>stop(1); } } $this-\>workerProcesses = \[\]; } // 2. Disconnect and purge secondary PDOs foreach (\['conn\_a', 'conn\_b', 'secondary'\] as $connection) { try { DB::disconnect($connection); DB::purge($connection); } catch (\\Throwable $e) { // Connection not initialized } } // 3. Child-first database cleanup scoped to isolated concurrency fixtures if (DB::connection()-\>getPdo()) { DB::statement('SET FOREIGN\_KEY\_CHECKS = 0'); DB::table('session\_reschedules')-\>truncate(); DB::table('session\_ledger\_entries')-\>truncate(); DB::table('booking\_events')-\>truncate(); DB::table('booking\_holds')-\>truncate(); DB::table('bookings')-\>truncate(); DB::table('booking\_calendar\_locks')-\>truncate(); DB::table('availability\_rules')-\>truncate(); DB::table('availability\_exceptions')-\>truncate(); DB::statement('SET FOREIGN\_KEY\_CHECKS = 1'); } parent::tearDown(); } 

**Verification:** Run php artisan test twice consecutively. Verify zero failures and confirm active database connections return to baseline.

**2. Database Capability Version Tolerance**

**Target File:** App\\Domains\\Database\\Services\\[DatabaseCapability.php](DatabaseCapability.php).

**Fix:** Update regex matching in vendor detection to accept standard MariaDB minor/patch versions (^10\\.(4|5|6|11)\\..\* and ^11\\..\*). Maintain strict validation of InnoDB engine availability and dynamic session isolation switching (SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED).

**3. Backup Status Visibility \& Categorized Alerting**

**Target Files:** App\\Domains\\System\\Services\\[BackupService.php](BackupService.php), App\\Http\\Controllers\\Admin\\[DashboardController.php](DashboardController.php), resources/views/admin/[dashboard.blade.php](dashboard.blade.php).

**Fix:** If offsite S3 upload fails, record last\_offsite\_backup\_status = 'failed'. Log the full exception via Laravel's protected application logger (Log::error($e)). Database/admin status messages must use fixed, predefined operational categories (e.g., s3\_replication\_failed, s3\_authentication\_failed, s3\_connectivity\_unavailable). Never derive the stored dashboard message directly from an exception string. Render a prominent warning banner across /admin and /admin/dashboard when offsite replication fails.

**4. Bounded Hold Retention Pruning**

**Target File:** app/Console/Commands/[CleanupExpiredHoldsCommand.php](CleanupExpiredHoldsCommand.php).

**Fix:** Hard-delete expired and released holds older than 30 days in indexed batches:

DB::table('booking\_holds') -\>whereIn('status', \['expired', 'released'\]) -\>where('expires\_at', '\<', now()-\>subDays(30)) -\>orderBy('id') -\>limit(1000) -\>delete(); 

**5. Resilient Queue \& GeoIP Fallbacks**

In config/[queue.php](queue.php), ensure fallback to sync when persistent workers are absent.

In App\\Domains\\Analytics\\Services\\[GeoIpService.php](GeoIpService.php), wrap .mmdb binary reads in a try/catch block. If missing or corrupt, default to country XX (unknown). Only evaluate CF-IPCountry or proxy headers when the request originates from a verified trusted proxy.

**Phase 1: Deep Behavioral Analytics, Country Pipeline Activation, Universal Bounce Rate \& Dual-Format Export**

Upgrade first-party analytics to deliver granular, YouTube Studio-style metrics without schema duplication or unbounded database growth. Activate the country segmentation report, embed the bounce rate metric across all analytical dimensions, and provide universal CSV/XLSX streaming export across all reporting views.

**1. Schema Migration \& Rollup Storage (daily\_country\_metrics)**

**Explicit Schema Extension:** Unlike daily\_metrics (which stores EAV key-value dimensions), daily\_country\_metrics uses a fixed-column structure. Add bounced\_sessions\_count directly to daily\_country\_metrics:

Schema::table('daily\_country\_metrics', function (Blueprint $table) { $table-\>unsignedInteger('bounced\_sessions\_count')-\>default(0)-\>after('page\_views'); }); 

Update App\\Domains\\Analytics\\Models\\[DailyCountryMetric.php](DailyCountryMetric.php) to include bounced\_sessions\_count in $fillable and $casts.

In daily\_metrics, record site-wide bounces and sessions using the existing EAV pattern:

Numerator: metric\_name = 'bounced\_sessions', dimension\_key = 'site', dimension\_value = 'all', count = {bounced\_count}.

Denominator: metric\_name = 'sessions', dimension\_key = 'site', dimension\_value = 'all', count = {total\_session\_count}.

When deriving site-wide bounce rate over any date range from daily\_metrics: \\text{Site Bounce Rate (\\%)} = \\left( \\frac{\\sum \\text{count where metric\\\_name = 'bounced\\\_sessions'}}{\\sum \\text{count where metric\\\_name = 'sessions'}} \\right) \\times 100 

**2. Country Segmentation Pipeline Diagnosis \& Route Registration**

**Route Registration:** Register the literal route definition in routes/[web.php](web.php) using the confirmed '[admin.role](admin.role)' middleware alias:

Route::get('/admin/analytics/countries', \[App\\Http\\Controllers\\Admin\\AnalyticsDashboardController::class, 'countries'\]) -\>middleware(\['web', '[admin.noindex](admin.noindex)', 'auth:web', '[admin.role](admin.role):super\_admin,admin'\]) -\>name('[admin.analytics.countries](admin.analytics.countries)'); 

**Sidebar Link:** Add an explicit navigation link in the admin layout navigation for Country Analytics.

**Column Alignment:** Ensure all session queries map visitor\_sessions.detected\_country\_code to daily\_country\_metrics.country\_code.

**Interactive Column Sorting \& Filtering:** Enable two-way sorting (ascending/descending) on:

Country Name / Code / Flag

Unique Visitors

Total Sessions

**Bounce Rate (%)** (derived as bounced\_sessions\_count / sessions \* 100)

Booking Conversion Rate (%)

Provide an inline text search filter to quickly find specific countries.

**3. Universal Bounce Rate Metric Integration**

**Schema-Accurate Bounce Metric Definition:** A visitor session is categorized as a "bounce" if:

Duration under 10 seconds: TIMESTAMPDIFF(SECOND, started\_at, last\_activity\_at) \< 10

Single pageview: Exactly 1 child event in analytics\_events matching the confirmed pageview event name (verified during preflight).

Zero conversions: 0 child events in analytics\_events matching the confirmed conversion event names (e.g., 'booking\_completed', 'booking\_cta\_click').

**Calculation:** \\text{Bounce Rate (\\%)} = \\left( \\frac{\\text{Count of Bounced Sessions}}{\\text{Total Sessions}} \\right) \\times 100 

**Embed Across All Analytics Dashboards:**

**Site Overview Panel (/admin/analytics):** Display site-wide bounce rate KPI card alongside unique visitors, pageviews, and average duration, with a trend line over time.

**Country Breakdown Panel (/admin/analytics/countries):** Calculate and display bounce rate per country derived from daily\_country\_metrics.bounced\_sessions\_count / [daily\_country\_metrics.sessions](daily_country_metrics.sessions).

**Content \& Section Attention Panel (/admin/analytics/sections):** Calculate entry-page bounce rate for each template and blog post.

**4. Granular Frontend Telemetry Pipeline \& Server-Side Security**

**Schema Migration for Telemetry Idempotency:** Inspect analytics\_events for an existing event UUID column. If absent, add event\_uuid (char 36, unique index):

Schema::table('analytics\_events', function (Blueprint $table) { $table-\>char('event\_uuid', 36)-\>nullable()-\>after('id'); $table-\>unique('event\_uuid', 'analytics\_events\_event\_uuid\_unique'); }); 

*Note:* All newly ingested telemetry events must have a non-null UUID; do not allow the ingestion path to persist a new event without event\_uuid.

**Implement resources/js/**[**analytics-telemetry.js**](analytics-telemetry.js)** bundled via Vite:**

**Server-Side Bounds Enforcement:** The ingestion controller (/analytics/event) must independently validate that payloads do not exceed 32 KB and contain no more than 20 events per batch. Reject excessive payloads with HTTP 413 or 422.

**Deterministic Ingestion Idempotency:** Attempt normal insertion of each telemetry event. If the event\_uuid unique constraint is violated, catch UniqueConstraintViolationException, treat the event as an already-ingested idempotent retry, and return a successful ingestion response without incrementing metrics. Do not use broad INSERT IGNORE semantics.

**Section View Firing Rule:** A section\_view event fires strictly once per pageview when the corresponding element with data-section-id first crosses the \\ge 50\\% visibility threshold. Subsequent intersection triggers during that same pageview must not increment views.

**Deterministic Dwell Ownership:** At any instant, a page may accrue dwell time to at most one section. When multiple tracked sections simultaneously satisfy the \\ge 50\\% visibility threshold, assign the active dwell interval to the section with the highest intersectionRatio; break ties deterministically by DOM order. When no tracked section satisfies the threshold, record no section dwell. Ensure one second of page activity cannot be counted simultaneously for multiple section-dwell timers on the same page.

**Template Coverage:** Instrument landing page sections (#hero, #pricing, #curriculum, #tutor-bio) and learning content templates:

resources/views/blog/[show.blade.php](show.blade.php) (data-section-id="blog-content")

resources/views/resources/[show.blade.php](show.blade.php) (data-section-id="resource-preview")

resources/views/games/[show.blade.php](show.blade.php) (data-section-id="game-board")

**Tab Visibility \& Active Presence Heartbeat:** Pause all timers when [document.visibilityState](document.visibilityState) === 'hidden'. While a page remains visible, send a lightweight activity heartbeat every 45 seconds (below the 60-second activity threshold). Pause heartbeats immediately when the document is hidden.

**Exit Beacon Dispatch:** Use [navigator.sendBeacon](navigator.sendBeacon)('/analytics/event', payload) on pagehide and visibilitychange. Deduplicate batches client-side to prevent firing identical payloads across both hooks.

**CSRF \& Ingestion Validation:** Verify existing CSRF handling on /analytics/event. Pass tokens or configure route middleware accordingly. Reject unlisted event names, invalid section IDs, and out-of-range numeric values.

**5. Bounded Telemetry Pruning \& Retention Management**

Add batch-oriented deletion routines to [AggregateDailyAnalyticsCommand.php](AggregateDailyAnalyticsCommand.php) to prevent table bloat:

Prune analytics\_events older than 90 days in chunks of 1,000 using indexed timestamps.

Prune marketing\_touches older than 90 days in chunks of 1,000.

Prune student\_auth\_attempts where cooldown\_until \< now() - INTERVAL 7 DAY or records older than 30 days in chunks of 1,000.

Verify that pruning queries utilize existing indexes (created\_at, cooldown\_until).

**6. Comprehensive Dual-Format Export Across All Analytics Views**

Enhance App\\Http\\Controllers\\Admin\\[AnalyticsDashboardController.php](AnalyticsDashboardController.php) and App\\Domains\\Reporting\\Services\\[ExportService.php](ExportService.php) to provide streaming downloads in both CSV and XLSX formats on every analytics view:

**Daily Metrics \& Overview Export:** (/admin/analytics/overview/export?format={csv|xlsx}) — Exports date, metric name, dimensions, counts, visitor tallies, and overall bounce rate percentage.

**Country Breakdown \& Conversion Export:** (/admin/analytics/countries/export?format={csv|xlsx}) — Exports country code, country name, unique visitors, total sessions, **bounce rate percentage**, and booking conversion percentage.

**Section Dwell \& Attention Export:** (/admin/analytics/sections/export?format={csv|xlsx}) — Exports section ID, page template, total views, total dwell seconds, average attention duration, entry bounce rate, and drop-off rate.

**Export Standards:**

Enforce cursor-based or chunked data extraction to keep PHP RAM usage minimal.

Prepend ' to all strings starting with =, +, -, @ to neutralize formula injection in both CSV and XLSX formats.

Set correct Content-Type headers (text/csv vs. application/[vnd.openxmlformats-officedocument.spreadsheetml.sheet](vnd.openxmlformats-officedocument.spreadsheetml.sheet)) and Content-Disposition: attachment.

**Phase 2: Live Presence, Engagement Counters \& Public Social Proof (with Fully Configurable Copy)**

Implement operational counters and social-proof widgets backed by flat cache keys, database queries aligned with their underlying metrics, and full editorial control over public text templates from the admin dashboard.

**1. Database \& Settings Configuration**

**Session Started Index Migration:** Create a migration adding an index on visitor\_sessions.started\_at:

Schema::table('visitor\_sessions', function (Blueprint $table) { $table-\>index('started\_at', 'visitor\_sessions\_started\_at\_index'); }); 

Seed settings in settings (group = 'counters'):

**Counter 1 (Learning Hours):**

counters.learning\_hours.public\_enabled (bool, default: false)

counters.learning\_hours.window\_days (int, default: 7)

[counters.learning\_hours.headline](counters.learning_hours.headline) (string, default: "Globally, Line of Action students have put in...")

[counters.learning\_hours.subtitle](counters.learning_hours.subtitle) (string, default: "of practice time in the last 7 days")

**Counter 2 (Monthly Traffic):**

counters.monthly\_traffic.public\_enabled (bool, default: false)

[counters.monthly\_traffic.source](counters.monthly_traffic.source) (enum: 'unique\_visitors', 'sessions', default: 'unique\_visitors')

counters.monthly\_traffic.template\_visitors (string, default: "We welcomed {count} visitors last month")

counters.monthly\_traffic.template\_sessions (string, default: "We had {count} website sessions last month")

**Counter 3 (Live Users):**

counters.live\_users.public\_enabled (bool, default: false)

[counters.live\_users.template](counters.live_users.template) (string, default: "{count} active visitors online right now")

**2. Presence \& Counter Engine**

Create App\\Domains\\Analytics\\Services\\[EngagementCounterService.php](EngagementCounterService.php):

**Live Activity Tracking:**

Confirm VisitorSession touches last\_activity\_at on active requests, beacons, and 45-second visibility heartbeats.

Implement live user resolution:

public function getLiveUsersCount(): int { return DB::table('visitor\_sessions') -\>where('last\_activity\_at', '\>', now()-\>subSeconds(60)) -\>distinct('visitor\_id') -\>count('visitor\_id'); } 

Format the public copy by replacing {count} inside the configured [counters.live\_users.template](counters.live_users.template).

**Monthly Traffic Breakdown:**

Derive metrics for the previous calendar month using the half-open interval \[\\text{month\\\_start}, \\text{month\\\_end}), with both boundaries derived from Cairo calendar dates and converted to UTC before querying visitor\_sessions:

Unique Visitors: COUNT(DISTINCT visitor\_id) where started\_at \>= $utcMonthStart and started\_at \< $utcMonthEnd.

Total Sessions: COUNT(id) where started\_at \>= $utcMonthStart and started\_at \< $utcMonthEnd.

Public Formatting: Replace {count} in either counters.monthly\_traffic.template\_visitors or counters.monthly\_traffic.template\_sessions based on the chosen source setting.

**Collective Learning Activity Counter:**

Bound calculations strictly to completed Cairo calendar days using a half-open interval \[\\text{window\\\_start}, \\text{window\\\_end}), where window\_end is midnight today (Cairo time) and window\_start is the configured number of complete Cairo days prior (counters.learning\_hours.window\_days). Exclude the current partial Cairo day. Convert both boundaries to UTC instants for database querying.

Calculation: \\text{Lesson Hours} = \\sum (\\text{[bookings.duration](bookings.duration)\\\_minutes where status='completed', within window}) / 60 \\text{Study Dwell Hours} = \\sum (\\text{daily\\\_[metrics.count](metrics.count) where metric\\\_name='section\\\_dwell\\\_seconds', within window}) / 3600 \\text{Total Hours} = \\text{Lesson Hours} + \\text{Study Dwell Hours} 

Format output dynamically into human-readable intervals: X days, Y hours, Z minutes.

Render with the admin-configured headline above the counter and subtitle below the counter (substituting {time} if included in the copy).

**3. Flat Caching \& Public Presentation Layer**

Cache public payload values using flat keys without tags:

Cache::remember('counters:social\_proof\_payload', 300, function () use ($counterService) { return $counterService-\>getPublicCountersPayload(); }); 

Build an [Alpine.js/Blade](Alpine.js/Blade) component resources/views/components/[social-proof-banner.blade.php](social-proof-banner.blade.php) rendering conditionally based on settings.

**Admin Configuration Interface:**

Provide input fields in App\\Http\\Controllers\\Admin\\[SettingController.php](SettingController.php) for visibility toggles, monthly traffic source, headline/subtitles, and template strings.

Temporary date-range filters belong strictly in AnalyticsDashboardController and views; do not persist temporary analytics filter state as application settings.

**Phase 3: Back-Office Manual Accounting, Canonical Pricing, Diagnostic Credit \& Reconciliation Hub**

Establish a cashier operations hub for manual payments, installments, discounts, and refunds. The cashier replaces manual spreadsheets, enforces strict decimal arithmetic, syncs directly with canonical pricing tiers, enforces the 48-hour diagnostic credit, tracks package validity windows, and provides dual-format streaming exports.

**1. Financial Invariants \& Server-Enforced Idempotency**

**No Floating-Point Math:** Prohibit casting DECIMAL(10,2) values to PHP (float). Use bcsub(), bcadd(), and bccomp() with scale 2.

**Server-Enforced Form Idempotency:** Every cashier mutation (Record Payment, Issue Refund, Grant Courtesy Credit, Create Package) must submit a client UUID. Persist this key under a database uniqueness constraint on the resulting record or ledger entry. Reject duplicate submissions with HTTP 409.

**Derived Balances:** \\text{Remaining Balance} = \\text{student\\\_[packages.final](packages.final)\\\_price} - \\sum(\\text{payment\\\_[records.amount](records.amount)\\\_paid}) + \\sum(\\text{payment\\\_[refunds.amount](refunds.amount)\\\_refunded}) Credits remain derived via SUM(session\_ledger\_entries.credit\_change). No mutable balance or credit columns are permitted.

**2. Canonical Product Tiers, Durations \& Validity Invariants**

The Cashier hub must provide one-click presets encoding Abdallah's exact business specifications:

Product Preset KeyNameSession CountSession DurationStandard PriceValidity WindowAudience / Accessdiagnostic\_roadmapDiagnostic \& Roadmap160 mins$25.0014 calendar daysMandatory Entryfoundation\_trackFoundation Coaching Track8120 mins$280.0075 calendar daysCore Trackfluency\_trackFluency Immersion Track12120 mins$390.00100 calendar daysCore Track (Priority)payg\_maintenancePay-As-You-Go Maintenance1120 mins$48.0030 calendar daysAlumni Only (Unlisted)advanced\_conversationalAdvanced Conversational160 mins$28.0030 calendar daysC1+ Debate (Unlisted) 

**3. 48-Hour Diagnostic Credit Automation Logic**

**Business Rule:** When a student completes and settles a Diagnostic Session ($25.00), and subsequently enrolls in an 8-session or 12-session track within **48 hours** of diagnostic settlement:

The $25.00 fee is 100% credited against the track package invoice.

**Package Invoice Amount:**

Foundation Track (8 sessions): **$255.00** ($280.00 standard − $25.00 diagnostic credit). Total customer expenditure across diagnostic + package = $280.00.

Fluency Immersion Track (12 sessions): **$365.00** ($390.00 standard − $25.00 diagnostic credit). Total customer expenditure across diagnostic + package = $390.00.

**Cashier Implementation:**

When selecting a student for foundation\_track or fluency\_track, query payment\_records and student\_packages for a completed diagnostic\_roadmap settled within the past 48 hours relative to now().

If found, automatically select and apply the **"48-Hour Diagnostic Credit (-$25.00)"** toggle:

Set student\_packages.original\_price to 280.00 or 390.00.

Set student\_packages.discount\_amount to 25.00.

Set student\_packages.final\_price to 255.00 or 365.00.

**4. Validity Window \& Expiration Calculation**

**Start Invariant:** Package access windows begin on the date of invoice settlement (evaluated at 00:00:00 Africa/Cairo).

**Calculation:** \\text{Cairo Midnight} = \\text{settlement\\\_date}-\>\\text{setTimezone('Africa/Cairo')}-\>\\text{startOfDay()} \\text{expiration\\\_date} = \\text{Cairo Midnight} + \\text{validity\\\_days} \\text{ (75 for 8-pack, 100 for 12-pack)} Store student\_packages.expiration\_date directly as the calculated date.

**5. Transaction Workflows \& Applicable-Level Lock Ordering**

**Applicable-Level Lock Ordering:** For each financial workflow, acquire all existing/applicable row locks in the established sequence: \\text{Student} \\longrightarrow \\text{StudentPackage} \\longrightarrow \\text{PaymentRecord} \\longrightarrow \\text{SessionLedgerEntry} A level that has no existing row is skipped; creation/insertion must not introduce a lock acquisition that violates relative ordering.

**Single Sessions (Diagnostic, Maintenance, Advanced):**

Log a payment\_records row and append a package\_grant (+1) entry to session\_ledger\_entries under row locks.

Set recorded\_by to the authenticated administrator (auth:web-\>id).

**Multi-Session Packages (Foundation \& Fluency):**

Create student\_packages record storing package\_name, original\_price, discount\_amount, final\_price, currency = 'USD', total\_sessions\_allocated (8 or 12), and calculated expiration\_date.

Append package\_grant (+8 or +12) to session\_ledger\_entries.

**Partial Payments \& Installments:**

Record initial and subsequent payments in payment\_records against student\_package\_id.

Calculate remaining package balance on read via bcsub().

Expose clear UI indicators: **Paid in Full** (0.00 remaining) vs **Partial Payment** (Owes $[X.XX](X.XX)).

**Custom Deals \& Negotiated Overrides:**

Allow an authorized administrator to manually override final\_price, total\_sessions\_allocated, or expiration\_date, recording administrative audit notes in audit\_logs.

**Refunds, Locks \& Atomic Idempotency:**

Lock the target payment\_records row using SELECT ... FOR UPDATE before calculating cumulative refunds.

Verify total refunded amount does not exceed payment:

$paymentRecord = DB::table('payment\_records')-\>where('id', $paymentId)-\>lockForUpdate()-\>first(); $totalRefunded = DB::table('payment\_refunds')-\>where('payment\_record\_id', $paymentId)-\>sum('amount\_refunded'); if (bccomp(bcadd($totalRefunded, $requestedRefund, 2), $paymentRecord-\>amount\_paid, 2) \> 0) { throw new \\InvalidArgumentException("Refund exceeds original payment amount."); } 

Record refund in payment\_refunds with a server-unique idempotency key and recorded\_by = auth:web-\>id.

Forfeit unused credits via expiration\_forfeit (-N) entries in session\_ledger\_entries with operation-specific idempotency keys (e.g., forfeit\_refund\_{$refundId}).

**Courtesy Grants:**

Allow granting makeup or courtesy credits (+N) via courtesy\_adjustment in session\_ledger\_entries without creating phantom financial income rows in payment\_records.

**Audit Integration:** Log all operations to audit\_logs via AuditLogService recording actor\_user\_id = auth:web-\>id, IP address, and financial context.

**6. Read-Only Reconciliation Diagnostic \& Admin Cashier UI**

Create an administrative cashier hub at /admin/billing/cashier in App\\Http\\Controllers\\Admin\\[StudentBillingController.php](StudentBillingController.php).

Provide a read-only reconciliation diagnostic command and view (/admin/billing/reconcile) that checks:

Negative derived package balances.

\\sum(\\text{payment\\\_[refunds.amount](refunds.amount)\\\_refunded}) \> \\text{payment\\\_[records.amount](records.amount)\\\_paid}.

Package allocated credits vs. ledger grant/consumption mismatches.

Orphaned or ownership-inconsistent financial records. The diagnostic must report discrepancies without automatically mutating historical ledger rows.

**7. Dual-Format Financial Exports (CSV \& XLSX)**

Implement streaming exports for both CSV and XLSX at /admin/billing/export?format={csv|xlsx} via ExportService:

Columns: Date, Student Name, Student Email, Transaction Type, Package Name, Amount Paid, Currency, Payment Method, Reference ID, Admin Logged.

Provide export triggers on the reconciliation view (/admin/billing/reconcile/export?format={csv|xlsx}) to export discrepancy reports.

Preload all relationships using chunking/cursors. Neutralize formula triggers (=, +, -, @) across all exported cells.

**Phase 4: Offers \& Promotions Management Portal**

A self-service admin portal to create, preview, schedule, and toggle public promotional banners and modals without code changes.

**1. Schema Migration (promotions)**

Create table promotions:

id (bigint unsigned, primary)

title (varchar 255)

headline (varchar 255)

subheadline (text, nullable)

cta\_text (varchar 100)

cta\_url (varchar 255)

banner\_image\_path (varchar 255, nullable)

is\_active (boolean, default: false)

display\_type (enum: 'top\_bar', 'floating\_modal', 'inline\_card')

starts\_at (datetime, nullable)

ends\_at (datetime, nullable)

has\_countdown (boolean, default: false)

created\_at, updated\_at (timestamps)

**2. Domain Service \& Controller**

Create App\\Domains\\Marketing\\Services\\[PromotionService.php](PromotionService.php).

**UTC Timestamp Invariant:** promotions.starts\_at and promotions.ends\_at must be stored as UTC instants. Admin UI converts to/from Cairo business timezone. Countdown calculations evaluate absolute UTC instants.

Build management UI at /admin/promotions with image uploads routed through App\\Http\\Controllers\\Admin\\[MediaController.php](MediaController.php).

**Live Preview Security:** Protect the preview route using EnsureAdminPreviewAccess:

Route::get('/admin/promotions/{promotion}/preview', \[PromotionController::class, 'preview'\]) -\>middleware(\['web', '[admin.noindex](admin.noindex)', '[admin.preview](admin.preview)'\]) -\>name('[admin.promotions.preview](admin.promotions.preview)'); 

**Time-Bounded Caching:** Bound cache TTL by the nearest state transition (starts\_at or ends\_at), up to a maximum of 300 seconds:

$ttl = min(300, $secondsUntilNextTransition); Cache::remember('marketing:active\_promo', $ttl, fn() =\> ...); 

The render view must perform final verification of starts\_at and ends\_at so an expired promotion is never displayed.

**Phase 5: Resilient Maintenance Mode \& Traffic Capture (with Dual-Format Export)**

Capture visitor traffic during maintenance without causing recursive failure loops.

**1. Cache-Backed Maintenance Middleware with Login Exception**

Create App\\Http\\Middleware\\[EnsureNotUnderMaintenance.php](EnsureNotUnderMaintenance.php):

Check maintenance status via short-TTL flat cache with fail-open fallback:

$isUnderMaintenance = false; try { $isUnderMaintenance = Cache::remember('system.maintenance\_mode', 30, function () { return (bool) DB::table('settings')-\>where('key', 'system.maintenance\_mode')-\>value('value'); }); } catch (\\Throwable $e) { // Fail open: assume live if database or database-backed cache is unavailable $isUnderMaintenance = false; } 

**Bypass Exceptions:**

Allow authenticated administrators (auth:web) to bypass maintenance.

**Unauthenticated Admin Access:** Explicitly exclude the verified administrator authentication routes from maintenance interception:

[admin.login](admin.login) (GET /admin/login)

[admin.login.submit](admin.login.submit) (POST /admin/login)

[admin.password.request](admin.password.request) (GET /admin/forgot-password)

[admin.password.email](admin.password.email) (POST /admin/forgot-password)

[admin.password.reset](admin.password.reset) (GET /admin/reset-password/{token})

[admin.password.update](admin.password.update) (POST /admin/reset-password)

Apply maintenance interception through the existing global web middleware path, verifying that the Livewire AJAX update endpoint (/livewire-ac3f421a/update) is covered. Add a route-specific assignment only if inspection proves it is not already covered by the global path.

Wrap visitor logging in a try/catch block:

try { $analyticsService-\>trackMaintenanceVisit(request()); } catch (\\Throwable $e) { // Suppress logging errors during database maintenance/outages } return response()-\>view('[errors.maintenance](errors.maintenance)', \[\], 503); 

**2. Maintenance Traffic Reporting \& Universal Export**

In App\\Http\\Controllers\\Admin\\[SystemHealthController.php](SystemHealthController.php):

Add a Maintenance Mode Traffic report panel showing hits, unique visitors, country breakdowns, and maintenance bounce percentages.

Implement streaming export in both CSV and XLSX formats (/admin/health/maintenance-visitors/export?format={csv|xlsx}) via ExportService, utilizing database cursors and formula-injection neutralization.

**Phase 6: Multi-Recipient Telegram Reminder Bot with Fully Configurable Windows**

Deliver lesson alerts to Abdallah and assistants via the Telegram Bot API using sequential execution, recipient-specific deduplication, verified post-acceptance logging, and **fully customizable, admin-configurable reminder lead times**.

**1. Schema Migration: Explicit Idempotency on booking\_events**

Ensure booking\_events enforces unique compound idempotency keys:

Schema::table('booking\_events', function (Blueprint $table) { $table-\>string('idempotency\_key', 120)-\>nullable()-\>after('event\_type'); $table-\>unique('idempotency\_key', 'booking\_events\_idempotency\_key\_unique'); }); 

**2. Dynamic Configuration \& Admin Settings Registry**

Store configuration in the settings table (group = 'telegram'):

telegram.bot\_token: Encrypted via Laravel's Crypt::encryptString.

telegram.notification\_chat\_ids: JSON array of recipient chat IDs (e.g., \["123456789", "987654321"\]).

telegram.reminders\_enabled: Boolean master toggle (true / false).

telegram.reminder\_windows: JSON array of integers representing the reminder lead times in minutes before lesson start time (start\_at\_utc). Default: \[1440, 60\] (24 hours and 1 hour).

Admins can configure any number of arbitrary reminder milestones (e.g., \[2880, 1440, 180, 60, 15\] for 48 hours, 24 hours, 3 hours, 1 hour, and 15 minutes) via the /admin/settings UI without code changes.

**3. Domain Notification Service with Dynamic Window \& Recipient Deduplication**

Update App\\Domains\\Notification\\Services\\[TelegramNotificationService.php](TelegramNotificationService.php):

Format dynamic deduplication keys based on booking ID, window offset (in minutes), and recipient chat ID: \\text{dedupeKey} = \\text{"reminder\\\_}\\{\\$booking-\>id\\}\\\_\\{\\$\\text{windowMinutes}\\}\\text{m\\\_}\\{\\$\\text{chatId}\\}" 

Dispatch HTTP calls sequentially with strict timeouts, recording success only after verified API acceptance (ok: true):

public function sendBookingReminderToRecipient(Booking $booking, string $chatId, int $windowMinutes): bool { $token = Crypt::decryptString(Setting::get('telegram.bot\_token')); $message = $this-\>formatReminderMessage($booking, $windowMinutes); $dedupeKey = "reminder\_{$booking-\>id}\_{$windowMinutes}m\_{$chatId}"; // 1. Check if recipient already received this specific milestone reminder if (DB::table('booking\_events')-\>where('idempotency\_key', $dedupeKey)-\>exists()) { return true; } // 2. Dispatch sequentially with strict timeout try { $response = Http::timeout(5) -\>post("[https://api.telegram.org/bot](https://api.telegram.org/bot){$token}/sendMessage", \[ 'chat\_id' =\> $chatId, 'text' =\> $message, 'parse\_mode' =\> 'Markdown', \]) -\>throw(); if ($response-\>json('ok') !== true) { Log::warning("Telegram API returned ok:false for chat {$chatId}"); return false; } } catch (\\Throwable $e) { Log::warning("Telegram reminder failed for chat {$chatId}: " . $e-\>getMessage()); return false; } // 3. Record success under compound idempotency key try { DB::table('booking\_events')-\>insert(\[ 'booking\_id' =\> $booking-\>id, 'event\_type' =\> 'telegram\_reminder\_sent', 'idempotency\_key' =\> $dedupeKey, 'performed\_by' =\> 'system', 'new\_data' =\> json\_encode(\[ 'chat\_id' =\> $chatId, 'window\_minutes' =\> $windowMinutes, 'accepted\_at' =\> now()-\>toIso8601String(), \]), 'created\_at' =\> now(), \]); } catch (\\Illuminate\\Database\\UniqueConstraintViolationException $e) { // Handled concurrently by another process } return true; } 

**4. Scheduled Reminder Command with Dynamic Window Iteration**

Update App\\Console\\Commands\\[SendBookingRemindersCommand.php](SendBookingRemindersCommand.php) (booking:send-telegram-reminders).

Schedule in routes/[console.php](console.php) to run every 5 minutes with overlap protection:

Schedule::command('booking:send-telegram-reminders') -\>everyFiveMinutes() -\>withoutOverlapping(10); 

**Execution Logic:**

Retrieve telegram.reminders\_enabled. If disabled, exit immediately.

Parse active telegram.reminder\_windows (array of integers) and telegram.notification\_chat\_ids.

For each configured milestone $windowMinutes:

Query confirmed bookings where start\_at\_utc falls within the eligibility window: \\text{start\\\_at\\\_utc} \\le \\text{now()} + \\$windowMinutes \\text{ minutes AND } \\text{start\\\_at\\\_utc} \> \\text{now()} 

For each eligible booking, check each recipient chat ID and dispatch sendBookingReminderToRecipient().

Because deduplication is compound (bookingId + windowMinutes + chatId), bookings that already received their reminder for this specific milestone are skipped instantly at step 1.

**5. Admin Settings Interface**

In App\\Http\\Controllers\\Admin\\[SettingController.php](SettingController.php) and admin settings Blade templates:

Provide a dynamic UI list manager allowing administrators to add, edit, or delete reminder milestones (with input fields accepting hours or minutes, serialized as a JSON array of minutes in telegram.reminder\_windows).

Provide a multi-input manager for telegram.notification\_chat\_ids.

Include a "Send Test Reminder" button to verify bot credentials and chat ID connectivity directly from the admin panel.

**Phase 7: External Resource Linking \& Total "Zero-Article" Codebase Eradication**

Execute a complete, 100% total eradication of the terms article and articles across every layer of the project. Execute the refactor in three disciplined, testable stages: (1) Migration \& Schema Reset, (2) Class, Service \& Variable Refactoring, and (3) Views, Routes \& UI String Auditing.

**1. External Resource Links \& Scheme Validation**

Create migration adding external\_url (varchar 500, nullable) to resources.

Update App\\Domains\\Resources\\Models\\[Resource.php](Resource.php) and App\\Http\\Controllers\\Admin\\[ResourceController.php](ResourceController.php):

Scheme Validation: Validate that external\_url uses strictly https:// (or http:// if explicitly allowed). Reject javascript:, data:, file:, protocol-relative URLs, and unapproved schemes before persisting.

In ResourceController@download, if external\_url is present, record the resource\_downloads lead entry and return an HTTP redirect to the external URL.

**2. Stage 1: Base Migrations \& Testing Database Clean Reset**

Directly rename and refactor existing migration files in database/migrations/:

[xxxx\_xx\_xx\_xxxxxx\_create\_articles\_table.php](xxxx_xx_xx_xxxxxx_create_articles_table.php) \\longrightarrow [xxxx\_xx\_xx\_xxxxxx\_create\_blogs\_table.php](xxxx_xx_xx_xxxxxx_create_blogs_table.php)

Inside migrations, define table blogs (not articles).

Rename revision tables: blog\_revisions (not article\_revisions).

Rename redirect tables: blog\_slug\_redirects (not article\_slug\_redirects).

Rename foreign keys: Change all article\_id columns to blog\_id. Update composite keys and indexes to match (blogs\_slug\_unique, blog\_revisions\_blog\_id\_foreign, etc.).

Reset and seed the testing database cleanly:

php artisan migrate:fresh --seed 

*(Do NOT run php artisan migrate incrementally during this phase; use migrate:fresh --seed exclusively).*

**3. Stage 2: Factories, Seeders, Models \& Domain Code Refactor**

**Factories \& Seeders:**

database/factories/[ArticleFactory.php](ArticleFactory.php) \\longrightarrow database/factories/[BlogFactory.php](BlogFactory.php) (Model: App\\Domains\\CMS\\Models\\Blog::class).

database/seeders/[ArticleSeeder.php](ArticleSeeder.php) \\longrightarrow database/seeders/[BlogSeeder.php](BlogSeeder.php).

In database/seeders/[DatabaseSeeder.php](DatabaseSeeder.php), call BlogSeeder::class.

**Domain Models:**

App\\Domains\\CMS\\Models\\[Article.php](Article.php) \\longrightarrow [Blog.php](Blog.php) (Property: $table = 'blogs';).

[ArticleRevision.php](ArticleRevision.php) \\longrightarrow [BlogRevision.php](BlogRevision.php).

[ArticleSlugRedirect.php](ArticleSlugRedirect.php) \\longrightarrow [BlogSlugRedirect.php](BlogSlugRedirect.php).

In [Administrator.php](Administrator.php), update relation: blogs(): HasMany referencing author\_id.

**Controllers, Form Requests \& Domain Services:**

app/Http/Controllers/[ArticleController.php](ArticleController.php) \\longrightarrow [BlogController.php](BlogController.php).

app/Http/Controllers/Admin/[ArticleController.php](ArticleController.php) \\longrightarrow Admin/[BlogController.php](BlogController.php).

[StoreArticleRequest.php](StoreArticleRequest.php) \\longrightarrow [StoreBlogRequest.php](StoreBlogRequest.php).

[UpdateArticleRequest.php](UpdateArticleRequest.php) \\longrightarrow [UpdateBlogRequest.php](UpdateBlogRequest.php).

App\\Domains\\CMS\\Services\\[ArticleService.php](ArticleService.php) \\longrightarrow App\\Domains\\CMS\\Services\\[BlogService.php](BlogService.php).

**Variable \& Parameter Eradication:**

Method signatures, parameters, and local variables across all controllers, requests, services, and commands:

Replace $article with $blog.

Replace $articles with $blogs.

Replace $articleId with $blogId.

**4. Stage 3: Blade Templates, Routes \& Public UI Singular Invariant**

**Views Directory Migration:**

resources/views/articles/ \\longrightarrow resources/views/blog/.

resources/views/admin/articles/ \\longrightarrow resources/views/admin/blog/.

**Controller View Returns \& Template Variables:**

Update return statements: view('[blog.index](blog.index)'), view('[blog.show](blog.show)'), view('[admin.blog.index](admin.blog.index)').

Update Blade loops: @foreach($blogs as $blog) and $blog-\>title, $blog-\>slug, $blog-\>body.

Telemetry Target: Instrument \<div data-section-id="blog-content"\> in resources/views/blog/[show.blade.php](show.blade.php).

**Route Registry (Zero Legacy Redirects):**

Because the site is unlaunched, delete all legacy /articles paths and redirect closures completely.

Define clean, canonical routes in routes/[web.php](web.php) (using the confirmed literal '[admin.role](admin.role):super\_admin,admin'):

// Public routes Route::get('/blog', \[App\\Http\\Controllers\\BlogController::class, 'index'\])-\>name('[blog.index](blog.index)'); Route::get('/blog/{slug}', \[App\\Http\\Controllers\\BlogController::class, 'show'\])-\>name('[blog.show](blog.show)'); // Admin routes Route::prefix('admin/blog')-\>name('[admin.blog](admin.blog).')-\>middleware(\['web', '[admin.noindex](admin.noindex)', 'auth:web', '[admin.role](admin.role):super\_admin,admin'\])-\>group(function () { Route::get('/', \[App\\Http\\Controllers\\Admin\\BlogController::class, 'index'\])-\>name('index'); Route::get('/create', \[App\\Http\\Controllers\\Admin\\BlogController::class, 'create'\])-\>name('create'); Route::post('/', \[App\\Http\\Controllers\\Admin\\BlogController::class, 'store'\])-\>name('store'); Route::get('/{blog}/edit', \[App\\Http\\Controllers\\Admin\\BlogController::class, 'edit'\])-\>name('edit'); Route::put('/{blog}', \[App\\Http\\Controllers\\Admin\\BlogController::class, 'update'\])-\>name('update'); Route::delete('/{blog}', \[App\\Http\\Controllers\\Admin\\BlogController::class, 'destroy'\])-\>name('destroy'); Route::get('/{blog}/preview', \[App\\Http\\Controllers\\Admin\\BlogController::class, 'preview'\])-\>name('preview'); }); 

**Strict Public \& Admin UI Singular Invariant ("Blog", Never "Blogs"):**

Audit all public and administrative templates, navigation menus, headers, breadcrumbs, buttons, and metadata.

Replace all occurrences of "Article" or "Articles" with **"Blog"**.

**Strict Check:** If any label, heading, or navigation link currently renders as the plural string "Blogs", rewrite it immediately to the singular **"Blog"** (e.g., Navbar link: "Blog"; Breadcrumb: "Home \> Blog \> Title"; Page Heading: "Blog"; Button: "Create Blog Post").

**5. Automated Tests Refactor**

Rename test files and classes:

tests/Feature/[ArticleTest.php](ArticleTest.php) \\longrightarrow tests/Feature/[BlogTest.php](BlogTest.php)

tests/Feature/Admin/[ArticleManagementTest.php](ArticleManagementTest.php) \\longrightarrow tests/Feature/Admin/[BlogManagementTest.php](BlogManagementTest.php)

Update all test methods, variables, and route calls:

Rename test\_admin\_can\_create\_article() to test\_admin\_can\_create\_blog().

Replace $this-\>get(route('[articles.index](articles.index)')) with $this-\>get(route('[blog.index](blog.index)')).

Update assertions to inspect table blogs instead of the legacy table.

**Phase 8: Verification, Test Suite Execution \& Output Report**

Upon completing the implementation, execute the following verification steps:

**1. Zero-Article String Audit Gate (With Semantic HTML Exemption)**

Execute targeted case-insensitive searches across the project:

Codebase logic directories:

git grep -i "article" -- app/ bootstrap/ config/ database/ routes/ tests/ 

*Requirement:* Zero results.

Blade view templates (filtering out standard semantic HTML5 tags):

git grep -i "article" -- resources/views/ | grep -v -E "\<\\/?article\[ \>\]" 

*Requirement:* Zero results outside of standard HTML5 \<article\> elements.

**2. Strict UI Singular "Blog" Audit Gate**

Execute a search across view templates to ensure no plural "Blogs" strings exist in UI copy:

git grep -E "\>\[\[:space:\]\]\*Blogs\[\[:space:\]\]\*\<" -- resources/views/ 

*Mandatory Requirement:* Zero occurrences of "Blogs" in HTML tags, navigation, or headings.

**3. Database Schema Integrity**

Confirm schema reproducibility on clean fixtures:

php artisan migrate:fresh --seed 

**4. Automated Test Suite Verification**

Run the full test suite twice consecutively to guarantee that child worker processes terminate cleanly, dynamic reminder commands pass with zero overlap, country segmentation and bounce rate assertions pass, all renamed blog tests pass, and fixtures leave zero residual test pollution:

php artisan test --filter=BufferExpandedConcurrencyLockTest php artisan test --filter=MariaDbConcurrencyVerificationTest php artisan test php artisan test 

*Mandatory Requirement:* All tests across both full runs must exit with code 0 without manual database truncation between runs.

**5. Dual-Format Export Verification**

Verify that all export routes (Overview, Countries, Section Dwell, Cashier Billing, Reconciliation Diagnostics, Maintenance Traffic) return valid, uncorrupted files under both ?format=csv and ?format=xlsx without memory exhaustion errors, and confirm the inclusion of the bounce rate percentage column.

**6. Local Frontend Bundle Build**

Verify asset compilation on the development machine:

npm run build 

*(Do NOT attempt to run npm run build on Hostinger shared runtimes; deploy compiled assets directly to public/build/).*

**7. Summary Deliverable**

Provide a concise change log detailing:

Modified and created files across App\\Domains\\\*.

Base migration refactors and schema resets (blogs, blog\_revisions, blog\_slug\_redirects, blog\_id foreign keys, and daily\_country\_metrics.bounced\_sessions\_count).

Confirmation of canonical pricing sync, presets binding (Diagnostic $25, Foundation $280/$255, Fluency $390/$365, Maintenance $48, Advanced $28), 48-hour diagnostic credit automation, and 75/100-day Cairo midnight expiration calculations (expiration\_date).

Confirmation of the registered /admin/analytics/countries route, sidebar link, confirmed event taxonomy names, and schema-accurate bounce rate analytics.

Confirmation of the zero-result git grep audit outputs (with semantic HTML \<article\> tags safely preserved).

Confirmation of the singular "Blog" UI audit across all views.

Confirmation of the dynamic Telegram reminder windows implementation (telegram.reminder\_windows) and admin management interface.

Test suite execution output confirming repeatable green test runs, export integrity, and clean connection teardowns.