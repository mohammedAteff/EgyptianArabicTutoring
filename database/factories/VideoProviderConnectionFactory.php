<?php

namespace Database\Factories;

use App\Domains\Lms\Models\VideoProviderConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VideoProviderConnection> */
class VideoProviderConnectionFactory extends Factory
{
    protected $model = VideoProviderConnection::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['provider' => 'bunny', 'library_id' => 123, 'cdn_hostname' => 'test-library.b-cdn.net', 'enabled' => false, 'lock_version' => 1];
    }
}
