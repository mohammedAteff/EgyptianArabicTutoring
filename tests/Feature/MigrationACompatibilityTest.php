<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Forms\Models\Form;
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
}
