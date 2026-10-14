<?php

namespace App\Http\Middleware;

use App\Domains\CMS\Services\LocalizedUrlService;
use App\Domains\CMS\Services\WebsiteCopyCatalog;
use App\Domains\CMS\Services\WebsiteCopyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyWebsiteCopy
{
    public function handle(Request $request, Closure $next): Response
    {
        $page = $request->routeIs('home.internal') ? 'home' : (app(LocalizedUrlService::class)->resolveRouteInfo(trim($request->getPathInfo(), '/'), $request)['key'] ?? null);
        if ($request->is('admin*', 'student*', 'api*') || ! isset(WebsiteCopyCatalog::PAGES[$page ?? ''])) {
            return $next($request);
        }
        $original = app(WebsiteCopyService::class)->apply($page);
        try {
            return $next($request);
        } finally {
            config(['public_content' => $original]);
        }
    }
}
