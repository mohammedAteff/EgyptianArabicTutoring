<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\LessonMaterial;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\System\Models\DevelopmentDataOperation;
use App\Domains\System\Services\BackupService;
use App\Domains\System\Services\DevelopmentDataArchiveService;
use App\Domains\System\Services\DevelopmentDataCatalog;
use App\Domains\System\Services\DevelopmentDataFiles;
use App\Domains\System\Services\DevelopmentDataGraph;
use App\Domains\System\Services\DevelopmentDataReconciler;
use App\Domains\System\Services\DevelopmentToolsService;
use App\Domains\System\Services\ManagedBackupCatalog;
use App\Domains\System\Services\ManagedBackupPortableService;
use Database\Factories\AdministratorFactory;
use Database\Factories\ResourceFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Exception\LogicException;
use Tests\TestCase;
use ZipArchive;

class DevelopmentDataArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['development_tools.enabled' => true, 'development_tools.require_snapshot' => false]);
    }

    public function test_full_export_manifest_hashes_and_credentials_exclusion(): void
    {
        $actor = $this->actor();
        $student = Student::factory()->verified()->create();
        $booking = Booking::factory()->create(['student_id' => $student->id, 'confirmation_token' => 'never-export-this-booking-token']);
        Setting::set('telegram_secret_fixture', 'never-export-this-setting');
        $archive = $this->export($actor, array_keys(DevelopmentDataCatalog::MODULES));

        $read = app(DevelopmentDataArchiveService::class)->read($actor, $archive['path']);

        $this->assertSame(1, $read['manifest']['format_version']);
        $this->assertSame(1, $read['manifest']['compatibility_version']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $read['manifest']['application_sha']);
        $this->assertArrayHasKey('business_timezone', $read['manifest']);
        $this->assertSame(1, $read['manifest']['row_counts']['students']);
        $this->assertSame(1, $read['manifest']['row_counts']['bookings']);
        $this->assertArrayNotHasKey('administrators', $read['rows']);
        $this->assertArrayNotHasKey('settings', $read['rows']);
        $this->assertArrayNotHasKey('confirmation_token', $read['rows']['bookings'][0]);
        $contents = implode('', $read['payloads']).json_encode($read['manifest']);
        $this->assertStringNotContainsString($actor->password, $contents);
        $this->assertStringNotContainsString('never-export-this-setting', $contents);
        $this->assertStringNotContainsString($booking->confirmation_token, $contents);
        $this->assertStringNotContainsString((string) config('app.key'), $contents);
    }

    public function test_student_filtered_export_only_includes_matching_profiles(): void
    {
        $actor = $this->actor();
        $selected = Student::factory()->verified()->create();
        $other = Student::factory()->verified()->create();
        $archive = $this->export($actor, ['students.profile'], ['student_id' => $selected->id]);

        $read = app(DevelopmentDataArchiveService::class)->read($actor, $archive['path']);

        $this->assertSame([$selected->id], array_column($read['rows']['students'], 'id'));
        $this->assertContains('financial.payments', $read['manifest']['excluded_modules']);
        $this->assertModelExists($other);
    }

    public function test_export_remains_readable_when_optional_git_metadata_cannot_be_collected(): void
    {
        $actor = $this->actor();
        $student = Student::factory()->verified()->create();
        if (function_exists('proc_open')) {
            Process::shouldReceive('path')->once()->with(base_path())->andReturnSelf();
            Process::shouldReceive('timeout')->once()->with(3)->andReturnSelf();
            Process::shouldReceive('run')->once()->with(['git', 'rev-parse', 'HEAD'])
                ->andThrow(new LogicException('Process execution is unavailable.'));
        }

        $archive = $this->export($actor, ['students.profile'], ['student_id' => $student->id]);
        $read = app(DevelopmentDataArchiveService::class)->read($actor, $archive['path']);

        $this->assertNull($read['manifest']['application_sha']);
        $this->assertSame([$student->id], array_column($read['rows']['students'], 'id'));
        $this->assertSame($archive['sha256'], $read['sha256']);
        $this->assertModelExists($student);
    }

    public function test_import_upload_is_inspection_only_and_requires_selected_preview(): void
    {
        $actor = $this->actor();
        Student::factory()->verified()->create();
        $archive = $this->export($actor, ['students.profile']);
        $tools = app(DevelopmentToolsService::class);
        $inspection = $tools->upload($actor, 'archive-session', $this->upload($archive['path']));

        $this->assertSame(1, Student::query()->count());
        $this->assertSame(1, $inspection['preview']['counts']['students']['skip_identical']);
        try {
            $tools->confirm($actor, 'archive-session', $inspection['token'], 'Stage5ArchivePass!', null, null, 'RESTORE MISSING DEVELOPMENT DATA');
            $this->fail('An inspection cannot bypass selection confirmation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('modules', $exception->errors());
        }
        $this->assertSame('pending', $inspection['operation']->fresh()->status);
    }

    public function test_restore_missing_is_selective_and_repeated_archive_skips_identical_rows(): void
    {
        $actor = $this->actor();
        $student = Student::factory()->verified()->create();
        $archive = $this->export($actor, ['students.profile', 'students.notifications']);
        DB::table('students')->where('id', $student->id)->delete();
        $tools = app(DevelopmentToolsService::class);
        $inspection = $tools->upload($actor, 'archive-session', $this->upload($archive['path']));
        $selection = $tools->selectImport($actor, 'archive-session', $inspection['token'], ['students.profile']);

        $first = $tools->confirm($actor, 'archive-session', $selection['token'], 'Stage5ArchivePass!', null, null, 'RESTORE MISSING DEVELOPMENT DATA');
        $again = $tools->upload($actor, 'archive-session', $this->upload($archive['path']));
        $againSelected = $tools->selectImport($actor, 'archive-session', $again['token'], ['students.profile']);
        $second = $tools->confirm($actor, 'archive-session', $againSelected['token'], 'Stage5ArchivePass!', null, null, 'RESTORE MISSING DEVELOPMENT DATA');

        $this->assertSame(1, $first['restored_records']['students']);
        $this->assertSame([], $second['restored_records']);
        $this->assertSame(1, $second['skipped_identical']);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'email_normalized' => $student->email_normalized]);
        $this->assertDatabaseCount('students', 1);
    }

    public function test_conflicting_profile_is_reported_and_never_overwritten(): void
    {
        $actor = $this->actor();
        $student = Student::factory()->verified()->create();
        $archive = $this->export($actor, ['students.profile']);
        $student->update(['first_name' => 'Changed after export']);
        $tools = app(DevelopmentToolsService::class);
        $inspection = $tools->upload($actor, 'archive-session', $this->upload($archive['path']));
        $selection = $tools->selectImport($actor, 'archive-session', $inspection['token'], ['students.profile']);

        $this->assertNotEmpty($selection['preview']['conflicts']);
        try {
            $tools->confirm($actor, 'archive-session', $selection['token'], 'Stage5ArchivePass!', null, null, 'RESTORE MISSING DEVELOPMENT DATA');
            $this->fail('Conflicts must not overwrite current records.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('modules', $exception->errors());
        }
        $this->assertSame('Changed after export', $student->fresh()->first_name);
    }

    public function test_corrupt_payload_and_incompatible_manifest_are_rejected(): void
    {
        $actor = $this->actor();
        Student::factory()->verified()->create();
        foreach (['payload', 'version'] as $kind) {
            $archive = $this->export($actor, ['students.profile']);
            $path = app(DevelopmentDataFiles::class)->disk()->path($archive['path']);
            $zip = new ZipArchive;
            $zip->open($path);
            if ($kind === 'payload') {
                $zip->addFromString('data/students.json', '[]');
            } else {
                $manifest = json_decode($zip->getFromName('manifest.json'), true);
                $manifest['compatibility_version'] = 999;
                $zip->addFromString('manifest.json', json_encode($manifest));
            }
            $zip->close();

            try {
                app(DevelopmentDataArchiveService::class)->read($actor, $archive['path']);
                $this->fail('Corrupted or incompatible archives must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('archive', $exception->errors());
            }
        }
        $this->assertDatabaseCount('students', 1);
    }

    public function test_traversal_unmanifested_entries_and_symlinks_are_rejected(): void
    {
        $actor = $this->actor();
        foreach (['../outside.php', 'files/unrecognized.php', 'files/'.str_repeat('a', 64).'.bin'] as $entry) {
            $archive = $this->export($actor, ['students.profile']);
            $zip = new ZipArchive;
            $zip->open(app(DevelopmentDataFiles::class)->disk()->path($archive['path']));
            $zip->addFromString($entry, 'no executable payload');
            if (str_ends_with($entry, '.bin')) {
                $zip->setExternalAttributesName($entry, ZipArchive::OPSYS_UNIX, 0120777 << 16);
            }
            $zip->close();

            try {
                app(DevelopmentDataArchiveService::class)->read($actor, $archive['path']);
                $this->fail('Unsafe ZIP entries must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('archive', $exception->errors());
            }
        }
        $this->assertDatabaseCount('students', 0);
    }

    public function test_private_lesson_file_restores_to_generated_private_path_with_identical_hash(): void
    {
        $actor = $this->actor();
        $student = Student::factory()->verified()->create();
        $booking = Booking::factory()->create(['student_id' => $student->id]);
        $bytes = "%PDF-1.4\nSynthetic safe lesson payload\n";
        Storage::disk('local')->put('lesson-materials/original.pdf', $bytes);
        $material = LessonMaterial::factory()->create(['booking_id' => $booking->id, 'kind' => 'private_file',
            'disk' => 'local', 'path' => 'lesson-materials/original.pdf', 'url' => null]);
        $archive = $this->export($actor, ['students.materials']);
        DB::table('lesson_materials')->where('id', $material->id)->delete();
        Storage::disk('local')->delete('lesson-materials/original.pdf');
        $tools = app(DevelopmentToolsService::class);
        $inspection = $tools->upload($actor, 'archive-session', $this->upload($archive['path']));
        $selection = $tools->selectImport($actor, 'archive-session', $inspection['token'], ['students.materials']);

        $summary = $tools->confirm($actor, 'archive-session', $selection['token'], 'Stage5ArchivePass!', null, null, 'RESTORE MISSING DEVELOPMENT DATA');

        $restored = LessonMaterial::query()->findOrFail($material->id);
        $this->assertNotSame('lesson-materials/original.pdf', $restored->path);
        $this->assertSame('local', $restored->disk);
        $this->assertSame($bytes, Storage::disk('local')->get($restored->path));
        $this->assertSame(1, $summary['restored_files']);
        Storage::disk('public')->assertMissing($restored->path);
    }

    public function test_unique_email_collision_with_different_primary_key_is_reported_before_restore(): void
    {
        $actor = $this->actor();
        $student = Student::factory()->verified()->create();
        $archive = $this->export($actor, ['students.profile']);
        $email = $student->email;
        $student->delete();
        Student::factory()->verified()->create(['email' => $email, 'email_normalized' => $email]);
        $tools = app(DevelopmentToolsService::class);
        $inspection = $tools->upload($actor, 'archive-session', $this->upload($archive['path']));
        $selection = $tools->selectImport($actor, 'archive-session', $inspection['token'], ['students.profile']);
        $this->assertNotEmpty($selection['preview']['conflicts']);
        $this->expectException(ValidationException::class);
        $tools->confirm($actor, 'archive-session', $selection['token'], 'Stage5ArchivePass!', null, null, 'RESTORE MISSING DEVELOPMENT DATA');
    }

    public function test_financial_round_trip_preserves_stable_grant_debit_payment_and_refund_identities(): void
    {
        $actor = $this->actor();
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'Portable purchase', 2, '80.00', '0.00', 'USD', null, 'portable-purchase', $actor->id, null, null, 'one_hour');
        $type = SessionType::factory()->create(['funding_mode' => 'package', 'required_entitlement_type_id' => EntitlementType::query()->where('code', 'one_hour')->value('id'), 'required_entitlement_units' => 1]);
        $booking = Booking::factory()->create(['student_id' => $student->id, 'session_type_id' => $type->id, 'funding_mode' => 'legacy']);
        DB::transaction(fn () => $ledger->consumeForBooking($booking, 'portable-debit'));
        $payment = $ledger->recordPayment($package, '80.00', 'portable-payment', $actor->id);
        $ledger->refund($payment, '10.00', 'portable-refund', $actor->id, 'Synthetic refund');
        $modules = ['students.bookings', 'financial.packages', 'financial.payments', 'financial.ledger'];
        $archive = $this->export($actor, $modules);
        $before = [];
        foreach (['student_packages', 'student_package_entitlements', 'session_ledger_entries', 'payment_records', 'payment_refunds'] as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $tools = app(DevelopmentToolsService::class);
        $reset = $tools->preview($actor, 'archive-session', 'reset', 'financial', []);
        $tools->confirm($actor, 'archive-session', $reset['token'], 'Stage5ArchivePass!', null, null, 'RESET FINANCIAL TEST HISTORY');
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $inspection = $tools->upload($actor, 'archive-session', $this->upload($archive['path']));
            $selected = $tools->selectImport($actor, 'archive-session', $inspection['token'], $modules);
            $this->assertSame([], $selected['preview']['conflicts']);
            $summary = $tools->confirm($actor, 'archive-session', $selected['token'], 'Stage5ArchivePass!', null, null, 'RESTORE MISSING DEVELOPMENT DATA');
            $this->assertSame(0, $summary['reconciliation']['orphan_count']);
            foreach ($before as $table => $rows) {
                $this->assertSame($rows, DB::table($table)->orderBy('id')->get()->toJson(), $table);
            }
        }
        $this->assertSame('package', $booking->fresh()->funding_mode);
        $this->assertSame('one_hour', $booking->fresh()->entitlement_code);
        $ledger->recordPayment(StudentPackage::query()->findOrFail($package->id), '5.00', 'next-real-payment', $actor->id);
        $this->assertDatabaseCount('payment_records', 2);
        $this->assertSame(0, app(DevelopmentDataReconciler::class)->verify(true)['orphan_count']);
    }

    public function test_payment_filter_follows_package_to_student_and_currency(): void
    {
        $actor = $this->actor();
        $ledger = app(StudentLedgerService::class);
        $selected = Student::factory()->verified()->create();
        $other = Student::factory()->verified()->create();
        foreach ([[$selected, 'USD'], [$selected, 'EUR'], [$other, 'USD']] as $index => [$student, $currency]) {
            $package = $ledger->createPackage($student, 'Filtered purchase', 1, '20.00', '0.00', $currency, null, 'filter-'.$index, $actor->id, null, null, 'one_hour');
            $ledger->recordPayment($package, '20.00', 'filter-pay-'.$index, $actor->id);
        }
        $read = app(DevelopmentDataArchiveService::class)->read($actor, $this->export($actor, ['financial.payments'], ['student_id' => $selected->id, 'currency' => 'USD'])['path']);
        $this->assertCount(1, $read['rows']['payment_records']);
        $this->assertSame('USD', $read['rows']['payment_records'][0]['currency']);
        $this->assertSame([$selected->id], array_column($read['rows']['students'], 'id'));
    }

    public function test_selective_restore_refuses_missing_parent_module(): void
    {
        $actor = $this->actor();
        $student = Student::factory()->verified()->create();
        Booking::factory()->create(['student_id' => $student->id]);
        $archive = $this->export($actor, ['students.bookings']);
        DB::table('bookings')->delete();
        DB::table('students')->delete();
        $tools = app(DevelopmentToolsService::class);
        $inspection = $tools->upload($actor, 'archive-session', $this->upload($archive['path']));
        $selected = $tools->selectImport($actor, 'archive-session', $inspection['token'], ['students.bookings']);
        $this->assertNotEmpty($selected['preview']['missing_dependencies']);
        $this->expectException(ValidationException::class);
        $tools->confirm($actor, 'archive-session', $selected['token'], 'Stage5ArchivePass!', null, null, 'RESTORE MISSING DEVELOPMENT DATA');
    }

    public function test_managed_snapshot_export_is_sanitized_and_restore_is_idempotent(): void
    {
        $actor = $this->actor();
        $student = Student::factory()->verified()->create();
        Setting::set('fixture_secret', 'forbidden-private-setting');
        $backup = basename(app(BackupService::class)->createBackup());
        $plan = app(DevelopmentDataGraph::class)->export(['backups.snapshots']);
        $archive = app(DevelopmentDataArchiveService::class)->create($actor, $plan, app(ManagedBackupCatalog::class)->inventory());
        $read = app(DevelopmentDataArchiveService::class)->read($actor, $archive['path']);
        $payload = implode('', $read['payloads']);
        $this->assertStringNotContainsString($actor->password, $payload);
        $this->assertStringNotContainsString('forbidden-private-setting', $payload);
        $this->assertStringContainsString($student->email, $payload);
        Storage::disk('managed_backups')->delete($backup);
        $tools = app(DevelopmentToolsService::class);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $inspection = $tools->upload($actor, 'archive-session', $this->upload($archive['path']));
            $selected = $tools->selectImport($actor, 'archive-session', $inspection['token'], ['backups.snapshots']);
            $summary = $tools->confirm($actor, 'archive-session', $selected['token'], 'Stage5ArchivePass!', null, null, 'RESTORE MISSING DEVELOPMENT DATA');
            $this->assertSame($attempt === 0 ? 1 : 0, $summary['restored_managed_snapshots']);
        }
        $this->assertCount(1, app(ManagedBackupCatalog::class)->inventory());
        $this->assertDatabaseCount('students', 1);
    }

    public function test_download_is_owner_only_private_and_hash_verified(): void
    {
        $actor = $this->actor();
        Student::factory()->verified()->create();
        $archive = $this->export($actor, ['students.profile']);
        $operation = DevelopmentDataOperation::factory()->create(['administrator_id' => $actor->id, 'type' => 'export', 'domain' => 'selection',
            'status' => 'completed', 'archive_path' => $archive['path'], 'summary' => ['archive_sha256' => $archive['sha256']]]);
        $response = $this->actingAs($actor, 'web')->get(route('admin.development-tools.download', $operation));
        $response->assertOk()->assertDownload('development-export-'.$operation->id.'.zip');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($archive['sha256'], hash_file('sha256', $response->baseResponse->getFile()->getPathname()));
        $other = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $this->actingAs($other, 'web')->get(route('admin.development-tools.download', $operation))->assertNotFound();
        $this->actingAs($other, 'web')->get(route('admin.development-tools.show', $operation))->assertNotFound();
        $this->filesCorrupt($archive['path']);
        $this->actingAs($actor, 'web')->get(route('admin.development-tools.download', $operation))->assertNotFound();
        config(['development_tools.enabled' => false]);
        $this->get(route('admin.development-tools.download', $operation))->assertNotFound();
    }

    public function test_resource_translation_round_trip_preserves_generated_live_locale_and_replay(): void
    {
        $actor = $this->actor();
        $resource = ResourceFactory::new()->create();
        DB::table('resource_translations')->insert(['resource_id' => $resource->id, 'locale' => 'ar', 'source_revision_id' => 1,
            'status' => 'published', 'title' => 'Synthetic restored translation', 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
        $archive = $this->export($actor, ['resources.records']);
        $read = app(DevelopmentDataArchiveService::class)->read($actor, $archive['path']);
        $this->assertArrayNotHasKey('live_locale', $read['rows']['resource_translations'][0]);
        $tools = app(DevelopmentToolsService::class);
        $reset = $tools->preview($actor, 'archive-session', 'reset', 'resources', ['resources' => true, 'history' => true, 'files' => true]);
        $tools->confirm($actor, 'archive-session', $reset['token'], 'Stage5ArchivePass!', null, null, 'RESET RESOURCE TEST DATA');
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $inspection = $tools->upload($actor, 'archive-session', $this->upload($archive['path']));
            $selection = $tools->selectImport($actor, 'archive-session', $inspection['token'], ['resources.records']);
            $this->assertSame([], $selection['preview']['conflicts']);
            $tools->confirm($actor, 'archive-session', $selection['token'], 'Stage5ArchivePass!', null, null, 'RESTORE MISSING DEVELOPMENT DATA');
        }
        $this->assertDatabaseCount('resources', 1);
        $this->assertDatabaseCount('resource_translations', 1);
        $this->assertDatabaseHas('resource_translations', ['resource_id' => $resource->id, 'locale' => 'ar', 'live_locale' => 'ar', 'status' => 'published']);
        $this->assertDatabaseCount('resource_categories', 1);
    }

    public function test_native_snapshot_parser_handles_comments_and_refuses_unsupported_insert_format(): void
    {
        $this->actor();
        $sql = "-- Native synthetic snapshot\nCREATE TABLE `students` (\n `id` bigint,\n `first_name` text\n);\n-- Rows\nINSERT INTO `students` VALUES (1, 'O\\'Brien; Arabic');";
        $rows = app(ManagedBackupPortableService::class)->nativeRows($sql);
        $this->assertSame("O'Brien; Arabic", $rows['students'][0]['first_name']);
        $this->expectException(ValidationException::class);
        app(ManagedBackupPortableService::class)->nativeRows('INSERT INTO `students` (`id`) VALUES (1);');
    }

    private function filesCorrupt(string $path): void
    {
        app(DevelopmentDataFiles::class)->disk()->put($path, 'Changed archive');
    }

    private function actor(): Administrator
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('managed_backups');

        return AdministratorFactory::new()->create(['role' => 'super_admin', 'password' => Hash::make('Stage5ArchivePass!')]);
    }

    /** @param list<string> $modules
     * @param array<string, mixed> $filters
     * @return array<string, mixed> */
    private function export(Administrator $actor, array $modules, array $filters = []): array
    {
        $plan = app(DevelopmentDataGraph::class)->export($modules, $filters);

        return app(DevelopmentDataArchiveService::class)->create($actor, $plan);
    }

    private function upload(string $path): UploadedFile
    {
        return new UploadedFile(app(DevelopmentDataFiles::class)->disk()->path($path), 'development.zip', 'application/zip', null, true);
    }
}
