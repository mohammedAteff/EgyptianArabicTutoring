<?php

namespace Database\Factories;

use App\Domains\Students\Models\PackageInstallment;
use App\Domains\Students\Models\StudentPackage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PackageInstallment> */
class PackageInstallmentFactory extends Factory
{
    protected $model = PackageInstallment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['student_package_id' => StudentPackage::factory(), 'sequence' => 1, 'expected_amount' => '240.00', 'due_date' => now()->addWeek()->toDateString(), 'status' => 'scheduled', 'schedule_key' => (string) Str::uuid(), 'fingerprint' => hash('sha256', fake()->uuid())];
    }
}
