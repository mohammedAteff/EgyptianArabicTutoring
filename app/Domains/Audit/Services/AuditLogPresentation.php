<?php

namespace App\Domains\Audit\Services;

use App\Domains\Administration\Services\OperationalReasonCatalog;
use App\Domains\Audit\Models\AuditLog;

class AuditLogPresentation
{
    /** @return array<int, array{field: string, before: string, after: string}> */
    public function diff(AuditLog $log): array
    {
        $before = $log->old_values ?? $log->previous_data ?? [];
        $after = $log->new_values ?? $log->new_data ?? [];
        $rows = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
            if (! is_string($field) || ! preg_match('/^(status|operational_status|active|enabled|funding_mode|outcome|reason_code|cancellation_cutoff_hours|late_cancellation|staff_cancellation|no_show|student_id|booking_id|session_type_id|previous_package_id|new_package_id|primary_student_id|secondary_student_id|merged_student_id|surviving_student_id|installment_count|expiration_date|date|start_at_utc|end_at_utc)$/', $field)) {
                continue;
            }
            $old = $this->value($field, $before[$field] ?? null);
            $new = $this->value($field, $after[$field] ?? null);
            if ($old !== $new) {
                $rows[] = ['field' => str_replace('_', ' ', $field), 'before' => $old, 'after' => $new];
            }
        }

        return $rows;
    }

    private function value(string $field, mixed $value): string
    {
        if ($value === null) {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if ((str_ends_with($field, '_id') || in_array($field, ['installment_count', 'cancellation_cutoff_hours'], true)) && is_numeric($value)) {
            return (string) $value;
        }
        $enums = ['active', 'inactive', 'archived', 'confirmed', 'cancelled', 'completed', 'pending', 'no_show', 'paused', 'open', 'closed', 'contacted', 'withdrawn', 'scheduled', 'voided', 'package', 'direct', 'free', 'legacy', 'restore', 'retain', 'deny', ...array_keys(OperationalReasonCatalog::all())];
        if (is_string($value) && in_array($value, $enums, true)) {
            return str_replace('_', ' ', $value);
        }
        if (is_string($value) && in_array($field, ['expiration_date', 'date', 'start_at_utc', 'end_at_utc'], true) && preg_match('/^\\d{4}-\\d{2}-\\d{2}(?:[ T]\\d{2}:\\d{2}:\\d{2})?$/', $value)) {
            return $value;
        }

        return '[hidden]';
    }
}
