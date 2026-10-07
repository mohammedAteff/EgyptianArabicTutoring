<?php

namespace Database\Factories;

use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\LmsAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<LmsAsset> */
class LmsAssetFactory extends Factory
{
    protected $model = LmsAsset::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['course_id' => Course::factory(), 'kind' => 'image', 'status' => 'active', 'disk' => 'local',
            'path' => 'lms-assets/'.Str::uuid().'.png', 'mime_type' => 'image/png', 'byte_size' => 1, 'sha256' => hash('sha256', 'fixture')];
    }
}
