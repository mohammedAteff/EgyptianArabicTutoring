<?php

namespace App\Domains\Students\Models;

use Database\Factories\PackageRenewalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class PackageRenewal extends Model
{
    /** @use HasFactory<PackageRenewalFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['student_id', 'previous_package_id', 'new_package_id', 'renewal_date', 'reason_code', 'notes', 'created_by', 'idempotency_key', 'fingerprint', 'created_at'];

    protected function casts(): array
    {
        return ['renewal_date' => 'immutable_date', 'created_at' => 'immutable_datetime'];
    }

    protected static function newFactory(): PackageRenewalFactory
    {
        return PackageRenewalFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (self $record): void {
            foreach (array_diff($record->getFillable(), []) as $field) {
                if ($record->isDirty($field)) {
                    throw new RuntimeException('Historical lifecycle terms cannot be changed.');
                }
            }
        });
        static::deleting(function (): void {
            throw new RuntimeException('Lifecycle history cannot be deleted.');
        });
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /** @return BelongsTo<StudentPackage, $this> */
    public function previousPackage(): BelongsTo
    {
        return $this->belongsTo(StudentPackage::class, 'previous_package_id');
    }

    /** @return BelongsTo<StudentPackage, $this> */
    public function newPackage(): BelongsTo
    {
        return $this->belongsTo(StudentPackage::class, 'new_package_id');
    }
}
