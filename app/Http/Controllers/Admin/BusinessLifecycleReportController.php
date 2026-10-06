<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Reporting\Services\BusinessLifecycleReport;
use App\Domains\Reporting\Services\ExportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class BusinessLifecycleReportController extends Controller
{
    public function index(Request $request, BusinessLifecycleReport $report): View
    {
        $filters = $report->filters($request);
        $rows = [];
        foreach ($report->rows($filters) as $row) {
            $rows[] = $row;
            if (count($rows) === 50) {
                break;
            }
        }

        return view('admin.reports.lifecycle', ['rows' => $rows, 'filters' => $filters, 'totals' => $report->totals($filters)]);
    }

    public function export(Request $request, BusinessLifecycleReport $report, ExportService $exports): Response
    {
        $filters = $report->filters($request);
        $rows = function () use ($report, $filters): \Generator {
            foreach ($report->rows($filters) as $row) {
                yield $report->exportRow($row);
            }
        };

        return $exports->export('business_lifecycle', ['Purchase', 'Student ID', 'Package', 'Currency', 'Period payments', 'Period refunds', 'Period net receipts', 'Outstanding now', 'Overpayment now', 'Typed remaining credits now', 'Effective expiry', 'Renewal purchases in period'], $rows(), $filters['format'] ?? 'csv', 'Business Lifecycle');
    }
}
