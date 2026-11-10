<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Lms\Models\LessonProgress;
use App\Domains\Lms\Services\LmsAccessService;
use App\Domains\Lms\Services\LmsProgressService;
use App\Domains\Students\Models\Student;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class StudentInsightsReadModel
{
    /** Metric identifiers, category, unit and retained-source reliability. */
    public const DEFINITIONS = [
        'sessions_completed' => ['sessions', 'sessions', 'A'],
        'session_duration_seconds' => ['sessions', 'seconds', 'A'],
        'homework_assigned' => ['homework', 'items', 'A'],
        'homework_submission_records' => ['homework', 'records', 'B'],
        'homework_items_submitted' => ['homework', 'items', 'B'],
        'lms_assignment_submissions' => ['lms', 'records', 'A'],
        'lms_assignment_activities_submitted' => ['lms', 'activities', 'A'],
        'quiz_attempts_submitted' => ['lms', 'attempts', 'A'],
        'quiz_activities_submitted' => ['lms', 'activities', 'A'],
        'lessons_first_completed' => ['lms', 'lessons', 'A'],
    ];

    public function __construct(private LmsAccessService $access, private LmsProgressService $progress) {}

    /** @return EloquentBuilder<Student> */
    public function population(?int $studentId = null): EloquentBuilder
    {
        return Student::query()->whereNull('merged_into_student_id')->where('identity_status', '<>', 'merged')
            ->when($studentId, fn (EloquentBuilder $query): EloquentBuilder => $query->whereKey($studentId));
    }

    /** Same visibility and booking ownership boundary as StudentTeachingReadModel::shared. */
    private function homework(?int $studentId): Builder
    {
        return DB::table('homeworks as h')->whereIn('h.student_id', $this->population($studentId)->select('id'))
            ->where('h.student_visible', true)->where(fn (Builder $query): Builder => $query->whereNull('h.booking_id')
            ->orWhereExists(fn (Builder $booking): Builder => $booking->selectRaw('1')->from('bookings as hb')
                ->whereColumn('hb.id', 'h.booking_id')->whereColumn('hb.student_id', 'h.student_id')->whereNull('hb.deleted_at')));
    }

    /** Retained evidence survives access withdrawal; structurally inconsistent private ownership does not. */
    private function lms(string $table, ?int $studentId, ?string $kind): Builder
    {
        $query = DB::table($table.' as e')->whereIn('e.student_id', $this->population($studentId)->select('id'));
        if ($kind !== null) {
            $query->join('lms_lesson_blocks as b', 'b.id', '=', 'e.block_id')->where('b.kind', $kind)
                ->join('lms_lessons as l', 'l.id', '=', 'b.lesson_id');
        } else {
            $query->join('lms_lessons as l', 'l.id', '=', 'e.lesson_id');
        }

        return $query->join('lms_sections as s', function ($join): void {
            $join->on('s.id', '=', 'l.section_id')->on('s.course_id', '=', 'l.course_id');
        })->join('lms_courses as c', 'c.id', '=', 'l.course_id')
            ->where(fn (Builder $owner): Builder => $owner->where('c.kind', 'catalog')->whereNull('c.owner_student_id')
                ->orWhere(fn (Builder $private): Builder => $private->where('c.kind', 'private')->whereColumn('c.owner_student_id', 'e.student_id')));
    }

    /** Safe scalar projection only. No authored content, assessment definitions, contact or finance columns. */
    public function records(StudentInsightsContext $context): Builder
    {
        $queries = [];
        if (in_array($context->category, ['all', 'sessions'], true)) {
            $queries[] = DB::table('bookings as e')->whereNull('e.deleted_at')->where('e.status', 'completed')
                ->whereIn('e.student_id', $this->population($context->studentId)->select('id'))
                ->where('e.start_at_utc', '>=', $context->from)->where('e.start_at_utc', '<', $context->effectiveUntil())
                ->selectRaw("e.student_id, 'session' as record_type, e.id as record_id, e.id as item_id, e.start_at_utc as occurred_at, NULL as date_only, e.status, CASE WHEN e.end_at_utc > e.start_at_utc THEN TIMESTAMPDIFF(SECOND, e.start_at_utc, e.end_at_utc) ELSE NULL END as duration_seconds, e.start_at_utc as interval_start, e.end_at_utc as interval_end, NULL as course_id, NULL as lesson_id");
        }
        if (in_array($context->category, ['all', 'homework'], true)) {
            $queries[] = $this->homework($context->studentId)->whereBetween('h.assigned_date', [$context->startDate, min($context->endDate, $context->generatedAt->setTimezone($context->timezone)->toDateString())])
                ->selectRaw("h.student_id, 'homework_assigned' as record_type, h.id as record_id, h.id as item_id, NULL as occurred_at, h.assigned_date as date_only, h.status, NULL as duration_seconds, NULL as interval_start, NULL as interval_end, NULL as course_id, NULL as lesson_id");
            $queries[] = $this->homework($context->studentId)->join('homework_submissions as e', 'e.homework_id', '=', 'h.id')
                ->where('e.submitted_at', '>=', $context->from)->where('e.submitted_at', '<', $context->effectiveUntil())
                ->selectRaw("h.student_id, 'homework_submission' as record_type, e.id as record_id, h.id as item_id, e.submitted_at as occurred_at, NULL as date_only, h.status, NULL as duration_seconds, NULL as interval_start, NULL as interval_end, NULL as course_id, NULL as lesson_id");
        }
        if (in_array($context->category, ['all', 'lms'], true)) {
            $queries[] = $this->lms('lms_assignment_submissions', $context->studentId, 'assignment')->where('e.status', '<>', 'erased')
                ->where('e.created_at', '>=', $context->from)->where('e.created_at', '<', $context->effectiveUntil())
                ->selectRaw("e.student_id, 'assignment' as record_type, e.id as record_id, e.block_id as item_id, e.created_at as occurred_at, NULL as date_only, e.status, NULL as duration_seconds, NULL as interval_start, NULL as interval_end, c.id as course_id, l.id as lesson_id");
            $queries[] = $this->lms('lms_quiz_attempts', $context->studentId, 'quiz')->whereIn('e.status', ['pending_review', 'graded'])
                ->where('e.submitted_at', '>=', $context->from)->where('e.submitted_at', '<', $context->effectiveUntil())
                ->whereColumn('e.submitted_at', '>=', 'e.created_at')
                ->selectRaw("e.student_id, 'quiz' as record_type, e.id as record_id, e.block_id as item_id, e.submitted_at as occurred_at, NULL as date_only, e.status, NULL as duration_seconds, NULL as interval_start, NULL as interval_end, c.id as course_id, l.id as lesson_id");
            $queries[] = $this->lms('lms_lesson_progress', $context->studentId, null)
                ->where('e.completed_at', '>=', $context->from)->where('e.completed_at', '<', $context->effectiveUntil())
                ->whereColumn('e.completed_at', '>=', 'e.started_at')
                ->whereNotExists(function (Builder $earlier): void {
                    $earlier->selectRaw('1')->from('lms_lesson_progress as p')->whereColumn('p.student_id', 'e.student_id')->whereColumn('p.lesson_id', 'e.lesson_id')
                        ->where('p.completed_at', '>', '0000-00-00 00:00:00')->whereColumn('p.completed_at', '>=', 'p.started_at')
                        ->where(fn (Builder $time): Builder => $time->whereColumn('p.completed_at', '<', 'e.completed_at')
                            ->orWhere(fn (Builder $tie): Builder => $tie->whereColumn('p.completed_at', 'e.completed_at')->whereColumn('p.id', '<', 'e.id')));
                })->selectRaw("e.student_id, 'lesson' as record_type, e.id as record_id, e.lesson_id as item_id, e.completed_at as occurred_at, NULL as date_only, 'completed' as status, NULL as duration_seconds, NULL as interval_start, NULL as interval_end, c.id as course_id, l.id as lesson_id");
        }
        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return DB::query()->fromSub($union, 'r');
    }

    /** Date bucketing uses PHP's IANA transitions, independent of MySQL timezone table installation. */
    private function localDate(StudentInsightsContext $context): string
    {
        $transitions = (new DateTimeZone($context->timezone))->getTransitions($context->from->getTimestamp(), $context->until->getTimestamp());
        $cases = [];
        foreach (array_reverse($transitions ?: []) as $transition) {
            $at = CarbonImmutable::createFromTimestampUTC($transition['ts'])->format('Y-m-d H:i:s');
            $offset = (int) $transition['offset'];
            $cases[] = "WHEN occurred_at >= '{$at}' THEN DATE(DATE_ADD(occurred_at, INTERVAL {$offset} SECOND))";
        }
        $fallback = $context->from->setTimezone($context->timezone)->getOffset();

        return 'COALESCE(date_only, CASE '.implode(' ', $cases)." ELSE DATE(DATE_ADD(occurred_at, INTERVAL {$fallback} SECOND)) END)";
    }

    private function aggregateQuery(StudentInsightsContext $context): Builder
    {
        $date = $this->localDate($context);

        return $this->records($context)->selectRaw("COUNT(CASE WHEN record_type='session' THEN 1 END) as sessions_completed,
            SUM(CASE WHEN record_type='session' THEN duration_seconds END) as session_duration_seconds,
            COUNT(CASE WHEN record_type='session' AND duration_seconds IS NULL THEN 1 END) as invalid_durations,
            COUNT(CASE WHEN record_type='homework_assigned' THEN 1 END) as homework_assigned,
            COUNT(CASE WHEN record_type='homework_submission' THEN 1 END) as homework_submission_records,
            COUNT(DISTINCT CASE WHEN record_type='homework_submission' THEN CONCAT(student_id, ':', item_id) END) as homework_items_submitted,
            COUNT(CASE WHEN record_type='assignment' THEN 1 END) as lms_assignment_submissions,
            COUNT(DISTINCT CASE WHEN record_type='assignment' THEN CONCAT(student_id, ':', item_id) END) as lms_assignment_activities_submitted,
            COUNT(CASE WHEN record_type='quiz' THEN 1 END) as quiz_attempts_submitted,
            COUNT(DISTINCT CASE WHEN record_type='quiz' THEN CONCAT(student_id, ':', item_id) END) as quiz_activities_submitted,
            COUNT(CASE WHEN record_type='lesson' THEN 1 END) as lessons_first_completed,
            COUNT(DISTINCT CASE WHEN record_type <> 'homework_assigned' THEN {$date} END) as recorded_learning_days,
            COUNT(DISTINCT CASE WHEN record_type <> 'homework_assigned' THEN student_id END) as students_with_recorded_activity");
    }

    public function metrics(StudentInsightsContext $context): array
    {
        return $this->shapeMetrics((array) $this->aggregateQuery($context)->first(), $context);
    }

    private function shapeMetrics(array $row, StudentInsightsContext $context): array
    {
        $definitions = self::DEFINITIONS + [$context->studentId ? 'recorded_learning_days' : 'students_with_recorded_activity' => ['all', $context->studentId ? 'days' : 'students', 'B']];
        $metrics = [];
        foreach ($definitions as $id => [$category, $unit, $reliability]) {
            if ($category !== 'all' && $context->category !== 'all' && $category !== $context->category) {
                continue;
            }
            $value = (int) ($row[$id] ?? 0);
            $state = 'recorded';
            if ($id === 'session_duration_seconds' && ($row['invalid_durations'] ?? 0) > 0) {
                $state = isset($row[$id]) ? 'partial' : 'unavailable';
                $value = isset($row[$id]) ? (int) $row[$id] : null;
            }
            $metrics[$id] = ['id' => $id, 'value' => $value, 'state' => $state, 'unit' => $unit, 'reliability' => $reliability, 'excluded_invalid_durations' => $id === 'session_duration_seconds' ? (int) ($row['invalid_durations'] ?? 0) : 0];
        }

        return $metrics;
    }

    /** Per-page SQL batch; zero-activity members remain visible without LMS dashboards per student. */
    public function students(StudentInsightsContext $context, string $search = ''): array
    {
        $page = $this->population()->when($search !== '', fn (EloquentBuilder $query): EloquentBuilder => $query->where(fn (EloquentBuilder $names): EloquentBuilder => $names->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%')))
            ->orderBy('id')->paginate(20, ['id', 'first_name', 'last_name'])->withQueryString();
        $rows = $this->aggregateQuery($context)->whereIn('student_id', $page->getCollection()->pluck('id'))->addSelect('student_id')->groupBy('student_id')->get()->keyBy('student_id');
        $summaries = [];
        foreach ($page as $student) {
            $individual = new StudentInsightsContext($student->id, $context->timezone, $context->category, $context->range, $context->startDate, $context->endDate, $context->from, $context->until, $context->generatedAt, $context->month);
            $summaries[$student->id] = $this->shapeMetrics((array) $rows->get($student->id, []), $individual);
        }

        return ['students' => $page, 'studentMetrics' => $summaries];
    }

    public function metricsForStudents(StudentInsightsContext $context, array $ids): array
    {
        $rows = $this->aggregateQuery($context)->whereIn('student_id', $ids)->addSelect('student_id')->groupBy('student_id')->get()->keyBy('student_id');
        $result = [];
        foreach ($ids as $id) {
            $individual = new StudentInsightsContext($id, $context->timezone, $context->category, $context->range, $context->startDate, $context->endDate, $context->from, $context->until, $context->generatedAt, $context->month);
            $result[$id] = $this->shapeMetrics((array) $rows->get($id, []), $individual);
        }

        return $result;
    }

    public function trends(StudentInsightsContext $context): array
    {
        $date = $this->localDate($context);
        $bucket = $context->days() > 90 ? "SUBSTRING({$date},1,7)" : $date;
        $rows = $this->records($context)->where('record_type', '<>', 'homework_assigned')
            ->selectRaw("{$bucket} as bucket, COUNT(*) as records, COUNT(DISTINCT student_id) as students, COUNT(DISTINCT {$date}) as days")
            ->groupBy('bucket')->orderBy('bucket')->get()->keyBy('bucket');
        $step = $context->days() > 90 ? 'month' : 'day';
        $start = CarbonImmutable::parse($context->startDate, $context->timezone);
        $end = CarbonImmutable::parse(min($context->endDate, $context->generatedAt->setTimezone($context->timezone)->toDateString()), $context->timezone);
        $buckets = [];
        for ($day = $step === 'month' ? $start->startOfMonth() : $start; $day->lte($end); $day = $step === 'month' ? $day->addMonth() : $day->addDay()) {
            $key = $day->format($step === 'month' ? 'Y-m' : 'Y-m-d');
            $buckets[] = ['bucket' => $key, 'records' => (int) ($rows[$key]->records ?? 0), 'students' => (int) ($rows[$key]->students ?? 0), 'days' => (int) ($rows[$key]->days ?? 0)];
        }

        return $buckets;
    }

    public function calendar(StudentInsightsContext $context): array
    {
        $date = $this->localDate($context);
        $first = CarbonImmutable::parse($context->month.'-01', $context->timezone);
        $rows = $this->records($context)->where('record_type', '<>', 'homework_assigned')
            ->whereRaw("{$date} >= ? AND {$date} < ?", [$first->toDateString(), $first->addMonth()->toDateString()])
            ->selectRaw("{$date} as day, COUNT(*) as records")->groupBy('day')->get()->keyBy('day');
        $days = [];
        for ($day = $first; $day->month === $first->month; $day = $day->addDay()) {
            $key = $day->toDateString();
            $days[] = ['date' => $key, 'number' => $day->day, 'count' => (int) ($rows[$key]->records ?? 0), 'state' => $key < $context->startDate || $key > $context->endDate ? 'outside' : ($day->gt($context->generatedAt) ? 'future' : (isset($rows[$key]) ? 'activity' : 'none'))];
        }

        return ['days' => $days, 'offset' => $first->dayOfWeekIso - 1, 'previous' => $first->subMonth()->format('Y-m') >= substr($context->startDate, 0, 7) ? $first->subMonth()->format('Y-m') : null, 'next' => $first->addMonth()->format('Y-m') <= substr($context->endDate, 0, 7) ? $first->addMonth()->format('Y-m') : null];
    }

    public function current(StudentInsightsContext $context, ?Student $student): array
    {
        $states = [];
        if (in_array($context->category, ['all', 'homework'], true)) {
            foreach ($this->homework($context->studentId)->selectRaw('h.status, COUNT(*) as total')->groupBy('h.status')->get() as $row) {
                $states['homework_'.$row->status] = (int) $row->total;
            }
            foreach (['assigned', 'in_progress', 'submitted', 'reviewed', 'completed'] as $status) {
                $states['homework_'.$status] ??= 0;
            }
        }
        if (in_array($context->category, ['all', 'lms'], true)) {
            $states['assignments_awaiting_review'] = $this->lms('lms_assignment_submissions', $context->studentId, 'assignment')->whereIn('e.status', ['submitted', 'under_review'])->where('e.created_at', '<', $context->generatedAt)->count();
            $states['assignments_reviewed'] = $this->lms('lms_assignment_submissions', $context->studentId, 'assignment')->whereIn('e.status', ['needs_revision', 'approved'])->where('e.created_at', '<', $context->generatedAt)->count();
            $states['quizzes_awaiting_review'] = $this->lms('lms_quiz_attempts', $context->studentId, 'quiz')->where('e.status', 'pending_review')->where('e.submitted_at', '>', '0000-00-00 00:00:00')->where('e.submitted_at', '<', $context->generatedAt)->whereColumn('e.submitted_at', '>=', 'e.created_at')->count();
        }
        $courses = [];
        if ($student && in_array($context->category, ['all', 'lms'], true)) {
            $overview = $this->access->studentOutlines($student);
            $rows = LessonProgress::query()->where('student_id', $student->id)->whereIn('lesson_id', $overview['canonical']->pluck('id'))->get(['id', 'lesson_id', 'requirement_hash', 'started_at', 'completed_at']);
            foreach ($overview['outlines'] as $id => $outline) {
                $progress = $this->progress->cohortSummary($overview['canonical']->where('course_id', $id)->values(), $rows, $context->generatedAt);
                $courses[] = ['id' => $id, 'title' => $outline['course']->title, 'status' => $progress['status'], 'completed' => $progress['completed'], 'required' => $progress['required'], 'percent' => $progress['required'] > 0 ? $progress['percent'] : null, 'url' => route('student.learning.courses.show', $id)];
            }
        }

        return ['states' => $states, 'courses' => $courses];
    }

    public function contributions(StudentInsightsContext $context): Builder
    {
        return $this->records($context)->select('r.*')->selectRaw($this->localDate($context).' as local_date')
            ->orderByDesc('local_date')->orderByDesc('occurred_at')->orderBy('record_type')->orderByDesc('record_id');
    }

    public function page(StudentInsightsContext $context, ?Student $student): array
    {
        return ['context' => $context, 'metrics' => $this->metrics($context), 'previousContext' => $context->previous(), 'previousMetrics' => $this->metrics($context->previous()), 'trends' => $this->trends($context), 'calendar' => $this->calendar($context), 'current' => $this->current($context, $student), 'estimatedActivity' => app(EstimatedActivityReport::class)->student($context)];
    }
}
