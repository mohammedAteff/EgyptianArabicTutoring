<?php

declare(strict_types=1);

namespace App\Domains\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionReschedule extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'old_start_at_utc' => 'datetime',
        'new_start_at_utc' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SessionReschedule $model) {
            $model->created_at = $model->created_at ?? now();
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
