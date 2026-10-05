<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Reporting\Services\ExportService;
use App\Domains\Reporting\Services\ReportPeriod;
use App\Domains\Timezone\Services\TimezoneDisplayService;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaintenanceAnalyticsController extends Controller
{
    public function index(Request $request, TimezoneDisplayService $countries): View
    {
        $filters = $this->filters($request);
        $query = $this->query($filters);
        $summary = (clone $query)->selectRaw('COUNT(*) AS hits, COUNT(DISTINCT visitor_id) AS visitors, COALESCE(SUM(is_bounced), 0) AS bounces')->first();
        $countryVisits = (clone $query)->selectRaw("CASE WHEN country_code IS NULL OR country_code IN ('', 'XX', 'ZZ') THEN 'ZZ' ELSE UPPER(country_code) END AS country_group, visitor_id, is_bounced");
        $countryRows = DB::query()->fromSub($countryVisits, 'visits')->selectRaw('country_group AS country_code, COUNT(*) AS hits, COUNT(DISTINCT visitor_id) AS visitors, SUM(is_bounced) AS bounces')
            ->groupBy('country_group')->orderByDesc('hits')->orderBy('country_group')->get()
            ->map(fn (object $row): array => [...$countries->countryDisplay($row->country_code), 'hits' => (int) $row->hits, 'visitors' => (int) $row->visitors, 'bounce_rate' => $row->hits > 0 ? round(100 * $row->bounces / $row->hits, 1) : 0.0]);
        $rows = (clone $query)->orderByDesc('id')->paginate(30)->withQueryString();
        $rows->through(fn (object $row): object => (object) [...(array) $row, ...$countries->countryDisplay($row->country_code)]);

        return view('admin.analytics.maintenance', [
            'title' => 'Maintenance Mode Analytics', 'filters' => $filters,
            'maintenanceHitsCount' => (int) $summary->hits, 'maintenanceUniqueVisitors' => (int) $summary->visitors,
            'maintenanceBounceRate' => $summary->hits > 0 ? round(100 * $summary->bounces / $summary->hits, 1) : 0.0,
            'maintenanceCountries' => $countryRows, 'maintenanceRows' => $rows,
            'businessTimezone' => app(TimezoneService::class)->getBusinessTimezone(),
        ]);
    }

    public function export(Request $request): StreamedResponse|BinaryFileResponse
    {
        $filters = $this->filters($request);
        $format = $filters['format'] ?? 'csv';
        $headers = [
            'Timestamp (UTC)',
            'Visitor ID',
            'Country Code',
            'Requested URL',
            'Referrer',
            'Bounced (Intercepted)',
        ];

        $rowsGenerator = function () use ($filters) {
            $cursor = $this->query($filters)
                ->orderByDesc('id')
                ->cursor();

            foreach ($cursor as $row) {
                yield [
                    $row->created_at,
                    $row->visitor_id,
                    $row->country_code,
                    $row->url,
                    $row->referrer ?? 'Direct / None',
                    $row->is_bounced ? 'Yes' : 'No',
                ];
            }
        };

        $baseFilename = 'maintenance_traffic_'.now()->format('Ymd_His');

        return app(ExportService::class)->export(
            $baseFilename,
            $headers,
            $rowsGenerator(),
            $format,
            'Maintenance Traffic'
        );
    }

    private function filters(Request $request): array
    {
        return app(ReportPeriod::class)->filters($request, ['country' => ['nullable', 'string', 'size:2'], 'path' => ['nullable', 'string', 'max:500']]);
    }

    private function query(array $filters): Builder
    {
        [$start, $end] = app(ReportPeriod::class)->bounds($filters);

        return DB::table('maintenance_visits')->whereBetween('created_at', [$start, $end])
            ->when($filters['country'] ?? null, function (Builder $query, string $country): void {
                if (strtoupper($country) === 'ZZ') {
                    $query->where(fn (Builder $unknown) => $unknown->whereNull('country_code')->orWhereIn('country_code', ['', 'XX', 'ZZ']));
                } else {
                    $query->where('country_code', strtoupper($country));
                }
            })
            ->when($filters['path'] ?? null, fn ($q, $path) => $q->where('url', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $path).'%'));
    }
}
