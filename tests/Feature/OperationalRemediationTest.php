<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\BookingService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Services\FormBuilderService;
use App\Domains\Resources\Services\EmailQualityService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentIdentityService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Timezone\Services\TimezoneDisplayService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OperationalRemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_preset_discount_and_manual_refund_use_the_same_net_invoice(): void
    {
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'Foundation Coaching Track', 8, '280.00', '25.00', 'USD', null, 'discounted-preset', presetKey: 'foundation_track', entitlementCode: 'one_hour');
        $this->assertSame('255.00', $package->final_price);
        $ledger->recordPayment($package, '255.00', 'first-payment', null);
        $this->assertSame('0.00', $ledger->summary($package)['balance_due']);
        $payment = $ledger->recordPayment($package, '255.00', 'second-payment', null);
        $summary = $ledger->summary($package);
        $this->assertSame('0.00', $summary['balance_due']);
        $this->assertSame('255.00', $summary['overpaid']);
        $ledger->refund($payment, '255.00', 'manual-refund', null, 'Overpayment correction', 0);
        $this->assertSame('0.00', $ledger->summary($package)['overpaid']);
        $this->assertSame('255.00', $ledger->summary($package)['net_paid']);
    }

    public function test_administrator_preferences_are_personal_and_student_time_is_always_twelve_hour(): void
    {
        $first = AdministratorFactory::new()->create(['time_format' => '24']);
        $second = AdministratorFactory::new()->create(['time_format' => '24']);
        $instant = CarbonImmutable::parse('2026-10-02 14:05:00', 'Africa/Cairo');
        $display = app(TimezoneDisplayService::class);
        $this->actingAs($first, 'web')->post(route('admin.preferences.time'), ['time_format' => '12'])->assertRedirect();
        $this->assertSame('24', $second->fresh()->time_format);
        $this->assertSame('2:05 PM', $display->administratorTime($instant));
        $this->actingAs($second, 'web');
        $this->assertSame('14:05', $display->administratorTime($instant));
        $this->assertSame('2:05 PM', $display->studentTime($instant));
    }

    public function test_national_and_international_phone_variants_match_with_a_second_identifier(): void
    {
        $student = Student::factory()->verified()->create(['email' => 'learner@example.com', 'email_normalized' => 'learner@example.com', 'phone_normalized' => '+201012345678']);
        foreach (['+201012345678', '201012345678', '01012345678', '00201012345678', '+20 (10) 1234-5678'] as $phone) {
            $this->assertTrue(app(StudentIdentityService::class)->phoneMatches($phone, $student->phone_normalized), $phone);
        }
        $this->post(route('student.login.submit'), ['date_of_birth' => '1990-01-01', 'email' => $student->email, 'phone' => '010 1234 5678'])->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($student, 'student');
        $this->assertFalse(app(StudentIdentityService::class)->phoneMatches('01012345679', $student->phone_normalized));
    }

    public function test_dns_failure_is_retryable_and_disposable_domains_are_rejected(): void
    {
        $quality = $this->getMockBuilder(EmailQualityService::class)->onlyMethods(['dnsRecords'])->getMock();
        $quality->method('dnsRecords')->willReturn(false);
        try {
            $quality->validate('learner@real-domain.example');
            $this->fail('DNS failures must not grant access.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('try again', $exception->errors()['email'][0]);
        }
        $this->expectException(ValidationException::class);
        app(EmailQualityService::class)->validate('learner@mailinator.com');
    }

    public function test_today_is_reported_without_writing_daily_rollups_and_funnels_are_unique(): void
    {
        $service = app(AnalyticsService::class);
        $session = $service->startSession('report-visitor', 'DE');
        foreach (['resource_gate_viewed', 'resource_gate_viewed', 'resource_requested', 'resource_downloaded', 'section_view', 'section_dwell'] as $name) {
            AnalyticsEvent::create(['event_name' => $name, 'visitor_token' => 'report-visitor', 'session_token' => $session->session_token, 'is_bot' => false, 'metadata' => ['section_id' => 'resource-preview', 'dwell_seconds' => 12, 'detected_country_code' => 'DE'], 'created_at' => now('UTC')]);
        }
        $start = CarbonImmutable::now('Africa/Cairo')->startOfDay();
        $end = $start->endOfDay();
        $this->assertSame(1, (int) $service->getResourceFunnel($start->setTimezone('UTC'), $end->setTimezone('UTC'))['gate_viewed']);
        $this->assertSame(1, (int) $service->reportingCountries($start, $end)->first()->unique_visitors);
        $this->assertSame(1, (int) $service->reportingMetrics($start, $end)->where('metric_name', 'sessions')->sum('count'));
        $this->assertSame(12.0, $service->sectionReport($start, $end)->firstWhere('section_id', 'resource-preview')['total_dwell_seconds']);
        $this->assertDatabaseCount('daily_metrics', 0);
    }

    public function test_onboarding_setup_preserves_existing_forms_and_requires_short_answers(): void
    {
        AdministratorFactory::new()->create(['role' => 'super_admin']);
        $this->artisan('forms:install-onboarding')->assertSuccessful();
        $this->artisan('forms:install-onboarding')->assertSuccessful();
        $this->assertDatabaseCount('forms', 2);
        $short = app(BookingService::class)->publishedIntakeForm();
        $this->assertCount(3, $short->publishedVersion->questions);
        $this->assertSame(['after_booking', 'next_session_check'], Form::where('slug', 'long-form')->first()->triggers->pluck('trigger_name')->sort()->values()->all());
        $this->expectException(ValidationException::class);
        app(BookingService::class)->validatePublicIntake($short->published_version_id, []);
    }

    public function test_long_form_autosave_rejects_cross_version_writes_and_resumes(): void
    {
        $builder = app(FormBuilderService::class);
        $author = AdministratorFactory::new()->create();
        $form = $builder->create(['title' => 'Learning profile', 'slug' => 'learning-profile', 'triggers' => [], 'is_mandatory' => false], [['question_key' => 'goal', 'label' => 'Goal', 'question_type' => 'long_text']], $author);
        $form = $builder->publish($form->id, $form->active_version_id, $form->lock_version);
        $student = Student::factory()->verified()->create();
        $this->actingAs($student, 'student');
        $this->withSession(['student_id' => $student->id, 'student_authenticated_at' => now('UTC')->toIso8601String(), 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()]);
        $save = $this->postJson(route('student.forms.autosave', $form->slug), ['form_version_id' => $form->published_version_id, 'answers' => ['goal' => 'Travel']])->assertOk();
        $draft = $save->json('draft_id');
        $updated = $builder->update($form->id, [], [['question_key' => 'goal', 'label' => 'New goal', 'question_type' => 'long_text']], $form->active_version_id, $form->lock_version);
        $builder->publish($updated->id, $updated->active_version_id, $updated->lock_version);
        $this->postJson(route('student.forms.autosave', $form->slug), ['form_version_id' => $form->published_version_id, 'answers' => ['goal' => 'Changed']])->assertConflict();
        $this->postJson(route('student.forms.autosave', $form->slug), ['submission_id' => $draft, 'form_version_id' => $form->published_version_id, 'answers' => ['goal' => 'Travel and family']])->assertOk();
        $this->get(route('student.forms.show', $form->slug))->assertOk()->assertSee('Travel and family');
    }

    public function test_maintenance_setting_model_invalidates_cache_and_custom_message_is_escaped(): void
    {
        $this->get('/')->assertOk();
        Setting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => '1', 'group' => 'system']);
        Setting::set('maintenance_message', 'Returning tomorrow <script>alert(1)</script>');
        $this->get('/')->assertStatus(503)->assertSee('Returning tomorrow')->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('data-whatsapp-cta', false);
        Setting::where('key', 'maintenance_mode')->first()->update(['value' => '0']);
        $this->get('/')->assertOk();
    }

    public function test_status_presenter_distinguishes_every_lifecycle_state(): void
    {
        foreach (['confirmed' => 'Confirmed', 'completed' => 'Session Delivered', 'cancelled' => 'Booking Canceled', 'no_show' => 'Session Forfeited', 'pending' => 'Pending Confirmation', 'held' => 'Reservation Held', 'unrecognized' => 'Unknown Status'] as $status => $label) {
            $booking = new Booking(['status' => $status]);
            $this->assertSame($label, $booking->studentStatusLabel());
        }
        $rescheduled = new Booking(['status' => 'confirmed']);
        $rescheduled->setAttribute('reschedules_exists', true);
        $this->assertSame('Session Rescheduled', $rescheduled->studentStatusLabel());
    }

    public function test_authenticated_student_link_requires_owned_session_and_never_overwrites(): void
    {
        $service = app(AnalyticsService::class);
        $session = $service->startSession('linked-visitor', 'DE');
        $student = Student::factory()->verified()->create();
        $other = Student::factory()->verified()->create();
        $request = Request::create('/student/login', 'POST');
        $request->attributes->set('analytics_visitor_token', 'linked-visitor');
        $request->attributes->set('analytics_session_token', 'unowned-session');
        $service->linkAuthenticatedStudent($request, $student->id);
        $this->assertNull($session->visitor->fresh()->student_id);
        $request->attributes->set('analytics_session_token', $session->session_token);
        $service->linkAuthenticatedStudent($request, $student->id);
        $service->linkAuthenticatedStudent($request, $other->id);
        $this->assertSame($student->id, $session->visitor->fresh()->student_id);
        $this->assertSame('DE', $session->visitor->fresh()->detected_country_code);
    }

    public function test_whatsapp_context_goals_exports_and_visibility_are_independent(): void
    {
        Setting::set('whatsapp_public', true);
        Setting::set('whatsapp_portal', false);
        Setting::set('whatsapp_url', 'https://wa.me/201012345678');
        Setting::set('analytics.goals', ['whatsapp_clicked']);
        $this->get('/')->assertOk()->assertSee('data-whatsapp-cta', false);
        $student = Student::factory()->verified()->create();
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_authenticated_at' => now('UTC')->toIso8601String(), 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String()])->get(route('student.dashboard'))->assertOk()->assertDontSee('data-whatsapp-cta', false);
        $this->app['auth']->forgetGuards();
        $service = app(AnalyticsService::class);
        $session = $service->startSession('whatsapp-visitor', 'DE');
        foreach (['public', 'portal'] as $context) {
            AnalyticsEvent::create(['event_name' => 'whatsapp_clicked', 'event_uuid' => (string) Str::uuid(), 'visitor_token' => 'whatsapp-visitor', 'session_token' => $session->session_token, 'utm_source' => 'newsletter', 'is_bot' => false, 'metadata' => ['context' => $context, 'language' => 'fr', 'detected_country_code' => 'DE'], 'created_at' => now('UTC')]);
        }
        $start = CarbonImmutable::now('Africa/Cairo')->startOfDay()->setTimezone('UTC');
        $end = CarbonImmutable::now('Africa/Cairo')->endOfDay()->setTimezone('UTC');
        $rows = $service->whatsappReport($start, $end);
        $this->assertCount(2, $rows);
        $this->assertSame('newsletter', $rows->first()['source']);
        $this->assertSame(1, $service->goalReport($start, $end)->first()['visitors']);
        $admin = AdministratorFactory::new()->create();
        $this->actingAs($admin, 'web')->get(route('admin.analytics.overview.export', ['range' => 'today']))->assertOk();
        $this->assertTrue($service->excluded(Request::create('/admin/analytics')));
    }

    public function test_mail_domains_with_null_mx_are_rejected_even_with_address_records(): void
    {
        $quality = $this->getMockBuilder(EmailQualityService::class)->onlyMethods(['dnsRecords'])->getMock();
        $quality->method('dnsRecords')->willReturn([['type' => 'MX', 'target' => '.'], ['type' => 'A', 'ip' => '192.0.2.1']]);
        $this->expectException(ValidationException::class);
        $quality->validate('learner@null-mail.example');
    }
}
