<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Students\Models\PackageRenewal;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\FinancialStatementService;
use App\Domains\Students\Services\InstallmentScheduleService;
use App\Domains\Students\Services\PackageRenewalService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PackageLifecycleController extends Controller
{
    public function show(StudentPackage $package, FinancialStatementService $statements): View
    {
        $siblingPackages = $package->student_id
            ? StudentPackage::query()->where('student_id', $package->student_id)->where('id', '!=', $package->id)->get()
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

    public function pause(Request $request, StudentPackage $package, AuditLogService $audit): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:255']]);

        // User policy: any future bookings must be cancelled first
        $futureBookings = Booking::query()
            ->where('student_package_id', $package->id)
            ->where('start_at_utc', '>', now('UTC'))
            ->whereIn('status', ['confirmed', 'held', 'pending'])
            ->count();

        if ($futureBookings > 0) {
            throw ValidationException::withMessages([
                'package' => "Cannot pause package: {$futureBookings} future scheduled session(s) must be cancelled first.",
            ]);
        }

        if ($package->isPaused()) {
            throw ValidationException::withMessages(['package' => 'This package is already paused.']);
        }

        $package->update([
            'paused_at' => now('UTC'),
            'pause_reason' => $request->input('reason'),
            'paused_by' => $request->user('web')->id,
        ]);

        $audit->log('package_paused', StudentPackage::class, $package->id, null, ['reason' => $request->input('reason')], $request->user('web')->id);

        return back()->with('success', 'Package paused successfully.');
    }

    public function resume(Request $request, StudentPackage $package, AuditLogService $audit): RedirectResponse
    {
        if (! $package->isPaused()) {
            throw ValidationException::withMessages(['package' => 'This package is not currently paused.']);
        }

        $pausedDays = max(1, (int) round($package->paused_at->diffInDays(now('UTC'))));
        $updates = [
            'paused_at' => null,
            'pause_reason' => null,
            'paused_by' => null,
        ];

        if ($package->expires_at) {
            $updates['expires_at'] = $package->expires_at->addDays($pausedDays);
        }
        if ($package->expiration_date) {
            $updates['expiration_date'] = $package->expiration_date->addDays($pausedDays);
        }

        $package->update($updates);

        $audit->log('package_resumed', StudentPackage::class, $package->id, null, ['days_paused' => $pausedDays], $request->user('web')->id);

        return back()->with('success', "Package resumed. Expiry extended by {$pausedDays} day(s).");
    }

    public function notes(Request $request, StudentPackage $package, AuditLogService $audit): RedirectResponse
    {
        $request->validate(['admin_notes' => ['nullable', 'string', 'max:5000']]);

        $package->update(['admin_notes' => $request->input('admin_notes')]);

        $audit->log('package_notes_updated', StudentPackage::class, $package->id, null, ['has_notes' => ! empty($request->input('admin_notes'))], $request->user('web')->id);

        return back()->with('success', 'Package business notes saved.');
    }

    public function transfer(Request $request, StudentPackage $package, StudentLedgerService $ledger, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'target_package_id' => ['required', 'integer', 'exists:student_packages,id'],
            'credits' => ['required', 'integer', 'min:1', 'max:500'],
            'source_allocation_id' => ['nullable', 'integer'],
            'target_allocation_id' => ['nullable', 'integer'],
            'description' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $targetPackage = StudentPackage::findOrFail($data['target_package_id']);

        $ledger->transferCredits(
            $package,
            $targetPackage,
            (int) $data['credits'],
            $data['description'],
            $data['idempotency_key'],
            $request->user('web')?->id,
            $data['source_allocation_id'] ? (int) $data['source_allocation_id'] : null,
            $data['target_allocation_id'] ? (int) $data['target_allocation_id'] : null,
        );

        $audit->log('credits_transferred', StudentPackage::class, $package->id, null, [
            'source_package_id' => $package->id,
            'target_package_id' => $targetPackage->id,
            'credits' => $data['credits'],
        ], $request->user('web')?->id);

        return back()->with('success', "{$data['credits']} credit(s) transferred to package #{$targetPackage->id}.");
    }
}
