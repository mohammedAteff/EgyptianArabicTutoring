<?php

namespace Database\Factories;

use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\VideoAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<VideoAsset> */
class VideoAssetFactory extends Factory
{
    protected $model = VideoAsset::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['course_id' => Course::factory(), 'provider' => 'bunny', 'provider_connection_id' => VideoProviderConnectionFactory::new(), 'library_id' => 123, 'provider_video_id' => Str::uuid(), 'label' => 'Synthetic video', 'status' => 'processing', 'upload_request_key' => Str::uuid(), 'lock_version' => 1];
    }
}
