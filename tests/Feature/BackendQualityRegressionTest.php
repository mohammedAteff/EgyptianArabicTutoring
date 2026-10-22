<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionReschedule;
use App\Domains\CMS\Models\Setting;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Services\FormBuilderService;
use App\Domains\Forms\Services\FormCompletionReadModel;
use App\Domains\Notifications\Services\TelegramAutomationService;
use App\Domains\Notifications\Services\TelegramReadService;
use App\Domains\Reporting\Services\BookingReportQuery;
use App\Domains\Reporting\Services\ReportService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\System\Services\OperationFailureReporter;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentMethodFactory;
use Database\Factories\ResourceFactory;
use Database\Factories\TelegramBotFactory;
use Database\Factories\TelegramRuleFactory;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class BackendQualityRegressionTest extends TestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $exceptTables = ['entitlement_types'];

    public function test_deleting_one_resource_preserves_a_shared_private_file(): void
    {
        Storage::fake('local');
        $path = 'resources/shared.pdf';
        Storage::disk('local')->put($path, 'Shared lesson material');
        $removed = ResourceFactory::new()->create(['file_path' => $path]);
        $survivor = ResourceFactory::new()->create(['file_path' => $path]);

        $this->actingAs(Administrator::factory()->create(['role' => 'super_admin']))
            ->delete(route('admin.resources.destroy', $removed))->assertRedirect();

        $this->assertSoftDeleted($removed);
        $this->assertModelExists($survivor);
        Storage::disk('local')->assertExists($path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'resource_deleted', 'entity_id' => $removed->id]);
    }

    public function test_resource_audit_failure_preserves_the_record_and_file(): void
    {
        Storage::fake('local');
        $resource = ResourceFactory::new()->create(['file_path' => 'resources/original.pdf']);
        Storage::disk('local')->put($resource->file_path, 'Original lesson material');
        $this->failAudit('resource_deleted');

        $this->actingAs(Administrator::factory()->create(['role' => 'super_admin']))
            ->delete(route('admin.resources.destroy', $resource))->assertSessionHas('error');

        $this->assertNull($resource->fresh()->deleted_at);
        Storage::disk('local')->assertExists($resource->file_path);
    }

    public function test_package_audit_failure_rolls_back_purchase_and_credit_grant(): void
    {
        $student = Student::factory()->verified()->create();
        $this->failAudit('student_package_created');

        $this->actingAs(Administrator::factory()->create(['role' => 'admin']))
            ->post(route('admin.students.packages.store', $student), $this->purchasePayload())->assertSessionHas('error');

        $this->assertDatabaseCount('student_packages', 0);
        $this->assertDatabaseCount('session_ledger_entries', 0);
    }

    public function test_payment_audit_failure_rolls_back_an_unlimited_package_receipt(): void
    {
        $student = Student::factory()->verified()->create();
        $package = app(StudentLedgerService::class)->createPackage($student, 'Unlimited term', 2, '40', '0', 'USD', null, (string) Str::uuid(), entitlementCode: 'one_hour');
        $method = PaymentMethodFactory::new()->create();
        $this->failAudit('student_payment_recorded');

        $this->actingAs(Administrator::factory()->create(['role' => 'admin']))
            ->post(route('admin.students.payments.store', [$student, $package]), [
                'amount_paid' => '20.00', 'payment_method' => (string) $method->id, 'payment_idempotency_key' => (string) Str::uuid(),
            ])->assertSessionHas('error');

        $this->assertDatabaseCount('payment_records', 0);
        $this->assertDatabaseCount('session_ledger_entries', 1);
    }

    public function test_identical_preset_retry_across_business_midnight_returns_conflict_without_new_purchase(): void
    {
        Setting::set('business_timezone', 'Africa/Cairo');
        $this->travelTo(CarbonImmutable::parse('2026-10-21 20:59:00 UTC'));
        $student = Student::factory()->verified()->create();
        $payload = $this->purchasePayload();
        $this->actingAs(Administrator::factory()->create(['role' => 'admin']))
            ->post(route('admin.students.packages.store', $student), $payload)->assertSessionHasNoErrors();

        $this->travelTo(CarbonImmutable::parse('2026-10-21 21:01:00 UTC'));
        $this->post(route('admin.students.packages.store', $student), $payload)->assertConflict();

        $this->assertDatabaseCount('student_packages', 1);
        $this->assertDatabaseCount('session_ledger_entries', 1);
    }

    public function test_scheduled_country_rollup_uses_the_business_calendar_date(): void
    {
        Setting::set('business_timezone', 'Asia/Tokyo');
        $this->travelTo(CarbonImmutable::parse('2026-10-21 15:02:00 UTC'));
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => $event->getSummaryForDisplay() === 'analytics:aggregate-daily-country');
        Artisan::shouldReceive('call')->once()->with('analytics:aggregate-daily-country', ['--date' => '2026-10-21'])->andReturn(0);

        $event->run(app());

        $this->assertSame(0, $event->exitCode);
    }

    public function test_booking_report_labels_the_configured_business_timezone(): void
    {
        Setting::set('business_timezone', 'Asia/Tokyo');
        Booking::factory()->create(['start_at_utc' => '2026-10-21 10:00:00', 'end_at_utc' => '2026-10-21 11:00:00']);

        $report = app(ReportService::class)->getBookingsReport(CarbonImmutable::parse('2026-10-21 UTC'), CarbonImmutable::parse('2026-10-22 UTC'));

        $this->assertSame('19:00 Asia/Tokyo', $report['rows']->sole()['lesson_time_cairo']);
    }

    public function test_unshared_private_file_is_removed_only_after_committed_deletion(): void
    {
        Storage::fake('local');
        $resource = ResourceFactory::new()->create(['file_path' => 'resources/retired.pdf']);
        Storage::disk('local')->put($resource->file_path, 'Retired lesson material');

        $this->actingAs(Administrator::factory()->create(['role' => 'super_admin']))
            ->delete(route('admin.resources.destroy', $resource))->assertRedirect();

        $this->assertSoftDeleted($resource);
        Storage::disk('local')->assertMissing($resource->file_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'resource_deleted', 'entity_id' => $resource->id]);
    }

    public function test_older_preset_request_key_replays_after_midnight_but_rejects_changed_terms(): void
    {
        Setting::set('business_timezone', 'Africa/Cairo');
        $this->travelTo(CarbonImmutable::parse('2026-10-21 20:59:00 UTC'));
        $student = Student::factory()->verified()->create();
        $payload = $this->purchasePayload();
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, $payload['package_name'], 8, '280.00', '0.00', 'USD',
            $ledger->calculateExpirationDate(now('UTC'), 75), $payload['package_idempotency_key'], presetKey: 'foundation_track');
        $fingerprint = $package->purchase_fingerprint;
        $this->travelTo(CarbonImmutable::parse('2026-10-21 21:01:00 UTC'));

        $this->actingAs(Administrator::factory()->create(['role' => 'admin']))
            ->post(route('admin.students.packages.store', $student), $payload)->assertConflict();
        $payload['original_price'] = '281.00';
        $this->postJson(route('admin.students.packages.store', $student), $payload)->assertUnprocessable()->assertJsonValidationErrors('package');

        $this->assertDatabaseCount('student_packages', 1);
        $this->assertSame($fingerprint, $package->fresh()->purchase_fingerprint);
    }

    public function test_unexpected_errors_are_reported_without_sensitive_exception_text(): void
    {
        Log::spy();
        app(OperationFailureReporter::class)->report(
            new RuntimeException('SQL contains student@example.test and secret-token-value'), 'synthetic_operation');

        Log::shouldHaveReceived('error')->once()->with('Backend operation failed.', [
            'operation' => 'synthetic_operation', 'exception_type' => RuntimeException::class,
        ]);
    }

    public function test_staff_booking_failure_rolls_back_and_does_not_expose_database_details(): void
    {
        $booking = Booking::factory()->create(['status' => 'confirmed']);
        $this->failAudit('booking_marked_completed');

        $response = $this->actingAs(Administrator::factory()->create(['role' => 'admin']))
            ->post(route('admin.bookings.complete', $booking));

        $response->assertSessionHas('error', 'Unable to save this change. Please try again.');
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_batch_form_completion_matches_individual_assignments_with_fixed_query_count(): void
    {
        $actor = Administrator::factory()->create();
        $builder = app(FormBuilderService::class);
        foreach (['none', 'after_booking', 'after_reschedule', 'next_session_check', 'pre_booking'] as $trigger) {
            $form = $builder->create(['title' => $trigger, 'slug' => 'form-'.$trigger, 'trigger' => $trigger, 'is_mandatory' => true],
                [['question_key' => 'goal', 'label' => 'Goal', 'question_type' => 'short_text']], $actor);
            $builder->publish($form->id, $form->active_version_id, $form->lock_version);
        }
        $students = Student::factory()->verified()->count(12)->create();
        $booking = Booking::factory()->create(['student_id' => $students[0]->id, 'status' => 'confirmed', 'start_at_utc' => now('UTC')->addDay(), 'end_at_utc' => now('UTC')->addDay()->addHour()]);
        SessionReschedule::query()->create([
            'booking_id' => $booking->id, 'old_start_at_utc' => $booking->start_at_utc,
            'new_start_at_utc' => $booking->start_at_utc, 'actor_type' => 'admin', 'old_timezone' => 'UTC', 'new_timezone' => 'UTC', 'idempotency_key' => (string) Str::uuid(), 'created_at' => now('UTC'),
        ]);
        $read = app(FormCompletionReadModel::class);
        DB::enableQueryLog();
        $batch = $read->forStudents($students->modelKeys());
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(5, count($queries));
        foreach ($students as $student) {
            $this->assertEquals($read->forStudent($student)->all(), $batch->get($student->id)->all());
        }
    }

    public function test_booking_export_is_lazy_and_preserves_filters_across_batches(): void
    {
        Booking::factory()->count(251)->create(['status' => 'confirmed', 'source' => 'quality-regression', 'start_at_utc' => '2026-10-21 10:00:00', 'end_at_utc' => '2026-10-21 11:00:00']);
        Booking::factory()->create(['status' => 'cancelled', 'source' => 'quality-regression', 'start_at_utc' => '2026-10-21 10:00:00', 'end_at_utc' => '2026-10-21 11:00:00']);
        $query = app(BookingReportQuery::class);
        DB::enableQueryLog();
        $rows = $query->rows(CarbonImmutable::parse('2026-10-21 UTC'), CarbonImmutable::parse('2026-10-22 UTC'), 'confirmed', ['source' => 'quality-regression']);
        $this->assertCount(0, DB::getQueryLog());
        $count = 0;
        foreach ($rows as $row) {
            $this->assertSame('Confirmed', $row['status']);
            $count++;
        }
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(251, $count);
        $this->assertLessThanOrEqual(7, count($queries));
    }

    public function test_a_newer_draft_does_not_reopen_a_submitted_form_for_telegram_reminders(): void
    {
        Http::preventStrayRequests();
        Queue::fake();
        $actor = Administrator::factory()->create();
        $builder = app(FormBuilderService::class);
        $form = $builder->create(['title' => 'Profile', 'slug' => 'long-form', 'trigger' => 'none', 'is_mandatory' => true],
            [['question_key' => 'goal', 'label' => 'Goal', 'question_type' => 'short_text']], $actor);
        $form = $builder->publish($form->id, $form->active_version_id, $form->lock_version);
        $student = Student::factory()->verified()->create();
        Booking::factory()->create(['student_id' => $student->id, 'status' => 'confirmed', 'start_at_utc' => now('UTC')->addMinutes(30), 'end_at_utc' => now('UTC')->addMinutes(90)]);
        FormSubmission::query()->create(['student_id' => $student->id, 'form_version_id' => $form->published_version_id, 'status' => 'submitted', 'submitted_at' => now('UTC')]);
        FormSubmission::query()->create(['student_id' => $student->id, 'form_version_id' => $form->published_version_id, 'status' => 'draft']);
        $bot = TelegramBotFactory::new()->create(['enabled' => true]);
        TelegramRuleFactory::new()->create(['telegram_bot_id' => $bot->id, 'trigger' => 'missing_form', 'enabled' => true, 'minutes' => 60]);
        $this->mock(TelegramAutomationService::class, fn ($mock) => $mock->shouldNotReceive('emit'));

        app(TelegramReadService::class)->tick();

        $metadata = app(FormCompletionReadModel::class)->forStudents([$student->id])->get($student->id)->sole();
        $this->assertSame('draft', $metadata['status']);
        $this->assertTrue($metadata['has_submitted']);
    }

    public function test_failed_file_cleanup_after_commit_is_reported_without_undoing_resource_deletion(): void
    {
        Storage::fake('local');
        $originalDisk = Storage::disk('local');
        $resource = ResourceFactory::new()->create(['file_path' => 'resources/cleanup-failure.pdf']);
        $originalDisk->put($resource->file_path, 'Preserved for later cleanup');
        $unavailableDisk = \Mockery::mock(FilesystemAdapter::class);
        $unavailableDisk->shouldReceive('delete')->once()->with($resource->file_path)->andReturnFalse();
        Storage::shouldReceive('disk')->with('local')->andReturn($unavailableDisk);
        Log::spy();

        $this->actingAs(Administrator::factory()->create(['role' => 'super_admin']))
            ->delete(route('admin.resources.destroy', $resource))->assertRedirect();

        $this->assertSoftDeleted($resource);
        $this->assertDatabaseHas('audit_logs', ['action' => 'resource_deleted', 'entity_id' => $resource->id]);
        $originalDisk->assertExists($resource->file_path);
        Log::shouldHaveReceived('error')->with('Backend operation failed.', [
            'operation' => 'retire_deleted_resource_file', 'exception_type' => RuntimeException::class,
        ])->once();
    }

    /** @return array<string, int|string> */
    private function purchasePayload(): array
    {
        return ['preset_key' => 'foundation_track', 'package_name' => 'Foundation Coaching Track', 'total_sessions_allocated' => 8,
            'original_price' => '280.00', 'discount_amount' => '0.00', 'currency' => 'USD', 'package_idempotency_key' => (string) Str::uuid()];
    }

    private function failAudit(string $action): void
    {
        AuditLog::creating(function (AuditLog $log) use ($action): void {
            if ($log->action === $action) {
                throw new RuntimeException('Synthetic audit storage failure');
            }
        });
    }
}
