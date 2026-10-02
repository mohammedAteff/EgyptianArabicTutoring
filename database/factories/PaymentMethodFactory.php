<?php

namespace Database\Factories;

use App\Domains\Students\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentMethod> */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => fake()->unique()->company(), 'active' => true, 'sort_order' => 10, 'is_default' => false];
    }
}
