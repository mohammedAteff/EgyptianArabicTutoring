<?php

namespace App\Domains\Analytics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class Visitor extends Model
{
    use HasFactory;

    protected $table = 'visitors';

    protected $fillable = [
        'visitor_token',
        'visitor_id',
        'first_seen_at',
        'last_seen_at',
        'device_type',
        'user_agent',
        'is_bot',
        'acquisition_source',
        'acquisition_medium',
        'acquisition_campaign',
        'acquisition_content',
        'acquisition_term',
        'acquisition_touch_at',
        'detected_country_code',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $visitor): void {
            if (Schema::hasColumn('visitors', 'visitor_id')) {
                if (! $visitor->visitor_token && $visitor->visitor_id) {
                    $visitor->visitor_token = $visitor->visitor_id;
                } elseif (! $visitor->visitor_id && $visitor->visitor_token) {
                    $visitor->visitor_id = $visitor->visitor_token;
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'acquisition_touch_at' => 'datetime',
            'is_bot' => 'boolean',
        ];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(VisitorSession::class, 'visitor_id');
    }

    public function funnelProgression(): HasOne
    {
        return $this->hasOne(VisitorFunnelProgression::class, 'visitor_id');
    }
}
