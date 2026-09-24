<?php

namespace App\Http\Middleware;

use App\Domains\Students\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $studentId = $request->session()->get('student_id');
        $expiresAt = $request->session()->get('student_auth_expires_at');
        $guard = Auth::guard('student');

        $isExpired = ! is_string($expiresAt) || now('UTC')->greaterThanOrEqualTo($expiresAt);
        $student = is_numeric($studentId) && ! $isExpired
            ? Cache::remember('student_auth_check_'.(int) $studentId, 30, fn () => Student::verified()->find((int) $studentId))
            : null;

        if (! $student || (int) $guard->id() !== (int) $studentId) {
            $guard->logout();
            $request->session()->forget(['student_id', 'student_authenticated_at', 'student_auth_expires_at']);

            return redirect()->route('student.login')->withErrors(['auth' => 'The session has expired or is invalid.']);
        }

        $request->attributes->set('student', $student);

        return $next($request);
    }
}
