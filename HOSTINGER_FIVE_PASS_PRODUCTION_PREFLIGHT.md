# Executive Summary

**NOT READY TO DEPLOY**

Actual Hostinger inspection on 2026-10-04 found production still at `d15967c88912f34db289ef3621bd9ebef1d56c85`, while GitHub main is the verified five-pass checkpoint `18ac92c27f96851bbf83d5df68831f76fc22dc76`. Seven checkpoint migrations are pending. All six historical purchases have positive, unexpired generic balances: 28 remaining units. None has sufficient explicit offering/type evidence for automatic classification. The candidate deliberately excludes unclassified purchases from typed availability, so a blind deployment would strand usable rights.

Other gates are a real 60/120-minute diagnostic availability conflict, missing active two-hour and advanced lesson products, an unverified backup restore, and coordinated installation of locked dependencies and two public asset copies. The ledger arithmetic itself reconciles. Scheduler, queue, watchdog, inbound Telegram and failed offsite backup operations remain a separate, unexecuted handoff.

This pass used SSH file inspection, SELECT/SHOW queries in a read-only transaction, in-memory archive validation and a TLS handshake. It did not boot application services, execute production Artisan, issue application HTTP requests, create a dump, deploy, migrate, change configuration, send messages, enroll MFA, push or commit. Temporary inspection programs and sanitized evidence stayed in private local temporary storage; no inspection program was uploaded. Two local planning documents are the only repository additions.

# GitHub Release Candidate

| Item | Verified value |
| --- | --- |
| Origin | `https://github.com/mohammedAteff/EgyptianArabicTutoring.git` |
| Baseline | `d15967c88912f34db289ef3621bd9ebef1d56c85` |
| Implementation | `a3315354350f5e01053a2fb3199b08b06b98d85b`; parent is baseline |
| Report / target main | `18ac92c27f96851bbf83d5df68831f76fc22dc76`; parent is implementation |
| Later unexpected commits | None observed by `ls-remote` |

The checkpoint's prior local validation reports 850 tests / 7,760 assertions, MariaDB concurrency 16 / 76, fresh-schema feature 151 / 1,133, 12 JavaScript tests, build/view compilation and dependency audits. Those are existing checkpoint results, not production acceptance. No application code changed and no suite was rerun for this documentation-only preflight.

# Current Production State

Application: `/home/u494520852/domains/mohamedateff.com/arabictutor_app`. Public wrapper: `/home/u494520852/domains/mohamedateff.com/public_html/arabictutor`. Configured public base: `https://mohamedateff.com/arabictutor`.

Detailed database snapshot: 2026-10-04 19:44:01 UTC, with subsequent read-only continuity verification. Production has 83 InnoDB tables, six purchases, seven ledger entries, eight payments, three refunds, six bookings, two SessionTypes and one administrator. Business maintenance setting is `0`. Do not replace it to manage a deployment window; use the separate deployment maintenance mechanism after authorization.

# Runtime / PHP / MariaDB

| Item | Actual production evidence |
| --- | --- |
| PHP executable | `/opt/alt/php84/usr/bin/php`; default CLI resolves to PHP 8.4 |
| PHP | 8.4.19 |
| Laravel | v13.32.0; production environment |
| Database | 11.8.9-MariaDB-log; InnoDB throughout |
| Composer | 2.8.11, `/usr/local/bin/composer` |
| Host Node / npm | Absent |
| CLI memory / execution | 1536M / 0 seconds (unlimited CLI execution) |
| CLI upload / POST limits | 1536M / 1536M; web-SAPI limits not established |
| App / CLI timezone | UTC / UTC |
| Business reporting timezone | Africa/Cairo |
| System / DB clocks | UTC; observed timestamps consistent with the local UTC inspection clock at network/whole-second resolution |
| DB session / global timezone | +00:00 for inspection / SYSTEM |
| DB max packet | 1,073,741,824 bytes |
| TLS | Authenticated TLS 1.3 for mohamedateff.com; valid Let's Encrypt certificate, expires 2026-11-10 11:16:06 UTC |
| Host filesystem | 52% used; substantial filesystem free space, but this is shared-host capacity, not an account quota guarantee |

TLS was checked without an application request. No NTP configuration or sustained clock-drift guarantee was established. Confirm web limits, account disk/CPU/process quotas and clock drift during the future acceptance window; do not treat generous CLI limits as web upload or hosting-plan guarantees.

# Production Git State

Production branch is `main`, SHA is baseline, and origin matches the expected GitHub repository. There are no tracked diffs. **Existing untracked file: `.env.backup.before-7123f15`.** Its contents were not read or displayed. Preserve it privately; never stage, overwrite, publish or delete it as part of a clean-up/reset. No checkout, pull, fetch, reset or merge was run on production.

# Production Configuration Review

Cached configuration is present. Queue, cache and sessions use database drivers. Mail uses `log`; external email is inactive. Filesystem default `local` points to application `storage/app/private`; `public` points only to `storage/app/public`; backup offsite disk is `s3`.

Sessions: 120-minute lifetime, Secure=true, HttpOnly=true, SameSite=lax, cookie path `/arabictutor`; payload encryption=false. Debug=false. The cookie path is appropriate for the installed subdirectory. Nullable encrypted TOTP model fields do not require enabling session-payload encryption.

