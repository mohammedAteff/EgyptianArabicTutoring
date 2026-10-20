<?php

namespace App\Console\Commands;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Models\StudentPackage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApplyBookingPolicyCorrections extends Command
{
    protected $signature = 'booking:apply-policy-corrections {--apply : Apply the reviewed policy changes} {--administrator= : Super administrator responsible for the correction}';

    protected $description = 'Preview or apply approved session labels/prices and remove expiry from diagnostic and single-session purchases';

    public function handle(DatabaseCapability $database, AuditLogService $audit): int
    {
        $sessions = [
            'egyptian-arabic-session' => ['title' => 'Two-Hour Single Session', 'description' => 'A flexible, private two-hour Arabic session tailored to your learning goals.', 'price' => '48.00', 'duration_minutes' => 120],
            'diagnostic-session' => ['title' => 'Diagnostic & Learning Roadmap', 'description' => 'A 60-minute diagnostic and learning consultation, followed by your personalized written learning roadmap.', 'price' => '25.00', 'duration_minutes' => 60],
            'advanced-conversational' => ['title' => 'One-Hour Single Session', 'description' => 'A flexible, private one-hour Arabic session tailored to your learning goals.', 'price' => '28.00', 'duration_minutes' => 60],
        ];
        $keys = ['diagnostic_roadmap', 'payg_maintenance', 'advanced_conversational'];
        $packages = StudentPackage::query()->whereIn('offering_key', $keys)->where('identity_state', 'mapped')
            ->where(fn ($query) => $query->whereNotNull('validity_days')->orWhereNotNull('expiration_date')->orWhereNotNull('expires_at'));
        $this->line(json_encode(['sessions' => SessionType::query()->whereIn('slug', array_keys($sessions))->get(['id', 'slug', 'title', 'price'])->toArray(), 'purchase_ids' => (clone $packages)->pluck('id')->all()], JSON_THROW_ON_ERROR));
        if (! $this->option('apply')) {
            return self::SUCCESS;
        }
        $administrator = Administrator::query()->whereKey($this->option('administrator'))->where('role', 'super_admin')->whereNull('suspended_at')->firstOrFail();
        $database->transaction(function () use ($sessions, $packages, $administrator, $audit): void {
            foreach ($sessions as $slug => $values) {
                $session = SessionType::query()->where('slug', $slug)->lockForUpdate()->firstOrFail();
                if ($session->duration_minutes !== $values['duration_minutes'] || $session->currency !== 'USD') {
                    throw new RuntimeException('Review changed duration or currency before applying: '.$slug);
                }
                $before = $session->only(array_keys($values));
                $session->fill($values);
                if ($session->isDirty()) {
                    $session->save();
                    $audit->log('session_type_policy_corrected', SessionType::class, $session->id, $before, $session->only(array_keys($values)), $administrator->id);
                }
            }
            foreach ((clone $packages)->orderBy('id')->lockForUpdate()->get() as $package) {
                if ($package->ledgerEntries()->where('entry_type', 'expiration_forfeit')->exists()) {
                    throw new RuntimeException('Review forfeited credits before removing expiry for purchase '.$package->id);
                }
                $before = $package->only(['validity_days', 'validity_started_at', 'expires_at', 'expiration_date']);
                $after = ['validity_days' => null, 'validity_started_at' => null, 'expires_at' => null, 'expiration_date' => null];
                // This explicit, audited policy correction is the only exception to immutable purchase terms.
                DB::table('student_packages')->where('id', $package->id)->update($after);
                $audit->log('single_session_expiry_policy_corrected', StudentPackage::class, $package->id, $before, $after, $administrator->id);
            }
        }, 3);
        $this->info('Approved policy corrections applied. Bookings, payments and credit ledger entries were preserved.');

        return self::SUCCESS;
    }
}
