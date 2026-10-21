<?php

namespace App\Http\Middleware;

use App\Domains\Administration\Models\Administrator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class UseMaintenanceDatabase
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user('web');
        if (! config('database.connections.maintenance.username') || ! $request->routeIs('admin.*')
            || ! $actor instanceof Administrator || ! $actor->isAdmin()
            || ! Administrator::query()->whereKey($actor->id)->whereIn('role', ['admin', 'super_admin'])->whereNull('suspended_at')->exists()) {
            return $next($request);
        }
        $runtime = DB::connection();
        $maintenance = DB::connection('maintenance');
        foreach (['host', 'port', 'database', 'unix_socket'] as $key) {
            abort_unless((string) $runtime->getConfig($key) === (string) $maintenance->getConfig($key), 503, 'Maintenance database must target the same application database.');
        }

        return DB::usingConnection('maintenance', fn (): Response => $next($request));
    }
}
