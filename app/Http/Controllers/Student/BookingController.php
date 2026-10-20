<?php

namespace App\Http\Controllers\Student;

use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Availability\Services\BookingSlotPresenter;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\MeetingLinkService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentUnavailability;
use App\Domains\Students\Services\EntitlementService;
use App\Domains\Students\Services\StudentBookingService;
use App\Domains\Students\Services\StudentPortalService;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class BookingController extends Controller
{
    public function show(Request $request, int $booking, TimezoneService $timezones, MeetingLinkService $meetingLinks, StudentPortalService $portal): View
    {
        /** @var Student $student */
        $student = $request->attributes->get('student');
        $owned = Booking::query()->where('student_id', $student->id)->with('sessionType')->withExists('reschedules')->findOrFail($booking);
        $timezone = $this->displayTimezone($request->session()->get('student_display_timezone'), $student, $timezones);
        $creditHistory = SessionLedgerEntry::query()->where('student_id', $student->id)->where('booking_id', $owned->id)->get();

        return view('student.bookings.show', [
            'booking' => $owned, 'timezone' => $timezone,
            'statusLabel' => $portal->bookingStatusLabel($owned),
            'meetingUrl' => $meetingLinks->studentUrl($owned),
            'meetingRevealMinutes' => (int) Setting::get('meeting.student_reveal_minutes', 15),
            'canReschedule' => $owned->status === 'confirmed' && now('UTC')->addHours(24)->lte($owned->start_at_utc),
            'canViewMaterials' => Gate::forUser($student)->allows('viewLessonWorkspace', $owned),
            'canAddToCalendar' => $owned->status === 'confirmed' && $owned->end_at_utc->isFuture() && (bool) $owned->confirmation_token,
            'creditEntry' => $creditHistory->firstWhere('entry_type', 'session_consumed'),
            'creditUsed' => $creditHistory->sum('credit_change') < 0,
        ]);
    }

    public function create(Request $request, TimezoneService $timezones): View
    {
        /** @var Student $student */
        $student = $request->attributes->get('student');
        $timezone = $this->displayTimezone($request->query('timezone'), $student, $timezones);
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $dateInput = $request->query('date');
        try {
            $fromDate = is_string($dateInput) ? CarbonImmutable::createFromFormat('!Y-m-d', $dateInput, $timezone) : null;
        } catch (Throwable) {
            $fromDate = null;
        }
        if (! $fromDate || $fromDate->lessThan($today) || $fromDate->greaterThan($today->addDays(60))) {
            $fromDate = $today;
        }

        $entitlements = app(EntitlementService::class)->forStudent($student->id);
        $sessionTypes = SessionType::query()->studentDiscoverable()->where('funding_mode', 'package')->with('requiredEntitlementType')->orderBy('duration_minutes')->get();
        $selectedSessionType = $sessionTypes->firstWhere('id', (int) $request->query('session_type_id'));
        $fundableTypes = $sessionTypes->filter(fn (SessionType $type): bool => app(EntitlementService::class)->canFund($student->id, $type));
        $hasFundableFormat = $fundableTypes->isNotEmpty();
        if (! $request->filled('session_type_id')) {
            $selectedSessionType = $fundableTypes->first();
        }
        $selectedBalance = $selectedSessionType ? ($entitlements[$selectedSessionType->requiredEntitlementType?->code]['available'] ?? 0) : array_sum(array_column($entitlements, 'available'));
        $availableCredits = $selectedSessionType && ! app(EntitlementService::class)->canFund($student->id, $selectedSessionType) ? 0 : $selectedBalance;
        $slots = [];
        if ($selectedSessionType && $availableCredits > 0) {
            $ownerToken = $this->slotOwnerToken($request, $student);
            $available = app(BookingSlotPresenter::class)->forMonth(
                $selectedSessionType, $timezone, $fromDate, $fromDate->endOfMonth()->min($today->addDays(60)), $ownerToken,
            );
            $unavailablePeriods = StudentUnavailability::query()->where('student_id', $student->id)->where('status', 'active')
                ->where('ends_at_utc', '>', $fromDate->utc())
                ->where('starts_at_utc', '<', $fromDate->endOfMonth()->min($today->addDays(60))->addDay()->utc())->get();
            foreach ($available as $date => $dailySlots) {
                foreach ($dailySlots as $slot) {
                    $start = CarbonImmutable::parse($slot['slot_start_utc'], 'UTC');
                    $end = CarbonImmutable::parse($slot['slot_end_utc'], 'UTC');
                    if ($unavailablePeriods->contains(fn (StudentUnavailability $period): bool => $period->starts_at_utc->lt($end) && $period->ends_at_utc->gt($start))) {
                        continue;
                    }
                    $slots[$date][] = $slot;
                }
            }
        }

        return view('student.bookings.create', [
            'sessionTypes' => $sessionTypes,
            'hasFundableFormat' => $hasFundableFormat,
            'fundableTypeIds' => $fundableTypes->modelKeys(),
            'selectedSessionType' => $selectedSessionType,
            'timezone' => $timezone,
            'fromDate' => $fromDate,
            'slots' => $slots,
            'busyTimes' => Booking::query()->where('status', 'confirmed')->where('start_at_utc', '>=', $fromDate->utc())->where('start_at_utc', '<', $fromDate->endOfMonth()->addDay()->utc())->where('start_at_utc', '>', now('UTC'))->orderBy('start_at_utc')->limit(100)->get(['start_at_utc', 'end_at_utc']),
            'entitlementBalances' => $entitlements,
            'availableCredits' => $availableCredits,
            'idempotencyKey' => Str::random(48),
        ]);
    }

    public function store(Request $request, TimezoneService $timezones, StudentBookingService $bookings): RedirectResponse
    {
        /** @var Student $student */
        $student = $request->attributes->get('student');
        $data = $request->validate([
            'session_type_id' => ['required', 'integer', 'exists:session_types,id'],
            'timezone' => ['required', 'string', 'timezone'],
            'slot_id' => ['required', 'string', 'max:4096'],
            'idempotency_key' => ['required', 'alpha_num', 'size:48'],
        ]);
        $timezone = $timezones->validate($data['timezone']);

        try {
            $booking = $bookings->create(
                $student,
                $data['slot_id'],
                (int) $data['session_type_id'],
                $timezone,
                $data['idempotency_key'],
                $this->slotOwnerToken($request, $student),
            );
        } catch (SlotUnavailableException|InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['slot_id' => $exception->getMessage()]);
        }

        app(AnalyticsService::class)->track('package_session_scheduled', ['booking_id' => $booking->id], $request, eventUuid: 'package-booking-'.$booking->id);

        return redirect()->route('student.bookings.show', $booking->id)->with('success', 'Lesson Confirmed. Your lesson has been booked using the required lesson credit.');
    }

    private function displayTimezone(?string $requested, Student $student, TimezoneService $timezones): string
    {
        foreach ([$requested, $student->preferred_timezone, $timezones->getBusinessTimezone()] as $candidate) {
            try {
                return $timezones->validate($candidate);
            } catch (Throwable) {
                continue;
            }
        }

        return 'Africa/Cairo';
    }

    private function slotOwnerToken(Request $request, Student $student): string
    {
        $secret = (string) config('services.student_auth.hmac_key');
        if ($secret === '') {
            $secret = (string) config('app.key');
        }

        return hash_hmac('sha256', 'student-booking:'.$student->id.':'.$request->session()->getId(), $secret);
    }
}
