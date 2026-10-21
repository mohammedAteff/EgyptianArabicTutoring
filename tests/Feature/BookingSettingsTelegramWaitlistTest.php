<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\AdminNotification;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\Notifications\Models\TelegramDelivery;
use App\Domains\Notifications\Services\TelegramActivityEvents;
use App\Domains\Notifications\Services\TelegramRuleCatalog;
use App\Domains\Notifications\Services\TelegramTemplateService;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\PaymentRefund;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\StudentLedgerService;
use App\Jobs\DeliverTelegramMessage;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Database\Factories\TelegramBotFactory;
use Database\Factories\TelegramDestinationFactory;
use Database\Factories\TelegramRuleFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BookingSettingsTelegramWaitlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_safeguards_draft_and_publish_with_the_main_settings_form(): void
    {
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']));
        $fields = ['site_name' => 'QA site', 'business_timezone' => 'Africa/Cairo', 'default_language' => 'en', 'cancellation_policy' => 'Cancel policy', 'rescheduling_policy' => 'Reschedule policy', 'booking_instructions' => 'Instructions', 'email_pin_preference' => 'request_activation'];
        $this->post(route('admin.settings.update'), $fields + ['action' => 'draft'])->assertSessionHasNoErrors();
        $this->assertSame('off', Setting::get('email.pin_preference', 'off'));
        $page = $this->get(route('admin.settings.index'))->assertOk()->assertDontSee('Save email preference');
        $document = new \DOMDocument;
        @$document->loadHTML($page->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertCount(1, $xpath->query('//form[@id="business-settings"]//select[@name="email_pin_preference"]/option[@selected and @value="request_activation"]'));
        $this->post(route('admin.settings.update'), $fields + ['action' => 'publish'])->assertSessionHasNoErrors();
        $this->assertSame('request_activation', Setting::get('email.pin_preference'));
        $this->assertFalse((bool) Setting::get('email.pin_enabled', false));
        $this->post(route('admin.settings.update'), array_replace($fields, ['email_pin_preference' => 'on']))->assertSessionHasErrors('email_pin_preference');
        $this->assertSame('request_activation', Setting::get('email.pin_preference'));
    }

    public function test_booked_times_appear_in_the_calendar_in_order_without_identity_or_bookable_controls(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00:00 UTC'));
        $student = Student::factory()->verified()->create(['preferred_timezone' => 'Europe/Paris']);
        $type = SessionType::factory()->create(['duration_minutes' => 120, 'funding_mode' => 'package', 'student_discoverable' => true, 'required_entitlement_type_id' => EntitlementType::where('code', 'two_hour')->value('id'), 'required_entitlement_units' => 1]);
        $package = app(StudentLedgerService::class)->createPackage($student, 'QA', 5, '0', '0', 'USD', null, fake()->uuid(), entitlementCode: 'two_hour');
        AvailabilityRule::create(['weekday' => 4, 'start_time' => '08:00', 'end_time' => '20:00', 'session_duration_minutes' => null, 'buffer_minutes' => 10, 'min_notice_hours' => 24, 'max_horizon_days' => 60, 'enabled' => true]);
        $other = Student::factory()->verified()->create(['first_name' => 'Private', 'last_name' => 'Learner']);
        $booking = Booking::factory()->create(['student_id' => $other->id, 'session_type_id' => $type->id, 'start_at_utc' => '2026-10-22 05:00:00', 'end_at_utc' => '2026-10-22 07:00:00', 'status' => 'confirmed']);
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_authenticated_at' => now('UTC')->toIso8601String(), 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()]);
        $page = $this->get(route('student.bookings.create', ['date' => '2026-10-22', 'session_type_id' => $type->id]))->assertOk()->assertSee('Join waitlist')->assertDontSee('Private Learner');
        $days = $page->viewData('displaySlots');
        $this->assertTrue($days['2026-10-22'][0]['booked']);
        $this->assertSame('2026-10-22 05:00:00', $days['2026-10-22'][0]['slot_start_utc']);
        $this->assertStringContainsString('preferred_time_from=07%3A00', $page->getContent());
        $document = new \DOMDocument;
        @$document->loadHTML($page->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertCount(0, $xpath->query('//*[@data-booked-time]//button'));
        $this->assertCount(1, $xpath->query('//*[@data-booked-time]//a'));
    }

    public function test_cancellation_and_reschedule_each_have_one_reason_selector_and_no_show_one_reason_value(): void
    {
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']));
        $booking = Booking::factory()->create(['status' => 'confirmed', 'start_at_utc' => now('UTC')->addDays(3), 'end_at_utc' => now('UTC')->addDays(3)->addHour()]);
        $page = $this->get(route('admin.bookings.show', $booking))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($page->getContent());
        $xpath = new \DOMXPath($document);
        foreach (['cancel', 'reschedule', 'no-show'] as $action) {
            $url = route('admin.bookings.'.$action, $booking);
            $this->assertCount(1, $xpath->query('//form[@action="'.$url.'"]//*[@name="reason_code"]'));
        }
    }

    public function test_every_new_activity_rule_receives_a_committed_event_and_no_private_notes(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        $student = Student::factory()->verified()->create();
        Setting::set('telegram.automation_enabled', true);
        $bot = TelegramBotFactory::new()->create();
        $destination = TelegramDestinationFactory::new()->create(['telegram_bot_id' => $bot->id]);
        foreach (TelegramActivityEvents::AUDIT_ALERTS as $trigger => $definition) {
            $rule = TelegramRuleFactory::new()->create(['telegram_bot_id' => $bot->id, 'trigger' => $trigger, 'template' => app(TelegramRuleCatalog::class)->get($trigger)['template']]);
            $rule->destinations()->attach($destination);
            DB::transaction(fn () => app(AuditLogService::class)->logStudent($student->id, $trigger, Student::class, $student->id, null, ['status' => 'completed', 'notes' => 'Never send confidential notes']));
            $delivery = TelegramDelivery::where('trigger', $trigger)->sole();
            $this->assertSame($student->id, $delivery->payload['source']['_student_id']);
            $this->assertArrayNotHasKey('notes', $delivery->payload['source']);
            $this->assertSame('completed', $delivery->payload['source']['status']);
        }
        $count = TelegramDelivery::count();
        try {
            DB::transaction(function () use ($student): void {
                app(AuditLogService::class)->log('student_waitlist_created', Student::class, $student->id);
                throw new \RuntimeException('Rollback fixture');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame($count, TelegramDelivery::count());
        Queue::assertPushed(DeliverTelegramMessage::class, $count);
    }

    public function test_waitlist_and_birthday_notifications_queue_only_configured_alerts_and_redact_summary_details(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Setting::set('telegram.automation_enabled', true);
        $student = Student::factory()->verified()->create();
        $bot = TelegramBotFactory::new()->create();
        $destination = TelegramDestinationFactory::new()->create(['telegram_bot_id' => $bot->id]);
        foreach (['waitlist_opening', 'student_birthday', 'system_warning'] as $trigger) {
            $catalog = app(TelegramRuleCatalog::class)->get($trigger);
            $rule = TelegramRuleFactory::new()->create(['telegram_bot_id' => $bot->id, 'trigger' => $trigger, 'template' => $catalog['template']]);
            $rule->destinations()->attach($destination);
            DB::transaction(fn () => AdminNotification::create(['type' => $trigger, 'level' => 'info', 'title' => 'QA', 'message' => 'Private details', 'data' => ['student_id' => $student->id, 'birthday_date' => '2026-11-03'], 'link' => route('admin.birthdays.index')]));
            $delivery = TelegramDelivery::where('trigger', $trigger)->sole();
            $this->assertSame('3 November', $delivery->payload['source']['birthday']);
            $summary = app(TelegramTemplateService::class)->render($trigger, $catalog['template'], $delivery->payload['source'], false);
            $this->assertStringNotContainsString($student->name, $summary);
            $this->assertStringNotContainsString('Private details', $summary);
        }
        $this->assertDatabaseCount('telegram_deliveries', 3);
    }

    public function test_payment_refund_purchase_and_verified_student_alerts_capture_real_events(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Setting::set('telegram.automation_enabled', true);
        $bot = TelegramBotFactory::new()->create();
        $destination = TelegramDestinationFactory::new()->create(['telegram_bot_id' => $bot->id]);
        foreach (['student_verified', 'package_created', 'payment_recorded', 'payment_refunded'] as $trigger) {
            $rule = TelegramRuleFactory::new()->create(['telegram_bot_id' => $bot->id, 'trigger' => $trigger, 'template' => app(TelegramRuleCatalog::class)->get($trigger)['template']]);
            $rule->destinations()->attach($destination);
        }
        DB::transaction(function (): void {
            $student = Student::factory()->verified()->create();
            $package = StudentPackage::factory()->create(['student_id' => $student->id]);
            $payment = PaymentRecord::factory()->create(['student_id' => $student->id, 'student_package_id' => $package->id, 'amount_paid' => '50.00']);
            PaymentRefund::factory()->create(['student_id' => $student->id, 'student_package_id' => $package->id, 'payment_record_id' => $payment->id, 'amount_refunded' => '10.00']);
        });
        $this->assertDatabaseCount('telegram_deliveries', 4);
        $this->assertSame('50.00', TelegramDelivery::where('trigger', 'payment_recorded')->sole()->payload['source']['amount']);
        $this->assertSame('10.00', TelegramDelivery::where('trigger', 'payment_refunded')->sole()->payload['source']['amount']);
        Queue::assertPushed(DeliverTelegramMessage::class, 4);
    }
}
