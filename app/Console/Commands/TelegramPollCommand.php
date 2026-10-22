<?php

namespace App\Console\Commands;

use App\Domains\Notifications\Services\TelegramCommandService;
use Illuminate\Console\Command;

class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll {--listen=45 : Bounded listening window in seconds; 0 checks once}';

    protected $description = 'Poll authorized bot commands using persistent offsets and locks.';

    public function handle(): int
    {
        $seconds = filter_var($this->option('listen'), FILTER_VALIDATE_INT);
        if ($seconds === false || $seconds < 0 || $seconds > 45) {
            $this->error('Choose a listening window between 0 and 45 seconds.');

            return self::FAILURE;
        }
        app(TelegramCommandService::class)->listen($seconds);
        $this->info('Telegram command polling completed.');

        return self::SUCCESS;
    }
}
