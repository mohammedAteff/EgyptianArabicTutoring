<?php

namespace App\Domains\Lms\Services;

use App\Domains\Analytics\Services\LearningAnalyticsService;
use App\Domains\Lms\Models\AssignmentSubmission;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\QuizAttempt;
use App\Domains\Lms\Models\VideoAsset;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

class LmsOperationsService
{
    public function __construct(private LearningAnalyticsService $analytics, private LmsSettings $settings) {}

    /** @param array<string,mixed> $filters */
    public function attention(array $filters = [], int $page = 1): LengthAwarePaginator
    {
        $options = $this->settings->values();
        $now = CarbonImmutable::now('UTC');
        $seen = [];
        $items = [];
        $offset = ($page - 1) * 20;
        foreach ($this->analytics->cohorts($now, $filters) as $cohort) {
            if (! $cohort['access']['allowed'] || $cohort['progress']['status'] === 'Completed') {
                continue;
            }
            $state = $cohort['progress'];
            $flags = [];
            $activity = collect([$cohort['visit']?->accessed_at, $state['started_at'], $state['last_completed_at'],
                $cohort['last_quiz_at'] ? CarbonImmutable::parse($cohort['last_quiz_at'], 'UTC') : null,
                $cohort['last_submission_at'] ? CarbonImmutable::parse($cohort['last_submission_at'], 'UTC') : null])->filter()->sort()->last()
                ?? $cohort['enrollment']->enrolled_at;
            if ($activity->lte($now->subSeconds((int) $options['inactivity_days'] * 86400))) {
                $flags['inactive'] = 'No learning activity for '.$options['inactivity_days'].' days';
            }
            $lastCompletion = $state['last_completed_at'] ?? $state['started_at'];
            if ($state['status'] === 'In Progress' && $lastCompletion && $lastCompletion->lte($now->subSeconds((int) $options['stalled_days'] * 86400))) {
                $flags['stalled'] = 'No new lesson completion for '.$options['stalled_days'].' days';
            }
            if ($cohort['failures']->contains(fn ($failure): bool => (int) $failure->failures >= (int) $options['quiz_failures'] && (int) $failure->passes === 0)) {
                $flags['quiz_failures'] = 'Repeated quiz failures without a current pass';
            }
            $ends = $cohort['access']['ends_at'];
            if ($ends && $ends->gt($now) && $ends->lte($now->addSeconds((int) $options['expiry_days'] * 86400)) && $state['percent'] < (int) $options['low_progress_percent']) {
                $flags['expiring'] = 'Access ending soon with low progress';
            }
            if ($cohort['course']->kind === 'private' && ! $cohort['visit'] && $state['status'] === 'Not Started') {
                foreach ($cohort['grants'] as $grant) {
                    $assignment = $grant->learningAssignment;
                    if ($grant->status !== 'active' || ! $assignment || $assignment->status !== 'assigned' || $grant->starts_at->isFuture() || ($grant->expires_at && $grant->expires_at->lte($now))) {
                        continue;
                    }
                    $available = CarbonImmutable::instance($grant->starts_at)->max($assignment->created_at);
                    if ($available->lte($now->subSeconds((int) $options['private_unopened_days'] * 86400))) {
                        $flags['private_unopened'] = 'Private follow-up not opened';
                        break;
                    }
                }
            }
            if (! $flags) {
                continue;
            }
            $studentId = $cohort['student']->id;
            if (! isset($seen[$studentId])) {
                $seen[$studentId] = count($seen);
                if ($seen[$studentId] >= $offset && $seen[$studentId] < $offset + 20) {
                    $items[$studentId] = ['student' => $cohort['student'], 'reasons' => []];
                }
            }
            if (isset($items[$studentId])) {
                foreach ($flags as $key => $label) {
                    $items[$studentId]['reasons'][$cohort['course']->id.':'.$key] = ['label' => $label, 'course' => $cohort['course'], 'percent' => $state['percent'], 'ends_at' => $ends];
                }
            }
        }

        return new LengthAwarePaginator(array_values($items), count($seen), 20, $page, ['path' => request()->url(), 'query' => request()->query()]);
    }

    /** @param array<string,mixed> $filters
     * @return array<string,mixed> */
    public function reviews(array $filters = []): array
    {
        $blockIds = LessonBlock::query()->when(! empty($filters['course_id']), fn ($q) => $q->whereHas('lesson', fn ($lessons) => $lessons->where('course_id', (int) $filters['course_id'])))->select('id');
        $quizzes = QuizAttempt::query()->whereIn('block_id', $blockIds)->where('status', 'pending_review')
            ->when(! empty($filters['student_id']), fn ($q) => $q->where('student_id', (int) $filters['student_id']))
            ->whereHas('student', fn ($q) => $q->verified()->whereNull('merged_into_student_id'))
            ->with(['student:id,first_name,last_name', 'block.lesson.course'])->orderBy('id')->paginate(20, ['id', 'student_id', 'block_id', 'number', 'status', 'created_at'], 'quizzes');
        $assignments = AssignmentSubmission::query()->whereIn('block_id', $blockIds)->whereIn('status', ['submitted', 'under_review'])
            ->when(! empty($filters['student_id']), fn ($q) => $q->where('student_id', (int) $filters['student_id']))
            ->whereHas('student', fn ($q) => $q->verified()->whereNull('merged_into_student_id'))
            ->with(['student:id,first_name,last_name', 'block.lesson.course'])->orderBy('id')->paginate(20, ['id', 'student_id', 'block_id', 'number', 'status', 'created_at'], 'assignments');
        $videos = VideoAsset::query()->where('provider', 'bunny')->whereIn('status', ['uploading', 'processing', 'failed', 'delete_failed', 'create_unconfirmed'])
            ->when(! empty($filters['course_id']), fn ($q) => $q->where('course_id', (int) $filters['course_id']))->with('course')->orderBy('id')
            ->paginate(20, ['id', 'course_id', 'label', 'status', 'created_at', 'updated_at'], 'videos');

        return ['quizzes' => $quizzes, 'assignments' => $assignments, 'videos' => $videos];
    }
}
