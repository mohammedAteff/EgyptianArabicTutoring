<?php

namespace Database\Factories;

use App\Domains\Booking\Models\RecurringLessonPlan;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<RecurringLessonPlan> */
class RecurringLessonPlanFactory extends Factory
{
    protected $model = RecurringLessonPlan::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['student_id' => Student::factory()->verified(), 'session_type_id' => SessionType::factory(), 'cadence' => 'weekly', 'start_date' => now()->addWeek()->toDateString(), 'preferred_time' => '12:00', 'timezone' => 'UTC', 'fold_policy' => 'reject', 'status' => 'active', 'idempotency_key' => (string) Str::uuid(), 'fingerprint' => hash('sha256', fake()->uuid())];
    }
}
