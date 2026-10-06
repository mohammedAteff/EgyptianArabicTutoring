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
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $suspended_at
 * @property int $bookings_count
 */
class Student extends Authenticatable
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'operational_status', 'operational_status_changed_at', 'operational_reason_code',
        'preferred_meeting_provider_id', 'suspended_at', 'suspended_by', 'suspension_reason',
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
        return ['date_of_birth' => 'date', 'suspended_at' => 'datetime'];
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

    public function verifiedEmails(): HasMany
    {
        return $this->hasMany(StudentEmail::class);
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

    public function scopeTutoringRoster(Builder $query): Builder
    {
        return $query->whereNull('merged_into_student_id')->where('identity_status', '!=', 'merged')
            ->where(function (Builder $query): void {
                $query->whereHas('packages', fn (Builder $packages) => $packages->where('total_sessions_allocated', '>', 0))
                    ->orWhereHas('bookings', fn (Builder $bookings) => $bookings->whereIn('status', ['confirmed', 'completed', 'no_show']));
            });
    }
}
