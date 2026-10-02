<?php

namespace App\Http\Middleware;

use App\Domains\Administration\Models\Administrator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        if ($guard->check() && ! Administrator::query()->whereNull('suspended_at')->whereKey($guard->id())->exists()) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->withErrors(['auth' => 'This account is unavailable.']);
        }

        return $next($request);
    }
}
