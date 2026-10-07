<?php

namespace Database\Factories;

use App\Domains\Lms\Models\VideoWebhookReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<VideoWebhookReceipt> */
class VideoWebhookReceiptFactory extends Factory
{
    protected $model = VideoWebhookReceipt::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['video_asset_id' => VideoAssetFactory::new(), 'receipt_hash' => hash('sha256', Str::random(64)), 'reported_status' => 3, 'received_at' => now('UTC')];
    }
}
