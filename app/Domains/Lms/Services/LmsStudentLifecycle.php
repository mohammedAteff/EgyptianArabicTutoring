<?php

namespace App\Domains\Lms\Services;

use App\Domains\Booking\Services\LessonMaterialService;
use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Enrollment;
use App\Domains\Lms\Models\LearningVisit;
use App\Domains\Lms\Models\LmsAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
        foreach (DB::table('lms_lesson_progress')->where('student_id', $secondaryId)->get() as $source) {
            $target = DB::table('lms_lesson_progress')->where('student_id', $primaryId)->where('lesson_id', $source->lesson_id)->where('requirement_hash', $source->requirement_hash)->first();
            if ($target) {
                DB::table('lms_lesson_progress')->where('id', $target->id)->update([
                    'started_at' => min($target->started_at, $source->started_at),
                    'manual_completed_at' => $target->manual_completed_at ?? $source->manual_completed_at,
                    'completed_at' => $target->completed_at ?? $source->completed_at,
                ]);
                DB::table('lms_lesson_progress')->where('id', $source->id)->delete();
            } else {
                DB::table('lms_lesson_progress')->where('id', $source->id)->update(['student_id' => $primaryId]);
            }
        }
        foreach (DB::table('lms_video_progress')->where('student_id', $secondaryId)->get() as $source) {
            $target = DB::table('lms_video_progress')->where('student_id', $primaryId)->where('block_id', $source->block_id)->where('media_hash', $source->media_hash)->first();
            if ($target) {
                $ranges = array_merge(json_decode($target->watched_ranges, true, 16, JSON_THROW_ON_ERROR), json_decode($source->watched_ranges, true, 16, JSON_THROW_ON_ERROR));
                usort($ranges, fn (array $a, array $b): int => $a[0] <=> $b[0]);
                $merged = [];
                foreach ($ranges as $range) {
                    $last = count($merged) - 1;
                    if ($last >= 0 && $range[0] <= $merged[$last][1]) {
                        $merged[$last][1] = max($range[1], $merged[$last][1]);
                    } else {
                        $merged[] = $range;
                    }
                }
                DB::table('lms_video_progress')->where('id', $target->id)->update(['watched_ranges' => json_encode($merged, JSON_THROW_ON_ERROR)]);
                DB::table('lms_video_progress')->where('id', $source->id)->delete();
            } else {
                DB::table('lms_video_progress')->where('id', $source->id)->update(['student_id' => $primaryId]);
            }
        }
        DB::table('lms_video_progress')->where('student_id', $primaryId)->update(['watch_token_hash' => null, 'lease_id' => null, 'playing' => false]);
        foreach (['lms_quiz_attempts', 'lms_assignment_submissions'] as $table) {
            foreach (DB::table($table)->where('student_id', $secondaryId)->orderBy('number')->get() as $source) {
                $number = 1 + (int) DB::table($table)->where('student_id', $primaryId)->where('block_id', $source->block_id)->where('definition_hash', $source->definition_hash)->max('number');
                DB::table($table)->where('id', $source->id)->update(['student_id' => $primaryId, 'number' => $number, 'request_key' => (string) Str::uuid(), 'lock_version' => $source->lock_version + 1]);
            }
        }
    }

    /** The existing privacy service owns authorization, locks and structural retention. */
    public function erase(int $studentId): void
    {
        $this->requireTransaction();
        DB::table('lms_playback_leases')->where('student_id', $studentId)->delete();
        DB::table('lms_video_progress')->where('student_id', $studentId)->delete();
        foreach (DB::table('lms_assignment_submissions')->where('student_id', $studentId)->whereNotNull('path')->get(['id', 'path']) as $submission) {
            DB::afterCommit(function () use ($submission): void {
                if (! $this->files->discardLearningSubmission($submission->path)) {
                    Log::warning('LMS submission file cleanup requires review.', ['submission_id' => $submission->id]);

                    return;
                }
                DB::table('lms_assignment_submissions')->where('id', $submission->id)->where('status', 'erased')->where('path', $submission->path)
                    ->update(['path' => null, 'mime_type' => null, 'byte_size' => null, 'sha256' => null]);
            });
        }
        DB::table('lms_quiz_attempts')->where('student_id', $studentId)->update(['definition' => '[]', 'answers' => null, 'marks' => null, 'feedback' => null, 'status' => 'erased', 'lock_version' => DB::raw('lock_version + 1')]);
        DB::table('lms_assignment_submissions')->where('student_id', $studentId)->update(['definition' => '[]', 'body' => null, 'url' => null, 'feedback' => null, 'status' => 'erased', 'lock_version' => DB::raw('lock_version + 1')]);
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
