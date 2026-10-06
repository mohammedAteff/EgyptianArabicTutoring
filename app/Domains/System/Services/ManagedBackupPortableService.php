<?php

namespace App\Domains\System\Services;

use App\Domains\Resources\Models\ResourceCategory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class ManagedBackupPortableService
{
    public function __construct(private DevelopmentDataCatalog $catalog, private DevelopmentDataSanitizer $sanitizer) {}

    /** @param array<string, mixed> $item
     * @return array<string, mixed> */
    public function export(array $item): array
    {
        $path = Storage::disk('managed_backups')->path($item['filename']);
        if (hash_file('sha256', $path) !== $item['sha256']) {
            throw ValidationException::withMessages(['backups' => 'A managed snapshot changed after preview.']);
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['backups' => 'A selected managed snapshot is unavailable.']);
        }
        try {
            if ($item['portable']) {
                $contents = $zip->getFromName('snapshot.json');
                $payload = is_string($contents) ? json_decode($contents, true) : null;
                if (! is_array($payload)) {
                    throw ValidationException::withMessages(['backups' => 'The managed portable snapshot is invalid.']);
                }

                return $this->validate($payload);
            }
            $sql = $zip->getFromName('database.sql');
            if (! is_string($sql) || strlen($sql) > (int) config('development_tools.max_expanded_bytes')) {
                throw ValidationException::withMessages(['backups' => 'The managed snapshot exceeds the portable conversion limit.']);
            }
            $rows = $this->nativeRows($sql);
            $files = [];
            $manifestJson = $zip->getFromName('manifest.json');
            $manifest = is_string($manifestJson) ? json_decode($manifestJson, true) : [];
            foreach ($manifest['files'] ?? [] as $entry => $metadata) {
                if (! preg_match('~^storage/private/((?:resources|lesson-materials)/[A-Za-z0-9_.-]+)$~D', $entry, $match)) {
                    continue;
                }
                $contents = $zip->getFromName($entry);
                if (! is_string($contents) || ! hash_equals((string) $metadata['sha256'], hash('sha256', $contents))) {
                    throw ValidationException::withMessages(['backups' => 'A snapshot private-file hash is invalid.']);
                }
                $files[] = ['path' => $match[1], 'sha256' => hash('sha256', $contents), 'payload' => base64_encode($contents)];
            }

            return $this->validate(['version' => 1, 'source_sha256' => $item['sha256'], 'source_created_at_utc' => $manifest['created_at_utc'] ?? null,
                'rows' => $rows, 'files' => $files, 'excluded' => ['administrators', 'users', 'settings', 'sessions', 'authentication_tokens', 'telegram_configuration', 'unrelated_content_files']]);
        } finally {
            $zip->close();
        }
    }

    /** Validate portable snapshot contents before export or managed-storage persistence.
     * @param array<string, mixed> $payload
     * @return array<string, mixed> */
    public function validate(array $payload): array
    {
        if (($payload['version'] ?? null) !== 1 || ! is_array($payload['rows'] ?? null) || ! is_array($payload['files'] ?? null)
            || array_diff(array_keys($payload), ['version', 'source_sha256', 'source_created_at_utc', 'rows', 'files', 'excluded']) !== []) {
            throw ValidationException::withMessages(['archive' => 'The managed portable snapshot format is invalid.']);
        }
        $schema = app(DevelopmentDataSchema::class)->read();
        $count = 0;
        foreach ($payload['rows'] as $table => $records) {
            if (! in_array($table, $this->catalog->tables(), true) || ! is_array($records) || ! array_is_list($records)) {
                throw ValidationException::withMessages(['archive' => 'A managed portable snapshot contains a prohibited table.']);
            }
            $count += count($records);
            foreach ($records as $row) {
                if (! is_array($row) || $this->sanitizer->row($row, $table) !== $row
                    || array_diff(array_keys($row), array_keys($schema[$table]['columns'])) !== []
                    || array_filter($row, fn (mixed $value): bool => $value !== null && ! is_scalar($value)) !== []) {
                    throw ValidationException::withMessages(['archive' => 'A managed portable snapshot contains unsupported columns or credentials.']);
                }
                if (in_array($table, ['content_revisions', 'entity_translation_revisions'], true)
                    && ! in_array($row[$table === 'content_revisions' ? 'revisable_type' : 'entity_type'] ?? null,
                        ['resource', 'resource_category', \App\Domains\Resources\Models\Resource::class, ResourceCategory::class], true)) {
                    throw ValidationException::withMessages(['archive' => 'A managed portable snapshot contains unrelated content.']);
                }
            }
        }
        $total = 0;
        foreach ($payload['files'] as $file) {
            if (! is_array($file) || ! is_string($file['path'] ?? null)
                || ! preg_match('~^(resources/[A-Za-z0-9_.-]+\.(pdf|zip|doc|docx|mp3|wav|m4a)|lesson-materials/[A-Za-z0-9_.-]+\.pdf)$~D', $file['path'])
                || str_contains($file['path'], '..') || ! is_string($file['payload'] ?? null) || ! is_string($file['sha256'] ?? null)) {
                throw ValidationException::withMessages(['archive' => 'A managed portable private-file path is invalid.']);
            }
            $bytes = base64_decode($file['payload'], true);
            if (! is_string($bytes) || ! hash_equals($file['sha256'], hash('sha256', $bytes))) {
                throw ValidationException::withMessages(['archive' => 'A managed portable private-file hash is invalid.']);
            }
            $total += strlen($bytes);
        }
        if ($count > (int) config('development_tools.max_rows') || $total > (int) config('development_tools.max_expanded_bytes')) {
            throw ValidationException::withMessages(['archive' => 'The managed portable snapshot exceeds its bounds.']);
        }

        return $payload;
    }

    /** Decode the application's native dump without executing SQL or extracting paths.
     * @return array<string, list<array<string, mixed>>> */
    public function nativeRows(string $sql): array
    {
        $columns = [];
        $rows = [];
        foreach ($this->statements($sql) as $statement) {
            while (preg_match('/\A\s*(?:--[^\r\n]*(?:\r?\n|$)|\/\*.*?\*\/)\s*/s', $statement, $comment)) {
                $statement = substr($statement, strlen($comment[0]));
            }
            if (preg_match('/CREATE TABLE \\x60([a-z0-9_]+)\\x60 \\(/', $statement, $table)) {
                if (in_array($table[1], $this->catalog->tables(), true)) {
                    preg_match_all('/^\\s*\\x60([a-z0-9_]+)\\x60\\s+/m', $statement, $names);
                    $columns[$table[1]] = $names[1];
                }

                continue;
            }
            if (! preg_match('/^INSERT INTO \\x60([a-z0-9_]+)\\x60 VALUES\\s+(.+)$/s', trim($statement), $insert)) {
                if (preg_match('/^INSERT(?:\s+IGNORE)?\s+INTO\s+\\x60([a-z0-9_]+)\\x60/i', trim($statement), $unsupported)
                    && in_array($unsupported[1], $this->catalog->tables(), true)) {
                    throw ValidationException::withMessages(['backups' => 'The native snapshot INSERT format requires reviewed conversion. It was not silently omitted.']);
                }

                continue;
            }
            $table = $insert[1];
            if (! in_array($table, $this->catalog->tables(), true)) {
                continue;
            }
            if (empty($columns[$table])) {
                throw ValidationException::withMessages(['backups' => 'This legacy snapshot format cannot be safely converted. Preserve it for reviewed system recovery.']);
            }
            foreach ($this->values($insert[2]) as $values) {
                if (count($values) !== count($columns[$table])) {
                    throw ValidationException::withMessages(['backups' => 'The native snapshot column/value inventory is incompatible.']);
                }
                $row = $this->sanitizer->row(array_combine($columns[$table], $values), $table);
                if ($table === 'visitors') {
                    $row['student_id'] = null;
                }
                if (in_array($table, ['content_revisions', 'entity_translation_revisions'], true)) {
                    $type = $row[$table === 'content_revisions' ? 'revisable_type' : 'entity_type'] ?? null;
                    if (! in_array($type, ['resource', 'resource_category', \App\Domains\Resources\Models\Resource::class, ResourceCategory::class], true)) {
                        continue;
                    }
                }
                $rows[$table][] = $row;
                if (array_sum(array_map('count', $rows)) > (int) config('development_tools.max_rows')) {
                    throw ValidationException::withMessages(['backups' => 'The snapshot exceeds the bounded portable row limit.']);
                }
            }
        }

        return $rows;
    }

    /** @return \Generator<int, string> */
    private function statements(string $sql): \Generator
    {
        $buffer = '';
        $quoted = false;
        $escaped = false;
        for ($index = 0, $length = strlen($sql); $index < $length; $index++) {
            $char = $sql[$index];
            if ($escaped) {
                $escaped = false;
            } elseif ($quoted && $char === '\\') {
                $escaped = true;
            } elseif ($char === "'") {
                $quoted = ! $quoted;
            }
            if ($char === ';' && ! $quoted) {
                yield trim($buffer);
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }
        if ($quoted) {
            throw ValidationException::withMessages(['backups' => 'The native snapshot has an unterminated SQL string.']);
        }
    }

    /** @return list<list<string|null>> */
    private function values(string $input): array
    {
        $rows = [];
        $row = [];
        $value = '';
        $quoted = false;
        $stringValue = false;
        $inside = false;
        for ($index = 0, $length = strlen($input); $index < $length; $index++) {
            $char = $input[$index];
            if ($quoted) {
                if ($char === '\\') {
                    $next = $input[++$index] ?? '';
                    $value .= match ($next) {
                        'n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0", 'Z' => chr(26), default => $next
                    };
                } elseif ($char === "'") {
                    if (($input[$index + 1] ?? '') === "'") {
                        $value .= "'";
                        $index++;
                    } else {
                        $quoted = false;
                    }
                } else {
                    $value .= $char;
                }

                continue;
            }
            if ($char === "'") {
                $quoted = true;
                $stringValue = true;
            } elseif ($char === '(' && ! $inside) {
                $inside = true;
            } elseif (($char === ',' || $char === ')') && $inside) {
                $row[] = ! $stringValue && strtoupper(trim($value)) === 'NULL' ? null : ($stringValue ? $value : trim($value));
                $value = '';
                $stringValue = false;
                if ($char === ')') {
                    $rows[] = $row;
                    $row = [];
                    $inside = false;
                }
            } elseif ($inside && ! ctype_space($char)) {
                $value .= $char;
            } elseif ($inside && $value !== '') {
                $value .= $char;
            }
        }
        if ($inside || $quoted) {
            throw ValidationException::withMessages(['backups' => 'The native snapshot values are incomplete.']);
        }

        return $rows;
    }
}
