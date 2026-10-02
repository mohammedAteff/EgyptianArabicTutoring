# Discovery

Date: 2026-10-02. Baseline: `0b72c7dc80cce2f82c2342370f441b4db35c99fb`. This pass addresses only the four follow-up issues. `REMEDIATION_REPORT.md` remains unchanged.

The V5.0 specification, original remediation prompt, previous report, follow-up prompt and five follow-up screenshots were reviewed. The ten-item discovery report was provided before editing. No dependencies or database schema were changed.

1. Footer WhatsApp is an enabled `SocialLink` rendered under **Connect Directly** in `layouts/public.blade.php`. Its destination comes from `getFormattedUrl()`. Existing admin Social Links controls remain in Content & FAQs.
2. Footer configuration is the `social_links` record: `platform`, `url_or_phone`, `default_message`, `label`, `enabled`, and `sort_order`.
3. The new `whatsapp-cta` component already used separate `Setting` keys: `whatsapp_public`, `whatsapp_portal`, `whatsapp_url`, `whatsapp_label`, and `whatsapp_message`. Public and student layouts include it independently.
4. Configuration was already separate. The floating presentation was wrong: desktop showed a permanent text pill; mobile showed a text button that expanded another pill. Both links emitted `whatsapp_clicked` with distinct but less explicit placement names. Footer rendering did not need replacement.
5. Cashier loads a selected student's packages with `payments`, `refunds`, and credit ledger entries. `StudentLedgerService::summary()` calculates net payments and balances. The financial export separately iterates `PaymentRecord` and `PaymentRefund` records.
6. Cashier Blade iterated payments only, despite already loading refunds. Its refund action also prefilled the original payment amount instead of the remaining amount allowed by the ledger.
7. `TrackVisitorSession` returned immediately for analytics-excluded requests. An administrator, excluded internal connection, or synthetic check could view a resource without receiving the two cookies required by `ResourceController`. The controller checked those cookies before email-quality validation and returned internal terminology. The frontend displayed arbitrary server messages.
8. Settings sections 1–6 live inside the existing `max-w-4xl mx-auto space-y-8` container. The newer operational form had been placed after that container closed.
9. The bottom form combined maintenance message, floating WhatsApp destination/switches/translations, conversion goals, and internal exclusions in one miscellaneous card.
10. Repairs reuse those existing views, services, controllers, middleware and tests. Three relationship PHPDoc declarations were subsequently added to the two existing student models to accurately type the loaded payment/package/refund collections; they have no runtime effect.

# Root Causes

| Area | Reproduced cause |
| --- | --- |
| WhatsApp | Incorrect persistent visual design, despite independent storage. The component rendered text pills instead of one circular icon-only anchor. |
| Cashier | Refund records were eager-loaded and exported, but never rendered in payment history. The action used the original payment amount. |
| Resource form | Analytics exclusions inadvertently skipped operational cookie initialization. Cookie validation ran before email-quality validation, and frontend error rendering exposed server exception messages. |
| Settings | The new form was outside the existing content container and grouped unrelated controls in one card with inconsistent styling. |

Baseline reproduction: restoring the original middleware made all three excluded-resource cases fail because `_va_visitor` was absent. Restoring the original Cashier view made the refund-history regression fail because the partial-refund status and refund rows were absent. Modified files were restored after each reproduction.

The screenshot alone cannot recover that browser's cookies or establish which exclusion applied. The exclusion defect was reproduced directly, including an administrator browsing the resource in the running local app. `admin.com`, the screenshot's email domain, published an MX record when checked; the screenshot is not evidence of an invalid individual mailbox. Domain routing does not establish mailbox ownership.

# Changes

