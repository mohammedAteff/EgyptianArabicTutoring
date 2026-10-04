# Pass 5 — Optional Super Admin TOTP Authentication

Completed locally on 2026-10-04. Implementation, automated verification, browser QA and security review are complete. **No commit, GitHub push, deployment, production account change or Hostinger operation occurred.**

## Local state and preservation

The branch remains `main`, with HEAD unchanged at `d15967c88912f34db289ef3621bd9ebef1d56c85`. Pass 5 changes are uncommitted alongside the pre-existing Passes 1–4 work. The earlier feature discovery plan and four pass reports were reviewed before implementation.

A private local database backup, working-tree patch and file-hash inventory were captured before this pass. Comparing the final tree against that inventory found no missing baseline files. Only twelve existing files changed during Pass 5: the Administrator model, staff AuthController, two shared middleware files, AppServiceProvider, bootstrap, routes, admin layout, Composer manifests, concurrency test and its worker. These are the intended integration points. Earlier business logic and reports remain byte-for-byte unchanged from the start of this pass.

`HOSTINGER_OPERATIONS_HANDOFF.md` remains unchanged, SHA-256 `C551CD5E06E671DD31CA7DBA127253F55443CBB26471CAA5B8B8677D76D94286`. Previously deferred production scheduler/queue/watchdog work was not resumed.

## Dependency decision

Installed and tested on PHP 8.4.25 and Laravel 13.32.0:

| Package | Locked version | License | Purpose |
| --- | --- | --- | --- |
| `pragmarx/google2fa` | 9.1.0 | MIT | Maintained TOTP generation, provisioning URI and verification |
| `bacon/bacon-qr-code` | 3.1.1 | BSD-2-Clause | Local SVG QR rendering |
| `paragonie/constant_time_encoding` | 3.1.3 | MIT | Transitive encoding dependency |
| `dasprid/enum` | 1.0.7 | BSD-2-Clause | Transitive QR dependency |

Compatibility, upstream releases, licenses and installed dependency APIs were checked before integration. These framework-independent libraries run with the installed PHP/Laravel versions. No existing dependency was upgraded or removed, and no JavaScript application dependency was added.

