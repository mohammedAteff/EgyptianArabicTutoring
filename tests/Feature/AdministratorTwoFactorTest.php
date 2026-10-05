<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Services\AdministratorTwoFactorService;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\CMS\Models\Setting;
use App\Domains\Students\Models\Student;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdministratorTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(string $role = 'super_admin'): Administrator
    {
        return AdministratorFactory::new()->create(['role' => $role]);
    }

    /** @return array{Administrator, string, list<string>} */
    private function enrolled(): array
    {
        $administrator = $this->administrator();
        $secret = app(Google2FA::class)->generateSecretKey();
        $codes = [Str::random(10).'-'.Str::random(10), Str::random(10).'-'.Str::random(10)];
        $administrator->forceFill([
            'two_factor_secret' => $secret, 'two_factor_confirmed_at' => now('UTC'),
            'two_factor_version' => (string) Str::uuid(),
            'two_factor_recovery_codes' => array_map(fn (string $code): string => Hash::make($code), $codes),
            'two_factor_last_used_step' => intdiv(now('UTC')->timestamp, 30) - 3,
        ])->save();

        return [$administrator, $secret, $codes];
    }

    private function otp(string $secret, int $stepOffset = 0): string
    {
        return app(Google2FA::class)->oathTotp($secret, intdiv(now('UTC')->timestamp, 30) + $stepOffset);
    }

    private function passwordStage(Administrator $administrator, bool $remember = false): void
    {
        $this->post(route('admin.login.submit'), ['email' => $administrator->email, 'password' => 'Password123!', 'remember' => $remember ? '1' : '0'])
            ->assertRedirect(route('admin.two-factor.challenge'));
        $this->assertGuest('web');
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    private function verifiedSession(Administrator $administrator): void
    {
        $this->actingAs($administrator, 'web')->withSession(['admin.two_factor_verified' => app(AdministratorTwoFactorService::class)->fingerprint($administrator)]);
    }

    #[TestWith(['admin'])]
    #[TestWith(['assistant'])]
    public function test_other_staff_roles_cannot_access_any_security_operation(string $role): void
    {
        $this->actingAs($this->administrator($role), 'web');
        $this->get(route('admin.security.show'))->assertForbidden();
        foreach (['enroll', 'confirm', 'regenerate', 'reset'] as $route) {
            $this->post(route('admin.security.'.$route), ['password' => 'Password123!', 'code' => '123456'])->assertForbidden();
        }
        $this->delete(route('admin.security.disable'))->assertForbidden();
    }

    public function test_student_and_guest_cannot_access_super_admin_security(): void
    {
        $this->get(route('admin.security.show'))->assertRedirect(route('admin.login'));
        $this->actingAs(Student::factory()->verified()->create(), 'student');
        $this->post(route('admin.security.enroll'), ['password' => 'Password123!'])->assertRedirect(route('admin.login'));
        $this->assertDatabaseMissing('administrators', ['two_factor_confirmed_at' => now('UTC')->toDateTimeString()]);
    }

    public function test_enrollment_requires_current_password_and_stays_pending_until_confirmation(): void
    {
        $this->travelTo(now('UTC')->startOfMinute());
        $administrator = $this->administrator();
        $this->actingAs($administrator, 'web')->get(route('admin.security.show'))->assertOk()->assertSeeText('Disabled');
        $this->post(route('admin.security.enroll'), ['password' => 'wrong-password'])->assertSessionHasErrors('password');
        $this->assertNull($administrator->fresh()->two_factor_pending_secret);
        $response = $this->post(route('admin.security.enroll'), ['password' => 'Password123!'])->assertOk()->assertSeeText('Manual setup key');
        $setup = $response->viewData('setup');
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->assertNotEmpty($setup['qr']);
        $this->assertStringContainsString('<svg', $setup['qr']);
        $this->assertFalse($administrator->fresh()->requiresTwoFactor());
        $this->assertNotSame($setup['secret'], DB::table('administrators')->where('id', $administrator->id)->value('two_factor_pending_secret'));
        $this->post(route('admin.security.confirm'), ['code' => $this->otp($setup['secret'], -3)])->assertSessionHasErrors('code');
        $this->assertNull($administrator->fresh()->two_factor_confirmed_at);
        $confirmed = $this->post(route('admin.security.confirm'), ['code' => $this->otp($setup['secret'])])->assertOk()->assertSeeText('Enabled');
        $codes = $confirmed->viewData('recoveryCodes');
        $this->assertCount(10, $codes);
        $confirmed->assertSee('data-copy-recovery', false)->assertSee('Copy all recovery codes')->assertSee('data-administrator-name="'.$administrator->name.'"', false);
        $this->assertSame(10, substr_count($confirmed->getContent(), 'data-recovery-code '));
        $fresh = $administrator->fresh();
        $this->assertTrue($fresh->requiresTwoFactor());
        $this->assertNull($fresh->two_factor_pending_secret);
        $raw = DB::table('administrators')->where('id', $administrator->id)->first();
        $this->assertNotSame($setup['secret'], $raw->two_factor_secret);
        foreach ($codes as $code) {
            $this->assertStringNotContainsString($code, $raw->two_factor_recovery_codes);
        }
        $this->assertTrue(Hash::check($codes[0], $fresh->two_factor_recovery_codes[0]));
        $this->get(route('admin.security.show'))->assertDontSee($setup['secret'])->assertDontSee($codes[0])->assertDontSeeText('Manual setup key')->assertDontSee('data-copy-recovery', false);
        $this->assertStringNotContainsString($setup['secret'], json_encode(session()->all()));
        $this->assertStringNotContainsString($codes[0], json_encode(session()->all()));
        foreach (['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_pending_secret', 'two_factor_version', 'two_factor_last_used_step'] as $field) {
            $this->assertArrayNotHasKey($field, $fresh->toArray());
        }
        $this->assertStringContainsString('no-store', $confirmed->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $confirmed->headers->get('Referrer-Policy'));
    }

    public function test_pending_setup_cannot_be_confirmed_from_another_session_or_after_expiry(): void
    {
        $administrator = $this->administrator();
        $service = app(AdministratorTwoFactorService::class);
        $setup = $service->beginEnrollment($administrator->id, 'Password123!', 'original-session');
        $this->actingAs($administrator, 'web');
        $this->post(route('admin.security.confirm'), ['code' => $this->otp($setup['secret'])])->assertSessionHasErrors('code');
        $this->travel(11)->minutes();
        $this->expectException(ValidationException::class);
        $service->confirmEnrollment($administrator->id, $this->otp($setup['secret']), 'original-session');
    }

    public function test_password_stage_never_authenticates_enabled_super_admin_and_challenge_rotates_session(): void
    {
        [$administrator, $secret] = $this->enrolled();
        $this->passwordStage($administrator);
        $id = session()->getId();
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.security.show'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.two-factor.verify'), ['code' => $this->otp($secret)])->assertRedirect(route('admin.security.show'));
        $this->assertAuthenticatedAs($administrator, 'web');
        $this->assertNotSame($id, session()->getId());
        $this->assertNotNull(session('admin.two_factor_verified'));
        $this->assertNull(session('admin.two_factor_pending'));
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_wrong_outside_window_and_replayed_totp_do_not_authenticate(): void
    {
        $this->travelTo(now('UTC')->startOfMinute());
        [$administrator, $secret] = $this->enrolled();
        $this->passwordStage($administrator);
        $this->post(route('admin.two-factor.verify'), ['code' => $this->otp($secret, -3)])->assertSessionHasErrors('code');
        $this->post(route('admin.two-factor.verify'), ['code' => 'not-a-code'])->assertSessionHasErrors('code');
        $this->assertGuest('web');
        $valid = $this->otp($secret);
        $this->post(route('admin.two-factor.verify'), ['code' => $valid])->assertRedirect();
        $this->post(route('admin.logout'));
        $this->passwordStage($administrator);
        $this->post(route('admin.two-factor.verify'), ['code' => $valid])->assertSessionHasErrors('code');
        $this->assertGuest('web');
    }

    public function test_library_clock_window_accepts_previous_step_without_business_timezone_logic(): void
    {
        $this->travelTo(now('UTC')->startOfMinute());
        config(['business.timezone' => 'Pacific/Auckland']);
        [$administrator, $secret] = $this->enrolled();
        $this->passwordStage($administrator);
        $this->post(route('admin.two-factor.verify'), ['code' => $this->otp($secret, -1)])->assertRedirect();
        $this->assertAuthenticatedAs($administrator, 'web');
    }

    public function test_expired_pending_login_and_changed_password_require_password_stage_again(): void
    {
        [$administrator, $secret] = $this->enrolled();
        $this->passwordStage($administrator);
        $this->travel(11)->minutes();
        $this->post(route('admin.two-factor.verify'), ['code' => $this->otp($secret)])->assertRedirect(route('admin.login'));
        $this->passwordStage($administrator);
        $administrator->forceFill(['password' => 'ChangedPassword123!'])->save();
        $this->post(route('admin.two-factor.verify'), ['code' => $this->otp($secret)])->assertRedirect(route('admin.login'));
        $this->assertGuest('web');
    }

    #[TestWith(['https://evil.example/admin'])]
    #[TestWith(['//evil.example/admin'])]
    #[TestWith(['/admin/../../elsewhere'])]
    #[TestWith(['/admin/%252f%252fevil.example'])]
    #[TestWith(['/admin/login'])]
    public function test_unsafe_intended_destination_is_rejected(string $intended): void
    {
        [$administrator, $secret] = $this->enrolled();
        $this->withSession(['url.intended' => $intended]);
        $this->passwordStage($administrator);
        $this->post(route('admin.two-factor.verify'), ['code' => $this->otp($secret)])->assertRedirect(route('admin.dashboard'));
    }

    public function test_safe_intended_staff_destination_is_preserved(): void
    {
        [$administrator, $secret] = $this->enrolled();
        $intended = route('admin.billing.cashier', ['student_id' => 123]);
        $this->withSession(['url.intended' => $intended]);
        $this->passwordStage($administrator);
        $this->post(route('admin.two-factor.verify'), ['code' => $this->otp($secret)])->assertRedirect($intended);
    }

    public function test_recovery_code_is_consumed_once_and_replay_cannot_authenticate(): void
    {
        [$administrator, , $codes] = $this->enrolled();
        $this->passwordStage($administrator);
        $this->post(route('admin.two-factor.verify'), ['recovery_code' => $codes[0]])->assertRedirect();
        $this->assertCount(1, $administrator->fresh()->two_factor_recovery_codes);
        $this->post(route('admin.logout'));
        $this->passwordStage($administrator);
        $this->post(route('admin.two-factor.verify'), ['recovery_code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertGuest('web');
        $this->assertDatabaseHas('audit_logs', ['action' => 'two_factor_recovery_code_used', 'administrator_id' => $administrator->id, 'ip_address' => null]);
    }

    public function test_regeneration_requires_password_and_factor_and_invalidates_all_old_codes(): void
    {
        [$administrator, , $codes] = $this->enrolled();
        $this->verifiedSession($administrator);
        $this->post(route('admin.security.regenerate'), ['password' => 'Password123!'])->assertSessionHasErrors();
        $this->post(route('admin.security.regenerate'), ['password' => 'wrong', 'recovery_code' => $codes[0]])->assertSessionHasErrors('password');
        $this->assertCount(2, $administrator->fresh()->two_factor_recovery_codes);
        $response = $this->post(route('admin.security.regenerate'), ['password' => 'Password123!', 'recovery_code' => $codes[0]])->assertOk()->assertSee('data-copy-recovery', false);
        $this->assertCount(10, $response->viewData('recoveryCodes'));
        $this->get(route('admin.security.show'))->assertDontSee($response->viewData('recoveryCodes')[0]);
        $this->post(route('admin.logout'));
        $this->passwordStage($administrator->fresh());
        $this->post(route('admin.two-factor.verify'), ['recovery_code' => $codes[1]])->assertSessionHasErrors('code');
        $this->post(route('admin.two-factor.verify'), ['recovery_code' => $response->viewData('recoveryCodes')[0]])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'two_factor_recovery_codes_regenerated']);
    }

    public function test_disabling_requires_both_factors_and_clears_all_security_secrets(): void
    {
        [$administrator, , $codes] = $this->enrolled();
        $this->verifiedSession($administrator);
        $this->delete(route('admin.security.disable'), ['password' => 'Password123!'])->assertSessionHasErrors();
        $this->delete(route('admin.security.disable'), ['password' => 'wrong', 'recovery_code' => $codes[0]])->assertSessionHasErrors('password');
        $this->assertTrue($administrator->fresh()->requiresTwoFactor());
        $this->delete(route('admin.security.disable'), ['password' => 'Password123!', 'recovery_code' => $codes[0]])->assertRedirect(route('admin.security.show'));
        $fresh = $administrator->fresh();
        $this->assertFalse($fresh->requiresTwoFactor());
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_recovery_codes);
        $this->assertNull($fresh->two_factor_pending_secret);
        $this->post(route('admin.logout'));
        $this->post(route('admin.login.submit'), ['email' => $fresh->email, 'password' => 'Password123!'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($fresh, 'web');
    }

    public function test_replacement_keeps_original_factor_active_until_new_setup_is_confirmed(): void
    {
        [$administrator, $secret, $codes] = $this->enrolled();
        $this->verifiedSession($administrator);
        $response = $this->post(route('admin.security.reset'), ['password' => 'Password123!', 'recovery_code' => $codes[0]])->assertOk();
        $setup = $response->viewData('setup');
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->assertSame($secret, $administrator->fresh()->two_factor_secret);
        $this->assertNotSame($secret, $setup['secret']);
        $this->get(route('admin.security.show'))->assertDontSee($setup['secret']);
        $this->post(route('admin.security.confirm'), ['code' => $this->otp($setup['secret'])])->assertOk();
        $this->assertSame($setup['secret'], $administrator->fresh()->two_factor_secret);
        $this->assertDatabaseHas('audit_logs', ['action' => 'two_factor_authenticator_replaced']);
    }

    public function test_old_authenticated_session_and_remember_cookie_cannot_bypass_enabled_two_factor(): void
    {
        [$administrator] = $this->enrolled();
        $this->actingAs($administrator, 'web')->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->assertGuest('web');
        $administrator->forceFill(['remember_token' => Str::random(60)])->save();
        $name = Auth::guard('web')->getRecallerName();
        Auth::forgetGuards();
        $this->withCookie($name, $administrator->id.'|'.$administrator->remember_token.'|'.$administrator->password)
            ->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->assertGuest('web');
    }

    public function test_remember_selection_does_not_create_a_bypass_cookie_for_enabled_owner(): void
    {
        [$administrator, $secret] = $this->enrolled();
        $this->passwordStage($administrator, true);
        $response = $this->post(route('admin.two-factor.verify'), ['code' => $this->otp($secret)])->assertRedirect();
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === Auth::guard('web')->getRecallerName()) {
                $this->assertLessThanOrEqual(time(), $cookie->getExpiresTime());
            }
        }
    }

    #[TestWith(['admin'])]
    #[TestWith(['assistant'])]
    #[TestWith(['super_admin'])]
    public function test_password_only_login_remains_available_when_two_factor_is_disabled(string $role): void
    {
        $administrator = $this->administrator($role);
        $this->post(route('admin.login.submit'), ['email' => $administrator->email, 'password' => 'Password123!', 'remember' => '1'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($administrator, 'web');
        $this->assertNull(session('admin.two_factor_pending'));
        $this->assertNull(session('admin.two_factor_verified'));
    }

    public function test_normal_admin_remember_cookie_continues_to_work(): void
    {
        $administrator = $this->administrator('admin');
        $administrator->forceFill(['remember_token' => Str::random(60)])->save();
        $name = Auth::guard('web')->getRecallerName();
        Auth::forgetGuards();
        $this->withCookie($name, $administrator->id.'|'.$administrator->remember_token.'|'.$administrator->password)->get(route('admin.dashboard'))->assertOk();
        $this->assertAuthenticatedAs($administrator, 'web');
    }

    public function test_password_reset_keeps_two_factor_and_requires_challenge_after_reset(): void
    {
        [$administrator, $secret] = $this->enrolled();
        $token = Password::broker('administrators')->createToken($administrator);
        $this->post(route('admin.password.update'), ['token' => $token, 'email' => $administrator->email, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertRedirect(route('admin.login'));
        $fresh = $administrator->fresh();
        $this->assertSame($secret, $fresh->two_factor_secret);
        $this->assertTrue($fresh->requiresTwoFactor());
        $this->post(route('admin.login.submit'), ['email' => $fresh->email, 'password' => 'NewPassword123!'])->assertRedirect(route('admin.two-factor.challenge'));
        $this->assertGuest('web');
    }

    public function test_suspension_between_password_and_factor_denies_access(): void
    {
        [$administrator, $secret] = $this->enrolled();
        $this->passwordStage($administrator);
        $administrator->forceFill(['suspended_at' => now('UTC')])->save();
        $this->post(route('admin.two-factor.verify'), ['code' => $this->otp($secret)])->assertRedirect(route('admin.login'));
        $this->assertGuest('web');
        $this->post(route('admin.login.submit'), ['email' => $administrator->email, 'password' => 'Password123!'])->assertSessionHasErrors('email');
    }

    public function test_recovery_and_totp_attempts_share_temporary_rate_limit(): void
    {
        [$administrator] = $this->enrolled();
        $this->passwordStage($administrator);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('admin.two-factor.verify'), ['recovery_code' => 'invalid-recovery-code'])->assertSessionHasErrors('code');
        }
        $response = $this->post(route('admin.two-factor.verify'), ['code' => '000000'])->assertStatus(429);
        $this->assertLessThanOrEqual(60, (int) $response->headers->get('Retry-After'));
        $this->travel(61)->seconds();
        $this->post(route('admin.two-factor.verify'), ['recovery_code' => 'still-invalid'])->assertSessionHasErrors('code');
    }

    public function test_enrollment_confirmation_and_security_changes_are_temporarily_throttled(): void
    {
        $administrator = $this->administrator();
        $this->actingAs($administrator, 'web');
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post(route('admin.security.enroll'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        }
        $this->post(route('admin.security.confirm'), ['code' => '000000'])->assertStatus(429);
    }

    public function test_security_audit_and_error_session_never_contain_factor_values(): void
    {
        [$administrator, $secret, $codes] = $this->enrolled();
        $this->passwordStage($administrator);
        $this->post(route('admin.two-factor.verify'), ['recovery_code' => $codes[0]])->assertRedirect();
        $this->post(route('admin.security.regenerate'), ['password' => 'Password123!', 'recovery_code' => $codes[1]])->assertOk();
        $audit = json_encode(AuditLog::query()->where('administrator_id', $administrator->id)->get()->toArray());
        $this->assertStringNotContainsString($secret, $audit);
        $this->assertStringNotContainsString($codes[0], $audit);
        $this->assertStringNotContainsString($codes[1], $audit);
        $this->assertStringNotContainsString('otpauth://', $audit);
        $this->assertSame(0, AuditLog::query()->where('administrator_id', $administrator->id)->whereNotNull('ip_address')->count());
        $this->post(route('admin.security.regenerate'), ['password' => 'wrong', 'code' => '123456', 'recovery_code' => $codes[1]])->assertSessionHasErrors();
        $this->assertNull(session('_old_input.code'));
        $this->assertNull(session('_old_input.recovery_code'));
        $this->assertNull(session('_old_input.password'));
    }

    public function test_pending_challenge_remains_reachable_during_maintenance(): void
    {
        Setting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => '1', 'type' => 'boolean', 'group' => 'general']);
        [$administrator] = $this->enrolled();
        $this->passwordStage($administrator);
        $this->get(route('admin.two-factor.challenge'))->assertOk()->assertSeeText('Verify your sign-in');
    }

    public function test_emergency_cli_requires_explicit_interactive_confirmation_and_records_safe_event(): void
    {
        [$administrator, $secret] = $this->enrolled();
        $this->artisan('administrator:recover-two-factor', ['administrator' => $administrator->id, '--operator' => 'Verified fixture operator', '--reason' => 'Local recovery drill'])
            ->expectsQuestion('Type RECOVER '.$administrator->id.' to confirm', 'CANCEL')->assertFailed();
        $this->assertSame($secret, $administrator->fresh()->two_factor_secret);
        $this->artisan('administrator:recover-two-factor', ['administrator' => $administrator->id, '--operator' => 'Verified fixture operator', '--reason' => 'Local recovery drill'])
            ->expectsQuestion('Type RECOVER '.$administrator->id.' to confirm', 'RECOVER '.$administrator->id)->assertSuccessful();
        $this->assertFalse($administrator->fresh()->requiresTwoFactor());
        $this->assertDatabaseHas('audit_logs', ['action' => 'two_factor_emergency_recovery', 'administrator_id' => $administrator->id, 'ip_address' => null]);
    }

    public function test_password_change_invalidates_pending_enrollment_confirmation(): void
    {
        $administrator = $this->administrator();
        $service = app(AdministratorTwoFactorService::class);
        $setup = $service->beginEnrollment($administrator->id, 'Password123!', 'same-session');
        $administrator->update(['password' => Hash::make('UpdatedPassword123!')]);
        $this->expectException(ValidationException::class);
        $service->confirmEnrollment($administrator->id, $this->otp($setup['secret']), 'same-session');
    }

    public function test_changed_factor_state_revokes_an_existing_verified_session(): void
    {
        [$administrator] = $this->enrolled();
        $this->verifiedSession($administrator);
        $administrator->forceFill(['two_factor_version' => (string) Str::uuid()])->save();
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->assertGuest('web');
    }

    public function test_both_factor_inputs_are_rejected_without_consuming_recovery_code(): void
    {
        [$administrator, $secret, $codes] = $this->enrolled();
        $this->passwordStage($administrator);
        $this->post(route('admin.two-factor.verify'), ['code' => $this->otp($secret), 'recovery_code' => $codes[0]])->assertSessionHasErrors(['code', 'recovery_code']);
        $this->assertCount(2, $administrator->fresh()->two_factor_recovery_codes);
        $this->assertGuest('web');
    }

    public function test_security_exception_response_hides_request_and_debug_payloads(): void
    {
        [$administrator, , $codes] = $this->enrolled();
        $this->verifiedSession($administrator);
        config(['app.debug' => true]);
        Log::spy();
        $this->mock(AdministratorTwoFactorService::class, function ($mock) use ($codes): void {
            $mock->shouldReceive('fingerprint')->andReturn(session('admin.two_factor_verified'));
            $mock->shouldReceive('regenerateRecoveryCodes')->andThrow(new \RuntimeException('Private failed factor '.$codes[0]));
        });
        $this->post(route('admin.security.regenerate'), ['password' => 'Password123!', 'recovery_code' => $codes[0]])
            ->assertStatus(500)->assertSeeText('Unable to complete this security check')->assertDontSee($codes[0])->assertDontSee('Password123!');
        Log::shouldHaveReceived('error')->once()->with('Staff two-factor security check failed.', ['exception_type' => \RuntimeException::class]);
    }

    public function test_emergency_cli_rejects_noninteractive_operation_without_changes(): void
    {
        [$administrator, $secret] = $this->enrolled();
        $this->artisan('administrator:recover-two-factor', ['administrator' => $administrator->id, '--operator' => 'Fixture', '--reason' => 'Noninteractive drill', '--no-interaction' => true])->assertFailed();
        $this->assertSame($secret, $administrator->fresh()->two_factor_secret);
    }

    #[TestWith(['privacy'])]
    #[TestWith(['student.dashboard'])]
    public function test_revoking_stale_staff_authentication_preserves_an_unrelated_student_session(string $route): void
    {
        [$administrator] = $this->enrolled();
        $student = Student::factory()->verified()->create();
        $this->actingAs($administrator, 'web')->actingAs($student, 'student')->withSession([
            'student_id' => $student->id,
            'student_authenticated_at' => now('UTC')->toIso8601String(),
            'student_auth_expires_at' => now('UTC')->addMinutes(180)->toIso8601String(),
        ])->get(route($route))->assertOk()->assertSessionHas('student_id', $student->id);
        $this->assertAuthenticatedAs($student, 'student');
        $this->assertGuest('web');
        $this->assertNull(session('admin.two_factor_verified'));
    }

    public function test_corrupt_factor_storage_fails_closed_without_a_password_debug_page(): void
    {
        [$administrator] = $this->enrolled();
        DB::table('administrators')->where('id', $administrator->id)->update(['two_factor_confirmed_at' => null, 'two_factor_secret' => 'invalid-ciphertext']);
        config(['app.debug' => true]);
        $this->post(route('admin.login.submit'), ['email' => $administrator->email, 'password' => 'Password123!'])
            ->assertStatus(500)->assertDontSee('Password123!')->assertDontSee('invalid-ciphertext');
        $this->assertGuest('web');
    }
}
