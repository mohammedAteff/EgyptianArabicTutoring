<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Models\DailyCountryMetric;
use App\Domains\Analytics\Models\DailyMetric;
use App\Domains\Analytics\Models\Visitor;
use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Reporting\Services\ReportService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CairoDailyAnalyticsRollupTest extends TestCase
{
    use RefreshDatabase;

    protected TimezoneService $timezoneService;

    protected ReportService $reportService;

    protected AnalyticsService $analyticsService;

    protected SessionType $sessionType;

    protected Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timezoneService = app(TimezoneService::class);
        $this->reportService = app(ReportService::class);
        $this->analyticsService = app(AnalyticsService::class);

        $this->sessionType = SessionType::create([
            'title' => 'Standard Arabic Lesson',
            'slug' => 'standard-lesson',
            'duration_minutes' => 60,
            'price' => 300,
            'currency' => 'EGP',
            'active' => true,
        ]);

        $this->contact = Contact::create([
            'name' => 'Kareem Farouk',
            'email' => 'kareem@example.com',
            'phone' => '+201000000001',
            'timezone' => 'Africa/Cairo',
            'locale' => 'en',
        ]);
    }

    public function test_session_crossing_cairo_midnight_is_attributed_only_to_started_at_cairo_date(): void
    {
        $cairoTz = 'Africa/Cairo';

        // Session starts at 23:45 Cairo on 2026-09-18
        $sessionStart = CarbonImmutable::parse('2026-09-18 23:45:00', $cairoTz);
        $sessionStartUtc = $sessionStart->setTimezone('UTC');

        $visitor = Visitor::create([
            'visitor_token' => 'vis-midnight-1',
            'first_seen_at' => $sessionStartUtc,
            'last_seen_at' => $sessionStartUtc,
        ]);

        $session = VisitorSession::create([
            'session_token' => 'sess-midnight-1',
            'visitor_id' => $visitor->id,
            'started_at' => $sessionStartUtc,
            'last_activity_at' => $sessionStartUtc->addMinutes(30),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Testing',
            'is_bot' => false,
            'landing_page' => '/',
        ]);

        // Event 1 at 23:50 Cairo on 2026-09-18
        AnalyticsEvent::create([
            'visitor_id' => $visitor->id,
            'visitor_token' => $visitor->visitor_token,
            'session_id' => $session->id,
            'session_token' => $session->session_token,
            'event_name' => 'page_view',
            'page' => '/lessons',
            'created_at' => CarbonImmutable::parse('2026-09-18 23:50:00', $cairoTz)->setTimezone('UTC'),
            'is_bot' => false,
        ]);

        // Event 2 at 00:15 Cairo on 2026-09-19 (within same session, crossed midnight)
        AnalyticsEvent::create([
            'visitor_id' => $visitor->id,
            'visitor_token' => $visitor->visitor_token,
            'session_id' => $session->id,
            'session_token' => $session->session_token,
            'event_name' => 'page_view',
            'page' => '/booking',
            'created_at' => CarbonImmutable::parse('2026-09-19 00:15:00', $cairoTz)->setTimezone('UTC'),
            'is_bot' => false,
        ]);

        // Run aggregation for 2026-09-18
        Artisan::call('analytics:aggregate-daily', ['--date' => '2026-09-18']);

        $metric18 = DailyMetric::where('metric_date', '2026-09-18')
            ->where('metric_name', 'sessions')
            ->first();

        $this->assertNotNull($metric18);
        $this->assertEquals(1, $metric18->count, 'Session started at 23:45 Cairo must be counted on 2026-09-18');

        // Run aggregation for 2026-09-19
        Artisan::call('analytics:aggregate-daily', ['--date' => '2026-09-19']);

        $metric19 = DailyMetric::where('metric_date', '2026-09-19')
            ->where('metric_name', 'sessions')
            ->first();

        $this->assertNotNull($metric19);
        $this->assertEquals(0, $metric19->count, 'Session crossing midnight must NOT be counted again on 2026-09-19');
    }

    public function test_reports_label_periods_before_the_authoritative_analytics_cutover(): void
    {
        Setting::set('analytics_authoritative_cutover_date', '2026-09-21', 'analytics');

        $admin = Administrator::create([
            'name' => 'Cutover Admin',
            'email' => 'cutover-admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'web')->get(route('admin.reports.index', [
            'type' => 'traffic',
            'range' => 'custom',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-02',
        ]));

        $response->assertOk();
        $response->assertDontSee('Non-Comparable Historical Data');
        $response->assertViewHas('isBeforeAuthoritativeCutover', true);
        $response->assertViewHas('authoritativeCutoverDate', '2026-09-21');
    }

    public function test_events_near_cairo_day_boundaries_are_attributed_strictly_to_respective_cairo_dates(): void
    {
        $cairoTz = 'Africa/Cairo';

        $visitor = Visitor::create([
            'visitor_token' => 'vis-boundary-1',
            'first_seen_at' => CarbonImmutable::parse('2026-09-18 20:00:00', $cairoTz)->setTimezone('UTC'),
            'last_seen_at' => CarbonImmutable::parse('2026-09-19 01:00:00', $cairoTz)->setTimezone('UTC'),
        ]);

        // Event at 23:59:59 Cairo on 2026-09-18
        AnalyticsEvent::create([
            'visitor_id' => $visitor->id,
            'visitor_token' => $visitor->visitor_token,
            'session_token' => 'sess-boundary-a',
            'event_name' => 'page_view',
            'page' => '/day-18',
            'created_at' => CarbonImmutable::parse('2026-09-18 23:59:59', $cairoTz)->setTimezone('UTC'),
            'is_bot' => false,
        ]);

        // Event at 00:00:00 Cairo on 2026-09-19
        AnalyticsEvent::create([
            'visitor_id' => $visitor->id,
            'visitor_token' => $visitor->visitor_token,
            'session_token' => 'sess-boundary-b',
            'event_name' => 'page_view',
            'page' => '/day-19',
            'created_at' => CarbonImmutable::parse('2026-09-19 00:00:00', $cairoTz)->setTimezone('UTC'),
            'is_bot' => false,
        ]);

        Artisan::call('analytics:aggregate-daily', ['--date' => '2026-09-18']);
        Artisan::call('analytics:aggregate-daily', ['--date' => '2026-09-19']);

        $pv18 = DailyMetric::where('metric_date', '2026-09-18')
            ->where('metric_name', 'page_view')
            ->first();
        $this->assertNotNull($pv18);
        $this->assertEquals(1, $pv18->count);

        $pv19 = DailyMetric::where('metric_date', '2026-09-19')
            ->where('metric_name', 'page_view')
            ->first();
        $this->assertNotNull($pv19);
        $this->assertEquals(1, $pv19->count);
    }

    public function test_bookings_created_metric_preserves_creation_count_even_if_later_cancelled(): void
    {
        $cairoTz = 'Africa/Cairo';
        $createdTimeUtc = CarbonImmutable::parse('2026-09-18 14:00:00', $cairoTz)->setTimezone('UTC');
        $startUtc = $createdTimeUtc->addDays(2);
        $endUtc = $startUtc->addHour();
        $snapshot = $this->timezoneService->createBookingSnapshot($startUtc, $endUtc, 'Europe/London', 'Africa/Cairo');

        // Create booking on 2026-09-18
        $booking = Booking::create(array_merge($snapshot, [
            'session_type_id' => $this->sessionType->id,
            'contact_id' => $this->contact->id,
            'confirmation_token' => 'BK-ROLLUP-TEST',
            'idempotency_key' => 'idem-rollup-1',
            'status' => 'confirmed',
        ]));

        DB::table('bookings')->where('id', $booking->id)->update([
            'created_at' => $createdTimeUtc,
            'updated_at' => $createdTimeUtc,
        ]);

        // Cancel it later
        $booking->update([
            'status' => 'cancelled',
            'cancellation_reason' => 'Schedule conflict',
            'cancelled_at' => $createdTimeUtc->addHours(5),
        ]);

        Artisan::call('analytics:aggregate-daily', ['--date' => '2026-09-18']);

        $metric = DailyMetric::where('metric_date', '2026-09-18')
            ->where('metric_name', 'bookings_created')
            ->first();

        $this->assertNotNull($metric);
        $this->assertEquals(1, $metric->count, 'Bookings created metric must include bookings created on that date regardless of later cancellation');
    }

    public function test_rebuilding_day_removes_deleted_and_changed_dimension_rows(): void
    {
        $cairoTz = 'Africa/Cairo';
        $eventTimeUtc = CarbonImmutable::parse('2026-09-18 11:00:00', $cairoTz)->setTimezone('UTC');

        $event = AnalyticsEvent::create([
            'visitor_token' => 'vis-rebuild-1',
            'session_token' => 'sess-rebuild-1',
            'event_name' => 'page_view',
            'page' => '/initial-stale-page',
            'created_at' => $eventTimeUtc,
            'is_bot' => false,
        ]);

        Artisan::call('analytics:aggregate-daily', ['--date' => '2026-09-18']);

        $initialMetric = DailyMetric::where('metric_date', '2026-09-18')
            ->where('metric_name', 'page_views')
            ->where('dimension_key', 'page')
            ->where('dimension_value', '/initial-stale-page')
            ->first();
        $this->assertNotNull($initialMetric);

        // Change the event's page
        $event->update(['page' => '/updated-new-page']);

        // Rebuild the day
        Artisan::call('analytics:aggregate-daily', ['--date' => '2026-09-18']);

        // The old dimension must be completely deleted
        $staleMetric = DailyMetric::where('metric_date', '2026-09-18')
            ->where('metric_name', 'page_views')
            ->where('dimension_key', 'page')
            ->where('dimension_value', '/initial-stale-page')
            ->first();
        $this->assertNull($staleMetric, 'Rebuilding a day must purge stale dimension rows');

        $newMetric = DailyMetric::where('metric_date', '2026-09-18')
            ->where('metric_name', 'page_views')
            ->where('dimension_key', 'page')
            ->where('dimension_value', '/updated-new-page')
            ->first();
        $this->assertNotNull($newMetric);
    }

    public function test_concurrent_or_repeated_aggregation_produces_no_duplicate_rows_with_empty_dimensions(): void
    {
        $cairoTz = 'Africa/Cairo';
        $timeUtc = CarbonImmutable::parse('2026-09-18 12:00:00', $cairoTz)->setTimezone('UTC');

        AnalyticsEvent::create([
            'visitor_token' => 'vis-dup-1',
            'session_token' => 'sess-dup-1',
            'event_name' => 'booking_cta_clicked',
            'created_at' => $timeUtc,
            'is_bot' => false,
        ]);

        // Run aggregation twice on the same day
        Artisan::call('analytics:aggregate-daily', ['--date' => '2026-09-18']);
        Artisan::call('analytics:aggregate-daily', ['--date' => '2026-09-18']);

        $ctaMetrics = DailyMetric::where('metric_date', '2026-09-18')
            ->where('metric_name', 'booking_cta_clicked')
            ->get();

        $this->assertCount(1, $ctaMetrics, 'Repeated aggregation must not produce duplicate metric rows');
        $this->assertEquals('', $ctaMetrics->first()->dimension_key);
        $this->assertEquals('', $ctaMetrics->first()->dimension_value);
    }

    public function test_report_service_traffic_report_groups_strictly_by_cairo_calendar_date(): void
    {
        $cairoTz = 'Africa/Cairo';

        // Event at 23:45 Cairo on 2026-09-18
        $time18 = CarbonImmutable::parse('2026-09-18 23:45:00', $cairoTz)->setTimezone('UTC');
        // Event at 00:15 Cairo on 2026-09-19
        $time19 = CarbonImmutable::parse('2026-09-19 00:15:00', $cairoTz)->setTimezone('UTC');

        AnalyticsEvent::create([
            'visitor_token' => 'vis-rep-1',
            'session_token' => 'sess-rep-1',
            'event_name' => 'page_view',
            'page' => '/',
            'created_at' => $time18,
            'is_bot' => false,
        ]);

        AnalyticsEvent::create([
            'visitor_token' => 'vis-rep-2',
            'session_token' => 'sess-rep-2',
            'event_name' => 'page_view',
            'page' => '/',
            'created_at' => $time19,
            'is_bot' => false,
        ]);

        $startCairo = CarbonImmutable::parse('2026-09-18 00:00:00', $cairoTz)->setTimezone('UTC');
        $endCairo = CarbonImmutable::parse('2026-09-19 23:59:59', $cairoTz)->setTimezone('UTC');

        $report = $this->reportService->getTrafficReport($startCairo, $endCairo);

        $reportDates = $report['rows']->pluck('report_date')->all();

        $this->assertContains('2026-09-18', $reportDates);
        $this->assertContains('2026-09-19', $reportDates);
    }

    public function test_winter_and_summer_cairo_midnight_boundary_assignment(): void
    {
        // Winter (January): Cairo is UTC+2
        // Midnight Jan 16 is 2026-01-15 22:00:00 UTC
        $w1 = CarbonImmutable::parse('2026-01-15 21:59:59', 'UTC'); // Jan 15 Cairo
        $w2 = CarbonImmutable::parse('2026-01-15 22:00:00', 'UTC'); // Jan 16 Cairo

        AnalyticsEvent::create([
            'visitor_token' => 'vis-win-1',
            'session_token' => 'sess-win-1',
            'event_name' => 'page_view',
            'created_at' => $w1,
            'is_bot' => false,
        ]);
        AnalyticsEvent::create([
            'visitor_token' => 'vis-win-2',
            'session_token' => 'sess-win-2',
            'event_name' => 'page_view',
            'created_at' => $w2,
            'is_bot' => false,
        ]);

        $winterReport = $this->reportService->getTrafficReport(
            CarbonImmutable::parse('2026-01-15 00:00:00', 'UTC'),
            CarbonImmutable::parse('2026-01-17 00:00:00', 'UTC')
        );

        $w15 = $winterReport['rows']->firstWhere('report_date', '2026-01-15');
        $w16 = $winterReport['rows']->firstWhere('report_date', '2026-01-16');

        $this->assertNotNull($w15, 'Jan 15 row must exist');
        $this->assertNotNull($w16, 'Jan 16 row must exist');
        $this->assertEquals(1, $w15->page_views, 'Event 1 second before midnight belongs to Jan 15 in Cairo');
        $this->assertEquals(1, $w16->page_views, 'Event at exact midnight belongs to Jan 16 in Cairo');

        // Summer (July): Cairo is UTC+3
        // Midnight Jul 16 is 2026-07-15 21:00:00 UTC
        $s1 = CarbonImmutable::parse('2026-07-15 20:59:59', 'UTC'); // Jul 15 Cairo
        $s2 = CarbonImmutable::parse('2026-07-15 21:00:00', 'UTC'); // Jul 16 Cairo

        AnalyticsEvent::create([
            'visitor_token' => 'vis-sum-1',
            'session_token' => 'sess-sum-1',
            'event_name' => 'page_view',
            'created_at' => $s1,
            'is_bot' => false,
        ]);
        AnalyticsEvent::create([
            'visitor_token' => 'vis-sum-2',
            'session_token' => 'sess-sum-2',
            'event_name' => 'page_view',
            'created_at' => $s2,
            'is_bot' => false,
        ]);

        $summerReport = $this->reportService->getTrafficReport(
            CarbonImmutable::parse('2026-07-15 00:00:00', 'UTC'),
            CarbonImmutable::parse('2026-07-17 00:00:00', 'UTC')
        );

        $s15 = $summerReport['rows']->firstWhere('report_date', '2026-07-15');
        $s16 = $summerReport['rows']->firstWhere('report_date', '2026-07-16');

        $this->assertNotNull($s15, 'Jul 15 row must exist');
        $this->assertNotNull($s16, 'Jul 16 row must exist');
        $this->assertEquals(1, $s15->page_views, 'Summer event before midnight belongs to Jul 15');
        $this->assertEquals(1, $s16->page_views, 'Summer event at midnight belongs to Jul 16');
    }

    public function test_cross_midnight_session_attributed_once_to_start_day_matching_daily_metrics(): void
    {
        $cairoTz = 'Africa/Cairo';

        // Visitor starts session at 23:45 Cairo on Sep 18
        $sessionStartCairo = CarbonImmutable::parse('2026-09-18 23:45:00', $cairoTz);
        $sessionStartUtc = $sessionStartCairo->setTimezone('UTC');

        // Second event occurs at 00:15 Cairo on Sep 19 (in the same session!)
        $event2Cairo = CarbonImmutable::parse('2026-09-19 00:15:00', $cairoTz);
        $event2Utc = $event2Cairo->setTimezone('UTC');

        $visitor = Visitor::create([
            'visitor_token' => 'vis-cross-session',
            'first_seen_at' => $sessionStartUtc,
            'last_seen_at' => $event2Utc,
            'is_bot' => false,
        ]);

        $session = VisitorSession::create([
            'visitor_id' => $visitor->id,
            'session_token' => 'sess-cross-midnight',
            'started_at' => $sessionStartUtc,
            'last_activity_at' => $event2Utc,
            'is_bot' => false,
        ]);

        // Event 1 on Sep 18 Cairo
        AnalyticsEvent::create([
            'visitor_token' => $visitor->visitor_token,
            'session_token' => $session->session_token,
            'event_name' => 'page_view',
            'page' => '/',
            'created_at' => $sessionStartUtc,
            'is_bot' => false,
        ]);

        // Event 2 on Sep 19 Cairo
        AnalyticsEvent::create([
            'visitor_token' => $visitor->visitor_token,
            'session_token' => $session->session_token,
            'event_name' => 'page_view',
            'page' => '/resources',
            'created_at' => $event2Utc,
            'is_bot' => false,
        ]);

        // Aggregate daily metrics for both days
        Artisan::call('analytics:aggregate-daily', ['--date' => '2026-09-18']);
        Artisan::call('analytics:aggregate-daily', ['--date' => '2026-09-19']);

        // Assert daily_metrics sessions: Sep 18 has 1, Sep 19 has 0
        $dm18 = DailyMetric::where('metric_date', '2026-09-18')->where('metric_name', 'sessions')->value('count');
        $dm19 = DailyMetric::where('metric_date', '2026-09-19')->where('metric_name', 'sessions')->value('count');

        $this->assertEquals(1, $dm18, 'daily_metrics must attribute session to Sep 18');
        $this->assertEquals(0, $dm19, 'daily_metrics must not double-count session on Sep 19');

        // Query ReportService for traffic report covering both days
        $startRange = CarbonImmutable::parse('2026-09-18 00:00:00', $cairoTz)->setTimezone('UTC');
        $endRange = CarbonImmutable::parse('2026-09-19 23:59:59', $cairoTz)->setTimezone('UTC');
        $report = $this->reportService->getTrafficReport($startRange, $endRange);

        $rep18 = $report['rows']->firstWhere('report_date', '2026-09-18');
        $rep19 = $report['rows']->firstWhere('report_date', '2026-09-19');

        $this->assertEquals(1, $rep18?->sessions, 'getTrafficReport must attribute session to Sep 18');
        $this->assertEquals(0, $rep19?->sessions ?? 0, 'getTrafficReport must not attribute session to Sep 19');

        // Total sessions in report summary must be 1, completely matching daily metrics!
        $this->assertEquals(1, $report['summary']['sessions']);
        $this->assertEquals($dm18 + $dm19, $report['summary']['sessions']);
    }

    public function test_daily_metrics_and_traffic_report_and_export_totals_identical(): void
    {
        $cairoTz = 'Africa/Cairo';
        $dayStart = CarbonImmutable::parse('2026-09-10 00:00:00', $cairoTz)->setTimezone('UTC');
        $dayEnd = CarbonImmutable::parse('2026-09-10 23:59:59', $cairoTz)->setTimezone('UTC');

        $visitor = Visitor::create([
            'visitor_token' => 'vis-export-test',
            'first_seen_at' => $dayStart->addHours(2),
            'last_seen_at' => $dayStart->addHours(4),
            'is_bot' => false,
        ]);

        $session = VisitorSession::create([
            'visitor_id' => $visitor->id,
            'session_token' => 'sess-export-test',
            'started_at' => $dayStart->addHours(2),
            'last_activity_at' => $dayStart->addHours(4),
            'is_bot' => false,
        ]);

        AnalyticsEvent::create([
            'visitor_token' => $visitor->visitor_token,
            'session_token' => $session->session_token,
            'event_name' => 'page_view',
            'page' => '/',
            'created_at' => $dayStart->addHours(2),
            'is_bot' => false,
        ]);

        AnalyticsEvent::create([
            'visitor_token' => $visitor->visitor_token,
            'session_token' => $session->session_token,
            'event_name' => 'page_view',
            'page' => '/about',
            'created_at' => $dayStart->addHours(3),
            'is_bot' => false,
        ]);

        Artisan::call('analytics:aggregate-daily', ['--date' => '2026-09-10']);

        $dmVisitors = DailyMetric::where('metric_date', '2026-09-10')->where('metric_name', 'unique_visitors')->value('count');
        $dmSessions = DailyMetric::where('metric_date', '2026-09-10')->where('metric_name', 'sessions')->value('count');
        $dmPageviews = DailyMetric::where('metric_date', '2026-09-10')->where('metric_name', 'page_view')->value('count');

        $report = $this->reportService->getTrafficReport($dayStart, $dayEnd);
        $row = $report['rows']->firstWhere('report_date', '2026-09-10');

        $this->assertEquals($dmVisitors, $row->visitors);
        $this->assertEquals($dmSessions, $row->sessions);
        $this->assertEquals($dmPageviews, $row->page_views);

        // Export via Admin endpoint
        $admin = Administrator::create([
            'name' => 'Super Admin',
            'email' => 'super-export@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $csvResponse = $this->actingAs($admin, 'web')->get(route('admin.reports.export', [
            'type' => 'traffic',
            'range' => 'today',
            'format' => 'csv',
        ]));
        $csvResponse->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csvResponse->headers->get('content-type'));
    }

    public function test_historical_traffic_report_survives_raw_event_pruning_and_serves_from_daily_metrics(): void
    {
        // Date 200 days ago (older than 180-day retention cutoff)
        $historicalDate = '2026-01-15';
        $start = CarbonImmutable::parse('2026-01-15 00:00:00', 'Africa/Cairo')->setTimezone('UTC');
        $end = CarbonImmutable::parse('2026-01-15 23:59:59', 'Africa/Cairo')->setTimezone('UTC');

        // Pre-aggregated rollups in daily_metrics
        DailyMetric::create([
            'metric_date' => $historicalDate,
            'metric_name' => 'unique_visitors',
            'dimension_key' => '',
            'dimension_value' => '',
            'count' => 42,
        ]);

        DailyMetric::create([
            'metric_date' => $historicalDate,
            'metric_name' => 'sessions',
            'dimension_key' => '',
            'dimension_value' => '',
            'count' => 55,
        ]);

        DailyMetric::create([
            'metric_date' => $historicalDate,
            'metric_name' => 'page_view',
            'dimension_key' => '',
            'dimension_value' => '',
            'count' => 110,
        ]);

        DailyMetric::create([
            'metric_date' => $historicalDate,
            'metric_name' => 'visitors_by_source',
            'dimension_key' => 'source',
            'dimension_value' => 'google',
            'count' => 30,
        ]);

        // Raw tables have ZERO events/sessions (pruned after 180 days)
        $this->assertEquals(0, AnalyticsEvent::whereBetween('created_at', [$start, $end])->count());
        $this->assertEquals(0, VisitorSession::whereBetween('started_at', [$start, $end])->count());

        // ReportService must serve historical data from durable rollups
        $report = $this->reportService->getTrafficReport($start, $end);

        $this->assertNotEmpty($report['rows']);
        $row = $report['rows']->firstWhere('report_date', $historicalDate);
        $this->assertNotNull($row, 'Historical date must be present in report rows even after raw event pruning');
        $this->assertEquals(42, $row->visitors);
        $this->assertEquals(55, $row->sessions);
        $this->assertEquals(110, $row->page_views);
        $this->assertEquals('google', $row->top_source);

        // Summaries must reflect durable rollups rather than zero
        $this->assertEquals(42, $report['summary']['visitors']);
        $this->assertEquals(55, $report['summary']['sessions']);
        $this->assertEquals(110, $report['summary']['page_views']);
    }

    public function test_returning_visitor_across_two_days_is_counted_once_in_period_unique_visitors(): void
    {
        $cairoTz = 'Africa/Cairo';
        $day1Cairo = CarbonImmutable::parse('2026-09-10 00:00:00', $cairoTz);
        $day2Cairo = CarbonImmutable::parse('2026-09-11 23:59:59', $cairoTz);

        $startUtc = $day1Cairo->setTimezone('UTC');
        $endUtc = $day2Cairo->setTimezone('UTC');

        // Visitor A visits on Day 1 and Day 2 (returning visitor)
        $visA = Visitor::create([
            'visitor_token' => 'vis-returning-a',
            'first_seen_at' => CarbonImmutable::parse('2026-09-10 10:00:00', $cairoTz)->setTimezone('UTC'),
            'last_seen_at' => CarbonImmutable::parse('2026-09-11 11:15:00', $cairoTz)->setTimezone('UTC'),
            'is_bot' => false,
        ]);

        // Day 1
        AnalyticsEvent::create([
            'visitor_id' => $visA->id,
            'visitor_token' => 'vis-returning-a',
            'session_token' => 'sess-a1',
            'event_name' => 'page_view',
            'created_at' => CarbonImmutable::parse('2026-09-10 10:00:00', $cairoTz)->setTimezone('UTC'),
            'is_bot' => false,
        ]);
        VisitorSession::create([
            'session_token' => 'sess-a1',
            'visitor_id' => $visA->id,
            'visitor_token' => 'vis-returning-a',
            'started_at' => CarbonImmutable::parse('2026-09-10 10:00:00', $cairoTz)->setTimezone('UTC'),
            'last_activity_at' => CarbonImmutable::parse('2026-09-10 10:15:00', $cairoTz)->setTimezone('UTC'),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Testing',
            'is_bot' => false,
        ]);

        // Day 2
        AnalyticsEvent::create([
            'visitor_id' => $visA->id,
            'visitor_token' => 'vis-returning-a',
            'session_token' => 'sess-a2',
            'event_name' => 'page_view',
            'created_at' => CarbonImmutable::parse('2026-09-11 11:00:00', $cairoTz)->setTimezone('UTC'),
            'is_bot' => false,
        ]);
        VisitorSession::create([
            'session_token' => 'sess-a2',
            'visitor_id' => $visA->id,
            'visitor_token' => 'vis-returning-a',
            'started_at' => CarbonImmutable::parse('2026-09-11 11:00:00', $cairoTz)->setTimezone('UTC'),
            'last_activity_at' => CarbonImmutable::parse('2026-09-11 11:15:00', $cairoTz)->setTimezone('UTC'),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Testing',
            'is_bot' => false,
        ]);

        // Visitor B visits ONLY on Day 2
        $visB = Visitor::create([
            'visitor_token' => 'vis-new-b',
            'first_seen_at' => CarbonImmutable::parse('2026-09-11 12:00:00', $cairoTz)->setTimezone('UTC'),
            'last_seen_at' => CarbonImmutable::parse('2026-09-11 12:15:00', $cairoTz)->setTimezone('UTC'),
            'is_bot' => false,
        ]);

        AnalyticsEvent::create([
            'visitor_id' => $visB->id,
            'visitor_token' => 'vis-new-b',
            'session_token' => 'sess-b1',
            'event_name' => 'page_view',
            'created_at' => CarbonImmutable::parse('2026-09-11 12:00:00', $cairoTz)->setTimezone('UTC'),
            'is_bot' => false,
        ]);
        VisitorSession::create([
            'session_token' => 'sess-b1',
            'visitor_id' => $visB->id,
            'visitor_token' => 'vis-new-b',
            'started_at' => CarbonImmutable::parse('2026-09-11 12:00:00', $cairoTz)->setTimezone('UTC'),
            'last_activity_at' => CarbonImmutable::parse('2026-09-11 12:15:00', $cairoTz)->setTimezone('UTC'),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Testing',
            'is_bot' => false,
        ]);

        $report = $this->reportService->getTrafficReport($startUtc, $endUtc);

        $rowDay1 = $report['rows']->firstWhere('report_date', '2026-09-10');
        $rowDay2 = $report['rows']->firstWhere('report_date', '2026-09-11');

        $this->assertNotNull($rowDay1);
        $this->assertNotNull($rowDay2);
        $this->assertEquals(1, $rowDay1->visitors, 'Day 1 has 1 unique visitor (Visitor A)');
        $this->assertEquals(2, $rowDay2->visitors, 'Day 2 has 2 unique visitors (Visitor A and B)');

        // Crucial test for V1-R07: Period unique visitors MUST be 2 (A and B), NOT the inflated sum 1 + 2 = 3!
        $this->assertEquals(2, $report['summary']['visitors'], 'Period unique visitors must deduplicate returning visitors to exact 2, not 3');
        $this->assertEquals('exact_unique_visitors', $report['summary']['visitors_basis']);
        $this->assertFalse($report['summary']['visitors_is_daily_sum']);
        $this->assertEquals(3, $report['summary']['sessions'], 'Total sessions is 3');
        $this->assertEquals(3, $report['summary']['page_views'], 'Total page views is 3');
    }

    public function test_pruned_source_filtered_traffic_report_prevents_unfiltered_rollup_contamination(): void
    {
        $cairoTz = 'Africa/Cairo';
        $historicalDate = '2025-01-15';
        $start = CarbonImmutable::parse("{$historicalDate} 00:00:00", $cairoTz)->setTimezone('UTC');
        $end = CarbonImmutable::parse("{$historicalDate} 23:59:59", $cairoTz)->setTimezone('UTC');

        // Source 1: Facebook
        DailyMetric::create([
            'metric_date' => $historicalDate,
            'metric_name' => 'visitors_by_source',
            'dimension_key' => 'source',
            'dimension_value' => 'facebook',
            'count' => 15,
        ]);
        DailyMetric::create([
            'metric_date' => $historicalDate,
            'metric_name' => 'sessions_by_source',
            'dimension_key' => 'source',
            'dimension_value' => 'facebook',
            'count' => 20,
        ]);
        DailyMetric::create([
            'metric_date' => $historicalDate,
            'metric_name' => 'page_views_by_source',
            'dimension_key' => 'source',
            'dimension_value' => 'facebook',
            'count' => 45,
        ]);

        // Source 2: Google
        DailyMetric::create([
            'metric_date' => $historicalDate,
            'metric_name' => 'visitors_by_source',
            'dimension_key' => 'source',
            'dimension_value' => 'google',
            'count' => 30,
        ]);
        DailyMetric::create([
            'metric_date' => $historicalDate,
            'metric_name' => 'sessions_by_source',
            'dimension_key' => 'source',
            'dimension_value' => 'google',
            'count' => 35,
        ]);
        DailyMetric::create([
            'metric_date' => $historicalDate,
            'metric_name' => 'page_views_by_source',
            'dimension_key' => 'source',
            'dimension_value' => 'google',
            'count' => 80,
        ]);

        // Overall unsegmented metrics
        DailyMetric::create([
            'metric_date' => $historicalDate,
            'metric_name' => 'unique_visitors',
            'dimension_key' => '',
            'dimension_value' => '',
            'count' => 45,
        ]);
        DailyMetric::create([
            'metric_date' => $historicalDate,
            'metric_name' => 'sessions',
            'dimension_key' => '',
            'dimension_value' => '',
            'count' => 55,
        ]);
        DailyMetric::create([
            'metric_date' => $historicalDate,
            'metric_name' => 'page_view',
            'dimension_key' => '',
            'dimension_value' => '',
            'count' => 125,
        ]);

        // Pruned state: 0 raw events or sessions exist
        $this->assertEquals(0, AnalyticsEvent::whereBetween('created_at', [$start, $end])->count());
        $this->assertEquals(0, VisitorSession::whereBetween('started_at', [$start, $end])->count());

        // 1. Query filtered by Facebook: MUST NOT leak Google or overall unfiltered totals
        $fbReport = $this->reportService->getTrafficReport($start, $end, 'facebook');
        $this->assertNotEmpty($fbReport['rows']);
        $fbRow = $fbReport['rows']->firstWhere('report_date', $historicalDate);
        $this->assertNotNull($fbRow);
        $this->assertEquals(15, $fbRow->visitors, 'Facebook visitors must be isolated to 15');
        $this->assertEquals(20, $fbRow->sessions, 'Facebook sessions must be isolated to 20, not overall 55');
        $this->assertEquals(45, $fbRow->page_views, 'Facebook page views must be isolated to 45, not overall 125');
        $this->assertEquals('facebook', $fbRow->top_source);

        $this->assertEquals(15, $fbReport['summary']['visitors']);
        $this->assertEquals(20, $fbReport['summary']['sessions']);
        $this->assertEquals(45, $fbReport['summary']['page_views']);

        // 2. Query filtered by an unrecorded source: MUST NOT inject unfiltered rollups
        $otherReport = $this->reportService->getTrafficReport($start, $end, 'twitter');
        $otherRow = $otherReport['rows']->firstWhere('report_date', $historicalDate);
        $this->assertEquals(0, $otherRow->visitors ?? 0);
        $this->assertEquals(0, $otherRow->sessions ?? 0);
        $this->assertEquals(0, $otherRow->page_views ?? 0);
        $this->assertEquals(0, $otherReport['summary']['visitors']);
        $this->assertEquals(0, $otherReport['summary']['sessions']);
        $this->assertEquals(0, $otherReport['summary']['page_views']);
    }

    public function test_custom_range_spanning_over_366_days_maintains_exact_cairo_dst_mapping(): void
    {
        $cairoTz = 'Africa/Cairo';

        // 400-day custom range spanning summer 2025 (+03:00), winter 2025-2026 (+02:00), and summer 2026 (+03:00)
        $startCairo = CarbonImmutable::parse('2025-06-01 00:00:00', $cairoTz);
        $endCairo = CarbonImmutable::parse('2026-07-06 23:59:59', $cairoTz);
        $this->assertGreaterThan(366, $startCairo->diffInDays($endCairo));

        $startUtc = $startCairo->setTimezone('UTC');
        $endUtc = $endCairo->setTimezone('UTC');

        // Winter event: 2026-01-15 21:30:00 UTC -> in Cairo winter (+02:00), this is 23:30 on 2026-01-15
        $winterEventTime = CarbonImmutable::parse('2026-01-15 21:30:00', 'UTC');
        $visWinter = Visitor::create([
            'visitor_token' => 'vis-winter-dst',
            'first_seen_at' => $winterEventTime,
            'last_seen_at' => $winterEventTime,
            'is_bot' => false,
        ]);
        AnalyticsEvent::create([
            'visitor_token' => $visWinter->visitor_token,
            'event_name' => 'page_view',
            'created_at' => $winterEventTime,
            'is_bot' => false,
        ]);

        // Summer event: 2026-06-15 21:30:00 UTC -> in Cairo summer (+03:00), this is 00:30 on 2026-06-16
        $summerEventTime = CarbonImmutable::parse('2026-06-15 21:30:00', 'UTC');
        $visSummer = Visitor::create([
            'visitor_token' => 'vis-summer-dst',
            'first_seen_at' => $summerEventTime,
            'last_seen_at' => $summerEventTime,
            'is_bot' => false,
        ]);
        AnalyticsEvent::create([
            'visitor_token' => $visSummer->visitor_token,
            'event_name' => 'page_view',
            'created_at' => $summerEventTime,
            'is_bot' => false,
        ]);

        $report = $this->reportService->getTrafficReport($startUtc, $endUtc);

        // Winter event must be mapped to 2026-01-15
        $winterRow = $report['rows']->firstWhere('report_date', '2026-01-15');
        $this->assertNotNull($winterRow, 'Winter event at 21:30 UTC must be grouped into 2026-01-15 in Cairo winter (+02:00)');
        $this->assertEquals(1, $winterRow->page_views);

        // Summer event must be mapped to 2026-06-16
        $summerRow = $report['rows']->firstWhere('report_date', '2026-06-16');
        $this->assertNotNull($summerRow, 'Summer event at 21:30 UTC must be grouped into 2026-06-16 in Cairo summer (+03:00)');
        $this->assertEquals(1, $summerRow->page_views);
    }

    public function test_session_at_exact_cairo_midnight_is_attributed_only_to_second_day_in_source_rollups(): void
    {
        $cairoTz = 'Africa/Cairo';
        // Adjacent days: 2026-09-18 and 2026-09-19
        // Cairo midnight separating Day 1 and Day 2 is 2026-09-19 00:00:00 Cairo
        // In summer (+03:00), 2026-09-19 00:00:00 Cairo is exactly 2026-09-18 21:00:00 UTC
        $midnightCairo = CarbonImmutable::parse('2026-09-19 00:00:00', $cairoTz);
        $midnightUtc = $midnightCairo->setTimezone('UTC');

        $vis = Visitor::create([
            'visitor_token' => 'vis-exact-midnight',
            'first_seen_at' => $midnightUtc,
            'last_seen_at' => $midnightUtc,
            'is_bot' => false,
        ]);

        VisitorSession::create([
            'session_token' => 'sess-exact-midnight',
            'visitor_id' => $vis->id,
            'visitor_token' => $vis->visitor_token,
            'utm_source' => 'google',
            'started_at' => $midnightUtc,
            'last_activity_at' => $midnightUtc->addMinutes(10),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Testing',
            'is_bot' => false,
            'landing_page' => '/',
        ]);

        // Aggregate both adjacent days
        Artisan::call('analytics:aggregate-daily', ['--date' => '2026-09-18']);
        Artisan::call('analytics:aggregate-daily', ['--date' => '2026-09-19']);

        // Assert on Day 1 (2026-09-18): midnight session must NOT appear
        $day1OverallSessions = DailyMetric::where('metric_date', '2026-09-18')
            ->where('metric_name', 'sessions')
            ->value('count');
        $day1SourceSessions = DailyMetric::where('metric_date', '2026-09-18')
            ->where('metric_name', 'sessions_by_source')
            ->where('dimension_value', 'google')
            ->value('count');

        $this->assertEquals(0, $day1OverallSessions ?? 0, 'Overall sessions on Day 1 must be 0 for exact midnight session');
        $this->assertEquals(0, $day1SourceSessions ?? 0, 'Source-filtered sessions on Day 1 must be 0 for exact midnight session');

        // Assert on Day 2 (2026-09-19): midnight session MUST appear
        $day2OverallSessions = DailyMetric::where('metric_date', '2026-09-19')
            ->where('metric_name', 'sessions')
            ->value('count');
        $day2SourceSessions = DailyMetric::where('metric_date', '2026-09-19')
            ->where('metric_name', 'sessions_by_source')
            ->where('dimension_value', 'google')
            ->value('count');

        $this->assertEquals(1, $day2OverallSessions, 'Overall sessions on Day 2 must be 1 for exact midnight session');
        $this->assertEquals(1, $day2SourceSessions, 'Source-filtered sessions on Day 2 must be 1 for exact midnight session');

        // Now prune raw visitor sessions
        VisitorSession::query()->delete();
        AnalyticsEvent::query()->delete();

        // Query pruned source-filtered reports for both days
        $startDay1 = CarbonImmutable::parse('2026-09-18 00:00:00', $cairoTz)->setTimezone('UTC');
        $endDay1 = CarbonImmutable::parse('2026-09-18 23:59:59', $cairoTz)->setTimezone('UTC');
        $day1Report = $this->reportService->getTrafficReport($startDay1, $endDay1, 'google');
        $day1Row = $day1Report['rows']->firstWhere('report_date', '2026-09-18');
        $this->assertEquals(0, $day1Row->sessions ?? 0, 'Pruned report for Day 1 must have 0 sessions for google');
        $this->assertEquals(0, $day1Report['summary']['sessions'], 'Pruned summary for Day 1 must have 0 sessions for google');

        $startDay2 = CarbonImmutable::parse('2026-09-19 00:00:00', $cairoTz)->setTimezone('UTC');
        $endDay2 = CarbonImmutable::parse('2026-09-19 23:59:59', $cairoTz)->setTimezone('UTC');
        $day2Report = $this->reportService->getTrafficReport($startDay2, $endDay2, 'google');
        $day2Row = $day2Report['rows']->firstWhere('report_date', '2026-09-19');
        $this->assertEquals(1, $day2Row->sessions, 'Pruned report for Day 2 must have 1 session for google');
        $this->assertEquals(1, $day2Report['summary']['sessions'], 'Pruned summary for Day 2 must have 1 session for google');
    }

    public function test_admin_summary_discloses_daily_sum_basis_and_suppresses_mixed_basis_growth_rate(): void
    {
        $admin = Administrator::create([
            'name' => 'Admin User',
            'email' => 'admin-test@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $cairoTz = 'Africa/Cairo';
        // Current period: 2025-03-10 to 2025-03-11 (2 pruned days)
        // Previous period: 2025-03-08 to 2025-03-09 (2 days with raw events)
        $start = CarbonImmutable::parse('2025-03-10 00:00:00', $cairoTz)->setTimezone('UTC');
        $end = CarbonImmutable::parse('2025-03-11 23:59:59', $cairoTz)->setTimezone('UTC');

        // Current period: Daily rollups (raw events pruned)
        DailyMetric::create([
            'metric_date' => '2025-03-10',
            'metric_name' => 'unique_visitors',
            'dimension_key' => '',
            'dimension_value' => '',
            'count' => 25,
        ]);
        DailyMetric::create([
            'metric_date' => '2025-03-11',
            'metric_name' => 'unique_visitors',
            'dimension_key' => '',
            'dimension_value' => '',
            'count' => 35,
        ]);

        // Previous period: Raw events exist (exact unique visitor identity)
        $prevTime = CarbonImmutable::parse('2025-03-08 12:00:00', $cairoTz)->setTimezone('UTC');
        $vis = Visitor::create([
            'visitor_token' => 'vis-prev-raw-1',
            'first_seen_at' => $prevTime,
            'last_seen_at' => $prevTime,
            'is_bot' => false,
        ]);
        AnalyticsEvent::create([
            'visitor_id' => $vis->id,
            'visitor_token' => $vis->visitor_token,
            'event_name' => 'page_view',
            'created_at' => $prevTime,
            'is_bot' => false,
        ]);

        // 1. Assert Service Level: ReportService marks basis as sum of daily uniques and marks non-comparable
        $report = $this->reportService->getTrafficReport($start, $end);
        $this->assertEquals(60, $report['summary']['visitors']);
        $this->assertEquals('sum_of_daily_uniques', $report['summary']['visitors_basis']);
        $this->assertTrue($report['summary']['visitors_is_daily_sum']);
        $this->assertEquals('exact_unique_visitors', $report['summary']['prev_visitors_basis']);
        $this->assertFalse($report['summary']['prev_visitors_is_daily_sum']);
        $this->assertFalse($report['summary']['is_comparable_visitors'], 'Mixed counting bases must be flagged as non-comparable');
        $this->assertNull($report['summary']['visitor_change_pct'], 'Growth percentage must be suppressed (null) for mixed bases');

        // 2. Assert View / HTTP Level: Admin summary discloses daily-sum basis and suppresses percentage comparison
        $response = $this->actingAs($admin, 'web')->get(route('admin.reports.index', [
            'type' => 'traffic',
            'range' => 'custom',
            'start_date' => '2025-03-10',
            'end_date' => '2025-03-11',
        ]));

        $response->assertOk();
        $response->assertSee('Sum of Daily Unique Visitors');
        $response->assertSee('Non-comparable');
        $response->assertSee('Raw events pruned across multi-day range; total represents sum of daily uniques rather than deduplicated visitors.');
        $response->assertDontSee('% vs prev period');
    }

    public function test_partially_pruned_previous_period_reconciles_rollups_and_marks_comparison_non_comparable(): void
    {
        $admin = Administrator::create([
            'name' => 'Admin User',
            'email' => 'admin-v1r07c@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $cairoTz = 'Africa/Cairo';

        // Current period: 2025-05-03 00:00:00 to 2025-05-04 23:59:59 (Cairo)
        // Previous period: 2025-05-01 00:00:00 to 2025-05-02 23:59:59 (Cairo)
        $start = CarbonImmutable::parse('2025-05-03 00:00:00', $cairoTz)->setTimezone('UTC');
        $end = CarbonImmutable::parse('2025-05-04 23:59:59', $cairoTz)->setTimezone('UTC');

        // --- Previous Period Setup ---
        // Day 1 (2025-05-01): PRUNED - represented exclusively by durable DailyMetric rollups
        DailyMetric::create([
            'metric_date' => '2025-05-01',
            'metric_name' => 'unique_visitors',
            'dimension_key' => '',
            'dimension_value' => '',
            'count' => 15,
        ]);
        DailyMetric::create([
            'metric_date' => '2025-05-01',
            'metric_name' => 'sessions',
            'dimension_key' => '',
            'dimension_value' => '',
            'count' => 20,
        ]);
        DailyMetric::create([
            'metric_date' => '2025-05-01',
            'metric_name' => 'page_view',
            'dimension_key' => '',
            'dimension_value' => '',
            'count' => 35,
        ]);
        // Source rollups for Day 1
        DailyMetric::create([
            'metric_date' => '2025-05-01',
            'metric_name' => 'visitors_by_source',
            'dimension_key' => 'source',
            'dimension_value' => 'google',
            'count' => 8,
        ]);
        DailyMetric::create([
            'metric_date' => '2025-05-01',
            'metric_name' => 'sessions_by_source',
            'dimension_key' => 'source',
            'dimension_value' => 'google',
            'count' => 10,
        ]);
        DailyMetric::create([
            'metric_date' => '2025-05-01',
            'metric_name' => 'page_views_by_source',
            'dimension_key' => 'source',
            'dimension_value' => 'google',
            'count' => 18,
        ]);
        DailyMetric::create([
            'metric_date' => '2025-05-01',
            'metric_name' => 'visitors_by_source',
            'dimension_key' => 'source',
            'dimension_value' => 'facebook',
            'count' => 7,
        ]);
        DailyMetric::create([
            'metric_date' => '2025-05-01',
            'metric_name' => 'sessions_by_source',
            'dimension_key' => 'source',
            'dimension_value' => 'facebook',
            'count' => 10,
        ]);
        DailyMetric::create([
            'metric_date' => '2025-05-01',
            'metric_name' => 'page_views_by_source',
            'dimension_key' => 'source',
            'dimension_value' => 'facebook',
            'count' => 17,
        ]);

        // Day 2 (2025-05-02): RETAINED RAW DATA (raw events and sessions exist, no DailyMetric)
        $day2Time1 = CarbonImmutable::parse('2025-05-02 10:00:00', $cairoTz)->setTimezone('UTC');
        $vis1 = Visitor::create([
            'visitor_token' => 'vis-prev-day2-g1',
            'first_seen_at' => $day2Time1,
            'last_seen_at' => $day2Time1,
            'is_bot' => false,
        ]);
        $sess1 = VisitorSession::create([
            'session_token' => 'sess-prev-day2-g1',
            'visitor_id' => $vis1->id,
            'started_at' => $day2Time1,
            'last_activity_at' => $day2Time1->addMinutes(15),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Testing',
            'utm_source' => 'google',
            'is_bot' => false,
            'landing_page' => '/',
        ]);
        AnalyticsEvent::create([
            'visitor_id' => $vis1->id,
            'visitor_token' => $vis1->visitor_token,
            'session_id' => $sess1->id,
            'session_token' => $sess1->session_token,
            'event_name' => 'page_view',
            'utm_source' => 'google',
            'created_at' => $day2Time1,
            'is_bot' => false,
        ]);
        AnalyticsEvent::create([
            'visitor_id' => $vis1->id,
            'visitor_token' => $vis1->visitor_token,
            'session_id' => $sess1->id,
            'session_token' => $sess1->session_token,
            'event_name' => 'page_view',
            'utm_source' => 'google',
            'created_at' => $day2Time1->addMinutes(5),
            'is_bot' => false,
        ]);

        $day2Time2 = CarbonImmutable::parse('2025-05-02 11:00:00', $cairoTz)->setTimezone('UTC');
        $vis2 = Visitor::create([
            'visitor_token' => 'vis-prev-day2-g2',
            'first_seen_at' => $day2Time2,
            'last_seen_at' => $day2Time2,
            'is_bot' => false,
        ]);
        $sess2 = VisitorSession::create([
            'session_token' => 'sess-prev-day2-g2',
            'visitor_id' => $vis2->id,
            'started_at' => $day2Time2,
            'last_activity_at' => $day2Time2->addMinutes(10),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Testing',
            'utm_source' => 'google',
            'is_bot' => false,
            'landing_page' => '/',
        ]);
        AnalyticsEvent::create([
            'visitor_id' => $vis2->id,
            'visitor_token' => $vis2->visitor_token,
            'session_id' => $sess2->id,
            'session_token' => $sess2->session_token,
            'event_name' => 'page_view',
            'utm_source' => 'google',
            'created_at' => $day2Time2,
            'is_bot' => false,
        ]);

        $day2Time3 = CarbonImmutable::parse('2025-05-02 12:00:00', $cairoTz)->setTimezone('UTC');
        $vis3 = Visitor::create([
            'visitor_token' => 'vis-prev-day2-fb1',
            'first_seen_at' => $day2Time3,
            'last_seen_at' => $day2Time3,
            'is_bot' => false,
        ]);
        $sess3 = VisitorSession::create([
            'session_token' => 'sess-prev-day2-fb1',
            'visitor_id' => $vis3->id,
            'started_at' => $day2Time3,
            'last_activity_at' => $day2Time3->addMinutes(10),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Testing',
            'utm_source' => 'facebook',
            'is_bot' => false,
            'landing_page' => '/',
        ]);
        AnalyticsEvent::create([
            'visitor_id' => $vis3->id,
            'visitor_token' => $vis3->visitor_token,
            'session_id' => $sess3->id,
            'session_token' => $sess3->session_token,
            'event_name' => 'page_view',
            'utm_source' => 'facebook',
            'created_at' => $day2Time3,
            'is_bot' => false,
        ]);

        // --- Current Period Setup ---
        // 2025-05-03 and 2025-05-04: RETAINED RAW DATA (exact unique visitors)
        $currTime1 = CarbonImmutable::parse('2025-05-03 14:00:00', $cairoTz)->setTimezone('UTC');
        $visCurr1 = Visitor::create([
            'visitor_token' => 'vis-curr-1',
            'first_seen_at' => $currTime1,
            'last_seen_at' => $currTime1,
            'is_bot' => false,
        ]);
        $sessCurr1 = VisitorSession::create([
            'session_token' => 'sess-curr-1',
            'visitor_id' => $visCurr1->id,
            'started_at' => $currTime1,
            'last_activity_at' => $currTime1->addMinutes(10),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Testing',
            'utm_source' => 'google',
            'is_bot' => false,
            'landing_page' => '/',
        ]);
        AnalyticsEvent::create([
            'visitor_id' => $visCurr1->id,
            'visitor_token' => $visCurr1->visitor_token,
            'session_id' => $sessCurr1->id,
            'session_token' => $sessCurr1->session_token,
            'event_name' => 'page_view',
            'utm_source' => 'google',
            'created_at' => $currTime1,
            'is_bot' => false,
        ]);

        $currTime2 = CarbonImmutable::parse('2025-05-04 15:00:00', $cairoTz)->setTimezone('UTC');
        $visCurr2 = Visitor::create([
            'visitor_token' => 'vis-curr-2',
            'first_seen_at' => $currTime2,
            'last_seen_at' => $currTime2,
            'is_bot' => false,
        ]);
        $sessCurr2 = VisitorSession::create([
            'session_token' => 'sess-curr-2',
            'visitor_id' => $visCurr2->id,
            'started_at' => $currTime2,
            'last_activity_at' => $currTime2->addMinutes(10),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Testing',
            'utm_source' => 'google',
            'is_bot' => false,
            'landing_page' => '/',
        ]);
        AnalyticsEvent::create([
            'visitor_id' => $visCurr2->id,
            'visitor_token' => $visCurr2->visitor_token,
            'session_id' => $sessCurr2->id,
            'session_token' => $sessCurr2->session_token,
            'event_name' => 'page_view',
            'utm_source' => 'google',
            'created_at' => $currTime2,
            'is_bot' => false,
        ]);

        // 1. Assert Unfiltered Report (V1-R07C):
        // Both Day 1 (DailyMetric: 15 vis, 20 sess, 35 pv) and Day 2 (Raw: 3 vis, 3 sess, 4 pv) MUST contribute!
        $report = $this->reportService->getTrafficReport($start, $end);

        $this->assertEquals(2, $report['summary']['visitors']);
        $this->assertEquals('exact_unique_visitors', $report['summary']['visitors_basis']);
        $this->assertFalse($report['summary']['visitors_is_daily_sum']);
        $this->assertEquals(2, $report['summary']['sessions']);
        $this->assertEquals(2, $report['summary']['page_views']);

        // Both previous days contribute to prev_visitors, prev_sessions, prev_page_views
        $this->assertEquals(18, $report['summary']['prev_visitors'], 'Previous visitors must reconcile Day 1 rollups (15) and Day 2 raw (3)');
        $this->assertEquals(23, $report['summary']['prev_sessions'], 'Previous sessions must reconcile Day 1 rollups (20) and Day 2 raw (3)');
        $this->assertEquals(39, $report['summary']['prev_page_views'], 'Previous page views must reconcile Day 1 rollups (35) and Day 2 raw (4)');

        // Previous visitor basis is a daily sum because Day 1 needed a rollup
        $this->assertEquals('sum_of_daily_uniques', $report['summary']['prev_visitors_basis']);
        $this->assertTrue($report['summary']['prev_visitors_is_daily_sum']);

        // Mixed counting bases must suppress comparison growth rate
        $this->assertFalse($report['summary']['is_comparable_visitors']);
        $this->assertNull($report['summary']['visitor_change_pct']);

        // 2. Assert Source-Filtered Report (V1-R07C source isolation on partially pruned previous period):
        // Day 1 google (DailyMetric: 8 vis, 10 sess, 18 pv) + Day 2 google (Raw: 2 vis, 2 sess, 3 pv)
        $googleReport = $this->reportService->getTrafficReport($start, $end, 'google');

        $this->assertEquals(2, $googleReport['summary']['visitors']);
        $this->assertEquals('exact_unique_visitors', $googleReport['summary']['visitors_basis']);
        $this->assertFalse($googleReport['summary']['visitors_is_daily_sum']);

        $this->assertEquals(10, $googleReport['summary']['prev_visitors'], 'Source-filtered prev visitors must reconcile Day 1 google rollups (8) and Day 2 google raw (2)');
        $this->assertEquals(12, $googleReport['summary']['prev_sessions'], 'Source-filtered prev sessions must reconcile Day 1 google rollups (10) and Day 2 google raw (2)');
        $this->assertEquals(21, $googleReport['summary']['prev_page_views'], 'Source-filtered prev page views must reconcile Day 1 google rollups (18) and Day 2 google raw (3)');

        $this->assertEquals('sum_of_daily_uniques', $googleReport['summary']['prev_visitors_basis']);
        $this->assertTrue($googleReport['summary']['prev_visitors_is_daily_sum']);
        $this->assertFalse($googleReport['summary']['is_comparable_visitors']);
        $this->assertNull($googleReport['summary']['visitor_change_pct']);

        // 3. Assert View / Admin UI: Exact-current vs partial-pruned-previous comparison is marked non-comparable
        $response = $this->actingAs($admin, 'web')->get(route('admin.reports.index', [
            'type' => 'traffic',
            'range' => 'custom',
            'start_date' => '2025-05-03',
            'end_date' => '2025-05-04',
        ]));

        $response->assertOk();
        $response->assertSee('Unique Visitors'); // current is exact
        $response->assertSee('Non-comparable');
        $response->assertSee('Previous period: 18');
        $response->assertSee('(sum of daily uniques)');
        $response->assertDontSee('% vs prev period');
    }

    public function test_partially_pruned_same_day_uses_authoritative_daily_rollups_for_unfiltered_and_source_reports(): void
    {
        $cairoTz = 'Africa/Cairo';
        $targetDate = '2025-06-15';
        $cairoStart = CarbonImmutable::parse("{$targetDate} 00:00:00", $cairoTz);
        $cairoEnd = CarbonImmutable::parse("{$targetDate} 23:59:59", $cairoTz);
        $start = $cairoStart->setTimezone('UTC');
        $end = $cairoEnd->setTimezone('UTC');

        // Seed Morning Records (08:00 Cairo = 05:00 UTC)
        $mornTime = CarbonImmutable::parse("{$targetDate} 08:00:00", $cairoTz)->setTimezone('UTC');

        // Vis 1 (google)
        $v1 = Visitor::create(['visitor_token' => 'v-same-1', 'first_seen_at' => $mornTime, 'last_seen_at' => $mornTime, 'is_bot' => false]);
        $s1 = VisitorSession::create(['session_token' => 's-same-1', 'visitor_id' => $v1->id, 'started_at' => $mornTime, 'last_activity_at' => $mornTime->addMinutes(15), 'ip_address' => '127.0.0.1', 'user_agent' => 'Testing', 'utm_source' => 'google', 'is_bot' => false, 'landing_page' => '/']);
        AnalyticsEvent::create(['visitor_id' => $v1->id, 'visitor_token' => $v1->visitor_token, 'session_id' => $s1->id, 'session_token' => $s1->session_token, 'event_name' => 'page_view', 'utm_source' => 'google', 'created_at' => $mornTime, 'is_bot' => false]);

        // Vis 2 (facebook)
        $v2 = Visitor::create(['visitor_token' => 'v-same-2', 'first_seen_at' => $mornTime, 'last_seen_at' => $mornTime, 'is_bot' => false]);
        $s2 = VisitorSession::create(['session_token' => 's-same-2', 'visitor_id' => $v2->id, 'started_at' => $mornTime, 'last_activity_at' => $mornTime->addMinutes(15), 'ip_address' => '127.0.0.1', 'user_agent' => 'Testing', 'utm_source' => 'facebook', 'is_bot' => false, 'landing_page' => '/']);
        AnalyticsEvent::create(['visitor_id' => $v2->id, 'visitor_token' => $v2->visitor_token, 'session_id' => $s2->id, 'session_token' => $s2->session_token, 'event_name' => 'page_view', 'utm_source' => 'facebook', 'created_at' => $mornTime, 'is_bot' => false]);

        // Vis 3 (google)
        $v3 = Visitor::create(['visitor_token' => 'v-same-3', 'first_seen_at' => $mornTime, 'last_seen_at' => $mornTime, 'is_bot' => false]);
        $s3 = VisitorSession::create(['session_token' => 's-same-3', 'visitor_id' => $v3->id, 'started_at' => $mornTime, 'last_activity_at' => $mornTime->addMinutes(15), 'ip_address' => '127.0.0.1', 'user_agent' => 'Testing', 'utm_source' => 'google', 'is_bot' => false, 'landing_page' => '/']);
        AnalyticsEvent::create(['visitor_id' => $v3->id, 'visitor_token' => $v3->visitor_token, 'session_id' => $s3->id, 'session_token' => $s3->session_token, 'event_name' => 'page_view', 'utm_source' => 'google', 'created_at' => $mornTime, 'is_bot' => false]);

        // Seed Afternoon Records (16:00 Cairo = 13:00 UTC)
        $aftTime = CarbonImmutable::parse("{$targetDate} 16:00:00", $cairoTz)->setTimezone('UTC');

        // Vis 4 (google)
        $v4 = Visitor::create(['visitor_token' => 'v-same-4', 'first_seen_at' => $aftTime, 'last_seen_at' => $aftTime, 'is_bot' => false]);
        $s4 = VisitorSession::create(['session_token' => 's-same-4', 'visitor_id' => $v4->id, 'started_at' => $aftTime, 'last_activity_at' => $aftTime->addMinutes(15), 'ip_address' => '127.0.0.1', 'user_agent' => 'Testing', 'utm_source' => 'google', 'is_bot' => false, 'landing_page' => '/']);
        AnalyticsEvent::create(['visitor_id' => $v4->id, 'visitor_token' => $v4->visitor_token, 'session_id' => $s4->id, 'session_token' => $s4->session_token, 'event_name' => 'page_view', 'utm_source' => 'google', 'created_at' => $aftTime, 'is_bot' => false]);

        // Vis 5 (direct)
        $v5 = Visitor::create(['visitor_token' => 'v-same-5', 'first_seen_at' => $aftTime, 'last_seen_at' => $aftTime, 'is_bot' => false]);
        $s5 = VisitorSession::create(['session_token' => 's-same-5', 'visitor_id' => $v5->id, 'started_at' => $aftTime, 'last_activity_at' => $aftTime->addMinutes(15), 'ip_address' => '127.0.0.1', 'user_agent' => 'Testing', 'utm_source' => null, 'is_bot' => false, 'landing_page' => '/']);
        AnalyticsEvent::create(['visitor_id' => $v5->id, 'visitor_token' => $v5->visitor_token, 'session_id' => $s5->id, 'session_token' => $s5->session_token, 'event_name' => 'page_view', 'utm_source' => null, 'created_at' => $aftTime, 'is_bot' => false]);

        // Aggregate full day into DailyMetric
        Artisan::call('analytics:aggregate-daily', ['--date' => $targetDate]);

        $this->assertDatabaseHas('daily_metrics', [
            'metric_date' => $targetDate,
            'metric_name' => 'unique_visitors',
            'count' => 5,
        ]);

        // Simulate partial pruning: delete early morning records (< 12:00 Cairo)
        $cutoff = CarbonImmutable::parse("{$targetDate} 12:00:00", $cairoTz)->setTimezone('UTC');
        AnalyticsEvent::where('created_at', '<', $cutoff)->delete();
        VisitorSession::where('started_at', '<', $cutoff)->delete();

        // Verify that raw database only has surviving afternoon records (2 visitors, 2 sessions, 2 page views)
        $this->assertEquals(2, AnalyticsEvent::whereBetween('created_at', [$start, $end])->count());
        $this->assertEquals(2, VisitorSession::whereBetween('started_at', [$start, $end])->count());

        // 1. Unfiltered report must return complete daily rollup totals, NOT the surviving raw count
        $report = $this->reportService->getTrafficReport($start, $end);
        $this->assertEquals(5, $report['summary']['visitors'], 'Unfiltered report must use authoritative full-day rollup visitors (5)');
        $this->assertEquals(5, $report['summary']['sessions'], 'Unfiltered report must use authoritative full-day rollup sessions (5)');
        $this->assertEquals(5, $report['summary']['page_views'], 'Unfiltered report must use authoritative full-day rollup page views (5)');
        $this->assertEquals('exact_unique_visitors', $report['summary']['visitors_basis']);

        // 2. Source-filtered report for 'google' must return full-day rollup (3), NOT surviving raw (1)
        $googleReport = $this->reportService->getTrafficReport($start, $end, 'google');
        $this->assertEquals(3, $googleReport['summary']['visitors'], 'Google report must use authoritative full-day rollup visitors (3)');
        $this->assertEquals(3, $googleReport['summary']['sessions'], 'Google report must use authoritative full-day rollup sessions (3)');
        $this->assertEquals(3, $googleReport['summary']['page_views'], 'Google report must use authoritative full-day rollup page views (3)');

        // 3. Source-filtered report for 'facebook' (which had all raw records pruned) must return full-day rollup (1)
        $fbReport = $this->reportService->getTrafficReport($start, $end, 'facebook');
        $this->assertEquals(1, $fbReport['summary']['visitors'], 'Facebook report must use authoritative rollup (1) even when all raw records were pruned');
        $this->assertEquals(1, $fbReport['summary']['sessions'], 'Facebook report must use authoritative rollup (1) even when all raw records were pruned');
        $this->assertEquals(1, $fbReport['summary']['page_views'], 'Facebook report must use authoritative rollup (1) even when all raw records were pruned');
    }

    public function test_retention_cleanup_preserves_raw_data_when_daily_rollup_is_missing_and_prunes_when_present(): void
    {
        $cairoTz = 'Africa/Cairo';
        Setting::set('analytics_retention_days', 30);

        // Day 1: 45 days ago (Missing rollup)
        $day1Cairo = CarbonImmutable::now($cairoTz)->subDays(45)->startOfDay();
        $day1Utc = $day1Cairo->addHours(10)->setTimezone('UTC');
        $day1DateStr = $day1Cairo->toDateString();

        $v1 = Visitor::create(['visitor_token' => 'v-ret-1', 'first_seen_at' => $day1Utc, 'last_seen_at' => $day1Utc, 'is_bot' => false]);
        $s1 = VisitorSession::create(['session_token' => 's-ret-1', 'visitor_id' => $v1->id, 'started_at' => $day1Utc, 'last_activity_at' => $day1Utc->addMinutes(10), 'ip_address' => '127.0.0.1', 'user_agent' => 'Testing', 'utm_source' => 'direct', 'is_bot' => false, 'landing_page' => '/']);
        $e1 = AnalyticsEvent::create(['visitor_id' => $v1->id, 'visitor_token' => $v1->visitor_token, 'session_id' => $s1->id, 'session_token' => $s1->session_token, 'event_name' => 'page_view', 'created_at' => $day1Utc, 'is_bot' => false]);

        // Day 2: 44 days ago (Rollup exists)
        $day2Cairo = CarbonImmutable::now($cairoTz)->subDays(44)->startOfDay();
        $day2Utc = $day2Cairo->addHours(10)->setTimezone('UTC');
        $day2DateStr = $day2Cairo->toDateString();

        $v2 = Visitor::create(['visitor_token' => 'v-ret-2', 'first_seen_at' => $day2Utc, 'last_seen_at' => $day2Utc, 'is_bot' => false]);
        $s2 = VisitorSession::create(['session_token' => 's-ret-2', 'visitor_id' => $v2->id, 'started_at' => $day2Utc, 'last_activity_at' => $day2Utc->addMinutes(10), 'ip_address' => '127.0.0.1', 'user_agent' => 'Testing', 'utm_source' => 'direct', 'is_bot' => false, 'landing_page' => '/']);
        $e2 = AnalyticsEvent::create(['visitor_id' => $v2->id, 'visitor_token' => $v2->visitor_token, 'session_id' => $s2->id, 'session_token' => $s2->session_token, 'event_name' => 'page_view', 'created_at' => $day2Utc, 'is_bot' => false]);

        // Create authoritative DailyMetric rollup for Day 2 only
        DailyMetric::create([
            'metric_date' => $day2DateStr,
            'metric_name' => 'unique_visitors',
            'dimension_key' => '',
            'dimension_value' => '',
            'count' => 1,
        ]);
        DailyMetric::create([
            'metric_date' => $day2DateStr,
            'metric_name' => 'sessions',
            'dimension_key' => '',
            'dimension_value' => '',
            'count' => 1,
        ]);
        DailyMetric::create([
            'metric_date' => $day2DateStr,
            'metric_name' => 'page_views',
            'dimension_key' => '',
            'dimension_value' => '',
            'count' => 1,
        ]);
        DailyCountryMetric::create([
            'metric_date' => $day2DateStr,
            'country_code' => 'ZZ',
            'unique_visitors' => 1,
            'sessions' => 1,
            'visitors' => 1,
            'page_views' => 1,
        ]);

        // Run retention pruning
        Artisan::call('analytics:aggregate-daily', ['--prune' => true]);

        // Day 1 has NO rollup -> raw events and sessions must be preserved to prevent data loss!
        $this->assertDatabaseHas('analytics_events', ['id' => $e1->id]);
        $this->assertDatabaseHas('visitor_sessions', ['id' => $s1->id]);

        // Day 2 HAS rollup -> raw records must be pruned safely
        $this->assertDatabaseMissing('analytics_events', ['id' => $e2->id]);
        $this->assertDatabaseMissing('visitor_sessions', ['id' => $s2->id]);
    }

    public function test_ninety_day_event_cleanup_never_deletes_an_unaggregated_cairo_day(): void
    {
        Setting::set('analytics_retention_days', 180);
        $dayCairo = CarbonImmutable::now('Africa/Cairo')->subDays(120)->startOfDay();
        $event = AnalyticsEvent::create([
            'event_name' => 'page_view',
            'page' => '/',
            'created_at' => $dayCairo->addHours(12)->setTimezone('UTC'),
            'is_bot' => false,
        ]);

        Artisan::call('analytics:aggregate-daily', ['--prune' => true]);
        $this->assertDatabaseHas('analytics_events', ['id' => $event->id]);

        DailyMetric::create([
            'metric_date' => $dayCairo->toDateString(),
            'metric_name' => 'unique_visitors',
            'dimension_key' => '',
            'dimension_value' => '',
            'count' => 1,
        ]);

        Artisan::call('analytics:aggregate-daily', ['--prune' => true]);
        $this->assertDatabaseHas('analytics_events', ['id' => $event->id]);

        DailyCountryMetric::create([
            'metric_date' => $dayCairo->toDateString(),
            'country_code' => 'ZZ',
            'unique_visitors' => 1,
            'sessions' => 0,
            'visitors' => 1,
            'page_views' => 1,
        ]);

        Artisan::call('analytics:aggregate-daily', ['--prune' => true]);
        $this->assertDatabaseMissing('analytics_events', ['id' => $event->id]);
    }

    public function test_previous_period_comparison_across_cairo_spring_and_fall_dst_transitions(): void
    {
        $cairoTz = 'Africa/Cairo';

        // 1. Cairo Spring-Forward DST Transition (April 24-25, 2026)
        $start = CarbonImmutable::parse('2026-04-24 00:00:00', $cairoTz)->setTimezone('UTC');
        $end = CarbonImmutable::parse('2026-04-25 23:59:59', $cairoTz)->setTimezone('UTC');

        // Previous period must be exactly April 22-23, 2026 starting at Cairo midnight
        $expectedPrevStartCairo = CarbonImmutable::parse('2026-04-22 00:00:00', $cairoTz);
        $prevStartExactUtc = $expectedPrevStartCairo->setTimezone('UTC');

        // Place a raw session and event at the exact previous period Cairo midnight
        $vPrev = Visitor::create(['visitor_token' => 'v-dst-prev-1', 'first_seen_at' => $prevStartExactUtc, 'last_seen_at' => $prevStartExactUtc, 'is_bot' => false]);
        $sPrev = VisitorSession::create(['session_token' => 's-dst-prev-1', 'visitor_id' => $vPrev->id, 'started_at' => $prevStartExactUtc, 'last_activity_at' => $prevStartExactUtc->addMinutes(10), 'ip_address' => '127.0.0.1', 'user_agent' => 'Testing', 'utm_source' => 'facebook', 'is_bot' => false, 'landing_page' => '/']);
        AnalyticsEvent::create(['visitor_id' => $vPrev->id, 'visitor_token' => $vPrev->visitor_token, 'session_id' => $sPrev->id, 'session_token' => $sPrev->session_token, 'event_name' => 'page_view', 'utm_source' => 'facebook', 'created_at' => $prevStartExactUtc, 'is_bot' => false]);

        // Place a current period session and event
        $currTimeUtc = CarbonImmutable::parse('2026-04-24 12:00:00', $cairoTz)->setTimezone('UTC');
        $vCurr = Visitor::create(['visitor_token' => 'v-dst-curr-1', 'first_seen_at' => $currTimeUtc, 'last_seen_at' => $currTimeUtc, 'is_bot' => false]);
        $sCurr = VisitorSession::create(['session_token' => 's-dst-curr-1', 'visitor_id' => $vCurr->id, 'started_at' => $currTimeUtc, 'last_activity_at' => $currTimeUtc->addMinutes(10), 'ip_address' => '127.0.0.1', 'user_agent' => 'Testing', 'utm_source' => 'facebook', 'is_bot' => false, 'landing_page' => '/']);
        AnalyticsEvent::create(['visitor_id' => $vCurr->id, 'visitor_token' => $vCurr->visitor_token, 'session_id' => $sCurr->id, 'session_token' => $sCurr->session_token, 'event_name' => 'page_view', 'utm_source' => 'facebook', 'created_at' => $currTimeUtc, 'is_bot' => false]);

        $report = $this->reportService->getTrafficReport($start, $end);

        // Previous start must be EXACT Cairo midnight (00:00:00), not shifted by elapsed UTC hours
        $this->assertEquals(
            '2026-04-22 00:00:00',
            CarbonImmutable::parse($report['summary']['prev_start'])->setTimezone($cairoTz)->toDateTimeString(),
            'Spring DST: Previous start must align to Cairo midnight'
        );
        $this->assertEquals(1, $report['summary']['prev_visitors'], 'Previous visitors must include exact-midnight visitor');
        $this->assertEquals(1, $report['summary']['prev_sessions'], 'Previous sessions must include exact-midnight session');
        $this->assertEquals(1, $report['summary']['prev_page_views'], 'Previous page views must include exact-midnight page view');
        $this->assertEquals(1, $report['summary']['visitors']);

        // Source-filtered reports must isolate previous-period midnight traffic
        $fbReport = $this->reportService->getTrafficReport($start, $end, 'facebook');
        $this->assertEquals(1, $fbReport['summary']['prev_visitors']);
        $this->assertEquals(1, $fbReport['summary']['prev_sessions']);

        $googleReport = $this->reportService->getTrafficReport($start, $end, 'google');
        $this->assertEquals(0, $googleReport['summary']['prev_visitors']);
        $this->assertEquals(0, $googleReport['summary']['prev_sessions']);

        // 2. Cairo Fall-Back DST Transition (October 30-31, 2026)
        $fallStart = CarbonImmutable::parse('2026-10-30 00:00:00', $cairoTz)->setTimezone('UTC');
        $fallEnd = CarbonImmutable::parse('2026-10-31 23:59:59', $cairoTz)->setTimezone('UTC');

        // Previous period must be exactly October 28-29, 2026 starting at Cairo midnight
        $expectedFallPrevStartCairo = CarbonImmutable::parse('2026-10-28 00:00:00', $cairoTz);
        $fallPrevStartExactUtc = $expectedFallPrevStartCairo->setTimezone('UTC');

        // Place a raw session and event at the exact previous period Cairo midnight
        $vFallPrev = Visitor::create(['visitor_token' => 'v-dst-fall-prev-1', 'first_seen_at' => $fallPrevStartExactUtc, 'last_seen_at' => $fallPrevStartExactUtc, 'is_bot' => false]);
        $sFallPrev = VisitorSession::create(['session_token' => 's-dst-fall-prev-1', 'visitor_id' => $vFallPrev->id, 'started_at' => $fallPrevStartExactUtc, 'last_activity_at' => $fallPrevStartExactUtc->addMinutes(10), 'ip_address' => '127.0.0.1', 'user_agent' => 'Testing', 'utm_source' => 'twitter', 'is_bot' => false, 'landing_page' => '/']);
        AnalyticsEvent::create(['visitor_id' => $vFallPrev->id, 'visitor_token' => $vFallPrev->visitor_token, 'session_id' => $sFallPrev->id, 'session_token' => $sFallPrev->session_token, 'event_name' => 'page_view', 'utm_source' => 'twitter', 'created_at' => $fallPrevStartExactUtc, 'is_bot' => false]);

        // Place a current period session and event
        $fallCurrTimeUtc = CarbonImmutable::parse('2026-10-30 12:00:00', $cairoTz)->setTimezone('UTC');
        $vFallCurr = Visitor::create(['visitor_token' => 'v-dst-fall-curr-1', 'first_seen_at' => $fallCurrTimeUtc, 'last_seen_at' => $fallCurrTimeUtc, 'is_bot' => false]);
        $sFallCurr = VisitorSession::create(['session_token' => 's-dst-fall-curr-1', 'visitor_id' => $vFallCurr->id, 'started_at' => $fallCurrTimeUtc, 'last_activity_at' => $fallCurrTimeUtc->addMinutes(10), 'ip_address' => '127.0.0.1', 'user_agent' => 'Testing', 'utm_source' => 'twitter', 'is_bot' => false, 'landing_page' => '/']);
        AnalyticsEvent::create(['visitor_id' => $vFallCurr->id, 'visitor_token' => $vFallCurr->visitor_token, 'session_id' => $sFallCurr->id, 'session_token' => $sFallCurr->session_token, 'event_name' => 'page_view', 'utm_source' => 'twitter', 'created_at' => $fallCurrTimeUtc, 'is_bot' => false]);

        $fallReport = $this->reportService->getTrafficReport($fallStart, $fallEnd);

        $this->assertEquals(
            '2026-10-28 00:00:00',
            CarbonImmutable::parse($fallReport['summary']['prev_start'])->setTimezone($cairoTz)->toDateTimeString(),
            'Fall DST: Previous start must align to Cairo midnight'
        );
        $this->assertEquals(1, $fallReport['summary']['prev_visitors'], 'Fall DST: Previous visitors must include exact-midnight visitor');
        $this->assertEquals(1, $fallReport['summary']['prev_sessions'], 'Fall DST: Previous sessions must include exact-midnight session');
        $this->assertEquals(1, $fallReport['summary']['prev_page_views'], 'Fall DST: Previous page views must include exact-midnight page view');
        $this->assertEquals(1, $fallReport['summary']['visitors']);

        $fallTwitterReport = $this->reportService->getTrafficReport($fallStart, $fallEnd, 'twitter');
        $this->assertEquals(1, $fallTwitterReport['summary']['prev_visitors']);
        $this->assertEquals(1, $fallTwitterReport['summary']['prev_sessions']);

        $fallGoogleReport = $this->reportService->getTrafficReport($fallStart, $fallEnd, 'google');
        $this->assertEquals(0, $fallGoogleReport['summary']['prev_visitors']);
        $this->assertEquals(0, $fallGoogleReport['summary']['prev_sessions']);
    }
}
