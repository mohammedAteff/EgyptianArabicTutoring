<?php

namespace App\Domains\Lms\Services;

use App\Domains\Lms\Models\AssignmentSubmission;
use App\Domains\Lms\Models\LearningAssignment;
use App\Domains\Lms\Models\QuizAttempt;
use App\Domains\Students\Models\Student;

class LmsLearningNotifications
{
    public function __construct(private LmsAccessService $access, private LmsLearningDefinition $definitions, private StudentLearningService $learning, private LmsSettings $settings) {}

    /** @return list<array{key:string,type:string,title:string,message:string,link:string}> */
    public function facts(Student $student): array
    {
        $events = [];
        $readyVideos = [];
        $overview = $this->learning->overview($student);
        $options = $this->settings->values();
        $assignments = LearningAssignment::query()->whereHas('accessGrant', fn ($query) => $query->where('student_id', $student->id))
            ->where('status', 'assigned')->with(['accessGrant', 'booking:id,student_id'])->orderByDesc('id')->limit(50)->get();
        foreach ($assignments as $assignment) {
            if ($this->access->assignmentInOutline($student, $assignment, $overview['outlines'], $overview['states'])) {
                $course = $overview['outlines'][$assignment->accessGrant->course_id]['course'];
                $events[] = ['key' => 'lms:'.$student->id.':assignment:'.$assignment->id, 'type' => 'learning',
                    'title' => $course->kind === 'private' ? 'Private learning assigned' : 'Learning assigned', 'message' => 'Your tutor has shared learning for you.',
                    'link' => route('student.learning.assignments.show', $assignment, false)];
                if ($course->kind === 'private' && count($readyVideos) < 50) {
                    foreach ($overview['canonical']->where('course_id', $course->id)->flatMap(fn ($lesson) => $lesson->blocks)->where('status', 'ready')->where('kind', 'video') as $block) {
                        $video = $block->videoAsset;
                        if ($video?->provider === 'bunny' && $video->usableFor($course) && ($overview['states'][$block->lesson_id]['allowed'] ?? false) && ! isset($readyVideos[$block->id])) {
                            $readyVideos[$block->id] = true;
                            $events[] = ['key' => 'lms:'.$student->id.':video:'.$block->id.':'.$this->definitions->mediaHash($block), 'type' => 'learning',
                                'title' => 'Private video ready', 'message' => 'Your tutor’s private video is ready to watch.', 'link' => route('student.learning.assignments.show', $assignment, false)];
                            if (count($readyVideos) >= 50) {
                                break;
                            }
                        }
                    }
                }
            }
        }
        $quizzes = QuizAttempt::query()->where('student_id', $student->id)->where('status', 'graded')->orderByDesc('updated_at')->limit(30)->get();
        $submissions = AssignmentSubmission::query()->where('student_id', $student->id)->whereIn('status', ['under_review', 'needs_revision', 'approved'])->orderByDesc('updated_at')->limit(30)->get();
        $blocks = $overview['canonical']->flatMap(fn ($lesson) => $lesson->blocks)->keyBy('id');
        foreach ($quizzes->concat($submissions) as $evidence) {
            $block = $blocks->get($evidence->block_id);
            $kind = $evidence instanceof QuizAttempt ? 'quiz' : 'assignment';
            if (! $block || $block->status !== 'ready' || $block->kind !== $kind || $evidence->definition_hash !== $this->definitions->hash($block->payload ?? []) || ! ($overview['states'][$block->lesson_id]['allowed'] ?? false)) {
                continue;
            }
            $reviewedAt = $evidence instanceof QuizAttempt ? $evidence->graded_at : $evidence->reviewed_at;
            $reviewKey = $reviewedAt ? $evidence->status.':'.$reviewedAt->toIso8601String() : (string) $evidence->lock_version;
            $events[] = ['key' => 'lms:'.$student->id.':'.$kind.':'.$evidence->id.':review:'.$reviewKey,
                'type' => 'learning', 'title' => $kind === 'quiz' ? 'Quiz result available' : ($evidence->status === 'needs_revision' ? 'Assignment needs revision' : 'Assignment review updated'),
                'message' => 'Open your learning to see the current result and feedback.',
                'link' => route('student.evidence.show', [$block->lesson->course_id, $block->lesson_id, $block], false)];
        }

        if ($options['expiry_notifications']) {
            $courses = collect($overview['outlines'])->pluck('course');
            $ends = $this->access->accessEndings($student, $courses);
            foreach ($courses->take(50) as $course) {
                $end = $ends[$course->id];
                if ($end && $end->gt(now('UTC')) && $end->lte(now('UTC')->addSeconds((int) $options['expiry_days'] * 86400))) {
                    $events[] = ['key' => 'lms:'.$student->id.':course:'.$course->id.':expiry:'.$end->toIso8601String(), 'type' => 'learning',
                        'title' => 'Learning access ending soon', 'message' => 'Open My Learning to check your current access end time.', 'link' => route('student.learning.courses.show', $course, false)];
                }
            }
        }
        if ($options['drip_notifications']) {
            foreach ($overview['canonical']->sortByDesc('published_at') as $lesson) {
                $state = $overview['states'][$lesson->id] ?? null;
                if ($state && $state['allowed'] && ! $state['completed'] && $state['unlocks_at'] !== null && $state['unlocks_at']->lte(now('UTC'))) {
                    $events[] = ['key' => 'lms:'.$student->id.':lesson:'.$lesson->id.':unlocked:'.$state['unlocks_at']->toIso8601String().':'.$this->definitions->requirementHash($lesson), 'type' => 'learning',
                        'title' => 'Scheduled lesson available', 'message' => 'A scheduled lesson is now available in your learning.', 'link' => route('student.learning.lessons.show', [$lesson->course_id, $lesson], false)];
                    if (count(array_filter($events, fn (array $event): bool => $event['title'] === 'Scheduled lesson available')) >= 50) {
                        break;
                    }
                }
            }
        }

        return $events;
    }
}
