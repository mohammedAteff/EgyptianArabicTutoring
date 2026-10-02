<?php

namespace App\Domains\Notifications\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramRuleState extends Model
{
    protected $fillable = ['telegram_rule_id', 'entity', 'last_emitted_at'];

    protected $hidden = [];

    protected function casts(): array
    {
        return ['last_emitted_at' => 'datetime'];
    }
}
