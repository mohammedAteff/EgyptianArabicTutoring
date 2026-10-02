<?php

namespace App\Domains\Booking\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'booking_events';

    protected $fillable = [
        'booking_id',
        'event_type', // 'created', 'rescheduled', 'cancelled', 'confirmed', 'completed', 'marked_no_show', 'restored'
        'performed_by', // 'customer', 'admin', 'system'
        'performed_by_id',
        'previous_data',
        'new_data',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_data' => 'array',
            'new_data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }
}
