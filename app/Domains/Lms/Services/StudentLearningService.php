<?php

namespace App\Domains\Lms\Services;

use App\Domains\Lms\Models\AssignmentSubmission;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Enrollment;
use App\Domains\Lms\Models\LearningAssignment;
use App\Domains\Lms\Models\LearningVisit;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\LessonBookmark;
use App\Domains\Lms\Models\LessonNote;
use App\Domains\Lms\Models\LessonProgress;
use App\Domains\Lms\Models\QuizAttempt;
use App\Domains\Lms\Models\Section;
use App\Domains\Students\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StudentLearningService
{
    public function __construct(private LmsAccessService $access, private LmsContentService $content, private LmsProgressService $progress, private LmsLearningGate $gate) {}

    /** @return array<string,mixed> */
    public function hub(Student $student): array
    {
        $overview = $this->overview($student);
        $courses = collect($overview['outlines'])->pluck('course');
        $visits = LearningVisit::query()->where('student_id', $student->id)->whereIn('course_id', $courses->pluck('id'))->orderByDesc('accessed_at')->orderByDesc('id')->get()->keyBy('course_id');
        $cards = $courses->map(function (Course $course) use ($overview, $visits): array {
            return ['course' => $course, 'visited' => $visits->has($course->id), 'url' => route('student.learning.courses.show', $course),
                'progress' => $overview['progress'][$course->id]];
        });
        $continue = $visits->filter(fn (LearningVisit $visit): bool => $cards->firstWhere('course.id', $visit->course_id)['progress']['status'] !== 'Completed')->take(6)->map(function (LearningVisit $visit) use ($overview, $courses, $cards): array {
            $course = $courses->firstWhere('id', $visit->course_id);
            $outline = $overview['outlines'][$course->id];
            $lessons = $outline ? $outline['sections']->flatMap(fn (Section $section) => $section->lessons) : collect();
            $lesson = $lessons->firstWhere('id', $visit->lesson_id);
            $progress = $cards->firstWhere('course.id', $visit->course_id)['progress'];
            if ($lesson && (! $progress['lessons'][$lesson->id]['allowed'] || $progress['lessons'][$lesson->id]['completed'])) {
                $lesson = $progress['next'];
            }

            return ['course' => $course, 'lesson' => $lesson, 'accessed_at' => $visit->accessed_at,
                'url' => $lesson ? route('student.learning.lessons.show', [$course, $lesson]) : route('student.learning.courses.show', $course)];
        })->values();
        $assignments = LearningAssignment::query()->where('status', 'assigned')->whereHas('accessGrant', fn ($query) => $query->where('student_id', $student->id))
            ->with(['accessGrant', 'booking:id,student_id'])->orderByDesc('id')->get()
            ->filter(fn (LearningAssignment $assignment): bool => $this->access->assignmentInOutline($student, $assignment, $overview['outlines'], $overview['states']))->values();
        foreach ($assignments as $assignment) {
            $assignment->accessGrant->setRelation('course', $overview['outlines'][$assignment->accessGrant->course_id]['course']);
        }

        return ['cards' => $cards, 'completedCards' => $cards->filter(fn (array $card): bool => $card['progress']['status'] === 'Completed'), 'continueLearning' => $continue, 'learningAssignments' => $assignments,
            'accessEndings' => $this->access->accessEndings($student, $courses),
            'privateNoteCount' => LessonNote::query()->where('student_id', $student->id)->count()];
    }

    /** One fresh, read-only curriculum/evidence projection reused within a page; no entitlement cache.
     * @return array<string,mixed> */
    public function overview(Student $student): array
    {
        $projection = $this->access->studentOutlines($student);
        $rows = LessonProgress::query()->where('student_id', $student->id)->whereIn('lesson_id', $projection['canonical']->pluck('id'))->get()->toBase();
        $progress = [];
        $states = [];
        foreach ($projection['outlines'] as $id => $outline) {
            $canonical = $projection['canonical']->where('course_id', $id)->values();
            $visible = $outline['sections']->flatMap(fn (Section $section) => $section->lessons);
            $summary = $this->progress->summary($student, $canonical, $rows, $projection['anchors']);
            $summary['lessons'] = $summary['lessons']->only($visible->pluck('id')->all());
            $summary['next'] = $visible->first(fn (Lesson $lesson): bool => $summary['lessons'][$lesson->id]['allowed'] && ! $summary['lessons'][$lesson->id]['completed']);
            $progress[$id] = $summary;
            $states += $summary['lessons']->all();
        }

        return $projection + ['progress' => $progress, 'states' => $states];
    }

    /** @return array<string,mixed>|null */
    public function course(Student $student, Course $course): ?array
    {
        $outline = $this->access->outline($student, $course);
        if (! $outline) {
            return null;
        }
        $course = $outline['course'];
        $lessons = $outline['sections']->flatMap(fn (Section $section) => $section->lessons)->values();

        return $outline + $this->progressData($student, $course, $lessons) + ['lessonList' => $lessons,
            'accessEndsAt' => $this->access->accessEndings($student, collect([$course]))[$course->id]];
    }

    /** Full published required curriculum is the denominator, including lessons outside a scoped grant.
     * @param Collection<int,Lesson> $visible
     * @return array<string,mixed> */
    private function progressData(Student $student, Course $course, Collection $visible): array
    {
        $canonical = Lesson::query()->where('course_id', $course->id)->where('status', 'published')->where('published_at', '<=', now('UTC'))
            ->whereHas('section', fn ($query) => $query->where('status', 'published')->where('published_at', '<=', now('UTC')))
            ->with(['section', 'blocks.videoAsset'])->orderBy('sort_order')->orderBy('id')->get();
        $rows = LessonProgress::query()->where('student_id', $student->id)->whereIn('lesson_id', $canonical->pluck('id'))->get()->toBase();
        $anchors = Enrollment::query()->where('student_id', $student->id)->where('course_id', $course->id)->get()->groupBy('course_id')
            ->filter(fn (Collection $group): bool => $group->contains('status', 'enrolled'))->map(fn (Collection $group) => $group->sortBy('enrolled_at')->first()->enrolled_at)->all();
        $summary = $this->progress->summary($student, $canonical, $rows, $anchors);
        $summary['lessons'] = $summary['lessons']->only($visible->pluck('id')->all());
        $summary['next'] = $visible->first(fn (Lesson $lesson): bool => $summary['lessons'][$lesson->id]['allowed'] && ! $summary['lessons'][$lesson->id]['completed']);

        return ['progress' => $summary, 'moduleProgress' => $course->sections->mapWithKeys(fn (Section $section): array => [$section->id => $this->progress->summary($student, $canonical->where('section_id', $section->id), $rows, $anchors)])];
    }

    /** @return array<string,mixed>|null */
    public function lesson(Student $student, Course $course, Lesson $lesson): ?array
    {
        if ((int) $lesson->course_id !== (int) $course->id) {
            return null;
        }
        $data = $this->course($student, $course);
        $current = $data ? $data['lessonList']->firstWhere('id', $lesson->id) : null;
        if (! $current || ! $this->gate->decision($student, $current)['allowed']) {
            return null;
        }
        $data['accessEndsAt'] = $this->access->accessEndings($student, collect([$data['course']]), $current)[$course->id];
        $blocks = $this->access->lessonBlocks($student, $current);
        $assetIds = $blocks->pluck('asset_id')->filter()->map(fn ($id): int => (int) $id)->values()->all();
        $rendered = $blocks->map(fn (LessonBlock $block): array => $this->renderBlock($data['course'], $current, $block, $assetIds));
        $attempts = QuizAttempt::query()->where('student_id', $student->id)->whereIn('block_id', $blocks->pluck('id'))->where('status', '!=', 'erased')->orderByDesc('number')->get()->groupBy('block_id');
        $submissions = AssignmentSubmission::query()->where('student_id', $student->id)->whereIn('block_id', $blocks->pluck('id'))->where('status', '!=', 'erased')->orderByDesc('number')->get()->groupBy('block_id');
        $rendered = $rendered->map(function (array $item) use ($blocks, $attempts, $submissions): array {
            if (! in_array($item['kind'], ['quiz', 'assignment'], true)) {
                return $item;
            }
            $hash = app(LmsLearningDefinition::class)->hash($blocks->firstWhere('id', $item['id'])->payload);
            $row = ($item['kind'] === 'quiz' ? $attempts : $submissions)->get($item['id'], collect())->firstWhere('definition_hash', $hash);
            $state = match ($row?->status) {
                'started' => 'In Progress', 'pending_review' => 'Awaiting manual review', 'graded' => $row instanceof QuizAttempt && $row->passed ? 'Passed' : 'Graded — not passed',
                'submitted' => 'Submitted', 'under_review' => 'Under Review', 'needs_revision' => 'Needs Revision', 'approved' => 'Approved',
                default => $item['kind'] === 'quiz' ? 'Not Started' : 'Not Submitted',
            };

            return $item + ['state' => $state];
        });
        $bookmarkableIds = $rendered->filter(fn (array $block): bool => $block['kind'] === 'video' || ($block['kind'] === 'external_video' && ($block['payload']['provider'] ?? null) === 'direct'))->pluck('id');
        $index = $data['lessonList']->search(fn (Lesson $item): bool => (int) $item->id === (int) $current->id);

        return $data + ['lesson' => $current, 'blocks' => $rendered,
            'hasBunnyPlaceholder' => $current->blocks()->where('kind', 'bunny_video')->where('status', 'placeholder')->exists(),
            'previousLesson' => $data['lessonList']->take($index)->reverse()->first(fn (Lesson $item): bool => $data['progress']['lessons'][$item->id]['allowed']),
            'nextLesson' => $data['lessonList']->slice($index + 1)->first(fn (Lesson $item): bool => $data['progress']['lessons'][$item->id]['allowed']),
            'notes' => LessonNote::query()->where('student_id', $student->id)->where('lesson_id', $current->id)->orderByDesc('id')->get(),
            'bookmarks' => LessonBookmark::query()->where('student_id', $student->id)->where('lesson_id', $current->id)->whereIn('block_id', $bookmarkableIds)->orderBy('block_id')->orderBy('position_milliseconds')->get()];
    }

    /** @param list<int> $assetIds
     * @return array<string,mixed>
     */
    private function renderBlock(Course $course, Lesson $lesson, LessonBlock $block, array $assetIds): array
    {
        try {
            if (in_array($block->kind, ['quiz', 'assignment'], true)) {
                return ['id' => $block->id, 'kind' => $block->kind, 'title' => $block->payload['title'],
                    'url' => route('student.evidence.show', [$course, $lesson, $block])];
            }
            if ($block->kind === 'video') {
                $video = $block->videoAsset;
                if ($video && $video->provider !== 'bunny') {
                    return ['id' => $block->id] + $this->content->normalize($course, ['kind' => $video->provider === 'youtube' ? 'youtube_video' : 'external_video', 'url' => $video->external_url]);
                }

                return ['id' => $block->id, 'kind' => 'video', 'label' => $video ? $video->label : 'Protected video',
                    'authorizationUrl' => route('student.video.authorize', [$course, $lesson, $block])];
            }
            $data = $this->content->normalize($course, $this->content->input($block->only(['kind', 'resource_id', 'asset_id', 'payload'])), $assetIds);

            return ['id' => $block->id, 'url' => route('student.learning.materials.open', [$course, $lesson, $block]), 'resourceTitle' => $block->resource?->title] + $data;
        } catch (ValidationException) {
            return ['id' => $block->id, 'kind' => 'unavailable'];
        }
    }
}
