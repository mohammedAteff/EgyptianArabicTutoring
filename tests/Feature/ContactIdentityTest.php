<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Services\ContactService;
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContactIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected ContactService $contactService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contactService = app(ContactService::class);
    }

    public function test_contact_email_is_normalized_to_lowercase_and_trimmed(): void
    {
        $contact = $this->contactService->resolveOrCreate(
            email: '   Ahmed.AlMasry+Arabic@Example.COM   ',
            name: 'Ahmed Al Masry',
            phone: '+20 100 123 4567'
        );

        $this->assertEquals('ahmed.almasry+arabic@example.com', $contact->email);
        $this->assertEquals('Ahmed.AlMasry+Arabic@Example.COM', $contact->display_email);
        $this->assertEquals('Ahmed Al Masry', $contact->name);
        $this->assertEquals('+20 100 123 4567', $contact->phone);

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'email' => 'ahmed.almasry+arabic@example.com',
            'display_email' => 'Ahmed.AlMasry+Arabic@Example.COM',
        ]);
    }

    public function test_resolve_or_create_returns_existing_contact_on_case_insensitive_match(): void
    {
        $first = $this->contactService->resolveOrCreate(
            email: 'Student.One@Example.com',
            name: 'Original Student',
            phone: '111-222'
        );

        $second = $this->contactService->resolveOrCreate(
            email: 'STUDENT.ONE@EXAMPLE.COM',
            name: 'Updated Name Should Not Overwrite',
            phone: '333-444'
        );

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals('Original Student', $second->name);
        $this->assertEquals('111-222', $second->phone);
        $this->assertEquals(1, Contact::query()->where('email', 'student.one@example.com')->count());
    }

    public function test_contact_merge_follows_pointer_to_canonical_record(): void
    {
        $canonical = $this->contactService->resolveOrCreate(
            email: 'primary@example.com',
            name: 'Primary Contact'
        );

        $duplicate = $this->contactService->resolveOrCreate(
            email: 'alt.email@example.com',
            name: 'Duplicate Contact',
            phone: '+20 123 999 888'
        );

        $admin = Administrator::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('secret'),
            'role' => 'super_admin',
        ]);

        // Perform merge
        $this->contactService->merge(
            canonical: $canonical,
            duplicate: $duplicate,
            adminId: $admin->id
        );

        // Verify duplicate is soft-deleted and points to canonical
        $duplicateFresh = Contact::withTrashed()->find($duplicate->id);
        $this->assertTrue($duplicateFresh->trashed());
        $this->assertEquals($canonical->id, $duplicateFresh->merged_into_contact_id);

        // Canonical inherited phone
        $canonicalFresh = $canonical->fresh();
        $this->assertEquals('+20 123 999 888', $canonicalFresh->phone);

        // Verify that looking up with duplicate email now resolves to canonical
        $resolved = $this->contactService->resolveOrCreate('alt.email@example.com');
        $this->assertEquals($canonical->id, $resolved->id);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'contact_merged',
            'entity_type' => Contact::class,
            'entity_id' => $canonical->id,
        ]);
    }

    public function test_soft_deleted_contact_is_restored_on_new_activity(): void
    {
        $contact = $this->contactService->resolveOrCreate(
            email: 'deleted.student@example.com',
            name: 'Deleted Student'
        );

        $contact->delete();
        $this->assertTrue($contact->fresh()->trashed());

        // New activity from this student should restore the record
        $restored = $this->contactService->resolveOrCreate(
            email: 'deleted.student@example.com',
            name: 'Deleted Student'
        );

        $this->assertEquals($contact->id, $restored->id);
        $this->assertFalse($restored->fresh()->trashed());
    }

    public function test_merge_preserves_notes_and_attribution_fields(): void
    {
        $canonical = $this->contactService->resolveOrCreate(
            email: 'master@example.com',
            name: 'Master Lead'
        );
        $canonical->update(['notes' => 'Existing canonical notes']);

        $duplicate = $this->contactService->resolveOrCreate(
            email: 'second@example.com',
            name: 'Second Lead',
            attribution: ['utm_source' => 'youtube', 'utm_campaign' => 'spring2026']
        );
        $duplicate->update(['notes' => 'Important details from phone call']);

        $this->contactService->merge($canonical, $duplicate);

        $canonicalFresh = $canonical->fresh();
        $this->assertStringContainsString('Existing canonical notes', $canonicalFresh->notes);
        $this->assertStringContainsString('Important details from phone call', $canonicalFresh->notes);
        $this->assertEquals('youtube', $canonicalFresh->utm_source);
        $this->assertEquals('spring2026', $canonicalFresh->utm_campaign);
    }

    public function test_contact_merge_locks_calendar_contacts_students_then_bookings(): void
    {
        $canonical = Contact::query()->create(['name' => 'Canonical', 'email' => 'lock-canonical@example.test']);
        $duplicate = Contact::query()->create(['name' => 'Duplicate', 'email' => 'lock-duplicate@example.test']);
        $student = Student::factory()->verified()->create();
        $sessionType = SessionType::query()->create([
            'title' => 'Lock-order session',
            'slug' => 'lock-order-session',
            'duration_minutes' => 60,
            'price' => '40.00',
            'currency' => 'USD',
            'active' => true,
        ]);
        $startUtc = CarbonImmutable::now('UTC')->addDays(12)->setTime(12, 0);
        $snapshot = app(TimezoneService::class)->createBookingSnapshot(
            $startUtc,
            $startUtc->addHour(),
            'Africa/Cairo',
            'Africa/Cairo',
        );
        $booking = Booking::query()->create(array_merge($snapshot, [
            'contact_id' => $duplicate->id,
            'student_id' => $student->id,
            'session_type_id' => $sessionType->id,
            'status' => 'confirmed',
            'idempotency_key' => 'contact-lock-order-'.fake()->uuid(),
            'confirmation_token' => fake()->sha256(),
        ]));
        $lockedTables = [];
        DB::listen(function (QueryExecuted $query) use (&$lockedTables): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'for update')) {
                foreach (['booking_calendar_locks', 'contacts', 'students', 'bookings'] as $table) {
                    if (str_contains($sql, $table)) {
                        $lockedTables[] = $table;
                        break;
                    }
                }
            }
        });

        $this->contactService->merge($canonical, $duplicate);

        $expectedOrder = ['booking_calendar_locks', 'contacts', 'students', 'bookings'];
        $this->assertSame($expectedOrder, array_values(array_unique($lockedTables)));
        $this->assertSame((int) $canonical->id, (int) $booking->fresh()->contact_id);
        $this->assertSame('lock-canonical@example.test', $canonical->fresh()->email);
    }
}
