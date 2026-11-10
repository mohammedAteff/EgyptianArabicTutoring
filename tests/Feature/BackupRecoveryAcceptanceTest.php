<?php

namespace Tests\Feature;

use App\Domains\Notifications\Services\TelegramAutomationService;
use App\Domains\System\Services\BackupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BackupRecoveryAcceptanceTest extends TestCase
{
    use DatabaseTruncation;

    private function fingerprints(bool $restored = false): array
    {
        $result = [];
        foreach (DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"') as $table) {
            $name = array_values((array) $table)[0];
            $schema = DB::selectOne('SHOW CREATE TABLE `'.$name.'`')->{'Create Table'};
            $query = DB::table($name);
            if ($restored && $name === 'settings') {
                $query->whereNotIn('key', ['last_restore_at', 'last_restore_file']);
            }
            if ($restored && $name === 'audit_logs') {
                $query->where('action', '!=', 'backup_restored');
            }
            $rows = $query->get()->map(fn (object $row): string => serialize((array) $row))->all();
            sort($rows, SORT_STRING);
            $result[$name] = ['schema' => hash('sha256', preg_replace('/ AUTO_INCREMENT=\d+/', '', $schema)),
                'rows' => count($rows), 'data' => hash('sha256', serialize($rows))];
        }
        ksort($result);

        return $result;
    }

    public function test_fresh_synthetic_archive_recovers_all_rows_schema_objects_binary_and_assets_under_strict_mode(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('managed_backups');
        config(['filesystems.backup_disk' => null]);
        $this->mock(TelegramAutomationService::class)->shouldReceive('emit')->once()->withArgs(fn ($trigger): bool => $trigger === 'backup_success');
        Schema::create('backup_recovery_fixture', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('base_value');
            $table->integer('virtual_value')->virtualAs('base_value * 2')->unique();
            $table->integer('stored_value')->storedAs('base_value * 3');
            $table->binary('payload', 32);
            $table->foreign('parent_id')->references('id')->on('backup_recovery_fixture');
        });
        $mode = DB::selectOne('SELECT @@SESSION.sql_mode mode')->mode;
        $strict = 'ONLY_FULL_GROUP_BY,STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_VALUE_ON_ZERO,NO_ENGINE_SUBSTITUTION';
        try {
            DB::table('backup_recovery_fixture')->insert(['id' => 1, 'parent_id' => null, 'base_value' => 7, 'payload' => "\x00\x80\xff'\\"]);
            DB::table('backup_recovery_fixture')->insert(['id' => 2, 'parent_id' => 1, 'base_value' => 8, 'payload' => "\x00\x01Binary"]);
            Storage::disk('local')->put('resources/required.bin', "PRIVATE_SYNTHETIC\x00\xff");
            Storage::disk('public')->put('media/required.txt', 'PUBLIC_SYNTHETIC');
            $this->assertDatabaseMissing('settings', ['key' => 'last_restore_at']);
            $this->assertDatabaseMissing('settings', ['key' => 'last_restore_file']);
            $this->assertDatabaseMissing('audit_logs', ['action' => 'backup_restored']);
            $service = app(BackupService::class);
            $expected = $this->fingerprints();
            $objects = $service->snapshotDatabaseObjects();
            $archive = $service->createBackup();
            $evidence = getenv('ACCEPTANCE_RESTRICTED_DIRECTORY') ?: storage_path('acceptance-restricted');
            File::ensureDirectoryExists($evidence, 0700);
            $original = $evidence.'/synthetic-recovery-original.zip';
            File::copy($archive, $original);
            $strictArchive = $evidence.'/synthetic-recovery-strict.zip';
            File::copy($original, $strictArchive);
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($strictArchive));
            $manifest = json_decode($zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            $sql = str_replace('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";', 'SET SQL_MODE="'.$strict.'";', $zip->getFromName('database.sql'), $replacements);
            $this->assertSame(1, $replacements);
            $manifest['database_sha256'] = hash('sha256', $sql);
            $this->assertTrue($zip->addFromString('database.sql', $sql));
            $this->assertTrue($zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR)));
            $this->assertTrue($zip->close());
            $target = $evidence.'/recovered-assets';

            $summary = $service->restoreBackup($strictArchive, $target, true, true);

            $this->assertTrue($summary['database_restored']);
            $this->assertSame(2, $summary['files_restored']);
            $actual = $this->fingerprints(true);
            $this->assertSame($expected, $actual);
            $this->assertSame($objects, $service->snapshotDatabaseObjects());
            $this->assertStringContainsString('STRICT_ALL_TABLES', DB::selectOne('SELECT @@SESSION.sql_mode mode')->mode);
            $this->assertSame("PRIVATE_SYNTHETIC\x00\xff", File::get($target.'/private/resources/required.bin'));
            $this->assertSame('PUBLIC_SYNTHETIC', File::get($target.'/public/media/required.txt'));
            $this->assertDatabaseHas('backup_recovery_fixture', ['id' => 1, 'virtual_value' => 14, 'stored_value' => 21]);
            $this->assertDatabaseHas('backup_recovery_fixture', ['id' => 2, 'parent_id' => 1, 'virtual_value' => 16, 'stored_value' => 24]);
            $this->assertSame(2, DB::table('settings')->whereIn('key', ['last_restore_at', 'last_restore_file'])->count());
            $this->assertSame(1, DB::table('audit_logs')->where('action', 'backup_restored')->count());
            $foreignKeys = collect(DB::select('SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION'))->groupBy(fn (object $key): string => $key->TABLE_NAME.'.'.$key->CONSTRAINT_NAME);
            foreach ($foreignKeys as $columns) {
                $first = $columns->first();
                $join = $columns->map(fn (object $key): string => 'c.`'.$key->COLUMN_NAME.'` = p.`'.$key->REFERENCED_COLUMN_NAME.'`')->implode(' AND ');
                $notNull = $columns->map(fn (object $key): string => 'c.`'.$key->COLUMN_NAME.'` IS NOT NULL')->implode(' AND ');
                $orphans = DB::selectOne('SELECT COUNT(*) total FROM `'.$first->TABLE_NAME.'` c LEFT JOIN `'.$first->REFERENCED_TABLE_NAME.'` p ON '.$join.' WHERE '.$notNull.' AND p.`'.$first->REFERENCED_COLUMN_NAME.'` IS NULL')->total;
                $this->assertSame(0, (int) $orphans);
            }
            file_put_contents($this->acceptanceOutputPath('synthetic-recovery.json'), json_encode([
                'database' => DB::connection()->getDatabaseName(), 'engine' => DB::selectOne('SELECT VERSION() version')->version,
                'original_sha256' => hash_file('sha256', $original), 'strict_variant_sha256' => hash_file('sha256', $strictArchive),
                'strict_variant_change' => 'Only SQL_MODE and matching SQL checksum changed; original archive retained.',
                'tables' => $actual, 'triggers' => count($objects), 'foreign_keys_checked' => $foreignKeys->count(),
                'orphan_rows' => 0, 'assets' => 2, 'restore_metadata_exceptions' => ['two documented last_restore settings', 'one backup_restored audit event'],
                'schema_normalization' => 'AUTO_INCREMENT next counters only; constraints, expressions, indexes and other schema compared.',
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } finally {
            DB::statement('SET AUTOCOMMIT=1');
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::statement('SET SESSION sql_mode = ?', [$mode]);
            Schema::dropIfExists('backup_recovery_fixture');
        }
    }
}
