<?php

namespace Database\Factories;

use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\StudentPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionLedgerEntry>
 */
class SessionLedgerEntryFactory extends Factory
{
    protected $model = SessionLedgerEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_package_id' => StudentPackage::factory(),
            'student_id' => fn (array $attributes) => StudentPackage::findOrFail($attributes['student_package_id'])->student_id,
            'idempotency_key' => fake()->uuid(),
            'entry_type' => 'package_grant',
            'credit_change' => 8,
            'description' => 'Package grant',
            'created_at' => now('UTC'),
        ];
    }
}
