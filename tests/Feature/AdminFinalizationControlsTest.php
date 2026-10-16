<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Models\Visitor;
use App\Domains\Analytics\Services\EngagementCounterService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\Booking\Models\MeetingProvider;
use App\Domains\Booking\Models\MeetingRoom;
use App\Domains\CMS\Models\Setting;
use App\Domains\CMS\Models\SocialLink;
use App\Domains\Reporting\Services\ReportService;
use App\Domains\Students\Models\PaymentMethod;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentLedgerService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AdminFinalizationControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_method_removal_preserves_recorded_payments_and_compacts_visible_positions(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        PaymentMethod::query()->update(['active' => false, 'is_default' => false]);
        $first = PaymentMethod::factory()->create(['active' => true, 'is_default' => true, 'sort_order' => 0]);
        $removed = PaymentMethod::factory()->create(['active' => true, 'sort_order' => 4]);
        $last = PaymentMethod::factory()->create(['active' => true, 'sort_order' => 5]);
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'Paid purchase', 2, '60.00', '0.00', 'USD', null, 'preserved-payment', entitlementCode: 'one_hour');
        $payment = $ledger->recordPayment($package, '60.00', 'recorded-payment', $admin->id, paymentMethodId: $removed->id);
        $original = $payment->fresh()->getAttributes();

        $this->actingAs($admin)->delete(route('admin.payment-methods.destroy', $removed))->assertSessionHasNoErrors();

        $this->assertSoftDeleted($removed);
        $this->assertSame($original, $payment->fresh()->getAttributes());
        $positions = PaymentMethod::query()->orderBy('sort_order')->orderBy('id')->pluck('sort_order')->all();
        $this->assertSame(range(0, count($positions) - 1), $positions);
        $this->assertModelExists($last);
        $this->get(route('admin.payment-methods.index'))->assertSee('Removed payment methods')->assertSee('Restore method');
        $this->post(route('admin.payment-methods.restore', $removed->id))->assertSessionHasNoErrors();
        $this->assertNotSoftDeleted($removed);
        $this->assertFalse($removed->fresh()->active);
        $this->assertTrue($first->fresh()->is_default);
    }

    public function test_default_payment_method_moves_to_an_enabled_replacement_and_last_enabled_method_is_protected(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        PaymentMethod::query()->update(['active' => false, 'is_default' => false]);
        $default = PaymentMethod::factory()->create(['active' => true, 'is_default' => true]);
        $replacement = PaymentMethod::factory()->create(['active' => true]);
        $this->actingAs($admin)->delete(route('admin.payment-methods.destroy', $default))->assertSessionHasNoErrors();
        $this->assertTrue($replacement->fresh()->is_default);
        $this->delete(route('admin.payment-methods.destroy', $replacement))->assertSessionHasErrors('method');
        $this->assertNotSoftDeleted($replacement);
    }

    public function test_provider_removal_preserves_assigned_links_and_can_be_restored(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $provider = MeetingProvider::factory()->create(['active' => true]);
        $room = MeetingRoom::factory()->create(['meeting_provider_id' => $provider->id]);
        $student = Student::factory()->verified()->create(['preferred_meeting_provider_id' => $provider->id]);
        $booking = Booking::factory()->create(['student_id' => $student->id, 'meeting_room_id' => $room->id, 'meeting_url_snapshot' => $room->url, 'meeting_provider_snapshot' => $provider->name]);
        $original = $booking->fresh()->getAttributes();

        $this->actingAs($admin)->delete(route('admin.meeting-links.providers.destroy', $provider))->assertSessionHasNoErrors();

        $this->assertSoftDeleted($provider);
        $this->assertSame($original, $booking->fresh()->getAttributes());
        $this->assertNull($student->fresh()->preferred_meeting_provider_id);
        $this->assertModelExists($room);
        $this->post(route('admin.meeting-links.rooms'), ['meeting_provider_id' => $provider->id, 'name' => 'Rejected room', 'url' => 'https://example.org/rejected-room', 'active' => true])->assertSessionHasErrors('meeting_provider_id');
        $this->get(route('admin.meeting-links.index'))->assertSee('Removed providers')->assertSee('Restore provider');
        $this->post(route('admin.meeting-links.providers.restore', $provider->id))->assertSessionHasNoErrors();
        $this->assertFalse($provider->fresh()->active);
    }

    public function test_social_channel_removal_and_restore_change_only_the_available_channel(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $social = SocialLink::query()->create(['platform' => 'x', 'label' => 'Our X profile', 'url_or_phone' => 'https://x.com/example', 'enabled' => true, 'sort_order' => 1]);
        $this->actingAs($admin)->delete(route('admin.content.social.destroy', $social))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($social);
        $this->get('/')->assertDontSee('Our X profile');
        $this->get(route('admin.content.index'))->assertSee('Restore channel');
        $this->post(route('admin.content.social.restore', $social->id))->assertSessionHasNoErrors();
        $this->assertFalse($social->fresh()->enabled);
    }

    #[TestWith(['facebook'])]
    #[TestWith(['linkedin'])]
    #[TestWith(['x'])]
    public function test_supported_footer_brands_use_their_own_logo(string $platform): void
    {
        SocialLink::query()->create(['platform' => $platform, 'label' => 'Brand', 'url_or_phone' => 'https://example.org/profile', 'enabled' => true, 'sort_order' => 1]);
        $this->get('/')->assertSee('data-brand-icon="'.$platform.'"', false);
    }

    public function test_assistants_cannot_remove_or_restore_administrative_options(): void
    {
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'assistant']));
        $method = PaymentMethod::factory()->create();
        $provider = MeetingProvider::factory()->create();
        $social = SocialLink::query()->create(['platform' => 'x', 'label' => 'X', 'url_or_phone' => 'https://x.com', 'enabled' => false, 'sort_order' => 1]);
        $this->delete(route('admin.payment-methods.destroy', $method))->assertForbidden();
        $this->delete(route('admin.meeting-links.providers.destroy', $provider))->assertForbidden();
        $this->delete(route('admin.content.social.destroy', $social))->assertForbidden();
        $this->post(route('admin.payment-methods.restore', 999))->assertForbidden();
        $this->post(route('admin.meeting-links.providers.restore', 999))->assertForbidden();
        $this->post(route('admin.content.social.restore', 999))->assertForbidden();
        $this->assertNotSoftDeleted($method);
        $this->assertNotSoftDeleted($provider);
        $this->assertNotSoftDeleted($social);
    }

    public function test_booking_timeline_shows_readable_details_without_raw_payload_and_old_links_work(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $oldToken = str_repeat('A', 64);
        $booking = Booking::factory()->create(['confirmation_token' => $oldToken]);
        BookingEvent::query()->create(['booking_id' => $booking->id, 'event_type' => 'created', 'performed_by' => 'student', 'created_at' => now(), 'new_data' => ['status' => 'confirmed', 'source' => 'student_portal', 'confirmation_token' => 'HIDDEN-IN-TIMELINE', 'start_at_utc' => '2026-10-17 06:00:00']]);
        $this->actingAs($admin)->get(route('admin.bookings.show', $booking))->assertSee('Lesson starts:')->assertSee('Student Portal')->assertDontSee('HIDDEN-IN-TIMELINE')->assertDontSee('{&quot;status&quot;', false)->assertSee('break-all');
        $this->get(route('booking.confirmation', $oldToken))->assertOk();
        $this->assertSame($oldToken, $booking->fresh()->confirmation_token);
    }

    public function test_page_view_counter_counts_only_eligible_page_views_inside_the_previous_business_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16 12:00:00', 'Africa/Cairo'));
        Setting::set('business_timezone', 'Africa/Cairo');
        Setting::set('counters.monthly_traffic.source', 'page_views');
        Setting::set('counters.monthly_traffic.public_enabled', true);
        $inside = CarbonImmutable::parse('2026-09-15 12:00:00', 'Africa/Cairo')->utc();
        $visitor = Visitor::query()->create(['visitor_token' => 'month-human', 'first_seen_at' => $inside, 'last_seen_at' => $inside, 'is_bot' => false]);
        foreach ([$inside, $inside->addDay(), $inside->addMonth()] as $time) {
            AnalyticsEvent::query()->create(['visitor_token' => $visitor->visitor_token, 'event_name' => 'page_view', 'page' => '/', 'is_bot' => false, 'created_at' => $time]);
        }
        AnalyticsEvent::query()->create(['visitor_token' => 'bot', 'event_name' => 'page_view', 'page' => '/', 'is_bot' => true, 'created_at' => $inside]);
        $payload = app(EngagementCounterService::class)->getCachedPublicPayload();
        $this->assertSame(2, $payload['monthly_traffic']['count']);
        $this->assertSame('Our pages were viewed 2 times last month', $payload['monthly_traffic']['text']);
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.settings.index'))->assertSee('Page Views')->assertDontSee('Legacy Lesson Video Meeting Link');
    }

    public function test_daily_traffic_uses_a_bounded_number_of_timezone_reads_and_preserves_counts(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16 12:00:00', 'UTC'));
        $start = CarbonImmutable::parse('2026-10-01 00:00:00', 'UTC');
        $visitor = Visitor::query()->create(['visitor_token' => 'performance-human', 'first_seen_at' => $start, 'last_seen_at' => now(), 'is_bot' => false]);
        foreach (range(1, 10) as $day) {
            foreach (range(1, 10) as $event) {
                AnalyticsEvent::query()->create(['visitor_token' => $visitor->visitor_token, 'event_name' => 'page_view', 'page' => '/', 'is_bot' => false, 'created_at' => $start->addDays($day)]);
            }
        }
        DB::enableQueryLog();
        $traffic = app(ReportService::class)->getTrafficReport($start, CarbonImmutable::parse('2026-10-16 23:59:59', 'UTC'));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame(100, $traffic['summary']['page_views']);
        $this->assertSame(1, $traffic['summary']['visitors']);
        $this->assertLessThan(70, count($queries), 'Report query count must not grow with every event and calendar day.');
    }

    public function test_reports_keep_only_the_filtered_export_pair(): void
    {
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']));
        foreach ([route('admin.analytics'), route('admin.analytics.countries'), route('admin.analytics.sections'), route('admin.billing.cashier'), ...array_map(fn (string $type): string => route('admin.reports.index', ['type' => $type]), ['traffic', 'bookings', 'resources', 'social', 'events', 'campaigns'])] as $url) {
            $response = $this->get($url)->assertOk();
            $document = new \DOMDocument;
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertSame(2, $xpath->query('//main//a[contains(@href,"format=csv") or contains(@href,"format=xlsx")]')->length, $url);
            $this->assertSame(2, $xpath->query('//main//form//a[contains(@href,"format=csv") or contains(@href,"format=xlsx")]')->length, $url);
        }
    }
}
