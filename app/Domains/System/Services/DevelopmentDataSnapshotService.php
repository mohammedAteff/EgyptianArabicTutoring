<?php

namespace App\Domains\System\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DevelopmentDataSnapshotService
{
    public function __construct(private BackupService $backups, private DevelopmentDataFiles $files, private ManagedBackupCatalog $managed) {}

    /** Protected system recovery is never included in the downloadable portable export.
     * @param array<string, mixed> $plan
     * @return array{identity: string, database_sha256: string, file_count: int, managed_snapshot_count: int} */
    public function create(array $plan, string $identity, bool $includeManaged): array
    {
        $directory = $this->files->operationPath($identity, 'recovery');
        $disk = $this->files->disk();
        if (! $disk->makeDirectory($directory)) {
            throw ValidationException::withMessages(['snapshot' => 'Protected recovery storage is unavailable. No reset was performed.']);
        }
        $sqlPath = $directory.'/database.sql';
        try {
            $this->backups->dumpDatabase($disk->path($sqlPath));
            $sqlHash = hash_file('sha256', $disk->path($sqlPath));
            if (! is_string($sqlHash) || $disk->size($sqlPath) === 0) {
                throw ValidationException::withMessages(['snapshot' => 'The protected database snapshot could not be verified.']);
            }
            $inventory = $this->files->inventory(array_replace($plan, ['files' => true]));
            foreach ($inventory as $file) {
                $target = $directory.'/files/'.$file['sha256'].'.bin';
                $contents = Storage::disk('local')->get($file['path']);
                if (! is_string($contents) || hash('sha256', $contents) !== $file['sha256'] || ! $disk->put($target, $contents, 'private')) {
                    throw ValidationException::withMessages(['snapshot' => 'The protected private-file snapshot could not be verified.']);
                }
            }
            $managed = $includeManaged ? $this->managed->inventory() : [];
            foreach ($managed as $item) {
                $contents = Storage::disk('managed_backups')->get($item['filename']);
                if (! is_string($contents) || hash('sha256', $contents) !== $item['sha256'] || ! $disk->put($directory.'/managed/'.$item['sha256'].'.zip', $contents, 'private')) {
                    throw ValidationException::withMessages(['snapshot' => 'A managed snapshot could not be protected outside the reset set.']);
                }
            }
            $manifest = ['protected_system_recovery_version' => 1, 'identity' => $identity, 'created_at_utc' => now('UTC')->toIso8601String(),
                'database_sha256' => $sqlHash, 'files' => $inventory, 'managed_snapshots' => $managed];
            $triggers = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)
                ? DB::select('SELECT TRIGGER_NAME, ACTION_TIMING, EVENT_MANIPULATION, EVENT_OBJECT_TABLE, ACTION_STATEMENT, SQL_MODE FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = ? ORDER BY TRIGGER_NAME', [DB::connection()->getDatabaseName()]) : [];
            $schema = json_encode(['tables' => app(DevelopmentDataSchema::class)->read(), 'triggers' => $triggers], JSON_THROW_ON_ERROR);
            if (! $disk->put($directory.'/schema.json', $schema, 'private')) {
                throw ValidationException::withMessages(['snapshot' => 'The protected constraint/trigger inventory could not be saved.']);
            }
            $manifest['schema_sha256'] = hash('sha256', $schema);
            $manifest['trigger_count'] = count($triggers);
            if (! $disk->put($directory.'/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR), 'private')) {
                throw ValidationException::withMessages(['snapshot' => 'The protected snapshot manifest could not be saved.']);
            }

            return ['identity' => $identity, 'database_sha256' => $sqlHash, 'file_count' => count($inventory), 'managed_snapshot_count' => count($managed)];
        } catch (\Throwable) {
            throw ValidationException::withMessages(['snapshot' => 'Protected pre-operation snapshot failed. No reset was performed; review protected recovery storage.']);
        }
    }
}
