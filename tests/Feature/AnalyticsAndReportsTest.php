<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Models\Visitor;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingHoldService;
use App\Domains\Booking\Services\BookingService;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Games\Models\Game;
use App\Domains\Reporting\Services\ReportService;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsAndReportsTest extends TestCase
{
    use RefreshDatabase;

    protected Administrator $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Administrator::create([
            'name' => 'Dr. Ahmad',
            'email' => 'ahmad@example.com',
            'password' => bcrypt('SecurePassword123!'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }

    public function test_visitor_tracking_middleware_sets_cookies_and_records_page_view(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        // Cookies should be queued
        $response->assertCookie('_va_visitor');
        $response->assertCookie('_va_session');

        $this->assertDatabaseCount('visitors', 1);
        $this->assertDatabaseCount('visitor_sessions', 1);

        $visitor = Visitor::first();
        $this->assertNotNull($visitor->visitor_token);
        $this->assertFalse($visitor->is_bot);

        // Page view should be recorded
        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'page_view',
            'visitor_token' => $visitor->visitor_token,
            'is_bot' => false,
        ]);
    }

    public function test_bot_user_agents_are_classified_as_bots(): void
    {
        $response = $this->withHeaders([
            'User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
        ])->get('/');

        $response->assertStatus(200);

        $this->assertDatabaseHas('visitors', [
            'is_bot' => true,
        ]);

        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'page_view',
            'is_bot' => true,
        ]);
    }

    public function test_utm_parameters_are_captured_on_session_and_events(): void
    {
        $response = $this->get('/?utm_source=youtube&utm_medium=video_desc&utm_campaign=survival_masri&utm_content=lesson_1');
        $response->assertStatus(200);

        $this->assertDatabaseHas('visitor_sessions', [
            'utm_source' => 'youtube',
            'utm_medium' => 'video_desc',
            'utm_campaign' => 'survival_masri',
            'utm_content' => 'lesson_1',
        ]);

        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'page_view',
            'utm_source' => 'youtube',
            'utm_campaign' => 'survival_masri',
        ]);
    }

    public function test_client_analytics_event_endpoint_records_allowed_event(): void
    {
        $response = $this->postJson(route('analytics.track'), [
            'event_name' => 'whatsapp_clicked',
            'page' => 'http://boltlanding.test/book',
            'metadata' => ['target' => 'https://wa.me/201012345678'],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'whatsapp_clicked',
            'page' => 'http://boltlanding.test/book',
        ]);
    }

    public function test_client_analytics_event_endpoint_rejects_server_only_events(): void
    {
        $response = $this->postJson(route('analytics.track'), [
            'event_name' => 'booking_completed',
            'page' => 'http://boltlanding.test/book',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['status' => 'rejected']);

        $this->assertDatabaseMissing('analytics_events', [
            'event_name' => 'booking_completed',
        ]);
    }

    public function test_event_deduplication_prevents_rapid_fire_duplicate_actions(): void
    {
        /** @var AnalyticsService $service */
        $service = app(AnalyticsService::class);

        $sessionToken = 'test-session-token-'.uniqid();
        $visitorToken = 'test-visitor-token-'.uniqid();

        // First track should succeed
        $event1 = $service->track(
            eventName: 'resource_downloaded',
            metadata: ['resource_id' => 10],
            visitorToken: $visitorToken,
            sessionToken: $sessionToken
        );

        $this->assertNotNull($event1);

        // Immediate identical second track should be deduplicated (within 5 seconds)
        $event2 = $service->track(
            eventName: 'resource_downloaded',
            metadata: ['resource_id' => 10],
            visitorToken: $visitorToken,
            sessionToken: $sessionToken
        );

        $this->assertNull($event2);
    }

    public function test_daily_analytics_aggregation_command(): void
    {
        $targetDate = '2026-09-10';

        // Seed some events on 2026-09-10
        AnalyticsEvent::create([
            'event_name' => 'page_view',
            'visitor_token' => 'vis-1',
            'session_token' => 'sess-1',
            'page' => '/resources',
            'utm_source' => 'google',
            'is_bot' => false,
            'created_at' => "{$targetDate} 10:00:00",
        ]);

        AnalyticsEvent::create([
            'event_name' => 'page_view',
            'visitor_token' => 'vis-2',
            'session_token' => 'sess-2',
            'page' => '/book',
            'utm_source' => 'youtube',
            'is_bot' => false,
            'created_at' => "{$targetDate} 11:30:00",
        ]);

        AnalyticsEvent::create([
            'event_name' => 'booking_completed',
            'visitor_token' => 'vis-2',
            'session_token' => 'sess-2',
            'page' => '/book',
            'utm_source' => 'youtube',
            'is_bot' => false,
            'created_at' => "{$targetDate} 11:35:00",
        ]);

        $this->artisan("analytics:aggregate-daily --date={$targetDate}")
            ->assertSuccessful();

        $this->assertDatabaseHas('daily_metrics', [
            'metric_date' => $targetDate,
            'metric_name' => 'unique_visitors',
            'count' => 2,
        ]);

        $this->assertDatabaseHas('daily_metrics', [
            'metric_date' => $targetDate,
            'metric_name' => 'booking_completed',
            'count' => 1,
        ]);
    }

    public function test_admin_can_view_analytics_dashboard_with_funnels(): void
    {
        $response = $this->actingAs($this->admin, 'web')->get(route('admin.analytics'));
        $response->assertStatus(200);
        $response->assertViewHas('primaryFunnel');
        $response->assertViewHas('resourceFunnel');
        $response->assertViewHas('gameFunnel');
        $response->assertViewHas('activeVisitorsCount');
        $response->assertSee('Primary Business Funnel');
    }

    public function test_admin_can_view_the_five_fixed_reports(): void
    {
        $trafficResp = $this->actingAs($this->admin, 'web')->get(route('admin.reports.index', ['type' => 'traffic']));
        $trafficResp->assertStatus(200);
        $trafficResp->assertSee('Traffic & Visitors', false);

        $bookingsResp = $this->actingAs($this->admin, 'web')->get(route('admin.reports.index', ['type' => 'bookings']));
        $bookingsResp->assertStatus(200);
        $bookingsResp->assertSee('Bookings Ledger', false);

        $resourcesResp = $this->actingAs($this->admin, 'web')->get(route('admin.reports.index', ['type' => 'resources']));
        $resourcesResp->assertStatus(200);
        $resourcesResp->assertSee('Resource Downloads', false);

        $socialResp = $this->actingAs($this->admin, 'web')->get(route('admin.reports.index', ['type' => 'social']));
        $socialResp->assertStatus(200);
        $socialResp->assertSee('Social & Messaging Clicks', false);

        $eventsResp = $this->actingAs($this->admin, 'web')->get(route('admin.reports.index', ['type' => 'events']));
        $eventsResp->assertStatus(200);
        $eventsResp->assertSee('Raw Events Log', false);
    }

    public function test_admin_can_export_csv_with_formula_injection_defense(): void
    {
        $sessionType = SessionType::create([
            'title' => 'Conversational Egyptian',
            'slug' => 'conversational-egyptian',
            'duration_minutes' => 50,
            'price' => 3000,
            'currency' => 'USD',
            'active' => true,
        ]);

        $maliciousContact = Contact::create([
            'name' => '=HYPERLINK("http://evil.com","Click Me")',
            'email' => '+123456@example.com',
            'display_email' => '+123456@example.com',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Booking::create([
            'confirmation_token' => '@BOOK123',
            'contact_id' => $maliciousContact->id,
            'session_type_id' => $sessionType->id,
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'UTC',
            'business_local_date_at_booking' => now('Africa/Cairo')->toDateString(),
            'business_local_start_time_at_booking' => '14:00:00',
            'business_local_end_time_at_booking' => '14:50:00',
            'customer_local_date_at_booking' => now('UTC')->toDateString(),
            'customer_local_start_time_at_booking' => '11:00:00',
            'customer_local_end_time_at_booking' => '11:50:00',
            'business_utc_offset_at_booking' => '+03:00',
            'customer_utc_offset_at_booking' => '+00:00',
            'start_at_utc' => now()->subHour(),
            'end_at_utc' => now()->subMinutes(10),
            'status' => 'confirmed',
            'idempotency_key' => 'idem-csv-123',
            'source' => '-hack-source',
        ]);

        $response = $this->actingAs($this->admin, 'web')->get(route('admin.reports.export', [
            'type' => 'bookings',
            'format' => 'csv',
        ]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringContainsString("'+123456", $content);
        $this->assertStringContainsString("'@BOOK123", $content);
        $this->assertStringContainsString("'-hack-source", $content);
    }

    public function test_admin_can_export_xlsx_report(): void
    {
        $response = $this->actingAs($this->admin, 'web')->get(route('admin.reports.export', [
            'type' => 'traffic',
            'format' => 'xlsx',
        ]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_landing_utm_survives_navigation_into_resource_request(): void
    {
        $category = ResourceCategory::create([
            'name' => 'Cheatsheets',
            'slug' => 'cheatsheets',
            'sort_order' => 1,
        ]);

        $resource = Resource::create([
            'category_id' => $category->id,
            'title' => 'Verb Conjugations Guide',
            'slug' => 'verb-conjugations-guide',
            'summary' => 'Comprehensive guide to Egyptian verbs',
            'file_path' => 'resources/test.pdf',
            'file_name' => 'test.pdf',
            'file_type' => 'pdf',
            'file_size_bytes' => 1024,
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        // 1. Visitor lands on site with UTM tags
        $landingResponse = $this->get('/?utm_source=tiktok&utm_medium=video&utm_campaign=cairo_basics');
        $landingResponse->assertOk();

        // 2. Visitor navigates to resource and requests access (without repeating UTMs in URL)
        $requestResponse = $this->post(route('resources.request', ['slug' => $resource->slug]), [
            'email' => 'fatima@example.com',
            'name' => 'Fatima',
        ]);
        $requestResponse->assertRedirect();

        // Assert contact acquired UTM attribution
        $this->assertDatabaseHas('contacts', [
            'email' => 'fatima@example.com',
            'name' => 'Fatima',
            'utm_source' => 'tiktok',
            'utm_medium' => 'video',
            'utm_campaign' => 'cairo_basics',
        ]);

        // Assert resource request acquired UTM attribution
        $this->assertDatabaseHas('resource_requests', [
            'resource_id' => $resource->id,
            'source' => 'tiktok',
            'medium' => 'video',
            'campaign' => 'cairo_basics',
        ]);

        // Assert resource_requested analytics event carries UTM attribution
        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'resource_requested',
            'utm_source' => 'tiktok',
            'utm_medium' => 'video',
            'utm_campaign' => 'cairo_basics',
        ]);
    }

    public function test_landing_utm_survives_navigation_into_booking(): void
    {
        $sessionType = SessionType::create([
            'title' => 'Survival Egyptian',
            'slug' => 'survival-egyptian',
            'duration_minutes' => 60,
            'price' => 50,
            'currency' => 'USD',
            'active' => true,
        ]);

        AvailabilityRule::create([
            'weekday' => 1, // Monday
            'start_time' => '10:00:00',
            'end_time' => '18:00:00',
            'enabled' => true,
        ]);

        // 1. Visitor lands with Instagram ad UTMs
        $this->get('/?utm_source=instagram&utm_medium=ad&utm_campaign=egypt_fast_track')
            ->assertOk();

        $sessionToken = session('analytics_session_token');
        $visitorToken = session('analytics_visitor_token');

        // Retrieve valid canonical grid slot
        $availabilityService = app(AvailabilityService::class);
        $slots = $availabilityService->getAvailableSlotsGroupedByDate($sessionType, 'UTC');
        $this->assertNotEmpty($slots);
        $firstDate = array_key_first($slots);
        $firstSlot = $slots[$firstDate][0];
        $slotStart = CarbonImmutable::parse($firstSlot['slot_start_utc']);
        $slotEnd = CarbonImmutable::parse($firstSlot['slot_end_utc']);

        // 2. Acquire hold
        $holdService = app(BookingHoldService::class);
        $hold = $holdService->acquireHold(
            visitorToken: $visitorToken,
            sessionToken: $sessionToken,
            sessionType: $sessionType,
            startUtc: $slotStart,
            endUtc: $slotEnd
        );

        // 3. Confirm public booking
        $bookingService = app(BookingService::class);
        $booking = $bookingService->createPublicBooking([
            'session_type_id' => $sessionType->id,
            'start_at_utc' => $slotStart,
            'end_at_utc' => $slotEnd,
            'customer_timezone' => 'UTC',
            'customer_name' => 'Tarek Student',
            'customer_email' => 'tarek@example.com',
            'idempotency_key' => (string) Str::uuid(),
            'hold_id' => $hold->id,
            'hold_token' => $hold->hold_token,
            'visitor_token' => $visitorToken,
            'session_token' => $sessionToken,
        ]);

        $this->assertNotNull($booking);

        // Assert Contact has UTM attribution
        $this->assertDatabaseHas('contacts', [
            'email' => 'tarek@example.com',
            'utm_source' => 'instagram',
            'utm_campaign' => 'egypt_fast_track',
        ]);

        // Assert Booking has UTM attribution
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'source' => 'instagram',
            'campaign' => 'egypt_fast_track',
        ]);

        // Assert booking_completed analytics event carries UTM attribution
        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'booking_completed',
            'utm_source' => 'instagram',
            'utm_campaign' => 'egypt_fast_track',
        ]);
    }

    public function test_raw_ip_is_absent_from_analytics_events_metadata_and_database(): void
    {
        $this->get('/')->assertOk();

        $events = AnalyticsEvent::all();
        $this->assertNotEmpty($events);

        foreach ($events as $event) {
            // ip_hash must be a valid 64-char sha256 hash
            $this->assertNotNull($event->ip_hash);
            $this->assertEquals(64, strlen($event->ip_hash));

            // metadata must never contain 'ip'
            if ($event->metadata) {
                $this->assertArrayNotHasKey('ip', $event->metadata);
            }
        }
    }

    public function test_bot_exclusion_across_events_and_reports(): void
    {
        $this->withHeaders([
            'User-Agent' => 'SemrushBot/7~bl (+http://www.semrush.com/bot.html)',
        ])->get('/')->assertOk();

        $event = AnalyticsEvent::latest('id')->first();
        $this->assertTrue($event->is_bot);

        $reportService = app(ReportService::class);
        $traffic = $reportService->getTrafficReport(
            now()->subDay()->startOfDay(),
            now()->endOfDay()
        );

        // Bots must be excluded from traffic summary
        $this->assertEquals(0, $traffic['summary']['visitors']);
        $this->assertEquals(0, $traffic['summary']['page_views']);
    }

    public function test_client_event_endpoint_rejects_unknown_and_oversized_metadata(): void
    {
        // 1. Unknown metadata key
        $resUnknown = $this->postJson(route('analytics.track'), [
            'event_name' => 'whatsapp_clicked',
            'metadata' => [
                'target' => 'https://wa.me/2010',
                'secret_role' => 'admin',
            ],
        ]);
        $resUnknown->assertStatus(422);
        $resUnknown->assertJsonFragment(['status' => 'rejected']);

        // 2. Nested metadata
        $resNested = $this->postJson(route('analytics.track'), [
            'event_name' => 'whatsapp_clicked',
            'metadata' => [
                'target' => ['nested' => 'disallowed'],
            ],
        ]);
        $resNested->assertStatus(422);

        // 3. String value > 500 characters
        $resOversized = $this->postJson(route('analytics.track'), [
            'event_name' => 'whatsapp_clicked',
            'metadata' => [
                'target' => str_repeat('A', 501),
            ],
        ]);
        $resOversized->assertStatus(422);

        // 4. Valid allowed metadata succeeds
        $resValid = $this->postJson(route('analytics.track'), [
            'event_name' => 'whatsapp_clicked',
            'metadata' => [
                'target' => 'https://wa.me/201012345678',
                'placement' => 'header',
            ],
        ]);
        $resValid->assertOk();
    }

    public function test_game_controller_tracks_events_via_analytics_service_with_canonical_tokens(): void
    {
        $game = Game::create([
            'title' => 'Arabic Verb Matcher',
            'slug' => 'verb-matcher',
            'description' => 'Match verbs with their past tense',
            'sort_order' => 1,
            'status' => 'available',
        ]);

        // 1. Show game -> game_opened event
        $this->get(route('games.show', ['slug' => $game->slug]))->assertOk();

        $openEvent = AnalyticsEvent::where('event_name', 'game_opened')
            ->whereJsonContains('metadata->game_slug', $game->slug)
            ->first();

        $this->assertNotNull($openEvent);
        $this->assertNotNull($openEvent->visitor_token);
        $this->assertNotNull($openEvent->session_token);

        // 2. Track game action -> game_completed
        $this->postJson(route('games.track', ['slug' => $game->slug]), [
            'action' => 'game_completed',
            'score' => 100,
            'duration_seconds' => 120,
        ])->assertOk();

        $completeEvent = AnalyticsEvent::where('event_name', 'game_completed')
            ->whereJsonContains('metadata->game_slug', $game->slug)
            ->first();

        $this->assertNotNull($completeEvent);
        $this->assertNotNull($completeEvent->visitor_token);
        $this->assertNotNull($completeEvent->session_token);
        $this->assertEquals(100, $completeEvent->metadata['score']);
    }

    public function test_traffic_report_totals_and_top_source_agree_with_underlying_data(): void
    {
        $day1 = '2026-08-01';
        $day2 = '2026-08-02';

        // Day 1: 3 facebook, 1 google
        for ($i = 0; $i < 3; $i++) {
            AnalyticsEvent::create([
                'event_name' => 'page_view',
                'visitor_token' => "vis-d1-fb-{$i}",
                'session_token' => "sess-d1-fb-{$i}",
                'page' => '/',
                'utm_source' => 'facebook',
                'is_bot' => false,
                'created_at' => "{$day1} 10:0{$i}:00",
            ]);
        }
        AnalyticsEvent::create([
            'event_name' => 'page_view',
            'visitor_token' => 'vis-d1-g-1',
            'session_token' => 'sess-d1-g-1',
            'page' => '/',
            'utm_source' => 'google',
            'is_bot' => false,
            'created_at' => "{$day1} 11:00:00",
        ]);

        // Day 2: 1 facebook, 3 google
        AnalyticsEvent::create([
            'event_name' => 'page_view',
            'visitor_token' => 'vis-d2-fb-1',
            'session_token' => 'sess-d2-fb-1',
            'page' => '/',
            'utm_source' => 'facebook',
            'is_bot' => false,
            'created_at' => "{$day2} 09:00:00",
        ]);
        for ($i = 0; $i < 3; $i++) {
            AnalyticsEvent::create([
                'event_name' => 'page_view',
                'visitor_token' => "vis-d2-g-{$i}",
                'session_token' => "sess-d2-g-{$i}",
                'page' => '/',
                'utm_source' => 'google',
                'is_bot' => false,
                'created_at' => "{$day2} 10:0{$i}:00",
            ]);
        }

        $reportService = app(ReportService::class);
        $report = $reportService->getTrafficReport(
            CarbonImmutable::parse($day1)->startOfDay(),
            CarbonImmutable::parse($day2)->endOfDay()
        );

        $rows = $report['rows'];
        // Must have exactly 2 rows (one for each date)
        $this->assertCount(2, $rows);

        $rowDay2 = $rows->firstWhere('report_date', $day2);
        $rowDay1 = $rows->firstWhere('report_date', $day1);

        $this->assertNotNull($rowDay1);
        $this->assertNotNull($rowDay2);

        // Day 1 top source must be facebook
        $this->assertEquals('facebook', $rowDay1->top_source);
        $this->assertEquals(4, $rowDay1->page_views);

        // Day 2 top source must be google
        $this->assertEquals('google', $rowDay2->top_source);
        $this->assertEquals(4, $rowDay2->page_views);

        // Total page views must reconcile
        $this->assertEquals(8, $report['summary']['page_views']);
        $this->assertEquals(8, $rows->sum('page_views'));
    }
}
