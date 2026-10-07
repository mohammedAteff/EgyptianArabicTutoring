<?php

namespace App\Domains\Lms\Models;

use Database\Factories\VideoProviderConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $provider
 * @property int|null $library_id
 * @property string|null $cdn_hostname
 * @property string|null $api_key
 * @property string|null $read_only_key
 * @property string|null $signing_key
 * @property string|null $account_key
 * @property array|null $allowed_domains
 * @property bool $enabled
 * @property int $lock_version
 */
class VideoProviderConnection extends Model
{
    /** @use HasFactory<VideoProviderConnectionFactory> */
    use HasFactory;

    protected $table = 'lms_video_providers';

    protected $guarded = ['*'];

    protected $hidden = ['api_key', 'read_only_key', 'signing_key', 'account_key'];

    protected static function newFactory(): VideoProviderConnectionFactory
    {
        return VideoProviderConnectionFactory::new();
    }

    protected function casts(): array
    {
        return ['api_key' => 'encrypted', 'read_only_key' => 'encrypted', 'signing_key' => 'encrypted', 'account_key' => 'encrypted', 'allowed_domains' => 'array', 'enabled' => 'boolean', 'lock_version' => 'integer', 'last_verified_at' => 'datetime'];
    }

    /** @return HasMany<VideoAsset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(VideoAsset::class, 'provider_connection_id');
    }
}
