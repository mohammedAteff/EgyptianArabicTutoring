<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Models\StudentPackageEntitlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StudentPackageEntitlement> */
class StudentPackageEntitlementFactory extends Factory
{
    protected $model = StudentPackageEntitlement::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['student_package_id' => StudentPackage::factory(), 'student_id' => fn (array $attributes) => StudentPackage::findOrFail($attributes['student_package_id'])->student_id, 'entitlement_type_id' => EntitlementType::factory(), 'granted_quantity' => 1];
    }
}
