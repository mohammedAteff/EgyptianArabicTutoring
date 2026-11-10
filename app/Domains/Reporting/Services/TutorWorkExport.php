<?php

namespace App\Domains\Reporting\Services;

use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TutorWorkExport
{
    public const HEADERS = ['Record / metric', 'Source', 'Placement', 'Category', 'Lifecycle', 'Acceptance', 'Corrected', 'Start UTC', 'End UTC', 'Work date', 'Original timezone', 'Numeric seconds', 'Clipped source seconds', 'Bucket', 'References', 'Category conflict', 'Cross-source disagreement', 'Metric scope', 'Value state', 'Unit'];

    public function download(TutorWorkContext $context, string $dataset, string $format): Response
    {
        return app(ExportService::class)->export('tutor_work_'.$dataset.'_'.$context->period->generatedAt->format('Ymd_His'), [...self::HEADERS, ...array_keys($context->metadata())], $this->rows($context, $dataset), $format, 'Tutor Work');
    }

    private function row(TutorWorkContext $context, array $values): array
    {
        return [...array_pad($values, count(self::HEADERS), null), ...array_values($context->metadata())];
    }

    public function rows(TutorWorkContext $context, string $dataset): Generator
    {
        app(TutorWorkService::class)->authorize($context->actor);
        $read = app(TutorWorkReport::class);
        if ($dataset === 'summary') {
            $summary = $read->summary($context);
            foreach (['gross', 'union', 'duplicate', 'allocated', 'unplaced', 'conflicts', 'cross_source_disagreement', 'review_count', 'estimated_shared', 'estimated_covered', 'estimated_additional'] as $metric) {
                $estimated = str_starts_with($metric, 'estimated_');
                $missing = $estimated && $summary['estimated_shared'] === 0;
                yield $this->row($context, [$metric, null, null, null, null, null, null, null, null, null, null, $missing ? null : $summary[$metric], null, null, null, null, null, in_array($metric, ['allocated', 'unplaced', 'review_count'], true) ? 'filtered_view' : 'accounting_context', $estimated ? ($missing ? (config('estimated_activity.mode') === 'off' ? 'disabled' : 'no_accepted') : 'partial') : 'recorded', $metric === 'review_count' ? 'records' : 'seconds']);
            }
            foreach (['categories', 'sources'] as $kind) {
                foreach ($summary[$kind] as $key => $seconds) {
                    yield $this->row($context, [$kind.':'.$key, $kind === 'sources' ? $key : null, 'placed', $kind === 'categories' ? $key : null, null, 'accepted', null, null, null, null, null, $seconds, null, null, null, null, null, $kind === 'sources' ? 'accounting_context_gross' : 'filtered_view_allocated', $kind === 'sources' && $key === 'estimated_admin' ? 'partial' : 'recorded', 'seconds']);
                }
            }
            foreach ($summary['buckets'] as $bucket => $values) {
                foreach ($values as $kind => $seconds) {
                    yield $this->row($context, ['bucket:'.$kind, null, $kind === 'unplaced' ? 'unplaced' : 'placed', null, null, 'accepted', null, null, null, null, null, $seconds, null, $bucket, null, null, null, 'filtered_view', 'recorded', 'seconds']);
                }
            }
        } elseif ($dataset === 'allocations') {
            foreach ($read->allocations($context) as $allocation) {
                if ($context->matches($allocation['category'], $allocation['source'])) {
                    yield $this->row($context, [$allocation['contribution'], $allocation['source'], 'placed', $allocation['category'], $allocation['source'] === 'estimated_admin' ? null : 'stopped', 'accepted', null, $allocation['started_at'], $allocation['ended_at'], $allocation['local_date'], null, $allocation['seconds'], null, $allocation['bucket'], $allocation['references'], (int) $allocation['conflict'], (int) $allocation['cross_source_disagreement'], 'filtered_view_allocated_after_full_reconciliation', $allocation['source'] === 'estimated_admin' ? 'partial' : 'recorded', 'seconds']);
                }
            }
        } else {
            $ids = $read->history($context)->reorder()->select('tutor_work_entries.id');
            $query = DB::table('tutor_work_entries as e')->leftJoin('tutor_work_intervals as i', 'i.entry_id', '=', 'e.id')->whereIn('e.id', $ids)->orderByDesc('e.work_date')->orderByDesc('e.id')->orderBy('i.started_at');
            foreach ($query->select(['e.id', 'e.origin', 'e.placement', 'e.category', 'e.lifecycle', 'e.acceptance', 'e.corrected', 'e.work_date', 'e.timezone', 'e.unplaced_seconds', 'i.started_at', 'i.ended_at'])->cursor() as $entry) {
                $duration = $entry->placement === 'unplaced' ? (int) $entry->unplaced_seconds : ($entry->ended_at ? CarbonImmutable::parse($entry->ended_at, 'UTC')->timestamp - CarbonImmutable::parse($entry->started_at, 'UTC')->timestamp : 0);
                $clipped = $entry->ended_at ? max(0, min(CarbonImmutable::parse($entry->ended_at, 'UTC')->timestamp, $context->period->effectiveUntil()->timestamp) - max(CarbonImmutable::parse($entry->started_at, 'UTC')->timestamp, $context->period->from->timestamp)) : null;
                yield $this->row($context, ['work:'.$entry->id, $entry->origin, $entry->placement, $entry->category, $entry->lifecycle, $entry->acceptance, (int) $entry->corrected, $entry->started_at, $entry->ended_at, $entry->work_date, $entry->timezone, $duration, $clipped, null, null, null, null, 'filtered_view_history_source_evidence_not_allocated', $entry->acceptance, 'seconds']);
            }
            if (in_array($context->state, ['all', 'accepted'], true) && $context->matches('teaching', 'recorded_session')) {
                foreach ($read->placedSources($context)->where('source', 'recorded_session')->cursor() as $booking) {
                    $start = CarbonImmutable::parse($booking->started_at, 'UTC');
                    $end = CarbonImmutable::parse($booking->ended_at, 'UTC');
                    yield $this->row($context, ['booking:'.$booking->record_id, 'recorded_session', 'placed', 'teaching', 'stopped', 'accepted', 0, $booking->started_at, $booking->ended_at, null, null, $end->timestamp - $start->timestamp, max(0, min($end->timestamp, $context->period->effectiveUntil()->timestamp) - max($start->timestamp, $context->period->from->timestamp)), null, null, null, null, 'filtered_view_recorded_schedule_not_observed_work', 'recorded', 'seconds']);
                }
            }
            if (in_array($context->state, ['all', 'accepted'], true)) {
                foreach (app(EstimatedActivityProjection::class)->allocations($context->period, $context->workspace) as $estimate) {
                    if ($context->matches($estimate['category'], 'estimated_admin')) {
                        yield $this->row($context, ['estimate:'.$estimate['record_id'], 'estimated_admin', 'placed', $estimate['category'], null, 'accepted', 0,
                            $estimate['started_at'], $estimate['ended_at'], $estimate['local_date'], null, $estimate['seconds'], $estimate['seconds'], null,
                            $estimate['references'], (int) ($estimate['category'] === 'unresolved_overlap'), null, 'shared_estimated_projection_before_cross_source_precedence', 'partial', 'seconds']);
                    }
                }
            }
        }
    }
}
