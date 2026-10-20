<?php

namespace App\Domains\Students\Services;

use App\Domains\Administration\Services\AdminNotificationService;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\BookingWaitlist;
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use App\Policies\StudentTeachingPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class WaitlistAvailabilityService
{
    /** @return list<array{start: string, end: string, label: string, booking_url: string, admin_booking_url: string}> */
    public function matches(BookingWaitlist $interest, int $limit = 3): array
    {
        $interest->loadMissing(['student', 'sessionType']);
        if (! in_array($interest->status, ['open', 'contacted'], true) || ! $interest->sessionType->active
            || ! $interest->sessionType->student_discoverable || $interest->student->operational_status !== 'active'
            || $interest->student->suspended_at || $interest->student->identity_status !== 'verified' || $interest->student->merged_into_student_id) {
            return [];
        }
        $today = CarbonImmutable::now($interest->timezone)->startOfDay();
        $from = CarbonImmutable::parse($interest->date_from->toDateString(), $interest->timezone)->max($today);
        $through = CarbonImmutable::parse($interest->date_to->toDateString(), $interest->timezone)->endOfDay()->min($today->addDays(90));
        if ($from->greaterThan($through)) {
            return [];
        }
        $days = app(AvailabilityService::class)->getAvailableSlotsGroupedByDate($interest->sessionType, $interest->timezone, $from, $through);
        $matches = [];
        foreach ($days as $slots) {
            foreach ($slots as $slot) {
                $start = CarbonImmutable::parse($slot['slot_start_utc'], 'UTC');
                $end = CarbonImmutable::parse($slot['slot_end_utc'], 'UTC');
                $local = $start->setTimezone($interest->timezone);
                $time = $local->format('H:i:s');
                $earliest = $interest->preferred_time_from;
                $latest = $interest->preferred_time_to;
                if ($earliest && $latest && ($earliest <= $latest ? ($time < $earliest || $time > $latest) : ($time < $earliest && $time > $latest))) {
                    continue;
                }
                try {
                    app(StudentSchedulingService::class)->assertAvailable($interest->student_id, $start, $end);
                } catch (SlotUnavailableException) {
                    continue;
                }
                $businessStart = $start->setTimezone(app(TimezoneService::class)->getBusinessTimezone());
                $matches[] = ['admin_booking_url' => route('admin.bookings.create', ['student_id' => $interest->student_id, 'session_type_id' => $interest->session_type_id, 'funding_mode' => $interest->sessionType->funding_mode === 'package' ? 'package' : 'direct', 'date' => $businessStart->toDateString(), 'start_time' => $businessStart->format('H:i')]), 'start' => $start->toDateTimeString(), 'end' => $end->toDateTimeString(),
                    'label' => $local->format('D j M, g:i A').'–'.$end->setTimezone($interest->timezone)->format('g:i A').' · '.$interest->timezone,
                    'booking_url' => $interest->sessionType->funding_mode === 'package'
                        ? route('student.bookings.create', ['session_type_id' => $interest->session_type_id, 'date' => $local->toDateString(), 'timezone' => $interest->timezone])
                        : route('booking.index', ['session_type' => $interest->session_type_id])];
                if (count($matches) >= $limit) {
                    return $matches;
                }
            }
        }

        return $matches;
    }

    public function notifyOpenings(): int
    {
        $sent = 0;
        BookingWaitlist::query()->whereIn('status', ['open', 'contacted'])->where('date_to', '>=', now('UTC')->subDay()->toDateString())
            ->with(['student', 'sessionType'])->chunkById(50, function ($interests) use (&$sent): void {
                foreach ($interests as $interest) {
                    $opening = $this->matches($interest, 1)[0] ?? null;
                    if (! $opening || $interest->last_offered_start_at?->toDateTimeString() === $opening['start']) {
                        continue;
                    }
                    DB::transaction(function () use ($interest, $opening, &$sent): void {
                        $student = Student::query()->lockForUpdate()->findOrFail($interest->student_id);
                        $locked = BookingWaitlist::query()->lockForUpdate()->findOrFail($interest->id);
                        if (! app(StudentTeachingPolicy::class)->usePortal($student) || $student->operational_status !== 'active') {
                            return;
                        }
                        if (! in_array($locked->status, ['open', 'contacted'], true) || $locked->last_offered_start_at?->toDateTimeString() === $opening['start']) {
                            return;
                        }
                        app(StudentNotificationService::class)->synchronize($student, [['key' => 'waitlist:'.$locked->id.':'.$opening['start'],
                            'type' => 'waitlist', 'title' => 'A preferred lesson time is available',
                            'message' => $opening['label'].'. Review current availability and confirm to book. This is not a reservation.', 'link' => $opening['booking_url']]]);
                        app(AdminNotificationService::class)->send('waitlist_opening', 'Waitlist opening · '.$student->name,
                            $opening['label'].'. Review the student’s preference before booking.', route('admin.waitlist.index'), ['student_id' => $student->id, 'waitlist_id' => $locked->id]);
                        $locked->update(['last_offered_start_at' => $opening['start'], 'availability_notified_at' => now('UTC')]);
                        $sent++;
                    }, 3);
                }
            });

        return $sent;
    }
}
