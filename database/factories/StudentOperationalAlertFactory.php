<?php

namespace Database\Factories;

use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentOperationalAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StudentOperationalAlert> */
class StudentOperationalAlertFactory extends Factory
{
    protected $model = StudentOperationalAlert::class;

    public function definition(): array
    {
        return ['student_id' => Student::factory()->verified(), 'created_by' => AdministratorFactory::new(), 'updated_by' => AdministratorFactory::new(), 'title' => fake()->sentence(3), 'body' => fake()->paragraph(), 'status' => 'active'];
    }
}
