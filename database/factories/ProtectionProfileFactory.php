<?php

namespace Database\Factories;

use App\Domains\Lms\Models\ProtectionProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProtectionProfile> */
class ProtectionProfileFactory extends Factory
{
    protected $model = ProtectionProfile::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['name' => fake()->unique()->lexify('Test-????'), 'device_limit' => 2, 'stream_limit' => 1, 'token_seconds' => 120, 'lease_seconds' => 150, 'heartbeat_seconds' => 30, 'watermark' => true, 'drm_mode' => 'none', 'active' => true, 'lock_version' => 1];
    }
}
