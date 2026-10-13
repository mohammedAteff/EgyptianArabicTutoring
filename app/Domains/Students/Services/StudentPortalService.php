<?php

namespace App\Domains\Students\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\MeetingLinkService;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Services\FormAssignmentService;
use App\Domains\Students\Models\LessonFeedback;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Timezone\Services\TimezoneService;
use Illuminate\Http\Request;

class StudentPortalService
{
    public function bookingStatusLabel(Booking $booking): string
    {
        return match ($booking->studentStatusLabel()) {
            'Session Delivered' => 'Completed',
            'Booking Canceled' => 'Cancelled',
            'Session Forfeited' => 'Missed',
            'Session Rescheduled' => 'Rescheduled',
            'Confirmed' => 'Confirmed',
            'Reservation Held' => 'Time Reserved',
            'Pending Confirmation' => 'Awaiting Confirmation',
            default => 'Status Unavailable',
        };
    }

    /** @return array<string, mixed> */
    public function data(Request $request, bool $readOnly = false): array
    {
        $timezones = app(TimezoneService::class);
        $ledger = app(StudentLedgerService::class);
        $formAssignments = app(FormAssignmentService::class);
        $meetingLinks = app(MeetingLinkService::class);
        /** @var Student $student */
        $student = $request->attributes->get('student');
        $bookings = Booking::query()
            ->where('student_id', $student->id)
            ->with(['sessionType', 'lessonMaterials' => fn ($query) => $query->visibleToStudent()
                ->select(['id', 'booking_id', 'kind', 'title', 'description', 'sort_order'])])
            ->withExists('reschedules')
            ->orderBy('start_at_utc')
            ->get();

        $upcoming = $bookings->filter(fn (Booking $booking): bool => $booking->start_at_utc->isFuture() && in_array($booking->status, ['confirmed', 'held', 'pending'], true));
        $history = $bookings->reject(fn (Booking $booking): bool => $upcoming->contains('id', $booking->id))->reverse();
        $packages = StudentPackage::query()->where('student_id', $student->id)->with(['entitlements.type', 'ledgerEntries', 'payments', 'refunds'])->orderByDesc('created_at')->get();
        $displayTimezone = $readOnly ? ($student->preferred_timezone ?: $timezones->getBusinessTimezone()) : $request->session()->get('student_display_timezone', $student->preferred_timezone ?: $timezones->getBusinessTimezone());
        $packageSummaries = $packages->map(fn (StudentPackage $package): array => [
            'name' => $package->package_name,
            'id' => $package->id, 'offering_key' => $package->offering_key ?? 'legacy_unclassified',
            'expiry' => $package->expires_at
                ? $package->expires_at->setTimezone($displayTimezone)->format('j M Y, g:i:s A T')
                : $package->expiryLabel(),
            'status' => $package->status,
            'currency' => $package->currency,
            'summary' => $ledger->summary($package, true),
        ]);
        $forms = $formAssignments->assignedTo($student);
        $formSubmissions = FormSubmission::query()->where('student_id', $student->id)->with('version:id,form_id')->orderByDesc('id')->get()
            ->groupBy(fn (FormSubmission $submission): int => (int) $submission->version->form_id)
            ->map(fn ($items) => $items->first());

        $balances = app(EntitlementService::class)->forPackages($packages);

        return app(StudentTeachingReadModel::class)->data($student, $bookings, $packages, $forms, $formSubmissions, $readOnly) + [
            'student' => $student,
            'upcoming' => $upcoming,
            'history' => $history,
            'bookingStatusLabels' => $bookings->mapWithKeys(fn (Booking $booking): array => [$booking->id => $this->bookingStatusLabel($booking)])->all(),
            'packageSummaries' => $packageSummaries,
            'entitlementBalances' => $balances,
            'totalPurchased' => (int) $packages->sum('total_sessions_allocated'),
            'completedCount' => $bookings->where('status', 'completed')->count(),
            'availableCredits' => array_sum(array_column($balances, 'available')),
            'lessonFeedback' => LessonFeedback::query()->where('student_id', $student->id)->get()->keyBy('booking_id'),
            'businessTimezone' => $timezones->getBusinessTimezone(),
            'tutorName' => config('business.tutor_name'),
            'timezone' => $displayTimezone,
            'meetingLinks' => $meetingLinks,
            'forms' => $forms,
            'formSubmissions' => $formSubmissions,
        ];
    }
}
