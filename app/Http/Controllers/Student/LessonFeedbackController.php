<?php

namespace App\Http\Controllers\Student;

use App\Domains\Students\Models\LessonFeedback;
use App\Domains\Students\Services\TeachingRecordService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LessonFeedbackController extends Controller
{
    public function store(Request $request, int $booking, TeachingRecordService $records): RedirectResponse
    {
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:2000']]);
        DB::transaction(function () use ($request, $booking, $records, $data): void {
            $student = $records->lockStudent($request->attributes->get('student')->id);
            $lesson = $records->lockBooking($student, $booking);
            abort_unless($lesson && $lesson->status === 'completed', 404);
            LessonFeedback::query()->updateOrCreate(['booking_id' => $lesson->id], $data + ['student_id' => $student->id]);
        }, 3);

        return to_route('student.dashboard')->with('success', 'Private lesson feedback saved. Thank you.');
    }
}