| Secret-backed configuration | State |
| --- | --- |
| APP_KEY | PRESENT |
| DB_PASSWORD | PRESENT |
| S3 access key / secret / bucket | PRESENT; offsite delivery has a CONFIGURATION ISSUE, last recorded replication failed |
| MAXMIND_LICENSE_KEY / GEOIP_DOWNLOAD_URL | MISSING |

A stored Telegram bot ciphertext decrypts with the current cached application key. No plaintext was retained or printed. Deployment must preserve this key and the secure environment/configuration backup. The application backup archive does not include `.env`; key recovery requires a separate protected environment copy. Do not run `key:generate`, copy `.env.example` over production, or use `composer run setup`.

# Database Backup Readiness

**BACKUP NOT READY** for deployment. Data and indexes occupy 7,979,008 bytes (approximately 7.61 MiB; engine allocation estimate, not dump size). The existing `backup-full-2026-10-02-132456.zip` is 582,008 bytes, mode 0600, outside public_html. Its SQL is 1,175,283 bytes. Manifest SQL and one included storage-file hash match. Archive integrity was inspected in memory, without extraction or restoration.

Its creation time is 2026-10-02 13:24:56 UTC. It is not a new pre-deployment snapshot, contains no environment configuration, and has never been restored in an isolated environment during this pass. The last offsite replication failed. Existing backups directory is 0755; future protected release snapshots should have a 0700 directory and 0600 secret/database artifacts, after explicit deployment authorization and ownership review.

Available tools: `/usr/bin/mariadb-dump`, `/usr/bin/mysqldump`, `/usr/bin/mariadb`, `/usr/bin/mysql`. Existing BackupService can dump through PDO and package SQL/private/public assets, but creates directories, writes health/audit state and can send notifications; it was not instantiated. Its PDO dump does not export triggers. Production has zero triggers now; the candidate adds four, so do not rely on that dump alone for a complete **post-cutover** recovery image.

Future primary method: protected MariaDB dump including triggers/routines/events, plus source, wrapper, assets, private files and environment backup. Store it under application `storage/app/backups/<release-window>/`, outside public_html; keep an independently protected copy outside the hosting account. A working S3 integration is not required to make that manual protected copy, but an unverified failed replica is not a backup.

Future command template, **not executed**; resolve DATABASE_NAME and create a 0600 client option file privately from the effective connection without putting credentials in arguments or reports:

```bash
umask 077
SNAPSHOT=/home/u494520852/domains/mohamedateff.com/arabictutor_app/storage/app/backups/<approved-release-window>
mkdir -m 700 "$SNAPSHOT"
/usr/bin/mariadb-dump --defaults-extra-file="$SNAPSHOT/database-client.cnf" \
  --single-transaction --skip-lock-tables --routines --events --triggers --hex-blob \
  "$DATABASE_NAME" > "$SNAPSHOT/pre-release.sql"
sha256sum "$SNAPSHOT/pre-release.sql"
```

Check exit status, nonempty SQL, schema/row completeness and manifest checksums before relying on the artifact. Restore with `/usr/bin/mariadb --defaults-extra-file=<protected-client-options> <isolated-database> < <verified-dump>` only into an explicitly isolated database first. Compare ledger counts/money/balances and test source plus original APP_KEY against that restore. A live restore is a separately approved destructive recovery action under maintenance; do not accidentally use production credentials for the drill. Dump/restore for this small dataset likely takes seconds to a few minutes, but there is no measured restore benchmark; reserve 10–20 minutes for copying, hashing and isolated verification.

# Migration State

64 production migration rows. Latest: `2026_10_03_141923_create_staff_and_student_bins`, batch 11. Batch counts 1–11 are respectively 16, 7, 9, 10, 2, 8, 3, 2, 2, 4, 1.

Seven checkpoint migrations are pending:

| File in database/migrations | Purpose / dependency |
| --- | --- |
| `2026_10_03_232534_create_daily_social_metrics_table.php` | Add durable social aggregation table and scope uniqueness |
| `2026_10_03_232535_redact_persisted_request_addresses.php` | Irreversible persisted-IP and audit-payload redaction |
| `2026_10_04_002050_redact_transient_rate_limit_addresses.php` | Address-key cleanup/rekey; requires candidate helper |
| `2026_10_04_031412_create_lesson_materials_table.php` | Lesson workspace schema |
| `2026_10_04_042111_expand_typed_session_entitlements.php` | Nullable expansion, types, allocations and reviews; diagnostic gets package funding |
| `2026_10_04_044431_constrain_typed_session_provenance.php` | Final provenance FK/index/check and four triggers; must follow expansion and approved mapping |
| `2026_10_04_135935_add_two_factor_security_to_administrators_table.php` | Nullable TOTP fields; no enrollment |

Target contains 70 migration files; production also has historical applied `2026_09_23_164114_create_articles_tables`, absent from target, alongside the blogs migration. Thus applying seven adds to **71 migration rows**, not 70. Article tables remain present. Do not remove that migration row or tables to make file counts equal. Read-only set comparison, not an application migration command, established this status. MariaDB DDL is not transactionally rolled back across the entire release; partial migration failure requires inspection before retry, not blind rerun.

