<?php

namespace App\Http\Controllers;

use App\Domains\Booking\Services\LessonMaterialService;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\LearningAssignment;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Services\LmsAccessService;
use App\Domains\Lms\Services\LmsLearningGate;
use App\Domains\Students\Services\StudentSessionContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LmsFoundationController extends Controller
{
    public function __construct(private LmsAccessService $access, private StudentSessionContext $students) {}

    public function course(Request $request, Course $course): JsonResponse
    {
        $student = $this->students->current($request);
        $outline = $this->access->outline($student, $course);
        abort_if($outline === null, 404);
        $sections = $outline['sections']->map(fn (Section $section) => ['id' => $section->id, 'title' => $section->title,
            'lessons' => $section->lessons->map(fn (Lesson $lesson) => $lesson->only(['id', 'title', 'slug']))->values()]);

        return $this->json(['course' => $outline['course']->only(['id', 'title', 'slug']), 'sections' => $sections->values()]);
    }

    public function lesson(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        abort_unless((int) $lesson->course_id === (int) $course->id, 404);
        $student = $this->students->current($request);
        abort_unless($this->access->canAccess($student, $lesson), 404);
        abort_unless(app(LmsLearningGate::class)->decision($student, $lesson)['allowed'], 404);
        $blocks = $this->access->lessonBlocks($student, $lesson)
            ->map(function (LessonBlock $block) use ($student, $course, $lesson): array {
                $data = $block->only(['id', 'kind', 'sort_order']);
                if ($block->kind === 'video') {
                    $data['authorization_url'] = $student ? route('student.video.authorize', [$course, $lesson, $block]) : null;
                } elseif (in_array($block->kind, ['quiz', 'assignment'], true)) {
                    $data['title'] = $block->payload['title'];
                    $data['url'] = $student ? route('student.evidence.show', [$course, $lesson, $block]) : null;
                } elseif ($block->kind === 'resource') {
                    $data['url'] = $student ? route('student.lms.resources.open', [$course, $lesson, $block]) : route('resources.show', $block->resource->slug);
                } else {
                    $data['content'] = $block->payload;
                }

                return $data;
            })->values();

        return $this->json(['lesson' => $lesson->only(['id', 'title', 'slug']), 'blocks' => $blocks]);
    }

    public function assignment(Request $request, LearningAssignment $assignment): JsonResponse
    {
        abort_unless($this->access->canAccess($this->students->current($request), $assignment), 404);

        return $this->json(['assignment' => $assignment->only(['id', 'instructions']), 'target' => $assignment->accessGrant->only(['course_id', 'section_id', 'lesson_id', 'starts_at', 'expires_at'])]);
    }

    public function resource(Request $request, Course $course, Lesson $lesson, LessonBlock $block, LessonMaterialService $files): Response
    {
        abort_unless((int) $lesson->course_id === (int) $course->id && (int) $block->lesson_id === (int) $lesson->id && $block->kind === 'resource', 404);
        $student = $this->students->current($request);
        abort_unless($student && $this->access->canAccess($student, $block), 404);
        abort_unless($block->resource !== null, 404);

        return $files->openResource($block->resource);
    }

    /** @param array<string, mixed> $data */
    private function json(array $data): JsonResponse
    {
        return response()->json($data, 200, ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow', 'Referrer-Policy' => 'no-referrer']);
    }
}
