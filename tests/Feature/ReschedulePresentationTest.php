<?php

namespace Tests\Feature;

use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Availability\Services\SlotResolver;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Timezone\Services\TimezoneService;
use App\Livewire\BookingWizard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReschedulePresentationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function zones(): array
    {
        return ['Cairo' => ['Africa/Cairo', 'eg.svg'], 'New York' => ['America/New_York', 'us.svg'], 'Berlin' => ['Europe/Berlin', 'de.svg'], 'UTC' => ['UTC', 'globe.svg']];
    }

    #[DataProvider('zones')]
    public function test_public_and_student_use_one_timezone_picker_calendar_and_slot_presentation(string $zone, string $flag): void
    {
        [$student, $lesson, $session] = $this->fixtures();
        $public = Livewire::test(BookingWizard::class)->call('selectTimezone', $zone)->call('selectDate', '2026-10-08');
        $public->assertSee('data-timezone-selector', false)->assertSee('Search timezones')->assertSee($flag)->assertSee('data-booking-slot', false)->assertSee('Tutor equivalent:');
        $this->portal($student);

        $response = $this->get(route('student.bookings.reschedule', ['booking' => $lesson->id, 'timezone' => $zone]));
        $response->assertSee('data-timezone-selector', false)->assertSee('Search timezones')->assertSee($flag)->assertSee('data-booking-slot', false)
            ->assertSee('Tutor equivalent:')->assertSee('Review your change')->assertSee('Old time')->assertSee('New time')->assertSee('Confirm reschedule')
            ->assertDontSee('current-student-timezones');
        $this->assertSame(1, substr_count($response->getContent(), 'data-timezone-selector'));
        $slots = $response->viewData('slots');
        $this->assertNotEmpty($slots['2026-10-08']);
        $this->assertMatchesRegularExpression('/^\d{1,2}:\d{2} [AP]M$/', $slots['2026-10-08'][0]['label']);
        $slot = $slots['2026-10-08'][0];
        $resolved = app(SlotResolver::class)->resolve($slot['slot_id'], $session, $zone, session('student_reschedule_visitor_token'));
        $this->assertSame($slot['slot_start_utc'], $resolved['slot_start_utc']);
        $this->assertSame($slot['slot_end_utc'], $resolved['slot_end_utc']);
    }

    public function test_student_reschedule_preserves_utc_and_exact_typed_debit_once(): void
    {
        [$student, $lesson, $session] = $this->fixtures();
        $session->update(['funding_mode' => 'package', 'required_entitlement_type_id' => EntitlementType::where('code', 'two_hour')->value('id'), 'required_entitlement_units' => 1]);
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'Long lessons', 2, '100', '0', 'USD', null, 'package-long', entitlementCode: 'two_hour');
        DB::transaction(function () use ($student, $lesson, $ledger): void {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $ledger->consumeForBooking($lesson, 'original-debit');
        });
        $lesson->refresh();
        $original = $lesson->only(['consumed_ledger_entry_id', 'student_package_id', 'student_package_entitlement_id', 'entitlement_type_id', 'entitlement_code', 'entitlement_units']);
        $this->portal($student);
        $response = $this->get(route('student.bookings.reschedule', ['booking' => $lesson->id, 'timezone' => 'America/New_York']));
        $slot = $response->viewData('slots')['2026-10-08'][0];
        $payload = ['slot_id' => $slot['slot_id'], 'timezone' => 'America/New_York', 'idempotency_key' => $response->viewData('idempotencyKey')];

        $this->post(route('student.bookings.reschedule.submit', $lesson->id), $payload)->assertRedirect(route('student.dashboard'));
        $this->post(route('student.bookings.reschedule.submit', $lesson->id), $payload)->assertRedirect(route('student.dashboard'));

        $lesson->refresh();
        $this->assertSame($original, $lesson->only(array_keys($original)));
        $this->assertSame($slot['slot_start_utc'], $lesson->start_at_utc->toDateTimeString());
        $this->assertSame('America/New_York', $lesson->customer_timezone);
        $this->assertSame('Africa/Cairo', $lesson->business_timezone);
        $this->assertSame($package->id, $lesson->student_package_id);
        $this->assertSame(1, SessionLedgerEntry::where('entry_type', 'session_consumed')->count());
        $this->assertDatabaseCount('session_reschedules', 1);
    }

    public function test_invalid_month_or_timezone_input_falls_back_safely(): void
    {
        [$student, $lesson] = $this->fixtures();
        $this->portal($student);

        $this->get(route('student.bookings.reschedule', ['booking' => $lesson->id, 'timezone' => ['invalid'], 'date' => '2026-99-99']))->assertOk()->assertViewHas('timezone', 'Africa/Cairo');
    }

    /** @return array<string, array{string, string}> */
    public static function confirmationInstants(): array
    {
        return [
            'summer offset' => ['2026-10-21 13:15:00 UTC', '-04:00'],
            'winter offset' => ['2027-01-12 14:15:00 UTC', '-05:00'],
        ];
    }

    #[DataProvider('confirmationInstants')]
    public function test_confirmation_display_timezone_labels_match_the_converted_time_without_rewriting_the_snapshot(string $startUtc, string $offset): void
    {
        [, $lesson] = $this->fixtures();
        $start = CarbonImmutable::parse($startUtc);
        $lesson->update(app(TimezoneService::class)->createBookingSnapshot($start, $start->addHour(), 'Africa/Cairo', 'Africa/Cairo'));
        $snapshot = $lesson->only(['start_at_utc', 'end_at_utc', 'customer_timezone', 'customer_utc_offset_at_booking']);

        $this->get(route('booking.confirmation', ['token' => $lesson->confirmation_token, 'timezone' => 'America/New_York']))
            ->assertOk()
            ->assertSee('9:15 AM')
            ->assertSee('10:15 AM')
            ->assertSee('New York · America/New_York (UTC'.$offset.')');

        $this->assertEquals($snapshot, $lesson->fresh()->only(array_keys($snapshot)));
    }

    /** @return array{Student, Booking, SessionType} */
    private function fixtures(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00:00 UTC'));
        Setting::set('business_timezone', 'Africa/Cairo', 'booking', true);
        Setting::set('booking_max_horizon_days', '60', 'booking', true);
        Setting::set('booking_min_notice_hours', '0', 'booking', true);
        $session = SessionType::query()->create(['title' => 'Arabic practice', 'slug' => 'arabic-practice', 'duration_minutes' => 50, 'price' => 30, 'currency' => 'USD', 'active' => true, 'funding_mode' => 'direct']);
        for ($weekday = 0; $weekday < 7; $weekday++) {
            AvailabilityRule::query()->create(['weekday' => $weekday, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'session_duration_minutes' => 50, 'buffer_minutes' => 10, 'min_notice_hours' => 0, 'max_horizon_days' => 60, 'enabled' => true]);
        }
        $student = Student::factory()->verified()->create(['preferred_timezone' => 'Africa/Cairo']);
        $lesson = Booking::factory()->create(['student_id' => $student->id, 'session_type_id' => $session->id]);

        return [$student, $lesson, $session];
    }

    private function portal(Student $student): void
    {
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_authenticated_at' => now('UTC')->toIso8601String(), 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()]);
    }
}
