<?php

namespace App\Domains\Analytics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingTouch extends Model
{
    use HasFactory;

    protected $table = 'marketing_touches';

    protected $fillable = [
        'visitor_id',
        'visitor_token',
        'session_token',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'referrer',
        'touch_at',
    ];

    protected function casts(): array
    {
        return [
            'touch_at' => 'datetime',
        ];
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }
}
