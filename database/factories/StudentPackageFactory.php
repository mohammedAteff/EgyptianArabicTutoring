<?php

namespace Database\Factories;

use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentPackage>
 */
class StudentPackageFactory extends Factory
{
    protected $model = StudentPackage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory()->verified(),
            'package_name' => 'Eight lessons',
            'original_price' => '240.00',
            'discount_amount' => '0.00',
            'final_price' => '240.00',
            'currency' => 'USD',
            'total_sessions_allocated' => 8,
            'expiration_date' => null,
            'status' => 'active',
        ];
    }
}
