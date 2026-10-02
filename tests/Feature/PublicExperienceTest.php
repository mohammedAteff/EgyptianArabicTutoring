<?php

namespace Tests\Feature;

use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\MeetingProvider;
use App\Domains\Booking\Models\MeetingRoom;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingService;
use App\Domains\Booking\Services\MeetingLinkService;
use App\Domains\CMS\Models\Faq;
use App\Domains\CMS\Models\Setting;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Services\ContactService;
use App\Domains\Games\Models\Game;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use App\Domains\Resources\Models\ResourceDownload;
use App\Domains\Resources\Services\EmailQualityService;
use App\Livewire\BookingWizard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\HasPublishedShortForm;
use Tests\Support\IssuesBookingSlotIds;
use Tests\TestCase;

class PublicExperienceTest extends TestCase
{
    use HasPublishedShortForm;
    use IssuesBookingSlotIds;
    use RefreshDatabase;

    protected SessionType $sessionType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installShortFormFixture();
        $quality = $this->getMockBuilder(EmailQualityService::class)->onlyMethods(['dnsRecords'])->getMock();
        $quality->method('dnsRecords')->willReturn([['type' => 'MX', 'target' => 'mx.example.test']]);
        $this->app->instance(EmailQualityService::class, $quality);

        $this->sessionType = SessionType::create([
            'title' => '1-on-1 Tutoring',
            'slug' => 'one-on-one',
            'duration_minutes' => 60,
            'price' => 35.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        // Weekly recurring rule for availability: Sunday 09:00 - 13:00 Cairo
        AvailabilityRule::create([
            'weekday' => 0,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'session_duration_minutes' => 60,
            'buffer_minutes' => 0,
            'min_notice_hours' => 0,
            'enabled' => true,
        ]);
    }

    public function test_homepage_loads_successfully_with_hero_and_content(): void
    {
        Setting::set('site_name', 'Egyptian Arabic Tutoring', 'general', true);
        Setting::set('hero_title', 'Speak Egyptian Arabic with Confidence', 'homepage', true);

        Faq::create([
            'question' => 'How are lessons conducted?',
            'answer' => 'Sessions take place 1-on-1 via Zoom or Google Meet.',
            'category' => 'general',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSeeText('Speak Egyptian Arabic with Confidence');
        $response->assertSeeText('Book a Private Lesson');
        $response->assertSeeText('How are lessons conducted?');
    }

    public function test_booking_page_renders_livewire_wizard(): void
    {
        $this->get('/book')->assertStatus(301)->assertRedirect('/booking');

        $response = $this->get('/booking');
        $response->assertStatus(200);
        $response->assertSeeLivewire(BookingWizard::class);
    }

    public function test_livewire_booking_wizard_step_flow_and_completion(): void
    {
        $nextSunday = CarbonImmutable::now('Africa/Cairo')->next(CarbonImmutable::SUNDAY);
        $slotDate = $nextSunday->toDateString();
        $slotStartUtc = CarbonImmutable::parse("{$slotDate} 09:00:00", 'Africa/Cairo')->setTimezone('UTC')->toDateTimeString();
        $component = Livewire::test(BookingWizard::class)
            ->call('setDetectedTimezone', 'America/New_York')
            ->assertSet('customerTimezone', 'America/New_York')
            ->call('selectDate', $slotDate)
            ->assertSet('selectedDate', $slotDate);
        $component->call('selectSlot', $this->slotIdFor($this->sessionType, $slotStartUtc, 'America/New_York', $component->get('visitorToken')))
            ->assertSet('currentStep', 3)
            ->set('first_name', 'Laila')
            ->set('last_name', 'Vance')
            ->set('date_of_birth', '1990-08-15')
            ->set('email', 'Laila.Vance@Example.com')
            ->set('phone', '+12025550123')
            ->set('notes', 'Planning a trip to Luxor and Cairo next month.')
            ->call('submitDetails')
            ->assertSet('currentStep', 4)
            ->assertSee('9:00 AM')
            ->call('confirmBooking');

        $booking = Booking::query()->where('customer_timezone', 'America/New_York')->first();
        $this->assertNotNull($booking);
        $this->assertEquals('laila.vance@example.com', $booking->contact->email);
        $this->assertEquals('Laila Vance', $booking->contact->name);

        $component->assertRedirect(route('booking.confirmation', ['token' => $booking->confirmation_token]));
    }

    public function test_booking_confirmation_page_displays_details_and_ics_download(): void
    {
        $contact = Contact::create([
            'name' => 'Kareem Tarek',
            'email' => 'kareem@example.com',
            'display_email' => 'Kareem@example.com',
        ]);

        $startUtc = CarbonImmutable::now('Africa/Cairo')->next(CarbonImmutable::SUNDAY)->setTime(10, 0, 0)->setTimezone('UTC');
        $endUtc = $startUtc->addHour();

        $booking = app(BookingService::class)->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $startUtc,
            'end_at_utc' => $endUtc,
            'customer_timezone' => 'Europe/London',
            'customer_name' => 'Kareem Tarek',
            'customer_email' => 'kareem@example.com',
            'idempotency_key' => 'idemp-conf-page-1',
        ], isTrustedAdmin: true);

        // Confirmation page
        $response = $this->get(route('booking.confirmation', ['token' => $booking->confirmation_token]));
        $response->assertStatus(200);
        $response->assertSeeText('Confirmed');
        $response->assertSeeText('Europe/London');
        $response->assertSeeText('Africa/Cairo');
        $response->assertSeeText('Your tutor will share the private video meeting link before the lesson.');

        // .ics calendar file download
        $icsResponse = $this->get(route('booking.ics', ['token' => $booking->confirmation_token]));
        $icsResponse->assertStatus(200);
        $icsResponse->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString('BEGIN:VCALENDAR', $icsResponse->getContent());
        $this->assertStringContainsString($booking->confirmation_token, $icsResponse->getContent());
        $this->assertStringNotContainsString('meet.google.com', $icsResponse->getContent());

        $room = MeetingRoom::factory()->create(['meeting_provider_id' => MeetingProvider::where('is_default', true)->firstOrFail()->id, 'url' => 'https://meet.example.test/arabic-room', 'url_hash' => hash('sha256', 'https://meet.example.test/arabic-room')]);
        $booking = app(MeetingLinkService::class)->assign($booking, roomId: $room->id);
        $this->travelTo($startUtc->subMinutes(15));
        $this->get(route('booking.confirmation', ['token' => $booking->confirmation_token]))
            ->assertSee('href="https://meet.example.test/arabic-room"', false)
            ->assertSeeText('Join Video Classroom');
        $icsResponse = $this->get(route('booking.ics', ['token' => $booking->confirmation_token]));
        $this->assertStringContainsString('LOCATION:https://meet.example.test/arabic-room', $icsResponse->getContent());
    }

    public function test_resources_catalog_and_category_filtering(): void
    {
        $category = ResourceCategory::create([
            'name' => 'Survival Egyptian',
            'slug' => 'survival-egyptian',
            'sort_order' => 1,
            'active' => true,
        ]);

        Resource::create([
            'category_id' => $category->id,
            'title' => 'Cairo Street Phrases Guide',
            'slug' => 'cairo-street-phrases-guide',
            'short_description' => '50 must-know street phrases for daily life in Cairo.',
            'status' => 'published',
            'file_type' => 'pdf',
            'published_at' => now(),
        ]);

        $response = $this->get(route('resources.index'));
        $response->assertStatus(200);
        $response->assertSeeText('Cairo Street Phrases Guide');
        $response->assertSeeText('Survival Egyptian');

        // Filtered by category
        $filteredResponse = $this->get(route('resources.index', ['category' => 'survival-egyptian']));
        $filteredResponse->assertStatus(200);
        $filteredResponse->assertSeeText('Cairo Street Phrases Guide');
    }

    public function test_resource_gate_submission_and_direct_download(): void
    {
        $category = ResourceCategory::create([
            'name' => 'Grammar & Dialect',
            'slug' => 'grammar-dialect',
            'active' => true,
        ]);

        $filePath = 'resources/test-verbs.pdf';
        Storage::disk('local')->put($filePath, '%PDF-1.4 real test pdf content');

        $resource = Resource::create([
            'category_id' => $category->id,
            'title' => 'Egyptian Verbs Masterclass',
            'slug' => 'egyptian-verbs-masterclass',
            'short_description' => 'Present and past tense conjugation cheatsheet.',
            'status' => 'published',
            'file_type' => 'pdf',
            'file_path' => $filePath,
            'is_gated' => true,
            'published_at' => now(),
        ]);

        // Detail page loads
        $showResponse = $this->get(route('resources.show', $resource->slug));
        $showResponse->assertStatus(200);
        $showResponse->assertSeeText('Egyptian Verbs Masterclass');
        $visitorCookie = $showResponse->getCookie('_va_visitor');
        $sessionCookie = $showResponse->getCookie('_va_session');
        $this->assertNotNull($visitorCookie);
        $this->assertNotNull($sessionCookie);

        // Request access via email gate
        $requestResponse = $this->withCredentials()->withCookies([
            '_va_visitor' => $visitorCookie->getValue(),
            '_va_session' => $sessionCookie->getValue(),
        ])->post(route('resources.request', $resource->slug), [
            'name' => 'Omar Sherif',
            'email' => 'Omar.Sherif@Example.com',
        ]);

        $requestResponse->assertRedirect(route('resources.show', ['slug' => $resource->slug]));
        $requestResponse->assertSessionHas('access_granted', true);

        // Contact should be recorded
        $this->assertDatabaseHas('contacts', [
            'email' => 'omar.sherif@example.com',
            'display_email' => 'Omar.Sherif@Example.com',
            'name' => 'Omar Sherif',
        ]);

        // Resource request should be recorded
        $this->assertDatabaseHas('resource_requests', [
            'resource_id' => $resource->id,
        ]);

        // Analytics event should be recorded
        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'resource_requested',
        ]);

