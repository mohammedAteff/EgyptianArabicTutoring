<?php

namespace Tests\Feature;

use App\Domains\Administration\Services\AdministratorTwoFactorService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentTwoFactorService;
use App\Mail\StudentSecurityVerification;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class StudentTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(Student $student): void
    {
        $this->post(route('student.login.submit'), ['date_of_birth' => $student->date_of_birth->toDateString(), 'name' => $student->name, 'email' => $student->email_normalized])->assertRedirect();
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    /** @return array{Student, string, list<string>} */
    private function enabled(): array
    {
        $this->freezeTime();
        Mail::fake();
        $student = Student::factory()->verified()->create();
        $this->signIn($student);
        $this->post(route('student.security.enroll'))->assertRedirect(route('student.security.show'));
        $this->assertFalse($student->fresh()->requiresTwoFactor());
        $url = null;
        Mail::assertSent(StudentSecurityVerification::class, function ($mail) use (&$url, $student): bool {
            $url = $mail->verificationUrl;

            return $mail->hasTo($student->email_normalized);
        });
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get($url)->assertOk()->assertViewHas('setup')->assertHeader('Cache-Control', 'no-store, private');
        $secret = $student->fresh()->two_factor_pending_secret;
        $code = app(Google2FA::class)->oathTotp($secret, intdiv(now('UTC')->timestamp, 30));
        $response = $this->post(route('student.security.confirm'), ['code' => $code])->assertOk()->assertViewHas('recoveryCodes');
        $student = $student->fresh();
        $this->assertTrue($student->requiresTwoFactor());
        $this->assertNotSame($secret, $student->getRawOriginal('two_factor_secret'));
        $this->assertArrayNotHasKey('two_factor_secret', $student->toArray());
        $this->withCookie(config('session.cookie'), session()->getId());

        return [$student, $secret, $response->viewData('recoveryCodes')];
    }

    public function test_enrollment_requires_email_approval_and_keeps_recovery_codes_out_of_sessions(): void
    {
        [$student] = $this->enabled();
        $this->assertCount(10, $student->two_factor_recovery_codes);
        $this->assertNull(session('recoveryCodes'));
        $this->get(route('student.security.show'))->assertOk()->assertDontSee('Save these recovery codes privately.');
    }

    public function test_personal_details_do_not_bypass_enabled_mfa_and_recovery_codes_are_single_use(): void
    {
        [$student, $secret, $codes] = $this->enabled();
        $this->post(route('student.logout'))->assertRedirect();
        $this->signIn($student);
        $this->assertGuest('student');
        $this->get(route('student.dashboard'))->assertRedirect(route('student.login'));
        $this->post(route('student.two-factor.verify'), ['recovery_code' => $codes[0]])->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($student, 'student');
        $this->post(route('student.logout'));
        $this->signIn($student);
        $this->post(route('student.two-factor.verify'), ['recovery_code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertGuest('student');
        $this->travel(31)->seconds();
        $otp = app(Google2FA::class)->oathTotp($secret, intdiv(now('UTC')->timestamp, 30));
        $this->post(route('student.two-factor.verify'), ['code' => $otp])->assertRedirect(route('student.dashboard'));
    }

    public function test_enrollment_cannot_be_confirmed_without_the_email_link_or_in_another_browser(): void
    {
        Mail::fake();
        $student = Student::factory()->verified()->create();
        $this->signIn($student);
        $this->post(route('student.security.enroll'))->assertRedirect();
        Mail::assertSent(StudentSecurityVerification::class);
        $secret = $student->fresh()->two_factor_pending_secret;
        $code = app(Google2FA::class)->oathTotp($secret, intdiv(now('UTC')->timestamp, 30));
        $this->post(route('student.security.confirm'), ['code' => $code])->assertSessionHasErrors('security');
        $this->assertFalse($student->fresh()->requiresTwoFactor());
        $this->expectException(ValidationException::class);
        app(StudentTwoFactorService::class)->setup($student->id, 'other-browser', str_repeat('a', 64));
    }

    public function test_replacing_recovery_codes_revokes_the_entire_old_set(): void
    {
        [$student, , $codes] = $this->enabled();
        $response = $this->post(route('student.security.change'), ['action' => 'regenerate', 'recovery_code' => $codes[0]])->assertOk();
        $this->assertCount(10, $response->viewData('recoveryCodes'));
        $this->post(route('student.security.change'), ['action' => 'disable', 'recovery_code' => $codes[1]])->assertSessionHasErrors('code');
        $this->assertTrue($student->fresh()->requiresTwoFactor());
    }

    public function test_expired_and_tampered_enrollment_links_cannot_approve_setup(): void
    {
        $this->freezeTime();
        Mail::fake();
        $student = Student::factory()->verified()->create();
        $this->signIn($student);
        $this->post(route('student.security.enroll'))->assertRedirect();
        $url = null;
        Mail::assertSent(StudentSecurityVerification::class, function ($mail) use (&$url): bool {
            $url = $mail->verificationUrl;

            return true;
        });
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get($url.'&extra=tampered')->assertForbidden();
        $this->travel(11)->minutes();
        $this->get($url)->assertForbidden();
        $this->assertFalse($student->fresh()->requiresTwoFactor());
    }

    public function test_disabling_requires_a_factor_and_revokes_old_session_proof(): void
    {
        [$student, , $codes] = $this->enabled();
        $oldProof = app(StudentTwoFactorService::class)->fingerprint($student);
        $this->post(route('student.security.change'), ['action' => 'disable', 'code' => '000000'])->assertSessionHasErrors('code');
        $this->post(route('student.security.change'), ['action' => 'disable', 'recovery_code' => $codes[0]])->assertOk();
        $this->assertFalse($student->fresh()->requiresTwoFactor());
        $this->withSession(['student.two_factor_verified' => $oldProof])->get(route('student.dashboard'))->assertRedirect(route('student.login'));
    }

    public function test_corrupt_authenticator_storage_fails_closed_without_a_debug_response(): void
    {
        $student = Student::factory()->verified()->create();
        DB::table('students')->where('id', $student->id)->update(['two_factor_secret' => 'fixture-corrupt-ciphertext']);
        config(['app.debug' => true]);
        $this->post(route('student.login.submit'), ['date_of_birth' => $student->date_of_birth->toDateString(), 'name' => $student->name, 'email' => $student->email_normalized])
            ->assertStatus(500)->assertDontSee('fixture-corrupt-ciphertext')->assertSee('Unable to complete this security check.');
        $this->assertGuest('student');
    }

    public function test_assistants_and_password_only_staff_cannot_reset_student_mfa(): void
    {
        [$student] = $this->enabled();
        foreach (['assistant', 'admin'] as $role) {
            $actor = AdministratorFactory::new()->create(['role' => $role]);
            $this->actingAs($actor, 'web')->post(route('admin.students.security.reset', $student), ['password' => 'Password123!', 'code' => '123456', 'reason' => 'Identity checked with the student.'])->assertForbidden();
        }
        $this->assertTrue($student->fresh()->requiresTwoFactor());
    }

    public function test_enrolled_admin_can_reset_student_mfa_with_an_audited_reason(): void
    {
        [$student] = $this->enabled();
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $secret = app(Google2FA::class)->generateSecretKey();
        $actor->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now('UTC'), 'two_factor_version' => (string) Str::uuid()])->save();
        $this->actingAs($actor, 'web')->withSession(['admin.two_factor_verified' => app(AdministratorTwoFactorService::class)->fingerprint($actor)])
            ->post(route('admin.students.security.reset', $student), ['password' => 'Password123!', 'code' => app(Google2FA::class)->oathTotp($secret, intdiv(now('UTC')->timestamp, 30)), 'reason' => 'Identity checked directly with the student.'])->assertRedirect();
        $this->assertFalse($student->fresh()->requiresTwoFactor());
        $this->assertDatabaseHas('audit_logs', ['action' => 'student_two_factor_staff_reset', 'entity_id' => $student->id]);
        $this->get(route('student.dashboard'))->assertRedirect(route('student.login'));
    }
}
