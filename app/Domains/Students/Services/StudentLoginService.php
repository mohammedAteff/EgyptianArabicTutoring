<?php

namespace App\Domains\Students\Services;

use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPortalLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentLoginService
{
    public function __construct(private StudentTwoFactorService $factors) {}

    public function beginChallenge(Request $request, Student $student): RedirectResponse
    {
        Auth::guard('student')->logout();
        $request->session()->forget(['student_id', 'student_authenticated_at', 'student_auth_expires_at', 'student.two_factor_verified', 'student.two_factor_setup_approved']);
        $request->session()->regenerate(true);
        $request->session()->put('student.two_factor_pending', ['id' => $student->id, 'fingerprint' => $this->factors->fingerprint($student), 'expires_at' => now('UTC')->addMinutes(10)->timestamp]);

        return redirect()->route('student.two-factor.challenge');
    }

    public function complete(Request $request, Student $student, bool $secondFactor = false): RedirectResponse
    {
        $current = Student::verified()->whereNull('suspended_at')->whereNull('merged_into_student_id')->findOrFail($student->id);
        abort_unless(hash_equals($this->factors->fingerprint($current), $this->factors->fingerprint($student)), 403);
        if ($current->requiresTwoFactor() && ! $secondFactor) {
            return $this->beginChallenge($request, $current);
        }
        Auth::guard('student')->login($current, false);
        $request->session()->regenerate(true);
        $request->session()->forget(['student.two_factor_pending', 'student.two_factor_setup_approved']);
        $request->session()->put(['student_id' => $current->id, 'student_authenticated_at' => now('UTC')->toIso8601String(),
            'student_auth_expires_at' => now('UTC')->addMinutes(180)->toIso8601String(), 'student.two_factor_verified' => $this->factors->fingerprint($current)]);
        StudentPortalLogin::query()->create(['student_id' => $current->id, 'timezone' => $current->preferred_timezone, 'authenticated_at' => now('UTC')]);
        app(AnalyticsService::class)->linkAuthenticatedStudent($request, $current->id);

        return redirect()->route('student.dashboard');
    }

    public function refreshProof(Request $request, Student $student): void
    {
        $request->session()->regenerate(true);
        $request->session()->put('student.two_factor_verified', $this->factors->fingerprint($student));
        $request->session()->forget('student.two_factor_setup_approved');
    }
}
