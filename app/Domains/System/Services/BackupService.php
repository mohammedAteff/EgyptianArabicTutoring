<?php

namespace App\Domains\System\Services;

use App\Domains\Administration\Services\AdminNotificationService;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\CMS\Models\Setting;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

class BackupService
{
    protected string $backupDir;

    public function __construct()
    {
        $this->backupDir = storage_path('app/backups');
        if (! File::isDirectory($this->backupDir)) {
            File::makeDirectory($this->backupDir, 0755, true);
        }
    }

    /**
     * Run full backup: MariaDB database dump and stored assets archive.
     */
    public function createBackup(string $type = 'full'): string
    {
        // The type is a label supplied by an operator/UI. Normalize it before
        // using it in a filesystem name so it can never introduce path
        // separators or traversal components.
        $type = trim((string) preg_replace('/[^A-Za-z0-9_-]/', '_', $type));
        $type = $type !== '' ? substr($type, 0, 32) : 'full';

        $timestamp = CarbonImmutable::now('UTC')->format('Y-m-d-His');
        $zipFilename = "backup-{$type}-{$timestamp}.zip";
        $zipPath = $this->backupDir.DIRECTORY_SEPARATOR.$zipFilename;

        $tempDir = storage_path('app/temp/backup-'.$timestamp);
        File::makeDirectory($tempDir, 0755, true);

        try {
            // 1. Generate SQL dump
            $sqlFile = $tempDir.DIRECTORY_SEPARATOR.'database.sql';
            $this->dumpDatabase($sqlFile);

            // 2. Create Zip archive
            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception("Unable to create zip file at {$zipPath}");
            }

            // Add database dump and calculate checksum
            $zip->addFile($sqlFile, 'database.sql');
            $dbSha256 = hash_file('sha256', $sqlFile);

            $fileHashes = [];

            // Add private files (includes gated resources stored under storage/app/private/resources)
            $this->addDirectoryToZip($zip, storage_path('app/private'), 'storage/private', $fileHashes);

            // Add public files (includes public media)
            $this->addDirectoryToZip($zip, storage_path('app/public'), 'storage/public', $fileHashes);

            // Legacy path fallbacks if present
            if (File::isDirectory(storage_path('app/resources'))) {
                $this->addDirectoryToZip($zip, storage_path('app/resources'), 'storage/resources', $fileHashes);
            }
            if (File::isDirectory(storage_path('app/media'))) {
                $this->addDirectoryToZip($zip, storage_path('app/media'), 'storage/media', $fileHashes);
            }

            // Add metadata manifest
            $manifest = [
                'type' => $type,
                'created_at_utc' => CarbonImmutable::now('UTC')->toIso8601String(),
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
                'db_connection' => config('database.default'),
                'database_sha256' => $dbSha256,
                'files' => $fileHashes,
                'retention_days' => (int) Setting::get('backup_retention_days', 30),
            ];
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

            $zip->close();

            // Replicate to offsite disk if configured
            $offsiteDisk = config('filesystems.backup_disk') ?: Setting::get('backup_offsite_disk');
            if ($offsiteDisk && $offsiteDisk !== 'local' && config("filesystems.disks.{$offsiteDisk}")) {
                try {
                    $stream = fopen($zipPath, 'r');
                    $uploaded = Storage::disk($offsiteDisk)->put('backups/'.$zipFilename, $stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                    if ($uploaded === false) {
                        throw new Exception("Offsite upload to disk '{$offsiteDisk}' returned false.");
                    }
                    Setting::set('last_offsite_backup_status', 'success', 'system');
                } catch (Throwable $offsiteEx) {
                    $category = $this->categorizeOffsiteError($offsiteEx);
                    Setting::set('last_offsite_backup_status', 'failed', 'system');
                    Setting::set('last_offsite_backup_category', $category, 'system');
                    Log::error("Failed to replicate backup {$zipFilename} to offsite disk {$offsiteDisk}: [{$category}]", ['exception' => $offsiteEx]);
                }
            }

            // Record success state in settings
            Setting::set('last_backup_at', CarbonImmutable::now('UTC')->toIso8601String(), 'system');
            Setting::set('last_backup_status', 'success', 'system');
            Setting::set('last_backup_file', $zipFilename, 'system');

            AuditLog::create([
                'administrator_id' => Auth::id(),
                'action' => 'backup_created',
                'entity_type' => self::class,
                'entity_id' => 0,
                'new_data' => [
                    'filename' => $zipFilename,
                    'size_bytes' => File::size($zipPath),
                    'type' => $type,
                ],
                'created_at' => now(),
            ]);

            return $zipPath;
        } catch (Throwable $e) {
            Setting::set('last_backup_status', 'failed: '.$e->getMessage(), 'system');
            Log::error('Backup creation failed: '.$e->getMessage(), ['exception' => $e]);
            try {
                app(AdminNotificationService::class)->notifyBackupFailed($e->getMessage());
            } catch (Throwable) {
                // Ignore failure in dispatching failure notification
            }
            throw $e;
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    /**
     * Dump database using mysqldump if available, or native PDO fallback.
     */
    public function dumpDatabase(string $outputPath): void
    {
        $connection = config('database.default');
        $dbConfig = config("database.connections.{$connection}");

        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = $dbConfig['port'] ?? '3306';
        $database = $dbConfig['database'] ?? '';
        $username = $dbConfig['username'] ?? 'root';
        $password = $dbConfig['password'] ?? '';

        $mysqldump = $this->findMysqldumpBinary();

        if (app()->environment('testing') || ! config('database.backup_use_mysqldump', false)) {
            $this->dumpDatabaseViaPdo($outputPath);

            return;
        }

        $mysqldump = $this->findMysqldumpBinary();

        if ($mysqldump && ! empty($database)) {
            $cmd = sprintf(
                '"%s" --host=%s --port=%s --user=%s %s %s > "%s"',
                $mysqldump,
                escapeshellarg($host),
                escapeshellarg((string) $port),
                escapeshellarg($username),
                $password !== '' ? '--password='.escapeshellarg($password) : '',
                escapeshellarg($database),
                $outputPath
            );

            @exec($cmd, $output, $returnCode);

            if ($returnCode === 0 && File::exists($outputPath) && File::size($outputPath) > 0) {
                return;
            }
        }

        // Native PHP / PDO SQL dump fallback (works in any environment)
        $this->dumpDatabaseViaPdo($outputPath);
    }

    /**
     * Dump database using PDO queries.
     */
    protected function dumpDatabaseViaPdo(string $outputPath): void
    {
        $isMysql = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
        $startedTransaction = false;

        if ($isMysql && DB::transactionLevel() === 0) {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            DB::beginTransaction();
            $startedTransaction = true;
        }

        try {
            $handle = fopen($outputPath, 'w');
            if (! $handle) {
                throw new Exception("Cannot open {$outputPath} for writing.");
            }

            fwrite($handle, "-- Egyptian Arabic Tutoring Platform Database Backup\n");
            fwrite($handle, '-- Generated: '.CarbonImmutable::now('UTC')->toIso8601String()." UTC\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($handle, "SET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";\n");
            fwrite($handle, "SET AUTOCOMMIT=0;\n");
            fwrite($handle, "START TRANSACTION;\n\n");

            $driver = DB::connection()->getDriverName();

            if ($driver === 'sqlite') {
                $tables = DB::select("SELECT name as table_name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                foreach ($tables as $t) {
                    $table = $t->table_name;
                    $schema = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?", [$table]);
                    if (! empty($schema) && ! empty($schema[0]->sql)) {
                        fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
                        fwrite($handle, $schema[0]->sql.";\n\n");
                    }

                    $rows = DB::table($table)->cursor();
                    $batch = [];
                    foreach ($rows as $row) {
                        $values = array_map(function ($val) {
                            if (is_null($val)) {
                                return 'NULL';
                            }
                            if (is_numeric($val) && ! is_string($val)) {
                                return $val;
                            }

                            return DB::getPdo()->quote((string) $val);
                        }, (array) $row);

                        $batch[] = '('.implode(', ', $values).')';
                        if (count($batch) >= 100) {
                            fwrite($handle, "INSERT INTO `{$table}` VALUES \n".implode(",\n", $batch).";\n");
                            $batch = [];
                        }
                    }
                    if (! empty($batch)) {
                        fwrite($handle, "INSERT INTO `{$table}` VALUES \n".implode(",\n", $batch).";\n\n");
                    }
                }

                fwrite($handle, "COMMIT;\n");
                fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
                fclose($handle);

                return;
            }

            $tables = DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
            $dbName = DB::getDatabaseName();
            $tableKey = "Tables_in_{$dbName}";

            foreach ($tables as $tableObj) {
                $table = $tableObj->$tableKey ?? current((array) $tableObj);

                // Fetch CREATE TABLE
                $createResult = DB::select("SHOW CREATE TABLE `{$table}`");
                if (! empty($createResult)) {
                    $createSql = $createResult[0]->{'Create Table'} ?? null;
                    if ($createSql) {
                        fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
                        fwrite($handle, $createSql.";\n\n");
                    }
                }

                // Dump rows in chunks
                $rows = DB::table($table)->cursor();
                $batch = [];
                foreach ($rows as $row) {
                    $values = array_map(function ($val) {
                        if (is_null($val)) {
                            return 'NULL';
                        }
                        if (is_numeric($val) && ! is_string($val)) {
                            return $val;
                        }

                        return DB::getPdo()->quote((string) $val);
                    }, (array) $row);

                    $batch[] = '('.implode(', ', $values).')';

                    if (count($batch) >= 100) {
                        fwrite($handle, "INSERT INTO `{$table}` VALUES \n".implode(",\n", $batch).";\n");
                        $batch = [];
                    }
                }

                if (! empty($batch)) {
                    fwrite($handle, "INSERT INTO `{$table}` VALUES \n".implode(",\n", $batch).";\n\n");
                }
            }

            fwrite($handle, "COMMIT;\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($handle);
        } finally {
            if ($startedTransaction) {
                DB::commit();
            }
        }
    }

    /**
     * Add a directory to a zip archive recursively while computing file checksums.
     */
    protected function addDirectoryToZip(ZipArchive $zip, string $dirPath, string $zipPrefix, array &$fileHashes = []): void
    {
        if (! File::isDirectory($dirPath)) {
            return;
        }

        $files = File::allFiles($dirPath);
        foreach ($files as $file) {
            $relativePath = $zipPrefix.'/'.str_replace('\\', '/', $file->getRelativePathname());
            $zip->addFile($file->getRealPath(), $relativePath);
            $fileHashes[$relativePath] = [
                'sha256' => hash_file('sha256', $file->getRealPath()),
                'size_bytes' => $file->getSize(),
            ];
        }
    }

    /**
     * Resolve and validate safe destination path for an archive entry.
     *
     * @throws Exception
     */
    public function resolveSafeDestinationPath(string $targetBase, string $entryName): string
    {
        if (str_contains($entryName, '..') || str_starts_with($entryName, '/') || str_starts_with($entryName, '\\') || str_contains($entryName, "\0")) {
            throw new Exception("Unsafe archive path detected for entry: {$entryName}");
        }

        $destPath = null;
        if (str_starts_with($entryName, 'storage/private/')) {
            $subPath = substr($entryName, strlen('storage/private/'));
            $destPath = $targetBase.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $subPath);
        } elseif (str_starts_with($entryName, 'storage/public/')) {
            $subPath = substr($entryName, strlen('storage/public/'));
            $destPath = $targetBase.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $subPath);
        } elseif (str_starts_with($entryName, 'storage/resources/')) {
            $subPath = substr($entryName, strlen('storage/resources/'));
            $destPath = $targetBase.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $subPath);
        } elseif (str_starts_with($entryName, 'storage/media/')) {
            $subPath = substr($entryName, strlen('storage/media/'));
            $destPath = $targetBase.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $subPath);
        } else {
            throw new Exception("Unrecognized archive storage path prefix for entry: {$entryName}");
        }

