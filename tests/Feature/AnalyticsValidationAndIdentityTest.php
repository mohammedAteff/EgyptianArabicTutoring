<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Models\Visitor;
use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Games\Models\Game;
use App\Livewire\BookingWizard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsValidationAndIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected Game $activeGame;

    protected Game $disabledGame;

    protected SessionType $sessionType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activeGame = Game::create([
            'title' => 'Arabic Verb Conjugator',
            'slug' => 'verb-conjugator',
            'description' => 'Test your knowledge of Egyptian Arabic verbs.',
            'category' => 'Grammar',
            'difficulty_level' => 'intermediate',
            'status' => 'available',
        ]);

        $this->disabledGame = Game::create([
            'title' => 'Disabled Vocabulary Quiz',
            'slug' => 'disabled-vocab-quiz',
            'description' => 'A quiz currently turned off.',
            'category' => 'Vocabulary',
            'difficulty_level' => 'beginner',
            'status' => 'coming_soon',
        ]);

        $this->sessionType = SessionType::create([
            'title' => 'Egyptian Arabic Lesson',
            'slug' => 'egyptian-arabic-lesson',
            'duration_minutes' => 60,
            'price' => 40.00,
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
    }

    public function test_generic_endpoint_rejects_client_claimed_resource_downloaded_event(): void
    {
        $response = $this->postJson(route('analytics.track'), [
            'event_name' => 'resource_downloaded',
            'metadata' => [
                'resource_id' => 1,
                'resource_slug' => 'egyptian-slang-guide',
            ],
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'rejected',
            'message' => 'Event must be generated server-side.',
        ]);

        $this->assertDatabaseMissing('analytics_events', [
            'event_name' => 'resource_downloaded',
        ]);
    }

    public function test_game_track_rejects_disabled_game(): void
    {
        $response = $this->postJson(route('games.track', $this->disabledGame->slug), [
            'action' => 'game_started',
            'level' => 'intermediate',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('analytics_events', [
            'event_name' => 'game_started',
        ]);
    }

    public function test_game_track_rejects_nested_metadata(): void
    {
        $response = $this->postJson(route('games.track', $this->activeGame->slug), [
            'action' => 'game_completed',
            'score' => 95,
            'metadata' => [
                'score' => 95,
                'nested_payload' => ['forbidden' => 'attack'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('analytics_events', [
            'event_name' => 'game_completed',
        ]);
    }

    public function test_game_track_rejects_disallowed_metadata_keys(): void
    {
        $response = $this->postJson(route('games.track', $this->activeGame->slug), [
            'action' => 'game_started',
            'metadata' => [
                'admin_secret' => 'leak',
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('analytics_events', [
            'event_name' => 'game_started',
        ]);
    }

    public function test_game_track_rejects_oversized_metadata(): void
    {
        $hugeString = str_repeat('A', 2500);

        $response = $this->postJson(route('games.track', $this->activeGame->slug), [
            'action' => 'game_completed',
            'level' => $hugeString,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('analytics_events', [
            'event_name' => 'game_completed',
        ]);
    }

    public function test_game_track_preserves_immutable_authoritative_game_fields(): void
    {
        $response = $this->postJson(route('games.track', $this->activeGame->slug), [
            'action' => 'game_completed',
            'score' => 100,
            'duration_seconds' => 120,
            'level' => 'hard',
            'metadata' => [
                'score' => 100,
                'duration_seconds' => 120,
                'level' => 'hard',
                // Client attempts to spoof another game slug or title
                'game_slug' => 'spoofed-admin-game',
                'game_title' => 'Fake Title',
            ],
        ]);

        // Disallowed keys 'game_slug' and 'game_title' in client metadata are rejected!
        $response->assertStatus(422);

        // A legitimate tracking request sets authoritative fields correctly
        $validResponse = $this->postJson(route('games.track', $this->activeGame->slug), [
            'action' => 'game_completed',
            'score' => 100,
            'duration_seconds' => 120,
            'level' => 'hard',
        ]);

        $validResponse->assertStatus(200);

        $event = AnalyticsEvent::where('event_name', 'game_completed')->firstOrFail();
        $this->assertEquals($this->activeGame->slug, $event->metadata['game_slug']);
        $this->assertEquals($this->activeGame->title, $event->metadata['game_title']);
        $this->assertEquals(100, $event->metadata['score']);
    }

    public function test_funnel_maintains_consistent_analytics_identity_and_utm_attribution_through_booking(): void
    {
        $visitorToken = (string) Str::uuid();
        $sessionToken = (string) Str::uuid();

        $visitor = Visitor::create([
            'visitor_token' => $visitorToken,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'device_type' => 'desktop',
            'is_bot' => false,
        ]);

        $visSession = VisitorSession::create([
            'session_token' => $sessionToken,
            'visitor_id' => $visitor->id,
            'started_at' => now(),
            'last_activity_at' => now(),
            'utm_source' => 'google_ads',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'egyptian_arabic_cairo',
            'utm_content' => 'ad_variant_1',
            'utm_term' => 'learn cairo arabic',
            'is_bot' => false,
        ]);

        // Simulate session state as established by TrackVisitorSession middleware
        session([
            'analytics_visitor_token' => $visitorToken,
            'analytics_session_token' => $sessionToken,
            'utm_source' => 'google_ads',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'egyptian_arabic_cairo',
            'utm_content' => 'ad_variant_1',
            'utm_term' => 'learn cairo arabic',
        ]);

        $slotDate = CarbonImmutable::now('Africa/Cairo')->addDays(3)->toDateString();
        $slotStartUtc = CarbonImmutable::parse("{$slotDate} 10:00:00", 'Africa/Cairo')->setTimezone('UTC')->toDateTimeString();
        $slotEndUtc = CarbonImmutable::parse("{$slotDate} 11:00:00", 'Africa/Cairo')->setTimezone('UTC')->toDateTimeString();

        Livewire::withQueryParams([
            'utm_source' => 'google_ads',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'egyptian_arabic_cairo',
        ])
            ->test(BookingWizard::class)
            ->call('selectSession', $this->sessionType->id)
            ->call('setDetectedTimezone', 'Africa/Cairo')
            ->call('selectDate', $slotDate)
            ->call('selectSlot', $slotStartUtc, $slotEndUtc, [
                'slot_start_utc' => $slotStartUtc,
                'slot_end_utc' => $slotEndUtc,
                'customer_formatted' => '10:00 AM',
                'customer_formatted_end' => '11:00 AM',
                'customer_date' => $slotDate,
                'business_start_time' => '10:00',
                'business_end_time' => '11:00',
                'business_date' => $slotDate,
            ])
            ->set('name', 'Attributed Student')
            ->set('email', 'attributed.student@example.com')
            ->call('submitDetails')
            ->call('confirmBooking');

        // 1. Verify hold analytics event carries canonical analytics tokens
        $holdEvent = AnalyticsEvent::where('event_name', 'booking_slot_held')->firstOrFail();
        $this->assertEquals($visitorToken, $holdEvent->visitor_token);
        $this->assertEquals($sessionToken, $holdEvent->session_token);

        // 2. Verify booking_completed event carries the SAME canonical analytics tokens
        $completedEvent = AnalyticsEvent::where('event_name', 'booking_completed')->firstOrFail();
        $this->assertEquals($visitorToken, $completedEvent->visitor_token);
        $this->assertEquals($sessionToken, $completedEvent->session_token);

        // 3. Verify Contact inherits UTM attribution
        $contact = Contact::where('email', 'attributed.student@example.com')->firstOrFail();
        $this->assertEquals('google_ads', $contact->utm_source);
        $this->assertEquals('cpc', $contact->utm_medium);
        $this->assertEquals('egyptian_arabic_cairo', $contact->utm_campaign);

        // 4. Verify Booking inherits UTM attribution
        $booking = Booking::where('contact_id', $contact->id)->firstOrFail();
        $this->assertEquals('google_ads', $booking->source);
        $this->assertEquals('cpc', $booking->medium);
        $this->assertEquals('egyptian_arabic_cairo', $booking->campaign);
        $this->assertEquals('ad_variant_1', $booking->content);
        $this->assertEquals('learn cairo arabic', $booking->term);
    }
}
