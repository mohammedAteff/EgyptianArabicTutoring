<?php

namespace App\Domains\Lms\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Lms\Models\AssignmentSubmission;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Enrollment;
use App\Domains\Lms\Models\LearningAssignment;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonProgress;
use App\Domains\Lms\Models\QuizAttempt;
use App\Domains\Students\Models\ResourceAssignment;
use App\Domains\Students\Models\Student;
use App\Policies\StudentTeachingPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class LmsTeachingTimeline
{
    public function __construct(private LmsProgressService $progress, private LmsLearningDefinition $definitions, private StudentLearningService $learning) {}

    /** @return Collection<int,array{key:string,at:CarbonImmutable,label:string,detail:string}> */
    public function staff(Administrator $actor, Student $student): Collection
    {
        Gate::forUser($actor)->authorize('manageTeaching', $student);
        Gate::forUser($actor)->authorize('manage', Course::class);

        return $this->events($student, true);
    }

    /** @return Collection<int,array{key:string,at:CarbonImmutable,label:string,detail:string}> */
    public function student(Student $student): Collection
    {
        abort_unless(app(StudentTeachingPolicy::class)->usePortal($student->fresh() ?? $student), 403);

        return $this->events($student, false);
    }

    /** Read bounded canonical facts; no timeline writer, audit import or security telemetry.
     * @return Collection<int,array{key:string,at:CarbonImmutable,label:string,detail:string}> */
    private function events(Student $student, bool $staff): Collection
    {
        $events = collect();
        $add = function (string $key, mixed $at, string $label, string $detail) use ($events): void {
            if ($at !== null) {
                $events->put($key, ['key' => $key, 'at' => CarbonImmutable::parse($at, 'UTC'), 'label' => $label, 'detail' => $detail]);
            }
        };
        foreach (Booking::query()->where('student_id', $student->id)->where('status', 'completed')->whereNotNull('completed_at')->orderByDesc('completed_at')->limit(20)->get() as $booking) {
            $add('session:'.$booking->id.':delivered', $booking->completed_at, 'Session delivered', 'Tutoring session #'.$booking->id);
        }
        $resources = ResourceAssignment::query()->where('student_id', $student->id)->where('student_visible', true)
            ->whereHas('resource', fn ($query) => $query->published())
            ->where(fn ($query) => $query->whereNull('booking_id')->orWhereHas('booking', fn ($query) => $query->where('student_id', $student->id)))
            ->with('resource:id,title')->orderByDesc('id')->limit(20)->get();
        foreach ($resources as $resource) {
            $add('resource:'.$resource->id.':assigned', $resource->created_at, 'Resource assigned', $resource->resource->title);
            $add('resource:'.$resource->id.':reviewed', $resource->reviewed_at, 'Resource reviewed', $resource->resource->title);
        }
        $assignments = LearningAssignment::query()->whereHas('accessGrant', fn ($query) => $query->where('student_id', $student->id))
            ->with(['accessGrant.course', 'booking:id,student_id'])->orderByDesc('id')->limit(50)->get();
        foreach ($assignments as $assignment) {
            $course = $assignment->accessGrant?->course;
            if (! $course || ($course->kind === 'private' && (int) $course->owner_student_id !== (int) $student->id) || ($assignment->booking_id && (int) $assignment->booking?->student_id !== (int) $student->id)) {
                continue;
            }
            $add('learning:'.$assignment->id.':assigned', $assignment->created_at, $course->kind === 'private' ? 'Private learning assigned' : 'Learning assigned', $course->title);
        }
        $courseIds = Enrollment::query()->where('student_id', $student->id)->orderByDesc('id')->limit(50)->pluck('course_id')->merge($assignments->pluck('accessGrant.course_id'))->unique();
        $lessons = Lesson::query()->whereIn('course_id', $courseIds)->where('status', 'published')->where('published_at', '<=', now('UTC'))
            ->whereHas('section', fn ($query) => $query->where('status', 'published')->where('published_at', '<=', now('UTC')))
            ->whereHas('course', fn ($query) => $query->where(fn ($query) => $query->where('kind', 'catalog')->orWhere('owner_student_id', $student->id)))
            ->with(['blocks.videoAsset', 'course', 'section'])->orderBy('sort_order')->orderBy('id')->get();
        $blocks = $lessons->flatMap(fn (Lesson $lesson) => $lesson->blocks)->keyBy('id');
        $retainedRows = LessonProgress::query()->where('student_id', $student->id)->whereIn('lesson_id', $lessons->pluck('id'))->get()->toBase();
        $rows = $retainedRows->whereNotNull('completed_at')->sortByDesc('completed_at')->take(50);
        $overview = $staff ? null : $this->learning->overview($student);
        foreach ($rows as $row) {
            $lesson = $lessons->firstWhere('id', $row->lesson_id);
            if ($lesson && $row->requirement_hash === $this->definitions->requirementHash($lesson) && ($staff || isset($overview['states'][$lesson->id]))) {
                $video = in_array('video', $this->definitions->lessonRules($lesson)['methods'], true);
                $add('lesson:'.$row->id.':completed', $row->completed_at, $video ? 'Video learning completed' : 'Lesson completed', $lesson->title);
            }
        }
        $quizzes = QuizAttempt::query()->where('student_id', $student->id)->whereIn('block_id', $blocks->keys())->where('status', 'graded')->where('passed', true)->orderByDesc('updated_at')->limit(30)->get();
        $submissions = AssignmentSubmission::query()->where('student_id', $student->id)->whereIn('block_id', $blocks->keys())->where('status', '!=', 'erased')->orderByDesc('updated_at')->limit(30)->get();
        foreach ($quizzes->concat($submissions) as $evidence) {
            $block = $blocks->get($evidence->block_id);
            $kind = $evidence instanceof QuizAttempt ? 'quiz' : 'assignment';
            if (! $block || $block->kind !== $kind || $evidence->definition_hash !== $this->definitions->hash($block->payload ?? []) || (! $staff && ($block->status !== 'ready' || ! ($overview['states'][$block->lesson_id]['allowed'] ?? false)))) {
                continue;
            }
            $detail = $block->payload['title'] ?? ucfirst($kind);
            if ($kind === 'quiz') {
                $add('quiz:'.$evidence->id.':passed', $evidence->graded_at ?? $evidence->updated_at, 'Quiz passed', $detail);
            } else {
                $add('assignment:'.$evidence->id.':submitted', $evidence->created_at, 'Assignment submitted', $detail);
                if (in_array($evidence->status, ['under_review', 'needs_revision', 'approved'], true)) {
                    $reviewedAt = $evidence->reviewed_at ?? $evidence->updated_at;
                    $add('assignment:'.$evidence->id.':review:'.$evidence->status.':'.$reviewedAt?->toIso8601String(), $reviewedAt, 'Assignment reviewed', $detail.' · '.str_replace('_', ' ', $evidence->status));
                }
            }
        }
        foreach ($lessons->groupBy('course_id') as $group) {
            $summary = $this->progress->cohortSummary($group, $retainedRows);
            $course = $group->first()->course;
            if ($summary['completed_at'] && ($staff || isset($overview['outlines'][$course->id]))) {
                $add('course:'.$course->id.':completed:'.$summary['completion_key'], $summary['completed_at'], 'Course completed', $course->title);
            }
        }

        return $events->sort(function (array $first, array $second): int {
            return $second['at']->getTimestamp() <=> $first['at']->getTimestamp() ?: strcmp($first['key'], $second['key']);
        })->take(80)->values();
    }
}
