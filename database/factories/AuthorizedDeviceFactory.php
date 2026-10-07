<?php

namespace Database\Factories;

use App\Domains\Lms\Models\AuthorizedDevice;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AuthorizedDevice> */
class AuthorizedDeviceFactory extends Factory
{
    protected $model = AuthorizedDevice::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['student_id' => Student::factory()->verified(), 'token_hash' => hash('sha256', Str::random(64)), 'label' => 'My browser', 'status' => 'authorized', 'last_seen_at' => now('UTC')];
    }
}
