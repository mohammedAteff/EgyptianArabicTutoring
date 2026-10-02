<?php

namespace App\Domains\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramDelivery extends Model
{
    protected $fillable = ['telegram_bot_id', 'telegram_destination_id', 'telegram_rule_id', 'dedupe_key', 'trigger', 'payload', 'status', 'due_at', 'attempted_at', 'attempts', 'next_part', 'message_ids', 'failure_code'];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'due_at' => 'datetime', 'attempted_at' => 'datetime', 'message_ids' => 'array', 'attempts' => 'integer', 'next_part' => 'integer'];
    }

    /** @return BelongsTo<TelegramBot, $this> */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(TelegramBot::class, 'telegram_bot_id');
    }

    /** @return BelongsTo<TelegramDestination, $this> */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(TelegramDestination::class, 'telegram_destination_id');
    }

    /** @return BelongsTo<TelegramRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(TelegramRule::class, 'telegram_rule_id');
    }
}