| File | Change |
| --- | --- |
| `resources/views/components/whatsapp-cta.blade.php` | One fixed circular SVG anchor, accessible localized label/title, safe-area offsets, visible keyboard focus and blank-label fallback. |
| `resources/js/analytics-telemetry.js` | Explicit `floating_cta` placement, retaining the existing guarded event initialization. |
| `resources/views/layouts/public.blade.php` | Footer event metadata becomes `footer_social` with public/language context. Footer appearance and destination logic are unchanged. |
| `app/Http/Middleware/TrackVisitorSession.php` | Excluded resource GETs initialize UUID cookies and session context without creating analytics visitors, sessions or events. |
| `app/Http/Controllers/ResourceController.php` | Friendly RFC validation, email-quality validation before context rejection, safe 403 and diagnostic logging without cookie values. |
| `app/Domains/Resources/Services/EmailQualityService.php` | Friendly invalid/permanent-email/retry messages; domain and disposable checks remain enforced. |
| `resources/views/public/resources/show.blade.php` | Explicit same-origin credentials, field validation feedback, safe 403/other error fallbacks, and removal of legacy PIN authorization jargon. |
| `app/Domains/Students/Services/StudentLedgerService.php` | Exposes the existing remaining-refundable calculation and shares its cent-based limit with the locked refund write path. |
| `app/Http/Controllers/Admin/StudentBillingController.php` | Computes refundable amounts from already loaded authoritative relationships. |
| `app/Domains/Students/Models/Student.php` | Documents the package relationship's actual model type. |
| `app/Domains/Students/Models/StudentPackage.php` | Documents payment and refund relationship model types. |
| `resources/views/admin/billing/cashier.blade.php` | Associated refund rows, amounts, timestamps, references, reasons, partial/full status and remaining-refundable actions. |
| `resources/views/admin/settings/index.blade.php` | Four aligned operational cards inside the existing settings container. |
| `tests/Feature/FollowupRemediationTest.php` | 17 new focused cases across the four issues. |
| `tests/telemetry.test.mjs` | Floating-placement and single-listener regression. |

# WhatsApp Separation

Footer Social Links retain their existing model, admin location, ordering, labels, styling and formatted destination. No footer record or stored value was migrated or changed. The only footer code change is analytics metadata.

The floating feature uses its own five existing setting keys. Its global destination is shared across languages; localized labels supply `aria-label` and `title`, and localized messages remain in the destination. An empty localized label falls back to the default accessible label. Separate public and portal switches are preserved.

The persistent control is icon-only at desktop and mobile sizes, with a 56 × 56 CSS-pixel target. It is fixed to the viewport and respects bottom/right safe-area insets. Existing maintenance rendering hides it. No new floating panel or settings system was introduced.

Both placements retain the existing `whatsapp_clicked` event. Metadata distinguishes `floating_cta` from `footer_social`, with public/portal context and language. No second outcome event is emitted for one click; the JS regression verifies that repeated initialization does not add a second floating click listener. Existing funnel deduplication is unchanged.

# Refund Ledger UI

Visible refund rows come directly from the package's existing `PaymentRefund` collection, filtered by `payment_record_id` and ordered by `refunded_at`. Each remains under its original payment, with its refund ID, negative amount/currency, actual timestamp formatted using the existing administrator time preference, original payment ID/reference and escaped reason. Multiple refunds remain separate rows.

The service's shared limit is the non-negative minimum of the payment's unrefunded amount and the package's net paid amount. Display and locked write validation use that same limit. Partial refunds show the remaining action limit; fully refunded payments hide the action. The write path still takes its existing transaction/row locks and validates current data, so a stale screen cannot authorize an excessive refund.

No new refund storage or processor integration was added. The export queries and append-only ledger remain authoritative. Net payments subtract refunds, remaining due never goes negative, overpayment stays separate, and credits follow the existing business rules.

# Public Resource Error UX

Email validation precedes the context check. Invalid syntax/domain or definitively unroutable email yields the `email` field error **Please enter a valid email address.** Disposable email yields **Please use a permanent email address.** Transient DNS failure asks the visitor to retry shortly. Existing DNS, null-MX, disposable-domain, caching and grant checks remain intact.

For excluded resource GETs, middleware supplies the same operational cookie/session prerequisites without recording excluded traffic. A genuine missing cookie on POST still fails closed with HTTP 403: **We couldn't process your request. Please refresh the page and try again.** It creates no resource request or grant. Internal logging records the resource ID and presence booleans, never cookie values.

The form sends same-origin credentials, reads structured email/name errors for 422 responses, and uses safe fallback messages for authorization or unexpected server errors. It no longer displays arbitrary server exception text. The same review removed authorization-context wording from the retained legacy PIN error branch. Grant binding, expiration, single-use download protection, ownership checks and locking were not bypassed.

# Settings UX

The existing operational POST form and setting keys remain unchanged. It now sits inside the same `max-w-4xl` container as sections 1–6, with the existing card radius, padding, borders, shadow, spacing and heading styles.

