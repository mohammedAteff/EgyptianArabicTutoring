<?php

namespace App\Http\Controllers\Student;

use App\Domains\Booking\Models\BookingWaitlist;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentUnavailability;
use App\Domains\Students\Services\StudentSchedulingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SchedulingController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Student $student */
        $student = $request->attributes->get('student');

        return response()->view('student.scheduling', ['student' => $student,
            'interests' => BookingWaitlist::query()->where('student_id', $student->id)->with('sessionType')->orderByDesc('id')->paginate(15, ['*'], 'waitlist_page'),
            'holidays' => StudentUnavailability::query()->where('student_id', $student->id)->orderByDesc('id')->paginate(15, ['*'], 'holiday_page'),
            'sessionTypes' => SessionType::query()->where('active', true)->orderBy('title')->get()])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, string $kind, StudentSchedulingService $service): RedirectResponse
    {
        /** @var Student $student */
        $student = $request->attributes->get('student');
        $data = $request->only(['session_type_id', 'date_from', 'date_to', 'timezone', 'reason_code', 'notes', 'idempotency_key']);
        if ($kind === 'holiday') {
            $service->holiday($student, $data);
        } else {
            $service->waitlist($student, $data);
        }

        return back()->with('success', $kind === 'holiday' ? 'Unavailability saved. Contact your tutor about any existing appointments.' : 'Interest registered. Your tutor will review it; this does not reserve a lesson.');
    }

    public function withdraw(Request $request, BookingWaitlist $interest, StudentSchedulingService $service): RedirectResponse
    {
        /** @var Student $student */
        $student = $request->attributes->get('student');
        abort_unless($interest->student_id === $student->id, 404);
        $service->waitlistStatus($interest, 'withdrawn');

        return back()->with('success', 'Interest withdrawn.');
    }

    public function closeHoliday(Request $request, StudentUnavailability $holiday, StudentSchedulingService $service): RedirectResponse
    {
        /** @var Student $student */
        $student = $request->attributes->get('student');
        abort_unless($holiday->student_id === $student->id, 404);
        $service->closeHoliday($holiday);

        return back()->with('success', 'Unavailability cancelled.');
    }
}
