<?php

namespace App\Domains\Booking\Models;

use App\Domains\Students\Models\EntitlementType;
use Database\Factories\SessionTypeFactory;
use Illuminate\Database\Eloquent\Builder;
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
        'public_bookable',
        'student_discoverable',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'public_bookable' => 'boolean',
            'student_discoverable' => 'boolean',
            'duration_minutes' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    public function scopePublicBookable(Builder $query): Builder
    {
        return $query->where('active', true)->where('public_bookable', true);
    }

    public function scopeStudentDiscoverable(Builder $query): Builder
    {
        return $query->where('active', true)->where('student_discoverable', true);
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
