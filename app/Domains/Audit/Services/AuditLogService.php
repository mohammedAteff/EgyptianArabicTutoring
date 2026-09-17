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
        $administratorId = $adminId ?? Auth::id();

        return AuditLog::create([
            'administrator_id' => $administratorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'previous_data' => $previousData,
            'new_data' => $newData,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
