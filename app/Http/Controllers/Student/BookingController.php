<?php

namespace App\Http\Controllers\Student;

use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Availability\Services\SlotResolver;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\StudentBookingService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class BookingController extends Controller
{
    public function create(Request $request, AvailabilityService $availability, SlotResolver $resolver, TimezoneService $timezones, StudentLedgerService $ledger): View
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

        $packages = StudentPackage::query()->where('student_id', $student->id)->get();
        $availableCredits = (int) $packages->sum(fn (StudentPackage $package): int => $ledger->summary($package)['remaining_credits']);
        $sessionTypes = SessionType::query()->where('active', true)->orderBy('duration_minutes')->get();
        $selectedSessionType = $sessionTypes->firstWhere('id', (int) $request->query('session_type_id'));
        $slots = [];
        if ($selectedSessionType && $availableCredits > 0) {
            $ownerToken = $this->slotOwnerToken($request, $student);
            $available = $availability->getAvailableSlotsGroupedByDate(
                sessionType: $selectedSessionType,
                customerTimezone: $timezone,
                fromDate: $fromDate,
                toDate: $fromDate->addDays(13),
                currentVisitorToken: $ownerToken,
            );
            foreach ($available as $date => $dailySlots) {
                foreach ($dailySlots as $slot) {
                    $slots[$date][] = [
                        'slot_id' => $resolver->issue($selectedSessionType, $slot, $timezone, $ownerToken),
                        'label' => CarbonImmutable::parse($slot['slot_start_utc'], 'UTC')->setTimezone($timezone)->format('g:i A'),
                    ];
                }
            }
        }

        return view('student.bookings.create', [
            'sessionTypes' => $sessionTypes,
            'selectedSessionType' => $selectedSessionType,
            'timezone' => $timezone,
            'fromDate' => $fromDate,
            'slots' => $slots,
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

        return redirect()->route('student.dashboard')->with('success', 'Your confirmed session is booked and one package credit has been reserved.');
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
