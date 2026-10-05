<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StudentNotification> */
class StudentNotificationFactory extends Factory
{
    protected $model = StudentNotification::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['student_id' => Student::factory()->verified(), 'deduplication_key' => fake()->sha256(), 'type' => 'homework', 'title' => 'Homework assigned', 'message' => 'New homework is ready.', 'link' => '/student/learning'];
    }
}
