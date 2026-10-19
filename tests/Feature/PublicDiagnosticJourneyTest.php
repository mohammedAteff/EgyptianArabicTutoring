<?php

namespace Tests\Feature;

use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\BookingHoldService;
use App\Domains\Booking\Services\BookingService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\BillingReconciliationService;
use App\Domains\Students\Services\StudentLedgerService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\HasPublishedShortForm;
use Tests\TestCase;

class PublicDiagnosticJourneyTest extends TestCase
{
    use HasPublishedShortForm, RefreshDatabase;

    public function test_trace_the_public_diagnostic_through_manual_payment_completion_and_coaching_purchase(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 08:00', 'Africa/Cairo'));
        $form = $this->installShortFormFixture();
        $type = $this->oneHourPackageLesson();
        $type->update(['title' => 'Diagnostic & Learning Roadmap', 'slug' => 'diagnostic-session']);
        AvailabilityRule::create(['weekday' => 3, 'start_time' => '08:00:00', 'end_time' => '24:00:00', 'buffer_minutes' => 10, 'min_notice_hours' => 12, 'max_horizon_days' => 60, 'enabled' => true]);
        $start = CarbonImmutable::parse('2026-10-21 08:00', 'Africa/Cairo')->utc();
        $hold = app(BookingHoldService::class)->acquireHold('journey-probe-visitor', 'journey-probe-session', $type, $start, $start->addHour());
        $booking = app(BookingService::class)->createPublicBooking(['session_type_id' => $type->id, 'customer_timezone' => 'Africa/Cairo', 'first_name' => 'Sarah', 'last_name' => 'Black', 'customer_name' => 'Sarah Black', 'email' => 'journey-probe@example.com', 'customer_email' => 'journey-probe@example.com', 'phone' => '+12025550141', 'date_of_birth' => '1992-04-11', 'visitor_token' => 'journey-probe-visitor', 'session_token' => 'journey-probe-session', 'hold_id' => $hold->id, 'hold_token' => $hold->hold_token, 'idempotency_key' => (string) Str::uuid()], ['goals' => 'Everyday conversations', 'experience' => 'Complete beginner', 'context' => 'Moving to Cairo'], $form->published_version_id);
        $student = Student::findOrFail($booking->student_id);
        $this->assertDatabaseHas('form_submissions', ['student_id' => $student->id, 'booking_id' => $booking->id, 'status' => 'submitted']);
        $admin = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->patch(route('admin.students.update', $student->id), ['first_name' => 'Sarah', 'last_name' => 'Black', 'email' => 'journey-probe@example.com', 'phone' => '+12025550141', 'date_of_birth' => '1992-04-11', 'preferred_timezone' => 'Africa/Cairo', 'identity_status' => 'verified'])->assertSessionHasNoErrors();
        $student->refresh();
        $this->assertSame('verified', $student->identity_status);
        $ledger = app(StudentLedgerService::class);
        $payload = $this->purchasePayload($booking->id);
        $this->actingAs($admin)->get(route('admin.billing.cashier', ['student_id' => $student->id]))->assertOk()->assertSee('Link public diagnostic booking')->assertSee('Booking #'.$booking->id);
        $this->post(route('admin.students.packages.store', $student), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $diagnostic = StudentPackage::query()->where('student_id', $student->id)->sole();
        $this->assertSame(0, $ledger->summary($diagnostic)['remaining_credits']);
        $this->assertNull($ledger->checkDiagnosticCreditEligibility($student->id));
        $method = PaymentMethodFactory::new()->create();
        $this->post(route('admin.students.payments.store', [$student, $diagnostic]), ['amount_paid' => '25.00',
            'payment_method' => (string) $method->id, 'payment_idempotency_key' => (string) Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($ledger->checkDiagnosticCreditEligibility($student->id));
        $this->travelTo(CarbonImmutable::parse('2026-10-21 09:10', 'Africa/Cairo'));
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']))->post(route('admin.bookings.complete', $booking))->assertSessionHasNoErrors();
        $booking->refresh();
        $eligibility = $ledger->checkDiagnosticCreditEligibility($student->id);
        $trackPayload = $this->purchasePayload($booking->id);
        unset($trackPayload['diagnostic_booking_id']);
        $trackPayload['preset_key'] = 'foundation_track';
        $this->post(route('admin.students.packages.store', $student), $trackPayload)->assertRedirect()->assertSessionHasNoErrors();
        $track = StudentPackage::query()->where('offering_key', 'foundation_track')->sole();
        $this->assertSame('completed', $booking->status);
        $this->assertSame('package', $booking->funding_mode);
        $this->assertNotNull($eligibility);
        $this->assertSame('255.00', $track->final_price);
        $this->assertNull($ledger->checkDiagnosticCreditEligibility($student->id));
        $this->assertFalse(app(BillingReconciliationService::class)->reconcile()['has_discrepancies']);
        $this->post(route('admin.students.packages.store', $student), $payload)->assertStatus(409);
        $this->assertDatabaseCount('student_packages', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'public_diagnostic_purchase_linked', 'entity_id' => $booking->id]);
    }

    #[TestWith(['other_student'])]
    #[TestWith(['cancelled'])]
    #[TestWith(['wrong_session'])]
    #[TestWith(['already_linked'])]
    #[TestWith(['wrong_purchase'])]
    public function test_invalid_diagnostic_link_rolls_back_the_purchase_and_ledger(string $case): void
    {
        $student = Student::factory()->verified()->create();
        $type = $this->oneHourPackageLesson();
        $type->update(['slug' => 'diagnostic-session']);
        $booking = Booking::factory()->create(['student_id' => $student->id, 'session_type_id' => $type->id, 'funding_mode' => 'direct', 'status' => 'confirmed']);
        if ($case === 'other_student') {
            $booking->update(['student_id' => Student::factory()->verified()->create()->id]);
        } elseif ($case === 'cancelled') {
            $booking->update(['status' => 'cancelled']);
        } elseif ($case === 'wrong_session') {
            $type->update(['slug' => 'ordinary-one-hour-lesson']);
        } elseif ($case === 'already_linked') {
            app(StudentLedgerService::class)->createPackage($student, 'Diagnostic & Roadmap', 1, '25.00', '0.00', 'USD', null, (string) Str::uuid(), presetKey: 'diagnostic_roadmap', diagnosticBookingId: $booking->id);
        }
        $payload = $this->purchasePayload($booking->id);
        if ($case === 'wrong_purchase') {
            $payload['preset_key'] = 'foundation_track';
        }
        $packageCount = StudentPackage::query()->count();
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']))->post(route('admin.students.packages.store', $student), $payload)
            ->assertRedirect()->assertSessionHasErrors('package');
        $this->assertDatabaseCount('student_packages', $packageCount);
        $this->assertDatabaseCount('session_ledger_entries', $case === 'already_linked' ? 2 : 0);
    }

    /** @return array<string, mixed> */
    private function purchasePayload(int $bookingId): array
    {
        return ['preset_key' => 'diagnostic_roadmap', 'package_name' => 'Diagnostic & Roadmap', 'total_sessions_allocated' => 1,
            'original_price' => '25.00', 'discount_amount' => '0.00', 'currency' => 'USD', 'expiration_date' => '2026-11-02', 'package_idempotency_key' => (string) Str::uuid(), 'diagnostic_booking_id' => $bookingId];
    }
}
