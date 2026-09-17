<?php

namespace App\Domains\Booking\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SessionType extends Model
{
    use HasFactory;

    protected $table = 'session_types';

    protected $fillable = [
        'title',
        'slug',
        'description',
        'duration_minutes',
        'price',
        'currency',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'duration_minutes' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    public function getNameAttribute(): string
    {
        return $this->title ?? '';
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'session_type_id');
    }

    public function holds(): HasMany
    {
        return $this->hasMany(BookingHold::class, 'session_type_id');
    }
}
