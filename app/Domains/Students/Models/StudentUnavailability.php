<?php

namespace App\Domains\Students\Models;

use Database\Factories\StudentUnavailabilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class StudentUnavailability extends Model
{
    /** @use HasFactory<StudentUnavailabilityFactory> */
    use HasFactory;

    protected $fillable = ['student_id', 'starts_at_utc', 'ends_at_utc', 'timezone', 'reason_code', 'notes', 'status', 'idempotency_key', 'fingerprint'];

    protected function casts(): array
    {
        return ['starts_at_utc' => 'immutable_datetime', 'ends_at_utc' => 'immutable_datetime'];
    }

    protected static function newFactory(): StudentUnavailabilityFactory
    {
        return StudentUnavailabilityFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (self $record): void {
            foreach (array_diff($record->getFillable(), ['status', 'notes']) as $field) {
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
}
