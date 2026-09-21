<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeTrailingSlash
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();

        if (in_array($request->getMethod(), ['GET', 'HEAD'], true)
            && $path !== '/'
            && str_ends_with($path, '/')
            && ! $request->is('build/*')
            && ! $request->is('assets/*')
            && ! str_contains(basename(rtrim($path, '/')), '.')) {
            $normalizedPath = rtrim($path, '/');
            $query = $request->getQueryString();

            return redirect()->to($normalizedPath.($query ? '?'.$query : ''), 301);
        }

        return $next($request);
    }
}
