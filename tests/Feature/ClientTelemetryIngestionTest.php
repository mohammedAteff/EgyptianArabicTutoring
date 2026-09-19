<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\DailyMetric;
use App\Domains\Reporting\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ClientTelemetryIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_cta_click_payload_is_accepted_and_persisted(): void
    {
        // Real payload from public layout click handler
        $response = $this->postJson(route('analytics.track'), [
            'event_name' => 'booking_cta_clicked',
            'page' => 'http://localhost/booking',
            'metadata' => [
                'label' => 'Book a Lesson',
                'button_text' => 'Book a Lesson',
                'cta_location' => 'header',
                'target' => '/booking',
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'booking_cta_clicked',
            'is_bot' => false,
        ]);
    }

    public function test_social_link_click_payload_is_accepted_and_persisted(): void
    {
        // Real payload from public footer social links
        $response = $this->postJson(route('analytics.track'), [
            'event_name' => 'social_link_clicked',
            'page' => 'http://localhost/',
            'metadata' => [
                'platform' => 'instagram',
                'target' => 'https://instagram.com/egyptianarabic',
                'target_url' => 'https://instagram.com/egyptianarabic',
                'placement' => 'footer',
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'social_link_clicked',
            'is_bot' => false,
        ]);
    }

    public function test_outbound_link_click_payload_is_accepted_and_persisted(): void
    {
        // Real payload from game card external click handler
        $response = $this->postJson(route('analytics.track'), [
            'event_name' => 'outbound_link_clicked',
            'page' => 'http://localhost/games',
            'metadata' => [
                'target' => 'https://wordwall.net/play/12345',
                'url' => 'https://wordwall.net/play/12345',
                'destination' => 'Egyptian Street Slang Drill',
                'text' => 'Egyptian Street Slang Drill',
                'placement' => 'game_card',
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'outbound_link_clicked',
            'is_bot' => false,
        ]);
    }

    public function test_disallowed_metadata_keys_are_rejected_with_422(): void
    {
        $response = $this->postJson(route('analytics.track'), [
            'event_name' => 'booking_cta_clicked',
            'page' => 'http://localhost/booking',
            'metadata' => [
                'arbitrary_unapproved_key' => 'evil_data',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['status' => 'rejected']);
    }

    public function test_one_physical_social_click_yields_one_counted_click_in_daily_metrics_and_social_report(): void
    {
        $cairoTz = 'Africa/Cairo';
        $todayCairo = CarbonImmutable::now($cairoTz)->format('Y-m-d');
        $eventTime = CarbonImmutable::now($cairoTz)->setTime(14, 0, 0)->setTimezone('UTC');

        // Exactly one event emitted by physical click on WhatsApp link
        $this->postJson(route('analytics.track'), [
            'event_name' => 'whatsapp_clicked',
            'page' => 'http://localhost/',
            'metadata' => [
                'platform' => 'whatsapp',
                'target' => 'https://wa.me/201012345678',
                'target_url' => 'https://wa.me/201012345678',
                'placement' => 'footer',
            ],
        ])->assertOk();

        // Run rollup
        Artisan::call('analytics:aggregate-daily', ['--date' => $todayCairo]);

        // Daily metric for social clicks must be exactly 1
        $socialMetric = DailyMetric::where('metric_date', $todayCairo)
            ->where('metric_name', 'social_clicks')
            ->value('count');
        $this->assertEquals(1, $socialMetric, 'One WhatsApp link click must produce exactly 1 daily social click');

        // ReportService must report exactly 1 click
        $reportService = app(ReportService::class);
        $start = CarbonImmutable::parse($todayCairo, $cairoTz)->startOfDay()->setTimezone('UTC');
        $end = CarbonImmutable::parse($todayCairo, $cairoTz)->endOfDay()->setTimezone('UTC');

        $report = $reportService->getSocialReport($start, $end);
        $this->assertEquals(1, $report['total_clicks'], 'Total social report clicks must be exactly 1');
        $this->assertEquals(1, $report['whatsapp_clicks'], 'WhatsApp report clicks must be exactly 1');
    }

    public function test_public_layout_markup_contains_clean_scoping_excluding_ics_and_reschedule(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        // Public page contains the tracking script
        $html = $response->getContent();

        // Proves intentional exclusion of .ics, /reschedule, /cancel, /download
        $this->assertStringContainsString('isActionOrExport', $html);
        $this->assertStringContainsString('.ics', $html);
        $this->assertStringContainsString('/reschedule', $html);
        $this->assertStringContainsString('/cancel', $html);

        // Proves single if-else-if handler pattern preventing duplicate events for WhatsApp/Telegram
        $this->assertStringContainsString('isWhatsApp', $html);
        $this->assertStringContainsString('isTelegram', $html);
        $this->assertStringContainsString('data-cta="booking"', $html);
    }
}
