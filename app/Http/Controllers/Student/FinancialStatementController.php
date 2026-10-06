<?php

namespace App\Http\Controllers\Student;

use App\Domains\Reporting\Services\ExportService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\FinancialStatementService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FinancialStatementController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Student $student */
        $student = $request->attributes->get('student');

        return response()->view('student.statements', ['packages' => StudentPackage::query()->where('student_id', $student->id)->orderByDesc('id')->paginate(20)])
            ->header('Cache-Control', 'private, no-store');
    }

    private function assertOwner(Request $request, StudentPackage $package): void
    {
        /** @var Student $student */
        $student = $request->attributes->get('student');
        abort_unless($package->student_id === $student->id, 404);
    }

    public function show(Request $request, StudentPackage $package, FinancialStatementService $statements): Response
    {
        $this->assertOwner($request, $package);

        return response()->view('admin.billing.statement', $statements->statement($package) + ['receipt' => null, 'studentView' => true])->header('Cache-Control', 'private, no-store');
    }

    public function receipt(Request $request, StudentPackage $package, int $payment, FinancialStatementService $statements): Response
    {
        $this->assertOwner($request, $package);
        $data = $statements->statement($package);
        $receipt = $package->payments->firstWhere('id', $payment);
        abort_unless($receipt !== null, 404);

        return response()->view('admin.billing.statement', $data + ['receipt' => $receipt, 'studentView' => true])->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, StudentPackage $package, FinancialStatementService $statements, ExportService $exports): Response
    {
        $this->assertOwner($request, $package);
        $values = $request->validate(['format' => ['nullable', 'in:csv,xlsx']]);
        $response = $exports->export('package_statement_'.$package->id, ['Record', 'Internal ID', 'Date (business timezone)', 'Amount', 'Currency', 'Description / method', 'Recorded external reference'], $statements->rows($package), $values['format'] ?? 'csv', 'Package Statement');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
