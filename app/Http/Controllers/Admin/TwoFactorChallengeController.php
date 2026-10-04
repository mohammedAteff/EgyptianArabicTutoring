<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Services\AdministratorLoginService;
use App\Domains\Administration\Services\AdministratorTwoFactorService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function show(Request $request, AdministratorTwoFactorService $twoFactor): View|RedirectResponse
    {
        if ($this->pending($request, $twoFactor) === null) {
            $request->session()->forget('admin.two_factor_pending');

            return redirect()->route('admin.login')->withErrors(['auth' => 'Your sign-in challenge has expired. Sign in with your password again.']);
        }

        return view('admin.auth.two-factor-challenge', ['title' => 'Verify your sign-in']);
    }

    public function store(Request $request, AdministratorTwoFactorService $twoFactor, AdministratorLoginService $login): RedirectResponse
    {
        $pending = $this->pending($request, $twoFactor);
        if ($pending === null) {
            $request->session()->forget('admin.two_factor_pending');

            return redirect()->route('admin.login')->withErrors(['auth' => 'Your sign-in challenge has expired. Sign in with your password again.']);
        }
        $data = $request->validate([
            'code' => ['nullable', 'required_without:recovery_code', Rule::prohibitedIf($request->filled('recovery_code')), 'string', 'regex:/\A[0-9]{6}\z/'],
            'recovery_code' => ['nullable', 'required_without:code', Rule::prohibitedIf($request->filled('code')), 'string', 'max:100'],
        ]);
        $administrator = $twoFactor->authenticate($pending['id'], $data['code'] ?? null, $data['recovery_code'] ?? null, $pending['fingerprint']);

        return $login->complete($request, $administrator);
    }

    /** @return array{id: int, fingerprint: string, expires_at: int}|null */
    private function pending(Request $request, AdministratorTwoFactorService $twoFactor): ?array
    {
        $pending = $request->session()->get('admin.two_factor_pending');
        if (! is_array($pending) || ! is_int($pending['id'] ?? null) || ! is_string($pending['fingerprint'] ?? null)
            || ! is_int($pending['expires_at'] ?? null) || $pending['expires_at'] <= now('UTC')->timestamp) {
            return null;
        }
        $administrator = Administrator::query()->whereKey($pending['id'])->whereNull('suspended_at')->first();
        if (! $administrator || ! $administrator->requiresTwoFactor() || ! hash_equals($twoFactor->fingerprint($administrator), $pending['fingerprint'])) {
            return null;
        }

        return $pending;
    }
}
