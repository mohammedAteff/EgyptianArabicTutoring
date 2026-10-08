<?php

namespace App\Http\Controllers\Student;

use App\Domains\Booking\Services\LessonMaterialService;
use App\Domains\Lms\Models\AssignmentSubmission;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\QuizAttempt;
use App\Domains\Lms\Services\LmsAccessService;
use App\Domains\Lms\Services\LmsAssignmentService;
use App\Domains\Lms\Services\LmsLearningDefinition;
use App\Domains\Lms\Services\LmsLearningGate;
use App\Domains\Lms\Services\LmsProgressService;
use App\Domains\Lms\Services\LmsQuizService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentPortalService;
use App\Domains\Students\Services\StudentSessionContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class LearningEvidenceController extends Controller
{
    public function __construct(private StudentSessionContext $students, private LmsProgressService $progress,
        private LmsQuizService $quizzes, private LmsAssignmentService $assignments, private LmsAccessService $access, private LmsLearningGate $gate) {}

    private function student(Request $request): Student
    {
        $student = $this->students->current($request);
        abort_unless($student !== null, 404);

        return $student;
    }

    public function manual(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        $this->progress->manual($this->student($request), $course, $lesson);

        return to_route('student.learning.lessons.show', [$course, $lesson])->with('status', 'Your completion requirement was saved.');
    }

    public function show(Request $request, Course $course, Lesson $lesson, LessonBlock $block, StudentPortalService $portal): Response
    {
        $student = $this->student($request);
        abort_unless($lesson->course_id === $course->id && $block->lesson_id === $lesson->id && in_array($block->kind, ['quiz', 'assignment'], true)
            && $this->access->canAccess($student, $block) && $this->gate->decision($student, $lesson)['allowed'], 404);
        $attempts = QuizAttempt::query()->where('student_id', $student->id)->where('block_id', $block->id)->where('status', '!=', 'erased')->orderByDesc('id')->get();
        $submissions = AssignmentSubmission::query()->where('student_id', $student->id)->where('block_id', $block->id)->where('status', '!=', 'erased')->orderByDesc('id')->get();
        $hash = app(LmsLearningDefinition::class)->hash($block->payload);
        $currentAttempts = $attempts->where('definition_hash', $hash);
        $currentSubmission = $submissions->firstWhere('definition_hash', $hash);

        return response()->view('student.learning.assessment', ['course' => $course, 'lesson' => $lesson, 'block' => $block,
            'attempts' => $attempts->map($this->quizzes->project(...)), 'submissions' => $submissions,
            'canStart' => $currentAttempts->contains('status', 'started') || $currentAttempts->count() < ($block->payload['attempt_limit'] ?? 0),
            'currentSubmission' => $currentSubmission, 'canSubmit' => ! $currentSubmission || $currentSubmission->status === 'needs_revision'] + $portal->data($request), 200,
            ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    public function beginQuiz(Request $request, Course $course, Lesson $lesson, LessonBlock $block): RedirectResponse
    {
        $v = $request->validate(['request_key' => ['required', 'uuid']]);
        $this->quizzes->begin($this->student($request), $course, $lesson, $block, $v['request_key']);

        return to_route('student.evidence.show', [$course, $lesson, $block]);
    }

    public function submitQuiz(Request $request, Course $course, Lesson $lesson, LessonBlock $block, int $attempt): RedirectResponse
    {
        $v = $request->validate(['answers' => ['required', 'array', 'max:100']]);
        $this->quizzes->submit($this->student($request), $course, $lesson, $block, $attempt, $v['answers']);

        return to_route('student.evidence.show', [$course, $lesson, $block])->with('status', 'Quiz submitted.');
    }

    public function submitAssignment(Request $request, Course $course, Lesson $lesson, LessonBlock $block): RedirectResponse
    {
        $file = $request->file('file');
        abort_unless($file === null || $file instanceof UploadedFile, 422);
        $this->assignments->submit($this->student($request), $course, $lesson, $block, $request->only(['request_key', 'kind', 'body', 'url']), $file);

        return to_route('student.evidence.show', [$course, $lesson, $block])->with('status', 'Assignment submitted.');
    }

    public function file(Request $request, int $submission, LessonMaterialService $files): Response
    {
        $owned = AssignmentSubmission::query()->where('student_id', $this->student($request)->id)->where('status', '!=', 'erased')->findOrFail($submission);
        abort_unless($owned->path && $owned->sha256, 404);

        return $files->openLearningSubmission($owned->path, $owned->sha256);
    }

    public function beginWatch(Request $request, Course $course, Lesson $lesson, LessonBlock $block): JsonResponse
    {
        return $this->json($this->progress->beginWatch($request, $this->student($request), $course, $lesson, $block));
    }

    public function sampleWatch(Request $request, Course $course, Lesson $lesson, LessonBlock $block, int $watch): JsonResponse
    {
        return $this->json($this->progress->sampleWatch($request, $this->student($request), $course, $lesson, $block, $watch));
    }

    /** @param array<string,mixed> $data */
    private function json(array $data): JsonResponse
    {
        return response()->json($data, 200, ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow']);
    }
}
