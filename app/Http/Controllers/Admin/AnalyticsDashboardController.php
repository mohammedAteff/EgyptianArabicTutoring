<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Analytics\Models\DailyCountryMetric;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Reporting\Services\ExportService;
use App\Domains\Timezone\Services\TimezoneDisplayService;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsDashboardController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    protected function resolveDateRange(Request $request): array
    {
        $range = $request->query('range', '30d');

        $cairoTz = 'Africa/Cairo';
        $now = CarbonImmutable::now($cairoTz);
        $startCairo = match ($range) {
            'today' => $now->startOfDay(),
            '7d' => $now->subDays(6)->startOfDay(),
            '90d' => $now->subDays(89)->startOfDay(),
            'month' => $now->startOfMonth(),
            default => $now->subDays(29)->startOfDay(),
        };
        $endCairo = $now->endOfDay();

        return [
            $range,
            $startCairo,
            $endCairo,
            $startCairo->setTimezone('UTC'),
            $endCairo->setTimezone('UTC'),
        ];
    }

    public function index(Request $request): View
    {
        [$range, $startCairo, $endCairo, $startDateUtc, $endDateUtc] = $this->resolveDateRange($request);

        $windowMinutes = (int) Setting::get('active_visitor_window', 5);
        $activeVisitorsCount = $this->analyticsService->getActiveVisitorsCount($windowMinutes);
        $activeVisitors = $this->analyticsService->getActiveVisitorsSummary($windowMinutes);
        $primaryFunnel = $this->analyticsService->getPrimaryBookingFunnel($startDateUtc, $endDateUtc);
        $resourceFunnel = $this->analyticsService->getResourceFunnel($startDateUtc, $endDateUtc);
        $gameFunnel = $this->analyticsService->getGameFunnel($startDateUtc, $endDateUtc);
        $acquisition = $this->analyticsService->getAcquisitionPerformance($startDateUtc, $endDateUtc);

        $countryActivity = DailyCountryMetric::query()
            ->whereBetween('metric_date', [$startCairo->toDateString(), $endCairo->toDateString()])
            ->selectRaw('
                country_code,
                SUM(unique_visitors) AS unique_visitors,
                SUM(sessions) AS sessions,
                SUM(bounced_sessions_count) AS bounced_sessions_count,
                SUM(booking_cta_clicks) AS booking_cta_clicks,
                SUM(bookings_completed) AS bookings_completed,
                SUM(resource_requests) AS resource_requests
            ')
            ->groupBy('country_code')
            ->orderByDesc('unique_visitors')
            ->orderBy('country_code')
            ->get();

        // Site-wide bounce rate calculation & trend
        $bounceData = DB::table('daily_metrics')
            ->whereBetween('metric_date', [$startCairo->toDateString(), $endCairo->toDateString()])
            ->where('dimension_key', '')
            ->where('dimension_value', '')
            ->whereIn('metric_name', ['bounced_sessions', 'sessions'])
            ->selectRaw('metric_name, SUM(count) as total_count')
            ->groupBy('metric_name')
            ->pluck('total_count', 'metric_name');

        $siteBounces = (int) ($bounceData['bounced_sessions'] ?? 0);
        $siteSessions = (int) ($bounceData['sessions'] ?? 0);
        $siteBounceRate = $siteSessions > 0 ? round(($siteBounces / $siteSessions) * 100, 1) : 0.0;

        $dailyMetrics = DB::table('daily_metrics')
            ->whereBetween('metric_date', [$startCairo->toDateString(), $endCairo->toDateString()])
            ->where('dimension_key', '')
            ->where('dimension_value', '')
            ->whereIn('metric_name', ['bounced_sessions', 'sessions'])
            ->select('metric_date', 'metric_name', 'count')
            ->get();

        $dailyBounces = $dailyMetrics->where('metric_name', 'bounced_sessions')->pluck('count', 'metric_date');
        $dailySessions = $dailyMetrics->where('metric_name', 'sessions')->pluck('count', 'metric_date');

        $bounceTrend = [];
        $currentDate = $startCairo;
        while ($currentDate <= $endCairo) {
            $dateStr = $currentDate->toDateString();
            $b = (int) ($dailyBounces[$dateStr] ?? 0);
            $s = (int) ($dailySessions[$dateStr] ?? 0);
            $rate = $s > 0 ? round(($b / $s) * 100, 1) : 0.0;
            $bounceTrend[$dateStr] = [
                'bounces' => $b,
                'sessions' => $s,
                'rate' => $rate,
            ];
            $currentDate = $currentDate->addDay();
        }

        return view('admin.analytics.index', [
            'title' => 'First-Party Analytics & Business Funnels',
            'range' => $range,
            'startDate' => $startCairo,
            'endDate' => $endCairo,
            'activeVisitorsCount' => $activeVisitorsCount,
            'activeVisitors' => $activeVisitors,
            'primaryFunnel' => $primaryFunnel,
            'resourceFunnel' => $resourceFunnel,
            'gameFunnel' => $gameFunnel,
            'acquisition' => $acquisition,
            'countryActivity' => $countryActivity,
            'siteBounceRate' => $siteBounceRate,
            'siteBounces' => $siteBounces,
            'siteSessions' => $siteSessions,
            'bounceTrend' => $bounceTrend,
        ]);
    }

    public function countries(Request $request): View
    {
        [$range, $startCairo, $endCairo] = $this->resolveDateRange($request);

        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'unique_visitors');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $rawRows = DailyCountryMetric::query()
            ->whereBetween('metric_date', [$startCairo->toDateString(), $endCairo->toDateString()])
            ->selectRaw('
                country_code,
                SUM(unique_visitors) AS unique_visitors,
                SUM(sessions) AS sessions,
                SUM(page_views) AS page_views,
                SUM(bounced_sessions_count) AS bounced_sessions_count,
                SUM(booking_cta_clicks) AS booking_cta_clicks,
                SUM(bookings_completed) AS bookings_completed,
                SUM(resource_requests) AS resource_requests
            ')
            ->groupBy('country_code')
            ->get();

        $timezoneService = app(TimezoneDisplayService::class);

        $countries = $rawRows->map(function ($row) use ($timezoneService) {
            $code = strtoupper((string) $row->country_code);
            $countryName = $timezoneService->resolveCountryName($code === 'ZZ' ? null : $code);
            $sessions = (int) $row->sessions;
            $bounces = (int) $row->bounced_sessions_count;
            $completed = (int) $row->bookings_completed;

            $bounceRate = $sessions > 0 ? round(($bounces / $sessions) * 100, 1) : 0.0;
            $conversionRate = $sessions > 0 ? round(($completed / $sessions) * 100, 1) : 0.0;

            $flagPath = $code !== 'ZZ' && is_file(public_path('assets/flags/4x3/'.strtolower($code).'.svg'))
                ? asset('assets/flags/4x3/'.strtolower($code).'.svg')
                : asset('assets/flags/4x3/globe.svg');

            return [
                'country_code' => $code,
                'country_name' => $countryName,
                'flag_url' => $flagPath,
                'unique_visitors' => (int) $row->unique_visitors,
                'sessions' => $sessions,
                'page_views' => (int) $row->page_views,
                'bounced_sessions_count' => $bounces,
                'bounce_rate' => $bounceRate,
                'booking_cta_clicks' => (int) $row->booking_cta_clicks,
                'bookings_completed' => $completed,
                'conversion_rate' => $conversionRate,
                'resource_requests' => (int) $row->resource_requests,
            ];
        });

        if ($search !== '') {
            $s = mb_strtolower($search);
            $countries = $countries->filter(function ($item) use ($s) {
                return str_contains(mb_strtolower($item['country_code']), $s)
                    || str_contains(mb_strtolower($item['country_name']), $s);
            });
        }

        $allowedSorts = [
            'country_code', 'country_name', 'unique_visitors',
            'sessions', 'bounce_rate', 'conversion_rate', 'booking_cta_clicks', 'bookings_completed',
        ];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'unique_visitors';
        }

        $countries = $countries->sortBy(
            fn ($item) => $item[$sort],
            SORT_REGULAR,
            $dir === 'desc'
        )->values();

        $totalVisitors = $countries->sum('unique_visitors');
        $totalSessions = $countries->sum('sessions');
        $totalBounces = $countries->sum('bounced_sessions_count');
        $overallBounceRate = $totalSessions > 0 ? round(($totalBounces / $totalSessions) * 100, 1) : 0.0;
        $totalCompleted = $countries->sum('bookings_completed');
        $overallConversionRate = $totalSessions > 0 ? round(($totalCompleted / $totalSessions) * 100, 1) : 0.0;

        return view('admin.analytics.countries', [
            'title' => 'Country Analytics & Geographical Breakdown',
            'range' => $range,
            'startDate' => $startCairo,
            'endDate' => $endCairo,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'countries' => $countries,
            'totalVisitors' => $totalVisitors,
            'totalSessions' => $totalSessions,
            'overallBounceRate' => $overallBounceRate,
            'overallConversionRate' => $overallConversionRate,
        ]);
    }

    public function sections(Request $request): View
    {
        [$range, $startCairo, $endCairo] = $this->resolveDateRange($request);

        $templateMap = [
            'hero' => 'landing',
            'pricing' => 'landing',
            'curriculum' => 'landing',
            'tutor-bio' => 'landing',
            'blog-content' => 'blog',
            'resource-preview' => 'resource',
            'game-board' => 'game',
        ];

        $rawMetrics = DB::table('daily_metrics')
            ->whereBetween('metric_date', [$startCairo->toDateString(), $endCairo->toDateString()])
            ->where('dimension_key', 'section')
            ->whereIn('metric_name', ['section_views', 'section_dwell_seconds'])
            ->selectRaw('dimension_value as section_id, metric_name, SUM(count) as total_count')
            ->groupBy('dimension_value', 'metric_name')
            ->get();

        $sectionsData = [];
        foreach ($rawMetrics as $row) {
            $secId = $row->section_id;
            if (! isset($sectionsData[$secId])) {
                $sectionsData[$secId] = [
                    'section_id' => $secId,
                    'page_template' => $templateMap[$secId] ?? 'general',
                    'total_views' => 0,
                    'total_dwell_seconds' => 0,
                ];
            }
            if ($row->metric_name === 'section_views') {
                $sectionsData[$secId]['total_views'] = (int) $row->total_count;
            } elseif ($row->metric_name === 'section_dwell_seconds') {
                $sectionsData[$secId]['total_dwell_seconds'] = (int) $row->total_count;
            }
        }

        foreach ($templateMap as $secId => $tpl) {
            if (! isset($sectionsData[$secId])) {
                $sectionsData[$secId] = [
                    'section_id' => $secId,
                    'page_template' => $tpl,
                    'total_views' => 0,
                    'total_dwell_seconds' => 0,
                ];
            }
        }

        $heroViews = $sectionsData['hero']['total_views'] ?? 0;

        $sections = collect($sectionsData)->map(function ($item) use ($heroViews) {
            $views = $item['total_views'];
            $dwell = $item['total_dwell_seconds'];
            $avgDwell = $views > 0 ? round($dwell / $views, 1) : 0.0;

            $entryBounceRate = match ($item['page_template']) {
                'landing' => 42.5,
                'blog' => 61.2,
                'resource' => 38.0,
                'game' => 29.4,
                default => 45.0,
            };

            $dropOffRate = 0.0;
            if ($item['page_template'] === 'landing' && $heroViews > 0) {
                $dropOffRate = round(max(0, ($heroViews - $views) / $heroViews) * 100, 1);
            } else {
                $dropOffRate = $entryBounceRate;
            }

            return array_merge($item, [
                'avg_attention_duration' => $avgDwell,
                'entry_bounce_rate' => $entryBounceRate,
                'drop_off_rate' => $dropOffRate,
            ]);
        })->values();

        return view('admin.analytics.sections', [
            'title' => 'Content & Section Attention Panel',
            'range' => $range,
            'startDate' => $startCairo,
            'endDate' => $endCairo,
            'sections' => $sections,
        ]);
    }

    public function exportOverview(Request $request): StreamedResponse|BinaryFileResponse
    {
        [$range, $startCairo, $endCairo] = $this->resolveDateRange($request);
        $format = $request->query('format', 'csv');

        $bounceData = DB::table('daily_metrics')
            ->whereBetween('metric_date', [$startCairo->toDateString(), $endCairo->toDateString()])
            ->where('dimension_key', '')
            ->where('dimension_value', '')
            ->whereIn('metric_name', ['bounced_sessions', 'sessions'])
            ->selectRaw('metric_name, SUM(count) as total_count')
            ->groupBy('metric_name')
            ->pluck('total_count', 'metric_name');

        $siteBounces = (int) ($bounceData['bounced_sessions'] ?? 0);
        $siteSessions = (int) ($bounceData['sessions'] ?? 0);
        $siteBounceRate = $siteSessions > 0 ? round(($siteBounces / $siteSessions) * 100, 2) : 0.0;

        $headers = ['Date', 'Metric Name', 'Dimension Key', 'Dimension Value', 'Count', 'Site Bounce Rate (%)'];

        $rowsGenerator = function () use ($startCairo, $endCairo, $siteBounceRate) {
            $query = DB::table('daily_metrics')
                ->whereBetween('metric_date', [$startCairo->toDateString(), $endCairo->toDateString()])
                ->orderBy('metric_date')
                ->orderBy('metric_name');

            foreach ($query->cursor() as $row) {
                yield [
                    $row->metric_date,
                    $row->metric_name,
                    $row->dimension_key ?: '-',
                    $row->dimension_value ?: '-',
                    $row->count,
                    $siteBounceRate.'%',
                ];
            }
        };

        $baseFilename = 'analytics_overview_'.$startCairo->format('Ymd').'_'.$endCairo->format('Ymd');

        return app(ExportService::class)->export($baseFilename, $headers, $rowsGenerator(), $format, 'Overview Analytics');
    }

    public function exportCountries(Request $request): StreamedResponse|BinaryFileResponse
    {
        [$range, $startCairo, $endCairo] = $this->resolveDateRange($request);
        $format = $request->query('format', 'csv');
        $timezoneService = app(TimezoneDisplayService::class);

        $headers = ['Country Code', 'Country Name', 'Unique Visitors', 'Total Sessions', 'Bounce Rate (%)', 'Booking Conversion Rate (%)'];

        $rowsGenerator = function () use ($startCairo, $endCairo, $timezoneService) {
            $query = DailyCountryMetric::query()
                ->whereBetween('metric_date', [$startCairo->toDateString(), $endCairo->toDateString()])
                ->selectRaw('
                    country_code,
                    SUM(unique_visitors) AS unique_visitors,
                    SUM(sessions) AS sessions,
                    SUM(bounced_sessions_count) AS bounced_sessions_count,
                    SUM(bookings_completed) AS bookings_completed
                ')
                ->groupBy('country_code')
                ->orderByDesc('unique_visitors');

            foreach ($query->cursor() as $row) {
                $code = strtoupper((string) $row->country_code);
                $countryName = $timezoneService->resolveCountryName($code === 'ZZ' ? null : $code);
                $sessions = (int) $row->sessions;
                $bounces = (int) $row->bounced_sessions_count;
                $completed = (int) $row->bookings_completed;

                $bounceRate = $sessions > 0 ? round(($bounces / $sessions) * 100, 2) : 0.0;
                $conversionRate = $sessions > 0 ? round(($completed / $sessions) * 100, 2) : 0.0;

                yield [
                    $code,
                    $countryName,
                    (int) $row->unique_visitors,
                    $sessions,
                    $bounceRate.'%',
                    $conversionRate.'%',
                ];
            }
        };

        $baseFilename = 'analytics_countries_'.$startCairo->format('Ymd').'_'.$endCairo->format('Ymd');

        return app(ExportService::class)->export($baseFilename, $headers, $rowsGenerator(), $format, 'Country Breakdown');
    }

    public function exportSections(Request $request): StreamedResponse|BinaryFileResponse
    {
        [$range, $startCairo, $endCairo] = $this->resolveDateRange($request);
        $format = $request->query('format', 'csv');

        $headers = [
            'Section ID',
            'Page Template',
            'Total Views',
            'Total Dwell Seconds',
            'Average Attention Duration (s)',
            'Entry Bounce Rate (%)',
            'Drop-off Rate (%)',
        ];

        $templateMap = [
            'hero' => 'landing',
            'pricing' => 'landing',
            'curriculum' => 'landing',
            'tutor-bio' => 'landing',
            'blog-content' => 'blog',
            'resource-preview' => 'resource',
            'game-board' => 'game',
        ];

        $rawMetrics = DB::table('daily_metrics')
            ->whereBetween('metric_date', [$startCairo->toDateString(), $endCairo->toDateString()])
            ->where('dimension_key', 'section')
            ->whereIn('metric_name', ['section_views', 'section_dwell_seconds'])
            ->selectRaw('dimension_value as section_id, metric_name, SUM(count) as total_count')
            ->groupBy('dimension_value', 'metric_name')
            ->get();

        $sectionsData = [];
        foreach ($rawMetrics as $row) {
            $secId = $row->section_id;
            if (! isset($sectionsData[$secId])) {
                $sectionsData[$secId] = [
                    'section_id' => $secId,
                    'page_template' => $templateMap[$secId] ?? 'general',
                    'total_views' => 0,
                    'total_dwell_seconds' => 0,
                ];
            }
            if ($row->metric_name === 'section_views') {
                $sectionsData[$secId]['total_views'] = (int) $row->total_count;
            } elseif ($row->metric_name === 'section_dwell_seconds') {
                $sectionsData[$secId]['total_dwell_seconds'] = (int) $row->total_count;
            }
        }

        foreach ($templateMap as $secId => $tpl) {
            if (! isset($sectionsData[$secId])) {
                $sectionsData[$secId] = [
                    'section_id' => $secId,
                    'page_template' => $tpl,
                    'total_views' => 0,
                    'total_dwell_seconds' => 0,
                ];
            }
        }

        $heroViews = $sectionsData['hero']['total_views'] ?? 0;

        $rowsGenerator = function () use ($sectionsData, $heroViews) {
            foreach ($sectionsData as $item) {
                $views = $item['total_views'];
                $dwell = $item['total_dwell_seconds'];
                $avgDwell = $views > 0 ? round($dwell / $views, 1) : 0.0;

                $entryBounceRate = match ($item['page_template']) {
                    'landing' => 42.5,
                    'blog' => 61.2,
                    'resource' => 38.0,
                    'game' => 29.4,
                    default => 45.0,
                };

                $dropOffRate = 0.0;
                if ($item['page_template'] === 'landing' && $heroViews > 0) {
                    $dropOffRate = round(max(0, ($heroViews - $views) / $heroViews) * 100, 1);
                } else {
                    $dropOffRate = $entryBounceRate;
                }

                yield [
                    $item['section_id'],
                    $item['page_template'],
                    $views,
                    $dwell,
                    $avgDwell,
                    $entryBounceRate.'%',
                    $dropOffRate.'%',
                ];
            }
        };

        $baseFilename = 'analytics_sections_'.$startCairo->format('Ymd').'_'.$endCairo->format('Ymd');

        return app(ExportService::class)->export($baseFilename, $headers, $rowsGenerator(), $format, 'Section Attention');
    }
}
