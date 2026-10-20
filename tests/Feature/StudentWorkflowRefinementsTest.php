<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Analytics\Services\EngagementCounterService;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Services\WebsiteCopyService;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\PaymentRefund;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentNotification;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\StudentLedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class StudentWorkflowRefinementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_exposes_copy_values_and_the_same_normalized_whatsapp_destination(): void
    {
        $student = Student::factory()->verified()->create(['first_name' => 'Copy', 'last_name' => 'Student', 'email' => 'copy@example.com', 'phone' => '+1 202-555-0142']);
        $this->actingAs(Administrator::factory()->create())->getJson(route('admin.search', ['q' => 'Copy']))->assertOk()
            ->assertJsonFragment(['kind' => 'student', 'email' => 'copy@example.com', 'phone' => '+1 202-555-0142', 'whatsapp_url' => 'https://wa.me/12025550142']);
    }

    public function test_new_lesson_displays_full_slot_details_and_review_before_using_eligible_credits(): void
    {
        [$student, $type] = $this->bookingFixture();
        $otherType = $this->oneHourPackageLesson();
        $this->studentSession($student);
        $response = $this->get(route('student.bookings.create'))->assertOk()->assertSee('Review lesson')->assertSee('Review Your Lesson')->assertSee('Tutor equivalent:')->assertSee('No additional payment is charged.');
        $this->assertSame($type->id, $response->viewData('selectedSessionType')->id);
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//select[@name="session_type_id"]/option[@value="'.$otherType->id.'"][@disabled]')->length);
        $this->assertSame(0, Booking::query()->count());
    }

    public function test_staff_booking_consumes_one_existing_credit_and_duplicate_submit_keeps_one_booking(): void
    {
        [$student, $type, $package] = $this->bookingFixture();
        $admin = Administrator::factory()->create();
        $payload = $this->bookingPayload($student, $type);
        $this->actingAs($admin)->get(route('admin.bookings.create', ['student_id' => $student->id]))->assertOk()->assertSee('Use existing package credits')->assertSee('2-hour sessions');
        $this->post(route('admin.bookings.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $booking = Booking::query()->sole();
        $this->assertSame('package', $booking->funding_mode);
        $this->assertSame('staff_package', $booking->source);
        $this->assertSame($student->id, $booking->student_id);
        $this->assertSame($package->id, $booking->student_package_id);
        $this->assertSame('Called to arrange this lesson', $booking->notes);
        $this->assertDatabaseHas('booking_events', ['booking_id' => $booking->id, 'performed_by' => 'admin', 'performed_by_id' => $admin->id]);
        $this->assertDatabaseHas('session_ledger_entries', ['booking_id' => $booking->id, 'entry_type' => 'session_consumed', 'credit_change' => -1, 'created_by' => $admin->id]);
        $this->post(route('admin.bookings.store'), $payload)->assertSessionHasNoErrors()->assertRedirect(route('admin.bookings.show', $booking));
        $this->assertSame(1, Booking::query()->count());
        $this->assertSame(1, SessionLedgerEntry::query()->where('entry_type', 'session_consumed')->count());
        $this->assertSame(1, (int) app(StudentLedgerService::class)->summary($package->fresh())['remaining_credits']);
        $this->assertSame(0, PaymentRecord::query()->count());
        $this->post(route('admin.bookings.store'), array_replace($payload, ['time' => '12:10']))->assertSessionHas('error');
        $this->assertSame(1, Booking::query()->count());
    }

    #[TestWith(['wrong_type'])]
    #[TestWith(['another_student'])]
    #[TestWith(['paused'])]
    #[TestWith(['expired'])]
    #[TestWith(['exhausted'])]
    #[TestWith(['collision'])]
    public function test_staff_credit_booking_rejects_unavailable_funding_or_times_without_partial_records(string $case): void
    {
        [$student, $type, $package] = $this->bookingFixture();
        $payload = $this->bookingPayload($student, $type);
        if ($case === 'wrong_type') {
            $payload['session_type_id'] = $this->oneHourPackageLesson()->id;
        } elseif ($case === 'another_student') {
            $payload['student_id'] = Student::factory()->verified()->create()->id;
        } elseif ($case === 'paused') {
            $package->update(['paused_at' => now()]);
        } elseif ($case === 'expired') {
            $package->update(['expiration_date' => '2026-10-01']);
        } elseif ($case === 'exhausted') {
            SessionLedgerEntry::factory()->create(['student_id' => $student->id, 'student_package_id' => $package->id, 'student_package_entitlement_id' => $package->entitlements()->sole()->id, 'entitlement_type_id' => $type->required_entitlement_type_id, 'credit_change' => -2, 'entry_type' => 'courtesy_adjustment']);
        } else {
            Booking::factory()->create(['start_at_utc' => CarbonImmutable::parse('2026-10-26 10:00', 'Africa/Cairo')->utc(), 'end_at_utc' => CarbonImmutable::parse('2026-10-26 12:00', 'Africa/Cairo')->utc()]);
        }
        $before = Booking::query()->count();
        $this->actingAs(Administrator::factory()->create())->post(route('admin.bookings.store'), $payload)->assertSessionHas('error');
        $this->assertSame($before, Booking::query()->count());
        $this->assertSame(0, SessionLedgerEntry::query()->where('entry_type', 'session_consumed')->count());
        $this->assertSame(0, PaymentRecord::query()->count());
    }

    public function test_package_booking_requires_staff_permission_and_an_explicit_student_and_idempotency_key(): void
    {
        [$student, $type] = $this->bookingFixture();
        $payload = $this->bookingPayload($student, $type);
        $this->post(route('admin.bookings.store'), $payload)->assertRedirect(route('admin.login'));
        $this->actingAs(Administrator::factory()->create(['role' => 'assistant']))->post(route('admin.bookings.store'), $payload)->assertForbidden();
        unset($payload['student_id'], $payload['idempotency_key']);
        $this->actingAs(Administrator::factory()->create())->post(route('admin.bookings.store'), $payload)->assertSessionHasErrors(['student_id', 'idempotency_key']);
        $this->assertSame(0, Booking::query()->count());
    }

    public function test_student_statement_references_are_local_and_timezone_survives_a_later_profile_change(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00', 'UTC'));
        $foreign = StudentPackage::factory()->create();
        $foreignPayment = PaymentRecord::factory()->create(['student_package_id' => $foreign->id]);
        PaymentRefund::factory()->create(['payment_record_id' => $foreignPayment->id]);
        $student = Student::factory()->verified()->create(['preferred_timezone' => 'Europe/Paris']);
        $first = StudentPackage::factory()->create(['student_id' => $student->id, 'package_name' => 'First lesson purchase']);
        PaymentRecord::factory()->create(['student_package_id' => $first->id, 'paid_at' => now('UTC')]);
        StudentPackage::factory()->create();
        $package = StudentPackage::factory()->create(['student_id' => $student->id, 'package_name' => 'Second lesson purchase']);
        $payment = PaymentRecord::factory()->create(['student_package_id' => $package->id, 'paid_at' => now('UTC')]);
        $refund = PaymentRefund::factory()->create(['payment_record_id' => $payment->id]);
        $student->update(['preferred_timezone' => 'America/New_York']);
        $this->assertSame('Europe/Paris', $package->fresh()->statement_timezone);
        $this->studentSession($student);
        $this->get(route('student.statements.index'))->assertOk()->assertSee('First lesson purchase · Purchase #1')->assertSee('Second lesson purchase · Purchase #2');
        $this->get(route('student.statements.show', $package))->assertOk()->assertSee('Second lesson purchase · Purchase #2')->assertSee('Payment #2')->assertSee('Refund #1')->assertSee('Original payment #2')->assertSee('Dates shown in Europe/Paris')->assertSee('20 Oct 2026, 12:00 PM');
        $this->get(route('student.receipts.show', [$package, $payment->id]))->assertOk()->assertSee('Purchase #2')->assertSee('Payment #2');
        $csv = $this->get(route('student.statements.export', $package))->assertOk()->streamedContent();
        $this->assertStringContainsString('Date (Europe/Paris)', $csv);
        $this->assertStringContainsString('Purchase,2,"2026-10-20 12:00:00"', $csv);
        $this->assertStringContainsString('"Actual payment",2,"2026-10-20 12:00:00"', $csv);
        $xlsx = $this->get(route('student.statements.export', [$package, 'format' => 'xlsx']))->assertOk();
        $book = IOFactory::load($xlsx->baseResponse->getFile()->getPathname());
        try {
            $sheet = $book->getActiveSheet();
            $this->assertSame('Date (Europe/Paris)', $sheet->getCell('C1')->getValue());
            $this->assertSame(2, $sheet->getCell('B2')->getValue());
            $this->assertSame('2026-10-20 12:00:00', $sheet->getCell('C2')->getValue());
        } finally {
            $book->disconnectWorksheets();
        }
        $this->assertStringContainsString('"Actual refund",1,', $csv);
        $this->assertStringContainsString('Original payment #2', $csv);
        $this->get(route('student.statements.show', $foreign))->assertNotFound();
        $this->get(route('student.statements.export', $foreign))->assertNotFound();
        $this->get(route('student.receipts.show', [$package, $payment->id + 100]))->assertNotFound();
        $this->actingAs(Administrator::factory()->create(), 'web')->get(route('admin.statements.show', $package))->assertOk()->assertSee('Purchase #'.$package->id)->assertSee('Payment #'.$payment->id)->assertSee('Dates shown in Africa/Cairo');
    }

    public function test_older_purchase_uses_the_current_profile_timezone_without_inventing_a_snapshot(): void
    {
        $student = Student::factory()->verified()->create(['preferred_timezone' => 'America/New_York']);
        $package = StudentPackage::factory()->create(['student_id' => $student->id]);
        DB::table('student_packages')->where('id', $package->id)->update(['statement_timezone' => null]);
        $this->studentSession($student);
        $this->get(route('student.statements.show', $package))->assertOk()->assertSee('Dates shown in America/New_York');
        $this->assertNull($package->fresh()->statement_timezone);
    }

    public function test_counter_wording_is_discoverable_and_published_from_the_existing_copy_editor(): void
    {
        $admin = Administrator::factory()->create();
        $this->actingAs($admin)->get(route('admin.settings.index'))->assertOk()->assertSee('Edit counter wording')->assertSee('#copy-group-counters');
        $this->get(route('admin.website-copy.index', ['page' => 'shared']))->assertOk()->assertSee('Learning activity — headline')->assertSee('Monthly traffic — page views message');
        request()->attributes->remove('website_copy_documents');
        $copies = app(WebsiteCopyService::class);
        $values = $copies->values('shared', 'en', true);
        $values['counters__counters__live_users__template'] = '{count} learners with us';
        $this->post(route('admin.website-copy.update'), ['page' => 'shared', 'locale' => 'en', 'values' => $values, 'state' => $copies->state('shared', 'en'), 'action' => 'publish', 'reviewed_source' => '1'])->assertSessionHasNoErrors();
        request()->attributes->remove('website_copy_documents');
        $this->assertSame('0 learners with us', app(EngagementCounterService::class)->getCachedPublicPayload()['live_users']['text']);
    }

    public function test_old_entitlement_notification_displays_local_purchase_number_without_rewriting_history(): void
    {
        StudentPackage::factory()->create();
        $student = Student::factory()->verified()->create();
        StudentPackage::factory()->create(['student_id' => $student->id]);
        $package = StudentPackage::factory()->create(['student_id' => $student->id]);
        $notification = StudentNotification::factory()->create(['student_id' => $student->id, 'type' => 'entitlement', 'title' => 'Existing credit notice', 'message' => 'Credit notice · Purchase #'.$package->id.' · 1 available']);
        $this->studentSession($student);
        $this->get(route('student.notifications.index'))->assertOk()->assertSee('Credit notice · Purchase #2 · 1 available');
        $this->assertSame('Credit notice · Purchase #'.$package->id.' · 1 available', $notification->fresh()->message);
    }

    private function studentSession(Student $student): void
    {
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()]);
    }

    private function bookingFixture(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 08:00', 'Africa/Cairo'));
        $student = Student::factory()->verified()->create(['preferred_timezone' => 'Europe/Paris']);
        $type = SessionType::factory()->create(['duration_minutes' => 120, 'funding_mode' => 'package', 'required_entitlement_type_id' => EntitlementType::query()->where('code', 'two_hour')->value('id'), 'required_entitlement_units' => 1]);
        $package = app(StudentLedgerService::class)->createPackage($student, 'Existing two-hour credits', 2, '80.00', '0.00', 'USD', null, 'workflow-grant', entitlementCode: 'two_hour');
        AvailabilityRule::query()->create(['weekday' => 1, 'start_time' => '10:00', 'end_time' => '16:00', 'session_duration_minutes' => null, 'buffer_minutes' => 10, 'min_notice_hours' => 0, 'max_horizon_days' => 60, 'enabled' => true]);

        return [$student, $type, $package];
    }

    private function bookingPayload(Student $student, SessionType $type): array
    {
        return ['funding_mode' => 'package', 'student_id' => $student->id, 'session_type_id' => $type->id, 'date' => '2026-10-26', 'time' => '10:00', 'customer_timezone' => 'Europe/Paris', 'notes' => 'Called to arrange this lesson', 'idempotency_key' => Str::random(48)];
    }
}
