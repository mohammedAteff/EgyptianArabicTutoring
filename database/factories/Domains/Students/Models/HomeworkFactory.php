<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Homework> */
class HomeworkFactory extends Factory
{
    protected $model = Homework::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['student_id' => Student::factory()->verified(), 'title' => fake()->sentence(3), 'instructions' => fake()->paragraph(), 'assigned_date' => now()->toDateString(), 'status' => 'assigned', 'student_visible' => false];
    }
}