The four new cards are **7. Maintenance Message**, **8. Floating WhatsApp**, **9. Analytics & Conversion Goals**, and **10. Analytics Exclusions & Internal Traffic**. The maintenance mode selector stays in Business Timezone & General. Footer Social Links stay in their existing admin area. One existing operational-save endpoint persists all four cards; validation, caches and settings-controller behavior are preserved.

# Automated Verification

All database test runs were serial. Existing assertions were not weakened.

| Command/check | Result |
| --- | --- |
| `php artisan test --compact tests/Feature/FollowupRemediationTest.php` | 17 passed; 159 assertions. |
| `php artisan test --compact tests/Feature/FollowupRemediationTest.php tests/Feature/OperationalRemediationTest.php tests/Feature/CashierBillingReconciliationTest.php tests/Feature/PublicExperienceTest.php tests/Feature/V5RemediationModuleTest.php` | 76 passed; 551 assertions, including a final rerun after relationship type documentation. |
| `php artisan test --compact --exclude-group=intermediate-schema` | 584 passed; 5,848 assertions. |
| `php vendor/bin/phpunit --configuration=phpunit.concurrency.xml --display-all-issues --no-progress tests/Feature/MariaDbConcurrencyVerificationTest.php` | 10 passed; 45 assertions; real MariaDB/InnoDB connections and locks. |
| `node --test tests/telemetry.test.mjs tests/form-builder.test.mjs` | 4 passed; 0 failed. |
| `npm run build` | Passed, Vite 8.3.0 production assets. |
| `php artisan view:cache` | Passed. |
| `php -l` for all changed/new non-Blade PHP files | 8 passed; no syntax failures. |
| `php vendor/bin/pint --dirty --format agent` | Passed. |
| `git -c core.safecrlf=false diff --check` | Passed. |
| `php vendor/bin/phpstan analyse --memory-limit=1G --no-progress --error-format=json -v` | 358 diagnostics remain, compared with 369 on the unchanged baseline. Not a clean static-analysis result. |

The current-schema test count increased from 567 to 584 because the new feature test contributes 17 parameterized cases. JS tests increased from 3 to 4. No migration changed, so the historical intermediate-schema compatibility group was not rerun; the full current-schema exclusion is the same as the prior report.

PHPStan baseline comparison used the same runtime/configuration while temporarily restoring the seven changed application PHP files to HEAD, then restoring their exact modified bytes in `finally`. Correct relationship generics removed the five initially introduced type diagnostics and eleven existing diagnostics. Six existing Cashier dynamic-property diagnostics now name `StudentPackage` instead of generic `Model`; they concern the same pre-existing fields/assignments. No ignore comments, baseline suppressions or lower rule level were added. Relationship type declarations follow the installed framework's [HasMany API](https://api.laravel.com/docs/13.x/Illuminate/Database/Eloquent/Relations/HasMany.html).

Focused coverage includes separate switches/configuration, French accessible labels/messages, icon-only markup, maintenance 503, distinct analytics placements, multiple partial/full refunds, payment association/timestamp/reference, action limits, unchanged credits, CSV parity, four email-quality failures, three missing-cookie combinations, three analytics-excluded resource flows with valid grants/downloads and no telemetry/mail, and settings persistence/container placement.

# Browser Verification

The running local Herd application was used with synthetic records; no real payment, refund transfer, WhatsApp message or email delivery was performed.

