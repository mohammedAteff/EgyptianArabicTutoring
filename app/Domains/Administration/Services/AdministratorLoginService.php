<?php

namespace App\Domains\Administration\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdministratorLoginService
{
    public function __construct(private AdministratorTwoFactorService $twoFactor, private AuditLogService $audit) {}

    public function beginChallenge(Request $request, Administrator $administrator): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->forget(['admin.two_factor_verified', 'auth.password_confirmed_at']);
        $request->session()->regenerate();
        $request->session()->put('admin.two_factor_pending', [
            'id' => $administrator->id,
            'fingerprint' => $this->twoFactor->fingerprint($administrator),
            'expires_at' => now('UTC')->addMinutes(10)->timestamp,
        ]);

        return redirect()->route('admin.two-factor.challenge');
    }

    public function complete(Request $request, Administrator $administrator, bool $remember = false): RedirectResponse
    {
        Auth::guard('web')->login($administrator, $remember && ! $administrator->requiresTwoFactor());
        $request->session()->forget('admin.two_factor_pending');
        $this->secureCurrentSession($request, $administrator);
        $this->audit->log('admin_login', Administrator::class, $administrator->id, adminId: $administrator->id);

        return redirect()->to($this->safeDestination($request));
    }

    public function secureCurrentSession(Request $request, Administrator $administrator): void
    {
        $request->session()->regenerate();
        if ($administrator->requiresTwoFactor()) {
            $request->session()->put('admin.two_factor_verified', $this->twoFactor->fingerprint($administrator));
        } else {
            $request->session()->forget('admin.two_factor_verified');
        }
    }

    private function safeDestination(Request $request): string
    {
        $fallback = route($request->user('web')?->isAssistant() ? 'admin.operations.index' : 'admin.dashboard');
        $intended = $request->session()->pull('url.intended');
        if (! is_string($intended) || preg_match('/[\x00-\x20\\\\]/', $intended)) {
            return $fallback;
        }
        $parts = parse_url($intended);
        $base = parse_url(route('admin.dashboard'));
        if ($parts === false || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || (isset($parts['host']) && (strtolower($parts['host']) !== strtolower($base['host']) || ($parts['scheme'] ?? '') !== $base['scheme'] || ($parts['port'] ?? null) !== ($base['port'] ?? null)))
            || (! isset($parts['host']) && (! str_starts_with($intended, '/') || str_starts_with($intended, '//')))) {
            return $fallback;
        }
        $path = rawurldecode($parts['path'] ?? '');
        $prefix = rtrim($base['path'], '/');
        if ($request->user('web')?->isAssistant() && in_array($path, [$prefix, $prefix.'/dashboard'], true)) {
            return $fallback;
        }
        if (($path !== $prefix && ! str_starts_with($path, $prefix.'/')) || str_contains($path, '..') || str_contains($path, '//') || preg_match('/[\x00-\x20\\\\%]/', $path)
            || in_array($path, [$prefix.'/login', $prefix.'/logout', $prefix.'/two-factor-challenge'], true)) {
            return $fallback;
        }

        return $intended;
    }
}
