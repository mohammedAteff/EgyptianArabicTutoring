<?php

namespace App\Domains\System\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Analytics\Services\AnalyticsLifecycle;
use App\Domains\Analytics\Services\EngagementCounterService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Students\Models\Student;
use App\Domains\System\Models\DevelopmentDataOperation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DevelopmentDataResetService
{
    public function __construct(private DevelopmentToolsAccess $access, private DevelopmentDataGraph $graph,
        private DevelopmentDataFiles $files, private DevelopmentDataReconciler $reconciler,
        private StudentSessionCleanupService $sessions, private ManagedBackupCatalog $managed) {}

    /** The operation coordinator owns the database transaction and protected snapshot.
     * @param array<string, mixed> $plan
     * @return array<string, mixed> */
    public function run(Administrator $actor, string $domain, array $plan, string $identity, ?DevelopmentDataOperation $operation = null): array
    {
        $this->access->authorize($actor);
        if (DB::transactionLevel() < 1) {
            throw ValidationException::withMessages(['operation' => 'A reset requires its operation transaction.']);
        }
        $this->access->assertConfirmed($actor, $operation, 'reset', $domain);
        if ($domain === 'backups') {
            return $this->resetBackups($plan, $identity);
        }
        $inventory = $this->files->inventory($plan);
        $deletable = $this->files->deletable($inventory, $plan);
        $copies = $this->files->quarantine($deletable, $identity);
        try {
            foreach ($plan['detach'] as $table => $records) {
                foreach ($records as $id => $changes) {
                    if (($changes['withdrawn_at'] ?? null) === '@operation_time') {
                        $changes['withdrawn_at'] = now('UTC')->toDateTimeString();
                    }
                    DB::table($table)->where('id', $id)->update($changes);
                }
            }
            $bookingIds = array_keys($plan['rows']['bookings'] ?? []);
            if ($bookingIds !== []) {
                DB::table('bookings')->whereIn('id', $bookingIds)->update([
                    'funding_mode' => 'legacy', 'entitlement_type_id' => null, 'entitlement_code' => null, 'entitlement_units' => null,
                    'student_package_id' => null, 'student_package_entitlement_id' => null, 'consumed_ledger_entry_id' => null, 'request_fingerprint' => null,
                ]);
            }
            if (! empty($plan['rows']['students'])) {
                DB::table('students')->whereIn('id', array_keys($plan['rows']['students']))->update(['possible_duplicate_of_student_id' => null, 'merged_into_student_id' => null]);
            }
            $this->clearReferences($plan);
            $order = $this->deleteOrder(array_keys($plan['rows']));
            foreach ($order as $table) {
                $primary = $this->graph->schema()[$table]['primary'][0];
                foreach (array_chunk(array_keys($plan['rows'][$table]), 500) as $ids) {
                    DB::table($table)->whereIn($primary, $ids)->delete();
                }
                if (DB::table($table)->whereIn($primary, array_keys($plan['rows'][$table]))->exists()) {
                    throw ValidationException::withMessages(['reconciliation' => 'Reset left an owned record behind. The operation was rolled back.']);
                }
            }
            $clearedSessions = $domain === 'students' ? $this->sessions->clear() : 0;
            if ($domain === 'analytics') {
                Setting::set('analytics_reset_booking_floor', (int) DB::table('bookings')->max('id'), 'development_lifecycle');
                Setting::set('analytics_collection_generation', (string) Str::uuid(), 'development_lifecycle');
                app(AnalyticsLifecycle::class)->forget();
            }
            $this->files->remove($copies);
            $reconciliation = $this->reconciler->verify(in_array($domain, ['students', 'financial'], true));
            DB::afterCommit(function () use ($copies, $plan): void {
                $this->files->discard($copies);
                Cache::forget(EngagementCounterService::cacheKey());
                foreach (array_keys($plan['rows']['students'] ?? []) as $id) {
                    Cache::forget('student_auth_check_'.$id);
                }
            });

            return ['deleted_records' => $plan['counts'], 'deleted_files' => count($deletable), 'shared_files_preserved' => count($inventory) - count($deletable),
                'cleared_student_sessions' => $clearedSessions, 'reconciliation' => $reconciliation];
        } catch (\Throwable $exception) {
            $this->files->compensate($copies);
            throw $exception;
        }
    }

    /** @param list<string> $tables
     * @return list<string> */
    private function deleteOrder(array $tables): array
    {
        $remaining = array_fill_keys($tables, true);
        $order = [];
        while ($remaining !== []) {
            $ready = [];
            foreach (array_keys($remaining) as $parent) {
                $blocked = false;
                foreach (array_keys($remaining) as $child) {
                    if ($child === $parent) {
                        continue;
                    }
                    foreach ($this->graph->schema()[$child]['foreign_keys'] as $foreign) {
                        if ($foreign['foreign_table'] === $parent && ! ($child === 'bookings' && $parent === 'session_ledger_entries')) {
                            $blocked = true;
                        }
                    }
                }
                if (! $blocked) {
                    $ready[] = $parent;
                }
            }
            if ($ready === []) {
                throw ValidationException::withMessages(['scope' => 'An unreviewed dependency cycle prevents a safe reset.']);
            }
            foreach ($ready as $table) {
                $order[] = $table;
                unset($remaining[$table]);
            }
        }

        return $order;
    }

    /** @param array<string, mixed> $plan */
    private function clearReferences(array $plan): void
    {
        $studentIds = array_keys($plan['rows']['students'] ?? []);
        $bookingIds = array_keys($plan['rows']['bookings'] ?? []);
        if ($studentIds !== []) {
            DB::table('audit_logs')->whereIn('actor_student_id', $studentIds)->update(['actor_student_id' => null,
                'previous_data' => null, 'old_values' => null, 'new_data' => null, 'new_values' => null, 'ip_address' => null, 'user_agent' => null]);
            DB::table('audit_logs')->whereIn('entity_type', ['student', Student::class])->whereIn('entity_id', $studentIds)
                ->update(['previous_data' => null, 'old_values' => null, 'new_data' => null, 'new_values' => null, 'ip_address' => null, 'user_agent' => null]);
            DB::table('visitors')->whereIn('student_id', $studentIds)->update(['student_id' => null]);
            DB::table('staff_recent_views')->where('entity_type', 'student')->whereIn('entity_id', $studentIds)->delete();
        }
        if ($bookingIds !== []) {
            DB::table('staff_recent_views')->where('entity_type', 'booking')->whereIn('entity_id', $bookingIds)->delete();
        }
    }

    /** @param array<string, mixed> $plan
     * @return array<string, mixed> */
    private function resetBackups(array $plan, string $identity): array
    {
        $disk = Storage::disk('managed_backups');
        $copies = [];
        try {
            foreach ($plan['managed_snapshots'] as $item) {
                $contents = $disk->get($item['filename']);
                $copy = $this->files->operationPath($identity, 'quarantine').'/managed-'.$item['sha256'].'.zip';
                if (! is_string($contents) || hash('sha256', $contents) !== $item['sha256']
                    || ! $this->files->disk()->put($copy, $contents, 'private')) {
                    throw ValidationException::withMessages(['backups' => 'A managed snapshot changed or could not be protected.']);
                }
                $copies[$item['filename']] = $copy;
            }
            foreach ($copies as $filename => $copy) {
                if (! $disk->delete($filename) || $disk->exists($filename)) {
                    throw ValidationException::withMessages(['backups' => 'A managed snapshot could not be removed. Recovery compensation will restore the reset set.']);
                }
            }
            foreach (['last_backup_at', 'last_backup_status', 'last_backup_file', 'last_offsite_backup_status', 'last_offsite_backup_category'] as $key) {
                DB::table('settings')->where('key', $key)->delete();
            }
            if ($this->managed->inventory() !== []) {
                throw ValidationException::withMessages(['backups' => 'Managed snapshot reconciliation failed. Nothing was committed.']);
            }
            $reconciliation = $this->reconciler->verify();
            DB::afterCommit(fn () => $this->files->discard($copies));

            return ['deleted_managed_snapshots' => count($copies), 'managed_snapshots_remaining' => 0, 'reconciliation' => $reconciliation];
        } catch (\Throwable $exception) {
            foreach ($copies as $filename => $copy) {
                $contents = $this->files->disk()->get($copy);
                if (! is_string($contents) || ! $disk->put($filename, $contents, 'private')) {
                    throw ValidationException::withMessages(['backups' => 'Restore the original managed snapshots from the protected recovery set before retrying.']);
                }
            }
            throw $exception;
        }
    }
}
