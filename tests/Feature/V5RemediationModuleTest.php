<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Analytics\Models\Visitor;
use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingHoldService;
use App\Domains\Booking\Services\BookingService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Services\FormBuilderService;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use App\Domains\Resources\Models\ResourceRequest;
use App\Domains\Students\Exceptions\StudentIdentityConflictException;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentIdentityService;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Middleware\EnsureNotUnderMaintenance;
use App\Livewire\BookingWizard;
use App\Mail\ResourceVerificationPinMail;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class V5RemediationModuleTest extends TestCase
{
    use RefreshDatabase;

    protected Administrator $admin;

    protected SessionType $sessionType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Administrator::create([
            'name' => 'Module Admin',
            'email' => 'admin-module@example.test',
            'password' => Hash::make('SecretPass123!'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->sessionType = SessionType::create([
            'title' => 'Diagnostic Arabic Session',
            'slug' => 'diagnostic-arabic-session-'.uniqid(),
            'duration_minutes' => 60,
            'price' => 35.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        for ($day = 0; $day <= 6; $day++) {
            AvailabilityRule::create([
                'weekday' => $day,
                'start_time' => '00:00:00',
                'end_time' => '23:59:59',
                'session_duration_minutes' => 60,
                'buffer_minutes' => 0,
                'min_notice_hours' => 0,
                'max_horizon_days' => 90,
                'enabled' => true,
            ]);
        }

        RateLimiter::clearResolvedInstances();
    }

    public function test_public_intake_uses_real_question_types_and_field_validation(): void
    {
        $form = $this->intakeForm([
            ['question_key' => 'level', 'label' => 'Experience', 'question_type' => 'dropdown', 'is_required' => true,
                'options' => [['label' => 'Beginner', 'value' => 'beginner']]],
            ['question_key' => 'notes', 'label' => 'Long notes', 'question_type' => 'long_text'],
            ['question_key' => 'interests', 'label' => 'Interests', 'question_type' => 'multiple_choice',
                'options' => [['label' => 'Travel', 'value' => 'travel']]],
            ['question_key' => 'contact', 'label' => 'Other email', 'question_type' => 'email'],
        ]);
        $question = $form->publishedVersion->questions->firstWhere('question_key', 'level');

        $wizard = $this->intakeWizard()->set('currentStep', 3)
            ->assertSeeHtml('<textarea')->assertSeeHtml('type="checkbox"')->assertSeeHtml('type="email"')
            ->assertSeeHtml('value="beginner"')
            ->set('intakeAnswers', [$question->id => 'forged'])->call('submitDetails');

        $wizard->assertHasErrors("intakeAnswers.{$question->id}")->assertSet('currentStep', 3);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_false_zero_and_hidden_required_answers_are_handled_consistently(): void
    {
        $form = $this->intakeForm([
            ['question_key' => 'studied', 'label' => 'Studied before', 'question_type' => 'yes_no', 'is_required' => true],
            ['question_key' => 'years', 'label' => 'Years', 'question_type' => 'number', 'is_required' => true],
            ['question_key' => 'course', 'label' => 'Previous course', 'question_type' => 'short_text', 'is_required' => true,
                'conditional_logic' => ['logic_version' => 1, 'mode' => 'all', 'conditions' => [
                    ['question_key' => 'studied', 'operator' => 'equals', 'value' => true],
                ]]],
            ['question_key' => 'info', 'label' => 'Information', 'question_type' => 'info_block'],
        ]);
        $questions = $form->publishedVersion->questions->keyBy('question_key');

        $this->intakeWizard()->set('currentStep', 3)->set('intakeAnswers', [
            $questions['studied']->id => '0', $questions['years']->id => 0, $questions['course']->id => 'Discard hidden value',
        ])->call('submitDetails')->assertHasNoErrors()->assertSet('currentStep', 4);

        $validated = app(BookingService::class)->validatePublicIntake($form->published_version_id, [
            'studied' => false, 'years' => 0, 'course' => 'Discard hidden value',
        ]);
        $this->assertSame(['studied' => false, 'years' => 0], $validated);
    }

    public function test_replacing_intake_during_booking_requires_review_of_the_new_form(): void
    {
        $first = $this->intakeForm([['question_key' => 'old_goal', 'label' => 'Old goal', 'question_type' => 'short_text']]);
        $wizard = $this->intakeWizard()->set('currentStep', 3)->set('intakeAnswers', ['old_goal' => 'Old response']);
        $second = $this->intakeForm([['question_key' => 'new_goal', 'label' => 'New goal', 'question_type' => 'short_text']]);

        $wizard->call('submitDetails')->assertHasErrors('intakeForm')->assertSet('currentStep', 3)
            ->assertSet('preBookingFormId', $second->id)->assertSet('intakeAnswers', [])->assertSee('New goal');
        $wizard->call('submitDetails')->assertHasNoErrors()->assertSet('currentStep', 4);

        try {
            app(BookingService::class)->validatePublicIntake($first->published_version_id, ['old_goal' => 'Old response']);
            $this->fail('A replaced intake version must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('intakeForm', $exception->errors());
        }
    }

    /** @param array<int, array<string, mixed>> $questions */
    private function intakeForm(array $questions): Form
    {
        $builder = app(FormBuilderService::class);
        $form = $builder->create([
            'title' => 'Review intake', 'slug' => 'review-intake-'.Str::lower(Str::random(10)),
            'trigger' => 'pre_booking', 'is_mandatory' => true, 'can_edit_after_submission' => false,
        ], $questions, $this->admin);

        return $builder->publish($form->id, $form->active_version_id, $form->lock_version);
    }

    private function intakeWizard(): Testable
    {
        return Livewire::test(BookingWizard::class)->set('first_name', 'Review')->set('last_name', 'Learner')
            ->set('email', 'review@example.test')->set('phone', '+201012345678')->set('date_of_birth', '1990-01-01');
    }

    public function test_pin_issuance_queues_mail_and_verification_unlocks_a_localized_download(): void
    {
        Mail::fake();
        Config::set('business.resources.require_pin_verification', true);
        Config::set('cache.default', 'database');
        $category = ResourceCategory::create(['name' => 'PIN review', 'slug' => 'pin-review', 'active' => true]);
        $resource = Resource::create([
            'title' => 'PIN review guide', 'slug' => 'pin-review-guide', 'category_id' => $category->id,
            'status' => 'published', 'published_at' => now()->subDay(), 'is_gated' => true,
            'external_url' => 'https://example.org/guide.pdf',
        ]);
        $page = $this->get(route('resources.show.fr', $resource->slug));
        $this->withCredentials()->withCookies([
            '_va_visitor' => $page->getCookie('_va_visitor')->getValue(),
            '_va_session' => $page->getCookie('_va_session')->getValue(),
        ]);

        $issued = $this->postJson(route('resources.request.fr', $resource->slug), ['name' => 'Review learner', 'email' => 'pin-review@example.test']);
        $issued->assertOk()->assertJsonPath('requires_pin', true);
        $challenge = $issued->json('challenge');
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $challenge);
        $this->assertDatabaseCount('resource_requests', 0);
        $pin = null;
        Mail::assertQueued(ResourceVerificationPinMail::class, function ($mail) use (&$pin): bool {
            $pin = $mail->pin;

            return $mail->hasTo('pin-review@example.test');
        });
        $this->assertMatchesRegularExpression('/\A[0-9]{6}\z/', $pin);

        $verified = $this->postJson(route('resources.verify-pin.fr', $resource->slug), ['challenge' => $challenge, 'pin' => $pin]);
        $verified->assertOk();
        $this->assertStringStartsWith(route('resources.download.fr', $resource->slug), $verified->json('download_url'));
        $this->assertDatabaseHas('resource_requests', ['resource_id' => $resource->id, 'consumed_challenge_hash' => hash('sha256', $challenge)]);
        $this->withCookie(config('session.cookie'), $verified->getCookie(config('session.cookie'))->getValue());
        $this->get($verified->json('download_url'))->assertRedirect('https://example.org/guide.pdf');
        $this->assertDatabaseHas('resource_downloads', ['resource_id' => $resource->id]);
    }

    public function test_broken_student_merge_chains_are_rejected_instead_of_linked(): void
    {
        $student = Student::factory()->create(['identity_status' => 'merged', 'merged_into_student_id' => null]);

        $this->expectException(StudentIdentityConflictException::class);
        app(StudentIdentityService::class)->resolveCanonicalStudent($student);
    }

    public function test_dynamic_tutor_timezone_and_customer_timezone_isolation(): void
    {
        Setting::set('business_timezone', 'Africa/Cairo', 'booking', true);
        Cache::forget('active_business_tz');

        $startUtc = CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC');
        $endUtc = $startUtc->addMinutes(60);

        $snapshot = app(TimezoneService::class)->createBookingSnapshot(
            $startUtc,
            $endUtc,
            'America/New_York',
            'Africa/Cairo'
        );

        $contact = Contact::create([
            'name' => 'Timezone Learner',
            'email' => 'tz-learner@example.test',
        ]);

        $booking = Booking::create($snapshot + [
            'contact_id' => $contact->id,
            'session_type_id' => $this->sessionType->id,
            'status' => 'confirmed',
            'confirmation_token' => Str::random(64),
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        $this->assertSame('Africa/Cairo', $booking->business_start->timezoneName);
        $this->assertSame('America/New_York', $booking->customer_start->timezoneName);
        $this->assertSame('12:00:00', $booking->start_at_utc->format('H:i:s'));

        // Update business_timezone to Asia/Tokyo
        Setting::set('business_timezone', 'Asia/Tokyo', 'booking', true);

        $booking->refresh();

        // start_at_utc is completely untouched
        $this->assertSame('12:00:00', $booking->start_at_utc->format('H:i:s'));
        // customer_start is completely isolated and untouched
        $this->assertSame('America/New_York', $booking->customer_start->timezoneName);
        $this->assertSame('08:00:00', $booking->customer_start->format('H:i:s'));
        // business_start dynamically reflects Asia/Tokyo (UTC 12:00 + 9 hours = 21:00)
        $this->assertSame('Asia/Tokyo', $booking->business_start->timezoneName);
        $this->assertSame('21:00:00', $booking->business_start->format('H:i:s'));
    }

    public function test_public_booking_student_bridge_assigns_legacy_unverified_without_duplicates(): void
    {
        $startUtc = CarbonImmutable::now('UTC')->addDays(3)->startOfHour();
        $endUtc = $startUtc->addMinutes(60);
        $visitorToken = (string) Str::uuid();
        $sessionToken = (string) Str::uuid();

        $hold = app(BookingHoldService::class)->acquireHold(
            $visitorToken,
            $sessionToken,
            $this->sessionType,
            $startUtc,
            $endUtc,
            60
        );

        $bookingService = app(BookingService::class);

        $booking1 = $bookingService->createPublicBooking([
            'hold_id' => $hold->id,
            'hold_token' => $hold->hold_token,
            'visitor_token' => $hold->visitor_token,
            'session_token' => $hold->session_token,
            'customer_name' => 'Farouk Ahmed',
            'first_name' => 'Farouk',
            'last_name' => 'Ahmed',
            'date_of_birth' => '1995-05-12',
            'customer_email' => 'farouk.ahmed@example.test',
            'customer_phone' => '+201011112222',
            'customer_timezone' => 'Africa/Cairo',
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $startUtc->toIso8601String(),
            'end_at_utc' => $endUtc->toIso8601String(),
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        $this->assertNotNull($booking1->student_id);
        $student = Student::find($booking1->student_id);
        $this->assertNotNull($student);
        $this->assertSame('legacy_unverified', $student->identity_status);
        $this->assertSame(1, Student::count());

        // Second booking for the same learner
        $startUtc2 = CarbonImmutable::now('UTC')->addDays(5)->startOfHour();
        $endUtc2 = $startUtc2->addMinutes(60);
        $sessionToken2 = (string) Str::uuid();
        $hold2 = app(BookingHoldService::class)->acquireHold(
            $visitorToken,
            $sessionToken2,
            $this->sessionType,
            $startUtc2,
            $endUtc2,
            60
        );

        $booking2 = $bookingService->createPublicBooking([
            'hold_id' => $hold2->id,
            'hold_token' => $hold2->hold_token,
            'visitor_token' => $hold2->visitor_token,
            'session_token' => $hold2->session_token,
            'customer_name' => 'Farouk Ahmed',
            'first_name' => 'Farouk',
            'last_name' => 'Ahmed',
            'date_of_birth' => '1995-05-12',
            'customer_email' => 'farouk.ahmed@example.test',
            'customer_phone' => '+201011112222',
            'customer_timezone' => 'Africa/Cairo',
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $startUtc2->toIso8601String(),
            'end_at_utc' => $endUtc2->toIso8601String(),
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        $this->assertSame($booking1->student_id, $booking2->student_id);
        $this->assertSame(1, Student::count());
    }

    public function test_intake_validation_precedence_and_rollback(): void
    {
        $builder = app(FormBuilderService::class);
        $questions = [
            [
                'question_key' => 'required_goal',
                'label' => 'Primary Learning Objective',
                'question_type' => 'short_text',
                'is_required' => true,
                'assistant_visible' => true,
            ],
        ];

        $form = $builder->create([
            'title' => 'Required Intake Form',
            'slug' => 'required-intake-'.uniqid(),
            'trigger' => 'pre_booking',
            'is_mandatory' => true,
            'can_edit_after_submission' => false,
        ], $questions, $this->admin);
        $form = $builder->publish($form->id, $form->active_version_id, $form->lock_version);

        $startUtc = CarbonImmutable::now('UTC')->addDays(4)->startOfHour();
        $endUtc = $startUtc->addMinutes(60);
        $visitorToken = (string) Str::uuid();
        $sessionToken = (string) Str::uuid();

        $hold = app(BookingHoldService::class)->acquireHold(
            $visitorToken,
            $sessionToken,
            $this->sessionType,
            $startUtc,
            $endUtc,
            60
        );

        $bookingService = app(BookingService::class);

        $bookingKey = Str::uuid()->toString();
        $identity = app(StudentIdentityService::class)->normalizeIdentity(
            'intake@example.test',
            '+201099887766',
            'Intake',
            'Student'
        );
        $lockKey = substr(DB::connection()->getDatabaseName(), 0, 20).':stu:'.substr(hash('sha256', $identity), 0, 32);
        $validationFailed = false;

        try {
            $bookingService->createPublicBooking(
                [
                    'hold_id' => $hold->id,
                    'hold_token' => $hold->hold_token,
                    'visitor_token' => $hold->visitor_token,
                    'session_token' => $hold->session_token,
                    'customer_name' => 'Intake Student',
                    'first_name' => 'Intake',
                    'last_name' => 'Student',
                    'date_of_birth' => '2000-01-01',
                    'customer_email' => 'intake@example.test',
                    'customer_phone' => '+201099887766',
                    'customer_timezone' => 'Africa/Cairo',
                    'session_type_id' => $this->sessionType->id,
                    'start_at_utc' => $startUtc->toIso8601String(),
                    'end_at_utc' => $endUtc->toIso8601String(),
                    'idempotency_key' => $bookingKey,
                ],
                intakeAnswers: [],
                formVersionId: $form->published_version_id
            );
        } catch (ValidationException) {
            $validationFailed = true;
        }

        $this->assertTrue($validationFailed, 'Missing mandatory intake answers must fail validation.');
        $this->assertDatabaseMissing('bookings', ['idempotency_key' => $bookingKey]);
        $this->assertDatabaseMissing('contacts', ['email' => 'intake@example.test']);
        $this->assertDatabaseMissing('students', ['email_normalized' => 'intake@example.test']);
        $this->assertSame('active', $hold->fresh()->status);
        $lockState = DB::connection()->selectOne('SELECT IS_USED_LOCK(?) AS owner', [$lockKey], useReadPdo: false);
        $this->assertNull($lockState?->owner, 'Intake validation must happen before acquiring the named identity lock.');
    }

    public function test_intake_answer_persistence_failure_rolls_back_booking_and_submission(): void
    {
        $builder = app(FormBuilderService::class);
        $form = $builder->create([
            'title' => 'Persistence Rollback Form',
            'slug' => 'persistence-rollback-'.uniqid(),
            'trigger' => 'pre_booking',
            'is_mandatory' => false,
            'can_edit_after_submission' => false,
        ], [[
            'question_key' => 'goal',
            'label' => 'Learning goal',
            'question_type' => 'short_text',
            'is_required' => false,
            'assistant_visible' => true,
        ]], $this->admin);
        $form = $builder->publish($form->id, $form->active_version_id, $form->lock_version);

        $startUtc = CarbonImmutable::now('UTC')->addDays(6)->startOfHour();
        $hold = app(BookingHoldService::class)->acquireHold(
            (string) Str::uuid(),
            (string) Str::uuid(),
            $this->sessionType,
            $startUtc,
            $startUtc->addHour(),
            60
        );
        $bookingKey = Str::uuid()->toString();
        $submissionCountBefore = FormSubmission::query()->where('form_version_id', $form->published_version_id)->count();
        $answerWriteFailed = true;

        DB::connection()->beforeExecuting(function (string $query) use (&$answerWriteFailed): void {
            if ($answerWriteFailed && str_contains(strtolower($query), 'form_answers')) {
                $answerWriteFailed = false;
                throw new \RuntimeException('Simulated intake answer persistence failure.');
            }
        });

        $caughtFailure = false;
        try {
            app(BookingService::class)->createPublicBooking([
                'hold_id' => $hold->id,
                'hold_token' => $hold->hold_token,
                'visitor_token' => $hold->visitor_token,
                'session_token' => $hold->session_token,
                'customer_name' => 'Rollback Student',
                'first_name' => 'Rollback',
                'last_name' => 'Student',
                'date_of_birth' => '1990-01-01',
                'customer_email' => 'rollback-student@example.test',
                'customer_phone' => '+201012345678',
                'customer_timezone' => 'Africa/Cairo',
                'session_type_id' => $this->sessionType->id,
                'start_at_utc' => $startUtc->toIso8601String(),
                'end_at_utc' => $startUtc->addHour()->toIso8601String(),
                'idempotency_key' => $bookingKey,
            ], ['goal' => 'Speak confidently'], $form->published_version_id);
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated intake answer persistence failure.', $exception->getMessage());
            $caughtFailure = true;
        }

        $this->assertTrue($caughtFailure, 'The injected answer persistence failure must be observed.');
        $this->assertDatabaseMissing('bookings', ['idempotency_key' => $bookingKey]);
        $this->assertDatabaseMissing('contacts', ['email' => 'rollback-student@example.test']);
        $this->assertDatabaseMissing('students', ['email_normalized' => 'rollback-student@example.test']);
        $this->assertSame(
            $submissionCountBefore,
            FormSubmission::query()->where('form_version_id', $form->published_version_id)->count()
        );
        $this->assertSame('active', $hold->fresh()->status);
        $this->assertFalse($answerWriteFailed, 'The failure hook must be inert after its one simulated failure.');
    }

    public function test_cross_version_question_rejection(): void
    {
        $builder = app(FormBuilderService::class);
        $questions = [
            [
                'question_key' => 'valid_goal',
                'label' => 'Valid Goal',
                'question_type' => 'short_text',
                'is_required' => false,
                'assistant_visible' => true,
            ],
        ];

        $form = $builder->create([
            'title' => 'Version Isolation Form',
            'slug' => 'version-isolation-'.uniqid(),
            'trigger' => 'pre_booking',
            'is_mandatory' => true,
            'can_edit_after_submission' => false,
        ], $questions, $this->admin);
        $builder->publish($form->id, $form->active_version_id, $form->lock_version);

        $startUtc = CarbonImmutable::now('UTC')->addDays(6)->startOfHour();
        $endUtc = $startUtc->addMinutes(60);
        $visitorToken = (string) Str::uuid();
        $sessionToken = (string) Str::uuid();

        $hold = app(BookingHoldService::class)->acquireHold(
            $visitorToken,
            $sessionToken,
            $this->sessionType,
            $startUtc,
            $endUtc,
            60
        );

        $bookingService = app(BookingService::class);

        // Submitting an invalid question ID not belonging to the version
        $this->expectException(ValidationException::class);

        $bookingService->createPublicBooking(
            [
                'hold_id' => $hold->id,
                'hold_token' => $hold->hold_token,
                'visitor_token' => $hold->visitor_token,
                'session_token' => $hold->session_token,
                'customer_name' => 'Question Test',
                'first_name' => 'Question',
                'last_name' => 'Test',
                'date_of_birth' => '1998-04-05',
                'customer_email' => 'qtest@example.test',
                'customer_phone' => '+201088776655',
                'customer_timezone' => 'Africa/Cairo',
                'session_type_id' => $this->sessionType->id,
                'start_at_utc' => $startUtc->toIso8601String(),
                'end_at_utc' => $endUtc->toIso8601String(),
                'idempotency_key' => Str::uuid()->toString(),
            ],
            intakeAnswers: [99999 => 'Cross-version value'],
            formVersionId: $form->published_version_id
        );
    }

    public function test_pre_booking_publication_replaces_the_active_trigger(): void
    {
        $builder = app(FormBuilderService::class);
        $questions = [
            ['question_key' => 'q1', 'label' => 'Question 1', 'question_type' => 'short_text', 'is_required' => false, 'assistant_visible' => true],
        ];

        $form1 = $builder->create([
            'title' => 'Form One',
            'slug' => 'form-one-'.uniqid(),
            'trigger' => 'pre_booking',
            'is_mandatory' => true,
            'can_edit_after_submission' => false,
        ], $questions, $this->admin);
        $builder->publish($form1->id, $form1->active_version_id, $form1->lock_version);

        $this->assertTrue($form1->triggers()->where('trigger_name', 'pre_booking')->exists());

        $form2 = $builder->create([
            'title' => 'Form Two',
            'slug' => 'form-two-'.uniqid(),
            'trigger' => 'pre_booking',
            'is_mandatory' => true,
            'can_edit_after_submission' => false,
        ], $questions, $this->admin);
        $builder->publish($form2->id, $form2->active_version_id, $form2->lock_version);

        // Form 1 pre_booking trigger was atomically removed
        $this->assertFalse($form1->triggers()->where('trigger_name', 'pre_booking')->exists());
        // Form 2 now has the pre_booking trigger
        $this->assertTrue($form2->triggers()->where('trigger_name', 'pre_booking')->exists());
        // Exactly one form has pre_booking
        $this->assertSame(1, DB::table('form_triggers')->where('trigger_name', 'pre_booking')->count());
    }

    public function test_publishing_one_intake_preserves_another_drafts_trigger_selection(): void
    {
        $builder = app(FormBuilderService::class);
        $questions = [['question_key' => 'goal', 'label' => 'Goal', 'question_type' => 'short_text']];
        $first = $builder->create(['title' => 'First draft', 'slug' => 'first-review-draft', 'trigger' => 'pre_booking'], $questions, $this->admin);
        $second = $builder->create(['title' => 'Second draft', 'slug' => 'second-review-draft', 'trigger' => 'pre_booking'], $questions, $this->admin);

        $builder->publish($first->id, $first->active_version_id, $first->lock_version);

        $this->assertTrue($second->triggers()->where('trigger_name', 'pre_booking')->exists());
        $builder->publish($second->id, $second->active_version_id, $second->lock_version);
        $this->assertSame($second->id, app(BookingService::class)->publishedIntakeForm()->id);
        $this->assertFalse($first->triggers()->where('trigger_name', 'pre_booking')->exists());
    }

    public function test_form_author_immutability(): void
    {
        $admin2 = Administrator::create([
            'name' => 'Second Admin',
            'email' => 'admin2@example.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $builder = app(FormBuilderService::class);
        $questions = [
            ['question_key' => 'goal', 'label' => 'Goal', 'question_type' => 'short_text', 'is_required' => false, 'assistant_visible' => true],
        ];

        $form = $builder->create([
            'title' => 'Immutable Author Form',
            'slug' => 'immutable-author-'.uniqid(),
            'trigger' => 'none',
            'is_mandatory' => false,
            'can_edit_after_submission' => false,
        ], $questions, $this->admin);

        $this->assertSame($this->admin->id, $form->created_by);

        // Update with another admin
        $updatedForm = $builder->update(
            $form->id,
            [
                'title' => 'Updated Form Title',
                'slug' => $form->slug,
                'trigger' => 'none',
                'is_mandatory' => false,
                'can_edit_after_submission' => false,
                'created_by' => $admin2->id, // Attempt to overwrite author
            ],
            $questions,
            $form->active_version_id,
            $form->lock_version
        );

        $this->assertSame($this->admin->id, $updatedForm->created_by);
        $this->assertSame($this->admin->id, $form->fresh()->created_by);
    }

    public function test_repeated_draft_autosave_preserves_single_draft_record(): void
    {
        $builder = app(FormBuilderService::class);
        $questions = [
            ['question_key' => 'notes', 'label' => 'Study Notes', 'question_type' => 'long_text', 'is_required' => false, 'assistant_visible' => true],
        ];

        $form = $builder->create([
            'title' => 'Student Study Form',
            'slug' => 'student-study-form-'.uniqid(),
            'trigger' => 'none',
            'is_mandatory' => false,
            'can_edit_after_submission' => true,
        ], $questions, $this->admin);
        $builder->publish($form->id, $form->active_version_id, $form->lock_version);
        $form->refresh();

        $student = Student::factory()->verified()->create();

        $sessionData = [
            'student_id' => $student->id,
            'student_authenticated_at' => now('UTC')->toIso8601String(),
            'student_auth_expires_at' => now('UTC')->addMinutes(180)->toIso8601String(),
        ];

        // 1st autosave
        $response1 = $this->actingAs($student, 'student')
            ->withSession($sessionData)
            ->postJson(route('student.forms.autosave', $form->slug), [
                'answers' => ['notes' => 'Draft attempt 1'],
            ]);
        $response1->assertOk()->assertJson(['success' => true]);

        // 2nd autosave
        $response2 = $this->actingAs($student, 'student')
            ->withSession($sessionData)
            ->postJson(route('student.forms.autosave', $form->slug), [
                'answers' => ['notes' => 'Draft attempt 2 updated'],
            ]);
        $response2->assertOk()->assertJson(['success' => true]);

        // Exactly 1 draft submission exists
        $submissions = FormSubmission::where('student_id', $student->id)
            ->where('form_version_id', $form->published_version_id)
            ->where('status', 'draft')
            ->get();

        $this->assertCount(1, $submissions);
        $this->assertSame('Draft attempt 2 updated', $submissions->first()->answers()->first()->value_text);
    }

    public function test_cookie_authority_and_parameter_neutrality(): void
    {
        Config::set('cache.default', 'database');

        $category = ResourceCategory::create([
            'slug' => 'guides-'.uniqid(),
            'name' => 'Guides',
            'sort_order' => 1,
            'active' => true,
        ]);

        $resource = Resource::create([
            'title' => 'Free Arabic Starter Guide',
            'slug' => 'free-arabic-guide-'.uniqid(),
            'category_id' => $category->id,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'sort_order' => 1,
        ]);

        // Missing cookies with spoofed tokens in body returns 403 Forbidden.
        $response = $this->postJson(route('resources.request', $resource->slug), [
            'name' => 'Spoofer',
            'email' => 'spoofer@example.test',
            'visitor_token' => Str::uuid()->toString(),
            'session_token' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(403);

        $visitorToken = (string) Str::uuid();
        $sessionToken = (string) Str::uuid();
        $spoofedToken = (string) Str::uuid();
        $requestUrl = route('resources.request', $resource->slug).'?'.http_build_query([
            'visitor_token' => $spoofedToken,
            'session_token' => $spoofedToken,
        ]);
        $this->withCredentials()->withCookies([
            '_va_visitor' => $visitorToken,
            '_va_session' => $sessionToken,
        ])->postJson($requestUrl, [
            'name' => 'Cookie Owner',
            'email' => 'cookie-owner@example.test',
            'visitor_token' => $spoofedToken,
            'session_token' => $spoofedToken,
        ])->assertOk()->assertJson(['requires_pin' => false]);

        $storedRequest = ResourceRequest::query()->where('resource_id', $resource->id)->firstOrFail();
        $this->assertSame($visitorToken, $storedRequest->visitor_token);
        $this->assertSame($sessionToken, $storedRequest->session_token);

        // Verification without cookies returns 403 even when tokens are supplied in the body.
        $challenge = bin2hex(random_bytes(32));
        $responseVerify = $this->postJson(route('resources.verify-pin', $resource->slug), [
            'challenge' => $challenge,
            'pin' => '123456',
            'visitor_token' => $spoofedToken,
            'session_token' => $spoofedToken,
        ]);

        $responseVerify->assertStatus(403);

        Config::set('business.resources.require_pin_verification', true);
        $challenge = bin2hex(random_bytes(32));
        $challengeHash = hash('sha256', $challenge);
        Cache::put("resource_pin:{$challenge}", [
            'hash' => Hash::make('123456'),
            'name' => 'Cookie Owner',
            'email' => 'cookie-owner@example.test',
            'resource_id' => $resource->id,
            'visitor_token' => $visitorToken,
            'session_token' => $sessionToken,
            'attribution' => [],
            'attempts' => 0,
        ], now()->addMinutes(10));

        $verifyUrl = route('resources.verify-pin', $resource->slug).'?'.http_build_query([
            'visitor_token' => $spoofedToken,
            'session_token' => $spoofedToken,
        ]);
        $this->withCredentials()->withCookies([
            '_va_visitor' => $visitorToken,
            '_va_session' => $sessionToken,
        ])->postJson($verifyUrl, [
            'challenge' => $challenge,
            'pin' => '123456',
            'visitor_token' => $spoofedToken,
            'session_token' => $spoofedToken,
        ])->assertOk();

        $this->assertDatabaseHas('resource_requests', [
            'resource_id' => $resource->id,
            'visitor_token' => $visitorToken,
            'session_token' => $sessionToken,
            'consumed_challenge_hash' => $challengeHash,
        ]);
    }

    public function test_granular_challenge_hash_replay_and_race_verification(): void
    {
        Config::set('cache.default', 'database');
        Config::set('business.resources.require_pin_verification', true);

        $category = ResourceCategory::create([
            'slug' => 'workbooks-'.uniqid(),
            'name' => 'Workbooks',
            'sort_order' => 1,
            'active' => true,
        ]);

        $resource = Resource::create([
            'title' => 'Gated Workbook',
            'slug' => 'gated-workbook-'.uniqid(),
            'category_id' => $category->id,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'sort_order' => 1,
        ]);

        $visitorToken = (string) Str::uuid();
        $sessionToken = (string) Str::uuid();
        $challenge = bin2hex(random_bytes(32));
        $challengeHash = hash('sha256', $challenge);

        $visitor = Visitor::create([
            'visitor_token' => $visitorToken,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'device_type' => 'desktop',
            'user_agent' => 'PHPUnit',
            'is_bot' => false,
        ]);
        VisitorSession::create([
            'session_token' => $sessionToken,
            'visitor_id' => $visitor->id,
            'started_at' => now(),
            'last_activity_at' => now(),
            'is_bot' => false,
        ]);

        // Mark challenge as already consumed
        $contact = Contact::create(['name' => 'Test Lead', 'email' => 'lead@example.test']);
        ResourceRequest::create([
            'resource_id' => $resource->id,
            'contact_id' => $contact->id,
            'visitor_token' => $visitorToken,
            'session_token' => $sessionToken,
            'consumed_challenge_hash' => $challengeHash,
        ]);

        // Ordinary replay: Submitting an already consumed challenge hash is intercepted by the locked pre-check and returns 409 Conflict
        $response = $this->withCredentials()->withCookies([
            '_va_visitor' => $visitorToken,
            '_va_session' => $sessionToken,
        ])->postJson(route('resources.verify-pin', $resource->slug), [
            'challenge' => $challenge,
            'pin' => '123456',
        ]);

        $response->assertStatus(409);

        // Concurrent collision: Bypassing the pre-check under a simulated concurrent write race triggers HTTP 409 Conflict
        // specifically from the database catch block on resource_requests_consumed_challenge_hash_unique
        $challenge2 = bin2hex(random_bytes(32));
        $challengeHash2 = hash('sha256', $challenge2);
        Cache::put("resource_pin:{$challenge2}", [
            'hash' => Hash::make('123456'),
            'name' => 'Race Lead',
            'email' => 'race@example.test',
            'resource_id' => $resource->id,
            'visitor_token' => $visitorToken,
            'session_token' => $sessionToken,
            'attribution' => [],
            'attempts' => 0,
        ], now()->addMinutes(10));

        $inserted = false;
        ResourceRequest::creating(function () use ($resource, $contact, $visitorToken, $sessionToken, $challengeHash2, &$inserted) {
            if (! $inserted) {
                $inserted = true;
                ResourceRequest::withoutEvents(function () use ($resource, $contact, $visitorToken, $sessionToken, $challengeHash2) {
                    ResourceRequest::create([
                        'resource_id' => $resource->id,
                        'contact_id' => $contact->id,
                        'visitor_token' => $visitorToken,
                        'session_token' => $sessionToken,
                        'consumed_challenge_hash' => $challengeHash2,
                        'created_at' => now(),
                    ]);
                });
            }
        });

        $responseCollision = $this->withCredentials()->withCookies([
            '_va_visitor' => $visitorToken,
            '_va_session' => $sessionToken,
        ])->postJson(route('resources.verify-pin', $resource->slug), [
            'challenge' => $challenge2,
            'pin' => '123456',
        ]);

        $responseCollision->assertStatus(409);
    }

    public function test_immediate_fifth_pin_attempt_invalidation(): void
    {
        Config::set('cache.default', 'database');
        Config::set('business.resources.require_pin_verification', true);

        $category = ResourceCategory::create([
            'slug' => 'pin-cat-'.uniqid(),
            'name' => 'PIN Category',
            'sort_order' => 1,
            'active' => true,
        ]);

        $resource = Resource::create([
            'title' => 'PIN Resource',
            'slug' => 'pin-resource-'.uniqid(),
            'category_id' => $category->id,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'sort_order' => 1,
        ]);

        $visitorToken = (string) Str::uuid();
        $sessionToken = (string) Str::uuid();
        $challenge = bin2hex(random_bytes(32));
        $realPin = '654321';

        $visitor = Visitor::create([
            'visitor_token' => $visitorToken,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'device_type' => 'desktop',
            'user_agent' => 'PHPUnit',
            'is_bot' => false,
        ]);
        VisitorSession::create([
            'session_token' => $sessionToken,
            'visitor_id' => $visitor->id,
            'started_at' => now(),
            'last_activity_at' => now(),
            'is_bot' => false,
        ]);

        Cache::put("resource_pin:{$challenge}", [
            'hash' => Hash::make($realPin),
            'name' => 'PIN User',
            'email' => 'pinuser@example.test',
            'resource_id' => $resource->id,
            'visitor_token' => $visitorToken,
            'session_token' => $sessionToken,
            'attribution' => [],
            'attempts' => 4, // 4 attempts already made; next is 5th
        ], now()->addMinutes(10));

        // Submit wrong pin on 5th attempt
        $response = $this->withCredentials()->withCookies([
            '_va_visitor' => $visitorToken,
            '_va_session' => $sessionToken,
        ])->postJson(route('resources.verify-pin', $resource->slug), [
            'challenge' => $challenge,
            'pin' => '000000',
        ]);

        $response->assertStatus(429);
        $this->assertNull(Cache::get("resource_pin:{$challenge}"));
    }

    public function test_maintenance_mode_boundary(): void
    {
        Setting::set('maintenance_mode', '1', 'general', false);
        Cache::forget('maintenance_mode_active');

        // Guest to public page gets 503
        $response = $this->get('/');
        $response->assertStatus(503);

        // Guest to admin login bypasses 503
        $adminLogin = $this->get('/admin/login');
        $adminLogin->assertStatus(200);

        $health = $this->get(route('admin.health'));
        $this->assertNotSame(503, $health->getStatusCode());

        Route::middleware('web')->get('/build/maintenance-probe', fn () => response('asset'));
        $this->get('/build/maintenance-probe')->assertOk()->assertSeeText('asset');
        $storageResponse = app(EnsureNotUnderMaintenance::class)->handle(
            Request::create('/storage/maintenance-probe'),
            fn () => response('asset'),
        );
        $this->assertSame('asset', $storageResponse->getContent());

        // Authenticated admin bypasses 503
        $authResponse = $this->actingAs($this->admin, 'web')->get('/');
        $this->assertNotSame(503, $authResponse->getStatusCode());

        // Clean up
        Setting::set('maintenance_mode', '0', 'general', false);
        Cache::forget('maintenance_mode_active');
    }

    public function test_resource_gate_telemetry_is_allowlisted_and_excluded_for_admins_and_previews(): void
    {
        $allowed = AnalyticsService::ALLOWED_EVENTS;

        $this->assertContains('resource_gate_viewed', $allowed);
        $this->assertContains('session_activity', $allowed);
        $this->assertContains('game_started', $allowed);
        $this->assertContains('game_completed', $allowed);
        $this->assertContains('section_view', $allowed);

        $category = ResourceCategory::create([
            'slug' => 'telemetry-'.uniqid(),
            'name' => 'Telemetry',
            'sort_order' => 1,
            'active' => true,
        ]);
        $resource = Resource::create([
            'title' => 'Telemetry Resource',
            'slug' => 'telemetry-resource-'.uniqid(),
            'category_id' => $category->id,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'sort_order' => 1,
        ]);

        $this->get(route('resources.show', $resource->slug))
            ->assertOk()
            ->assertSee('data-analytics-event="resource_gate_viewed"', false);

        $this->actingAs($this->admin, 'web')
            ->get(route('resources.show', $resource->slug))
            ->assertOk()
            ->assertDontSee('data-analytics-event="resource_gate_viewed"', false);

        $this->get(route('resources.preview', $resource->slug))
            ->assertOk()
            ->assertDontSee('data-analytics-event="resource_gate_viewed"', false);

        $telemetryScript = file_get_contents(resource_path('js/analytics-telemetry.js'));
        $resourceView = file_get_contents(resource_path('views/public/resources/show.blade.php'));
        $this->assertStringContainsString("querySelectorAll('[data-analytics-event]')", $telemetryScript);
        $this->assertStringContainsString('data-analytics-event="resource_gate_viewed"', $resourceView);
        $this->assertStringContainsString("'section_view'", $telemetryScript);

        // Telemetry POST
        Auth::guard('web')->logout();
        $page = $this->get(route('resources.show', $resource->slug));
        $visitorToken = $page->getCookie('_va_visitor')->getValue();
        $sessionToken = $page->getCookie('_va_session')->getValue();

        $response = $this->withCredentials()->withCookies([
            '_va_visitor' => $visitorToken,
            '_va_session' => $sessionToken,
        ])->postJson(route('analytics.track'), [
            'event_name' => 'resource_gate_viewed',
            'metadata' => ['resource_slug' => $resource->slug],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'resource_gate_viewed', 'visitor_token' => $visitorToken]);
    }

    public function test_resource_gate_telemetry_script_dispatches_once_when_the_page_mounts(): void
    {
        $script = <<<'JS'
const fs = require('node:fs');
const vm = require('node:vm');
const calls = [];
const element = {
    dataset: {analyticsEvent: 'resource_gate_viewed', analyticsMetadata: '{"resource_slug":"guide"}'},
    removeAttribute(name) { delete this.dataset[name === 'data-analytics-event' ? 'analyticsEvent' : 'analyticsMetadata']; }
};
const document = {
    readyState: 'complete', visibilityState: 'visible', addEventListener() {},
    querySelector(selector) {
        if (selector === 'meta[name="analytics-event-url"]') return {content: '/analytics/track'};
        if (selector === 'meta[name="csrf-token"]') return {getAttribute: () => 'csrf'};
        return null;
    },
    querySelectorAll(selector) {
        return selector === '[data-analytics-event]' && element.dataset.analyticsEvent ? [element] : [];
    }
};
const context = vm.createContext({
    document, window: {location: {pathname: '/resources/guide', href: 'https://example.test/resources/guide'}, addEventListener() {}},
    fetch(url, options) { calls.push(JSON.parse(options.body)); return Promise.resolve({ok: true}); },
    setInterval() { return 1; }, clearInterval() {}, crypto: {randomUUID: () => '12345678-1234-4234-8234-123456789abc'}
});
const source = fs.readFileSync(process.argv[1], 'utf8');
vm.runInContext(source, context);
vm.runInContext(source, context);
process.stdout.write(JSON.stringify(calls.flatMap(call => call.events)));
JS;
        $process = new Process(['node', '-e', $script, resource_path('js/analytics-telemetry.js')]);
        $process->mustRun();
        $events = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertCount(1, $events);
        $this->assertSame('resource_gate_viewed', $events[0]['event_name']);
        $this->assertSame('guide', $events[0]['metadata']['resource_slug']);
    }
}
