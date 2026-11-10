<?php

namespace App\Domains\Reporting\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

final readonly class TutorWorkContext
{
    public const VERSION = 'tutor-work-v3-estimated';

    public function __construct(public int $actor, public StudentInsightsContext $period, public int $workspace, public string $category = 'all', public string $source = 'all', public string $state = 'all', public string $grouping = 'daily', public string $dataset = 'summary') {}

    public static function fromRequest(Request $request, int $actor): self
    {
        app(TutorWorkService::class)->authorize($actor);
        $workspace = app(TutorWorkWorkspace::class)->ready();
        $data = $request->validate(['category' => ['nullable', Rule::in(['all', 'unresolved_overlap', ...array_keys(TutorWorkInput::CATEGORIES)])], 'source' => ['nullable', Rule::in(['all', 'recorded_session', 'timer', 'manual', 'estimated_admin'])], 'state' => ['nullable', Rule::in(['all', 'accepted', 'needs_review', 'excluded', 'provisional'])], 'grouping' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])], 'dataset' => ['nullable', Rule::in(['summary', 'records', 'allocations'])], 'student_id' => ['prohibited']]);
        $periodRequest = $request->duplicate(Arr::only($request->query(), ['range', 'start_date', 'end_date', 'timezone', 'format', 'page']));
        $base = StudentInsightsContext::fromRequest($periodRequest, null, true);
        $period = new StudentInsightsContext(null, $base->timezone, 'all', $base->range, $base->startDate, $base->endDate, $base->from, $base->until, $base->generatedAt->startOfSecond(), $base->month);

        return new self($actor, $period, $workspace->id, $data['category'] ?? 'all', $data['source'] ?? 'all', $data['state'] ?? 'all', $data['grouping'] ?? 'daily', $data['dataset'] ?? 'summary');
    }

    public function matches(string $category, string $source): bool
    {
        return ($this->category === 'all' || $this->category === $category) && ($this->source === 'all' || $this->source === $source);
    }

    public function filters(): array
    {
        return Arr::except($this->period->filters(), ['student_id', 'category']) + ['category' => $this->category, 'source' => $this->source, 'state' => $this->state, 'grouping' => $this->grouping, 'dataset' => $this->dataset];
    }

    public function metadata(): array
    {
        $measurement = ['measurement_definition' => EstimatedActivityDefinition::VERSION, 'estimated_placement' => 'Online server receipt-time approximation; partial measurement, not attention or productivity; no video/off-platform time. Exact microsecond boundaries; cumulative remainders apportion integer seconds across atomic allocations, without moving boundaries or bridging gaps.', 'source_precedence' => 'recorded_session > manual > stopped accepted timer > estimated_admin; normalize shared estimates before precedence; filters afterward'];

        return ['workspace_id' => $this->workspace, 'workspace_key' => TutorWorkWorkspace::KEY, 'workspace_scope' => 'Shared sole-tutor business ledger; currently eligible Super Admins only', 'requesting_actor_id' => $this->actor, 'teaching_scope' => 'Retained service-wide completed schedules included once', 'historical_coverage' => 'Only explicitly adopted legacy work is included; preserved legacy scopes remain outside this ledger', 'definition_version' => self::VERSION, 'start_date' => $this->period->startDate, 'end_date' => $this->period->endDate, 'timezone' => $this->period->timezone, 'utc_start_inclusive' => $this->period->from->toIso8601String(), 'utc_end_requested_exclusive' => $this->period->until->toIso8601String(), 'utc_end_effective_exclusive' => $this->period->effectiveUntil()->toIso8601String(), 'generated_at' => $this->period->generatedAt->toIso8601String(), 'category_filter' => $this->category, 'source_filter' => $this->source, 'history_state_filter' => $this->state, 'grouping' => $this->grouping, 'limitations' => 'Recorded schedules, explicit declarations and bounded estimates, not observed productivity. Estimates are read-only; unmeasured time is unavailable, not zero. Reconcile all accepted placed sources before display filters; state filters affect history only. Gross, union and duplicate duration cover all accepted sources; allocated and unplaced values match display filters. Unplaced dates retain original timezone and never join the union. Running, paused, needs-review and excluded work never contributes. Phase 03 attributes whole appointments to scheduled start; this workload report clips intersecting intervals to the selected period. Current retained source data; no historical completeness or snapshot-isolation claim.'] + $measurement;
    }
}
