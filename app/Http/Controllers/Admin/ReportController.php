<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Reporting\Services\ExportService;
use App\Domains\Reporting\Services\ReportService;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService,
        protected ExportService $exportService
    ) {}

    public function index(Request $request): View
    {
        $reportType = $request->query('type', 'traffic');
        $range = $request->query('range', '30d');

        [$start, $end] = $this->resolveDateRange($range, $request);

        $data = match ($reportType) {
            'bookings' => $this->reportService->getBookingsReport($start, $end, $request->query('status')),
            'resources' => $this->reportService->getResourcesReport($start, $end),
            'social' => $this->reportService->getSocialReport($start, $end),
            'events' => $this->reportService->getEventsReport($start, $end, $request->query('event_name')),
            default => $this->reportService->getTrafficReport($start, $end, $request->query('source')),
        };

        return view('admin.reports.index', [
            'title' => 'Operational Reports & Data Exports',
            'reportType' => $reportType,
            'range' => $range,
            'start' => $start,
            'end' => $end,
            'reportData' => $data,
        ]);
    }

    public function export(Request $request): Response
    {
        $reportType = $request->query('type', 'traffic');
        $format = $request->query('format', 'csv');
        $range = $request->query('range', '30d');

        [$start, $end] = $this->resolveDateRange($range, $request);

        $timestamp = now()->format('Ymd_His');

        return match ($reportType) {
            'bookings' => $this->exportBookings($start, $end, $format, $timestamp, $request->query('status')),
            'resources' => $this->exportResources($start, $end, $format, $timestamp),
            'social' => $this->exportSocial($start, $end, $format, $timestamp),
            'events' => $this->exportEvents($start, $end, $format, $timestamp, $request->query('event_name')),
            default => $this->exportTraffic($start, $end, $format, $timestamp, $request->query('source')),
        };
    }

    protected function exportTraffic(CarbonImmutable $start, CarbonImmutable $end, string $format, string $ts, ?string $source): Response
    {
        $report = $this->reportService->getTrafficReport($start, $end, $source);
        $headers = ['Date', 'Unique Visitors', 'Sessions', 'Page Views', 'Top Source'];
        $rows = $report['rows']->map(fn ($r) => [
            $r->report_date,
            $r->visitors,
            $r->sessions,
            $r->page_views,
            $r->top_source,
        ]);

        $filename = "traffic_report_{$ts}.{$format}";

        return $format === 'xlsx'
            ? $this->exportService->exportXlsx($filename, $headers, $rows, 'Traffic Report')
            : $this->exportService->exportCsv($filename, $headers, $rows);
    }

    protected function exportBookings(CarbonImmutable $start, CarbonImmutable $end, string $format, string $ts, ?string $status): Response
    {
        $report = $this->reportService->getBookingsReport($start, $end, $status);
        $headers = [
            'Booking Code',
            'Customer Name',
            'Customer Email',
            'Session Type',
            'Status',
            'Date (Cairo)',
            'Time (Cairo)',
            'Time (Student Local)',
            'Acquisition Source',
            'Campaign',
            'Booked At',
        ];
        $rows = $report['rows']->map(fn ($b) => [
            $b['booking_code'],
            $b['customer_name'],
            $b['customer_email'],
            $b['session_title'],
            $b['status'],
            $b['lesson_date_cairo'],
            $b['lesson_time_cairo'],
            $b['lesson_time_student'],
            $b['source'],
            $b['campaign'],
            $b['created_at'],
        ]);

        $filename = "bookings_report_{$ts}.{$format}";

        return $format === 'xlsx'
            ? $this->exportService->exportXlsx($filename, $headers, $rows, 'Bookings Report')
            : $this->exportService->exportCsv($filename, $headers, $rows);
    }

    protected function exportResources(CarbonImmutable $start, CarbonImmutable $end, string $format, string $ts): Response
    {
        $report = $this->reportService->getResourcesReport($start, $end);
        $headers = ['Resource Title', 'Slug', 'File Type', 'Status', 'Lead Requests', 'Downloads', 'Conversion Rate'];
        $rows = $report['rows']->map(fn ($r) => [
            $r['title'],
            $r['slug'],
            $r['file_type'],
            $r['status'],
            $r['requests'],
            $r['downloads'],
            $r['conversion_pct'],
        ]);

        $filename = "resources_report_{$ts}.{$format}";

        return $format === 'xlsx'
            ? $this->exportService->exportXlsx($filename, $headers, $rows, 'Resources Report')
            : $this->exportService->exportCsv($filename, $headers, $rows);
    }

    protected function exportSocial(CarbonImmutable $start, CarbonImmutable $end, string $format, string $ts): Response
    {
        $report = $this->reportService->getSocialReport($start, $end);
        $headers = ['Platform', 'Event Name', 'Page', 'Date', 'Total Clicks'];
        $rows = $report['rows']->map(fn ($s) => [
            $s['platform'],
            $s['event_name'],
            $s['page'],
            $s['date'],
            $s['clicks'],
        ]);

        $filename = "social_clicks_report_{$ts}.{$format}";

        return $format === 'xlsx'
            ? $this->exportService->exportXlsx($filename, $headers, $rows, 'Social Clicks')
            : $this->exportService->exportCsv($filename, $headers, $rows);
    }

    protected function exportEvents(CarbonImmutable $start, CarbonImmutable $end, string $format, string $ts, ?string $eventName): Response
    {
        $report = $this->reportService->getEventsReport($start, $end, $eventName);
        $headers = ['Event Name', 'Page', 'Source', 'Date', 'Occurrences'];
        $rows = $report['rows']->map(fn ($e) => [
            $e->event_name,
            $e->page,
            $e->source,
            $e->report_date,
            $e->event_count,
        ]);

        $filename = "events_report_{$ts}.{$format}";

        return $format === 'xlsx'
            ? $this->exportService->exportXlsx($filename, $headers, $rows, 'Events Log')
            : $this->exportService->exportCsv($filename, $headers, $rows);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function resolveDateRange(string $range, Request $request): array
    {
        $now = CarbonImmutable::now();

        if ($range === 'custom' && $request->filled('start_date') && $request->filled('end_date')) {
            return [
                CarbonImmutable::parse($request->query('start_date'))->startOfDay(),
                CarbonImmutable::parse($request->query('end_date'))->endOfDay(),
            ];
        }

        return match ($range) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            '7d' => [$now->subDays(7)->startOfDay(), $now->endOfDay()],
            '90d' => [$now->subDays(90)->startOfDay(), $now->endOfDay()],
            'this_month' => [$now->startOfMonth(), $now->endOfMonth()],
            'last_month' => [$now->subMonth()->startOfMonth(), $now->subMonth()->endOfMonth()],
            default => [$now->subDays(30)->startOfDay(), $now->endOfDay()],
        };
    }
}
