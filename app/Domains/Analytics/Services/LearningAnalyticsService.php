<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\AssignmentSubmission;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Enrollment;
use App\Domains\Lms\Models\LearningVisit;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\LessonProgress;
use App\Domains\Lms\Models\QuizAttempt;
use App\Domains\Lms\Models\VideoProgress;
use App\Domains\Lms\Services\LmsAccessService;
use App\Domains\Lms\Services\LmsLearningDefinition;
use App\Domains\Lms\Services\LmsProgressService;
use Carbon\CarbonInterface;

class LearningAnalyticsService
{
    public function __construct(private LmsProgressService $progress, private LmsLearningDefinition $definitions, private LmsAccessService $access) {}

    /** Current retained enrollment cohorts, streamed in bounded batches. Definitions are hashed once per curriculum.
     * @param array<string,mixed> $filters
     * @return \Generator<int,array<string,mixed>> */
    public function cohorts(CarbonInterface $end, array $filters = [], ?CarbonInterface $start = null): \Generator
    {
        $courses = Course::query()->where('status', 'published')->where('published_at', '<=', $end)
            ->when(! empty($filters['course_id']), fn ($q) => $q->whereKey((int) $filters['course_id']))->with('accessRule')->orderBy('id')->lazyById(10);
        foreach ($courses as $course) {
            $lessons = Lesson::query()->where('course_id', $course->id)->where('status', 'published')->where('published_at', '<=', $end)
                ->whereHas('section', fn ($q) => $q->where('status', 'published')->where('published_at', '<=', $end))
                ->with(['section', 'blocks.videoAsset.course'])->orderBy('sort_order')->orderBy('id')->get();
            $blocks = $lessons->flatMap(fn (Lesson $lesson) => $lesson->blocks);
            $hashes = $lessons->mapWithKeys(fn (Lesson $lesson): array => [$lesson->id => $this->definitions->requirementHash($lesson)])->all();
            $blockHashes = $blocks->where('status', 'ready')->where('kind', 'quiz')->mapWithKeys(fn (LessonBlock $block): array => [$block->id => $this->definitions->hash($block->payload)])->all();
            $query = Enrollment::query()->where('course_id', $course->id)->where('status', 'enrolled')->where('enrolled_at', '<=', $end)
                ->when($course->kind === 'private', fn ($q) => $q->where('student_id', $course->owner_student_id))
                ->when(! empty($filters['student_id']), fn ($q) => $q->where('student_id', (int) $filters['student_id']))
                ->whereHas('student', fn ($q) => $q->verified()->whereNull('suspended_at')->whereNull('merged_into_student_id'))
                ->with('student')->orderBy('id');
            foreach ($query->lazyById(200)->chunk(200) as $chunk) {
                $batch = collect($chunk->all())->unique('student_id');
                $studentIds = $batch->pluck('student_id')->all();
                $students = $batch->pluck('student');
                $enrollments = Enrollment::query()->where('course_id', $course->id)->whereIn('student_id', $studentIds)->get();
                $grants = AccessGrant::query()->where('course_id', $course->id)->whereIn('student_id', $studentIds)->with(['learningAssignment.booking', 'lesson:id,section_id'])->get();
                $decisions = $this->access->cohortDecisions($course, $students, $enrollments, $grants);
                $rows = LessonProgress::query()->whereIn('student_id', $studentIds)->whereIn('lesson_id', $lessons->pluck('id'))->get()->toBase()->groupBy('student_id');
                $visits = LearningVisit::query()->where('course_id', $course->id)->whereIn('student_id', $studentIds)->get()->keyBy('student_id');
                $quizRows = $start ? QuizAttempt::query()->whereIn('student_id', $studentIds)->whereIn('block_id', $blocks->pluck('id'))->where('status', '!=', 'erased')
                    ->whereBetween('submitted_at', [$start, $end])->get(['id', 'student_id', 'block_id', 'definition_hash', 'number', 'status', 'score', 'passed', 'submitted_at', 'graded_at', 'created_at', 'updated_at'])->toBase()->groupBy('student_id') : collect();
                $submissions = $start ? AssignmentSubmission::query()->whereIn('student_id', $studentIds)->whereIn('block_id', $blocks->pluck('id'))->where('status', '!=', 'erased')
                    ->whereBetween('created_at', [$start, $end])->get(['id', 'student_id', 'block_id', 'definition_hash', 'number', 'status', 'reviewed_at', 'created_at', 'updated_at'])->toBase()->groupBy('student_id') : collect();
                $watches = $start ? VideoProgress::query()->whereIn('student_id', $studentIds)->whereIn('block_id', $blocks->pluck('id'))
                    ->whereBetween('created_at', [$start, $end])->get()->toBase()->groupBy('student_id') : collect();
                $failures = collect();
                $lastSubmissions = collect();
                $lastQuizzes = collect();
                if (! $start) {
                    $lastQuizzes = QuizAttempt::query()->whereIn('student_id', $studentIds)->whereIn('block_id', $blocks->pluck('id'))->where('status', '!=', 'erased')
                        ->whereNotNull('submitted_at')->toBase()->select('student_id')->selectRaw('MAX(submitted_at) AS submitted_at')->groupBy('student_id')->get()->keyBy('student_id');
                    if ($blockHashes) {
                        $failures = QuizAttempt::query()->whereIn('student_id', $studentIds)->where('status', 'graded')->where(function ($query) use ($blockHashes): void {
                            foreach ($blockHashes as $blockId => $hash) {
                                $query->orWhere(fn ($q) => $q->where('block_id', $blockId)->where('definition_hash', $hash));
                            }
                        })->toBase()->select('student_id', 'block_id')->selectRaw('SUM(CASE WHEN passed = 0 THEN 1 ELSE 0 END) AS failures, SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) AS passes')
                            ->groupBy('student_id', 'block_id')->get()->groupBy('student_id');
                    }
                    $lastSubmissions = AssignmentSubmission::query()->whereIn('student_id', $studentIds)->whereIn('block_id', $blocks->pluck('id'))->where('status', '!=', 'erased')
                        ->toBase()->select('student_id')->selectRaw('MAX(created_at) AS submitted_at')->groupBy('student_id')->get()->keyBy('student_id');
                }
                foreach ($batch as $enrollment) {
                    $studentRows = $rows->get($enrollment->student_id, collect());
                    yield ['course' => $course, 'student' => $enrollment->student, 'enrollment' => $enrollment, 'lessons' => $lessons, 'blocks' => $blocks,
                        'progress' => $this->progress->cohortSummary($lessons, $studentRows, $end, $hashes),
                        'visit' => $visits->get($enrollment->student_id), 'quizzes' => $quizRows->get($enrollment->student_id, collect()),
                        'submissions' => $submissions->get($enrollment->student_id, collect()), 'watches' => $watches->get($enrollment->student_id, collect()),
                        'quiz_hashes' => $blockHashes, 'access' => $decisions[$enrollment->student_id], 'grants' => $grants->where('student_id', $enrollment->student_id),
                        'failures' => $failures->get($enrollment->student_id, collect()), 'last_submission_at' => $lastSubmissions->get($enrollment->student_id)?->submitted_at,
                        'last_quiz_at' => $lastQuizzes->get($enrollment->student_id)?->submitted_at];
                }
            }
        }
    }

