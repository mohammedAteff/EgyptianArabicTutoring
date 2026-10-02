<?php

namespace App\Jobs;

use App\Domains\Notifications\Services\TelegramDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverTelegramMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public int $deliveryId) {}

    public function handle(TelegramDeliveryService $service): void
    {
        $service->deliver($this->deliveryId);
    }
}
