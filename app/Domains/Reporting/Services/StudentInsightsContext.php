<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Local dates are inclusive; UTC bounds are half open. No stored snapshot. */
final readonly class StudentInsightsContext
{
    public const VERSION = 'student-insights-v2-estimated';

    public function __construct(
        public ?int $studentId,
        public string $timezone,
        public string $category,
        public string $range,
        public string $startDate,
        public string $endDate,
        public CarbonImmutable $from,
        public CarbonImmutable $until,
        public CarbonImmutable $generatedAt,
        public string $month,
    ) {}

    public static function fromRequest(Request $request, ?Student $student, bool $admin = false): self
    {
        $zones = app(TimezoneService::class);
        $data = $request->validate([
            'student_id' => $admin ? ['nullable', 'integer', 'min:1'] : ['prohibited'],
            'range' => ['nullable', Rule::in(array_diff(array_keys(ReportPeriod::RANGES), ['this_month']))],
            'start_date' => ['nullable', 'required_if:range,custom', 'required_with:end_date', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'required_if:range,custom', 'required_with:start_date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'timezone' => ['nullable', 'timezone:all'],
            'category' => ['nullable', Rule::in(['all', 'sessions', 'homework', 'lms'])],
            'month' => ['nullable', 'date_format:Y-m'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'records_page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'q' => ['nullable', 'string', 'max:100'],
            'format' => ['nullable', Rule::in(['csv', 'xlsx'])],
            'dataset' => ['nullable', Rule::in(['aggregate_summary', 'individual_summary', 'activity_records', 'estimated_allocations'])],
        ], [
            'start_date.required_if' => __('insights.custom_dates_required'),
            'end_date.required_if' => __('insights.custom_dates_required'),
            'start_date.required_with' => __('insights.date_pair_required'),
            'end_date.required_with' => __('insights.date_pair_required'),
            'student_id.prohibited' => __('insights.own_account_only'),
        ], array_merge(
            array_combine(['start_date', 'end_date', 'timezone', 'range', 'category', 'month'], array_map(fn (string $key): string => __('insights.'.$key), ['start_date', 'end_date', 'timezone', 'range', 'category', 'month'])),
            __('insights.validation_attributes'),
        ));
        $timezone = $data['timezone'] ?? null;
        if (! $timezone && ! $admin) {
            foreach ([$request->session()->get('student_display_timezone'), $student?->preferred_timezone] as $candidate) {
                if (is_string($candidate) && $zones->isValid($candidate)) {
                    $timezone = $candidate;
                    break;
                }
            }
        }
        $timezone ??= $zones->getBusinessTimezone();
        $generated = CarbonImmutable::now('UTC');
        $today = $generated->setTimezone($timezone)->startOfDay();
        $range = $data['range'] ?? (isset($data['start_date'], $data['end_date']) ? 'custom' : 'month');
        [$start, $end] = match ($range) {
            'today' => [$today, $today],
            '7d' => [$today->subDays(6), $today],
            '30d' => [$today->subDays(29), $today],
            '90d' => [$today->subDays(89), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->startOfMonth()->subDay()],
            'custom' => [CarbonImmutable::parse($data['start_date'], $timezone), CarbonImmutable::parse($data['end_date'], $timezone)],
            default => [$today->startOfMonth(), $today],
        };
        if ($start->gt($today)) {
            throw ValidationException::withMessages(['start_date' => __('insights.future_error')]);
        }
        if ($start->diffInDays($end) + 1 > 1825) {
            throw ValidationException::withMessages(['end_date' => __('insights.period_error')]);
        }
        $month = $data['month'] ?? $end->min($today)->format('Y-m');
        if ($month < $start->format('Y-m') || $month > $end->format('Y-m')) {
            throw ValidationException::withMessages(['month' => __('insights.month_error')]);
        }

        return new self($student?->id, $timezone, $data['category'] ?? 'all', $range, $start->toDateString(), $end->toDateString(), $start->utc(), $end->addDay()->startOfDay()->utc(), $generated, $month);
    }

    public function effectiveUntil(): CarbonImmutable
    {
        return $this->until->min($this->generatedAt);
    }

    public function days(): int
    {
        return (int) CarbonImmutable::parse($this->startDate)->diffInDays(CarbonImmutable::parse($this->endDate)) + 1;
    }

    public function previous(): self
    {
        $end = CarbonImmutable::parse($this->startDate, $this->timezone)->subDay();
        $start = $end->subDays($this->days() - 1);

        return new self($this->studentId, $this->timezone, $this->category, 'custom', $start->toDateString(), $end->toDateString(), $start->utc(), $end->addDay()->utc(), $this->generatedAt, $end->format('Y-m'));
    }

    public function filters(): array
    {
        return ['range' => $this->range, 'start_date' => $this->range === 'custom' ? $this->startDate : null, 'end_date' => $this->range === 'custom' ? $this->endDate : null, 'timezone' => $this->timezone, 'category' => $this->category, 'student_id' => $this->studentId];
    }

    public function metadata(): array
    {
        $measurement = ['measurement_definition' => EstimatedActivityDefinition::VERSION, 'estimated_limitations' => 'Partial measurement of eligible first-party text/image, assessments, shared homework and lesson note editing; excludes video, games, external and off-platform study; not proof of attention or learning; exact microsecond boundaries; cumulative remainders apportion whole seconds per student without moving boundaries or bridging gaps'];

        return ['scope' => $this->studentId ? 'individual' : 'aggregate', 'student_id' => $this->studentId, 'start_date' => $this->startDate, 'end_date' => $this->endDate, 'utc_start_inclusive' => $this->from->toIso8601String(), 'utc_end_requested_exclusive' => $this->until->toIso8601String(), 'utc_end_effective_exclusive' => $this->effectiveUntil()->toIso8601String(), 'timezone' => $this->timezone, 'category' => $this->category, 'metric_version' => self::VERSION, 'generated_at' => $this->generatedAt->toIso8601String(), 'period_state' => $this->until->gt($this->generatedAt) ? 'incomplete' : 'complete', 'local_date_count' => $this->days(), 'population' => 'Current retained accounts; suspended, unverified and withdrawn included; deleted/erased and merged-away excluded; no enrollment requirement.', 'coverage' => 'unknown; retained records only', 'limitations' => 'Unlinked legacy records excluded; current ownership; future occurrences excluded; homework submission timing may be synthesized from legacy updated_at; current progress is independent of period.'] + $measurement;
    }
}
