<?php

namespace App\Domains\Analytics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyCountryMetric extends Model
{
    use HasFactory;

    protected $table = 'daily_country_metrics';

    protected $fillable = [
        'metric_date',
        'country_code',
        'unique_visitors',
        'visitors',
        'sessions',
        'page_views',
        'bounced_sessions_count',
        'booking_cta_clicks',
        'bookings_completed',
        'resource_requests',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'unique_visitors' => 'integer',
            'visitors' => 'integer',
            'sessions' => 'integer',
            'page_views' => 'integer',
            'bounced_sessions_count' => 'integer',
            'booking_cta_clicks' => 'integer',
            'bookings_completed' => 'integer',
            'resource_requests' => 'integer',
        ];
    }
}
