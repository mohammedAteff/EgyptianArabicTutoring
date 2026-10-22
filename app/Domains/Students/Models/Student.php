<?php

namespace App\Domains\Students\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Forms\Models\FormSubmission;
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
 * @property Carbon|null $date_of_birth
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
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_pending_secret', 'two_factor_pending_session', 'two_factor_version', 'two_factor_last_used_step',
        'date_of_birth',
        'internal_notes',
        'possible_duplicate_of_student_id',
        'merged_into_student_id',
    ];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'suspended_at' => 'datetime', 'two_factor_secret' => 'encrypted', 'two_factor_pending_secret' => 'encrypted', 'two_factor_recovery_codes' => 'encrypted:array', 'two_factor_confirmed_at' => 'datetime', 'two_factor_pending_at' => 'datetime', 'two_factor_last_used_step' => 'integer'];
    }

    public function requiresTwoFactor(): bool
    {
        return $this->two_factor_secret !== null || $this->two_factor_confirmed_at !== null;
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

    /** @return HasMany<StudentEmail, $this> */
    public function verifiedEmails(): HasMany
    {
        return $this->hasMany(StudentEmail::class)->whereNotNull('verified_at');
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return HasMany<PaymentRecord, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(PaymentRecord::class);
    }

    /** @return HasMany<PaymentRefund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    /**
     * @return HasMany<StudentGuardian, $this>
     */
    public function guardians(): HasMany
    {
        return $this->hasMany(StudentGuardian::class);
    }

    /**
     * @return HasMany<StaffPinnedStudent, $this>
     */
    public function pinnedByStaff(): HasMany
    {
        return $this->hasMany(StaffPinnedStudent::class);
    }

    /**
     * @return HasMany<FormSubmission, $this>
     */
    public function formSubmissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /** @return BelongsTo<self, $this> */
    public function possibleDuplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'possible_duplicate_of_student_id');
    }

    /** @return BelongsTo<self, $this> */
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
