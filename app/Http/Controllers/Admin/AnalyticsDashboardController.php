<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Reporting\Services\ExportService;
use App\Domains\Reporting\Services\ReportPeriod;
use App\Domains\Reporting\Services\ReportService;
use App\Domains\Timezone\Services\TimezoneDisplayService;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsDashboardController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    /** @return array<string, mixed> */
    protected function filters(Request $request): array
    {
        return app(ReportPeriod::class)->filters($request, [
            'search' => ['nullable', 'string', 'max:255'], 'section' => ['nullable', 'string', 'max:64'],
            'sort' => ['nullable', Rule::in(['country_code', 'country_name', 'unique_visitors', 'sessions', 'bounce_rate', 'conversion_rate', 'booking_cta_clicks', 'bookings_completed'])],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
    }

    protected function resolveDateRange(array $filters): array
    {
        [$start, $end] = app(ReportPeriod::class)->bounds($filters);
        $tz = app(TimezoneService::class)->getBusinessTimezone();

        return [$filters['range'], $start->setTimezone($tz), $end->setTimezone($tz), $start, $end];
    }

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        [$range, $startCairo, $endCairo, $startDateUtc, $endDateUtc] = $this->resolveDateRange($filters);

        $traffic = app(ReportService::class)->getTrafficReport($startDateUtc, $endDateUtc)['summary'];
        $windowMinutes = (int) Setting::get('active_visitor_window', 5);
        $activeVisitorsCount = $this->analyticsService->getActiveVisitorsCount($windowMinutes);
        $activeVisitors = $this->analyticsService->getActiveVisitorsSummary($windowMinutes);
        $primaryFunnel = $this->analyticsService->getPrimaryBookingFunnel($startDateUtc, $endDateUtc);
        $resourceFunnel = $this->analyticsService->getResourceFunnel($startDateUtc, $endDateUtc);
        $gameFunnel = $this->analyticsService->getGameFunnel($startDateUtc, $endDateUtc);
        $acquisition = $this->analyticsService->getAcquisitionPerformance($startDateUtc, $endDateUtc);

        $countryActivity = $this->analyticsService->reportingCountries($startCairo, $endCairo);
        $dailyMetrics = $this->analyticsService->reportingMetrics($startCairo, $endCairo)->where('dimension_key', '')->where('dimension_value', '');
        $siteBounces = (int) $dailyMetrics->where('metric_name', 'bounced_sessions')->sum('count');
        $siteSessions = (int) $dailyMetrics->where('metric_name', 'sessions')->sum('count');
        $siteBounceRate = $siteSessions > 0 ? round(($siteBounces / $siteSessions) * 100, 1) : 0.0;

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
            'range' => $range, 'filters' => $filters,
            'startDate' => $startCairo,
            'endDate' => $endCairo,
            'activeVisitorsCount' => $activeVisitorsCount,
            'activeVisitors' => $activeVisitors,
            'primaryFunnel' => $primaryFunnel,
            'resourceFunnel' => $resourceFunnel,
            'gameFunnel' => $gameFunnel,
            'acquisition' => $acquisition,
            'countryActivity' => $countryActivity,
            'traffic' => $traffic,
            'siteBounceRate' => $siteBounceRate,
            'siteBounces' => $siteBounces,
            'siteSessions' => $siteSessions,
            'bounceTrend' => $bounceTrend,
            'goals' => $this->analyticsService->goalReport($startDateUtc, $endDateUtc),
            'whatsappActivity' => $this->analyticsService->whatsappReport($startDateUtc, $endDateUtc),
        ]);
    }

    public function countries(Request $request): View
    {
        $filters = $this->filters($request);
        [$range, $startCairo, $endCairo] = $this->resolveDateRange($filters);

        $search = $filters['search'] ?? '';
        $sort = $filters['sort'] ?? 'unique_visitors';
        $dir = $filters['dir'] ?? 'desc';
        $countries = $this->countryRows($startCairo, $endCairo, $filters);

        $totalVisitors = $countries->sum('unique_visitors');
        $totalSessions = $countries->sum('sessions');
        $totalBounces = $countries->sum('bounced_sessions_count');
        $overallBounceRate = $totalSessions > 0 ? round(($totalBounces / $totalSessions) * 100, 1) : 0.0;
        $totalCompleted = $countries->sum('bookings_completed');
        $overallConversionRate = $totalSessions > 0 ? round(($totalCompleted / $totalSessions) * 100, 1) : 0.0;

        return view('admin.analytics.countries', [
            'title' => 'Country Analytics & Geographical Breakdown',
            'range' => $range, 'filters' => $filters,
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
        $filters = $this->filters($request);
        [$range, $startCairo, $endCairo] = $this->resolveDateRange($filters);

        $sections = $this->sectionRows($startCairo, $endCairo, $filters);

        return view('admin.analytics.sections', [
            'title' => 'Content & Section Attention Panel',
            'range' => $range, 'filters' => $filters,
            'startDate' => $startCairo,
            'endDate' => $endCairo,
            'sections' => $sections,
        ]);
    }

    public function exportOverview(Request $request): StreamedResponse|BinaryFileResponse
    {
        $filters = $this->filters($request);
        [$range, $startCairo, $endCairo] = $this->resolveDateRange($filters);
        $format = $filters['format'] ?? 'csv';

        $metrics = $this->analyticsService->reportingMetrics($startCairo, $endCairo);
        $global = $metrics->where('dimension_key', '')->where('dimension_value', '');
        $sessions = $global->where('metric_name', 'sessions')->sum('count');
        $siteBounceRate = $sessions ? round(100 * $global->where('metric_name', 'bounced_sessions')->sum('count') / $sessions, 2) : 0;
        $headers = ['Date', 'Metric Name', 'Dimension Key', 'Dimension Value', 'Count', 'Site Bounce Rate (%)'];
        $clicks = $this->analyticsService->whatsappReport($startCairo->setTimezone('UTC'), $endCairo->setTimezone('UTC'));
        $goals = $this->analyticsService->goalReport($startCairo->setTimezone('UTC'), $endCairo->setTimezone('UTC'));
        $traffic = app(ReportService::class)->getTrafficReport($startCairo->utc(), $endCairo->utc())['summary'];
        $rowsGenerator = function () use ($metrics, $siteBounceRate, $clicks, $goals, $traffic) {
            foreach (['visitors', 'sessions', 'page_views'] as $metric) {
                yield ['Period total', $metric, 'period', $metric === 'visitors' ? $traffic['visitors_basis'] : 'total', $traffic[$metric], $siteBounceRate.'%'];
            }
            foreach ($metrics->sortBy('metric_date') as $row) {
                yield [$row->metric_date, $row->metric_name, $row->dimension_key ?: '-', $row->dimension_value ?: '-', $row->count, $siteBounceRate.'%'];
            }
            foreach ($clicks as $click) {
                yield [$click['date'], 'whatsapp_clicked', 'country/source/medium/campaign/content/context/language', implode(' / ', [$click['country'], $click['source'], $click['medium'], $click['campaign'], $click['content'], $click['context'], $click['language']]), $click['clicks'], $siteBounceRate.'%'];
            }
            foreach ($goals as $goal) {
                yield ['Selected period', $goal['event'], 'goal_unique_visitors', $goal['rate'].'% visitor conversion', $goal['visitors'], $siteBounceRate.'%'];
            }
        };

        $baseFilename = 'analytics_overview_'.$startCairo->format('Ymd').'_'.$endCairo->format('Ymd');

        return app(ExportService::class)->export($baseFilename, $headers, $rowsGenerator(), $format, 'Overview Analytics');
    }

    public function exportCountries(Request $request): StreamedResponse|BinaryFileResponse
    {
        $filters = $this->filters($request);
        [$range, $startCairo, $endCairo] = $this->resolveDateRange($filters);
        $format = $filters['format'] ?? 'csv';
        $headers = ['Country Code', 'Country Name', 'Unique Visitors', 'Total Sessions', 'Page Views', 'Bounced Sessions', 'Bounce Rate (%)', 'Booking CTA Clicks', 'Completed Bookings', 'Booking Conversion Rate (%)', 'Resource Requests'];
        $rowsGenerator = function () use ($startCairo, $endCairo, $filters): \Generator {
            foreach ($this->countryRows($startCairo, $endCairo, $filters) as $row) {
                yield [$row['country_code'], $row['country_name'], $row['unique_visitors'], $row['sessions'], $row['page_views'], $row['bounced_sessions_count'], $row['bounce_rate'], $row['booking_cta_clicks'], $row['bookings_completed'], $row['conversion_rate'], $row['resource_requests']];
            }
        };

        $baseFilename = 'analytics_countries_'.$startCairo->format('Ymd').'_'.$endCairo->format('Ymd');

        return app(ExportService::class)->export($baseFilename, $headers, $rowsGenerator(), $format, 'Country Breakdown');
    }

    public function exportSections(Request $request): StreamedResponse|BinaryFileResponse
    {
        $filters = $this->filters($request);
        [$range, $startCairo, $endCairo] = $this->resolveDateRange($filters);
        $format = $filters['format'] ?? 'csv';

        $headers = [
            'Section ID',
            'Page Template',
            'Total Views',
            'Total Dwell Seconds',
            'Average Attention Duration (s)',
            'Entry Bounce Rate (%)',
            'Drop-off Rate (%)',
        ];

        $sections = $this->sectionRows($startCairo, $endCairo, $filters);
        $rowsGenerator = function () use ($sections) {
            foreach ($sections as $item) {
                yield [$item['section_id'], $item['page_template'], $item['total_views'], $item['total_dwell_seconds'], $item['avg_attention_duration'], $item['entry_bounce_rate'] === null ? 'Unavailable' : $item['entry_bounce_rate'].'%', $item['drop_off_rate'] === null ? 'Unavailable' : $item['drop_off_rate'].'%'];
            }
        };

        $baseFilename = 'analytics_sections_'.$startCairo->format('Ymd').'_'.$endCairo->format('Ymd');

        return app(ExportService::class)->export($baseFilename, $headers, $rowsGenerator(), $format, 'Section Attention');
    }

    private function sectionRows(CarbonImmutable $start, CarbonImmutable $end, array $filters): Collection
    {
        $rows = $this->analyticsService->sectionReport($start, $end);

        return empty($filters['section']) ? $rows : $rows->filter(fn (array $row): bool => str_contains($row['section_id'], $filters['section']))->values();
    }

    private function countryRows(CarbonImmutable $startCairo, CarbonImmutable $endCairo, array $filters): Collection
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $sort = $filters['sort'] ?? 'unique_visitors';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $rawRows = $this->analyticsService->reportingCountries($startCairo, $endCairo);

        $timezoneService = app(TimezoneDisplayService::class);

        $countries = $rawRows->map(function ($row) use ($timezoneService) {
            $country = $timezoneService->countryDisplay($row->country_code);
            $sessions = (int) $row->sessions;
            $bounces = (int) $row->bounced_sessions_count;
            $completed = (int) $row->bookings_completed;

            $bounceRate = $sessions > 0 ? round(($bounces / $sessions) * 100, 1) : 0.0;
            $conversionRate = $sessions > 0 ? round(($completed / $sessions) * 100, 1) : 0.0;

            return [
                ...$country,
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

        return $countries;
    }
}