# Historical Package Inventory

Six purchases across three current names. Full sanitized evidence and per-purchase decisions are in `PRODUCTION_TYPED_ENTITLEMENT_MAPPING_PROPOSAL.md`.

| Purchase | Allocated | Courtesy | Remaining | Net USD paid | USD due | Expiry |
| --- | ---: | ---: | ---: | ---: | ---: | --- |
| SP-1 diagnostic | 1 | 0 | 1 | 25.00 | 0.00 | 2026-10-15 |
| SP-2 Foundation | 8 | 0 | 8 | 280.00 | 0.00 | 2026-12-15 |
| SP-3 diagnostic | 1 | 0 | 1 | 25.00 | 0.00 | 2026-10-15 |
| SP-4 Foundation | 8 | 0 | 8 | 255.00 | 25.00 | NULL |
| SP-5 Advanced | 1 | 0 | 1 | 28.00 | 0.00 | 2026-10-31 |
| SP-6 Foundation | 8 | 1 | 9 | 280.00 | 0.00 | 2026-12-15 |

No recorded consumption, restoration or forfeiture. All six are active, unexpired and attached to nonsuspended, undeleted student records at inspection. Financial status does not by itself establish eligibility policy or entitlement identity.

# Typed Entitlement Classification

A=0, B=0, C=6, D=0 intentionally approved legacy, E=0 primary purchase classifications. No explicit original offering/type fields or typed wording in grant descriptions exist. All six are ambiguous despite resemblance to current presets. Secondary booking anomalies and SP-4 expiry/payment differences do not justify fabricated mappings.

Leaving all six `legacy_unclassified` is safe archival storage but not a safe business cutover: the candidate exposes zero available typed units for unclassified history. Preserve 28 rights through approved homogeneous mappings or a separately reviewed mixed-rights solution before reopening package scheduling.

# Mapping Decisions Required

Approve exact purchase IDs, offering identity, entitlement type, evidence and reviewer. Confirm SP-6 courtesy shares its purchase type. Confirm SP-4 NULL expiry explicitly and preserve its USD 25.00 due. Do not recompute historical validity or translate generic units using minutes. The current mapper supports one type per purchase; mixed rights need a separate reviewed design.

Manifest fingerprints must come from the candidate **after expansion**, under maintenance, using `entitlements:review`. Do not substitute this report's table hashes. Preview each single-purchase manifest, then apply only explicitly approved manifests, reconcile after each, and reject changed facts. No approval was sought or assumed during inspection.

# Session Type / Duration Review

| Production type | Key | Duration / active | Rule overrides | Booking path / current relation | Future funding decision |
| --- | --- | --- | --- | --- | --- |
| ID 2 Diagnostic & Learning Roadmap | `diagnostic-session` | 60 minutes; active; USD 25 | Sun–Thu 60; Friday 120 | Public diagnostic flow; all six historical bookings use this type, none has a package debit | Expansion explicitly sets package + `one_hour` ×1; historical bookings remain legacy |
| ID 1 1-on-1 Egyptian Arabic Tutoring | `egyptian-arabic-session` | 60 minutes; inactive; USD 40 | Same global rules if activated | No historical booking uses it; no explicit package relation | Expansion leaves legacy; do not reinterpret/activate without owner decision |
| Advanced Conversational | No SessionType exists | Absent | Not applicable | SP-5 exists but no matching lesson product | Approve new active 60-minute package product, `one_hour` ×1 |
| Normal two-hour lesson | No SessionType exists | Absent | Sun–Thu currently override to 60 | Foundation purchases exist but no matching lesson product | Approve active 120-minute package product, `two_hour` ×1 |

Ten enabled availability rules: nine Sun–Thu intervals with 60-minute duration, 15-minute buffer, 12-hour notice and 60-day horizon; Friday rule ID 12 is 09:00–22:00, 120-minute duration, 10-minute buffer with the same notice/horizon. No availability exceptions. Saturday has no enabled interval.

AvailabilityService uses the rule override before SessionType duration, for recurring and special-hour intervals. Confirmed B-5 and completed B-4 are diagnostic bookings of 120 minutes. This independently verifies the production mismatch. Creating a 120-minute lesson without addressing Sun–Thu overrides would reproduce the checkpoint's reverse 120→60 issue.

The candidate public BookingService writes an explicit `direct` funding snapshot; package scheduling separately consumes a typed allocation. Setting the diagnostic SessionType to package-capable does not retroactively fund historical bookings or mean every public diagnostic booking consumes a purchased unit. Acceptance must exercise both paths and confirm the agreed direct-versus-package policy.

Future configuration proposal: after owner approval, remove duration overrides where a rule should honor the selected product, retaining approved hours, buffers, notice and horizon; configure the two absent package lesson products explicitly. Do not replace diagnostic with two-hour rights or silently shorten B-5. If the business intentionally needs overrides, first design/test a product-specific duration policy rather than permitting inconsistent typed promises.

# Upcoming Booking Cutover Risk

