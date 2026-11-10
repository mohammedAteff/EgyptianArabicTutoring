<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Students\Models\Student;
use Generator;
use Symfony\Component\HttpFoundation\Response;

class StudentInsightsExport
{
    public function __construct(private StudentInsightsReadModel $read, private ExportService $exports) {}

    public function download(StudentInsightsContext $context, ?Student $student, string $dataset, string $format): Response
    {
        $metadata = array_keys($context->metadata());
        $headers = $dataset === 'activity_records'
            ? ['Student ID', 'Record type', 'Record ID', 'Item ID', 'Occurred at UTC', 'Local date', 'Status', 'Valid duration seconds', 'Interval start UTC', 'Interval end UTC', 'Course ID', 'Lesson ID', 'Metric IDs', 'Reliability', ...$metadata]
            : ['Student ID', 'Metric ID', 'Metric label', 'Value', 'Value state', 'Unit', 'Period or current', 'Course ID', 'Course status', 'Reliability', 'Excluded invalid durations', ...$metadata];
        $rows = $dataset === 'activity_records' ? $this->records($context) : $this->summaries($context, $student);
        if ($dataset === 'estimated_allocations') {
            $headers = ['Student ID', 'Source', 'Definition', 'Category', 'Start UTC', 'End UTC', 'Numeric seconds', 'Local date', 'References', ...$metadata];
            $rows = $this->estimates($context);
        }

        return $this->exports->export('student_insights_'.$dataset.'_'.$context->generatedAt->format('Ymd_His'), $headers, $rows, $format, 'Student Insights');
    }

    public static function metricIds(string $type, bool $aggregate = false): string
    {
        return match ($type) {
            'session' => 'sessions_completed;session_duration_seconds',
            'homework_assigned' => 'homework_assigned',
            'homework_submission' => 'homework_submission_records;homework_items_submitted',
            'assignment' => 'lms_assignment_submissions;lms_assignment_activities_submitted',
            'quiz' => 'quiz_attempts_submitted;quiz_activities_submitted',
            'lesson' => 'lessons_first_completed',
            default => throw new \UnhandledMatchError('Unsupported insight record type.'),
        }.($type !== 'homework_assigned' ? ($aggregate ? ';students_with_recorded_activity' : ';recorded_learning_days') : '');
    }

    private function records(StudentInsightsContext $context): Generator
    {
        foreach ($this->read->contributions($context)->lazy(200) as $row) {
            yield [$row->student_id, $row->record_type, $row->record_id, $row->item_id, $row->occurred_at, $row->local_date, $row->status, $row->duration_seconds === null ? null : (int) $row->duration_seconds, $row->interval_start, $row->interval_end, $row->course_id, $row->lesson_id, self::metricIds($row->record_type, $context->studentId === null), $row->record_type === 'homework_submission' ? 'B: legacy timing may be synthesized' : 'A: retained source', ...array_values($context->metadata())];
        }
    }

    private function metricRows(StudentInsightsContext $context, ?int $studentId, array $metrics, string $period = 'period'): Generator
    {
        foreach ($metrics as $metric) {
            yield [$studentId, $metric['id'], __('insights.metrics.'.$metric['id']), $metric['value'], $metric['state'], $metric['unit'], $period, null, null, $metric['reliability'], $metric['excluded_invalid_durations'], ...array_values($context->metadata())];
        }
    }

    private function summaries(StudentInsightsContext $context, ?Student $student): Generator
    {
        yield from $this->metricRows($context, $context->studentId, $this->read->metrics($context));
        $measurement = app(EstimatedActivityReport::class)->student($context);
        foreach (['estimated_active_learning_seconds' => $measurement['seconds'],
            'estimated_contributing_students' => $measurement['seconds'] !== null ? $measurement['contributors'] : null,
            'estimated_overlapping_context_seconds' => $measurement['seconds'] !== null ? $measurement['overlap_seconds'] : null] as $metric => $value) {
            yield [$context->studentId, $metric, $metric === 'estimated_active_learning_seconds' ? __('activity.title') : $metric, $value, $measurement['state'],
                $metric === 'estimated_contributing_students' ? 'students' : 'seconds', 'period', null, null,
                'Estimated; partial measurement; '.EstimatedActivityDefinition::VERSION, null, ...array_values($context->metadata())];
        }
        $previous = $context->previous();
        yield from $this->metricRows($previous, $context->studentId, $this->read->metrics($previous), 'previous_period');
        $current = $this->read->current($context, $student);
        foreach ($current['states'] as $id => $value) {
            yield [$context->studentId, $id, __('insights.current_metrics.'.$id), $value, 'recorded', 'records', 'current', null, null, 'A', 0, ...array_values($context->metadata())];
        }
        foreach ($current['courses'] as $course) {
            foreach (['completed', 'required', 'percent'] as $key) {
                yield [$context->studentId, 'course_'.$key, $key, $course[$key], $course[$key] === null ? 'not_applicable' : 'recorded', $key === 'percent' ? 'percent' : 'lessons', 'current', $course['id'], $course['status'], 'A', 0, ...array_values($context->metadata())];
            }
        }
        if ($context->studentId === null) {
            foreach ($this->read->population()->orderBy('id')->select('id')->lazyById(100)->chunk(100) as $students) {
                $metrics = $this->read->metricsForStudents($context, $students->pluck('id')->all());
                $previousMetrics = $this->read->metricsForStudents($previous, $students->pluck('id')->all());
                foreach ($students as $member) {
                    yield from $this->metricRows($context, $member->id, $metrics[$member->id]);
                    yield from $this->metricRows($previous, $member->id, $previousMetrics[$member->id], 'previous_period');
                }
            }
        }
    }

    private function estimates(StudentInsightsContext $context): Generator
    {
        foreach (app(EstimatedActivityProjection::class)->allocations($context) as $row) {
            if ($context->category === 'sessions' || ($context->category !== 'all' && ! in_array($row['category'], [$context->category, 'unresolved_overlap'], true))) {
                continue;
            }
            yield [$row['student_id'], $row['source'], $row['definition'], $row['category'], $row['started_at'], $row['ended_at'],
                $row['seconds'], $row['local_date'], $row['references'], ...array_values($context->metadata())];
        }
    }
}
