<?php

namespace App\Domains\Analytics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Visitor extends Model
{
    use HasFactory;

    protected $table = 'visitors';

    protected $fillable = [
        'visitor_token',
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
    ];

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
