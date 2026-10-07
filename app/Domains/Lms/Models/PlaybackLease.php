<?php

namespace App\Domains\Lms\Models;

use App\Domains\Students\Models\Student;
use Database\Factories\PlaybackLeaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $student_id
 * @property int $device_id
 * @property int $course_id
 * @property int $lesson_id
 * @property int $block_id
 * @property int $video_asset_id
 * @property int $profile_id
 * @property int $profile_version
 * @property string $session_hash
 * @property string $lease_token_hash
 * @property string $request_key
 * @property string $status
 * @property Carbon $authorized_until
 * @property Carbon $expires_at
 * @property Carbon $last_heartbeat_at
 * @property string $session_code
 */
class PlaybackLease extends Model
{
    /** @use HasFactory<PlaybackLeaseFactory> */
    use HasFactory;

    protected $table = 'lms_playback_leases';

    protected $guarded = ['*'];

    protected $hidden = ['session_hash', 'lease_token_hash', 'request_key'];

    protected static function newFactory(): PlaybackLeaseFactory
    {
        return PlaybackLeaseFactory::new();
    }

    protected function casts(): array
    {
        return ['profile_version' => 'integer', 'authorized_until' => 'datetime', 'expires_at' => 'datetime', 'last_heartbeat_at' => 'datetime'];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /** @return BelongsTo<AuthorizedDevice, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(AuthorizedDevice::class, 'device_id');
    }

    /** @return BelongsTo<VideoAsset, $this> */
    public function video(): BelongsTo
    {
        return $this->belongsTo(VideoAsset::class, 'video_asset_id');
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }

    /** @return BelongsTo<LessonBlock, $this> */
    public function block(): BelongsTo
    {
        return $this->belongsTo(LessonBlock::class, 'block_id');
    }

    /** @return BelongsTo<ProtectionProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(ProtectionProfile::class, 'profile_id');
    }
}
