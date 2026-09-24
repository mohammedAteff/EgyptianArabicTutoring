**Master Engineering Specification (v2.0 — Final Frozen Production Master)**

**Student Management, Forms Engine \& Operational CRM**

**Prime Directive: Brownfield Integrity \& Multi-Source Forensic Discovery**

This project operates inside an active, brownfield production Laravel application with existing database records, models, calendar availability services, routes, and views. Past implementation context, local scratchpads, or commit history may exist in the workspace.

**Non-Negotiable Operating Rules**

**Mandatory Multi-Source Forensic Discovery Before Code Execution:** You are strictly forbidden from creating files, modifying code, running migrations, or executing Artisan commands until Phase 0 is complete. Inspect the codebase and accessible workspace context (recent commits, workspace notes, local documentation) to verify existing architectural patterns and partial implementations.

**Workspace Context Availability Rule:** Do not claim to have inspected workspace history, logs, or scratchpads unless those sources are directly accessible in the current execution environment. If inaccessible, explicitly state so in the Phase 0 report and continue using verified codebase files only. Never inspect .env, credentials, secret keys, or private tokens for historical discovery.

**Reconcile, Do Not Duplicate or Overwrite:** If a service, helper, or component already exists (e.g., TimezoneDisplayService, an availability calculator, booking traits, or RBAC gates/policies), audit its implementation, isolate its failure modes, and refactor or extend it cleanly. Never introduce duplicate parallel structures or competing authorization systems.

**Strict Fail-Closed Vendor/Version Execution:** Database-specific operations (transaction isolation setting, session variables, lock syntax, indexing, and online DDL mechanics) are conditional. Phase 0 must identify the exact database vendor (mysql, mariadb, pgsql) and server release version. All vendor-specific operations must run through an explicit DatabaseCapability service that fails closed with UnsupportedDatabaseVendorException on unverified engines. Never default or fall back silently to MySQL.

**Brownfield Lock Targets:** Do not invent generic placeholder tables (e.g., scheduling\_resources) if an existing stable entity (such as a primary tutor row in users or a schedules table) already serializes tutor availability. Identify and use that row for pessimistic locking. All illustrative SQL in this document must be substituted with the exact canonical entity identified during Phase 0.

**Follow Established Repository Conventions:** Conform strictly to codebase conventions: Primary Key strategy (auto-incrementing BigIncrements vs. ULID/UUID), reactive frontend lifecycle patterns (Livewire v2/v3, [Alpine.js](Alpine.js), Blade), coding styles, and translation dictionary conventions.

**Zero External Paid Dependencies:** Do not introduce Twilio, Auth0, Clerk, Firebase, SendGrid OTP, or paid verification APIs. All authentication, identity verification, and business logic must run entirely within native Laravel capabilities and internal database state.

**Non-Destructive Database Evolution:** Write reversible migrations with explicit, tested down() methods. Never drop production columns, truncate populated tables, or make irreversible assumptions about legacy data.

**Mandatory Phase 0: Forensic Audit \& Reconciliation Gate**

Before writing or modifying any code, migrations, or configuration, execute a comprehensive read-only audit of the repository and output the reconciliation report.

PHASE 0 AUDIT GATE ┌────────────────────────────────────────────────────────────────────────┐ │ 1. Inspect Workspace History \& Accessible Git Log (git log -n 20) │ │ 2. Audit DB Vendor, Exact Version, Schema Migrations \& PK Conventions │ │ 3. Audit Existing Models, Relationships, Roles \& Authorization Policies│ │ 4. Trace Booking Flow, Availability Calculators \& Timezone Services │ │ 5. Audit Slot Identity Architecture (Stateless Signed Token vs DB Row) │ │ 6. Identify Canonical Scheduling Resource Entity for Locking │ │ 7. Audit Existing Frontend Stack (Livewire, Alpine, Blade, Tailwind) │ │ 8. Output Reconciliation Report \& Wait for Review Before Modifying Code│ └────────────────────────────────────────────────────────────────────────┘ 

**1. Inspection Checklist**

**Workspace \& Git History:** Review accessible commits (git log -n 20 --stat) and configuration notes to identify partial implementations or established decisions.

**Database Vendor, Version \& DDL Capabilities:** Inspect config/[database.php](database.php) and run-time database connection to confirm the exact RDBMS vendor (MySQL, MariaDB, or PostgreSQL) and server version. Determine whether online DDL constraints permit metadata-lock-free foreign key creation without full table copies under the active engine configuration.

**Dependencies \& Configuration:** Inspect [composer.json](composer.json), [package.json](package.json), config/[app.php](app.php), config/[auth.php](auth.php), config/[session.php](session.php), and config/[database.php](database.php).

**Database State \& PK Conventions:** Audit all files in database/migrations/ to understand table schema evolution, foreign key rules, indexes, and whether the application standardizes on bigIncrements, ulid, or uuid.

**Models, Relationships \& Existing Authorization:** Audit app/Models/ (Booking, User, Schedule, Availability, customer/session models). Inspect existing authorization layers: app/Policies/, app/Providers/[AuthServiceProvider.php](AuthServiceProvider.php), spatie/laravel-permission (if present), or custom middleware gates to ensure new roles integrate cleanly.

**Slot Identity Provenance:** Inspect the availability engine to identify whether availability slots are modeled as persisted database records, ephemeral cache entries, or stateless cryptographically signed tokens. Reconcile this implementation rather than introducing a divergent pattern.

**Booking Pipeline \& Lock Target:** Trace how calendar availability, slot computation, booking creation, and rescheduling operate. Identify the stable entity representing the tutor/schedule to serve as the FOR UPDATE lock target. Inspect TimezoneDisplayService, IP/country detection middleware, and booking notification/mail triggers.

**Routing \& Middleware:** Audit routes/[web.php](web.php), routes/[api.php](api.php), and app/Http/Middleware/ to verify CSRF, cookie, and session middleware stacks.

**Frontend Architecture:** Verify Livewire version (v2 vs. v3), [Alpine.js](Alpine.js) usage, Blade layouts, and Tailwind configuration.

**2. Required Phase 0 Output**

Deliver a concise Reconciliation Report documenting:

Confirmed Laravel, Livewire, and frontend framework versions.

Confirmed production database vendor and exact engine release version.

Established primary key convention (auto-incrementing integers, UUIDs, or ULIDs).

Existing models, services, authorization policies, and traits that overlap with this specification.

Current slot identity implementation pattern (stateless token vs. persisted table).

Designated row/table target for pessimistic scheduling locks.

Concrete integration points where new functionality will hook into existing controllers/services.

Accessibility statement regarding workspace history/logs.

**CRITICAL GATE:** Do not proceed past Phase 0 until the Phase 0 Reconciliation Report is reviewed and approved.

**Subsystem Architecture \& Invariants**

\[STUDENT\] │ ┌───────────────────────────────┼───────────────────────────────┐ ▼ ▼ ▼ \[Identity \& Access\] \[Forms \& Profile\] \[Sessions \& Ledger\] • DOB + 2/3 Normalization • Dynamic Form Builder • Universal Lock Hierarchy: • 180-Min Session Boundary • Immutable after 1st submission Resource -\> Student -\> • Dual-Layer Rate Limiting record (draft or submitted) Booking -\> Package -\> • Atomic Failure Tracking • Base-Version Optimistic Lock Ledger/Payment • No Remember-Me Auth • ChunkById Stream Export • SlotResolver Server-Auth • Column-Type Compliant • Separate Canonical Answers • Two-Phase DB Isolation Privacy Anonymization • Frozen Version Read-Only • Business-Date FIFO Credits • PII-Blinded Audit Trail • Reschedule (≥24h Fixed UTC) • Zero Ledger Consumption on Reschedule Action • State-Validated Cancellation with Application Idempotency 

**Module 1: Critical Bug Fixes (Timezone \& Booking Correctness)**

Audit the current booking engine and timezone service first. Fix these issues before introducing new layers.

**Bug A: IANA-to-ISO Country Flag Resolution**

**Defect:** Valid IANA timezones frequently resolve to a fallback globe icon because the timezone-to-country mapping array is incomplete, stale, or failing during string parsing.

**Implementation:**

In TimezoneDisplayService (or equivalent class), resolve the country code dynamically using PHP's native DateTimeZone::getLocation():$tz = new \\DateTimeZone($ianaTimezone); $location = $tz-\>getLocation(); $countryCode = $location ? $location\['country\_code'\] : null; 

Maintain a targeted static override map *only* for known edge cases, legacy aliases, or multi-territorial exceptions where getLocation() returns ?? or non-standard designations.

Return the fallback globe icon strictly for non-territorial or system zones (UTC, GMT, or Etc/\*).

Country flags represent presentation metadata only; never use the resolved country code to infer client billing or operational rules.

**Bug B: Authoritative Manual Timezone Override \& Slot Binding**

**Defect:** When a user manually selects an alternative timezone in the booking UI, the system continues to compute availability slots or persist appointments based on the IP-detected timezone.

**Implementation:**

