<?php

namespace App\Domains\Reporting\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Explicit synthetic retention only. No scheduler and no real-user retention defaults. */
class EstimatedActivityRetention
{
    /** Source deletion immediately changes reports; no identifiable derived totals are retained.
     * @param array<string, mixed> $policy
     * @return array<string,int> */
    public function prune(string $runId, array $policy): array
    {
        abort_unless(app(EstimatedActivityGate::class)->isolated(), 403);
        abort_unless(array_keys($policy) === ['permits_seconds', 'intervals_seconds', 'coverage_seconds'], 422);
        foreach ($policy as $seconds) {
            abort_unless($seconds === null || (is_int($seconds) && $seconds >= 0 && $seconds <= 31536000), 422);
        }

        return DB::transaction(function () use ($runId, $policy): array {
            DB::table('estimated_activity_runs')->where('id', $runId)->where('runtime_hash', app(EstimatedActivityGate::class)->runtimeHash())->lockForUpdate()->firstOrFail();
            $result = ['permits' => 0, 'intervals' => 0, 'coverage' => 0];
            $now = CarbonImmutable::now('UTC');
            if ($policy['permits_seconds'] !== null) {
                $result['permits'] = DB::table('estimated_activity_streams')->whereIn('grant_id', DB::table('estimated_activity_grants')->select('id')->where('run_id', $runId))
                    ->where('expires_at', '<=', EstimatedActivityDefinition::stamp($now->subSeconds($policy['permits_seconds'])))->delete();
            }
            if ($policy['intervals_seconds'] !== null) {
                $result['intervals'] = DB::table('estimated_activity_intervals')->where('run_id', $runId)
                    ->where('ended_at', '<=', EstimatedActivityDefinition::stamp($now->subSeconds($policy['intervals_seconds'])))->delete();
            }
            if ($policy['coverage_seconds'] !== null) {
                $result['coverage'] = DB::table('estimated_activity_coverage')->where('run_id', $runId)
                    ->whereRaw('COALESCE(ended_at, last_accepted_at, anchored_at) <= ?', [EstimatedActivityDefinition::stamp($now->subSeconds($policy['coverage_seconds']))])->delete();
            }

            return $result;
        }, 5);
    }
}
