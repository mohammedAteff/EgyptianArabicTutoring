# Production QA Data Plan

QA Run: **QA_ACCEPTANCE_2026_10_06_A**. Human-readable records use **QA ACCEPTANCE**; suffixes identify this run. Governing matrix SHA-256: `57ce8326521f95011d02eaf399ea50e9fec9e136f7eb59e352ea4155bb4a5dd7`. All 153 permanent IDs parsed; 139 require synthetic data. This plan is written before dataset mutations.

**Baseline.** Production application: `7cb9a236b60350cfa134c2d6c94e14df1eb20c6b`; documentation HEAD/origin: `e47026645f38fb0b8a0b77e85b5110b6a3714fdd`. Only four Markdown files differ; production tracked source is clean. PHP 8.4.19 / Laravel 13.32.0 / MariaDB 11.8.9. Fresh production inventory captured privately. Maintenance off; destructive flags remain off; no queued/failed jobs. Existing protected predeployment archive hash reverified: `0454ac9211c980c175e5145b5d4d16b4310e2e512f11de1dda4366102e8b6ea3`, 23,228,252 bytes, mode 0600. It is the old-source recovery snapshot, not a new current-release backup. Ordinary managed backup manifest also verifies. Existing offsite S3 warning is unchanged. Creation does not invoke reset and the reset-only mandatory recovery procedure does not require another full backup.

**Creation boundaries.** This prepares data; it does not pass full capability acceptance. Domain services retain real time, availability, notice, typed provenance, policy snapshots, locking/idempotency and ownership. No Student portal visit/synchronization is allowed while preparing notification events. No dependency, source, deployment, environment or scheduler change. No fabricated provider payment, raw IP, genuine owner edit or destructive operation. Temporary private setup/receipts remain outside Git; secrets never enter these documents.

**Readiness accounting.** “Prepared” means the capability’s required disposable starting entities exist and are coherent. It does not mean its mutation, browser download, negative branch or destructive action passed. Temporal predicates wait for actual UTC time. Any missing fixture path, isolated clone, archive artifact or genuinely necessary configuration authorization is explicitly uncovered/conditional in the final reconciliation; no completion notification while required coverage is missing.

**Dependency order.** Q01/Q02 → Q09/Q07/Q08 → Q03 → Q04 → Q05/Q06/Q10 → Q11 → remaining gated prerequisites. Q15 depends on clarification. START precedes production mutations; FINAL follows integrity, documents and push.

## Q01 — Six normalized identities and empty/foreign-owner prerequisites

| Item | Planned state |
|---|---|
| Capability IDs | SA-019, SA-020, SA-021, SA-022, SA-023, SA-024, SA-025, SA-083, STU-001, STU-002, STU-003, STU-004, STU-008 |
| Prerequisites | Matrix prerequisite rules for these IDs; healthy pinned baseline |
| Records/entities | Six Students A–F; A teaching, B finance, C incompatible/expired, D planning, E/F identity/privacy pair. E remains empty initially; independent synthetic emails and reserved fictional phones. |
| Required starting state | Zero production Students. Existing genuine Contacts and owner untouched. |
| Expected state after creation | Valid normalized verified identities, synthetic DOBs and IANA zones; no Student portal visit or notification synchronization. E/F are separate valid identities, not uniqueness violations. |
| Scenario dependencies | None |
| Cleanup ownership | Reset Students owns their dependent graph; independent Contacts require manual cleanup. Merge/anonymization is deferred. |
| External side effects | No outbound mail. Secondary-email verification is a later acceptance action. |
| Temporary configuration/original value | None. |

## Q02 — Dedicated staff roles and factor ownership

| Item | Planned state |
|---|---|
| Capability IDs | SA-001, SA-013, SA-014, SA-015, SA-016, SA-017, SA-018, SA-082, SA-087, SA-088, SA-097 |
| Prerequisites | Matrix prerequisite rules for these IDs; healthy pinned baseline |
| Records/entities | Two QA Super Admins, one QA Admin and one QA Assistant. Own MFA enrollment on one QA Super Admin only, credentials held outside Git. |
| Required starting state | One genuine owner account with its original credentials/factors. |
| Expected state after creation | QA accounts available for authorization and factor tests. Genuine owner unchanged; no owner credential rotation. |
| Scenario dependencies | None |
| Cleanup ownership | Manual reviewed staff cleanup after acceptance; factor/challenge/session graph belongs to those QA accounts. |
| External side effects | No reset-link email; actual password-recovery transport remains Log mail. |
| Temporary configuration/original value | No global authentication settings or destructive flag changes. |

## Q03 — Typed purchases and coherent manual cash states

