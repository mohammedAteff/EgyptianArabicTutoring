<?php

namespace Tests\Feature;

use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingHoldService;
use App\Domains\Booking\Services\BookingService;
use App\Domains\Booking\Services\RescheduleService;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentBookingService;
use App\Domains\Students\Services\StudentLedgerService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\HasPublishedShortForm;
use Tests\TestCase;

class FixedDurationBookingTest extends TestCase
{
    use HasPublishedShortForm;
    use RefreshDatabase;

    private const DURATION_MESSAGE = 'This time does not fit your selected lesson duration. Please choose another time or lesson type.';

    /** @return array{SessionType, AvailabilityRule, CarbonImmutable} */
    private function schedule(int $minutes, ?int $override = null, string $funding = 'package'): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00:00 UTC'));
        $type = SessionType::factory()->create(['duration_minutes' => $minutes, 'funding_mode' => $funding, 'public_bookable' => true, 'student_discoverable' => true,
            'required_entitlement_type_id' => $funding === 'package' ? EntitlementType::where('code', $minutes === 120 ? 'two_hour' : 'one_hour')->value('id') : null, 'required_entitlement_units' => $funding === 'package' ? 1 : null]);
        $rule = AvailabilityRule::create(['weekday' => 1, 'start_time' => '10:00', 'end_time' => '18:00', 'session_duration_minutes' => $override, 'buffer_minutes' => 5, 'min_notice_hours' => 0, 'max_horizon_days' => 60, 'enabled' => true]);

