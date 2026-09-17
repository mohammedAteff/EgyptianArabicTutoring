<?php

namespace App\Domains\Booking\Services;

use App\Domains\Administration\Services\AdminNotificationService;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Booking\Exceptions\BookingPolicyViolationException;
use App\Domains\Booking\Exceptions\InvalidBookingStatusTransitionException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\CMS\Models\Setting;
use Illuminate\Support\Facades\DB;

class CancellationService
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected AnalyticsService $analyticsService
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
        ?string $reason = null
    ): Booking {
        return DB::transaction(function () use ($booking, $performedBy, $performedById, $reason) {
            $lockedBooking = Booking::query()->where('id', $booking->id)->lockForUpdate()->firstOrFail();

            if ($lockedBooking->status === 'cancelled') {
                throw new InvalidBookingStatusTransitionException('This booking has already been cancelled.');
            }

            if ($lockedBooking->status === 'completed') {
                throw new InvalidBookingStatusTransitionException('Cannot cancel a completed session.');
            }

            if ($lockedBooking->status === 'no_show') {
                throw new InvalidBookingStatusTransitionException('Cannot cancel a session marked as no-show.');
            }

            if (! in_array($lockedBooking->status, ['confirmed', 'pending'], true)) {
                throw new InvalidBookingStatusTransitionException("Cannot cancel a booking with status '{$lockedBooking->status}'.");
            }

            if ($performedBy === 'customer') {
                if ($lockedBooking->start_at_utc <= now('UTC')) {
                    throw new BookingPolicyViolationException('Past appointments cannot be cancelled.');
                }

                $cutoffHours = (int) Setting::get('booking_cancellation_cutoff_hours', 24);
                if ($lockedBooking->start_at_utc < now('UTC')->addHours($cutoffHours)) {
                    throw new BookingPolicyViolationException("Appointments cannot be cancelled within {$cutoffHours} hours of the scheduled start time.");
                }
            }

            $previousStatus = $lockedBooking->status;

            $lockedBooking->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

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
