<?php

namespace App\Domains\System\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\System\Models\DevelopmentDataOperation;
use Illuminate\Support\Facades\DB;

class DevelopmentDataNumbering
{
    public function __construct(private DevelopmentToolsAccess $access, private DevelopmentDataGraph $graph,
        private DevelopmentDataCatalog $catalog, private AuditLogService $audit) {}

    /** MySQL sequence DDL must run after the deletion transaction commits, never inside it.
     * @return array<string, mixed> */
    public function finish(Administrator $actor, DevelopmentDataOperation $operation): array
    {
        $this->access->authorize($actor);
        $operation->refresh();
        abort_unless($operation->administrator_id === $actor->id && $operation->status === 'completed', 403);
        $summary = $operation->summary;
        if ($operation->type !== 'reset' || ($summary['numbering_status'] ?? null) !== 'pending' || DB::transactionLevel() !== 0) {
            return $summary;
        }
        $results = [];
        foreach ($summary['numbering_tables'] as $table) {
            $meta = $this->graph->schema()[$table] ?? null;
            if (! in_array($table, $this->catalog->resetTables(), true) || ! preg_match('/^[a-z][a-z0-9_]*$/D', $table)
                || $meta === null || count($meta['primary']) !== 1 || empty($meta['columns'][$meta['primary'][0]]['auto_increment'])) {
                $results[$table] = 'Not restarted: unsupported table';

                continue;
            }
            if (DB::table($table)->exists()) {
                $results[$table] = 'Preserved: records remain';

                continue;
            }
            try {
                DB::statement('ALTER TABLE '.DB::connection()->getQueryGrammar()->wrapTable($table).' AUTO_INCREMENT = 1');
                $counter = DB::selectOne('SELECT AUTO_INCREMENT AS next_id FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [DB::connection()->getDatabaseName(), $table]);
                $results[$table] = (int) ($counter->next_id ?? 0) === 1 ? 'Restarted: next record #1' : 'Preserved: new records arrived during reset';
            } catch (\Throwable $exception) {
                report($exception);
                $results[$table] = 'Not restarted: database rejected numbering change; deletion already completed';
            }
        }
        $summary['numbering_status'] = count(array_filter($results, fn (string $result): bool => str_starts_with($result, 'Not restarted:'))) > 0 ? 'needs_attention' : 'completed';
        $summary['numbering_results'] = $results;
        unset($summary['numbering_tables']);
        $operation->update(['summary' => $summary]);
        $this->audit->log('development_data_numbering_completed', DevelopmentDataOperation::class, $operation->id,
            newData: ['status' => $summary['numbering_status'], 'results' => $results], adminId: $actor->id);

        return $summary;
    }
}
