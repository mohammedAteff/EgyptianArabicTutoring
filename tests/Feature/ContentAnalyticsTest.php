<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Services\ContentAnalyticsService;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Games\Models\Game;
use App\Domains\Reporting\Services\ReportService;
use App\Domains\Resources\Models\ResourceDownload;
use App\Domains\Resources\Models\ResourceRequest;
use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Database\Factories\ResourceFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContentAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_completion_matches_the_same_game_visitor_and_attempt_and_excludes_invalid_scores(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00 UTC'));
        $game = Game::create(['title' => 'Vocabulary', 'slug' => 'vocabulary', 'status' => 'available']);
        Game::create(['title' => 'Numbers', 'slug' => 'numbers', 'status' => 'available']);
        $from = CarbonImmutable::parse('2026-10-20 00:00:00 UTC');
        $to = CarbonImmutable::parse('2026-10-22 23:59:59 UTC');
        foreach ([
            ['vocabulary', 'alice', 'one', 'game_started', '2026-10-20 10:00', []],
            ['vocabulary', 'alice', 'one', 'game_completed', '2026-10-20 10:01', ['score' => 4, 'total' => 5, 'duration_seconds' => 60]],
            ['vocabulary', 'alice', 'one', 'game_completed', '2026-10-20 10:02', ['score' => 4, 'total' => 5]],
            ['vocabulary', 'alice', 'two', 'game_started', '2026-10-20 11:00', []],
            ['numbers', 'alice', 'two', 'game_completed', '2026-10-20 11:01', []],
            ['vocabulary', 'bob', 'three', 'game_started', '2026-10-20 12:00', []],
            ['vocabulary', 'alice', 'three', 'game_completed', '2026-10-20 12:01', []],
            ['vocabulary', 'alice', 'four', 'game_started', '2026-10-22 11:00', []],
            ['vocabulary', 'alice', 'four', 'game_completed', '2026-10-22 11:01', ['score' => 10, 'total' => 5]],
            ['vocabulary', 'alice', null, 'game_started', '2026-10-20 13:00', []],
        ] as [$slug, $visitor, $attempt, $event, $at, $metadata]) {
            AnalyticsEvent::create(['event_uuid' => (string) Str::uuid(), 'event_name' => $event, 'visitor_token' => $visitor, 'metadata' => ['game_slug' => $slug, 'attempt_id' => $attempt] + $metadata, 'created_at' => $at, 'is_bot' => false]);
        }
        $row = app(ContentAnalyticsService::class)->games($from, $to, $game->id)['rows']->first();
        $this->assertSame(4, $row['attempts']);
        $this->assertSame(2, $row['linked_completed']);
        $this->assertSame(50.0, $row['completion_rate']);
        $this->assertSame(1, $row['score_samples']);
        $this->assertSame(80.0, $row['average_score']);
        $this->assertSame(60, $row['average_seconds']);
        $this->assertSame(1, $row['legacy_starts']);
        $this->assertSame(2, $row['without_completion']);
    }

    public function test_resource_conversion_links_requests_in_the_period_instead_of_dividing_all_download_actions(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00 UTC'));
        $resource = ResourceFactory::new()->create();
        $contact = Contact::create(['name' => 'Example contact', 'email' => 'resource@example.org']);
        $old = ResourceRequest::create(['resource_id' => $resource->id, 'contact_id' => $contact->id, 'created_at' => '2026-10-01 12:00:00']);
        $one = ResourceRequest::create(['resource_id' => $resource->id, 'contact_id' => $contact->id, 'created_at' => '2026-10-21 12:00:00']);
        ResourceRequest::create(['resource_id' => $resource->id, 'contact_id' => $contact->id, 'created_at' => '2026-10-21 13:00:00']);
        foreach ([$old, $one, $one] as $request) {
            ResourceDownload::create(['resource_id' => $resource->id, 'contact_id' => $contact->id, 'request_id' => $request->id, 'created_at' => '2026-10-21 14:00:00']);
        }
        $row = app(ContentAnalyticsService::class)->resources(CarbonImmutable::parse('2026-10-20'), CarbonImmutable::parse('2026-10-22 23:59:59'), $resource->id)['rows']->first();
        $this->assertSame(2, $row['requests']);
        $this->assertSame(3, $row['download_actions']);
        $this->assertSame(1, $row['linked_downloads']);
        $this->assertSame(50.0, $row['download_rate']);
        $this->assertSame(1, $row['returning_requesters']);
        $this->assertSame(0, $row['new_requesters']);
        $this->assertTrue($row['external']);
        $website = app(ReportService::class)->getResourcesReport(CarbonImmutable::parse('2026-10-20'), CarbonImmutable::parse('2026-10-22 23:59:59'), ['resource_id' => $resource->id])['rows']->first();
        $this->assertSame('50%', $website['conversion_pct']);
        $this->assertSame(1, $website['requests_followed_by_access']);
    }

    public function test_empty_and_external_game_reports_do_not_invent_completion_or_duration(): void
    {
        $game = Game::create(['title' => 'External', 'slug' => 'external', 'status' => 'available', 'target_url' => 'https://example.org/game']);
        $row = app(ContentAnalyticsService::class)->games(CarbonImmutable::now()->subDays(7), CarbonImmutable::now(), $game->id)['rows']->first();
        $this->assertTrue($row['external']);
        $this->assertNull($row['completion_rate']);
        $this->assertNull($row['average_score']);
        $this->assertNull($row['average_seconds']);
        $this->assertSame(0, $row['attempts']);
    }

    public function test_game_period_respects_cairo_midnight_and_excludes_bot_events(): void
    {
        $game = Game::create(['title' => 'Vocabulary', 'slug' => 'vocabulary', 'status' => 'available']);
        $start = CarbonImmutable::parse('2026-10-21', 'Africa/Cairo')->utc();
        foreach ([[$start->subSecond(), false], [$start, false], [$start->addDay(), false], [$start->addHour(), true]] as [$at, $bot]) {
            AnalyticsEvent::create(['event_name' => 'game_opened', 'metadata' => ['game_slug' => $game->slug], 'created_at' => $at, 'is_bot' => $bot]);
        }
        $row = app(ContentAnalyticsService::class)->games($start, $start->addDay()->subMicrosecond(), $game->id)['rows']->first();

        $this->assertSame(1, $row['opened']);
    }

    public function test_item_totals_survive_raw_event_pruning_and_reaggregation_does_not_erase_them(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00 UTC'));
        $game = Game::create(['title' => 'Vocabulary', 'slug' => 'vocabulary', 'status' => 'available']);
        $resource = ResourceFactory::new()->create();
        $day = CarbonImmutable::parse('2026-10-20', 'Africa/Cairo');
        AnalyticsEvent::create(['event_name' => 'game_opened', 'metadata' => ['game_slug' => $game->slug], 'created_at' => $day->utc()->addHour(), 'is_bot' => false]);
        AnalyticsEvent::create(['event_name' => 'resource_gate_viewed', 'metadata' => ['resource_slug' => $resource->slug], 'created_at' => $day->utc()->addHour(), 'is_bot' => false]);
        $service = app(ContentAnalyticsService::class);
        $service->aggregateDay($day);
        $service->aggregateDay($day);
        AnalyticsEvent::query()->delete();
        $service->aggregateDay($day);
        $gameRow = $service->games($day->utc(), $day->endOfDay()->utc(), $game->id)['rows']->first();
        $resourceRow = $service->resources($day->utc(), $day->endOfDay()->utc(), $resource->id)['rows']->first();
        $this->assertSame(1, $gameRow['opened']);
        $this->assertSame(0, $gameRow['attempts']);
        $this->assertTrue($gameRow['uses_durable_history']);
        $this->assertSame(1, $resourceRow['gate_views']);
        $this->assertTrue($resourceRow['uses_durable_history']);
        $partial = $service->games($day->utc()->addHours(4), $day->endOfDay()->utc(), $game->id)['rows']->first();
        $this->assertSame(0, $partial['opened']);
    }

    public function test_later_bookings_are_linked_to_the_same_browser_after_engagement_within_the_period(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00 UTC'));
        $game = Game::create(['title' => 'Vocabulary', 'slug' => 'vocabulary', 'status' => 'available']);
        $resource = ResourceFactory::new()->create();
        $visitor = (string) Str::uuid();
        $contact = Contact::create(['name' => 'Example requester', 'email' => 'requester@example.org']);
        ResourceRequest::create(['resource_id' => $resource->id, 'contact_id' => $contact->id, 'visitor_token' => $visitor, 'created_at' => '2026-10-21 09:00:00']);
        AnalyticsEvent::create(['event_name' => 'game_opened', 'visitor_token' => $visitor, 'metadata' => ['game_slug' => $game->slug], 'created_at' => '2026-10-21 09:00:00', 'is_bot' => false]);
        foreach ([[$visitor, '08:00:00', 'confirmed'], [$visitor, '10:00:00', 'confirmed'], [$visitor, '11:00:00', 'cancelled'], [(string) Str::uuid(), '11:00:00', 'confirmed']] as [$token, $at, $status]) {
            BookingFactory::new()->create(['visitor_token' => $token, 'created_at' => '2026-10-21 '.$at, 'status' => $status]);
        }
        $from = CarbonImmutable::parse('2026-10-21 00:00 UTC');
        $through = CarbonImmutable::parse('2026-10-21 23:59:59 UTC');
        $service = app(ContentAnalyticsService::class);
        $this->assertSame(1, $service->games($from, $through, $game->id)['rows']->first()['later_bookings']);
        $this->assertSame(1, $service->resources($from, $through, $resource->id)['rows']->first()['later_bookings']);
    }
}
