<?php

namespace Database\Seeders;

use App\Domains\Booking\Actions\SyncDiagnosticSessionType;
use Illuminate\Database\Seeder;

class SyncDiagnosticSessionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        (new SyncDiagnosticSessionType)->execute();
    }
}
