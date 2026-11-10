<?php

namespace App\Http\Middleware;

use App\Domains\Students\Services\StudentSessionContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class EnsureQuickNotesSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('student.quick-notes.*')) {
            return $next($request);
        }
        $student = app(StudentSessionContext::class)->current($request);
        try {
            $proof = json_decode(Crypt::decryptString((string) $request->header('X-Quick-Notes-Session')), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $proof = [];
        }
        if (! $student || ($proof['student_id'] ?? null) !== $student->id || ($proof['session_id'] ?? null) !== $request->session()->getId()) {
            return response()->json(['message' => __('Your session changed or expired. Copy your draft before signing in again.'), 'code' => $student && ($proof['student_id'] ?? null) !== $student->id ? 'account_changed' : 'session_expired'], 401)->header('Cache-Control', 'private, no-store');
        }
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $handler = app(ExceptionHandler::class);
            if ($exception instanceof ValidationException || $exception instanceof HttpExceptionInterface || $exception instanceof AuthorizationException || $exception instanceof ModelNotFoundException) {
                $response = $handler->render($request, $exception);
            } else {
                Log::error('Quick Notes request failed.', ['exception_type' => $exception::class]);
                $response = response()->json(['message' => __('Could not save. Your draft is still here. Check your connection and retry.')], 500);
            }
        }

        return $response->header('Cache-Control', 'private, no-store');
    }
}
