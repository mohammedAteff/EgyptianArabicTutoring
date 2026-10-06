<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Students\Models\PackageRenewal;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\FinancialStatementService;
use App\Domains\Students\Services\InstallmentScheduleService;
use App\Domains\Students\Services\PackageRenewalService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PackageLifecycleController extends Controller
{
    public function show(StudentPackage $package, FinancialStatementService $statements): View
    {
        return view('admin.billing.lifecycle', $statements->statement($package) + ['originRenewal' => PackageRenewal::query()->where('new_package_id', $package->id)->first(), 'renewals' => PackageRenewal::query()->where('previous_package_id', $package->id)->with('newPackage')->orderByDesc('id')->paginate(15)]);
    }

    public function installments(Request $request, StudentPackage $package, InstallmentScheduleService $service): RedirectResponse
    {
        $values = $request->validate(['installments' => ['required', 'array', 'max:12'], 'installments.*' => ['array:expected_amount,due_date'], 'idempotency_key' => ['required', 'string', 'max:100']]);
        $rows = array_values(array_filter($values['installments'], fn (array $row): bool => ! empty($row['expected_amount']) || ! empty($row['due_date'])));
        $service->create($package, $rows, $values['idempotency_key'], (int) $request->user('web')->id);

        return back()->with('success', 'Expected installments saved. Actual payment history is unchanged.');
    }

    public function void(Request $request, StudentPackage $package, InstallmentScheduleService $service): RedirectResponse
    {
        $service->void($package, (int) $request->user('web')->id);

        return back()->with('success', 'Forecast schedule voided. Actual payments and outstanding balance are preserved.');
    }

    public function renew(Request $request, StudentPackage $package, PackageRenewalService $service): RedirectResponse
    {
        $renewal = $service->renew($package, $request->only(['renewal_date', 'idempotency_key', 'reason_code', 'notes', 'offering_key', 'expiration_date']), (int) $request->user('web')->id);

        return redirect()->route('admin.packages.lifecycle', $renewal->new_package_id)->with('success', 'Renewal recorded as a new purchase. The previous purchase is preserved.');
    }
}
