<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\CMS\Models\Setting;
use App\Domains\Reporting\Services\ReportService;
use App\Domains\Students\Models\Student;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class OperationalSettingsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string,mixed> */
    private function fields(): array
    {
        return ['site_name' => 'QA site', 'business_timezone' => 'Africa/Cairo', 'default_language' => 'en', 'hero_title' => 'QA title', 'hero_subtitle' => 'QA subtitle', 'cancellation_policy' => 'QA cancellation', 'rescheduling_policy' => 'QA rescheduling', 'booking_instructions' => 'QA instructions', 'maintenance_mode' => 0, 'operations_present' => 1, 'maintenance_message' => 'QA back soon', 'whatsapp_url' => 'https://wa.me/201022222222', 'whatsapp_public' => 1, 'whatsapp_portal' => 0, 'whatsapp_label' => ['en' => 'Chat QA', 'fr' => 'Discuter QA', 'de' => 'Kontakt QA'], 'whatsapp_message' => ['en' => 'QA hello', 'fr' => 'QA bonjour', 'de' => 'QA hallo'], 'action' => 'publish'];
    }

    #[TestWith(['publish'])] #[TestWith(['draft'])]
    public function test_normal_settings_save_persists_operational_controls_and_reloads(string $action): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $this->actingAs($admin, 'web')->post(route('admin.settings.update'), array_merge($this->fields(), ['action' => $action]))->assertRedirect()->assertSessionHasNoErrors();
        $page = $this->get(route('admin.settings.index'))->assertOk()->assertSee('Chat QA')->assertSee('https://wa.me/201022222222');
        $document = new \DOMDocument;
        @$document->loadHTML($page->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertCount(1, $xpath->query('//form[@id="business-settings"]//button[@name="action" and @value="publish"]'));
        $this->assertCount(1, $xpath->query('//form[@id="business-settings"]//input[@name="whatsapp_public" and @checked]'));
        auth('web')->logout();
        $this->get('/')->assertOk()->assertSee('aria-label="Chat QA"', false)->assertSee('https://wa.me/201022222222?text=QA%20hello', false);
    }

    public function test_normal_settings_workflow_toggles_maintenance_anonymously_and_restores_live(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $this->actingAs($admin, 'web')->post(route('admin.settings.update'), array_merge($this->fields(), ['maintenance_mode' => 1]))->assertSessionHasNoErrors();
        $this->get(route('admin.settings.index'))->assertSee('value="1" selected', false);
        auth('web')->logout();
        foreach (['/', '/pricing', '/resources', '/book', '/student/login'] as $path) {
            $this->get($path)->assertStatus(503)->assertSee('QA back soon')->assertDontSee('data-whatsapp-cta', false);
        }
        $this->get('/admin/login')->assertOk();
        $this->assertDatabaseCount('maintenance_visits', 5);
        $this->actingAs($admin, 'web')->post(route('admin.settings.update'), $this->fields())->assertSessionHasNoErrors();
        auth('web')->logout();
        $this->get('/')->assertOk();
    }

    public function test_invalid_operational_values_do_not_partially_publish_other_settings(): void
    {
        Setting::set('site_name', 'Before');
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']), 'web')->post(route('admin.settings.update'), array_merge($this->fields(), ['whatsapp_url' => 'https://example.org']))->assertSessionHasErrors('whatsapp_url');
        $this->assertSame('Before', Setting::get('site_name'));
    }

    public function test_enabled_contact_requires_its_destination_and_off_switch_survives_reload(): void
    {
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']), 'web')->post(route('admin.settings.update'), array_merge($this->fields(), ['whatsapp_url' => '']))->assertSessionHasErrors('whatsapp_url');
        $this->post(route('admin.settings.update'), array_merge($this->fields(), ['whatsapp_public' => 0, 'whatsapp_portal' => 0]))->assertSessionHasNoErrors();
        auth('web')->logout();
        $response = $this->get('/');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $this->assertCount(0, (new \DOMXPath($document))->query('//a[@data-whatsapp-cta]'));
    }

    public function test_social_report_preserves_all_platforms_placement_unique_visitors_and_unknown_history(): void
    {
        foreach (['youtube', 'tiktok', 'instagram', 'telegram', 'whatsapp'] as $platform) {
            foreach (['one', 'one', 'two'] as $visitor) {
                AnalyticsEvent::create(['event_uuid' => (string) Str::uuid(), 'event_name' => $platform === 'whatsapp' ? 'whatsapp_clicked' : ($platform === 'telegram' ? 'telegram_clicked' : 'social_link_clicked'), 'visitor_token' => $visitor, 'page' => '/resources', 'metadata' => ['platform' => $platform, 'placement' => 'footer_social', 'language' => 'fr', 'detected_country_code' => 'FR'], 'created_at' => now('UTC'), 'is_bot' => false]);
            }
        }
        AnalyticsEvent::create(['event_uuid' => (string) Str::uuid(), 'event_name' => 'outbound_link_clicked', 'visitor_token' => 'one', 'page' => '/games', 'metadata' => [], 'created_at' => now('UTC'), 'is_bot' => false]);
        $report = app(ReportService::class)->getSocialReport(now('UTC')->subMinute(), now('UTC')->addMinute());
        $this->assertSame(15, $report['total_clicks']);
        $this->assertSame(1, $report['outbound_clicks']);
        $this->assertSame(2, $report['unique_visitors']);
        foreach (['YouTube', 'TikTok', 'Instagram', 'Telegram', 'WhatsApp'] as $platform) {
            $row = $report['rows']->firstWhere('platform', $platform);
            $this->assertSame(3, $row['clicks']);
            $this->assertSame(2, $row['unique_visitors']);
            $this->assertSame('footer_social', $row['placement']);
            $this->assertSame('fr', $row['language']);
            $this->assertSame('FR', $row['country']);
        }
        $this->assertSame('unknown', $report['rows']->firstWhere('platform', 'Outbound Link')['placement']);
    }

    public function test_maintenance_cache_is_invalidated_after_atomic_settings_commit(): void
    {
        DB::transaction(function (): void {
            Setting::set('maintenance_mode', true);
            Cache::put('maintenance_mode_active', false, 30);
        });
        $this->assertFalse(Cache::has('maintenance_mode_active'));
        $this->get('/')->assertStatus(503);
    }

    public function test_draft_publish_reload_applies_portal_goals_and_connection_exclusion_then_clears_them(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $student = Student::factory()->verified()->create();
        $fields = array_merge($this->fields(), ['whatsapp_portal' => 1, 'goals' => ['booking_completed'], 'exclude_connection' => 1]);
        foreach (['draft', 'publish'] as $action) {
            $this->actingAs($admin, 'web')->post(route('admin.settings.update'), array_merge($fields, ['action' => $action]))->assertSessionHasNoErrors();
            $this->get(route('admin.settings.index'))->assertOk()->assertSee('Chat QA');
        }
        $this->assertSame(['booking_completed'], Setting::get('analytics.goals'));
        $this->assertCount(1, Setting::get('analytics.internal_hashes'));
        auth('web')->logout();
        $this->get('/')->assertOk()->assertSee('name="analytics-disabled" content="1"', false);
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()])->get(route('student.dashboard'))->assertOk()->assertSee('aria-label="Chat QA"', false);
        $this->actingAs($admin, 'web')->post(route('admin.settings.update'), array_merge($this->fields(), ['whatsapp_public' => 0, 'whatsapp_portal' => 0, 'goals' => [], 'clear_exclusions' => 1]))->assertSessionHasNoErrors();
        $this->assertSame([], Setting::get('analytics.goals'));
        $this->assertSame([], Setting::get('analytics.internal_hashes'));
        auth('web')->logout();
        $this->get(route('student.dashboard'))->assertOk()->assertDontSee('data-whatsapp-cta', false);
        $this->get('/')->assertOk()->assertDontSee('name="analytics-disabled" content="1"', false);
    }
}
