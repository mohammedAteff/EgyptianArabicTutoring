<?php

namespace Database\Seeders;

use App\Domains\Administration\Models\Administrator;
use Database\Factories\AdministratorFactory;
use Illuminate\Database\Seeder;

class AdministratorTwoFactorQaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local') || Administrator::query()->where('email', 'feature-qa-admin@example.test')->exists()) {
            return;
        }

        AdministratorFactory::new()->create([
            'name' => 'Feature QA Admin',
            'email' => 'feature-qa-admin@example.test',
            'password' => 'FeatureQa2026!',
            'role' => 'admin',
        ]);
    }
}
