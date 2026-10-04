<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Services\SocialAnalyticsRollup;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Audit\Services\PrivacyDatabaseSessionHandler;
use App\Domains\Audit\Services\TransientRateLimitKey;
use App\Domains\CMS\Models\Setting;
use App\Domains\Reporting\Services\ReportService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SocialHistoryAndIpPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_dimensions_and_daily_uniques_survive_pruning_without_false_period_uniques(): void
    {
        $day = CarbonImmutable::parse('2026-05-01', 'Africa/Cairo');
        $this->event($day->addHours(10), 'same-visitor', 'footer_social');
        $this->event($day->addHours(11), 'same-visitor', 'footer_social');
        $this->event($day->addDay()->addHours(10), 'same-visitor', 'floating_cta');
        $this->event($day->addDay()->addHours(11), 'other-visitor', 'floating_cta');
        $this->event($day->addDay()->addHours(12), 'bot-visitor', 'footer_social', ['is_bot' => true]);
        $this->event($day->addHours(12), 'outbound-visitor', 'unknown', ['event_name' => 'outbound_link_clicked', 'metadata' => []]);
        $service = app(ReportService::class);
        $before = $service->getSocialReport($day->utc(), $day->addDay()->endOfDay()->utc());
        $this->assertSame(4, $before['total_clicks']);
        $this->assertSame(2, $before['unique_visitors']);
        $this->assertSame(1, $before['outbound_clicks']);
        $scope = ['platform' => 'whatsapp', 'placement' => 'footer_social', 'page_url' => '/social-qa', 'country' => 'EG', 'source' => 'qa', 'medium' => 'social', 'campaign' => 'launch', 'language' => 'en', 'context' => 'public'];
        $this->assertSame(2, $service->getSocialReport($day->utc(), $day->endOfDay()->utc(), $scope)['total_clicks']);
        $rollup = app(SocialAnalyticsRollup::class);
        $rollup->aggregateDay($day);
        $rollup->aggregateDay($day->addDay());
        AnalyticsEvent::query()->delete();
        $after = $service->getSocialReport($day->utc(), $day->addDay()->endOfDay()->utc());
        $this->assertEquals($before['rows']->all(), $after['rows']->all());
        $this->assertNull($after['unique_visitors']);
        $this->assertTrue($after['uses_durable_history']);
        $filtered = $service->getSocialReport($day->utc(), $day->addDay()->endOfDay()->utc(), $scope);
        $this->assertSame(2, $filtered['total_clicks']);
        $this->assertSame(1, $filtered['rows']->sole()['unique_visitors']);
        $rollup->aggregateDay($day);
        $this->assertSame(2, $service->getSocialReport($day->utc(), $day->endOfDay()->utc(), $scope)['total_clicks']);
        Setting::set('business_timezone', 'Europe/Berlin');
        $this->assertSame(0, $service->getSocialReport($day->utc(), $day->endOfDay()->utc(), $scope)['total_clicks']);
    }

    #[TestWith(['2026-01-10'])]
    #[TestWith(['2026-06-10'])]
    public function test_existing_aggregation_pruning_persists_social_detail_before_deleting_raw_events(string $date): void
    {
        $day = CarbonImmutable::parse($date, 'Africa/Cairo');
        $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00:00', 'Africa/Cairo'));
        $this->event($day->addHours(12), 'prune-visitor', 'footer_social');
        $this->artisan('analytics:aggregate-daily', ['--date' => $date, '--prune' => true])->assertSuccessful();
        $this->assertDatabaseMissing('analytics_events', ['visitor_token' => 'prune-visitor']);
        $this->assertDatabaseHas('daily_social_metrics', ['metric_date' => $date, 'reporting_timezone' => 'Africa/Cairo', 'clicks' => 1]);
        $result = app(ReportService::class)->getSocialReport($day->utc(), $day->endOfDay()->utc(), ['placement' => 'footer_social']);
        $this->assertSame(1, $result['total_clicks']);
        $this->assertSame('footer_social', $result['rows']->sole()['placement']);
    }

    public function test_social_business_day_handles_dst_and_excludes_the_next_day(): void
    {
        Setting::set('business_timezone', 'Europe/Berlin');
        $day = CarbonImmutable::parse('2026-10-25', 'Europe/Berlin');
        $this->event($day->utc(), 'first', 'footer_social');
        $this->event($day->addDay()->utc()->subSecond(), 'last', 'footer_social');
        $this->event($day->addDay()->utc(), 'outside', 'footer_social');
        $this->assertSame(2, app(ReportService::class)->getSocialReport($day->utc(), $day->endOfDay()->utc())['total_clicks']);
    }

    public function test_audit_guard_and_database_sessions_preserve_records_without_request_addresses(): void
    {
        $request = Request::create('/qa', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.8', 'HTTP_USER_AGENT' => 'Privacy QA']);
        $this->app->instance('request', $request);
        $audit = app(AuditLogService::class)->log('privacy_qa', 'privacy_test', null, null, ['request_ip' => '203.0.113.8', 'nested' => ['client_ip' => '2001:db8::1'], 'amount' => '25.00']);
        $this->assertNull($audit->ip_address);
        $this->assertSame('[redacted]', $audit->new_values['request_ip']);
        $this->assertSame('25.00', $audit->new_values['amount']);
        $direct = AuditLog::create(['action' => 'direct_qa', 'entity_type' => 'privacy_test', 'ip_address' => '203.0.113.8', 'created_at' => now()]);
        $this->assertNull($direct->ip_address);
        $handler = Session::driver('database')->getHandler();
        $this->assertInstanceOf(PrivacyDatabaseSessionHandler::class, $handler);
        $id = Str::random(40);
        $handler->write($id, 'unchanged-session-payload');
        $session = DB::table('sessions')->where('id', $id)->first();
        $this->assertNull($session->ip_address);
        $this->assertSame(base64_encode('unchanged-session-payload'), $session->payload);
        $this->assertSame('Privacy QA', $session->user_agent);
    }

    public function test_staff_auth_password_recovery_audits_remain_and_ip_rate_limiting_still_works(): void
    {
        Notification::fake();
        $admin = AdministratorFactory::new()->create();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8']);
        $key = TransientRateLimitKey::make('staff-login', $admin->email.'|203.0.113.8');
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));
        $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'Password123!'])->assertSessionHasErrors('email');
        RateLimiter::clear($key);
        $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'Password123!'])->assertRedirect(route('admin.dashboard'));
        $this->post(route('admin.logout'))->assertRedirect();
        $this->post(route('admin.password.email'), ['email' => $admin->email])->assertRedirect();
        $token = Password::broker('administrators')->createToken($admin);
        $this->post(route('admin.password.update'), ['email' => $admin->email, 'token' => $token, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertRedirect();
        foreach (['admin_login', 'admin_logout', 'password_reset_requested', 'password_reset_completed'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action, 'ip_address' => null]);
        }
    }

    public function test_historical_address_purge_is_idempotent_and_keeps_unrelated_audit_fields(): void
    {
        $uuid = Str::uuid()->toString();
        $id = DB::table('audit_logs')->insertGetId(['event_uuid' => $uuid, 'action' => 'legacy_qa', 'entity_type' => 'privacy_test', 'ip_address' => '203.0.113.8', 'new_data' => json_encode(['request_ip' => '203.0.113.8', 'amount' => '25.00', 'nested' => ['remote_addr' => '2001:db8::1']]), 'created_at' => now()]);
        $migration = require database_path('migrations/2026_10_03_232535_redact_persisted_request_addresses.php');
        $migration->up();
        $migration->up();
        $row = DB::table('audit_logs')->where('id', $id)->first();
        $this->assertNull($row->ip_address);
        $this->assertSame($uuid, $row->event_uuid);
        $this->assertSame('legacy_qa', $row->action);
        $data = json_decode($row->new_data, true);
        $this->assertSame('25.00', $data['amount']);
        $this->assertSame('[redacted]', $data['request_ip']);
        $this->assertSame('[redacted]', $data['nested']['remote_addr']);
    }

    public function test_rate_limit_cache_cleanup_preserves_active_counters_timers_and_unrelated_keys(): void
    {
        $prefix = (string) config('cache.prefix');
        $expiry = now()->timestamp + 60;
        $newKey = $prefix.TransientRateLimitKey::make('staff-login', 'qa@example.test|203.0.113.8');
        DB::table('cache')->insert([
            ['key' => $prefix.'qa@example.test|203.0.113.8', 'value' => serialize(4), 'expiration' => $expiry],
            ['key' => $prefix.'qa@example.test|203.0.113.8:timer', 'value' => serialize($expiry), 'expiration' => $expiry],
            ['key' => $newKey, 'value' => serialize(1), 'expiration' => $expiry],
            ['key' => $prefix.'throttle:hold:ip:2001:db8::1', 'value' => serialize(2), 'expiration' => now()->timestamp - 1],
            ['key' => $prefix.'unrelated-setting', 'value' => serialize('keep'), 'expiration' => $expiry],
        ]);
        $migration = require database_path('migrations/2026_10_04_002050_redact_transient_rate_limit_addresses.php');
        $migration->up();
        $migration->up();
        $this->assertSame(5, unserialize(DB::table('cache')->where('key', $newKey)->value('value'), ['allowed_classes' => false]));
        $this->assertSame($expiry, (int) DB::table('cache')->where('key', $newKey)->value('expiration'));
        $this->assertSame($expiry, unserialize(DB::table('cache')->where('key', $newKey.':timer')->value('value'), ['allowed_classes' => false]));
        $this->assertDatabaseHas('cache', ['key' => $prefix.'unrelated-setting', 'value' => serialize('keep')]);
        $this->assertSame(0, DB::table('cache')->where('key', 'like', '%203.0.113.8%')->orWhere('key', 'like', '%2001:db8::1%')->count());
    }

    private function event(CarbonImmutable $time, string $visitor, string $placement, array $overrides = []): AnalyticsEvent
    {
        return AnalyticsEvent::create(array_replace(['event_name' => 'whatsapp_clicked', 'visitor_token' => $visitor, 'page' => '/social-qa', 'utm_source' => 'qa', 'utm_medium' => 'social', 'utm_campaign' => 'launch', 'is_bot' => false, 'created_at' => $time->utc(), 'metadata' => ['platform' => 'whatsapp', 'placement' => $placement, 'language' => 'en', 'context' => 'public', 'detected_country_code' => 'EG']], $overrides));
    }
}