| Item | Planned state |
|---|---|
| Capability IDs | SA-026, SA-027, SA-028, SA-029, SA-030, SA-031, SA-032, SA-033, SA-034, SA-035, SA-036, SA-037, SA-038, SA-099, STU-005, STU-006, STU-024, STU-025 |
| Prerequisites | Matrix prerequisite rules for these IDs; Q01, Q02 |
| Records/entities | Small named one_hour/two_hour purchases for B, C and F; unpaid/partial/full/overpaid/full-refund/partial-refund states; one EUR purchase; canonical grant/allocation/payment/refund/installment/renewal rows; one nondefault QA payment method. |
| Required starting state | Zero purchases, allocations, payments or refunds. |
| Expected state after creation | Each purchase has one explicit type; mixed rights remain separate. Forecast installments never become cash records. Money is manually recorded synthetic cash with QA references; no provider transaction. |
| Scenario dependencies | Q01, Q02 |
| Cleanup ownership | Reset Students includes owned money/ledger/planning rows. Independent Financial reset overlaps and includes funded booking dependents; QA PaymentMethod requires manual cleanup. |
| External side effects | No gateway call, payer contact or external transaction. |
| Temporary configuration/original value | QA method is nondefault. Existing real defaults untouched. |

## Q04 — Valid booking lifecycle and immutable funding

| Item | Planned state |
|---|---|
| Capability IDs | PUB-008, PUB-009, PUB-010, PUB-011, PUB-012, PUB-013, SA-003, SA-004, SA-039, SA-040, SA-041, SA-042, SA-043, SA-047, SA-051, SA-059, SA-085, STU-007, STU-009, STU-010, STU-011, STU-012, STU-023, STU-028 |
| Prerequisites | Matrix prerequisite rules for these IDs; Q01, Q02, Q03, Q09 |
| Records/entities | Minimal public/direct and package-funded bookings, hold and events, one reschedule, cancelled/completed/no-show states, original debit/restore/policy facts. Harmless QA room only if assignment requires it. |
| Required starting state | Existing availability/notice/policy and genuine calendar definitions. |
| Expected state after creation | All new slots generated and revalidated by current services. Completed/no-show synthetic lessons use supported status transitions; actual UTC dates are retained. Meeting reveal/ended-confirmed/late-cancel predicates wait for real time; no backdating or clock replacement. |
| Scenario dependencies | Q01, Q02, Q03, Q09 |
| Cleanup ownership | Reset Students for owned graph; independent direct Contact/history reviewed manually. Financial reset only includes package-funded closure. |
| External side effects | Existing admin inbox may receive QA notices. Inspect Telegram producers/rules before booking; no extra live sends authorized. |
| Temporary configuration/original value | No notice, horizon, timezone, policy, meeting-reveal or genuine availability weakening. Same-day must reject under current notice when unavailable. |

## Q05 — Small manual recurrence, waitlist and Student holiday

| Item | Planned state |
|---|---|
| Capability IDs | SA-044, SA-045, SA-046, SA-047, SA-048, SA-049, SA-050, STU-026, STU-027 |
| Prerequisites | Matrix prerequisite rules for these IDs; Q01, Q02, Q03, Q04 |
| Records/entities | Dedicated labeled package lesson types if existing types cannot supply both requirements; one two-occurrence recurring plan, small waitlist interests, narrow future Student holiday. |
| Required starting state | Current valid weekly tutor rules; no QA planning rows. |
| Expected state after creation | Manual plan/occurrences use real availability and provenance. Holiday affects only D, not global calendar. Future blocked/notice/DST branches are acceptance actions with explicit expected outcomes. |
| Scenario dependencies | Q01, Q02, Q03, Q04 |
| Cleanup ownership | Reset Students owns plans/occurrences/interest/holiday; dedicated SessionTypes manual cleanup after all references removed. |
| External side effects | No recurrence worker, auto-scheduling or waitlist notification. |
| Temporary configuration/original value | Reuse existing weekly rules. No global exception/availability change unless a scenario cannot otherwise be prepared. |

## Q06 — Shared and private teaching graph

| Item | Planned state |
|---|---|
| Capability IDs | SA-052, SA-053, SA-054, SA-055, SA-056, SA-057, SA-058, SA-059, SA-060, STU-011, STU-012, STU-013, STU-014, STU-015, STU-016, STU-017, STU-018, STU-019, STU-023 |
| Prerequisites | Matrix prerequisite rules for these IDs; Q01, Q02, Q04, Q07 |
| Records/entities | A completed lesson; passive private PDF/shared Resource/safe link/unshared recording materials; shared/private homework, plans/milestones, errors/tags/preparation/notes; small feedback only through supported Student workflow if no portal visit is required. |
| Required starting state | A identity and booking; published QA Resource with owned private payload. |
| Expected state after creation | Real owner IDs and sharing flags; staff-only preparation never Student-visible. Qualifying unread notification events exist but sync has never run. |
| Scenario dependencies | Q01, Q02, Q04, Q07 |
| Cleanup ownership | Reset Students owns teaching and lesson payload; shared library payload belongs to Resource cleanup. Remove owned bytes only after reference review. |
| External side effects | No recording provider, live video or external file sharing. |
| Temporary configuration/original value | None. |

## Q07 — Small Resource library, gate and ownership graph

