<?php

namespace App\Http\Middleware;

use App\Domains\Students\Services\StudentSessionContext;
use App\Domains\Timezone\Services\TimezoneService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentAuthenticated
{
    public function __construct(private StudentSessionContext $students) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('student');
        $student = $this->students->current($request);
        if (! $student) {
            $guard->logout();
            $request->session()->forget(['student_id', 'student_authenticated_at', 'student_auth_expires_at']);

            return redirect()->route('student.login')->withErrors(['auth' => 'The session has expired or is invalid.']);
        }

        $timezone = $request->input('timezone');
        if (is_string($timezone) && app(TimezoneService::class)->isValid($timezone)) {
            $request->session()->put('student_display_timezone', $timezone);
            if ($student->preferred_timezone !== $timezone) {
                $student->update(['preferred_timezone' => $timezone]);
            }
        }
        $request->attributes->set('student', $student);

        return $next($request);
    }
}
