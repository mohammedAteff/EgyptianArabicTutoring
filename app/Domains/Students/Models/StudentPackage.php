<?php

namespace App\Domains\Students\Models;

use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Database\Factories\StudentPackageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class StudentPackage extends Model
{
    use HasFactory;

    protected $fillable = ['offering_key', 'identity_state', 'validity_days', 'purchase_fingerprint', 'student_id', 'package_name', 'original_price', 'discount_amount', 'final_price', 'currency', 'total_sessions_allocated', 'expiration_date', 'validity_started_at', 'expires_at', 'status'];

    protected function casts(): array
    {
        return ['validity_started_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'expiration_date' => 'date', 'original_price' => 'decimal:2', 'discount_amount' => 'decimal:2', 'final_price' => 'decimal:2'];
    }

    public function expiryDeadline(?string $timezone = null): ?CarbonImmutable
    {
        return $this->expires_at ?? ($this->expiration_date
            ? CarbonImmutable::parse($this->expiration_date->toDateString(), $timezone ?? app(TimezoneService::class)->getBusinessTimezone())->endOfDay()->utc()
            : null);
    }

    public function expiryLabel(): string
    {
        if ($this->expires_at) {
            $timezone = app(TimezoneService::class)->getBusinessTimezone();

            return $this->expires_at->setTimezone($timezone)->format('Y-m-d H:i').' '.$timezone;
        }

        return $this->expiration_date?->toDateString() ?? ($this->validity_days !== null ? 'No expiry date recorded' : 'No expiry');
    }

    public function isExpired(?string $timezone = null): bool
    {
        $deadline = $this->expiryDeadline($timezone);

        return $deadline !== null && ($this->expires_at ? now('UTC')->greaterThanOrEqualTo($deadline) : now('UTC')->greaterThan($deadline));
    }

    /** @param Builder<StudentPackage> $query */
    public function scopeWithinValidity(Builder $query): Builder
    {
        return $query->where(function ($validity): void {
            $validity->where('expires_at', '>', now('UTC'))->orWhere(function ($legacy): void {
                $legacy->whereNull('expires_at')->where(function ($dates): void {
                    $dates->where(fn ($unlimited) => $unlimited->whereNull('expiration_date')->whereNull('validity_days'))
                        ->orWhereDate('expiration_date', '>=', now(app(TimezoneService::class)->getBusinessTimezone())->toDateString());
                });
            });
        });
    }

    /** @param Builder<StudentPackage> $query */
    public function scopeExpiringBetween(Builder $query, CarbonImmutable $from, CarbonImmutable $through): Builder
    {
        $timezone = app(TimezoneService::class)->getBusinessTimezone();

        return $query->where(function ($dates) use ($from, $through, $timezone): void {
            $dates->whereBetween('expires_at', [$from->utc(), $through->utc()])
                ->orWhere(fn ($legacy) => $legacy->whereNull('expires_at')->whereBetween('expiration_date', [$from->setTimezone($timezone)->toDateString(), $through->setTimezone($timezone)->toDateString()]));
        });
    }

    /** @return HasMany<PackageInstallment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(PackageInstallment::class)->orderBy('due_date')->orderBy('sequence');
    }

    /** @return HasMany<PackageRenewal, $this> */
    public function renewals(): HasMany
    {
        return $this->hasMany(PackageRenewal::class, 'previous_package_id');
    }

    protected static function booted(): void
    {
        static::updating(function (self $package): void {
            if ($package->getRawOriginal('validity_started_at') !== null && $package->isDirty('validity_started_at')) {
                throw new RuntimeException('An activated package keeps its original validity start.');
            }
            if ($package->getRawOriginal('expires_at') !== null && $package->isDirty('expires_at') && ($package->expires_at === null || $package->expires_at->lessThan(CarbonImmutable::parse($package->getRawOriginal('expires_at'), 'UTC')))) {
                throw new RuntimeException('An activated package deadline cannot be shortened.');
            }
            foreach (['package_name', 'offering_key', 'identity_state', 'validity_days', 'purchase_fingerprint', 'student_id', 'original_price', 'discount_amount', 'final_price', 'currency', 'total_sessions_allocated'] as $field) {
                if ($package->isDirty($field)) {
                    throw new RuntimeException('Historical package terms cannot be changed.');
                }
            }
        });
    }

    protected static function newFactory(): StudentPackageFactory
    {
        return StudentPackageFactory::new();
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return HasMany<StudentPackageEntitlement, $this> */
    public function entitlements(): HasMany
    {
        return $this->hasMany(StudentPackageEntitlement::class);
    }

    /** @return HasMany<SessionLedgerEntry, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SessionLedgerEntry::class);
    }

    /**
     * @return HasMany<PaymentRecord, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(PaymentRecord::class);
    }

    /**
     * @return HasMany<PaymentRefund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }
}
