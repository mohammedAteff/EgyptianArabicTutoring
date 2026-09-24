<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentIdentityService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class StudentBackfillAndIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_identity_normalization_preserves_unicode_and_requires_phone_country_context(): void
    {
        $identity = app(StudentIdentityService::class);

        $this->assertSame('ali محمد', $identity->normalizeName('  Ａli،  محمد  '));
        $this->assertSame('test.name+tag@example.com', $identity->normalizeEmail(' Test.Name+Tag@Example.COM '));
        $this->assertSame('+201012345678', $identity->normalizePhone('01012345678', 'EG'));

        $this->expectException(InvalidArgumentException::class);
        $identity->normalizePhone('01012345678');
    }

    public function test_backfill_defaults_to_dry_run_then_applies_once_without_changing_utc_booking_time(): void
    {
        $booking = $this->createBooking('backfill@example.com', '+201012345678');
        $originalStart = $booking->start_at_utc->toDateTimeString();

        $this->assertSame(0, Artisan::call('migrate:students-backfill'));
        $this->assertSame(0, Student::count());
        $this->assertNull($booking->fresh()->student_id);

        $this->assertSame(0, Artisan::call('migrate:students-backfill', ['--apply' => true]));
        $student = Student::query()->firstOrFail();
        $this->assertSame($student->id, $booking->fresh()->student_id);
        $this->assertSame('legacy_unverified', $student->identity_status);
        $this->assertNull($student->date_of_birth);
        $this->assertSame($originalStart, $booking->fresh()->start_at_utc->toDateTimeString());

        $this->assertSame(0, Artisan::call('migrate:students-backfill', ['--apply' => true]));
        $this->assertSame(1, Student::count());
    }

    public function test_ambiguous_national_phone_remains_unlinked_for_manual_triage(): void
    {
        $booking = $this->createBooking('ambiguous@example.com', '01012345678');

        $this->assertSame(0, Artisan::call('migrate:students-backfill', ['--apply' => true]));

        $this->assertNull($booking->fresh()->student_id);
        $this->assertDatabaseHas('migration_booking_exceptions', [
            'booking_id' => $booking->id,
            'reason' => 'ambiguous_national_phone',
        ]);
        $this->assertSame(0, Student::count());
    }

    public function test_bookings_student_link_foreign_key_rejects_orphan_student_ids(): void
    {
        $this->assertTrue(DB::table('information_schema.key_column_usage')
            ->whereRaw('CONSTRAINT_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'bookings')
            ->where('COLUMN_NAME', 'student_id')
            ->where('REFERENCED_TABLE_NAME', 'students')
            ->where('REFERENCED_COLUMN_NAME', 'id')
            ->exists());

        $contact = Contact::query()->create(['name' => 'Foreign Key Test', 'email' => 'booking-fk@example.test']);
        $sessionType = SessionType::query()->create([
            'title' => 'Foreign key test session',
            'slug' => 'booking-fk-'.Str::random(8),
            'duration_minutes' => 60,
            'price' => 25,
            'currency' => 'USD',
            'active' => true,
        ]);
        $start = CarbonImmutable::now('UTC')->addDays(5)->setTime(10, 0);
        $snapshot = app(TimezoneService::class)->createBookingSnapshot($start, $start->addHour(), 'UTC', 'Africa/Cairo');
        $missingStudentId = (int) (Student::withTrashed()->max('id') ?? 0) + 100000;

        $this->expectException(QueryException::class);
        Booking::query()->create(array_merge($snapshot, [
            'contact_id' => $contact->id,
            'student_id' => $missingStudentId,
            'session_type_id' => $sessionType->id,
            'status' => 'confirmed',
            'idempotency_key' => 'booking-fk-'.Str::uuid(),
            'confirmation_token' => Str::random(64),
        ]));
    }

    public function test_contact_matching_existing_verified_email_gets_legacy_profile_with_review_pointer(): void
    {
        $existing = Student::factory()->verified()->create([
            'email' => 'existing@example.com',
            'email_normalized' => 'existing@example.com',
        ]);
        $booking = $this->createBooking('existing@example.com', '+201012345678');

        $this->assertSame(0, Artisan::call('migrate:students-backfill', ['--apply' => true]));

        $linked = $booking->fresh()->student;
        $this->assertNotNull($linked);
        $this->assertSame('legacy_unverified', $linked->identity_status);
        $this->assertSame($existing->id, $linked->possible_duplicate_of_student_id);
        $this->assertDatabaseHas('migration_booking_exceptions', ['booking_id' => $booking->id, 'reason' => 'possible_duplicate']);
    }

    public function test_backfill_links_an_exact_canonical_student_match_without_creating_a_duplicate(): void
    {
        $existing = Student::factory()->verified()->create([
            'first_name' => 'Legacy',
            'last_name' => 'Student',
            'name_normalized' => 'legacy student',
            'email' => 'canonical@example.com',
            'email_normalized' => 'canonical@example.com',
            'phone' => '+201012345678',
            'phone_normalized' => '+201012345678',
        ]);
        $booking = $this->createBooking('canonical@example.com', '+201012345678');

        $this->assertSame(0, Artisan::call('migrate:students-backfill', ['--apply' => true]));

        $this->assertSame($existing->id, $booking->fresh()->student_id);
        $this->assertSame(1, Student::count());
        $this->assertDatabaseMissing('migration_booking_exceptions', ['booking_id' => $booking->id]);
    }

    private function createBooking(string $email, ?string $phone): Booking
    {
        $contact = Contact::create(['name' => 'Legacy Student', 'email' => $email, 'phone' => $phone]);
        $sessionType = SessionType::create([
            'title' => 'Legacy lesson',
            'slug' => 'legacy-lesson-'.Str::random(8),
            'duration_minutes' => 60,
            'price' => 30,
            'currency' => 'USD',
            'active' => true,
        ]);
        $start = CarbonImmutable::parse('2026-10-15 10:00:00', 'UTC');
        $snapshot = app(TimezoneService::class)->createBookingSnapshot($start, $start->addHour(), 'Africa/Cairo', 'Africa/Cairo');

        return Booking::create($snapshot + [
            'contact_id' => $contact->id,
            'session_type_id' => $sessionType->id,
            'status' => 'confirmed',
            'idempotency_key' => (string) Str::uuid(),
            'confirmation_token' => bin2hex(random_bytes(32)),
        ]);
    }
}
