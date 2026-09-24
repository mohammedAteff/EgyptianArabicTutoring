<?php

namespace App\Http\Controllers\Student;

use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Availability\Services\SlotResolver;
use App\Domains\Booking\Exceptions\BookingPolicyViolationException;
use App\Domains\Booking\Exceptions\InvalidBookingStatusTransitionException;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\RescheduleService;
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class RescheduleController extends Controller
{
    public function show(Request $request, int $booking, AvailabilityService $availability, SlotResolver $resolver, TimezoneService $timezones): View|RedirectResponse
    {
        $student = $request->attributes->get('student');
        $ownedBooking = Booking::query()->whereKey($booking)->where('student_id', $student->id)->with('sessionType')->firstOrFail();
        if ($ownedBooking->status !== 'confirmed' || $ownedBooking->start_at_utc->lessThan(now('UTC')->addHours(24))) {
            return redirect()->route('student.dashboard')->with('info', 'Contact your tutor to change this session.');
        }

        $timezone = $this->displayTimezone($student, $ownedBooking, $timezones);
        $from = $request->query('date');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $fromDate = is_string($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)
            ? CarbonImmutable::createFromFormat('!Y-m-d', $from, $timezone)
            : null;
        if (! $fromDate || $fromDate->lessThan($today) || $fromDate->greaterThan($today->addDays(60))) {
            $fromDate = $today;
        }

        $visitorToken = $request->session()->get('student_reschedule_visitor_token');
        if (! is_string($visitorToken) || strlen($visitorToken) !== 64) {
            $visitorToken = Str::random(64);
            $request->session()->put('student_reschedule_visitor_token', $visitorToken);
        }

        $available = $availability->getAvailableSlotsGroupedByDate(
            sessionType: $ownedBooking->sessionType,
            customerTimezone: $timezone,
            fromDate: $fromDate,
            toDate: $fromDate->addDays(13),
            currentVisitorToken: $visitorToken,
        );
        $slots = [];
        foreach ($available as $date => $dailySlots) {
            foreach ($dailySlots as $slot) {
                $slots[$date][] = [
                    'id' => $resolver->issue($ownedBooking->sessionType, $slot, $timezone, $visitorToken),
                    'label' => CarbonImmutable::parse($slot['slot_start_utc'], 'UTC')->setTimezone($timezone)->format('g:i A'),
                ];
            }
        }

        return view('student.reschedule', [
            'booking' => $ownedBooking,
            'timezone' => $timezone,
            'slots' => $slots,
            'fromDate' => $fromDate,
            'idempotencyKey' => Str::random(48),
        ]);
    }

    public function update(Request $request, int $booking, SlotResolver $resolver, RescheduleService $reschedules, TimezoneService $timezones): RedirectResponse
    {
        $student = $request->attributes->get('student');
        $ownedBooking = Booking::query()->whereKey($booking)->where('student_id', $student->id)->with('sessionType')->firstOrFail();
        $input = $request->validate([
            'slot_id' => ['required', 'string', 'max:4096'],
            'idempotency_key' => ['required', 'string', 'size:48'],
        ]);

        if (DB::table('session_reschedules')->where('booking_id', $ownedBooking->id)->where('idempotency_key', $input['idempotency_key'])->exists()) {
            return redirect()->route('student.dashboard')->with('success', 'Your session change was recorded.');
        }

        $timezone = $this->displayTimezone($student, $ownedBooking, $timezones);
        $visitorToken = $request->session()->get('student_reschedule_visitor_token');
        if (! is_string($visitorToken)) {
            return back()->withErrors(['slot_id' => 'Select a new time slot.']);
        }

        try {
            $slot = $resolver->resolve($input['slot_id'], $ownedBooking->sessionType, $timezone, $visitorToken);
            $reschedules->reschedule(
                booking: $ownedBooking,
                newStartUtc: $slot['slot_start_utc'],
                newEndUtc: $slot['slot_end_utc'],
                performedBy: 'student',
                performedById: $student->id,
                idempotencyKey: $input['idempotency_key'],
                customerTimezone: $timezone,
                slotId: $input['slot_id'],
                slotOwnerToken: $visitorToken,
            );
        } catch (SlotUnavailableException|BookingPolicyViolationException|InvalidBookingStatusTransitionException $exception) {
            return back()->withErrors(['slot_id' => $exception->getMessage()]);
        }

        return redirect()->route('student.dashboard')->with('success', 'Your session change was recorded.');
    }

    private function displayTimezone(Student $student, Booking $booking, TimezoneService $timezones): string
    {
        foreach ([$student->preferred_timezone, $booking->customer_timezone, $timezones->getBusinessTimezone()] as $candidate) {
            try {
                return $timezones->validate($candidate);
            } catch (Throwable) {
                continue;
            }
        }

        return 'Africa/Cairo';
    }
}
