<?php

namespace App\Domains\Lms\Models;

use Database\Factories\VideoWebhookReceiptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $video_asset_id
 * @property string $receipt_hash
 * @property int $reported_status
 * @property Carbon $received_at
 */
class VideoWebhookReceipt extends LmsModel
{
    /** @use HasFactory<VideoWebhookReceiptFactory> */
    use HasFactory;

    protected $table = 'lms_video_webhook_receipts';

    protected $guarded = ['*'];

    protected $hidden = ['receipt_hash'];

    protected static function newFactory(): VideoWebhookReceiptFactory
    {
        return VideoWebhookReceiptFactory::new();
    }

    protected function casts(): array
    {
        return ['reported_status' => 'integer', 'received_at' => 'datetime'];
    }

    /** @return BelongsTo<VideoAsset, $this> */
    public function video(): BelongsTo
    {
        return $this->belongsTo(VideoAsset::class, 'video_asset_id');
    }
}
