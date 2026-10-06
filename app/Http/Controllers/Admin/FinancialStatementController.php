<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Reporting\Services\ExportService;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\FinancialStatementService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FinancialStatementController extends Controller
{
    public function show(StudentPackage $package, FinancialStatementService $statements): Response
    {
        return response()->view('admin.billing.statement', $statements->statement($package) + ['receipt' => null, 'studentView' => false])->header('Cache-Control', 'private, no-store');
    }

    public function receipt(StudentPackage $package, int $payment, FinancialStatementService $statements): Response
    {
        $data = $statements->statement($package);
        $receipt = $package->payments->firstWhere('id', $payment);
        abort_unless($receipt !== null, 404);

        return response()->view('admin.billing.statement', $data + ['receipt' => $receipt, 'studentView' => false])->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, StudentPackage $package, FinancialStatementService $statements, ExportService $exports): Response
    {
        $values = $request->validate(['format' => ['nullable', 'in:csv,xlsx']]);
        $response = $exports->export('package_statement_'.$package->id, ['Record', 'Internal ID', 'Date (business timezone)', 'Amount', 'Currency', 'Description / method', 'Recorded external reference'], $statements->rows($package), $values['format'] ?? 'csv', 'Package Statement');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
