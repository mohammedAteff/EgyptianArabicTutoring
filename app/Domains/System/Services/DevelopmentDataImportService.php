<?php

namespace App\Domains\System\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Analytics\Services\AnalyticsLifecycle;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\CMS\Models\Setting;
use App\Domains\System\Models\DevelopmentDataOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class DevelopmentDataImportService
{
    public function __construct(private DevelopmentToolsAccess $access, private DevelopmentDataCatalog $catalog,
        private DevelopmentDataGraph $graph, private DevelopmentDataFiles $files, private DevelopmentDataSanitizer $sanitizer,
        private DevelopmentDataReconciler $reconciler, private AuditLogService $audit) {}

    /** @param array<string, mixed> $archive
     * @param list<string> $modules
     * @return array<string, mixed> */
    public function preview(Administrator $actor, array $archive, array $modules): array
    {
        $this->access->authorize($actor);
        if (array_diff($modules, $archive['manifest']['included_modules']) !== []) {
            throw ValidationException::withMessages(['modules' => 'Select only modules present in this archive.']);
        }
        $tables = $this->catalog->selectedTables($modules);
        $rows = array_intersect_key($archive['rows'], array_flip($tables));
        $counts = [];
        $conflicts = [];
        $missing = [];
        $existingRows = [];
        $parentCache = [];
        foreach ($rows as $table => $records) {
            $existingRows[$table] = DB::table($table)->whereIn('id', array_column($records, 'id'))->get()->keyBy('id')->all();
            $counts[$table] = ['restore' => 0, 'skip_identical' => 0];
            $this->uniqueConflicts($table, $records, $conflicts);
            foreach ($records as $row) {
                $existing = $existingRows[$table][$row['id']] ?? null;
                if ($existing === null) {
                    $counts[$table]['restore']++;
                } elseif ($this->sanitizer->fingerprint((array) $existing, $table) === $this->sanitizer->fingerprint($row, $table)) {
                    $counts[$table]['skip_identical']++;
                } else {
                    $conflicts[] = ['table' => $table, 'id' => $row['id'], 'reason' => 'Existing record differs; overwrite is prohibited.'];
                }
                $this->dependencies($archive, $rows, $table, $row, $missing, $conflicts, $parentCache);
                $this->validateValues($table, $row);
            }
        }
        $files = $this->fileRows($archive, $rows, $missing);
        foreach ($files as $file) {
            $existing = $existingRows[$file['table']][$file['id']] ?? null;
            if ($existing !== null) {
                $path = ((array) $existing)[$file['column']] ?? null;
                if (! is_string($path) || ! Storage::disk('local')->exists($path)
                    || hash_file('sha256', $this->files->privatePath($path)) !== $file['sha256']) {
                    $conflicts[] = ['table' => $file['table'], 'id' => $file['id'], 'reason' => 'Existing private-file payload differs or is missing.'];
                }
            }
        }
        $fingerprint = hash('sha256', json_encode([$archive['sha256'], $modules, $counts, $conflicts, $missing], JSON_THROW_ON_ERROR));

        return ['counts' => $counts, 'conflicts' => array_values($conflicts), 'missing_dependencies' => array_values($missing),
            'file_count' => count($files), 'snapshot_count' => in_array('backups.snapshots', $modules, true) ? count($archive['manifest']['managed_snapshots'] ?? []) : 0,
            'fingerprint' => $fingerprint, 'policy' => 'Restore missing; skip identical; refuse conflicts', 'rows' => $rows, 'files' => $files];
    }

    /** Query unique identities in bounded batches, using the database collation for comparisons.
     * @param list<array<string, mixed>> $records
     * @param array<int|string, mixed> $conflicts */
    private function uniqueConflicts(string $table, array $records, array &$conflicts): void
    {
        foreach ($this->graph->schema()[$table]['unique_indexes'] as $columns) {
            if ($columns === ['id']) {
                continue;
            }
            $identities = [];
            foreach ($records as $row) {
                foreach (['live_locale' => ['published', 'stale'], 'draft_locale' => ['draft']] as $generated => $statuses) {
                    if (! empty($this->graph->schema()[$table]['columns'][$generated]['generated'])) {
                        $row[$generated] = in_array($row['status'] ?? null, $statuses, true) ? ($row['locale'] ?? null) : null;
                    }
                }
                $values = array_intersect_key($row, array_flip($columns));
                if (count($values) === count($columns) && ! in_array(null, $values, true)) {
                    $identities[] = ['id' => $row['id'], 'values' => $values];
                }
            }
            $seen = [];
            foreach (array_chunk($identities, 100) as $batch) {
                $query = null;
                foreach ($batch as $identity) {
                    $candidate = DB::table($table)->selectRaw('? AS archive_id', [$identity['id']])
                        ->where($identity['values'])->where('id', '!=', $identity['id']);
                    if ($query === null) {
                        $query = $candidate;
                    } else {
                        $query->unionAll($candidate);
                    }
                }
                foreach ($query->get() as $collision) {
                    $conflicts[] = ['table' => $table, 'id' => $collision->archive_id, 'reason' => 'A unique identity belongs to another current record; overwrite is prohibited.'];
                }
                foreach ($batch as $identity) {
                    $key = json_encode($identity['values'], JSON_THROW_ON_ERROR);
                    if (isset($seen[$key])) {
                        $conflicts[] = ['table' => $table, 'id' => $identity['id'], 'reason' => 'The archive repeats a unique identity.'];
                    }
                    $seen[$key] = true;
                }
            }
        }
    }

    /** @param array<string, mixed> $archive
     * @param array<string, list<array<string, mixed>>> $rows
     * @param array<string, mixed> $row
     * @param array<string, mixed> $missing
     * @param array<int|string, mixed> $conflicts
     * @param array<string, object|null> $parentCache */
    private function dependencies(array $archive, array $rows, string $table, array $row, array &$missing, array &$conflicts, array &$parentCache): void
    {
        foreach ($this->graph->schema()[$table]['foreign_keys'] as $foreign) {
            $columns = [];
            foreach ($foreign['columns'] as $index => $column) {
                $columns[$foreign['foreign_columns'][$index]] = $row[$column] ?? null;
            }
            if (in_array(null, $columns, true)) {
                continue;
            }
            $parent = $foreign['foreign_table'];
            $selected = collect($rows[$parent] ?? [])->first(function (array $candidate) use ($columns): bool {
                foreach ($columns as $column => $value) {
                    if ((string) ($candidate[$column] ?? '') !== (string) $value) {
                        return false;
                    }
                }

                return true;
            });
            if ($selected !== null) {
                continue;
            }
            $key = $parent.'|'.json_encode($columns, JSON_THROW_ON_ERROR);
            if (! array_key_exists($key, $parentCache)) {
                $parentCache[$key] = DB::table($parent)->where($columns)->first();
            }
            $existing = $parentCache[$key];
            if ($existing === null) {
                $missing[$key] = ['table' => $parent, 'identity' => $columns, 'reason' => 'Include the parent module or restore its missing identity first.'];

                continue;
            }
            $archivedParent = collect($archive['rows'][$parent] ?? [])->first(fn (array $candidate): bool => (string) ($candidate[$foreign['foreign_columns'][0]] ?? '') === (string) $columns[$foreign['foreign_columns'][0]]);
            if ($archivedParent !== null && $this->sanitizer->fingerprint((array) $existing, $parent) !== $this->sanitizer->fingerprint($archivedParent, $parent)) {
                $conflicts[$key] = ['table' => $parent, 'identity' => $columns, 'reason' => 'Existing parent identity differs from the archive.'];
            }
            foreach ($archive['manifest']['dependencies'] ?? [] as $dependency) {
                if (($dependency['table'] ?? null) !== $parent || ($dependency['columns'] ?? null) !== $columns) {
                    continue;
                }
                $safe = $this->sanitizer->row((array) $existing, $parent);
                unset($safe['created_at'], $safe['updated_at'], $safe['remember_token'], $safe['two_factor_version'], $safe['two_factor_last_used_step']);
                if ($parent === 'administrators') {
                    $safe = array_intersect_key($safe, array_flip(['id', 'role', 'email']));
                }
                if (($dependency['fingerprint'] ?? null) !== $this->sanitizer->fingerprint($safe, $parent)) {
                    $conflicts[$key] = ['table' => $parent, 'identity' => $columns, 'reason' => 'An external configuration/identity dependency changed.'];
                }
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function validateValues(string $table, array $row): void
    {
        foreach (['url', 'external_url', 'meeting_url_snapshot'] as $column) {
            $url = $row[$column] ?? null;
            if ($url !== null && (! is_string($url) || ! str_starts_with($url, 'https://') || filter_var($url, FILTER_VALIDATE_URL) === false)) {
                throw ValidationException::withMessages(['archive' => 'An imported URL must retain the application HTTPS safety contract.']);
            }
        }
        if ($table === 'resources' && ! empty($row['cover_image_path'])) {
            if (! preg_match('~^media/[A-Za-z0-9_.-]+\.(jpg|jpeg|png|webp)$~D', $row['cover_image_path'])
                || ! Storage::disk('public')->exists($row['cover_image_path'])) {
                throw ValidationException::withMessages(['archive' => 'The Resource cover requires its existing shared Media file. Shared Media is not reset or imported by this tool.']);
            }
        }
    }

    /** @param array<string, mixed> $archive
     * @param array<string, list<array<string, mixed>>> $rows
     * @param array<string, mixed> $missing
     * @return list<array<string, mixed>> */
    private function fileRows(array $archive, array $rows, array &$missing): array
    {
        $files = [];
        foreach ($archive['manifest']['file_inventory'] ?? [] as $file) {
            $table = $file['table'] ?? '';
            $column = $table === 'resources' ? 'file_path' : 'path';
            if (! in_array($table, ['resources', 'lesson_materials'], true) || ($file['column'] ?? null) !== $column
                || ! in_array($file['extension'] ?? null, ['pdf', 'zip', 'doc', 'docx', 'mp3', 'wav', 'm4a'], true)
                || ! is_string($file['sha256'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $file['sha256'])
                || ($file['entry'] ?? null) !== 'files/'.$file['sha256'].'.bin'
                || ! isset($archive['payloads'][$file['entry']]) || hash('sha256', $archive['payloads'][$file['entry']]) !== $file['sha256']) {
                throw ValidationException::withMessages(['archive' => 'The private-file inventory is invalid.']);
            }
            $owner = collect($rows[$table] ?? [])->first(fn (array $row): bool => (string) $row['id'] === (string) ($file['id'] ?? ''));
            if ($owner !== null) {
                if (($owner[$column] ?? null) !== ($file['path'] ?? null) || ($table === 'lesson_materials' && ($file['extension'] !== 'pdf' || $owner['kind'] !== 'private_file'))) {
                    throw ValidationException::withMessages(['archive' => 'The private-file owner does not match its inventory.']);
                }
                $key = $table.':'.$file['id'];
                if (isset($files[$key])) {
                    throw ValidationException::withMessages(['archive' => 'The archive repeats a private-file owner.']);
                }
                $file['destination'] = ($table === 'resources' ? 'resources/' : 'lesson-materials/').$archive['manifest']['archive_id'].'-'.$file['id'].'-'.substr($file['sha256'], 0, 16).'.'.$file['extension'];
                $files[$key] = $file;
            }
        }
        foreach (['resources' => 'file_path', 'lesson_materials' => 'path'] as $table => $column) {
            foreach ($rows[$table] ?? [] as $row) {
                if (! empty($row[$column]) && ! isset($files[$table.':'.$row['id']])) {
                    $missing['file:'.$table.':'.$row['id']] = ['table' => $table, 'id' => $row['id'], 'reason' => 'The private-file payload module is required.'];
                }
            }
        }

        return array_values($files);
    }

    /** Coordinator owns the transaction, operation lock, security confirmation and snapshot.
     * @param array<string, mixed> $archive
     * @param list<string> $modules
     * @return array<string, mixed> */
    public function run(Administrator $actor, array $archive, array $modules, ?DevelopmentDataOperation $operation = null): array
    {
        $this->access->authorize($actor);
        if (DB::transactionLevel() < 1) {
            throw ValidationException::withMessages(['operation' => 'Import requires its operation transaction.']);
        }
        $this->access->assertConfirmed($actor, $operation, 'import', 'selection');
        $preview = $this->preview($actor, $archive, $modules);
        if ($preview['conflicts'] !== [] || $preview['missing_dependencies'] !== []) {
            throw ValidationException::withMessages(['modules' => 'Resolve the preview conflicts or missing dependencies before restoring. Nothing was overwritten.']);
        }
        $createdFiles = [];
        $inserted = [];
        $deferred = [];
        try {
            foreach ($preview['files'] as $file) {
                if (DB::table($file['table'])->where('id', $file['id'])->exists()) {
                    continue;
                }
                $disk = Storage::disk('local');
                $contents = $archive['payloads'][$file['entry']];
                if ($disk->exists($file['destination'])) {
                    if (hash('sha256', (string) $disk->get($file['destination'])) !== $file['sha256']) {
                        throw ValidationException::withMessages(['files' => 'A generated private path already contains different bytes.']);
                    }
                } elseif (! $disk->put($file['destination'], $contents, 'private')) {
                    throw ValidationException::withMessages(['files' => 'The verified private payload could not be stored.']);
                } else {
                    $createdFiles[] = $file['destination'];
                }
            }
            $pending = [];
            foreach ($preview['rows'] as $table => $records) {
                foreach ($records as $row) {
                    if (! DB::table($table)->where('id', $row['id'])->exists()) {
                        $pending[$table.':'.$row['id']] = ['table' => $table, 'row' => $row];
                    }
                }
            }
            while ($pending !== []) {
                $progress = false;
                foreach ($pending as $key => $item) {
                    $table = $item['table'];
                    $row = $item['row'];
                    $original = $row;
                    if ($table === 'bookings') {
                        $row['confirmation_token'] = Str::random(64);
                        $row = array_replace($row, ['funding_mode' => 'legacy', 'entitlement_type_id' => null, 'entitlement_code' => null, 'entitlement_units' => null,
                            'student_package_id' => null, 'student_package_entitlement_id' => null, 'consumed_ledger_entry_id' => null, 'request_fingerprint' => null]);
                    }
                    if ($table === 'students') {
                        $row['merged_into_student_id'] = null;
                        $row['possible_duplicate_of_student_id'] = null;
                    }
                    foreach ($preview['files'] as $file) {
                        if ($file['table'] === $table && (string) $file['id'] === (string) $row['id']) {
                            $row[$file['column']] = $file['destination'];
                            if ($table === 'lesson_materials') {
                                $row['disk'] = 'local';
                            }
                        }
                    }
                    if (! $this->parentsExist($table, $row)) {
                        continue;
                    }
                    DB::table($table)->insert($row);
                    $inserted[$table][] = $row['id'];
                    if (in_array($table, ['bookings', 'students'], true)) {
                        $deferred[] = ['table' => $table, 'row' => $original];
                    }
                    unset($pending[$key]);
                    $progress = true;
                }
                if (! $progress) {
                    throw ValidationException::withMessages(['dependencies' => 'The selected rows contain an unsupported dependency cycle. Import was rolled back.']);
                }
            }
            foreach ($deferred as $item) {
                $row = $item['row'];
                $fields = $item['table'] === 'students' ? ['merged_into_student_id', 'possible_duplicate_of_student_id']
                    : ['funding_mode', 'entitlement_type_id', 'entitlement_code', 'entitlement_units', 'student_package_id', 'student_package_entitlement_id', 'consumed_ledger_entry_id', 'request_fingerprint'];
                DB::table($item['table'])->where('id', $row['id'])->update(array_intersect_key($row, array_flip($fields)));
            }
            $snapshots = in_array('backups.snapshots', $modules, true) ? $this->restoreSnapshots($actor, $archive, $createdFiles) : 0;
            if (array_filter($modules, fn (string $module): bool => str_starts_with($module, 'analytics.')) !== []) {
                $floor = $archive['manifest']['analytics_lifecycle']['booking_floor'] ?? 0;
                if (! is_int($floor) || $floor < 0) {
                    throw ValidationException::withMessages(['archive' => 'The archived analytics lifecycle boundary is invalid.']);
                }
                Setting::set('analytics_reset_booking_floor', $floor, 'development_lifecycle');
                Setting::set('analytics_collection_generation', (string) Str::uuid(), 'development_lifecycle');
                app(AnalyticsLifecycle::class)->forget();
            }
            $financial = array_intersect($modules, ['financial.packages', 'financial.payments', 'financial.ledger', 'students.bookings']) !== [];
            $reconciliation = $this->reconciler->verify($financial);

            return ['restored_records' => array_map('count', $inserted), 'skipped_identical' => array_sum(array_column($preview['counts'], 'skip_identical')),
                'restored_files' => count($createdFiles), 'restored_managed_snapshots' => $snapshots, 'reconciliation' => $reconciliation];
        } catch (\Throwable $exception) {
            foreach ($createdFiles as $path) {
                Storage::disk(str_starts_with($path, 'backup-portable-') ? 'managed_backups' : 'local')->delete($path);
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $row */
    private function parentsExist(string $table, array $row): bool
    {
        foreach ($this->graph->schema()[$table]['foreign_keys'] as $foreign) {
            $columns = [];
            foreach ($foreign['columns'] as $index => $column) {
                $columns[$foreign['foreign_columns'][$index]] = $row[$column] ?? null;
            }
            if (! in_array(null, $columns, true) && ! DB::table($foreign['foreign_table'])->where($columns)->exists()) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $archive
     * @param list<string> $createdFiles */
    private function restoreSnapshots(Administrator $actor, array $archive, array &$createdFiles): int
    {
        $restored = 0;
        foreach ($archive['manifest']['managed_snapshots'] ?? [] as $snapshot) {
            $entry = $snapshot['entry'] ?? '';
            if (! preg_match('~^snapshots/[a-f0-9]{64}\.json$~D', $entry) || ! isset($archive['payloads'][$entry])) {
                throw ValidationException::withMessages(['archive' => 'A managed portable snapshot is missing.']);
            }
            $payload = json_decode($archive['payloads'][$entry], true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                throw ValidationException::withMessages(['archive' => 'The managed portable snapshot format is unsupported.']);
            }
            app(ManagedBackupPortableService::class)->validate($payload);
            $hash = hash('sha256', $archive['payloads'][$entry]);
            $filename = 'backup-portable-'.$hash.'.zip';
            $disk = Storage::disk('managed_backups');
            if ($disk->exists($filename)) {
                continue;
            }
            $zip = new ZipArchive;
            $disk->makeDirectory('');
            if ($zip->open($disk->path($filename), ZipArchive::CREATE) !== true) {
                throw ValidationException::withMessages(['backups' => 'The managed portable snapshot could not be stored.']);
            }
            $createdFiles[] = $filename;
            $zip->addFromString('manifest.json', json_encode(['managed_portable_version' => 1, 'snapshot_sha256' => $hash, 'created_at_utc' => now('UTC')->toIso8601String()], JSON_THROW_ON_ERROR));
            $zip->addFromString('snapshot.json', $archive['payloads'][$entry]);
            $zip->close();
            $this->audit->log('development_backup_imported', self::class, null, newData: ['filename' => $filename, 'archive_sha256' => $archive['sha256']], adminId: $actor->id);
            $restored++;
        }

        return $restored;
    }
}
