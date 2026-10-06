# Development Data Reset and Restore Runbook

## Scope and prerequisites

These tools are for an explicitly enabled development/launch environment. They default to disabled. No reset, deployment, scheduler setup or Hostinger operation is authorized merely by this runbook.

The server operator must verify the intended database and private storage before enabling `DESTRUCTIVE_ADMIN_TOOLS_ENABLED`. The normal local application remained disabled during Stage 5. `DESTRUCTIVE_ADMIN_TOOLS_REQUIRE_SNAPSHOT` defaults to true; removing the checkbox cannot bypass that requirement. Current supported destructive environment: reconciled MariaDB, database-backed JSON sessions and local private resource/material storage. An unsupported session format/backend, unknown dependent table, unsupported dependency cycle, missing private file or failed recovery snapshot stops the operation.

Sign in as an active Super Admin and open System → Development & Launch Tools. Admin, Assistant and Student accounts cannot use these routes. Every mutation uses POST with CSRF, a current password, a fresh authenticator code or unused recovery code when MFA is enabled, an exact phrase and a five-minute account/session/security-bound preview token. Password, role or security changes invalidate the preview. A used authenticator step cannot authorize another operation.

## Reset workflow

1. Choose the domain and options. Leave portable export and mandatory protected recovery selected.
2. Preview every record group, affected private files, shared files, managed snapshot count and dependency warning. Resource scope is shown again as disabled, immutable checkboxes; return to the tools page to change it.
3. Enter the exact phrase, current password and required factor. Do not submit an old preview after data or files change.
4. Read the completion counts and reconciliation. Student/financial completion requires zero FK orphans and zero financial discrepancies. An empty financial environment is reported as `UNINITIALIZED_DATASET`, not as a meaningful balance history.
5. Download the private portable export from the completed reset/export operation and protect it as private business data.

Operations share a lock with normal backup creation/retention. Double submissions cannot apply the same operation twice. A competing operation or changed preview requires a new preview. Physical auto-increment IDs are not restarted.

| Button | Exact phrase | Removes | Preserves / boundaries |
|---|---|---|---|
| Reset analytics | `RESET ALL ANALYTICS` | Visitors, sessions, raw events, campaign/funnel facts, country/social/daily rollups, maintenance facts and reconciliation facts | Configuration, exclusions, goals/definitions, SocialLinks, content and business bookings. A recorded booking boundary prevents retained old bookings from repopulating country/acquisition analytics; operational business reports still retain them. New collection has a new deduplication generation. |
| Reset students | `DELETE ALL STUDENTS` | Every Student, including archived/merged/deleted identities, and the reviewed FK ownership closure: bookings, finance, forms, teaching records, email verification/authentication state, notifications, student-linked tasks/alerts, planning and owned private lesson files | Administrators, independent Contacts/public leads, shared Resources, business definitions, calendar settings, content and configuration. Student authentication keys are cleared from stored/current sessions while staff login remains. Student audit links/payloads are redacted; system audit history remains. Owned recent-view, outbound delivery and queued email references are handled explicitly. |
| Reset financial test history | `RESET FINANCIAL TEST HISTORY` | All purchases, allocations, actual payments/refunds, typed ledger, installments/renewals/mapping reviews, package-funded bookings and required booking dependents | Students and unrelated direct/free bookings. Package-funded teaching/material history is included rather than left with missing debit provenance. Definitions/configuration remain. The next payment uses the existing authoritative payment service. |
| Reset resources | `RESET RESOURCE TEST DATA` | Only selected Resources, Categories, request/download history and private resource files; owned translations and revision history follow selected definitions | Resource subsystem/page and unrelated content/public Media. Categories default to preserved. Referencing LessonMaterial rows remain, with the Resource link removed and student access withdrawn; Homework links are detached. Required history must be included to delete referenced Resources. Categories owning retained Resources cannot be deleted. Shared private bytes stay. Unchecking Files retains those bytes even if metadata is removed; no automatic unowned-file sweep runs. |
| Reset managed local backups | `RESET MANAGED BACKUPS` | Positively proven, hash-verified local application-managed snapshots and known last-backup/offsite runtime status keys | Retention/storage settings, application code, deployment/emergency/manual/protected files and external/offsite copies. Unknown, malformed or oversized/unverifiable archives are preserved. Protected recovery is created outside the managed set before removal. The managed dashboard returns to Never Run / zero snapshots; next normal Run Backup Now begins the new lifecycle. |

## Export Everything and Custom Export

All module checkboxes start checked for Export Everything. Uncheck modules for Custom Export. Modules cover Student profiles/emails, bookings/planning, forms/answers, teaching, private lesson materials, notifications, purchases/allocations/installments/renewals, payments/refunds, typed ledger, analytics domains, Resources/translations/private files, categories, request/download history, revisions and selected managed snapshots.

Required forward parent rows are included automatically and reflected in the manifest. Student filtering follows ownership through bookings/packages/forms; financial currency filtering follows the financial graph. Analytics dates are business dates converted to half-open UTC boundaries. Required parent history can broaden the result, for example a renewal parent; review the counts. Shared library definitions are not restricted by the Student filter. Private payloads accompany selected Resource/material metadata; there is no detached file-only restore mode. Leaving managed archive checkboxes empty selects all positively identified managed snapshots.

