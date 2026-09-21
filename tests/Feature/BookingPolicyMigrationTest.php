<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPolicyMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_v2_policy_cutoffs_are_split_by_migration(): void
    {
        $this->assertSame('4', (string) Setting::get('booking_cancellation_cutoff_hours'));
        $this->assertSame('24', (string) Setting::get('booking_reschedule_cutoff_hours'));
    }
}
