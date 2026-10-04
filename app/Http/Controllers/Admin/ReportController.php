<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Reporting\Services\ExportService;
use App\Domains\Reporting\Services\ReportPeriod;
use App\Domains\Reporting\Services\ReportService;
use App\Domains\Resources\Models\ResourceCategory;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $filters = $this->filters($request);
        $reportType = $filters['type'];
        $range = $filters['range'];
        [$start, $end] = app(ReportPeriod::class)->bounds($filters);
        $cutoverDate = Setting::get('analytics_authoritative_cutover_date');
        $cutoverDate = is_string($cutoverDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoverDate)
            ? $cutoverDate
            : null;
        $isBeforeCutover = $cutoverDate !== null
            && $start->setTimezone(app(TimezoneService::class)->getBusinessTimezone())->toDateString() < $cutoverDate;

        $data = match ($reportType) {
            'bookings' => $this->reportService->getBookingsReport($start, $end, $filters['status'] ?? null, $filters),
            'resources' => $this->reportService->getResourcesReport($start, $end, $filters),
            'social' => $this->reportService->getSocialReport($start, $end, $filters),
            'events' => $this->reportService->getEventsReport($start, $end, $filters['event_name'] ?? null, $filters),
            'campaigns' => $this->reportService->getCampaignContentReport($start, $end, $filters['campaign'] ?? null, $filters),
            default => $this->reportService->getTrafficReport($start, $end, $filters['source'] ?? null),
        };

        return view('admin.reports.index', [
            'title' => 'Operational Reports & Data Exports',
            'reportType' => $reportType, 'filters' => $filters, 'filterFields' => $this->fields($reportType),
            'range' => $range,
            'start' => $start->setTimezone(app(TimezoneService::class)->getBusinessTimezone()),
            'end' => $end->setTimezone(app(TimezoneService::class)->getBusinessTimezone()),
            'reportData' => $data,
            'authoritativeCutoverDate' => $cutoverDate,
            'isBeforeAuthoritativeCutover' => $isBeforeCutover,
        ]);
    }

    public function export(Request $request): Response
    {
        $filters = $this->filters($request);
        $reportType = $filters['type'];
        $format = $filters['format'] ?? 'csv';

        [$start, $end] = app(ReportPeriod::class)->bounds($filters);

        $timestamp = now()->format('Ymd_His');

        return match ($reportType) {
            'bookings' => $this->exportBookings($start, $end, $format, $timestamp, $filters['status'] ?? null, $filters),
            'resources' => $this->exportResources($start, $end, $format, $timestamp, $filters),
            'social' => $this->exportSocial($start, $end, $format, $timestamp, $filters),
            'events' => $this->exportEvents($start, $end, $format, $timestamp, $filters['event_name'] ?? null, $filters),
            'campaigns' => $this->exportCampaigns($start, $end, $format, $timestamp, $filters['campaign'] ?? null, $filters),
            default => $this->exportTraffic($start, $end, $format, $timestamp, $filters['source'] ?? null),
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

        $rows->push([$report['summary']['visitors_is_daily_sum'] ? 'Period total (sum of daily uniques)' : 'Period total (unique visitors)', $report['summary']['visitors'], $report['summary']['sessions'], $report['summary']['page_views'], $source ?: 'all']);

        $filename = "traffic_report_{$ts}.{$format}";

        return $format === 'xlsx'
            ? $this->exportService->exportXlsx($filename, $headers, $rows, 'Traffic Report')
            : $this->exportService->exportCsv($filename, $headers, $rows);
    }

    protected function exportBookings(CarbonImmutable $start, CarbonImmutable $end, string $format, string $ts, ?string $status, array $filters): Response
    {
        $report = $this->reportService->getBookingsReport($start, $end, $status, $filters);
        $headers = [
            'Booking Code',
            'Customer Name',
            'Customer Email',
            'Session Type',
            'Status',
            'Date (Business Time)',
            'Time (Business Time)',
            'Time (Student Local)',
            'Acquisition Source',
            'Campaign',
            'Content',
            'Referrer',
            'Touch Time',
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
            $b['content'],
            $b['referrer'],
            $b['touch_at'],
            $b['created_at'],
        ]);

        $filename = "bookings_report_{$ts}.{$format}";

        return $format === 'xlsx'
            ? $this->exportService->exportXlsx($filename, $headers, $rows, 'Bookings Report')
            : $this->exportService->exportCsv($filename, $headers, $rows);
    }

    protected function exportCampaigns(CarbonImmutable $start, CarbonImmutable $end, string $format, string $ts, ?string $campaign, array $filters): Response
    {
        $report = $this->reportService->getCampaignContentReport($start, $end, $campaign, $filters);
        $headers = ['Campaign', 'Content (Ad / Post)', 'Source', 'Unique Visitors', 'Total Bookings', 'Confirmed / Completed', 'Conversion Rate (%)'];
        $rows = $report['rows']->map(fn ($r) => [
            $r['campaign'],
            $r['content'],
            $r['source'],
            $r['visitors_count'],
            $r['bookings_count'],
            $r['confirmed_bookings'],
            $r['conversion_rate'].'%',
        ]);

        $filename = "campaign_attribution_{$ts}.{$format}";

        return $format === 'xlsx'
            ? $this->exportService->exportXlsx($filename, $headers, $rows, 'Campaign Attribution')
            : $this->exportService->exportCsv($filename, $headers, $rows);
    }

    protected function exportResources(CarbonImmutable $start, CarbonImmutable $end, string $format, string $ts, array $filters): Response
    {
        $report = $this->reportService->getResourcesReport($start, $end, $filters);
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

    protected function exportSocial(CarbonImmutable $start, CarbonImmutable $end, string $format, string $ts, array $filters): Response
    {
        $report = $this->reportService->getSocialReport($start, $end, $filters);
        $headers = ['Platform', 'Placement', 'Event Name', 'Page', 'Date', 'Total Clicks', 'Daily Unique Visitors (dimension group)', 'Language', 'Country', 'Source', 'Medium', 'Campaign', 'Context'];
        $rows = $report['rows']->map(fn ($s) => [
            $s['platform'], $s['placement'], $s['event_name'], $s['page'], $s['date'], $s['clicks'],
            $s['unique_visitors'], $s['language'], $s['country'], $s['source'], $s['medium'], $s['campaign'], $s['context'],
        ]);

        $filename = "social_clicks_report_{$ts}.{$format}";

        return $format === 'xlsx'
            ? $this->exportService->exportXlsx($filename, $headers, $rows, 'Social Clicks')
            : $this->exportService->exportCsv($filename, $headers, $rows);
    }

    protected function exportEvents(CarbonImmutable $start, CarbonImmutable $end, string $format, string $ts, ?string $eventName, array $filters): Response
    {
        $report = $this->reportService->getEventsReport($start, $end, $eventName, $filters);
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

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $type = $request->validate(['type' => ['nullable', Rule::in(['traffic', 'bookings', 'resources', 'social', 'events', 'campaigns'])]])['type'] ?? 'traffic';
        $rules = [];
        foreach ($this->fields($type) as $key => $definition) {
            if (! in_array($key, ['range', 'start_date', 'end_date'], true)) {
                $rules[$key] = ['nullable', 'string', 'max:500'];
                if (is_array($definition[1])) {
                    $rules[$key][] = Rule::in(array_keys($definition[1]));
                }
            }
        }
        if ($type === 'resources') {
            $rules['resource_id'] = ['nullable', 'integer', 'exists:resources,id'];
            $rules['category_id'] = ['nullable', 'integer', 'exists:resource_categories,id'];
        }
        $filters = app(ReportPeriod::class)->filters($request, $rules);
        if ($type === 'social') {
            if (! empty($filters['platform'])) {
                $filters['platform'] = strtolower(trim($filters['platform']));
            }
            if (! empty($filters['country'])) {
                $filters['country'] = strtoupper(trim($filters['country']));
            }
        }
        $filters['type'] = $type;

        return $filters;
    }

    /** @return array<string, array<mixed>> */
    private function fields(string $type): array
    {
        $dimensions = match ($type) {
            'bookings' => ['status' => ['Status', ['confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'no_show' => 'No show', 'pending' => 'Pending']], 'source' => ['Source', 'text'], 'campaign' => ['Campaign', 'text']],
            'resources' => ['resource_id' => ['Resource', \App\Domains\Resources\Models\Resource::query()->orderBy('title')->pluck('title', 'id')->all()], 'category_id' => ['Category', ResourceCategory::query()->orderBy('name')->pluck('name', 'id')->all()]],
            'social' => ['platform' => ['Platform (e.g. whatsapp)', 'text'], 'placement' => ['Placement', ['footer_social' => 'Footer Social', 'floating_cta' => 'Floating CTA', 'unknown' => 'Unknown']], 'page_url' => ['Page (exact URL)', 'text'], 'country' => ['Country code', 'text'], 'source' => ['Source', 'text'], 'medium' => ['Medium', 'text'], 'campaign' => ['Campaign', 'text'], 'language' => ['Language', 'text'], 'context' => ['Context', ['public' => 'Public', 'portal' => 'Student portal', 'unknown' => 'Unknown']]],
            'events' => ['event_name' => ['Event', array_combine(AnalyticsService::ALLOWED_EVENTS, AnalyticsService::ALLOWED_EVENTS)], 'page_url' => ['Page (exact URL)', 'text'], 'source' => ['Source', 'text']],
            'campaigns' => ['campaign' => ['Campaign', 'text'], 'source' => ['Source', 'text'], 'content' => ['Content', 'text']],
            default => ['source' => ['Source', 'text']],
        };

        return array_merge(app(ReportPeriod::class)->fields(), $dimensions);
    }
}