| Item | Planned state |
|---|---|
| Capability IDs | PUB-014, PUB-015, PUB-016, SA-002, SA-021, SA-022, SA-061, SA-062, SA-092 |
| Prerequisites | Matrix prerequisite rules for these IDs; Q01, Q02 |
| Records/entities | One QA category; one published private-file Resource and one hidden draft; harmless passive PDF; synthetic gate Contact/request/download facts through existing services; valid independent duplicate Contact signals if allowed. |
| Required starting state | One genuine Resource/private file and two genuine Contacts preserved. |
| Expected state after creation | All visible synthetic content labeled QA ACCEPTANCE. Gate creates real request/grant facts; tokens stay outside Git. Shared teaching references use same library Resource rather than copied payload. |
| Scenario dependencies | Q01, Q02 |
| Cleanup ownership | Reset Resources owns definitions/translations/request/download/payload; independent Contacts/manual public media not assumed removed by Student reset. |
| External side effects | No external email or Telegram lead alert. No genuine Resource payload replacement. |
| Temporary configuration/original value | Only new QA definitions/publication; genuine records untouched. |

## Q08 — Minimal CMS publication and revision states

| Item | Planned state |
|---|---|
| Capability IDs | PUB-004, PUB-017, PUB-018, PUB-026, SA-063, SA-064, SA-065, SA-066, SA-067, SA-068, SA-069, SA-077 |
| Prerequisites | Matrix prerequisite rules for these IDs; Q02, Q07 |
| Records/entities | One QA public Media image, Page, FAQ, Blog plus old-slug history, available/coming-soon Games, minimal promotion display variants and French/German draft/live translations as required. |
| Required starting state | Genuine content/configuration preserved; zero Blogs/promotions/media. |
| Expected state after creation | Clearly labeled QA content, draft/live distinction and real revision/translation state. Config-only acceptance uses captured original settings and QA actors; no owner brand overwrite. |
| Scenario dependencies | Q02, Q07 |
| Cleanup ownership | Manual CMS/media/promotion cleanup, Resource reset for category/Resource translation ownership. Restore any scoped temporary setting exactly. |
| External side effects | Clearly marked synthetic public content may be visible. No external game/contact target execution. |
| Temporary configuration/original value | No permanent global copy, social destination, WhatsApp or homepage settings changes. |

## Q09 — Published follow-up questionnaire with versions and drafts

| Item | Planned state |
|---|---|
| Capability IDs | PUB-010, SA-070, SA-071, STU-020, STU-021, STU-022 |
| Prerequisites | Matrix prerequisite rules for these IDs; Q01, Q02 |
| Records/entities | One QA follow-up Form, a small conditional visible/private question set, two versions, one draft and one submitted response/revision. Reuse existing real pre_booking Form for any real public booking intake. |
| Required starting state | Two genuine Forms/triggers unchanged. |
| Expected state after creation | New QA trigger after_booking/after_reschedule limits assignment; responses validated by FormSubmissionService without Student portal GET. Original pre_booking publication untouched. |
| Scenario dependencies | Q01, Q02 |
| Cleanup ownership | Student reset owns answers/submissions/revisions; QA Form/version/questions/triggers manual cleanup after dependents. No pre_booking trigger deletion. |
| External side effects | No notification background delivery; assignment later synchronizes on portal visit. |
| Temporary configuration/original value | No replacement of genuine active intake form. |

## Q10 — Daily staff productivity and notification prerequisites

| Item | Planned state |
|---|---|
| Capability IDs | SA-004, SA-005, SA-006, SA-007, SA-008, SA-009, SA-010, SA-011, SA-012 |
| Prerequisites | Matrix prerequisite rules for these IDs; Q01, Q02, Q04 |
| Records/entities | Small shared/private Notes; QA-only personal pin/favorite; open/assigned/completed tasks; active/resolved operational alerts; personal saved views and recents through existing services; canonical booking admin notification. |
| Required starting state | One genuine staff Note and no tasks/preferences/recents. |
| Expected state after creation | Operational facts have exact QA actor/Student IDs; no Student access to staff text; no owner preference changes. |
| Scenario dependencies | Q01, Q02, Q04 |
| Cleanup ownership | Student reset owns linked alerts/tasks; independent staff notes/preferences/views/recents and inbox manual cleanup using manifest IDs. |
| External side effects | Internal admin inbox only. |
| Temporary configuration/original value | None. |

## Q11 — Tagged real public analytics observations

| Item | Planned state |
|---|---|
| Capability IDs | PUB-023, PUB-024, SA-072, SA-073, SA-074, SA-075, SA-089 |
| Prerequisites | Matrix prerequisite rules for these IDs; Q04, Q07, Q08 |
| Records/entities | Bounded current browser visit/attention/social/game/Resource/campaign events, acquisition/funnel facts and genuine server country result; event IDs and parent IDs recorded. No invented countries or historical visits. |
| Required starting state | Existing genuine analytics facts retained. |
| Expected state after creation | Deterministic observed events labeled by QA campaign/page/entities. Current-day data prepared; a complete-day rollup waits for the existing authorized scheduler/time. Two-country and historical branches require later isolated observations if not naturally available. |
| Scenario dependencies | Q04, Q07, Q08 |
| Cleanup ownership | Reset Analytics is whole-scope and needs separate disposable clone authorization. Production cleanup must review exact QA IDs and shared daily rollup overlap. |
| External side effects | Do not open/send external WhatsApp/Telegram/social messages. No intentionally stored raw IP. |
| Temporary configuration/original value | No exclusion/retention/geo-IP/provider/worker changes. Respect staff exclusion; do not clear genuine owner auth. |

