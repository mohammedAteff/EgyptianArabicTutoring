<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingHoldService;
use App\Domains\Booking\Services\BookingService;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Services\FormAssignmentService;
use App\Domains\Forms\Services\FormBuilderService;
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('intermediate-schema')]
class MigrationACompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_prompt_trigger_column_physically_exists_under_intermediate_schema(): void
    {
        $this->assertTrue(
            Schema::hasColumn('forms', 'prompt_trigger'),
            'Failed asserting that forms.prompt_trigger column is physically present in the intermediate schema.'
        );
    }

    public function test_form_workflows_operate_without_errors_under_intermediate_schema(): void
    {
        $admin = Administrator::create([
            'name' => 'Super Admin',
            'email' => 'admin-compat@example.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
        ]);

        $builder = app(FormBuilderService::class);
        $questions = [
            [
                'question_key' => 'experience_level',
                'label' => 'What is your Arabic experience?',
                'question_type' => 'short_text',
                'is_required' => true,
                'assistant_visible' => true,
                'sort_order' => 1,
            ],
        ];

        // 1. Create a form with pre_booking trigger
        $form = $builder->create([
            'title' => 'Pre-Booking Intake Form',
            'slug' => 'pre-booking-intake-test',
            'trigger' => 'pre_booking',
            'is_mandatory' => true,
            'can_edit_after_submission' => false,
        ], $questions, $admin);

        $this->assertInstanceOf(Form::class, $form);
        $this->assertDatabaseHas('forms', [
            'id' => $form->id,
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('form_triggers', [
            'form_id' => $form->id,
            'trigger_name' => 'pre_booking',
        ]);

        // 2. Publish the form
        $published = $builder->publish($form->id, $form->active_version_id, $form->lock_version);
        $this->assertSame('published', $published->status);
        $this->assertNotNull($published->published_version_id);

        // 3. Query pre-booking form
        $resolvedPreBooking = Form::where('status', 'published')
            ->whereHas('triggers', fn ($q) => $q->where('trigger_name', 'pre_booking'))
            ->first();
        $this->assertNotNull($resolvedPreBooking);
        $this->assertSame($form->id, $resolvedPreBooking->id);

        // 4. Test other trigger assignments
        $afterBookingForm = $builder->create([
            'title' => 'Post-Booking Survey',
            'slug' => 'post-booking-survey-test',
            'trigger' => 'after_booking',
            'is_mandatory' => false,
            'can_edit_after_submission' => false,
        ], $questions, $admin);
        $builder->publish($afterBookingForm->id, $afterBookingForm->active_version_id, $afterBookingForm->lock_version);

        $assignmentService = app(FormAssignmentService::class);
        $student = Student::factory()->verified()->create();

        // Student has no bookings yet, so after_booking form is not assigned
        $this->assertFalse($assignmentService->isAssignedTo($afterBookingForm, $student));

        // Create a contact and booking for the student
        $sessionType = SessionType::create([
            'title' => 'Test Session',
            'slug' => 'test-session-compat-'.uniqid(),
            'duration_minutes' => 60,
            'price' => 20,
            'currency' => 'USD',
            'active' => true,
        ]);
        $contact = Contact::create([
            'name' => 'Test Student',
            'email' => 'student-compat-'.uniqid().'@example.test',
        ]);
        $start = CarbonImmutable::now('UTC')->addDays(5)->startOfHour();
        $snapshot = app(TimezoneService::class)->createBookingSnapshot(
            $start,
            $start->addMinutes(60),
            'Africa/Cairo',
            'Africa/Cairo'
        );
        Booking::create($snapshot + [
            'student_id' => $student->id,
            'contact_id' => $contact->id,
            'session_type_id' => $sessionType->id,
            'status' => 'confirmed',
            'confirmation_token' => Str::random(64),
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        // Student now has a booking, so after_booking form is assigned
        $this->assertTrue($assignmentService->isAssignedTo($afterBookingForm, $student));
        $assigned = $assignmentService->assignedTo($student);
        $this->assertTrue($assigned->contains('id', $afterBookingForm->id));
    }

    public function test_public_booking_and_intake_work_under_intermediate_schema(): void
    {
        $admin = Administrator::create([
            'name' => 'Compatibility Admin',
            'email' => 'public-compat-admin@example.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
        ]);
        $form = app(FormBuilderService::class)->create([
            'title' => 'Public Compatibility Intake',
            'slug' => 'public-compat-intake-'.uniqid(),
            'trigger' => 'pre_booking',
            'is_mandatory' => true,
            'can_edit_after_submission' => false,
        ], [[
            'question_key' => 'experience_level',
            'label' => 'Arabic experience',
            'question_type' => 'short_text',
            'is_required' => true,
            'assistant_visible' => true,
        ]], $admin);
        $form = app(FormBuilderService::class)->publish($form->id, $form->active_version_id, $form->lock_version);

        $sessionType = SessionType::create([
            'title' => 'Compatibility Session',
            'slug' => 'public-compat-session-'.uniqid(),
            'duration_minutes' => 60,
            'price' => 20,
            'currency' => 'USD',
            'active' => true,
        ]);
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            AvailabilityRule::updateOrCreate(
                ['weekday' => $weekday],
                [
                    'start_time' => '00:00:00',
                    'end_time' => '23:59:59',
                    'session_duration_minutes' => 60,
                    'buffer_minutes' => 0,
                    'min_notice_hours' => 0,
                    'max_horizon_days' => 90,
                    'enabled' => true,
                ]
            );
        }

        $startUtc = CarbonImmutable::now('UTC')->addDays(8)->setTime(10, 0);
        $hold = app(BookingHoldService::class)->acquireHold(
            (string) Str::uuid(),
            (string) Str::uuid(),
            $sessionType,
            $startUtc,
            $startUtc->addHour(),
            60
        );
        $booking = app(BookingService::class)->createPublicBooking([
            'hold_id' => $hold->id,
            'hold_token' => $hold->hold_token,
            'visitor_token' => $hold->visitor_token,
            'session_token' => $hold->session_token,
            'session_type_id' => $sessionType->id,
            'customer_timezone' => 'Africa/Cairo',
            'first_name' => 'Schema',
            'last_name' => 'Compatibility',
            'customer_name' => 'Schema Compatibility',
            'customer_email' => 'schema-compat-student@example.test',
            'customer_phone' => '+201011223344',
            'date_of_birth' => '1992-03-04',
            'start_at_utc' => $startUtc->toIso8601String(),
            'end_at_utc' => $startUtc->addHour()->toIso8601String(),
            'idempotency_key' => Str::uuid()->toString(),
        ], ['experience_level' => 'Beginner'], (int) $form->published_version_id);

        $this->assertTrue(Schema::hasColumn('forms', 'prompt_trigger'));
        $this->assertNotNull($booking->student_id);
        $this->assertDatabaseHas('form_submissions', [
            'booking_id' => $booking->id,
            'student_id' => $booking->student_id,
            'form_version_id' => $form->published_version_id,
            'status' => 'submitted',
        ]);
        $this->assertSame(1, FormSubmission::query()->where('booking_id', $booking->id)->count());
    }
}
