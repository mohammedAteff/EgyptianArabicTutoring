<?php

namespace App\Domains\Marketing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Promotion extends Model
{
    use HasFactory;

    protected $table = 'promotions';

    protected $fillable = [
        'title',
        'headline',
        'subheadline',
        'cta_text',
        'cta_url',
        'banner_image_path',
        'is_active',
        'display_type',
        'starts_at',
        'ends_at',
        'has_countdown',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'has_countdown' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Determine if this promotion is active and within its schedule.
     */
    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = Carbon::now('UTC');

        if ($this->starts_at && $this->starts_at->isAfter($now)) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isBefore($now)) {
            return false;
        }

        return true;
    }

    /**
     * Calculate seconds until the next state transition (either starting or ending).
     */
    public function getSecondsUntilNextTransition(): ?int
    {
        $now = Carbon::now('UTC');

        if ($this->starts_at && $this->starts_at->isAfter($now)) {
            return max(1, $now->diffInSeconds($this->starts_at));
        }

        if ($this->ends_at && $this->ends_at->isAfter($now)) {
            return max(1, $now->diffInSeconds($this->ends_at));
        }

        return null;
    }
}
