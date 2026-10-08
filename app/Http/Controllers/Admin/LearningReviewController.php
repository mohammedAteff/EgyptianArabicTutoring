<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Services\LessonMaterialService;
use App\Domains\Lms\Models\AssignmentSubmission;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\QuizAttempt;
use App\Domains\Lms\Services\LmsAssignmentService;
use App\Domains\Lms\Services\LmsQuizService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class LearningReviewController extends Controller
{
    private function actor(Request $request): Administrator
    {
        $actor = $request->user();
        abort_unless($actor instanceof Administrator, 403);
        Gate::forUser($actor)->authorize('manage', Course::class);

        return $actor;
    }

    public function index(Request $request, Course $course): Response
    {
        $this->actor($request);
        $blockIds = LessonBlock::query()->whereHas('lesson', fn ($query) => $query->where('course_id', $course->id))->select('id');

        return response()->view('admin.lms.reviews.index', ['title' => 'Learning reviews', 'course' => $course,
            'attempts' => QuizAttempt::query()->whereIn('block_id', $blockIds)->where('status', 'pending_review')->orderBy('id')->paginate(20, ['*'], 'quizzes'),
            'submissions' => AssignmentSubmission::query()->whereIn('block_id', $blockIds)->whereIn('status', ['submitted', 'under_review'])->orderBy('id')->paginate(20, ['*'], 'assignments')], 200,
            ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function quiz(Request $request, int $attempt, LmsQuizService $quizzes): RedirectResponse
    {
        $v = $request->validate(['version' => ['required', 'integer', 'min:1'], 'marks' => ['required', 'array', 'max:100'], 'marks.*' => ['required', 'integer', 'between:0,100'], 'feedback' => ['nullable', 'string', 'max:5000']]);
        $marks = array_map(fn ($mark): int => (int) $mark, $v['marks']);
        $row = $quizzes->review($this->actor($request), $attempt, (int) $v['version'], $marks, $v['feedback'] ?? null);

        return to_route('admin.lms.reviews.index', LessonBlock::query()->findOrFail($row->block_id)->lesson->course_id)->with('success', 'Quiz reviewed.');
    }

    public function assignment(Request $request, int $submission, LmsAssignmentService $assignments): RedirectResponse
    {
        $v = $request->validate(['version' => ['required', 'integer', 'min:1'], 'status' => ['required', 'string'], 'feedback' => ['nullable', 'string', 'max:5000']]);
        $row = $assignments->review($this->actor($request), $submission, (int) $v['version'], $v['status'], $v['feedback'] ?? null);

        return to_route('admin.lms.reviews.index', LessonBlock::query()->findOrFail($row->block_id)->lesson->course_id)->with('success', 'Assignment review saved.');
    }

    public function file(Request $request, int $submission, LessonMaterialService $files): Response
    {
        $this->actor($request);
        $row = AssignmentSubmission::query()->where('status', '!=', 'erased')->findOrFail($submission);
        abort_unless($row->path && $row->sha256, 404);

        return $files->openLearningSubmission($row->path, $row->sha256);
    }
}