Decouple detected\_country\_code / detected\_timezone (used exclusively for initial default display) from active\_booking\_timezone.

When a user selects a timezone:

Validate the IANA string via DateTimeZone::listIdentifiers().

Store active\_booking\_timezone in session/component state.

Recompute and re-render available calendar slots using the selected timezone’s local offset against server availability.

**Slot Identity Invariant:**

A slot\_id must identify a server-authorized availability interval and be bound server-side to:

Canonical scheduling resource / tutor ID;

UTC start instant;

UTC end instant / duration;

Applicable availability policy.

The server must reject any slot\_id that does not exist, is expired, belongs to another resource, falls outside current availability, conflicts with an existing booking, or violates policy.

The client must never generate, manipulate, or submit arbitrary UTC timestamps or client-fabricated slot identifiers.

Persist bookings strictly in UTC alongside the student's selected active\_booking\_timezone for presentation.

Do not perform arithmetic offset adjustments on dates. Always use PHP DateTimeImmutable with IANA DateTimeZone to prevent Daylight Saving Time (DST) calculation errors.

**Module 2: Passwordless Student Identity \& Access Layer**

**1. Database Schema (students table)**

Create a dedicated students table aligned with the project's primary key conventions:

id: Matches project primary key convention derived in Phase 0 (BigIncrements, UUID, or ULID).

first\_name: string.

last\_name: string.

name\_normalized: string, indexed.

email: string, nullable (supports legacy bookings lacking email), indexed.

email\_normalized: string, nullable, indexed (non-unique).

phone: string, nullable, indexed.

phone\_normalized: string, nullable, indexed (non-unique).

date\_of\_birth: date, nullable (nullable at DB level for legacy backfill; strictly required for active student verification).

preferred\_timezone: string, nullable (valid IANA timezone string; presentation preference only, never overrides UTC booking instants).

identity\_status: enum (verified, legacy\_unverified, merged).

possible\_duplicate\_of\_student\_id: nullable FK to [students.id](students.id) (nullOnDelete()). Advisory only; does not participate in authentication.

merged\_into\_student\_id: nullable FK to [students.id](students.id) (nullOnDelete()).

internal\_notes: text, nullable (strictly staff-only).

created\_at, updated\_at, deleted\_at: timestamps.

**2. Canonical Identity Normalization (StudentIdentityService)**

Implement normalization once in StudentIdentityService and reuse across registration, authentication, deduplication, and admin search:

**Name Normalization:**

Apply Unicode NFKC normalization.

Convert to Unicode-aware lowercase (mb\_strtolower).

Remove Unicode punctuation and normalize multiple whitespace runs into a single ASCII space. Trim edges.

Preserve characters across all alphabets (e.g., Arabic, Latin, Cyrillic). Do not transliterate Arabic to Latin for matching.

Construct name\_normalized as: normalize(first\_name . ' ' . last\_name). When verifying submitted input, normalize the submitted string and assert exact equality against name\_normalized.

**Email Normalization:**

Trim surrounding whitespace and lowercase the entire string.

Retain dots and plus tags in the local-part (do not strip provider-specific features like Gmail dots).

**Phone Normalization:**

Normalize to canonical E.164 format (e.g., +201012345678).

Require explicit country context from a country selector or previously saved profile; do not guess or infer country from client IP.

**3. Low-Assurance Student Authentication \& Web Session Security**

**Verification Rule:**

date\_of\_birth is mandatory and must match exactly; **AND**

At least two of the following normalized fields must match:

name\_normalized

email\_normalized

phone\_normalized

**Authentication Query Scope Invariant:**Student::where('identity\_status', 'verified') -\>whereNull('deleted\_at') // matching conditions Records with identity\_status = 'legacy\_unverified' or 'merged' must never satisfy authentication.

**Authentication Failure Uniformity:**

Use a single generic user-facing message: *"The provided student details could not be verified."*

Never use field-specific early exits or reveal which identifiers matched.

Do not disclose candidate counts or use different redirect destinations for different failure reasons.

Do not log raw submitted PII.

Layered rate limiting and stateful tracking are the authoritative defense against enumeration.

If multiple student records satisfy the matching rule, authentication must fail closed and flag an administrative review warning for ambiguous identity. Never authenticate into the first matching record.

**Dual-Layer Rate Limiting \& Consecutive Failure Tracking**

**Globally-Keyed Identity Tiers:** Identity-fingerprint limiters must be keyed globally by HMAC digest across all IP addresses using an application secret (STUDENT\_AUTH\_HMAC\_KEY) to prevent proxy rotation attacks against a victim:RateLimiter::for('student-auth-email', function (Request $request) { $hmac = hash\_hmac('sha256', (string) $request-\>email\_normalized, config('services.student\_auth.hmac\_key')); return Limit::perMinutes(15, 5)-\>by("student-auth-email:{$hmac}"); }); Enforce independent limiters:

Max 5 attempts per 15 minutes per HMAC(normalized\_email) (Global).

Max 5 attempts per 15 minutes per HMAC(normalized\_phone) (Global).

Max 5 attempts per 15 minutes per HMAC(DOB + normalized\_email) (Global).

Max 5 attempts per 15 minutes per HMAC(DOB + normalized\_phone) (Global).

**Sequential State Machine (StudentAuthAttemptTracker):** Laravel's Limit::perMinutes() operates as a sliding window across all attempts, not a sequential state machine. To enforce the 15-minute cooldown after 3 consecutive failures:

Maintain an atomic cache/database counter tracking consecutive failures per normalized identity fingerprint.

On any authentication failure: Atomically increment consecutive failure counter. If counter \\ge 3, set cooldown\_until = now()-\>addMinutes(15).

On any authentication success: Atomically reset the consecutive failure counter to 0 for all fingerprints submitted in that attempt.

Requests arriving while now() \< cooldown\_until are immediately rejected with HTTP 429.

**Independent Client IP Limits:**

Max 5 attempts per 10 minutes per IP address.

Max 50 attempts per 24 hours per IP address.

**Web Session Security \& Elimination of Persistent Auth**

**No Persistent Student Authentication / No Remember-Me Credential:** The student guard must not issue, honor, or accept a remember\_token or long-lived authentication cookies. The 180-minute session boundary is absolute; pass $remember = false explicitly at all authentication call sites.

**Session Lifecycle:** All state-changing web requests (POST, PATCH, DELETE) enforce CSRF protection. Student session cookies must enforce Secure, HttpOnly, and SameSite=Lax (or Strict).

Upon successful authentication, regenerate the session ID ($request-\>session()-\>regenerate()) to prevent session fixation.

Store auth state under a dedicated session namespace: student\_id, student\_authenticated\_at, and student\_auth\_expires\_at = now() + 180 minutes.

**Per-Request Live Status Verification:** Middleware (EnsureStudentAuthenticated) must verify both the expiration timestamp and the live database status on every request (cached with a 30-second TTL):$studentId = session('student\_id'); $student = Cache::remember("student\_auth\_check\_{$studentId}", 30, function () use ($studentId) { return Student::where('id', $studentId) -\>where('identity\_status', 'verified') -\>whereNull('deleted\_at') -\>first(); }); if (!$student || now()-\>greaterThanOrEqualTo(session('student\_auth\_expires\_at'))) { $request-\>session()-\>forget(\['student\_id', 'student\_authenticated\_at', 'student\_auth\_expires\_at'\]); return redirect()-\>route('[student.login](student.login)')-\>withErrors(\['auth' =\> 'The session has expired or is invalid.'\]); } 

On logout or expiry, flush only student\_\* session keys. Do not call $request-\>session()-\>flush().

**4. Scoped Student Access Boundary**

**Permitted Access:** Own upcoming and historical bookings, self-service rescheduling, personal credit/session balance summary, student-facing forms/questionnaires, and payment status summaries.

**Prohibited Access:** Internal admin/tutor notes, internal duplicate flags, audit logs, raw payment provider transaction references, private staff payment notes, administrative settings, and identity modification actions.

**Identity Immutability:** Students cannot edit core identity fields (date\_of\_birth, name, email, phone) within the student portal. Identity modifications require administrative intervention.

**Timezone Display Hierarchy:**

students.preferred\_timezone (if populated).

Fallback to bookings.active\_booking\_timezone.

Fallback to application default business timezone.

**5. Identity Match Classification, Admin Merge Tool \& Privacy Erasure**

**Match Tiers**

EXACT\_MATCH: Exact DOB + at least 2 normalized identifiers -\> Auto-link permitted.

STRONG\_MATCH: Exact DOB + exactly 1 normalized identifier -\> Do NOT auto-link. Set possible\_duplicate\_of\_student\_id and flag for review.

POSSIBLE\_DUPLICATE: Shared normalized email or phone with conflicting/missing DOB or name -\> Do NOT auto-link. Set possible\_duplicate\_of\_student\_id and flag for review.

AMBIGUOUS: Multiple records match criteria -\> Do NOT auto-link. Flag for manual resolution.

**Append-Only Invariant Under Profile Merges**