        // Download route serves PDF with the issued download token
        $downloadToken = session('download_token');
        $sessionCookie = $requestResponse->getCookie(config('session.cookie'));
        $visitorCookie = $requestResponse->getCookie('_va_visitor');
        $analyticsSessionCookie = $requestResponse->getCookie('_va_session');
        $requestedContact = Contact::query()->where('email', 'omar.sherif@example.com')->firstOrFail();
        $canonicalContact = Contact::query()->create([
            'name' => 'Canonical Download Contact',
            'email' => 'canonical-download-contact@example.com',
        ]);
        app(ContactService::class)->merge($canonicalContact, $requestedContact);

        // A different browser session cannot consume the grant, and the
        // rejected attempt must not burn the legitimate visitor's token.
        if ($sessionCookie) {
            $this->withCookie(config('session.cookie'), 'invalid-session-cookie');
            $unauthorizedResponse = $this->get(route('resources.download', [
                'slug' => $resource->slug,
                'token' => $downloadToken,
            ]));
            $unauthorizedResponse->assertRedirect(route('resources.show', ['slug' => $resource->slug]));
            $this->assertSame(0, ResourceDownload::where('resource_id', $resource->id)->count());
        }

        foreach ([
            [config('session.cookie'), $sessionCookie],
            ['_va_visitor', $visitorCookie],
            ['_va_session', $analyticsSessionCookie],
        ] as [$cookieName, $cookie]) {
            if ($cookie) {
                $this->withCookie($cookieName, $cookie->getValue());
            }
        }
        $downloadResponse = $this->get(route('resources.download', ['slug' => $resource->slug, 'token' => $downloadToken]));
        $downloadResponse->assertStatus(200);
        $downloadResponse->assertHeader('Content-Type', 'application/pdf');

