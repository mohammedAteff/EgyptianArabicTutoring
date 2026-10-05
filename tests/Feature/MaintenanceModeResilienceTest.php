<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaintenanceModeResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::forget('system.maintenance_mode');
        parent::tearDown();
    }

    public function test_normal_operation_serves_pages_normally(): void
    {
        Cache::forget('system.maintenance_mode');
        DB::table('settings')->updateOrInsert(
            ['key' => 'system.maintenance_mode'],
            ['value' => '0', 'group' => 'system', 'is_public' => false]
        );

        $response = $this->get('/');
        $response->assertOk();
    }

    public function test_maintenance_mode_intercepts_public_traffic_and_records_visits(): void
    {
        Cache::forget('system.maintenance_mode');
        DB::table('settings')->updateOrInsert(
            ['key' => 'system.maintenance_mode'],
            ['value' => '1', 'group' => 'system', 'is_public' => false]
        );

        // 1. Inbound request returns 503
        $response = $this->get('/');
        $response->assertStatus(503);

        // 2. Verified visit recorded in maintenance_visits table
        $this->assertDatabaseHas('maintenance_visits', [
            'url' => url('/'),
            'is_bounced' => true,
        ]);
    }

    public function test_authenticated_administrator_bypasses_maintenance_mode(): void
    {
        Cache::forget('system.maintenance_mode');
        DB::table('settings')->updateOrInsert(
            ['key' => 'system.maintenance_mode'],
            ['value' => '1', 'group' => 'system', 'is_public' => false]
        );

        $admin = Administrator::create([
            'name' => 'Super Admin',
            'email' => 'superadmin_maint@example.com',
            'password' => bcrypt('Secret123!'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'web')
            ->get('/')
            ->assertOk();

        $this->actingAs($admin, 'web')
            ->get('/admin/dashboard')
            ->assertOk();
    }

    public function test_unauthenticated_admin_auth_endpoints_bypass_maintenance(): void
    {
        Cache::forget('system.maintenance_mode');
        DB::table('settings')->updateOrInsert(
            ['key' => 'system.maintenance_mode'],
            ['value' => '1', 'group' => 'system', 'is_public' => false]
        );

        // admin.login
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Sign In');

        // admin.password.request
        $this->get('/admin/forgot-password')
            ->assertOk()
            ->assertSee('Forgot Password');

        // /up heartbeat
        $this->get('/up')
            ->assertOk();
    }

    public function test_system_health_controller_displays_metrics_and_exports_traffic(): void
    {
        $admin = Administrator::create([
            'name' => 'Owner Admin',
            'email' => 'owner_maint@example.com',
            'password' => bcrypt('Secret123!'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        // Seed some maintenance visits
        DB::table('maintenance_visits')->insert([
            [
                'visitor_id' => 'vis_123',
                'ip_address' => '127.0.0.1',
                'country_code' => 'EG',
                'url' => 'http://localhost/pricing',
                'is_bounced' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'visitor_id' => 'vis_456',
                'ip_address' => '127.0.0.1',
                'country_code' => 'US',
                'url' => 'http://localhost/contact',
                'is_bounced' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Health page
        $this->actingAs($admin, 'web')
            ->get(route('admin.analytics.maintenance'))
            ->assertOk()
            ->assertSee('Maintenance Mode Analytics')
            ->assertSee('Total Intercepted Hits');

        // CSV export
        $csvResponse = $this->actingAs($admin, 'web')
            ->get(route('admin.health.maintenance-visitors.export', ['format' => 'csv']));
        $csvResponse->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csvResponse->headers->get('content-type'));

        // XLSX export
        $xlsxResponse = $this->actingAs($admin, 'web')
            ->get(route('admin.health.maintenance-visitors.export', ['format' => 'xlsx']));
        $xlsxResponse->assertOk();
    }
}
