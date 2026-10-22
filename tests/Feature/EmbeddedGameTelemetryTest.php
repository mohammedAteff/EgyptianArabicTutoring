<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Games\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class EmbeddedGameTelemetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_retries_share_an_event_identity_but_replays_and_other_games_are_separate(): void
    {
        $game = Game::create(['title' => 'Vocabulary', 'slug' => 'vocabulary', 'status' => 'available']);
        $other = Game::create(['title' => 'Numbers', 'slug' => 'numbers', 'status' => 'available']);
        $attempt = (string) Str::uuid();
        $this->withCredentials()->withHeaders(['User-Agent' => 'Mozilla/5.0'])->withCookie('_va_visitor', (string) Str::uuid())
            ->withSession(['analytics_visitor_token' => (string) Str::uuid(), 'analytics_session_token' => (string) Str::uuid()]);
        foreach (['game_started', 'game_started', 'game_completed', 'game_completed'] as $action) {
            $this->postJson(route('games.track', $game->slug), ['action' => $action, 'metadata' => ['attempt_id' => $attempt]])->assertOk();
        }
        $this->assertSame(2, AnalyticsEvent::whereIn('event_name', ['game_started', 'game_completed'])->count());
        $this->postJson(route('games.track', $game->slug), ['action' => 'game_started', 'attempt_id' => (string) Str::uuid()])->assertOk();
        $this->postJson(route('games.track', $other->slug), ['action' => 'game_started', 'attempt_id' => $attempt])->assertOk();
        $this->assertSame(4, AnalyticsEvent::whereIn('event_name', ['game_started', 'game_completed'])->count());
    }

    #[TestWith([['score' => -1], 'score'])]
    #[TestWith([['score' => 6, 'total' => 5], 'score'])]
    #[TestWith([['total' => 0], 'total'])]
    #[TestWith([['duration_seconds' => 'yesterday'], 'duration_seconds'])]
    #[TestWith([['duration_seconds' => 86401], 'duration_seconds'])]
    #[TestWith([['attempt_id' => 'invalid'], 'attempt_id'])]
    public function test_invalid_measurements_return_validation_errors_without_recording_a_completion(array $values, string $field): void
    {
        $game = Game::create(['title' => 'Vocabulary', 'slug' => 'vocabulary', 'status' => 'available']);
        $this->postJson(route('games.track', $game->slug), ['action' => 'game_completed', 'metadata' => $values])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseMissing('analytics_events', ['event_name' => 'game_completed']);
    }

    public function test_resource_gate_deduplication_is_scoped_to_each_resource(): void
    {
        $request = Request::create('/resources');
        $request->attributes->set('analytics_session_token', 'gate-session');
        $request->attributes->set('analytics_visitor_token', 'gate-visitor');
        foreach (['guide-one', 'guide-one', 'guide-two'] as $slug) {
            app(AnalyticsService::class)->track('resource_gate_viewed', ['resource_slug' => $slug], $request);
        }

        $this->assertSame(2, AnalyticsEvent::where('event_name', 'resource_gate_viewed')->count());
    }
}
