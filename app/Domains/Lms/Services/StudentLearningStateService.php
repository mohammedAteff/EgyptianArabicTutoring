<?php

namespace App\Domains\Lms\Services;

use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\LearningVisit;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBookmark;
use App\Domains\Lms\Models\LessonNote;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\TeachingRecordService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StudentLearningStateService
{
    public function __construct(private TeachingRecordService $records, private LmsAccessService $access, private LmsContentService $content) {}

    public function visit(Student $student, Course $course, ?Lesson $lesson = null): void
    {
        DB::transaction(function () use ($student, $course, $lesson): void {
            [$student, $course, $lesson] = $this->lockTarget($student, $course, $lesson);
            $visit = LearningVisit::query()->where('student_id', $student->id)->where('course_id', $course->id)->lockForUpdate()->first() ?? new LearningVisit;
            $visit->forceFill(['student_id' => $student->id, 'course_id' => $course->id, 'accessed_at' => now('UTC')]);
            if ($lesson) {
                $visit->lesson_id = $lesson->id;
            }
            $visit->save();
        }, 3);
    }

    public function saveNote(Student $student, Course $course, Lesson $lesson, string $body, ?int $noteId = null, ?int $version = null): LessonNote
    {
        return DB::transaction(function () use ($student, $course, $lesson, $body, $noteId, $version): LessonNote {
            [$student, $course, $lesson] = $this->lockTarget($student, $course, $lesson);
            $note = $noteId ? LessonNote::query()->where('student_id', $student->id)->where('lesson_id', $lesson->id)->lockForUpdate()->findOrFail($noteId) : new LessonNote;
            if ($noteId) {
                abort_unless($note->lock_version === $version, 409, 'This note changed. Reload before editing.');
            }
            $values = Validator::make(['body' => $body], ['body' => ['required', 'string', 'max:10000']])->validate();
            $note->forceFill(['student_id' => $student->id, 'lesson_id' => $lesson->id, 'body' => $values['body'], 'lock_version' => $noteId ? $note->lock_version + 1 : 1])->save();

            return $note;
        }, 3);
    }

    /** Owners may remove their private text even after course access ends. */
    public function deleteNote(Student $student, int $noteId, int $version): void
    {
        DB::transaction(function () use ($student, $noteId, $version): void {
            $student = $this->records->lockStudent($student->id);
            $note = LessonNote::query()->where('student_id', $student->id)->lockForUpdate()->findOrFail($noteId);
            abort_unless($note->lock_version === $version, 409, 'This note changed. Reload before deleting.');
            $note->delete();
        }, 3);
    }

    public function bookmark(Student $student, Course $course, Lesson $lesson, int $blockId, float $seconds, ?string $label): LessonBookmark
    {
        return DB::transaction(function () use ($student, $course, $lesson, $blockId, $seconds, $label): LessonBookmark {
            [$student, $course, $lesson] = $this->lockTarget($student, $course, $lesson);
            $block = $lesson->blocks()->lockForUpdate()->findOrFail($blockId);
            abort_unless($this->access->canAccess($student, $block), 404);
            $data = $this->content->normalize($course, $this->content->input($block->only(['kind', 'resource_id', 'asset_id', 'video_asset_id', 'payload'])), [], $block->video_asset_id ? [(int) $block->video_asset_id] : []);
            $timestampSupported = $data['kind'] === 'external_video' && ($data['payload']['provider'] ?? null) === 'direct';
            if ($block->kind === 'video' && $block->videoAsset) {
                $video = $block->videoAsset;
                $timestampSupported = $video->provider === 'bunny';
                if ($video->provider === 'external') {
                    $external = $this->content->normalize($course, ['kind' => 'external_video', 'url' => $video->external_url]);
                    $timestampSupported = ($external['payload']['provider'] ?? null) === 'direct';
                }
            }
            if (! $timestampSupported) {
                throw ValidationException::withMessages(['block_id' => 'Timestamp bookmarks are available for direct videos.']);
            }
            if (! is_finite($seconds) || $seconds < 0 || $seconds > 86400) {
                throw ValidationException::withMessages(['seconds' => 'Choose a video timestamp between 0 and 86400 seconds.']);
            }
            if ($block->kind === 'video' && $block->videoAsset?->provider === 'bunny' && $seconds > ($block->videoAsset->duration_seconds ?? 0)) {
                throw ValidationException::withMessages(['seconds' => 'Choose a timestamp within this video.']);
            }
            $label = Validator::make(['label' => $label], ['label' => ['nullable', 'string', 'max:500']])->validate()['label'];
            $position = (int) round($seconds * 1000);
            $bookmark = LessonBookmark::query()->where('student_id', $student->id)->where('block_id', $block->id)->where('position_milliseconds', $position)->lockForUpdate()->first() ?? new LessonBookmark;
            $bookmark->forceFill(['student_id' => $student->id, 'lesson_id' => $lesson->id, 'block_id' => $block->id, 'position_milliseconds' => $position, 'label' => $label])->save();

            return $bookmark;
        }, 3);
    }

    public function deleteBookmark(Student $student, int $bookmarkId): void
    {
        DB::transaction(function () use ($student, $bookmarkId): void {
            $student = $this->records->lockStudent($student->id);
            LessonBookmark::query()->where('student_id', $student->id)->lockForUpdate()->findOrFail($bookmarkId)->delete();
        }, 3);
    }

    /** Canonical Student → Course ordering serializes against grants, publishing, merge and erasure.
     * @return array{Student,Course,Lesson|null}
     */
    private function lockTarget(Student $student, Course $course, ?Lesson $lesson): array
    {
        $student = $this->records->lockStudent($student->id);
        $course = Course::query()->lockForUpdate()->findOrFail($course->id);
        $lesson = $lesson ? Lesson::query()->where('course_id', $course->id)->lockForUpdate()->findOrFail($lesson->id) : null;
        abort_unless($this->access->canAccess($student, $lesson ?? $course), 404);

        return [$student, $course, $lesson];
    }
}
