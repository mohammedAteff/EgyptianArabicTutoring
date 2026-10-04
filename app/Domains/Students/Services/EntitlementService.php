<?php

namespace App\Domains\Students\Services;

use App\Domains\Booking\Models\SessionType;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Models\StudentPackageEntitlement;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EntitlementService
{
    public function __construct(private TimezoneService $timezones) {}

    /** @return array{type: EntitlementType, units: int} */
    public function requirement(SessionType $session): array
    {
        $type = $session->requiredEntitlementType;
        if ($session->funding_mode !== 'package' || ! $type || ! $type->active || (int) $session->required_entitlement_units < 1) {
            throw new InvalidArgumentException('This lesson is not configured for package entitlements. Contact your tutor.');
        }

        return ['type' => $type, 'units' => (int) $session->required_entitlement_units];
    }

    public function eligible(StudentPackage $package, ?string $timezone = null): bool
    {
        if ($package->status !== 'active' || $package->identity_state === 'legacy_unclassified') {
            return false;
        }
        if ($package->expiration_date === null) {
            return $package->validity_days === null;
        }

        return now('UTC')->lessThanOrEqualTo(CarbonImmutable::parse($package->expiration_date->toDateString(), $timezone ?? $this->timezones->getBusinessTimezone())->endOfDay()->utc());
    }

    /** @param Builder<StudentPackage> $query */
    public function scopeAvailablePackages(Builder $query): void
    {
        $query->where('status', 'active')->where('identity_state', '!=', 'legacy_unclassified')
            ->where(fn ($q) => $q->where(fn ($unlimited) => $unlimited->whereNull('expiration_date')->whereNull('validity_days'))->orWhereDate('expiration_date', '>=', now($this->timezones->getBusinessTimezone())->toDateString()))
            ->whereHas('entitlements', fn ($allocations) => $allocations->whereHas('type', fn ($types) => $types->where('active', true))->whereRaw('(select coalesce(sum(credit_change),0) from session_ledger_entries where student_package_entitlement_id = student_package_entitlements.id) > 0'));
    }

    public function selectAndLock(int $studentId, SessionType $session): ?StudentPackageEntitlement
    {
        if (DB::transactionLevel() < 1) {
            throw new InvalidArgumentException('Entitlement selection requires a transaction.');
        }
        $requirement = $this->requirement($session);
        $packages = StudentPackage::query()->where('student_id', $studentId)->where('status', 'active')->orderBy('id')->lockForUpdate()->get();
        $allocations = StudentPackageEntitlement::query()->whereIn('student_package_id', $packages->modelKeys())->where('student_id', $studentId)->orderBy('id')->lockForUpdate()->get();
        $entries = SessionLedgerEntry::query()->whereIn('student_package_id', $packages->modelKeys())->orderBy('id')->lockForUpdate()->get();
        $packages = $packages->sort(fn (StudentPackage $a, StudentPackage $b): int => (($a->expiration_date === null) <=> ($b->expiration_date === null)) ?: strcmp((string) $a->expiration_date?->toDateString(), (string) $b->expiration_date?->toDateString()) ?: strcmp((string) $a->created_at, (string) $b->created_at) ?: ($a->id <=> $b->id));
        foreach ($packages as $package) {
            if (! $this->eligible($package)) {
                continue;
            }
            foreach ($allocations->where('student_package_id', $package->id)->where('entitlement_type_id', $requirement['type']->id) as $allocation) {
                if ((int) $entries->where('student_package_entitlement_id', $allocation->id)->sum('credit_change') >= $requirement['units']) {
                    $allocation->setRelation('package', $package);
                    $allocation->setRelation('type', $requirement['type']);

                    return $allocation;
                }
            }
        }

        return null;
    }

    public function canFund(int $studentId, SessionType $session): bool
    {
        try {
            $requirement = $this->requirement($session);
        } catch (InvalidArgumentException) {
            return false;
        }
        $packages = StudentPackage::query()->where('student_id', $studentId);
        $this->scopeAvailablePackages($packages);

        return $packages->whereHas('entitlements', fn ($allocations) => $allocations
            ->where('entitlement_type_id', $requirement['type']->id)
            ->whereRaw('(select coalesce(sum(credit_change),0) from session_ledger_entries where student_package_entitlement_id = student_package_entitlements.id) >= ?', [$requirement['units']]))->exists();
    }

    public function lockAllocation(StudentPackage $package, ?int $allocationId): StudentPackageEntitlement
    {
        if ($allocationId === null || DB::transactionLevel() < 1) {
            throw new InvalidArgumentException('Choose an explicit entitlement allocation.');
        }
        $allocation = StudentPackageEntitlement::query()->whereKey($allocationId)->where('student_package_id', $package->id)->where('student_id', $package->student_id)->lockForUpdate()->first();
        if (! $allocation) {
            throw new InvalidArgumentException('The entitlement allocation does not belong to this package and student.');
        }

        return $allocation;
    }

    public function lockedBalance(StudentPackageEntitlement $allocation): int
    {
        $entries = SessionLedgerEntry::query()->where('student_package_entitlement_id', $allocation->id)->orderBy('id')->lockForUpdate()->get(['id', 'credit_change']);
        $balance = 0;
        foreach ($entries as $entry) {
            $balance += $entry->credit_change;
        }

        return $balance;
    }

    /** @return list<array{id: int|null, code: string, label: string, allocated: int, courtesy: int, consumed: int, restored: int, forfeited: int, remaining: int, available: int}> */
    public function projection(StudentPackage $package, ?string $timezone = null): array
    {
        $package->loadMissing(['entitlements.type', 'ledgerEntries']);
        $rows = [];
        foreach ($package->entitlements as $allocation) {
            $entries = $package->ledgerEntries->where('student_package_entitlement_id', $allocation->id);
            $remaining = (int) $entries->sum('credit_change');
            $rows[] = ['id' => (int) $allocation->id, 'code' => $allocation->type->code, 'label' => $allocation->type->label,
                'allocated' => (int) $entries->where('entry_type', 'package_grant')->sum('credit_change'),
                'courtesy' => (int) $entries->where('entry_type', 'courtesy_adjustment')->sum('credit_change'),
                'consumed' => -(int) $entries->where('entry_type', 'session_consumed')->sum('credit_change'),
                'restored' => (int) $entries->where('entry_type', 'cancellation_restore')->sum('credit_change'),
                'forfeited' => -(int) $entries->where('entry_type', 'expiration_forfeit')->sum('credit_change'),
                'remaining' => $remaining, 'available' => $allocation->type->active && $this->eligible($package, $timezone) ? max(0, $remaining) : 0];
        }
        $legacy = $package->ledgerEntries->whereNull('student_package_entitlement_id');
        if ($legacy->isNotEmpty()) {
            $rows[] = ['id' => null, 'code' => 'legacy_unclassified', 'label' => 'Unclassified historical units — review required',
                'allocated' => (int) $legacy->where('entry_type', 'package_grant')->sum('credit_change'),
                'courtesy' => (int) $legacy->where('entry_type', 'courtesy_adjustment')->sum('credit_change'),
                'consumed' => -(int) $legacy->where('entry_type', 'session_consumed')->sum('credit_change'),
                'restored' => (int) $legacy->where('entry_type', 'cancellation_restore')->sum('credit_change'),
                'forfeited' => -(int) $legacy->where('entry_type', 'expiration_forfeit')->sum('credit_change'),
                'remaining' => (int) $legacy->sum('credit_change'), 'available' => 0];
        }

        return $rows;
    }

    public function balanceText(StudentPackage $package, bool $available = false): string
    {
        return implode('; ', array_map(fn (array $row): string => $row['label'].': '.$row[$available ? 'available' : 'remaining'], $this->projection($package))) ?: 'No entitlements';
    }

    /** @return array<string, array{label: string, remaining: int, available: int}> */
    public function forStudent(int $studentId): array
    {
        $totals = [];
        foreach (StudentPackage::query()->where('student_id', $studentId)->with(['entitlements.type', 'ledgerEntries'])->get() as $package) {
            foreach ($this->projection($package) as $row) {
                $code = $row['code'];
                $totals[$code] ??= ['label' => $row['label'], 'remaining' => 0, 'available' => 0];
                $totals[$code]['remaining'] += $row['remaining'];
                $totals[$code]['available'] += $row['available'];
            }
        }

        return $totals;
    }
}
