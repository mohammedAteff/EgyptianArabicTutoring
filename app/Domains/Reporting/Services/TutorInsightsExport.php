<?php

namespace App\Domains\Reporting\Services;

use Generator;
use Symfony\Component\HttpFoundation\Response;

class TutorInsightsExport
{
    public function __construct(private TutorInsightsReadModel $read, private ExportService $exports) {}

    public function download(TutorInsightsContext $context, string $dataset, string $format): Response
    {
        $headers = match ($dataset) {
            'summary' => ['Metric ID', 'Metric label', 'Value', 'Value state', 'Unit', 'Scope', 'Group', 'Source definition', 'Denominator', 'Invalid duration records'],
            'operations' => ['Source kind', 'Record ID', 'Occurred at UTC', 'Local date', 'Student reference', 'Booking reference', 'Submission reference', 'Conversation reference', 'Actor type', 'Classification', 'Metric ID', 'Source limitation'],
            default => ['Booking reference', 'Student reference', 'Session type reference', 'Session type', 'Current outcome', 'Interval start UTC', 'Interval end UTC', 'Local date', 'Valid duration seconds', 'Duration state', 'Metric IDs'],
        };

        return $this->exports->export('tutor_insights_'.$dataset.'_'.$context->period->generatedAt->format('Ymd_His'), [...$headers, ...array_keys($context->metadata())], $this->rows($context, $dataset), $format, 'Tutor Insights');
    }

    public static function appointmentMetricIds(object $record, bool $upcoming = false): string
    {
        if ($upcoming) {
            return 'upcoming_confirmed_appointments';
        }
        $ids = ['outcome_'.$record->status];
        if ($record->status === 'completed') {
            $ids = array_merge($ids, ['appointments_completed', 'teaching_days', $record->student_id === null ? 'unlinked_completed_appointments' : 'linked_completed_appointments']);
            if ($record->student_id !== null) {
                $ids = array_merge($ids, ['identified_students_taught', 'sessions_per_identified_student']);
            }
            if ($record->duration_seconds !== null) {
                $ids[] = 'recorded_teaching_seconds';
            }
            if ($record->type_slug === 'diagnostic-session') {
                $ids[] = 'diagnostic_type_completed';
            }
        }

        return implode(';', $ids);
    }

    private function metrics(TutorInsightsContext $context, string $scope): Generator
    {
        foreach ($this->read->metrics($context) as $metric) {
            yield [$metric['id'], $metric['label'], $metric['value'], $metric['state'], $metric['unit'], $scope, null, $metric['source'], $metric['denominator'], $metric['invalid'], ...array_values($context->metadata())];
        }
    }

    public function rows(TutorInsightsContext $context, string $dataset): Generator
    {
        if ($dataset === 'summary') {
            yield from $this->metrics($context, 'selected_period');
            yield from $this->metrics($context->previous(), 'previous_period');
            if ($context->view === 'operations') {
                return;
            }
            foreach ($this->read->outcomes($context) as $outcome => $count) {
                yield ['outcome_'.$outcome, TutorInsightsContext::OUTCOMES[$outcome] ?? $outcome, $count, 'recorded', 'appointments', 'selected_period', null, 'Current canonical status; current scheduled-start attribution.', null, 0, ...array_values($context->metadata())];
            }
            yield ['upcoming_confirmed_appointments', 'Upcoming confirmed appointments', $context->upcomingApplies() ? $this->read->appointments($context, true)->count() : null, $context->upcomingApplies() ? 'recorded' : 'not_applicable', 'appointments', 'upcoming_snapshot', null, 'Confirmed starts from generation instant to exclusive local start of date 30 days after today; student and type filters apply; independent of historical range.', null, 0, ...array_values($context->metadata())];
            foreach ($this->read->types($context) as $type) {
                yield ['appointments_by_type', 'Appointments by recorded type', (int) $type->appointments, 'recorded', 'appointments', 'selected_period', $type->session_type_id === null ? 'Unknown type' : 'Type #'.$type->session_type_id.' · '.$type->session_type, 'Exhaustive current recorded session-type buckets; includes inactive historical types.', null, 0, ...array_values($context->metadata())];
            }
            foreach ($this->read->trends($context) as $bucket) {
                foreach (['appointments_completed', 'recorded_teaching_seconds', 'identified_students_taught'] as $id) {
                    $metric = $bucket['metrics'][$id];
                    yield [$id, $metric['label'], $metric['value'], $metric['state'], $metric['unit'], 'trend', $bucket['date'].($bucket['partial'] ? ' · partial bucket' : ''), $metric['source'], $metric['denominator'], $metric['invalid'], ...array_values($context->metadata())];
                }
            }
            foreach ($this->read->distributions($context) as $kind => $buckets) {
                foreach ($buckets as $bucket) {
                    foreach (['appointments_completed', 'recorded_teaching_seconds'] as $id) {
                        $metric = $bucket['metrics'][$id];
                        yield [$id, $metric['label'], $metric['value'], $metric['state'], $metric['unit'], $kind, $bucket['label'], $metric['source'], null, $metric['invalid'], ...array_values($context->metadata())];
                    }
                }
            }

            return;
        }
        foreach ($this->read->contributions($context, $dataset)->lazy(200) as $record) {
            if ($dataset === 'operations') {
                yield [$record->source_kind, $record->record_id, $record->occurred_at, $record->local_date, $record->student_id ?? 'unlinked', $record->booking_id, $record->submission_id, $record->conversation_id, $record->actor_type, $record->classification, $record->metric_id, TutorInsightsReadModel::DEFINITIONS[$record->metric_id][2], ...array_values($context->metadata())];
            } else {
                yield [$record->record_id, $record->student_id ?? 'unlinked', $record->session_type_id, $record->session_type, $record->status, $record->interval_start, $record->interval_end, $record->local_date, $record->duration_seconds === null ? null : (int) $record->duration_seconds, $record->duration_seconds === null ? 'unavailable' : 'recorded', self::appointmentMetricIds($record, $dataset === 'upcoming'), ...array_values($context->metadata())];
            }
        }
    }
}
