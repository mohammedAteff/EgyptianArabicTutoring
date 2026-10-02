<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Models\AdminNotification;
use App\Domains\Administration\Services\AdminNotificationService;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\CancellationService;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Games\Models\Game;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminFunctionsAndCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected Administrator $superAdmin;

    protected Administrator $ordinaryAdmin;

    protected SessionType $sessionType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = Administrator::create([
            'name' => 'Super Tutor',
            'email' => 'super@boltlanding.test',
            'password' => Hash::make('Password123!'),
            'role' => 'super_admin',
        ]);

        $this->ordinaryAdmin = Administrator::create([
            'name' => 'Staff Assistant',
            'email' => 'staff@boltlanding.test',
            'password' => Hash::make('Password123!'),
            'role' => 'admin',
        ]);

        $this->sessionType = SessionType::create([
            'title' => 'Trial 1-on-1 Lesson',
            'slug' => 'trial-lesson',
            'duration_minutes' => 60,
            'price' => 0.00,
            'currency' => 'USD',
            'active' => true,
        ]);
    }

    public function test_notification_creation_read_state_and_deduplication(): void
    {
        $service = app(AdminNotificationService::class);

        // 1. Creation
        $n1 = $service->send(
            type: 'booking_created',
            title: 'New Student Booking',
            message: 'Sarah booked for tomorrow.',
            link: '/admin/bookings/1',
            level: 'success'
        );

        $this->assertDatabaseHas('admin_notifications', [
            'id' => $n1->id,
            'title' => 'New Student Booking',
            'read_at' => null,
        ]);

        // 2. Deduplication within 15 minutes updates repeat_count rather than creating a duplicate row
        $n2 = $service->send(
            type: 'booking_created',
            title: 'New Student Booking',
            message: 'Sarah booked for tomorrow again.',
            link: '/admin/bookings/1',
            level: 'success'
        );

        $this->assertEquals($n1->id, $n2->id);
        $this->assertEquals(1, AdminNotification::where('title', 'New Student Booking')->count());
        $this->assertEquals(2, $n2->data['repeat_count']);

        // 3. Mark as read
        $this->actingAs($this->superAdmin, 'web')
            ->post(route('admin.notifications.read', $n1))
            ->assertRedirect();

        $this->assertNotNull($n1->fresh()->read_at);

        // 4. Mark all as read
        $n3 = $service->send('resource_requested', 'Lead #2', 'Lead text', level: 'info');
        $this->assertNull($n3->fresh()->read_at);

        $this->actingAs($this->superAdmin, 'web')
            ->post(route('admin.notifications.read-all'))
            ->assertRedirect();

        $this->assertNotNull($n3->fresh()->read_at);

        // 5. Dismiss / Destroy
        $this->actingAs($this->superAdmin, 'web')
            ->delete(route('admin.notifications.destroy', $n1))
            ->assertRedirect();

        $this->assertDatabaseMissing('admin_notifications', ['id' => $n1->id]);
    }

    public function test_notification_authorization_super_admin_vs_ordinary_admin(): void
    {
        $service = app(AdminNotificationService::class);

        $service->send('booking_created', 'Booking Notice', 'Sarah booked', level: 'success');
        $service->send('backup_failure', 'Backup Failure', 'Disk full', level: 'danger');
        $service->send('system_warning', 'Worker Alert', 'Queue timeout', level: 'danger');

        // Ordinary admin only sees operational booking notification
        $this->actingAs($this->ordinaryAdmin, 'web')
            ->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSee('Booking Notice')
            ->assertDontSee('Backup Failure')
            ->assertDontSee('Worker Alert');

        // Super admin sees all notifications including system warnings and backup failures
        $this->actingAs($this->superAdmin, 'web')
            ->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSee('Booking Notice')
            ->assertSee('Backup Failure')
            ->assertSee('Worker Alert');

        $backupFailure = AdminNotification::where('type', 'backup_failure')->firstOrFail();
        $this->actingAs($this->ordinaryAdmin, 'web')
            ->post(route('admin.notifications.read', $backupFailure))
            ->assertForbidden();
        $this->assertNull($backupFailure->fresh()->read_at);

        $this->actingAs($this->ordinaryAdmin, 'web')
            ->delete(route('admin.notifications.destroy', $backupFailure))
            ->assertForbidden();
        $this->assertDatabaseHas('admin_notifications', ['id' => $backupFailure->id]);
    }

    public function test_resource_category_crud_and_in_use_deletion_guard(): void
    {
        // 1. Create Category
        $this->actingAs($this->superAdmin, 'web')
            ->post(route('admin.resource-categories.store'), [
                'name' => 'Colloquial Grammar',
                'slug' => 'colloquial-grammar',
                'sort_order' => 1,
                'active' => '1',
            ])
            ->assertRedirect(route('admin.resource-categories.index'));

        $category = ResourceCategory::where('slug', 'colloquial-grammar')->firstOrFail();
        $this->assertEquals('Colloquial Grammar', $category->name);
        $this->assertTrue($category->active);

        // 2. Update Category
        $this->actingAs($this->superAdmin, 'web')
            ->put(route('admin.resource-categories.update', $category), [
                'name' => 'Advanced Cairo Grammar',
                'slug' => 'cairo-grammar',
                'sort_order' => 2,
                'active' => '0',
            ])
            ->assertRedirect(route('admin.resource-categories.index'));

        $category->refresh();
        $this->assertEquals('Advanced Cairo Grammar', $category->name);
        $this->assertEquals('cairo-grammar', $category->slug);
        $this->assertFalse($category->active);

        // 3. Prevent deletion when resources are in use
        $resource = Resource::create([
            'category_id' => $category->id,
            'title' => 'Verb Conjugation Matrix',
            'slug' => 'verb-matrix',
            'description' => 'Detailed Egyptian verbs',
            'file_path' => 'resources/verbs.pdf',
            'file_name' => 'verbs.pdf',
            'file_size_bytes' => 1024,
            'mime_type' => 'application/pdf',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($this->superAdmin, 'web')
            ->delete(route('admin.resource-categories.destroy', $category))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('resource_categories', ['id' => $category->id]);

        // 4. Deletion succeeds once resources are removed/reassigned
        $resource->forceDelete();

        $this->actingAs($this->superAdmin, 'web')
            ->delete(route('admin.resource-categories.destroy', $category))
            ->assertRedirect(route('admin.resource-categories.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('resource_categories', ['id' => $category->id]);
    }

    public function test_game_lifecycle_create_update_status_and_delete(): void
    {
        // 1. Create Game
        $this->actingAs($this->superAdmin, 'web')
            ->post(route('admin.games.store'), [
                'title' => 'Egyptian Street Numbers',
                'slug' => 'egyptian-street-numbers',
                'description' => 'Learn market numbers in Cairo slang',
                'badge' => 'Math & Markets',
                'target_url' => 'https://games.boltlanding.test/numbers',
                'thumbnail_path' => 'media/numbers-thumb.webp',
                'status' => 'available',
                'featured' => '1',
                'sort_order' => 5,
            ])
            ->assertRedirect(route('admin.games.index'));

        $game = Game::where('slug', 'egyptian-street-numbers')->firstOrFail();
        $this->assertEquals('available', $game->status);
        $this->assertTrue($game->featured);

        // 2. Update Game
        $this->actingAs($this->superAdmin, 'web')
            ->put(route('admin.games.update', $game), [
                'title' => 'Egyptian Street Numbers Deluxe',
                'slug' => 'egyptian-street-numbers',
                'description' => 'Updated edition with voice clips',
                'badge' => 'Audio Challenge',
                'status' => 'coming_soon',
                'featured' => '0',
                'sort_order' => 10,
            ])
            ->assertRedirect(route('admin.games.index'));

        $game->refresh();
        $this->assertEquals('Egyptian Street Numbers Deluxe', $game->title);
        $this->assertEquals('coming_soon', $game->status);
        $this->assertFalse($game->featured);

        // 3. Delete Game
        $this->actingAs($this->superAdmin, 'web')
            ->delete(route('admin.games.destroy', $game))
            ->assertRedirect(route('admin.games.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('games', ['id' => $game->id]);
    }

    public function test_booking_calendar_day_view_boundary_and_cairo_timezone_rendering(): void
    {
        // Set up availability for Thursday (day 4) 10:00 to 18:00 Cairo time
        AvailabilityRule::create([
            'weekday' => 4, // Thursday
            'start_time' => '10:00:00',
            'end_time' => '18:00:00',
            'buffer_minutes' => 0,
            'enabled' => true,
        ]);

        // 2026-10-15 is Thursday in Africa/Cairo
        // 14:00 Cairo time = 11:00 UTC (Cairo is UTC+3 in October)
        $startUtc = CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC');
        $endUtc = CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC');

        $contact = Contact::create([
            'name' => 'Farida Student',
            'email' => 'farida@example.test',
        ]);

        $booking = $this->createBookingFixture($contact, $startUtc, $endUtc, 'America/New_York');

        // 1. Visit day view on 2026-10-15
        $this->superAdmin->update(['time_format' => '12']);
        $response = $this->actingAs($this->superAdmin, 'web')
            ->get(route('admin.bookings.index', ['view' => 'day', 'date' => '2026-10-15']));

        $response->assertOk();
        $response->assertSee('Farida Student');
        $response->assertSee('2:00 PM');
        $response->assertSee('America/New_York');
        $response->assertSee('date=2026-10-14'); // prev day link
        $response->assertSee('date=2026-10-16'); // next day link

        // 2. Visit adjacent day (2026-10-16 Friday)
        $adjacentResponse = $this->actingAs($this->superAdmin, 'web')
            ->get(route('admin.bookings.index', ['view' => 'day', 'date' => '2026-10-16']));

        $adjacentResponse->assertOk();
        $adjacentResponse->assertDontSee('Farida Student');
        $adjacentResponse->assertSee('No appointments scheduled for this day');
    }

    public function test_booking_calendar_week_view_boundary_and_cairo_timezone_rendering(): void
    {
        $startUtc = CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'); // Thursday 14:00 Cairo
        $endUtc = CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC');

        $contact = Contact::create([
            'name' => 'Tarek Student',
            'email' => 'tarek@example.test',
        ]);

        $this->createBookingFixture($contact, $startUtc, $endUtc, 'Europe/London');

        // Week containing 2026-10-15
        $response = $this->actingAs($this->superAdmin, 'web')
            ->get(route('admin.bookings.index', ['view' => 'week', 'date' => '2026-10-15']));

        $response->assertOk();
        $response->assertSee('Tarek Student');
        $response->assertSee('14:00');
        $response->assertSee('Thu');
        $response->assertSee('1 session');
        $response->assertSee('date=2026-10-08'); // prev week
        $response->assertSee('date=2026-10-22'); // next week
    }

    public function test_booking_cancel_and_resource_request_events_trigger_notifications(): void
    {
        // 1. Cancellation triggers notification
        $startUtc = CarbonImmutable::parse('2026-11-10 10:00:00', 'UTC');
        $endUtc = CarbonImmutable::parse('2026-11-10 11:00:00', 'UTC');

        $contact = Contact::create([
            'name' => 'Layla Cancel',
            'email' => 'layla@example.test',
        ]);

        $booking = $this->createBookingFixture($contact, $startUtc, $endUtc, 'UTC');

        app(CancellationService::class)->cancel(
            booking: $booking,
            performedBy: 'admin',
            performedById: $this->superAdmin->id,
            reason: 'Emergency conflict'
        );

        $this->assertDatabaseHas('admin_notifications', [
            'type' => 'booking_cancelled',
            'title' => "Booking Cancelled #{$booking->id}",
        ]);

        // 2. Resource Request triggers notification
        $category = ResourceCategory::create(['name' => 'Audios', 'slug' => 'audios']);
        $resource = Resource::create([
            'category_id' => $category->id,
            'title' => 'Cairo Street Slang Audio',
            'slug' => 'slang-audio',
            'description' => 'Audio guide',
            'file_path' => 'resources/slang.mp3',
            'file_name' => 'slang.mp3',
            'file_size_bytes' => 2048,
            'mime_type' => 'audio/mpeg',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $resourcePage = $this->get(route('resources.show', $resource->slug));
        $visitorCookie = $resourcePage->getCookie('_va_visitor');
        $sessionCookie = $resourcePage->getCookie('_va_session');
        $this->assertNotNull($visitorCookie);
        $this->assertNotNull($sessionCookie);

        $this->withCredentials()->withCookies([
            '_va_visitor' => $visitorCookie->getValue(),
            '_va_session' => $sessionCookie->getValue(),
        ])->post(route('resources.request', $resource->slug), [
            'name' => 'Omar Requester',
            'email' => 'omar@test.org',
        ])->assertRedirect();

        $this->assertDatabaseHas('admin_notifications', [
            'type' => 'resource_requested',
            'title' => 'New Resource Lead',
        ]);
    }

    protected function createBookingFixture(Contact $contact, CarbonImmutable $startUtc, CarbonImmutable $endUtc, string $customerTimezone = 'America/New_York'): Booking
    {
        $snapshot = app(TimezoneService::class)->createBookingSnapshot(
            startUtc: $startUtc,
            endUtc: $endUtc,
            customerTimezone: $customerTimezone,
            businessTimezone: 'Africa/Cairo'
        );

        return Booking::create(array_merge($snapshot, [
            'contact_id' => $contact->id,
            'session_type_id' => $this->sessionType->id,
            'status' => 'confirmed',
            'idempotency_key' => 'idem_'.Str::random(16),
            'confirmation_token' => 'conf_'.Str::random(32),
        ]));
    }
}