        // Resource download should be recorded
        $this->assertDatabaseHas('resource_downloads', [
            'resource_id' => $resource->id,
            'contact_id' => $canonicalContact->id,
        ]);
        $this->assertDatabaseHas('resource_requests', [
            'resource_id' => $resource->id,
            'contact_id' => $canonicalContact->id,
        ]);

        // A gated grant is bound to the issuing session and cannot be replayed.
        $replayResponse = $this->get(route('resources.download', [
            'slug' => $resource->slug,
            'token' => $downloadToken,
        ]));
        $replayResponse->assertRedirect(route('resources.show', ['slug' => $resource->slug]));
        $this->assertSame(1, ResourceDownload::where('resource_id', $resource->id)->count());

        // Clean up test file
        Storage::disk('local')->delete($filePath);
    }

    public function test_gated_resource_cannot_be_downloaded_without_token_or_email_submission(): void
    {
        $category = ResourceCategory::create([
            'name' => 'Grammar & Dialect',
            'slug' => 'grammar-dialect-2',
            'active' => true,
        ]);

        $filePath = 'resources/test-verbs-gated.pdf';
        Storage::disk('local')->put($filePath, '%PDF-1.4 gated');

        $resource = Resource::create([
            'category_id' => $category->id,
            'title' => 'Gated Grammar Guide',
            'slug' => 'gated-grammar-guide',
            'short_description' => 'Cheatsheet',
            'status' => 'published',
            'file_type' => 'pdf',
            'file_path' => $filePath,
            'is_gated' => true,
            'published_at' => now(),
        ]);

        // Attempt direct download with no session or token
        $response = $this->get(route('resources.download', $resource->slug));
        $response->assertRedirect(route('resources.show', ['slug' => $resource->slug]));
        $response->assertSessionHas('error');

        // No download recorded
        $this->assertEquals(0, ResourceDownload::where('resource_id', $resource->id)->count());

        Storage::disk('local')->delete($filePath);
    }

    public function test_missing_resource_file_returns_404_instead_of_fake_pdf(): void
    {
        $category = ResourceCategory::create([
            'name' => 'Grammar & Dialect',
            'slug' => 'grammar-dialect-3',
            'active' => true,
        ]);

        $resource = Resource::create([
            'category_id' => $category->id,
            'title' => 'Missing File Guide',
            'slug' => 'missing-file-guide',
            'short_description' => 'Cheatsheet',
            'status' => 'published',
            'file_type' => 'pdf',
            'file_path' => 'resources/nonexistent-file.pdf',
            'is_gated' => false, // ungated so token check passes
            'published_at' => now(),
        ]);

        $response = $this->get(route('resources.download', $resource->slug));
        $response->assertStatus(404);
    }

    public function test_games_catalog_and_interactive_event_tracking(): void
    {
        $game = Game::create([
            'title' => 'Egyptian Street Numbers Challenge',
            'slug' => 'egyptian-street-numbers-challenge',
            'description' => 'Master Arabic numbers 1 to 100.',
            'status' => 'available',
            'sort_order' => 1,
        ]);

        // Games catalog
        $indexResponse = $this->get(route('games.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSeeText('Egyptian Street Numbers Challenge');

        // Game show & open event
        $showResponse = $this->get(route('games.show', $game->slug));
        $showResponse->assertStatus(200);
        $showResponse->assertSeeText('Egyptian Street Numbers Challenge');

        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'game_opened',
        ]);

        // Game start tracking
        $trackStart = $this->postJson(route('games.track', $game->slug), [
            'action' => 'game_started',
        ]);
        $trackStart->assertStatus(200);
        $trackStart->assertJson(['status' => 'tracked', 'action' => 'game_started']);

        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'game_started',
        ]);

        // Game completion tracking
        $trackComplete = $this->postJson(route('games.track', $game->slug), [
            'action' => 'game_completed',
            'metadata' => ['score' => 5, 'total' => 5],
        ]);
        $trackComplete->assertStatus(200);
        $trackComplete->assertJson(['status' => 'tracked', 'action' => 'game_completed']);

        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'game_completed',
        ]);
    }

    public function test_internal_browser_urls_include_configured_application_subpath(): void
    {
        $game = Game::create([
            'title' => 'Subpath Game',
            'slug' => 'subpath-game',
            'description' => 'Checks prefix-aware browser URLs.',
            'status' => 'available',
        ]);

        $server = [
            'HTTPS' => 'on',
            'HTTP_HOST' => 'mohamedateff.com',
            'SCRIPT_NAME' => '/arabictutor/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
        ];

        $gameResponse = $this->withServerVariables($server)
            ->get('https://mohamedateff.com/arabictutor/games/subpath-game');

        $gameResponse->assertOk();
        $gameResponse->assertSee('mohamedateff.com\\/arabictutor\\/games\\/subpath-game\\/track', false);
        $gameResponse->assertSee('href="https://mohamedateff.com/arabictutor/booking"', false);
        $gameResponse->assertDontSee('/admin/login', false);
        $gameResponse->assertSee('data-update-uri="https://mohamedateff.com/arabictutor/livewire-', false);
        $gameResponse->assertDontSee('/arabictutor/arabictutor/', false);
    }

    public function test_external_game_card_renders_with_preview_image_badge_and_clickable_link(): void
    {
        $externalGame = Game::create([
            'title' => '6-Word Story',
            'slug' => '6-word-story',
            'description' => 'Think fast, speak continuously, and practice Egyptian Arabic through quick speaking challenges.',
            'badge' => 'Speaking Practice',
            'thumbnail_path' => 'images/games/6-word-story.webp',
            'target_url' => 'https://mohamedateff.com/6word',
            'status' => 'available',
            'featured' => true,
            'sort_order' => 1,
        ]);

        $response = $this->get(route('games.index'));
        $response->assertStatus(200);
        $response->assertSeeText('Available Games');
        $response->assertSeeText('6-Word Story');
        $response->assertSeeText('Speaking Practice');
        $response->assertSeeText('Think fast, speak continuously');
        $response->assertDontSee('Games are currently being updated. Check back shortly!');

        // Assert link target, rel, and URL
        $response->assertSee('href="https://mohamedateff.com/6word"', false);
        $response->assertSee('target="_blank"', false);
        $response->assertSee('rel="noopener noreferrer"', false);
        $response->assertSee('images/games/6-word-story.webp', false);

        // Assert show route redirects directly to external game and logs event
        $showResponse = $this->get(route('games.show', $externalGame->slug));
        $showResponse->assertRedirect('https://mohamedateff.com/6word');
        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'game_opened',
        ]);
    }

    public function test_static_pages_load_successfully(): void
    {
        $this->get(route('about'))->assertStatus(200)->assertSeeText('Meet Your Tutor, Abdallah');
        $this->get(route('faq'))->assertStatus(200)->assertSeeText('Frequently Asked Questions');
        $this->get(route('terms'))->assertStatus(200)->assertSeeText('Terms of Service & Booking Policy');
        $this->get(route('privacy'))->assertStatus(200)->assertSeeText('Privacy Policy & Data Ethics');
    }
}
