<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Booking\Models\BookingWaitlist;
use App\Domains\Booking\Models\RecurringLessonOccurrence;
use App\Domains\Booking\Models\RecurringLessonPlan;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentUnavailability;
use App\Domains\Students\Services\StudentSchedulingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentSchedulingController extends Controller
{
    public function index(Student $student): View
    {
        return view('admin.scheduling.student', ['student' => $student,
            'plans' => RecurringLessonPlan::query()->where('student_id', $student->id)->with(['sessionType', 'student'])->orderByDesc('id')->paginate(10, ['*'], 'plans_page'),
            'occurrences' => RecurringLessonOccurrence::query()->whereHas('plan', fn ($query) => $query->where('student_id', $student->id))->with('booking')->orderByDesc('id')->limit(100)->get(),
            'holidays' => StudentUnavailability::query()->where('student_id', $student->id)->orderByDesc('id')->paginate(15, ['*'], 'holidays_page'),
            'sessionTypes' => SessionType::query()->where('active', true)->orderBy('title')->get()]);
    }

    public function holiday(Request $request, Student $student, StudentSchedulingService $service): RedirectResponse
    {
        $service->holiday($student, $request->only(['date_from', 'date_to', 'timezone', 'reason_code', 'notes', 'idempotency_key']), (int) $request->user('web')->id);

        return back()->with('success', 'Unavailability saved. Existing appointments still appear on Today and the calendar.');
    }

    public function closeHoliday(Request $request, Student $student, StudentUnavailability $holiday, StudentSchedulingService $service): RedirectResponse
    {
        abort_unless($holiday->student_id === $student->id, 404);
        $service->closeHoliday($holiday, (int) $request->user('web')->id);

        return back()->with('success', 'Unavailability cancelled.');
    }

    public function waitlistIndex(Request $request): View
    {
        $values = $request->validate(['status' => ['nullable', 'in:all,open,contacted,closed,withdrawn']]);
        $status = $values['status'] ?? 'open';
        $query = BookingWaitlist::query()->with(['student', 'sessionType'])->orderBy('date_from')->orderBy('id');
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if (in_array($status, ['open', 'contacted'], true)) {
            $query->whereHas('student', fn ($students) => $students->where('operational_status', 'active'));
        }

        return view('admin.scheduling.waitlist', ['interests' => $query->paginate(25)->withQueryString(), 'status' => $status]);
    }

    public function waitlistUpdate(Request $request, BookingWaitlist $interest, StudentSchedulingService $service): RedirectResponse
    {
        $values = $request->validate(['status' => ['required', 'in:open,contacted,closed,withdrawn']]);
        $service->waitlistStatus($interest, $values['status'], (int) $request->user('web')->id);

        return back()->with('success', 'Waitlist interest updated. No booking or credit change was made.');
    }
}
