<?php

namespace App\Domains\Lms\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Lms\Models\AssignmentSubmission;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\LearningAssignment;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\QuizAttempt;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentTeachingReadModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class LmsTutoringReadModel
{
    public function __construct(private LmsAccessService $access, private LmsProgressService $progress,
        private LmsLearningDefinition $definitions, private StudentLearningService $learning, private StudentTeachingReadModel $teaching, private LmsTeachingTimeline $timeline) {}

    /** @return Collection<int,array<string,mixed>> */
    public function assignments(Student $student, ?int $bookingId = null): Collection
    {
        $rows = LearningAssignment::query()->whereHas('accessGrant', fn ($query) => $query->where('student_id', $student->id))
            ->when($bookingId, fn ($query) => $query->where('booking_id', $bookingId))
            ->with(['accessGrant.course', 'accessGrant.lesson', 'accessGrant.section', 'lessonBlock', 'booking:id,student_id,status,start_at_utc'])
            ->orderByDesc('id')->limit(50)->get();
        $courseIds = $rows->pluck('accessGrant.course_id')->unique();
        $lessons = Lesson::query()->whereIn('course_id', $courseIds)->where('status', 'published')->where('published_at', '<=', now('UTC'))
            ->whereHas('section', fn ($query) => $query->where('status', 'published')->where('published_at', '<=', now('UTC')))
            ->with(['section', 'blocks.videoAsset'])->orderBy('sort_order')->orderBy('id')->get();
        $summaries = $lessons->groupBy('course_id')->map(fn (Collection $group): array => $this->progress->summary($student, $group));
        $blockIds = $lessons->flatMap(fn (Lesson $lesson) => $lesson->blocks)->pluck('id');
        $quizzes = QuizAttempt::query()->where('student_id', $student->id)->whereIn('block_id', $blockIds)->where('status', '!=', 'erased')->orderByDesc('number')->get()->toBase()->groupBy('block_id');
        $submissions = AssignmentSubmission::query()->where('student_id', $student->id)->whereIn('block_id', $blockIds)->where('status', '!=', 'erased')->orderByDesc('number')->get()->toBase()->groupBy('block_id');

        return $rows->filter(function (LearningAssignment $row) use ($student): bool {
            $course = $row->accessGrant?->course;

            return $course && ($course->kind !== 'private' || (int) $course->owner_student_id === (int) $student->id)
                && (! $row->booking_id || (int) $row->booking?->student_id === (int) $student->id);
        })->map(fn (LearningAssignment $row): array => $this->projection($row, $student, $summaries, $quizzes, $submissions))->values();
    }

    /** @param Collection<int,array<string,mixed>> $summaries
     * @param Collection<int|string,Collection<int,QuizAttempt>> $quizzes
     * @param Collection<int|string,Collection<int,AssignmentSubmission>> $submissions
     * @return array<string,mixed> */
    private function projection(LearningAssignment $row, Student $student, Collection $summaries, Collection $quizzes, Collection $submissions): array
    {
        $grant = $row->accessGrant;
        $course = $grant->course;
        $summary = $summaries->get($course->id, ['status' => 'Not Started', 'percent' => 0, 'lessons' => collect(), 'completed_at' => null]);
        $state = $grant->lesson_id ? ($summary['lessons']->get($grant->lesson_id)['status'] ?? 'Not Started') : $summary['status'];
        $assessment = null;
        if ($row->lessonBlock && $row->lessonBlock->kind === $row->block_kind) {
            $block = $row->lessonBlock;
            $hash = $this->definitions->hash($block->payload ?? []);
            $currentEvidence = ($block->kind === 'quiz' ? $quizzes : $submissions)->get($block->id, collect())->where('definition_hash', $hash);
            $evidence = $currentEvidence->first();
            $assessment = match ($evidence?->status) {
                'started' => 'In Progress', 'pending_review' => 'Awaiting manual review',
                'graded' => $evidence instanceof QuizAttempt && $evidence->passed ? 'Passed' : 'Graded — not passed',
                'submitted' => 'Submitted', 'under_review' => 'Under Review', 'needs_revision' => 'Needs Revision', 'approved' => 'Approved',
                default => $block->kind === 'quiz' ? 'Not Started' : 'Not Submitted',
            };
            $completed = $currentEvidence->contains(fn (QuizAttempt|AssignmentSubmission $item): bool => $item instanceof QuizAttempt
                ? $item->status === 'graded' && $item->passed === true : $item->status === 'approved');
            $state = $completed ? 'Completed' : (in_array($assessment, ['Not Started', 'Not Submitted'], true) ? 'Not Started' : 'In Progress');
        }
        $allowed = $this->access->canAccess($student, $row);
        $expired = $row->status !== 'assigned' || $grant->status !== 'active' || ($grant->expires_at && $grant->expires_at->lte(now('UTC')));
        $group = $expired ? 'Expired' : ($grant->starts_at->isFuture() ? 'Scheduled' : ($allowed ? ($state === 'Not Started' ? 'New' : $state) : 'Unavailable'));
        $reason = $expired ? ($grant->status === 'revoked' ? 'Access revoked' : 'Access ended')
            : ($group === 'Scheduled' ? 'Available at the scheduled start' : (! $allowed ? ($course->status === 'draft' ? 'Preparing learning' : 'Learning is currently locked or unavailable') : null));
        $title = $row->lessonBlock ? ($row->lessonBlock->payload['title'] ?? ucfirst((string) $row->block_kind))
            : ($grant->lesson ? $grant->lesson->title : ($grant->section ? $grant->section->title : $course->title));

        return ['assignment' => $row, 'grant' => $grant, 'course' => $course, 'title' => $title, 'kind' => $row->block_kind ?? ($course->kind === 'private' ? 'private' : ($grant->lesson_id ? 'lesson' : 'course')),
            'state' => $state, 'group' => $group, 'assessment' => $assessment, 'progress' => $summary, 'allowed' => $allowed,
            'instructions' => $allowed ? $row->instructions : null, 'reason' => $reason,
            'starts_at' => $grant->starts_at, 'expires_at' => $grant->expires_at,
            'url' => $allowed ? route('student.learning.assignments.show', $row) : null,
            'booking_id' => $row->booking_id, 'booking_date' => $row->booking?->start_at_utc];
    }

    /** @return array<string,mixed> */
    public function profile(Administrator $actor, Student $student): array
    {
        $student = Student::query()->findOrFail($student->id);
        Gate::forUser($actor)->authorize('manageTeaching', $student);
        Gate::forUser($actor)->authorize('manage', Course::class);
        $items = $this->assignments($student);
        $hub = $this->learning->hub($student);
        $blockIds = LessonBlock::query()->whereHas('lesson.course', fn ($query) => $query->where('kind', 'catalog')->orWhere('owner_student_id', $student->id))->select('id');
        $quizzes = QuizAttempt::query()->where('student_id', $student->id)->whereIn('block_id', $blockIds)->where('status', 'pending_review')->count();
        $submissions = AssignmentSubmission::query()->where('student_id', $student->id)->whereIn('block_id', $blockIds)->whereIn('status', ['submitted', 'under_review'])->count();

        return ['items' => $items, 'cards' => $hub['cards'], 'resources' => $this->teaching->resources($student)->take(20), 'timeline' => $this->timeline->staff($actor, $student),
            'drafts' => Course::query()->where('kind', 'private')->where('owner_student_id', $student->id)->whereNotNull('private_learning_key')->where('status', 'draft')->orderByDesc('id')->limit(20)->get(),
            'review_needs' => ['quizzes' => $quizzes, 'assignments' => $submissions]];
    }
}
