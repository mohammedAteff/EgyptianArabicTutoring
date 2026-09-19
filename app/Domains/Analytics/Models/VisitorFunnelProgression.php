<?php

namespace App\Domains\Analytics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitorFunnelProgression extends Model
{
    use HasFactory;

    protected $table = 'visitor_funnel_progressions';

    protected $fillable = [
        'visitor_id',
        'cohort_date',
        'visitor_at',
        'booking_cta_observed_at',
        'booking_cta_qualified_at',
        'booking_cta_provenance',
        'booking_started_observed_at',
        'booking_started_qualified_at',
        'booking_started_provenance',
        'slot_held_observed_at',
        'slot_held_qualified_at',
        'slot_held_provenance',
        'booking_completed_observed_at',
        'booking_completed_qualified_at',
        'booking_completed_provenance',
        'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'cohort_date' => 'date',
            'visitor_at' => 'datetime',
            'booking_cta_observed_at' => 'datetime',
            'booking_cta_qualified_at' => 'datetime',
            'booking_started_observed_at' => 'datetime',
            'booking_started_qualified_at' => 'datetime',
            'slot_held_observed_at' => 'datetime',
            'slot_held_qualified_at' => 'datetime',
            'booking_completed_observed_at' => 'datetime',
            'booking_completed_qualified_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }
}
