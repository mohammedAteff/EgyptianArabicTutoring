<?php

namespace App\Http\Middleware;

use App\Domains\Analytics\Services\AnalyticsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotUnderMaintenance
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isUnderMaintenance = false;
        try {
            $isUnderMaintenance = Cache::remember('system.maintenance_mode', 30, function () {
                $val = DB::table('settings')->where('key', 'system.maintenance_mode')->value('value');
                if ($val === null) {
                    $val = DB::table('settings')->where('key', 'maintenance_mode')->value('value');
                }

                return in_array($val, ['1', 1, true, 'true'], true);
            });
        } catch (\Throwable $e) {
            // Fail open: assume live if database or database-backed cache is unavailable
            $isUnderMaintenance = false;
        }

        if (! $isUnderMaintenance) {
            return $next($request);
        }

        // 1. Bypass for authenticated administrators
        if (Auth::guard('web')->check()) {
            return $next($request);
        }

        // 2. Bypass for unauthenticated admin authentication routes and password recovery
        $adminAuthRoutes = [
            'admin.login',
            'admin.login.submit',
            'admin.password.request',
            'admin.password.email',
            'admin.password.reset',
            'admin.password.update',
        ];

        if ($request->routeIs($adminAuthRoutes) || $request->is('admin/login', 'admin/forgot-password', 'admin/reset-password*')) {
            return $next($request);
        }

        // 3. Heartbeat health check
        if ($request->is('up')) {
            return $next($request);
        }

        // Track visitor under maintenance with safe error handling
        try {
            $this->analyticsService->trackMaintenanceVisit($request);
        } catch (\Throwable $e) {
            // Suppress logging errors during database maintenance/outages
        }

        return response()->view('errors.maintenance', [
            'title' => 'Under Scheduled Maintenance',
        ], 503);
    }
}
