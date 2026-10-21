<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyAdminNoindexHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        if ($request->routeIs('admin.security.*', 'admin.two-factor.*', 'student.*', 'booking.confirmation*', 'booking.ics')) {
            $response->headers->set('Cache-Control', 'private, no-store');
            if (! $response->headers->has('Referrer-Policy')) {
                $response->headers->set('Referrer-Policy', 'no-referrer');
            }
        }

        return $response;
    }
}