[Fortify's current 1.x manifest](https://github.com/laravel/fortify/blob/1.x/composer.json) supports Laravel 13 and uses the same TOTP and QR libraries. [Fortify's documented flow](https://laravel.com/framework/docs/13.x/fortify) was considered, but installing its additional authentication routes/provider/actions would overlap this application's custom staff flow. Integrating its underlying maintained libraries directly keeps the existing guard, model, login form and controller architecture. See [Google2FA](https://github.com/antonioribeiro/google2fa), its [releases](https://github.com/antonioribeiro/google2fa/releases), and [BaconQrCode](https://github.com/Bacon/BaconQrCode).

Composer audit found **no advisory on these additions**. It still reports two pre-existing `league/commonmark` 2.10.1 advisories: [GHSA-97jj-33gv-5xf9](https://github.com/advisories/GHSA-97jj-33gv-5xf9), medium severity, and [GHSA-3q6v-r5mr-hxv8](https://github.com/advisories/GHSA-3q6v-r5mr-hxv8), high severity. The existing stack was not upgraded in this scoped pass; the overall dependency audit is therefore not clean.

## Scope and architecture

Only `super_admin` accounts can enroll, confirm, regenerate, replace or disable MFA. Both route middleware and controller/service checks enforce that restriction. Ordinary Admins and Assistants have no Account Security navigation entry and receive no second-factor challenge. Student authentication continues using its existing flow.

There is no second staff guard or Administrator model. The existing domain Administrator model carries additive security state. `AdministratorTwoFactorService` owns enrollment, factors and atomic recovery; `AdministratorLoginService` owns staged sign-in and session proof; separate controllers handle settings and the guest challenge. A middleware check at the authentication boundary prevents stale sessions and remember cookies from bypassing newly enabled or changed MFA.

That check runs before visitor tracking and maintenance handling. When staff proof becomes invalid, it removes only staff authentication/security state and rotates the session. An unrelated student session in the same browser remains usable. Public requests also continue after stale staff privileges are removed.

## Schema and storage

The additive migration `2026_10_04_135935_add_two_factor_security_to_administrators_table.php` adds eight nullable columns to `administrators`:

| Column | Storage and purpose |
| --- | --- |
| `two_factor_secret` | Encrypted active secret, text |
| `two_factor_recovery_codes` | Encrypted array of password hashes, text |
| `two_factor_confirmed_at` | Confirmation timestamp |
| `two_factor_last_used_step` | Last accepted TOTP time step, unsigned bigint |
| `two_factor_version` | Security-state UUID used to revoke old proof |
| `two_factor_pending_secret` | Encrypted pending secret, text |
| `two_factor_pending_at` | Pending setup timestamp |
| `two_factor_pending_session` | HMAC session binding, 64 characters |

The migration was applied only to the local database and test schema. Existing accounts default to disabled; migration does not force enrollment.

Laravel encrypted casts protect both secrets and the recovery-hash array using application encryption. Recovery plaintext is never stored in the database. Secrets, recovery state, binding, version and used step are hidden from model serialization and excluded from mass assignment. Existing report/export paths do not select these fields.

Protect `APP_KEY` and encrypted database backups together: losing the key makes enrolled secrets unreadable. A partial active state fails closed instead of silently granting password-only access.

## Enrollment and replacement

Every setup request requires the current password; an old authenticated session alone is insufficient. The session rotates before pending setup starts. The pending secret is encrypted and bound to the current session plus account/password/security-state fingerprint, with a ten-minute expiry.

The initial intended response displays a locally rendered QR and manual key. The QR encodes a standard `otpauth://totp` URI; no remote QR service receives it. A subsequent settings GET does not redisplay the key or QR. Invalid confirmation leaves initial enrollment disabled/pending. Confirmation from another session, after expiry or after password/security-state change is rejected.

A valid six-digit code activates the secret, records the accepted step, generates ten recovery codes, rotates the security version and remember token, clears pending state and refreshes current-session proof. Recovery codes appear only in this response. They are not placed in flash or session storage.

Replacing an enabled authenticator requires current password plus current TOTP or an unused recovery code. The original authenticator remains active while the replacement is pending. Only successful replacement confirmation swaps the active secret and recovery set.

## Sign-in, clock and remember policy

Password validation checks the existing provider, allowed staff role and suspension before choosing the next stage. For an enabled Super Admin, password success **does not log the account into the normal guard**. It creates a ten-minute pending state containing only account ID, an opaque HMAC fingerprint and expiry.

The challenge rechecks the fresh account and pending fingerprint. A valid TOTP or recovery factor creates the authenticated staff session, rotates its ID, stores opaque proof and records the completed login. Pending state cannot access privileged routes. Intended redirects are restricted to same-origin admin destinations; external, protocol-relative, encoded traversal and authentication-loop destinations are rejected.

Google2FA verifies six-digit, 30-second TOTP using Unix wall-clock time with a one-step window in either direction. Business Timezone settings are not used. The last accepted step is updated under a row lock, preventing reuse of the same or an older step. After using a code, wait for a fresh code before another sensitive action, or use an unused recovery code.

Enabled Super Admin sign-ins never create a remember-me bypass. Enrollment/security changes rotate existing remember tokens, and the boundary rejects remembered or previously authenticated sessions lacking current MFA proof. Admin, Assistant and disabled Super Admin password-only behavior remains available; the ordinary Admin remember-cookie regression test passes. No trusted-device feature was added.

Password reset retains enrolled MFA and requires the second factor at the next sign-in. Password changes invalidate pending enrollment/challenges and old verified proof. Suspension is checked at password, challenge and service boundaries and cannot be bypassed with a factor.

## Recovery codes and strong settings actions

Each set contains ten cryptographically random codes with twenty random alphanumeric characters separated by a hyphen. Each code is hashed using Laravel's configured password hasher; the hashes are encrypted as a single array.

Verification takes an administrator row lock, checks the hash, removes the matching entry and audits its use inside the same transaction. Two real MariaDB worker processes competing for one code produced exactly one success. Replayed use fails. TOTP and recovery inputs are mutually exclusive.

Regenerating codes requires password plus one factor, replaces the entire set, rotates security version/remember token and refreshes current proof. Old unused codes immediately fail. Disabling requires the same strong verification, clears active/pending secrets, timestamps, recovery hashes and replay state, and rotates/resecures session state. The next sign-in then uses the existing password-only flow. There is no email disable link or web emergency bypass.

## Temporary throttling

All keys reuse the application's HMAC-based transient rate-limit abstraction. Raw IP addresses and emails are not persisted as throttle keys.

| Boundary | Limits |
| --- | --- |
| Password sign-in failures | Existing five attempts per 60 seconds, now using an opaque key |
| TOTP/recovery challenge POST | Five/minute per session, ten/minute per account, thirty/minute per connection |
| Enrollment confirmation and all security writes | Ten/minute per actor, thirty/minute per connection |

TOTP and recovery attempts share the challenge pool. Security writes share one pool, preventing attempts from escaping limits by switching actions. Limits expire automatically; this pass adds no permanent owner lockout. HTTP 429 responses preserve retry headers while avoiding debug payloads.

## Audits and security review

High-level events cover `two_factor_enrollment_started`, `two_factor_reset_started`, `two_factor_enabled`, `two_factor_authenticator_replaced`, `two_factor_recovery_codes_regenerated`, `two_factor_recovery_code_used`, `two_factor_disabled` and `two_factor_emergency_recovery`, alongside completed staff login/logout.

Normal MFA events contain the administrator/entity identifiers, without factor values. Emergency recovery adds only the supplied non-secret operator and reason. Existing Pass 2 audit privacy behavior is reused; staff logout no longer records raw IP.

The final implementation and diff were inspected for disclosure through model serialization, exports, audits, logs, sessions, validation, HTML and exception rendering. Passwords/codes are excluded from flashed input. Factor parameters use `SensitiveParameter`. Unexpected login/challenge/security exceptions log only a generic message and exception class; their responses suppress detailed debug/request content even with local debug enabled. Authentication and validation still use the normal framework flow. Security/challenge responses use `no-store, private` and `no-referrer` headers.

Tests cover malicious debug payloads, safe auditing, hidden/encrypted state, partial/corrupt storage failing closed and no factor values in error session state. Actual browser QA keys/codes were privately compared against the local Laravel log with no match. Temporary private QR/code files and in-memory QA factor values were cleared. Screenshot artifacts contain no secret or recovery code. Secrets appear only in the authorized initial provisioning response; plaintext recovery codes appear only upon enable/regeneration.

## Emergency owner recovery runbook

Use this only if both the authenticator and recovery codes are lost. It requires trusted server console access; no HTTP route invokes it.

1. Verify the business owner's identity outside the affected login session. Record a recovery ticket and identify the exact Super Admin ID through existing trusted records. Never choose an account by guesswork.
2. In a trusted interactive console at the application root, run the command below, replacing `123` with the verified ID and using a real operator/ticket. Do not include passwords, keys or recovery codes in either option.

   ```console
   php artisan administrator:recover-two-factor 123 --operator="Verified operator" --reason="Owner verified; recovery ticket ABC-123"
   ```

3. Read the warning. Enter exactly `RECOVER 123` only for the verified intended account. Any other response cancels without changing security state. Noninteractive execution is rejected; there is no force flag.
4. Successful recovery clears factor state, rotates the remember/security version, deletes database staff sessions for that ID and records the high-level security audit. Password and suspension remain unchanged. If suspension applies, existing suspension procedures still govern access.
5. The owner signs in with the existing password, enrolls a new authenticator and privately stores the new recovery set. If a password reset is also needed, use the existing password recovery process separately.

Success, cancellation and noninteractive rejection were tested with synthetic accounts. This command was not run against a real owner or production account.

**Downgrade constraint:** migration `down()` drops security columns and has no automatic enabled-account guard. Never roll back this schema or deploy older password-only authentication code while an owner relies on MFA. Plan owner access, securely preserve the encrypted state and application key, and perform an explicitly authorized recovery/disable before any downgrade. This report does not authorize a downgrade or deployment.

Any later production rollout must separately preserve `APP_KEY`, use HTTPS/secure cookies and an accurately synchronized server clock, apply the additive migration before serving the new code, and verify owner enrollment/recovery deliberately. None of those production actions occurred in Pass 5.

## Automated verification

All database test groups ran sequentially on local MariaDB 10.11.18. No production dataset was used.

| Check | Final result |
| --- | --- |
| Targeted MFA plus existing student authentication tests | 47 tests, 406 assertions passed; includes 40 MFA cases |
| Full current-schema PHP suite | 848 tests, 7,753 assertions passed |
| Full MariaDB concurrency suite, separately | 16 tests, 76 assertions passed |
| Combined full/concurrency PHP total | **864 tests, 7,829 assertions passed** |
| Existing telemetry JavaScript tests | Eight passed |
| Frontend build | Passed with Vite 8.3.0 |
| Blade view cache | Passed |
| Pint, dirty files, agent format | Passed |
| Git diff whitespace check | Passed; existing Windows line-ending notices only |
| PHPStan | 257 existing diagnostics; **zero added, zero removed** |

The full current-schema run excludes `MigrationACompatibilityTest`, which deliberately exercises an earlier intermediate schema, and executes `MariaDbConcurrencyVerificationTest` separately as in the existing verification workflow. It does not claim to rerun that intermediate migration-stage suite. The targeted result is a subset of the full result, not added to its total.

Representative commands, using the installed PHP 8.4 executable where needed:

```console
php artisan test --compact tests/Feature/AdministratorTwoFactorTest.php tests/Feature/StudentAuthenticationTest.php
php vendor/bin/phpunit --filter '^(?!.*(?:MariaDbConcurrencyVerificationTest|MigrationACompatibilityTest)).*$'
php artisan test --compact tests/Feature/MariaDbConcurrencyVerificationTest.php
node --test tests/telemetry.test.mjs
npm run build
php artisan view:cache --no-interaction
php vendor/bin/pint --dirty --format agent
git diff --check
php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress --error-format=json -v
composer audit --format=json
```

PHPStan was compared against the pre-pass result as a multiset of path, diagnostic identifier and message, including duplicate diagnostics. The same 257 remained. No baseline, ignore or strictness change was introduced. The runtime memory override avoids the local default-memory limit; **PHPStan and the overall Composer audit still fail because of pre-existing debt**, not a clean global static/security result.

## Actual browser QA and evidence

Local Herd browser checks used synthetic staff accounts. A local-only factory seeder supplies a normal Admin QA account, refuses non-local environments and does not overwrite an existing account. It is not called from the production/default seeder.

The following flows were exercised through the rendered interface:

1. Disabled Super Admin password sign-in and Account Security access.
2. Password-confirmed enrollment, rendered SVG QR/manual key, and rejected outside-window code while setup stayed disabled.
3. The actual rendered QR was rasterized and decoded with a standards-compatible QR test implementation. Its standard provisioning URI supplied the secret to the installed Google2FA library for real time-based codes. No remote service was used; a physical phone app was not tested.
4. Valid setup confirmation, ten recovery codes displayed once, and a subsequent settings GET without QR/key/code redisclosure.
5. Logout and password stage with remember selected, challenge display, and privileged-route denial before completing the factor.
6. Valid TOTP sign-in and restored Super Admin access.
7. Recovery sign-in, rejected reuse, and successful use of a different code.
8. Strong regeneration, rejection of an unused old code, strong disable with a new code, and restored password-only Super Admin sign-in.
9. Normal Admin direct password login with no security entry. Assistant password authentication also remained unchanged: the existing dashboard role policy returns 403, while permitted Student Records access returned 200 with no security entry. Existing automated student authentication regressions passed.

The challenge was visually inspected at 1440×900 and 390×844. The mobile page has no horizontal overflow, labeled numeric/one-time-code input, visible focus, recovery alternative, cancel action and controls at least 44 pixels high. The synthetic Super Admin ended disabled with factor state cleared, and all QA staff were logged out. The temporary browser tab was closed and viewport overrides reset.

Secret-free evidence:

- [Enabled security settings during QA](PASS_5_SECURITY_DESKTOP.jpg).
- [Desktop sign-in challenge](PASS_5_CHALLENGE_DESKTOP.jpg).
- [Mobile sign-in challenge](PASS_5_CHALLENGE_MOBILE.jpg).
- [Security disabled at the end of QA](PASS_5_SECURITY_DISABLED.jpg).

## Completion boundary

The optional Super Admin MFA foundation is implemented and locally verified. All earlier pass work remains in the working tree. **No real production Super Admin was modified, no GitHub changes were published, no production migration ran and no Hostinger connection or operation was made during this pass.**
