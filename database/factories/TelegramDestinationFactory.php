<?php

namespace Database\Factories;

use App\Domains\Notifications\Models\TelegramDestination;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TelegramDestination> */
class TelegramDestinationFactory extends Factory
{
    protected $model = TelegramDestination::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['telegram_bot_id' => TelegramBotFactory::new(), 'name' => 'QA private destination', 'chat_id' => fake()->unique()->numerify('#########'), 'enabled' => true, 'type' => 'private', 'detail_level' => 'summary', 'allowed_user_ids' => []];
    }
}
