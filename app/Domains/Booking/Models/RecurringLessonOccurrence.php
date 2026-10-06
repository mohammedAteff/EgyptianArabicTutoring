<?php

namespace App\Domains\Booking\Models;

use Database\Factories\RecurringLessonOccurrenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class RecurringLessonOccurrence extends Model
{
    /** @use HasFactory<RecurringLessonOccurrenceFactory> */
    use HasFactory;

    protected $fillable = ['recurring_lesson_plan_id', 'sequence', 'local_date', 'booking_id', 'status', 'reason_code'];

    protected function casts(): array
    {
        return ['local_date' => 'immutable_date', 'sequence' => 'integer'];
    }

    protected static function newFactory(): RecurringLessonOccurrenceFactory
    {
        return RecurringLessonOccurrenceFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (self $record): void {
            foreach (array_diff($record->getFillable(), ['booking_id', 'status', 'reason_code']) as $field) {
                if ($record->isDirty($field)) {
                    throw new RuntimeException('Historical lifecycle terms cannot be changed.');
                }
            }
        });
        static::deleting(function (): void {
            throw new RuntimeException('Lifecycle history cannot be deleted.');
        });
    }

    /** @return BelongsTo<RecurringLessonPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(RecurringLessonPlan::class, 'recurring_lesson_plan_id');
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }
}
