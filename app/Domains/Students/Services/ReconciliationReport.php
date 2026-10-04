<?php

namespace App\Domains\Students\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class ReconciliationReport
{
    public const CATEGORIES = [
        'negative_balances' => 'Negative Derived Balance', 'excess_refunds' => 'Excess Refund',
        'credit_mismatches' => 'Credit Allocation Mismatch', 'ownership_inconsistencies' => 'Ownership Inconsistency',
        'unreconciled_bookings' => 'Unreconciled Booking', 'negative_credit_balance_bookings' => 'Negative Credit Balance Booking',
    ];

    /** @return array<string, mixed> */
    public function filters(Request $request): array
    {
        return $request->validate(['q' => ['nullable', 'string', 'max:255'], 'student_id' => ['nullable', 'integer', 'min:1'], 'category' => ['nullable', Rule::in(array_keys(self::CATEGORIES))], 'format' => ['nullable', Rule::in(['csv', 'xlsx'])]]);
    }

    /** @return Collection<int, array<int, string>> */
    public function rows(array $report, array $filters): Collection
    {
        $rows = collect();
        foreach (self::CATEGORIES as $category => $label) {
            if (! empty($filters['category']) && $filters['category'] !== $category) {
                continue;
            }
            foreach ($report[$category] as $item) {
                if (! empty($filters['student_id']) && ! in_array((int) $filters['student_id'], array_map('intval', $item['related_student_ids'] ?? [$item['student_id'] ?? 0]), true)) {
                    continue;
                }
                $details = array_intersect_key($item, array_flip(['offering_key', 'entitlement_code', 'allocation_id', 'package_name', 'package_id', 'final_price', 'net_paid', 'remaining_balance', 'amount_paid', 'total_refunded', 'excess_amount', 'allocated_sessions', 'granted_credits', 'derived_credit_balance', 'details', 'status']));
                $row = [$label, (string) ($item['record_id'] ?? $item['payment_id'] ?? $item['package_id'] ?? $item['booking_id']), (string) ($item['student_id'] ?? implode(', ', $item['related_student_ids'] ?? [])), (string) ($item['student_name'] ?? ''), (string) json_encode($details, JSON_UNESCAPED_UNICODE), (string) ($item['issue'] ?? $item['type'] ?? '')];
                if (! empty($filters['q']) && ! str_contains(mb_strtolower(implode(' ', $row)), mb_strtolower(trim($filters['q'])))) {
                    continue;
                }
                $rows->push($row);
            }
        }

        return $rows;
    }

    /** @return list<string> */
    public function headers(): array
    {
        return ['Discrepancy Category', 'Record ID', 'Student ID', 'Student Name', 'Details', 'Issue Description'];
    }
}
