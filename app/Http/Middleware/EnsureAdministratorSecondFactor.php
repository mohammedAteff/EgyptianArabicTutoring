<?php

namespace App\Http\Middleware;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Services\AdministratorTwoFactorService;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdministratorSecondFactor
{
    public function __construct(private AdministratorTwoFactorService $twoFactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        abort_unless($guard instanceof SessionGuard, 500);
        $user = $guard->user();
        if ($user instanceof Administrator) {
            $administrator = Administrator::find($user->id);
            $proof = $request->session()->get('admin.two_factor_verified');
            $sessionProof = $request->session()->get('admin.session_fingerprint');
            if ($administrator && $guard->viaRemember() && ! $administrator->requiresTwoFactor()) {
                $sessionProof = $this->twoFactor->fingerprint($administrator);
                $request->session()->put('admin.session_fingerprint', $sessionProof);
            }
            $invalidSession = ($request->session()->has($guard->getName()) || is_string($sessionProof))
                && (! $administrator || ! is_string($sessionProof) || ! hash_equals($this->twoFactor->fingerprint($administrator), $sessionProof));
            $needsProof = $administrator?->requiresTwoFactor() || is_string($proof);
            if ($invalidSession || ($needsProof && (! $administrator || $administrator->suspended_at !== null || $guard->viaRemember()
                || ! is_string($proof) || ! hash_equals($this->twoFactor->fingerprint($administrator), $proof)))) {
                $guard->logout();
                $request->session()->forget(['admin.two_factor_pending', 'admin.two_factor_verified', 'admin.session_fingerprint', 'auth.password_confirmed_at']);
                $request->session()->regenerate(true);
                if ($request->is('admin', 'admin/*') || $request->routeIs('*.preview')) {
                    return redirect()->route('admin.login')->withErrors(['auth' => 'Please sign in again to complete the required security checks.']);
                }
            }
        }

        return $next($request);
    }
}
