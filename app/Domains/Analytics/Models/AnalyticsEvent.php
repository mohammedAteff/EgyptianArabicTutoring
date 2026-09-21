<?php

namespace App\Domains\Analytics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AnalyticsEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'analytics_events';

    protected $fillable = [
        'event_name',
        'visitor_token',
        'visitor_id',
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

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            if (Schema::hasColumn('analytics_events', 'visitor_id')) {
                if (! $event->visitor_token && $event->visitor_id) {
                    $event->visitor_token = $event->visitor_id;
                } elseif (! $event->visitor_id && $event->visitor_token) {
                    $event->visitor_id = $event->visitor_token;
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_bot' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
