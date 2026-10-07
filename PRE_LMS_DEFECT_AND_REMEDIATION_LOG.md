# Pre-LMS Defect and Remediation Log

Accepted runtime candidate: `4296b21368cbc9c564d0cb7a85e29b2ae408e0ee`. Nine application defects repaired; Critical0/High1/Medium8/Low0. Final engineering gates pass. PRE-LMS STABLE BASELINE approved for owner review.

## PRELMS-001 — Mounted Student notification links

- Classification: APPLICATION DEFECT. Severity: MEDIUM. Capabilities: STU-022.
- Reproduction/root cause: Rendering relative persisted links omitted /arabictutor.
- Expected: All saved notification links must work at root and mounted /arabictutor.
- Actual: Relative persisted /student links navigated outside the mount.
- Repair: Render controlled persisted links through url(); preserve fragments and stored relative values.
- Affected code/data: resources/views/student/notifications.blade.php, tests/Feature/StudentTeachingExperienceTest.php. QA-only observed facts are retained; no genuine data repair/deletion.
- Regression/local verification: Meaningful root/mount red→green2cases/8assertions; current full teaching and ownership regressions included.
- Repair/deployment: `40be0b5f2cfc663cbe25afdd712bf41b85500409` (included in final `4296b21368cbc9c564d0cb7a85e29b2ae408e0ee`); smallest bounded source change, no production hot edit.
- Regression/production retest: Existing nine notifications rendered with the correct prefix and opened the owned lesson; root/subdirectory regression passes.
- Status: REPAIRED / DEPLOYED / ESTABLISHED FAILURE RETEST PASSED. Additional constrained branches are explicit in the153-ID report.

## PRELMS-002 — Build-only shell-quote advisory

- Classification: APPLICATION DEFECT. Severity: HIGH. Capabilities: SA-080.
- Reproduction/root cause: concurrently resolved vulnerable shell-quote1.9.0.
- Expected: Current build tooling must have no known high/critical advisory.
- Actual: npm audit reported upstream critical shell-quote1.9.0 advisory in dev-only concurrently.
- Repair: After owner authorization, scope concurrently override to shell-quote1.11.0; one locked dependency changes. No other dependency or Hostinger process setting changes.
- Affected code/data: package.json, package-lock.json. QA-only observed facts are retained; no genuine data repair/deletion.
- Regression/local verification: Actual npm ci --ignore-scripts, concurrently CLI help,20JS tests,build and both zero-advisory audits. GHSA-pqg4-j6r4-53mv / CVE-2026-102422; not exposed Hostinger Node runtime.
- Repair/deployment: `addda6f227f05f206db24a6f1adc1c1eb70ee1f9` (included in final `4296b21368cbc9c564d0cb7a85e29b2ae408e0ee`); smallest bounded source change, no production hot edit.
- Regression/production retest: Owner-authorized targeted override1.11.0 changes one lock entry; actual installed CLI,20JS tests,build and zero-advisory audits pass.
- Status: REPAIRED / DEPLOYED / ESTABLISHED FAILURE RETEST PASSED. Additional constrained branches are explicit in the153-ID report.

## PRELMS-003 — Optional Git metadata blocks Hostinger export

- Classification: APPLICATION DEFECT. Severity: MEDIUM. Capabilities: SA-094, SA-097, SA-095, SA-096.
- Reproduction/root cause: Optional Process invocation throws when proc_open is disabled.
- Expected: Optional provenance metadata must not prevent a hash-verified compatible export.
- Actual: Hostinger disables proc_open; optional Git Process throws before ZIP can be built.
- Repair: Return nullable application_sha when process/Git lookup is unavailable or times out; retain all payload/schema/compatibility/ownership checks.
- Affected code/data: app/Domains/System/Services/DevelopmentDataArchiveService.php, tests/Feature/DevelopmentDataArchiveTest.php. QA-only observed facts are retained; no genuine data repair/deletion.
- Regression/local verification: Actual disable_functions=proc_open red→green1/4; complete archive17/96; live5089-byte ZIP and no-op import succeed.
- Repair/deployment: `addda6f227f05f206db24a6f1adc1c1eb70ee1f9` (included in final `4296b21368cbc9c564d0cb7a85e29b2ae408e0ee`); smallest bounded source change, no production hot edit.
- Regression/production retest: Actual disable_functions red/green regression; real5089-byte production ZIP and no-op import succeed with truthful null application_sha.
- Status: REPAIRED / DEPLOYED / ESTABLISHED FAILURE RETEST PASSED. Additional constrained branches are explicit in the153-ID report.

## PRELMS-004 — Private exports advertise shared caching

