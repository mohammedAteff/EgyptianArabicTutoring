<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\DailyCountryMetric;
use App\Domains\Analytics\Models\MarketingTouch;
use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\Analytics\Services\GeoIpService;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingHoldService;
use App\Domains\Contacts\Models\Contact;
use App\Http\Middleware\TrackVisitorSession;
use App\Models\Administrator;
use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\Session;
use App\Models\Visitor;
use App\Services\AnalyticsService;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\HasPublishedShortForm;
use Tests\TestCase;

class PhaseCVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->installShortFormFixture();
    }

    use HasPublishedShortForm;
    use RefreshDatabase;

    /** @test */
    public function test_analytics_ingests_country_from_trusted_proxy_and_normalizes_case(): void
    {
        config(['services.geoip.trusted_proxies' => ['173.245.48.0/20']]);
        $session = app(AnalyticsService::class)->startSession();
        $visitorId = $session->visitor_id;

        $this->withoutMiddleware(TrackVisitorSession::class)
            ->withSession([
                'analytics_visitor_token' => $visitorId,
                'analytics_session_token' => $session->session_token,
            ])
            ->withCookie('_va_visitor', $visitorId)
            ->withCookie('_va_session', $session->session_token)
            ->withServerVariables([
                'REMOTE_ADDR' => '173.245.48.50', // Configured trusted proxy IP
                'HTTP_CF_IPCOUNTRY' => 'de', // Lowercase from header
            ])
            ->postJson('/api/analytics/events', [
                'event_name' => 'page_view',
                'visitor_id' => $visitorId,
                'page' => '/pricing',
            ])
            ->assertOk();

        $event = AnalyticsEvent::where('visitor_id', $visitorId)->first();
        $this->assertNotNull($event);
        $this->assertEquals('DE', $event->metadata['detected_country_code']);
        $this->assertArrayNotHasKey('ip', $event->metadata);
    }

    /** @test */
    public function test_untrusted_client_country_header_is_ignored(): void
    {
        config(['services.geoip.trusted_proxies' => ['173.245.48.0/20']]);
        $session = app(AnalyticsService::class)->startSession();
        $visitorId = $session->visitor_id;

        $this->withoutMiddleware(TrackVisitorSession::class)
            ->withSession([
                'analytics_visitor_token' => $visitorId,
                'analytics_session_token' => $session->session_token,
            ])
            ->withCookie('_va_visitor', $visitorId)
            ->withCookie('_va_session', $session->session_token)
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.195', // Untrusted client
                'HTTP_X_COUNTRY_CODE' => 'FR',
            ])
            ->postJson('/api/analytics/events', [
                'event_name' => 'page_view',
                'visitor_id' => $visitorId,
                'page' => '/pricing',
            ])
            ->assertOk();

        $event = AnalyticsEvent::where('visitor_id', $visitorId)->first();
        $this->assertNotNull($event);
        $this->assertNotEquals('FR', $event->metadata['detected_country_code'] ?? null);
    }

    /** @test */
    public function test_untrusted_client_cannot_poison_arbitrary_visitor_identity(): void
    {
        $legitimateSession = app(AnalyticsService::class)->startSession();
        $spoofedVisitorId = (string) Str::uuid();

        // Client attempts to POST an event claiming to be a different visitor UUID
        $this->withSession(['visitor_id' => $legitimateSession->visitor_id])
            ->postJson('/api/analytics/events', [
                'event_name' => 'page_view',
                'visitor_id' => $spoofedVisitorId,
                'page' => '/pricing',
            ]);

        // System must reject or bind to verified session visitor_id, never to spoofed ID
        $this->assertDatabaseMissing('analytics_events', [
            'visitor_id' => $spoofedVisitorId,
        ]);
    }

    /** @test */
    public function test_unresolvable_country_normalizes_to_zz_in_daily_metrics(): void
    {
        $visitorId = (string) Str::uuid();

        Visitor::create([
            'visitor_id' => $visitorId,
            'detected_country_code' => null,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Session::create([
            'session_id' => (string) Str::uuid(),
            'visitor_id' => $visitorId,
            'detected_country_code' => null,
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        $cairoDate = CarbonImmutable::now('Africa/Cairo')->toDateString();

        Artisan::call('analytics:aggregate-daily-country', ['--date' => $cairoDate]);

        $this->assertDatabaseHas('daily_country_metrics', [
            'metric_date' => $cairoDate,
            'country_code' => 'ZZ',
        ]);
    }

    /** @test */
    public function test_visitor_acquisition_country_remains_immutable(): void
    {
        $visitorId = (string) Str::uuid();
        $service = app(AnalyticsService::class);

        $service->recordSession($visitorId, 'DE');
        $this->assertEquals('DE', Visitor::where('visitor_id', $visitorId)->value('detected_country_code'));

        // Second session weeks later from EG
        $service->recordSession($visitorId, 'EG');

        // Acquisition country must remain DE
        $this->assertEquals('DE', Visitor::where('visitor_id', $visitorId)->value('detected_country_code'));
    }

    /** @test */
    public function test_booking_country_is_snapshotted_from_session_and_immutable(): void
    {
        $visitorId = (string) Str::uuid();
        $service = app(AnalyticsService::class);

        // Acquired in DE, Session 2 in EG
        $service->recordSession($visitorId, 'DE');
        $session = $service->recordSession($visitorId, 'EG');

        $sessionType = SessionType::query()->create([
            'title' => 'Country snapshot test',
            'slug' => 'country-snapshot-'.Str::uuid(),
            'duration_minutes' => 60,
            'price' => 25,
            'currency' => 'USD',
            'active' => true,
        ]);
        $startCairo = CarbonImmutable::now('Africa/Cairo')->addDays(5)->startOfDay()->setTime(10, 0);
        AvailabilityRule::query()->create([
            'weekday' => $startCairo->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '16:00:00',
            'session_duration_minutes' => 60,
            'buffer_minutes' => 0,
            'min_notice_hours' => 0,
            'max_horizon_days' => 90,
            'enabled' => true,
        ]);
        $visitorToken = $visitorId;
        $holdSessionToken = 'country-snapshot-hold-'.Str::uuid();
        $startUtc = $startCairo->setTimezone('UTC');
        $endUtc = $startUtc->addHour();
        $hold = app(BookingHoldService::class)->acquireHold(
            visitorToken: $visitorToken,
            sessionToken: $holdSessionToken,
            sessionType: $sessionType,
            startUtc: $startUtc,
            endUtc: $endUtc,
            analyticsVisitorToken: $visitorId,
            analyticsSessionToken: $session->session_token,
        );

        // Booking finalized through the same authenticated hold path as a real public request.
        $booking = app(BookingService::class)->createPublicBooking([
            'session_type_id' => $sessionType->id,
            'customer_timezone' => 'Africa/Cairo',
            'customer_name' => 'Country Snapshot Student',
            'first_name' => 'Country Snapshot',
            'last_name' => 'Student',
            'customer_email' => 'country-snapshot-'.Str::uuid().'@boltlanding.test',
            'customer_phone' => '+201000000008',
            'date_of_birth' => '1990-01-01',
            'visitor_token' => $visitorToken,
            'session_token' => $holdSessionToken,
            'hold_id' => $hold->id,
            'hold_token' => $hold->hold_token,
            'analytics_visitor_token' => $visitorId,
            'analytics_session_token' => $session->session_token,
            'start_at_utc' => $startUtc->addDays(2),
            'end_at_utc' => $endUtc->addDays(2),
            // Caller attempts to pass conflicting country parameter.
            'detected_country_code' => 'US',
            'idempotency_key' => 'country-snapshot-booking-'.Str::uuid(),
        ]);

        // Service derives EG from session, ignoring caller-supplied 'US'
        $this->assertEquals('EG', $booking->detected_country_code);
        $this->assertSame($startUtc->toDateTimeString(), $booking->start_at_utc->setTimezone('UTC')->toDateTimeString());

        // Later session in DE does not alter historical booking snapshot
        $service->recordSession($visitorId, 'DE');
        $booking->refresh();
        $this->assertEquals('EG', $booking->detected_country_code);
    }

    /** @test */
    public function test_missing_mmdb_fails_gracefully_to_null(): void
    {
        config(['services.geoip.database_path' => storage_path('geoip/non_existent_database.mmdb')]);

        $geoService = new GeoIpService;
        $code = $geoService->getCountryCode('8.8.8.8');

        $this->assertNull($code);
    }

    /** @test */
    public function test_daily_country_metrics_aggregation_is_idempotent(): void
    {
        $service = app(AnalyticsService::class);
        $v1 = (string) Str::uuid();
        $v2 = (string) Str::uuid();

        $service->recordSession($v1, 'DE');
        $service->recordSession($v2, 'US');

        $cairoDate = CarbonImmutable::now('Africa/Cairo')->toDateString();

        // Run once
        Artisan::call('analytics:aggregate-daily-country', ['--date' => $cairoDate]);
        $countAfterFirst = DailyCountryMetric::where('metric_date', $cairoDate)->count();

        // Run twice
        Artisan::call('analytics:aggregate-daily-country', ['--date' => $cairoDate]);
        $countAfterSecond = DailyCountryMetric::where('metric_date', $cairoDate)->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
        $this->assertDatabaseHas('daily_country_metrics', [
            'metric_date' => $cairoDate,
            'country_code' => 'DE',
            'sessions' => 1,
        ]);
        $this->assertDatabaseHas('daily_country_metrics', [
            'metric_date' => $cairoDate,
            'country_code' => 'US',
            'sessions' => 1,
        ]);
    }

    /** @test */
    public function test_admin_country_audience_table_renders_country_names_and_unknown_label(): void
    {
        $cairoDate = CarbonImmutable::now('Africa/Cairo')->subDay()->toDateString();
        DailyCountryMetric::create([
            'metric_date' => $cairoDate,
            'country_code' => 'DE',
            'unique_visitors' => 2,
            'sessions' => 3,
            'booking_cta_clicks' => 1,
            'bookings_completed' => 1,
            'resource_requests' => 0,
        ]);
        DailyCountryMetric::create([
            'metric_date' => $cairoDate,
            'country_code' => 'ZZ',
            'unique_visitors' => 0,
            'sessions' => 0,
            'booking_cta_clicks' => 0,
            'bookings_completed' => 0,
            'resource_requests' => 0,
        ]);

        $admin = Administrator::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->actingAs($admin)->get('/admin/analytics?range=7d');

        $response->assertOk()
            ->assertSee('Germany (DE)')
            ->assertSee('Unknown / Unresolved (ZZ)');
    }

    /** @test */
    public function test_daily_country_metrics_exclude_visitors_marked_as_bots(): void
    {
        $visitor = Visitor::create([
            'visitor_token' => (string) Str::uuid(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'is_bot' => true,
            'detected_country_code' => 'DE',
        ]);

        VisitorSession::create([
            'session_token' => (string) Str::uuid(),
            'visitor_id' => $visitor->id,
            'started_at' => now(),
            'last_activity_at' => now(),
            'is_bot' => false,
            'detected_country_code' => 'DE',
        ]);

        $cairoDate = CarbonImmutable::now('Africa/Cairo')->toDateString();
        Artisan::call('analytics:aggregate-daily-country', ['--date' => $cairoDate]);

        $this->assertDatabaseMissing('daily_country_metrics', [
            'metric_date' => $cairoDate,
            'country_code' => 'DE',
        ]);
        $this->assertDatabaseHas('daily_country_metrics', [
            'metric_date' => $cairoDate,
            'country_code' => 'ZZ',
            'unique_visitors' => 0,
            'sessions' => 0,
        ]);
    }

    /** @test */
    public function test_daily_country_metrics_exclude_events_linked_to_bot_sessions(): void
    {
        $visitor = Visitor::create([
            'visitor_token' => (string) Str::uuid(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'is_bot' => true,
            'detected_country_code' => 'DE',
        ]);
        $session = VisitorSession::create([
            'session_token' => (string) Str::uuid(),
            'visitor_id' => $visitor->id,
            'started_at' => now(),
            'last_activity_at' => now(),
            'is_bot' => true,
            'detected_country_code' => 'DE',
        ]);
        AnalyticsEvent::create([
            'event_name' => 'booking_cta_clicked',
            'visitor_token' => $visitor->visitor_token,
            'session_token' => $session->session_token,
            'metadata' => ['detected_country_code' => 'DE'],
            'is_bot' => false,
            'created_at' => now(),
        ]);

        $cairoDate = CarbonImmutable::now('Africa/Cairo')->toDateString();
        Artisan::call('analytics:aggregate-daily-country', ['--date' => $cairoDate]);

        $this->assertDatabaseMissing('daily_country_metrics', [
            'metric_date' => $cairoDate,
            'country_code' => 'DE',
        ]);
    }

    /** @test */
    public function test_daily_country_metrics_exclude_events_linked_directly_to_bot_visitors(): void
    {
        $visitor = Visitor::create([
            'visitor_token' => (string) Str::uuid(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'is_bot' => true,
            'detected_country_code' => 'DE',
        ]);

        AnalyticsEvent::create([
            'event_name' => 'booking_cta_clicked',
            'visitor_token' => $visitor->visitor_token,
            'metadata' => ['detected_country_code' => 'DE'],
            'is_bot' => false,
            'created_at' => now(),
        ]);

        $cairoDate = CarbonImmutable::now('Africa/Cairo')->toDateString();
        Artisan::call('analytics:aggregate-daily-country', ['--date' => $cairoDate]);

        $this->assertDatabaseHas('daily_country_metrics', [
            'metric_date' => $cairoDate,
            'country_code' => 'ZZ',
            'booking_cta_clicks' => 0,
        ]);
        $this->assertDatabaseMissing('daily_country_metrics', [
            'metric_date' => $cairoDate,
            'country_code' => 'DE',
            'booking_cta_clicks' => 1,
        ]);
    }

    /** @test */
    public function test_daily_country_metrics_exclude_completed_bookings_linked_to_bot_visitors(): void
    {
        $visitor = Visitor::create([
            'visitor_token' => (string) Str::uuid(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'is_bot' => true,
            'detected_country_code' => 'DE',
        ]);

        Booking::create([
            'contact_id' => Contact::create([
                'name' => 'Bot',
                'email' => 'bot@example.com',
            ])->id,
            'session_type_id' => SessionType::create([
                'title' => 'Bot Test Session',
                'slug' => 'bot-test-session',
                'duration_minutes' => 60,
                'price' => 25,
                'currency' => 'USD',
                'active' => false,
            ])->id,
            'visitor_token' => $visitor->visitor_token,
            'detected_country_code' => 'DE',
            'status' => 'confirmed',
            'start_at_utc' => now()->addDay(),
            'end_at_utc' => now()->addDay()->addHour(),
            'customer_timezone' => 'Africa/Cairo',
            'business_timezone' => 'Africa/Cairo',
            'business_local_date_at_booking' => now('Africa/Cairo')->addDay()->toDateString(),
            'business_local_start_time_at_booking' => '10:00:00',
            'business_local_end_time_at_booking' => '11:00:00',
            'customer_local_date_at_booking' => now('Africa/Cairo')->addDay()->toDateString(),
            'customer_local_start_time_at_booking' => '10:00:00',
            'customer_local_end_time_at_booking' => '11:00:00',
            'business_utc_offset_at_booking' => 'UTC+3',
            'customer_utc_offset_at_booking' => 'UTC+3',
            'idempotency_key' => 'bot-country-idempotency',
            'confirmation_token' => 'bot-country-confirmation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cairoDate = CarbonImmutable::now('Africa/Cairo')->toDateString();
        Artisan::call('analytics:aggregate-daily-country', ['--date' => $cairoDate]);

        $this->assertDatabaseHas('daily_country_metrics', [
            'metric_date' => $cairoDate,
            'country_code' => 'ZZ',
            'bookings_completed' => 0,
        ]);
        $this->assertDatabaseMissing('daily_country_metrics', [
            'metric_date' => $cairoDate,
            'country_code' => 'DE',
            'bookings_completed' => 1,
        ]);
    }

    /** @test */
    public function test_daily_metrics_exclude_events_linked_to_bot_visitors_even_when_event_flag_is_false(): void
    {
        $visitor = Visitor::create([
            'visitor_token' => (string) Str::uuid(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'is_bot' => true,
        ]);
        $session = VisitorSession::create([
            'session_token' => (string) Str::uuid(),
            'visitor_id' => $visitor->id,
            'started_at' => now(),
            'last_activity_at' => now(),
            'is_bot' => true,
        ]);
        AnalyticsEvent::create([
            'event_name' => 'page_view',
            'visitor_token' => $visitor->visitor_token,
            'session_token' => $session->session_token,
            'is_bot' => false,
            'created_at' => now(),
        ]);

        $cairoDate = CarbonImmutable::now('Africa/Cairo')->toDateString();
        Artisan::call('analytics:aggregate-daily', ['--date' => $cairoDate]);

        $this->assertDatabaseHas('daily_metrics', [
            'metric_date' => $cairoDate,
            'metric_name' => 'unique_visitors',
            'count' => 0,
        ]);
        $this->assertDatabaseMissing('daily_metrics', [
            'metric_date' => $cairoDate,
            'metric_name' => 'page_view',
            'count' => 1,
        ]);
    }

    /** @test */
    public function test_session_cookie_cannot_cross_visitor_identity(): void
    {
        $visitorA = (string) Str::uuid();
        $visitorB = (string) Str::uuid();

        $firstResponse = $this->withCookie('_va_visitor', $visitorA)->get('/');
        $sessionA = $firstResponse->getCookie('_va_session')?->getValue();
        $this->assertNotNull($sessionA);

        $this->withCookies([
            '_va_visitor' => $visitorB,
            '_va_session' => $sessionA,
        ])->get('/')->assertOk();

        $visitorBRecord = \App\Domains\Analytics\Models\Visitor::where('visitor_token', $visitorB)->firstOrFail();
        $sessionB = VisitorSession::where('visitor_id', $visitorBRecord->id)->latest('id')->firstOrFail();

        $this->assertNotSame($sessionA, $sessionB->session_token);
        $this->assertSame($visitorBRecord->id, (int) $sessionB->visitor_id);
    }

    /** @test */
    public function test_event_deduplication_scopes_only_the_contract_events(): void
    {
        $service = app(\App\Domains\Analytics\Services\AnalyticsService::class);
        $session = $service->startSession((string) Str::uuid());

        $this->assertNotNull($service->track('session_started', sessionToken: $session->session_token));
        $this->assertNull($service->track('session_started', sessionToken: $session->session_token));

        $this->assertNotNull($service->track('resource_gate_viewed', ['resource_id' => 1], sessionToken: $session->session_token));
        $this->assertNull($service->track('resource_gate_viewed', ['resource_id' => 2], sessionToken: $session->session_token));

        $this->assertNotNull($service->track('social_link_clicked', ['platform' => 'whatsapp'], sessionToken: $session->session_token));
        $this->assertNotNull($service->track('social_link_clicked', ['platform' => 'whatsapp'], sessionToken: $session->session_token));
    }

    /** @test */
    public function test_marketing_touch_rapid_duplicates_are_atomic_but_distinct_touches_survive(): void
    {
        $visitor = \App\Domains\Analytics\Models\Visitor::create([
            'visitor_token' => (string) Str::uuid(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        $service = app(\App\Domains\Analytics\Services\AnalyticsService::class);
        $base = CarbonImmutable::parse('2026-09-21 10:00:00', 'UTC');

        $first = $service->recordMarketingTouch($visitor, 'session-a', 'google', 'cpc', 'arabic', 'one', null, 'https://google.com', $base);
        $duplicate = $service->recordMarketingTouch($visitor, 'session-a', 'google', 'cpc', 'arabic', 'one', null, 'https://google.com', $base->addSeconds(2));
        $differentCampaign = $service->recordMarketingTouch($visitor, 'session-a', 'google', 'email', 'arabic', 'one', null, 'https://google.com', $base->addSeconds(2));
        $later = $service->recordMarketingTouch($visitor, 'session-a', 'google', 'cpc', 'arabic', 'one', null, 'https://google.com', $base->addSeconds(6));

        $this->assertNotNull($first);
        $this->assertSame($first->id, $duplicate?->id);
        $this->assertNotSame($first->id, $differentCampaign?->id);
        $this->assertNotSame($first->id, $later?->id);
        $this->assertSame(3, MarketingTouch::where('visitor_id', $visitor->id)->count());
    }
}
