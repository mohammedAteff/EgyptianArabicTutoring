<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Models\AuditLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function showForgotForm(): View
    {
        return view('admin.auth.forgot-password', [
            'title' => 'Reset Administrator Password',
        ]);
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($validated['email']));

        $status = Password::broker('administrators')->sendResetLink(['email' => $email]);

        if ($status === Password::RESET_THROTTLED) {
            return back()->withErrors(['email' => 'Please wait a few moments before requesting another reset link.']);
        }

        // Record audit log if administrator exists, without logging secrets or plain tokens
        $admin = Administrator::where('email', $email)->first();
        if ($admin) {
            AuditLog::create([
                'administrator_id' => $admin->id,
                'action' => 'password_reset_requested',
                'entity_type' => Administrator::class,
                'entity_id' => $admin->id,
                'ip_address' => null,
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);

            Log::info("Password recovery requested for administrator account: {$email}");
        }

        return back()->with('status', 'If an administrator account matches that email address, password recovery instructions have been dispatched.');
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('admin.auth.reset-password', [
            'title' => 'Set New Password',
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker('administrators')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Administrator $admin, string $password) use ($request) {
                $admin->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }

                AuditLog::create([
                    'administrator_id' => $admin->id,
                    'action' => 'password_reset_completed',
                    'entity_type' => Administrator::class,
                    'entity_id' => $admin->id,
                    'ip_address' => null,
                    'user_agent' => $request->userAgent(),
                    'created_at' => now(),
                ]);

                Log::info("Password successfully reset for administrator account: {$admin->email}");
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('admin.login')
                ->with('success', 'Your password has been successfully reset. You may now sign in.');
        }

        return back()->withErrors(['email' => 'Invalid or expired password reset link.']);
    }
}
