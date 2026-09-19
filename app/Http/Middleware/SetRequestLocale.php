<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetRequestLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rawUri = (string) ($request->server('REQUEST_URI') ?: $request->getRequestUri());
        $path = parse_url($rawUri, PHP_URL_PATH) ?? $request->getPathInfo();

        // 1. Trailing slash standardization (Section 26)
        // Standardize all routes without trailing slashes except '/'
        // Redirect trailing-slash variants (/fr/, /fr/reservation/) using HTTP 301.
        if ($request->isMethodSafe() && $path !== '/' && str_ends_with($path, '/')) {
            $cleanPath = rtrim($path, '/');
            $queryString = $request->getQueryString();
            $targetUrl = $cleanPath.($queryString ? '?'.$queryString : '');

            return redirect($targetUrl, 301);
        }

        // 2. Localized path detection (Section 26)
        $segments = $request->segments();
        $firstSegment = $segments[0] ?? '';

        $locale = match ($firstSegment) {
            'fr' => 'fr',
            'de' => 'de',
            default => 'en',
        };

        app()->setLocale($locale);

        if ($request->hasSession()) {
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }
}
