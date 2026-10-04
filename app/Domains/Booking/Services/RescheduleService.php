<?php

namespace App\Domains\Booking\Services;

use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Availability\Services\SlotResolver;
use App\Domains\Booking\Exceptions\BookingPolicyViolationException;
use App\Domains\Booking\Exceptions\InvalidBookingStatusTransitionException;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\Booking\Models\SessionReschedule;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\EntitlementService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class RescheduleService
{
    public function __construct(
        protected TimezoneService $timezoneService,
        protected AuditLogService $auditLogService,
        protected AvailabilityService $availabilityService,
        protected AnalyticsService $analyticsService,
        protected SlotResolver $slotResolver,
        protected DatabaseCapability $databaseCapability,
    ) {}

    /**
     * Reschedule an existing booking atomically while preserving full history.
     *
     * @throws SlotUnavailableException
     * @throws InvalidBookingStatusTransitionException
     * @throws BookingPolicyViolationException
     */
    public function reschedule(
        Booking $booking,
        CarbonInterface|string $newStartUtc,
        CarbonInterface|string|null $newEndUtc = null,
        string $performedBy = 'customer',
        ?int $performedById = null,
        ?string $reason = null,
        ?string $idempotencyKey = null,
        ?string $customerTimezone = null,
        ?string $slotId = null,
        ?string $slotOwnerToken = null,
        ?int $newSessionTypeId = null,
    ): Booking {
        $startUtc = $this->timezoneService->toUtc($newStartUtc);
        $targetType = $newSessionTypeId !== null ? SessionType::query()->whereKey($newSessionTypeId)->where('active', true)->firstOrFail() : $booking->sessionType;
        if ($performedBy === 'student' && $newSessionTypeId !== null && $newSessionTypeId !== $booking->session_type_id) {
            throw new BookingPolicyViolationException('Contact your tutor to change lesson type.');
        }
        $nowUtc = CarbonImmutable::now('UTC');

        if ($startUtc <= $nowUtc) {
            throw new SlotUnavailableException('Cannot reschedule to a slot in the past.');
        }

        if ($performedBy === 'student') {
            if (! $slotId || ! $slotOwnerToken || ! $customerTimezone) {
                throw new BookingPolicyViolationException('Select a valid server-issued slot.');
            }
            $issuedSlot = $this->slotResolver->resolve($slotId, $booking->sessionType, $customerTimezone, $slotOwnerToken);
            $issuedStart = $this->timezoneService->toUtc($issuedSlot['slot_start_utc']);
            if (! $issuedStart->equalTo($startUtc)) {
                throw new BookingPolicyViolationException('The selected time slot is no longer valid.');
            }
            $newEndUtc = $issuedSlot['slot_end_utc'];
        }

        return $this->databaseCapability->transaction(function () use ($booking, $startUtc, $newEndUtc, $performedBy, $performedById, $reason, $idempotencyKey, $customerTimezone, $slotId, $slotOwnerToken, $targetType, $newSessionTypeId) {
            $candidateConfig = $this->availabilityService->resolveSlotConfiguration(
                sessionType: $targetType,
                startUtc: $startUtc,
                endUtc: $newEndUtc ? $this->timezoneService->toUtc($newEndUtc) : null
            );
            $this->availabilityService->acquireCalendarDateLocks($startUtc, $candidateConfig['end_utc'], $candidateConfig['buffer_minutes']);

            $lockedStudent = null;
            if ($booking->student_id !== null) {
                $lockedStudent = Student::withTrashed()->whereKey($booking->student_id)->lockForUpdate()->firstOrFail();
            }

            if ($performedBy === 'student') {
                if (! $lockedStudent || $lockedStudent->trashed() || $lockedStudent->identity_status !== 'verified' || $lockedStudent->suspended_at !== null
                    || (int) $booking->student_id !== (int) $performedById) {
                    throw new BookingPolicyViolationException('This booking is not available to the student.');
                }
            }

            if ($performedBy === 'student') {
                $issuedSlot = $this->slotResolver->resolve($slotId, $booking->sessionType, $customerTimezone, $slotOwnerToken);
                $issuedStart = $this->timezoneService->toUtc($issuedSlot['slot_start_utc']);
                $issuedEnd = $this->timezoneService->toUtc($issuedSlot['slot_end_utc']);
                if (! $issuedStart->equalTo($candidateConfig['start_utc']) || ! $issuedEnd->equalTo($candidateConfig['end_utc'])) {
                    throw new SlotUnavailableException('The selected time slot changed. Select it again.');
                }
            }

            // Re-read ownership and lifecycle under the booking row lock.
            $lockedBooking = Booking::query()->where('id', $booking->id)->lockForUpdate()->firstOrFail();
            if ($lockedBooking->session_type_id !== $booking->session_type_id
                || (int) $lockedBooking->student_id !== (int) $booking->student_id
                || ($performedBy === 'student' && (int) $lockedBooking->student_id !== (int) $performedById)) {
                throw new BookingPolicyViolationException('This booking is not available to the student.');
            }

            $freshTarget = SessionType::query()->whereKey($targetType->id)->lockForUpdate()->firstOrFail();
            if ($newSessionTypeId !== null && ! $freshTarget->active) {
                throw new BookingPolicyViolationException('This lesson type is no longer available.');
            }
            if ($freshTarget->duration_minutes !== $targetType->duration_minutes) {
                throw new SlotUnavailableException('Lesson configuration changed. Select the time again.');
            }
            $targetType = $freshTarget;

            if ($newSessionTypeId !== null && $newSessionTypeId !== $lockedBooking->session_type_id && $lockedBooking->consumed_ledger_entry_id !== null) {
                $required = app(EntitlementService::class)->requirement($targetType);
                if ($required['type']->code !== $lockedBooking->entitlement_code || $required['units'] !== (int) $lockedBooking->entitlement_units) {
                    throw new BookingPolicyViolationException('A lesson change requires the same entitlement type and units as the original debit.');
                }
            }
            if ($newSessionTypeId !== null && $newSessionTypeId !== $lockedBooking->session_type_id && $lockedBooking->funding_mode === 'legacy') {
                throw new BookingPolicyViolationException('Review historical funding before changing lesson type.');
            }
            if ($performedBy === 'student' && $idempotencyKey) {
                $existing = SessionReschedule::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    if ((int) $existing->booking_id !== (int) $lockedBooking->id) {
                        throw new BookingPolicyViolationException('Invalid reschedule request.');
                    }

                    return $lockedBooking;
                }
            }

            if ($lockedBooking->status === 'cancelled') {
                throw new InvalidBookingStatusTransitionException('Cancelled bookings cannot be rescheduled.');
            }

            if ($lockedBooking->status === 'completed') {
                throw new InvalidBookingStatusTransitionException('Cannot reschedule a completed session.');
            }

            if ($lockedBooking->status === 'no_show') {
                throw new InvalidBookingStatusTransitionException('Cannot reschedule a session marked as no-show.');
            }

            if ($lockedBooking->status !== 'confirmed') {
                throw new InvalidBookingStatusTransitionException("Cannot reschedule a booking with status '{$lockedBooking->status}'.");
            }

            if ($performedBy === 'student') {
                if (! $idempotencyKey || $lockedBooking->start_at_utc < now('UTC')->addHours(24)) {
                    throw new BookingPolicyViolationException('Contact your tutor to change a session within 24 hours.');
                }
            } elseif ($performedBy === 'customer') {
                if ($lockedBooking->start_at_utc <= now('UTC')) {
                    throw new BookingPolicyViolationException('Past appointments cannot be rescheduled.');
                }

                $cutoffHours = (int) Setting::get('booking_reschedule_cutoff_hours', 24);
                if ($lockedBooking->start_at_utc < now('UTC')->addHours($cutoffHours)) {
                    throw new BookingPolicyViolationException("Appointments cannot be rescheduled within {$cutoffHours} hours of the scheduled start time.");
                }
            }

            // Authoritatively resolve target slot configuration without forcing base duration
            $slotConfig = $candidateConfig;
            $endUtc = $slotConfig['end_utc'];

            // 3. Authoritative slot validation excluding this booking
            $this->availabilityService->validateSlotForBooking(
                sessionType: $targetType,
                startUtc: $startUtc,
                endUtc: $endUtc,
                excludeBookingId: $lockedBooking->id,
                isTrustedAdmin: ($performedBy === 'admin'),
                resolvedConfig: $slotConfig
            );

            // 4. Capture previous snapshot
            $previousData = [
                'start_at_utc' => $lockedBooking->start_at_utc?->toDateTimeString(),
                'end_at_utc' => $lockedBooking->end_at_utc?->toDateTimeString(),
                'customer_local_date' => $lockedBooking->customer_local_date_at_booking?->toDateString(),
                'customer_local_start_time' => $lockedBooking->customer_local_start_time_at_booking,
                'business_local_date' => $lockedBooking->business_local_date_at_booking?->toDateString(),
                'business_local_start_time' => $lockedBooking->business_local_start_time_at_booking,
                'status' => $lockedBooking->status,
            ];

            // 5. Generate new snapshot using CURRENT business timezone
            $currentBusinessTz = $this->timezoneService->getBusinessTimezone();
            $newSnapshot = $this->timezoneService->createBookingSnapshot(
                startUtc: $startUtc,
                endUtc: $endUtc,
                customerTimezone: $customerTimezone ?: $lockedBooking->customer_timezone,
                businessTimezone: $currentBusinessTz
            );
            $oldTimezone = $lockedBooking->customer_timezone;

            // 6. Update booking
            $lockedBooking->update(array_merge($newSnapshot, [
                'session_type_id' => $targetType->id,
                'status' => 'confirmed',
                'admin_reconfirmation_needed' => true,
            ]));

            $lockedBooking = app(MeetingLinkService::class)->revalidate($lockedBooking);

            if (in_array($performedBy, ['student', 'admin', 'system'], true)) {
                SessionReschedule::create([
                    'booking_id' => $lockedBooking->id,
                    'actor_type' => $performedBy,
                    'actor_id' => $performedById,
                    'old_start_at_utc' => $previousData['start_at_utc'],
                    'new_start_at_utc' => $startUtc->toDateTimeString(),
                    'old_timezone' => $oldTimezone,
                    'new_timezone' => $customerTimezone ?: $oldTimezone,
                    'idempotency_key' => $idempotencyKey ?: (string) Str::uuid(),
                    'ip_address' => null,
                    'created_at' => now('UTC'),
                ]);
            }

            $newData = [
                'start_at_utc' => $startUtc->toDateTimeString(),
                'end_at_utc' => $endUtc->toDateTimeString(),
                'customer_local_date' => $newSnapshot['customer_local_date_at_booking'],
                'customer_local_start_time' => $newSnapshot['customer_local_start_time_at_booking'],
                'reason' => $reason,
            ];

            // 7. Record BookingEvent
            BookingEvent::create([
                'booking_id' => $lockedBooking->id,
                'event_type' => 'rescheduled',
                'performed_by' => $performedBy,
                'performed_by_id' => $performedById,
                'previous_data' => $previousData,
                'new_data' => $newData,
                'created_at' => now(),
            ]);

            // 8. Record AuditLog
            $this->auditLogService->log(
                action: 'booking_rescheduled',
                entityType: Booking::class,
                entityId: $lockedBooking->id,
                previousData: $previousData,
                newData: $newData,
                adminId: $performedBy === 'admin' ? $performedById : null
            );

            DB::afterCommit(function () use ($lockedBooking, $performedBy, $startUtc, $endUtc, $idempotencyKey): void {
                $this->analyticsService->trackEvent(
                    eventType: 'booking_rescheduled',
                    page: '/booking/reschedule',
                    metadata: [
                        'booking_id' => $lockedBooking->id,
                        'performed_by' => $performedBy,
                        'start_at_utc' => $startUtc->toDateTimeString(),
                        'end_at_utc' => $endUtc->toDateTimeString(),
                        'customer_timezone' => $lockedBooking->customer_timezone,
                    ],
                    eventUuid: Uuid::uuid5(Uuid::NAMESPACE_URL, 'booking-reschedule:'.$idempotencyKey)->toString(),
                );

            });

            return $lockedBooking->fresh();
        }, 5);
    }
}
