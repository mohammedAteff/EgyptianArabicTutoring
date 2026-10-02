<?php

namespace App\Console\Commands;

use App\Domains\Notifications\Models\TelegramDelivery;
use App\Domains\Notifications\Services\TelegramDeliveryService;
use App\Domains\Notifications\Services\TelegramReadService;
use Illuminate\Console\Command;

class TelegramWatchdogCommand extends Command
{
    protected $signature = 'telegram:watchdog';

    protected $description = 'Check scheduler and queue independently; run from a separate host cron.';

    public function handle(): int
    {
        app(TelegramReadService::class)->watchdog();
        TelegramDelivery::where('status', 'pending')->whereIn('trigger', ['scheduler_stale', 'queue_stale'])->where('due_at', '<=', now('UTC'))->orderBy('id')->limit(20)->pluck('id')->each(fn ($id) => app(TelegramDeliveryService::class)->deliver((int) $id));
        $this->info('Independent heartbeat checks completed.');

        return self::SUCCESS;
    }
}
