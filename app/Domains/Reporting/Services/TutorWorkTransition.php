<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Reporting\Models\TutorWorkEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class TutorWorkTransition
{
    private const COLUMNS = [
        'tutor_work_entries' => ['id', 'administrator_id', 'active_owner', 'origin', 'placement', 'lifecycle', 'acceptance', 'version', 'category', 'description', 'timezone', 'work_date', 'unplaced_seconds', 'original_declaration', 'system_reason', 'corrected', 'created_at', 'updated_at'],
        'tutor_work_segments' => ['id', 'entry_id', 'started_at', 'ended_at', 'open_entry'],
        'tutor_work_intervals' => ['id', 'entry_id', 'administrator_id', 'started_at', 'ended_at'],
        'tutor_work_revisions' => ['id', 'entry_id', 'administrator_id', 'from_version', 'to_version', 'action', 'reason', 'before_value', 'after_value', 'created_at'],
        'tutor_work_operations' => ['id', 'administrator_id', 'operation_id', 'fingerprint', 'result', 'created_at'],
    ];

    private function legacy(): Builder
    {
        return DB::table('tutor_work_entries')->when(Schema::hasColumn('tutor_work_entries', 'workspace_id'), fn ($query) => $query->whereNull('workspace_id'));
    }

    /** Read-only evidence, with authored values represented only by preservation hashes. */
    public function preflight(): array
    {
        if (! Schema::hasTable('tutor_work_entries')) {
            throw new RuntimeException('The original Tutor Work migration is not applied.');
        }
        $entries = $this->legacy()->orderBy('id')->get(['id', 'administrator_id', 'origin', 'lifecycle', 'acceptance', 'version']);
        $ids = $entries->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $active = $entries->whereIn('lifecycle', ['running', 'paused'])->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $proof = $this->proof($ids, $active);
        $workspace = app(TutorWorkWorkspace::class)->find();
        $decided = ($workspace->transition_decision ?? null) ? json_decode($workspace->transition_decision, true, flags: JSON_THROW_ON_ERROR) : ['adopt' => [], 'preserve' => []];
        $unresolved = array_values(array_diff($ids, $decided['preserve']));
        $constraints = [];
        foreach (array_keys(self::COLUMNS) as $table) {
            $constraints[$table] = ['foreign_keys' => Schema::getForeignKeys($table), 'indexes' => Schema::getIndexes($table)];
        }
        $checks = DB::table('information_schema.CHECK_CONSTRAINTS')->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())->whereIn('TABLE_NAME', array_keys(self::COLUMNS))->orderBy('TABLE_NAME')->orderBy('CONSTRAINT_NAME')->get(['TABLE_NAME', 'CONSTRAINT_NAME', 'CHECK_CLAUSE'])->all();
        $collisions = DB::query()->fromSub(DB::table('tutor_work_operations')->select('operation_id')->groupBy('operation_id')->havingRaw('COUNT(DISTINCT administrator_id) > 1'), 'collisions')->count();
        $receiptMismatches = DB::table('tutor_work_operations as operation')->when(Schema::hasColumn('tutor_work_operations', 'workspace_id'), fn ($query) => $query->whereNull('operation.workspace_id'))
            ->leftJoin('tutor_work_entries as result_entry', 'result_entry.id', '=', DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(operation.result, '$.entry_id')) AS UNSIGNED)"))
            ->where(fn ($query) => $query->whereNull('result_entry.id')->orWhereRaw('NOT (result_entry.administrator_id <=> operation.administrator_id)'))->count();
        $ownershipMismatches = 0;
        foreach (['tutor_work_intervals', 'tutor_work_revisions'] as $table) {
            $ownershipMismatches += DB::table($table.' as child')->joinSub($this->legacy(), 'legacy', 'legacy.id', '=', 'child.entry_id')->whereRaw('NOT (child.administrator_id <=> legacy.administrator_id)')->count();
        }

        return ['database' => DB::connection()->getDatabaseName(), 'database_version' => DB::selectOne('SELECT VERSION() AS version')->version,
            'schema' => ['phase04_applied' => true, 'phase04a_applied' => $workspace !== null && $workspace->transition_state !== 'schema_pending', 'phase04a_migration_recorded' => Schema::hasTable('migrations') && DB::table('migrations')->where('migration', '2026_11_04_130940_add_shared_workspace_to_tutor_work')->exists(), 'constraints' => $constraints, 'checks' => $checks],
            'workspace_state' => $workspace->transition_state ?? 'schema_pending', 'legacy_entry_ids' => $ids,
            'legacy_scopes' => $entries->groupBy(fn ($entry): string => (string) ($entry->administrator_id ?? 'former'))->map(fn ($rows, $owner): array => ['legacy_owner_id' => $owner, 'entry_ids' => $rows->pluck('id')->all(), 'entries' => $rows->count()])->values()->all(),
            'entry_counts' => $this->legacy()->selectRaw('origin, lifecycle, acceptance, COUNT(*) AS entries')->groupBy('origin', 'lifecycle', 'acceptance')->get()->all(),
            'active_entry_ids' => $active, 'active_effective_intervals' => DB::table('tutor_work_intervals')->whereIn('entry_id', $active)->count(),
            'ownership_mismatches' => $ownershipMismatches, 'receipt_scope_mismatches' => $receiptMismatches, 'operation_uuids_in_multiple_actor_scopes' => $collisions,
            'preservation' => $proof['hashes'], 'counts' => array_map('count', $proof['ids']),
            'digest' => hash('sha256', json_encode([$proof['hashes'], $ids, $active], JSON_THROW_ON_ERROR)),
            'decision_required' => $unresolved !== [], 'unresolved_entry_ids' => $unresolved, 'adoption_selected' => $decided['adopt'], 'preservation_selected' => $decided['preserve'], 'message' => 'No adoption is inferred from an administrator role. Explicitly adopt or preserve every legacy entry; preserve means retained outside shared scope.'];
    }

    /** @return array{ids: array<string, array<int>>, hashes: array<string, string>, active: array<int>, open_segments: array<int>} */
    private function proof(array $entries, array $active, ?array $originalIds = null, ?array $mutableSegments = null): array
    {
        $ids = [];
        $hashes = [];
        $mutableSegments ??= DB::table('tutor_work_segments')->whereIn('entry_id', $active)->whereNotNull('open_entry')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        foreach (self::COLUMNS as $table => $columns) {
            $query = DB::table($table);
            if ($originalIds !== null) {
                $query->whereIn('id', $originalIds[$table]);
            } elseif ($table === 'tutor_work_entries') {
                $query->whereIn('id', $entries);
            } elseif ($table !== 'tutor_work_operations') {
                $query->whereIn('entry_id', $entries);
            } elseif (Schema::hasColumn($table, 'workspace_id')) {
                $query->whereNull('workspace_id');
            }
            $hash = hash_init('sha256');
            $ids[$table] = [];
            foreach ($query->orderBy('id')->select($columns)->cursor() as $row) {
                $values = (array) $row;
                $ids[$table][] = (int) $row->id;
                if ($table === 'tutor_work_entries' && in_array((int) $row->id, $active, true)) {
                    foreach (['active_owner', 'lifecycle', 'acceptance', 'version', 'system_reason', 'updated_at'] as $mutable) {
                        unset($values[$mutable]);
                    }
                }
                if ($table === 'tutor_work_segments' && in_array((int) $row->id, $mutableSegments, true)) {
                    unset($values['ended_at'], $values['open_entry']);
                }
                hash_update($hash, json_encode($values, JSON_THROW_ON_ERROR).'\n');
            }
            $hashes[$table] = hash_final($hash);
        }

        return ['ids' => $ids, 'hashes' => $hashes, 'active' => $active, 'open_segments' => $mutableSegments];
    }

    public function convert(array $adopt, array $preserve, bool $cutoverActive, string $expectedDigest): array
    {
        $adopt = $this->identifiers($adopt);
        $preserve = $this->identifiers($preserve);
        $decision = ['adopt' => $adopt, 'preserve' => $preserve, 'cutover_active' => $cutoverActive];
        if (array_intersect($adopt, $preserve) !== []) {
            throw new RuntimeException('Adoption and preservation sets overlap. No transition was applied.');
        }

        if (DB::transactionLevel() !== 0) {
            throw new RuntimeException('The bounded cutover must own its transaction. Run the supported transition command outside another transaction.');
        }
        $owners = $this->legacy()->whereNotNull('administrator_id')->distinct()->orderBy('administrator_id')->pluck('administrator_id')->map(fn ($id): int => (int) $id)->all();

        return DB::transaction(function () use ($adopt, $preserve, $cutoverActive, $expectedDigest, $decision, $owners): array {
            Administrator::withTrashed()->whereIn('id', $owners)->orderBy('id')->lockForUpdate()->get(['id']);
            $workspace = app(TutorWorkWorkspace::class)->find(true);
            if (! $workspace) {
                throw new RuntimeException('Apply the additive shared-workspace migration to the explicitly approved target first.');
            }
            if ($workspace->transition_state === 'ready') {
                if ($workspace->transition_decision && json_decode($workspace->transition_decision, true, flags: JSON_THROW_ON_ERROR) === $decision) {
                    return ['result' => 'already_applied', 'workspace_id' => $workspace->id, 'cutover_at' => $workspace->cutover_at];
                }
                throw new RuntimeException('The workspace is already initialized. Refusing a new historical adoption decision.');
            }
            if ($workspace->transition_state !== 'pending') {
                throw new RuntimeException('The schema upgrade has not finished. No data conversion was applied.');
            }
            $lockedEntries = $this->legacy()->orderBy('id')->lockForUpdate()->get(['id', 'administrator_id']);
            $lockedOwners = $lockedEntries->pluck('administrator_id')->filter(fn ($id): bool => $id !== null)->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();
            if ($owners !== $lockedOwners) {
                throw new RuntimeException('Legacy owner scopes changed while waiting for cutover. Rerun preflight; no conversion committed.');
            }
            $preflight = $this->preflight();
            if (! hash_equals($preflight['digest'], $expectedDigest)) {
                throw new RuntimeException('Legacy evidence changed or no matching preflight digest was supplied. Rerun preflight; no conversion was applied.');
            }
            $all = array_merge($adopt, $preserve);
            sort($all, SORT_NUMERIC);
            if ($all !== $preflight['legacy_entry_ids'] || ($preflight['ownership_mismatches'] !== 0 || $preflight['receipt_scope_mismatches'] !== 0)) {
                throw new RuntimeException('Decide the disposition of every legacy entry exactly once and resolve ownership mismatches first.');
            }
            if ($preflight['active_entry_ids'] !== [] && (! $cutoverActive || $preflight['active_effective_intervals'] !== 0)) {
                throw new RuntimeException('Active legacy timers require explicit --cutover-active and must have no ambiguous pre-existing effective intervals. No timer was selected as winner.');
            }
            $before = $this->proof($preflight['legacy_entry_ids'], $preflight['active_entry_ids']);
            $now = CarbonImmutable::now('UTC')->startOfSecond();
            foreach ($this->legacy()->orderBy('id')->cursor() as $row) {
                $entry = TutorWorkEntry::findOrFail($row->id);
                DB::table('tutor_work_segments')->where('entry_id', $entry->id)->update(['opened_by' => $entry->administrator_id]);
                if (in_array($entry->id, $preflight['active_entry_ids'], true)) {
                    app(TutorWorkService::class)->freeze($entry, $now, 'legacy_cutover', 'explicit_legacy_cutover_needs_review', $entry->administrator_id);
                }
                if (! in_array($entry->id, $adopt, true)) {
                    continue;
                }
                DB::table('tutor_work_entries')->where('id', $entry->id)->update(['workspace_id' => $workspace->id, 'created_by' => $entry->administrator_id]);
                DB::table('tutor_work_intervals')->where('entry_id', $entry->id)->update(['workspace_id' => $workspace->id]);
                DB::table('tutor_work_revisions')->where('entry_id', $entry->id)->update(['workspace_id' => $workspace->id]);
                DB::table('tutor_work_revisions')->where('entry_id', $entry->id)->where('action', 'security_revocation')->update(['system_action' => true, 'subject_administrator_id' => $entry->administrator_id]);
                DB::table('tutor_work_revisions')->where('entry_id', $entry->id)->where('system_action', false)->update(['actor_id' => $entry->administrator_id]);
            }
            $after = $this->proof($preflight['legacy_entry_ids'], $preflight['active_entry_ids'], $before['ids'], $before['open_segments']);
            if ($before['hashes'] !== $after['hashes']) {
                throw new RuntimeException('Legacy preservation check failed. The entire data conversion was rolled back.');
            }
            $preservationDigest = hash('sha256', json_encode($after['hashes'], JSON_THROW_ON_ERROR));
            DB::table('tutor_work_workspaces')->where('id', $workspace->id)->update(['transition_state' => 'ready', 'cutover_at' => $now, 'transition_decision' => json_encode($decision, JSON_THROW_ON_ERROR), 'preservation_digest' => $preservationDigest]);

            return ['result' => 'applied', 'workspace_id' => $workspace->id, 'cutover_at' => $now->toIso8601String(), 'adopted_entry_ids' => $adopt, 'preserved_entry_ids' => $preserve, 'finalized_legacy_timers' => $preflight['active_entry_ids'], 'preservation_digest' => $preservationDigest];
        }, 5);
    }

    private function identifiers(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            if (! preg_match('/\A[1-9][0-9]*\z/', (string) $value)) {
                throw new RuntimeException('Entry sets require positive integer identifiers.');
            }
            $ids[] = (int) $value;
        }
        if (count($ids) !== count(array_unique($ids))) {
            throw new RuntimeException('An entry identifier was specified more than once.');
        }
        sort($ids, SORT_NUMERIC);

        return $ids;
    }
}
