<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingHoldService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Services\FormBuilderService;
use App\Domains\Notifications\Models\TelegramBot;
use App\Domains\Notifications\Models\TelegramDelivery;
use App\Domains\Notifications\Models\TelegramDestination;
use App\Domains\Notifications\Models\TelegramRule;
use App\Domains\Notifications\Services\TelegramAutomationService;
use App\Domains\Notifications\Services\TelegramCommandService;
use App\Domains\Notifications\Services\TelegramDeliveryService;
use App\Domains\Notifications\Services\TelegramLegacyImporter;
use App\Domains\Notifications\Services\TelegramReadService;
use App\Domains\Notifications\Services\TelegramRuleCatalog;
use App\Domains\Notifications\Services\TelegramTemplateService;
use App\Domains\Reporting\Services\ReportService;
use App\Domains\Resources\Models\ResourceCategory;
use App\Domains\Resources\Models\ResourceDownload;
use App\Domains\Resources\Models\ResourceRequest;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\System\Services\BackupService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Database\Factories\TelegramBotFactory;
use Database\Factories\TelegramDestinationFactory;
use Database\Factories\TelegramRuleFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TelegramAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 123]], 200)]);
        Queue::fake();
        Setting::set('telegram.automation_enabled', true);
    }

    /** @return array{TelegramBot,TelegramDestination,TelegramRule} */
    private function configured(string $trigger = 'booking_created', array $attributes = []): array
    {
        $bot = TelegramBotFactory::new()->create();
        $dest = TelegramDestinationFactory::new()->create(['telegram_bot_id' => $bot->id]);
        $rule = TelegramRuleFactory::new()->create(array_merge(['telegram_bot_id' => $bot->id, 'trigger' => $trigger, 'template' => app(TelegramRuleCatalog::class)->get($trigger)['template']], $attributes));
        $rule->destinations()->attach($dest);

        return [$bot, $dest, $rule];
    }

    private function booking(): Booking
    {
        $type = SessionType::create(['slug' => 'qa-'.Str::random(8), 'title' => 'QA Diagnostic', 'duration_minutes' => 60, 'price_amount' => 25, 'currency' => 'USD', 'is_active' => true]);
        $student = Student::factory()->verified()->create(['first_name' => 'QA', 'last_name' => 'learner', 'email' => 'qa@example.org']);
        $contact = Contact::create(['name' => 'QA learner', 'email' => 'qa@example.org']);
        $start = now('UTC')->addMinutes(30);
        $snapshot = app(TimezoneService::class)->createBookingSnapshot($start, $start->copy()->addHour(), 'Europe/Berlin', 'Africa/Cairo');

        return Booking::create(array_merge($snapshot, ['contact_id' => $contact->id, 'session_type_id' => $type->id, 'student_id' => $student->id, 'status' => 'confirmed', 'idempotency_key' => (string) Str::uuid(), 'confirmation_token' => Str::random(64)]));
    }

    public function test_credentials_are_encrypted_hidden_and_never_returned_or_flashed(): void
    {
        [$bot] = $this->configured();
        $this->assertNotSame($bot->token, DB::table('telegram_bots')->value('token'));
        $this->assertArrayNotHasKey('token', $bot->toArray());
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']), 'web')->get(route('admin.telegram.index', ['tab' => 'bots']))->assertOk()->assertDontSee($bot->token);
        $this->post(route('admin.telegram.bot'), ['name' => 'QA', 'token' => 'invalid-secret'])->assertSessionHasErrors('token')->assertSessionMissing('_old_input.token');
    }

    #[TestWith(['assistant'])] #[TestWith(['viewer'])]
    public function test_unprivileged_roles_cannot_manage_telegram(string $role): void
    {
        $this->actingAs(AdministratorFactory::new()->create(['role' => $role]), 'web')->get(route('admin.telegram.index'))->assertForbidden();
    }

    public function test_legacy_import_preserves_token_windows_recipients_and_is_idempotent(): void
    {
        Setting::set('telegram.center_migrated', false);
        Setting::set('telegram.bot_token', Crypt::encryptString('123456:ABCDEFGHIJKLMNOPQRSTUVWXYZ_legacy'));
        Setting::set('telegram.reminders_enabled', true);
        Setting::set('telegram.notification_chat_ids', ['123', '456']);
        Setting::set('telegram.reminder_windows', [1440, 120]);
        app(TelegramLegacyImporter::class)->import();
        app(TelegramLegacyImporter::class)->import();
        $this->assertDatabaseCount('telegram_bots', 1);
        $this->assertDatabaseCount('telegram_destinations', 2);
        $this->assertDatabaseCount('telegram_rules', 2);
        $this->assertSame('123456:ABCDEFGHIJKLMNOPQRSTUVWXYZ_legacy', TelegramBot::first()->token);
        $this->assertSame([120, 1440], TelegramRule::orderBy('minutes')->pluck('minutes')->all());
    }

    public function test_multiple_destinations_dedupe_and_summary_redaction(): void
    {
        [$bot,$dest,$rule] = $this->configured();
        $personal = TelegramDestinationFactory::new()->create(['telegram_bot_id' => $bot->id, 'detail_level' => 'personal']);
        $rule->destinations()->attach($personal);
        $engine = app(TelegramAutomationService::class);
        $engine->emit('booking_created', 'booking:1', ['student_name' => 'Private Learner', 'email' => 'private@example.org']);
        $engine->emit('booking_created', 'booking:1', ['student_name' => 'Private Learner', 'email' => 'private@example.org']);
        $this->assertDatabaseCount('telegram_deliveries', 2);
        $summary = TelegramDelivery::where('telegram_destination_id', $dest->id)->first();
        $this->assertStringNotContainsString('private@example.org', implode('', $summary->payload['parts']));
        $this->assertStringContainsString('private@example.org', implode('', TelegramDelivery::where('telegram_destination_id', $personal->id)->first()->payload['parts']));
        $this->assertStringNotContainsString('private@example.org', DB::table('telegram_deliveries')->value('payload'));
    }

    #[TestWith(['global'])] #[TestWith(['bot'])] #[TestWith(['destination'])] #[TestWith(['rule'])]
    public function test_each_switch_prevents_delivery(string $switch): void
    {
        [$bot,$dest,$rule] = $this->configured();
        match ($switch) {
            'global' => Setting::set('telegram.automation_enabled', false),'bot' => $bot->update(['enabled' => false]),'destination' => $dest->update(['enabled' => false]),'rule' => $rule->update(['enabled' => false])
        };
        app(TelegramAutomationService::class)->emit('booking_created', 'disabled', []);
        $this->assertDatabaseCount('telegram_deliveries', 0);
        Http::assertNothingSent();
    }

    public function test_cross_bot_destination_is_rejected_and_template_placeholder_is_validated(): void
    {
        [$bot,$dest] = $this->configured();
        $other = TelegramBotFactory::new()->create();
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']), 'web');
        $data = ['telegram_bot_id' => $other->id, 'name' => 'QA rule', 'trigger' => 'booking_created', 'mode' => 'instant', 'priority' => 'normal', 'minutes' => 60, 'threshold' => 1, 'window_minutes' => 60, 'cooldown_minutes' => 60, 'send_time' => '08:00', 'template' => 'Booking {booking_id}', 'destinations' => [$dest->id]];
        $this->post(route('admin.telegram.rule'), $data)->assertSessionHasErrors('destinations.0');
        $data['telegram_bot_id'] = $bot->id;
        $data['template'] = '{unavailable_secret}';
        $this->post(route('admin.telegram.rule'), $data)->assertSessionHasErrors('template');
    }

    public function test_preview_uses_sample_data_and_never_executes_markup(): void
    {
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']), 'web')->post(route('admin.telegram.preview'), ['trigger' => 'booking_created', 'template' => '<script>{student_name}</script>'])->assertSessionHas('preview', '<script>Example student</script>');
        Http::assertNothingSent();
    }

    public function test_success_test_delivery_is_non_sensitive_and_logged_once(): void
    {
        [$bot,$dest] = $this->configured();
        $result = app(TelegramDeliveryService::class)->test($dest);
        $this->assertSame('sent', $result->status);
        $this->assertSame(['123'], $result->message_ids);
        Http::assertSent(fn ($r) => str_contains($r['text'], 'TEST') && ! isset($r['parse_mode']));
        $this->assertNotNull($bot->fresh()->last_success_at);
        app(TelegramDeliveryService::class)->deliver($result->id, true);
        Http::assertSentCount(1);
    }

    public function test_explicit_rejection_retries_with_backoff_and_stops_after_four_attempts(): void
    {
        [$bot,$dest] = $this->configured();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 429, 'parameters' => ['retry_after' => 60]], 429)]);
        $result = app(TelegramDeliveryService::class)->test($dest);
        $this->assertSame('pending', $result->status);
        $this->assertSame('telegram_429', $result->failure_code);
        $this->assertTrue($result->due_at->isFuture());
        $result->update(['due_at' => now('UTC'), 'attempts' => 3]);
        $this->travel(61)->seconds();
        app(TelegramDeliveryService::class)->deliver($result->id);
        $this->assertSame('failed', $result->fresh()->status);
        $this->assertSame(4, $result->fresh()->attempts);
    }

    public function test_network_failure_is_uncertain_and_does_not_leak_token(): void
    {
        [$bot,$dest] = $this->configured();
        Http::fake(['api.telegram.org/*' => Http::failedConnection()]);
        $result = app(TelegramDeliveryService::class)->test($dest);
        $this->assertSame('uncertain', $result->status);
        $this->assertSame('network_outcome_unknown', $result->failure_code);
        $this->assertStringNotContainsString($bot->token, json_encode($result->toArray()));
    }

    public function test_quiet_hours_delay_normal_messages_but_critical_messages_bypass(): void
    {
        $this->travelTo(Carbon::parse('2026-10-02 23:30', 'Africa/Cairo'));
        [$bot,$dest,$rule] = $this->configured('booking_created', ['quiet_start' => '22:00', 'quiet_end' => '07:00']);
        app(TelegramAutomationService::class)->emit('booking_created', 'one', []);
        $this->assertSame('2026-10-03 07:00', TelegramDelivery::first()->due_at->setTimezone('Africa/Cairo')->format('Y-m-d H:i'));
        $rule->update(['priority' => 'critical']);
        app(TelegramAutomationService::class)->emit('booking_created', 'two', []);
        $this->assertTrue(TelegramDelivery::latest('id')->first()->due_at->lte(now('UTC')));
    }

    public function test_threshold_cooldown_is_persistent(): void
    {
        [$bot,$dest,$rule] = $this->configured('traffic_spike', ['cooldown_minutes' => 60]);
        $engine = app(TelegramAutomationService::class);
        $engine->emit('traffic_spike', 'one', ['unique_visitors' => 50], 'traffic');
        $engine->emit('traffic_spike', 'two', ['unique_visitors' => 55], 'traffic');
        $this->assertDatabaseCount('telegram_deliveries', 1);
        $this->travel(61)->minutes();
        $engine->emit('traffic_spike', 'three', ['unique_visitors' => 60], 'traffic');
        $this->assertDatabaseCount('telegram_deliveries', 2);
    }

    public function test_countdown_uses_confirmed_bookings_and_repeat_ticks_do_not_duplicate(): void
    {
        $this->configured('session_reminder', ['minutes' => 60]);
        $booking = $this->booking();
        app(TelegramReadService::class)->tick();
        app(TelegramReadService::class)->tick();
        $this->assertDatabaseCount('telegram_deliveries', 1);
        $this->assertStringContainsString('QA Diagnostic', implode('', TelegramDelivery::first()->payload['parts']));
        $booking->update(['status' => 'cancelled']);
        $this->travel(1)->minutes();
        app(TelegramReadService::class)->tick();
        $this->assertDatabaseCount('telegram_deliveries', 1);
    }

    public function test_expiring_package_requires_unused_active_credits_and_uses_ledger(): void
    {
        $this->configured('package_expiring', ['threshold' => 14]);
        $student = Student::factory()->verified()->create();
        $package = app(StudentLedgerService::class)->createPackage($student, 'QA package', 8, '280', '25', 'USD', now('Africa/Cairo')->addDays(7)->toDateString(), (string) Str::uuid(), entitlementCode: 'one_hour');
        app(StudentLedgerService::class)->recordPayment($package, '255', (string) Str::uuid(), null);
        app(TelegramReadService::class)->tick();
        app(TelegramReadService::class)->tick();
        $this->assertDatabaseCount('telegram_deliveries', 1);
        $this->assertSame('1-hour sessions: 8', TelegramDelivery::first()->payload['source']['remaining_credits']);
    }

    #[TestWith([true])] #[TestWith([false])]
    public function test_interactive_commands_require_both_chat_and_user_allowlisting(bool $allowed): void
    {
        [$bot,$dest] = $this->configured();
        Setting::set('telegram.commands_enabled', true);
        $bot->update(['commands_enabled' => true, 'commands' => ['today']]);
        $dest->update(['allowed_user_ids' => ['777']]);
        app(TelegramCommandService::class)->handle($bot, ['update_id' => 1, 'message' => ['chat' => ['id' => $dest->chat_id], 'from' => ['id' => $allowed ? '777' : '888'], 'text' => '/today']]);
        $this->assertDatabaseCount('telegram_deliveries', $allowed ? 1 : 0);
    }

    public function test_student_command_does_not_choose_one_of_ambiguous_names(): void
    {
        [$bot,$dest] = $this->configured();
        Setting::set('telegram.commands_enabled', true);
        $bot->update(['commands_enabled' => true, 'commands' => ['student']]);
        $dest->update(['allowed_user_ids' => ['777'], 'detail_level' => 'personal']);
        Student::factory()->verified()->count(2)->create(['first_name' => 'Same', 'last_name' => 'Name', 'name_normalized' => 'same name']);
        app(TelegramCommandService::class)->handle($bot, ['update_id' => 9, 'message' => ['chat' => ['id' => $dest->chat_id], 'from' => ['id' => '777'], 'text' => '/student Same Name']]);
        Http::assertSent(fn ($r) => str_contains($r['text'], 'No unique student match'));
    }

    public function test_polling_advances_offset_and_duplicate_update_is_not_sent_again(): void
    {
        [$bot,$dest] = $this->configured();
        Setting::set('telegram.commands_enabled', true);
        $bot->update(['commands_enabled' => true, 'commands' => ['stats']]);
        $dest->update(['allowed_user_ids' => ['777']]);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*/getUpdates*' => Http::response(['ok' => true, 'result' => [['update_id' => 7, 'message' => ['chat' => ['id' => $dest->chat_id], 'from' => ['id' => '777'], 'text' => '/stats invalid']]]]), '*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
        app(TelegramCommandService::class)->poll();
        $this->assertSame(8, $bot->fresh()->update_offset);
        app(TelegramCommandService::class)->poll();
        $this->assertDatabaseCount('telegram_deliveries', 1);
    }

    public function test_independent_watchdog_checks_scheduler_and_queue_separately(): void
    {
        $this->configured('scheduler_stale', ['minutes' => 15, 'priority' => 'critical']);
        $this->configured('queue_stale', ['minutes' => 15, 'priority' => 'critical']);
        Setting::set('last_scheduler_run_at', now('UTC')->subHour()->toIso8601String());
        Cache::put('queue_worker_heartbeat_at', now('UTC')->toIso8601String());
        app(TelegramReadService::class)->watchdog();
        $this->assertDatabaseCount('telegram_deliveries', 1);
        $this->assertSame('scheduler_stale', TelegramDelivery::first()->trigger);
        Http::assertNothingSent();
    }

    public function test_business_digest_distinguishes_created_bookings_from_sessions_today(): void
    {
        $booking = $this->booking();
        $booking->update(['start_at_utc' => now('UTC')->addDay()]);
        $text = app(TelegramReadService::class)->businessDigest(['bookings']);
        $this->assertStringContainsString('Bookings created today: 1', $text);
        $this->assertStringContainsString('Sessions today: 0', $text);
        $this->assertStringContainsString('Sessions tomorrow: 1', $text);
    }

    public function test_message_splitting_respects_telegram_limit_and_unicode(): void
    {
        $parts = app(TelegramTemplateService::class)->split(str_repeat('م', 8000));
        $this->assertCount(3, $parts);
        foreach ($parts as $part) {
            $this->assertLessThanOrEqual(3500, mb_strlen($part));
        } $this->assertSame(8000, mb_strlen(implode('', $parts)));
    }

    public function test_history_filters_do_not_expose_encrypted_payload(): void
    {
        [$bot,$dest] = $this->configured();
        $delivery = app(TelegramDeliveryService::class)->test($dest);
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']), 'web')->get(route('admin.telegram.index', ['tab' => 'history', 'status' => 'sent', 'bot' => $bot->id]))->assertOk()->assertSee('sent')->assertDontSee('TEST — Telegram connectivity check');
    }

    #[TestWith(['created', 'booking_created'])] #[TestWith(['rescheduled', 'booking_rescheduled'])] #[TestWith(['cancelled', 'booking_cancelled'])]
    public function test_booking_events_emit_only_after_commit_and_rolled_back_events_do_not_alert(string $event, string $trigger): void
    {
        $booking = $this->booking();
        $this->configured($trigger);
        DB::beginTransaction();
        BookingEvent::create(['booking_id' => $booking->id, 'event_type' => $event, 'performed_by' => 'system', 'created_at' => now()]);
        $this->assertDatabaseCount('telegram_deliveries', 0);
        DB::rollBack();
        $this->assertDatabaseCount('telegram_deliveries', 0);
        DB::transaction(fn () => BookingEvent::create(['booking_id' => $booking->id, 'event_type' => $event, 'performed_by' => 'system', 'created_at' => now()]));
        $this->assertDatabaseCount('telegram_deliveries', 1);
        Http::assertNothingSent();
    }

    public function test_expired_hold_alert_requires_valid_collected_contact_and_never_converted_hold(): void
    {
        $booking = $this->booking();
        $this->configured('hold_abandoned');
        foreach (['active', 'converted', 'active'] as $index => $status) {
            $hold = BookingHold::create(['visitor_token' => (string) Str::uuid(), 'session_token' => (string) Str::uuid(), 'hold_token' => Str::random(64), 'session_type_id' => $booking->session_type_id, 'slot_start_utc' => $booking->start_at_utc, 'slot_end_utc' => $booking->end_at_utc, 'expires_at' => now('UTC')->subMinute(), 'status' => $status]);
            if ($index !== 2) {
                $hold->lead_details = ['name' => 'QA hold', 'email' => 'qa-hold@example.org'];
                $hold->save();
            }
        }
        $this->assertSame(2, app(BookingHoldService::class)->cleanExpiredHolds());
        $this->assertDatabaseCount('telegram_deliveries', 1);
        app(BookingHoldService::class)->cleanExpiredHolds();
        $this->assertDatabaseCount('telegram_deliveries', 1);
    }

    public function test_maintenance_transition_and_duration_alert_use_one_incident_and_cooldown(): void
    {
        $this->configured('maintenance_enabled');
        $this->configured('maintenance_duration', ['minutes' => 120]);
        Setting::set('maintenance_mode', false);
        Setting::set('maintenance_mode', true);
        $this->assertDatabaseCount('telegram_deliveries', 1);
        Setting::set('maintenance_mode', true);
        $this->assertDatabaseCount('telegram_deliveries', 1);
        $this->travel(121)->minutes();
        app(TelegramReadService::class)->tick();
        app(TelegramReadService::class)->tick();
        $this->assertDatabaseCount('telegram_deliveries', 2);
    }

    public function test_low_credit_alert_reads_append_only_ledger_and_excludes_expired_packages(): void
    {
        $this->configured('low_credits', ['threshold' => 1]);
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'QA credits', 8, '280', '25', 'USD', now('Africa/Cairo')->addDays(30)->toDateString(), (string) Str::uuid(), entitlementCode: 'one_hour');
        $ledger->recordPayment($package, '255', (string) Str::uuid(), null);
        $this->assertDatabaseCount('telegram_deliveries', 0);
        $ledger->adjustCredits($package, -7, 'QA consumption adjustment', (string) Str::uuid(), allocationId: $package->entitlements()->value('id'));
        app(TelegramReadService::class)->tick();
        $this->assertDatabaseCount('telegram_deliveries', 1);
        $this->assertSame('1-hour sessions: 1', TelegramDelivery::first()->payload['source']['remaining_credits']);
        $package->update(['expiration_date' => now('Africa/Cairo')->subDay()->toDateString()]);
        $this->travel(2)->days();
        app(TelegramReadService::class)->tick();
        $this->assertDatabaseCount('telegram_deliveries', 1);
    }

    public function test_missing_required_form_follows_assignment_and_submission_state(): void
    {
        $booking = $this->booking();
        $this->configured('missing_form', ['minutes' => 720]);
        $builder = app(FormBuilderService::class);
        $form = $builder->create(['title' => 'QA required', 'slug' => 'qa-required', 'is_mandatory' => true, 'trigger' => 'next_session_check'], [['question_key' => 'qa', 'question_type' => 'short_text', 'label' => 'QA question']], AdministratorFactory::new()->create());
        $builder->publish($form->id, $form->active_version_id, $form->lock_version);
        app(TelegramReadService::class)->tick();
        $this->assertDatabaseCount('telegram_deliveries', 1);
        $form->refresh();
        FormSubmission::create(['student_id' => $booking->student_id, 'form_version_id' => $form->published_version_id, 'status' => 'submitted', 'submission_revision' => 1, 'submitted_at' => now('UTC')]);
        app(TelegramReadService::class)->tick();
        $this->assertDatabaseCount('telegram_deliveries', 1);
    }

    public function test_form_submitted_alert_does_not_include_hidden_answers(): void
    {
        $booking = $this->booking();
        $this->configured('form_submitted');
        $builder = app(FormBuilderService::class);
        $form = $builder->create(['title' => 'QA form', 'slug' => 'qa-form'], [['question_key' => 'qa', 'question_type' => 'short_text', 'label' => 'QA question', 'assistant_visible' => false]], AdministratorFactory::new()->create());
        DB::transaction(fn () => FormSubmission::create(['student_id' => $booking->student_id, 'form_version_id' => $form->active_version_id, 'status' => 'submitted', 'submission_revision' => 1, 'submitted_at' => now('UTC')]));
        $this->assertDatabaseCount('telegram_deliveries', 1);
        $this->assertArrayNotHasKey('answers', TelegramDelivery::first()->payload['source']);
    }

    public function test_resource_alerts_use_authoritative_request_download_and_known_country(): void
    {
        $this->configured('resource_lead');
        $this->configured('resource_downloaded');
        $category = ResourceCategory::create(['name' => 'QA resources', 'slug' => 'qa-resources', 'active' => true]);
        $resource = \App\Domains\Resources\Models\Resource::create(['title' => 'QA guide', 'slug' => 'qa-guide', 'category_id' => $category->id, 'status' => 'published', 'external_url' => 'https://example.org/guide.pdf']);
        $contact = Contact::create(['first_name' => 'QA', 'last_name' => 'Resource', 'email' => 'qa-resource@example.org']);
        $request = DB::transaction(fn () => ResourceRequest::create(['resource_id' => $resource->id, 'contact_id' => $contact->id, 'source' => 'qa-campaign', 'visitor_token' => (string) Str::uuid()]));
        DB::transaction(fn () => ResourceDownload::create(['resource_id' => $resource->id, 'contact_id' => $contact->id, 'request_id' => $request->id]));
        $this->assertDatabaseCount('telegram_deliveries', 2);
        $this->assertSame('ZZ', TelegramDelivery::first()->payload['source']['country']);
        $this->assertSame('qa-campaign', TelegramDelivery::first()->payload['source']['source']);
    }

    public function test_traffic_spike_reuses_existing_non_bot_unique_visitor_definition(): void
    {
        $this->configured('traffic_spike', ['threshold' => 2, 'window_minutes' => 60]);
        foreach (['one', 'one', 'two'] as $token) {
            AnalyticsEvent::create(['event_uuid' => (string) Str::uuid(), 'event_name' => 'page_view', 'visitor_token' => $token, 'page' => '/', 'metadata' => [], 'is_bot' => false, 'created_at' => now('UTC')]);
        } app(TelegramReadService::class)->tick();
        $this->assertDatabaseCount('telegram_deliveries', 1);
        $this->assertSame(2, TelegramDelivery::first()->payload['source']['unique_visitors']);
    }

    public function test_optional_conditions_match_before_creating_a_delivery(): void
    {
        $this->configured('resource_downloaded', ['conditions' => ['country' => 'DE']]);
        app(TelegramAutomationService::class)->emit('resource_downloaded', 'one', ['country' => 'FR']);
        $this->assertDatabaseCount('telegram_deliveries', 0);
        app(TelegramAutomationService::class)->emit('resource_downloaded', 'two', ['country' => 'DE']);
        $this->assertDatabaseCount('telegram_deliveries', 1);
    }

    public function test_scheduled_digest_is_daily_idempotent_and_on_demand_is_excluded_from_ticks(): void
    {
        $this->travelTo(Carbon::parse('2026-10-02 09:00', 'Africa/Cairo'));
        $this->configured('business_digest', ['mode' => 'scheduled', 'send_time' => '08:00', 'sections' => ['bookings']]);
        $this->configured('analytics_digest', ['mode' => 'on_demand']);
        app(TelegramReadService::class)->tick();
        app(TelegramReadService::class)->tick();
        $this->assertDatabaseCount('telegram_deliveries', 1);
    }

    public function test_disabled_command_stats_period_and_incorrect_chat_do_not_disclose_data(): void
    {
        [$bot,$dest] = $this->configured();
        Setting::set('telegram.commands_enabled', true);
        $bot->update(['commands_enabled' => true, 'commands' => ['stats']]);
        $dest->update(['allowed_user_ids' => ['777']]);
        $service = app(TelegramCommandService::class);
        $service->handle($bot, ['update_id' => 1, 'message' => ['chat' => ['id' => 'wrong-chat'], 'from' => ['id' => '777'], 'text' => '/stats 7d']]);
        $service->handle($bot, ['update_id' => 2, 'message' => ['chat' => ['id' => $dest->chat_id], 'from' => ['id' => '777'], 'text' => '/today']]);
        $this->assertDatabaseCount('telegram_deliveries', 0);
    }

    public function test_long_delivery_resumes_at_the_next_part_without_repeating_accepted_parts(): void
    {
        [$bot,$dest] = $this->configured();
        $service = app(TelegramDeliveryService::class);
        $delivery = $service->direct($dest, ['First part', 'Second part'], 'long-test');
        $this->assertSame(1, $delivery->next_part);
        $this->assertSame('pending', $delivery->status);
        $this->travel(4)->seconds();
        $service->deliver($delivery->id);
        $this->assertSame('sent', $delivery->fresh()->status);
        Http::assertSentCount(2);
    }

    public function test_terminal_telegram_rejection_is_visible_without_sensitive_error_text(): void
    {
        [$bot,$dest] = $this->configured();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 403, 'description' => $bot->token], 403)]);
        $delivery = app(TelegramDeliveryService::class)->test($dest);
        $this->assertSame('failed', $delivery->status);
        $this->assertSame('telegram_403', $delivery->failure_code);
        $this->assertStringNotContainsString($bot->token, json_encode($delivery->toArray()));
    }

    #[TestWith(['cancelled'])]
    #[TestWith(['rescheduled'])]
    public function test_queued_reminder_cancels_when_its_booking_changes(string $change): void
    {
        $this->configured('session_reminder', ['minutes' => 60]);
        $booking = $this->booking();
        app(TelegramReadService::class)->tick();
        $delivery = TelegramDelivery::firstOrFail();
        $booking->update($change === 'cancelled' ? ['status' => 'cancelled'] : ['start_at_utc' => $booking->start_at_utc->copy()->addDay()]);
        app(TelegramDeliveryService::class)->deliver($delivery->id);
        $this->assertSame('cancelled', $delivery->fresh()->status);
        $this->assertSame('source_no_longer_eligible', $delivery->fresh()->failure_code);
        Http::assertNothingSent();
    }

    public function test_connectivity_command_sends_only_a_labeled_test(): void
    {
        [, $destination] = $this->configured();
        $this->artisan('telegram:test', ['destination' => $destination->id])->assertSuccessful();
        Http::assertSent(fn ($request): bool => str_contains($request['text'], 'TEST — Telegram connectivity check. No student or customer data.'));
        $this->artisan('telegram:test', ['destination' => 999999])->assertFailed();
    }

    public function test_contending_sender_does_not_postpone_the_lock_owners_delivery(): void
    {
        [$bot, , $rule] = $this->configured();
        app(TelegramAutomationService::class)->emit('booking_created', 'lock-contention-fixture', [], ruleId: $rule->id);
        $delivery = TelegramDelivery::query()->where('telegram_rule_id', $rule->id)->firstOrFail();
        $due = $delivery->due_at->toIso8601String();
        $lock = Cache::lock('telegram-send-bot:'.$bot->id, 30);
        $this->assertTrue($lock->get());
        try {
            app(TelegramDeliveryService::class)->deliver($delivery->id);
            $this->assertSame($due, $delivery->fresh()->due_at->toIso8601String());
            $this->assertSame('pending', $delivery->fresh()->status);
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
        app(TelegramDeliveryService::class)->deliver($delivery->id);
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->attempts);
        Http::assertSentCount(1);
    }

    public function test_imported_legacy_command_does_not_send_a_second_reminder(): void
    {
        Setting::set('telegram.center_migrated', true);
        Setting::set('telegram.reminders_enabled', true);
        $this->artisan('booking:send-telegram-reminders')->expectsOutputToContain('managed by Telegram Bots')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_analytics_digest_uses_the_same_period_visitor_basis_as_operational_reports(): void
    {
        $end = CarbonImmutable::now('Africa/Cairo')->endOfDay();
        $start = $end->startOfDay()->subDays(6);
        $summary = app(ReportService::class)->getTrafficReport($start, $end)['summary'];
        $digest = app(TelegramReadService::class)->stats(7);
        $label = $summary['visitors_is_daily_sum'] ? 'Sum of daily unique visitors' : 'Unique visitors';
        $this->assertStringContainsString($label.': '.$summary['visitors'], $digest);
        $this->assertStringContainsString('Sessions: '.$summary['sessions'], $digest);
    }

    #[TestWith(['unconfigured'])]
    #[TestWith(['success'])]
    #[TestWith(['failed'])]
    public function test_backup_alert_reports_actual_private_archive_size_and_current_replication_outcome(string $outcome): void
    {
        [, , $successRule] = $this->configured('backup_success');
        [, , $failureRule] = $this->configured('backup_failure');
        Setting::set('last_offsite_backup_status', 'failed');
        Setting::set('backup_offsite_disk', 'qa-backup');
        config(['filesystems.backup_disk' => 'qa-backup', 'filesystems.disks.qa-backup' => $outcome === 'unconfigured' ? null : ['driver' => 'local']]);
        $managed = Storage::fake('managed_backups');
        $private = Storage::fake('local');
        $public = Storage::fake('public');
        if ($outcome !== 'unconfigured') {
            $disk = \Mockery::mock(FilesystemAdapter::class);
            $disk->shouldReceive('put')->once()->andReturn($outcome === 'success');
            Storage::shouldReceive('disk')->with('qa-backup')->andReturn($disk);
            Storage::shouldReceive('disk')->with('managed_backups')->andReturn($managed);
            Storage::shouldReceive('disk')->with('local')->andReturn($private);
            Storage::shouldReceive('disk')->with('public')->andReturn($public);
        }
        $service = new class extends BackupService
        {
            public function dumpDatabase(string $outputPath): void
            {
                File::put($outputPath, '-- Fictional test database dump');
            }

            protected function addDirectoryToZip(\ZipArchive $zip, string $dirPath, string $zipPrefix, array &$fileHashes = []): void {}
        };
        $path = $service->createBackup('telegram-qa-'.Str::random(8));
        try {
            $payload = TelegramDelivery::where('telegram_rule_id', $successRule->id)->firstOrFail()->payload['source'];
            $this->assertSame(basename($path), $payload['file_name']);
            $this->assertSame(filesize($path), $payload['file_size']);
            $this->assertSame('success', $payload['local_status']);
            $this->assertSame($outcome === 'unconfigured' ? 'not configured' : $outcome, $payload['offsite_status']);
            $this->assertSame($outcome === 'failed' ? 1 : 0, TelegramDelivery::where('telegram_rule_id', $failureRule->id)->count());
            $this->assertStringNotContainsString('https://', implode('', TelegramDelivery::where('telegram_rule_id', $successRule->id)->first()->payload['parts']));
            Http::assertNothingSent();
        } finally {
            File::delete($path);
        }
    }
}