Two actionable upcoming confirmed bookings: B-6, 2026-10-06 06:00–07:00 UTC (60 minutes), and B-5, 2026-10-09 12:30–14:30 UTC (120 minutes). Both use diagnostic ID 2 and have zero ledger links. There is **no recorded dependency on ambiguous package credits**, nor evidence justifying a retrospective debit. Original source is Direct / None; that is attribution, not an explicit funding snapshot.

All six bookings default to legacy during expansion unless mapping proves an original package debit. Preserve links, tokens, dates, duration and history. Verify legacy reschedule/cancellation under the candidate: no fresh debit or restoration without provenance; a duration conflict should stop for review rather than silently rewrite B-5. B-2 is marked completed with a future 2026-10-05 schedule; retain and review this anomaly separately. Upcoming by date alone includes completed/cancelled history; actionable upcoming count excludes those statuses.

# Financial / Ledger Baseline

| Baseline | Value |
| --- | ---: |
| Purchases / historical allocated units | 6 / 27 |
| Grant rows / granted units | 6 / 27 |
| Courtesy rows / net units | 1 / +1 |
| Consumption / restoration / forfeiture units | 0 / 0 / 0 |
| Remaining generic units | 28 |
| Payments | 8 rows; USD 1,570.00 |
| Refunds | 3 rows; USD 677.00 |
| Net cash | USD 893.00 |
| Historical final invoices | USD 918.00 |
| Remaining due | USD 25.00, SP-4 |

Reconciliation passes: no negative package balances, mismatched grant quantities, refund above source payment, orphan package/booking ledger rows, orphan package owners, owner mismatches for ledger/payment/booking, refund-package mismatch, consumption missing a booking, or restoration without an equal opposite debit. Zero historical package consumptions make the last checks vacuous, not proof of an inferred booking funding link. Gross overpayments with compensating refunds are preserved history, not repair targets.

Checksum method: PDO ordered rows by ID; baseline physical column order; JSON with unescaped slashes/unicode; SHA-256. PII-bearing values are hashed in memory and not included here. For later comparison select **only the original ordered columns**, excluding added identity/provenance fields; use the same serialization. Store the protected column list with release evidence. Expansion/mapping must not change original values, timestamps, idempotency keys or money.

| Table | Rows | Pre-expansion SHA-256 |
| --- | ---: | --- |
| student_packages | 6 | `b0e2d231e8b2d48b8f3035331688cc78c656f0f6f7274ad323af7090117f8b06` |
| session_ledger_entries | 7 | `6024931276887ad7e540e39e6eb937ec1790c4171b91fea2cea992c6f315852c` |
| payment_records | 8 | `fa1c4f4d2be033167b487749e41362f4cac917aa4b2d8844c865b0e1ab442582` |
| payment_refunds | 3 | `498fe38cd12f2ede19b482cd15fd5df2986cc51434a166a6c5ab48f3dab26a7c` |
| bookings | 6 | `729b571633af0a5658be66ede0055894bbb02b8ac30fca2dae76c56941726d43` |
| migrations | 64 | `b4afa9c0a0743749c275ae412aae5cc05c1dcb440f8cd96a93c56d4c0ac1f48e` |

Approved future lesson configuration changes and new QA transactions need separately recorded expected deltas. Do not demand unchanged hashes after intentional business edits, or hide unexpected changes within those deltas.

# Privacy Migration Impact

| Table / data | Rows inspected | Estimated affected rows |
| --- | ---: | ---: |
| audit_logs.ip_address | 132 | 80 |
| session_reschedules.ip_address | 0 | 0 |
| maintenance_visits.ip_address | 14 | 0 |
| sessions.ip_address | 1 | 1 |
| Audit previous_data/new_data/old_values/new_values | 132 | 0 payload rows; 0 malformed JSON cells |
| Database cache | 103 | 4 expired address-bearing rate-limit keys; 0 active address-bearing keys |

Estimate: 81 persisted address fields and four expired cache rows, at snapshot time; counts can change with live traffic. No raw addresses were printed. Source review confirms only address columns and matching address payload values are redacted; unrelated audit/business fields remain. The payload traversal was evaluated in memory using the migration's rules. Transient cleanup only targets configured-prefix address-bearing throttle/login keys, deleting expired ones or preserving/merging active counters and timers under HMAC keys. No broad cache/session purge is required. Redaction is irreversible by `down()` and must follow a protected restore-ready backup.

# Social Analytics Migration Impact

Raw events: 1,118, oldest 2026-09-18 00:16:13 UTC, newest 2026-10-03 16:42:38 UTC; 996 have non-bot event flags. Existing daily_metrics and daily_country_metrics both contain zero rows. Configured retention is 180 days; retained event range has not reached that boundary, so absence of older events does not establish an active pruning process.

Canonical non-bot social scope contains seven clicks: four WhatsApp, one Telegram, two outbound; no social_link_clicked rows. Existing retained metadata gives five distinct social date/dimension groups across three completed Cairo days. Approximately eight rows would cover those groups plus their three day markers. Aggregating every completed date from September 18 through October 3 would additionally produce empty-day markers (approximately 21 rows total), not fabricated dimensions. The initial estimate is an upper bound until the candidate's actual aggregation scope is run; no rollup/backfill was run now.

