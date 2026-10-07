<?php

namespace App\Http\Controllers\Student;

use App\Domains\Booking\Services\LessonMaterialService;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\LearningAssignment;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\LessonBookmark;
use App\Domains\Lms\Models\LessonNote;
use App\Domains\Lms\Services\LmsAccessService;
use App\Domains\Lms\Services\StudentLearningService;
use App\Domains\Lms\Services\StudentLearningStateService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentPortalService;
use App\Domains\Students\Services\StudentSessionContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LearningController extends Controller
{
    public function __construct(private StudentSessionContext $students, private StudentPortalService $portal, private StudentLearningService $learning, private StudentLearningStateService $state, private LmsAccessService $access) {}

    public function index(Request $request): Response
    {
        return $this->page($request, 'student.learning.index', $this->learning->hub($this->student($request)));
    }

    public function course(Request $request, Course $course): Response
    {
        $student = $this->student($request);
        $data = $this->learning->course($student, $course);
        if (! $data) {
            return $this->unavailable($request, $student, $course);
        }
        $this->state->visit($student, $data['course']);

        return $this->page($request, 'student.learning.course', $data);
    }

    public function lesson(Request $request, Course $course, Lesson $lesson): Response
    {
        abort_unless((int) $lesson->course_id === (int) $course->id, 404);
        $student = $this->student($request);
        $data = $this->learning->lesson($student, $course, $lesson);
        if (! $data) {
            return $this->unavailable($request, $student, $course, $lesson);
        }
        $this->state->visit($student, $data['course'], $data['lesson']);

        $response = $this->page($request, 'student.learning.lesson', $data);
        if ($data['blocks']->contains('kind', 'video')) {
            $response->headers->set('Referrer-Policy', 'strict-origin');
        }

        return $response;
    }

    public function assignment(Request $request, int $assignment): Response
    {
        $student = $this->student($request);
        $assignment = LearningAssignment::query()->whereHas('accessGrant', fn ($query) => $query->where('student_id', $student->id))->findOrFail($assignment);
        abort_unless($this->access->canAccess($student, $assignment), 404);
        $course = $assignment->accessGrant->course;
        $data = $this->learning->course($student, $course);
        abort_unless($data !== null, 404);
        $this->state->visit($student, $course);

        return $this->page($request, 'student.learning.course', ['learningAssignment' => $assignment] + $data);
    }

    public function material(Request $request, Course $course, Lesson $lesson, LessonBlock $block, LessonMaterialService $files): Response
    {
        abort_unless((int) $lesson->course_id === (int) $course->id && (int) $block->lesson_id === (int) $lesson->id, 404);
        abort_unless($this->access->canAccess($this->student($request), $block), 404);
        if (in_array($block->kind, ['resource', 'file'], true) && $block->resource) {
            return $files->openResource($block->resource);
        }
        abort_unless(in_array($block->kind, ['image', 'file'], true) && $block->asset !== null, 404);

        return $files->openCourseAsset($block->asset);
    }

    public function notes(Request $request): Response
    {
        $student = $this->student($request);
        $notes = LessonNote::query()->where('student_id', $student->id)->orderByDesc('id')->paginate(20);

        return $this->page($request, 'student.learning.notes', ['notes' => $notes]);
    }

    public function storeNote(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        $values = $request->validate(['body' => ['required', 'string', 'max:10000']]);
        $this->state->saveNote($this->student($request), $course, $lesson, $values['body']);

        return redirect()->route('student.learning.lessons.show', [$course, $lesson])->with('status', 'Private note saved.');
    }

    public function updateNote(Request $request, Course $course, Lesson $lesson, int $note): RedirectResponse
    {
        $values = $request->validate(['body' => ['required', 'string', 'max:10000'], 'version' => ['required', 'integer', 'min:1']]);
        $this->state->saveNote($this->student($request), $course, $lesson, $values['body'], $note, (int) $values['version']);

        return redirect()->route('student.learning.lessons.show', [$course, $lesson])->with('status', 'Private note updated.');
    }

    public function deleteNote(Request $request, int $note): RedirectResponse
    {
        $values = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $this->state->deleteNote($this->student($request), $note, (int) $values['version']);

        return redirect()->route('student.learning.notes.index')->with('status', 'Private note deleted.');
    }

    public function storeBookmark(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        $values = $request->validate(['block_id' => ['required', 'integer', 'min:1'], 'seconds' => ['required', 'numeric', 'between:0,86400'], 'label' => ['nullable', 'string', 'max:500']]);
        $this->state->bookmark($this->student($request), $course, $lesson, (int) $values['block_id'], (float) $values['seconds'], $values['label'] ?? null);

        return redirect()->route('student.learning.lessons.show', [$course, $lesson])->with('status', 'Video bookmark saved.');
    }

    public function deleteBookmark(Request $request, Course $course, Lesson $lesson, int $bookmark): RedirectResponse
    {
        abort_unless((int) $lesson->course_id === (int) $course->id, 404);
        $student = $this->student($request);
        $owned = LessonBookmark::query()->where('student_id', $student->id)->where('lesson_id', $lesson->id)->findOrFail($bookmark);
        $this->state->deleteBookmark($student, $owned->id);

        return redirect()->route('student.learning.index')->with('status', 'Video bookmark deleted.');
    }

    private function student(Request $request): Student
    {
        $student = $this->students->current($request);
        abort_unless($student !== null, 404);

        return $student;
    }

    private function unavailable(Request $request, Student $student, Course $course, ?Lesson $lesson = null): Response
    {
        return $this->page($request, 'student.learning.unavailable', $this->access->unavailable($student, $course, $lesson), 404);
    }

    /** Canonical Portal reads retain visit-synchronized, deduplicated notification state.
     * @param  array<string,mixed>  $data
     */
    private function page(Request $request, string $view, array $data, int $status = 200): Response
    {
        return response()->view($view, $data + $this->portal->data($request), $status, ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow, noarchive', 'Referrer-Policy' => 'no-referrer']);
    }
}
