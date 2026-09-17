<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Resources\Models\Resource;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(
        protected TimezoneService $timezoneService
    ) {}

    public function getTrafficReport(CarbonInterface $start, CarbonInterface $end, ?string $source = null): array
    {
        $query = AnalyticsEvent::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('is_bot', false);

        if ($source) {
            $query->where('utm_source', $source);
        }

        // Daily traffic totals grouped strictly by date (one row per date)
        $dailyTotals = (clone $query)
            ->select(
                DB::raw('DATE(created_at) as report_date'),
                DB::raw('COUNT(DISTINCT visitor_token) as visitors'),
                DB::raw('COUNT(DISTINCT session_token) as sessions'),
                DB::raw('COUNT(CASE WHEN event_name = "page_view" THEN 1 END) as page_views')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderByDesc('report_date')
            ->get();

        // Calculate actual top acquisition source for each day
        $sourcesByDate = (clone $query)
            ->select(
                DB::raw('DATE(created_at) as report_date'),
                DB::raw('COALESCE(utm_source, "direct") as source_name'),
                DB::raw('COUNT(*) as total_events')
            )
            ->groupBy(DB::raw('DATE(created_at)'), 'source_name')
            ->orderByDesc('total_events')
            ->get()
            ->groupBy('report_date');

        $dailyRows = $dailyTotals->map(function ($row) use ($sourcesByDate) {
            $top = $sourcesByDate->get($row->report_date)?->first();
            $row->top_source = $top ? $top->source_name : 'direct';

            return $row;
        });

        $totalVisitors = (clone $query)->whereNotNull('visitor_token')->distinct('visitor_token')->count('visitor_token');
        $totalSessions = (clone $query)->whereNotNull('session_token')->distinct('session_token')->count('session_token');
        $totalPageViews = (clone $query)->where('event_name', 'page_view')->count();

        $diffDays = $start->diffInDays($end) ?: 1;
        $prevStart = CarbonImmutable::parse($start)->subDays($diffDays);
        $prevEnd = CarbonImmutable::parse($start)->subSecond();

        $prevQuery = AnalyticsEvent::query()
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->where('is_bot', false);

        if ($source) {
            $prevQuery->where('utm_source', $source);
        }

        $prevVisitors = (clone $prevQuery)->whereNotNull('visitor_token')->distinct('visitor_token')->count('visitor_token');
        $prevSessions = (clone $prevQuery)->whereNotNull('session_token')->distinct('session_token')->count('session_token');
        $prevPageViews = (clone $prevQuery)->where('event_name', 'page_view')->count();

        return [
            'rows' => $dailyRows,
            'summary' => [
                'visitors' => $totalVisitors,
                'sessions' => $totalSessions,
                'page_views' => $totalPageViews,
                'prev_visitors' => $prevVisitors,
                'prev_sessions' => $prevSessions,
                'prev_page_views' => $prevPageViews,
                'visitor_change_pct' => $prevVisitors > 0 ? round((($totalVisitors - $prevVisitors) / $prevVisitors) * 100, 1) : 0,
            ],
        ];
    }

    public function getBookingsReport(CarbonInterface $start, CarbonInterface $end, ?string $status = null): array
    {
        $query = Booking::query()
            ->with(['contact', 'sessionType'])
            ->whereBetween('start_at_utc', [$start, $end]);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $bookings = $query->orderByDesc('start_at_utc')->get();

        $businessTz = $this->timezoneService->getBusinessTimezone();

        $rows = $bookings->map(function (Booking $b) use ($businessTz) {
            $cairoStart = $b->start_at_utc->copy()->setTimezone($businessTz);
            $studentStart = $b->customer_timezone
                ? $b->start_at_utc->copy()->setTimezone($b->customer_timezone)
                : $cairoStart;

            return [
                'booking_code' => $b->confirmation_token,
                'customer_name' => $b->contact?->name ?? 'Unknown',
                'customer_email' => $b->contact?->display_email ?? $b->contact?->email ?? '',
                'session_title' => $b->sessionType?->title ?? 'Arabic Lesson',
                'status' => ucfirst(str_replace('_', ' ', $b->status)),
                'lesson_date_cairo' => $cairoStart->format('Y-m-d'),
                'lesson_time_cairo' => $cairoStart->format('H:i').' Cairo',
                'lesson_time_student' => $studentStart->format('H:i').' ('.($b->customer_timezone ?: 'Cairo').')',
                'source' => $b->source ?: 'Direct / Organic',
                'campaign' => $b->campaign ?: '—',
                'created_at' => $b->created_at->format('Y-m-d H:i'),
            ];
        });

        return [
            'rows' => $rows,
            'count' => $bookings->count(),
            'confirmed_count' => $bookings->where('status', 'confirmed')->count(),
            'completed_count' => $bookings->where('status', 'completed')->count(),
            'cancelled_count' => $bookings->where('status', 'cancelled')->count(),
        ];
    }

    public function getResourcesReport(CarbonInterface $start, CarbonInterface $end): array
    {
        $resources = Resource::query()
            ->withCount([
                'requests' => fn ($q) => $q->whereBetween('created_at', [$start, $end]),
                'downloads' => fn ($q) => $q->whereBetween('created_at', [$start, $end]),
            ])
            ->orderByDesc('requests_count')
            ->get();

        $rows = $resources->map(function (Resource $r) {
            $reqCount = $r->requests_count;
            $dlCount = $r->downloads_count;
            $conversion = $reqCount > 0 ? round(($dlCount / $reqCount) * 100, 1) : 0;

            return [
                'title' => $r->title,
                'slug' => $r->slug,
                'file_type' => strtoupper($r->file_type),
                'status' => ucfirst($r->status),
                'requests' => $reqCount,
                'downloads' => $dlCount,
                'conversion_pct' => $conversion.'%',
            ];
        });

        return [
            'rows' => $rows,
            'total_requests' => $resources->sum('requests_count'),
            'total_downloads' => $resources->sum('downloads_count'),
        ];
    }

    public function getSocialReport(CarbonInterface $start, CarbonInterface $end): array
    {
        $socialEvents = [
            'whatsapp_clicked',
            'telegram_clicked',
            'social_link_clicked',
            'outbound_link_clicked',
        ];

        $rows = AnalyticsEvent::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('event_name', $socialEvents)
            ->where('is_bot', false)
            ->select(
                'event_name',
                'page',
                DB::raw('DATE(created_at) as report_date'),
                DB::raw('COUNT(*) as clicks')
            )
            ->groupBy('event_name', 'page', DB::raw('DATE(created_at)'))
            ->orderByDesc('report_date')
            ->get()
            ->map(function ($row) {
                $platform = match ($row->event_name) {
                    'whatsapp_clicked' => 'WhatsApp',
                    'telegram_clicked' => 'Telegram',
                    'social_link_clicked' => 'Social Channel',
                    default => 'Outbound Link',
                };

                return [
                    'platform' => $platform,
                    'event_name' => $row->event_name,
                    'page' => $row->page ?: '/',
                    'date' => $row->report_date,
                    'clicks' => $row->clicks,
                ];
            });

        return [
            'rows' => $rows,
            'total_clicks' => $rows->sum('clicks'),
            'whatsapp_clicks' => $rows->where('platform', 'WhatsApp')->sum('clicks'),
            'telegram_clicks' => $rows->where('platform', 'Telegram')->sum('clicks'),
        ];
    }

    public function getEventsReport(CarbonInterface $start, CarbonInterface $end, ?string $eventName = null): array
    {
        $query = AnalyticsEvent::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('is_bot', false);

        if ($eventName && $eventName !== 'all') {
            $query->where('event_name', $eventName);
        }

        $rows = (clone $query)
            ->select(
                'event_name',
                DB::raw('COALESCE(page, "/") as page'),
                DB::raw('COALESCE(utm_source, "direct") as source'),
                DB::raw('DATE(created_at) as report_date'),
                DB::raw('COUNT(*) as event_count')
            )
            ->groupBy('event_name', 'page', 'utm_source', DB::raw('DATE(created_at)'))
            ->orderByDesc('report_date')
            ->get();

        return [
            'rows' => $rows,
            'total_events' => $rows->sum('event_count'),
            'available_events' => AnalyticsService::ALLOWED_EVENTS,
        ];
    }
}
