<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Reporting\Services\ExportService;
use App\Domains\Students\Services\ReceivablesReadModel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReceivablesController extends Controller
{
    public function index(Request $request, ReceivablesReadModel $reader): View
    {
        $filters = $reader->filters($request);
        $rows = [];
        $more = false;
        foreach ($reader->rows($filters) as $row) {
            if (count($rows) === 50) {
                $more = true;
                break;
            }
            $rows[] = $row;
        }

        return view('admin.billing.receivables', ['rows' => $rows, 'filters' => $filters, 'more' => $more, 'nextId' => $rows === [] ? null : $rows[array_key_last($rows)]['package']->id]);
    }

    public function export(Request $request, ReceivablesReadModel $reader, ExportService $exports): Response
    {
        $filters = $reader->filters($request);
        unset($filters['after_id']);
        $rows = function () use ($reader, $filters): \Generator {
            foreach ($reader->rows($filters) as $row) {
                $p = $row['package'];
                yield [$p->id, $p->student_id, $p->package_name, $p->currency, $row['summary']['net_paid'], $row['summary']['balance_due'], $row['overdue'], $row['summary']['overpaid']];
            }
        };

        return $exports->export('receivables', ['Purchase', 'Student ID', 'Package', 'Currency', 'Net paid', 'Due now', 'Overdue forecast', 'Overpaid'], $rows(), $filters['format'] ?? 'csv', 'Receivables');
    }
}
