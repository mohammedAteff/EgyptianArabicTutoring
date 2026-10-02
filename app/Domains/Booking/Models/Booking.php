<?php

namespace App\Domains\Booking\Models;

use App\Domains\CMS\Models\Setting;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Students\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bookings';

    protected $fillable = [
        'contact_id',
        'student_id',
        'admin_reconfirmation_needed',
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
            'admin_reconfirmation_needed' => 'boolean',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'business_local_date_at_booking' => 'date',
            'customer_local_date_at_booking' => 'date',
        ];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<SessionType, $this> */
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

    public function reschedules(): HasMany
    {
        return $this->hasMany(SessionReschedule::class, 'booking_id');
    }

    public function studentStatusLabel(): string
    {
        $hasReschedules = (bool) ($this->reschedules_exists ?? (
            $this->relationLoaded('reschedules') ? $this->reschedules->isNotEmpty() : false
        ));

        return match ($this->status) {
            'completed' => 'Session Delivered',
            'no_show', 'no-show' => 'Session Forfeited',
            'cancelled' => 'Booking Canceled',
            'confirmed' => $hasReschedules ? 'Session Rescheduled' : 'Confirmed',
            'pending' => 'Pending Confirmation',
            'held' => 'Reservation Held',
            default => (function () {
                Log::warning("Unrecognized booking status encountered: [{$this->status}]");

                return 'Unknown Status';
            })(),
        };
    }

    public function getCustomerStartAttribute(): Carbon
    {
        return $this->start_at_utc->copy()->setTimezone($this->customer_timezone);
    }

    public function getBusinessStartAttribute(): Carbon
    {
        $activeBusinessTz = Cache::remember('active_business_tz', 3600, function () {
            return Setting::where('key', 'business_timezone')->value('value') ?? 'Africa/Cairo';
        });

        return $this->start_at_utc->copy()->setTimezone($activeBusinessTz);
    }
}
