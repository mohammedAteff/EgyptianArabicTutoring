<?php

namespace App\Domains\Notifications\Models;

use Database\Factories\TelegramBotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramBot extends Model
{
    use HasFactory;

    protected static function newFactory(): TelegramBotFactory
    {
        return TelegramBotFactory::new();
    }

    protected $fillable = ['name', 'token', 'enabled', 'commands_enabled', 'commands', 'update_offset', 'last_success_at', 'last_failure_at', 'failure_code'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'enabled' => 'boolean', 'commands_enabled' => 'boolean', 'commands' => 'array', 'last_success_at' => 'datetime', 'last_failure_at' => 'datetime', 'update_offset' => 'integer'];
    }

    /** @return HasMany<TelegramDestination, $this> */
    public function destinations(): HasMany
    {
        return $this->hasMany(TelegramDestination::class);
    }

    /** @return HasMany<TelegramRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(TelegramRule::class);
    }
}
