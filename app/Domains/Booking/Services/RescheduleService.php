<?php

namespace App\Domains\Booking\Services;

use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Exceptions\BookingPolicyViolationException;
use App\Domains\Booking\Exceptions\InvalidBookingStatusTransitionException;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\CMS\Models\Setting;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RescheduleService
{
    public function __construct(
        protected TimezoneService $timezoneService,
        protected AuditLogService $auditLogService,
        protected AvailabilityService $availabilityService,
        protected AnalyticsService $analyticsService
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
        ?string $reason = null
    ): Booking {
        $startUtc = $this->timezoneService->toUtc($newStartUtc);
        $nowUtc = CarbonImmutable::now('UTC');

        if ($startUtc <= $nowUtc) {
            throw new SlotUnavailableException('Cannot reschedule to a slot in the past.');
        }

        return DB::transaction(function () use ($booking, $startUtc, $newEndUtc, $performedBy, $performedById, $reason) {
            // 1. Lock the existing booking row to prevent concurrent mutations
            $lockedBooking = Booking::query()->where('id', $booking->id)->lockForUpdate()->firstOrFail();

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

            if ($performedBy === 'customer') {
                if ($lockedBooking->start_at_utc <= now('UTC')) {
                    throw new BookingPolicyViolationException('Past appointments cannot be rescheduled.');
                }

                $cutoffHours = (int) Setting::get('booking_reschedule_cutoff_hours', 24);
                if ($lockedBooking->start_at_utc < now('UTC')->addHours($cutoffHours)) {
                    throw new BookingPolicyViolationException("Appointments cannot be rescheduled within {$cutoffHours} hours of the scheduled start time.");
                }
            }

            // Authoritatively resolve target slot configuration without forcing base duration
            $candidateEndUtc = $newEndUtc ? $this->timezoneService->toUtc($newEndUtc) : null;
            $slotConfig = $this->availabilityService->resolveSlotConfiguration(
                sessionType: $lockedBooking->sessionType,
                startUtc: $startUtc,
                endUtc: null
            );
            $endUtc = $slotConfig['end_utc'];

            // 2. Acquire deterministic calendar row locks for buffer-expanded new date(s)
            $buffer = (int) $slotConfig['buffer_minutes'];
            $this->availabilityService->acquireCalendarDateLocks($startUtc, $endUtc, $buffer);

            // 3. Authoritative slot validation excluding this booking
            $this->availabilityService->validateSlotForBooking(
                sessionType: $lockedBooking->sessionType,
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
                customerTimezone: $lockedBooking->customer_timezone,
                businessTimezone: $currentBusinessTz
            );

            // 6. Update booking
            $lockedBooking->update(array_merge($newSnapshot, [
                'status' => 'confirmed',
            ]));

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

            // 9. Track authoritative server-side analytics event
            $this->analyticsService->trackEvent(
                eventType: 'booking_rescheduled',
                page: '/booking/reschedule',
                metadata: [
                    'booking_id' => $lockedBooking->id,
                    'performed_by' => $performedBy,
                    'start_at_utc' => $startUtc->toDateTimeString(),
                    'end_at_utc' => $endUtc->toDateTimeString(),
                    'customer_timezone' => $lockedBooking->customer_timezone,
                ]
            );

            return $lockedBooking->fresh();
        }, 5);
    }
}
