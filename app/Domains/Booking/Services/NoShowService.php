<?php

namespace App\Domains\Booking\Services;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Models\Student;
use Carbon\CarbonImmutable;
use DomainException;

class NoShowService
{
    public function mark(Booking $booking, ?int $administratorId, ?string $reasonCode = null): Booking
    {
        $snapshot = Booking::query()->findOrFail($booking->id);

        return app(DatabaseCapability::class)->transaction(function () use ($snapshot, $administratorId, $reasonCode): Booking {
            app(AvailabilityService::class)->acquireCalendarDateLocks(CarbonImmutable::instance($snapshot->start_at_utc), CarbonImmutable::instance($snapshot->end_at_utc));
            if ($snapshot->student_id !== null) {
                Student::withTrashed()->whereKey($snapshot->student_id)->lockForUpdate()->firstOrFail();
            }
            $locked = Booking::query()->lockForUpdate()->findOrFail($snapshot->id);
            if ((int) $locked->student_id !== (int) $snapshot->student_id || ! $locked->start_at_utc->equalTo($snapshot->start_at_utc) || ! $locked->end_at_utc->equalTo($snapshot->end_at_utc)) {
                throw new DomainException('The booking changed. Reload and try again.');
            }
            if ($locked->status !== 'confirmed') {
                throw new DomainException('Only confirmed sessions can be marked as no-show.');
            }
            $policy = app(BookingPolicyService::class);
            $outcome = $policy->forBooking($locked)['no_show'];
            $locked->update(['status' => 'no_show']);
            $policy->record($locked, 'no_show', $outcome, 'admin', $administratorId, $reasonCode);
            BookingEvent::query()->create(['booking_id' => $locked->id, 'event_type' => 'marked_no_show',
                'performed_by' => 'admin', 'performed_by_id' => $administratorId, 'previous_data' => ['status' => 'confirmed'],
                'new_data' => ['status' => 'no_show', 'outcome' => $outcome, 'reason_code' => $reasonCode], 'created_at' => now('UTC')]);
            app(AuditLogService::class)->log('booking_marked_no_show', Booking::class, $locked->id, ['status' => 'confirmed'], ['status' => 'no_show', 'outcome' => $outcome, 'reason_code' => $reasonCode], $administratorId);

            return $locked;
        }, 3);
    }
}
