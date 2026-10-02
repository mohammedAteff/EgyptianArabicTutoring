<?php

use App\Http\Middleware\ApplyAdminNoindexHeaders;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureAdminPreviewAccess;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureNotUnderMaintenance;
use App\Http\Middleware\EnsureStudentAuthenticated;
use App\Http\Middleware\NormalizeTrailingSlash;
use App\Http\Middleware\SetRequestLocale;
use App\Http\Middleware\TrackVisitorSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

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
            TrackVisitorSession::class,
            EnsureNotUnderMaintenance::class,
        ]);
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            TrackVisitorSession::class,
        );
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            EnsureNotUnderMaintenance::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['token', 'telegram_bot_token']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
