<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\Models\SessionType;
use App\Domains\Students\Models\EntitlementType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class SyncDiagnosticSessionType
{
    /**
     * Synchronize the canonical Diagnostic & Learning Roadmap session type
     * and deactivate legacy session types without deleting historical records.
     */
    public function execute(): SessionType
    {
        $lockName = 'arabic_tutoring_sync_diagnostic_session_type';

        return DB::transaction(function () use ($lockName): SessionType {
            $lockHeld = $this->acquireDatabaseMutex($lockName);

            try {
                // Prefer the stable slug. If an older installation has no
                // slug, reconcile the earliest exact-title record instead of
                // creating a second canonical row.
                $canonical = SessionType::query()
                    ->where('slug', 'diagnostic-session')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if (! $canonical) {
                    $canonical = SessionType::query()
                        ->where('title', 'Diagnostic & Learning Roadmap')
                        ->orderBy('created_at')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->first();
                }

                if (! $canonical) {
                    try {
                        $canonical = SessionType::create([
                            'funding_mode' => 'package',
                            'required_entitlement_type_id' => EntitlementType::query()->where('code', 'one_hour')->value('id'),
                            'required_entitlement_units' => 1,
                            'slug' => 'diagnostic-session',
                            'title' => 'Diagnostic & Learning Roadmap',
                            'description' => 'Initial 60-minute diagnostic assessment and customized learning roadmap for Egyptian Arabic fluency with Abdallah.',
                            'duration_minutes' => 60,
                            'price' => 25.00,
                            'currency' => 'USD',
                            'active' => true,
                        ]);
                    } catch (QueryException $exception) {
                        // A second process may have won the unique slug race
                        // on an engine without advisory locks.
                        if (! str_contains($exception->getMessage(), '1062') && $exception->getCode() !== '23000') {
                            throw $exception;
                        }

                        $canonical = SessionType::query()
                            ->where('slug', 'diagnostic-session')
                            ->lockForUpdate()
                            ->firstOrFail();
                    }
                }

                $canonical->fill([
                    'funding_mode' => 'package',
                    'required_entitlement_type_id' => EntitlementType::query()->where('code', 'one_hour')->value('id'),
                    'required_entitlement_units' => 1,
                    'slug' => 'diagnostic-session',
                    'title' => 'Diagnostic & Learning Roadmap',
                    'description' => 'Initial 60-minute diagnostic assessment and customized learning roadmap for Egyptian Arabic fluency with Abdallah.',
                    'duration_minutes' => 60,
                    'price' => 25.00,
                    'currency' => 'USD',
                    'active' => true,
                ])->save();

                // Lock and deactivate every other record. Nothing is deleted,
                // so historical bookings retain their original foreign keys.
                SessionType::query()
                    ->where('id', '!=', $canonical->id)
                    ->lockForUpdate()
                    ->update(['active' => false]);

                return $canonical->fresh();
            } finally {
                if ($lockHeld) {
                    DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);
                }
            }
        }, 5);
    }

    protected function acquireDatabaseMutex(string $lockName): bool
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return false;
        }

        $result = DB::selectOne('SELECT GET_LOCK(?, 30) AS acquired', [$lockName]);
        if ((int) ($result->acquired ?? 0) !== 1) {
            throw new \RuntimeException('Unable to acquire the session-type synchronization mutex.');
        }

        return true;
    }
}
