<?php

namespace Database\Factories;

use App\Domains\Notifications\Models\TelegramBot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TelegramBot> */
class TelegramBotFactory extends Factory
{
    protected $model = TelegramBot::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['name' => 'QA notification bot', 'token' => '123456:ABCDEFGHIJKLMNOPQRSTUVWXYZ_fake', 'enabled' => true];
    }
}
