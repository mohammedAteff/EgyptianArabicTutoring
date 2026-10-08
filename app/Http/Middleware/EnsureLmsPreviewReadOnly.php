<?php

namespace App\Http\Middleware;

use App\Domains\Administration\Models\Administrator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLmsPreviewReadOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $preview = $request->hasSession() ? $request->session()->get('lms_preview') : null;
        $actor = $request->user('web');
        abort_if(is_array($preview) && $actor instanceof Administrator && (int) ($preview['actor_id'] ?? 0) === $actor->id,
            403, 'Exit learner preview before using the Student area.');

        return $next($request);
    }
}
