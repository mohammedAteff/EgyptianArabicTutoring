<?php

namespace Database\Seeders;

use App\Domains\Reporting\Models\TutorWorkEntry;
use Illuminate\Database\Seeder;

class TutorWorkEntrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TutorWorkEntry::factory()->create();
    }
}
