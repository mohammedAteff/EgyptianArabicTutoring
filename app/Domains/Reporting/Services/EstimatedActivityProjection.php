<?php

namespace App\Domains\Reporting\Services;

use Generator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Interval normalization only. Cross-source work precedence remains exclusively in TutorWorkReport. */
class EstimatedActivityProjection
{
    public function available(): bool
    {
        return Schema::hasTable('estimated_activity_intervals');
    }

    public function query(StudentInsightsContext $context, ?int $workspace = null): Builder
    {
        return DB::table('estimated_activity_intervals')->where('definition', EstimatedActivityDefinition::VERSION)
            ->when($workspace !== null, fn ($q) => $q->where('workspace_id', $workspace), fn ($q) => $q->whereIn('student_id', app(StudentInsightsReadModel::class)->population($context->studentId)->select('id')))
            ->where('started_at', '<', $context->effectiveUntil())->where('ended_at', '>', $context->from)
            ->orderBy('student_id')->orderBy('started_at')->orderBy('id');
    }

    /** Memory is bounded by concurrent intervals, not historical row count.
     * @return Generator<int,array<string,mixed>> */
    public function allocations(StudentInsightsContext $context, ?int $workspace = null): Generator
    {
        if (! $this->available()) {
            return;
        }
        $rows = new \IteratorIterator($this->query($context, $workspace)->lazy(500)->getIterator());
        $rows->rewind();
        $lower = EstimatedActivityDefinition::micros($context->from);
        $upper = EstimatedActivityDefinition::micros($context->effectiveUntil());
        while ($rows->valid()) {
            $remainder = 0;
            $student = $rows->current()->student_id;
            $position = max($lower, EstimatedActivityDefinition::micros($rows->current()->started_at));
            $active = [];
            $dayEnd = 0;
            $localDate = '';
            while (($rows->valid() && $rows->current()->student_id === $student) || $active !== []) {
                while ($rows->valid() && $rows->current()->student_id === $student
                    && EstimatedActivityDefinition::micros($rows->current()->started_at) <= $position) {
                    $row = (array) $rows->current();
                    $row['end'] = min($upper, EstimatedActivityDefinition::micros($row['ended_at']));
                    $active[$row['id']] = $row;
                    $rows->next();
                }
                foreach ($active as $id => $row) {
                    if ($row['end'] <= $position) {
                        unset($active[$id]);
                    }
                }
                $next = $rows->valid() && $rows->current()->student_id === $student
                    ? max($position, EstimatedActivityDefinition::micros($rows->current()->started_at)) : $upper;
                if ($active === []) {
                    if ($next >= $upper) {
                        break;
                    }
                    $position = $next;

                    continue;
                }
                if ($position >= $dayEnd) {
                    $local = EstimatedActivityDefinition::instant($position)->setTimezone($context->timezone);
                    $localDate = $local->toDateString();
                    $dayEnd = EstimatedActivityDefinition::micros($local->addDay()->startOfDay());
                }
                $end = min($upper, $next, min(array_column($active, 'end')), $dayEnd);
                if ($end <= $position) {
                    break;
                }
                $contexts = array_unique(array_map(fn (array $row): string => $row['kind'].':'.$row['record_id'], $active));
                $categories = array_unique(array_column($active, 'category'));
                $ambiguous = $workspace === null ? count($contexts) > 1 : count($categories) > 1;
                $category = $ambiguous ? 'unresolved_overlap' : reset($categories);
                $first = reset($active);
                yield ['interval_id' => $first['id'].':'.$position, 'record_id' => $first['id'], 'source' => $workspace === null ? 'estimated_student' : 'estimated_admin',
                    'student_id' => $student, 'category' => $category, 'started_at' => EstimatedActivityDefinition::boundary($position),
                    'ended_at' => EstimatedActivityDefinition::boundary($end), 'seconds' => EstimatedActivityDefinition::seconds($end - $position, $remainder), 'local_date' => $localDate,
                    'references' => implode(';', array_map(fn (array $row): string => 'estimate:'.$row['id'].'/actor:'.($row['administrator_id'] ?? 'student').'/'.$row['kind'].':'.$row['record_id'], $active)),
                    'acceptance' => 'accepted', 'corrected' => 0, 'definition' => EstimatedActivityDefinition::VERSION];
                $position = $end;
            }
        }
    }
}
