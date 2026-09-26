<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Analytics\Models\DailyMetric;
use App\Domains\Analytics\Models\Visitor;
use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\Analytics\Services\EngagementCounterService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EngagementCountersTest extends TestCase
{
    use RefreshDatabase;

    protected EngagementCounterService $counterService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->counterService = app(EngagementCounterService::class);
    }

    public function test_live_users_count_reflects_sessions_active_in_last_60_seconds(): void
    {
        $visitorA = Visitor::create(['visitor_token' => 'tok_a', 'user_agent' => 'UA', 'detected_country_code' => 'US', 'first_seen_at' => now(), 'last_seen_at' => now()]);
        $visitorB = Visitor::create(['visitor_token' => 'tok_b', 'user_agent' => 'UA', 'detected_country_code' => 'GB', 'first_seen_at' => now(), 'last_seen_at' => now()]);

        // Session A: Active 30 seconds ago
        VisitorSession::create([
            'session_token' => 'sess-tok-a',
            'session_uuid' => 'sess-a',
            'visitor_id' => $visitorA->id,
            'started_at' => now()->subMinutes(10),
            'last_activity_at' => now()->subSeconds(30),
            'page_views_count' => 3,
        ]);

        // Session B: Active 10 seconds ago
        VisitorSession::create([
            'session_token' => 'sess-tok-b',
            'session_uuid' => 'sess-b',
            'visitor_id' => $visitorB->id,
            'started_at' => now()->subMinutes(5),
            'last_activity_at' => now()->subSeconds(10),
            'page_views_count' => 2,
        ]);

        // Session C: Inactive (90 seconds ago)
        VisitorSession::create([
            'session_token' => 'sess-tok-c',
            'session_uuid' => 'sess-c',
            'visitor_id' => $visitorA->id,
            'started_at' => now()->subHours(1),
            'last_activity_at' => now()->subSeconds(90),
            'page_views_count' => 1,
        ]);

        $count = $this->counterService->getLiveUsersCount();
        $this->assertEquals(2, $count);
    }

    public function test_previous_month_traffic_uses_cairo_month_boundaries_converted_to_utc(): void
    {
        $cairoNow = CarbonImmutable::now('Africa/Cairo');
        $cairoLastMonthStart = $cairoNow->subMonthNoOverflow()->startOfMonth();
        $utcStart = $cairoLastMonthStart->setTimezone('UTC');

        $visitor = Visitor::create(['visitor_token' => 'tok_m', 'user_agent' => 'UA', 'detected_country_code' => 'EG', 'first_seen_at' => $utcStart, 'last_seen_at' => $utcStart]);

        // Session strictly inside last month (Cairo)
        VisitorSession::create([
            'session_token' => 'sess-tok-lm',
            'session_uuid' => 'sess-last-month',
            'visitor_id' => $visitor->id,
            'started_at' => $utcStart->addDays(2),
            'last_activity_at' => $utcStart->addDays(2)->addMinutes(15),
            'page_views_count' => 5,
        ]);

        // Session outside (2 months ago)
        VisitorSession::create([
            'session_token' => 'sess-tok-2m',
            'session_uuid' => 'sess-two-months-ago',
            'visitor_id' => $visitor->id,
            'started_at' => $utcStart->subDays(5),
            'last_activity_at' => $utcStart->subDays(5)->addMinutes(10),
            'page_views_count' => 2,
        ]);

        $traffic = $this->counterService->getPreviousMonthTraffic();
        $this->assertEquals(1, $traffic['unique_visitors']);
        $this->assertEquals(1, $traffic['sessions']);
    }

    public function test_collective_learning_activity_aggregates_completed_lessons_and_study_dwell(): void
    {
        $sessionType = SessionType::create([
            'title' => 'Arabic Practice',
            'slug' => 'arabic-practice',
            'duration_minutes' => 60,
            'price' => 30,
            'currency' => 'USD',
            'active' => true,
        ]);

        $contact = Contact::create([
            'name' => 'Sara Student',
            'email' => 'sara@example.com',
            'phone' => '+201000000002',
            'timezone' => 'Africa/Cairo',
        ]);

        $cairoNow = CarbonImmutable::now('Africa/Cairo');
        $yesterdayCairo = $cairoNow->subDays(2)->setTime(14, 0, 0);
        $yesterdayUtc = $yesterdayCairo->setTimezone('UTC');

        $snapshot = app(TimezoneService::class)->createBookingSnapshot(
            $yesterdayUtc,
            $yesterdayUtc->addHour(),
            'Africa/Cairo',
            'Africa/Cairo'
        );

        // Completed booking: 60 minutes = 1.0 hour
        Booking::create(array_merge($snapshot, [
            'session_type_id' => $sessionType->id,
            'contact_id' => $contact->id,
            'status' => 'completed',
            'confirmation_token' => 'BK-TEST-1',
            'idempotency_key' => 'idem-test-1',
        ]));

        // Dwell metric: 7200 seconds = 2.0 hours
        DailyMetric::create([
            'metric_date' => $yesterdayCairo->toDateString(),
            'metric_name' => 'section_dwell_seconds',
            'dimension_key' => 'section',
            'dimension_value' => 'curriculum',
            'count' => 7200,
        ]);

        $activity = $this->counterService->getCollectiveLearningActivity(7);
        $this->assertEquals(1.0, $activity['lesson_hours']);
        $this->assertEquals(2.0, $activity['study_dwell_hours']);
        $this->assertEquals(3.0, $activity['total_hours']);
        $this->assertStringContainsString('3 hours', $activity['formatted_time']);
    }

    public function test_public_counters_payload_and_flat_cache(): void
    {
        Setting::set('counters.live_users.public_enabled', true, 'counters', true);
        Setting::set('counters.live_users.template', '{count} online now', 'counters', true);

        Cache::forget(EngagementCounterService::CACHE_KEY);

        $payload = $this->counterService->getCachedPublicPayload();
        $this->assertTrue(Cache::has(EngagementCounterService::CACHE_KEY));
        $this->assertTrue($payload['live_users']['enabled']);
        $this->assertStringContainsString('online now', $payload['live_users']['text']);
    }

    public function test_admin_settings_updates_social_proof_counters(): void
    {
        $admin = Administrator::create([
            'email' => 'admin@boltlanding.test',
            'name' => 'Admin User',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $response = $this->actingAs($admin, 'web')->post('/admin/settings', [
            'action' => 'publish',
            'site_name' => 'Updated Site',
            'business_timezone' => 'Africa/Cairo',
            'default_language' => 'en',
            'hero_title' => 'Updated Title',
            'hero_subtitle' => 'Updated Subtitle',
            'cancellation_policy' => '4 hours policy',
            'booking_cancellation_cutoff_hours' => 4,
            'booking_reschedule_cutoff_hours' => 24,
            'rescheduling_policy' => '24 hours policy',
            'booking_instructions' => 'Follow steps',
            // Phase 2 Counters
            'counters_learning_hours_public_enabled' => '1',
            'counters_learning_hours_window_days' => 14,
            'counters_learning_hours_headline' => 'Our students completed...',
            'counters_learning_hours_subtitle' => 'across Egyptian lessons',
            'counters_monthly_traffic_public_enabled' => '1',
            'counters_monthly_traffic_source' => 'sessions',
            'counters_monthly_traffic_template_sessions' => '{count} total sessions',
            'counters_live_users_public_enabled' => '1',
            'counters_live_users_template' => '{count} learners active',
        ]);

        $response->assertRedirect();
        $this->assertTrue((bool) Setting::get('counters.learning_hours.public_enabled'));
        $this->assertEquals(14, (int) Setting::get('counters.learning_hours.window_days'));
        $this->assertEquals('Our students completed...', Setting::get('counters.learning_hours.headline'));
        $this->assertEquals('sessions', Setting::get('counters.monthly_traffic.source'));
    }
}