The new scoped unique index is small relative to the approximately 7.61 MiB schema. SELECT/CREATE/ALTER/INDEX/REFERENCES/TRIGGER privileges are visible through sanitized SHOW GRANTS flags. Account quota remains unverified. Only retained facts may be aggregated; unknown dimensions retain source-defined unknown/ZZ values. Do not claim recovered social history from already-pruned events. After authorization, aggregate explicitly selected complete dates without `--prune` or `--rebuild-funnel`; recurring scheduling belongs to the separate handoff. Durable schema is technically ready, automation is not evidenced active.

# Private Storage Readiness

`storage/app/private`, `storage/app/backups`, `storage/framework`, `bootstrap/cache` and public build directories exist and are writable by SSH UID 494520852; ownership matches. Directories are 0755. The local disk root is outside public_html. The exposed `public` symlink points to application `public`, and `public/storage` resolves only to `storage/app/public`, not private storage. There is no wrapper-root storage symlink to private files.

Private placement is ready on inspected filesystem evidence. Web-worker effective access, actual web-SAPI upload limits, HTTP download completion and denied direct access still need future acceptance; no synthetic/student file was uploaded. Application validation caps private PDF uploads at 10 MiB and applies passive-PDF checks, regardless of CLI's larger limits. Preserve the private disk root and authenticated download routes; never add a private-storage public link.

# TOTP Readiness

Valid HTTPS, secure/HttpOnly subdirectory sessions, stable APP_KEY, working key decryption and SSH CLI access are established. Server UTC was consistent with the inspection clock; recheck near enrollment for drift. Candidate lock includes Google2FA v9.1.0 and Bacon QR v3.1.1, not yet installed on production. The TOTP migration only adds nullable columns; it enrolls no one and leaves the sole current administrator with MFA disabled.

Emergency recovery command in candidate source: `administrator:recover-two-factor <exact-super-admin-ID> --operator=<verified-operator> --reason=<nonsecret-ticket>`. It requires a trusted interactive terminal and exact `RECOVER <ID>` confirmation, validates Super Admin role, clears factor state and revokes sessions while preserving password/suspension. Do not pass `--no-interaction` to actual recovery. This command was not run; recovery execution/QR rendering remain future isolated acceptance checks. Do not enroll the real owner to prove deployment. TOTP readiness is conditional on dependency installation, production browser acceptance and a protected key backup.

# Dependency / Composer Readiness

All PHP/extension constraints in the target's locked production packages and root requirements match the observed PHP 8.4.19 extensions, checked locally with Composer Semver against production inventory. Target Laravel is v13.32.0, CommonMark 2.10.2, PhpSpreadsheet 5.9.0, Google2FA v9.1.0 and Bacon QR v3.1.1. Current production CommonMark is 2.10.1, PhpSpreadsheet 5.9.0; MFA packages are absent.

Composer 2.8.11 is available, but **proc_open is disabled in the default CLI configuration**. Standard install is not an unconditional green check: use the previously documented, temporary CLI override removing only proc_open from the disabled-functions list, preserving all other restrictions. No php.ini/.env changes are needed. Artifact downloads, network access, Composer scripts and a full install were not exercised against the target on production because replacing vendor is outside this pass.

During future preparation derive the override without displaying secrets, then use the PHP binary and resolved Composer script:

```bash
PHP=/opt/alt/php84/usr/bin/php
DISABLED_FUNCTIONS="$($PHP -r '$items=array_filter(array_map("trim",explode(",",ini_get("disable_functions"))),fn($name)=>$name!=="proc_open"); echo implode(",",$items);')"
$PHP -d "disable_functions=$DISABLED_FUNCTIONS" /usr/local/bin/composer install \
  --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts
```

Prefer preparation outside the serving vendor tree, pinned to the exact target lock. Suppress post-autoload application discovery until required schemas exist, then explicitly run package discovery and platform checks. Do not run update, setup or key generation. Expected installation success is **conditional** on that override, provider downloads and future actual install/platform verification. No safe exact-target remote dry run was performed because the target lock was not uploaded or checked out.

# Asset Deployment Strategy

Host Node/npm are absent. Local Node is v24.19.0 and npm 11.17.0. `public/build` is ignored and no build files are tracked; Git checkout alone will not publish assets. Existing wrapper/build is a separate physical copy from application/public/build. Both contain 31 identical files, aggregate SHA-256 `208da75fd0354ef0bda8df66c37b1334edfc793552a51502fa7e9c984e7ed6c3`; manifests are 0644 and directories 0755.

Future procedure: export/checkout the exact target into an isolated local build directory, run `npm ci` against committed package-lock and `npm run build`, validate every manifest file reference, record source SHA/lock/artifact hashes and package the complete public/build tree. Prepare uploads in private staging, preserve old asset trees, then publish manifest and assets to **both** application/public/build and wrapper/build under maintenance. Preserve existing wrappers, rewrites, public symlink and subdirectory base. Keep old fingerprinted assets through cache expiry rather than deleting them before clients finish loading. Verify hashes and manifest references on both copies before reopening. No assets were rebuilt, uploaded or replaced now. Asset strategy is established; target artifact publication remains unexecuted.

# Browser Acceptance Plan

Execute after separate deployment authorization, preferably first on an isolated restored staging site. Use clearly identified synthetic staff/student fixtures with external mail/Telegram disabled and no real student or owner factor enrollment.

