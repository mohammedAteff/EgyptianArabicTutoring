<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\MeetingProvider;
use App\Domains\Booking\Models\MeetingRoom;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\MeetingLinkService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Notifications\Services\TelegramNotificationService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class TelegramMultiRecipientReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('telegram.center_migrated', false);

        Setting::set('telegram.bot_token', Crypt::encryptString('123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11'), 'telegram', false);
        Setting::set('telegram.reminders_enabled', true, 'telegram', false);
        Setting::set('telegram.reminder_windows', [1440, 120], 'telegram', false);
        Setting::set('telegram.notification_chat_ids', ['11111', '22222'], 'telegram', false);
    }

    public function test_dynamic_multi_recipient_reminders_and_compound_deduplication(): void
    {
        Setting::set('video_meeting_url', 'https://meet.example.com/abdallah', 'booking', false);
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => ['message_id' => 999]], 200),
        ]);

        $sessionType = SessionType::create([
            'slug' => 'test-session',
            'title' => 'Test Arabic Session',
            'duration_minutes' => 60,
            'price_amount' => 50.00,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $contact = Contact::create([
            'first_name' => 'Sara',
            'last_name' => 'Connor',
            'email' => 'sara@example.com',
            'phone' => '+201000000000',
        ]);

        // Booking starting in 90 minutes (falls within 120m and 1440m windows)
        $startUtc = Carbon::now('UTC')->addMinutes(90);
        $endUtc = $startUtc->copy()->addMinutes(60);
        $snapshot = app(TimezoneService::class)
            ->createBookingSnapshot($startUtc, $endUtc, 'Europe/London', 'Africa/Cairo');

        $booking = Booking::create(array_merge($snapshot, [
            'session_type_id' => $sessionType->id,
            'contact_id' => $contact->id,
            'status' => 'confirmed',
            'idempotency_key' => (string) Str::uuid(),
            'confirmation_token' => Str::random(64),
            'admin_reconfirmation_needed' => false,
        ]));

        $provider = MeetingProvider::where('is_default', true)->firstOrFail();
        $room = MeetingRoom::create(['meeting_provider_id' => $provider->id, 'name' => 'QA reminder room', 'url' => 'https://meet.example.com/abdallah', 'url_hash' => hash('sha256', 'https://meet.example.com/abdallah')]);
        app(MeetingLinkService::class)->assign($booking, roomId: $room->id);

        // 1. Run reminder command
        $this->artisan('booking:send-telegram-reminders')
            ->assertSuccessful();

        // 2. Verified HTTP requests sent for each recipient chat ID and window
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && $request['chat_id'] === '11111'
                && str_contains($request['text'], 'https://meet.example.com/abdallah');
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && $request['chat_id'] === '22222';
        });

        // 3. Verified compound idempotency keys in booking_events
        $this->assertDatabaseHas('booking_events', [
            'booking_id' => $booking->id,
            'event_type' => 'telegram_reminder_sent',
            'idempotency_key' => "reminder_{$booking->id}_120m_11111",
        ]);

        $this->assertDatabaseHas('booking_events', [
            'booking_id' => $booking->id,
            'event_type' => 'telegram_reminder_sent',
            'idempotency_key' => "reminder_{$booking->id}_120m_22222",
        ]);

        // 4. Repeated execution skips without sending duplicate HTTP calls
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true], 200),
        ]);

        $this->artisan('booking:send-telegram-reminders')
            ->assertSuccessful();

        // No new calls sent because all milestones are already deduplicated
        Http::assertNothingSent();
    }

    public function test_reminders_skipped_when_disabled(): void
    {
        Http::fake();
        Setting::set('telegram.reminders_enabled', false, 'telegram', false);

        $this->artisan('booking:send-telegram-reminders')
            ->expectsOutput('Telegram reminders are currently disabled in Settings.')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_no_hardcoded_milestone_is_sent_when_admin_removes_all_windows(): void
    {
        Http::fake();
        Setting::set('telegram.reminder_windows', [], 'telegram', false);

        $this->artisan('booking:send-telegram-reminders')
            ->expectsOutput('No Telegram reminder milestones are configured in Settings.')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_admin_can_send_test_telegram_notification(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => ['message_id' => 123]], 200),
        ]);

        $admin = Administrator::create([
            'name' => 'Admin Bot Tester',
            'email' => 'bot_tester@example.com',
            'password' => bcrypt('Secret123!'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'web')
            ->post(route('admin.settings.telegram.test'))
            ->assertRedirect()
            ->assertSessionHas('success');

        Http::assertSent(function ($request) {
            return str_contains($request['text'], 'Telegram Bot Test Alert');
        });
    }

    public function test_undecryptable_bot_token_fails_closed_without_sending_a_request(): void
    {
        Http::fake();
        Setting::set('telegram.bot_token', 'not-an-encrypted-token', 'telegram', false);

        $this->assertFalse(app(TelegramNotificationService::class)->sendTestNotification('11111'));
        Http::assertNothingSent();
    }

    public function test_admin_can_replace_milestones_and_recipients_and_turn_reminders_off(): void
    {
        $admin = Administrator::create([
            'name' => 'Reminder Admin',
            'email' => 'reminder-admin@example.com',
            'password' => bcrypt('Secret123!'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $baseSettings = [
            'site_name' => 'Arabic Tutor',
            'business_timezone' => 'Africa/Cairo',
            'default_language' => 'en',
            'hero_title' => 'Learn Egyptian Arabic',
            'hero_subtitle' => 'Private lessons',
            'cancellation_policy' => 'Cancel with notice',
            'rescheduling_policy' => 'Reschedule with notice',
            'booking_instructions' => 'Join online',
            'telegram_settings_present' => '1',
        ];

        $this->actingAs($admin, 'web')
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Telegram Bots')->assertDontSee('Add reminder milestone')
            ->assertDontSee('Add recipient chat ID');

        $this->post(route('admin.settings.update'), array_merge($baseSettings, [
            'telegram_reminders_enabled' => '1',
            'telegram_reminder_windows' => ['2880', '60', '15'],
            'telegram_notification_chat_ids' => ['-100123456789', '123456789'],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame([2880, 60, 15], Setting::get('telegram.reminder_windows'));
        $this->assertSame(['-100123456789', '123456789'], Setting::get('telegram.notification_chat_ids'));
        $this->assertTrue((bool) Setting::get('telegram.reminders_enabled'));

        $this->post(route('admin.settings.update'), array_merge($baseSettings, [
            'telegram_reminder_windows' => ['120'],
            'telegram_notification_chat_ids' => ['-100123456789'],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse((bool) Setting::get('telegram.reminders_enabled'));
        $this->assertSame([120], Setting::get('telegram.reminder_windows'));
        $this->assertSame(['-100123456789'], Setting::get('telegram.notification_chat_ids'));
    }

    public function test_invalid_milestones_and_chat_ids_are_rejected_without_changing_configuration(): void
    {
        $admin = Administrator::create([
            'name' => 'Reminder Admin',
            'email' => 'reminder-validation@example.com',
            'password' => bcrypt('Secret123!'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'web')->post(route('admin.settings.update'), [
            'site_name' => 'Arabic Tutor',
            'business_timezone' => 'Africa/Cairo',
            'default_language' => 'en',
            'hero_title' => 'Learn Egyptian Arabic',
            'hero_subtitle' => 'Private lessons',
            'cancellation_policy' => 'Cancel with notice',
            'rescheduling_policy' => 'Reschedule with notice',
            'booking_instructions' => 'Join online',
            'telegram_settings_present' => '1',
            'telegram_reminder_windows' => ['0', '60', '60'],
            'telegram_notification_chat_ids' => ['not-a-chat-id'],
        ])->assertSessionHasErrors(['telegram_reminder_windows.0', 'telegram_reminder_windows.2', 'telegram_notification_chat_ids.0']);

        $this->assertSame([1440, 120], Setting::get('telegram.reminder_windows'));
        $this->assertSame(['11111', '22222'], Setting::get('telegram.notification_chat_ids'));
        $this->assertTrue((bool) Setting::get('telegram.reminders_enabled'));
    }
}