        return [$type, $rule, CarbonImmutable::parse('2026-11-16 10:00:00', 'Africa/Cairo')->utc()];
    }

    private function publicPayload(SessionType $type, int $holdId, string $holdToken): array
    {
        return ['session_type_id' => $type->id, 'customer_timezone' => 'Africa/Cairo', 'first_name' => 'Synthetic', 'last_name' => 'Duration', 'customer_email' => 'duration@example.test', 'customer_phone' => '+201000000006', 'date_of_birth' => '1990-01-01', 'hold_id' => $holdId, 'hold_token' => $holdToken, 'visitor_token' => 'duration-visitor', 'session_token' => 'duration-session', 'idempotency_key' => 'duration-confirm'];
    }

    public static function fixedProducts(): array
    {
        return ['one-hour package' => [60, 120, 'package'], 'two-hour package' => [120, 60, 'package'], 'one-hour direct' => [60, 120, 'direct'], 'two-hour direct' => [120, 60, 'direct']];
    }

    #[DataProvider('fixedProducts')]
    public function test_incompatible_slots_are_hidden_and_cannot_be_held(int $minutes, int $override, string $funding): void
    {
        [$type, , $start] = $this->schedule($minutes, $override, $funding);
        $date = $start->setTimezone('Africa/Cairo')->startOfDay();
        $this->assertSame([], app(AvailabilityService::class)->getAvailableSlotsGroupedByDate($type, 'Africa/Cairo', $date, $date));

        try {
            app(BookingHoldService::class)->acquireHold('duration-visitor', 'duration-session', $type, $start, $start->addMinutes($override));
            $this->fail('An incompatible hold must be rejected.');
        } catch (SlotUnavailableException $exception) {
            $this->assertSame(self::DURATION_MESSAGE, $exception->getMessage());
        }

        $this->assertDatabaseCount('booking_holds', 0);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('payment_records', 0);
        $this->assertDatabaseCount('session_ledger_entries', 0);
    }

    #[DataProvider('fixedProducts')]
    public function test_stale_hold_reuse_and_public_confirmation_reject_changed_duration_without_partial_records(int $minutes, int $override, string $funding): void
    {
        [$type, $rule, $start] = $this->schedule($minutes, null, $funding);
        $start = $start->addMinutes($minutes + 5);
        $this->installShortFormFixture();
        $hold = app(BookingHoldService::class)->acquireHold('duration-visitor', 'duration-session', $type, $start, $start->addMinutes($minutes));
        $rule->update(['session_duration_minutes' => $override]);

        foreach (['reuse', 'confirm'] as $action) {
            try {
                if ($action === 'reuse') {
                    app(BookingHoldService::class)->validateOwnedHold($hold->id, $hold->hold_token, 'duration-visitor', 'duration-session', $type->id, $start->toDateTimeString(), $start->addMinutes($minutes)->toDateTimeString());
                } else {
                    app(BookingService::class)->createPublicBooking($this->publicPayload($type, $hold->id, $hold->hold_token));
                }
                $this->fail('A stale incompatible selection must be rejected.');
            } catch (SlotUnavailableException $exception) {
                $this->assertSame(self::DURATION_MESSAGE, $exception->getMessage());
            }
        }

        $this->assertSame('active', $hold->fresh()->status);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('payment_records', 0);
        $this->assertDatabaseCount('session_ledger_entries', 0);
    }

    public static function compatibleDurations(): array
    {
        return ['one hour' => [60], 'two hours' => [120]];
    }

    #[DataProvider('compatibleDurations')]
    public function test_overlapping_incompatible_rule_does_not_hide_or_reject_a_compatible_rule(int $minutes): void
    {
        [$type, , $start] = $this->schedule($minutes, $minutes === 120 ? 60 : 120);
        AvailabilityRule::create(['weekday' => 1, 'start_time' => '10:00', 'end_time' => '18:00', 'session_duration_minutes' => null, 'buffer_minutes' => 5, 'min_notice_hours' => 0, 'max_horizon_days' => 60, 'enabled' => true]);
        $date = $start->setTimezone('Africa/Cairo')->startOfDay();
        $slots = app(AvailabilityService::class)->getAvailableSlotsGroupedByDate($type, 'Africa/Cairo', $date, $date);

        $this->assertSame($minutes, $slots['2026-11-16'][0]['duration_minutes']);
        $hold = app(BookingHoldService::class)->acquireHold('duration-visitor', 'duration-session', $type, $start, $start->addMinutes($minutes));

        $this->assertSame($minutes, (int) $hold->slot_start_utc->diffInMinutes($hold->slot_end_utc));
        $this->assertSame('active', $hold->status);
    }

    #[DataProvider('compatibleDurations')]
    public function test_compatible_public_confirmation_keeps_product_duration_and_replay_is_idempotent(int $minutes): void
    {
        [$type, , $start] = $this->schedule($minutes);
        $this->installShortFormFixture();
        $hold = app(BookingHoldService::class)->acquireHold('duration-visitor', 'duration-session', $type, $start, $start->addMinutes($minutes));
        $payload = $this->publicPayload($type, $hold->id, $hold->hold_token);

        $booking = app(BookingService::class)->createPublicBooking($payload);

        $this->assertSame($minutes, (int) $booking->start_at_utc->diffInMinutes($booking->end_at_utc));
        $this->assertSame($booking->id, app(BookingService::class)->createPublicBooking($payload)->id);
        $this->assertSame('converted', $hold->fresh()->status);
        $this->assertDatabaseCount('bookings', 1);
    }

    #[DataProvider('compatibleDurations')]
    public function test_student_stale_slot_and_staff_package_entry_reject_mismatch_without_debit(int $minutes): void
    {
        [$type, $rule, $start] = $this->schedule($minutes);
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'Synthetic matching credits', 8, '280', '0', 'USD', null, fake()->uuid(), entitlementCode: $minutes === 120 ? 'two_hour' : 'one_hour');
        $ledger->recordPayment($package, '280', fake()->uuid(), null);
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()]);
        $this->withCookie(config('session.cookie'), session()->getId());
        $response = $this->get(route('student.bookings.create', ['session_type_id' => $type->id, 'date' => '2026-11-16', 'timezone' => 'Africa/Cairo']))->assertOk();
        $slot = $response->viewData('slots')['2026-11-16'][0];
        $rule->update(['session_duration_minutes' => $minutes === 120 ? 60 : 120]);

        $this->post(route('student.bookings.store'), ['session_type_id' => $type->id, 'timezone' => 'Africa/Cairo', 'slot_id' => $slot['slot_id'], 'idempotency_key' => $response->viewData('idempotencyKey')])
            ->assertSessionHasErrors(['slot_id' => self::DURATION_MESSAGE]);
        $this->assertSame(8, $ledger->summary($package->fresh())['remaining_credits']);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseMissing('session_ledger_entries', ['entry_type' => 'session_consumed']);

        $admin = AdministratorFactory::new()->create(['role' => 'super_admin']);
        try {
            app(StudentBookingService::class)->createForAdministrator($student, $type, $start, 'Africa/Cairo', str_repeat('a', 48), $admin->id);
            $this->fail('Staff package entry must reject the same mismatch.');
        } catch (SlotUnavailableException) {
            $this->assertDatabaseCount('bookings', 0);
            $this->assertDatabaseMissing('session_ledger_entries', ['entry_type' => 'session_consumed']);
        }
        try {
            app(BookingService::class)->createAdminBooking(['session_type_id' => $type->id, 'start_at_utc' => $start, 'end_at_utc' => $start->addMinutes($minutes === 120 ? 60 : 120), 'customer_email' => 'manual@example.test']);
            $this->fail('Direct staff entry must not bypass a package product duration.');
        } catch (SlotUnavailableException $exception) {
            $this->assertSame(self::DURATION_MESSAGE, $exception->getMessage());
        }
        $this->assertDatabaseCount('bookings', 0);
        $this->assertSame(8, $ledger->summary($package->fresh())['remaining_credits']);

        $rule->update(['session_duration_minutes' => null]);
        $key = str_repeat('b', 48);
        $bookings = app(StudentBookingService::class);
        $booking = $bookings->createForAdministrator($student, $type, $start, 'Africa/Cairo', $key, $admin->id);
        $this->assertSame($minutes, (int) $booking->start_at_utc->diffInMinutes($booking->end_at_utc));
        $this->assertSame($booking->id, $bookings->createForAdministrator($student, $type, $start, 'Africa/Cairo', $key, $admin->id)->id);
        $this->assertSame(7, $ledger->summary($package->fresh())['remaining_credits']);
        $this->assertDatabaseCount('bookings', 1);
        $rule->update(['session_duration_minutes' => $minutes === 120 ? 60 : 120]);
        try {
            app(RescheduleService::class)->reschedule($booking, $start->addWeek(), performedBy: 'admin', performedById: $admin->id);
            $this->fail('A funded lesson must keep its product duration during staff rescheduling.');
        } catch (SlotUnavailableException $exception) {
            $this->assertSame(self::DURATION_MESSAGE, $exception->getMessage());
        }
        $this->assertSame($start->toDateTimeString(), $booking->fresh()->start_at_utc->toDateTimeString());
        $this->assertSame(7, $ledger->summary($package->fresh())['remaining_credits']);
        $this->assertSame(1, SessionLedgerEntry::where('entry_type', 'session_consumed')->count());
    }

    public function test_duration_error_is_translated_for_public_locales(): void
    {
        [$type, , $start] = $this->schedule(120, 60);
        foreach (['fr', 'de'] as $locale) {
            app()->setLocale($locale);
            try {
                app(AvailabilityService::class)->resolveSlotConfiguration($type, $start, requireSessionDuration: true);
                $this->fail('Incompatible slot should be rejected.');
            } catch (SlotUnavailableException $exception) {
                $this->assertSame(__(self::DURATION_MESSAGE), $exception->getMessage());
                $this->assertNotSame(self::DURATION_MESSAGE, $exception->getMessage());
            }
        }
    }

    public function test_legacy_short_hold_is_rejected_even_after_the_rule_becomes_compatible(): void
    {
        [$type, , $start] = $this->schedule(120);
        $this->installShortFormFixture();
        $hold = app(BookingHoldService::class)->acquireHold('duration-visitor', 'duration-session', $type, $start, $start->addMinutes(120));
        $hold->update(['slot_end_utc' => $start->addMinutes(60)]);

        try {
            app(BookingService::class)->createPublicBooking($this->publicPayload($type, $hold->id, $hold->hold_token));
            $this->fail('A legacy short hold must not confirm after the rule changes.');
        } catch (SlotUnavailableException $exception) {
            $this->assertSame(self::DURATION_MESSAGE, $exception->getMessage());
        }

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('payment_records', 0);
        $this->assertDatabaseCount('session_ledger_entries', 0);
    }
}
