<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Students\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Server-only fixture control. There is deliberately no HTTP grant or live-mode API. */
class EstimatedActivitySynthetic
{
    public function beginRun(CarbonImmutable $expires): string
    {
        $gate = app(EstimatedActivityGate::class);
        abort_unless($gate->isolated() && $expires->isFuture() && $expires->lte(CarbonImmutable::now('UTC')->addSeconds(EstimatedActivityDefinition::MAX_RUN_SECONDS)), 403);
        $id = (string) Str::uuid();
        $runtime = $gate->runtimeHash();
        DB::table('estimated_activity_runs')->insert(['id' => $id, 'runtime_hash' => $runtime, 'generation' => 1, 'enabled' => true,
            'expires_at' => EstimatedActivityDefinition::stamp($expires), 'created_at' => EstimatedActivityDefinition::stamp(CarbonImmutable::now('UTC'))]);
        file_put_contents(storage_path('estimated-activity-runtime.json'), json_encode(['run_id' => $id, 'runtime_hash' => $runtime,
            'signature' => EstimatedActivityDefinition::fingerprint($id.'|'.$runtime)], JSON_THROW_ON_ERROR), LOCK_EX);
        config(['estimated_activity.mode' => 'synthetic', 'estimated_activity.run_id' => $id]);

        return $id;
    }

    /** A sealed file alone cannot authorize an ordinary database or an ungranted identity. */
    public function loadRuntime(): void
    {
        if (! app()->environment('activity-acceptance') || ! app(EstimatedActivityGate::class)->isolated()) {
            return;
        }
        $path = storage_path('estimated-activity-runtime.json');
        $seal = is_file($path) ? json_decode(file_get_contents($path), true) : null;
        if (is_array($seal) && isset($seal['run_id'], $seal['runtime_hash'], $seal['signature'])
            && hash_equals(app(EstimatedActivityGate::class)->runtimeHash(), $seal['runtime_hash'])
            && hash_equals(EstimatedActivityDefinition::fingerprint($seal['run_id'].'|'.$seal['runtime_hash']), $seal['signature'])) {
            config(['estimated_activity.mode' => 'synthetic', 'estimated_activity.run_id' => $seal['run_id']]);
        }
    }

    public function grant(string $runId, Student|Administrator $fixture, CarbonImmutable $expires): string
    {
        $gate = app(EstimatedActivityGate::class);
        abort_unless($gate->isolated(), 403);

        return DB::transaction(function () use ($gate, $runId, $fixture, $expires): string {
            $fixture::query()->whereKey($fixture->id)->lockForUpdate()->firstOrFail();
            $run = DB::table('estimated_activity_runs')->where('id', $runId)->where('runtime_hash', $gate->runtimeHash())->lockForUpdate()->first();
            abort_unless($run && $run->enabled && $expires->isFuture() && $expires->lte(CarbonImmutable::parse($run->expires_at, 'UTC')), 403);
            $id = (string) Str::uuid();
            DB::table('estimated_activity_grants')->insert(['id' => $id, 'run_id' => $runId,
                'principal_type' => $fixture instanceof Student ? 'student' : 'admin', 'principal_id' => $fixture->id,
                'principal_fingerprint' => $gate->principalFingerprint($fixture->fresh()), 'expires_at' => EstimatedActivityDefinition::stamp($expires)]);

            return $id;
        }, 5);
    }

    /** Every transition advances generation, including re-enablement. Explicit timers are untouched. */
    public function setEnabled(string $runId, bool $enabled): void
    {
        abort_unless(app(EstimatedActivityGate::class)->isolated(), 403);
        DB::transaction(function () use ($runId, $enabled): void {
            DB::table('estimated_activity_runs')->where('id', $runId)->where('runtime_hash', app(EstimatedActivityGate::class)->runtimeHash())->lockForUpdate()->firstOrFail();
            DB::table('estimated_activity_runs')->where('id', $runId)->increment('generation', 1, ['enabled' => $enabled]);
            $now = EstimatedActivityDefinition::stamp(CarbonImmutable::now('UTC'));
            DB::table('estimated_activity_coverage')->where('run_id', $runId)->whereNull('ended_at')->update(['ended_at' => $now, 'end_reason' => 'generation_changed']);
            DB::table('estimated_activity_streams')->whereIn('grant_id', DB::table('estimated_activity_grants')->select('id')->where('run_id', $runId))->delete();
        }, 5);
    }
}