The ZIP includes a versioned manifest with application version/available Git SHA, UTC creation time, business timezone, included/excluded modules, row counts, schema compatibility fingerprints, external dependency fingerprints, private-file inventory and per-entry hashes/sizes. Limits: 50,000 database rows, 100 MiB ZIP, 256 MiB expanded payload and 10,000 entries. Oversized operations require a smaller selection or reviewed offline work, not removal of the safety limits.

Portable exports exclude administrators/users, configuration/settings, APP_KEY, staff passwords, TOTP/recovery secrets, authentication sessions/challenges/tokens and Telegram configuration. Booking confirmation tokens are regenerated on restore. Portable exports still contain the selected private business data; store them accordingly.

## Import preview and selective restore

1. Upload a compatible portable ZIP. This creates only a private inspection copy and preview; no business rows are written.
2. Review manifest/hashes/counts, conflicts and missing dependencies.
3. Select modules via checkboxes and click Preview chosen restore. The inspection token alone cannot authorize restore.
4. Review the chosen immutable module list, then enter `RESTORE MISSING DEVELOPMENT DATA`, current password and required factor.
5. Check completion and reconciliation. Repeat imports skip identical rows and retain original stable IDs/idempotency keys, so payments, refunds, grants and booking debits do not duplicate.

The only policy is Restore missing / skip identical / refuse differences. There is no overwrite, replacement, automatic ID remapping or automatic legacy entitlement classification. Different current rows, unique emails/slugs/idempotency identities, changed external staff/configuration dependencies and missing parent modules stop confirmation. This is not an arbitrary cross-environment migration system: restore requires reviewed matching identities/schema/configuration.

ZIP entry names, symlinks, duplicate/unmanifested entries, unknown columns/tables, credential fields, malformed identities, unsupported versions, hash mismatches and size limits are checked before persistence. Private payload owners/hashes are checked and new application-private paths are generated. Archive paths are never extracted or used as destinations. Existing different private bytes are never replaced. File writes/deletes are compensated on failure. FKs and typed booking triggers stay enabled throughout normal reset/portable restore; cyclic typed bookings are staged validly, their original ledger restored, then their complete funding snapshot restored atomically.

Resource restoration does not automatically republish LessonMaterials withdrawn by an earlier Resource reset. Review and explicitly share those materials using the existing teaching workflow.

## Managed backup portable copies versus protected system recovery

Managed snapshot export converts only reviewed resettable-domain rows and owned private resource/material bytes into sanitized portable snapshot payloads. SQL is parsed, never executed. Unsupported native dump syntax fails closed; raw database/configuration/staff credentials and unrelated files are excluded. Restoring the Backups module adds idempotent managed portable snapshot archives; it does not restore their rows into the live database. Select the ordinary domain modules to restore live data. Full raw-system recovery is a separate operation outside this UI.

Protected pre-reset recovery lives under the configured private `development-data/recovery/<identity>` directory. It contains the existing full raw database dump, verified selected-domain private-file copies, managed archive copies for backup reset, hashes and a schema/FK/index/generated-column/trigger inventory. It can contain credential state because it is full internal recovery material; it has no portable download route and is never copied unchanged into a portable export. APP_KEY/environment files are not copied; preserve server secrets through the application's established secret-management process.

Do not execute a raw system dump or the legacy full `backup:restore` command as an unreviewed recovery step. The inherited SQL restore path rebuilds tables and requires explicit review for generated columns and typed triggers. Full system recovery is not certified by Stage 5's portable round-trip tests. Validate a reviewed recovery in an isolated environment using the captured schema/trigger inventory, current migrations, business reconciliation and verified file hashes before any real operation. This runbook does not authorize a production restore or deployment.

## Failure, retention and hygiene

A wrong phrase/password/factor, stale/expired/foreign token, changed data/files, conflict, unknown ownership or failed mandatory snapshot causes refusal. Review the visible error and build a fresh preview. File/reconciliation failure rolls back database writes and restores removed files from quarantine. If compensation itself fails, stop and recover verified bytes from the protected recovery set before another operation. Partial protected snapshots remain private for review; they are not labeled as completed operations.

The latest 20 owned operations appear in the tools page. At most 20 unexpired previews can be open per actor. Tokens expire after five minutes. Operation records store only safe scope/counts/digests/summaries, not password/factor/plain operation tokens or exported rows. Inspection/export files and protected recovery are deliberately retained for manual review; no automatic cleanup scheduler or protected-recovery pruning is installed. Manage retention through a reviewed operator process, preserving needed exports/recovery and safe audit evidence. Do not blanket-delete arbitrary private/backup directories.

Disable the server flag after the intended development/launch cleanup and verify that both navigation and direct routes are denied. Do not change Telegram bot/destination, enable commands/polling, configure workers or execute the Hostinger handoff. Stage completion uses only the separately owner-authorized one-off status notification.
