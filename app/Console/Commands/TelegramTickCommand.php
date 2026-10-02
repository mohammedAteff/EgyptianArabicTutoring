<?php

namespace App\Console\Commands;

use App\Domains\CMS\Models\Setting;
use App\Domains\Notifications\Models\TelegramDelivery;
use App\Domains\Notifications\Services\TelegramLegacyImporter;
use App\Domains\Notifications\Services\TelegramReadService;
use App\Jobs\DeliverTelegramMessage;
use Illuminate\Console\Command;

class TelegramTickCommand extends Command
{
    protected $signature = 'telegram:tick';

    protected $description = 'Scan configured Telegram alert rules and dispatch due deliveries.';

    public function handle(): int
    {
        app(TelegramLegacyImporter::class)->import();
        app(TelegramReadService::class)->tick();
        TelegramDelivery::where('status', 'sending')->where('attempted_at', '<', now('UTC')->subMinutes(5))->update(['status' => 'uncertain', 'failure_code' => 'worker_interrupted']);
        TelegramDelivery::where('status', 'pending')->where('due_at', '<=', now('UTC'))->orderBy('id')->limit(100)->pluck('id')->each(fn ($id) => DeliverTelegramMessage::dispatch((int) $id));
        Setting::set('telegram.last_tick_at', now('UTC')->toIso8601String(), 'telegram');
        $this->info('Telegram monitor completed.');

        return self::SUCCESS;
    }
}
