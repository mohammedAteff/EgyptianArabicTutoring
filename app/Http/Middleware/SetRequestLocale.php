<?php

namespace App\Http\Middleware;

use App\Domains\Analytics\Services\GeoIpService;
use App\Domains\CMS\Services\LocalizedUrlService;
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

        $urls = app(LocalizedUrlService::class);
        $routeInfo = $urls->resolveRouteInfo(trim($request->getPathInfo(), '/'), $request);
        $public = $routeInfo !== null && ! $request->is('admin*', 'student*', 'api*', 'preview*')
            && $request->isMethod('GET') && ! str_contains($request->path(), '/download')
            && ! preg_match('/bot|crawler|spider/i', (string) $request->userAgent());
        if ($public) {
            $selected = $request->query('lang');
            if (is_string($selected) && in_array($selected, ['en', 'fr', 'de'], true)) {
                $request->session()->put('public_locale', $selected);
                cookie()->queue(cookie('public_locale', $selected, 525600, null, null, $request->isSecure(), true, false, 'lax'));
            }
            $saved = $request->session()->get('public_locale', $request->cookie('public_locale'));
            if (! in_array($saved, ['en', 'fr', 'de'], true)) {
                $saved = $request->session()->get('public_initial_locale');
                if (! in_array($saved, ['en', 'fr', 'de'], true)) {
                    $country = app(GeoIpService::class)->detectCountryFromRequest($request);
                    $saved = match ($country) {
                        'FR' => 'fr', 'DE', 'AT' => 'de', default => 'en'
                    };
                    $request->session()->put('public_initial_locale', $saved);
                }
            }
            if (! in_array($firstSegment, ['fr', 'de'], true) && $saved !== 'en') {
                return redirect($urls->getUrlForLocale($saved, $request), 302);
            }
        }

        app()->setLocale($locale);

        if ($request->hasSession()) {
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }
}
