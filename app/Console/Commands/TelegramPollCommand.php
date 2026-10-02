<?php

namespace App\Console\Commands;

use App\Domains\Notifications\Services\TelegramCommandService;
use Illuminate\Console\Command;

class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll';

    protected $description = 'Poll authorized bot commands using persistent offsets and locks.';

    public function handle(): int
    {
        app(TelegramCommandService::class)->poll();
        $this->info('Telegram command polling completed.');

        return self::SUCCESS;
    }
}
