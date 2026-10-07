# Pre-LMS Invariants

Application release: `4296b21368cbc9c564d0cb7a85e29b2ae408e0ee`. Read this during LMS Stage1 discovery. These current contracts survive LMS work; acceptance limitations are in the153-ID report. This document authorizes no LMS implementation, cleanup, deployment, credential change or operations configuration.

## 1. Student identity and normalization

- Authority: StudentIdentityService / StudentEmailService.
- Current rule: Canonical name/email/phone normalization, DOB plus two recorded identifiers, one verified nonsuspended identity; Resource email is not authentication verification.
- Data/schema: students,student_emails,student_auth_attempts.
- Important executed tests: StudentAuthenticationTest,StudentSecondaryEmailTest,ContactIdentityTest.
- LMS must not bypass: Do not make Resource requests or unreviewed imported email a trusted login identity.

## 2. Student ownership

- Authority: EnsureStudentAuthenticated / StudentPortalService / scoped controllers.
- Current rule: Every predictable ID is scoped to the authenticated Student before disclosure or mutation; foreign/private objects return generic404.
- Data/schema: student_id FKs; owner-scoped queries.
- Important executed tests: LessonWorkspaceTest,StudentTeachingExperienceTest,BinAuthorizationTest.
- LMS must not bypass: Do not rely on hidden buttons or course membership alone for tutoring record/file ownership.

## 3. Authentication/session boundaries

- Authority: Student AuthController; administrator AuthController; session middleware.
- Current rule: Separate Student/web guards; absolute Student authentication deadline; staff inactivity/MFA proof; secure HTTP-only Lax cookies under mounted path; JSON database sessions.
- Data/schema: sessions,students,administrators; session payload/version.
- Important executed tests: SessionInactivityAndCookieRefreshTest,StudentAuthenticationTest,AdministratorTwoFactorTest.
- LMS must not bypass: Do not extend expired QA/user proofs, forge session fields or revive serialized PHP sessions.

## 4. Staff roles

- Authority: Administrator / role middleware / existing policies.
- Current rule: Super Admin/Admin/Assistant permissions are server-enforced; role-aware navigation reflects them. Assistant operational access does not grant Cashier/security/CMS access.
- Data/schema: administrators.role and action policies.
- Important executed tests: AdministratorAuthorizationTest,StageOnePresentationTest.
- LMS must not bypass: Do not equate visibility with permission or add bypass routes.

## 5. Super Admin security/TOTP

- Authority: AdministratorTwoFactorService / TwoFactorSecurityController.
- Current rule: Staged password+factor proof, one-use current time step/recovery code, actor/version/session binding; credential changes require existing security workflow.
- Data/schema: encrypted two_factor fields,proofs,audit_logs.
- Important executed tests: AdministratorTwoFactorTest,MariaDbConcurrencyVerificationTest.
- LMS must not bypass: Do not export factors/recovery credentials or bypass reauthentication for destructive/LMS administration.

## 6. Canonical UTC booking storage

- Authority: TimezoneService / BookingService.
- Current rule: start_at_utc/end_at_utc are authoritative instants; presentation converts them without rewriting persisted booking.
- Data/schema: bookings UTC/snapshot fields.
- Important executed tests: TimezoneTest,ReschedulePresentationTest,BookingEngineTest.
- LMS must not bypass: Do not store local clock text as UTC or change an instant to fix its label.

## 7. Business Timezone interpretation

- Authority: TimezoneService / AvailabilityService / ReportPeriod.
- Current rule: Tutor rules, policy/date/report boundaries use configured IANA Business Timezone with real DST offset.
- Data/schema: settings.business_timezone,availability rules/snapshots.
- Important executed tests: CanonicalAvailabilityValidationTest,DstGapAndFoldHandlingTest.
- LMS must not bypass: Do not hard-code Cairo offset+3 year-round or infer business date from server date.

## 8. Student timezone display

- Authority: EnsureStudentAuthenticated / timezone components / reschedule controllers.
- Current rule: Explicit valid choice persists; browser/device choice can take precedence over account fallback. All displayed time/city/IANA/offset agree with the selected instant.
- Data/schema: students.preferred_timezone,student_display_timezone; booking snapshots.
- Important executed tests: ReschedulePresentationTest,TimezoneTest.
- LMS must not bypass: Do not treat display preference as funding/availability authority or overwrite historical snapshots on GET.

## 9. Availability authority

- Authority: AvailabilityService / SlotResolver.
- Current rule: Server resolves active format, real rules/overrides/grid/buffer/notice/horizon/holiday/conflict and signed visitor-owned slot.
- Data/schema: availability_rules,availability_exceptions,bookings,holds.
- Important executed tests: AvailabilityTest,CanonicalAvailabilityValidationTest,V3SlotIdentityTest.
- LMS must not bypass: Do not trust a client-proposed duration/UTC slot or weaken notice for testing/course enrollment.

