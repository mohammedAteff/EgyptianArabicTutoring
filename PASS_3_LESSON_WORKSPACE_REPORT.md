# Pass 3 — Lesson Workspace Foundation

Completed locally on 2026-10-04 (Africa/Cairo). Delivery is uncommitted working-tree changes on `main`; HEAD remains **d15967c88912f34db289ef3621bd9ebef1d56c85**. No commit, push, deployment, Hostinger connection, production migration, or production business-data mutation occurred.

## Baseline and reuse

Repository: `C:/Users/e/Herd/ArabicwAbdallah`.

Read the Pass 3 request, forensic reuse/dependency plan, Pass 1 and Pass 2 reports, and the existing Booking, Student Portal, educational notes, Resource Library, meeting-link, policy, merge/privacy, and test implementations. No equivalent Booking-owned multi-material model existed. `StudentBin` remains the separate educational-notes concern; Resources remain reusable library records. This pass adds a small relation rather than replacing either feature.

Existing Pass 1/2 changes, reports, screenshots, and other untracked files were preserved. Installed versions were checked: PHP 8.4.25, Laravel 13.32.0, Livewire 4.4.5, PHPUnit 12.5.35, Pint 1.32.1, Larastan 3.12.2, Tailwind 4 and Vite 8.3.0. No dependency or static-analysis configuration changed. `.ai/rules` is absent. Laravel, testing, Tailwind and computer-use skills were applied. Boost tools were unavailable; installed source, CLI, official framework/test documentation, MariaDB schema inspection and the connected Herd browser supplied the fallbacks.

## New schema

Migration: `database/migrations/2026_10_04_031412_create_lesson_materials_table.php`.

Only one new table is introduced: `lesson_materials`. No Booking column, financial table or entitlement schema was added or changed by Pass 3.

| Column | Purpose / constraint |
|---|---|
| `id` | Stable material identity. |
| `booking_id` | Required Booking FK, restricted hard deletion. Materials inherit student ownership through Booking; no redundant `student_id`. |
| `created_by` | Nullable Administrator FK; hard deletion of an administrator clears the reference rather than deleting teaching records. |
| `kind` | Required string, one of `private_file`, `resource`, `external_link`, `recording`, matching the application's string-kind convention. |
| `title`, `description` | Required title up to 200 characters; optional description validated to 5,000 characters. Escaped display, no rich HTML. |
| `resource_id` | Nullable Resource FK, restricted hard deletion while referenced. No copied Resource payload. |
| `disk`, `path` | Private PDF locator only; disk must be the existing `local` private disk. User input cannot supply either field. |
| `url` | HTTPS educational/recording destination only; at most 2,048 characters at the request boundary. |
| `student_visible` | Explicit boolean, defaults false. |
| `sort_order` | Unsigned integer, request range 0–10,000; deterministic tie-break on `id`. |
| `withdrawn_at` | Withdrawal tombstone; the row and parent Booking survive. |
| `created_at`, `updated_at` | Standard material lifecycle timestamps. |

The composite `lesson_materials_projection` index covers Booking, withdrawal, visibility and sort order. FK indexes also cover creator and Resource references.

MariaDB's `lesson_materials_payload` CHECK rejects incompatible combinations even when writes bypass Form Requests:

- Active private PDF: non-null `local` disk and path, no Resource or URL.
- Active Resource: Resource required, no disk, path or URL.
- Active external/recording link: URL required, no Resource, disk or path.
- Withdrawn row: visibility false; Resource and URL cleared. Disk/path are both absent, or retained together solely for a private PDF awaiting safe cleanup.

Review caught MariaDB's nullable CHECK-expression behavior: a missing disk could otherwise yield NULL and pass. Explicit non-null guards now reject both active and withdrawn malformed PDF payloads. Seven negative database cases verify the corrected constraint, including that defect.

The migration is applied to the local Herd database. Its corrected constraint was also synchronized in place locally without dropping the table or discarding the synthetic QA materials. Test databases are rebuilt from the corrected migration. The SQL CHECK follows this application's MariaDB engine; SQLite portability is not claimed. Migration rollback drops the new material table and is not a file-cleanup mechanism.

