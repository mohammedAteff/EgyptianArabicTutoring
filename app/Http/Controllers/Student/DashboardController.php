<?php

namespace App\Http\Controllers\Student;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\MeetingLinkService;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Services\FormAssignmentService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, TimezoneService $timezones, StudentLedgerService $ledger, FormAssignmentService $formAssignments, MeetingLinkService $meetingLinks): View
    {
        /** @var Student $student */
        $student = $request->attributes->get('student');
        $bookings = Booking::query()
            ->where('student_id', $student->id)
            ->with('sessionType')
            ->orderBy('start_at_utc')
            ->get();

        $upcoming = $bookings->filter(fn (Booking $booking): bool => $booking->start_at_utc->isFuture() && in_array($booking->status, ['confirmed', 'held', 'pending'], true));
        $history = $bookings->reject(fn (Booking $booking): bool => $upcoming->contains('id', $booking->id))->reverse();
        $packages = StudentPackage::query()->where('student_id', $student->id)->orderByDesc('created_at')->get();
        $packageSummaries = $packages->map(fn (StudentPackage $package): array => [
            'name' => $package->package_name,
            'status' => $package->status,
            'currency' => $package->currency,
            'summary' => $ledger->summary($package),
        ]);
        $forms = $formAssignments->assignedTo($student);
        $formSubmissions = FormSubmission::query()->where('student_id', $student->id)->with('version:id,form_id')->orderByDesc('id')->get()
            ->groupBy(fn (FormSubmission $submission): int => (int) $submission->version->form_id)
            ->map(fn ($items) => $items->first());

        return view('student.dashboard', [
            'student' => $student,
            'upcoming' => $upcoming,
            'history' => $history,
            'packageSummaries' => $packageSummaries,
            'totalPurchased' => (int) $packages->sum('total_sessions_allocated'),
            'completedCount' => $bookings->where('status', 'completed')->count(),
            'availableCredits' => (int) $packageSummaries->sum(fn (array $package): int => $package['summary']['remaining_credits']),
            'businessTimezone' => $timezones->getBusinessTimezone(),
            'tutorName' => config('business.tutor_name'),
            'meetingUrl' => $meetingLinks->current(),
            'forms' => $forms,
            'formSubmissions' => $formSubmissions,
        ]);
    }
}
