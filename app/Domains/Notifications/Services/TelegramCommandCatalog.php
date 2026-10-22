<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\Models\TelegramBot;

class TelegramCommandCatalog
{
    /** @return array<string, string> */
    public function all(): array
    {
        return ['today' => '📅 Today', 'tomorrow' => '🗓 Tomorrow', 'week' => '📆 Next 7 days', 'student' => '👤 Find student',
            'stats' => '📊 Analytics', 'games' => '🎮 Games', 'resources' => '📚 Resources', 'attention' => '🔔 Needs attention',
            'money' => '💳 Money', 'status' => '🛠 System status', 'brief' => '☀️ Morning briefing', 'wrap' => '🌙 Daily wrap-up'];
    }

    public function allows(TelegramBot $bot, string $command): bool
    {
        if (in_array($command, ['start', 'help'], true)) {
            return true;
        }

        return in_array($command, $bot->commands ?? [], true)
            || (in_array($command, ['games', 'resources'], true) && in_array('stats', $bot->commands ?? [], true))
            || ($command === 'week' && in_array('today', $bot->commands ?? [], true));
    }
}
