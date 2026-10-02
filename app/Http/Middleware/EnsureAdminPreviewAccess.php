<?php

namespace App\Http\Middleware;

use App\Domains\Administration\Models\Administrator;
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
        $administrator = Auth::guard('web')->id() ? Administrator::query()->whereNull('suspended_at')->find(Auth::guard('web')->id()) : null;

        abort_unless(
            $administrator !== null && $administrator->suspended_at === null && in_array($administrator->role, ['super_admin', 'admin'], true),
            403,
            'Draft previews are restricted to administrators.'
        );

        return $next($request);
    }
}
