<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Services\AdministratorLoginService;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Services\TransientRateLimitKey;
use App\Http\Controllers\Controller;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login', [
            'title' => 'Admin Sign In',
        ]);
    }

    public function login(Request $request, AdministratorLoginService $login): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = TransientRateLimitKey::make('staff-login', Str::transliterate(Str::lower($credentials['email']).'|'.$request->ip()));

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        $remember = $request->boolean('remember');

        /** @var SessionGuard $guard */
        $guard = Auth::guard('web');
        $provider = $guard->getProvider();
        $admin = $provider->retrieveByCredentials([...$credentials, 'suspended_at' => null]);
        if (! $admin instanceof Administrator || ! in_array($admin->role, ['super_admin', 'admin', 'assistant'], true) || ! $provider->validateCredentials($admin, $credentials)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);
        $provider->rehashPasswordIfRequired($admin, $credentials);

        return $admin->requiresTwoFactor() ? $login->beginChallenge($request, $admin) : $login->complete($request, $admin, $remember);
    }

    public function logout(Request $request): RedirectResponse
    {
        $admin = Auth::guard('web')->user();

        if ($admin) {
            AuditLog::create([
                'administrator_id' => $admin->id,
                'action' => 'admin_logout',
                'entity_type' => Administrator::class,
                'entity_id' => $admin->id,
                'ip_address' => null,
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('success', 'You have been successfully signed out.');
    }
}
