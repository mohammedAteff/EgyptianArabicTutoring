<?php

namespace App\Domains\Booking\Models;

use Database\Factories\BookingPolicyDecisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class BookingPolicyDecision extends Model
{
    /** @use HasFactory<BookingPolicyDecisionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['booking_id', 'action', 'outcome', 'reason_code', 'policy_snapshot', 'actor_type', 'actor_id', 'created_at'];

    protected function casts(): array
    {
        return ['policy_snapshot' => 'array', 'created_at' => 'immutable_datetime'];
    }

    protected static function newFactory(): BookingPolicyDecisionFactory
    {
        return BookingPolicyDecisionFactory::new();
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

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }
}
