<?php

namespace App\Domains\Reporting\Services;

use Illuminate\Support\Facades\DB;

class EstimatedActivityReport
{
    /** Same canonical calculation for student, staff, screen and export contexts.
     * @return array<string,mixed> */
    public function student(StudentInsightsContext $context): array
    {
        $result = ['seconds' => null, 'state' => config('estimated_activity.mode') === 'off' ? 'disabled' : 'unavailable',
            'collection' => config('estimated_activity.mode') === 'off' ? 'disabled' : 'synthetic',
            'definition' => EstimatedActivityDefinition::VERSION, 'contributors' => 0, 'days' => [], 'trends' => [],
            'overlap_seconds' => 0, 'anchored' => 0, 'eligible' => 0, 'interruptions' => 0];
        $projection = app(EstimatedActivityProjection::class);
        if (! $projection->available()) {
            return $result;
        }
        $contributors = [];
        $monthly = $context->days() > 100;
        foreach ($projection->allocations($context) as $row) {
            if ($context->category !== 'all' && $row['category'] !== $context->category && $row['category'] !== 'unresolved_overlap') {
                continue;
            }
            if ($context->category === 'sessions') {
                continue;
            }
            $result['seconds'] = ($result['seconds'] ?? 0) + $row['seconds'];
            $result['days'][$row['local_date']] = ($result['days'][$row['local_date']] ?? 0) + $row['seconds'];
            $bucket = $monthly ? substr($row['local_date'], 0, 7) : $row['local_date'];
            $result['trends'][$bucket] = ($result['trends'][$bucket] ?? 0) + $row['seconds'];
            $result['overlap_seconds'] += $row['category'] === 'unresolved_overlap' ? $row['seconds'] : 0;
            $contributors[$row['student_id']] = true;
        }
        $coverage = DB::table('estimated_activity_coverage')->whereIn('student_id', app(StudentInsightsReadModel::class)->population($context->studentId)->select('id'))
            ->where('definition', EstimatedActivityDefinition::VERSION)->where('anchored_at', '<', $context->effectiveUntil())
            ->whereRaw('COALESCE(ended_at, last_accepted_at, anchored_at) >= ?', [$context->from->toDateTimeString()])
            ->selectRaw('COUNT(*) as anchored, SUM(ended_at IS NOT NULL) as interruptions')->first();
        $result['anchored'] = (int) $coverage->anchored;
        $result['interruptions'] = (int) $coverage->interruptions;
        if (config('estimated_activity.mode') === 'synthetic') {
            $result['eligible'] = DB::table('estimated_activity_grants as g')->join('estimated_activity_runs as r', 'r.id', '=', 'g.run_id')
                ->where('g.run_id', config('estimated_activity.run_id'))->where('g.principal_type', 'student')->whereNull('g.revoked_at')
                ->where('r.enabled', true)->where('r.expires_at', '>', now('UTC'))->where('g.expires_at', '>', now('UTC'))
                ->whereIn('g.principal_id', app(StudentInsightsReadModel::class)->population($context->studentId)->select('id'))->count();
        }
        $result['contributors'] = count($contributors);
        $result['state'] = $result['seconds'] !== null ? 'partial' : ($result['anchored'] ? 'no_accepted' : ($result['eligible'] ? 'eligible' : $result['state']));
        ksort($result['days']);
        ksort($result['trends']);

        return $result;
    }

    /** @return array<string,mixed> */
    public function admin(TutorWorkContext $context): array
    {
        $summary = app(TutorWorkReport::class)->summary($context);

        return ['state' => $summary['estimated_shared'] > 0 ? 'partial' : (config('estimated_activity.mode') === 'off' ? 'disabled' : 'no_accepted'),
            'seconds' => $summary['estimated_shared'] ?: null, 'covered' => $summary['estimated_covered'], 'additional' => $summary['estimated_additional'],
            'definition' => EstimatedActivityDefinition::VERSION];
    }
}
