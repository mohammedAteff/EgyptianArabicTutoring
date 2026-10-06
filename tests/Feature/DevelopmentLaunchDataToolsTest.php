<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\LessonMaterial;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Reporting\Services\ReportService;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\System\Services\BackupService;
use App\Domains\System\Services\DevelopmentDataGraph;
use App\Domains\System\Services\DevelopmentDataReconciler;
use App\Domains\System\Services\DevelopmentDataResetService;
use App\Domains\System\Services\DevelopmentDataSnapshotService;
use App\Domains\System\Services\DevelopmentToolsService;
use Database\Factories\AdministratorFactory;
use Database\Factories\ResourceFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class DevelopmentLaunchDataToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['development_tools.enabled' => true, 'development_tools.require_snapshot' => false, 'session.driver' => 'database']);
    }

    public function test_disabled_tools_are_hidden_and_backend_denied(): void
    {
        $actor = $this->actor();
        config(['development_tools.enabled' => false]);

        $this->actingAs($actor, 'web')->get(route('admin.dashboard'))->assertDontSee('Development &amp; Launch Tools', false);
        $this->get(route('admin.development-tools.index'))->assertNotFound();
        $this->post(route('admin.development-tools.preview'), ['type' => 'reset', 'domain' => 'analytics'])->assertNotFound();
        $this->assertDatabaseCount('development_data_operations', 0);
    }

    #[TestWith(['admin'])]
    #[TestWith(['assistant'])]
    public function test_other_staff_roles_are_denied(string $role): void
    {
        $actor = AdministratorFactory::new()->create(['role' => $role]);

        $this->actingAs($actor, 'web')->get(route('admin.development-tools.index'))->assertForbidden();
        $this->post(route('admin.development-tools.preview'), ['type' => 'reset', 'domain' => 'analytics'])->assertForbidden();
        $this->assertDatabaseCount('development_data_operations', 0);
    }

    public function test_student_and_guest_cannot_enter_tools(): void
    {
        $this->get(route('admin.development-tools.index'))->assertRedirect(route('admin.login'));
        $student = Student::factory()->verified()->create();

        $this->actingAs($student, 'student')->post(route('admin.development-tools.preview'), ['type' => 'reset', 'domain' => 'students'])
            ->assertRedirect(route('admin.login'));
        $this->assertModelExists($student);
    }

    public function test_enabled_super_admin_gets_preview_without_deletion(): void
    {
        $actor = $this->actor();
        $student = Student::factory()->verified()->create();

        $response = $this->actingAs($actor, 'web')->post(route('admin.development-tools.preview'), ['type' => 'reset', 'domain' => 'students']);

        $response->assertOk()->assertSee('DELETE ALL STUDENTS')->assertSee('Current password')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertModelExists($student);
        $this->assertDatabaseCount('development_data_operations', 1);
        $this->assertDatabaseHas('development_data_operations', ['status' => 'pending']);
    }

    #[TestWith(['Wrong phrase', 'Stage5QaPass2026!', 'phrase'])]
    #[TestWith(['RESET ALL ANALYTICS', 'wrong-password', 'password'])]
    public function test_wrong_confirmation_never_mutates_facts(string $phrase, string $password, string $field): void
    {
        $actor = $this->actor();
        $this->fact();
        $tools = app(DevelopmentToolsService::class);
        $preview = $tools->preview($actor, 'session-a', 'reset', 'analytics', []);

        try {
            $tools->confirm($actor, 'session-a', $preview['token'], $password, null, null, $phrase);
            $this->fail('Wrong confirmation must be refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertDatabaseCount('analytics_events', 1);
        $this->assertSame('pending', $preview['operation']->fresh()->status);
    }

    public function test_missing_password_is_rejected_by_endpoint(): void
    {
        $actor = $this->actor();

        $this->actingAs($actor, 'web')->post(route('admin.development-tools.confirm'),
            ['operation_token' => str_repeat('a', 64), 'phrase' => 'DELETE ALL STUDENTS'])->assertSessionHasErrors('password');
        $this->assertDatabaseCount('development_data_operations', 0);
    }

    public function test_expired_or_foreign_session_token_does_not_delete(): void
    {
        $actor = $this->actor();
        $this->fact();
        $tools = app(DevelopmentToolsService::class);
        $preview = $tools->preview($actor, 'session-a', 'reset', 'analytics', []);
        $preview['operation']->update(['expires_at' => now('UTC')->subSecond()]);

        foreach (['session-a', 'session-b'] as $session) {
            try {
                $tools->confirm($actor, $session, $preview['token'], 'Stage5QaPass2026!', null, null, 'RESET ALL ANALYTICS');
                $this->fail('An expired or foreign token must be refused.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('operation_token', $exception->errors());
            }
        }
        $this->assertDatabaseCount('analytics_events', 1);
    }

    public function test_changed_preview_and_unknown_scope_are_refused(): void
    {
        $actor = $this->actor();
        $tools = app(DevelopmentToolsService::class);
        $preview = $tools->preview($actor, 'session-a', 'reset', 'analytics', []);
        $this->fact();

        try {
            $tools->confirm($actor, 'session-a', $preview['token'], 'Stage5QaPass2026!', null, null, 'RESET ALL ANALYTICS');
            $this->fail('Changed facts require a fresh preview.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('operation_token', $exception->errors());
        }
        $this->actingAs($actor, 'web')->post(route('admin.development-tools.preview'), [
            'type' => 'reset', 'domain' => 'resources', 'scope' => ['resources' => true, 'unknown' => true],
        ])->assertSessionHasErrors('scope');
        $this->assertDatabaseCount('analytics_events', 1);
    }

    public function test_analytics_reset_preserves_configuration_and_idempotent_replay(): void
    {
        $actor = $this->actor();
        $this->fact();
        Setting::set('analytics.excluded_ips', ['127.0.0.1'], 'analytics');
        $tools = app(DevelopmentToolsService::class);
        $preview = $tools->preview($actor, 'session-a', 'reset', 'analytics', []);

        $first = $tools->confirm($actor, 'session-a', $preview['token'], 'Stage5QaPass2026!', null, null, 'RESET ALL ANALYTICS');
        $second = $tools->confirm($actor, 'session-a', $preview['token'], '', null, null, '');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('analytics_events', 0);
        $this->assertSame(['127.0.0.1'], Setting::get('analytics.excluded_ips'));
        $this->assertDatabaseCount('administrators', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'development_data_reset_completed']);
    }

    public function test_financial_reset_removes_complete_typed_cycle_and_keeps_students_and_free_lesson(): void
    {
        $actor = $this->actor();
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'Synthetic purchase', 2, '80.00', '0.00', 'USD', null, 'financial-reset-package', $actor->id, null, null, 'one_hour');
        $type = SessionType::factory()->create(['funding_mode' => 'package', 'required_entitlement_type_id' => EntitlementType::query()->where('code', 'one_hour')->value('id'), 'required_entitlement_units' => 1]);
        $booking = Booking::factory()->create(['student_id' => $student->id, 'session_type_id' => $type->id, 'funding_mode' => 'legacy']);
        DB::transaction(fn () => $ledger->consumeForBooking($booking, 'reset-booking-debit'));
        $ledger->recordPayment($package, '40.00', 'reset-payment', $actor->id);
        $free = Booking::factory()->create(['student_id' => $student->id, 'funding_mode' => 'free']);
        $tools = app(DevelopmentToolsService::class);
        $preview = $tools->preview($actor, 'session-a', 'reset', 'financial', []);

        $summary = $tools->confirm($actor, 'session-a', $preview['token'], 'Stage5QaPass2026!', null, null, 'RESET FINANCIAL TEST HISTORY');

        $this->assertModelExists($student);
        $this->assertModelExists($free);
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
        foreach (['student_packages', 'student_package_entitlements', 'session_ledger_entries', 'payment_records', 'payment_refunds'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame(0, $summary['reconciliation']['orphan_count']);
        $this->assertSame('UNINITIALIZED_DATASET', $summary['reconciliation']['financial_status']);
    }

    public function test_student_reset_removes_owned_files_but_preserves_lead_resource_and_staff_session(): void
    {
        $actor = $this->actor();
        Storage::fake('local');
        Storage::fake('managed_backups');
        $student = Student::factory()->verified()->create();
        $booking = Booking::factory()->create(['student_id' => $student->id]);
        $lead = Contact::query()->create(['name' => 'Independent synthetic lead', 'email' => 'independent@example.test']);
        $resource = ResourceFactory::new()->create();
        Storage::disk('local')->put('lesson-materials/test-student.pdf', 'synthetic PDF bytes');
        LessonMaterial::factory()->create(['booking_id' => $booking->id, 'kind' => 'private_file', 'disk' => 'local', 'path' => 'lesson-materials/test-student.pdf', 'url' => null]);
        DB::table('sessions')->insert(['id' => 'shared-session', 'user_id' => $actor->id, 'last_activity' => now()->timestamp,
            'payload' => base64_encode(json_encode(['student_id' => $student->id, 'student_auth_expires_at' => now()->addHour()->toIso8601String(), 'login_web' => $actor->id]))]);
        $tools = app(DevelopmentToolsService::class);
        $preview = $tools->preview($actor, 'session-a', 'reset', 'students', []);

        $summary = $tools->confirm($actor, 'session-a', $preview['token'], 'Stage5QaPass2026!', null, null, 'DELETE ALL STUDENTS');

        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('lesson_materials', 0);
        Storage::disk('local')->assertMissing('lesson-materials/test-student.pdf');
        $this->assertModelExists($lead);
        $this->assertModelExists($resource);
        $session = json_decode(base64_decode(DB::table('sessions')->where('id', 'shared-session')->value('payload')), true);
        $this->assertSame($actor->id, $session['login_web']);
        $this->assertArrayNotHasKey('student_id', $session);
        $this->assertSame(0, $summary['reconciliation']['orphan_count']);
    }

    private function actor(): Administrator
    {
        return AdministratorFactory::new()->create(['role' => 'super_admin', 'password' => Hash::make('Stage5QaPass2026!')]);
    }

    public function test_reset_services_refuse_unconfirmed_direct_invocation(): void
    {
        $actor = $this->actor();
        $student = Student::factory()->verified()->create();
        $plan = app(DevelopmentDataGraph::class)->reset('students');

        try {
            DB::transaction(fn () => app(DevelopmentDataResetService::class)->run($actor, 'students', $plan, (string) Str::uuid()));
            $this->fail('Direct unconfirmed deletion must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('operation', $exception->errors());
        }
        $this->assertModelExists($student);
    }

    public function test_no_get_endpoint_can_reset_or_import(): void
    {
        $actor = $this->actor();
        $student = Student::factory()->verified()->create();

        $this->actingAs($actor, 'web')->get('/admin/development-tools/confirm')->assertStatus(405);
        $this->get('/admin/development-tools/preview')->assertStatus(405);
        $this->get('/admin/development-tools/import')->assertStatus(405);
        $this->assertModelExists($student);
        $this->assertDatabaseCount('development_data_operations', 0);
    }

    public function test_mfa_missing_factor_is_refused_and_fresh_code_then_succeeds_once(): void
    {
        $this->freezeTime();
        $actor = $this->actor();
        $secret = 'JBSWY3DPEHPK3PXP';
        $actor->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now('UTC'),
            'two_factor_version' => (string) Str::uuid(), 'two_factor_last_used_step' => null])->save();
        $this->fact();
        $tools = app(DevelopmentToolsService::class);
        $preview = $tools->preview($actor, 'session-a', 'reset', 'analytics', []);

        try {
            $tools->confirm($actor, 'session-a', $preview['token'], 'Stage5QaPass2026!', null, null, 'RESET ALL ANALYTICS');
            $this->fail('Enabled MFA must require a current factor.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('code', $exception->errors());
        }
        $this->assertDatabaseCount('analytics_events', 1);
        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $tools->confirm($actor, 'session-a', $preview['token'], 'Stage5QaPass2026!', $code, null, 'RESET ALL ANALYTICS');
        $this->assertDatabaseCount('analytics_events', 0);
        $second = $tools->preview($actor->fresh(), 'session-a', 'reset', 'analytics', []);
        try {
            $tools->confirm($actor->fresh(), 'session-a', $second['token'], 'Stage5QaPass2026!', $code, null, 'RESET ALL ANALYTICS');
            $this->fail('A used authenticator code must not authorize another operation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('code', $exception->errors());
        }
    }

    public function test_unused_recovery_factor_is_consumed_without_export_or_audit_contents(): void
    {
        $actor = $this->actor();
        $recovery = 'synthetic-unused-recovery';
        $actor->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now('UTC'),
            'two_factor_version' => (string) Str::uuid(), 'two_factor_recovery_codes' => [Hash::make($recovery)]])->save();
        $tools = app(DevelopmentToolsService::class);
        $preview = $tools->preview($actor, 'session-a', 'reset', 'analytics', []);

        $tools->confirm($actor, 'session-a', $preview['token'], 'Stage5QaPass2026!', null, $recovery, 'RESET ALL ANALYTICS');

        $this->assertSame([], $actor->fresh()->two_factor_recovery_codes);
        $this->assertStringNotContainsString($recovery, json_encode(DB::table('audit_logs')->get()));
        $this->assertStringNotContainsString('Stage5QaPass2026!', json_encode(DB::table('audit_logs')->get()));
    }

    public function test_required_snapshot_failure_refuses_reset_even_when_option_unchecked(): void
    {
        $actor = $this->actor();
        $student = Student::factory()->verified()->create();
        config(['development_tools.require_snapshot' => true]);
        $this->mock(DevelopmentDataSnapshotService::class)->shouldReceive('create')->once()
            ->andThrow(ValidationException::withMessages(['snapshot' => 'Protected snapshot unavailable.']));
        $tools = app(DevelopmentToolsService::class);
        $preview = $tools->preview($actor, 'session-a', 'reset', 'students', ['snapshot' => false]);

        try {
            $tools->confirm($actor, 'session-a', $preview['token'], 'Stage5QaPass2026!', null, null, 'DELETE ALL STUDENTS');
            $this->fail('A mandatory snapshot cannot be bypassed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('snapshot', $exception->errors());
        }
        $this->assertModelExists($student);
        $this->assertSame('pending', $preview['operation']->fresh()->status);
    }

    public function test_protected_snapshot_is_created_privately_outside_managed_reset_set(): void
    {
        $actor = $this->actor();
        Storage::fake('local');
        Storage::fake('managed_backups');
        config(['development_tools.require_snapshot' => true]);
        $this->fact();
        $tools = app(DevelopmentToolsService::class);
        $preview = $tools->preview($actor, 'session-a', 'reset', 'analytics', []);

        $summary = $tools->confirm($actor, 'session-a', $preview['token'], 'Stage5QaPass2026!', null, null, 'RESET ALL ANALYTICS');

        $id = $summary['protected_recovery']['identity'];
        Storage::disk('local')->assertExists('development-data/recovery/'.$id.'/database.sql');
        Storage::disk('local')->assertExists('development-data/recovery/'.$id.'/manifest.json');
        $manifest = json_decode(Storage::disk('local')->get('development-data/recovery/'.$id.'/manifest.json'), true);
        $schema = Storage::disk('local')->get('development-data/recovery/'.$id.'/schema.json');
        $this->assertSame($manifest['schema_sha256'], hash('sha256', $schema));
        $this->assertGreaterThanOrEqual(2, $manifest['trigger_count']);
        Storage::disk('managed_backups')->assertDirectoryEmpty('/');
        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function test_resource_reset_withdraws_shared_lesson_reference_preserves_categories_and_removes_private_payload(): void
    {
        $actor = $this->actor();
        Storage::fake('local');
        Storage::fake('managed_backups');
        $student = Student::factory()->verified()->create();
        $booking = Booking::factory()->create(['student_id' => $student->id]);
        Storage::disk('local')->put('resources/owned.pdf', "%PDF-1.4\nSynthetic resource");
        $resource = ResourceFactory::new()->create(['file_path' => 'resources/owned.pdf', 'file_type' => 'pdf', 'external_url' => null]);
        $material = LessonMaterial::factory()->create(['booking_id' => $booking->id, 'kind' => 'resource', 'resource_id' => $resource->id, 'url' => null, 'student_visible' => true]);
        $tools = app(DevelopmentToolsService::class);
        $preview = $tools->preview($actor, 'session-a', 'reset', 'resources', ['resources' => true, 'history' => true, 'files' => true]);

        $summary = $tools->confirm($actor, 'session-a', $preview['token'], 'Stage5QaPass2026!', null, null, 'RESET RESOURCE TEST DATA');

        $this->assertDatabaseCount('resources', 0);
        $this->assertDatabaseCount('resource_categories', 1);
        $this->assertModelExists($material);
        $this->assertNotNull($material->fresh()->withdrawn_at);
        $this->assertNull($material->fresh()->resource_id);
        $this->assertFalse($material->fresh()->student_visible);
        Storage::disk('local')->assertMissing('resources/owned.pdf');
        $this->assertSame(0, $summary['reconciliation']['orphan_count']);
    }

    public function test_categories_cannot_reset_while_referencing_resources_are_preserved(): void
    {
        $actor = $this->actor();
        $resource = ResourceFactory::new()->create();

        try {
            app(DevelopmentToolsService::class)->preview($actor, 'session-a', 'reset', 'resources', ['categories' => true]);
            $this->fail('Referenced categories cannot disappear.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('scope', $exception->errors());
        }
        $this->assertModelExists($resource);
        $this->assertDatabaseCount('resource_categories', 1);
    }

    public function test_backup_reset_removes_only_managed_and_protects_external_and_predeployment_archives(): void
    {
        $actor = $this->actor();
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('managed_backups');
        Http::preventStrayRequests();
        $backups = app(BackupService::class);
        $native = basename($backups->createBackup());
        $protected = basename($backups->createBackup('pre-deployment'));
        Storage::disk('managed_backups')->put('emergency-manual.zip', 'external archive preserved');
        Setting::set('backup_retention_days', 37);
        $tools = app(DevelopmentToolsService::class);
        $preview = $tools->preview($actor, 'session-a', 'reset', 'backups', ['snapshot' => true]);

        $summary = $tools->confirm($actor, 'session-a', $preview['token'], 'Stage5QaPass2026!', null, null, 'RESET MANAGED BACKUPS');

        Storage::disk('managed_backups')->assertMissing($native);
        Storage::disk('managed_backups')->assertExists($protected);
        Storage::disk('managed_backups')->assertExists('emergency-manual.zip');
        $this->assertSame([], $backups->getBackups());
        $this->assertNull(Setting::get('last_backup_at'));
        $this->assertSame(37, Setting::get('backup_retention_days'));
        $this->assertSame(1, $summary['deleted_managed_snapshots']);
        $next = basename($backups->createBackup());
        $this->assertCount(1, $backups->getBackups());
        $this->assertSame($next, $backups->getBackups()[0]['filename']);
        Http::assertNothingSent();
    }

    public function test_analytics_reset_does_not_recollect_retained_bookings_or_leave_click_dedup_stale(): void
    {
        $actor = $this->actor();
        $booking = Booking::factory()->create(['created_at' => now('UTC')->subDay()]);
        $tools = app(DevelopmentToolsService::class);
        $preview = $tools->preview($actor, 'session-a', 'reset', 'analytics', []);
        $tools->confirm($actor, 'session-a', $preview['token'], 'Stage5QaPass2026!', null, null, 'RESET ALL ANALYTICS');

        $countries = app(AnalyticsService::class)->reportingCountries(now('UTC')->subDays(2), now('UTC')->addDay());

        $this->assertSame(0, (int) $countries->sum('bookings_completed'));
        $this->assertModelExists($booking);
        $this->assertSame(1, app(ReportService::class)->nonBotBookingQuery()->count());
        $this->assertTrue(Str::isUuid(Setting::get('analytics_collection_generation')));
    }

    public function test_files_and_database_are_compensated_when_reconciliation_fails(): void
    {
        $actor = $this->actor();
        Storage::fake('local');
        Storage::fake('managed_backups');
        $student = Student::factory()->verified()->create();
        $booking = Booking::factory()->create(['student_id' => $student->id]);
        Storage::disk('local')->put('lesson-materials/rollback.pdf', 'Synthetic compensation bytes');
        $material = LessonMaterial::factory()->create(['booking_id' => $booking->id, 'kind' => 'private_file', 'disk' => 'local', 'path' => 'lesson-materials/rollback.pdf', 'url' => null]);
        $this->mock(DevelopmentDataReconciler::class)->shouldReceive('verify')->once()->andThrow(ValidationException::withMessages(['reconciliation' => 'Synthetic verification failure']));
        $tools = app(DevelopmentToolsService::class);
        $preview = $tools->preview($actor, 'session-a', 'reset', 'students', []);
        try {
            $tools->confirm($actor, 'session-a', $preview['token'], 'Stage5QaPass2026!', null, null, 'DELETE ALL STUDENTS');
            $this->fail('Reconciliation failure must roll back.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reconciliation', $exception->errors());
        }
        $this->assertModelExists($student);
        $this->assertModelExists($booking);
        $this->assertModelExists($material);
        $this->assertSame('Synthetic compensation bytes', Storage::disk('local')->get('lesson-materials/rollback.pdf'));
        $this->assertSame('pending', $preview['operation']->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'development_data_reset_completed']);
    }

    public function test_confirmation_rejects_missing_csrf_token_in_web_environment(): void
    {
        $actor = $this->actor();
        $this->app['env'] = 'local';
        $this->actingAs($actor, 'web')->post('/admin/development-tools/confirm', ['operation_token' => str_repeat('a', 64),
            'password' => 'Stage5QaPass2026!', 'phrase' => 'RESET ALL ANALYTICS'])->assertStatus(419);
        $this->assertDatabaseCount('development_data_operations', 0);
    }

    private function fact(): void
    {
        DB::table('analytics_events')->insert(['event_uuid' => fake()->uuid(), 'event_name' => 'page_view', 'page' => '/', 'metadata' => '{}', 'is_bot' => false, 'created_at' => now()]);
    }
}
