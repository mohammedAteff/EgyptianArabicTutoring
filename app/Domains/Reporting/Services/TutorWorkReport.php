<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Reporting\Models\TutorWorkEntry;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TutorWorkReport
{
    public function history(TutorWorkContext $context): \Illuminate\Database\Eloquent\Builder
    {
        $this->authorize($context);

        return TutorWorkEntry::query()->where('workspace_id', $context->workspace)
            ->where(function ($query) use ($context): void {
                $query->where(function ($dates) use ($context): void {
                    $dates->where('placement', 'unplaced')->whereBetween('work_date', [$context->period->startDate, $context->period->endDate]);
                })->orWhereExists(function ($intervals) use ($context): void {
                    $intervals->selectRaw('1')->from('tutor_work_intervals as i')->whereColumn('i.entry_id', 'tutor_work_entries.id')->where('i.started_at', '<', $context->period->effectiveUntil())->where('i.ended_at', '>', $context->period->from);
                })->orWhere(function ($open) use ($context): void {
                    $open->whereIn('lifecycle', ['running', 'paused'])->whereExists(function ($segments) use ($context): void {
                        $segments->selectRaw('1')->from('tutor_work_segments as provisional')->whereColumn('provisional.entry_id', 'tutor_work_entries.id')->where('provisional.started_at', '<', $context->period->effectiveUntil())->whereRaw('COALESCE(provisional.ended_at, ?) > ?', [$context->period->generatedAt->toDateTimeString(), $context->period->from->toDateTimeString()]);
                    });
                })->orWhere(function ($zero) use ($context): void {
                    $zero->where('lifecycle', 'stopped')->where('placement', 'placed')->whereNotExists(fn ($intervals) => $intervals->selectRaw('1')->from('tutor_work_intervals as missing')->whereColumn('missing.entry_id', 'tutor_work_entries.id'))->whereBetween('work_date', [$context->period->startDate, $context->period->endDate]);
                });
            })
            ->when($context->category !== 'all', fn ($query) => $query->where('category', $context->category))
            ->when($context->source !== 'all', fn ($query) => $query->where('origin', $context->source))
            ->when($context->state !== 'all', fn ($query) => $query->where('acceptance', $context->state))
            ->select('tutor_work_entries.*')->selectSub(DB::table('tutor_work_intervals as duration')->selectRaw('SUM(TIMESTAMPDIFF(SECOND, started_at, ended_at))')->whereColumn('duration.entry_id', 'tutor_work_entries.id'), 'placed_seconds')
            ->orderByDesc('work_date')->orderByDesc('id');
    }

    public function placedSources(TutorWorkContext $context): Builder
    {
        $this->authorize($context);
        $work = DB::table('tutor_work_intervals as i')->join('tutor_work_entries as e', 'e.id', '=', 'i.entry_id')
            ->where('i.workspace_id', $context->workspace)->where('e.workspace_id', $context->workspace)->where('e.acceptance', 'accepted')->where('e.lifecycle', 'stopped')->where('e.placement', 'placed')
            ->where('i.started_at', '<', $context->period->effectiveUntil())->where('i.ended_at', '>', $context->period->from)
            ->selectRaw('i.id as interval_id, e.id as record_id, e.origin as source, e.category, i.started_at, i.ended_at, e.acceptance, e.corrected');
        $bookings = DB::table('bookings as b')->whereNull('b.deleted_at')->where('b.status', 'completed')
            ->where(fn ($owners) => $owners->whereNull('b.student_id')->orWhereIn('b.student_id', app(StudentInsightsReadModel::class)->population()->select('id')))
            ->whereColumn('b.end_at_utc', '>', 'b.start_at_utc')->where('b.start_at_utc', '<', $context->period->effectiveUntil())->where('b.end_at_utc', '>', $context->period->from)
            ->selectRaw("b.id as interval_id, b.id as record_id, 'recorded_session' as source, 'teaching' as category, b.start_at_utc as started_at, b.end_at_utc as ended_at, 'accepted' as acceptance, 0 as corrected");
        $work->unionAll($bookings);

        return DB::query()->fromSub($work, 'sources')->orderBy('started_at')->orderBy('source')->orderBy('record_id')->orderBy('interval_id');
    }

    /** Sweep ordered effective intervals; memory is proportional to concurrently overlapping sources. */
    public function allocations(TutorWorkContext $context): Generator
    {
        $this->authorize($context);
        $rows = $this->acceptedSources($context);
        $rows->rewind();
        $active = [];
        $remainder = 0;
        $grossRemainders = [];
        $position = EstimatedActivityDefinition::micros($context->period->from);
        $until = EstimatedActivityDefinition::micros($context->period->effectiveUntil());
        while ($rows->valid() || $active !== []) {
            while ($rows->valid() && EstimatedActivityDefinition::micros($rows->current()->started_at) <= $position) {
                $row = (array) $rows->current();
                $row['end'] = min($until, EstimatedActivityDefinition::micros($row['ended_at']));
                $active[$row['source'].':'.$row['interval_id']] = $row;
                $rows->next();
            }
            foreach ($active as $key => $row) {
                if ($row['end'] <= $position) {
                    unset($active[$key]);
                }
            }
            $nextStart = $rows->valid() ? max($position, EstimatedActivityDefinition::micros($rows->current()->started_at)) : $until;
            if ($active === []) {
                if (! $rows->valid() || $nextStart >= $until) {
                    break;
                }
                $position = $nextStart;

                continue;
            }
            $local = EstimatedActivityDefinition::instant($position)->setTimezone($context->period->timezone);
            $end = min($until, $nextStart, min(array_column($active, 'end')), EstimatedActivityDefinition::micros($local->addDay()->startOfDay()));
            if ($end <= $position) {
                break;
            }
            $priorities = ['recorded_session' => 0, 'manual' => 1, 'timer' => 2, 'estimated_admin' => 3];
            $priority = min(array_map(fn (array $row): int => $priorities[$row['source']], $active));
            $winners = array_values(array_filter($active, fn (array $row): bool => $priorities[$row['source']] === $priority));
            usort($winners, fn (array $a, array $b): int => [$a['record_id'], $a['interval_id']] <=> [$b['record_id'], $b['interval_id']]);
            $category = count(array_unique(array_column($winners, 'category'))) > 1 ? 'unresolved_overlap' : $winners[0]['category'];
            $bucket = match ($context->grouping) {
                'weekly' => $local->startOfWeek()->toDateString(), 'monthly' => $local->startOfMonth()->toDateString(), default => $local->toDateString()
            };
            $gross = [];
            $grossMicros = [];
            foreach ($active as $row) {
                $grossMicros[$row['source']] = ($grossMicros[$row['source']] ?? 0) + $end - $position;
            }
            foreach ($grossMicros as $source => $micros) {
                $grossRemainders[$source] ??= 0;
                $gross[$source] = EstimatedActivityDefinition::seconds($micros, $grossRemainders[$source]);
            }
            $seconds = EstimatedActivityDefinition::seconds($end - $position, $remainder);
            yield ['started_at' => EstimatedActivityDefinition::boundary($position), 'ended_at' => EstimatedActivityDefinition::boundary($end), 'local_date' => $local->toDateString(), 'bucket' => $bucket, 'seconds' => $seconds, 'category' => $category, 'source' => $winners[0]['source'], 'contribution' => $winners[0]['source'].':'.$winners[0]['record_id'], 'references' => implode(';', array_map(fn (array $row): string => $row['references'] ?? $row['source'].':'.$row['record_id'].'/interval:'.$row['interval_id'], array_values($active))), 'conflict' => $category === 'unresolved_overlap', 'cross_source_disagreement' => count(array_unique(array_column($active, 'category'))) > 1, 'gross' => $gross];
            $position = $end;
        }
    }

    private function authorize(TutorWorkContext $context): void
    {
        app(TutorWorkService::class)->authorize($context->actor);
        abort_unless((int) app(TutorWorkWorkspace::class)->ready()->id === $context->workspace, 403);
    }

    /** Merge the normalized shared estimate into the existing precedence sweep. */
    private function acceptedSources(TutorWorkContext $context): Generator
    {
        $sources = new \IteratorIterator($this->placedSources($context)->lazy(500)->getIterator());
        $estimates = app(EstimatedActivityProjection::class)->allocations($context->period, $context->workspace);
        $sources->rewind();
        $estimates->rewind();
        while ($sources->valid() || $estimates->valid()) {
            if ($sources->valid() && (! $estimates->valid() || $sources->current()->started_at <= $estimates->current()['started_at'])) {
                yield $sources->current();
                $sources->next();
            } else {
                yield (object) $estimates->current();
                $estimates->next();
            }
        }
    }

    public function unplaced(TutorWorkContext $context): Builder
    {
        $this->authorize($context);

        return DB::table('tutor_work_entries')->where('workspace_id', $context->workspace)->where('placement', 'unplaced')->where('lifecycle', 'stopped')->where('acceptance', 'accepted')->whereBetween('work_date', [$context->period->startDate, $context->period->endDate])
            ->when($context->category !== 'all', fn ($query) => $query->where('category', $context->category))->when($context->source !== 'all', fn ($query) => $query->where('origin', $context->source));
    }

    public function summary(TutorWorkContext $context): array
    {
        $summary = ['gross' => 0, 'union' => 0, 'duplicate' => 0, 'allocated' => 0, 'unplaced' => 0, 'conflicts' => 0, 'cross_source_disagreement' => 0, 'categories' => [], 'sources' => [], 'buckets' => [], 'review_count' => 0, 'estimated_shared' => 0, 'estimated_covered' => 0, 'estimated_additional' => 0];
        foreach ($this->allocations($context) as $allocation) {
            $summary['union'] += $allocation['seconds'];
            $summary['gross'] += array_sum($allocation['gross']);
            $summary['estimated_shared'] += $allocation['gross']['estimated_admin'] ?? 0;
            $summary['estimated_additional'] += $allocation['source'] === 'estimated_admin' ? $allocation['seconds'] : 0;
            foreach ($allocation['gross'] as $source => $seconds) {
                $summary['sources'][$source] = ($summary['sources'][$source] ?? 0) + $seconds;
            }
            $summary['conflicts'] += $allocation['conflict'] ? $allocation['seconds'] : 0;
            $summary['cross_source_disagreement'] += $allocation['cross_source_disagreement'] ? $allocation['seconds'] : 0;
            if ($context->matches($allocation['category'], $allocation['source'])) {
                $summary['allocated'] += $allocation['seconds'];
                $summary['categories'][$allocation['category']] = ($summary['categories'][$allocation['category']] ?? 0) + $allocation['seconds'];
                $summary['buckets'][$allocation['bucket']]['allocated'] = ($summary['buckets'][$allocation['bucket']]['allocated'] ?? 0) + $allocation['seconds'];
            }
        }
        foreach ($this->unplaced($context)->orderBy('id')->select(['id', 'work_date', 'unplaced_seconds', 'timezone', 'category', 'origin'])->cursor() as $entry) {
            $summary['unplaced'] += (int) $entry->unplaced_seconds;
            $date = CarbonImmutable::parse($entry->work_date, 'UTC');
            $bucket = match ($context->grouping) {
                'weekly' => $date->startOfWeek()->toDateString(), 'monthly' => $date->startOfMonth()->toDateString(), default => $entry->work_date
            };
            $summary['buckets'][$bucket]['unplaced'] = ($summary['buckets'][$bucket]['unplaced'] ?? 0) + (int) $entry->unplaced_seconds;
        }
        $reviewContext = new TutorWorkContext($context->actor, $context->period, $context->workspace, $context->category, $context->source, 'needs_review', $context->grouping);
        $summary['review_count'] = $this->history($reviewContext)->count();
        $summary['duplicate'] = $summary['gross'] - $summary['union'];
        $summary['estimated_covered'] = $summary['estimated_shared'] - $summary['estimated_additional'];
        ksort($summary['buckets']);

        return $summary;
    }

    public static function duration(int $seconds): string
    {
        return sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
