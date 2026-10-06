<?php

namespace App\Domains\Booking\Models;

use App\Domains\Students\Models\Student;
use Database\Factories\BookingWaitlistFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class BookingWaitlist extends Model
{
    /** @use HasFactory<BookingWaitlistFactory> */
    use HasFactory;

    protected $fillable = ['student_id', 'session_type_id', 'date_from', 'date_to', 'timezone', 'status', 'reason_code', 'notes', 'idempotency_key', 'fingerprint'];

    protected function casts(): array
    {
        return ['date_from' => 'immutable_date', 'date_to' => 'immutable_date'];
    }

    protected static function newFactory(): BookingWaitlistFactory
    {
        return BookingWaitlistFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (self $record): void {
            foreach (array_diff($record->getFillable(), ['status', 'reason_code', 'notes']) as $field) {
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

    /** @return BelongsTo<SessionType, $this> */
    public function sessionType(): BelongsTo
    {
        return $this->belongsTo(SessionType::class, 'session_type_id');
    }
}
