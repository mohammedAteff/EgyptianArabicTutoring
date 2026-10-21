<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\StaffTask;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\MeetingRoom;
use App\Domains\CMS\Models\Setting;
use App\Domains\Notifications\Models\TelegramDelivery;
use App\Domains\Notifications\Models\TelegramRule;
use App\Domains\Notifications\Services\TelegramDeliveryService;
use App\Domains\Notifications\Services\TelegramReadService;
use App\Domains\Notifications\Services\TelegramRuleCatalog;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\HomeworkSubmission;
use App\Domains\Students\Models\LessonFeedback;
use App\Domains\Students\Models\PackageInstallment;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Jobs\DeliverTelegramMessage;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Database\Factories\TelegramBotFactory;
use Database\Factories\TelegramDestinationFactory;
use Database\Factories\TelegramRuleFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TelegramAlertCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function rule(string $trigger, array $values = []): TelegramRule
    {
        $bot = TelegramBotFactory::new()->create();
        $destination = TelegramDestinationFactory::new()->create(['telegram_bot_id' => $bot->id, 'detail_level' => 'personal']);
        $rule = TelegramRuleFactory::new()->create(array_replace(['telegram_bot_id' => $bot->id, 'trigger' => $trigger, 'template' => app(TelegramRuleCatalog::class)->get($trigger)['template'], 'cooldown_minutes' => 1440], $values));
        $rule->destinations()->attach($destination);

        return $rule;
    }

    public function test_rules_are_collapsible_by_category_and_the_editing_category_is_open(): void
    {
        $booking = $this->rule('booking_created', ['name' => 'Booking notice']);
        $finance = $this->rule('payment_recorded', ['name' => 'Payment notice']);
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']));
        $page = $this->get(route('admin.telegram.index', ['tab' => 'rules', 'edit' => $finance->id]))->assertOk()->assertSeeText('Alert category');
        $document = new \DOMDocument;
        @$document->loadHTML($page->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertCount(1, $xpath->query('//details[@open][.//a[contains(@href,"edit='.$finance->id.'")]]'));
        $this->assertCount(1, $xpath->query('//details[not(@open)][.//a[contains(@href,"edit='.$booking->id.'")]]'));
        $this->assertCount(1, $xpath->query('//select[@x-model="category"]/option[@value="Finance"]'));
        $groups = array_column($page->viewData('catalog'), 'group');
        $expected = $groups;
        sort($expected, SORT_NATURAL | SORT_FLAG_CASE);
        $this->assertSame($expected, $groups);
        $this->assertSame('Finance', $page->viewData('catalog')['low_credits']['group']);
        $this->assertStringNotContainsString('{birthday}', $page->viewData('catalog')['waitlist_opening']['template']);
    }

    public function test_overdue_installment_uses_actual_unpaid_amount_and_cancels_queued_alert_after_settlement(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-21 10:00:00 UTC'));
        Queue::fake();
        Http::preventStrayRequests();
        Setting::set('telegram.automation_enabled', true);
        $this->rule('installment_overdue', ['threshold' => 2]);
        $package = StudentPackage::factory()->create(['original_price' => '100.00', 'discount_amount' => '0.00', 'final_price' => '100.00']);
        PackageInstallment::factory()->create(['student_package_id' => $package->id, 'due_date' => '2026-10-18', 'expected_amount' => '100.00']);
        PaymentRecord::factory()->create(['student_package_id' => $package->id, 'amount_paid' => '40.00']);
        app(TelegramReadService::class)->tick();
        $delivery = TelegramDelivery::where('trigger', 'installment_overdue')->sole();
        $this->assertSame('60.00', $delivery->payload['source']['amount']);
        app(TelegramReadService::class)->tick();
        $this->assertSame(1, TelegramDelivery::where('trigger', 'installment_overdue')->count());
        PaymentRecord::factory()->create(['student_package_id' => $package->id, 'amount_paid' => '60.00']);
        app(TelegramDeliveryService::class)->deliver($delivery->id);
        $this->assertSame('cancelled', $delivery->fresh()->status);
        Http::assertNothingSent();
        Queue::assertPushed(DeliverTelegramMessage::class, 1);
    }

    public function test_completed_staff_tasks_and_fixed_meeting_details_cancel_queued_alerts(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-21 10:00:00 UTC'));
        Queue::fake();
        Http::preventStrayRequests();
        Setting::set('telegram.automation_enabled', true);
        $this->rule('staff_task_overdue', ['threshold' => 1]);
        $this->rule('meeting_missing', ['minutes' => 90]);
        $task = StaffTask::factory()->create(['due_date' => '2026-10-19']);
        StaffTask::factory()->create(['due_date' => '2026-10-19', 'status' => 'completed']);
        $booking = Booking::factory()->create(['start_at_utc' => now('UTC')->addHour(), 'end_at_utc' => now('UTC')->addHours(2)]);
        app(TelegramReadService::class)->tick();
        $this->assertSame(1, TelegramDelivery::where('trigger', 'staff_task_overdue')->count());
        $this->assertSame(1, TelegramDelivery::where('trigger', 'meeting_missing')->count());
        $task->update(['status' => 'completed']);
        $room = MeetingRoom::factory()->create();
        $booking->update(['meeting_room_id' => $room->id, 'meeting_url_snapshot' => $room->url]);
        foreach (TelegramDelivery::all() as $delivery) {
            app(TelegramDeliveryService::class)->deliver($delivery->id);
            $this->assertSame('cancelled', $delivery->fresh()->status);
        }
        Http::assertNothingSent();
        Queue::assertPushed(DeliverTelegramMessage::class, 2);
    }

    public function test_homework_and_feedback_events_do_not_transmit_submitted_answers_or_comments(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Setting::set('telegram.automation_enabled', true);
        $this->rule('homework_submitted');
        $this->rule('lesson_feedback');
        $student = Student::factory()->verified()->create();
        $booking = Booking::factory()->create(['student_id' => $student->id]);
        $homework = Homework::factory()->create(['student_id' => $student->id, 'booking_id' => $booking->id]);
        DB::transaction(function () use ($homework, $student, $booking): void {
            HomeworkSubmission::factory()->create(['homework_id' => $homework->id, 'body' => 'Private answer']);
            LessonFeedback::factory()->create(['student_id' => $student->id, 'booking_id' => $booking->id, 'comment' => 'Private feedback']);
        });
        $this->assertDatabaseCount('telegram_deliveries', 2);
        foreach (TelegramDelivery::all() as $delivery) {
            $this->assertArrayNotHasKey('body', $delivery->payload['source']);
            $this->assertArrayNotHasKey('comment', $delivery->payload['source']);
            $this->assertSame($student->id, $delivery->payload['source']['_student_id']);
        }
        Queue::assertPushed(DeliverTelegramMessage::class, 2);
    }

    public function test_overdue_alerts_refresh_partial_payments_and_respect_minimum_days_overdue(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-21 10:00:00 UTC'));
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 321]], 200)]);
        Setting::set('telegram.automation_enabled', true);
        $this->rule('installment_overdue', ['threshold' => 2]);
        $package = StudentPackage::factory()->create(['original_price' => '100.00', 'discount_amount' => '0.00', 'final_price' => '100.00']);
        PackageInstallment::factory()->create(['student_package_id' => $package->id, 'due_date' => '2026-10-18', 'expected_amount' => '100.00']);
        $recent = StudentPackage::factory()->create();
        PackageInstallment::factory()->create(['student_package_id' => $recent->id, 'due_date' => '2026-10-20']);
        app(TelegramReadService::class)->tick();
        $delivery = TelegramDelivery::where('trigger', 'installment_overdue')->sole();
        $this->assertSame($package->id, $delivery->payload['source']['record_id']);
        PaymentRecord::factory()->create(['student_package_id' => $package->id, 'amount_paid' => '40.00']);
        app(TelegramDeliveryService::class)->deliver($delivery->id);
        $this->assertSame('60.00', $delivery->fresh()->payload['source']['amount']);
        $this->assertSame('sent', $delivery->fresh()->status);
        Http::assertSentCount(1);
        Queue::assertPushed(DeliverTelegramMessage::class, 1);
    }
}
