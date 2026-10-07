<?php

namespace App\Domains\Lms\Services;

use App\Domains\Booking\Services\LessonMaterialService;
use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Enrollment;
use App\Domains\Lms\Models\LearningVisit;
use App\Domains\Lms\Models\LmsAsset;
use Illuminate\Support\Facades\DB;
use LogicException;

class LmsStudentLifecycle
{
    public function __construct(private LessonMaterialService $files) {}

    /** Called only after the canonical merge has locked its Student/Booking graph. */
    public function merge(int $primaryId, int $secondaryId): void
    {
        $this->requireTransaction();
        DB::table('lms_playback_leases')->whereIn('student_id', [$primaryId, $secondaryId])->where('status', 'active')->update(['status' => 'revoked']);
        DB::table('lms_authorized_devices')->whereIn('student_id', [$primaryId, $secondaryId])->update(['status' => 'revoked', 'revoked_at' => now('UTC')]);
        Course::query()->whereIn('id', AccessGrant::query()->whereIn('student_id', [$primaryId, $secondaryId])->select('course_id'))
            ->orWhereIn('owner_student_id', [$primaryId, $secondaryId])->orderBy('id')->lockForUpdate()->get(['id']);
        DB::table('lms_courses')->where('owner_student_id', $secondaryId)->update(['owner_student_id' => $primaryId]);
        $enrollments = Enrollment::query()->whereIn('student_id', [$primaryId, $secondaryId])->orderBy('id')->lockForUpdate()->get();
        foreach ($enrollments->where('student_id', $secondaryId) as $source) {
            $survivor = $enrollments->where('student_id', $primaryId)->where('course_id', $source->course_id)->where('status', '!=', 'superseded')->first();
            if ($source->status !== 'superseded' && $survivor) {
                $source->forceFill(['status' => 'superseded', 'superseded_by_id' => $survivor->id])->save();
            }
            $source->forceFill(['student_id' => $primaryId])->save();
        }
        DB::table('lms_access_grants')->where('student_id', $secondaryId)->update(['student_id' => $primaryId]);
        DB::table('lms_access_events')->where('student_id', $secondaryId)->update(['student_id' => $primaryId]);
        $visits = LearningVisit::query()->whereIn('student_id', [$primaryId, $secondaryId])->orderBy('id')->lockForUpdate()->get();
        foreach ($visits->where('student_id', $secondaryId) as $source) {
            $survivor = $visits->where('student_id', $primaryId)->firstWhere('course_id', $source->course_id);
            if ($survivor) {
                $latest = $source->accessed_at->gt($survivor->accessed_at) ? $source : $survivor;
                $survivor->forceFill(['lesson_id' => $latest->lesson_id ?? $source->lesson_id ?? $survivor->lesson_id, 'accessed_at' => $latest->accessed_at])->save();
                $source->delete();
            } else {
                $source->forceFill(['student_id' => $primaryId])->save();
            }
        }
        DB::table('lms_lesson_notes')->where('student_id', $secondaryId)->update(['student_id' => $primaryId, 'lock_version' => DB::raw('lock_version + 1')]);
        DB::table('lms_lesson_bookmarks')->where('student_id', $secondaryId)->update(['student_id' => $primaryId]);
    }

    /** The existing privacy service owns authorization, locks and structural retention. */
    public function erase(int $studentId): void
    {
        $this->requireTransaction();
        DB::table('lms_playback_leases')->where('student_id', $studentId)->delete();
        DB::table('lms_authorized_devices')->where('student_id', $studentId)->delete();
        foreach (['lms_lesson_notes', 'lms_lesson_bookmarks', 'lms_learning_visits'] as $table) {
            DB::table($table)->where('student_id', $studentId)->delete();
        }
        Course::query()->whereIn('id', AccessGrant::query()->where('student_id', $studentId)->select('course_id'))
            ->orWhere('owner_student_id', $studentId)->orderBy('id')->lockForUpdate()->get(['id']);
        $grants = AccessGrant::query()->where('student_id', $studentId)->orderBy('id')->lockForUpdate()->get(['id']);
        DB::table('lms_learning_assignments')->whereIn('access_grant_id', $grants->modelKeys())->update(['instructions' => null, 'status' => 'withdrawn']);
        DB::table('lms_access_grants')->where('student_id', $studentId)->whereNull('revoked_at')->update(['revoked_at' => now('UTC'), 'status' => 'revoked']);
        DB::table('lms_access_grants')->where('student_id', $studentId)->update(['reason' => null, 'metadata' => null, 'lock_version' => DB::raw('lock_version + 1')]);
        DB::table('lms_enrollments')->where('student_id', $studentId)->where('status', '!=', 'superseded')->update(['status' => 'archived']);
        $courses = Course::query()->where('owner_student_id', $studentId)->orderBy('id')->lockForUpdate()->get();
        foreach ($courses as $course) {
            DB::table('lms_video_assets')->where('course_id', $course->id)->where('status', '!=', 'deleted')->update(['status' => 'withdrawn', 'label' => 'Redacted private media', 'external_url' => null]);
            $course->forceFill(['title' => 'Redacted private learning', 'slug' => 'private-redacted-'.$course->id.'-'.bin2hex(random_bytes(8)), 'status' => 'archived',
                'lock_version' => $course->lock_version + 1])->save();
            DB::table('content_revisions')->where('revisable_type', Course::class)->where('revisable_id', $course->id)->update([
                'title' => 'Redacted private learning', 'status' => 'privacy_erased',
                'content' => json_encode(['schema' => 1, 'title' => 'Redacted private learning', 'slug' => $course->slug,
                    'access' => ['audience' => 'selected_students', 'access_mode' => 'permanent', 'starts_at' => null, 'expires_at' => null, 'relative_days' => null], 'sections' => []], JSON_THROW_ON_ERROR),
            ]);
            foreach (LmsAsset::query()->where('course_id', $course->id)->orderBy('id')->lockForUpdate()->get() as $asset) {
                $asset->forceFill(['status' => 'withdrawn', 'original_name' => null])->save();
                DB::afterCommit(fn () => $this->files->cleanupCourseAsset($asset));
            }
            DB::table('lms_sections')->where('course_id', $course->id)->update(['title' => 'Redacted section', 'status' => 'archived']);
            $lessons = DB::table('lms_lessons')->where('course_id', $course->id)->pluck('id');
            foreach ($lessons as $lessonId) {
                DB::table('lms_lessons')->where('id', $lessonId)->update(['title' => 'Redacted lesson', 'slug' => 'redacted-lesson-'.$lessonId.'-'.bin2hex(random_bytes(8)), 'status' => 'archived']);
            }
            DB::table('lms_lesson_blocks')->whereIn('lesson_id', $lessons)->update(['status' => 'withdrawn', 'resource_id' => null, 'asset_id' => null, 'video_asset_id' => null, 'payload' => null]);
        }
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Canonical Student lifecycle locks must be held before LMS ownership changes.');
        }
    }
}
