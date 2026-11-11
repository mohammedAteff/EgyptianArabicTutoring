<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Models\DailyMetric;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Reporting\Services\ReportService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FocusedAnalyticsCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    private function event(string $date, ?string $source, string $visitor, string $name = 'page_view', bool $bot = false): void
    {
        AnalyticsEvent::create(['event_name' => $name, 'visitor_token' => $visitor, 'session_token' => $visitor.'-session',
            'utm_source' => $source, 'page' => '/', 'is_bot' => $bot,
            'created_at' => CarbonImmutable::parse($date.' 12:00:00', 'Africa/Cairo')->utc()]);
    }

    private function report(?string $source = null): array
    {
        return app(ReportService::class)->getTrafficReport(CarbonImmutable::parse('2026-09-10', 'Africa/Cairo')->utc(), CarbonImmutable::parse('2026-09-11', 'Africa/Cairo')->endOfDay()->utc(), $source);
    }

    public static function retentionStates(): array
    {
        return ['raw' => [0], 'mixed' => [1], 'retained' => [2]];
    }

    #[DataProvider('retentionStates')]
    public function test_daily_source_visitors_rank_consistently_independently_of_event_volume(int $prunedDays): void
    {
        $this->freezeTime();
        foreach (['2026-09-10', '2026-09-11'] as $index => $date) {
            for ($i = 0; $i < 10; $i++) {
                $this->event($date, 'Alpha', 'returning-alpha');
            }
            $this->event($date, 'Beta', 'returning-beta');
            $this->event($date, 'Beta', 'other-beta');
            $this->event($date, 'Bot', 'excluded-bot', bot: true);
            $this->artisan('analytics:aggregate-daily', ['--date' => $date])->assertSuccessful();
            if ($index < $prunedDays) {
                AnalyticsEvent::whereDate('created_at', $date)->delete();
            }
        }

        $report = $this->report();

        $this->assertSame('Beta', $report['summary']['top_source']);
        $this->assertSame('sum_of_daily_source_visitors', $report['summary']['top_source_basis']);
        $this->assertSame(['Beta', 'Beta'], $report['rows']->pluck('top_source')->all());
        $this->assertSame(24, $report['summary']['page_views']);
        $this->assertSame($prunedDays === 0 ? 3 : 6, $report['summary']['visitors']);
        $this->assertSame('Alpha', $this->report('Alpha')['summary']['top_source']);
        $this->assertSame(20, $this->report('Alpha')['summary']['page_views']);
    }

    public function test_returning_browsers_contribute_on_each_day_and_ties_use_source_name(): void
    {
        $this->freezeTime();
        $this->event('2026-09-10', 'Alpha', 'returning');
        $this->event('2026-09-11', 'Alpha', 'returning');
        $this->event('2026-09-10', 'Beta', 'one-off');
        $this->event('2026-09-10', 'Beta', 'another-one-off');
        $this->assertSame('Alpha', $this->report()['summary']['top_source']);

        foreach (['2026-09-10', '2026-09-11'] as $date) {
            $this->artisan('analytics:aggregate-daily', ['--date' => $date])->assertSuccessful();
        }
        AnalyticsEvent::query()->delete();

        $this->assertSame('Alpha', $this->report()['summary']['top_source']);
    }

    public function test_missing_source_history_disables_period_ranking_without_changing_traffic_totals(): void
    {
        $this->freezeTime();
        DailyMetric::create(['metric_date' => '2026-09-10', 'metric_name' => 'unique_visitors', 'dimension_key' => '', 'dimension_value' => '', 'count' => 7]);
        $this->event('2026-09-11', 'Beta', 'current');

        $report = $this->report();

        $this->assertNull($report['summary']['top_source']);
        $this->assertStringContainsString('coverage is missing', $report['summary']['top_source_explanation']);
        $this->assertNull($report['rows']->firstWhere('report_date', '2026-09-10')->top_source);
        $this->assertSame(8, $report['summary']['visitors']);
        $this->assertNull($this->report('Beta')['summary']['top_source']);
    }

    public function test_unassigned_sources_are_not_invented_as_direct_and_empty_period_is_unavailable(): void
    {
        $this->freezeTime();
        $this->assertNull($this->report()['summary']['top_source']);
        $this->event('2026-09-10', null, 'unassigned');
        $this->event('2026-09-10', '', 'empty-source');
        $this->assertNull($this->report()['summary']['top_source']);
        $this->artisan('analytics:aggregate-daily', ['--date' => '2026-09-10'])->assertSuccessful();
        AnalyticsEvent::query()->delete();
        $this->assertNull($this->report()['summary']['top_source']);
        $this->assertSame(2, $this->report()['summary']['visitors']);
    }

    public function test_traffic_screen_csv_and_xlsx_share_ranking_basis_and_unavailable_coverage(): void
    {
        $this->freezeTime();
        $this->event('2026-09-11', 'Beta', 'current');
        DailyMetric::create(['metric_date' => '2026-09-10', 'metric_name' => 'unique_visitors', 'dimension_key' => '', 'dimension_value' => '', 'count' => 7]);
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']), 'web');
        $parameters = ['type' => 'traffic', 'range' => 'custom', 'start_date' => '2026-09-10', 'end_date' => '2026-09-11'];

        $this->get(route('admin.reports.index', $parameters))->assertSeeText('Period Top Source:')->assertSeeText('sum of distinct browser visitors per business day')->assertSeeText('Unavailable');
        $csv = $this->get(route('admin.reports.export', $parameters + ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Top Source (sum of daily browser visitors; recorded sources; ties by source name)', $csv);
        $this->assertStringContainsString('coverage is missing', $csv);
        $xlsx = $this->get(route('admin.reports.export', $parameters + ['format' => 'xlsx']))->assertOk();
        $workbook = IOFactory::load($xlsx->baseResponse->getFile()->getPathname());
        $sheet = $workbook->getActiveSheet();
        $this->assertSame('Beta', $sheet->getCell('E2')->getValue());
        $this->assertSame('Unavailable', $sheet->getCell('E3')->getValue());
        $this->assertSame('Unavailable', $sheet->getCell('E4')->getValue());
        $this->assertStringContainsString('coverage is missing', $sheet->getCell('F4')->getValue());
        $workbook->disconnectWorksheets();
    }

    public function test_disjoint_resource_and_game_visitors_remain_independent_activity_counts(): void
    {
        $this->freezeTime();
        foreach (['resource_gate_viewed' => 1, 'resource_requested' => 2, 'resource_downloaded' => 3, 'game_opened' => 1, 'game_started' => 1, 'game_completed' => 2] as $name => $count) {
            for ($i = 0; $i < $count; $i++) {
                $this->event('2026-09-10', 'Alpha', $name.$i, $name);
            }
        }
        $service = app(AnalyticsService::class);
        $start = CarbonImmutable::parse('2026-09-10', 'Africa/Cairo')->utc();
        $end = $start->addDay()->subSecond();

        $this->assertSame(['gate_viewed' => 1, 'requested' => 2, 'downloaded' => 3], $service->getResourceFunnel($start, $end));
        $this->assertSame(['opened' => 1, 'started' => 1, 'completed' => 2], $service->getGameFunnel($start, $end));
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']), 'web')
            ->get(route('admin.analytics', ['range' => 'custom', 'start_date' => '2026-09-10', 'end_date' => '2026-09-10']))
            ->assertSeeText('Resource activity')->assertSeeText('Game activity')->assertSeeText('These independent counts do not establish a conversion journey.')
            ->assertDontSeeText('Resource Lead Capture Funnel')->assertDontSeeText('(200%)')->assertDontSeeText('(150%)');
        $this->assertSame(['gate_viewed' => 0, 'requested' => 0, 'downloaded' => 0], $service->getResourceFunnel($start->subDays(3), $start->subDays(2)));
        $this->assertSame(['opened' => 0, 'started' => 0, 'completed' => 0], $service->getGameFunnel($start->subDays(3), $start->subDays(2)));
    }
}
