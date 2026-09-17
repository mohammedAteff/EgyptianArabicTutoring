<?php

namespace App\Domains\Availability\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AvailabilityRule extends Model
{
    use HasFactory;

    protected $table = 'availability_rules';

    protected $fillable = [
        'weekday',
        'start_time',
        'end_time',
        'session_duration_minutes',
        'buffer_minutes',
        'min_notice_hours',
        'max_horizon_days',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'session_duration_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'min_notice_hours' => 'integer',
            'max_horizon_days' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    public function scopeForWeekday(Builder $query, int $weekday): Builder
    {
        return $query->where('weekday', $weekday)->where('enabled', true);
    }
}
