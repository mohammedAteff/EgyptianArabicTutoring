<?php

namespace App\Domains\Analytics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * @property-read Visitor|null $visitor
 */
class VisitorSession extends Model
{
    use HasFactory;

    protected $table = 'visitor_sessions';

    protected $fillable = [
        'session_token',
        'session_id',
        'visitor_id',
        'started_at',
        'last_activity_at',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'referrer',
        'landing_page',
        'is_bot',
        'detected_country_code',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $session): void {
            if (Schema::hasColumn('visitor_sessions', 'session_id')) {
                if (! $session->session_token && $session->session_id) {
                    $session->session_token = $session->session_id;
                } elseif (! $session->session_id && $session->session_token) {
                    $session->session_id = $session->session_token;
                }
            }

            $visitorId = $session->getAttribute('visitor_id');
            if ($visitorId !== null && is_string($visitorId) && ! is_numeric($visitorId)) {
                $visitorUuid = $visitorId;
                $visitor = Visitor::where('visitor_token', $visitorUuid)
                    ->orWhere('visitor_id', $visitorUuid)
                    ->first();
                if (! $visitor) {
                    $visitor = Visitor::create([
                        'visitor_token' => $visitorUuid,
                        'visitor_id' => $visitorUuid,
                        'first_seen_at' => now(),
                        'last_seen_at' => now(),
                    ]);
                }
                $session->visitor_id = $visitor->id;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'is_bot' => 'boolean',
        ];
    }

    /** @return BelongsTo<Visitor, $this> */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class, 'visitor_id');
    }
}