| Check | Explicit acceptance |
| --- | --- |
| Admin / student | Login and permission boundaries, typed balances/expiry, billing filters and ledger history, legacy upcoming booking access |
| Typed booking | One-hour and two-hour funded synthetic bookings; correct allocation, one debit, stable replay; reject wrong type/insufficient balance |
| Cancellation / reschedule | Restore only original typed debit once; no credit minted for direct/legacy booking; verify B-5 duration conflict safely without mutating the real booking |
| Filtered CSV / XLSX | Synthetic date/student/type/package/method/status filters produce matching rows and totals; signed money numeric, credit change separate; staff-only references/notes excluded from student views |
| XLSX completion | Download browser file, open in Excel/LibreOffice, confirm expected filtered rows and numeric refund cells; distinguish complete download from a response header alone |
| Private PDF completion | Attach a harmless small passive PDF to a synthetic completed lesson; authorized student downloads it and byte hash matches; anonymous/other student/direct storage path denied; withdraw and verify denial |
| Clipboard | Copy synthetic identity fields and grouped student details with mouse/keyboard, paste into a native text editor and inspect exact Unicode/newlines; cancel/deny clipboard permission to check fallback |
| Social report | Compare a retained-date canonical source count against durable report/export with all selected filters; no fabricated absent history; no prune during acceptance |
| TOTP disabled | Nullable fields remain unset for existing administrator, ordinary login still works, QR dependency loads in isolated QA; test enrollment/recovery only on approved synthetic Super Admin in isolation |

Record/download evidence without PII or factor secrets. Clean only explicitly isolated synthetic fixtures through supported application actions, preserving audit trail; never delete historical ledgers. Do not send test messages as part of this release acceptance. Recurring Telegram and operational message tests require the separate handoff authorization.

# Zero-Table Startup Finding

**Clean-install technical debt only for this existing production upgrade.** routes/console.php resolves the business timezone while registering schedules; Setting lookup expects settings to exist. This database already contains 84 settings rows and the entire baseline schema. It does not block the inspected upgrade path. A completely empty isolated recovery/install target needs the checkpoint-documented controlled bootstrap before Artisan migration; importing the existing full backup includes settings and avoids that empty-table path. Do not claim empty-database startup was fixed.

# Hostinger Operations Current State

| Operation | State | Read-only evidence / limit |
| --- | --- | --- |
| hPanel scheduler cron configuration | UNKNOWN | No hPanel inspection; crontab binary unavailable |
| Scheduler execution | INACTIVE on observed health evidence | Last heartbeat 2026-10-01 14:08:00 UTC, not current; no running scheduler process observed |
| Queue worker | INACTIVE at process inspection | No queue process/heartbeat; database queue, zero queued/failed jobs; empty queue does not prove a worker |
| Watchdog | UNKNOWN | No heartbeat or running watchdog process, proposed operations directory absent; hPanel unseen |
| Inbound Telegram polling | INACTIVE | Bot commands_enabled=0, update_offset=0, no poll process; no Telegram API request made |
| Telegram notification automation execution | UNKNOWN | Enabled configuration, one historical sent test at 2026-10-02 08:13:11 UTC, stale scheduler; no current end-to-end evidence |
| External mail | INACTIVE | log mailer |
| Offsite/S3 backup | INACTIVE on latest delivery evidence | Last status failed, category s3_replication_failed; credentials present do not establish delivery |

No queue/scheduler/watchdog process was observed in the account process snapshot. Short-lived cron executions can be missed, so the table distinguishes observed execution from unknown hPanel configuration. No cron entries, workers, keys, bot gates, destinations, rules, offsets or services were changed. `HOSTINGER_OPERATIONS_HANDOFF.md` remains unchanged and unexecuted; it is a separate project.

# Exact Staged Deployment Plan

**All steps below are future actions, contingent on separate deployment authorization and closed business/backup gates. None was executed.** A blind pull followed by unrestricted migrate is unsuitable. PHP application code may be installed for controlled CLI preparation while all consumers remain blocked; that is not public application cutover.

## Stage 0 — Approval, maintenance and recoverable baseline

Approve the six purchase decisions, NULL expiry/courtesy handling, lesson products and duration policy. Complete the isolated restore drill, target build and dependency preparation before the window. Reverify GitHub target and production SHA/status; preserve the existing untracked environment backup. Capture new reconciliation/IP/analytics counts and protected hashes immediately before writes.

Enter deployment maintenance with the existing PHP binary and `php artisan down --render=errors::503 --retry=60 --no-interaction` under baseline code; verify the wrapper blocks public/admin/student mutations. If that view is unavailable, use the baseline's proven maintenance template, test its wrapper response before continuing, and do not change the business maintenance setting. Inspect that no background consumer is writing; any active consumer requires an explicitly coordinated pause under deployment authorization. Do not execute the operations handoff. No worker restart/activation is part of this release plan.

Take the fresh protected DB dump and source/config/private-file/asset/wrapper backups described above. Preserve actual `.env`, cached effective configuration, APP_KEY and the preexisting environment backup outside the web root. Record baseline SHA and restore verification. Fail closed if any artifact is missing or the protected independent copy/restore check fails.

