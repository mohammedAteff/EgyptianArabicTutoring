<?php

namespace Database\Factories;

use App\Domains\Administration\Models\StaffBin;
use App\Domains\Administration\Models\StaffNotePreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StaffNotePreference> */
class StaffNotePreferenceFactory extends Factory
{
    protected $model = StaffNotePreference::class;

    public function definition(): array
    {
        return ['administrator_id' => AdministratorFactory::new(), 'staff_bin_id' => StaffBin::factory(), 'pinned' => false, 'favorite' => false];
    }
}
