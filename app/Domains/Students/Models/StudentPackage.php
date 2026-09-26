<?php

namespace App\Domains\Students\Models;

use Database\Factories\StudentPackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class StudentPackage extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'package_name', 'original_price', 'discount_amount', 'final_price', 'currency', 'total_sessions_allocated', 'expiration_date', 'status'];

    protected function casts(): array
    {
        return ['expiration_date' => 'date', 'original_price' => 'decimal:2', 'discount_amount' => 'decimal:2', 'final_price' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $package): void {
            foreach (['student_id', 'original_price', 'discount_amount', 'final_price', 'currency', 'total_sessions_allocated'] as $field) {
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

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SessionLedgerEntry::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentRecord::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }
}
