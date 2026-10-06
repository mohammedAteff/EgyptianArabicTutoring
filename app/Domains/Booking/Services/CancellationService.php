<?php

namespace App\Domains\Booking\Services;

use App\Domains\Administration\Services\AdminNotificationService;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Exceptions\BookingPolicyViolationException;
use App\Domains\Booking\Exceptions\InvalidBookingStatusTransitionException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Models\Student;
use Carbon\CarbonImmutable;

class CancellationService
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected AnalyticsService $analyticsService,
        protected AvailabilityService $availabilityService,
        protected DatabaseCapability $databaseCapability,
    ) {}

    /**
     * Cancel an existing booking without physically deleting it.
     *
     * @throws InvalidBookingStatusTransitionException
     * @throws BookingPolicyViolationException
     */
    public function cancel(
        Booking $booking,
        string $performedBy = 'customer',
        ?int $performedById = null,
        ?string $reason = null,
        ?string $reasonCode = null,
    ): Booking {
        $snapshot = Booking::query()->whereKey($booking->id)->firstOrFail();

        return $this->databaseCapability->transaction(function () use ($snapshot, $performedBy, $performedById, $reason, $reasonCode) {
            if ($snapshot->start_at_utc && $snapshot->end_at_utc) {
                $this->availabilityService->acquireCalendarDateLocks(
                    CarbonImmutable::instance($snapshot->start_at_utc),
                    CarbonImmutable::instance($snapshot->end_at_utc),
                );
            }

            if ($snapshot->student_id !== null) {
                Student::withTrashed()->whereKey($snapshot->student_id)->lockForUpdate()->firstOrFail();
            }

            $lockedBooking = Booking::query()->whereKey($snapshot->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedBooking->student_id !== (int) $snapshot->student_id
                || ! $lockedBooking->start_at_utc->equalTo($snapshot->start_at_utc)
                || ! $lockedBooking->end_at_utc->equalTo($snapshot->end_at_utc)) {
                throw new BookingPolicyViolationException('The booking changed while cancellation was being prepared. Reload and try again.');
            }

            if ($lockedBooking->status === 'cancelled') {
                return $lockedBooking->fresh(['contact', 'sessionType']);
            }

            if ($lockedBooking->status === 'completed') {
                throw new InvalidBookingStatusTransitionException('Cannot cancel a completed session.');
            }

            if ($lockedBooking->status === 'no_show') {
                throw new InvalidBookingStatusTransitionException('Cannot cancel a session marked as no-show.');
            }

            if ($lockedBooking->status !== 'confirmed') {
                throw new InvalidBookingStatusTransitionException("Only confirmed bookings can be cancelled; current status is '{$lockedBooking->status}'.");
            }

            $policy = app(BookingPolicyService::class);
            $outcome = $policy->cancellationOutcome($lockedBooking, $performedBy);

            $previousStatus = $lockedBooking->status;

            $lockedBooking->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $policy->record($lockedBooking, 'cancellation', $outcome, $performedBy, $performedById, $reasonCode);

            // Record BookingEvent
            BookingEvent::create([
                'booking_id' => $lockedBooking->id,
                'event_type' => 'cancelled',
                'performed_by' => $performedBy,
                'performed_by_id' => $performedById,
                'previous_data' => [
                    'status' => $previousStatus,
                ],
                'new_data' => [
                    'status' => 'cancelled',
                    'cancelled_at' => $lockedBooking->cancelled_at?->toDateTimeString(),
                    'reason' => $reason,
                    'reason_code' => $reasonCode,
                    'outcome' => $outcome,
                ],
                'created_at' => now(),
            ]);

            // Record AuditLog
            $this->auditLogService->log(
                action: 'booking_cancelled',
                entityType: Booking::class,
                entityId: $lockedBooking->id,
                previousData: ['status' => $previousStatus],
                newData: ['status' => 'cancelled', 'reason' => $reason],
                adminId: $performedBy === 'admin' ? $performedById : null
            );

            // Track authoritative server-side analytics event
            $this->analyticsService->trackEvent(
                eventType: 'booking_cancelled',
                page: '/booking/cancel',
                metadata: [
                    'booking_id' => $lockedBooking->id,
                    'performed_by' => $performedBy,
                    'reason' => $reason,
                ]
            );

            $freshBooking = $lockedBooking->fresh(['contact', 'sessionType']);

            try {
                app(AdminNotificationService::class)->notifyBookingCancelled($freshBooking, $reason ?? '');
            } catch (\Throwable) {
                // Internal notification failure must not block cancellation
            }

            return $freshBooking;
        });
    }
}
