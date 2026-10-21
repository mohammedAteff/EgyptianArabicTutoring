<?php

namespace App\Http\Controllers\Student;

use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentLoginService;
use App\Domains\Students\Services\StudentTwoFactorService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function __construct(private StudentTwoFactorService $factors, private StudentLoginService $login) {}

    public function show(Request $request): View
    {
        return view('student.security', ['student' => $request->attributes->get('student')]);
    }

    public function enroll(Request $request): RedirectResponse
    {
        $request->session()->forget('student.two_factor_setup_approved');
        $request->session()->regenerate(true);
        $this->factors->beginEnrollment($request->attributes->get('student')->id, $request->session()->getId());

        return to_route('student.security.show')->with('success', 'Check your primary email to continue setup in this browser.');
    }

    public function approve(Request $request): View
    {
        $data = $request->validate(['state' => ['required', 'string', 'size:64']]);
        $student = $request->attributes->get('student');
        $setup = $this->factors->setup($student->id, $request->session()->getId(), $data['state']);
        $request->session()->put('student.two_factor_setup_approved', $data['state']);

        return view('student.security', compact('student', 'setup'));
    }

    public function confirm(Request $request): View
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']]);
        $student = $request->attributes->get('student');
        $recoveryCodes = $this->factors->confirm($student->id, $request->session()->getId(), (string) $request->session()->get('student.two_factor_setup_approved'), $data['code']);
        $student = $student->fresh();
        $this->login->refreshProof($request, $student);

        return view('student.security', compact('student', 'recoveryCodes'));
    }

    public function change(Request $request): View
    {
        $data = $this->factorInput($request) + $request->validate(['action' => ['required', 'in:regenerate,disable']]);
        $student = $request->attributes->get('student');
        $recoveryCodes = $this->factors->change($student->id, $data['action'], $data['code'] ?? null, $data['recovery_code'] ?? null, $this->factors->fingerprint($student));
        $student = $student->fresh();
        $this->login->refreshProof($request, $student);

        return view('student.security', compact('student', 'recoveryCodes'));
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $this->pending($request)) {
            return to_route('student.login')->withErrors(['auth' => 'The sign-in challenge expired. Sign in again.']);
        }

        return view('student.two-factor-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $pending = $this->pending($request);
        if (! $pending) {
            return to_route('student.login')->withErrors(['auth' => 'The sign-in challenge expired. Sign in again.']);
        }
        $data = $this->factorInput($request);
        $student = $this->factors->authenticate($pending['id'], $data['code'] ?? null, $data['recovery_code'] ?? null, $pending['fingerprint']);

        return $this->login->complete($request, $student, true);
    }

    public function resetStudent(Request $request, Student $student): RedirectResponse
    {
        $data = $this->factorInput($request) + $request->validate(['password' => ['required', 'string', 'max:1024'], 'reason' => ['required', 'string', 'min:10', 'max:500']]);
        $this->factors->resetByStaff($request->user('web'), $student->id, $data['password'], $data['code'] ?? null, $data['recovery_code'] ?? null, $data['reason']);

        return back()->with('success', 'Student MFA reset. Existing student sessions and recovery codes are invalid.');
    }

    /** @return array<string, mixed> */
    private function factorInput(Request $request): array
    {
        return $request->validate(['code' => ['nullable', 'required_without:recovery_code', Rule::prohibitedIf($request->filled('recovery_code')), 'string', 'regex:/\A[0-9]{6}\z/'],
            'recovery_code' => ['nullable', 'required_without:code', Rule::prohibitedIf($request->filled('code')), 'string', 'max:100']]);
    }

    /** @return array{id: int, fingerprint: string, expires_at: int}|null */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get('student.two_factor_pending');
        if (! is_array($pending) || ! is_int($pending['id'] ?? null) || ! is_string($pending['fingerprint'] ?? null)
            || ! is_int($pending['expires_at'] ?? null) || $pending['expires_at'] <= now('UTC')->timestamp) {
            return null;
        }
        $student = Student::verified()->whereNull('suspended_at')->whereNull('merged_into_student_id')->find($pending['id']);

        return $student && $student->requiresTwoFactor() && hash_equals($this->factors->fingerprint($student), $pending['fingerprint']) ? $pending : null;
    }
}
