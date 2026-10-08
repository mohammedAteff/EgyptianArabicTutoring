<?php

namespace App\Domains\Lms\Services;

use App\Domains\Lms\Models\Enrollment;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonProgress;
use App\Domains\Students\Models\Student;
use Carbon\CarbonImmutable;

class LmsLearningGate
{
    public function __construct(private LmsLearningDefinition $definitions) {}

    /** This gate adds educational locks; it never grants or evaluates entitlement.
     * @return array{allowed:bool,reason:?string,unlocks_at:?CarbonImmutable} */
    public function decision(?Student $student, Lesson $lesson, ?array $completed = null, ?array $anchors = null): array
    {
        $rules = $this->definitions->lessonRules($lesson);
        $unlocks = null;
        if ($rules['drip_mode'] === 'fixed') {
            $unlocks = CarbonImmutable::parse($rules['drip_at'])->utc();
        }
        if ($rules['drip_mode'] === 'relative') {
            $rows = $anchors === null && $student ? Enrollment::query()->where('student_id', $student->id)->where('course_id', $lesson->course_id)->get() : collect();
            $anchor = $anchors !== null ? ($anchors[$lesson->course_id] ?? null) : ($rows->contains('status', 'enrolled') ? $rows->sortBy('enrolled_at')->first()?->enrolled_at : null);
            if (! $anchor) {
                return ['allowed' => false, 'reason' => 'Enrollment is required for this scheduled lesson.', 'unlocks_at' => null];
            }
            $unlocks = CarbonImmutable::parse($anchor)->utc()->addSeconds((int) $rules['drip_days'] * 86400);
        }
        if ($unlocks && $unlocks->isFuture()) {
            return ['allowed' => false, 'reason' => 'This lesson is scheduled for later.', 'unlocks_at' => $unlocks];
        }
        if (! empty($rules['prerequisite_key'])) {
            $id = (int) substr($rules['prerequisite_key'], 7);
            if ($completed !== null && array_key_exists($id, $completed)) {
                return ['allowed' => $completed[$id], 'reason' => $completed[$id] ? null : 'Complete the prerequisite lesson first.', 'unlocks_at' => $unlocks];
            }
            $previous = Lesson::query()->where('course_id', $lesson->course_id)->find($id);
            if (! $student || ! $previous || ! LessonProgress::query()->where('student_id', $student->id)->where('lesson_id', $id)
                ->where('requirement_hash', $this->definitions->requirementHash($previous))->whereNotNull('completed_at')->exists()) {
                return ['allowed' => false, 'reason' => 'Complete the prerequisite lesson first.', 'unlocks_at' => $unlocks];
            }
        }

        return ['allowed' => true, 'reason' => null, 'unlocks_at' => $unlocks];
    }
}
