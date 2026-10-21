<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Models\DailyCountryMetric;
use App\Domains\Analytics\Models\DailyMetric;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Notifications\Models\TelegramDelivery;
use App\Domains\Notifications\Services\TelegramAutomationService;
use App\Domains\Notifications\Services\TelegramCommandService;
use App\Domains\Notifications\Services\TelegramDeliveryService;
use App\Domains\Notifications\Services\TelegramReadService;
use App\Domains\Notifications\Services\TelegramReportFormatter;
use App\Domains\Notifications\Services\TelegramTemplateService;
use App\Domains\Reporting\Services\ReportService;
use App\Jobs\DeliverTelegramMessage;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Database\Factories\TelegramBotFactory;
use Database\Factories\TelegramDestinationFactory;
use Database\Factories\TelegramRuleFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TelegramAnalyticsPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_country_ranking_filters_unresolved_before_limiting_without_changing_reports_or_records(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-21 12:00', 'Africa/Cairo'));
        foreach (['ZZ' => 57, 'EG' => 52, 'US' => 10, 'NL' => 4, 'MX' => 3, 'FR' => 2, 'DE' => 1] as $code => $count) {
            DailyCountryMetric::create(['reporting_timezone' => 'Africa/Cairo', 'metric_date' => '2026-10-20', 'country_code' => $code, 'unique_visitors' => $count, 'visitors' => $count]);
        }
        $start = CarbonImmutable::parse('2026-10-15', 'Africa/Cairo');
        $end = CarbonImmutable::parse('2026-10-21 23:59:59', 'Africa/Cairo');
        $before = app(AnalyticsService::class)->reportingCountries($start, $end)->toJson();
        $traffic = app(ReportService::class)->getTrafficReport($start, $end)['summary'];

        $report = app(TelegramReadService::class)->stats(7);
        $plain = app(TelegramReportFormatter::class)->format($report)['text'];

        $this->assertStringContainsString("Top countries · visitors\n🇪🇬 Egypt — 52\n🇺🇸 United States — 10\n🇳🇱 Netherlands — 4\n🇲🇽 Mexico — 3\n🇫🇷 France — 2", $plain);
        $this->assertStringNotContainsString('Germany', $plain);
        $this->assertStringNotContainsString('ZZ', $plain);
        $this->assertStringNotContainsString('Historical periods', $plain);
        $this->assertSame($before, app(AnalyticsService::class)->reportingCountries($start, $end)->toJson());
        $this->assertSame(json_encode($traffic, JSON_THROW_ON_ERROR), json_encode(app(ReportService::class)->getTrafficReport($start, $end)['summary'], JSON_THROW_ON_ERROR));
        $this->assertDatabaseHas('daily_country_metrics', ['country_code' => 'ZZ', 'unique_visitors' => 57]);
    }

    public function test_page_links_hide_raw_urls_and_escape_analytics_values(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-21 12:00', 'Africa/Cairo'));
        config(['app.url' => 'https://example.com/arabictutor']);
        foreach (['https://example.com/arabictutor/_arabictutor-landing', '/arabictutor/booking', '/pricing?topic=one&other="two"', 'javascript:<b>Forged</b>'] as $page) {
            AnalyticsEvent::create(['event_name' => 'page_view', 'page' => $page, 'metadata' => [], 'is_bot' => false, 'created_at' => now('UTC')]);
        }

        $report = app(TelegramReadService::class)->stats(1);
        $formatted = app(TelegramReportFormatter::class)->format($report);
        $links = collect($formatted['entities'])->where('type', 'text_link')->pluck('url')->all();

        $this->assertContains('https://example.com/arabictutor/', $links);
        $this->assertContains('https://example.com/arabictutor/booking', $links);
        $this->assertContains('https://example.com/arabictutor/pricing?topic=one&other=%22two%22', $links);
        $this->assertCount(3, $links);
        $this->assertStringContainsString('Home — 1', $formatted['text']);
        $this->assertStringContainsString('Booking — 1', $formatted['text']);
        $this->assertStringContainsString('javascript:<b>Forged</b> — 1', $formatted['text']);
        $this->assertStringNotContainsString('https://example.com', $formatted['text']);
        $this->assertStringContainsString('No country data.', $formatted['text']);
        $this->assertStringContainsString('Direct traffic — 0', $formatted['text']);
        $this->assertStringNotContainsString(' / none', $formatted['text']);
    }

    #[TestWith(['analytics_digest'])]
    #[TestWith(['business_digest'])]
    public function test_digest_delivery_keeps_custom_template_literal_and_sends_clickable_labels_without_previews(string $trigger): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
        Queue::fake();
        Setting::set('telegram.automation_enabled', true);
        $bot = TelegramBotFactory::new()->create();
        $destination = TelegramDestinationFactory::new()->create(['telegram_bot_id' => $bot->id]);
        $rule = TelegramRuleFactory::new()->create(['telegram_bot_id' => $bot->id, 'trigger' => $trigger, 'template' => '<b>Owner</b> 😀 {digest}', 'mode' => 'on_demand']);
        $rule->destinations()->attach($destination);

        app(TelegramAutomationService::class)->emit($trigger, 'format-test', ['digest' => '<b>Top pages</b>'."\n".'<a href="https://example.com/?a=1&amp;b=2">Home &amp; news</a> — 8']);
        $delivery = TelegramDelivery::firstOrFail();
        app(TelegramDeliveryService::class)->deliver($delivery->id);

        $this->assertSame('sent', $delivery->fresh()->status);
        Http::assertSent(function (Request $request): bool {
            $this->assertSame("<b>Owner</b> 😀 Top pages\nHome & news — 8", $request['text']);
            $this->assertSame([['type' => 'bold', 'offset' => 16, 'length' => 9], ['type' => 'text_link', 'offset' => 26, 'length' => 11, 'url' => 'https://example.com/?a=1&b=2']], $request['entities']);
            $this->assertSame(['is_disabled' => true], $request['link_preview_options']);
            $this->assertFalse(isset($request['parse_mode']));

            return true;
        });
        Queue::assertPushed(DeliverTelegramMessage::class);
    }

    public function test_stats_command_uses_the_same_formatted_report(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-21 12:00', 'Africa/Cairo'));
        Http::preventStrayRequests();
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
        Setting::set('telegram.commands_enabled', true);
        $bot = TelegramBotFactory::new()->create(['commands_enabled' => true, 'commands' => ['stats']]);
        $destination = TelegramDestinationFactory::new()->create(['telegram_bot_id' => $bot->id, 'allowed_user_ids' => ['777']]);

        app(TelegramCommandService::class)->handle($bot, ['update_id' => 123, 'message' => ['chat' => ['id' => $destination->chat_id], 'from' => ['id' => '777'], 'text' => '/stats 7d']]);

        Http::assertSent(fn (Request $request): bool => str_starts_with($request['text'], "Analytics\n15 Oct 2026 – 21 Oct 2026") && $request['entities'][0] === ['type' => 'bold', 'offset' => 0, 'length' => 9] && ! str_contains($request['text'], 'Historical'));
    }

    public function test_long_reports_split_without_losing_text_or_unicode_entity_positions(): void
    {
        $formatter = app(TelegramReportFormatter::class);
        $text = str_repeat('😀', 1748).'Home'.str_repeat('x', 4000);
        $parts = $formatter->split($text, [['type' => 'text_link', 'offset' => 3496, 'length' => 4, 'url' => 'https://example.com']]);

        $this->assertSame($text, implode('', $parts['parts']));
        $this->assertSame([['type' => 'text_link', 'offset' => 3496, 'length' => 4, 'url' => 'https://example.com']], $parts['entities'][0]);
        foreach ($parts['parts'] as $part) {
            $this->assertLessThanOrEqual(3500, $formatter->units($part));
        }
        $this->assertSame(['parts' => ['<b>Name</b> [private]'], 'entities' => [[]]], app(TelegramTemplateService::class)->deliveryParts('booking_created', '<b>Name</b> {student_name}', ['student_name' => 'Private person'], false));
    }

    public function test_website_reports_omit_historical_analytics_warnings(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-21 12:00', 'Africa/Cairo'));
        DailyMetric::create(['reporting_timezone' => 'Africa/Cairo', 'metric_date' => '2025-01-01', 'metric_name' => 'unique_visitors', 'count' => 12]);
        $admin = AdministratorFactory::new()->create(['role' => 'super_admin']);

        $this->actingAs($admin, 'web')->get(route('admin.reports.index', ['type' => 'traffic', 'range' => 'custom', 'start_date' => '2025-01-01', 'end_date' => '2025-01-02']))
            ->assertOk()->assertDontSee('Non-comparable')->assertDontSee('Raw events pruned')->assertDontSee('Historical Data')->assertSee('Sum of Daily Unique Visitors');
    }
}
