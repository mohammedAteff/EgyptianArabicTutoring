<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\LessonNote;
use App\Domains\Lms\Services\LmsAccessService;
use App\Domains\Lms\Services\LmsLearningGate;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\FeedbackReadModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Closed first-party surface dictionary. Record IDs are hints, never authority. */
class EstimatedActivityRegistry
{
    public const CONTEXTS = [
        'student_lesson' => ['routes' => ['student.learning.lessons.show'], 'category' => 'lms'],
        'student_assessment' => ['routes' => ['student.evidence.show'], 'category' => 'lms'],
        'student_note_draft' => ['routes' => ['student.learning.lessons.show'], 'category' => 'lms'],
        'student_note' => ['routes' => ['student.learning.lessons.show'], 'category' => 'lms'],
        'student_homework' => ['routes' => ['student.teaching.index'], 'category' => 'homework'],
        'admin_course_create' => ['routes' => ['admin.lms.courses.create'], 'category' => 'learning_materials'],
        'admin_course' => ['routes' => ['admin.lms.courses.edit'], 'category' => 'learning_materials'],
        'admin_lms_review' => ['routes' => ['admin.lms.reviews.index'], 'category' => 'homework_review'],
        'admin_lesson_materials' => ['routes' => ['admin.lessons.show'], 'category' => 'lesson_preparation'],
        'admin_feedback' => ['routes' => ['admin.feedback.show'], 'category' => 'feedback'],
        'admin_booking' => ['routes' => ['admin.bookings.show'], 'category' => 'booking_admin'],
        'admin_booking_create' => ['routes' => ['admin.bookings.create'], 'category' => 'booking_admin'],
        'admin_teaching_homework' => ['routes' => ['admin.students.teaching'], 'category' => 'homework_review'],
        'admin_teaching_materials' => ['routes' => ['admin.students.teaching'], 'category' => 'lesson_preparation'],
        'admin_teaching_plan' => ['routes' => ['admin.students.teaching'], 'category' => 'general_admin'],
        'admin_teaching_practice' => ['routes' => ['admin.students.teaching'], 'category' => 'general_admin'],
    ];

    public function validate(Student|Administrator $actor, string $kind, int $record): string
    {
        abort_unless(isset(self::CONTEXTS[$kind]), 404);
        abort_unless(($actor instanceof Student) === str_starts_with($kind, 'student_'), 403);
        if ($actor instanceof Student) {
            if ($kind === 'student_homework') {
                $homework = Homework::where('student_id', $actor->id)->where('student_visible', true)
                    ->where(fn ($query) => $query->whereNull('booking_id')->orWhereHas('booking', fn ($booking) => $booking->where('student_id', $actor->id)))
                    ->findOrFail($record);
            } else {
                $block = null;
                if (in_array($kind, ['student_lesson', 'student_assessment'], true)) {
                    $block = LessonBlock::findOrFail($record);
                    abort_unless($block->status === 'ready' && in_array($block->kind, $kind === 'student_lesson' ? ['rich_text', 'image'] : ['quiz', 'assignment'], true), 404);
                    $lesson = $block->lesson()->firstOrFail();
                } elseif ($kind === 'student_note') {
                    $lesson = LessonNote::where('student_id', $actor->id)->findOrFail($record)->lesson()->firstOrFail();
                } else {
                    $lesson = Lesson::findOrFail($record);
                }
                $access = app(LmsAccessService::class);
                abort_unless($access->canAccess($actor, $lesson) && app(LmsLearningGate::class)->decision($actor, $lesson)['allowed']
                    && (! $block || $access->canAccess($actor, $block)), 404);
            }
        } else {
            abort_unless($actor->isSuperAdmin() && ! $actor->suspended_at && ! $actor->trashed(), 403);
            if (in_array($kind, ['admin_course', 'admin_course_create', 'admin_lms_review'], true)) {
                Gate::forUser($actor)->authorize('manage', Course::class);
                if ($kind !== 'admin_course_create') {
                    Course::findOrFail($record);
                } else {
                    abort_unless($record === 0, 404);
                }
            } elseif ($kind === 'admin_feedback') {
                app(FeedbackReadModel::class)->conversation($actor, $record);
            } elseif (str_starts_with($kind, 'admin_teaching_')) {
                Gate::forUser($actor)->authorize('manageTeaching', Student::findOrFail($record));
            } elseif ($kind === 'admin_booking_create') {
                abort_unless($record === 0, 404);
            } else {
                Gate::forUser($actor)->authorize('manageLessonWorkspace', Booking::findOrFail($record));
            }
        }

        return self::CONTEXTS[$kind]['category'];
    }

    /** Course mutations serialize on their course row; no page loader or progress write is invoked. */
    public function signature(string $kind, int $record): string
    {
        if (in_array($kind, ['student_lesson', 'student_assessment'], true)) {
            $block = LessonBlock::findOrFail($record);
            $lesson = $block->lesson()->firstOrFail();
        } elseif ($kind === 'student_note') {
            $lesson = LessonNote::findOrFail($record)->lesson()->firstOrFail();
        } elseif ($kind === 'student_note_draft') {
            $lesson = Lesson::findOrFail($record);
        } else {
            return EstimatedActivityDefinition::fingerprint($kind.':'.$record);
        }
        $course = Course::whereKey($lesson->course_id)->lockForUpdate()->firstOrFail();
        $lesson = Lesson::whereKey($lesson->id)->lockForUpdate()->firstOrFail();
        $section = $lesson->section()->lockForUpdate()->firstOrFail();
        $block = isset($block) ? $block->fresh() : null;
        $epoch = DB::table('estimated_activity_context_versions')->where('course_id', $course->id)->lockForUpdate()->value('generation');
        if ($epoch === null) {
            DB::table('estimated_activity_context_versions')->insert(['course_id' => $course->id, 'generation' => 1]);
            $epoch = 1;
        }

        return EstimatedActivityDefinition::fingerprint(json_encode([$epoch, $course->id, $course->lock_version, $course->status,
            $lesson->id, $lesson->lock_version, $lesson->status, $section->id, $section->lock_version, $section->status,
            $block?->id, $block?->lock_version, $block?->status], JSON_THROW_ON_ERROR));
    }
}
