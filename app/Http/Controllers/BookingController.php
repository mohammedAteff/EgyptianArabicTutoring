<?php

namespace App\Http\Controllers;

use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\CancellationService;
use App\Domains\Booking\Services\IcsGenerator;
use App\Domains\Booking\Services\RescheduleService;
use App\Domains\CMS\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(): View
    {
        return view('public.booking', [
            'title' => 'Book a Private Egyptian Arabic Lesson',
        ]);
    }

    public function confirmation(string $token): View
    {
        $booking = Booking::query()
            ->where('confirmation_token', $token)
            ->with(['sessionType', 'contact'])
            ->firstOrFail();

        return view('public.confirmation', [
            'booking' => $booking,
            'title' => 'Booking Confirmed — #'.substr($booking->confirmation_token, 0, 8),
        ]);
    }

    public function ics(string $token, IcsGenerator $icsGenerator): Response
    {
        $booking = Booking::query()
            ->where('confirmation_token', $token)
            ->with(['sessionType'])
            ->firstOrFail();

        $icsContent = $icsGenerator->generate($booking);

        return response($icsContent, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="egyptian-arabic-lesson-'.substr($booking->confirmation_token, 0, 8).'.ics"',
        ]);
    }

    public function cancel(Request $request, string $token, CancellationService $cancellationService): RedirectResponse
    {
        $booking = Booking::query()
            ->where('confirmation_token', $token)
            ->firstOrFail();

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $cancellationService->cancel(
                booking: $booking,
                performedBy: 'customer',
                reason: $validated['reason'] ?? 'Cancelled by student'
            );

            return redirect()->route('booking.confirmation', ['token' => $token])
                ->with('success', 'Your booking has been cancelled successfully.');
        } catch (\Exception $e) {
            return redirect()->route('booking.confirmation', ['token' => $token])
                ->with('error', 'Unable to cancel: '.$e->getMessage());
        }
    }

    public function showReschedule(
        string $token,
        AvailabilityService $availabilityService
    ): View|RedirectResponse {
        $booking = Booking::query()
            ->where('confirmation_token', $token)
            ->with(['sessionType', 'contact'])
            ->firstOrFail();

        if ($booking->status !== 'confirmed') {
            return redirect()->route('booking.confirmation', ['token' => $token])
                ->with('info', "Bookings with status '{$booking->status}' cannot be rescheduled.");
        }

        if ($booking->start_at_utc <= now('UTC')) {
            return redirect()->route('booking.confirmation', ['token' => $token])
                ->with('error', 'Past appointments cannot be rescheduled.');
        }

        $cutoffHours = (int) Setting::get('booking_cancellation_cutoff_hours', 24);
        if ($booking->start_at_utc < now('UTC')->addHours($cutoffHours)) {
            return redirect()->route('booking.confirmation', ['token' => $token])
                ->with('error', "Appointments cannot be rescheduled within {$cutoffHours} hours of the scheduled start time.");
        }

        $availableSlots = $availabilityService->getAvailableSlotsGroupedByDate(
            sessionType: $booking->sessionType,
            customerTimezone: $booking->customer_timezone,
            fromDate: now('UTC'),
            toDate: now('UTC')->addDays(30)
        );

        return view('public.reschedule', [
            'booking' => $booking,
            'availableSlots' => $availableSlots,
            'title' => 'Reschedule Your Lesson — #'.substr($booking->confirmation_token, 0, 8),
        ]);
    }

    public function processReschedule(
        Request $request,
        string $token,
        RescheduleService $rescheduleService,
        AvailabilityService $availabilityService
    ): RedirectResponse {
        $booking = Booking::query()
            ->where('confirmation_token', $token)
            ->with('sessionType')
            ->firstOrFail();

        if ($booking->status !== 'confirmed') {
            return redirect()->route('booking.confirmation', ['token' => $token])
                ->with('error', "Cannot reschedule a booking with status '{$booking->status}'.");
        }

        $validated = $request->validate([
            'new_start_utc' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $startUtc = CarbonImmutable::parse($validated['new_start_utc'], 'UTC');

        try {
            $slotConfig = $availabilityService->resolveSlotConfiguration(
                sessionType: $booking->sessionType,
                startUtc: $startUtc,
                endUtc: null
            );
            $endUtc = $slotConfig['end_utc'];

            $rescheduleService->reschedule(
                booking: $booking,
                newStartUtc: $startUtc,
                newEndUtc: $endUtc,
                performedBy: 'customer',
                reason: $validated['reason'] ?? 'Rescheduled by student'
            );

            return redirect()->route('booking.confirmation', ['token' => $token])
                ->with('success', 'Your session has been successfully rescheduled!');
        } catch (\Exception $e) {
            return back()->with('error', 'Unable to reschedule: '.$e->getMessage());
        }
    }
}
