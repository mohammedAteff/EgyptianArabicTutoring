<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Services\AdminNotificationService;
use App\Domains\Booking\Models\Booking;
use App\Domains\CMS\Models\Setting;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Notifications\Models\TelegramDelivery;
use App\Domains\Notifications\Models\TelegramDestination;
use App\Domains\Notifications\Services\TelegramDeliveryService;
use App\Domains\Resources\Models\ResourceRequest;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentNotification;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\System\Services\DevelopmentDataCatalog;
use App\Domains\System\Services\DevelopmentDataReconciler;
use App\Domains\System\Services\DevelopmentToolsService;
use App\Jobs\DeliverTelegramMessage;
use Database\Factories\AdministratorFactory;
use Database\Factories\ResourceFactory;
use Database\Factories\TelegramDestinationFactory;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class DevelopmentResetNumberingTest extends TestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $exceptTables = ['entitlement_types', 'meeting_providers'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['development_tools.enabled' => true, 'development_tools.require_snapshot' => false, 'session.driver' => 'database']);
        $this->assertSame(0, DB::transactionLevel());
    }

    private function actor(): Administrator
    {
        return AdministratorFactory::new()->create(['role' => 'super_admin', 'password' => Hash::make('ResetQaPass2026!')]);
    }

    /** @param array<string, mixed> $scope
     * @return array<string, mixed> */
    private function reset(Administrator $actor, string $domain, array $scope = []): array
    {
        $tools = app(DevelopmentToolsService::class);
        $preview = $tools->preview($actor, 'numbering-session', 'reset', $domain, $scope);

        return $tools->confirm($actor, 'numbering-session', $preview['token'], 'ResetQaPass2026!', null, null, DevelopmentDataCatalog::PHRASES[$domain]);
    }

    #[TestWith(['telegram', 'RESET TELEGRAM DELIVERY HISTORY'])]
    #[TestWith(['notifications', 'RESET ADMIN NOTIFICATIONS'])]
    #[TestWith(['leads', 'RESET RESOURCE INQUIRIES'])]
    public function test_new_reset_endpoint_previews_without_deleting_records(string $domain, string $phrase): void
    {
        $actor = $this->actor();
        $lead = Contact::query()->create(['name' => 'Preview lead', 'email' => 'preview@example.test']);
        $notification = app(AdminNotificationService::class)->send('qa', 'Preview notification', 'Leave intact');
        $delivery = $this->delivery();

        $this->actingAs($actor)->post(route('admin.development-tools.preview'), ['type' => 'reset', 'domain' => $domain])
            ->assertOk()->assertSee($phrase)->assertSee('Current password');

        $this->assertModelExists($lead);
        $this->assertModelExists($notification);
        $this->assertModelExists($delivery);
    }

    public function test_notification_reset_restarts_at_one_and_replay_preserves_new_notification(): void
    {
        $actor = $this->actor();
        $studentNotification = StudentNotification::factory()->create();
        $service = app(AdminNotificationService::class);
        $notification = $service->send('qa', 'Original', 'Original message');
        DB::table('admin_notifications')->where('id', $notification->id)->update(['id' => 51]);
        $tools = app(DevelopmentToolsService::class);
        $preview = $tools->preview($actor, 'numbering-session', 'reset', 'notifications', []);

        $summary = $tools->confirm($actor, 'numbering-session', $preview['token'], 'ResetQaPass2026!', null, null, 'RESET ADMIN NOTIFICATIONS');
        $next = $service->send('qa', 'Original', 'Fresh message');
        $replay = $tools->confirm($actor, 'numbering-session', $preview['token'], '', null, null, '');

        $this->assertSame(1, $next->id);
        $this->assertSame('Fresh message', $next->message);
        $this->assertSame($summary, $replay);
        $this->assertSame('Restarted: next record #1', $summary['numbering_results']['admin_notifications']);
        $this->assertModelExists($studentNotification);
        $this->assertModelExists($next);
        $this->assertDatabaseHas('audit_logs', ['action' => 'development_data_numbering_completed']);
        $this->actingAs($actor)->get(route('admin.notifications.index'))->assertSee('Notification #1');
    }

    public function test_telegram_reset_restarts_history_preserves_configuration_and_blocks_old_jobs(): void
    {
        Http::preventStrayRequests();
        $actor = $this->actor();
        $delivery = $this->delivery();
        $destination = $delivery->destination;
        $bot = $delivery->bot;
        $oldJob = new DeliverTelegramMessage($delivery->id);
        $legacyJob = clone $oldJob;
        $legacyJob->expectedDedupeKey = null;
        Setting::set('telegram.automation_enabled', true);

        $summary = $this->reset($actor, 'telegram');
        $next = $this->delivery($destination->id);
        $oldJob->handle(app(TelegramDeliveryService::class));
        $legacyJob->handle(app(TelegramDeliveryService::class));

        $this->assertSame(1, $next->id);
        $this->assertSame('pending', $next->fresh()->status);
        $this->assertSame('completed', $summary['numbering_status']);
        $this->assertModelExists($destination);
        $this->assertModelExists($bot);
        $this->assertSame(1, (int) Setting::get('telegram.automation_enabled'));
        Http::assertNothingSent();

        $bot->update(['enabled' => false]);
        (new DeliverTelegramMessage($next->id))->handle(app(TelegramDeliveryService::class));
        $this->assertSame('cancelled', $next->fresh()->status);
    }

    public function test_sending_telegram_delivery_blocks_reset(): void
    {
        $actor = $this->actor();
        $delivery = $this->delivery();
        $delivery->update(['status' => 'sending']);

        $this->actingAs($actor)->post(route('admin.development-tools.preview'), ['type' => 'reset', 'domain' => 'telegram'])->assertSessionHasErrors('scope');

        $this->assertModelExists($delivery);
        $this->assertDatabaseCount('development_data_operations', 0);
    }

    public function test_queued_delivery_identity_cannot_target_a_reused_id(): void
    {
        Http::preventStrayRequests();
        $delivery = $this->delivery();
        $job = new DeliverTelegramMessage($delivery->id);
        $delivery->update(['dedupe_key' => fake()->sha256()]);

        $job->handle(app(TelegramDeliveryService::class));

        $this->assertSame('pending', $delivery->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_leads_reset_removes_inquiries_and_unbooked_contacts_preserves_booked_people_and_resources(): void
    {
        $actor = $this->actor();
        $lead = Contact::query()->create(['name' => 'Lead to remove', 'email' => 'lead@example.test']);
        $booking = Booking::factory()->create();
        $resource = ResourceFactory::new()->create();
        ResourceRequest::query()->create(['contact_id' => $lead->id, 'resource_id' => $resource->id]);
        ResourceRequest::query()->create(['contact_id' => $booking->contact_id, 'resource_id' => $resource->id]);
        $student = Student::factory()->verified()->create();

        $summary = $this->reset($actor, 'leads');
        $newRequest = ResourceRequest::query()->create(['contact_id' => $booking->contact_id, 'resource_id' => $resource->id]);

        $this->assertDatabaseMissing('contacts', ['id' => $lead->id]);
        $this->assertModelExists($booking->contact);
        $this->assertModelExists($booking);
        $this->assertModelExists($resource);
        $this->assertModelExists($student);
        $this->assertSame(1, $newRequest->id);
        $this->assertSame('Preserved: records remain', $summary['numbering_results']['contacts']);
        $this->assertSame(0, $summary['reconciliation']['orphan_count']);
    }

    #[TestWith(['students', 'students'])]
    #[TestWith(['analytics', 'analytics_events'])]
    #[TestWith(['resources', 'resources'])]
    public function test_existing_resets_restart_emptied_business_numbering(string $domain, string $table): void
    {
        $actor = $this->actor();
        if ($domain === 'students') {
            Student::factory()->verified()->create(['id' => 51]);
        } elseif ($domain === 'resources') {
            ResourceFactory::new()->create(['id' => 51]);
        } else {
            DB::table('analytics_events')->insert(['id' => 51, 'event_uuid' => fake()->uuid(), 'event_name' => 'page_view', 'page' => '/', 'metadata' => '{}', 'is_bot' => false, 'created_at' => now()]);
        }

        $summary = $this->reset($actor, $domain, $domain === 'resources' ? ['resources' => true, 'history' => true] : []);
        $nextId = DB::selectOne('SELECT AUTO_INCREMENT AS next_id FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table])->next_id;

        $this->assertDatabaseCount($table, 0);
        $this->assertSame(1, (int) $nextId);
        $this->assertSame('Restarted: next record #1', $summary['numbering_results'][$table]);
    }

    public function test_financial_numbering_restarts_but_retained_free_booking_is_not_renumbered(): void
    {
        $actor = $this->actor();
        $student = Student::factory()->verified()->create();
        $booking = Booking::factory()->create(['student_id' => $student->id, 'funding_mode' => 'free']);
        $ledger = app(StudentLedgerService::class);
        $ledger->createPackage($student, 'Old purchase', 1, '30.00', '0.00', 'USD', null, 'old-purchase', $actor->id, null, null, 'one_hour');

        $this->reset($actor, 'financial');
        $next = $ledger->createPackage($student, 'New purchase', 1, '30.00', '0.00', 'USD', null, 'new-purchase', $actor->id, null, null, 'one_hour');

        $this->assertSame(1, $next->id);
        $this->assertModelExists($booking);
        $this->assertModelExists($student);
    }

    public function test_failed_reconciliation_leaves_history_and_numbering_unchanged(): void
    {
        $actor = $this->actor();
        $notification = app(AdminNotificationService::class)->send('qa', 'Original', 'Unchanged');
        DB::table('admin_notifications')->where('id', $notification->id)->update(['id' => 51]);
        $this->mock(DevelopmentDataReconciler::class)->shouldReceive('verify')->once()->andThrow(ValidationException::withMessages(['reconciliation' => 'QA failure']));
        $tools = app(DevelopmentToolsService::class);
        $preview = $tools->preview($actor, 'numbering-session', 'reset', 'notifications', []);

        try {
            $tools->confirm($actor, 'numbering-session', $preview['token'], 'ResetQaPass2026!', null, null, 'RESET ADMIN NOTIFICATIONS');
            $this->fail('A failed reconciliation must roll back.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reconciliation', $exception->errors());
        }

        $this->assertDatabaseHas('admin_notifications', ['id' => 51]);
        $this->assertSame('pending', $preview['operation']->fresh()->status);
        $next = app(AdminNotificationService::class)->send('qa-next', 'Next', 'Next message');
        $this->assertSame(52, $next->id);
    }

    private function delivery(?int $destinationId = null): TelegramDelivery
    {
        $destination = $destinationId === null ? TelegramDestinationFactory::new()->create() : TelegramDestination::query()->findOrFail($destinationId);

        return TelegramDelivery::query()->create(['telegram_bot_id' => $destination->telegram_bot_id, 'telegram_destination_id' => $destination->id,
            'dedupe_key' => fake()->sha256(), 'trigger' => 'qa', 'payload' => ['parts' => ['QA history only']], 'due_at' => now('UTC')]);
    }
}