- Desktop 1440 × 1000 and mobile 390 × 844: circular icon-only CTA remained at the same bottom-right viewport coordinates before/after scrolling to the footer and back. Target measured 56 × 56. Keyboard focus was visibly outlined. Footer WhatsApp remained under Connect Directly with its unchanged destination and message.
- Public switch off/portal on: floating CTA disappeared from the public resource page while footer WhatsApp remained. It appeared on student login and the authenticated synthetic student's dashboard. Portal off/public on: it disappeared from the authenticated portal independently.
- Maintenance mode: with the public CTA enabled, the anonymous browser displayed the configured maintenance message and no floating widget. An independent HTTP check returned 503.
- Cashier: added a synthetic $20 payment and recorded a $20 manual refund. After reload, the original $285 payment and its $30 partial refund remained visible; the new payment showed Fully refunded and its separate -$20 refund. Net paid was $255, final invoice $255, due $0 and credits 8. Only the partially refunded payment retained its correctly capped action.
- Downloaded the actual financial CSV and inspected the payment/refund records. It contains `Refund #2 (Payment #2)`, -20.00, and the same stored timestamp as the UI, alongside the original payment/refund. Browser Excel download attempts timed out; no successful manual Excel artifact claim is made.
- Resource: malformed input triggered the browser's familiar email-format validation. Disposable email showed the friendly permanent-email error. A routable synthetic Gmail address unlocked the local resource; the admin resource list showed one request and one download.
- Intentionally removed `_va_visitor` through the browser's developer cookie controls and submitted a routable address. The UI displayed the safe refresh/retry error. Reloading restored the context. No persistent developer override was left in place.
- Settings: saved the moved maintenance message, floating destination/label/message and switches, WhatsApp conversion goal and current-connection exclusion; reloaded and verified persistence. Sections 1, 7, 8, 9 and 10 measured the same x-coordinate and 896-pixel width at desktop size.
- Temporary operational settings were restored, including Live mode, original maintenance message, disabled floating switches/blank destination, empty conversion goals and cleared local exclusions. The existing synthetic student's identity status was restored to legacy-unverified. Browser viewport overrides were reset. Synthetic ledger records and the local resource fixture remain available for inspection; they are not deployment data.

Screenshots were captured from the actual browser, not generated mockups:

- [Desktop footer and floating icon](C:/Users/e/AppData/Local/Temp/awa-followup-whatsapp-desktop.png)
- [Mobile footer and floating icon](C:/Users/e/AppData/Local/Temp/awa-followup-whatsapp-mobile.png)
- [Cashier payment and refund history](C:/Users/e/AppData/Local/Temp/awa-followup-cashier.png)
- [Aligned settings cards](C:/Users/e/AppData/Local/Temp/awa-followup-settings.png)
- [Friendly resource email error](C:/Users/e/AppData/Local/Temp/awa-followup-resource-error.png)
- [Missing-cookie retry error](C:/Users/e/AppData/Local/Temp/awa-followup-resource-context.png)
- [Maintenance page](C:/Users/e/AppData/Local/Temp/awa-followup-maintenance.png)

# Remaining Limitations

- Static analysis retains 358 diagnostics described above; passing runtime checks do not imply a clean PHPStan result.
- The in-app browser did not return an Excel download artifact, or a file path for the resource's initial download action. CSV parity was manually verified; the resource request/download records and automated grant/download checks passed. Existing export implementation was not changed in this pass.
- Email-quality validation establishes domain routing, not individual mailbox ownership, as in the baseline.
- The previously reported Hostinger recurring scheduler/worker configuration gap is outside these four repairs.

# Publication Verification

The earlier explicit GitHub/Hostinger deployment authorization was used for this follow-up. Runtime release `efc5a583f0c3cad1b3be87a812d7a07f3dce713c` was pushed to GitHub `main` and fast-forwarded into the existing Hostinger application checkout. Local fixture databases and uploaded synthetic files were not transferred. No migration, onboarding reinstall, dependency change or production business-setting update was performed.

Before replacing production source/assets, a private server-side snapshot of the previous commit, source, environment, public wrapper and build was created. The existing full database/storage backup service produced a ZIP whose integrity and database-manifest checksum were validated. The asset transfer checksum was validated before extraction.

Database capability verification passed on MariaDB 11.8.9/InnoDB. Configuration, routes and Blade views were cached, workers received the existing restart signal, and the application returned to Live. Checksums confirmed that `.env`, all nine operational setting values in scope and the complete footer Social Links records were unchanged. Existing unrelated environment-backup files were preserved.

Seven public live GET checks returned HTTP 200: homepage, pricing, resources, booking, admin login, student login and Arabic Alphabet. The latter was requested as an analytics-excluded synthetic client: both operational cookies were present, the safe retry script was present, and the old internal error string was absent. The deployed CSS `app-DkZ5rSWm.css` and JavaScript `app-d3et6oWW.js` returned HTTP 200 with exact SHA-256 matches to the local production build.

The live homepage was also inspected in the browser. Existing footer WhatsApp remained in Connect Directly with its preserved destination. Floating visibility retains the production account's existing saved switches; local enabled-state desktop/mobile screenshots document the repaired widget without changing those production settings. Private authenticated refund/settings screens were exercised locally, not by modifying customer records in production.
