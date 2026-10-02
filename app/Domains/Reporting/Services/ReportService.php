<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Models\DailyMetric;
use App\Domains\Analytics\Models\MarketingTouch;
use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Resources\Models\Resource;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(
        protected TimezoneService $timezoneService
    ) {}

    public function getTrafficReport(CarbonInterface $start, CarbonInterface $end, ?string $source = null): array
    {
        $current = $this->resolvePeriodTrafficMetrics($start, $end, $source);

        $startCairoStr = CarbonImmutable::parse($start)->setTimezone(app(TimezoneService::class)->getBusinessTimezone())->toDateString();
        $endCairoStr = CarbonImmutable::parse($end)->setTimezone(app(TimezoneService::class)->getBusinessTimezone())->toDateString();

        $dateCount = max(1, (new \DateTimeImmutable($startCairoStr))->diff(new \DateTimeImmutable($endCairoStr))->days + 1);
        $prevStartDateStr = (new \DateTimeImmutable($startCairoStr))->sub(new \DateInterval("P{$dateCount}D"))->format('Y-m-d');
        $prevEndDateStr = (new \DateTimeImmutable($startCairoStr))->sub(new \DateInterval('P1D'))->format('Y-m-d');

        $prevStart = CarbonImmutable::parse($prevStartDateStr, app(TimezoneService::class)->getBusinessTimezone())->startOfDay()->setTimezone('UTC');
        $prevEnd = CarbonImmutable::parse($prevEndDateStr, app(TimezoneService::class)->getBusinessTimezone())->endOfDay()->setTimezone('UTC');

        $prev = $this->resolvePeriodTrafficMetrics($prevStart, $prevEnd, $source);

        // Only compute comparison growth rate when current and previous periods have identical counting bases
        $isComparableVisitors = ($current['visitors_basis'] === $prev['visitors_basis']);
        $visitorChangePct = ($isComparableVisitors && $prev['visitors'] > 0)
            ? round((($current['visitors'] - $prev['visitors']) / $prev['visitors']) * 100, 1)
            : ($isComparableVisitors ? 0.0 : null);

        return [
            'rows' => $current['rows'],
            'summary' => [
                'visitors' => $current['visitors'],
                'visitors_basis' => $current['visitors_basis'],
                'visitors_is_daily_sum' => $current['visitors_is_daily_sum'],
                'sessions' => $current['sessions'],
                'page_views' => $current['page_views'],
                'prev_visitors' => $prev['visitors'],
                'prev_visitors_basis' => $prev['visitors_basis'],
                'prev_visitors_is_daily_sum' => $prev['visitors_is_daily_sum'],
                'is_comparable_visitors' => $isComparableVisitors,
                'prev_sessions' => $prev['sessions'],
                'prev_page_views' => $prev['page_views'],
                'prev_start' => $prevStart,
                'prev_end' => $prevEnd,
                'visitor_change_pct' => $visitorChangePct,
            ],
        ];
    }

    /**
     * Resolves reconciled daily rows and aggregate summary metrics for a given date range.
     * Reconciles raw events/sessions with durable daily rollups when raw data has been pruned.
     *
     * @return array{
     *     rows: Collection,
     *     visitors: int,
     *     sessions: int,
     *     page_views: int,
     *     visitors_basis: string,
     *     visitors_is_daily_sum: bool,
     *     has_pruned_dates: bool
     * }
     */
    protected function resolvePeriodTrafficMetrics(CarbonInterface $start, CarbonInterface $end, ?string $source = null): array
    {
        $query = $this->nonBotEventQuery()
            ->whereBetween('created_at', [$start, $end]);

        if ($source) {
            $query->where('utm_source', $source);
        }

        $dateExpr = $this->getBusinessDateExpression('created_at', $start, $end);

        // Daily traffic totals grouped strictly by Cairo calendar date (one row per date)
        $dailyTotals = (clone $query)
            ->select(
                DB::raw("{$dateExpr} as report_date"),
                DB::raw('COUNT(DISTINCT visitor_token) as visitors'),
                DB::raw('COUNT(CASE WHEN event_name = "page_view" THEN 1 END) as page_views')
            )
            ->groupBy('report_date')
            ->orderByDesc('report_date')
            ->toBase()->get();

        // Sessions strictly attributed by session started_at in Cairo day (matching daily_metrics.sessions)
        $sessionDateExpr = $this->getBusinessDateExpression('started_at', $start, $end);
        $sessionsByDate = $this->nonBotSessionQuery()
            ->whereBetween('started_at', [$start, $end])
            ->when($source, fn ($q) => $q->where('utm_source', $source))
            ->select(
                DB::raw("{$sessionDateExpr} as report_date"),
                DB::raw('COUNT(*) as sessions_count')
            )
            ->groupBy('report_date')
            ->pluck('sessions_count', 'report_date');

        // Calculate actual top acquisition source for each day
        $sourcesByDate = (clone $query)
            ->select(
                DB::raw("{$dateExpr} as report_date"),
                DB::raw('COALESCE(utm_source, "direct") as source_name'),
                DB::raw('COUNT(*) as total_events')
            )
            ->groupBy('report_date', 'source_name')
            ->orderByDesc('total_events')
            ->toBase()->get()
            ->groupBy('report_date');

        $sessionQuery = $this->nonBotSessionQuery()->with('visitor')->whereBetween('started_at', [$start, $end])->when($source, fn ($q) => $q->where('utm_source', $source));
        $sessionRows = $sessionQuery->get();
        foreach ($sessionsByDate as $date => $count) {
            if (! $dailyTotals->firstWhere('report_date', (string) $date)) {
                $dailyTotals->push((object) ['report_date' => (string) $date, 'visitors' => 0, 'page_views' => 0]);
            }
        }
        $eventTokens = (clone $query)->get(['visitor_token', 'created_at']);
        foreach ($dailyTotals as $row) {
            $tokens = $eventTokens->filter(fn ($event) => $event->created_at->copy()->setTimezone($this->timezoneService->getBusinessTimezone())->toDateString() === $row->report_date)->pluck('visitor_token');
            $row->visitors = $tokens->merge($sessionRows->filter(fn ($session) => $session->started_at->copy()->setTimezone($this->timezoneService->getBusinessTimezone())->toDateString() === $row->report_date)->map(fn ($session) => $session->visitor?->visitor_token))->filter()->unique()->count();
        }

        $dailyRows = $dailyTotals->map(function ($row) use ($sourcesByDate, $sessionsByDate) {
            $top = $sourcesByDate->get($row->report_date)?->first();
            $row->top_source = $top ? $top->source_name : 'direct';
            $row->sessions = (int) ($sessionsByDate->get($row->report_date) ?? 0);

            return $row;
        });

        // Merge with durable daily_metrics rollups for dates where raw events were pruned (EDITS V1 §9, §13-15)
        $startCairoDate = CarbonImmutable::parse($start)->setTimezone(app(TimezoneService::class)->getBusinessTimezone())->toDateString();
        $endCairoDate = CarbonImmutable::parse($end)->setTimezone(app(TimezoneService::class)->getBusinessTimezone())->toDateString();

        $historicalMetrics = DailyMetric::where('reporting_timezone', $this->timezoneService->getBusinessTimezone())->whereBetween('metric_date', [$startCairoDate, $endCairoDate])
            ->get()
            ->groupBy(function ($metric) {
                return $metric->metric_date instanceof CarbonInterface
                    ? $metric->metric_date->toDateString()
                    : substr((string) $metric->metric_date, 0, 10);
            });

        $hasPrunedDates = false;
        foreach ($historicalMetrics as $mDate => $metricsForDate) {
            $mDateStr = (string) $mDate;
            $day = CarbonImmutable::parse($mDateStr, $this->timezoneService->getBusinessTimezone());
            if ($start->greaterThan($day->startOfDay()->utc()) || $end->lessThan($day->endOfDay()->utc()->startOfSecond())) {
                continue;
            }
            $existingRow = $dailyRows->firstWhere('report_date', $mDateStr);

            $visitorsMetric = $source
                ? (int) $metricsForDate->where('metric_name', 'visitors_by_source')->where('dimension_value', $source)->sum('count')
                : (int) $metricsForDate->where('dimension_key', '')->where('metric_name', 'unique_visitors')->sum('count');

            $sessionsMetric = $source
                ? (int) $metricsForDate->where('metric_name', 'sessions_by_source')->where('dimension_value', $source)->sum('count')
                : (int) $metricsForDate->where('dimension_key', '')->where('metric_name', 'sessions')->sum('count');

            $pvMetric = $source
                ? (int) $metricsForDate->where('metric_name', 'page_views_by_source')->where('dimension_value', $source)->sum('count')
                : (int) ($metricsForDate->where('dimension_key', '')->where('metric_name', 'page_view')->sum('count') ?: $metricsForDate->where('metric_name', 'page_views')->sum('count'));

            $topSourceRow = $metricsForDate->where('metric_name', 'visitors_by_source')->sortByDesc('count')->first();
            $topSource = $source ?: ($topSourceRow?->dimension_value ?: 'direct');

            $hasMetrics = ($visitorsMetric > 0 || $sessionsMetric > 0 || $pvMetric > 0);

            $isPartiallyPruned = $existingRow && (
                $existingRow->visitors < $visitorsMetric ||
                $existingRow->sessions < $sessionsMetric ||
                $existingRow->page_views < $pvMetric
            );

            if ($hasMetrics && (! $existingRow || ($existingRow->visitors === 0 && $existingRow->page_views === 0 && $existingRow->sessions === 0) || $isPartiallyPruned)) {
                $hasPrunedDates = true;

                if ($existingRow) {
                    $existingRow->visitors = max($existingRow->visitors, $visitorsMetric);
                    $existingRow->sessions = max($existingRow->sessions, $sessionsMetric);
                    $existingRow->page_views = max($existingRow->page_views, $pvMetric);
                    $existingRow->top_source = $topSource;
                } else {
                    $dailyRows->push((object) [
                        'report_date' => $mDateStr,
                        'visitors' => $visitorsMetric,
                        'sessions' => $sessionsMetric,
                        'page_views' => $pvMetric,
                        'top_source' => $topSource,
                    ]);
                }
            }
        }

        $dailyRows = $dailyRows->sortByDesc('report_date')->values();

        $rawEventsExist = (clone $query)->exists();
        $rawSessionsExist = $this->nonBotSessionQuery()
            ->whereBetween('started_at', [$start, $end])
            ->when($source, fn ($q) => $q->where('utm_source', $source))
            ->exists();

        if (! $hasPrunedDates && ($rawEventsExist || $rawSessionsExist || $dailyRows->isEmpty())) {
            // Raw events retain exact distinct visitor identity across the full queried period
            $totalVisitors = $eventTokens->pluck('visitor_token')->merge($sessionRows->map(fn ($session) => $session->visitor?->visitor_token))->filter()->unique()->count();
            $totalSessions = $this->nonBotSessionQuery()
                ->whereBetween('started_at', [$start, $end])
                ->when($source, fn ($q) => $q->where('utm_source', $source))
                ->count();
            $totalPageViews = (clone $query)->where('event_name', 'page_view')->count();
            $visitorsBasis = 'exact_unique_visitors';
            $visitorsIsDailySum = false;
        } else {
            // Pruned history: additive metrics (sessions, page views) sum daily rollups;
            // distinct visitors across multi-day ranges cannot be deduplicated without raw identity.
            $totalVisitors = (int) $dailyRows->sum('visitors');
            $totalSessions = (int) $dailyRows->sum('sessions');
            $totalPageViews = (int) $dailyRows->sum('page_views');

            $isSingleDay = ($startCairoDate === $endCairoDate);
            $visitorsBasis = $isSingleDay ? 'exact_unique_visitors' : 'sum_of_daily_uniques';
            $visitorsIsDailySum = ! $isSingleDay;
        }

        return [
            'rows' => $dailyRows,
            'visitors' => $totalVisitors,
            'sessions' => $totalSessions,
            'page_views' => $totalPageViews,
            'visitors_basis' => $visitorsBasis,
            'visitors_is_daily_sum' => $visitorsIsDailySum,
            'has_pruned_dates' => $hasPrunedDates,
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
                'content' => $b->content ?: '—',
                'referrer' => $b->referrer ?: '—',
                'touch_at' => $b->touch_at ? $b->touch_at->format('Y-m-d H:i') : '—',
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

        $dateExpr = $this->getBusinessDateExpression('created_at', $start, $end);

        $platformExpr = "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.platform')), ''), CASE event_name WHEN 'whatsapp_clicked' THEN 'whatsapp' WHEN 'telegram_clicked' THEN 'telegram' WHEN 'social_link_clicked' THEN 'social channel' ELSE 'outbound link' END)";
        $placementExpr = "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.placement')), ''), 'unknown')";
        $languageExpr = "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.language')), ''), 'unknown')";
        $countryExpr = "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.detected_country_code')), ''), 'ZZ')";
        $query = $this->nonBotEventQuery()->whereBetween('created_at', [$start, $end])->whereIn('event_name', $socialEvents);
        $rows = (clone $query)
            ->select('event_name', 'page', 'utm_source', 'utm_medium', 'utm_campaign')
            ->selectRaw("{$dateExpr} as report_date, LOWER({$platformExpr}) as platform, {$placementExpr} as placement, {$languageExpr} as language, {$countryExpr} as country")
            ->selectRaw("COUNT(*) as clicks, COUNT(DISTINCT NULLIF(visitor_token, '')) as unique_visitors")
            ->groupBy('event_name', 'page', 'utm_source', 'utm_medium', 'utm_campaign', 'report_date', 'platform', 'placement', 'language', 'country')
            ->orderByDesc('report_date')->orderBy('platform')->toBase()->get()
            ->map(function ($row): array {
                $platform = match ($row->platform) {
                    'whatsapp' => 'WhatsApp', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', default => ucwords($row->platform),
                };

                return [
                    'platform' => $platform, 'placement' => $row->placement,
                    'event_name' => $row->event_name, 'page' => $row->page ?: '/',
                    'date' => $row->report_date, 'clicks' => (int) $row->clicks,
                    'unique_visitors' => (int) $row->unique_visitors,
                    'language' => $row->language, 'country' => $row->country,
                    'source' => $row->utm_source, 'medium' => $row->utm_medium, 'campaign' => $row->utm_campaign,
                ];
            });

        return [
            'rows' => $rows, 'total_clicks' => $rows->sum('clicks'),
            'unique_visitors' => (clone $query)->where('visitor_token', '!=', '')->distinct()->count('visitor_token'),
            'platform_totals' => $rows->groupBy('platform')->map(fn ($group): int => $group->sum('clicks')),
            'whatsapp_clicks' => $rows->where('platform', 'WhatsApp')->sum('clicks'),
            'telegram_clicks' => $rows->where('platform', 'Telegram')->sum('clicks'),
        ];
    }

    public function getEventsReport(CarbonInterface $start, CarbonInterface $end, ?string $eventName = null): array
    {
        $query = $this->nonBotEventQuery()
            ->whereBetween('created_at', [$start, $end]);

        if ($eventName && $eventName !== 'all') {
            $query->where('event_name', $eventName);
        }

        $dateExpr = $this->getBusinessDateExpression('created_at', $start, $end);

        $rows = (clone $query)
            ->select(
                'event_name',
                DB::raw('COALESCE(page, "/") as page'),
                DB::raw('COALESCE(utm_source, "direct") as source'),
                DB::raw("{$dateExpr} as report_date"),
                DB::raw('COUNT(*) as event_count')
            )
            ->groupBy('event_name', 'page', 'utm_source', 'report_date')
            ->orderByDesc('report_date')
            ->get();

        return [
            'rows' => $rows,
            'total_events' => $rows->sum('event_count'),
            'available_events' => AnalyticsService::ALLOWED_EVENTS,
        ];
    }

    /**
     * Granular Campaign & Content Attribution Drilldown (Section 19).
     * Campaign → Content → Visitor Count → Downstream Bookings (EDITS V1 §18B-19).
     *
     * Defines the campaign/content visitor cohort by eligible touch/arrival time within [$start, $end],
     * and counts subsequent attributed bookings for those same visitors within the 30-day attribution window.
     * Booking-date creation activity in the period is labeled separately.
     */
    public function getCampaignContentReport(CarbonInterface $start, CarbonInterface $end, ?string $campaign = null): array
    {
        // 1. Discover visitor campaign touches in [$start, $end] via AnalyticsEvent
        $eventsQuery = $this->nonBotEventQuery()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('utm_campaign');

        if ($campaign) {
            $eventsQuery->where('utm_campaign', $campaign);
        }

        $events = $eventsQuery
            ->select(
                'visitor_token',
                'utm_campaign',
                DB::raw('COALESCE(utm_content, "(not set)") as utm_content'),
                DB::raw('COALESCE(utm_source, "direct") as utm_source'),
                'created_at as touch_time'
            )
            ->toBase()->get();

        // 2. Discover marketing touches in [$start, $end] via MarketingTouch
        $touchesQuery = MarketingTouch::query()
            ->whereBetween('touch_at', [$start, $end])
            ->whereHas('visitor', function ($query): void {
                $query->where('is_bot', false);
            })
            ->whereNotNull('utm_campaign');

        if ($campaign) {
            $touchesQuery->where('utm_campaign', $campaign);
        }

        $touches = $touchesQuery
            ->select(
                'visitor_token',
                'utm_campaign',
                DB::raw('COALESCE(utm_content, "(not set)") as utm_content'),
                DB::raw('COALESCE(utm_source, "direct") as utm_source'),
                'touch_at as touch_time'
            )
            ->get();

        // 3. Assemble visitor cohorts by (source, campaign, content)
        $cohorts = [];

        foreach ($events as $ev) {
            $src = (string) $ev->utm_source;
            $camp = (string) $ev->utm_campaign;
            $cnt = (string) $ev->utm_content;
            $key = $src.'::'.$camp.'::'.$cnt;

            if (! isset($cohorts[$key])) {
                $cohorts[$key] = [
                    'campaign' => $camp,
                    'content' => $cnt,
                    'source' => $src,
                    'visitors' => [],
                ];
            }

            if ($ev->visitor_token) {
                $time = CarbonImmutable::parse($ev->touch_time);
                $cohorts[$key]['visitors'][$ev->visitor_token][] = $time;
            }
        }

        foreach ($touches as $t) {
            $src = (string) $t->utm_source;
            $camp = (string) $t->utm_campaign;
            $cnt = (string) $t->utm_content;
            $key = $src.'::'.$camp.'::'.$cnt;

            if (! isset($cohorts[$key])) {
                $cohorts[$key] = [
                    'campaign' => $camp,
                    'content' => $cnt,
                    'source' => $src,
                    'visitors' => [],
                ];
            }

            if ($t->visitor_token) {
                $time = CarbonImmutable::parse($t->touch_time);
                $cohorts[$key]['visitors'][$t->visitor_token][] = $time;
            }
        }

        // 4. Query bookings created within the period to label booking-date activity separately
        $bookingsInPeriodQuery = $this->nonBotBookingQuery()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('campaign');

        if ($campaign) {
            $bookingsInPeriodQuery->where('campaign', $campaign);
        }

        $periodBookings = $bookingsInPeriodQuery
            ->select(
                'campaign',
                DB::raw('COALESCE(content, "(not set)") as content'),
                DB::raw('COALESCE(source, "direct") as source'),
                DB::raw('COUNT(*) as total_created'),
                DB::raw('COUNT(CASE WHEN status IN ("confirmed", "completed") THEN 1 END) as confirmed_created')
            )
            ->groupBy('campaign', DB::raw('COALESCE(content, "(not set)")'), DB::raw('COALESCE(source, "direct")'))
            ->toBase()->get();

        $createdInPeriod = [];
        $confirmedCreatedInPeriod = [];
        foreach ($periodBookings as $pb) {
            $k = $pb->source.'::'.$pb->campaign.'::'.$pb->content;
            $createdInPeriod[$k] = (int) $pb->total_created;
            $confirmedCreatedInPeriod[$k] = (int) $pb->confirmed_created;
        }

        // 5. Calculate downstream bookings and conversion rate strictly for each cohort
        $allKeys = array_unique(array_merge(array_keys($cohorts), array_keys($createdInPeriod)));
        $allCampaignContents = [];

        foreach ($allKeys as $key) {
            $cohort = $cohorts[$key] ?? null;
            if ($cohort) {
                $src = $cohort['source'];
                $camp = $cohort['campaign'];
                $cnt = $cohort['content'];
                $visitors = $cohort['visitors'];
                $visitorTokens = array_keys($visitors);
                $visitorsCount = count($visitorTokens);
            } else {
                [$src, $camp, $cnt] = explode('::', $key, 3);
                $visitors = [];
                $visitorTokens = [];
                $visitorsCount = 0;
            }

            // Candidate bookings matching this campaign, content, and source
            $candidateBookings = $this->nonBotBookingQuery()
                ->where('campaign', $camp)
                ->where(function ($q) use ($cnt) {
                    if ($cnt === '(not set)') {
                        $q->whereNull('content')->orWhere('content', '')->orWhere('content', '(not set)');
                    } else {
                        $q->where('content', $cnt);
                    }
                })
                ->where(function ($q) use ($src) {
                    if ($src === 'direct') {
                        $q->whereNull('source')->orWhere('source', '')->orWhere('source', 'direct');
                    } else {
                        $q->where('source', $src);
                    }
                })
                ->get();

            $downstreamBookings = 0;
            $confirmedDownstreamBookings = 0;
            $unlinkedBookings = 0;
            $confirmedUnlinkedBookings = 0;

            if ($candidateBookings->isNotEmpty()) {
                foreach ($candidateBookings as $b) {
                    $isDownstream = false;

                    // Restrict downstream cohort counts strictly to bookings joined to a qualifying cohort visitor
                    // where an eligible touch for that visitor in this cohort lies in the booking's 30-day lookback
                    if ($b->visitor_token && isset($visitors[$b->visitor_token])) {
                        $createdAt = CarbonImmutable::parse($b->created_at);
                        $touchTimes = $visitors[$b->visitor_token];
                        foreach ($touchTimes as $touchTime) {
                            if ($createdAt->gte($touchTime) && $createdAt->lte($touchTime->addDays(30))) {
                                $isDownstream = true;
                                break;
                            }
                        }

                        // Also check authoritative attributed touch_at on booking if recorded in period
                        if (! $isDownstream && $b->touch_at) {
                            $touchTime = CarbonImmutable::parse($b->touch_at);
                            if ($touchTime->gte($start) && $touchTime->lte($end) && $createdAt->gte($touchTime) && $createdAt->lte($touchTime->addDays(30))) {
                                $isDownstream = true;
                            }
                        }
                    }

                    if ($isDownstream) {
                        $downstreamBookings++;
                        if (in_array($b->status, ['confirmed', 'completed'], true)) {
                            $confirmedDownstreamBookings++;
                        }
                    } else {
                        // Unlinked/non-cohort booking activity: either touch_at occurred in period or booking created in period
                        $bCreatedAt = CarbonImmutable::parse($b->created_at);
                        $bTouchTime = $b->touch_at ? CarbonImmutable::parse($b->touch_at) : null;
                        if (($bTouchTime && $bTouchTime->gte($start) && $bTouchTime->lte($end)) || ($bCreatedAt->gte($start) && $bCreatedAt->lte($end))) {
                            $unlinkedBookings++;
                            if (in_array($b->status, ['confirmed', 'completed'], true)) {
                                $confirmedUnlinkedBookings++;
                            }
                        }
                    }
                }
            }

            $convRate = $visitorsCount > 0
                ? round(($confirmedDownstreamBookings / $visitorsCount) * 100, 1)
                : 0.0;

            $allCampaignContents[$key] = [
                'campaign' => $camp,
                'content' => $cnt,
                'source' => $src,
                'visitors_count' => $visitorsCount,
                'bookings_count' => $downstreamBookings,
                'confirmed_bookings' => $confirmedDownstreamBookings,
                'conversion_rate' => $convRate,
                'unlinked_bookings_count' => $unlinkedBookings,
                'confirmed_unlinked_bookings_count' => $confirmedUnlinkedBookings,
                'bookings_created_in_period' => $createdInPeriod[$key] ?? 0,
                'confirmed_created_in_period' => $confirmedCreatedInPeriod[$key] ?? 0,
            ];
        }

        $rows = collect($allCampaignContents)->sortByDesc('bookings_count')->values();

        $groupedByCampaign = $rows->groupBy('campaign')->map(function ($items, $camp) {
            return [
                'campaign' => $camp,
                'total_visitors' => $items->sum('visitors_count'),
                'total_bookings' => $items->sum('bookings_count'),
                'total_confirmed' => $items->sum('confirmed_bookings'),
                'total_unlinked_bookings' => $items->sum('unlinked_bookings_count'),
                'total_created_in_period' => $items->sum('bookings_created_in_period'),
                'contents' => $items->values(),
            ];
        })->values();

        return [
            'rows' => $rows,
            'grouped' => $groupedByCampaign,
            'total_campaigns' => $groupedByCampaign->count(),
            'total_visitors' => $rows->sum('visitors_count'),
            'total_bookings' => $rows->sum('bookings_count'),
            'total_confirmed' => $rows->sum('confirmed_bookings'),
            'total_unlinked_bookings' => $rows->sum('unlinked_bookings_count'),
            'total_created_in_period' => $rows->sum('bookings_created_in_period'),
        ];
    }

    /**
     * Build the canonical non-bot event scope, including defensive checks
     * against malformed events linked to bot visitors or sessions.
     */
    /** @return Builder<AnalyticsEvent> */
    public function nonBotEventQuery(): Builder
    {
        return AnalyticsEvent::query()
            ->where('is_bot', false)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('visitors')
                    ->whereColumn('visitors.visitor_token', 'analytics_events.visitor_token')
                    ->where('visitors.is_bot', true);
            })
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('visitor_sessions as bot_sessions')
                    ->leftJoin('visitors as bot_visitors', 'bot_visitors.id', '=', 'bot_sessions.visitor_id')
                    ->where(function ($nested): void {
                        $nested->whereColumn('bot_sessions.session_token', 'analytics_events.session_token')
                            ->orWhereColumn('bot_sessions.session_id', 'analytics_events.session_token');
                    })
                    ->where(function ($nested): void {
                        $nested->where('bot_sessions.is_bot', true)
                            ->orWhere('bot_visitors.is_bot', true);
                    });
            });
    }

    /**
     * Build the canonical non-bot session scope.
     */
    /** @return Builder<VisitorSession> */
    public function nonBotSessionQuery(): Builder
    {
        return VisitorSession::query()
            ->where('is_bot', false)
            ->whereHas('visitor', function ($query): void {
                $query->where('is_bot', false);
            });
    }

    /**
     * Exclude bookings that can be traced to a visitor flagged as a bot.
     */
    /** @return Builder<Booking> */
    public function nonBotBookingQuery(): Builder
    {
        return Booking::query()
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('visitors')
                    ->whereColumn('visitors.visitor_token', 'bookings.visitor_token')
                    ->where('visitors.is_bot', true);
            });
    }

    public function getCairoDateExpression(string $column = 'created_at', ?CarbonInterface $start = null, ?CarbonInterface $end = null): string
    {
        return $this->getBusinessDateExpression($column, $start, $end);
    }

    public function getBusinessDateExpression(string $column = 'created_at', ?CarbonInterface $start = null, ?CarbonInterface $end = null): string
    {
        if ($start && $end) {
            $startCairo = CarbonImmutable::parse($start)->setTimezone(app(TimezoneService::class)->getBusinessTimezone())->startOfDay();
            $endCairo = CarbonImmutable::parse($end)->setTimezone(app(TimezoneService::class)->getBusinessTimezone())->startOfDay();

            $diff = $startCairo->diffInDays($endCairo);
            if ($diff >= 0 && $diff <= 1096) {
                $cases = [];
                $curr = $startCairo->startOfDay();
                while ($curr->lte($endCairo)) {
                    $dayStr = $curr->toDateString();
                    $nextDay = $curr->addDay()->startOfDay();
                    $dayStartUtc = $curr->setTimezone('UTC')->toDateTimeString();
                    $dayEndUtc = $nextDay->setTimezone('UTC')->toDateTimeString();
                    $cases[] = "WHEN {$column} >= '{$dayStartUtc}' AND {$column} < '{$dayEndUtc}' THEN '{$dayStr}'";
                    $curr = $nextDay;
                }

                if (! empty($cases)) {
                    return '(CASE '.implode(' ', $cases).' ELSE NULL END)';
                }
            }
        }

        // For long ranges >3 years: partition into exact DST transition intervals (EDITS V1 §13-15)
        $tz = new \DateTimeZone(app(TimezoneService::class)->getBusinessTimezone());
        $startTs = $start ? CarbonImmutable::parse($start)->getTimestamp() : CarbonImmutable::now(app(TimezoneService::class)->getBusinessTimezone())->subDays(30)->getTimestamp();
        $endTs = $end ? CarbonImmutable::parse($end)->getTimestamp() : CarbonImmutable::now(app(TimezoneService::class)->getBusinessTimezone())->getTimestamp();
        $transitions = $tz->getTransitions($startTs, $endTs);

        if (! empty($transitions) && count($transitions) > 1) {
            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            $cases = [];
            $count = count($transitions);

            for ($i = 0; $i < $count; $i++) {
                $t = $transitions[$i];
                $offsetSeconds = (int) $t['offset'];
                $offsetStr = sprintf('%s%02d:%02d', $offsetSeconds < 0 ? '-' : '+', intdiv(abs($offsetSeconds), 3600), intdiv(abs($offsetSeconds) % 3600, 60));
                $fromUtc = date('Y-m-d H:i:s', $t['ts']);

                $sqlExpr = $isSqlite
                    ? "DATE(datetime({$column}, '{$offsetSeconds} seconds'))"
                    : "DATE(CONVERT_TZ({$column}, '+00:00', '{$offsetStr}'))";

                if (isset($transitions[$i + 1])) {
                    $toUtc = date('Y-m-d H:i:s', $transitions[$i + 1]['ts']);
                    $cases[] = "WHEN {$column} >= '{$fromUtc}' AND {$column} < '{$toUtc}' THEN {$sqlExpr}";
                } else {
                    $cases[] = "WHEN {$column} >= '{$fromUtc}' THEN {$sqlExpr}";
                }
            }

            $firstSeconds = (int) $transitions[0]['offset'];
            $firstOffset = sprintf('%s%02d:%02d', $firstSeconds < 0 ? '-' : '+', intdiv(abs($firstSeconds), 3600), intdiv(abs($firstSeconds) % 3600, 60));
            $firstSqlExpr = $isSqlite
                ? "DATE(datetime({$column}, '{$firstSeconds} seconds'))"
                : "DATE(CONVERT_TZ({$column}, '+00:00', '{$firstOffset}'))";
            $firstFrom = date('Y-m-d H:i:s', $transitions[0]['ts']);
            array_unshift($cases, "WHEN {$column} < '{$firstFrom}' THEN {$firstSqlExpr}");

            return '(CASE '.implode(' ', $cases).' ELSE NULL END)';
        }

        $ref = $start ?? CarbonImmutable::now(app(TimezoneService::class)->getBusinessTimezone());
        $refDateTime = new \DateTime($ref->toIso8601String(), new \DateTimeZone('UTC'));
        $offsetSeconds = (new \DateTimeZone(app(TimezoneService::class)->getBusinessTimezone()))->getOffset($refDateTime);
        $offset = sprintf('%s%02d:%02d', $offsetSeconds < 0 ? '-' : '+', intdiv(abs($offsetSeconds), 3600), intdiv(abs($offsetSeconds) % 3600, 60));

        if (DB::connection()->getDriverName() === 'sqlite') {
            return "DATE(datetime({$column}, '{$offsetSeconds} seconds'))";
        }

        return "DATE(CONVERT_TZ({$column}, '+00:00', '{$offset}'))";
    }
}
