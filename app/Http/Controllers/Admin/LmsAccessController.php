<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\LmsAccessService;
use App\Domains\Students\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LmsAccessController extends Controller
{
    public function issue(Request $request, Student $student, Course $course, LmsAccessOperations $operations): JsonResponse
    {
        $actor = $this->actor($request);
        Gate::forUser($actor)->authorize('manage', $course);
        $values = $request->validate(['operation' => ['required', Rule::in(['grant', 'enroll', 'assign'])],
            'operation_key' => ['required', 'string'], 'section_id' => ['nullable', 'integer', 'min:1'], 'lesson_id' => ['nullable', 'integer', 'min:1']]);
        abort_if(! empty($values['section_id']) && ! empty($values['lesson_id']), 422);
        $target = ! empty($values['lesson_id']) ? Lesson::query()->where('course_id', $course->id)->findOrFail($values['lesson_id'])
            : (! empty($values['section_id']) ? Section::query()->where('course_id', $course->id)->findOrFail($values['section_id']) : $course);
        abort_if($values['operation'] === 'enroll' && ! ($target instanceof Course), 422);
        $result = match ($values['operation']) {
            'grant' => $operations->grant($actor, $student, $target, $request->all(), $values['operation_key']),
            'enroll' => $operations->enroll($actor, $student, $course, $request->all(), $values['operation_key']),
            'assign' => $operations->assign($actor, $student, $target, $request->all(), $values['operation_key']),
            default => abort(422),
        };

        return $this->json(['id' => $result->id, 'operation' => $values['operation']]);
    }

    public function change(Request $request, AccessGrant $grant, LmsAccessOperations $operations): JsonResponse
    {
        $actor = $this->actor($request);
        Gate::forUser($actor)->authorize('manage', $grant);
        $values = $request->validate(['action' => ['required', Rule::in(['revoke', 'extend', 'set_expiration', 'remove_expiration', 'set_start', 'regrant'])],
            'operation_key' => ['required', 'string'], 'expected_version' => ['required_unless:action,regrant', 'integer', 'min:1']]);
        $result = $values['action'] === 'regrant' ? $operations->regrant($actor, $grant, $request->all(), $values['operation_key'])
            : $operations->change($actor, $grant, $values['action'], $request->all(), (int) $values['expected_version'], $values['operation_key']);

        return $this->json(['id' => $result->id, 'status' => $result->status, 'lock_version' => $result->lock_version]);
    }

    public function studentAccess(Request $request, Student $student, LmsAccessService $access): JsonResponse
    {
        Gate::forUser($this->actor($request))->authorize('manage', Course::class);

        return $this->json(['courses' => $access->activeForStudent($student)->map(fn (Course $course) => $course->only(['id', 'title']))]);
    }

    public function courseStudents(Request $request, Course $course, LmsAccessService $access): JsonResponse
    {
        Gate::forUser($this->actor($request))->authorize('manage', $course);

        return $this->json(['student_ids' => $access->studentsWithCourseAccess($course)->map(fn (Student $student): int => $student->id)->values()]);
    }

    private function actor(Request $request): Administrator
    {
        $actor = $request->user('web');
        abort_unless($actor instanceof Administrator, 403);

        return $actor;
    }

    /** @param array<string, mixed> $data */
    private function json(array $data): JsonResponse
    {
        return response()->json($data, 200, ['Cache-Control' => 'private, no-store']);
    }
}
