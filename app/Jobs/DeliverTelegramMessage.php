<?php

namespace App\Jobs;

use App\Domains\CMS\Models\Setting;
use App\Domains\Notifications\Models\TelegramDelivery;
use App\Domains\Notifications\Services\TelegramDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverTelegramMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public ?string $expectedDedupeKey = null;

    public ?string $expectedHistoryGeneration = null;

    public function __construct(public int $deliveryId)
    {
        $this->expectedDedupeKey = TelegramDelivery::query()->whereKey($deliveryId)->value('dedupe_key');
        $this->expectedHistoryGeneration = Setting::get('telegram.delivery_history_generation');
    }

    public function handle(TelegramDeliveryService $service): void
    {
        if ($this->expectedHistoryGeneration !== Setting::get('telegram.delivery_history_generation')) {
            return;
        }
        $service->deliver($this->deliveryId, expectedDedupeKey: $this->expectedDedupeKey);
    }
}
