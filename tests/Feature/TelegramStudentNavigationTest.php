<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\Setting;
use App\Domains\Notifications\Models\TelegramBot;
use App\Domains\Notifications\Models\TelegramDelivery;
use App\Domains\Notifications\Models\TelegramDestination;
use App\Domains\Notifications\Services\TelegramBusinessReadService;
use App\Domains\Notifications\Services\TelegramCommandService;
use App\Domains\Notifications\Services\TelegramDeliveryService;
use App\Domains\Notifications\Services\TelegramNavigationService;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\FeedbackReadModel;
use App\Domains\Students\Services\FeedbackService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Jobs\DeliverTelegramMessage;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Database\Factories\TelegramBotFactory;
use Database\Factories\TelegramDestinationFactory;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as WebRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TelegramStudentNavigationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{TelegramBot, TelegramDestination} */
    private function bot(array $commands = ['feedback', 'insights'], string $detail = 'personal', string $type = 'private'): array
    {
        Http::preventStrayRequests();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 11]])]);
        Queue::fake();
        Mail::fake();
        Setting::set('telegram.commands_enabled', true);
        $bot = TelegramBotFactory::new()->create(['commands_enabled' => true, 'commands' => $commands]);
        $destination = TelegramDestinationFactory::new()->create(['telegram_bot_id' => $bot->id, 'detail_level' => $detail, 'type' => $type, 'allowed_user_ids' => ['777']]);

        return [$bot, $destination];
    }

    private function message(TelegramBot $bot, TelegramDestination $destination, string $text, string $sender = '777', int $id = 1): void
    {
        app(TelegramCommandService::class)->handle($bot, ['update_id' => $id, 'message' => ['chat' => ['id' => $destination->chat_id], 'from' => ['id' => $sender], 'text' => $text]]);
    }

    private function buttons(array $page): array
    {
        return collect($page['reply_markup']['inline_keyboard'])->flatten(1)->all();
    }

    public static function commands(): array
    {
        return [['feedback'], ['insights']];
    }

    public function test_commands_are_selectable_without_automatically_selecting_existing_bots(): void
    {
        [$bot] = $this->bot(['today']);
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']), 'web')
            ->get(route('admin.telegram.index', ['tab' => 'commands']))->assertOk()
            ->assertSee('value="feedback"', false)->assertSee('value="insights"', false);
        $this->assertSame(['today'], $bot->fresh()->commands);
        $this->post(route('admin.telegram.bot', $bot), ['name' => 'Synthetic selectable bot', 'enabled' => 1, 'commands_enabled' => 1, 'commands' => ['today', 'feedback', 'insights']])->assertRedirect();
        $this->assertSame(['today', 'feedback', 'insights'], $bot->fresh()->commands);
        Http::assertNothingSent();
    }

    #[DataProvider('commands')]
    public function test_unselected_and_unauthorized_commands_cannot_deliver_a_section(string $command): void
    {
        [$bot, $destination] = $this->bot([]);
        $this->message($bot, $destination, '/'.$command);
        $bot->update(['commands' => [$command]]);
        $this->message($bot, $destination, '/'.$command, '888');
        $bot->update(['enabled' => false]);
        $this->message($bot, $destination, '/'.$command);
        $this->assertDatabaseCount('telegram_deliveries', 0);
        Http::assertNothingSent();
    }

    #[DataProvider('commands')]
    public function test_summary_destinations_have_no_section_buttons_or_protected_links(string $command): void
    {
        [$bot, $destination] = $this->bot(detail: 'summary');
        $page = app(TelegramNavigationService::class)->render($bot, $destination, '777', ['command' => 'start']);
        $labels = array_column($this->buttons($page), 'text');
        $this->assertNotContains('💬 Student Feedback', $labels);
        $this->assertNotContains('📖 Student Insights', $labels);
        $restricted = app(TelegramNavigationService::class)->render($bot, $destination, '777', ['command' => $command]);
        $this->assertStringContainsString('requires a trusted personal-detail destination', $restricted['text']);
        $this->message($bot, $destination, '/'.$command);
        $this->assertSame('command_access_revoked', TelegramDelivery::query()->sole()->failure_code);
        Http::assertNothingSent();
    }

    public function test_feedback_links_use_canonical_status_filters_and_do_not_change_conversations(): void
    {
        [$bot, $destination] = $this->bot();
        $student = Student::factory()->verified()->create();
        app(FeedbackService::class)->create($student, ['request_key' => (string) Str::uuid(), 'type' => 'question', 'area' => 'account', 'subject' => 'Private synthetic subject', 'body' => 'Private synthetic body']);
        $tables = ['feedback_conversations', 'feedback_events', 'feedback_operations', 'feedback_read_states', 'admin_notifications', 'student_notifications'];
        $snapshot = fn (): array => collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
        $before = $snapshot();
        $page = app(TelegramNavigationService::class)->render($bot, $destination, '777', ['command' => 'feedback']);
        $buttons = collect($this->buttons($page));
        $this->assertSame(route('admin.feedback.index'), $buttons->firstWhere('text', 'Feedback inbox ↗')['url']);
        foreach (['open' => 'Open conversations', 'waiting_student' => 'Waiting for the student', 'resolved' => 'Resolved conversations', 'closed' => 'Closed conversations'] as $status => $label) {
            $url = $buttons->firstWhere('text', $label.' ↗')['url'];
            $this->assertSame(route('admin.feedback.index', ['status' => $status]), $url);
            parse_str((string) parse_url($url, PHP_URL_QUERY), $filters);
            $this->assertSame(['status' => $status], app(FeedbackReadModel::class)->filters(WebRequest::create('/', 'GET', $filters), true));
        }
        $this->message($bot, $destination, '/feedback');
        $this->assertSame($before, $snapshot());
        $this->assertDatabaseCount('telegram_deliveries', 1);
        $this->assertSame('command_feedback', TelegramDelivery::query()->sole()->trigger);
        $this->assertStringNotContainsString('Private synthetic', json_encode($page, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('unread', $page['text']);
        Queue::assertNothingPushed();
    }

    public function test_insights_reuses_student_selection_and_preserves_custom_period_and_timezone(): void
    {
        [$bot, $destination] = $this->bot();
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', 'UTC'));
        $student = Student::factory()->verified()->create(['first_name' => 'Synthetic', 'last_name' => 'One', 'name_normalized' => 'synthetic one']);
        Student::factory()->verified()->create(['first_name' => 'Synthetic', 'last_name' => 'Two', 'name_normalized' => 'synthetic two']);
        $navigation = app(TelegramNavigationService::class);
        $state = ['command' => 'insights', 'query' => 'Synthetic', 'range' => 'custom', 'start_date' => '2026-10-28', 'end_date' => '2026-11-02', 'timezone' => 'Europe/Paris'];
        $search = $navigation->render($bot, $destination, '777', $state);
        $choice = collect($this->buttons($search))->first(fn (array $button): bool => str_contains($button['text'], 'Synthetic One'));
        $selected = $navigation->callback($bot, $destination, '777', $choice['callback_data']);
        $page = $navigation->render($bot, $destination, '777', $selected);
        $url = collect($this->buttons($page))->firstWhere('text', 'Student Insights ↗')['url'];
        $this->assertSame(route('admin.student-insights.index', ['range' => 'custom', 'start_date' => '2026-10-28', 'end_date' => '2026-11-02', 'timezone' => 'Europe/Paris', 'category' => 'all', 'student_id' => $student->id]), $url);
        $this->assertContains('🏠 Home', array_column($this->buttons($page), 'text'));
        Http::assertNothingSent();
    }

    public function test_merged_students_have_no_individual_insights_link_and_group_search_discloses_no_names(): void
    {
        [$bot, $destination] = $this->bot();
        $student = Student::factory()->verified()->create(['first_name' => 'Secret', 'last_name' => 'Synthetic', 'name_normalized' => 'secret synthetic', 'identity_status' => 'merged']);
        $navigation = app(TelegramNavigationService::class);
        $page = $navigation->render($bot, $destination, '777', ['command' => 'insights', 'id' => $student->id]);
        $this->assertStringContainsString('Student unavailable', $page['text']);
        $this->assertEmpty(array_filter($this->buttons($page), fn (array $button): bool => isset($button['url'])));
        $destination->update(['type' => 'group']);
        $page = $navigation->render($bot, $destination, '777', ['command' => 'insights', 'query' => 'Secret']);
        $this->assertStringNotContainsString('Secret', json_encode($page, JSON_THROW_ON_ERROR));
        $this->assertNotContains('Find student', array_column($this->buttons($page), 'text'));
        Http::assertNothingSent();
    }

    public function test_selected_student_links_require_selected_commands_and_are_not_added_to_ambiguous_search(): void
    {
        [$bot, $destination] = $this->bot(['student', 'feedback', 'insights']);
        $student = Student::factory()->verified()->create(['name_normalized' => 'selection one']);
        Student::factory()->verified()->create(['name_normalized' => 'selection two']);
        $navigation = app(TelegramNavigationService::class);
        $search = $navigation->render($bot, $destination, '777', ['command' => 'student', 'query' => 'selection']);
        $this->assertNotContains('Student Insights', array_column($this->buttons($search), 'text'));
        $page = $navigation->render($bot, $destination, '777', ['command' => 'student', 'id' => $student->id]);
        $this->assertSame(route('admin.feedback.index', ['student_id' => $student->id]), collect($this->buttons($page))->firstWhere('text', 'Student Feedback')['url']);
        $this->assertContains('Student Insights', array_column($this->buttons($page), 'text'));
        $bot->update(['commands' => ['student']]);
        $page = $navigation->render($bot, $destination, '777', ['command' => 'student', 'id' => $student->id]);
        $this->assertNotContains('Student Insights', array_column($this->buttons($page), 'text'));
        $this->assertNotContains('Student Feedback', array_column($this->buttons($page), 'text'));
        Http::assertNothingSent();
    }

    #[DataProvider('commands')]
    public function test_new_callbacks_are_bound_expire_and_recheck_deferred_access(string $command): void
    {
        [$bot, $destination] = $this->bot();
        $navigation = app(TelegramNavigationService::class);
        $page = $navigation->render($bot, $destination, '777', ['command' => 'start']);
        $button = collect($this->buttons($page))->firstWhere('text', $command === 'feedback' ? '💬 Student Feedback' : '📖 Student Insights');
        $this->assertNull($navigation->callback($bot, $destination, '888', $button['callback_data']));
        $other = TelegramDestinationFactory::new()->create(['telegram_bot_id' => $bot->id]);
        $this->assertNull($navigation->callback($bot, $other, '777', $button['callback_data']));
        $this->assertNotNull($navigation->callback($bot, $destination, '777', $button['callback_data']));
        $this->message($bot, $destination, '/'.$command);
        $delivery = TelegramDelivery::query()->sole();
        $delivery->update(['status' => 'pending', 'due_at' => now('UTC')]);
        $bot->update(['commands' => []]);
        app(TelegramDeliveryService::class)->deliver($delivery->id);
        $this->assertSame('command_access_revoked', $delivery->fresh()->failure_code);
        $bot->update(['commands' => [$command]]);
        $this->travel(31)->minutes();
        app(TelegramCommandService::class)->handle($bot, ['update_id' => 2, 'callback_query' => ['id' => 'expired', 'data' => $button['callback_data'], 'from' => ['id' => '777'], 'message' => ['message_id' => 11, 'chat' => ['id' => $destination->chat_id]]]]);
        $this->assertSame(1, TelegramDelivery::query()->count());
        $this->assertCount(1, Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/answerCallbackQuery') && str_contains($request['text'], 'expired')));
    }

    public function test_generated_website_links_still_require_staff_authentication_and_authorization(): void
    {
        $this->bot();
        $this->get(route('admin.feedback.index'))->assertRedirect();
        $this->get(route('admin.student-insights.index'))->assertRedirect();
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'assistant']), 'web');
        $this->get(route('admin.feedback.index'))->assertForbidden();
        $this->get(route('admin.student-insights.index'))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_homework_page_keeps_membership_and_totals_without_loading_unrelated_categories(): void
    {
        $this->bot();
        $this->freezeTime();
        $student = Student::factory()->verified()->create();
        foreach (range(1, 7) as $number) {
            Homework::factory()->create(['student_id' => $student->id, 'status' => 'submitted', 'title' => 'Synthetic homework '.$number]);
        }
        $queries = [];
        DB::listen(function (QueryExecuted $event) use (&$queries): void {
            $queries[] = $event->sql;
        });
        $page = app(TelegramBusinessReadService::class)->attention(true, 'learning', 1);
        $this->assertStringContainsString('Homework awaiting feedback — 7', $page['text']);
        $this->assertStringContainsString('6. Homework awaiting feedback — Synthetic homework 6', $page['text']);
        $this->assertStringContainsString('7. Homework awaiting feedback — Synthetic homework 7', $page['text']);
        $this->assertStringContainsString('Showing 6–7 of 7', $page['text']);
        $this->assertStringNotContainsString('Synthetic homework 5', $page['text']);
        $sql = implode("\n", $queries);
        foreach (['student_packages', 'bookings', 'lms_assignment_submissions', 'lms_quiz_attempts', 'lms_video_assets'] as $table) {
            $this->assertStringNotContainsString($table, $sql);
        }
        Http::assertNothingSent();
    }

    public function test_package_pages_load_only_the_visible_history_and_timezones_refresh_on_reused_services(): void
    {
        $this->bot();
        $this->freezeTime();
        $student = Student::factory()->verified()->create();
        foreach (range(1, 12) as $number) {
            app(StudentLedgerService::class)->createPackage($student, 'Synthetic package '.$number, 2, '40', '0', 'USD', null, 'package-page-'.$number, entitlementCode: 'one_hour');
        }
        $read = app(TelegramBusinessReadService::class);
        $loaded = 0;
        Event::listen('eloquent.retrieved: '.StudentPackage::class, function () use (&$loaded): void {
            $loaded++;
        });
        $page = $read->student(['id' => $student->id, 'view' => 'packages', 'page' => 1]);
        $this->assertSame(5, $loaded);
        $this->assertStringContainsString('Synthetic package 7', $page['text']);
        $this->assertStringContainsString('Synthetic package 3', $page['text']);
        $this->assertStringNotContainsString('Synthetic package 8', $page['text']);
        $this->assertStringNotContainsString('Synthetic package 2', $page['text']);
        Setting::set('business_timezone', 'Europe/Paris');
        $this->assertStringContainsString('Europe/Paris', $read->attention(true, 'learning')['text']);
        Setting::set('business_timezone', 'Africa/Cairo');
        $this->assertStringContainsString('Africa/Cairo', $read->attention(true, 'learning')['text']);
        Http::assertNothingSent();
    }

    public function test_new_menu_entries_follow_existing_order_and_menu_publication_remains_explicit(): void
    {
        [$bot, $destination] = $this->bot(['today', 'stats', 'money', 'feedback', 'insights']);
        $page = app(TelegramNavigationService::class)->render($bot, $destination, '777', ['command' => 'start']);
        $this->assertSame(['📅 Today', '📆 Next 7 days', '📊 Analytics', '🎮 Games', '📚 Resources', '💳 Money', '💬 Student Feedback', '📖 Student Insights', '↻ Refresh'], array_column($this->buttons($page), 'text'));
        $help = app(TelegramNavigationService::class)->render($bot, $destination, '777', ['command' => 'help']);
        $this->assertStringContainsString('/feedback — Student Feedback', $help['text']);
        $this->assertStringContainsString('/insights — Student Insights', $help['text']);
        Http::assertNothingSent();
    }

    public static function deferredRevocations(): array
    {
        $cases = [];
        foreach (['feedback', 'insights'] as $command) {
            foreach (['destination', 'sender', 'summary', 'global', 'bot'] as $revocation) {
                $cases[$command.' '.$revocation] = [$command, $revocation];
            }
        }
        $cases['insights private to group'] = ['insights', 'group'];

        return $cases;
    }

    #[DataProvider('deferredRevocations')]
    public function test_deferred_new_sections_recheck_current_destination_and_access(string $command, string $revocation): void
    {
        [$bot, $destination] = $this->bot();
        config(['queue.default' => 'database']);
        $this->freezeTime();
        $this->message($bot, $destination, '/start');
        $this->message($bot, $destination, '/'.$command, id: 2);
        $delivery = TelegramDelivery::query()->where('trigger', 'command_'.$command)->sole();
        $this->assertSame('pending', $delivery->status);
        match ($revocation) {
            'destination' => $destination->update(['enabled' => false]),
            'sender' => $destination->update(['allowed_user_ids' => []]),
            'summary' => $destination->update(['detail_level' => 'summary']),
            'global' => Setting::set('telegram.commands_enabled', false),
            'bot' => $bot->update(['enabled' => false]),
            'group' => $destination->update(['type' => 'group']),
        };
        $this->travel(6)->seconds();
        app(TelegramDeliveryService::class)->deliver($delivery->id);
        $this->assertSame('cancelled', $delivery->fresh()->status);
        Http::assertSentCount(1);
        Queue::assertPushed(DeliverTelegramMessage::class);
    }
}
