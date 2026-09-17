<?php

namespace App\Http\Controllers;

use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Games\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class GameController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    public function index(): View
    {
        $games = Game::query()
            ->whereIn('status', ['available', 'coming_soon'])
            ->orderBy('sort_order')
            ->get();

        return view('public.games.index', [
            'games' => $games,
            'title' => 'Egyptian Arabic Learning Games & Quizzes',
        ]);
    }

    public function preview(Request $request, string $slug): View|RedirectResponse
    {
        if (! Auth::guard('web')->check()) {
            abort(403, 'Preview requires administrator authentication.');
        }

        $game = Game::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $draftRevision = $game->revisions()->where('status', 'draft')->latest('id')->first();
        if ($draftRevision) {
            $previewGame = clone $game;
            $previewGame->title = $draftRevision->title ?? $game->title;
            $previewGame->description = $draftRevision->content['description'] ?? $game->description;
            $previewGame->badge = $draftRevision->content['badge'] ?? $game->badge;
            $previewGame->target_url = $draftRevision->content['target_url'] ?? $game->target_url;
            $previewGame->thumbnail_path = $draftRevision->content['thumbnail_path'] ?? $game->thumbnail_path;
        } else {
            $previewGame = $game;
        }

        if ($previewGame->target_url && $request->boolean('redirect')) {
            return redirect()->away($previewGame->target_url);
        }

        return view('public.games.show', [
            'game' => $previewGame,
            'title' => '[PREVIEW] '.$previewGame->title,
            'isPreview' => true,
        ]);
    }

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $game = Game::query()
            ->where('slug', $slug)
            ->whereIn('status', ['available', 'coming_soon'])
            ->firstOrFail();

        $visitorToken = $request->attributes->get('analytics_visitor_token')
            ?? ($request->hasSession() ? $request->session()->get('analytics_visitor_token') : null)
            ?? $request->cookie('_va_visitor');

        $sessionToken = $request->attributes->get('analytics_session_token')
            ?? ($request->hasSession() ? $request->session()->get('analytics_session_token') : null)
            ?? $request->cookie('_va_session');

        $this->analyticsService->track(
            eventName: 'game_opened',
            metadata: [
                'game_slug' => $game->slug,
                'game_title' => $game->title,
                'target_url' => $game->target_url,
            ],
            request: $request,
            visitorToken: $visitorToken,
            sessionToken: $sessionToken,
            page: '/'.ltrim($request->path(), '/')
        );

        if ($game->target_url) {
            return redirect()->away($game->target_url);
        }

        return view('public.games.show', [
            'game' => $game,
            'title' => $game->title.' — Egyptian Arabic Game',
        ]);
    }

    public function track(Request $request, string $slug): JsonResponse
    {
        $game = Game::query()
            ->where('slug', $slug)
            ->available()
            ->firstOrFail();

        $action = $request->input('action'); // 'game_started' or 'game_completed'

        if (! in_array($action, ['game_started', 'game_completed'], true)) {
            return response()->json(['error' => 'Invalid game action'], 422);
        }

        $payloadJson = json_encode($request->all());
        if (strlen((string) $payloadJson) > 2048) {
            return response()->json(['error' => 'Metadata size exceeds 2KB limit.'], 422);
        }

        $visitorToken = $request->attributes->get('analytics_visitor_token')
            ?? ($request->hasSession() ? $request->session()->get('analytics_visitor_token') : null)
            ?? $request->cookie('_va_visitor');

        $sessionToken = $request->attributes->get('analytics_session_token')
            ?? ($request->hasSession() ? $request->session()->get('analytics_session_token') : null)
            ?? $request->cookie('_va_session');

        $allowedKeys = $action === 'game_started'
            ? ['level']
            : ['score', 'total', 'duration_seconds', 'level'];

        $clientValues = [];

        foreach ($allowedKeys as $key) {
            if ($request->has($key)) {
                $val = $request->input($key);
                if (is_array($val) || is_object($val)) {
                    return response()->json(['error' => 'Nested metadata is not permitted.'], 422);
                }
                if (is_string($val) && mb_strlen($val) > 500) {
                    return response()->json(['error' => "Metadata value for '{$key}' exceeds 500 characters."], 422);
                }
                $clientValues[$key] = $val;
            }
        }

        if ($request->has('metadata')) {
            $incoming = $request->input('metadata');
            if (! is_array($incoming)) {
                return response()->json(['error' => 'Metadata must be an array.'], 422);
            }

            if (count($incoming) > 10) {
                return response()->json(['error' => 'Metadata exceeds maximum of 10 keys.'], 422);
            }

            foreach ($incoming as $k => $v) {
                if (! is_string($k) || ! in_array($k, $allowedKeys, true)) {
                    return response()->json(['error' => "Disallowed metadata key '{$k}' for event '{$action}'."], 422);
                }
                if (is_array($v) || is_object($v)) {
                    return response()->json(['error' => 'Nested metadata is not permitted.'], 422);
                }
                if (is_string($v) && mb_strlen($v) > 500) {
                    return response()->json(['error' => "Metadata value for '{$k}' exceeds 500 characters."], 422);
                }
                $clientValues[$k] = $v;
            }
        }

        $metadata = array_merge($clientValues, [
            'game_slug' => $game->slug,
            'game_title' => $game->title,
        ]);

        $this->analyticsService->track(
            eventName: $action,
            metadata: $metadata,
            request: $request,
            visitorToken: $visitorToken,
            sessionToken: $sessionToken,
            page: '/'.ltrim($request->path(), '/')
        );

        return response()->json(['status' => 'tracked', 'action' => $action]);
    }
}
