<?php

namespace App\Http\Controllers\Student;

use App\Domains\Administration\Services\AdminNotificationService;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Lms\Services\ProtectedPlaybackService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentAuthAttemptTracker;
use App\Domains\Students\Services\StudentEmailService;
use App\Domains\Students\Services\StudentIdentityService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use InvalidArgumentException;

class AuthController extends Controller
{
    private const FAILURE_MESSAGE = 'The provided student details could not be verified.';

    public function showLogin(): View
    {
        return view('student.login');
    }

    public function login(Request $request, StudentIdentityService $identity, StudentAuthAttemptTracker $attempts, AdminNotificationService $notifications): RedirectResponse
    {
        $input = $request->only(['date_of_birth', 'name', 'email', 'phone', 'phone_country']);
        if (is_string($input['email'] ?? null)) {
            $input['email'] = trim($input['email']);
        }
        $isValid = Validator::make($input, [
            'date_of_birth' => ['required', 'date_format:Y-m-d'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'phone_country' => ['nullable', 'string', 'size:2'],
        ])->passes();

        $dateOfBirth = is_string($input['date_of_birth'] ?? null) ? $input['date_of_birth'] : '';
        $name = null;
        $email = null;
        if ($isValid) {
            try {
                $name = isset($input['name']) ? $identity->normalizeName($input['name']) : null;
                $email = $identity->normalizeEmail($input['email'] ?? null);
            } catch (InvalidArgumentException) {
                $isValid = false;
            }
        }
        $phone = trim((string) ($input['phone'] ?? '')) ?: null;

        $fingerprints = $identity->authFingerprints(
            is_string($input['email'] ?? null) ? $input['email'] : null,
            is_string($input['phone'] ?? null) ? $input['phone'] : null,
            $dateOfBirth,
            is_string($input['phone_country'] ?? null) ? $input['phone_country'] : null,
        );
        $result = $attempts->attempt($fingerprints, function () use ($isValid, $dateOfBirth, $name, $email, $phone, $notifications, $identity): ?Student {
            if (! $isValid || count(array_filter([$name, $email, $phone])) < 2) {
                return null;
            }

            $candidates = Student::verified()->whereNull('suspended_at')
                ->whereDate('date_of_birth', $dateOfBirth)
                ->where(function ($query) use ($name, $email): void {
                    if ($name) {
                        $query->orWhere('name_normalized', $name);
                    }
                    if ($email) {
                        $query->orWhere('email_normalized', $email)->orWhereHas('verifiedEmails', fn ($emails) => $emails->where('email_normalized', $email));
                    }
                })
                ->select(['id', 'name_normalized', 'email_normalized', 'phone_normalized'])
                ->orderBy('id')
                ->get();

            $matches = $candidates->filter(function (Student $student) use ($name, $email, $phone, $identity): bool {
                $matched = 0;
                $matched += $name !== null && $name === $student->name_normalized ? 1 : 0;
                $matched += $email !== null && app(StudentEmailService::class)->trusted($student, $email) ? 1 : 0;
                $matched += $identity->phoneMatches($phone, $student->phone_normalized) ? 1 : 0;

                return $matched >= 2;
            });

            if ($matches->count() > 1) {
                $notifications->notifySystemWarning('Ambiguous student verification', 'Multiple verified student profiles matched an authentication attempt. Review identity records.');
            }

            return $matches->count() === 1 ? $matches->first() : null;
        });

        if ($result['cooling_down']) {
            return back()->withErrors(['auth' => self::FAILURE_MESSAGE])->setStatusCode(429);
        }

        $student = $result['student'];
        if (! $student) {
            return back()->withErrors(['auth' => self::FAILURE_MESSAGE]);
        }

        Auth::guard('student')->login($student, false);
        $request->session()->regenerate();
        $request->session()->put([
            'student_id' => $student->id,
            'student_authenticated_at' => now('UTC')->toIso8601String(),
            'student_auth_expires_at' => now('UTC')->addMinutes(180)->toIso8601String(),
        ]);

        app(AnalyticsService::class)->linkAuthenticatedStudent($request, $student->id);

        return redirect()->route('student.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        app(ProtectedPlaybackService::class)->logout($request);
        Auth::guard('student')->logout();
        $request->session()->forget(['student_id', 'student_authenticated_at', 'student_auth_expires_at']);

        return redirect()->route('student.login');
    }
}
