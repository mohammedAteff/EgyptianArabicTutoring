<?php

namespace App\Domains\Notifications\Models;

use Database\Factories\TelegramDestinationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramDestination extends Model
{
    use HasFactory;

    protected static function newFactory(): TelegramDestinationFactory
    {
        return TelegramDestinationFactory::new();
    }

    protected $fillable = ['telegram_bot_id', 'name', 'chat_id', 'type', 'detail_level', 'enabled', 'allowed_user_ids'];

    protected $hidden = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'allowed_user_ids' => 'array'];
    }

    /** @return BelongsTo<TelegramBot, $this> */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(TelegramBot::class, 'telegram_bot_id');
    }
}