## Q12 — Maintenance analytics prerequisites without service disruption

| Item | Planned state |
|---|---|
| Capability IDs | SA-076 |
| Prerequisites | Matrix prerequisite rules for these IDs; Q02, Q11 |
| Records/entities | QA actor plus tagged visitor context; controlled maintenance visit requires a separately bounded maintenance window or isolated clone. |
| Required starting state | Production maintenance is off, existing genuine visits present. |
| Expected state after creation | Do not relabel genuine visits as synthetic. If safe fixture creation has no supported isolated path, report this ID uncovered rather than inventing traffic. |
| Scenario dependencies | Q02, Q11 |
| Cleanup ownership | Reset Analytics includes maintenance facts; global reset not executed. |
| External side effects | Production visitor disruption must not occur implicitly. |
| Temporary configuration/original value | No maintenance toggle at data preparation unless explicitly approved as a narrow window. |

## Q13 — Existing verified Telegram and safe history dependencies

| Item | Planned state |
|---|---|
| Capability IDs | SA-084, SA-085, SA-086 |
| Prerequisites | Matrix prerequisite rules for these IDs; Q02, Q04, Q11 |
| Records/entities | Reuse existing verified bot/destination/rules; exactly one run START and one final status delivery. No synthetic bot, token, destination or read command. |
| Required starting state | Existing bot/destination/rule state and prior Sent proof captured safely. |
| Expected state after creation | Two safe delivery receipts only. Later fake-transport rule/security acceptance is isolated; never expand live recipients/configuration. |
| Scenario dependencies | Q02, Q04, Q11 |
| Cleanup ownership | Safe status/audit records retain normal application history; manual QA lifecycle follows existing retention. |
| External side effects | Exactly two user-authorized owner status messages for this dataset task. |
| Temporary configuration/original value | No Telegram configuration, scheduler, workers, polling or commands changed. |

## Q14 — Destructive and private archive prerequisite graph only

| Item | Planned state |
|---|---|
| Capability IDs | SA-081, SA-087, SA-088, SA-089, SA-090, SA-091, SA-092, SA-093, SA-094, SA-095, SA-096, SA-097 |
| Prerequisites | Matrix prerequisite rules for these IDs; Q01, Q02, Q03, Q04, Q06, Q07, Q09, Q10, Q11 |
| Records/entities | Reuse tiny owned Student/finance/teaching/Resource/analytics graph and QA actors. Existing managed catalog and protected deployment backup remain available. Portable completion/archive operations must be created later in separately approved disposable scope. |
| Required starting state | Both destructive flags false; no operations. Protected verified recovery archive available. |
| Expected state after creation | No preview, reset, delete, restore, archive inspection or feature enablement. Dataset graph is prepared for later N/O checks; actual archive/operation prerequisites absent must be marked conditional/uncovered, not fabricated. |
| Scenario dependencies | Q01, Q02, Q03, Q04, Q06, Q07, Q09, Q10, Q11 |
| Cleanup ownership | Reset Students/Financial/Resources/Analytics/Managed Backups overlap as documented; no cleanup executed. Manual protected recovery is never a disposable managed backup. |
| External side effects | None now; later archive confidentiality/isolation and exact recovery approval required. |
| Temporary configuration/original value | Permanent feature-off unchanged; require_snapshot remains true. |

## Q15 — Legacy unclassified review prerequisite

| Item | Planned state |
|---|---|
| Capability IDs | SA-083, SA-098, STU-005, STU-009 |
| Prerequisites | Matrix prerequisite rules for these IDs; Q01, Q02, Q03 |
| Records/entities | At most one coherent clearly labeled unclassified purchase under C, only through an explicitly permitted reviewed fixture path. |
| Required starting state | No historical unclassified production purchases; new purchase service mandates explicit type. |
| Expected state after creation | Pending clarification: no manual historical ledger fabrication under current instruction. Refuse to strip provenance from a classified purchase. Record genuine blocker if no permitted creation path. |
| Scenario dependencies | Q01, Q02, Q03 |
| Cleanup ownership | Student/Financial ownership, with mapping review kept stable; no --apply during dataset creation. |
| External side effects | None. |
| Temporary configuration/original value | No constraints or triggers disabled. |

## Every-capability coverage map

A no data; B public; C Student; D entitlement; E booking; F money; G teaching; H Resource/file; I forms; J analytics; K operations; L configuration; M security; N destructive preview; O destructive execution **deferred**. Matrix QA classes are preserved exactly. IDs with A need no synthetic row merely to populate the plan.

