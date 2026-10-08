<?php

use App\Http\Middleware\ApplyAdminNoindexHeaders;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureAdministratorSecondFactor;
use App\Http\Middleware\EnsureAdminPreviewAccess;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureNotUnderMaintenance;
use App\Http\Middleware\EnsureStudentAuthenticated;
use App\Http\Middleware\NormalizeTrailingSlash;
use App\Http\Middleware\SetRequestLocale;
use App\Http\Middleware\TrackStaffRecentView;
use App\Http\Middleware\TrackVisitorSession;
use Illuminate\Auth\AuthenticationException;
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
        $middleware->redirectGuestsTo(fn (Request $request) => route('admin.login'));
        $middleware->alias([
            'role' => EnsureAdminRole::class,
            'admin.role' => EnsureAdminRole::class,
            'admin.noindex' => ApplyAdminNoindexHeaders::class,
            'admin.preview' => EnsureAdminPreviewAccess::class,
            'account.active' => EnsureAccountActive::class,
            'student.auth' => EnsureStudentAuthenticated::class,
        ]);
        $middleware->web(append: [
            NormalizeTrailingSlash::class,
            SetRequestLocale::class,
            EnsureAdministratorSecondFactor::class,
            TrackStaffRecentView::class,
            TrackVisitorSession::class,
            EnsureNotUnderMaintenance::class,
        ]);
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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['watch_token', 'api_key', 'read_only_key', 'signing_key', 'account_key', 'lease_token', 'token', 'operation_token', 'telegram_bot_token', 'code', 'recovery_code', 'two_factor_secret', 'two_factor_pending_secret']);
        $exceptions->report(function (Throwable $exception): ?bool {
            if (request()->routeIs('admin.security.*', 'admin.two-factor.*', 'admin.login.submit')) {
                Log::error('Staff two-factor security check failed.', ['exception_type' => $exception::class]);

                return false;
            }

            return null;
        });
        $exceptions->render(function (Throwable $exception, Request $request): ?Response {
            if (! $request->routeIs('admin.security.*', 'admin.two-factor.*', 'admin.login.submit')
                || $exception instanceof ValidationException
                || $exception instanceof AuthenticationException) {
                return null;
            }

            $message = 'Unable to complete this security check. Please try again.';
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
