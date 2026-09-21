<?php

namespace App\Http\Controllers;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\CancellationService;
use App\Domains\Booking\Services\IcsGenerator;
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

    public function showReschedule(string $token): RedirectResponse
    {
        Booking::query()->where('confirmation_token', $token)->firstOrFail();

        return redirect()->route('booking.confirmation', ['token' => $token])
            ->with('info', 'To reschedule, please contact Abdallah directly by WhatsApp, Telegram, or email at least 24 hours before your lesson.');
    }

    public function processReschedule(string $token): never
    {
        abort(403, 'Customer self-service rescheduling is disabled. Contact the tutor directly.');
    }
}
