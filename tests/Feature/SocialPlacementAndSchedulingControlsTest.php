<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Services\SocialAnalyticsRollup;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\CMS\Models\SocialLink;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SocialPlacementAndSchedulingControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_social_icons_are_shared_between_the_banner_and_footer(): void
    {
        foreach (SocialLink::PLATFORMS as $platform) {
            $this->channel($platform);
        }

        $response = $this->get('/');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        foreach (SocialLink::PLATFORMS as $platform) {
            $banner = $xpath->query('//nav[@data-social-banner]//a[@data-social-platform="'.$platform.'"]//svg')->item(0);
            $footer = $xpath->query('//footer//a[@data-social-platform="'.$platform.'"]//svg')->item(0);
            $this->assertNotNull($banner, $platform);
            $this->assertNotNull($footer, $platform);
            $this->assertSame($document->saveXML($footer), $document->saveXML($banner));
        }
    }

    public function test_channel_placements_and_global_banner_visibility_save_independently(): void
    {
        $channel = $this->channel();
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.content.social.update'), ['socials' => [$channel->id => ['url_or_phone' => $channel->url_or_phone, 'label' => $channel->label, 'enabled' => true, 'show_in_banner' => false, 'show_in_footer' => true, 'sort_order' => 0]]])->assertSessionHasNoErrors();

        $this->assertFalse($channel->fresh()->show_in_banner);
        $this->assertTrue($channel->fresh()->show_in_footer);
        $this->get('/')->assertDontSee('data-social-banner', false)->assertSee('data-social-placement="footer_social"', false);
        $channel->refresh()->update(['show_in_banner' => true, 'show_in_footer' => false]);
        $this->get('/')->assertSee('data-social-placement="top_banner"', false)->assertDontSee('data-social-placement="footer_social"', false);
        $this->post(route('admin.content.social.display'), ['banner_enabled' => false])->assertSessionHasNoErrors();
        $this->assertFalse((bool) Setting::get('social.banner_enabled'));
        $this->get('/')->assertDontSee('data-social-banner', false);
        $this->assertDatabaseHas('audit_logs', ['action' => 'social_banner_visibility_updated']);
    }

    public function test_disabled_channels_never_appear_in_either_placement(): void
    {
        $this->channel()->update(['enabled' => false]);

        $this->get('/')->assertDontSee('data-social-placement="top_banner"', false)->assertDontSee('data-social-placement="footer_social"', false);
    }

    public function test_new_admin_controls_require_authentication_and_an_admin_role(): void
    {
        $rule = $this->rule();
        $this->put(route('admin.availability.rules.update', $rule), $this->ruleInput())->assertRedirect(route('admin.login'));
        $this->post(route('admin.content.social.display'), ['banner_enabled' => false])->assertRedirect(route('admin.login'));
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'assistant']));
        $this->put(route('admin.availability.rules.update', $rule), $this->ruleInput())->assertForbidden();
        $this->post(route('admin.content.social.display'), ['banner_enabled' => false])->assertForbidden();
        $this->assertSame(10, $rule->fresh()->buffer_minutes);
    }

    public function test_banner_and_footer_payloads_are_counted_once_and_show_click_times(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 15:20:30', 'Africa/Cairo'));
        foreach (['top_banner', 'footer_social'] as $placement) {
            $this->postJson(route('analytics.track'), ['event_uuid' => (string) Str::uuid(), 'event_name' => 'social_link_clicked', 'page' => '/social-qa', 'metadata' => ['platform' => 'youtube', 'placement' => $placement, 'target_url' => 'https://youtube.com/example', 'context' => 'public', 'language' => 'en']])->assertOk();
        }
        AnalyticsEvent::query()->create(['event_uuid' => (string) Str::uuid(), 'event_name' => 'social_link_clicked', 'visitor_token' => 'bot', 'page' => '/', 'metadata' => ['platform' => 'youtube', 'placement' => 'top_banner'], 'is_bot' => true, 'created_at' => now('UTC')]);
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']));

        $this->get(route('admin.reports.index', ['type' => 'social', 'range' => 'today']))->assertSee('Individual click times')->assertSee('Oct 19, 2026')->assertViewHas('reportData', fn ($data) => $data['total_clicks'] === 2 && $data['placement_totals']['top_banner'] === 1 && $data['placement_totals']['footer_social'] === 1 && $data['recent_clicks']->count() === 2);
        $this->get(route('admin.reports.index', ['type' => 'social', 'range' => 'today', 'placement' => 'top_banner', 'platform' => 'YouTube']))->assertViewHas('reportData', fn ($data) => $data['total_clicks'] === 1 && $data['recent_clicks']->count() === 1 && $data['recent_clicks']->first()->placement === 'top_banner');
    }

    #[TestWith(['csv'])]
    #[TestWith(['xlsx'])]
    public function test_individual_click_exports_include_business_time_and_respect_placement_filters(string $format): void
    {
        $time = CarbonImmutable::parse('2026-10-19 15:20:30', 'Africa/Cairo');
        $this->travelTo($time);
        $this->click($time, 'top_banner');
        $this->click($time->subMinute(), 'footer_social');
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']));

        $response = $this->get(route('admin.reports.export', ['type' => 'social', 'range' => 'today', 'placement' => 'top_banner', 'detail' => 'clicks', 'format' => $format]))->assertOk();

        if ($format === 'csv') {
            $csv = $response->streamedContent();
            $this->assertStringContainsString('2026-10-19 15:20:30+03:00', $csv);
            $this->assertStringContainsString('top_banner', $csv);
            $this->assertStringNotContainsString('footer_social', $csv);
        } else {
            $workbook = IOFactory::load($response->baseResponse->getFile()->getPathname());
            $sheet = $workbook->getActiveSheet();
            $this->assertSame('2026-10-19 15:20:30+03:00', $sheet->getCell('A2')->getValue());
            $this->assertSame('top_banner', $sheet->getCell('C2')->getValue());
            $this->assertSame(2, $sheet->getHighestRow());
            $workbook->disconnectWorksheets();
        }
    }

    public function test_click_pagination_keeps_filters_and_durable_totals_survive_pruning(): void
    {
        $time = CarbonImmutable::parse('2026-10-19 12:00:00', 'Africa/Cairo');
        $this->travelTo($time->addHour());
        foreach (range(0, 25) as $minute) {
            $this->click($time->subMinutes($minute), 'top_banner');
        }
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.reports.index', ['type' => 'social', 'range' => 'today', 'placement' => 'top_banner', 'clicks_page' => 2]))->assertViewHas('reportData', fn ($data) => $data['recent_clicks']->count() === 1 && $data['total_clicks'] === 26);
        $rollup = app(SocialAnalyticsRollup::class);
        $rollup->aggregateDay($time);
        AnalyticsEvent::query()->delete();

        $this->get(route('admin.reports.index', ['type' => 'social', 'range' => 'today', 'placement' => 'top_banner']))->assertViewHas('reportData', fn ($data) => $data['total_clicks'] === 26 && $data['recent_clicks']->isEmpty())->assertSee('No individual clicks are available');
    }

    #[TestWith([0])]
    #[TestWith([15])]
    public function test_existing_window_buffer_can_be_changed_without_touching_appointments(int $buffer): void
    {
        $rule = $this->rule();
        $booking = Booking::factory()->create();
        $before = $booking->fresh()->getAttributes();
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.availability.rules.update', $rule), [...$this->ruleInput(), 'buffer_minutes' => $buffer])->assertSessionHasNoErrors();

        $this->assertSame($buffer, $rule->fresh()->buffer_minutes);
        $this->assertSame($before, $booking->fresh()->getAttributes());
        $this->assertDatabaseHas('audit_logs', ['action' => 'availability_rule_updated', 'entity_id' => $rule->id]);
        $this->get(route('admin.availability.index'))->assertSee('Edit hours &amp; buffer', false)->assertSee('Buffer between lessons');
    }

    public function test_invalid_window_returns_clear_feedback_and_keeps_the_submitted_values(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $payload = [...$this->ruleInput(), 'end_time' => '09:30', 'availability_form' => 'create'];

        $response = $this->actingAs($admin)->from(route('admin.availability.index'))->post(route('admin.availability.rules.store'), $payload);

        $response->assertSessionHasErrors(['end_time' => 'This window is shorter than one 50-minute lesson. Extend the window or shorten the lesson. The buffer is the gap between lessons; the last lesson only needs to finish by the window end.']);
        $this->assertDatabaseCount('availability_rules', 0);
        $this->withCookie(config('session.cookie'), session()->getId())->get(route('admin.availability.index'))->assertSee('shorter than one 50-minute lesson')->assertSee('value="09:30"', false);
    }

    public function test_editing_a_buffer_preserves_inherited_lesson_length(): void
    {
        $rule = $this->rule();
        $rule->update(['session_duration_minutes' => null]);
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']));

        $this->put(route('admin.availability.rules.update', $rule), [...$this->ruleInput(), 'session_duration_minutes' => null, 'buffer_minutes' => 5])->assertSessionHasNoErrors();

        $this->assertNull($rule->fresh()->session_duration_minutes);
        $this->assertSame(5, $rule->fresh()->buffer_minutes);
        $this->get(route('admin.availability.index'))->assertSee('Lesson type duration');
    }

    #[TestWith([-1])]
    #[TestWith([61])]
    public function test_out_of_range_buffer_does_not_change_the_window(int $buffer): void
    {
        $rule = $this->rule();
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']));

        $this->put(route('admin.availability.rules.update', $rule), [...$this->ruleInput(), 'buffer_minutes' => $buffer])->assertSessionHasErrors('buffer_minutes');

        $this->assertSame(10, $rule->fresh()->buffer_minutes);
    }

    public function test_ten_minute_buffer_is_explained_when_an_admin_uses_an_off_grid_start(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 12:00:00', 'Africa/Cairo'));
        $rule = $this->rule();
        $type = SessionType::factory()->create(['duration_minutes' => 50]);
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']));

        $response = $this->post(route('admin.bookings.store'), ['session_type_id' => $type->id, 'student_name' => 'Off-grid student', 'student_email' => 'off-grid@example.test', 'date' => '2026-10-26', 'time' => '09:50']);

        $response->assertSessionHas('error', fn ($message) => str_contains($message, '50-minute lessons and a 10-minute buffer') && str_contains($message, '09:00 and 10:00'));
        $this->assertDatabaseCount('bookings', 0);
        $slots = app(AvailabilityService::class)->getAvailableSlotsGroupedByDate($type, 'Africa/Cairo', CarbonImmutable::parse('2026-10-26', 'Africa/Cairo'), CarbonImmutable::parse('2026-10-26', 'Africa/Cairo'));
        $this->assertSame(['09:00', '10:00', '11:00', '12:00'], array_column($slots['2026-10-26'], 'business_start_time'));
    }

    public function test_all_weekdays_can_run_from_eight_until_midnight_with_ten_minute_buffers(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 00:00', 'Africa/Cairo'));
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $type = SessionType::factory()->create(['duration_minutes' => 60]);
        foreach (range(0, 6) as $weekday) {
            $this->actingAs($admin)->post(route('admin.availability.rules.store'), [...$this->ruleInput(), 'weekday' => $weekday, 'start_time' => '08:00', 'end_time' => '00:00', 'session_duration_minutes' => null])->assertSessionHasNoErrors();
        }

        $slots = app(AvailabilityService::class)->getAvailableSlotsGroupedByDate($type, 'Africa/Cairo', CarbonImmutable::parse('2026-10-26', 'Africa/Cairo'), CarbonImmutable::parse('2026-11-01', 'Africa/Cairo'));

        $this->assertDatabaseCount('availability_rules', 7);
        $this->assertCount(7, $slots);
        foreach ($slots as $daySlots) {
            $this->assertSame('08:00', $daySlots[0]['business_start_time']);
            $this->assertSame('22:00', end($daySlots)['business_start_time']);
            $this->assertSame(10, $daySlots[0]['buffer_minutes']);
        }
        $this->assertSame(['24:00:00'], AvailabilityRule::query()->distinct()->pluck('end_time')->all());
    }

    public function test_a_lesson_can_finish_exactly_at_midnight_and_the_editor_round_trips_it(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 00:00', 'Africa/Cairo'));
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $rule = $this->rule();
        $type = SessionType::factory()->create(['duration_minutes' => 120]);
        $this->actingAs($admin)->put(route('admin.availability.rules.update', $rule), [...$this->ruleInput(), 'start_time' => '22:00', 'end_time' => '00:00', 'session_duration_minutes' => null])->assertSessionHasNoErrors();
        $start = CarbonImmutable::parse('2026-10-26 22:00', 'Africa/Cairo')->utc();
        $end = $start->addHours(2);

        $configuration = app(AvailabilityService::class)->resolveSlotConfiguration($type, $start, $end);

        $this->assertSame(120, $configuration['duration_minutes']);
        $this->assertSame(10, $configuration['buffer_minutes']);
        $this->get(route('admin.availability.index'))->assertSee('value="00:00"', false)->assertSee('the following day');
    }

    public function test_earlier_end_time_creates_an_overnight_schedule(): void
    {
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']))->post(route('admin.availability.rules.store'), [...$this->ruleInput(), 'start_time' => '08:00', 'end_time' => '07:00'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('availability_rules', ['start_time' => '08:00:00', 'end_time' => '31:00:00']);
    }

    private function channel(string $platform = 'youtube'): SocialLink
    {
        return SocialLink::query()->create(['platform' => $platform, 'label' => ucfirst($platform), 'url_or_phone' => $platform === 'whatsapp' ? '+12025550123' : 'https://example.org/'.$platform, 'enabled' => true, 'sort_order' => 0]);
    }

    private function rule(): AvailabilityRule
    {
        return AvailabilityRule::query()->create([...$this->ruleInput(), 'start_time' => '09:00:00', 'end_time' => '13:00:00', 'enabled' => true]);
    }

    /** @return array<string, mixed> */
    private function ruleInput(): array
    {
        return ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '13:00', 'session_duration_minutes' => 50, 'buffer_minutes' => 10, 'min_notice_hours' => 0, 'max_horizon_days' => 60];
    }

    private function click(CarbonImmutable $time, string $placement): void
    {
        AnalyticsEvent::query()->create(['event_uuid' => (string) Str::uuid(), 'event_name' => 'social_link_clicked', 'visitor_token' => 'social-human', 'page' => '/social-qa', 'metadata' => ['platform' => 'youtube', 'placement' => $placement, 'language' => 'en', 'context' => 'public'], 'is_bot' => false, 'created_at' => $time->utc()]);
    }
}
