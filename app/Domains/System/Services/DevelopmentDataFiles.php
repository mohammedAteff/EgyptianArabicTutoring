<?php

namespace App\Domains\System\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DevelopmentDataFiles
{
    public function disk(): FilesystemAdapter
    {
        return Storage::disk((string) config('development_tools.disk'));
    }

    public function operationPath(string $identity, string $kind): string
    {
        if (! preg_match('/^[a-f0-9-]{16,64}$/', $identity) || ! in_array($kind, ['exports', 'imports', 'recovery', 'quarantine'], true)) {
            throw ValidationException::withMessages(['archive' => 'Invalid protected archive identity.']);
        }

        return (string) config('development_tools.directory').'/'.$kind.'/'.$identity;
    }

    public function privatePath(string $path): string
    {
        if (! preg_match('~^(resources/[A-Za-z0-9_-]+\.(pdf|zip|doc|docx|mp3|wav|m4a)|lesson-materials/[A-Za-z0-9_-]+\.pdf)$~D', $path)) {
            throw ValidationException::withMessages(['files' => 'An unrecognized private file path requires review.']);
        }
        $disk = Storage::disk('local');
        $absolute = $disk->path($path);
        $root = realpath($disk->path(''));
        $real = realpath($absolute);
        $comparisonRoot = str_replace('\\', '/', (string) $root).'/';
        $comparisonReal = str_replace('\\', '/', (string) $real);
        if ($root === false || $real === false || ! str_starts_with($comparisonReal, $comparisonRoot) || is_link($absolute) || is_link(dirname($absolute))) {
            throw ValidationException::withMessages(['files' => 'A private file is missing or resolves outside its approved storage root.']);
        }

        return $absolute;
    }

    /** @param array<string, mixed> $plan
     * @return list<array{table: string, id: string, column: string, path: string, sha256: string, size: int, extension: string, entry: string}> */
    public function inventory(array $plan): array
    {
        if (! $plan['files']) {
            return [];
        }
        $files = [];
        foreach (['lesson_materials' => 'path', 'resources' => 'file_path'] as $table => $column) {
            foreach ($plan['rows'][$table] ?? [] as $id => $row) {
                $path = $row[$column] ?? null;
                if (! is_string($path) || $path === '') {
                    continue;
                }
                if ($table === 'lesson_materials' && ($row['kind'] !== 'private_file' || $row['disk'] !== 'local')) {
                    continue;
                }
                $absolute = $this->privatePath($path);
                $hash = hash_file('sha256', $absolute);
                if (! is_string($hash)) {
                    throw ValidationException::withMessages(['files' => 'A private file could not be verified.']);
                }
                $files[] = ['table' => $table, 'id' => (string) $id, 'column' => $column, 'path' => $path, 'sha256' => $hash,
                    'size' => filesize($absolute), 'extension' => strtolower(pathinfo($path, PATHINFO_EXTENSION)), 'entry' => 'files/'.$hash.'.bin'];
            }
        }

        return $files;
    }

    /** @param list<array<string, mixed>> $files
     * @param array<string, mixed> $plan
     * @return list<array<string, mixed>> */
    public function deletable(array $files, array $plan): array
    {
        $paths = array_column($files, 'path');
        $shared = DB::table('resources')->whereIn('file_path', $paths)->whereNotIn('id', array_keys($plan['rows']['resources'] ?? []))->pluck('file_path')
            ->merge(DB::table('lesson_materials')->where('disk', 'local')->whereIn('path', $paths)->whereNotIn('id', array_keys($plan['rows']['lesson_materials'] ?? []))->pluck('path'))->all();

        return array_values(array_filter($files, fn (array $file): bool => ! in_array($file['path'], $shared, true)));
    }

    /** @param list<array<string, mixed>> $files
     * @return array<string, string> */
    public function quarantine(array $files, string $identity): array
    {
        $copies = [];
        foreach ($files as $file) {
            $path = $this->operationPath($identity, 'quarantine').'/'.$file['sha256'].'.bin';
            $contents = Storage::disk('local')->get($file['path']);
            if (! is_string($contents) || hash('sha256', $contents) !== $file['sha256'] || ! $this->disk()->put($path, $contents, 'private')) {
                throw ValidationException::withMessages(['files' => 'Private-file compensation could not be prepared. No reset was performed.']);
            }
            $copies[$file['path']] = $path;
        }

        return $copies;
    }

    /** @param array<string, string> $copies */
    public function remove(array $copies): void
    {
        foreach ($copies as $source => $copy) {
            $this->privatePath($source);
            if (! Storage::disk('local')->delete($source) || Storage::disk('local')->exists($source)) {
                throw ValidationException::withMessages(['files' => 'A private file could not be removed; the operation will be rolled back.']);
            }
        }
    }

    /** @param array<string, string> $copies */
    public function compensate(array $copies): void
    {
        foreach ($copies as $source => $copy) {
            $contents = $this->disk()->get($copy);
            if (! is_string($contents) || ! Storage::disk('local')->put($source, $contents, 'private')) {
                throw ValidationException::withMessages(['files' => 'File compensation needs operator recovery from the protected pre-operation snapshot.']);
            }
        }
    }

    /** @param array<string, string> $copies */
    public function discard(array $copies): void
    {
        foreach ($copies as $copy) {
            $this->disk()->delete($copy);
        }
    }
}
