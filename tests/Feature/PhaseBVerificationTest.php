<?php

namespace Tests\Feature;

use App\Domains\Booking\Actions\SyncDiagnosticSessionType;
use App\Domains\Booking\Models\SessionType;
use App\Services\SessionTypeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhaseBVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_detected_country_code_columns_and_table_exist_in_schema(): void
    {
        $this->assertTrue(Schema::hasColumn('visitors', 'detected_country_code'));
        $this->assertTrue(Schema::hasColumn('visitor_sessions', 'detected_country_code'));
        $this->assertTrue(Schema::hasColumn('bookings', 'detected_country_code'));

        $this->assertTrue(Schema::hasTable('daily_country_metrics'));
        $this->assertTrue(Schema::hasColumn('daily_country_metrics', 'metric_date'));
        $this->assertTrue(Schema::hasColumn('daily_country_metrics', 'country_code'));
        $this->assertTrue(Schema::hasColumn('daily_country_metrics', 'visitors'));
        $this->assertTrue(Schema::hasColumn('daily_country_metrics', 'sessions'));
        $this->assertTrue(Schema::hasColumn('daily_country_metrics', 'page_views'));
    }

    public function test_diagnostic_session_type_sync_and_legacy_deactivation_16_5(): void
    {
        // 1. Ensure a legacy session type exists
        $legacy = SessionType::create([
            'title' => '1-on-1 Egyptian Arabic Tutoring',
            'slug' => 'egyptian-arabic-session',
            'description' => 'Legacy description',
            'duration_minutes' => 60,
            'price' => 40.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        // 2. Execute sync action
        $syncAction = new SyncDiagnosticSessionType;
        $diagnostic = $syncAction->execute();

        // 3. Assert diagnostic session type attributes
        $this->assertSame('Diagnostic & Learning Roadmap', $diagnostic->title);
        $this->assertSame('diagnostic-session', $diagnostic->slug);
        $this->assertTrue((bool) $diagnostic->active);
        $this->assertEquals(25.00, (float) $diagnostic->price);
        $this->assertSame(60, (int) $diagnostic->duration_minutes);

        // 4. Assert legacy record was deactivated but NOT deleted
        $legacy->refresh();
        $this->assertFalse((bool) $legacy->active);
        $this->assertDatabaseHas('session_types', ['id' => $legacy->id]);

        // 5. Total count must be at least 2
        $this->assertGreaterThanOrEqual(2, SessionType::count());
    }

    /** @test */
    public function test_session_types_synchronization_is_atomic_idempotent_and_preserves_records(): void
    {
        // Seed initial state with an existing custom session type
        $legacyId = \DB::table('session_types')->insertGetId([
            'title' => 'Legacy 90-min Session',
            'slug' => 'legacy-90-min-session',
            'duration_minutes' => 90,
            'price' => 45.00,
            'currency' => 'USD',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Execute synchronization service/command
        app(SessionTypeSyncService::class)->sync();

        $activeTypes = \App\Models\SessionType::where('active', true)->get();
        $this->assertCount(1, $activeTypes);
        $this->assertEquals(60, $activeTypes->first()->duration_minutes);
        $this->assertEquals(25.00, $activeTypes->first()->price);

        // Legacy record is deactivated, NOT deleted
        $legacy = \App\Models\SessionType::find($legacyId);
        $this->assertNotNull($legacy);
        $this->assertFalse((bool) $legacy->active);

        // Rerunning synchronization does not duplicate the Diagnostic record
        app(SessionTypeSyncService::class)->sync();
        $this->assertEquals(1, \App\Models\SessionType::where('active', true)->count());
    }
}
