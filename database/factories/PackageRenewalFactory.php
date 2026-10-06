<?php

namespace Database\Factories;

use App\Domains\Students\Models\PackageRenewal;
use App\Domains\Students\Models\StudentPackage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PackageRenewal> */
class PackageRenewalFactory extends Factory
{
    protected $model = PackageRenewal::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['previous_package_id' => StudentPackage::factory(), 'student_id' => fn (array $attributes) => StudentPackage::query()->findOrFail($attributes['previous_package_id'])->student_id, 'new_package_id' => fn (array $attributes) => StudentPackage::factory()->create(['student_id' => $attributes['student_id']])->id, 'renewal_date' => now()->toDateString(), 'idempotency_key' => (string) Str::uuid(), 'fingerprint' => hash('sha256', fake()->uuid()), 'created_at' => now('UTC')];
    }
}