Append-only means historical financial and event facts are immutable. A merge operation may update ownership/reference foreign keys (student\_id) required to associate historical records with the surviving student, but **must never alter amounts, credit changes, event types, timestamps, transaction references, or other historical business facts**. All foreign-key reassignments must occur inside the merge transaction under global lock order and be recorded in audit\_logs.

**Universal Admin Merge Tool (Owner Only)**

The Merge Tool **must conform strictly to the Universal Global Lock Hierarchy**. It locks all 5 tiers in order before re-linking:

MERGE TOOL LOCK SEQUENCE 1. Identify all affected Canonical Scheduling Resources across both students' bookings. 2. Lock all identified resources in ASCENDING ID order. 3. Lock Student A and Student B in ASCENDING ID order. 4. Lock all affected Bookings in ASCENDING ID order. 5. Lock all affected Student Packages in ASCENDING ID order. 6. Lock all affected Payment Records and Session Ledger Entries in ASCENDING ID order. 7. Re-link child foreign keys (ownership update only) \& flatten pointers. 

**Pessimistic Locking Phase:**

Find all distinct resource IDs associated with active/held bookings of both Student A and Student B. Lock those canonical resource rows in deterministic ascending ID order using the entity identified in Phase 0.

Lock both student rows in deterministic ascending ID order:$ids = \[$primaryStudentId, $secondaryStudentId\]; sort($ids); $lockedStudents = Student::whereIn('id', $ids)-\>orderBy('id', 'asc')-\>lockForUpdate()-\>get(); 

Lock all child bookings in ascending ID order: SELECT id FROM bookings WHERE student\_id IN (...) ORDER BY id ASC FOR UPDATE.

Lock all child student packages in ascending ID order: SELECT id FROM student\_packages WHERE student\_id IN (...) ORDER BY id ASC FOR UPDATE.

Lock all child payment records and session ledger entries in ascending ID order:DB::select('SELECT id FROM payment\_records WHERE student\_id IN (...) ORDER BY id ASC FOR UPDATE'); DB::select('SELECT id FROM session\_ledger\_entries WHERE student\_id IN (...) ORDER BY id ASC FOR UPDATE'); 

**Re-linking Phase:** Re-link child relationships to the primary student: bookings, form submissions, packages, payments, refunds, session ledger entries, and reschedule history.

**Pointer Flattening:** Update all existing records where merged\_into\_student\_id = $secondaryStudent-\>id to point directly to $primaryStudent-\>id to guarantee single-hop resolution:Student::where('merged\_into\_student\_id', $secondaryStudent-\>id) -\>update(\['merged\_into\_student\_id' =\> $primaryStudent-\>id\]); 

**Tombstone \& Invalidation:** Retain the secondary student record with identity\_status = 'merged', merged\_into\_student\_id = $primaryStudent-\>id, and soft-delete it (deleted\_at = now()).

Invalidate auth caches (Cache::forget("student\_auth\_check\_{$id}")) for both student records.

Log the merge event in audit\_logs without storing sensitive PII in plain text.

**Data Deletion \& Privacy Erasure Support:** Students with historical bookings, packages, payments, or ledger entries must not be physically hard-deleted. Removal uses soft deletion (deleted\_at).

*GDPR/Privacy Erasure Support:* Implement an authorized anonymization/erasure workflow that removes or anonymizes personal data from application-controlled records while preserving legally and operationally required financial and audit records. The implementation must be designed to support applicable data-erasure requirements; legal compliance is not inferred solely from the software implementation.

*Semantic Column Anonymization (No Type Conflicts):* Cryptographic hashes must never be inserted into strongly typed non-string columns (such as DATE) or semantically validated fields (such as E.164 phone numbers). The workflow must execute:

first\_name: Set to 'Anonymized'.

last\_name: Set to 'Student'.

name\_normalized: Set to 'anonymized student'.

email: Set to deterministic pseudonymous internal email: 'anonymized\_' . $student-\>id . '@[internal.invalid](internal.invalid)'.

email\_normalized: Set to 'anonymized\_' . $student-\>id . '@[internal.invalid](internal.invalid)'.

phone: Set to NULL.

phone\_normalized: Set to NULL.

date\_of\_birth: Set to NULL.

internal\_notes: Set to NULL.

form\_answers: Redact/scrub all PII answers; purge values submitted for non-anonymized identity fields.

audit\_logs: Redact personal identification strings from historical JSON payloads while preserving the event shell (actor, timestamp, action, event\_uuid).

**Module 3: Student Self-Service Area \& Rescheduling**

**1. Student Dashboard Views (/student/...)**

**Upcoming Sessions Card:** Next session date and time rendered in the student’s preferred timezone, tutor name, meeting link, and session status (confirmed, held).

**Reschedule Trigger:** Visible directly on upcoming confirmed session cards that meet the policy window.

**Session Ledger Summary:** Total sessions purchased, sessions completed/attended, available remaining session credits, and package status.

**Profile Questionnaires Card:** List of assigned dynamic forms with completion status badges (Draft, Submitted, Update Available).

**2. Self-Service Rescheduling Engine**

**Fixed UTC Duration Policy Check:** Self-service rescheduling is permitted if the session start time is \\ge 24 hours in the future relative to now('UTC'). Evaluate this strictly using fixed-duration UTC subtraction:$canReschedule = now('UTC')-\>addHours(24)-\>lessThanOrEqualTo($booking-\>start\_time\_utc); Never use subDay() or calculate against non-UTC instances to prevent DST shift errors. If \< 24 hours, disable the reschedule action and instruct the student to contact the tutor directly.

**Lifecycle State Model:**

Rescheduling a session must not change the booking lifecycle status to rescheduled\_by\_student. The status remains confirmed.

Track the reschedule event in session\_reschedules: id, booking\_id, actor\_type (student, admin, system), actor\_id (nullable), old\_start\_time\_utc, new\_start\_time\_utc, old\_timezone, new\_timezone, idempotency\_key (unique string), ip\_address, created\_at.

Set a boolean flag on the booking: admin\_reconfirmation\_needed = true.

**Server-Side Ownership Re-derivation:** Reactive components (Livewire or controller actions) handling booking or reschedule actions must never trust client-supplied public IDs ($bookingId, $slotId). Every action invocation must re-verify that the booking belongs to session('student\_id') directly from the database:$booking = Booking::where('id', $this-\>bookingId) -\>where('student\_id', session('student\_id')) -\>firstOrFail(); 

**3. Unified Booking + Credit Transaction \& Concurrency Controls**

**Composite Database Indexes**

CREATE INDEX idx\_bookings\_resource\_status\_window ON bookings (resource\_id, status, start\_time\_utc, end\_time\_utc); CREATE INDEX idx\_ledger\_package\_student ON session\_ledger\_entries (student\_package\_id, student\_id); CREATE INDEX idx\_student\_packages\_student\_status ON student\_packages (student\_id, status, expiration\_date); 

**Universal Global Lock Hierarchy**

To eliminate cross-transaction deadlocks, all transactions across the entire application touching these entities must acquire row locks in this strict, non-negotiable order:

**Canonical Scheduling Resource(s)** (sorted ascending by canonical resource ID; substitute the exact table/model confirmed in Phase 0)

**Student Row(s)** (sorted ascending by student\_id)

**Booking Row(s)** (sorted ascending by booking\_id or window checks)

**Student Package Row(s)** (sorted ascending by package\_id)

**Session Ledger Entries \& Payment Records** (sorted ascending by primary key)

DB::transaction(..., 3) is strictly a recovery defense against transient deadlocks; it is never a substitute for consistent lock ordering.

**Fail-Closed Two-Phase Isolation Lifecycle via DatabaseCapability**

Database isolation queries and vendor-specific syntax are separated into two distinct lifecycles: connection-level preparation before DB::transaction() and transaction-scoped execution within the transaction block. Unverified or unrecognized database vendors must fail immediately with UnsupportedDatabaseVendorException. In MariaDB, the compatibility prefix 5.5.5- is explicitly stripped before semver comparison:

