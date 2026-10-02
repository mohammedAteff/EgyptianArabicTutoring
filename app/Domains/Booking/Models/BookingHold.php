<?php

namespace App\Domains\Booking\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingHold extends Model
{
    use HasFactory;

    protected $table = 'booking_holds';

    protected $hidden = ['lead_details'];

    protected $fillable = [
        'visitor_token',
        'session_token',
        'hold_token',
        'session_type_id',
        'slot_start_utc',
        'slot_end_utc',
        'expires_at',
        'released_at',
        'status', // 'active', 'expired', 'converted', 'released'
    ];

    protected function casts(): array
    {
        return [
            'lead_details' => 'encrypted:array',
            'slot_start_utc' => 'datetime',
            'slot_end_utc' => 'datetime',
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function sessionType(): BelongsTo
    {
        return $this->belongsTo(SessionType::class, 'session_type_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || ($this->status === 'active' && $this->expires_at->isPast());
    }

    public function release(): void
    {
        $this->lead_details = null;
        $this->update([
            'status' => 'released',
            'released_at' => now(),
        ]);
    }

    public function convert(): void
    {
        $this->lead_details = null;
        $this->update([
            'status' => 'converted',
        ]);
    }
}
