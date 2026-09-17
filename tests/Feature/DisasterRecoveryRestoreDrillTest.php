<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\Setting;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use App\Domains\System\Services\BackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DisasterRecoveryRestoreDrillTest extends TestCase
{
    public function test_destructive_restore_drill_into_isolated_disposable_database_and_directory(): void
    {
        $backupService = app(BackupService::class);
        $isolatedDbName = 'bolt_landing_restore_drill_'.time();

        // 1. Prepare disposable database on MySQL/MariaDB
        DB::statement("CREATE DATABASE IF NOT EXISTS `{$isolatedDbName}`");

        $defaultMysqlConfig = config('database.connections.mysql');
        config([
            'database.connections.restore_drill' => array_merge($defaultMysqlConfig, [
                'database' => $isolatedDbName,
            ]),
        ]);

        // 2. Prepare real test assets and database records
        $drillPdfPath = storage_path('app/private/resources/drill_doc.pdf');
        $drillCoverPath = storage_path('app/public/media/drill_cover.png');
        File::ensureDirectoryExists(dirname($drillPdfPath));
        File::ensureDirectoryExists(dirname($drillCoverPath));
        File::put($drillPdfPath, 'REAL-DRILL-PDF-BYTES-9988');
        File::put($drillCoverPath, 'REAL-DRILL-COVER-PNG-BYTES-5544');

        Setting::set('restore_drill_sentinel_key', 'sentinel_verified_value', 'system');

        // Create resource record in database
        $category = ResourceCategory::create([
            'name' => 'Drill Category',
            'slug' => 'drill-category-'.time(),
            'sort_order' => 1,
            'active' => true,
        ]);

        $resource = Resource::create([
            'title' => 'Drill Recovery Resource',
            'slug' => 'drill-recovery-resource-'.time(),
            'category_id' => $category->id,
            'short_description' => 'Disaster recovery verification document',
            'file_type' => 'pdf',
            'file_path' => 'resources/drill_doc.pdf',
            'file_size' => 24,
            'cover_image_path' => 'media/drill_cover.png',
            'status' => 'published',
            'published_at' => now(),
        ]);

        // 3. Create full point-in-time snapshot backup
        $backupPath = $backupService->createBackup('full_drill');
        $this->assertFileExists($backupPath);

        // 4. Execute restore drill into disposable database and isolated directory
        $isolatedExtractDir = storage_path('app/temp/isolated_extract_'.uniqid());
        File::ensureDirectoryExists($isolatedExtractDir);

        try {
            $restoreResult = $backupService->restoreBackup(
                zipPath: $backupPath,
                targetExtractDir: $isolatedExtractDir,
                restoreDatabase: true,
                restoreFiles: true,
                connection: 'restore_drill'
            );

            // 5. Assert restoration execution summary
            $this->assertTrue($restoreResult['database_restored']);
            $this->assertGreaterThan(0, $restoreResult['files_restored']);

            // 6. Assert records and relationships reconstructed in disposable database
            $restoredSetting = DB::connection('restore_drill')->table('settings')
                ->where('key', 'restore_drill_sentinel_key')
                ->value('value');
            $this->assertSame('sentinel_verified_value', $restoredSetting);

            $restoredResource = DB::connection('restore_drill')->table('resources')
                ->where('id', $resource->id)
                ->first();
            $this->assertNotNull($restoredResource);
            $this->assertSame('Drill Recovery Resource', $restoredResource->title);
            $this->assertSame($category->id, (int) $restoredResource->category_id);

            // 7. Assert extracted files match exact bytes and SHA-256
            $extractedPdf = $isolatedExtractDir.'/private/resources/drill_doc.pdf';
            $this->assertFileExists($extractedPdf);
            $this->assertSame('REAL-DRILL-PDF-BYTES-9988', File::get($extractedPdf));
            $this->assertSame(hash('sha256', 'REAL-DRILL-PDF-BYTES-9988'), hash_file('sha256', $extractedPdf));

            $extractedCover = $isolatedExtractDir.'/public/media/drill_cover.png';
            $this->assertFileExists($extractedCover);
            $this->assertSame('REAL-DRILL-COVER-PNG-BYTES-5544', File::get($extractedCover));
            $this->assertSame(hash('sha256', 'REAL-DRILL-COVER-PNG-BYTES-5544'), hash_file('sha256', $extractedCover));
        } finally {
            // Crucial: Disconnect and purge connection before dropping database to avoid MySQL metadata lock deadlock
            DB::purge('restore_drill');
            DB::disconnect('restore_drill');

            DB::statement("DROP DATABASE IF EXISTS `{$isolatedDbName}`");
            File::deleteDirectory($isolatedExtractDir);
            File::delete($drillPdfPath);
            File::delete($drillCoverPath);
            $backupService->deleteBackup(basename($backupPath));

            // Clean up created models from test db
            $resource->forceDelete();
            $category->forceDelete();
            Setting::where('key', 'restore_drill_sentinel_key')->delete();
        }
    }
}
