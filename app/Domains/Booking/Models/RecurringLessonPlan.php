<?php

namespace App\Domains\Booking\Models;

use App\Domains\Students\Models\Student;
use Database\Factories\RecurringLessonPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class RecurringLessonPlan extends Model
{
    /** @use HasFactory<RecurringLessonPlanFactory> */
    use HasFactory;

    protected $fillable = ['student_id', 'session_type_id', 'cadence', 'start_date', 'end_date', 'occurrence_count', 'preferred_time', 'timezone', 'fold_policy', 'status', 'created_by', 'idempotency_key', 'fingerprint'];

    protected function casts(): array
    {
        return ['start_date' => 'immutable_date', 'end_date' => 'immutable_date', 'occurrence_count' => 'integer'];
    }

    protected static function newFactory(): RecurringLessonPlanFactory
    {
        return RecurringLessonPlanFactory::new();
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
