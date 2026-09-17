<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Notifications\AdminResetPasswordNotification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected Administrator $admin;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clearResolvedInstances();

        $this->admin = Administrator::create([
            'name' => 'Tutor Admin',
            'email' => 'tutor.recovery@boltlanding.test',
            'password' => Hash::make('OldPassword123!'),
            'role' => 'admin',
        ]);
    }

    public function test_password_recovery_dispatches_notification_with_secure_url(): void
    {
        Notification::fake();

        $response = $this->post(route('admin.password.email'), [
            'email' => 'tutor.recovery@boltlanding.test',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        // Verify notification sent with valid token
        $capturedToken = null;
        Notification::assertSentTo($this->admin, AdminResetPasswordNotification::class, function ($notification, $channels, $notifiable) use (&$capturedToken) {
            $capturedToken = $notification->token;
            $mail = $notification->toMail($notifiable);

            // Assert mail contains valid action URL
            $expectedUrlPrefix = route('admin.password.reset', ['token' => $capturedToken, 'email' => $this->admin->email]);
            $this->assertSame($expectedUrlPrefix, $mail->actionUrl);
            $this->assertStringContainsString('60 minutes', implode(' ', $mail->introLines).implode(' ', $mail->outroLines));

            return ! empty($capturedToken);
        });

        $this->assertNotNull($capturedToken);

        // Verify token in database is hashed, NEVER plaintext
        $record = DB::table('password_reset_tokens')->where('email', $this->admin->email)->first();
        $this->assertNotNull($record);
        $this->assertNotEquals($capturedToken, $record->token);
        $this->assertTrue(Hash::check($capturedToken, $record->token) || sha1($capturedToken) === $record->token || hash_equals($record->token, hash_hmac('sha256', $capturedToken, config('app.key'))));

        // Verify AuditLog exists
        $this->assertDatabaseHas('audit_logs', [
            'administrator_id' => $this->admin->id,
            'action' => 'password_reset_requested',
        ]);
    }

    public function test_unknown_email_returns_neutral_response_without_account_enumeration(): void
    {
        Notification::fake();

        $response = $this->post(route('admin.password.email'), [
            'email' => 'unknown.hacker@evil.com',
        ]);

        $response->assertRedirect();
        // Exact same neutral message
        $response->assertSessionHas('status', 'If an administrator account matches that email address, password recovery instructions have been dispatched.');

        // No notification sent to anyone
        Notification::assertNothingSent();

        // No record created in password_reset_tokens
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'unknown.hacker@evil.com',
        ]);
    }

    public function test_valid_password_reset_succeeds_and_updates_password(): void
    {
        Notification::fake();

        $this->post(route('admin.password.email'), [
            'email' => $this->admin->email,
        ]);

        $token = null;
        Notification::assertSentTo($this->admin, AdminResetPasswordNotification::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->assertNotNull($token);

        $response = $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => $this->admin->email,
            'password' => 'NewSecurePassword999!',
            'password_confirmation' => 'NewSecurePassword999!',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHas('success');

        // Password actually updated
        $this->assertTrue(Hash::check('NewSecurePassword999!', $this->admin->fresh()->password));

        // Token consumed and deleted from database (cannot be used again)
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $this->admin->email,
        ]);

        // AuditLog recorded
        $this->assertDatabaseHas('audit_logs', [
            'administrator_id' => $this->admin->id,
            'action' => 'password_reset_completed',
        ]);
    }

    public function test_wrong_token_is_rejected(): void
    {
        Notification::fake();

        $this->post(route('admin.password.email'), [
            'email' => $this->admin->email,
        ]);

        $response = $this->post(route('admin.password.update'), [
            'token' => 'completely-wrong-token-value',
            'email' => $this->admin->email,
            'password' => 'NewSecurePassword999!',
            'password_confirmation' => 'NewSecurePassword999!',
        ]);

        $response->assertSessionHasErrors('email');

        // Password not changed
        $this->assertTrue(Hash::check('OldPassword123!', $this->admin->fresh()->password));
    }

    public function test_expired_token_is_rejected(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 12:00:00');
        Notification::fake();

        $this->post(route('admin.password.email'), [
            'email' => $this->admin->email,
        ]);

        $token = null;
        Notification::assertSentTo($this->admin, AdminResetPasswordNotification::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        // Advance time by 61 minutes (beyond 60 min expiration)
        CarbonImmutable::setTestNow('2026-10-01 13:01:00');

        $response = $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => $this->admin->email,
            'password' => 'NewSecurePassword999!',
            'password_confirmation' => 'NewSecurePassword999!',
        ]);

        $response->assertSessionHasErrors('email');

        // Password not changed
        $this->assertTrue(Hash::check('OldPassword123!', $this->admin->fresh()->password));

        CarbonImmutable::setTestNow();
    }

    public function test_token_cannot_be_reused_once_consumed(): void
    {
        Notification::fake();

        $this->post(route('admin.password.email'), [
            'email' => $this->admin->email,
        ]);

        $token = null;
        Notification::assertSentTo($this->admin, AdminResetPasswordNotification::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        // First reset succeeds
        $first = $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => $this->admin->email,
            'password' => 'FirstNewPassword123!',
            'password_confirmation' => 'FirstNewPassword123!',
        ]);
        $first->assertRedirect(route('admin.login'));
        $this->assertTrue(Hash::check('FirstNewPassword123!', $this->admin->fresh()->password));

        // Second reset with the same token must fail
        $second = $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => $this->admin->email,
            'password' => 'SecondNewPassword123!',
            'password_confirmation' => 'SecondNewPassword123!',
        ]);
        $second->assertSessionHasErrors('email');

        // Password remains the first updated password
        $this->assertTrue(Hash::check('FirstNewPassword123!', $this->admin->fresh()->password));
    }

    public function test_no_secret_tokens_or_urls_are_logged_during_recovery(): void
    {
        Notification::fake();

        $capturedLogs = [];
        Log::listen(function ($message) use (&$capturedLogs) {
            $capturedLogs[] = $message->message;
        });

        $this->post(route('admin.password.email'), [
            'email' => $this->admin->email,
        ]);

        $token = null;
        Notification::assertSentTo($this->admin, AdminResetPasswordNotification::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->assertNotNull($token);

        // Verify that neither the plain token nor the reset URL was logged anywhere
        foreach ($capturedLogs as $logMsg) {
            $this->assertStringNotContainsString($token, $logMsg, 'Plaintext token must never be logged');
            $this->assertStringNotContainsString('reset-password/'.$token, $logMsg, 'Reset URL with token must never be logged');
        }
    }
}
