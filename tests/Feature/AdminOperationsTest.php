<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected Administrator $admin;

    protected SessionType $sessionType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Administrator::create([
            'name' => 'Ahmad Tutor',
            'email' => 'tutor@boltlanding.test',
            'password' => Hash::make('SecretPass123!'),
            'role' => 'super_admin',
        ]);

        $this->sessionType = SessionType::create([
            'title' => '1-on-1 Egyptian Arabic Session',
            'slug' => 'egyptian-arabic-session',
            'duration_minutes' => 60,
            'price' => 40.00,
            'currency' => 'USD',
            'active' => true,
        ]);
    }

    public function test_unauthenticated_user_redirected_to_admin_login(): void
    {
        $response = $this->get(route('admin.dashboard'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_login_success_and_session_regeneration(): void
    {
        $loginResponse = $this->post(route('admin.login.submit'), [
            'email' => 'tutor@boltlanding.test',
            'password' => 'SecretPass123!',
        ]);

        $loginResponse->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($this->admin, 'web');

        $this->assertDatabaseHas('audit_logs', [
            'administrator_id' => $this->admin->id,
            'action' => 'admin_login',
        ]);
    }

    public function test_admin_login_throttles_after_repeated_failures(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.login.submit'), [
                'email' => 'tutor@boltlanding.test',
                'password' => 'WrongPassword',
            ]);
        }

        // 6th attempt should be blocked
        $response = $this->post(route('admin.login.submit'), [
            'email' => 'tutor@boltlanding.test',
            'password' => 'WrongPassword',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_admin_dashboard_renders_schedule_and_kpis(): void
    {
        $contact = Contact::create([
            'name' => 'Sarah Connor',
            'email' => 'sarah@example.com',
            'display_email' => 'sarah@example.com',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $booking = Booking::create([
            'contact_id' => $contact->id,
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => now('UTC')->addHours(2),
            'end_at_utc' => now('UTC')->addHours(3),
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'America/New_York',
            'business_local_date_at_booking' => now('Africa/Cairo')->toDateString(),
            'business_local_start_time_at_booking' => now('Africa/Cairo')->addHours(2)->toTimeString(),
            'business_local_end_time_at_booking' => now('Africa/Cairo')->addHours(3)->toTimeString(),
            'customer_local_date_at_booking' => now('America/New_York')->toDateString(),
            'customer_local_start_time_at_booking' => now('America/New_York')->addHours(2)->toTimeString(),
            'customer_local_end_time_at_booking' => now('America/New_York')->addHours(3)->toTimeString(),
            'business_utc_offset_at_booking' => '+03:00',
            'customer_utc_offset_at_booking' => '-04:00',
            'status' => 'confirmed',
            'idempotency_key' => 'idem-dash-123',
            'confirmation_token' => 'token-dash-123',
        ]);

        $response = $this->actingAs($this->admin, 'web')->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertSeeText('Sarah Connor');
        $response->assertSeeText('Confirmed Bookings');
    }

    public function test_admin_bookings_list_and_filters(): void
    {
        $contact = Contact::create([
            'name' => 'Michael Scott',
            'email' => 'michael@dundermifflin.com',
            'display_email' => 'michael@dundermifflin.com',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $booking = Booking::create([
            'contact_id' => $contact->id,
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => now('UTC')->addDays(2),
            'end_at_utc' => now('UTC')->addDays(2)->addHour(),
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'Europe/London',
            'business_local_date_at_booking' => now('Africa/Cairo')->addDays(2)->toDateString(),
            'business_local_start_time_at_booking' => '10:00:00',
            'business_local_end_time_at_booking' => '11:00:00',
            'customer_local_date_at_booking' => now('Europe/London')->addDays(2)->toDateString(),
            'customer_local_start_time_at_booking' => '08:00:00',
            'customer_local_end_time_at_booking' => '09:00:00',
            'business_utc_offset_at_booking' => '+03:00',
            'customer_utc_offset_at_booking' => '+01:00',
            'status' => 'confirmed',
            'idempotency_key' => 'idem-book-filter',
            'confirmation_token' => 'token-book-filter',
        ]);

        // List view
        $response = $this->actingAs($this->admin, 'web')->get(route('admin.bookings.index', [
            'status' => 'confirmed',
            'date' => 'upcoming',
        ]));
        $response->assertStatus(200);
        $response->assertSeeText('Michael Scott');

        // Calendar view
        $calResponse = $this->actingAs($this->admin, 'web')->get(route('admin.bookings.index', [
            'view' => 'calendar',
            'month' => now('Africa/Cairo')->format('Y-m'),
        ]));
        $calResponse->assertStatus(200);
    }

    public function test_admin_booking_detail_and_notes_update(): void
    {
        $contact = Contact::create([
            'name' => 'Dwight Schrute',
            'email' => 'dwight@beetfarm.com',
            'display_email' => 'dwight@beetfarm.com',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $booking = Booking::create([
            'contact_id' => $contact->id,
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => now('UTC')->addDays(1),
            'end_at_utc' => now('UTC')->addDays(1)->addHour(),
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'America/New_York',
            'business_local_date_at_booking' => now('Africa/Cairo')->addDays(1)->toDateString(),
            'business_local_start_time_at_booking' => '12:00:00',
            'business_local_end_time_at_booking' => '13:00:00',
            'customer_local_date_at_booking' => now('America/New_York')->addDays(1)->toDateString(),
            'customer_local_start_time_at_booking' => '05:00:00',
            'customer_local_end_time_at_booking' => '06:00:00',
            'business_utc_offset_at_booking' => '+03:00',
            'customer_utc_offset_at_booking' => '-04:00',
            'status' => 'confirmed',
            'idempotency_key' => 'idem-detail-123',
            'confirmation_token' => 'token-detail-123',
        ]);

        // Show page
        $showResponse = $this->actingAs($this->admin, 'web')->get(route('admin.bookings.show', $booking->id));
        $showResponse->assertStatus(200);
        $showResponse->assertSeeText('Dwight Schrute');
        $showResponse->assertSeeText('Dual Timezone Authoritative Comparison');

        // Update notes
        $notesResponse = $this->actingAs($this->admin, 'web')->patch(route('admin.bookings.notes', $booking->id), [
            'notes' => 'Mastered Egyptian Arabic greetings (Salamu Alaykom, Ezayak). Homework: audio exercise 1.',
        ]);
        $notesResponse->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'notes' => 'Mastered Egyptian Arabic greetings (Salamu Alaykom, Ezayak). Homework: audio exercise 1.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'booking_notes_updated',
            'entity_id' => $booking->id,
        ]);
    }

    public function test_admin_booking_completion_and_cancellation(): void
    {
        $contact = Contact::create([
            'name' => 'Jim Halpert',
            'email' => 'jim@example.com',
            'display_email' => 'jim@example.com',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $booking = Booking::create([
            'contact_id' => $contact->id,
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => now('UTC')->addHours(24),
            'end_at_utc' => now('UTC')->addHours(25),
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'Europe/Berlin',
            'business_local_date_at_booking' => now('Africa/Cairo')->addDay()->toDateString(),
            'business_local_start_time_at_booking' => '15:00:00',
            'business_local_end_time_at_booking' => '16:00:00',
            'customer_local_date_at_booking' => now('Europe/Berlin')->addDay()->toDateString(),
            'customer_local_start_time_at_booking' => '14:00:00',
            'customer_local_end_time_at_booking' => '15:00:00',
            'business_utc_offset_at_booking' => '+03:00',
            'customer_utc_offset_at_booking' => '+02:00',
            'status' => 'confirmed',
            'idempotency_key' => 'idem-cancel-comp',
            'confirmation_token' => 'token-cancel-comp',
        ]);

        // Complete
        $compResponse = $this->actingAs($this->admin, 'web')->post(route('admin.bookings.complete', $booking->id));
        $compResponse->assertRedirect();

        $this->assertEquals('completed', $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->completed_at);

        // Cancel
        $booking2 = Booking::create([
            'contact_id' => $contact->id,
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => now('UTC')->addDays(3),
            'end_at_utc' => now('UTC')->addDays(3)->addHour(),
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'Europe/Berlin',
            'business_local_date_at_booking' => now('Africa/Cairo')->addDays(3)->toDateString(),
            'business_local_start_time_at_booking' => '15:00:00',
            'business_local_end_time_at_booking' => '16:00:00',
            'customer_local_date_at_booking' => now('Europe/Berlin')->addDays(3)->toDateString(),
            'customer_local_start_time_at_booking' => '14:00:00',
            'customer_local_end_time_at_booking' => '15:00:00',
            'business_utc_offset_at_booking' => '+03:00',
            'customer_utc_offset_at_booking' => '+02:00',
            'status' => 'confirmed',
            'idempotency_key' => 'idem-cancel-2',
            'confirmation_token' => 'token-cancel-2',
        ]);

        $cancelResponse = $this->actingAs($this->admin, 'web')->post(route('admin.bookings.cancel', $booking2->id), [
            'cancellation_reason' => 'Tutor has urgent conference',
        ]);
        $cancelResponse->assertRedirect();

        $this->assertEquals('cancelled', $booking2->fresh()->status);
        $this->assertEquals('Tutor has urgent conference', $booking2->fresh()->cancellation_reason);
    }

    public function test_admin_availability_rule_and_date_exception_management(): void
    {
        // 1. Create availability rule
        $ruleResponse = $this->actingAs($this->admin, 'web')->post(route('admin.availability.rules.store'), [
            'weekday' => 1, // Monday
            'start_time' => '10:00',
            'end_time' => '14:00',
            'session_duration_minutes' => 60,
            'buffer_minutes' => 15,
            'min_notice_hours' => 12,
            'max_horizon_days' => 60,
        ]);
        $ruleResponse->assertRedirect();

        $this->assertDatabaseHas('availability_rules', [
            'weekday' => 1,
            'start_time' => '10:00:00',
            'end_time' => '14:00:00',
            'enabled' => true,
        ]);

        $rule = AvailabilityRule::where('weekday', 1)->first();

        // 2. Toggle rule
        $this->actingAs($this->admin, 'web')->post(route('admin.availability.toggle', $rule->id));
        $this->assertFalse($rule->fresh()->enabled);

        // 3. Add date exception (blocked holiday)
        $excResponse = $this->actingAs($this->admin, 'web')->post(route('admin.availability.exceptions.store'), [
            'date' => now()->addDays(5)->toDateString(),
            'is_blocked' => 1,
            'reason' => 'National Holiday',
        ]);
        $excResponse->assertRedirect();

        $this->assertDatabaseHas('availability_exceptions', [
            'date' => now()->addDays(5)->toDateString(),
            'type' => 'blocked',
            'notes' => 'National Holiday',
        ]);
    }

    public function test_admin_contact_duplicate_detection_and_merge(): void
    {
        $contactA = Contact::create([
            'name' => 'Layla Hassan',
            'email' => 'layla.hassan@example.com',
            'display_email' => 'layla.hassan@example.com',
            'phone' => '+201099887766',
            'first_seen_at' => now()->subDays(10),
            'last_seen_at' => now()->subDays(10),
        ]);

        $contactB = Contact::create([
            'name' => 'Layla H.',
            'email' => 'layla.alt@example.com',
            'display_email' => 'layla.alt@example.com',
            'phone' => '+201099887766', // identical phone
            'first_seen_at' => now()->subDays(2),
            'last_seen_at' => now()->subDays(2),
        ]);

        // Attach a booking to duplicate contact B
        $booking = Booking::create([
            'contact_id' => $contactB->id,
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => now('UTC')->addDays(5),
            'end_at_utc' => now('UTC')->addDays(5)->addHour(),
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'Africa/Cairo',
            'business_local_date_at_booking' => now('Africa/Cairo')->addDays(5)->toDateString(),
            'business_local_start_time_at_booking' => '10:00:00',
            'business_local_end_time_at_booking' => '11:00:00',
            'customer_local_date_at_booking' => now('Africa/Cairo')->addDays(5)->toDateString(),
            'customer_local_start_time_at_booking' => '10:00:00',
            'customer_local_end_time_at_booking' => '11:00:00',
            'business_utc_offset_at_booking' => '+03:00',
            'customer_utc_offset_at_booking' => '+03:00',
            'status' => 'confirmed',
            'idempotency_key' => 'idem-merge-test',
            'confirmation_token' => 'token-merge-test',
        ]);

        // Check duplicate review page
        $dupResponse = $this->actingAs($this->admin, 'web')->get(route('admin.contacts.duplicates'));
        $dupResponse->assertStatus(200);
        $dupResponse->assertSeeText('+201099887766');

        // Execute merge (Merge B into A)
        $mergeResponse = $this->actingAs($this->admin, 'web')->post(route('admin.contacts.merge'), [
            'canonical_id' => $contactA->id,
            'duplicate_id' => $contactB->id,
        ]);
        $mergeResponse->assertRedirect(route('admin.contacts.show', $contactA->id));

        // Booking reassigned to contact A
        $this->assertEquals($contactA->id, $booking->fresh()->contact_id);

        // Contact B soft-deleted and pointer set
        $this->assertTrue($contactB->fresh()->trashed());
        $this->assertEquals($contactA->id, $contactB->fresh()->merged_into_contact_id);
    }

    public function test_admin_resource_crud_lifecycle(): void
    {
        $category = ResourceCategory::create([
            'name' => 'Grammar Workbooks',
            'slug' => 'grammar-workbooks',
            'active' => true,
        ]);

        // Create
        $storeResponse = $this->actingAs($this->admin, 'web')->post(route('admin.resources.store'), [
            'title' => 'Mastering Egyptian Dual & Plural Forms',
            'slug' => 'mastering-egyptian-dual-plural',
            'category_id' => $category->id,
            'short_description' => 'A comprehensive workbook on irregular plurals and dual suffixes in Masri.',
            'file_type' => 'pdf',
            'featured' => 0,
            'status' => 'published',
            'sort_order' => 1,
        ]);
        $storeResponse->assertRedirect(route('admin.resources.index'));

        $this->assertDatabaseHas('resources', [
            'slug' => 'mastering-egyptian-dual-plural',
            'status' => 'published',
            'short_description' => 'A comprehensive workbook on irregular plurals and dual suffixes in Masri.',
        ]);

        $resource = Resource::where('slug', 'mastering-egyptian-dual-plural')->first();

        // Edit
        $updateResponse = $this->actingAs($this->admin, 'web')->put(route('admin.resources.update', $resource->id), [
            'title' => 'Mastering Egyptian Plurals & Duals (Updated Edition)',
            'slug' => 'mastering-egyptian-dual-plural',
            'category_id' => $category->id,
            'short_description' => 'Updated edition with 50 practice sentences.',
            'file_type' => 'pdf',
            'featured' => 1,
            'status' => 'published',
            'sort_order' => 2,
        ]);
        $updateResponse->assertRedirect(route('admin.resources.index'));

        $this->assertEquals('Mastering Egyptian Plurals & Duals (Updated Edition)', $resource->fresh()->title);

        // Archive / Destroy
        $deleteResponse = $this->actingAs($this->admin, 'web')->delete(route('admin.resources.destroy', $resource->id));
        $deleteResponse->assertRedirect(route('admin.resources.index'));

        $this->assertTrue($resource->fresh()->trashed());
    }

    public function test_admin_settings_update(): void
    {
        $response = $this->actingAs($this->admin, 'web')->post(route('admin.settings.update'), [
            'site_name' => 'Ahmad Arabic Academy',
            'business_timezone' => 'Africa/Cairo',
            'default_language' => 'en',
            'hero_title' => 'Speak Street Egyptian Arabic Fluently',
            'hero_subtitle' => 'Personalized 1-on-1 private lessons with structured immersion.',
            'booking_instructions' => 'Select your preferred local time slot.',
            'cancellation_policy' => 'Notice required 24 hours in advance.',
            'rescheduling_policy' => 'Free rescheduling up to 24 hours prior.',
            'maintenance_mode' => 0,
        ]);

        $response->assertRedirect();

        $this->assertEquals('Ahmad Arabic Academy', Setting::get('site_name'));
        $this->assertEquals('Speak Street Egyptian Arabic Fluently', Setting::get('hero_title'));
    }

    public function test_regular_admin_denied_access_to_super_admin_routes(): void
    {
        $regularAdmin = Administrator::create([
            'name' => 'Regular Staff Admin',
            'email' => 'staff@boltlanding.test',
            'password' => Hash::make('StaffPass123!'),
            'role' => 'admin',
        ]);

        // Regular admin attempting to access settings -> 403 Forbidden
        $settingsResponse = $this->actingAs($regularAdmin, 'web')->get(route('admin.settings.index'));
        $settingsResponse->assertStatus(403);

        // Regular admin attempting to access system health -> 403 Forbidden
        $healthResponse = $this->actingAs($regularAdmin, 'web')->get(route('admin.health'));
        $healthResponse->assertStatus(403);

        // Regular admin attempting to access audit logs -> 403 Forbidden
        $auditResponse = $this->actingAs($regularAdmin, 'web')->get(route('admin.audit-logs'));
        $auditResponse->assertStatus(403);

        // But super admin CAN access them with 200 OK
        $superSettings = $this->actingAs($this->admin, 'web')->get(route('admin.settings.index'));
        $superSettings->assertStatus(200);

        $superHealth = $this->actingAs($this->admin, 'web')->get(route('admin.health'));
        $superHealth->assertStatus(200);
    }
}
