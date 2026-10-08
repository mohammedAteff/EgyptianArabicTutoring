<?php

namespace App\Domains\Lms\Services;

use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Lms\Models\AssignmentSubmission;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Enrollment;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\LessonProgress;
use App\Domains\Lms\Models\QuizAttempt;
use App\Domains\Lms\Models\VideoProgress;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\TeachingRecordService;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LmsProgressService
{
    public function __construct(private LmsLearningDefinition $definitions, private LmsLearningGate $gate,
        private LmsAccessService $access, private TeachingRecordService $students, private AuditLogService $audits) {}

    /** All learning writes use the same Student → Course → Lesson lock order.
     * @return array{Student,Course,Lesson} */
    public function lockTarget(Student $student, Course $course, Lesson $lesson): array
    {
        $student = $this->students->lockStudent($student->id);
        $course = Course::query()->lockForUpdate()->findOrFail($course->id);
        $lesson = Lesson::query()->where('course_id', $course->id)->lockForUpdate()->findOrFail($lesson->id);
        abort_unless($this->access->canAccess($student, $lesson) && $this->gate->decision($student, $lesson)['allowed'], 404);

        return [$student, $course, $lesson];
    }

    public function start(Student $student, Course $course, Lesson $lesson): LessonProgress
    {
        return DB::transaction(function () use ($student, $course, $lesson): LessonProgress {
            [$student,$course,$lesson] = $this->lockTarget($student, $course, $lesson);

            return $this->evaluate($student, $lesson);
        }, 3);
    }

    public function manual(Student $student, Course $course, Lesson $lesson): LessonProgress
    {
        return DB::transaction(function () use ($student, $course, $lesson): LessonProgress {
            [$student,$course,$lesson] = $this->lockTarget($student, $course, $lesson);
            abort_unless(in_array('manual', $this->definitions->lessonRules($lesson)['methods'], true), 422);
            $row = $this->row($student, $lesson);
            if (! $row->manual_completed_at) {
                $row->forceFill(['manual_completed_at' => now('UTC')])->save();
            }

            return $this->evaluate($student, $lesson);
        }, 3);
    }

    /** Called only within a locked learning transaction; review can complete evidence after access expires. */
    public function evaluate(Student $student, Lesson $lesson): LessonProgress
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Learning evidence requires canonical locks.');
        }
        $row = $this->row($student, $lesson);
        $rules = $this->definitions->lessonRules($lesson);
        $blocks = $lesson->blocks()->where('status', 'ready')->with('videoAsset')->get();
        $satisfied = true;
        foreach ($rules['methods'] as $method) {
            $met = match ($method) {
                'manual' => $row->manual_completed_at !== null,
                'video' => $blocks->where('kind', 'video')->isNotEmpty() && $blocks->where('kind', 'video')->every(function (LessonBlock $block) use ($student, $rules): bool {
                    $watch = VideoProgress::query()->where('student_id', $student->id)->where('block_id', $block->id)->where('media_hash', $this->definitions->mediaHash($block))->first();

                    return $watch && $this->watchPercent($watch) >= $rules['video_threshold'];
                }),
                'quiz_complete','quiz_pass' => $blocks->where('kind', 'quiz')->isNotEmpty() && $blocks->where('kind', 'quiz')->every(fn (LessonBlock $block): bool => QuizAttempt::query()->where('student_id', $student->id)->where('block_id', $block->id)->where('definition_hash', $this->definitions->hash($block->payload))
                    ->where('status', 'graded')->when($method === 'quiz_pass', fn ($query) => $query->where('passed', true))->exists()),
                'assignment_submit','assignment_approve' => $blocks->where('kind', 'assignment')->isNotEmpty() && $blocks->where('kind', 'assignment')->every(fn (LessonBlock $block): bool => AssignmentSubmission::query()->where('student_id', $student->id)->where('block_id', $block->id)->where('definition_hash', $this->definitions->hash($block->payload))
                    ->whereIn('status', $method === 'assignment_approve' ? ['approved'] : ['submitted', 'under_review', 'needs_revision', 'approved'])->exists()),
                default => false,
            };
            $satisfied = $satisfied && $met;
        }
        if ($satisfied && ! $row->completed_at) {
            $row->forceFill(['completed_at' => now('UTC')])->save();
            $this->audits->logStudent($student->id, 'lms_lesson_completed', LessonProgress::class, $row->id, null, ['lesson_id' => $lesson->id, 'requirement_hash' => $row->requirement_hash]);
            app(AnalyticsService::class)->recordLearning($student, 'lms_lesson_completed', 'progress:'.$row->id,
                ['course_id' => (int) $lesson->course_id, 'lesson_id' => (int) $lesson->id, 'requirement_hash' => $row->requirement_hash], $row->completed_at);
            $lessons = Lesson::query()->where('course_id', $lesson->course_id)->where('status', 'published')->where('published_at', '<=', now('UTC'))
                ->whereHas('section', fn ($query) => $query->where('status', 'published')->where('published_at', '<=', now('UTC')))->with('blocks.videoAsset')->get();
            $summary = $this->summary($student, $lessons);
            if ($summary['status'] === 'Completed') {
                app(AnalyticsService::class)->recordLearning($student, 'lms_course_completed', $lesson->course_id.':'.$summary['completion_key'],
                    ['course_id' => (int) $lesson->course_id, 'completion_key' => $summary['completion_key']], $summary['completed_at']);
            }
        }

        return $row;
    }

    private function row(Student $student, Lesson $lesson): LessonProgress
    {
        $hash = $this->definitions->requirementHash($lesson);
        $row = LessonProgress::query()->where('student_id', $student->id)->where('lesson_id', $lesson->id)->where('requirement_hash', $hash)->lockForUpdate()->first();
        if (! $row) {
            $started = LessonProgress::query()->where('student_id', $student->id)
                ->whereIn('lesson_id', Lesson::query()->where('course_id', $lesson->course_id)->select('id'))->exists();
            $row = new LessonProgress;
            $row->forceFill(['student_id' => $student->id, 'lesson_id' => $lesson->id, 'requirement_hash' => $hash, 'started_at' => now('UTC')])->save();
            if (! $started) {
                app(AnalyticsService::class)->recordLearning($student, 'lms_course_started', 'course:'.$lesson->course_id,
                    ['course_id' => (int) $lesson->course_id], $row->started_at);
            }
        }

        return $row;
    }

    /** @return array<string,mixed> */
    public function lessonState(Student $student, Lesson $lesson): array
    {
        $row = LessonProgress::query()->where('student_id', $student->id)->where('lesson_id', $lesson->id)->where('requirement_hash', $this->definitions->requirementHash($lesson))->first();

        return ['status' => $row?->completed_at ? 'Completed' : ($row ? 'In Progress' : 'Not Started'),
            'completed' => $row?->completed_at !== null, 'required' => $this->definitions->lessonRules($lesson)['required']] + $this->gate->decision($student, $lesson);
    }

    /** @param Collection<int,Lesson> $lessons
     * @param Collection<int,LessonProgress>|null $retainedRows
     * @param array<int,mixed>|null $enrollmentAnchors
     * @return array<string,mixed> */
    public function summary(Student $student, Collection $lessons, ?Collection $retainedRows = null, ?array $enrollmentAnchors = null): array
    {
        (new \Illuminate\Database\Eloquent\Collection($lessons->all()))->loadMissing('blocks.videoAsset');
        $rows = ($retainedRows ?? LessonProgress::query()->where('student_id', $student->id)->whereIn('lesson_id', $lessons->pluck('id'))->get())->groupBy('lesson_id');
        $current = $lessons->mapWithKeys(function (Lesson $lesson) use ($rows): array {
            $row = $rows->get($lesson->id, collect())->firstWhere('requirement_hash', $this->definitions->requirementHash($lesson));

            return [$lesson->id => $row];
        });
        $completedMap = $current->map(fn (?LessonProgress $row): bool => $row?->completed_at !== null)->all();
        $relativeCourses = $lessons->filter(fn (Lesson $lesson): bool => $this->definitions->lessonRules($lesson)['drip_mode'] === 'relative')->pluck('course_id')->unique();
        $anchors = $enrollmentAnchors ?? Enrollment::query()->where('student_id', $student->id)->whereIn('course_id', $relativeCourses)->get()->groupBy('course_id')
            ->filter(fn (Collection $rows): bool => $rows->contains('status', 'enrolled'))->map(fn (Collection $rows) => $rows->sortBy('enrolled_at')->first()->enrolled_at)->all();
        $states = $lessons->mapWithKeys(function (Lesson $lesson) use ($student, $current, $completedMap, $anchors): array {
            $row = $current[$lesson->id];

            return [$lesson->id => ['status' => $row?->completed_at ? 'Completed' : ($row ? 'In Progress' : 'Not Started'),
                'completed' => $row?->completed_at !== null, 'required' => $this->definitions->lessonRules($lesson)['required']] + $this->gate->decision($student, $lesson, $completedMap, $anchors)];
        });

        return $this->assembleSummary($lessons, $current, $states->all());
    }

    /** Canonical cohort projection consumes a batch of retained rows; no per-Student database reads.
     * @param Collection<int,Lesson> $lessons
     * @param Collection<int,LessonProgress> $rows
     * @return array<string,mixed> */
    public function cohortSummary(Collection $lessons, Collection $rows, ?CarbonInterface $at = null, ?array $hashes = null): array
    {
        $hashes ??= $lessons->mapWithKeys(fn (Lesson $lesson): array => [$lesson->id => $this->definitions->requirementHash($lesson)])->all();
        $rows = $rows->filter(fn (LessonProgress $row): bool => $at === null || $row->started_at->lte($at))->groupBy('lesson_id');
        $current = $lessons->mapWithKeys(fn (Lesson $lesson): array => [$lesson->id => $rows->get($lesson->id, collect())
            ->firstWhere('requirement_hash', $hashes[$lesson->id])]);
        $states = $lessons->mapWithKeys(function (Lesson $lesson) use ($current, $at): array {
            $row = $current[$lesson->id];
            $complete = $row?->completed_at !== null && ($at === null || $row->completed_at->lte($at));

            return [$lesson->id => ['status' => $complete ? 'Completed' : ($row ? 'In Progress' : 'Not Started'),
                'completed' => $complete, 'required' => $this->definitions->lessonRules($lesson)['required'], 'allowed' => false]];
        });

        return $this->assembleSummary($lessons, $current, $states->all());
    }

    /** @param Collection<int,Lesson> $lessons
     * @param Collection<int,LessonProgress|null> $current
     * @param array<int,array<string,mixed>> $states
     * @return array<string,mixed> */
    private function assembleSummary(Collection $lessons, Collection $current, array $states): array
    {
        $states = collect($states);
        $required = $states->filter(fn (array $state): bool => $state['required']);
        $completed = $required->filter(fn (array $state): bool => $state['completed'])->count();
        $done = $required->isNotEmpty() && $completed === $required->count();
        $requiredRows = $current->filter(fn (?LessonProgress $row, int $lessonId): bool => $required->has($lessonId));

        return ['status' => $done ? 'Completed' : ($states->contains(fn (array $s): bool => $s['status'] !== 'Not Started') ? 'In Progress' : 'Not Started'),
            'percent' => $required->isEmpty() ? 0 : (int) floor(100 * $completed / $required->count()), 'completed' => $completed, 'required' => $required->count(),
            'completed_at' => $done ? $requiredRows->max('completed_at') : null,
            'completion_key' => $done ? hash('sha256', json_encode($requiredRows->sortKeys()->map(fn (LessonProgress $row): string => $row->requirement_hash)->all(), JSON_THROW_ON_ERROR)) : null,
            'started_at' => $current->filter()->min('started_at'), 'last_completed_at' => $current->filter()->max('completed_at'),
            'lessons' => $states, 'next' => $lessons->first(fn (Lesson $l): bool => $states[$l->id]['allowed'] && ! $states[$l->id]['completed'])];
    }

    public function watchPercent(VideoProgress $watch): float
    {
        $milliseconds = array_sum(array_map(fn (array $range): int => $range[1] - $range[0], $watch->watched_ranges));

        return min(100, 100 * $milliseconds / max(1, $watch->duration_milliseconds));
    }

    /** @return array<string,mixed> */
    public function beginWatch(Request $request, Student $student, Course $course, Lesson $lesson, LessonBlock $block): array
    {
        return DB::transaction(function () use ($request, $student, $course, $lesson, $block): array {
            [$student,$course,$lesson] = $this->lockTarget($student, $course, $lesson);
            $block = $lesson->blocks()->lockForUpdate()->findOrFail($block->id);
            $lease = app(ProtectedPlaybackService::class)->learningProof($request, $student, $block);
            abort_unless($block->videoAsset?->provider === 'bunny' && $block->videoAsset->duration_seconds > 0, 422);
            $hash = $this->definitions->mediaHash($block);
            $watch = VideoProgress::query()->where('student_id', $student->id)->where('block_id', $block->id)->where('media_hash', $hash)->lockForUpdate()->first() ?? new VideoProgress;
            $token = bin2hex(random_bytes(32));
            $watch->forceFill(['student_id' => $student->id, 'block_id' => $block->id, 'media_hash' => $hash, 'duration_milliseconds' => (int) round($block->videoAsset->duration_seconds * 1000),
                'watched_ranges' => $watch->watched_ranges ?? [], 'watch_token_hash' => hash('sha256', $token), 'lease_id' => $lease->id,
                'position_milliseconds' => $watch->position_milliseconds ?? 0, 'playing' => false, 'sequence' => 0, 'sampled_at' => now('UTC')])->save();
            $this->evaluate($student, $lesson);

            return ['id' => $watch->id, 'watch_token' => $token, 'resume_seconds' => $watch->position_milliseconds / 1000, 'percent' => $this->watchPercent($watch), 'sequence' => 0];
        }, 3);
    }

    /** @return array<string,mixed> */
    public function sampleWatch(Request $request, Student $student, Course $course, Lesson $lesson, LessonBlock $block, int $id): array
    {
        $v = Validator::make($request->only(['position', 'mode', 'sequence', 'watch_token']), [
            'position' => ['required', 'numeric', 'between:0,86400'], 'mode' => ['required', Rule::in(['playing', 'anchor'])],
            'sequence' => ['required', 'integer', 'min:1'], 'watch_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']])->validate();

        return DB::transaction(function () use ($request, $student, $course, $lesson, $block, $id, $v): array {
            [$student,$course,$lesson] = $this->lockTarget($student, $course, $lesson);
            $block = $lesson->blocks()->lockForUpdate()->findOrFail($block->id);
            $watch = VideoProgress::query()->where('student_id', $student->id)->where('block_id', $block->id)->lockForUpdate()->findOrFail($id);
            abort_unless($watch->media_hash === $this->definitions->mediaHash($block) && hash_equals((string) $watch->watch_token_hash, hash('sha256', $v['watch_token'])), 404);
            $lease = app(ProtectedPlaybackService::class)->learningProof($request, $student, $block);
            abort_unless($lease->id === $watch->lease_id, 404);
            if ((int) $v['sequence'] === $watch->sequence) {
                return ['percent' => $this->watchPercent($watch), 'sequence' => $watch->sequence];
            }
            abort_unless((int) $v['sequence'] === $watch->sequence + 1, 409);
            $position = (int) round((float) $v['position'] * 1000);
            abort_if($position > $watch->duration_milliseconds, 422);
            $elapsed = $watch->sampled_at ? max(0, $watch->sampled_at->diffInMilliseconds(now('UTC'))) : 0;
            $advance = $position - $watch->position_milliseconds;
            $ranges = $watch->watched_ranges;
            // Credit only forward, continuous 1x playback within a fresh bounded batch.
            if ($watch->playing && $v['mode'] === 'playing' && $advance > 0 && $advance <= 20000 && $elapsed <= 25000 && $advance <= $elapsed + 500) {
                $creditedEnd = min($position, $watch->position_milliseconds + (int) floor($elapsed));
                if ($creditedEnd > $watch->position_milliseconds) {
                    $ranges[] = [$watch->position_milliseconds, $creditedEnd];
                }
                usort($ranges, fn (array $a, array $b): int => $a[0] <=> $b[0]);
                $merged = [];
                foreach ($ranges as $range) {
                    $last = count($merged) - 1;
                    if ($last >= 0 && $range[0] <= $merged[$last][1]) {
                        $merged[$last][1] = max($merged[$last][1], $range[1]);
                    } else {
                        $merged[] = $range;
                    }
                }
                abort_if(count($merged) > 10000, 422, 'Too many fragmented watch ranges. Continue from a saved position.');
                $ranges = $merged;
            }
            $watch->forceFill(['watched_ranges' => $ranges, 'position_milliseconds' => $position, 'playing' => $v['mode'] === 'playing',
                'sequence' => (int) $v['sequence'], 'sampled_at' => now('UTC')])->save();
            $progress = $this->evaluate($student, $lesson);

            return ['percent' => $this->watchPercent($watch), 'sequence' => $watch->sequence, 'completed' => $progress->completed_at !== null];
        }, 3);
    }
}
