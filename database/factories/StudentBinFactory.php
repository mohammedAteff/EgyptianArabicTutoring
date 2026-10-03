<?php

namespace Database\Factories;

use App\Domains\Students\Models\StudentBin;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StudentBin> */
class StudentBinFactory extends Factory
{
    protected $model = StudentBin::class;

    public function definition(): array
    {
        return ['student_id' => StudentFactory::new(), 'created_by_type' => 'staff', 'created_by_id' => AdministratorFactory::new(), 'student_visible' => true, 'title' => fake()->sentence(3), 'body' => fake()->paragraph()];
    }
}
