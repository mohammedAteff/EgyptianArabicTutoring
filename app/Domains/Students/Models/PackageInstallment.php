<?php

namespace App\Domains\Students\Models;

use Database\Factories\PackageInstallmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class PackageInstallment extends Model
{
    /** @use HasFactory<PackageInstallmentFactory> */
    use HasFactory;

    protected $fillable = ['student_package_id', 'sequence', 'expected_amount', 'due_date', 'status', 'schedule_key', 'fingerprint', 'created_by'];

    protected function casts(): array
    {
        return ['expected_amount' => 'decimal:2', 'due_date' => 'immutable_date', 'sequence' => 'integer'];
    }

    protected static function newFactory(): PackageInstallmentFactory
    {
        return PackageInstallmentFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (self $record): void {
            foreach (array_diff($record->getFillable(), ['status']) as $field) {
                if ($record->isDirty($field)) {
                    throw new RuntimeException('Historical lifecycle terms cannot be changed.');
                }
            }
        });
        static::deleting(function (): void {
            throw new RuntimeException('Lifecycle history cannot be deleted.');
        });
    }

    /** @return BelongsTo<StudentPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(StudentPackage::class, 'student_package_id');
    }
}
