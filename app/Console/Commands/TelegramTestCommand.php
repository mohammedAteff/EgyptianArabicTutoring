<?php

namespace App\Console\Commands;

use App\Domains\Notifications\Models\TelegramDestination;
use App\Domains\Notifications\Services\TelegramDeliveryService;
use Illuminate\Console\Command;

class TelegramTestCommand extends Command
{
    protected $signature = 'telegram:test {destination : Existing destination ID}';

    protected $description = 'Send a labeled connectivity test containing no student or customer data';

    public function handle(TelegramDeliveryService $deliveryService): int
    {
        $destination = TelegramDestination::find($this->argument('destination'));
        if (! $destination) {
            $this->error('Destination does not exist.');

            return self::FAILURE;
        }
        $delivery = $deliveryService->test($destination);
        $this->info('Connectivity test delivery #'.$delivery->id.': '.$delivery->status);

        return $delivery->status === 'sent' ? self::SUCCESS : self::FAILURE;
    }
}
