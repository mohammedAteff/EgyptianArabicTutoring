<?php

namespace App\Domains\Notifications\Models;

use Database\Factories\TelegramRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TelegramRule extends Model
{
    use HasFactory;

    protected static function newFactory(): TelegramRuleFactory
    {
        return TelegramRuleFactory::new();
    }

    protected $fillable = ['telegram_bot_id', 'name', 'trigger', 'enabled', 'mode', 'priority', 'minutes', 'threshold', 'window_minutes', 'cooldown_minutes', 'send_time', 'quiet_start', 'quiet_end', 'sections', 'template', 'conditions', 'legacy_reminder'];

    protected $hidden = [];

    protected function casts(): array
    {
        return ['legacy_reminder' => 'boolean', 'enabled' => 'boolean', 'conditions' => 'array', 'sections' => 'array', 'minutes' => 'integer', 'threshold' => 'integer', 'window_minutes' => 'integer', 'cooldown_minutes' => 'integer'];
    }

    /** @return BelongsTo<TelegramBot, $this> */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(TelegramBot::class, 'telegram_bot_id');
    }

    /** @return BelongsToMany<TelegramDestination, $this> */
    public function destinations(): BelongsToMany
    {
        return $this->belongsToMany(TelegramDestination::class, 'telegram_destination_rule');
    }
}
