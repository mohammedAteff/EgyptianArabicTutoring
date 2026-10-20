<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\AdminNotification;
use App\Domains\Administration\Services\OperationsReadModel;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingWaitlist;
use App\Domains\Booking\Models\RecurringLessonPlan;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentNotification;
use App\Domains\Students\Services\BirthdayService;
use App\Domains\Students\Services\EntitlementService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Students\Services\WaitlistAvailabilityService;
use App\Livewire\BookingWizard;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentPlanningEnhancementTest extends TestCase
{
    use RefreshDatabase;

    private function login(Student $student): void
    {
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_authenticated_at' => now('UTC')->toIso8601String(), 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()]);
    }

    public function test_low_credit_list_excludes_a_student_with_a_healthy_balance_in_either_type(): void
    {
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $ledger->createPackage($student, 'One hour', 1, '0', '0', 'USD', null, fake()->uuid(), entitlementCode: 'one_hour');
        $ledger->createPackage($student, 'Two hours', 10, '100', '0', 'USD', null, fake()->uuid(), entitlementCode: 'two_hour');
        $low = Student::factory()->verified()->create();
        $ledger->createPackage($low, 'Low one hour', 1, '25', '0', 'USD', null, fake()->uuid(), entitlementCode: 'one_hour');
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $data = app(OperationsReadModel::class)->forAdministrator($actor);
        $this->assertSame([$low->id], $data['lowCreditStudents']->modelKeys());
        $this->assertFalse(app(EntitlementService::class)->allCreditsLow($student->packages));
        $this->assertTrue(app(EntitlementService::class)->allCreditsLow($low->packages));
    }

    public function test_students_can_request_a_busy_time_and_receive_one_opening_notification_without_booking(): void
    {
        $this->travelTo(CarbonImmutable::parse('2031-01-01 10:00:00 UTC'));
        Setting::set('business_timezone', 'UTC');
        $student = Student::factory()->verified()->create(['preferred_timezone' => 'UTC']);
        $type = SessionType::factory()->create(['student_discoverable' => true]);
        AvailabilityRule::query()->create(['weekday' => 4, 'start_time' => '08:00', 'end_time' => '14:00', 'session_duration_minutes' => null, 'buffer_minutes' => 0, 'min_notice_hours' => 0, 'max_horizon_days' => 60, 'enabled' => true]);
        $busy = Booking::factory()->create(['start_at_utc' => '2031-01-02 10:00:00', 'end_at_utc' => '2031-01-02 12:00:00', 'status' => 'confirmed']);
        $this->login($student);
        $data = ['session_type_id' => $type->id, 'date_from' => '2031-01-02', 'date_to' => '2031-01-02', 'timezone' => 'UTC', 'preferred_time_from' => '10:00', 'preferred_time_to' => '10:00', 'idempotency_key' => fake()->uuid()];
        $this->post(route('student.scheduling.store', 'waitlist'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $interest = BookingWaitlist::query()->firstOrFail();
        $this->assertSame('10:00:00', $interest->preferred_time_from);
        $waitlist = app(WaitlistAvailabilityService::class);
        $this->assertSame([], $waitlist->matches($interest));
        $busy->update(['status' => 'cancelled']);
        $this->assertCount(1, $waitlist->matches($interest));
        $this->assertSame(1, $waitlist->notifyOpenings());
        $this->assertSame(0, $waitlist->notifyOpenings());
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('session_ledger_entries', 0);
        $this->assertSame(1, StudentNotification::query()->where('student_id', $student->id)->where('type', 'waitlist')->count());
        $this->post(route('student.waitlist.withdraw', $interest))->assertRedirect();
        $this->assertSame([], $waitlist->matches($interest->fresh()));
    }

    public function test_planning_shows_only_the_students_own_recurring_plans(): void
    {
        $student = Student::factory()->verified()->create();
        $own = SessionType::factory()->create(['title' => 'My weekly conversation']);
        $foreign = SessionType::factory()->create(['title' => 'Another student secret', 'student_discoverable' => false]);
        RecurringLessonPlan::factory()->create(['student_id' => $student->id, 'session_type_id' => $own->id]);
        RecurringLessonPlan::factory()->create(['session_type_id' => $foreign->id]);
        $this->login($student);
        $this->get(route('student.scheduling.index'))->assertOk()->assertSeeText('My weekly conversation')->assertDontSeeText('Another student secret')->assertSeeText('Lesson Waitlist');
        $this->get(route('admin.birthdays.index'))->assertRedirect();
    }

    public function test_birthday_reminders_cross_the_year_boundary_and_do_not_repeat(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-12-29 10:00:00 UTC'));
        Setting::set('business_timezone', 'UTC');
        Setting::set('birthdays.days_before', 7);
        Student::factory()->verified()->create(['first_name' => 'January', 'last_name' => 'Learner', 'date_of_birth' => '1992-01-05']);
        $birthdays = app(BirthdayService::class);
        $this->assertSame(1, $birthdays->remind());
        $this->assertSame(0, $birthdays->remind());
        $this->assertDatabaseCount('student_birthday_reminders', 1);
        $this->assertSame('2031-01-05', AdminNotification::query()->where('type', 'student_birthday')->firstOrFail()->data['birthday_date']);
        Setting::set('birthdays.enabled', false);
        $this->assertSame(0, $birthdays->remind());
    }

    public function test_two_students_with_the_same_name_receive_separate_birthday_reminders(): void
    {
        $this->travelTo(CarbonImmutable::parse('2031-01-01 10:00:00 UTC'));
        Setting::set('business_timezone', 'UTC');
        Student::factory()->verified()->count(2)->create(['first_name' => 'Same', 'last_name' => 'Name', 'date_of_birth' => '1990-01-08']);
        $this->assertSame(2, app(BirthdayService::class)->remind());
        $this->assertSame(2, AdminNotification::query()->where('type', 'student_birthday')->count());
    }

    public function test_calendar_hides_birth_years_observes_leap_days_and_excludes_archived_students(): void
    {
        $this->travelTo(CarbonImmutable::parse('2031-02-01 10:00:00 UTC'));
        Setting::set('business_timezone', 'UTC');
        $leap = Student::factory()->verified()->create(['first_name' => 'Leap', 'last_name' => 'Learner', 'date_of_birth' => '1992-02-29']);
        Student::factory()->verified()->create(['first_name' => 'Archived', 'last_name' => 'Secret', 'date_of_birth' => '1986-02-10', 'operational_status' => 'archived']);
        $this->assertSame('2031-02-28', app(BirthdayService::class)->occurrence($leap, 2031)->toDateString());
        $this->assertSame('2032-02-29', app(BirthdayService::class)->occurrence($leap, 2032)->toDateString());
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'assistant']))->get(route('admin.birthdays.index'))->assertOk()->assertSeeText('Leap Learner')->assertDontSeeText('1992')->assertDontSeeText('Archived Secret');
        $this->post(route('admin.birthdays.update'), ['enabled' => 1, 'days_before' => 2, 'time' => '08:00'])->assertForbidden();
    }

    public function test_admin_can_save_reminder_settings_and_pin_preference_without_enabling_pin_verification(): void
    {
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']));
        $this->post(route('admin.birthdays.update'), ['enabled' => 1, 'days_before' => 3, 'time' => '08:30'])->assertSessionHasNoErrors();
        $this->assertSame(3, Setting::get('birthdays.days_before'));
        $this->post(route('admin.settings.email-safeguards'), ['email_pin_preference' => 'request_activation'])->assertSessionHasNoErrors();
        $this->get(route('admin.settings.index'))->assertOk()->assertSeeText('Six-digit PIN verification: OFF');
        $this->assertFalse((bool) config('business.resources.require_pin_verification'));
    }

    public function test_public_details_reject_disposable_email_before_advancing_to_review(): void
    {
        Livewire::test(BookingWizard::class)->set('first_name', 'Test')->set('last_name', 'Learner')->set('email', 'student@mailinator.com')->set('phone', '+12025550123')->set('date_of_birth', '1990-01-01')->call('submitDetails')->assertHasErrors('email');
        $this->assertDatabaseCount('bookings', 0);
    }
}
