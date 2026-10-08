<?php

namespace App\Domains\Lms\Services;

use App\Domains\Lms\Models\AssignmentSubmission;
use App\Domains\Lms\Models\LearningAssignment;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\QuizAttempt;
use App\Domains\Students\Models\Student;

class LmsLearningNotifications
{
    public function __construct(private LmsAccessService $access, private LmsLearningDefinition $definitions) {}

    /** @return list<array{key:string,type:string,title:string,message:string,link:string}> */
    public function facts(Student $student): array
    {
        $events = [];
        $assignments = LearningAssignment::query()->whereHas('accessGrant', fn ($query) => $query->where('student_id', $student->id))
            ->where('status', 'assigned')->orderByDesc('id')->limit(50)->get();
        foreach ($assignments as $assignment) {
            if ($this->access->canAccess($student, $assignment)) {
                $events[] = ['key' => 'lms:'.$student->id.':assignment:'.$assignment->id, 'type' => 'learning',
                    'title' => 'Learning assigned', 'message' => 'Your tutor has shared learning for you.',
                    'link' => route('student.learning.assignments.show', $assignment, false)];
            }
        }
        $quizzes = QuizAttempt::query()->where('student_id', $student->id)->where('status', 'graded')->orderByDesc('updated_at')->limit(30)->get();
        $submissions = AssignmentSubmission::query()->where('student_id', $student->id)->whereIn('status', ['under_review', 'needs_revision', 'approved'])->orderByDesc('updated_at')->limit(30)->get();
        $blocks = LessonBlock::query()->whereIn('id', $quizzes->pluck('block_id')->merge($submissions->pluck('block_id')))->with('lesson')->get()->keyBy('id');
        foreach ($quizzes->concat($submissions) as $evidence) {
            $block = $blocks->get($evidence->block_id);
            $kind = $evidence instanceof QuizAttempt ? 'quiz' : 'assignment';
            if (! $block || $block->kind !== $kind || $evidence->definition_hash !== $this->definitions->hash($block->payload ?? []) || ! $this->access->canAccess($student, $block)) {
                continue;
            }
            $reviewedAt = $evidence instanceof QuizAttempt ? $evidence->graded_at : $evidence->reviewed_at;
            $reviewKey = $reviewedAt ? $evidence->status.':'.$reviewedAt->toIso8601String() : (string) $evidence->lock_version;
            $events[] = ['key' => 'lms:'.$student->id.':'.$kind.':'.$evidence->id.':review:'.$reviewKey,
                'type' => 'learning', 'title' => $kind === 'quiz' ? 'Quiz result available' : 'Assignment review updated',
                'message' => 'Open your learning to see the current result and feedback.',
                'link' => route('student.evidence.show', [$block->lesson->course_id, $block->lesson_id, $block], false)];
        }

        return $events;
    }
}
