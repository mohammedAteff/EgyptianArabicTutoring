<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportPeriod
{
    public const RANGES = ['today' => 'Today', '7d' => '7 Days', '30d' => '30 Days', '90d' => '90 Days', 'month' => 'This Month to Date', 'this_month' => 'This Month', 'last_month' => 'Last Month', 'custom' => 'Custom'];

    /** @return array<string, mixed> */
    public function filters(Request $request, array $rules = []): array
    {
        $filters = $request->validate(array_merge([
            'range' => ['nullable', Rule::in(array_keys(self::RANGES))],
            'start_date' => ['nullable', 'required_if:range,custom', 'required_with:end_date', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'required_if:range,custom', 'required_with:start_date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'format' => ['nullable', Rule::in(['csv', 'xlsx'])],
        ], $rules));
        $filters['range'] = ! empty($filters['start_date']) && ! empty($filters['end_date']) ? 'custom' : ($filters['range'] ?? '30d');

        return $filters;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function bounds(array $filters): array
    {
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        $now = CarbonImmutable::now($timezone);
        [$start, $end] = match ($filters['range'] ?? '30d') {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            '7d' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            '90d' => [$now->subDays(89)->startOfDay(), $now->endOfDay()],
            'month' => [$now->startOfMonth(), $now->endOfDay()],
            'this_month' => [$now->startOfMonth(), $now->endOfMonth()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'custom' => [CarbonImmutable::parse($filters['start_date'], $timezone)->startOfDay(), CarbonImmutable::parse($filters['end_date'], $timezone)->endOfDay()],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
        };
        if ($start->diffInDays($end) > 1825) {
            throw ValidationException::withMessages(['end_date' => 'Choose a period of at most five years.']);
        }

        return [$start->utc(), $end->utc()];
    }

    /** @return array<string, array<mixed>> */
    public function fields(): array
    {
        return ['range' => ['Date period', self::RANGES], 'start_date' => ['Date from (business time)', 'date'], 'end_date' => ['Date through (business time)', 'date']];
    }
}
