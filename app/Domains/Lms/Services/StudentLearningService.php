<?php

namespace App\Domains\Lms\Services;

use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\LearningAssignment;
use App\Domains\Lms\Models\LearningVisit;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\LessonBookmark;
use App\Domains\Lms\Models\LessonNote;
use App\Domains\Lms\Models\Section;
use App\Domains\Students\Models\Student;
use Illuminate\Validation\ValidationException;

class StudentLearningService
{
    public function __construct(private LmsAccessService $access, private LmsContentService $content) {}

    /** @return array<string,mixed> */
    public function hub(Student $student): array
    {
        $courses = $this->access->activeForStudent($student);
        $visits = LearningVisit::query()->where('student_id', $student->id)->whereIn('course_id', $courses->pluck('id'))->orderByDesc('accessed_at')->orderByDesc('id')->get()->keyBy('course_id');
        $cards = $courses->map(fn (Course $course): array => ['course' => $course, 'visited' => $visits->has($course->id), 'url' => route('student.learning.courses.show', $course)]);
        $continue = $visits->take(6)->map(function (LearningVisit $visit) use ($student, $courses): array {
            $course = $courses->firstWhere('id', $visit->course_id);
            $outline = $this->access->outline($student, $course);
            $lessons = $outline ? $outline['sections']->flatMap(fn (Section $section) => $section->lessons) : collect();
            $lesson = $lessons->firstWhere('id', $visit->lesson_id);

            return ['course' => $course, 'lesson' => $lesson, 'accessed_at' => $visit->accessed_at,
                'url' => $lesson ? route('student.learning.lessons.show', [$course, $lesson]) : route('student.learning.courses.show', $course)];
        })->values();
        $assignments = LearningAssignment::query()->where('status', 'assigned')->whereHas('accessGrant', fn ($query) => $query->where('student_id', $student->id))
            ->with(['accessGrant.course', 'accessGrant.lesson', 'accessGrant.section', 'booking:id,student_id'])->orderByDesc('id')->get()
            ->filter(fn (LearningAssignment $assignment): bool => $this->access->canAccess($student, $assignment))->values();

        return ['cards' => $cards, 'continueLearning' => $continue, 'learningAssignments' => $assignments,
            'accessEndings' => $this->access->accessEndings($student, $courses),
            'privateNoteCount' => LessonNote::query()->where('student_id', $student->id)->count()];
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

        return $outline + ['lessonList' => $lessons, 'accessEndsAt' => $this->access->accessEndings($student, collect([$course]))[$course->id]];
    }

    /** @return array<string,mixed>|null */
    public function lesson(Student $student, Course $course, Lesson $lesson): ?array
    {
        if ((int) $lesson->course_id !== (int) $course->id) {
            return null;
        }
        $data = $this->course($student, $course);
        $current = $data ? $data['lessonList']->firstWhere('id', $lesson->id) : null;
        if (! $current) {
            return null;
        }
        $data['accessEndsAt'] = $this->access->accessEndings($student, collect([$data['course']]), $current)[$course->id];
        $blocks = $this->access->lessonBlocks($student, $current);
        $assetIds = $blocks->pluck('asset_id')->filter()->map(fn ($id): int => (int) $id)->values()->all();
        $rendered = $blocks->map(fn (LessonBlock $block): array => $this->renderBlock($data['course'], $current, $block, $assetIds));
        $bookmarkableIds = $rendered->filter(fn (array $block): bool => $block['kind'] === 'video' || ($block['kind'] === 'external_video' && ($block['payload']['provider'] ?? null) === 'direct'))->pluck('id');
        $index = $data['lessonList']->search(fn (Lesson $item): bool => (int) $item->id === (int) $current->id);

        return $data + ['lesson' => $current, 'blocks' => $rendered,
            'hasBunnyPlaceholder' => $current->blocks()->where('kind', 'bunny_video')->where('status', 'placeholder')->exists(),
            'previousLesson' => $data['lessonList']->get($index - 1), 'nextLesson' => $data['lessonList']->get($index + 1),
            'notes' => LessonNote::query()->where('student_id', $student->id)->where('lesson_id', $current->id)->orderByDesc('id')->get(),
            'bookmarks' => LessonBookmark::query()->where('student_id', $student->id)->where('lesson_id', $current->id)->whereIn('block_id', $bookmarkableIds)->orderBy('block_id')->orderBy('position_milliseconds')->get()];
    }

    /** @param list<int> $assetIds
     * @return array<string,mixed>
     */
    private function renderBlock(Course $course, Lesson $lesson, LessonBlock $block, array $assetIds): array
    {
        try {
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
