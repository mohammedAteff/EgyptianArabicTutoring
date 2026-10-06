<?php

namespace Database\Factories;

use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentUnavailability;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<StudentUnavailability> */
class StudentUnavailabilityFactory extends Factory
{
    protected $model = StudentUnavailability::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['student_id' => Student::factory()->verified(), 'starts_at_utc' => now('UTC')->addWeek()->startOfDay(), 'ends_at_utc' => now('UTC')->addWeek()->startOfDay()->addDay(), 'timezone' => 'UTC', 'status' => 'active', 'idempotency_key' => (string) Str::uuid(), 'fingerprint' => hash('sha256', fake()->uuid())];
    }
}
