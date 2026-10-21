<?php

namespace Tests\Feature;

use App\Domains\Administration\Notifications\AdminResetPasswordNotification;
use App\Domains\Administration\Services\SecurityMailTransport;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\PaymentRefund;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentTwoFactorService;
use Database\Factories\AdministratorFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_mail_refuses_log_transports_and_logging_failovers(): void
    {
        config(['mail.default' => 'failover', 'mail.mailers.failover' => ['transport' => 'failover', 'mailers' => ['smtp', 'log']]]);
        $this->assertFalse(SecurityMailTransport::canDeliver());
        config(['mail.mailers.failover.mailers' => ['smtp']]);
        $this->assertTrue(SecurityMailTransport::canDeliver());
        config(['mail.mailers.failover.mailers' => ['failover']]);
        $this->assertFalse(SecurityMailTransport::canDeliver());
    }

    public function test_password_recovery_never_sends_secrets_to_a_logging_mailer(): void
    {
        $actor = AdministratorFactory::new()->create();
        config(['mail.default' => 'log']);
        $this->app['env'] = 'production';
        $notification = new AdminResetPasswordNotification('fixture-reset-token');
        $this->assertSame([], $notification->via($actor));
    }

    public function test_student_setup_refuses_a_logging_failover_outside_tests(): void
    {
        $student = Student::factory()->verified()->create();
        config(['mail.default' => 'failover', 'mail.mailers.failover' => ['transport' => 'failover', 'mailers' => ['smtp', 'log']]]);
        $this->app['env'] = 'production';
        $this->expectException(ValidationException::class);
        app(StudentTwoFactorService::class)->beginEnrollment($student->id, 'fixture-session');
    }

    public function test_password_change_revokes_an_existing_password_only_staff_session(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $this->post(route('admin.login.submit'), ['email' => $actor->email, 'password' => 'Password123!'])->assertRedirect();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get(route('admin.dashboard'))->assertOk();
        $actor->update(['password' => Hash::make('A new long passphrase 2026')]);
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->assertGuest('web');
    }

    public function test_repeated_recovery_requests_do_not_reveal_account_existence(): void
    {
        Notification::fake();
        $actor = AdministratorFactory::new()->create();
        foreach ([$actor->email, 'unknown@example.test'] as $email) {
            for ($i = 0; $i < 2; $i++) {
                $this->post(route('admin.password.email'), ['email' => $email])->assertSessionHasNoErrors()->assertSessionHas('status', 'If an administrator account matches that email address, password recovery instructions have been dispatched.');
            }
        }
        Notification::assertSentTo($actor, AdminResetPasswordNotification::class);
    }

    public function test_login_account_limit_survives_changing_ip_addresses(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        for ($i = 1; $i <= 20; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$i])->post(route('admin.login.submit'), ['email' => $actor->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.100'])->post(route('admin.login.submit'), ['email' => $actor->email, 'password' => 'Password123!'])->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_get_timezone_does_not_persist_and_protected_patch_does(): void
    {
        $student = Student::factory()->verified()->create(['preferred_timezone' => 'UTC']);
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String()]);
        $this->get(route('student.profile', ['timezone' => 'Africa/Cairo']))->assertOk();
        $this->assertSame('UTC', $student->fresh()->preferred_timezone);
        $this->patch(route('student.profile.timezone'), ['timezone' => 'Africa/Cairo'])->assertRedirect();
        $this->assertSame('Africa/Cairo', $student->fresh()->preferred_timezone);
    }

    public function test_calendar_token_does_not_authorize_cancellation_and_foreign_students_are_denied(): void
    {
        $booking = Booking::factory()->create(['student_id' => Student::factory()->verified()]);
        $this->post(route('booking.cancel', $booking->confirmation_token))->assertForbidden();
        $other = Student::factory()->verified()->create();
        $this->actingAs($other, 'student')->withSession(['student_id' => $other->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String()]);
        $this->post(route('booking.cancel', $booking->confirmation_token))->assertForbidden();
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_authenticated_owner_can_cancel_an_upcoming_booking(): void
    {
        $student = Student::factory()->verified()->create();
        $booking = Booking::factory()->create(['student_id' => $student->id, 'start_at_utc' => now('UTC')->addDays(3), 'end_at_utc' => now('UTC')->addDays(3)->addHour()]);
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String()]);
        $this->post(route('booking.cancel', $booking->confirmation_token))->assertRedirect();
        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_public_booking_link_has_private_headers_and_expires_after_the_history_window(): void
    {
        $booking = Booking::factory()->create(['start_at_utc' => now('UTC')->addDay(), 'end_at_utc' => now('UTC')->addDay()->addHour()]);
        $this->get(route('booking.confirmation', $booking->confirmation_token))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->travel(10)->days();
        $this->get(route('booking.confirmation', $booking->confirmation_token))->assertStatus(410);
    }

    public function test_sensitive_tokens_are_redacted_from_analytics_urls(): void
    {
        $token = str_repeat('Secret123', 4);
        $this->assertStringNotContainsString($token, AnalyticsService::safeUrl('https://example.test/booking/confirmation/'.$token.'?signature='.$token));
        $this->assertSame('/booking?utm_source=teacher', AnalyticsService::safeUrl('/booking?utm_source=teacher'));
    }

    #[TestWith(['purchase_currency'])]
    #[TestWith(['payment_owner'])]
    #[TestWith(['refund_owner'])]
    #[TestWith(['payment_amount'])]
    #[TestWith(['refund_amount'])]
    public function test_database_refuses_inconsistent_financial_writes(string $kind): void
    {
        $payment = PaymentRecord::factory()->create();
        $refund = PaymentRefund::factory()->create(['payment_record_id' => $payment->id]);
        $other = Student::factory()->verified()->create();
        $this->expectException(QueryException::class);
        match ($kind) {
            'purchase_currency' => DB::table('student_packages')->where('id', $payment->student_package_id)->update(['currency' => 'EUR']),
            'payment_owner' => DB::table('payment_records')->where('id', $payment->id)->update(['student_id' => $other->id]),
            'refund_owner' => DB::table('payment_refunds')->where('id', $refund->id)->update(['student_id' => $other->id]),
            'payment_amount' => DB::table('payment_records')->where('id', $payment->id)->update(['amount_paid' => '-0.01']),
            'refund_amount' => DB::table('payment_refunds')->where('id', $refund->id)->update(['amount_refunded' => '-0.01']),
        };
    }
}
