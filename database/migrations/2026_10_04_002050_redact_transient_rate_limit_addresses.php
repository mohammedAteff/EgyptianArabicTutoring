<?php

use App\Domains\Audit\Services\TransientRateLimitKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = DB::connection(config('cache.stores.database.connection'));
        $table = (string) config('cache.stores.database.table', 'cache');
        if (! $connection->getSchemaBuilder()->hasTable($table)) {
            return;
        }
        $prefix = (string) config('cache.prefix');
        $counts = ['expired_rate_limit_rows' => 0, 'active_rate_limit_rows_rekeyed' => 0];
        $connection->table($table)->orderBy('key')->chunkById(200, function ($rows) use ($connection, $table, $prefix, &$counts): void {
            foreach ($rows as $row) {
                if (! str_starts_with($row->key, $prefix)) {
                    continue;
                }
                $logicalKey = substr($row->key, strlen($prefix));
                $timer = str_ends_with($logicalKey, ':timer');
                $logicalKey = $timer ? substr($logicalKey, 0, -6) : $logicalKey;
                if (preg_match('/^(throttle:(?:hold|booking-confirm):ip:)(.+)$/', $logicalKey, $match) && filter_var($match[2], FILTER_VALIDATE_IP)) {
                    $newKey = TransientRateLimitKey::make(rtrim($match[1], ':'), $match[2]);
                } elseif (preg_match('/^(.+)\|([^|]+)$/', $logicalKey, $match) && filter_var($match[2], FILTER_VALIDATE_IP)) {
                    $newKey = TransientRateLimitKey::make('staff-login', $logicalKey);
                } else {
                    continue;
                }
                if ($row->expiration <= now()->timestamp) {
                    $counts['expired_rate_limit_rows'] += $connection->table($table)->where('key', $row->key)->delete();

                    continue;
                }
                $newKey = $prefix.$newKey.($timer ? ':timer' : '');
                $connection->transaction(function () use ($connection, $table, $row, $newKey, $timer): void {
                    $existing = $connection->table($table)->where('key', $newKey)->lockForUpdate()->first();
                    $value = $row->value;
                    if ($existing) {
                        $oldValue = unserialize($value, ['allowed_classes' => false]);
                        $currentValue = unserialize($existing->value, ['allowed_classes' => false]);
                        if (! is_int($oldValue) || ! is_int($currentValue)) {
                            throw new RuntimeException('Unexpected rate-limit cache value; historical cache row retained.');
                        }
                        $value = serialize($timer ? max($oldValue, $currentValue) : $oldValue + $currentValue);
                    }
                    $connection->table($table)->updateOrInsert(['key' => $newKey], ['value' => $value, 'expiration' => max((int) $row->expiration, (int) ($existing->expiration ?? 0))]);
                    $connection->table($table)->where('key', $row->key)->delete();
                });
                $counts['active_rate_limit_rows_rekeyed']++;
            }
        }, 'key');
        Log::info('Transient request-address cache cleanup completed (counts only).', $counts);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /* Address-bearing keys are not reconstructed. Counters retain their original expirations. */
    }
};
