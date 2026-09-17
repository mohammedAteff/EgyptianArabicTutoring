<?php

namespace App\Domains\Analytics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'analytics_events';

    protected $fillable = [
        'event_name',
        'visitor_token',
        'session_token',
        'page',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'metadata',
        'ip_hash',
        'is_bot',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_bot' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
