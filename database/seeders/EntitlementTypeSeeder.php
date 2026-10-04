<?php

namespace Database\Seeders;

use App\Domains\Students\Models\EntitlementType;
use Illuminate\Database\Seeder;

class EntitlementTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['one_hour' => ['1-hour sessions', 60], 'two_hour' => ['2-hour sessions', 120]] as $code => [$label, $minutes]) {
            EntitlementType::query()->firstOrCreate(['code' => $code], ['label' => $label, 'nominal_minutes' => $minutes, 'active' => true]);
        }
    }
}
