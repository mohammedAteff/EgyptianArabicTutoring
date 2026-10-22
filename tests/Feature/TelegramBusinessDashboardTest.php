<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\CMS\Models\Setting;
use App\Domains\Games\Models\Game;
use App\Domains\Notifications\Models\TelegramBot;
use App\Domains\Notifications\Models\TelegramDelivery;
use App\Domains\Notifications\Models\TelegramDestination;
use App\Domains\Notifications\Services\TelegramAutomationService;
use App\Domains\Notifications\Services\TelegramBusinessReadService;
use App\Domains\Notifications\Services\TelegramCommandService;
use App\Domains\Notifications\Services\TelegramDeliveryService;
use App\Domains\Notifications\Services\TelegramNavigationService;
use App\Domains\Notifications\Services\TelegramReadService;
use App\Domains\Notifications\Services\TelegramRuleCatalog;
use App\Domains\Notifications\Services\TelegramTemplateService;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\InstallmentScheduleService;
use App\Domains\Students\Services\StudentLedgerService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Database\Factories\StaffTaskFactory;
use Database\Factories\TelegramBotFactory;
use Database\Factories\TelegramDestinationFactory;
use Database\Factories\TelegramRuleFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class TelegramBusinessDashboardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{TelegramBot, TelegramDestination} */
    private function bot(array $commands = ['today', 'tomorrow', 'student', 'stats', 'attention', 'money', 'status', 'brief', 'wrap'], string $detail = 'personal'): array
    {
        Http::preventStrayRequests();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 11]])]);
        Queue::fake();
        Setting::set('telegram.commands_enabled', true);
        $bot = TelegramBotFactory::new()->create(['commands_enabled' => true, 'commands' => $commands]);
        $destination = TelegramDestinationFactory::new()->create(['telegram_bot_id' => $bot->id, 'allowed_user_ids' => ['777', '888'], 'detail_level' => $detail]);

        return [$bot, $destination];
    }

    private function message(TelegramBot $bot, TelegramDestination $destination, string $text, int $id = 1): void
    {
        app(TelegramCommandService::class)->handle($bot, ['update_id' => $id, 'message' => ['chat' => ['id' => $destination->chat_id], 'from' => ['id' => '777'], 'text' => $text]]);
    }

    public function test_home_exposes_enabled_options_and_games_are_part_of_analytics(): void
    {
        [$bot, $destination] = $this->bot(['stats', 'today']);
        $this->message($bot, $destination, '/start');
        Http::assertSent(function (Request $request): bool {
            $labels = collect($request['reply_markup']['inline_keyboard'])->flatten(1)->pluck('text');
            $this->assertContains('🎮 Games', $labels->all());
            $this->assertContains('📚 Resources', $labels->all());
            $this->assertNotContains('💳 Money', $labels->all());
            $this->assertStringContainsString('Business dashboard', $request['text']);

            return true;
        });
    }

    public function test_navigation_edits_the_existing_report_and_a_duplicate_update_does_not_edit_twice(): void
    {
        [$bot, $destination] = $this->bot();
        $page = app(TelegramNavigationService::class)->render($bot, $destination, '777', ['command' => 'stats', 'range' => '7d']);
        $button = collect($page['reply_markup']['inline_keyboard'])->flatten(1)->firstWhere('text', '🎮 Games');
        $update = ['update_id' => 2, 'callback_query' => ['id' => 'callback-2', 'data' => $button['callback_data'], 'from' => ['id' => '777'], 'message' => ['message_id' => 11, 'chat' => ['id' => $destination->chat_id]]]];
        app(TelegramCommandService::class)->handle($bot, $update);
        app(TelegramCommandService::class)->handle($bot, $update);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/answerCallbackQuery'));
        $edits = Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/editMessageText'));
        $this->assertCount(1, $edits);
        $this->assertSame(11, $edits->first()[0]['message_id']);
        $this->assertStringContainsString('Games', $edits->first()[0]['text']);
        $this->assertDatabaseCount('telegram_deliveries', 1);
    }

    public function test_callback_tokens_are_bound_to_user_destination_and_current_command_permissions(): void
    {
        [$bot, $destination] = $this->bot(['stats']);
        $navigation = app(TelegramNavigationService::class);
        $page = $navigation->render($bot, $destination, '777', ['command' => 'start']);
        $data = $page['reply_markup']['inline_keyboard'][0][0]['callback_data'];
        $this->assertNull($navigation->callback($bot, $destination, '888', $data));
        $other = TelegramDestinationFactory::new()->create(['telegram_bot_id' => $bot->id]);
        $this->assertNull($navigation->callback($bot, $other, '777', $data));
        $bot->update(['commands' => []]);
        $this->assertNull($navigation->callback($bot, $destination, '777', $data));
        Http::assertNothingSent();
    }

    public function test_expired_callback_is_acknowledged_without_a_report(): void
    {
        [$bot, $destination] = $this->bot();
        $page = app(TelegramNavigationService::class)->render($bot, $destination, '777', ['command' => 'start']);
        $this->travel(31)->minutes();
        app(TelegramCommandService::class)->handle($bot, ['update_id' => 3, 'callback_query' => ['id' => 'expired', 'data' => $page['reply_markup']['inline_keyboard'][0][0]['callback_data'], 'from' => ['id' => '777'], 'message' => ['message_id' => 11, 'chat' => ['id' => $destination->chat_id]]]]);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/answerCallbackQuery') && str_contains($request['text'], 'expired'));
        $this->assertDatabaseCount('telegram_deliveries', 0);
    }

    public function test_summary_destination_cannot_read_student_or_financial_reports(): void
    {
        [$bot, $destination] = $this->bot(detail: 'summary');
        Student::factory()->verified()->create(['first_name' => 'Private', 'last_name' => 'Learner']);
        $this->message($bot, $destination, '/money');
        Http::assertSent(fn (Request $request): bool => str_contains($request['text'], 'requires a trusted personal-detail destination') && ! str_contains($request['text'], 'Private'));
    }

    public function test_partial_student_search_offers_choices_without_selecting_a_record(): void
    {
        [$bot, $destination] = $this->bot();
        Student::factory()->verified()->create(['first_name' => 'Maya', 'last_name' => 'One', 'name_normalized' => 'maya one']);
        Student::factory()->verified()->create(['first_name' => 'Maya', 'last_name' => 'Two', 'name_normalized' => 'maya two']);
        $this->message($bot, $destination, '/student Maya');
        Http::assertSent(function (Request $request): bool {
            $labels = collect($request['reply_markup']['inline_keyboard'])->flatten(1)->pluck('text')->implode(' ');

            return str_contains($request['text'], 'Choose a student') && str_contains($labels, 'Maya One') && str_contains($labels, 'Maya Two') && ! str_contains($request['text'], 'Available to book');
        });
    }

    public function test_game_titles_are_literal_and_custom_periods_are_supported(): void
    {
        [$bot, $destination] = $this->bot();
        Game::create(['title' => '<b>Forged</b> & quiz', 'slug' => 'quiz', 'status' => 'available']);
        $this->message($bot, $destination, '/games 2026-10-01 2026-10-14');
        Http::assertSent(fn (Request $request): bool => str_contains($request['text'], '<b>Forged</b> & quiz') && str_contains($request['text'], '1 Oct 2026 – 14 Oct 2026'));
    }

    public function test_system_and_attention_reports_are_available_without_changing_business_records(): void
    {
        [$bot, $destination] = $this->bot();
        $before = Student::count();
        $page = app(TelegramNavigationService::class)->render($bot, $destination, '777', ['command' => 'attention']);
        $this->assertStringContainsString('Needs attention', $page['text']);
        $this->assertStringContainsString('All clear', $page['text']);
        $this->assertStringNotContainsString('Overdue installments', $page['text']);
        $this->assertSame($before, Student::count());
        Http::assertNothingSent();

    }

    public function test_bounded_polling_requests_button_updates_and_keeps_reading_within_the_window(): void
    {
        [$bot, $destination] = $this->bot();
        Sleep::fake();
        Http::fake(['api.telegram.org/*/getUpdates*' => Http::response(['ok' => true, 'result' => []])]);
        app(TelegramCommandService::class)->listen(4);
        $polls = Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/getUpdates'));
        $this->assertCount(2, $polls);
        foreach ($polls as [$request]) {
            $this->assertSame(['message', 'callback_query'], json_decode($request['allowed_updates'], true));
        }
    }

    public function test_pending_homework_has_a_student_link_and_summary_views_hide_its_title(): void
    {
        $this->bot();
        $homework = Homework::factory()->create(['status' => 'submitted', 'title' => 'Private <b>practice</b>']);
        Homework::factory()->create(['status' => 'completed', 'title' => 'Finished work']);
        $reports = app(TelegramBusinessReadService::class);
        $personal = $reports->attention(true, 'learning');
        $this->assertStringContainsString('Homework awaiting feedback — 1', $personal['text']);
        $this->assertStringContainsString('Private &lt;b&gt;practice&lt;/b&gt;', $personal['text']);
        $this->assertStringNotContainsString('Finished work', $personal['text']);
        $this->assertSame(route('admin.students.teaching', $homework->student_id), $personal['buttons'][0]['url']);
        $summary = $reports->attention(false, 'learning');
        $this->assertStringNotContainsString('Private', $summary['text']);
        $this->assertEmpty(array_filter($summary['buttons'], fn (array $button): bool => isset($button['url'])));
        $this->assertSame('submitted', $homework->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_default_notifications_hide_long_urls_but_preserve_literal_student_names_and_custom_templates(): void
    {
        $catalog = app(TelegramRuleCatalog::class);
        $values = ['student_name' => '<b>Example</b>', 'amount' => '25.00', 'currency' => 'USD', 'admin_url' => 'https://example.org/admin/student'];
        $service = app(TelegramTemplateService::class);
        $parts = $service->deliveryParts('payment_recorded', $catalog->get('payment_recorded')['template'], $values, true);
        $this->assertStringContainsString('<b>Example</b>', $parts['parts'][0]);
        $this->assertStringNotContainsString('https://example.org', $parts['parts'][0]);
        $this->assertSame('https://example.org/admin/student', collect($parts['entities'][0])->firstWhere('type', 'text_link')['url']);
        $custom = $service->deliveryParts('payment_recorded', '<b>Literal owner template</b> {student_name}', $values, true);
        $this->assertSame('<b>Literal owner template</b> <b>Example</b>', $custom['parts'][0]);
        $this->assertSame([], $custom['entities'][0]);
    }

    public function test_money_keeps_currencies_separate_and_uses_actual_payments_refunds_and_installment_balances(): void
    {
        Http::preventStrayRequests();
        Queue::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00 UTC'));
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $usd = $ledger->createPackage($student, 'USD package', 4, '100.00', '0', 'USD', null, 'usd-package', entitlementCode: 'one_hour');
        $eur = $ledger->createPackage($student, 'EUR package', 2, '50.00', '0', 'EUR', null, 'eur-package', entitlementCode: 'two_hour');
        $payment = $ledger->recordPayment($usd, '40.00', 'usd-paid', null);
        $ledger->refund($payment, '10.00', 'usd-refund', null);
        $ledger->recordPayment($eur, '20.00', 'eur-paid', null);
        app(InstallmentScheduleService::class)->create($usd, [['expected_amount' => '50.00', 'due_date' => '2026-10-21'], ['expected_amount' => '50.00', 'due_date' => '2026-11-21']], 'usd-installments', null);
        $page = app(TelegramBusinessReadService::class)->money(CarbonImmutable::parse('2026-10-22 00:00 UTC'), CarbonImmutable::parse('2026-10-22 23:59:59 UTC'));
        $this->assertStringContainsString("<b>USD</b>\nReceived: 40.00 · Refunds: 10.00\nNet received: 30.00\nOutstanding: 70.00 · Overdue: 20.00", $page['text']);
        $this->assertStringContainsString("<b>EUR</b>\nReceived: 20.00 · Refunds: 0.00\nNet received: 20.00\nOutstanding: 30.00 · Overdue: 0.00", $page['text']);
        Http::assertNothingSent();
    }

    public function test_scheduled_briefing_generates_separate_personal_and_summary_payloads(): void
    {
        [$bot, $personal] = $this->bot();
        Setting::set('telegram.automation_enabled', true);
        $summary = TelegramDestinationFactory::new()->create(['telegram_bot_id' => $bot->id, 'detail_level' => 'summary']);
        $rule = TelegramRuleFactory::new()->create(['telegram_bot_id' => $bot->id, 'trigger' => 'morning_briefing', 'mode' => 'on_demand', 'template' => app(TelegramRuleCatalog::class)->get('morning_briefing')['template']]);
        $rule->destinations()->attach([$personal->id, $summary->id]);
        app(TelegramAutomationService::class)->emit('morning_briefing', 'morning-test', ['date' => '2026-10-22', 'digest' => ''], null, $rule->id);
        $personalText = TelegramDelivery::where('telegram_destination_id', $personal->id)->firstOrFail()->payload['parts'];
        $summaryText = TelegramDelivery::where('telegram_destination_id', $summary->id)->firstOrFail()->payload['parts'];
        $this->assertStringContainsString('Money', implode('', $personalText));
        $this->assertStringNotContainsString('Money', implode('', $summaryText));
        $this->assertDatabaseCount('telegram_deliveries', 2);
        Http::assertNothingSent();
    }

    public function test_queued_command_is_cancelled_when_access_is_revoked_before_delivery(): void
    {
        [$bot, $destination] = $this->bot();
        $this->message($bot, $destination, '/money');
        $this->message($bot, $destination, '/money', 2);
        $queued = TelegramDelivery::where('status', 'pending')->firstOrFail();
        $destination->update(['allowed_user_ids' => []]);
        $this->travel(6)->seconds();
        app(TelegramDeliveryService::class)->deliver($queued->id);
        $this->assertSame('cancelled', $queued->fresh()->status);
        $this->assertSame('command_access_revoked', $queued->fresh()->failure_code);
        Http::assertSentCount(1);
    }

    public function test_command_menu_publishes_scoped_suggestions_without_sending_a_report(): void
    {
        [$bot, $destination] = $this->bot(['stats', 'money'], 'summary');
        $this->assertTrue(app(TelegramCommandService::class)->publishMenu($bot));
        Http::assertSent(function (Request $request): bool {
            if (($request['scope']['type'] ?? '') !== 'chat') {
                return false;
            }
            $commands = collect($request['commands'])->pluck('command');
            $this->assertContains('games', $commands->all());
            $this->assertNotContains('money', $commands->all());

            return str_ends_with($request->url(), '/setMyCommands');
        });
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/sendMessage'));
        $this->assertDatabaseCount('telegram_deliveries', 0);
    }

    public function test_weekly_summary_is_scheduled_on_mondays_and_deduplicated(): void
    {
        [$bot, $destination] = $this->bot();
        Setting::set('telegram.automation_enabled', true);
        $rule = TelegramRuleFactory::new()->create(['telegram_bot_id' => $bot->id, 'trigger' => 'weekly_summary', 'mode' => 'scheduled', 'send_time' => '08:00', 'template' => app(TelegramRuleCatalog::class)->get('weekly_summary')['template']]);
        $rule->destinations()->attach($destination);
        $this->travelTo(CarbonImmutable::parse('2026-10-25 09:00', 'Africa/Cairo'));
        app(TelegramReadService::class)->tick();
        $this->assertDatabaseCount('telegram_deliveries', 0);
        $this->travelTo(CarbonImmutable::parse('2026-10-26 09:00', 'Africa/Cairo'));
        app(TelegramReadService::class)->tick();
        app(TelegramReadService::class)->tick();
        $this->assertDatabaseCount('telegram_deliveries', 1);
        Http::assertNothingSent();
    }

    public function test_an_older_queued_navigation_cannot_overwrite_a_newer_report(): void
    {
        [$bot, $destination] = $this->bot();
        $this->message($bot, $destination, '/start');
        $page = app(TelegramNavigationService::class)->render($bot, $destination, '777', ['command' => 'start']);
        $buttons = collect($page['reply_markup']['inline_keyboard'])->flatten(1);
        foreach ([2 => '📊 Analytics', 3 => '🛠 System status'] as $id => $label) {
            if ($id === 3) {
                $this->travel(2)->seconds();
            }
            $update = ['update_id' => $id, 'callback_query' => ['id' => 'callback-'.$id, 'data' => $buttons->firstWhere('text', $label)['callback_data'], 'from' => ['id' => '777'], 'message' => ['message_id' => 11, 'chat' => ['id' => $destination->chat_id]]]];
            app(TelegramCommandService::class)->handle($bot, $update);
            if ($id === 2) {
                $olderUpdate = $update;
            }
        }
        app(TelegramCommandService::class)->handle($bot, $olderUpdate);
        $old = TelegramDelivery::where('trigger', 'command_stats')->firstOrFail();
        $this->travel(6)->seconds();
        app(TelegramDeliveryService::class)->deliver($old->id);
        $this->assertSame('cancelled', $old->fresh()->status);
        $this->assertSame('report_expired_or_superseded', $old->fresh()->failure_code);
        $this->assertCount(1, Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/editMessageText')));
    }

    public function test_menu_update_endpoint_requires_an_administrator_and_records_an_audit(): void
    {
        [$bot] = $this->bot();
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'assistant']))
            ->post(route('admin.telegram.commands', $bot))->assertForbidden();
        Http::assertNothingSent();
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']))
            ->post(route('admin.telegram.commands', $bot))->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('audit_logs', ['action' => 'telegram_command_menu_saved', 'entity_id' => $bot->id]);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/sendMessage'));
    }

    public function test_system_status_does_not_copy_sensitive_backup_exception_details(): void
    {
        Setting::set('last_backup_status', 'failed: password=do-not-send /private/backups/archive.sql');
        $text = app(TelegramBusinessReadService::class)->status()['text'];
        $this->assertStringContainsString('Not successful or unknown', $text);
        $this->assertStringNotContainsString('do-not-send', $text);
        $this->assertStringNotContainsString('archive.sql', $text);
    }

    public function test_student_card_separates_credit_types_and_explains_unavailable_remaining_credits(): void
    {
        Http::preventStrayRequests();
        Queue::fake();
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $one = $ledger->createPackage($student, 'One-hour package', 4, '100', '0', 'USD', null, 'one-hour-card', entitlementCode: 'one_hour');
        $ledger->createPackage($student, 'Two-hour package', 2, '100', '0', 'USD', null, 'two-hour-card', entitlementCode: 'two_hour');
        $one->update(['paused_at' => now('UTC')]);
        $page = app(TelegramBusinessReadService::class)->student(['id' => $student->id]);
        $this->assertStringContainsString('1-hour sessions: 0 available · 4 remaining', $page['text']);
        $this->assertStringContainsString('2-hour sessions: 2 available · 2 remaining', $page['text']);
        Http::assertNothingSent();
    }

    public function test_queued_notifications_reapply_a_destination_privacy_downgrade(): void
    {
        [$bot, $destination] = $this->bot();
        Setting::set('telegram.automation_enabled', true);
        $rule = TelegramRuleFactory::new()->create(['telegram_bot_id' => $bot->id, 'trigger' => 'payment_recorded', 'template' => app(TelegramRuleCatalog::class)->get('payment_recorded')['template']]);
        $rule->destinations()->attach($destination);
        app(TelegramAutomationService::class)->emit('payment_recorded', 'privacy-downgrade', ['student_name' => 'Private Learner', 'amount' => '25.00', 'currency' => 'USD', 'record_id' => 1, 'admin_url' => 'https://example.org/admin']);
        $delivery = TelegramDelivery::firstOrFail();
        $destination->update(['detail_level' => 'summary']);
        app(TelegramDeliveryService::class)->deliver($delivery->id);
        $this->assertSame('sent', $delivery->fresh()->status);
        Http::assertSent(fn (Request $request): bool => str_contains($request['text'], '[private]') && ! str_contains($request['text'], 'Private Learner') && ! str_contains($request['text'], '25.00'));
    }

    public function test_game_comparisons_keep_business_midnights_across_the_cairo_daylight_saving_transition(): void
    {
        [$bot, $destination] = $this->bot();
        $this->travelTo(CarbonImmutable::parse('2026-10-30 12:00', 'Africa/Cairo'));
        $game = Game::create(['title' => 'Vocabulary', 'slug' => 'vocabulary', 'status' => 'available']);
        AnalyticsEvent::create(['event_name' => 'game_started', 'metadata' => ['game_slug' => $game->slug],
            'created_at' => CarbonImmutable::parse('2026-10-29 00:15', 'Africa/Cairo')->utc(), 'is_bot' => false]);
        $page = app(TelegramNavigationService::class)->render($bot, $destination, '777', ['command' => 'games', 'section' => 'games', 'range' => 'today']);
        $this->assertStringContainsString('Previous comparable period started: 1', $page['text']);
        Http::assertNothingSent();
    }

    public function test_a_long_follow_up_resumes_after_refreshing_its_current_facts(): void
    {
        [$bot, $destination] = $this->bot();
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00 UTC'));
        Setting::set('telegram.automation_enabled', true);
        $task = StaffTaskFactory::new()->create(['due_date' => '2026-10-20', 'status' => 'open']);
        $rule = TelegramRuleFactory::new()->create(['telegram_bot_id' => $bot->id, 'trigger' => 'staff_task_overdue', 'mode' => 'instant', 'threshold' => 1, 'template' => str_repeat('Reminder ', 500)]);
        $rule->destinations()->attach($destination);
        app(TelegramAutomationService::class)->emit('staff_task_overdue', 'long-follow-up', ['record_id' => $task->id], null, $rule->id);
        $delivery = TelegramDelivery::firstOrFail();
        $sender = app(TelegramDeliveryService::class);
        $sender->deliver($delivery->id);
        $this->assertSame(1, $delivery->fresh()->next_part);
        $this->travel(6)->seconds();
        $sender->deliver($delivery->id);
        $this->assertSame('sent', $delivery->fresh()->status);
        Http::assertSentCount(2);
    }
}
