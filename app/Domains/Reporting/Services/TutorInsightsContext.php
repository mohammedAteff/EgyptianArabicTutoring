<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Students\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

final readonly class TutorInsightsContext
{
    public const VERSION = 'tutor-insights-v1';

    public const OUTCOMES = ['completed' => 'Completed', 'cancelled' => 'Canceled', 'no_show' => 'No-show', 'confirmed' => 'Confirmed but not completed', 'pending' => 'Pending', 'held' => 'Held'];

    public const ACTIVITIES = ['cancellations' => 'Cancellation actions', 'reschedules' => 'Reschedule actions', 'lms_reviews' => 'LMS final-review records', 'feedback' => 'Feedback activity'];

    public function __construct(
        public StudentInsightsContext $period,
        public string $view,
        public ?int $sessionTypeId,
        public ?string $outcome,
        public string $grouping,
        public string $activity,
    ) {}

    public static function fromRequest(Request $request, ?Student $student): self
    {
        $view = $request->query('view') ?: 'teaching';
        $teaching = $view === 'teaching';
        $data = $request->validate([
            'view' => ['nullable', Rule::in(['teaching', 'operations'])],
            'session_type_id' => $teaching ? ['nullable', 'integer', 'exists:session_types,id'] : ['prohibited'],
            'outcome' => $teaching ? ['nullable', Rule::in(array_keys(self::OUTCOMES))] : ['prohibited'],
            'grouping' => $teaching ? ['nullable', Rule::in(['weekly', 'monthly'])] : ['prohibited'],
            'activity' => $teaching ? ['prohibited'] : ['nullable', Rule::in(array_merge(['all'], array_keys(self::ACTIVITIES)))],
            'dataset' => ['nullable', Rule::in($teaching ? ['summary', 'appointments', 'upcoming'] : ['summary', 'operations'])],
            'page' => ['nullable', 'integer', 'between:1,1000000'],
            'appointments_page' => ['nullable', 'integer', 'between:1,1000000'],
            'upcoming_page' => ['nullable', 'integer', 'between:1,1000000'],
            'students_page' => ['nullable', 'integer', 'between:1,1000000'],
            'q' => ['nullable', 'string', 'max:100'],
        ], ['prohibited' => 'The :attribute filter does not apply to the selected report view.']);
        $periodRequest = $request->duplicate(Arr::only($request->query(), ['range', 'start_date', 'end_date', 'timezone', 'student_id', 'format']));
        $period = StudentInsightsContext::fromRequest($periodRequest, $student, true);

        return new self($period, $view, isset($data['session_type_id']) ? (int) $data['session_type_id'] : null, $data['outcome'] ?? null, $data['grouping'] ?? ($period->days() > 90 ? 'monthly' : 'weekly'), $data['activity'] ?? 'all');
    }

    public function previous(): self
    {
        return new self($this->period->previous(), $this->view, $this->sessionTypeId, $this->outcome, $this->grouping, $this->activity);
    }

    public function teachingApplies(): bool
    {
        return $this->outcome === null || $this->outcome === 'completed';
    }

    public function upcomingApplies(): bool
    {
        return $this->outcome === null || $this->outcome === 'confirmed';
    }

    public function upcomingUntil(): CarbonImmutable
    {
        return $this->period->generatedAt->setTimezone($this->period->timezone)->startOfDay()->addDays(30)->utc();
    }

    public function filters(?string $view = null): array
    {
        $common = Arr::except($this->period->filters(), ['category']);
        if ($view !== null && $view !== $this->view) {
            return array_filter($common + ['view' => $view], fn ($value) => $value !== null);
        }

        return array_filter($common + ['view' => $this->view] + ($this->view === 'teaching'
            ? ['session_type_id' => $this->sessionTypeId, 'outcome' => $this->outcome, 'grouping' => $this->grouping]
            : ['activity' => $this->activity]), fn ($value) => $value !== null);
    }

    public function metadata(): array
    {
        return [
            'view' => $this->view, 'range' => $this->period->range, 'student_id' => $this->period->studentId,
            'session_type_id' => $this->sessionTypeId, 'outcome' => $this->outcome, 'grouping' => $this->view === 'teaching' ? $this->grouping : null, 'activity' => $this->view === 'operations' ? $this->activity : null,
            'requested_start_date' => $this->period->startDate, 'requested_end_date' => $this->period->endDate,
            'utc_start_inclusive' => $this->period->from->toIso8601String(), 'utc_end_requested_exclusive' => $this->period->until->toIso8601String(), 'utc_end_effective_exclusive' => $this->period->effectiveUntil()->toIso8601String(),
            'timezone' => $this->period->timezone, 'generated_at' => $this->period->generatedAt->toIso8601String(), 'definition_version' => self::VERSION,
            'period_state' => $this->period->until->gt($this->period->generatedAt) ? 'incomplete' : 'complete',
            'upcoming_start_inclusive' => $this->period->generatedAt->toIso8601String(), 'upcoming_end_exclusive' => $this->upcomingUntil()->toIso8601String(),
            'coverage' => 'Unknown historical completeness; current retained records, not a historical database snapshot.',
            'population' => 'Canonical retained students including suspended, unverified and withdrawn; genuine unlinked bookings included service-wide; erased/deleted and merged-away ownership excluded. Feedback follows current available-conversation visibility.',
            'provider_basis' => TutorInsightsReadModel::PROVIDER_BASIS,
            'duration_basis' => 'Whole positive stored UTC interval attributed to scheduled start; overlapping appointments remain distinct; excludes buffers; not total working time.',
            'diagnostic_basis' => 'Canonical diagnostic-session type only; consultation purpose is not proven, including package-funded bookings; subtotal is included in completed total.',
            'operational_limitations' => 'Canonical source records only; action actors may be student or system. LMS latest final-review dates can overwrite history; ordinary homework review history excluded. Feedback units are conversations, administrator messages and resolution transitions; never summed as work time.',
        ];
    }
}
