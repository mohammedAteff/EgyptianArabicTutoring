<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /**
     * Record an administrative audit log.
     *
     * @param  array<string, mixed>|null  $previousData
     * @param  array<string, mixed>|null  $newData
     */
    public function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $previousData = null,
        ?array $newData = null,
        ?int $adminId = null
    ): AuditLog {
        $administratorId = $adminId ?? Auth::guard('web')->id();

        return AuditLog::create([
            'administrator_id' => $administratorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'previous_data' => $previousData,
            'new_data' => $newData,
            'ip_address' => null,
            'created_at' => now(),
        ]);
    }

    /**
     * Record an event performed by an authenticated student without storing submitted identity data.
     *
     * @param  array<string, mixed>|null  $previousData
     * @param  array<string, mixed>|null  $newData
     */
    public function logStudent(
        int $studentId,
        string $action,
        string $targetType,
        ?int $targetId = null,
        ?array $previousData = null,
        ?array $newData = null
    ): AuditLog {
        return AuditLog::create([
            'actor_type' => 'student',
            'actor_student_id' => $studentId,
            'action' => $action,
            'entity_type' => $targetType,
            'entity_id' => $targetId,
            'target_type' => $targetType,
            'target_id' => $targetId === null ? null : (string) $targetId,
            'old_values' => $previousData,
            'new_values' => $newData,
            'ip_address' => null,
            'created_at' => now('UTC'),
        ]);
    }
}