| Capability | Matrix QA classification | Synthetic | A–O | Planned scenarios |
|---|---|---|---|---|
| PUB-001 | READ-ONLY, PUBLIC-INTERACTION | No | A, B | Read-only/current configuration; no new data |
| PUB-002 | READ-ONLY, PUBLIC-INTERACTION | No | A, B | Read-only/current configuration; no new data |
| PUB-003 | READ-ONLY, PUBLIC-INTERACTION | No | A, B | Read-only/current configuration; no new data |
| PUB-004 | READ-ONLY, PUBLIC-INTERACTION | Yes | B | Q08 |
| PUB-005 | READ-ONLY, PUBLIC-INTERACTION | No | A, B | Read-only/current configuration; no new data |
| PUB-006 | READ-ONLY, PUBLIC-INTERACTION | No | A, B | Read-only/current configuration; no new data |
| PUB-007 | READ-ONLY, PUBLIC-INTERACTION | No | A, B | Read-only/current configuration; no new data |
| PUB-008 | READ-ONLY, PUBLIC-INTERACTION | Yes | B | Q04 |
| PUB-009 | PUBLIC-INTERACTION, BOOKING-DATA, SECURITY/PERMISSION | Yes | B, E, M | Q04 |
| PUB-010 | PUBLIC-INTERACTION, STUDENT-DATA, BOOKING-DATA | Yes | B, C, E, I | Q04, Q09 |
| PUB-011 | PUBLIC-INTERACTION, STUDENT-DATA, BOOKING-DATA, SECURITY/PERMISSION | Yes | B, C, E, M | Q04 |
| PUB-012 | READ-ONLY, BOOKING-DATA, SECURITY/PERMISSION | Yes | E, M | Q04 |
| PUB-013 | PUBLIC-INTERACTION, BOOKING-DATA, ENTITLEMENT-DATA, SECURITY/PERMISSION | Yes | B, D, E, M | Q04 |
| PUB-014 | READ-ONLY, PUBLIC-INTERACTION | Yes | B | Q07 |
| PUB-015 | PUBLIC-INTERACTION, RESOURCE-DATA, STUDENT-DATA, ANALYTICS-DATA, SECURITY/PERMISSION | Yes | B, C, H, J, M | Q07 |
| PUB-016 | PUBLIC-INTERACTION, RESOURCE-DATA, ANALYTICS-DATA, SECURITY/PERMISSION | Yes | B, H, J, M | Q07 |
| PUB-017 | READ-ONLY, PUBLIC-INTERACTION, RESOURCE-DATA | Yes | B, H | Q08 |
| PUB-018 | PUBLIC-INTERACTION, ANALYTICS-DATA | Yes | B, J | Q08 |
| PUB-019 | READ-ONLY, PUBLIC-INTERACTION | No | A, B | Read-only/current configuration; no new data |
| PUB-020 | READ-ONLY, PUBLIC-INTERACTION | No | A, B | Read-only/current configuration; no new data |
| PUB-021 | PUBLIC-INTERACTION, CONFIGURATION-TOGGLE | No | A, B, L | Read-only/current configuration; no new data |
| PUB-022 | READ-ONLY, CONFIGURATION-TOGGLE, ANALYTICS-DATA | No | A, J, L | Read-only/current configuration; no new data |
| PUB-023 | READ-ONLY, ANALYTICS-DATA | Yes | J | Q11 |
| PUB-024 | PUBLIC-INTERACTION, ANALYTICS-DATA, SECURITY/PERMISSION | Yes | B, J, M | Q11 |
| PUB-025 | READ-ONLY, SECURITY/PERMISSION | No | A, M | Read-only/current configuration; no new data |
| PUB-026 | PUBLIC-INTERACTION, RESOURCE-DATA, CONFIGURATION-TOGGLE | Yes | B, H, L | Q08 |
| STU-001 | STUDENT-DATA, SECURITY/PERMISSION | Yes | C, M | Q01 |
| STU-002 | STUDENT-DATA, SECURITY/PERMISSION | Yes | C, M | Q01 |
| STU-003 | STUDENT-DATA, SECURITY/PERMISSION | Yes | C, M | Q01 |
| STU-004 | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes | C, G, I, M | Q01 |
| STU-005 | STUDENT-DATA, ENTITLEMENT-DATA, SECURITY/PERMISSION | Yes | C, D, M | Q03, Q15 |
| STU-006 | STUDENT-DATA, ENTITLEMENT-DATA | Yes | C, D | Q03 |
| STU-007 | STUDENT-DATA, BOOKING-DATA, SECURITY/PERMISSION | Yes | C, E, M | Q04 |
| STU-008 | STUDENT-DATA, PUBLIC-INTERACTION | Yes | B, C | Q01 |
| STU-009 | STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA, SECURITY/PERMISSION | Yes | C, D, E, M | Q04, Q15 |
| STU-010 | STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA, SECURITY/PERMISSION | Yes | C, D, E, M | Q04 |
| STU-011 | STUDENT-DATA, BOOKING-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes | C, E, G, M | Q04, Q06 |
| STU-012 | STUDENT-DATA, BOOKING-DATA, TEACHING-DATA, RESOURCE-DATA, SECURITY/PERMISSION | Yes | C, E, G, H, M | Q04, Q06 |
| STU-013 | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes | C, G, M | Q06 |
| STU-014 | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes | C, G, M | Q06 |
| STU-015 | STUDENT-DATA, TEACHING-DATA, RESOURCE-DATA, SECURITY/PERMISSION | Yes | C, G, H, M | Q06 |
| STU-016 | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes | C, G, M | Q06 |
| STU-017 | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes | C, G, M | Q06 |
| STU-018 | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes | C, G, M | Q06 |
| STU-019 | STUDENT-DATA, SECURITY/PERMISSION | Yes | C, M | Q06 |
| STU-020 | STUDENT-DATA, SECURITY/PERMISSION | Yes | C, I, M | Q09 |
| STU-021 | STUDENT-DATA, SECURITY/PERMISSION | Yes | C, I, M | Q09 |
| STU-022 | STUDENT-DATA, SECURITY/PERMISSION | Yes | C, M | Q09 |
| STU-023 | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes | C, G, M | Q04, Q06 |
| STU-024 | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes | C, D, F, M | Q03 |
| STU-025 | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes | C, D, F, M | Q03 |
| STU-026 | STUDENT-DATA, BOOKING-DATA, SECURITY/PERMISSION | Yes | C, E, M | Q05 |
| STU-027 | STUDENT-DATA, BOOKING-DATA, SECURITY/PERMISSION | Yes | C, E, M | Q05 |
| STU-028 | STUDENT-DATA, BOOKING-DATA, SECURITY/PERMISSION | Yes | C, E, M | Q04 |
| SA-001 | READ-ONLY, SECURITY/PERMISSION | Yes | M | Q02 |
| SA-002 | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, RESOURCE-DATA | Yes | C, H, M | Q07 |
| SA-003 | READ-ONLY, SECURITY/PERMISSION | Yes | M | Q04 |
| SA-004 | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA | Yes | C, D, E, F, M | Q04, Q10 |
| SA-005 | READ-ONLY, SECURITY/PERMISSION | Yes | K, M | Q10 |
| SA-006 | READ-ONLY, SECURITY/PERMISSION | Yes | K, M | Q10 |
| SA-007 | READ-ONLY, SECURITY/PERMISSION | Yes | K, M | Q10 |
| SA-008 | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA | Yes | C, K, M | Q10 |
| SA-009 | READ-ONLY, SECURITY/PERMISSION | Yes | K, M | Q10 |
| SA-010 | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA | Yes | C, E, K, M | Q10 |
| SA-011 | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA | Yes | C, M | Q10 |
| SA-012 | READ-ONLY, SECURITY/PERMISSION | Yes | K, M | Q10 |
| SA-013 | READ-ONLY, SECURITY/PERMISSION | Yes | K, M | Q02 |
| SA-014 | READ-ONLY, SECURITY/PERMISSION | Yes | K, M | Q02 |
| SA-015 | READ-ONLY, SECURITY/PERMISSION | Yes | M | Q02 |
| SA-016 | READ-ONLY, SECURITY/PERMISSION | Yes | M | Q02 |
| SA-017 | READ-ONLY, SECURITY/PERMISSION | Yes | K, M | Q02 |
| SA-018 | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA | Yes | C, M | Q02 |
| SA-019 | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, ENTITLEMENT-DATA | Yes | C, D, M | Q01 |
| SA-020 | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA | Yes | C, M | Q01 |
| SA-021 | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, RESOURCE-DATA, ANALYTICS-DATA | Yes | C, H, J, M | Q01, Q07 |
| SA-022 | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, RESOURCE-DATA | Yes | C, H, M | Q01, Q07 |
| SA-023 | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, TEACHING-DATA | Yes | C, D, E, F, G, M | Q01 |
| SA-024 | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA, TEACHING-DATA, RESOURCE-DATA, FINANCIAL-DATA, DESTRUCTIVE-EXECUTION | Yes | C, E, F, G, H, M, O | Q01 |
| SA-025 | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA | Yes | C, E, M | Q01 |
| SA-026 | READ-ONLY, SECURITY/PERMISSION, FINANCIAL-DATA, ENTITLEMENT-DATA | Yes | D, F, M | Q03 |
| SA-027 | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes | C, D, F, M | Q03 |
| SA-028 | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes | C, D, F, M | Q03 |
| SA-029 | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes | C, D, F, M | Q03 |
| SA-030 | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes | C, D, F, M | Q03 |
| SA-031 | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes | C, D, F, M | Q03 |
| SA-032 | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes | C, D, F, M | Q03 |
| SA-033 | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes | C, D, F, M | Q03 |
| SA-034 | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes | C, D, F, M | Q03 |
| SA-035 | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes | C, D, F, K, M | Q03 |
| SA-036 | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION | Yes | C, D, F, M | Q03 |
| SA-037 | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION, BOOKING-DATA | Yes | C, D, E, F, M | Q03 |
| SA-038 | STUDENT-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, SECURITY/PERMISSION, BOOKING-DATA | Yes | C, D, E, F, M | Q03 |
| SA-039 | READ-ONLY, SECURITY/PERMISSION, BOOKING-DATA | Yes | E, M | Q04 |
| SA-040 | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA | Yes | C, E, M | Q04 |
| SA-041 | READ-ONLY, SECURITY/PERMISSION, BOOKING-DATA, ENTITLEMENT-DATA | Yes | D, E, K, M | Q04 |
| SA-042 | READ-ONLY, SECURITY/PERMISSION, BOOKING-DATA, ENTITLEMENT-DATA | Yes | D, E, M | Q04 |
| SA-043 | READ-ONLY, SECURITY/PERMISSION, BOOKING-DATA, ENTITLEMENT-DATA | Yes | D, E, K, M | Q04 |
| SA-044 | READ-ONLY, SECURITY/PERMISSION, CONFIGURATION-TOGGLE, ENTITLEMENT-DATA | Yes | D, L, M | Q05 |
| SA-045 | READ-ONLY, SECURITY/PERMISSION, CONFIGURATION-TOGGLE, BOOKING-DATA | Yes | E, L, M | Q05 |
| SA-046 | READ-ONLY, SECURITY/PERMISSION, CONFIGURATION-TOGGLE, BOOKING-DATA | Yes | E, L, M | Q05 |
| SA-047 | READ-ONLY, SECURITY/PERMISSION, CONFIGURATION-TOGGLE, BOOKING-DATA, ENTITLEMENT-DATA | Yes | D, E, L, M | Q04, Q05 |
| SA-048 | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA | Yes | C, D, E, M | Q05 |
| SA-049 | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA | Yes | C, E, M | Q05 |
| SA-050 | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, BOOKING-DATA | Yes | C, E, K, M | Q05 |
| SA-051 | READ-ONLY, SECURITY/PERMISSION, BOOKING-DATA, CONFIGURATION-TOGGLE | Yes | E, L, M | Q04 |
| SA-052 | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION, BOOKING-DATA, RESOURCE-DATA | Yes | C, E, G, H, K, M | Q06 |
| SA-053 | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION, RESOURCE-DATA | Yes | C, G, H, M | Q06 |
| SA-054 | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes | C, G, M | Q06 |
| SA-055 | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes | C, G, K, M | Q06 |
| SA-056 | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION, RESOURCE-DATA | Yes | C, G, H, M | Q06 |
| SA-057 | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes | C, G, M | Q06 |
| SA-058 | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes | C, G, M | Q06 |
| SA-059 | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes | C, G, M | Q04, Q06 |
| SA-060 | STUDENT-DATA, TEACHING-DATA, SECURITY/PERMISSION | Yes | C, G, K, M | Q06 |
| SA-061 | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION, DESTRUCTIVE-EXECUTION | Yes | H, L, M, O | Q07 |
| SA-062 | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes | H, L, M | Q07 |
| SA-063 | RESOURCE-DATA, SECURITY/PERMISSION, DESTRUCTIVE-EXECUTION | Yes | H, M, O | Q08 |
| SA-064 | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes | H, L, M | Q08 |
| SA-065 | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes | H, L, M | Q08 |
| SA-066 | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes | H, L, M | Q08 |
| SA-067 | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes | H, L, M | Q08 |
| SA-068 | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes | H, L, M | Q08 |
| SA-069 | RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes | H, L, M | Q08 |
| SA-070 | STUDENT-DATA, RESOURCE-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes | C, H, I, L, M | Q09 |
| SA-071 | READ-ONLY, STUDENT-DATA, SECURITY/PERMISSION | Yes | C, I, M | Q09 |
| SA-072 | READ-ONLY, ANALYTICS-DATA, SECURITY/PERMISSION | Yes | J, M | Q11 |
| SA-073 | READ-ONLY, ANALYTICS-DATA, SECURITY/PERMISSION | Yes | J, M | Q11 |
| SA-074 | READ-ONLY, ANALYTICS-DATA, SECURITY/PERMISSION | Yes | J, M | Q11 |
| SA-075 | READ-ONLY, ANALYTICS-DATA, BOOKING-DATA, RESOURCE-DATA, SECURITY/PERMISSION | Yes | E, H, J, M | Q11 |
| SA-076 | READ-ONLY, ANALYTICS-DATA, SECURITY/PERMISSION | Yes | J, M | Q12 |
| SA-077 | READ-ONLY, CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes | L, M | Q08 |
| SA-078 | CONFIGURATION-TOGGLE, SECURITY/PERMISSION | No | A, L, M | Read-only/current configuration; no new data |
| SA-079 | CONFIGURATION-TOGGLE, SECURITY/PERMISSION, ANALYTICS-DATA | No | A, J, L, M | Read-only/current configuration; no new data |
| SA-080 | READ-ONLY, SECURITY/PERMISSION | No | A, M | Read-only/current configuration; no new data |
| SA-081 | RESOURCE-DATA, SECURITY/PERMISSION, DESTRUCTIVE-EXECUTION | Yes | H, M, O | Q14 |
| SA-082 | READ-ONLY, SECURITY/PERMISSION | Yes | M | Q02 |
| SA-083 | READ-ONLY, SECURITY/PERMISSION, STUDENT-DATA, ENTITLEMENT-DATA | Yes | C, D, M | Q01, Q15 |
| SA-084 | CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes | L, M | Q13 |
| SA-085 | BOOKING-DATA, ANALYTICS-DATA, CONFIGURATION-TOGGLE, SECURITY/PERMISSION | Yes | E, J, L, M | Q04, Q13 |
| SA-086 | READ-ONLY, SECURITY/PERMISSION | Yes | M | Q13 |
| SA-087 | READ-ONLY, SECURITY/PERMISSION, CONFIGURATION-TOGGLE | Yes | L, M | Q02, Q14 |
| SA-088 | SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW, DESTRUCTIVE-EXECUTION | Yes | M, N, O | Q02, Q14 |
| SA-089 | ANALYTICS-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW, DESTRUCTIVE-EXECUTION | Yes | J, M, N, O | Q11, Q14 |
| SA-090 | STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, TEACHING-DATA, RESOURCE-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW, DESTRUCTIVE-EXECUTION | Yes | C, D, E, F, G, H, M, N, O | Q14 |
| SA-091 | FINANCIAL-DATA, ENTITLEMENT-DATA, BOOKING-DATA, TEACHING-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW, DESTRUCTIVE-EXECUTION | Yes | D, E, F, G, M, N, O | Q14 |
| SA-092 | RESOURCE-DATA, TEACHING-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW, DESTRUCTIVE-EXECUTION | Yes | G, H, M, N, O | Q07, Q14 |
| SA-093 | RESOURCE-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW, DESTRUCTIVE-EXECUTION | Yes | H, M, N, O | Q14 |
| SA-094 | STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, TEACHING-DATA, RESOURCE-DATA, ANALYTICS-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW | Yes | C, D, E, F, G, H, J, M, N | Q14 |
| SA-095 | RESOURCE-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW | Yes | H, M, N | Q14 |
| SA-096 | STUDENT-DATA, BOOKING-DATA, ENTITLEMENT-DATA, FINANCIAL-DATA, TEACHING-DATA, RESOURCE-DATA, ANALYTICS-DATA, SECURITY/PERMISSION, DESTRUCTIVE-PREVIEW, DESTRUCTIVE-EXECUTION | Yes | C, D, E, F, G, H, J, M, N, O | Q14 |
| SA-097 | READ-ONLY, RESOURCE-DATA, SECURITY/PERMISSION | Yes | H, M | Q02, Q14 |
| SA-098 | READ-ONLY, ENTITLEMENT-DATA, FINANCIAL-DATA, BOOKING-DATA, SECURITY/PERMISSION | Yes | D, E, F, M | Q15 |
| SA-099 | CONFIGURATION-TOGGLE, FINANCIAL-DATA, SECURITY/PERMISSION | Yes | F, L, M | Q03 |

