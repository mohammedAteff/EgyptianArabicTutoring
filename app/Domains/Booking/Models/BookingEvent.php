<?php

namespace App\Domains\Booking\Models;

use App\Domains\Timezone\Services\TimezoneDisplayService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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

    /** @return list<array{label: string, value: string}> */
    public function displayDetails(): array
    {
        $labels = ['status' => 'Status', 'start_at_utc' => 'Lesson starts', 'end_at_utc' => 'Lesson ends',
            'completed_at' => 'Completed at', 'cancelled_at' => 'Cancelled at', 'customer_timezone' => 'Student timezone',
            'source' => 'Booked through', 'reason' => 'Reason', 'cancellation_reason' => 'Cancellation reason',
            'notes' => 'Notes', 'admin_reconfirmation_needed' => 'Reconfirmation needed'];
        $details = [];
        foreach ($labels as $key => $label) {
            $value = $this->new_data[$key] ?? null;
            if ($value === null || $value === '' || ! is_scalar($value)) {
                continue;
            }
            if (in_array($key, ['start_at_utc', 'end_at_utc', 'completed_at', 'cancelled_at'], true)) {
                try {
                    $value = app(TimezoneDisplayService::class)->administratorDateTime(CarbonImmutable::parse((string) $value, 'UTC'));
                } catch (\Throwable) {
                    continue;
                }
            } elseif (is_bool($value)) {
                $value = $value ? 'Yes' : 'No';
            } elseif (in_array($key, ['status', 'source'], true)) {
                $value = Str::headline((string) $value);
            }
            $details[] = ['label' => $label, 'value' => (string) $value];
        }

        return $details;
    }
}
