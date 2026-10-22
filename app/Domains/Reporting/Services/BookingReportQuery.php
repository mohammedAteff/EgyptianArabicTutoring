<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class BookingReportQuery
{
    public function __construct(private TimezoneService $timezones) {}

    /** @param array<string, mixed> $filters
     * @return Builder<Booking>
     */
    public function query(CarbonInterface $start, CarbonInterface $end, ?string $status = null, array $filters = []): Builder
    {
        $query = Booking::query()
            ->with(['contact', 'sessionType'])
            ->whereBetween('start_at_utc', [$start, $end])
            ->when($filters['source'] ?? null, fn ($q, $source) => $q->where('source', $source))
            ->when($filters['campaign'] ?? null, fn ($q, $campaign) => $q->where('campaign', $campaign));

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query->orderByDesc('start_at_utc')->orderByDesc('id');
    }

    /** @return array<string, string> */
    public function row(Booking $b, ?string $businessTz = null): array
    {
        $businessTz ??= $this->timezones->getBusinessTimezone();
        $cairoStart = $b->start_at_utc->copy()->setTimezone($businessTz);
        $studentStart = $b->customer_timezone
            ? $b->start_at_utc->copy()->setTimezone($b->customer_timezone)
            : $cairoStart;

        return [
            'booking_code' => $b->confirmation_token,
            'customer_name' => $b->contact->name ?? 'Unknown',
            'customer_email' => $b->contact->display_email ?? $b->contact->email ?? '',
            'session_title' => $b->sessionType->title ?? 'Arabic Lesson',
            'status' => ucfirst(str_replace('_', ' ', $b->status)),
            'lesson_date_cairo' => $cairoStart->format('Y-m-d'),
            'lesson_time_cairo' => $cairoStart->format('H:i').' '.($businessTz === 'Africa/Cairo' ? 'Cairo' : $businessTz),
            'lesson_time_student' => $studentStart->format('H:i').' ('.($b->customer_timezone ?: $businessTz).')',
            'source' => $b->source ?: 'Direct / Organic',
            'campaign' => $b->campaign ?: '—',
            'content' => $b->content ?: '—',
            'referrer' => $b->referrer ?: '—',
            'touch_at' => $b->touch_at ? $b->touch_at->format('Y-m-d H:i') : '—',
            'created_at' => $b->created_at->format('Y-m-d H:i'),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return \Generator<int, array<string, string>>
     */
    public function rows(CarbonInterface $start, CarbonInterface $end, ?string $status = null, array $filters = []): \Generator
    {
        $timezone = $this->timezones->getBusinessTimezone();
        foreach ($this->query($start, $end, $status, $filters)->lazy(250) as $booking) {
            yield $this->row($booking, $timezone);
        }
    }
}