## Creation outcome and final coverage reconciliation

Captured 2026-10-06T18:00:35+00:00. All 153 IDs remain classified; all 139 synthetic IDs remain mapped to scenarios. Six Students, 15 planned scenarios, 331 database rows and four inventoried application payloads are retained. **132 starting-fixture capability IDs prepared; seven uncovered** (SA-076, SA-084, SA-085, SA-095, SA-096, SA-097, SA-098). Exact rows, state, dependencies and cleanup are in PRODUCTION_QA_DATASET_MANIFEST.md; limitations/reconciliation are in PRODUCTION_QA_DATASET_REPORT.md. No complete dataset claim or full acceptance run.

Construction adjustments: canonical FIFO required a second completed C lesson to reach the diagnostic purchase; one explicit courtesy unit retains the near-expiry fixture. An extra one-unit zero-price C purchase and two-unit lesson requirement prepare single-allocation non-pooling; one USD 5.00 partial refund includes an exact two_hour forfeiture while retaining USD 5.00 overpayment. Same-day attempt actually rejected outside available schedule/grid before notice; no policy weakening. A published Page/pending draft, Blog/redirect/revision, passive media, two Game cards, inactive FAQ/social/promotion variants and localized stale/draft states are present.

Q11 respects staff browser exclusion. Real UI Resource request/download facts plus independent anonymous HTTP GET/request/download/campaign are present; six client payloads are explicitly simulated telemetry with zero dwell, no claim of human attention/social click/game completion. Country/time come from actual server observations; no spoofed IP/country/time. Future complete-day historical/retention/temporal branches wait for real conditions and separately authorized acceptance.

Q12 has no synthetic maintenance visit. Q13 has the real authorized START and eventual FINAL status receipt; SA-084/SA-085 still lack isolated faked security/rule fixtures and no live integration expansion was attempted. Q14 reuses the intact graph but SA-095/096/097 have no private portable archive/canonical operation prerequisite. Q15 lacks a permitted unclassified purchase adapter; the earlier clarification is pending. Current feature-off/source/configuration are preserved. No destructive execution, acceptance, cleanup or LMS.
