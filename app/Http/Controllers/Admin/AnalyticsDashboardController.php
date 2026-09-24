<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Analytics\Models\DailyCountryMetric;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\CMS\Models\Setting;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsDashboardController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    public function index(Request $request): View
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

        $startDateUtc = $startCairo->setTimezone('UTC');
        $endDateUtc = $endCairo->setTimezone('UTC');

        $windowMinutes = (int) Setting::get('active_visitor_window', 5);
        $activeVisitorsCount = $this->analyticsService->getActiveVisitorsCount($windowMinutes);
        $activeVisitors = $this->analyticsService->getActiveVisitorsSummary($windowMinutes);
        $primaryFunnel = $this->analyticsService->getPrimaryBookingFunnel($startDateUtc, $endDateUtc);
        $resourceFunnel = $this->analyticsService->getResourceFunnel($startDateUtc, $endDateUtc);
        $gameFunnel = $this->analyticsService->getGameFunnel($startDateUtc, $endDateUtc);
        $acquisition = $this->analyticsService->getAcquisitionPerformance($startDateUtc, $endDateUtc);
        $countryActivity = DailyCountryMetric::query()
            ->whereBetween('metric_date', [$startCairo->toDateString(), $endCairo->toDateString()])
            ->selectRaw('country_code, SUM(unique_visitors) AS unique_visitors, SUM(sessions) AS sessions, SUM(booking_cta_clicks) AS booking_cta_clicks, SUM(bookings_completed) AS bookings_completed, SUM(resource_requests) AS resource_requests')
            ->groupBy('country_code')
            ->orderByDesc('unique_visitors')
            ->orderBy('country_code')
            ->get();

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
        ]);
    }
}
