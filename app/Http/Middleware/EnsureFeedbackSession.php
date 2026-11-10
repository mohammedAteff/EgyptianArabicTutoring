<?php

namespace App\Http\Middleware;

use App\Domains\Students\Services\StudentSessionContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureFeedbackSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('student.feedback.*', 'admin.feedback.*')) {
            return $next($request);
        }
        if ($request->routeIs('student.feedback.*')) {
            $student = app(StudentSessionContext::class)->current($request);
            if ((! $student && $request->expectsJson()) || ! $request->isMethodSafe()) {
                try {
                    $proof = json_decode(Crypt::decryptString((string) ($request->header('X-Feedback-Session') ?: $request->input('_feedback_session'))), true, flags: JSON_THROW_ON_ERROR);
                } catch (Throwable) {
                    $proof = [];
                }
                if (! $student || ($proof['student_id'] ?? null) !== $student->id || ($proof['session_id'] ?? null) !== $request->session()->getId()) {
                    return response()->json(['message' => __('feedback.session_expired')], 401)->header('Cache-Control', 'private, no-store');
                }
            }
        }

        return $next($request)->header('Cache-Control', 'private, no-store');
    }
}
