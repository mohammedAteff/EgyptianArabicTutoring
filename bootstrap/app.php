<?php

use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\NormalizeTrailingSlash;
use App\Http\Middleware\SetRequestLocale;
use App\Http\Middleware\TrackVisitorSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
        ]);
        $middleware->web(append: [
            NormalizeTrailingSlash::class,
            SetRequestLocale::class,
            CheckMaintenanceMode::class,
            TrackVisitorSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
