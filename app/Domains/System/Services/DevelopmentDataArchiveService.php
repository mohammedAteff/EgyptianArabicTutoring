<?php

namespace App\Domains\System\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Analytics\Services\AnalyticsLifecycle;
use App\Domains\Timezone\Services\TimezoneService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class DevelopmentDataArchiveService
{
    public function __construct(private DevelopmentToolsAccess $access, private DevelopmentDataCatalog $catalog,
        private DevelopmentDataGraph $graph, private DevelopmentDataFiles $files, private DevelopmentDataSanitizer $sanitizer,
        private ManagedBackupPortableService $portable) {}

    /** @param array<string, mixed> $plan
     * @param list<array<string, mixed>> $snapshots
     * @return array{path: string, sha256: string, identity: string, manifest: array<string, mixed>} */
    public function create(Administrator $actor, array $plan, array $snapshots = []): array
    {
        $this->access->authorize($actor);
        $identity = (string) Str::uuid();
        $path = $this->files->operationPath($identity, 'exports').'.zip';
        $this->files->disk()->makeDirectory(dirname($path));
        $zip = new ZipArchive;
        if ($zip->open($this->files->disk()->path($path), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw ValidationException::withMessages(['archive' => 'The private export archive could not be created.']);
        }
        $entries = [];
        $counts = [];
        $schema = [];
        $inventory = $this->files->inventory($plan);
        try {
            foreach ($plan['rows'] as $table => $records) {
                if (! in_array($table, $this->catalog->tables(), true)) {
                    continue;
                }
                $rows = array_values(array_map(fn (array $row): array => $this->sanitizer->row($row, $table), $records));
                if ($table === 'visitors') {
                    $rows = array_map(fn (array $row): array => array_replace($row, ['student_id' => null]), $rows);
                }
                $contents = json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                $this->add($zip, $entries, 'data/'.$table.'.json', $contents);
                $counts[$table] = count($rows);
                $schema[$table] = $this->schemaHash($table);
            }
            foreach ($inventory as $file) {
                $contents = Storage::disk('local')->get($file['path']);
                if (! is_string($contents) || ! hash_equals($file['sha256'], hash('sha256', $contents))) {
                    throw ValidationException::withMessages(['files' => 'A private file changed while exporting. Rebuild the preview.']);
                }
                $this->add($zip, $entries, $file['entry'], $contents);
            }
            $snapshotInventory = [];
            foreach ($snapshots as $snapshot) {
                $payload = $this->portable->export($snapshot);
                $contents = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                $entry = 'snapshots/'.$snapshot['sha256'].'.json';
                $this->add($zip, $entries, $entry, $contents);
                $snapshotInventory[] = ['entry' => $entry, 'source_sha256' => $snapshot['sha256'], 'source_filename' => $snapshot['filename']];
            }
            $included = $this->catalog->modulesFor(array_keys($counts));
            if ($snapshots !== []) {
                $included[] = 'backups.snapshots';
            }
            $result = Process::path(base_path())->timeout(3)->run(['git', 'rev-parse', 'HEAD']);
            $sha = trim($result->output());
            $manifest = ['format_version' => DevelopmentDataCatalog::VERSION, 'compatibility_version' => DevelopmentDataCatalog::VERSION,
                'archive_id' => $identity, 'application_version' => app()->version(), 'application_sha' => preg_match('/^[a-f0-9]{40}$/D', $sha) ? $sha : null,
                'created_at_utc' => now('UTC')->toIso8601String(), 'business_timezone' => app(TimezoneService::class)->getBusinessTimezone(),
                'included_modules' => $included, 'excluded_modules' => array_values(array_diff(array_keys(DevelopmentDataCatalog::MODULES), $included)),
                'row_counts' => $counts, 'schema' => $schema, 'file_inventory' => $inventory, 'managed_snapshots' => $snapshotInventory,
                'entries' => $entries, 'dependencies' => $this->dependencies($plan),
                'excluded_credentials' => ['staff_passwords', 'totp_secrets', 'recovery_codes', 'app_key', 'telegram_credentials', 'authentication_sessions_tokens'],
                'restore_policy' => 'restore_missing_skip_identical'];
            $manifest['analytics_lifecycle'] = ['booking_floor' => app(AnalyticsLifecycle::class)->bookingFloor()];
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $zip->close();
            if ($this->files->disk()->size($path) > (int) config('development_tools.max_archive_bytes')) {
                throw ValidationException::withMessages(['archive' => 'The export exceeds the archive-size limit. Select fewer modules or snapshots.']);
            }
            $hash = hash_file('sha256', $this->files->disk()->path($path));
            if (! is_string($hash)) {
                throw ValidationException::withMessages(['archive' => 'The completed archive could not be verified.']);
            }

            return ['path' => $path, 'sha256' => $hash, 'identity' => $identity, 'manifest' => $manifest];
        } catch (\Throwable $exception) {
            if ($zip->status === ZipArchive::ER_OK) {
                try {
                    $zip->close();
                } catch (\Throwable) {
                }
            }
            $this->files->disk()->delete($path);
            throw $exception;
        }
    }

    public function schemaHash(string $table): string
    {
        $meta = $this->graph->schema()[$table];
        $columns = array_map(fn (array $column): array => array_intersect_key($column, array_flip(['name', 'type_name', 'type', 'nullable', 'auto_increment', 'generated', 'generation'])), $meta['columns']);
        $foreign = array_map(fn (array $key): array => array_intersect_key($key, array_flip(['columns', 'foreign_table', 'foreign_columns', 'on_update', 'on_delete'])), $meta['foreign_keys']);

        return hash('sha256', json_encode([$columns, $foreign, $meta['primary']], JSON_THROW_ON_ERROR));
    }

    /** @return array{manifest: array<string, mixed>, rows: array<string, list<array<string, mixed>>>, payloads: array<string, string>, sha256: string} */
    public function read(Administrator $actor, string $path): array
    {
        $this->access->authorize($actor);
        $zip = new ZipArchive;
        $absolute = $this->files->disk()->path($path);
        if (! $this->files->disk()->exists($path) || $this->files->disk()->size($path) > (int) config('development_tools.max_archive_bytes')
            || $zip->open($absolute) !== true) {
            throw ValidationException::withMessages(['archive' => 'The archive is invalid or exceeds the upload limit.']);
        }
        try {
            $names = [];
            $expanded = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = $stat['name'] ?? '';
                if (! preg_match('~^(manifest\.json|data/[a-z][a-z0-9_]*\.json|files/[a-f0-9]{64}\.bin|snapshots/[a-f0-9]{64}\.json)$~D', $name) || isset($names[$name])) {
                    throw ValidationException::withMessages(['archive' => 'Archive traversal, duplicate or unrecognized entries are prohibited.']);
                }
                $system = 0;
                $attributes = 0;
                $zip->getExternalAttributesIndex($index, $system, $attributes);
                if (($attributes >> 16 & 0170000) === 0120000) {
                    throw ValidationException::withMessages(['archive' => 'Archive symlinks are prohibited.']);
                }
                $expanded += (int) ($stat['size'] ?? 0);
                $names[$name] = true;
            }
            if ($zip->numFiles > (int) config('development_tools.max_entries') || $expanded > (int) config('development_tools.max_expanded_bytes')) {
                throw ValidationException::withMessages(['archive' => 'The archive exceeds the entry or expanded-size limit.']);
            }
            $json = $zip->getFromName('manifest.json');
            $manifest = is_string($json) ? json_decode($json, true, 64, JSON_THROW_ON_ERROR) : null;
            if (! is_array($manifest) || ($manifest['format_version'] ?? null) !== 1 || ($manifest['compatibility_version'] ?? null) !== 1
                || ! Str::isUuid($manifest['archive_id'] ?? '') || ! is_array($manifest['entries'] ?? null)
                || ! is_array($manifest['row_counts'] ?? null) || ! is_array($manifest['included_modules'] ?? null)
                || array_diff($manifest['included_modules'], array_keys(DevelopmentDataCatalog::MODULES)) !== []) {
                throw ValidationException::withMessages(['archive' => 'The manifest or archive compatibility version is invalid.']);
            }
            if (array_diff(array_keys($names), ['manifest.json', ...array_keys($manifest['entries'])]) !== []
                || array_diff(array_keys($manifest['entries']), array_keys($names)) !== []) {
                throw ValidationException::withMessages(['archive' => 'Every archive payload must have exactly one manifested hash.']);
            }
            $payloads = [];
            foreach ($manifest['entries'] as $entry => $metadata) {
                $contents = $zip->getFromName($entry);
                if (! is_string($contents) || ! is_array($metadata) || ! is_string($metadata['sha256'] ?? null)
                    || ! hash_equals($metadata['sha256'], hash('sha256', $contents)) || strlen($contents) !== ($metadata['size'] ?? null)) {
                    throw ValidationException::withMessages(['archive' => 'An archive payload hash or size does not match its manifest.']);
                }
                $payloads[$entry] = $contents;
            }
            $rows = [];
            $dataEntries = array_values(array_filter(array_keys($manifest['entries']), fn (string $entry): bool => str_starts_with($entry, 'data/')));
            $expectedEntries = array_map(fn (string $table): string => 'data/'.$table.'.json', array_keys($manifest['row_counts']));
            if (array_diff($dataEntries, $expectedEntries) !== [] || array_diff($expectedEntries, $dataEntries) !== []) {
                throw ValidationException::withMessages(['archive' => 'Every database payload must have a supported row inventory.']);
            }
            foreach ($manifest['row_counts'] as $table => $count) {
                if (! in_array($table, $this->catalog->tables(), true) || ($manifest['schema'][$table] ?? null) !== $this->schemaHash($table)) {
                    throw ValidationException::withMessages(['archive' => 'The archive schema is incompatible: '.$table.'.']);
                }
                $records = json_decode($payloads['data/'.$table.'.json'] ?? '', true, 64, JSON_THROW_ON_ERROR);
                if (! is_array($records) || ! array_is_list($records) || count($records) !== $count) {
                    throw ValidationException::withMessages(['archive' => 'The row inventory does not match its manifest.']);
                }
                foreach ($records as $row) {
                    if (! is_array($row) || array_diff(array_keys($row), array_keys($this->graph->schema()[$table]['columns'])) !== []
                        || $row !== $this->sanitizer->row($row, $table)) {
                        throw ValidationException::withMessages(['archive' => 'Archive rows contain unknown columns or prohibited credential fields.']);
                    }
                    foreach ($row as $value) {
                        if ($value !== null && ! is_scalar($value)) {
                            throw ValidationException::withMessages(['archive' => 'Database cells must be scalar values.']);
                        }
                    }
                    if (! isset($row['id']) || filter_var($row['id'], FILTER_VALIDATE_INT) === false || (int) $row['id'] < 1) {
                        throw ValidationException::withMessages(['archive' => 'An archive record has an invalid stable identity.']);
                    }
                }
                if (count(array_unique(array_column($records, 'id'))) !== count($records)) {
                    throw ValidationException::withMessages(['archive' => 'An archive repeats a database identity.']);
                }
                $rows[$table] = $records;
            }
            if (array_sum(array_map('count', $rows)) > (int) config('development_tools.max_rows')) {
                throw ValidationException::withMessages(['archive' => 'The archive exceeds the restore row limit.']);
            }
            $derivedModules = $this->catalog->modulesFor(array_keys($rows));
            if (! empty($manifest['managed_snapshots'])) {
                $derivedModules[] = 'backups.snapshots';
            }
            if (array_diff($derivedModules, $manifest['included_modules']) !== [] || array_diff($manifest['included_modules'], $derivedModules) !== []) {
                throw ValidationException::withMessages(['archive' => 'The module inventory differs from the payload inventory.']);
            }
            foreach ($manifest['managed_snapshots'] ?? [] as $snapshot) {
                $entry = $snapshot['entry'] ?? '';
                if (! is_string($entry) || ! isset($payloads[$entry]) || ! str_starts_with($entry, 'snapshots/')) {
                    throw ValidationException::withMessages(['archive' => 'A managed portable payload is missing.']);
                }
                $payload = json_decode($payloads[$entry], true, 64, JSON_THROW_ON_ERROR);
                if (! is_array($payload)) {
                    throw ValidationException::withMessages(['archive' => 'A managed portable payload is invalid.']);
                }
                $this->portable->validate($payload);
            }
            $hash = hash_file('sha256', $absolute);
            if (! is_string($hash)) {
                throw ValidationException::withMessages(['archive' => 'The uploaded archive could not be fingerprinted.']);
            }

            return ['manifest' => $manifest, 'rows' => $rows, 'payloads' => $payloads, 'sha256' => $hash];
        } catch (\Throwable $exception) {
            if ($exception instanceof ValidationException) {
                throw $exception;
            }
            throw ValidationException::withMessages(['archive' => 'The uploaded archive has invalid JSON or an unreadable payload.']);
        } finally {
            $zip->close();
        }
    }

    /** @param array<string, array{sha256: string, size: int}> $entries */
    private function add(ZipArchive $zip, array &$entries, string $name, string $contents): void
    {
        if (isset($entries[$name])) {
            return;
        }
        $total = array_sum(array_column($entries, 'size')) + strlen($contents);
        if ($total > (int) config('development_tools.max_expanded_bytes') || count($entries) >= (int) config('development_tools.max_entries')
            || ! $zip->addFromString($name, $contents)) {
            throw ValidationException::withMessages(['archive' => 'The archive exceeds its bounds or could not store a payload.']);
        }
        $entries[$name] = ['sha256' => hash('sha256', $contents), 'size' => strlen($contents)];
    }

    /** @param array<string, mixed> $plan
     * @return list<array{table: string, columns: array<string, mixed>, fingerprint: string}> */
    private function dependencies(array $plan): array
    {
        $dependencies = [];
        foreach ($plan['rows'] as $table => $records) {
            if (! in_array($table, $this->catalog->tables(), true)) {
                continue;
            }
            foreach ($this->graph->schema()[$table]['foreign_keys'] as $foreign) {
                $parent = $foreign['foreign_table'];
                if (isset($plan['rows'][$parent]) || ($table === 'visitors' && $parent === 'students')) {
                    continue;
                }
                foreach ($records as $row) {
                    $columns = [];
                    foreach ($foreign['columns'] as $index => $column) {
                        $columns[$foreign['foreign_columns'][$index]] = $row[$column] ?? null;
                    }
                    if (in_array(null, $columns, true)) {
                        continue;
                    }
                    $existing = DB::table($parent)->where($columns)->first();
                    if ($existing === null) {
                        throw ValidationException::withMessages(['dependencies' => 'The export contains a missing external dependency. Review '.$parent.'.']);
                    }
                    $safe = $this->sanitizer->row((array) $existing, $parent);
                    unset($safe['created_at'], $safe['updated_at'], $safe['remember_token'], $safe['two_factor_version'], $safe['two_factor_last_used_step']);
                    if ($parent === 'administrators') {
                        $safe = array_intersect_key($safe, array_flip(['id', 'role', 'email']));
                    }
                    $key = $parent.'|'.json_encode($columns, JSON_THROW_ON_ERROR);
                    $dependencies[$key] = ['table' => $parent, 'columns' => $columns, 'fingerprint' => $this->sanitizer->fingerprint($safe, $parent)];
                }
            }
        }

        return array_values($dependencies);
    }
}
