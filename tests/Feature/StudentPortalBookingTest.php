<?php

namespace Tests\Feature;

use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\LessonMaterial;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\ResourceAssignment;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentNotification;
use App\Domains\Students\Models\StudentUnavailability;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Students\Services\StudentSchedulingService;
use App\Domains\Students\Services\StudentTeachingReadModel;
use Carbon\CarbonImmutable;
use Database\Factories\ResourceFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPortalBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owned_unavailability_is_excluded_from_displayed_slots_using_the_existing_rule(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-12 08:00', 'Africa/Cairo'));
        $date = CarbonImmutable::parse('2026-10-19', 'Africa/Cairo');
        $type = $this->oneHourPackageLesson();
        $student = Student::factory()->verified()->create(['preferred_timezone' => 'Africa/Cairo']);
        app(StudentLedgerService::class)->createPackage($student, 'Display fixture', 2, '80.00', '0.00', 'USD', null, 'display-fixture-grant', entitlementCode: 'one_hour');
        AvailabilityRule::query()->create(['weekday' => $date->dayOfWeek, 'start_time' => '10:00:00', 'end_time' => '14:00:00', 'session_duration_minutes' => 60, 'buffer_minutes' => 0, 'min_notice_hours' => 0, 'max_horizon_days' => 60, 'enabled' => true]);
        $teacherSlots = app(AvailabilityService::class)->getAvailableSlotsGroupedByDate($type, 'Africa/Cairo', $date, $date);
        $this->assertNotEmpty($teacherSlots[$date->toDateString()]);
        StudentUnavailability::factory()->create(['student_id' => $student->id, 'starts_at_utc' => $date->utc(), 'ends_at_utc' => $date->addDay()->utc(), 'status' => 'active']);

        try {
            app(StudentSchedulingService::class)->assertAvailable($student->id, $date->setTime(10, 0)->utc(), $date->setTime(11, 0)->utc());
            $this->fail('The authoritative scheduling rule must reject the recorded period.');
        } catch (SlotUnavailableException $exception) {
            $this->assertSame('This time overlaps your recorded unavailability.', $exception->getMessage());
        }

        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()]);
        $response = $this->get(route('student.bookings.create', ['session_type_id' => $type->id, 'date' => $date->toDateString(), 'timezone' => 'Africa/Cairo']))->assertOk();
        $this->assertSame([], $response->viewData('slots')[$date->toDateString()] ?? []);
    }

    public function test_unfunded_selection_can_change_to_another_funded_type_without_authorizing_unfunded_booking(): void
    {
        $student = Student::factory()->verified()->create();
        $oneHour = $this->oneHourPackageLesson();
        $twoHour = SessionType::factory()->create(['funding_mode' => 'package', 'required_entitlement_type_id' => EntitlementType::query()->where('code', 'two_hour')->value('id'), 'required_entitlement_units' => 1, 'duration_minutes' => 120]);
        app(StudentLedgerService::class)->createPackage($student, 'Picker fixture', 2, '80.00', '0.00', 'USD', null, 'picker-fixture-grant', entitlementCode: 'one_hour');
        $this->studentSession($student);

        $response = $this->get(route('student.bookings.create', ['session_type_id' => $twoHour->id]))->assertOk();
        $this->assertSame(0, $response->viewData('availableCredits'));
        $this->assertTrue($response->viewData('hasFundableFormat'));
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $button = (new \DOMXPath($document))->query('//button[normalize-space(.)="Show Available Times"]')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $button);
        $this->assertFalse($button->hasAttribute('disabled'));
        $this->assertSame([], $response->viewData('slots'));
        $funded = $this->get(route('student.bookings.create', ['session_type_id' => $oneHour->id]))->assertOk();
        $this->assertSame(2, $funded->viewData('availableCredits'));
        $this->assertSame(0, Booking::query()->count());
    }

    public function test_hidden_types_leave_credits_and_historical_appointments_intact(): void
    {
        $student = Student::factory()->verified()->create();
        $hidden = $this->oneHourPackageLesson();
        $hidden->update(['student_discoverable' => false]);
        $package = app(StudentLedgerService::class)->createPackage($student, 'Preserved fixture', 2, '80.00', '0.00', 'USD', null, 'preserved-fixture-grant', entitlementCode: 'one_hour');
        $booking = Booking::factory()->create(['student_id' => $student->id, 'session_type_id' => $hidden->id]);
        $this->studentSession($student);
        $response = $this->get(route('student.bookings.create'))->assertOk()->assertSee('Lesson booking is unavailable right now');
        $this->assertTrue($response->viewData('sessionTypes')->isEmpty());
        $this->get(route('student.scheduling.index'))->assertOk()->assertDontSee($hidden->title);
        $this->get(route('student.bookings.show', $booking->id))->assertOk()->assertSee($hidden->title);
        $this->assertSame(2, (int) app(StudentLedgerService::class)->summary($package->fresh())['remaining_credits']);
        $this->post(route('student.scheduling.store', 'waitlist'), ['session_type_id' => $hidden->id])->assertSessionHasErrors('session_type_id');
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_appointment_details_are_owned_and_future_material_access_remains_denied(): void
    {
        $student = Student::factory()->verified()->create();
        $other = Student::factory()->verified()->create();
        $owned = Booking::factory()->create(['student_id' => $student->id, 'funding_mode' => 'direct']);
        $foreign = Booking::factory()->create(['student_id' => $other->id]);
        LessonMaterial::factory()->create(['booking_id' => $owned->id, 'title' => 'Restricted future material', 'student_visible' => true]);
        $this->get(route('student.bookings.show', $owned->id))->assertRedirect(route('student.login'));
        $this->studentSession($student);
        $this->get(route('student.bookings.show', $foreign->id))->assertNotFound();
        $this->get(route('student.bookings.show', $owned->id))->assertOk()
            ->assertSee('To be arranged')->assertSee('Add to Calendar')->assertSee('Reschedule Lesson')
            ->assertDontSee('Restricted future material')->assertDontSee('View Learning Materials')->assertDontSee('Payment Recorded');
        $this->get(route('student.lessons.show', $owned->id))->assertNotFound();
        $this->assertSame('confirmed', $owned->fresh()->status);
    }

    public function test_completed_and_cancelled_details_keep_their_real_status_and_calendar_eligibility(): void
    {
        $student = Student::factory()->verified()->create();
        $completed = Booking::factory()->create(['student_id' => $student->id, 'status' => 'completed']);
        $cancelled = Booking::factory()->create(['student_id' => $student->id, 'status' => 'cancelled']);
        $this->studentSession($student);
        $this->get(route('student.bookings.show', $completed->id))->assertOk()->assertViewHas('statusLabel', 'Completed')->assertSee('View Learning Materials')->assertDontSee('Add to Calendar');
        $this->get(route('student.bookings.show', $cancelled->id))->assertOk()->assertViewHas('statusLabel', 'Cancelled')->assertDontSee('Add to Calendar')->assertDontSee('View Learning Materials');
        $this->assertSame('completed', $completed->fresh()->status);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
    }

    public function test_lesson_notifications_get_owned_destinations_and_legacy_ids_and_read_state_are_preserved(): void
    {
        $student = Student::factory()->verified()->create();
        $booking = Booking::factory()->create(['student_id' => $student->id]);
        $readAt = now('UTC')->subDay();
        $legacy = StudentNotification::factory()->create(['student_id' => $student->id, 'type' => 'lesson', 'link' => '/student#history-title', 'read_at' => $readAt]);
        $this->studentSession($student);
        $response = $this->get(route('student.notifications.index'))->assertOk();
        $this->assertSame(route('student.dashboard'), $response->viewData('destinations')[$legacy->id]);
        $this->assertDatabaseHas('student_notifications', ['student_id' => $student->id, 'type' => 'lesson', 'link' => route('student.bookings.show', $booking->id, false)]);
        $this->assertSame('/student#history-title', $legacy->fresh()->link);
        $this->assertSame($readAt->toDateTimeString(), $legacy->fresh()->read_at->toDateTimeString());
        $this->get(route('student.notifications.index'))->assertOk();
        $this->assertSame(2, StudentNotification::query()->where('student_id', $student->id)->where('type', 'lesson')->count());
    }

    public function test_owned_unavailability_is_excluded_from_reschedule_choices_using_the_existing_rule(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-12 08:00', 'Africa/Cairo'));
        $date = CarbonImmutable::parse('2026-10-19', 'Africa/Cairo');
        $student = Student::factory()->verified()->create(['preferred_timezone' => 'Africa/Cairo']);
        $type = $this->oneHourPackageLesson();
        $booking = Booking::factory()->create(['student_id' => $student->id, 'session_type_id' => $type->id, 'start_at_utc' => $date->addDay()->setTime(10, 0)->utc(), 'end_at_utc' => $date->addDay()->setTime(11, 0)->utc()]);
        AvailabilityRule::query()->create(['weekday' => $date->dayOfWeek, 'start_time' => '10:00:00', 'end_time' => '14:00:00', 'session_duration_minutes' => 60, 'buffer_minutes' => 0, 'min_notice_hours' => 0, 'max_horizon_days' => 60, 'enabled' => true]);
        $this->assertNotEmpty(app(AvailabilityService::class)->getAvailableSlotsGroupedByDate($type, 'Africa/Cairo', $date, $date)[$date->toDateString()]);
        StudentUnavailability::factory()->create(['student_id' => $student->id, 'starts_at_utc' => $date->utc(), 'ends_at_utc' => $date->addDay()->utc(), 'status' => 'active']);
        $this->studentSession($student);
        $response = $this->get(route('student.bookings.reschedule', [$booking->id, 'date' => $date->toDateString(), 'timezone' => 'Africa/Cairo']))->assertOk();
        $this->assertSame([], $response->viewData('slots')[$date->toDateString()] ?? []);
    }

    public function test_hidden_resource_discovery_preserves_the_assignment_staff_projection_and_authorized_delivery(): void
    {
        $student = Student::factory()->verified()->create();
        $resource = ResourceFactory::new()->create(['title' => 'Explicit hidden resource fixture', 'student_discoverable' => false]);
        $assignment = ResourceAssignment::factory()->create(['student_id' => $student->id, 'resource_id' => $resource->id, 'student_visible' => true]);
        $this->studentSession($student);
        $this->get(route('student.teaching.index'))->assertOk()->assertDontSee($resource->title);
        $this->get(route('student.resources.open', $assignment->id))->assertRedirect($resource->external_url);
        $this->assertDatabaseHas('resource_assignments', ['id' => $assignment->id, 'student_visible' => true]);
        $this->assertTrue(app(StudentTeachingReadModel::class)->resources($student)->contains('id', $assignment->id));
        $this->assertFalse(app(StudentTeachingReadModel::class)->resources($student, studentDiscovery: true)->contains('id', $assignment->id));
    }

    private function studentSession(Student $student): void
    {
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()]);
    }
}
