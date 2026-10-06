<?php

namespace App\Domains\Students\Services;

use App\Domains\Administration\Services\OperationalReasonCatalog;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Models\PackageRenewal;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PackageRenewalService
{
    /** @param array<string, mixed> $data */
    public function renew(StudentPackage $previous, array $data, ?int $administratorId): PackageRenewal
    {
        $values = Validator::make($data, [
            'renewal_date' => ['required', 'date_format:Y-m-d'], 'idempotency_key' => ['required', 'string', 'max:100'],
            'reason_code' => ['nullable', 'string', 'max:40'], 'notes' => ['nullable', 'string', 'max:1000'],
            'offering_key' => ['nullable', 'in:'.implode(',', array_keys(StudentLedgerService::PRESETS))],
            'expiration_date' => ['nullable', 'date_format:Y-m-d'],
        ])->validate();
        OperationalReasonCatalog::validate($values['reason_code'] ?? null);
        $fingerprint = hash('sha256', json_encode([$previous->id, $values], JSON_THROW_ON_ERROR));

        return app(DatabaseCapability::class)->transaction(function () use ($previous, $values, $fingerprint, $administratorId): PackageRenewal {
            $student = Student::query()->lockForUpdate()->findOrFail($previous->student_id);
            $source = StudentPackage::query()->with('entitlements.type')->lockForUpdate()->findOrFail($previous->id);
            if ($source->student_id !== $student->id) {
                throw ValidationException::withMessages(['package' => 'Package ownership changed. Reload and try again.']);
            }
            $existing = PackageRenewal::query()->where('idempotency_key', $values['idempotency_key'])->first();
            if ($existing) {
                if ($existing->student_id !== $student->id || $existing->fingerprint !== $fingerprint) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This renewal key was already used for different details.']);
                }

                return $existing;
            }
            $offering = $values['offering_key'] ?? (isset(StudentLedgerService::PRESETS[$source->offering_key]) ? $source->offering_key : null);
            if ($offering === null && ($source->identity_state === 'legacy_unclassified' || $source->entitlements->count() !== 1)) {
                throw ValidationException::withMessages(['offering_key' => 'Choose a classified offering for this renewal.']);
            }
            $purchase = app(StudentLedgerService::class)->createPackage(
                $student, $source->package_name, $source->total_sessions_allocated, $source->original_price, $source->discount_amount,
                $source->currency, $values['expiration_date'] ?? null, 'renewal:'.$values['idempotency_key'], $administratorId,
                $offering, null, $source->entitlements->first()?->type?->code,
            );
            $attributes = $values;
            unset($attributes['offering_key'], $attributes['expiration_date']);
            $renewal = PackageRenewal::query()->create(array_merge($attributes, ['student_id' => $student->id, 'previous_package_id' => $source->id,
                'new_package_id' => $purchase->id, 'fingerprint' => $fingerprint, 'created_by' => $administratorId, 'created_at' => now('UTC')]));
            app(AuditLogService::class)->log('finance_package_renewed', StudentPackage::class, $source->id, null, ['previous_package_id' => $source->id, 'new_package_id' => $purchase->id], $administratorId);

            return $renewal;
        }, 5);
    }
}