- Classification: APPLICATION DEFECT. Severity: MEDIUM. Capabilities: SA-019, SA-026, SA-035, SA-071, SA-073, SA-074, SA-075, SA-076, STU-024.
- Reproduction/root cause: BinaryFileResponse makes XLSX public after construction; CSV omitted no-store.
- Expected: Authenticated sensitive exports must advertise private/no-store in both formats.
- Actual: CSV lacked no-store; XLSX BinaryFileResponse set public after constructor headers.
- Repair: Add no-store to shared export response and call setPrivate on the completed XLSX response.
- Affected code/data: app/Domains/Reporting/Services/ExportService.php, tests/Feature/FilterAwareExportsTest.php. QA-only observed facts are retained; no genuine data repair/deletion.
- Regression/local verification: Fifteen datasets reproduce cache-header failure; complete16/368 passes; actual parsed scoped CSV/XLSX retests private/no-store.
- Repair/deployment: `addda6f227f05f206db24a6f1adc1c1eb70ee1f9` (included in final `4296b21368cbc9c564d0cb7a85e29b2ae408e0ee`); smallest bounded source change, no production hot edit.
- Regression/production retest: Explicit completed-response setPrivate plus no-store; FilterAwareExports16/368 and actual CSV/XLSX private/no-store semantic parity pass.
- Status: REPAIRED / DEPLOYED / ESTABLISHED FAILURE RETEST PASSED. Additional constrained branches are explicit in the153-ID report.

## PRELMS-005 — Assistant navigation advertises forbidden destinations

- Classification: APPLICATION DEFECT. Severity: MEDIUM. Capabilities: SA-001, SA-002.
- Reproduction/root cause: Shared shell links ignored existing Admin/Super permissions.
- Expected: Assistant navigation must expose permitted destinations and omit forbidden actions.
- Actual: Normal Assistant saw Settings and other Admin/Super links; actual Settings click403.
- Repair: Use existing isAdmin/isSuperAdmin predicates for nav/bell/media controls; keep Assistant operations/tasks/notes/Students/bookings/Contacts/leads/forms. No permission change.
- Affected code/data: resources/views/layouts/admin.blade.php, tests/Feature/StageOnePresentationTest.php. QA-only observed facts are retained; no genuine data repair/deletion.
- Regression/local verification: Meaningful red forbidden Dashboard link; final12/116; actual postdeploy role nav plus direct403 and390px drawer/Escape focus proof.
- Repair/deployment: `addda6f227f05f206db24a6f1adc1c1eb70ee1f9` (included in final `4296b21368cbc9c564d0cb7a85e29b2ae408e0ee`); smallest bounded source change, no production hot edit.
- Regression/production retest: Actual postdeploy Assistant has retained eight destinations, no forbidden settings/CMS/finance links; direct Settings still403; StageOne12/116 passes.
- Status: REPAIRED / DEPLOYED / ESTABLISHED FAILURE RETEST PASSED. Additional constrained branches are explicit in the153-ID report.

## PRELMS-006 — Game publish overwrites selected availability

- Classification: APPLICATION DEFECT. Severity: MEDIUM. Capabilities: SA-068, PUB-018.
- Reproduction/root cause: Unconditional publish branch overrides Coming Soon/Disabled.
- Expected: Publish must retain selected Available/Coming Soon/Disabled and only convert an explicit draft publication.
- Actual: Publishing QA Game5 Coming Soon made it Available.
- Repair: Remove the unconditional availability override; retain the existing draft→available branch.
- Affected code/data: app/Http/Controllers/Admin/GameController.php, tests/Feature/CmsDraftAndPreviewMatrixTest.php. QA-only observed facts are retained; no genuine data repair/deletion.
- Regression/local verification: Coming Soon/Disabled meaningful red cases, available control; complete12/104; actual Game5 restored and catalog has no play link. Two old static findings resolve.
- Repair/deployment: `addda6f227f05f206db24a6f1adc1c1eb70ee1f9` (included in final `4296b21368cbc9c564d0cb7a85e29b2ae408e0ee`); smallest bounded source change, no production hot edit.
- Regression/production retest: Coming Soon/Disabled/available regressions pass; actual QA Game5 publish preserves Coming Soon and catalog has no play link.
- Status: REPAIRED / DEPLOYED / ESTABLISHED FAILURE RETEST PASSED. Additional constrained branches are explicit in the153-ID report.

## PRELMS-007 — Background download consumes visible single-use grant

- Classification: APPLICATION DEFECT. Severity: MEDIUM. Capabilities: PUB-015, PUB-016.
- Reproduction/root cause: submitEmail navigates before the displayed download control is used.
- Expected: The visible single-use download control must own the first grant consumption.
- Actual: Request6/download4 occur before visible click; offered link is already consumed.
- Repair: Remove only immediate background navigation in the active successful submitEmail branch. Server single-use/expiry/private/publication checks remain.
- Affected code/data: resources/views/public/resources/show.blade.php, tests/resource-access.test.mjs. QA-only observed facts are retained; no genuine data repair/deletion.
- Regression/local verification: Actual inline method sandbox red→green; rejected429/422/network controls;20JS tests and PHP Resource adjacent65/521; actual733-byte browser file/hash and one download/replay proof.
- Repair/deployment: `addda6f227f05f206db24a6f1adc1c1eb70ee1f9` (included in final `4296b21368cbc9c564d0cb7a85e29b2ae408e0ee`); smallest bounded source change, no production hot edit.
- Regression/production retest: Actual active inline method red/green; real browser733-byte PDF hash matches fixture; explicit click records one download, replay returns gate.
- Status: REPAIRED / DEPLOYED / ESTABLISHED FAILURE RETEST PASSED. Additional constrained branches are explicit in the153-ID report.

