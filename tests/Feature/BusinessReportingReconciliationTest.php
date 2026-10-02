<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Models\Visitor;
use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Analytics\Services\EngagementCounterService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Notifications\Services\TelegramReadService;
use App\Domains\Reporting\Services\ReportService;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use App\Domains\Resources\Models\ResourceRequest;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class BusinessReportingReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_retained_historical_activity_reconciles_without_rollups_in_the_business_timezone(): void
    {
        Setting::set('business_timezone', 'Europe/Berlin');
        $start = CarbonImmutable::parse('2026-09-12', 'Europe/Berlin')->startOfDay();
        $end = $start->addDay()->endOfDay();
        $visitors = [];
        for ($i = 0; $i < 10; $i++) {
            $visitors[] = Visitor::create(['visitor_token' => 'matrix-visitor-'.$i, 'is_bot' => false, 'detected_country_code' => 'DE', 'first_seen_at' => $start->utc(), 'last_seen_at' => $end->utc()]);
        }
        for ($i = 0; $i < 17; $i++) {
            $instant = $start->addHours(10)->addDays($i % 2)->utc();
            VisitorSession::create(['visitor_id' => $visitors[$i % 10]->id, 'session_token' => 'matrix-session-'.$i, 'is_bot' => false, 'detected_country_code' => 'DE', 'started_at' => $instant, 'last_activity_at' => $instant->addMinutes(5)]);
        }
        for ($i = 0; $i < 42; $i++) {
            AnalyticsEvent::create(['event_name' => 'page_view', 'visitor_token' => $visitors[$i % 8]->visitor_token, 'session_token' => 'matrix-session-'.($i % 8), 'page' => '/', 'is_bot' => false, 'metadata' => ['detected_country_code' => 'DE'], 'created_at' => $start->addHours(11)->addDays($i % 2)->utc()]);
        }
        for ($i = 0; $i < 3; $i++) {
            AnalyticsEvent::create(['event_name' => 'resource_requested', 'visitor_token' => $visitors[$i]->visitor_token, 'is_bot' => false, 'metadata' => ['detected_country_code' => 'DE'], 'created_at' => $start->addHours(12)->utc()]);
        }
        $contact = Contact::create(['name' => 'Matrix QA', 'email' => 'matrix@example.org']);
        $category = ResourceCategory::create(['name' => 'Matrix QA', 'slug' => 'matrix-qa', 'active' => true]);
        $resource = Resource::create(['title' => 'Matrix guide', 'slug' => 'matrix-guide', 'category_id' => $category->id, 'status' => 'published', 'is_gated' => true, 'gate_mode' => 'A']);
        for ($i = 0; $i < 3; $i++) {
            ResourceRequest::create(['contact_id' => $contact->id, 'resource_id' => $resource->id, 'visitor_token' => $visitors[$i]->visitor_token, 'created_at' => $start->addHours(12)->utc()]);
        }
        $type = SessionType::create(['slug' => 'matrix-lesson', 'title' => 'Matrix lesson', 'duration_minutes' => 60, 'price' => '25.00', 'currency' => 'USD', 'active' => true]);
        for ($i = 0; $i < 2; $i++) {
            $instant = $start->addHours(14 + $i)->utc();
            Booking::forceCreate(['contact_id' => $contact->id, 'session_type_id' => $type->id, 'start_at_utc' => $instant, 'end_at_utc' => $instant->addHour(), 'status' => 'confirmed', 'visitor_token' => $visitors[$i]->visitor_token, 'detected_country_code' => 'DE', 'confirmation_token' => Str::random(64), 'idempotency_key' => Str::uuid()->toString(), 'created_at' => $instant] + app(TimezoneService::class)->createBookingSnapshot($instant, $instant->addHour(), 'America/New_York', 'Europe/Berlin'));
            AnalyticsEvent::create(['event_name' => 'booking_completed', 'visitor_token' => $visitors[$i]->visitor_token, 'is_bot' => false, 'metadata' => ['detected_country_code' => 'DE'], 'created_at' => $instant]);
        }
        $reports = app(ReportService::class);
        $analytics = app(AnalyticsService::class);
        $summary = $reports->getTrafficReport($start->utc(), $end->utc())['summary'];
        $this->assertSame(10, $summary['visitors']);
        $this->assertSame(17, $summary['sessions']);
        $this->assertSame(42, $summary['page_views']);
        $this->assertFalse($summary['visitors_is_daily_sum']);
        $countries = $analytics->reportingCountries($start, $end);
        $this->assertSame(10, (int) $countries->sum('unique_visitors'));
        $this->assertSame(17, (int) $countries->sum('sessions'));
        $this->assertSame(42, (int) $countries->sum('page_views'));
        $this->assertSame(3, (int) $countries->sum('resource_requests'));
        $this->assertSame(2, (int) $countries->sum('bookings_completed'));
        $metrics = $analytics->reportingMetrics($start, $end);
        $this->assertSame(17, (int) $metrics->where('metric_name', 'sessions')->sum('count'));
        $this->assertSame(42, (int) $metrics->where('metric_name', 'page_view')->sum('count'));
        $this->assertSame(3, (int) $metrics->where('metric_name', 'resource_requested')->sum('count'));
        $this->assertSame(2, (int) $metrics->where('metric_name', 'booking_completed')->sum('count'));
        $acquisition = $analytics->getAcquisitionPerformance($start->utc(), $end->utc());
        $this->assertSame(10, (int) $acquisition->sum('visitors'));
        $this->assertSame(17, (int) $acquisition->sum('sessions'));
        $this->assertSame(42, (int) $acquisition->sum('page_views'));
        $this->assertSame(3, (int) $acquisition->sum('leads'));
        $this->assertSame(2, (int) $acquisition->sum('bookings'));
        $this->assertSame(3, (int) $reports->getResourcesReport($start->utc(), $end->utc())['total_requests']);
        $this->assertCount(2, $reports->getBookingsReport($start->utc(), $end->utc())['rows']);
        $this->travelTo($end->subHours(1));
        $telegram = app(TelegramReadService::class)->stats(2);
        $this->assertStringContainsString('Unique visitors: 10', $telegram);
        $this->assertStringContainsString('Sessions: 17', $telegram);
        $this->assertStringContainsString('Page views: 42', $telegram);
        $admin = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $this->actingAs($admin, 'web')->get(route('admin.analytics'))->assertOk()->assertViewHas('traffic', fn ($traffic) => $traffic['visitors'] === 10 && $traffic['sessions'] === 17 && $traffic['page_views'] === 42);
        $export = $this->get(route('admin.reports.export', ['range' => 'custom', 'start_date' => '2026-09-12', 'end_date' => '2026-09-13', 'format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('10,17,42', $export);
        $xlsx = $this->get(route('admin.reports.export', ['range' => 'custom', 'start_date' => '2026-09-12', 'end_date' => '2026-09-13', 'format' => 'xlsx']))->assertOk();
        $workbook = IOFactory::load($xlsx->baseResponse->getFile()->getPathname());
        $sheet = $workbook->getActiveSheet();
        $lastRow = $sheet->getHighestRow();
        $this->assertSame([10, 17, 42], [$sheet->getCell('B'.$lastRow)->getValue(), $sheet->getCell('C'.$lastRow)->getValue(), $sheet->getCell('D'.$lastRow)->getValue()]);
        $workbook->disconnectWorksheets();
        Setting::set('analytics.goals', ['booking_completed']);
        $goal = $analytics->goalReport($start->utc(), $end->utc())->first();
        $this->assertSame(2, $goal['count']);
        $this->assertSame(20.0, (float) $goal['rate']);
        $this->travelTo(CarbonImmutable::parse('2026-10-02', 'Europe/Berlin'));
        $this->assertSame(['unique_visitors' => 10, 'sessions' => 17], app(EngagementCounterService::class)->getPreviousMonthTraffic());
        Setting::set('counters.monthly_traffic.source', 'sessions');
        $this->assertSame(17, app(EngagementCounterService::class)->getPublicCountersPayload()['monthly_traffic']['count']);
        Setting::set('counters.monthly_traffic.source', 'unique_visitors');
        $this->assertSame(10, app(EngagementCounterService::class)->getPublicCountersPayload()['monthly_traffic']['count']);
        $this->assertDatabaseCount('daily_metrics', 0);
    }

    public function test_rollups_preserve_their_timezone_partition_after_business_timezone_changes(): void
    {
        Setting::set('business_timezone', 'Europe/Berlin');
        $this->artisan('analytics:aggregate-daily', ['--date' => '2026-09-12'])->assertSuccessful();
        $this->assertDatabaseHas('daily_metrics', ['metric_date' => '2026-09-12', 'reporting_timezone' => 'Europe/Berlin']);
        $oldCount = DB::table('daily_metrics')->where('reporting_timezone', 'Europe/Berlin')->count();
        Setting::set('business_timezone', 'Asia/Dubai');
        $this->artisan('analytics:aggregate-daily', ['--date' => '2026-09-12'])->assertSuccessful();
        $this->assertSame($oldCount, DB::table('daily_metrics')->where('reporting_timezone', 'Europe/Berlin')->count());
        $this->assertDatabaseHas('daily_country_metrics', ['metric_date' => '2026-09-12', 'reporting_timezone' => 'Asia/Dubai']);
        $berlinKey = EngagementCounterService::cacheKey();
        Setting::set('business_timezone', 'Europe/Berlin');
        $this->assertNotSame($berlinKey, EngagementCounterService::cacheKey());
    }

    public function test_session_conversion_across_midnight_does_not_become_a_bounce_at_the_reporting_boundary(): void
    {
        Setting::set('business_timezone', 'Europe/Berlin');
        $start = CarbonImmutable::parse('2026-09-12', 'Europe/Berlin')->startOfDay();
        $sessionStart = $start->endOfDay()->subSeconds(3)->utc();
        $visitor = Visitor::create(['visitor_token' => 'midnight-visitor', 'is_bot' => false, 'detected_country_code' => 'DE', 'first_seen_at' => $sessionStart, 'last_seen_at' => $sessionStart->addSeconds(5)]);
        VisitorSession::create(['visitor_id' => $visitor->id, 'session_token' => 'midnight-session', 'is_bot' => false, 'detected_country_code' => 'DE', 'started_at' => $sessionStart, 'last_activity_at' => $sessionStart->addSeconds(5)]);
        foreach (['page_view' => $sessionStart, 'resource_requested' => $sessionStart->addSeconds(5)] as $event => $instant) {
            AnalyticsEvent::create(['event_name' => $event, 'visitor_token' => $visitor->visitor_token, 'session_token' => 'midnight-session', 'is_bot' => false, 'created_at' => $instant]);
        }
        $analytics = app(AnalyticsService::class);
        $this->assertSame(0, (int) $analytics->reportingMetrics($start, $start->endOfDay())->where('metric_name', 'bounced_sessions')->sum('count'));
        $this->assertSame(0, (int) $analytics->reportingCountries($start, $start->endOfDay())->sum('bounced_sessions_count'));
        $this->assertSame(0, (int) $analytics->reportingMetrics($start, $start->endOfDay())->where('metric_name', 'resource_requested')->sum('count'));
    }
}
