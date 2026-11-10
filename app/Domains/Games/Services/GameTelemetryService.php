<?php

namespace App\Domains\Games\Services;

use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\Analytics\Services\AnalyticsLifecycle;
use App\Domains\Games\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
            'ui_locale' => ['sometimes', Rule::in(['en', 'fr', 'de'])],
            'practice_language' => ['sometimes', Rule::in(config('games.practice_languages'))],
            'mode' => ['sometimes', Rule::in(['connected', 'wild'])],
            'reveal_seconds' => ['sometimes', 'integer', 'between:1,60'],
            'content_source' => ['sometimes', Rule::in(['curated'])],
            'contract_version' => ['sometimes', 'integer', Rule::in([2])],
            'ending' => ['sometimes', Rule::in(['timed', 'advanced'])],
            'skip_count' => ['sometimes', 'integer', 'between:0,6'],
            'revealed_count' => ['sometimes', 'integer', 'between:0,6'],
            'reason' => ['sometimes', Rule::in(['early_finish', 'restart', 'exit', 'page_left'])],
            'sequence' => ['sometimes', 'integer', 'between:1,10000'],
        ])->validate();
        if (isset($values['score'], $values['total']) && (float) $values['score'] > (float) $values['total']) {
            throw ValidationException::withMessages(['score' => 'The score cannot exceed the total.']);
        }
    }

    public function eventIdentity(Request $request, Game $game, string $action, ?string $attemptId, ?int $sequence = null): ?string
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

        $identityAction = $action === 'game_activity' ? $action.':'.$sequence : $action;

        return (string) Uuid::uuid5(Uuid::NAMESPACE_URL, implode(':', ['embedded-game', app(AnalyticsLifecycle::class)->generation(), $game->id, $visitor ?? $session ?? ($request->hasSession() ? $request->session()->getId() : 'anonymous'), $attemptId, $identityAction]));
    }
}
