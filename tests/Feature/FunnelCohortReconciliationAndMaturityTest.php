<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Models\Visitor;
use App\Domains\Analytics\Models\VisitorFunnelProgression;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Analytics\Services\FunnelProgressionService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Contacts\Models\Contact;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class FunnelCohortReconciliationAndMaturityTest extends TestCase
{
    use RefreshDatabase;

    protected FunnelProgressionService $funnelService;

    protected AnalyticsService $analyticsService;

    protected Administrator $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->funnelService = app(FunnelProgressionService::class);
        $this->analyticsService = app(AnalyticsService::class);

        $this->admin = Administrator::create([
            'name' => 'Admin Test',
            'email' => 'admintest@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
            'active' => true,
        ]);
    }

    public function test_half_open_window_boundary_rules(): void
    {
        $firstVisit = CarbonImmutable::parse('2026-05-01 10:00:00', 'UTC');
        CarbonImmutable::setTestNow($firstVisit->addDays(5));

        $visitor = Visitor::create([
            'visitor_token' => 'visitor-boundary-test',
            'first_seen_at' => $firstVisit,
            'last_seen_at' => $firstVisit,
            'is_bot' => false,
        ]);

        $this->funnelService->recordVisit($visitor, $firstVisit);

        // 1. Pre-first-visit event (clock skew / invalid timestamp) -> MUST NOT qualify
        $preVisitTime = $firstVisit->subSeconds(5);
        $this->funnelService->recordStage($visitor, 'booking_cta', $preVisitTime, true);

        $prog = VisitorFunnelProgression::where('visitor_id', $visitor->id)->first();
        $this->assertEquals('none', $prog->booking_cta_provenance, 'Pre-first-visit events must not qualify for the cohort');

        // 2. Exact lower boundary: event at t_first_seen -> QUALIFIES
        $this->funnelService->recordStage($visitor, 'booking_cta', $firstVisit, true);
        $prog->refresh();
        $this->assertEquals('observed', $prog->booking_cta_provenance);

        // 3. Just inside 30-day window: t_first_seen + 30 days - 1 second -> QUALIFIES
        $insideWindowTime = $firstVisit->addSeconds(FunnelProgressionService::MATURITY_SECONDS - 1);
        $this->funnelService->recordStage($visitor, 'booking_started', $insideWindowTime, true);
        $prog->refresh();
        $this->assertEquals('observed', $prog->booking_started_provenance);

        // 4. Exact upper boundary: t_first_seen + 30 days -> MUST NOT qualify (upper boundary is exclusive)
        $exactCutoff = $firstVisit->addSeconds(FunnelProgressionService::MATURITY_SECONDS);
        $this->funnelService->recordStage($visitor, 'slot_held', $exactCutoff, true);
        $prog->refresh();
        $this->assertEquals('none', $prog->slot_held_provenance, 'Event at exact 30-day boundary must be excluded');

        // 5. Way after cutoff -> MUST NOT qualify
        $afterCutoff = $firstVisit->addDays(35);
        $this->funnelService->recordStage($visitor, 'booking_completed', $afterCutoff, true);
        $prog->refresh();
        $this->assertEquals('none', $prog->booking_completed_provenance);

        CarbonImmutable::setTestNow();
    }

    public function test_routine_ingestion_cannot_mutate_already_matured_cohort(): void
    {
        // Cohort from 60 days ago (now() >= cutoff)
        $oldVisit = CarbonImmutable::now()->subDays(60);
        $visitor = Visitor::create([
            'visitor_token' => 'visitor-matured-cohort',
            'first_seen_at' => $oldVisit,
            'last_seen_at' => $oldVisit,
            'is_bot' => false,
        ]);

        $this->funnelService->recordVisit($visitor, $oldVisit);

        // A routine event arrives with a backdated timestamp in the original window
        $backdatedTime = $oldVisit->addDays(10);
        $this->funnelService->recordStage($visitor, 'booking_cta', $backdatedTime, true);

        $prog = VisitorFunnelProgression::where('visitor_id', $visitor->id)->first();
        $this->assertEquals('none', $prog->booking_cta_provenance, 'Routine ingestion must not alter an already matured cohort');
    }

    public function test_audited_explicit_reconciliation_updates_reconciled_at(): void
    {
        $oldVisit = CarbonImmutable::now()->subDays(60);
        $visitor = Visitor::create([
            'visitor_token' => 'visitor-reconcile-test',
            'first_seen_at' => $oldVisit,
            'last_seen_at' => $oldVisit,
            'is_bot' => false,
        ]);

        $this->funnelService->recordVisit($visitor, $oldVisit);

        // Upstream stage was previously imputed or none
        $inWindowTime = $oldVisit->addDays(5);
        $reconciled = $this->funnelService->reconcileLateEvent($visitor, 'booking_cta', $inWindowTime);

        $this->assertTrue($reconciled);
        $prog = VisitorFunnelProgression::where('visitor_id', $visitor->id)->first();
        $this->assertEquals('observed', $prog->booking_cta_provenance);
        $this->assertNotNull($prog->reconciled_at);

        // Attempting to reconcile an event outside the 30-day window must fail
        $outOfWindowTime = $oldVisit->addDays(32);
        $reconciledLate = $this->funnelService->reconcileLateEvent($visitor, 'booking_completed', $outOfWindowTime);
        $this->assertFalse($reconciledLate);
    }

    public function test_rebuild_funnel_command_is_deterministic_and_incorporates_server_bookings(): void
    {
        $sessionType = SessionType::create([
            'title' => 'Arabic Test Lesson',
            'slug' => 'test-lesson',
            'duration_minutes' => 60,
            'price' => 30,
            'currency' => 'USD',
            'active' => true,
        ]);

        $firstVisit = CarbonImmutable::now()->subDays(10);
        $visitor = Visitor::create([
            'visitor_token' => 'visitor-rebuild-client',
            'first_seen_at' => $firstVisit,
            'last_seen_at' => $firstVisit,
            'is_bot' => false,
        ]);

        // Raw event: CTA clicked 1 day after first visit
        AnalyticsEvent::create([
            'event_name' => 'booking_cta_clicked',
            'visitor_token' => $visitor->visitor_token,
            'is_bot' => false,
            'created_at' => $firstVisit->addDay(),
        ]);

        // Blocked client tracking: no client event for booking_completed, but authoritative converted BookingHold exists!
        BookingHold::create([
            'visitor_token' => $visitor->visitor_token,
            'session_token' => 'sess-123',
            'hold_token' => 'hold-tok-123',
            'session_type_id' => $sessionType->id,
            'slot_start_utc' => $firstVisit->addDays(5),
            'slot_end_utc' => $firstVisit->addDays(5)->addMinutes(60),
            'expires_at' => $firstVisit->addDays(5)->addMinutes(10),
            'status' => 'converted',
            'created_at' => $firstVisit->addDays(2),
        ]);

        $contact = Contact::create([
            'email' => 'student@test.com',
            'name' => 'Test Student',
        ]);

        $bookingTime = $firstVisit->addDays(2);
        Booking::create([
            'contact_id' => $contact->id,
            'session_type_id' => $sessionType->id,
            'start_at_utc' => $bookingTime->addDays(3),
            'end_at_utc' => $bookingTime->addDays(3)->addMinutes(60),
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'Europe/London',
            'business_local_date_at_booking' => '2026-06-01',
            'business_local_start_time_at_booking' => '14:00',
            'business_local_end_time_at_booking' => '15:00',
            'customer_local_date_at_booking' => '2026-06-01',
            'customer_local_start_time_at_booking' => '12:00',
            'customer_local_end_time_at_booking' => '13:00',
            'business_utc_offset_at_booking' => '+02:00',
            'customer_utc_offset_at_booking' => '+01:00',
            'status' => 'confirmed',
            'idempotency_key' => (string) Str::uuid(),
            'confirmation_token' => Str::random(64),
            'visitor_token' => $visitor->visitor_token,
            'created_at' => $bookingTime,
        ]);

        // Run rebuild command
        Artisan::call('analytics:aggregate-daily', ['--rebuild-funnel' => true, '--date' => $firstVisit->toDateString()]);

        $prog = VisitorFunnelProgression::where('visitor_id', $visitor->id)->first();
        $this->assertNotNull($prog);
        $this->assertEquals('observed', $prog->booking_cta_provenance);
        // Completed booking is imputed from authoritative database record
        $this->assertEquals('imputed', $prog->booking_completed_provenance);
        // Intervening stages are deterministically imputed
        $this->assertEquals('imputed', $prog->slot_held_provenance);
        $this->assertEquals('imputed', $prog->booking_started_provenance);
        $this->assertNotNull($prog->reconciled_at);

        // Run rebuild command AGAIN -> must be completely idempotent and identical
        Artisan::call('analytics:aggregate-daily', ['--rebuild-funnel' => true, '--date' => $firstVisit->toDateString()]);
        $progSecond = VisitorFunnelProgression::where('visitor_id', $visitor->id)->first();
        $this->assertEquals('observed', $progSecond->booking_cta_provenance);
        $this->assertEquals('imputed', $progSecond->booking_completed_provenance);
        $this->assertEquals('imputed', $progSecond->slot_held_provenance);
        $this->assertEquals('imputed', $progSecond->booking_started_provenance);
    }

    public function test_rebuild_requires_actual_booking_record_and_removing_booking_changes_completion(): void
    {
        $sessionType = SessionType::create([
            'title' => 'Test Session',
            'slug' => 'test-session-auth',
            'duration_minutes' => 60,
            'price' => 30.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        $firstVisit = CarbonImmutable::parse('2026-05-10 10:00:00', 'UTC');
        $visitor = Visitor::create([
            'visitor_token' => 'visitor-booking-presence-test',
            'first_seen_at' => $firstVisit,
            'last_seen_at' => $firstVisit,
            'is_bot' => false,
        ]);

        // Hold exists with converted status, but NO booking record exists yet
        BookingHold::forceCreate([
            'visitor_token' => $visitor->visitor_token,
            'session_token' => 'sess-booking-presence',
            'hold_token' => 'hold-tok-presence',
            'session_type_id' => $sessionType->id,
            'slot_start_utc' => $firstVisit->addDays(5),
            'slot_end_utc' => $firstVisit->addDays(5)->addMinutes(60),
            'expires_at' => $firstVisit->addDays(5)->addMinutes(10),
            'status' => 'converted',
            'created_at' => $firstVisit->addDays(2),
        ]);

        // Rebuild WITHOUT a booking record -> slot_held qualifies, but booking_completed MUST be none!
        $this->funnelService->rebuildVisitorFunnel($visitor);
        $progWithoutBooking = VisitorFunnelProgression::where('visitor_id', $visitor->id)->first();
        $this->assertEquals('none', $progWithoutBooking->booking_completed_provenance, 'Hold alone must not manufacture completion without actual booking record');

        // Now create the actual booking
        $contact = Contact::create([
            'email' => 'presence@test.com',
            'name' => 'Presence Test',
        ]);

        $booking = Booking::forceCreate([
            'contact_id' => $contact->id,
            'visitor_token' => $visitor->visitor_token,
            'session_type_id' => $sessionType->id,
            'start_at_utc' => $firstVisit->addDays(5),
            'end_at_utc' => $firstVisit->addDays(5)->addMinutes(60),
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'Europe/London',
            'business_local_date_at_booking' => '2026-05-15',
            'business_local_start_time_at_booking' => '13:00',
            'business_local_end_time_at_booking' => '14:00',
            'customer_local_date_at_booking' => '2026-05-15',
            'customer_local_start_time_at_booking' => '11:00',
            'customer_local_end_time_at_booking' => '12:00',
            'business_utc_offset_at_booking' => '+02:00',
            'customer_utc_offset_at_booking' => '+01:00',
            'status' => 'confirmed',
            'idempotency_key' => (string) Str::uuid(),
            'confirmation_token' => Str::random(64),
            'created_at' => $firstVisit->addDays(2),
        ]);

        // Rebuild WITH booking record -> booking_completed is imputed!
        $this->funnelService->rebuildVisitorFunnel($visitor);
        $progWithBooking = VisitorFunnelProgression::where('visitor_id', $visitor->id)->first();
        $this->assertEquals('imputed', $progWithBooking->booking_completed_provenance);

        // Delete the fixture booking -> completion status MUST change back to none!
        $booking->forceDelete();
        $this->funnelService->rebuildVisitorFunnel($visitor);
        $progAfterDelete = VisitorFunnelProgression::where('visitor_id', $visitor->id)->first();
        $this->assertEquals('none', $progAfterDelete->booking_completed_provenance, 'Removing booking must change completion status');
    }

    public function test_hold_before_cutoff_and_booking_after_cutoff_is_excluded_from_funnel(): void
    {
        $sessionType = SessionType::create([
            'title' => 'Test Session Cutoff',
            'slug' => 'test-session-cutoff',
            'duration_minutes' => 60,
            'price' => 30.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        $firstVisit = CarbonImmutable::parse('2026-05-01 10:00:00', 'UTC');
        $visitor = Visitor::create([
            'visitor_token' => 'visitor-hold-inside-book-outside',
            'first_seen_at' => $firstVisit,
            'last_seen_at' => $firstVisit,
            'is_bot' => false,
        ]);

        // Hold created at day 28 (inside 30-day window)
        BookingHold::forceCreate([
            'visitor_token' => $visitor->visitor_token,
            'session_token' => 'sess-cutoff-test',
            'hold_token' => 'hold-tok-cutoff',
            'session_type_id' => $sessionType->id,
            'slot_start_utc' => $firstVisit->addDays(32),
            'slot_end_utc' => $firstVisit->addDays(32)->addMinutes(60),
            'expires_at' => $firstVisit->addDays(32)->addMinutes(10),
            'status' => 'converted',
            'created_at' => $firstVisit->addDays(28),
        ]);

        $contact = Contact::create([
            'email' => 'cutoff@test.com',
            'name' => 'Cutoff Test',
        ]);

        // Actual booking completes at day 32 (OUTSIDE 30-day window)
        Booking::forceCreate([
            'contact_id' => $contact->id,
            'visitor_token' => $visitor->visitor_token,
            'session_type_id' => $sessionType->id,
            'start_at_utc' => $firstVisit->addDays(33),
            'end_at_utc' => $firstVisit->addDays(33)->addMinutes(60),
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'Europe/London',
            'business_local_date_at_booking' => '2026-06-03',
            'business_local_start_time_at_booking' => '13:00',
            'business_local_end_time_at_booking' => '14:00',
            'customer_local_date_at_booking' => '2026-06-03',
            'customer_local_start_time_at_booking' => '11:00',
            'customer_local_end_time_at_booking' => '12:00',
            'business_utc_offset_at_booking' => '+02:00',
            'customer_utc_offset_at_booking' => '+01:00',
            'status' => 'confirmed',
            'idempotency_key' => (string) Str::uuid(),
            'confirmation_token' => Str::random(64),
            'created_at' => $firstVisit->addDays(32),
        ]);

        $this->funnelService->rebuildVisitorFunnel($visitor);

        $prog = VisitorFunnelProgression::where('visitor_id', $visitor->id)->first();
        $this->assertEquals('imputed', $prog->slot_held_provenance, 'Hold inside window qualifies');
        $this->assertEquals('none', $prog->booking_completed_provenance, 'Booking completed outside 30-day window must NOT qualify');
    }

    public function test_raw_event_pruning_followed_by_rebuild_preserves_historical_observed_progressions(): void
    {
        // Visitor from 200 days ago (older than 180-day retention cutoff)
        $oldVisit = CarbonImmutable::now()->subDays(200);
        $visitor = Visitor::create([
            'visitor_token' => 'visitor-pruned-retention',
            'first_seen_at' => $oldVisit,
            'last_seen_at' => $oldVisit,
            'is_bot' => false,
        ]);

        // Manually record historical progression that was observed live 200 days ago
        $prog = $this->funnelService->recordVisit($visitor, $oldVisit);
        $prog->update([
            'booking_cta_observed_at' => $oldVisit->addHour(),
            'booking_cta_qualified_at' => $oldVisit->addHour(),
            'booking_cta_provenance' => 'observed',
            'booking_started_observed_at' => $oldVisit->addHours(2),
            'booking_started_qualified_at' => $oldVisit->addHours(2),
            'booking_started_provenance' => 'observed',
        ]);

        // Raw events for this period have been purged (no AnalyticsEvent rows exist)
        $this->assertDatabaseMissing('analytics_events', ['visitor_token' => $visitor->visitor_token]);

        // Rebuild funnel
        $this->funnelService->rebuildVisitorFunnel($visitor);

        $refreshed = VisitorFunnelProgression::where('visitor_id', $visitor->id)->first();
        $this->assertEquals('observed', $refreshed->booking_cta_provenance, 'Historical observed CTA must be preserved');
        $this->assertEquals('observed', $refreshed->booking_started_provenance, 'Historical observed booking_started must be preserved');
    }

    public function test_cairo_cohort_boundaries_and_midnight_selection(): void
    {
        // Cairo on 2026-09-10 (UTC+3)
        // Cairo midnight: 2026-09-10 00:00:00 (+03:00) = 2026-09-09 21:00:00 UTC
        // Next midnight: 2026-09-11 00:00:00 (+03:00) = 2026-09-10 21:00:00 UTC

        // Visitor A: 1 second before Cairo midnight on Sep 10 -> belongs to Sep 09
        $timeA = CarbonImmutable::parse('2026-09-09 20:59:59', 'UTC');
        $visA = Visitor::create([
            'visitor_token' => 'vis-sep-09-late',
            'first_seen_at' => $timeA,
            'last_seen_at' => $timeA,
            'is_bot' => false,
        ]);
        $this->funnelService->recordVisit($visA);

        // Visitor B: exact Cairo midnight on Sep 10 -> belongs to Sep 10
        $timeB = CarbonImmutable::parse('2026-09-09 21:00:00', 'UTC');
        $visB = Visitor::create([
            'visitor_token' => 'vis-sep-10-midnight',
            'first_seen_at' => $timeB,
            'last_seen_at' => $timeB,
            'is_bot' => false,
        ]);
        $this->funnelService->recordVisit($visB);

        // Visitor C: 1 second before next Cairo midnight -> belongs to Sep 10
        $timeC = CarbonImmutable::parse('2026-09-10 20:59:59', 'UTC');
        $visC = Visitor::create([
            'visitor_token' => 'vis-sep-10-late',
            'first_seen_at' => $timeC,
            'last_seen_at' => $timeC,
            'is_bot' => false,
        ]);
        $this->funnelService->recordVisit($visC);

        // Visitor D: exact next Cairo midnight -> belongs to Sep 11
        $timeD = CarbonImmutable::parse('2026-09-10 21:00:00', 'UTC');
        $visD = Visitor::create([
            'visitor_token' => 'vis-sep-11-midnight',
            'first_seen_at' => $timeD,
            'last_seen_at' => $timeD,
            'is_bot' => false,
        ]);
        $this->funnelService->recordVisit($visD);

        // Query cohort funnel for Cairo date 2026-09-10
        $cairoDate = CarbonImmutable::parse('2026-09-10', 'Africa/Cairo');
        $funnel = $this->funnelService->getCohortFunnel($cairoDate, $cairoDate);

        // Exactly Visitors B and C must qualify (total = 2)
        $this->assertEquals(2, $funnel['visitors'], 'Cohort for 2026-09-10 in Cairo must include exactly visitors between 21:00 UTC Sep 9 and 21:00 UTC Sep 10');
    }

    public function test_durable_reconciliation_audit_persists_cutover_record(): void
    {
        $firstVisit = CarbonImmutable::now()->subDays(5);
        $visitor = Visitor::create([
            'visitor_token' => 'visitor-audit-record-test',
            'first_seen_at' => $firstVisit,
            'last_seen_at' => $firstVisit,
            'is_bot' => false,
        ]);

        Artisan::call('analytics:aggregate-daily', ['--rebuild-funnel' => true, '--date' => $firstVisit->toDateString()]);

        $this->assertDatabaseHas('analytics_reconciliation_audits', [
            'audit_type' => 'rebuild',
        ]);
    }

    public function test_admin_dashboard_renders_exact_labels_provenance_breakdown_and_maturity(): void
    {
        $firstVisit = CarbonImmutable::now()->subDays(5);
        $visitor = Visitor::create([
            'visitor_token' => 'visitor-dashboard-test',
            'first_seen_at' => $firstVisit,
            'last_seen_at' => $firstVisit,
            'is_bot' => false,
        ]);

        $this->funnelService->recordVisit($visitor, $firstVisit);
        $this->funnelService->recordStage($visitor, 'booking_cta', $firstVisit->addHour(), true);

        $response = $this->actingAs($this->admin)->get('/admin/analytics');
        $response->assertStatus(200);

        // Check Stage 2 exact label per Section 4
        $response->assertSee('Booking CTA Reached / Qualified');
        $response->assertSee('Observed:');
        $response->assertSee('Imputed:');

        // Check Cohort Maturity status per Section 6
        $response->assertSee('Immature / In-Progress');
    }

    public function test_partial_raw_event_pruning_preserves_observed_cta_and_replays_retained_event(): void
    {
        // Visitor from 185 days ago: day-1 event falls outside 180-day retention window
        $firstVisit = CarbonImmutable::now()->subDays(185);
        $visitor = Visitor::create([
            'visitor_token' => 'visitor-partial-prune-test',
            'first_seen_at' => $firstVisit,
            'last_seen_at' => $firstVisit,
            'is_bot' => false,
        ]);

        $prog = $this->funnelService->recordVisit($visitor, $firstVisit);
        // Day 1: CTA was observed live
        $day1Cta = $firstVisit->addHours(2);
        $prog->update([
            'booking_cta_observed_at' => $day1Cta,
            'booking_cta_qualified_at' => $day1Cta,
            'booking_cta_provenance' => 'observed',
        ]);

        // Day 1 raw event has been pruned (older than 180 days)
        // Day 10 event (175 days ago) is still RETAINED in analytics_events
        $day10Started = $firstVisit->addDays(10);
        AnalyticsEvent::forceCreate([
            'visitor_token' => $visitor->visitor_token,
            'event_name' => 'booking_started',
            'is_bot' => false,
            'created_at' => $day10Started,
        ]);

        // Rebuild funnel
        $this->funnelService->rebuildVisitorFunnel($visitor);

        $refreshed = VisitorFunnelProgression::where('visitor_id', $visitor->id)->first();

        // 1. Day 1 CTA must remain OBSERVED (not wiped or imputed)
        $this->assertEquals('observed', $refreshed->booking_cta_provenance, 'Partially pruned window must preserve historical observed CTA');
        $this->assertEquals($day1Cta->toDateTimeString(), CarbonImmutable::parse($refreshed->booking_cta_observed_at)->toDateTimeString());

        // 2. Day 10 retained event must be replayed as OBSERVED
        $this->assertEquals('observed', $refreshed->booking_started_provenance, 'Retained raw event must replay as observed');
        $this->assertEquals($day10Started->toDateTimeString(), CarbonImmutable::parse($refreshed->booking_started_observed_at)->toDateTimeString());

        // 3. Repeat rebuild invariance
        $this->funnelService->rebuildVisitorFunnel($visitor);
        $refreshedAgain = VisitorFunnelProgression::where('visitor_id', $visitor->id)->first();
        $this->assertEquals('observed', $refreshedAgain->booking_cta_provenance);
        $this->assertEquals('observed', $refreshedAgain->booking_started_provenance);
    }

    public function test_booking_rebuild_attributes_to_actual_visitor_without_slot_collision_or_cancellation_erasure(): void
    {
        $sessionType = SessionType::create([
            'title' => 'Arabic Consultation',
            'slug' => 'arabic-consultation',
            'duration_minutes' => 60,
            'price' => 50.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        $slotStart = CarbonImmutable::now()->addDays(5);
        $slotEnd = $slotStart->addMinutes(60);

        // Visitor A: visits at day -10
        $visitA = CarbonImmutable::now()->subDays(10);
        $visitorA = Visitor::create([
            'visitor_token' => 'visitor-a-token',
            'first_seen_at' => $visitA,
            'last_seen_at' => $visitA,
            'is_bot' => false,
        ]);
        $this->funnelService->recordVisit($visitorA, $visitA);

        $contactA = Contact::create(['email' => 'visitorA@example.com', 'name' => 'Visitor A']);

        // Visitor A books slot S, and then cancels it
        $bookingA = Booking::forceCreate([
            'contact_id' => $contactA->id,
            'visitor_token' => $visitorA->visitor_token,
            'session_type_id' => $sessionType->id,
            'start_at_utc' => $slotStart,
            'end_at_utc' => $slotEnd,
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'Africa/Cairo',
            'business_local_date_at_booking' => $slotStart->toDateString(),
            'business_local_start_time_at_booking' => '10:00',
            'business_local_end_time_at_booking' => '11:00',
            'customer_local_date_at_booking' => $slotStart->toDateString(),
            'customer_local_start_time_at_booking' => '10:00',
            'customer_local_end_time_at_booking' => '11:00',
            'business_utc_offset_at_booking' => '+02:00',
            'customer_utc_offset_at_booking' => '+02:00',
            'status' => 'cancelled', // Visitor A cancelled!
            'cancelled_at' => $visitA->addDays(2),
            'idempotency_key' => (string) Str::uuid(),
            'confirmation_token' => Str::random(64),
            'created_at' => $visitA->addDay(),
        ]);

        // Visitor B: visits at day -5
        $visitB = CarbonImmutable::now()->subDays(5);
        $visitorB = Visitor::create([
            'visitor_token' => 'visitor-b-token',
            'first_seen_at' => $visitB,
            'last_seen_at' => $visitB,
            'is_bot' => false,
        ]);
        $this->funnelService->recordVisit($visitorB, $visitB);

        $contactB = Contact::create(['email' => 'visitorB@example.com', 'name' => 'Visitor B']);

        // Visitor B rebooks that SAME slot S
        $bookingB = Booking::forceCreate([
            'contact_id' => $contactB->id,
            'visitor_token' => $visitorB->visitor_token,
            'session_type_id' => $sessionType->id,
            'start_at_utc' => $slotStart,
            'end_at_utc' => $slotEnd,
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'Africa/Cairo',
            'business_local_date_at_booking' => $slotStart->toDateString(),
            'business_local_start_time_at_booking' => '10:00',
            'business_local_end_time_at_booking' => '11:00',
            'customer_local_date_at_booking' => $slotStart->toDateString(),
            'customer_local_start_time_at_booking' => '10:00',
            'customer_local_end_time_at_booking' => '11:00',
            'business_utc_offset_at_booking' => '+02:00',
            'customer_utc_offset_at_booking' => '+02:00',
            'status' => 'confirmed',
            'idempotency_key' => (string) Str::uuid(),
            'confirmation_token' => Str::random(64),
            'created_at' => $visitB->addDay(),
        ]);

        // Rebuild both funnels
        $this->funnelService->rebuildVisitorFunnel($visitorA);
        $this->funnelService->rebuildVisitorFunnel($visitorB);

        $progA = VisitorFunnelProgression::where('visitor_id', $visitorA->id)->first();
        $progB = VisitorFunnelProgression::where('visitor_id', $visitorB->id)->first();

        // 1. Visitor A's conversion stage must NOT be erased by subsequent cancellation
        $this->assertEquals('imputed', $progA->booking_completed_provenance, 'Cancelled booking must still count as completed conversion for Visitor A');
        $this->assertEquals($bookingA->created_at->toDateTimeString(), CarbonImmutable::parse($progA->booking_completed_qualified_at)->toDateTimeString());

        // 2. Visitor B's conversion stage is attributed to Visitor B's booking
        $this->assertEquals('imputed', $progB->booking_completed_provenance, 'Visitor B booking must count for Visitor B');
        $this->assertEquals($bookingB->created_at->toDateTimeString(), CarbonImmutable::parse($progB->booking_completed_qualified_at)->toDateTimeString());
    }
}
