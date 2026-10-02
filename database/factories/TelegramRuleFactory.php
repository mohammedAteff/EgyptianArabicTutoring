<?php

namespace Database\Factories;

use App\Domains\Notifications\Models\TelegramRule;
use App\Domains\Notifications\Services\TelegramRuleCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TelegramRule> */
class TelegramRuleFactory extends Factory
{
    protected $model = TelegramRule::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['telegram_bot_id' => TelegramBotFactory::new(), 'name' => 'QA booking alert', 'trigger' => 'booking_created', 'template' => app(TelegramRuleCatalog::class)->get('booking_created')['template'], 'enabled' => true];
    }
}