## 10. Booking locking/concurrency

- Authority: BookingService / StudentBookingService / calendar locking.
- Current rule: Transactions and ordered locks protect overlapping dates, holds, last allocation and idempotency. One race winner is a real persisted fact.
- Data/schema: booking_calendar_locks,booking_holds,bookings,ledger.
- Important executed tests: MariaDbConcurrencyVerificationTest,BusinessLifecycleConcurrencyTest.
- LMS must not bypass: Do not implement an LMS booking writer outside these shared locks/transactions.

## 11. BookingHold behavior

- Authority: BookingHoldService / SlotResolver.
- Current rule: Signed visitor-owned temporary hold; expiry and release remain real; hold is neither confirmed booking nor permanent credit debit.
- Data/schema: booking_holds and slot ownership signatures.
- Important executed tests: BookingHoldAuthenticationTest,BufferExpandedConcurrencyLockTest.
- LMS must not bypass: Do not count a hold as revenue/lesson or bypass visitor binding.

## 12. Typed one_hour/two_hour separation

- Authority: EntitlementService / StudentLedgerService / SessionType.
- Current rule: Exact requested type+units from one eligible allocation, earliest-expiring compatible selection; no conversion or pooling. Duration is not a grant-inference rule.
- Data/schema: entitlement_types,student_package_entitlements,session_types.
- Important executed tests: TypedEntitlementsTest,StudentCreditBookingTest.
- LMS must not bypass: Do not translate minutes into rights or sum incompatible/separate grants to fund a lesson.

## 13. Exact funding provenance

- Authority: StudentBookingService / StudentLedgerService and typed DB guards.
- Current rule: Package/allocation/type/code/units/consumed-ledger ID form one consistent immutable funding fact; generated/composite/FK/trigger protections remain enabled.
- Data/schema: bookings provenance,session_ledger_entries;10generated columns/4guards.
- Important executed tests: TypedEntitlementsTest,MariaDbConcurrencyVerificationTest.
- LMS must not bypass: Do not strip provenance, disable foreign_key_checks, infer legacy type or write raw ledger adjustments.

## 14. No reschedule second debit

- Authority: RescheduleService.
- Current rule: Move canonical slot/snapshot and append reschedule history; retain original funding/debit exactly.
- Data/schema: session_reschedules,bookings,original ledger debit.
- Important executed tests: ReschedulePresentationTest,StudentReschedulingTest,TypedEntitlementsTest.
- LMS must not bypass: Do not implement a cancel/rebook approximation that debits twice.

## 15. Exact cancellation restoration

- Authority: CancellationService / BookingPolicyService / NoShowService.
- Current rule: Use immutable captured policy; restore original allocation/type/quantity exactly once or retain intended consumption. No-show does not fabricate payment.
- Data/schema: policy_snapshot,booking_policy_decisions,cancellation_restore ledger.
- Important executed tests: BookingLifecycleAndPolicyCutoffTest,BusinessLifecycleConcurrencyTest.
- LMS must not bypass: Do not substitute current policy or a convenient different package for the original consequence.

## 16. Purchase/payment/refund reconciliation

- Authority: StudentLedgerService / BillingReconciliationService.
- Current rule: Original−discount=net; gross payments−refunds=net paid; due/overpayment derive per currency. Refund is bounded by recorded payment; optional forfeiture is explicit and typed.
- Data/schema: student_packages,payment_records,payment_refunds,allocations.
- Important executed tests: StudentLedgerTest,CashierBillingReconciliationTest,TypedEntitlementsTest.
- LMS must not bypass: Do not silently balance deliberate overpayment or reduce rights merely because cash was refunded.

## 17. Append-only history

- Authority: Ledger/renewal/cancellation services and audit layer.
- Current rule: Ordinary transactions append facts/idempotent consequences rather than rewriting history. Privileged previewed reset is a separate default-off exception.
- Data/schema: ledger,payments,refunds,booking_events,audit_logs.
- Important executed tests: StudentLedgerTest,BusinessLifecycleDataQualityTest.
- LMS must not bypass: Do not mutate historic amounts/ledger identities through LMS progress or normal edits.

## 18. Installments versus payments

- Authority: InstallmentScheduleService / ReceivablesReadModel / FinancialStatementService.
- Current rule: Schedule is immutable full-price forecast with FIFO satisfaction projection; only PaymentRecord represents actual cash. Renewal creates new purchase/grant.
- Data/schema: package_installments,payments,refunds,package_renewals.
- Important executed tests: BusinessLifecycleDataQualityTest,BusinessLifecycleConcurrencyTest.
- LMS must not bypass: Do not create receipts/revenue from due installments or merge different currencies.

