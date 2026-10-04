<?php

namespace App\Domains\Students\Models;

use Database\Factories\Domains\Students\Models\StudentPackageEntitlementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class StudentPackageEntitlement extends Model
{
    /** @use HasFactory<StudentPackageEntitlementFactory> */
    use HasFactory;

    protected $fillable = ['student_id', 'student_package_id', 'entitlement_type_id', 'granted_quantity'];

    protected function casts(): array
    {
        return ['granted_quantity' => 'integer'];
    }

    protected static function newFactory(): StudentPackageEntitlementFactory
    {
        return StudentPackageEntitlementFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Allocation purchase snapshots are immutable.'));
        static::deleting(fn () => throw new RuntimeException('Allocations retain historical provenance.'));
    }

    /** @return BelongsTo<StudentPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(StudentPackage::class, 'student_package_id');
    }

    /** @return BelongsTo<EntitlementType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(EntitlementType::class, 'entitlement_type_id');
    }

    /** @return HasMany<SessionLedgerEntry, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SessionLedgerEntry::class, 'student_package_entitlement_id');
    }
}
