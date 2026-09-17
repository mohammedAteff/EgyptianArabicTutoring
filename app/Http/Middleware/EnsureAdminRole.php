<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('admin.login');
        }

        if (empty($roles)) {
            $roles = ['super_admin'];
        }

        if (! in_array($user->role, $roles, true)) {
            abort(403, 'Unauthorized access. Higher administrator privileges required.');
        }

        return $next($request);
    }
}
