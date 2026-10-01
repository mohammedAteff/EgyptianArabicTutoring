<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Page;
use App\Domains\CMS\Services\LocalizedUrlService;
use App\Domains\CMS\Services\TranslationService;
use App\Domains\Games\Models\Game;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use App\Livewire\BookingWizard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\Support\IssuesBookingSlotIds;
use Tests\TestCase;

class LocalizedRoutingAndBookingStateTest extends TestCase
{
    use IssuesBookingSlotIds;
    use RefreshDatabase;

    protected SessionType $sessionType;

    protected Resource $resource;

    protected Game $game;

    protected LocalizedUrlService $urlService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->urlService = app(LocalizedUrlService::class);

        $this->sessionType = SessionType::create([
            'title' => 'Egyptian Arabic Lesson',
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

        $category = ResourceCategory::create([
            'name' => 'Grammar Guides',
            'slug' => 'grammar',
            'active' => true,
        ]);

        $this->resource = Resource::create([
            'category_id' => $category->id,
            'title' => 'Survival Guide',
            'slug' => 'survival-guide',
            'description' => 'Essential Egyptian Arabic survival phrases',
            'file_path' => 'resources/survival.pdf',
            'file_type' => 'pdf',
            'file_size' => 1024,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->game = Game::create([
            'title' => 'Cairo Vocab Challenge',
            'slug' => 'cairo-vocab',
            'description' => 'Test your vocabulary',
            'game_type' => 'quiz',
            'status' => 'available',
        ]);

        // Seed pages
        Page::create([
            'slug' => 'about',
            'title' => 'About Ahmad',
            'content' => 'Experienced Arabic tutor',
            'status' => 'published',
        ]);
        Page::create([
            'slug' => 'faq',
            'title' => 'Frequently Asked Questions',
            'content' => 'Common questions answered',
            'status' => 'published',
        ]);
        Page::create([
            'slug' => 'terms',
            'title' => 'Terms of Service',
            'content' => 'Terms and conditions',
            'status' => 'published',
        ]);
        Page::create([
            'slug' => 'privacy',
            'title' => 'Privacy Policy',
            'content' => 'Privacy and data policy',
            'status' => 'published',
        ]);

        $admin = Administrator::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
            'active' => true,
        ]);

        // Create English translations
        app(TranslationService::class)->updateEnglishSource($this->resource, [
            'title' => 'Survival Guide',
            'short_description' => 'Essential Egyptian Arabic survival phrases',
        ], $admin->id);

        app(TranslationService::class)->updateEnglishSource($this->game, [
            'title' => 'Cairo Vocab Challenge',
            'description' => 'Test your vocabulary',
        ], $admin->id);
    }

    public function test_section_26_canonical_routing_table_status_codes(): void
    {
        // English routes
        $this->get('/')->assertStatus(200);
        $this->get('/booking')->assertStatus(200);
        $this->get('/resources')->assertStatus(200);
        $this->get('/resources/survival-guide')->assertStatus(200);
        $this->get('/games')->assertStatus(200);
        $this->get('/games/cairo-vocab')->assertStatus(200);
        $this->get('/about')->assertStatus(200);
        $this->get('/faq')->assertStatus(200);
        $this->get('/terms')->assertStatus(200);
        $this->get('/privacy')->assertStatus(200);

        // French routes
        $this->get('/fr')->assertStatus(200);
        $this->get('/fr/reservation')->assertStatus(200);
        $this->get('/fr/ressources')->assertStatus(200);
        $this->get('/fr/ressources/survival-guide')->assertStatus(200);
        $this->get('/fr/jeux')->assertStatus(200);
        $this->get('/fr/jeux/cairo-vocab')->assertStatus(200);
        $this->get('/fr/a-propos')->assertStatus(200);
        $this->get('/fr/faq')->assertStatus(200);
        $this->get('/fr/conditions')->assertStatus(200);
        $this->get('/fr/confidentialite')->assertStatus(200);

        // German routes
        $this->get('/de')->assertStatus(200);
        $this->get('/de/buchen')->assertStatus(200);
        $this->get('/de/ressourcen')->assertStatus(200);
        $this->get('/de/ressourcen/survival-guide')->assertStatus(200);
        $this->get('/de/spiele')->assertStatus(200);
        $this->get('/de/spiele/cairo-vocab')->assertStatus(200);
        $this->get('/de/ueber-uns')->assertStatus(200);
        $this->get('/de/faq')->assertStatus(200);
        $this->get('/de/agb')->assertStatus(200);
        $this->get('/de/datenschutz')->assertStatus(200);
    }

    public function test_legacy_book_route_301_redirects_to_booking(): void
    {
        $response = $this->get('/book');
        $response->assertStatus(301);
        $response->assertRedirect('/booking');
    }

    public function test_trailing_slash_routes_301_redirect(): void
    {
        $cases = [
            '/fr/' => url('/fr'),
            '/fr/reservation/' => url('/fr/reservation'),
            '/de/buchen/' => url('/de/buchen'),
            '/de/ressourcen/?category=grammar' => url('/de/ressourcen?category=grammar'),
        ];

        foreach ($cases as $uri => $expectedLocation) {
            $req = Request::create($uri, 'GET');
            $response = $this->app->handle($req);
            $this->assertEquals(301, $response->getStatusCode());
            $this->assertEquals(
                parse_url($expectedLocation, PHP_URL_PATH),
                parse_url($response->headers->get('Location'), PHP_URL_PATH)
            );
            if ($query = parse_url($expectedLocation, PHP_URL_QUERY)) {
                $this->assertEquals($query, parse_url($response->headers->get('Location'), PHP_URL_QUERY));
            }
        }
    }

    public function test_in_progress_booking_state_preservation_across_language_switch(): void
    {
        $slotStart = CarbonImmutable::now('UTC')->addDays(5)->setTime(10, 0, 0);
        // Step 1: Start booking in English
        $component = Livewire::test(BookingWizard::class)
            ->call('selectTimezone', 'Europe/Paris')
            ->call('selectDate', $slotStart->setTimezone('Europe/Paris')->format('Y-m-d'));
        $component->call('selectSlot', $this->slotIdFor($this->sessionType, $slotStart->toDateTimeString(), 'Europe/Paris', $component->get('visitorToken')))
            ->set('first_name', 'Nadia')
            ->set('last_name', 'Benali')
            ->set('date_of_birth', '1990-05-15')
            ->set('email', 'nadia@example.com')
            ->set('phone', '+33612345678')
            ->set('notes', 'Interested in Egyptian slang')
            ->call('submitDetails');

        $this->assertEquals(4, $component->get('currentStep'));
        $holdId = $component->get('holdId');
        $holdToken = $component->get('holdToken');
        $this->assertNotNull($holdId);
        $this->assertNotNull($holdToken);

        // Check session state contains all unsubmitted inputs and hold
        $sessionState = Session::get('booking_flow_state');
        $this->assertNotNull($sessionState);
        $this->assertEquals('Nadia Benali', $sessionState['name']);
        $this->assertEquals('nadia@example.com', $sessionState['email']);
        $this->assertEquals('+33612345678', $sessionState['phone']);
        $this->assertEquals('Interested in Egyptian slang', $sessionState['notes']);
        $this->assertEquals('Europe/Paris', $sessionState['customer_timezone']);
        $this->assertEquals($holdId, $sessionState['hold_id']);

        // Step 2: Switch to French (/fr/reservation)
        // A new instance mounts in the French context within the same session
        $frenchComponent = Livewire::test(BookingWizard::class);

        // Verify all fields, step, and the exact same hold survive
        $this->assertEquals(4, $frenchComponent->get('currentStep'), 'Step must be preserved');
        $this->assertEquals('Nadia Benali', $frenchComponent->get('name'));
        $this->assertEquals('nadia@example.com', $frenchComponent->get('email'));
        $this->assertEquals('+33612345678', $frenchComponent->get('phone'));
        $this->assertEquals('Interested in Egyptian slang', $frenchComponent->get('notes'));
        $this->assertEquals('Europe/Paris', $frenchComponent->get('customerTimezone'));
        $this->assertEquals($holdId, $frenchComponent->get('holdId'), 'Hold ID must be reused');
        $this->assertEquals($holdToken, $frenchComponent->get('holdToken'), 'Hold token must be reused');

        // Verify no second hold row was created in database
        $holdCount = BookingHold::where('session_token', session()->getId())->count();
        $this->assertEquals(1, $holdCount, 'Language switch must never create a duplicate hold row');
    }

    public function test_expired_hold_is_not_restored_on_language_switch(): void
    {
        $slotStart = CarbonImmutable::now('UTC')->addDays(5)->setTime(10, 0, 0);
        $component = Livewire::test(BookingWizard::class);
        $component->call('selectSlot', $this->slotIdFor($this->sessionType, $slotStart->toDateTimeString(), 'Africa/Cairo', $component->get('visitorToken')))
            ->set('first_name', 'Hans')
            ->set('last_name', 'Schmidt')
            ->set('date_of_birth', '1990-06-20')
            ->set('email', 'hans@example.de');

        $holdId = $component->get('holdId');
        $this->assertNotNull($holdId);

        // Simulate hold expiration in database
        BookingHold::where('id', $holdId)->update([
            'expires_at' => now()->subMinutes(5),
            'status' => 'expired',
        ]);

        // Load German booking page (/de/buchen)
        $germanComponent = Livewire::test(BookingWizard::class);

        $this->assertNull($germanComponent->get('holdId'), 'Expired hold must not be restored');
        $this->assertEquals(2, $germanComponent->get('currentStep'), 'User must be sent back to slot selection');
        $this->assertEquals('Hans Schmidt', $germanComponent->get('name'), 'Form inputs should still survive');
    }

    public function test_foreign_session_cannot_hijack_hold_state(): void
    {
        $slotStart = CarbonImmutable::now('UTC')->addDays(5)->setTime(10, 0, 0);
        $component = Livewire::test(BookingWizard::class);
        $component->call('selectSlot', $this->slotIdFor($this->sessionType, $slotStart->toDateTimeString(), 'Africa/Cairo', $component->get('visitorToken')))
            ->set('first_name', 'Legitimate')
            ->set('last_name', 'User')
            ->set('date_of_birth', '1990-07-25');

        // Tamper with visitor token in session to simulate foreign attacker
        $sessionState = Session::get('booking_flow_state');
        $sessionState['visitor_token'] = 'attacker-fake-token';
        Session::put('booking_flow_state', $sessionState);

        $attackerComponent = Livewire::test(BookingWizard::class);

        $this->assertNull($attackerComponent->get('holdId'), 'Attacker with mismatched visitor token must not restore hold');
        $this->assertEmpty($attackerComponent->get('name'), 'Attacker must not see unsubmitted inputs');
    }

    public function test_section_27_missing_translation_fallback_contract(): void
    {
        // Survival Guide has English translation only. German translation does not exist.
        // Request German resource detail: /de/ressourcen/survival-guide
        $response = $this->get('/de/ressourcen/survival-guide');

        // 1. Status 200 OK (not 404)
        $response->assertStatus(200);

        // 2. Localized German shell is rendered
        $response->assertSee(__('Request Free Access'), false);

        // 3. Fallback banner is displayed in German
        $response->assertSee(__('content_fallback_banner'), false);

        // 4. Content container explicitly tagged <div lang="en" dir="ltr">
        $response->assertSee('lang="en"', false);
        $response->assertSee('dir="ltr"', false);

        // 5. English title and content rendered
        $response->assertSee('Survival Guide');

        // 6. Canonical link points to the application-generated English URL
        $expectedCanonical = url('/resources/survival-guide');
        $response->assertSee('<link rel="canonical" href="'.$expectedCanonical.'"', false);

        // 7. Zero hreflang tags emitted for fallback page
        $response->assertDontSee('hreflang="de"');
        $response->assertDontSee('hreflang="en"');
    }

    public function test_section_27_published_translation_canonical_and_hreflang(): void
    {
        // Publish a French translation for Survival Guide
        app(TranslationService::class)->saveDraft($this->resource, 'fr', [
            'title' => 'Guide de Survie',
            'short_description' => 'Phrases essentielles en arabe égyptien',
        ]);
        app(TranslationService::class)->publishTranslation($this->resource, 'fr');

        $response = $this->get('/fr/ressources/survival-guide');
        $response->assertStatus(200);

        // French title rendered
        $response->assertSee('Guide de Survie');

        // Fallback banner NOT rendered
        $response->assertDontSee(__('content_fallback_banner'));

        // Canonical points to French URL
        $expectedCanonical = url('/fr/ressources/survival-guide');
        $response->assertSee('<link rel="canonical" href="'.$expectedCanonical.'"', false);

        // Hreflang alternates emitted
        $response->assertSee('hreflang="fr"', false);
        $response->assertSee('hreflang="en"', false);
    }
}
