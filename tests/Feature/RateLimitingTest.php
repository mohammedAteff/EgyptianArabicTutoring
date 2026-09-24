<?php

namespace Tests\Feature;

use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Games\Models\Game;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use App\Domains\Timezone\Services\TimezoneService;
use App\Livewire\BookingWizard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clearResolvedInstances();
    }

    protected function createBooking(SessionType $sessionType, string $email = 'student@example.com', ?string $idempotencyKey = null): Booking
    {
        $contact = Contact::create([
            'name' => 'Rate Limit Student',
            'email' => $email,
        ]);

        $startUtc = CarbonImmutable::now('UTC')->addDays(5);
        $endUtc = $startUtc->addMinutes(50);

        $snapshot = app(TimezoneService::class)->createBookingSnapshot(
            startUtc: $startUtc,
            endUtc: $endUtc,
            customerTimezone: 'Africa/Cairo',
            businessTimezone: 'Africa/Cairo'
        );

        return Booking::create(array_merge($snapshot, [
            'contact_id' => $contact->id,
            'session_type_id' => $sessionType->id,
            'status' => 'confirmed',
            'confirmation_token' => bin2hex(random_bytes(32)),
            'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
            'reschedule_count' => 0,
        ]));
    }

    public function test_booking_cancel_endpoint_is_rate_limited(): void
    {
        $sessionType = SessionType::create([
            'title' => 'Trial Lesson',
            'slug' => 'trial-lesson',
            'duration_minutes' => 50,
            'price' => 25.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        $booking = $this->createBooking($sessionType, 'ratelimit@example.com');

        // Per-token limit is 5 per minute
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post(route('booking.cancel', ['token' => $booking->confirmation_token]));
            $this->assertNotEquals(429, $response->getStatusCode(), "Request {$i} should not be throttled");
        }

        // 6th request with same token must return 429 Too Many Requests
        $throttled = $this->post(route('booking.cancel', ['token' => $booking->confirmation_token]));
        $throttled->assertStatus(429);
    }

    public function test_booking_reschedule_endpoint_is_not_a_public_mutation_endpoint(): void
    {
        $sessionType = SessionType::create([
            'title' => 'Trial Lesson',
            'slug' => 'trial-lesson-resched',
            'duration_minutes' => 50,
            'price' => 25.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        $booking = $this->createBooking($sessionType, 'resched_rate@example.com');

        $response = $this->post(route('booking.reschedule.submit', ['token' => $booking->confirmation_token]), [
            'new_start_utc' => now()->addDays(6)->toDateTimeString(),
        ]);
        $response->assertForbidden();
    }

    public function test_resource_request_endpoint_is_rate_limited(): void
    {
        $category = ResourceCategory::create([
            'name' => 'Grammar Guides',
            'slug' => 'grammar-guides',
            'sort_order' => 1,
        ]);

        $resource = Resource::create([
            'category_id' => $category->id,
            'title' => 'Verb Guide',
            'slug' => 'verb-guide',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'file_type' => 'pdf',
            'file_path' => 'resources/test.pdf',
        ]);

        $email = 'spam_test@example.com';

        for ($i = 0; $i < 5; $i++) {
            $response = $this->post(route('resources.request', ['slug' => $resource->slug]), [
                'name' => 'Spam Tester',
                'email' => $email,
            ]);
            $this->assertNotEquals(429, $response->getStatusCode(), "Request {$i} should not be throttled");
        }

        // 6th request with same email must return 429
        $throttled = $this->post(route('resources.request', ['slug' => $resource->slug]), [
            'name' => 'Spam Tester',
            'email' => $email,
        ]);
        $throttled->assertStatus(429);
    }

    public function test_game_track_endpoint_is_rate_limited(): void
    {
        $game = Game::create([
            'title' => 'Verb Conjugator',
            'slug' => 'verb-conjugator',
            'description' => 'Practice verb forms.',
            'category' => 'Grammar',
            'status' => 'published',
        ]);

        for ($i = 0; $i < 30; $i++) {
            $response = $this->post(route('games.track', ['slug' => $game->slug]), [
                'action' => 'game_started',
            ]);
            $this->assertNotEquals(429, $response->getStatusCode(), "Request {$i} should not be throttled");
        }

        // 31st request must return 429
        $throttled = $this->post(route('games.track', ['slug' => $game->slug]), [
            'action' => 'game_started',
        ]);
        $throttled->assertStatus(429);
    }

    public function test_password_reset_request_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post(route('admin.password.email'), [
                'email' => 'admin_spam@example.com',
            ]);
            $this->assertNotEquals(429, $response->getStatusCode(), "Request {$i} should not be throttled");
        }

        // 6th request must return 429
        $throttled = $this->post(route('admin.password.email'), [
            'email' => 'admin_spam@example.com',
        ]);
        $throttled->assertStatus(429);
    }

    public function test_password_reset_attempt_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post(route('admin.password.update'), [
                'token' => 'invalid-token',
                'email' => 'admin_spam@example.com',
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
            ]);
            $this->assertNotEquals(429, $response->getStatusCode(), "Request {$i} should not be throttled");
        }

        // 6th attempt must return 429
        $throttled = $this->post(route('admin.password.update'), [
            'token' => 'invalid-token',
            'email' => 'admin_spam@example.com',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ]);
        $throttled->assertStatus(429);
    }

    public function test_independent_rate_limit_buckets_do_not_interfere(): void
    {
        // 1. Fill resource-request bucket
        $category = ResourceCategory::create([
            'name' => 'Vocabulary',
            'slug' => 'vocab',
            'sort_order' => 1,
        ]);
        $resource = Resource::create([
            'category_id' => $category->id,
            'title' => 'Vocab List',
            'slug' => 'vocab-list',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'file_type' => 'pdf',
            'file_path' => 'resources/vocab.pdf',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('resources.request', ['slug' => $resource->slug]), [
                'name' => 'Tester',
                'email' => 'bucket_test@example.com',
            ]);
        }
        $this->post(route('resources.request', ['slug' => $resource->slug]), [
            'name' => 'Tester',
            'email' => 'bucket_test@example.com',
        ])->assertStatus(429);

        // 2. Game tracking should still succeed because it has its own independent bucket
        $game = Game::create([
            'title' => 'Vocab Match',
            'slug' => 'vocab-match',
            'description' => 'Match words.',
            'category' => 'Vocab',
            'status' => 'published',
        ]);

        $gameResponse = $this->post(route('games.track', ['slug' => $game->slug]), [
            'action' => 'game_started',
        ]);
        $this->assertNotEquals(429, $gameResponse->getStatusCode(), 'Game track should not be blocked by resource limiter');
    }

    public function test_livewire_slot_hold_rate_limiting(): void
    {
        Setting::set('booking_buffer_minutes', '15');
        $sessionType = SessionType::create([
            'title' => 'Standard Arabic',
            'slug' => 'standard-arabic',
            'duration_minutes' => 50,
            'price' => 30.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        AvailabilityRule::create([
            'weekday' => 4, // Thursday
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'session_duration_minutes' => 50,
            'buffer_minutes' => 15,
            'min_notice_hours' => 24,
            'max_horizon_days' => 60,
            'enabled' => true,
        ]);

        // mount() automatically selects single active session type
        $component = Livewire::test(BookingWizard::class);

        $visitorToken = $component->get('visitorToken');

        // Artificially simulate 5 hold attempts hitting the visitor throttle
        $holdVisitorKey = 'throttle:hold:visitor:'.$visitorToken;
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($holdVisitorKey, 60);
        }

        $component->call('selectSlot', 'invalid-slot-id');

        $component->assertSet('errorMessage', 'Too many slot reservation attempts. Please wait a moment before selecting another slot.');
        $component->assertSet('holdId', null);

        // Verify no hold record was written to DB
        $this->assertEquals(0, BookingHold::where('visitor_token', $visitorToken)->count());
    }

    public function test_livewire_booking_confirmation_rate_limiting(): void
    {
        $sessionType = SessionType::create([
            'title' => 'Business Arabic',
            'slug' => 'business-arabic',
            'duration_minutes' => 50,
            'price' => 40.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        $email = 'spammer@example.com';
        $confirmEmailKey = 'throttle:booking-confirm:email:'.$email;

        // Hit email throttle 3 times
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit($confirmEmailKey, 300);
        }

        // mount() selects the active session type
        $component = Livewire::test(BookingWizard::class)
            ->set('name', 'Spam User')
            ->set('email', $email);

        $component->call('confirmBooking');

        $component->assertSet('errorMessage', 'Too many booking attempts. Please wait a moment before trying again.');
        $this->assertEquals(0, Booking::count());
    }

    public function test_livewire_booking_confirmation_idempotency_bypasses_throttle(): void
    {
        $sessionType = SessionType::create([
            'title' => 'Conversation Arabic',
            'slug' => 'conversation-arabic',
            'duration_minutes' => 50,
            'price' => 30.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        $component = Livewire::test(BookingWizard::class);
        $idempotencyKey = $component->get('idempotencyKey');

        // Existing booking with this idempotency key
        $booking = $this->createBooking($sessionType, 'idempotent@example.com', $idempotencyKey);

        // Max out the throttle
        $confirmEmailKey = 'throttle:booking-confirm:email:idempotent@example.com';
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($confirmEmailKey, 300);
        }

        $component->set('name', 'Existing Customer')
            ->set('email', 'idempotent@example.com');

        // Calling confirmBooking should redirect to confirmation route rather than failing on rate limiter
        $component->call('confirmBooking')
            ->assertRedirect(route('booking.confirmation', ['token' => $booking->confirmation_token]));
    }
}