namespace App\\Services\\Database; use Illuminate\\Support\\Facades\\DB; use App\\Exceptions\\UnsupportedDatabaseVendorException; class DatabaseCapability { protected string $driver; protected string $vendor; protected string $rawVersion; protected string $cleanVersion; public function \_\_construct() { $connection = DB::connection(); $this-\>driver = $connection-\>getDriverName(); $pdo = $connection-\>getPdo(); $this-\>rawVersion = (string) $pdo-\>getAttribute(\\PDO::ATTR\_SERVER\_VERSION); if ($this-\>driver === 'pgsql') { $this-\>vendor = 'pgsql'; $this-\>cleanVersion = $this-\>rawVersion; } elseif ($this-\>driver === 'mysql') { if (stripos($this-\>rawVersion, 'MariaDB') !== false) { $this-\>vendor = 'mariadb'; // Strip MariaDB 5.5.5- replication compatibility prefix $stripped = preg\_replace('/^5\\.5\\.5-/', '', $this-\>rawVersion); if (preg\_match('/^(\\d+\\.\\d+\\.\\d+)/', $stripped, $matches)) { $this-\>cleanVersion = $matches\[1\]; } else { $this-\>cleanVersion = $stripped; } } else { $this-\>vendor = 'mysql'; $this-\>cleanVersion = $this-\>rawVersion; } } else { throw new UnsupportedDatabaseVendorException( "Unsupported database driver \[{$this-\>driver}\]. Production environment must run on verified mysql, mariadb, or pgsql." ); } } public function vendor(): string { return $this-\>vendor; } public function version(): string { return $this-\>cleanVersion; } public function assertMatchesReconciledEnvironment(?string $expectedVendor): void { $normalizedExpected = strtolower((string) $expectedVendor); if ($this-\>driver === 'sqlite' || ($expectedVendor \&\& $this-\>vendor !== $normalizedExpected)) { throw new \\RuntimeException( "VERIFICATION GATE FAILED: Concurrency and transactional verification requires the reconciled production database vendor ({$expectedVendor}). Found driver: {$this-\>driver}, vendor: {$this-\>vendor}, version: {$this-\>cleanVersion}. Concurrency sign-off on SQLite is strictly prohibited." ); } } /\*\* \* Phase A: Session-level preparation executed BEFORE entering DB::transaction(). \* Captures previous session isolation to allow clean restoration in finally block. \*/ public function prepareTransactionIsolation(): ?string { if ($this-\>vendor === 'mysql') { $prev = DB::scalar('SELECT @@session.transaction\_isolation'); DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED'); return (string) $prev; } if ($this-\>vendor === 'mariadb') { $var = version\_compare($this-\>cleanVersion, '11.1.1', '\>=') ? 'transaction\_isolation' : 'tx\_isolation'; $prev = DB::scalar("SELECT @@session.{$var}"); DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED'); return (string) $prev; } // PostgreSQL defaults to READ COMMITTED; session pre-allocation not required. return null; } /\*\* \* Phase B: Executed INSIDE the active DB::transaction() block. \*/ public function applyTransactionIsolationInsideTransaction(): void { if ($this-\>vendor === 'pgsql') { DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED'); } } /\*\* \* Restoration: Executed in finally block after transaction commit/rollback. \*/ public function restoreTransactionIsolation(?string $previous): void { if (!$previous) { return; } $normalized = str\_replace('-', ' ', $previous); if (in\_array($this-\>vendor, \['mysql', 'mariadb'\], true)) { DB::statement("SET SESSION TRANSACTION ISOLATION LEVEL {$normalized}"); } } } 

**Authoritative Server-Side Slot Resolution \& Unified Transaction Service**

**Authoritative Slot Resolution:** NEW\_BOOKING and RESCHEDULE must accept a server-authorized slot\_id (or an authenticated server-side slot object). **Raw client-supplied new\_start\_utc and new\_end\_utc timestamps must never be authoritative inputs.** Inside the transaction, after the canonical resource row is locked FOR UPDATE, SlotResolver re-resolves and validates the slot against current availability and policies.

**Transaction-Derived FIFO Package Selection:** NEW\_BOOKING derives and locks the eligible package row using StudentLedgerService inside the transaction.

**Application-Level Idempotent Cancellation:** CANCEL acquires resource and student locks, locks and re-reads the booking row, asserts status === 'confirmed', and asserts it is not completed/cancelled/held. Before inserting cancellation\_restore, it checks if a restoration entry already exists for this booking\_id; if present, it returns the existing state cleanly without attempting a duplicate insert.

namespace App\\Services\\Booking; use Illuminate\\Support\\Facades\\DB; use App\\Services\\Database\\DatabaseCapability; use App\\Services\\Billing\\StudentLedgerService; use App\\Services\\Booking\\SlotResolver; use App\\Exceptions\\InsufficientCreditsException; use App\\Exceptions\\SlotConflictException; use App\\Exceptions\\InvalidBookingStateException; use App\\Exceptions\\InvalidSlotException; class UnifiedBookingTransactionService { public const MODE\_NEW\_BOOKING = 'NEW\_BOOKING'; public const MODE\_RESCHEDULE = 'RESCHEDULE'; public const MODE\_CANCEL = 'CANCEL'; public function \_\_construct( protected DatabaseCapability $databaseCapability, protected StudentLedgerService $studentLedgerService, protected SlotResolver $slotResolver ) {} public function execute(string $mode, array $params) { // Phase A: Prepare session isolation outside transaction $previousIsolation = $this-\>databaseCapability-\>prepareTransactionIsolation(); try { return DB::transaction(function () use ($mode, $params) { // Phase B: Apply inside active transaction (PostgreSQL) $this-\>databaseCapability-\>applyTransactionIsolationInsideTransaction(); $resourceId = $params\['resource\_id'\]; $studentId = $params\['student\_id'\]; $slotId = $params\['slot\_id'\] ?? null; $bookingId = $params\['booking\_id'\] ?? null; // Step 1: Lock Canonical Scheduling Resource (Concrete target resolved in Phase 0) // Illustrative table name; substitute exact table (e.g. users, schedules, tutors) from Phase 0: DB::select('SELECT id FROM canonical\_scheduling\_resources WHERE id = :resource\_id FOR UPDATE', \[ 'resource\_id' =\> $resourceId, \]); // Step 2: Lock Student Row DB::select('SELECT id FROM students WHERE id = :student\_id FOR UPDATE', \[ 'student\_id' =\> $studentId, \]); // Step 3: Server-Side Slot Resolution \& Window Conflict Verification $resolvedSlot = null; if (in\_array($mode, \[self::MODE\_NEW\_BOOKING, self::MODE\_RESCHEDULE\], true)) { if (!$slotId) { throw new InvalidSlotException('A valid server-authorized slot\_id is required.'); } // Re-resolve slot server-side after acquiring resource lock $resolvedSlot = $this-\>slotResolver-\>resolveAndValidate($slotId, $resourceId); if (!$resolvedSlot) { throw new InvalidSlotException('The requested availability slot is invalid or has expired.'); } $newStartUtc = $resolvedSlot-\>start\_time\_utc; $newEndUtc = $resolvedSlot-\>end\_time\_utc; // Window conflict check under READ COMMITTED record locks $conflicts = DB::select(" SELECT id FROM bookings WHERE resource\_id = :resource\_id AND status IN ('confirmed', 'held') AND start\_time\_utc \< :new\_end\_utc AND end\_time\_utc \> :new\_start\_utc AND id != :current\_booking\_id FOR UPDATE ", \[ 'resource\_id' =\> $resourceId, 'new\_end\_utc' =\> $newEndUtc, 'new\_start\_utc' =\> $newStartUtc, 'current\_booking\_id' =\> $bookingId ?? 0, \]); if (!empty($conflicts)) { throw new SlotConflictException('The selected time slot has already been reserved.'); } } // Step 4: Execute Requested Operation Mode if ($mode === self::MODE\_NEW\_BOOKING) { // Step 4.1: Lock eligible Student Package (Universal Lock Hierarchy Step 4) $eligiblePackage = $this-\>studentLedgerService-\>selectAndLockEligiblePackageForBooking($studentId); if (!$eligiblePackage) { throw new InsufficientCreditsException('Insufficient session credits available in active packages.'); } // Step 4.2: Insert Booking Record $createdBookingId = DB::table('bookings')-\>insertGetId(\[ 'student\_id' =\> $studentId, 'resource\_id' =\> $resourceId, 'start\_time\_utc' =\> $resolvedSlot-\>start\_time\_utc, 'end\_time\_utc' =\> $resolvedSlot-\>end\_time\_utc, 'status' =\> 'confirmed', 'active\_booking\_timezone' =\> $params\['active\_booking\_timezone'\], 'created\_at' =\> now(), 'updated\_at' =\> now(), \]); // Step 4.3: Append -1 Ledger Event (Universal Lock Hierarchy Step 5) DB::table('session\_ledger\_entries')-\>insert(\[ 'student\_id' =\> $studentId, 'student\_package\_id' =\> $eligiblePackage-\>id, 'booking\_id' =\> $createdBookingId, 'idempotency\_key' =\> $params\['idempotency\_key'\], 'entry\_type' =\> 'session\_consumed', 'credit\_change' =\> -1, 'description' =\> "Session booked for {$resolvedSlot-\>start\_time\_utc} UTC", 'created\_at' =\> now(), \]); return $createdBookingId; } elseif ($mode === self::MODE\_RESCHEDULE) { // Lock existing booking row $existingBooking = DB::table('bookings') -\>where('id', $bookingId) -\>where('student\_id', $studentId) -\>lockForUpdate() -\>first(); if (!$existingBooking || $existingBooking-\>status !== 'confirmed') { throw new InvalidBookingStateException('Only confirmed bookings can be rescheduled.'); } // Update booking window - ZERO ledger mutation DB::table('bookings')-\>where('id', $bookingId)-\>update(\[ 'start\_time\_utc' =\> $resolvedSlot-\>start\_time\_utc, 'end\_time\_utc' =\> $resolvedSlot-\>end\_time\_utc, 'active\_booking\_timezone' =\> $params\['active\_booking\_timezone'\], 'admin\_reconfirmation\_needed' =\> true, 'updated\_at' =\> now(), \]); DB::table('session\_reschedules')-\>insert(\[ 'booking\_id' =\> $bookingId, 'actor\_type' =\> $params\['actor\_type'\], 'actor\_id' =\> $params\['actor\_id'\] ?? null, 'old\_start\_time\_utc' =\> $existingBooking-\>start\_time\_utc, 'new\_start\_time\_utc' =\> $resolvedSlot-\>start\_time\_utc, 'old\_timezone' =\> $existingBooking-\>active\_booking\_timezone, 'new\_timezone' =\> $params\['active\_booking\_timezone'\], 'idempotency\_key' =\> $params\['idempotency\_key'\], 'ip\_address' =\> $params\['ip\_address'\], 'created\_at' =\> now(), \]); return $bookingId; } elseif ($mode === self::MODE\_CANCEL) { // Lock and re-read target booking to assert state $booking = DB::table('bookings') -\>where('id', $bookingId) -\>where('student\_id', $studentId) -\>lockForUpdate() -\>first(); if (!$booking || $booking-\>status !== 'confirmed') { throw new InvalidBookingStateException('Only confirmed bookings owned by the student may be cancelled.'); } DB::table('bookings')-\>where('id', $bookingId)-\>update(\[ 'status' =\> 'cancelled', 'updated\_at' =\> now(), \]); if ($params\['restore\_credit'\] ?? false) { // Application-level idempotency check: inspect if restoration already exists $existingRestore = DB::table('session\_ledger\_entries') -\>where('booking\_id', $bookingId) -\>where('entry\_type', 'cancellation\_restore') -\>lockForUpdate() -\>first(); if (!$existingRestore) { $consumedEntry = DB::table('session\_ledger\_entries') -\>where('booking\_id', $bookingId) -\>where('entry\_type', 'session\_consumed') -\>first(); if ($consumedEntry \&\& $consumedEntry-\>student\_package\_id) { // Step 4 in Lock Hierarchy: Lock Student Package DB::select('SELECT id FROM student\_packages WHERE id = :package\_id FOR UPDATE', \[ 'package\_id' =\> $consumedEntry-\>student\_package\_id, \]); // Step 5 in Lock Hierarchy: Append Ledger Restoration Entry DB::table('session\_ledger\_entries')-\>insert(\[ 'student\_id' =\> $studentId, 'student\_package\_id' =\> $consumedEntry-\>student\_package\_id, 'booking\_id' =\> $bookingId, 'idempotency\_key' =\> $params\['idempotency\_key'\], 'entry\_type' =\> 'cancellation\_restore', 'credit\_change' =\> 1, 'description' =\> "Credit restored from cancelled booking #{$bookingId}", 'created\_at' =\> now(), \]); } } } return $bookingId; } }, 3); } finally { $this-\>databaseCapability-\>restoreTransactionIsolation($previousIsolation); } } } 

**Module 4: Dynamic First-Party Forms Engine**

**1. Database Schema \& Non-Circular Migration Ordering**

Create tables in strict non-circular sequence:

forms:

id: Primary Key convention.

title: string.

slug: string (unique).

description: text, nullable.

status: enum (draft, published, archived).

prompt\_trigger: enum (none, after\_booking, after\_reschedule, next\_session\_check).

is\_mandatory: boolean (default false).

can\_edit\_after\_submission: boolean (default false).

lock\_version: integer (default 1) — *optimistic lock dedicated strictly to form-level metadata updates (title, slug, settings)*.

created\_by: FK to [users.id](users.id).

active\_version\_id: FK to [form\_versions.id](form_versions.id) (nullable, added in step 3).

form\_versions:

id: Primary Key convention.

form\_id: FK to [forms.id](forms.id) (cascadeOnDelete()).

version\_number: integer.

changelog: text, nullable.

created\_at: timestamp.

Constraint: UNIQUE(form\_id, version\_number).

*Schema update:* Add active\_version\_id column to forms referencing [form\_versions.id](form_versions.id) (nullOnDelete()).

form\_questions:

id: Primary Key convention.

form\_version\_id: FK to [form\_versions.id](form_versions.id) (cascadeOnDelete()).

question\_key: slug string.

label: string.

description: text, nullable.

question\_type: string.

is\_required: boolean.

assistant\_visible: boolean (default true). If false, omitted from Assistant role views and exports.

sort\_order: integer.

validation\_rules: JSON, nullable — *stores min/max values, string lengths, regex, allowed date ranges, and custom constraints*.

presentation\_config: JSON, nullable — *stores placeholder text, rating scale limits (1-5 vs 1-10), left/right labels, and UI rendering hints*.

conditional\_logic: JSON, nullable.

Constraint: UNIQUE(form\_version\_id, question\_key).

form\_question\_options:

id: Primary Key convention.

form\_question\_id: FK to [form\_questions.id](form_questions.id) (cascadeOnDelete()).

label: string, value: string, sort\_order: integer.

Constraint: UNIQUE(form\_question\_id, value).

form\_submissions:

id: Primary Key convention.

form\_version\_id: FK to [form\_versions.id](form_versions.id) (restrictOnDelete()).

student\_id: FK to [students.id](students.id) (cascadeOnDelete()).

status: enum (draft, submitted).

submitted\_at: timestamp, nullable.

submission\_revision: integer (default 1).

form\_submission\_revisions:

id: Primary Key convention.

form\_submission\_id: FK to [form\_submissions.id](form_submissions.id) (cascadeOnDelete()).

revision\_number: integer.

snapshot\_answers: JSON — *immutable historical record of answers at this revision*.

submitted\_at, created\_at: timestamps.

Constraint: UNIQUE(form\_submission\_id, revision\_number).

form\_answers:

id: Primary Key convention.

form\_submission\_id: FK to [form\_submissions.id](form_submissions.id) (cascadeOnDelete()).

form\_question\_id: FK to [form\_questions.id](form_questions.id) (restrictOnDelete()).

value\_text: longtext or JSON, nullable — *canonical current-state answer*.

Constraint: UNIQUE(form\_submission\_id, form\_question\_id).

**2. Supported Question Types \& Configuration**

short\_text, long\_text, email, phone, number, date, single\_choice (Radio), multiple\_choice (Checkboxes), dropdown (Select), yes\_no (Boolean), rating\_scale (1-5 or 1-10), info\_block (Presentation-only instructional text; no answers stored).

**3. Canonical Answer State vs. Revision Snapshots**

**form\_answers is the sole canonical source of truth for current state.** All student portal forms, assistant views, and streaming exports query form\_answers.

**form\_submission\_revisions.snapshot\_answers is strictly an append-only historical audit snapshot.**

Every submission or revision mutation must execute inside a database transaction:

Update or insert current rows in form\_answers.

Increment form\_submissions.submission\_revision.

Insert a full snapshot into form\_submission\_revisions matching the new submission\_revision.

**4. Form Version Freeze Point \& Base-Version Concurrency**

**Freeze Point:** A form version becomes immutable after the first submission record (draft or submitted) exists against it.

**Superseded Versions Are Read-Only:** Historical form versions with existing drafts or submissions are permanently frozen. They cannot be edited in place, reactivated as current, or published.

**Draft Isolation:** Drafts created under an older version remain permanently anchored to that version. Publishing a newer version does not overwrite drafts. The student portal renders an Update Available badge.

**Base-Version Concurrency:** Form structure mutations submit base\_version\_id. The operation locks the parent forms row FOR UPDATE:

Verify forms.active\_version\_id == incoming\_base\_version\_id.

If mismatched -\> Reject with HTTP 409 Conflict (editor must reload latest version).

If active\_version has \\ge 1 submission -\> create v+1, deep-copy questions/options with updates applied, and update forms.active\_version\_id.

If 0 submissions -\> apply modifications in place to the active version.

Creating the first draft locks the parent forms row FOR UPDATE, verifying the target version is currently active before inserting form\_submissions.

**5. Conditional Logic Engine**

**Schema:** {"logic\_version": 1, "mode": "all", "conditions": \[{"question\_key": "studied\_arabic\_before", "operator": "equals", "value": "yes"}\]}.

**Supported Operators:** equals, not\_equals, contains, not\_contains, in, not\_in, greater\_than, less\_than, is\_answered, is\_not\_answered.

**Authoritative Server Evaluation:** Client UI toggles are non-authoritative. Backend FormValidationService evaluates logic on submission.

**Hidden Field Policy:** If a question is evaluated as hidden, its is\_required flag is bypassed, and any submitted value is purged from form\_answers.

Validate against circular dependencies and depth \> 3 in builder rules.

**6. Memory-Safe ChunkById Streaming CSV Export**

return response()-\>stream(function () use ($formVersionId) { $handle = fopen('php://output', 'w'); // 1. Pre-load question columns once outside the loop $questions = FormQuestion::where('form\_version\_id', $formVersionId) -\>where('question\_type', '!=', 'info\_block') -\>when(auth()-\>user()-\>isAssistant(), fn($q) =\> $q-\>where('assistant\_visible', true)) -\>orderBy('sort\_order') -\>get(); fputcsv( $handle, array\_merge(\['Submission ID', 'Student ID', 'Submitted At'\], $questions-\>pluck('label')-\>all()) ); // 2. Chunk submissions by primary key with eager loading FormSubmission::where('form\_version\_id', $formVersionId) -\>with(\['answers' =\> function ($query) { $query-\>select(\['id', 'form\_submission\_id', 'form\_question\_id', 'value\_text'\]); }\]) -\>chunkById(200, function ($submissions) use ($handle, $questions) { foreach ($submissions as $submission) { $answersMap = $submission-\>answers-\>keyBy('form\_question\_id'); $row = \[$submission-\>id, $submission-\>student\_id, $submission-\>submitted\_at\]; foreach ($questions as $q) { $val = $answersMap\[$q-\>id\]-\>value\_text ?? ''; // CSV Formula Injection Defense if (in\_array(substr((string) $val, 0, 1), \['=', '+', '-', '@'\], true)) { $val = "'" . $val; } $row\[\] = $val; } fputcsv($handle, $row); } }); fclose($handle); }, 200, \[ 'Content-Type' =\> 'text/csv', 'Content-Disposition' =\> 'attachment; filename="[form-export.csv](form-export.csv)"', \]); 

**Module 5: Student Billing \& Session Ledger**

The system must never store remaining sessions as a single mutable scalar integer. All balances derive from an append-only event ledger.

**1. Database Schema**

student\_packages:

id: Primary Key convention.

student\_id: FK to [students.id](students.id).

package\_name: string.

original\_price, discount\_amount, final\_price: decimal(10, 2). *Immutable historical financial facts once created; never recalculated dynamically.*

currency: string (default USD).

total\_sessions\_allocated: integer. *Display/reporting metadata only; credit balance is never derived from this column.*

expiration\_date: date, nullable.

status: enum (active, completed, expired, cancelled).

payment\_records (Append-Only Payments):

id, student\_package\_id, student\_id: FKs.

idempotency\_key: string (unique).

amount\_paid: decimal(10, 2), currency: string.

payment\_method: string (default PayPal - Manual).

transaction\_reference: string, nullable.

paid\_at: timestamp, recorded\_by: FK to [users.id](users.id), notes: text, nullable.

payment\_refunds (Append-Only Refunds):

id, payment\_record\_id, student\_package\_id, student\_id: FKs.

idempotency\_key: string (unique).

amount\_refunded: decimal(10, 2), currency: string, reason: text, nullable.

refunded\_at: timestamp, recorded\_by: FK to [users.id](users.id).

session\_ledger\_entries (Append-Only Credit Events):

id, student\_id, student\_package\_id (nullable), booking\_id (nullable).

idempotency\_key: string (unique).

entry\_type: enum (package\_grant, session\_consumed, courtesy\_adjustment, cancellation\_restore, expiration\_forfeit).

credit\_change: integer (+8, -1, +1).

description: string, created\_by: FK to [users.id](users.id) (nullable), created\_at: timestamp.

Unique index on (booking\_id, entry\_type) for session\_consumed entries.

**2. Deterministic FIFO Allocation \& Business-Date Expiration**

**Exact Expiration Construction:** A package with expiration\_date (DATE) is eligible through 23:59:59 of that date in the configured business timezone:$expiresAtUtc = Carbon::parse($package-\>expiration\_date, config('[app.timezone](app.timezone)')) -\>endOfDay() -\>setTimezone('UTC'); $isExpired = now('UTC')-\>greaterThan($expiresAtUtc); 

**FIFO Selection Policy in StudentLedgerService:** Inside selectAndLockEligiblePackageForBooking($studentId) under lock (FOR UPDATE), query active packages obeying Universal Global Lock Order (Step 4):$packages = StudentPackage::where('student\_id', $studentId) -\>where('status', 'active') -\>orderByRaw('expiration\_date IS NULL, expiration\_date ASC') -\>orderBy('created\_at', 'asc') -\>orderBy('id', 'asc') -\>lockForUpdate() -\>get(); foreach ($packages as $package) { if ($package-\>expiration\_date) { $expiresAtUtc = Carbon::parse($package-\>expiration\_date, config('[app.timezone](app.timezone)'))-\>endOfDay()-\>setTimezone('UTC'); if (now('UTC')-\>greaterThan($expiresAtUtc)) { continue; } } $balance = (int) SessionLedgerEntry::where('student\_package\_id', $package-\>id)-\>lockForUpdate()-\>sum('credit\_change'); if ($balance \> 0) { return $package; } } return null; 

**Credit Consumption Lifecycle:**

Exactly one session\_consumed = -1 entry is created when a booking is confirmed (NEW\_BOOKING).

Rescheduling (RESCHEDULE) does not mutate the ledger.

Eligible cancellations append cancellation\_restore = +1.

Session completion updates the booking lifecycle status; it does not mutate the ledger.

**3. Refund Concurrency, Serialization \& Ceilings**

To eliminate race conditions where concurrent refunds across multiple payment records breach the aggregate package ceiling, **the target student\_packages row must be locked FOR UPDATE before evaluating payment and package ceilings**:

// Step 4 in Universal Lock Hierarchy: Lock Student Package DB::select('SELECT id FROM student\_packages WHERE id = :package\_id FOR UPDATE', \[ 'package\_id' =\> $packageId, \]); // Step 5 in Universal Lock Hierarchy: Lock Payment Record $payment = DB::table('payment\_records') -\>where('id', $paymentRecordId) -\>where('student\_package\_id', $packageId) -\>lockForUpdate() -\>firstOrFail(); // Per-Payment Ceiling Check $paymentRefundedTotal = (float) DB::table('payment\_refunds') -\>where('payment\_record\_id', $paymentRecordId) -\>sum('amount\_refunded'); $refundablePaymentAmount = $payment-\>amount\_paid - $paymentRefundedTotal; if ($requestedAmount \<= 0 || $requestedAmount \> $refundablePaymentAmount) { throw new InvalidRefundException('Refund amount exceeds the refundable ceiling for this payment.'); } // Aggregate Package Ceiling Check $packagePaidTotal = (float) DB::table('payment\_records') -\>where('student\_package\_id', $packageId) -\>sum('amount\_paid'); $packageRefundedTotal = (float) DB::table('payment\_refunds') -\>where('student\_package\_id', $packageId) -\>sum('amount\_refunded'); if (($packageRefundedTotal + $requestedAmount) \> $packagePaidTotal) { throw new InvalidRefundException('Refund amount exceeds total paid amount for this package.'); } // Insert Refund Record DB::table('payment\_refunds')-\>insert(\[ 'payment\_record\_id' =\> $paymentRecordId, 'student\_package\_id' =\> $packageId, 'student\_id' =\> $payment-\>student\_id, 'idempotency\_key' =\> $idempotencyKey, 'amount\_refunded' =\> $requestedAmount, 'currency' =\> $payment-\>currency, 'reason' =\> $reason, 'refunded\_at' =\> now(), 'recorded\_by' =\> auth()-\>id(), \]); 

**Decoupled Financial Invariants:**

Monetary refunds never automatically credit or mutate session\_ledger\_entries. Restoring student session credits requires an independent, authorized admin adjustment (cancellation\_restore or courtesy\_adjustment).

Completed sessions are never marked unconsumed or reversed solely because a financial refund was issued.

**4. Deterministic Financial Calculations**

All customer balances, net revenues, and session credit totals must be dynamically derived on read from the append-only event ledger and payment tables (never stored as mutable scalar counters on parent models):

**Gross Paid** = SUM(payment\_records.amount\_paid) for the student/package in scope

**Gross Refunded** = SUM(payment\_refunds.amount\_refunded) for the same scope

**Net Paid** = Gross Paid - Gross Refunded

**Balance Due** = student\_packages.final\_price - Net Paid

**Remaining Credits** = SUM(session\_ledger\_entries.credit\_change) for the target student\_package\_id, restricted to non-expired, active packages per the FIFO eligibility rule in Section 5.2

**Module 6: SEO Articles CMS**

**1. Database Schema \& Constraints**

articles:

id: Primary Key convention.

title: string, slug: string (unique indexed), excerpt: text, body: longtext.

featured\_image\_path, seo\_title, seo\_description, canonical\_url: nullable strings.

status: enum (draft, published, archived), published\_at: timestamp, nullable.

author\_id: FK to [users.id](users.id), locale: string (default 'en', indexed).

translation\_group\_id: string/UUID, nullable, indexed.

lock\_version: integer (default 1).

Constraint: UNIQUE(translation\_group\_id, locale) for non-null group IDs.

article\_revisions:

id: Primary Key convention, article\_id: FK to [articles.id](articles.id).

Snapshot of all content and SEO fields, revised\_by: FK to [users.id](users.id), created\_at: timestamp.

article\_slug\_redirects:

id: Primary Key convention, article\_id: FK to [articles.id](articles.id).

old\_slug: string (unique indexed), created\_at: timestamp.

**2. Optimistic Locking, Redirects \& Content Sanitization**

**Optimistic Locking:** On update, verify submitted lock\_version matches the database. If mismatched, reject with HTTP 409 Conflict.

**Complete Revision Snapshots:** Every update to a published article writes a complete snapshot of all fields to article\_revisions in the same transaction.

**Slug Collisions \& 301 Redirects:** When updating a slug, record the previous slug in article\_slug\_redirects. Requests to /articles/{old\_slug} return HTTP 301 Moved Permanently to /articles/{new\_slug}.

**Content Sanitization:** Hand-rolled regex sanitizers are strictly prohibited. Use HTMLPurifier (mews/purifier):

Allowed elements: h1, h2, h3, h4, p, b, i, strong, em, ul, ol, li, blockquote, a, img.

Allowed attributes: [a.href](a.href), [a.title](a.title), [img.src](img.src), [img.alt](img.alt).

Scheme filters: [a.href](a.href) permits only https://, http://, mailto:. [img.src](img.src) permits same-origin storage URLs only (strictly reject data: URIs).

**Module 7: RBAC, Audit Logging \& Social Links Dashboard**

**1. Role-Based Access Control \& Explicit Column Projection**

CapabilityOwnerAdminAssistantStudentManage Admins \& System Settings**Yes**NoNoNoExecute Student Profile Merges**Yes**NoNoNoView Financial Data (Payments/Prices/Refunds)**Yes****Yes**No (403)Summary OnlyRecord Payments, Refunds \& Discounts**Yes****Yes**No (403)NoView \& Reschedule Bookings**Yes****Yes****Yes**Own OnlyView Student Contact Info \& Submissions**Yes****Yes****Yes**Own OnlyView Hidden Form Answers (assistant\_visible = false)**Yes****Yes**No (403)Own OnlyConfigure Form Builder \& Versions**Yes****Yes**No (403)NoPublish SEO Articles**Yes****Yes**No (403)No 

**Data Projection Safeguard:** Do not rely on Eloquent $hidden arrays on models to enforce security. All Assistant-scoped query paths must use explicit -\>select(\[...\]) column allowlists or dedicated API Resources (AssistantStudentResource) to guarantee financial columns (original\_price, amount\_paid, transaction\_reference) are never fetched:return Student::where('id', $id) -\>select(\['id', 'first\_name', 'last\_name', 'email', 'phone', 'preferred\_timezone'\]) -\>firstOrFail(); 

**2. Append-Only Audit Trail (audit\_logs) \& Privacy Erasure Support**

audit\_logs:

id, event\_uuid (unique), actor\_type (admin, student, system).

actor\_user\_id: nullable FK to [users.id](users.id), actor\_student\_id: nullable FK to [students.id](students.id).

action: string (e.g., [booking.reschedule](booking.reschedule), [student.merge](student.merge), [form.publish](form.publish)).

target\_type: string, target\_id: string/int.

old\_values: JSON, nullable, new\_values: JSON, nullable.

ip\_address: string, nullable, created\_at: timestamp.

**PII Minimization \& Erasure Workflow Support:** Historical audit rows are immutable records of operational history. However, to support data erasure requirements:

Audit payloads (old\_values, new\_values) must never store raw sensitive PII (plaintext passwords, session IDs, full credit card/banking data, full date of birth, or full phone/email).

Store entity IDs and cryptographically blinded hashes.

*GDPR/Privacy Erasure Support:* Implement an authorized anonymization/erasure workflow that removes or anonymizes personal data from application-controlled records while preserving legally and operationally required financial/audit records. The implementation must be designed to support applicable data-erasure requirements; legal compliance is not inferred solely from the software implementation.

When the workflow executes, personal PII is scrubbed from primary tables per the semantic column rules in Module 2.5, and identifying strings within audit payloads are pseudonymized/redacted while preserving the event shell (actor, timestamp, action, event\_uuid).

**3. Social Links Dashboard Redesign**

Predefined platforms (YouTube, Instagram, TikTok, Facebook, LinkedIn, X/Twitter, Custom).

Strict URL validation requiring http:// or https://.

Instant toggle switch backed by database validation (reverts on failure).

Deterministic numeric display order.

Render using local inline SVG icons only; never load third-party external icon scripts or images.

**Module 8: Historical Data Migration Strategy**

**Step 1: Schema Evolution \& Non-Blocking Constraints**

To prevent table locks and operational disruption on an active production bookings table:

**Migration 8A (Immediate):**Schema::table('bookings', function (Blueprint $table) { // Use the exact FK column type required by the [students.id](students.id) PK convention // confirmed during Phase 0 (e.g., $table-\>foreignId('student\_id')-\>nullable(), // $table-\>ulid('student\_id')-\>nullable(), or $table-\>uuid('student\_id')-\>nullable()). // Never hardcode unsignedBigInteger unless Phase 0 explicitly confirms it. }); 

**Migration 8B (Vendor/Version-Specific Constraint Deployment):**

Do not assume ALGORITHM=INPLACE, LOCK=NONE is universally valid. In MySQL, adding a foreign key with foreign\_key\_checks=1 requires COPY under certain storage engine configurations, blocking concurrent DML.

*Implementation Rule:* Migration 8B must first inspect the database vendor and server version. If the engine cannot execute an online, lock-free foreign key addition without holding table locks, the migration must log the exact manual DDL deployment strategy (e.g., pt-online-schema-change / gh-ost or scheduled maintenance window) and stop, rather than disabling foreign\_key\_checks or locking the production table.

**Step 2: Idempotent Data Backfill (migrate:students-backfill)**

**Signature:** php artisan migrate:students-backfill {--dry-run : Run analysis without writing} {--apply : Apply mutations}

**Default:** --dry-run. Process records in bounded chunks (200 records) inside bounded transactions.

**Precedence Rules:**

Existing bookings.student\_id -\> Preserve as-is.

Exact canonical match on existing student -\> Link student\_id.

Unique contact details with determinable country context -\> Create students record with identity\_status = 'legacy\_unverified', date\_of\_birth = NULL, populate names, and link.

**Ambiguous Legacy Numbers:** A legacy phone number with no determinable country code (no leading + and no country metadata) must be treated as missing. Never guess or assume the business's home country. Such records fall into the manual triage bucket with bookings.student\_id = NULL.

Log all unmatched and ambiguous records to migration\_booking\_exceptions.

**Step 3: Historical Timezone Preservation**

If stored in UTC: Leave untouched.

If stored in local time with known historical IANA timezone: Convert to UTC using that specific timezone and date.

If timezone provenance is ambiguous: Log to migration\_booking\_timezone\_exceptions without altering the timestamp.

**Mandatory Verification Gates**

VERIFICATION GATES ┌─────────────────────────┐ ┌─────────────────────────┐ ┌─────────────────────────┐ │ 1. Booking \& Timezones │──▶ │ 2. Identity \& Access │──▶ │ 3. Forms \& Ledger │ │ • Pre-flight FAIL-CLOSED│ │ • Global HMAC limiters │ │ • ChunkById query log │ │ • Universal lock order │ │ • Consecutive tracker │ │ • Formula CSV injection │ │ • Derived FIFO booking │ │ • No remember-token auth│ │ • Package ceiling race │ │ • Zero ledger consume on│ │ • Live status check TTL │ │ • Business-date FIFO │ │ reschedule action │ │ • Semantic anonymize │ │ • Canonical vs snapshot │ │ • SlotResolver tamper │ │ • 5-tier merge lock │ │ • Immutability on draft │ └─────────────────────────┘ └─────────────────────────┘ └─────────────────────────┘ 

**Pre-Flight Fail-Closed Environment Check**

Before the verification suite begins execution, an automated environment check inside the base test runner (TestCase::setUp()) invokes DatabaseCapability::assertMatchesReconciledEnvironment(). **If the database vendor does not match the reconciled production vendor, it fails immediately with a runtime exception (FAIL-CLOSED)**:

protected function setUp(): void { parent::setUp(); $expectedVendor = config('database.reconciled\_vendor'); // set during Phase 0 sign-off app(\\App\\Services\\Database\\DatabaseCapability::class)-\>assertMatchesReconciledEnvironment($expectedVendor); } 

**1. Booking \& Concurrency Suite**

**Universal Lock Ordering Test (Booking vs. Merge):** Concurrently execute a booking transaction and an Admin Merge Tool transaction involving the same student. Assert both follow the Canonical Resource ASC -\> Student ASC -\> Booking ASC -\> Package ASC -\> Ledger/Payment ASC hierarchy across all 5 tiers. Assert both complete cleanly without unhandled deadlock exceptions.

**Transaction-Derived FIFO Selection Test:** Create two active packages for a student (Package A with 2 credits expiring in 3 days; Package B with 5 credits expiring in 10 days). Execute NEW\_BOOKING without specifying package ID. Assert the booking is created, credits are deducted from Package A, and Package B remains untouched.

**Adjacent Slot Concurrency Test:** Simulate two concurrent processes booking adjacent non-overlapping slots (10:00–11:00 and 11:00–12:00) on the same canonical resource under READ COMMITTED. Assert both succeed without gap-lock deadlocks.

**Reschedule Zero-Ledger Invariant Test:** Execute a valid reschedule action on a confirmed booking. Assert that session\_reschedules logs the event, admin\_reconfirmation\_needed = true, and zero rows are inserted into session\_ledger\_entries.

**Cancellation State Validation \& Idempotency Test:** Attempt to cancel a booking in completed status; assert InvalidBookingStateException. Cancel a confirmed booking with restore\_credit = true; assert status becomes cancelled and one cancellation\_restore ledger entry is appended. Execute the cancellation request a second time on the same booking; assert the operation returns cleanly (idempotent success) and does not insert duplicate ledger rows.

**SlotResolver Tamper Test:** Submit client-manipulated UTC timestamps against an authorized slot\_id. Assert SlotResolver validates intervals server-side and the persisted booking adheres strictly to server-side slot definitions.

**Reschedule 24h Window Test:** Assert a session at +25 hours can be rescheduled by the student; assert a session at +23 hours returns a validation error across DST transition boundaries.

**2. Student Identity \& Security Suite**

**Scope Invariant Test:** Assert a record with identity\_status = 'legacy\_unverified' or 'merged' fails authentication even if DOB and identifiers match.

**Global HMAC Rate Limit Test:** Send 5 invalid verification requests for the same target email across 5 distinct IP addresses. Assert the 6th request fails with HTTP 429 Too Many Requests.

**Consecutive Failure State Test:** Submit 3 consecutive failed attempts on an identity fingerprint. Assert StudentAuthAttemptTracker triggers a 15-minute cooldown. Submit a successful attempt on another account; assert its failure count resets cleanly.

**Semantic Column Anonymization Test:** Execute privacy erasure on a student. Assert first\_name is 'Anonymized', last\_name is 'Student', email is 'anonymized\_{id}@[internal.invalid](internal.invalid)', phone, phone\_normalized, and date\_of\_birth are strictly NULL, and no type violation exceptions occur.

**Merge Append-Only Fact Preservation Test:** Merge Student B into Student A. Assert historical ledger rows, payments, and refunds have student\_id updated to Student A, but amount\_paid, credit\_change, created\_at, and idempotency\_key remain completely identical.

**Session Invalidation on Merge Test:** Authenticate Student B. Concurrently merge Student B into Student A. On Student B's next request, assert session rejection and redirect to login within 30 seconds. Assert pointers flatten to Student A.

**Livewire Tamper Test:** Invoke a reschedule action from Student A's session passing Student B's booking\_id. Assert HTTP 403/404 and zero database mutation.

**3. Forms Engine Suite**

**ChunkById Streaming Query Log Test:** Stream an export of 400 form submissions. Inspect DB::getQueryLog() to assert questions were queried exactly once and submission answers were eager-loaded in batches of 200 (no N+1 queries).

**CSV Injection Test:** Export a form response containing =cmd|' /C calc'!A0. Assert the output CSV cell begins with '=cmd....

**Version Freeze on Draft Test:** Create the first draft against Form v1. Assert Form v1 is now frozen. Attempt an admin edit on Form v1; assert Form v2 is created, Form v1 questions remain untouched, and the draft remains linked to Form v1.

**Canonical vs. Snapshot State Test:** Update a submitted form. Assert form\_answers reflects the updated answers and form\_submission\_revisions contains an immutable snapshot of revision 1 and revision 2.

**4. Ledger \& Accounting Suite**

**Business-Date Expiration Test:** Set package expiration to date D. In a business timezone of UTC+2, verify that at D 21:59:58 UTC (23:59:58 local) the package is valid, and at D 22:00:01 UTC (00:00:01 local next day) the package is expired.

**Hot-Path Index Seek Test:** Execute an EXPLAIN query for SessionLedgerEntry::where('student\_package\_id', $package-\>id)-\>lockForUpdate()-\>sum('credit\_change'). Assert the planner uses idx\_ledger\_package\_student as an index range scan/seek rather than a full index scan.

**Package Ceiling Multi-Payment Concurrency Test:** Create a package with Payment A ($100) and Payment B ($100). Concurrently execute a $150 refund against Payment A and a $150 refund against Payment B. Assert that the student\_packages row lock serializes execution, exactly one refund succeeds, the other fails ceiling validation, and aggregate refunds do not exceed $200.

**Deterministic Refund Sequential Test:** In a sequential test, execute a $70 refund followed by a $40 refund against a $100 payment. Assert the second attempt throws a ceiling exception and the final refunded amount equals exactly $70.

**Financial Derivation Test:** Grant 8 credits, consume 2, restore 1. Assert balance equals 7. Issue a monetary refund; assert session credits are completely unaffected.

**RBAC Projection Test:** Perform a GET request as an Assistant to student endpoints; assert financial columns are omitted from raw SQL queries.

**Step-by-Step Implementation Sequence**

Execute implementation in strict chronological order:

\[ \] **Batch 0: Forensic Audit \& Reconciliation Report (MANDATORY HARD STOP)**

Audit DB vendor, exact engine version, schema migrations, and PK conventions.

Audit existing authorization layers (gates, policies, roles).

Identify canonical scheduling resource lock target and slot identity model.

Deliver Phase 0 Reconciliation Report and **WAIT** for review and approval before touching code.

\[ \] **Batch 1: Timezone \& Booking Correctness**

Implement dynamic DateTimeZone::getLocation() country resolution with curated static override map in TimezoneDisplayService.

Fix manual timezone state handling; bind server-authoritative slot\_id.

Implement server-side SlotResolver validating intervals against scheduling resources.

Add tests for DST transitions and slot tamper rejections.

\[ \] **Batch 2: Schema Migrations \& Idempotent Backfill**

Run Migration 8A (bookings.student\_id nullable column matching confirmed PK convention).

Create students table migration with normalization columns, indexes, and soft deletes.

Implement migrate:students-backfill with ambiguous phone triage rules.

Run backfill with --dry-run, verify exceptions, then execute --apply.

Evaluate engine capabilities and execute Migration 8B (FK constraint).

\[ \] **Batch 3: Student Identity, Auth \& Rate Limiting**

Implement StudentIdentityService (NFKC, E.164, lowercase email).

Build verification controller with globally-keyed HMAC limiters and StudentAuthAttemptTracker.

Configure student session guard with no remember-me functionality.

Implement EnsureStudentAuthenticated with live status checking.

Build Admin Merge Tool adhering to Universal Global Lock Hierarchy across all 5 levels, append-only fact immutability, and single-hop pointer flattening.

\[ \] **Batch 4: Student Portal \& Self-Service Rescheduling**

Build student dashboard views.

Implement reschedule engine with fixed 24h UTC cutoff and server-side ownership validation.

Create composite indexes: idx\_bookings\_resource\_status\_window, idx\_ledger\_package\_student, and idx\_student\_packages\_student\_status.

Implement DatabaseCapability service with fail-closed vendor verification, MariaDB 5.5.5- prefix scrubbing, and two-phase isolation lifecycle.

Implement UnifiedBookingTransactionService with explicit modes (NEW\_BOOKING with transaction-derived FIFO, RESCHEDULE, CANCEL with application-level idempotency), Universal Global Lock Order, and deadlock retries.

\[ \] **Batch 5: Dynamic Forms Engine**

Run non-circular migrations (forms -\> form\_versions -\> FK update -\> form\_questions with validation\_rules/presentation\_config -\> form\_submissions -\> form\_answers / form\_submission\_revisions).

Implement version freeze on first submission record (draft or submitted) with optimistic builder locking.

Build conditional logic evaluation service.

Build memory-safe chunkById CSV streaming export with eager loading.

\[ \] **Batch 6: Billing \& Session Ledger**

Create student\_packages, payment\_records, payment\_refunds, and session\_ledger\_entries migrations.

Implement StudentLedgerService with deterministic FIFO allocation and exact Carbon UTC expiration logic.

Build refund processor locking student\_packages row FOR UPDATE before evaluating per-payment and package ceilings.

\[ \] **Batch 7: SEO Articles CMS**

Create articles, revisions, and redirect tables (including UNIQUE(translation\_group\_id, locale)).

Configure HTMLPurifier (mews/purifier) with explicit tag/attribute allowlist.

Implement optimistic locking and 301 slug redirect routing.

\[ \] **Batch 8: RBAC \& Audit Logging**

Enforce Owner, Admin, and Assistant gates with explicit SQL column projection.

Build append-only audit\_logs subscriber with PII minimization and semantic privacy erasure support.

Refactor social links management UI with local SVG icons.

\[ \] **Batch 9: Final Quality Gate \& Report**

Implement pre-flight fail-closed check in TestCase::setUp().

Execute complete test suite against an isolated database on the target production database engine (php artisan test).

Run code formatting checks (./vendor/bin/pint).

Deliver final execution summary documenting all files created, modified, and verified.