## Implementation and entry points

| Area | Files / behavior |
|---|---|
| Domain | `app/Domains/Booking/Models/LessonMaterial.php`; `LessonMaterialService.php`; ordered `Booking::lessonMaterials()`. |
| Policies | `app/Policies/LessonWorkspacePolicy.php`, `LessonMaterialPolicy.php`, explicitly registered in `AppServiceProvider`. |
| Validation | `app/Http/Requests/StoreLessonMaterialRequest.php`, `UpdateLessonMaterialRequest.php`; `app/Rules/PassiveLessonPdf.php`, `SafeLessonUrl.php`. |
| Controllers | `app/Http/Controllers/Admin/LessonWorkspaceController.php`, `Student/LessonWorkspaceController.php`. |
| Views | `resources/views/admin/bookings/lesson-workspace.blade.php`, `student/lesson-workspace.blade.php`, `components/lesson-materials.blade.php`. |
| Existing consumers | Booking detail, Student Record session table, Student Dashboard controller/history, routes, merge and privacy services. |
| Test data | `database/factories/Domains/Booking/Models/LessonMaterialFactory.php`, optional local-only `database/seeders/LessonMaterialSeeder.php`. |
| Coverage | `tests/Feature/LessonWorkspaceTest.php`. |

The seeder is not registered in `DatabaseSeeder`. It targets only an existing synthetic QA booking in a local environment and does not implicitly create schedule or financial records. The factory also requires an explicitly supplied parent Booking.

| Route | Purpose |
|---|---|
| `GET /admin/bookings/{booking}/lesson` | Workspace, named `admin.lessons.show`. Reachable from Booking detail and Student Record session history. |
| `POST /admin/bookings/{booking}/lesson/materials` | Attach a new material. |
| `PATCH /admin/bookings/{booking}/lesson/materials/{material}` | Edit title, description, visibility and order. |
| `DELETE /admin/bookings/{booking}/lesson/materials/{material}` | Withdraw; retry pending PDF cleanup on the same route. |
| `GET /admin/bookings/{booking}/lesson/materials/{material}` | Authorized staff open/download. |
| `GET /student/lessons/{booking}` | Owned, eligible lesson material page. |
| `GET /student/lessons/{booking}/materials/{material}` | Freshly authorized student open/download. |

Booking detail remains intact. Workspace context reads the existing student, session type, duration, business/student timezone values, booking status, meeting provider/link presence, completion record and existing credit-ledger provenance. It provides no booking status, schedule, confirmation, meeting, payment, refund or credit mutation controls. Raw meeting links are not copied into the material interface.

Metadata edits cannot replace kind, URL, file or Resource payload. To replace a payload, withdraw the old item and attach another; no hidden replacement lifecycle or revision engine was introduced.

## Authorization and student presentation

| Actor | Workspace / preparation | Attach / edit / publish / withdraw | Material access |
|---|---|---|---|
| Active Super Admin | Allowed | Allowed | Active own-system preparation and shared items. |
| Active Admin | Allowed, following existing tutor-equivalent educational permissions | Allowed | Same as Super Admin. |
| Assistant | Denied | Denied | Denied, including private PDFs and preparation links. |
| Verified, active canonical Student | Own eligible lesson only | Denied | Explicitly shared, non-withdrawn material only; Resource must still be published. |
| Guest, suspended/deleted staff, suspended/unverified/deleted Student | Denied or existing login redirect | Denied | Denied. |

Student eligibility is precise: `completed`, or `confirmed` with `end_at_utc <= now(UTC)`. Future/in-progress confirmed, cancelled, no-show, held and pending bookings do not qualify. Soft-deleted bookings are excluded. A deliberately recorded completed booking qualifies by completion state even if its scheduled instant is unusual; materials never change that state.