## Stage 1 — Candidate CLI preparation and expansion

Under verified maintenance, fetch and verify exact target commit `18ac92c27f96851bbf83d5df68831f76fc22dc76`; fast-forward/checkout only after confirming no tracked local changes. Do not reset/delete untracked files. Install the exact locked vendor using the restricted temporary CLI override and `--no-scripts`, with the prior vendor backup available. Keep every serving consumer blocked.

Run **only** the typed expansion path, not all pending migrations:

```bash
/opt/alt/php84/usr/bin/php artisan migrate --force --no-interaction \
  --path=database/migrations/2026_10_04_042111_expand_typed_session_entitlements.php
```

This creates one_hour/two_hour types, nullable provenance, allocations/review tables and diagnostic configuration. It does not classify purchases or debit bookings. It is not wholly passive: diagnostic SessionType is explicitly set to package funding; therefore old code must never resume serving during this intermediate state. Verify partial DDL and migration record if an error occurs. Stop on any discrepancy.

## Stage 2 — Approved historical mapping

Use candidate `entitlements:review --no-interaction` to capture fresh inventory/fingerprints privately. Prepare **one JSON file per approved purchase**, owner evidence and reviewer; SP-4 NULL expiry must be explicit. Preview each with `entitlements:review <private-manifest> --no-interaction`; only then run the same file with `--apply --no-interaction`. No generic inference or automatic mapping loop with guessed defaults.

Reconcile original-column checksums and 27 granted +1 courtesy =28 remaining units after each mapping. Identity/provenance additions are expected; original amounts, dates, owners, descriptions, timestamps and keys are not. Keep any unresolved history explicitly unclassified and **do not reopen** while active usable rights remain stranded. Mixed rights or unexpected debit history halt this plan for a reviewed forward design.

## Stage 3 — Remaining prerequisite schema, assets and product configuration

While maintenance remains, apply social, persisted privacy, transient privacy, material and TOTP migrations individually with `migrate --force --no-interaction --path=<exact file listed above>`, in that order. Privacy now has the verified backup and candidate TransientRateLimitKey helper; material/TOTP consumers must not run before their tables/columns exist. Check affected counts and nullable disabled MFA state. Installing all required schema before exposing candidate UI is necessary; do not defer material/security schema until after public cutover.

Publish the verified target build to both public copies. Then run explicit `package:discover --no-interaction`, Composer locked platform checks, and targeted production cache rebuilds (`config:cache`, `route:cache`, `view:cache`) with the PHP binary. Do not indiscriminately clear database cache, session tables, rate-limit counters or operational locks. Ensure cached configuration still uses the original production key/paths/drivers. If route/view compilation fails, keep maintenance and diagnose.

Configure the approved SessionTypes and availability duration policy using supported admin actions in the controlled acceptance session, preserving the inactive legacy type and historical booking snapshots. Add an active two-hour package product and one-hour advanced product only with owner-approved identity. Diagnostic remains one_hour ×1. If administrative configuration must occur before final constraints, do it now; verify exact funding/type/units combinations and candidate schema checks.

## Stage 4 — Provenance constraints

After mapping/configuration checks and before allowing business writes:

```bash
/opt/alt/php84/usr/bin/php artisan migrate --force --no-interaction \
  --path=database/migrations/2026_10_04_044431_constrain_typed_session_provenance.php
```

Verify composite debit FK, provenance unique index, SessionType CHECK and all four ledger/booking INSERT/UPDATE guards on MariaDB. Legacy rows with nullable typed provenance remain legitimate; they must not be falsified to satisfy constraints. Repeat ledger/financial checks and migration status: 71 rows expected including the production-only historical articles migration. Create a new protected post-mapping recovery snapshot **including triggers**, recording its distinct restore/code point. DDL partial failure is a maintenance stop, not permission to skip a constraint.

## Stage 5 — Controlled acceptance; application cutover gate

Only now allow a tightly controlled synthetic acceptance session with all schema/dependencies/assets ready. Use a secret maintenance bypass held by the release operator, or a restored isolated staging site first; ordinary users remain blocked. Execute the browser matrix, approved legacy reschedule/cancellation boundary checks in isolation, one_hour/two_hour funding/restoration, disabled TOTP and dependency rendering. No real booking/owner MFA mutation for QA. Record synthetic business writes and expected deltas separately.

Optionally aggregate explicitly selected completed retained analytics dates with `analytics:aggregate-daily --date=YYYY-MM-DD --no-interaction` without `--prune`/`--rebuild-funnel`, under separate approval for that historical aggregation scope. Do not fabricate unavailable dimensions or start recurring automation. This release can create the durable table without implementing the operations handoff.

## Stage 6 — Reopen only after all gates pass

Reverify no usable right is stranded, two public manifests match, existing bookings/links remain valid, key continuity holds and protection/exports/downloads work. Obtain the final release readiness decision; then `php artisan up --no-interaction` restores ordinary serving. Preserve the separate business maintenance setting. Record target SHA, migration/schema/financial checks and UTC window. Do not start scheduler, queue, watchdog, polling, email or S3 jobs in this application release. If acceptance fails, remain under maintenance and use the recovery policy below.

# Recovery / Rollback Strategy

