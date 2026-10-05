<?php

namespace App\Domains\Students\Services;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\LessonMaterial;
use App\Domains\Resources\Models\Resource;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\LearningMilestone;
use App\Domains\Students\Models\LearningPlan;
use App\Domains\Students\Models\ResourceAssignment;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentErrorLog;
use App\Domains\Students\Models\TeachingTag;
use App\Domains\Students\Models\TutorPreparation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeachingRecordService
{
    public const MODELS = [
        'homework' => Homework::class, 'plan' => LearningPlan::class, 'milestone' => LearningMilestone::class,
        'preparation' => TutorPreparation::class, 'resource' => ResourceAssignment::class,
        'error' => StudentErrorLog::class, 'tag' => TeachingTag::class,
    ];

    public function __construct(private AuditLogService $audits) {}

    public function lockStudent(int $studentId): Student
    {
        $student = Student::query()->whereNull('merged_into_student_id')->lockForUpdate()->findOrFail($studentId);
        abort_if($student->suspended_at !== null, 404);

        return $student;
    }

    public function lockBooking(Student $student, ?int $bookingId): ?Booking
    {
        return $bookingId ? Booking::query()->where('student_id', $student->id)->lockForUpdate()->findOrFail($bookingId) : null;
    }

    /** @param array<string, mixed> $data */
    public function save(Student $student, string $kind, array $data, int $actorId): Model
    {
        abort_unless(isset(self::MODELS[$kind]), 404);

        return DB::transaction(function () use ($student, $kind, $data, $actorId): Model {
            $student = $this->lockStudent($student->id);
            $booking = $this->lockBooking($student, isset($data['booking_id']) ? (int) $data['booking_id'] : null);
            $modelClass = self::MODELS[$kind];
            $recordId = $data['record_id'] ?? null;
            unset($data['record_id']);
            if ($kind === 'milestone') {
                $plan = LearningPlan::query()->where('student_id', $student->id)->lockForUpdate()->findOrFail($data['learning_plan_id']);
                $record = $recordId ? $plan->milestones()->lockForUpdate()->findOrFail($recordId) : new LearningMilestone;
                unset($data['booking_id']);
                $data['completed_at'] = $data['status'] === 'completed' ? ($record->completed_at ?? now('UTC')) : null;
            } else {
                $record = $recordId ? $modelClass::query()->where('student_id', $student->id)->lockForUpdate()->findOrFail($recordId) : new $modelClass;
                $data['student_id'] = $student->id;
                if ($kind !== 'plan') {
                    $data['booking_id'] = $booking?->id;
                } else {
                    unset($data['booking_id']);
                }
                if (! $record->exists) {
                    $data['created_by'] = $actorId;
                }
            }
            if (! empty($data['resource_id'])) {
                Resource::query()->published()->lockForUpdate()->findOrFail($data['resource_id']);
            }
            if ($kind === 'homework' && ! empty($data['lesson_material_id'])) {
                if (! $booking) {
                    throw ValidationException::withMessages(['lesson_material_id' => 'Choose the lesson that owns this material.']);
                }
                LessonMaterial::query()->where('booking_id', $booking->id)->whereNull('withdrawn_at')->lockForUpdate()->findOrFail($data['lesson_material_id']);
            }
            if ($kind === 'homework' && empty($data['lesson_material_id']) && ! array_key_exists('lesson_material_id', $data) && $record->lesson_material_id) {
                abort_unless($booking && (int) $record->material?->booking_id === (int) $booking->id, 404);
            }
            if ($kind === 'resource' && $record->exists && (int) $record->resource_id !== (int) $data['resource_id']) {
                $data['reviewed_at'] = null;
            }
            if ($kind === 'resource' && ! empty($data['withdraw'])) {
                $data['student_visible'] = false;
            }
            unset($data['withdraw']);
            if ($kind === 'tag') {
                $data['label'] = mb_strtolower(trim($data['label']));
                if (! $recordId) {
                    $record = TeachingTag::query()->where('student_id', $student->id)->where('booking_id', $booking?->id)->where('label', $data['label'])->first() ?? $record;
                }
            }
            $record->fill($data)->save();
            if ($kind === 'milestone') {
                $plan->touch();
            }
            $this->audits->log('student_teaching_'.$kind.'_saved', Student::class, $student->id, null,
                ['record_id' => $record->id, 'kind' => $kind, 'booking_id' => $booking?->id], $actorId);

            return $record;
        }, 3);
    }

    /** Student and Booking locks are held by privacy/merge services. */
    public function erase(int $studentId): void
    {
        DB::table('homeworks')->where('student_id', $studentId)->update(['title' => 'Redacted homework', 'instructions' => '[redacted]', 'feedback' => null, 'student_response' => null, 'url' => null, 'resource_id' => null, 'lesson_material_id' => null, 'student_visible' => false]);
        DB::table('learning_milestones')->whereIn('learning_plan_id', DB::table('learning_plans')->select('id')->where('student_id', $studentId))->update(['title' => 'Redacted milestone']);
        DB::table('learning_plans')->where('student_id', $studentId)->update(['title' => 'Redacted plan', 'goals' => '[redacted]', 'focus_areas' => null, 'current_level' => null, 'notes' => null, 'student_visible' => false]);
        DB::table('tutor_preparations')->where('student_id', $studentId)->update(['body' => '[redacted]']);
        DB::table('resource_assignments')->where('student_id', $studentId)->update(['instructions' => null, 'student_visible' => false]);
        DB::table('student_error_logs')->where('student_id', $studentId)->update(['mistake' => '[redacted]', 'correction' => '[redacted]', 'notes' => null, 'student_visible' => false]);
        DB::table('teaching_tags')->where('student_id', $studentId)->update(['label' => 'redacted', 'student_visible' => false]);
        DB::table('lesson_feedback')->where('student_id', $studentId)->update(['rating' => null, 'comment' => null]);
        DB::table('student_notifications')->where('student_id', $studentId)->delete();
    }
}
