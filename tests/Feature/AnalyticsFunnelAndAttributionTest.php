<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Models\DailyMetric;
use App\Domains\Analytics\Models\Visitor;
use App\Domains\Analytics\Models\VisitorFunnelProgression;
use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Analytics\Services\FunnelProgressionService;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingHoldService;
use App\Domains\Booking\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsFunnelAndAttributionTest extends TestCase
{
    use RefreshDatabase;

    protected FunnelProgressionService $funnelService;

    protected AnalyticsService $analyticsService;

    protected SessionType $sessionType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->funnelService = app(FunnelProgressionService::class);
        $this->analyticsService = app(AnalyticsService::class);

        $this->sessionType = SessionType::create([
            'title' => 'Standard Arabic Lesson',
            'slug' => 'standard-lesson',
            'duration_minutes' => 60,
            'price' => 300,
            'currency' => 'EGP',
            'active' => true,
        ]);

        for ($w = 0; $w <= 6; $w++) {
            AvailabilityRule::create([
                'weekday' => $w,
                'start_time' => '00:00:00',
                'end_time' => '23:59:00',
                'buffer_minutes' => 0,
                'enabled' => true,
            ]);
        }
    }

    public function test_funnel_cumulative_progression_counts_each_visitor_once(): void
    {
        $visitor = Visitor::create([
            'visitor_token' => 'vis-cumulative-1',
            'first_seen_at' => '2026-09-10 10:00:00',
            'last_seen_at' => '2026-09-10 10:00:00',
        ]);

        // 1. Visit
        $this->funnelService->recordVisit($visitor, CarbonImmutable::parse('2026-09-10 10:00:00'));

        // 2. CTA
        $this->funnelService->recordStage($visitor, 'booking_cta', CarbonImmutable::parse('2026-09-10 10:05:00'), true);
        // Repeat CTA event: must not inflate
        $this->funnelService->recordStage($visitor, 'booking_cta', CarbonImmutable::parse('2026-09-10 10:06:00'), true);

        // 3. Started
        $this->funnelService->recordStage($visitor, 'booking_started', CarbonImmutable::parse('2026-09-10 10:10:00'), true);
        // Repeat Started event
        $this->funnelService->recordStage($visitor, 'booking_started', CarbonImmutable::parse('2026-09-10 10:11:00'), true);

        // 4. Held
        $this->funnelService->recordStage($visitor, 'slot_held', CarbonImmutable::parse('2026-09-10 10:15:00'), true);

        // 5. Completed
        $this->funnelService->recordStage($visitor, 'booking_completed', CarbonImmutable::parse('2026-09-10 10:20:00'), true);

        $report = $this->funnelService->getCohortFunnel(
            CarbonImmutable::parse('2026-09-10', 'Africa/Cairo'),
            CarbonImmutable::parse('2026-09-10', 'Africa/Cairo')
        );

        $this->assertEquals(1, $report['visitors']);
        $this->assertEquals(1, $report['cta_qualified']);
        $this->assertEquals(1, $report['cta_observed']);
        $this->assertEquals(0, $report['cta_imputed']);
        $this->assertEquals(1, $report['booking_started']);
        $this->assertEquals(1, $report['slot_held']);
        $this->assertEquals(1, $report['booking_completed']);

        // Terminal abandonment must show completed = 1, total = 1
        $this->assertEquals(1, $report['abandonment']['completed']);
        $this->assertEquals(0, $report['abandonment']['held_abandoned']);
        $this->assertEquals(0, $report['abandonment']['started_abandoned']);
        $this->assertEquals(0, $report['abandonment']['cta_abandoned']);
        $this->assertEquals(0, $report['abandonment']['pre_cta']);
        $this->assertEquals(1, $report['abandonment']['total']);
    }

    public function test_direct_booking_imputes_upstream_stages_with_correct_provenance(): void
    {
        $visitor = Visitor::create([
            'visitor_token' => 'vis-direct-1',
            'first_seen_at' => '2026-09-12 11:00:00',
            'last_seen_at' => '2026-09-12 11:00:00',
        ]);

        // Direct booking completion without prior CTA or started events
        $this->funnelService->recordVisit($visitor, CarbonImmutable::parse('2026-09-12 11:00:00'));
        $this->funnelService->recordStage($visitor, 'booking_completed', CarbonImmutable::parse('2026-09-12 11:15:00'), true);

        $progression = VisitorFunnelProgression::where('visitor_id', $visitor->id)->first();
        $this->assertNotNull($progression);

        // Verification of stage provenance
        $this->assertEquals('imputed', $progression->booking_cta_provenance);
        $this->assertNull($progression->booking_cta_observed_at);
        $this->assertNotNull($progression->booking_cta_qualified_at);

        $this->assertEquals('imputed', $progression->booking_started_provenance);
        $this->assertNull($progression->booking_started_observed_at);

        $this->assertEquals('imputed', $progression->slot_held_provenance);
        $this->assertNull($progression->slot_held_observed_at);

        $this->assertEquals('observed', $progression->booking_completed_provenance);
        $this->assertNotNull($progression->booking_completed_observed_at);

        $report = $this->funnelService->getCohortFunnel(
            CarbonImmutable::parse('2026-09-12', 'Africa/Cairo'),
            CarbonImmutable::parse('2026-09-12', 'Africa/Cairo')
        );

        $this->assertEquals(1, $report['visitors']);
        $this->assertEquals(1, $report['cta_qualified']);
        $this->assertEquals(0, $report['cta_observed']);
        $this->assertEquals(1, $report['cta_imputed']);
        $this->assertEquals(1, $report['booking_started']);
        $this->assertEquals(1, $report['slot_held']);
        $this->assertEquals(1, $report['booking_completed']);
    }

    public function test_blocked_client_tracking_preserves_funnel_integrity(): void
    {
        $visitorToken = 'vis-blocked-client';
        $sessionToken = 'sess-blocked-client';

        $visitor = Visitor::create([
            'visitor_token' => $visitorToken,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        VisitorSession::create([
            'visitor_id' => $visitor->id,
            'session_token' => $sessionToken,
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        // Simulate server-side slot hold and completion while browser events are blocked
        $start = CarbonImmutable::now('UTC')->addDays(5)->setTime(10, 0, 0);
        $end = $start->addMinutes(60);

        $hold = app(BookingHoldService::class)->acquireHold(
            visitorToken: $visitorToken,
            sessionToken: $sessionToken,
            sessionType: $this->sessionType,
            startUtc: $start,
            endUtc: $end,
            durationMinutes: 10
        );

        $this->assertNotNull($hold);

        $booking = app(BookingService::class)->createPublicBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $start,
            'end_at_utc' => $end,
            'customer_name' => 'Blocked Browser Customer',
            'first_name' => 'Blocked',
            'last_name' => 'Browser Customer',
            'customer_email' => 'blocked@example.com',
            'customer_phone' => '+201000000002',
            'date_of_birth' => '1990-01-01',
            'visitor_token' => $visitorToken,
            'session_token' => $sessionToken,
            'hold_id' => $hold->id,
            'hold_token' => $hold->hold_token,
            'customer_timezone' => 'UTC',
            'idempotency_key' => 'idem-blocked-client-1',
        ]);

        $this->assertNotNull($booking);

        // Verify zero fabricated client events exist in raw analytics
        $fabricatedCtaCount = AnalyticsEvent::where('visitor_token', $visitorToken)
            ->where('event_name', 'booking_cta_clicked')
            ->count();
        $this->assertEquals(0, $fabricatedCtaCount, 'Fake client event must never be inserted into raw logs');

        // But the cumulative cohort funnel remains fully intact via deterministic imputation
        $report = $this->analyticsService->getPrimaryBookingFunnel(now()->subDay(), now()->addDay());
        $this->assertGreaterThanOrEqual(1, $report['visitors']);
        $this->assertGreaterThanOrEqual(1, $report['cta_qualified']);
        $this->assertGreaterThanOrEqual(1, $report['booking_completed']);
    }

    public function test_returning_visitor_excluded_from_new_cohort(): void
    {
        // Visitor first seen on August 15
        $visitor = Visitor::create([
            'visitor_token' => 'vis-returning-1',
            'first_seen_at' => '2026-08-15 10:00:00',
            'last_seen_at' => '2026-09-12 14:00:00',
        ]);
        $this->funnelService->recordVisit($visitor, CarbonImmutable::parse('2026-08-15 10:00:00'));

        // Booking made on September 12
        $this->funnelService->recordStage($visitor, 'booking_completed', CarbonImmutable::parse('2026-09-12 14:00:00'), true);

        // Query cohort for Sept 10 - Sept 16
        $report = $this->funnelService->getCohortFunnel(
            CarbonImmutable::parse('2026-09-10', 'Africa/Cairo'),
            CarbonImmutable::parse('2026-09-16', 'Africa/Cairo')
        );

        // The returning visitor must be excluded from this cohort!
        $this->assertEquals(0, $report['visitors']);
        $this->assertEquals(0, $report['booking_completed']);
    }

    public function test_cohort_maturity_window_cutoff(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-25 12:00:00', 'UTC'));
        $firstSeen = CarbonImmutable::parse('2026-09-01 10:00:00');

        $visitor = Visitor::create([
            'visitor_token' => 'vis-maturity-cutoff',
            'first_seen_at' => $firstSeen,
            'last_seen_at' => $firstSeen,
        ]);
        $this->funnelService->recordVisit($visitor, $firstSeen);

        // Booking A: 2026-09-25 (inside the 30-day window) -> qualifies
        $this->funnelService->recordStage($visitor, 'booking_cta', CarbonImmutable::parse('2026-09-25 12:00:00'), true);
        $progression = VisitorFunnelProgression::where('visitor_id', $visitor->id)->first();
        $this->assertEquals('observed', $progression->booking_cta_provenance);

        // Booking B: exactly 30 days later (2026-10-01 10:00:00) -> upper boundary is exclusive, must NOT qualify
        $exactCutoff = $firstSeen->addDays(30);
        $this->funnelService->recordStage($visitor, 'booking_completed', $exactCutoff, true);

        $progression->refresh();
        $this->assertEquals('none', $progression->booking_completed_provenance, 'Event at exact 30-day boundary must not qualify');

        // Booking C: 2026-10-15 (well past 30 days) -> must not alter 30-day cohort conversion
        $this->funnelService->recordStage($visitor, 'booking_completed', CarbonImmutable::parse('2026-10-15 10:00:00'), true);
        $progression->refresh();
        $this->assertEquals('none', $progression->booking_completed_provenance);
    }

    public function test_late_event_inside_window_can_be_reconciled(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-05 10:00:00', 'UTC'));
        $firstSeen = CarbonImmutable::parse('2026-09-01 10:00:00');
        $visitor = Visitor::create([
            'visitor_token' => 'vis-reconcile-test',
            'first_seen_at' => $firstSeen,
            'last_seen_at' => $firstSeen,
        ]);

        // Downstream action creates imputed CTA
        $this->funnelService->recordVisit($visitor, $firstSeen);
        $this->funnelService->recordStage($visitor, 'booking_started', CarbonImmutable::parse('2026-09-05 10:00:00'), true);

        $progression = VisitorFunnelProgression::where('visitor_id', $visitor->id)->first();
        $this->assertEquals('imputed', $progression->booking_cta_provenance);
        $this->assertNull($progression->booking_cta_observed_at);

        // Late event arrives whose authoritative timestamp is 2026-09-03 (inside 30 days)
        $this->travelTo(CarbonImmutable::parse('2026-10-03 10:00:00', 'UTC'));
        $reconciled = $this->funnelService->reconcileLateEvent(
            $visitor,
            'booking_cta',
            CarbonImmutable::parse('2026-09-03 15:00:00')
        );

        $this->assertTrue($reconciled);
        $progression->refresh();
        $this->assertEquals('observed', $progression->booking_cta_provenance);
        $this->assertNotNull($progression->booking_cta_observed_at);
        $this->assertNotNull($progression->reconciled_at);
    }

    public function test_booking_slot_held_fires_only_post_commit(): void
    {
        $visitorToken = 'vis-rollback-hold';
        $sessionToken = 'sess-rollback-hold';

        $visitor = Visitor::create([
            'visitor_token' => $visitorToken,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        VisitorSession::create([
            'visitor_id' => $visitor->id,
            'session_token' => $sessionToken,
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        $start = CarbonImmutable::now('UTC')->addDays(6)->setTime(14, 0, 0);
        $end = $start->addMinutes(60);

        try {
            DB::transaction(function () use ($start, $end, $visitorToken, $sessionToken) {
                // Simulate hold attempt that throws before commit
                app(BookingHoldService::class)->acquireHold(
                    visitorToken: $visitorToken,
                    sessionToken: $sessionToken,
                    sessionType: $this->sessionType,
                    startUtc: $start,
                    endUtc: $end,
                    durationMinutes: 10
                );

                throw new \RuntimeException('Simulated failure before hold transaction commit');
            });
        } catch (\RuntimeException) {
            // Expected
        }

        $holdEventCount = AnalyticsEvent::where('visitor_token', $visitorToken)
            ->where('event_name', 'booking_slot_held')
            ->count();

        $this->assertEquals(0, $holdEventCount, 'booking_slot_held must NOT be dispatched on rolled back transactions');
    }

    public function test_daily_activity_rollup_idempotency(): void
    {
        AnalyticsEvent::create([
            'event_name' => 'page_view',
            'visitor_token' => 'v1',
            'session_token' => 's1',
            'created_at' => '2026-09-14 12:00:00',
        ]);
        AnalyticsEvent::create([
            'event_name' => 'page_view',
            'visitor_token' => 'v2',
            'session_token' => 's2',
            'created_at' => '2026-09-14 13:00:00',
        ]);

        // Run aggregation 3 times for the same date
        Artisan::call('analytics:aggregate-daily', ['--date' => '2026-09-14']);
        Artisan::call('analytics:aggregate-daily', ['--date' => '2026-09-14']);
        Artisan::call('analytics:aggregate-daily', ['--date' => '2026-09-14']);

        $metrics = DailyMetric::where('metric_date', '2026-09-14')
            ->where('metric_name', 'page_view')
            ->get();

        $this->assertCount(1, $metrics, 'Idempotent aggregation must not duplicate metric rows');
        $this->assertEquals(2, $metrics->first()->count);
    }

    public function test_first_non_direct_acquisition_attribution(): void
    {
        $visitorToken = 'vis-attr-first-touch';

        // Day 1: Direct visit -> acquisition unassigned
        $this->analyticsService->track(
            eventName: 'page_view',
            visitorToken: $visitorToken,
            page: '/'
        );

        $visitor = Visitor::where('visitor_token', $visitorToken)->first();
        $this->assertNotNull($visitor);
        $this->assertNull($visitor->acquisition_source);

        // Day 10: Arrives with YouTube campaign -> becomes permanent First Non-Direct Touch
        $req = Request::create('/?utm_source=youtube&utm_campaign=intro');
        $this->analyticsService->track(
            eventName: 'page_view',
            request: $req,
            visitorToken: $visitorToken
        );

        $visitor->refresh();
        $this->assertEquals('youtube', $visitor->acquisition_source);
        $this->assertEquals('intro', $visitor->acquisition_campaign);

        // Day 20: Subsequent visit with Google campaign -> must NOT overwrite first-touch
        $reqGoogle = Request::create('/?utm_source=google&utm_campaign=search');
        $this->analyticsService->track(
            eventName: 'page_view',
            request: $reqGoogle,
            visitorToken: $visitorToken
        );

        $visitor->refresh();
        $this->assertEquals('youtube', $visitor->acquisition_source, 'Acquisition attribution must be immutable once assigned');
    }

    public function test_last_non_direct_touch_booking_attribution(): void
    {
        $visitorToken = 'vis-conversion-attr';
        $visitor = Visitor::create([
            'visitor_token' => $visitorToken,
            'first_seen_at' => now()->subDays(40),
            'last_seen_at' => now(),
        ]);

        // Touch 1 (35 days ago): YouTube -> outside 30-day window
        VisitorSession::create([
            'visitor_id' => $visitor->id,
            'session_token' => 's-old-youtube',
            'started_at' => now()->subDays(35),
            'last_activity_at' => now()->subDays(35),
            'utm_source' => 'youtube',
            'utm_campaign' => 'old-campaign',
        ]);

        // Touch 2 (10 days ago): Telegram -> inside 30-day window
        VisitorSession::create([
            'visitor_id' => $visitor->id,
            'session_token' => 's-telegram',
            'started_at' => now()->subDays(10),
            'last_activity_at' => now()->subDays(10),
            'utm_source' => 'telegram',
            'utm_campaign' => 'channel-post',
            'utm_content' => 'post-42',
        ]);

        // Touch 3 (1 day ago): Direct visit
        VisitorSession::create([
            'visitor_id' => $visitor->id,
            'session_token' => 's-direct',
            'started_at' => now()->subDay(),
            'last_activity_at' => now()->subDay(),
            'utm_source' => null,
            'referrer' => null,
        ]);

        $conversionAttr = $this->analyticsService->getBookingConversionAttribution($visitorToken, now());

        $this->assertEquals('telegram', $conversionAttr['utm_source']);
        $this->assertEquals('channel-post', $conversionAttr['utm_campaign']);
        $this->assertEquals('post-42', $conversionAttr['utm_content']);
    }
}
