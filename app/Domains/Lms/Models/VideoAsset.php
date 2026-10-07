<?php

namespace App\Domains\Lms\Models;

use Database\Factories\VideoAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $course_id
 * @property string $provider
 * @property int|null $provider_connection_id
 * @property int|null $library_id
 * @property string|null $provider_video_id
 * @property string|null $external_url
 * @property string $label
 * @property string $status
 * @property int|null $provider_status
 * @property int|null $duration_seconds
 * @property bool $has_mp4_fallback
 * @property string $upload_request_key
 * @property int $lock_version
 */
class VideoAsset extends LmsModel
{
    /** @use HasFactory<VideoAssetFactory> */
    use HasFactory;

    protected $table = 'lms_video_assets';

    protected $guarded = ['*'];

    protected $hidden = ['provider_connection_id', 'library_id', 'provider_video_id', 'external_url', 'upload_request_key'];

    protected static function newFactory(): VideoAssetFactory
    {
        return VideoAssetFactory::new();
    }

    protected function casts(): array
    {
        return ['provider_status' => 'integer', 'duration_seconds' => 'integer', 'has_mp4_fallback' => 'boolean', 'reconciled_at' => 'datetime', 'lock_version' => 'integer'];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /** @return BelongsTo<VideoProviderConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(VideoProviderConnection::class, 'provider_connection_id');
    }

    public function usableFor(Course $target): bool
    {
        $source = $this->course;

        return $this->status === 'ready' && $source !== null
            && ($this->provider !== 'bunny' || ($this->provider_video_id !== null && $this->duration_seconds > 0 && ! $this->has_mp4_fallback))
            && ($source->kind === 'catalog' || ($target->kind === 'private' && (int) $source->owner_student_id === (int) $target->owner_student_id));
    }
}
