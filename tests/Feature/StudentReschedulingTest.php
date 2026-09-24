<?php

namespace Tests\Feature;

use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Availability\Services\SlotResolver;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentReschedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_student_dashboard_lists_future_held_bookings_as_upcoming(): void
    {
        [$student, $booking] = $this->prepareBooking();
        $booking->update(['status' => 'held']);

        $response = $this->actingAs($student, 'student')
            ->withSession($this->studentSession($student))
            ->get(route('student.dashboard'));

        $response->assertOk()
            ->assertSeeText('Held')
            ->assertDontSeeText('No upcoming sessions.');
    }

    public function test_student_reschedule_uses_server_slot_preserves_status_and_is_idempotent(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 08:00:00 UTC');
        [$student, $booking, $sessionType] = $this->prepareBooking();
        $bookingStart = now('UTC')->addHours(25);
        $booking->update(['start_at_utc' => $bookingStart, 'end_at_utc' => $bookingStart->copy()->addMinutes(50)]);
        $token = Str::random(64);
        $date = CarbonImmutable::parse('2026-10-05', 'Africa/Cairo');
        $available = app(AvailabilityService::class)->getAvailableSlotsGroupedByDate($sessionType, 'Africa/Cairo', $date, $date, $token);
        $this->assertNotEmpty($available['2026-10-05'] ?? []);
        $slot = collect($available['2026-10-05'])->first(fn (array $candidate): bool => $candidate['slot_start_utc'] !== $booking->start_at_utc->toDateTimeString());
        $slotId = app(SlotResolver::class)->issue($sessionType, $slot, 'Africa/Cairo', $token);
        $key = Str::random(48);

        $this->actingAs($student, 'student')->withSession($this->studentSession($student) + ['student_reschedule_visitor_token' => $token]);
        $this->post(route('student.bookings.reschedule.submit', $booking->id), ['slot_id' => $slotId, 'idempotency_key' => $key])
            ->assertRedirect(route('student.dashboard'));

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
        $this->assertTrue($booking->admin_reconfirmation_needed);
        $this->assertSame($slot['slot_start_utc'], $booking->start_at_utc->toDateTimeString());
        $this->assertDatabaseHas('session_reschedules', ['booking_id' => $booking->id, 'actor_type' => 'student', 'idempotency_key' => $key]);

        $this->post(route('student.bookings.reschedule.submit', $booking->id), ['slot_id' => $slotId, 'idempotency_key' => $key])
            ->assertRedirect(route('student.dashboard'));
        $this->assertDatabaseCount('session_reschedules', 1);
        $this->assertDatabaseCount('session_ledger_entries', 0);
    }

    public function test_student_cannot_reschedule_another_students_booking_or_one_within_24_hours(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 08:00:00 UTC');
        [$student, $booking] = $this->prepareBooking();
        $other = Student::factory()->verified()->create();
        $this->actingAs($other, 'student')->withSession($this->studentSession($other))
            ->get(route('student.bookings.reschedule', $booking->id))->assertNotFound();

        $ownerToken = Str::random(64);
        $date = CarbonImmutable::parse('2026-10-05', 'Africa/Cairo');
        $available = app(AvailabilityService::class)->getAvailableSlotsGroupedByDate($booking->sessionType, 'Africa/Cairo', $date, $date, $ownerToken);
        $slot = $available['2026-10-05'][0];
        $slotId = app(SlotResolver::class)->issue($booking->sessionType, $slot, 'Africa/Cairo', $ownerToken);
        $this->actingAs($other, 'student')->withSession($this->studentSession($other) + ['student_reschedule_visitor_token' => $ownerToken])
            ->post(route('student.bookings.reschedule.submit', $booking->id), ['slot_id' => $slotId, 'idempotency_key' => Str::random(48)])
            ->assertNotFound();
        $this->assertSame('2026-10-05 06:00:00', $booking->fresh()->start_at_utc->toDateTimeString());
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertDatabaseCount('session_reschedules', 0);
        $this->assertDatabaseCount('session_ledger_entries', 0);

        $blockedStart = now('UTC')->addHours(23);
        $booking->update(['start_at_utc' => $blockedStart, 'end_at_utc' => $blockedStart->copy()->addMinutes(50)]);
        $this->actingAs($student, 'student')->withSession($this->studentSession($student))
            ->get(route('student.bookings.reschedule', $booking->id))->assertRedirect(route('student.dashboard'));
        $this->actingAs($student, 'student')->withSession($this->studentSession($student) + ['student_reschedule_visitor_token' => $ownerToken])
            ->post(route('student.bookings.reschedule.submit', $booking->id), ['slot_id' => $slotId, 'idempotency_key' => Str::random(48)])
            ->assertSessionHasErrors('slot_id');
        $this->assertSame($blockedStart->toDateTimeString(), $booking->fresh()->start_at_utc->toDateTimeString());
        $this->assertDatabaseCount('session_reschedules', 0);
    }

    public function test_student_reschedule_uses_fixed_utc_cutoff_across_cairo_dst_transitions(): void
    {
        $cases = [
            ['now' => '2026-04-23 21:30:00 UTC', 'hours' => 23, 'minutes' => 30, 'allowed' => false],
            ['now' => '2026-10-29 20:30:00 UTC', 'hours' => 24, 'minutes' => 30, 'allowed' => true],
        ];

        foreach ($cases as $case) {
            $this->travelTo(CarbonImmutable::parse($case['now'], 'UTC'));
            [$student, $booking, $sessionType] = $this->prepareBooking();
            $bookingStart = now('UTC')->addHours($case['hours'])->addMinutes($case['minutes']);
            $booking->update(['start_at_utc' => $bookingStart, 'end_at_utc' => $bookingStart->copy()->addMinutes(50)]);

            $ownerToken = Str::random(64);
            $date = CarbonImmutable::now('Africa/Cairo')->addDays(5)->startOfDay();
            $available = app(AvailabilityService::class)->getAvailableSlotsGroupedByDate(
                sessionType: $sessionType,
                customerTimezone: 'Africa/Cairo',
                fromDate: $date,
                toDate: $date,
                currentVisitorToken: $ownerToken,
            );
            $slot = $available[$date->toDateString()][0];
            $slotId = app(SlotResolver::class)->issue($sessionType, $slot, 'Africa/Cairo', $ownerToken);
            $key = Str::random(48);

            $this->actingAs($student, 'student')->withSession($this->studentSession($student) + ['student_reschedule_visitor_token' => $ownerToken]);
            $response = $this->post(route('student.bookings.reschedule.submit', $booking->id), ['slot_id' => $slotId, 'idempotency_key' => $key]);

            if ($case['allowed']) {
                $response->assertRedirect(route('student.dashboard'));
                $this->assertSame($slot['slot_start_utc'], $booking->fresh()->start_at_utc->toDateTimeString());
                $this->assertDatabaseHas('session_reschedules', ['booking_id' => $booking->id, 'idempotency_key' => $key]);
            } else {
                $response->assertSessionHasErrors('slot_id');
                $this->assertSame($bookingStart->toDateTimeString(), $booking->fresh()->start_at_utc->toDateTimeString());
                $this->assertDatabaseMissing('session_reschedules', ['booking_id' => $booking->id, 'idempotency_key' => $key]);
            }

            $this->assertSame(0, DB::table('session_ledger_entries')->where('student_id', $student->id)->count());
        }
    }

    public function test_admin_reschedule_is_recorded_in_history_without_consuming_credits(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 08:00:00 UTC');
        [$student, $booking] = $this->prepareBooking();
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $nextStart = CarbonImmutable::parse('2026-10-06 10:00:00', 'Africa/Cairo')->setTimezone('UTC');

        $this->actingAs($admin, 'web')->post(route('admin.bookings.reschedule', $booking->id), [
            'new_date' => '2026-10-06',
            'new_time' => '10:00',
            'reason' => 'Tutor schedule change',
        ])->assertRedirect();

        $this->assertDatabaseHas('session_reschedules', [
            'booking_id' => $booking->id,
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'old_start_at_utc' => $booking->start_at_utc->toDateTimeString(),
            'new_start_at_utc' => $nextStart->toDateTimeString(),
            'old_timezone' => 'Africa/Cairo',
            'new_timezone' => 'Africa/Cairo',
        ]);
        $this->assertDatabaseCount('session_ledger_entries', 0);
        $this->assertTrue($booking->fresh()->admin_reconfirmation_needed);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame(1, DB::table('session_reschedules')->where('booking_id', $booking->id)->count());
    }

    public function test_student_dashboard_shows_tutor_and_only_a_configured_https_meeting_link(): void
    {
        [$student] = $this->prepareBooking();
        Setting::set('video_meeting_url', 'https://meet.example.test/arabic-room', 'booking', true);

        $response = $this->actingAs($student, 'student')
            ->withSession($this->studentSession($student))
            ->get(route('student.dashboard'));

        $response->assertOk()
            ->assertSeeText('Tutor: Abdallah')
            ->assertSee('href="https://meet.example.test/arabic-room"', false)
            ->assertSeeText('Join lesson with Abdallah');

        Setting::set('video_meeting_url', 'javascript:alert(1)', 'booking', true);
        $this->actingAs($student, 'student')
            ->withSession($this->studentSession($student))
            ->get(route('student.dashboard'))
            ->assertDontSee('href="javascript:alert(1)"', false)
            ->assertSeeText('Your tutor will share the private meeting link before the lesson.');
    }

    /** @return array{Student, Booking, SessionType} */
    private function prepareBooking(): array
    {
        Setting::set('business_timezone', 'Africa/Cairo', 'booking', true);
        Setting::set('booking_min_notice_hours', '0', 'booking', true);
        Setting::set('booking_max_horizon_days', '60', 'booking', true);
        $sessionType = SessionType::create([
            'title' => 'Arabic lesson', 'slug' => 'arabic-lesson-'.Str::random(8),
            'duration_minutes' => 50, 'price' => 30, 'currency' => 'USD', 'active' => true,
        ]);
        for ($weekday = 0; $weekday < 7; $weekday++) {
            AvailabilityRule::create([
                'weekday' => $weekday, 'start_time' => '09:00:00', 'end_time' => '17:00:00',
                'session_duration_minutes' => 50, 'buffer_minutes' => 10,
                'min_notice_hours' => 0, 'max_horizon_days' => 60, 'enabled' => true,
            ]);
        }
        $student = Student::factory()->verified()->create(['preferred_timezone' => 'Africa/Cairo']);
        $contact = Contact::create(['name' => 'Student Reschedule', 'email' => 'reschedule+'.Str::random(8).'@example.com']);
        $start = CarbonImmutable::parse('2026-10-05 09:00:00', 'Africa/Cairo')->setTimezone('UTC');
        $snapshot = app(TimezoneService::class)->createBookingSnapshot($start, $start->addMinutes(50), 'Africa/Cairo', 'Africa/Cairo');
        $booking = Booking::create($snapshot + [
            'student_id' => $student->id, 'contact_id' => $contact->id,
            'session_type_id' => $sessionType->id, 'status' => 'confirmed',
            'confirmation_token' => bin2hex(random_bytes(32)), 'idempotency_key' => (string) Str::uuid(),
        ]);

        return [$student, $booking, $sessionType];
    }

    /** @return array<string, int|string> */
    private function studentSession(Student $student): array
    {
        return [
            'student_id' => $student->id,
            'student_authenticated_at' => now('UTC')->toIso8601String(),
            'student_auth_expires_at' => now('UTC')->addMinutes(180)->toIso8601String(),
        ];
    }
}
