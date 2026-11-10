<?php

use App\Http\Middleware\ApplyAdminNoindexHeaders;
use App\Http\Middleware\ApplyWebsiteCopy;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureAdministratorSecondFactor;
use App\Http\Middleware\EnsureAdminPreviewAccess;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureFeedbackSession;
use App\Http\Middleware\EnsureLmsPreviewReadOnly;
use App\Http\Middleware\EnsureNotUnderMaintenance;
use App\Http\Middleware\EnsureQuickNotesSession;
use App\Http\Middleware\EnsureStudentAuthenticated;
use App\Http\Middleware\NormalizeTrailingSlash;
use App\Http\Middleware\SetRequestLocale;
use App\Http\Middleware\TrackStaffRecentView;
use App\Http\Middleware\TrackVisitorSession;
use App\Http\Middleware\UseMaintenanceDatabase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

ini_set('unserialize_callback_func', 'spl_autoload_call');

if (($_ENV['APP_ENV'] ?? '') === 'testing' || getenv('APP_ENV') === 'testing' || defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__')) {
    @ini_set('memory_limit', '512M');
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trimStrings(except: [fn (Request $request): bool => $request->is('student/quick-notes*', 'student/feedback*', 'admin/feedback*')]);
        $middleware->redirectGuestsTo(fn (Request $request) => route('admin.login'));
        $middleware->alias([
            'role' => EnsureAdminRole::class,
            'admin.role' => EnsureAdminRole::class,
            'admin.noindex' => ApplyAdminNoindexHeaders::class,
            'admin.preview' => EnsureAdminPreviewAccess::class,
            'account.active' => EnsureAccountActive::class,
            'student.auth' => EnsureStudentAuthenticated::class,
            'lms.preview.readonly' => EnsureLmsPreviewReadOnly::class,
        ]);
        $middleware->web(append: [
            NormalizeTrailingSlash::class,
            SetRequestLocale::class,
            EnsureAdministratorSecondFactor::class,
            UseMaintenanceDatabase::class,
            TrackStaffRecentView::class,
            TrackVisitorSession::class,
            EnsureNotUnderMaintenance::class,
            ApplyWebsiteCopy::class,
        ]);
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureStudentAuthenticated::class);
        $middleware->prependToPriorityList(EnsureStudentAuthenticated::class, EnsureLmsPreviewReadOnly::class);
        $middleware->prependToPriorityList(EnsureStudentAuthenticated::class, EnsureQuickNotesSession::class);
        $middleware->prependToPriorityList(EnsureStudentAuthenticated::class, EnsureFeedbackSession::class);
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            TrackVisitorSession::class,
        );
        $middleware->prependToPriorityList(
            TrackVisitorSession::class,
            EnsureAdministratorSecondFactor::class,
        );
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            EnsureNotUnderMaintenance::class,
        );
        $middleware->prependToPriorityList([EnsureFeedbackSession::class, EnsureNotUnderMaintenance::class], SetRequestLocale::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $exception, Request $request): ?Response {
            if ($request->is('admin*')) {
                app()->setLocale('en');
            } elseif (! $request->is('api*')) {
                $saved = $request->hasSession() ? $request->session()->get('public_locale', $request->cookie('public_locale')) : $request->cookie('public_locale');
                $selected = $request->is('student', 'student/*') ? $request->query('lang') : null;
                $locale = in_array($selected, ['en', 'fr', 'de'], true) ? $selected : (in_array($request->segment(1), ['fr', 'de'], true) ? $request->segment(1) : $saved);
                app()->setLocale(in_array($locale, ['en', 'fr', 'de'], true) ? $locale : 'en');
            }

            return null;
        });
        $exceptions->dontFlash(['watch_token', 'api_key', 'read_only_key', 'signing_key', 'account_key', 'lease_token', 'token', 'operation_token', 'telegram_bot_token', 'code', 'recovery_code', 'two_factor_secret', 'two_factor_pending_secret']);
        $exceptions->report(function (Throwable $exception): ?bool {
            if (request()->routeIs('student.feedback.*', 'admin.feedback.*')) {
                Log::error('Feedback request failed.', ['exception_type' => $exception::class]);

                return false;
            }
            if (request()->routeIs('admin.security.*', 'admin.two-factor.*', 'admin.login.submit', 'student.security.*', 'student.two-factor.*', 'student.login.submit', 'admin.students.security.reset')) {
                Log::error('Staff two-factor security check failed.', ['exception_type' => $exception::class]);

                return false;
            }

            return null;
        });
        $exceptions->render(function (Throwable $exception, Request $request): ?Response {
            if (! $request->routeIs('student.feedback.*', 'admin.feedback.*')) {
                return null;
            }
            if ($exception instanceof AuthenticationException) {
                return $request->expectsJson()
                    ? response()->json(['message' => __('feedback.session_expired')], 401)->header('Cache-Control', 'private, no-store')
                    : null;
            }
            if ($exception instanceof ValidationException) {
                return response()->json(['message' => __('feedback.validation_failed'), 'errors' => $exception->errors()], 422)
                    ->header('Cache-Control', 'private, no-store');
            }
            $status = match (true) {
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                $exception instanceof ModelNotFoundException => 404,
                $exception instanceof AuthorizationException => 403,
                default => 500,
            };
            $message = match (true) {
                $status >= 500 => __('feedback.failed'),
                in_array($status, [401, 419], true) => __('feedback.session_expired'),
                $status === 429 => __('feedback.rate_limit'),
                in_array($status, [403, 404], true) => __('feedback.unavailable'),
                $exception instanceof HttpExceptionInterface => $exception->getMessage() ?: __('feedback.unavailable'),
                default => __('feedback.unavailable'),
            };
            $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], $status, $headers)->header('Cache-Control', 'private, no-store');
            }

            return response()->view('errors.feedback', [
                'status' => $status,
                'message' => $status >= 500 ? __($request->isMethodSafe() ? 'feedback.load_failed' : 'feedback.request_failed') : $message,
                'admin' => $request->routeIs('admin.feedback.*'),
                'retryUrl' => $request->isMethodSafe() && $status >= 500 ? $request->fullUrl() : null,
            ], $status, $headers)
                ->header('Cache-Control', 'private, no-store');
        });
        $exceptions->render(function (Throwable $exception, Request $request): ?Response {
            if (! $request->routeIs('admin.security.*', 'admin.two-factor.*', 'admin.login.submit', 'student.security.*', 'student.two-factor.*', 'student.login.submit', 'admin.students.security.reset')
                || $exception instanceof ValidationException
                || $exception instanceof AuthenticationException) {
                return null;
            }

            $message = __('Unable to complete this security check. Please try again.');
            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
            $response = $request->expectsJson() ? response()->json(['message' => $message], $status, $headers) : response($message, $status, $headers);
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Referrer-Policy', 'no-referrer');

            return $response;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
