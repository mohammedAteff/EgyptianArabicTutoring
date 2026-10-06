<?php

namespace App\Domains\Booking\Models;

use App\Domains\Students\Models\EntitlementType;
use Database\Factories\SessionTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SessionType extends Model
{
    use HasFactory;

    protected static function newFactory(): SessionTypeFactory
    {
        return SessionTypeFactory::new();
    }

    protected $table = 'session_types';

    protected $fillable = [
        'funding_mode', 'required_entitlement_type_id', 'required_entitlement_units',
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

    /** @return BelongsTo<EntitlementType, $this> */
    public function requiredEntitlementType(): BelongsTo
    {
        return $this->belongsTo(EntitlementType::class, 'required_entitlement_type_id');
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
