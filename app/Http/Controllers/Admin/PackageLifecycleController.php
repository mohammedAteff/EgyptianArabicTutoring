<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Students\Models\PackageRenewal;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\EntitlementService;
use App\Domains\Students\Services\FinancialStatementService;
use App\Domains\Students\Services\InstallmentScheduleService;
use App\Domains\Students\Services\PackagePauseService;
use App\Domains\Students\Services\PackageRenewalService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class PackageLifecycleController extends Controller
{
    public function show(StudentPackage $package, FinancialStatementService $statements, EntitlementService $entitlements): View
    {
        $siblingPackages = $package->student_id
            ? StudentPackage::query()->where('student_id', $package->student_id)->where('id', '!=', $package->id)
                ->with('entitlements.type')->get()->filter(fn (StudentPackage $sibling): bool => $entitlements->eligible($sibling))
            : collect();

        return view('admin.billing.lifecycle', $statements->statement($package) + [
            'originRenewal' => PackageRenewal::query()->where('new_package_id', $package->id)->first(),
            'renewals' => PackageRenewal::query()->where('previous_package_id', $package->id)->with('newPackage')->orderByDesc('id')->paginate(15),
            'siblingPackages' => $siblingPackages,
        ]);
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

    public function pause(Request $request, StudentPackage $package, PackagePauseService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $service->pause($package, $data['reason'], (int) $request->user('web')->id);

        return back()->with('success', 'Package paused successfully.');
    }

    public function resume(Request $request, StudentPackage $package, PackagePauseService $service): RedirectResponse
    {
        $service->resume($package, (int) $request->user('web')->id);

        return back()->with('success', 'Package resumed. Its expiry was extended by the paused time.');
    }

    public function notes(Request $request, StudentPackage $package, AuditLogService $audit): RedirectResponse
    {
        $request->validate(['admin_notes' => ['nullable', 'string', 'max:5000']]);

        $package->update(['admin_notes' => $request->input('admin_notes')]);

        $audit->log('package_notes_updated', StudentPackage::class, $package->id, null, ['has_notes' => ! empty($request->input('admin_notes'))], $request->user('web')->id);

        return back()->with('success', 'Package business notes saved.');
    }

    public function transfer(Request $request, StudentPackage $package, StudentLedgerService $ledger): RedirectResponse
    {
        $data = $request->validate([
            'target_package_id' => ['required', 'integer', 'exists:student_packages,id'],
            'credits' => ['required', 'integer', 'min:1', 'max:500'],
            'source_allocation_id' => ['required', 'integer', 'min:1'],
            'target_allocation_id' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:200'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        $targetPackage = StudentPackage::findOrFail($data['target_package_id']);

        try {
            $ledger->transferCredits(
                $package,
                $targetPackage,
                (int) $data['credits'],
                $data['description'],
                $data['idempotency_key'],
                $request->user('web')->id,
                (int) $data['source_allocation_id'],
                (int) $data['target_allocation_id'],
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['credits' => $exception->getMessage()]);
        }

        return back()->with('success', "{$data['credits']} credit(s) transferred to package #{$targetPackage->id}.");
    }
}
