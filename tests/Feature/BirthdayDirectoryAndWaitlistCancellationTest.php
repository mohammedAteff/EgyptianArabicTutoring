<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\AdminNotification;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\CancellationService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentNotification;
use App\Domains\Students\Services\StudentBookingService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Students\Services\StudentSchedulingService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BirthdayDirectoryAndWaitlistCancellationTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['Europe/Paris', '2026-10-22', '07:00', '7:00 AM'])]
    #[TestWith(['America/New_York', '2026-10-22', '01:00', '1:00 AM'])]
    #[TestWith(['Pacific/Honolulu', '2026-10-21', '19:00', '7:00 PM'])]
    public function test_cancelling_iriss_october_22_lesson_notifies_the_matching_student_and_staff_on_the_next_scan(string $timezone, string $date, string $time, string $label): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00:00 UTC'));
        Setting::set('business_timezone', 'Africa/Cairo');
        Setting::set('birthdays.enabled', false);
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $iris = Student::factory()->verified()->create(['first_name' => 'Iris', 'last_name' => 'Smith', 'preferred_timezone' => 'Europe/Paris']);
        $waiting = Student::factory()->verified()->create(['first_name' => 'Waiting', 'last_name' => 'Learner', 'preferred_timezone' => $timezone]);
        $type = SessionType::factory()->create(['title' => 'Two-Hour Single Session', 'duration_minutes' => 120, 'funding_mode' => 'package',
            'student_discoverable' => true, 'required_entitlement_type_id' => EntitlementType::query()->where('code', 'two_hour')->value('id'), 'required_entitlement_units' => 1]);
        AvailabilityRule::query()->create(['weekday' => 4, 'start_time' => '08:00', 'end_time' => '20:00', 'session_duration_minutes' => null,
            'buffer_minutes' => 10, 'min_notice_hours' => 24, 'max_horizon_days' => 60, 'enabled' => true]);
        $ledger = app(StudentLedgerService::class);
        $irisPackage = $ledger->createPackage($iris, 'Iris package', 2, '100', '0', 'USD', null, fake()->uuid(), entitlementCode: 'two_hour');
        $waitingPackage = $ledger->createPackage($waiting, 'Waiting package', 2, '100', '0', 'USD', null, fake()->uuid(), entitlementCode: 'two_hour');
        $booking = app(StudentBookingService::class)->createForAdministrator($iris, $type, CarbonImmutable::parse('2026-10-22 05:00:00 UTC'), 'Europe/Paris', fake()->uuid(), $actor->id);
        $interest = app(StudentSchedulingService::class)->waitlist($waiting, ['session_type_id' => $type->id, 'date_from' => $date, 'date_to' => $date,
            'preferred_time_from' => $time, 'preferred_time_to' => $time, 'timezone' => $timezone, 'idempotency_key' => fake()->uuid()]);
        $this->artisan('students:planning-reminders')->assertSuccessful();
        $this->assertSame(0, StudentNotification::query()->where('type', 'waitlist')->count());
        $this->assertSame(0, AdminNotification::query()->where('type', 'waitlist_opening')->count());

        app(CancellationService::class)->cancel($booking, 'customer', $iris->id, 'Schedule changed');
        $this->artisan('students:planning-reminders')->assertSuccessful();
        $this->assertSame('cancelled', $booking->fresh()->status);
        $notification = StudentNotification::query()->where('student_id', $waiting->id)->where('type', 'waitlist')->sole();
        $this->assertStringContainsString($timezone, $notification->message);
        $this->assertStringContainsString($label, $notification->message);
        $this->assertStringNotContainsString('Iris', $notification->message);
        $staffNotification = AdminNotification::query()->where('type', 'waitlist_opening')->sole();
        $this->assertSame($waiting->id, $staffNotification->data['student_id']);
        $this->assertSame($interest->id, $staffNotification->data['waitlist_id']);
        $this->assertSame('2026-10-22 05:00:00', $interest->fresh()->last_offered_start_at->toDateTimeString());
        $this->assertSame(2, $ledger->summary($irisPackage->fresh())['remaining_credits']);
        $this->assertSame(2, $ledger->summary($waitingPackage->fresh())['remaining_credits']);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertSame(1, SessionLedgerEntry::query()->where('entry_type', 'session_consumed')->count());
        $this->assertSame(1, SessionLedgerEntry::query()->where('entry_type', 'cancellation_restore')->count());

        $this->artisan('students:planning-reminders')->assertSuccessful();
        $this->assertSame(1, StudentNotification::query()->where('type', 'waitlist')->count());
        $this->assertSame(1, AdminNotification::query()->where('type', 'waitlist_opening')->count());
    }

    public function test_staff_can_open_the_whole_year_list_from_the_calendar_without_birth_years_or_ages(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00:00 UTC'));
        Student::factory()->verified()->create(['first_name' => 'Zoe', 'last_name' => 'January', 'date_of_birth' => '1982-01-15']);
        Student::factory()->verified()->create(['first_name' => 'Iris', 'last_name' => 'July', 'date_of_birth' => '1995-07-22']);
        Student::factory()->verified()->create(['first_name' => 'Leah', 'last_name' => 'Leapday', 'date_of_birth' => '1992-02-29']);
        Student::factory()->verified()->create(['first_name' => 'Zane', 'last_name' => 'February', 'date_of_birth' => '1988-02-28']);
        Student::factory()->verified()->create(['first_name' => 'Adam', 'last_name' => 'December', 'date_of_birth' => '1986-12-03']);
        Student::factory()->verified()->create(['first_name' => 'Archived', 'last_name' => 'Secret', 'date_of_birth' => '1990-03-01', 'operational_status' => 'archived']);
        Student::factory()->verified()->create(['first_name' => 'Missing', 'last_name' => 'Birthday', 'date_of_birth' => null]);
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'assistant']));
        $this->get(route('admin.birthdays.index'))->assertOk()->assertSee('View all birthdays')->assertSee(route('admin.birthdays.all'));
        $this->get(route('admin.birthdays.all'))->assertOk()->assertSeeTextInOrder(['Zoe January', 'Zane February', 'Leah Leapday', 'Iris July', 'Adam December'])
            ->assertSeeText('29 February')->assertSeeText('5 birthdays')->assertDontSeeText('Archived Secret')->assertDontSeeText('Missing Birthday')
            ->assertDontSeeText('1982')->assertDontSeeText('1995')->assertDontSeeText('1992')->assertDontSeeText('1986');
    }

    public function test_birthday_directory_requires_staff_authentication(): void
    {
        $this->get(route('admin.birthdays.all'))->assertRedirect(route('admin.login'));
    }
}
