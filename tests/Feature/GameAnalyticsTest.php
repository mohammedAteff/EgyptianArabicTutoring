<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Models\Visitor;
use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\Games\Models\Game;
use App\Domains\Games\Services\GameAnalyticsService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class GameAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_events_use_separate_browser_identifiers_without_student_or_session_links(): void
    {
        $game = Game::create(['title' => 'Story', 'slug' => '6-word-story', 'status' => 'available']);
        $attempt = (string) Str::uuid();
        $metadata = ['attempt_id' => $attempt, 'level' => 'easy', 'ui_locale' => 'fr', 'practice_language' => 'ar-eg', 'mode' => 'connected', 'reveal_seconds' => 5, 'content_source' => 'curated', 'contract_version' => 2];
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; Mobile)'])->postJson(route('games.track', $game->slug), ['action' => 'game_started', 'metadata' => $metadata])->assertOk();
        $event = AnalyticsEvent::where('event_name', 'game_started')->firstOrFail();
        $this->assertSame('browser', $event->metadata['privacy_scope']);
        $this->assertSame('mobile', $event->metadata['device_type']);
        $this->assertNull($event->ip_hash);
        $this->assertFalse(Visitor::where('visitor_token', $event->visitor_token)->exists());
        $this->assertFalse(VisitorSession::where('session_token', $event->session_token)->exists());
        $this->assertSame($attempt, $event->metadata['attempt_id']);
        $this->assertArrayNotHasKey('student_id', $event->metadata);
        $this->assertArrayNotHasKey('score', $event->metadata);
    }

    public function test_activity_retries_deduplicate_but_new_sequences_and_outcomes_are_retained(): void
    {
        $game = Game::create(['title' => 'Story', 'slug' => '6-word-story', 'status' => 'available']);
        $attempt = (string) Str::uuid();
        $this->withCredentials()->withCookie('_va_visitor', (string) Str::uuid())->withSession(['analytics_visitor_token' => (string) Str::uuid(), 'analytics_session_token' => (string) Str::uuid()]);
        foreach ([1, 1, 2] as $sequence) {
            $this->postJson(route('games.track', $game->slug), ['action' => 'game_activity', 'metadata' => ['attempt_id' => $attempt, 'sequence' => $sequence, 'duration_seconds' => $sequence * 15, 'contract_version' => 2]])->assertOk();
        }
        $this->assertSame(2, AnalyticsEvent::where('event_name', 'game_activity')->count());
        foreach ([1, 2] as $retry) {
            $this->postJson(route('games.track', $game->slug), ['action' => 'game_interrupted', 'metadata' => ['attempt_id' => $attempt, 'reason' => 'early_finish', 'duration_seconds' => 30, 'revealed_count' => 3]])->assertOk();
        }
        $this->assertSame(1, AnalyticsEvent::where('event_name', 'game_interrupted')->count());
    }

    #[TestWith(['game_started', ['practice_language' => 'ar-franko']])]
    #[TestWith(['game_started', ['reveal_seconds' => 61]])]
    #[TestWith(['game_started', ['content_source' => 'ai']])]
    #[TestWith(['game_completed', ['score' => 18, 'total' => 30]])]
    #[TestWith(['game_completed', ['skip_count' => 7]])]
    #[TestWith(['game_activity', ['sequence' => -1]])]
    #[TestWith(['game_interrupted', ['reason' => 'recording']])]
    #[TestWith(['game_started', ['story' => 'private writing']])]
    public function test_game_endpoint_rejects_unapproved_or_invalid_measurements(string $action, array $metadata): void
    {
        $game = Game::create(['title' => 'Story', 'slug' => '6-word-story', 'status' => 'available']);
        $this->postJson(route('games.track', $game->slug), ['action' => $action, 'metadata' => ['attempt_id' => (string) Str::uuid(), ...$metadata]])->assertUnprocessable();
        $this->assertDatabaseMissing('analytics_events', ['event_name' => $action]);
    }

    public function test_report_keeps_completed_interrupted_pending_and_missing_outcomes_distinct(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00 UTC'));
        $game = Game::create(['title' => 'Story', 'slug' => '6-word-story', 'status' => 'available']);
        $other = Game::create(['title' => 'Other', 'slug' => 'other', 'status' => 'available']);
        foreach ([
            ['one', 'game_started', '2026-10-20 10:00', []],
            ['one', 'game_completed', '2026-10-20 10:01', ['duration_seconds' => 30, 'ending' => 'timed']],
            ['one', 'game_completed', '2026-10-20 10:02', ['duration_seconds' => 45, 'ending' => 'timed']],
            ['two', 'game_started', '2026-10-20 11:00', []],
            ['two', 'game_interrupted', '2026-10-20 11:01', ['duration_seconds' => 15, 'reason' => 'early_finish']],
            ['three', 'game_started', '2026-10-20 12:00', []],
            ['three', 'game_activity', '2026-10-20 12:01', ['duration_seconds' => 10]],
            ['four', 'game_started', '2026-10-22 11:00', []],
        ] as [$attempt, $event, $at, $metadata]) {
            AnalyticsEvent::create(['event_uuid' => (string) Str::uuid(), 'event_name' => $event, 'visitor_token' => 'game-browser', 'session_token' => 'game-session', 'metadata' => ['game_slug' => $game->slug, 'attempt_id' => $attempt, 'practice_language' => 'ar-eg', 'device_type' => 'mobile', ...$metadata], 'created_at' => $at, 'is_bot' => false]);
        }
        AnalyticsEvent::create(['event_name' => 'game_completed', 'visitor_token' => 'game-browser', 'metadata' => ['game_slug' => $other->slug, 'attempt_id' => 'three'], 'created_at' => '2026-10-20 12:02', 'is_bot' => false]);
        $report = app(GameAnalyticsService::class)->report(CarbonImmutable::parse('2026-10-20'), CarbonImmutable::parse('2026-10-22 12:00'), $game->id);
        $row = $report['rows']->first();
        $this->assertSame(4, $row['attempt_count']);
        $this->assertSame(1, $row['linked_completed']);
        $this->assertSame(25.0, $row['completion_rate']);
        $this->assertSame(1, $row['interrupted']);
        $this->assertSame(1, $row['without_completion']);
        $this->assertSame(1, $row['pending']);
        $this->assertSame(1, $row['repeat_browsers']);
        $this->assertSame(3, $row['duration_samples']);
        $this->assertSame(55, $row['active_seconds']);
        $this->assertSame(1, $row['play_sessions']);
        $this->assertEquals(55, $row['average_session_seconds']);
        $this->assertSame(4, $report['breakdowns']['practice_language']['ar-eg']);
        $this->assertArrayNotHasKey('attempts', $row);
    }

    public function test_games_analytics_requires_an_administrator(): void
    {
        $this->get(route('admin.analytics.games'))->assertRedirect(route('admin.login'));
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'staff']))->get(route('admin.analytics.games'))->assertForbidden();
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']))->get(route('admin.analytics.games'))->assertOk()->assertSeeText('Games Analytics')->assertSeeText('No activity yet.');
    }

    public function test_retained_daily_totals_do_not_invent_detailed_attempt_history(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00 UTC'));
        $game = Game::create(['title' => 'Story', 'slug' => '6-word-story', 'status' => 'available']);
        DB::table('daily_metrics')->insert(['metric_date' => '2026-10-20', 'reporting_timezone' => 'Africa/Cairo', 'dimension_key' => 'game', 'dimension_value' => (string) $game->id, 'metric_name' => 'opened', 'count' => 42, 'created_at' => now(), 'updated_at' => now()]);
        $start = CarbonImmutable::parse('2026-10-20', 'Africa/Cairo')->utc();
        $row = app(GameAnalyticsService::class)->report($start, $start->addDay()->subMicrosecond(), $game->id)['rows']->first();
        $this->assertSame(42, $row['opened']);
        $this->assertTrue($row['uses_history']);
        $this->assertSame(0, $row['attempt_count']);
        $this->assertNull($row['average_seconds']);
    }
}
