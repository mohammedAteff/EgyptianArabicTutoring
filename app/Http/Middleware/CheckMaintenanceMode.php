<?php

namespace App\Http\Middleware;

use App\Domains\CMS\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $maintenance = Setting::get('maintenance_mode', false);
        $isMaintenanceActive = in_array($maintenance, ['1', 1, true, 'true'], true);

        if ($isMaintenanceActive) {
            // Allow admin routes, health heartbeat, and authenticated administrators
            if ($request->is('admin*') || $request->is('up') || Auth::guard('web')->check()) {
                return $next($request);
            }

            return response()->view('errors.503', [
                'title' => 'Under Scheduled Maintenance',
            ], 503);
        }

        return $next($request);
    }
}