Before schema/data changes, restore baseline code/vendor/assets and wrapper from the protected copies and leave the database intact. After expansion or classification, never resume baseline code merely because migrations were nullable: old generic consumers can ignore typed allocation/provenance. Before new accepted business events, a verified **full pre-release database restore** plus baseline source/vendor/wrapper/assets/config/private files can return to the original point under maintenance, subject to explicit destructive recovery approval.

Typed mapping creates allocation rows; expansion's down() refuses rollback once allocations exist. Privacy cannot restore erased addresses. MariaDB DDL may have partially succeeded without a migration record. Do not use blanket `migrate:rollback`, drop new tables or disable FK/trigger checks to force a downgrade. An isolated restore of the dump can import explicit grants/rows with its own controlled FK handling; that is distinct from bypassing live integrity constraints to continue business writes.

After accepted typed bookings, payments, refunds, cancellations, QA writes mixed with real writes, lesson uploads or MFA enrollment, an automatic pre-release restore would lose post-snapshot facts. **Forward recovery is the default.** Freeze consumers, preserve a fresh dump including triggers and private files, enumerate new events/IDs/financial deltas and repair code/schema with reviewed reconciliation. A deliberate point-in-time restore requires an approved replay plan for every accepted event and external consequence, not generic unit conversion or synthetic re-creation of money.

Once MFA is enabled, old code may bypass the factor or fail on newly encrypted state. Do not downgrade authentication; preserve APP_KEY and trusted recovery access and forward-fix. Private lesson files and database material references must be restored as one consistent point; retain newly uploaded originals privately and reconcile referenced/withdrawn files rather than deleting them. Preserve mapping reviews/audit trail and distinct pre-release/post-mapping snapshots.

# Maintenance Window Estimate

Assumes all owner mapping decisions, duration/product decisions, target assets, locked vendor and isolated restore are completed **before** maintenance. Unresolved approvals have no predictable downtime estimate and are a reason not to start.

| Scenario | Planning window |
| --- | --- |
| Best case | 30–45 minutes |
| Expected | 60–90 minutes |
| Conservative | 2–3 hours, with a pre-agreed stop/recovery point |

The 83-table approximately 7.61 MiB database is small: dump/DDL/mapping are likely seconds to a few minutes each, not benchmarked. Six reviewed mapping previews/applications and reconciliation may take 10–20 minutes; browser acceptance/download/clipboard checks about 20–40 minutes. Longest likely work is dependency downloads, isolated restore verification and acceptance, not the small redaction scan. DDL metadata locks or hosting quotas can extend this materially. Maintenance is required through expansion, mapping, product configuration, constraints and acceptance.

# Deployment Blockers

1. Six ambiguous usable purchases /28 units lack approved explicit mapping. Unclassified typed balances would strand them.
2. Diagnostic 60-minute configuration versus Friday 120-minute override, confirmed B-5 conflict, and missing active advanced/two-hour funded lesson types.
3. No fresh, protected, independently preserved pre-release backup with isolated restore proof and environment/key continuity artifact. Existing archive hashes pass, but that is insufficient recovery proof.
4. Exact production install and two-copy asset publication remain unexecuted; default proc_open restriction requires the controlled CLI override. Confirm web upload/runtime and account quotas during preparation.
5. Owner-approved treatment of NULL expiry, courtesy type, historical legacy bookings and future-dated completion anomaly is outstanding. No historical edit is authorized by this preflight.

Recurring infrastructure gaps are recorded separately; they are not silently solved or falsely treated as active. Release acceptance must not promise automated reminders/email/durable scheduled rollups while those remain inactive/unknown.

# Owner Decisions Required

Approve per-purchase homogeneous offering/type mappings with evidence, or commission a mixed/unclear rights-preservation solution. Confirm SP-4 unlimited expiry intent and existing payment due, SP-6 courtesy type, and preservation of all 28 remaining units. Approve product-specific duration behavior and matching active lesson types; preserve B-5's agreed 120-minute schedule while reviewing future diagnostic behavior. Confirm the existing direct/legacy booking treatment and review B-2's future completion separately without inventing package funding.

Approve a later deployment window and protected independent backup/isolated restore process. Keep production TOTP optional and disabled until separate owner enrollment; acknowledge automation remains a separate developer/hPanel handoff. These are listed decisions, not questions or approvals inferred during this pass.

# Final Preflight Verdict

**NOT READY TO DEPLOY**

Production inspection remained read-only. At **2026-10-04 19:56:17 UTC**, final comparison confirmed the same baseline SHA and 64 migrations, unchanged complete-row hashes for purchases, ledger, payments, refunds, bookings, SessionTypes, availability rules, settings, bots and deliveries; unchanged environment/configuration/lock/wrapper file hashes and timestamps; and unchanged complete artifact-tree hashes for both public build copies. The final account process snapshot again showed no scheduler/queue/watchdog/poll process. The database session reported read-only=1.

Local Git status contains only the two requested untracked planning reports; the historical operations handoff remains unchanged. Live traffic may legitimately change analytics/session/cache rows independently; no global freeze was imposed and no claim about other actors is made. No production mutations, migration, key change, cron/worker change, Telegram/email delivery, MFA enrollment, upload or Git push was performed by this pass.
