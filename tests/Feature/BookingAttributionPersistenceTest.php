<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Models\MarketingTouch;
use App\Domains\Analytics\Models\Visitor;
use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingHoldService;
use App\Domains\Booking\Services\BookingService;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Reporting\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingAttributionPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected SessionType $sessionType;

    protected AnalyticsService $analyticsService;

    protected BookingService $bookingService;

    protected BookingHoldService $holdService;

    protected ReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionType = SessionType::create([
            'title' => 'Arabic Conversational Lesson',
            'slug' => 'arabic-conversational-lesson',
            'duration_minutes' => 60,
            'price' => 25.00,
            'currency' => 'USD',
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

        $this->analyticsService = app(AnalyticsService::class);
        $this->bookingService = app(BookingService::class);
        $this->holdService = app(BookingHoldService::class);
        $this->reportService = app(ReportService::class);
    }

    public function test_full_booking_path_preserves_first_touch_and_records_last_non_direct_conversion_touch(): void
    {
        $visitorToken = (string) Str::uuid();

        // Day 1: Direct visit -> visitor created with no acquisition
        $resDay1 = $this->withCookies([
            '_va_visitor' => $visitorToken,
        ])->get('/');
        $resDay1->assertOk();

        $sessionToken = $resDay1->getCookie('_va_session')?->getValue();

        $visitor = Visitor::where('visitor_token', $visitorToken)->first();
        $this->assertNotNull($visitor);
        $this->assertNull($visitor->acquisition_source, 'Day 1 direct visit must not set acquisition source');

        // Day 5: Visit from YouTube campaign -> locks First Non-Direct Touch
        $resDay5 = $this->withCookies([
            '_va_visitor' => $visitorToken,
        ])->get('/?utm_source=youtube&utm_medium=video&utm_campaign=egyptian-101&utm_content=v1');
        $resDay5->assertOk();

        $day5Session = $resDay5->getCookie('_va_session')?->getValue();

        $visitor->refresh();
        $this->assertEquals('youtube', $visitor->acquisition_source);
        $this->assertEquals('egyptian-101', $visitor->acquisition_campaign);
        $this->assertEquals('v1', $visitor->acquisition_content);

        // Backdate Day 5 touch to 15 days ago in the simulated timeline
        MarketingTouch::where('visitor_id', $visitor->id)->where('utm_source', 'youtube')
            ->update(['touch_at' => CarbonImmutable::now()->subDays(15)]);

        // Day 15: Visit from Telegram (5 days ago) -> adds a more recent marketing touch, but acquisition remains YouTube
        $day15Session = (string) Str::uuid();
        $day15Time = CarbonImmutable::now()->subDays(5);
        $this->analyticsService->recordMarketingTouch(
            visitor: $visitor,
            sessionToken: $day15Session,
            utmSource: 'telegram',
            utmMedium: 'social',
            utmCampaign: 'telegram-discount',
            utmContent: 'promo-post-42',
            utmTerm: 'egyptian-arabic',
            referrer: 'https://t.me/channel',
            touchAt: $day15Time
        );

        $visitor->refresh();
        $this->assertEquals('youtube', $visitor->acquisition_source, 'Acquisition source must remain immutable YouTube');

        // Day 20: Direct booking -> visitor comes directly to book
        $resDay20 = $this->withCookies([
            '_va_visitor' => $visitorToken,
        ])->get('/booking');
        $bookingSession = $resDay20->getCookie('_va_session')?->getValue() ?? (string) Str::uuid();

        // Ensure session exists in DB
        VisitorSession::firstOrCreate(
            ['session_token' => $bookingSession],
            [
                'visitor_id' => $visitor->id,
                'started_at' => now(),
                'last_activity_at' => now(),
            ]
        );

        $slotStart = CarbonImmutable::now('UTC')->addDays(2)->setTime(12, 0, 0);
        $slotEnd = $slotStart->addMinutes(60);

        $hold = $this->holdService->acquireHold(
            visitorToken: $visitorToken,
            sessionToken: $bookingSession,
            sessionType: $this->sessionType,
            startUtc: $slotStart,
            endUtc: $slotEnd,
            durationMinutes: 10
        );

        $booking = $this->bookingService->createPublicBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slotStart,
            'end_at_utc' => $slotEnd,
            'customer_timezone' => 'Africa/Cairo',
            'customer_name' => 'Ahmed Visitor',
            'customer_email' => 'ahmed.visitor@example.com',
            'idempotency_key' => 'idemp-attr-test-1',
            'hold_id' => $hold->id,
            'hold_token' => $hold->hold_token,
            'visitor_token' => $visitorToken,
            'session_token' => $bookingSession,
        ]);

        $this->assertNotNull($booking);
        // Assert conversion attribution resolved from Last Non-Direct Touch (Telegram)
        $this->assertEquals('telegram', $booking->source);
        $this->assertEquals('social', $booking->medium);
        $this->assertEquals('telegram-discount', $booking->campaign);
        $this->assertEquals('promo-post-42', $booking->content);
        $this->assertEquals('egyptian-arabic', $booking->term);
        $this->assertEquals('https://t.me/channel', $booking->referrer);
        $this->assertNotNull($booking->touch_at);
        $this->assertEquals($day15Time->toDateTimeString(), $booking->touch_at->toDateTimeString());
    }

    public function test_booking_attribution_30_day_lookback_boundary_inclusion(): void
    {
        $visitorToken = (string) Str::uuid();
        $visitor = Visitor::create([
            'visitor_token' => $visitorToken,
            'first_seen_at' => CarbonImmutable::parse('2026-08-01 12:00:00'),
            'last_seen_at' => CarbonImmutable::parse('2026-10-01 12:00:00'),
        ]);

        $bookingTime = CarbonImmutable::parse('2026-10-01 12:00:00');
        // Exact 30 days prior (2026-09-01 12:00:00) -> inclusive lower bound
        $exact30DaysPrior = $bookingTime->subDays(30);

        MarketingTouch::create([
            'visitor_id' => $visitor->id,
            'visitor_token' => $visitorToken,
            'utm_source' => 'youtube',
            'utm_campaign' => 'boundary-test',
            'touch_at' => $exact30DaysPrior,
        ]);

        $attr = $this->analyticsService->getBookingConversionAttribution($visitorToken, $bookingTime);
        $this->assertEquals('youtube', $attr['utm_source'], 'Exact 30-day prior boundary must be inclusive');
        $this->assertEquals('boundary-test', $attr['utm_campaign']);
    }

    public function test_booking_attribution_older_than_30_days_falls_back_to_direct(): void
    {
        $visitorToken = (string) Str::uuid();
        $visitor = Visitor::create([
            'visitor_token' => $visitorToken,
            'first_seen_at' => CarbonImmutable::parse('2026-08-01 12:00:00'),
            'last_seen_at' => CarbonImmutable::parse('2026-10-01 12:00:00'),
        ]);

        $bookingTime = CarbonImmutable::parse('2026-10-01 12:00:00');
        // 30 days + 1 second prior -> strictly excluded
        $past30Days = $bookingTime->subDays(30)->subSecond();

        MarketingTouch::create([
            'visitor_id' => $visitor->id,
            'visitor_token' => $visitorToken,
            'utm_source' => 'youtube',
            'utm_campaign' => 'too-old-campaign',
            'touch_at' => $past30Days,
        ]);

        $attr = $this->analyticsService->getBookingConversionAttribution($visitorToken, $bookingTime);
        $this->assertEquals('Direct / None', $attr['utm_source'], 'Touches older than 30 days must fall back to Direct / None');
        $this->assertNull($attr['utm_campaign']);
        $this->assertNull($attr['touch_at']);
    }

    public function test_within_session_campaign_change_creates_new_touch_and_attributes_booking(): void
    {
        $visitorToken = (string) Str::uuid();

        // 1. Initial request on session arrives with Campaign 1 (Google)
        $res1 = $this->withCookies([
            '_va_visitor' => $visitorToken,
        ])->get('/?utm_source=google&utm_medium=cpc&utm_campaign=search-egyptian&utm_content=ad-1');

        $sessionToken = $res1->getCookie('_va_session')?->getValue();
        $this->assertNotNull($sessionToken);

        $session = VisitorSession::where('session_token', $sessionToken)->first();
        $this->assertNotNull($session);
        $this->assertEquals('google', $session->utm_source);

        // 2. 5 minutes later within the active session, visitor clicks Campaign 2 (Facebook)
        $this->withCookies([
            '_va_visitor' => $visitorToken,
            '_va_session' => $sessionToken,
        ])->get('/?utm_source=facebook&utm_medium=feed&utm_campaign=social-discount&utm_content=creative-banner');

        $touches = MarketingTouch::where('visitor_token', $visitorToken)->orderBy('touch_at')->get();
        $this->assertCount(2, $touches, 'Within-session campaign arrival must persist a distinct marketing touch');
        $this->assertEquals('facebook', $touches->last()->utm_source);
        $this->assertEquals('social-discount', $touches->last()->utm_campaign);
        $this->assertEquals('creative-banner', $touches->last()->utm_content);

        // 3. Complete direct booking
        $slotStart = CarbonImmutable::now('UTC')->addDays(3)->setTime(10, 0, 0);
        $slotEnd = $slotStart->addMinutes(60);

        $hold = $this->holdService->acquireHold(
            visitorToken: $visitorToken,
            sessionToken: $sessionToken,
            sessionType: $this->sessionType,
            startUtc: $slotStart,
            endUtc: $slotEnd,
            durationMinutes: 10
        );

        $booking = $this->bookingService->createPublicBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slotStart,
            'end_at_utc' => $slotEnd,
            'customer_timezone' => 'Africa/Cairo',
            'customer_name' => 'Sara Booking',
            'customer_email' => 'sara@example.com',
            'idempotency_key' => 'idemp-within-session-1',
            'hold_id' => $hold->id,
            'hold_token' => $hold->hold_token,
            'visitor_token' => $visitorToken,
            'session_token' => $sessionToken,
        ]);

        $this->assertEquals('facebook', $booking->source);
        $this->assertEquals('social-discount', $booking->campaign);
        $this->assertEquals('creative-banner', $booking->content);
        $this->assertNotNull($booking->touch_at);
    }

    public function test_granular_campaign_content_attribution_report(): void
    {
        $start = CarbonImmutable::parse('2026-09-01 00:00:00');
        $end = CarbonImmutable::parse('2026-09-30 23:59:59');

        // Visitor 1 & 2 arriving via Campaign: survival-arabic, Content: video-1
        AnalyticsEvent::create([
            'event_name' => 'page_view',
            'visitor_token' => 'vis-camp-1',
            'session_token' => 'sess-1',
            'utm_source' => 'youtube',
            'utm_campaign' => 'survival-arabic',
            'utm_content' => 'video-1',
            'created_at' => CarbonImmutable::parse('2026-09-10 10:00:00'),
        ]);
        AnalyticsEvent::create([
            'event_name' => 'page_view',
            'visitor_token' => 'vis-camp-2',
            'session_token' => 'sess-2',
            'utm_source' => 'youtube',
            'utm_campaign' => 'survival-arabic',
            'utm_content' => 'video-1',
            'created_at' => CarbonImmutable::parse('2026-09-10 11:00:00'),
        ]);

        // Visitor 3 arriving via Campaign: survival-arabic, Content: video-2
        AnalyticsEvent::create([
            'event_name' => 'page_view',
            'visitor_token' => 'vis-camp-3',
            'session_token' => 'sess-3',
            'utm_source' => 'youtube',
            'utm_campaign' => 'survival-arabic',
            'utm_content' => 'video-2',
            'created_at' => CarbonImmutable::parse('2026-09-12 14:00:00'),
        ]);

        $contact = Contact::create([
            'name' => 'Report Contact',
            'email' => 'contact@example.com',
            'display_email' => 'contact@example.com',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        // Downstream bookings:
        // 1 confirmed booking for video-1
        Booking::create([
            'contact_id' => $contact->id,
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => '2026-09-20 10:00:00',
            'end_at_utc' => '2026-09-20 11:00:00',
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'UTC',
            'business_local_date_at_booking' => '2026-09-20',
            'business_local_start_time_at_booking' => '12:00:00',
            'business_local_end_time_at_booking' => '13:00:00',
            'customer_local_date_at_booking' => '2026-09-20',
            'customer_local_start_time_at_booking' => '10:00:00',
            'customer_local_end_time_at_booking' => '11:00:00',
            'business_utc_offset_at_booking' => '+02:00',
            'customer_utc_offset_at_booking' => '+00:00',
            'idempotency_key' => 'idem-report-1',
            'confirmation_token' => 'conf-report-1',
            'visitor_token' => 'vis-camp-1',
            'touch_at' => CarbonImmutable::parse('2026-09-10 10:00:00'),
            'source' => 'youtube',
            'campaign' => 'survival-arabic',
            'content' => 'video-1',
            'status' => 'confirmed',
            'created_at' => CarbonImmutable::parse('2026-09-15 10:00:00'),
        ]);

        // 2 confirmed bookings for video-2
        Booking::create([
            'contact_id' => $contact->id,
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => '2026-09-21 10:00:00',
            'end_at_utc' => '2026-09-21 11:00:00',
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'UTC',
            'business_local_date_at_booking' => '2026-09-21',
            'business_local_start_time_at_booking' => '12:00:00',
            'business_local_end_time_at_booking' => '13:00:00',
            'customer_local_date_at_booking' => '2026-09-21',
            'customer_local_start_time_at_booking' => '10:00:00',
            'customer_local_end_time_at_booking' => '11:00:00',
            'business_utc_offset_at_booking' => '+02:00',
            'customer_utc_offset_at_booking' => '+00:00',
            'idempotency_key' => 'idem-report-2',
            'confirmation_token' => 'conf-report-2',
            'visitor_token' => 'vis-camp-3',
            'touch_at' => CarbonImmutable::parse('2026-09-12 14:00:00'),
            'source' => 'youtube',
            'campaign' => 'survival-arabic',
            'content' => 'video-2',
            'status' => 'confirmed',
            'created_at' => CarbonImmutable::parse('2026-09-16 11:00:00'),
        ]);

        Booking::create([
            'contact_id' => $contact->id,
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => '2026-09-22 10:00:00',
            'end_at_utc' => '2026-09-22 11:00:00',
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'UTC',
            'business_local_date_at_booking' => '2026-09-22',
            'business_local_start_time_at_booking' => '12:00:00',
            'business_local_end_time_at_booking' => '13:00:00',
            'customer_local_date_at_booking' => '2026-09-22',
            'customer_local_start_time_at_booking' => '10:00:00',
            'customer_local_end_time_at_booking' => '11:00:00',
            'business_utc_offset_at_booking' => '+02:00',
            'customer_utc_offset_at_booking' => '+00:00',
            'idempotency_key' => 'idem-report-3',
            'confirmation_token' => 'conf-report-3',
            'visitor_token' => 'vis-camp-3',
            'touch_at' => CarbonImmutable::parse('2026-09-12 14:00:00'),
            'source' => 'youtube',
            'campaign' => 'survival-arabic',
            'content' => 'video-2',
            'status' => 'completed',
            'created_at' => CarbonImmutable::parse('2026-09-16 12:00:00'),
        ]);

        $report = $this->reportService->getCampaignContentReport($start, $end);

        $this->assertNotEmpty($report['rows']);
        $video1Row = $report['rows']->firstWhere('content', 'video-1');
        $video2Row = $report['rows']->firstWhere('content', 'video-2');

        $this->assertNotNull($video1Row, 'video-1 must be an explicit distinct content row');
        $this->assertNotNull($video2Row, 'video-2 must be an explicit distinct content row');

        $this->assertEquals(2, $video1Row['visitors_count']);
        $this->assertEquals(1, $video1Row['confirmed_bookings']);
        $this->assertEquals(50.0, $video1Row['conversion_rate']);

        $this->assertEquals(1, $video2Row['visitors_count']);
        $this->assertEquals(2, $video2Row['confirmed_bookings']);

        // Assert admin report route responds with campaigns report
        $admin = Administrator::create([
            'name' => 'Dr. Admin',
            'email' => 'admin-camp@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $res = $this->actingAs($admin, 'web')->get(route('admin.reports.index', ['type' => 'campaigns', 'range' => '30d']));
        $res->assertOk();
        $res->assertSee('survival-arabic');
        $res->assertSee('video-1');
        $res->assertSee('video-2');
    }

    public function test_stale_utm_greater_than_30_days_yields_direct_none_without_fabricated_touch(): void
    {
        $now = CarbonImmutable::now();
        $staleTime = $now->subDays(31);

        $visitor = Visitor::create([
            'visitor_token' => 'vis-stale-utm',
            'first_seen_at' => $staleTime,
            'last_seen_at' => $staleTime,
            'is_bot' => false,
        ]);

        // Marketing touch occurred 31 days ago
        MarketingTouch::create([
            'visitor_id' => $visitor->id,
            'visitor_token' => $visitor->visitor_token,
            'utm_source' => 'facebook',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'spring_sale',
            'utm_content' => 'ad_1',
            'touch_at' => $staleTime,
        ]);

        // Hold created today
        $slotStart = CarbonImmutable::now('UTC')->addDays(2)->setTime(12, 0, 0);
        $hold = $this->holdService->acquireHold(
            visitorToken: $visitor->visitor_token,
            sessionToken: 'sess-stale',
            sessionType: $this->sessionType,
            startUtc: $slotStart,
            endUtc: $slotStart->addMinutes(60),
            durationMinutes: 10
        );

        // Booking created today
        $booking = $this->bookingService->createPublicBooking([
            'customer_name' => 'Stale Test User',
            'customer_email' => 'stale@example.com',
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slotStart,
            'end_at_utc' => $slotStart->addMinutes(60),
            'hold_id' => $hold->id,
            'hold_token' => $hold->hold_token,
            'visitor_token' => $visitor->visitor_token,
            'session_token' => 'sess-stale',
            'customer_timezone' => 'UTC',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        // Conversion attribution must be Direct / None and touch_at must be null (NEVER fabricated now())
        $this->assertEquals('Direct / None', $booking->source);
        $this->assertNull($booking->campaign);
        $this->assertNull($booking->touch_at);
    }

    public function test_exact_30_day_lookback_boundary_endpoints_inclusive_and_exclusive(): void
    {
        $bookingTime = CarbonImmutable::parse('2026-06-15 12:00:00', 'UTC');

        $visExact = Visitor::create([
            'visitor_token' => 'vis-bound-exact',
            'first_seen_at' => $bookingTime->subDays(35),
            'last_seen_at' => $bookingTime,
            'is_bot' => false,
        ]);

        // Boundary test: exactly 30 days prior (inclusive lower bound)
        $touchAtExact30Days = $bookingTime->subDays(30);
        MarketingTouch::create([
            'visitor_id' => $visExact->id,
            'visitor_token' => $visExact->visitor_token,
            'utm_source' => 'google',
            'utm_campaign' => 'exact_30',
            'touch_at' => $touchAtExact30Days,
        ]);
        $attrExact = $this->analyticsService->getBookingConversionAttribution('vis-bound-exact', $bookingTime);
        $this->assertEquals('google', $attrExact['utm_source'], 'Touch at exactly 30 days prior must qualify');
        $this->assertEquals('exact_30', $attrExact['utm_campaign']);

        $visEarly = Visitor::create([
            'visitor_token' => 'vis-bound-early',
            'first_seen_at' => $bookingTime->subDays(35),
            'last_seen_at' => $bookingTime,
            'is_bot' => false,
        ]);

        // Boundary test: 30 days + 1 second prior (exclusive, must not qualify)
        $touch1SecTooEarly = $bookingTime->subDays(30)->subSecond();
        MarketingTouch::create([
            'visitor_id' => $visEarly->id,
            'visitor_token' => $visEarly->visitor_token,
            'utm_source' => 'google',
            'utm_campaign' => 'too_early',
            'touch_at' => $touch1SecTooEarly,
        ]);
        $attrEarly = $this->analyticsService->getBookingConversionAttribution('vis-bound-early', $bookingTime);
        $this->assertEquals('Direct / None', $attrEarly['utm_source'], 'Touch 1 second before 30 days must not qualify');

        $visNow = Visitor::create([
            'visitor_token' => 'vis-bound-now',
            'first_seen_at' => $bookingTime->subDays(5),
            'last_seen_at' => $bookingTime,
            'is_bot' => false,
        ]);

        // Upper boundary test: exactly at booking time (inclusive upper bound)
        MarketingTouch::create([
            'visitor_id' => $visNow->id,
            'visitor_token' => $visNow->visitor_token,
            'utm_source' => 'twitter',
            'utm_campaign' => 'at_booking_time',
            'touch_at' => $bookingTime,
        ]);
        $attrNow = $this->analyticsService->getBookingConversionAttribution('vis-bound-now', $bookingTime);
        $this->assertEquals('twitter', $attrNow['utm_source'], 'Touch at exact booking time must qualify');
    }

    public function test_atomic_first_acquisition_prevents_concurrent_first_touch_overwrite(): void
    {
        $vToken = 'vis-atomic-acquisition';
        $now = CarbonImmutable::now();

        $visitor = Visitor::create([
            'visitor_token' => $vToken,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'is_bot' => false,
        ]);

        // Simulating two concurrent first arrivals: first non-direct arrival wins
        // First arrival arrives with google / summer_camp
        $req1 = Request::create('/?utm_source=google&utm_medium=cpc&utm_campaign=summer_camp', 'GET');
        $this->analyticsService->track(
            eventName: 'page_view',
            page: '/',
            request: $req1,
            visitorToken: $vToken,
            sessionToken: 'sess-first'
        );

        $visitor->refresh();
        $this->assertEquals('google', $visitor->acquisition_source);
        $this->assertEquals('summer_camp', $visitor->acquisition_campaign);

        // Concurrent arrival attempts to overwrite with facebook / retargeting
        $req2 = Request::create('/?utm_source=facebook&utm_medium=social&utm_campaign=retargeting', 'GET');
        $this->analyticsService->track(
            eventName: 'page_view',
            page: '/',
            request: $req2,
            visitorToken: $vToken,
            sessionToken: 'sess-second'
        );

        $visitor->refresh();
        // First acquisition must REMAIN google / summer_camp!
        $this->assertEquals('google', $visitor->acquisition_source);
        $this->assertEquals('summer_camp', $visitor->acquisition_campaign);
    }

    public function test_campaign_report_distinguishes_multiple_sources_sharing_same_campaign_and_content(): void
    {
        $start = CarbonImmutable::parse('2026-07-01 00:00:00');
        $end = CarbonImmutable::parse('2026-07-31 23:59:59');

        // Source 1: Google running campaign "arabic_mastery" with content "grammar_reel"
        AnalyticsEvent::create([
            'visitor_token' => 'vis-goog-1',
            'utm_source' => 'google',
            'utm_campaign' => 'arabic_mastery',
            'utm_content' => 'grammar_reel',
            'event_name' => 'page_view',
            'created_at' => $start->addDays(2),
            'is_bot' => false,
        ]);
        AnalyticsEvent::create([
            'visitor_token' => 'vis-goog-2',
            'utm_source' => 'google',
            'utm_campaign' => 'arabic_mastery',
            'utm_content' => 'grammar_reel',
            'event_name' => 'page_view',
            'created_at' => $start->addDays(2),
            'is_bot' => false,
        ]);

        // Source 2: Facebook running the SAME campaign "arabic_mastery" with content "grammar_reel"
        AnalyticsEvent::create([
            'visitor_token' => 'vis-fb-1',
            'utm_source' => 'facebook',
            'utm_campaign' => 'arabic_mastery',
            'utm_content' => 'grammar_reel',
            'event_name' => 'page_view',
            'created_at' => $start->addDays(3),
            'is_bot' => false,
        ]);

        // Booking from Google
        $contact1 = Contact::create(['email' => 'g@test.com', 'name' => 'Google Student']);
        Booking::forceCreate([
            'contact_id' => $contact1->id,
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $start->addDays(5),
            'end_at_utc' => $start->addDays(5)->addHour(),
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'UTC',
            'business_local_date_at_booking' => '2026-07-06',
            'business_local_start_time_at_booking' => '12:00:00',
            'business_local_end_time_at_booking' => '13:00:00',
            'customer_local_date_at_booking' => '2026-07-06',
            'customer_local_start_time_at_booking' => '10:00:00',
            'customer_local_end_time_at_booking' => '11:00:00',
            'business_utc_offset_at_booking' => '+03:00',
            'customer_utc_offset_at_booking' => '+00:00',
            'idempotency_key' => 'idem-shared-1',
            'confirmation_token' => 'conf-shared-1',
            'visitor_token' => 'vis-goog-1',
            'touch_at' => $start->addDays(2),
            'source' => 'google',
            'campaign' => 'arabic_mastery',
            'content' => 'grammar_reel',
            'status' => 'confirmed',
            'created_at' => $start->addDays(5),
        ]);

        $report = $this->reportService->getCampaignContentReport($start, $end);

        // Assert both Google and Facebook are distinct rows! Neither overwrites the other.
        $googleRow = $report['rows']->first(fn ($r) => $r['source'] === 'google' && $r['content'] === 'grammar_reel');
        $fbRow = $report['rows']->first(fn ($r) => $r['source'] === 'facebook' && $r['content'] === 'grammar_reel');

        $this->assertNotNull($googleRow, 'Google row must exist distinctly');
        $this->assertNotNull($fbRow, 'Facebook row must exist distinctly');

        $this->assertEquals(2, $googleRow['visitors_count']);
        $this->assertEquals(1, $googleRow['confirmed_bookings']);

        $this->assertEquals(1, $fbRow['visitors_count']);
        $this->assertEquals(0, $fbRow['confirmed_bookings']);
    }

    public function test_touch_in_month_one_followed_by_booking_in_month_two_report_relationship(): void
    {
        $month1Start = CarbonImmutable::parse('2026-05-01 00:00:00');
        $month1End = CarbonImmutable::parse('2026-05-31 23:59:59');

        $month2Start = CarbonImmutable::parse('2026-06-01 00:00:00');
        $month2End = CarbonImmutable::parse('2026-06-30 23:59:59');

        // Visitor 1: Marketing arrival event in Month 1 (May 20)
        AnalyticsEvent::create([
            'visitor_token' => 'vis-cross-month-1',
            'utm_source' => 'linkedin',
            'utm_campaign' => 'exec_arabic',
            'utm_content' => 'whitepaper',
            'event_name' => 'page_view',
            'created_at' => CarbonImmutable::parse('2026-05-20 14:00:00'),
            'is_bot' => false,
        ]);

        // Visitor 1: Downstream attributed booking in Month 2 (June 5, within 30 days of May 20 touch)
        $contact1 = Contact::create(['email' => 'exec1@test.com', 'name' => 'Exec Student 1']);
        Booking::forceCreate([
            'contact_id' => $contact1->id,
            'visitor_token' => 'vis-cross-month-1',
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => CarbonImmutable::parse('2026-06-10 10:00:00'),
            'end_at_utc' => CarbonImmutable::parse('2026-06-10 11:00:00'),
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'UTC',
            'business_local_date_at_booking' => '2026-06-10',
            'business_local_start_time_at_booking' => '13:00:00',
            'business_local_end_time_at_booking' => '14:00:00',
            'customer_local_date_at_booking' => '2026-06-10',
            'customer_local_start_time_at_booking' => '10:00:00',
            'customer_local_end_time_at_booking' => '11:00:00',
            'business_utc_offset_at_booking' => '+03:00',
            'customer_utc_offset_at_booking' => '+00:00',
            'idempotency_key' => 'idem-cross-month-1',
            'confirmation_token' => 'conf-cross-month-1',
            'source' => 'linkedin',
            'campaign' => 'exec_arabic',
            'content' => 'whitepaper',
            'touch_at' => CarbonImmutable::parse('2026-05-20 14:00:00'),
            'status' => 'confirmed',
            'created_at' => CarbonImmutable::parse('2026-06-05 09:00:00'),
        ]);

        // Month 1 Report: Visitor 1 arrived in May and converted downstream on June 5
        // Downstream bookings must be credited to May's campaign cohort!
        $reportM1 = $this->reportService->getCampaignContentReport($month1Start, $month1End);
        $m1Row = $reportM1['rows']->firstWhere('campaign', 'exec_arabic');
        $this->assertNotNull($m1Row);
        $this->assertEquals(1, $m1Row['visitors_count'], 'Month 1 cohort has 1 unique visitor');
        $this->assertEquals(1, $m1Row['bookings_count'], 'Month 1 cohort has 1 downstream booking');
        $this->assertEquals(1, $m1Row['confirmed_bookings'], 'Month 1 cohort has 1 confirmed downstream booking');
        $this->assertEquals(100.0, $m1Row['conversion_rate'], 'Month 1 conversion rate is 100%');
        $this->assertEquals(0, $m1Row['bookings_created_in_period'], 'No bookings were created during Month 1');

        // Prior to Visitor 2 arrival: Month 2 has 0 visitors from this campaign
        // Downstream bookings for Month 2 arrivals must be 0 and conversion rate must be 0.0% (NEVER 100%)
        $reportM2Initial = $this->reportService->getCampaignContentReport($month2Start, $month2End);
        $m2RowInitial = $reportM2Initial['rows']->firstWhere('campaign', 'exec_arabic');
        $this->assertNotNull($m2RowInitial);
        $this->assertEquals(0, $m2RowInitial['visitors_count'], 'Month 2 has 0 campaign arrivals so far');
        $this->assertEquals(0, $m2RowInitial['bookings_count'], 'Month 2 has 0 downstream bookings for June arrivals');
        $this->assertEquals(0, $m2RowInitial['confirmed_bookings']);
        $this->assertEquals(0.0, $m2RowInitial['conversion_rate'], 'Conversion rate must be 0.0% when visitors are 0');
        $this->assertEquals(1, $m2RowInitial['bookings_created_in_period'], '1 booking was created in June from a prior May touch');

        // Visitor 2: Distinct visitor arriving in Month 2 (June 2) with the same campaign/content
        AnalyticsEvent::create([
            'visitor_token' => 'vis-cross-month-2',
            'utm_source' => 'linkedin',
            'utm_campaign' => 'exec_arabic',
            'utm_content' => 'whitepaper',
            'event_name' => 'page_view',
            'created_at' => CarbonImmutable::parse('2026-06-02 10:00:00'),
            'is_bot' => false,
        ]);

        // Visitor 2: Books in Month 2 (June 3)
        $contact2 = Contact::create(['email' => 'exec2@test.com', 'name' => 'Exec Student 2']);
        Booking::forceCreate([
            'contact_id' => $contact2->id,
            'visitor_token' => 'vis-cross-month-2',
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => CarbonImmutable::parse('2026-06-15 10:00:00'),
            'end_at_utc' => CarbonImmutable::parse('2026-06-15 11:00:00'),
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'UTC',
            'business_local_date_at_booking' => '2026-06-15',
            'business_local_start_time_at_booking' => '13:00:00',
            'business_local_end_time_at_booking' => '14:00:00',
            'customer_local_date_at_booking' => '2026-06-15',
            'customer_local_start_time_at_booking' => '10:00:00',
            'customer_local_end_time_at_booking' => '11:00:00',
            'business_utc_offset_at_booking' => '+03:00',
            'customer_utc_offset_at_booking' => '+00:00',
            'idempotency_key' => 'idem-cross-month-2',
            'confirmation_token' => 'conf-cross-month-2',
            'source' => 'linkedin',
            'campaign' => 'exec_arabic',
            'content' => 'whitepaper',
            'touch_at' => CarbonImmutable::parse('2026-06-02 10:00:00'),
            'status' => 'confirmed',
            'created_at' => CarbonImmutable::parse('2026-06-03 15:00:00'),
        ]);

        // Month 2 Report with both visitors:
        // Month 2 visitor count = 1 (Visitor 2 only)
        // Downstream bookings for Month 2 arrivals = 1 (Visitor 2 only, Visitor 1 is not credited to June!)
        // Conversion rate for Month 2 = 100.0% (1/1), NOT 200.0%
        // Total bookings created in June = 2 (Visitor 1 created June 5 + Visitor 2 created June 3)
        $reportM2WithBoth = $this->reportService->getCampaignContentReport($month2Start, $month2End);
        $m2RowWithBoth = $reportM2WithBoth['rows']->firstWhere('campaign', 'exec_arabic');
        $this->assertNotNull($m2RowWithBoth);
        $this->assertEquals('linkedin', $m2RowWithBoth['source']);
        $this->assertEquals(1, $m2RowWithBoth['visitors_count'], 'June cohort visitor count is 1');
        $this->assertEquals(1, $m2RowWithBoth['bookings_count'], 'June downstream bookings count is 1 (Visitor 2 only)');
        $this->assertEquals(1, $m2RowWithBoth['confirmed_bookings'], 'June confirmed downstream bookings count is 1');
        $this->assertEquals(100.0, $m2RowWithBoth['conversion_rate'], 'June conversion rate is 100.0% (1/1)');
        $this->assertEquals(2, $m2RowWithBoth['bookings_created_in_period'], '2 total bookings created in June');
        $this->assertEquals(2, $m2RowWithBoth['confirmed_created_in_period'], '2 confirmed bookings created in June');
    }

    public function test_booking_with_touch_at_in_range_but_no_qualifying_cohort_visitor_records_has_zero_downstream_cohort_bookings(): void
    {
        $start = CarbonImmutable::parse('2026-08-01 00:00:00');
        $end = CarbonImmutable::parse('2026-08-31 23:59:59');

        // ZERO marketing events or touches recorded for campaign "orphan_campaign"
        $contact = Contact::create(['email' => 'orphan@example.com', 'name' => 'Orphan Student']);

        // A booking exists with campaign="orphan_campaign" and touch_at in range,
        // but its visitor_token is NOT in any cohort visitor record for that campaign.
        Booking::forceCreate([
            'contact_id' => $contact->id,
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => CarbonImmutable::parse('2026-08-20 10:00:00'),
            'end_at_utc' => CarbonImmutable::parse('2026-08-20 11:00:00'),
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'UTC',
            'business_local_date_at_booking' => '2026-08-20',
            'business_local_start_time_at_booking' => '13:00:00',
            'business_local_end_time_at_booking' => '14:00:00',
            'customer_local_date_at_booking' => '2026-08-20',
            'customer_local_start_time_at_booking' => '10:00:00',
            'customer_local_end_time_at_booking' => '11:00:00',
            'business_utc_offset_at_booking' => '+03:00',
            'customer_utc_offset_at_booking' => '+00:00',
            'idempotency_key' => 'idem-orphan-1',
            'confirmation_token' => 'conf-orphan-1',
            'visitor_token' => 'vis-unrecorded-token',
            'source' => 'twitter',
            'campaign' => 'orphan_campaign',
            'content' => 'ad_orphan',
            'touch_at' => CarbonImmutable::parse('2026-08-10 12:00:00'),
            'status' => 'confirmed',
            'created_at' => CarbonImmutable::parse('2026-08-11 10:00:00'),
        ]);

        $report = $this->reportService->getCampaignContentReport($start, $end);
        $row = $report['rows']->firstWhere('campaign', 'orphan_campaign');

        $this->assertNotNull($row, 'Row for orphan campaign should exist in report from period bookings');
        // Crucial assertions for V1-R08:
        $this->assertEquals(0, $row['visitors_count'], 'Cohort visitors must be strictly 0');
        $this->assertEquals(0, $row['bookings_count'], 'Downstream cohort bookings must be strictly 0 when no cohort visitors exist');
        $this->assertEquals(0, $row['confirmed_bookings'], 'Confirmed downstream cohort bookings must be 0');
        $this->assertEquals(0.0, $row['conversion_rate'], 'Conversion rate must be 0.0%');
        // Legacy/unlinked activity is recorded in dedicated non-comparable counts
        $this->assertEquals(1, $row['unlinked_bookings_count'], 'Unlinked bookings count records non-cohort booking');
        $this->assertEquals(1, $row['bookings_created_in_period'], 'Bookings created in period records the created booking');
    }

    public function test_legacy_booking_without_token_or_touch_has_zero_downstream_cohort_bookings(): void
    {
        $start = CarbonImmutable::parse('2026-07-01 00:00:00');
        $end = CarbonImmutable::parse('2026-07-31 23:59:59');

        $contact = Contact::create(['email' => 'legacy@example.com', 'name' => 'Legacy Student']);

        // Legacy booking created with neither visitor_token nor touch_at
        Booking::forceCreate([
            'contact_id' => $contact->id,
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => CarbonImmutable::parse('2026-07-20 10:00:00'),
            'end_at_utc' => CarbonImmutable::parse('2026-07-20 11:00:00'),
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'UTC',
            'business_local_date_at_booking' => '2026-07-20',
            'business_local_start_time_at_booking' => '13:00:00',
            'business_local_end_time_at_booking' => '14:00:00',
            'customer_local_date_at_booking' => '2026-07-20',
            'customer_local_start_time_at_booking' => '10:00:00',
            'customer_local_end_time_at_booking' => '11:00:00',
            'business_utc_offset_at_booking' => '+03:00',
            'customer_utc_offset_at_booking' => '+00:00',
            'idempotency_key' => 'idem-legacy-1',
            'confirmation_token' => 'conf-legacy-1',
            'visitor_token' => null,
            'touch_at' => null,
            'source' => 'direct',
            'campaign' => 'legacy_campaign',
            'content' => '(not set)',
            'status' => 'confirmed',
            'created_at' => CarbonImmutable::parse('2026-07-15 14:00:00'),
        ]);

        $report = $this->reportService->getCampaignContentReport($start, $end);
        $row = $report['rows']->firstWhere('campaign', 'legacy_campaign');

        $this->assertNotNull($row);
        // Crucial assertions for V1-R08:
        $this->assertEquals(0, $row['visitors_count'], 'Cohort visitors must be strictly 0');
        $this->assertEquals(0, $row['bookings_count'], 'Legacy booking without visitor token must NEVER be counted as downstream cohort booking');
        $this->assertEquals(0, $row['confirmed_bookings'], 'Confirmed downstream cohort bookings must be 0');
        $this->assertEquals(0.0, $row['conversion_rate'], 'Conversion rate must be 0.0%');
        $this->assertEquals(1, $row['unlinked_bookings_count'], 'Legacy unlinked booking tracked in dedicated count');
        $this->assertEquals(1, $row['bookings_created_in_period'], 'Legacy booking tracked in bookings created in period');
    }

    public function test_repeated_touches_in_period_credit_downstream_booking_within_30_days_of_latest_touch(): void
    {
        $start = CarbonImmutable::parse('2026-05-01 00:00:00');
        $end = CarbonImmutable::parse('2026-05-31 23:59:59');

        $vis = Visitor::create([
            'visitor_token' => 'vis-repeated-touch',
            'first_seen_at' => CarbonImmutable::parse('2026-05-01 10:00:00'),
            'last_seen_at' => CarbonImmutable::parse('2026-05-25 15:00:00'),
            'is_bot' => false,
        ]);

        // Touch 1 on Day 1 (2026-05-01)
        AnalyticsEvent::create([
            'visitor_id' => $vis->id,
            'visitor_token' => 'vis-repeated-touch',
            'utm_source' => 'facebook',
            'utm_campaign' => 'summer_boost',
            'utm_content' => 'ad_video',
            'event_name' => 'page_view',
            'created_at' => CarbonImmutable::parse('2026-05-01 10:00:00'),
            'is_bot' => false,
        ]);

        // Touch 2 on Day 25 (2026-05-25)
        MarketingTouch::create([
            'visitor_id' => $vis->id,
            'visitor_token' => 'vis-repeated-touch',
            'utm_source' => 'facebook',
            'utm_campaign' => 'summer_boost',
            'utm_content' => 'ad_video',
            'touch_at' => CarbonImmutable::parse('2026-05-25 15:00:00'),
            'landing_page' => '/',
        ]);

        $contact = Contact::create(['email' => 'repeated@example.com', 'name' => 'Repeated Touch Student']);

        // Booking on Day 40 (2026-06-09) -> 39 days after Day 1 (>30d), but 15 days after Day 25 (<=30d)
        Booking::forceCreate([
            'contact_id' => $contact->id,
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => CarbonImmutable::parse('2026-06-15 10:00:00'),
            'end_at_utc' => CarbonImmutable::parse('2026-06-15 11:00:00'),
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'UTC',
            'business_local_date_at_booking' => '2026-06-15',
            'business_local_start_time_at_booking' => '13:00:00',
            'business_local_end_time_at_booking' => '14:00:00',
            'customer_local_date_at_booking' => '2026-06-15',
            'customer_local_start_time_at_booking' => '10:00:00',
            'customer_local_end_time_at_booking' => '11:00:00',
            'business_utc_offset_at_booking' => '+03:00',
            'customer_utc_offset_at_booking' => '+00:00',
            'idempotency_key' => 'idem-repeated-1',
            'confirmation_token' => 'conf-repeated-1',
            'visitor_token' => 'vis-repeated-touch',
            'source' => 'facebook',
            'campaign' => 'summer_boost',
            'content' => 'ad_video',
            'touch_at' => CarbonImmutable::parse('2026-05-25 15:00:00'),
            'status' => 'confirmed',
            'created_at' => CarbonImmutable::parse('2026-06-09 12:00:00'),
        ]);

        $report = $this->reportService->getCampaignContentReport($start, $end);
        $row = $report['rows']->firstWhere('campaign', 'summer_boost');

        $this->assertNotNull($row);
        $this->assertEquals(1, $row['visitors_count'], '1 unique visitor for cohort');
        // Crucial test for V1-R08: repeated eligible touch within lookback must credit downstream booking
        $this->assertEquals(1, $row['bookings_count'], 'Downstream booking must be credited via the eligible Day 25 touch');
        $this->assertEquals(1, $row['confirmed_bookings']);
        $this->assertEquals(100.0, $row['conversion_rate']);
    }

    public function test_booking_more_than_30_days_after_last_touch_does_not_count_as_downstream(): void
    {
        $start = CarbonImmutable::parse('2026-05-01 00:00:00');
        $end = CarbonImmutable::parse('2026-05-31 23:59:59');

        $vis = Visitor::create([
            'visitor_token' => 'vis-single-touch',
            'first_seen_at' => CarbonImmutable::parse('2026-05-01 10:00:00'),
            'last_seen_at' => CarbonImmutable::parse('2026-05-01 10:00:00'),
            'is_bot' => false,
        ]);

        // Single touch on Day 1 (2026-05-01) with NO newer touch
        AnalyticsEvent::create([
            'visitor_id' => $vis->id,
            'visitor_token' => 'vis-single-touch',
            'utm_source' => 'google',
            'utm_campaign' => 'expired_campaign',
            'utm_content' => 'ad_old',
            'event_name' => 'page_view',
            'created_at' => CarbonImmutable::parse('2026-05-01 10:00:00'),
            'is_bot' => false,
        ]);

        $contact = Contact::create(['email' => 'expired@example.com', 'name' => 'Expired Touch Student']);

        // Booking on Day 40 (2026-06-09) -> 39 days after Day 1 (>30d window)
        Booking::forceCreate([
            'contact_id' => $contact->id,
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => CarbonImmutable::parse('2026-06-15 10:00:00'),
            'end_at_utc' => CarbonImmutable::parse('2026-06-15 11:00:00'),
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'UTC',
            'business_local_date_at_booking' => '2026-06-15',
            'business_local_start_time_at_booking' => '13:00:00',
            'business_local_end_time_at_booking' => '14:00:00',
            'customer_local_date_at_booking' => '2026-06-15',
            'customer_local_start_time_at_booking' => '10:00:00',
            'customer_local_end_time_at_booking' => '11:00:00',
            'business_utc_offset_at_booking' => '+03:00',
            'customer_utc_offset_at_booking' => '+00:00',
            'idempotency_key' => 'idem-expired-1',
            'confirmation_token' => 'conf-expired-1',
            'visitor_token' => 'vis-single-touch',
            'source' => 'google',
            'campaign' => 'expired_campaign',
            'content' => 'ad_old',
            'touch_at' => CarbonImmutable::parse('2026-05-01 10:00:00'),
            'status' => 'confirmed',
            'created_at' => CarbonImmutable::parse('2026-06-09 12:00:00'),
        ]);

        $report = $this->reportService->getCampaignContentReport($start, $end);
        $row = $report['rows']->firstWhere('campaign', 'expired_campaign');

        $this->assertNotNull($row);
        $this->assertEquals(1, $row['visitors_count']);
        // Booking after 30 days without newer eligible touch must NOT count as downstream
        $this->assertEquals(0, $row['bookings_count']);
        $this->assertEquals(0, $row['confirmed_bookings']);
        $this->assertEquals(0.0, $row['conversion_rate']);
    }
}
