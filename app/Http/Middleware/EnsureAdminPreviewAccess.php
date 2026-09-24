<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPreviewAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $administrator = Auth::guard('web')->user();

        abort_unless(
            $administrator !== null && in_array($administrator->role, ['super_admin', 'admin'], true),
            403,
            'Draft previews are restricted to administrators.'
        );

        return $next($request);
    }
}