## 19. Resource authority

- Authority: ResourceController / Resource model and reference guards.
- Current rule: Published/gated Resource authorizes immediate single-use request/download; replacement/detachment respects references. One explicit visible click consumes its grant.
- Data/schema: resources,requests,downloads,translations,file paths.
- Important executed tests: ExternalResourceLinkingTest,ResourceSafeReplacementTest,resource-access.test.mjs.
- LMS must not bypass: Do not put private files on public storage or treat a Resource email as verified identity.

## 20. Private lesson/material files

- Authority: LessonMaterialService / LessonWorkspaceController.
- Current rule: Owned eligible lesson plus sharing flag/reference and private path authorization precede file/redirect delivery. Hash/bytes remain intact.
- Data/schema: lesson_materials,bookings,private storage.
- Important executed tests: LessonWorkspaceTest,StudentTeachingExperienceTest.
- LMS must not bypass: Do not expose storage paths as public URLs or authorize only by knowing a filename.

## 21. Teaching ownership/sharing

- Authority: TeachingRecordService / StudentTeachingReadModel.
- Current rule: Plans,milestones,homework,preparation,errors,tags,notes,feedback are owner-scoped and explicitly shared. Tutor-only preparation/feedback controls stay distinct.
- Data/schema: teaching tables,sharing flags,student/booking FKs.
- Important executed tests: StudentTeachingExperienceTest,BinAuthorizationTest.
- LMS must not bypass: Do not make every lesson/course note visible or allow Students to declare tutor completion.

## 22. Student notification synchronization

- Authority: StudentNotificationService.
- Current rule: Current model synchronizes eligible facts on authenticated visits, deduplicates semantic identity, persists unread/read state and renders mounted links.
- Data/schema: student_notifications and qualifying facts.
- Important executed tests: StudentTeachingExperienceTest.
- LMS must not bypass: Do not erase read state or replace this with an assumed worker without an explicit architecture decision.

## 23. Analytics/privacy

- Authority: AnalyticsService / ingestion and rollup services.
- Current rule: Bounded allowlisted metadata; no permanent raw IP; exclude staff/preview/bot traffic; preserve authoritative cutover. One semantic action uses one recorder: internal game open server-side, external-card open client-side.
- Data/schema: visitors,sessions,analytics_events,aggregates,privacy fields.
- Important executed tests: ClientTelemetryIngestionTest,SocialHistoryAndIpPrivacyTest,PublicExperienceTest.
- LMS must not bypass: Do not count client+server copies with different UUIDs, trust client booking completion, or add third-party ad trackers.

## 24. Audit expectations

- Authority: AuditLog / domain lifecycle history.
- Current rule: Privileged mutation records actor, entity and safe allowlisted changes; protect credentials/private answer/token material.
- Data/schema: audit_logs,booking_events,operation summaries.
- Important executed tests: AdministratorTwoFactorTest,BusinessLifecycleDataQualityTest.
- LMS must not bypass: Do not copy secrets into audit/telemetry/report payloads or hide actor provenance.

## 25. Manual meeting-room model

- Authority: MeetingLinkService / MeetingProvider / MeetingRoom.
- Current rule: Configured pool/preference and explicit assignment; overlap race admits one winner; stored harmless/genuine snapshots and15-minute reveal are independent of browser link visibility.
- Data/schema: providers,rooms,booking meeting snapshots.
- Important executed tests: MeetingRotationTest,MariaDbConcurrencyVerificationTest.
- LMS must not bypass: Do not invent meeting workers, publish room credentials or reuse overlapping rooms.

## 26. Manual recurrence/waitlist

- Authority: RecurringLessonService / StudentSchedulingService.
- Current rule: Bounded explicit generation with blocked reasons; interest is neither reserved slot nor debit; Student holiday does not move existing lessons automatically.
- Data/schema: plans,occurrences,waitlists,unavailability.
- Important executed tests: BusinessLifecycleDataQualityTest,BusinessLifecycleConcurrencyTest.
- LMS must not bypass: Do not automatically generate/change original occurrences or consume credit for interest.

## 27. Destructive-tools fail-closed boundary

- Authority: DevelopmentData operation/archive/reset services and controller.
- Current rule: Default-off permanent feature gate; Super-only actor/session/password/factor-bound immutable preview; mandatory recovery, locks, compatibility/hash/private-file guards; restore missing/skip identical/refuse differences.
- Data/schema: development_data_operations,private archives/recovery;flags.
- Important executed tests: DevelopmentLaunchDataToolsTest,DevelopmentDataArchiveTest,DevelopmentDataConcurrencyTest.
- LMS must not bypass: Do not enable flags silently, overwrite differing imports, reset outside exact reviewed ownership closure, or treat protected native backups as managed debris.

