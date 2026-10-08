<?php

namespace App\Domains\Lms\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Booking\Services\LessonMaterialService;
use App\Domains\Lms\Models\AssignmentSubmission;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\TeachingRecordService;
use App\Rules\SafeLessonUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LmsAssignmentService
{
    public function __construct(private LmsProgressService $progress, private LmsLearningDefinition $definitions,
        private TeachingRecordService $students, private LessonMaterialService $files, private AuditLogService $audits) {}

    /** @param array<string,mixed> $data */
    public function submit(Student $student, Course $course, Lesson $lesson, LessonBlock $block, array $data, ?UploadedFile $file): AssignmentSubmission
    {
        $v = Validator::make($data, ['request_key' => ['required', 'uuid'], 'kind' => ['required', Rule::in(['text', 'file', 'audio', 'video', 'external_link'])],
            'body' => ['nullable', 'string', 'max:20000'], 'url' => ['nullable', 'string', 'max:2000']])->validate();
        $path = null;
        try {
            return DB::transaction(function () use ($student, $course, $lesson, $block, $v, $file, &$path): AssignmentSubmission {
                [$student,$course,$lesson] = $this->progress->lockTarget($student, $course, $lesson);
                $block = $lesson->blocks()->where('kind', 'assignment')->where('status', 'ready')->lockForUpdate()->findOrFail($block->id);
                $definition = $this->definitions->assessment('assignment', $block->payload);
                $hash = $this->definitions->hash($definition);
                abort_unless(in_array($v['kind'], $definition['types'], true), 422, 'Choose an allowed submission type.');
                $body = $v['kind'] === 'text' ? ($v['body'] ?? null) : null;
                $url = $v['kind'] === 'external_link' ? ($v['url'] ?? null) : null;
                if ($v['kind'] === 'text' && (! is_string($body) || trim($body) === '')) {
                    $this->invalid('Write your response before submitting.');
                }
                if ($v['kind'] === 'external_link' && (! is_string($url) || ! SafeLessonUrl::isSafe($url))) {
                    $this->invalid('Use a safe HTTPS link without login credentials.');
                }
                $existing = AssignmentSubmission::query()->where('student_id', $student->id)->where('request_key', $v['request_key'])->first();
                if ($existing) {
                    abort_unless($existing->block_id === $block->id && $existing->definition_hash === $hash && $existing->kind === $v['kind']
                        && $existing->body === $body && $existing->url === $url && (! $existing->path || ($file && hash_file('sha256', $file->getRealPath()) === $existing->sha256)), 409);

                    return $existing;
                }
                $previous = AssignmentSubmission::query()->where('student_id', $student->id)->where('block_id', $block->id)->where('definition_hash', $hash)->orderByDesc('number')->first();
                abort_if($previous && $previous->status !== 'needs_revision', 409, 'Your latest submission is already awaiting review or approved.');
                $attachment = [];
                if (in_array($v['kind'], ['file', 'audio', 'video'], true)) {
                    if (! $file) {
                        $this->invalid('Choose a file before submitting.');
                    }
                    $attachment = $this->files->storeLearningSubmission($file, $v['kind']);
                    $path = $attachment['path'];
                }
                $submission = new AssignmentSubmission;
                $submission->forceFill(['student_id' => $student->id, 'block_id' => $block->id, 'definition_hash' => $hash, 'definition' => $definition, 'request_key' => $v['request_key'],
                    'kind' => $v['kind'], 'number' => $previous ? $previous->number + 1 : 1, 'original_number' => $previous ? $previous->number + 1 : 1,
                    'status' => 'submitted', 'body' => $body, 'url' => $url, 'lock_version' => 1] + $attachment)->save();
                $this->progress->evaluate($student, $lesson);
                $this->audits->logStudent($student->id, 'lms_assignment_submitted', AssignmentSubmission::class, $submission->id, null, ['block_id' => $block->id, 'number' => $submission->number, 'kind' => $submission->kind]);
                app(AnalyticsService::class)->recordLearning($student, 'lms_assignment_submitted', 'submission:'.$submission->id,
                    ['course_id' => (int) $course->id, 'lesson_id' => (int) $lesson->id, 'block_id' => (int) $block->id, 'submission_id' => (int) $submission->id], $submission->created_at);

                return $submission;
            });
        } catch (\Throwable $error) {
            if ($path) {
                $this->files->discardLearningSubmission($path);
            }
            throw $error;
        }
    }

    public function review(Administrator $actor, int $id, int $version, string $status, ?string $feedback): AssignmentSubmission
    {
        Gate::forUser($actor)->authorize('manage', Course::class);
        Validator::make(['status' => $status, 'feedback' => $feedback], ['status' => ['required', Rule::in(['under_review', 'needs_revision', 'approved'])], 'feedback' => ['nullable', 'string', 'max:5000']])->validate();
        $original = AssignmentSubmission::query()->findOrFail($id);

        return DB::transaction(function () use ($actor, $original, $version, $status, $feedback): AssignmentSubmission {
            Gate::forUser($actor)->authorize('manage', Course::class);
            $student = $this->students->lockStudent($original->student_id);
            $block = LessonBlock::query()->findOrFail($original->block_id);
            Course::query()->lockForUpdate()->findOrFail($block->lesson->course_id);
            $lesson = Lesson::query()->lockForUpdate()->findOrFail($block->lesson_id);
            $submission = AssignmentSubmission::query()->lockForUpdate()->findOrFail($original->id);
            abort_unless($submission->lock_version === $version && in_array($submission->status, ['submitted', 'under_review'], true), 409);
            abort_if($status === 'under_review' && $submission->status === 'under_review', 409);
            if ($status === 'needs_revision' && trim($feedback ?? '') === '') {
                $this->invalid('Explain the requested revision.');
            }
            $submission->forceFill(['status' => $status, 'reviewed_by' => $actor->id, 'reviewed_at' => now('UTC'), 'feedback' => $feedback, 'lock_version' => $submission->lock_version + 1])->save();
            $this->progress->evaluate($student, $lesson);
            $this->audits->log('lms_assignment_reviewed', AssignmentSubmission::class, $submission->id, null, ['status' => $status, 'version' => $submission->lock_version], $actor->id);

            return $submission;
        }, 3);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['submission' => $message]);
    }
}