    /** @param array<string,mixed> $filters
     * @return array<string,mixed> */
    public function report(CarbonInterface $start, CarbonInterface $end, array $filters = []): array
    {
        $totals = $this->emptyCounts();
        $courses = [];
        $videos = [];
        $active = [];
        $scoreSum = 0;
        $scoreCount = 0;
        foreach ($this->cohorts($end, $filters, $start) as $cohort) {
            $course = $cohort['course'];
            $state = $cohort['progress'];
            $student = $cohort['student'];
            $row = $courses[$course->id] ?? ['course' => $course] + $this->emptyCounts() + ['progress_sum' => 0, 'score_sum' => 0, 'score_count' => 0];
            $row['enrolled']++;
            $row['active_enrollments'] += (int) $cohort['access']['allowed'];
            $row['started'] += (int) ($state['status'] !== 'Not Started');
            $row['completed'] += (int) ($state['status'] === 'Completed');
            $row['lessons_completed'] += $state['lessons']->where('completed', true)->count();
            $row['progress_sum'] += $state['percent'];
            if ($cohort['visit'] && $cohort['visit']->accessed_at->betweenIncluded($start, $end)) {
                $active[$student->id] = true;
            }
            foreach ($cohort['quizzes'] as $attempt) {
                if (! $attempt->submitted_at || ! $attempt->submitted_at->betweenIncluded($start, $end)) {
                    continue;
                }
                $row['quiz_attempts']++;
                $graded = $attempt->status === 'graded' && ($attempt->graded_at ?? $attempt->updated_at)->lte($end);
                $row['quiz_passes'] += (int) ($graded && $attempt->passed);
                if ($graded) {
                    $row['score_sum'] += (float) $attempt->score;
                    $row['score_count']++;
                    $scoreSum += (float) $attempt->score;
                    $scoreCount++;
                }
            }
            foreach ($cohort['submissions'] as $submission) {
                if (! $submission->created_at || ! $submission->created_at->betweenIncluded($start, $end)) {
                    continue;
                }
                $row['assignments_submitted']++;
                $row['assignments_reviewed'] += (int) (in_array($submission->status, ['under_review', 'needs_revision', 'approved'], true)
                    && ($submission->reviewed_at ?? $submission->updated_at)->lte($end));
                $row['assignments_awaiting_review'] += (int) in_array($submission->status, ['submitted', 'under_review'], true);
            }
            foreach ($cohort['blocks']->where('kind', 'video')->where('status', 'ready') as $block) {
                $asset = $block->videoAsset;
                if (! $asset || $asset->provider !== 'bunny' || ! $asset->usableFor($course)) {
                    continue;
                }
                $watch = $cohort['watches']->first(fn (VideoProgress $watch): bool => $watch->block_id === $block->id
                    && $watch->media_hash === $this->definitions->mediaHash($block) && $watch->created_at?->betweenIncluded($start, $end));
                $video = $videos[$block->id] ?? ['block_id' => $block->id, 'course' => $course, 'label' => $asset->label, 'lesson_id' => $block->lesson_id,
                    'started' => 0, '25' => 0, '50' => 0, '75' => 0, 'completed' => 0, 'percent_sum' => 0, 'seconds_sum' => 0];
                if ($watch) {
                    $percent = $this->progress->watchPercent($watch);
                    $video['started']++;
                    foreach ([25, 50, 75] as $threshold) {
                        $video[(string) $threshold] += (int) ($percent >= $threshold);
                    }
                    $lesson = $cohort['lessons']->firstWhere('id', $block->lesson_id);
                    $video['completed'] += (int) ($percent >= $this->definitions->lessonRules($lesson)['video_threshold']);
                    $video['percent_sum'] += $percent;
                    $video['seconds_sum'] += min($watch->duration_milliseconds, array_sum(array_map(fn (array $range): int => $range[1] - $range[0], $watch->watched_ranges))) / 1000;
                }
                $videos[$block->id] = $video;
            }
            foreach (array_keys($totals) as $key) {
                $totals[$key] += $row[$key] - ($courses[$course->id][$key] ?? 0);
            }
            $courses[$course->id] = $row;
        }
        $courseRows = collect($courses)->map(function (array $row): array {
            $row['completion_rate'] = $row['enrolled'] ? round(100 * $row['completed'] / $row['enrolled'], 1) : 0;
            $row['average_progress'] = $row['enrolled'] ? (int) floor($row['progress_sum'] / $row['enrolled']) : 0;
            $row['quiz_average'] = $row['score_count'] ? round($row['score_sum'] / $row['score_count'], 1) : null;
            unset($row['score_sum'],$row['score_count'],$row['progress_sum']);

            return $row;
        })->values();
        $videoRows = collect($videos)->map(function (array $row): array {
            $row['average_percent'] = $row['started'] ? round($row['percent_sum'] / $row['started'], 1) : 0;
            $row['average_seconds'] = $row['started'] ? (int) round($row['seconds_sum'] / $row['started']) : 0;
            unset($row['percent_sum'],$row['seconds_sum']);

            return $row;
        })->values();
        $totals['active_learners'] = count($active);
        $totals['completion_rate'] = $totals['enrolled'] ? round(100 * $totals['completed'] / $totals['enrolled'], 1) : 0;
        $totals['quiz_average'] = $scoreCount ? round($scoreSum / $scoreCount, 1) : null;

        return ['summary' => $totals, 'courses' => $courseRows, 'videos' => $videoRows, 'cohort' => 'Current verified retained enrollments; current curriculum and evidence through the selected end. Dated activity/submissions use the selected period. Video coverage is current for placements started in the period.'];
    }

    /** @return array<string,int> */
    private function emptyCounts(): array
    {
        return ['enrolled' => 0, 'active_enrollments' => 0, 'started' => 0, 'completed' => 0, 'lessons_completed' => 0,
            'quiz_attempts' => 0, 'quiz_passes' => 0, 'assignments_submitted' => 0, 'assignments_reviewed' => 0, 'assignments_awaiting_review' => 0];
    }
}
