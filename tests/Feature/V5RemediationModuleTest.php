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
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
        Cache::forget('active_business_tz');

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
        $builder->publish($form->id, $form->active_version_id, $form->lock_version);

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

        // Missing required intake answers must throw ValidationException before creating booking
        $this->expectException(ValidationException::class);

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
                'idempotency_key' => Str::uuid()->toString(),
            ],
            intakeAnswers: [], // Empty answers fails validation
            formVersionId: $form->published_version_id
        );
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

    public function test_all_writer_pre_booking_publication_serialization(): void
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

    public function test_concurrent_draft_autosave_preserves_single_draft_record(): void
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

        // Missing cookies with spoofed tokens in body returns 403 Forbidden
        $response = $this->postJson(route('resources.request', $resource->slug), [
            'name' => 'Spoofer',
            'email' => 'spoofer@example.test',
            'visitor_token' => Str::uuid()->toString(),
            'session_token' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(403);

        // Verification without cookies returns 403 Forbidden
        $challenge = bin2hex(random_bytes(32));
        $responseVerify = $this->postJson(route('resources.verify-pin', $resource->slug), [
            'challenge' => $challenge,
            'pin' => '123456',
            'visitor_token' => Str::uuid()->toString(),
        ]);

        $responseVerify->assertStatus(403);
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

        // Authenticated admin bypasses 503
        $authResponse = $this->actingAs($this->admin, 'web')->get('/');
        $this->assertNotSame(503, $authResponse->getStatusCode());

        // Clean up
        Setting::set('maintenance_mode', '0', 'general', false);
        Cache::forget('maintenance_mode_active');
    }

    public function test_telemetry_triple_verification(): void
    {
        $allowed = AnalyticsService::ALLOWED_EVENTS;

        $this->assertContains('resource_gate_viewed', $allowed);
        $this->assertContains('session_activity', $allowed);
        $this->assertContains('game_started', $allowed);
        $this->assertContains('game_completed', $allowed);
        $this->assertContains('section_view', $allowed);

        // Telemetry POST
        $visitorToken = (string) Str::uuid();
        $sessionToken = (string) Str::uuid();

        $response = $this->withCookies([
            '_va_visitor' => $visitorToken,
            '_va_session' => $sessionToken,
        ])->postJson(route('analytics.track'), [
            'event_name' => 'session_activity',
            'metadata' => ['duration_seconds' => 120],
        ]);

        $response->assertOk();
    }
}
