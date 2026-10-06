<?php

namespace App\Domains\System\Services;

use App\Domains\Students\Services\BillingReconciliationService;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DevelopmentDataReconciler
{
    public function __construct(private DevelopmentDataGraph $graph, private BillingReconciliationService $billing) {}

    /** @return array{orphan_count: int, financial_discrepancies: int, financial_status: string} */
    public function verify(bool $financial = false): array
    {
        $orphans = 0;
        foreach ($this->graph->schema() as $table => $meta) {
            foreach ($meta['foreign_keys'] as $foreign) {
                $query = DB::table($table.' as child')->leftJoin($foreign['foreign_table'].' as parent', function (JoinClause $join) use ($foreign): void {
                    foreach ($foreign['columns'] as $index => $column) {
                        $join->on('child.'.$column, '=', 'parent.'.$foreign['foreign_columns'][$index]);
                    }
                })->whereNull('parent.'.$foreign['foreign_columns'][0]);
                foreach ($foreign['columns'] as $column) {
                    $query->whereNotNull('child.'.$column);
                }
                $orphans += $query->count();
            }
        }
        $report = $financial ? $this->billing->reconcile() : null;
        $discrepancies = (int) ($report['total_discrepancies_count'] ?? 0);
        if ($orphans !== 0 || $discrepancies !== 0) {
            throw ValidationException::withMessages(['reconciliation' => 'Completion reconciliation failed. The database operation was rolled back; review the existing ownership/financial state.']);
        }

        return ['orphan_count' => $orphans, 'financial_discrepancies' => $discrepancies,
            'financial_status' => $report['status'] ?? ($financial ? 'RECONCILED' : 'not_requested')];
    }
}