The student controller queries the authenticated canonical Student's Booking before resolving the nested material and checks the policies again. Foreign IDs, hidden/withdrawn materials and ineligible lessons return safe 404s. Request path/disk/query-string overrides have no effect. Existing student-auth middleware rejects merged-away and suspended accounts.

Past-session history adds compact Materials and Recording lists only when there are matching visible items. No empty material block and no empty recording heading/message are rendered. Multiple items are ordered by `sort_order`, then `id`; recording links are grouped separately. Titles/descriptions are escaped. The shared Blade component operates on an already-authorized projection and performs no database query.

Dashboard and lesson pages load only material ID, Booking ID, kind, title, description and order. Raw URL/path/disk payloads are not loaded into the student history projection and are hidden from model serialization. A six-booking regression confirms one additional material query rather than one query per card. The dashboard and both workspace pages send `Cache-Control: private, no-store`.

## Private PDFs, Resources and external links

New lesson PDFs use `storage/app/private/lesson-materials/{UUID}.pdf` through the existing `local` disk. The original upload name is not used as a locator; there is no predictable public file URL or public-disk copy. Validation checks upload validity, PDF MIME, `.pdf` extension, the 10 MB limit, PDF header/end marker, and obvious HTML/script, action, embedded-file and hex-escaped PDF-name patterns.

