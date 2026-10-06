<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Booking\Models\RecurringLessonPlan;
use App\Domains\Booking\Services\RecurringLessonService;
use App\Domains\Students\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecurringLessonController extends Controller
{
    public function index(): View
    {
        return view('admin.scheduling.recurring', ['plans' => RecurringLessonPlan::query()->with(['student', 'sessionType'])->orderByDesc('id')->paginate(20)]);
    }

    public function store(Request $request, Student $student, RecurringLessonService $service): RedirectResponse
    {
        $service->create($student, $request->only(['session_type_id', 'cadence', 'start_date', 'end_date', 'occurrence_count', 'preferred_time', 'timezone', 'fold_policy', 'idempotency_key']), (int) $request->user('web')->id);

        return back()->with('success', 'Recurring plan saved. Generate a bounded batch when ready.');
    }

    public function generate(Request $request, RecurringLessonPlan $plan, RecurringLessonService $service): RedirectResponse
    {
        $values = $request->validate(['from_sequence' => ['required', 'integer', 'between:1,104'], 'count' => ['required', 'integer', 'between:1,12']]);
        $results = $service->generate($plan, (int) $request->user('web')->id, (int) $values['from_sequence'], (int) $values['count']);
        $booked = collect($results)->where('status', 'booked')->count();

        return back()->with('success', $booked.' confirmed occurrence(s); '.(count($results) - $booked).' blocked. Review each occurrence on the student scheduling page.');
    }

    public function update(Request $request, RecurringLessonPlan $plan, RecurringLessonService $service): RedirectResponse
    {
        $values = $request->validate(['status' => ['required', 'in:active,paused,completed']]);
        $service->status($plan, $values['status'], (int) $request->user('web')->id);

        return back()->with('success', 'Plan status updated. Existing bookings remain scheduled.');
    }
}
