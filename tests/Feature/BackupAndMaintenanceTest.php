<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\System\Services\BackupService;
use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BackupAndMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected Administrator $superAdmin;

    protected Administrator $regularAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = Administrator::create([
            'name' => 'Super Administrator',
            'email' => 'super@boltlanding.test',
            'password' => Hash::make('SecretPass123!'),
            'role' => 'super_admin',
        ]);

        $this->regularAdmin = Administrator::create([
            'name' => 'Regular Administrator',
            'email' => 'regular@boltlanding.test',
            'password' => Hash::make('SecretPass123!'),
            'role' => 'admin',
        ]);
    }

    public function test_backup_run_command_creates_valid_archive(): void
    {
        $this->artisan('backup:run --clean')
            ->assertSuccessful();

        $backupService = app(BackupService::class);
        $backups = $backupService->getBackups();

        $this->assertNotEmpty($backups);
        $latest = $backups[0];
        $this->assertStringEndsWith('.zip', $latest['filename']);
        $this->assertGreaterThan(0, $latest['size_bytes']);

        $path = $backupService->getBackupPath($latest['filename']);
        $this->assertNotNull($path);
        $this->assertFileExists($path);

        // Cleanup test backup
        $backupService->deleteBackup($latest['filename']);
    }

    public function test_cleanup_expired_holds_command_transitions_expired_holds(): void
    {
        $sessionType = SessionType::create([
            'title' => 'Session',
            'slug' => 'session-test',
            'duration_minutes' => 60,
            'price' => 40.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        // 1. Create active hold that is in the past
        $expiredHold = BookingHold::create([
            'session_type_id' => $sessionType->id,
            'slot_start_utc' => now('UTC')->addHour(),
            'slot_end_utc' => now('UTC')->addHours(2),
            'visitor_token' => 'vis-expired',
            'hold_token' => 'tok-expired',
            'status' => 'active',
            'expires_at' => now('UTC')->subMinute(),
        ]);

        // 2. Create active hold that is still valid
        $validHold = BookingHold::create([
            'session_type_id' => $sessionType->id,
            'slot_start_utc' => now('UTC')->addHours(3),
            'slot_end_utc' => now('UTC')->addHours(4),
            'visitor_token' => 'vis-valid',
            'hold_token' => 'tok-valid',
            'status' => 'active',
            'expires_at' => now('UTC')->addMinutes(10),
        ]);

        $this->artisan('booking:cleanup-holds')
            ->assertSuccessful()
            ->expectsOutputToContain('Cleaned up 1 expired booking hold(s).');

        $this->assertEquals('expired', $expiredHold->fresh()->status);
        $this->assertEquals('active', $validHold->fresh()->status);
    }

    public function test_super_admin_can_access_backups_dashboard_and_create_backup(): void
    {
        $response = $this->actingAs($this->superAdmin, 'web')
            ->get(route('admin.backups.index'));

        $response->assertOk();
        $response->assertSee('Database & Asset Backups');

        $createResponse = $this->actingAs($this->superAdmin, 'web')
            ->post(route('admin.backups.create'));

        $createResponse->assertRedirect();
        $createResponse->assertSessionHas('success');
    }

    public function test_regular_admin_denied_access_to_backups(): void
    {
        $response = $this->actingAs($this->regularAdmin, 'web')
            ->get(route('admin.backups.index'));

        $response->assertForbidden();

        $createResponse = $this->actingAs($this->regularAdmin, 'web')
            ->post(route('admin.backups.create'));

        $createResponse->assertForbidden();
    }

    public function test_backup_download_and_deletion(): void
    {
        $backupService = app(BackupService::class);
        $backupPath = $backupService->createBackup('test');
        $filename = basename($backupPath);

        // Download as super admin
        $downloadResponse = $this->actingAs($this->superAdmin, 'web')
            ->get(route('admin.backups.download', ['filename' => $filename]));

        $downloadResponse->assertOk();
        $this->assertEquals('application/zip', $downloadResponse->headers->get('content-type'));

        // Delete as super admin
        $deleteResponse = $this->actingAs($this->superAdmin, 'web')
            ->delete(route('admin.backups.destroy', ['filename' => $filename]));

        $deleteResponse->assertRedirect();
        $this->assertFalse(File::exists($backupPath));
    }

    public function test_maintenance_mode_blocks_public_visitors_with_503(): void
    {
        // 1. Maintenance mode off
        Setting::set('maintenance_mode', '0');
        $resPublic = $this->get('/');
        $resPublic->assertOk();

        // 2. Turn maintenance mode on
        Setting::set('maintenance_mode', '1');

        $resBlocked = $this->get('/');
        $resBlocked->assertStatus(503);
        $resBlocked->assertSee("We'll Be Right Back", false);

        // 3. Admin routes are still accessible
        $resAdmin = $this->get(route('admin.login'));
        $resAdmin->assertOk();

        // 4. Authenticated admin can view public routes during maintenance
        $resAuthAdmin = $this->actingAs($this->superAdmin, 'web')->get('/');
        $resAuthAdmin->assertOk();

        // Turn maintenance mode back off
        Setting::set('maintenance_mode', '0');
    }

    public function test_backup_zip_contains_known_private_and_public_assets_with_manifest_and_sha256(): void
    {
        $backupService = app(BackupService::class);

        // 1. Create real private resource file & public media file
        $privateDir = storage_path('app/private/resources');
        $publicDir = storage_path('app/public/media');
        File::makeDirectory($privateDir, 0755, true, true);
        File::makeDirectory($publicDir, 0755, true, true);

        $privateFile = $privateDir.'/test-resource.pdf';
        $publicFile = $publicDir.'/test-banner.jpg';

        $privateContent = 'PRIVATE_GATED_PDF_CONTENT_'.uniqid();
        $publicContent = 'PUBLIC_MEDIA_BANNER_CONTENT_'.uniqid();

        File::put($privateFile, $privateContent);
        File::put($publicFile, $publicContent);

        $expectedPrivateSha = hash('sha256', $privateContent);
        $expectedPublicSha = hash('sha256', $publicContent);

        // Create a unique setting to verify in database dump
        $uniqueKey = 'test_backup_marker_'.uniqid();
        Setting::set($uniqueKey, 'marked_value', 'system');

        $backupPath = $backupService->createBackup('full');
        $this->assertFileExists($backupPath);

        // Verify ZIP contents
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($backupPath));

        // 1. Verify manifest exists and is valid
        $manifestJson = $zip->getFromName('manifest.json');
        $this->assertNotFalse($manifestJson);
        $manifest = json_decode($manifestJson, true);
        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('database_sha256', $manifest);
        $this->assertArrayHasKey('files', $manifest);

        // 2. Verify database dump inside ZIP
        $sql = $zip->getFromName('database.sql');
        $this->assertNotFalse($sql);
        $this->assertEquals($manifest['database_sha256'], hash('sha256', $sql));
        $this->assertStringContainsString($uniqueKey, $sql);

        // 3. Verify private and public asset files inside ZIP
        $zipPrivateContent = $zip->getFromName('storage/private/resources/test-resource.pdf');
        $this->assertSame($privateContent, $zipPrivateContent);

        $zipPublicContent = $zip->getFromName('storage/public/media/test-banner.jpg');
        $this->assertSame($publicContent, $zipPublicContent);

        // Verify checksums in manifest match
        $this->assertEquals($expectedPrivateSha, $manifest['files']['storage/private/resources/test-resource.pdf']['sha256']);
        $this->assertEquals($expectedPublicSha, $manifest['files']['storage/public/media/test-banner.jpg']['sha256']);

        $zip->close();

        // Clean up
        File::delete([$privateFile, $publicFile]);
        $backupService->deleteBackup(basename($backupPath));
    }

    public function test_restore_drill_into_isolated_directory_verifies_exact_hashes_and_files(): void
    {
        $backupService = app(BackupService::class);

        // 1. Create known assets
        $privateDir = storage_path('app/private/resources');
        $publicDir = storage_path('app/public/media');
        File::makeDirectory($privateDir, 0755, true, true);
        File::makeDirectory($publicDir, 0755, true, true);

        $privateFile = $privateDir.'/restore-drill.pdf';
        $publicFile = $publicDir.'/restore-drill.jpg';

        $privateContent = 'RESTORE_DRILL_PRIVATE_'.uniqid();
        $publicContent = 'RESTORE_DRILL_PUBLIC_'.uniqid();

        File::put($privateFile, $privateContent);
        File::put($publicFile, $publicContent);

        $backupPath = $backupService->createBackup('full');

        // 2. Restore into isolated directory (files only)
        $isolatedTarget = storage_path('app/temp/isolated_restore_'.uniqid());
        $summary = $backupService->restoreBackup($backupPath, $isolatedTarget, false, true);

        $this->assertGreaterThanOrEqual(2, $summary['files_restored']);
        $this->assertFalse($summary['database_restored']);

        $restoredPrivate = $isolatedTarget.'/private/resources/restore-drill.pdf';
        $restoredPublic = $isolatedTarget.'/public/media/restore-drill.jpg';

        $this->assertFileExists($restoredPrivate);
        $this->assertFileExists($restoredPublic);

        $this->assertSame($privateContent, File::get($restoredPrivate));
        $this->assertSame($publicContent, File::get($restoredPublic));

        $this->assertEquals(hash('sha256', $privateContent), hash_file('sha256', $restoredPrivate));
        $this->assertEquals(hash('sha256', $publicContent), hash_file('sha256', $restoredPublic));

        // Clean up
        File::delete([$privateFile, $publicFile]);
        File::deleteDirectory($isolatedTarget);
        $backupService->deleteBackup(basename($backupPath));
    }

    public function test_corrupt_or_incomplete_backup_detected(): void
    {
        $backupService = app(BackupService::class);
        $tempDir = storage_path('app/temp/corrupt_test_'.uniqid());
        File::makeDirectory($tempDir, 0755, true, true);

        // Missing manifest
        $noManifestZip = $tempDir.'/no_manifest.zip';
        $zipA = new ZipArchive;
        $zipA->open($noManifestZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zipA->addFromString('database.sql', '-- dummy');
        $zipA->close();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('missing manifest.json');
        try {
            $backupService->restoreBackup($noManifestZip);
        } finally {
            File::delete($noManifestZip);
            File::deleteDirectory($tempDir);
        }
    }

    public function test_corrupt_database_checksum_mismatch_detected(): void
    {
        $backupService = app(BackupService::class);
        $tempDir = storage_path('app/temp/corrupt_test_'.uniqid());
        File::makeDirectory($tempDir, 0755, true, true);

        $tamperedDbZip = $tempDir.'/tampered_db.zip';
        $zip = new ZipArchive;
        $zip->open($tamperedDbZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('database.sql', 'ALTERED CONTENT');
        $manifest = [
            'database_sha256' => hash('sha256', 'ORIGINAL CONTENT'),
            'files' => [],
        ];
        $zip->addFromString('manifest.json', json_encode($manifest));
        $zip->close();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Database dump checksum mismatch');
        try {
            $backupService->restoreBackup($tamperedDbZip);
        } finally {
            File::delete($tamperedDbZip);
            File::deleteDirectory($tempDir);
        }
    }

    public function test_retention_policy_prunes_expired_backups(): void
    {
        $backupService = app(BackupService::class);
        $backupDir = storage_path('app/backups');

        // Create 2 "old" dummy backups and 1 "fresh"
        $oldFile1 = $backupDir.'/backup-full-2025-01-01-000000.zip';
        $oldFile2 = $backupDir.'/backup-full-2025-01-02-000000.zip';
        $freshFile = $backupDir.'/backup-full-'.now('UTC')->format('Y-m-d-His').'.zip';

        File::put($oldFile1, 'dummy old 1');
        File::put($oldFile2, 'dummy old 2');
        File::put($freshFile, 'dummy fresh');

        // Backdate mtime
        touch($oldFile1, time() - (45 * 86400));
        touch($oldFile2, time() - (45 * 86400));

        Setting::set('backup_retention_days', '30');

        $pruned = $backupService->cleanOldBackups();
        $this->assertGreaterThanOrEqual(2, $pruned);

        $this->assertFalse(File::exists($oldFile1));
        $this->assertFalse(File::exists($oldFile2));
        $this->assertTrue(File::exists($freshFile));

        File::delete($freshFile);
    }

    public function test_artisan_backup_restore_command_executes_successfully(): void
    {
        $backupService = app(BackupService::class);
        $backupPath = $backupService->createBackup('full');
        $filename = basename($backupPath);

        $isolatedTarget = storage_path('app/temp/artisan_restore_'.uniqid());

        $this->artisan('backup:restore', [
            'file' => $filename,
            '--force' => true,
            '--no-db' => true,
            '--target-dir' => $isolatedTarget,
        ])
            ->expectsOutputToContain('Restoration completed successfully')
            ->assertSuccessful();

        $this->assertDirectoryExists($isolatedTarget);

        File::deleteDirectory($isolatedTarget);
        $backupService->deleteBackup($filename);
    }

    public function test_documented_environment_setting_selects_intended_disk(): void
    {
        $this->assertEquals(env('BACKUP_OFFSITE_DISK', null), config('filesystems.backup_disk'));
    }

    public function test_corrupt_asset_causes_zero_destination_and_database_changes(): void
    {
        $backupService = app(BackupService::class);
        $tempDir = storage_path('app/temp/corrupt_asset_drill_'.uniqid());
        $isolatedTarget = storage_path('app/temp/isolated_target_'.uniqid());
        File::makeDirectory($tempDir, 0755, true, true);

        // Record a setting baseline
        $baselineSettingKey = 'preflight_canary_'.uniqid();
        Setting::set($baselineSettingKey, 'original_val', 'system');

        $corruptZip = $tempDir.'/corrupt_asset.zip';
        $zip = new ZipArchive;
        $zip->open($corruptZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Add database.sql that would alter the setting if executed
        $tamperedSql = "UPDATE `settings` SET `value` = 'tampered_val' WHERE `key` = '{$baselineSettingKey}';\n";
        $zip->addFromString('database.sql', $tamperedSql);

        // Add 1 valid file and 1 tampered file
        $validContent = 'VALID_CONTENT';
        $tamperedContent = 'ACTUAL_CONTENT_DOES_NOT_MATCH_HASH';
        $zip->addFromString('storage/private/resources/file1.pdf', $validContent);
        $zip->addFromString('storage/public/media/file2.jpg', $tamperedContent);

        $manifest = [
            'database_sha256' => hash('sha256', $tamperedSql),
            'files' => [
                'storage/private/resources/file1.pdf' => [
                    'sha256' => hash('sha256', $validContent),
                    'size_bytes' => strlen($validContent),
                ],
                'storage/public/media/file2.jpg' => [
                    'sha256' => hash('sha256', 'DIFFERENT_HASH_EXPECTED'),
                    'size_bytes' => 123,
                ],
            ],
        ];
        $zip->addFromString('manifest.json', json_encode($manifest));
        $zip->close();

        $exceptionThrown = false;
        try {
            $backupService->restoreBackup($corruptZip, $isolatedTarget, true, true);
        } catch (Exception $e) {
            $exceptionThrown = true;
            $this->assertStringContainsString('File checksum mismatch for storage/public/media/file2.jpg', $e->getMessage());
        }

        $this->assertTrue($exceptionThrown, 'Expected restoreBackup to throw an exception on checksum mismatch during preflight.');

        // Verify zero files written to isolated target
        if (File::isDirectory($isolatedTarget)) {
            $this->assertEmpty(File::allFiles($isolatedTarget), 'Preflight failure must NOT write any files to the destination directory.');
        } else {
            $this->assertDirectoryDoesNotExist($isolatedTarget);
        }

        // Verify database was NOT touched
        $this->assertEquals('original_val', Setting::get($baselineSettingKey), 'Preflight failure must NOT execute database restoration statements.');

        // Clean up
        File::deleteDirectory($tempDir);
        if (File::isDirectory($isolatedTarget)) {
            File::deleteDirectory($isolatedTarget);
        }
    }

    public function test_missing_manifest_asset_fails_preflight_with_zero_writes(): void
    {
        $backupService = app(BackupService::class);
        $tempDir = storage_path('app/temp/missing_asset_drill_'.uniqid());
        $isolatedTarget = storage_path('app/temp/isolated_missing_target_'.uniqid());
        File::makeDirectory($tempDir, 0755, true, true);

        $zipPath = $tempDir.'/missing_asset.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('database.sql', '-- dummy');
        $manifest = [
            'database_sha256' => hash('sha256', '-- dummy'),
            'files' => [
                'storage/private/resources/ghost.pdf' => [
                    'sha256' => hash('sha256', 'anything'),
                    'size_bytes' => 10,
                ],
            ],
        ];
        $zip->addFromString('manifest.json', json_encode($manifest));
        $zip->close();

        $exceptionThrown = false;
        try {
            $backupService->restoreBackup($zipPath, $isolatedTarget, false, true);
        } catch (Exception $e) {
            $exceptionThrown = true;
            $this->assertStringContainsString('Archive missing manifest entry: storage/private/resources/ghost.pdf', $e->getMessage());
        }

        $this->assertTrue($exceptionThrown);
        if (File::isDirectory($isolatedTarget)) {
            $this->assertEmpty(File::allFiles($isolatedTarget));
        } else {
            $this->assertDirectoryDoesNotExist($isolatedTarget);
        }

        File::deleteDirectory($tempDir);
        if (File::isDirectory($isolatedTarget)) {
            File::deleteDirectory($isolatedTarget);
        }
    }

    public function test_unsafe_archive_path_fails_preflight_with_zero_writes(): void
    {
        $backupService = app(BackupService::class);
        $tempDir = storage_path('app/temp/unsafe_path_drill_'.uniqid());
        $isolatedTarget = storage_path('app/temp/isolated_unsafe_target_'.uniqid());
        File::makeDirectory($tempDir, 0755, true, true);

        $zipPath = $tempDir.'/unsafe_path.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('database.sql', '-- dummy');
        $manifest = [
            'database_sha256' => hash('sha256', '-- dummy'),
            'files' => [
                'storage/private/../../exploit.php' => [
                    'sha256' => hash('sha256', '<?php phpinfo();'),
                    'size_bytes' => 17,
                ],
            ],
        ];
        $zip->addFromString('manifest.json', json_encode($manifest));
        $zip->close();

        $exceptionThrown = false;
        try {
            $backupService->restoreBackup($zipPath, $isolatedTarget, false, true);
        } catch (Exception $e) {
            $exceptionThrown = true;
            $this->assertStringContainsString('Unsafe archive path detected', $e->getMessage());
        }

        $this->assertTrue($exceptionThrown);
        if (File::isDirectory($isolatedTarget)) {
            $this->assertEmpty(File::allFiles($isolatedTarget));
        } else {
            $this->assertDirectoryDoesNotExist($isolatedTarget);
        }

        File::deleteDirectory($tempDir);
        if (File::isDirectory($isolatedTarget)) {
            File::deleteDirectory($isolatedTarget);
        }
    }

    public function test_failed_offsite_write_records_failure(): void
    {
        $backupService = app(BackupService::class);

        // Mock a filesystem disk where put returns false
        $failingDisk = $this->createMock(Filesystem::class);
        $failingDisk->method('put')->willReturn(false);

        Storage::set('test_offsite_failing', $failingDisk);
        config([
            'filesystems.backup_disk' => 'test_offsite_failing',
            'filesystems.disks.test_offsite_failing' => ['driver' => 'custom'],
        ]);

        $backupPath = $backupService->createBackup('test');
        $this->assertFileExists($backupPath);

        $status = Setting::get('last_offsite_backup_status');
        $this->assertNotNull($status);
        $this->assertStringStartsWith('failed:', $status);

        // Clean up
        $backupService->deleteBackup(basename($backupPath));
    }
}