Downloads use an authorized controller, a generated slug filename, attachment disposition, correct PDF MIME, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`, and `Referrer-Policy: no-referrer`. Stored paths must match the private lesson namespace. Canonical filesystem paths must remain inside the intended private directory; traversal, absolute path substitutions, null bytes, backslashes and symlink escapes fail closed. Unsigned direct `/storage/lesson-materials/...` access is denied by the existing local-disk serving boundary; no signed public URL is generated.

**PDF limit:** this is bounded MIME/content screening and private attachment delivery, not a complete PDF parser, antivirus engine or sanitizer. Complex/compressed object structures are not fully interpreted; valid PDFs with encoded names may also be rejected by the conservative screen. Only trusted authorized tutors can upload. The implementation does not certify every accepted PDF as malware-free. Existing Resource uploads retain their existing validation independently.

Resource materials reference an existing published Resource and reuse its existing private bytes. There is no duplicate file and no public email-gate bypass route used as student authorization. Every student access still passes lesson ownership and visibility checks. Unpublished, archived, soft-deleted or unsafe legacy-link Resources are denied. Existing public Resource behavior and public request/download analytics are unchanged; lesson delivery does not manufacture public Resource leads/download events. Existing supported Resource file extensions are PDF, ZIP, DOC, DOCX, MP3, WAV and M4A.

External learning and recording URLs require HTTPS, a valid host, and no embedded user/password credentials, whitespace/control characters, encoded control characters or backslashes. JavaScript/data/relative/HTTP URLs are rejected. Link safety is checked again when redirecting, including existing Resource external URLs. The application does not fetch, scrape, synchronize or copy remote content. External links use `noopener noreferrer`; redirects are private/no-store and no-referrer.

Recording is an optional manual link, with the same metadata/visibility lifecycle as other materials. No provider API, import, automatic recording, meeting-room integration or synchronization was added. Application authorization controls disclosure of a destination URL. It cannot revoke a link already copied or downloaded, stop an already-started transfer, or revoke access granted by the external provider. Provider sharing permissions and expiration remain the tutor's responsibility.

## Withdrawal, merge, privacy and audit

Writes lock the parent Booking, then the nested material. Attaching verifies a live, non-merged Student relation and locks a referenced published Resource. These locks coordinate with existing merge/privacy Booking locks. Attachment failure attempts to compensate a newly stored file; storage and the database remain separate transactional systems.

Withdrawal retains a tombstone, immediately hides the item, and clears URL/Resource references without deleting the Booking. Private PDF cleanup occurs after access is withdrawn. Cleanup verifies the opaque namespace and canonical directory before deletion, then clears the locator. Failed deletion leaves a denied private item with a staff-visible retry action; it never republishes it. Retries are idempotent and do not add another withdrawal audit. Hard deletion of a referenced Booking/Resource is restricted to avoid silent file/provenance orphaning; ordinary Resource soft deletion remains supported.

`StudentMergeService` locks material rows alongside existing child records. It keeps material and Booking IDs/file locators unchanged while existing Booking canonical ownership moves to the primary Student. The primary receives permitted access; the merged-away account's session is invalidated. No material `student_id` needs synchronization.

`StudentPrivacyService` redacts material titles/descriptions, clears URL/Resource disclosure, withdraws every material on the locked bookings (including historical soft-deleted bookings), and schedules private PDF cleanup after commit. Original Booking/financial provenance and shared library Resource files survive. Failed cleanup logs only the material ID and retains the private pointer for retry. Successful privacy cleanup is tested against actual file absence.

Audit actions include `lesson_material_attached`, `lesson_recording_attached`, `lesson_material_updated`, `lesson_material_visibility_changed`, `lesson_material_withdrawn`, `lesson_recording_withdrawn`, and `lesson_material_privacy_erased`. The actor, entity and Booking IDs, kind, visibility and ordering supply the audit meaning. Previous data contains only visibility/order. No file bytes, file locator, material description/title, raw URL, recording token or credential is copied into these payloads.

**Cleanup limit:** filesystem deletion is not atomic with database transactions. Permission failures or a process crash after withdrawal/privacy commit can leave a denied private file awaiting retry. There is no new background cleanup job in this foundation. Rollback/force-removal outside these services and restoration of old backups require separate operational file handling; this pass does not claim erasure from historical backups or external providers.

## Verification

All accepted final database runs were sequential against `bolt_landing_test`; the current-schema suite finished before the separate concurrency class. Focused totals below are included in the full suite, not added twice.

| Check | Final result |
|---|---|
| `php artisan test --compact tests/Feature/LessonWorkspaceTest.php` | **53 passed, 226 assertions**. |
| `php artisan test --compact --exclude-group=intermediate-schema --exclude-filter=MariaDbConcurrencyVerificationTest` | **777 passed, 7,256 assertions** after the corrected constraint. |
| `php artisan test --compact tests/Feature/MariaDbConcurrencyVerificationTest.php` | **13 passed, 57 assertions**, after the final full run. |
| Combined current-schema PHP result | **790 passed, 7,313 assertions**. |
| `node --test tests/telemetry.test.mjs tests/clipboard.test.mjs tests/form-builder.test.mjs` | **12 passed**. |
| `npm run build` | Passed, Vite 8.3.0. |
| `php artisan view:cache --no-interaction` | Passed after final UI copy/layout changes. |
| `php vendor/bin/pint --dirty --format agent` | Passed after final PHP changes. |
| `git diff --check` | Passed; non-failing existing CRLF-normalization notices only. |
| Full verbose PHPStan, unchanged level/config, 2 GB limit | **259 before -> 259 after; zero added or removed findings**. |
| Local migration / route discovery | New migration applied; all seven routes registered. |

PHPStan remains repository-wide failing for the 259 existing findings. Zero delta is the result; this is not a static-analysis-clean claim. Comparison normalizes full verbose findings by path, identifier and message, preserving duplicates and allowing line movements. No baseline, suppression, rule-level or dependency change was introduced.

Coverage exercises the staff matrix through policies and HTTP actions; guest/suspension boundaries; private upload bytes and safe download headers; public-path denial; malformed/HTML/script/obfuscated/wrong-extension/oversized PDFs; unsafe URLs and token-free audit; request payload tampering; all seven invalid database combinations; ordering and escaped output; absent/hidden/visible recordings; foreign/ineligible/withdrawn IDs; traversal and ignored path query parameters; Resource reuse/archival/deletion; cleanup failure/retry; merge; privacy; and the bounded history projection. Tests snapshot Booking attributes, payments, refunds, packages and credit ledgers across material operations to prove business records and status remain unchanged.

The historical `intermediate-schema` compatibility group is excluded from the requested current-schema run, following the existing Pass 2 acceptance boundary. It was not changed or rerun. The existing concurrency class verifies broader booking/financial concurrency; it is not a PDF filesystem atomicity certification.

Final evidence is in `C:/Users/e/AppData/Local/Temp/`: `pass3-targeted-final.log`, `pass3-full-final.log`, `pass3-concurrency-final.log`, `pass3-js-final.log`, `pass3-build-final.log`, `pass3-views-final.log`, `pass3-pint-final.log`, `pass3-diff-final.log`, `pass3-phpstan-before.json`, `pass3-phpstan-final.json`, and `pass3-phpstan-delta.json`.

## Herd browser QA

Used the existing local Herd site and synthetic accounts. No separate web server, production account, real student/customer data, or outbound notification was used.

Admin flow: opened Student Record, followed the completed lesson's Workspace entry, inspected read-only context, uploaded a structurally complete one-page synthetic PDF, attached the existing published Resource, added educational and recording links, changed visibility and order, and withdrew a disposable link. A private recording was absent from the student history before publication; it appeared only after explicit sharing. Ordering changes appeared in the student list. Withdrawal returned a safe student 404.

Student flow: signed in through the existing student login, inspected past-session history and the owned lesson page, followed the educational and recording links to their expected synthetic destinations, and confirmed a foreign lesson ID returned 404. Optional/empty recording behavior was checked before publication and is also covered by endpoint tests. The recording QA URL is a harmless placeholder, not an actual provider/video integration.

Desktop was checked at 1280 px for admin and 1440 x 900 for student; mobile at 390 x 844. No horizontal document overflow was observed. New mobile form controls measured 44 px minimum height. Mobile QA caught missing input borders; those were corrected and rechecked after the final build. Current admin/student console error lists were empty. Temporary viewport overrides were reset and agent QA tabs closed.

**Browser download limitation:** PDF and Resource native file-save waits timed out; a documented alternative PDF download wait also timed out. No saved-file result from those browser actions is certified and no browser restriction was bypassed. Automated endpoint tests verified the exact PDF/Resource bytes, attachment names, authorization and headers. Native browser save/open remains an explicit manual acceptance item, separate from the passing endpoint contract and link/presentation checks.

Retained browser evidence:

- `C:/Users/e/AppData/Local/Temp/pass3-browser-qa.json`.
- `C:/Users/e/AppData/Local/Temp/pass3-admin-desktop.png`.
- `C:/Users/e/AppData/Local/Temp/pass3-admin-mobile.png`.
- `C:/Users/e/AppData/Local/Temp/pass3-admin-mobile-controls.png` — corrected form borders.
- `C:/Users/e/AppData/Local/Temp/pass3-student-desktop.png`.
- `C:/Users/e/AppData/Local/Temp/pass3-student-mobile.png`.
- `C:/Users/e/AppData/Local/Temp/pass3-student-workspace.png` — complete page at restored default sizing.

Synthetic local Booking #7 retains four shared QA materials (PDF, Resource, learning link, recording link) for review, plus one withdrawn link tombstone. No existing booking, package, payment or credit record was altered to create these materials. The optional seeder was not run.

## Extension boundary and deferred operations

Future homework/progress features can introduce their own typed Booking-owned relations and policies, reuse this Workspace's authorized context and material relation, and connect assignments to existing Forms/Resources. That is an extension point only: no task lifecycle, grading, milestone or progress schema is implemented here. Materials are delivery metadata, not entitlement transactions or a generic teaching JSON blob.

Typed credits, TOTP, homework, progress/milestones, pronunciation logs, lesson tags, feedback, Staff Notes pin/favorite, a large LMS, Zoom/Meet APIs and recording automation were not implemented. No scheduler, queue, watchdog or hosting changes occurred. `HOSTINGER_OPERATIONS_HANDOFF.md` remains unchanged, SHA256 **C551CD5E06E671DD31CA7DBA127253F55443CBB26471CAA5B8B8677D76D94286**.

This pass stops at local implementation, verification and this report. It does not push or deploy.
