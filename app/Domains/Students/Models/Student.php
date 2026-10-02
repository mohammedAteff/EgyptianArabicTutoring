<?php

namespace App\Domains\Students\Models;

use App\Domains\Booking\Models\Booking;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Student extends Authenticatable
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'name_normalized',
        'email',
        'email_normalized',
        'phone',
        'phone_normalized',
        'date_of_birth',
        'preferred_timezone',
        'identity_status',
        'possible_duplicate_of_student_id',
        'merged_into_student_id',
        'internal_notes',
    ];

    protected $hidden = [
        'date_of_birth',
        'internal_notes',
        'possible_duplicate_of_student_id',
        'merged_into_student_id',
    ];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    protected static function newFactory(): StudentFactory
    {
        return StudentFactory::new();
    }

    public function getNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    /**
     * @return HasMany<StudentPackage, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(StudentPackage::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function possibleDuplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'possible_duplicate_of_student_id');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_student_id');
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('identity_status', 'verified');
    }
}
