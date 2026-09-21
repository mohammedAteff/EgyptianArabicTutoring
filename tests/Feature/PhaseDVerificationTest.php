<?php

namespace Tests\Feature;

use App\Services\TimezoneDisplayService;
use Carbon\Carbon;
use Tests\TestCase;

class PhaseDVerificationTest extends TestCase
{
    /** @test */
    public function test_slot_utc_offset_calculates_against_slot_scheduled_instant_16_3(): void
    {
        $service = app(TimezoneDisplayService::class);

        // Europe/Berlin: UTC+2 in summer (CEST), UTC+1 in winter (CET)
        $summerSlotInstant = Carbon::parse('2026-07-15 14:00:00', 'UTC');
        $winterSlotInstant = Carbon::parse('2027-01-15 14:00:00', 'UTC');

        $summerDisplay = $service->formatSlotForDisplay('Europe/Berlin', $summerSlotInstant);
        $winterDisplay = $service->formatSlotForDisplay('Europe/Berlin', $winterSlotInstant);

        $this->assertEquals('UTC+2', $summerDisplay['utc_offset']);
        $this->assertEquals('UTC+1', $winterDisplay['utc_offset']);
        $this->assertEquals('DE', $summerDisplay['timezone_country_code']);
        $this->assertEquals('Germany', $summerDisplay['timezone_country_name']);
        $this->assertStringContainsString('flags/4x3/de.svg', $summerDisplay['flag_asset']);
    }

    /** @test */
    public function test_format_slot_for_display_supports_reversed_parameter_order(): void
    {
        $service = app(TimezoneDisplayService::class);
        $instant = Carbon::parse('2026-07-15 14:00:00', 'UTC');

        $display = $service->formatSlotForDisplay($instant, 'Europe/Berlin');

        $this->assertEquals('UTC+2', $display['utc_offset']);
        $this->assertEquals('DE', $display['timezone_country_code']);
        $this->assertEquals('Berlin', $display['city']);
    }

    /** @test */
    public function test_neutral_utc_fallback_formatting(): void
    {
        $service = app(TimezoneDisplayService::class);
        $instant = Carbon::parse('2026-07-15 14:00:00', 'UTC');

        $display = $service->formatSlotForDisplay('UTC', $instant);

        $this->assertEquals('UTC', $display['utc_offset']);
        $this->assertNull($display['timezone_country_code']);
        $this->assertEquals('Unknown / Unresolved', $display['timezone_country_name']);
        $this->assertStringContainsString('flags/4x3/globe.svg', $display['flag_asset']);
        $this->assertEquals('UTC', $display['city']);
        $this->assertEquals('UTC (Coordinated Universal Time)', $display['label']);
    }

    /** @test */
    public function test_offset_uses_the_absolute_instant_even_when_input_has_another_timezone(): void
    {
        $service = app(TimezoneDisplayService::class);
        // 08:30 in Cairo is 06:30 UTC, before New York's 2026 spring-forward
        // transition. Reconstructing the wall-clock text as UTC would be 08:30
        // UTC and incorrectly report the post-transition UTC-4 offset.
        $instant = Carbon::parse('2026-03-08 08:30:00', 'Africa/Cairo');

        $display = $service->formatSlotForDisplay('America/New_York', $instant);

        $this->assertEquals('UTC-5', $display['utc_offset']);
    }

    /** @test */
    public function test_svg_flag_assets_exist_on_filesystem(): void
    {
        $flags = [
            'de', 'eg', 'us', 'gb', 'fr', 'ca', 'au', 'it', 'es', 'nl', 'at',
            'be', 'ch', 'ar', 'br', 'ae', 'sa', 'kw', 'jp', 'sg', 'globe',
        ];

        foreach ($flags as $flag) {
            $path = public_path("assets/flags/4x3/{$flag}.svg");
            $this->assertFileExists($path, "Flag asset {$flag}.svg must exist in public/assets/flags/4x3/");
            $content = file_get_contents($path);
            $this->assertStringContainsString('<svg', $content);
        }
    }

    /** @test */
    public function test_supported_non_european_timezone_uses_its_local_flag_asset(): void
    {
        $service = app(TimezoneDisplayService::class);
        $display = $service->formatSlotForDisplay(
            'Asia/Tokyo',
            Carbon::parse('2026-07-15 14:00:00', 'UTC'),
        );

        $this->assertEquals('JP', $display['timezone_country_code']);
        $this->assertStringEndsWith('flags/4x3/jp.svg', $display['flag_asset']);
    }
}
