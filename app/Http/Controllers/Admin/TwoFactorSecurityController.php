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

class TwoFactorSecurityController extends Controller
{
    public function show(Request $request): View
    {
        return $this->page($request);
    }

    public function store(Request $request, AdministratorTwoFactorService $twoFactor): View
    {
        $data = $request->validate(['password' => ['required', 'string', 'max:1024']]);
        $request->session()->regenerate();
        $setup = $twoFactor->beginEnrollment($this->administrator($request)->id, $data['password'], $request->session()->getId());

        return $this->page($request, ['setup' => $setup]);
    }

    public function confirm(Request $request, AdministratorTwoFactorService $twoFactor, AdministratorLoginService $login): View
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']]);
        $codes = $twoFactor->confirmEnrollment($this->administrator($request)->id, $data['code'], $request->session()->getId());
        $login->secureCurrentSession($request, $this->administrator($request)->fresh());

        return $this->page($request, ['recoveryCodes' => $codes]);
    }

    public function regenerate(Request $request, AdministratorTwoFactorService $twoFactor, AdministratorLoginService $login): View
    {
        $data = $this->strongInput($request);
        $codes = $twoFactor->regenerateRecoveryCodes($this->administrator($request)->id, $data['password'], $data['code'] ?? null, $data['recovery_code'] ?? null);
        $login->secureCurrentSession($request, $this->administrator($request)->fresh());

        return $this->page($request, ['recoveryCodes' => $codes]);
    }

    public function reset(Request $request, AdministratorTwoFactorService $twoFactor): View
    {
        $data = $this->strongInput($request);
        $request->session()->regenerate();
        $setup = $twoFactor->beginEnrollment($this->administrator($request)->id, $data['password'], $request->session()->getId(), true, $data['code'] ?? null, $data['recovery_code'] ?? null);

        return $this->page($request, ['setup' => $setup]);
    }

    public function destroy(Request $request, AdministratorTwoFactorService $twoFactor, AdministratorLoginService $login): RedirectResponse
    {
        $data = $this->strongInput($request);
        $twoFactor->disable($this->administrator($request)->id, $data['password'], $data['code'] ?? null, $data['recovery_code'] ?? null);
        $login->secureCurrentSession($request, $this->administrator($request)->fresh());

        return redirect()->route('admin.security.show')->with('success', 'Two-factor authentication is disabled. Your next sign-in will use your password.');
    }

    /** @return array{password: string, code?: string|null, recovery_code?: string|null} */
    private function strongInput(Request $request): array
    {
        return $request->validate([
            'password' => ['required', 'string', 'max:1024'],
            'code' => ['nullable', 'required_without:recovery_code', Rule::prohibitedIf($request->filled('recovery_code')), 'string', 'regex:/\A[0-9]{6}\z/'],
            'recovery_code' => ['nullable', 'required_without:code', Rule::prohibitedIf($request->filled('code')), 'string', 'max:100'],
        ]);
    }

    private function administrator(Request $request): Administrator
    {
        $administrator = $request->user('web');
        abort_unless($administrator instanceof Administrator && $administrator->isSuperAdmin(), 403);

        return $administrator;
    }

    /** @param array<string, mixed> $extra */
    private function page(Request $request, array $extra = []): View
    {
        $administrator = $this->administrator($request)->fresh();

        return view('admin.auth.security', ['title' => 'Account Security', 'enabled' => $administrator->requiresTwoFactor(), 'pending' => $administrator->two_factor_pending_at !== null, ...$extra]);
    }
}
