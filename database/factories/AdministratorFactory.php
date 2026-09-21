<?php

namespace Database\Factories;

use App\Domains\Administration\Models\Administrator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Administrator>
 */
class AdministratorFactory extends Factory
{
    protected $model = Administrator::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Abdallah Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('Password123!'),
            'role' => 'super_admin',
        ];
    }
}
