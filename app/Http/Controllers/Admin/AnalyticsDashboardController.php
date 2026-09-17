<?php

namespace App\Http\Controllers\Admin;

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

        $now = CarbonImmutable::now();
        $startDate = match ($range) {
            'today' => $now->startOfDay(),
            '7d' => $now->subDays(7)->startOfDay(),
            '90d' => $now->subDays(90)->startOfDay(),
            'month' => $now->startOfMonth(),
            default => $now->subDays(30)->startOfDay(),
        };
        $endDate = $now->endOfDay();

        $windowMinutes = (int) Setting::get('active_visitor_window', 5);
        $activeVisitorsCount = $this->analyticsService->getActiveVisitorsCount($windowMinutes);
        $activeVisitors = $this->analyticsService->getActiveVisitorsSummary($windowMinutes);
        $primaryFunnel = $this->analyticsService->getPrimaryBookingFunnel($startDate, $endDate);
        $resourceFunnel = $this->analyticsService->getResourceFunnel($startDate, $endDate);
        $gameFunnel = $this->analyticsService->getGameFunnel($startDate, $endDate);
        $acquisition = $this->analyticsService->getAcquisitionPerformance($startDate, $endDate);

        return view('admin.analytics.index', [
            'title' => 'First-Party Analytics & Business Funnels',
            'range' => $range,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'activeVisitorsCount' => $activeVisitorsCount,
            'activeVisitors' => $activeVisitors,
            'primaryFunnel' => $primaryFunnel,
            'resourceFunnel' => $resourceFunnel,
            'gameFunnel' => $gameFunnel,
            'acquisition' => $acquisition,
        ]);
    }
}
