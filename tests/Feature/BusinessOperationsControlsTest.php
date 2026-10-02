<?php

namespace Tests\Feature;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\MeetingProvider;
use App\Domains\Booking\Models\MeetingRoom;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\MeetingLinkService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Students\Models\PaymentMethod;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BusinessOperationsControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_payment_method_rename_and_disable_preserve_payment_facts(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $this->actingAs($admin, 'web')->post(route('admin.payment-methods.store'), ['name' => 'Revolut', 'sort_order' => 0])->assertRedirect();
        $method = PaymentMethod::where('name', 'Revolut')->firstOrFail();
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'QA package', 3, '30.00', '0.00', 'USD', null, 'method-qa');
        $payment = $ledger->recordPayment($package, '30.00', (string) Str::uuid(), $admin->id, paymentMethodId: $method->id);
        $this->actingAs($admin, 'web')->put(route('admin.payment-methods.update', $method), ['name' => 'Revolut transfer', 'active' => 0, 'is_default' => 0, 'sort_order' => 10])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Revolut', $payment->fresh()->payment_method);
        $this->assertSame($method->id, $payment->fresh()->payment_method_id);
        $this->post(route('admin.students.payments.store', [$student->id, $package->id]), ['amount_paid' => '1.00', 'payment_method' => (string) $method->id, 'payment_idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('payment_records', 1);
        $this->put(route('admin.payment-methods.update', $method), ['name' => 'Revolut transfer', 'active' => 1, 'is_default' => 1, 'sort_order' => 0])->assertSessionHasNoErrors();
        $this->assertTrue($method->fresh()->is_default);
    }

    public function test_courtesy_and_validity_extension_preserve_the_append_only_ledger(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'QA validity', 3, '30.00', '0.00', 'USD', CarbonImmutable::now()->addDays(10)->toDateString(), 'validity-qa');
        $ledger->adjustCredits($package, 2, 'Service recovery', (string) Str::uuid(), $admin->id);
        $before = $package->ledgerEntries()->get()->toArray();
        $this->actingAs($admin, 'web')->post(route('admin.students.packages.validity', [$student->id, $package->id]), ['previous_expiration_date' => $package->expiration_date->toDateString(), 'expiration_date' => $package->expiration_date->copy()->addDays(30)->toDateString(), 'reason' => 'Travel accommodation'])->assertSessionHasNoErrors();
        $this->assertSame($before, $package->ledgerEntries()->get()->toArray());
        $this->assertSame(2, $ledger->summary($package->fresh())['courtesy_credits']);
        $this->assertSame(5, $ledger->summary($package->fresh())['remaining_credits']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'package_validity_extended', 'entity_id' => $package->id]);
        $change = AuditLog::where('action', 'package_validity_extended')->firstOrFail();
        $this->assertSame($package->expiration_date->toDateString(), $change->previous_data['expiration_date']);
        $this->assertSame($package->expiration_date->copy()->addDays(30)->toDateString(), $change->new_data['expiration_date']);
        $this->get(route('admin.billing.cashier', ['student_id' => $student->id]))->assertOk()->assertSee('Service recovery');
    }

    public function test_suspension_terminates_an_existing_student_session_and_restore_allows_relogin(): void
    {
        $owner = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $student = Student::factory()->verified()->create();
        $this->actingAs($owner, 'web')->post(route('admin.accounts.suspension', ['student', $student->id]), ['suspend' => 1, 'reason' => 'QA hold'])->assertSessionHasNoErrors();
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()])->get(route('student.dashboard'))->assertRedirect(route('student.login'));
        $this->assertGuest('student');
        $this->actingAs($owner, 'web')->post(route('admin.accounts.suspension', ['student', $student->id]), ['suspend' => 0, 'reason' => 'QA restore'])->assertSessionHasNoErrors();
        $this->post(route('student.login.submit'), ['date_of_birth' => $student->date_of_birth->toDateString(), 'name' => $student->name, 'email' => $student->email])->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($student, 'student');
    }

    public function test_only_super_admin_can_suspend_and_self_suspension_is_rejected(): void
    {
        $owner = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $this->actingAs($admin, 'web')->post(route('admin.accounts.suspension', ['administrator', $owner->id]), ['suspend' => 1, 'reason' => 'QA'])->assertForbidden();
        $this->actingAs($owner, 'web')->post(route('admin.accounts.suspension', ['administrator', $owner->id]), ['suspend' => 1, 'reason' => 'QA'])->assertSessionHasErrors('suspend');
        $this->assertNull($owner->fresh()->suspended_at);
    }

    public function test_suspended_administrator_cannot_reuse_an_existing_session_or_log_in(): void
    {
        $owner = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $admin = AdministratorFactory::new()->create(['role' => 'admin', 'password' => bcrypt('Password123!')]);
        $this->actingAs($owner, 'web')->post(route('admin.accounts.suspension', ['administrator', $admin->id]), ['suspend' => 1, 'reason' => 'QA'])->assertSessionHasNoErrors();
        $this->actingAs($admin, 'web')->get(route('admin.bookings.index'))->assertRedirect(route('admin.login'));
        $this->assertGuest('web');
        $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'Password123!'])->assertSessionHasErrors('email');
    }

    public function test_room_overlap_rejected_adjacent_times_allowed_and_archival_retains_snapshots(): void
    {
        $provider = MeetingProvider::where('is_default', true)->firstOrFail();
        $room = MeetingRoom::create(['meeting_provider_id' => $provider->id, 'name' => 'QA room', 'url' => 'https://example.org/qa-room', 'url_hash' => hash('sha256', 'https://example.org/qa-room')]);
        $first = $this->booking('2026-11-02 10:00:00');
        $overlap = $this->booking('2026-11-02 10:30:00');
        $adjacent = $this->booking('2026-11-02 11:00:00');
        $links = app(MeetingLinkService::class);
        $links->assign($first, roomId: $room->id);
        try {
            $links->assign($overlap, roomId: $room->id);
            $this->fail('Overlapping room assignment must be rejected.');
        } catch (ValidationException) {
            $this->assertNull($overlap->fresh()->meeting_room_id);
        }
        $links->assign($adjacent, roomId: $room->id);
        $otherRoom = MeetingRoom::create(['meeting_provider_id' => $provider->id, 'name' => 'Alternate QA room', 'url' => 'https://example.org/alternate-qa-room', 'url_hash' => hash('sha256', 'https://example.org/alternate-qa-room')]);
        $links->assign($overlap, roomId: $otherRoom->id);
        $this->assertSame($otherRoom->id, $overlap->fresh()->meeting_room_id);
        $room->update(['url' => 'https://example.org/new-url', 'active' => false]);
        $provider->update(['active' => false]);
        $this->assertSame('https://example.org/qa-room', $first->fresh()->meeting_url_snapshot);
        $this->assertSame($room->id, $adjacent->fresh()->meeting_room_id);
    }

    public function test_student_link_reveal_uses_utc_lead_time_and_cancelled_links_are_hidden(): void
    {
        $provider = MeetingProvider::where('is_default', true)->firstOrFail();
        $room = MeetingRoom::create(['meeting_provider_id' => $provider->id, 'name' => 'Reveal room', 'url' => 'https://example.org/reveal', 'url_hash' => hash('sha256', 'https://example.org/reveal')]);
        $booking = $this->booking('2026-11-02 10:00:00');
        $links = app(MeetingLinkService::class);
        $links->assign($booking, roomId: $room->id);
        Setting::set('meeting.student_reveal_minutes', 15);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:44:59', 'UTC'));
        $this->assertNull($links->studentUrl($booking->fresh()));
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:45:00', 'UTC'));
        $this->assertSame('https://example.org/reveal', $links->studentUrl($booking->fresh()));
        $booking->update(['status' => 'cancelled']);
        $this->assertNull($links->studentUrl($booking->fresh()));
        $this->assertNull($links->notificationUrl($booking->fresh(), 10));
    }

    public function test_business_and_travelling_student_timezones_present_one_unchanged_utc_instant(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin', 'time_format' => '24']);
        $student = Student::factory()->verified()->create(['preferred_timezone' => 'America/New_York']);
        Setting::set('business_timezone', 'Europe/Berlin');
        $booking = $this->booking('2026-11-02 10:00:00');
        $booking->update(['student_id' => $student->id, 'customer_timezone' => 'America/New_York']);
        $utc = $booking->start_at_utc->toIso8601String();
        $this->actingAs($admin, 'web')->get(route('admin.bookings.show', $booking))->assertOk()->assertSee('11:00');
        $this->get(route('admin.bookings.index'))->assertOk()->assertSee('05:00 (America/New_York)');
        $this->travelTo(CarbonImmutable::parse('2026-11-01', 'UTC'));
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()]);
        $this->get(route('student.dashboard', ['timezone' => 'America/New_York']))->assertOk()->assertSee('5:00 AM');
        $this->get(route('student.dashboard', ['timezone' => 'Europe/London']))->assertOk()->assertSee('10:00 AM');
        Setting::set('business_timezone', 'Asia/Dubai');
        $this->actingAs($admin, 'web')->get(route('admin.bookings.show', $booking))->assertOk()->assertSee('14:00');
        $this->assertSame($utc, $booking->fresh()->start_at_utc->toIso8601String());
    }

    public function test_assistants_and_students_cannot_manage_payment_methods_meeting_pools_or_validity(): void
    {
        $assistant = AdministratorFactory::new()->create(['role' => 'assistant']);
        foreach (['admin.payment-methods.index', 'admin.meeting-links.index'] as $route) {
            $this->actingAs($assistant, 'web')->get(route($route))->assertForbidden();
        }
        $this->app['auth']->guard('web')->logout();
        $student = Student::factory()->verified()->create();
        $this->actingAs($student, 'student')->get(route('admin.meeting-links.index'))->assertRedirect(route('admin.login'));
    }

    private function booking(string $start): Booking
    {
        $contact = Contact::firstOrCreate(['email' => 'room-qa@example.org'], ['name' => 'Room QA']);
        $type = SessionType::firstOrCreate(['slug' => 'room-qa'], ['title' => 'Room QA', 'duration_minutes' => 60, 'price' => '25.00', 'currency' => 'USD', 'active' => true]);
        $instant = CarbonImmutable::parse($start, 'UTC');

        return Booking::create(['contact_id' => $contact->id, 'session_type_id' => $type->id, 'start_at_utc' => $instant, 'end_at_utc' => $instant->addHour(), 'status' => 'confirmed', 'confirmation_token' => Str::random(64), 'idempotency_key' => Str::random(48)] + app(TimezoneService::class)->createBookingSnapshot($instant, $instant->addHour(), 'America/New_York', 'Europe/Berlin'));
    }

    public function test_brownfield_meeting_import_preserves_the_existing_provider_and_skips_room_collisions(): void
    {
        Setting::set('video_meeting_url', 'https://meet.google.com/qa-legacy-room');
        $first = $this->booking('2027-02-02 10:00:00');
        $overlap = $this->booking('2027-02-02 10:30:00');
        $adjacent = $this->booking('2027-02-02 11:00:00');
        $migration = require database_path('migrations/2026_10_02_102325_add_meeting_room_assignments.php');
        $migration->importLegacyMeetingRoom();
        $provider = MeetingProvider::where('is_default', true)->sole();
        $this->assertSame('Google Meet', $provider->name);
        $this->assertSame('https://meet.google.com/qa-legacy-room', $first->fresh()->meeting_url_snapshot);
        $this->assertNull($overlap->fresh()->meeting_room_id);
        $this->assertSame($first->fresh()->meeting_room_id, $adjacent->fresh()->meeting_room_id);
    }
}
