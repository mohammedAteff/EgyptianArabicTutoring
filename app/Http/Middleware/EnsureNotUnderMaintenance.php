<?php

namespace App\Http\Middleware;

use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\CMS\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
        // 0. Static asset bypass
        if ($request->is(['build/*', 'assets/*', 'images/*', 'storage/*', 'vendor/*', 'favicon.ico', 'robots.txt'])) {
            return $next($request);
        }

        $isUnderMaintenance = false;
        try {
            $isUnderMaintenance = Cache::remember('maintenance_mode_active', 30, function (): bool {
                $value = Setting::where('key', 'maintenance_mode')->value('value')
                    ?? Setting::where('key', 'system.maintenance_mode')->value('value');

                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            });
        } catch (\Throwable $e) {
            Log::warning('Maintenance status lookup failed; allowing the request through.', [
                'exception' => $e,
            ]);
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
            'admin.health',
        ];

        if ($request->routeIs($adminAuthRoutes) || $request->is('admin/login*', 'admin/forgot-password*', 'admin/reset-password*', 'admin/health*')) {
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
            Log::warning('Maintenance visit tracking failed.', [
                'exception' => $e,
            ]);
        }

        $view = view()->exists('errors.503') ? 'errors.503' : 'errors.maintenance';

        $response = response()->view($view, ['title' => 'Under Scheduled Maintenance'], 503);
        $token = $request->attributes->get('analytics_maintenance_visitor_token');
        if (is_string($token) && $token !== $request->cookie('_va_visitor')) {
            $response->withCookie(cookie('_va_visitor', $token, 60 * 24 * 365, '/', null, $request->isSecure() || app()->isProduction(), false, false, 'Lax'));
        }

        return $response;
    }
}
