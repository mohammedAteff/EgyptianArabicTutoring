<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $counts = [];
        foreach (['audit_logs', 'session_reschedules', 'maintenance_visits', 'sessions'] as $table) {
            if (Schema::hasColumn($table, 'ip_address')) {
                $counts[$table] = DB::table($table)->whereNotNull('ip_address')->count();
                DB::table($table)->whereNotNull('ip_address')->update(['ip_address' => null]);
            }
        }
        $scrub = function (mixed $value) use (&$scrub): mixed {
            if (is_array($value)) {
                foreach ($value as $key => $child) {
                    $normalized = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', (string) $key), '_'));
                    $value[$key] = in_array($normalized, ['ip', 'ip_address', 'client_ip', 'request_ip', 'remote_addr', 'remote_address'], true) ? '[redacted]' : $scrub($child);
                }
            } elseif (is_string($value)) {
                if (filter_var(trim($value), FILTER_VALIDATE_IP)) {
                    return '[redacted]';
                }
                $value = preg_replace_callback('/(?<![\w.])(?:\d{1,3}\.){3}\d{1,3}(?![\w.])/', fn (array $match): string => filter_var($match[0], FILTER_VALIDATE_IP) ? '[redacted]' : $match[0], $value) ?? $value;
                $value = preg_replace_callback('/(?<![\w:])(?:[a-f0-9]{0,4}:){2,}[a-f0-9:.]+(?![\w:])/i', fn (array $match): string => filter_var($match[0], FILTER_VALIDATE_IP) ? '[redacted]' : $match[0], $value) ?? $value;
            }

            return $value;
        };
        $counts['audit_payload_rows'] = 0;
        DB::table('audit_logs')->orderBy('id')->chunkById(200, function ($rows) use ($scrub, &$counts): void {
            foreach ($rows as $row) {
                $updates = [];
                foreach (['previous_data', 'new_data', 'old_values', 'new_values'] as $column) {
                    if ($row->$column === null) {
                        continue;
                    }
                    $original = json_decode($row->$column, true, 512, JSON_THROW_ON_ERROR);
                    $redacted = $scrub($original);
                    if ($redacted !== $original) {
                        $updates[$column] = json_encode($redacted, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                    }
                }
                if ($updates !== []) {
                    DB::table('audit_logs')->where('id', $row->id)->update($updates);
                    $counts['audit_payload_rows']++;
                }
            }
        });
        Log::info('Persisted request-address purge completed (counts only).', $counts);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /* Intentional: erased addresses cannot be reconstructed. Restore a protected pre-migration backup if recovery is required. */
    }
};
