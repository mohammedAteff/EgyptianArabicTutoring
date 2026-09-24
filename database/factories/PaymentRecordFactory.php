<?php

namespace Database\Factories;

use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\StudentPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentRecord>
 */
class PaymentRecordFactory extends Factory
{
    protected $model = PaymentRecord::class;

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
            'amount_paid' => '240.00',
            'currency' => 'USD',
            'payment_method' => 'PayPal - Manual',
            'paid_at' => now('UTC'),
            'created_at' => now('UTC'),
        ];
    }
}
