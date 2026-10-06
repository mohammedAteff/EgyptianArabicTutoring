<?php

namespace App\Domains\System\Services;

use App\Domains\Audit\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class ManagedBackupCatalog
{
    /** @return list<array{filename: string, sha256: string, size: int, portable: bool}> */
    public function inventory(): array
    {
        $disk = Storage::disk('managed_backups');
        $proof = DB::table('audit_logs')->whereIn('action', ['backup_created', 'development_backup_imported'])->pluck('new_data')
            ->map(function (mixed $data): ?string {
                $row = is_string($data) ? json_decode($data, true) : null;

                return is_array($row) && is_string($row['filename'] ?? null) ? $row['filename'] : null;
            })->filter()->all();
        $items = [];
        foreach ($disk->files() as $filename) {
            $safeFilename = AuditLog::scrubPayload(['filename' => $filename])['filename'];
            if (! preg_match('/^backup-[a-zA-Z0-9_-]+\\.zip$/D', $filename)
                || (! in_array($filename, $proof, true) && ! in_array($safeFilename, $proof, true))) {
                continue;
            }
            $path = $disk->path($filename);
            if (is_link($path)) {
                continue;
            }
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                continue;
            }
            try {
                $expanded = 0;
                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $expanded += (int) ($zip->statIndex($index)['size'] ?? 0);
                }
                if ($expanded > (int) config('development_tools.max_expanded_bytes') || $zip->numFiles > (int) config('development_tools.max_entries')) {
                    continue;
                }
                $json = $zip->getFromName('manifest.json');
                $manifest = is_string($json) ? json_decode($json, true) : null;
                if (! is_array($manifest)) {
                    continue;
                }
                if (! empty($manifest['protected']) || ! empty($manifest['protected_system_recovery_version'])) {
                    continue;
                }
                if (preg_match('/protected|recovery|deploy|emergency|manual/i', (string) ($manifest['type'] ?? ''))) {
                    continue;
                }
                $portable = ($manifest['managed_portable_version'] ?? null) === 1;
                if ($portable) {
                    $payload = $zip->getFromName('snapshot.json');
                    if (! is_string($payload) || ! is_string($manifest['snapshot_sha256'] ?? null)
                        || ! hash_equals($manifest['snapshot_sha256'], hash('sha256', $payload))) {
                        continue;
                    }
                } else {
                    $sql = $zip->getFromName('database.sql');
                    if (! is_string($sql) || ! is_string($manifest['database_sha256'] ?? null)
                        || ! hash_equals($manifest['database_sha256'], hash('sha256', $sql))
                        || (($manifest['type'] ?? null) !== 'full' && ! Str::isUuid($manifest['managed_snapshot_id'] ?? ''))) {
                        continue;
                    }
                }
                $hash = hash_file('sha256', $path);
                $size = filesize($path);
                if (is_string($hash) && is_int($size)) {
                    $items[] = ['filename' => $filename, 'sha256' => $hash, 'size' => $size, 'portable' => $portable];
                }
            } finally {
                $zip->close();
            }
        }
        usort($items, fn (array $first, array $second): int => strcmp($first['filename'], $second['filename']));

        return $items;
    }

    /** @param list<string> $filenames
     * @return list<array{filename: string, sha256: string, size: int, portable: bool}> */
    public function selected(array $filenames): array
    {
        $items = $this->inventory();
        if (array_diff($filenames, array_column($items, 'filename')) !== []) {
            throw ValidationException::withMessages(['backups' => 'A selected snapshot is not positively identified as managed. Nothing was reset or exported.']);
        }

        return $filenames === [] ? $items : array_values(array_filter($items, fn (array $item): bool => in_array($item['filename'], $filenames, true)));
    }
}
