<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\LessonMaterial;
use App\Domains\Lms\Services\LmsTeachingTimeline;
use App\Domains\Resources\Models\Resource;
use App\Domains\Students\Models\LessonFeedback;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\TeachingRecordService;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTeachingRecordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class StudentTeachingController extends Controller
{
    public function show(Request $request, Student $student, LmsTeachingTimeline $timeline): Response
    {
        Gate::authorize('manageTeaching', $student);
        $bookingId = $request->integer('booking') ?: null;
        $bookings = Booking::query()->where('student_id', $student->id)->with('sessionType')->orderByDesc('start_at_utc')->get();
        abort_if($bookingId && ! $bookings->contains('id', $bookingId), 404);
        $records = [];
        foreach (TeachingRecordService::MODELS as $kind => $modelClass) {
            if ($kind === 'milestone') {
                continue;
            }
            $query = $modelClass::query()->where('student_id', $student->id);
            if ($kind === 'plan') {
                $query->with('milestones');
            }
            if ($kind === 'resource') {
                $query->with('resource:id,title');
            }
            $records[$kind] = $query->orderByDesc('id')->get();
        }
        $resources = Resource::query()->published()->orderBy('title')->get(['id', 'title']);
        $materials = LessonMaterial::query()->whereIn('booking_id', $bookings->modelKeys())->whereNull('withdrawn_at')->get(['id', 'booking_id', 'title']);
        $feedback = LessonFeedback::query()->where('student_id', $student->id)->with('booking:id,start_at_utc')->orderByDesc('id')->get();

        return response()->view('admin.students.teaching', compact('student', 'bookings', 'bookingId', 'records', 'resources', 'materials', 'feedback') + ['learningTimeline' => $timeline->staff($request->user('web'), $student)])->header('Cache-Control', 'private, no-store');
    }

    public function store(SaveTeachingRecordRequest $request, Student $student, string $kind, TeachingRecordService $records): RedirectResponse
    {
        $records->save($student, $kind, $request->validated(), $request->user()->id);

        return to_route('admin.students.teaching', ['student' => $student, 'booking' => $request->integer('booking_id') ?: null])->with('success', 'Teaching record saved.');
    }
}
