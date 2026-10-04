<?php

namespace App\Http\Middleware;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Services\AdministratorTwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdministratorSecondFactor
{
    public function __construct(private AdministratorTwoFactorService $twoFactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        $user = $guard->user();
        if ($user instanceof Administrator) {
            $administrator = Administrator::find($user->id);
            $proof = $request->session()->get('admin.two_factor_verified');
            $needsProof = $administrator?->requiresTwoFactor() || is_string($proof);
            if ($needsProof && (! $administrator || $administrator->suspended_at !== null || $guard->viaRemember()
                || ! is_string($proof) || ! hash_equals($this->twoFactor->fingerprint($administrator), $proof))) {
                $guard->logout();
                $request->session()->forget(['admin.two_factor_pending', 'admin.two_factor_verified', 'auth.password_confirmed_at']);
                $request->session()->regenerate(true);
                if ($request->is('admin', 'admin/*') || $request->routeIs('*.preview')) {
                    return redirect()->route('admin.login')->withErrors(['auth' => 'Please sign in again to complete the required security checks.']);
                }
            }
        }

        return $next($request);
    }
}
