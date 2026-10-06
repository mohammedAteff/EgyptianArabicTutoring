<?php

namespace App\Domains\Students\Services;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Models\PackageInstallment;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Timezone\Services\TimezoneService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InstallmentScheduleService
{
    /** @param array<int, array<string, mixed>> $installments */
    public function create(StudentPackage $package, array $installments, string $key, ?int $administratorId): void
    {
        $values = Validator::make(['installments' => $installments, 'key' => $key], [
            'key' => ['required', 'string', 'max:100'], 'installments' => ['required', 'array', 'min:1', 'max:12'],
            'installments.*' => ['array:expected_amount,due_date'],
            'installments.*.expected_amount' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999'],
            'installments.*.due_date' => ['required', 'date_format:Y-m-d'],
        ])->validate();
        $rows = array_values($values['installments']);
        usort($rows, fn (array $a, array $b): int => strcmp($a['due_date'], $b['due_date']));
        $fingerprint = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
        app(DatabaseCapability::class)->transaction(function () use ($package, $rows, $key, $administratorId, $fingerprint): void {
            Student::query()->lockForUpdate()->findOrFail($package->student_id);
            $locked = StudentPackage::query()->lockForUpdate()->findOrFail($package->id);
            if ($locked->student_id !== $package->student_id) {
                throw ValidationException::withMessages(['package' => 'Package ownership changed. Reload and try again.']);
            }
            $existing = PackageInstallment::query()->where('student_package_id', $locked->id)->orderBy('sequence')->get();
            $keyOwner = PackageInstallment::query()->where('schedule_key', $key)->first();
            if ($keyOwner && $keyOwner->student_package_id !== $locked->id) {
                throw ValidationException::withMessages(['installments' => 'This schedule key belongs to another purchase.']);
            }
            if ($existing->isNotEmpty()) {
                if ($existing->first()->schedule_key === $key && $existing->first()->fingerprint === $fingerprint) {
                    return;
                }
                throw ValidationException::withMessages(['installments' => 'This purchase already has an immutable installment schedule.']);
            }
            $total = '0.00';
            foreach ($rows as $row) {
                $total = bcadd($total, (string) $row['expected_amount'], 2);
            }
            if (bccomp($total, $locked->final_price, 2) !== 0) {
                throw ValidationException::withMessages(['installments' => 'Expected installments must total the purchase final price. Actual payments are allocated in due-date order.']);
            }
            foreach ($rows as $index => $row) {
                PackageInstallment::query()->create(array_merge($row, ['student_package_id' => $locked->id, 'sequence' => $index + 1, 'schedule_key' => $key, 'fingerprint' => $fingerprint, 'created_by' => $administratorId]));
            }
            app(AuditLogService::class)->log('finance_installment_schedule_created', StudentPackage::class, $locked->id, null, ['installment_count' => count($rows)], $administratorId);
        }, 3);
    }

    /** @param array<string, mixed>|null $summary
     * @return array<int, array<string, mixed>> */
    public function projection(StudentPackage $package, ?array $summary = null, ?string $businessTimezone = null): array
    {
        $package->loadMissing('installments');
        $businessTimezone ??= app(TimezoneService::class)->getBusinessTimezone();
        $summary ??= app(StudentLedgerService::class)->summary($package, true, $businessTimezone);
        $remainingPaid = (string) $summary['net_paid'];
        $today = now($businessTimezone)->toDateString();
        $rows = [];
        foreach ($package->installments as $installment) {
            if ($installment->status === 'voided') {
                $rows[] = ['id' => $installment->id, 'due_date' => $installment->due_date->toDateString(), 'expected_amount' => $installment->expected_amount, 'paid' => '0.00', 'due' => '0.00', 'status' => 'voided'];

                continue;
            }
            $paid = bccomp($remainingPaid, $installment->expected_amount, 2) >= 0 ? $installment->expected_amount : $remainingPaid;
            $remainingPaid = bcsub($remainingPaid, $paid, 2);
            $due = bcsub($installment->expected_amount, $paid, 2);
            $status = bccomp($due, '0', 2) === 0 ? 'paid' : ($installment->due_date->toDateString() < $today ? 'overdue' : (bccomp($paid, '0', 2) > 0 ? 'partially_paid' : 'scheduled'));
            $rows[] = ['id' => $installment->id, 'due_date' => $installment->due_date->toDateString(), 'expected_amount' => $installment->expected_amount, 'paid' => $paid, 'due' => $due, 'status' => $status];
        }

        return $rows;
    }

    public function void(StudentPackage $package, ?int $administratorId): void
    {
        app(DatabaseCapability::class)->transaction(function () use ($package, $administratorId): void {
            Student::query()->lockForUpdate()->findOrFail($package->student_id);
            $locked = StudentPackage::query()->lockForUpdate()->findOrFail($package->id);
            if ($locked->student_id !== $package->student_id) {
                throw ValidationException::withMessages(['package' => 'Package ownership changed. Reload and try again.']);
            }
            PackageInstallment::query()->where('student_package_id', $locked->id)->update(['status' => 'voided']);
            app(AuditLogService::class)->log('finance_installment_schedule_voided', StudentPackage::class, $locked->id, null, ['status' => 'voided'], $administratorId);
        }, 3);
    }
}
