<?php

namespace App\Domains\Games\Services;

use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\Analytics\Services\AnalyticsLifecycle;
use App\Domains\Games\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

class GameTelemetryService
{
    /** @param array<string, mixed> $values */
    public function validate(array $values): void
    {
        Validator::make($values, [
            'attempt_id' => ['sometimes', 'required', 'uuid'],
            'score' => ['sometimes', 'required', 'numeric', 'between:0,1000000'],
            'total' => ['sometimes', 'required', 'numeric', 'gt:0', 'max:1000000'],
            'duration_seconds' => ['sometimes', 'required', 'numeric', 'between:0,86400'],
            'level' => ['sometimes', 'nullable', 'string', 'max:100'],
        ])->validate();
        if (isset($values['score'], $values['total']) && (float) $values['score'] > (float) $values['total']) {
            throw ValidationException::withMessages(['score' => 'The score cannot exceed the total.']);
        }
    }

    public function eventIdentity(Request $request, Game $game, string $action, ?string $attemptId): ?string
    {
        if ($attemptId === null) {
            return null;
        }
        $session = $request->attributes->get('analytics_session_token')
            ?? ($request->hasSession() ? $request->session()->get('analytics_session_token') : null)
            ?? $request->cookie('_va_session');
        $visitor = ($session ? VisitorSession::query()->where('session_token', $session)->orWhere('session_id', $session)->first()?->visitor->visitor_token : null)
            ?? ($request->hasSession() ? $request->session()->get('analytics_visitor_token') : null)
            ?? $request->attributes->get('analytics_visitor_token') ?? $request->cookie('_va_visitor');

        return (string) Uuid::uuid5(Uuid::NAMESPACE_URL, implode(':', ['embedded-game', app(AnalyticsLifecycle::class)->generation(), $game->id, $visitor ?? $session ?? ($request->hasSession() ? $request->session()->getId() : 'anonymous'), $attemptId, $action]));
    }
}
