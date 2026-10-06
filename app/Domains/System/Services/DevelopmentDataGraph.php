<?php

namespace App\Domains\System\Services;

use App\Domains\Resources\Models\ResourceCategory;
use App\Mail\StudentSecondaryEmailVerification;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DevelopmentDataGraph
{
    public function __construct(private DevelopmentDataCatalog $catalog, private DevelopmentDataSchema $metadata) {}

    /** @return array<string, array<string, mixed>> */
    public function schema(): array
    {
        return $this->metadata->read();
    }

    /** @param array<string, mixed> $scope
     * @return array<string, mixed> */
    public function reset(string $domain, array $scope = [], bool $lock = false): array
    {
        if (! isset(DevelopmentDataCatalog::PHRASES[$domain])) {
            throw ValidationException::withMessages(['domain' => 'Choose a supported reset domain.']);
        }
        $rows = [];
        $detach = [];
        $warnings = [];
        $roots = match ($domain) {
            'analytics' => $this->catalog->selectedTables(array_keys(array_filter(DevelopmentDataCatalog::MODULES, fn (array $tables, string $module): bool => str_starts_with($module, 'analytics.'), ARRAY_FILTER_USE_BOTH))),
            'students' => ['students', 'student_auth_attempts'],
            'financial' => ['student_packages', 'student_package_entitlements', 'payment_records', 'payment_refunds', 'session_ledger_entries', 'package_installments', 'package_renewals', 'entitlement_mapping_reviews'],
            'resources' => array_merge(! empty($scope['resources']) ? ['resources'] : [], ! empty($scope['categories']) ? ['resource_categories'] : [], ! empty($scope['history']) ? ['resource_requests', 'resource_downloads'] : []),
            default => [],
        };
        if ($domain === 'resources' && $roots === []) {
            throw ValidationException::withMessages(['scope' => 'Select Resources, Categories or request/download history. Files require Resources.']);
        }
        if ($domain === 'resources' && ! empty($scope['files']) && empty($scope['resources'])) {
            throw ValidationException::withMessages(['scope' => 'Deleting private resource files requires including their Resource records.']);
        }
        foreach ($roots as $table) {
            $this->include($rows, $table, $this->query($table, $lock));
        }
        if ($domain === 'financial') {
            $this->include($rows, 'bookings', $this->query('bookings', $lock)->where(fn (Builder $query) => $query->where('funding_mode', 'package')->orWhereNotNull('student_package_id')->orWhereNotNull('consumed_ledger_entry_id')));
            $warnings[] = 'All purchases, allocations, payments, refunds, ledger, installments and renewals are removed. Package-funded lessons and their dependent teaching records are included. Students and unrelated direct/free lessons remain.';
        }
        if ($domain === 'students') {
            $emails = array_values(array_filter(array_column($rows['students'] ?? [], 'email_normalized')));
            if ($emails !== []) {
                $this->include($rows, 'booking_holds', $this->query('booking_holds', $lock)->whereIn('lead_details->email', $emails));
            }
            $warnings[] = 'All students, including archived/merged/deleted history, and owned business/financial records are removed. Independent Contacts and shared Resources remain. Student session keys and transient authentication records are cleared; staff login is retained.';
        }
        do {
            $previous = array_sum(array_map('count', $rows));
            foreach ($this->schema() as $child => $meta) {
                foreach ($meta['foreign_keys'] as $foreign) {
                    $parent = $foreign['foreign_table'];
                    if (empty($rows[$parent])) {
                        continue;
                    }
                    $parentValues = array_values(array_unique(array_column($rows[$parent], $foreign['foreign_columns'][0])));
                    $query = $this->query($child, $lock)->whereIn($foreign['columns'][0], $parentValues);
                    if (in_array($child, ['audit_logs', 'visitors'], true) && $parent === 'students') {
                        continue;
                    }
                    if ($domain === 'resources' && $parent === 'resources' && in_array($child, ['lesson_materials', 'homeworks'], true)) {
                        foreach ($query->get() as $record) {
                            $changes = ['resource_id' => null];
                            if ($child === 'lesson_materials') {
                                $changes += ['withdrawn_at' => $record->withdrawn_at ?? '@operation_time', 'student_visible' => 0, 'url' => null];
                            }
                            $detach[$child][(string) $record->id] = $changes;
                        }

                        continue;
                    }
                    if ($domain === 'resources' && $child === 'resources' && empty($scope['resources']) && $query->exists()) {
                        throw ValidationException::withMessages(['scope' => 'Categories still own Resources. Include Resources or preserve Categories.']);
                    }
                    if ($domain === 'resources' && in_array($child, ['resource_requests', 'resource_downloads'], true) && empty($scope['history']) && $query->exists()) {
                        throw ValidationException::withMessages(['scope' => 'Resources have required request/download history. Include that checkbox or preserve the Resources.']);
                    }
                    $allowed = array_merge($this->catalog->tables(), ['student_email_verifications', 'student_auth_attempts', 'booking_holds']);
                    if (! in_array($child, $allowed, true)) {
                        if ($query->exists()) {
                            throw ValidationException::withMessages(['scope' => 'An unreviewed dependent table requires review: '.$child.'. No reset was performed.']);
                        }

                        continue;
                    }
                    $this->include($rows, $child, $query);
                }
            }
            $this->revisions($rows, $lock);
        } while ($previous !== array_sum(array_map('count', $rows)));

        if (in_array($domain, ['students', 'financial'], true)) {
            $studentIds = array_keys($rows['students'] ?? []);
            $bookingIds = array_keys($rows['bookings'] ?? []);
            $this->include($rows, 'staff_recent_views', $this->query('staff_recent_views', $lock)->where(function (Builder $query) use ($studentIds, $bookingIds): void {
                $query->where(fn (Builder $students) => $students->where('entity_type', 'student')->whereIn('entity_id', $studentIds))
                    ->orWhere(fn (Builder $bookings) => $bookings->where('entity_type', 'booking')->whereIn('entity_id', $bookingIds));
            }));
            $this->include($rows, 'telegram_deliveries', $this->query('telegram_deliveries', $lock)->where(function (Builder $query) use ($studentIds, $bookingIds): void {
                $query->whereIn('payload->source->student_id', $studentIds)->orWhereIn('payload->source->booking_id', $bookingIds);
            }));
            if ($domain === 'students') {
                foreach (['jobs', 'failed_jobs'] as $queue) {
                    $this->include($rows, $queue, $this->query($queue, $lock)->where('payload->displayName', StudentSecondaryEmailVerification::class));
                }
            }
        }
        $plan = $this->plan($rows, $detach, $warnings, $domain === 'resources' ? ! empty($scope['files']) : true);
        if ($domain === 'students') {
            $sessions = app(StudentSessionCleanupService::class)->preview();
            $plan['student_session_count'] = $sessions['count'];
            $plan['fingerprint'] = hash('sha256', $plan['fingerprint'].'|'.$sessions['fingerprint']);
        }

        return $plan;
    }

    /** @param list<string> $modules
     * @param array<string, mixed> $filters
     * @return array<string, mixed> */
    public function export(array $modules, array $filters = []): array
    {
        $rows = [];
        foreach ($this->catalog->selectedTables($modules) as $table) {
            $query = $this->query($table, false);
            if (in_array($table, ['content_revisions', 'entity_translation_revisions'], true)) {
                $typeColumn = $table === 'content_revisions' ? 'revisable_type' : 'entity_type';
                $query->whereIn($typeColumn, ['resource', 'resource_category', \App\Domains\Resources\Models\Resource::class, ResourceCategory::class]);
            }
            $columns = $this->schema()[$table]['columns'];
            $module = (string) $this->module($table);
            if (str_starts_with($module, 'students.') && isset($columns['student_id'])) {
                $query->whereNotNull('student_id');
            }
            if (! empty($filters['student_id']) && (str_starts_with($module, 'students.') || str_starts_with($module, 'financial.'))) {
                $this->dimension($query, $table, 'student_id', $filters['student_id']);
            }
            if (! empty($filters['currency']) && str_starts_with($module, 'financial.')) {
                $this->dimension($query, $table, 'currency', $filters['currency']);
            }
            if (! empty($filters['date_from']) && isset($columns['created_at']) && str_starts_with((string) $this->module($table), 'analytics.')) {
                $query->where('created_at', '>=', $filters['date_from']);
            }
            if (! empty($filters['date_to']) && isset($columns['created_at']) && str_starts_with((string) $this->module($table), 'analytics.')) {
                $query->where('created_at', '<', $filters['date_to']);
            }
            $this->include($rows, $table, $query);
        }
        do {
            $previous = array_sum(array_map('count', $rows));
            foreach ($rows as $child => $records) {
                foreach ($this->schema()[$child]['foreign_keys'] as $foreign) {
                    $parent = $foreign['foreign_table'];
                    if ($child === 'visitors' && $parent === 'students') {
                        continue;
                    }
                    if (! in_array($parent, $this->catalog->tables(), true)) {
                        continue;
                    }
                    $values = array_values(array_filter(array_unique(array_column($records, $foreign['columns'][0])), fn (mixed $value): bool => $value !== null));
                    if ($values !== []) {
                        $this->include($rows, $parent, $this->query($parent, false)->whereIn($foreign['foreign_columns'][0], $values));
                    }
                }
            }
        } while ($previous !== array_sum(array_map('count', $rows)));

        return $this->plan($rows, [], ['Required parent rows are included automatically; configuration and credential/session tables are excluded.'], true);
    }

    private function module(string $table): ?string
    {
        foreach (DevelopmentDataCatalog::MODULES as $module => $tables) {
            if (in_array($table, $tables, true)) {
                return $module;
            }
        }

        return null;
    }

    /** @param list<string> $seen */
    private function hasDimension(string $table, string $column, array $seen = []): bool
    {
        if (in_array($table, $seen, true)) {
            return false;
        }
        if (($column === 'student_id' && $table === 'students') || isset($this->schema()[$table]['columns'][$column])) {
            return true;
        }
        foreach ($this->schema()[$table]['foreign_keys'] as $foreign) {
            if (in_array($foreign['foreign_table'], $this->catalog->tables(), true)
                && $this->hasDimension($foreign['foreign_table'], $column, [...$seen, $table])) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $seen */
    private function dimension(Builder $query, string $table, string $column, mixed $value, array $seen = []): void
    {
        if ($column === 'student_id' && $table === 'students') {
            $query->where($table.'.id', $value);

            return;
        }
        if (isset($this->schema()[$table]['columns'][$column])) {
            $query->where($table.'.'.$column, $value);

            return;
        }
        $parents = array_filter($this->schema()[$table]['foreign_keys'], fn (array $foreign): bool => in_array($foreign['foreign_table'], $this->catalog->tables(), true)
            && $this->hasDimension($foreign['foreign_table'], $column, [...$seen, $table]));
        if ($parents === []) {
            return;
        }
        $query->where(function (Builder $matches) use ($parents, $table, $column, $value, $seen): void {
            foreach ($parents as $foreign) {
                $matches->orWhereExists(function (Builder $parent) use ($foreign, $table, $column, $value, $seen): void {
                    $parent->selectRaw('1')->from($foreign['foreign_table']);
                    foreach ($foreign['columns'] as $index => $childColumn) {
                        $parent->whereColumn($foreign['foreign_table'].'.'.$foreign['foreign_columns'][$index], $table.'.'.$childColumn);
                    }
                    $this->dimension($parent, $foreign['foreign_table'], $column, $value, [...$seen, $table]);
                });
            }
        });
    }

    private function query(string $table, bool $lock): Builder
    {
        $primary = $this->schema()[$table]['primary'];
        if (count($primary) !== 1) {
            throw ValidationException::withMessages(['scope' => 'The ownership graph requires a reviewed single-column identity for '.$table.'.']);
        }
        $query = DB::table($table)->orderBy($primary[0]);

        return $lock ? $query->lockForUpdate() : $query;
    }

    /** @param array<string, array<string, array<string, mixed>>> $rows */
    private function include(array &$rows, string $table, Builder $query): void
    {
        $rows[$table] ??= [];
        $key = $this->schema()[$table]['primary'][0];
        foreach ($query->limit((int) config('development_tools.max_rows') + 1)->get() as $record) {
            /** @var array<string, mixed> $row */
            $row = (array) $record;
            $rows[$table][(string) $row[$key]] = $row;
        }
        if (array_sum(array_map('count', $rows)) > (int) config('development_tools.max_rows')) {
            throw ValidationException::withMessages(['scope' => 'This operation exceeds the bounded record limit. Export a smaller selection or arrange a reviewed offline operation.']);
        }
    }

    /** @param array<string, array<string, array<string, mixed>>> $rows */
    private function revisions(array &$rows, bool $lock): void
    {
        foreach (['resources' => ['resource', \App\Domains\Resources\Models\Resource::class], 'resource_categories' => ['resource_category', ResourceCategory::class]] as $table => $types) {
            if (empty($rows[$table])) {
                continue;
            }
            foreach (['content_revisions' => ['revisable_type', 'revisable_id'], 'entity_translation_revisions' => ['entity_type', 'entity_id']] as $history => $keys) {
                $this->include($rows, $history, $this->query($history, $lock)->whereIn($keys[0], $types)->whereIn($keys[1], array_keys($rows[$table])));
            }
        }
    }

    /** @param array<string, array<string, array<string, mixed>>> $rows
     * @param array<string, array<string, array<string, mixed>>> $detach
     * @param list<string> $warnings
     * @return array<string, mixed> */
    private function plan(array $rows, array $detach, array $warnings, bool $files): array
    {
        ksort($rows);
        foreach ($rows as &$records) {
            ksort($records);
        }
        unset($records);
        $counts = array_map('count', $rows);
        $fingerprint = hash('sha256', json_encode([$rows, $detach, $files], JSON_THROW_ON_ERROR));

        return compact('rows', 'detach', 'counts', 'warnings', 'fingerprint', 'files');
    }
}
