<?php

namespace App\Domains\Lms\Models;

use Database\Factories\ProtectionProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property string $name
 * @property bool $authentication_required
 * @property bool $secure_playback
 * @property bool $downloads_allowed
 * @property int|null $device_limit
 * @property int|null $stream_limit
 * @property int $token_seconds
 * @property int $lease_seconds
 * @property int $heartbeat_seconds
 * @property bool $watermark
 * @property string $drm_mode
 * @property bool $active
 * @property int $lock_version
 */
class ProtectionProfile extends LmsModel
{
    /** @use HasFactory<ProtectionProfileFactory> */
    use HasFactory;

    protected $table = 'lms_protection_profiles';

    protected $guarded = ['*'];

    protected static function newFactory(): ProtectionProfileFactory
    {
        return ProtectionProfileFactory::new();
    }

    protected function casts(): array
    {
        return ['authentication_required' => 'boolean', 'secure_playback' => 'boolean', 'downloads_allowed' => 'boolean', 'device_limit' => 'integer', 'stream_limit' => 'integer', 'token_seconds' => 'integer', 'lease_seconds' => 'integer', 'heartbeat_seconds' => 'integer', 'watermark' => 'boolean', 'active' => 'boolean', 'lock_version' => 'integer'];
    }
}
