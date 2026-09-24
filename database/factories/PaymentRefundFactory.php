<?php

namespace Database\Factories;

use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\PaymentRefund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentRefund>
 */
class PaymentRefundFactory extends Factory
{
    protected $model = PaymentRefund::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_record_id' => PaymentRecord::factory(),
            'student_package_id' => fn (array $attributes) => PaymentRecord::findOrFail($attributes['payment_record_id'])->student_package_id,
            'student_id' => fn (array $attributes) => PaymentRecord::findOrFail($attributes['payment_record_id'])->student_id,
            'idempotency_key' => fake()->uuid(),
            'amount_refunded' => '10.00',
            'currency' => 'USD',
            'refunded_at' => now('UTC'),
            'created_at' => now('UTC'),
        ];
    }
}
