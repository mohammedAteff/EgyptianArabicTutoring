<?php

namespace App\Http\Controllers\Student;

use App\Domains\Booking\Services\LessonMaterialService;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\HomeworkSubmission;
use App\Domains\Students\Services\StudentTeachingReadModel;
use App\Domains\Students\Services\TeachingRecordService;
use App\Http\Controllers\Controller;
use App\Rules\SafeLessonUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class HomeworkController extends Controller
{
    public function update(Request $request, int $homework, TeachingRecordService $records, StudentTeachingReadModel $read): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['in_progress', 'submitted'])], 'student_response' => ['nullable', 'string', 'max:5000'], 'submission_key' => ['nullable', 'uuid']]);
        DB::transaction(function () use ($request, $homework, $records, $read, $data): void {
            $student = $records->lockStudent($request->attributes->get('student')->id);
            $item = $read->shared(Homework::query(), $student)->lockForUpdate()->findOrFail($homework);
            abort_if($item->status === 'completed', 409, 'Completed homework is locked.');
            if ($item->submissions()->doesntExist() && filled($item->student_response) && in_array($item->status, ['submitted', 'reviewed'], true)) {
                HomeworkSubmission::query()->create(['homework_id' => $item->id, 'body' => $item->student_response, 'submission_key' => Str::uuid()->toString(), 'submitted_at' => $item->getAttribute('updated_at')]);
            }
            if ($data['status'] === 'submitted') {
                $key = $data['submission_key'] ?? Str::uuid()->toString();
                $existing = HomeworkSubmission::query()->where('submission_key', $key)->first();
                if ($existing) {
                    if ((int) $existing->homework_id !== (int) $item->id || (string) $existing->body !== (string) ($data['student_response'] ?? '')) {
                        throw ValidationException::withMessages(['student_response' => 'This submission key was already used. Reload the form and try again.']);
                    }

                    return;
                }
                HomeworkSubmission::query()->create(['homework_id' => $item->id, 'body' => $data['student_response'] ?? null, 'submission_key' => $key, 'submitted_at' => now('UTC')]);
            }
            unset($data['submission_key']);
            $item->update($data);
        }, 3);

        return to_route('student.teaching.index')->with('success', 'Homework progress saved.');
    }

    public function resource(Request $request, int $homework, StudentTeachingReadModel $read, LessonMaterialService $materials): Response
    {
        $student = $request->attributes->get('student');
        $item = $read->shared(Homework::query(), $student)->with('resource')->findOrFail($homework);
        abort_unless($item->resource && $item->resource->isPublished(), 404);

        return $materials->openResource($item->resource);
    }

    public function link(Request $request, int $homework, StudentTeachingReadModel $read): Response
    {
        $item = $read->shared(Homework::query(), $request->attributes->get('student'))->findOrFail($homework);
        abort_unless(is_string($item->url) && SafeLessonUrl::isSafe($item->url), 404);

        return redirect()->away($item->url, 302, ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }
}
