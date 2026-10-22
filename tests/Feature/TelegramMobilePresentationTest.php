<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\CMS\Models\Setting;
use App\Domains\Games\Models\Game;
use App\Domains\Notifications\Models\TelegramDelivery;
use App\Domains\Notifications\Services\TelegramBusinessReadService;
use App\Domains\Notifications\Services\TelegramDeliveryService;
use App\Domains\Notifications\Services\TelegramNavigationService;
use App\Domains\Notifications\Services\TelegramReadService;
use App\Domains\Notifications\Services\TelegramReportFormatter;
use App\Domains\Notifications\Services\TelegramRuleCatalog;
use App\Domains\Notifications\Services\TelegramTemplateService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\StudentLedgerService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Database\Factories\ResourceFactory;
use Database\Factories\StaffTaskFactory;
use Database\Factories\TelegramBotFactory;
use Database\Factories\TelegramDestinationFactory;
use Database\Factories\TelegramRuleFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TelegramMobilePresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00 UTC'));
        Http::preventStrayRequests();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
        Queue::fake();
        Setting::set('telegram.commands_enabled', true);
        Setting::set('telegram.automation_enabled', true);
    }

    private function package(Student $student, int $balance, string $code): StudentPackage
    {
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'Practice package', max(1, $balance), '100', '0', 'USD', null, (string) Str::uuid(), entitlementCode: $code);
        if ($balance === 0) {
            $ledger->adjustCredits($package, -1, 'Used lesson fixture', (string) Str::uuid(), allocationId: $package->entitlements()->value('id'));
        }

        return $package;
    }

    #[TestWith([0, 10, false])]
    #[TestWith([10, 0, false])]
    #[TestWith([1, 1, true])]
    #[TestWith([0, 0, true])]
    #[TestWith([2, 1, false])]
    public function test_attention_and_daily_digest_apply_the_student_wide_credit_rule(int $oneHour, int $twoHour, bool $low): void
    {
        $student = Student::factory()->verified()->create(['first_name' => 'Example', 'last_name' => 'Learner']);
        $this->package($student, $oneHour, 'one_hour');
        $this->package($student, $twoHour, 'two_hour');
        $before = $student->packages()->with('ledgerEntries')->get()->toJson();
        $report = app(TelegramBusinessReadService::class)->attention(true, 'credits');
        $this->assertStringContainsString('Students with low credits — '.($low ? '1' : '0'), $report['text']);
        if ($low) {
            $this->assertStringContainsString('1-hour: '.$oneHour.' · 2-hour: '.$twoHour.' available', $report['text']);
            $this->assertSame(1, substr_count($report['text'], 'Example Learner'));
        } else {
            $this->assertStringNotContainsString('Example Learner', $report['text']);
        }
        $this->assertStringContainsString('Students with low credits (all types ≤1): '.($low ? '1' : '0'), app(TelegramReadService::class)->businessDigest(['students']));
        $this->assertSame($before, $student->packages()->with('ledgerEntries')->get()->toJson());
        Http::assertNothingSent();
    }

    public function test_credit_check_sums_separate_packages_of_the_same_type(): void
    {
        $student = Student::factory()->verified()->create();
        $this->package($student, 1, 'one_hour');
        $this->package($student, 1, 'one_hour');
        $this->package($student, 0, 'two_hour');
        $this->assertStringContainsString('Students with low credits — 0', app(TelegramBusinessReadService::class)->attention(true, 'credits')['text']);
        Http::assertNothingSent();
    }

    public function test_queued_low_credit_alert_is_cancelled_after_a_top_up_of_the_other_type(): void
    {
        $student = Student::factory()->verified()->create();
        $one = $this->package($student, 0, 'one_hour');
        $bot = TelegramBotFactory::new()->create();
        $destination = TelegramDestinationFactory::new()->create(['telegram_bot_id' => $bot->id]);
        $rule = TelegramRuleFactory::new()->create(['telegram_bot_id' => $bot->id, 'trigger' => 'low_credits', 'threshold' => 1, 'template' => app(TelegramRuleCatalog::class)->get('low_credits')['template']]);
        $rule->destinations()->attach($destination);
        app(TelegramReadService::class)->scanPackage($one);
        $delivery = TelegramDelivery::firstOrFail();
        $this->package($student, 10, 'two_hour');
        app(TelegramDeliveryService::class)->deliver($delivery->id);
        $this->assertSame('cancelled', $delivery->fresh()->status);
        $this->assertSame('source_no_longer_eligible', $delivery->fresh()->failure_code);
        Http::assertNothingSent();
    }

    #[TestWith(['personal'])]
    #[TestWith(['summary'])]
    public function test_a_valid_low_credit_alert_uses_its_configured_threshold_and_current_balances(string $detail): void
    {
        $student = Student::factory()->verified()->create(['first_name' => 'Private', 'last_name' => 'Learner']);
        $one = $this->package($student, 2, 'one_hour');
        $this->package($student, 0, 'two_hour');
        $bot = TelegramBotFactory::new()->create();
        $destination = TelegramDestinationFactory::new()->create(['telegram_bot_id' => $bot->id, 'detail_level' => $detail]);
        $rule = TelegramRuleFactory::new()->create(['telegram_bot_id' => $bot->id, 'trigger' => 'low_credits', 'threshold' => 2, 'template' => app(TelegramRuleCatalog::class)->get('low_credits')['template']]);
        $rule->destinations()->attach($destination);
        app(TelegramReadService::class)->scanPackage($one);
        $delivery = TelegramDelivery::firstOrFail();
        app(TelegramDeliveryService::class)->deliver($delivery->id);
        $this->assertSame('sent', $delivery->fresh()->status);
        Http::assertSent(function (Request $request) use ($detail): bool {
            $this->assertStringContainsString('1-hour: 2 · 2-hour: 0 available', $request['text']);
            if ($detail === 'summary') {
                $this->assertStringNotContainsString('Private Learner', $request['text']);
            } else {
                $this->assertStringContainsString('Private Learner', $request['text']);
            }

            return true;
        });
    }

    public function test_attention_returns_to_the_first_page_when_old_items_are_resolved(): void
    {
        $tasks = StaffTaskFactory::new()->count(6)->create(['status' => 'open', 'due_date' => '2026-10-21']);
        $tasks->last()->update(['status' => 'completed']);
        $page = app(TelegramBusinessReadService::class)->attention(true, 'tasks', 1);
        $this->assertStringContainsString('Showing 1–5 of 5', $page['text']);
        $this->assertStringNotContainsString($tasks->last()->title, $page['text']);
        Http::assertNothingSent();
    }

    public function test_clear_checks_are_hidden_but_available_through_all_checks(): void
    {
        $bot = TelegramBotFactory::new()->create(['commands_enabled' => true, 'commands' => ['attention']]);
        $destination = TelegramDestinationFactory::new()->create(['telegram_bot_id' => $bot->id, 'allowed_user_ids' => ['777']]);
        $navigation = app(TelegramNavigationService::class);
        $page = $navigation->render($bot, $destination, '777', ['command' => 'attention']);
        $this->assertStringContainsString('All clear', $page['text']);
        $this->assertStringNotContainsString('Overdue installments', $page['text']);
        $button = collect($page['reply_markup']['inline_keyboard'])->flatten(1)->firstWhere('text', 'All checks');
        $state = $navigation->callback($bot, $destination, '777', $button['callback_data']);
        $all = $navigation->render($bot, $destination, '777', $state);
        $this->assertStringContainsString('0 · Overdue installments', $all['text']);
        $labels = collect($all['reply_markup']['inline_keyboard'])->flatten(1)->pluck('text');
        $this->assertContains('Low credits (0)', $labels->all());
        foreach ($all['reply_markup']['inline_keyboard'] as $row) {
            if (count($row) === 2) {
                $this->assertLessThanOrEqual(18, mb_strlen($row[0]['text']));
                $this->assertLessThanOrEqual(18, mb_strlen($row[1]['text']));
            }
        }
        Http::assertNothingSent();
    }

    public function test_analytics_overview_has_separate_detail_and_date_navigation(): void
    {
        $bot = TelegramBotFactory::new()->create(['commands_enabled' => true, 'commands' => ['stats']]);
        $destination = TelegramDestinationFactory::new()->create(['telegram_bot_id' => $bot->id, 'allowed_user_ids' => ['777']]);
        $navigation = app(TelegramNavigationService::class);
        $page = $navigation->render($bot, $destination, '777', ['command' => 'stats', 'range' => '30d']);
        $this->assertLessThan(1100, mb_strlen($page['text']));
        $this->assertStringNotContainsString('Top pages', $page['text']);
        $this->assertStringNotContainsString('Change vs', $page['text']);
        $buttons = collect($page['reply_markup']['inline_keyboard'])->flatten(1);
        $this->assertNull($buttons->firstWhere('text', 'Yesterday'));
        $state = $navigation->callback($bot, $destination, '777', $buttons->firstWhere('text', '📅 Date range')['callback_data']);
        $picker = $navigation->render($bot, $destination, '777', $state);
        $buttons = collect($picker['reply_markup']['inline_keyboard'])->flatten(1);
        $next = $navigation->callback($bot, $destination, '777', $buttons->firstWhere('text', '7 days')['callback_data']);
        $this->assertSame('7d', $next['range']);
        $this->assertFalse($next['period_picker']);
        $report = $navigation->render($bot, $destination, '777', $next);
        $this->assertStringContainsString('16 Oct 2026 – 22 Oct 2026', $report['text']);
        $this->assertSame(['↻ Refresh', '🏠 Home'], array_column($report['reply_markup']['inline_keyboard'][count($report['reply_markup']['inline_keyboard']) - 1], 'text'));
        Http::assertNothingSent();
    }

    public function test_game_scores_and_counting_notes_remain_available_without_crowding_the_overview(): void
    {
        $game = Game::create(['title' => 'Vocabulary', 'slug' => 'vocabulary', 'status' => 'available']);
        $bot = TelegramBotFactory::new()->create(['commands_enabled' => true, 'commands' => ['stats']]);
        $destination = TelegramDestinationFactory::new()->create(['telegram_bot_id' => $bot->id, 'allowed_user_ids' => ['777']]);
        $navigation = app(TelegramNavigationService::class);
        $state = ['command' => 'games', 'section' => 'games', 'id' => $game->id, 'range' => '7d'];
        $page = $navigation->render($bot, $destination, '777', $state);
        $this->assertStringNotContainsString('Legacy starts', $page['text']);
        $buttons = collect($page['reply_markup']['inline_keyboard'])->flatten(1);
        $scores = $navigation->callback($bot, $destination, '777', $buttons->firstWhere('text', 'Scores & time')['callback_data']);
        $this->assertStringContainsString('Average score: Not available', $navigation->render($bot, $destination, '777', $scores)['text']);
        $notes = $navigation->callback($bot, $destination, '777', $buttons->firstWhere('text', 'How counted')['callback_data']);
        $this->assertStringContainsString('Association, not causation', $navigation->render($bot, $destination, '777', $notes)['text']);
        Http::assertNothingSent();
    }

    public function test_website_pages_and_comparisons_are_accessible_from_the_details_menu(): void
    {
        AnalyticsEvent::create(['event_name' => 'page_view', 'page' => '/games', 'metadata' => [], 'created_at' => now('UTC'), 'is_bot' => false]);
        $bot = TelegramBotFactory::new()->create(['commands_enabled' => true, 'commands' => ['stats']]);
        $destination = TelegramDestinationFactory::new()->create(['telegram_bot_id' => $bot->id, 'allowed_user_ids' => ['777']]);
        $navigation = app(TelegramNavigationService::class);
        $page = $navigation->render($bot, $destination, '777', ['command' => 'stats', 'range' => '7d']);
        $buttons = collect($page['reply_markup']['inline_keyboard'])->flatten(1);
        $details = $navigation->callback($bot, $destination, '777', $buttons->firstWhere('text', 'Website details')['callback_data']);
        $menu = $navigation->render($bot, $destination, '777', $details);
        $buttons = collect($menu['reply_markup']['inline_keyboard'])->flatten(1);
        $pages = $navigation->callback($bot, $destination, '777', $buttons->firstWhere('text', 'Pages')['callback_data']);
        $report = $navigation->render($bot, $destination, '777', $pages);
        $this->assertStringContainsString('Games — 1', app(TelegramReportFormatter::class)->format($report['text'])['text']);
        $changes = $navigation->callback($bot, $destination, '777', $buttons->firstWhere('text', 'Changes')['callback_data']);
        $comparison = $navigation->render($bot, $destination, '777', $changes);
        $this->assertStringContainsString('Page views: 1 · +1 · new activity', $comparison['text']);
        $this->assertStringContainsString('Booking events: 0 · unchanged', $comparison['text']);
        Http::assertNothingSent();
    }

    public function test_resource_journey_and_access_limitations_are_available_in_separate_views(): void
    {
        $resource = ResourceFactory::new()->create(['external_url' => 'https://example.org/resource']);
        $bot = TelegramBotFactory::new()->create(['commands_enabled' => true, 'commands' => ['stats']]);
        $destination = TelegramDestinationFactory::new()->create(['telegram_bot_id' => $bot->id, 'allowed_user_ids' => ['777']]);
        $navigation = app(TelegramNavigationService::class);
        $page = $navigation->render($bot, $destination, '777', ['command' => 'resources', 'section' => 'resources', 'id' => $resource->id, 'range' => '7d']);
        $this->assertStringContainsString('Requests: 0', $page['text']);
        $this->assertStringNotContainsString('File receipt', $page['text']);
        $buttons = collect($page['reply_markup']['inline_keyboard'])->flatten(1);
        $journey = $navigation->callback($bot, $destination, '777', $buttons->firstWhere('text', 'Request journey')['callback_data']);
        $this->assertStringContainsString('First-time: 0', $navigation->render($bot, $destination, '777', $journey)['text']);
        $notes = $navigation->callback($bot, $destination, '777', $buttons->firstWhere('text', 'How counted')['callback_data']);
        $this->assertStringContainsString('File receipt is not confirmed', $navigation->render($bot, $destination, '777', $notes)['text']);
        Http::assertNothingSent();
    }

    public function test_previous_default_templates_are_upgraded_but_custom_markup_is_literal(): void
    {
        $templates = app(TelegramTemplateService::class);
        $previous = "💳 Payment recorded\n{student_name}\n{amount} {currency}\n{admin_url}";
        $values = ['student_name' => '<b>Example</b>', 'amount' => '25.00', 'currency' => 'USD', 'admin_url' => 'https://example.org/admin'];
        $parts = $templates->deliveryParts('payment_recorded', $previous, $values, true);
        $this->assertSame("💳 Payment recorded\n\n<b>Example</b>\n25.00 USD\n\nOpen details", $parts['parts'][0]);
        $preview = app(TelegramReportFormatter::class)->previewHtml($parts['parts'][0], $parts['entities'][0])->toHtml();
        $this->assertStringContainsString('<strong>💳 Payment recorded</strong>', $preview);
        $this->assertStringContainsString('&lt;b&gt;Example&lt;/b&gt;', $preview);
        $this->assertStringContainsString('href="https://example.org/admin"', $preview);
        $custom = $templates->deliveryParts('payment_recorded', '<script>{student_name}</script>', $values, true);
        $this->assertSame('<script><b>Example</b></script>', $custom['parts'][0]);
        $this->assertStringNotContainsString('<script>', app(TelegramReportFormatter::class)->previewHtml($custom['parts'][0], $custom['entities'][0])->toHtml());
        Http::assertNothingSent();
    }

    public function test_website_preview_uses_the_delivery_formatter_without_sending_messages(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $template = app(TelegramRuleCatalog::class)->get('payment_recorded')['template'];
        $this->actingAs($admin)->from(route('admin.telegram.index', ['tab' => 'rules']))
            ->post(route('admin.telegram.preview'), ['trigger' => 'payment_recorded', 'template' => $template])
            ->assertRedirect()->assertSessionHas('preview_messages', fn (array $messages): bool => str_contains($messages['parts'][0], '50.00 USD') && $messages['entities'][0][0]['type'] === 'bold');
        $this->get(route('admin.telegram.index', ['tab' => 'rules']))->assertOk()->assertSee('Mobile message preview')->assertSee('<strong>💳 Payment recorded</strong>', false);
        $this->assertDatabaseCount('telegram_deliveries', 0);
        Http::assertNothingSent();
    }

    public function test_template_editor_shows_the_current_default_without_changing_the_saved_rule(): void
    {
        $previous = "💳 Payment recorded\n{student_name}\n{amount} {currency}\n{admin_url}";
        $rule = TelegramRuleFactory::new()->create(['trigger' => 'payment_recorded', 'template' => $previous]);
        $response = $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']))
            ->get(route('admin.telegram.index', ['tab' => 'rules', 'edit' => $rule->id]))->assertOk();
        $this->assertSame(app(TelegramRuleCatalog::class)->get('payment_recorded')['template'], $response->viewData('editingRule')->template);
        $this->assertSame($previous, $rule->fresh()->template);
        Http::assertNothingSent();
    }

    public function test_mobile_preview_keeps_long_unicode_custom_templates_literal_and_shows_message_parts(): void
    {
        $template = str_repeat('😀', 2000).'<script>{student_name}</script>';
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']))
            ->from(route('admin.telegram.index', ['tab' => 'rules']))
            ->post(route('admin.telegram.preview'), ['trigger' => 'payment_recorded', 'template' => $template])
            ->assertRedirect()->assertSessionHas('preview_messages', function (array $messages) use ($template): bool {
                $this->assertCount(2, $messages['parts']);
                $this->assertSame(str_replace('{student_name}', 'Example student', $template), implode('', $messages['parts']));

                return true;
            });
        $this->get(route('admin.telegram.index', ['tab' => 'rules']))->assertOk()
            ->assertSee('Message 1 of 2')->assertSee('Message 2 of 2')->assertSee('&lt;script&gt;Example student&lt;/script&gt;', false);
        $this->assertDatabaseCount('telegram_deliveries', 0);
        Http::assertNothingSent();
    }
}