        $normalizedTargetBase = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $targetBase), DIRECTORY_SEPARATOR);
        $normalizedDest = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $destPath);

        if (! str_starts_with($normalizedDest, $normalizedTargetBase.DIRECTORY_SEPARATOR)) {
            throw new Exception("Unsafe archive destination path for entry: {$entryName}");
        }

        return $destPath;
    }

    /**
     * Restore database and stored assets from a backup archive.
     *
     * @param  string  $zipPath  Full path to the backup zip file
     * @param  string|null  $targetExtractDir  Directory to extract assets to (defaults to storage_path('app'))
     * @param  bool  $restoreDatabase  Whether to restore the SQL database dump
     * @param  bool  $restoreFiles  Whether to restore uploaded files
     * @param  string|null  $connection  Optional database connection name to execute restoration on
     * @return array Summary of restoration results
     *
     * @throws Exception
     */
    public function restoreBackup(
        string $zipPath,
        ?string $targetExtractDir = null,
        bool $restoreDatabase = true,
        bool $restoreFiles = true,
        ?string $connection = null
    ): array {
        if (! File::exists($zipPath)) {
            throw new Exception("Backup file not found: {$zipPath}");
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new Exception("Corrupt or unreadable backup archive: {$zipPath}");
        }

        // ==========================================
        // PREFLIGHT PHASE: Validate everything in-memory BEFORE any writes occur
        // ==========================================

        // 1. Verify manifest existence and integrity
        $manifestJson = $zip->getFromName('manifest.json');
        if ($manifestJson === false) {
            $zip->close();

            throw new Exception("Incomplete backup archive: missing manifest.json in {$zipPath}");
        }

        $manifest = json_decode($manifestJson, true);
        if (! is_array($manifest)) {
            $zip->close();

            throw new Exception("Invalid or corrupt manifest.json in {$zipPath}");
        }

        if (! isset($manifest['files']) || ! is_array($manifest['files'])) {
            $zip->close();

            throw new Exception("Invalid manifest.json: missing files array in {$zipPath}");
        }

        // 2. Preflight database dump if database restoration requested
        $sql = null;
        if ($restoreDatabase) {
            $sql = $zip->getFromName('database.sql');
            if ($sql === false) {
                $zip->close();

                throw new Exception("Incomplete backup archive: missing database.sql in {$zipPath}");
            }

            if (empty($manifest['database_sha256'])) {
                $zip->close();

                throw new Exception("Incomplete manifest.json: missing database_sha256 in {$zipPath}");
            }

            $actualDbSha = hash('sha256', $sql);
            if ($actualDbSha !== $manifest['database_sha256']) {
                $zip->close();

                throw new Exception("Database dump checksum mismatch: expected {$manifest['database_sha256']}, got {$actualDbSha}");
            }
        }

        // 3. Preflight all files if file restoration requested
        $verifiedFiles = [];
        $targetBase = $targetExtractDir ?? storage_path('app');

        if ($restoreFiles) {
            // First check every entry in manifest['files'] exists in archive and matches hash
            foreach ($manifest['files'] as $entryName => $fileMeta) {
                $destPath = $this->resolveSafeDestinationPath($targetBase, $entryName);

                $content = $zip->getFromName($entryName);
                if ($content === false) {
                    $zip->close();

                    throw new Exception("Archive missing manifest entry: {$entryName}");
                }

                if (isset($fileMeta['sha256'])) {
                    $actualHash = hash('sha256', $content);
                    if ($actualHash !== $fileMeta['sha256']) {
                        $zip->close();

                        throw new Exception("File checksum mismatch for {$entryName}: expected {$fileMeta['sha256']}, got {$actualHash}");
                    }
                }

                $verifiedFiles[] = [
                    'entry' => $entryName,
                    'destPath' => $destPath,
                    'content' => $content,
                ];
            }

            // Also check that all zip entries are safe and manifested
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);

                if ($entryName === 'manifest.json' || $entryName === 'database.sql' || str_ends_with($entryName, '/')) {
                    continue;
                }

                if (str_contains($entryName, '..') || str_starts_with($entryName, '/') || str_starts_with($entryName, '\\') || str_contains($entryName, "\0")) {
                    $zip->close();

                    throw new Exception("Unsafe archive path detected: {$entryName}");
                }

                if (str_starts_with($entryName, 'storage/')) {
                    if (! isset($manifest['files'][$entryName])) {
                        $zip->close();

                        throw new Exception("Unmanifested storage file detected in archive: {$entryName}");
                    }
                }
            }
        }

        $zip->close();

        // ==========================================
        // EXECUTION PHASE: Preflight passed; now perform modifications
        // ==========================================

        // 4. Restore database if requested
        $databaseRestored = false;
        if ($restoreDatabase && $sql !== null) {
            DB::connection($connection)->unprepared($sql);
            $databaseRestored = true;
        }

        // 5. Restore verified files if requested
        $filesRestored = 0;
        if ($restoreFiles) {
            if (! File::isDirectory($targetBase)) {
                File::makeDirectory($targetBase, 0755, true);
            }

            foreach ($verifiedFiles as $file) {
                $destDir = dirname($file['destPath']);
                if (! File::isDirectory($destDir)) {
                    File::makeDirectory($destDir, 0755, true);
                }

                File::put($file['destPath'], $file['content']);
                $filesRestored++;
            }
        }

        // 6. Update settings and AuditLog
        $nowUtc = CarbonImmutable::now('UTC')->toIso8601String();
        Setting::set('last_restore_at', $nowUtc, 'system');
        Setting::set('last_restore_file', basename($zipPath), 'system');

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'backup_restored',
            'entity_type' => self::class,
            'entity_id' => 0,
            'new_data' => [
                'filename' => basename($zipPath),
                'database_restored' => $databaseRestored,
                'files_restored' => $filesRestored,
            ],
            'created_at' => now(),
        ]);

        return [
            'status' => 'success',
            'file' => basename($zipPath),
            'filename' => basename($zipPath),
            'database_restored' => $databaseRestored,
            'files_restored' => $filesRestored,
            'restored_at' => $nowUtc,
            'manifest' => $manifest,
        ];
    }

    /**
     * Prune backups older than retention days.
     */
    public function cleanOldBackups(): int
    {
        $retentionDays = (int) Setting::get('backup_retention_days', 30);
        $threshold = CarbonImmutable::now('UTC')->subDays($retentionDays);
        $deletedCount = 0;

        $files = File::files($this->backupDir);
        foreach ($files as $file) {
            if ($file->getExtension() === 'zip') {
                $lastModified = CarbonImmutable::createFromTimestamp($file->getMTime(), 'UTC');
                if ($lastModified->isBefore($threshold)) {
                    File::delete($file->getRealPath());
                    $deletedCount++;
                }
            }
        }

        // Prune offsite disk if configured
        $offsiteDisk = config('filesystems.backup_disk') ?: Setting::get('backup_offsite_disk');
        if ($offsiteDisk && $offsiteDisk !== 'local' && config("filesystems.disks.{$offsiteDisk}")) {
            try {
                $offsiteFiles = Storage::disk($offsiteDisk)->files('backups');
                foreach ($offsiteFiles as $offsiteFile) {
                    if (str_ends_with($offsiteFile, '.zip')) {
                        $mtime = Storage::disk($offsiteDisk)->lastModified($offsiteFile);
                        if (CarbonImmutable::createFromTimestamp($mtime, 'UTC')->isBefore($threshold)) {
                            Storage::disk($offsiteDisk)->delete($offsiteFile);
                        }
                    }
                }
            } catch (Throwable $offsiteEx) {
                Log::warning('Failed to prune old backups on offsite disk: '.$offsiteEx->getMessage());
            }
        }

        return $deletedCount;
    }

    /**
     * Get list of backups sorted newest first.
     */
    public function getBackups(): array
    {
        if (! File::isDirectory($this->backupDir)) {
            return [];
        }

        $files = File::files($this->backupDir);
        $backups = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'zip') {
                $backups[] = [
                    'filename' => $file->getFilename(),
                    'size_bytes' => $file->getSize(),
                    'size_formatted' => $this->formatBytes($file->getSize()),
                    'created_at' => CarbonImmutable::createFromTimestamp($file->getMTime(), 'UTC'),
                ];
            }
        }

        usort($backups, fn ($a, $b) => $b['created_at']->timestamp <=> $a['created_at']->timestamp);

        return $backups;
    }

    /**
     * Resolve absolute path for a backup file securely preventing directory traversal.
     */
    public function getBackupPath(string $filename): ?string
    {
        $cleanFilename = basename($filename);
        $path = $this->backupDir.DIRECTORY_SEPARATOR.$cleanFilename;

        if (File::exists($path) && str_ends_with($cleanFilename, '.zip')) {
            return $path;
        }

        return null;
    }

    /**
     * Delete a specific backup file.
     */
    public function deleteBackup(string $filename): bool
    {
        $path = $this->getBackupPath($filename);
        if ($path && File::exists($path)) {
            $deleted = File::delete($path);
            if ($deleted) {
                AuditLog::create([
                    'administrator_id' => Auth::id(),
                    'action' => 'backup_deleted',
                    'entity_type' => self::class,
                    'entity_id' => 0,
                    'new_data' => ['filename' => basename($filename)],
                    'created_at' => now(),
                ]);
            }

            return $deleted;
        }

        return false;
    }

    protected function findMysqldumpBinary(): ?string
    {
        $candidates = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\Program Files\\MariaDB 10.4\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
        ];

        foreach ($candidates as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }

    public function categorizeOffsiteError(Throwable $e): string
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'accessdenied')
            || str_contains($message, 'forbidden')
            || str_contains($message, '403')
            || str_contains($message, 'invalidaccesskey')
            || str_contains($message, 'signaturedoesnotmatch')
            || str_contains($message, 'credential')
            || str_contains($message, 'unauthorized')
            || str_contains($message, '401')
        ) {
            return 's3_authentication_failed';
        }

        if (str_contains($message, 'curl')
            || str_contains($message, 'connect')
            || str_contains($message, 'timeout')
            || str_contains($message, 'timed out')
            || str_contains($message, 'resolve host')
            || str_contains($message, 'unreachable')
            || str_contains($message, 'network')
        ) {
            return 's3_connectivity_unavailable';
        }

        return 's3_replication_failed';
    }
}
