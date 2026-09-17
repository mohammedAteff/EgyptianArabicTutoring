<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingService;
use App\Domains\Booking\Services\CancellationService;
use App\Domains\Booking\Services\RescheduleService;
use App\Domains\Contacts\Services\ContactService;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        protected TimezoneService $timezoneService,
        protected RescheduleService $rescheduleService,
        protected CancellationService $cancellationService,
        protected BookingService $bookingService,
        protected ContactService $contactService,
        protected AvailabilityService $availabilityService
    ) {}

    public function index(Request $request): View
    {
        $businessTz = $this->timezoneService->getBusinessTimezone();
        $nowUtc = CarbonImmutable::now('UTC');
        $viewMode = $request->query('view', 'list');

        $query = Booking::query()->with(['contact', 'sessionType']);

        // Filter by status
        $status = $request->query('status', 'all');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Filter by date scope
        $dateScope = $request->query('date', 'all');
        if ($dateScope === 'today') {
            $todayStart = CarbonImmutable::now($businessTz)->startOfDay()->setTimezone('UTC');
            $todayEnd = CarbonImmutable::now($businessTz)->endOfDay()->setTimezone('UTC');
            $query->whereBetween('start_at_utc', [$todayStart, $todayEnd]);
        } elseif ($dateScope === 'upcoming') {
            $query->where('start_at_utc', '>=', $nowUtc);
        } elseif ($dateScope === 'past') {
            $query->where('start_at_utc', '<', $nowUtc);
        }

        // Search
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('confirmation_token', 'like', "%{$search}%")
                    ->orWhereHas('contact', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        // Order by start_at_utc
        $query->orderBy('start_at_utc', $dateScope === 'past' ? 'desc' : 'asc');

        $bookings = $query->paginate(15)->withQueryString();

        // 1. Day view resolution
        $selectedDate = $request->query('date', CarbonImmutable::now($businessTz)->toDateString());
        try {
            $dayCarbon = CarbonImmutable::createFromFormat('Y-m-d', $selectedDate, $businessTz)->startOfDay();
        } catch (\Throwable) {
            $dayCarbon = CarbonImmutable::now($businessTz)->startOfDay();
            $selectedDate = $dayCarbon->toDateString();
        }

        $dayStartUtc = $dayCarbon->setTimezone('UTC');
        $dayEndUtc = $dayCarbon->endOfDay()->setTimezone('UTC');

        $dayBookings = Booking::query()
            ->with(['contact', 'sessionType'])
            ->whereBetween('start_at_utc', [$dayStartUtc, $dayEndUtc])
            ->whereIn('status', ['confirmed', 'completed', 'no_show', 'cancelled'])
            ->orderBy('start_at_utc')
            ->get();

        $prevDay = $dayCarbon->subDay()->toDateString();
        $nextDay = $dayCarbon->addDay()->toDateString();

        // 2. Week view resolution
        $weekStart = $dayCarbon->startOfWeek();
        $weekEnd = $dayCarbon->endOfWeek();
        $weekStartUtc = $weekStart->setTimezone('UTC');
        $weekEndUtc = $weekEnd->setTimezone('UTC');

        $weekBookingsRaw = Booking::query()
            ->with(['contact', 'sessionType'])
            ->whereBetween('start_at_utc', [$weekStartUtc, $weekEndUtc])
            ->whereIn('status', ['confirmed', 'completed', 'no_show', 'cancelled'])
            ->orderBy('start_at_utc')
            ->get()
            ->groupBy(function ($b) use ($businessTz) {
                return CarbonImmutable::instance($b->start_at_utc)->setTimezone($businessTz)->toDateString();
            });

        $weekDays = [];
        for ($i = 0; $i < 7; $i++) {
            $dayInstance = $weekStart->addDays($i);
            $dateStr = $dayInstance->toDateString();
            $weekDays[] = [
                'date' => $dateStr,
                'day_name' => $dayInstance->format('D'),
                'formatted' => $dayInstance->format('M j'),
                'is_today' => $dateStr === CarbonImmutable::now($businessTz)->toDateString(),
                'bookings' => $weekBookingsRaw->get($dateStr, collect()),
            ];
        }

        $prevWeek = $dayCarbon->subWeek()->toDateString();
        $nextWeek = $dayCarbon->addWeek()->toDateString();

        // 3. Month / Calendar mode overview
        $calendarMonth = $request->query('month', CarbonImmutable::now($businessTz)->format('Y-m'));
        try {
            $monthCarbon = CarbonImmutable::createFromFormat('Y-m', $calendarMonth, $businessTz);
        } catch (\Throwable) {
            $monthCarbon = CarbonImmutable::now($businessTz);
            $calendarMonth = $monthCarbon->format('Y-m');
        }

        $monthStartUtc = $monthCarbon->startOfMonth()->setTimezone('UTC');
        $monthEndUtc = $monthCarbon->endOfMonth()->setTimezone('UTC');

        $monthBookings = Booking::query()
            ->with('contact')
            ->whereBetween('start_at_utc', [$monthStartUtc, $monthEndUtc])
            ->whereIn('status', ['confirmed', 'completed', 'no_show'])
            ->get()
            ->groupBy(function ($b) use ($businessTz) {
                return CarbonImmutable::instance($b->start_at_utc)->setTimezone($businessTz)->toDateString();
            });

        $startWeekday = $monthCarbon->startOfMonth()->dayOfWeek;
        $daysInMonth = $monthCarbon->daysInMonth;
        $prevMonth = $monthCarbon->subMonth()->format('Y-m');
        $nextMonth = $monthCarbon->addMonth()->format('Y-m');

        return view('admin.bookings.index', [
            'title' => 'Bookings & Tutoring Calendar',
            'bookings' => $bookings,
            'viewMode' => $viewMode,
            'status' => $status,
            'dateScope' => $dateScope,
            'search' => $search,
            'selectedDate' => $selectedDate,
            'dayCarbon' => $dayCarbon,
            'dayBookings' => $dayBookings,
            'prevDay' => $prevDay,
            'nextDay' => $nextDay,
            'weekDays' => $weekDays,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'prevWeek' => $prevWeek,
            'nextWeek' => $nextWeek,
            'calendarMonth' => $calendarMonth,
            'monthCarbon' => $monthCarbon,
            'monthBookings' => $monthBookings,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'startWeekday' => $startWeekday,
            'daysInMonth' => $daysInMonth,
            'businessTz' => $businessTz,
        ]);
    }

    public function show(Booking $booking): View
    {
        $booking->load(['contact', 'sessionType', 'events']);
        $businessTz = $this->timezoneService->getBusinessTimezone();

        return view('admin.bookings.show', [
            'title' => "Booking #{$booking->id} — ".($booking->contact->name ?? 'Student'),
            'booking' => $booking,
            'businessTz' => $businessTz,
        ]);
    }

    public function updateNotes(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $prevNotes = $booking->notes;
        $booking->update(['notes' => $validated['notes']]);

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'booking_notes_updated',
            'entity_type' => Booking::class,
            'entity_id' => $booking->id,
            'previous_data' => ['notes' => $prevNotes],
            'new_data' => ['notes' => $validated['notes']],
            'created_at' => now(),
        ]);

        return back()->with('success', 'Tutor session notes updated successfully.');
    }

    public function complete(Request $request, Booking $booking): RedirectResponse
    {
        try {
            DB::transaction(function () use ($booking) {
                $locked = Booking::query()->where('id', $booking->id)->lockForUpdate()->firstOrFail();

                if ($locked->status === 'cancelled') {
                    throw new \DomainException('Cannot complete a cancelled session.');
                }
                if ($locked->status === 'completed') {
                    throw new \DomainException('Session is already completed.');
                }
                if ($locked->status === 'no_show') {
                    throw new \DomainException('Cannot complete a session marked as no-show.');
                }

                $previousStatus = $locked->status;
                $locked->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                BookingEvent::create([
                    'booking_id' => $locked->id,
                    'event_type' => 'completed',
                    'performed_by' => 'admin',
                    'performed_by_id' => Auth::id(),
                    'previous_data' => ['status' => $previousStatus],
                    'new_data' => ['status' => 'completed', 'completed_at' => now()->toDateTimeString()],
                    'created_at' => now(),
                ]);

                AuditLog::create([
                    'administrator_id' => Auth::id(),
                    'action' => 'booking_marked_completed',
                    'entity_type' => Booking::class,
                    'entity_id' => $locked->id,
                    'created_at' => now(),
                ]);
            });

            return back()->with('success', 'Booking marked as completed.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function markNoShow(Request $request, Booking $booking): RedirectResponse
    {
        try {
            DB::transaction(function () use ($booking) {
                $locked = Booking::query()->where('id', $booking->id)->lockForUpdate()->firstOrFail();

                if ($locked->status === 'cancelled') {
                    throw new \DomainException('Cannot mark a cancelled session as no-show.');
                }
                if ($locked->status === 'no_show') {
                    throw new \DomainException('Session is already marked as no-show.');
                }
                if ($locked->status === 'completed') {
                    throw new \DomainException('Cannot mark a completed session as no-show.');
                }

                $previousStatus = $locked->status;
                $locked->update(['status' => 'no_show']);

                BookingEvent::create([
                    'booking_id' => $locked->id,
                    'event_type' => 'marked_no_show',
                    'performed_by' => 'admin',
                    'performed_by_id' => Auth::id(),
                    'previous_data' => ['status' => $previousStatus],
                    'new_data' => ['status' => 'no_show'],
                    'created_at' => now(),
                ]);

                AuditLog::create([
                    'administrator_id' => Auth::id(),
                    'action' => 'booking_marked_no_show',
                    'entity_type' => Booking::class,
                    'entity_id' => $locked->id,
                    'created_at' => now(),
                ]);
            });

            return back()->with('success', 'Booking marked as student no-show.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reschedule(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'new_date' => ['required', 'date_format:Y-m-d'],
            'new_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $businessTz = $this->timezoneService->getBusinessTimezone();
            $dateTimeString = "{$validated['new_date']} {$validated['new_time']}:00";
            $newStartUtc = $this->timezoneService->toUtc($dateTimeString, $businessTz);

            $slotConfig = $this->availabilityService->resolveSlotConfiguration(
                sessionType: $booking->sessionType,
                startUtc: $newStartUtc,
                endUtc: null
            );
            $newEndUtc = $slotConfig['end_utc'];

            $this->rescheduleService->reschedule(
                booking: $booking,
                newStartUtc: $newStartUtc,
                newEndUtc: $newEndUtc,
                performedBy: 'admin',
                performedById: Auth::id(),
                reason: $validated['reason'] ?? 'Rescheduled by tutor'
            );

            return back()->with('success', 'Lesson rescheduled successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Reschedule failed: '.$e->getMessage());
        }
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->cancellationService->cancel(
                booking: $booking,
                performedBy: 'admin',
                performedById: Auth::id(),
                reason: $validated['cancellation_reason']
            );

            return back()->with('success', 'Booking cancelled successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Cancellation failed: '.$e->getMessage());
        }
    }

    public function create(): View
    {
        $sessionTypes = SessionType::where('active', true)->orderBy('title')->get();
        $businessTz = $this->timezoneService->getBusinessTimezone();

        return view('admin.bookings.create', [
            'title' => 'Create Student Booking',
            'sessionTypes' => $sessionTypes,
            'businessTz' => $businessTz,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $businessTz = $this->timezoneService->getBusinessTimezone();

        $validated = $request->validate([
            'session_type_id' => ['required', 'exists:session_types,id'],
            'student_name' => ['required', 'string', 'max:255'],
            'student_email' => ['required', 'email', 'max:255'],
            'student_phone' => ['nullable', 'string', 'max:50'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'customer_timezone' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $sessionType = SessionType::findOrFail($validated['session_type_id']);
        $customerTimezone = $validated['customer_timezone'] ?? $businessTz;

        try {
            $startUtc = $this->timezoneService->toUtc("{$validated['date']} {$validated['time']}:00", $businessTz);

            $slotConfig = $this->availabilityService->resolveSlotConfiguration(
                sessionType: $sessionType,
                startUtc: $startUtc,
                endUtc: null
            );
            $endUtc = $slotConfig['end_utc'];

            $booking = $this->bookingService->createAdminBooking([
                'session_type_id' => $sessionType->id,
                'customer_name' => $validated['student_name'],
                'customer_email' => $validated['student_email'],
                'customer_phone' => $validated['student_phone'] ?? null,
                'customer_timezone' => $customerTimezone,
                'business_timezone' => $businessTz,
                'start_at_utc' => $startUtc,
                'end_at_utc' => $endUtc,
                'notes' => $validated['notes'] ?? 'Booked manually by administrator',
                'idempotency_key' => (string) Str::uuid(),
            ], Auth::id());

            AuditLog::create([
                'administrator_id' => Auth::id(),
                'action' => 'admin_booking_created',
                'entity_type' => Booking::class,
                'entity_id' => $booking->id,
                'new_data' => [
                    'confirmation_token' => $booking->confirmation_token,
                    'start_at_utc' => $booking->start_at_utc,
                ],
                'created_at' => now(),
            ]);

            return redirect()->route('admin.bookings.show', $booking->id)
                ->with('success', 'Booking #'.substr($booking->confirmation_token, 0, 8).' created successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Booking creation failed: '.$e->getMessage());
        }
    }
}
