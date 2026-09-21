<?php

namespace App\Domains\Booking\Models;

use App\Domains\Contacts\Models\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bookings';

    protected $fillable = [
        'contact_id',
        'visitor_token',
        'session_type_id',
        'start_at_utc',
        'end_at_utc',
        'business_timezone',
        'customer_timezone',
        'business_local_date_at_booking',
        'business_local_start_time_at_booking',
        'business_local_end_time_at_booking',
        'customer_local_date_at_booking',
        'customer_local_start_time_at_booking',
        'customer_local_end_time_at_booking',
        'business_utc_offset_at_booking',
        'customer_utc_offset_at_booking',
        'status', // 'confirmed', 'cancelled', 'completed', 'no_show', 'pending'
        'idempotency_key',
        'confirmation_token',
        'notes',
        'source',
        'medium',
        'campaign',
        'content',
        'term',
        'referrer',
        'touch_at',
        'cancelled_at',
        'cancellation_reason',
        'completed_at',
        'detected_country_code',
    ];

    protected function casts(): array
    {
        return [
            'start_at_utc' => 'datetime',
            'end_at_utc' => 'datetime',
            'touch_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'business_local_date_at_booking' => 'date',
            'customer_local_date_at_booking' => 'date',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function sessionType(): BelongsTo
    {
        return $this->belongsTo(SessionType::class, 'session_type_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(BookingEvent::class, 'booking_id');
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled' || $this->cancelled_at !== null;
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isNoShow(): bool
    {
        return $this->status === 'no_show';
    }
}