## PRELMS-008 — Confirmation time and timezone label disagree

- Classification: APPLICATION DEFECT. Severity: MEDIUM. Capabilities: PUB-007, PUB-012, STU-008.
- Reproduction/root cause: Converted display time uses selected zone but label/offset uses stored customer snapshot.
- Expected: Converted time, city, IANA name and offset must describe the same chosen display instant.
- Actual: New York9:15AM was labeled Africa/Cairo UTC+03:00 from stored snapshot.
- Repair: Render selected customerStart timezoneName and instant offset; do not change stored UTC/customer snapshot.
- Affected code/data: resources/views/public/confirmation.blade.php, tests/Feature/ReschedulePresentationTest.php. QA-only observed facts are retained; no genuine data repair/deletion.
- Regression/local verification: Meaningful summer/winter red cases; complete8/103; actual9:15AM New York UTC-04:00 and preserved booking24 snapshot.
- Repair/deployment: `70b60d3edd02a23cf46a7457a50109e8a5aabf1d` (included in final `4296b21368cbc9c564d0cb7a85e29b2ae408e0ee`); smallest bounded source change, no production hot edit.
- Regression/production retest: Actual9:15AM New York now labels America/New_York UTC-04:00. Summer/winter regression8/103 and unchanged persisted snapshot pass.
- Status: REPAIRED / DEPLOYED / ESTABLISHED FAILURE RETEST PASSED. Additional constrained branches are explicit in the153-ID report.

## PRELMS-009 — Internal game openings recorded twice

- Classification: APPLICATION DEFECT. Severity: MEDIUM. Capabilities: PUB-018, PUB-024, SA-072.
- Reproduction/root cause: Internal catalog inline client callback and GameController each record the same opening with different UUIDs. Actual events1819/1821 match one click/time/slug.
- Expected: One internal opening has one canonical event; external cards retain client/outbound tracking.
- Actual: Actual single internal click created identical semantic events1819/1821 at04:10:18UTC with different UUIDs.
- Repair: Attach catalog client handler only when target is external; internal destination GameController remains the server authority.
- Affected code/data: resources/views/public/games/index.blade.php, tests/Feature/PublicExperienceTest.php. QA-only observed facts are retained; no genuine data repair/deletion.
- Regression/local verification: Meaningful rendered-anchor red1/3; internal/external final2/20 and full release regression; unique live server-API campaign yields exactly one opening1834, negative422/422/404, target restored. Fresh postfix GUI click constrained by browser tool outage.
- Repair/deployment: `4296b21368cbc9c564d0cb7a85e29b2ae408e0ee` (included in final `4296b21368cbc9c564d0cb7a85e29b2ae408e0ee`); smallest bounded source change, no production hot edit.
- Regression/production retest: Meaningful rendered-anchor red case; final internal/external regressions2/20 pass. Current production internal markup has no client handler, external retains both events, destination returns200, invalid/nested/Coming Soon422/422/404. Unique server-API campaign has exactly one game_opened; QA target restored. Browser postfix click constrained by tool outage.
- Status: REPAIRED / DEPLOYED / ESTABLISHED FAILURE RETEST PASSED. Additional constrained branches are explicit in the153-ID report.

## Operations findings

- OPS-001 — Existing S3 offsite replication warning. Protected native local recovery hashes/SQL/snapshots remain valid. No AWS credential or unrelated infrastructure change.
- OPS-002 — Production log mail. Application email paths are exercised locally; actual inbox delivery is not certified. No mail transport change.

## Test/tooling findings

- Missing concurrency provider depended on seeding; a per-test factory/cleanup now retains the same strong overlap assertions. Final26/152 passes; correction in70b60d3.
- Initial cancellation lookup did not reproduce; final current-schema run passes. Failed intermediate runs/import omissions/wrong fixture name,type,page-text and unsupported country query are retained as tooling evidence, not application defects. No weakened assertion or fake production clock.
- Popup notification ERR_ABORTED settled into actual TikTok/WhatsApp destinations; no messages sent. Resource timeout alone was not the reproduced background-grant defect.
- Transient HTTPS/SSH reachability failure recovered. Browser control remained stalled, so QA Game4 link restoration used the normal authenticated endpoint and fresh server state. Postfix actual markup/server-event retest is distinguished from fresh GUI replay. Exact original browser-cookie restoration is not claimed; QA authentication is closed through the normal server logout before delivery.

## Intentional/expected current behavior

Visit-synchronized notifications; manual recurrence/waitlist/room assignment; external linked games separate from built-in tracking; receipts certify recorded manual money, not provider settlement; nonconvertible single-allocation rights; restore-missing refuses differences; deliberate USD5 overpayment warning; destructive tools default-off. Optional features remain separate in MISSING_FEATURES_BACKLOG.md.
